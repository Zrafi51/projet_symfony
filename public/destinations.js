document.addEventListener('DOMContentLoaded', () => {
    const page = document.querySelector('[data-destinations-page]');
    if (!page) {
        return;
    }

    const grid = document.getElementById('destinationsGrid');
    const searchField = document.getElementById('destinationsSearchField');
    const continentFilter = document.getElementById('destinationsContinentFilter');
    const budgetMinFilter = document.getElementById('destinationsBudgetMin');
    const budgetMaxFilter = document.getElementById('destinationsBudgetMax');
    const departureFilter = document.getElementById('destinationsDepartureFilter');
    const returnFilter = document.getElementById('destinationsReturnFilter');
    const travelTypeFilter = document.getElementById('destinationsTravelTypeFilter');
    const adultsFilter = document.getElementById('destinationsAdultsFilter');
    const childrenFilter = document.getElementById('destinationsChildrenFilter');
    const feedback = document.getElementById('destinationsFilterFeedback');
    const summaryCopy = document.getElementById('destinationsSummaryCopy');
    const visibleCount = document.getElementById('destinationsVisibleCount');
    const emptyState = document.getElementById('destinationsEmptyState');
    const detailsOverlay = document.getElementById('destinationDetailsOverlay');
    const detailsImage = document.getElementById('destinationDetailsImage');
    const detailsMood = document.getElementById('destinationDetailsMood');
    const detailsTitle = document.getElementById('destinationDetailsTitle');
    const detailsSubtitle = document.getElementById('destinationDetailsSubtitle');
    const detailsPrice = document.getElementById('destinationDetailsPrice');
    const detailsOriginalPrice = document.getElementById('destinationDetailsOriginalPrice');
    const detailsDuration = document.getElementById('destinationDetailsDuration');
    const detailsTravelers = document.getElementById('destinationDetailsTravelers');
    const detailsBestPeriod = document.getElementById('destinationDetailsBestPeriod');
    const detailsDescription = document.getElementById('destinationDetailsDescription');
    const detailsHighlights = document.getElementById('destinationDetailsHighlights');
    const detailsReserve = document.getElementById('destinationDetailsReserve');
    const detailsPersonalize = document.getElementById('destinationDetailsPersonalize');
    const packageStatus = document.getElementById('destinationPackageStatus');
    const packageContent = document.getElementById('destinationPackageContent');
    const interestButtons = Array.from(document.querySelectorAll('.destinations-interest-chip'));
    const popularButtons = Array.from(document.querySelectorAll('[data-popular-destination]'));
    const detailCloseButtons = Array.from(document.querySelectorAll('[data-close-destination-details]'));
    const recommendationEndpoint = page.dataset.recommendationEndpoint || '/destinations/recommendations';
    const packageDetailsEndpoint = page.dataset.packageDetailsEndpoint || '/destinations/package-details';
    const favoriteEndpoint = page.dataset.favoriteEndpoint || '/favorites/toggle';
    const isAuthenticated = page.dataset.isAuthenticated === '1';
    const flaskEnabled = page.dataset.flaskEnabled === '1';

    const selectedInterests = new Map();
    let cardModels = [];
    let activeCard = null;
    let flaskTimer = null;
    let flaskAbortController = null;

    const normalize = (value) => (value || '')
        .toString()
        .normalize('NFD')
        .replace(/[\u0300-\u036f]/g, '')
        .toLowerCase()
        .replace(/[^a-z0-9]+/g, ' ')
        .trim();

    const splitValues = (value) => (value || '')
        .split('|')
        .map((item) => item.trim())
        .filter(Boolean);

    const parseNumber = (value, fallback = 0) => {
        const parsed = Number.parseInt(value, 10);
        return Number.isFinite(parsed) ? parsed : fallback;
    };

    const escapeHtml = (value) => (value || '').toString()
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');

    const escapeAttr = escapeHtml;
    const formatCount = (count) => String(Math.max(0, count)).padStart(2, '0');

    const parseFavoriteKeys = () => {
        try {
            const keys = JSON.parse(page.dataset.favoriteKeys || '[]');
            return Array.isArray(keys) ? keys.map((key) => key.toString()) : [];
        } catch (error) {
            return [];
        }
    };

    const favoriteKeys = new Set(parseFavoriteKeys());

    const buildFallbackFavoriteKey = (card) => {
        const source = normalize(card.dataset.source || 'database') || 'database';
        const packageId = parseNumber(card.dataset.packageId);
        if (source !== 'flask' && packageId > 0) {
            return `db-${packageId}`;
        }

        const name = normalize(card.dataset.name || 'destination').replace(/\s+/g, '-');
        const country = normalize(card.dataset.country || 'voyage').replace(/\s+/g, '-');
        return `${source === 'flask' ? 'flask' : 'trip'}-${name || 'destination'}-${country || 'voyage'}`;
    };

    const createCardModel = (card) => {
        const audiences = splitValues(card.dataset.audiences);
        const interests = splitValues(card.dataset.interests);

        return {
            element: card,
            searchBlob: normalize(card.dataset.search),
            name: (card.dataset.name || '').trim(),
            country: (card.dataset.country || '').trim(),
            continent: (card.dataset.continent || '').trim(),
            continentKey: normalize(card.dataset.continent),
            price: parseNumber(card.dataset.price),
            travelMood: (card.dataset.travelMood || '').trim(),
            travelMoodKey: normalize(card.dataset.travelMood),
            audiences,
            audienceKeys: audiences.map(normalize),
            interests,
            interestKeys: interests.map(normalize),
            maxTravelers: parseNumber(card.dataset.maxTravelers, 2),
            image: card.dataset.image || '',
            description: (card.dataset.description || '').trim(),
            durationLabel: (card.dataset.duration || '').trim(),
            durationDays: parseNumber(card.dataset.duration, 7),
            bestPeriod: (card.dataset.bestPeriod || '').trim(),
            priceLabel: (card.dataset.priceLabel || '').trim(),
            originalPriceLabel: (card.dataset.originalPriceLabel || '').trim(),
            originalPrice: parseNumber(card.dataset.originalPrice, Math.round(parseNumber(card.dataset.price) * 1.16)),
            highlights: splitValues(card.dataset.highlights),
            paymentPath: (card.dataset.paymentPath || '/paiement').trim(),
            contactPath: (card.dataset.contactPath || '/contact').trim(),
            packageId: parseNumber(card.dataset.packageId),
            source: (card.dataset.source || 'database').trim(),
            favoriteKey: (card.dataset.favoriteKey || buildFallbackFavoriteKey(card)).trim(),
            isFavorite: card.dataset.isFavorite === '1' || favoriteKeys.has((card.dataset.favoriteKey || buildFallbackFavoriteKey(card)).trim()),
        };
    };

    const hydrateCardModels = () => {
        cardModels = Array.from(document.querySelectorAll('[data-destination-card]')).map(createCardModel);
    };

    const selectedInterestLabels = () => Array.from(selectedInterests.values());

    const setFeedback = (message, isError = false) => {
        if (!feedback) {
            return;
        }

        feedback.textContent = message;
        feedback.classList.toggle('is-error', isError);
        feedback.classList.toggle('is-success', !isError && message !== '');
    };

    const updateDisabledField = (field, shouldDisable) => {
        if (!field) {
            return;
        }

        field.disabled = shouldDisable;
        field.classList.toggle('is-locked', shouldDisable);
    };

    const syncTravelTypeRules = () => {
        if (!travelTypeFilter || !adultsFilter || !childrenFilter) {
            return;
        }

        const travelTypeKey = normalize(travelTypeFilter.value);
        let adults = Math.max(1, parseNumber(adultsFilter.value, 2));
        let children = Math.max(0, parseNumber(childrenFilter.value, 0));
        let lockAdults = false;
        let lockChildren = false;

        if (travelTypeKey.includes('couple')) {
            adults = 2;
            children = 0;
            lockAdults = true;
            lockChildren = true;
        } else if (travelTypeKey.includes('solo') || travelTypeKey.includes('business')) {
            adults = 1;
            children = 0;
            lockAdults = true;
            lockChildren = true;
        }

        adultsFilter.value = String(adults);
        childrenFilter.value = String(children);
        updateDisabledField(adultsFilter, lockAdults);
        updateDisabledField(childrenFilter, lockChildren);
    };

    const setInitialValues = () => {
        const queryParams = new URLSearchParams(window.location.search);

        if (searchField) {
            searchField.value = page.dataset.initialSearch || '';
        }
        if (continentFilter) {
            continentFilter.value = page.dataset.initialContinent || '';
        }
        if (budgetMinFilter) {
            budgetMinFilter.value = queryParams.has('budget_min') ? String(parseNumber(page.dataset.initialBudgetMin)) : '';
        }
        if (budgetMaxFilter) {
            budgetMaxFilter.value = queryParams.has('budget_max') ? String(parseNumber(page.dataset.initialBudgetMax, 5000)) : '';
        }
        if (departureFilter) {
            departureFilter.value = page.dataset.initialDeparture || '';
        }
        if (returnFilter) {
            returnFilter.value = page.dataset.initialReturn || '';
        }
        if (travelTypeFilter) {
            travelTypeFilter.value = page.dataset.initialTravelType || 'Tous les profils';
        }
        if (adultsFilter) {
            adultsFilter.value = String(parseNumber(page.dataset.initialAdults, 2));
        }
        if (childrenFilter) {
            childrenFilter.value = String(parseNumber(page.dataset.initialChildren, 0));
        }

        syncTravelTypeRules();

        if (departureFilter && returnFilter && departureFilter.value !== '') {
            returnFilter.min = departureFilter.value;
        }
    };

    const getTripLength = () => {
        if (!departureFilter || !returnFilter || departureFilter.value === '' || returnFilter.value === '') {
            return null;
        }

        const departure = new Date(departureFilter.value);
        const returnDate = new Date(returnFilter.value);
        if (Number.isNaN(departure.getTime()) || Number.isNaN(returnDate.getTime())) {
            return null;
        }

        return Math.floor((returnDate.getTime() - departure.getTime()) / 86400000) + 1;
    };

    const hasInvalidDates = () => Boolean(
        departureFilter
        && returnFilter
        && departureFilter.value !== ''
        && returnFilter.value !== ''
        && new Date(returnFilter.value).getTime() < new Date(departureFilter.value).getTime()
    );

    const getFilterPayload = () => ({
        search: searchField ? searchField.value.trim() : '',
        continent: continentFilter ? continentFilter.value.trim() : '',
        budget_min: Math.max(0, parseNumber(budgetMinFilter ? budgetMinFilter.value : page.dataset.initialBudgetMin, 500)),
        budget_max: Math.max(0, parseNumber(budgetMaxFilter ? budgetMaxFilter.value : page.dataset.initialBudgetMax, 5000)),
        date_debut: departureFilter ? departureFilter.value : '',
        date_fin: returnFilter ? returnFilter.value : '',
        type_voyage: travelTypeFilter ? travelTypeFilter.value : 'Tous les profils',
        nb_adultes: Math.max(1, parseNumber(adultsFilter ? adultsFilter.value : 2, 2)),
        nb_enfants: Math.max(0, parseNumber(childrenFilter ? childrenFilter.value : 0, 0)),
        interets: selectedInterestLabels(),
    });

    const matchesCard = (card, tripLength, invalidDates) => {
        const payload = getFilterPayload();
        const searchTerm = normalize(payload.search);
        if (searchTerm && !card.searchBlob.includes(searchTerm)) {
            return false;
        }

        const continent = normalize(payload.continent);
        if (continent && card.continentKey !== continent) {
            return false;
        }

        const budgetMin = Math.max(0, payload.budget_min);
        const budgetMax = Math.max(budgetMin, payload.budget_max);
        if (card.price < budgetMin || card.price > budgetMax) {
            return false;
        }

        const travelType = normalize(payload.type_voyage);
        if (travelType && travelType !== normalize('Tous les profils') && !card.audienceKeys.includes(travelType)) {
            return false;
        }

        const totalTravelers = payload.nb_adultes + payload.nb_enfants;
        if (totalTravelers > card.maxTravelers) {
            return false;
        }

        if (selectedInterests.size > 0) {
            const hasMatchingInterest = Array.from(selectedInterests.keys()).some((interest) => card.interestKeys.includes(interest));
            if (!hasMatchingInterest) {
                return false;
            }
        }

        if (!invalidDates && tripLength !== null && tripLength < card.durationDays) {
            return false;
        }

        return true;
    };

    const buildSummary = (count, tripLength) => {
        const payload = getFilterPayload();
        const parts = [];

        if (payload.search !== '') {
            parts.push(payload.search);
        }
        if (payload.continent !== '') {
            parts.push(payload.continent);
        }
        if (payload.type_voyage !== '' && payload.type_voyage !== 'Tous les profils') {
            parts.push(payload.type_voyage);
        }
        if (selectedInterests.size > 0) {
            parts.push(selectedInterestLabels().join(', '));
        }
        if (tripLength !== null) {
            parts.push(`${tripLength} jours disponibles`);
        }

        const lead = count > 0
            ? `${count} destination${count > 1 ? 's' : ''} visible${count > 1 ? 's' : ''}`
            : 'Aucune destination visible';

        return parts.length === 0
            ? `${lead}. Explorez les offres premium avec les recommandations IA connectees a Flask.`
            : `${lead} pour ${parts.join(' - ')}.`;
    };

    const applyFilters = (options = {}) => {
        const invalidDates = hasInvalidDates();
        const tripLength = getTripLength();
        let visibleIndex = 0;
        const visibleCards = [];

        cardModels.forEach((card) => {
            const isVisible = !invalidDates && matchesCard(card, tripLength, invalidDates);
            card.element.classList.toggle('is-hidden', !isVisible);
            card.element.hidden = !isVisible;

            if (isVisible) {
                card.element.style.setProperty('--destinations-order', String(visibleIndex));
                visibleCards.push(card);
                visibleIndex += 1;
            }
        });

        if (visibleCount) {
            visibleCount.textContent = formatCount(visibleCards.length);
        }

        if (summaryCopy) {
            summaryCopy.textContent = buildSummary(visibleCards.length, invalidDates ? null : tripLength);
        }

        if (emptyState) {
            emptyState.hidden = visibleCards.length > 0 || invalidDates;
        }

        if (invalidDates) {
            setFeedback('La date de retour doit etre apres la date de depart.', true);
        } else if (visibleCards.length === 0) {
            setFeedback('Aucun pack ne correspond exactement a vos filtres. Essayez un autre budget ou retirez une envie.', false);
        } else if (options.keepMessage !== true) {
            setFeedback(`${visibleCards.length} pack${visibleCards.length > 1 ? 's' : ''} selectionne${visibleCards.length > 1 ? 's' : ''}.`, false);
        }

        if (options.syncWithFlask !== false) {
            scheduleFlaskSync();
        }
    };

    const buildReserveUrl = (card) => {
        const payload = getFilterPayload();
        const params = new URLSearchParams();
        params.set('destination', [card.name, card.country].filter(Boolean).join(', '));
        params.set('country', card.country || '');
        params.set('montant', String(card.price));
        params.set('package_id', String(card.packageId));
        params.set('type_voyage', payload.type_voyage !== 'Tous les profils' ? payload.type_voyage : card.travelMood);

        if (payload.date_debut !== '') {
            params.set('date', payload.date_debut);
            params.set('date_debut', payload.date_debut);
        }
        if (payload.date_fin !== '') {
            params.set('date_fin', payload.date_fin);
        }
        params.set('adults', String(payload.nb_adultes));
        params.set('children', String(payload.nb_enfants));

        return `${card.paymentPath}?${params.toString()}`;
    };

    const buildContactUrl = (card) => {
        const payload = getFilterPayload();
        const params = new URLSearchParams();
        params.set('destination', [card.name, card.country].filter(Boolean).join(', '));
        params.set('travel_type', payload.type_voyage !== 'Tous les profils' ? payload.type_voyage : card.travelMood);
        params.set('budget', String(card.price));

        return `${card.contactPath}?${params.toString()}`;
    };

    const buildPersonalizationPrompt = (card) => {
        const payload = getFilterPayload();
        const country = card.country ? `, ${card.country}` : '';
        const travelType = payload.type_voyage !== 'Tous les profils' ? payload.type_voyage : (card.travelMood || 'couple');
        const budgetMin = payload.budget_min || 1000;
        const budgetMax = payload.budget_max || card.price || 5000;
        const duration = card.durationLabel || `${card.durationDays || 7} jours`;
        const interests = selectedInterestLabels().length > 0
            ? selectedInterestLabels().join(', ')
            : (card.interests.length > 0 ? card.interests.join(', ') : 'detente, culture');

        return [
            'Je veux personnaliser ce pack voyage.',
            `Destination actuelle: ${card.name || 'destination'}${country}.`,
            `Type de voyage: ${travelType}.`,
            `Budget: ${budgetMin} TND a ${budgetMax} TND.`,
            `Voyageurs: ${payload.nb_adultes} adultes et ${payload.nb_enfants} enfants.`,
            `Duree souhaitee: ${duration}.`,
            `Interets: ${interests}.`,
            'Propose une version optimisee du pack avec alternatives premium.',
        ].join('\n');
    };

    const buildPersonalizeUrl = (card) => {
        const params = new URLSearchParams();
        params.set('prompt', buildPersonalizationPrompt(card));

        return `/chat?${params.toString()}`;
    };

    const populateDetails = (card) => {
        if (detailsImage) {
            detailsImage.src = card.image;
            detailsImage.alt = card.name;
        }
        if (detailsMood) {
            detailsMood.textContent = card.travelMood;
        }
        if (detailsTitle) {
            detailsTitle.textContent = card.name;
        }
        if (detailsSubtitle) {
            detailsSubtitle.textContent = [card.country, card.continent].filter(Boolean).join(' - ');
        }
        if (detailsPrice) {
            detailsPrice.textContent = card.priceLabel;
            detailsPrice.dataset.moneyAmount = String(card.price);
            detailsPrice.dataset.moneyBase = 'TND';
        }
        if (detailsOriginalPrice) {
            detailsOriginalPrice.textContent = card.originalPriceLabel;
            detailsOriginalPrice.dataset.moneyAmount = String(card.originalPrice);
            detailsOriginalPrice.dataset.moneyBase = 'TND';
        }
        if (detailsDuration) {
            detailsDuration.textContent = card.durationLabel;
        }
        if (detailsTravelers) {
            detailsTravelers.textContent = `${card.maxTravelers} pers. max`;
        }
        if (detailsBestPeriod) {
            detailsBestPeriod.textContent = card.bestPeriod;
        }
        if (detailsDescription) {
            detailsDescription.textContent = card.description;
        }
        if (detailsHighlights) {
            detailsHighlights.innerHTML = '';
            card.highlights.forEach((highlight) => {
                const item = document.createElement('li');
                item.textContent = highlight;
                detailsHighlights.appendChild(item);
            });
        }
        if (detailsReserve) {
            detailsReserve.href = buildReserveUrl(card);
        }
        if (detailsPersonalize) {
            detailsPersonalize.href = buildPersonalizeUrl(card);
        }
        if (packageStatus) {
            packageStatus.textContent = 'Chargement des details IA...';
            packageStatus.classList.remove('is-error', 'is-success');
        }
        if (packageContent) {
            packageContent.innerHTML = `
                <article class="destinations-package-card destinations-package-card-loading">
                    <strong>Connexion Flask</strong>
                    <p>Nous demandons le transport, l'hebergement, les activites et la verification budget.</p>
                </article>
            `;
        }

        if (window.EasyTravelCurrency) {
            window.EasyTravelCurrency.refresh();
        }
    };

    const renderPackageDetails = (data) => {
        if (!packageStatus || !packageContent) {
            return;
        }

        packageStatus.textContent = data.message || 'Details mis a jour.';
        packageStatus.classList.toggle('is-success', data.ok === true);
        packageStatus.classList.toggle('is-error', data.ok !== true);

        const sections = Array.isArray(data.sections) ? data.sections : [];
        if (sections.length === 0) {
            packageContent.innerHTML = `
                <article class="destinations-package-card">
                    <strong>Pack de base disponible</strong>
                    <p>Les details IA complets ne sont pas disponibles, mais vous pouvez reserver ou personnaliser ce voyage.</p>
                </article>
            `;
            return;
        }

        packageContent.innerHTML = sections.map((section) => `
            <article class="destinations-package-card">
                <span class="destinations-package-icon">${escapeHtml(section.icon || '✓')}</span>
                <strong>${escapeHtml(section.title || 'Detail')}</strong>
                <p>${escapeHtml(section.copy || '').replace(/\n/g, '<br>')}</p>
            </article>
        `).join('');
    };

    const loadPackageDetails = async (card) => {
        if (!packageDetailsEndpoint) {
            return;
        }

        try {
            const payload = getFilterPayload();
            const response = await fetch(packageDetailsEndpoint, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                },
                body: JSON.stringify({
                    ...payload,
                    destination: card.name,
                    continent: card.continent,
                    budget: card.price,
                    duree: card.durationDays,
                    interets: selectedInterestLabels().length > 0 ? selectedInterestLabels() : card.interests,
                }),
            });

            renderPackageDetails(await response.json());
        } catch (error) {
            renderPackageDetails({
                ok: false,
                message: 'Flask ne repond pas pour les details du pack.',
                sections: [],
            });
        }
    };

    const openDetails = (card) => {
        if (!detailsOverlay) {
            return;
        }

        activeCard = card;
        populateDetails(card);
        loadPackageDetails(card);
        detailsOverlay.hidden = false;
        document.body.classList.add('destinations-modal-open');

        window.requestAnimationFrame(() => {
            detailsOverlay.classList.add('is-visible');
        });
    };

    const closeDetails = () => {
        if (!detailsOverlay || detailsOverlay.hidden) {
            return;
        }

        detailsOverlay.classList.remove('is-visible');
        document.body.classList.remove('destinations-modal-open');
        activeCard = null;

        window.setTimeout(() => {
            if (!detailsOverlay.classList.contains('is-visible')) {
                detailsOverlay.hidden = true;
            }
        }, 180);
    };

    const syncFavoriteButtons = (favoriteKey, isFavorite) => {
        if (!favoriteKey) {
            return;
        }

        if (isFavorite) {
            favoriteKeys.add(favoriteKey);
        } else {
            favoriteKeys.delete(favoriteKey);
        }

        document.querySelectorAll('[data-destination-card]').forEach((card) => {
            if ((card.dataset.favoriteKey || '') !== favoriteKey) {
                return;
            }

            card.dataset.isFavorite = isFavorite ? '1' : '0';
            const button = card.querySelector('[data-favorite-toggle]');
            if (!button) {
                return;
            }

            button.classList.toggle('is-active', isFavorite);
            button.setAttribute('aria-pressed', isFavorite ? 'true' : 'false');
            button.setAttribute('aria-label', isFavorite ? 'Retirer des favoris' : 'Ajouter aux favoris');
        });

        hydrateCardModels();
    };

    const favoritePayloadFromCard = (card) => ({
        favorite_key: card.favoriteKey,
        destination_id: card.packageId,
        destination_name: card.name,
        country: card.country,
        continent: card.continent,
        image_path: card.image,
        description: card.description,
        duration_label: card.durationLabel,
        price_amount: card.price,
        price_currency: 'TND',
        source: card.source || 'database',
        destination_url: '/destinations',
    });

    const toggleFavorite = async (button) => {
        const cardElement = button.closest('[data-destination-card]');
        if (!cardElement) {
            return;
        }

        if (!isAuthenticated) {
            setFeedback('Connectez-vous pour mettre ce voyage dans vos favoris.', true);
            window.setTimeout(() => {
                window.location.href = '/login';
            }, 650);
            return;
        }

        const card = createCardModel(cardElement);
        button.classList.add('is-busy');
        button.disabled = true;

        try {
            const response = await fetch(favoriteEndpoint, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                },
                body: JSON.stringify(favoritePayloadFromCard(card)),
            });
            const data = await response.json();

            if (!response.ok || data.ok !== true) {
                throw new Error(data.message || 'Impossible de modifier ce favori.');
            }

            syncFavoriteButtons(data.favorite_key || card.favoriteKey, data.is_favorite === true);
            setFeedback(data.message || 'Favoris mis a jour.', false);
        } catch (error) {
            setFeedback(error.message || 'Impossible de modifier ce favori.', true);
        } finally {
            button.classList.remove('is-busy');
            button.disabled = false;
        }
    };

    const renderCard = (card) => {
        const interests = Array.isArray(card.interests) ? card.interests : [];
        const audiences = Array.isArray(card.audiences) ? card.audiences : [];
        const highlights = Array.isArray(card.highlights) ? card.highlights : [];
        const tags = interests.slice(0, 3).map((interest) => `<span class="destinations-card-tag">${escapeHtml(interest)}</span>`).join('');
        const favoriteKey = card.favorite_key || '';
        const isFavorite = card.is_favorite === true || favoriteKeys.has(favoriteKey);

        return `
            <article
                class="destinations-card ${card.source === 'flask' ? 'destinations-card-flask' : ''}"
                data-destination-card
                data-search="${escapeAttr(card.search_blob || '')}"
                data-name="${escapeAttr(card.destination_name || '')}"
                data-country="${escapeAttr(card.country || '')}"
                data-continent="${escapeAttr(card.continent || '')}"
                data-price="${escapeAttr(card.price_amount || 0)}"
                data-travel-mood="${escapeAttr(card.travel_mood || '')}"
                data-audiences="${escapeAttr(audiences.join('|'))}"
                data-interests="${escapeAttr(interests.join('|'))}"
                data-max-travelers="${escapeAttr(card.max_travelers || 2)}"
                data-image="${escapeAttr(card.image_path || '')}"
                data-description="${escapeAttr(card.description || '')}"
                data-duration="${escapeAttr(card.duration_label || '7 jours')}"
                data-best-period="${escapeAttr(card.best_period || 'Toute l annee')}"
                data-price-label="${escapeAttr(card.price_label || '')}"
                data-original-price-label="${escapeAttr(card.original_price_label || '')}"
                data-original-price="${escapeAttr(card.original_price_amount || 0)}"
                data-highlights="${escapeAttr(highlights.join('|'))}"
                data-payment-path="${escapeAttr(card.payment_path || '/paiement')}"
                data-contact-path="${escapeAttr(card.contact_path || '/contact')}"
                data-package-id="${escapeAttr(card.id || 0)}"
                data-source="${escapeAttr(card.source || 'database')}"
                data-favorite-key="${escapeAttr(favoriteKey)}"
                data-is-favorite="${isFavorite ? '1' : '0'}"
            >
                <div class="destinations-card-visual">
                    <img src="${escapeAttr(card.image_path || '')}" alt="${escapeAttr(card.destination_name || 'Destination')}" class="destinations-card-image">
                    <div class="destinations-card-overlay"></div>
                    <button
                        type="button"
                        class="destinations-favorite-button ${isFavorite ? 'is-active' : ''}"
                        data-favorite-toggle
                        aria-label="${isFavorite ? 'Retirer des favoris' : 'Ajouter aux favoris'}"
                        aria-pressed="${isFavorite ? 'true' : 'false'}"
                    >
                        <span aria-hidden="true">&#9829;</span>
                    </button>
                    <div class="destinations-card-headline">
                        <span class="destinations-card-badge">${escapeHtml(card.source === 'flask' ? 'IA Flask' : (card.travel_mood || 'Pack'))}</span>
                        <h3 class="destinations-card-title">${escapeHtml(card.destination_name || 'Destination')}</h3>
                        <p class="destinations-card-subtitle">${escapeHtml(card.subtitle || [card.country, card.continent].filter(Boolean).join(' - '))}</p>
                    </div>
                </div>

                <div class="destinations-card-body">
                    <p class="destinations-card-description">${escapeHtml(card.description || '')}</p>
                    <div class="destinations-card-meta">
                        <div>
                            <span class="destinations-card-label">A partir de</span>
                            <strong class="destinations-card-price" data-money data-money-base="TND" data-money-amount="${escapeAttr(card.price_amount || 0)}">${escapeHtml(card.price_label || '')}</strong>
                        </div>
                        <div class="destinations-card-meta-right">
                            <span class="destinations-card-label">Duree</span>
                            <strong class="destinations-card-duration">${escapeHtml(card.duration_label || '')}</strong>
                        </div>
                    </div>
                    <div class="destinations-card-tags">${tags}</div>
                    <button type="button" class="destinations-card-action" data-open-destination-details>Voir details</button>
                </div>
            </article>
        `;
    };

    const wireCardDetails = () => {
        cardModels.forEach((card) => {
            const trigger = card.element.querySelector('[data-open-destination-details]');
            if (!trigger) {
                return;
            }

            trigger.addEventListener('click', () => openDetails(card));
        });
    };

    const wireCardFavorites = () => {
        document.querySelectorAll('[data-favorite-toggle]').forEach((button) => {
            if (button.dataset.favoriteBound === '1') {
                return;
            }

            button.dataset.favoriteBound = '1';
            button.addEventListener('click', (event) => {
                event.preventDefault();
                event.stopPropagation();
                toggleFavorite(button);
            });
        });
    };

    const renderCards = (cards, message, isError = false) => {
        if (!grid || !Array.isArray(cards)) {
            return;
        }

        grid.innerHTML = cards.map(renderCard).join('');
        hydrateCardModels();
        wireCardDetails();
        wireCardFavorites();
        applyFilters({ syncWithFlask: false, keepMessage: true });
        setFeedback(message, isError);
        if (window.EasyTravelCurrency) {
            window.EasyTravelCurrency.refresh();
        }
    };

    const syncWithFlask = async () => {
        if (!flaskEnabled || !recommendationEndpoint || hasInvalidDates()) {
            return;
        }

        if (flaskAbortController) {
            flaskAbortController.abort();
        }

        flaskAbortController = new AbortController();
        setFeedback('Connexion Flask en cours pour recalculer les recommandations IA...', false);

        try {
            const response = await fetch(recommendationEndpoint, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                },
                body: JSON.stringify(getFilterPayload()),
                signal: flaskAbortController.signal,
            });

            if (!response.ok) {
                throw new Error(`HTTP ${response.status}`);
            }

            const data = await response.json();
            if (!Array.isArray(data.cards)) {
                throw new Error('Reponse Flask invalide');
            }

            const sourceLabel = data.source === 'flask' ? 'Flask IA' : 'base de donnees';
            renderCards(data.cards, data.message || `Resultats mis a jour depuis ${sourceLabel}.`, data.ok === false && data.source !== 'database');
        } catch (error) {
            if (error.name === 'AbortError') {
                return;
            }

            setFeedback('Flask ne repond pas pour le moment, les filtres locaux restent actifs.', true);
        }
    };

    const scheduleFlaskSync = () => {
        if (!flaskEnabled) {
            return;
        }

        window.clearTimeout(flaskTimer);
        flaskTimer = window.setTimeout(syncWithFlask, 420);
    };

    const bindFilters = () => {
        if (searchField) {
            searchField.addEventListener('input', applyFilters);
        }
        if (continentFilter) {
            continentFilter.addEventListener('change', applyFilters);
        }
        if (budgetMinFilter) {
            budgetMinFilter.addEventListener('input', () => {
                const budgetMin = Math.max(0, parseNumber(budgetMinFilter.value, 500));
                const budgetMax = Math.max(0, parseNumber(budgetMaxFilter ? budgetMaxFilter.value : 0, 5000));
                if (budgetMin > budgetMax && budgetMaxFilter) {
                    budgetMaxFilter.value = String(budgetMin + 1000);
                }
                applyFilters();
            });
        }
        if (budgetMaxFilter) {
            budgetMaxFilter.addEventListener('input', applyFilters);
        }
        if (departureFilter) {
            departureFilter.addEventListener('change', () => {
                if (returnFilter) {
                    returnFilter.min = departureFilter.value;
                    if (returnFilter.value !== '' && returnFilter.value < departureFilter.value) {
                        returnFilter.value = departureFilter.value;
                    }
                }
                applyFilters();
            });
        }
        if (returnFilter) {
            returnFilter.addEventListener('change', applyFilters);
        }
        if (travelTypeFilter) {
            travelTypeFilter.addEventListener('change', () => {
                syncTravelTypeRules();
                applyFilters();
            });
        }
        if (adultsFilter) {
            adultsFilter.addEventListener('input', applyFilters);
            adultsFilter.addEventListener('change', applyFilters);
        }
        if (childrenFilter) {
            childrenFilter.addEventListener('input', applyFilters);
            childrenFilter.addEventListener('change', applyFilters);
        }
    };

    interestButtons.forEach((button) => {
        button.addEventListener('click', () => {
            const interestLabel = button.dataset.interest || button.textContent.trim();
            const interestKey = normalize(interestLabel);

            if (selectedInterests.has(interestKey)) {
                selectedInterests.delete(interestKey);
            } else {
                selectedInterests.set(interestKey, interestLabel);
            }

            button.classList.toggle('is-active', selectedInterests.has(interestKey));
            applyFilters();
        });
    });

    popularButtons.forEach((button) => {
        button.addEventListener('click', () => {
            if (!searchField) {
                return;
            }

            searchField.value = button.dataset.popularDestination || '';
            applyFilters();

            if (grid) {
                grid.scrollIntoView({ behavior: 'smooth', block: 'start' });
            }
        });
    });

    detailCloseButtons.forEach((button) => {
        button.addEventListener('click', closeDetails);
    });

    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape' && activeCard) {
            closeDetails();
        }
    });

    setInitialValues();
    hydrateCardModels();
    wireCardDetails();
    wireCardFavorites();
    bindFilters();
    applyFilters();
});
