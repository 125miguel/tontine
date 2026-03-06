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
    public $association_id;
    public $created_at;
    public $type_tontine;
    public $mode_beneficiaire;

    public function __construct($db) {
        $this->conn = $db;
    }

    /**
 * Créer une nouvelle tontine
 */
public function create() {
    $query = "INSERT INTO " . $this->table . "
              (nom, description, type_tontine, mode_beneficiaire, montant_cotisation, periodicite,
               jour_reunion, prochaine_reunion, admin_id, association_id)
              VALUES (:nom, :description, :type_tontine, :mode_beneficiaire, :montant_cotisation, :periodicite,
                      :jour_reunion, :prochaine_reunion, :admin_id, :association_id)";
    
    $stmt = $this->conn->prepare($query);
    
    $stmt->bindParam(":nom", $this->nom);
    $stmt->bindParam(":description", $this->description);
    $stmt->bindParam(":type_tontine", $this->type_tontine);
    $stmt->bindParam(":mode_beneficiaire", $this->mode_beneficiaire);
    $stmt->bindParam(":montant_cotisation", $this->montant_cotisation);
    $stmt->bindParam(":periodicite", $this->periodicite);
    $stmt->bindParam(":jour_reunion", $this->jour_reunion);
    $stmt->bindParam(":prochaine_reunion", $this->prochaine_reunion);
    $stmt->bindParam(":admin_id", $this->admin_id);
    $stmt->bindParam(":association_id", $this->association_id);
    
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
            $this->mode_beneficiaire = $row['mode_beneficiaire'];
            $this->montant_cotisation = $row['montant_cotisation'];
            $this->periodicite = $row['periodicite'];
            $this->jour_reunion = $row['jour_reunion'];
            $this->prochaine_reunion = $row['prochaine_reunion'];
            $this->admin_id = $row['admin_id'];
            $this->association_id = $row['association_id'];
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
    /**
     * Calculer la prochaine date de réunion en fonction de la périodicité
     * @param string $date_reference Date de référence (optionnelle)
     * @return string Date au format Y-m-d
     */
    public function calculerProchaineReunion($date_reference = null) {
        // Si aucune date de référence, utiliser la prochaine réunion ou la date du jour
        if(!$date_reference) {
            $date_reference = $this->prochaine_reunion ?? date('Y-m-d');
        }
        
        // Calcul selon la périodicité
        switch($this->periodicite) {
            case 'journalier':
                return date('Y-m-d', strtotime($date_reference . ' +1 day'));
                
            case 'hebdomadaire':
                return date('Y-m-d', strtotime($date_reference . ' +7 days'));
                
            case 'mensuel':
                // Gestion spéciale pour les mois (ex: 31 janvier + 1 mois = 28 février)
                $date = new DateTime($date_reference);
                $date->modify('+1 month');
                return $date->format('Y-m-d');
                
            default:
                // Par défaut, on ajoute 7 jours
                return date('Y-m-d', strtotime($date_reference . ' +7 days'));
        }
    }

    /**
     * Mettre à jour la prochaine réunion dans la base de données
     * @return bool Succès ou échec
     */
    public function updateProchaineReunion() {
        // Calculer la nouvelle date
        $nouvelle_date = $this->calculerProchaineReunion();
        
        // Mettre à jour l'objet
        $this->prochaine_reunion = $nouvelle_date;
        
        // Mettre à jour la base
        $query = "UPDATE " . $this->table . " 
                SET prochaine_reunion = :prochaine_reunion 
                WHERE id = :id";
        
        $stmt = $this->conn->prepare($query);
        return $stmt->execute([
            'prochaine_reunion' => $this->prochaine_reunion,
            'id' => $this->id
        ]);
    }
}
?>