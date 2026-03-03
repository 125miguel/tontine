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
require_once __DIR__ . '/../../models/MembreTontine.php';

/**
 * Générer un mot de passe aléatoire par défaut
 */
function genererMotDePasse($longueur = 8) {
    $caracteres = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
    $mot_de_passe = '';
    for ($i = 0; $i < $longueur; $i++) {
        $mot_de_passe .= $caracteres[rand(0, strlen($caracteres) - 1)];
    }
    return $mot_de_passe;
}

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

// Récupérer l'email du membre
$query = "SELECT u.email, u.prenom, u.nom FROM membre_tontine mt
          JOIN users u ON mt.user_id = u.id
          WHERE mt.id = :mid";
$stmt = $db->prepare($query);
$stmt->execute(['mid' => $membre_id]);
$membre = $stmt->fetch(PDO::FETCH_ASSOC);

if($membre) {
    // Générer un nouveau mot de passe
    $temp_password = genererMotDePasse(6);
    $hashed = password_hash($temp_password, PASSWORD_DEFAULT);
    
    // Mettre à jour le mot de passe
    $query = "UPDATE users SET password = :password, premiere_connexion = 1 
              WHERE email = :email";
    $stmt = $db->prepare($query);
    $stmt->execute([
        'password' => $hashed,
        'email' => $membre['email']
    ]);
    
    // Stocker en session pour affichage
    $_SESSION['reset_password'] = $temp_password;
    $_SESSION['reset_user'] = $membre['prenom'] . ' ' . $membre['nom'];
}

header("Location: voir_membres.php?id=" . $tontine_id . "&reset=1");
exit();
?>