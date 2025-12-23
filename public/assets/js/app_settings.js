(function () {
    const STORAGE_KEY = "appSettings";

    const defaultSettings = {
        currency: "USD",
        dateFormat: "DD/MM/YYYY",
        numberFormat: "1,234.56",
        startOfWeek: "sunday",
        showCurrencySymbol: true,
        showThousandsSeparator: true,
        autoSave: true,
        showNotifications: false,
        compactView: false
    };

    function getSettings() {
        try {
            const raw = localStorage.getItem(STORAGE_KEY);
            if (!raw) return { ...defaultSettings };
            const parsed = JSON.parse(raw);
            return { ...defaultSettings, ...parsed };
        } catch (e) {
            return { ...defaultSettings };
        }
    }

    function currencySymbol(code) {
        const map = { USD: "$", EUR: "€", GBP: "£", ILS: "₪" };
        return map[code] || "$";
    }

    function formatNumber(num, settings) {
        const decimals = 2;
        let formatted = Number(num).toFixed(decimals);

        if (!settings.showThousandsSeparator) {

            if (settings.numberFormat === "1.234,56") {
                formatted = formatted.replace(".", ",");
            }
            return formatted;
        }

        if (settings.numberFormat === "1,234.56") {
            return formatted.replace(/\B(?=(\d{3})+(?!\d))/g, ",");
        }

        if (settings.numberFormat === "1.234,56") {
            formatted = formatted.replace(".", ","); // decimal to comma first
            return formatted.replace(/\B(?=(\d{3})+(?!\d))/g, ".");
        }

        if (settings.numberFormat === "1 234.56") {
            return formatted.replace(/\B(?=(\d{3})+(?!\d))/g, " ");
        }

        return formatted;
    }

    function formatMoney(value, settings) {
        const n = Number(value);
        if (!Number.isFinite(n)) return "";

        const sign = n < 0 ? "-" : "";
        const abs = Math.abs(n);

        const sym = currencySymbol(settings.currency);
        const amountText = formatNumber(abs, settings);

        if (settings.showCurrencySymbol) {
            return sign + sym + amountText;
        }

        return sign + amountText + " " + settings.currency;
    }

    function formatDate(dateStr, settings) {
        if (!dateStr) return "";
        // expecting YYYY-MM-DD
        const parts = String(dateStr).split("-");
        if (parts.length < 3) return dateStr;

        const year = parts[0];
        const month = parts[1].padStart(2, "0");
        const day = parts[2].padStart(2, "0");

        switch (settings.dateFormat) {
            case "DD/MM/YYYY":
                return `${day}/${month}/${year}`;
            case "MM/DD/YYYY":
                return `${month}/${day}/${year}`;
            case "YYYY-MM-DD":
                return `${year}-${month}-${day}`;
            case "DD-MM-YYYY":
                return `${day}-${month}-${year}`;
            default:
                return `${day}/${month}/${year}`;
        }
    }

    function applyCompactView(settings) {
        document.body.classList.toggle("compact-view", !!settings.compactView);
    }

    function applyFormatting() {
        const settings = getSettings();

        applyCompactView(settings);

        document.querySelectorAll("[data-money]").forEach((el) => {
            const raw = el.getAttribute("data-money");
            el.textContent = formatMoney(raw, settings);
        });

        document.querySelectorAll("[data-date]").forEach((el) => {
            const raw = el.getAttribute("data-date");
            el.textContent = formatDate(raw, settings);
        });
    }

    window.addEventListener("appSettingsChanged", applyFormatting);

    window.addEventListener("storage", function (e) {
        if (e.key === STORAGE_KEY || e.key === "__appSettingsPing") {
            applyFormatting();
        }
    });

    document.addEventListener("DOMContentLoaded", applyFormatting);

    window.FMAppSettings = { getSettings, applyFormatting };
})();
