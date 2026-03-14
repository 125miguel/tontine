<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();

if(!isset($_SESSION['reset_user_id']) || !isset($_SESSION['reset_code_id']) || !isset($_SESSION['reset_association_id'])) {
    header("Location: mot_de_passe_oublie.php");
    exit();
}

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../models/PasswordReset.php';

$error = '';
$success = '';

if($_SERVER['REQUEST_METHOD'] == 'POST') {
    $password = $_POST['password'] ?? '';
    $confirm = $_POST['confirm_password'] ?? '';
    
    if(empty($password) || empty($confirm)) {
        $error = "Veuillez remplir tous les champs";
    } elseif($password != $confirm) {
        $error = "Les mots de passe ne correspondent pas";
    } elseif(strlen($password) < 6) {
        $error = "Le mot de passe doit contenir au moins 6 caractères";
    } else {
        $database = new Database();
        $db = $database->getConnection();
        
        // Mettre à jour le mot de passe pour cette association
        $hashed = password_hash($password, PASSWORD_DEFAULT);
        
        $query = "UPDATE membres_association SET password = :password, premiere_connexion = 0 
                  WHERE user_id = :user_id AND association_id = :association_id";
        $stmt = $db->prepare($query);
        
        if($stmt->execute([
            'password' => $hashed,
            'user_id' => $_SESSION['reset_user_id'],
            'association_id' => $_SESSION['reset_association_id']
        ])) {
            // Marquer le code comme utilisé
            $passwordReset = new PasswordReset($db);
            $passwordReset->markAsUsed($_SESSION['reset_code_id']);
            
            // Nettoyer la session
            unset($_SESSION['reset_user_id']);
            unset($_SESSION['reset_email']);
            unset($_SESSION['reset_code_id']);
            unset($_SESSION['reset_association_id']);
            unset($_SESSION['reset_association_nom']);
            
            $success = "Mot de passe modifié avec succès !";
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
    <title>Nouveau mot de passe - TONTONTINE</title>
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
            margin: 0;
        }
        .card-body {
            padding: 40px;
            background: white;
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
        .btn-primary:disabled {
            background-color: #a0aec0;
            cursor: not-allowed;
            transform: none;
        }
        .form-control {
            border-radius: 12px;
            border: 2px solid #e0e0e0;
            padding: 12px 15px;
            transition: all 0.3s;
            font-size: 15px;
        }
        .form-control:focus {
            border-color: #1E3A8A;
            box-shadow: 0 0 0 0.2rem rgba(30, 58, 138, 0.25);
            outline: none;
        }
        .form-label {
            font-weight: 600;
            color: #2D3748;
            margin-bottom: 8px;
            font-size: 14px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .form-label i {
            color: #1E3A8A;
            margin-right: 6px;
        }
        .alert {
            border-radius: 12px;
            padding: 15px;
            margin-bottom: 25px;
            border: none;
            display: flex;
            align-items: center;
        }
        .alert-danger {
            background-color: #fee2e2;
            color: #dc2626;
            border-left: 4px solid #dc2626;
        }
        .alert-success {
            background-color: #def7ec;
            color: #0e9f6e;
            border-left: 4px solid #0e9f6e;
        }
        .alert i {
            font-size: 20px;
            margin-right: 10px;
        }
        .text-muted {
            color: #718096 !important;
            line-height: 1.6;
        }
        .text-muted strong {
            color: #1E3A8A;
            font-weight: 600;
        }
        .password-requirements {
            background-color: #f8f9fa;
            border-radius: 10px;
            padding: 12px 15px;
            margin-top: 10px;
            font-size: 13px;
            color: #4a5568;
        }
        .password-requirements i {
            color: #1E3A8A;
            margin-right: 8px;
            font-size: 14px;
        }
        .password-requirements ul {
            margin: 8px 0 0 0;
            padding-left: 25px;
        }
        .password-requirements li {
            margin-bottom: 4px;
        }
        .redirect-message {
            text-align: center;
            margin-top: 20px;
            padding: 15px;
            background-color: #f8f9fa;
            border-radius: 10px;
        }
        .redirect-message p {
            margin-bottom: 0;
            color: #4a5568;
        }
        .spinner {
            display: inline-block;
            width: 20px;
            height: 20px;
            border: 3px solid rgba(30, 58, 138, 0.3);
            border-radius: 50%;
            border-top-color: #1E3A8A;
            animation: spin 1s ease-in-out infinite;
            margin-right: 10px;
        }
        @keyframes spin {
            to { transform: rotate(360deg); }
        }
    </style>
</head>
<body>
    <div class="card">
        <div class="card-header">
            <i class="fas fa-hand-holding-usd fa-3x mb-3"></i>
            <h2>TONTONTINE</h2>
            <p class="mb-0">Définition d'un nouveau mot de passe</p>
        </div>
        <div class="card-body">
            
            <?php if($error): ?>
                <div class="alert alert-danger">
                    <i class="fas fa-exclamation-circle"></i>
                    <?= htmlspecialchars($error) ?>
                </div>
            <?php endif; ?>
            
            <?php if($success): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle"></i>
                    <?= htmlspecialchars($success) ?>
                </div>
                <div class="redirect-message">
                    <div class="d-flex align-items-center justify-content-center">
                        <div class="spinner"></div>
                        <p class="mb-0">Redirection vers la page de connexion...</p>
                    </div>
                </div>
            <?php endif; ?>

            <?php if(!$success): ?>
                <div class="text-center mb-4">
                    <i class="fas fa-lock fa-3x mb-3" style="color: #1E3A8A;"></i>
                    <p class="text-muted mb-1">
                        Nouveau mot de passe pour
                    </p>
                    <strong class="d-block" style="color: #1E3A8A; font-size: 18px;">
                        <i class="fas fa-users me-2"></i>
                        <?= htmlspecialchars($_SESSION['reset_association_nom'] ?? 'Votre association') ?>
                    </strong>
                </div>

                <form method="POST" id="passwordForm">
                    <div class="mb-4">
                        <label class="form-label">
                            <i class="fas fa-key"></i>
                            Nouveau mot de passe
                        </label>
                        <input type="password" 
                               name="password" 
                               class="form-control" 
                               placeholder="••••••••"
                               minlength="6"
                               required>
                    </div>
                    
                    <div class="mb-4">
                        <label class="form-label">
                            <i class="fas fa-check-circle"></i>
                            Confirmer le mot de passe
                        </label>
                        <input type="password" 
                               name="confirm_password" 
                               class="form-control" 
                               placeholder="••••••••"
                               minlength="6"
                               required>
                    </div>

                    <!-- Exigences du mot de passe -->
                    <div class="password-requirements">
                        <i class="fas fa-shield-alt"></i>
                        <strong>Exigences :</strong>
                        <ul>
                            <li>Minimum 6 caractères</li>
                            <li>Les mots de passe doivent correspondre</li>
                        </ul>
                    </div>
                    
                    <button type="submit" class="btn-primary mt-3" id="submitBtn">
                        <i class="fas fa-save me-2"></i>
                        Modifier mon mot de passe
                    </button>
                </form>

                <div class="text-center mt-4">
                    <a href="login.php" class="text-decoration-none" style="color: #1E3A8A;">
                        <i class="fas fa-arrow-left me-1"></i>
                        Retour à la connexion
                    </a>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <script>
        // Empêcher la soumission multiple du formulaire
        document.getElementById('passwordForm')?.addEventListener('submit', function(e) {
            const submitBtn = document.getElementById('submitBtn');
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Traitement en cours...';
        });

        // Validation en temps réel
        const password = document.querySelector('input[name="password"]');
        const confirm = document.querySelector('input[name="confirm_password"]');
        
        if(password && confirm) {
            function validatePasswords() {
                if(confirm.value.length > 0) {
                    if(password.value !== confirm.value) {
                        confirm.setCustomValidity('Les mots de passe ne correspondent pas');
                    } else {
                        confirm.setCustomValidity('');
                    }
                }
            }
            
            password.addEventListener('change', validatePasswords);
            confirm.addEventListener('keyup', validatePasswords);
        }
    </script>
</body>
</html>