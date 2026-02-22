<?php
session_start();

if(!isset($_SESSION['user_id']) || $_SESSION['user_role'] != 'admin') {
    header("Location: ../auth/login.php");
    exit();
}

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../models/Tontine.php';
require_once __DIR__ . '/../../models/User.php';
require_once __DIR__ . '/../../models/MembreTontine.php';

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

// Recherche d'utilisateurs (MAINTENANT LES ADMIN AUSSI)
$search = $_GET['search'] ?? '';
$users = [];

if(!empty($search)) {
    $query = "SELECT * FROM users 
              WHERE (nom LIKE :search OR prenom LIKE :search OR email LIKE :search) 
              ORDER BY nom, prenom";  // PLUS DE "AND role = 'membre'"
    
    $stmt = $db->prepare($query);
    $searchTerm = "%$search%";
    $stmt->bindParam(":search", $searchTerm);
    $stmt->execute();
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Ajouter un membre
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
        } else {
            $error = "Erreur lors de l'ajout du membre";
        }
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
                <div class="card">
                    <div class="card-header bg-primary text-white">
                        <h4 class="mb-0"> Ajouter des membres à "<?= htmlspecialchars($tontine->nom) ?>"</h4>
                    </div>
                    <div class="card-body">
                        
                        <?php if($error): ?>
                            <div class="alert alert-danger"><?= $error ?></div>
                        <?php endif; ?>
                        
                        <?php if($success): ?>
                            <div class="alert alert-success"><?= $success ?></div>
                        <?php endif; ?>

                        <!-- Formulaire de recherche -->
                        <form method="GET" class="mb-4">
                            <input type="hidden" name="id" value="<?= $tontine_id ?>">
                            <div class="input-group">
                                <input type="text" name="search" class="form-control" 
                                       placeholder="Rechercher par nom, prénom ou email..."
                                       value="<?= htmlspecialchars($search) ?>">
                                <button type="submit" class="btn btn-primary">
                                    <i class="bi bi-search"></i> Rechercher
                                </button>
                            </div>
                        </form>

                        <?php if(!empty($search)): ?>
                            <h5 class="mb-3">Résultats de la recherche</h5>
                            
                            <?php if(empty($users)): ?>
                                <div class="alert alert-info">
                                    Aucun utilisateur trouvé. 
                                    <a href="../auth/register.php" target="_blank">Créer un nouveau compte</a>
                                </div>
                            <?php else: ?>
                                <div class="list-group">
                                    <?php foreach($users as $u): ?>
                                        <div class="list-group-item d-flex justify-content-between align-items-center">
                                            <div>
                                                <strong><?= htmlspecialchars($u['prenom'] . ' ' . $u['nom']) ?></strong><br>
                                                <small class="text-muted">
                                                    <i class="bi bi-envelope"></i> <?= htmlspecialchars($u['email']) ?> |
                                                    <i class="bi bi-telephone"></i> <?= htmlspecialchars($u['telephone']) ?>
                                                </small>
                                            </div>
                                            <a href="?id=<?= $tontine_id ?>&add_user=<?= $u['id'] ?>" 
                                               class="btn btn-success btn-sm"
                                               onclick="return confirm('Ajouter ce membre à la tontine ?')">
                                                <i class="bi bi-person-plus"></i> Ajouter
                                            </a>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>