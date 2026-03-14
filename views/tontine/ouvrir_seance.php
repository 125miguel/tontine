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
require_once __DIR__ . '/../../models/Seance.php';
require_once __DIR__ . '/../../models/Cotisation.php';
require_once __DIR__ . '/../../models/MembreTontine.php';

$database = new Database();
$db = $database->getConnection();

$tontine_id = $_GET['id'] ?? 0;

// Vérifier que la tontine appartient bien à cet admin
$tontine = new Tontine($db);
if(!$tontine->getById($tontine_id) || $tontine->admin_id != $_SESSION['user_id']) {
    header("Location: mes_tontines.php");
    exit();
}

$seance = new Seance($db);
$cotisation = new Cotisation($db);
$membreTontine = new MembreTontine($db);

$error = '';
$success = '';

// Vérifier si une séance est déjà ouverte
$seance_active = false;
$seance_data = null;

if($seance->getSeanceActive($tontine_id)) {
    $seance_active = true;
    $seance_data = [
        'id' => $seance->id,
        'date_seance' => $seance->date_seance,
    ];
}

// Si le formulaire est soumis pour ouvrir une séance
if($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['ouvrir_seance'])) {
    
    $date_seance = $_POST['date_seance'] ?? date('Y-m-d');
    $nom_reunion = $_POST['nom_reunion'] ?? null;
    
    // Vérifier si une séance est déjà ouverte
    if($seance->aSeanceActive($tontine_id)) {
        $error = "Une séance est déjà ouverte pour cette tontine. Veuillez la clôturer d'abord.";
    } else {
        // Créer la séance
        $seance->tontine_id = $tontine_id;
        $seance->date_seance = $date_seance;
        
        if($seance->create()) {
            // Initialiser les présences
           require_once __DIR__ . '/../../models/Presence.php';
           $presence = new Presence($db);
           $presence->initPresences($seance->id, $tontine_id);
            // Initialiser les cotisations pour tous les membres
            if($cotisation->initCotisationsPourSeance($seance->id, $tontine_id, $tontine->montant_cotisation)) {
                $success = "Séance ouverte avec succès !";
                // Recharger la séance active
                $seance->getSeanceActive($tontine_id);
                $seance_active = true;
                $seance_data = [
                    'id' => $seance->id,
                    'date_seance' => $seance->date_seance,
                ];
            } else {
                $error = "Erreur lors de l'initialisation des cotisations";
            }
        } else {
            $error = "Erreur lors de l'ouverture de la séance";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ouvrir une séance</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.8.1/font/bootstrap-icons.css">
    <style>
        :root {
            --primary: #1E3A8A;        /* Bleu sombre */
            --primary-light: #3B5BA5;   /* Bleu plus clair */
            --white: #FFFFFF;
            --bg-light: #F8FAFC;
            --text-dark: #0F172A;
            --text-light: #475569;
            --border: #E2E8F0;
            --danger: #EF4444;
            --success: #10B981;
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
        
        .alert-danger {
            background: #FEE2E2;
            color: #991B1B;
            border: none;
            border-radius: 10px;
        }
        
        .alert-success {
            background: #D1FAE5;
            color: #065F46;
            border: none;
            border-radius: 10px;
        }
        
        .alert-info {
            background: #DBEAFE;
            color: var(--primary);
            border: none;
            border-radius: 10px;
        }
        
        .form-control {
            border-radius: 10px;
            border: 2px solid var(--border);
            padding: 12px;
            transition: all 0.3s;
        }
        
        .form-control:focus {
            border-color: var(--primary);
            box-shadow: none;
            outline: none;
        }
        
        .text-muted {
            color: var(--text-light) !important;
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
                <a class="nav-link" href="mes_tontines.php">
                    <i class="bi bi-arrow-left"></i> Retour
                </a>
            </div>
        </div>
    </nav>

    <div class="container mt-5">
        <div class="row">
            <div class="col-md-8 offset-md-2">
                <div class="card">
                    <div class="card-header">
                        <h4 class="mb-0"><i class="bi bi-calendar-plus"></i> Ouvrir une séance pour "<?= htmlspecialchars($tontine->nom) ?>"</h4>
                    </div>
                    <div class="card-body">
                        
                        <?php if($error): ?>
                            <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
                        <?php endif; ?>
                        
                        <?php if($success): ?>
                            <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
                        <?php endif; ?>

                        <?php if($seance_active && $seance_data): ?>
                            <div class="alert alert-info">
                                <strong><i class="bi bi-check-circle"></i> Séance active</strong><br>
                                <?php if(!empty($seance_data['nom_reunion'])): ?>
                                    <strong><?= htmlspecialchars($seance_data['nom_reunion']) ?></strong><br>
                                <?php endif; ?>
                                Date: <?= date('d/m/Y', strtotime($seance_data['date_seance'])) ?><br>
                                <a href="gerer_cotisations.php?seance_id=<?= $seance_data['id'] ?>" class="btn btn-primary mt-2">
                                    <i class="bi bi-pencil-square"></i> Gérer les cotisations
                                </a>
                            </div>
                        <?php else: ?>
                            <form method="POST" action="">

                                <div class="mb-3">
                                    <label class="form-label">Date de la séance</label>
                                    <input type="date" name="date_seance" class="form-control" 
                                        value="<?= date('Y-m-d') ?>" required>
                                    <small class="text-muted">
                                        <i class="bi bi-info-circle"></i> 
                                        Après clôture, la prochaine réunion sera automatiquement calculée selon la périodicité.
                                        <?php if($tontine->periodicite == 'hebdomadaire'): ?>
                                            (Prochaine : +7 jours)
                                        <?php elseif($tontine->periodicite == 'mensuel'): ?>
                                            (Prochaine : +1 mois)
                                        <?php elseif($tontine->periodicite == 'journalier'): ?>
                                            (Prochaine : +1 jour)
                                        <?php endif; ?>
                                    </small>
                                </div>

                                <button type="submit" name="ouvrir_seance" class="btn btn-success w-100">
                                    <i class="bi bi-play-circle"></i> Ouvrir la séance
                                </button>
                            </form>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>