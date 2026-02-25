<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();

if(!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

require_once __DIR__ . '/../../config/database.php';

$database = new Database();
$db = $database->getConnection();

// Récupérer toutes les tontines du membre
$query = "SELECT t.*, mt.ordre_tour, mt.date_adhesion,
                 (SELECT COUNT(*) FROM seances WHERE tontine_id = t.id) as nb_seances,
                 (SELECT COUNT(*) FROM amendes_appliquees a 
                  JOIN seances s ON a.seance_id = s.id 
                  WHERE s.tontine_id = t.id AND a.membre_tontine_id = mt.id AND a.est_paye = 0) as nb_amendes
          FROM membre_tontine mt
          JOIN tontines t ON mt.tontine_id = t.id
          WHERE mt.user_id = :user_id AND mt.est_actif = 1
          ORDER BY t.nom";

$stmt = $db->prepare($query);
$stmt->execute(['user_id' => $_SESSION['user_id']]);
$tontines = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Si une seule tontine, on y va directement
if(count($tontines) == 1) {
    $_SESSION['tontine_active'] = $tontines[0]['id'];
    header("Location: ../dashboard.php");
    exit();
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Choisir ma tontine</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.8.1/font/bootstrap-icons.css">
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            padding: 20px;
        }
        .container {
            max-width: 800px;
        }
        h2 {
            color: white;
            text-shadow: 2px 2px 4px rgba(0,0,0,0.2);
            margin-bottom: 30px;
        }
        .card {
            border-radius: 15px;
            border: none;
            box-shadow: 0 10px 40px rgba(0,0,0,0.2);
            transition: transform 0.3s, box-shadow 0.3s;
            margin-bottom: 20px;
        }
        .card:hover {
            transform: translateY(-5px);
            box-shadow: 0 15px 50px rgba(0,0,0,0.3);
        }
        .card-body {
            padding: 25px;
        }
        .badge-type {
            font-size: 14px;
            padding: 8px 15px;
            border-radius: 20px;
            background: #f0f0f0;
            color: #333;
        }
        .stat-item {
            display: inline-block;
            margin-right: 20px;
            color: #666;
        }
        .btn-select {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            border-radius: 10px;
            padding: 10px 25px;
            font-weight: 600;
            transition: all 0.3s;
            width: 100%;
            text-decoration: none;
            display: inline-block;
            text-align: center;
        }
        .btn-select:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 20px rgba(102, 126, 234, 0.4);
            color: white;
        }
        .alert {
            border-radius: 10px;
            border: none;
        }
    </style>
</head>
<body>
    <div class="container">
        <h2 class="text-center">Choisissez la tontine à consulter</h2>
        
        <?php if(empty($tontines)): ?>
            <div class="alert alert-info text-center">
                Vous n'êtes membre d'aucune tontine pour le moment.
            </div>
        <?php else: ?>
            <div class="row">
                <?php foreach($tontines as $t): ?>
                    <div class="col-md-6">
                        <div class="card">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-start mb-3">
                                    <h4 class="card-title mb-0">
                                        <?= htmlspecialchars($t['nom']) ?>
                                    </h4>
                                    <span class="badge-type">
                                        <?= $t['type_tontine'] ?>
                                    </span>
                                </div>
                                
                                <p class="text-muted mb-3">
                                    <?= htmlspecialchars($t['description'] ?: 'Aucune description') ?>
                                </p>
                                
                                <div class="mb-3">
                                    <span class="stat-item">
                                        Montant: <?= number_format($t['montant_cotisation'], 0, ',', ' ') ?> F
                                    </span>
                                    <span class="stat-item">
                                        Prochaine: <?= date('d/m/Y', strtotime($t['prochaine_reunion'])) ?>
                                    </span>
                                </div>
                                
                                <div class="mb-3">
                                    <span class="stat-item">
                                        Votre ordre: <strong><?= $t['ordre_tour'] ?></strong>
                                    </span>
                                    <?php if($t['nb_amendes'] > 0): ?>
                                        <span class="badge bg-danger">
                                            <?= $t['nb_amendes'] ?> amende(s) impayée(s)
                                        </span>
                                    <?php endif; ?>
                                </div>
                                
                                <a href="set_tontine.php?id=<?= $t['id'] ?>" class="btn-select">
                                    Accéder à cette tontine
                                </a>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>