<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();
require_once 'config/database.php';
require_once 'models/MembreTontine.php';
require_once 'models/Cotisation.php';

$database = new Database();
$db = $database->getConnection();

// Mets ici l'ID du membre que tu veux tester
$user_id = 2;  // REMPLACE PAR L'ID DE TON MEMBRE (ex: 2, 3, etc.)

echo "<h2> TEST POUR LE MEMBRE ID: " . $user_id . "</h2>";

// 1. Vérifier dans membre_tontine
$query = "SELECT * FROM membre_tontine WHERE user_id = :user_id AND est_actif = 1";
$stmt = $db->prepare($query);
$stmt->execute(['user_id' => $user_id]);

echo "<h3>1. Membre dans membre_tontine:</h3>";
if($stmt->rowCount() > 0) {
    while($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        echo " Trouvé: ID=" . $row['id'] . ", Tontine ID=" . $row['tontine_id'] . ", Ordre=" . $row['ordre_tour'] . "<br>";
        
        // 2. Vérifier les cotisations pour ce membre_tontine_id
        $query2 = "SELECT * FROM cotisations WHERE membre_tontine_id = :mt_id";
        $stmt2 = $db->prepare($query2);
        $stmt2->execute(['mt_id' => $row['id']]);
        
        echo "<h4>Cotisations pour membre_tontine_id=" . $row['id'] . ":</h4>";
        if($stmt2->rowCount() > 0) {
            $total = 0;
            while($cot = $stmt2->fetch(PDO::FETCH_ASSOC)) {
                echo " - Séance ID=" . $cot['seance_id'] . ", Montant=" . $cot['montant'] . " F, Statut=" . $cot['statut'] . "<br>";
                if($cot['statut'] == 'paye') {
                    $total += $cot['montant'];
                }
            }
            echo "<strong>Total payé: " . $total . " F</strong><br>";
        } else {
            echo " Aucune cotisation trouvée<br>";
        }
        echo "<hr>";
    }
} else {
    echo " Aucun enregistrement dans membre_tontine pour cet utilisateur<br>";
}

// 3. Vérifier la table cotisations directement
echo "<h3>2. Vérification directe dans cotisations:</h3>";
$query3 = "SELECT c.*, mt.user_id FROM cotisations c 
           JOIN membre_tontine mt ON c.membre_tontine_id = mt.id
           WHERE mt.user_id = :user_id";
$stmt3 = $db->prepare($query3);
$stmt3->execute(['user_id' => $user_id]);

if($stmt3->rowCount() > 0) {
    while($row = $stmt3->fetch(PDO::FETCH_ASSOC)) {
        echo " Cotisation ID=" . $row['id'] . ", Montant=" . $row['montant'] . " F, Statut=" . $row['statut'] . "<br>";
    }
} else {
    echo " Aucune cotisation trouvée pour cet utilisateur<br>";
}
?>