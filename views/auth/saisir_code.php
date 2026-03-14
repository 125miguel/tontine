<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();

if(!isset($_SESSION['reset_user_id']) || !isset($_SESSION['reset_email']) || !isset($_SESSION['reset_association_id'])) {
    header("Location: mot_de_passe_oublie.php");
    exit();
}

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../models/PasswordReset.php';

$error = '';

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
            $_SESSION['reset_code_id'] = $reset['id'];
            header("Location: nouveau_mot_de_passe_association.php");
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
    <title>Saisir le code - TONTONTINE</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        body {
            background-color: #1E3A8A;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
        }
        .card {
            border-radius: 20px;
            border: none;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            max-width: 450px;
            width: 100%;
            overflow: hidden;
        }
        .card-header {
            background-color: #1E3A8A;
            color: white;
            text-align: center;
            padding: 40px 30px;
            border-bottom: none;
        }
        .card-header h2 {
            font-weight: 700;
            margin-bottom: 10px;
            font-size: 28px;
        }
        .card-header p {
            opacity: 0.9;
            font-size: 16px;
        }
        .card-body {
            padding: 40px;
            background: white;
        }
        .code-input {
            font-size: 32px;
            letter-spacing: 12px;
            text-align: center;
            font-weight: 700;
            padding: 15px;
            border: 2px solid #e0e0e0;
            border-radius: 15px;
            transition: all 0.3s;
            font-family: monospace;
        }
        .code-input:focus {
            border-color: #1E3A8A;
            box-shadow: 0 0 0 0.2rem rgba(30, 58, 138, 0.25);
            outline: none;
        }
        .btn-primary {
            background-color: #1E3A8A;
            border: none;
            padding: 14px;
            width: 100%;
            border-radius: 12px;
            font-weight: 600;
            font-size: 16px;
            transition: all 0.3s;
            color: white;
        }
        .btn-primary:hover {
            background-color: #152b63;
            transform: translateY(-2px);
            box-shadow: 0 10px 30px rgba(30, 58, 138, 0.3);
        }
        .btn-primary:active {
            transform: translateY(0);
        }
        .form-control {
            border-radius: 12px;
            border: 2px solid #e0e0e0;
            padding: 12px 15px;
            transition: all 0.3s;
        }
        .form-control:focus {
            border-color: #1E3A8A;
            box-shadow: none;
        }
        .alert {
            border-radius: 12px;
            padding: 15px;
            margin-bottom: 25px;
            border: none;
        }
        .alert-danger {
            background-color: #fee2e2;
            color: #dc2626;
            border-left: 4px solid #dc2626;
        }
        .form-label {
            font-weight: 600;
            color: #2D3748;
            margin-bottom: 8px;
            font-size: 14px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .text-muted {
            color: #718096 !important;
            line-height: 1.6;
        }
        .text-muted strong {
            color: #1E3A8A;
            font-weight: 600;
        }
        .link-custom {
            color: #1E3A8A;
            text-decoration: none;
            font-weight: 500;
            transition: all 0.3s;
            display: inline-block;
            padding: 5px 10px;
        }
        .link-custom:hover {
            color: #152b63;
            text-decoration: underline;
        }
        .icon-email {
            color: #1E3A8A;
            font-size: 48px;
            margin-bottom: 15px;
        }
        .timer-text {
            font-size: 14px;
            color: #718096;
            margin-top: 15px;
            padding: 10px;
            background: #f8f9fa;
            border-radius: 10px;
        }
    </style>
</head>
<body>
    <div class="card">
        <div class="card-header">
            <i class="fas fa-hand-holding-usd fa-3x mb-3"></i>
            <h2>TONTONTINE</h2>
            <p class="mb-0">Vérification du code de sécurité</p>
        </div>
        <div class="card-body">
            <?php if($error): ?>
                <div class="alert alert-danger">
                    <i class="fas fa-exclamation-circle me-2"></i>
                    <?= htmlspecialchars($error) ?>
                </div>
            <?php endif; ?>

            <div class="text-center mb-4">
                <i class="fas fa-envelope icon-email"></i>
                <p class="text-muted mb-0">
                    Un code de vérification à 6 chiffres<br>a été envoyé à :
                </p>
                <strong class="d-block mt-2" style="color: #1E3A8A; font-size: 16px;">
                    <?= htmlspecialchars($_SESSION['reset_email']) ?>
                </strong>
                <p class="text-muted mt-2 small">
                    pour l'association sélectionnée
                </p>
            </div>

            <form method="POST">
                <div class="mb-4">
                    <label class="form-label">
                        <i class="fas fa-key me-2"></i>Code de validation
                    </label>
                    <input type="text" 
                           name="code" 
                           class="form-control code-input" 
                           maxlength="6" 
                           pattern="[0-9]{6}" 
                           placeholder="••••••" 
                           inputmode="numeric"
                           autocomplete="one-time-code"
                           required>
                    <div class="timer-text text-center mt-3">
                        <i class="far fa-clock me-1"></i>
                        Ce code expirera dans 15 minutes
                    </div>
                </div>
                
                <button type="submit" class="btn-primary">
                    <i class="fas fa-check-circle me-2"></i>
                    Vérifier le code
                </button>
                
                <div class="text-center mt-4">
                    <a href="mot_de_passe_oublie.php" class="link-custom">
                        <i class="fas fa-redo-alt me-1"></i>
                        Renvoyer un nouveau code
                    </a>
                </div>
            </form>

            <div class="text-center mt-3">
                <a href="../auth/login.php" class="link-custom small">
                    <i class="fas fa-arrow-left me-1"></i>
                    Retour à la connexion
                </a>
            </div>
        </div>
    </div>

    <script>
        // Auto-focus sur le champ code
        document.addEventListener('DOMContentLoaded', function() {
            document.querySelector('.code-input').focus();
        });
    </script>
</body>
</html>