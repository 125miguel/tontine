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
require_once __DIR__ . '/../../models/MembreTontine.php';

$retire = $_GET['retire'] ?? 0;
$error = $_GET['error'] ?? 0;

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
$membres = $membreTontine->getMembresByTontine($tontine_id);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Membres de la tontine</title>
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
                <a class="nav-link" href="ajouter_membre.php?id=<?= $tontine_id ?>">
                    <i class="bi bi-person-plus"></i> Ajouter
                </a>
                <a class="nav-link" href="mes_tontines.php">
                    <i class="bi bi-arrow-left"></i> Retour
                </a>
            </div>
        </div>
    </nav>

    <div class="container mt-5">
        
        <!-- Messages de confirmation -->
        <?php if($retire == 1): ?>
            <div class="alert alert-success">Membre retiré avec succès !</div>
        <?php endif; ?>

        <?php if($error == 1): ?>
            <div class="alert alert-danger">Erreur lors du retrait du membre.</div>
        <?php endif; ?>

        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2><i class="bi bi-people"></i> Membres de "<?= htmlspecialchars($tontine->nom) ?>"</h2>
            <a href="ajouter_membre.php?id=<?= $tontine_id ?>" class="btn btn-success">
                <i class="bi bi-person-plus"></i> Ajouter un membre
            </a>
        </div>

        <?php if($membres->rowCount() == 0): ?>
            <div class="alert alert-info">
                <i class="bi bi-info-circle"></i> Aucun membre actif dans cette tontine pour le moment.
                <a href="ajouter_membre.php?id=<?= $tontine_id ?>" class="alert-link">Ajouter votre premier membre</a>
            </div>
        <?php else: ?>
            <div class="card">
                <div class="card-header bg-primary text-white">
                    <div class="row">
                        <div class="col-md-1">#</div>
                        <div class="col-md-3">Nom</div>
                        <div class="col-md-2">Contact</div>
                        <div class="col-md-2">Email</div>
                        <div class="col-md-1">Ordre</div>
                        <div class="col-md-1">Statut</div>
                        <div class="col-md-2">Actions</div>
                    </div>
                </div>
                <div class="card-body p-0">
                    <div class="list-group list-group-flush">
                        <?php 
                        $compteur = 1;
                        while($m = $membres->fetch(PDO::FETCH_ASSOC)): 
                        ?>
                            <div class="list-group-item">
                                <div class="row align-items-center">
                                    <div class="col-md-1"><?= $compteur++ ?></div>
                                    <div class="col-md-3">
                                        <strong><?= htmlspecialchars($m['prenom'] . ' ' . $m['nom']) ?></strong>
                                    </div>
                                    <div class="col-md-2"><?= htmlspecialchars($m['telephone']) ?></div>
                                    <div class="col-md-2"><?= htmlspecialchars($m['email']) ?></div>
                                    <div class="col-md-1">
                                        <span class="badge bg-info"><?= $m['ordre_tour'] ?></span>
                                    </div>
                                    <div class="col-md-1">
                                        <?php if($m['est_actif']): ?>
                                            <span class="badge bg-success">Actif</span>
                                        <?php else: ?>
                                            <span class="badge bg-secondary">Retiré</span>
                                        <?php endif; ?>
                                    </div>
                                    <div class="col-md-2">
                                        <?php if($m['est_actif']): ?>
                                            <a href="retirer_membre.php?id=<?= $m['id'] ?>&tontine_id=<?= $tontine_id ?>" 
                                               class="btn btn-sm btn-danger"
                                               onclick="return confirm('Retirer <?= htmlspecialchars($m['prenom'] . ' ' . $m['nom']) ?> de la tontine ?\nSon historique sera conservé.')">
                                                <i class="bi bi-person-x"></i> Retirer
                                            </a>
                                        <?php else: ?>
                                            <span class="text-muted">—</span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        <?php endwhile; ?>
                    </div>
                </div>
            </div>

            <div class="mt-4">
                <p class="text-muted">
                    <strong><i class="bi bi-people"></i> Total membres actifs:</strong> <?= $membres->rowCount() ?><br>
                    <strong><i class="bi bi-cash-stack"></i> Montant cotisation:</strong> <?= number_format($tontine->montant_cotisation, 0, ',', ' ') ?> FCFA<br>
                    <strong><i class="bi bi-calculator"></i> Total par réunion:</strong> <?= number_format($tontine->montant_cotisation * $membres->rowCount(), 0, ',', ' ') ?> FCFA
                </p>
            </div>
        <?php endif; ?>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>