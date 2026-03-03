<?php
// Démarrer la session pour stocker les infos de l'utilisateur connecté
session_start();

// Inclure les fichiers nécessaires
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../models/User.php';

$error = '';

// Si l'utilisateur est déjà connecté, on le redirige vers le tableau de bord
if(isset($_SESSION['user_id'])) {
    header("Location: ../dashboard.php");
    exit();
}

// Vérifier si le formulaire a été soumis
if($_SERVER['REQUEST_METHOD'] == 'POST') {
    
    $identifiant = $_POST['identifiant'] ?? '';
    $password = $_POST['password'] ?? '';
    
    if(empty($identifiant) || empty($password)) {
        $error = "Veuillez remplir tous les champs";
    } else {
        // Tentative de connexion
        $database = new Database();
        $db = $database->getConnection();
        
        $user = new User($db);
        $logged = false;
        
        // Détecter si c'est un email ou un téléphone
        if(filter_var($identifiant, FILTER_VALIDATE_EMAIL)) {
            // C'est un email
            $logged = $user->login($identifiant, $password);
        } else {
            // C'est un téléphone (on suppose)
            $logged = $user->loginByPhone($identifiant, $password);
        }
        
        if($logged) {
            // Connexion réussie !
            
            // Stocker les infos en session
            $_SESSION['user_id'] = $user->id;
            $_SESSION['user_nom'] = $user->nom . ' ' . $user->prenom;
            $_SESSION['user_role'] = $user->role;
            $_SESSION['user_email'] = $user->email;
            $_SESSION['user_telephone'] = $user->telephone;
            
            // Vérifier si c'est la première connexion (pour les membres seulement)
            if($user->role == 'membre' && $user->premiere_connexion) {
                header("Location: changer_mdp.php?first=1");
                exit();
            } elseif($user->role == 'membre') {
                // Vérifier combien de tontines pour ce membre
                $query = "SELECT COUNT(*) as nb FROM membre_tontine WHERE user_id = :uid AND est_actif = 1";
                $stmt = $db->prepare($query);
                $stmt->execute(['uid' => $user->id]);
                $nb_tontines = $stmt->fetch()['nb'];
                
                if($nb_tontines > 1) {
                    header("Location: choisir_tontine.php");
                    exit();
                } elseif($nb_tontines == 1) {
                    $query = "SELECT tontine_id FROM membre_tontine WHERE user_id = :uid LIMIT 1";
                    $stmt = $db->prepare($query);
                    $stmt->execute(['uid' => $user->id]);
                    $t = $stmt->fetch();
                    $_SESSION['tontine_active'] = $t['tontine_id'];
                    header("Location: ../dashboard.php");
                    exit();
                } else {
                    header("Location: ../dashboard.php");
                    exit();
                }
            } else {
                // Admin → dashboard direct
                header("Location: ../dashboard.php");
                exit();
            }
        } else {
            $error = "Identifiant ou mot de passe incorrect";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Connexion - Tontine</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        .card {
            border-radius: 20px;
            border: none;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            overflow: hidden;
            max-width: 450px;
            width: 100%;
        }
        .card-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            text-align: center;
            padding: 30px 20px;
            border-bottom: none;
        }
        .card-header h2 {
            margin: 0;
            font-size: 32px;
            font-weight: 600;
        }
        .card-header p {
            margin: 10px 0 0;
            opacity: 0.9;
            font-size: 16px;
        }
        .card-body {
            padding: 40px;
            background: white;
        }
        .form-control {
            border-radius: 10px;
            border: 2px solid #e0e0e0;
            padding: 12px 15px;
            font-size: 15px;
            transition: all 0.3s;
        }
        .form-control:focus {
            border-color: #667eea;
            box-shadow: none;
            outline: none;
        }
        .btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border: none;
            border-radius: 10px;
            padding: 14px;
            font-size: 16px;
            font-weight: 600;
            transition: all 0.3s;
            width: 100%;
        }
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 30px rgba(102, 126, 234, 0.4);
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
        .register-link {
            text-align: center;
            margin-top: 25px;
            padding-top: 20px;
            border-top: 1px solid #e0e0e0;
        }
        .register-link a {
            color: #667eea;
            text-decoration: none;
            font-weight: 500;
            transition: all 0.3s;
        }
        .register-link a:hover {
            color: #764ba2;
            text-decoration: underline;
        }
    </style>
</head>
<body>
    <?php if(isset($_SESSION['register_success'])): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <i class="bi bi-check-circle-fill me-2"></i> <?= $_SESSION['register_success'] ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php unset($_SESSION['register_success']); ?>
    <?php endif; ?>
    <div class="container d-flex justify-content-center">
        <div class="card">
            <div class="card-header">
                <h2> Connexion</h2>
                <p>Accédez à votre espace tontine</p>
            </div>
            <div class="text-center mt-3">
                <a href="mot_de_passe_oublie.php">Mot de passe oublié ?</a>
            </div>
            <div class="card-body">
                
                <?php if($error): ?>
                    <div class="alert alert-danger">
                        <strong> Erreur :</strong> <?= htmlspecialchars($error) ?>
                    </div>
                <?php endif; ?>

                <?php if(isset($_SESSION['success'])): ?>
                    <div class="alert alert-success">
                        <strong> Succès !</strong> <?= $_SESSION['success']; unset($_SESSION['success']); ?>
                    </div>
                <?php endif; ?>

                <form method="POST" action="">
                    <div class="mb-4">
                        <label class="form-label">Email ou Téléphone</label>
                        <input type="text" name="identifiant" class="form-control" 
                            value="<?= htmlspecialchars($_POST['identifiant'] ?? '') ?>" 
                            placeholder="exemple@email.com ou 6XXXXXXXX" required>
                        <small class="text-muted">Connectez-vous avec votre email ou votre numéro de téléphone</small>
                    </div>

                    <div class="mb-4">
                        <label class="form-label"> Mot de passe</label>
                        <input type="password" name="password" class="form-control" 
                               placeholder="••••••••" required>
                    </div>

                    <button type="submit" class="btn btn-primary">
                        Se connecter
                    </button>

                    <div class="register-link">
                        Pas encore de compte ? 
                        <a href="register.php">Inscrivez-vous ici</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</body>
</html>