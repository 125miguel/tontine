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

$membreTontine = new MembreTontine($db);
$cotisation = new Cotisation($db);
$amendeAppliquee = new AmendeAppliquee($db);

// ============================================
// 1. ÉTAT GÉNÉRAL DE LA TONTINE
// ============================================

$total_membres = $membreTontine->countMembres($tontine_id);

$query = "SELECT SUM(montant) as total FROM cotisations c
          JOIN seances s ON c.seance_id = s.id
          WHERE s.tontine_id = :tid AND c.statut = 'paye'";
$stmt = $db->prepare($query);
$stmt->execute(['tid' => $tontine_id]);
$total_collecte = $stmt->fetch()['total'] ?? 0;

$query = "SELECT SUM(total_collecte) as total FROM seances 
          WHERE tontine_id = :tid AND est_cloturee = 1";
$stmt = $db->prepare($query);
$stmt->execute(['tid' => $tontine_id]);
$total_distribue = $stmt->fetch()['total'] ?? 0;

$query = "SELECT SUM(a.montant) as total FROM amendes_appliquees a
          JOIN seances s ON a.seance_id = s.id
          WHERE s.tontine_id = :tid AND a.est_paye = 1";
$stmt = $db->prepare($query);
$stmt->execute(['tid' => $tontine_id]);
$solde_amendes = $stmt->fetch()['total'] ?? 0;

// ============================================
// 2. ÉTAT DES MEMBRES
// ============================================

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

$query = "SELECT COUNT(*) as total FROM membre_tontine 
          WHERE tontine_id = :tid AND est_actif = 0";
$stmt = $db->prepare($query);
$stmt->execute(['tid' => $tontine_id]);
$membres_exclus = $stmt->fetch()['total'];

$query = "SELECT u.nom, u.prenom, 
                 COUNT(a.id) as nb_amendes, 
                 SUM(a.montant) as total_amendes
          FROM membre_tontine mt
          JOIN users u ON mt.user_id = u.id
          LEFT JOIN amendes_appliquees a ON a.membre_tontine_id = mt.id
          WHERE mt.tontine_id = :tid
          GROUP BY mt.id
          ORDER BY total_amendes DESC";
$stmt = $db->prepare($query);
$stmt->execute(['tid' => $tontine_id]);
$penalites_membres = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ============================================
// 3. ÉTAT PAR SÉANCE
// ============================================

$query = "SELECT s.*, 
                 CONCAT(u.prenom, ' ', u.nom) as beneficiaire_nom,
                 (SELECT COUNT(*) FROM membre_tontine WHERE tontine_id = :tid AND est_actif = 1) as nb_membres,
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
                 SUM(CASE WHEN c.statut = 'retard' THEN 1 ELSE 0 END) as nb_retards,
                 SUM(CASE WHEN c.statut = 'paye' THEN 1 ELSE 0 END) as nb_paiements
          FROM membre_tontine mt
          JOIN users u ON mt.user_id = u.id
          LEFT JOIN cotisations c ON c.membre_tontine_id = mt.id
          WHERE mt.tontine_id = :tid
          GROUP BY mt.id
          ORDER BY nb_retards ASC, nb_paiements DESC";
$stmt = $db->prepare($query);
$stmt->execute(['tid' => $tontine_id]);
$classement_membres = $stmt->fetchAll(PDO::FETCH_ASSOC);

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
        
        .tabs {
            display: flex;
            gap: 5px;
            margin-bottom: 20px;
            border-bottom: 2px solid var(--primary);
            flex-wrap: wrap;
        }
        
        .tab {
            padding: 10px 20px;
            background: none;
            border: none;
            color: var(--primary);
            font-weight: bold;
            cursor: pointer;
            font-size: 14px;
            border-radius: 5px 5px 0 0;
        }
        
        .tab.active {
            background: var(--primary);
            color: var(--white);
        }
        
        .tab-content { display: none; }
        .tab-content.active { display: block; }
        
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
        
        .row { display: flex; gap: 20px; flex-wrap: wrap; }
        .col { flex: 1; min-width: 200px; }
        
        .stat-box {
            background: var(--bg-light);
            padding: 20px;
            text-align: center;
            border-radius: 10px;
            border: 1px solid var(--border);
        }
        
        .stat-number { 
            font-size: 32px; 
            font-weight: bold; 
            color: var(--primary); 
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
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
        
        tr:hover {
            background-color: var(--bg-light);
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
        
        .text-success { color: var(--success); }
        .text-warning { color: var(--warning); }
        .text-danger { color: var(--danger); }
        
        .mt-5 { margin-top: 30px; }
        .text-center { text-align: center; }
        
        .detail-link {
            display: inline-block;
            padding: 10px 20px;
            background: var(--primary-light);
            color: var(--white);
            text-decoration: none;
            border-radius: 5px;
            margin-top: 10px;
        }
        
        .detail-link:hover {
            background: var(--primary);
        }
    </style>
</head>
<body>
    <div class="navbar">
        <div class="container">
            <span style="font-size: 24px; font-weight: bold;">TONTONTINE</span>
            <div>
                <span style="margin-right: 20px;"><?= htmlspecialchars($_SESSION['association_nom']) ?></span>
                <a href="../tontine/mes_tontines.php">← Retour</a>
            </div>
        </div>
    </div>

    <div class="container">
        <h2 style="margin-bottom: 20px;">États - <?= htmlspecialchars($tontine->nom) ?></h2>

        <div class="tabs">
            <button class="tab active" onclick="window.showTab('general', this)"> État Général</button>
            <button class="tab" onclick="window.showTab('membres', this)"> État Membres</button>
            <button class="tab" onclick="window.showTab('seances', this)"> État Séances</button>
            <button class="tab" onclick="window.showTab('retards', this)"> État Retards</button>
        </div>

        <div id="general" class="tab-content active">
            <div class="card">
                <div class="card-header"> État général</div>
                <div class="card-body">
                    <div class="row">
                        <div class="col"><div class="stat-box"><div class="stat-number"><?= $total_membres ?></div><div>Membres</div></div></div>
                        <div class="col"><div class="stat-box"><div class="stat-number"><?= number_format($total_collecte,0,',',' ') ?> F</div><div>Collecté</div></div></div>
                        <div class="col"><div class="stat-box"><div class="stat-number"><?= number_format($total_distribue,0,',',' ') ?> F</div><div>Distribué</div></div></div>
                        <div class="col"><div class="stat-box"><div class="stat-number"><?= number_format($solde_amendes,0,',',' ') ?> F</div><div>Amendes</div></div></div>
                    </div>
                </div>
            </div>
        </div>

        <div id="membres" class="tab-content">
            <div class="card">
                <div class="card-header">👥 État des membres</div>
                <div class="card-body">
                    <div class="row">
                        <div class="col"><div class="stat-box"><div class="stat-number text-success"><?= $membres_a_jour ?></div><div>À jour</div></div></div>
                        <div class="col"><div class="stat-box"><div class="stat-number text-warning"><?= $membres_en_retard ?></div><div>En retard</div></div></div>
                        <div class="col"><div class="stat-box"><div class="stat-number text-danger"><?= $membres_exclus ?></div><div>Exclus</div></div></div>
                    </div>

                    <h3 style="margin: 20px 0;">Historique des pénalités</h3>
                    <table>
                        <tr><th>Membre</th><th>Amendes</th><th>Total</th></tr>
                        <?php foreach($penalites_membres as $p): ?>
                        <tr>
                            <td><?= htmlspecialchars($p['prenom'] . ' ' . $p['nom']) ?></td>
                            <td><?= $p['nb_amendes'] ?></td>
                            <td><?= number_format($p['total_amendes'] ?? 0,0,',',' ') ?> F</td>
                        </tr>
                        <?php endforeach; ?>
                    </table>
                </div>
            </div>
        </div>

        <div id="seances" class="tab-content">
            <div class="card">
                <div class="card-header"> État par séance</div>
                <div class="card-body">
                    <table>
                        <tr><th>Date</th><th>Montant Prévu</th><th>Montant Reçu</th><th>Écart</th><th>Bénéficiaire</th></tr>
                        <?php foreach($seances as $s): 
                            $prevu = $s['nb_membres'] * $tontine->montant_cotisation;
                            $reel = $s['total_encaisse'] ?? 0;
                            $ecart = $reel - $prevu;
                        ?>
                        <tr>
                            <td><?= date('d/m/Y', strtotime($s['date_seance'])) ?></td>
                            <td><?= number_format($prevu,0,',',' ') ?> F</td>
                            <td><?= number_format($reel,0,',',' ') ?> F</td>
                            <td class="<?= $ecart>=0?'text-success':'text-danger' ?>"><?= number_format($ecart,0,',',' ') ?> F</td>
                            <td><?= htmlspecialchars($s['beneficiaire_nom'] ?? '-') ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </table>
                </div>
            </div>
        </div>

        <div id="retards" class="tab-content">
            <div class="card">
                <div class="card-header"> État des retards</div>
                <div class="card-body">
                    <table>
                        <tr><th>#</th><th>Membre</th><th>Paiements</th><th>Retards</th></tr>
                        <?php $rang=1; foreach($classement_membres as $c): ?>
                        <tr>
                            <td><?= $rang++ ?></td>
                            <td><?= htmlspecialchars($c['prenom'] . ' ' . $c['nom']) ?></td>
                            <td><?= $c['nb_paiements'] ?></td>
                            <td><?= $c['nb_retards'] ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </table>
                </div>
            </div>
        </div>
        
        <div class="text-center">
            <a href="etats_detail_membres.php?tontine_id=<?= $tontine_id ?>" class="detail-link"> Détail par membre</a>
        </div>

        <div class="text-center mt-5">
            <a href="../tontine/mes_tontines.php" class="btn">← Retour</a>
        </div>
    </div>

    <script>
    // Définir les fonctions GLOBALEMENT
    window.showTab = function(tabId, btn) {
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
    };
    
    console.log('Script chargé, showTab défini');
    </script>
</body>
</html>