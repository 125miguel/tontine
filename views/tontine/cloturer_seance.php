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

// Vérifier les droits
$tontine = new Tontine($db);
$tontine->getById($seance->tontine_id);
if($tontine->admin_id != $_SESSION['user_id']) {
    header("Location: ../auth/login.php");
    exit();
}

// Calculer le total
$cotisation = new Cotisation($db);
$total_collecte = $cotisation->calculerTotalSeance($seance_id);

// Clôturer la séance
if($seance->cloturer($seance_id, $total_collecte)) {
    header("Location: rapport_seance.php?seance_id=" . $seance_id . "&cloturee=1");
} else {
    header("Location: rapport_seance.php?seance_id=" . $seance_id . "&error=1");
}
exit();
?>