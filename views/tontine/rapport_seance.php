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
require_once __DIR__ . '/../../models/Tontine.php';
require_once __DIR__ . '/../../models/Cotisation.php';
require_once __DIR__ . '/../../models/AmendeAppliquee.php';
require_once __DIR__ . '/../../models/MembreTontine.php';
require_once __DIR__ . '/../../models/Presence.php';

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
$presence = new Presence($db);

// Récupérer les données
$cotisations = $cotisation->getBySeance($seance_id);
$total_cotisations = $cotisation->calculerTotalSeance($seance_id);
$total_amendes = $amendeAppliquee->calculerTotalSeance($seance_id);
$presences = $presence->getBySeance($seance_id);
$nb_presents = $presence->countPresences($seance_id);
$nb_absents = $presence->countAbsences($seance_id);

// Compter les statuts des cotisations
$nb_paye = 0;
$nb_impaye = 0;
$nb_retard = 0;
$total_montant_paye = 0;
$total_montant_impaye = 0;
$total_montant_retard = 0;

// Remettre le curseur au début pour compter
$cotisations->execute();
while($c = $cotisations->fetch(PDO::FETCH_ASSOC)) {
    if($c['statut'] == 'paye') {
        $nb_paye++;
        $total_montant_paye += $c['montant'];
    } elseif($c['statut'] == 'retard') {
        $nb_retard++;
        $total_montant_retard += $c['montant'];
    } else {
        $nb_impaye++;
        $total_montant_impaye += $c['montant'];
    }
}

// Récupérer les amendes
$amendes = $amendeAppliquee->getBySeance($seance_id);
$amendes_payees = 0;
$amendes_impayees = 0;
$total_amendes_payees = 0;
$total_amendes_impayees = 0;

foreach($amendes as $a) {
    if($a['est_paye']) {
        $amendes_payees++;
        $total_amendes_payees += $a['montant'];
    } else {
        $amendes_impayees++;
        $total_amendes_impayees += $a['montant'];
    }
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
    <title>Rapport de séance - <?= htmlspecialchars($tontine->nom) ?></title>
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
            color: var(--text-dark);
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        .rapport-header {
            background: var(--primary);
            color: var(--white);
            padding: 30px 0;
            margin-bottom: 30px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
        }
        
        .rapport-header h2 {
            font-weight: 700;
        }
        
        .card {
            border-radius: 15px;
            border: 1px solid var(--border);
            box-shadow: 0 2px 15px rgba(0,0,0,0.05);
            margin-bottom: 25px;
            transition: transform 0.2s, box-shadow 0.2s;
        }
        
        .card:hover {
            box-shadow: 0 5px 25px rgba(30, 58, 138, 0.1);
        }
        
        .card-header {
            background: var(--primary);
            color: var(--white);
            border-radius: 15px 15px 0 0 !important;
            font-weight: 600;
            padding: 15px 20px;
        }
        
        .card-header.bg-info {
            background: var(--info) !important;
        }
        
        .card-header.bg-success {
            background: var(--success) !important;
        }
        
        .card-header i {
            margin-right: 8px;
        }
        
        .card-body {
            padding: 20px;
        }
        
        .total-box {
            background: linear-gradient(135deg, var(--primary) 0%, var(--primary-light) 100%);
            color: var(--white);
            padding: 25px;
            border-radius: 15px;
            text-align: center;
            box-shadow: 0 5px 20px rgba(30, 58, 138, 0.2);
            height: 100%;
        }
        
        .total-box h5 {
            font-size: 16px;
            text-transform: uppercase;
            letter-spacing: 1px;
            margin-bottom: 10px;
            opacity: 0.9;
        }
        
        .total-box h2 {
            font-size: 32px;
            font-weight: 700;
            margin: 0;
        }
        
        .stat-card {
            background: var(--white);
            border-radius: 12px;
            padding: 20px;
            text-align: center;
            border: 1px solid var(--border);
            height: 100%;
        }
        
        .stat-value {
            font-size: 28px;
            font-weight: 700;
            margin: 5px 0;
        }
        
        .stat-label {
            color: var(--text-light);
            font-size: 14px;
        }
        
        .stat-card.success { border-top: 4px solid var(--success); }
        .stat-card.warning { border-top: 4px solid var(--warning); }
        .stat-card.danger { border-top: 4px solid var(--danger); }
        .stat-card.info { border-top: 4px solid var(--info); }
        
        .table {
            margin-bottom: 0;
        }
        
        .table th {
            background: var(--primary);
            color: var(--white);
            font-weight: 600;
            border: none;
            padding: 12px 10px;
        }
        
        .table td {
            padding: 12px 10px;
            vertical-align: middle;
            border-bottom: 1px solid var(--border);
        }
        
        .table tbody tr:hover td {
            background-color: var(--bg-light);
        }
        
        .badge {
            padding: 6px 12px;
            border-radius: 50px;
            font-weight: 500;
            font-size: 12px;
        }
        
        .badge-paye { 
            background: var(--success-bg); 
            color: #065F46;
            border: 1px solid var(--success);
        }
        
        .badge-impaye { 
            background: var(--danger-bg); 
            color: #991B1B;
            border: 1px solid var(--danger);
        }
        
        .badge-retard { 
            background: var(--warning-bg); 
            color: #92400E;
            border: 1px solid var(--warning);
        }
        
        .badge-present {
            background: var(--success-bg);
            color: #065F46;
            padding: 4px 10px;
            border-radius: 50px;
        }
        
        .badge-absent {
            background: var(--danger-bg);
            color: #991B1B;
            padding: 4px 10px;
            border-radius: 50px;
        }
        
        .info-row {
            display: flex;
            margin-bottom: 12px;
            border-bottom: 1px dashed var(--border);
            padding-bottom: 8px;
        }
        
        .info-label {
            font-weight: 600;
            min-width: 150px;
            color: var(--text-light);
        }
        
        .info-value {
            font-weight: 500;
        }
        
        .action-buttons {
            display: flex;
            gap: 15px;
            justify-content: center;
            margin: 30px 0;
            flex-wrap: wrap;
        }
        
        .btn {
            padding: 12px 30px;
            border-radius: 10px;
            font-weight: 600;
            transition: all 0.3s;
        }
        
        .btn-primary {
            background: var(--primary);
            border: none;
        }
        
        .btn-primary:hover {
            background: var(--primary-light);
            transform: translateY(-2px);
            box-shadow: 0 10px 30px rgba(30, 58, 138, 0.3);
        }
        
        .btn-success {
            background: var(--success);
            border: none;
        }
        
        .btn-success:hover {
            background: #0E9F6E;
            transform: translateY(-2px);
            box-shadow: 0 10px 30px rgba(16, 185, 129, 0.3);
        }
        
        .btn-info {
            background: var(--info);
            border: none;
            color: white;
        }
        
        .btn-info:hover {
            background: #2563EB;
            transform: translateY(-2px);
            box-shadow: 0 10px 30px rgba(59, 130, 246, 0.3);
        }
        
        .btn-outline-light {
            border: 2px solid var(--white);
            color: var(--white);
        }
        
        .btn-outline-light:hover {
            background: var(--white);
            color: var(--primary);
        }
        
        .alert {
            border-radius: 12px;
            border: none;
            padding: 15px 20px;
        }
        
        .alert-success {
            background: var(--success-bg);
            color: #065F46;
        }
        
        .alert-danger {
            background: var(--danger-bg);
            color: #991B1B;
        }
        
        .alert-info {
            background: var(--info-bg);
            color: var(--primary);
        }
        
        .summary-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 15px;
            margin-bottom: 20px;
        }
        
        @media print {
            .btn, .action-buttons, .navbar, .rapport-header .btn {
                display: none !important;
            }
            body { background: white; }
            .card { box-shadow: none; border: 1px solid #ddd; }
        }
    </style>
</head>
<body>

    <div class="rapport-header">
        <div class="container">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h2 class="mb-2"><i class="bi bi-file-text"></i> Rapport de séance</h2>
                    <p class="mb-0">
                        <i class="bi bi-calendar"></i> <?= date('d/m/Y', strtotime($seance->date_seance)) ?> - 
                        <i class="bi bi-bank2"></i> <?= htmlspecialchars($tontine->nom) ?>
                    </p>
                </div>
                <a href="mes_tontines.php" class="btn btn-outline-light">
                    <i class="bi bi-arrow-left"></i> Retour
                </a>
            </div>
        </div>
    </div>

    <div class="container mb-5" id="rapport-content">
        
        <?php if(isset($message)): ?>
            <div class="alert alert-success">
                <i class="bi bi-check-circle-fill me-2"></i> <?= $message ?>
            </div>
        <?php endif; ?>

        <?php if($cloturee == 1): ?>
            <div class="alert alert-success">
                <i class="bi bi-check-circle-fill me-2"></i> Séance clôturée avec succès !
            </div>
        <?php endif; ?>

        <?php if($error == 1): ?>
            <div class="alert alert-danger">
                <i class="bi bi-exclamation-triangle-fill me-2"></i> Erreur lors de la clôture de la séance.
            </div>
        <?php endif; ?>

        <!-- En-tête du rapport -->
        <div class="card">
            <div class="card-header">
                <i class="bi bi-info-circle"></i> Informations générales
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6">
                        <div class="info-row">
                            <span class="info-label"><i class="bi bi-tag"></i> Tontine :</span>
                            <span class="info-value"><?= htmlspecialchars($tontine->nom) ?></span>
                        </div>
                        <div class="info-row">
                            <span class="info-label"><i class="bi bi-calendar"></i> Date :</span>
                            <span class="info-value"><?= date('d/m/Y', strtotime($seance->date_seance)) ?></span>
                        </div>
                        <div class="info-row">
                            <span class="info-label"><i class="bi bi-person-badge"></i> Président :</span>
                            <span class="info-value"><?= htmlspecialchars($_SESSION['user_nom']) ?></span>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="info-row">
                            <span class="info-label"><i class="bi bi-check-circle"></i> Statut :</span>
                            <span class="info-value">
                                <span class="badge bg-<?= $seance->est_cloturee ? 'success' : 'warning' ?>">
                                    <?= $seance->est_cloturee ? 'Clôturée' : 'En cours' ?>
                                </span>
                            </span>
                        </div>
                        <div class="info-row">
                            <span class="info-label"><i class="bi bi-trophy"></i> Bénéficiaire :</span>
                            <span class="info-value"><?= htmlspecialchars($beneficiaire_nom) ?></span>
                        </div>
                        <div class="info-row">
                            <span class="info-label"><i class="bi bi-people"></i> Membres présents :</span>
                            <span class="info-value"><?= $nb_presents ?> / <?= $nb_presents + $nb_absents ?></span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Résumé financier -->
        <div class="row mb-4">
            <div class="col-md-4 mb-3">
                <div class="total-box">
                    <h5><i class="bi bi-cash-stack"></i> Cotisations</h5>
                    <h2><?= number_format($total_cotisations, 0, ',', ' ') ?> F</h2>
                </div>
            </div>
            <div class="col-md-4 mb-3">
                <div class="total-box">
                    <h5><i class="bi bi-exclamation-triangle"></i> Amendes</h5>
                    <h2><?= number_format($total_amendes, 0, ',', ' ') ?> F</h2>
                </div>
            </div>
            <div class="col-md-4 mb-3">
                <div class="total-box">
                    <h5><i class="bi bi-piggy-bank"></i> Total collecté</h5>
                    <h2><?= number_format($total_cotisations + $total_amendes, 0, ',', ' ') ?> F</h2>
                </div>
            </div>
        </div>

        <!-- Détail des cotisations -->
        <div class="card">
            <div class="card-header">
                <i class="bi bi-cash-stack"></i> Détail des cotisations
            </div>
            <div class="card-body">
                <div class="summary-grid mb-4">
                    <div class="stat-card success">
                        <i class="bi bi-check-circle-fill" style="color: var(--success); font-size: 24px;"></i>
                        <div class="stat-value"><?= $nb_paye ?></div>
                        <div class="stat-label">Payés</div>
                        <small><?= number_format($total_montant_paye, 0, ',', ' ') ?> F</small>
                    </div>
                    <div class="stat-card warning">
                        <i class="bi bi-exclamation-triangle-fill" style="color: var(--warning); font-size: 24px;"></i>
                        <div class="stat-value"><?= $nb_retard ?></div>
                        <div class="stat-label">Retards</div>
                        <small><?= number_format($total_montant_retard, 0, ',', ' ') ?> F</small>
                    </div>
                    <div class="stat-card danger">
                        <i class="bi bi-x-circle-fill" style="color: var(--danger); font-size: 24px;"></i>
                        <div class="stat-value"><?= $nb_impaye ?></div>
                        <div class="stat-label">Impayés</div>
                        <small><?= number_format($total_montant_impaye, 0, ',', ' ') ?> F</small>
                    </div>
                </div>

                <div class="table-responsive">
                    <table class="table">
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
                                    <td><i class="bi bi-person-circle me-2" style="color: var(--primary);"></i><?= htmlspecialchars($c['prenom'] . ' ' . $c['nom']) ?></td>
                                    <td><strong><?= number_format($c['montant'], 0, ',', ' ') ?> F</strong></td>
                                    <td>
                                        <?php if($c['statut'] == 'paye'): ?>
                                            <span class="badge badge-paye"><i class="bi bi-check-circle me-1"></i>Payé</span>
                                        <?php elseif($c['statut'] == 'retard'): ?>
                                            <span class="badge badge-retard"><i class="bi bi-clock me-1"></i>Retard</span>
                                        <?php else: ?>
                                            <span class="badge badge-impaye"><i class="bi bi-x-circle me-1"></i>Impayé</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Détail des amendes -->
        <?php if(!empty($amendes)): ?>
        <div class="card mt-4">
            <div class="card-header">
                <i class="bi bi-exclamation-triangle"></i> Détail des amendes
            </div>
            <div class="card-body">
                <div class="summary-grid mb-4">
                    <div class="stat-card success">
                        <i class="bi bi-check-circle-fill" style="color: var(--success); font-size: 24px;"></i>
                        <div class="stat-value"><?= $amendes_payees ?></div>
                        <div class="stat-label">Amendes payées</div>
                        <small><?= number_format($total_amendes_payees, 0, ',', ' ') ?> F</small>
                    </div>
                    <div class="stat-card danger">
                        <i class="bi bi-x-circle-fill" style="color: var(--danger); font-size: 24px;"></i>
                        <div class="stat-value"><?= $amendes_impayees ?></div>
                        <div class="stat-label">Amendes impayées</div>
                        <small><?= number_format($total_amendes_impayees, 0, ',', ' ') ?> F</small>
                    </div>
                    <div class="stat-card info">
                        <i class="bi bi-calculator-fill" style="color: var(--info); font-size: 24px;"></i>
                        <div class="stat-value"><?= $amendes_payees + $amendes_impayees ?></div>
                        <div class="stat-label">Total amendes</div>
                        <small><?= number_format($total_amendes_payees + $total_amendes_impayees, 0, ',', ' ') ?> F</small>
                    </div>
                </div>

                <div class="table-responsive">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Membre</th>
                                <th>Type d'amende</th>
                                <th>Montant</th>
                                <th>Statut</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach($amendes as $a): ?>
                                <tr>
                                    <td><i class="bi bi-person-circle me-2" style="color: var(--primary);"></i><?= htmlspecialchars($a['prenom'] . ' ' . $a['nom']) ?></td>
                                    <td><?= ucfirst(str_replace('_', ' ', $a['type_amende'])) ?></td>
                                    <td><strong><?= number_format($a['montant'], 0, ',', ' ') ?> F</strong></td>
                                    <td>
                                        <?php if($a['est_paye']): ?>
                                            <span class="badge badge-paye"><i class="bi bi-check-circle me-1"></i>Payé</span>
                                        <?php else: ?>
                                            <span class="badge badge-impaye"><i class="bi bi-x-circle me-1"></i>Impayé</span>
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

        <!-- Section des présences -->
        <div class="card mt-4">
            <div class="card-header bg-info">
                <i class="bi bi-person-check"></i> Présences
            </div>
            <div class="card-body">
                <div class="summary-grid mb-4">
                    <div class="stat-card success">
                        <i class="bi bi-person-check-fill" style="color: var(--success); font-size: 24px;"></i>
                        <div class="stat-value"><?= $nb_presents ?></div>
                        <div class="stat-label">Présents</div>
                        <small><?= round(($nb_presents / max(1, $nb_presents + $nb_absents)) * 100, 1) ?>%</small>
                    </div>
                    <div class="stat-card danger">
                        <i class="bi bi-person-x-fill" style="color: var(--danger); font-size: 24px;"></i>
                        <div class="stat-value"><?= $nb_absents ?></div>
                        <div class="stat-label">Absents</div>
                        <small><?= round(($nb_absents / max(1, $nb_presents + $nb_absents)) * 100, 1) ?>%</small>
                    </div>
                    <div class="stat-card info">
                        <i class="bi bi-people-fill" style="color: var(--info); font-size: 24px;"></i>
                        <div class="stat-value"><?= $nb_presents + $nb_absents ?></div>
                        <div class="stat-label">Total membres</div>
                    </div>
                </div>

                <div class="table-responsive">
                    <table class="table table-sm">
                        <thead>
                            <tr>
                                <th>Ordre</th>
                                <th>Membre</th>
                                <th>Statut</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach($presences as $p): ?>
                                <tr>
                                    <td><strong>#<?= $p['ordre_tour'] ?></strong></td>
                                    <td><i class="bi bi-person-circle me-2" style="color: var(--primary);"></i><?= htmlspecialchars($p['prenom'] . ' ' . $p['nom']) ?></td>
                                    <td>
                                        <?php if($p['est_present']): ?>
                                            <span class="badge-present"><i class="bi bi-check-circle me-1"></i>Présent</span>
                                        <?php else: ?>
                                            <span class="badge-absent"><i class="bi bi-x-circle me-1"></i>Absent</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Notes de séance -->
        <div class="card mt-4">
            <div class="card-header">
                <i class="bi bi-pencil-square"></i> Notes de séance
            </div>
            <div class="card-body">
                <form method="POST">
                    <div class="mb-3">
                        <textarea name="notes" class="form-control" rows="5" 
                                  placeholder="Saisissez vos observations, décisions, incidents éventuels..."><?= htmlspecialchars($notes) ?></textarea>
                    </div>
                    <button type="submit" name="save_notes" class="btn btn-primary">
                        <i class="bi bi-save"></i> Enregistrer les notes
                    </button>
                </form>
                <?php if(!empty($notes)): ?>
                    <div class="mt-3 p-3 bg-light rounded">
                        <strong>Dernières notes :</strong>
                        <p class="mb-0 mt-2"><?= nl2br(htmlspecialchars($notes)) ?></p>
                    </div>
                <?php endif; ?>
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
                <i class="bi bi-printer"></i> Imprimer / PDF
            </button>

            <a href="gerer_cotisations.php?seance_id=<?= $seance_id ?>" class="btn btn-primary">
                <i class="bi bi-pencil"></i> Modifier les cotisations
            </a>
        </div>

    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>