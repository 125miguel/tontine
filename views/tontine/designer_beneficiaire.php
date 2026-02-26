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

// Récupérer la tontine pour vérifier les droits
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

// Récupérer tous les membres qui ont payé (pour les proposer comme bénéficiaires)
$query = "SELECT c.*, mt.user_id, u.nom, u.prenom, mt.ordre_tour
          FROM cotisations c
          JOIN membre_tontine mt ON c.membre_tontine_id = mt.id
          JOIN users u ON mt.user_id = u.id
          WHERE c.seance_id = :seance_id AND c.statut = 'paye'
          ORDER BY mt.ordre_tour ASC";

$stmt = $db->prepare($query);
$stmt->execute(['seance_id' => $seance_id]);
$beneficiaires_potentiels = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Traiter la sélection du bénéficiaire
if($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['designer'])) {
    $membre_tontine_id = $_POST['membre_id'] ?? 0;
    
    if(!$membre_tontine_id) {
        $error = "Veuillez sélectionner un bénéficiaire";
    } else {
       // Désigner le bénéficiaire (sans clôturer)
        if($seance->setBeneficiaire($seance_id, $membre_tontine_id)) {
            $success = " Bénéficiaire désigné avec succès : " . htmlspecialchars($_POST['beneficiaire_nom']);
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
    </style>
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary">
        <div class="container">
            <a class="navbar-brand" href="../dashboard.php">
                <i class="bi bi-bank2"></i> Tontine
            </a>
            <div class="navbar-nav ms-auto">
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
                        <h4> <?= $success ?></h4>
                        <p>Redirection vers la liste des tontines...</p>
                    </div>
                <?php else: ?>

                <div class="card mb-4">
                    <div class="card-header bg-success text-white">
                        <h4 class="mb-0"> Désigner le bénéficiaire du jour</h4>
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

                <?php if(empty($beneficiaires_potentiels)): ?>
                    <div class="alert alert-warning text-center">
                        <i class="bi bi-exclamation-triangle fs-1"></i>
                        <h4>Aucun membre n'a encore payé</h4>
                        <p>Vous devez d'abord enregistrer les paiements avant de désigner un bénéficiaire.</p>
                        <a href="gerer_cotisations.php?seance_id=<?= $seance_id ?>" class="btn btn-primary">
                            Retour à la gestion des cotisations
                        </a>
                    </div>
                <?php else: ?>
                    
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
                                <i class="bi bi-check-circle"></i> Désigner le bénéficiaire et clôturer
                            </button>
                        </div>
                    </form>

                    <div class="alert alert-info mt-4">
                        <i class="bi bi-info-circle"></i>
                        <strong>Note:</strong> Une fois le bénéficiaire désigné, la séance sera clôturée et on ne pourra plus modifier les cotisations.
                    </div>

                <?php endif; ?>
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