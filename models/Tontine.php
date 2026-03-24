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
    
    // Propriétés pour les cycles
    public $type_cycle;
    public $duree_cycle;
    public $date_debut_cycle;
    public $date_fin_cycle;
    public $cycle_actuel;
    public $cycle_termine;
    public $parent_tontine_id;
    
    // Propriétés pour la gestion financière
    public $type_cotisation;
    public $solde_caisse;

    public function __construct($db) {
        $this->conn = $db;
    }

    /**
     * Créer une nouvelle tontine
     */
    public function create() {
        $query = "INSERT INTO " . $this->table . "
                  (nom, description, type_tontine, mode_beneficiaire, montant_cotisation, type_cotisation, solde_caisse, periodicite,
                   jour_reunion, prochaine_reunion, admin_id, association_id,
                   type_cycle, duree_cycle, date_debut_cycle, date_fin_cycle, cycle_actuel)
                  VALUES (:nom, :description, :type_tontine, :mode_beneficiaire, :montant_cotisation, :type_cotisation, :solde_caisse, :periodicite,
                          :jour_reunion, :prochaine_reunion, :admin_id, :association_id,
                          :type_cycle, :duree_cycle, :date_debut_cycle, :date_fin_cycle, :cycle_actuel)";
        
        $stmt = $this->conn->prepare($query);
        
        $stmt->bindParam(":nom", $this->nom);
        $stmt->bindParam(":description", $this->description);
        $stmt->bindParam(":type_tontine", $this->type_tontine);
        $stmt->bindParam(":mode_beneficiaire", $this->mode_beneficiaire);
        $stmt->bindParam(":montant_cotisation", $this->montant_cotisation);
        $stmt->bindParam(":type_cotisation", $this->type_cotisation);
        $stmt->bindParam(":solde_caisse", $this->solde_caisse);
        $stmt->bindParam(":periodicite", $this->periodicite);
        $stmt->bindParam(":jour_reunion", $this->jour_reunion);
        $stmt->bindParam(":prochaine_reunion", $this->prochaine_reunion);
        $stmt->bindParam(":admin_id", $this->admin_id);
        $stmt->bindParam(":association_id", $this->association_id);
        
        // Paramètres des cycles
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
            $this->type_cotisation = $row['type_cotisation'] ?? 'fixe';
            $this->solde_caisse = $row['solde_caisse'] ?? 0;
            $this->periodicite = $row['periodicite'];
            $this->jour_reunion = $row['jour_reunion'];
            $this->prochaine_reunion = $row['prochaine_reunion'];
            $this->admin_id = $row['admin_id'];
            $this->association_id = $row['association_id'];
            $this->created_at = $row['created_at'];
            
            // Propriétés des cycles
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
                    type_cotisation = :type_cotisation,
                    solde_caisse = :solde_caisse,
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
        $this->type_cotisation = htmlspecialchars(strip_tags($this->type_cotisation));
        $this->periodicite = htmlspecialchars(strip_tags($this->periodicite));
        $this->jour_reunion = htmlspecialchars(strip_tags($this->jour_reunion));
        
        $stmt->bindParam(":nom", $this->nom);
        $stmt->bindParam(":description", $this->description);
        $stmt->bindParam(":montant_cotisation", $this->montant_cotisation);
        $stmt->bindParam(":type_cotisation", $this->type_cotisation);
        $stmt->bindParam(":solde_caisse", $this->solde_caisse);
        $stmt->bindParam(":periodicite", $this->periodicite);
        $stmt->bindParam(":jour_reunion", $this->jour_reunion);
        $stmt->bindParam(":prochaine_reunion", $this->prochaine_reunion);
        $stmt->bindParam(":id", $this->id);
        $stmt->bindParam(":admin_id", $this->admin_id);
        
        // Paramètres des cycles
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
     * Calculer la prochaine date de réunion
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

    // ========== MÉTHODES POUR LES CYCLES ==========

    public function initCycle($type_cycle, $duree_personnalisee = null) {
        $this->type_cycle = $type_cycle;
        $this->date_debut_cycle = date('Y-m-d');
        $this->cycle_actuel = $this->cycle_actuel ?? 1;
        $this->cycle_termine = 0;
        
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
        
        if($this->duree_cycle) {
            $date = new DateTime($this->date_debut_cycle);
            $date->modify('+' . $this->duree_cycle . ' months');
            $this->date_fin_cycle = $date->format('Y-m-d');
        }
        
        return $this->update();
    }

    public function estCycleTermine() {
        if(!$this->date_fin_cycle) {
            return false;
        }
        
        $aujourdhui = new DateTime();
        $date_fin = new DateTime($this->date_fin_cycle);
        
        return $aujourdhui > $date_fin;
    }

    public function terminerCycle() {
        $this->cycle_termine = 1;
        return $this->update();
    }

    public function getProgressionCycle() {
        if(!$this->date_debut_cycle || !$this->date_fin_cycle) {
            return 0;
        }
        
        $debut = new DateTime($this->date_debut_cycle);
        $fin = new DateTime($this->date_fin_cycle);
        $aujourdhui = new DateTime();
        
        if($aujourdhui > $fin) {
            return 100;
        }
        
        $total_jours = $debut->diff($fin)->days;
        $jours_ecoules = $debut->diff($aujourdhui)->days;
        
        if($total_jours == 0) return 0;
        
        return round(($jours_ecoules / $total_jours) * 100);
    }

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

    public function demarrerNouveauCycle() {
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
        
        $this->cycle_actuel++;
        $this->date_debut_cycle = date('Y-m-d');
        
        if($this->duree_cycle) {
            $date = new DateTime($this->date_debut_cycle);
            $date->modify('+' . $this->duree_cycle . ' months');
            $this->date_fin_cycle = $date->format('Y-m-d');
        }
        
        $this->cycle_termine = 0;
        
        return $this->update();
    }

    // ========== MÉTHODES POUR LA GESTION FINANCIÈRE ==========

    public function updateSoldeCaisse($montant, $operation = 'ajout') {
        if($operation == 'ajout') {
            $this->solde_caisse += $montant;
        } else {
            $this->solde_caisse -= $montant;
        }
        
        $query = "UPDATE " . $this->table . " SET solde_caisse = :solde WHERE id = :id";
        $stmt = $this->conn->prepare($query);
        return $stmt->execute([
            'solde' => $this->solde_caisse,
            'id' => $this->id
        ]);
    }

    public function enregistrerOperation($type, $montant, $description, $lien_id = null) {
        $query = "INSERT INTO operations_caisse 
                  (tontine_id, type_operation, montant, description, lien_id)
                  VALUES (:tontine_id, :type, :montant, :description, :lien_id)";
        
        $stmt = $this->conn->prepare($query);
        return $stmt->execute([
            'tontine_id' => $this->id,
            'type' => $type,
            'montant' => $montant,
            'description' => $description,
            'lien_id' => $lien_id
        ]);
    }

    public function getSoldeCaisse() {
        return $this->solde_caisse;
    }

    public function estCotisationLibre() {
        return ($this->type_tontine == 'anniversaire' && $this->type_cotisation == 'libre');
    }

    public function estTypeSolidaire() {
        return ($this->type_tontine == 'solidarite');
    }

    public function estTypePret() {
        return ($this->type_tontine == 'pret');
    }

    // ========== MÉTHODES POUR LES PRÊTS ==========

    /**
     * Créer un nouveau prêt
     */
    public function creerPret($membre_id, $montant, $taux_interet, $duree_mois) {
        // Vérifier si le solde est suffisant
        if($this->solde_caisse < $montant) {
            return ['success' => false, 'message' => 'Solde insuffisant'];
        }
        
        // Calculer le montant total à rembourser avec intérêts
        $interets = $montant * ($taux_interet / 100);
        $montant_total = $montant + $interets;
        
        // Date d'octroi et échéance
        $date_octroi = date('Y-m-d');
        $date_echeance = date('Y-m-d', strtotime("+$duree_mois months"));
        
        // Créer le prêt
        $query = "INSERT INTO prets (tontine_id, membre_id, montant_pret, taux_interet, montant_total_du, duree_remboursement, date_octroi, date_echeance)
                  VALUES (:tid, :mid, :montant, :taux, :total, :duree, :date_octroi, :date_echeance)";
        $stmt = $this->conn->prepare($query);
        $result = $stmt->execute([
            'tid' => $this->id,
            'mid' => $membre_id,
            'montant' => $montant,
            'taux' => $taux_interet,
            'total' => $montant_total,
            'duree' => $duree_mois,
            'date_octroi' => $date_octroi,
            'date_echeance' => $date_echeance
        ]);
        
        if(!$result) {
            return ['success' => false, 'message' => 'Erreur lors de la création du prêt'];
        }
        
        $pret_id = $this->conn->lastInsertId();
        
        // Créer les échéances mensuelles
        $montant_echeance = round($montant_total / $duree_mois);
        for($i = 1; $i <= $duree_mois; $i++) {
            $date_echeance_mois = date('Y-m-d', strtotime("+$i months"));
            $queryEch = "INSERT INTO echeances_prets (pret_id, numero_echeance, montant_du, date_echeance)
                         VALUES (:pid, :num, :montant, :date)";
            $stmtEch = $this->conn->prepare($queryEch);
            $stmtEch->execute([
                'pid' => $pret_id,
                'num' => $i,
                'montant' => $montant_echeance,
                'date' => $date_echeance_mois
            ]);
        }
        
        // Retirer le montant du solde de la caisse
        $this->updateSoldeCaisse($montant, 'retrait');
        $this->enregistrerOperation('pret', $montant, "Prêt accordé d'un montant de " . number_format($montant, 0, ',', ' ') . " F (taux " . $taux_interet . "%)");
        
        return ['success' => true, 'pret_id' => $pret_id, 'message' => 'Prêt créé avec succès'];
    }

    /**
     * Enregistrer un remboursement d'échéance (VERSION CORRIGÉE)
     */
    public function rembourserEcheance($echeance_id, $montant_paye) {
        // Récupérer l'échéance avec les infos du prêt
        $query = "SELECT e.*, p.id as pret_id, p.montant_total_du, p.statut as pret_statut
                  FROM echeances_prets e
                  JOIN prets p ON e.pret_id = p.id
                  WHERE e.id = :id";
        $stmt = $this->conn->prepare($query);
        $stmt->execute(['id' => $echeance_id]);
        $echeance = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if(!$echeance) {
            return ['success' => false, 'message' => 'Échéance non trouvée'];
        }
        
        // Vérifier si l'échéance n'est pas déjà payée
        if($echeance['statut'] == 'paye') {
            return ['success' => false, 'message' => 'Cette échéance a déjà été payée'];
        }
        
        // Mettre à jour l'échéance
        $query = "UPDATE echeances_prets 
                  SET montant_paye = :paye, 
                      date_paiement = NOW(), 
                      statut = 'paye'
                  WHERE id = :id";
        $stmt = $this->conn->prepare($query);
        $result = $stmt->execute([
            'paye' => $montant_paye,
            'id' => $echeance_id
        ]);
        
        if(!$result) {
            return ['success' => false, 'message' => 'Erreur lors du remboursement'];
        }
        
        // Ajouter le montant au solde de la caisse
        $this->updateSoldeCaisse($montant_paye, 'ajout');
        $this->enregistrerOperation(
            'remboursement', 
            $montant_paye, 
            "Remboursement d'échéance n°" . $echeance['numero_echeance']
        );
        
        // Vérifier si TOUTES les échéances sont payées
        $queryCheck = "SELECT COUNT(*) as total, 
                              SUM(CASE WHEN statut = 'paye' THEN 1 ELSE 0 END) as paye
                       FROM echeances_prets 
                       WHERE pret_id = :pid";
        $stmtCheck = $this->conn->prepare($queryCheck);
        $stmtCheck->execute(['pid' => $echeance['pret_id']]);
        $stats = $stmtCheck->fetch(PDO::FETCH_ASSOC);
        
        // Si toutes les échéances sont payées, marquer le prêt comme remboursé
        if($stats['total'] == $stats['paye']) {
            $query = "UPDATE prets SET statut = 'rembourse' WHERE id = :id";
            $stmt = $this->conn->prepare($query);
            $stmt->execute(['id' => $echeance['pret_id']]);
            
            return ['success' => true, 'message' => 'Prêt entièrement remboursé !'];
        }
        
        return ['success' => true, 'message' => 'Remboursement enregistré'];
    }

    /**
     * Récupérer tous les prêts d'une tontine
     */
    public function getPrets($statut = null) {
        $query = "SELECT p.*, u.prenom, u.nom 
                  FROM prets p
                  JOIN membre_tontine mt ON p.membre_id = mt.id
                  JOIN users u ON mt.user_id = u.id
                  WHERE p.tontine_id = :tid";
        
        if($statut) {
            $query .= " AND p.statut = :statut";
            $stmt = $this->conn->prepare($query);
            $stmt->execute(['tid' => $this->id, 'statut' => $statut]);
        } else {
            $stmt = $this->conn->prepare($query);
            $stmt->execute(['tid' => $this->id]);
        }
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Récupérer les échéances d'un prêt
     */
    public function getEcheancesPret($pret_id) {
        $query = "SELECT * FROM echeances_prets WHERE pret_id = :pid ORDER BY numero_echeance ASC";
        $stmt = $this->conn->prepare($query);
        $stmt->execute(['pid' => $pret_id]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Vérifier les prêts en retard et appliquer des pénalités
     */
    public function verifierPretsEnRetard() {
        $aujourdhui = date('Y-m-d');
        
        $query = "SELECT p.*, e.id as echeance_id, e.numero_echeance, e.montant_du
                  FROM prets p
                  JOIN echeances_prets e ON p.id = e.pret_id
                  WHERE p.tontine_id = :tid 
                    AND p.statut = 'actif'
                    AND e.statut = 'en_attente'
                    AND e.date_echeance < :aujourdhui";
        $stmt = $this->conn->prepare($query);
        $stmt->execute(['tid' => $this->id, 'aujourdhui' => $aujourdhui]);
        $echeances_retard = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach($echeances_retard as $e) {
            $query = "UPDATE echeances_prets SET statut = 'retard' WHERE id = :id";
            $stmt = $this->conn->prepare($query);
            $stmt->execute(['id' => $e['echeance_id']]);
            
            // Appliquer une pénalité de retard (5% du montant dû)
            $penalite = round($e['montant_du'] * 0.05);
            $this->updateSoldeCaisse($penalite, 'ajout');
            $this->enregistrerOperation('penalite', $penalite, "Pénalité de retard - Prêt - Échéance n°" . $e['numero_echeance']);
        }
        
        return count($echeances_retard);
    }
    
}
?>