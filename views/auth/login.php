<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../models/User.php';

$error = '';
$step = 1;
$identifiant = '';
$associations = [];

// Si déjà connecté, redirection
if(isset($_SESSION['user_id'])) {
    header("Location: ../dashboard.php");
    exit();
}

// ÉTAPE 1 : Traitement de l'identifiant
if(isset($_POST['check_identifiant'])) {
    $identifiant = $_POST['identifiant'] ?? '';
    
    if(empty($identifiant)) {
        $error = "Veuillez saisir votre email ou téléphone";
    } else {
        $database = new Database();
        $db = $database->getConnection();
        
        // Chercher l'utilisateur
        $query = "SELECT id, nom, prenom FROM users WHERE email = :id OR telephone = :id";
        $stmt = $db->prepare($query);
        $stmt->execute(['id' => $identifiant]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if($user) {
            // Récupérer ses associations
            $query = "SELECT a.id, a.nom as association_nom 
                      FROM associations a
                      JOIN membres_association ma ON a.id = ma.association_id
                      WHERE ma.user_id = :user_id AND ma.est_actif = 1";
            $stmt = $db->prepare($query);
            $stmt->execute(['user_id' => $user['id']]);
            $associations = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            if(count($associations) > 0) {
                $_SESSION['temp_user_id'] = $user['id'];
                $_SESSION['temp_user_nom'] = $user['prenom'] . ' ' . $user['nom'];
                $_SESSION['temp_identifiant'] = $identifiant;
                $step = 2;
            } else {
                $error = "Vous n'êtes membre d'aucune association.";
            }
        } else {
            $error = "Aucun compte trouvé avec cet identifiant.";
        }
    }
}

// ÉTAPE 2 : Traitement de la connexion à l'association
if(isset($_POST['login_association'])) {
    if(!isset($_SESSION['temp_user_id'])) {
        header("Location: login.php");
        exit();
    }
    
    $association_id = $_POST['association_id'] ?? 0;
    $password = $_POST['password'] ?? '';
    
    if(empty($association_id) || empty($password)) {
        $error = "Veuillez sélectionner une association et saisir votre mot de passe";
    } else {
        $database = new Database();
        $db = $database->getConnection();
        
        // Vérifier le mot de passe pour cette association
        $query = "SELECT ma.*, u.nom, u.prenom, u.email, u.telephone, u.role 
                  FROM membres_association ma
                  JOIN users u ON ma.user_id = u.id
                  WHERE ma.user_id = :user_id AND ma.association_id = :association_id";
        $stmt = $db->prepare($query);
        $stmt->execute([
            'user_id' => $_SESSION['temp_user_id'],
            'association_id' => $association_id
        ]);
        $membre = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if($membre && password_verify($password, $membre['password'])) {
            // Récupérer le nom de l'association
            $query = "SELECT nom FROM associations WHERE id = :id";
            $stmt = $db->prepare($query);
            $stmt->execute(['id' => $association_id]);
            $assoc = $stmt->fetch(PDO::FETCH_ASSOC);
            
            // Stocker en session
            $_SESSION['user_id'] = $membre['user_id'];
            $_SESSION['user_nom'] = $membre['prenom'] . ' ' . $membre['nom'];
            $_SESSION['user_role'] = $membre['role'];
            $_SESSION['user_email'] = $membre['email'];
            $_SESSION['user_telephone'] = $membre['telephone'];
            $_SESSION['association_active'] = $association_id;
            $_SESSION['association_nom'] = $assoc['nom'];
            
            // Nettoyer les variables temporaires
            unset($_SESSION['temp_user_id']);
            unset($_SESSION['temp_user_nom']);
            unset($_SESSION['temp_identifiant']);
            
            // Vérifier première connexion
            if($membre['premiere_connexion']) {
                header("Location: changer_mdp_association.php?first=1");
                exit();
            }
            
            header("Location: ../dashboard.php");
            exit();
        } else {
            $error = "Mot de passe incorrect pour cette association.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Connexion - TONTONTINE</title>
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
        .login-card {
            background: white;
            border-radius: 20px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            max-width: 450px;
            width: 100%;
            overflow: hidden;
        }
        .login-header {
            background: linear-gradient(135deg, #6B46C1 0%, #FF8A4C 100%);
            color: white;
            padding: 40px 30px;
            text-align: center;
        }
        .login-header h2 {
            font-weight: 700;
            margin-bottom: 10px;
        }
        .login-body {
            padding: 40px 30px;
        }
        .form-control, .form-select {
            border-radius: 10px;
            border: 2px solid #e0e0e0;
            padding: 12px 15px;
            font-size: 15px;
            transition: all 0.3s;
        }
        .form-control:focus, .form-select:focus {
            border-color: #6B46C1;
            box-shadow: none;
            outline: none;
        }
        .btn-login {
            background: linear-gradient(135deg, #6B46C1 0%, #FF8A4C 100%);
            color: white;
            border: none;
            border-radius: 10px;
            padding: 14px;
            font-size: 16px;
            font-weight: 600;
            width: 100%;
            transition: all 0.3s;
        }
        .btn-login:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 30px rgba(107, 70, 193, 0.4);
        }
        .btn-back {
            background: white;
            color: #6B46C1;
            border: 2px solid #6B46C1;
            border-radius: 10px;
            padding: 12px;
            font-size: 14px;
            font-weight: 600;
            width: 100%;
            transition: all 0.3s;
            margin-top: 10px;
        }
        .btn-back:hover {
            background: #6B46C1;
            color: white;
        }
        .alert {
            border-radius: 10px;
        }
        .user-info {
            background: #f8f9fa;
            border-radius: 10px;
            padding: 15px;
            margin-bottom: 20px;
            text-align: center;
        }
        .user-info i {
            color: #6B46C1;
            font-size: 40px;
            margin-bottom: 10px;
        }
    </style>
</head>
<body>
    <div class="login-card">
        <div class="login-header">
            <h2><i class="fas fa-hand-holding-usd me-2"></i>TONTONTINE</h2>
            <p><?= $step == 1 ? 'Connectez-vous à votre espace' : 'Choisissez votre association' ?></p>
        </div>
        <div class="login-body">
            
            <?php if($error): ?>
                <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>

            <?php if($step == 1): ?>
                <!-- ÉTAPE 1 : Saisie de l'identifiant -->
                <form method="POST">
                    <div class="mb-4">
                        <label class="form-label"> Email ou  Téléphone</label>
                        <input type="text" name="identifiant" class="form-control" 
                               value="<?= htmlspecialchars($_POST['identifiant'] ?? '') ?>" 
                               placeholder="exemple@email.com ou 691234567" required>
                    </div>
                    
                    <button type="submit" name="check_identifiant" class="btn-login">
                        Continuer
                    </button>
                    
                    <div class="text-center mt-3">
                        <a href="register.php" class="text-decoration-none" style="color: #6B46C1;">
                            Pas encore de compte ? Inscrivez-vous
                        </a>
                    </div>
                </form>

            <?php else: ?>
                <!-- ÉTAPE 2 : Choix de l'association et mot de passe -->
                <div class="user-info">
                    <i class="fas fa-user-circle"></i>
                    <h5><?= htmlspecialchars($_SESSION['temp_user_nom']) ?></h5>
                    <p class="text-muted mb-0"><?= htmlspecialchars($_SESSION['temp_identifiant']) ?></p>
                </div>

                <form method="POST">
                    <div class="mb-4">
                        <label class="form-label"> Association</label>
                        <select name="association_id" class="form-select" required>
                            <option value="">Sélectionnez votre association</option>
                            <?php foreach($associations as $assoc): ?>
                                <option value="<?= $assoc['id'] ?>"><?= htmlspecialchars($assoc['association_nom']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="mb-4">
                        <label class="form-label"> Mot de passe</label>
                        <input type="password" name="password" class="form-control" 
                               placeholder="Votre mot de passe pour cette association" required>
                    </div>
                    
                    <button type="submit" name="login_association" class="btn-login">
                        Se connecter
                    </button>
                    
                    <button type="button" onclick="window.location.href='login.php'" class="btn-back">
                        <i class="fas fa-arrow-left me-2"></i>Changer d'identifiant
                    </button>
                </form>
            <?php endif; ?>

            <div class="text-center mt-3">
                <a href="mot_de_passe_oublie.php" class="text-muted small">Mot de passe oublié ?</a>
            </div>
        </div>
    </div>
</body>
</html>