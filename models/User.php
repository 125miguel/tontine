<?php
// Fichier: models/User.php
// But: Gérer toutes les opérations sur les utilisateurs

class User {
    // Connexion à la base de données
    private $conn;
    
    // Nom de la table dans la base
    private $table = "users";
    
    // Propriétés de l'utilisateur (colonnes dans la table)
    public $id;
    public $nom;
    public $prenom;
    public $email;
    public $telephone;
    public $adresse;
    public $password;
    public $role;
    public $created_at;
    public $nom_association;

    /**
     * Constructeur : s'exécute automatiquement quand on crée un objet User
     * @param $db La connexion à la base de données
     */
    public function __construct($db) {
        $this->conn = $db;
    }

    /**
     * Créer un nouvel utilisateur (inscription)
     * @return bool True si réussi, False si échec
     */
    public function create() {
        $query = "INSERT INTO " . $this->table . "
                (nom, prenom, nom_association, email, telephone, password, role, premiere_connexion)
                VALUES (:nom, :prenom, :nom_association, :email, :telephone, :password, :role, :premiere_connexion)";
        
        $stmt = $this->conn->prepare($query);
        
        $this->nom = htmlspecialchars(strip_tags($this->nom));
        $this->prenom = htmlspecialchars(strip_tags($this->prenom));
        $this->nom_association = htmlspecialchars(strip_tags($this->nom_association ?? ''));
        $this->email = htmlspecialchars(strip_tags($this->email));
        $this->telephone = htmlspecialchars(strip_tags($this->telephone));
        $this->role = htmlspecialchars(strip_tags($this->role));
        $this->password = password_hash($this->password, PASSWORD_DEFAULT);
        
        $stmt->bindParam(":nom", $this->nom);
        $stmt->bindParam(":prenom", $this->prenom);
        $stmt->bindParam(":nom_association", $this->nom_association);
        $stmt->bindParam(":email", $this->email);
        $stmt->bindParam(":telephone", $this->telephone);
        $stmt->bindParam(":password", $this->password);
        $stmt->bindParam(":role", $this->role);
        $stmt->bindParam(":premiere_connexion", $this->premiere_connexion);
        
        if($stmt->execute()) {
            return true;
        }
        return false;
    }
    /**
     * Vérifier si un email existe déjà
     * @param string $email L'email à vérifier
     * @return bool True si l'email existe, False sinon
     */
    public function emailExists($email) {
        $query = "SELECT id FROM " . $this->table . " WHERE email = :email";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":email", $email);
        $stmt->execute();
        
        return $stmt->rowCount() > 0;
    }

    /**
     * Connecter un utilisateur
     * @param string $email L'email de l'utilisateur
     * @param string $password Le mot de passe (non hashé)
     * @return bool True si connexion réussie
     */
    public function login($email, $password) {
        // Chercher l'utilisateur avec cet email
        $query = "SELECT * FROM " . $this->table . " WHERE email = :email LIMIT 1";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":email", $email);
        $stmt->execute();
        
        // Si l'utilisateur existe
        if($stmt->rowCount() > 0) {
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            
            // Vérifier le mot de passe
            if(password_verify($password, $row['password'])) {
                // Remplir les propriétés de l'objet
                $this->id = $row['id'];
                $this->nom = $row['nom'];
                $this->prenom = $row['prenom'];
                $this->email = $row['email'];
                $this->telephone = $row['telephone'];
                $this->role = $row['role'];
                $this->created_at = $row['created_at'];
                
                return true;
            }
        }
        
        return false;
    }
        /**
     * Connecter un utilisateur par téléphone
     */
    public function loginByPhone($telephone, $password) {
        $query = "SELECT * FROM " . $this->table . " WHERE telephone = :telephone LIMIT 1";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":telephone", $telephone);
        $stmt->execute();

        if($stmt->rowCount() > 0) {
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if(password_verify($password, $row['password'])) {
                $this->id = $row['id'];
                $this->nom = $row['nom'];
                $this->prenom = $row['prenom'];
                $this->nom_association = $row['nom_association'];
                $this->email = $row['email'];
                $this->telephone = $row['telephone'];
                $this->role = $row['role'];
                $this->premiere_connexion = $row['premiere_connexion'];
                return true;
            }
        }
        return false;
    }

    /**
     * Récupérer un utilisateur par son ID
     * @param int $id L'ID de l'utilisateur
     * @return bool True si trouvé
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
            $this->prenom = $row['prenom'];
            $this->nom_association = $row['nom_association'];
            $this->email = $row['email'];
            $this->telephone = $row['telephone'];
            $this->role = $row['role'];
            $this->created_at = $row['created_at'];
            
            return true;
        }
        
        return false;
    }
}
?>