<?php
// Fichier: models/Seance.php
// But: Gérer les séances (réunions) de tontine

class Seance {
    private $conn;
    private $table = "seances";
    
    // Propriétés
    public $id;
    public $tontine_id;
    public $date_seance;
    public $beneficiaire_id;
    public $total_collecte;
    public $est_cloturee;
    public $created_at;

    public function __construct($db) {
        $this->conn = $db;
    }

    /**
     * Créer une nouvelle séance (ouvrir une réunion)
     */
    public function create() {
        $query = "INSERT INTO " . $this->table . "
                  (tontine_id, date_seance)
                  VALUES (:tontine_id, :date_seance)";
        
        $stmt = $this->conn->prepare($query);
        
        $stmt->bindParam(":tontine_id", $this->tontine_id);
        $stmt->bindParam(":date_seance", $this->date_seance);
        
        if($stmt->execute()) {
            $this->id = $this->conn->lastInsertId();
            return true;
        }
        return false;
    }

    /**
     * Récupérer la séance active d'une tontine (non clôturée)
     */
    public function getSeanceActive($tontine_id) {
        $query = "SELECT * FROM " . $this->table . " 
                  WHERE tontine_id = :tontine_id AND est_cloturee = 0
                  ORDER BY date_seance DESC LIMIT 1";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":tontine_id", $tontine_id);
        $stmt->execute();
        
        if($stmt->rowCount() > 0) {
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            $this->id = $row['id'];
            $this->tontine_id = $row['tontine_id'];
            $this->date_seance = $row['date_seance'];
            $this->beneficiaire_id = $row['beneficiaire_id'];
            $this->total_collecte = $row['total_collecte'];
            $this->est_cloturee = $row['est_cloturee'];
            return true;
        }
        return false;
    }

    /**
     * Récupérer toutes les séances d'une tontine
     */
    public function getByTontine($tontine_id) {
        $query = "SELECT s.*, 
                         CONCAT(u.prenom, ' ', u.nom) as beneficiaire_nom
                  FROM " . $this->table . " s
                  LEFT JOIN membre_tontine mt ON s.beneficiaire_id = mt.id
                  LEFT JOIN users u ON mt.user_id = u.id
                  WHERE s.tontine_id = :tontine_id
                  ORDER BY s.date_seance DESC";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":tontine_id", $tontine_id);
        $stmt->execute();
        
        return $stmt;
    }

    /**
     * Récupérer une séance par son ID
     */
    public function getById($id) {
        $query = "SELECT * FROM " . $this->table . " WHERE id = :id LIMIT 1";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":id", $id);
        $stmt->execute();
        
        if($stmt->rowCount() > 0) {
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            $this->id = $row['id'];
            $this->tontine_id = $row['tontine_id'];
            $this->date_seance = $row['date_seance'];
            $this->beneficiaire_id = $row['beneficiaire_id'];
            $this->total_collecte = $row['total_collecte'];
            $this->est_cloturee = $row['est_cloturee'];
            return true;
        }
        return false;
    }

    /**
     * Définir le bénéficiaire de la séance
     */
    public function setBeneficiaire($seance_id, $membre_tontine_id) {
        $query = "UPDATE " . $this->table . " 
                  SET beneficiaire_id = :beneficiaire_id
                  WHERE id = :id";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":beneficiaire_id", $membre_tontine_id);
        $stmt->bindParam(":id", $seance_id);
        
        return $stmt->execute();
    }

    /**
     * Clôturer une séance
     */
    public function cloturer($seance_id, $total) {
        $query = "UPDATE " . $this->table . " 
                  SET est_cloturee = 1, total_collecte = :total
                  WHERE id = :id";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":total", $total);
        $stmt->bindParam(":id", $seance_id);
        
        return $stmt->execute();
    }
    /**
     * Récupérer les notes d'une séance
     */
    public function getNotes($seance_id) {
        $query = "SELECT notes FROM notes_seance WHERE seance_id = :seance_id";
        $stmt = $this->conn->prepare($query);
        $stmt->execute(['seance_id' => $seance_id]);
        
        if($stmt->rowCount() > 0) {
            return $stmt->fetch()['notes'];
        }
        return '';
    }

    /**
     * Sauvegarder les notes d'une séance
     */
    public function saveNotes($seance_id, $notes) {
        // Vérifier si des notes existent déjà
        $query = "SELECT id FROM notes_seance WHERE seance_id = :seance_id";
        $stmt = $this->conn->prepare($query);
        $stmt->execute(['seance_id' => $seance_id]);
        
        if($stmt->rowCount() > 0) {
            // Mettre à jour
            $query = "UPDATE notes_seance SET notes = :notes WHERE seance_id = :seance_id";
        } else {
            // Insérer
            $query = "INSERT INTO notes_seance (seance_id, notes) VALUES (:seance_id, :notes)";
        }
        
        $stmt = $this->conn->prepare($query);
        return $stmt->execute([
            'seance_id' => $seance_id,
            'notes' => $notes
        ]);
    }

    /**
     * Vérifier si une tontine a une séance active
     */
    public function aSeanceActive($tontine_id) {
        $query = "SELECT id FROM " . $this->table . " 
                  WHERE tontine_id = :tontine_id AND est_cloturee = 0";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":tontine_id", $tontine_id);
        $stmt->execute();
        
        return $stmt->rowCount() > 0;
    }
}
?>