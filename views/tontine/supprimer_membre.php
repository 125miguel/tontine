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

$membre_id = $_GET['id'] ?? 0;
$tontine_id = $_GET['tontine_id'] ?? 0;

if(!$membre_id || !$tontine_id) {
    header("Location: mes_tontines.php");
    exit();
}

// Vérifier que la tontine appartient à cet admin
$tontine = new Tontine($db);
if(!$tontine->getById($tontine_id) || $tontine->admin_id != $_SESSION['user_id']) {
    header("Location: mes_tontines.php");
    exit();
}

// Vérifier que le membre n'a pas d'activités
$membreTontine = new MembreTontine($db);
if($membreTontine->aDesActivites($membre_id)) {
    header("Location: voir_membres.php?id=" . $tontine_id . "&error=activites");
    exit();
}

// Supprimer définitivement
$query = "DELETE FROM membre_tontine WHERE id = :id AND tontine_id = :tontine_id";
$stmt = $db->prepare($query);
$stmt->execute(['id' => $membre_id, 'tontine_id' => $tontine_id]);

header("Location: voir_membres.php?id=" . $tontine_id . "&supprime=1");
exit();
?>