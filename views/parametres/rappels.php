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
require_once __DIR__ . '/../../models/Notification.php';
require_once __DIR__ . '/../../helpers/mail_helper.php';

$database = new Database();
$db = $database->getConnection();

$tontine_id = $_GET['tontine_id'] ?? 0;

// Vérifier la tontine
$tontine = new Tontine($db);
if(!$tontine->getById($tontine_id) || $tontine->admin_id != $_SESSION['user_id']) {
    header("Location: ../tontine/mes_tontines.php");
    exit();
}

// Récupérer l'association
$queryAssoc = "SELECT nom FROM associations WHERE id = :id";
$stmtAssoc = $db->prepare($queryAssoc);
$stmtAssoc->execute(['id' => $tontine->association_id]);
$association = $stmtAssoc->fetch(PDO::FETCH_ASSOC);
$association_nom = $association['nom'] ?? 'Votre association';

$message = '';
$error = '';

// VALEURS PAR DÉFAUT
$rappel_reunion = 1;
$jours_avant_reunion = 1;
$rappel_impaye = 1;

// Récupérer les préférences actuelles
$queryPrefs = "SELECT * FROM rappels WHERE tontine_id = :tontine_id";
$stmtPrefs = $db->prepare($queryPrefs);
$stmtPrefs->execute(['tontine_id' => $tontine_id]);
$prefs = $stmtPrefs->fetch(PDO::FETCH_ASSOC);

if(!$prefs) {
    // Créer des préférences par défaut
    $query = "INSERT INTO rappels (tontine_id, rappel_reunion, jours_avant_reunion, rappel_impaye) 
              VALUES (:tontine_id, 1, 1, 1)";
    $stmt = $db->prepare($query);
    $stmt->execute(['tontine_id' => $tontine_id]);
    
    // Recharger
    $stmtPrefs->execute(['tontine_id' => $tontine_id]);
    $prefs = $stmtPrefs->fetch(PDO::FETCH_ASSOC);
}

if($prefs) {
    $rappel_reunion = $prefs['rappel_reunion'] ?? 1;
    $jours_avant_reunion = $prefs['jours_avant_reunion'] ?? 1;
    $rappel_impaye = $prefs['rappel_impaye'] ?? 1;
}

// Sauvegarder les paramètres
if($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['save_prefs'])) {
    $rappel_reunion = isset($_POST['rappel_reunion']) ? 1 : 0;
    $jours_avant_reunion = $_POST['jours_avant_reunion'] ?? 1;
    $rappel_impaye = isset($_POST['rappel_impaye']) ? 1 : 0;
    
    $query = "UPDATE rappels SET 
              rappel_reunion = :rappel_reunion,
              jours_avant_reunion = :jours_avant_reunion,
              rappel_impaye = :rappel_impaye
              WHERE tontine_id = :tontine_id";
    
    $stmt = $db->prepare($query);
    if($stmt->execute([
        'rappel_reunion' => $rappel_reunion,
        'jours_avant_reunion' => $jours_avant_reunion,
        'rappel_impaye' => $rappel_impaye,
        'tontine_id' => $tontine_id
    ])) {
        $message = "Paramètres de notification enregistrés !";
        // Recharger
        $stmtPrefs->execute(['tontine_id' => $tontine_id]);
        $prefs = $stmtPrefs->fetch(PDO::FETCH_ASSOC);
        if($prefs) {
            $rappel_reunion = $prefs['rappel_reunion'] ?? 1;
            $jours_avant_reunion = $prefs['jours_avant_reunion'] ?? 1;
            $rappel_impaye = $prefs['rappel_impaye'] ?? 1;
        }
    } else {
        $error = "Erreur lors de l'enregistrement";
    }
}

// ENVOI MANUEL DES RAPPELS
if(isset($_POST['envoyer_rappel_reunion'])) {
    $date_reunion = $_POST['date_reunion'] ?? date('Y-m-d', strtotime('+2 days'));
    
    // Récupérer tous les membres
    $query = "SELECT mt.id, u.nom, u.prenom, u.email 
              FROM membre_tontine mt
              JOIN users u ON mt.user_id = u.id
              WHERE mt.tontine_id = :tid AND mt.est_actif = 1";
    $stmt = $db->prepare($query);
    $stmt->execute(['tid' => $tontine_id]);
    $membres = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Récupérer le prochain bénéficiaire pour Djangui/Anniversaire
    $prochain_beneficiaire = null;
    if($tontine->type_tontine == 'djangui' || $tontine->type_tontine == 'anniversaire') {
        $membreTontine = new MembreTontine($db);
        $prochain = $membreTontine->getProchainBeneficiaire($tontine_id);
        $prochain_beneficiaire = $prochain['prenom'] . ' ' . $prochain['nom'];
    }
    
    // Récupérer les échéances pour Prêt
    $echeances = [];
    if($tontine->type_tontine == 'pret') {
        $queryEch = "SELECT e.* FROM echeances_prets e
                     JOIN prets p ON e.pret_id = p.id
                     WHERE p.tontine_id = :tid AND e.statut = 'en_attente'
                     AND e.date_echeance BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 7 DAY)";
        $stmtEch = $db->prepare($queryEch);
        $stmtEch->execute(['tid' => $tontine_id]);
        $echeances = $stmtEch->fetchAll(PDO::FETCH_ASSOC);
    }
    
    $data = [
        'date_reunion' => $date_reunion,
        'prochain_beneficiaire' => $prochain_beneficiaire,
        'prochain_anniversaire' => $prochain_beneficiaire,
        'echeances' => $echeances
    ];
    
    $tontine_array = [
        'id' => $tontine->id,
        'nom' => $tontine->nom,
        'type_tontine' => $tontine->type_tontine,
        'montant_cotisation' => $tontine->montant_cotisation,
        'type_cotisation' => $tontine->type_cotisation,
        'solde_caisse' => $tontine->solde_caisse
    ];
    
    $count = 0;
    $notification = new Notification($db);
    
    foreach($membres as $m) {
        $result = envoyerRappelReunion(
            $m['prenom'] . ' ' . $m['nom'],
            $m['prenom'] . ' ' . $m['nom'],
            $m['email'],
            $tontine_array,
            $data
        );
        
        if($result) $count++;
        
        // Enregistrer dans l'historique
        $notification->enregistrer(
            $tontine_id,
            $m['email'],
            "Rappel de réunion - " . $tontine->nom,
            $result ? 'envoye' : 'echoue'
        );
    }
    
    $message = "$count email(s) envoyé(s) avec succès !";
}

if(isset($_POST['tester_rappel'])) {
    $membre_id = $_POST['membre_id'] ?? 0;
    $date_reunion = $_POST['date_reunion'] ?? date('Y-m-d', strtotime('+2 days'));
    
    $query = "SELECT u.nom, u.prenom, u.email 
              FROM membre_tontine mt
              JOIN users u ON mt.user_id = u.id
              WHERE mt.id = :mid";
    $stmt = $db->prepare($query);
    $stmt->execute(['mid' => $membre_id]);
    $membre = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if($membre) {
        $tontine_array = [
            'id' => $tontine->id,
            'nom' => $tontine->nom,
            'type_tontine' => $tontine->type_tontine,
            'montant_cotisation' => $tontine->montant_cotisation,
            'type_cotisation' => $tontine->type_cotisation,
            'solde_caisse' => $tontine->solde_caisse
        ];
        
        $data = [
            'date_reunion' => $date_reunion,
            'prochain_beneficiaire' => 'Test',
            'prochain_anniversaire' => 'Test',
            'echeances' => []
        ];
        
        $result = envoyerRappelReunion(
            $membre['prenom'] . ' ' . $membre['nom'],
            $membre['prenom'] . ' ' . $membre['nom'],
            $membre['email'],
            $tontine_array,
            $data
        );
        
        if($result) {
            $message = "Email test envoyé avec succès à " . $membre['email'];
            
            // Enregistrer dans l'historique
            $notification = new Notification($db);
            $notification->enregistrer(
                $tontine_id,
                $membre['email'],
                "Test de rappel - " . $tontine->nom,
                'envoye'
            );
        } else {
            $error = "Erreur lors de l'envoi de l'email test";
        }
    } else {
        $error = "Membre non trouvé";
    }
}

// Récupérer les membres
$queryMembres = "SELECT mt.id, u.nom, u.prenom, u.email 
                 FROM membre_tontine mt
                 JOIN users u ON mt.user_id = u.id
                 WHERE mt.tontine_id = :tid AND mt.est_actif = 1
                 ORDER BY u.nom, u.prenom";
$stmtMembres = $db->prepare($queryMembres);
$stmtMembres->execute(['tid' => $tontine_id]);
$membres = $stmtMembres->fetchAll(PDO::FETCH_ASSOC);

// Historique des notifications
$notification = new Notification($db);
$historique = $notification->getHistorique($tontine_id);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Rappels & Notifications - <?= htmlspecialchars($tontine->nom) ?></title>
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
        
        .card-header.bg-warning {
            background: var(--warning) !important;
            color: var(--white);
        }
        
        .card-header.bg-info {
            background: var(--info) !important;
            color: var(--white);
        }
        
        .btn-primary {
            background: var(--primary);
            border: none;
        }
        
        .btn-primary:hover {
            background: var(--primary-light);
        }
        
        .btn-action {
            background: var(--success);
            border: none;
            color: var(--white);
        }
        
        .btn-action:hover {
            background: #0E9F6E;
        }
        
        .badge-envoye {
            background: var(--success);
            color: var(--white);
            padding: 5px 10px;
            border-radius: 20px;
        }
        
        .badge-echoue {
            background: var(--danger);
            color: var(--white);
            padding: 5px 10px;
            border-radius: 20px;
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
        
        .type-badge {
            display: inline-block;
            padding: 5px 12px;
            border-radius: 50px;
            font-size: 12px;
            font-weight: 600;
            margin-left: 10px;
        }
        
        .badge-djangui { background: var(--info-bg); color: var(--primary); }
        .badge-anniversaire { background: #FEF3C7; color: #92400E; }
        .badge-solidarite { background: #D1FAE5; color: #065F46; }
        .badge-pret { background: #E0E7FF; color: #3730A3; }
        
        .form-check-input:checked {
            background-color: var(--primary);
            border-color: var(--primary);
        }
        
        .preview-box {
            background: var(--bg-light);
            border-radius: 10px;
            padding: 15px;
            max-height: 400px;
            overflow-y: auto;
        }
        
        .preview-box iframe {
            width: 100%;
            border: none;
            background: white;
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
                    <i class="bi bi-building me-1"></i> <?= htmlspecialchars($association_nom) ?>
                </span>
                <a class="nav-link" href="../tontine/mes_tontines.php">
                    <i class="bi bi-arrow-left me-1"></i> Retour
                </a>
            </div>
        </div>
    </nav>

    <div class="container mt-5">
        <div class="row">
            <div class="col-lg-10 mx-auto">
                
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <div>
                        <h2 class="mb-1"><i class="bi bi-bell"></i> Rappels & Notifications</h2>
                        <p class="text-muted">
                            <?= htmlspecialchars($tontine->nom) ?>
                            <span class="type-badge badge-<?= $tontine->type_tontine ?>"><?= ucfirst($tontine->type_tontine) ?></span>
                        </p>
                    </div>
                </div>

                <?php if($message): ?>
                    <div class="alert alert-success"><?= htmlspecialchars($message) ?></div>
                <?php endif; ?>
                
                <?php if($error): ?>
                    <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
                <?php endif; ?>

                <div class="row">
                    <!-- Configuration des rappels -->
                    <div class="col-md-6">
                        <div class="card">
                            <div class="card-header">
                                <i class="bi bi-gear"></i> Configuration automatique
                            </div>
                            <div class="card-body">
                                <form method="POST">
                                    <div class="mb-4">
                                        <h5>Notifications par email</h5>
                                        
                                        <div class="form-check form-switch mb-2">
                                            <input class="form-check-input" type="checkbox" 
                                                   id="rappel_reunion" name="rappel_reunion"
                                                   <?= $rappel_reunion ? 'checked' : '' ?>>
                                            <label class="form-check-label" for="rappel_reunion">
                                                <strong>Rappels de réunion</strong>
                                            </label>
                                        </div>
                                        
                                        <div class="ms-4 mt-2 mb-3">
                                            <label for="jours_avant_reunion" class="form-label">Envoyer :</label>
                                            <select class="form-select w-50" name="jours_avant_reunion" id="jours_avant_reunion">
                                                <option value="1" <?= $jours_avant_reunion == 1 ? 'selected' : '' ?>>1 jour avant</option>
                                                <option value="2" <?= $jours_avant_reunion == 2 ? 'selected' : '' ?>>2 jours avant</option>
                                                <option value="3" <?= $jours_avant_reunion == 3 ? 'selected' : '' ?>>3 jours avant</option>
                                                <option value="7" <?= $jours_avant_reunion == 7 ? 'selected' : '' ?>>1 semaine avant</option>
                                            </select>
                                        </div>
                                        
                                        <div class="form-check form-switch mb-2">
                                            <input class="form-check-input" type="checkbox" 
                                                   id="rappel_impaye" name="rappel_impaye"
                                                   <?= $rappel_impaye ? 'checked' : '' ?>>
                                            <label class="form-check-label" for="rappel_impaye">
                                                <strong>Rappels pour cotisations impayées</strong>
                                            </label>
                                        </div>
                                        <small class="text-muted d-block">Les rappels sont envoyés automatiquement 3 jours après la réunion.</small>
                                    </div>

                                    <button type="submit" name="save_prefs" class="btn btn-primary">
                                        <i class="bi bi-save"></i> Enregistrer les paramètres
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Envoi manuel -->
                    <div class="col-md-6">
                        <div class="card">
                            <div class="card-header">
                                <i class="bi bi-send"></i> Envoi manuel
                            </div>
                            <div class="card-body">
                                <form method="POST">
                                    <div class="mb-3">
                                        <label class="form-label">Date de la réunion</label>
                                        <input type="date" name="date_reunion" class="form-control" 
                                               value="<?= date('Y-m-d', strtotime('+2 days')) ?>" required>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label class="form-label">Destinataires</label>
                                        <div class="alert alert-info py-2">
                                            <i class="bi bi-info-circle"></i>
                                            Envoyer à <strong><?= count($membres) ?></strong> membre(s) actif(s)
                                        </div>
                                    </div>
                                    
                                    <button type="submit" name="envoyer_rappel_reunion" class="btn btn-action w-100 mb-2"
                                            onclick="return confirm('Envoyer les rappels à tous les membres ?')">
                                        <i class="bi bi-envelope-paper"></i> Envoyer à tous les membres
                                    </button>
                                </form>
                                
                                <hr>
                                
                                <form method="POST">
                                    <div class="mb-3">
                                        <label class="form-label">Test d'envoi</label>
                                        <select name="membre_id" class="form-select" required>
                                            <option value="">Sélectionner un membre</option>
                                            <?php foreach($membres as $m): ?>
                                                <option value="<?= $m['id'] ?>">
                                                    <?= htmlspecialchars($m['prenom'] . ' ' . $m['nom']) ?> (<?= htmlspecialchars($m['email']) ?>)
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <input type="date" name="date_reunion" class="form-control" 
                                               value="<?= date('Y-m-d', strtotime('+2 days')) ?>" required>
                                    </div>
                                    
                                    <button type="submit" name="tester_rappel" class="btn btn-outline-primary w-100">
                                        <i class="bi bi-bug"></i> Tester l'envoi
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Aperçu du message -->
                <div class="card mt-4">
                    <div class="card-header bg-info">
                        <i class="bi bi-eye"></i> Aperçu du message
                    </div>
                    <div class="card-body">
                        <div class="alert alert-info">
                            <i class="bi bi-info-circle"></i>
                            Les messages sont personnalisés selon le type de tontine et incluent le nom de l'association <strong><?= htmlspecialchars($association_nom) ?></strong>.
                        </div>
                        <div class="preview-box">
                            <?php
                            $tontine_array = [
                                'id' => $tontine->id,
                                'nom' => $tontine->nom,
                                'type_tontine' => $tontine->type_tontine,
                                'montant_cotisation' => $tontine->montant_cotisation,
                                'type_cotisation' => $tontine->type_cotisation,
                                'solde_caisse' => $tontine->solde_caisse
                            ];
                            $data_test = [
                                'date_reunion' => date('d/m/Y', strtotime('+2 days')),
                                'prochain_beneficiaire' => 'Jean Dupont',
                                'prochain_anniversaire' => 'Marie Martin',
                                'echeances' => []
                            ];
                            
                            // Afficher le bon template selon le type
                            switch($tontine->type_tontine) {
                                case 'djangui':
                                    echo getRappelDjangui('Membre Test', $tontine_array, $data_test);
                                    break;
                                case 'anniversaire':
                                    echo getRappelAnniversaire('Membre Test', $tontine_array, $data_test);
                                    break;
                                case 'solidarite':
                                    echo getRappelSolidarite('Membre Test', $tontine_array, $data_test);
                                    break;
                                case 'pret':
                                    echo getRappelPret('Membre Test', $tontine_array, $data_test);
                                    break;
                                default:
                                    echo getRappelGenerique('Membre Test', $tontine_array, $data_test);
                            }
                            ?>
                        </div>
                    </div>
                </div>

                <!-- Historique des notifications -->
                <div class="card mt-4">
                    <div class="card-header">
                        <i class="bi bi-clock-history"></i> Dernières notifications
                    </div>
                    <div class="card-body p-0">
                        <?php if(empty($historique)): ?>
                            <div class="p-4 text-center text-muted">
                                <i class="bi bi-inbox"></i> Aucune notification envoyée pour le moment.
                            </div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-hover mb-0">
                                    <thead>
                                        <tr>
                                            <th>Date</th>
                                            <th>Destinataire</th>
                                            <th>Sujet</th>
                                            <th>Statut</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach($historique as $n): ?>
                                            <tr>
                                                <td><?= date('d/m/Y H:i', strtotime($n['created_at'])) ?></td>
                                                <td><?= htmlspecialchars($n['destinataire']) ?></td>
                                                <td><?= htmlspecialchars($n['sujet']) ?></td>
                                                <td>
                                                    <?php if($n['statut'] == 'envoye'): ?>
                                                        <span class="badge-envoye">Envoyé</span>
                                                    <?php else: ?>
                                                        <span class="badge-echoue">Échoué</span>
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
                    <a href="../tontine/mes_tontines.php" class="btn btn-outline-secondary">
                        <i class="bi bi-arrow-left"></i> Retour
                    </a>
                </div>
                
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>