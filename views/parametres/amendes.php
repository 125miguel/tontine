<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();

if(!isset($_SESSION['user_id']) || $_SESSION['user_role'] != 'admin') {
    header("Location: ../auth/login.php");
    exit();
}

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../models/RegleAmende.php';
require_once __DIR__ . '/../../models/Tontine.php';

$database = new Database();
$db = $database->getConnection();

$tontine_id = $_GET['tontine_id'] ?? 0;

if(!$tontine_id) {
    header("Location: ../tontine/mes_tontines.php");
    exit();
}

// Vérifier que la tontine appartient à cet admin
$tontine = new Tontine($db);
if(!$tontine->getById($tontine_id) || $tontine->admin_id != $_SESSION['user_id']) {
    header("Location: ../tontine/mes_tontines.php");
    exit();
}

$regleAmende = new RegleAmende($db);
$message = '';

// Sauvegarde des règles
if($_SERVER['REQUEST_METHOD'] == 'POST') {
    
    // Liste des types d'amendes avec leurs libellés
    $types = [
        'retard_cotisation' => 'Retard de cotisation',
        'retard_reunion' => 'Retard à la réunion',
        'absence' => 'Absence non justifiée',
        'absence_justifiee' => 'Absence justifiée',
        'telephone' => 'Téléphone qui sonne',
        'dispute' => 'Manque de respect / Dispute',
        'nourriture' => 'Nourriture non partagée',
        'autre' => 'Autre amende'
    ];
    
    foreach($types as $type => $libelle) {
        $montant = $_POST['amende_' . $type] ?? 0;
        if($montant !== '') {
            $regleAmende->setRegle(
                $tontine_id,
                $type,
                floatval($montant),
                $libelle
            );
        }
    }
    
    $message = " Règles d'amendes enregistrées avec succès !";
}

// Récupérer les règles existantes
$regles = $regleAmende->getByTontine($tontine_id);
$regles_index = [];
foreach($regles as $r) {
    $regles_index[$r['type_amende']] = $r;
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Configuration des amendes</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.8.1/font/bootstrap-icons.css">
    <style>
        body { background: #f8f9fa; }
        .card { border-radius: 15px; border: none; box-shadow: 0 5px 20px rgba(0,0,0,0.05); }
        .card-header { border-radius: 15px 15px 0 0 !important; }
        .montant-input { max-width: 150px; }
    </style>
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
                <div class="card">
                    <div class="card-header bg-warning text-white">
                        <h4 class="mb-0"> Configuration des amendes</h4>
                        <p class="mb-0">Tontine : <strong><?= htmlspecialchars($tontine->nom) ?></strong></p>
                    </div>
                    <div class="card-body">
                        
                        <?php if($message): ?>
                            <div class="alert alert-success"><?= $message ?></div>
                        <?php endif; ?>

                        <form method="POST">
                            
                            <div class="row">
                                <!-- Retards -->
                                <div class="col-md-6 mb-4">
                                    <div class="card h-100">
                                        <div class="card-header bg-light">
                                            <h5 class="mb-0"> Retards</h5>
                                        </div>
                                        <div class="card-body">
                                            <div class="mb-3">
                                                <label class="form-label">Retard de cotisation (FCFA)</label>
                                                <input type="number" name="amende_retard_cotisation" class="form-control montant-input"
                                                       value="<?= $regles_index['retard_cotisation']['montant'] ?? 500 ?>">
                                            </div>
                                            <div class="mb-3">
                                                <label class="form-label">Retard à la réunion (FCFA)</label>
                                                <input type="number" name="amende_retard_reunion" class="form-control montant-input"
                                                       value="<?= $regles_index['retard_reunion']['montant'] ?? 1000 ?>">
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Absences -->
                                <div class="col-md-6 mb-4">
                                    <div class="card h-100">
                                        <div class="card-header bg-light">
                                            <h5 class="mb-0"> Absences</h5>
                                        </div>
                                        <div class="card-body">
                                            <div class="mb-3">
                                                <label class="form-label">Absence non justifiée (FCFA)</label>
                                                <input type="number" name="amende_absence" class="form-control montant-input"
                                                       value="<?= $regles_index['absence']['montant'] ?? 2000 ?>">
                                            </div>
                                            <div class="mb-3">
                                                <label class="form-label">Absence justifiée (FCFA)</label>
                                                <input type="number" name="amende_absence_justifiee" class="form-control montant-input"
                                                       value="<?= $regles_index['absence_justifiee']['montant'] ?? 0 ?>">
                                                <small class="text-muted">Mettre 0 si pas d'amende</small>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Autres amendes -->
                                <div class="col-md-6 mb-4">
                                    <div class="card h-100">
                                        <div class="card-header bg-light">
                                            <h5 class="mb-0"> Comportement</h5>
                                        </div>
                                        <div class="card-body">
                                            <div class="mb-3">
                                                <label class="form-label">Téléphone qui sonne (FCFA)</label>
                                                <input type="number" name="amende_telephone" class="form-control montant-input"
                                                       value="<?= $regles_index['telephone']['montant'] ?? 500 ?>">
                                            </div>
                                            <div class="mb-3">
                                                <label class="form-label">Dispute / Manque de respect (FCFA)</label>
                                                <input type="number" name="amende_dispute" class="form-control montant-input"
                                                       value="<?= $regles_index['dispute']['montant'] ?? 1000 ?>">
                                            </div>
                                            <div class="mb-3">
                                                <label class="form-label">Nourriture non partagée (FCFA)</label>
                                                <input type="number" name="amende_nourriture" class="form-control montant-input"
                                                       value="<?= $regles_index['nourriture']['montant'] ?? 250 ?>">
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Autre -->
                                <div class="col-md-6 mb-4">
                                    <div class="card h-100">
                                        <div class="card-header bg-light">
                                            <h5 class="mb-0"> Autre</h5>
                                        </div>
                                        <div class="card-body">
                                            <div class="mb-3">
                                                <label class="form-label">Autre amende (FCFA)</label>
                                                <input type="number" name="amende_autre" class="form-control montant-input"
                                                       value="<?= $regles_index['autre']['montant'] ?? 0 ?>">
                                                <small class="text-muted">Amende personnalisée</small>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="text-center mt-4">
                                <button type="submit" class="btn btn-success btn-lg">
                                    <i class="bi bi-save"></i> Enregistrer les règles d'amendes
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>