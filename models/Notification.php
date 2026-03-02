<?php
require_once __DIR__ . '/../helpers/mail_helper.php';

class Notification {
    private $conn;
    private $table = "notifications";

    public function __construct($db) {
        $this->conn = $db;
    }

    /**
     * Envoyer une notification par email
     */
    public function envoyerEmail($tontine_id, $destinataire, $sujet, $message) {
        // Envoyer l'email
        $result = envoyerEmail($destinataire, $sujet, $message);
        
        // Déterminer le statut
        $statut = $result ? 'envoye' : 'echec';
        $date = $result ? date('Y-m-d H:i:s') : null;
        
        // Enregistrer dans la base
        $query = "INSERT INTO " . $this->table . " 
                  (tontine_id, type, destinataire, sujet, message, statut, date_envoi)
                  VALUES (:tontine_id, 'email', :destinataire, :sujet, :message, :statut, :date_envoi)";
        
        $stmt = $this->conn->prepare($query);
        return $stmt->execute([
            'tontine_id' => $tontine_id,
            'destinataire' => $destinataire,
            'sujet' => $sujet,
            'message' => $message,
            'statut' => $statut,
            'date_envoi' => $date
        ]);
    }

    /**
     * Envoyer un rappel de réunion à tous les membres
     */
    public function rappelReunion($tontine_id, $date_reunion) {
        // Récupérer les infos de la tontine
        $tontine = new Tontine($this->conn);
        $tontine->getById($tontine_id);
        
        // Récupérer tous les membres actifs
        $query = "SELECT u.email, u.prenom, u.nom 
                  FROM membre_tontine mt
                  JOIN users u ON mt.user_id = u.id
                  WHERE mt.tontine_id = :tontine_id AND mt.est_actif = 1";
        
        $stmt = $this->conn->prepare($query);
        $stmt->execute(['tontine_id' => $tontine_id]);
        $membres = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        if(empty($membres)) {
            return 0;
        }
        
        // Sujet et message commun
        $sujet = "Rappel : Réunion " . $tontine->nom;
        $date_formatee = date('d/m/Y', strtotime($date_reunion));
        
        $message_base = "
        <!DOCTYPE html>
        <html>
        <head>
            <style>
                body { font-family: Arial, sans-serif; line-height: 1.6; }
                .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                .header { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 20px; text-align: center; border-radius: 10px 10px 0 0; }
                .content { background: #f9f9f9; padding: 30px; border-radius: 0 0 10px 10px; }
                .button { display: inline-block; padding: 10px 20px; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; text-decoration: none; border-radius: 5px; }
                .footer { text-align: center; margin-top: 20px; color: #999; font-size: 12px; }
                .details { background: white; padding: 15px; border-radius: 5px; margin: 20px 0; }
            </style>
        </head>
        <body>
            <div class='container'>
                <div class='header'>
                    <h2>Rappel de réunion</h2>
                </div>
                <div class='content'>
                    <p>Bonjour <strong>{PRENOM}</strong>,</p>
                    <p>Ceci est un rappel pour la prochaine réunion de la tontine <strong>{TONTINE}</strong>.</p>
                    
                    <div class='details'>
                        <p><strong>Date :</strong> {DATE}</p>
                        <p><strong>Montant :</strong> {MONTANT} FCFA</p>
                        <p><strong>Lieu :</strong> À confirmer</p>
                    </div>
                    
                    <p>Merci de prévoir votre cotisation.</p>
                    
                    <p>Cordialement,<br>Votre président</p>
                </div>
                <div class='footer'>
                    <p>© 2025 Tontine App. Tous droits réservés.</p>
                </div>
            </div>
        </body>
        </html>
        ";
        
        $compteur = 0;
        
        foreach($membres as $membre) {
            // Personnaliser le message
            $message = str_replace(
                ['{PRENOM}', '{TONTINE}', '{DATE}', '{MONTANT}'],
                [$membre['prenom'], $tontine->nom, $date_formatee, number_format($tontine->montant_cotisation, 0, ',', ' ')],
                $message_base
            );
            
            if($this->envoyerEmail($tontine_id, $membre['email'], $sujet, $message)) {
                $compteur++;
            }
        }
        
        return $compteur;
    }

    /**
     * Envoyer un rappel d'impayé à un membre spécifique
     */
    public function rappelImpaye($tontine_id, $membre_id, $montant, $date_seance) {
        // Récupérer les infos du membre
        $query = "SELECT u.email, u.prenom, u.nom, t.nom as tontine_nom
                  FROM membre_tontine mt
                  JOIN users u ON mt.user_id = u.id
                  JOIN tontines t ON mt.tontine_id = t.id
                  WHERE mt.id = :membre_id";
        
        $stmt = $this->conn->prepare($query);
        $stmt->execute(['membre_id' => $membre_id]);
        $membre = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if(!$membre) {
            return false;
        }
        
        $sujet = "Rappel : Cotisation impayée - " . $membre['tontine_nom'];
        
        $message = "
        <!DOCTYPE html>
        <html>
        <head>
            <style>
                body { font-family: Arial, sans-serif; line-height: 1.6; }
                .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                .header { background: linear-gradient(135deg, #ff6b6b 0%, #c44545 100%); color: white; padding: 20px; text-align: center; border-radius: 10px 10px 0 0; }
                .content { background: #f9f9f9; padding: 30px; border-radius: 0 0 10px 10px; }
                .amount { font-size: 24px; font-weight: bold; color: #c44545; text-align: center; padding: 20px; background: white; border-radius: 5px; margin: 20px 0; }
                .button { display: inline-block; padding: 10px 20px; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; text-decoration: none; border-radius: 5px; }
                .footer { text-align: center; margin-top: 20px; color: #999; font-size: 12px; }
            </style>
        </head>
        <body>
            <div class='container'>
                <div class='header'>
                    <h2>Rappel de cotisation impayée</h2>
                </div>
                <div class='content'>
                    <p>Bonjour <strong>{$membre['prenom']}</strong>,</p>
                    <p>Notre système indique que vous avez une cotisation impayée pour la tontine <strong>{$membre['tontine_nom']}</strong>.</p>
                    
                    <div class='amount'>
                        Montant dû : " . number_format($montant, 0, ',', ' ') . " FCFA
                    </div>
                    
                    <p><strong>Date de la séance :</strong> " . date('d/m/Y', strtotime($date_seance)) . "</p>
                    
                    <p>Merci de régulariser votre situation dès que possible.</p>
                    
                    <p>Cordialement,<br>Votre président</p>
                </div>
                <div class='footer'>
                    <p>© 2025 Tontine App. Tous droits réservés.</p>
                </div>
            </div>
        </body>
        </html>
        ";
        
        return $this->envoyerEmail($tontine_id, $membre['email'], $sujet, $message);
    }

    /**
     * Historique des notifications envoyées
     */
    public function getHistorique($tontine_id, $limit = 20) {
        $query = "SELECT * FROM " . $this->table . " 
                  WHERE tontine_id = :tontine_id 
                  ORDER BY created_at DESC 
                  LIMIT :limit";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':tontine_id', $tontine_id);
        $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
?>