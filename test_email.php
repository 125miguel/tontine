<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/config/mail.php';
require_once __DIR__ . '/vendor/phpmailer/src/PHPMailer.php';
require_once __DIR__ . '/vendor/phpmailer/src/SMTP.php';
require_once __DIR__ . '/vendor/phpmailer/src/Exception.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

echo "<h2>Test d'envoi d'email</h2>";

$mail = new PHPMailer(true);

try {
    echo "1. Configuration SMTP...<br>";
    $mail->isSMTP();
    $mail->Host       = SMTP_HOST;
    $mail->SMTPAuth   = true;
    $mail->Username   = SMTP_USER;
    $mail->Password   = SMTP_PASS;
    $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
    $mail->Port       = SMTP_PORT;
    
    echo "2. Configuration expéditeur...<br>";
    $mail->setFrom(SMTP_FROM, SMTP_FROM_NAME);
    $mail->addAddress(SMTP_USER); // Envoie à toi-même pour test
    
    echo "3. Préparation du contenu...<br>";
    $mail->isHTML(true);
    $mail->Subject = 'Test TONTONTINE';
    $mail->Body    = '<h1>Test réussi !</h1><p>La configuration email fonctionne parfaitement.</p>';
    
    echo "4. Envoi en cours...<br>";
    $mail->send();
    
    echo "<p style='color: green; font-weight: bold;'>✅ Email envoyé avec succès !</p>";
    
} catch (Exception $e) {
    echo "<p style='color: red; font-weight: bold;'>❌ Erreur : " . $mail->ErrorInfo . "</p>";
}
?>