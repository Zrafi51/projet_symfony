document.addEventListener('DOMContentLoaded', () => {
    const cardField = document.getElementById('numeroCarteField');
    const holderField = document.getElementById('nomTitulaireField');
    const expirationField = document.getElementById('expirationField');
    const cvvField = document.getElementById('cvvField');
    const paiementSelect = document.getElementById('paiementSelect');
    const totalButton = document.querySelector('[data-calculate-total]');

    const digits = (value, max) => (value || '').replace(/\D+/g, '').slice(0, max);

    const formatCard = (value) => digits(value, 19).replace(/(.{4})/g, '$1 ').trim();
    const formatExpiration = (value) => {
        const clean = digits(value, 4);
        return clean.length <= 2 ? clean : `${clean.slice(0, 2)}/${clean.slice(2)}`;
    };

    if (cardField) {
        cardField.addEventListener('input', () => {
            cardField.value = formatCard(cardField.value);
        });
    }

    if (holderField) {
        holderField.addEventListener('input', () => {
            holderField.value = holderField.value.replace(/[^A-Za-zÀ-ÿ '\-]/g, '').toUpperCase();
        });
    }

    if (expirationField) {
        expirationField.addEventListener('input', () => {
            expirationField.value = formatExpiration(expirationField.value);
        });

        expirationField.addEventListener('blur', () => {
            const value = expirationField.value.trim();
            if (value !== '' && !/^\d{2}\/\d{2}$/.test(value)) {
                expirationField.setCustomValidity('Utilisez le format MM/AA, par exemple 10/30.');
            } else {
                expirationField.setCustomValidity('');
            }
        });

        expirationField.addEventListener('input', () => {
            expirationField.setCustomValidity('');
        });
    }

    if (cvvField) {
        cvvField.addEventListener('input', () => {
            cvvField.value = digits(cvvField.value, 4);
        });
    }

    const calculateTotal = () => {
        const transport = Number.parseFloat(document.getElementById('montantTransport')?.value || '0') || 0;
        const hebergement = Number.parseFloat(document.getElementById('montantHebergement')?.value || '0') || 0;
        const activites = Number.parseFloat(document.getElementById('montantActivites')?.value || '0') || 0;
        const total = transport + hebergement + activites;
        const totalDisplay = document.getElementById('montantTotalDisplay');
        const totalInput = document.getElementById('montantTotal');

        if (totalDisplay) {
            totalDisplay.textContent = `${total.toFixed(2)} TND`;
        }
        if (totalInput) {
            totalInput.value = total.toFixed(2);
        }
    };

    ['montantTransport', 'montantHebergement', 'montantActivites'].forEach((id) => {
        document.getElementById(id)?.addEventListener('input', calculateTotal);
    });

    if (totalButton) {
        totalButton.addEventListener('click', calculateTotal);
    }

    if (paiementSelect) {
        paiementSelect.addEventListener('change', () => {
            const option = paiementSelect.options[paiementSelect.selectedIndex];
            const montant = Number.parseFloat(option.dataset.montant || '0') || 0;
            const client = document.getElementById('clientNom');
            const destination = document.getElementById('destination');
            const transport = document.getElementById('montantTransport');
            const hebergement = document.getElementById('montantHebergement');
            const activites = document.getElementById('montantActivites');

            if (client) client.value = option.dataset.client || '';
            if (destination) destination.value = option.dataset.destination || '';
            if (transport) transport.value = (montant * 0.40).toFixed(2);
            if (hebergement) hebergement.value = (montant * 0.45).toFixed(2);
            if (activites) activites.value = (montant * 0.15).toFixed(2);
            calculateTotal();
        });
    }

    calculateTotal();
});
