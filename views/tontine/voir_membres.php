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
require_once __DIR__ . '/../../models/MembreTontine.php';

$retire = $_GET['retire'] ?? 0;
$error = $_GET['error'] ?? 0;
$desactive = $_GET['desactive'] ?? 0;
$supprime = $_GET['supprime'] ?? 0;
$error_activites = $_GET['error'] ?? 0;

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

// Récupérer les membres avec leur adresse
$query = "SELECT m.*, u.nom, u.prenom, u.email, u.telephone, u.adresse 
          FROM membre_tontine m
          JOIN users u ON m.user_id = u.id
          WHERE m.tontine_id = :tontine_id
          ORDER BY m.ordre_tour ASC";
$stmt = $db->prepare($query);
$stmt->execute(['tontine_id' => $tontine_id]);
$membres = $stmt;
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Membres de la tontine - <?= htmlspecialchars($tontine->nom) ?></title>
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
        .btn-primary {
            background: linear-gradient(135deg, #6B46C1 0%, #FF8A4C 100%);
            border: none;
        }
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 30px rgba(107, 70, 193, 0.3);
        }
        .btn-success {
            background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
            border: none;
        }
        .btn-success:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 30px rgba(40, 167, 69, 0.3);
        }
        .btn-warning {
            background: linear-gradient(135deg, #ffc107 0%, #e0a800 100%);
            border: none;
            color: #333;
        }
        .btn-warning:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 30px rgba(255, 193, 7, 0.3);
        }
        .btn-danger {
            background: linear-gradient(135deg, #dc3545 0%, #c82333 100%);
            border: none;
        }
        .btn-danger:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 30px rgba(220, 53, 69, 0.3);
        }
        .btn-outline-warning {
            border: 2px solid #ffc107;
            color: #ffc107;
            background: transparent;
        }
        .btn-outline-warning:hover {
            background: linear-gradient(135deg, #ffc107 0%, #e0a800 100%);
            color: #333;
        }
        .btn-outline-danger {
            border: 2px solid #dc3545;
            color: #dc3545;
            background: transparent;
        }
        .btn-outline-danger:hover {
            background: linear-gradient(135deg, #dc3545 0%, #c82333 100%);
            color: white;
        }
        .badge-actif {
            background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
            color: white;
            padding: 5px 10px;
            border-radius: 20px;
            font-size: 12px;
        }
        .badge-inactif {
            background: linear-gradient(135deg, #6c757d 0%, #5a6268 100%);
            color: white;
            padding: 5px 10px;
            border-radius: 20px;
            font-size: 12px;
        }
        .badge-ordre {
            background: linear-gradient(135deg, #6B46C1 0%, #FF8A4C 100%);
            color: white;
            padding: 5px 10px;
            border-radius: 20px;
            font-size: 14px;
            font-weight: 600;
        }
        .alert {
            border-radius: 10px;
            border: none;
        }
        .table th {
            background: linear-gradient(135deg, #6B46C1 0%, #FF8A4C 100%);
            color: white;
            font-weight: 600;
        }
        .table td {
            vertical-align: middle;
        }
        .association-badge {
            background: rgba(255,255,255,0.2);
            padding: 5px 15px;
            border-radius: 50px;
            font-size: 14px;
            color: white;
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
                    <i class="bi bi-building"></i> <?= htmlspecialchars($_SESSION['association_nom']) ?>
                </span>
                <a class="nav-link text-white" href="ajouter_membre.php?id=<?= $tontine_id ?>">
                    <i class="bi bi-person-plus"></i> Ajouter
                </a>
                <a class="nav-link text-white" href="mes_tontines.php">
                    <i class="bi bi-arrow-left"></i> Retour
                </a>
            </div>
        </div>
    </nav>

    <div class="container mt-5">
        
        <!-- Messages de confirmation -->
        <?php if($retire == 1): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <i class="bi bi-check-circle-fill me-2"></i>  Membre retiré avec succès !
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <?php if($desactive == 1): ?>
            <div class="alert alert-warning alert-dismissible fade show" role="alert">
                <i class="bi bi-person-x-fill me-2"></i>  Membre désactivé avec succès (données conservées).
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <?php if($supprime == 1): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <i class="bi bi-trash-fill me-2"></i>  Membre supprimé définitivement.
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <?php if($error == 1): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <i class="bi bi-exclamation-triangle-fill me-2"></i>  Erreur lors du retrait du membre.
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <?php if($error_activites == 'activites'): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <i class="bi bi-exclamation-triangle-fill me-2"></i>  Impossible de supprimer : ce membre a déjà des activités (cotisations, amendes, bénéficiaire).
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2><i class="bi bi-people-fill"></i> Membres de "<?= htmlspecialchars($tontine->nom) ?>"</h2>
            <a href="ajouter_membre.php?id=<?= $tontine_id ?>" class="btn btn-success">
                <i class="bi bi-person-plus-fill"></i> Ajouter un membre
            </a>
        </div>

        <?php if($membres->rowCount() == 0): ?>
            <div class="alert alert-info">
                <i class="bi bi-info-circle-fill me-2"></i> Aucun membre dans cette tontine pour le moment.
                <a href="ajouter_membre.php?id=<?= $tontine_id ?>" class="alert-link">Ajouter votre premier membre</a>
            </div>
        <?php else: ?>
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0"><i class="bi bi-list-ul"></i> Liste des membres</h5>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead>
                                <tr>
                                    <th class="text-center">#</th>
                                    <th>Membre</th>
                                    <th>Contact</th>
                                    <th>Email</th>
                                    <th>Adresse</th>
                                    <th class="text-center">Ordre</th>
                                    <th class="text-center">Statut</th>
                                    <th class="text-center">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php 
                                $compteur = 1;
                                while($m = $membres->fetch(PDO::FETCH_ASSOC)): 
                                    $activites = $membreTontine->aDesActivites($m['id']);
                                ?>
                                    <tr>
                                        <td class="text-center"><?= $compteur++ ?></td>
                                        <td>
                                            <strong><?= htmlspecialchars($m['prenom'] . ' ' . $m['nom']) ?></strong>
                                        </td>
                                        <td><?= htmlspecialchars($m['telephone']) ?></td>
                                        <td><?= htmlspecialchars($m['email']) ?></td>
                                        <td><?= htmlspecialchars($m['adresse'] ?? '-') ?></td>
                                        <td class="text-center">
                                            <span class="badge-ordre"><?= $m['ordre_tour'] ?></span>
                                        </td>
                                        <td class="text-center">
                                            <?php if($m['est_actif']): ?>
                                                <span class="badge-actif"><i class="bi bi-check-circle"></i> Actif</span>
                                            <?php else: ?>
                                                <span class="badge-inactif"><i class="bi bi-slash-circle"></i> Inactif</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="text-center">
                                            <?php if($m['est_actif']): ?>
                                                <?php if($activites): ?>
                                                    <!-- Désactiver seulement (a des activités) -->
                                                    <a href="desactiver_membre.php?id=<?= $m['id'] ?>&tontine_id=<?= $tontine_id ?>" 
                                                       class="btn btn-outline-warning btn-sm"
                                                       onclick="return confirm('Désactiver <?= htmlspecialchars($m['prenom'] . ' ' . $m['nom']) ?> ?\nSes données seront conservées.')"
                                                       title="Désactiver (conserve l'historique)">
                                                        <i class="bi bi-person-x"></i>
                                                    </a>
                                                <?php else: ?>
                                                    <!-- Supprimer définitivement (pas d'activités) -->
                                                    <a href="supprimer_membre.php?id=<?= $m['id'] ?>&tontine_id=<?= $tontine_id ?>" 
                                                       class="btn btn-outline-danger btn-sm"
                                                       onclick="return confirm('Supprimer définitivement <?= htmlspecialchars($m['prenom'] . ' ' . $m['nom']) ?> ?\nCette action est irréversible.')"
                                                       title="Supprimer définitivement">
                                                        <i class="bi bi-trash"></i>
                                                    </a>
                                                <?php endif; ?>
                                            <?php else: ?>
                                                <span class="text-muted">—</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <div class="mt-4">
                <p class="text-muted">
                    <strong><i class="bi bi-people"></i> Total membres:</strong> <?= $membres->rowCount() ?><br>
                    <strong><i class="bi bi-cash-stack"></i> Montant cotisation:</strong> <?= number_format($tontine->montant_cotisation, 0, ',', ' ') ?> FCFA<br>
                    <strong><i class="bi bi-calculator"></i> Total par réunion:</strong> <?= number_format($tontine->montant_cotisation * $membres->rowCount(), 0, ',', ' ') ?> FCFA
                </p>
            </div>
        <?php endif; ?>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>