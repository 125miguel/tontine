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

$database = new Database();
$db = $database->getConnection();

$tontine_id = $_GET['tontine_id'] ?? 0;

if(!$tontine_id) {
    header("Location: mes_tontines.php");
    exit();
}

$tontine = new Tontine($db);
if(!$tontine->getById($tontine_id) || $tontine->admin_id != $_SESSION['user_id']) {
    header("Location: mes_tontines.php");
    exit();
}

// Vérifier que c'est bien une tontine anniversaire
if($tontine->type_tontine != 'anniversaire') {
    header("Location: voir_membres.php?id=" . $tontine_id . "&error=not_anniversaire");
    exit();
}

$membreTontine = new MembreTontine($db);

// Récupérer tous les membres avec leur date d'anniversaire
$query = "SELECT mt.id, u.nom, u.prenom, mt.date_anniversaire,
                 DAY(mt.date_anniversaire) as jour,
                 MONTH(mt.date_anniversaire) as mois
          FROM membre_tontine mt
          JOIN users u ON mt.user_id = u.id
          WHERE mt.tontine_id = :tid AND mt.est_actif = 1
          ORDER BY u.nom, u.prenom";
$stmt = $db->prepare($query);
$stmt->execute(['tid' => $tontine_id]);
$membres = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Vérifier que tous les membres ont une date d'anniversaire
$membres_sans_date = [];
foreach($membres as $m) {
    if(empty($m['date_anniversaire'])) {
        $membres_sans_date[] = $m;
    }
}

// Traitement du classement
if($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['classer'])) {
    
    // Date de référence (aujourd'hui)
    $date_reference = new DateTime();
    $aujourdhui = $date_reference->format('Y-m-d');
    
    // Récupérer tous les membres avec leur date d'anniversaire
    $query = "SELECT mt.id, mt.date_anniversaire,
                     DAY(mt.date_anniversaire) as jour,
                     MONTH(mt.date_anniversaire) as mois
              FROM membre_tontine mt
              WHERE mt.tontine_id = :tid AND mt.est_actif = 1
              AND mt.date_anniversaire IS NOT NULL";
    $stmt = $db->prepare($query);
    $stmt->execute(['tid' => $tontine_id]);
    $membres_a_classer = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Tableau pour stocker les membres avec leur prochain anniversaire
    $prochains_anniversaires = [];
    
    foreach($membres_a_classer as $m) {
        // Extraire le jour et le mois
        $jour = $m['jour'];
        $mois = $m['mois'];
        
        // Créer la date du prochain anniversaire
        $annee_courante = (int)date('Y');
        $prochain_anniversaire = new DateTime("$annee_courante-$mois-$jour");
        
        // Si l'anniversaire est déjà passé cette année, prendre l'année prochaine
        if($prochain_anniversaire < new DateTime()) {
            $prochain_anniversaire->modify('+1 year');
        }
        
        // Calculer le nombre de jours jusqu'au prochain anniversaire
        $aujourdhui = new DateTime();
        $jours_restants = $aujourdhui->diff($prochain_anniversaire)->days;
        
        $prochains_anniversaires[] = [
            'id' => $m['id'],
            'date_anniversaire' => $m['date_anniversaire'],
            'prochain_anniversaire' => $prochain_anniversaire->format('Y-m-d'),
            'jours_restants' => $jours_restants
        ];
    }

    // Trier par nombre de jours restants (du plus proche au plus éloigné)
    usort($prochains_anniversaires, function($a, $b) {
        return $a['jours_restants'] - $b['jours_restants'];
    });

    // Mettre à jour l'ordre des membres
    $ordre = 1;
    $success = true;
    
    foreach($prochains_anniversaires as $p) {
        $query = "UPDATE membre_tontine SET ordre_tour = :ordre WHERE id = :id";
        $stmt = $db->prepare($query);
        $stmt->execute([
            'ordre' => $ordre,
            'id' => $p['id']
        ]);
        
        if($stmt->rowCount() == 0) {
            $success = false;
        }
        
        $ordre++;
    }
    
    if($success) {
        $_SESSION['success_message'] = "Classement par anniversaire effectué avec succès !";
        header("Location: voir_membres.php?id=" . $tontine_id . "&classement=ok");
        exit();
    } else {
        $error = "Erreur lors du classement";
    }
}

// Récupérer l'association
$query = "SELECT nom FROM associations WHERE id = :id";
$stmt = $db->prepare($query);
$stmt->execute(['id' => $_SESSION['association_active']]);
$association_nom = $stmt->fetch()['nom'];
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Classement par anniversaire - <?= htmlspecialchars($tontine->nom) ?></title>
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
            box-shadow: 0 2px 20px rgba(0,0,0,0.1);
        }
        
        .navbar-brand, .nav-link {
            color: var(--white) !important;
        }
        
        .navbar-brand {
            font-size: 24px;
            font-weight: 700;
        }
        
        .container {
            max-width: 900px;
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
            border-radius: 15px;
            border: 1px solid var(--border);
            box-shadow: 0 5px 20px rgba(0,0,0,0.05);
            margin-bottom: 25px;
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
        
        .card-header.bg-warning {
            background: var(--warning) !important;
            color: var(--text-dark);
        }
        
        .card-body {
            padding: 25px;
        }
        
        .info-box {
            background: var(--info-bg);
            border-left: 4px solid var(--primary);
            padding: 15px;
            border-radius: 10px;
            margin-bottom: 20px;
        }
        
        .warning-box {
            background: var(--warning-bg);
            border-left: 4px solid var(--warning);
            padding: 15px;
            border-radius: 10px;
            margin-bottom: 20px;
        }
        
        .success-box {
            background: var(--success-bg);
            border-left: 4px solid var(--success);
            padding: 15px;
            border-radius: 10px;
            margin-bottom: 20px;
        }
        
        .member-list {
            margin: 20px 0;
        }
        
        .member-item {
            display: flex;
            align-items: center;
            padding: 12px;
            border: 1px solid var(--border);
            border-radius: 10px;
            margin-bottom: 8px;
            background: var(--white);
            transition: all 0.2s;
        }
        
        .member-item:hover {
            border-color: var(--primary);
            box-shadow: 0 2px 10px rgba(30, 58, 138, 0.1);
        }
        
        .member-avatar {
            width: 45px;
            height: 45px;
            background: var(--primary);
            color: var(--white);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            font-size: 18px;
            margin-right: 15px;
        }
        
        .member-info {
            flex: 1;
        }
        
        .member-name {
            font-weight: 600;
            color: var(--text-dark);
        }
        
        .member-date {
            font-size: 13px;
            color: var(--text-light);
        }
        
        .member-date i {
            color: var(--warning);
            margin-right: 5px;
        }
        
        .btn-primary {
            background: var(--primary);
            border: none;
            padding: 12px 30px;
            border-radius: 10px;
            font-weight: 600;
            transition: all 0.3s;
        }
        
        .btn-primary:hover {
            background: var(--primary-light);
            transform: translateY(-2px);
            box-shadow: 0 10px 30px rgba(30, 58, 138, 0.3);
        }
        
        .btn-warning {
            background: var(--warning);
            border: none;
            color: var(--text-dark);
            padding: 12px 30px;
            border-radius: 10px;
            font-weight: 600;
        }
        
        .btn-warning:hover {
            background: #D97706;
            color: var(--white);
            transform: translateY(-2px);
        }
        
        .btn-outline-secondary {
            border: 2px solid var(--text-light);
            color: var(--text-light);
            background: transparent;
            padding: 10px 25px;
            border-radius: 10px;
            font-weight: 600;
        }
        
        .btn-outline-secondary:hover {
            background: var(--text-light);
            color: var(--white);
        }
        
        .badge {
            padding: 5px 12px;
            border-radius: 50px;
            font-size: 12px;
            font-weight: 600;
        }
        
        .badge-warning {
            background: var(--warning-bg);
            color: #92400E;
        }
        
        .badge-success {
            background: var(--success-bg);
            color: #065F46;
        }
        
        .badge-danger {
            background: var(--danger-bg);
            color: #991B1B;
        }
        
        .preview-order {
            background: var(--bg-light);
            border-radius: 10px;
            padding: 15px;
            margin-top: 20px;
        }
        
        .order-number {
            display: inline-block;
            width: 30px;
            height: 30px;
            background: var(--primary);
            color: var(--white);
            border-radius: 50%;
            text-align: center;
            line-height: 30px;
            font-weight: bold;
            margin-right: 10px;
        }
    </style>
</head>
<body>
    <nav class="navbar navbar-expand-lg">
        <div class="container">
            <a class="navbar-brand" href="../dashboard.php">
                <i class="bi bi-bank2 me-2"></i>TONTONTINE
            </a>
            <div class="navbar-nav ms-auto">
                <span class="nav-link">
                    <i class="bi bi-building me-1"></i> <?= htmlspecialchars($association_nom) ?>
                </span>
                <span class="nav-link">
                    <span class="badge-warning">
                        <i class="bi bi-gift"></i> Anniversaire
                    </span>
                </span>
                <a class="nav-link" href="voir_membres.php?id=<?= $tontine_id ?>">
                    <i class="bi bi-arrow-left me-1"></i> Retour
                </a>
            </div>
        </div>
    </nav>

    <div class="container">
        <div class="page-header">
            <h2><i class="bi bi-gift"></i> Classement par anniversaire</h2>
            <p><?= htmlspecialchars($tontine->nom) ?> - Triez les membres par date d'anniversaire la plus proche</p>
        </div>

        <!-- Vérification des dates d'anniversaire -->
        <?php if(!empty($membres_sans_date)): ?>
            <div class="warning-box">
                <h5><i class="bi bi-exclamation-triangle"></i> Dates d'anniversaire manquantes</h5>
                <p>Les membres suivants n'ont pas de date d'anniversaire renseignée :</p>
                <ul>
                    <?php foreach($membres_sans_date as $m): ?>
                        <li><?= htmlspecialchars($m['prenom'] . ' ' . $m['nom']) ?></li>
                    <?php endforeach; ?>
                </ul>
                <p>Veuillez d'abord saisir leur date d'anniversaire avant de procéder au classement.</p>
                <a href="ajouter_membre.php?id=<?= $tontine_id ?>" class="btn btn-warning">
                    <i class="bi bi-pencil"></i> Modifier les membres
                </a>
            </div>
        <?php else: ?>

            <!-- Explication -->
            <div class="info-box">
                <i class="bi bi-info-circle"></i>
                <strong>Comment ça marche ?</strong>
                <p class="mb-0 mt-2">
                    Le système va classer automatiquement les membres en fonction de la <strong>date d'anniversaire la plus proche</strong>.
                    Le membre dont l'anniversaire arrive en premier sera le premier bénéficiaire.
                </p>
            </div>

            <!-- Aperçu du classement -->
            <div class="card">
                <div class="card-header">
                    <i class="bi bi-sort-numeric-down"></i> Aperçu du classement à venir
                </div>
                <div class="card-body">
                    
                    <?php
                    // Calculer le classement pour l'aperçu
                    $date_reference = new DateTime();
                    $aujourdhui = $date_reference->format('Y-m-d');
                    
                    $apercu = [];
                    foreach($membres as $m) {
                        if(empty($m['date_anniversaire'])) continue;
                        
                        $jour = $m['jour'];
                        $mois = $m['mois'];
                        $annee_courante = (int)date('Y');
                        $prochain = new DateTime("$annee_courante-$mois-$jour");
                        
                        if($prochain < new DateTime()) {
                            $prochain->modify('+1 year');
                        }
                        
                        $jours_restants = (new DateTime())->diff($prochain)->days;
                        
                        $apercu[] = [
                            'nom' => $m['prenom'] . ' ' . $m['nom'],
                            'date' => $m['date_anniversaire'],
                            'prochain' => $prochain->format('d/m/Y'),
                            'jours' => $jours_restants
                        ];
                    }
                    
                    // Trier par jours restants
                    usort($apercu, function($a, $b) {
                        return $a['jours'] - $b['jours'];
                    });
                    ?>

                    <div class="preview-order">
                        <h6 class="mb-3">Ordre de bénéficiaires (du plus proche au plus éloigné) :</h6>
                        
                        <?php foreach($apercu as $index => $a): ?>
                            <div class="d-flex align-items-center mb-2 p-2 <?= $index == 0 ? 'bg-warning bg-opacity-10 rounded' : '' ?>">
                                <span class="order-number"><?= $index + 1 ?></span>
                                <div class="flex-grow-1">
                                    <strong><?= htmlspecialchars($a['nom']) ?></strong>
                                    <br>
                                    <small class="text-muted">
                                        Anniversaire : <?= date('d/m', strtotime($a['date'])) ?> - 
                                        Prochain : <?= $a['prochain'] ?> 
                                        (dans <?= $a['jours'] ?> jour<?= $a['jours'] > 1 ? 's' : '' ?>)
                                    </small>
                                </div>
                                <?php if($index == 0): ?>
                                    <span class="badge-warning">Prochain bénéficiaire</span>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>

                    <?php if(isset($error)): ?>
                        <div class="alert alert-danger mt-3"><?= htmlspecialchars($error) ?></div>
                    <?php endif; ?>

                    <form method="POST" class="text-center mt-4">
                        <input type="hidden" name="classer" value="1">
                        
                        <p class="text-muted mb-3">
                            <i class="bi bi-exclamation-triangle text-warning"></i>
                            Cette action va définir l'ordre définitif des bénéficiaires en fonction des anniversaires.
                        </p>
                        
                        <button type="submit" class="btn btn-primary btn-lg" 
                                onclick="return confirm('Confirmez-vous le classement par anniversaire ?\nCette action définira l\'ordre des bénéficiaires.')">
                            <i class="bi bi-check-circle"></i> Confirmer le classement
                        </button>
                        
                        <a href="voir_membres.php?id=<?= $tontine_id ?>" class="btn btn-outline-secondary btn-lg ms-2">
                            <i class="bi bi-x-circle"></i> Annuler
                        </a>
                    </form>
                </div>
            </div>

            <!-- Informations complémentaires -->
            <div class="card mt-3">
                <div class="card-header bg-warning">
                    <i class="bi bi-question-circle"></i> Comment est calculé l'ordre ?
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <h6><i class="bi bi-calendar"></i> Règle de calcul</h6>
                            <p>Le système prend la date d'aujourd'hui et calcule pour chaque membre le nombre de jours jusqu'à son prochain anniversaire.</p>
                            <p>Les membres sont ensuite triés du plus petit nombre de jours au plus grand.</p>
                        </div>
                        <div class="col-md-6">
                            <h6><i class="bi bi-arrow-repeat"></i> Gestion des dates passées</h6>
                            <p>Si l'anniversaire de l'année est déjà passé, le système prend automatiquement l'anniversaire de l'année prochaine.</p>
                            <p>Exemple : Si on est en décembre, l'anniversaire de janvier est considéré comme "dans 1 mois".</p>
                        </div>
                    </div>
                </div>
            </div>

        <?php endif; ?>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>