<?php
// Fichier: models/RegleAmende.php
// But: Gérer les règles d'amendes (simplifié)

class RegleAmende {
    private $conn;
    private $table = "regles_amendes";

    public function __construct($db) {
        $this->conn = $db;
    }

    /**
     * Créer ou mettre à jour une règle d'amende
     */
    public function setRegle($tontine_id, $type, $montant, $description = '') {
        // Vérifier si la règle existe déjà
        $query = "SELECT id FROM " . $this->table . " 
                  WHERE tontine_id = :tontine_id AND type_amende = :type";
        $stmt = $this->conn->prepare($query);
        $stmt->execute([
            'tontine_id' => $tontine_id,
            'type' => $type
        ]);

        if($stmt->rowCount() > 0) {
            // Mettre à jour
            $query = "UPDATE " . $this->table . " 
                      SET montant = :montant, description = :description, est_actif = 1
                      WHERE tontine_id = :tontine_id AND type_amende = :type";
        } else {
            // Créer
            $query = "INSERT INTO " . $this->table . " 
                      (tontine_id, type_amende, montant, description)
                      VALUES (:tontine_id, :type, :montant, :description)";
        }

        $stmt = $this->conn->prepare($query);
        return $stmt->execute([
            'tontine_id' => $tontine_id,
            'type' => $type,
            'montant' => $montant,
            'description' => $description
        ]);
    }

    /**
     * Récupérer toutes les règles d'une tontine
     */
    public function getByTontine($tontine_id) {
        $query = "SELECT * FROM " . $this->table . " 
                  WHERE tontine_id = :tontine_id AND est_actif = 1
                  ORDER BY type_amende";
        
        $stmt = $this->conn->prepare($query);
        $stmt->execute(['tontine_id' => $tontine_id]);
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Récupérer une règle spécifique
     */
    public function getByType($tontine_id, $type) {
        $query = "SELECT * FROM " . $this->table . " 
                  WHERE tontine_id = :tontine_id AND type_amende = :type AND est_actif = 1";
        
        $stmt = $this->conn->prepare($query);
        $stmt->execute([
            'tontine_id' => $tontine_id,
            'type' => $type
        ]);
        
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
}
?>