<?php
class AmendeAppliquee {
    private $conn;
    private $table = "amendes_appliquees";

    public function __construct($db) {
        $this->conn = $db;
    }

    /**
     * Appliquer une amende à un membre
     */
    public function appliquer($seance_id, $membre_tontine_id, $regle_amende_id, $montant, $date_application) {
        $query = "INSERT INTO " . $this->table . "
                  (seance_id, membre_tontine_id, regle_amende_id, montant, date_application)
                  VALUES (:seance_id, :membre_tontine_id, :regle_amende_id, :montant, :date_application)";
        
        $stmt = $this->conn->prepare($query);
        return $stmt->execute([
            'seance_id' => $seance_id,
            'membre_tontine_id' => $membre_tontine_id,
            'regle_amende_id' => $regle_amende_id,
            'montant' => $montant,
            'date_application' => $date_application
        ]);
    }

    /**
     * Récupérer les amendes d'une séance
     */
    public function getBySeance($seance_id) {
        $query = "SELECT a.*, r.type_amende, r.description, u.nom, u.prenom
                  FROM " . $this->table . " a
                  LEFT JOIN regles_amendes r ON a.regle_amende_id = r.id
                  LEFT JOIN membre_tontine m ON a.membre_tontine_id = m.id
                  LEFT JOIN users u ON m.user_id = u.id
                  WHERE a.seance_id = :seance_id
                  ORDER BY a.date_application DESC";
        
        $stmt = $this->conn->prepare($query);
        $stmt->execute(['seance_id' => $seance_id]);
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Récupérer les amendes impayées d'un membre
     */
    public function getImpayesByMembre($membre_tontine_id) {
        $query = "SELECT a.*, r.type_amende, r.description, s.date_seance
                  FROM " . $this->table . " a
                  LEFT JOIN regles_amendes r ON a.regle_amende_id = r.id
                  LEFT JOIN seances s ON a.seance_id = s.id
                  WHERE a.membre_tontine_id = :membre_tontine_id 
                  AND a.est_paye = 0
                  ORDER BY a.date_application DESC";
        
        $stmt = $this->conn->prepare($query);
        $stmt->execute(['membre_tontine_id' => $membre_tontine_id]);
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Récupérer les amendes d'un membre
     */
    public function getByMembre($membre_tontine_id) {
        $query = "SELECT a.*, r.type_amende, s.date_seance
                  FROM " . $this->table . " a
                  LEFT JOIN regles_amendes r ON a.regle_amende_id = r.id
                  LEFT JOIN seances s ON a.seance_id = s.id
                  WHERE a.membre_tontine_id = :membre_tontine_id
                  ORDER BY a.date_application DESC";
        
        $stmt = $this->conn->prepare($query);
        $stmt->execute(['membre_tontine_id' => $membre_tontine_id]);
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Calculer le total des amendes pour une séance
     */
    public function calculerTotalSeance($seance_id) {
        $query = "SELECT SUM(montant) as total 
                  FROM " . $this->table . " 
                  WHERE seance_id = :seance_id";
        
        $stmt = $this->conn->prepare($query);
        $stmt->execute(['seance_id' => $seance_id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        
        return $row['total'] ?? 0;
    }

    /**
     * Calculer le total des amendes payées pour une séance
     */
    public function calculerTotalPayeSeance($seance_id) {
        $query = "SELECT SUM(montant) as total 
                  FROM " . $this->table . " 
                  WHERE seance_id = :seance_id AND est_paye = 1";
        
        $stmt = $this->conn->prepare($query);
        $stmt->execute(['seance_id' => $seance_id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        
        return $row['total'] ?? 0;
    }

    /**
     * Marquer une amende comme payée
     */
    public function marquerPaye($id, $date_paiement) {
        $query = "UPDATE " . $this->table . " 
                  SET est_paye = 1, date_paiement = :date_paiement
                  WHERE id = :id";
        
        $stmt = $this->conn->prepare($query);
        return $stmt->execute([
            'date_paiement' => $date_paiement,
            'id' => $id
        ]);
    }
}
?>