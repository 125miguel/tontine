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
    </style>
</head>
<body>
    <div class="card">
        <div class="card-header">
            <h2><i class="fas fa-hand-holding-usd me-2"></i>TONTONTINE</h2>
            <p class="mb-0">Nouveau mot de passe</p>
        </div>
        <div class="card-body">
            
            <?php if($error): ?>
                <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>
            
            <?php if($success): ?>
                <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
                <p class="text-center">Redirection vers la connexion...</p>
            <?php endif; ?>

            <?php if(!$success): ?>
                <p class="text-muted text-center mb-4">
                    Nouveau mot de passe pour <strong><?= htmlspecialchars($_SESSION['reset_association_nom'] ?? '') ?></strong>
                </p>

                <form method="POST">
                    <div class="mb-4">
                        <label class="form-label"> Nouveau mot de passe</label>
                        <input type="password" name="password" class="form-control" 
                               placeholder="Minimum 6 caractères" required>
                    </div>
                    
                    <div class="mb-4">
                        <label class="form-label"> Confirmer le mot de passe</label>
                        <input type="password" name="confirm_password" class="form-control" 
                               placeholder="Confirmer" required>
                    </div>
                    
                    <button type="submit" class="btn-primary">
                        Modifier mon mot de passe
                    </button>
                </form>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>