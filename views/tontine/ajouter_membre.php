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

$membreTontine = new MembreTontine($db);
$user = new User($db);

$error = '';
$success = '';
$temp_password = '';

// Traitement du formulaire de création de nouveau membre
if($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['creer_membre'])) {
    
    $nom = $_POST['nom'] ?? '';
    $prenom = $_POST['prenom'] ?? '';
    $email = $_POST['email'] ?? '';
    $telephone = $_POST['telephone'] ?? '';
    
    if(empty($nom) || empty($prenom) || empty($email) || empty($telephone)) {
        $error = "Tous les champs sont obligatoires";
    } else {
        // Vérifier si l'email existe déjà
        $query = "SELECT id FROM users WHERE email = :email";
        $stmt = $db->prepare($query);
        $stmt->execute(['email' => $email]);
        
        if($stmt->rowCount() > 0) {
            $error = "Cet email existe déjà. Utilisez la recherche pour ajouter ce membre.";
        } else {
            // Générer un mot de passe temporaire
            $temp_password = genererMotDePasse(6);
            $hashed = password_hash($temp_password, PASSWORD_DEFAULT);
            
            // Créer l'utilisateur
            $query = "INSERT INTO users (nom, prenom, email, telephone, password, role, premiere_connexion) 
                      VALUES (:nom, :prenom, :email, :telephone, :password, 'membre', 1)";
            $stmt = $db->prepare($query);
            $stmt->execute([
                'nom' => $nom,
                'prenom' => $prenom,
                'email' => $email,
                'telephone' => $telephone,
                'password' => $hashed
            ]);
            
            $user_id = $db->lastInsertId();
            
            // Ajouter à la tontine
            $membreTontine->user_id = $user_id;
            $membreTontine->tontine_id = $tontine_id;
            $membreTontine->ordre_tour = $membreTontine->getProchainOrdre($tontine_id);
            $membreTontine->ajouterMembre();
            
            $_SESSION['temp_password'] = $temp_password;
            $_SESSION['temp_user'] = $email;
            
            header("Location: ajouter_membre.php?id=" . $tontine_id . "&created=1");
            exit();
        }
    }
}

// Ajouter un membre existant
if(isset($_GET['add_user'])) {
    $user_id = $_GET['add_user'];
    
    // Vérifier si déjà membre
    $membreTontine->user_id = $user_id;
    $membreTontine->tontine_id = $tontine_id;
    
    if($membreTontine->estDejaMembre()) {
        $error = "Cet utilisateur est déjà membre de cette tontine";
    } else {
        // Obtenir le prochain ordre
        $membreTontine->ordre_tour = $membreTontine->getProchainOrdre($tontine_id);
        
        if($membreTontine->ajouterMembre()) {
            $success = "Membre ajouté avec succès !";
            
            // Vérifier si c'est un nouveau membre qui n'a jamais eu de mot de passe
            $queryUser = "SELECT premiere_connexion FROM users WHERE id = :uid";
            $stmtUser = $db->prepare($queryUser);
            $stmtUser->execute(['uid' => $user_id]);
            $user_data = $stmtUser->fetch(PDO::FETCH_ASSOC);
            
            if($user_data && $user_data['premiere_connexion']) {
                // Récupérer l'email
                $queryEmail = "SELECT email FROM users WHERE id = :uid";
                $stmtEmail = $db->prepare($queryEmail);
                $stmtEmail->execute(['uid' => $user_id]);
                $email = $stmtEmail->fetch()['email'];
                
                // Générer un nouveau mot de passe
                $temp_password = genererMotDePasse(6);
                $hashed = password_hash($temp_password, PASSWORD_DEFAULT);
                
                $queryUpdate = "UPDATE users SET password = :password WHERE id = :uid";
                $stmtUpdate = $db->prepare($queryUpdate);
                $stmtUpdate->execute([
                    'password' => $hashed,
                    'uid' => $user_id
                ]);
                
                $_SESSION['temp_password'] = $temp_password;
                $_SESSION['temp_user'] = $email;
            }
        } else {
            $error = "Erreur lors de l'ajout du membre";
        }
    }
}

// Recherche d'utilisateurs
$search = $_GET['search'] ?? '';
$users = [];

if(!empty($search)) {
    $query = "SELECT * FROM users 
              WHERE email = :search OR nom LIKE :searchLike OR prenom LIKE :searchLike
              ORDER BY nom, prenom";
    
    $searchTerm = "%$search%";
    $stmt = $db->prepare($query);
    $stmt->execute([
        'search' => $search,
        'searchLike' => $searchTerm
    ]);
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
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
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary">
        <div class="container">
            <a class="navbar-brand" href="../dashboard.php">
                <i class="bi bi-bank2"></i> Tontine
            </a>
            <div class="navbar-nav ms-auto">
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
                
                <!-- Message de succès pour la création -->
                <?php if(isset($_GET['created']) && $_GET['created'] == 1): ?>
                    <div class="alert alert-success"> Membre créé et ajouté avec succès !</div>
                <?php endif; ?>
                
                <!-- Message pour afficher le mot de passe temporaire -->
                <?php if(isset($_SESSION['temp_password'])): ?>
                    <div class="alert alert-info">
                        <h5><i class="bi bi-key"></i> Mot de passe temporaire généré</h5>
                        <p>
                            <strong>Email :</strong> <?= htmlspecialchars($_SESSION['temp_user']) ?><br>
                            <strong>Mot de passe temporaire :</strong> 
                            <span class="badge bg-dark fs-6 p-2"><?= $_SESSION['temp_password'] ?></span>
                        </p>
                        <p class="mb-0">
                            <small>À communiquer au membre. Il devra changer son mot de passe à la première connexion.</small>
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

                <div class="card mb-4">
                    <div class="card-header bg-primary text-white">
                        <h4 class="mb-0"><i class="bi bi-person-plus"></i> Ajouter des membres à "<?= htmlspecialchars($tontine->nom) ?>"</h4>
                    </div>
                    <div class="card-body">
                        
                        <!-- Formulaire de recherche -->
                        <form method="GET" class="mb-4">
                            <input type="hidden" name="id" value="<?= $tontine_id ?>">
                            <div class="input-group">
                                <input type="text" name="search" class="form-control" 
                                       placeholder="Rechercher par email, nom ou prénom..."
                                       value="<?= htmlspecialchars($search) ?>">
                                <button type="submit" class="btn btn-primary">
                                    <i class="bi bi-search"></i> Rechercher
                                </button>
                            </div>
                            <small class="text-muted">Tapez l'email exact pour rechercher un membre existant</small>
                        </form>

                        <?php if(!empty($search)): ?>
                            <h5 class="mb-3">Résultats de la recherche</h5>
                            
                            <?php if(empty($users)): ?>
                                <div class="alert alert-info">
                                    <p><i class="bi bi-info-circle"></i> Aucun utilisateur trouvé avec "<?= htmlspecialchars($search) ?>"</p>
                                    <p class="mb-0">Vous pouvez créer un nouveau membre avec cet email :</p>
                                </div>
                                
                                <!-- Formulaire de création de nouveau membre -->
                                <div class="card mt-3">
                                    <div class="card-header bg-success text-white">
                                        <h5 class="mb-0">Créer un nouveau membre</h5>
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
                                                           value="<?= htmlspecialchars($search) ?>" readonly>
                                                </div>
                                                <div class="col-md-6 mb-3">
                                                    <label class="form-label">Téléphone</label>
                                                    <input type="tel" name="telephone" class="form-control" required>
                                                </div>
                                                <div class="col-12">
                                                    <button type="submit" class="btn btn-success w-100">
                                                        <i class="bi bi-person-plus"></i> Créer et ajouter ce membre
                                                    </button>
                                                </div>
                                            </div>
                                        </form>
                                    </div>
                                </div>
                                
                            <?php else: ?>
                                <div class="list-group">
                                    <?php foreach($users as $u): ?>
                                        <div class="list-group-item d-flex justify-content-between align-items-center">
                                            <div>
                                                <strong><?= htmlspecialchars($u['prenom'] . ' ' . $u['nom']) ?></strong>
                                                <?php if($u['role'] == 'admin'): ?>
                                                    <span class="badge bg-warning text-dark ms-2">Président</span>
                                                <?php endif; ?>
                                                <?php if($u['premiere_connexion']): ?>
                                                    <span class="badge bg-info ms-2">Nouveau</span>
                                                <?php endif; ?>
                                                <br>
                                                <small class="text-muted">
                                                    <i class="bi bi-envelope"></i> <?= htmlspecialchars($u['email']) ?> |
                                                    <i class="bi bi-telephone"></i> <?= htmlspecialchars($u['telephone']) ?>
                                                </small>
                                            </div>
                                            <a href="?id=<?= $tontine_id ?>&add_user=<?= $u['id'] ?>" 
                                               class="btn btn-success btn-sm"
                                               onclick="return confirm('Ajouter <?= htmlspecialchars($u['prenom'] . ' ' . $u['nom']) ?> à la tontine ?')">
                                                <i class="bi bi-person-plus"></i> Ajouter
                                            </a>
                                        </div>
                                    <?php endforeach; ?>
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
</body>
</html>