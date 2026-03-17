<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();

// Vérifier si l'utilisateur est connecté et est admin
if(!isset($_SESSION['user_id']) || $_SESSION['association_role'] != 'admin') {
    header("Location: ../auth/login.php");
    exit();
}

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../models/Tontine.php';

$database = new Database();
$db = $database->getConnection();

// Récupérer l'association du président
$query = "SELECT id, nom FROM associations WHERE admin_id = :admin_id";
$stmt = $db->prepare($query);
$stmt->execute(['admin_id' => $_SESSION['user_id']]);
$association = $stmt->fetch(PDO::FETCH_ASSOC);

if(!$association) {
    // Normalement ça n'arrive pas, mais au cas où
    header("Location: mes_tontines.php?error=no_association");
    exit();
}

$error = '';
$success = '';

if($_SERVER['REQUEST_METHOD'] == 'POST') {
    
    $nom = $_POST['nom'] ?? '';
    $description = $_POST['description'] ?? '';
    $type_tontine = $_POST['type_tontine'] ?? 'anniversaire';
    $mode_beneficiaire = $_POST['mode_beneficiaire'] ?? 'manuel';
    $montant = $_POST['montant'] ?? '';
    $periodicite = $_POST['periodicite'] ?? '';
    $jour_reunion = $_POST['jour_reunion'] ?? '';
    $prochaine_reunion = $_POST['prochaine_reunion'] ?? '';
    
    // NOUVEAU : Récupérer les données du cycle
    $type_cycle = $_POST['type_cycle'] ?? '';
    $duree_cycle_perso = $_POST['duree_cycle_perso'] ?? 0;
    
    if(empty($nom) || empty($montant) || empty($periodicite) || empty($jour_reunion) || empty($prochaine_reunion)) {
        $error = "Veuillez remplir tous les champs obligatoires";
    } else {
        $tontine = new Tontine($db);
        $tontine->nom = $nom;
        $tontine->description = $description;
        $tontine->type_tontine = $type_tontine;
        $tontine->mode_beneficiaire = $mode_beneficiaire;
        $tontine->montant_cotisation = $montant;
        $tontine->periodicite = $periodicite;
        $tontine->jour_reunion = $jour_reunion;
        $tontine->prochaine_reunion = $prochaine_reunion;
        $tontine->admin_id = $_SESSION['user_id'];
        $tontine->association_id = $association['id'];
        
        // NOUVEAU : Initialiser le cycle si sélectionné
        if(!empty($type_cycle)) {
            $tontine->type_cycle = $type_cycle;
            
            // Calculer la durée en mois selon le type
            switch($type_cycle) {
                case 'trimestriel':
                    $duree = 3;
                    break;
                case 'semestriel':
                    $duree = 6;
                    break;
                case 'annuel':
                    $duree = 12;
                    break;
                case 'personnalise':
                    $duree = intval($duree_cycle_perso);
                    break;
                default:
                    $duree = 0;
            }
            
            if($duree > 0) {
                $tontine->duree_cycle = $duree;
                $tontine->date_debut_cycle = date('Y-m-d');
                
                // Calculer la date de fin
                $date_fin = new DateTime();
                $date_fin->modify('+' . $duree . ' months');
                $tontine->date_fin_cycle = $date_fin->format('Y-m-d');
                
                $tontine->cycle_actuel = 1;
                $tontine->cycle_termine = 0;
            }
        } else {
            // Pas de cycle
            $tontine->type_cycle = null;
            $tontine->duree_cycle = null;
            $tontine->date_debut_cycle = null;
            $tontine->date_fin_cycle = null;
            $tontine->cycle_actuel = 1;
        }
        
        if($tontine->create()) {
            $_SESSION['tontine_created'] = "Tontine créée avec succès !";
            $_SESSION['new_tontine_id'] = $tontine->id;
            
            if($mode_beneficiaire == 'manuel') {
                header("Location: ajouter_membre.php?id=" . $tontine->id . "&mode=manuel");
            } else {
                header("Location: mes_tontines.php");
            }
            exit();
        } else {
            $error = "Erreur lors de la création";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Créer une tontine - <?= htmlspecialchars($association['nom']) ?></title>
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
            --danger: #EF4444;
            --success: #10B981;
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
        }
        
        .card-header {
            background: var(--primary);
            color: var(--white);
            border-radius: 15px 15px 0 0 !important;
        }
        
        .form-control, .form-select {
            border-radius: 10px;
            border: 2px solid var(--border);
            padding: 12px;
            transition: all 0.3s;
        }
        
        .form-control:focus, .form-select:focus {
            border-color: var(--primary);
            box-shadow: none;
            outline: none;
        }
        
        .btn-primary {
            background: var(--primary);
            border: none;
            padding: 12px;
            border-radius: 10px;
            font-weight: 600;
            transition: all 0.3s;
        }
        
        .btn-primary:hover {
            background: var(--primary-light);
            transform: translateY(-2px);
            box-shadow: 0 10px 30px rgba(30, 58, 138, 0.3);
        }
        
        .alert-danger {
            background: #FEE2E2;
            color: #991B1B;
            border: none;
            border-radius: 10px;
        }
        
        .alert-success {
            background: #D1FAE5;
            color: #065F46;
            border: none;
            border-radius: 10px;
        }
        
        .badge-association {
            background: rgba(255,255,255,0.2);
            color: var(--white);
            padding: 5px 15px;
            border-radius: 50px;
            font-size: 14px;
        }
        
        .text-danger {
            color: var(--danger) !important;
        }
        
        .form-label {
            font-weight: 500;
            color: var(--text-dark);
        }
        
        .info-box {
            background: var(--info-bg);
            border-left: 4px solid var(--primary);
            padding: 15px;
            border-radius: 10px;
            margin-bottom: 20px;
        }
        
        .info-box i {
            color: var(--primary);
            margin-right: 10px;
        }
        
        .cycle-option {
            background: var(--white);
            border: 1px solid var(--border);
            border-radius: 10px;
            padding: 15px;
            margin-bottom: 10px;
            transition: all 0.2s;
        }
        
        .cycle-option:hover {
            border-color: var(--primary);
            box-shadow: 0 2px 10px rgba(30, 58, 138, 0.1);
        }
        
        .cycle-option.selected {
            border-color: var(--primary);
            background: var(--info-bg);
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
                    <i class="bi bi-building"></i> <?= htmlspecialchars($association['nom']) ?>
                </span>
                <a class="nav-link" href="mes_tontines.php">
                    <i class="bi bi-arrow-left"></i> Retour
                </a>
            </div>
        </div>
    </nav>

    <div class="container mt-5">
        <div class="row justify-content-center">
            <div class="col-md-8">
                <div class="card">
                    <div class="card-header">
                        <h4 class="mb-0"><i class="bi bi-plus-circle"></i> Créer une nouvelle tontine</h4>
                    </div>
                    <div class="card-body">
                        
                        <?php if($error): ?>
                            <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
                        <?php endif; ?>
                        
                        <?php if($success): ?>
                            <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
                        <?php endif; ?>

                        <form method="POST" action="">
                            <div class="mb-3">
                                <label class="form-label">Nom de la tontine <span class="text-danger">*</span></label>
                                <input type="text" name="nom" class="form-control" 
                                       value="<?= htmlspecialchars($_POST['nom'] ?? '') ?>" required>
                            </div>

                            <div class="mb-3">
                                <label class="form-label">Description</label>
                                <textarea name="description" class="form-control" rows="3"><?= htmlspecialchars($_POST['description'] ?? '') ?></textarea>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Type de tontine</label>
                                    <select name="type_tontine" class="form-select">
                                        <option value="anniversaire" <?= ($_POST['type_tontine'] ?? '') == 'anniversaire' ? 'selected' : '' ?>> Anniversaire</option>
                                        <option value="djangui" <?= ($_POST['type_tontine'] ?? '') == 'djangui' ? 'selected' : '' ?>> Djangui</option>
                                        <option value="solidarite" <?= ($_POST['type_tontine'] ?? '') == 'solidarite' ? 'selected' : '' ?>> Solidarité</option>
                                    </select>
                                </div>

                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Mode bénéficiaire</label>
                                    <select name="mode_beneficiaire" class="form-select">
                                        <option value="manuel" <?= ($_POST['mode_beneficiaire'] ?? '') == 'manuel' ? 'selected' : '' ?>> Manuel</option>
                                        <option value="auto" <?= ($_POST['mode_beneficiaire'] ?? '') == 'auto' ? 'selected' : '' ?>> Automatique</option>
                                    </select>
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Montant (FCFA) <span class="text-danger">*</span></label>
                                    <input type="number" name="montant" class="form-control" 
                                           value="<?= htmlspecialchars($_POST['montant'] ?? '') ?>" required>
                                </div>

                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Périodicité <span class="text-danger">*</span></label>
                                    <select name="periodicite" class="form-select" required>
                                        <option value="hebdomadaire" <?= ($_POST['periodicite'] ?? '') == 'hebdomadaire' ? 'selected' : '' ?>>Hebdomadaire</option>
                                        <option value="mensuel" <?= ($_POST['periodicite'] ?? '') == 'mensuel' ? 'selected' : '' ?>>Mensuel</option>
                                        <option value="journalier" <?= ($_POST['periodicite'] ?? '') == 'journalier' ? 'selected' : '' ?>>Journalier</option>
                                    </select>
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Jour de réunion <span class="text-danger">*</span></label>
                                    <input type="text" name="jour_reunion" class="form-control" 
                                           value="<?= htmlspecialchars($_POST['jour_reunion'] ?? '') ?>"
                                           placeholder="Ex: Samedi, 15 du mois" required>
                                </div>

                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Prochaine réunion <span class="text-danger">*</span></label>
                                    <input type="date" name="prochaine_reunion" class="form-control" 
                                           value="<?= htmlspecialchars($_POST['prochaine_reunion'] ?? date('Y-m-d')) ?>" required>
                                </div>
                            </div>

                            <!-- NOUVELLE SECTION : Gestion des cycles -->
                            <div class="info-box mb-4">
                                <i class="bi bi-arrow-repeat"></i>
                                <strong>Gestion des cycles (optionnel)</strong> - Définissez la durée de vie de votre tontine
                            </div>

                            <div class="mb-4">
                                <label class="form-label">Type de cycle</label>
                                <select name="type_cycle" class="form-select" id="type_cycle">
                                    <option value="">Aucun cycle (tontine unique)</option>
                                    <option value="trimestriel" <?= ($_POST['type_cycle'] ?? '') == 'trimestriel' ? 'selected' : '' ?>>Trimestriel (3 mois)</option>
                                    <option value="semestriel" <?= ($_POST['type_cycle'] ?? '') == 'semestriel' ? 'selected' : '' ?>>Semestriel (6 mois)</option>
                                    <option value="annuel" <?= ($_POST['type_cycle'] ?? '') == 'annuel' ? 'selected' : '' ?>>Annuel (12 mois)</option>
                                    <option value="personnalise" <?= ($_POST['type_cycle'] ?? '') == 'personnalise' ? 'selected' : '' ?>>Cycle personnalisé</option>
                                </select>
                                <small class="text-muted">
                                    Le cycle définit la durée pendant laquelle tous les membres pourront bénéficier. 
                                    À la fin du cycle, vous pourrez renouveler la tontine.
                                </small>
                            </div>

                            <div id="duree_personnalisee" style="display: none;" class="mb-4">
                                <label class="form-label">Durée personnalisée (en mois)</label>
                                <input type="number" name="duree_cycle_perso" class="form-control" 
                                       min="1" max="60" value="<?= htmlspecialchars($_POST['duree_cycle_perso'] ?? '3') ?>"
                                       placeholder="Ex: 4 mois">
                                <small class="text-muted">Entrez le nombre de mois (maximum 60 mois = 5 ans)</small>
                            </div>

                            <!-- Aperçu du cycle -->
                            <div id="apercu_cycle" class="alert alert-info" style="display: none;">
                                <i class="bi bi-calendar-check"></i>
                                <span id="message_cycle"></span>
                            </div>

                            <!-- Informations sur les cycles -->
                            <div class="cycle-option">
                                <h6><i class="bi bi-question-circle"></i> Comment fonctionnent les cycles ?</h6>
                                <ul class="mb-0 small">
                                    <li>Un cycle permet de planifier la durée de vie de votre tontine</li>
                                    <li>À la fin du cycle, tous les membres auront bénéficié au moins une fois</li>
                                    <li>Vous pourrez alors démarrer un nouveau cycle avec les mêmes membres ou en ajouter de nouveaux</li>
                                    <li>L'ordre des bénéficiaires pourra être redéfini à chaque nouveau cycle</li>
                                </ul>
                            </div>

                            <button type="submit" class="btn btn-primary w-100 mt-3">
                                <i class="bi bi-plus-circle"></i> Créer la tontine
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
    // Gestion de l'affichage du champ de durée personnalisée
    document.getElementById('type_cycle').addEventListener('change', function() {
        const dureePerso = document.getElementById('duree_personnalisee');
        const apercu = document.getElementById('apercu_cycle');
        const message = document.getElementById('message_cycle');
        
        if(this.value === 'personnalise') {
            dureePerso.style.display = 'block';
        } else {
            dureePerso.style.display = 'none';
        }
        
        // Afficher l'aperçu
        if(this.value) {
            let texte = '';
            const aujourdhui = new Date();
            const dateFin = new Date();
            
            switch(this.value) {
                case 'trimestriel':
                    dateFin.setMonth(aujourdhui.getMonth() + 3);
                    texte = 'Cycle trimestriel : fin prévue le ' + dateFin.toLocaleDateString('fr-FR');
                    break;
                case 'semestriel':
                    dateFin.setMonth(aujourdhui.getMonth() + 6);
                    texte = 'Cycle semestriel : fin prévue le ' + dateFin.toLocaleDateString('fr-FR');
                    break;
                case 'annuel':
                    dateFin.setMonth(aujourdhui.getMonth() + 12);
                    texte = 'Cycle annuel : fin prévue le ' + dateFin.toLocaleDateString('fr-FR');
                    break;
                case 'personnalise':
                    const duree = document.querySelector('input[name="duree_cycle_perso"]').value || 3;
                    dateFin.setMonth(aujourdhui.getMonth() + parseInt(duree));
                    texte = 'Cycle personnalisé de ' + duree + ' mois : fin prévue le ' + dateFin.toLocaleDateString('fr-FR');
                    break;
            }
            
            message.textContent = texte;
            apercu.style.display = 'block';
        } else {
            apercu.style.display = 'none';
        }
    });

    // Mettre à jour l'aperçu quand la durée personnalisée change
    document.querySelector('input[name="duree_cycle_perso"]')?.addEventListener('input', function() {
        const typeCycle = document.getElementById('type_cycle').value;
        if(typeCycle === 'personnalise') {
            const apercu = document.getElementById('apercu_cycle');
            const message = document.getElementById('message_cycle');
            const aujourdhui = new Date();
            const dateFin = new Date();
            const duree = this.value || 3;
            
            dateFin.setMonth(aujourdhui.getMonth() + parseInt(duree));
            message.textContent = 'Cycle personnalisé de ' + duree + ' mois : fin prévue le ' + dateFin.toLocaleDateString('fr-FR');
            apercu.style.display = 'block';
        }
    });

    // Déclencher l'événement au chargement si une valeur est déjà sélectionnée
    window.addEventListener('load', function() {
        const event = new Event('change');
        document.getElementById('type_cycle').dispatchEvent(event);
    });
    </script>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>