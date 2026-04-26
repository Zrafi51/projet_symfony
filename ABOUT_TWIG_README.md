# Page About - Version Twig

## Vue d'ensemble

La page "À propos" a été créée en **Twig** pour le projet Symfony, en reprenant exactement le même design et les mêmes fonctionnalités que le projet Java (WorkshopJdbc-3A14).

## Structure des fichiers Twig

### Templates Twig
```
templates/
├── about-layout.html.twig    # Layout spécial avec navbar et footer
└── about/
    ├── index.html.twig        # Template principal (Twig)
    └── index.php              # Template PHP (backup)
```

### Contrôleur
```
src/Controller/AboutController.php
```

### Assets
```
public/
├── about.css                  # Styles CSS
└── about.js                   # JavaScript pour animations
```

## Fonctionnement

### 1. Détection automatique Twig

Le `PhpTemplateRenderer` détecte automatiquement les templates Twig :
- Si `templates/about/index.html.twig` existe → utilise Twig
- Sinon → utilise `templates/about/index.php`

### 2. Layout personnalisé

Le template `about/index.html.twig` étend `about-layout.html.twig` :

```twig
{% extends 'about-layout.html.twig' %}

{% block content %}
    <!-- Contenu de la page -->
{% endblock %}
```

### 3. Avantages de Twig

✅ **Syntaxe claire** : `{{ variable }}` au lieu de `<?= $h($variable) ?>`
✅ **Héritage de templates** : `{% extends %}` et `{% block %}`
✅ **Sécurité** : Échappement automatique des variables
✅ **Lisibilité** : Code plus propre et maintenable
✅ **Réutilisabilité** : Composants et macros

## Comparaison PHP vs Twig

### PHP (index.php)
```php
<h1 class="about-hero-title"><?= $h($title) ?></h1>
```

### Twig (index.html.twig)
```twig
<h1 class="about-hero-title">{{ title }}</h1>
```

### Layout PHP (about-layout.php)
```php
<?php require $contentTemplate; ?>
```

### Layout Twig (about-layout.html.twig)
```twig
{% block content %}{% endblock %}
```

## Structure du template

### 1. Hero Section
```twig
<section class="about-hero">
    <video class="about-hero-video" autoplay muted loop playsinline>
        <source src="/assets/java/Luke Cameron.mp4" type="video/mp4">
    </video>
    <div class="about-hero-overlay"></div>
    <div class="about-hero-content">
        <h1 class="about-hero-title">Nous ne vendons pas des voyages.</h1>
        <p class="about-hero-subtitle">Nous créons les souvenirs qui façonneront votre vie.</p>
    </div>
</section>
```

### 2. Notre Histoire (Scrollytelling)
```twig
<section class="about-story-section">
    <div class="about-story-container">
        <h2 class="about-section-title">Notre Histoire</h2>
        <div class="about-story-content">
            <!-- Images et textes qui changent automatiquement -->
        </div>
    </div>
</section>
```

### 3. Nos Valeurs
```twig
<section class="about-values-section">
    <div class="about-values-container">
        <h2 class="about-section-title">Nos Valeurs</h2>
        <div class="about-values-grid">
            <!-- 3 cartes avec icônes Canvas -->
        </div>
    </div>
</section>
```

### 4. Notre Équipe
```twig
<section class="about-team-section">
    <div class="about-team-container">
        <h2 class="about-section-title">Notre Équipe Passionnée</h2>
        <div class="about-team-showcase">
            <!-- 4 membres avec effets hover -->
        </div>
    </div>
</section>
```

### 5. CTA Final
```twig
<section class="about-cta-section">
    <div class="about-cta-container">
        <h2 class="about-cta-title">Prêt à Commencer Votre Aventure ?</h2>
        <a href="/contact" class="about-cta-btn">Contactez-nous</a>
    </div>
</section>
```

## Layout about-layout.html.twig

Le layout inclut :

### Navbar fixe
```twig
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
```

### Contenu principal
```twig
<main>
    {% block content %}{% endblock %}
</main>
```

### Footer complet
```twig
<footer class="about-footer">
    <div class="about-footer-content">
        <!-- 4 sections : EasyTravel, Liens rapides, Support, Newsletter -->
    </div>
    <div class="about-footer-bottom">
        <p>© 2024 EasyTravel - Tous droits réservés</p>
    </div>
</footer>
```

### Script navbar
```twig
<script>
    const navbar = document.getElementById('navbar');
    window.addEventListener('scroll', () => {
        if (window.scrollY > 50) {
            navbar.classList.add('scrolled');
        } else {
            navbar.classList.remove('scrolled');
        }
    });
</script>
```

## Contrôleur AboutController

```php
<?php

namespace App\Controller;

use App\View\PhpTemplateRenderer;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class AboutController extends AbstractController
{
    public function __construct(
        private readonly PhpTemplateRenderer $renderer,
    ) {
    }

    #[Route('/about', name: 'app_about', methods: ['GET'])]
    public function index(): Response
    {
        return new Response($this->renderer->render('about/index', [
            'title' => 'À propos - EasyTravel',
        ]));
    }
}
```

## Animations JavaScript (about.js)

Le fichier `about.js` gère :

1. **Scrollytelling** : Changement automatique des histoires toutes les 4 secondes
2. **Intersection Observer** : Animation des cartes au scroll
3. **Icônes Canvas** : Dessin des icônes (boussole, globe, diamant)

## Test de la page

### Démarrer le serveur
```bash
cd d:\projet_symfony
symfony server:start
```

### Accéder à la page
```
http://localhost:8000/about
```

### Vérifications
- ✅ Le template Twig est utilisé (vérifier dans les logs)
- ✅ La navbar est transparente puis devient blanche au scroll
- ✅ Les histoires changent automatiquement
- ✅ Les cartes de valeurs s'animent au scroll
- ✅ Les membres de l'équipe ont des effets hover
- ✅ Tous les liens fonctionnent

## Avantages de cette approche

### 1. Flexibilité
- Templates Twig pour les nouvelles pages
- Templates PHP pour la compatibilité legacy
- Détection automatique par le renderer

### 2. Maintenabilité
- Code Twig plus lisible
- Séparation claire layout/contenu
- Réutilisation facile

### 3. Performance
- Twig compile les templates en PHP
- Cache automatique
- Optimisations intégrées

### 4. Sécurité
- Échappement automatique
- Protection XSS
- Validation des variables

## Migration PHP → Twig

Si vous avez d'autres pages en PHP à migrer :

### Étape 1 : Créer le template Twig
```bash
# Copier le fichier PHP
cp templates/page/index.php templates/page/index.html.twig
```

### Étape 2 : Convertir la syntaxe
```php
# PHP
<?= $h($variable) ?>

# Twig
{{ variable }}
```

### Étape 3 : Ajouter l'héritage
```twig
{% extends 'layout.html.twig' %}

{% block content %}
    <!-- Contenu -->
{% endblock %}
```

### Étape 4 : Tester
Le renderer détectera automatiquement le template Twig.

## Ressources

- [Documentation Twig](https://twig.symfony.com/)
- [Symfony Twig](https://symfony.com/doc/current/templates.html)
- [Twig Best Practices](https://twig.symfony.com/doc/3.x/coding_standards.html)

## Support

En cas de problème :
1. Vérifier que Twig est installé : `composer show symfony/twig-bundle`
2. Vérifier les logs : `var/log/dev.log`
3. Vider le cache : `php bin/console cache:clear`
4. Vérifier la syntaxe Twig : `php bin/console lint:twig templates/`

## Conclusion

La page About est maintenant disponible en **Twig** avec :
- ✅ Même design que le projet Java
- ✅ Même fonctionnalités
- ✅ Code plus propre et maintenable
- ✅ Meilleure sécurité
- ✅ Performance optimale

🎉 La migration est terminée !
