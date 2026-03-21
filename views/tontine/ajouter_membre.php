<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();

if(!isset($_SESSION['user_id']) || $_SESSION['association_role'] != 'admin') {
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
$mode = $_GET['mode'] ?? 'normal';

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

// Traiter la création d'un nouveau membre
if($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['creer_membre'])) {
    
    $nom = $_POST['nom'] ?? '';
    $prenom = $_POST['prenom'] ?? '';
    $email = $_POST['email'] ?? '';
    $telephone = $_POST['telephone'] ?? '';
    $adresse = $_POST['adresse'] ?? '';
    $date_anniversaire = $_POST['date_anniversaire'] ?? null;
    
    if(empty($nom) || empty($prenom) || empty($email) || empty($telephone)) {
        $error = "Tous les champs sont obligatoires";
    } else {
        // Vérifier si l'email existe déjà
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
                header("Location: ajouter_membre.php?id=" . $tontine_id . "&mode=" . $mode . "&error=exist");
                exit();
            }
        } else {
            // CAS 3 : Créer un nouvel utilisateur
            $temp_password = genererMotDePasse(6);
            $hashed = password_hash($temp_password, PASSWORD_DEFAULT);
            
            $query = "INSERT INTO users (nom, prenom, email, telephone, adresse, password, role, premiere_connexion) 
                      VALUES (:nom, :prenom, :email, :telephone, :adresse, :password, 'membre', 1)";
            $stmt = $db->prepare($query);
            $result = $stmt->execute([
                'nom' => $nom,
                'prenom' => $prenom,
                'email' => $email,
                'telephone' => $telephone,
                'adresse' => $adresse,
                'password' => $hashed
            ]);
            
            if(!$result) {
                $error = "Erreur lors de la création de l'utilisateur";
            } else {
                $user_id = $db->lastInsertId();
            }
        }
        
        if(!isset($error) || empty($error)) {
            // Ajouter à l'association
            $temp_password = $temp_password ?? genererMotDePasse(6);
            $hashed = password_hash($temp_password, PASSWORD_DEFAULT);
            
            $query = "INSERT INTO membres_association (user_id, association_id, password, role) 
                      VALUES (:uid, :aid, :password, 'membre')";
            $stmt = $db->prepare($query);
            $result = $stmt->execute([
                'uid' => $user_id,
                'aid' => $association['id'],
                'password' => $hashed
            ]);
            
            if(!$result) {
                $error = "Erreur lors de l'ajout à l'association";
            } else {
                // Ajouter à la tontine
                $membreTontine->user_id = $user_id;
                $membreTontine->tontine_id = $tontine_id;
                $membreTontine->association_id = $association['id'];
                $membreTontine->ordre_tour = $membreTontine->getProchainOrdre($tontine_id);
                
                if($membreTontine->ajouterMembre()) {
                    // Ajouter la date d'anniversaire si la tontine est de type anniversaire
                    if($tontine->type_tontine == 'anniversaire' && !empty($date_anniversaire)) {
                        $query = "UPDATE membre_tontine SET date_anniversaire = :date WHERE id = :id";
                        $stmt = $db->prepare($query);
                        $stmt->execute(['date' => $date_anniversaire, 'id' => $membreTontine->id]);
                    }
                    
                    $_SESSION['temp_password'] = $temp_password;
                    $_SESSION['temp_user'] = $email;
                    
                    header("Location: " . $_SERVER['PHP_SELF'] . "?id=" . $tontine_id . "&mode=" . $mode . "&created=1");
                    exit();
                } else {
                    $error = "Erreur lors de l'ajout à la tontine";
                }
            }
        }
    }
}

// Ajouter un membre existant à la tontine (CAS 1)
if(isset($_GET['add_user'])) {
    $user_id = $_GET['add_user'];
    $need_birthday = isset($_GET['need_birthday']) ? 1 : 0;
    
    $membreTontine->user_id = $user_id;
    $membreTontine->tontine_id = $tontine_id;
    $membreTontine->association_id = $association['id'];
    
    if($membreTontine->estDejaMembre()) {
        $error = "Cet utilisateur est déjà membre de cette tontine";
    } else {
        $membreTontine->ordre_tour = $membreTontine->getProchainOrdre($tontine_id);
        
        if($membreTontine->ajouterMembre()) {
            // Récupérer l'ID du membre qui vient d'être ajouté
            $query = "SELECT id FROM membre_tontine 
                      WHERE user_id = :uid AND tontine_id = :tid AND association_id = :aid";
            $stmt = $db->prepare($query);
            $stmt->execute([
                'uid' => $user_id,
                'tid' => $tontine_id,
                'aid' => $association['id']
            ]);
            $new_membre = $stmt->fetch(PDO::FETCH_ASSOC);
            $new_membre_id = $new_membre['id'] ?? null;
            
            // Si c'est une tontine anniversaire et qu'on a besoin de la date
            if($tontine->type_tontine == 'anniversaire' && $need_birthday && $new_membre_id) {
                // Rediriger vers la page pour saisir la date
                header("Location: saisir_anniversaire.php?membre_id=" . $new_membre_id . "&tontine_id=" . $tontine_id);
                exit();
            } else {
                $success = "Membre ajouté avec succès à la tontine !";
                // Rester sur la page actuelle
            }
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
    $query = "SELECT * FROM users WHERE email = :search OR telephone = :search";
    $stmt = $db->prepare($query);
    $stmt->execute(['search' => $search]);
    $user_trouve = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if($user_trouve) {
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

// Compter les membres
$queryCount = "SELECT COUNT(*) as total FROM membre_tontine WHERE tontine_id = :tid AND est_actif = 1";
$stmtCount = $db->prepare($queryCount);
$stmtCount->execute(['tid' => $tontine_id]);
$membres_count = $stmtCount->fetch()['total'];
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
        :root {
            --primary: #1E3A8A;
            --primary-light: #3B5BA5;
            --white: #FFFFFF;
            --bg-light: #F8FAFC;
            --text-dark: #0F172A;
            --text-light: #475569;
            --border: #E2E8F0;
            --success: #10B981;
            --warning: #F59E0B;
            --warning-bg: #FEF3C7;
            --danger: #EF4444;
            --info: #3B82F6;
            --info-bg: #DBEAFE;
        }
        
        body {
            background: var(--bg-light);
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        .navbar {
            background: var(--primary);
        }
        
        .navbar-brand, .nav-link {
            color: var(--white) !important;
        }
        
        .card {
            border-radius: 15px;
            border: 1px solid var(--border);
            box-shadow: 0 5px 20px rgba(0,0,0,0.05);
        }
        
        .card-header {
            background: var(--primary);
            color: var(--white);
            border-radius: 15px 15px 0 0 !important;
        }
        
        .card-header.bg-success {
            background: var(--success) !important;
        }
        
        .btn-success {
            background: var(--success);
            border: none;
        }
        
        .btn-success:hover {
            background: #0E9F6E;
            transform: translateY(-2px);
            box-shadow: 0 10px 30px rgba(16, 185, 129, 0.3);
        }
        
        .btn-primary {
            background: var(--primary);
            border: none;
        }
        
        .btn-primary:hover {
            background: var(--primary-light);
        }
        
        .temp-password {
            background: var(--primary);
            color: var(--white);
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 20px;
        }
        
        .password-value {
            font-size: 24px;
            font-weight: bold;
            letter-spacing: 2px;
            background: var(--white);
            color: var(--primary);
            padding: 10px 20px;
            border-radius: 10px;
            display: inline-block;
        }
        
        .info-box {
            background: var(--info-bg);
            border-left: 4px solid var(--primary);
            padding: 15px;
            border-radius: 10px;
            margin-bottom: 20px;
        }
        
        .alert-info {
            background: var(--info-bg);
            color: var(--primary);
            border: none;
            border-radius: 10px;
        }
        
        .alert-success {
            background: #D1FAE5;
            color: #065F46;
            border: none;
            border-radius: 10px;
        }
        
        .alert-danger {
            background: #FEE2E2;
            color: #991B1B;
            border: none;
            border-radius: 10px;
        }
        
        .alert-warning {
            background: var(--warning-bg);
            color: #92400E;
            border: none;
            border-radius: 10px;
        }
        
        .form-control {
            border-radius: 10px;
            border: 2px solid var(--border);
            padding: 10px;
            transition: all 0.3s;
        }
        
        .form-control:focus {
            border-color: var(--primary);
            box-shadow: none;
            outline: none;
        }
        
        .text-muted {
            color: var(--text-light) !important;
        }
        
        .progress-bar {
            background-color: var(--primary);
        }
        
        .mode-badge {
            background: var(--warning);
            color: var(--text-dark);
            padding: 5px 15px;
            border-radius: 50px;
            font-size: 14px;
            font-weight: 600;
        }
        
        .type-badge {
            display: inline-block;
            padding: 5px 15px;
            border-radius: 50px;
            font-size: 12px;
            font-weight: 600;
            margin-left: 10px;
        }
        
        .badge-anniversaire { background: #FEF3C7; color: #92400E; }
        
        .birthday-icon {
            color: var(--warning);
            font-size: 20px;
            margin-right: 8px;
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
                <span class="nav-link">
                    <i class="bi bi-building"></i> <?= htmlspecialchars($association['nom']) ?>
                </span>
                <?php if($tontine->type_tontine == 'anniversaire'): ?>
                <span class="nav-link">
                    <span class="type-badge badge-anniversaire">
                        <i class="bi bi-gift"></i> Anniversaire
                    </span>
                </span>
                <?php endif; ?>
                <?php if($mode == 'manuel'): ?>
                <span class="nav-link">
                    <span class="mode-badge">
                        <i class="bi bi-pencil-square"></i> Mode manuel
                    </span>
                </span>
                <?php endif; ?>
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
                
                <!-- Messages de confirmation -->
                <?php if(isset($_GET['birthday_saved']) && $_GET['birthday_saved'] == 1): ?>
                    <div class="alert alert-success">
                        <i class="bi bi-check-circle-fill me-2"></i> 
                        Date d'anniversaire enregistrée avec succès !
                    </div>
                <?php endif; ?>

                <!-- Bannière spéciale pour les anniversaires -->
                <?php if($tontine->type_tontine == 'anniversaire'): ?>
                <div class="alert alert-warning d-flex align-items-center mb-4">
                    <i class="bi bi-gift-fill birthday-icon"></i>
                    <div>
                        <strong>Tontine Anniversaire</strong> - N'oubliez pas de saisir la date d'anniversaire de chaque membre.
                        Le système classera automatiquement les bénéficiaires par date la plus proche.
                    </div>
                </div>
                <?php endif; ?>

                <!-- Bannière mode manuel -->
                <?php if($mode == 'manuel'): ?>
                <div class="alert alert-info d-flex align-items-center mb-4">
                    <div class="me-3">
                        <i class="bi bi-info-circle-fill" style="font-size: 2rem;"></i>
                    </div>
                    <div>
                        <h5 class="mb-1"><i class="bi bi-pencil-square"></i> Mode manuel activé</h5>
                        <p class="mb-0">
                            Vous êtes en mode bénéficiaire manuel. Après avoir ajouté tous les membres, 
                            vous devrez définir l'ordre de passage des bénéficiaires.
                            <strong>Membres actuels : <?= $membres_count ?></strong>
                        </p>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Barre de progression -->
                <?php if($mode == 'manuel' && $membres_count > 0): ?>
                <div class="card mb-4">
                    <div class="card-body">
                        <div class="d-flex justify-content-between mb-2">
                            <span><i class="bi bi-people"></i> Membres ajoutés</span>
                            <span class="fw-bold"><?= $membres_count ?> membre<?= $membres_count > 1 ? 's' : '' ?></span>
                        </div>
                        <div class="progress" style="height: 10px;">
                            <div class="progress-bar" role="progressbar" 
                                 style="width: <?= min(100, $membres_count * 10) ?>%;" 
                                 aria-valuenow="<?= $membres_count ?>" 
                                 aria-valuemin="0" 
                                 aria-valuemax="10"></div>
                        </div>
                    </div>
                </div>
                <?php endif; ?>

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
                        <form method="GET" action="<?= $_SERVER['PHP_SELF'] ?>" class="mb-4">
                            <input type="hidden" name="id" value="<?= $tontine_id ?>">
                            <input type="hidden" name="mode" value="<?= $mode ?>">
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
                                    <?php if($tontine->type_tontine == 'anniversaire'): ?>
                                        <a href="<?= $_SERVER['PHP_SELF'] ?>?id=<?= $tontine_id ?>&mode=<?= $mode ?>&add_user=<?= $user_trouve['id'] ?>&need_birthday=1" 
                                           class="btn btn-success"
                                           onclick="return confirm('Ajouter ce membre à la tontine ?')">
                                            <i class="bi bi-calendar-plus"></i> Ajouter et saisir anniversaire
                                        </a>
                                    <?php else: ?>
                                        <a href="<?= $_SERVER['PHP_SELF'] ?>?id=<?= $tontine_id ?>&mode=<?= $mode ?>&add_user=<?= $user_trouve['id'] ?>" 
                                           class="btn btn-success"
                                           onclick="return confirm('Ajouter ce membre à la tontine ?')">
                                            <i class="bi bi-person-plus"></i> Ajouter à cette tontine
                                        </a>
                                    <?php endif; ?>
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
                                    <div class="card-header" style="background: var(--success); color: var(--white);">
                                        <h5 class="mb-0">
                                            <i class="bi bi-person-plus"></i> 
                                            Créer un nouveau membre pour votre association
                                        </h5>
                                    </div>
                                    <div class="card-body">
                                        <form method="POST" action="<?= $_SERVER['PHP_SELF'] ?>?id=<?= $tontine_id ?>&mode=<?= $mode ?>">
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
                                                
                                                <!-- Champ date d'anniversaire pour les tontines anniversaire -->
                                                <?php if($tontine->type_tontine == 'anniversaire'): ?>
                                                <div class="col-12 mb-3">
                                                    <label class="form-label">
                                                        <i class="bi bi-gift"></i> Date d'anniversaire
                                                    </label>
                                                    <input type="date" name="date_anniversaire" class="form-control" 
                                                           value="<?= date('Y-m-d') ?>" required>
                                                    <small class="text-muted">
                                                        Cette date servira à déterminer l'ordre des bénéficiaires 
                                                        (du plus proche au plus éloigné)
                                                    </small>
                                                </div>
                                                <?php endif; ?>
                                                
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

                <!-- Actions supplémentaires -->
                <?php if($tontine->type_tontine == 'anniversaire' && $membres_count > 0): ?>
                <div class="card mt-4" style="border-left: 4px solid var(--warning);">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h5 class="mb-1"><i class="bi bi-gift"></i> Classement par anniversaire</h5>
                                <p class="text-muted mb-0">
                                    Vous avez ajouté <strong><?= $membres_count ?> membre<?= $membres_count > 1 ? 's' : '' ?></strong>.
                                    Une fois tous les membres ajoutés, vous pourrez classer automatiquement 
                                    par ordre d'anniversaire (du plus proche au plus éloigné).
                                </p>
                            </div>
                            <a href="classer_anniversaire.php?tontine_id=<?= $tontine_id ?>" 
                               class="btn btn-warning btn-lg">
                                <i class="bi bi-sort-numeric-down"></i> Classer par anniversaire
                            </a>
                        </div>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Bouton pour passer à l'ordonnancement (mode manuel) -->
                <?php if($mode == 'manuel' && $membres_count > 0 && $tontine->type_tontine != 'anniversaire'): ?>
                <div class="card mt-4" style="border-left: 4px solid var(--primary);">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h5 class="mb-1"><i class="bi bi-sort-numeric-down"></i> Étape suivante</h5>
                                <p class="text-muted mb-0">
                                    Vous avez ajouté <strong><?= $membres_count ?> membre<?= $membres_count > 1 ? 's' : '' ?></strong>.
                                    Définissez maintenant l'ordre de passage des bénéficiaires.
                                </p>
                            </div>
                            <a href="ordonner_membres.php?tontine_id=<?= $tontine_id ?>" 
                               class="btn btn-primary btn-lg">
                                <i class="bi bi-sort-numeric-down"></i> Ordonner les bénéficiaires
                            </a>
                        </div>
                    </div>
                </div>
                <?php endif; ?>

                <?php if($mode == 'manuel' && $membres_count == 0): ?>
                <div class="alert alert-warning mt-4">
                    <i class="bi bi-exclamation-triangle"></i>
                    <strong>Attention :</strong> Vous n'avez pas encore ajouté de membres. 
                    Ajoutez au moins un membre avant de passer à l'ordonnancement.
                </div>
                <?php endif; ?>

            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>