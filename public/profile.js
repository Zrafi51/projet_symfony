document.addEventListener('DOMContentLoaded', () => {
    const accountMenuButton = document.getElementById('accountMenuButton');
    const accountMenu = document.getElementById('accountMenu');
    const notificationToggle = document.getElementById('profileNotificationToggle');
    const notificationMenu = document.getElementById('profileNotificationMenu');
    const searchToggle = document.getElementById('profileSearchToggle');
    const searchOverlay = document.getElementById('profileSearchOverlay');
    const searchInput = document.getElementById('profileSearchInput');
    const searchClose = document.getElementById('profileSearchClose');
    const searchClear = document.getElementById('profileSearchClear');
    const searchSummary = document.getElementById('profileSearchSummary');
    const searchableCards = Array.from(document.querySelectorAll('.profile-side-card, .profile-form-card, .profile-feature-card'));

    const avatarForm = document.querySelector('[data-profile-avatar-form]');
    const avatarInput = document.getElementById('avatarInput');
    const avatarTrigger = document.querySelector('[data-profile-avatar-trigger]');
    const avatarSubmit = document.querySelector('[data-profile-avatar-submit]');
    const avatarName = document.querySelector('[data-profile-avatar-name]');
    const avatarLabel = document.getElementById('avatarLabel');
    const heroAvatarImage = document.getElementById('avatarPreview');
    const heroAvatarInitial = document.getElementById('avatarInitial');
    const summaryAvatarImage = document.getElementById('summaryAvatarPreview');
    const summaryAvatarInitial = document.getElementById('summaryAvatarInitial');

    const notificationForm = document.querySelector('[data-notification-form]');
    const notificationCount = document.querySelector('[data-notification-count]');

    const closeFloatingMenus = (except) => {
        if (accountMenu && except !== 'account') {
            accountMenu.style.display = 'none';
        }
        if (notificationMenu && except !== 'notification') {
            notificationMenu.style.display = 'none';
        }
    };

    if (accountMenuButton && accountMenu) {
        accountMenuButton.addEventListener('click', (event) => {
            event.stopPropagation();
            const shouldOpen = accountMenu.style.display !== 'block';
            closeFloatingMenus();
            accountMenu.style.display = shouldOpen ? 'block' : 'none';
        });

        accountMenu.addEventListener('click', (event) => {
            event.stopPropagation();
        });
    }

    if (notificationToggle && notificationMenu) {
        notificationToggle.addEventListener('click', (event) => {
            event.stopPropagation();
            const shouldOpen = notificationMenu.style.display !== 'block';
            closeFloatingMenus();
            notificationMenu.style.display = shouldOpen ? 'block' : 'none';
        });

        notificationMenu.addEventListener('click', (event) => {
            event.stopPropagation();
        });
    }

    document.addEventListener('click', () => {
        closeFloatingMenus();
    });

    const restoreProfileCards = () => {
        searchableCards.forEach((card) => {
            card.style.display = '';
        });
        if (searchSummary) {
            searchSummary.textContent = 'Tapez pour filtrer les sections du profil.';
        }
    };

    const applyProfileSearch = () => {
        if (!searchInput || !searchSummary) {
            return;
        }

        const query = searchInput.value.trim().toLowerCase();
        if (query === '') {
            restoreProfileCards();
            return;
        }

        let matches = 0;
        searchableCards.forEach((card) => {
            const isMatch = card.textContent.toLowerCase().includes(query);
            card.style.display = isMatch ? '' : 'none';
            if (isMatch) {
                matches += 1;
            }
        });

        searchSummary.textContent = matches > 0
            ? `${matches} section(s) correspondent a votre recherche.`
            : 'Aucune section ne correspond a cette recherche.';
    };

    const openSearchPanel = () => {
        if (!searchOverlay || !searchInput) {
            return;
        }

        closeFloatingMenus();
        searchOverlay.style.display = 'flex';
        document.body.classList.add('profile-search-open');
        window.setTimeout(() => searchInput.focus(), 40);
        applyProfileSearch();
    };

    const closeSearchPanel = (resetSearch = false) => {
        if (!searchOverlay) {
            return;
        }

        searchOverlay.style.display = 'none';
        document.body.classList.remove('profile-search-open');

        if (resetSearch && searchInput) {
            searchInput.value = '';
        }

        restoreProfileCards();
    };

    if (searchToggle) {
        searchToggle.addEventListener('click', openSearchPanel);
    }

    if (searchClose) {
        searchClose.addEventListener('click', () => closeSearchPanel(false));
    }

    if (searchClear) {
        searchClear.addEventListener('click', () => {
            if (!searchInput) {
                return;
            }

            searchInput.value = '';
            applyProfileSearch();
            searchInput.focus();
        });
    }

    if (searchOverlay) {
        searchOverlay.addEventListener('click', (event) => {
            if (event.target === searchOverlay) {
                closeSearchPanel(false);
            }
        });
    }

    if (searchInput) {
        searchInput.addEventListener('input', applyProfileSearch);
    }

    document.addEventListener('keydown', (event) => {
        if (event.key !== 'Escape') {
            return;
        }

        closeFloatingMenus();
        if (searchOverlay && searchOverlay.style.display === 'flex') {
            closeSearchPanel(false);
        }
    });

    if (avatarForm && avatarInput && avatarTrigger) {
        avatarTrigger.addEventListener('click', () => avatarInput.click());

        avatarInput.addEventListener('change', () => {
            if (!avatarInput.files || avatarInput.files.length === 0) {
                if (avatarName) {
                    avatarName.textContent = 'Aucun fichier selectionne';
                }
                if (avatarSubmit) {
                    avatarSubmit.disabled = true;
                }
                return;
            }

            const file = avatarInput.files[0];
            const isImage = file.type.startsWith('image/');
            const isSmallEnough = file.size <= 5 * 1024 * 1024;
            if (!isImage || !isSmallEnough) {
                avatarInput.value = '';
                if (avatarName) {
                    avatarName.textContent = !isImage
                        ? 'Le fichier doit etre une image'
                        : 'Image trop lourde (5 Mo max)';
                }
                if (avatarSubmit) {
                    avatarSubmit.disabled = true;
                }
                return;
            }

            if (avatarName) {
                avatarName.textContent = file.name;
            }
            if (avatarLabel) {
                avatarLabel.textContent = file.name;
            }
            if (avatarSubmit) {
                avatarSubmit.disabled = false;
            }

            const reader = new FileReader();
            reader.addEventListener('load', () => {
                const previewSource = String(reader.result);
                if (heroAvatarImage) {
                    heroAvatarImage.src = previewSource;
                    heroAvatarImage.style.display = 'block';
                }
                if (summaryAvatarImage) {
                    summaryAvatarImage.src = previewSource;
                    summaryAvatarImage.style.display = 'block';
                }
                if (heroAvatarInitial) {
                    heroAvatarInitial.style.display = 'none';
                }
                if (summaryAvatarInitial) {
                    summaryAvatarInitial.style.display = 'none';
                }
            });
            reader.readAsDataURL(file);
        });
    }

    if (notificationForm) {
        const notificationInputs = Array.from(notificationForm.querySelectorAll('.profile-notification-input'));
        const notificationCards = Array.from(notificationForm.querySelectorAll('.profile-notification-card'));
        const actionButtons = Array.from(notificationForm.querySelectorAll('[data-notification-action]'));

        const syncNotificationCards = () => {
            let activeCount = 0;

            notificationCards.forEach((card) => {
                const input = card.querySelector('.profile-notification-input');
                const isChecked = Boolean(input && input.checked);
                card.classList.toggle('profile-notification-card-active', isChecked);
                if (isChecked) {
                    activeCount += 1;
                }
            });

            if (notificationCount) {
                notificationCount.textContent = `${activeCount} active(s)`;
            }
        };

        notificationInputs.forEach((input) => {
            input.addEventListener('change', syncNotificationCards);
        });

        actionButtons.forEach((button) => {
            button.addEventListener('click', () => {
                const action = button.getAttribute('data-notification-action');

                notificationInputs.forEach((input) => {
                    if (action === 'all') {
                        input.checked = true;
                    } else if (action === 'none') {
                        input.checked = false;
                    } else if (action === 'essential') {
                        input.checked = input.name !== 'notify_offers';
                    }
                });

                syncNotificationCards();
            });
        });

        syncNotificationCards();
    }

    let navbarScrolled = false;
    const canvas = document.getElementById('logoCanvas');
    if (canvas) {
        const ctx = canvas.getContext('2d');
        let planePos = 0;

        const drawPin = (x, y) => {
            ctx.fillStyle = '#E89B6D';
            ctx.beginPath();
            ctx.arc(x, y - 4, 4, 0, Math.PI * 2);
            ctx.fill();
            ctx.beginPath();
            ctx.moveTo(x, y);
            ctx.lineTo(x - 3, y + 6);
            ctx.lineTo(x + 3, y + 6);
            ctx.closePath();
            ctx.fill();
            ctx.fillStyle = 'white';
            ctx.beginPath();
            ctx.arc(x, y - 4, 1.5, 0, Math.PI * 2);
            ctx.fill();
        };

        const animateLogo = () => {
            planePos += 0.002;
            if (planePos > 1) {
                planePos = 0;
            }

            ctx.clearRect(0, 0, 200, 60);

            const logoPrimary = navbarScrolled ? '#0B3C5D' : 'white';
            ctx.strokeStyle = logoPrimary;
            ctx.lineWidth = 2;
            ctx.setLineDash([4, 4]);
            ctx.globalAlpha = 0.5;
            ctx.beginPath();
            ctx.moveTo(15, 30);
            ctx.quadraticCurveTo(40, 10, 65, 30);
            ctx.quadraticCurveTo(90, 50, 115, 30);
            ctx.quadraticCurveTo(135, 15, 155, 30);
            ctx.stroke();
            ctx.setLineDash([]);
            ctx.globalAlpha = 1;

            drawPin(15, 30);
            drawPin(85, 30);
            drawPin(155, 30);

            let x;
            let y;
            if (planePos < 0.33) {
                const phase = planePos / 0.33;
                x = 15 + (65 - 15) * phase;
                y = 30 - 20 * Math.sin(phase * Math.PI);
            } else if (planePos < 0.66) {
                const phase = (planePos - 0.33) / 0.33;
                x = 65 + (115 - 65) * phase;
                y = 30 + 20 * Math.sin(phase * Math.PI);
            } else {
                const phase = (planePos - 0.66) / 0.34;
                x = 115 + (155 - 115) * phase;
                y = 30 - 15 * Math.sin(phase * Math.PI);
            }

            ctx.fillStyle = logoPrimary;
            ctx.beginPath();
            ctx.moveTo(x - 5, y);
            ctx.lineTo(x + 8, y - 1.5);
            ctx.lineTo(x + 6, y + 1.5);
            ctx.lineTo(x - 3, y + 1);
            ctx.closePath();
            ctx.fill();

            ctx.beginPath();
            ctx.moveTo(x - 1, y - 3);
            ctx.lineTo(x + 2, y - 1);
            ctx.lineTo(x + 1, y + 1);
            ctx.closePath();
            ctx.fill();

            ctx.beginPath();
            ctx.moveTo(x - 1, y + 3);
            ctx.lineTo(x + 2, y + 1);
            ctx.lineTo(x + 1, y - 1);
            ctx.closePath();
            ctx.fill();

            ctx.beginPath();
            ctx.moveTo(x - 5, y - 2);
            ctx.lineTo(x - 3, y);
            ctx.lineTo(x - 4, y + 2);
            ctx.closePath();
            ctx.fill();

            ctx.fillStyle = '#E89B6D';
            ctx.beginPath();
            ctx.arc(x + 5, y, 1.25, 0, Math.PI * 2);
            ctx.fill();

            ctx.font = 'bold 18px Arial';
            ctx.fillStyle = logoPrimary;
            ctx.fillText('Easy', 60, 56);
            ctx.fillStyle = '#E89B6D';
            ctx.fillText('Travel', 100, 56);

            requestAnimationFrame(animateLogo);
        };

        animateLogo();
    }

    const navbar = document.querySelector('.navbar-shell');
    const profileScroll = document.querySelector('.profile-scroll');
    const hasContainerScroll = Boolean(
        profileScroll && profileScroll.scrollHeight > profileScroll.clientHeight + 10
    );
    const scrollTarget = hasContainerScroll ? profileScroll : window;
    const getScrollTop = () => hasContainerScroll
        ? (profileScroll ? profileScroll.scrollTop : 0)
        : (window.scrollY || document.documentElement.scrollTop || 0);

    let lastScrollTop = getScrollTop();
    let scrollTicking = false;

    const syncNavbar = () => {
        if (!navbar) {
            return;
        }

        const currentScrollTop = getScrollTop();
        navbarScrolled = currentScrollTop > 50;
        navbar.classList.toggle('scrolled', navbarScrolled);

        const keepVisible = currentScrollTop < 90
            || (accountMenu && accountMenu.style.display === 'block')
            || (notificationMenu && notificationMenu.style.display === 'block')
            || (searchOverlay && searchOverlay.style.display === 'flex');

        if (keepVisible) {
            navbar.style.transform = 'translateY(0)';
        } else if (currentScrollTop > lastScrollTop + 4) {
            navbar.style.transform = 'translateY(-100%)';
        } else if (currentScrollTop < lastScrollTop - 4) {
            navbar.style.transform = 'translateY(0)';
        }

        lastScrollTop = currentScrollTop;
        scrollTicking = false;
    };

    scrollTarget.addEventListener('scroll', () => {
        if (!scrollTicking) {
            window.requestAnimationFrame(syncNavbar);
            scrollTicking = true;
        }
    }, { passive: true });
    syncNavbar();
});
