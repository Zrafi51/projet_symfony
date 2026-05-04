// ========================================
// FACTURE.JS - Logique du formulaire de facture
// ========================================

document.addEventListener('DOMContentLoaded', function() {
    initFactureForm();
});

function initFactureForm() {
    // Sélection du paiement
    const paiementSelect = document.getElementById('paiementSelect');
    if (paiementSelect) {
        paiementSelect.addEventListener('change', function() {
            const selectedOption = this.options[this.selectedIndex];
            if (this.value !== '0') {
                remplirDepuisPaiement(selectedOption);
            }
        });
    }

    // Calcul automatique lors de la saisie
    const montantFields = ['montantTransport', 'montantHebergement', 'montantActivites'];
    montantFields.forEach(fieldId => {
        const field = document.getElementById(fieldId);
        if (field) {
            field.addEventListener('input', calculerTotal);
        }
    });

    // Calcul initial
    calculerTotal();
}

function remplirDepuisPaiement(option) {
    const clientNom = option.getAttribute('data-client') || '';
    const destination = option.getAttribute('data-destination') || '';
    const montant = parseFloat(option.getAttribute('data-montant')) || 0;
    const typeVoyage = option.getAttribute('data-type') || '';

    // Remplir les champs client
    const clientNomField = document.getElementById('clientNom');
    const destinationField = document.getElementById('destination');

    if (clientNomField) clientNomField.value = clientNom;
    if (destinationField) destinationField.value = destination;

    // Répartir le montant (40% transport, 45% hébergement, 15% activités)
    const montantTransport = document.getElementById('montantTransport');
    const montantHebergement = document.getElementById('montantHebergement');
    const montantActivites = document.getElementById('montantActivites');

    if (montantTransport) montantTransport.value = (montant * 0.40).toFixed(2);
    if (montantHebergement) montantHebergement.value = (montant * 0.45).toFixed(2);
    if (montantActivites) montantActivites.value = (montant * 0.15).toFixed(2);

    // Calculer le total
    calculerTotal();

    // Afficher un message
    afficherMessage('✅ Informations chargées depuis le paiement sélectionné', 'success');
}

function calculerTotal() {
    const transport = parseFloat(document.getElementById('montantTransport')?.value) || 0;
    const hebergement = parseFloat(document.getElementById('montantHebergement')?.value) || 0;
    const activites = parseFloat(document.getElementById('montantActivites')?.value) || 0;

    const total = transport + hebergement + activites;

    // Afficher le total
    const montantTotalDisplay = document.getElementById('montantTotalDisplay');
    const montantTotalInput = document.getElementById('montantTotal');

    if (montantTotalDisplay) {
        montantTotalDisplay.textContent = total.toFixed(2) + ' €';
    }

    if (montantTotalInput) {
        montantTotalInput.value = total.toFixed(2);
    }

    return total;
}

function previsualiserFacture() {
    // Valider le formulaire
    if (!validerFormulaire()) {
        return;
    }

    // Créer un formulaire temporaire pour la prévisualisation
    const form = document.createElement('form');
    form.method = 'POST';
    form.action = '/factures/previsualiser';
    form.target = '_blank';

    // Récupérer tous les champs
    const fields = [
        'client_nom', 'client_email', 'client_adresse',
        'destination', 'date_debut', 'date_fin', 'nb_personnes',
        'montant_transport', 'montant_hebergement', 'montant_activites', 'montant_total'
    ];

    fields.forEach(fieldName => {
        const field = document.querySelector(`[name="${fieldName}"]`);
        if (field) {
            const input = document.createElement('input');
            input.type = 'hidden';
            input.name = fieldName;
            input.value = field.value;
            form.appendChild(input);
        }
    });

    document.body.appendChild(form);
    form.submit();
    document.body.removeChild(form);
}

function validerFormulaire() {
    const clientNom = document.getElementById('clientNom')?.value.trim();
    const clientEmail = document.getElementById('clientEmail')?.value.trim();
    const destination = document.getElementById('destination')?.value.trim();
    const dateDebut = document.getElementById('dateDebut')?.value.trim();
    const dateFin = document.getElementById('dateFin')?.value.trim();

    if (!clientNom) {
        afficherMessage('⚠ Veuillez entrer le nom du client', 'error');
        document.getElementById('clientNom')?.focus();
        return false;
    }

    if (!clientEmail) {
        afficherMessage('⚠ Veuillez entrer l\'email du client', 'error');
        document.getElementById('clientEmail')?.focus();
        return false;
    }

    if (!destination) {
        afficherMessage('⚠ Veuillez entrer la destination', 'error');
        document.getElementById('destination')?.focus();
        return false;
    }

    if (!dateDebut) {
        afficherMessage('⚠ Veuillez entrer la date de début', 'error');
        document.getElementById('dateDebut')?.focus();
        return false;
    }

    if (!dateFin) {
        afficherMessage('⚠ Veuillez entrer la date de fin', 'error');
        document.getElementById('dateFin')?.focus();
        return false;
    }

    const transport = parseFloat(document.getElementById('montantTransport')?.value) || 0;
    const hebergement = parseFloat(document.getElementById('montantHebergement')?.value) || 0;
    const activites = parseFloat(document.getElementById('montantActivites')?.value) || 0;

    if (transport <= 0 || hebergement <= 0 || activites <= 0) {
        afficherMessage('⚠ Les montants doivent être supérieurs à 0', 'error');
        return false;
    }

    return true;
}

function afficherMessage(message, type) {
    const messageBox = document.getElementById('messageBox');
    if (!messageBox) return;

    messageBox.textContent = message;
    messageBox.className = 'facture-message ' + type;
    messageBox.style.display = 'block';

    // Masquer après 5 secondes
    setTimeout(() => {
        messageBox.style.display = 'none';
    }, 5000);
}

// Fonction pour imprimer la facture
function imprimerFacture() {
    window.print();
}

// Fonction pour télécharger en PDF (utilise l'impression du navigateur)
function telechargerPDF() {
    window.print();
}
