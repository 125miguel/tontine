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
        
        // Pour les tontines solidarité ou prêt, ajouter l'amende au solde
        if(($type_tontine == 'solidarite' || $type_tontine == 'pret') && $montant > 0) {
            $queryMembre = "SELECT u.prenom, u.nom FROM membre_tontine mt
                            JOIN users u ON mt.user_id = u.id
                            WHERE mt.id = :mid";
            $stmtMembre = $db->prepare($queryMembre);
            $stmtMembre->execute(['mid' => $membre_id]);
            $membreInfo = $stmtMembre->fetch(PDO::FETCH_ASSOC);
            
            $tontine->updateSoldeCaisse($montant, 'ajout');
            $tontine->enregistrerOperation(
                'amende', 
                $montant, 
                "Amende manuelle (" . $type . ") pour " . $membreInfo['prenom'] . ' ' . $membreInfo['nom']
            );
            $tontine->getById($tontine->id);
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
            margin-bottom: 20px;
        }
        
        .card-header {
            background: var(--primary);
            color: var(--white);
            border-radius: 15px 15px 0 0 !important;
        }
        
        .card-header.bg-warning {
            background: var(--warning) !important;
            color: var(--white);
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
        
        .btn-info {
            background: var(--info);
            border: none;
            color: var(--white);
        }
        
        .btn-info:hover {
            background: #2563EB;
        }
        
        .badge.bg-success {
            background: var(--success) !important;
            color: var(--white);
        }
        
        .badge.bg-warning {
            background: var(--warning) !important;
            color: var(--white);
        }
        
        .badge.bg-secondary {
            background: var(--text-light) !important;
            color: var(--white);
        }
        
        .alert-info {
            background: #DBEAFE;
            color: var(--primary);
            border: none;
            border-radius: 10px;
        }
        
        .alert-success {
            background: #D1FAE5;
            color: #065F46;
            border: none;
            border-radius: 10px;
        }
        
        .alert-warning {
            background: #FEF3C7;
            color: #92400E;
            border: none;
            border-radius: 10px;
        }
        
        .alert-danger {
            background: #FEE2E2;
            color: #991B1B;
            border: none;
            border-radius: 10px;
        }
        
        .form-control, .form-select {
            border-radius: 10px;
            border: 2px solid var(--border);
            padding: 10px;
            transition: all 0.3s;
        }
        
        .form-control:focus, .form-select:focus {
            border-color: var(--primary);
            box-shadow: none;
            outline: none;
        }
        
        .table th {
            background: var(--primary);
            color: var(--white);
        }
        
        .text-muted {
            color: var(--text-light) !important;
        }
        
        .table-warning {
            background-color: #FEF3C7;
        }
        
        .solde-info {
            background: linear-gradient(135deg, var(--primary) 0%, var(--primary-light) 100%);
            color: var(--white);
            border-radius: 10px;
            padding: 15px;
            margin-bottom: 20px;
        }
        
        .table td {
            vertical-align: middle;
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
                <?php if($type_tontine == 'solidarite'): ?>
                    <span class="nav-link">
                        <span class="badge bg-info">Solidarité</span>
                    </span>
                <?php endif; ?>
                <?php if($type_tontine == 'pret'): ?>
                    <span class="nav-link">
                        <span class="badge bg-info">Prêt</span>
                    </span>
                <?php endif; ?>
                <?php if($type_tontine == 'anniversaire'): ?>
                    <span class="nav-link">
                        <span class="badge bg-warning">Anniversaire</span>
                    </span>
                <?php endif; ?>
                <a class="nav-link" href="mes_tontines.php">
                    <i class="bi bi-arrow-left"></i> Retour
                </a>
            </div>
        </div>
    </nav>

    <div class="container mt-5">
        <div class="row">
            <div class="col-md-10 offset-md-1">
                
                <!-- Messages de notification -->
                <?php if(isset($_GET['amende_manuelle']) && $_GET['amende_manuelle'] == 'ok'): ?>
                    <div class="alert alert-success"> Amende manuelle appliquée avec succès !</div>
                <?php endif; ?>
                
                <?php if(isset($_GET['amende_erreur']) && $_GET['amende_erreur'] == 1): ?>
                    <div class="alert alert-danger"> Montant invalide. Veuillez saisir un montant ou utiliser le montant par défaut.</div>
                <?php endif; ?>
                
                <!-- Affichage du solde pour solidarité ou prêt -->
                <?php if($type_tontine == 'solidarite' || $type_tontine == 'pret'): ?>
                    <div class="solde-info">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <i class="bi bi-piggy-bank" style="font-size: 24px;"></i>
                                <strong>
                                    <?php if($type_tontine == 'solidarite'): ?>
                                        Caisse de solidarité
                                    <?php else: ?>
                                        Caisse des prêts
                                    <?php endif; ?>
                                </strong>
                            </div>
                            <div>
                                <h3 class="mb-0"><?= number_format($tontine->solde_caisse, 0, ',', ' ') ?> FCFA</h3>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>

                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h4 class="mb-0"><i class="bi bi-cash-stack"></i> Gestion des cotisations</h4>
                        <span class="badge bg-light text-dark">
                            Séance du <?= date('d/m/Y', strtotime($seance->date_seance)) ?>
                        </span>
                    </div>
                    <div class="card-body">
                        
                        <!-- Résumé de la séance -->
                        <div class="row mb-4">
                            <div class="col-md-4">
                                <div class="alert alert-info text-center">
                                    <strong>Total collecté</strong>
                                    <h2><?= number_format($total_collecte, 0, ',', ' ') ?> F</h2>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="alert alert-success text-center">
                                    <strong>Payés</strong>
                                    <h2><?= $nb_payes ?> / <?= $nb_total ?></h2>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="alert alert-warning text-center">
                                    <strong>En attente</strong>
                                    <h2><?= $nb_total - $nb_payes ?></h2>
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
                                        <th>Montant</th>
                                        <th>Statut</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php while($c = $cotisations->fetch(PDO::FETCH_ASSOC)): ?>
                                        <tr>
                                            <?php if($type_tontine == 'djangui' || $type_tontine == 'anniversaire'): ?>
                                                <td class="text-center"><?= $c['ordre_tour'] ?></td>
                                            <?php endif; ?>
                                            <td>
                                                <strong><?= htmlspecialchars($c['prenom'] . ' ' . $c['nom']) ?></strong>
                                            </td>
                                            <td><?= number_format($c['montant'], 0, ',', ' ') ?> F</td>
                                            <td>
                                                <?php if($c['statut'] == 'paye'): ?>
                                                    <span class="badge bg-success">Payé</span>
                                                <?php elseif($c['statut'] == 'retard'): ?>
                                                    <span class="badge bg-warning">Retard</span>
                                                <?php else: ?>
                                                    <span class="badge bg-secondary">En attente</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?php if($c['statut'] != 'paye'): ?>
                                                    <a href="?seance_id=<?= $seance_id ?>&payer=<?= $c['id'] ?>" 
                                                       class="btn btn-sm btn-success"
                                                       onclick="return confirm('Confirmer le paiement ?')">
                                                        <i class="bi bi-check-circle"></i> Payer
                                                    </a>
                                                    <a href="?seance_id=<?= $seance_id ?>&retard=<?= $c['id'] ?>" 
                                                       class="btn btn-sm btn-warning"
                                                       onclick="return confirm('Marquer comme retard ?')">
                                                        <i class="bi bi-clock"></i> Retard
                                                    </a>
                                                <?php else: ?>
                                                    <span class="text-muted">✓ Payé</span>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endwhile; ?>
                                </tbody>
                            </table>
                        </div>

                        <!-- Section pour appliquer des amendes manuelles -->
                        <div class="card mt-4">
                            <div class="card-header" style="background: var(--warning); color: var(--white);">
                                <h5 class="mb-0"><i class="bi bi-pencil-square"></i> Appliquer une amende manuelle</h5>
                            </div>
                            <div class="card-body">
                                <form method="POST" action="" class="row g-3">
                                    <div class="col-md-4">
                                        <label class="form-label">Membre</label>
                                        <select name="membre_id" class="form-control" required>
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
                                        <select name="type_amende" class="form-control" id="type_amende" required onchange="chargerMontantDefaut()">
                                            <option value="">Choisir...</option>
                                            <option value="absence"> Absence</option>
                                            <option value="retard_reunion"> Retard réunion</option>
                                            <option value="telephone"> Téléphone</option>
                                            <option value="dispute"> Dispute</option>
                                            <option value="nourriture"> Nourriture</option>
                                            <option value="autre"> Autre</option>
                                        </select>
                                    </div>
                                    
                                    <div class="col-md-3">
                                        <label class="form-label">Montant (FCFA)</label>
                                        <input type="number" name="montant" id="montant_amende" class="form-control" placeholder="Montant">
                                        <small class="text-muted" id="montant_info">Laissez vide pour utiliser le montant par défaut</small>
                                    </div>
                                    
                                    <div class="col-md-2 d-flex align-items-end">
                                        <button type="submit" name="appliquer_amende_manuelle" class="btn btn-warning w-100">
                                            <i class="bi bi-plus-circle"></i> Appliquer
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </div>

                        <!-- Section des amendes -->
                        <?php if(!empty($amendes)): ?>
                            <div class="card mt-4">
                                <div class="card-header" style="background: var(--warning); color: var(--white);">
                                    <h5 class="mb-0"><i class="bi bi-exclamation-triangle"></i> Amendes appliquées</h5>
                                </div>
                                <div class="card-body">
                                    <table class="table table-sm">
                                        <thead>
                                            <tr>
                                                <th>Membre</th>
                                                <th>Type</th>
                                                <th>Montant</th>
                                                <th>Statut</th>
                                                <th>Action</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach($amendes as $a): ?>
                                                <tr>
                                                    <td><?= htmlspecialchars($a['prenom'] . ' ' . $a['nom']) ?></td>
                                                    <td><?= str_replace('_', ' ', $a['type_amende']) ?></td>
                                                    <td><?= number_format($a['montant'], 0, ',', ' ') ?> F</td>
                                                    <td>
                                                        <?php if($a['est_paye'] == 1): ?>
                                                            <span class="badge bg-success">Payé</span>
                                                        <?php else: ?>
                                                            <span class="badge bg-warning">Impayé</span>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td>
                                                        <?php if($a['est_paye'] == 0): ?>
                                                            <a href="payer_amende.php?id=<?= $a['id'] ?>&seance_id=<?= $seance_id ?>" 
                                                               class="btn btn-sm btn-success"
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
                                                <th colspan="3"><?= number_format($total_amendes, 0, ',', ' ') ?> F</th>
                                            </tr>
                                        </tfoot>
                                    </table>
                                </div>
                            </div>
                        <?php endif; ?>

                        <?php if($nb_payes == $nb_total && $nb_total > 0): ?>
                            <div class="alert alert-success mt-3">
                                <strong> Tous les membres ont payé !</strong>
                                <p>Vous pouvez maintenant désigner le bénéficiaire.</p>
                                <a href="designer_beneficiaire.php?seance_id=<?= $seance_id ?>" 
                                   class="btn btn-primary">
                                    Désigner le bénéficiaire
                                </a>
                            </div>
                        <?php endif; ?>

                        <?php if($seance->est_cloturee): ?>
                            <div class="alert alert-info mt-3">
                                Cette séance est clôturée.
                            </div>
                        <?php endif; ?>

                        <!-- Boutons d'action -->
                        <div class="text-center mt-4">
                            <?php if($type_tontine == 'djangui' || $type_tontine == 'anniversaire'): ?>
                                <a href="designer_beneficiaire.php?seance_id=<?= $seance_id ?>" 
                                   class="btn btn-warning">
                                    <i class="bi bi-trophy"></i> Désigner le bénéficiaire
                                </a>
                            <?php endif; ?>
                            <a href="rapport_seance.php?seance_id=<?= $seance_id ?>" class="btn btn-info">
                                <i class="bi bi-file-text"></i> Rapport
                            </a>
                            <a href="gestion_presences.php?seance_id=<?= $seance_id ?>" class="btn btn-info">
                                <i class="bi bi-person-check"></i> Présences
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