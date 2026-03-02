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
require_once __DIR__ . '/../../models/Seance.php';

$database = new Database();
$db = $database->getConnection();

$tontine_id = $_GET['tontine_id'] ?? 0;

// Vérifier la tontine
$tontine = new Tontine($db);
if(!$tontine->getById($tontine_id) || $tontine->admin_id != $_SESSION['user_id']) {
    header("Location: ../tontine/mes_tontines.php");
    exit();
}

// Récupérer la prochaine réunion
$seance = new Seance($db);
$seance->getSeanceActive($tontine_id);
$date_reunion = $seance->date_seance ?? $tontine->prochaine_reunion;

// Envoyer les rappels
$notification = new Notification($db);
$nb_envoyes = $notification->rappelReunion($tontine_id, $date_reunion);

header("Location: rappels.php?tontine_id=" . $tontine_id . "&envoi=" . $nb_envoyes);
exit();
?>