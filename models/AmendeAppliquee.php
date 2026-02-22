<?php
// Fichier: models/AmendeAppliquee.php
// But: Gérer les amendes appliquées (simplifié)

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
 * Marquer une amende comme payée
 * @param int $id ID de l'amende
 * @param string $date_paiement Date de paiement (Y-m-d)
 * @return bool True si succès
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