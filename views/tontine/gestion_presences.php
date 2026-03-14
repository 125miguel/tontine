<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();

if(!isset($_SESSION['user_id']) || $_SESSION['user_role'] != 'admin') {
    header("Location: ../auth/login.php");
    exit();
}

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../models/Seance.php';
require_once __DIR__ . '/../../models/Tontine.php';
require_once __DIR__ . '/../../models/Presence.php';

$database = new Database();
$db = $database->getConnection();

$seance_id = $_GET['seance_id'] ?? 0;

if(!$seance_id) {
    header("Location: mes_tontines.php");
    exit();
}

$seance = new Seance($db);
if(!$seance->getById($seance_id)) {
    header("Location: mes_tontines.php");
    exit();
}

$tontine = new Tontine($db);
$tontine->getById($seance->tontine_id);

if($tontine->admin_id != $_SESSION['user_id']) {
    header("Location: ../auth/login.php");
    exit();
}

$presence = new Presence($db);

// Mettre à jour une présence
if(isset($_GET['set_presence'])) {
    $membre_id = $_GET['membre_id'] ?? 0;
    $value = $_GET['value'] ?? 1;
    $presence->setPresence($seance_id, $membre_id, $value);
    header("Location: gestion_presences.php?seance_id=" . $seance_id);
    exit();
}

$presences = $presence->getBySeance($seance_id);
$nb_presents = $presence->countPresences($seance_id);
$nb_absents = $presence->countAbsences($seance_id);
$nb_total = count($presences);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestion des présences</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.8.1/font/bootstrap-icons.css">
    <style>
        body {
            background-color: #f8f9fc;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        .navbar {
            background-color: #1E3A8A;
            padding: 15px 0;
            box-shadow: 0 2px 20px rgba(0,0,0,0.1);
        }
        .navbar-brand {
            color: white;
            font-size: 24px;
            font-weight: 700;
        }
        .navbar-brand:hover {
            color: #e0e0e0;
        }
        .nav-link {
            color: white !important;
        }
        .nav-link:hover {
            color: #e0e0e0 !important;
        }
        .card {
            border-radius: 15px;
            border: none;
            box-shadow: 0 10px 40px rgba(30, 58, 138, 0.1);
            margin-bottom: 20px;
        }
        .card-header {
            background-color: #1E3A8A;
            color: white;
            border-radius: 15px 15px 0 0 !important;
            font-weight: 600;
            border-bottom: none;
            padding: 15px 20px;
        }
        .card-header h4 {
            margin: 0;
            font-size: 1.25rem;
        }
        .card-header i {
            margin-right: 8px;
        }
        .present-btn {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 20px;
            cursor: pointer;
            transition: all 0.3s;
            border: none;
            text-decoration: none;
            margin: 0 5px;
        }
        .present-btn.active {
            background: #28a745;
            color: white;
        }
        .present-btn.inactive {
            background: #dc3545;
            color: white;
        }
        .present-btn:hover {
            transform: scale(1.1);
            text-decoration: none;
            color: white;
        }
        .stats-card {
            background: white;
            border-radius: 10px;
            padding: 20px;
            text-align: center;
            box-shadow: 0 5px 20px rgba(0,0,0,0.05);
            height: 100%;
            border: 1px solid #e9ecef;
        }
        .stats-card h3 {
            font-size: 32px;
            font-weight: 700;
            color: #2D3748;
            margin-bottom: 5px;
        }
        .stats-card p {
            color: #666;
            font-size: 14px;
            text-transform: uppercase;
            letter-spacing: 1px;
            margin: 0;
        }
        .table thead th {
            background-color: #f8f9fc;
            color: #1E3A8A;
            border-bottom: 2px solid #1E3A8A;
            font-weight: 600;
        }
        .badge {
            font-weight: 500;
            padding: 5px 10px;
            font-size: 12px;
        }
        .badge.bg-success {
            background-color: #28a745 !important;
        }
        .badge.bg-danger {
            background-color: #dc3545 !important;
        }
        .btn-retour {
            background: white;
            color: #1E3A8A;
            border-radius: 50px;
            padding: 8px 20px;
            font-weight: 600;
            text-decoration: none;
            transition: all 0.3s;
            border: 2px solid white;
            display: inline-block;
        }
        .btn-retour:hover {
            background: #1E3A8A;
            color: white;
            transform: translateY(-2px);
            box-shadow: 0 10px 30px rgba(0,0,0,0.2);
        }
        .btn-retour i {
            margin-right: 5px;
        }
        .table td {
            vertical-align: middle;
        }
    </style>
</head>
<body>
    <nav class="navbar navbar-expand-lg">
        <div class="container">
            <a class="navbar-brand" href="../dashboard.php">
                <i class="bi bi-bank2 me-2"></i>TONTONTINE
            </a>
            <div class="navbar-nav ms-auto">
                <a class="nav-link" href="gerer_cotisations.php?seance_id=<?= $seance_id ?>">
                    <i class="bi bi-arrow-left me-1"></i> Retour aux cotisations
                </a>
            </div>
        </div>
    </nav>

    <div class="container mt-5">
        <div class="row">
            <div class="col-md-10 offset-md-1">
                
                <!-- En-tête de la séance -->
                <div class="mb-4">
                    <h2 class="mb-2">Gestion des présences</h2>
                    <p class="text-muted">
                        Séance du <?= date('d/m/Y', strtotime($seance->date_seance)) ?> - 
                        Tontine : <strong><?= htmlspecialchars($tontine->nom) ?></strong>
                    </p>
                </div>

                <!-- Cartes de statistiques -->
                <div class="row mb-4">
                    <div class="col-md-4 mb-3">
                        <div class="stats-card">
                            <h3><?= $nb_total ?></h3>
                            <p>Total membres</p>
                        </div>
                    </div>
                    <div class="col-md-4 mb-3">
                        <div class="stats-card" style="border-top: 4px solid #28a745;">
                            <h3 class="text-success"><?= $nb_presents ?></h3>
                            <p>Présents</p>
                        </div>
                    </div>
                    <div class="col-md-4 mb-3">
                        <div class="stats-card" style="border-top: 4px solid #dc3545;">
                            <h3 class="text-danger"><?= $nb_absents ?></h3>
                            <p>Absents</p>
                        </div>
                    </div>
                </div>

                <!-- Tableau des présences -->
                <div class="card">
                    <div class="card-header">
                        <i class="bi bi-person-check"></i> Liste des membres
                    </div>
                    <div class="card-body">
                        <?php if(empty($presences)): ?>
                            <div class="alert alert-info">
                                <i class="bi bi-info-circle me-2"></i>
                                Aucun membre trouvé pour cette séance.
                            </div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead>
                                        <tr>
                                            <th>Ordre</th>
                                            <th>Membre</th>
                                            <th>Statut</th>
                                            <th class="text-center">Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach($presences as $p): ?>
                                            <tr>
                                                <td><strong>#<?= $p['ordre_tour'] ?></strong></td>
                                                <td>
                                                    <div class="d-flex align-items-center">
                                                        <i class="bi bi-person-circle me-2" style="color: #1E3A8A; font-size: 1.2rem;"></i>
                                                        <strong><?= htmlspecialchars($p['prenom'] . ' ' . $p['nom']) ?></strong>
                                                    </div>
                                                </td>
                                                <td>
                                                    <?php if($p['est_present']): ?>
                                                        <span class="badge bg-success">
                                                            <i class="bi bi-check-circle me-1"></i>Présent
                                                        </span>
                                                    <?php else: ?>
                                                        <span class="badge bg-danger">
                                                            <i class="bi bi-x-circle me-1"></i>Absent
                                                        </span>
                                                    <?php endif; ?>
                                                </td>
                                                <td class="text-center">
                                                    <a href="?seance_id=<?= $seance_id ?>&set_presence=1&membre_id=<?= $p['membre_tontine_id'] ?>&value=1" 
                                                       class="present-btn <?= $p['est_present'] ? 'active' : '' ?>"
                                                       onclick="return confirm('Marquer <?= htmlspecialchars($p['prenom'] . ' ' . $p['nom']) ?> comme présent ?')"
                                                       title="Marquer présent">
                                                        <i class="bi bi-check-lg"></i>
                                                    </a>
                                                    <a href="?seance_id=<?= $seance_id ?>&set_presence=1&membre_id=<?= $p['membre_tontine_id'] ?>&value=0" 
                                                       class="present-btn <?= !$p['est_present'] ? 'inactive' : '' ?>"
                                                       onclick="return confirm('Marquer <?= htmlspecialchars($p['prenom'] . ' ' . $p['nom']) ?> comme absent ?')"
                                                       title="Marquer absent">
                                                        <i class="bi bi-x-lg"></i>
                                                    </a>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>

                            <!-- Légende -->
                            <div class="mt-4 p-3 bg-light rounded">
                                <div class="d-flex align-items-center">
                                    <span class="me-3"><strong>Légende :</strong></span>
                                    <span class="me-3">
                                        <span class="present-btn active me-1" style="width: 30px; height: 30px;">
                                            <i class="bi bi-check-lg"></i>
                                        </span> Présent
                                    </span>
                                    <span>
                                        <span class="present-btn inactive me-1" style="width: 30px; height: 30px;">
                                            <i class="bi bi-x-lg"></i>
                                        </span> Absent
                                    </span>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Bouton de retour -->
                <div class="text-center mt-4">
                    <a href="gerer_cotisations.php?seance_id=<?= $seance_id ?>" class="btn-retour">
                        <i class="bi bi-arrow-left"></i> Retour à la gestion des cotisations
                    </a>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>