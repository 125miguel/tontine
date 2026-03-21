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

$database = new Database();
$db = $database->getConnection();

$tontine_id = $_GET['tontine_id'] ?? 0;

// Vérifier que la tontine appartient à cet admin et est de type solidarite
$tontine = new Tontine($db);
if(!$tontine->getById($tontine_id) || $tontine->admin_id != $_SESSION['user_id']) {
    header("Location: mes_tontines.php");
    exit();
}

if($tontine->type_tontine != 'solidarite') {
    header("Location: voir_membres.php?id=" . $tontine_id . "&error=not_solidarite");
    exit();
}

$membreTontine = new MembreTontine($db);

// Récupérer les membres
$query = "SELECT mt.id, u.nom, u.prenom 
          FROM membre_tontine mt
          JOIN users u ON mt.user_id = u.id
          WHERE mt.tontine_id = :tid AND mt.est_actif = 1
          ORDER BY u.nom, u.prenom";
$stmt = $db->prepare($query);
$stmt->execute(['tid' => $tontine_id]);
$membres = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Récupérer les demandes en attente
$query = "SELECT d.*, u.nom, u.prenom 
          FROM demandes_aide d
          JOIN membre_tontine mt ON d.membre_id = mt.id
          JOIN users u ON mt.user_id = u.id
          WHERE d.tontine_id = :tid AND d.statut = 'en_attente'
          ORDER BY d.date_demande DESC";
$stmt = $db->prepare($query);
$stmt->execute(['tid' => $tontine_id]);
$demandes_attente = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Récupérer les demandes traitées
$query = "SELECT d.*, u.nom, u.prenom 
          FROM demandes_aide d
          JOIN membre_tontine mt ON d.membre_id = mt.id
          JOIN users u ON mt.user_id = u.id
          WHERE d.tontine_id = :tid AND d.statut != 'en_attente'
          ORDER BY d.date_traitement DESC LIMIT 10";
$stmt = $db->prepare($query);
$stmt->execute(['tid' => $tontine_id]);
$demandes_traitees = $stmt->fetchAll(PDO::FETCH_ASSOC);

$error = '';
$success = '';

// Traitement du formulaire de demande
if($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['faire_demande'])) {
    $membre_id = $_POST['membre_id'] ?? 0;
    $type_demande = $_POST['type_demande'] ?? '';
    $montant = $_POST['montant'] ?? 0;
    $description = $_POST['description'] ?? '';
    
    if(empty($membre_id) || empty($type_demande) || $montant <= 0) {
        $error = "Veuillez remplir tous les champs obligatoires";
    } else {
        // Vérifier si le membre a déjà une demande en cours
        if($membreTontine->aDemandeEnCours($membre_id)) {
            $error = "Ce membre a déjà une demande d'aide en attente";
        } else {
            $query = "INSERT INTO demandes_aide 
                      (tontine_id, membre_id, type_demande, description, montant_demande)
                      VALUES (:tid, :mid, :type, :desc, :montant)";
            $stmt = $db->prepare($query);
            
            if($stmt->execute([
                'tid' => $tontine_id,
                'mid' => $membre_id,
                'type' => $type_demande,
                'desc' => $description,
                'montant' => $montant
            ])) {
                $success = "Demande d'aide enregistrée avec succès !";
            } else {
                $error = "Erreur lors de l'enregistrement";
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Demande d'aide - <?= htmlspecialchars($tontine->nom) ?></title>
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
            --warning-bg: #FEF3C7;
            --danger: #EF4444;
            --danger-bg: #FEE2E2;
            --info: #3B82F6;
            --info-bg: #DBEAFE;
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
        
        .card-header.bg-success {
            background: var(--success) !important;
        }
        
        .card-header.bg-warning {
            background: var(--warning) !important;
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
        
        .btn-warning {
            background: var(--warning);
            border: none;
            color: var(--text-dark);
        }
        
        .btn-danger {
            background: var(--danger);
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
        
        .badge-warning {
            background: var(--warning-bg);
            color: #92400E;
            padding: 5px 10px;
            border-radius: 20px;
        }
        
        .badge-success {
            background: #D1FAE5;
            color: #065F46;
            padding: 5px 10px;
            border-radius: 20px;
        }
        
        .badge-danger {
            background: var(--danger-bg);
            color: #991B1B;
            padding: 5px 10px;
            border-radius: 20px;
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
        
        .demande-item {
            border-left: 4px solid var(--warning);
            transition: all 0.2s;
        }
        
        .demande-item:hover {
            transform: translateX(5px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.05);
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
                    <span class="badge-warning">
                        <i class="bi bi-shield-check"></i> Solidarité
                    </span>
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
                
                <h2 class="mb-4"><i class="bi bi-shield-check"></i> Gestion des demandes d'aide</h2>
                <p class="text-muted mb-4"><?= htmlspecialchars($tontine->nom) ?> - Fonds de solidarité</p>
                
                <!-- Solde de la caisse -->
                <div class="solde-box">
                    <i class="bi bi-piggy-bank" style="font-size: 32px;"></i>
                    <h3 class="mt-2">Solde de la caisse</h3>
                    <div class="solde-value"><?= number_format($tontine->solde_caisse, 0, ',', ' ') ?> FCFA</div>
                    <small>Les cotisations et amendes alimentent cette caisse</small>
                </div>
                
                <?php if($error): ?>
                    <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
                <?php endif; ?>
                
                <?php if($success): ?>
                    <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
                <?php endif; ?>
                
                <div class="row">
                    <!-- Formulaire de demande -->
                    <div class="col-md-6">
                        <div class="card">
                            <div class="card-header">
                                <i class="bi bi-plus-circle"></i> Nouvelle demande d'aide
                            </div>
                            <div class="card-body">
                                <form method="POST">
                                    <div class="mb-3">
                                        <label class="form-label">Membre concerné</label>
                                        <select name="membre_id" class="form-select" required>
                                            <option value="">Sélectionner un membre</option>
                                            <?php foreach($membres as $m): ?>
                                                <option value="<?= $m['id'] ?>"><?= htmlspecialchars($m['prenom'] . ' ' . $m['nom']) ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label class="form-label">Type d'aide</label>
                                        <select name="type_demande" class="form-select" required>
                                            <option value="">Sélectionner</option>
                                            <option value="deces">Décès</option>
                                            <option value="maladie">Maladie grave</option>
                                            <option value="mariage">Mariage</option>
                                            <option value="naissance">Naissance</option>
                                            <option value="accident">Accident</option>
                                            <option value="autre">Autre situation</option>
                                        </select>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label class="form-label">Montant demandé (FCFA)</label>
                                        <input type="number" name="montant" class="form-control" 
                                               min="1000" step="1000" required>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label class="form-label">Description / Justification</label>
                                        <textarea name="description" class="form-control" rows="3" 
                                                  placeholder="Décrivez la situation..."></textarea>
                                    </div>
                                    
                                    <button type="submit" name="faire_demande" class="btn btn-primary w-100">
                                        <i class="bi bi-send"></i> Envoyer la demande
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Demandes en attente -->
                    <div class="col-md-6">
                        <div class="card">
                            <div class="card-header bg-warning">
                                <i class="bi bi-clock-history"></i> Demandes en attente
                                <span class="badge bg-dark float-end"><?= count($demandes_attente) ?></span>
                            </div>
                            <div class="card-body p-0">
                                <?php if(empty($demandes_attente)): ?>
                                    <div class="p-4 text-center text-muted">
                                        <i class="bi bi-inbox"></i> Aucune demande en attente
                                    </div>
                                <?php else: ?>
                                    <?php foreach($demandes_attente as $d): ?>
                                        <div class="p-3 border-bottom demande-item">
                                            <div class="d-flex justify-content-between">
                                                <div>
                                                    <strong><?= htmlspecialchars($d['prenom'] . ' ' . $d['nom']) ?></strong>
                                                    <span class="badge-warning ms-2"><?= ucfirst($d['type_demande']) ?></span>
                                                </div>
                                                <div>
                                                    <strong class="text-warning"><?= number_format($d['montant_demande'], 0, ',', ' ') ?> F</strong>
                                                </div>
                                            </div>
                                            <small class="text-muted"><?= date('d/m/Y H:i', strtotime($d['date_demande'])) ?></small>
                                            <p class="mb-2 small"><?= htmlspecialchars($d['description']) ?></p>
                                            <div class="btn-group btn-group-sm">
                                                <a href="traiter_demande.php?id=<?= $d['id'] ?>&action=approuver&tontine_id=<?= $tontine_id ?>" 
                                                   class="btn btn-success"
                                                   onclick="return confirm('Approuver cette demande ?')">
                                                    <i class="bi bi-check-lg"></i> Approuver
                                                </a>
                                                <a href="traiter_demande.php?id=<?= $d['id'] ?>&action=refuser&tontine_id=<?= $tontine_id ?>" 
                                                   class="btn btn-danger"
                                                   onclick="return confirm('Refuser cette demande ?')">
                                                    <i class="bi bi-x-lg"></i> Refuser
                                                </a>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Historique des demandes traitées -->
                <div class="card mt-4">
                    <div class="card-header">
                        <i class="bi bi-clock-history"></i> Historique des demandes traitées
                    </div>
                    <div class="card-body p-0">
                        <?php if(empty($demandes_traitees)): ?>
                            <div class="p-4 text-center text-muted">
                                <i class="bi bi-inbox"></i> Aucune demande traitée
                            </div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-hover mb-0">
                                    <thead>
                                        <tr>
                                            <th>Date</th>
                                            <th>Membre</th>
                                            <th>Type</th>
                                            <th>Montant</th>
                                            <th>Statut</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach($demandes_traitees as $d): ?>
                                            <tr>
                                                <td><?= date('d/m/Y', strtotime($d['date_traitement'])) ?></td>
                                                <td><?= htmlspecialchars($d['prenom'] . ' ' . $d['nom']) ?></td>
                                                <td><?= ucfirst($d['type_demande']) ?></td>
                                                <td><?= number_format($d['montant_accorde'] ?? $d['montant_demande'], 0, ',', ' ') ?> F</td>
                                                <td>
                                                    <?php if($d['statut'] == 'approuve'): ?>
                                                        <span class="badge-success">Approuvé</span>
                                                    <?php elseif($d['statut'] == 'refuse'): ?>
                                                        <span class="badge-danger">Refusé</span>
                                                    <?php else: ?>
                                                        <span class="badge-warning"><?= $d['statut'] ?></span>
                                                    <?php endif; ?>
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
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>