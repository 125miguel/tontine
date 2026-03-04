<?php
session_start();

// Si l'utilisateur n'est pas connecté, on le redirige vers login
if(!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// Sinon, on le redirige vers le dashboard
header("Location: ../dashboard.php");
exit();
?>