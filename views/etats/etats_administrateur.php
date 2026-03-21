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
require_once __DIR__ . '/../../models/Cotisation.php';
require_once __DIR__ . '/../../models/Seance.php';
require_once __DIR__ . '/../../models/AmendeAppliquee.php';

$database = new Database();
$db = $database->getConnection();

$tontine_id = $_GET['tontine_id'] ?? 0;

if(!$tontine_id) {
    header("Location: ../tontine/mes_tontines.php");
    exit();
}

$tontine = new Tontine($db);
if(!$tontine->getById($tontine_id) || $tontine->admin_id != $_SESSION['user_id']) {
    header("Location: ../tontine/mes_tontines.php");
    exit();
}

// Déterminer le type de tontine
$type_tontine = $tontine->type_tontine;

$membreTontine = new MembreTontine($db);
$cotisation = new Cotisation($db);
$amendeAppliquee = new AmendeAppliquee($db);

// ============================================
// 1. ÉTAT GÉNÉRAL DE LA TONTINE
// ============================================

$total_membres = $membreTontine->countMembres($tontine_id);

// Calculer le nombre total de séances
$query = "SELECT COUNT(*) as total FROM seances WHERE tontine_id = :tid";
$stmt = $db->prepare($query);
$stmt->execute(['tid' => $tontine_id]);
$total_seances = $stmt->fetch()['total'] ?? 0;

// Total collecté (cotisations payées)
$query = "SELECT SUM(montant) as total FROM cotisations c
          JOIN seances s ON c.seance_id = s.id
          WHERE s.tontine_id = :tid AND c.statut = 'paye'";
$stmt = $db->prepare($query);
$stmt->execute(['tid' => $tontine_id]);
$total_collecte = $stmt->fetch()['total'] ?? 0;

// Total distribué aux bénéficiaires (UNIQUEMENT pour Djangui et Anniversaire)
$total_distribue = 0;
if($type_tontine == 'djangui' || $type_tontine == 'anniversaire') {
    $query = "SELECT SUM(total_collecte) as total FROM seances 
              WHERE tontine_id = :tid AND est_cloturee = 1";
    $stmt = $db->prepare($query);
    $stmt->execute(['tid' => $tontine_id]);
    $total_distribue = $stmt->fetch()['total'] ?? 0;
}

// Total des amendes perçues
$query = "SELECT SUM(a.montant) as total FROM amendes_appliquees a
          JOIN seances s ON a.seance_id = s.id
          WHERE s.tontine_id = :tid AND a.est_paye = 1";
$stmt = $db->prepare($query);
$stmt->execute(['tid' => $tontine_id]);
$total_amendes_percues = $stmt->fetch()['total'] ?? 0;

// Montant qui reste en caisse
$solde_caisse = $total_collecte - $total_distribue;

// ============================================
// 2. ÉTAT DES MEMBRES
// ============================================

// Membres à jour (sans aucun retard)
$query = "SELECT COUNT(DISTINCT mt.id) as total FROM membre_tontine mt
          WHERE mt.tontine_id = :tid AND mt.est_actif = 1
          AND NOT EXISTS (
              SELECT 1 FROM cotisations c
              JOIN seances s ON c.seance_id = s.id
              WHERE c.membre_tontine_id = mt.id AND c.statut = 'retard'
          )";
$stmt = $db->prepare($query);
$stmt->execute(['tid' => $tontine_id]);
$membres_a_jour = $stmt->fetch()['total'];

// Membres en retard (au moins un retard)
$query = "SELECT COUNT(DISTINCT mt.id) as total FROM membre_tontine mt
          WHERE mt.tontine_id = :tid AND mt.est_actif = 1
          AND EXISTS (
              SELECT 1 FROM cotisations c
              JOIN seances s ON c.seance_id = s.id
              WHERE c.membre_tontine_id = mt.id AND c.statut = 'retard'
          )";
$stmt = $db->prepare($query);
$stmt->execute(['tid' => $tontine_id]);
$membres_en_retard = $stmt->fetch()['total'];

// Membres exclus/désactivés
$query = "SELECT COUNT(*) as total FROM membre_tontine 
          WHERE tontine_id = :tid AND est_actif = 0";
$stmt = $db->prepare($query);
$stmt->execute(['tid' => $tontine_id]);
$membres_exclus = $stmt->fetch()['total'];

// Total des amendes impayées
$query = "SELECT SUM(a.montant) as total FROM amendes_appliquees a
          JOIN seances s ON a.seance_id = s.id
          WHERE s.tontine_id = :tid AND a.est_paye = 0";
$stmt = $db->prepare($query);
$stmt->execute(['tid' => $tontine_id]);
$amendes_impayees = $stmt->fetch()['total'] ?? 0;

// Liste des membres avec leurs pénalités
$query = "SELECT u.nom, u.prenom, 
                 COUNT(a.id) as nb_amendes, 
                 SUM(CASE WHEN a.est_paye = 1 THEN a.montant ELSE 0 END) as amendes_payees,
                 SUM(CASE WHEN a.est_paye = 0 THEN a.montant ELSE 0 END) as amendes_impayees
          FROM membre_tontine mt
          JOIN users u ON mt.user_id = u.id
          LEFT JOIN amendes_appliquees a ON a.membre_tontine_id = mt.id
          WHERE mt.tontine_id = :tid
          GROUP BY mt.id
          ORDER BY amendes_impayees DESC, nb_amendes DESC";
$stmt = $db->prepare($query);
$stmt->execute(['tid' => $tontine_id]);
$penalites_membres = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ============================================
// 3. ÉTAT PAR SÉANCE
// ============================================

$query = "SELECT s.*, 
                 CONCAT(u.prenom, ' ', u.nom) as beneficiaire_nom,
                 (SELECT COUNT(*) FROM membre_tontine WHERE tontine_id = :tid AND est_actif = 1) as nb_membres,
                 (SELECT COUNT(*) FROM cotisations WHERE seance_id = s.id AND statut = 'paye') as nb_presents,
                 (SELECT COUNT(*) FROM cotisations WHERE seance_id = s.id AND statut = 'retard') as nb_retards,
                 (SELECT SUM(montant) FROM cotisations WHERE seance_id = s.id AND statut = 'paye') as total_encaisse
          FROM seances s
          LEFT JOIN membre_tontine mt ON s.beneficiaire_id = mt.id
          LEFT JOIN users u ON mt.user_id = u.id
          WHERE s.tontine_id = :tid
          ORDER BY s.date_seance DESC";
$stmt = $db->prepare($query);
$stmt->execute(['tid' => $tontine_id]);
$seances = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ============================================
// 4. ÉTAT DES RETARDS
// ============================================

$query = "SELECT u.nom, u.prenom, 
                 COUNT(c.id) as nb_cotisations,
                 SUM(CASE WHEN c.statut = 'paye' THEN 1 ELSE 0 END) as nb_paiements,
                 SUM(CASE WHEN c.statut = 'retard' THEN 1 ELSE 0 END) as nb_retards,
                 ROUND((SUM(CASE WHEN c.statut = 'paye' THEN 1 ELSE 0 END) / COUNT(c.id)) * 100, 1) as taux_paiement
          FROM membre_tontine mt
          JOIN users u ON mt.user_id = u.id
          LEFT JOIN cotisations c ON c.membre_tontine_id = mt.id
          WHERE mt.tontine_id = :tid
          GROUP BY mt.id
          ORDER BY nb_retards DESC, taux_paiement ASC";
$stmt = $db->prepare($query);
$stmt->execute(['tid' => $tontine_id]);
$classement_membres = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Moyenne des retards
$total_membres_actifs = $membres_a_jour + $membres_en_retard;
$pourcentage_retard = $total_membres_actifs > 0 ? round(($membres_en_retard / $total_membres_actifs) * 100, 1) : 0;

// ============================================
// 5. LISTE DES MEMBRES POUR LE FILTRE
// ============================================
$query_membres = "SELECT mt.id, u.nom, u.prenom 
                  FROM membre_tontine mt
                  JOIN users u ON mt.user_id = u.id
                  WHERE mt.tontine_id = :tid AND mt.est_actif = 1
                  ORDER BY u.nom, u.prenom";
$stmt_membres = $db->prepare($query_membres);
$stmt_membres->execute(['tid' => $tontine_id]);
$membres_liste = $stmt_membres->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>États - <?= htmlspecialchars($tontine->nom) ?></title>
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
        
        * { margin: 0; padding: 0; box-sizing: border-box; }
        
        body {
            background: var(--bg-light);
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            color: var(--text-dark);
        }
        
        .navbar {
            background: var(--primary);
            padding: 15px 0;
            color: var(--white);
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        .navbar .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .navbar a {
            color: var(--white);
            text-decoration: none;
            padding: 5px 10px;
            border-radius: 5px;
        }
        
        .navbar a:hover {
            background: rgba(255,255,255,0.1);
        }
        
        .container {
            max-width: 1200px;
            margin: 30px auto;
            padding: 0 20px;
        }
        
        .page-header {
            margin-bottom: 30px;
        }
        
        .page-header h2 {
            color: var(--text-dark);
            font-size: 28px;
            margin-bottom: 5px;
        }
        
        .page-header p {
            color: var(--text-light);
            font-size: 16px;
        }
        
        .type-badge {
            display: inline-block;
            padding: 5px 15px;
            border-radius: 50px;
            font-size: 13px;
            font-weight: 600;
            margin-left: 10px;
        }
        
        .badge-djangui { background: var(--info-bg); color: var(--primary); }
        .badge-anniversaire { background: var(--warning-bg); color: #92400E; }
        .badge-solidarite { background: var(--success-bg); color: #065F46; }
        .badge-pret { background: #E0E7FF; color: #3730A3; }
        
        .tabs {
            display: flex;
            gap: 5px;
            margin-bottom: 25px;
            border-bottom: 2px solid var(--border);
            flex-wrap: wrap;
        }
        
        .tab {
            padding: 12px 24px;
            background: none;
            border: none;
            color: var(--text-light);
            font-weight: 600;
            cursor: pointer;
            font-size: 15px;
            border-radius: 8px 8px 0 0;
            transition: all 0.2s;
        }
        
        .tab:hover {
            color: var(--primary);
            background: var(--info-bg);
        }
        
        .tab.active {
            background: var(--primary);
            color: var(--white);
        }
        
        .tab-content { display: none; }
        .tab-content.active { display: block; }
        
        .card {
            background: var(--white);
            border-radius: 12px;
            box-shadow: 0 2px 15px rgba(0,0,0,0.08);
            margin-bottom: 25px;
            border: 1px solid var(--border);
            overflow: hidden;
        }
        
        .card-header {
            background: var(--primary);
            color: var(--white);
            padding: 15px 20px;
            font-weight: 600;
            font-size: 16px;
        }
        
        .card-body { padding: 25px; }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .stat-card {
            background: var(--bg-light);
            padding: 20px;
            border-radius: 10px;
            border: 1px solid var(--border);
        }
        
        .stat-label {
            color: var(--text-light);
            font-size: 14px;
            margin-bottom: 8px;
        }
        
        .stat-value {
            font-size: 28px;
            font-weight: 700;
            color: var(--text-dark);
        }
        
        .stat-value.positive { color: var(--success); }
        .stat-value.warning { color: var(--warning); }
        .stat-value.danger { color: var(--danger); }
        
        .stat-detail {
            font-size: 13px;
            color: var(--text-light);
            margin-top: 5px;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
            font-size: 14px;
        }
        
        th {
            background: var(--bg-light);
            color: var(--text-dark);
            padding: 12px 10px;
            text-align: left;
            font-weight: 600;
            border-bottom: 2px solid var(--border);
        }
        
        td {
            padding: 12px 10px;
            border-bottom: 1px solid var(--border);
        }
        
        tr:hover td {
            background-color: var(--bg-light);
        }
        
        .badge {
            display: inline-block;
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 12px;
            font-weight: 500;
        }
        
        .badge.success { background: var(--success-bg); color: #065F46; }
        .badge.warning { background: var(--warning-bg); color: #92400E; }
        .badge.danger { background: var(--danger-bg); color: #991B1B; }
        .badge.info { background: var(--info-bg); color: var(--primary); }
        
        .btn {
            display: inline-block;
            padding: 10px 20px;
            background: var(--primary);
            color: var(--white);
            text-decoration: none;
            border-radius: 6px;
            border: none;
            cursor: pointer;
            font-size: 14px;
            font-weight: 500;
            transition: background 0.2s;
        }
        
        .btn:hover {
            background: var(--primary-light);
        }
        
        .btn-outline {
            background: transparent;
            border: 2px solid var(--primary);
            color: var(--primary);
        }
        
        .btn-outline:hover {
            background: var(--primary);
            color: var(--white);
        }
        
        .text-success { color: var(--success); }
        .text-warning { color: var(--warning); }
        .text-danger { color: var(--danger); }
        
        .mt-4 { margin-top: 20px; }
        .mt-5 { margin-top: 30px; }
        .mb-3 { margin-bottom: 15px; }
        .text-center { text-align: center; }
        
        .progress-bar {
            width: 100%;
            height: 8px;
            background: var(--border);
            border-radius: 4px;
            overflow: hidden;
            margin: 8px 0;
        }
        
        .progress-fill {
            height: 100%;
            background: var(--primary);
            border-radius: 4px;
        }
        
        .info-message {
            background: var(--info-bg);
            color: var(--primary);
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            border-left: 4px solid var(--primary);
        }
        
        .summary-box {
            background: var(--white);
            border: 1px solid var(--border);
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 20px;
        }
        
        .summary-row {
            display: flex;
            justify-content: space-between;
            padding: 8px 0;
            border-bottom: 1px solid var(--border);
        }
        
        .summary-row:last-child {
            border-bottom: none;
        }
        
        .caisse-info {
            background: var(--info-bg);
            color: var(--primary);
            border-left: 4px solid var(--info);
            padding: 15px;
            border-radius: 10px;
            margin: 15px 0;
        }
    </style>
</head>
<body>
    <div class="navbar">
        <div class="container">
            <span style="font-size: 24px; font-weight: 700;">TONTONTINE</span>
            <div style="display: flex; gap: 20px; align-items: center;">
                <span><?= htmlspecialchars($_SESSION['association_nom']) ?></span>
                <a href="../tontine/mes_tontines.php">← Retour aux tontines</a>
            </div>
        </div>
    </div>

    <div class="container">
        <div class="page-header">
            <h2>États de la tontine
                <?php if($type_tontine == 'djangui'): ?>
                    <span class="type-badge badge-djangui">Djangui</span>
                <?php elseif($type_tontine == 'anniversaire'): ?>
                    <span class="type-badge badge-anniversaire">Anniversaire</span>
                <?php elseif($type_tontine == 'solidarite'): ?>
                    <span class="type-badge badge-solidarite">Solidarité</span>
                <?php elseif($type_tontine == 'pret'): ?>
                    <span class="type-badge badge-pret">Prêt</span>
                <?php endif; ?>
            </h2>
            <p><?= htmlspecialchars($tontine->nom) ?> - <?= ucfirst($tontine->type_tontine) ?></p>
        </div>

        <div class="tabs">
            <button class="tab active" onclick="showTab('general', this)"> Vue d'ensemble</button>
            <button class="tab" onclick="showTab('membres', this)"> Situation des membres</button>
            <button class="tab" onclick="showTab('seances', this)"> Historique des séances</button>
            <?php if($type_tontine == 'djangui' || $type_tontine == 'anniversaire'): ?>
                <button class="tab" onclick="showTab('retards', this)">⏱ Suivi des retards</button>
            <?php endif; ?>
        </div>

        <!-- ========== TAB 1 : VUE D'ENSEMBLE ========== -->
        <div id="general" class="tab-content active">
            <div class="card">
                <div class="card-header">Résumé financier</div>
                <div class="card-body">
                    <div class="stats-grid">
                        <div class="stat-card">
                            <div class="stat-label">Membres actifs</div>
                            <div class="stat-value"><?= $total_membres ?></div>
                            <div class="stat-detail"><?= $total_seances ?> séance(s) tenue(s)</div>
                        </div>
                        <div class="stat-card">
                            <div class="stat-label">Total collecté</div>
                            <div class="stat-value positive"><?= number_format($total_collecte, 0, ',', ' ') ?> F</div>
                            <div class="stat-detail">Somme de toutes les cotisations</div>
                        </div>
                        <?php if($type_tontine == 'djangui' || $type_tontine == 'anniversaire'): ?>
                            <div class="stat-card">
                                <div class="stat-label">Total distribué</div>
                                <div class="stat-value"><?= number_format($total_distribue, 0, ',', ' ') ?> F</div>
                                <div class="stat-detail">Versé aux bénéficiaires</div>
                            </div>
                            <div class="stat-card">
                                <div class="stat-label">Solde en caisse</div>
                                <div class="stat-value <?= $solde_caisse >= 0 ? 'positive' : 'danger' ?>">
                                    <?= number_format($solde_caisse, 0, ',', ' ') ?> F
                                </div>
                                <div class="stat-detail">Collecté - Distribué</div>
                            </div>
                        <?php endif; ?>
                    </div>

                    <div class="summary-box">
                        <h4 style="margin-bottom: 15px; color: var(--primary);">Détail des amendes</h4>
                        <div class="summary-row">
                            <span>Amendes perçues :</span>
                            <strong class="text-success"><?= number_format($total_amendes_percues, 0, ',', ' ') ?> F</strong>
                        </div>
                        <div class="summary-row">
                            <span>Amendes impayées :</span>
                            <strong class="text-danger"><?= number_format($amendes_impayees, 0, ',', ' ') ?> F</strong>
                        </div>
                        <div class="summary-row">
                            <span>Total des amendes :</span>
                            <strong><?= number_format($total_amendes_percues + $amendes_impayees, 0, ',', ' ') ?> F</strong>
                        </div>
                    </div>

                    <div class="info-message">
                        <strong>À savoir :</strong> Le montant des cotisations est de 
                        <strong><?= number_format($tontine->montant_cotisation, 0, ',', ' ') ?> F</strong> par membre et par séance.
                        Pour <?= $total_membres ?> membre(s), le montant attendu par séance est de 
                        <strong><?= number_format($tontine->montant_cotisation * $total_membres, 0, ',', ' ') ?> F</strong>.
                    </div>
                </div>
            </div>
        </div>

        <!-- ========== TAB 2 : SITUATION DES MEMBRES ========== -->
        <div id="membres" class="tab-content">
            <div class="card">
                <div class="card-header">Situation des membres</div>
                <div class="card-body">
                    <div class="stats-grid">
                        <div class="stat-card">
                            <div class="stat-label">Membres à jour</div>
                            <div class="stat-value positive"><?= $membres_a_jour ?></div>
                            <div class="stat-detail">Aucun retard de paiement</div>
                        </div>
                        <div class="stat-card">
                            <div class="stat-label">Membres en retard</div>
                            <div class="stat-value warning"><?= $membres_en_retard ?></div>
                            <div class="stat-detail">Au moins un retard</div>
                        </div>
                        <div class="stat-card">
                            <div class="stat-label">Membres exclus</div>
                            <div class="stat-value danger"><?= $membres_exclus ?></div>
                            <div class="stat-detail">Désactivés de la tontine</div>
                        </div>
                    </div>

                    <?php if($total_membres_actifs > 0): ?>
                    <div class="summary-box">
                        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px;">
                            <span><strong>Taux de retard :</strong></span>
                            <span class="<?= $pourcentage_retard > 30 ? 'text-danger' : ($pourcentage_retard > 10 ? 'text-warning' : 'text-success') ?>">
                                <?= $pourcentage_retard ?>% des membres sont en retard
                            </span>
                        </div>
                        <div class="progress-bar">
                            <div class="progress-fill" style="width: <?= $pourcentage_retard ?>%;"></div>
                        </div>
                    </div>
                    <?php endif; ?>

                    <h4 style="margin: 25px 0 15px; color: var(--primary);">Détail des pénalités par membre</h4>
                    
                    <?php if(empty($penalites_membres)): ?>
                        <p class="text-center" style="color: var(--text-light); padding: 30px;">Aucune pénalité enregistrée</p>
                    <?php else: ?>
                        <table>
                            <thead>
                                <tr>
                                    <th>Membre</th>
                                    <th>Nombre d'amendes</th>
                                    <th>Amendes payées</th>
                                    <th>Amendes impayées</th>
                                    <th>Situation</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach($penalites_membres as $p): ?>
                                <tr>
                                    <td><strong><?= htmlspecialchars($p['prenom'] . ' ' . $p['nom']) ?></strong></td>
                                    <td><?= $p['nb_amendes'] ?></td>
                                    <td class="text-success"><?= number_format($p['amendes_payees'] ?? 0, 0, ',', ' ') ?> F</td>
                                    <td class="text-danger"><?= number_format($p['amendes_impayees'] ?? 0, 0, ',', ' ') ?> F</td>
                                    <td>
                                        <?php if(($p['amendes_impayees'] ?? 0) > 0): ?>
                                            <span class="badge danger">Impayé</span>
                                        <?php elseif(($p['amendes_payees'] ?? 0) > 0): ?>
                                            <span class="badge success">Payé</span>
                                        <?php else: ?>
                                            <span class="badge info">Aucune amende</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- ========== TAB 3 : HISTORIQUE DES SÉANCES ========== -->
        <div id="seances" class="tab-content">
            <div class="card">
                <div class="card-header">Détail des séances</div>
                <div class="card-body">
                    <?php if(empty($seances)): ?>
                        <p class="text-center" style="color: var(--text-light); padding: 30px;">Aucune séance n'a encore été tenue</p>
                    <?php else: ?>
                        <table>
                            <thead>
                                <tr>
                                    <th>Date</th>
                                    <th>Montant attendu</th>
                                    <th>Montant reçu</th>
                                    <th>Écart</th>
                                    <?php if($type_tontine == 'djangui' || $type_tontine == 'anniversaire'): ?>
                                        <th>Bénéficiaire</th>
                                    <?php endif; ?>
                                    <th>Participation</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach($seances as $s): 
                                    $prevu = $s['nb_membres'] * $tontine->montant_cotisation;
                                    $reel = $s['total_encaisse'] ?? 0;
                                    $ecart = $reel - $prevu;
                                    $taux_participation = $prevu > 0 ? round(($reel / $prevu) * 100, 1) : 0;
                                ?>
                                <tr>
                                    <td><?= date('d/m/Y', strtotime($s['date_seance'])) ?></td>
                                    <td><?= number_format($prevu, 0, ',', ' ') ?> F</td>
                                    <td><?= number_format($reel, 0, ',', ' ') ?> F</td>
                                    <td class="<?= $ecart >= 0 ? 'text-success' : 'text-danger' ?>">
                                        <?= ($ecart >= 0 ? '+' : '') . number_format($ecart, 0, ',', ' ') ?> F
                                    </td>
                                    <?php if($type_tontine == 'djangui' || $type_tontine == 'anniversaire'): ?>
                                        <td><?= htmlspecialchars($s['beneficiaire_nom'] ?? '-') ?></td>
                                    <?php endif; ?>
                                    <td>
                                        <?= $s['nb_presents'] ?> présent(s) / <?= $s['nb_membres'] ?> membres
                                        <br>
                                        <small>(<?= $taux_participation ?>% collecté)</small>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- ========== TAB 4 : SUIVI DES RETARDS (UNIQUEMENT pour Djangui et Anniversaire) ========== -->
        <?php if($type_tontine == 'djangui' || $type_tontine == 'anniversaire'): ?>
        <div id="retards" class="tab-content">
            <div class="card">
                <div class="card-header">Classement des membres par régularité</div>
                <div class="card-body">
                    <?php if(empty($classement_membres)): ?>
                        <p class="text-center" style="color: var(--text-light); padding: 30px;">Aucun membre dans cette tontine</p>
                    <?php else: ?>
                        <table>
                            <thead>
                                <tr>
                                    <th>Position</th>
                                    <th>Membre</th>
                                    <th>Paiements effectués</th>
                                    <th>Retards enregistrés</th>
                                    <th>Taux de ponctualité</th>
                                    <th>Appréciation</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php 
                                $rang = 1;
                                foreach($classement_membres as $c): 
                                    $total_seances_membre = $c['nb_paiements'] + $c['nb_retards'];
                                    $taux_ponctualite = $total_seances_membre > 0 ? round(($c['nb_paiements'] / $total_seances_membre) * 100, 1) : 100;
                                ?>
                                <tr>
                                    <td><strong>#<?= $rang++ ?></strong></td>
                                    <td><?= htmlspecialchars($c['prenom'] . ' ' . $c['nom']) ?></td>
                                    <td><?= $c['nb_paiements'] ?> séance(s)</td>
                                    <td><?= $c['nb_retards'] ?> fois</td>
                                    <td><?= $taux_ponctualite ?>%</td>
                                    <td>
                                        <?php if($c['nb_retards'] == 0): ?>
                                            <span class="badge success">Excellent</span>
                                        <?php elseif($c['nb_retards'] <= 2): ?>
                                            <span class="badge info">Correct</span>
                                        <?php elseif($c['nb_retards'] <= 4): ?>
                                            <span class="badge warning">À surveiller</span>
                                        <?php else: ?>
                                            <span class="badge danger">Préoccupant</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>

                        <div class="info-message mt-4">
                            <strong>Comment lire ce tableau :</strong> Plus un membre est en haut du classement, 
                            plus il est ponctuel. Les retards peuvent entraîner des amendes selon les règles de la tontine.
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <?php endif; ?>
        
        <div class="text-center mt-5">
            <a href="etats_detail_membres.php?tontine_id=<?= $tontine_id ?>" class="btn btn-outline" style="margin-right: 10px;">
                📋 Voir le détail par membre
            </a>
            <a href="../tontine/mes_tontines.php" class="btn">
                ← Retour à la liste des tontines
            </a>
        </div>
    </div>

    <script>
    // Fonction pour changer d'onglet
    function showTab(tabId, btn) {
        // Cacher tous les contenus
        var contents = document.querySelectorAll('.tab-content');
        for(var i = 0; i < contents.length; i++) {
            contents[i].classList.remove('active');
        }
        
        // Désactiver tous les boutons
        var tabs = document.querySelectorAll('.tab');
        for(var i = 0; i < tabs.length; i++) {
            tabs[i].classList.remove('active');
        }
        
        // Activer le bon contenu
        document.getElementById(tabId).classList.add('active');
        
        // Activer le bouton cliqué
        btn.classList.add('active');
    }
    </script>
</body>
</html>