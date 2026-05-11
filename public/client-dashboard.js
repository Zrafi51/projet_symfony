document.addEventListener('DOMContentLoaded', () => {
    const tabButtons = Array.from(document.querySelectorAll('.tab-button'));
    const tabSections = Array.from(document.querySelectorAll('.tab-section'));
    const accountMenuButton = document.getElementById('accountMenuButton');
    const accountMenu = document.getElementById('accountMenu');
    const notificationToggle = document.getElementById('notificationToggle');
    const notificationMenu = document.getElementById('notificationMenu');
    const searchToggle = document.getElementById('dashboardSearchToggle');
    const searchOverlay = document.getElementById('dashboardSearchOverlay');
    const searchInput = document.getElementById('dashboardSearchInput');
    const searchClose = document.getElementById('dashboardSearchClose');
    const searchClear = document.getElementById('dashboardSearchClear');
    const searchSummary = document.getElementById('dashboardSearchSummary');
    const searchableCards = Array.from(document.querySelectorAll(
        '.tab-section .trip-card, .tab-section .reclamation-card, .tab-section .document-row, .tab-section .dashboard-empty-card'
    ));

    let activeTab = 'upcoming';
    let searchActive = false;

    const restoreVisibleSections = () => {
        searchableCards.forEach((item) => {
            item.style.display = '';
        });

        tabSections.forEach((section) => {
            section.style.display = section.id === `${activeTab}-section` ? 'block' : 'none';
        });

        tabButtons.forEach((button) => {
            button.classList.toggle('tab-button-active', button.getAttribute('data-tab') === activeTab);
        });
    };

    const applySearch = () => {
        if (!searchInput || !searchSummary) {
            return;
        }

        const query = searchInput.value.trim().toLowerCase();
        if (query === '') {
            searchActive = false;
            searchSummary.textContent = 'Tapez pour filtrer les cartes visibles du dashboard.';
            restoreVisibleSections();
            return;
        }

        searchActive = true;
        let totalMatches = 0;

        tabButtons.forEach((button) => {
            button.classList.remove('tab-button-active');
        });

        tabSections.forEach((section) => {
            const items = Array.from(section.querySelectorAll('.trip-card, .reclamation-card, .document-row, .dashboard-empty-card'));
            let sectionMatches = 0;

            items.forEach((item) => {
                const itemMatches = item.textContent.toLowerCase().includes(query);
                item.style.display = itemMatches ? '' : 'none';
                if (itemMatches) {
                    sectionMatches += 1;
                }
            });

            section.style.display = sectionMatches > 0 ? 'block' : 'none';
            totalMatches += sectionMatches;
        });

        searchSummary.textContent = totalMatches > 0
            ? `${totalMatches} resultat(s) trouves dans le dashboard.`
            : 'Aucun resultat. Essayez un autre mot-cle.';
    };

    const openSearch = () => {
        if (!searchOverlay || !searchInput) {
            return;
        }

        closeFloatingMenus();
        searchOverlay.style.display = 'flex';
        document.body.classList.add('dashboard-search-open');
        window.setTimeout(() => searchInput.focus(), 40);
        applySearch();
    };

    const closeSearchPanel = (resetSearch = false) => {
        if (!searchOverlay) {
            return;
        }

        searchOverlay.style.display = 'none';
        document.body.classList.remove('dashboard-search-open');

        if (resetSearch && searchInput) {
            searchInput.value = '';
        }

        searchActive = false;
        restoreVisibleSections();
        if (searchSummary) {
            searchSummary.textContent = 'Tapez pour filtrer les cartes visibles du dashboard.';
        }
    };

    const showTab = (tabName) => {
        activeTab = tabName;
        if (searchActive) {
            closeSearchPanel(true);
        } else {
            restoreVisibleSections();
        }
    };

    tabButtons.forEach((button) => {
        button.addEventListener('click', () => {
            const tabName = button.getAttribute('data-tab') || 'upcoming';
            showTab(tabName);
        });
    });

    restoreVisibleSections();

    const animatedCards = document.querySelectorAll('.stat-card, .trip-card');
    animatedCards.forEach((card) => {
        card.addEventListener('mouseenter', () => {
            card.style.transform = 'translateY(-6px) scale(1.02)';
        });

        card.addEventListener('mouseleave', () => {
            card.style.transform = 'translateY(0) scale(1)';
        });
    });

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
    const dashboardScroll = document.querySelector('.dashboard-scroll');
    const hasContainerScroll = Boolean(
        dashboardScroll && dashboardScroll.scrollHeight > dashboardScroll.clientHeight + 10
    );
    const scrollTarget = hasContainerScroll ? dashboardScroll : window;
    const getScrollTop = () => hasContainerScroll
        ? (dashboardScroll ? dashboardScroll.scrollTop : 0)
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

    function closeFloatingMenus(except) {
        if (accountMenu && except !== 'account') {
            accountMenu.style.display = 'none';
        }
        if (notificationMenu && except !== 'notification') {
            notificationMenu.style.display = 'none';
        }
    }

    if (accountMenuButton && accountMenu) {
        accountMenuButton.addEventListener('click', (event) => {
            event.stopPropagation();
            const shouldOpen = accountMenu.style.display !== 'block';
            closeFloatingMenus();
            accountMenu.style.display = shouldOpen ? 'block' : 'none';
            syncNavbar();
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
            syncNavbar();
        });

        notificationMenu.addEventListener('click', (event) => {
            event.stopPropagation();
        });
    }

    document.addEventListener('click', () => {
        closeFloatingMenus();
    });

    if (searchToggle) {
        searchToggle.addEventListener('click', openSearch);
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
            applySearch();
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
        searchInput.addEventListener('input', applySearch);
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
});
