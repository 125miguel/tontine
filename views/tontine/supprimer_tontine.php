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

$database = new Database();
$db = $database->getConnection();

$tontine_id = $_GET['id'] ?? 0;

if(!$tontine_id) {
    header("Location: mes_tontines.php");
    exit();
}

// Vérifier que la tontine appartient à cet admin
$tontine = new Tontine($db);
if(!$tontine->getById($tontine_id) || $tontine->admin_id != $_SESSION['user_id']) {
    header("Location: mes_tontines.php");
    exit();
}

// Supprimer la tontine (les données liées seront supprimées en cascade grâce aux FOREIGN KEY)
$query = "DELETE FROM tontines WHERE id = :id AND admin_id = :admin_id";
$stmt = $db->prepare($query);

if($stmt->execute(['id' => $tontine_id, 'admin_id' => $_SESSION['user_id']])) {
    header("Location: mes_tontines.php?supprime=1");
} else {
    header("Location: mes_tontines.php?error=1");
}
exit();
?>