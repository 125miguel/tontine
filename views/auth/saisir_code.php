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
            background: linear-gradient(135deg, #6B46C1 0%, #FF8A4C 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            font-family: 'Inter', sans-serif;
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
            background: linear-gradient(135deg, #6B46C1 0%, #FF8A4C 100%);
            color: white;
            text-align: center;
            padding: 40px 30px;
        }
        .card-header h2 {
            font-weight: 700;
            margin-bottom: 10px;
        }
        .card-body {
            padding: 40px;
            background: white;
        }
        .code-input {
            font-size: 24px;
            letter-spacing: 10px;
            text-align: center;
            font-weight: bold;
        }
        .btn-primary {
            background: linear-gradient(135deg, #6B46C1 0%, #FF8A4C 100%);
            border: none;
            padding: 12px;
            width: 100%;
            border-radius: 10px;
            font-weight: 600;
            transition: all 0.3s;
        }
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 30px rgba(107, 70, 193, 0.4);
        }
        .form-control {
            border-radius: 10px;
            border: 2px solid #e0e0e0;
            padding: 12px;
            transition: all 0.3s;
        }
        .form-control:focus {
            border-color: #6B46C1;
            box-shadow: none;
        }
        .alert {
            border-radius: 10px;
        }
        .debug-code {
            background: #f8f9fa;
            border: 2px dashed #6B46C1;
            padding: 15px;
            text-align: center;
            border-radius: 10px;
            margin-bottom: 20px;
        }
    </style>
</head>
<body>
    <div class="card">
        <div class="card-header">
            <h2><i class="fas fa-hand-holding-usd me-2"></i>TONTONTINE</h2>
            <p class="mb-0">Saisissez votre code</p>
        </div>
        <div class="card-body">
            
            <?php if(isset($_SESSION['debug_code'])): ?>
                <div class="alert alert-info text-center">
                    <h5><i class="fas fa-flask"></i> MODE TEST</h5>
                    <p>Code de validation :</p>
                    <div style="font-size: 48px; font-weight: bold; letter-spacing: 5px; color: #6B46C1;">
                        <?= $_SESSION['debug_code'] ?>
                    </div>
                    <p class="mt-2 mb-0"><small>Ce code est affiché car l'envoi d'email n'est pas configuré.</small></p>
                </div>
                <?php unset($_SESSION['debug_code']); ?>
            <?php endif; ?>

            <?php if($error): ?>
                <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>

            <p class="text-muted text-center mb-4">
                Un code a été envoyé à <strong><?= htmlspecialchars($_SESSION['reset_email']) ?></strong>
                pour l'association sélectionnée.
            </p>

            <form method="POST">
                <div class="mb-4">
                    <label class="form-label"> Code de validation</label>
                    <input type="text" name="code" class="form-control code-input" 
                           maxlength="6" pattern="[0-9]{6}" 
                           placeholder="123456" required>
                </div>
                
                <button type="submit" class="btn-primary">
                    Vérifier le code
                </button>
                
                <div class="text-center mt-3">
                    <a href="mot_de_passe_oublie.php" class="text-decoration-none" style="color: #6B46C1;">
                        Renvoyer un code
                    </a>
                </div>
            </form>
        </div>
    </div>
</body>
</html>