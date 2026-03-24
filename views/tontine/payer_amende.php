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
require_once __DIR__ . '/../../models/AmendeAppliquee.php';

$database = new Database();
$db = $database->getConnection();

$amende_id = $_GET['id'] ?? 0;
$seance_id = $_GET['seance_id'] ?? 0;

if(!$amende_id || !$seance_id) {
    header("Location: mes_tontines.php");
    exit();
}

// Récupérer l'amende avec les infos de la tontine et du membre
$query = "SELECT a.*, mt.tontine_id, u.prenom, u.nom, r.type_amende
          FROM amendes_appliquees a
          JOIN membre_tontine mt ON a.membre_tontine_id = mt.id
          JOIN users u ON mt.user_id = u.id
          JOIN regles_amendes r ON a.regle_amende_id = r.id
          WHERE a.id = :id";
$stmt = $db->prepare($query);
$stmt->execute(['id' => $amende_id]);
$amende = $stmt->fetch(PDO::FETCH_ASSOC);

if(!$amende) {
    header("Location: gerer_cotisations.php?seance_id=" . $seance_id);
    exit();
}

// Récupérer la tontine pour connaître son type
$tontine = new Tontine($db);
$tontine->getById($amende['tontine_id']);

// Vérifier les droits
if($tontine->admin_id != $_SESSION['user_id']) {
    header("Location: ../auth/login.php");
    exit();
}

// Marquer l'amende comme payée
$amendeAppliquee = new AmendeAppliquee($db);
$result = $amendeAppliquee->marquerPaye($amende_id, date('Y-m-d'));

if($result) {
    // UNIQUEMENT pour les tontines Solidarité et Prêt : ajouter le montant au solde
    if($tontine->type_tontine == 'solidarite' || $tontine->type_tontine == 'pret') {
        $tontine->updateSoldeCaisse($amende['montant'], 'ajout');
        $tontine->enregistrerOperation(
            'amende', 
            $amende['montant'], 
            "Amende payée - " . $amende['type_amende'] . " - " . $amende['prenom'] . ' ' . $amende['nom']
        );
        // Recharger la tontine pour mettre à jour l'affichage
        $tontine->getById($tontine->id);
    }
    
    $_SESSION['success_message'] = "Amende payée avec succès !";
} else {
    $_SESSION['error_message'] = "Erreur lors du paiement de l'amende";
}

header("Location: gerer_cotisations.php?seance_id=" . $seance_id);
exit();
?>