# 🔄 Migration Java → Symfony : Système de Factures

## 📋 Correspondance des fichiers

| Java (WorkshopJdbc-3A14) | Symfony (projet_symfony) | Statut |
|--------------------------|--------------------------|--------|
| `Facture.fxml` | `templates/facture/create.html.twig` | ✅ Créé |
| `FactureTemplate.fxml` | `templates/facture/preview.html.twig` | ✅ Créé |
| `FactureController.java` | `src/Controller/FactureController.php` | ✅ Créé |
| `Facture.java` (Entity) | `src/Repository/FactureRepository.php` | ✅ Existe |
| `FactureService.java` | `src/Repository/FactureRepository.php` | ✅ Existe |
| Styles inline FXML | `public/facture.css` | ✅ Créé |
| Logique JavaFX | `public/facture.js` | ✅ Créé |

---

## 🎨 Correspondance des styles

### Java FXML → CSS Symfony

```java
// Java FXML
style="-fx-background-color: linear-gradient(to right, #0b4a6e, #1d5f8a);"
```

```css
/* Symfony CSS */
background: linear-gradient(to right, #0b4a6e, #1d5f8a);
```

### Bordures arrondies

```java
// Java FXML
style="-fx-background-radius: 24;"
```

```css
/* Symfony CSS */
border-radius: 24px;
```

### Padding

```java
// Java FXML
style="-fx-padding: 28 40;"
```

```css
/* Symfony CSS */
padding: 28px 40px;
```

---

## 🔧 Correspondance des méthodes

### Java → PHP

| Java (FactureController.java) | PHP (FactureController.php) |
|-------------------------------|----------------------------|
| `initialize()` | `index()` |
| `chargerPaiements()` | `$paiementRepository->findPaidPayments()` |
| `remplirDepuisPaiement()` | JavaScript `remplirDepuisPaiement()` |
| `calculerTotal()` | JavaScript `calculerTotal()` |
| `genererFacture()` | `generer()` |
| `previsualiserFacture()` | `previsualiser()` |
| `envoyerAuClient()` | `envoyer()` |
| `retourAdmin()` | `retourAdmin()` |

---

## 📊 Correspondance des composants

### Formulaire de génération

| Java (Facture.fxml) | Symfony (create.html.twig) |
|---------------------|----------------------------|
| `ComboBox<String> paiementComboBox` | `<select id="paiementSelect">` |
| `TextField clientNomField` | `<input id="clientNom">` |
| `TextField clientEmailField` | `<input id="clientEmail">` |
| `TextField montantTransportField` | `<input id="montantTransport">` |
| `Label montantTotalLabel` | `<div id="montantTotalDisplay">` |
| `Button genererBtn` | `<button type="submit">` |

### Prévisualisation

| Java (FactureTemplate.fxml) | Symfony (preview.html.twig) |
|-----------------------------|----------------------------|
| `Label numeroFactureLabel` | `{{ facture.numero_facture }}` |
| `Label clientNomLabel` | `{{ facture.client_nom }}` |
| `Label montantTotalLabel` | `{{ facture.montant_total }}` |
| `StackPane cachet` | `<div class="facture-stamp">` |
| `VBox signature` | `<div class="facture-signature">` |

---

## 🎯 Logique métier

### Ventilation automatique

**Java :**
```java
double total = p.getMontant();
montantTransportField.setText(String.format("%.2f", total * 0.40));
montantHebergementField.setText(String.format("%.2f", total * 0.45));
montantActivitesField.setText(String.format("%.2f", total * 0.15));
```

**Symfony (JavaScript) :**
```javascript
const transport = montant * 0.40;
const hebergement = montant * 0.45;
const activites = montant * 0.15;
montantTransport.value = transport.toFixed(2);
```

### Calcul du total

**Java :**
```java
double transport = parseDouble(montantTransportField.getText());
double hebergement = parseDouble(montantHebergementField.getText());
double activites = parseDouble(montantActivitesField.getText());
double total = transport + hebergement + activites;
montantTotalLabel.setText(String.format("%.2f €", total));
```

**Symfony (JavaScript) :**
```javascript
const transport = parseFloat(document.getElementById('montantTransport').value) || 0;
const hebergement = parseFloat(document.getElementById('montantHebergement').value) || 0;
const activites = parseFloat(document.getElementById('montantActivites').value) || 0;
const total = transport + hebergement + activites;
document.getElementById('montantTotalDisplay').textContent = total.toFixed(2) + ' €';
```

---

## 🗄️ Base de données

### Java (FactureService.java)

```java
String query = "INSERT INTO factures (numero_facture, date_emission, client_nom, ...) VALUES (?, ?, ?, ...)";
PreparedStatement ps = connection.prepareStatement(query);
ps.setString(1, facture.getNumeroFacture());
ps.executeUpdate();
```

### Symfony (FactureRepository.php)

```php
$statement = $connection->prepare(
    'INSERT INTO factures (numero_facture, date_emission, client_nom, ...) VALUES (:numero_facture, :date_emission, :client_nom, ...)'
);
$statement->execute($payload);
```

---

## 🎨 Design : Avant/Après

### Header

**Java FXML :**
```xml
<VBox style="-fx-background-color: linear-gradient(to right, #0b4a6e, #1d5f8a); -fx-background-radius: 24; -fx-padding: 28 40;">
    <Label text="Génération de Facture" style="-fx-text-fill: white; -fx-font-size: 24px; -fx-font-weight: 900;"/>
</VBox>
```

**Symfony HTML/CSS :**
```html
<div class="facture-header">
    <h1>Génération de Facture</h1>
</div>
```

```css
.facture-header {
    background: linear-gradient(to right, #0b4a6e, #1d5f8a);
    border-radius: 24px;
    padding: 28px 40px;
}

.facture-header h1 {
    color: white;
    font-size: 24px;
    font-weight: 900;
}
```

---

## ⚡ Différences principales

### 1. Gestion des événements

**Java :**
```java
@FXML
private void calculerTotal() {
    // Logique
}
```

**Symfony :**
```javascript
function calculerTotal() {
    // Logique
}
```

### 2. Navigation

**Java :**
```java
FXMLLoader loader = new FXMLLoader(getClass().getResource("/FactureTemplate.fxml"));
Parent root = loader.load();
Stage stage = new Stage();
stage.setScene(new Scene(root));
stage.show();
```

**Symfony :**
```php
return $this->redirectToRoute('facture_preview', ['id' => $factureId]);
```

### 3. Génération PDF

**Java :**
```java
// Utilise iText PDF
PdfWriter writer = new PdfWriter(destination);
PdfDocument pdfDoc = new PdfDocument(writer);
Document document = new Document(pdfDoc);
```

**Symfony :**
```javascript
// Utilise l'impression du navigateur
window.print(); // Enregistrer en PDF
```

---

## ✅ Fonctionnalités identiques

1. ✅ **Sélection de paiement** avec auto-remplissage
2. ✅ **Ventilation automatique** (40/45/15)
3. ✅ **Calcul en temps réel** du total
4. ✅ **Validation des champs** obligatoires
5. ✅ **Prévisualisation** avant enregistrement
6. ✅ **Statuts de facture** (GENEREE, PREVIEW, ENVOYEE)
7. ✅ **Design professionnel** avec cachet et signature
8. ✅ **Impression** et export PDF
9. ✅ **Retour au dashboard** admin

---

## 🚀 Avantages de la version Symfony

1. **Web-based** - Accessible depuis n'importe quel navigateur
2. **Responsive** - S'adapte aux différentes tailles d'écran
3. **Pas d'installation** - Pas besoin de JavaFX
4. **Multi-utilisateurs** - Plusieurs admins simultanés
5. **Déploiement facile** - Hébergement web standard

---

## 📝 Checklist de migration

- [x] Créer `FactureController.php`
- [x] Créer `templates/facture/create.html.twig`
- [x] Créer `templates/facture/preview.html.twig`
- [x] Créer `public/facture.css`
- [x] Créer `public/facture.js`
- [x] Ajouter les routes dans le contrôleur
- [x] Intégrer au dashboard admin
- [x] Tester la génération de factures
- [x] Tester la prévisualisation
- [x] Tester l'envoi au client
- [x] Documenter le système

---

## 🎓 Conclusion

Le système de factures Symfony reproduit **fidèlement** le design et les fonctionnalités du projet Java, tout en bénéficiant des avantages d'une application web moderne.

**Résultat :** Une expérience utilisateur identique, mais accessible depuis n'importe quel navigateur ! 🎉
