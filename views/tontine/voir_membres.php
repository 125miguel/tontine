<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();

if(!isset($_SESSION['user_id']) || $_SESSION['association_role'] != 'admin') {
    header("Location: ../auth/login.php");
    exit();
}

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../models/Tontine.php';
require_once __DIR__ . '/../../models/MembreTontine.php';

$retire = $_GET['retire'] ?? 0;
$error = $_GET['error'] ?? 0;
$desactive = $_GET['desactive'] ?? 0;
$supprime = $_GET['supprime'] ?? 0;
$error_activites = $_GET['error'] ?? 0;
$reset = $_GET['reset'] ?? 0;
$melange = $_GET['melange'] ?? 0;
$ordre_genere = $_GET['ordre_genere'] ?? 0;

$database = new Database();
$db = $database->getConnection();

$tontine_id = $_GET['id'] ?? 0;

// Vérifier que la tontine appartient bien à cet admin
$tontine = new Tontine($db);
if(!$tontine->getById($tontine_id) || $tontine->admin_id != $_SESSION['user_id']) {
    header("Location: mes_tontines.php");
    exit();
}

// Vérifier le mode de la tontine
$mode_auto = ($tontine->mode_beneficiaire == 'auto');

// Vérifier si l'ordre final a déjà été généré
$query = "SELECT COUNT(*) as nb FROM membre_tontine 
          WHERE tontine_id = :tid AND ordre_final IS NOT NULL";
$stmt = $db->prepare($query);
$stmt->execute(['tid' => $tontine_id]);
$ordre_final_existe = $stmt->fetch()['nb'] > 0;

$membreTontine = new MembreTontine($db);

// Récupérer les membres avec leur adresse
$query = "SELECT m.*, u.nom, u.prenom, u.email, u.telephone, u.adresse 
          FROM membre_tontine m
          JOIN users u ON m.user_id = u.id
          WHERE m.tontine_id = :tontine_id
          ORDER BY COALESCE(m.ordre_final, m.ordre_tour) ASC";
$stmt = $db->prepare($query);
$stmt->execute(['tontine_id' => $tontine_id]);
$membres = $stmt;

// Récupérer le prochain bénéficiaire (pour tous les modes)
$prochain_beneficiaire = null;
if($tontine->mode_beneficiaire == 'manuel' || $tontine->mode_beneficiaire == 'auto') {
    $prochain_beneficiaire = $membreTontine->getProchainBeneficiaire($tontine_id);
}

// Récupérer le dernier bénéficiaire pour information
$query_dernier = "SELECT s.*, u.prenom, u.nom 
                  FROM seances s
                  LEFT JOIN membre_tontine mt ON s.beneficiaire_id = mt.id
                  LEFT JOIN users u ON mt.user_id = u.id
                  WHERE s.tontine_id = :tid AND s.beneficiaire_id IS NOT NULL
                  ORDER BY s.date_seance DESC LIMIT 1";
$stmt_dernier = $db->prepare($query_dernier);
$stmt_dernier->execute(['tid' => $tontine_id]);
$dernier_beneficiaire = $stmt_dernier->fetch(PDO::FETCH_ASSOC);

// ========== CALCUL DE LA PROGRESSION PAR SÉANCES ==========
$cycle_termine = false;
$progression_cycle = 0;
$seances_effectuees = 0;
$seances_prevues = 0;
$jours_restants = 0;

if($tontine->type_cycle) {
    $date_debut = new DateTime($tontine->date_debut_cycle);
    $date_fin = new DateTime($tontine->date_fin_cycle);
    $aujourdhui = new DateTime();
    
    // Calculer le nombre de séances prévues selon la périodicité
    switch($tontine->periodicite) {
        case 'hebdomadaire':
            $interval = new DateInterval('P1W'); // 1 semaine
            $interval_jours = 7;
            break;
        case 'mensuel':
            $interval = new DateInterval('P1M'); // 1 mois
            $interval_jours = 30; // Approximation
            break;
        case 'journalier':
            $interval = new DateInterval('P1D'); // 1 jour
            $interval_jours = 1;
            break;
        default:
            $interval = new DateInterval('P1W'); // Par défaut hebdomadaire
            $interval_jours = 7;
    }
    
    // Compter le nombre de séances prévues entre début et fin
    $periode = new DatePeriod($date_debut, $interval, $date_fin);
    $seances_prevues = iterator_count($periode);
    
    // Compter le nombre de séances réellement effectuées
    $query_seances = "SELECT COUNT(*) as total FROM seances 
                      WHERE tontine_id = :tid 
                      AND date_seance BETWEEN :debut AND :fin";
    $stmt_seances = $db->prepare($query_seances);
    $stmt_seances->execute([
        'tid' => $tontine_id,
        'debut' => $tontine->date_debut_cycle,
        'fin' => $tontine->date_fin_cycle
    ]);
    $seances_effectuees = $stmt_seances->fetch()['total'] ?? 0;
    
    // Calculer la progression en pourcentage (basée sur les séances)
    if($seances_prevues > 0) {
        $progression_cycle = round(($seances_effectuees / $seances_prevues) * 100);
    }
    
    // Vérifier si le cycle est terminé
    $cycle_termine = ($aujourdhui > $date_fin) || ($seances_effectuees >= $seances_prevues);
    
    // Calculer les jours restants (info temporelle)
    if($aujourdhui < $date_fin) {
        $jours_restants = $aujourdhui->diff($date_fin)->days;
    }
    
    // Si le cycle est terminé mais pas marqué comme tel, on le marque
    if($cycle_termine && !$tontine->cycle_termine) {
        $tontine->terminerCycle();
    }
}

// Récupérer le nombre de cycles déjà effectués
$query_cycles = "SELECT COUNT(*) as total FROM cycles_tontine WHERE tontine_id = :tid";
$stmt_cycles = $db->prepare($query_cycles);
$stmt_cycles->execute(['tid' => $tontine_id]);
$nb_cycles_effectues = $stmt_cycles->fetch()['total'];
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Membres de la tontine - <?= htmlspecialchars($tontine->nom) ?></title>
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
            --success-bg: #D1FAE5;
            --warning: #F59E0B;
            --warning-bg: #FEF3C7;
            --danger: #EF4444;
            --danger-bg: #FEE2E2;
            --info: #3B82F6;
            --info-bg: #DBEAFE;
        }
        
        body {
            background: var(--bg-light);
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        .navbar {
            background: var(--primary);
        }
        
        .navbar-brand, .nav-link {
            color: var(--white) !important;
        }
        
        .card {
            border-radius: 15px;
            border: 1px solid var(--border);
            box-shadow: 0 5px 20px rgba(0,0,0,0.05);
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
        
        .btn-success {
            background: var(--success);
            border: none;
        }
        
        .btn-success:hover {
            background: #0E9F6E;
        }
        
        .btn-warning {
            background: var(--warning);
            border: none;
            color: var(--white);
        }
        
        .btn-warning:hover {
            background: #D97706;
        }
        
        .btn-danger {
            background: var(--danger);
            border: none;
        }
        
        .btn-danger:hover {
            background: #DC2626;
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
        
        .btn-outline-warning {
            border: 2px solid var(--warning);
            color: var(--warning);
            background: transparent;
        }
        
        .btn-outline-warning:hover {
            background: var(--warning);
            color: var(--white);
        }
        
        .btn-outline-danger {
            border: 2px solid var(--danger);
            color: var(--danger);
            background: transparent;
        }
        
        .btn-outline-danger:hover {
            background: var(--danger);
            color: var(--white);
        }
        
        .btn-outline-info {
            border: 2px solid var(--info);
            color: var(--info);
            background: transparent;
        }
        
        .btn-outline-info:hover {
            background: var(--info);
            color: var(--white);
        }
        
        .badge-actif {
            background: var(--success);
            color: var(--white);
            padding: 5px 10px;
            border-radius: 20px;
            font-size: 12px;
        }
        
        .badge-inactif {
            background: var(--text-light);
            color: var(--white);
            padding: 5px 10px;
            border-radius: 20px;
            font-size: 12px;
        }
        
        .badge-ordre-final {
            background: var(--success);
            color: var(--white);
            padding: 8px 12px;
            border-radius: 50%;
            font-size: 16px;
            font-weight: 700;
            width: 40px;
            height: 40px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
        }
        
        .badge-ordre-temp {
            background: var(--warning);
            color: var(--white);
            padding: 8px 12px;
            border-radius: 50%;
            font-size: 16px;
            font-weight: 700;
            width: 40px;
            height: 40px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
        }
        
        .alert {
            border-radius: 10px;
            border: none;
        }
        
        .alert-success {
            background: var(--success-bg);
            color: #065F46;
        }
        
        .alert-danger {
            background: var(--danger-bg);
            color: #991B1B;
        }
        
        .alert-warning {
            background: var(--warning-bg);
            color: #92400E;
        }
        
        .alert-info {
            background: var(--info-bg);
            color: var(--primary);
            border: none;
        }
        
        .table th {
            background: var(--primary);
            color: var(--white);
            font-weight: 600;
        }
        
        .table td {
            vertical-align: middle;
        }
        
        .beneficiaire-card {
            border-left: 4px solid var(--primary);
            transition: transform 0.3s;
        }
        
        .beneficiaire-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 30px rgba(30, 58, 138, 0.15);
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
            font-size: 24px;
            font-weight: bold;
        }
        
        .mode-badge-auto {
            background: var(--info);
            color: white;
            padding: 8px 15px;
            border-radius: 50px;
        }
        
        .mode-badge-manuel {
            background: var(--warning);
            color: var(--text-dark);
            padding: 8px 15px;
            border-radius: 50px;
        }
        
        /* Styles pour les cycles */
        .cycle-card {
            background: linear-gradient(135deg, var(--primary) 0%, var(--primary-light) 100%);
            color: var(--white);
            border-radius: 15px;
            padding: 20px;
            margin-bottom: 20px;
        }
        
        .cycle-progress {
            height: 10px;
            background: rgba(255,255,255,0.3);
            border-radius: 5px;
            margin: 15px 0;
        }
        
        .cycle-progress-bar {
            height: 100%;
            background: var(--white);
            border-radius: 5px;
            transition: width 0.3s;
        }
        
        .cycle-stats {
            display: flex;
            justify-content: space-between;
            margin-top: 10px;
            font-size: 14px;
            flex-wrap: wrap;
            gap: 10px;
        }
        
        .cycle-badge {
            background: rgba(255,255,255,0.2);
            padding: 5px 15px;
            border-radius: 50px;
            font-size: 14px;
        }
        
        .cycle-terminated {
            background: var(--warning-bg);
            color: var(--text-dark);
            border-left: 4px solid var(--warning);
        }
        
        .stat-highlight {
            font-weight: 700;
            font-size: 16px;
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
                <span class="nav-link">
                    <i class="bi bi-building"></i> <?= htmlspecialchars($_SESSION['association_nom']) ?>
                </span>
                <a class="nav-link" href="ajouter_membre.php?id=<?= $tontine_id ?><?= $tontine->mode_beneficiaire == 'manuel' ? '&mode=manuel' : '' ?>">
                    <i class="bi bi-person-plus"></i> Ajouter
                </a>
                <a class="nav-link" href="mes_tontines.php">
                    <i class="bi bi-arrow-left"></i> Retour
                </a>
            </div>
        </div>
    </nav>

    <div class="container mt-5">
        
        <!-- Messages de confirmation -->
        <?php if($retire == 1): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <i class="bi bi-check-circle-fill me-2"></i>  Membre retiré avec succès !
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <?php if($desactive == 1): ?>
            <div class="alert alert-warning alert-dismissible fade show" role="alert">
                <i class="bi bi-person-x-fill me-2"></i>  Membre désactivé avec succès (données conservées).
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <?php if($supprime == 1): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <i class="bi bi-trash-fill me-2"></i>  Membre supprimé définitivement.
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <?php if($error == 1): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <i class="bi bi-exclamation-triangle-fill me-2"></i>  Erreur lors du retrait du membre.
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <?php if($error_activites == 'activites'): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <i class="bi bi-exclamation-triangle-fill me-2"></i>  Impossible de supprimer : ce membre a déjà des activités (cotisations, amendes, bénéficiaire).
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <?php if($melange == 1): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <i class="bi bi-shuffle"></i>  Ordre des bénéficiaires mélangé avec succès !
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <?php if($ordre_genere == 1): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <i class="bi bi-check-circle-fill"></i>  Ordre définitif des bénéficiaires généré avec succès !
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <?php if($reset == 1 && isset($_SESSION['reset_password'])): ?>
            <div class="alert alert-info alert-dismissible fade show" role="alert">
                <h5 class="alert-heading"><i class="bi bi-key-fill"></i>  Nouveau mot de passe généré</h5>
                <p>
                    <strong>Membre :</strong> <?= htmlspecialchars($_SESSION['reset_user']) ?><br>
                    <strong>Nouveau mot de passe :</strong> 
                    <span class="badge bg-dark fs-5 p-2"><?= $_SESSION['reset_password'] ?></span>
                </p>
                <p class="mb-0">
                    <small> À communiquer au membre. Il devra le changer à sa prochaine connexion.</small>
                </p>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
            <?php unset($_SESSION['reset_password']); ?>
            <?php unset($_SESSION['reset_user']); ?>
        <?php endif; ?>
        
        <?php if(isset($_GET['ordre_finalise']) && $_GET['ordre_finalise'] == 1): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <i class="bi bi-check-circle-fill me-2"></i> 
                <strong>Ordre finalisé !</strong> L'ordre des bénéficiaires a été défini et ne peut plus être modifié.
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <?php if(isset($_GET['error']) && $_GET['error'] == 'cycle_non_termine'): ?>
            <div class="alert alert-warning alert-dismissible fade show" role="alert">
                <i class="bi bi-exclamation-triangle-fill me-2"></i> 
                Le cycle n'est pas encore terminé. Vous ne pouvez pas renouveler maintenant.
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <!-- Carte d'information du cycle -->
        <?php if($tontine->type_cycle): ?>
            <div class="cycle-card mb-4">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h5 class="mb-1">
                            <i class="bi bi-arrow-repeat"></i> Cycle n°<?= $tontine->cycle_actuel ?>
                            <?php if($nb_cycles_effectues > 0): ?>
                                <span class="cycle-badge ms-2">
                                    <i class="bi bi-clock-history"></i> <?= $nb_cycles_effectues ?> cycle(s) précédent(s)
                                </span>
                            <?php endif; ?>
                        </h5>
                        <p class="mb-0">
                            Du <?= date('d/m/Y', strtotime($tontine->date_debut_cycle)) ?> 
                            au <?= date('d/m/Y', strtotime($tontine->date_fin_cycle)) ?>
                            (<?= ucfirst($tontine->type_cycle) ?>)
                        </p>
                    </div>
                    <?php if($cycle_termine): ?>
                        <a href="renouveler_cycle.php?id=<?= $tontine_id ?>" class="btn btn-warning">
                            <i class="bi bi-arrow-repeat"></i> Renouveler le cycle
                        </a>
                    <?php endif; ?>
                </div>

                <?php if(!$cycle_termine): ?>
                    <div class="cycle-progress">
                        <div class="cycle-progress-bar" style="width: <?= $progression_cycle ?>%;"></div>
                    </div>
                    <div class="cycle-stats">
                        <span>
                            <i class="bi bi-people"></i> 
                            <span class="stat-highlight"><?= $seances_effectuees ?>/<?= $seances_prevues ?></span> séances
                            (<?= $progression_cycle ?>%)
                        </span>
                        <span>
                            <i class="bi bi-calendar"></i> 
                            Fin prévue : <?= date('d/m/Y', strtotime($tontine->date_fin_cycle)) ?>
                            <?php if($jours_restants > 0): ?>
                                (<span class="stat-highlight"><?= $jours_restants ?></span> jours)
                            <?php endif; ?>
                        </span>
                    </div>
                <?php else: ?>
                    <div class="alert cycle-terminated mt-3 mb-0">
                        <i class="bi bi-check-circle-fill me-2"></i>
                        Cycle terminé ! <strong><?= $seances_effectuees ?></strong> séances effectuées sur <strong><?= $seances_prevues ?></strong> prévues.
                    </div>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <!-- En-tête avec titre et boutons -->
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h2 class="mb-1"><i class="bi bi-people-fill"></i> Membres de "<?= htmlspecialchars($tontine->nom) ?>"</h2>
                <div class="mt-2">
                    <span class="badge bg-<?= $mode_auto ? 'info' : 'warning' ?> p-2 me-2">
                        <i class="bi bi-<?= $mode_auto ? 'robot' : 'person' ?>"></i>
                        Mode <?= $mode_auto ? 'Automatique' : 'Manuel' ?>
                    </span>
                    <?php if($dernier_beneficiaire): ?>
                    <span class="text-muted">
                        <i class="bi bi-clock-history"></i> 
                        Dernier bénéficiaire : <?= htmlspecialchars($dernier_beneficiaire['prenom'] . ' ' . $dernier_beneficiaire['nom']) ?>
                        (<?= date('d/m/Y', strtotime($dernier_beneficiaire['date_seance'])) ?>)
                    </span>
                    <?php endif; ?>
                </div>
            </div>
            <div>
                <?php if($mode_auto && !$ordre_final_existe): ?>
                    <a href="generer_ordre_final.php?id=<?= $tontine_id ?>" 
                       class="btn btn-warning me-2"
                       onclick="return confirm('Générer l\'ordre définitif des bénéficiaires ?\nCette action est irréversible.')">
                        <i class="bi bi-shuffle"></i> Générer l'ordre final
                    </a>
                <?php endif; ?>
                <a href="ajouter_membre.php?id=<?= $tontine_id ?><?= $tontine->mode_beneficiaire == 'manuel' ? '&mode=manuel' : '' ?>" class="btn btn-success">
                    <i class="bi bi-person-plus-fill"></i> Ajouter un membre
                </a>
                <?php 
                // Vérifier si l'ordre a déjà été finalisé
                $query = "SELECT ordre_finalise FROM tontines WHERE id = :id";
                $stmt = $db->prepare($query);
                $stmt->execute(['id' => $tontine_id]);
                $ordre_finalise = $stmt->fetch()['ordre_finalise'] ?? 0;

                if($tontine->mode_beneficiaire == 'manuel' && $membres->rowCount() > 0 && !$ordre_finalise): ?>
                <a href="ordonner_membres.php?tontine_id=<?= $tontine_id ?>" class="btn btn-primary ms-2">
                    <i class="bi bi-sort-numeric-down"></i> Ordonner
                </a>
                <?php endif; ?>
                
                <?php if($tontine->type_cycle && $cycle_termine): ?>
                <a href="renouveler_cycle.php?id=<?= $tontine_id ?>" class="btn btn-outline-info ms-2">
                    <i class="bi bi-arrow-repeat"></i> Renouveler
                </a>
                <?php endif; ?>
            </div>
        </div>

        <!-- Carte du prochain bénéficiaire -->
        <?php if($prochain_beneficiaire && !$cycle_termine): ?>
        <div class="card mb-4 beneficiaire-card">
            <div class="card-body">
                <div class="row align-items-center">
                    <div class="col-auto">
                        <div class="beneficiaire-avatar">
                            <i class="bi bi-trophy-fill"></i>
                        </div>
                    </div>
                    <div class="col">
                        <div class="d-flex align-items-center mb-2">
                            <h4 class="mb-0 me-3">Prochain bénéficiaire</h4>
                            <span class="badge bg-<?= $mode_auto ? 'info' : 'warning' ?> p-2">
                                <i class="bi bi-<?= $mode_auto ? 'robot' : 'person' ?>"></i>
                                Ordre manuel
                            </span>
                        </div>
                        <h3 class="mb-1" style="color: var(--primary);">
                            <?= htmlspecialchars($prochain_beneficiaire['prenom'] . ' ' . $prochain_beneficiaire['nom']) ?>
                        </h3>
                        <div class="row mt-2">
                            <div class="col-md-3">
                                <small class="text-muted d-block">Ordre n°</small>
                                <strong><?= $prochain_beneficiaire['ordre_tour'] ?></strong>
                            </div>
                            <div class="col-md-3">
                                <small class="text-muted d-block">Téléphone</small>
                                <strong><?= htmlspecialchars($prochain_beneficiaire['telephone'] ?? 'Non renseigné') ?></strong>
                            </div>
                            <?php if(!empty($prochain_beneficiaire['email'])): ?>
                            <div class="col-md-3">
                                <small class="text-muted d-block">Email</small>
                                <strong><?= htmlspecialchars($prochain_beneficiaire['email']) ?></strong>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="col-auto">
                        <div class="text-center">
                            <span class="badge bg-success p-3">
                                <i class="bi bi-calendar-check"></i> 
                                Prochaine séance
                            </span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <?php if($membres->rowCount() == 0): ?>
            <div class="alert alert-info">
                <i class="bi bi-info-circle-fill me-2"></i> Aucun membre dans cette tontine pour le moment.
                <a href="ajouter_membre.php?id=<?= $tontine_id ?><?= $tontine->mode_beneficiaire == 'manuel' ? '&mode=manuel' : '' ?>" class="alert-link">Ajouter votre premier membre</a>
            </div>
        <?php else: ?>
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0"><i class="bi bi-list-ul"></i> Liste des membres</h5>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead>
                                <tr>
                                    <th class="text-center">#</th>
                                    <th>Membre</th>
                                    <th>Contact</th>
                                    <th>Email</th>
                                    <th>Adresse</th>
                                    <th class="text-center">Ordre</th>
                                    <th class="text-center">Statut</th>
                                    <th class="text-center">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php 
                                $compteur = 1;
                                while($m = $membres->fetch(PDO::FETCH_ASSOC)): 
                                    $activites = $membreTontine->aDesActivites($m['id']);
                                    $ordre_affiche = $m['ordre_final'] ?? $m['ordre_tour'];
                                    $classe_ordre = $m['ordre_final'] ? 'badge-ordre-final' : 'badge-ordre-temp';
                                    
                                    // Mettre en évidence le prochain bénéficiaire
                                    $est_prochain = ($prochain_beneficiaire && $prochain_beneficiaire['id'] == $m['id']);
                                ?>
                                    <tr class="<?= $est_prochain ? 'table-primary' : '' ?>">
                                        <td class="text-center"><?= $compteur++ ?></td>
                                        <td>
                                            <strong><?= htmlspecialchars($m['prenom'] . ' ' . $m['nom']) ?></strong>
                                            <?php if($est_prochain): ?>
                                                <span class="badge bg-success ms-2">
                                                    <i class="bi bi-star-fill"></i> Prochain
                                                </span>
                                            <?php endif; ?>
                                        </td>
                                        <td><?= htmlspecialchars($m['telephone']) ?></td>
                                        <td><?= htmlspecialchars($m['email']) ?></td>
                                        <td><?= htmlspecialchars($m['adresse'] ?? '-') ?></td>
                                        <td class="text-center">
                                            <span class="<?= $classe_ordre ?>">#<?= $ordre_affiche ?></span>
                                        </td>
                                        <td class="text-center">
                                            <?php if($m['est_actif']): ?>
                                                <span class="badge-actif"><i class="bi bi-check-circle"></i> Actif</span>
                                            <?php else: ?>
                                                <span class="badge-inactif"><i class="bi bi-slash-circle"></i> Inactif</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="text-center">
                                            <?php if($m['est_actif']): ?>
                                                <!-- Réinitialiser mot de passe -->
                                                <a href="reset_mdp_membre.php?id=<?= $m['id'] ?>&tontine_id=<?= $tontine_id ?>" 
                                                   class="btn btn-outline-primary btn-sm"
                                                   onclick="return confirm('Générer un nouveau mot de passe pour <?= htmlspecialchars($m['prenom'] . ' ' . $m['nom']) ?> ?')"
                                                   title="Réinitialiser le mot de passe">
                                                    <i class="bi bi-key"></i>
                                                </a>
                                                
                                                <?php if($activites): ?>
                                                    <!-- Désactiver seulement (a des activités) -->
                                                    <a href="desactiver_membre.php?id=<?= $m['id'] ?>&tontine_id=<?= $tontine_id ?>" 
                                                       class="btn btn-outline-warning btn-sm"
                                                       onclick="return confirm('Désactiver <?= htmlspecialchars($m['prenom'] . ' ' . $m['nom']) ?> ?\nSes données seront conservées.')"
                                                       title="Désactiver (conserve l'historique)">
                                                        <i class="bi bi-person-x"></i>
                                                    </a>
                                                <?php else: ?>
                                                    <!-- Supprimer définitivement (pas d'activités) -->
                                                    <a href="supprimer_membre.php?id=<?= $m['id'] ?>&tontine_id=<?= $tontine_id ?>" 
                                                       class="btn btn-outline-danger btn-sm"
                                                       onclick="return confirm('Supprimer définitivement <?= htmlspecialchars($m['prenom'] . ' ' . $m['nom']) ?> ?\nCette action est irréversible.')"
                                                       title="Supprimer définitivement">
                                                        <i class="bi bi-trash"></i>
                                                    </a>
                                                <?php endif; ?>
                                            <?php else: ?>
                                                <span class="text-muted">—</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <div class="row mt-4">
                <div class="col-md-6">
                    <div class="card">
                        <div class="card-body">
                            <h6 class="card-title text-muted mb-3">Récapitulatif financier</h6>
                            <p class="mb-2">
                                <strong><i class="bi bi-cash-stack"></i> Montant cotisation:</strong> 
                                <?= number_format($tontine->montant_cotisation, 0, ',', ' ') ?> FCFA
                            </p>
                            <p class="mb-0">
                                <strong><i class="bi bi-calculator"></i> Total par réunion:</strong> 
                                <?= number_format($tontine->montant_cotisation * $membres->rowCount(), 0, ',', ' ') ?> FCFA
                            </p>
                        </div>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="card">
                        <div class="card-body">
                            <h6 class="card-title text-muted mb-3">Légende des ordres</h6>
                            <p class="mb-2">
                                <span class="badge-ordre-final me-2">#1</span> Ordre définitif (mode auto)
                            </p>
                            <p class="mb-0">
                                <span class="badge-ordre-temp me-2">#1</span> Ordre provisoire (mode manuel)
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>