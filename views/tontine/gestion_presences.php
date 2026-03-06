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
        }
        .stats-card {
            background: white;
            border-radius: 10px;
            padding: 15px;
            text-align: center;
            box-shadow: 0 5px 20px rgba(0,0,0,0.05);
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
                <a class="nav-link" href="gerer_cotisations.php?seance_id=<?= $seance_id ?>">
                    <i class="bi bi-arrow-left"></i> Retour
                </a>
            </div>
        </div>
    </nav>

    <div class="container mt-5">
        <div class="row">
            <div class="col-md-10 offset-md-1">
                
                <div class="card mb-4">
                    <div class="card-header">
                        <h4 class="mb-0"><i class="bi bi-person-check"></i> Gestion des présences</h4>
                    </div>
                    <div class="card-body">
                        
                        <div class="row mb-4">
                            <div class="col-md-4">
                                <div class="stats-card">
                                    <h3><?= $nb_total ?></h3>
                                    <p class="text-muted">Total membres</p>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="stats-card" style="border-left: 4px solid #28a745;">
                                    <h3 class="text-success"><?= $nb_presents ?></h3>
                                    <p class="text-muted">Présents</p>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="stats-card" style="border-left: 4px solid #dc3545;">
                                    <h3 class="text-danger"><?= $nb_absents ?></h3>
                                    <p class="text-muted">Absents</p>
                                </div>
                            </div>
                        </div>

                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Ordre</th>
                                        <th>Membre</th>
                                        <th>Présent</th>
                                        <th>Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach($presences as $p): ?>
                                        <tr>
                                            <td><?= $p['ordre_tour'] ?></td>
                                            <td>
                                                <strong><?= htmlspecialchars($p['prenom'] . ' ' . $p['nom']) ?></strong>
                                            </td>
                                            <td>
                                                <?php if($p['est_present']): ?>
                                                    <span class="badge bg-success">Présent</span>
                                                <?php else: ?>
                                                    <span class="badge bg-danger">Absent</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <a href="?seance_id=<?= $seance_id ?>&set_presence=1&membre_id=<?= $p['membre_tontine_id'] ?>&value=1" 
                                                   class="present-btn <?= $p['est_present'] ? 'active' : '' ?>"
                                                   onclick="return confirm('Marquer comme présent ?')">
                                                    <i class="bi bi-check-lg"></i>
                                                </a>
                                                <a href="?seance_id=<?= $seance_id ?>&set_presence=1&membre_id=<?= $p['membre_tontine_id'] ?>&value=0" 
                                                   class="present-btn <?= !$p['est_present'] ? 'inactive' : '' ?>"
                                                   onclick="return confirm('Marquer comme absent ?')">
                                                    <i class="bi bi-x-lg"></i>
                                                </a>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>