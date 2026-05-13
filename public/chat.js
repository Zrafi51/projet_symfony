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

    const renderCardValue = (value) => {
        if (Array.isArray(value)) {
            if (value.length === 0) {
                return '<span class="travel-pack-muted">Aucune entree.</span>';
            }
            return `<ul>${value.map((item) => `<li>${renderCardValue(item)}</li>`).join('')}</ul>`;
        }
        if (value && typeof value === 'object') {
            const entries = Object.entries(value);
            if (entries.length === 0) {
                return '<span class="travel-pack-muted">Non renseigne.</span>';
            }
            return entries.map(([key, item]) => `
                <div class="travel-pack-field">
                    <strong>${escapeHtml(key.replace(/_/g, ' '))}</strong>
                    <span>${renderCardValue(item)}</span>
                </div>
            `).join('');
        }

        return escapeHtml(value || '');
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
                <div class="travel-pack-body">${renderCardValue(current.card || {})}</div>
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
        isSending = true;
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
            renderCards();
            if (status) {
                status.textContent = 'Assistant voyage | Reponses rapides et idees de sejour';
            }
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
