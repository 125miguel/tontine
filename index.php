<?php
session_start();
if(isset($_SESSION['user_id'])) {
    header("Location: views/dashboard.php");
    exit();
}

// Connexion à la base de données pour récupérer les vrais avis
require_once 'config/database.php';
$database = new Database();
$db = $database->getConnection();

// Récupérer les avis approuvés
$query = "SELECT nom, role, note, message FROM avis WHERE approuve = 1 ORDER BY created_at DESC LIMIT 6";
$stmt = $db->prepare($query);
$stmt->execute();
$vrais_avis = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Si pas d'avis, utiliser les témoignages par défaut
if(empty($vrais_avis)) {
    $vrais_avis = [
        [
            'nom' => 'Marie N.',
            'role' => 'Présidente, Tontine des Mamans',
            'note' => 5,
            'message' => 'Depuis que j\'utilise TONTONTINE, la gestion de ma tontine est devenue un jeu d\'enfant. Fini les erreurs de calcul et les oublis !'
        ],
        [
            'nom' => 'Jean P.',
            'role' => 'Président, Djangui des Amis',
            'note' => 5,
            'message' => 'Le système d\'amendes automatiques nous a fait gagner un temps précieux. Mes membres apprécient la transparence.'
        ],
        [
            'nom' => 'Amadou T.',
            'role' => 'Président, Tontine Solidarité',
            'note' => 5,
            'message' => 'Interface intuitive, support réactif. Mes membres, même les moins technophiles, s\'y sont rapidement adaptés.'
        ]
    ];
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>TONTONTINE - Gérez vos tontines en toute simplicité</title>
    
    <!-- Bootstrap 5 -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Font Awesome 6 -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    
    <!-- AOS (Animate On Scroll) -->
    <link href="https://unpkg.com/aos@2.3.1/dist/aos.css" rel="stylesheet">
    
    <!-- Favicon -->
    <link rel="icon" type="image/png" href="assets/images/favicon.png">
    
    <style>
        :root {
            --violet: #6B46C1;
            --orange: #FF8A4C;
            --jaune: #FBBF24;
            --blanc: #F7F9FC;
            --gris: #2D3748;
            --violet-clair: #9F7AEA;
            --orange-clair: #FFB088;
        }
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Inter', sans-serif;
            color: var(--gris);
            overflow-x: hidden;
            background: linear-gradient(135deg, #fff5f0 0%, #f0e7ff 100%);
        }
        
        /* Navigation */
        .navbar {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            padding: 20px 0;
            box-shadow: 0 2px 20px rgba(0,0,0,0.05);
            transition: all 0.3s;
        }
        
        .navbar.scrolled {
            padding: 15px 0;
            background: white;
            box-shadow: 0 5px 30px rgba(107, 70, 193, 0.1);
        }
        
        .navbar-brand {
            font-size: 28px;
            font-weight: 800;
            background: linear-gradient(135deg, var(--violet) 0%, var(--orange) 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            letter-spacing: -0.5px;
        }
        
        .nav-link {
            color: var(--gris) !important;
            font-weight: 500;
            margin: 0 15px;
            transition: all 0.3s;
            position: relative;
        }
        
        .nav-link:after {
            content: '';
            position: absolute;
            bottom: -5px;
            left: 0;
            width: 0;
            height: 2px;
            background: linear-gradient(135deg, var(--violet) 0%, var(--orange) 100%);
            transition: width 0.3s;
        }
        
        .nav-link:hover:after {
            width: 100%;
        }
        
        .btn-nav {
            background: linear-gradient(135deg, var(--violet) 0%, var(--orange) 100%);
            color: white !important;
            padding: 10px 25px !important;
            border-radius: 50px;
            font-weight: 600;
            transition: all 0.3s !important;
        }
        
        .btn-nav:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 30px rgba(107, 70, 193, 0.3);
        }
        
        .btn-nav:after {
            display: none;
        }
        
        /* Hero Section */
        .hero {
            padding: 150px 0 100px;
            position: relative;
            overflow: hidden;
        }
        
        .hero-bg {
            position: absolute;
            top: 0;
            right: 0;
            width: 60%;
            height: 100%;
            background: linear-gradient(135deg, var(--violet-clair) 0%, var(--orange-clair) 100%);
            clip-path: polygon(100% 0, 0 0, 100% 100%);
            opacity: 0.1;
            z-index: -1;
        }
        
        .hero h1 {
            font-size: 56px;
            font-weight: 800;
            line-height: 1.2;
            margin-bottom: 20px;
        }
        
        .hero h1 span {
            background: linear-gradient(135deg, var(--violet) 0%, var(--orange) 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            display: inline-block;
        }
        
        .hero p {
            font-size: 18px;
            color: #666;
            margin-bottom: 30px;
            line-height: 1.8;
        }
        
        .hero-buttons {
            display: flex;
            gap: 20px;
            margin-bottom: 50px;
        }
        
        .btn-primary-custom {
            background: linear-gradient(135deg, var(--violet) 0%, var(--orange) 100%);
            color: white;
            padding: 15px 40px;
            border-radius: 50px;
            font-weight: 600;
            text-decoration: none;
            transition: all 0.3s;
            border: none;
            display: inline-block;
        }
        
        .btn-primary-custom:hover {
            transform: translateY(-3px);
            box-shadow: 0 20px 40px rgba(107, 70, 193, 0.3);
            color: white;
        }
        
        .btn-secondary-custom {
            background: white;
            color: var(--violet);
            padding: 15px 40px;
            border-radius: 50px;
            font-weight: 600;
            text-decoration: none;
            transition: all 0.3s;
            border: 2px solid var(--violet);
            display: inline-block;
        }
        
        .btn-secondary-custom:hover {
            background: var(--violet);
            color: white;
            transform: translateY(-3px);
            box-shadow: 0 20px 40px rgba(107, 70, 193, 0.2);
        }
        
        .hero-stats {
            display: flex;
            gap: 50px;
        }
        
        .stat-item h3 {
            font-size: 36px;
            font-weight: 700;
            color: var(--violet);
            margin-bottom: 5px;
        }
        
        .stat-item p {
            margin: 0;
            color: #888;
            font-size: 14px;
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        
        .hero-image {
            position: relative;
            animation: float 3s ease-in-out infinite;
        }
        
        @keyframes float {
            0% { transform: translateY(0px); }
            50% { transform: translateY(-20px); }
            100% { transform: translateY(0px); }
        }
        
        .hero-image img {
            width: 100%;
            border-radius: 20px;
            box-shadow: 0 30px 60px rgba(0,0,0,0.1);
        }
        
        /* Features Section */
        .features {
            padding: 100px 0;
            background: white;
        }
        
        .section-title {
            text-align: center;
            margin-bottom: 60px;
        }
        
        .section-title h2 {
            font-size: 42px;
            font-weight: 700;
            margin-bottom: 20px;
        }
        
        .section-title h2 span {
            background: linear-gradient(135deg, var(--violet) 0%, var(--orange) 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }
        
        .section-title p {
            font-size: 18px;
            color: #666;
            max-width: 700px;
            margin: 0 auto;
        }
        
        .feature-card {
            background: white;
            border-radius: 20px;
            padding: 40px 30px;
            text-align: center;
            box-shadow: 0 10px 40px rgba(0,0,0,0.05);
            transition: all 0.3s;
            height: 100%;
            border: 1px solid rgba(107, 70, 193, 0.1);
        }
        
        .feature-card:hover {
            transform: translateY(-10px);
            box-shadow: 0 20px 60px rgba(107, 70, 193, 0.15);
            border-color: var(--violet);
        }
        
        .feature-icon {
            width: 90px;
            height: 90px;
            background: linear-gradient(135deg, var(--violet) 0%, var(--orange) 100%);
            border-radius: 30px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 30px;
            color: white;
            font-size: 36px;
            transform: rotate(45deg);
            transition: all 0.3s;
        }
        
        .feature-icon i {
            transform: rotate(-45deg);
        }
        
        .feature-card:hover .feature-icon {
            transform: rotate(0deg);
            border-radius: 50%;
        }
        
        .feature-card h3 {
            font-size: 22px;
            font-weight: 600;
            margin-bottom: 15px;
        }
        
        .feature-card p {
            color: #666;
            margin: 0;
            line-height: 1.8;
        }
        
        /* Testimonials Section */
        .testimonials {
            padding: 100px 0;
            background: linear-gradient(135deg, var(--violet-clair) 0%, var(--orange-clair) 100%);
            position: relative;
            overflow: hidden;
        }
        
        .testimonials:before {
            content: '';
            position: absolute;
            top: -50px;
            left: -50px;
            width: 200px;
            height: 200px;
            background: rgba(255,255,255,0.1);
            border-radius: 50%;
        }
        
        .testimonials:after {
            content: '';
            position: absolute;
            bottom: -50px;
            right: -50px;
            width: 300px;
            height: 300px;
            background: rgba(255,255,255,0.1);
            border-radius: 50%;
        }
        
        .testimonial-card {
            background: white;
            border-radius: 20px;
            padding: 40px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.1);
            position: relative;
            z-index: 1;
            transition: all 0.3s;
        }
        
        .testimonial-card:hover {
            transform: scale(1.02);
        }
        
        .testimonial-rating {
            color: #FFD700;
            font-size: 20px;
            margin-bottom: 20px;
        }
        
        .testimonial-text {
            font-size: 16px;
            line-height: 1.8;
            color: #555;
            margin-bottom: 30px;
            font-style: italic;
        }
        
        .testimonial-author {
            display: flex;
            align-items: center;
        }
        
        .testimonial-avatar {
            width: 60px;
            height: 60px;
            background: linear-gradient(135deg, var(--violet) 0%, var(--orange) 100%);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 700;
            font-size: 20px;
            margin-right: 20px;
        }
        
        .testimonial-info h5 {
            margin: 0;
            font-weight: 600;
        }
        
        .testimonial-info p {
            margin: 0;
            color: #888;
            font-size: 14px;
        }
        
        /* Formulaire d'avis */
        .review-form {
            background: white;
            border-radius: 20px;
            padding: 50px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.1);
            margin-top: 50px;
        }
        
        .review-form h3 {
            font-size: 28px;
            font-weight: 700;
            margin-bottom: 30px;
            text-align: center;
        }
        
        .form-control, .form-select {
            border: 2px solid #e0e0e0;
            border-radius: 10px;
            padding: 12px 15px;
            transition: all 0.3s;
        }
        
        .form-control:focus, .form-select:focus {
            border-color: var(--violet);
            box-shadow: none;
            outline: none;
        }
        
        .rating-input {
            display: flex;
            flex-direction: row-reverse;
            justify-content: flex-end;
            gap: 5px;
            margin-bottom: 20px;
        }
        
        .rating-input input {
            display: none;
        }
        
        .rating-input label {
            font-size: 30px;
            color: #ddd;
            cursor: pointer;
            transition: all 0.3s;
        }
        
        .rating-input input:checked ~ label,
        .rating-input label:hover,
        .rating-input label:hover ~ label {
            color: #FFD700;
        }
        
        /* CTA Section */
        .cta {
            padding: 100px 0;
            background: white;
        }
        
        .cta-box {
            background: linear-gradient(135deg, var(--violet) 0%, var(--orange) 100%);
            border-radius: 30px;
            padding: 80px;
            text-align: center;
            color: white;
        }
        
        .cta-box h2 {
            font-size: 42px;
            font-weight: 700;
            margin-bottom: 20px;
        }
        
        .cta-box p {
            font-size: 18px;
            margin-bottom: 30px;
            opacity: 0.9;
        }
        
        .btn-cta {
            background: white;
            color: var(--violet);
            padding: 15px 50px;
            border-radius: 50px;
            font-weight: 600;
            text-decoration: none;
            display: inline-block;
            transition: all 0.3s;
            font-size: 18px;
        }
        
        .btn-cta:hover {
            transform: translateY(-3px);
            box-shadow: 0 20px 40px rgba(0,0,0,0.2);
            color: var(--orange);
        }
        
        /* Footer */
        footer {
            background: var(--gris);
            color: #999;
            padding: 60px 0 20px;
        }
        
        footer h5 {
            color: white;
            font-weight: 600;
            margin-bottom: 25px;
        }
        
        footer ul {
            list-style: none;
            padding: 0;
        }
        
        footer ul li {
            margin-bottom: 12px;
        }
        
        footer ul li a {
            color: #999;
            text-decoration: none;
            transition: color 0.3s;
        }
        
        footer ul li a:hover {
            color: white;
        }
        
        .social-links {
            display: flex;
            gap: 15px;
            margin-top: 20px;
        }
        
        .social-links a {
            width: 40px;
            height: 40px;
            background: rgba(255,255,255,0.1);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            transition: all 0.3s;
        }
        
        .social-links a:hover {
            background: linear-gradient(135deg, var(--violet) 0%, var(--orange) 100%);
            transform: translateY(-3px);
        }
        
        .copyright {
            text-align: center;
            margin-top: 40px;
            padding-top: 20px;
            border-top: 1px solid #444;
            color: #777;
        }
        
        /* Responsive */
        @media (max-width: 768px) {
            .hero h1 {
                font-size: 36px;
            }
            
            .hero-buttons {
                flex-direction: column;
            }
            
            .hero-stats {
                flex-direction: column;
                gap: 20px;
            }
            
            .section-title h2 {
                font-size: 32px;
            }
            
            .cta-box {
                padding: 40px 20px;
            }
            
            .cta-box h2 {
                font-size: 28px;
            }
        }
    </style>
</head>
<body>

    <!-- Messages de notification (en haut de la page) -->
    <?php if(isset($_SESSION['avis_success'])): ?>
        <div class="container mt-5 pt-5">
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <i class="fas fa-check-circle me-2"></i> <?= $_SESSION['avis_success'] ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        </div>
        <?php unset($_SESSION['avis_success']); ?>
    <?php endif; ?>

    <?php if(isset($_SESSION['avis_error'])): ?>
        <div class="container mt-5 pt-5">
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <i class="fas fa-exclamation-circle me-2"></i> <?= $_SESSION['avis_error'] ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        </div>
        <?php unset($_SESSION['avis_error']); ?>
    <?php endif; ?>

    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg fixed-top">
        <div class="container">
            <a class="navbar-brand" href="#">
                <i class="fas fa-hand-holding-usd me-2"></i>TONTONTINE
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item">
                        <a class="nav-link" href="#accueil">Accueil</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="#fonctionnalites">Fonctionnalités</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="#tarifs">Tarifs</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="#avis">Avis</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="views/auth/login.php">Connexion</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link btn-nav" href="views/auth/register.php">
                            <i class="fas fa-user-plus me-2"></i>S'inscrire
                        </a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <!-- Hero Section -->
    <section id="accueil" class="hero">
        <div class="hero-bg"></div>
        <div class="container">
            <div class="row align-items-center">
                <div class="col-lg-6" data-aos="fade-right">
                    <h1>
                        Gérez vos <span>tontines</span><br>
                        en toute <span>simplicité</span>
                    </h1>
                    <p>
                        TONTONTINE est la solution moderne pour gérer vos tontines, 
                        cotisations, amendes et rapports. Rejoignez des milliers de 
                        présidents qui nous font confiance.
                    </p>
                    <div class="hero-buttons">
                        <a href="views/auth/register.php" class="btn-primary-custom">
                            <i class="fas fa-rocket me-2"></i>Commencer gratuitement
                        </a>
                        <a href="#fonctionnalites" class="btn-secondary-custom">
                            <i class="fas fa-play me-2"></i>Voir la démo
                        </a>
                    </div>
                    <div class="hero-stats">
                        <div class="stat-item">
                            <h3>1000+</h3>
                            <p>Tontines créées</p>
                        </div>
                        <div class="stat-item">
                            <h3>15k+</h3>
                            <p>Membres actifs</p>
                        </div>
                        <div class="stat-item">
                            <h3>4.9</h3>
                            <p>Note moyenne</p>
                        </div>
                    </div>
                </div>
                <div class="col-lg-6" data-aos="fade-left">
                    <div class="hero-image">
                        <img src="assets/images/dashboard.jpg" alt="Dashboard TONTONTINE" class="img-fluid rounded-3 shadow-lg">
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Features Section -->
    <section id="fonctionnalites" class="features">
        <div class="container">
            <div class="section-title" data-aos="fade-up">
                <h2>Tout ce dont vous avez besoin dans <span>une seule application</span></h2>
                <p>Une solution complète pour gérer vos tontines en toute sérénité</p>
            </div>
            <div class="row g-4">
                <div class="col-lg-4 col-md-6" data-aos="zoom-in" data-aos-delay="100">
                    <div class="feature-card">
                        <div class="feature-icon">
                            <i class="fas fa-users"></i>
                        </div>
                        <h3>Gestion des membres</h3>
                        <p>Ajoutez et gérez facilement les membres de vos tontines. Suivez leurs cotisations en temps réel.</p>
                    </div>
                </div>
                <div class="col-lg-4 col-md-6" data-aos="zoom-in" data-aos-delay="200">
                    <div class="feature-card">
                        <div class="feature-icon">
                            <i class="fas fa-coins"></i>
                        </div>
                        <h3>Cotisations & Amendes</h3>
                        <p>Enregistrez les paiements, gérez les retards et appliquez automatiquement les amendes.</p>
                    </div>
                </div>
                <div class="col-lg-4 col-md-6" data-aos="zoom-in" data-aos-delay="300">
                    <div class="feature-card">
                        <div class="feature-icon">
                            <i class="fas fa-calendar-check"></i>
                        </div>
                        <h3>Séances & Bénéficiaires</h3>
                        <p>Organisez vos réunions, désignez les bénéficiaires et suivez l'ordre des tours.</p>
                    </div>
                </div>
                <div class="col-lg-4 col-md-6" data-aos="zoom-in" data-aos-delay="400">
                    <div class="feature-card">
                        <div class="feature-icon">
                            <i class="fas fa-file-pdf"></i>
                        </div>
                        <h3>Rapports détaillés</h3>
                        <p>Générez des rapports complets de chaque séance avec notes et export PDF.</p>
                    </div>
                </div>
                <div class="col-lg-4 col-md-6" data-aos="zoom-in" data-aos-delay="500">
                    <div class="feature-card">
                        <div class="feature-icon">
                            <i class="fas fa-bell"></i>
                        </div>
                        <h3>Notifications</h3>
                        <p>Recevez des rappels par email pour les réunions et les cotisations impayées.</p>
                    </div>
                </div>
                <div class="col-lg-4 col-md-6" data-aos="zoom-in" data-aos-delay="600">
                    <div class="feature-card">
                        <div class="feature-icon">
                            <i class="fas fa-shield-alt"></i>
                        </div>
                        <h3>Sécurisé</h3>
                        <p>Vos données sont protégées. Authentification sécurisée et mots de passe hashés.</p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Tarifs Section -->
    <section id="tarifs" class="features" style="background: linear-gradient(135deg, #f5f0ff 0%, #fff5f0 100%);">
        <div class="container">
            <div class="section-title" data-aos="fade-up">
                <h2>Des <span>tarifs</span> adaptés à vos besoins</h2>
                <p>Choisissez la formule qui correspond à votre tontine</p>
            </div>
            <div class="row g-4">
                <div class="col-lg-4" data-aos="flip-left" data-aos-delay="100">
                    <div class="feature-card">
                        <h3 style="font-size: 28px; margin-bottom: 20px;">Gratuit</h3>
                        <div style="font-size: 48px; font-weight: 700; color: var(--violet); margin-bottom: 20px;">0 FCFA</div>
                        <ul style="list-style: none; padding: 0; margin: 30px 0; text-align: left;">
                            <li style="margin-bottom: 15px;"><i class="fas fa-check-circle" style="color: var(--violet); margin-right: 10px;"></i> Jusqu'à 4 membres</li>
                            <li style="margin-bottom: 15px;"><i class="fas fa-check-circle" style="color: var(--violet); margin-right: 10px;"></i> Toutes les fonctionnalités</li>
                            <li style="margin-bottom: 15px;"><i class="fas fa-check-circle" style="color: var(--violet); margin-right: 10px;"></i> Rapports de séance</li>
                            <li style="margin-bottom: 15px;"><i class="fas fa-check-circle" style="color: var(--violet); margin-right: 10px;"></i> Gestion des amendes</li>
                            <li style="margin-bottom: 15px;"><i class="fas fa-check-circle" style="color: var(--violet); margin-right: 10px;"></i> Support par email</li>
                        </ul>
                        <a href="views/auth/register.php" class="btn-primary-custom" style="width: 100%;">Commencer</a>
                    </div>
                </div>
                <div class="col-lg-4" data-aos="flip-left" data-aos-delay="200">
                    <div class="feature-card" style="border: 2px solid var(--violet); transform: scale(1.05); position: relative;">
                        <div style="position: absolute; top: -15px; left: 50%; transform: translateX(-50%); background: linear-gradient(135deg, var(--violet) 0%, var(--orange) 100%); color: white; padding: 5px 30px; border-radius: 50px; font-weight: 600;">Populaire</div>
                        <h3 style="font-size: 28px; margin-bottom: 20px; margin-top: 20px;">Basic</h3>
                        <div style="font-size: 48px; font-weight: 700; color: var(--orange); margin-bottom: 20px;">5 000 FCFA <small style="font-size: 16px;">/mois</small></div>
                        <ul style="list-style: none; padding: 0; margin: 30px 0; text-align: left;">
                            <li style="margin-bottom: 15px;"><i class="fas fa-check-circle" style="color: var(--orange); margin-right: 10px;"></i> 5 à 15 membres</li>
                            <li style="margin-bottom: 15px;"><i class="fas fa-check-circle" style="color: var(--orange); margin-right: 10px;"></i> Toutes les fonctionnalités</li>
                            <li style="margin-bottom: 15px;"><i class="fas fa-check-circle" style="color: var(--orange); margin-right: 10px;"></i> Rapports PDF</li>
                            <li style="margin-bottom: 15px;"><i class="fas fa-check-circle" style="color: var(--orange); margin-right: 10px;"></i> Support prioritaire</li>
                            <li style="margin-bottom: 15px;"><i class="fas fa-check-circle" style="color: var(--orange); margin-right: 10px;"></i> Export des données</li>
                        </ul>
                        <a href="views/auth/register.php" class="btn-primary-custom" style="width: 100%;">Choisir Basic</a>
                    </div>
                </div>
                <div class="col-lg-4" data-aos="flip-left" data-aos-delay="300">
                    <div class="feature-card">
                        <h3 style="font-size: 28px; margin-bottom: 20px;">Pro</h3>
                        <div style="font-size: 48px; font-weight: 700; color: var(--violet); margin-bottom: 20px;">10 000 FCFA <small style="font-size: 16px;">/mois</small></div>
                        <ul style="list-style: none; padding: 0; margin: 30px 0; text-align: left;">
                            <li style="margin-bottom: 15px;"><i class="fas fa-check-circle" style="color: var(--violet); margin-right: 10px;"></i> 16 à 50 memb
                            </li>
                            <li style="margin-bottom: 15px;"><i class="fas fa-check-circle" style="color: var(--violet); margin-right: 10px;"></i> Toutes les fonctionnalités</li>
                            <li style="margin-bottom: 15px;"><i class="fas fa-check-circle" style="color: var(--violet); margin-right: 10px;"></i> Support VIP</li>
                            <li style="margin-bottom: 15px;"><i class="fas fa-check-circle" style="color: var(--violet); margin-right: 10px;"></i> Formation incluse</li>
                            <li style="margin-bottom: 15px;"><i class="fas fa-check-circle" style="color: var(--violet); margin-right: 10px;"></i> Personnalisation</li>
                        </ul>
                        <a href="views/auth/register.php" class="btn-primary-custom" style="width: 100%;">Choisir Pro</a>
                    </div>
                </div>
            </div>
            <p class="text-center mt-4 text-muted">
                <i class="fas fa-gift me-2"></i>Offre annuelle : 2 mois offerts
            </p>
        </div>
    </section>

    <!-- Testimonials Section -->
    <section id="avis" class="testimonials">
        <div class="container">
            <div class="section-title" data-aos="fade-up" style="color: white;">
                <h2 style="color: white;">Ce que disent nos <span style="color: var(--jaune);">utilisateurs</span></h2>
                <p style="color: rgba(255,255,255,0.8);">Ils nous font confiance</p>
            </div>
            <div class="row g-4">
                <?php 
                $index = 0;
                foreach($vrais_avis as $avis): 
                    $avatar = strtoupper(substr($avis['nom'], 0, 2));
                    $delay = $index * 100;
                ?>
                <div class="col-lg-4" data-aos="fade-up" data-aos-delay="<?= $delay ?>">
                    <div class="testimonial-card">
                        <div class="testimonial-rating">
                            <?php for($i=0; $i<$avis['note']; $i++): ?>
                                <i class="fas fa-star"></i>
                            <?php endfor; ?>
                        </div>
                        <p class="testimonial-text">"<?= htmlspecialchars($avis['message']) ?>"</p>
                        <div class="testimonial-author">
                            <div class="testimonial-avatar"><?= $avatar ?></div>
                            <div class="testimonial-info">
                                <h5><?= htmlspecialchars($avis['nom']) ?></h5>
                                <p><?= htmlspecialchars($avis['role']) ?></p>
                            </div>
                        </div>
                    </div>
                </div>
                <?php 
                    $index++;
                endforeach; 
                ?>
            </div>

            <!-- Formulaire d'avis -->
            <div class="review-form" data-aos="fade-up">
                <h3>Partagez votre expérience</h3>
                <form action="traitement_avis.php" method="POST">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <input type="text" name="nom" class="form-control" placeholder="Votre nom" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <input type="email" name="email" class="form-control" placeholder="Votre email" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <input type="text" name="role" class="form-control" placeholder="Votre rôle (ex: Président...)" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <div class="rating-input">
                                <input type="radio" name="note" value="5" id="star5"><label for="star5"><i class="fas fa-star"></i></label>
                                <input type="radio" name="note" value="4" id="star4"><label for="star4"><i class="fas fa-star"></i></label>
                                <input type="radio" name="note" value="3" id="star3"><label for="star3"><i class="fas fa-star"></i></label>
                                <input type="radio" name="note" value="2" id="star2"><label for="star2"><i class="fas fa-star"></i></label>
                                <input type="radio" name="note" value="1" id="star1"><label for="star1"><i class="fas fa-star"></i></label>
                            </div>
                        </div>
                        <div class="col-12 mb-3">
                            <textarea name="message" class="form-control" rows="5" placeholder="Votre message..." required></textarea>
                        </div>
                        <div class="col-12 text-center">
                            <button type="submit" class="btn-primary-custom">
                                <i class="fas fa-paper-plane me-2"></i>Envoyer mon avis
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </section>

    <!-- CTA Section -->
    <section class="cta">
        <div class="container">
            <div class="cta-box" data-aos="zoom-in">
                <h2>Prêt à simplifier votre tontine ?</h2>
                <p>Rejoignez des milliers de présidents qui nous font confiance</p>
                <a href="views/auth/register.php" class="btn-cta">
                    <i class="fas fa-rocket me-2"></i>Commencer gratuitement
                </a>
                <p class="mt-3" style="opacity: 0.8; font-size: 14px;">
                    <i class="fas fa-check-circle me-1"></i>15 jours d'essai gratuit 
                    <i class="fas fa-check-circle ms-3 me-1"></i>Sans carte bancaire
                </p>
            </div>
        </div>
    </section>

    <!-- Footer -->
    <footer>
        <div class="container">
            <div class="row">
                <div class="col-md-4 mb-4">
                    <h5><i class="fas fa-hand-holding-usd me-2"></i>TONTONTINE</h5>
                    <p>La solution moderne pour gérer vos tontines en toute simplicité.</p>
                    <div class="social-links">
                        <a href="#"><i class="fab fa-facebook-f"></i></a>
                        <a href="#"><i class="fab fa-twitter"></i></a>
                        <a href="#"><i class="fab fa-linkedin-in"></i></a>
                        <a href="#"><i class="fab fa-instagram"></i></a>
                    </div>
                </div>
                <div class="col-md-2 mb-4">
                    <h5>Liens</h5>
                    <ul>
                        <li><a href="#accueil">Accueil</a></li>
                        <li><a href="#fonctionnalites">Fonctionnalités</a></li>
                        <li><a href="#tarifs">Tarifs</a></li>
                        <li><a href="#avis">Avis</a></li>
                    </ul>
                </div>
                <div class="col-md-2 mb-4">
                    <h5>Légal</h5>
                    <ul>
                        <li><a href="#">Mentions légales</a></li>
                        <li><a href="#">CGV</a></li>
                        <li><a href="#">Confidentialité</a></li>
                    </ul>
                </div>
                <div class="col-md-4 mb-4">
                    <h5>Contact</h5>
                    <ul>
                        <li><i class="fas fa-envelope me-2"></i> contact@tontontine.com</li>
                        <li><i class="fas fa-phone me-2"></i> +237 6XX XXX XXX</li>
                        <li><i class="fas fa-map-marker-alt me-2"></i> Yaoundé, Cameroun</li>
                    </ul>
                </div>
            </div>
            <div class="copyright">
                &copy; 2025 TONTONTINE. Tous droits réservés. Créé avec <i class="fas fa-heart" style="color: var(--orange);"></i> pour les tontines.
            </div>
        </div>
    </footer>

    <!-- Scripts -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://unpkg.com/aos@2.3.1/dist/aos.js"></script>
    <script>
        AOS.init({
            duration: 1000,
            once: true
        });

        // Navbar scroll effect
        window.addEventListener('scroll', function() {
            if (window.scrollY > 50) {
                document.querySelector('.navbar').classList.add('scrolled');
            } else {
                document.querySelector('.navbar').classList.remove('scrolled');
            }
        });
    </script>
</body>
</html>