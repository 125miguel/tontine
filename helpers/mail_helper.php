<?php
require_once __DIR__ . '/../vendor/phpmailer/src/PHPMailer.php';
require_once __DIR__ . '/../vendor/phpmailer/src/SMTP.php';
require_once __DIR__ . '/../vendor/phpmailer/src/Exception.php';
require_once __DIR__ . '/../config/mail.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

/**
 * Envoi d'email simple
 */
function envoyerEmail($destinataire, $sujet, $message) {
    $mail = new PHPMailer(true);
    
    try {
        // Configuration du serveur
        $mail->isSMTP();
        $mail->Host       = SMTP_HOST;
        $mail->SMTPAuth   = true;
        $mail->Username   = SMTP_USER;
        $mail->Password   = SMTP_PASS;
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = SMTP_PORT;
        
        // Expéditeur et destinataire
        $mail->setFrom(SMTP_FROM, SMTP_FROM_NAME);
        $mail->addAddress($destinataire);
        
        // Contenu
        $mail->isHTML(true);
        $mail->Subject = $sujet;
        $mail->Body    = $message;
        $mail->AltBody = strip_tags($message);
        
        $mail->send();
        return true;
    } catch (Exception $e) {
        error_log("Erreur envoi email: " . $mail->ErrorInfo);
        return false;
    }
}

/**
 * Envoyer un rappel de réunion personnalisé selon le type de tontine
 */
function envoyerRappelReunion($destinataire, $nom, $email, $tontine, $data) {
    $type = $tontine['type_tontine'] ?? 'djangui';
    $montant = number_format($tontine['montant_cotisation'], 0, ',', ' ');
    $date_reunion = isset($data['date_reunion']) ? date('d/m/Y', strtotime($data['date_reunion'])) : 'À définir';
    
    switch($type) {
        case 'djangui':
            $sujet = " Rappel de réunion - " . $tontine['nom'];
            $body = getRappelDjangui($nom, $tontine, $data);
            break;
        case 'anniversaire':
            $sujet = " Rappel de réunion - " . $tontine['nom'];
            $body = getRappelAnniversaire($nom, $tontine, $data);
            break;
        case 'solidarite':
            $sujet = " Rappel de réunion - " . $tontine['nom'];
            $body = getRappelSolidarite($nom, $tontine, $data);
            break;
        case 'pret':
            $sujet = " Rappel de réunion - " . $tontine['nom'];
            $body = getRappelPret($nom, $tontine, $data);
            break;
        default:
            $sujet = " Rappel de réunion - " . $tontine['nom'];
            $body = getRappelGenerique($nom, $tontine, $data);
    }
    
    return envoyerEmail($email, $sujet, $body);
}

/**
 * Rappel Djangui
 */
function getRappelDjangui($nom, $tontine, $data) {
    $beneficiaire = $data['prochain_beneficiaire'] ?? 'Non défini';
    $montant = number_format($tontine['montant_cotisation'], 0, ',', ' ');
    $date_reunion = isset($data['date_reunion']) ? date('d/m/Y', strtotime($data['date_reunion'])) : 'À définir';
    
    return "
    <div style='font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto;'>
        <div style='background: #1E3A8A; padding: 20px; text-align: center; color: white; border-radius: 10px 10px 0 0;'>
            <h2 style='margin: 0;'>🏦 TONTONTINE</h2>
            <p style='margin: 5px 0 0;'>" . htmlspecialchars($tontine['nom']) . "</p>
        </div>
        <div style='background: white; padding: 25px; border: 1px solid #E2E8F0; border-radius: 0 0 10px 10px;'>
            <h3 style='color: #1E3A8A;'>Bonjour " . htmlspecialchars($nom) . ",</h3>
            
            <p>Ce message pour vous rappeler la prochaine réunion de la tontine.</p>
            
            <div style='background: #F8FAFC; padding: 15px; border-radius: 10px; margin: 20px 0;'>
                <p style='margin: 5px 0;'><strong> Date :</strong> " . $date_reunion . "</p>
                <p style='margin: 5px 0;'><strong> Montant :</strong> " . $montant . " FCFA</p>
                <p style='margin: 5px 0;'><strong> Prochain bénéficiaire :</strong> " . htmlspecialchars($beneficiaire) . "</p>
            </div>
            
            <p>Merci de votre participation !</p>
            
            <hr style='border-color: #E2E8F0; margin: 20px 0;'>
            <p style='color: #666; font-size: 12px; text-align: center;'>
                Cet email a été envoyé automatiquement. Merci de ne pas y répondre.
            </p>
        </div>
    </div>";
}

/**
 * Rappel Anniversaire
 */
function getRappelAnniversaire($nom, $tontine, $data) {
    $prochain_anniversaire = $data['prochain_anniversaire'] ?? 'Non défini';
    $montant = number_format($tontine['montant_cotisation'], 0, ',', ' ');
    $date_reunion = isset($data['date_reunion']) ? date('d/m/Y', strtotime($data['date_reunion'])) : 'À définir';
    $type_cotisation = $tontine['type_cotisation'] ?? 'fixe';
    
    $montant_texte = ($type_cotisation == 'libre') ? "Libre (chacun donne ce qu'il veut)" : $montant . " FCFA";
    
    return "
    <div style='font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto;'>
        <div style='background: #F59E0B; padding: 20px; text-align: center; color: white; border-radius: 10px 10px 0 0;'>
            <h2 style='margin: 0;'>🎂 TONTONTINE ANNIVERSAIRE</h2>
            <p style='margin: 5px 0 0;'>" . htmlspecialchars($tontine['nom']) . "</p>
        </div>
        <div style='background: white; padding: 25px; border: 1px solid #E2E8F0; border-radius: 0 0 10px 10px;'>
            <h3 style='color: #F59E0B;'>Bonjour " . htmlspecialchars($nom) . ",</h3>
            
            <p>🎉 La prochaine réunion de la tontine anniversaire approche !</p>
            
            <div style='background: #FEF3C7; padding: 15px; border-radius: 10px; margin: 20px 0;'>
                <p style='margin: 5px 0;'><strong> Date :</strong> " . $date_reunion . "</p>
                <p style='margin: 5px 0;'><strong> Cotisation :</strong> " . $montant_texte . "</p>
                <p style='margin: 5px 0;'><strong> Prochain anniversaire :</strong> " . htmlspecialchars($prochain_anniversaire) . "</p>
            </div>
            
            <p>N'oubliez pas de préparer votre cotisation !</p>
            
            <hr style='border-color: #E2E8F0; margin: 20px 0;'>
            <p style='color: #666; font-size: 12px; text-align: center;'>
                Cet email a été envoyé automatiquement.
            </p>
        </div>
    </div>";
}

/**
 * Rappel Solidarité
 */
function getRappelSolidarite($nom, $tontine, $data) {
    $solde = number_format($tontine['solde_caisse'] ?? 0, 0, ',', ' ');
    $montant = number_format($tontine['montant_cotisation'], 0, ',', ' ');
    $date_reunion = isset($data['date_reunion']) ? date('d/m/Y', strtotime($data['date_reunion'])) : 'À définir';
    
    return "
    <div style='font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto;'>
        <div style='background: #10B981; padding: 20px; text-align: center; color: white; border-radius: 10px 10px 0 0;'>
            <h2 style='margin: 0;'>🛡️ TONTONTINE SOLIDARITÉ</h2>
            <p style='margin: 5px 0 0;'>" . htmlspecialchars($tontine['nom']) . "</p>
        </div>
        <div style='background: white; padding: 25px; border: 1px solid #E2E8F0; border-radius: 0 0 10px 10px;'>
            <h3 style='color: #10B981;'>Bonjour " . htmlspecialchars($nom) . ",</h3>
            
            <p>🤝 La prochaine réunion de la caisse de solidarité approche !</p>
            
            <div style='background: #D1FAE5; padding: 15px; border-radius: 10px; margin: 20px 0;'>
                <p style='margin: 5px 0;'><strong> Date :</strong> " . $date_reunion . "</p>
                <p style='margin: 5px 0;'><strong> Cotisation :</strong> " . $montant . " FCFA</p>
                <p style='margin: 5px 0;'><strong> Solde de la caisse :</strong> " . $solde . " FCFA</p>
            </div>
            
            <p>Ensemble, soutenons les membres dans le besoin !</p>
            
            <hr style='border-color: #E2E8F0; margin: 20px 0;'>
            <p style='color: #666; font-size: 12px; text-align: center;'>
                Cet email a été envoyé automatiquement.
            </p>
        </div>
    </div>";
}

/**
 * Rappel Prêt
 */
function getRappelPret($nom, $tontine, $data) {
    $solde = number_format($tontine['solde_caisse'] ?? 0, 0, ',', ' ');
    $montant = number_format($tontine['montant_cotisation'], 0, ',', ' ');
    $date_reunion = isset($data['date_reunion']) ? date('d/m/Y', strtotime($data['date_reunion'])) : 'À définir';
    $echeances = $data['echeances'] ?? [];
    
    $echeances_html = '';
    if(!empty($echeances)) {
        $echeances_html = '<div style="margin-top: 10px;"><strong>📋 Échéances à venir :</strong><ul style="margin: 5px 0 0 20px;">';
        foreach($echeances as $e) {
            $echeances_html .= '<li>' . date('d/m/Y', strtotime($e['date_echeance'])) . ' - ' . number_format($e['montant_du'], 0, ',', ' ') . ' F</li>';
        }
        $echeances_html .= '</ul></div>';
    }
    
    return "
    <div style='font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto;'>
        <div style='background: #3B82F6; padding: 20px; text-align: center; color: white; border-radius: 10px 10px 0 0;'>
            <h2 style='margin: 0;'>💰 TONTONTINE PRÊT</h2>
            <p style='margin: 5px 0 0;'>" . htmlspecialchars($tontine['nom']) . "</p>
        </div>
        <div style='background: white; padding: 25px; border: 1px solid #E2E8F0; border-radius: 0 0 10px 10px;'>
            <h3 style='color: #3B82F6;'>Bonjour " . htmlspecialchars($nom) . ",</h3>
            
            <p>📊 La prochaine réunion de la tontine prêt approche !</p>
            
            <div style='background: #DBEAFE; padding: 15px; border-radius: 10px; margin: 20px 0;'>
                <p style='margin: 5px 0;'><strong> Date :</strong> " . $date_reunion . "</p>
                <p style='margin: 5px 0;'><strong> Cotisation :</strong> " . $montant . " FCFA</p>
                <p style='margin: 5px 0;'><strong> Fonds disponibles :</strong> " . $solde . " FCFA</p>
                " . $echeances_html . "
            </div>
            
            <p>Pensez à vos remboursements !</p>
            
            <hr style='border-color: #E2E8F0; margin: 20px 0;'>
            <p style='color: #666; font-size: 12px; text-align: center;'>
                Cet email a été envoyé automatiquement.
            </p>
        </div>
    </div>";
}

/**
 * Rappel générique (fallback)
 */
function getRappelGenerique($nom, $tontine, $data) {
    $montant = number_format($tontine['montant_cotisation'], 0, ',', ' ');
    $date_reunion = isset($data['date_reunion']) ? date('d/m/Y', strtotime($data['date_reunion'])) : 'À définir';
    
    return "
    <div style='font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto;'>
        <div style='background: #1E3A8A; padding: 20px; text-align: center; color: white; border-radius: 10px 10px 0 0;'>
            <h2 style='margin: 0;'>🏦 TONTONTINE</h2>
            <p style='margin: 5px 0 0;'>" . htmlspecialchars($tontine['nom']) . "</p>
        </div>
        <div style='background: white; padding: 25px; border: 1px solid #E2E8F0; border-radius: 0 0 10px 10px;'>
            <h3 style='color: #1E3A8A;'>Bonjour " . htmlspecialchars($nom) . ",</h3>
            
            <p>Ce message pour vous rappeler la prochaine réunion de la tontine.</p>
            
            <div style='background: #F8FAFC; padding: 15px; border-radius: 10px; margin: 20px 0;'>
                <p style='margin: 5px 0;'><strong> Date :</strong> " . $date_reunion . "</p>
                <p style='margin: 5px 0;'><strong> Montant :</strong> " . $montant . " FCFA</p>
            </div>
            
            <p>Merci de votre participation !</p>
            
            <hr style='border-color: #E2E8F0; margin: 20px 0;'>
            <p style='color: #666; font-size: 12px; text-align: center;'>
                Cet email a été envoyé automatiquement.
            </p>
        </div>
    </div>";
}

/**
 * Envoyer un rappel d'impayé
 */
function envoyerRappelImpaye($destinataire, $nom, $email, $tontine, $data) {
    $montant = number_format($data['montant'] ?? 0, 0, ',', ' ');
    $date_limite = isset($data['date_limite']) ? date('d/m/Y', strtotime($data['date_limite'])) : 'Dans les plus brefs délais';
    $sujet = "⚠️ Rappel d'impayé - " . $tontine['nom'];
    
    $body = "
    <div style='font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto;'>
        <div style='background: #EF4444; padding: 20px; text-align: center; color: white; border-radius: 10px 10px 0 0;'>
            <h2 style='margin: 0;'>⚠️ TONTONTINE</h2>
            <p style='margin: 5px 0 0;'>Rappel d'impayé</p>
        </div>
        <div style='background: white; padding: 25px; border: 1px solid #E2E8F0; border-radius: 0 0 10px 10px;'>
            <h3 style='color: #EF4444;'>Bonjour " . htmlspecialchars($nom) . ",</h3>
            
            <p>Nous vous rappelons que vous avez un impayé dans la tontine <strong>" . htmlspecialchars($tontine['nom']) . "</strong>.</p>
            
            <div style='background: #FEE2E2; padding: 15px; border-radius: 10px; margin: 20px 0;'>
                <p style='margin: 5px 0;'><strong> Montant dû :</strong> " . $montant . " FCFA</p>
                <p style='margin: 5px 0;'><strong> Date limite :</strong> " . $date_limite . "</p>
            </div>
            
            <p>Merci de régulariser votre situation dans les meilleurs délais.</p>
            
            <hr style='border-color: #E2E8F0; margin: 20px 0;'>
            <p style='color: #666; font-size: 12px; text-align: center;'>
                Cet email a été envoyé automatiquement.
            </p>
        </div>
    </div>";
    
    return envoyerEmail($email, $sujet, $body);
}

/**
 * Envoyer une confirmation (séance, aide, prêt)
 */
function envoyerConfirmation($destinataire, $nom, $email, $tontine, $type, $data) {
    switch($type) {
        case 'seance':
            $sujet = "✅ Confirmation séance - " . $tontine['nom'];
            $message = "La séance du " . date('d/m/Y', strtotime($data['date_seance'])) . " a été clôturée. Bénéficiaire : " . $data['beneficiaire'];
            break;
        case 'aide':
            $sujet = "🛡️ Aide accordée - " . $tontine['nom'];
            $message = "Une aide de " . number_format($data['montant'], 0, ',', ' ') . " FCFA vous a été accordée.";
            break;
        case 'pret':
            $sujet = "💰 Prêt accordé - " . $tontine['nom'];
            $message = "Votre prêt de " . number_format($data['montant'], 0, ',', ' ') . " FCFA a été approuvé.";
            break;
        default:
            $sujet = "✅ Confirmation - " . $tontine['nom'];
            $message = "Opération effectuée avec succès.";
    }
    
    $body = "
    <div style='font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto;'>
        <div style='background: #10B981; padding: 20px; text-align: center; color: white; border-radius: 10px 10px 0 0;'>
            <h2 style='margin: 0;'>✅ TONTONTINE</h2>
        </div>
        <div style='background: white; padding: 25px; border: 1px solid #E2E8F0; border-radius: 0 0 10px 10px;'>
            <h3>Bonjour " . htmlspecialchars($nom) . ",</h3>
            <p>" . $message . "</p>
            <p>Cordialement,<br>L'équipe TONTONTINE</p>
        </div>
    </div>";
    
    return envoyerEmail($email, $sujet, $body);
}
?>