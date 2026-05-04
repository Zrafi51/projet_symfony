/**
 * CUSTOM-CURSOR.JS - Curseur Avion Animé
 */

(function() {
    // Ne pas activer sur mobile
    if (window.innerWidth <= 768) {
        return;
    }

    // Créer le curseur avion
    const cursor = document.createElement('div');
    cursor.id = 'custom-cursor';
    cursor.innerHTML = `
        <svg viewBox="0 0 32 32" fill="none" xmlns="http://www.w3.org/2000/svg">
            <!-- Corps de l'avion -->
            <path d="M16 4L10 24L16 21L22 24L16 4Z" fill="url(#planeGradient)" stroke="#E76F51" stroke-width="2" stroke-linejoin="round"/>
            
            <!-- Ailes -->
            <path d="M8 12L16 10L24 12" stroke="#E76F51" stroke-width="2" stroke-linecap="round"/>
            <path d="M10 18L16 20L22 18" stroke="#E76F51" stroke-width="2" stroke-linecap="round"/>
            
            <!-- Cockpit -->
            <circle cx="16" cy="12" r="3" fill="#0B3C5D" opacity="0.6"/>
            <circle cx="16" cy="12" r="2" fill="#1D5F8A"/>
            
            <!-- Hublots -->
            <circle cx="16" cy="16" r="1.5" fill="#0B3C5D" opacity="0.4"/>
            <circle cx="16" cy="19" r="1.5" fill="#0B3C5D" opacity="0.4"/>
            
            <!-- Gradient -->
            <defs>
                <linearGradient id="planeGradient" x1="16" y1="4" x2="16" y2="24">
                    <stop offset="0%" stop-color="#F4A261"/>
                    <stop offset="50%" stop-color="#F4A261"/>
                    <stop offset="100%" stop-color="#E76F51"/>
                </linearGradient>
            </defs>
        </svg>
    `;
    document.body.appendChild(cursor);

    // Créer la traînée
    const trail = document.createElement('div');
    trail.id = 'cursor-trail';
    document.body.appendChild(trail);

    let mouseX = 0;
    let mouseY = 0;
    let cursorX = 0;
    let cursorY = 0;
    let lastX = 0;
    let lastY = 0;

    // Suivre la position de la souris
    document.addEventListener('mousemove', (e) => {
        mouseX = e.clientX;
        mouseY = e.clientY;
    });

    // Animation fluide du curseur
    function animateCursor() {
        // Interpolation pour mouvement fluide
        cursorX += (mouseX - cursorX) * 0.2;
        cursorY += (mouseY - cursorY) * 0.2;

        // Calculer l'angle de rotation basé sur la direction
        const deltaX = mouseX - lastX;
        const deltaY = mouseY - lastY;
        const angle = Math.atan2(deltaY, deltaX) * (180 / Math.PI);

        // Mettre à jour la position et rotation
        cursor.style.left = cursorX + 'px';
        cursor.style.top = cursorY + 'px';
        
        // Rotation progressive vers la direction du mouvement
        if (Math.abs(deltaX) > 0.5 || Math.abs(deltaY) > 0.5) {
            cursor.style.transform = `translate(-50%, -50%) rotate(${angle}deg)`;
        }

        // Mettre à jour la traînée
        const speed = Math.sqrt(deltaX * deltaX + deltaY * deltaY);
        trail.style.left = cursorX + 'px';
        trail.style.top = cursorY + 'px';
        trail.style.height = Math.min(speed * 4, 60) + 'px';
        trail.style.transform = `rotate(${angle + 90}deg)`;

        // Créer des particules si mouvement rapide
        if (speed > 3 && Math.random() > 0.6) {
            createParticle(cursorX, cursorY);
        }

        lastX = cursorX;
        lastY = cursorY;

        requestAnimationFrame(animateCursor);
    }

    // Créer une particule de nuage
    function createParticle(x, y) {
        const particle = document.createElement('div');
        particle.className = 'cursor-particle';
        particle.style.left = x + (Math.random() - 0.5) * 30 + 'px';
        particle.style.top = y + (Math.random() - 0.5) * 30 + 'px';
        particle.style.width = (Math.random() * 4 + 3) + 'px';
        particle.style.height = particle.style.width;
        document.body.appendChild(particle);

        setTimeout(() => {
            particle.remove();
        }, 800);
    }

    // Démarrer l'animation
    animateCursor();

    // Cacher le curseur quand il quitte la fenêtre
    document.addEventListener('mouseleave', () => {
        cursor.style.opacity = '0';
        trail.style.opacity = '0';
    });

    document.addEventListener('mouseenter', () => {
        cursor.style.opacity = '1';
        trail.style.opacity = '1';
    });

    // Effet au clic
    document.addEventListener('mousedown', () => {
        cursor.style.transform = `translate(-50%, -50%) rotate(${Math.atan2(mouseY - lastY, mouseX - lastX) * (180 / Math.PI)}deg) scale(0.9)`;
        
        // Créer plusieurs particules au clic
        for (let i = 0; i < 5; i++) {
            setTimeout(() => createParticle(cursorX, cursorY), i * 50);
        }
    });

    document.addEventListener('mouseup', () => {
        cursor.style.transform = `translate(-50%, -50%) rotate(${Math.atan2(mouseY - lastY, mouseX - lastX) * (180 / Math.PI)}deg) scale(1)`;
    });

    // Effet spécial sur les liens
    const interactiveElements = document.querySelectorAll('a, button, input, textarea, [role="button"]');
    
    interactiveElements.forEach(element => {
        element.addEventListener('mouseenter', () => {
            cursor.style.filter = 'drop-shadow(0 0 15px rgba(244, 162, 97, 1)) drop-shadow(0 0 25px rgba(231, 111, 81, 0.6))';
        });
        
        element.addEventListener('mouseleave', () => {
            cursor.style.filter = 'drop-shadow(0 3px 8px rgba(0, 0, 0, 0.4)) drop-shadow(0 0 15px rgba(244, 162, 97, 0.3))';
        });
    });

    // Observer pour les nouveaux éléments ajoutés dynamiquement
    const observer = new MutationObserver((mutations) => {
        mutations.forEach((mutation) => {
            mutation.addedNodes.forEach((node) => {
                if (node.nodeType === 1) {
                    const newInteractive = node.querySelectorAll('a, button, input, textarea, [role="button"]');
                    newInteractive.forEach(element => {
                        element.addEventListener('mouseenter', () => {
                            cursor.style.filter = 'drop-shadow(0 0 15px rgba(244, 162, 97, 1)) drop-shadow(0 0 25px rgba(231, 111, 81, 0.6))';
                        });
                        
                        element.addEventListener('mouseleave', () => {
                            cursor.style.filter = 'drop-shadow(0 3px 8px rgba(0, 0, 0, 0.4)) drop-shadow(0 0 15px rgba(244, 162, 97, 0.3))';
                        });
                    });
                }
            });
        });
    });

    observer.observe(document.body, {
        childList: true,
        subtree: true
    });
})();
