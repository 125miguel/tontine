<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();

if(!isset($_SESSION['user_id'])) {
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

$tontine_id = $_GET['id'] ?? 0;
$userId = $_SESSION['user_id'];

if(!$tontine_id) {
    header("Location: ../dashboard.php");
    exit();
}

// Vérifier que le membre appartient bien à cette tontine
$query = "SELECT * FROM membre_tontine 
          WHERE user_id = :uid AND tontine_id = :tid AND est_actif = 1";
$stmt = $db->prepare($query);
$stmt->execute(['uid' => $userId, 'tid' => $tontine_id]);

if($stmt->rowCount() == 0) {
    header("Location: ../dashboard.php");
    exit();
}

$membre = $stmt->fetch(PDO::FETCH_ASSOC);

// Récupérer les infos de la tontine
$tontine = new Tontine($db);
$tontine->getById($tontine_id);

// Récupérer les cotisations du membre pour cette tontine
$cotisation = new Cotisation($db);
$amendeAppliquee = new AmendeAppliquee($db);

// Total cotisé par le membre
$queryTotal = "SELECT SUM(montant) as total FROM cotisations 
               WHERE membre_tontine_id = :mid AND statut = 'paye'";
$stmtTotal = $db->prepare($queryTotal);
$stmtTotal->execute(['mid' => $membre['id']]);
$total_cotise = $stmtTotal->fetch()['total'] ?? 0;

// Amendes du membre
$amendes = $amendeAppliquee->getByMembre($membre['id']);
$amendes_payees = 0;
$amendes_impayees = 0;
foreach($amendes as $a) {
    if($a['est_paye']) {
        $amendes_payees += $a['montant'];
    } else {
        $amendes_impayees += $a['montant'];
    }
}

// Dernières cotisations
$queryDernieres = "SELECT c.*, s.date_seance 
                   FROM cotisations c
                   JOIN seances s ON c.seance_id = s.id
                   WHERE c.membre_tontine_id = :mid 
                   ORDER BY s.date_seance DESC LIMIT 5";
$stmtDernieres = $db->prepare($queryDernieres);
$stmtDernieres->execute(['mid' => $membre['id']]);
$dernieres_cotisations = $stmtDernieres->fetchAll(PDO::FETCH_ASSOC);

// Statistiques globales de la tontine
$queryMembres = "SELECT COUNT(*) as total FROM membre_tontine 
                 WHERE tontine_id = :tid AND est_actif = 1";
$stmtMembres = $db->prepare($queryMembres);
$stmtMembres->execute(['tid' => $tontine_id]);
$total_membres = $stmtMembres->fetch()['total'];

// Prochaine réunion
$prochaine_reunion = $tontine->prochaine_reunion;

// Prochain bénéficiaire (si mode auto)
$prochain_beneficiaire = null;
if($tontine->mode_beneficiaire == 'auto') {
    // Récupérer le dernier bénéficiaire
    $query = "SELECT beneficiaire_id FROM seances 
              WHERE tontine_id = :tid AND beneficiaire_id IS NOT NULL 
              ORDER BY date_seance DESC LIMIT 1";
    $stmt = $db->prepare($query);
    $stmt->execute(['tid' => $tontine_id]);
    $dernier = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if($dernier) {
        // Récupérer l'ordre du dernier
        $query = "SELECT ordre_tour FROM membre_tontine WHERE id = :mid";
        $stmt = $db->prepare($query);
        $stmt->execute(['mid' => $dernier['beneficiaire_id']]);
        $ordre_dernier = $stmt->fetch()['ordre_tour'];
        
        // Chercher le suivant
        $query = "SELECT u.prenom, u.nom, mt.ordre_tour 
                  FROM membre_tontine mt
                  JOIN users u ON mt.user_id = u.id
                  WHERE mt.tontine_id = :tid AND mt.est_actif = 1 
                  AND mt.ordre_tour > :ordre
                  ORDER BY mt.ordre_tour ASC LIMIT 1";
        $stmt = $db->prepare($query);
        $stmt->execute(['tid' => $tontine_id, 'ordre' => $ordre_dernier]);
        $suivant = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if(!$suivant) {
            // Retour au début
            $query = "SELECT u.prenom, u.nom, mt.ordre_tour 
                      FROM membre_tontine mt
                      JOIN users u ON mt.user_id = u.id
                      WHERE mt.tontine_id = :tid AND mt.est_actif = 1 
                      ORDER BY mt.ordre_tour ASC LIMIT 1";
            $stmt = $db->prepare($query);
            $stmt->execute(['tid' => $tontine_id]);
            $suivant = $stmt->fetch(PDO::FETCH_ASSOC);
        }
        
        $prochain_beneficiaire = $suivant;
    } else {
        // Premier bénéficiaire
        $query = "SELECT u.prenom, u.nom, mt.ordre_tour 
                  FROM membre_tontine mt
                  JOIN users u ON mt.user_id = u.id
                  WHERE mt.tontine_id = :tid AND mt.est_actif = 1 
                  ORDER BY mt.ordre_tour ASC LIMIT 1";
        $stmt = $db->prepare($query);
        $stmt->execute(['tid' => $tontine_id]);
        $prochain_beneficiaire = $stmt->fetch(PDO::FETCH_ASSOC);
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Détails - <?= htmlspecialchars($tontine->nom) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.8.1/font/bootstrap-icons.css">
    <style>
        body {
            background: linear-gradient(135deg, #f5f0ff 0%, #fff5f0 100%);
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        .navbar {
            background: linear-gradient(135deg, #6B46C1 0%, #FF8A4C 100%);
            padding: 15px 0;
            box-shadow: 0 2px 20px rgba(0,0,0,0.1);
        }
        .navbar-brand {
            color: white;
            font-size: 24px;
            font-weight: 700;
        }
        .nav-link {
            color: white !important;
        }
        .card {
            border-radius: 15px;
            border: none;
            box-shadow: 0 5px 20px rgba(0,0,0,0.05);
            transition: transform 0.3s;
            margin-bottom: 20px;
        }
        .card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 30px rgba(107, 70, 193, 0.1);
        }
        .card-header {
            background: linear-gradient(135deg, #6B46C1 0%, #FF8A4C 100%);
            color: white;
            border-radius: 15px 15px 0 0 !important;
            font-weight: 600;
        }
        .stat-card {
            background: white;
            border-radius: 15px;
            padding: 25px;
            text-align: center;
            box-shadow: 0 5px 20px rgba(0,0,0,0.05);
            height: 100%;
        }
        .stat-icon {
            font-size: 40px;
            color: #6B46C1;
            margin-bottom: 15px;
        }
        .stat-number {
            font-size: 32px;
            font-weight: 700;
            color: #2D3748;
        }
        .stat-label {
            color: #666;
            font-size: 14px;
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        .amount-positive {
            color: #28a745;
            font-weight: 600;
        }
        .amount-negative {
            color: #dc3545;
            font-weight: 600;
        }
        .badge-paye {
            background: #28a745;
            color: white;
            padding: 5px 10px;
            border-radius: 20px;
        }
        .badge-impaye {
            background: #dc3545;
            color: white;
            padding: 5px 10px;
            border-radius: 20px;
        }
        .badge-attente {
            background: #ffc107;
            color: #000;
            padding: 5px 10px;
            border-radius: 20px;
        }
        .tontine-header {
            background: linear-gradient(135deg, #6B46C1 0%, #FF8A4C 100%);
            color: white;
            padding: 30px 0;
            margin-bottom: 30px;
        }
        .btn-retour {
            background: white;
            color: #6B46C1;
            border-radius: 50px;
            padding: 10px 25px;
            font-weight: 600;
            text-decoration: none;
            transition: all 0.3s;
        }
        .btn-retour:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 30px rgba(0,0,0,0.2);
            color: #FF8A4C;
        }
    </style>
</head>
<body>

    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg">
        <div class="container">
            <a class="navbar-brand" href="../dashboard.php">
                <i class="bi bi-bank2 me-2"></i>TONTONTINE
            </a>
            <div class="navbar-nav ms-auto">
                <span class="nav-link">
                    <i class="bi bi-person-circle"></i> <?= htmlspecialchars($_SESSION['user_nom']) ?>
                </span>
                <a class="nav-link" href="../logout.php">
                    <i class="bi bi-box-arrow-right"></i> Déconnexion
                </a>
            </div>
        </div>
    </nav>

    <!-- En-tête de la tontine -->
    <div class="tontine-header">
        <div class="container">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h1 class="mb-2"><?= htmlspecialchars($tontine->nom) ?></h1>
                    <p class="mb-0">
                        <span class="badge bg-light text-dark me-2"><?= $tontine->type_tontine ?></span>
                        <span class="badge bg-light text-dark"><?= $total_membres ?> membres</span>
                    </p>
                </div>
                <a href="../dashboard.php" class="btn-retour">
                    <i class="bi bi-arrow-left me-2"></i>Retour
                </a>
            </div>
        </div>
    </div>

    <div class="container mb-5">

        <!-- Cartes de statistiques -->
        <div class="row mb-4">
            <div class="col-md-3 mb-3">
                <div class="stat-card">
                    <div class="stat-icon">
                        <i class="bi bi-cash-stack"></i>
                    </div>
                    <div class="stat-number"><?= number_format($total_cotise, 0, ',', ' ') ?> F</div>
                    <div class="stat-label">Total cotisé</div>
                </div>
            </div>
            <div class="col-md-3 mb-3">
                <div class="stat-card">
                    <div class="stat-icon">
                        <i class="bi bi-exclamation-triangle"></i>
                    </div>
                    <div class="stat-number"><?= number_format($amendes_impayees, 0, ',', ' ') ?> F</div>
                    <div class="stat-label">Amendes impayées</div>
                </div>
            </div>
            <div class="col-md-3 mb-3">
                <div class="stat-card">
                    <div class="stat-icon">
                        <i class="bi bi-check-circle"></i>
                    </div>
                    <div class="stat-number"><?= number_format($amendes_payees, 0, ',', ' ') ?> F</div>
                    <div class="stat-label">Amendes payées</div>
                </div>
            </div>
            <div class="col-md-3 mb-3">
                <div class="stat-card">
                    <div class="stat-icon">
                        <i class="bi bi-calendar"></i>
                    </div>
                    <div class="stat-number"><?= date('d/m', strtotime($prochaine_reunion)) ?></div>
                    <div class="stat-label">Prochaine réunion</div>
                </div>
            </div>
        </div>

        <!-- Prochain bénéficiaire (si mode auto) -->
        <?php if($tontine->mode_beneficiaire == 'auto' && $prochain_beneficiaire): ?>
        <div class="card mb-4">
            <div class="card-header">
                <i class="bi bi-trophy me-2"></i>Prochain bénéficiaire (mode automatique)
            </div>
            <div class="card-body">
                <h3 class="text-center"><?= htmlspecialchars($prochain_beneficiaire['prenom'] . ' ' . $prochain_beneficiaire['nom']) ?></h3>
                <p class="text-center text-muted">Ordre n°<?= $prochain_beneficiaire['ordre_tour'] ?></p>
            </div>
        </div>
        <?php endif; ?>

        <!-- Dernières cotisations -->
        <?php if(!empty($dernieres_cotisations)): ?>
        <div class="card mb-4">
            <div class="card-header">
                <i class="bi bi-clock-history me-2"></i>Dernières cotisations
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Montant</th>
                                <th>Statut</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach($dernieres_cotisations as $c): ?>
                            <tr>
                                <td><?= date('d/m/Y', strtotime($c['date_seance'])) ?></td>
                                <td class="<?= $c['statut'] == 'paye' ? 'amount-positive' : 'amount-negative' ?>">
                                    <?= number_format($c['montant'], 0, ',', ' ') ?> F
                                </td>
                                <td>
                                    <?php if($c['statut'] == 'paye'): ?>
                                        <span class="badge-paye">Payé</span>
                                    <?php elseif($c['statut'] == 'retard'): ?>
                                        <span class="badge-impaye">Retard</span>
                                    <?php else: ?>
                                        <span class="badge-attente">En attente</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- Liste des amendes -->
        <?php if(!empty($amendes)): ?>
        <div class="card">
            <div class="card-header">
                <i class="bi bi-exclamation-triangle me-2"></i>Mes amendes
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Type</th>
                                <th>Montant</th>
                                <th>Statut</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach($amendes as $a): ?>
                            <tr>
                                <td><?= date('d/m/Y', strtotime($a['date_application'])) ?></td>
                                <td><?= str_replace('_', ' ', $a['type_amende'] ?? 'Amende') ?></td>
                                <td class="<?= $a['est_paye'] ? 'amount-positive' : 'amount-negative' ?>">
                                    <?= number_format($a['montant'], 0, ',', ' ') ?> F
                                </td>
                                <td>
                                    <?php if($a['est_paye']): ?>
                                        <span class="badge-paye">Payé</span>
                                    <?php else: ?>
                                        <span class="badge-impaye">Impayé</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <?php endif; ?>

    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>