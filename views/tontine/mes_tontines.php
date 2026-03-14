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

$supprime = $_GET['supprime'] ?? 0;
$error = $_GET['error'] ?? 0;
$activites = $_GET['activites'] ?? 0;

$database = new Database();
$db = $database->getConnection();

$tontine = new Tontine($db);
$membreTontine = new MembreTontine($db);

// Récupérer les tontines de l'association active
$query = "SELECT * FROM tontines WHERE association_id = :aid ORDER BY created_at DESC";
$stmt = $db->prepare($query);
$stmt->execute(['aid' => $_SESSION['association_active']]);
$tontines = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mes tontines - <?= htmlspecialchars($_SESSION['association_nom']) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.8.1/font/bootstrap-icons.css">
    <style>
        :root {
            --primary: #1E3A8A;        /* Bleu sombre */
            --primary-light: #3B5BA5;   /* Bleu plus clair */
            --white: #FFFFFF;
            --bg-light: #F8FAFC;
            --text-dark: #0F172A;
            --text-light: #475569;
            --border: #E2E8F0;
            --success: #10B981;
            --warning: #F59E0B;
            --danger: #EF4444;
            --info: #3B82F6;
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
            margin-bottom: 20px;
            transition: all 0.3s;
        }
        
        .card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 30px rgba(30, 58, 138, 0.1);
            border-color: var(--primary);
        }
        
        .card-header {
            background: var(--primary);
            color: var(--white);
            border-radius: 15px 15px 0 0 !important;
        }
        
        .btn-primary {
            background: var(--primary);
            border: none;
        }
        
        .btn-primary:hover {
            background: var(--primary-light);
        }
        
        .btn-success {
            background: var(--success);
            border: none;
        }
        
        .btn-success:hover {
            background: #0E9F6E;
        }
        
        .btn-info {
            background: var(--info);
            border: none;
            color: var(--white);
        }
        
        .btn-info:hover {
            background: #2563EB;
        }
        
        .btn-warning {
            background: var(--warning);
            border: none;
            color: var(--white);
        }
        
        .btn-warning:hover {
            background: #D97706;
        }
        
        .btn-danger {
            background: var(--danger);
            border: none;
        }
        
        .btn-danger:hover {
            background: #DC2626;
        }
        
        .btn-outline-danger {
            border: 2px solid var(--danger);
            color: var(--danger);
            background: transparent;
        }
        
        .btn-outline-danger:hover {
            background: var(--danger);
            color: var(--white);
        }
        
        .danger-zone {
            border: 2px solid var(--danger);
            border-radius: 15px;
            padding: 20px;
            margin-top: 20px;
            background: rgba(239, 68, 68, 0.05);
        }
        
        .danger-zone h5 {
            color: var(--danger);
            font-weight: 700;
            margin-bottom: 15px;
        }
        
        .badge-type {
            font-size: 12px;
            padding: 5px 10px;
            border-radius: 20px;
            background: rgba(255,255,255,0.2);
            color: var(--white);
            margin-left: 10px;
        }
        
        .badge-success {
            background: var(--success);
            color: var(--white);
        }
        
        .badge-secondary {
            background: var(--text-light);
            color: var(--white);
        }
        
        .alert-success {
            background: #D1FAE5;
            color: #065F46;
            border: none;
        }
        
        .alert-danger {
            background: #FEE2E2;
            color: #991B1B;
            border: none;
        }
        
        .alert-warning {
            background: #FEF3C7;
            color: #92400E;
            border: none;
        }
        
        .alert-info {
            background: #DBEAFE;
            color: var(--primary);
            border: none;
        }
        
        .association-badge {
            background: rgba(255,255,255,0.2);
            padding: 5px 15px;
            border-radius: 50px;
            font-size: 14px;
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
                    <i class="bi bi-building"></i> <?= htmlspecialchars($_SESSION['association_nom']) ?>
                </span>
                <a class="nav-link" href="../dashboard.php">
                    <i class="bi bi-arrow-left"></i> Retour
                </a>
            </div>
        </div>
    </nav>

    <div class="container mt-5">
        
        <!-- Messages de notification -->
        <?php if($supprime == 1): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <i class="bi bi-check-circle-fill me-2"></i>  Tontine supprimée avec succès !
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <?php if($error == 1): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <i class="bi bi-exclamation-triangle-fill me-2"></i>  Erreur lors de la suppression.
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <?php if($activites == 1): ?>
            <div class="alert alert-warning alert-dismissible fade show" role="alert">
                <i class="bi bi-info-circle-fill me-2"></i>  Suppression impossible : cette tontine a déjà des activités.
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2><i class="bi bi-list-ul"></i> Mes tontines</h2>
            <a href="create.php" class="btn btn-success">
                <i class="bi bi-plus-circle"></i> Nouvelle tontine
            </a>
        </div>

        <?php if(empty($tontines)): ?>
            <div class="alert alert-info">
                <i class="bi bi-info-circle"></i> Vous n'avez pas encore créé de tontine. 
                <a href="create.php" class="alert-link">Créer votre première tontine</a>
            </div>
        <?php else: ?>
            <div class="row">
                <?php foreach($tontines as $row): 
                    $nbMembres = $membreTontine->countMembres($row['id']);
                ?>
                    <div class="col-md-6 mb-4">
                        <div class="card h-100">
                            <div class="card-header">
                                <div class="d-flex justify-content-between align-items-center">
                                    <h5 class="mb-0">
                                        <?= htmlspecialchars($row['nom']) ?>
                                        <span class="badge-type"><?= $row['type_tontine'] ?></span>
                                    </h5>
                                    <?php if($row['mode_beneficiaire'] == 'auto'): ?>
                                        <span class="badge badge-success"> Auto</span>
                                    <?php else: ?>
                                        <span class="badge badge-secondary"> Manuel</span>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <div class="card-body">
                                <p class="card-text">
                                    <strong> Montant:</strong> <?= number_format($row['montant_cotisation'], 0, ',', ' ') ?> FCFA<br>
                                    <strong> Réunion:</strong> <?= htmlspecialchars($row['jour_reunion']) ?><br>
                                    <strong> Membres:</strong> <?= $nbMembres ?><br>
                                    <strong> Prochaine réunion:</strong> <?= date('d/m/Y', strtotime($row['prochaine_reunion'])) ?>
                                </p>

                                <!-- Actions rapides -->
                                <div class="d-flex flex-wrap gap-2 mb-3">
                                    <a href="voir_membres.php?id=<?= $row['id'] ?>" class="btn btn-primary btn-sm">
                                        <i class="bi bi-people"></i> Membres
                                    </a>
                                    <a href="ajouter_membre.php?id=<?= $row['id'] ?>" class="btn btn-success btn-sm">
                                        <i class="bi bi-person-plus"></i> Ajouter
                                    </a>
                                    <a href="ouvrir_seance.php?id=<?= $row['id'] ?>" class="btn btn-info btn-sm">
                                        <i class="bi bi-play-circle"></i> Séance
                                    </a>
                                    <a href="../parametres/amendes.php?tontine_id=<?= $row['id'] ?>" class="btn btn-warning btn-sm">
                                        <i class="bi bi-exclamation-triangle"></i> Amendes
                                    </a>
                                    <a href="../parametres/rappels.php?tontine_id=<?= $row['id'] ?>" class="btn btn-info btn-sm">
                                        <i class="bi bi-bell"></i> Rappels
                                    </a>
                                    <a href="../etats/etats_administrateur.php?tontine_id=<?= $row['id'] ?>" class="btn btn-info btn-sm">
                                        <i class="bi bi-file-text"></i> États
                                    </a>
                                </div>

                                <!-- Zone de danger pour la suppression -->
                                <?php
                                $tontine->getById($row['id']);
                                if(!$tontine->aDesActivites()):
                                ?>
                                    <div class="danger-zone">
                                        <p class="text-muted small mb-3">Cette tontine n'a aucune activité. Vous pouvez la supprimer définitivement.</p>
                                        <a href="supprimer_tontine.php?id=<?= $row['id'] ?>" 
                                           class="btn btn-outline-danger btn-sm"
                                           onclick="return confirm(' Êtes-vous sûr de vouloir supprimer définitivement cette tontine ?\nCette action est irréversible.')">
                                            <i class="bi bi-trash"></i> Supprimer cette tontine
                                        </a>
                                    </div>
                                <?php else: ?>
                                    <div class="alert alert-warning mt-3 mb-0">
                                        <i class="bi bi-info-circle"></i>
                                        <strong>Suppression impossible :</strong> Cette tontine a déjà des activités.
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>