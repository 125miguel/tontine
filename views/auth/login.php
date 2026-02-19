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
    
    $email = $_POST['email'] ?? '';
    $password = $_POST['password'] ?? '';
    
    if(empty($email) || empty($password)) {
        $error = "Veuillez remplir tous les champs";
    } else {
        // Tentative de connexion
        $database = new Database();
        $db = $database->getConnection();
        
        $user = new User($db);
        
        if($user->login($email, $password)) {
            // Connexion réussie !
            
            // Stocker les infos dans la session
            $_SESSION['user_id'] = $user->id;
            $_SESSION['user_nom'] = $user->nom . ' ' . $user->prenom;
            $_SESSION['user_email'] = $user->email;
            $_SESSION['user_role'] = $user->role;
            
            // Rediriger vers le tableau de bord
            header("Location: ../dashboard.php");
            exit();
        } else {
            $error = "Email ou mot de passe incorrect";
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
    <div class="container d-flex justify-content-center">
        <div class="card">
            <div class="card-header">
                <h2>🔐 Connexion</h2>
                <p>Accédez à votre espace tontine</p>
            </div>
            <div class="card-body">
                
                <?php if($error): ?>
                    <div class="alert alert-danger">
                        <strong>❌ Erreur :</strong> <?= htmlspecialchars($error) ?>
                    </div>
                <?php endif; ?>

                <?php if(isset($_SESSION['success'])): ?>
                    <div class="alert alert-success">
                        <strong>✅ Succès !</strong> <?= $_SESSION['success']; unset($_SESSION['success']); ?>
                    </div>
                <?php endif; ?>

                <form method="POST" action="">
                    <div class="mb-4">
                        <label class="form-label">📧 Email</label>
                        <input type="email" name="email" class="form-control" 
                               value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" 
                               placeholder="exemple@email.com" required>
                    </div>

                    <div class="mb-4">
                        <label class="form-label">🔒 Mot de passe</label>
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