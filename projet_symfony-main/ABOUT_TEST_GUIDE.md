# Guide de Test - Page About

## Démarrage rapide

1. **Démarrer le serveur Symfony**
   ```bash
   cd d:\projet_symfony
   symfony server:start
   ```

2. **Accéder à la page About**
   - Ouvrir le navigateur : `http://localhost:8000/about`
   - Ou cliquer sur "À propos" dans la navigation

## Checklist de test

### ✅ Hero Section
- [ ] La vidéo se charge et joue automatiquement
- [ ] Le titre "Nous ne vendons pas des voyages." est visible
- [ ] Le sous-titre est visible
- [ ] L'overlay sombre est appliqué sur la vidéo

### ✅ Navbar
- [ ] La navbar est transparente au chargement
- [ ] La navbar devient blanche au scroll
- [ ] Le logo change de couleur (blanc → bleu)
- [ ] Les liens de navigation fonctionnent
- [ ] Le lien "À propos" est actif (orange)
- [ ] Le bouton "Contactez-nous" fonctionne

### ✅ Notre Histoire (Scrollytelling)
- [ ] L'image 1 (2021 - L'Étincelle) est visible au départ
- [ ] Après 4 secondes, l'image change pour 2023
- [ ] Après 8 secondes, l'image change pour 2025
- [ ] Le cycle recommence automatiquement
- [ ] Les textes changent en même temps que les images
- [ ] Les transitions sont fluides

### ✅ Nos Valeurs
- [ ] Les 3 cartes sont visibles
- [ ] Les icônes Canvas sont dessinées correctement :
  - Boussole (Authenticité)
  - Globe (Responsabilité)
  - Diamant (Excellence)
- [ ] Les cartes s'animent au scroll (apparition progressive)
- [ ] Au hover sur une carte :
  - La carte se soulève
  - L'ombre s'agrandit
  - L'icône se soulève et tourne légèrement

### ✅ Notre Équipe
- [ ] L'image de fond est visible
- [ ] Les 4 spots (cercles blancs) sont positionnés sur l'image
- [ ] Au hover sur un spot :
  - Le spot devient orange et grossit
  - L'overlay s'assombrit
  - Les informations du membre apparaissent :
    * Linda Boukhris - Experte Destinations
    * Wassim Cheikh - Directeur Technique
    * Seif eddine Thairi - Développeur Full Stack
    * Shayma Majdoub - Chef de Projet

### ✅ CTA Final
- [ ] Le titre "Prêt à Commencer Votre Aventure ?" est visible
- [ ] Le bouton "Contactez-nous" est visible
- [ ] Le bouton redirige vers `/contact`
- [ ] Au hover, le bouton grossit légèrement

### ✅ Footer
- [ ] Les 4 sections sont visibles :
  - EasyTravel (avec description)
  - Liens rapides
  - Support
  - Newsletter
- [ ] Les liens fonctionnent
- [ ] Les icônes sociales sont visibles
- [ ] Le champ email newsletter est fonctionnel
- [ ] Le copyright est affiché en bas

### ✅ Responsive
- [ ] La page s'adapte sur mobile (< 768px)
- [ ] La page s'adapte sur tablette (768px - 1200px)
- [ ] Tous les éléments restent lisibles

### ✅ Performance
- [ ] La page se charge en moins de 3 secondes
- [ ] Les animations sont fluides (60 fps)
- [ ] Pas de lag au scroll
- [ ] La vidéo ne ralentit pas la page

## Problèmes courants

### La vidéo ne se charge pas
- Vérifier que le fichier existe : `/public/assets/java/Luke Cameron.mp4`
- Vérifier les permissions du fichier
- Vérifier le format vidéo (MP4)

### Les images ne s'affichent pas
- Vérifier que les images existent dans `/public/assets/java/`
- Vérifier les chemins dans le template

### Les icônes Canvas ne s'affichent pas
- Ouvrir la console du navigateur (F12)
- Vérifier qu'il n'y a pas d'erreurs JavaScript
- Vérifier que `about.js` est bien chargé

### Les animations ne fonctionnent pas
- Vérifier que `about.js` est chargé
- Vérifier la console pour les erreurs
- Vérifier que les classes CSS sont bien appliquées

### La navbar ne change pas au scroll
- Vérifier que le script JavaScript dans `about-layout.php` fonctionne
- Vérifier la console pour les erreurs

## Comparaison avec Java

Pour comparer avec la version Java :
1. Ouvrir le projet Java : `D:\WorkshopJdbc-3A14`
2. Lancer l'application Java
3. Naviguer vers la page About
4. Comparer visuellement avec la version Symfony

Les deux versions doivent être identiques en termes de :
- Design
- Couleurs
- Animations
- Interactions
- Contenu

## Support

Si vous rencontrez des problèmes :
1. Vérifier les logs Symfony : `var/log/dev.log`
2. Vérifier la console du navigateur (F12)
3. Vérifier que tous les assets sont présents
4. Vérifier les permissions des fichiers

## Prochaines étapes

Une fois la page About validée :
- [ ] Tester sur différents navigateurs (Chrome, Firefox, Safari, Edge)
- [ ] Tester sur différents appareils (Desktop, Tablet, Mobile)
- [ ] Optimiser les performances si nécessaire
- [ ] Ajouter des tests automatisés
- [ ] Documenter les modifications
