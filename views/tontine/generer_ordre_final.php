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

// Vérifier que la tontine est en mode automatique
if($tontine->mode_beneficiaire != 'auto') {
    header("Location: voir_membres.php?id=" . $tontine_id . "&error=mode_manuel");
    exit();
}

// Vérifier si l'ordre final a déjà été généré
$query = "SELECT COUNT(*) as nb FROM membre_tontine 
          WHERE tontine_id = :tid AND ordre_final IS NOT NULL";
$stmt = $db->prepare($query);
$stmt->execute(['tid' => $tontine_id]);
$ordre_final_existe = $stmt->fetch()['nb'] > 0;

if($ordre_final_existe) {
    header("Location: voir_membres.php?id=" . $tontine_id . "&error=already_generated");
    exit();
}

// Récupérer tous les membres actifs
$query = "SELECT id FROM membre_tontine WHERE tontine_id = :tid AND est_actif = 1";
$stmt = $db->prepare($query);
$stmt->execute(['tid' => $tontine_id]);
$membres = $stmt->fetchAll(PDO::FETCH_ASSOC);

if(empty($membres)) {
    header("Location: voir_membres.php?id=" . $tontine_id . "&error=no_members");
    exit();
}

// Mélanger les IDs
$ids = array_column($membres, 'id');
shuffle($ids);

// Assigner les ordres finaux (de 1 à N)
$ordre = 1;
foreach($ids as $membre_id) {
    $query = "UPDATE membre_tontine SET ordre_final = :ordre WHERE id = :id";
    $stmt = $db->prepare($query);
    $stmt->execute(['ordre' => $ordre, 'id' => $membre_id]);
    $ordre++;
}

// Mettre à jour également ordre_tour avec la même valeur (optionnel)
$query = "UPDATE membre_tontine SET ordre_tour = ordre_final WHERE tontine_id = :tid";
$stmt = $db->prepare($query);
$stmt->execute(['tid' => $tontine_id]);

header("Location: voir_membres.php?id=" . $tontine_id . "&ordre_genere=1");
exit();
?>