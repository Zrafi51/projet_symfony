# Page About - Migration Java vers Symfony

## Vue d'ensemble

La page "À propos" a été migrée du projet Java (WorkshopJdbc-3A14) vers Symfony en conservant exactement le même design et les mêmes fonctionnalités.

## Fichiers créés

### Backend (Symfony)
- **src/Controller/AboutController.php** : Contrôleur gérant la route `/about`
- **templates/about/index.php** : Template principal de la page About
- **templates/about-layout.php** : Layout spécial avec navbar et footer intégrés

### Frontend
- **public/about.css** : Styles CSS identiques au projet Java
- **public/about.js** : JavaScript pour les animations et interactions

## Fonctionnalités

### 1. Hero Section avec Vidéo
- Vidéo en arrière-plan avec overlay
- Titre et sous-titre animés
- Design identique au projet Java

### 2. Notre Histoire (Scrollytelling)
- 3 étapes : 2021 (L'Étincelle), 2023 (L'Expansion), 2025 (L'Avenir)
- Images et textes qui changent automatiquement toutes les 4 secondes
- Transitions fluides entre les étapes

### 3. Nos Valeurs
- 3 cartes avec icônes Canvas personnalisées :
  - **Authenticité** : Icône boussole
  - **Responsabilité** : Icône globe
  - **Excellence** : Icône diamant
- Animations au scroll (reveal effect)
- Effets hover sur les cartes

### 4. Notre Équipe
- Showcase avec image de fond
- 4 membres de l'équipe positionnés sur l'image :
  - Linda Boukhris - Experte Destinations
  - Wassim Cheikh - Directeur Technique
  - Seif eddine Thairi - Développeur Full Stack
  - Shayma Majdoub - Chef de Projet
- Effets hover pour afficher les informations

### 5. CTA Final
- Section d'appel à l'action
- Bouton vers la page Contact

### 6. Navbar et Footer
- Navbar fixe avec effet de scroll
- Footer complet avec liens et newsletter

## Animations JavaScript

### Scrollytelling
```javascript
// Changement automatique des histoires toutes les 4 secondes
setInterval(() => {
    const nextStory = (currentStory + 1) % 3;
    updateStory(nextStory);
}, 4000);
```

### Intersection Observer
```javascript
// Animation des cartes de valeurs au scroll
const valuesObserver = new IntersectionObserver((entries) => {
    entries.forEach((entry, index) => {
        if (entry.isIntersecting) {
            setTimeout(() => {
                entry.target.classList.add('reveal');
            }, index * 180);
        }
    });
}, observerOptions);
```

### Icônes Canvas
- Dessin dynamique des icônes avec Canvas API
- 3 icônes personnalisées : boussole, globe, diamant

## Comparaison Java vs Symfony

| Aspect | Java (JavaFX) | Symfony (PHP) |
|--------|---------------|---------------|
| **Contrôleur** | AboutController.java | AboutController.php |
| **Vue** | About.fxml | about/index.php |
| **Styles** | about.css | about.css (identique) |
| **Animations** | JavaFX Animations | JavaScript + CSS |
| **Layout** | FXML StackPane | HTML + CSS |

## Route

```php
#[Route('/about', name: 'app_about', methods: ['GET'])]
```

Accessible via : `http://localhost:8000/about`

## Design System

### Couleurs
- **Primary** : #0B3C5D (Bleu foncé)
- **Secondary** : #F4A261 (Orange doré)
- **Accent** : #E76F51 (Orange corail)
- **Background** : #F7F9FB (Gris clair)

### Typographie
- **Titres** : Bold, grandes tailles (36px-60px)
- **Corps** : Regular, 18px
- **Line-height** : 1.5-1.6

### Espacements
- **Sections** : 80px padding vertical
- **Cartes** : 30-40px padding
- **Gaps** : 30-60px entre éléments

## Responsive Design

Le design est responsive avec des breakpoints :
- **Desktop** : > 1200px
- **Tablet** : 768px - 1200px
- **Mobile** : < 768px

## Assets requis

Les images et vidéos doivent être présentes dans `/public/assets/java/` :
- Luke Cameron.mp4 (vidéo hero)
- GettyImages-158525984-5b6df57dc9e77c005086b0ca.jpg
- 3fddde5acc7047afabbb1d9dd69301cd.jpg
- aede2fa75f528a9251e4809645f62f7a.jpg

## Tests

Pour tester la page :
1. Démarrer le serveur Symfony : `symfony server:start`
2. Accéder à : `http://localhost:8000/about`
3. Vérifier :
   - ✅ Vidéo hero se charge et joue
   - ✅ Scrollytelling change automatiquement
   - ✅ Cartes de valeurs s'animent au scroll
   - ✅ Hover sur les membres de l'équipe fonctionne
   - ✅ Navbar change au scroll
   - ✅ Tous les liens fonctionnent

## Améliorations futures

- [ ] Ajouter des transitions de page
- [ ] Implémenter le formulaire newsletter
- [ ] Ajouter plus d'animations micro-interactions
- [ ] Optimiser les performances vidéo
- [ ] Ajouter des tests automatisés

## Auteur

Migration réalisée en conservant fidèlement le design et les fonctionnalités du projet Java original.
