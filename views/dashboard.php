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
    $tontine_active = clone $tontine; // Copie pour éviter les interférences
}

// Pour les membres : récupérer leurs tontines
$mesTontines = [];
$totalCotise = 0;
$amendesImpayees = [];
$prochaineReunion = null;
$dernieresCotisations = [];

if($userRole == 'membre') {
    // Récupérer les tontines du membre
    $tontinesMembre = $membreTontine->getTontinesByMembre($userId);
    
    while($t = $tontinesMembre->fetch(PDO::FETCH_ASSOC)) {
        $mesTontines[] = $t;
        
        // Si c'est la tontine active, on calcule les stats spécifiques
        if($tontine_active && $t['id'] == $tontine_active_id) {
            // Récupérer l'ID du membre dans cette tontine
            $query = "SELECT id FROM membre_tontine 
                      WHERE user_id = :uid AND tontine_id = :tid";
            $stmt = $db->prepare($query);
            $stmt->execute(['uid' => $userId, 'tid' => $t['id']]);
            $membre = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if($membre) {
                // Total cotisé
                $queryTotal = "SELECT SUM(montant) as total FROM cotisations 
                               WHERE membre_tontine_id = :mid AND statut = 'paye'";
                $stmtTotal = $db->prepare($queryTotal);
                $stmtTotal->execute(['mid' => $membre['id']]);
                $total = $stmtTotal->fetch(PDO::FETCH_ASSOC);
                $totalCotise += ($total['total'] ?? 0);
                
                // Amendes impayées
                $amendes = $amendeAppliquee->getImpayesByMembre($membre['id']);
                $amendesImpayees = array_merge($amendesImpayees, $amendes);
                
                // Dernières cotisations (3 dernières)
                $queryDernieres = "SELECT c.*, s.date_seance 
                                   FROM cotisations c
                                   JOIN seances s ON c.seance_id = s.id
                                   WHERE c.membre_tontine_id = :mid 
                                   ORDER BY s.date_seance DESC LIMIT 3";
                $stmtDernieres = $db->prepare($queryDernieres);
                $stmtDernieres->execute(['mid' => $membre['id']]);
                while($cot = $stmtDernieres->fetch(PDO::FETCH_ASSOC)) {
                    $dernieresCotisations[] = $cot;
                }
                
                // Prochaine réunion
                if(!$prochaineReunion || $t['prochaine_reunion'] < $prochaineReunion) {
                    $prochaineReunion = $t['prochaine_reunion'];
                }
            }
        }
    }
}

// Pour le président : statistiques globales
$statsPresident = [];
$dernieresSeances = [];
$membresAvecAmendes = [];

if($userRole == 'admin') {
    // Nombre de tontines créées
    $queryTontines = "SELECT COUNT(*) as total FROM tontines WHERE admin_id = :aid";
    $stmtTontines = $db->prepare($queryTontines);
    $stmtTontines->execute(['aid' => $userId]);
    $statsPresident['tontines'] = $stmtTontines->fetch()['total'];
    
    // Nombre total de membres dans toutes ses tontines
    $queryMembres = "SELECT COUNT(DISTINCT mt.id) as total 
                     FROM membre_tontine mt
                     JOIN tontines t ON mt.tontine_id = t.id
                     WHERE t.admin_id = :aid AND mt.est_actif = 1";
    $stmtMembres = $db->prepare($queryMembres);
    $stmtMembres->execute(['aid' => $userId]);
    $statsPresident['membres'] = $stmtMembres->fetch()['total'];
    
    // Total des cotisations collectées
    $queryCotisations = "SELECT SUM(c.montant) as total 
                         FROM cotisations c
                         JOIN seances s ON c.seance_id = s.id
                         JOIN tontines t ON s.tontine_id = t.id
                         WHERE t.admin_id = :aid AND c.statut = 'paye'";
    $stmtCotisations = $db->prepare($queryCotisations);
    $stmtCotisations->execute(['aid' => $userId]);
    $statsPresident['total_cotise'] = $stmtCotisations->fetch()['total'] ?? 0;
    
    // Total des amendes collectées
    $queryAmendes = "SELECT SUM(a.montant) as total 
                     FROM amendes_appliquees a
                     JOIN seances s ON a.seance_id = s.id
                     JOIN tontines t ON s.tontine_id = t.id
                     WHERE t.admin_id = :aid AND a.est_paye = 1";
    $stmtAmendes = $db->prepare($queryAmendes);
    $stmtAmendes->execute(['aid' => $userId]);
    $statsPresident['total_amendes'] = $stmtAmendes->fetch()['total'] ?? 0;
    
    // Dernières séances
    $querySeances = "SELECT s.*, t.nom as tontine_nom 
                     FROM seances s
                     JOIN tontines t ON s.tontine_id = t.id
                     WHERE t.admin_id = :aid
                     ORDER BY s.date_seance DESC LIMIT 5";
    $stmtSeances = $db->prepare($querySeances);
    $stmtSeances->execute(['aid' => $userId]);
    $dernieresSeances = $stmtSeances->fetchAll(PDO::FETCH_ASSOC);
    
    // Membres avec amendes impayées
    $queryMembresAmendes = "SELECT DISTINCT u.nom, u.prenom, a.montant, a.date_application
                            FROM amendes_appliquees a
                            JOIN membre_tontine mt ON a.membre_tontine_id = mt.id
                            JOIN users u ON mt.user_id = u.id
                            JOIN seances s ON a.seance_id = s.id
                            JOIN tontines t ON s.tontine_id = t.id
                            WHERE t.admin_id = :aid AND a.est_paye = 0 AND mt.est_actif = 1
                            ORDER BY a.date_application DESC";
    $stmtMembresAmendes = $db->prepare($queryMembresAmendes);
    $stmtMembresAmendes->execute(['aid' => $userId]);
    $membresAvecAmendes = $stmtMembresAmendes->fetchAll(PDO::FETCH_ASSOC);
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tableau de bord - Tontine</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.8.1/font/bootstrap-icons.css">
    <style>
        body { background: #f8f9fa; }
        .navbar { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); }
        .stat-card {
            background: white;
            border-radius: 15px;
            padding: 20px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.05);
            transition: transform 0.3s;
            height: 100%;
        }
        .stat-card:hover { transform: translateY(-5px); }
        .stat-icon { font-size: 40px; color: #667eea; margin-bottom: 15px; }
        .stat-number { font-size: 28px; font-weight: 600; color: #333; }
        .stat-label { color: #666; font-size: 14px; text-transform: uppercase; }
        .section-title {
            margin: 30px 0 20px;
            font-weight: 600;
            color: #333;
            border-bottom: 2px solid #e0e0e0;
            padding-bottom: 10px;
        }
        .list-group-item { border-left: none; border-right: none; }
        .badge-amende { background: #ffc107; color: #000; }
        .tontine-active {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 15px;
            border-radius: 10px;
            margin-bottom: 20px;
        }
        .tontine-active a {
            color: white;
            text-decoration: underline;
        }
    </style>
</head>
<body>

    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg navbar-dark">
        <div class="container">
            <a class="navbar-brand" href="#"><i class="bi bi-bank2"></i> Tontine</a>
            <div class="navbar-nav ms-auto">
                <span class="nav-link text-white">
                    <i class="bi bi-person-circle"></i> <?= htmlspecialchars($user->prenom . ' ' . $user->nom) ?>
                </span>
                <span class="nav-link text-white">
                    <i class="bi bi-tag"></i> <?= $user->role == 'admin' ? 'Président' : 'Membre' ?>
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
            <h4 class="alert-heading"><i class="bi bi-hand-thumbs-up"></i>Bonjour, <?= htmlspecialchars($user->prenom) ?> !</h4>
            <p class="mb-0">Bienvenue dans votre espace personnel.</p>
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
                        <div class="stat-label">Tontines créées</div>
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
                        <div class="stat-label">Amendes perçues</div>
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
                        <div class="stat-number"><?= $tontine_active ? '1' : '0' ?></div>
                        <div class="stat-label">Tontine active</div>
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