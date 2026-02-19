<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();

// Vérifier si l'utilisateur est connecté
if(!isset($_SESSION['user_id'])) {
    header("Location: auth/login.php");
    exit();
}

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../models/User.php';
require_once __DIR__ . '/../models/MembreTontine.php';
require_once __DIR__ . '/../models/Cotisation.php';

$database = new Database();
$db = $database->getConnection();

$user = new User($db);
$user->getById($_SESSION['user_id']);

$membreTontine = new MembreTontine($db);
$cotisation = new Cotisation($db);

// Récupérer les tontines du membre
$tontinesMembre = $membreTontine->getTontinesByMembre($_SESSION['user_id']);

// Calculer le total des cotisations du membre
$totalCotise = 0;
$prochaineReunion = null;
$nomTontine = "";

echo "<!-- DÉBOGAGE MEMBRE ID: " . $_SESSION['user_id'] . " -->";

if($tontinesMembre && $tontinesMembre->rowCount() > 0) {
    echo "<!-- Membres trouvés dans " . $tontinesMembre->rowCount() . " tontines -->";
    
    while($tm = $tontinesMembre->fetch(PDO::FETCH_ASSOC)) {
        echo "<!-- Tontine: " . $tm['nom'] . " (ID: " . $tm['id'] . ") -->";
        
        // Récupérer l'ID du membre dans cette tontine
        $query = "SELECT id FROM membre_tontine 
                  WHERE user_id = :user_id AND tontine_id = :tontine_id";
        $stmt = $db->prepare($query);
        $stmt->execute([
            'user_id' => $_SESSION['user_id'],
            'tontine_id' => $tm['id']
        ]);
        
        if($stmt->rowCount() > 0) {
            $membre = $stmt->fetch(PDO::FETCH_ASSOC);
            echo "<!-- Membre tontine ID: " . $membre['id'] . " -->";
            
            // Vérifier les cotisations de ce membre
            $queryVerif = "SELECT * FROM cotisations WHERE membre_tontine_id = :membre_id";
            $stmtVerif = $db->prepare($queryVerif);
            $stmtVerif->execute(['membre_id' => $membre['id']]);
            echo "<!-- Cotisations trouvées: " . $stmtVerif->rowCount() . " -->";
            
            while($cot = $stmtVerif->fetch(PDO::FETCH_ASSOC)) {
                echo "<!-- Cotisation: " . $cot['montant'] . " F, Statut: " . $cot['statut'] . " -->";
            }
            
            // Calculer le total des cotisations payées
            $queryTotal = "SELECT SUM(montant) as total FROM cotisations 
                           WHERE membre_tontine_id = :membre_id AND statut = 'paye'";
            $stmtTotal = $db->prepare($queryTotal);
            $stmtTotal->execute(['membre_id' => $membre['id']]);
            $total = $stmtTotal->fetch(PDO::FETCH_ASSOC);
            $totalCotise += ($total['total'] ?? 0);
            echo "<!-- Total pour cette tontine: " . ($total['total'] ?? 0) . " -->";
            
            // Récupérer la prochaine réunion
            if(!$prochaineReunion) {
                $prochaineReunion = $tm['prochaine_reunion'];
                $nomTontine = $tm['nom'];
            }
        } else {
            echo "<!-- ERREUR: Membre non trouvé dans cette tontine -->";
        }
    }
    
    // Remettre le curseur au début pour l'affichage
    $tontinesMembre->execute();
} else {
    echo "<!-- Aucune tontine trouvée pour ce membre -->";
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tableau de bord - Tontine</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.8.1/font/bootstrap-icons.css">
    <style>
        body {
            background: #f8f9fa;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        .navbar {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .welcome-card {
            background: white;
            border-radius: 15px;
            padding: 30px;
            margin: 30px 0;
            box-shadow: 0 5px 20px rgba(0,0,0,0.05);
            border-left: 5px solid #667eea;
        }
        .stat-card {
            background: white;
            border-radius: 15px;
            padding: 25px;
            text-align: center;
            box-shadow: 0 5px 20px rgba(0,0,0,0.05);
            transition: transform 0.3s;
            height: 100%;
        }
        .stat-card:hover {
            transform: translateY(-5px);
        }
        .stat-icon {
            font-size: 40px;
            color: #667eea;
            margin-bottom: 15px;
        }
        .stat-number {
            font-size: 32px;
            font-weight: 600;
            color: #333;
            margin: 10px 0;
        }
        .stat-label {
            color: #666;
            font-size: 14px;
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        .section-title {
            margin: 40px 0 20px;
            font-weight: 600;
            color: #333;
            border-bottom: 2px solid #e0e0e0;
            padding-bottom: 10px;
        }
    </style>
</head>
<body>

    <!-- Barre de navigation -->
    <nav class="navbar navbar-expand-lg navbar-dark">
        <div class="container">
            <a class="navbar-brand" href="#">
                <i class="bi bi-bank2"></i> Tontine
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item">
                        <span class="nav-link">
                            <i class="bi bi-person-circle"></i> <?= htmlspecialchars($user->prenom . ' ' . $user->nom) ?>
                        </span>
                    </li>
                    <li class="nav-item">
                        <span class="nav-link">
                            <i class="bi bi-tag"></i> <?= $user->role == 'admin' ? 'Président' : 'Membre' ?>
                        </span>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="logout.php">
                            <i class="bi bi-box-arrow-right"></i> Déconnexion
                        </a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <!-- Contenu principal -->
    <div class="container mt-4">
        
        <!-- Message de bienvenue -->
        <div class="welcome-card">
            <h3>👋 Bonjour, <?= htmlspecialchars($user->prenom) ?> !</h3>
            <p class="mb-0">Bienvenue dans votre espace de gestion de tontine.</p>
        </div>

        <!-- Statistiques -->
        <div class="row mb-4">
            <div class="col-md-4 mb-3">
                <div class="stat-card">
                    <div class="stat-icon">
                        <i class="bi bi-people-fill"></i>
                    </div>
                    <div class="stat-number"><?= $tontinesMembre ? $tontinesMembre->rowCount() : 0 ?></div>
                    <div class="stat-label">Mes tontines</div>
                </div>
            </div>
            <div class="col-md-4 mb-3">
                <div class="stat-card">
                    <div class="stat-icon">
                        <i class="bi bi-cash-stack"></i>
                    </div>
                    <div class="stat-number"><?= number_format($totalCotise, 0, ',', ' ') ?> F</div>
                    <div class="stat-label">Cotisations versées</div>
                </div>
            </div>
            <div class="col-md-4 mb-3">
                <div class="stat-card">
                    <div class="stat-icon">
                        <i class="bi bi-calendar-check"></i>
                    </div>
                    <div class="stat-number">
                        <?php if($prochaineReunion): ?>
                            <?= date('d/m', strtotime($prochaineReunion)) ?>
                        <?php else: ?>
                            -
                        <?php endif; ?>
                    </div>
                    <div class="stat-label">Prochaine réunion</div>
                </div>
            </div>
        </div>

        <!-- Liste des tontines du membre -->
        <?php if($tontinesMembre && $tontinesMembre->rowCount() > 0): ?>
            <h4 class="section-title">
                <i class="bi bi-grid-3x3-gap-fill"></i> Mes tontines
            </h4>
            <div class="row">
                <?php while($tontine = $tontinesMembre->fetch(PDO::FETCH_ASSOC)): 
                    // Récupérer l'ID du membre dans cette tontine
                    $query = "SELECT id, ordre_tour FROM membre_tontine 
                              WHERE user_id = :user_id AND tontine_id = :tontine_id";
                    $stmt = $db->prepare($query);
                    $stmt->execute([
                        'user_id' => $_SESSION['user_id'],
                        'tontine_id' => $tontine['id']
                    ]);
                    $membreInfo = $stmt->fetch(PDO::FETCH_ASSOC);
                ?>
                    <div class="col-md-6 mb-4">
                        <div class="card h-100">
                            <div class="card-header bg-primary text-white">
                                <h5 class="mb-0"><?= htmlspecialchars($tontine['nom']) ?></h5>
                            </div>
                            <div class="card-body">
                                <p>
                                    <strong>💰 Montant:</strong> <?= number_format($tontine['montant_cotisation'], 0, ',', ' ') ?> F<br>
                                    <strong>🎯 Ton ordre:</strong> <?= $membreInfo['ordre_tour'] ?? 'Non défini' ?><br>
                                    <strong>📅 Prochaine réunion:</strong> <?= date('d/m/Y', strtotime($tontine['prochaine_reunion'])) ?>
                                </p>
                                <a href="tontine/voir_mes_cotisations.php?tontine_id=<?= $tontine['id'] ?>" 
                                   class="btn btn-outline-primary">
                                    Voir mes cotisations
                                </a>
                            </div>
                        </div>
                    </div>
                <?php endwhile; ?>
            </div>
        <?php else: ?>
            <div class="alert alert-info">
                Vous n'êtes membre d'aucune tontine pour le moment.
            </div>
        <?php endif; ?>

        <!-- Actions pour le président -->
        <?php if($user->role == 'admin'): ?>
            <h4 class="section-title">
                <i class="bi bi-gear-fill"></i> Actions Président
            </h4>
            <div class="row mb-4">
                <div class="col-md-3 mb-3">
                    <a href="tontine/create.php" class="btn btn-primary w-100">
                        <i class="bi bi-plus-circle"></i> Créer
                    </a>
                </div>
                <div class="col-md-3 mb-3">
                    <a href="tontine/mes_tontines.php" class="btn btn-outline-primary w-100">
                        <i class="bi bi-list-ul"></i> Mes tontines
                    </a>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>