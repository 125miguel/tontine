<?php
// Fichier: test_user.php
// But: Tester la création d'un utilisateur

// Afficher les erreurs pour le debug
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Inclure les fichiers nécessaires
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/models/User.php';

echo "<h2>🔬 TEST DU MODÈLE USER</h2>";

// 1. Connexion à la base
echo "<h3>1. Connexion à la base :</h3>";
$database = new Database();
$db = $database->getConnection();

if($db) {
    echo "✅ Connexion OK<br>";
} else {
    echo "❌ Connexion échouée<br>";
    exit;
}

// 2. Création d'un utilisateur de test
echo "<h3>2. Création d'un utilisateur :</h3>";

$user = new User($db);
$user->nom = "NGONO";
$user->prenom = "Marie";
$user->email = "marie@test.com";
$user->telephone = "691234567";
$user->password = "password123";
$user->role = "admin";

if($user->create()) {
    echo "✅ Utilisateur créé avec succès !<br>";
    echo "Nom : " . $user->nom . "<br>";
    echo "Email : " . $user->email . "<br>";
    echo "Rôle : " . $user->role . "<br>";
} else {
    echo "❌ Erreur lors de la création<br>";
}

// 3. Test de connexion
echo "<h3>3. Test de connexion :</h3>";

$login = $user->login("marie@test.com", "password123");

if($login) {
    echo "✅ Connexion réussie !<br>";
    echo "Bienvenue " . $user->prenom . " " . $user->nom . "<br>";
} else {
    echo "❌ Échec de connexion<br>";
}

// 4. Vérification si l'email existe
echo "<h3>4. Test emailExists :</h3>";

if($user->emailExists("marie@test.com")) {
    echo "✅ L'email existe bien<br>";
} else {
    echo "❌ L'email n'existe pas<br>";
}

// 5. Récupération par ID
echo "<h3>5. Test getById :</h3>";

if($user->getById(1)) {
    echo "✅ Utilisateur trouvé : " . $user->prenom . " " . $user->nom . "<br>";
} else {
    echo "❌ Utilisateur non trouvé<br>";
}

echo "<hr>";
echo "🎉 Tests terminés ! Vérifie dans phpMyAdmin que l'utilisateur a bien été créé.";
?>