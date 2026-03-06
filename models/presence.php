<?php
class Presence {
    private $conn;
    private $table = "presences";

    public function __construct($db) {
        $this->conn = $db;
    }

    /**
     * Initialiser les présences pour une séance
     */
    public function initPresences($seance_id, $tontine_id) {
        // Récupérer tous les membres actifs de la tontine
        $query = "SELECT id FROM membre_tontine 
                  WHERE tontine_id = :tontine_id AND est_actif = 1";
        $stmt = $this->conn->prepare($query);
        $stmt->execute(['tontine_id' => $tontine_id]);
        
        $success = true;
        while($membre = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $query = "INSERT INTO " . $this->table . "
                      (seance_id, membre_tontine_id, est_present)
                      VALUES (:seance_id, :membre_id, 1)";
            $insert = $this->conn->prepare($query);
            if(!$insert->execute([
                'seance_id' => $seance_id,
                'membre_id' => $membre['id']
            ])) {
                $success = false;
            }
        }
        return $success;
    }

    /**
     * Mettre à jour la présence d'un membre
     */
    public function setPresence($seance_id, $membre_tontine_id, $est_present) {
        $query = "UPDATE " . $this->table . " 
                  SET est_present = :est_present
                  WHERE seance_id = :seance_id AND membre_tontine_id = :membre_id";
        $stmt = $this->conn->prepare($query);
        return $stmt->execute([
            'est_present' => $est_present,
            'seance_id' => $seance_id,
            'membre_id' => $membre_tontine_id
        ]);
    }

    /**
     * Récupérer les présences d'une séance
     */
    public function getBySeance($seance_id) {
        $query = "SELECT p.*, u.nom, u.prenom, mt.ordre_tour
                  FROM " . $this->table . " p
                  JOIN membre_tontine mt ON p.membre_tontine_id = mt.id
                  JOIN users u ON mt.user_id = u.id
                  WHERE p.seance_id = :seance_id
                  ORDER BY mt.ordre_tour ASC";
        $stmt = $this->conn->prepare($query);
        $stmt->execute(['seance_id' => $seance_id]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Compter les présents pour une séance
     */
    public function countPresences($seance_id) {
        $query = "SELECT COUNT(*) as total FROM " . $this->table . " 
                  WHERE seance_id = :seance_id AND est_present = 1";
        $stmt = $this->conn->prepare($query);
        $stmt->execute(['seance_id' => $seance_id]);
        return $stmt->fetch()['total'];
    }

    /**
     * Compter les absents pour une séance
     */
    public function countAbsences($seance_id) {
        $query = "SELECT COUNT(*) as total FROM " . $this->table . " 
                  WHERE seance_id = :seance_id AND est_present = 0";
        $stmt = $this->conn->prepare($query);
        $stmt->execute(['seance_id' => $seance_id]);
        return $stmt->fetch()['total'];
    }
}
?>