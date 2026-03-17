<?php
// Fichier: models/Tontine.php
// But: Gérer toutes les opérations sur les tontines

class Tontine {
    private $conn;
    private $table = "tontines";
    
    // Propriétés existantes
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
    
    // NOUVELLES propriétés pour les cycles
    public $type_cycle;        // trimestriel, semestriel, annuel, personnalise
    public $duree_cycle;       // en mois
    public $date_debut_cycle;   // date de début du cycle actuel
    public $date_fin_cycle;     // date de fin du cycle actuel
    public $cycle_actuel;       // numéro du cycle (1, 2, 3...)
    public $cycle_termine;      // 0 ou 1
    public $parent_tontine_id;  // pour lier les cycles entre eux

    public function __construct($db) {
        $this->conn = $db;
    }

    /**
     * Créer une nouvelle tontine
     */
    public function create() {
        $query = "INSERT INTO " . $this->table . "
                  (nom, description, type_tontine, mode_beneficiaire, montant_cotisation, periodicite,
                   jour_reunion, prochaine_reunion, admin_id, association_id,
                   type_cycle, duree_cycle, date_debut_cycle, date_fin_cycle, cycle_actuel)
                  VALUES (:nom, :description, :type_tontine, :mode_beneficiaire, :montant_cotisation, :periodicite,
                          :jour_reunion, :prochaine_reunion, :admin_id, :association_id,
                          :type_cycle, :duree_cycle, :date_debut_cycle, :date_fin_cycle, :cycle_actuel)";
        
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
        
        // NOUVEAUX paramètres
        $stmt->bindParam(":type_cycle", $this->type_cycle);
        $stmt->bindParam(":duree_cycle", $this->duree_cycle);
        $stmt->bindParam(":date_debut_cycle", $this->date_debut_cycle);
        $stmt->bindParam(":date_fin_cycle", $this->date_fin_cycle);
        $stmt->bindParam(":cycle_actuel", $this->cycle_actuel);
        
        if($stmt->execute()) {
            $this->id = $this->conn->lastInsertId();
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
            
            // NOUVELLES propriétés
            $this->type_cycle = $row['type_cycle'] ?? null;
            $this->duree_cycle = $row['duree_cycle'] ?? null;
            $this->date_debut_cycle = $row['date_debut_cycle'] ?? null;
            $this->date_fin_cycle = $row['date_fin_cycle'] ?? null;
            $this->cycle_actuel = $row['cycle_actuel'] ?? 1;
            $this->cycle_termine = $row['cycle_termine'] ?? 0;
            $this->parent_tontine_id = $row['parent_tontine_id'] ?? null;
            
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
                    prochaine_reunion = :prochaine_reunion,
                    type_cycle = :type_cycle,
                    duree_cycle = :duree_cycle,
                    date_debut_cycle = :date_debut_cycle,
                    date_fin_cycle = :date_fin_cycle,
                    cycle_actuel = :cycle_actuel,
                    cycle_termine = :cycle_termine,
                    parent_tontine_id = :parent_tontine_id
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
        
        // NOUVEAUX paramètres
        $stmt->bindParam(":type_cycle", $this->type_cycle);
        $stmt->bindParam(":duree_cycle", $this->duree_cycle);
        $stmt->bindParam(":date_debut_cycle", $this->date_debut_cycle);
        $stmt->bindParam(":date_fin_cycle", $this->date_fin_cycle);
        $stmt->bindParam(":cycle_actuel", $this->cycle_actuel);
        $stmt->bindParam(":cycle_termine", $this->cycle_termine);
        $stmt->bindParam(":parent_tontine_id", $this->parent_tontine_id);
        
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
     */
    public function calculerProchaineReunion($date_reference = null) {
        if(!$date_reference) {
            $date_reference = $this->prochaine_reunion ?? date('Y-m-d');
        }
        
        switch($this->periodicite) {
            case 'journalier':
                return date('Y-m-d', strtotime($date_reference . ' +1 day'));
            case 'hebdomadaire':
                return date('Y-m-d', strtotime($date_reference . ' +7 days'));
            case 'mensuel':
                $date = new DateTime($date_reference);
                $date->modify('+1 month');
                return $date->format('Y-m-d');
            default:
                return date('Y-m-d', strtotime($date_reference . ' +7 days'));
        }
    }

    /**
     * Mettre à jour la prochaine réunion
     */
    public function updateProchaineReunion() {
        $nouvelle_date = $this->calculerProchaineReunion();
        $this->prochaine_reunion = $nouvelle_date;
        
        $query = "UPDATE " . $this->table . " 
                SET prochaine_reunion = :prochaine_reunion 
                WHERE id = :id";
        
        $stmt = $this->conn->prepare($query);
        return $stmt->execute([
            'prochaine_reunion' => $this->prochaine_reunion,
            'id' => $this->id
        ]);
    }

    /**
     * Vérifier si la tontine a des activités
     */
    public function aDesActivites() {
        $query = "SELECT id FROM seances WHERE tontine_id = :id LIMIT 1";
        $stmt = $this->conn->prepare($query);
        $stmt->execute(['id' => $this->id]);
        if($stmt->rowCount() > 0) {
            return true;
        }
        
        $query = "SELECT c.id FROM cotisations c
                JOIN seances s ON c.seance_id = s.id
                WHERE s.tontine_id = :id LIMIT 1";
        $stmt = $this->conn->prepare($query);
        $stmt->execute(['id' => $this->id]);
        return $stmt->rowCount() > 0;
    }

    // ========== NOUVELLES MÉTHODES POUR LES CYCLES ==========

    /**
     * Initialiser un nouveau cycle
     */
    public function initCycle($type_cycle, $duree_personnalisee = null) {
        $this->type_cycle = $type_cycle;
        $this->date_debut_cycle = date('Y-m-d');
        $this->cycle_actuel = $this->cycle_actuel ?? 1;
        $this->cycle_termine = 0;
        
        // Calculer la durée en mois
        switch($type_cycle) {
            case 'trimestriel':
                $this->duree_cycle = 3;
                break;
            case 'semestriel':
                $this->duree_cycle = 6;
                break;
            case 'annuel':
                $this->duree_cycle = 12;
                break;
            case 'personnalise':
                $this->duree_cycle = $duree_personnalisee;
                break;
            default:
                $this->duree_cycle = null;
        }
        
        // Calculer la date de fin
        if($this->duree_cycle) {
            $date = new DateTime($this->date_debut_cycle);
            $date->modify('+' . $this->duree_cycle . ' months');
            $this->date_fin_cycle = $date->format('Y-m-d');
        }
        
        return $this->update();
    }

    /**
     * Vérifier si le cycle actuel est terminé
     */
    public function estCycleTermine() {
        if(!$this->date_fin_cycle) {
            return false;
        }
        
        $aujourdhui = new DateTime();
        $date_fin = new DateTime($this->date_fin_cycle);
        
        return $aujourdhui > $date_fin;
    }

    /**
     * Marquer le cycle comme terminé
     */
    public function terminerCycle() {
        $this->cycle_termine = 1;
        return $this->update();
    }

    /**
     * Calculer la progression du cycle en pourcentage
     */
    public function getProgressionCycle() {
        if(!$this->date_debut_cycle || !$this->date_fin_cycle) {
            return 0;
        }
        
        $debut = new DateTime($this->date_debut_cycle);
        $fin = new DateTime($this->date_fin_cycle);
        $aujourdhui = new DateTime();
        
        // Si la date de fin est dépassée
        if($aujourdhui > $fin) {
            return 100;
        }
        
        $total_jours = $debut->diff($fin)->days;
        $jours_ecoules = $debut->diff($aujourdhui)->days;
        
        if($total_jours == 0) return 0;
        
        return round(($jours_ecoules / $total_jours) * 100);
    }

    /**
     * Obtenir le nombre de jours restants dans le cycle
     */
    public function getJoursRestants() {
        if(!$this->date_fin_cycle) {
            return null;
        }
        
        $aujourdhui = new DateTime();
        $date_fin = new DateTime($this->date_fin_cycle);
        
        if($aujourdhui > $date_fin) {
            return 0;
        }
        
        return $aujourdhui->diff($date_fin)->days;
    }

    /**
     * Démarrer un nouveau cycle (après la fin d'un cycle)
     */
    public function demarrerNouveauCycle() {
        // Sauvegarder l'ancien cycle dans l'historique
        $query = "INSERT INTO cycles_tontine 
                  (tontine_id, numero_cycle, date_debut, date_fin, nb_membres, ordre_finalise)
                  SELECT :tontine_id, cycle_actuel, date_debut_cycle, date_fin_cycle, 
                         (SELECT COUNT(*) FROM membre_tontine WHERE tontine_id = :tontine_id2 AND est_actif = 1),
                         CASE WHEN mode_beneficiaire = 'auto' THEN 1 ELSE 0 END
                  FROM tontines WHERE id = :tontine_id3";
        
        $stmt = $this->conn->prepare($query);
        $stmt->execute([
            'tontine_id' => $this->id,
            'tontine_id2' => $this->id,
            'tontine_id3' => $this->id
        ]);
        
        // Incrémenter le numéro de cycle
        $this->cycle_actuel++;
        
        // Réinitialiser les dates du nouveau cycle
        $this->date_debut_cycle = date('Y-m-d');
        if($this->duree_cycle) {
            $date = new DateTime($this->date_debut_cycle);
            $date->modify('+' . $this->duree_cycle . ' months');
            $this->date_fin_cycle = $date->format('Y-m-d');
        }
        
        $this->cycle_termine = 0;
        
        return $this->update();
    }
}
?>