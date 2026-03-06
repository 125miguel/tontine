<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();

if(!isset($_SESSION['user_id'])) {
    header("Location: auth/login.php");
    exit();
}

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../models/User.php';
require_once __DIR__ . '/../models/Tontine.php';
require_once __DIR__ . '/../models/MembreTontine.php';
require_once __DIR__ . '/../models/Cotisation.php';
require_once __DIR__ . '/../models/Seance.php';
require_once __DIR__ . '/../models/AmendeAppliquee.php';

// Vérifier qu'une association est active
if(!isset($_SESSION['association_active'])) {
    // Si pas d'association active, rediriger vers choix
    header("Location: auth/choisir_association.php");
    exit();
}

$association_active = $_SESSION['association_active'];
$association_nom = $_SESSION['association_nom'] ?? 'Association';

$database = new Database();
$db = $database->getConnection();

$user = new User($db);
$user->getById($_SESSION['user_id']);

$membreTontine = new MembreTontine($db);
$cotisation = new Cotisation($db);
$seance = new Seance($db);
$amendeAppliquee = new AmendeAppliquee($db);
$tontine = new Tontine($db);

// Données communes
$userId = $_SESSION['user_id'];
$userRole = $_SESSION['user_role'];

// Récupérer la tontine active si elle existe
$tontine_active = null;
$tontine_active_id = $_SESSION['tontine_active'] ?? null;

if($tontine_active_id) {
    $tontine->getById($tontine_active_id);
    $tontine_active = clone $tontine;
}

// Pour les membres : récupérer leurs tontines (UNIQUEMENT de l'association active)
$mesTontines = [];
$totalCotise = 0;
$amendesImpayees = [];
$prochaineReunion = null;
$dernieresCotisations = [];

if($userRole == 'membre') {
    // Récupérer les tontines du membre pour cette association
    $query = "SELECT t.* FROM tontines t
              JOIN membre_tontine mt ON t.id = mt.tontine_id
              WHERE mt.user_id = :user_id 
              AND t.association_id = :association_id
              AND mt.est_actif = 1
              ORDER BY t.nom";
    
    $stmt = $db->prepare($query);
    $stmt->execute([
        'user_id' => $userId,
        'association_id' => $association_active
    ]);
    $mesTontines = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Calculer le total des cotisations pour cette association
    $query = "SELECT SUM(c.montant) as total 
              FROM cotisations c
              JOIN seances s ON c.seance_id = s.id
              JOIN tontines t ON s.tontine_id = t.id
              WHERE c.membre_tontine_id IN (
                  SELECT id FROM membre_tontine 
                  WHERE user_id = :user_id AND association_id = :association_id
              ) AND c.statut = 'paye'";
    
    $stmt = $db->prepare($query);
    $stmt->execute([
        'user_id' => $userId,
        'association_id' => $association_active
    ]);
    $totalCotise = $stmt->fetch()['total'] ?? 0;
    
    // Récupérer les amendes impayées pour cette association
    $query = "SELECT a.*, r.type_amende 
              FROM amendes_appliquees a
              JOIN regles_amendes r ON a.regle_amende_id = r.id
              JOIN seances s ON a.seance_id = s.id
              JOIN tontines t ON s.tontine_id = t.id
              WHERE a.membre_tontine_id IN (
                  SELECT id FROM membre_tontine 
                  WHERE user_id = :user_id AND association_id = :association_id
              ) AND a.est_paye = 0
              ORDER BY a.date_application DESC";
    
    $stmt = $db->prepare($query);
    $stmt->execute([
        'user_id' => $userId,
        'association_id' => $association_active
    ]);
    $amendesImpayees = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Dernières cotisations pour cette association
    $query = "SELECT c.*, s.date_seance 
              FROM cotisations c
              JOIN seances s ON c.seance_id = s.id
              JOIN tontines t ON s.tontine_id = t.id
              WHERE c.membre_tontine_id IN (
                  SELECT id FROM membre_tontine 
                  WHERE user_id = :user_id AND association_id = :association_id
              )
              ORDER BY s.date_seance DESC LIMIT 5";
    
    $stmt = $db->prepare($query);
    $stmt->execute([
        'user_id' => $userId,
        'association_id' => $association_active
    ]);
    $dernieresCotisations = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Prochaine réunion pour cette association
    $query = "SELECT MIN(prochaine_reunion) as prochaine 
              FROM tontines 
              WHERE association_id = :association_id";
    
    $stmt = $db->prepare($query);
    $stmt->execute(['association_id' => $association_active]);
    $prochaineReunion = $stmt->fetch()['prochaine'];
}

// Pour le président : statistiques globales de l'association
$statsPresident = [];
$dernieresSeances = [];
$membresAvecAmendes = [];

if($userRole == 'admin') {
    // Nombre de tontines créées dans cette association
    $queryTontines = "SELECT COUNT(*) as total FROM tontines 
                      WHERE association_id = :aid";
    $stmtTontines = $db->prepare($queryTontines);
    $stmtTontines->execute(['aid' => $association_active]);
    $statsPresident['tontines'] = $stmtTontines->fetch()['total'];
    
    // Nombre total de membres actifs dans cette association
    $queryMembres = "SELECT COUNT(DISTINCT mt.id) as total 
                     FROM membre_tontine mt
                     WHERE mt.association_id = :aid AND mt.est_actif = 1";
    $stmtMembres = $db->prepare($queryMembres);
    $stmtMembres->execute(['aid' => $association_active]);
    $statsPresident['membres'] = $stmtMembres->fetch()['total'];
    
    // Total des cotisations collectées dans cette association
    $queryCotisations = "SELECT SUM(c.montant) as total 
                         FROM cotisations c
                         JOIN seances s ON c.seance_id = s.id
                         JOIN tontines t ON s.tontine_id = t.id
                         WHERE t.association_id = :aid AND c.statut = 'paye'";
    $stmtCotisations = $db->prepare($queryCotisations);
    $stmtCotisations->execute(['aid' => $association_active]);
    $statsPresident['total_cotise'] = $stmtCotisations->fetch()['total'] ?? 0;
    
    // Total des amendes collectées dans cette association
    $queryAmendes = "SELECT SUM(a.montant) as total 
                     FROM amendes_appliquees a
                     JOIN seances s ON a.seance_id = s.id
                     JOIN tontines t ON s.tontine_id = t.id
                     WHERE t.association_id = :aid AND a.est_paye = 1";
    $stmtAmendes = $db->prepare($queryAmendes);
    $stmtAmendes->execute(['aid' => $association_active]);
    $statsPresident['total_amendes'] = $stmtAmendes->fetch()['total'] ?? 0;
    
    // Dernières séances dans cette association
    $querySeances = "SELECT s.*, t.nom as tontine_nom 
                     FROM seances s
                     JOIN tontines t ON s.tontine_id = t.id
                     WHERE t.association_id = :aid
                     ORDER BY s.date_seance DESC LIMIT 5";
    $stmtSeances = $db->prepare($querySeances);
    $stmtSeances->execute(['aid' => $association_active]);
    $dernieresSeances = $stmtSeances->fetchAll(PDO::FETCH_ASSOC);
    
    // Membres avec amendes impayées dans cette association
    $queryMembresAmendes = "SELECT DISTINCT u.nom, u.prenom, a.montant, a.date_application
                            FROM amendes_appliquees a
                            JOIN membre_tontine mt ON a.membre_tontine_id = mt.id
                            JOIN users u ON mt.user_id = u.id
                            JOIN seances s ON a.seance_id = s.id
                            JOIN tontines t ON s.tontine_id = t.id
                            WHERE t.association_id = :aid AND a.est_paye = 0 AND mt.est_actif = 1
                            ORDER BY a.date_application DESC";
    $stmtMembresAmendes = $db->prepare($queryMembresAmendes);
    $stmtMembresAmendes->execute(['aid' => $association_active]);
    $membresAvecAmendes = $stmtMembresAmendes->fetchAll(PDO::FETCH_ASSOC);
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tableau de bord - <?= htmlspecialchars($association_nom) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.8.1/font/bootstrap-icons.css">
    <style>
        body { background: linear-gradient(135deg, #f5f0ff 0%, #fff5f0 100%); }
        .navbar { background: linear-gradient(135deg, #6B46C1 0%, #FF8A4C 100%); }
        .stat-card {
            background: white;
            border-radius: 15px;
            padding: 20px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.05);
            transition: transform 0.3s;
            height: 100%;
        }
        .stat-card:hover { transform: translateY(-5px); }
        .stat-icon { font-size: 40px; color: #6B46C1; margin-bottom: 15px; }
        .stat-number { font-size: 28px; font-weight: 600; color: #333; }
        .stat-label { color: #666; font-size: 14px; text-transform: uppercase; }
        .section-title {
            margin: 30px 0 20px;
            font-weight: 600;
            color: #333;
            border-bottom: 2px solid #6B46C1;
            padding-bottom: 10px;
        }
        .list-group-item { border-left: none; border-right: none; }
        .badge-amende { background: #ffc107; color: #000; }
        .tontine-active {
            background: linear-gradient(135deg, #6B46C1 0%, #FF8A4C 100%);
            color: white;
            padding: 15px;
            border-radius: 10px;
            margin-bottom: 20px;
        }
        .tontine-active a {
            color: white;
            text-decoration: underline;
        }
        .card {
            border-radius: 15px;
            border: none;
            box-shadow: 0 5px 20px rgba(0,0,0,0.05);
            transition: transform 0.3s, box-shadow 0.3s;
        }
        .card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 30px rgba(107, 70, 193, 0.1);
        }
        .card-header {
            border-radius: 15px 15px 0 0 !important;
        }
        a {
            text-decoration: none;
            color: inherit;
        }
        .association-badge {
            background: rgba(255,255,255,0.2);
            padding: 5px 15px;
            border-radius: 50px;
            font-size: 14px;
            margin-left: 10px;
        }
    </style>
</head>
<body>

    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg navbar-dark">
        <div class="container">
            <a class="navbar-brand" href="#"><i class="bi bi-bank2"></i> TONTONTINE</a>
            <div class="navbar-nav ms-auto">
                <span class="nav-link text-white">
                    <i class="bi bi-person-circle"></i> <?= htmlspecialchars($user->prenom . ' ' . $user->nom) ?>
                </span>
                <span class="nav-link text-white">
                    <i class="bi bi-tag"></i> <?= $user->role == 'admin' ? 'Président' : 'Membre' ?>
                </span>
                <span class="nav-link text-white">
                    <i class="bi bi-building"></i> <?= htmlspecialchars($association_nom) ?>
                </span>
                <?php if($userRole == 'membre' && $tontine_active): ?>
                    <span class="nav-link text-white">
                        <i class="bi bi-bank2"></i> <?= htmlspecialchars($tontine_active->nom) ?>
                    </span>
                <?php endif; ?>
                <a class="nav-link text-white" href="logout.php">
                    <i class="bi bi-box-arrow-right"></i> Déconnexion
                </a>
            </div>
        </div>
    </nav>

    <div class="container mt-5">

        <!-- Message de bienvenue -->
        <div class="alert alert-info">
            <h4 class="alert-heading"><i class="bi bi-hand-thumbs-up"></i> Bonjour, <?= htmlspecialchars($user->prenom) ?> !</h4>
            <p class="mb-0">Bienvenue dans l'espace de <strong><?= htmlspecialchars($association_nom) ?></strong></p>
        </div>

        <?php if($userRole == 'membre' && $tontine_active): ?>
            <!-- Tontine active en vedette -->
            <div class="tontine-active">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h5 class="mb-1">
                            Tontine active : <strong><?= htmlspecialchars($tontine_active->nom) ?></strong>
                            <span class="badge bg-light text-dark ms-2"><?= $tontine_active->type_tontine ?></span>
                        </h5>
                        <p class="mb-0">
                            Montant cotisation : <?= number_format($tontine_active->montant_cotisation, 0, ',', ' ') ?> F
                        </p>
                    </div>
                    <a href="auth/choisir_tontine.php" class="btn btn-outline-light">
                        <i class="bi bi-arrow-repeat"></i> Changer de tontine
                    </a>
                </div>
            </div>
        <?php endif; ?>

        <?php if($userRole == 'admin'): ?>

            <!-- STATISTIQUES PRÉSIDENT -->
            <div class="row mb-4">
                <div class="col-md-3 mb-3">
                    <div class="stat-card text-center">
                        <div class="stat-icon"><i class="bi bi-bank2"></i></div>
                        <div class="stat-number"><?= $statsPresident['tontines'] ?></div>
                        <div class="stat-label">Tontines</div>
                    </div>
                </div>
                <div class="col-md-3 mb-3">
                    <div class="stat-card text-center">
                        <div class="stat-icon"><i class="bi bi-people"></i></div>
                        <div class="stat-number"><?= $statsPresident['membres'] ?></div>
                        <div class="stat-label">Membres</div>
                    </div>
                </div>
                <div class="col-md-3 mb-3">
                    <div class="stat-card text-center">
                        <div class="stat-icon"><i class="bi bi-cash-stack"></i></div>
                        <div class="stat-number"><?= number_format($statsPresident['total_cotise'], 0, ',', ' ') ?> F</div>
                        <div class="stat-label">Cotisations</div>
                    </div>
                </div>
                <div class="col-md-3 mb-3">
                    <div class="stat-card text-center">
                        <div class="stat-icon"><i class="bi bi-exclamation-triangle"></i></div>
                        <div class="stat-number"><?= number_format($statsPresident['total_amendes'], 0, ',', ' ') ?> F</div>
                        <div class="stat-label">Amendes</div>
                    </div>
                </div>
            </div>

            <!-- ACTIONS RAPIDES PRÉSIDENT -->
            <div class="row mb-4">
                <div class="col-12">
                    <div class="card">
                        <div class="card-header bg-primary text-white">
                            <h5 class="mb-0"><i class="bi bi-gear"></i> Actions rapides</h5>
                        </div>
                        <div class="card-body">
                            <a href="tontine/create.php" class="btn btn-primary me-2">
                                <i class="bi bi-plus-circle"></i> Nouvelle tontine
                            </a>
                            <a href="tontine/mes_tontines.php" class="btn btn-outline-primary me-2">
                                <i class="bi bi-list-ul"></i> Mes tontines
                            </a>
                            <a href="tontine/mes_tontines.php" class="btn btn-outline-success">
                                <i class="bi bi-play-circle"></i> Ouvrir une séance
                            </a>
                        </div>
                    </div>
                </div>
            </div>

            <!-- DERNIÈRES SÉANCES -->
            <div class="row">
                <div class="col-md-6 mb-4">
                    <div class="card">
                        <div class="card-header bg-info text-white">
                            <h5 class="mb-0"><i class="bi bi-calendar-check"></i> Dernières séances</h5>
                        </div>
                        <div class="card-body">
                            <?php if(empty($dernieresSeances)): ?>
                                <p class="text-muted">Aucune séance pour le moment</p>
                            <?php else: ?>
                                <ul class="list-group list-group-flush">
                                    <?php foreach($dernieresSeances as $s): ?>
                                        <li class="list-group-item d-flex justify-content-between align-items-center">
                                            <?= date('d/m/Y', strtotime($s['date_seance'])) ?> - <?= htmlspecialchars($s['tontine_nom']) ?>
                                            <span class="badge bg-<?= $s['est_cloturee'] ? 'success' : 'warning' ?>">
                                                <?= $s['est_cloturee'] ? 'Clôturée' : 'En cours' ?>
                                            </span>
                                        </li>
                                    <?php endforeach; ?>
                                </ul>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- MEMBRES AVEC AMENDES -->
                <div class="col-md-6 mb-4">
                    <div class="card">
                        <div class="card-header bg-warning">
                            <h5 class="mb-0"><i class="bi bi-exclamation-triangle"></i> Amendes impayées</h5>
                        </div>
                        <div class="card-body">
                            <?php if(empty($membresAvecAmendes)): ?>
                                <p class="text-muted">Aucune amende impayée</p>
                            <?php else: ?>
                                <ul class="list-group list-group-flush">
                                    <?php foreach($membresAvecAmendes as $m): ?>
                                        <li class="list-group-item d-flex justify-content-between align-items-center">
                                            <?= htmlspecialchars($m['prenom'] . ' ' . $m['nom']) ?>
                                            <span class="badge bg-danger"><?= number_format($m['montant'], 0, ',', ' ') ?> F</span>
                                        </li>
                                    <?php endforeach; ?>
                                </ul>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>

        <?php else: ?>

            <!-- STATISTIQUES MEMBRE -->
            <div class="row mb-4">
                <div class="col-md-4 mb-3">
                    <div class="stat-card text-center">
                        <div class="stat-icon"><i class="bi bi-bank2"></i></div>
                        <div class="stat-number"><?= count($mesTontines) ?></div>
                        <div class="stat-label">Mes tontines</div>
                    </div>
                </div>
                <div class="col-md-4 mb-3">
                    <div class="stat-card text-center">
                        <div class="stat-icon"><i class="bi bi-cash-stack"></i></div>
                        <div class="stat-number"><?= number_format($totalCotise, 0, ',', ' ') ?> F</div>
                        <div class="stat-label">Cotisations versées</div>
                    </div>
                </div>
                <div class="col-md-4 mb-3">
                    <div class="stat-card text-center">
                        <div class="stat-icon"><i class="bi bi-calendar-check"></i></div>
                        <div class="stat-number"><?= $prochaineReunion ? date('d/m', strtotime($prochaineReunion)) : '-' ?></div>
                        <div class="stat-label">Prochaine réunion</div>
                    </div>
                </div>
            </div>

            <!-- MES TONTINES -->
            <h4 class="section-title"><i class="bi bi-grid-3x3-gap-fill"></i> Mes tontines dans <?= htmlspecialchars($association_nom) ?></h4>
            <div class="row mb-4">
                <?php if(empty($mesTontines)): ?>
                    <div class="col-12">
                        <div class="alert alert-info">Vous n'êtes membre d'aucune tontine dans cette association.</div>
                    </div>
                <?php else: ?>
                    <?php foreach($mesTontines as $t): ?>
                        <div class="col-md-6 mb-3">
                            <a href="tontine/details_tontine.php?id=<?= $t['id'] ?>" style="text-decoration: none; color: inherit;">
                                <div class="card h-100">
                                    <div class="card-header bg-primary text-white">
                                        <h5 class="mb-0"><?= htmlspecialchars($t['nom']) ?></h5>
                                    </div>
                                    <div class="card-body">
                                        <p class="mb-1"><strong> Montant:</strong> <?= number_format($t['montant_cotisation'], 0, ',', ' ') ?> F</p>
                                        <p class="mb-1"><strong> Réunions:</strong> <?= htmlspecialchars($t['jour_reunion']) ?></p>
                                        <p class="mb-0"><strong> Prochain tour:</strong> À déterminer</p>
                                    </div>
                                </div>
                            </a>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>

            <!-- AMENDES IMPAYÉES -->
            <?php if(!empty($amendesImpayees)): ?>
                <h4 class="section-title"><i class="bi bi-exclamation-triangle"></i> Amendes impayées</h4>
                <div class="card mb-4">
                    <div class="card-body">
                        <ul class="list-group">
                            <?php foreach($amendesImpayees as $a): ?>
                                <li class="list-group-item d-flex justify-content-between align-items-center">
                                    <?= htmlspecialchars($a['type_amende'] ?? 'Amende') ?> 
                                    <span class="badge bg-danger"><?= number_format($a['montant'], 0, ',', ' ') ?> F</span>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                </div>
            <?php endif; ?>

            <!-- DERNIÈRES COTISATIONS -->
            <?php if(!empty($dernieresCotisations)): ?>
                <h4 class="section-title"><i class="bi bi-clock-history"></i> Dernières cotisations</h4>
                <div class="card mb-4">
                    <div class="card-body">
                        <ul class="list-group">
                            <?php foreach($dernieresCotisations as $c): ?>
                                <li class="list-group-item d-flex justify-content-between align-items-center">
                                    <?= date('d/m/Y', strtotime($c['date_seance'])) ?>
                                    <span class="badge bg-<?= $c['statut'] == 'paye' ? 'success' : 'warning' ?>">
                                        <?= number_format($c['montant'], 0, ',', ' ') ?> F
                                    </span>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                </div>
            <?php endif; ?>

        <?php endif; ?>

    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>