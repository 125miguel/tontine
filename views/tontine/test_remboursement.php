<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../models/Tontine.php';

$database = new Database();
$db = $database->getConnection();

echo "<h1>Test de remboursement</h1>";

// Récupérer toutes les échéances non payées
$query = "SELECT e.*, p.tontine_id, u.prenom, u.nom 
          FROM echeances_prets e
          JOIN prets p ON e.pret_id = p.id
          JOIN membre_tontine mt ON p.membre_id = mt.id
          JOIN users u ON mt.user_id = u.id
          WHERE e.statut = 'en_attente'
          ORDER BY e.id";
$stmt = $db->prepare($query);
$stmt->execute();
$echeances = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "<h2>Échéances en attente</h2>";
if(empty($echeances)) {
    echo "<p>Aucune échéance en attente</p>";
} else {
    echo "<table border='1' cellpadding='5'>";
    echo "<tr><th>ID</th><th>Membre</th><th>Montant</th><th>Date échéance</th><th>Action</th></tr>";
    foreach($echeances as $e) {
        echo "<tr>";
        echo "<td>" . $e['id'] . "</td>";
        echo "<td>" . htmlspecialchars($e['prenom'] . ' ' . $e['nom']) . "</td>";
        echo "<td>" . number_format($e['montant_du'], 0, ',', ' ') . " F</td>";
        echo "<td>" . date('d/m/Y', strtotime($e['date_echeance'])) . "</td>";
        echo "<td><a href='?rembourser=" . $e['id'] . "'>Rembourser</a></td>";
        echo "</tr>";
    }
    echo "</table>";
}

// Traitement du remboursement
if(isset($_GET['rembourser'])) {
    $echeance_id = $_GET['rembourser'];
    echo "<h2>Traitement du remboursement ID: $echeance_id</h2>";
    
    // Récupérer l'échéance
    $queryEch = "SELECT e.*, p.tontine_id 
                 FROM echeances_prets e
                 JOIN prets p ON e.pret_id = p.id
                 WHERE e.id = :id";
    $stmtEch = $db->prepare($queryEch);
    $stmtEch->execute(['id' => $echeance_id]);
    $echeance = $stmtEch->fetch(PDO::FETCH_ASSOC);
    
    if($echeance) {
        echo "Échéance trouvée :<br>";
        echo "<pre>";
        print_r($echeance);
        echo "</pre>";
        
        // Mettre à jour l'échéance
        $query = "UPDATE echeances_prets 
                  SET montant_paye = montant_du, 
                      date_paiement = NOW(), 
                      statut = 'paye'
                  WHERE id = :id";
        $stmt = $db->prepare($query);
        $result = $stmt->execute(['id' => $echeance_id]);
        
        if($result) {
            echo "<p style='color:green'>✅ Remboursement effectué avec succès !</p>";
            
            // Mettre à jour le solde
            $tontine = new Tontine($db);
            $tontine->getById($echeance['tontine_id']);
            $tontine->updateSoldeCaisse($echeance['montant_du'], 'ajout');
            $tontine->enregistrerOperation(
                'remboursement', 
                $echeance['montant_du'], 
                "Remboursement d'échéance n°" . $echeance['numero_echeance']
            );
            echo "<p>Solde mis à jour</p>";
        } else {
            echo "<p style='color:red'>❌ Erreur lors du remboursement</p>";
        }
    } else {
        echo "<p style='color:red'>❌ Échéance non trouvée</p>";
    }
}
?>