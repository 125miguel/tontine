<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../models/User.php';
require_once __DIR__ . '/../../models/PasswordReset.php';

$message = '';
$error = '';

if($_SERVER['REQUEST_METHOD'] == 'POST') {
    $email = $_POST['email'] ?? '';
    
    if(empty($email)) {
        $error = "Veuillez saisir votre email";
    } else {
        $database = new Database();
        $db = $database->getConnection();
        
        // Vérifier si l'email existe
        $query = "SELECT id FROM users WHERE email = :email";
        $stmt = $db->prepare($query);
        $stmt->execute(['email' => $email]);
        
        if($stmt->rowCount() > 0) {
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            
            // Créer un token
            $passwordReset = new PasswordReset($db);
            $token = $passwordReset->createToken($user['id']);
            
            if($token) {
                // Ici, tu enverras un email avec le lien
                // Pour l'instant, on affiche juste le lien (à des fins de test)
                $reset_link = "http://localhost/tontine/views/auth/reset_mot_de_passe.php?token=" . $token;
                $message = "Un email a été envoyé à $email avec les instructions.";
                
                // En mode test, on affiche le lien (à supprimer en production)
                $debug_link = $reset_link;
            } else {
                $error = "Erreur lors de la génération du token";
            }
        } else {
            $error = "Aucun compte trouvé avec cet email";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mot de passe oublié</title>
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
                <h2>Mot de passe oublié</h2>
                <p class="mb-0">Saisissez votre email pour réinitialiser votre mot de passe</p>
            </div>
            <div class="card-body">
                
                <?php if($error): ?>
                    <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
                <?php endif; ?>
                
                <?php if($message): ?>
                    <div class="alert alert-success">
                        <?= htmlspecialchars($message) ?>
                        <?php if(isset($debug_link)): ?>
                            <hr>
                            <small>Lien de test : <a href="<?= $debug_link ?>"><?= $debug_link ?></a></small>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>

                <form method="POST">
                    <div class="mb-4">
                        <label class="form-label">Votre email</label>
                        <input type="email" name="email" class="form-control" 
                               placeholder="exemple@email.com" required>
                    </div>
                    
                    <button type="submit" class="btn btn-primary">
                        Envoyer les instructions
                    </button>
                    
                    <div class="text-center mt-3">
                        <a href="login.php">Retour à la connexion</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</body>
</html>