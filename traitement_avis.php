<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();
require_once 'config/database.php';

if($_SERVER['REQUEST_METHOD'] == 'POST') {
    
    $nom = trim($_POST['nom'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $role = trim($_POST['role'] ?? '');
    $note = (int)($_POST['note'] ?? 5);
    $message = trim($_POST['message'] ?? '');
    
    // Validation simple
    $errors = [];
    
    if(empty($nom)) $errors[] = "Le nom est obligatoire";
    if(empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = "Email invalide";
    if(empty($role)) $errors[] = "Le rôle est obligatoire";
    if($note < 1 || $note > 5) $errors[] = "Note invalide";
    if(empty($message)) $errors[] = "Le message est obligatoire";
    
    if(empty($errors)) {
        $database = new Database();
        $db = $database->getConnection();
        
        $query = "INSERT INTO avis (nom, email, role, note, message) 
                  VALUES (:nom, :email, :role, :note, :message)";
        
        $stmt = $db->prepare($query);
        
        if($stmt->execute([
            'nom' => $nom,
            'email' => $email,
            'role' => $role,
            'note' => $note,
            'message' => $message
        ])) {
            $_SESSION['avis_success'] = "Merci pour votre avis ! Il sera publié après modération.";
        } else {
            $_SESSION['avis_error'] = "Erreur lors de l'envoi de l'avis. Veuillez réessayer.";
        }
    } else {
        $_SESSION['avis_error'] = implode("<br>", $errors);
    }
    
    header("Location: index.php#avis");
    exit();
} else {
    header("Location: index.php");
    exit();
}
?>