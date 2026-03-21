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

$database = new Database();
$db = $database->getConnection();

$membre_id = $_GET['membre_id'] ?? 0;
$tontine_id = $_GET['tontine_id'] ?? 0;

if(!$membre_id || !$tontine_id) {
    header("Location: mes_tontines.php");
    exit();
}

// Vérifier que la tontine appartient à cet admin
$tontine = new Tontine($db);
if(!$tontine->getById($tontine_id) || $tontine->admin_id != $_SESSION['user_id']) {
    header("Location: mes_tontines.php");
    exit();
}

// Récupérer les infos du membre
$query = "SELECT mt.*, u.nom, u.prenom 
          FROM membre_tontine mt
          JOIN users u ON mt.user_id = u.id
          WHERE mt.id = :mid AND mt.tontine_id = :tid";
$stmt = $db->prepare($query);
$stmt->execute(['mid' => $membre_id, 'tid' => $tontine_id]);
$membre = $stmt->fetch(PDO::FETCH_ASSOC);

if(!$membre) {
    header("Location: ajouter_membre.php?id=" . $tontine_id);
    exit();
}

$error = '';
$success = '';

// Traitement du formulaire
if($_SERVER['REQUEST_METHOD'] == 'POST') {
    $date_anniversaire = $_POST['date_anniversaire'] ?? '';
    
    if(empty($date_anniversaire)) {
        $error = "Veuillez saisir la date d'anniversaire";
    } else {
        $query = "UPDATE membre_tontine SET date_anniversaire = :date WHERE id = :id";
        $stmt = $db->prepare($query);
        
        if($stmt->execute(['date' => $date_anniversaire, 'id' => $membre_id])) {
            $_SESSION['success_message'] = "Date d'anniversaire enregistrée avec succès !";
            header("Location: ajouter_membre.php?id=" . $tontine_id . "&mode=manuel&birthday_saved=1");
            exit();
        } else {
            $error = "Erreur lors de l'enregistrement";
        }
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
    <title>Saisir date d'anniversaire</title>
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
            --warning: #F59E0B;
            --warning-bg: #FEF3C7;
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
        
        .container {
            max-width: 600px;
            margin: 50px auto;
            padding: 0 20px;
        }
        
        .card {
            background: var(--white);
            border-radius: 15px;
            border: 1px solid var(--border);
            box-shadow: 0 5px 20px rgba(0,0,0,0.05);
        }
        
        .card-header {
            background: var(--primary);
            color: var(--white);
            border-radius: 15px 15px 0 0 !important;
            padding: 20px;
        }
        
        .card-body {
            padding: 30px;
        }
        
        .form-control {
            border-radius: 10px;
            border: 2px solid var(--border);
            padding: 12px;
            font-size: 16px;
        }
        
        .form-control:focus {
            border-color: var(--primary);
            box-shadow: none;
            outline: none;
        }
        
        .btn-primary {
            background: var(--primary);
            border: none;
            padding: 12px;
            border-radius: 10px;
            font-weight: 600;
            width: 100%;
        }
        
        .btn-primary:hover {
            background: var(--primary-light);
        }
        
        .btn-outline-secondary {
            border: 2px solid var(--text-light);
            color: var(--text-light);
            padding: 10px 20px;
            border-radius: 10px;
            text-decoration: none;
        }
        
        .btn-outline-secondary:hover {
            background: var(--text-light);
            color: var(--white);
        }
        
        .alert-danger {
            background: #FEE2E2;
            color: #991B1B;
            border: none;
            border-radius: 10px;
            padding: 15px;
        }
        
        .alert-success {
            background: #D1FAE5;
            color: #065F46;
            border: none;
            border-radius: 10px;
        }
        
        .alert-warning {
            background: var(--warning-bg);
            color: #92400E;
            border: none;
            border-radius: 10px;
        }
        
        .birthday-icon {
            color: var(--warning);
            font-size: 48px;
            margin-bottom: 15px;
        }
        
        .member-name {
            font-size: 24px;
            font-weight: 600;
            color: var(--primary);
            margin-bottom: 5px;
        }
        
        .success-message {
            background: #D1FAE5;
            color: #065F46;
            padding: 15px;
            border-radius: 10px;
            margin-bottom: 20px;
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
                <a class="nav-link" href="ajouter_membre.php?id=<?= $tontine_id ?>&mode=manuel">
                    <i class="bi bi-arrow-left me-1"></i> Retour
                </a>
            </div>
        </div>
    </nav>

    <div class="container">
        <div class="card">
            <div class="card-header text-center">
                <i class="bi bi-gift" style="font-size: 32px;"></i>
                <h4 class="mt-2">Date d'anniversaire</h4>
            </div>
            <div class="card-body">
                
                <?php if(isset($_GET['added']) && $_GET['added'] == 1): ?>
                    <div class="success-message">
                        <i class="bi bi-check-circle"></i> Membre ajouté avec succès !
                    </div>
                <?php endif; ?>

                <div class="text-center mb-4">
                    <div class="birthday-icon">
                        <i class="bi bi-calendar-heart"></i>
                    </div>
                    <div class="member-name">
                        <?= htmlspecialchars($membre['prenom'] . ' ' . $membre['nom']) ?>
                    </div>
                    <p class="text-muted">
                        Ce membre a été ajouté à la tontine. Veuillez saisir sa date d'anniversaire pour le classement.
                    </p>
                </div>

                <?php if($error): ?>
                    <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
                <?php endif; ?>

                <form method="POST">
                    <div class="mb-4">
                        <label class="form-label fw-bold">Date d'anniversaire</label>
                        <input type="date" name="date_anniversaire" class="form-control" 
                               value="<?= date('Y-m-d') ?>" required>
                        <div class="form-text">
                            Cette date sera utilisée pour classer les bénéficiaires du plus proche au plus éloigné.
                        </div>
                    </div>

                    <button type="submit" class="btn btn-primary mb-3">
                        <i class="bi bi-check-circle"></i> Enregistrer la date
                    </button>

                    <div class="text-center">
                        <a href="ajouter_membre.php?id=<?= $tontine_id ?>&mode=manuel" class="btn btn-outline-secondary">
                            <i class="bi bi-x-circle"></i> Passer pour l'instant
                        </a>
                    </div>
                </form>

                <div class="alert alert-warning mt-4">
                    <i class="bi bi-info-circle"></i>
                    <strong>Note :</strong> Si vous ne saisissez pas la date maintenant, vous pourrez le faire plus tard 
                    depuis la page des membres. Mais le classement automatique ne pourra pas se faire sans cette information.
                </div>
            </div>
        </div>
    </div>
</body>
</html>