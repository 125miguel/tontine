<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../models/User.php';

$error = '';
$success = '';

if($_SERVER['REQUEST_METHOD'] == 'POST') {
    
    $nom = $_POST['nom'] ?? '';
    $prenom = $_POST['prenom'] ?? '';
    $nom_association = $_POST['nom_association'] ?? '';
    $email = $_POST['email'] ?? '';
    $telephone = $_POST['telephone'] ?? '';
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    
    if(empty($nom) || empty($prenom) || empty($email) || empty($telephone) || empty($password)) {
        $error = "Tous les champs sont obligatoires";
    } elseif($password != $confirm_password) {
        $error = "Les mots de passe ne correspondent pas";
    } elseif(strlen($password) < 6) {
        $error = "Le mot de passe doit contenir au moins 6 caractères";
    } else {
        $database = new Database();
        $db = $database->getConnection();
        
        $user = new User($db);
        
        // Vérifier si l'email existe déjà
        if($user->emailExists($email)) {
            $error = "Cet email est déjà utilisé";
        } else {
            $user->nom = $nom;
            $user->prenom = $prenom;
            $user->nom_association = $nom_association;
            $user->email = $email;
            $user->telephone = $telephone;
            $user->password = $password;
            $user->role = 'admin';
            
           if($user->create()) {
                // Récupérer l'ID du nouvel utilisateur
                $nouvel_admin_id = $db->lastInsertId();
                
                // 1. Créer l'association
                $query = "INSERT INTO associations (nom, admin_id) VALUES (:nom, :admin_id)";
                $stmt = $db->prepare($query);
                $stmt->execute([
                    'nom' => $nom_association,
                    'admin_id' => $nouvel_admin_id
                ]);
                
                $association_id = $db->lastInsertId();
                
                // 2. Ajouter le président comme membre de sa propre association
                // Générer le même mot de passe pour l'association
                $hashed = password_hash($password, PASSWORD_DEFAULT);
                
                $query = "INSERT INTO membres_association (user_id, association_id, password, role) 
                          VALUES (:user_id, :association_id, :password, 'admin')";
                $stmt = $db->prepare($query);
                $stmt->execute([
                    'user_id' => $nouvel_admin_id,
                    'association_id' => $association_id,
                    'password' => $hashed
                ]);
                
                $_SESSION['register_success'] = "Inscription réussie ! Connectez-vous avec vos identifiants.";
                header("Location: login.php");
                exit();
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
    <title>Inscription Président - TONTONTINE</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        :root {
            --primary: #1E3A8A;        /* Bleu sombre */
            --primary-light: #3B5BA5;   /* Bleu plus clair */
            --white: #FFFFFF;
            --bg-light: #F8FAFC;
            --text-dark: #0F172A;
            --text-light: #475569;
            --border: #E2E8F0;
            --danger: #EF4444;
            --success: #10B981;
        }
        
        body {
            background: var(--primary);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            padding: 20px;
        }
        
        .card {
            border-radius: 20px;
            border: none;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            overflow: hidden;
            max-width: 600px;
            width: 100%;
        }
        
        .card-header {
            background: var(--primary);
            color: var(--white);
            text-align: center;
            padding: 30px 20px;
        }
        
        .card-header h2 {
            margin: 0;
            font-size: 28px;
            font-weight: 700;
        }
        
        .card-header p {
            margin: 10px 0 0;
            opacity: 0.9;
        }
        
        .card-body {
            padding: 40px;
            background: var(--white);
        }
        
        .form-control {
            border-radius: 10px;
            border: 2px solid var(--border);
            padding: 12px;
            font-size: 15px;
            transition: all 0.3s;
        }
        
        .form-control:focus {
            border-color: var(--primary);
            box-shadow: none;
            outline: none;
        }
        
        .btn-primary {
            background: var(--primary);
            border: none;
            border-radius: 10px;
            padding: 14px;
            font-size: 16px;
            font-weight: 600;
            transition: all 0.3s;
            width: 100%;
        }
        
        .btn-primary:hover {
            background: var(--primary-light);
            transform: translateY(-2px);
            box-shadow: 0 10px 30px rgba(30, 58, 138, 0.4);
        }
        
        .alert {
            border-radius: 10px;
            padding: 15px;
            margin-bottom: 25px;
            border: none;
        }
        
        .alert-danger {
            background: #FEE2E2;
            color: #991B1B;
        }
        
        .alert-success {
            background: #D1FAE5;
            color: #065F46;
        }
        
        .form-label {
            font-weight: 500;
            color: var(--text-dark);
            margin-bottom: 8px;
        }
        
        .text-muted {
            color: var(--text-light) !important;
        }
        
        .text-danger {
            color: var(--danger) !important;
        }
        
        .login-link {
            text-align: center;
            margin-top: 25px;
            padding-top: 20px;
            border-top: 1px solid var(--border);
        }
        
        .login-link a {
            color: var(--primary);
            text-decoration: none;
            font-weight: 500;
        }
        
        .login-link a:hover {
            color: var(--primary-light);
            text-decoration: underline;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-md-10">
                <div class="card">
                    <div class="card-header">
                        <h2> Inscription Président</h2>
                        <p>Créez votre compte pour gérer vos tontines</p>
                    </div>
                    <div class="card-body">
                        
                        <?php if($error): ?>
                            <div class="alert alert-danger">
                                <strong> Erreur :</strong> <?= htmlspecialchars($error) ?>
                            </div>
                        <?php endif; ?>
                        
                        <?php if($success): ?>
                            <div class="alert alert-success">
                                <strong> Succès !</strong> <?= htmlspecialchars($success) ?>
                            </div>
                        <?php endif; ?>

                        <form method="POST">
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
                                <label class="form-label">Nom de votre association <span class="text-danger">*</span></label>
                                <input type="text" name="nom_association" class="form-control" 
                                    value="<?= htmlspecialchars($_POST['nom_association'] ?? '') ?>"
                                    placeholder="Ex: Association des Mamans Fortes, Djangui des Amis..."
                                    required>
                                <small class="text-muted">Ce nom sera unique et identifiera votre association</small>
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
                            </div>

                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Mot de passe</label>
                                    <input type="password" name="password" class="form-control" 
                                           placeholder="••••••••" required>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Confirmer</label>
                                    <input type="password" name="confirm_password" class="form-control" 
                                           placeholder="••••••••" required>
                                </div>
                            </div>

                            <button type="submit" class="btn btn-primary">
                                S'inscrire
                            </button>
                            
                            <div class="login-link">
                                Déjà un compte ? <a href="login.php">Connectez-vous ici</a>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>