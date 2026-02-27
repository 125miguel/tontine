<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();

if(!isset($_SESSION['reset_email'])) {
    header("Location: mot_de_passe_oublie.php");
    exit();
}

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../models/PasswordReset.php';

$error = '';
$success = '';

if($_SERVER['REQUEST_METHOD'] == 'POST') {
    $code = $_POST['code'] ?? '';
    
    if(empty($code)) {
        $error = "Veuillez saisir le code";
    } else {
        $database = new Database();
        $db = $database->getConnection();
        
        $passwordReset = new PasswordReset($db);
        $reset = $passwordReset->verifyCode($_SESSION['reset_email'], $code);
        
        if($reset) {
            $_SESSION['reset_user_id'] = $reset['user_id'];
            $_SESSION['reset_code_id'] = $reset['id'];
            header("Location: nouveau_mot_de_passe.php");
            exit();
        } else {
            $error = "Code invalide ou expiré";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Saisir le code</title>
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
        .code-input {
            font-size: 24px;
            letter-spacing: 10px;
            text-align: center;
            font-weight: bold;
        }
        .btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border: none;
            padding: 12px;
            width: 100%;
        }
    </style>
</head>
<body>
    <div class="container d-flex justify-content-center">
        <div class="card">
            <div class="card-header">
                <h2>Saisissez votre code</h2>
                <p class="mb-0">Un code à 6 chiffres vous a été envoyé par email</p>
            </div>
            <div class="card-body">
                
                <?php if($error): ?>
                    <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
                <?php endif; ?>

                <form method="POST">
                    <div class="mb-4">
                        <label class="form-label">Code de validation</label>
                        <input type="text" name="code" class="form-control code-input" 
                               maxlength="6" pattern="[0-9]{6}" 
                               placeholder="123456" required>
                    </div>
                    
                    <button type="submit" class="btn btn-primary">
                        Vérifier le code
                    </button>
                    
                    <div class="text-center mt-3">
                        <a href="mot_de_passe_oublie.php">Renvoyer un code</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</body>
</html>