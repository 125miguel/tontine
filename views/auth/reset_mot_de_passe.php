<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../models/User.php';
require_once __DIR__ . '/../../models/PasswordReset.php';

$token = $_GET['token'] ?? '';
$error = '';
$success = '';

if(empty($token)) {
    header("Location: mot_de_passe_oublie.php");
    exit();
}

$database = new Database();
$db = $database->getConnection();

// Vérifier le token
$passwordReset = new PasswordReset($db);
$reset = $passwordReset->verifyToken($token);

if(!$reset) {
    $error = "Le lien de réinitialisation est invalide ou a expiré.";
}

if($_SERVER['REQUEST_METHOD'] == 'POST' && $reset) {
    $password = $_POST['password'] ?? '';
    $confirm = $_POST['confirm_password'] ?? '';
    
    if(empty($password) || empty($confirm)) {
        $error = "Veuillez remplir tous les champs";
    } elseif($password != $confirm) {
        $error = "Les mots de passe ne correspondent pas";
    } elseif(strlen($password) < 6) {
        $error = "Le mot de passe doit contenir au moins 6 caractères";
    } else {
        // Mettre à jour le mot de passe
        $hashed = password_hash($password, PASSWORD_DEFAULT);
        
        $query = "UPDATE users SET password = :password WHERE id = :id";
        $stmt = $db->prepare($query);
        
        if($stmt->execute([
            'password' => $hashed,
            'id' => $reset['user_id']
        ])) {
            // Marquer le token comme utilisé
            $passwordReset->markAsUsed($reset['id']);
            
            $success = "Mot de passe modifié avec succès !";
            // Rediriger vers login après 3 secondes
            header("refresh:3;url=login.php");
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
    <title>Réinitialiser mon mot de passe</title>
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
                <h2>Nouveau mot de passe</h2>
            </div>
            <div class="card-body">
                
                <?php if($error): ?>
                    <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
                <?php endif; ?>
                
                <?php if($success): ?>
                    <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
                    <p class="text-center">Redirection vers la page de connexion...</p>
                <?php endif; ?>

                <?php if($reset && !$success): ?>
                    <form method="POST">
                        <div class="mb-4">
                            <label class="form-label">Nouveau mot de passe</label>
                            <input type="password" name="password" class="form-control" 
                                   placeholder="Minimum 6 caractères" required>
                        </div>
                        
                        <div class="mb-4">
                            <label class="form-label">Confirmer le mot de passe</label>
                            <input type="password" name="confirm_password" class="form-control" 
                                   placeholder="Confirmer" required>
                        </div>
                        
                        <button type="submit" class="btn btn-primary">
                            Modifier mon mot de passe
                        </button>
                    </form>
                <?php endif; ?>
                
                <div class="text-center mt-3">
                    <a href="login.php">Retour à la connexion</a>
                </div>
            </div>
        </div>
    </div>
</body>
</html>