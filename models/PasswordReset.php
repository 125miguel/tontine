<?php
class PasswordReset {
    private $conn;
    private $table = "password_resets";

    public function __construct($db) {
        $this->conn = $db;
    }

    /**
     * Créer un token de réinitialisation
     */
    public function createToken($user_id) {
        // Générer un token aléatoire
        $token = bin2hex(random_bytes(32));
        $expire_at = date('Y-m-d H:i:s', strtotime('+1 hour'));
        
        $query = "INSERT INTO " . $this->table . " 
                  (user_id, token, expire_at)
                  VALUES (:user_id, :token, :expire_at)";
        
        $stmt = $this->conn->prepare($query);
        
        if($stmt->execute([
            'user_id' => $user_id,
            'token' => $token,
            'expire_at' => $expire_at
        ])) {
            return $token;
        }
        return false;
    }

    /**
     * Vérifier si un token est valide
     */
    public function verifyToken($token) {
        $query = "SELECT * FROM " . $this->table . " 
                  WHERE token = :token AND used = 0 AND expire_at > NOW()
                  ORDER BY id DESC LIMIT 1";
        
        $stmt = $this->conn->prepare($query);
        $stmt->execute(['token' => $token]);
        
        if($stmt->rowCount() > 0) {
            return $stmt->fetch(PDO::FETCH_ASSOC);
        }
        return false;
    }

    /**
     * Marquer un token comme utilisé
     */
    public function markAsUsed($id) {
        $query = "UPDATE " . $this->table . " SET used = 1 WHERE id = :id";
        $stmt = $this->conn->prepare($query);
        return $stmt->execute(['id' => $id]);
    }
    /**
     * Créer un code de réinitialisation à 6 chiffres
     */
    public function createCode($user_id) {
        // Générer un code aléatoire à 6 chiffres
        $code = str_pad(rand(0, 999999), 6, '0', STR_PAD_LEFT);
        $expire_at = date('Y-m-d H:i:s', strtotime('+15 minutes'));
        
        $query = "INSERT INTO " . $this->table . " 
                (user_id, code, expire_at)
                VALUES (:user_id, :code, :expire_at)";
        
        $stmt = $this->conn->prepare($query);
        
        if($stmt->execute([
            'user_id' => $user_id,
            'code' => $code,
            'expire_at' => $expire_at
        ])) {
            return $code;
        }
        return false;
    }

    /**
     * Vérifier si un code est valide
     */
    public function verifyCode($email, $code) {
        $query = "SELECT pr.* FROM " . $this->table . " pr
                JOIN users u ON pr.user_id = u.id
                WHERE u.email = :email AND pr.code = :code 
                AND pr.used = 0 AND pr.expire_at > NOW()
                ORDER BY pr.id DESC LIMIT 1";
        
        $stmt = $this->conn->prepare($query);
        $stmt->execute([
            'email' => $email,
            'code' => $code
        ]);
        
        if($stmt->rowCount() > 0) {
            return $stmt->fetch(PDO::FETCH_ASSOC);
        }
        return false;
    }
}
?>