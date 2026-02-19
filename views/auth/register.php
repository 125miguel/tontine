<?php
// Démarrer la session pour les messages
session_start();

// Inclure les fichiers nécessaires
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../models/User.php';

$error = '';
$success = '';

// Vérifier si le formulaire a été soumis
if($_SERVER['REQUEST_METHOD'] == 'POST') {
    
    // Récupérer les données
    $nom = $_POST['nom'] ?? '';
    $prenom = $_POST['prenom'] ?? '';
    $email = $_POST['email'] ?? '';
    $telephone = $_POST['telephone'] ?? '';
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    $role = $_POST['role'] ?? 'membre';
    
    // Validation
    if(empty($nom) || empty($prenom) || empty($email) || empty($telephone) || empty($password)) {
        $error = "Tous les champs sont obligatoires";
    } elseif($password != $confirm_password) {
        $error = "Les mots de passe ne correspondent pas";
    } elseif(strlen($password) < 6) {
        $error = "Le mot de passe doit contenir au moins 6 caractères";
    } else {
        // Tout est bon, on essaie d'inscrire
        
        $database = new Database();
        $db = $database->getConnection();
        
        $user = new User($db);
        
        // Vérifier si l'email existe déjà
        if($user->emailExists($email)) {
            $error = "Cet email est déjà utilisé";
        } else {
            // Créer l'utilisateur
            $user->nom = $nom;
            $user->prenom = $prenom;
            $user->email = $email;
            $user->telephone = $telephone;
            $user->password = $password;
            $user->role = $role;
            
            if($user->create()) {
                $success = "Inscription réussie ! Vous pouvez vous connecter.";
                // Vider le formulaire
                $_POST = array();
            } else {
                $error = "Erreur lors de l'inscription";
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
    <title>Inscription - Tontine</title>
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
        }
        .card-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            text-align: center;
            padding: 25px;
            border-bottom: none;
        }
        .card-header h2 {
            margin: 0;
            font-size: 28px;
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
        .form-control, .form-select {
            border-radius: 10px;
            border: 2px solid #e0e0e0;
            padding: 12px 15px;
            font-size: 15px;
            transition: all 0.3s;
        }
        .form-control:focus, .form-select:focus {
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
        .text-muted {
            color: #999 !important;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-md-8 col-lg-6">
                <div class="card">
                    <div class="card-header">
                        <h2>📝 Créer un compte</h2>
                        <p>Rejoignez votre tontine en ligne</p>
                    </div>
                    <div class="card-body">
                        
                        <?php if($error): ?>
                            <div class="alert alert-danger">
                                <strong>❌ Erreur :</strong> <?= htmlspecialchars($error) ?>
                            </div>
                        <?php endif; ?>
                        
                        <?php if($success): ?>
                            <div class="alert alert-success">
                                <strong>✅ Succès !</strong> <?= htmlspecialchars($success) ?>
                            </div>
                        <?php endif; ?>

                        <form method="POST" action="">
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Nom</label>
                                    <input type="text" name="nom" class="form-control" 
                                           value="<?= htmlspecialchars($_POST['nom'] ?? '') ?>" 
                                           placeholder="Votre nom" required>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Prénom</label>
                                    <input type="text" name="prenom" class="form-control" 
                                           value="<?= htmlspecialchars($_POST['prenom'] ?? '') ?>" 
                                           placeholder="Votre prénom" required>
                                </div>
                            </div>

                            <div class="mb-3">
                                <label class="form-label">Email</label>
                                <input type="email" name="email" class="form-control" 
                                       value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" 
                                       placeholder="exemple@email.com" required>
                            </div>

                            <div class="mb-3">
                                <label class="form-label">Téléphone</label>
                                <input type="tel" name="telephone" class="form-control" 
                                       value="<?= htmlspecialchars($_POST['telephone'] ?? '') ?>" 
                                       placeholder="6XXXXXXXX" required>
                                <small class="text-muted">Format: 691234567</small>
                            </div>

                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Mot de passe</label>
                                    <input type="password" name="password" class="form-control" 
                                           placeholder="••••••••" required>
                                    <small class="text-muted">Minimum 6 caractères</small>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Confirmer</label>
                                    <input type="password" name="confirm_password" class="form-control" 
                                           placeholder="••••••••" required>
                                </div>
                            </div>

                            <div class="mb-4">
                                <label class="form-label">Je suis</label>
                                <select name="role" class="form-select">
                                    <option value="membre" <?= ($_POST['role'] ?? '') == 'membre' ? 'selected' : '' ?>>👤 Membre de tontine</option>
                                    <option value="admin" <?= ($_POST['role'] ?? '') == 'admin' ? 'selected' : '' ?>>👑 Président de tontine</option>
                                </select>
                            </div>

                            <button type="submit" class="btn btn-primary w-100">
                                📝 S'inscrire
                            </button>
                            
                            <p class="text-center mt-4 mb-0">
                                Déjà un compte ? 
                                <a href="login.php" class="text-decoration-none" style="color: #667eea;">
                                    Connectez-vous ici
                                </a>
                            </p>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>