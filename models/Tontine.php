<?php
// Fichier: models/Tontine.php
// But: Gérer toutes les opérations sur les tontines

class Tontine {
    private $conn;
    private $table = "tontines";
    
    // Propriétés
    public $id;
    public $nom;
    public $description;
    public $montant_cotisation;
    public $periodicite;
    public $jour_reunion;
    public $prochaine_reunion;
    public $admin_id;
    public $created_at;
    public $type_tontine;

    public function __construct($db) {
        $this->conn = $db;
    }

    /**
 * Créer une nouvelle tontine
 */
public function create() {
    $query = "INSERT INTO " . $this->table . "
              (nom, description, type_tontine, montant_cotisation, periodicite,
               jour_reunion, prochaine_reunion, admin_id)
              VALUES (:nom, :description, :type_tontine, :montant_cotisation, :periodicite,
                      :jour_reunion, :prochaine_reunion, :admin_id)";
    
    $stmt = $this->conn->prepare($query);
    
    // Nettoyer les données
    $this->nom = htmlspecialchars(strip_tags($this->nom));
    $this->description = htmlspecialchars(strip_tags($this->description));
    $this->type_tontine = htmlspecialchars(strip_tags($this->type_tontine));
    $this->montant_cotisation = htmlspecialchars(strip_tags($this->montant_cotisation));
    $this->periodicite = htmlspecialchars(strip_tags($this->periodicite));
    $this->jour_reunion = htmlspecialchars(strip_tags($this->jour_reunion));
    
    // Lier les paramètres
    $stmt->bindParam(":nom", $this->nom);
    $stmt->bindParam(":description", $this->description);
    $stmt->bindParam(":type_tontine", $this->type_tontine);
    $stmt->bindParam(":montant_cotisation", $this->montant_cotisation);
    $stmt->bindParam(":periodicite", $this->periodicite);
    $stmt->bindParam(":jour_reunion", $this->jour_reunion);
    $stmt->bindParam(":prochaine_reunion", $this->prochaine_reunion);
    $stmt->bindParam(":admin_id", $this->admin_id);
    
    if($stmt->execute()) {
        return true;
    }
    return false;
}

    /**
     * Récupérer toutes les tontines d'un admin
     */
    public function getByAdmin($admin_id) {
        $query = "SELECT * FROM " . $this->table . " 
                  WHERE admin_id = :admin_id 
                  ORDER BY created_at DESC";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":admin_id", $admin_id);
        $stmt->execute();
        
        return $stmt;
    }

    /**
     * Récupérer une tontine par son ID
     */
    public function getById($id) {
        $query = "SELECT * FROM " . $this->table . " WHERE id = :id LIMIT 1";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":id", $id);
        $stmt->execute();
        
        if($stmt->rowCount() > 0) {
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            
            $this->id = $row['id'];
            $this->nom = $row['nom'];
            $this->description = $row['description'];
            $this->type_tontine = $row['type_tontine'];
            $this->montant_cotisation = $row['montant_cotisation'];
            $this->periodicite = $row['periodicite'];
            $this->jour_reunion = $row['jour_reunion'];
            $this->prochaine_reunion = $row['prochaine_reunion'];
            $this->admin_id = $row['admin_id'];
            $this->created_at = $row['created_at'];
            
            return true;
        }
        
        return false;
    }

    /**
     * Mettre à jour une tontine
     */
    public function update() {
        $query = "UPDATE " . $this->table . "
                  SET
                    nom = :nom,
                    description = :description,
                    montant_cotisation = :montant_cotisation,
                    periodicite = :periodicite,
                    jour_reunion = :jour_reunion,
                    prochaine_reunion = :prochaine_reunion
                  WHERE id = :id AND admin_id = :admin_id";
        
        $stmt = $this->conn->prepare($query);
        
        $this->nom = htmlspecialchars(strip_tags($this->nom));
        $this->description = htmlspecialchars(strip_tags($this->description));
        $this->montant_cotisation = htmlspecialchars(strip_tags($this->montant_cotisation));
        $this->periodicite = htmlspecialchars(strip_tags($this->periodicite));
        $this->jour_reunion = htmlspecialchars(strip_tags($this->jour_reunion));
        
        $stmt->bindParam(":nom", $this->nom);
        $stmt->bindParam(":description", $this->description);
        $stmt->bindParam(":montant_cotisation", $this->montant_cotisation);
        $stmt->bindParam(":periodicite", $this->periodicite);
        $stmt->bindParam(":jour_reunion", $this->jour_reunion);
        $stmt->bindParam(":prochaine_reunion", $this->prochaine_reunion);
        $stmt->bindParam(":id", $this->id);
        $stmt->bindParam(":admin_id", $this->admin_id);
        
        if($stmt->execute()) {
            return true;
        }
        return false;
    }

    /**
     * Supprimer une tontine
     */
    public function delete($id, $admin_id) {
        $query = "DELETE FROM " . $this->table . " 
                  WHERE id = :id AND admin_id = :admin_id";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":id", $id);
        $stmt->bindParam(":admin_id", $admin_id);
        
        if($stmt->execute()) {
            return true;
        }
        return false;
    }
}
?>