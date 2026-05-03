/* ===== Forum Agence de Voyage - JavaScript ===== */

document.addEventListener('DOMContentLoaded', function () {

    // ===== DARK/LIGHT THEME TOGGLE =====
    const themeToggle = document.getElementById('themeToggle');
    const themeIcon = document.getElementById('themeIcon');
    const html = document.documentElement;

    // Load saved theme
    const savedTheme = localStorage.getItem('theme') || 'light';
    html.setAttribute('data-theme', savedTheme);
    updateThemeIcon(savedTheme);

    if (themeToggle) {
        themeToggle.addEventListener('click', function () {
            const current = html.getAttribute('data-theme');
            const next = current === 'light' ? 'dark' : 'light';

            // Smooth fade transition
            document.body.style.opacity = '0.95';
            setTimeout(() => {
                html.setAttribute('data-theme', next);
                localStorage.setItem('theme', next);
                updateThemeIcon(next);
                document.body.style.opacity = '1';
            }, 150);
        });
    }

    function updateThemeIcon(theme) {
        if (themeIcon) {
            themeIcon.className = theme === 'dark' ? 'fas fa-sun' : 'fas fa-moon';
        }
    }

    // ===== LIKE BUTTON (AJAX toggle — one like per user per post) =====
    document.querySelectorAll('.like-btn').forEach(function (btn) {
        btn.addEventListener('click', function () {
            if (btn.dataset.busy === '1') return; // prevent double-click races
            btn.dataset.busy = '1';

            const postId = btn.dataset.postId;
            const countSpan = btn.querySelector('.likes-count');
            const label = btn.querySelector('.like-label');
            const icon = btn.querySelector('i');

            fetch('/forum/post/' + postId + '/like', {
                method: 'POST',
                headers: { 'X-Requested-With': 'XMLHttpRequest' },
            })
                .then(function (res) {
                    if (res.status === 401) {
                        window.location.href = '/login';
                        throw new Error('not authenticated');
                    }
                    return res.json();
                })
                .then(function (data) {
                    if (countSpan) {
                        countSpan.textContent = '(' + data.likes + ')';
                    }
                    // Live-update the stats bar above the button so the number
                    // changes immediately without a page reload.
                    document.querySelectorAll('.post-likes-stat[data-post-id="' + postId + '"]')
                        .forEach(function (el) { el.textContent = data.likes; });
                    if (data.liked) {
                        // Toggled ON
                        btn.classList.remove('btn-light');
                        btn.classList.add('btn-danger', 'liked');
                        btn.setAttribute('aria-pressed', 'true');
                        btn.dataset.liked = '1';
                        if (icon) {
                            icon.classList.remove('far');
                            icon.classList.add('fas');
                        }
                        if (label) label.textContent = 'Aimé';
                    } else {
                        // Toggled OFF
                        btn.classList.remove('btn-danger', 'liked');
                        btn.classList.add('btn-light');
                        btn.setAttribute('aria-pressed', 'false');
                        btn.dataset.liked = '0';
                        if (icon) {
                            icon.classList.remove('fas');
                            icon.classList.add('far');
                        }
                        if (label) label.textContent = "J'aime";
                    }
                })
                .catch(function (err) { console.error('Like error:', err); })
                .finally(function () { btn.dataset.busy = '0'; });
        });
    });

    // ===== REACTION BUTTONS (AJAX) =====
    document.querySelectorAll('.react-btn').forEach(function (btn) {
        btn.addEventListener('click', function () {
            const postId = this.dataset.postId;
            const reactionType = this.dataset.reaction;
            const reactionTypesMap = { '❤️': "J'adore", '😂': 'Haha', '😮': 'Wow', '😢': 'Triste', '✈️': 'Voyage' };

            const formData = new FormData();
            formData.append('reaction_type', reactionType);

            fetch('/forum/post/' + postId + '/react', {
                method: 'POST',
                body: formData,
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                },
            })
                .then(function (res) { return res.json(); })
                .then(function (data) {
                    if (data.action === 'removed') {
                        btn.classList.remove('active', 'btn-primary', 'text-white');
                    } else {
                        // Remove active from siblings
                        const parent = btn.closest('.card-body, .d-flex');
                        if (parent) {
                            parent.querySelectorAll('.react-btn').forEach(function (b) {
                                b.classList.remove('active', 'btn-primary', 'text-white');
                            });
                        }
                        btn.classList.add('active', 'btn-primary', 'text-white');
                    }

                    // Update reaction summary on EVERY card showing this post
                    // (feed and modal can both render the same post on screen).
                    if (data.counts) {
                        var html = '';
                        for (var emoji in data.counts) {
                            html += '<span class="me-1" title="' + (reactionTypesMap[emoji] || emoji) + '">'
                                  + emoji + ' ' + data.counts[emoji] + '</span>';
                        }
                        if (!html) html = '<small class="text-muted">Aucune réaction</small>';
                        // Find every reaction-summary that belongs to this post.
                        // The card container has `id="post-<id>"`; if not, fall back
                        // to scoping via the clicked button's nearest card.
                        var post = document.getElementById('post-' + postId);
                        var scope = post || btn.closest('.post-card, .card');
                        if (scope) {
                            scope.querySelectorAll('.reaction-summary').forEach(function (s) {
                                s.innerHTML = html;
                            });
                        }
                    }
                })
                .catch(function (err) { console.error('Reaction error:', err); });
        });
    });

    // ===== TOGGLE COMMENTS SECTION =====
    document.querySelectorAll('.toggle-comments').forEach(function (btn) {
        btn.addEventListener('click', function () {
            const targetId = this.dataset.target;
            const section = document.getElementById(targetId);
            if (section) {
                const isVisible = section.style.display !== 'none';
                section.style.display = isVisible ? 'none' : 'block';
                if (!isVisible) {
                    // Focus the comment input
                    const input = section.querySelector('input[type="text"]');
                    if (input) input.focus();
                }
            }
        });
    });

    // ===== ADD COMMENT (AJAX) — no page refresh =====
    // Escape user-supplied strings before injecting into HTML to prevent XSS.
    function escapeHtml(s) {
        return String(s)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#39;');
    }

    function buildCommentNode(c, currentUser, photoBase) {
        var avatar;
        if (c.photoUrl) {
            avatar = '<img src="' + escapeHtml(c.photoUrl) + '" '
                   + 'class="rounded-circle me-2 flex-shrink-0" '
                   + 'style="width:32px;height:32px;object-fit:cover;" alt="' + escapeHtml(c.auteur) + '">';
        } else {
            avatar = '<div class="rounded-circle bg-secondary d-flex align-items-center justify-content-center me-2 flex-shrink-0" '
                   + 'style="width:32px;height:32px;">'
                   + '<span class="text-white small">' + escapeHtml(c.auteur.charAt(0).toUpperCase()) + '</span>'
                   + '</div>';
        }
        var canDelete = c.auteur === currentUser || c.isAdmin;
        var deleteForm = canDelete
            ? '<form action="' + escapeHtml(c.deleteUrl) + '" method="post" class="ms-1">'
                + '<input type="hidden" name="_token" value="' + escapeHtml(c.deleteCsrf) + '">'
                + '<button type="submit" class="btn btn-link btn-sm text-danger p-0" title="Supprimer" '
                +         'onclick="return confirm(\'Supprimer ce commentaire ?\')">'
                + '<i class="fas fa-times"></i>'
                + '</button>'
                + '</form>'
            : '';

        var wrapper = document.createElement('div');
        wrapper.className = 'd-flex mb-2 comment-item';
        wrapper.innerHTML = avatar
            + '<div class="flex-grow-1">'
            + '  <div class="bg-light rounded p-2 comment-bubble">'
            + '    <strong class="small">' + escapeHtml(c.auteur) + '</strong>'
            + '    <p class="mb-0 small">' + escapeHtml(c.contenu) + '</p>'
            + '  </div>'
            + '  <small class="text-muted ms-2">' + escapeHtml(c.dateFormatted) + '</small>'
            + '</div>'
            + deleteForm;
        return wrapper;
    }

    document.querySelectorAll('.comments-section form[action*="/comment"]').forEach(function (form) {
        // Skip the delete form (single hidden _token, no contenu input)
        if (!form.querySelector('input[name="comment[contenu]"]')) return;

        form.addEventListener('submit', function (e) {
            e.preventDefault();

            var input = form.querySelector('input[name="comment[contenu]"]');
            var section = form.closest('.comments-section');
            var postIdMatch = section ? section.id.match(/comments-(\d+)/) : null;
            var postId = postIdMatch ? postIdMatch[1] : null;
            var contenu = (input.value || '').trim();

            if (!contenu) {
                input.focus();
                return;
            }

            var btn = form.querySelector('button[type="submit"]');
            var prevHtml = btn ? btn.innerHTML : '';
            if (btn) {
                btn.disabled = true;
                btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
            }

            fetch(form.action, {
                method: 'POST',
                body: new FormData(form),
                headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' },
                credentials: 'same-origin',
            })
                .then(function (res) { return res.json().then(function (j) { return { status: res.status, body: j }; }); })
                .then(function (r) {
                    if (!r.body.ok) {
                        var msg = (r.body.errors && r.body.errors[0]) || 'Erreur lors de l\'ajout du commentaire.';
                        alert(msg);
                        return;
                    }

                    // Remove the "no comments yet" placeholder if present.
                    var emptyMsg = section.querySelector('p.text-muted.text-center');
                    if (emptyMsg) emptyMsg.remove();

                    var currentUserEl = document.querySelector('[data-current-user]');
                    var currentUser = currentUserEl ? currentUserEl.dataset.currentUser : r.body.auteur;
                    var node = buildCommentNode(r.body, currentUser, '/uploads/profiles/');
                    section.appendChild(node);

                    // Reset the input
                    input.value = '';

                    // Update the comment count badges/labels for this post.
                    if (postId) {
                        document.querySelectorAll('.post-comments-stat[data-post-id="' + postId + '"]')
                            .forEach(function (el) { el.textContent = r.body.totalComments; });
                        document.querySelectorAll('.toggle-comments[data-target="comments-' + postId + '"]')
                            .forEach(function (el) {
                                el.innerHTML = '<i class="fas fa-comment me-1"></i>Commenter (' + r.body.totalComments + ')';
                            });
                    }

                    // Scroll the new comment into view.
                    node.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
                })
                .catch(function (err) {
                    console.error('Comment error:', err);
                    alert('Erreur réseau lors de l\'ajout du commentaire.');
                })
                .finally(function () {
                    if (btn) {
                        btn.disabled = false;
                        btn.innerHTML = prevHtml;
                    }
                });
        });
    });

    // ===== AI ITINERARY PLANNER =====
    // Takes a free-form French prompt (destination, duration, budget, interests)
    // and returns a structured Markdown plan from /ai/suggest?type=itinerary.
    const aiSuggestBtn = document.getElementById('aiSuggestBtn');
    const aiPrompt = document.getElementById('aiPrompt');
    const aiResult = document.getElementById('aiResult');
    const aiModalEl = document.getElementById('aiItineraryModal');
    const aiModalBody = document.getElementById('aiItineraryBody');
    const aiModalSource = document.getElementById('aiItinerarySource');
    const aiCopyBtn = document.getElementById('aiItineraryCopy');
    var lastItineraryText = '';

    // Minimal Markdown → safe HTML renderer, scoped to itinerary output.
    // Supports: ## / ### headings, **bold**, bullet lists (-), paragraphs, line breaks.
    function renderItineraryMarkdown(md) {
        if (!md) return '';
        // 1) HTML-escape so LLM output can't inject markup.
        var escaped = md
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#39;');

        var lines = escaped.split(/\r?\n/);
        var html = '';
        var i = 0;
        while (i < lines.length) {
            var line = lines[i];
            var trimmed = line.trim();

            if (trimmed === '') { i++; continue; }

            // Headings
            var h2 = trimmed.match(/^##\s+(.*)$/);
            if (h2) { html += '<h2>' + inline(h2[1]) + '</h2>'; i++; continue; }
            var h3 = trimmed.match(/^###\s+(.*)$/);
            if (h3) { html += '<h3>' + inline(h3[1]) + '</h3>'; i++; continue; }

            // Bullet list (consume consecutive '- ' or '* ' lines)
            if (/^[-*]\s+/.test(trimmed)) {
                html += '<ul>';
                while (i < lines.length && /^[-*]\s+/.test(lines[i].trim())) {
                    var item = lines[i].trim().replace(/^[-*]\s+/, '');
                    html += '<li>' + inline(item) + '</li>';
                    i++;
                }
                html += '</ul>';
                continue;
            }

            // Horizontal rule
            if (/^---+$/.test(trimmed)) { html += '<hr>'; i++; continue; }

            // Paragraph (consume lines until blank / block start)
            var para = [trimmed];
            i++;
            while (i < lines.length) {
                var next = lines[i].trim();
                if (next === '' || /^##\s+/.test(next) || /^###\s+/.test(next) || /^[-*]\s+/.test(next)) break;
                para.push(next);
                i++;
            }
            html += '<p>' + inline(para.join(' ')) + '</p>';
        }
        return html;

        function inline(s) {
            // **bold**
            s = s.replace(/\*\*([^*]+)\*\*/g, '<strong>$1</strong>');
            // *italic* (avoid matching our bold markers — already consumed above)
            s = s.replace(/(^|[^*])\*([^*\n]+)\*/g, '$1<em>$2</em>');
            // `code`
            s = s.replace(/`([^`]+)`/g, '<code>$1</code>');
            return s;
        }
    }

    function openItineraryModal() {
        if (aiModalEl && typeof bootstrap !== 'undefined') {
            var modal = bootstrap.Modal.getOrCreateInstance(aiModalEl);
            modal.show();
        }
    }

    function setItineraryLoading() {
        if (!aiModalBody) return;
        aiModalBody.innerHTML =
            '<div class="text-center py-5">' +
            '<div class="spinner-border text-info" role="status"></div>' +
            '<p class="mt-3 text-muted small">L\'IA prépare ton voyage…</p>' +
            '</div>';
        if (aiModalSource) aiModalSource.textContent = '';
    }

    function showItineraryError(msg) {
        if (!aiModalBody) return;
        aiModalBody.innerHTML =
            '<div class="alert alert-danger mb-0">' +
            '<i class="fas fa-exclamation-triangle me-2"></i>' + msg +
            '</div>';
    }

    function runItinerary(prompt) {
        if (!prompt) {
            if (aiResult) aiResult.innerHTML = '<div class="alert alert-warning py-1 small mb-0">Décris ton voyage en une phrase.</div>';
            return;
        }

        if (aiResult) aiResult.innerHTML = '<div class="small text-muted mt-1"><i class="fas fa-spinner fa-spin me-1"></i>Génération en cours…</div>';
        openItineraryModal();
        setItineraryLoading();

        const formData = new FormData();
        formData.append('prompt', prompt);
        formData.append('type', 'itinerary');

        fetch('/ai/suggest', { method: 'POST', body: formData })
            .then(function (res) { return res.json(); })
            .then(function (data) {
                lastItineraryText = data && data.suggestion ? String(data.suggestion) : '';
                if (!lastItineraryText) {
                    showItineraryError("Pas d'itinéraire disponible pour cette demande. Essaie de préciser la destination et la durée.");
                    if (aiResult) aiResult.innerHTML = '';
                    return;
                }
                if (aiModalBody) aiModalBody.innerHTML = renderItineraryMarkdown(lastItineraryText);
                if (aiModalSource && data.source) {
                    aiModalSource.textContent = data.source === 'ai'
                        ? 'Généré par IA'
                        : 'Suggestion locale (IA indisponible)';
                }
                if (aiResult) aiResult.innerHTML = '<div class="small text-success mt-1"><i class="fas fa-check me-1"></i>Itinéraire prêt.</div>';
            })
            .catch(function () {
                showItineraryError('Erreur de connexion. Réessaie dans un instant.');
                if (aiResult) aiResult.innerHTML = '';
            });
    }

    if (aiSuggestBtn) {
        aiSuggestBtn.addEventListener('click', function () {
            runItinerary(aiPrompt ? aiPrompt.value.trim() : '');
        });

        if (aiPrompt) {
            aiPrompt.addEventListener('keydown', function (e) {
                // Enter submits, Shift+Enter inserts a newline (textarea behaviour).
                if (e.key === 'Enter' && !e.shiftKey) {
                    e.preventDefault();
                    aiSuggestBtn.click();
                }
            });
        }
    }

    document.querySelectorAll('.ai-example').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var ex = btn.getAttribute('data-example') || '';
            if (aiPrompt) aiPrompt.value = ex;
            runItinerary(ex);
        });
    });

    if (aiCopyBtn) {
        aiCopyBtn.addEventListener('click', function () {
            if (!lastItineraryText) return;
            var done = function () {
                var original = aiCopyBtn.innerHTML;
                aiCopyBtn.innerHTML = '<i class="fas fa-check me-1"></i>Copié';
                setTimeout(function () { aiCopyBtn.innerHTML = original; }, 1500);
            };
            if (navigator.clipboard && navigator.clipboard.writeText) {
                navigator.clipboard.writeText(lastItineraryText).then(done, function () {});
            } else {
                var ta = document.createElement('textarea');
                ta.value = lastItineraryText;
                document.body.appendChild(ta);
                ta.select();
                try { document.execCommand('copy'); done(); } catch (e) {}
                document.body.removeChild(ta);
            }
        });
    }

    // AI Suggest for post description — popup où l'utilisateur décrit son
    // intention en mots libres. Les chips ajoutent juste un mot-clé à ce qu'il
    // a déjà tapé (« poétique », « court », « corrige les fautes »…) ; ils ne
    // remplacent jamais son texte. Le contrôleur détecte ces mots-clés et
    // applique le bon mode (correction / réécriture / intégration / génération).
    document.querySelectorAll('.ai-suggest-btn').forEach(function (btn) {
        var scope    = btn.closest('.new-post-card') || btn.closest('form') || document;
        var panel    = scope.querySelector('.ai-prompt-panel');
        var input    = scope.querySelector('.ai-prompt-input');
        var genBtn   = scope.querySelector('.ai-prompt-generate');
        var cancel   = scope.querySelector('.ai-prompt-cancel');
        var closeBtn = scope.querySelector('.ai-prompt-close');
        var hint     = scope.querySelector('.ai-prompt-hint');
        var form     = btn.closest('form');
        var textarea = form ? form.querySelector('textarea') : null;
        if (!panel || !input || !genBtn || !textarea) return;

        function openPanel() {
            panel.classList.remove('d-none');
            setTimeout(function () { input.focus(); }, 50);
        }
        function closePanel() {
            panel.classList.add('d-none');
            if (hint) hint.classList.add('d-none');
        }

        btn.addEventListener('click', function () {
            if (panel.classList.contains('d-none')) openPanel();
            else closePanel();
        });
        if (cancel)   cancel.addEventListener('click', closePanel);
        if (closeBtn) closeBtn.addEventListener('click', closePanel);

        // Chip click — append the keyword (or toggle off if already present).
        // Never overwrites the user's typing.
        scope.querySelectorAll('.ai-chip').forEach(function (chip) {
            chip.addEventListener('click', function () {
                var word    = chip.dataset.chip || chip.textContent.trim();
                var current = input.value.trim();
                if (current.toLowerCase().indexOf(word.toLowerCase()) !== -1) {
                    chip.classList.toggle('active');
                    input.focus();
                    return;
                }
                input.value = current
                    ? current.replace(/[\s,;]+$/, '') + ', ' + word
                    : word;
                chip.classList.add('active');
                input.focus();
            });
        });

        input.addEventListener('keydown', function (e) {
            if (e.key === 'Enter' && !e.shiftKey) {
                e.preventDefault();
                genBtn.click();
            }
            if (e.key === 'Escape') closePanel();
        });

        genBtn.addEventListener('click', function () {
            var instruction = input.value.trim();
            var existing    = textarea.value.trim();
            if (!instruction && !existing) {
                input.focus();
                return;
            }
            // Prompt combiné : texte déjà écrit (contexte) + demande de l'utilisateur.
            var parts = [];
            if (existing)    parts.push('Texte déjà écrit : "' + existing + '"');
            if (instruction) parts.push('Demande : ' + instruction);
            var prompt = parts.join('\n');

            var originalGen = genBtn.innerHTML;
            genBtn.disabled = true;
            genBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i>Rédaction…';
            if (hint) hint.classList.remove('d-none');

            var fd = new FormData();
            fd.append('prompt', prompt);
            fd.append('type', btn.dataset.type || 'description');

            fetch('/ai/suggest', { method: 'POST', body: fd })
                .then(function (r) { return r.json(); })
                .then(function (data) {
                    if (data && data.suggestion) {
                        textarea.value = data.suggestion;
                        textarea.dispatchEvent(new Event('input', { bubbles: true }));
                        textarea.focus();
                        input.value = '';
                        scope.querySelectorAll('.ai-chip.active').forEach(function (c) {
                            c.classList.remove('active');
                        });
                        closePanel();
                    }
                })
                .catch(function () { /* silencieux */ })
                .finally(function () {
                    genBtn.disabled = false;
                    genBtn.innerHTML = originalGen;
                    if (hint) hint.classList.add('d-none');
                });
        });
    });

    // ===== AUTO-DISMISS FLASH MESSAGES =====
    document.querySelectorAll('.alert-dismissible').forEach(function (alert) {
        setTimeout(function () {
            var bsAlert = bootstrap.Alert.getOrCreateInstance(alert);
            bsAlert.close();
        }, 5000);
    });

    // ===== ENABLE TOOLTIPS =====
    var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    tooltipTriggerList.forEach(function (el) {
        new bootstrap.Tooltip(el);
    });
});

/* ===== MODERN IMAGE UPLOAD + VALIDATION + LIGHTBOX ===== */
document.addEventListener('DOMContentLoaded', function () {
    var uploadZone = document.getElementById('uploadZone');
    var fileInput = document.getElementById('imageFilesInput');
    var previewGrid = document.getElementById('imagePreview');
    var errorBox = document.getElementById('imageError');
    var form = document.getElementById('newPostForm');
    var publishBtn = document.getElementById('publishBtn');

    function showError(msg) {
        if (!errorBox) return;
        errorBox.classList.remove('d-none');
        errorBox.querySelector('span').textContent = msg;
    }

    function hideError() {
        if (!errorBox) return;
        errorBox.classList.add('d-none');
    }

    function renderPreviews() {
        if (!previewGrid || !fileInput) return;
        previewGrid.innerHTML = '';
        var files = fileInput.files;
        if (!files || files.length === 0) {
            if (uploadZone) uploadZone.classList.remove('has-files');
            return;
        }
        if (uploadZone) uploadZone.classList.add('has-files');

        Array.from(files).forEach(function (file, idx) {
            var reader = new FileReader();
            reader.onload = function (e) {
                var item = document.createElement('div');
                item.className = 'upload-preview-item';
                item.innerHTML =
                    '<img src="' + e.target.result + '" alt="">' +
                    '<button type="button" class="remove-preview" data-idx="' + idx + '" title="Retirer"><i class="fas fa-times"></i></button>';
                previewGrid.appendChild(item);
            };
            reader.readAsDataURL(file);
        });
    }

    // Native file selection — browser handles fileInput.files directly
    if (fileInput) {
        fileInput.addEventListener('change', function () {
            renderPreviews();
            if (fileInput.files && fileInput.files.length > 0) hideError();
        });
    }

    // Remove a single preview — rebuild fileInput.files via DataTransfer
    if (previewGrid) {
        previewGrid.addEventListener('click', function (e) {
            var btn = e.target.closest('.remove-preview');
            if (!btn || !fileInput) return;
            e.preventDefault();
            var idx = parseInt(btn.dataset.idx, 10);
            try {
                var newDt = new DataTransfer();
                Array.from(fileInput.files).forEach(function (f, i) {
                    if (i !== idx) newDt.items.add(f);
                });
                fileInput.files = newDt.files;
            } catch (err) {
                // Fallback: clear all if removal is not supported
                fileInput.value = '';
            }
            renderPreviews();
        });
    }

    // Drag & drop — write dropped files to the input via DataTransfer, then fire change
    if (uploadZone) {
        ['dragenter', 'dragover'].forEach(function (evt) {
            uploadZone.addEventListener(evt, function (e) {
                e.preventDefault();
                e.stopPropagation();
                uploadZone.classList.add('drag-over');
            });
        });
        ['dragleave', 'drop'].forEach(function (evt) {
            uploadZone.addEventListener(evt, function (e) {
                e.preventDefault();
                e.stopPropagation();
                uploadZone.classList.remove('drag-over');
            });
        });
        uploadZone.addEventListener('drop', function (e) {
            if (!fileInput || !e.dataTransfer || !e.dataTransfer.files.length) return;
            try {
                var dt = new DataTransfer();
                // Merge existing + dropped
                Array.from(fileInput.files || []).forEach(function (f) { dt.items.add(f); });
                Array.from(e.dataTransfer.files).forEach(function (f) { dt.items.add(f); });
                while (dt.files.length > 100) dt.items.remove(dt.files.length - 1);
                fileInput.files = dt.files;
                fileInput.dispatchEvent(new Event('change'));
            } catch (err) {}
        });
    }

    // Client-side validation on submit — mirrors the PHP rules.
    if (form) {
        form.addEventListener('submit', function (e) {
            var hasImage = fileInput && fileInput.files && fileInput.files.length > 0;
            // Also count videos — a publication is valid with EITHER images OR videos (or both).
            var videoInput = document.getElementById('videoFilesInput');
            var hasVideo = videoInput && videoInput.files && videoInput.files.length > 0;
            var descriptionEl = form.querySelector('[name$="[description]"]');
            var descriptionOk = descriptionEl && descriptionEl.value.trim().length >= 5;

            if (!descriptionOk) {
                e.preventDefault();
                showError('La description doit contenir au moins 5 caractères.');
                if (descriptionEl) descriptionEl.focus();
                return false;
            }

            if (!hasImage && !hasVideo) {
                e.preventDefault();
                showError('Veuillez ajouter au moins une image ou une vidéo.');
                uploadZone.scrollIntoView({ behavior: 'smooth', block: 'center' });
                return false;
            }

            hideError();
            if (publishBtn) {
                publishBtn.disabled = true;
                publishBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Publication...';
            }
        });
    }

    // ===== MEDIA LIGHTBOX — image + video, prev/next navigation =====
    var modalEl = document.getElementById('imageViewerModal');
    if (modalEl && typeof bootstrap !== 'undefined') {
        var modal = new bootstrap.Modal(modalEl);
        var modalImg = document.getElementById('imageViewerImg');
        var modalVideo = document.getElementById('imageViewerVideo');
        var modalCaption = document.getElementById('imageViewerCaption');
        var modalCounter = document.getElementById('imageViewerCounter');
        var prevBtn = document.getElementById('imageViewerPrev');
        var nextBtn = document.getElementById('imageViewerNext');

        var currentItems = [];
        var currentIndex = 0;

        function showItem(index) {
            if (index < 0 || index >= currentItems.length) return;
            currentIndex = index;
            var item = currentItems[index];

            // Pause video before switching to avoid audio bleed.
            modalVideo.pause();
            modalVideo.removeAttribute('src');
            modalVideo.load();

            if (item.type === 'video') {
                modalImg.classList.add('d-none');
                modalVideo.classList.remove('d-none');
                modalVideo.src = item.src;
            } else {
                modalVideo.classList.add('d-none');
                modalImg.classList.remove('d-none');
                modalImg.src = item.src;
                modalImg.alt = item.caption || '';
            }
            modalCaption.textContent = item.caption || '';
            modalCounter.textContent = (index + 1) + ' / ' + currentItems.length;

            // Hide prev/next if only one item.
            var hideNav = currentItems.length <= 1;
            prevBtn.classList.toggle('d-none', hideNav);
            nextBtn.classList.toggle('d-none', hideNav);
            prevBtn.disabled = index === 0;
            nextBtn.disabled = index === currentItems.length - 1;
        }

        function buildGallery(trigger) {
            // Group triggers by data-gallery (e.g. "post-42"). Falls back to the
            // nearest .post-image-container so old markup keeps working.
            var galleryKey = trigger.dataset.gallery;
            var triggers;
            if (galleryKey) {
                triggers = Array.from(document.querySelectorAll(
                    '.image-viewer-trigger[data-gallery="' + galleryKey + '"]'
                ));
            } else {
                var group = trigger.closest('.post-image-container') || document.body;
                triggers = Array.from(group.querySelectorAll('.image-viewer-trigger'));
            }
            // Deduplicate by src (the cover image often duplicates the first gallery thumb).
            var seen = {};
            return triggers.filter(function (t) {
                var key = t.dataset.src || t.src || t.getAttribute('src') || '';
                if (seen[key]) return false;
                seen[key] = true;
                return true;
            }).map(function (t) {
                return {
                    src: t.dataset.src || t.src || t.getAttribute('src') || '',
                    caption: t.dataset.caption || '',
                    type: t.dataset.type === 'video' ? 'video' : 'image',
                    el: t,
                };
            });
        }

        document.addEventListener('click', function (e) {
            var trigger = e.target.closest('.image-viewer-trigger');
            if (!trigger) return;
            e.preventDefault();
            currentItems = buildGallery(trigger);
            var clickedSrc = trigger.dataset.src || trigger.src || trigger.getAttribute('src') || '';
            var idx = currentItems.findIndex(function (it) { return it.src === clickedSrc; });
            showItem(idx >= 0 ? idx : 0);
            modal.show();
        });

        prevBtn.addEventListener('click', function () { showItem(currentIndex - 1); });
        nextBtn.addEventListener('click', function () { showItem(currentIndex + 1); });

        // Keyboard navigation.
        document.addEventListener('keydown', function (e) {
            if (!modalEl.classList.contains('show')) return;
            if (e.key === 'ArrowLeft') { e.preventDefault(); showItem(currentIndex - 1); }
            else if (e.key === 'ArrowRight') { e.preventDefault(); showItem(currentIndex + 1); }
        });

        // Stop video playback when closing modal.
        modalEl.addEventListener('hidden.bs.modal', function () {
            modalVideo.pause();
            modalVideo.removeAttribute('src');
            modalVideo.load();
        });
    }
});
