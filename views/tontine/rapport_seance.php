<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();

if(!isset($_SESSION['user_id']) || $_SESSION['user_role'] != 'admin') {
    header("Location: ../auth/login.php");
    exit();
}

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../models/Seance.php';
require_once __DIR__ . '/../../models/Tontine.php';
require_once __DIR__ . '/../../models/Cotisation.php';
require_once __DIR__ . '/../../models/AmendeAppliquee.php';
require_once __DIR__ . '/../../models/MembreTontine.php';

$database = new Database();
$db = $database->getConnection();

$seance_id = $_GET['seance_id'] ?? 0;
$cloturee = $_GET['cloturee'] ?? 0;
$error = $_GET['error'] ?? 0;

if(!$seance_id) {
    header("Location: mes_tontines.php");
    exit();
}

$seance = new Seance($db);
if(!$seance->getById($seance_id)) {
    header("Location: mes_tontines.php");
    exit();
}

// Vérifier que la tontine appartient à cet admin
$tontine = new Tontine($db);
$tontine->getById($seance->tontine_id);
if($tontine->admin_id != $_SESSION['user_id']) {
    header("Location: ../auth/login.php");
    exit();
}

$cotisation = new Cotisation($db);
$amendeAppliquee = new AmendeAppliquee($db);

// Récupérer les données
$cotisations = $cotisation->getBySeance($seance_id);
$total_cotisations = $cotisation->calculerTotalSeance($seance_id);
$total_amendes = $amendeAppliquee->calculerTotalSeance($seance_id);

// Compter les statuts
$nb_paye = 0;
$nb_impaye = 0;
$nb_retard = 0;

// Remettre le curseur au début pour compter
$cotisations->execute();
while($c = $cotisations->fetch(PDO::FETCH_ASSOC)) {
    if($c['statut'] == 'paye') $nb_paye++;
    elseif($c['statut'] == 'retard') $nb_retard++;
    else $nb_impaye++;
}

// Récupérer les amendes
$amendes = $amendeAppliquee->getBySeance($seance_id);
$amendes_payees = 0;
$amendes_impayees = 0;

foreach($amendes as $a) {
    if($a['est_paye']) $amendes_payees += $a['montant'];
    else $amendes_impayees += $a['montant'];
}

// Récupérer le bénéficiaire
$beneficiaire_nom = '-';
if($seance->beneficiaire_id) {
    $query = "SELECT u.nom, u.prenom FROM membre_tontine mt 
              JOIN users u ON mt.user_id = u.id 
              WHERE mt.id = :mid";
    $stmt = $db->prepare($query);
    $stmt->execute(['mid' => $seance->beneficiaire_id]);
    $benef = $stmt->fetch(PDO::FETCH_ASSOC);
    $beneficiaire_nom = $benef['prenom'] . ' ' . $benef['nom'];
}

// Sauvegarder les notes
if($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['save_notes'])) {
    $notes = $_POST['notes'] ?? '';
    $seance->saveNotes($seance_id, $notes);
    $message = "Notes enregistrées avec succès !";
}

$notes = $seance->getNotes($seance_id);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Rapport de séance</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.8.1/font/bootstrap-icons.css">
    <style>
        body { background: #f8f9fa; }
        .rapport-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 30px 0;
            margin-bottom: 30px;
        }
        .card {
            border-radius: 15px;
            border: none;
            box-shadow: 0 5px 20px rgba(0,0,0,0.05);
            margin-bottom: 20px;
        }
        .card-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 15px 15px 0 0 !important;
        }
        .table th {
            background: #f8f9fa;
        }
        .badge-paye { background: #28a745; color: white; }
        .badge-impaye { background: #dc3545; color: white; }
        .badge-retard { background: #ffc107; color: black; }
        .total-box {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 20px;
            border-radius: 10px;
            text-align: center;
        }
        .print-btn {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            padding: 12px 30px;
            border-radius: 10px;
            font-size: 16px;
            cursor: pointer;
            transition: all 0.3s;
        }
        .print-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 30px rgba(102, 126, 234, 0.4);
        }
        .action-buttons {
            display: flex;
            gap: 10px;
            justify-content: center;
            margin-top: 20px;
        }
    </style>
</head>
<body>

    <div class="rapport-header">
        <div class="container">
            <div class="d-flex justify-content-between align-items-center">
                <h2><i class="bi bi-file-text"></i> Rapport de séance</h2>
                <a href="mes_tontines.php" class="btn btn-outline-light">
                    <i class="bi bi-arrow-left"></i> Retour
                </a>
            </div>
        </div>
    </div>

    <div class="container mb-5" id="rapport-content">
        
        <?php if(isset($message)): ?>
            <div class="alert alert-success"><?= $message ?></div>
        <?php endif; ?>

        <?php if($cloturee == 1): ?>
            <div class="alert alert-success"> Séance clôturée avec succès !</div>
        <?php endif; ?>

        <?php if($error == 1): ?>
            <div class="alert alert-danger"> Erreur lors de la clôture de la séance.</div>
        <?php endif; ?>

        <!-- En-tête du rapport -->
        <div class="card">
            <div class="card-header">
                <h4 class="mb-0"><i class="bi bi-info-circle"></i> Informations générales</h4>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6">
                        <p><strong>Tontine :</strong> <?= htmlspecialchars($tontine->nom) ?></p>
                        <p><strong>Date de séance :</strong> <?= date('d/m/Y', strtotime($seance->date_seance)) ?></p>
                        <p><strong>Président :</strong> <?= htmlspecialchars($_SESSION['user_nom']) ?></p>
                    </div>
                    <div class="col-md-6">
                        <p><strong>Statut :</strong> 
                            <span class="badge bg-<?= $seance->est_cloturee ? 'success' : 'warning' ?>">
                                <?= $seance->est_cloturee ? 'Clôturée' : 'En cours' ?>
                            </span>
                        </p>
                        <p><strong>Bénéficiaire :</strong> <?= htmlspecialchars($beneficiaire_nom) ?></p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Résumé financier -->
        <div class="row">
            <div class="col-md-4">
                <div class="total-box">
                    <h5>Total cotisations</h5>
                    <h2><?= number_format($total_cotisations, 0, ',', ' ') ?> F</h2>
                </div>
            </div>
            <div class="col-md-4">
                <div class="total-box">
                    <h5>Total amendes</h5>
                    <h2><?= number_format($total_amendes, 0, ',', ' ') ?> F</h2>
                </div>
            </div>
            <div class="col-md-4">
                <div class="total-box">
                    <h5>Total général</h5>
                    <h2><?= number_format($total_cotisations + $total_amendes, 0, ',', ' ') ?> F</h2>
                </div>
            </div>
        </div>

        <!-- Détail des cotisations -->
        <div class="card mt-4">
            <div class="card-header">
                <h5 class="mb-0"><i class="bi bi-cash-stack"></i> Détail des cotisations</h5>
            </div>
            <div class="card-body">
                <div class="row mb-3">
                    <div class="col-md-4">
                        <div class="alert alert-success text-center">
                            <strong>Payés</strong>
                            <h3><?= $nb_paye ?></h3>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="alert alert-danger text-center">
                            <strong>Impayés</strong>
                            <h3><?= $nb_impaye ?></h3>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="alert alert-warning text-center">
                            <strong>Retards</strong>
                            <h3><?= $nb_retard ?></h3>
                        </div>
                    </div>
                </div>

                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>Membre</th>
                            <th>Montant</th>
                            <th>Statut</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        $cotisations->execute();
                        while($c = $cotisations->fetch(PDO::FETCH_ASSOC)): 
                        ?>
                            <tr>
                                <td><?= htmlspecialchars($c['prenom'] . ' ' . $c['nom']) ?></td>
                                <td><?= number_format($c['montant'], 0, ',', ' ') ?> F</td>
                                <td>
                                    <?php if($c['statut'] == 'paye'): ?>
                                        <span class="badge badge-paye">Payé</span>
                                    <?php elseif($c['statut'] == 'retard'): ?>
                                        <span class="badge badge-retard">Retard</span>
                                    <?php else: ?>
                                        <span class="badge badge-impaye">Impayé</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Détail des amendes -->
        <?php if(!empty($amendes)): ?>
        <div class="card mt-4">
            <div class="card-header">
                <h5 class="mb-0"><i class="bi bi-exclamation-triangle"></i> Détail des amendes</h5>
            </div>
            <div class="card-body">
                <div class="row mb-3">
                    <div class="col-md-6">
                        <div class="alert alert-success text-center">
                            <strong>Amendes payées</strong>
                            <h3><?= number_format($amendes_payees, 0, ',', ' ') ?> F</h3>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="alert alert-danger text-center">
                            <strong>Amendes impayées</strong>
                            <h3><?= number_format($amendes_impayees, 0, ',', ' ') ?> F</h3>
                        </div>
                    </div>
                </div>

                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>Membre</th>
                            <th>Type</th>
                            <th>Montant</th>
                            <th>Statut</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($amendes as $a): ?>
                            <tr>
                                <td><?= htmlspecialchars($a['prenom'] . ' ' . $a['nom']) ?></td>
                                <td><?= str_replace('_', ' ', $a['type_amende']) ?></td>
                                <td><?= number_format($a['montant'], 0, ',', ' ') ?> F</td>
                                <td>
                                    <?php if($a['est_paye']): ?>
                                        <span class="badge badge-paye">Payé</span>
                                    <?php else: ?>
                                        <span class="badge badge-impaye">Impayé</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php endif; ?>

        <!-- Notes de séance -->
        <div class="card mt-4">
            <div class="card-header">
                <h5 class="mb-0"><i class="bi bi-pencil-square"></i> Notes de séance</h5>
            </div>
            <div class="card-body">
                <form method="POST">
                    <div class="mb-3">
                        <textarea name="notes" class="form-control" rows="5" 
                                  placeholder="Saisissez vos observations, décisions, etc."><?= htmlspecialchars($notes) ?></textarea>
                    </div>
                    <button type="submit" name="save_notes" class="btn btn-primary">
                        <i class="bi bi-save"></i> Enregistrer les notes
                    </button>
                </form>
            </div>
        </div>

        <!-- Boutons d'action -->
        <div class="action-buttons">
            <?php if(!$seance->est_cloturee): ?>
                <a href="cloturer_seance.php?seance_id=<?= $seance_id ?>" 
                   class="btn btn-success"
                   onclick="return confirm('Une fois clôturée, vous ne pourrez plus modifier les cotisations. Continuer ?')">
                    <i class="bi bi-lock-fill"></i> Clôturer la séance
                </a>
            <?php endif; ?>

            <button class="btn btn-info" onclick="window.print()">
                <i class="bi bi-printer"></i> Imprimer / Exporter en PDF
            </button>
        </div>

    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>