/**
 * HAND-TRACKING.JS - Remote Cursor Control
 * Features: Cursor, Click, Scroll (Left=Up, Right=Down), and Persistence
 */

class HandCursorControl {
    constructor() {
        this.isActive = false;
        this.isLoading = false;
        this.hands = null;
        this.camera = null;
        this.videoElement = null;
        this.canvasElement = null;
        this.canvasCtx = null;
        this.cursorElement = null;
        this.toggleBtn = null;
        this.lastHoveredElement = null;

        // Smooth movement properties
        this.cursorX = window.innerWidth / 2;
        this.cursorY = window.innerHeight / 2;
        this.targetX = this.cursorX;
        this.targetY = this.cursorY;
        this.lerpAmount = 0.1; // Slower, more stable movement (usual "dpi" feel)
        this.cameraMargin = 0.15; // 15% margin to reach corners easily

        // Click gesture properties
        this.isPinching = false;
        this.pinchThreshold = 0.05;
        this.lastClickTime = 0;
        this.clickCooldown = 600;

        // Scroll properties
        this.slowScrollSpeed = 70; // Pixels per frame
        this.isScrolling = false;
        this.edgeThreshold = 100; // Pixels from top/bottom
        this.edgeScrollSpeed = 20;

        this.initUI();
        this.checkPersistence();
    }

    initUI() {
        if (!document.getElementById('hand-tracking-preview-container')) {
            const container = document.createElement('div');
            container.id = 'hand-tracking-preview-container';
            container.innerHTML = `
                <video id="hand-tracking-video" playsinline></video>
                <canvas id="hand-tracking-canvas"></canvas>
                <div style="position:absolute; top:5px; left:5px; color:white; font-size:10px; background:rgba(0,0,0,0.5); padding:2px 5px; border-radius:3px;">
                    Hand Tracking Mode
                </div>
            `;
            document.body.appendChild(container);
        }

        this.videoElement = document.getElementById('hand-tracking-video');
        this.canvasElement = document.getElementById('hand-tracking-canvas');
        this.canvasCtx = this.canvasElement.getContext('2d');

        if (!document.getElementById('hand-cursor')) {
            this.cursorElement = document.createElement('div');
            this.cursorElement.id = 'hand-cursor';
            document.body.appendChild(this.cursorElement);
        } else {
            this.cursorElement = document.getElementById('hand-cursor');
        }

        this.toggleBtn = document.getElementById('hand-tracking-toggle');
        if (this.toggleBtn) {
            this.toggleBtn.addEventListener('click', () => this.toggle());
        }

        this.animate();
    }

    checkPersistence() {
        const savedState = localStorage.getItem('handTrackingActive');
        if (savedState === 'true') {
            setTimeout(() => this.start(), 1000);
        }
    }

    async initMediaPipe() {
        if (this.hands) return;
        this.isLoading = true;
        if (this.toggleBtn) this.toggleBtn.classList.add('is-loading');

        try {
            this.hands = new Hands({
                locateFile: (file) => `https://cdn.jsdelivr.net/npm/@mediapipe/hands/${file}`
            });

            this.hands.setOptions({
                maxNumHands: 2, // Allow 2 hands for different scroll directions
                modelComplexity: 1,
                minDetectionConfidence: 0.6,
                minTrackingConfidence: 0.6
            });

            this.hands.onResults((results) => this.onResults(results));

            this.camera = new Camera(this.videoElement, {
                onFrame: async () => {
                    if (this.isActive) {
                        await this.hands.send({ image: this.videoElement });
                    }
                },
                width: 640,
                height: 480
            });

            this.isLoading = false;
            if (this.toggleBtn) this.toggleBtn.classList.remove('is-loading');
        } catch (error) {
            console.error("MediaPipe Init Error:", error);
            this.stop();
        }
    }

    async toggle() {
        if (this.isActive) this.stop();
        else await this.start();
    }

    async start() {
        await this.initMediaPipe();
        this.isActive = true;
        localStorage.setItem('handTrackingActive', 'true');
        if (this.toggleBtn) this.toggleBtn.classList.add('is-active');
        document.getElementById('hand-tracking-preview-container').classList.add('is-active');
        this.cursorElement.classList.add('is-active');
        // Hide custom airplane cursor if present
        const customCursor = document.getElementById('custom-cursor');
        const cursorTrail = document.getElementById('cursor-trail');
        if (customCursor) customCursor.style.display = 'none';
        if (cursorTrail) cursorTrail.style.display = 'none';
        document.body.style.cursor = 'none';
        try { await this.camera.start(); } catch (err) { this.stop(); }
    }

    stop() {
        this.isActive = false;
        localStorage.setItem('handTrackingActive', 'false');
        if (this.toggleBtn) this.toggleBtn.classList.remove('is-active');
        document.getElementById('hand-tracking-preview-container').classList.remove('is-active');
        this.cursorElement.classList.remove('is-active');
        // Restore custom airplane cursor if present
        const customCursor = document.getElementById('custom-cursor');
        const cursorTrail = document.getElementById('cursor-trail');
        if (customCursor) customCursor.style.display = '';
        if (cursorTrail) cursorTrail.style.display = '';
        document.body.style.cursor = '';
        if (this.camera) this.camera.stop();
    }

    onResults(results) {
        this.canvasCtx.save();
        this.canvasCtx.clearRect(0, 0, this.canvasElement.width, this.canvasElement.height);
        this.canvasCtx.drawImage(results.image, 0, 0, this.canvasElement.width, this.canvasElement.height);

        this.isScrolling = false;
        let scrollDirection = 0; // -1 for up, 1 for down

        if (results.multiHandLandmarks && results.multiHandLandmarks.length > 0) {
            results.multiHandLandmarks.forEach((landmarks, index) => {
                const handedness = results.multiHandedness[index].label; // "Left" or "Right"
                
                // Feedback
                drawConnectors(this.canvasCtx, landmarks, HAND_CONNECTIONS, { color: '#E76F51', lineWidth: 2 });
                drawLandmarks(this.canvasCtx, landmarks, { color: '#FFFFFF', lineWidth: 1, radius: 2 });

                // Gesture logic
                const indexTip = landmarks[8];
                const thumbTip = landmarks[4];
                const middleTip = landmarks[12];
                const ringTip = landmarks[16];
                const pinkyTip = landmarks[20];

                const isHandOpen = 
                    indexTip.y < landmarks[6].y && 
                    middleTip.y < landmarks[10].y && 
                    ringTip.y < landmarks[14].y && 
                    pinkyTip.y < landmarks[18].y;

                if (isHandOpen) {
                    this.isScrolling = true;
                    if (handedness === 'Right') {
                        scrollDirection = 1;
                    } else {
                        scrollDirection = -1;
                    }
                } else if (index === 0) {
                    // Map camera coordinates [margin, 1-margin] to screen [0, 1]
                    let normalizedX = (indexTip.x - this.cameraMargin) / (1 - 2 * this.cameraMargin);
                    let normalizedY = (indexTip.y - this.cameraMargin) / (1 - 2 * this.cameraMargin);
                    
                    // Clamp to [0, 1]
                    normalizedX = Math.max(0, Math.min(1, normalizedX));
                    normalizedY = Math.max(0, Math.min(1, normalizedY));

                    // Invert X for mirrored video
                    this.targetX = (1 - normalizedX) * window.innerWidth;
                    this.targetY = normalizedY * window.innerHeight;

                    const distance = Math.sqrt(
                        Math.pow(indexTip.x - thumbTip.x, 2) +
                        Math.pow(indexTip.y - thumbTip.y, 2)
                    );

                    if (distance < this.pinchThreshold) {
                        if (!this.isPinching) {
                            this.triggerClick();
                            this.isPinching = true;
                            this.cursorElement.classList.add('is-clicking');
                        }
                    } else {
                        this.isPinching = false;
                        this.cursorElement.classList.remove('is-clicking');
                    }
                }
            });
        }

        if (this.isScrolling) {
            this.scrollTarget(scrollDirection * this.slowScrollSpeed);
            this.cursorElement.style.borderColor = scrollDirection > 0 ? '#3498db' : '#9b59b6'; // Blue for down, Purple for up
        } else {
            this.cursorElement.style.borderColor = '#E76F51';
        }

        this.canvasCtx.restore();
    }

    getScrollParent(el) {
        if (!el || el === document.body || el === document.documentElement) return window;
        const style = window.getComputedStyle(el);
        const overflowY = style.getPropertyValue('overflow-y') || style.getPropertyValue('overflow');
        const isScrollable = (overflowY === 'auto' || overflowY === 'scroll') && el.scrollHeight > el.clientHeight;
        return isScrollable ? el : this.getScrollParent(el.parentElement);
    }

    scrollTarget(speed) {
        const el = document.elementFromPoint(this.cursorX, this.cursorY);
        const container = this.getScrollParent(el);
        if (container === window) {
            window.scrollBy(0, speed);
        } else {
            container.scrollBy(0, speed);
        }
    }

    triggerClick() {
        const now = Date.now();
        if (now - this.lastClickTime < this.clickCooldown) return;
        this.lastClickTime = now;
        const rawEl = document.elementFromPoint(this.cursorX, this.cursorY);
        if (!rawEl) return;

        // Find the closest interactive element (button, link, input, etc.)
        let el = rawEl;
        const interactive = rawEl.closest('button, a, input, select, textarea, [role="button"], label');
        if (interactive) el = interactive;

        // --- Helper: find a checkbox/radio input from the target or its label ---
        const findToggleInput = (target) => {
            if (target.tagName === 'INPUT' && (target.type === 'checkbox' || target.type === 'radio')) {
                return target;
            }
            if (target.tagName === 'LABEL') {
                const inner = target.querySelector('input[type="checkbox"], input[type="radio"]');
                if (inner) return inner;
                const forId = target.getAttribute('for');
                if (forId) {
                    const linked = document.getElementById(forId);
                    if (linked && (linked.type === 'checkbox' || linked.type === 'radio')) return linked;
                }
            }
            const parentLabel = target.closest('label');
            if (parentLabel) {
                const inner = parentLabel.querySelector('input[type="checkbox"], input[type="radio"]');
                if (inner) return inner;
            }
            return null;
        };

        // === 1. Handle expanded SELECT option selection ===
        if (el.tagName === 'SELECT' && el.getAttribute('data-hand-expanded') === 'true') {
            const rect = el.getBoundingClientRect();
            const itemHeight = rect.height / el.size;
            const index = Math.floor((this.cursorY - rect.top) / itemHeight);
            if (index >= 0 && index < el.options.length) {
                el.selectedIndex = index;
                el.dispatchEvent(new Event('change', { bubbles: true }));
            }
            this.createClickRipple(this.cursorX, this.cursorY);
            return;
        }

        // === 2. Handle checkbox / radio toggle ===
        const toggleInput = findToggleInput(el);
        if (toggleInput) {
            if (toggleInput.type === 'checkbox') {
                toggleInput.checked = !toggleInput.checked;
            } else if (toggleInput.type === 'radio') {
                toggleInput.checked = true;
            }
            toggleInput.dispatchEvent(new Event('change', { bubbles: true }));
            toggleInput.dispatchEvent(new Event('input', { bubbles: true }));
            this.createClickRipple(this.cursorX, this.cursorY);
            return;
        }

        // === 3. Handle file inputs (Browsers block file dialogs from untrusted synthetic events) ===
        // We must detect file inputs AND file trigger buttons (which we know use the class or attribute)
        const isFileTrigger = (el.tagName === 'INPUT' && el.type === 'file') ||
                              el.hasAttribute('data-admin-avatar-trigger') ||
                              el.hasAttribute('data-profile-avatar-trigger') ||
                              (el.tagName === 'BUTTON' && el.textContent.toLowerCase().includes('choisir'));
        
        if (isFileTrigger) {
            // Since we CANNOT open the file dialog due to browser security (untrusted event),
            // we show a nice toast/alert to the user instead of failing silently.
            let toast = document.getElementById('hand-tracking-file-toast');
            if (!toast) {
                toast = document.createElement('div');
                toast.id = 'hand-tracking-file-toast';
                toast.style.position = 'fixed';
                toast.style.bottom = '20px';
                toast.style.left = '50%';
                toast.style.transform = 'translateX(-50%)';
                toast.style.backgroundColor = '#E76F51';
                toast.style.color = 'white';
                toast.style.padding = '12px 24px';
                toast.style.borderRadius = '8px';
                toast.style.zIndex = '9999999';
                toast.style.fontWeight = 'bold';
                toast.style.boxShadow = '0 4px 12px rgba(0,0,0,0.3)';
                toast.style.transition = 'opacity 0.3s ease';
                document.body.appendChild(toast);
            }
            toast.textContent = 'Action bloquee : Veuillez utiliser la souris pour choisir un fichier (Securite du navigateur).';
            toast.style.opacity = '1';
            setTimeout(() => { toast.style.opacity = '0'; }, 4000);
            
            this.createClickRipple(this.cursorX, this.cursorY);
            return;
        }

        // === 4. Handle SELECT elements (expand them) ===
        if (el.tagName === 'SELECT') {
            el.focus();
            const isExpanded = el.getAttribute('data-hand-expanded') === 'true';
            if (!isExpanded) {
                const originalSize = el.size || 0;
                el.setAttribute('data-hand-original-size', originalSize);
                el.setAttribute('data-hand-expanded', 'true');
                el.size = Math.min(10, el.options.length || 5);
                const originalZ = el.style.zIndex;
                el.style.zIndex = '1000005';
                const collapse = () => {
                    el.size = parseInt(el.getAttribute('data-hand-original-size') || '0');
                    el.removeAttribute('data-hand-expanded');
                    el.style.zIndex = originalZ;
                    el.removeEventListener('change', collapse);
                    el.removeEventListener('blur', collapse);
                    el.removeEventListener('click', collapse);
                };
                setTimeout(() => {
                    el.addEventListener('change', collapse);
                    el.addEventListener('blur', collapse);
                    el.addEventListener('click', (e) => {
                         if (el.getAttribute('data-hand-expanded') === 'true') collapse();
                    }, { once: true });
                }, 100);
            }
            this.createClickRipple(this.cursorX, this.cursorY);
            return;
        }

        // === 5. Focus text inputs / textareas ===
        if (['INPUT', 'TEXTAREA'].includes(el.tagName)) {
            el.focus();
        }

        // === 6. Standard click for all other elements ===
        // To avoid double-firing (which instantly toggles menus like the notification menu twice),
        // we ONLY dispatch synthetic events on the raw element. Event delegation will handle it.
        // We do NOT call el.click() if we are already dispatching to its child.
        const evtOpts = {
            view: window, bubbles: true, cancelable: true,
            clientX: this.cursorX, clientY: this.cursorY
        };
        rawEl.dispatchEvent(new MouseEvent('mousedown', evtOpts));
        rawEl.dispatchEvent(new MouseEvent('mouseup', evtOpts));
        rawEl.dispatchEvent(new MouseEvent('click', evtOpts));
        
        // For specific cases where synthetic click doesn't work (like anchor tags without children),
        // or buttons that strictly require native .click(), we handle them exclusively if rawEl === el.
        if (rawEl === el && typeof el.click === 'function' && el.tagName !== 'INPUT') {
             // If rawEl is the exact button (no child spans), dispatchEvent already fired a click.
             // We do nothing more.
        } else if (rawEl !== el && el.tagName === 'A') {
             // For links, sometimes synthetic clicks on children don't trigger navigation.
             // We programmatically navigate if it has an href.
             if (el.href) window.location.href = el.href;
        }

        this.createClickRipple(this.cursorX, this.cursorY);
    }

    handleHover() {
        const el = document.elementFromPoint(this.cursorX, this.cursorY);
        if (el !== this.lastHoveredElement) {
            if (this.lastHoveredElement) {
                this.lastHoveredElement.dispatchEvent(new MouseEvent('mouseleave', { bubbles: true }));
                this.lastHoveredElement.dispatchEvent(new MouseEvent('mouseout', { bubbles: true }));
            }
            if (el) {
                el.dispatchEvent(new MouseEvent('mouseenter', { bubbles: true }));
                el.dispatchEvent(new MouseEvent('mouseover', { bubbles: true }));
            }
            this.lastHoveredElement = el;
        }
        if (el) {
            el.dispatchEvent(new MouseEvent('mousemove', {
                view: window, bubbles: true, cancelable: true,
                clientX: this.cursorX, clientY: this.cursorY
            }));

            // Pre-select options in expanded selects for visual feedback
            if (el.tagName === 'SELECT' && el.getAttribute('data-hand-expanded') === 'true') {
                const rect = el.getBoundingClientRect();
                const itemHeight = rect.height / el.size;
                const index = Math.floor((this.cursorY - rect.top) / itemHeight);
                if (index >= 0 && index < el.options.length && el.selectedIndex !== index) {
                    el.selectedIndex = index;
                }
            }
        }
    }

    createClickRipple(x, y) {
        const ripple = document.createElement('div');
        ripple.className = 'hand-click-ripple';
        ripple.style.left = x + 'px';
        ripple.style.top = y + 'px';
        document.body.appendChild(ripple);
        setTimeout(() => ripple.remove(), 600);
    }

    animate() {
        if (this.isActive) {
            this.cursorX += (this.targetX - this.cursorX) * this.lerpAmount;
            this.cursorY += (this.targetY - this.cursorY) * this.lerpAmount;
            this.cursorElement.style.left = this.cursorX + 'px';
            this.cursorElement.style.top = this.cursorY + 'px';

            this.handleHover();

            // Edge Scrolling logic
            if (!this.isScrolling) { // Only edge scroll if not manually scrolling with hand gesture
                if (this.cursorY < this.edgeThreshold) {
                    this.scrollTarget(-this.edgeScrollSpeed);
                    this.cursorElement.classList.add('is-edge-scrolling');
                    this.cursorElement.style.borderColor = '#9b59b6';
                } else if (this.cursorY > window.innerHeight - this.edgeThreshold) {
                    this.scrollTarget(this.edgeScrollSpeed);
                    this.cursorElement.classList.add('is-edge-scrolling');
                    this.cursorElement.style.borderColor = '#3498db';
                } else {
                    this.cursorElement.classList.remove('is-edge-scrolling');
                    if (!this.isScrolling) this.cursorElement.style.borderColor = '#E76F51';
                }
            }
        }
        requestAnimationFrame(() => this.animate());
    }
}

document.addEventListener('DOMContentLoaded', () => {
    window.handControl = new HandCursorControl();
});
