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

$tontine_id = $_GET['id'] ?? 0;

// Vérifier que la tontine appartient bien à cet admin
$tontine = new Tontine($db);
if(!$tontine->getById($tontine_id) || $tontine->admin_id != $_SESSION['user_id']) {
    header("Location: mes_tontines.php");
    exit();
}

// Vérifier que le cycle est bien terminé
if(!$tontine->cycle_termine && !$tontine->estCycleTermine()) {
    // Si le cycle n'est pas marqué comme terminé mais qu'il est effectivement terminé
    if($tontine->estCycleTermine()) {
        $tontine->terminerCycle();
    } else {
        header("Location: voir_membres.php?id=" . $tontine_id . "&error=cycle_non_termine");
        exit();
    }
}

$membreTontine = new MembreTontine($db);

// Récupérer les membres actuels
$query = "SELECT mt.*, u.nom, u.prenom, u.email, u.telephone
          FROM membre_tontine mt
          JOIN users u ON mt.user_id = u.id
          WHERE mt.tontine_id = :tid AND mt.est_actif = 1
          ORDER BY mt.ordre_tour ASC";
$stmt = $db->prepare($query);
$stmt->execute(['tid' => $tontine_id]);
$membres_actuels = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Statistiques du cycle terminé
$nb_membres = count($membres_actuels);

// Nombre de séances dans le cycle
$query = "SELECT COUNT(*) as total FROM seances 
          WHERE tontine_id = :tid 
          AND date_seance BETWEEN :debut AND :fin";
$stmt = $db->prepare($query);
$stmt->execute([
    'tid' => $tontine_id,
    'debut' => $tontine->date_debut_cycle,
    'fin' => $tontine->date_fin_cycle
]);
$nb_seances = $stmt->fetch()['total'] ?? 0;

// Total collecté pendant le cycle
$query = "SELECT SUM(c.montant) as total FROM cotisations c
          JOIN seances s ON c.seance_id = s.id
          WHERE s.tontine_id = :tid 
          AND s.date_seance BETWEEN :debut AND :fin
          AND c.statut = 'paye'";
$stmt = $db->prepare($query);
$stmt->execute([
    'tid' => $tontine_id,
    'debut' => $tontine->date_debut_cycle,
    'fin' => $tontine->date_fin_cycle
]);
$total_collecte = $stmt->fetch()['total'] ?? 0;

// Traitement du renouvellement
if($_SERVER['REQUEST_METHOD'] == 'POST') {
    
    $action = $_POST['action'] ?? '';
    
    if($action == 'renouveler') {
        
        // Récupérer les membres à conserver
        $membres_conserver = $_POST['membres'] ?? [];
        
        // Type de cycle pour le nouveau cycle
        $nouveau_type_cycle = $_POST['type_cycle'] ?? $tontine->type_cycle;
        $duree_perso = $_POST['duree_cycle_perso'] ?? 0;
        
        // 1. Désactiver les membres non conservés
        foreach($membres_actuels as $m) {
            if(!in_array($m['id'], $membres_conserver)) {
                $query = "UPDATE membre_tontine SET est_actif = 0 
                          WHERE id = :id AND tontine_id = :tid";
                $stmt = $db->prepare($query);
                $stmt->execute(['id' => $m['id'], 'tid' => $tontine_id]);
            }
        }
        
        // 2. Démarrer le nouveau cycle
        $tontine->demarrerNouveauCycle();
        
        // 3. Réinitialiser l'ordre des membres pour le nouveau cycle
        if($nouveau_type_cycle != $tontine->type_cycle) {
            $tontine->type_cycle = $nouveau_type_cycle;
            
            // Recalculer la durée
            switch($nouveau_type_cycle) {
                case 'trimestriel':
                    $tontine->duree_cycle = 3;
                    break;
                case 'semestriel':
                    $tontine->duree_cycle = 6;
                    break;
                case 'annuel':
                    $tontine->duree_cycle = 12;
                    break;
                case 'personnalise':
                    $tontine->duree_cycle = intval($duree_perso);
                    break;
            }
            
            // Recalculer la date de fin
            $date_fin = new DateTime($tontine->date_debut_cycle);
            $date_fin->modify('+' . $tontine->duree_cycle . ' months');
            $tontine->date_fin_cycle = $date_fin->format('Y-m-d');
            
            $tontine->update();
        }
        
        // 4. Rediriger vers l'ordonnancement si mode manuel
        if($tontine->mode_beneficiaire == 'manuel') {
            header("Location: ordonner_membres.php?tontine_id=" . $tontine_id . "&nouveau_cycle=1");
        } else {
            // Mode auto : générer un nouvel ordre aléatoire
            header("Location: generer_ordre_final.php?id=" . $tontine_id . "&nouveau_cycle=1");
        }
        exit();
        
    } elseif($action == 'cloturer') {
        // Clôturer définitivement la tontine
        $query = "UPDATE tontines SET actif = 0 WHERE id = :id";
        $stmt = $db->prepare($query);
        $stmt->execute(['id' => $tontine_id]);
        
        header("Location: mes_tontines.php?success=cloturee");
        exit();
    }
}

// Récupérer l'association
$query = "SELECT nom FROM associations WHERE id = :id";
$stmt = $db->prepare($query);
$stmt->execute(['id' => $_SESSION['association_active']]);
$association_nom = $stmt->fetch()['nom'];
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Renouveler le cycle - <?= htmlspecialchars($tontine->nom) ?></title>
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
            --success-bg: #D1FAE5;
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
            padding: 15px 0;
            box-shadow: 0 2px 20px rgba(0,0,0,0.1);
        }
        
        .navbar-brand, .nav-link {
            color: var(--white) !important;
        }
        
        .navbar-brand {
            font-size: 24px;
            font-weight: 700;
        }
        
        .container {
            max-width: 900px;
            margin: 30px auto;
            padding: 0 20px;
        }
        
        .page-header {
            margin-bottom: 30px;
        }
        
        .page-header h2 {
            color: var(--text-dark);
            font-size: 28px;
            margin-bottom: 5px;
        }
        
        .page-header p {
            color: var(--text-light);
            font-size: 16px;
        }
        
        .card {
            background: var(--white);
            border-radius: 15px;
            border: 1px solid var(--border);
            box-shadow: 0 5px 20px rgba(0,0,0,0.05);
            margin-bottom: 25px;
            overflow: hidden;
        }
        
        .card-header {
            background: var(--primary);
            color: var(--white);
            padding: 15px 20px;
            font-weight: 600;
            font-size: 16px;
        }
        
        .card-header i {
            margin-right: 8px;
        }
        
        .card-body {
            padding: 25px;
        }
        
        .summary-card {
            background: var(--info-bg);
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 25px;
            border-left: 5px solid var(--primary);
        }
        
        .summary-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 15px;
            margin-top: 15px;
        }
        
        .summary-item {
            text-align: center;
            padding: 15px;
            background: var(--white);
            border-radius: 10px;
            border: 1px solid var(--border);
        }
        
        .summary-value {
            font-size: 24px;
            font-weight: 700;
            color: var(--primary);
        }
        
        .summary-label {
            font-size: 13px;
            color: var(--text-light);
            margin-top: 5px;
        }
        
        .member-item {
            display: flex;
            align-items: center;
            padding: 12px;
            border: 1px solid var(--border);
            border-radius: 10px;
            margin-bottom: 8px;
            background: var(--white);
            transition: all 0.2s;
        }
        
        .member-item:hover {
            border-color: var(--primary);
            background: var(--info-bg);
        }
        
        .member-avatar {
            width: 40px;
            height: 40px;
            background: var(--primary);
            color: var(--white);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            margin-right: 15px;
        }
        
        .member-info {
            flex: 1;
        }
        
        .member-name {
            font-weight: 600;
            color: var(--text-dark);
        }
        
        .member-contact {
            font-size: 12px;
            color: var(--text-light);
        }
        
        .form-check-input:checked {
            background-color: var(--primary);
            border-color: var(--primary);
        }
        
        .btn-success {
            background: var(--success);
            border: none;
            padding: 12px 25px;
            border-radius: 8px;
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
            background: transparent;
            padding: 10px 20px;
            border-radius: 8px;
            font-weight: 600;
        }
        
        .btn-outline-secondary:hover {
            background: var(--text-light);
            color: var(--white);
        }
        
        .btn-danger {
            background: var(--danger);
            border: none;
            padding: 12px 25px;
            border-radius: 8px;
            font-weight: 600;
        }
        
        .btn-danger:hover {
            background: #DC2626;
        }
        
        .info-box {
            background: var(--info-bg);
            border-radius: 10px;
            padding: 15px;
            margin: 20px 0;
            border-left: 4px solid var(--primary);
        }
        
        .badge-success {
            background: var(--success-bg);
            color: #065F46;
            padding: 5px 10px;
            border-radius: 20px;
            font-size: 12px;
        }
        
        .badge-warning {
            background: var(--warning-bg);
            color: #92400E;
            padding: 5px 10px;
            border-radius: 20px;
            font-size: 12px;
        }
        
        .section-title {
            font-size: 18px;
            font-weight: 600;
            color: var(--primary);
            margin: 25px 0 15px;
            padding-bottom: 8px;
            border-bottom: 2px solid var(--border);
        }
        
        .alert-warning {
            background: var(--warning-bg);
            color: #92400E;
            border: none;
            border-left: 4px solid var(--warning);
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
                <a class="nav-link" href="voir_membres.php?id=<?= $tontine_id ?>">
                    <i class="bi bi-arrow-left me-1"></i> Retour
                </a>
            </div>
        </div>
    </nav>

    <div class="container">
        <div class="page-header">
            <h2><i class="bi bi-arrow-repeat"></i> Renouvellement du cycle</h2>
            <p><?= htmlspecialchars($tontine->nom) ?> - Cycle n°<?= $tontine->cycle_actuel ?> terminé</p>
        </div>

        <!-- Résumé du cycle terminé -->
        <div class="summary-card">
            <h5><i class="bi bi-bar-chart"></i> Bilan du cycle terminé</h5>
            <div class="summary-grid">
                <div class="summary-item">
                    <div class="summary-value"><?= $nb_membres ?></div>
                    <div class="summary-label">Membres</div>
                </div>
                <div class="summary-item">
                    <div class="summary-value"><?= $nb_seances ?></div>
                    <div class="summary-label">Séances</div>
                </div>
                <div class="summary-item">
                    <div class="summary-value"><?= number_format($total_collecte, 0, ',', ' ') ?> F</div>
                    <div class="summary-label">Collecté</div>
                </div>
                <div class="summary-item">
                    <div class="summary-value"><?= date('d/m/Y', strtotime($tontine->date_debut_cycle)) ?></div>
                    <div class="summary-label">Date début</div>
                </div>
                <div class="summary-item">
                    <div class="summary-value"><?= date('d/m/Y', strtotime($tontine->date_fin_cycle)) ?></div>
                    <div class="summary-label">Date fin</div>
                </div>
            </div>
        </div>

        <!-- Formulaire de renouvellement -->
        <form method="POST" id="renouvellementForm">
            <input type="hidden" name="action" value="renouveler" id="actionInput">

            <div class="card">
                <div class="card-header">
                    <i class="bi bi-people"></i> Étape 1 : Choisir les membres à conserver
                </div>
                <div class="card-body">
                    <p class="text-muted mb-3">
                        Sélectionnez les membres qui participeront au prochain cycle.
                        Les membres non sélectionnés seront désactivés.
                    </p>

                    <?php if(empty($membres_actuels)): ?>
                        <div class="alert alert-warning">
                            <i class="bi bi-exclamation-triangle"></i>
                            Aucun membre actif dans cette tontine.
                        </div>
                    <?php else: ?>
                        <div class="mb-3">
                            <div class="form-check mb-2">
                                <input class="form-check-input" type="checkbox" id="selectAll" checked>
                                <label class="form-check-label fw-bold" for="selectAll">
                                    Tous sélectionner / Désélectionner
                                </label>
                            </div>
                        </div>

                        <div class="member-list">
                            <?php foreach($membres_actuels as $m): ?>
                            <div class="member-item">
                                <input class="form-check-input me-3" type="checkbox" 
                                       name="membres[]" value="<?= $m['id'] ?>" 
                                       id="membre_<?= $m['id'] ?>" checked>
                                <div class="member-avatar">
                                    <?= strtoupper(substr($m['prenom'], 0, 1) . substr($m['nom'], 0, 1)) ?>
                                </div>
                                <div class="member-info">
                                    <div class="member-name">
                                        <?= htmlspecialchars($m['prenom'] . ' ' . $m['nom']) ?>
                                    </div>
                                    <div class="member-contact">
                                        <i class="bi bi-telephone"></i> <?= htmlspecialchars($m['telephone'] ?? 'Non renseigné') ?>
                                        <?php if(!empty($m['email'])): ?>
                                            | <i class="bi bi-envelope"></i> <?= htmlspecialchars($m['email']) ?>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <span class="badge-success">
                                    <i class="bi bi-check-circle"></i> Ordre #<?= $m['ordre_tour'] ?>
                                </span>
                            </div>
                            <?php endforeach; ?>
                        </div>

                        <div class="mt-3">
                            <a href="ajouter_membre.php?id=<?= $tontine_id ?>" class="btn btn-outline-primary btn-sm">
                                <i class="bi bi-person-plus"></i> Ajouter de nouveaux membres
                            </a>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <div class="card">
                <div class="card-header">
                    <i class="bi bi-calendar"></i> Étape 2 : Configurer le nouveau cycle
                </div>
                <div class="card-body">
                    <div class="mb-4">
                        <label class="form-label">Type de cycle</label>
                        <select name="type_cycle" class="form-select" id="type_cycle">
                            <option value="trimestriel" <?= $tontine->type_cycle == 'trimestriel' ? 'selected' : '' ?>>Trimestriel (3 mois)</option>
                            <option value="semestriel" <?= $tontine->type_cycle == 'semestriel' ? 'selected' : '' ?>>Semestriel (6 mois)</option>
                            <option value="annuel" <?= $tontine->type_cycle == 'annuel' ? 'selected' : '' ?>>Annuel (12 mois)</option>
                            <option value="personnalise" <?= $tontine->type_cycle == 'personnalise' ? 'selected' : '' ?>>Cycle personnalisé</option>
                        </select>
                    </div>

                    <div id="duree_personnalisee" style="display: <?= $tontine->type_cycle == 'personnalise' ? 'block' : 'none' ?>;" class="mb-3">
                        <label class="form-label">Durée personnalisée (en mois)</label>
                        <input type="number" name="duree_cycle_perso" class="form-control" 
                               min="1" max="60" value="<?= $tontine->duree_cycle ?? 3 ?>"
                               placeholder="Ex: 4 mois">
                    </div>

                    <div id="apercu_cycle" class="info-box">
                        <i class="bi bi-calendar-check"></i>
                        <span id="message_cycle">
                            <?php
                            $date_fin = new DateTime();
                            $duree = $tontine->duree_cycle ?? 3;
                            $date_fin->modify('+' . $duree . ' months');
                            echo "Nouveau cycle : fin prévue le " . $date_fin->format('d/m/Y');
                            ?>
                        </span>
                    </div>
                </div>
            </div>

            <div class="card">
                <div class="card-header">
                    <i class="bi bi-info-circle"></i> Informations importantes
                </div>
                <div class="card-body">
                    <ul class="mb-0">
                        <li class="mb-2">Le nouveau cycle démarrera immédiatement après validation.</li>
                        <li class="mb-2">L'ordre des bénéficiaires sera à redéfinir (selon le mode de votre tontine).</li>
                        <li class="mb-2">Les membres non conservés seront désactivés mais leur historique restera visible.</li>
                        <li>Vous pourrez ajouter de nouveaux membres avant de démarrer le cycle.</li>
                    </ul>
                </div>
            </div>

            <div class="d-flex justify-content-between mt-4">
                <button type="button" class="btn btn-outline-secondary" onclick="history.back()">
                    <i class="bi bi-arrow-left"></i> Annuler
                </button>
                <div>
                    <button type="button" class="btn btn-danger me-2" onclick="cloturerTontine()">
                        <i class="bi bi-x-circle"></i> Clôturer la tontine
                    </button>
                    <button type="submit" class="btn btn-success">
                        <i class="bi bi-arrow-repeat"></i> Démarrer le nouveau cycle
                    </button>
                </div>
            </div>
        </form>
    </div>

    <script>
        // Gestion de la sélection/désélection de tous les membres
        document.getElementById('selectAll')?.addEventListener('change', function() {
            const checkboxes = document.querySelectorAll('input[name="membres[]"]');
            checkboxes.forEach(cb => cb.checked = this.checked);
        });

        // Gestion de l'affichage de la durée personnalisée
        document.getElementById('type_cycle').addEventListener('change', function() {
            const dureePerso = document.getElementById('duree_personnalisee');
            const message = document.getElementById('message_cycle');
            
            if(this.value === 'personnalise') {
                dureePerso.style.display = 'block';
            } else {
                dureePerso.style.display = 'none';
            }
            
            updateApercu();
        });

        // Mise à jour de l'aperçu
        function updateApercu() {
            const type = document.getElementById('type_cycle').value;
            const message = document.getElementById('message_cycle');
            const dureePerso = document.querySelector('input[name="duree_cycle_perso"]')?.value || 3;
            
            let duree;
            switch(type) {
                case 'trimestriel': duree = 3; break;
                case 'semestriel': duree = 6; break;
                case 'annuel': duree = 12; break;
                case 'personnalise': duree = parseInt(dureePerso) || 3; break;
                default: duree = 3;
            }
            
            const aujourdhui = new Date();
            const dateFin = new Date();
            dateFin.setMonth(aujourdhui.getMonth() + duree);
            
            message.textContent = 'Nouveau cycle : fin prévue le ' + dateFin.toLocaleDateString('fr-FR');
        }

        document.querySelector('input[name="duree_cycle_perso"]')?.addEventListener('input', updateApercu);

        // Confirmation de clôture
        function cloturerTontine() {
            if(confirm(' Êtes-vous sûr de vouloir clôturer définitivement cette tontine ?\nToutes les données seront conservées mais la tontine ne sera plus active.')) {
                document.getElementById('actionInput').value = 'cloturer';
                document.getElementById('renouvellementForm').submit();
            }
        }

        // Confirmation avant renouvellement
        document.getElementById('renouvellementForm').addEventListener('submit', function(e) {
            const action = document.getElementById('actionInput').value;
            
            if(action === 'renouveler') {
                const checkboxes = document.querySelectorAll('input[name="membres[]"]:checked');
                if(checkboxes.length === 0) {
                    e.preventDefault();
                    alert('Veuillez sélectionner au moins un membre à conserver.');
                    return;
                }
                
                if(!confirm('Confirmez-vous le démarrage d\'un nouveau cycle ?')) {
                    e.preventDefault();
                }
            }
        });
    </script>
</body>
</html>