document.addEventListener('DOMContentLoaded', () => {
    const navbar = document.getElementById('aboutNavbar');
    const searchToggle = document.getElementById('aboutSearchToggle');
    const searchOverlay = document.getElementById('aboutSearchOverlay');
    const searchPanel = document.getElementById('aboutSearchPanel');
    const searchClose = document.getElementById('aboutSearchClose');
    const searchDestination = document.getElementById('aboutSearchDestination');
    const searchDate = document.getElementById('aboutSearchDate');
    const searchTravelers = document.getElementById('aboutSearchTravelers');
    const searchSubmit = document.getElementById('aboutSearchSubmit');
    const searchMessage = document.getElementById('aboutSearchMessage');
    const popularButtons = Array.from(document.querySelectorAll('.about-search-chip'));
    const scrollTopButton = document.getElementById('aboutScrollTopButton');
    const accountMenuButton = document.querySelector('[data-account-menu-button]');
    const accountMenu = document.querySelector('[data-account-menu]');
    const storySection = document.getElementById('aboutStorySection');
    const storyImages = Array.from(document.querySelectorAll('.about-story-image'));
    const storyCopies = Array.from(document.querySelectorAll('.about-story-copy'));
    const valuesSection = document.getElementById('aboutValuesSection');
    const valueCards = Array.from(document.querySelectorAll('.about-value-card'));
    const valueTitles = Array.from(document.querySelectorAll('.about-value-title'));
    const teamShowcase = document.querySelector('.about-team-showcase');
    const teamMembers = Array.from(document.querySelectorAll('.about-team-member'));
    const hero = document.querySelector('.about-hero');
    const heroContent = document.querySelector('.about-hero-content');
    const heroVideo = document.querySelector('.about-hero-video');
    const revealTargets = Array.from(document.querySelectorAll('[data-scroll-reveal]'));
    const reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

    let navbarScrolled = false;
    let lastScrollTop = window.scrollY || document.documentElement.scrollTop || 0;
    let currentStory = 0;
    let scrollTicking = false;
    let valuesRevealPlayed = false;
    let activeTeamMemberIndex = -1;

    const clamp = (value, min, max) => Math.max(min, Math.min(max, value));
    const isSearchOpen = () => Boolean(searchOverlay && searchOverlay.style.display === 'flex');

    const updateStory = (index) => {
        currentStory = index;

        storyImages.forEach((image, itemIndex) => {
            image.classList.toggle('is-active', itemIndex === index);
        });

        storyCopies.forEach((copy, itemIndex) => {
            copy.classList.toggle('is-active', itemIndex === index);
        });
    };

    const updateStoryByScroll = () => {
        if (!storySection || storyImages.length === 0) {
            return;
        }

        const rect = storySection.getBoundingClientRect();
        const scrollSpan = Math.max(storySection.offsetHeight - window.innerHeight, 1);
        const progress = clamp(((window.innerHeight * 0.18) - rect.top) / scrollSpan, 0, 0.999);
        const nextIndex = Math.min(storyImages.length - 1, Math.floor(progress * storyImages.length));

        if (nextIndex !== currentStory) {
            updateStory(nextIndex);
        }
    };

    const updateScrollTopButton = () => {
        if (!scrollTopButton) {
            return;
        }

        const shouldShow = (window.scrollY || document.documentElement.scrollTop || 0) > 500;
        scrollTopButton.classList.toggle('is-visible', shouldShow);
    };

    const playValuesReveal = () => {
        valueCards.forEach((card, index) => {
            window.setTimeout(() => {
                card.classList.add('reveal');
            }, index * 180);
        });
    };

    const updateValuesEffects = () => {
        if (!valuesSection) {
            return;
        }

        const viewportHeight = window.innerHeight || document.documentElement.clientHeight || 0;
        if (viewportHeight <= 0) {
            return;
        }

        const rect = valuesSection.getBoundingClientRect();
        if (!valuesRevealPlayed && rect.top <= viewportHeight * 0.78) {
            valuesRevealPlayed = true;
            playValuesReveal();
        }

        const progress = clamp((viewportHeight - rect.top) / (viewportHeight + rect.height), 0, 1);
        const titleOffset = progress * 30;
        valueTitles.forEach((title) => {
            title.style.transform = `translateY(${titleOffset}px)`;
        });
    };

    const updateHeroParallax = () => {
        if (!hero || !heroContent || reduceMotion) {
            return;
        }

        const currentScrollTop = window.scrollY || document.documentElement.scrollTop || 0;
        const heroHeight = Math.max(hero.offsetHeight, 1);
        const progress = clamp(currentScrollTop / heroHeight, 0, 1);
        const contentTranslateY = progress * -46;
        const contentScale = 1 - (progress * 0.045);
        const contentOpacity = Math.max(0.58, 1 - (progress * 0.42));

        heroContent.style.transform = `translate3d(0, ${contentTranslateY}px, 0) scale(${contentScale})`;
        heroContent.style.opacity = `${contentOpacity}`;

        if (heroVideo) {
            const videoScale = 1 + (progress * 0.08);
            const videoTranslateY = progress * 26;
            heroVideo.style.transform = `translate3d(0, ${videoTranslateY}px, 0) scale(${videoScale})`;
        }
    };

    const updateScrollReveal = () => {
        if (revealTargets.length === 0) {
            return;
        }

        if (reduceMotion) {
            revealTargets.forEach((target) => {
                target.classList.add('is-visible');
            });

            return;
        }

        const viewportHeight = window.innerHeight || document.documentElement.clientHeight || 0;
        revealTargets.forEach((target) => {
            const rect = target.getBoundingClientRect();
            const isVisible = rect.top <= viewportHeight * 0.86;
            target.classList.toggle('is-visible', isVisible);
        });
    };

    const setActiveTeamMember = (index) => {
        activeTeamMemberIndex = index;
        if (teamShowcase) {
            teamShowcase.classList.add('is-focused');
        }

        teamMembers.forEach((member, memberIndex) => {
            member.classList.toggle('is-active', memberIndex === index);
        });
    };

    const clearActiveTeamMember = () => {
        activeTeamMemberIndex = -1;
        if (teamShowcase) {
            teamShowcase.classList.remove('is-focused');
        }

        teamMembers.forEach((member) => {
            member.classList.remove('is-active');
        });
    };

    const syncNavbar = () => {
        if (!navbar) {
            return;
        }

        const currentScrollTop = window.scrollY || document.documentElement.scrollTop || 0;
        navbarScrolled = currentScrollTop > 50;
        navbar.classList.toggle('scrolled', navbarScrolled);

        const keepVisible = currentScrollTop <= 100 || isSearchOpen();
        if (keepVisible) {
            navbar.style.transform = 'translateY(0)';
        } else if (currentScrollTop > lastScrollTop + 4) {
            navbar.style.transform = 'translateY(-140%)';
        } else if (currentScrollTop < lastScrollTop - 4) {
            navbar.style.transform = 'translateY(0)';
        }

        lastScrollTop = currentScrollTop;
    };

    const handlePageScroll = () => {
        if (scrollTicking) {
            return;
        }

        scrollTicking = true;
        window.requestAnimationFrame(() => {
            syncNavbar();
            updateHeroParallax();
            updateStoryByScroll();
            updateValuesEffects();
            updateScrollReveal();
            updateScrollTopButton();
            scrollTicking = false;
        });
    };

    const openSearch = () => {
        if (!searchOverlay) {
            return;
        }

        searchOverlay.style.display = 'flex';
        searchOverlay.setAttribute('aria-hidden', 'false');
        document.body.classList.add('about-search-open');
        if (navbar) {
            navbar.style.transform = 'translateY(0)';
        }
        if (accountMenu) {
            accountMenu.hidden = true;
            accountMenu.classList.remove('is-open');
        }
        if (accountMenuButton) {
            accountMenuButton.setAttribute('aria-expanded', 'false');
        }
        if (searchMessage) {
            searchMessage.textContent = '';
        }

        window.setTimeout(() => {
            if (searchDestination) {
                searchDestination.focus();
            }
        }, 30);
    };

    const closeSearch = () => {
        if (!searchOverlay) {
            return;
        }

        searchOverlay.style.display = 'none';
        searchOverlay.setAttribute('aria-hidden', 'true');
        document.body.classList.remove('about-search-open');
        if (searchMessage) {
            searchMessage.textContent = '';
        }
        syncNavbar();
    };

    if (searchToggle) {
        searchToggle.addEventListener('click', openSearch);
    }

    if (searchClose) {
        searchClose.addEventListener('click', closeSearch);
    }

    if (searchOverlay) {
        searchOverlay.addEventListener('click', (event) => {
            if (event.target === searchOverlay) {
                closeSearch();
            }
        });
    }

    if (searchPanel) {
        searchPanel.addEventListener('click', (event) => {
            event.stopPropagation();
        });
    }

    popularButtons.forEach((button) => {
        button.addEventListener('click', () => {
            if (searchDestination) {
                searchDestination.value = button.getAttribute('data-destination') || '';
                searchDestination.focus();
            }
        });
    });

    if (searchSubmit) {
        searchSubmit.addEventListener('click', () => {
            const destination = searchDestination ? searchDestination.value.trim() : '';

            if (destination === '') {
                if (searchMessage) {
                    searchMessage.textContent = 'Veuillez saisir une destination avant de lancer la recherche.';
                }
                if (searchDestination) {
                    searchDestination.focus();
                }
                return;
            }

            const params = new URLSearchParams();
            params.set('search', destination);
            if (searchDate && searchDate.value !== '') {
                params.set('date', searchDate.value);
            }
            if (searchTravelers && searchTravelers.value !== '') {
                params.set('travelers', searchTravelers.value);
            }

            window.location.href = `/destinations?${params.toString()}`;
        });
    }

    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape') {
            closeSearch();
        }
    });

    if (scrollTopButton) {
        scrollTopButton.addEventListener('click', () => {
            window.scrollTo({ top: 0, behavior: 'smooth' });
        });
    }

    if (teamMembers.length > 0) {
        teamMembers.forEach((member, index) => {
            member.addEventListener('mouseenter', () => {
                setActiveTeamMember(index);
            });

            member.addEventListener('mouseleave', () => {
                if (activeTeamMemberIndex === index) {
                    clearActiveTeamMember();
                }
            });

            member.addEventListener('focusin', () => {
                setActiveTeamMember(index);
            });

            member.addEventListener('focusout', () => {
                window.setTimeout(() => {
                    if (!member.contains(document.activeElement) && activeTeamMemberIndex === index) {
                        clearActiveTeamMember();
                    }
                }, 0);
            });

            member.addEventListener('click', () => {
                const shouldActivate = !member.classList.contains('is-active');
                if (shouldActivate) {
                    setActiveTeamMember(index);
                } else {
                    clearActiveTeamMember();
                }
            });
        });

        if (teamShowcase) {
            teamShowcase.addEventListener('mouseleave', clearActiveTeamMember);
        }
    }

    document.addEventListener('click', (event) => {
        if (teamShowcase && !teamShowcase.contains(event.target)) {
            clearActiveTeamMember();
        }
    });

    const logoCanvas = document.getElementById('aboutLogoCanvas');
    if (logoCanvas) {
        const context = logoCanvas.getContext('2d');
        let planePos = 0;

        const drawPin = (x, y) => {
            context.fillStyle = '#E89B6D';
            context.beginPath();
            context.arc(x, y - 4, 4, 0, Math.PI * 2);
            context.fill();
            context.beginPath();
            context.moveTo(x, y);
            context.lineTo(x - 3, y + 6);
            context.lineTo(x + 3, y + 6);
            context.closePath();
            context.fill();
            context.fillStyle = 'white';
            context.beginPath();
            context.arc(x, y - 4, 1.5, 0, Math.PI * 2);
            context.fill();
        };

        const animateLogo = () => {
            planePos += 0.002;
            if (planePos > 1) {
                planePos = 0;
            }

            context.clearRect(0, 0, 200, 60);

            const logoPrimary = navbarScrolled ? '#0B3C5D' : 'white';
            context.strokeStyle = logoPrimary;
            context.lineWidth = 2;
            context.setLineDash([4, 4]);
            context.globalAlpha = 0.5;
            context.beginPath();
            context.moveTo(15, 30);
            context.quadraticCurveTo(40, 10, 65, 30);
            context.quadraticCurveTo(90, 50, 115, 30);
            context.quadraticCurveTo(135, 15, 155, 30);
            context.stroke();
            context.setLineDash([]);
            context.globalAlpha = 1;

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

            context.fillStyle = logoPrimary;
            context.beginPath();
            context.moveTo(x - 5, y);
            context.lineTo(x + 8, y - 1.5);
            context.lineTo(x + 6, y + 1.5);
            context.lineTo(x - 3, y + 1);
            context.closePath();
            context.fill();

            context.beginPath();
            context.moveTo(x - 1, y - 3);
            context.lineTo(x + 2, y - 1);
            context.lineTo(x + 1, y + 1);
            context.closePath();
            context.fill();

            context.beginPath();
            context.moveTo(x - 1, y + 3);
            context.lineTo(x + 2, y + 1);
            context.lineTo(x + 1, y - 1);
            context.closePath();
            context.fill();

            context.beginPath();
            context.moveTo(x - 5, y - 2);
            context.lineTo(x - 3, y);
            context.lineTo(x - 4, y + 2);
            context.closePath();
            context.fill();

            context.fillStyle = '#E89B6D';
            context.beginPath();
            context.arc(x + 5, y, 1.25, 0, Math.PI * 2);
            context.fill();

            context.font = 'bold 18px Arial';
            context.fillStyle = logoPrimary;
            context.fillText('Easy', 60, 56);
            context.fillStyle = '#E89B6D';
            context.fillText('Travel', 100, 56);

            window.requestAnimationFrame(animateLogo);
        };

        animateLogo();
    }

    function drawCompassIcon() {
        const canvas = document.getElementById('compass-icon');
        if (!canvas) {
            return;
        }

        const ctx = canvas.getContext('2d');
        const centerX = 48;
        const centerY = 48;

        ctx.fillStyle = '#000000';
        ctx.beginPath();
        ctx.arc(centerX, centerY, 34, 0, Math.PI * 2);
        ctx.fill();

        ctx.fillStyle = '#F1B11A';
        ctx.beginPath();
        ctx.arc(centerX, centerY, 30, 0, Math.PI * 2);
        ctx.fill();

        ctx.fillStyle = 'white';
        ctx.beginPath();
        ctx.arc(centerX, centerY, 24, 0, Math.PI * 2);
        ctx.fill();

        ctx.strokeStyle = '#D1D5DB';
        ctx.lineWidth = 3;
        ctx.beginPath();
        ctx.arc(centerX, centerY, 20, 0, Math.PI * 2);
        ctx.stroke();

        ctx.strokeStyle = '#C7CDD3';
        ctx.lineWidth = 2.4;
        ctx.beginPath();
        ctx.moveTo(centerX, 28);
        ctx.lineTo(centerX, 68);
        ctx.moveTo(28, centerY);
        ctx.lineTo(68, centerY);
        ctx.stroke();

        ctx.fillStyle = '#E53935';
        ctx.beginPath();
        ctx.moveTo(45, 14);
        ctx.lineTo(52, 14);
        ctx.lineTo(51, 22);
        ctx.closePath();
        ctx.fill();

        ctx.fillStyle = '#E64646';
        ctx.beginPath();
        ctx.moveTo(centerX, centerY);
        ctx.lineTo(57, 39);
        ctx.lineTo(49, 54);
        ctx.closePath();
        ctx.fill();

        ctx.fillStyle = '#8A8F98';
        ctx.beginPath();
        ctx.moveTo(centerX, centerY);
        ctx.lineTo(39, 58);
        ctx.lineTo(47, 42);
        ctx.closePath();
        ctx.fill();

        ctx.fillStyle = '#585D66';
        ctx.beginPath();
        ctx.arc(centerX, centerY, 4, 0, Math.PI * 2);
        ctx.fill();

        ctx.strokeStyle = '#F4C542';
        ctx.lineWidth = 1.8;
        ctx.beginPath();
        ctx.arc(centerX, centerY, 27, 0, Math.PI * 2);
        ctx.stroke();
    }

    function drawGlobeIcon() {
        const canvas = document.getElementById('globe-icon');
        if (!canvas) {
            return;
        }

        const ctx = canvas.getContext('2d');
        const centerX = 48;
        const centerY = 48;

        ctx.fillStyle = '#000000';
        ctx.beginPath();
        ctx.arc(centerX, centerY, 34, 0, Math.PI * 2);
        ctx.fill();

        ctx.fillStyle = '#0E8BFF';
        ctx.beginPath();
        ctx.arc(centerX, centerY, 30, 0, Math.PI * 2);
        ctx.fill();

        ctx.fillStyle = '#3BC300';
        ctx.beginPath();
        ctx.moveTo(41, 28);
        ctx.lineTo(34, 32);
        ctx.lineTo(36, 41);
        ctx.lineTo(30, 47);
        ctx.lineTo(35, 57);
        ctx.lineTo(44, 60);
        ctx.lineTo(47, 50);
        ctx.lineTo(42, 40);
        ctx.closePath();
        ctx.fill();

        ctx.beginPath();
        ctx.moveTo(51, 30);
        ctx.lineTo(62, 27);
        ctx.lineTo(66, 35);
        ctx.lineTo(61, 44);
        ctx.lineTo(56, 42);
        ctx.lineTo(54, 51);
        ctx.lineTo(49, 39);
        ctx.closePath();
        ctx.fill();

        ctx.beginPath();
        ctx.moveTo(52, 47);
        ctx.lineTo(58, 51);
        ctx.lineTo(61, 61);
        ctx.lineTo(57, 68);
        ctx.lineTo(51, 61);
        ctx.closePath();
        ctx.fill();

        ctx.fillStyle = '#8DE7FF';
        ctx.globalAlpha = 0.6;
        ctx.beginPath();
        ctx.ellipse(35, 30, 9, 6, 0, 0, Math.PI * 2);
        ctx.fill();
        ctx.globalAlpha = 1;
    }

    function drawDiamondIcon() {
        const canvas = document.getElementById('diamond-icon');
        if (!canvas) {
            return;
        }

        const ctx = canvas.getContext('2d');

        ctx.fillStyle = 'black';
        ctx.beginPath();
        ctx.moveTo(24, 30);
        ctx.lineTo(36, 14);
        ctx.lineTo(60, 14);
        ctx.lineTo(72, 30);
        ctx.lineTo(48, 74);
        ctx.closePath();
        ctx.fill();

        ctx.fillStyle = '#7FE6FF';
        ctx.beginPath();
        ctx.moveTo(29, 30);
        ctx.lineTo(38, 19);
        ctx.lineTo(58, 19);
        ctx.lineTo(67, 30);
        ctx.lineTo(48, 64);
        ctx.closePath();
        ctx.fill();

        ctx.fillStyle = '#BDF7FF';
        ctx.beginPath();
        ctx.moveTo(38, 19);
        ctx.lineTo(48, 19);
        ctx.lineTo(58, 19);
        ctx.lineTo(54, 30);
        ctx.lineTo(42, 30);
        ctx.closePath();
        ctx.fill();

        ctx.fillStyle = '#49CCF5';
        ctx.beginPath();
        ctx.moveTo(42, 30);
        ctx.lineTo(54, 30);
        ctx.lineTo(48, 60);
        ctx.closePath();
        ctx.fill();

        ctx.fillStyle = '#22B8F0';
        ctx.beginPath();
        ctx.moveTo(30, 30);
        ctx.lineTo(42, 30);
        ctx.lineTo(48, 60);
        ctx.closePath();
        ctx.fill();

        ctx.fillStyle = '#35D2FF';
        ctx.beginPath();
        ctx.moveTo(54, 30);
        ctx.lineTo(66, 30);
        ctx.lineTo(48, 60);
        ctx.closePath();
        ctx.fill();
    }

    updateStory(0);
    syncNavbar();
    updateHeroParallax();
    updateStoryByScroll();
    updateValuesEffects();
    updateScrollReveal();
    updateScrollTopButton();
    drawCompassIcon();
    drawGlobeIcon();
    drawDiamondIcon();

    window.addEventListener('scroll', handlePageScroll, { passive: true });
    window.addEventListener('resize', handlePageScroll);
});
