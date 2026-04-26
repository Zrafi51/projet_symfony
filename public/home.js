document.addEventListener('DOMContentLoaded', () => {
    const navbar = document.getElementById('homeNavbar');
    const scrollTopButton = document.getElementById('homeScrollTop');
    const searchOverlay = document.getElementById('homeSearchOverlay');
    const searchToggle = document.getElementById('homeSearchToggle');
    const aboutSearchToggle = document.getElementById('aboutSearchToggle');
    const searchForm = document.getElementById('homeSearchForm');
    const destinationInput = document.getElementById('homeSearchDestination');
    const heroVideo = document.querySelector('.home-hero-video');
    const heroVideoToggle = document.querySelector('[data-home-video-toggle]');
    const heroVideoSwitch = document.querySelector('[data-home-video-switch]');
    let lastScrollY = window.scrollY;
    let currentHeroVideo = 0;

    const openSearch = () => {
        if (!searchOverlay) {
            aboutSearchToggle?.click();
            return;
        }

        searchOverlay.classList.add('is-open');
        searchOverlay.setAttribute('aria-hidden', 'false');
        document.body.style.overflow = 'hidden';
        window.setTimeout(() => destinationInput?.focus(), 80);
    };

    const closeSearch = () => {
        if (!searchOverlay) {
            return;
        }

        searchOverlay.classList.remove('is-open');
        searchOverlay.setAttribute('aria-hidden', 'true');
        document.body.style.overflow = '';
    };

    const updateScrollState = () => {
        const currentY = window.scrollY;
        const shouldHide = currentY > lastScrollY && currentY > 130;

        navbar?.classList.toggle('is-scrolled', currentY > 35);
        navbar?.classList.toggle('is-hidden', shouldHide);
        scrollTopButton?.classList.toggle('is-visible', currentY > 520);
        lastScrollY = Math.max(currentY, 0);
    };

    searchToggle?.addEventListener('click', openSearch);
    document.querySelectorAll('[data-open-search]').forEach((button) => {
        button.addEventListener('click', openSearch);
    });
    document.querySelectorAll('[data-search-close]').forEach((button) => {
        button.addEventListener('click', closeSearch);
    });

    document.querySelectorAll('[data-search-chip]').forEach((chip) => {
        chip.addEventListener('click', () => {
            if (destinationInput) {
                destinationInput.value = chip.dataset.searchChip || '';
                destinationInput.focus();
            }
        });
    });

    searchForm?.addEventListener('submit', (event) => {
        event.preventDefault();
        const formData = new FormData(searchForm);
        const params = new URLSearchParams();
        const destination = String(formData.get('destination') || '').trim();
        const date = String(formData.get('date') || '').trim();
        const travelers = String(formData.get('travelers') || '').trim();

        if (destination) {
            params.set('search', destination);
        }
        if (date) {
            params.set('date', date);
        }
        if (travelers) {
            params.set('travelers', travelers);
        }

        window.location.href = `/destinations${params.toString() ? `?${params.toString()}` : ''}`;
    });

    scrollTopButton?.addEventListener('click', () => {
        window.scrollTo({ top: 0, behavior: 'smooth' });
    });

    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape') {
            closeSearch();
        }
    });

    if (heroVideo) {
        const videos = (heroVideo.dataset.homeVideoList || '')
            .split('|')
            .map((item) => item.trim())
            .filter(Boolean);

        heroVideoToggle?.addEventListener('click', () => {
            if (heroVideo.paused) {
                heroVideo.play().catch(() => {});
                heroVideoToggle.classList.add('is-playing');
            } else {
                heroVideo.pause();
                heroVideoToggle.classList.remove('is-playing');
            }
        });

        heroVideoSwitch?.addEventListener('click', () => {
            if (videos.length < 2) {
                return;
            }

            currentHeroVideo = (currentHeroVideo + 1) % videos.length;
            heroVideoSwitch.classList.add('is-switching');
            heroVideo.src = videos[currentHeroVideo];
            heroVideo.load();
            heroVideo.play().catch(() => {});
            window.setTimeout(() => {
                heroVideoSwitch.classList.remove('is-switching');
            }, 420);
        });
    }

    const observer = new IntersectionObserver((entries) => {
        entries.forEach((entry) => {
            if (!entry.isIntersecting) {
                return;
            }

            const delay = entry.target.dataset.revealDelay || '0';
            entry.target.style.setProperty('--reveal-delay', `${delay}ms`);
            entry.target.classList.add('is-visible');
            observer.unobserve(entry.target);
        });
    }, { threshold: 0.16, rootMargin: '0px 0px -60px 0px' });

    document.querySelectorAll('.reveal-on-scroll').forEach((element) => {
        observer.observe(element);
    });

    const atmosphereSection = document.querySelector('[data-atmosphere-section]');
    const atmosphereTrack = document.querySelector('[data-atmosphere-track]');
    const atmosphereProgress = document.querySelector('[data-atmosphere-progress]');
    let atmosphereFrame = null;
    const destinationVideos = Array.from(document.querySelectorAll('[data-destination-video]'));
    let destinationFrame = null;
    const serviceSection = document.querySelector('[data-service-section]');
    const serviceGlowA = document.querySelector('[data-service-glow-a]');
    const serviceGlowB = document.querySelector('[data-service-glow-b]');
    const serviceCta = document.querySelector('[data-service-cta]');
    const offerCards = Array.from(document.querySelectorAll('[data-offer-card]'));
    let offerFrame = null;
    const journeySection = document.querySelector('[data-journey-next]');
    const journeyStepElements = Array.from(document.querySelectorAll('[data-journey-step]'));
    let journeyFrame = null;

    const syncAtmosphereScroll = () => {
        if (!atmosphereSection || !atmosphereTrack) {
            return;
        }

        const isDesktop = window.innerWidth >= 1024;
        if (!isDesktop) {
            atmosphereSection.style.minHeight = '';
            atmosphereTrack.style.transform = '';
            if (atmosphereProgress) {
                atmosphereProgress.style.width = '0%';
            }
            return;
        }

        const travelDistance = Math.max(0, atmosphereTrack.scrollWidth - window.innerWidth + 96);
        atmosphereSection.style.minHeight = `${window.innerHeight + travelDistance}px`;

        const rect = atmosphereSection.getBoundingClientRect();
        const scrollRange = Math.max(1, atmosphereSection.offsetHeight - window.innerHeight);
        const progress = Math.min(1, Math.max(0, -rect.top / scrollRange));
        atmosphereTrack.style.transform = `translate3d(${-travelDistance * progress}px, 0, 0)`;

        if (atmosphereProgress) {
            atmosphereProgress.style.width = `${progress * 100}%`;
        }
    };

    const requestAtmosphereSync = () => {
        if (atmosphereFrame !== null) {
            return;
        }

        atmosphereFrame = window.requestAnimationFrame(() => {
            atmosphereFrame = null;
            syncAtmosphereScroll();
        });
    };

    const syncDestinationParallax = () => {
        if (!destinationVideos.length) {
            return;
        }

        const viewportHeight = window.innerHeight || 1;
        destinationVideos.forEach((video) => {
            const card = video.closest('.home-destination-next-card');
            if (!card) {
                return;
            }

            const rect = card.getBoundingClientRect();
            const progress = Math.min(1, Math.max(0, (viewportHeight - rect.top) / (viewportHeight + rect.height)));
            const offset = -42 * progress;
            video.style.setProperty('--destination-video-y', `${offset}px`);
        });
    };

    const requestDestinationSync = () => {
        if (destinationFrame !== null) {
            return;
        }

        destinationFrame = window.requestAnimationFrame(() => {
            destinationFrame = null;
            syncDestinationParallax();
        });
    };

    if (serviceSection) {
        serviceSection.addEventListener('pointermove', (event) => {
            const rect = serviceSection.getBoundingClientRect();
            const x = event.clientX - rect.left;
            const y = event.clientY - rect.top;

            if (serviceGlowA) {
                serviceGlowA.style.transform = `translate3d(${x * 0.02}px, ${y * 0.02}px, 0)`;
            }
            if (serviceGlowB) {
                serviceGlowB.style.transform = `translate3d(${-x * 0.02}px, ${-y * 0.02}px, 0)`;
            }
        });
    }

    document.querySelectorAll('.home-service-next-card').forEach((card) => {
        card.addEventListener('pointermove', (event) => {
            const rect = card.getBoundingClientRect();
            card.style.setProperty('--service-x', `${event.clientX - rect.left}px`);
            card.style.setProperty('--service-y', `${event.clientY - rect.top}px`);
        });
    });

    serviceCta?.addEventListener('pointermove', (event) => {
        const rect = serviceCta.getBoundingClientRect();
        const x = (event.clientX - rect.left - rect.width / 2) * 0.28;
        const y = (event.clientY - rect.top - rect.height / 2) * 0.28;
        serviceCta.style.transform = `translate3d(${x}px, ${y}px, 0)`;
    });

    serviceCta?.addEventListener('pointerleave', () => {
        serviceCta.style.transform = '';
    });

    const syncOfferParallax = () => {
        if (!offerCards.length) {
            return;
        }

        const viewportHeight = window.innerHeight || 1;
        offerCards.forEach((card) => {
            const rect = card.getBoundingClientRect();
            const progress = Math.min(1, Math.max(0, (viewportHeight - rect.top) / (viewportHeight + rect.height)));
            const lift = -34 * progress;
            card.style.setProperty('--offer-lift', `${lift}px`);
        });
    };

    const requestOfferSync = () => {
        if (offerFrame !== null) {
            return;
        }

        offerFrame = window.requestAnimationFrame(() => {
            offerFrame = null;
            syncOfferParallax();
        });
    };

    document.querySelectorAll('[data-hover-video]').forEach((video) => {
        const card = video.closest('.home-destination-card');
        if (!card) {
            return;
        }

        card.addEventListener('mouseenter', () => {
            video.play().catch(() => {});
        });
        card.addEventListener('mouseleave', () => {
            video.pause();
            video.currentTime = 0;
        });
    });

    const mapImage = document.getElementById('homeMapImage');
    const mapCountry = document.getElementById('homeMapCountry');
    const mapCity = document.getElementById('homeMapCity');
    const mapTrip = document.getElementById('homeMapTrip');
    const mapPrice = document.getElementById('homeMapPrice');
    const mapPins = Array.from(document.querySelectorAll('.home-map-pin'));

    const activatePin = (pin) => {
        mapPins.forEach((item) => item.classList.toggle('is-active', item === pin));
        if (mapImage) {
            mapImage.src = pin.dataset.mapImage || mapImage.src;
            mapImage.alt = pin.dataset.mapCity || 'Destination';
        }
        if (mapCountry) {
            mapCountry.textContent = pin.dataset.mapCountry || '';
        }
        if (mapCity) {
            mapCity.textContent = pin.dataset.mapCity || '';
        }
        if (mapTrip) {
            mapTrip.textContent = pin.dataset.mapTrip || '';
        }
        if (mapPrice) {
            mapPrice.textContent = pin.dataset.mapPrice || '';
        }
    };

    mapPins.forEach((pin, index) => {
        pin.addEventListener('click', () => activatePin(pin));
        if (index === 0) {
            activatePin(pin);
        }
    });

    const mapNextSection = document.querySelector('[data-map-next]');
    const mapNextLayer = document.querySelector('[data-map-next-layer]');
    const mapNextPins = Array.from(document.querySelectorAll('[data-map-next-pin]'));
    const mapNextDetailHost = document.getElementById('homeMapNextDetailHost');
    const mapNextDetail = document.getElementById('homeMapNextDetail');
    const mapNextHover = document.getElementById('homeMapNextHover');
    const mapNextHoverImage = document.getElementById('homeMapNextHoverImage');
    const mapNextHoverTrip = document.getElementById('homeMapNextHoverTrip');
    const mapNextHoverCity = document.getElementById('homeMapNextHoverCity');
    const mapNextHoverCountry = document.getElementById('homeMapNextHoverCountry');
    const mapNextHoverDuration = document.getElementById('homeMapNextHoverDuration');
    const mapNextHoverPrice = document.getElementById('homeMapNextHoverPrice');
    const mapNextImage = document.getElementById('homeMapNextImage');
    const mapNextScore = document.getElementById('homeMapNextScore');
    const mapNextCountry = document.getElementById('homeMapNextCountry');
    const mapNextCity = document.getElementById('homeMapNextCity');
    const mapNextTrip = document.getElementById('homeMapNextTrip');
    const mapNextDescription = document.getElementById('homeMapNextDescription');
    const mapNextDuration = document.getElementById('homeMapNextDuration');
    const mapNextPrice = document.getElementById('homeMapNextPrice');
    const mapNextOldPrice = document.getElementById('homeMapNextOldPrice');
    const mapNextPeriod = document.getElementById('homeMapNextPeriod');
    const mapNextIncludes = document.getElementById('homeMapNextIncludes');
    const mapNextHighlightOne = document.getElementById('homeMapNextHighlightOne');
    const mapNextHighlightTwo = document.getElementById('homeMapNextHighlightTwo');
    const mapNextHighlightThree = document.getElementById('homeMapNextHighlightThree');
    const mapNextLink = document.getElementById('homeMapNextLink');
    let activeMapNextPin = null;
    let mapNextHoverTimer = null;

    const formatMapScore = (score) => {
        const numericScore = Number.parseFloat(score || '0');
        if (!Number.isFinite(numericScore) || numericScore <= 0) {
            return '92% IA';
        }

        return numericScore > 10 ? `${Math.round(numericScore)}% IA` : `${numericScore.toFixed(1)} avis`;
    };

    const extractMoneyAmount = (value) => {
        const parsed = Number.parseFloat((value || '').toString().replace(/\s/g, '').replace(',', '.').replace(/[^0-9.-]/g, ''));
        return Number.isFinite(parsed) ? parsed : 0;
    };

    const setMoneyText = (element, label, amount, baseCurrency = 'EUR') => {
        if (!element) {
            return;
        }

        element.textContent = label;
        element.dataset.moneyBase = baseCurrency;
        element.dataset.moneyAmount = amount || String(extractMoneyAmount(label));
    };

    const refreshCurrency = () => {
        if (window.EasyTravelCurrency) {
            window.EasyTravelCurrency.refresh();
        }
    };

    const setMapNextActivePin = (pin) => {
        mapNextPins.forEach((item) => item.classList.toggle('is-active', item === pin));
    };

    const updateMapNextDetail = (pin) => {
        if (mapNextImage) {
            mapNextImage.src = pin.dataset.mapNextImage || mapNextImage.src;
            mapNextImage.alt = pin.dataset.mapNextCity || 'Destination';
        }
        if (mapNextScore) {
            mapNextScore.textContent = formatMapScore(pin.dataset.mapNextScore);
        }
        if (mapNextCountry) {
            mapNextCountry.textContent = `${pin.dataset.mapNextCountry || 'Monde'} - experience complete`;
        }
        if (mapNextCity) {
            mapNextCity.textContent = pin.dataset.mapNextCity || 'Destination';
        }
        if (mapNextTrip) {
            mapNextTrip.textContent = (pin.dataset.mapNextTrip || 'Package signature').toUpperCase();
        }
        if (mapNextDescription) {
            mapNextDescription.textContent = pin.dataset.mapNextDescription || 'Destination EasyTravel.';
        }
        if (mapNextDuration) {
            mapNextDuration.textContent = pin.dataset.mapNextDuration || '7 jours / 6 nuits';
        }
        if (mapNextPrice) {
            setMoneyText(mapNextPrice, pin.dataset.mapNextPrice || '1490 EUR', pin.dataset.mapNextPriceAmount || '');
        }
        if (mapNextOldPrice) {
            const oldPrice = pin.dataset.mapNextOldPrice || '';
            setMoneyText(mapNextOldPrice, oldPrice, pin.dataset.mapNextOldPriceAmount || '');
            mapNextOldPrice.hidden = oldPrice.trim() === '';
        }
        if (mapNextPeriod) {
            mapNextPeriod.textContent = `Meilleure periode: ${pin.dataset.mapNextPeriod || 'Toute l annee'}`;
        }
        if (mapNextIncludes) {
            mapNextIncludes.textContent = pin.dataset.mapNextIncludes || 'Vol, hotel, guide';
        }
        if (mapNextHighlightOne) {
            mapNextHighlightOne.textContent = `- ${pin.dataset.mapNextHighlightOne || 'Experience signature'}`;
        }
        if (mapNextHighlightTwo) {
            mapNextHighlightTwo.textContent = `- ${pin.dataset.mapNextHighlightTwo || 'Budget bien calibre'}`;
        }
        if (mapNextHighlightThree) {
            mapNextHighlightThree.textContent = `- ${pin.dataset.mapNextHighlightThree || 'Suggestion IA'}`;
        }
        if (mapNextLink) {
            mapNextLink.href = pin.dataset.mapNextHref || '/destinations';
        }
        refreshCurrency();
    };

    const positionMapNextHover = (pin) => {
        if (!mapNextHover || !mapNextLayer) {
            return;
        }

        const layerRect = mapNextLayer.getBoundingClientRect();
        const pinRect = pin.getBoundingClientRect();
        const hoverWidth = mapNextHover.offsetWidth || 308;
        const hoverHeight = mapNextHover.offsetHeight || 350;
        const pointX = pinRect.left - layerRect.left + pinRect.width / 2;
        const pointY = pinRect.top - layerRect.top + pinRect.height / 2;
        let x = pointX - hoverWidth / 2;
        let y = pointY - hoverHeight - 30;

        if (pointX < hoverWidth * 0.55) {
            x = pointX + 26;
        } else if (pointX > layerRect.width - hoverWidth * 0.55) {
            x = pointX - hoverWidth - 26;
        }

        x = Math.min(Math.max(16, x), Math.max(16, layerRect.width - hoverWidth - 16));
        if (y < 16) {
            y = pointY + 30;
        }
        if (y + hoverHeight > layerRect.height - 16) {
            y = Math.max(16, layerRect.height - hoverHeight - 16);
        }

        mapNextHover.style.left = `${x}px`;
        mapNextHover.style.top = `${y}px`;
    };

    const showMapNextHover = (pin) => {
        if (!mapNextHover) {
            return;
        }

        window.clearTimeout(mapNextHoverTimer);
        activeMapNextPin = pin;
        setMapNextActivePin(pin);

        if (mapNextHoverImage) {
            mapNextHoverImage.src = pin.dataset.mapNextImage || mapNextHoverImage.src;
            mapNextHoverImage.alt = pin.dataset.mapNextCity || 'Destination';
        }
        if (mapNextHoverTrip) {
            mapNextHoverTrip.textContent = (pin.dataset.mapNextTrip || 'Package signature').toUpperCase();
        }
        if (mapNextHoverCity) {
            mapNextHoverCity.textContent = pin.dataset.mapNextCity || 'Destination';
        }
        if (mapNextHoverCountry) {
            mapNextHoverCountry.textContent = `${pin.dataset.mapNextCountry || 'Monde'} - depart rapide`;
        }
        if (mapNextHoverDuration) {
            mapNextHoverDuration.textContent = pin.dataset.mapNextDuration || '7 jours / 6 nuits';
        }
        if (mapNextHoverPrice) {
            setMoneyText(mapNextHoverPrice, pin.dataset.mapNextPrice || '1490 EUR', pin.dataset.mapNextPriceAmount || '');
        }
        mapNextHover.classList.add('is-visible');
        mapNextHover.setAttribute('aria-hidden', 'false');
        positionMapNextHover(pin);
        refreshCurrency();
    };

    const hideMapNextHover = () => {
        mapNextHoverTimer = window.setTimeout(() => {
            mapNextHover?.classList.remove('is-visible');
            mapNextHover?.setAttribute('aria-hidden', 'true');
            if (!mapNextDetail?.classList.contains('is-open')) {
                mapNextPins.forEach((pin) => pin.classList.remove('is-active'));
            }
        }, 140);
    };

    const openMapNextDetail = (pin) => {
        activeMapNextPin = pin;
        window.clearTimeout(mapNextHoverTimer);
        mapNextHover?.classList.remove('is-visible');
        mapNextHover?.setAttribute('aria-hidden', 'true');
        setMapNextActivePin(pin);
        updateMapNextDetail(pin);
        mapNextDetailHost?.classList.add('is-open');
        mapNextDetail?.classList.add('is-open');
        window.setTimeout(() => {
            mapNextDetailHost?.scrollIntoView({ behavior: 'smooth', block: 'center' });
        }, 40);
    };

    mapNextPins.forEach((pin) => {
        pin.addEventListener('click', () => openMapNextDetail(pin));
        pin.addEventListener('mouseenter', () => showMapNextHover(pin));
        pin.addEventListener('focus', () => showMapNextHover(pin));
        pin.addEventListener('mouseleave', hideMapNextHover);
        pin.addEventListener('blur', hideMapNextHover);
    });

    mapNextHover?.addEventListener('mouseenter', () => {
        window.clearTimeout(mapNextHoverTimer);
        if (activeMapNextPin) {
            setMapNextActivePin(activeMapNextPin);
        }
    });
    mapNextHover?.addEventListener('mouseleave', hideMapNextHover);
    document.querySelector('[data-map-next-hover-detail]')?.addEventListener('click', () => {
        if (activeMapNextPin) {
            openMapNextDetail(activeMapNextPin);
        }
    });

    document.querySelector('[data-map-next-close]')?.addEventListener('click', () => {
        mapNextDetail?.classList.remove('is-open');
        mapNextDetailHost?.classList.remove('is-open');
        mapNextPins.forEach((pin) => pin.classList.remove('is-active'));
    });

    if (mapNextSection) {
        const mapPreviewObserver = new IntersectionObserver((entries) => {
            entries.forEach((entry) => {
                if (!entry.isIntersecting) {
                    return;
                }

                mapNextSection.classList.add('is-previewing');
                window.setTimeout(() => {
                    mapNextSection.classList.remove('is-previewing');
                    mapNextSection.classList.add('is-points-ready');
                }, 5000);
                mapPreviewObserver.unobserve(mapNextSection);
            });
        }, { threshold: 0.22 });

        mapPreviewObserver.observe(mapNextSection);
    }

    const syncJourneyProgress = () => {
        if (!journeySection) {
            return;
        }

        const rect = journeySection.getBoundingClientRect();
        const viewportHeight = window.innerHeight || 1;
        const start = viewportHeight * 0.68;
        const range = Math.max(1, rect.height - viewportHeight * 0.18);
        const progress = Math.min(100, Math.max(0, ((start - rect.top) / range) * 100));
        journeySection.style.setProperty('--journey-progress', progress.toFixed(2));

        journeyStepElements.forEach((step) => {
            const stepRect = step.getBoundingClientRect();
            const animationStart = viewportHeight * 0.85;
            const animationEnd = viewportHeight * 0.6;
            const stepProgress = Math.min(1, Math.max(0, (animationStart - stepRect.top) / Math.max(1, animationStart - animationEnd)));
            const easedProgress = 1 - Math.pow(1 - stepProgress, 2);
            const direction = Number.parseFloat(step.dataset.journeyDirection || '1');
            const isMobile = window.innerWidth < 768;
            const x = isMobile ? 0 : direction * (1 - easedProgress) * 100;
            const y = isMobile ? (1 - easedProgress) * 60 : 0;
            const scale = 0.9 + easedProgress * 0.1;
            const icon = step.querySelector('[data-journey-icon]');

            step.style.opacity = String(easedProgress);
            step.style.transform = `translate3d(${x}px, ${y}px, 0) scale(${scale})`;

            if (icon) {
                const iconScale = Math.max(0.001, easedProgress);
                const rotation = -180 + easedProgress * 180;
                icon.style.transform = `scale(${iconScale}) rotate(${rotation}deg)`;
            }
        });
    };

    const requestJourneySync = () => {
        if (journeyFrame !== null) {
            return;
        }

        journeyFrame = window.requestAnimationFrame(() => {
            journeyFrame = null;
            syncJourneyProgress();
        });
    };

    document.getElementById('homeNewsletterForm')?.addEventListener('submit', (event) => {
        event.preventDefault();
        const button = event.currentTarget.querySelector('button');
        if (!button) {
            return;
        }

        const previousText = button.textContent;
        button.textContent = 'Inscription envoyee';
        window.setTimeout(() => {
            button.textContent = previousText;
        }, 2200);
    });

    updateScrollState();
    syncAtmosphereScroll();
    syncDestinationParallax();
    syncOfferParallax();
    syncJourneyProgress();
    window.addEventListener('scroll', updateScrollState, { passive: true });
    window.addEventListener('scroll', requestAtmosphereSync, { passive: true });
    window.addEventListener('scroll', requestDestinationSync, { passive: true });
    window.addEventListener('scroll', requestOfferSync, { passive: true });
    window.addEventListener('scroll', requestJourneySync, { passive: true });
    window.addEventListener('resize', requestAtmosphereSync);
    window.addEventListener('resize', requestDestinationSync);
    window.addEventListener('resize', requestOfferSync);
    window.addEventListener('resize', requestJourneySync);
    window.addEventListener('load', requestAtmosphereSync);
    window.addEventListener('load', requestDestinationSync);
    window.addEventListener('load', requestOfferSync);
    window.addEventListener('load', requestJourneySync);
});
