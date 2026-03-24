<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();

if(!isset($_SESSION['user_id']) || $_SESSION['association_role'] != 'admin') {
    header("Location: ../auth/login.php");
    exit();
}

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../models/Seance.php';
require_once __DIR__ . '/../../models/Cotisation.php';
require_once __DIR__ . '/../../models/Tontine.php';
require_once __DIR__ . '/../../models/MembreTontine.php';
require_once __DIR__ . '/../../models/RegleAmende.php';
require_once __DIR__ . '/../../models/AmendeAppliquee.php';

$database = new Database();
$db = $database->getConnection();

if(!$db) {
    die("Erreur de connexion à la base de données");
}

$seance_id = $_GET['seance_id'] ?? 0;

if(!$seance_id) {
    header("Location: mes_tontines.php");
    exit();
}

$seance = new Seance($db);
if(!$seance->getById($seance_id)) {
    header("Location: mes_tontines.php");
    exit();
}

// Récupérer la tontine pour vérifier les droits
$tontine = new Tontine($db);
$tontine->getById($seance->tontine_id);

if($tontine->admin_id != $_SESSION['user_id']) {
    header("Location: ../auth/login.php");
    exit();
}

// Déterminer le type de tontine
$type_tontine = $tontine->type_tontine;

$cotisation = new Cotisation($db);
$membreTontine = new MembreTontine($db);
$regleAmende = new RegleAmende($db);
$amendeAppliquee = new AmendeAppliquee($db);

// Traiter les amendes manuelles (POST)
if($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['appliquer_amende_manuelle'])) {
    
    $membre_id = $_POST['membre_id'] ?? 0;
    $type = $_POST['type_amende'] ?? '';
    $montant_saisi = $_POST['montant'] ?? '';
    
    if($membre_id && $type) {
        
        $regle = $regleAmende->getByType($tontine->id, $type);
        
        if($montant_saisi !== '' && $montant_saisi > 0) {
            $montant = $montant_saisi;
        } elseif($regle && $regle['montant'] > 0) {
            $montant = $regle['montant'];
        } else {
            header("Location: gerer_cotisations.php?seance_id=" . $seance_id . "&amende_erreur=1");
            exit();
        }
        
        if($regle) {
            $amendeAppliquee->appliquer(
                $seance_id,
                $membre_id,
                $regle['id'],
                $montant,
                date('Y-m-d')
            );
        } else {
            $queryNewRegle = "INSERT INTO regles_amendes 
                              (tontine_id, type_amende, montant, description) 
                              VALUES (:tid, :type, :montant, 'Amende manuelle')";
            $stmtNew = $db->prepare($queryNewRegle);
            $stmtNew->execute([
                'tid' => $tontine->id,
                'type' => $type,
                'montant' => $montant
            ]);
            $regle_id = $db->lastInsertId();
            
            $amendeAppliquee->appliquer(
                $seance_id,
                $membre_id,
                $regle_id,
                $montant,
                date('Y-m-d')
            );
        }
        
        
        header("Location: gerer_cotisations.php?seance_id=" . $seance_id . "&amende_manuelle=ok");
        exit();
    }
}

// Traiter le marquage d'un paiement
if(isset($_GET['payer'])) {
    $cotisation_id = $_GET['payer'];
    
    $queryInfo = "SELECT c.*, mt.user_id, u.prenom, u.nom 
                  FROM cotisations c
                  JOIN membre_tontine mt ON c.membre_tontine_id = mt.id
                  JOIN users u ON mt.user_id = u.id
                  WHERE c.id = :cid";
    $stmtInfo = $db->prepare($queryInfo);
    $stmtInfo->execute(['cid' => $cotisation_id]);
    $cotisation_info = $stmtInfo->fetch(PDO::FETCH_ASSOC);
    
    $cotisation->updateStatut($cotisation_id, 'paye', date('Y-m-d'));
    
    if(($type_tontine == 'solidarite' || $type_tontine == 'pret') && $cotisation_info) {
        $tontine->updateSoldeCaisse($cotisation_info['montant'], 'ajout');
        $tontine->enregistrerOperation(
            'cotisation', 
            $cotisation_info['montant'], 
            "Cotisation de " . $cotisation_info['prenom'] . ' ' . $cotisation_info['nom'],
            $cotisation_id
        );
        $tontine->getById($tontine->id);
    }
    
    header("Location: gerer_cotisations.php?seance_id=" . $seance_id);
    exit();
}

// Traiter le marquage d'un retard (avec amende automatique)
if(isset($_GET['retard'])) {
    $cotisation_id = $_GET['retard'];
    
    $queryInfo = "SELECT c.*, mt.user_id, u.prenom, u.nom 
                  FROM cotisations c
                  JOIN membre_tontine mt ON c.membre_tontine_id = mt.id
                  JOIN users u ON mt.user_id = u.id
                  WHERE c.id = :cid";
    $stmtInfo = $db->prepare($queryInfo);
    $stmtInfo->execute(['cid' => $cotisation_id]);
    $cotisation_info = $stmtInfo->fetch(PDO::FETCH_ASSOC);
    
    $cotisation->updateStatut($cotisation_id, 'retard', date('Y-m-d'));
    
    $queryMembre = "SELECT membre_tontine_id FROM cotisations WHERE id = :cid";
    $stmtMembre = $db->prepare($queryMembre);
    $stmtMembre->execute(['cid' => $cotisation_id]);
    $membre = $stmtMembre->fetch(PDO::FETCH_ASSOC);
    
    if($membre) {
        $regle = $regleAmende->getByType($tontine->id, 'retard_cotisation');
        
        if($regle && $regle['montant'] > 0) {
            $amendeAppliquee->appliquer(
                $seance_id,
                $membre['membre_tontine_id'],
                $regle['id'],
                $regle['montant'],
                date('Y-m-d')
            );
            
            if(($type_tontine == 'solidarite' || $type_tontine == 'pret') && $cotisation_info) {
                $tontine->updateSoldeCaisse($regle['montant'], 'ajout');
                $tontine->enregistrerOperation(
                    'amende', 
                    $regle['montant'], 
                    "Amende pour retard de " . $cotisation_info['prenom'] . ' ' . $cotisation_info['nom']
                );
                $tontine->getById($tontine->id);
            }
        }
    }
    
    header("Location: gerer_cotisations.php?seance_id=" . $seance_id);
    exit();
}

// Récupérer les cotisations de la séance
$cotisations = $cotisation->getBySeance($seance_id);
$total_collecte = $cotisation->calculerTotalSeance($seance_id);
$nb_payes = $cotisation->countPayes($seance_id);
$nb_total = $cotisations ? $cotisations->rowCount() : 0;

// Récupérer les amendes de la séance
$amendes = $amendeAppliquee->getBySeance($seance_id);
$total_amendes = $amendeAppliquee->calculerTotalSeance($seance_id);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gérer les cotisations</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.8.1/font/bootstrap-icons.css">
    <style>
        :root {
            --primary: #1E3A8A;
            --primary-light: #2B4A9E;
            --white: #FFFFFF;
            --bg-light: #F8FAFC;
            --text-dark: #0F172A;
            --text-light: #475569;
            --border: #E2E8F0;
            --success: #10B981;
            --success-light: #D1FAE5;
            --warning: #F59E0B;
            --danger: #EF4444;
            --info: #3B82F6;
        }
        
        body {
            background: var(--bg-light);
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        .navbar {
            background: var(--primary);
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        .navbar-brand, .nav-link {
            color: var(--white) !important;
        }
        
        .card {
            border-radius: 16px;
            border: 1px solid var(--border);
            box-shadow: 0 2px 12px rgba(0,0,0,0.05);
            margin-bottom: 20px;
            overflow: hidden;
        }
        
        .card-header {
            background: var(--primary);
            color: var(--white);
            border-radius: 16px 16px 0 0 !important;
            font-weight: 600;
            padding: 15px 20px;
            border-bottom: none;
        }
        
        .card-header.bg-warning {
            background: var(--warning) !important;
        }
        
        .btn-primary {
            background: var(--primary);
            border: none;
            padding: 8px 16px;
            border-radius: 8px;
            font-weight: 500;
            transition: all 0.2s;
        }
        
        .btn-primary:hover {
            background: var(--primary-light);
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(30, 58, 138, 0.3);
        }
        
        .btn-success {
            background: var(--success);
            border: none;
            padding: 6px 12px;
            border-radius: 6px;
            font-weight: 500;
        }
        
        .btn-success:hover {
            background: #0E9F6E;
            transform: translateY(-1px);
        }
        
        .btn-warning {
            background: var(--warning);
            border: none;
            color: var(--white);
            padding: 8px 16px;
            border-radius: 8px;
        }
        
        .btn-warning:hover {
            background: #D97706;
        }
        
        .btn-info {
            background: var(--info);
            border: none;
            color: var(--white);
            padding: 8px 16px;
            border-radius: 8px;
        }
        
        .btn-info:hover {
            background: #2563EB;
        }
        
        .btn-sm {
            padding: 4px 10px;
            font-size: 12px;
        }
        
        .badge-paye {
            background: var(--success-light);
            color: #065F46;
            padding: 5px 12px;
            border-radius: 20px;
            font-weight: 500;
        }
        
        .badge-retard {
            background: #FEF3C7;
            color: #92400E;
            padding: 5px 12px;
            border-radius: 20px;
            font-weight: 500;
        }
        
        .badge-attente {
            background: #F3F4F6;
            color: #4B5563;
            padding: 5px 12px;
            border-radius: 20px;
            font-weight: 500;
        }
        
        .alert-info {
            background: #DBEAFE;
            color: var(--primary);
            border: none;
            border-radius: 12px;
        }
        
        .alert-success {
            background: var(--success-light);
            color: #065F46;
            border: none;
            border-radius: 12px;
        }
        
        .alert-warning {
            background: #FEF3C7;
            color: #92400E;
            border: none;
            border-radius: 12px;
        }
        
        .alert-danger {
            background: #FEE2E2;
            color: #991B1B;
            border: none;
            border-radius: 12px;
        }
        
        .solde-info {
            background: linear-gradient(135deg, var(--primary) 0%, var(--primary-light) 100%);
            color: var(--white);
            border-radius: 16px;
            padding: 20px;
            margin-bottom: 20px;
        }
        
        .table th {
            background: var(--primary);
            color: var(--white);
            font-weight: 600;
            padding: 12px 10px;
        }
        
        .table td {
            padding: 12px 10px;
            vertical-align: middle;
            border-bottom: 1px solid var(--border);
        }
        
        .table tr:hover td {
            background-color: var(--bg-light);
        }
        
        .form-control, .form-select {
            border-radius: 10px;
            border: 2px solid var(--border);
            padding: 10px;
            transition: all 0.2s;
        }
        
        .form-control:focus, .form-select:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(30, 58, 138, 0.1);
            outline: none;
        }
        
        .text-muted {
            color: var(--text-light) !important;
        }
        
        .action-buttons {
            display: flex;
            gap: 12px;
            justify-content: center;
            flex-wrap: wrap;
            margin-top: 20px;
        }
    </style>
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark">
        <div class="container">
            <a class="navbar-brand" href="../dashboard.php">
                <i class="bi bi-bank2 me-2"></i>TONTONTINE
            </a>
            <div class="navbar-nav ms-auto">
                <?php if($type_tontine == 'solidarite'): ?>
                    <span class="nav-link"><span class="badge bg-info">Solidarité</span></span>
                <?php endif; ?>
                <?php if($type_tontine == 'pret'): ?>
                    <span class="nav-link"><span class="badge bg-info">Prêt</span></span>
                <?php endif; ?>
                <?php if($type_tontine == 'anniversaire'): ?>
                    <span class="nav-link"><span class="badge bg-warning">Anniversaire</span></span>
                <?php endif; ?>
                <a class="nav-link" href="mes_tontines.php">
                    <i class="bi bi-arrow-left me-1"></i> Retour
                </a>
            </div>
        </div>
    </nav>

    <div class="container mt-5">
        <div class="row">
            <div class="col-md-10 offset-md-1">
                
                <!-- Messages de notification -->
                <?php if(isset($_GET['amende_manuelle']) && $_GET['amende_manuelle'] == 'ok'): ?>
                    <div class="alert alert-success">✓ Amende manuelle appliquée avec succès !</div>
                <?php endif; ?>
                
                <?php if(isset($_GET['amende_erreur']) && $_GET['amende_erreur'] == 1): ?>
                    <div class="alert alert-danger">⚠ Montant invalide. Veuillez saisir un montant ou utiliser le montant par défaut.</div>
                <?php endif; ?>
                
                <!-- Affichage du solde pour solidarité ou prêt -->
                <?php if($type_tontine == 'solidarite' || $type_tontine == 'pret'): ?>
                    <div class="solde-info">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <i class="bi bi-piggy-bank me-2" style="font-size: 24px;"></i>
                                <strong><?= $type_tontine == 'solidarite' ? 'Caisse de solidarité' : 'Caisse des prêts' ?></strong>
                            </div>
                            <div>
                                <h3 class="mb-0"><?= number_format($tontine->solde_caisse, 0, ',', ' ') ?> FCFA</h3>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>

                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h4 class="mb-0"><i class="bi bi-cash-stack me-2"></i> Gestion des cotisations</h4>
                        <span class="badge bg-light text-dark">
                            Séance du <?= date('d/m/Y', strtotime($seance->date_seance)) ?>
                        </span>
                    </div>
                    <div class="card-body">
                        
                        <!-- Résumé de la séance -->
                        <div class="row mb-4">
                            <div class="col-md-4 mb-3">
                                <div class="alert alert-info text-center">
                                    <strong>Total collecté</strong>
                                    <h2 class="mb-0"><?= number_format($total_collecte, 0, ',', ' ') ?> F</h2>
                                </div>
                            </div>
                            <div class="col-md-4 mb-3">
                                <div class="alert alert-success text-center">
                                    <strong>Payés</strong>
                                    <h2 class="mb-0"><?= $nb_payes ?> / <?= $nb_total ?></h2>
                                </div>
                            </div>
                            <div class="col-md-4 mb-3">
                                <div class="alert alert-warning text-center">
                                    <strong>En attente</strong>
                                    <h2 class="mb-0"><?= $nb_total - $nb_payes ?></h2>
                                </div>
                            </div>
                        </div>

                        <!-- Liste des membres -->
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <?php if($type_tontine == 'djangui' || $type_tontine == 'anniversaire'): ?>
                                            <th class="text-center">Ordre</th>
                                        <?php endif; ?>
                                        <th>Membre</th>
                                        <th class="text-center">Montant</th>
                                        <th class="text-center">Statut</th>
                                        <th class="text-center">Actions</th>
                                    </thead>
                                <tbody>
                                    <?php while($c = $cotisations->fetch(PDO::FETCH_ASSOC)): ?>
                                        <tr>
                                            <?php if($type_tontine == 'djangui' || $type_tontine == 'anniversaire'): ?>
                                                <td class="text-center"><strong><?= $c['ordre_tour'] ?></strong></td>
                                            <?php endif; ?>
                                            <td>
                                                <i class="bi bi-person-circle me-2" style="color: var(--primary);"></i>
                                                <strong><?= htmlspecialchars($c['prenom'] . ' ' . $c['nom']) ?></strong>
                                            </td>
                                            <td class="text-center"><?= number_format($c['montant'], 0, ',', ' ') ?> F</td>
                                            <td class="text-center">
                                                <?php if($c['statut'] == 'paye'): ?>
                                                    <span class="badge-paye"><i class="bi bi-check-circle me-1"></i>Payé</span>
                                                <?php elseif($c['statut'] == 'retard'): ?>
                                                    <span class="badge-retard"><i class="bi bi-clock me-1"></i>Retard</span>
                                                <?php else: ?>
                                                    <span class="badge-attente"><i class="bi bi-hourglass me-1"></i>En attente</span>
                                                <?php endif; ?>
                                            </td>
                                            <td class="text-center">
                                                <?php if($c['statut'] != 'paye'): ?>
                                                    <a href="?seance_id=<?= $seance_id ?>&payer=<?= $c['id'] ?>" 
                                                       class="btn btn-success btn-sm"
                                                       onclick="return confirm('Confirmer le paiement ?')">
                                                        <i class="bi bi-check-circle"></i> Payer
                                                    </a>
                                                    <a href="?seance_id=<?= $seance_id ?>&retard=<?= $c['id'] ?>" 
                                                       class="btn btn-warning btn-sm ms-1"
                                                       onclick="return confirm('Marquer comme retard ?')">
                                                        <i class="bi bi-clock"></i> Retard
                                                    </a>
                                                <?php else: ?>
                                                    <span class="text-muted"><i class="bi bi-check-lg"></i> Payé</span>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endwhile; ?>
                                </tbody>
                             </table>
                        </div>

                        <!-- Section pour appliquer des amendes manuelles -->
                        <div class="card mt-4">
                            <div class="card-header bg-warning">
                                <h5 class="mb-0"><i class="bi bi-pencil-square me-2"></i> Appliquer une amende manuelle</h5>
                            </div>
                            <div class="card-body">
                                <form method="POST" action="" class="row g-3">
                                    <div class="col-md-4">
                                        <label class="form-label">Membre</label>
                                        <select name="membre_id" class="form-select" required>
                                            <option value="">Sélectionner un membre</option>
                                            <?php 
                                            $cotisations->execute();
                                            while($c = $cotisations->fetch(PDO::FETCH_ASSOC)): 
                                            ?>
                                                <option value="<?= $c['membre_tontine_id'] ?>">
                                                    <?= htmlspecialchars($c['prenom'] . ' ' . $c['nom']) ?>
                                                </option>
                                            <?php endwhile; 
                                            $cotisations->execute();
                                            ?>
                                        </select>
                                    </div>
                                    
                                    <div class="col-md-3">
                                        <label class="form-label">Type d'amende</label>
                                        <select name="type_amende" class="form-select" id="type_amende" required onchange="chargerMontantDefaut()">
                                            <option value="">Choisir...</option>
                                            <option value="absence">Absence</option>
                                            <option value="retard_reunion">Retard réunion</option>
                                            <option value="telephone">Téléphone</option>
                                            <option value="dispute">Dispute</option>
                                            <option value="nourriture">Nourriture</option>
                                            <option value="autre">Autre</option>
                                        </select>
                                    </div>
                                    
                                    <div class="col-md-3">
                                        <label class="form-label">Montant (FCFA)</label>
                                        <input type="number" name="montant" id="montant_amende" class="form-control" placeholder="Montant">
                                        <small class="text-muted">Laissez vide pour utiliser le montant par défaut</small>
                                    </div>
                                    
                                    <div class="col-md-2 d-flex align-items-end">
                                        <button type="submit" name="appliquer_amende_manuelle" class="btn btn-primary w-100">
                                            <i class="bi bi-plus-circle me-1"></i> Appliquer
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </div>

                        <!-- Section des amendes -->
                        <?php if(!empty($amendes)): ?>
                            <div class="card mt-4">
                                <div class="card-header bg-warning">
                                    <h5 class="mb-0"><i class="bi bi-exclamation-triangle me-2"></i> Amendes appliquées</h5>
                                </div>
                                <div class="card-body p-0">
                                    <table class="table table-sm mb-0">
                                        <thead>
                                            32;
                                                <th>Membre</th>
                                                <th>Type</th>
                                                <th class="text-center">Montant</th>
                                                <th class="text-center">Statut</th>
                                                <th class="text-center">Action</th>
                                            </thead>
                                        <tbody>
                                            <?php foreach($amendes as $a): ?>
                                                <tr>
                                                    <td><i class="bi bi-person-circle me-2" style="color: var(--primary);"></i><?= htmlspecialchars($a['prenom'] . ' ' . $a['nom']) ?></td>
                                                    <td><?= str_replace('_', ' ', $a['type_amende']) ?></td>
                                                    <td class="text-center"><?= number_format($a['montant'], 0, ',', ' ') ?> F</td>
                                                    <td class="text-center">
                                                        <?php if($a['est_paye'] == 1): ?>
                                                            <span class="badge-paye">Payé</span>
                                                        <?php else: ?>
                                                            <span class="badge-retard">Impayé</span>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td class="text-center">
                                                        <?php if($a['est_paye'] == 0): ?>
                                                            <a href="payer_amende.php?id=<?= $a['id'] ?>&seance_id=<?= $seance_id ?>" 
                                                               class="btn btn-success btn-sm"
                                                               onclick="return confirm('Marquer cette amende comme payée ?')">
                                                                <i class="bi bi-check-circle"></i> Payer
                                                            </a>
                                                        <?php else: ?>
                                                            <span class="text-muted">✓ Payé</span>
                                                        <?php endif; ?>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                        <tfoot>
                                            <tr class="table-warning">
                                                <th colspan="2">TOTAL AMENDES</th>
                                                <th colspan="3" class="text-center"><strong><?= number_format($total_amendes, 0, ',', ' ') ?> F</strong></th>
                                            </tr>
                                        </tfoot>
                                    </table>
                                </div>
                            </div>
                        <?php endif; ?>

                        <!-- Message "Tous les membres ont payé" - UNIQUEMENT pour Djangui et Anniversaire -->
                        <?php if(($type_tontine == 'djangui' || $type_tontine == 'anniversaire') && $nb_payes == $nb_total && $nb_total > 0): ?>
                            <div class="alert alert-success mt-3 text-center">
                                <i class="bi bi-check-circle-fill me-2"></i>
                                <strong>Tous les membres ont payé !</strong>
                                <p class="mb-0 mt-2">Vous pouvez maintenant désigner le bénéficiaire.</p>
                                <a href="designer_beneficiaire.php?seance_id=<?= $seance_id ?>" class="btn btn-primary mt-2">
                                    <i class="bi bi-trophy me-1"></i> Désigner le bénéficiaire
                                </a>
                            </div>
                        <?php endif; ?>

                        <?php if($seance->est_cloturee): ?>
                            <div class="alert alert-info mt-3 text-center">
                                <i class="bi bi-lock-fill me-2"></i>
                                Cette séance est clôturée.
                            </div>
                        <?php endif; ?>

                        <!-- Boutons d'action -->
                        <div class="action-buttons">
                            <?php if($type_tontine == 'djangui' || $type_tontine == 'anniversaire'): ?>
                                <a href="designer_beneficiaire.php?seance_id=<?= $seance_id ?>" class="btn btn-warning">
                                    <i class="bi bi-trophy me-1"></i> Désigner le bénéficiaire
                                </a>
                            <?php endif; ?>
                            <a href="rapport_seance.php?seance_id=<?= $seance_id ?>" class="btn btn-info">
                                <i class="bi bi-file-text me-1"></i> Rapport
                            </a>
                            <a href="gestion_presences.php?seance_id=<?= $seance_id ?>" class="btn btn-info">
                                <i class="bi bi-person-check me-1"></i> Présences
                            </a>
                        </div>

                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
    const montantsDefaut = {
        <?php
        $types = ['absence', 'retard_reunion', 'telephone', 'dispute', 'nourriture', 'autre'];
        foreach($types as $type) {
            $regle = $regleAmende->getByType($tontine->id, $type);
            $montant = $regle ? $regle['montant'] : 0;
            echo "'$type': $montant,\n";
        }
        ?>
    };

    function chargerMontantDefaut() {
        const typeSelect = document.getElementById('type_amende');
        const montantInput = document.getElementById('montant_amende');
        const type = typeSelect.value;
        
        if(type && montantsDefaut[type] > 0) {
            montantInput.placeholder = "Défaut: " + montantsDefaut[type] + " F";
            montantInput.value = '';
        } else {
            montantInput.placeholder = "Montant";
        }
    }
    </script>
</body>
</html>