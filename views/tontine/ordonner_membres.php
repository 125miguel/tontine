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

// Vérifier que la tontine appartient bien à cet admin
$tontine = new Tontine($db);
if(!$tontine->getById($tontine_id) || $tontine->admin_id != $_SESSION['user_id']) {
    header("Location: mes_tontines.php");
    exit();
}

// Vérifier si l'ordre a déjà été finalisé
$query = "SELECT ordre_finalise FROM tontines WHERE id = :id";
$stmt = $db->prepare($query);
$stmt->execute(['id' => $tontine_id]);
$ordre_finalise = $stmt->fetch()['ordre_finalise'] ?? 0;

// Si l'ordre est déjà finalisé, rediriger avec un message
if($ordre_finalise == 1) {
    $_SESSION['error_message'] = "L'ordre des bénéficiaires a déjà été défini et ne peut plus être modifié.";
    header("Location: voir_membres.php?id=" . $tontine_id);
    exit();
}

// Récupérer l'association du président
$query = "SELECT id, nom FROM associations WHERE admin_id = :admin_id";
$stmt = $db->prepare($query);
$stmt->execute(['admin_id' => $_SESSION['user_id']]);
$association = $stmt->fetch(PDO::FETCH_ASSOC);

$membreTontine = new MembreTontine($db);

// Traitement de la sauvegarde
if(isset($_POST['save_ordre'])) {
    $ordre_data = $_POST['ordre'] ?? [];
    $success = true;
    
    foreach($ordre_data as $item) {
        $parts = explode('_', $item);
        if(count($parts) == 2) {
            $id = $parts[0];
            $ordre = $parts[1];
            
            $query = "UPDATE membre_tontine SET ordre_tour = :ordre WHERE id = :id";
            $stmt = $db->prepare($query);
            $stmt->bindParam(":ordre", $ordre);
            $stmt->bindParam(":id", $id);
            
            if(!$stmt->execute()) {
                $success = false;
            }
        }
    }
    
    if($success) {
        // Marquer la tontine comme ayant l'ordre finalisé
        $query = "UPDATE tontines SET ordre_finalise = 1 WHERE id = :id";
        $stmt = $db->prepare($query);
        $stmt->execute(['id' => $tontine_id]);
        
        $_SESSION['success_message'] = "Ordre sauvegardé avec succès !";
        header("Location: voir_membres.php?id=" . $tontine_id . "&ordre_finalise=1");
        exit();
    } else {
        $error = "Erreur lors de la sauvegarde";
    }
}

// Récupérer les membres de la tontine
$membres = $membreTontine->getMembresByTontine($tontine_id);
$membres_list = $membres->fetchAll(PDO::FETCH_ASSOC);
$total_membres = count($membres_list);

// Vérifier s'il y a des membres
if($total_membres == 0) {
    header("Location: ajouter_membre.php?id=" . $tontine_id . "&mode=manuel&error=no_members");
    exit();
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ordonner les bénéficiaires - <?= htmlspecialchars($tontine->nom) ?></title>
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
            box-shadow: 0 2px 20px rgba(0,0,0,0.1);
        }
        
        .navbar-brand, .nav-link {
            color: var(--white) !important;
        }
        
        .navbar-brand {
            font-size: 24px;
            font-weight: 700;
        }
        
        .warning-banner {
            background: var(--warning);
            color: var(--text-dark);
            padding: 15px;
            border-radius: 10px;
            margin-bottom: 20px;
            font-weight: 600;
            border-left: 5px solid #d97706;
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
            border-bottom: none;
            padding: 15px 20px;
        }
        
        .card-header i {
            margin-right: 8px;
        }
        
        .list-group-item {
            border: 1px solid var(--border);
            border-radius: 10px !important;
            margin-bottom: 8px;
            padding: 15px;
            background: var(--white);
            transition: all 0.3s;
            cursor: move;
        }
        
        .list-group-item:hover {
            transform: translateX(5px);
            box-shadow: 0 5px 15px rgba(30, 58, 138, 0.1);
            border-color: var(--primary);
        }
        
        .badge-ordre {
            background: var(--primary);
            color: var(--white);
            width: 40px;
            height: 40px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 50%;
            font-weight: bold;
            font-size: 18px;
        }
        
        .btn-success {
            background: var(--success);
            border: none;
            padding: 12px 30px;
            border-radius: 10px;
            font-weight: 600;
            transition: all 0.3s;
        }
        
        .btn-success:hover {
            background: #0E9F6E;
            transform: translateY(-2px);
            box-shadow: 0 10px 30px rgba(16, 185, 129, 0.3);
        }
        
        .btn-outline-secondary {
            border: 2px solid var(--text-light);
            color: var(--text-light);
            border-radius: 10px;
            padding: 10px 25px;
            font-weight: 600;
        }
        
        .grip-handle {
            color: var(--text-light);
            font-size: 20px;
            margin-right: 15px;
            cursor: grab;
        }
        
        .grip-handle:active {
            cursor: grabbing;
        }
        
        .info-box {
            background: #DBEAFE;
            border-left: 4px solid var(--primary);
            padding: 15px;
            border-radius: 10px;
        }
        
        .member-photo {
            width: 45px;
            height: 45px;
            background: var(--primary);
            color: var(--white);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 20px;
            font-weight: bold;
            margin-right: 15px;
        }
        
        .confirmation-warning {
            background: #FEF3C7;
            border-left: 5px solid #F59E0B;
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 20px;
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
                    <i class="bi bi-building me-1"></i> <?= htmlspecialchars($association['nom']) ?>
                </span>
                <span class="nav-link">
                    <span class="badge bg-warning text-dark">
                        <i class="bi bi-pencil-square"></i> Ordonnancement manuel
                    </span>
                </span>
                <a class="nav-link" href="mes_tontines.php">
                    <i class="bi bi-arrow-left"></i> Retour
                </a>
            </div>
        </div>
    </nav>

    <div class="container mt-5">
        <div class="row">
            <div class="col-lg-8 mx-auto">
                
                <!-- En-tête -->
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <div>
                        <h2 class="mb-1"><i class="bi bi-sort-numeric-down"></i> Ordre des bénéficiaires</h2>
                        <p class="text-muted">
                            Tontine : <strong><?= htmlspecialchars($tontine->nom) ?></strong> (Mode manuel)
                        </p>
                    </div>
                    <div class="text-end">
                        <span class="badge bg-primary p-3">
                            <i class="bi bi-people"></i> <?= $total_membres ?> membre<?= $total_membres > 1 ? 's' : '' ?>
                        </span>
                    </div>
                </div>

                <!-- Message d'avertissement important -->
                <div class="confirmation-warning">
                    <div class="d-flex">
                        <div class="me-3">
                            <i class="bi bi-exclamation-triangle-fill" style="color: #F59E0B; font-size: 32px;"></i>
                        </div>
                        <div>
                            <h4 class="mb-2"> Attention ! Cette action est irréversible</h4>
                            <p class="mb-1">Vous êtes sur le point de définir l'ordre définitif des bénéficiaires.</p>
                            <p class="mb-0"><strong>Une fois sauvegardé, cet ordre ne pourra plus être modifié.</strong></p>
                        </div>
                    </div>
                </div>

                <!-- Message d'information -->
                <div class="info-box mb-4">
                    <div class="d-flex">
                        <div class="me-3">
                            <i class="bi bi-info-circle-fill" style="color: var(--primary); font-size: 24px;"></i>
                        </div>
                        <div>
                            <h5 class="mb-2">Comment ça marche ?</h5>
                            <p class="mb-1">1. Faites glisser les membres pour définir l'ordre de passage</p>
                            <p class="mb-1">2. Le premier de la liste sera le prochain bénéficiaire</p>
                            <p class="mb-1">3. Après chaque séance, le bénéficiaire passera automatiquement en fin de liste</p>
                            <p class="mb-0"><strong class="text-danger">4. Cette opération n'est possible qu'une seule fois !</strong></p>
                        </div>
                    </div>
                </div>

                <!-- Formulaire de sauvegarde -->
                <form method="POST" id="ordreForm" onsubmit="return confirmSauvegarde()">
                    <div class="card">
                        <div class="card-header">
                            <div class="d-flex justify-content-between align-items-center">
                                <span><i class="bi bi-list-task"></i> Ordre de passage</span>
                                <button type="button" class="btn btn-sm btn-light" onclick="resetOrdre()">
                                    <i class="bi bi-arrow-counterclockwise"></i> Réinitialiser
                                </button>
                            </div>
                        </div>
                        <div class="card-body">
                            <div id="membres-liste" class="list-group">
                                <?php foreach($membres_list as $index => $membre): ?>
                                <div class="list-group-item d-flex align-items-center" data-id="<?= $membre['id'] ?>">
                                    <span class="grip-handle"><i class="bi bi-grip-vertical"></i></span>
                                    <span class="badge-ordre me-3"><?= $index + 1 ?></span>
                                    <div class="member-photo">
                                        <?= strtoupper(substr($membre['prenom'], 0, 1) . substr($membre['nom'], 0, 1)) ?>
                                    </div>
                                    <div class="flex-grow-1">
                                        <strong><?= htmlspecialchars($membre['prenom'] . ' ' . $membre['nom']) ?></strong>
                                        <br>
                                        <small class="text-muted">
                                            <i class="bi bi-telephone"></i> <?= htmlspecialchars($membre['telephone'] ?? 'Non renseigné') ?>
                                        </small>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            </div>

                            <!-- Champs cachés pour l'ordre -->
                            <div id="ordre-inputs"></div>

                            <?php if(isset($error)): ?>
                                <div class="alert alert-danger mt-3"><?= htmlspecialchars($error) ?></div>
                            <?php endif; ?>

                            <div class="text-center mt-4">
                                <button type="submit" class="btn btn-success btn-lg" id="saveBtn">
                                    <i class="bi bi-check-circle"></i> Finaliser et sauvegarder l'ordre
                                </button>
                                <a href="mes_tontines.php" class="btn btn-outline-secondary btn-lg ms-2">
                                    <i class="bi bi-skip-forward"></i> Annuler
                                </a>
                            </div>
                        </div>
                    </div>
                </form>

                <!-- Aperçu de l'ordre -->
                <div class="card mt-4">
                    <div class="card-header">
                        <i class="bi bi-eye"></i> Aperçu de l'ordre
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-4 text-center">
                                <div class="p-3" style="background: #DBEAFE; border-radius: 10px;">
                                    <i class="bi bi-1-circle-fill" style="color: var(--primary); font-size: 32px;"></i>
                                    <h6 class="mt-2">Prochain bénéficiaire</h6>
                                    <p class="mb-0 fw-bold" id="preview-first">-</p>
                                </div>
                            </div>
                            <div class="col-md-4 text-center">
                                <div class="p-3" style="background: #FEF3C7; border-radius: 10px;">
                                    <i class="bi bi-2-circle-fill" style="color: #F59E0B; font-size: 32px;"></i>
                                    <h6 class="mt-2">Deuxième</h6>
                                    <p class="mb-0 fw-bold" id="preview-second">-</p>
                                </div>
                            </div>
                            <div class="col-md-4 text-center">
                                <div class="p-3" style="background: #D1FAE5; border-radius: 10px;">
                                    <i class="bi bi-3-circle-fill" style="color: #10B981; font-size: 32px;"></i>
                                    <h6 class="mt-2">Troisième</h6>
                                    <p class="mb-0 fw-bold" id="preview-third">-</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.0/Sortable.min.js"></script>
    <script>
        // Initialiser SortableJS pour le drag & drop
        const el = document.getElementById('membres-liste');
        const sortable = new Sortable(el, {
            animation: 150,
            ghostClass: 'bg-light',
            handle: '.grip-handle',
            onEnd: function() {
                updateOrdreAffichage();
                updatePreview();
            }
        });

        // Mettre à jour les numéros d'ordre
        function updateOrdreAffichage() {
            const items = document.querySelectorAll('#membres-liste .list-group-item');
            items.forEach((item, index) => {
                const badge = item.querySelector('.badge-ordre');
                badge.textContent = index + 1;
            });
        }

        // Mettre à jour l'aperçu
        function updatePreview() {
            const items = document.querySelectorAll('#membres-liste .list-group-item');
            const previewFirst = document.getElementById('preview-first');
            const previewSecond = document.getElementById('preview-second');
            const previewThird = document.getElementById('preview-third');
            
            if(items.length > 0) {
                previewFirst.textContent = items[0].querySelector('strong').textContent;
            }
            if(items.length > 1) {
                previewSecond.textContent = items[1].querySelector('strong').textContent;
            }
            if(items.length > 2) {
                previewThird.textContent = items[2].querySelector('strong').textContent;
            }
        }

        // Confirmation avant sauvegarde
        function confirmSauvegarde() {
            const items = document.querySelectorAll('#membres-liste .list-group-item');
            const ordreInputs = document.getElementById('ordre-inputs');
            
            // Vider les anciens inputs
            ordreInputs.innerHTML = '';
            
            // Créer un input caché pour chaque membre
            items.forEach((item, index) => {
                const input = document.createElement('input');
                input.type = 'hidden';
                input.name = 'ordre[]';
                input.value = item.dataset.id + '_' + (index + 1);
                ordreInputs.appendChild(input);
            });
            
            // Ajouter le champ save_ordre
            const saveInput = document.createElement('input');
            saveInput.type = 'hidden';
            saveInput.name = 'save_ordre';
            saveInput.value = '1';
            ordreInputs.appendChild(saveInput);
            
            // Demander confirmation
            return confirm(' ATTENTION : Une fois sauvegardé, cet ordre ne pourra plus être modifié.\n\nÊtes-vous sûr de vouloir continuer ?');
        }

        // Réinitialiser l'ordre
        function resetOrdre() {
            if(confirm('Voulez-vous vraiment réinitialiser l\'ordre ?')) {
                location.reload();
            }
        }

        // Initialiser l'aperçu
        document.addEventListener('DOMContentLoaded', updatePreview);
    </script>
</body>
</html>