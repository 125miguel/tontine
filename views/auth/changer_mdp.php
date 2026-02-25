<?php
session_start();

if(!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

require_once __DIR__ . '/../../config/database.php';

$error = '';
$success = '';

if($_SERVER['REQUEST_METHOD'] == 'POST') {
    $ancien = $_POST['ancien'] ?? '';
    $nouveau = $_POST['nouveau'] ?? '';
    $confirmation = $_POST['confirmation'] ?? '';
    
    if(empty($ancien) || empty($nouveau) || empty($confirmation)) {
        $error = "Tous les champs sont obligatoires";
    } elseif($nouveau != $confirmation) {
        $error = "Les nouveaux mots de passe ne correspondent pas";
    } elseif(strlen($nouveau) < 6) {
        $error = "Le mot de passe doit contenir au moins 6 caractères";
    } else {
        // Vérifier l'ancien mot de passe
        $database = new Database();
        $db = $database->getConnection();
        
        $query = "SELECT password FROM users WHERE id = :id";
        $stmt = $db->prepare($query);
        $stmt->execute(['id' => $_SESSION['user_id']]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if(password_verify($ancien, $user['password'])) {
            // Mettre à jour le mot de passe
            $hash = password_hash($nouveau, PASSWORD_DEFAULT);
            $query = "UPDATE users SET password = :password, premiere_connexion = 0 WHERE id = :id";
            $stmt = $db->prepare($query);
            if($stmt->execute(['password' => $hash, 'id' => $_SESSION['user_id']])) {
                $success = "Mot de passe modifié avec succès !";
                if(isset($_GET['first'])) {
                    header("refresh:3;url=../dashboard.php");
                }
            } else {
                $error = "Erreur lors de la modification";
            }
        } else {
            $error = "Ancien mot de passe incorrect";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Changer mon mot de passe</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
    <div class="container mt-5">
        <div class="row justify-content-center">
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header bg-primary text-white">
                        <h4 class="mb-0">
                            <?= isset($_GET['first']) ? 'Première connexion : ' : '' ?>
                            Changer mon mot de passe
                        </h4>
                    </div>
                    <div class="card-body">
                        <?php if(isset($_GET['first'])): ?>
                            <div class="alert alert-info">
                                Bienvenue ! Pour des raisons de sécurité, veuillez changer votre mot de passe.
                            </div>
                        <?php endif; ?>
                        
                        <?php if($error): ?>
                            <div class="alert alert-danger"><?= $error ?></div>
                        <?php endif; ?>
                        
                        <?php if($success): ?>
                            <div class="alert alert-success"><?= $success ?></div>
                            <p class="text-center">Redirection vers le tableau de bord...</p>
                        <?php else: ?>
                            <form method="POST">
                                <div class="mb-3">
                                    <label class="form-label">Ancien mot de passe</label>
                                    <input type="password" name="ancien" class="form-control" required>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Nouveau mot de passe</label>
                                    <input type="password" name="nouveau" class="form-control" required>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Confirmer le nouveau</label>
                                    <input type="password" name="confirmation" class="form-control" required>
                                </div>
                                <button type="submit" class="btn btn-primary w-100">
                                    Changer mon mot de passe
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