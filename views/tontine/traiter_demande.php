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

$database = new Database();
$db = $database->getConnection();

$demande_id = $_GET['id'] ?? 0;
$action = $_GET['action'] ?? '';
$tontine_id = $_GET['tontine_id'] ?? 0;

if(!$demande_id || !$action || !$tontine_id) {
    header("Location: mes_tontines.php");
    exit();
}

// Récupérer la demande avec les infos du membre
$query = "SELECT d.*, t.solde_caisse, t.id as tontine_id, t.nom as tontine_nom,
                 u.prenom, u.nom
          FROM demandes_aide d
          JOIN tontines t ON d.tontine_id = t.id
          JOIN membre_tontine mt ON d.membre_id = mt.id
          JOIN users u ON mt.user_id = u.id
          WHERE d.id = :id AND t.admin_id = :admin_id";
$stmt = $db->prepare($query);
$stmt->execute(['id' => $demande_id, 'admin_id' => $_SESSION['user_id']]);
$demande = $stmt->fetch(PDO::FETCH_ASSOC);

if(!$demande) {
    header("Location: demander_aide.php?tontine_id=" . $tontine_id);
    exit();
}

$tontine = new Tontine($db);
$tontine->getById($tontine_id);

$error = '';
$success = '';

if($action == 'approuver') {
    // Vérifier si le solde est suffisant
    if($tontine->solde_caisse < $demande['montant_demande']) {
        $error = "Solde insuffisant ! Solde actuel : " . number_format($tontine->solde_caisse, 0, ',', ' ') . " FCFA";
    } else {
        // Mettre à jour la demande
        $query = "UPDATE demandes_aide 
                  SET statut = 'approuve', 
                      montant_accorde = montant_demande,
                      date_traitement = NOW(),
                      valide_par = :admin_id
                  WHERE id = :id";
        $stmt = $db->prepare($query);
        $stmt->execute(['admin_id' => $_SESSION['user_id'], 'id' => $demande_id]);
        
        // Retirer le montant du solde de la tontine
        $tontine->updateSoldeCaisse($demande['montant_demande'], 'retrait');
        
        // Enregistrer l'opération avec le nom du membre
        $tontine->enregistrerOperation(
            'aide', 
            $demande['montant_demande'], 
            "Aide accordée à " . $demande['prenom'] . ' ' . $demande['nom'] . " (" . ucfirst($demande['type_demande']) . ")",
            $demande_id
        );
        
        // Recharger la tontine
        $tontine->getById($tontine_id);
        
        $success = "Demande approuvée avec succès ! Montant versé : " . number_format($demande['montant_demande'], 0, ',', ' ') . " FCFA";
    }
} elseif($action == 'refuser') {
    // Mettre à jour la demande
    $query = "UPDATE demandes_aide 
              SET statut = 'refuse', 
                  date_traitement = NOW(),
                  valide_par = :admin_id
              WHERE id = :id";
    $stmt = $db->prepare($query);
    $stmt->execute(['admin_id' => $_SESSION['user_id'], 'id' => $demande_id]);
    
    $success = "Demande refusée avec succès !";
}

// Redirection
if($success) {
    $_SESSION['success_message'] = $success;
} elseif($error) {
    $_SESSION['error_message'] = $error;
}

header("Location: demander_aide.php?tontine_id=" . $tontine_id);
exit();
?>