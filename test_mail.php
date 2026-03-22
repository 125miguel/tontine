<?php
// Fichier: test_mail.php
// Test d'envoi d'emails personnalisés

error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/models/Tontine.php';
require_once __DIR__ . '/helpers/mail_helper.php';

// REMPLACEZ PAR L'ID DE VOTRE TONTINE
$tontine_id = 15; // Mettez l'ID de votre tontine

$database = new Database();
$db = $database->getConnection();

$query = "SELECT * FROM tontines WHERE id = :id";
$stmt = $db->prepare($query);
$stmt->execute(['id' => $tontine_id]);
$tontine = $stmt->fetch(PDO::FETCH_ASSOC);

if(!$tontine) {
    die("Tontine non trouvée");
}

echo "<h1>Test d'envoi d'email - Type: " . $tontine['type_tontine'] . "</h1>";

// Données pour l'email
$data = [
    'date_reunion' => date('Y-m-d', strtotime('+2 days')),
    'prochain_beneficiaire' => 'Jean Dupont',
    'prochain_anniversaire' => 'Marie Martin',
    'echeances' => [
        ['date_echeance' => date('Y-m-d', strtotime('+15 days')), 'montant_du' => 10000],
        ['date_echeance' => date('Y-m-d', strtotime('+45 days')), 'montant_du' => 10000],
        ['date_echeance' => date('Y-m-d', strtotime('+75 days')), 'montant_du' => 10000]
    ]
];

// ============================================
// REMPLACEZ CETTE LIGNE PAR VOTRE EMAIL
// ============================================
$destinataire = "miguelhouha@gmail.com"; // <-- METTEZ VOTRE EMAIL ICI
$nom = "Test User";

echo "<p>Envoi à : <strong>" . $destinataire . "</strong></p>";

// Envoyer l'email
$result = envoyerRappelReunion($nom, $nom, $destinataire, $tontine, $data);

if($result) {
    echo "<p style='color:green'>✅ Email envoyé avec succès à " . $destinataire . "</p>";
    echo "<p>Vérifiez votre boîte mail (et les spams).</p>";
} else {
    echo "<p style='color:red'>❌ Erreur lors de l'envoi de l'email</p>";
}

// Afficher un aperçu du message
echo "<h2>Aperçu du message :</h2>";
echo "<div style='border:1px solid #ccc; padding:20px; margin-top:20px; background:white;'>";

// Afficher le bon template selon le type
switch($tontine['type_tontine']) {
    case 'djangui':
        echo getRappelDjangui($nom, $tontine, $data);
        break;
    case 'anniversaire':
        echo getRappelAnniversaire($nom, $tontine, $data);
        break;
    case 'solidarite':
        echo getRappelSolidarite($nom, $tontine, $data);
        break;
    case 'pret':
        echo getRappelPret($nom, $tontine, $data);
        break;
    default:
        echo getRappelGenerique($nom, $tontine, $data);
}

echo "</div>";
?>