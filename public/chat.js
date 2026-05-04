document.addEventListener('DOMContentLoaded', () => {
    const page = document.querySelector('[data-chat-page]');
    if (!page) {
        return;
    }

    const history = page.querySelector('[data-chat-history]');
    const messages = page.querySelector('[data-chat-messages]');
    const form = page.querySelector('[data-chat-form]');
    const input = page.querySelector('[data-chat-input]');
    const status = page.querySelector('[data-chat-status]');
    const newButton = page.querySelector('[data-new-chat]');
    const speechButton = page.querySelector('[data-speech-toggle]');
    const speechStatus = page.querySelector('[data-speech-status]');
    const generateCardButton = page.querySelector('[data-generate-card]');
    const cardList = page.querySelector('[data-card-list]');
    const cardDetail = page.querySelector('[data-card-detail]');
    const packStatus = page.querySelector('[data-pack-status]');
    const filterButtons = Array.from(page.querySelectorAll('[data-history-filter]'));
    const pendingPrompt = (page.dataset.pendingPrompt || '').trim();
    let sessions = [];
    let activeSession = null;
    let isSending = false;
    let pendingPromptSubmitted = false;
    let historyFilter = 'all';
    let recognition = null;
    let isListening = false;
    let speechBaseValue = '';
    let speechTranscript = '';
    let speechHeardAudio = false;
    let speechHadError = false;
    let speechStopTimer = null;
    const defaultSpeechHint = speechStatus ? speechStatus.textContent : '';

    try {
        sessions = JSON.parse(page.dataset.sessions || '[]');
    } catch (error) {
        sessions = [];
    }

    const escapeHtml = (value) => (value || '').toString()
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');

    const nowTime = () => new Date().toLocaleTimeString('fr-FR', { hour: '2-digit', minute: '2-digit' });
    const activeCards = () => (activeSession && Array.isArray(activeSession.cards)) ? activeSession.cards : [];
    const setSpeechStatus = (message) => {
        if (speechStatus) {
            speechStatus.textContent = message || defaultSpeechHint;
        }
    };

    const setListeningState = (listening) => {
        isListening = listening;
        speechButton?.classList.toggle('is-listening', listening);
        speechButton?.setAttribute('aria-pressed', listening ? 'true' : 'false');
        if (speechButton) {
            speechButton.disabled = isSending;
        }
    };

    const clearSpeechTimer = () => {
        if (speechStopTimer) {
            window.clearTimeout(speechStopTimer);
            speechStopTimer = null;
        }
    };

    const visibleSessions = () => (
        historyFilter === 'favorites'
            ? sessions.filter((session) => Boolean(session.isFavorite))
            : sessions
    );

    const sortSessions = () => {
        sessions = [
            ...sessions.filter((session) => Boolean(session.isFavorite)),
            ...sessions.filter((session) => !Boolean(session.isFavorite)),
        ];
    };

    const cardTitle = (card) => {
        const payload = card.card || {};
        return payload.title || payload.destination || payload.destination_name || payload.name || 'Pack voyage';
    };

    const formatCardKey = (key) => key
        .replace(/_/g, ' ')
        .replace(/\b\w/g, (letter) => letter.toUpperCase());

    const normalizeList = (value) => {
        if (Array.isArray(value)) {
            return value.filter((item) => item !== null && item !== undefined && item !== '');
        }
        if (value && typeof value === 'object') {
            return Object.values(value).filter((item) => item !== null && item !== undefined && item !== '');
        }
        return value ? [value] : [];
    };

    const valueFrom = (item, keys, fallback = '') => {
        if (!item || typeof item !== 'object') {
            return fallback;
        }
        for (const key of keys) {
            if (item[key] !== null && item[key] !== undefined && item[key] !== '') {
                return item[key];
            }
        }
        return fallback;
    };

    const stripMarkup = (value) => (value || '').toString()
        .replace(/<br\s*\/?>/gi, ' ')
        .replace(/<\/p>/gi, ' ')
        .replace(/<[^>]*>/g, '')
        .replace(/&nbsp;/g, ' ')
        .replace(/&amp;/g, '&')
        .replace(/&quot;/g, '"')
        .replace(/&#039;/g, "'")
        .replace(/\s+/g, ' ')
        .trim();

    const shortText = (value, maxLength = 170) => {
        const text = stripMarkup(value);
        if (text.length <= maxLength) {
            return text;
        }
        return `${text.slice(0, maxLength).replace(/\s+\S*$/, '')}...`;
    };

    const renderChipList = (items) => {
        const values = normalizeList(items).map((item) => {
            if (item && typeof item === 'object') {
                return valueFrom(item, ['name', 'title', 'destination', 'city', 'City'], '');
            }
            return item;
        }).filter(Boolean);

        if (values.length === 0) {
            return '<span class="travel-pack-muted">Non renseigne.</span>';
        }

        return `<div class="travel-pack-chip-list">${values.map((item) => `<span class="travel-pack-chip">${escapeHtml(item)}</span>`).join('')}</div>`;
    };

    const renderHotelCard = (hotel, index) => {
        const name = hotel && typeof hotel === 'object' ? valueFrom(hotel, ['name', 'Name', 'hotelName', 'title'], `Hotel ${index + 1}`) : hotel;
        const city = valueFrom(hotel, ['city', 'City', 'destination'], '');
        const address = valueFrom(hotel, ['address', 'Address'], '');
        const stars = valueFrom(hotel, ['stars', 'Stars', 'starRating'], '');
        const rating = valueFrom(hotel, ['rating', 'Rating'], '');
        const reviews = valueFrom(hotel, ['reviewCount', 'ReviewCount'], '');
        const photo = valueFrom(hotel, ['thumbnail', 'Thumbnail', 'main_photo', 'mainPhoto', 'Main Photo'], '');
        const description = shortText(valueFrom(hotel, ['notes', 'description', 'Description', 'hotelDescription', 'HotelDescription'], ''), 155);
        const meta = [
            city || address,
            stars ? `${stars} etoiles` : '',
            rating ? `Note ${rating}${reviews ? ` (${reviews} avis)` : ''}` : '',
        ].filter(Boolean).join(' | ');

        return `
            <article class="travel-pack-compact-card travel-pack-hotel-card">
                ${photo ? `<img src="${escapeHtml(photo)}" alt="" class="travel-pack-hotel-thumb" loading="lazy">` : ''}
                <div class="travel-pack-compact-content">
                    <span class="travel-pack-array-index">${index + 1}</span>
                    <div>
                        <h5>${escapeHtml(name || `Hotel ${index + 1}`)}</h5>
                        ${meta ? `<p class="travel-pack-card-meta">${escapeHtml(meta)}</p>` : ''}
                        ${description ? `<p>${escapeHtml(description)}</p>` : ''}
                    </div>
                </div>
            </article>
        `;
    };

    const hotelsFromMessageMetadata = (metadata) => {
        if (!metadata || metadata.last_list_type !== 'hotels') {
            return [];
        }

        const directHotels = normalizeList(metadata.hotels);
        if (directHotels.length > 0) {
            return directHotels;
        }

        const grouped = metadata.hotels_by_destination;
        if (!grouped || typeof grouped !== 'object') {
            return [];
        }

        return Object.entries(grouped).flatMap(([destination, items]) => normalizeList(items).map((hotel) => {
            if (hotel && typeof hotel === 'object' && !hotel.destination) {
                return { ...hotel, destination };
            }
            return hotel;
        }));
    };

    const renderMessageHotelCards = (message) => {
        const hotels = hotelsFromMessageMetadata(message.metadata || {});
        if (hotels.length === 0) {
            return '';
        }

        return `<div class="message-hotel-grid">${hotels.slice(0, 5).map(renderHotelCard).join('')}</div>`;
    };

    const renderActivityCard = (activity, index) => {
        const name = activity && typeof activity === 'object' ? valueFrom(activity, ['name', 'Name', 'title'], `Activite ${index + 1}`) : activity;
        const location = valueFrom(activity, ['location', 'Location', 'place'], '');
        const time = valueFrom(activity, ['time', 'Time', 'timing', 'Timing', 'moment'], '');
        const description = shortText(valueFrom(activity, ['description', 'Description', 'notes'], ''), 170);
        const meta = [location, time].filter(Boolean).join(' | ');

        return `
            <article class="travel-pack-compact-card">
                <div class="travel-pack-compact-content">
                    <span class="travel-pack-array-index">${index + 1}</span>
                    <div>
                        <h5>${escapeHtml(name || `Activite ${index + 1}`)}</h5>
                        ${meta ? `<p class="travel-pack-card-meta">${escapeHtml(meta)}</p>` : ''}
                        ${description ? `<p>${escapeHtml(description)}</p>` : ''}
                    </div>
                </div>
            </article>
        `;
    };

    const renderSchedule = (schedule) => {
        const days = normalizeList(schedule);
        if (days.length === 0) {
            return '<span class="travel-pack-muted">Planning a completer.</span>';
        }

        return `<div class="travel-pack-schedule">${days.map((day, index) => {
            if (!day || typeof day !== 'object') {
                return `<article class="travel-pack-day"><strong>Jour ${index + 1}</strong><p>${escapeHtml(day)}</p></article>`;
            }
            const dayLabel = valueFrom(day, ['day', 'Day'], index + 1);
            const slots = [
                ['Matin', valueFrom(day, ['morning', 'Morning'], '')],
                ['Apres-midi', valueFrom(day, ['afternoon', 'Afternoon'], '')],
                ['Soir', valueFrom(day, ['evening', 'Evening'], '')],
            ].filter(([, text]) => text);
            return `
                <article class="travel-pack-day">
                    <strong>Jour ${escapeHtml(dayLabel)}</strong>
                    ${slots.map(([label, text]) => `<p><span>${label}</span>${escapeHtml(text)}</p>`).join('')}
                </article>
            `;
        }).join('')}</div>`;
    };

    const renderTravelPack = (payload) => {
        const card = payload || {};
        const destinations = normalizeList(card.destinations || card.destination || card.destination_name);
        const hotels = normalizeList(card.selected_hotels || card.hotels);
        const activities = normalizeList(card.selected_activities || card.activities);
        const tripLength = card.trip_length_days || card.duration_days || '';
        const dateRange = card.date_range && typeof card.date_range === 'object'
            ? [card.date_range.depart_date, card.date_range.return_date].filter(Boolean).join(' -> ')
            : '';

        return `
            <section class="travel-pack-section travel-pack-overview">
                <h4>Resume</h4>
                <div class="travel-pack-summary-grid">
                    <div><strong>Destinations</strong>${renderChipList(destinations)}</div>
                    ${tripLength ? `<div><strong>Duree</strong><span>${escapeHtml(tripLength)} jours</span></div>` : ''}
                    ${dateRange ? `<div><strong>Dates</strong><span>${escapeHtml(dateRange)}</span></div>` : ''}
                </div>
            </section>
            <section class="travel-pack-section">
                <h4>Hotels retenus</h4>
                ${hotels.length ? `<div class="travel-pack-card-grid">${hotels.map(renderHotelCard).join('')}</div>` : '<span class="travel-pack-muted">Aucun hotel retenu pour le moment.</span>'}
            </section>
            <section class="travel-pack-section">
                <h4>Activites ajoutees au pack</h4>
                ${activities.length ? `<div class="travel-pack-card-grid">${activities.map(renderActivityCard).join('')}</div>` : '<span class="travel-pack-muted">Aucune activite ajoutee pour le moment.</span>'}
            </section>
            <section class="travel-pack-section">
                <h4>Programme suggere</h4>
                ${renderSchedule(card.schedule)}
            </section>
        `;
    };

    const hasCardValue = (value) => {
        if (value === null || value === undefined || value === '') {
            return false;
        }
        if (Array.isArray(value)) {
            return value.some(hasCardValue);
        }
        if (typeof value === 'object') {
            return Object.values(value).some(hasCardValue);
        }
        return true;
    };

    const renderCardValue = (value, depth = 0) => {
        if (Array.isArray(value)) {
            if (value.length === 0) {
                return '<span class="travel-pack-muted">Aucune entree.</span>';
            }
            const items = value.filter(hasCardValue);
            if (items.length === 0) {
                return '<span class="travel-pack-muted">Aucune entree.</span>';
            }
            return `<div class="travel-pack-array">${items.map((item, index) => `
                <div class="travel-pack-array-item">
                    <span class="travel-pack-array-index">${index + 1}</span>
                    <div>${renderCardValue(item, depth + 1)}</div>
                </div>
            `).join('')}</div>`;
        }
        if (value && typeof value === 'object') {
            const entries = Object.entries(value).filter(([, item]) => hasCardValue(item));
            if (entries.length === 0) {
                return '<span class="travel-pack-muted">Non renseigne.</span>';
            }

            if (depth === 0) {
                return entries.map(([key, item]) => `
                    <section class="travel-pack-section">
                        <h4>${escapeHtml(formatCardKey(key))}</h4>
                        <div>${renderCardValue(item, depth + 1)}</div>
                    </section>
                `).join('');
            }

            return entries.map(([key, item]) => `
                <div class="travel-pack-field">
                    <strong>${escapeHtml(formatCardKey(key))}</strong>
                    <span>${renderCardValue(item, depth + 1)}</span>
                </div>
            `).join('');
        }

        return `<span class="travel-pack-text">${escapeHtml(value || '')}</span>`;
    };

    const renderHistory = () => {
        if (!history) {
            return;
        }

        const shownSessions = visibleSessions();
        if (shownSessions.length === 0) {
            history.innerHTML = historyFilter === 'favorites'
                ? '<div class="history-empty">Aucune discussion favorite.</div>'
                : '<div class="history-empty">Aucune discussion pour le moment.</div>';
            return;
        }

        history.innerHTML = shownSessions.map((session) => `
            <article class="history-item ${activeSession && activeSession.id === session.id ? 'is-active history-item-active' : ''} ${session.isFavorite ? 'is-favorite' : ''}" data-session-id="${escapeHtml(session.id)}">
                <div class="history-item-main">
                    <div class="history-item-title-row">
                        <button
                            type="button"
                            class="history-favorite-button ${session.isFavorite ? 'is-active' : ''}"
                            data-toggle-favorite="${escapeHtml(session.id)}"
                            aria-label="${session.isFavorite ? 'Retirer des favoris' : 'Ajouter aux favoris'}"
                            aria-pressed="${session.isFavorite ? 'true' : 'false'}"
                        >${session.isFavorite ? '&#9733;' : '&#9734;'}</button>
                        <div class="history-item-title">${escapeHtml(session.title || 'Discussion')}</div>
                    </div>
                    <div class="history-item-meta">${escapeHtml(session.lastActivity || 'Maintenant')} | ${(session.messages || []).length} messages</div>
                </div>
                <div class="history-item-actions">
                    <button type="button" class="history-delete-button" data-delete-session="${escapeHtml(session.id)}">Supprimer</button>
                </div>
            </article>
        `).join('');
    };

    const renderMessages = () => {
        if (!messages) {
            return;
        }

        if (!activeSession) {
            messages.innerHTML = `
                <div class="chat-empty-state">
                    <strong>Commencez une nouvelle discussion</strong>
                    <span>Travel-AI peut proposer des destinations, budgets et idees de sejour.</span>
                </div>
            `;
            return;
        }

        const sessionMessages = activeSession.messages || [];
        if (sessionMessages.length === 0) {
            messages.innerHTML = `
                <div class="chat-empty-state">
                    <strong>Nouvelle discussion prete</strong>
                    <span>Posez une question sur un budget, une destination ou une ambiance de voyage.</span>
                </div>
            `;
            return;
        }

        messages.innerHTML = sessionMessages.map((message) => `
            <div class="message-row ${message.role === 'user' ? 'is-user' : 'is-ai'}">
                <div class="message-bubble ${message.role === 'user' ? 'message-user' : 'message-ai'}">
                    <p class="message-text">${escapeHtml(message.content)}</p>
                    ${message.role === 'user' ? '' : renderMessageHotelCards(message)}
                    <time class="message-time ${message.role === 'user' ? 'message-time-user' : 'message-time-ai'}">${escapeHtml(message.time || nowTime())}</time>
                </div>
            </div>
        `).join('');
        messages.scrollTop = messages.scrollHeight;
    };

    const renderCards = () => {
        const cards = activeCards();
        if (packStatus) {
            packStatus.textContent = activeSession ? `${cards.length} pack(s) sauvegarde(s)` : 'Selectionnez une discussion.';
        }
        if (generateCardButton) {
            generateCardButton.disabled = !activeSession || isSending;
            generateCardButton.textContent = cards.length > 0 ? 'Regenerer' : 'Generer';
        }
        if (!cardList || !cardDetail) {
            return;
        }
        if (!activeSession) {
            cardList.innerHTML = '';
            cardDetail.innerHTML = '<div class="chat-empty-state travel-pack-empty"><strong>Aucun pack</strong><span>Selectionnez une discussion pour voir ses packs.</span></div>';
            return;
        }
        if (cards.length === 0) {
            cardList.innerHTML = '<div class="history-empty">Aucun pack genere.</div>';
            cardDetail.innerHTML = '<div class="chat-empty-state travel-pack-empty"><strong>Pack final pret a generer</strong><span>Demandez des hotels et activites, puis genere le pack final.</span></div>';
            return;
        }

        const current = cards[0];
        cardList.innerHTML = cards.map((card, index) => `
            <article class="travel-pack-item ${index === 0 ? 'is-active' : ''}" data-card-id="${escapeHtml(card.id)}">
                <strong>${escapeHtml(cardTitle(card))}</strong>
                <span>${escapeHtml(card.updatedAt || card.createdAt || 'Maintenant')} | ${escapeHtml(card.status || 'generated')}</span>
                <button type="button" data-delete-card="${escapeHtml(card.id)}">Supprimer</button>
            </article>
        `).join('');
        cardDetail.innerHTML = `
            <article class="travel-pack-card">
                <div class="travel-pack-card-top">
                    <div>
                        <span class="travel-pack-label">Pack courant</span>
                        <h3>${escapeHtml(cardTitle(current))}</h3>
                    </div>
                    <span class="travel-pack-pill">${escapeHtml(current.status || 'generated')}</span>
                </div>
                <div class="travel-pack-body">${renderTravelPack(current.card || {})}</div>
            </article>
        `;
    };

    const setActiveSession = (session) => {
        activeSession = session;
        renderHistory();
        renderMessages();
        renderCards();
    };

    const refreshSession = (session) => {
        if (!session) {
            return;
        }

        const index = sessions.findIndex((item) => item.id === session.id);
        if (index >= 0) {
            sessions[index] = session;
        } else {
            sessions.unshift(session);
        }
        sortSessions();
        setActiveSession(sessions.find((item) => item.id === session.id));
    };

    const createSession = async () => {
        const response = await fetch('/chat/sessions', { method: 'POST', headers: { 'Accept': 'application/json' } });
        const data = await response.json();
        if (!data.ok || !data.session) {
            throw new Error(data.error || 'Creation impossible');
        }
        refreshSession(data.session);

        return data.session;
    };

    const SpeechRecognition = window.SpeechRecognition || window.webkitSpeechRecognition;
    if (speechButton && !SpeechRecognition) {
        speechButton.disabled = true;
        speechButton.classList.add('is-unavailable');
        speechButton.setAttribute('title', 'Dictee vocale indisponible sur ce navigateur');
        setSpeechStatus('Dictee vocale indisponible sur ce navigateur. Vous pouvez toujours taper votre message.');
    } else if (speechButton && SpeechRecognition) {
        recognition = new SpeechRecognition();
        recognition.lang = 'fr-FR';
        recognition.continuous = true;
        recognition.interimResults = true;
        recognition.maxAlternatives = 1;

        recognition.onstart = () => {
            speechBaseValue = input ? input.value.trim() : '';
            speechTranscript = '';
            speechHeardAudio = false;
            speechHadError = false;
            setListeningState(true);
            setSpeechStatus('Ecoute en cours... parlez maintenant, puis marquez une courte pause.');
            clearSpeechTimer();
            speechStopTimer = window.setTimeout(() => {
                if (recognition && isListening) {
                    recognition.stop();
                }
            }, 14000);
        };

        recognition.onaudiostart = () => {
            speechHeardAudio = true;
            setSpeechStatus('Micro actif. Je vous ecoute...');
        };

        recognition.onsoundstart = () => {
            speechHeardAudio = true;
            setSpeechStatus('Son detecte, transcription en cours...');
        };

        recognition.onspeechstart = () => {
            speechHeardAudio = true;
            setSpeechStatus('Parole detectee, continuez...');
        };

        recognition.onresult = (event) => {
            let finalTranscript = '';
            let interimTranscript = '';
            for (let index = 0; index < event.results.length; index++) {
                const result = event.results[index];
                const text = result[0] ? result[0].transcript.trim() : '';
                if (result.isFinal) {
                    finalTranscript += ` ${text}`;
                } else {
                    interimTranscript += ` ${text}`;
                }
            }

            speechTranscript = `${finalTranscript} ${interimTranscript}`.trim();
            if (input) {
                input.value = [speechBaseValue, speechTranscript].filter(Boolean).join(' ');
                input.focus();
            }
            if (speechTranscript) {
                setSpeechStatus(`Texte detecte : "${speechTranscript}"`);
            }
        };

        recognition.onnomatch = () => {
            setSpeechStatus('Son detecte, mais aucun texte clair. Rapprochez-vous du micro et reessayez.');
        };

        recognition.onerror = (event) => {
            speechHadError = true;
            const messages = {
                'not-allowed': 'Micro refuse. Autorisez le micro dans le navigateur puis reessayez.',
                'service-not-allowed': 'Service vocal bloque par le navigateur. Essayez Chrome/Edge sur localhost.',
                'no-speech': 'Aucune parole detectee. Cliquez sur Voix, parlez clairement, puis faites une pause.',
                'audio-capture': 'Aucun micro detecte. Verifiez le micro du navigateur ou du systeme.',
                network: 'Service de dictee indisponible. Pour du 100% local, il faudra ajouter Whisper/Vosk cote serveur.',
            };
            setSpeechStatus(messages[event.error] || 'Dictee vocale interrompue. Reessayez dans quelques secondes.');
        };

        recognition.onend = () => {
            clearSpeechTimer();
            setListeningState(false);
            if (speechTranscript || (input && input.value.trim() !== speechBaseValue)) {
                setSpeechStatus('Texte detecte. Verifiez puis envoyez.');
            } else if (!speechHadError && speechHeardAudio) {
                setSpeechStatus('J ai entendu du son, mais aucun texte clair. Essayez une phrase courte en francais.');
            } else if (!speechHadError) {
                setSpeechStatus('Aucun son detecte. Verifiez l autorisation micro puis reessayez.');
            } else {
                speechHadError = false;
            }
        };
    }

    if (sessions.length > 0) {
        activeSession = sessions[0];
    }
    renderHistory();
    renderMessages();
    renderCards();

    filterButtons.forEach((button) => {
        button.addEventListener('click', () => {
            historyFilter = button.dataset.historyFilter || 'all';
            filterButtons.forEach((entry) => entry.classList.toggle('is-active', entry === button));
            renderHistory();
        });
    });

    history?.addEventListener('click', async (event) => {
        const favoriteButton = event.target.closest('[data-toggle-favorite]');
        if (favoriteButton) {
            event.stopPropagation();
            const sessionId = favoriteButton.dataset.toggleFavorite;
            const session = sessions.find((entry) => entry.id === sessionId);
            if (!session) {
                return;
            }

            favoriteButton.disabled = true;
            try {
                const response = await fetch(`/chat/sessions/${encodeURIComponent(sessionId)}/favorite`, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
                    body: JSON.stringify({ favorite: !Boolean(session.isFavorite) }),
                });
                const data = await response.json();
                if (data.session) {
                    refreshSession(data.session);
                }
            } finally {
                favoriteButton.disabled = false;
            }
            return;
        }

        const deleteButton = event.target.closest('[data-delete-session]');
        if (deleteButton) {
            event.stopPropagation();
            const sessionId = deleteButton.dataset.deleteSession;
            const response = await fetch(`/chat/sessions/${encodeURIComponent(sessionId)}/delete`, { method: 'POST', headers: { 'Accept': 'application/json' } });
            if (response.ok) {
                sessions = sessions.filter((session) => session.id !== sessionId);
                activeSession = sessions[0] || null;
                renderHistory();
                renderMessages();
                renderCards();
            }
            return;
        }

        const item = event.target.closest('[data-session-id]');
        if (!item) {
            return;
        }

        const session = sessions.find((entry) => entry.id === item.dataset.sessionId);
        if (session) {
            setActiveSession(session);
        }
    });

    newButton?.addEventListener('click', async () => {
        try {
            await createSession();
            input?.focus();
        } catch (error) {
            if (status) {
                status.textContent = 'Impossible de creer une discussion pour le moment.';
            }
        }
    });

    speechButton?.addEventListener('click', () => {
        if (!recognition || isSending) {
            return;
        }

        if (isListening) {
            clearSpeechTimer();
            recognition.stop();
            return;
        }

        try {
            recognition.start();
        } catch (error) {
            setSpeechStatus('Dictee deja en cours. Patientez une seconde puis reessayez.');
        }
    });

    generateCardButton?.addEventListener('click', async () => {
        if (!activeSession || isSending) {
            return;
        }
        isSending = true;
        renderCards();
        if (packStatus) {
            packStatus.textContent = 'Generation du pack...';
        }
        try {
            const response = await fetch(`/chat/sessions/${encodeURIComponent(activeSession.id)}/cards/generate`, {
                method: 'POST',
                headers: { 'Accept': 'application/json' },
            });
            const data = await response.json();
            if (data.session) {
                refreshSession(data.session);
            } else if (packStatus) {
                packStatus.textContent = data.error || 'Generation impossible.';
            }
        } catch (error) {
            if (packStatus) {
                packStatus.textContent = 'Generation impossible pour le moment.';
            }
        } finally {
            isSending = false;
            renderCards();
        }
    });

    cardList?.addEventListener('click', async (event) => {
        const deleteButton = event.target.closest('[data-delete-card]');
        if (!deleteButton || !activeSession) {
            return;
        }
        event.stopPropagation();
        const cardId = deleteButton.dataset.deleteCard;
        const response = await fetch(`/chat/sessions/${encodeURIComponent(activeSession.id)}/cards/${encodeURIComponent(cardId)}/delete`, {
            method: 'POST',
            headers: { 'Accept': 'application/json' },
        });
        const data = await response.json();
        if (data.session) {
            refreshSession(data.session);
        }
    });

    form?.addEventListener('submit', async (event) => {
        event.preventDefault();
        if (!input || input.value.trim() === '' || isSending) {
            return;
        }

        const prompt = input.value.trim();
        input.value = '';
        if (recognition && isListening) {
            recognition.stop();
        }
        isSending = true;
        if (speechButton) {
            speechButton.disabled = true;
        }
        if (!activeSession) {
            try {
                activeSession = await createSession();
            } catch (error) {
                isSending = false;
                input.value = prompt;
                if (status) {
                    status.textContent = 'Discussion indisponible. Reessayez dans quelques secondes.';
                }
                return;
            }
        }

        activeSession.messages = activeSession.messages || [];
        activeSession.messages.push({ role: 'user', content: prompt, time: nowTime() });
        activeSession.messages.push({ role: 'assistant', content: 'Travel-AI reflechit...', time: nowTime(), pending: true });
        renderMessages();
        renderCards();
        if (status) {
            status.textContent = 'Travel-AI ecrit une reponse...';
        }

        try {
            const response = await fetch(`/chat/sessions/${encodeURIComponent(activeSession.id)}/messages`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
                body: JSON.stringify({ message: prompt }),
            });
            const data = await response.json();
            if (data.session) {
                refreshSession(data.session);
            } else if (data.error) {
                activeSession.messages = activeSession.messages.filter((message) => !message.pending);
                activeSession.messages.push({ role: 'assistant', content: data.error, time: nowTime() });
                renderMessages();
            }
        } catch (error) {
            activeSession.messages = activeSession.messages.filter((message) => !message.pending);
            activeSession.messages.push({ role: 'assistant', content: 'Mode hors ligne: impossible de joindre Travel-AI pour le moment.', time: nowTime() });
            renderMessages();
        } finally {
            isSending = false;
            if (speechButton && SpeechRecognition) {
                speechButton.disabled = false;
            }
            renderCards();
            if (status) {
                status.textContent = 'Assistant voyage | Reponses rapides et idees de sejour';
            }
            setSpeechStatus();
            input.focus();
        }
    });

    if (pendingPrompt && input && form && !pendingPromptSubmitted) {
        pendingPromptSubmitted = true;
        input.value = pendingPrompt;
        window.history.replaceState({}, document.title, window.location.pathname);
        window.setTimeout(() => {
            form.requestSubmit();
        }, 120);
    }
});
