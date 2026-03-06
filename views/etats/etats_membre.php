<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();

if(!isset($_SESSION['user_id']) || $_SESSION['user_role'] != 'membre') {
    header("Location: ../auth/login.php");
    exit();
}

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../models/User.php';
require_once __DIR__ . '/../../models/Tontine.php';
require_once __DIR__ . '/../../models/MembreTontine.php';
require_once __DIR__ . '/../../models/Cotisation.php';
require_once __DIR__ . '/../../models/Seance.php';
require_once __DIR__ . '/../../models/AmendeAppliquee.php';

$database = new Database();
$db = $database->getConnection();

$user = new User($db);
$user->getById($_SESSION['user_id']);

$association_active = $_SESSION['association_active'];
$tontine_id = $_GET['tontine_id'] ?? 0;

if(!$tontine_id) {
    header("Location: ../dashboard.php");
    exit();
}

// Vérifier que le membre appartient bien à cette tontine
$query = "SELECT mt.* FROM membre_tontine mt
          WHERE mt.user_id = :user_id AND mt.tontine_id = :tontine_id AND mt.est_actif = 1";
$stmt = $db->prepare($query);
$stmt->execute([
    'user_id' => $_SESSION['user_id'],
    'tontine_id' => $tontine_id
]);
$membre_tontine = $stmt->fetch(PDO::FETCH_ASSOC);

if(!$membre_tontine) {
    header("Location: ../dashboard.php");
    exit();
}

$tontine = new Tontine($db);
$tontine->getById($tontine_id);

// 1. RELEVÉ PERSONNEL
// Total cotisé
$query = "SELECT SUM(montant) as total FROM cotisations 
          WHERE membre_tontine_id = :mid AND statut = 'paye'";
$stmt = $db->prepare($query);
$stmt->execute(['mid' => $membre_tontine['id']]);
$total_cotise = $stmt->fetch()['total'] ?? 0;

// Nombre de cycles complétés (séances où il a payé)
$query = "SELECT COUNT(*) as nb FROM cotisations 
          WHERE membre_tontine_id = :mid AND statut = 'paye'";
$stmt = $db->prepare($query);
$stmt->execute(['mid' => $membre_tontine['id']]);
$cycles_completes = $stmt->fetch()['nb'];

// Tours déjà reçus (comme bénéficiaire)
$query = "SELECT COUNT(*) as nb FROM seances 
          WHERE beneficiaire_id = :mid";
$stmt = $db->prepare($query);
$stmt->execute(['mid' => $membre_tontine['id']]);
$tours_recus = $stmt->fetch()['nb'];

// Prochain tour prévu
$prochain_tour = null;
if($tontine->mode_beneficiaire == 'auto') {
    // Logique du prochain bénéficiaire
    $query = "SELECT beneficiaire_id FROM seances 
              WHERE tontine_id = :tid AND beneficiaire_id IS NOT NULL 
              ORDER BY date_seance DESC LIMIT 1";
    $stmt = $db->prepare($query);
    $stmt->execute(['tid' => $tontine_id]);
    $dernier = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if($dernier) {
        if($dernier['beneficiaire_id'] == $membre_tontine['id']) {
            $prochain_tour = "C'était votre tour !";
        } else {
            $prochain_tour = "À venir";
        }
    } else {
        $prochain_tour = "Premier tour";
    }
} else {
    $prochain_tour = "Mode manuel";
}

// Retards éventuels
$query = "SELECT COUNT(*) as nb FROM cotisations 
          WHERE membre_tontine_id = :mid AND statut = 'retard'";
$stmt = $db->prepare($query);
$stmt->execute(['mid' => $membre_tontine['id']]);
$retards = $stmt->fetch()['nb'];

// 2. CALENDRIER DES PAIEMENTS
// Historique des paiements
$query = "SELECT c.*, s.date_seance 
          FROM cotisations c
          JOIN seances s ON c.seance_id = s.id
          WHERE c.membre_tontine_id = :mid
          ORDER BY s.date_seance DESC";
$stmt = $db->prepare($query);
$stmt->execute(['mid' => $membre_tontine['id']]);
$historique_paiements = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Prochain paiement (prochaine séance)
$query = "SELECT * FROM seances 
          WHERE tontine_id = :tid AND date_seance >= CURDATE()
          ORDER BY date_seance ASC LIMIT 1";
$stmt = $db->prepare($query);
$stmt->execute(['tid' => $tontine_id]);
$prochaine_seance = $stmt->fetch(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mes états - <?= htmlspecialchars($tontine->nom) ?></title>
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
            margin-bottom: 20px;
        }
        .card-header {
            background: linear-gradient(135deg, #6B46C1 0%, #FF8A4C 100%);
            color: white;
            border-radius: 15px 15px 0 0 !important;
        }
        .stat-card {
            background: white;
            border-radius: 15px;
            padding: 20px;
            text-align: center;
            box-shadow: 0 5px 20px rgba(0,0,0,0.05);
            height: 100%;
        }
        .stat-number {
            font-size: 32px;
            font-weight: 700;
            color: #6B46C1;
        }
        .stat-label {
            color: #666;
            font-size: 14px;
            text-transform: uppercase;
        }
        .table th {
            background: #f8f9fa;
        }
        .badge-paye { background: #28a745; color: white; }
        .badge-retard { background: #ffc107; color: black; }
        .badge-impaye { background: #dc3545; color: white; }
        .section-title {
            margin: 30px 0 20px;
            font-weight: 600;
            color: #333;
            border-bottom: 2px solid #6B46C1;
            padding-bottom: 10px;
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
                    <i class="bi bi-person-circle"></i> <?= htmlspecialchars($user->prenom . ' ' . $user->nom) ?>
                </span>
                <a class="nav-link text-white" href="../dashboard.php">
                    <i class="bi bi-arrow-left"></i> Retour
                </a>
            </div>
        </div>
    </nav>

    <div class="container mt-5">
        
        <h2 class="mb-4"><i class="bi bi-file-text"></i> Mes états - <?= htmlspecialchars($tontine->nom) ?></h2>

        <!-- 1. RELEVE PERSONNEL -->
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0"><i class="bi bi-person-badge"></i> Relevé personnel</h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-3 mb-3">
                        <div class="stat-card">
                            <div class="stat-number"><?= number_format($total_cotise, 0, ',', ' ') ?> F</div>
                            <div class="stat-label">Total cotisé</div>
                        </div>
                    </div>
                    <div class="col-md-3 mb-3">
                        <div class="stat-card">
                            <div class="stat-number"><?= $cycles_completes ?></div>
                            <div class="stat-label">Cycles complétés</div>
                        </div>
                    </div>
                    <div class="col-md-3 mb-3">
                        <div class="stat-card">
                            <div class="stat-number"><?= $tours_recus ?></div>
                            <div class="stat-label">Tours reçus</div>
                        </div>
                    </div>
                    <div class="col-md-3 mb-3">
                        <div class="stat-card">
                            <div class="stat-number"><?= $retards ?></div>
                            <div class="stat-label">Retards</div>
                        </div>
                    </div>
                </div>
                
                <div class="alert alert-info mt-3">
                    <i class="bi bi-info-circle"></i>
                    <strong>Prochain tour :</strong> <?= $prochain_tour ?>
                </div>
            </div>
        </div>

        <!-- 2. CALENDRIER DES PAIEMENTS -->
        <div class="card mt-4">
            <div class="card-header">
                <h5 class="mb-0"><i class="bi bi-calendar-check"></i> Historique des paiements</h5>
            </div>
            <div class="card-body">
                <?php if(empty($historique_paiements)): ?>
                    <p class="text-muted">Aucun paiement enregistré pour le moment.</p>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Date séance</th>
                                    <th>Montant</th>
                                    <th>Statut</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach($historique_paiements as $p): ?>
                                    <tr>
                                        <td><?= date('d/m/Y', strtotime($p['date_seance'])) ?></td>
                                        <td><?= number_format($p['montant'], 0, ',', ' ') ?> F</td>
                                        <td>
                                            <?php if($p['statut'] == 'paye'): ?>
                                                <span class="badge badge-paye">Payé</span>
                                            <?php elseif($p['statut'] == 'retard'): ?>
                                                <span class="badge badge-retard">Retard</span>
                                            <?php else: ?>
                                                <span class="badge badge-impaye">Impayé</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>

                <?php if($prochaine_seance): ?>
                    <div class="alert alert-warning mt-3">
                        <i class="bi bi-exclamation-triangle"></i>
                        <strong>Prochain paiement :</strong> <?= date('d/m/Y', strtotime($prochaine_seance['date_seance'])) ?> - 
                        Montant : <?= number_format($tontine->montant_cotisation, 0, ',', ' ') ?> F
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Bouton retour -->
        <div class="text-center mt-4">
            <a href="../dashboard.php" class="btn btn-primary">
                <i class="bi bi-arrow-left"></i> Retour au tableau de bord
            </a>
        </div>

    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>