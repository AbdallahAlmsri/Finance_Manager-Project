<?php

require_once __DIR__ . '/../includes/web_boot.php';
require_once __DIR__ . '/../includes/auth.php';
require_login();

$userId = current_user_id();
$currentPage = 'reports';
$pageTitle   = 'Reports';

$conn = db_conn();

function clamp_date($s) {
    $s = trim((string)$s);
    if ($s === '') return null;
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $s)) return null;
    return $s;
}

function previous_period($startDate, $endDate) {
    $start = strtotime($startDate);
    $end   = strtotime($endDate);
    if (!$start || !$end) return [$startDate, $endDate];

    $days = (int)floor(($end - $start) / 86400) + 1;
    if ($days < 1) $days = 1;

    $pEnd   = date('Y-m-d', strtotime($startDate . ' -1 day'));
    $pStart = date('Y-m-d', strtotime($pEnd . ' -' . ($days - 1) . ' day'));

    return [$pStart, $pEnd];
}

function load_report_data($conn, $userId, $startDate, $endDate) {
    $sqlCat = "
        SELECT category, COALESCE(SUM(amount), 0) AS total
        FROM transactions
        WHERE user_id = $1
          AND tx_type = 'Expense'
          AND tx_date >= $2::date
          AND tx_date <= $3::date
        GROUP BY category
        ORDER BY total DESC, category ASC
    ";
    $resCat = pg_query_params($conn, $sqlCat, [$userId, $startDate, $endDate]);
    if (!$resCat) {
        die("Failed to load categories: " . pg_last_error($conn));
    }

    $categories = [];
    while ($r = pg_fetch_assoc($resCat)) {
        $categories[] = [
                'category' => (string)$r['category'],
                'amount'   => (float)$r['total']
        ];
    }

    $sqlIncome = "
        SELECT COALESCE(SUM(amount), 0) AS total
        FROM transactions
        WHERE user_id = $1
          AND tx_type = 'Income'
          AND tx_date >= $2::date
          AND tx_date <= $3::date
    ";
    $resIncome = pg_query_params($conn, $sqlIncome, [$userId, $startDate, $endDate]);
    if (!$resIncome) {
        die("Failed to load income total: " . pg_last_error($conn));
    }
    $incomeTotal = (float)pg_fetch_result($resIncome, 0, 0);

    $sqlExpense = "
        SELECT COALESCE(SUM(amount), 0) AS total
        FROM transactions
        WHERE user_id = $1
          AND tx_type = 'Expense'
          AND tx_date >= $2::date
          AND tx_date <= $3::date
    ";
    $resExpense = pg_query_params($conn, $sqlExpense, [$userId, $startDate, $endDate]);
    if (!$resExpense) {
        die("Failed to load expense total: " . pg_last_error($conn));
    }
    $expenseTotal = (float)pg_fetch_result($resExpense, 0, 0);

    $sqlTx = "
        SELECT
            tx_date,
            tx_type,
            category,
            amount,
            COALESCE(note, '') AS note
        FROM transactions
        WHERE user_id = $1
          AND tx_date >= $2::date
          AND tx_date <= $3::date
        ORDER BY tx_date DESC, id DESC
    ";
    $resTx = pg_query_params($conn, $sqlTx, [$userId, $startDate, $endDate]);
    if (!$resTx) {
        die("Failed to load transactions: " . pg_last_error($conn));
    }

    $txs = [];
    while ($r = pg_fetch_assoc($resTx)) {
        $txs[] = [
                'date'     => (string)$r['tx_date'],
                'type'     => (string)$r['tx_type'],
                'category' => (string)$r['category'],
                'amount'   => (float)$r['amount'],
                'note'     => (string)$r['note'],
        ];
    }

    return [
            'categories' => $categories,
            'income'     => $incomeTotal,
            'expense'    => $expenseTotal,
            'txs'        => $txs,
    ];
}

$action = $_GET['action'] ?? '';

$startDate = clamp_date($_GET['start'] ?? '');
$endDate   = clamp_date($_GET['end'] ?? '');

if (!$startDate || !$endDate) {
    $endDate = date('Y-m-d');
    $startDate = date('Y-m-d', strtotime('-30 days'));
}

if ($action === 'data') {
    $compare = ($_GET['compare'] ?? '0') === '1';

    $current = load_report_data($conn, $userId, $startDate, $endDate);

    $payload = [
            'start'    => $startDate,
            'end'      => $endDate,
            'current'  => $current,
            'previous' => null
    ];

    if ($compare) {
        $prev   = previous_period($startDate, $endDate);
        $pStart = $prev[0];
        $pEnd   = $prev[1];

        $payload['previous'] = [
                'start' => $pStart,
                'end'   => $pEnd,
                'data'  => load_report_data($conn, $userId, $pStart, $pEnd)
        ];
    }

    header('Content-Type: application/json; charset=UTF-8');
    echo json_encode($payload);
    exit;
}

if ($action === 'export') {
    $data = load_report_data($conn, $userId, $startDate, $endDate);

    $filename = 'financial_report_' . $endDate . '.csv';
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');

    $out = fopen('php://output', 'w');

    fprintf($out, chr(0xEF) . chr(0xBB) . chr(0xBF));

    fputcsv($out, ['Expenses by Category', '(' . $startDate . ' to ' . $endDate . ')']);
    fputcsv($out, ['Category', 'Amount']);
    foreach ($data['categories'] as $row) {
        fputcsv($out, [$row['category'], number_format($row['amount'], 2, '.', '')]);
    }

    fputcsv($out, []);
    fputcsv($out, ['Total Income', number_format($data['income'], 2, '.', '')]);
    fputcsv($out, ['Total Expenses', number_format($data['expense'], 2, '.', '')]);

    fputcsv($out, []);
    fputcsv($out, ['Transactions', '(' . $startDate . ' to ' . $endDate . ')']);
    fputcsv($out, ['Date', 'Type', 'Category', 'Amount', 'Note']);

    foreach ($data['txs'] as $t) {
        fputcsv($out, [
                $t['date'],
                $t['type'],
                $t['category'],
                number_format($t['amount'], 2, '.', ''),
                $t['note']
        ]);
    }

    fclose($out);
    exit;
}

require_once __DIR__ . '/../includes/header.php';
?>

<main class="main-content">

    <section class="panel">
        <div class="panel-header">
            <h2 class="panel-title">Financial Reports</h2>
            <div class="report-controls">
                <div class="date-range-picker">
                    <input type="date" id="reportStartDate" class="form-control" style="width: auto; display: inline-block;">
                    <span style="margin: 0 0.5rem;">to</span>
                    <input type="date" id="reportEndDate" class="form-control" style="width: auto; display: inline-block;">
                    <button type="button" id="applyDateRange" class="btn-primary btn-small" style="margin-left: 0.5rem;">Apply</button>
                </div>
                <div class="report-actions">
                    <label class="comparison-toggle">
                        <input type="checkbox" id="compareToggle">
                        <span>Compare with Previous Period</span>
                    </label>
                    <button type="button" id="exportReport" class="btn-primary btn-small">Export</button>
                </div>
            </div>
        </div>

        <div class="report-content">
            <div class="report-chart" id="categoryChart">
                <h3 class="chart-heading">Monthly Expenses by Category <span class="chart-period"></span></h3>
                <div id="categoryChartContent"></div>
            </div>

            <div class="report-chart" id="incomeExpenseChart">
                <h3 class="chart-heading">Income vs Expenses <span class="chart-period"></span></h3>
                <div id="incomeExpenseChartContent"></div>
            </div>
        </div>

        <div id="categoryDrillDown" class="category-drilldown" style="display: none;">
            <div class="drilldown-header">
                <h3 class="drilldown-title">Category Details: <span id="drilldownCategoryName"></span></h3>
                <button type="button" class="btn-ghost btn-small" id="closeDrillDown">Close</button>
            </div>
            <div class="drilldown-content" id="drilldownContent"></div>
        </div>
    </section>

    <section class="panel">
        <h2 class="panel-title">Summary</h2>
        <ul class="summary-list" id="reportSummary"></ul>
    </section>

</main>

<script>
    (function() {
        var compareMode = false;

        var startDate = "<?php echo htmlspecialchars($startDate); ?>";
        var endDate   = "<?php echo htmlspecialchars($endDate); ?>";

        document.getElementById('reportStartDate').value = startDate;
        document.getElementById('reportEndDate').value = endDate;

        document.getElementById('applyDateRange').addEventListener('click', function() {
            startDate = document.getElementById('reportStartDate').value;
            endDate = document.getElementById('reportEndDate').value;
            loadAndRender();
        });

        document.getElementById('compareToggle').addEventListener('change', function() {
            compareMode = this.checked;
            loadAndRender();
        });

        document.getElementById('closeDrillDown').addEventListener('click', function() {
            document.getElementById('categoryDrillDown').style.display = 'none';
        });

        document.getElementById('exportReport').addEventListener('click', function() {
            var url = "reports.php?action=export&start=" + encodeURIComponent(startDate) +
                "&end=" + encodeURIComponent(endDate);
            window.location.href = url;
        });

        function getSettings() {
            try {
                var raw = localStorage.getItem('appSettings');
                if (!raw) return null;
                return JSON.parse(raw);
            } catch (e) {
                return null;
            }
        }

        function formatDateFromSettings(dateStr) {
            if (!dateStr) return '';
            var parts = String(dateStr).split('-');
            if (parts.length < 3) return dateStr;

            var year = parts[0];
            var month = String(parts[1]).padStart(2, '0');
            var day = String(parts[2]).padStart(2, '0');

            var s = getSettings();
            var fmt = (s && s.dateFormat) ? s.dateFormat : 'DD/MM/YYYY';

            switch(fmt) {
                case 'DD/MM/YYYY': return day + '/' + month + '/' + year;
                case 'MM/DD/YYYY': return month + '/' + day + '/' + year;
                case 'YYYY-MM-DD': return year + '-' + month + '-' + day;
                case 'DD-MM-YYYY': return day + '-' + month + '-' + year;
                default: return day + '/' + month + '/' + year;
            }
        }

        function formatRange(s, e) {
            return formatDateFromSettings(s) + ' - ' + formatDateFromSettings(e);
        }

        function loadAndRender() {
            var url = "reports.php?action=data&start=" + encodeURIComponent(startDate) +
                "&end=" + encodeURIComponent(endDate) +
                "&compare=" + (compareMode ? "1" : "0");

            fetch(url)
                .then(function(r) { return r.json(); })
                .then(function(payload) { render(payload); })
                .catch(function(err) { alert("Failed to load report data: " + err); });
        }

        function render(payload) {
            var periodText = formatRange(payload.start, payload.end);
            document.querySelectorAll('.chart-period').forEach(function(el) {
                el.textContent = '(' + periodText + ')';
            });

            var cats = payload.current.categories || [];
            var catMax = 0;
            cats.forEach(function(c) { if (Number(c.amount) > catMax) catMax = Number(c.amount); });
            if (catMax <= 0) catMax = 1;

            var catHtml = '';
            cats.forEach(function(c) {
                var width = (Number(c.amount) / catMax) * 100;
                catHtml +=
                    '<div class="bar-row clickable-category" data-category="' + escapeHtml(c.category) + '">' +
                    '<span class="bar-label">' + escapeHtml(c.category) + '</span>' +
                    '<div class="bar-track">' +
                    '<div class="bar-fill bar-fill-expense" style="width: ' + width.toFixed(2) + '%;"></div>' +
                    '</div>' +
                    '<span class="bar-value" data-money="' + escapeAttr(String(c.amount)) + '"></span>' +
                    '</div>';
            });

            if (!catHtml) {
                catHtml = '<div style="opacity:0.8; padding:10px;">No expenses in this period.</div>';
            }
            document.getElementById('categoryChartContent').innerHTML = catHtml;

            document.querySelectorAll('.clickable-category').forEach(function(row) {
                row.addEventListener('click', function() {
                    var category = this.getAttribute('data-category');
                    showCategoryDrillDown(category, payload.current.txs || []);
                });
            });

            var income = Number(payload.current.income || 0);
            var expense = Number(payload.current.expense || 0);

            var ieHtml = '';
            if (compareMode && payload.previous && payload.previous.data) {
                var pIncome = Number(payload.previous.data.income || 0);
                var pExpense = Number(payload.previous.data.expense || 0);
                var maxAmt = Math.max(income, expense, pIncome, pExpense);
                if (maxAmt <= 0) maxAmt = 1;

                ieHtml =
                    '<div class="bar-row">' +
                    '<span class="bar-label">Income</span>' +
                    '<div class="bar-track">' +
                    '<div class="bar-fill bar-fill-income current-period" style="width: ' + (income / maxAmt * 100).toFixed(2) + '%;"></div>' +
                    '<div class="bar-fill bar-fill-income previous-period" style="width: ' + (pIncome / maxAmt * 100).toFixed(2) + '%; opacity: 0.6;"></div>' +
                    '</div>' +
                    '<span class="bar-value"><span data-money="' + escapeAttr(String(income)) + '"></span> <span style="opacity:0.6;">(<span data-money="' + escapeAttr(String(pIncome)) + '"></span>)</span></span>' +
                    '</div>' +
                    '<div class="bar-row">' +
                    '<span class="bar-label">Expenses</span>' +
                    '<div class="bar-track">' +
                    '<div class="bar-fill bar-fill-expense current-period" style="width: ' + (expense / maxAmt * 100).toFixed(2) + '%;"></div>' +
                    '<div class="bar-fill bar-fill-expense previous-period" style="width: ' + (pExpense / maxAmt * 100).toFixed(2) + '%; opacity: 0.6;"></div>' +
                    '</div>' +
                    '<span class="bar-value"><span data-money="' + escapeAttr(String(expense)) + '"></span> <span style="opacity:0.6;">(<span data-money="' + escapeAttr(String(pExpense)) + '"></span>)</span></span>' +
                    '</div>';
            } else {
                var maxAmt2 = Math.max(income, expense);
                if (maxAmt2 <= 0) maxAmt2 = 1;

                ieHtml =
                    '<div class="bar-row">' +
                    '<span class="bar-label">Income</span>' +
                    '<div class="bar-track">' +
                    '<div class="bar-fill bar-fill-income" style="width: ' + (income / maxAmt2 * 100).toFixed(2) + '%;"></div>' +
                    '</div>' +
                    '<span class="bar-value" data-money="' + escapeAttr(String(income)) + '"></span>' +
                    '</div>' +
                    '<div class="bar-row">' +
                    '<span class="bar-label">Expenses</span>' +
                    '<div class="bar-track">' +
                    '<div class="bar-fill bar-fill-expense" style="width: ' + (expense / maxAmt2 * 100).toFixed(2) + '%;"></div>' +
                    '</div>' +
                    '<span class="bar-value" data-money="' + escapeAttr(String(expense)) + '"></span>' +
                    '</div>';
            }

            document.getElementById('incomeExpenseChartContent').innerHTML = ieHtml;

            var summary = document.getElementById('reportSummary');
            var topCat = (cats.length > 0) ? cats[0] : null;

            var summaryHtml = '';
            if (topCat) {
                summaryHtml += '<li>Highest spending category: <strong>' + escapeHtml(topCat.category) + '</strong> (<strong data-money="' + escapeAttr(String(topCat.amount)) + '"></strong>)</li>';
            } else {
                summaryHtml += '<li>Highest spending category: <strong>N/A</strong></li>';
            }
            summaryHtml += '<li>Current period total income: <strong data-money="' + escapeAttr(String(income)) + '"></strong></li>';
            summaryHtml += '<li>Current period total expenses: <strong data-money="' + escapeAttr(String(expense)) + '"></strong></li>';
            summary.innerHTML = summaryHtml;

            if (window.FMAppSettings && typeof window.FMAppSettings.applyFormatting === 'function') {
                window.FMAppSettings.applyFormatting();
            }
        }

        function showCategoryDrillDown(category, txs) {
            var filtered = txs.filter(function(t) {
                return String(t.type) === 'Expense' && String(t.category) === String(category);
            });

            var drilldown = document.getElementById('categoryDrillDown');
            var content = document.getElementById('drilldownContent');
            var name = document.getElementById('drilldownCategoryName');

            name.textContent = category;

            if (!filtered.length) {
                content.innerHTML = '<div style="opacity:0.8; padding:10px;">No transactions for this category.</div>';
                drilldown.style.display = 'block';
                return;
            }

            content.innerHTML =
                '<div class="drilldown-table"><table class="table">' +
                '<thead><tr><th>Date</th><th>Amount</th><th>Note</th></tr></thead><tbody>' +
                filtered.map(function(item) {
                    return '<tr>' +
                        '<td data-date="' + escapeAttr(String(item.date || '')) + '"></td>' +
                        '<td class="align-right" data-money="' + escapeAttr(String(item.amount || 0)) + '"></td>' +
                        '<td>' + escapeHtml(item.note || '') + '</td>' +
                        '</tr>';
                }).join('') +
                '</tbody></table></div>';

            drilldown.style.display = 'block';

            if (window.FMAppSettings && typeof window.FMAppSettings.applyFormatting === 'function') {
                window.FMAppSettings.applyFormatting();
            }
        }

        function escapeHtml(s) {
            return String(s == null ? '' : s)
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;')
                .replace(/'/g, '&#039;');
        }

        function escapeAttr(s) {
            return String(s == null ? '' : s)
                .replace(/&/g, '&amp;')
                .replace(/"/g, '&quot;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;');
        }

        loadAndRender();

        window.addEventListener('appSettingsChanged', function() {
            loadAndRender();
        });

        window.addEventListener('storage', function(e) {
            if (e.key === 'appSettings' || e.key === '__appSettingsPing') {
                loadAndRender();
            }
        });
    })();
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
