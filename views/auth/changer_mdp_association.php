<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();

if(!isset($_SESSION['user_id']) || !isset($_SESSION['association_active'])) {
    header("Location: login.php");
    exit();
}

require_once __DIR__ . '/../../config/database.php';

$database = new Database();
$db = $database->getConnection();

$error = '';
$success = '';

// Récupérer le nom de l'association
$query = "SELECT nom FROM associations WHERE id = :id";
$stmt = $db->prepare($query);
$stmt->execute(['id' => $_SESSION['association_active']]);
$assoc = $stmt->fetch(PDO::FETCH_ASSOC);
$association_nom = $assoc['nom'] ?? 'Association';

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
        // Mettre à jour le mot de passe pour cette association
        $hashed = password_hash($nouveau, PASSWORD_DEFAULT);
        
        $query = "UPDATE membres_association 
                  SET password = :password, premiere_connexion = 0
                  WHERE user_id = :user_id AND association_id = :association_id";
        $stmt = $db->prepare($query);
        
        if($stmt->execute([
            'password' => $hashed,
            'user_id' => $_SESSION['user_id'],
            'association_id' => $_SESSION['association_active']
        ])) {
            $success = "Mot de passe modifié avec succès !";
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
    <title>Changer mon mot de passe - <?= htmlspecialchars($association_nom) ?></title>
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
            padding: 14px;
            width: 100%;
            border-radius: 10px;
            font-weight: 600;
            font-size: 16px;
            transition: all 0.3s;
        }
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 30px rgba(107, 70, 193, 0.4);
        }
        .form-control {
            border-radius: 10px;
            border: 2px solid #e0e0e0;
            padding: 12px 15px;
            font-size: 15px;
            transition: all 0.3s;
        }
        .form-control:focus {
            border-color: #6B46C1;
            box-shadow: none;
            outline: none;
        }
        .alert {
            border-radius: 10px;
            padding: 15px;
            margin-bottom: 25px;
            border: none;
        }
        .form-label {
            font-weight: 500;
            color: #333;
            margin-bottom: 8px;
        }
        .association-name {
            font-weight: 700;
            color: #FF8A4C;
        }
    </style>
</head>
<body>
    <div class="card">
        <div class="card-header">
            <h2><i class="fas fa-hand-holding-usd me-2"></i>TONTONTINE</h2>
            <p class="mb-0">Première connexion à <span class="association-name"><?= htmlspecialchars($association_nom) ?></span></p>
        </div>
        <div class="card-body">
            
            <?php if(isset($_GET['first'])): ?>
                <div class="alert alert-info">
                    <i class="fas fa-info-circle me-2"></i>
                    Bienvenue ! Pour des raisons de sécurité, veuillez choisir votre mot de passe permanent.
                </div>
            <?php endif; ?>
            
            <?php if($error): ?>
                <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>
            
            <?php if($success): ?>
                <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
                <p class="text-center">Redirection vers votre espace...</p>
            <?php else: ?>
                <form method="POST">
                    <div class="mb-4">
                        <label class="form-label"> Nouveau mot de passe</label>
                        <input type="password" name="nouveau" class="form-control" 
                               placeholder="Minimum 6 caractères" required>
                    </div>
                    
                    <div class="mb-4">
                        <label class="form-label"> Confirmer le mot de passe</label>
                        <input type="password" name="confirmation" class="form-control" 
                               placeholder="Confirmer" required>
                    </div>
                    
                    <button type="submit" class="btn-primary">
                        <i class="fas fa-save me-2"></i>Enregistrer mon mot de passe
                    </button>
                </form>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>