<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();

// Vérifier si l'utilisateur est connecté et est admin
if(!isset($_SESSION['user_id']) || $_SESSION['user_role'] != 'admin') {
    header("Location: ../auth/login.php");
    exit();
}

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../models/Tontine.php';

$database = new Database();
$db = $database->getConnection();

// Récupérer l'association du président
$query = "SELECT id, nom FROM associations WHERE admin_id = :admin_id";
$stmt = $db->prepare($query);
$stmt->execute(['admin_id' => $_SESSION['user_id']]);
$association = $stmt->fetch(PDO::FETCH_ASSOC);

if(!$association) {
    // Normalement ça n'arrive pas, mais au cas où
    header("Location: mes_tontines.php?error=no_association");
    exit();
}

$error = '';
$success = '';

if($_SERVER['REQUEST_METHOD'] == 'POST') {
    
    $nom = $_POST['nom'] ?? '';
    $description = $_POST['description'] ?? '';
    $type_tontine = $_POST['type_tontine'] ?? 'anniversaire';
    $mode_beneficiaire = $_POST['mode_beneficiaire'] ?? 'manuel';
    $montant = $_POST['montant'] ?? '';
    $periodicite = $_POST['periodicite'] ?? '';
    $jour_reunion = $_POST['jour_reunion'] ?? '';
    $prochaine_reunion = $_POST['prochaine_reunion'] ?? '';
    
    if(empty($nom) || empty($montant) || empty($periodicite) || empty($jour_reunion) || empty($prochaine_reunion)) {
        $error = "Veuillez remplir tous les champs obligatoires";
    } else {
        $tontine = new Tontine($db);
        $tontine->nom = $nom;
        $tontine->description = $description;
        $tontine->type_tontine = $type_tontine;
        $tontine->mode_beneficiaire = $mode_beneficiaire;
        $tontine->montant_cotisation = $montant;
        $tontine->periodicite = $periodicite;
        $tontine->jour_reunion = $jour_reunion;
        $tontine->prochaine_reunion = $prochaine_reunion;
        $tontine->admin_id = $_SESSION['user_id'];
        $tontine->association_id = $association['id'];  // Lier à l'association
        
        if($tontine->create()) {
            $_SESSION['tontine_created'] = "Tontine créée avec succès !";
            header("Location: mes_tontines.php");
            exit();
        } else {
            $error = "Erreur lors de la création";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Créer une tontine - <?= htmlspecialchars($association['nom']) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.8.1/font/bootstrap-icons.css">
    <style>
        body {
            background: linear-gradient(135deg, #f5f0ff 0%, #fff5f0 100%);
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        .navbar {
            background: linear-gradient(135deg, #6B46C1 0%, #FF8A4C 100%);
        }
        .card {
            border-radius: 15px;
            border: none;
            box-shadow: 0 10px 40px rgba(107, 70, 193, 0.1);
        }
        .card-header {
            background: linear-gradient(135deg, #6B46C1 0%, #FF8A4C 100%);
            color: white;
            border-radius: 15px 15px 0 0 !important;
        }
        .form-control, .form-select {
            border-radius: 10px;
            border: 2px solid #e0e0e0;
            padding: 12px;
            transition: all 0.3s;
        }
        .form-control:focus, .form-select:focus {
            border-color: #6B46C1;
            box-shadow: none;
        }
        .btn-primary {
            background: linear-gradient(135deg, #6B46C1 0%, #FF8A4C 100%);
            border: none;
            padding: 12px;
            border-radius: 10px;
            font-weight: 600;
            transition: all 0.3s;
        }
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 30px rgba(107, 70, 193, 0.3);
        }
        .badge-association {
            background: rgba(255,255,255,0.2);
            color: white;
            padding: 5px 15px;
            border-radius: 50px;
            font-size: 14px;
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
                <span class="nav-link text-white">
                    <i class="bi bi-building"></i> <?= htmlspecialchars($association['nom']) ?>
                </span>
                <a class="nav-link text-white" href="mes_tontines.php">
                    <i class="bi bi-arrow-left"></i> Retour
                </a>
            </div>
        </div>
    </nav>

    <div class="container mt-5">
        <div class="row justify-content-center">
            <div class="col-md-8">
                <div class="card">
                    <div class="card-header">
                        <h4 class="mb-0"><i class="bi bi-plus-circle"></i> Créer une nouvelle tontine</h4>
                    </div>
                    <div class="card-body">
                        
                        <?php if($error): ?>
                            <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
                        <?php endif; ?>
                        
                        <?php if($success): ?>
                            <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
                        <?php endif; ?>

                        <form method="POST" action="">
                            <div class="mb-3">
                                <label class="form-label">Nom de la tontine <span class="text-danger">*</span></label>
                                <input type="text" name="nom" class="form-control" 
                                       value="<?= htmlspecialchars($_POST['nom'] ?? '') ?>" required>
                            </div>

                            <div class="mb-3">
                                <label class="form-label">Description</label>
                                <textarea name="description" class="form-control" rows="3"><?= htmlspecialchars($_POST['description'] ?? '') ?></textarea>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Type de tontine</label>
                                    <select name="type_tontine" class="form-select">
                                        <option value="anniversaire" <?= ($_POST['type_tontine'] ?? '') == 'anniversaire' ? 'selected' : '' ?>> Anniversaire</option>
                                        <option value="djangui" <?= ($_POST['type_tontine'] ?? '') == 'djangui' ? 'selected' : '' ?>> Djangui</option>
                                        <option value="solidarite" <?= ($_POST['type_tontine'] ?? '') == 'solidarite' ? 'selected' : '' ?>> Solidarité</option>
                                        <option value="deuil" <?= ($_POST['type_tontine'] ?? '') == 'deuil' ? 'selected' : '' ?>> Deuil</option>
                                    </select>
                                </div>

                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Mode bénéficiaire</label>
                                    <select name="mode_beneficiaire" class="form-select">
                                        <option value="manuel" <?= ($_POST['mode_beneficiaire'] ?? '') == 'manuel' ? 'selected' : '' ?>>👤 Manuel</option>
                                        <option value="auto" <?= ($_POST['mode_beneficiaire'] ?? '') == 'auto' ? 'selected' : '' ?>>🤖 Automatique</option>
                                    </select>
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Montant (FCFA) <span class="text-danger">*</span></label>
                                    <input type="number" name="montant" class="form-control" 
                                           value="<?= htmlspecialchars($_POST['montant'] ?? '') ?>" required>
                                </div>

                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Périodicité <span class="text-danger">*</span></label>
                                    <select name="periodicite" class="form-select" required>
                                        <option value="hebdomadaire" <?= ($_POST['periodicite'] ?? '') == 'hebdomadaire' ? 'selected' : '' ?>>Hebdomadaire</option>
                                        <option value="mensuel" <?= ($_POST['periodicite'] ?? '') == 'mensuel' ? 'selected' : '' ?>>Mensuel</option>
                                        <option value="journalier" <?= ($_POST['periodicite'] ?? '') == 'journalier' ? 'selected' : '' ?>>Journalier</option>
                                    </select>
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Jour de réunion <span class="text-danger">*</span></label>
                                    <input type="text" name="jour_reunion" class="form-control" 
                                           value="<?= htmlspecialchars($_POST['jour_reunion'] ?? '') ?>"
                                           placeholder="Ex: Samedi, 15 du mois" required>
                                </div>

                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Prochaine réunion <span class="text-danger">*</span></label>
                                    <input type="date" name="prochaine_reunion" class="form-control" 
                                           value="<?= htmlspecialchars($_POST['prochaine_reunion'] ?? date('Y-m-d')) ?>" required>
                                </div>
                            </div>

                            <button type="submit" class="btn btn-primary w-100">
                                <i class="bi bi-plus-circle"></i> Créer la tontine
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>