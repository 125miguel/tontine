<?php
// Fichier: models/MembreTontine.php
// But: Gérer les membres d'une tontine

class MembreTontine {
    private $conn;
    private $table = "membre_tontine";
    
    // Propriétés
    public $id;
    public $user_id;
    public $tontine_id;
    public $ordre_tour;
    public $date_adhesion;
    public $est_actif;
    public $association_id;  

    public function __construct($db) {
        $this->conn = $db;
    }

    /**
     * Ajouter un membre à une tontine
     */
   /**
 * Ajouter un membre à une tontine
 */
public function ajouterMembre() {
    // Vérifier si le membre est déjà dans la tontine
    if($this->estDejaMembre()) {
        return false;
    }

    $query = "INSERT INTO " . $this->table . "
              (user_id, tontine_id, association_id, ordre_tour)
              VALUES (:user_id, :tontine_id, :association_id, :ordre_tour)";
    
    $stmt = $this->conn->prepare($query);
    
    $stmt->bindParam(":user_id", $this->user_id);
    $stmt->bindParam(":tontine_id", $this->tontine_id);
    $stmt->bindParam(":association_id", $this->association_id);
    $stmt->bindParam(":ordre_tour", $this->ordre_tour);
    
    if($stmt->execute()) {
        return true;
    }
    return false;
}

    /**
    * Vérifier si un membre est déjà dans la tontine
    */
    public function estDejaMembre() {
        $query = "SELECT id FROM " . $this->table . " 
                WHERE user_id = :user_id AND tontine_id = :tontine_id AND association_id = :association_id";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":user_id", $this->user_id);
        $stmt->bindParam(":tontine_id", $this->tontine_id);
        $stmt->bindParam(":association_id", $this->association_id);
        $stmt->execute();
        
        return $stmt->rowCount() > 0;
    }
    /**
     * Récupérer tous les membres d'une tontine
     */
    public function getMembresByTontine($tontine_id) {
        $query = "SELECT m.*, u.nom, u.prenom, u.email, u.telephone 
                  FROM " . $this->table . " m
                  JOIN users u ON m.user_id = u.id
                  WHERE m.tontine_id = :tontine_id AND m.est_actif = 1
                  ORDER BY m.ordre_tour ASC";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":tontine_id", $tontine_id);
        $stmt->execute();
        
        return $stmt;
    }

    /**
     * Récupérer les tontines d'un membre
     */
    public function getTontinesByMembre($user_id) {
        $query = "SELECT t.*, m.ordre_tour, m.date_adhesion
                  FROM " . $this->table . " m
                  JOIN tontines t ON m.tontine_id = t.id
                  WHERE m.user_id = :user_id AND m.est_actif = 1
                  ORDER BY m.date_adhesion DESC";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":user_id", $user_id);
        $stmt->execute();
        
        return $stmt;
    }

    /**
     * Définir l'ordre du prochain tour (le plus petit ordre disponible)
     */
    public function getProchainOrdre($tontine_id) {
        $query = "SELECT MAX(ordre_tour) as max_ordre 
                  FROM " . $this->table . " 
                  WHERE tontine_id = :tontine_id";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":tontine_id", $tontine_id);
        $stmt->execute();
        
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return ($row['max_ordre'] ?? 0) + 1;
    }

    /**
     * Retirer un membre d'une tontine (désactiver)
     */
    public function retirerMembre($id, $tontine_id) {
        $query = "UPDATE " . $this->table . " 
                  SET est_actif = 0 
                  WHERE id = :id AND tontine_id = :tontine_id";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":id", $id);
        $stmt->bindParam(":tontine_id", $tontine_id);
        
        return $stmt->execute();
    }

    /**
     * Mettre à jour l'ordre d'un membre
     */
    public function updateOrdre($id, $nouvel_ordre) {
        $query = "UPDATE " . $this->table . " 
                  SET ordre_tour = :ordre 
                  WHERE id = :id";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":ordre", $nouvel_ordre);
        $stmt->bindParam(":id", $id);
        
        return $stmt->execute();
    }

    /**
     * Compter le nombre de membres dans une tontine
     */
    public function countMembres($tontine_id) {
        $query = "SELECT COUNT(*) as total 
                  FROM " . $this->table . " 
                  WHERE tontine_id = :tontine_id AND est_actif = 1";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":tontine_id", $tontine_id);
        $stmt->execute();
        
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row['total'];
    }
    /**
    * Vérifier si un membre a des activités (cotisations, amendes)
    */
    public function aDesActivites($membre_tontine_id) {
        // Vérifier les cotisations
        $query = "SELECT id FROM cotisations WHERE membre_tontine_id = :mid LIMIT 1";
        $stmt = $this->conn->prepare($query);
        $stmt->execute(['mid' => $membre_tontine_id]);
        if($stmt->rowCount() > 0) {
            return true;
        }
        
        // Vérifier les amendes
        $query = "SELECT id FROM amendes_appliquees WHERE membre_tontine_id = :mid LIMIT 1";
        $stmt = $this->conn->prepare($query);
        $stmt->execute(['mid' => $membre_tontine_id]);
        if($stmt->rowCount() > 0) {
            return true;
        }
        
        // Vérifier s'il a été bénéficiaire
        $query = "SELECT id FROM seances WHERE beneficiaire_id = :mid LIMIT 1";
        $stmt = $this->conn->prepare($query);
        $stmt->execute(['mid' => $membre_tontine_id]);
        return $stmt->rowCount() > 0;
    }
    /**
     * Récupérer le prochain bénéficiaire d'une tontine
     */
    public function getProchainBeneficiaire($tontine_id) {
        // Récupérer le dernier bénéficiaire
        $query = "SELECT beneficiaire_id FROM seances 
                WHERE tontine_id = :tid AND beneficiaire_id IS NOT NULL 
                ORDER BY date_seance DESC LIMIT 1";
        $stmt = $this->conn->prepare($query);
        $stmt->execute(['tid' => $tontine_id]);
        $dernier = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if($dernier) {
            // Récupérer l'ordre du dernier bénéficiaire
            $query = "SELECT ordre_tour FROM membre_tontine WHERE id = :mid";
            $stmt = $this->conn->prepare($query);
            $stmt->execute(['mid' => $dernier['beneficiaire_id']]);
            $ordre_dernier = $stmt->fetch()['ordre_tour'];
            
            // Chercher le suivant (ordre > dernier)
            $query = "SELECT mt.*, u.prenom, u.nom, u.telephone, u.email
                    FROM membre_tontine mt
                    JOIN users u ON mt.user_id = u.id
                    WHERE mt.tontine_id = :tid 
                        AND mt.est_actif = 1 
                        AND mt.ordre_tour > :ordre
                    ORDER BY mt.ordre_tour ASC LIMIT 1";
            $stmt = $this->conn->prepare($query);
            $stmt->execute(['tid' => $tontine_id, 'ordre' => $ordre_dernier]);
            $suivant = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if(!$suivant) {
                // Retour au début (cycle complet)
                $query = "SELECT mt.*, u.prenom, u.nom, u.telephone, u.email
                        FROM membre_tontine mt
                        JOIN users u ON mt.user_id = u.id
                        WHERE mt.tontine_id = :tid 
                            AND mt.est_actif = 1 
                        ORDER BY mt.ordre_tour ASC LIMIT 1";
                $stmt = $this->conn->prepare($query);
                $stmt->execute(['tid' => $tontine_id]);
                $suivant = $stmt->fetch(PDO::FETCH_ASSOC);
            }
        } else {
            // Aucune séance encore, prendre le premier de l'ordre
            $query = "SELECT mt.*, u.prenom, u.nom, u.telephone, u.email
                    FROM membre_tontine mt
                    JOIN users u ON mt.user_id = u.id
                    WHERE mt.tontine_id = :tid 
                        AND mt.est_actif = 1 
                    ORDER BY mt.ordre_tour ASC LIMIT 1";
            $stmt = $this->conn->prepare($query);
            $stmt->execute(['tid' => $tontine_id]);
            $suivant = $stmt->fetch(PDO::FETCH_ASSOC);
        }
        
        return $suivant;
    }
}
?>