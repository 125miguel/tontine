<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/config/mail.php';
require_once __DIR__ . '/helpers/mail_helper.php';

$email = 'ton.email@gmail.com'; // Remplace par ton email
$sujet = 'Test mail_helper';
$message = '<h1>Test</h1><p>Ceci est un test.</p>';

if(envoyerEmail($email, $sujet, $message)) {
    echo "✅ Email envoyé avec succès !";
} else {
    echo "❌ Échec de l'envoi";
}
?>