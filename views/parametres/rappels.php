<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();

if(!isset($_SESSION['user_id']) || $_SESSION['user_role'] != 'admin') {
    header("Location: ../auth/login.php");
    exit();
}

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../models/Tontine.php';
require_once __DIR__ . '/../../models/Notification.php';

$database = new Database();
$db = $database->getConnection();

$tontine_id = $_GET['tontine_id'] ?? 0;

// Vérifier la tontine
$tontine = new Tontine($db);
if(!$tontine->getById($tontine_id) || $tontine->admin_id != $_SESSION['user_id']) {
    header("Location: ../tontine/mes_tontines.php");
    exit();
}

$message = '';
$error = '';

// VALEURS PAR DÉFAUT (au cas où)
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

// Si $prefs existe, on met à jour les valeurs
if($prefs) {
    $rappel_reunion = $prefs['rappel_reunion'] ?? 1;
    $jours_avant_reunion = $prefs['jours_avant_reunion'] ?? 1;
    $rappel_impaye = $prefs['rappel_impaye'] ?? 1;
}

// Sauvegarder les paramètres
if($_SERVER['REQUEST_METHOD'] == 'POST') {
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

// Historique des notifications
$notification = new Notification($db);
$historique = $notification->getHistorique($tontine_id);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Rappels & Notifications</title>
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
                <a class="nav-link" href="../tontine/mes_tontines.php">
                    <i class="bi bi-arrow-left"></i> Retour
                </a>
            </div>
        </div>
    </nav>

    <div class="container mt-5">
        <div class="row justify-content-center">
            <div class="col-md-8">
                
                <?php if($message): ?>
                    <div class="alert alert-success"><?= $message ?></div>
                <?php endif; ?>
                
                <?php if($error): ?>
                    <div class="alert alert-danger"><?= $error ?></div>
                <?php endif; ?>

                <div class="card mb-4">
                    <div class="card-header bg-info text-white">
                        <h4 class="mb-0"><i class="bi bi-bell"></i> Rappels & Notifications</h4>
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
                                <small class="text-muted d-block mb-3">Les rappels sont envoyés automatiquement 3 jours après la réunion.</small>
                            </div>

                            <button type="submit" class="btn btn-primary">
                                <i class="bi bi-save"></i> Enregistrer
                            </button>
                        </form>
                    </div>
                </div>

                <!-- Actions manuelles -->
                <div class="card mb-4">
                    <div class="card-header bg-warning">
                        <h5 class="mb-0"><i class="bi bi-send"></i> Envoi manuel</h5>
                    </div>
                    <div class="card-body">
                        <div class="d-grid gap-2">
                            <a href="envoyer_rappel_reunion.php?tontine_id=<?= $tontine_id ?>" 
                               class="btn btn-outline-primary"
                               onclick="return confirm('Envoyer un rappel de réunion à tous les membres ?')">
                                <i class="bi bi-calendar"></i> Envoyer un rappel de réunion maintenant
                            </a>
                            <a href="envoyer_rappel_impayes.php?tontine_id=<?= $tontine_id ?>" 
                               class="btn btn-outline-warning"
                               onclick="return confirm('Envoyer des rappels pour toutes les cotisations impayées ?')">
                                <i class="bi bi-exclamation-triangle"></i> Relancer les impayés
                            </a>
                        </div>
                    </div>
                </div>

                <!-- Historique -->
                <div class="card">
                    <div class="card-header bg-secondary text-white">
                        <h5 class="mb-0"><i class="bi bi-clock-history"></i> Dernières notifications</h5>
                    </div>
                    <div class="card-body">
                        <?php if(empty($historique)): ?>
                            <p class="text-muted">Aucune notification envoyée pour le moment.</p>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-sm">
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
                                                        <span class="badge bg-success">Envoyé</span>
                                                    <?php elseif($n['statut'] == 'echec'): ?>
                                                        <span class="badge bg-danger">Échec</span>
                                                    <?php else: ?>
                                                        <span class="badge bg-warning">En attente</span>
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
            </div>
        </div>
    </div>
</body>
</html>