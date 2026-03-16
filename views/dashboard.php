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
$userRole = $_SESSION['association_role'] ?? 'membre';

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

/**
 * Fonction pour récupérer le prochain bénéficiaire d'une tontine
 */
function getProchainBeneficiaire($db, $tontine_id) {
    // Récupérer le dernier bénéficiaire
    $query = "SELECT beneficiaire_id FROM seances 
              WHERE tontine_id = :tid AND beneficiaire_id IS NOT NULL 
              ORDER BY date_seance DESC LIMIT 1";
    $stmt = $db->prepare($query);
    $stmt->execute(['tid' => $tontine_id]);
    $dernier = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if($dernier) {
        // Récupérer l'ordre du dernier bénéficiaire
        $query = "SELECT ordre_tour, ordre_final FROM membre_tontine WHERE id = :mid";
        $stmt = $db->prepare($query);
        $stmt->execute(['mid' => $dernier['beneficiaire_id']]);
        $ordre_dernier = $stmt->fetch(PDO::FETCH_ASSOC);
        $ordre_valeur = $ordre_dernier['ordre_final'] ?? $ordre_dernier['ordre_tour'];
        
        // Chercher le suivant (ordre > dernier)
        $query = "SELECT mt.*, u.prenom, u.nom, u.telephone, u.email,
                         COALESCE(mt.ordre_final, mt.ordre_tour) as ordre_actuel
                  FROM membre_tontine mt
                  JOIN users u ON mt.user_id = u.id
                  WHERE mt.tontine_id = :tid 
                    AND mt.est_actif = 1 
                    AND COALESCE(mt.ordre_final, mt.ordre_tour) > :ordre
                  ORDER BY COALESCE(mt.ordre_final, mt.ordre_tour) ASC LIMIT 1";
        $stmt = $db->prepare($query);
        $stmt->execute(['tid' => $tontine_id, 'ordre' => $ordre_valeur]);
        $suivant = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if(!$suivant) {
            // Retour au début (cycle complet)
            $query = "SELECT mt.*, u.prenom, u.nom, u.telephone, u.email,
                             COALESCE(mt.ordre_final, mt.ordre_tour) as ordre_actuel
                      FROM membre_tontine mt
                      JOIN users u ON mt.user_id = u.id
                      WHERE mt.tontine_id = :tid 
                        AND mt.est_actif = 1 
                      ORDER BY COALESCE(mt.ordre_final, mt.ordre_tour) ASC LIMIT 1";
            $stmt = $db->prepare($query);
            $stmt->execute(['tid' => $tontine_id]);
            $suivant = $stmt->fetch(PDO::FETCH_ASSOC);
        }
    } else {
        // Aucune séance encore, prendre le premier de l'ordre
        $query = "SELECT mt.*, u.prenom, u.nom, u.telephone, u.email,
                         COALESCE(mt.ordre_final, mt.ordre_tour) as ordre_actuel
                  FROM membre_tontine mt
                  JOIN users u ON mt.user_id = u.id
                  WHERE mt.tontine_id = :tid 
                    AND mt.est_actif = 1 
                  ORDER BY COALESCE(mt.ordre_final, mt.ordre_tour) ASC LIMIT 1";
        $stmt = $db->prepare($query);
        $stmt->execute(['tid' => $tontine_id]);
        $suivant = $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    return $suivant;
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
        :root {
            --primary: #1E3A8A;        /* Bleu sombre */
            --primary-light: #3B5BA5;   /* Bleu plus clair */
            --white: #FFFFFF;
            --bg-light: #F8FAFC;
            --text-dark: #0F172A;
            --text-light: #475569;
            --border: #E2E8F0;
            --success: #10B981;
            --warning: #F59E0B;
            --danger: #EF4444;
            --info: #3B82F6;
        }
        
        body { 
            background: var(--bg-light); 
            color: var(--text-dark);
        }
        
        .navbar { 
            background: var(--primary); 
        }
        
        .navbar-brand, .nav-link {
            color: var(--white) !important;
        }
        
        .nav-link:hover {
            color: rgba(255,255,255,0.8) !important;
        }
        
        .stat-card {
            background: var(--white);
            border-radius: 15px;
            padding: 20px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.05);
            transition: transform 0.3s;
            height: 100%;
            border: 1px solid var(--border);
        }
        
        .stat-card:hover { 
            transform: translateY(-5px); 
            border-color: var(--primary);
        }
        
        .stat-icon { 
            font-size: 40px; 
            color: var(--primary); 
            margin-bottom: 15px; 
        }
        
        .stat-number { 
            font-size: 28px; 
            font-weight: 600; 
            color: var(--text-dark); 
        }
        
        .stat-label { 
            color: var(--text-light); 
            font-size: 14px; 
            text-transform: uppercase; 
        }
        
        .section-title {
            margin: 30px 0 20px;
            font-weight: 600;
            color: var(--text-dark);
            border-bottom: 2px solid var(--primary);
            padding-bottom: 10px;
        }
        
        .list-group-item { 
            border-left: none; 
            border-right: none; 
            border-color: var(--border);
        }
        
        .badge-amende { 
            background: var(--warning); 
            color: var(--white); 
        }
        
        .tontine-active {
            background: var(--primary);
            color: var(--white);
            padding: 15px;
            border-radius: 10px;
            margin-bottom: 20px;
        }
        
        .tontine-active a {
            color: var(--white);
            text-decoration: underline;
        }
        
        .card {
            border-radius: 15px;
            border: none;
            box-shadow: 0 5px 20px rgba(0,0,0,0.05);
            transition: transform 0.3s, box-shadow 0.3s;
            border: 1px solid var(--border);
        }
        
        .card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 30px rgba(30, 58, 138, 0.1);
            border-color: var(--primary);
        }
        
        .card-header {
            background: var(--primary);
            color: var(--white);
            border-radius: 15px 15px 0 0 !important;
        }
        
        .btn-primary {
            background: var(--primary);
            border: none;
        }
        
        .btn-primary:hover {
            background: var(--primary-light);
        }
        
        .btn-outline-primary {
            border: 2px solid var(--primary);
            color: var(--primary);
        }
        
        .btn-outline-primary:hover {
            background: var(--primary);
            color: var(--white);
        }
        
        .btn-outline-success {
            border: 2px solid var(--success);
            color: var(--success);
        }
        
        .btn-outline-success:hover {
            background: var(--success);
            color: var(--white);
        }
        
        .btn-outline-info {
            border: 2px solid var(--info);
            color: var(--info);
        }
        
        .btn-outline-info:hover {
            background: var(--info);
            color: var(--white);
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
        
        .badge-ordre-final {
            background: var(--success);
            color: var(--white);
            padding: 5px 10px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
        }
        
        .badge-ordre-temp {
            background: var(--warning);
            color: var(--white);
            padding: 5px 10px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
        }
        
        .badge-success {
            background: var(--success);
            color: var(--white);
        }
        
        .badge-warning {
            background: var(--warning);
            color: var(--white);
        }
        
        .badge-danger {
            background: var(--danger);
            color: var(--white);
        }
        
        .badge-info {
            background: var(--info);
            color: var(--white);
        }
        
        .alert-info {
            background: #DBEAFE;
            color: var(--primary);
            border: none;
        }
        
        .alert-info .badge {
            background: var(--primary);
            color: var(--white);
        }
        
        .beneficiaire-card {
            border-left: 4px solid var(--primary);
            margin-bottom: 20px;
        }
        
        .beneficiaire-avatar {
            width: 50px;
            height: 50px;
            background: var(--primary);
            color: var(--white);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
        }
        
        .mode-badge {
            font-size: 12px;
            padding: 5px 10px;
            border-radius: 20px;
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
                        <div class="card-header">
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
                        <div class="card-header">
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
                        <div class="card-header">
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
                    <?php foreach($mesTontines as $t): 
                        // Récupérer le prochain bénéficiaire pour cette tontine
                        $prochain = getProchainBeneficiaire($db, $t['id']);
                        
                        // Vérifier si l'ordre final existe
                        $query = "SELECT COUNT(*) as nb FROM membre_tontine 
                                  WHERE tontine_id = :tid AND ordre_final IS NOT NULL";
                        $stmt = $db->prepare($query);
                        $stmt->execute(['tid' => $t['id']]);
                        $ordre_final_existe = $stmt->fetch()['nb'] > 0;
                        
                        $badge_ordre = $ordre_final_existe ? 'badge-ordre-final' : 'badge-ordre-temp';
                        $mode_label = $t['mode_beneficiaire'] == 'auto' ? 'Automatique' : 'Manuel';
                        $badge_mode = $t['mode_beneficiaire'] == 'auto' ? 'info' : 'warning';
                    ?>
                        <div class="col-md-6 mb-4">
                            <a href="tontine/details_tontine.php?id=<?= $t['id'] ?>" style="text-decoration: none; color: inherit;">
                                <div class="card h-100">
                                    <div class="card-header d-flex justify-content-between align-items-center">
                                        <h5 class="mb-0"><?= htmlspecialchars($t['nom']) ?></h5>
                                        <span class="badge bg-<?= $badge_mode ?> mode-badge">
                                            <i class="bi bi-<?= $t['mode_beneficiaire'] == 'auto' ? 'robot' : 'person' ?>"></i>
                                            <?= $mode_label ?>
                                        </span>
                                    </div>
                                    <div class="card-body">
                                        <div class="row mb-3">
                                            <div class="col-6">
                                                <p class="mb-1 text-muted">Montant</p>
                                                <p class="h5"><?= number_format($t['montant_cotisation'], 0, ',', ' ') ?> F</p>
                                            </div>
                                            <div class="col-6">
                                                <p class="mb-1 text-muted">Réunions</p>
                                                <p class="h5"><?= htmlspecialchars($t['jour_reunion']) ?></p>
                                            </div>
                                        </div>
                                        
                                        <!-- Prochain bénéficiaire -->
                                        <?php if($prochain): ?>
                                            <div class="beneficiaire-card p-3 bg-light rounded">
                                                <div class="d-flex align-items-center">
                                                    <div class="beneficiaire-avatar me-3">
                                                        <i class="bi bi-trophy-fill"></i>
                                                    </div>
                                                    <div>
                                                        <small class="text-muted d-block">Prochain bénéficiaire</small>
                                                        <strong class="h6"><?= htmlspecialchars($prochain['prenom'] . ' ' . $prochain['nom']) ?></strong>
                                                        <div class="mt-1">
                                                            <span class="<?= $badge_ordre ?>">#<?= $prochain['ordre_actuel'] ?></span>
                                                            <?php if(!$ordre_final_existe && $t['mode_beneficiaire'] == 'auto'): ?>
                                                                <small class="text-muted d-block">Ordre provisoire</small>
                                                            <?php endif; ?>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        <?php else: ?>
                                            <div class="alert alert-warning mb-0 py-2">
                                                <i class="bi bi-exclamation-triangle"></i> 
                                                En attente de l'ordre des bénéficiaires
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                    <div class="card-footer bg-transparent border-0 text-center pb-3">
                                        <span class="btn btn-outline-primary btn-sm">
                                            <i class="bi bi-file-text"></i> Voir détails
                                        </span>
                                    </div>
                                </div>
                            </a>
                            <div class="mt-2 text-center">
                                <a href="etats/etats_membre.php?tontine_id=<?= $t['id'] ?>" class="btn btn-outline-info btn-sm me-2">
                                    <i class="bi bi-file-text"></i> Mes états
                                </a>
                                <a href="tontine/details_tontine.php?id=<?= $t['id'] ?>" class="btn btn-outline-success btn-sm">
                                    <i class="bi bi-eye"></i> Détails
                                </a>
                            </div>
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