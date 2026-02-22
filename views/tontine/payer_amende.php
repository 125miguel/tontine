<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();

if(!isset($_SESSION['user_id']) || $_SESSION['user_role'] != 'admin') {
    header("Location: ../auth/login.php");
    exit();
}

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../models/AmendeAppliquee.php';

$database = new Database();
$db = $database->getConnection();

$amende_id = $_GET['id'] ?? 0;
$seance_id = $_GET['seance_id'] ?? 0;

if($amende_id && $seance_id) {
    $amendeAppliquee = new AmendeAppliquee($db);
    $amendeAppliquee->marquerPaye($amende_id, date('Y-m-d'));
}

header("Location: gerer_cotisations.php?seance_id=" . $seance_id);
exit();
?>