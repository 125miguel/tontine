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

$membre_tontine_id = $_GET['id'] ?? 0;
$tontine_id = $_GET['tontine_id'] ?? 0;

if(!$membre_tontine_id || !$tontine_id) {
    header("Location: mes_tontines.php");
    exit();
}

// Vérifier que la tontine appartient à cet admin
$tontine = new Tontine($db);
if(!$tontine->getById($tontine_id) || $tontine->admin_id != $_SESSION['user_id']) {
    header("Location: mes_tontines.php");
    exit();
}

// Récupérer l'ID de l'utilisateur et l'association
$query = "SELECT user_id, association_id FROM membre_tontine WHERE id = :mid";
$stmt = $db->prepare($query);
$stmt->execute(['mid' => $membre_tontine_id]);
$membre = $stmt->fetch(PDO::FETCH_ASSOC);

if(!$membre) {
    header("Location: voir_membres.php?id=" . $tontine_id . "&error=1");
    exit();
}

// Générer un nouveau mot de passe
$temp_password = genererMotDePasse(6);
$hashed = password_hash($temp_password, PASSWORD_DEFAULT);

// Mettre à jour le mot de passe dans membres_association
$query = "UPDATE membres_association 
          SET password = :password, premiere_connexion = 1
          WHERE user_id = :user_id AND association_id = :association_id";
$stmt = $db->prepare($query);
$stmt->execute([
    'password' => $hashed,
    'user_id' => $membre['user_id'],
    'association_id' => $membre['association_id']
]);

// Stocker le mot de passe en session pour affichage
$_SESSION['reset_password'] = $temp_password;

// Récupérer les infos du membre pour affichage
$query = "SELECT prenom, nom FROM users WHERE id = :uid";
$stmt = $db->prepare($query);
$stmt->execute(['uid' => $membre['user_id']]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);
$_SESSION['reset_user'] = $user['prenom'] . ' ' . $user['nom'];

header("Location: voir_membres.php?id=" . $tontine_id . "&reset=1");
exit();
?>