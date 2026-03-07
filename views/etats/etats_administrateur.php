<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();

if(!isset($_SESSION['user_id']) || $_SESSION['user_role'] != 'admin') {
    header("Location: ../auth/login.php");
    exit();
}

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../models/Tontine.php';
require_once __DIR__ . '/../../models/MembreTontine.php';
require_once __DIR__ . '/../../models/Cotisation.php';
require_once __DIR__ . '/../../models/Seance.php';
require_once __DIR__ . '/../../models/AmendeAppliquee.php';

$database = new Database();
$db = $database->getConnection();

$tontine_id = $_GET['tontine_id'] ?? 0;

if(!$tontine_id) {
    header("Location: ../tontine/mes_tontines.php");
    exit();
}

$tontine = new Tontine($db);
if(!$tontine->getById($tontine_id) || $tontine->admin_id != $_SESSION['user_id']) {
    header("Location: ../tontine/mes_tontines.php");
    exit();
}

$membreTontine = new MembreTontine($db);
$cotisation = new Cotisation($db);
$amendeAppliquee = new AmendeAppliquee($db);

// ============================================
// 1. ÉTAT GÉNÉRAL DE LA TONTINE
// ============================================

// Total membres actifs
$total_membres = $membreTontine->countMembres($tontine_id);

// Total collecté (toutes séances confondues)
$query = "SELECT SUM(montant) as total FROM cotisations c
          JOIN seances s ON c.seance_id = s.id
          WHERE s.tontine_id = :tid AND c.statut = 'paye'";
$stmt = $db->prepare($query);
$stmt->execute(['tid' => $tontine_id]);
$total_collecte = $stmt->fetch()['total'] ?? 0;

// Total distribué (somme des montants des séances clôturées)
$query = "SELECT SUM(total_collecte) as total FROM seances 
          WHERE tontine_id = :tid AND est_cloturee = 1";
$stmt = $db->prepare($query);
$stmt->execute(['tid' => $tontine_id]);
$total_distribue = $stmt->fetch()['total'] ?? 0;

// Solde des amendes (amendes payées)
$query = "SELECT SUM(a.montant) as total FROM amendes_appliquees a
          JOIN seances s ON a.seance_id = s.id
          WHERE s.tontine_id = :tid AND a.est_paye = 1";
$stmt = $db->prepare($query);
$stmt->execute(['tid' => $tontine_id]);
$solde_amendes = $stmt->fetch()['total'] ?? 0;

// ============================================
// 2. ÉTAT DES MEMBRES
// ============================================

// Membres à jour (pas de retard)
$query = "SELECT COUNT(DISTINCT mt.id) as total FROM membre_tontine mt
          WHERE mt.tontine_id = :tid AND mt.est_actif = 1
          AND NOT EXISTS (
              SELECT 1 FROM cotisations c
              JOIN seances s ON c.seance_id = s.id
              WHERE c.membre_tontine_id = mt.id AND c.statut = 'retard'
          )";
$stmt = $db->prepare($query);
$stmt->execute(['tid' => $tontine_id]);
$membres_a_jour = $stmt->fetch()['total'];

// Membres en retard
$query = "SELECT COUNT(DISTINCT mt.id) as total FROM membre_tontine mt
          WHERE mt.tontine_id = :tid AND mt.est_actif = 1
          AND EXISTS (
              SELECT 1 FROM cotisations c
              JOIN seances s ON c.seance_id = s.id
              WHERE c.membre_tontine_id = mt.id AND c.statut = 'retard'
          )";
$stmt = $db->prepare($query);
$stmt->execute(['tid' => $tontine_id]);
$membres_en_retard = $stmt->fetch()['total'];

// Membres exclus (inactifs)
$query = "SELECT COUNT(*) as total FROM membre_tontine 
          WHERE tontine_id = :tid AND est_actif = 0";
$stmt = $db->prepare($query);
$stmt->execute(['tid' => $tontine_id]);
$membres_exclus = $stmt->fetch()['total'];

// Historique des pénalités par membre
$query = "SELECT u.nom, u.prenom, 
                 COUNT(a.id) as nb_amendes, 
                 SUM(a.montant) as total_amendes
          FROM membre_tontine mt
          JOIN users u ON mt.user_id = u.id
          LEFT JOIN amendes_appliquees a ON a.membre_tontine_id = mt.id
          WHERE mt.tontine_id = :tid
          GROUP BY mt.id
          ORDER BY total_amendes DESC";
$stmt = $db->prepare($query);
$stmt->execute(['tid' => $tontine_id]);
$penalites_membres = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ============================================
// 3. ÉTAT PAR SÉANCE
// ============================================

$query = "SELECT s.*, 
                 CONCAT(u.prenom, ' ', u.nom) as beneficiaire_nom,
                 (SELECT COUNT(*) FROM membre_tontine WHERE tontine_id = :tid AND est_actif = 1) as nb_membres,
                 (SELECT SUM(montant) FROM cotisations WHERE seance_id = s.id AND statut = 'paye') as total_encaisse
          FROM seances s
          LEFT JOIN membre_tontine mt ON s.beneficiaire_id = mt.id
          LEFT JOIN users u ON mt.user_id = u.id
          WHERE s.tontine_id = :tid
          ORDER BY s.date_seance DESC";
$stmt = $db->prepare($query);
$stmt->execute(['tid' => $tontine_id]);
$seances = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ============================================
// 4. ÉTAT DES RETARDS
// ============================================

// Classement des plus réguliers
$query = "SELECT u.nom, u.prenom, 
                 COUNT(c.id) as nb_cotisations,
                 SUM(CASE WHEN c.statut = 'retard' THEN 1 ELSE 0 END) as nb_retards,
                 SUM(CASE WHEN c.statut = 'paye' THEN 1 ELSE 0 END) as nb_paiements
          FROM membre_tontine mt
          JOIN users u ON mt.user_id = u.id
          LEFT JOIN cotisations c ON c.membre_tontine_id = mt.id
          WHERE mt.tontine_id = :tid
          GROUP BY mt.id
          ORDER BY nb_retards ASC, nb_paiements DESC";
$stmt = $db->prepare($query);
$stmt->execute(['tid' => $tontine_id]);
$classement_membres = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>États administrateur - <?= htmlspecialchars($tontine->nom) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.8.1/font/bootstrap-icons.css">
    <style>
        body {
            background: linear-gradient(135deg, #f5f0ff 0%, #fff5f0 100%);
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        .navbar {
            background: linear-gradient(135deg, #6B46C1 0%, #FF8A4C 100%);
        }
        .card {
            border-radius: 15px;
            border: none;
            box-shadow: 0 10px 40px rgba(107, 70, 193, 0.1);
            margin-bottom: 20px;
        }
        .card-header {
            background: linear-gradient(135deg, #6B46C1 0%, #FF8A4C 100%);
            color: white;
            border-radius: 15px 15px 0 0 !important;
        }
        .stat-card {
            background: white;
            border-radius: 15px;
            padding: 20px;
            text-align: center;
            box-shadow: 0 5px 20px rgba(0,0,0,0.05);
            height: 100%;
        }
        .stat-number {
            font-size: 32px;
            font-weight: 700;
            color: #6B46C1;
        }
        .stat-label {
            color: #666;
            font-size: 14px;
            text-transform: uppercase;
        }
        .table th {
            background: #f8f9fa;
        }
        .badge-success { background: #28a745; color: white; }
        .badge-warning { background: #ffc107; color: black; }
        .badge-danger { background: #dc3545; color: white; }
        .section-title {
            margin: 30px 0 20px;
            font-weight: 600;
            color: #333;
            border-bottom: 2px solid #6B46C1;
            padding-bottom: 10px;
        }
        .nav-tabs .nav-link {
            color: #6B46C1;
            font-weight: 600;
        }
        .nav-tabs .nav-link.active {
            background: linear-gradient(135deg, #6B46C1 0%, #FF8A4C 100%);
            color: white;
            border: none;
        }
    </style>
</head>
<body>

    <nav class="navbar navbar-expand-lg navbar-dark">
        <div class="container">
            <a class="navbar-brand" href="../dashboard.php">
                <i class="bi bi-bank2"></i> TONTONTINE
            </a>
            <div class="navbar-nav ms-auto">
                <span class="nav-link text-white">
                    <i class="bi bi-building"></i> <?= htmlspecialchars($_SESSION['association_nom']) ?>
                </span>
                <a class="nav-link text-white" href="../tontine/mes_tontines.php">
                    <i class="bi bi-arrow-left"></i> Retour
                </a>
            </div>
        </div>
    </nav>

    <div class="container mt-5">
        
        <h2 class="mb-4"><i class="bi bi-file-text"></i> États - <?= htmlspecialchars($tontine->nom) ?></h2>

        <!-- Navigation par onglets -->
        <ul class="nav nav-tabs mb-4" id="myTab" role="tablist">
            <li class="nav-item" role="presentation">
                <button class="nav-link active" id="general-tab" data-bs-toggle="tab" data-bs-target="#general" type="button">
                     État général
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="membres-tab" data-bs-toggle="tab" data-bs-target="#membres" type="button">
                     État des membres
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="seances-tab" data-bs-toggle="tab" data-bs-target="#seances" type="button">
                     État par séance
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="retards-tab" data-bs-toggle="tab" data-bs-target="#retards" type="button">
                     État des retards
                </button>
            </li>
        </ul>

        <div class="tab-content" id="myTabContent">
            
            <!-- 1. ÉTAT GÉNÉRAL -->
            <div class="tab-pane fade show active" id="general" role="tabpanel">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="bi bi-pie-chart"></i> État général de la tontine</h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-3 mb-3">
                                <div class="stat-card">
                                    <div class="stat-number"><?= $total_membres ?></div>
                                    <div class="stat-label">Membres actifs</div>
                                </div>
                            </div>
                            <div class="col-md-3 mb-3">
                                <div class="stat-card">
                                    <div class="stat-number"><?= number_format($total_collecte ?? 0, 0, ',', ' ') ?> F</div>
                                    <div class="stat-label">Total collecté</div>
                                </div>
                            </div>
                            <div class="col-md-3 mb-3">
                                <div class="stat-card">
                                    <div class="stat-number"><?= number_format($total_distribue ?? 0, 0, ',', ' ') ?> F</div>
                                    <div class="stat-label">Total distribué</div>
                                </div>
                            </div>
                            <div class="col-md-3 mb-3">
                                <div class="stat-card">
                                    <div class="stat-number"><?= number_format($solde_amendes ?? 0, 0, ',', ' ') ?> F</div>
                                    <div class="stat-label">Solde amendes</div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- 2. ÉTAT DES MEMBRES -->
            <div class="tab-pane fade" id="membres" role="tabpanel">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="bi bi-people"></i> État des membres</h5>
                    </div>
                    <div class="card-body">
                        <div class="row mb-4">
                            <div class="col-md-4">
                                <div class="stat-card">
                                    <div class="stat-number text-success"><?= $membres_a_jour ?></div>
                                    <div class="stat-label">À jour</div>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="stat-card">
                                    <div class="stat-number text-warning"><?= $membres_en_retard ?></div>
                                    <div class="stat-label">En retard</div>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="stat-card">
                                    <div class="stat-number text-danger"><?= $membres_exclus ?></div>
                                    <div class="stat-label">Exclus</div>
                                </div>
                            </div>
                        </div>

                        <h6 class="mt-4 mb-3">Historique des pénalités</h6>
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Membre</th>
                                    <th>Nombre d'amendes</th>
                                    <th>Total amendes</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach($penalites_membres as $p): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($p['prenom'] . ' ' . $p['nom']) ?></td>
                                        <td><?= $p['nb_amendes'] ?></td>
                                        <td><?= number_format($p['total_amendes'] ?? 0, 0, ',', ' ') ?> F</td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- 3. ÉTAT PAR SÉANCE -->
            <div class="tab-pane fade" id="seances" role="tabpanel">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="bi bi-calendar-week"></i> État par séance</h5>
                    </div>
                    <div class="card-body">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Date</th>
                                    <th>Montant prévu</th>
                                    <th>Montant réel</th>
                                    <th>Écart</th>
                                    <th>Bénéficiaire</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach($seances as $s): 
                                    $prevu = $s['nb_membres'] * $tontine->montant_cotisation;
                                    $reel = $s['total_encaisse'] ?? 0;
                                    $ecart = $reel - $prevu;
                                ?>
                                    <tr>
                                        <td><?= date('d/m/Y', strtotime($s['date_seance'])) ?></td>
                                        <td><?= number_format($prevu ?? 0, 0, ',', ' ') ?> F</td>
                                       <td><?= number_format($reel ?? 0, 0, ',', ' ') ?> F</td>
                                        <td class="<?= $ecart >= 0 ? 'text-success' : 'text-danger' ?>">
                                            <td><?= number_format($ecart ?? 0, 0, ',', ' ') ?> F</td>
                                        </td>
                                        <td><?= htmlspecialchars($s['beneficiaire_nom'] ?? '-') ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- 4. ÉTAT DES RETARDS -->
            <div class="tab-pane fade" id="retards" role="tabpanel">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="bi bi-clock-history"></i> État des retards</h5>
                    </div>
                    <div class="card-body">
                        <h6 class="mb-3">Classement des membres les plus réguliers</h6>
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>#</th>
                                    <th>Membre</th>
                                    <th>Paiements</th>
                                    <th>Retards</th>
                                    <th>Régularité</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php 
                                $rang = 1;
                                foreach($classement_membres as $c): 
                                    $total = $c['nb_cotisations'] ?: 0;
                                    $regularite = $total > 0 ? round(($c['nb_paiements'] / $total) * 100) : 0;
                                ?>
                                    <tr>
                                        <td><?= $rang++ ?></td>
                                        <td><?= htmlspecialchars($c['prenom'] . ' ' . $c['nom']) ?></td>
                                        <td><?= $c['nb_paiements'] ?></td>
                                        <td><?= $c['nb_retards'] ?></td>
                                        <td>
                                            <div class="progress">
                                                <div class="progress-bar bg-success" role="progressbar" 
                                                     style="width: <?= $regularite ?>%"
                                                     aria-valuenow="<?= $regularite ?>" aria-valuemin="0" aria-valuemax="100">
                                                    <?= $regularite ?>%
                                                </div>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <!-- Bouton retour -->
        <div class="text-center mt-4">
            <a href="../tontine/mes_tontines.php" class="btn btn-primary">
                <i class="bi bi-arrow-left"></i> Retour à mes tontines
            </a>
        </div>

    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>