<?php
session_start();

// Vérifier si l'utilisateur est connecté et est admin
if(!isset($_SESSION['user_id']) || $_SESSION['user_role'] != 'admin') {
    header("Location: ../auth/login.php");
    exit();
}

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../models/Tontine.php';

$error = '';
$success = '';

if($_SERVER['REQUEST_METHOD'] == 'POST') {
    
    $nom = $_POST['nom'] ?? '';
    $description = $_POST['description'] ?? '';
    $montant = $_POST['montant'] ?? '';
    $periodicite = $_POST['periodicite'] ?? '';
    $jour_reunion = $_POST['jour_reunion'] ?? '';
    $prochaine_reunion = $_POST['prochaine_reunion'] ?? '';
    
    if(empty($nom) || empty($montant) || empty($periodicite) || empty($jour_reunion) || empty($prochaine_reunion)) {
        $error = "Veuillez remplir tous les champs obligatoires";
    } else {
        $database = new Database();
        $db = $database->getConnection();
        
        $tontine = new Tontine($db);
        $tontine->nom = $nom;
        $tontine->description = $description;
        $tontine->montant_cotisation = $montant;
        $tontine->periodicite = $periodicite;
        $tontine->jour_reunion = $jour_reunion;
        $tontine->prochaine_reunion = $prochaine_reunion;
        $tontine->admin_id = $_SESSION['user_id'];
        
        if($tontine->create()) {
            $success = "Tontine créée avec succès !";
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
    <title>Créer une tontine</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.8.1/font/bootstrap-icons.css">
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary">
        <div class="container">
            <a class="navbar-brand" href="../dashboard.php">
                <i class="bi bi-bank2"></i> Tontine
            </a>
            <div class="navbar-nav ms-auto">
                <a class="nav-link" href="../dashboard.php">
                    <i class="bi bi-arrow-left"></i> Retour
                </a>
            </div>
        </div>
    </nav>

    <div class="container mt-5">
        <div class="row justify-content-center">
            <div class="col-md-8">
                <div class="card">
                    <div class="card-header bg-primary text-white">
                        <h4 class="mb-0"> Créer une nouvelle tontine</h4>
                    </div>
                    <div class="card-body">
                        
                        <?php if($error): ?>
                            <div class="alert alert-danger"><?= $error ?></div>
                        <?php endif; ?>
                        
                        <?php if($success): ?>
                            <div class="alert alert-success"><?= $success ?></div>
                        <?php endif; ?>

                        <form method="POST" action="">
                            <div class="mb-3">
                                <label class="form-label">Nom de la tontine *</label>
                                <input type="text" name="nom" class="form-control" required>
                            </div>

                            <div class="mb-3">
                                <label class="form-label">Description</label>
                                <textarea name="description" class="form-control" rows="3"></textarea>
                            </div>

                            <div class="mb-3">
                                <label class="form-label">Montant de la cotisation (FCFA) *</label>
                                <input type="number" name="montant" class="form-control" required>
                            </div>

                            <div class="mb-3">
                                <label class="form-label">Périodicité *</label>
                                <select name="periodicite" class="form-control" required>
                                    <option value="hebdomadaire">Hebdomadaire</option>
                                    <option value="mensuel">Mensuel</option>
                                    <option value="journalier">Journalier</option>
                                </select>
                            </div>

                            <div class="mb-3">
                                <label class="form-label">Jour de réunion *</label>
                                <input type="text" name="jour_reunion" class="form-control" 
                                       placeholder="Ex: Samedi, 15 du mois" required>
                            </div>

                            <div class="mb-3">
                                <label class="form-label">Prochaine réunion *</label>
                                <input type="date" name="prochaine_reunion" class="form-control" required>
                            </div>

                            <button type="submit" class="btn btn-primary w-100">
                                Créer la tontine
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>