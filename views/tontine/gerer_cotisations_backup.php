<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
// ... reste du code
session_start();

if(!isset($_SESSION['user_id']) || $_SESSION['user_role'] != 'admin') {
    header("Location: ../auth/login.php");
    exit();
}

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../models/Seance.php';
require_once __DIR__ . '/../../models/Cotisation.php';
require_once __DIR__ . '/../../models/Tontine.php';

$database = new Database();
$db = $database->getConnection();

$seance_id = $_GET['seance_id'] ?? 0;

$seance = new Seance($db);
if(!$seance->getById($seance_id)) {
    header("Location: mes_tontines.php");
    exit();
}

// Récupérer la tontine pour vérifier les droits
$tontine = new Tontine($db);
$tontine->getById($seance->tontine_id);

if($tontine->admin_id != $_SESSION['user_id']) {
    header("Location: mes_tontines.php");
    exit();
}

$cotisation = new Cotisation($db);

// Traiter le marquage d'un paiement
if(isset($_GET['payer'])) {
    $cotisation_id = $_GET['payer'];
    $cotisation->updateStatut($cotisation_id, 'paye', date('Y-m-d'));
    
    // Recalculer le total de la séance
    $total = $cotisation->calculerTotalSeance($seance_id);
    
    // Rediriger pour éviter la resoumission du formulaire
    header("Location: gerer_cotisations.php?seance_id=" . $seance_id);
    exit();
}

// Traiter le marquage d'un retard
if(isset($_GET['retard'])) {
    $cotisation_id = $_GET['retard'];
    $cotisation->updateStatut($cotisation_id, 'retard', date('Y-m-d'));
    
    $total = $cotisation->calculerTotalSeance($seance_id);
    
    header("Location: gerer_cotisations.php?seance_id=" . $seance_id);
    exit();
}

// Récupérer les cotisations de la séance
$cotisations = $cotisation->getBySeance($seance_id);
$total_collecte = $cotisation->calculerTotalSeance($seance_id);
$nb_payes = $cotisation->countPayes($seance_id);
$nb_total = $cotisations->rowCount();
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gérer les cotisations</title>
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
                <a class="nav-link" href="mes_tontines.php">
                    <i class="bi bi-arrow-left"></i> Retour
                </a>
            </div>
        </div>
    </nav>

    <div class="container mt-5">
        <div class="row">
            <div class="col-md-10 offset-md-1">
                <div class="card">
                    <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
                        <h4 class="mb-0">💰 Gestion des cotisations</h4>
                        <span class="badge bg-light text-dark">
                            Séance du <?= date('d/m/Y', strtotime($seance->date_seance)) ?>
                        </span>
                    </div>
                    <div class="card-body">
                        
                        <!-- Résumé de la séance -->
                        <div class="row mb-4">
                            <div class="col-md-4">
                                <div class="alert alert-info text-center">
                                    <strong>Total collecté</strong>
                                    <h2><?= number_format($total_collecte, 0, ',', ' ') ?> F</h2>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="alert alert-success text-center">
                                    <strong>Payés</strong>
                                    <h2><?= $nb_payes ?> / <?= $nb_total ?></h2>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="alert alert-warning text-center">
                                    <strong>En attente</strong>
                                    <h2><?= $nb_total - $nb_payes ?></h2>
                                </div>
                            </div>
                        </div>

                        <!-- Liste des membres -->
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead class="table-light">
                                    <tr>
                                        <th>Ordre</th>
                                        <th>Membre</th>
                                        <th>Montant</th>
                                        <th>Statut</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php while($c = $cotisations->fetch(PDO::FETCH_ASSOC)): ?>
                                        <tr>
                                            <td><?= $c['ordre_tour'] ?></td>
                                            <td>
                                                <strong><?= htmlspecialchars($c['prenom'] . ' ' . $c['nom']) ?></strong>
                                            </td>
                                            <td><?= number_format($c['montant'], 0, ',', ' ') ?> F</td>
                                            <td>
                                                <?php if($c['statut'] == 'paye'): ?>
                                                    <span class="badge bg-success">Payé</span>
                                                <?php elseif($c['statut'] == 'retard'): ?>
                                                    <span class="badge bg-warning">Retard</span>
                                                <?php else: ?>
                                                    <span class="badge bg-secondary">En attente</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?php if($c['statut'] != 'paye'): ?>
                                                    <a href="?seance_id=<?= $seance_id ?>&payer=<?= $c['id'] ?>" 
                                                       class="btn btn-sm btn-success"
                                                       onclick="return confirm('Confirmer le paiement ?')">
                                                        <i class="bi bi-check-circle"></i> Payer
                                                    </a>
                                                    <a href="?seance_id=<?= $seance_id ?>&retard=<?= $c['id'] ?>" 
                                                       class="btn btn-sm btn-warning"
                                                       onclick="return confirm('Marquer comme retard ?')">
                                                        <i class="bi bi-clock"></i> Retard
                                                    </a>
                                                <?php else: ?>
                                                    <span class="text-muted">✓ Payé</span>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endwhile; ?>
                                </tbody>
                            </table>
                        </div>

                        <?php if($nb_payes == $nb_total && $nb_total > 0): ?>
                            <div class="alert alert-success mt-3">
                                <strong>✅ Tous les membres ont payé !</strong>
                                <p>Vous pouvez maintenant désigner le bénéficiaire.</p>
                                <a href="designer_beneficiaire.php?seance_id=<?= $seance_id ?>" 
                                   class="btn btn-primary">
                                    Désigner le bénéficiaire
                                </a>
                            
                            </div>
                                   
                        <?php endif; ?>

                        <?php if($seance->est_cloturee): ?>
                            <div class="alert alert-info mt-3">
                                Cette séance est clôturée.
                            </div>
                        <?php endif; ?>
                                                <!-- Bouton pour désigner le bénéficiaire même avec des impayés -->
                        <div class="text-center mt-4">
                            <a href="designer_beneficiaire.php?seance_id=<?= $seance_id ?>" 
                               class="btn btn-warning">
                                <i class="bi bi-trophy"></i> Désigner le bénéficiaire (même avec des impayés)
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>