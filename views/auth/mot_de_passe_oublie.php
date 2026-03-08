<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../models/User.php';
require_once __DIR__ . '/../../models/PasswordReset.php';
require_once __DIR__ . '/../../config/mail.php';

// Inclusion de PHPMailer
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
use PHPMailer\PHPMailer\SMTP;

require_once __DIR__ . '/../../vendor/phpmailer/src/PHPMailer.php';
require_once __DIR__ . '/../../vendor/phpmailer/src/SMTP.php';
require_once __DIR__ . '/../../vendor/phpmailer/src/Exception.php';

$message = '';
$error = '';
$step = 1; // 1 = saisie email, 2 = choix association
$email = '';
$associations = [];

if($_SERVER['REQUEST_METHOD'] == 'POST') {
    
    if(isset($_POST['check_email'])) {
        // ÉTAPE 1 : Vérifier l'email
        $email = $_POST['email'] ?? '';
        
        if(empty($email)) {
            $error = "Veuillez saisir votre email";
        } else {
            $database = new Database();
            $db = $database->getConnection();
            
            // Vérifier si l'email existe
            $query = "SELECT id FROM users WHERE email = :email";
            $stmt = $db->prepare($query);
            $stmt->execute(['email' => $email]);
            
            if($stmt->rowCount() > 0) {
                $user = $stmt->fetch(PDO::FETCH_ASSOC);
                
                // Récupérer toutes les associations de ce membre
                $query = "SELECT a.id, a.nom as association_nom 
                          FROM associations a
                          JOIN membres_association ma ON a.id = ma.association_id
                          WHERE ma.user_id = :user_id";
                $stmt = $db->prepare($query);
                $stmt->execute(['user_id' => $user['id']]);
                $associations = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                if(count($associations) > 0) {
                    $_SESSION['reset_user_id'] = $user['id'];
                    $_SESSION['reset_email'] = $email;
                    $step = 2;
                } else {
                    $error = "Vous n'êtes membre d'aucune association.";
                }
            } else {
                $error = "Aucun compte trouvé avec cet email";
            }
        }
    }
    
    if(isset($_POST['send_code'])) {
        // ÉTAPE 2 : Envoyer le code pour l'association choisie
        $association_id = $_POST['association_id'] ?? 0;
        $email = $_SESSION['reset_email'] ?? '';
        
        if(empty($email)) {
            $error = "Session expirée. Veuillez recommencer.";
            header("refresh:2;url=mot_de_passe_oublie.php");
        } else if(!$association_id) {
            $error = "Veuillez sélectionner une association";
        } else {
            $database = new Database();
            $db = $database->getConnection();
            
            // Récupérer le nom de l'association
            $query = "SELECT nom FROM associations WHERE id = :id";
            $stmt = $db->prepare($query);
            $stmt->execute(['id' => $association_id]);
            $assoc = $stmt->fetch(PDO::FETCH_ASSOC);
            
            // Créer un code de réinitialisation
            $passwordReset = new PasswordReset($db);
            $code = $passwordReset->createCode($_SESSION['reset_user_id']);
            
            if($code) {
                // Stocker l'association choisie
                $_SESSION['reset_association_id'] = $association_id;
                $_SESSION['reset_association_nom'] = $assoc['nom'];
                
                // Préparer l'email
                $sujet = " Code de réinitialisation - TONTONTINE";
                
                // Message HTML
                $message_html = "
                <!DOCTYPE html>
                <html>
                <head>
                    <meta charset='UTF-8'>
                    <style>
                        body {
                            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
                            background: #f5f5f5;
                            margin: 0;
                            padding: 0;
                        }
                        .container {
                            max-width: 600px;
                            margin: 20px auto;
                            background: white;
                            border-radius: 15px;
                            overflow: hidden;
                            box-shadow: 0 10px 40px rgba(107, 70, 193, 0.2);
                        }
                        .header {
                            background: linear-gradient(135deg, #6B46C1 0%, #FF8A4C 100%);
                            color: white;
                            padding: 40px 30px;
                            text-align: center;
                        }
                        .header h1 {
                            margin: 0;
                            font-size: 28px;
                            font-weight: 700;
                        }
                        .content {
                            padding: 40px 30px;
                            background: white;
                        }
                        .code-box {
                            background: linear-gradient(135deg, #f5f0ff 0%, #fff5f0 100%);
                            border: 2px dashed #6B46C1;
                            border-radius: 15px;
                            padding: 30px;
                            text-align: center;
                            margin: 30px 0;
                        }
                        .code {
                            font-size: 48px;
                            font-weight: 800;
                            letter-spacing: 10px;
                            color: #6B46C1;
                            background: white;
                            padding: 20px 30px;
                            border-radius: 10px;
                            display: inline-block;
                            box-shadow: 0 5px 20px rgba(107, 70, 193, 0.2);
                        }
                        .info {
                            background: #f8f9fa;
                            border-left: 4px solid #FF8A4C;
                            padding: 15px;
                            border-radius: 5px;
                            margin: 20px 0;
                        }
                        .footer {
                            text-align: center;
                            padding: 20px;
                            background: #f8f9fa;
                            color: #666;
                            font-size: 12px;
                        }
                        .association-name {
                            font-weight: 700;
                            color: #FF8A4C;
                        }
                    </style>
                </head>
                <body>
                    <div class='container'>
                        <div class='header'>
                            <h1> TONTONTINE</h1>
                            <p>Réinitialisation de votre mot de passe</p>
                        </div>
                        <div class='content'>
                            <p>Bonjour,</p>
                            <p>Vous avez demandé à réinitialiser votre mot de passe pour l'association 
                            <span class='association-name'>" . htmlspecialchars($assoc['nom']) . "</span>.</p>
                            
                            <div class='code-box'>
                                <div class='code'>$code</div>
                            </div>
                            
                            <div class='info'>
                                <p style='margin:0;'><strong> Ce code est valable 15 minutes.</strong></p>
                                <p style='margin:10px 0 0;'>Si vous n'êtes pas à l'origine de cette demande, ignorez simplement cet email.</p>
                            </div>
                            
                            <p>Une fois connecté, vous pourrez choisir un nouveau mot de passe.</p>
                        </div>
                        <div class='footer'>
                            <p>© 2025 TONTONTINE. Tous droits réservés.</p>
                            <p style='margin:5px 0 0;'>Application de gestion de tontines</p>
                        </div>
                    </div>
                </body>
                </html>
                ";
                
                // Message texte brut
                $message_texte = "Bonjour,\n\n";
                $message_texte .= "Vous avez demandé à réinitialiser votre mot de passe pour l'association " . $assoc['nom'] . ".\n\n";
                $message_texte .= "Votre code de validation est : $code\n\n";
                $message_texte .= "Ce code est valable 15 minutes.\n\n";
                $message_texte .= "Si vous n'êtes pas à l'origine de cette demande, ignorez cet email.\n\n";
                $message_texte .= "Cordialement,\nL'équipe TONTONTINE";
                
                // Envoyer l'email avec PHPMailer
                $mail = new PHPMailer(true);
                
                try {
                    // Configuration du serveur SMTP
                    $mail->isSMTP();
                    $mail->Host       = SMTP_HOST;
                    $mail->SMTPAuth   = true;
                    $mail->Username   = SMTP_USER;
                    $mail->Password   = SMTP_PASS;
                    $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
                    $mail->Port       = SMTP_PORT;
                    $mail->CharSet    = 'UTF-8';
                    
                    // Expéditeur et destinataire
                    $mail->setFrom(SMTP_FROM, SMTP_FROM_NAME);
                    $mail->addAddress($email);
                    
                    // Contenu
                    $mail->isHTML(true);
                    $mail->Subject = $sujet;
                    $mail->Body    = $message_html;
                    $mail->AltBody = $message_texte;
                    
                    $mail->send();
                    
                    // Redirection après succès
                    header("Location: saisir_code.php");
                    exit();
                    
                } catch (Exception $e) {
                    $error = " Erreur lors de l'envoi de l'email : " . $mail->ErrorInfo;
                }
            } else {
                $error = " Erreur lors de la génération du code";
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mot de passe oublié - TONTONTINE</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        body {
            background: linear-gradient(135deg, #6B46C1 0%, #FF8A4C 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            font-family: 'Inter', sans-serif;
            padding: 20px;
        }
        .card {
            border-radius: 20px;
            border: none;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            max-width: 500px;
            width: 100%;
            overflow: hidden;
        }
        .card-header {
            background: linear-gradient(135deg, #6B46C1 0%, #FF8A4C 100%);
            color: white;
            text-align: center;
            padding: 40px 30px;
        }
        .card-header h2 {
            font-weight: 700;
            margin-bottom: 10px;
            font-size: 32px;
        }
        .card-header p {
            margin: 0;
            opacity: 0.9;
            font-size: 16px;
        }
        .card-body {
            padding: 40px;
            background: white;
        }
        .btn-primary {
            background: linear-gradient(135deg, #6B46C1 0%, #FF8A4C 100%);
            border: none;
            padding: 14px;
            width: 100%;
            border-radius: 10px;
            font-weight: 600;
            font-size: 16px;
            transition: all 0.3s;
        }
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 30px rgba(107, 70, 193, 0.4);
        }
        .btn-back {
            background: white;
            color: #6B46C1;
            border: 2px solid #6B46C1;
            border-radius: 10px;
            padding: 12px;
            width: 100%;
            margin-top: 10px;
            font-weight: 600;
            transition: all 0.3s;
        }
        .btn-back:hover {
            background: #6B46C1;
            color: white;
        }
        .form-control, .form-select {
            border-radius: 10px;
            border: 2px solid #e0e0e0;
            padding: 12px 15px;
            font-size: 15px;
            transition: all 0.3s;
        }
        .form-control:focus, .form-select:focus {
            border-color: #6B46C1;
            box-shadow: none;
            outline: none;
        }
        .alert {
            border-radius: 10px;
            padding: 15px;
            margin-bottom: 25px;
            border: none;
        }
        .form-label {
            font-weight: 500;
            color: #333;
            margin-bottom: 8px;
        }
        .text-muted {
            color: #6c757d !important;
        }
        a {
            text-decoration: none;
        }
        a:hover {
            text-decoration: underline;
        }
    </style>
</head>
<body>
    <div class="card">
        <div class="card-header">
            <h2><i class="fas fa-hand-holding-usd me-2"></i>TONTONTINE</h2>
            <p><?= $step == 1 ? 'Mot de passe oublié' : 'Choisissez votre association' ?></p>
        </div>
        <div class="card-body">
            
            <?php if($error): ?>
                <div class="alert alert-danger">
                    <i class="fas fa-exclamation-circle me-2"></i><?= htmlspecialchars($error) ?>
                </div>
            <?php endif; ?>

            <?php if(isset($message) && $message): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle me-2"></i><?= htmlspecialchars($message) ?>
                </div>
            <?php endif; ?>

            <?php if($step == 1): ?>
                <!-- ÉTAPE 1 : Saisie de l'email -->
                <form method="POST">
                    <div class="mb-4">
                        <label class="form-label"> Votre email</label>
                        <input type="email" name="email" class="form-control" 
                               placeholder="exemple@email.com" 
                               value="<?= htmlspecialchars($_POST['email'] ?? '') ?>"
                               required>
                        <small class="text-muted">Nous enverrons un code de validation à cette adresse</small>
                    </div>
                    
                    <button type="submit" name="check_email" class="btn-primary">
                        <i class="fas fa-arrow-right me-2"></i>Vérifier mon email
                    </button>
                    
                    <div class="text-center mt-3">
                        <a href="login.php" class="text-decoration-none" style="color: #6B46C1;">
                            <i class="fas fa-arrow-left me-1"></i>Retour à la connexion
                        </a>
                    </div>
                </form>

            <?php else: ?>
                <!-- ÉTAPE 2 : Choix de l'association -->
                <form method="POST">
                    <div class="mb-4">
                        <label class="form-label"> Association</label>
                        <select name="association_id" class="form-select" required>
                            <option value="">Choisissez votre association</option>
                            <?php foreach($associations as $assoc): ?>
                                <option value="<?= $assoc['id'] ?>"><?= htmlspecialchars($assoc['association_nom']) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <small class="text-muted">Pour quelle association souhaitez-vous réinitialiser le mot de passe ?</small>
                    </div>
                    
                    <button type="submit" name="send_code" class="btn-primary">
                        <i class="fas fa-envelope me-2"></i>Envoyer le code
                    </button>
                    
                    <button type="button" onclick="window.location.href='mot_de_passe_oublie.php'" class="btn-back">
                        <i class="fas fa-arrow-left me-2"></i>Changer d'email
                    </button>
                </form>
            <?php endif; ?>

            <div class="text-center mt-3">
                <a href="register.php" class="text-muted small">
                    <i class="fas fa-user-plus me-1"></i>Pas encore de compte ? Inscrivez-vous
                </a>
            </div>
        </div>
    </div>
</body>
</html>