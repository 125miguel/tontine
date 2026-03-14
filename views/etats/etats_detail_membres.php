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

$cotisation = new Cotisation($db);

// Récupérer toutes les séances
$query = "SELECT s.* FROM seances s
          WHERE s.tontine_id = :tid
          ORDER BY s.date_seance DESC";
$stmt = $db->prepare($query);
$stmt->execute(['tid' => $tontine_id]);
$seances = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Récupérer tous les membres actifs
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
    <title>Détail par membre - <?= htmlspecialchars($tontine->nom) ?></title>
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
        }
        
        * { margin: 0; padding: 0; box-sizing: border-box; }
        
        body {
            background: var(--bg-light);
            font-family: Arial, sans-serif;
            color: var(--text-dark);
        }
        
        .navbar {
            background: var(--primary);
            padding: 15px 0;
            color: var(--white);
        }
        
        .navbar .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 20px;
            display: flex;
            justify-content: space-between;
        }
        
        .navbar a {
            color: var(--white);
            text-decoration: none;
        }
        
        .navbar a:hover {
            text-decoration: underline;
        }
        
        .container {
            max-width: 1200px;
            margin: 30px auto;
            padding: 0 20px;
        }
        
        .card {
            background: var(--white);
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            margin-bottom: 20px;
            border: 1px solid var(--border);
        }
        
        .card-header {
            background: var(--primary);
            color: var(--white);
            padding: 15px 20px;
            border-radius: 10px 10px 0 0;
            font-weight: bold;
        }
        
        .card-body { padding: 20px; }
        
        .row { display: flex; gap: 20px; margin-bottom: 20px; flex-wrap: wrap; }
        .col { flex: 1; min-width: 200px; }
        
        .form-select {
            width: 100%;
            padding: 10px;
            border: 1px solid var(--border);
            border-radius: 5px;
            font-size: 16px;
        }
        
        .form-select:focus {
            outline: 2px solid var(--primary);
            outline-offset: 2px;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 10px;
        }
        
        th {
            background: var(--primary);
            color: var(--white);
            padding: 10px;
            text-align: left;
        }
        
        td {
            padding: 10px;
            border-bottom: 1px solid var(--border);
        }
        
        .table-bordered {
            border: 1px solid var(--border);
        }
        
        .table-bordered th, .table-bordered td {
            border: 1px solid var(--border);
        }
        
        .btn {
            display: inline-block;
            padding: 10px 20px;
            background: var(--primary);
            color: var(--white);
            text-decoration: none;
            border-radius: 5px;
            border: none;
            cursor: pointer;
        }
        
        .btn:hover {
            background: var(--primary-light);
        }
        
        .text-center { text-align: center; }
        .mt-5 { margin-top: 30px; }
        .mb-3 { margin-bottom: 15px; }
        
        .bg-success-cell { 
            background-color: #D1FAE5; 
            color: #065F46;
        }
        
        .bg-warning-cell { 
            background-color: #FEF3C7; 
            color: #92400E;
        }
        
        .bg-secondary-cell { 
            background-color: #F3F4F6; 
            color: var(--text-dark);
        }
        
        .bg-info-cell { 
            background-color: #DBEAFE; 
            color: var(--primary);
        }
        
        .bg-light { 
            background-color: var(--bg-light); 
        }
        
        .table-responsive {
            overflow-x: auto;
        }
        
        small {
            font-size: 11px;
            opacity: 0.8;
        }
    </style>
</head>
<body>
    <div class="navbar">
        <div class="container">
            <span style="font-size: 24px; font-weight: bold;">TONTONTINE</span>
            <div>
                <span style="margin-right: 20px;"><?= htmlspecialchars($_SESSION['association_nom']) ?></span>
                <a href="etats_administrateur.php?tontine_id=<?= $tontine_id ?>">← Retour aux états</a>
            </div>
        </div>
    </div>

    <div class="container">
        <h2 style="margin-bottom: 20px;"> Détail par membre - <?= htmlspecialchars($tontine->nom) ?></h2>

        <div class="card">
            <div class="card-header"> Filtre</div>
            <div class="card-body">
                <div class="row">
                    <div class="col">
                        <select class="form-select" id="filtreMembre" onchange="filterMembre(this.value)">
                            <option value="all">Tous les membres</option>
                            <?php foreach($membres_liste as $m): ?>
                                <option value="<?= $m['id'] ?>"><?= htmlspecialchars($m['prenom'] . ' ' . $m['nom']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-header"> Tableau des cotisations par membre</div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table-bordered">
                        <thead>
                            <tr>
                                <th>Membre</th>
                                <?php foreach($seances as $s): ?>
                                    <th class="text-center"><?= date('d/m', strtotime($s['date_seance'])) ?></th>
                                <?php endforeach; ?>
                                <th class="text-center">Total</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            $total_general = 0;
                            foreach($membres_liste as $membre):
                                $total_membre = 0;
                            ?>
                                <tr class="membre-row" data-membre-id="<?= $membre['id'] ?>">
                                    <td><strong><?= htmlspecialchars($membre['prenom'] . ' ' . $membre['nom']) ?></strong></td>
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
                                                echo '<td class="text-center bg-success-cell">' . number_format($cotisation_data['montant'],0,',',' ') . ' F<br><small>payé</small></td>';
                                            elseif($cotisation_data['statut'] == 'retard'):
                                                $total_membre += $cotisation_data['montant'];
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
                                </tr>
                            <?php 
                                $total_general += $total_membre;
                            endforeach; 
                            ?>
                        </tbody>
                        <tfoot>
                            <tr>
                                <th>Total séance</th>
                                <?php 
                                foreach($seances as $s): 
                                    $query_total = "SELECT SUM(montant) as total FROM cotisations 
                                                    WHERE seance_id = :sid AND statut = 'paye'";
                                    $stmt_total = $db->prepare($query_total);
                                    $stmt_total->execute(['sid' => $s['id']]);
                                    $total_seance = $stmt_total->fetch()['total'] ?? 0;
                                ?>
                                    <th class="text-center"><?= number_format($total_seance,0,',',' ') ?> F</th>
                                <?php endforeach; ?>
                                <th class="text-center"><?= number_format($total_general,0,',',' ') ?> F</th>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>
        </div>

        <div class="text-center mt-5">
            <a href="etats_administrateur.php?tontine_id=<?= $tontine_id ?>" class="btn">← Retour aux états</a>
        </div>
    </div>

    <script>
    function filterMembre(membreId) {
        var rows = document.querySelectorAll('.membre-row');
        for(var i = 0; i < rows.length; i++) {
            if(membreId === 'all') {
                rows[i].style.display = '';
            } else {
                if(rows[i].dataset.membreId == membreId) {
                    rows[i].style.display = '';
                } else {
                    rows[i].style.display = 'none';
                }
            }
        }
    }
    </script>
</body>
</html>