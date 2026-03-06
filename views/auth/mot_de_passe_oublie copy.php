<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../models/User.php';
require_once __DIR__ . '/../../models/PasswordReset.php';
require_once __DIR__ . '/../../helpers/mail_helper.php';

$message = '';
$error = '';
$step = 1; // 1 = saisie email, 2 = choix association
$email = '';
$associations = [];

if($_SERVER['REQUEST_METHOD'] == 'POST') {
    
    if(isset($_POST['check_email'])) {
        // ÉTAPE 1 : Vérifier l'email
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
                
                // Récupérer toutes les associations de ce membre
                $query = "SELECT a.id, a.nom as association_nom 
                          FROM associations a
                          JOIN membres_association ma ON a.id = ma.association_id
                          WHERE ma.user_id = :user_id";
                $stmt = $db->prepare($query);
                $stmt->execute(['user_id' => $user['id']]);
                $associations = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                if(count($associations) > 0) {
                    $_SESSION['reset_user_id'] = $user['id'];
                    $_SESSION['reset_email'] = $email;
                    $step = 2;
                } else {
                    $error = "Vous n'êtes membre d'aucune association.";
                }
            } else {
                $error = "Aucun compte trouvé avec cet email";
            }
        }
    }
    
    if(isset($_POST['send_code'])) {
        // ÉTAPE 2 : Envoyer le code pour l'association choisie
        $association_id = $_POST['association_id'] ?? 0;
        
        if(!$association_id) {
            $error = "Veuillez sélectionner une association";
        } else {
            $database = new Database();
            $db = $database->getConnection();
            
            // Récupérer le nom de l'association
            $query = "SELECT nom FROM associations WHERE id = :id";
            $stmt = $db->prepare($query);
            $stmt->execute(['id' => $association_id]);
            $assoc = $stmt->fetch(PDO::FETCH_ASSOC);
            
            // Créer un code de réinitialisation
            $passwordReset = new PasswordReset($db);
            $code = $passwordReset->createCode($_SESSION['reset_user_id']);
            
            if($code) {
                
                // Stocker l'association choisie
                $_SESSION['reset_association_id'] = $association_id;
                $_SESSION['reset_association_nom'] = $assoc['nom'];
                $_SESSION['debug_code'] = $code; // Stocker le code pour le test
                
                // Rediriger vers la page de saisie du code
                header("Location: saisir_code.php");
                exit();
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mot de passe oublié - TONTONTINE</title>
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
        .btn-back {
            background: white;
            color: #6B46C1;
            border: 2px solid #6B46C1;
            border-radius: 10px;
            padding: 10px;
            width: 100%;
            margin-top: 10px;
            transition: all 0.3s;
        }
        .btn-back:hover {
            background: #6B46C1;
            color: white;
        }
        .form-control, .form-select {
            border-radius: 10px;
            border: 2px solid #e0e0e0;
            padding: 12px;
            transition: all 0.3s;
        }
        .form-control:focus, .form-select:focus {
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
            <p class="mb-0"><?= $step == 1 ? 'Mot de passe oublié' : 'Choisissez votre association' ?></p>
        </div>
        <div class="card-body">
            
            <?php if($error): ?>
                <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>

            <?php if($step == 1): ?>
                <!-- ÉTAPE 1 : Saisie de l'email -->
                <form method="POST">
                    <div class="mb-4">
                        <label class="form-label"> Votre email</label>
                        <input type="email" name="email" class="form-control" 
                               placeholder="exemple@email.com" required>
                    </div>
                    
                    <button type="submit" name="check_email" class="btn-primary">
                        Vérifier mon email
                    </button>
                    
                    <div class="text-center mt-3">
                        <a href="login.php" class="text-decoration-none" style="color: #6B46C1;">
                            Retour à la connexion
                        </a>
                    </div>
                </form>

            <?php else: ?>
                <!-- ÉTAPE 2 : Choix de l'association -->
                <form method="POST">
                    <div class="mb-4">
                        <label class="form-label"> Association</label>
                        <select name="association_id" class="form-select" required>
                            <option value="">Choisissez votre association</option>
                            <?php foreach($associations as $assoc): ?>
                                <option value="<?= $assoc['id'] ?>"><?= htmlspecialchars($assoc['association_nom']) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <small class="text-muted">Pour quelle association souhaitez-vous réinitialiser le mot de passe ?</small>
                    </div>
                    
                    <button type="submit" name="send_code" class="btn-primary">
                        Envoyer le code
                    </button>
                    
                    <button type="button" onclick="window.location.href='mot_de_passe_oublie.php'" class="btn-back">
                        <i class="fas fa-arrow-left me-2"></i>Changer d'email
                    </button>
                </form>
            <?php endif; ?>

        </div>
    </div>
</body>
</html>