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
        require_once __DIR__ . '/Tontine.php';
        $tontine = new Tontine($this->conn);
        $tontine->getById($tontine_id);
        
        // Récupérer le nom de l'association
        $query = "SELECT nom FROM associations WHERE id = :aid";
        $stmt = $this->conn->prepare($query);
        $stmt->execute(['aid' => $tontine->association_id]);
        $assoc = $stmt->fetch(PDO::FETCH_ASSOC);
        $association_nom = $assoc['nom'] ?? 'Association';
        
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
        $sujet = " Rappel : Réunion " . $tontine->nom . " - " . $association_nom;
        $date_formatee = date('d/m/Y', strtotime($date_reunion));
        
        $message_base = "
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
                .details {
                    background: linear-gradient(135deg, #f5f0ff 0%, #fff5f0 100%);
                    padding: 25px;
                    border-radius: 10px;
                    margin: 25px 0;
                    border-left: 4px solid #FF8A4C;
                }
                .details p {
                    margin: 10px 0;
                    font-size: 16px;
                }
                .details strong {
                    color: #6B46C1;
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
                    <p>Rappel de réunion</p>
                </div>
                <div class='content'>
                    <p>Bonjour <strong>{PRENOM}</strong>,</p>
                    <p>Ceci est un rappel pour la prochaine réunion de la tontine <strong>{TONTINE}</strong> 
                    de l'association <span class='association-name'>{ASSOCIATION}</span>.</p>
                    
                    <div class='details'>
                        <p><strong> Date :</strong> {DATE}</p>
                        <p><strong> Montant :</strong> {MONTANT} FCFA</p>
                        <p><strong> Lieu :</strong> À confirmer</p>
                    </div>
                    
                    <p>Merci de prévoir votre cotisation.</p>
                    
                    <p>Cordialement,<br>Votre président</p>
                </div>
                <div class='footer'>
                    <p>© 2025 TONTONTINE. Tous droits réservés.</p>
                    <p style='margin:5px 0 0;'>Application de gestion de tontines</p>
                </div>
            </div>
        </body>
        </html>
        ";
        
        $compteur = 0;
        
        foreach($membres as $membre) {
            // Personnaliser le message
            $message = str_replace(
                ['{PRENOM}', '{TONTINE}', '{ASSOCIATION}', '{DATE}', '{MONTANT}'],
                [
                    $membre['prenom'],
                    $tontine->nom,
                    $association_nom,
                    $date_formatee,
                    number_format($tontine->montant_cotisation, 0, ',', ' ')
                ],
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
        $query = "SELECT u.email, u.prenom, u.nom, t.nom as tontine_nom, t.association_id
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
        
        // Récupérer le nom de l'association
        $query = "SELECT nom FROM associations WHERE id = :aid";
        $stmt = $this->conn->prepare($query);
        $stmt->execute(['aid' => $membre['association_id']]);
        $assoc = $stmt->fetch(PDO::FETCH_ASSOC);
        $association_nom = $assoc['nom'] ?? 'Association';
        
        $sujet = " Rappel : Cotisation impayée - " . $membre['tontine_nom'];
        
        $message = "
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
                    background: linear-gradient(135deg, #dc3545 0%, #c82333 100%);
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
                .amount-box {
                    background: linear-gradient(135deg, #f5f0ff 0%, #fff5f0 100%);
                    padding: 25px;
                    border-radius: 10px;
                    margin: 25px 0;
                    text-align: center;
                    border-left: 4px solid #dc3545;
                }
                .amount {
                    font-size: 36px;
                    font-weight: 800;
                    color: #dc3545;
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
                    <p>Rappel de cotisation impayée</p>
                </div>
                <div class='content'>
                    <p>Bonjour <strong>{$membre['prenom']}</strong>,</p>
                    <p>Notre système indique que vous avez une cotisation impayée pour la tontine <strong>{$membre['tontine_nom']}</strong> 
                    de l'association <span class='association-name'>{$association_nom}</span>.</p>
                    
                    <div class='amount-box'>
                        <p><strong>Montant dû :</strong></p>
                        <div class='amount'>" . number_format($montant, 0, ',', ' ') . " FCFA</div>
                    </div>
                    
                    <p><strong> Date de la séance :</strong> " . date('d/m/Y', strtotime($date_seance)) . "</p>
                    
                    <p>Merci de régulariser votre situation dès que possible.</p>
                    
                    <p>Cordialement,<br>Votre président</p>
                </div>
                <div class='footer'>
                    <p>© 2025 TONTONTINE. Tous droits réservés.</p>
                    <p style='margin:5px 0 0;'>Application de gestion de tontines</p>
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