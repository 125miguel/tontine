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

// Vérifier que le membre appartient bien à cette tontine et à l'association active
$query = "SELECT mt.*, t.*, t.id as tontine_id, t.nom as tontine_nom,
                 u.nom as user_nom, u.prenom as user_prenom
          FROM membre_tontine mt
          JOIN tontines t ON mt.tontine_id = t.id
          JOIN users u ON mt.user_id = u.id
          WHERE mt.user_id = :uid 
            AND mt.tontine_id = :tid 
            AND mt.est_actif = 1
            AND t.association_id = :aid";
$stmt = $db->prepare($query);
$stmt->execute([
    'uid' => $userId,
    'tid' => $tontine_id,
    'aid' => $_SESSION['association_active']
]);

if($stmt->rowCount() == 0) {
    header("Location: ../dashboard.php");
    exit();
}

$membre = $stmt->fetch(PDO::FETCH_ASSOC);

// Récupérer les infos de la tontine
$tontine = new Tontine($db);
$tontine->getById($tontine_id);

// Déterminer le type de tontine
$type_tontine = $tontine->type_tontine;

// Récupérer les cotisations du membre pour cette tontine
$cotisation = new Cotisation($db);
$amendeAppliquee = new AmendeAppliquee($db);

// Récupérer tous les membres de la tontine avec leur ordre
$query_membres = "SELECT mt.*, u.nom, u.prenom, u.telephone, u.email,
                         COALESCE(mt.ordre_final, mt.ordre_tour) as ordre_actuel,
                         mt.date_anniversaire
                  FROM membre_tontine mt
                  JOIN users u ON mt.user_id = u.id
                  WHERE mt.tontine_id = :tid AND mt.est_actif = 1
                  ORDER BY COALESCE(mt.ordre_final, mt.ordre_tour) ASC";
$stmt_membres = $db->prepare($query_membres);
$stmt_membres->execute(['tid' => $tontine_id]);
$membres_liste = $stmt_membres->fetchAll(PDO::FETCH_ASSOC);

// Récupérer les séances passées
$query_seances = "SELECT s.*, 
                         u.prenom as benef_prenom, u.nom as benef_nom,
                         (SELECT COUNT(*) FROM cotisations WHERE seance_id = s.id AND statut = 'paye') as nb_presents,
                         (SELECT COUNT(*) FROM cotisations WHERE seance_id = s.id AND statut = 'retard') as nb_retards
                  FROM seances s
                  LEFT JOIN membre_tontine mt ON s.beneficiaire_id = mt.id
                  LEFT JOIN users u ON mt.user_id = u.id
                  WHERE s.tontine_id = :tid
                  ORDER BY s.date_seance DESC LIMIT 5";
$stmt_seances = $db->prepare($query_seances);
$stmt_seances->execute(['tid' => $tontine_id]);
$seances = $stmt_seances->fetchAll(PDO::FETCH_ASSOC);

// Statistiques du membre pour cette tontine
$query_stats = "SELECT 
                    SUM(CASE WHEN c.statut = 'paye' THEN c.montant ELSE 0 END) as total_paye,
                    SUM(CASE WHEN c.statut = 'retard' THEN c.montant ELSE 0 END) as total_retard,
                    COUNT(CASE WHEN c.statut = 'paye' THEN 1 END) as nb_paye,
                    COUNT(CASE WHEN c.statut = 'retard' THEN 1 END) as nb_retard
                FROM cotisations c
                JOIN seances s ON c.seance_id = s.id
                WHERE c.membre_tontine_id = :mid";
$stmt_stats = $db->prepare($query_stats);
$stmt_stats->execute(['mid' => $membre['id']]);
$stats = $stmt_stats->fetch(PDO::FETCH_ASSOC);

// Total cotisé par le membre
$total_cotise = $stats['total_paye'] ?? 0;

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
$total_membres = count($membres_liste);

// Prochaine réunion
$prochaine_reunion = $tontine->prochaine_reunion;

// Vérifier si l'ordre final existe
$query_ordre = "SELECT COUNT(*) as nb FROM membre_tontine 
                WHERE tontine_id = :tid AND ordre_final IS NOT NULL";
$stmt_ordre = $db->prepare($query_ordre);
$stmt_ordre->execute(['tid' => $tontine_id]);
$ordre_final_existe = $stmt_ordre->fetch()['nb'] > 0;

/**
 * Fonction pour récupérer le prochain bénéficiaire (uniquement pour Djangui et Anniversaire)
 */
function getProchainBeneficiaire($db, $tontine_id, $mode) {
    $query = "SELECT beneficiaire_id FROM seances 
              WHERE tontine_id = :tid AND beneficiaire_id IS NOT NULL 
              ORDER BY date_seance DESC LIMIT 1";
    $stmt = $db->prepare($query);
    $stmt->execute(['tid' => $tontine_id]);
    $dernier = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if($dernier) {
        $query = "SELECT ordre_tour, ordre_final FROM membre_tontine WHERE id = :mid";
        $stmt = $db->prepare($query);
        $stmt->execute(['mid' => $dernier['beneficiaire_id']]);
        $ordre_dernier = $stmt->fetch(PDO::FETCH_ASSOC);
        $ordre_valeur = $ordre_dernier['ordre_final'] ?? $ordre_dernier['ordre_tour'];
        
        $query = "SELECT mt.*, u.prenom, u.nom, u.telephone,
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
            $query = "SELECT mt.*, u.prenom, u.nom, u.telephone,
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
        $query = "SELECT mt.*, u.prenom, u.nom, u.telephone,
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

// Récupérer le prochain bénéficiaire UNIQUEMENT pour Djangui et Anniversaire
$prochain_beneficiaire = null;
if($type_tontine == 'djangui' || $type_tontine == 'anniversaire') {
    $prochain_beneficiaire = getProchainBeneficiaire($db, $tontine_id, $tontine->mode_beneficiaire);
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
        :root {
            --primary: #1E3A8A;
            --primary-light: #3B5BA5;
            --white: #FFFFFF;
            --bg-light: #F8FAFC;
            --text-dark: #0F172A;
            --text-light: #475569;
            --border: #E2E8F0;
            --success: #10B981;
            --warning: #F59E0B;
            --danger: #EF4444;
            --info: #3B82F6;
            --info-bg: #DBEAFE;
        }
        
        body {
            background-color: var(--bg-light);
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        .navbar {
            background-color: var(--primary);
            padding: 15px 0;
            box-shadow: 0 2px 20px rgba(0,0,0,0.1);
        }
        
        .navbar-brand {
            color: var(--white);
            font-size: 24px;
            font-weight: 700;
        }
        
        .navbar-brand:hover {
            color: #e0e0e0;
        }
        
        .nav-link {
            color: var(--white) !important;
        }
        
        .nav-link:hover {
            color: #e0e0e0 !important;
        }
        
        .tontine-header {
            background-color: var(--primary);
            color: var(--white);
            padding: 30px 0;
            margin-bottom: 30px;
        }
        
        .tontine-header h1 {
            color: var(--white);
        }
        
        .btn-retour {
            background: var(--white);
            color: var(--primary);
            border-radius: 50px;
            padding: 10px 25px;
            font-weight: 600;
            text-decoration: none;
            transition: all 0.3s;
            border: 2px solid var(--white);
        }
        
        .btn-retour:hover {
            background: var(--primary);
            color: var(--white);
            transform: translateY(-2px);
            box-shadow: 0 10px 30px rgba(0,0,0,0.2);
        }
        
        .card {
            border-radius: 15px;
            border: 1px solid var(--border);
            box-shadow: 0 5px 20px rgba(0,0,0,0.05);
            transition: transform 0.3s, box-shadow 0.3s;
            margin-bottom: 20px;
        }
        
        .card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 30px rgba(30, 58, 138, 0.1);
            border-color: var(--primary);
        }
        
        .card-header {
            background-color: var(--primary);
            color: var(--white);
            border-radius: 15px 15px 0 0 !important;
            font-weight: 600;
            border-bottom: none;
            padding: 15px 20px;
        }
        
        .card-header i {
            margin-right: 8px;
        }
        
        .stat-card {
            background: var(--white);
            border-radius: 15px;
            padding: 20px;
            text-align: center;
            box-shadow: 0 5px 20px rgba(0,0,0,0.05);
            height: 100%;
            border: 1px solid var(--border);
        }
        
        .stat-icon {
            font-size: 32px;
            color: var(--primary);
            margin-bottom: 10px;
        }
        
        .stat-number {
            font-size: 24px;
            font-weight: 700;
            color: var(--text-dark);
        }
        
        .stat-label {
            color: var(--text-light);
            font-size: 13px;
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        
        .amount-positive {
            color: var(--success);
            font-weight: 600;
        }
        
        .amount-negative {
            color: var(--danger);
            font-weight: 600;
        }
        
        .badge-paye {
            background: var(--success);
            color: var(--white);
            padding: 5px 10px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 500;
        }
        
        .badge-impaye {
            background: var(--danger);
            color: var(--white);
            padding: 5px 10px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 500;
        }
        
        .badge-attente {
            background: var(--warning);
            color: var(--text-dark);
            padding: 5px 10px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 500;
        }
        
        .badge-ordre-final {
            background: var(--success);
            color: var(--white);
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 14px;
            font-weight: 600;
        }
        
        .badge-ordre-temp {
            background: var(--warning);
            color: var(--white);
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 14px;
            font-weight: 600;
        }
        
        .badge-mode-auto {
            background: var(--info);
            color: var(--white);
            padding: 8px 15px;
            border-radius: 50px;
            font-size: 14px;
            font-weight: 600;
        }
        
        .badge-mode-manuel {
            background: var(--warning);
            color: var(--text-dark);
            padding: 8px 15px;
            border-radius: 50px;
            font-size: 14px;
            font-weight: 600;
        }
        
        .bg-light.text-dark {
            background-color: rgba(255,255,255,0.2) !important;
            color: var(--white) !important;
            padding: 5px 15px;
        }
        
        .text-primary-custom {
            color: var(--primary);
        }
        
        .table thead th {
            background-color: var(--bg-light);
            color: var(--primary);
            border-bottom: 2px solid var(--primary);
            font-weight: 600;
        }
        
        .beneficiaire-card {
            background: linear-gradient(135deg, #DBEAFE 0%, #BFDBFE 100%);
            border-radius: 15px;
            padding: 20px;
            margin-bottom: 20px;
            border-left: 5px solid var(--primary);
        }
        
        .beneficiaire-avatar {
            width: 60px;
            height: 60px;
            background: var(--primary);
            color: var(--white);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 28px;
        }
        
        .info-badge {
            background: #DBEAFE;
            color: var(--primary);
            padding: 5px 15px;
            border-radius: 50px;
            font-size: 14px;
            font-weight: 600;
        }
        
        .section-title {
            margin: 30px 0 20px;
            font-weight: 600;
            color: var(--text-dark);
            border-bottom: 2px solid var(--primary);
            padding-bottom: 10px;
        }
        
        h1, h2, h3, h4 {
            color: var(--text-dark);
        }
        
        .btn-primary-custom {
            background-color: var(--primary);
            color: var(--white);
            border: none;
            padding: 8px 20px;
            border-radius: 5px;
            transition: all 0.3s;
        }
        
        .btn-primary-custom:hover {
            background-color: #152b63;
            color: var(--white);
        }
        
        .btn-outline-primary {
            border: 2px solid var(--primary);
            color: var(--primary);
            background: transparent;
        }
        
        .btn-outline-primary:hover {
            background: var(--primary);
            color: var(--white);
        }
        
        .btn-outline-secondary {
            border: 2px solid var(--text-light);
            color: var(--text-light);
            background: transparent;
        }
        
        .btn-outline-secondary:hover {
            background: var(--text-light);
            color: var(--white);
        }
        
        .caisse-info {
            background: var(--info-bg);
            color: var(--primary);
            border-left: 4px solid var(--info);
            padding: 15px;
            border-radius: 10px;
            margin: 15px 0;
        }
        
        .table td {
            vertical-align: middle;
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
                    <i class="bi bi-building me-1"></i> <?= htmlspecialchars($_SESSION['association_nom'] ?? 'Association') ?>
                </span>
                <span class="nav-link">
                    <i class="bi bi-person-circle me-1"></i> <?= htmlspecialchars($_SESSION['user_nom']) ?>
                </span>
                <a class="nav-link" href="../logout.php">
                    <i class="bi bi-box-arrow-right me-1"></i> Déconnexion
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
                    <div class="d-flex align-items-center">
                        <span class="badge bg-light text-dark me-3"><?= ucfirst($tontine->type_tontine) ?></span>
                        <?php if($type_tontine == 'djangui'): ?>
                            <span class="badge <?= $tontine->mode_beneficiaire == 'auto' ? 'badge-mode-auto' : 'badge-mode-manuel' ?> me-3">
                                <i class="bi bi-<?= $tontine->mode_beneficiaire == 'auto' ? 'robot' : 'person' ?>"></i>
                                Mode <?= $tontine->mode_beneficiaire == 'auto' ? 'Automatique' : 'Manuel' ?>
                            </span>
                        <?php endif; ?>
                        <span class="info-badge">
                            <i class="bi bi-people"></i> <?= $total_membres ?> membre<?= $total_membres > 1 ? 's' : '' ?>
                        </span>
                        <span class="info-badge ms-2">
                            <i class="bi bi-cash-stack"></i> <?= number_format($tontine->montant_cotisation, 0, ',', ' ') ?> F
                        </span>
                        <?php if($type_tontine == 'solidarite' || $type_tontine == 'pret'): ?>
                            <span class="info-badge ms-2" style="background: var(--info-bg);">
                                <i class="bi bi-piggy-bank"></i> Solde: <?= number_format($tontine->solde_caisse, 0, ',', ' ') ?> F
                            </span>
                        <?php endif; ?>
                    </div>
                </div>
                <a href="../dashboard.php" class="btn-retour">
                    <i class="bi bi-arrow-left me-2"></i>Retour
                </a>
            </div>
        </div>
    </div>

    <div class="container mb-5">

        <!-- Prochain bénéficiaire (UNIQUEMENT pour Djangui et Anniversaire) -->
        <?php if(($type_tontine == 'djangui' || $type_tontine == 'anniversaire') && $prochain_beneficiaire): ?>
        <div class="beneficiaire-card">
            <div class="row align-items-center">
                <div class="col-auto">
                    <div class="beneficiaire-avatar">
                        <i class="bi bi-trophy-fill"></i>
                    </div>
                </div>
                <div class="col">
                    <small class="text-<?= $tontine->mode_beneficiaire == 'auto' ? 'primary' : 'warning' ?> fw-bold">
                        <i class="bi bi-star-fill"></i> PROCHAIN BÉNÉFICIAIRE
                    </small>
                    <h3 class="mb-1"><?= htmlspecialchars($prochain_beneficiaire['prenom'] . ' ' . $prochain_beneficiaire['nom']) ?></h3>
                    <div class="d-flex align-items-center">
                        <span class="<?= $ordre_final_existe ? 'badge-ordre-final' : 'badge-ordre-temp' ?> me-3">
                            <i class="bi bi-hash"></i> Ordre <?= $prochain_beneficiaire['ordre_actuel'] ?>
                        </span>
                        <?php if(!empty($prochain_beneficiaire['telephone'])): ?>
                        <span class="text-muted">
                            <i class="bi bi-telephone"></i> <?= htmlspecialchars($prochain_beneficiaire['telephone']) ?>
                        </span>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="col-auto text-center">
                    <div class="display-4 mb-2">📅</div>
                    <span class="badge bg-primary p-3">
                        Prochaine séance : <?= date('d/m/Y', strtotime($prochaine_reunion)) ?>
                    </span>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- Cartes de statistiques personnelles -->
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
                        <i class="bi bi-clock-history"></i>
                    </div>
                    <div class="stat-number"><?= $stats['nb_paye'] ?? 0 ?></div>
                    <div class="stat-label">Séances payées</div>
                </div>
            </div>
            <div class="col-md-3 mb-3">
                <div class="stat-card">
                    <div class="stat-icon">
                        <i class="bi bi-exclamation-triangle"></i>
                    </div>
                    <div class="stat-number"><?= $stats['nb_retard'] ?? 0 ?></div>
                    <div class="stat-label">Retards</div>
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

        <!-- Section : Liste des membres -->
        <h4 class="section-title">
            <i class="bi bi-people"></i> Membres de la tontine (<?= $total_membres ?>)
        </h4>
        <div class="card mb-4">
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead>
                            <tr>
                                <th class="text-center">#</th>
                                <th>Membre</th>
                                <th>Contact</th>
                                <?php if($type_tontine == 'anniversaire'): ?>
                                    <th class="text-center">Anniversaire</th>
                                <?php endif; ?>
                                <?php if($type_tontine == 'djangui' || $type_tontine == 'anniversaire'): ?>
                                    <th class="text-center">Ordre</th>
                                <?php endif; ?>
                                <th class="text-center">Statut</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach($membres_liste as $index => $m): 
                                $est_prochain = ($prochain_beneficiaire && $prochain_beneficiaire['id'] == $m['id']);
                                $est_moi = ($m['user_id'] == $userId);
                            ?>
                                <tr class="<?= $est_prochain ? 'table-primary' : '' ?>">
                                    <td class="text-center"><?= $index + 1 ?></td>
                                    <td>
                                        <strong><?= htmlspecialchars($m['prenom'] . ' ' . $m['nom']) ?></strong>
                                        <?php if($est_moi): ?>
                                            <span class="badge bg-info ms-1">Moi</span>
                                        <?php endif; ?>
                                        <?php if($est_prochain && ($type_tontine == 'djangui' || $type_tontine == 'anniversaire')): ?>
                                            <span class="badge bg-success ms-1">Prochain</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <i class="bi bi-telephone"></i> <?= htmlspecialchars($m['telephone'] ?? '-') ?>
                                    </td>
                                    <?php if($type_tontine == 'anniversaire'): ?>
                                        <td class="text-center">
                                            <?php if(!empty($m['date_anniversaire'])): ?>
                                                <?= date('d/m', strtotime($m['date_anniversaire'])) ?>
                                            <?php else: ?>
                                                <span class="text-muted">Non renseigné</span>
                                            <?php endif; ?>
                                        </td>
                                    <?php endif; ?>
                                    <?php if($type_tontine == 'djangui' || $type_tontine == 'anniversaire'): ?>
                                        <td class="text-center">
                                            <span class="<?= $ordre_final_existe ? 'badge-ordre-final' : 'badge-ordre-temp' ?>">
                                                #<?= $m['ordre_actuel'] ?>
                                            </span>
                                        </td>
                                    <?php endif; ?>
                                    <td class="text-center">
                                        <?php if($m['est_actif']): ?>
                                            <span class="badge bg-success">Actif</span>
                                        <?php else: ?>
                                            <span class="badge bg-secondary">Inactif</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="row">
            <!-- Dernières séances -->
            <div class="col-md-6">
                <h4 class="section-title">
                    <i class="bi bi-calendar-check"></i> Dernières séances
                </h4>
                <?php if(empty($seances)): ?>
                    <div class="alert alert-info">
                        <i class="bi bi-info-circle"></i> Aucune séance pour le moment
                    </div>
                <?php else: ?>
                <div class="card">
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover mb-0">
                                <thead>
                                    <tr>
                                        <th>Date</th>
                                        <?php if($type_tontine == 'djangui' || $type_tontine == 'anniversaire'): ?>
                                            <th>Bénéficiaire</th>
                                        <?php endif; ?>
                                        <th class="text-center">Présents</th>
                                        <th class="text-center">Retards</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach($seances as $s): ?>
                                        <tr>
                                            <td><?= date('d/m/Y', strtotime($s['date_seance'])) ?></td>
                                            <?php if($type_tontine == 'djangui' || $type_tontine == 'anniversaire'): ?>
                                                <td>
                                                    <?php if($s['beneficiaire_id']): ?>
                                                        <?= htmlspecialchars($s['benef_prenom'] . ' ' . $s['benef_nom']) ?>
                                                    <?php else: ?>
                                                        <span class="text-muted">Non désigné</span>
                                                    <?php endif; ?>
                                                </td>
                                            <?php endif; ?>
                                            <td class="text-center">
                                                <span class="badge bg-success"><?= $s['nb_presents'] ?></span>
                                            </td>
                                            <td class="text-center">
                                                <span class="badge bg-warning"><?= $s['nb_retards'] ?></span>
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

            <!-- Dernières cotisations et amendes -->
            <div class="col-md-6">
                <!-- Dernières cotisations -->
                <?php if(!empty($dernieres_cotisations)): ?>
                <h4 class="section-title">
                    <i class="bi bi-clock-history"></i> Dernières cotisations
                </h4>
                <div class="card mb-4">
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover mb-0">
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
                                                    <span class="badge-paye"><i class="bi bi-check-circle me-1"></i>Payé</span>
                                                <?php elseif($c['statut'] == 'retard'): ?>
                                                    <span class="badge-impaye"><i class="bi bi-exclamation-circle me-1"></i>Retard</span>
                                                <?php else: ?>
                                                    <span class="badge-attente"><i class="bi bi-hourglass me-1"></i>En attente</span>
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
                <h4 class="section-title">
                    <i class="bi bi-exclamation-triangle"></i> Mes amendes
                </h4>
                <div class="card">
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover mb-0">
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
                                            <td><?= ucfirst(str_replace('_', ' ', $a['type_amende'] ?? 'Amende')) ?></td>
                                            <td class="<?= $a['est_paye'] ? 'amount-positive' : 'amount-negative' ?>">
                                                <?= number_format($a['montant'], 0, ',', ' ') ?> F
                                            </td>
                                            <td>
                                                <?php if($a['est_paye']): ?>
                                                    <span class="badge-paye"><i class="bi bi-check-circle me-1"></i>Payé</span>
                                                <?php else: ?>
                                                    <span class="badge-impaye"><i class="bi bi-x-circle me-1"></i>Impayé</span>
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
        </div>

        <!-- Actions disponibles -->
        <div class="row mt-4">
            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <i class="bi bi-gear"></i> Actions
                    </div>
                    <div class="card-body">
                        <a href="details_tontine.php?id=<?= $tontine_id ?>" class="btn btn-primary-custom me-2">
                            <i class="bi bi-arrow-repeat"></i> Actualiser
                        </a>
                        <a href="../etats/etats_membre.php?tontine_id=<?= $tontine_id ?>" class="btn btn-outline-primary me-2">
                            <i class="bi bi-file-text"></i> Mes états détaillés
                        </a>
                        <a href="../dashboard.php" class="btn btn-outline-secondary">
                            <i class="bi bi-arrow-left"></i> Retour au tableau de bord
                        </a>
                    </div>
                </div>
            </div>
        </div>

    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>