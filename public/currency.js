(function () {
    const STORAGE_KEY = 'easytravel_currency';
    const TND_VALUE = {
        TND: 1,
        EUR: 3.35,
        USD: 3.12,
    };
    const LABELS = {
        TND: 'TND',
        EUR: 'EUR',
        USD: 'USD',
    };

    const supported = Object.keys(TND_VALUE);

    const getStoredCurrency = () => {
        const value = window.localStorage.getItem(STORAGE_KEY) || 'TND';
        return supported.includes(value) ? value : 'TND';
    };

    const parseAmount = (value) => {
        const normalized = (value || '')
            .toString()
            .replace(/\s/g, '')
            .replace(',', '.')
            .replace(/[^0-9.-]/g, '');
        const parsed = Number.parseFloat(normalized);
        return Number.isFinite(parsed) ? parsed : 0;
    };

    const convert = (amount, baseCurrency, targetCurrency) => {
        const base = supported.includes(baseCurrency) ? baseCurrency : 'TND';
        const target = supported.includes(targetCurrency) ? targetCurrency : 'TND';
        return (amount * TND_VALUE[base]) / TND_VALUE[target];
    };

    const formatAmount = (amount, currency) => {
        const decimals = amount < 100 ? 2 : 0;
        const formatted = new Intl.NumberFormat('fr-FR', {
            minimumFractionDigits: decimals,
            maximumFractionDigits: decimals,
        }).format(amount);

        return `${formatted} ${LABELS[currency] || currency}`;
    };

    const syncSelects = (currency) => {
        document.querySelectorAll('[data-currency-select]').forEach((select) => {
            select.value = currency;
        });

        document.querySelectorAll('[data-currency-current]').forEach((element) => {
            element.textContent = LABELS[currency] || currency;
        });
    };

    const refreshMoney = () => {
        const currency = getStoredCurrency();
        syncSelects(currency);

        document.querySelectorAll('[data-money]').forEach((element) => {
            const baseCurrency = (element.dataset.moneyBase || 'TND').toUpperCase();
            const rawAmount = element.dataset.moneyAmount || '';
            const rawText = element.textContent || '';
            if (rawAmount.trim() === '' && rawText.trim() === '') {
                return;
            }

            const amount = rawAmount.trim() !== ''
                ? parseAmount(rawAmount)
                : parseAmount(rawText);
            const converted = convert(amount, baseCurrency, currency);
            element.textContent = formatAmount(converted, currency);
        });
    };

    const ensureFloatingSwitcher = () => {
        if (document.querySelector('[data-currency-switcher]')) {
            return;
        }

        const switcher = document.createElement('div');
        switcher.className = 'currency-switcher currency-switcher-floating';
        switcher.setAttribute('data-currency-switcher', '');
        switcher.innerHTML = `
            <span class="currency-switcher-icon" aria-hidden="true">$</span>
            <span class="currency-switcher-copy">
                <label class="currency-switcher-label" for="currencySwitcherAuto">Devise</label>
                <strong class="currency-switcher-current" data-currency-current>TND</strong>
            </span>
            <select id="currencySwitcherAuto" class="currency-switcher-select" data-currency-select aria-label="Choisir la devise">
                <option value="TND">TND</option>
                <option value="EUR">EUR</option>
                <option value="USD">USD</option>
            </select>
        `;
        document.body.appendChild(switcher);
    };

    const bindSwitchers = () => {
        document.addEventListener('change', (event) => {
            const select = event.target.closest('[data-currency-select]');
            if (!select) {
                return;
            }

            const currency = supported.includes(select.value) ? select.value : 'TND';
            window.localStorage.setItem(STORAGE_KEY, currency);
            refreshMoney();
        });
    };

    document.addEventListener('DOMContentLoaded', () => {
        ensureFloatingSwitcher();
        bindSwitchers();
        refreshMoney();
    });

    window.EasyTravelCurrency = {
        refresh: refreshMoney,
        convert,
        formatAmount,
        get current() {
            return getStoredCurrency();
        },
    };
})();
