<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();

if(!isset($_SESSION['user_id']) || $_SESSION['user_role'] != 'admin') {
    header("Location: ../auth/login.php");
    exit();
}

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../models/Seance.php';
require_once __DIR__ . '/../../models/Cotisation.php';
require_once __DIR__ . '/../../models/Tontine.php';
require_once __DIR__ . '/../../models/MembreTontine.php';

$database = new Database();
$db = $database->getConnection();

$seance_id = $_GET['seance_id'] ?? 0;

if(!$seance_id) {
    header("Location: mes_tontines.php");
    exit();
}

$seance = new Seance($db);
if(!$seance->getById($seance_id)) {
    header("Location: mes_tontines.php");
    exit();
}

// Récupérer la tontine pour vérifier les droits et le mode
$tontine = new Tontine($db);
$tontine->getById($seance->tontine_id);

if($tontine->admin_id != $_SESSION['user_id']) {
    header("Location: ../auth/login.php");
    exit();
}

// Vérifier que la séance n'est pas déjà clôturée
if($seance->est_cloturee) {
    header("Location: gerer_cotisations.php?seance_id=" . $seance_id);
    exit();
}

$cotisation = new Cotisation($db);
$membreTontine = new MembreTontine($db);

// Calculer le total collecté
$total_collecte = $cotisation->calculerTotalSeance($seance_id);
$nb_payes = $cotisation->countPayes($seance_id);

// Récupérer tous les membres qui ont payé
$query = "SELECT c.*, mt.user_id, u.nom, u.prenom, mt.ordre_tour
          FROM cotisations c
          JOIN membre_tontine mt ON c.membre_tontine_id = mt.id
          JOIN users u ON mt.user_id = u.id
          WHERE c.seance_id = :seance_id AND c.statut = 'paye'
          ORDER BY mt.ordre_tour ASC";

$stmt = $db->prepare($query);
$stmt->execute(['seance_id' => $seance_id]);
$beneficiaires_potentiels = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Mode automatique : trouver le prochain bénéficiaire
$prochain_beneficiaire = null;
if($tontine->mode_beneficiaire == 'auto' && !empty($beneficiaires_potentiels)) {
    // Récupérer le dernier bénéficiaire
    $query = "SELECT beneficiaire_id FROM seances 
              WHERE tontine_id = :tid AND beneficiaire_id IS NOT NULL 
              ORDER BY date_seance DESC LIMIT 1";
    $stmt = $db->prepare($query);
    $stmt->execute(['tid' => $tontine->id]);
    $dernier = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if($dernier) {
        // Trouver le suivant dans l'ordre
        $ordre_dernier = 0;
        foreach($beneficiaires_potentiels as $b) {
            if($b['membre_tontine_id'] == $dernier['beneficiaire_id']) {
                $ordre_dernier = $b['ordre_tour'];
                break;
            }
        }
        
        // Chercher le suivant avec un ordre supérieur
        $suivant = null;
        foreach($beneficiaires_potentiels as $b) {
            if($b['ordre_tour'] > $ordre_dernier) {
                $suivant = $b;
                break;
            }
        }
        
        // Si pas de suivant, prendre le premier (retour au début)
        if(!$suivant && !empty($beneficiaires_potentiels)) {
            $suivant = $beneficiaires_potentiels[0];
        }
        
        $prochain_beneficiaire = $suivant;
    } elseif(!empty($beneficiaires_potentiels)) {
        // Premier bénéficiaire de la tontine
        $prochain_beneficiaire = $beneficiaires_potentiels[0];
    }
}

// Traiter la sélection du bénéficiaire
if($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['designer'])) {
    $membre_tontine_id = $_POST['membre_id'] ?? 0;
    
    if(!$membre_tontine_id) {
        $error = "Veuillez sélectionner un bénéficiaire";
    } else {
        // Désigner le bénéficiaire (sans clôturer)
        if($seance->setBeneficiaire($seance_id, $membre_tontine_id)) {
            $success = "Bénéficiaire désigné avec succès : " . htmlspecialchars($_POST['beneficiaire_nom']);
            header("refresh:2;url=rapport_seance.php?seance_id=" . $seance_id);
            exit();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Désigner le bénéficiaire</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.8.1/font/bootstrap-icons.css">
    <style>
        .beneficiaire-card {
            cursor: pointer;
            transition: all 0.3s;
            border: 2px solid transparent;
        }
        .beneficiaire-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
        }
        .beneficiaire-card.selected {
            border-color: #28a745;
            background-color: #f0fff0;
        }
        .total-amount {
            font-size: 2.5rem;
            font-weight: bold;
            color: #28a745;
        }
        .auto-suggestion {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 30px;
            border-radius: 15px;
            text-align: center;
            margin-bottom: 30px;
        }
        .auto-suggestion h3 {
            font-size: 2rem;
            margin: 15px 0;
        }
        .badge-mode {
            position: absolute;
            top: 10px;
            right: 10px;
        }
    </style>
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary">
        <div class="container">
            <a class="navbar-brand" href="../dashboard.php">
                <i class="bi bi-bank2"></i> Tontine
            </a>
            <div class="navbar-nav ms-auto">
                <span class="nav-link text-white">
                    Mode: <strong><?= $tontine->mode_beneficiaire == 'auto' ? 'Automatique' : 'Manuel' ?></strong>
                </span>
                <a class="nav-link" href="gerer_cotisations.php?seance_id=<?= $seance_id ?>">
                    <i class="bi bi-arrow-left"></i> Retour
                </a>
            </div>
        </div>
    </nav>

    <div class="container mt-5">
        <div class="row">
            <div class="col-md-8 offset-md-2">
                
                <?php if(isset($success)): ?>
                    <div class="alert alert-success text-center">
                        <h4><?= $success ?></h4>
                        <p>Redirection vers le rapport de séance...</p>
                    </div>
                <?php else: ?>

                <?php if(isset($error)): ?>
                    <div class="alert alert-danger"><?= $error ?></div>
                <?php endif; ?>

                <div class="card mb-4">
                    <div class="card-header bg-success text-white">
                        <h4 class="mb-0"><i class="bi bi-trophy"></i> Désigner le bénéficiaire du jour</h4>
                    </div>
                    <div class="card-body text-center">
                        <h5>Total collecté pour cette séance</h5>
                        <div class="total-amount">
                            <?= number_format($total_collecte, 0, ',', ' ') ?> FCFA
                        </div>
                        <p class="text-muted">
                            <?= $nb_payes ?> membre(s) ont cotisé
                        </p>
                    </div>
                </div>

                <?php if($tontine->mode_beneficiaire == 'auto' && $prochain_beneficiaire): ?>
                    <!-- Mode automatique : suggestion -->
                    <div class="auto-suggestion">
                        <h5>Mode automatique</h5>
                        <p>Le prochain bénéficiaire selon l'ordre est :</p>
                        <h3><?= htmlspecialchars($prochain_beneficiaire['prenom'] . ' ' . $prochain_beneficiaire['nom']) ?></h3>
                        <p class="mb-3">Ordre n°<?= $prochain_beneficiaire['ordre_tour'] ?></p>
                        <form method="POST">
                            <input type="hidden" name="membre_id" value="<?= $prochain_beneficiaire['membre_tontine_id'] ?>">
                            <input type="hidden" name="beneficiaire_nom" value="<?= htmlspecialchars($prochain_beneficiaire['prenom'] . ' ' . $prochain_beneficiaire['nom']) ?>">
                            <button type="submit" name="designer" class="btn btn-light btn-lg">
                                <i class="bi bi-check-circle"></i> Confirmer ce bénéficiaire
                            </button>
                        </form>
                    </div>
                <?php endif; ?>

                <?php if(empty($beneficiaires_potentiels)): ?>
                    <div class="alert alert-warning text-center">
                        <i class="bi bi-exclamation-triangle fs-1"></i>
                        <h4>Aucun membre n'a encore payé</h4>
                        <p>Vous devez d'abord enregistrer les paiements avant de désigner un bénéficiaire.</p>
                        <a href="gerer_cotisations.php?seance_id=<?= $seance_id ?>" class="btn btn-primary">
                            Retour à la gestion des cotisations
                        </a>
                    </div>
                <?php elseif($tontine->mode_beneficiaire == 'manuel'): ?>
                    <!-- Mode manuel : choix libre -->
                    <form method="POST" id="formBeneficiaire">
                        <input type="hidden" name="membre_id" id="selectedMembreId" value="">
                        <input type="hidden" name="beneficiaire_nom" id="selectedMembreNom" value="">
                        
                        <div class="row">
                            <?php foreach($beneficiaires_potentiels as $benef): ?>
                                <div class="col-md-6 mb-3">
                                    <div class="card beneficiaire-card" 
                                         onclick="selectBeneficiaire(<?= $benef['membre_tontine_id'] ?>, '<?= htmlspecialchars($benef['prenom'] . ' ' . $benef['nom']) ?>', this)">
                                        <div class="card-body">
                                            <h5 class="card-title">
                                                <?= htmlspecialchars($benef['prenom'] . ' ' . $benef['nom']) ?>
                                            </h5>
                                            <p class="card-text">
                                                <strong>Ordre:</strong> <?= $benef['ordre_tour'] ?><br>
                                                <strong>A payé:</strong> <?= number_format($benef['montant'], 0, ',', ' ') ?> F
                                            </p>
                                            <div class="text-center">
                                                <span class="badge bg-success">Cliquer pour sélectionner</span>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>

                        <div class="text-center mt-4">
                            <button type="submit" name="designer" class="btn btn-success btn-lg" id="btnDesigner" disabled>
                                <i class="bi bi-check-circle"></i> Désigner le bénéficiaire
                            </button>
                        </div>
                    </form>
                <?php endif; ?>

                <div class="alert alert-info mt-4">
                    <i class="bi bi-info-circle"></i>
                    <strong>Note:</strong> La séance ne sera pas clôturée après cette désignation. Vous pourrez consulter le rapport et clôturer plus tard.
                </div>

                <?php endif; ?>
            </div>
        </div>
    </div>

    <script>
    function selectBeneficiaire(membreId, membreNom, element) {
        // Enlever la sélection de toutes les cartes
        document.querySelectorAll('.beneficiaire-card').forEach(card => {
            card.classList.remove('selected');
        });
        
        // Ajouter la sélection à la carte cliquée
        element.classList.add('selected');
        
        // Mettre à jour les champs cachés
        document.getElementById('selectedMembreId').value = membreId;
        document.getElementById('selectedMembreNom').value = membreNom;
        
        // Activer le bouton
        document.getElementById('btnDesigner').disabled = false;
    }
    </script>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>