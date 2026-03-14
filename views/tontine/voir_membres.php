<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();

if(!isset($_SESSION['user_id']) || $_SESSION['association_role'] != 'admin') {
    header("Location: ../auth/login.php");
    exit();
}

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../models/Tontine.php';
require_once __DIR__ . '/../../models/MembreTontine.php';

$retire = $_GET['retire'] ?? 0;
$error = $_GET['error'] ?? 0;
$desactive = $_GET['desactive'] ?? 0;
$supprime = $_GET['supprime'] ?? 0;
$error_activites = $_GET['error'] ?? 0;
$reset = $_GET['reset'] ?? 0;
$melange = $_GET['melange'] ?? 0;
$ordre_genere = $_GET['ordre_genere'] ?? 0;

$database = new Database();
$db = $database->getConnection();

$tontine_id = $_GET['id'] ?? 0;

// Vérifier que la tontine appartient bien à cet admin
$tontine = new Tontine($db);
if(!$tontine->getById($tontine_id) || $tontine->admin_id != $_SESSION['user_id']) {
    header("Location: mes_tontines.php");
    exit();
}

// Vérifier le mode de la tontine
$mode_auto = ($tontine->mode_beneficiaire == 'auto');

// Vérifier si l'ordre final a déjà été généré
$query = "SELECT COUNT(*) as nb FROM membre_tontine 
          WHERE tontine_id = :tid AND ordre_final IS NOT NULL";
$stmt = $db->prepare($query);
$stmt->execute(['tid' => $tontine_id]);
$ordre_final_existe = $stmt->fetch()['nb'] > 0;

$membreTontine = new MembreTontine($db);

// Récupérer les membres avec leur adresse
$query = "SELECT m.*, u.nom, u.prenom, u.email, u.telephone, u.adresse 
          FROM membre_tontine m
          JOIN users u ON m.user_id = u.id
          WHERE m.tontine_id = :tontine_id
          ORDER BY COALESCE(m.ordre_final, m.ordre_tour) ASC";
$stmt = $db->prepare($query);
$stmt->execute(['tontine_id' => $tontine_id]);
$membres = $stmt;
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Membres de la tontine - <?= htmlspecialchars($tontine->nom) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.8.1/font/bootstrap-icons.css">
    <style>
        :root {
            --primary: #1E3A8A;        /* Bleu sombre */
            --primary-light: #3B5BA5;   /* Bleu plus clair */
            --white: #FFFFFF;
            --bg-light: #F8FAFC;
            --text-dark: #0F172A;
            --text-light: #475569;
            --border: #E2E8F0;
            --success: #10B981;
            --warning: #F59E0B;
            --danger: #EF4444;
            --info: #3B82F6;
        }
        
        body {
            background: var(--bg-light);
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        .navbar {
            background: var(--primary);
        }
        
        .navbar-brand, .nav-link {
            color: var(--white) !important;
        }
        
        .card {
            border-radius: 15px;
            border: 1px solid var(--border);
            box-shadow: 0 5px 20px rgba(0,0,0,0.05);
        }
        
        .card-header {
            background: var(--primary);
            color: var(--white);
            border-radius: 15px 15px 0 0 !important;
        }
        
        .btn-primary {
            background: var(--primary);
            border: none;
        }
        
        .btn-primary:hover {
            background: var(--primary-light);
        }
        
        .btn-success {
            background: var(--success);
            border: none;
        }
        
        .btn-success:hover {
            background: #0E9F6E;
        }
        
        .btn-warning {
            background: var(--warning);
            border: none;
            color: var(--white);
        }
        
        .btn-warning:hover {
            background: #D97706;
        }
        
        .btn-danger {
            background: var(--danger);
            border: none;
        }
        
        .btn-danger:hover {
            background: #DC2626;
        }
        
        .btn-outline-primary {
            border: 2px solid var(--primary);
            color: var(--primary);
            background: transparent;
        }
        
        .btn-outline-primary:hover {
            background: var(--primary);
            color: var(--white);
        }
        
        .btn-outline-warning {
            border: 2px solid var(--warning);
            color: var(--warning);
            background: transparent;
        }
        
        .btn-outline-warning:hover {
            background: var(--warning);
            color: var(--white);
        }
        
        .btn-outline-danger {
            border: 2px solid var(--danger);
            color: var(--danger);
            background: transparent;
        }
        
        .btn-outline-danger:hover {
            background: var(--danger);
            color: var(--white);
        }
        
        .badge-actif {
            background: var(--success);
            color: var(--white);
            padding: 5px 10px;
            border-radius: 20px;
            font-size: 12px;
        }
        
        .badge-inactif {
            background: var(--text-light);
            color: var(--white);
            padding: 5px 10px;
            border-radius: 20px;
            font-size: 12px;
        }
        
        .badge-ordre-final {
            background: var(--success);
            color: var(--white);
            padding: 8px 12px;
            border-radius: 50%;
            font-size: 16px;
            font-weight: 700;
            width: 40px;
            height: 40px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
        }
        
        .badge-ordre-temp {
            background: var(--warning);
            color: var(--white);
            padding: 8px 12px;
            border-radius: 50%;
            font-size: 16px;
            font-weight: 700;
            width: 40px;
            height: 40px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
        }
        
        .alert {
            border-radius: 10px;
            border: none;
        }
        
        .alert-success {
            background: #D1FAE5;
            color: #065F46;
        }
        
        .alert-danger {
            background: #FEE2E2;
            color: #991B1B;
        }
        
        .alert-warning {
            background: #FEF3C7;
            color: #92400E;
        }
        
        .alert-info {
            background: #DBEAFE;
            color: var(--primary);
            border: none;
        }
        
        .alert-info .badge {
            background: var(--primary) !important;
            color: var(--white) !important;
            font-size: 18px;
            padding: 8px 15px;
        }
        
        .table th {
            background: var(--primary);
            color: var(--white);
            font-weight: 600;
        }
        
        .table td {
            vertical-align: middle;
        }
        
        .association-badge {
            background: rgba(255,255,255,0.2);
            padding: 5px 15px;
            border-radius: 50px;
            font-size: 14px;
            color: var(--white);
        }
    </style>
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark">
        <div class="container">
            <a class="navbar-brand" href="../dashboard.php">
                <i class="bi bi-bank2"></i> TONTONTINE
            </a>
            <div class="navbar-nav ms-auto">
                <span class="nav-link">
                    <i class="bi bi-building"></i> <?= htmlspecialchars($_SESSION['association_nom']) ?>
                </span>
                <a class="nav-link" href="ajouter_membre.php?id=<?= $tontine_id ?>">
                    <i class="bi bi-person-plus"></i> Ajouter
                </a>
                <a class="nav-link" href="mes_tontines.php">
                    <i class="bi bi-arrow-left"></i> Retour
                </a>
            </div>
        </div>
    </nav>

    <div class="container mt-5">
        
        <!-- Messages de confirmation -->
        <?php if($retire == 1): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <i class="bi bi-check-circle-fill me-2"></i>  Membre retiré avec succès !
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <?php if($desactive == 1): ?>
            <div class="alert alert-warning alert-dismissible fade show" role="alert">
                <i class="bi bi-person-x-fill me-2"></i>  Membre désactivé avec succès (données conservées).
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <?php if($supprime == 1): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <i class="bi bi-trash-fill me-2"></i>  Membre supprimé définitivement.
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <?php if($error == 1): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <i class="bi bi-exclamation-triangle-fill me-2"></i>  Erreur lors du retrait du membre.
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <?php if($error_activites == 'activites'): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <i class="bi bi-exclamation-triangle-fill me-2"></i>  Impossible de supprimer : ce membre a déjà des activités (cotisations, amendes, bénéficiaire).
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <?php if($melange == 1): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <i class="bi bi-shuffle"></i>  Ordre des bénéficiaires mélangé avec succès !
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <?php if($ordre_genere == 1): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <i class="bi bi-check-circle-fill"></i>  Ordre définitif des bénéficiaires généré avec succès !
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <?php if($reset == 1 && isset($_SESSION['reset_password'])): ?>
            <div class="alert alert-info alert-dismissible fade show" role="alert">
                <h5 class="alert-heading"><i class="bi bi-key-fill"></i>  Nouveau mot de passe généré</h5>
                <p>
                    <strong>Membre :</strong> <?= htmlspecialchars($_SESSION['reset_user']) ?><br>
                    <strong>Nouveau mot de passe :</strong> 
                    <span class="badge bg-dark fs-5 p-2"><?= $_SESSION['reset_password'] ?></span>
                </p>
                <p class="mb-0">
                    <small> À communiquer au membre. Il devra le changer à sa prochaine connexion.</small>
                </p>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
            <?php unset($_SESSION['reset_password']); ?>
            <?php unset($_SESSION['reset_user']); ?>
        <?php endif; ?>

        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2><i class="bi bi-people-fill"></i> Membres de "<?= htmlspecialchars($tontine->nom) ?>"</h2>
            <div>
                <?php if($mode_auto && !$ordre_final_existe): ?>
                    <a href="generer_ordre_final.php?id=<?= $tontine_id ?>" 
                       class="btn btn-warning me-2"
                       onclick="return confirm('Générer l\'ordre définitif des bénéficiaires ?\nCette action est irréversible.')">
                        <i class="bi bi-shuffle"></i> Générer l'ordre final
                    </a>
                <?php endif; ?>
                <a href="ajouter_membre.php?id=<?= $tontine_id ?>" class="btn btn-success">
                    <i class="bi bi-person-plus-fill"></i> Ajouter un membre
                </a>
            </div>
        </div>

        <?php if($membres->rowCount() == 0): ?>
            <div class="alert alert-info">
                <i class="bi bi-info-circle-fill me-2"></i> Aucun membre dans cette tontine pour le moment.
                <a href="ajouter_membre.php?id=<?= $tontine_id ?>" class="alert-link">Ajouter votre premier membre</a>
            </div>
        <?php else: ?>
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0"><i class="bi bi-list-ul"></i> Liste des membres</h5>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead>
                                <tr>
                                    <th class="text-center">#</th>
                                    <th>Membre</th>
                                    <th>Contact</th>
                                    <th>Email</th>
                                    <th>Adresse</th>
                                    <th class="text-center">Ordre</th>
                                    <th class="text-center">Statut</th>
                                    <th class="text-center">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php 
                                $compteur = 1;
                                while($m = $membres->fetch(PDO::FETCH_ASSOC)): 
                                    $activites = $membreTontine->aDesActivites($m['id']);
                                    $ordre_affiche = $m['ordre_final'] ?? $m['ordre_tour'];
                                    $classe_ordre = $m['ordre_final'] ? 'badge-ordre-final' : 'badge-ordre-temp';
                                ?>
                                    <tr>
                                        <td class="text-center"><?= $compteur++ ?></td>
                                        <td>
                                            <strong><?= htmlspecialchars($m['prenom'] . ' ' . $m['nom']) ?></strong>
                                        </td>
                                        <td><?= htmlspecialchars($m['telephone']) ?></td>
                                        <td><?= htmlspecialchars($m['email']) ?></td>
                                        <td><?= htmlspecialchars($m['adresse'] ?? '-') ?></td>
                                        <td class="text-center">
                                            <span class="<?= $classe_ordre ?>">#<?= $ordre_affiche ?></span>
                                        </td>
                                        <td class="text-center">
                                            <?php if($m['est_actif']): ?>
                                                <span class="badge-actif"><i class="bi bi-check-circle"></i> Actif</span>
                                            <?php else: ?>
                                                <span class="badge-inactif"><i class="bi bi-slash-circle"></i> Inactif</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="text-center">
                                            <?php if($m['est_actif']): ?>
                                                <!-- Réinitialiser mot de passe -->
                                                <a href="reset_mdp_membre.php?id=<?= $m['id'] ?>&tontine_id=<?= $tontine_id ?>" 
                                                   class="btn btn-outline-primary btn-sm"
                                                   onclick="return confirm('Générer un nouveau mot de passe pour <?= htmlspecialchars($m['prenom'] . ' ' . $m['nom']) ?> ?')"
                                                   title="Réinitialiser le mot de passe">
                                                    <i class="bi bi-key"></i>
                                                </a>
                                                
                                                <?php if($activites): ?>
                                                    <!-- Désactiver seulement (a des activités) -->
                                                    <a href="desactiver_membre.php?id=<?= $m['id'] ?>&tontine_id=<?= $tontine_id ?>" 
                                                       class="btn btn-outline-warning btn-sm"
                                                       onclick="return confirm('Désactiver <?= htmlspecialchars($m['prenom'] . ' ' . $m['nom']) ?> ?\nSes données seront conservées.')"
                                                       title="Désactiver (conserve l'historique)">
                                                        <i class="bi bi-person-x"></i>
                                                    </a>
                                                <?php else: ?>
                                                    <!-- Supprimer définitivement (pas d'activités) -->
                                                    <a href="supprimer_membre.php?id=<?= $m['id'] ?>&tontine_id=<?= $tontine_id ?>" 
                                                       class="btn btn-outline-danger btn-sm"
                                                       onclick="return confirm('Supprimer définitivement <?= htmlspecialchars($m['prenom'] . ' ' . $m['nom']) ?> ?\nCette action est irréversible.')"
                                                       title="Supprimer définitivement">
                                                        <i class="bi bi-trash"></i>
                                                    </a>
                                                <?php endif; ?>
                                            <?php else: ?>
                                                <span class="text-muted">—</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <div class="mt-4">
                <p class="text-muted">
                    <strong><i class="bi bi-people"></i> Total membres:</strong> <?= $membres->rowCount() ?><br>
                    <strong><i class="bi bi-cash-stack"></i> Montant cotisation:</strong> <?= number_format($tontine->montant_cotisation, 0, ',', ' ') ?> FCFA<br>
                    <strong><i class="bi bi-calculator"></i> Total par réunion:</strong> <?= number_format($tontine->montant_cotisation * $membres->rowCount(), 0, ',', ' ') ?> FCFA
                </p>
            </div>
        <?php endif; ?>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>