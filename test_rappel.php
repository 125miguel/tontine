<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/models/Notification.php';
require_once __DIR__ . '/models/Tontine.php';

$database = new Database();
$db = $database->getConnection();

$tontine_id = $_GET['tontine_id'] ?? 0;

if(!$tontine_id) {
    die("Veuillez spécifier un ID de tontine");
}

$tontine = new Tontine($db);
$tontine->getById($tontine_id);

$notification = new Notification($db);

echo "<h2>Test d'envoi de rappel pour la tontine : " . htmlspecialchars($tontine->nom) . "</h2>";

// Envoyer un rappel de réunion
echo "<h3>Envoi du rappel de réunion...</h3>";
$result = $notification->rappelReunion($tontine_id, $tontine->prochaine_reunion);

echo "<p>✅ Rappel envoyé à " . $result . " membres</p>";

echo "<p><a href='views/tontine/mes_tontines.php'>Retour</a></p>";
?>