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

// ========== 1. RÉCUPÉRER L'ID DE LA TONTINE ==========
$tontine_id = $_GET['tontine_id'] ?? 0;

// ========== 2. TRAITEMENT DU REMBOURSEMENT ==========
if(isset($_GET['rembourser'])) {
    $echeance_id = (int)$_GET['rembourser'];
    
    // Récupérer l'échéance avec les infos du prêt
    $queryEch = "SELECT e.*, p.tontine_id, p.id as pret_id
                 FROM echeances_prets e
                 JOIN prets p ON e.pret_id = p.id
                 WHERE e.id = :id";
    $stmtEch = $db->prepare($queryEch);
    $stmtEch->execute(['id' => $echeance_id]);
    $echeance = $stmtEch->fetch(PDO::FETCH_ASSOC);
    
    if($echeance && $echeance['statut'] == 'en_attente') {
        // Mettre à jour l'échéance
        $query = "UPDATE echeances_prets 
                  SET montant_paye = montant_du, 
                      date_paiement = NOW(), 
                      statut = 'paye'
                  WHERE id = :id";
        $stmt = $db->prepare($query);
        $result = $stmt->execute(['id' => $echeance_id]);
        
        if($result) {
            // Mettre à jour le solde
            $tontine_temp = new Tontine($db);
            $tontine_temp->getById($echeance['tontine_id']);
            $tontine_temp->updateSoldeCaisse($echeance['montant_du'], 'ajout');
            $tontine_temp->enregistrerOperation(
                'remboursement', 
                $echeance['montant_du'], 
                "Remboursement d'échéance n°" . $echeance['numero_echeance']
            );
            
            // Vérifier si toutes les échéances sont payées
            $queryCheck = "SELECT COUNT(*) as total, 
                                  SUM(CASE WHEN statut = 'paye' THEN 1 ELSE 0 END) as paye
                           FROM echeances_prets 
                           WHERE pret_id = :pid";
            $stmtCheck = $db->prepare($queryCheck);
            $stmtCheck->execute(['pid' => $echeance['pret_id']]);
            $stats = $stmtCheck->fetch(PDO::FETCH_ASSOC);
            
            // Si toutes les échéances sont payées, marquer le prêt comme remboursé
            if($stats['total'] == $stats['paye']) {
                $query = "UPDATE prets SET statut = 'rembourse' WHERE id = :id";
                $stmt = $db->prepare($query);
                $stmt->execute(['id' => $echeance['pret_id']]);
                $_SESSION['success_message'] = "Prêt entièrement remboursé !";
            } else {
                $_SESSION['success_message'] = "Remboursement de " . number_format($echeance['montant_du'], 0, ',', ' ') . " F effectué !";
            }
        } else {
            $_SESSION['error_message'] = "Erreur lors du remboursement";
        }
        
        $redirect_id = $echeance['tontine_id'];
    } else {
        $_SESSION['error_message'] = "Échéance non trouvée ou déjà payée";
        $redirect_id = $tontine_id;
    }
    
    // Redirection vers la page de gestion des prêts
    header("Location: gerer_prets.php?tontine_id=" . $redirect_id);
    exit();
}

// ========== 3. VÉRIFICATION DE LA TONTINE ==========
$tontine = new Tontine($db);
if(!$tontine->getById($tontine_id) || $tontine->admin_id != $_SESSION['user_id']) {
    header("Location: mes_tontines.php");
    exit();
}

if($tontine->type_tontine != 'pret') {
    header("Location: voir_membres.php?id=" . $tontine_id . "&error=not_pret");
    exit();
}

// ========== 4. RÉCUPÉRATION DES MESSAGES ==========
$error = $_SESSION['error_message'] ?? '';
$success = $_SESSION['success_message'] ?? '';
unset($_SESSION['error_message']);
unset($_SESSION['success_message']);

// ========== 5. TRAITEMENT DU FORMULAIRE DE PRÊT ==========
if($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['creer_pret'])) {
    $membre_id = $_POST['membre_id'] ?? 0;
    $montant = $_POST['montant'] ?? 0;
    $taux_interet = $_POST['taux_interet'] ?? 0;
    $duree = $_POST['duree'] ?? 0;
    
    if($membre_id && $montant > 0 && $taux_interet >= 0 && $duree > 0) {
        $result = $tontine->creerPret($membre_id, $montant, $taux_interet, $duree);
        if($result['success']) {
            $success = $result['message'];
            $tontine->getById($tontine_id);
        } else {
            $error = $result['message'];
        }
    } else {
        $error = "Veuillez remplir tous les champs correctement";
    }
}

// ========== 6. RÉCUPÉRATION DES DONNÉES ==========
$queryMembres = "SELECT mt.id, u.nom, u.prenom 
                 FROM membre_tontine mt
                 JOIN users u ON mt.user_id = u.id
                 WHERE mt.tontine_id = :tid AND mt.est_actif = 1
                 ORDER BY u.nom, u.prenom";
$stmtMembres = $db->prepare($queryMembres);
$stmtMembres->execute(['tid' => $tontine_id]);
$membres = $stmtMembres->fetchAll(PDO::FETCH_ASSOC);

$prets_actifs = $tontine->getPrets('actif');
$prets_rembourses = $tontine->getPrets('rembourse');
$tontine->verifierPretsEnRetard();
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestion des prêts - <?= htmlspecialchars($tontine->nom) ?></title>
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
        
        .form-control, .form-select {
            border-radius: 10px;
            border: 2px solid var(--border);
            padding: 12px;
        }
        
        .form-control:focus, .form-select:focus {
            border-color: var(--primary);
            box-shadow: none;
            outline: none;
        }
        
        .solde-box {
            background: linear-gradient(135deg, var(--primary) 0%, var(--primary-light) 100%);
            color: var(--white);
            border-radius: 15px;
            padding: 20px;
            text-align: center;
            margin-bottom: 20px;
        }
        
        .solde-value {
            font-size: 36px;
            font-weight: 700;
        }
        
        .badge-actif {
            background: var(--success);
            color: var(--white);
            padding: 5px 10px;
            border-radius: 20px;
        }
        
        .badge-rembourse {
            background: var(--info);
            color: var(--white);
            padding: 5px 10px;
            border-radius: 20px;
        }
        
        .badge-retard {
            background: var(--warning);
            color: var(--white);
            padding: 5px 10px;
            border-radius: 20px;
        }
        
        .echeance-item {
            border-left: 4px solid var(--primary);
            transition: all 0.2s;
            margin-bottom: 5px;
        }
        
        .echeance-paye {
            border-left-color: var(--success);
            background: #D1FAE5;
        }
        
        .echeance-retard {
            border-left-color: var(--warning);
            background: #FEF3C7;
        }
        
        .alert-success {
            background: #D1FAE5;
            color: #065F46;
            border: none;
            border-radius: 10px;
        }
        
        .alert-danger {
            background: #FEE2E2;
            color: #991B1B;
            border: none;
            border-radius: 10px;
        }
        
        .progress {
            height: 10px;
            border-radius: 5px;
        }
        
        .pret-card {
            border: 1px solid var(--border);
            border-radius: 12px;
            margin-bottom: 15px;
            overflow: hidden;
        }
        
        .pret-card .pret-header {
            background: var(--bg-light);
            padding: 12px 15px;
            border-bottom: 1px solid var(--border);
        }
        
        .pret-card .pret-body {
            padding: 15px;
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
                <span class="nav-link">
                    <i class="bi bi-building me-1"></i> <?= htmlspecialchars($_SESSION['association_nom']) ?>
                </span>
                <span class="nav-link">
                    <span class="badge bg-info">Prêt avec intérêts</span>
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
                
                <h2 class="mb-4"><i class="bi bi-cash-stack"></i> Gestion des prêts</h2>
                <p class="text-muted mb-4"><?= htmlspecialchars($tontine->nom) ?></p>
                
                <div class="solde-box">
                    <i class="bi bi-piggy-bank" style="font-size: 32px;"></i>
                    <h3 class="mt-2">Solde disponible</h3>
                    <div class="solde-value"><?= number_format($tontine->solde_caisse, 0, ',', ' ') ?> FCFA</div>
                </div>
                
                <?php if($error): ?>
                    <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
                <?php endif; ?>
                
                <?php if($success): ?>
                    <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
                <?php endif; ?>
                
                <!-- Formulaire de prêt -->
                <div class="card">
                    <div class="card-header">
                        <i class="bi bi-plus-circle"></i> Accorder un nouveau prêt
                    </div>
                    <div class="card-body">
                        <form method="POST">
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Membre</label>
                                    <select name="membre_id" class="form-select" required>
                                        <option value="">Sélectionner un membre</option>
                                        <?php foreach($membres as $m): ?>
                                            <option value="<?= $m['id'] ?>"><?= htmlspecialchars($m['prenom'] . ' ' . $m['nom']) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Montant (FCFA)</label>
                                    <input type="number" name="montant" class="form-control" min="1000" step="1000" required>
                                </div>
                                
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Taux d'intérêt (%)</label>
                                    <input type="number" name="taux_interet" class="form-control" value="5" step="0.5" min="0" required>
                                </div>
                                
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Durée (mois)</label>
                                    <input type="number" name="duree" class="form-control" min="1" max="36" value="6" required>
                                </div>
                                
                                <div class="col-12">
                                    <button type="submit" name="creer_pret" class="btn btn-primary w-100">
                                        <i class="bi bi-cash-stack"></i> Accorder le prêt
                                    </button>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
                
                <!-- Prêts actifs -->
                <div class="card mt-4">
                    <div class="card-header">
                        <i class="bi bi-clock-history"></i> Prêts en cours (<?= count($prets_actifs) ?>)
                    </div>
                    <div class="card-body">
                        <?php if(empty($prets_actifs)): ?>
                            <div class="text-center text-muted py-4">Aucun prêt en cours</div>
                        <?php else: ?>
                            <?php foreach($prets_actifs as $pret): 
                                $echeances = $tontine->getEcheancesPret($pret['id']);
                                $total_rembourse = 0;
                                foreach($echeances as $e) {
                                    if($e['statut'] == 'paye') {
                                        $total_rembourse += $e['montant_du'];
                                    }
                                }
                                $progression = round(($total_rembourse / $pret['montant_total_du']) * 100, 1);
                            ?>
                                <div class="pret-card">
                                    <div class="pret-header">
                                        <div class="d-flex justify-content-between align-items-center">
                                            <div>
                                                <strong><?= htmlspecialchars($pret['prenom'] . ' ' . $pret['nom']) ?></strong>
                                                <small class="text-muted ms-2">Prêt du <?= date('d/m/Y', strtotime($pret['date_octroi'])) ?></small>
                                            </div>
                                            <div class="text-end">
                                                <span class="text-primary"><?= number_format($pret['montant_pret'], 0, ',', ' ') ?> F</span>
                                                <small class="text-muted d-block">+ intérêts: <?= number_format($pret['montant_total_du'] - $pret['montant_pret'], 0, ',', ' ') ?> F</small>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="pret-body">
                                        <div class="progress mb-3">
                                            <div class="progress-bar bg-success" style="width: <?= $progression ?>%;"><?= $progression ?>%</div>
                                        </div>
                                        
                                        <div class="row mb-3">
                                            <div class="col-4">
                                                <small class="text-muted">Remboursé</small><br>
                                                <strong><?= number_format($total_rembourse, 0, ',', ' ') ?> F</strong>
                                            </div>
                                            <div class="col-4">
                                                <small class="text-muted">Reste à payer</small><br>
                                                <strong class="text-warning"><?= number_format($pret['montant_total_du'] - $total_rembourse, 0, ',', ' ') ?> F</strong>
                                            </div>
                                            <div class="col-4">
                                                <small class="text-muted">Échéances</small><br>
                                                <strong><?= count($echeances) ?></strong>
                                            </div>
                                        </div>
                                        
                                        <h6 class="mb-2">Échéances</h6>
                                        <?php foreach($echeances as $e): ?>
                                            <div class="echeance-item p-2 mb-2 rounded <?= $e['statut'] == 'paye' ? 'echeance-paye' : ($e['statut'] == 'retard' ? 'echeance-retard' : '') ?>">
                                                <div class="d-flex justify-content-between align-items-center">
                                                    <div>
                                                        <strong>Échéance n°<?= $e['numero_echeance'] ?></strong>
                                                        <div><small><?= date('d/m/Y', strtotime($e['date_echeance'])) ?></small></div>
                                                    </div>
                                                    <div>
                                                        <strong><?= number_format($e['montant_du'], 0, ',', ' ') ?> F</strong>
                                                    </div>
                                                    <div>
                                                        <?php if($e['statut'] == 'paye'): ?>
                                                            <span class="badge-actif">✓ Payé</span>
                                                        <?php elseif($e['statut'] == 'retard'): ?>
                                                            <span class="badge-retard">Retard</span>
                                                        <?php else: ?>
                                                            <a href="?rembourser=<?= $e['id'] ?>" 
                                                               class="btn btn-sm btn-success"
                                                               onclick="return confirm('Confirmer le remboursement de <?= number_format($e['montant_du'], 0, ',', ' ') ?> F ?')">
                                                                Rembourser
                                                            </a>
                                                        <?php endif; ?>
                                                    </div>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- Prêts remboursés -->
                <div class="card mt-4">
                    <div class="card-header">
                        <i class="bi bi-check-circle"></i> Prêts remboursés (<?= count($prets_rembourses) ?>)
                    </div>
                    <div class="card-body p-0">
                        <?php if(empty($prets_rembourses)): ?>
                            <div class="p-4 text-center text-muted">
                                <i class="bi bi-inbox"></i> Aucun prêt remboursé
                            </div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-hover mb-0">
                                    <thead>
                                        <tr>
                                            <th>Membre</th>
                                            <th class="text-center">Montant emprunté</th>
                                            <th class="text-center">Intérêts payés</th>
                                            <th class="text-center">Total remboursé</th>
                                            <th class="text-center">Date de fin</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach($prets_rembourses as $pret): ?>
                                            <tr>
                                                <td>
                                                    <i class="bi bi-person-circle me-2" style="color: var(--primary);"></i>
                                                    <strong><?= htmlspecialchars($pret['prenom'] . ' ' . $pret['nom']) ?></strong>
                                                </td>
                                                <td class="text-center"><?= number_format($pret['montant_pret'], 0, ',', ' ') ?> F</td>
                                                <td class="text-center text-success"><?= number_format($pret['montant_total_du'] - $pret['montant_pret'], 0, ',', ' ') ?> F</td>
                                                <td class="text-center"><strong><?= number_format($pret['montant_total_du'], 0, ',', ' ') ?> F</strong></td>
                                                <td class="text-center"><?= date('d/m/Y', strtotime($pret['date_echeance'])) ?></td>
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
                        <i class="bi bi-arrow-left"></i> Retour
                    </a>
                </div>
            </div>
        </div>
    </div>
</body>
</html>