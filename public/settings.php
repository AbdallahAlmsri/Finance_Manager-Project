<?php

require_once __DIR__ . '/../includes/web_boot.php';
require_once __DIR__ . '/../includes/auth.php';
require_login();

$userId = current_user_id();
$currentPage = 'settings';
$pageTitle   = 'Settings';
require_once __DIR__ . '/../includes/header.php';
?>

<main class="main-content">

    <section class="panel">
        <h2 class="panel-title">Application Settings</h2>
        <p class="panel-text">Customize your finance manager experience. All settings are saved locally in your browser.</p>
    </section>

    <section class="panel panel-form">
        <h2 class="panel-title">General Settings</h2>
        <form id="settingsForm" class="form-grid">

            <div class="form-group">
                <label for="currency">Currency <span class="required">*</span></label>
                <select id="currency" name="currency" class="form-control" required>
                    <option value="USD">USD - US Dollar ($)</option>
                    <option value="EUR">EUR - Euro (€)</option>
                    <option value="GBP">GBP - British Pound (£)</option>
                    <option value="ILS">ILS - Israeli Shekel (₪)</option>
                </select>
            </div>

            <div class="form-group">
                <label for="dateFormat">Date Format <span class="required">*</span></label>
                <select id="dateFormat" name="dateFormat" class="form-control" required>
                    <option value="DD/MM/YYYY">DD/MM/YYYY (e.g., 30/11/2025)</option>
                    <option value="MM/DD/YYYY">MM/DD/YYYY (e.g., 11/30/2025)</option>
                    <option value="YYYY-MM-DD">YYYY-MM-DD (e.g., 2025-11-30)</option>
                    <option value="DD-MM-YYYY">DD-MM-YYYY (e.g., 30-11-2025)</option>
                </select>
            </div>

            <div class="form-group">
                <label for="numberFormat">Number Format <span class="required">*</span></label>
                <select id="numberFormat" name="numberFormat" class="form-control" required>
                    <option value="1,234.56">1,234.56 (US Style)</option>
                    <option value="1.234,56">1.234,56 (European Style)</option>
                    <option value="1 234.56">1 234.56 (Space Separator)</option>
                </select>
            </div>

            <div class="form-group">
                <label for="startOfWeek">Start of Week</label>
                <select id="startOfWeek" name="startOfWeek" class="form-control">
                    <option value="monday">Monday</option>
                    <option value="sunday" selected>Sunday</option>
                </select>
            </div>

            <div class="form-group full-width">
                <label>
                    <input type="checkbox" id="showCurrencySymbol" name="showCurrencySymbol" checked>
                    <span style="margin-left: 0.5rem;">Show currency symbol before amount</span>
                </label>
            </div>

            <div class="form-group full-width">
                <label>
                    <input type="checkbox" id="showThousandsSeparator" name="showThousandsSeparator" checked>
                    <span style="margin-left: 0.5rem;">Show thousands separator</span>
                </label>
            </div>

            <div class="form-group full-width">
                <label>
                    <input type="checkbox" id="autoSave" name="autoSave" checked>
                    <span style="margin-left: 0.5rem;">Auto-save transactions (frontend only)</span>
                </label>
            </div>

            <div class="form-group full-width">
                <label>
                    <input type="checkbox" id="showNotifications" name="showNotifications">
                    <span style="margin-left: 0.5rem;">Show budget warnings and notifications</span>
                </label>
            </div>

            <div class="form-group full-width">
                <label>
                    <input type="checkbox" id="compactView" name="compactView">
                    <span style="margin-left: 0.5rem;">Use compact view for tables</span>
                </label>
            </div>

            <div class="form-actions">
                <button type="submit" class="btn-primary btn-large">
                    Save Settings
                </button>
                <button type="button" id="resetSettings" class="btn-primary btn-large" style="margin-left: 1rem; background: #dc2626; border-color: #b91c1c;">
                    Reset to Defaults
                </button>
            </div>
        </form>
    </section>

    <section class="panel">
        <h2 class="panel-title">Preview</h2>
        <p class="panel-text">See how your settings will look:</p>
        <div class="settings-preview">
            <div class="preview-item">
                <strong>Currency Format:</strong>
                <span id="previewCurrency">$1,234.56</span>
            </div>
            <div class="preview-item">
                <strong>Date Format:</strong>
                <span id="previewDate">30/11/2025</span>
            </div>
            <div class="preview-item">
                <strong>Number Format:</strong>
                <span id="previewNumber">1,234.56</span>
            </div>
        </div>
    </section>

</main>

<script>
    (function() {
        var defaultSettings = {
            currency: 'USD',
            dateFormat: 'DD/MM/YYYY',
            numberFormat: '1,234.56',
            startOfWeek: 'sunday',
            showCurrencySymbol: true,
            showThousandsSeparator: true,
            autoSave: true,
            showNotifications: false,
            compactView: false
        };

        function broadcastSettingsChanged() {
            // lets other pages/tabs update immediately
            window.dispatchEvent(new Event('appSettingsChanged'));
            try {
                localStorage.setItem('__appSettingsPing', String(Date.now()));
            } catch (e) {}
        }

        function loadSettings() {
            var saved = localStorage.getItem('appSettings');
            var settings = saved ? JSON.parse(saved) : defaultSettings;

            document.getElementById('currency').value = settings.currency || defaultSettings.currency;
            document.getElementById('dateFormat').value = settings.dateFormat || defaultSettings.dateFormat;
            document.getElementById('numberFormat').value = settings.numberFormat || defaultSettings.numberFormat;
            document.getElementById('startOfWeek').value = settings.startOfWeek || defaultSettings.startOfWeek;
            document.getElementById('showCurrencySymbol').checked = settings.showCurrencySymbol !== false;
            document.getElementById('showThousandsSeparator').checked = settings.showThousandsSeparator !== false;
            document.getElementById('autoSave').checked = settings.autoSave !== false;
            document.getElementById('showNotifications').checked = settings.showNotifications === true;
            document.getElementById('compactView').checked = settings.compactView === true;

            updatePreview();
        }

        function saveSettings() {
            var settings = {
                currency: document.getElementById('currency').value,
                dateFormat: document.getElementById('dateFormat').value,
                numberFormat: document.getElementById('numberFormat').value,
                startOfWeek: document.getElementById('startOfWeek').value,
                showCurrencySymbol: document.getElementById('showCurrencySymbol').checked,
                showThousandsSeparator: document.getElementById('showThousandsSeparator').checked,
                autoSave: document.getElementById('autoSave').checked,
                showNotifications: document.getElementById('showNotifications').checked,
                compactView: document.getElementById('compactView').checked
            };

            localStorage.setItem('appSettings', JSON.stringify(settings));
            updatePreview();
            broadcastSettingsChanged();
            alert('Settings saved successfully! Changes will be applied across the application.');
        }

        function updatePreview() {
            var settings = JSON.parse(localStorage.getItem('appSettings') || JSON.stringify(defaultSettings));

            var currencySymbols = { 'USD': '$', 'EUR': '€', 'GBP': '£', 'ILS': '₪' };
            var symbol = currencySymbols[settings.currency] || '$';
            var amount = formatNumber(1234.56, settings);

            document.getElementById('previewCurrency').textContent =
                settings.showCurrencySymbol ? symbol + amount : amount + ' ' + settings.currency;

            var testDate = new Date(2025, 10, 30);
            document.getElementById('previewDate').textContent = formatDate(testDate, settings.dateFormat);

            document.getElementById('previewNumber').textContent = formatNumber(1234.56, settings);
        }

        function formatNumber(num, settings) {
            var decimals = 2;
            var formatted = num.toFixed(decimals);

            if (settings.showThousandsSeparator) {
                if (settings.numberFormat === '1,234.56') {
                    formatted = formatted.replace(/\B(?=(\d{3})+(?!\d))/g, ',');
                } else if (settings.numberFormat === '1.234,56') {
                    formatted = formatted.replace('.', ',');
                    formatted = formatted.replace(/\B(?=(\d{3})+(?!\d))/g, '.');
                } else if (settings.numberFormat === '1 234.56') {
                    formatted = formatted.replace(/\B(?=(\d{3})+(?!\d))/g, ' ');
                }
            }

            return formatted;
        }

        function formatDate(date, format) {
            var day = String(date.getDate()).padStart(2, '0');
            var month = String(date.getMonth() + 1).padStart(2, '0');
            var year = date.getFullYear();

            switch(format) {
                case 'DD/MM/YYYY': return day + '/' + month + '/' + year;
                case 'MM/DD/YYYY': return month + '/' + day + '/' + year;
                case 'YYYY-MM-DD': return year + '-' + month + '-' + day;
                case 'DD-MM-YYYY': return day + '-' + month + '-' + year;
                default: return day + '/' + month + '/' + year;
            }
        }

        var settingsForm = document.getElementById('settingsForm');
        if (settingsForm) {
            settingsForm.addEventListener('submit', function(e) {
                e.preventDefault();
                saveSettings();
            });
        }

        var resetBtn = document.getElementById('resetSettings');
        if (resetBtn) {
            resetBtn.addEventListener('click', function() {
                if (confirm('Are you sure you want to reset all settings to defaults?')) {
                    localStorage.removeItem('appSettings');
                    loadSettings();
                    broadcastSettingsChanged();
                    alert('Settings reset to defaults!');
                }
            });
        }

        var formInputs = document.querySelectorAll('#settingsForm input, #settingsForm select');
        formInputs.forEach(function(input) {
            input.addEventListener('change', updatePreview);
        });

        loadSettings();
    })();
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
