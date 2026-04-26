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
    const pendingPrompt = (page.dataset.pendingPrompt || '').trim();
    let sessions = [];
    let activeSession = null;
    let isSending = false;
    let pendingPromptSubmitted = false;

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

    const renderHistory = () => {
        if (!history) {
            return;
        }

        if (sessions.length === 0) {
            history.innerHTML = '<div class="history-empty">Aucune discussion pour le moment.</div>';
            return;
        }

        history.innerHTML = sessions.map((session) => `
            <article class="history-item ${activeSession && activeSession.id === session.id ? 'is-active history-item-active' : ''}" data-session-id="${escapeHtml(session.id)}">
                <div>
                    <div class="history-item-title">${escapeHtml(session.title || 'Discussion')}</div>
                    <div class="history-item-meta">${escapeHtml(session.lastActivity || 'Maintenant')} | ${(session.messages || []).length} messages</div>
                </div>
                <button type="button" class="history-delete-button" data-delete-session="${escapeHtml(session.id)}">Supprimer</button>
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

    const setActiveSession = (session) => {
        activeSession = session;
        renderHistory();
        renderMessages();
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

    history?.addEventListener('click', async (event) => {
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
