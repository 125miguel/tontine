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
require_once __DIR__ . '/../../models/Cotisation.php';
require_once __DIR__ . '/../../models/MembreTontine.php';
require_once __DIR__ . '/../../models/AmendeAppliquee.php';

$database = new Database();
$db = $database->getConnection();

$tontine_id = $_GET['tontine_id'] ?? 0;
$cycle_id = $_GET['cycle'] ?? 'all';
$membre_filtre = $_GET['membre'] ?? 'all';

if(!$tontine_id) {
    header("Location: ../tontine/mes_tontines.php");
    exit();
}

$tontine = new Tontine($db);
if(!$tontine->getById($tontine_id) || $tontine->admin_id != $_SESSION['user_id']) {
    header("Location: ../tontine/mes_tontines.php");
    exit();
}

$cotisation = new Cotisation($db);
$membreTontine = new MembreTontine($db);
$amendeAppliquee = new AmendeAppliquee($db);

// ========== RÉCUPÉRATION DES CYCLES ==========
$cycles = [];
$query_cycles = "SELECT * FROM cycles_tontine WHERE tontine_id = :tid ORDER BY numero_cycle DESC";
$stmt_cycles = $db->prepare($query_cycles);
$stmt_cycles->execute(['tid' => $tontine_id]);
$cycles_historique = $stmt_cycles->fetchAll(PDO::FETCH_ASSOC);

// ========== FILTRAGE DES SÉANCES PAR CYCLE ==========
$condition_cycle = "";
$params = ['tid' => $tontine_id];

if($cycle_id != 'all') {
    if($cycle_id == 'actuel') {
        $condition_cycle = " AND s.date_seance BETWEEN :debut_cycle AND :fin_cycle";
        $params['debut_cycle'] = $tontine->date_debut_cycle;
        $params['fin_cycle'] = $tontine->date_fin_cycle;
    } else {
        // Chercher dans l'historique
        foreach($cycles_historique as $c) {
            if($c['numero_cycle'] == $cycle_id) {
                $condition_cycle = " AND s.date_seance BETWEEN :debut_cycle AND :fin_cycle";
                $params['debut_cycle'] = $c['date_debut'];
                $params['fin_cycle'] = $c['date_fin'];
                break;
            }
        }
    }
}

// Récupérer les séances filtrées
$query = "SELECT s.* FROM seances s
          WHERE s.tontine_id = :tid $condition_cycle
          ORDER BY s.date_seance DESC";
$stmt = $db->prepare($query);
$stmt->execute($params);
$seances = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Récupérer tous les membres actifs
$query_membres = "SELECT mt.id, u.nom, u.prenom, u.telephone, u.email
                  FROM membre_tontine mt
                  JOIN users u ON mt.user_id = u.id
                  WHERE mt.tontine_id = :tid AND mt.est_actif = 1
                  ORDER BY u.nom, u.prenom";
$stmt_membres = $db->prepare($query_membres);
$stmt_membres->execute(['tid' => $tontine_id]);
$membres_liste = $stmt_membres->fetchAll(PDO::FETCH_ASSOC);

// ========== STATISTIQUES GLOBALES ==========
$total_cotisations = 0;
$total_amendes = 0;
$total_retards = 0;

foreach($membres_liste as $m) {
    // Récupérer les stats du membre directement avec des requêtes
    $query_stats = "SELECT 
                        (SELECT SUM(montant) FROM cotisations WHERE membre_tontine_id = :mid AND statut = 'paye') as total_cotise,
                        (SELECT COUNT(*) FROM cotisations WHERE membre_tontine_id = :mid AND statut = 'retard') as nb_retards,
                        (SELECT SUM(montant) FROM amendes_appliquees WHERE membre_tontine_id = :mid) as total_amendes";
    $stmt_stats = $db->prepare($query_stats);
    $stmt_stats->execute(['mid' => $m['id']]);
    $stats = $stmt_stats->fetch(PDO::FETCH_ASSOC);
    
    $total_cotisations += $stats['total_cotise'] ?? 0;
    $total_amendes += $stats['total_amendes'] ?? 0;
    $total_retards += $stats['nb_retards'] ?? 0;
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Détail par membre - <?= htmlspecialchars($tontine->nom) ?></title>
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
            max-width: 1400px;
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
            max-width: 1400px;
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
        
        .card-header i {
            margin-right: 8px;
        }
        
        .card-body {
            padding: 20px;
        }
        
        .filter-section {
            background: var(--bg-light);
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 20px;
            border: 1px solid var(--border);
        }
        
        .filter-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 15px;
            margin-bottom: 15px;
        }
        
        .filter-item {
            display: flex;
            flex-direction: column;
        }
        
        .filter-item label {
            font-size: 13px;
            font-weight: 600;
            color: var(--text-light);
            margin-bottom: 5px;
        }
        
        .form-select, .form-control {
            padding: 10px 12px;
            border: 2px solid var(--border);
            border-radius: 8px;
            font-size: 14px;
            transition: all 0.2s;
        }
        
        .form-select:focus, .form-control:focus {
            border-color: var(--primary);
            outline: none;
            box-shadow: 0 0 0 3px rgba(30, 58, 138, 0.1);
        }
        
        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            padding: 10px 20px;
            background: var(--primary);
            color: var(--white);
            text-decoration: none;
            border-radius: 8px;
            border: none;
            cursor: pointer;
            font-size: 14px;
            font-weight: 500;
            transition: all 0.2s;
        }
        
        .btn:hover {
            background: var(--primary-light);
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(30, 58, 138, 0.2);
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
        
        .btn-sm {
            padding: 6px 12px;
            font-size: 13px;
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-bottom: 25px;
        }
        
        .stat-card {
            background: var(--white);
            padding: 20px;
            border-radius: 10px;
            border: 1px solid var(--border);
            text-align: center;
        }
        
        .stat-value {
            font-size: 28px;
            font-weight: 700;
            color: var(--primary);
        }
        
        .stat-label {
            font-size: 14px;
            color: var(--text-light);
            margin-top: 5px;
        }
        
        .table-responsive {
            overflow-x: auto;
            margin-top: 15px;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
            font-size: 13px;
        }
        
        th {
            background: var(--primary);
            color: var(--white);
            padding: 12px 8px;
            text-align: center;
            font-weight: 600;
            white-space: nowrap;
        }
        
        td {
            padding: 10px 8px;
            border-bottom: 1px solid var(--border);
            text-align: center;
            vertical-align: middle;
        }
        
        .membre-info {
            text-align: left;
            font-weight: 600;
            min-width: 180px;
        }
        
        .membre-info small {
            display: block;
            font-size: 11px;
            color: var(--text-light);
            font-weight: normal;
        }
        
        tr:hover td {
            background-color: var(--bg-light);
        }
        
        .bg-success-cell { 
            background-color: var(--success-bg); 
            color: #065F46;
            font-weight: 600;
        }
        
        .bg-warning-cell { 
            background-color: var(--warning-bg); 
            color: #92400E;
            font-weight: 600;
        }
        
        .bg-secondary-cell { 
            background-color: #F3F4F6; 
            color: var(--text-dark);
        }
        
        .bg-info-cell { 
            background-color: var(--info-bg); 
            color: var(--primary);
            font-weight: 700;
        }
        
        .bg-light { 
            background-color: var(--bg-light); 
        }
        
        .total-row {
            background: var(--bg-light);
            font-weight: 600;
        }
        
        .total-row td {
            border-top: 2px solid var(--primary);
        }
        
        .badge {
            display: inline-block;
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 11px;
            font-weight: 600;
        }
        
        .badge-success { background: var(--success-bg); color: #065F46; }
        .badge-warning { background: var(--warning-bg); color: #92400E; }
        .badge-danger { background: var(--danger-bg); color: #991B1B; }
        .badge-info { background: var(--info-bg); color: var(--primary); }
        
        .info-box {
            background: var(--info-bg);
            color: var(--primary);
            padding: 15px;
            border-radius: 8px;
            margin: 15px 0;
            border-left: 4px solid var(--primary);
        }
        
        .summary-row {
            display: flex;
            justify-content: space-between;
            padding: 8px 0;
            border-bottom: 1px dashed var(--border);
        }
        
        .summary-row:last-child {
            border-bottom: none;
        }
        
        .text-success { color: var(--success); }
        .text-warning { color: var(--warning); }
        .text-danger { color: var(--danger); }
        
        .mt-4 { margin-top: 20px; }
        .mb-3 { margin-bottom: 15px; }
        .text-center { text-align: center; }
        
        .cycle-tag {
            background: var(--info-bg);
            color: var(--primary);
            padding: 5px 15px;
            border-radius: 50px;
            font-size: 13px;
            display: inline-block;
            margin-right: 10px;
        }
    </style>
</head>
<body>
    <div class="navbar">
        <div class="container">
            <span style="font-size: 24px; font-weight: 700;"><i class="bi bi-bank2"></i> TONTONTINE</span>
            <div>
                <span style="margin-right: 20px;"><i class="bi bi-building"></i> <?= htmlspecialchars($_SESSION['association_nom']) ?></span>
                <a href="etats_administrateur.php?tontine_id=<?= $tontine_id ?>"><i class="bi bi-arrow-left"></i> Retour aux états</a>
            </div>
        </div>
    </div>

    <div class="container">
        <div class="page-header">
            <h2><i class="bi bi-person-badge"></i> Détail par membre</h2>
            <p><?= htmlspecialchars($tontine->nom) ?> - <?= ucfirst($tontine->type_tontine) ?> (<?= number_format($tontine->montant_cotisation,0,',',' ') ?> F/séance)</p>
        </div>

        <!-- FILTRES AVANCÉS -->
        <div class="filter-section">
            <h5 style="margin-bottom: 15px; color: var(--primary);">
                <i class="bi bi-funnel"></i> Filtres
            </h5>
            <form method="GET" action="" id="filterForm">
                <input type="hidden" name="tontine_id" value="<?= $tontine_id ?>">
                
                <div class="filter-grid">
                    <div class="filter-item">
                        <label><i class="bi bi-arrow-repeat"></i> Cycle</label>
                        <select name="cycle" class="form-select" onchange="this.form.submit()">
                            <option value="all" <?= $cycle_id == 'all' ? 'selected' : '' ?>>Tous les cycles</option>
                            <option value="actuel" <?= $cycle_id == 'actuel' ? 'selected' : '' ?>>Cycle actuel (n°<?= $tontine->cycle_actuel ?>)</option>
                            <?php foreach($cycles_historique as $c): ?>
                                <option value="<?= $c['numero_cycle'] ?>" <?= $cycle_id == $c['numero_cycle'] ? 'selected' : '' ?>>
                                    Cycle n°<?= $c['numero_cycle'] ?> (<?= date('d/m/Y', strtotime($c['date_debut'])) ?> - <?= date('d/m/Y', strtotime($c['date_fin'])) ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="filter-item">
                        <label><i class="bi bi-person"></i> Membre</label>
                        <select name="membre" class="form-select" onchange="this.form.submit()">
                            <option value="all" <?= $membre_filtre == 'all' ? 'selected' : '' ?>>Tous les membres</option>
                            <?php foreach($membres_liste as $m): ?>
                                <option value="<?= $m['id'] ?>" <?= $membre_filtre == $m['id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($m['prenom'] . ' ' . $m['nom']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="filter-item" style="justify-content: flex-end; display: flex; align-items: flex-end;">
                        <button type="button" class="btn btn-outline btn-sm" onclick="resetFilters()">
                            <i class="bi bi-x-circle"></i> Réinitialiser
                        </button>
                    </div>
                </div>
            </form>
        </div>

        <!-- STATISTIQUES GLOBALES -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-value"><?= count($membres_liste) ?></div>
                <div class="stat-label">Membres actifs</div>
            </div>
            <div class="stat-card">
                <div class="stat-value"><?= count($seances) ?></div>
                <div class="stat-label">Séances</div>
            </div>
            <div class="stat-card">
                <div class="stat-value"><?= number_format($total_cotisations, 0, ',', ' ') ?> F</div>
                <div class="stat-label">Total cotisations</div>
            </div>
            <div class="stat-card">
                <div class="stat-value"><?= number_format($total_amendes, 0, ',', ' ') ?> F</div>
                <div class="stat-label">Total amendes</div>
            </div>
            <div class="stat-card">
                <div class="stat-value"><?= $total_retards ?></div>
                <div class="stat-label">Retards</div>
            </div>
        </div>

        <!-- LÉGENDE -->
        <div class="info-box">
            <div style="display: flex; gap: 20px; flex-wrap: wrap;">
                <span><span class="badge badge-success">■</span> Payé</span>
                <span><span class="badge badge-warning">■</span> Retard</span>
                <span><span class="badge badge-info">■</span> En attente</span>
                <span><span class="badge" style="background: #F3F4F6;">■</span> Non concerné</span>
            </div>
        </div>

        <!-- TABLEAU DÉTAILLÉ -->
        <div class="card">
            <div class="card-header">
                <i class="bi bi-table"></i> Tableau des cotisations par membre
                <?php if($cycle_id != 'all'): ?>
                    <span class="cycle-tag">
                        <?php 
                        if($cycle_id == 'actuel') {
                            echo "Cycle actuel (n°{$tontine->cycle_actuel})";
                        } else {
                            echo "Cycle n°$cycle_id";
                        }
                        ?>
                    </span>
                <?php endif; ?>
            </div>
            <div class="card-body">
                <?php if(empty($seances)): ?>
                    <div class="alert alert-warning">
                        <i class="bi bi-exclamation-triangle"></i>
                        Aucune séance trouvée pour la période sélectionnée.
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table>
                            <thead>
                                <tr>
                                    <th style="min-width: 200px;">Membre</th>
                                    <?php foreach($seances as $index => $s): ?>
                                        <th class="text-center" title="<?= date('d/m/Y', strtotime($s['date_seance'])) ?>">
                                            <strong>Séance <?= $index + 1 ?></strong><br>
                                            <small><?= date('d/m', strtotime($s['date_seance'])) ?></small>
                                        </th>
                                    <?php endforeach; ?>
                                    <th class="text-center" style="min-width: 100px;">Total</th>
                                    <th class="text-center" style="min-width: 80px;">Retards</th>
                                    <th class="text-center" style="min-width: 100px;">Amendes</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                $total_general = 0;
                                $stats_membres = [];
                                
                                foreach($membres_liste as $membre):
                                    // Filtrer si un membre spécifique est sélectionné
                                    if($membre_filtre != 'all' && $membre_filtre != $membre['id']) {
                                        continue;
                                    }
                                    
                                    $total_membre = 0;
                                    $nb_retards_membre = 0;
                                    $total_amendes_membre = 0;
                                    
                                    // Récupérer les amendes du membre
                                    $query_amendes = "SELECT SUM(montant) as total, est_paye FROM amendes_appliquees 
                                                      WHERE membre_tontine_id = :mid GROUP BY est_paye";
                                    $stmt_amendes = $db->prepare($query_amendes);
                                    $stmt_amendes->execute(['mid' => $membre['id']]);
                                    while($a = $stmt_amendes->fetch(PDO::FETCH_ASSOC)) {
                                        if($a['est_paye']) {
                                            $total_amendes_membre += $a['total'];
                                        }
                                    }
                                ?>
                                    <tr class="membre-row" data-membre-id="<?= $membre['id'] ?>">
                                        <td class="membre-info">
                                            <i class="bi bi-person-circle" style="color: var(--primary); margin-right: 5px;"></i>
                                            <?= htmlspecialchars($membre['prenom'] . ' ' . $membre['nom']) ?>
                                            <small>
                                                <i class="bi bi-telephone"></i> <?= htmlspecialchars($membre['telephone'] ?? '') ?>
                                            </small>
                                        </td>
                                        <?php 
                                        foreach($seances as $s):
                                            $query = "SELECT statut, montant FROM cotisations 
                                                      WHERE seance_id = :sid AND membre_tontine_id = :mid";
                                            $stmt = $db->prepare($query);
                                            $stmt->execute([
                                                'sid' => $s['id'],
                                                'mid' => $membre['id']
                                            ]);
                                            $cotisation_data = $stmt->fetch(PDO::FETCH_ASSOC);
                                            
                                            if($cotisation_data):
                                                if($cotisation_data['statut'] == 'paye'):
                                                    $total_membre += $cotisation_data['montant'];
                                                    echo '<td class="text-center bg-success-cell">' . number_format($cotisation_data['montant'],0,',',' ') . ' F</td>';
                                                elseif($cotisation_data['statut'] == 'retard'):
                                                    $total_membre += $cotisation_data['montant'];
                                                    $nb_retards_membre++;
                                                    echo '<td class="text-center bg-warning-cell">' . number_format($cotisation_data['montant'],0,',',' ') . ' F<br><small>retard</small></td>';
                                                else:
                                                    echo '<td class="text-center bg-secondary-cell">' . number_format($cotisation_data['montant'],0,',',' ') . ' F<br><small>en attente</small></td>';
                                                endif;
                                            else:
                                                echo '<td class="text-center bg-light">-</td>';
                                            endif;
                                        endforeach; 
                                        ?>
                                        <td class="text-center bg-info-cell">
                                            <strong><?= number_format($total_membre,0,',',' ') ?> F</strong>
                                        </td>
                                        <td class="text-center">
                                            <?php if($nb_retards_membre > 0): ?>
                                                <span class="badge-warning" style="padding: 4px 8px; border-radius: 4px;"><?= $nb_retards_membre ?> retard(s)</span>
                                            <?php else: ?>
                                                <span class="badge-success" style="padding: 4px 8px; border-radius: 4px;">0</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="text-center">
                                            <?php if($total_amendes_membre > 0): ?>
                                                <span class="badge-warning" style="padding: 4px 8px; border-radius: 4px;">
                                                    <?= number_format($total_amendes_membre,0,',',' ') ?> F
                                                </span>
                                            <?php else: ?>
                                                -
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php 
                                    $total_general += $total_membre;
                                    $stats_membres[] = [
                                        'nom' => $membre['prenom'] . ' ' . $membre['nom'],
                                        'total' => $total_membre,
                                        'retards' => $nb_retards_membre,
                                        'amendes' => $total_amendes_membre
                                    ];
                                endforeach; 
                                ?>
                                
                                <!-- Ligne des totaux -->
                                <tr class="total-row">
                                    <td><strong>TOTAUX</strong></td>
                                    <?php foreach($seances as $s): 
                                        $query_total = "SELECT SUM(montant) as total FROM cotisations 
                                                        WHERE seance_id = :sid AND (statut = 'paye' OR statut = 'retard')";
                                        $stmt_total = $db->prepare($query_total);
                                        $stmt_total->execute(['sid' => $s['id']]);
                                        $total_seance = $stmt_total->fetch()['total'] ?? 0;
                                    ?>
                                        <td class="text-center"><strong><?= number_format($total_seance,0,',',' ') ?> F</strong></td>
                                    <?php endforeach; ?>
                                    <td class="text-center"><strong><?= number_format($total_general,0,',',' ') ?> F</strong></td>
                                    <td class="text-center"><strong><?= array_sum(array_column($stats_membres, 'retards')) ?></strong></td>
                                    <td class="text-center"><strong><?= number_format(array_sum(array_column($stats_membres, 'amendes')),0,',',' ') ?> F</strong></td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    
                    <!-- RÉSUMÉ PAR MEMBRE (si un seul membre est sélectionné) -->
                    <?php if($membre_filtre != 'all' && !empty($stats_membres)): 
                        $m = $stats_membres[0];
                        $total_seances_membre = count($seances);
                        $taux_ponctualite = $total_seances_membre > 0 ? round(($total_seances_membre - $m['retards']) / $total_seances_membre * 100, 1) : 100;
                    ?>
                        <div class="card mt-4">
                            <div class="card-header" style="background: var(--info);">
                                <i class="bi bi-file-person"></i> Résumé pour <?= htmlspecialchars($m['nom']) ?>
                            </div>
                            <div class="card-body">
                                <div class="stats-grid" style="grid-template-columns: repeat(3, 1fr);">
                                    <div class="stat-card">
                                        <div class="stat-value <?= $m['total'] > 0 ? 'text-success' : '' ?>">
                                            <?= number_format($m['total'],0,',',' ') ?> F
                                        </div>
                                        <div class="stat-label">Total cotisé</div>
                                    </div>
                                    <div class="stat-card">
                                        <div class="stat-value <?= $m['retards'] > 0 ? 'text-warning' : 'text-success' ?>">
                                            <?= $m['retards'] ?>
                                        </div>
                                        <div class="stat-label">Retards</div>
                                    </div>
                                    <div class="stat-card">
                                        <div class="stat-value <?= $m['amendes'] > 0 ? 'text-warning' : 'text-success' ?>">
                                            <?= number_format($m['amendes'],0,',',' ') ?> F
                                        </div>
                                        <div class="stat-label">Amendes</div>
                                    </div>
                                </div>
                                
                                <div class="summary-box" style="margin-top: 20px;">
                                    <h6 style="color: var(--primary); margin-bottom: 10px;">Analyse</h6>
                                    <div class="summary-row">
                                        <span>Nombre de séances :</span>
                                        <strong><?= count($seances) ?></strong>
                                    </div>
                                    <div class="summary-row">
                                        <span>Moyenne par séance :</span>
                                        <strong><?= count($seances) > 0 ? number_format($m['total'] / count($seances), 0, ',', ' ') : 0 ?> F</strong>
                                    </div>
                                    <div class="summary-row">
                                        <span>Taux de ponctualité :</span>
                                        <strong>
                                            <span class="<?= $taux_ponctualite >= 90 ? 'text-success' : ($taux_ponctualite >= 70 ? 'text-warning' : 'text-danger') ?>">
                                                <?= $taux_ponctualite ?>%
                                            </span>
                                        </strong>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>
                    
                <?php endif; ?>
            </div>
        </div>

        <div class="text-center mt-4">
            <a href="etats_administrateur.php?tontine_id=<?= $tontine_id ?>" class="btn">
                <i class="bi bi-arrow-left"></i> Retour aux états
            </a>
            <button onclick="window.print()" class="btn btn-outline ms-2">
                <i class="bi bi-printer"></i> Imprimer
            </button>
        </div>
    </div>

    <script>
    function resetFilters() {
        window.location.href = 'etats_detail_membres.php?tontine_id=<?= $tontine_id ?>';
    }
    
    // Mise en évidence de la ligne au survol
    document.querySelectorAll('.membre-row').forEach(row => {
        row.addEventListener('mouseenter', function() {
            this.style.backgroundColor = 'var(--bg-light)';
        });
        row.addEventListener('mouseleave', function() {
            this.style.backgroundColor = '';
        });
    });
    </script>
</body>
</html>