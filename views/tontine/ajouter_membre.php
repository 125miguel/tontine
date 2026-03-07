<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();

if(!isset($_SESSION['user_id']) || $_SESSION['user_role'] != 'admin') {
    header("Location: ../auth/login.php");
    exit();
}

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../models/Tontine.php';
require_once __DIR__ . '/../../models/User.php';
require_once __DIR__ . '/../../models/MembreTontine.php';

/**
 * Générer un mot de passe aléatoire par défaut
 */
function genererMotDePasse($longueur = 8) {
    $caracteres = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
    $mot_de_passe = '';
    for ($i = 0; $i < $longueur; $i++) {
        $mot_de_passe .= $caracteres[rand(0, strlen($caracteres) - 1)];
    }
    return $mot_de_passe;
}

$database = new Database();
$db = $database->getConnection();

$tontine_id = $_GET['id'] ?? 0;

// Vérifier que la tontine appartient bien à cet admin
$tontine = new Tontine($db);
if(!$tontine->getById($tontine_id) || $tontine->admin_id != $_SESSION['user_id']) {
    header("Location: mes_tontines.php");
    exit();
}

// Récupérer l'association du président
$query = "SELECT id, nom FROM associations WHERE admin_id = :admin_id";
$stmt = $db->prepare($query);
$stmt->execute(['admin_id' => $_SESSION['user_id']]);
$association = $stmt->fetch(PDO::FETCH_ASSOC);

if(!$association) {
    header("Location: mes_tontines.php?error=no_association");
    exit();
}

$membreTontine = new MembreTontine($db);
$user = new User($db);

$error = '';
$success = '';

// Traiter la création d'un nouveau membre (CAS 2 et 3)
if($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['creer_membre'])) {
    
    $nom = $_POST['nom'] ?? '';
    $prenom = $_POST['prenom'] ?? '';
    $email = $_POST['email'] ?? '';
    $telephone = $_POST['telephone'] ?? '';
    $adresse = $_POST['adresse'] ?? '';
    
    if(empty($nom) || empty($prenom) || empty($email) || empty($telephone)) {
        $error = "Tous les champs sont obligatoires";
    } else {
        // Vérifier si l'email existe déjà (sécurité)
        $query = "SELECT id FROM users WHERE email = :email";
        $stmt = $db->prepare($query);
        $stmt->execute(['email' => $email]);
        $user_existant = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if($user_existant) {
            // CAS 2 : L'utilisateur existe déjà
            $user_id = $user_existant['id'];
            
            // Vérifier s'il est déjà dans l'association
            $query = "SELECT id FROM membres_association 
                      WHERE user_id = :uid AND association_id = :aid";
            $stmt = $db->prepare($query);
            $stmt->execute([
                'uid' => $user_id,
                'aid' => $association['id']
            ]);
            
            if($stmt->rowCount() > 0) {
                $error = "Cet utilisateur est déjà dans votre association. Veuillez utiliser la recherche.";
                header("Location: ajouter_membre.php?id=" . $tontine_id);
                exit();
            }
        } else {
            // CAS 3 : Créer un nouvel utilisateur
            $temp_password = genererMotDePasse(6);
            $hashed = password_hash($temp_password, PASSWORD_DEFAULT);
            
            $query = "INSERT INTO users (nom, prenom, email, telephone, adresse, password, role, premiere_connexion) 
                      VALUES (:nom, :prenom, :email, :telephone, :adresse, :password, 'membre', 1)";
            $stmt = $db->prepare($query);
            $stmt->execute([
                'nom' => $nom,
                'prenom' => $prenom,
                'email' => $email,
                'telephone' => $telephone,
                'adresse' => $adresse,
                'password' => $hashed
            ]);
            
            $user_id = $db->lastInsertId();
        }
        
        // Ajouter à l'association (pour CAS 2 et 3) avec rôle 'membre'
        $temp_password = $temp_password ?? genererMotDePasse(6);
        $hashed = password_hash($temp_password, PASSWORD_DEFAULT);
        
        $query = "INSERT INTO membres_association (user_id, association_id, password, role) 
                  VALUES (:uid, :aid, :password, 'membre')";
        $stmt = $db->prepare($query);
        $stmt->execute([
            'uid' => $user_id,
            'aid' => $association['id'],
            'password' => $hashed
        ]);
        
        // Ajouter à la tontine
        $membreTontine->user_id = $user_id;
        $membreTontine->tontine_id = $tontine_id;
        $membreTontine->association_id = $association['id'];
        $membreTontine->ordre_tour = $membreTontine->getProchainOrdre($tontine_id);
        $membreTontine->ajouterMembre();
        
        $_SESSION['temp_password'] = $temp_password;
        $_SESSION['temp_user'] = $email;
        
        header("Location: ajouter_membre.php?id=" . $tontine_id . "&created=1");
        exit();
    }
}

// Ajouter un membre existant à la tontine (CAS 1)
if(isset($_GET['add_user'])) {
    $user_id = $_GET['add_user'];
    
    // Vérifier si déjà membre de cette tontine
    $membreTontine->user_id = $user_id;
    $membreTontine->tontine_id = $tontine_id;
    $membreTontine->association_id = $association['id'];
    
    if($membreTontine->estDejaMembre()) {
        $error = "Cet utilisateur est déjà membre de cette tontine";
    } else {
        // Obtenir le prochain ordre
        $membreTontine->ordre_tour = $membreTontine->getProchainOrdre($tontine_id);
        
        if($membreTontine->ajouterMembre()) {
            $success = "Membre ajouté avec succès à la tontine !";
        } else {
            $error = "Erreur lors de l'ajout du membre";
        }
    }
}

// Recherche
$search = $_GET['search'] ?? '';
$user_trouve = null;
$est_dans_association = false;

if(!empty($search)) {
    // Chercher si l'email/téléphone existe déjà dans users
    $query = "SELECT * FROM users WHERE email = :search OR telephone = :search";
    $stmt = $db->prepare($query);
    $stmt->execute(['search' => $search]);
    $user_trouve = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if($user_trouve) {
        // Vérifier s'il est déjà dans l'association
        $query = "SELECT * FROM membres_association 
                  WHERE user_id = :uid AND association_id = :aid";
        $stmt = $db->prepare($query);
        $stmt->execute([
            'uid' => $user_trouve['id'],
            'aid' => $association['id']
        ]);
        $est_dans_association = $stmt->fetch(PDO::FETCH_ASSOC);
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ajouter des membres</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.8.1/font/bootstrap-icons.css">
    <style>
        body {
            background: linear-gradient(135deg, #f5f0ff 0%, #fff5f0 100%);
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        .navbar {
            background: linear-gradient(135deg, #6B46C1 0%, #FF8A4C 100%);
        }
        .card {
            border-radius: 15px;
            border: none;
            box-shadow: 0 10px 40px rgba(107, 70, 193, 0.1);
        }
        .card-header {
            background: linear-gradient(135deg, #6B46C1 0%, #FF8A4C 100%);
            color: white;
            border-radius: 15px 15px 0 0 !important;
        }
        .btn-success {
            background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
            border: none;
        }
        .btn-success:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 30px rgba(40, 167, 69, 0.3);
        }
        .temp-password {
            background: linear-gradient(135deg, #6B46C1 0%, #FF8A4C 100%);
            color: white;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 20px;
        }
        .password-value {
            font-size: 24px;
            font-weight: bold;
            letter-spacing: 2px;
            background: white;
            color: #6B46C1;
            padding: 10px 20px;
            border-radius: 10px;
            display: inline-block;
        }
        .info-box {
            background: #e7f5ff;
            border-left: 4px solid #0d6efd;
            padding: 15px;
            border-radius: 10px;
            margin-bottom: 20px;
        }
    </style>
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark">
        <div class="container">
            <a class="navbar-brand" href="../dashboard.php">
                <i class="bi bi-bank2"></i> TONTONTINE
            </a>
            <div class="navbar-nav ms-auto">
                <span class="nav-link text-white">
                    <i class="bi bi-building"></i> <?= htmlspecialchars($association['nom']) ?>
                </span>
                <a class="nav-link" href="voir_membres.php?id=<?= $tontine_id ?>">
                    <i class="bi bi-people"></i> Voir les membres
                </a>
                <a class="nav-link" href="mes_tontines.php">
                    <i class="bi bi-arrow-left"></i> Retour
                </a>
            </div>
        </div>
    </nav>

    <div class="container mt-5">
        <div class="row">
            <div class="col-md-8 offset-md-2">
                
                <!-- Message pour afficher le mot de passe temporaire -->
                <?php if(isset($_GET['created']) && isset($_SESSION['temp_password'])): ?>
                    <div class="temp-password">
                        <h5 class="mb-3"><i class="bi bi-check-circle"></i> Membre créé avec succès !</h5>
                        <p>
                            <strong>Email :</strong> <?= htmlspecialchars($_SESSION['temp_user']) ?><br>
                            <strong>Mot de passe temporaire :</strong> 
                            <span class="password-value"><?= $_SESSION['temp_password'] ?></span>
                        </p>
                        <p class="mb-0">
                            <small> À communiquer au membre pour qu'il se connecte à votre association.</small>
                        </p>
                    </div>
                    <?php 
                    unset($_SESSION['temp_password']);
                    unset($_SESSION['temp_user']);
                    ?>
                <?php endif; ?>

                <?php if($error): ?>
                    <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
                <?php endif; ?>
                
                <?php if($success): ?>
                    <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
                <?php endif; ?>

                <div class="card">
                    <div class="card-header">
                        <h4 class="mb-0"><i class="bi bi-person-plus"></i> Ajouter des membres à "<?= htmlspecialchars($tontine->nom) ?>"</h4>
                    </div>
                    <div class="card-body">
                        
                        <!-- Formulaire de recherche -->
                        <form method="GET" class="mb-4">
                            <input type="hidden" name="id" value="<?= $tontine_id ?>">
                            <div class="input-group">
                                <input type="text" name="search" class="form-control" 
                                       placeholder="Rechercher par nom, email ou téléphone..."
                                       value="<?= htmlspecialchars($search) ?>">
                                <button type="submit" class="btn btn-primary">
                                    <i class="bi bi-search"></i> Rechercher
                                </button>
                            </div>
                        </form>

                        <?php if(!empty($search)): ?>
                            <h5 class="mb-3">Résultat de la recherche</h5>
                            
                            <?php if($user_trouve && $est_dans_association): ?>
                                <!-- CAS 1 : Membre existe et est déjà dans l'association -->
                                <div class="alert alert-success">
                                    <p><strong> Membre trouvé dans votre association !</strong></p>
                                    <p>
                                        <strong>Nom :</strong> <?= htmlspecialchars($user_trouve['prenom'] . ' ' . $user_trouve['nom']) ?><br>
                                        <strong>Email :</strong> <?= htmlspecialchars($user_trouve['email']) ?><br>
                                        <strong>Téléphone :</strong> <?= htmlspecialchars($user_trouve['telephone']) ?><br>
                                        <?php if(!empty($user_trouve['adresse'])): ?>
                                            <strong>Adresse :</strong> <?= htmlspecialchars($user_trouve['adresse']) ?>
                                        <?php endif; ?>
                                    </p>
                                    <a href="?id=<?= $tontine_id ?>&add_user=<?= $user_trouve['id'] ?>" 
                                       class="btn btn-success"
                                       onclick="return confirm('Ajouter ce membre à la tontine ?')">
                                        <i class="bi bi-person-plus"></i> Ajouter à cette tontine
                                    </a>
                                </div>
                            <?php else: ?>
                                <!-- CAS 2 et 3 : Membre pas dans l'association ou inexistant -->
                                <div class="alert alert-warning">
                                    <p><i class="bi bi-exclamation-triangle"></i> 
                                    Aucun membre trouvé avec "<?= htmlspecialchars($search) ?>" dans votre association.</p>
                                    <p class="mb-0">Vous pouvez créer un nouveau membre avec ces informations.</p>
                                </div>
                                
                                <!-- Formulaire de création -->
                                <div class="card mt-3">
                                    <div class="card-header bg-success text-white">
                                        <h5 class="mb-0">Créer un nouveau membre pour votre association</h5>
                                    </div>
                                    <div class="card-body">
                                        <form method="POST">
                                            <input type="hidden" name="creer_membre" value="1">
                                            <div class="row">
                                                <div class="col-md-6 mb-3">
                                                    <label class="form-label">Nom</label>
                                                    <input type="text" name="nom" class="form-control" required>
                                                </div>
                                                <div class="col-md-6 mb-3">
                                                    <label class="form-label">Prénom</label>
                                                    <input type="text" name="prenom" class="form-control" required>
                                                </div>
                                                <div class="col-md-6 mb-3">
                                                    <label class="form-label">Email</label>
                                                    <input type="email" name="email" class="form-control" 
                                                           value="<?= htmlspecialchars($search) ?>" placeholder="exemple@email.com" required>
                                                </div>
                                                <div class="col-md-6 mb-3">
                                                    <label class="form-label">Téléphone</label>
                                                    <input type="tel" name="telephone" class="form-control" placeholder="6XXXXXXXX" required>
                                                </div>
                                                <div class="col-12 mb-3">
                                                    <label class="form-label">Adresse / Quartier (optionnel)</label>
                                                    <input type="text" name="adresse" class="form-control" placeholder="Ex: Bonanjo, Douala">
                                                </div>
                                                <div class="col-12">
                                                    <button type="submit" class="btn btn-success w-100">
                                                        <i class="bi bi-person-plus"></i> Créer et ajouter à l'association
                                                    </button>
                                                </div>
                                            </div>
                                        </form>
                                    </div>
                                </div>
                            <?php endif; ?>
                        <?php else: ?>
                            <div class="alert alert-info">
                                <i class="bi bi-search"></i> Utilisez le champ de recherche ci-dessus pour trouver ou créer des membres.
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>