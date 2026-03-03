<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();

if(!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

require_once __DIR__ . '/../../config/database.php';

$database = new Database();
$db = $database->getConnection();

$error = '';
$success = '';

if($_SERVER['REQUEST_METHOD'] == 'POST') {
    $nouveau = $_POST['nouveau'] ?? '';
    $confirmation = $_POST['confirmation'] ?? '';
    
    if(empty($nouveau) || empty($confirmation)) {
        $error = "Tous les champs sont obligatoires";
    } elseif($nouveau != $confirmation) {
        $error = "Les mots de passe ne correspondent pas";
    } elseif(strlen($nouveau) < 6) {
        $error = "Le mot de passe doit contenir au moins 6 caractères";
    } else {
        // Mettre à jour directement
        $hash = password_hash($nouveau, PASSWORD_DEFAULT);
        $query = "UPDATE users SET password = :password, premiere_connexion = 0 WHERE id = :id";
        $stmt = $db->prepare($query);
        if($stmt->execute(['password' => $hash, 'id' => $_SESSION['user_id']])) {
            $success = "Mot de passe modifié avec succès !";
            unset($_SESSION['premiere_connexion']);
            header("refresh:2;url=../dashboard.php");
        } else {
            $error = "Erreur lors de la modification";
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
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .card {
            border-radius: 20px;
            border: none;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            max-width: 450px;
            width: 100%;
        }
        .card-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            text-align: center;
            padding: 30px 20px;
            border-radius: 20px 20px 0 0 !important;
        }
        .card-body {
            padding: 40px;
            background: white;
            border-radius: 0 0 20px 20px;
        }
        .btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border: none;
            padding: 12px;
            width: 100%;
        }
        .alert {
            border-radius: 10px;
        }
    </style>
</head>
<body>
    <div class="container d-flex justify-content-center">
        <div class="card">
            <div class="card-header">
                <h4>Changer mon mot de passe</h4>
                <?php if(isset($_GET['first'])): ?>
                    <p class="mb-0">Bienvenue ! Choisissez votre mot de passe permanent</p>
                <?php endif; ?>
            </div>
            <div class="card-body">
                
                <?php if($error): ?>
                    <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
                <?php endif; ?>
                
                <?php if($success): ?>
                    <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
                    <p class="text-center">Redirection vers le tableau de bord...</p>
                <?php else: ?>
                    <form method="POST">
                        <div class="mb-3">
                            <label class="form-label">Nouveau mot de passe</label>
                            <input type="password" name="nouveau" class="form-control" 
                                   placeholder="Minimum 6 caractères" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Confirmer le nouveau</label>
                            <input type="password" name="confirmation" class="form-control" 
                                   placeholder="Confirmer" required>
                        </div>
                        <button type="submit" class="btn btn-primary">
                            Changer mon mot de passe
                        </button>
                    </form>
                <?php endif; ?>
            </div>
        </div>
    </div>
</body>
</html>