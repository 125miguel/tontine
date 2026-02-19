<?php
// Fichier: models/Cotisation.php
// But: Gérer les cotisations des membres

class Cotisation {
    private $conn;
    private $table = "cotisations";
    
    // Propriétés
    public $id;
    public $seance_id;
    public $membre_tontine_id;
    public $a_paye;
    public $date_paiement;
    public $montant;
    public $statut;
    public $created_at;

    public function __construct($db) {
        $this->conn = $db;
    }

    /**
     * Enregistrer une cotisation
     */
    public function create() {
        $query = "INSERT INTO " . $this->table . "
                  (seance_id, membre_tontine_id, a_paye, date_paiement, montant, statut)
                  VALUES (:seance_id, :membre_tontine_id, :a_paye, :date_paiement, :montant, :statut)";
        
        $stmt = $this->conn->prepare($query);
        
        $stmt->bindParam(":seance_id", $this->seance_id);
        $stmt->bindParam(":membre_tontine_id", $this->membre_tontine_id);
        $stmt->bindParam(":a_paye", $this->a_paye);
        $stmt->bindParam(":date_paiement", $this->date_paiement);
        $stmt->bindParam(":montant", $this->montant);
        $stmt->bindParam(":statut", $this->statut);
        
        return $stmt->execute();
    }

    /**
     * Récupérer les cotisations d'une séance
     */
    public function getBySeance($seance_id) {
        $query = "SELECT c.*, mt.user_id, u.nom, u.prenom, mt.ordre_tour
                  FROM " . $this->table . " c
                  JOIN membre_tontine mt ON c.membre_tontine_id = mt.id
                  JOIN users u ON mt.user_id = u.id
                  WHERE c.seance_id = :seance_id
                  ORDER BY mt.ordre_tour ASC";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":seance_id", $seance_id);
        $stmt->execute();
        
        return $stmt;
    }

    /**
     * Mettre à jour le statut d'une cotisation
     */
    public function updateStatut($id, $statut, $date_paiement = null) {
        $query = "UPDATE " . $this->table . " 
                  SET statut = :statut, a_paye = 1, date_paiement = :date_paiement
                  WHERE id = :id";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":statut", $statut);
        $stmt->bindParam(":date_paiement", $date_paiement);
        $stmt->bindParam(":id", $id);
        
        return $stmt->execute();
    }

    /**
     * Initialiser les cotisations pour une nouvelle séance
     * Crée une entrée pour chaque membre de la tontine
     */
    public function initCotisationsPourSeance($seance_id, $tontine_id, $montant_cotisation) {
        // Récupérer tous les membres actifs de la tontine
        $queryMembres = "SELECT id FROM membre_tontine 
                         WHERE tontine_id = :tontine_id AND est_actif = 1";
        
        $stmtMembres = $this->conn->prepare($queryMembres);
        $stmtMembres->bindParam(":tontine_id", $tontine_id);
        $stmtMembres->execute();
        
        $success = true;
        
        while($membre = $stmtMembres->fetch(PDO::FETCH_ASSOC)) {
            $query = "INSERT INTO " . $this->table . "
                      (seance_id, membre_tontine_id, montant, statut)
                      VALUES (:seance_id, :membre_id, :montant, 'en_attente')";
            
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(":seance_id", $seance_id);
            $stmt->bindParam(":membre_id", $membre['id']);
            $stmt->bindParam(":montant", $montant_cotisation);
            
            if(!$stmt->execute()) {
                $success = false;
            }
        }
        
        return $success;
    }

    /**
     * Calculer le total collecté pour une séance
     */
    public function calculerTotalSeance($seance_id) {
        $query = "SELECT SUM(montant) as total 
                  FROM " . $this->table . " 
                  WHERE seance_id = :seance_id AND statut = 'paye'";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":seance_id", $seance_id);
        $stmt->execute();
        
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row['total'] ?? 0;
    }

    /**
     * Compter le nombre de payés pour une séance
     */
    public function countPayes($seance_id) {
        $query = "SELECT COUNT(*) as total 
                  FROM " . $this->table . " 
                  WHERE seance_id = :seance_id AND statut = 'paye'";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":seance_id", $seance_id);
        $stmt->execute();
        
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row['total'];
    }
}
?>