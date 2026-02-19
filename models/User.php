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
    public $password;
    public $role;
    public $created_at;

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
        // Requête SQL pour insérer un utilisateur
        $query = "INSERT INTO " . $this->table . "
                  SET
                    nom = :nom,
                    prenom = :prenom,
                    email = :email,
                    telephone = :telephone,
                    password = :password,
                    role = :role";
        
        // Préparer la requête (sécurité)
        $stmt = $this->conn->prepare($query);
        
        // Nettoyer les données (protéger contre les injections)
        $this->nom = htmlspecialchars(strip_tags($this->nom));
        $this->prenom = htmlspecialchars(strip_tags($this->prenom));
        $this->email = htmlspecialchars(strip_tags($this->email));
        $this->telephone = htmlspecialchars(strip_tags($this->telephone));
        $this->role = htmlspecialchars(strip_tags($this->role));
        
        // Hasher le mot de passe (sécurité)
        $this->password = password_hash($this->password, PASSWORD_DEFAULT);
        
        // Lier les paramètres à la requête
        $stmt->bindParam(":nom", $this->nom);
        $stmt->bindParam(":prenom", $this->prenom);
        $stmt->bindParam(":email", $this->email);
        $stmt->bindParam(":telephone", $this->telephone);
        $stmt->bindParam(":password", $this->password);
        $stmt->bindParam(":role", $this->role);
        
        // Exécuter la requête
        if($stmt->execute()) {
            return true;
        }
        
        // En cas d'erreur
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