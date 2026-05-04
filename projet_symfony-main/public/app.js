document.addEventListener('DOMContentLoaded', () => {
    const wrappers = Array.from(document.querySelectorAll('[data-account-menu-wrapper]'));
    if (wrappers.length === 0) {
        return;
    }

    const setMenuState = (wrapper, isOpen) => {
        const button = wrapper.querySelector('[data-account-menu-button]');
        const menu = wrapper.querySelector('[data-account-menu]');
        if (!button || !menu) {
            return;
        }

        button.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
        menu.hidden = !isOpen;
        menu.classList.toggle('is-open', isOpen);
    };

    const closeAllMenus = (exceptWrapper = null) => {
        wrappers.forEach((wrapper) => {
            if (wrapper !== exceptWrapper) {
                setMenuState(wrapper, false);
            }
        });
    };

    wrappers.forEach((wrapper) => {
        const button = wrapper.querySelector('[data-account-menu-button]');
        const menu = wrapper.querySelector('[data-account-menu]');
        if (!button || !menu) {
            return;
        }

        setMenuState(wrapper, false);

        button.addEventListener('click', (event) => {
            event.preventDefault();
            event.stopPropagation();

            const shouldOpen = menu.hidden;
            closeAllMenus(shouldOpen ? wrapper : null);
            setMenuState(wrapper, shouldOpen);
        });

        menu.addEventListener('click', (event) => {
            event.stopPropagation();
        });
    });

    document.addEventListener('click', () => {
        closeAllMenus();
    });

    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape') {
            closeAllMenus();
        }
    });
});
