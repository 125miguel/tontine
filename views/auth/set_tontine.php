<?php
session_start();

if(!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

if(isset($_GET['id'])) {
    $_SESSION['tontine_active'] = $_GET['id'];
    header("Location: ../dashboard.php");
} else {
    header("Location: choisir_tontine.php");
}
?>