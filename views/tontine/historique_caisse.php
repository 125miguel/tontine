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

$database = new Database();
$db = $database->getConnection();

$tontine_id = $_GET['tontine_id'] ?? 0;

$tontine = new Tontine($db);
if(!$tontine->getById($tontine_id) || $tontine->admin_id != $_SESSION['user_id']) {
    header("Location: mes_tontines.php");
    exit();
}

// Récupérer l'historique
$query = "SELECT o.*, 
          CASE 
              WHEN o.type_operation = 'cotisation' THEN 'Cotisation'
              WHEN o.type_operation = 'amende' THEN 'Amende'
              WHEN o.type_operation = 'aide' THEN 'Aide versée'
              WHEN o.type_operation = 'pret' THEN 'Prêt accordé'
              WHEN o.type_operation = 'remboursement' THEN 'Remboursement'
              ELSE o.type_operation
          END as type_label
          FROM operations_caisse o
          WHERE o.tontine_id = :tid
          ORDER BY o.date_operation DESC";
$stmt = $db->prepare($query);
$stmt->execute(['tid' => $tontine_id]);
$operations = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Calculer les totaux
$total_entrees = 0;
$total_sorties = 0;

foreach($operations as $op) {
    if(in_array($op['type_operation'], ['cotisation', 'amende', 'remboursement'])) {
        $total_entrees += $op['montant'];
    } elseif(in_array($op['type_operation'], ['aide', 'pret'])) {
        $total_sorties += $op['montant'];
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Historique de la caisse - <?= htmlspecialchars($tontine->nom) ?></title>
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
        }
        
        .card-header {
            background: var(--primary);
            color: var(--white);
            border-radius: 15px 15px 0 0 !important;
        }
        
        .stat-card {
            background: var(--white);
            border-radius: 12px;
            padding: 20px;
            text-align: center;
            border: 1px solid var(--border);
        }
        
        .stat-value {
            font-size: 28px;
            font-weight: 700;
        }
        
        .stat-value.positive { color: var(--success); }
        .stat-value.negative { color: var(--danger); }
        
        .table th {
            background: var(--primary);
            color: var(--white);
        }
        
        .badge-entree {
            background: #D1FAE5;
            color: #065F46;
            padding: 5px 10px;
            border-radius: 20px;
        }
        
        .badge-sortie {
            background: #FEE2E2;
            color: #991B1B;
            padding: 5px 10px;
            border-radius: 20px;
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
                <span class="nav-link">
                    <i class="bi bi-building me-1"></i> <?= htmlspecialchars($_SESSION['association_nom']) ?>
                </span>
                <a class="nav-link" href="voir_membres.php?id=<?= $tontine_id ?>">
                    <i class="bi bi-arrow-left me-1"></i> Retour
                </a>
            </div>
        </div>
    </nav>

    <div class="container mt-5">
        <div class="row">
            <div class="col-lg-10 mx-auto">
                
                <h2 class="mb-4"><i class="bi bi-clock-history"></i> Historique de la caisse</h2>
                <p class="text-muted mb-4"><?= htmlspecialchars($tontine->nom) ?></p>
                
                <!-- Statistiques -->
                <div class="row mb-4">
                    <div class="col-md-4 mb-3">
                        <div class="stat-card">
                            <i class="bi bi-piggy-bank" style="font-size: 32px; color: var(--primary);"></i>
                            <h5 class="mt-2">Solde actuel</h5>
                            <div class="stat-value" style="color: var(--primary);">
                                <?= number_format($tontine->solde_caisse, 0, ',', ' ') ?> F
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4 mb-3">
                        <div class="stat-card">
                            <i class="bi bi-arrow-down-circle" style="font-size: 32px; color: var(--success);"></i>
                            <h5 class="mt-2">Total entrées</h5>
                            <div class="stat-value positive">
                                <?= number_format($total_entrees, 0, ',', ' ') ?> F
                            </div>
                            <small>Cotisations + Amendes + Remboursements</small>
                        </div>
                    </div>
                    <div class="col-md-4 mb-3">
                        <div class="stat-card">
                            <i class="bi bi-arrow-up-circle" style="font-size: 32px; color: var(--danger);"></i>
                            <h5 class="mt-2">Total sorties</h5>
                            <div class="stat-value negative">
                                <?= number_format($total_sorties, 0, ',', ' ') ?> F
                            </div>
                            <small>Aides + Prêts accordés</small>
                        </div>
                    </div>
                </div>
                
                <!-- Tableau des opérations -->
                <div class="card">
                    <div class="card-header">
                        <i class="bi bi-list-ul"></i> Détail des opérations
                    </div>
                    <div class="card-body p-0">
                        <?php if(empty($operations)): ?>
                            <div class="p-4 text-center text-muted">
                                <i class="bi bi-inbox"></i> Aucune opération enregistrée
                            </div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-hover mb-0">
                                    <thead>
                                        <tr>
                                            <th>Date</th>
                                            <th>Type</th>
                                            <th>Description</th>
                                            <th class="text-end">Montant</th>
                                        </thead>
                                        <tbody>
                                            <?php foreach($operations as $op): 
                                                $est_entree = in_array($op['type_operation'], ['cotisation', 'amende', 'remboursement']);
                                            ?>
                                                <tr>
                                                    <td><?= date('d/m/Y H:i', strtotime($op['date_operation'])) ?></td>
                                                    <td>
                                                        <?php if($est_entree): ?>
                                                            <span class="badge-entree">+ <?= $op['type_label'] ?></span>
                                                        <?php else: ?>
                                                            <span class="badge-sortie">- <?= $op['type_label'] ?></span>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td><?= htmlspecialchars($op['description']) ?></td>
                                                    <td class="text-end <?= $est_entree ? 'text-success' : 'text-danger' ?>">
                                                        <?= ($est_entree ? '+' : '-') ?> <?= number_format($op['montant'], 0, ',', ' ') ?> F
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
                
                <div class="text-center mt-4">
                    <a href="voir_membres.php?id=<?= $tontine_id ?>" class="btn btn-outline-secondary">
                        <i class="bi bi-arrow-left"></i> Retour à la tontine
                    </a>
                </div>
                
            </div>
        </div>
    </div>
</body>
</html>