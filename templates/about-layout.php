<?php

$title = $title ?? 'À propos - EasyTravel';
$h = static fn (mixed $value): string => htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $h($title) ?></title>
    <link rel="icon" type="image/png" href="/assets/java/trans_bg.png">
    <link rel="stylesheet" href="/about.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
            background-color: #0B3C5D;
            overflow-x: hidden;
        }
        
        /* Navbar */
        .about-navbar {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            z-index: 1000;
            background-color: transparent;
            padding: 20px 40px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            transition: all 0.3s ease;
        }
        
        .about-navbar.scrolled {
            background-color: white;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
        }
        
        .about-navbar-logo {
            color: white;
            font-size: 24px;
            font-weight: bold;
            text-decoration: none;
            transition: color 0.3s ease;
        }
        
        .about-navbar.scrolled .about-navbar-logo {
            color: #0B3C5D;
        }
        
        .about-navbar-menu {
            display: flex;
            gap: 30px;
            list-style: none;
        }
        
        .about-navbar-menu a {
            color: white;
            text-decoration: none;
            font-size: 16px;
            font-weight: 500;
            transition: color 0.3s ease;
        }
        
        .about-navbar.scrolled .about-navbar-menu a {
            color: #0B3C5D;
        }
        
        .about-navbar-menu a:hover,
        .about-navbar-menu a.active {
            color: #F4A261;
        }
        
        .about-navbar-cta {
            background: linear-gradient(to right, #F4A261, #E76F51);
            color: white;
            padding: 10px 25px;
            border-radius: 25px;
            text-decoration: none;
            font-weight: bold;
            transition: transform 0.3s ease;
        }
        
        .about-navbar-cta:hover {
            transform: scale(1.05);
        }
        
        /* Footer */
        .about-footer {
            background: linear-gradient(to bottom, #0a0a0a, #0B3C5D, #000000);
            color: white;
            padding: 60px 40px 30px 40px;
        }
        
        .about-footer-content {
            max-width: 1200px;
            margin: 0 auto;
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 40px;
            margin-bottom: 40px;
        }
        
        .about-footer-section h3 {
            color: #cfb07a;
            font-size: 20px;
            margin-bottom: 15px;
        }
        
        .about-footer-section p,
        .about-footer-section a {
            color: rgba(255, 255, 255, 0.7);
            font-size: 14px;
            line-height: 1.8;
            text-decoration: none;
            display: block;
            margin-bottom: 8px;
        }
        
        .about-footer-section a:hover {
            color: #F4A261;
        }
        
        .about-footer-social {
            display: flex;
            gap: 12px;
            margin-top: 15px;
        }
        
        .about-footer-social a {
            width: 40px;
            height: 40px;
            background-color: rgba(255, 255, 255, 0.1);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            text-decoration: none;
            transition: all 0.3s ease;
        }
        
        .about-footer-social a:hover {
            background-color: #F4A261;
            transform: translateY(-3px);
        }
        
        .about-footer-bottom {
            text-align: center;
            padding-top: 30px;
            border-top: 1px solid rgba(255, 255, 255, 0.1);
            color: rgba(255, 255, 255, 0.5);
            font-size: 13px;
        }
        
        .about-newsletter-input {
            width: 100%;
            padding: 12px;
            border: 1px solid rgba(255, 255, 255, 0.2);
            background-color: rgba(255, 255, 255, 0.1);
            border-radius: 8px;
            color: white;
            margin-bottom: 10px;
        }
        
        .about-newsletter-input::placeholder {
            color: rgba(255, 255, 255, 0.4);
        }
        
        .about-newsletter-btn {
            width: 100%;
            padding: 12px;
            background: linear-gradient(to right, #F4A261, #E76F51);
            color: white;
            border: none;
            border-radius: 8px;
            font-weight: bold;
            cursor: pointer;
            transition: transform 0.3s ease;
        }
        
        .about-newsletter-btn:hover {
            transform: scale(1.02);
        }
    </style>
</head>
<body>
    <!-- Navbar -->
    <nav class="about-navbar" id="navbar">
        <a href="/" class="about-navbar-logo">EasyTravel</a>
        <ul class="about-navbar-menu">
            <li><a href="/">Accueil</a></li>
            <li><a href="/destinations">Destinations</a></li>
            <li><a href="/about" class="active">À propos</a></li>
            <li><a href="/contact">Contact</a></li>
        </ul>
        <a href="/contact" class="about-navbar-cta">Contactez-nous</a>
    </nav>

    <!-- Main Content -->
    <main>
        <?php require $contentTemplate; ?>
    </main>

    <!-- Footer -->
    <footer class="about-footer">
        <div class="about-footer-content">
            <div class="about-footer-section">
                <h3>EasyTravel</h3>
                <p>Créateur d'expériences de voyage uniques avec l'intelligence artificielle depuis 2024.</p>
                <div class="about-footer-social">
                    <a href="#">f</a>
                    <a href="#">📷</a>
                    <a href="#">🐦</a>
                </div>
            </div>
            
            <div class="about-footer-section">
                <h3>Liens rapides</h3>
                <a href="/destinations">Destinations</a>
                <a href="/activites">Nos services</a>
                <a href="/about">À propos</a>
            </div>
            
            <div class="about-footer-section">
                <h3>Support</h3>
                <a href="/contact">Contact</a>
                <a href="#">FAQ</a>
                <a href="#">Conditions générales</a>
                <a href="#">Politique de confidentialité</a>
            </div>
            
            <div class="about-footer-section">
                <h3>Newsletter</h3>
                <p>Recevez nos meilleures offres</p>
                <input type="email" class="about-newsletter-input" placeholder="Votre email">
                <button class="about-newsletter-btn">S'abonner</button>
            </div>
        </div>
        
        <div class="about-footer-bottom">
            <p>© 2024 EasyTravel - Tous droits réservés | Créé avec amour pour les voyageurs</p>
        </div>
    </footer>

    <script>
        // Navbar scroll effect
        const navbar = document.getElementById('navbar');
        window.addEventListener('scroll', () => {
            if (window.scrollY > 50) {
                navbar.classList.add('scrolled');
            } else {
                navbar.classList.remove('scrolled');
            }
        });
    </script>
</body>
</html>
