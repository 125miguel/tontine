<?php
$created = $_GET['created'] ?? 0;
// ACTIVER L'AFFICHAGE DES ERREURS (ajoute ces 2 lignes)
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();

if(!isset($_SESSION['user_id']) || $_SESSION['user_role'] != 'admin') {
    header("Location: ../auth/login.php");
    exit();
}

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../models/Tontine.php';
require_once __DIR__ . '/../../models/MembreTontine.php';

$supprime = $_GET['supprime'] ?? 0;
$error = $_GET['error'] ?? 0;

// ... reste du code
$database = new Database();
$db = $database->getConnection();

$tontine = new Tontine($db);
$membreTontine = new MembreTontine($db);

// Récupérer les tontines de l'admin connecté
$stmt = $tontine->getByAdmin($_SESSION['user_id']);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mes tontines</title>
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
                <a class="nav-link" href="../dashboard.php">
                    <i class="bi bi-arrow-left"></i> Retour
                </a>
            </div>
        </div>
    </nav>

    <div class="container mt-5">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2> Mes tontines</h2>
            <a href="create.php" class="btn btn-success">
                <i class="bi bi-plus-circle"></i> Nouvelle tontine
            </a>
        </div>
        <?php if(isset($_SESSION['tontine_created'])): ?>
            <div class="alert alert-success"><?= $_SESSION['tontine_created'] ?></div>
            <?php unset($_SESSION['tontine_created']); ?>
        <?php endif; ?>
        
        <?php if($supprime == 1): ?>
            <div class="alert alert-success">Tontine supprimée avec succès !</div>
        <?php endif; ?>

        <?php if($error == 1): ?>
            <div class="alert alert-danger">Erreur lors de la suppression.</div>
        <?php endif; ?>

        <?php if($stmt->rowCount() == 0): ?>
            <div class="alert alert-info">
                Vous n'avez pas encore créé de tontine. 
                <a href="create.php">Créer votre première tontine</a>
            </div>
        <?php else: ?>
            <div class="row">
                <?php while($row = $stmt->fetch(PDO::FETCH_ASSOC)): 
                    $nbMembres = $membreTontine->countMembres($row['id']);
                ?>
                    <div class="col-md-6 mb-4">
                        <div class="card h-100">
                            <div class="card-header bg-primary text-white">
                                <h5 class="mb-0"><?= htmlspecialchars($row['nom']) ?></h5>
                            </div>
                            <div class="card-body">
                               <p class="card-text">
                                    <span class="badge bg-info mb-2"><?= $row['type_tontine'] ?></span><br>
                                    <strong> Montant:</strong> <?= number_format($row['montant_cotisation'], 0, ',', ' ') ?> FCFA<br>
                                    <strong> Réunion:</strong> <?= htmlspecialchars($row['jour_reunion']) ?><br>
                                    <strong> Membres:</strong> <?= $nbMembres ?><br>
                                    <strong> Prochaine réunion:</strong> <?= date('d/m/Y', strtotime($row['prochaine_reunion'])) ?>
                                </p>
                                <div class="d-flex justify-content-between">
                                    <a href="voir_membres.php?id=<?= $row['id'] ?>" class="btn btn-primary">
                                        <i class="bi bi-people"></i> Membres
                                    </a>
                                    <a href="ajouter_membre.php?id=<?= $row['id'] ?>" class="btn btn-success">
                                        <i class="bi bi-person-plus"></i> Ajouter
                                    </a>
                                    <a href="ouvrir_seance.php?id=<?= $row['id'] ?>" class="btn btn-info btn-sm">
                                        <i class="bi bi-play-circle"></i> Séance
                                    </a>
                                    <a href="../parametres/amendes.php?tontine_id=<?= $row['id'] ?>" class="btn btn-warning btn-sm">
                                        <i class="bi bi-exclamation-triangle"></i> Amendes
                                    </a>
                                    <a href="supprimer_tontine.php?id=<?= $row['id'] ?>" 
                                    class="btn btn-sm btn-danger"
                                    onclick="return confirm('Êtes-vous sûr de vouloir supprimer définitivement cette tontine ?\nToutes les données (membres, séances, cotisations, amendes) seront perdues.')">
                                        <i class="bi bi-trash"></i> Supprimer
                                    </a>
                                    <a href="../parametres/rappels.php?tontine_id=<?= $row['id'] ?>" class="btn btn-info btn-sm">
                                        <i class="bi bi-bell"></i> Rappels
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endwhile; ?>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>