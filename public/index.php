<?php
require_once __DIR__ . '/../includes/web_boot.php';
require_once __DIR__ . '/../includes/auth.php';
require_login();

$userId = current_user_id();
$currentPage = 'dashboard';
$pageTitle   = 'Dashboard';
require_once __DIR__ . '/../includes/header.php';

$conn = db_conn();

// Fetch recent transactions for the table (show more than 3 so UI is useful)
$sqlRecent = "
    SELECT id, tx_date, category, tx_type, amount, COALESCE(note, '') AS note
    FROM transactions
    WHERE user_id = $1
    ORDER BY tx_date DESC, id DESC
    LIMIT 5
";
$resRecent = pg_query_params($conn, $sqlRecent, [$userId]);
if (!$resRecent) {
    die("Query failed: " . pg_last_error($conn));
}
$recentTransactions = [];
while ($row = pg_fetch_assoc($resRecent)) {
    $recentTransactions[] = [
            'id' => (int)$row['id'],
            'date' => $row['tx_date'],
            'category' => $row['category'],
            'type' => $row['tx_type'],
            'amount' => (float)$row['amount'],
            'note' => $row['note']
    ];
}

// Totals (always computed from DB so it's up-to-date)
$sqlTotals = "
    SELECT
        COALESCE(SUM(CASE WHEN tx_type = 'Income' THEN amount ELSE 0 END), 0) AS total_income,
        COALESCE(SUM(CASE WHEN tx_type = 'Expense' THEN amount ELSE 0 END), 0) AS total_expense
    FROM transactions
    WHERE user_id = $1
";
$resTotals = pg_query_params($conn, $sqlTotals, [$userId]);
if (!$resTotals) {
    die("Query failed: " . pg_last_error($conn));
}
$totalsRow = pg_fetch_assoc($resTotals);
$totalIncome = (float)$totalsRow['total_income'];
$totalExpense = (float)$totalsRow['total_expense'];

$startingBalance = 500.00;
$balance = $startingBalance + $totalIncome - $totalExpense;

// Build spending by category from ALL expense transactions (keeps chart accurate)
$sqlByCategory = "
    SELECT COALESCE(category, 'Uncategorized') AS category, COALESCE(SUM(amount),0) AS total
    FROM transactions
    WHERE user_id = $1 AND tx_type = 'Expense'
    GROUP BY category
    ORDER BY total DESC
    LIMIT 10
";
$resByCat = pg_query_params($conn, $sqlByCategory, [$userId]);
$categoryNames = [];
$categoryAmounts = [];
if ($resByCat) {
    while ($r = pg_fetch_assoc($resByCat)) {
        $categoryNames[] = $r['category'];
        $categoryAmounts[] = (float)$r['total'];
    }
}

// Fallback: ensure arrays are defined
$categoryNames = $categoryNames ?? [];
$categoryAmounts = $categoryAmounts ?? [];
?>
    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

    <main class="main-content">

        <section class="cards-row">
            <div class="card">
                <h2 class="card-title">Total Balance</h2>
                <p class="card-value"><span data-money="<?php echo htmlspecialchars(number_format($balance, 2, '.', '')); ?>"></span></p>
            </div>

            <div class="card card-income">
                <h2 class="card-title">Total Income</h2>
                <p class="card-value"><span data-money="<?php echo htmlspecialchars(number_format($totalIncome, 2, '.', '')); ?>"></span></p>
            </div>

            <div class="card card-expense">
                <h2 class="card-title">Total Expenses</h2>
                <p class="card-value"><span data-money="<?php echo htmlspecialchars(number_format($totalExpense, 2, '.', '')); ?>"></span></p>
            </div>
        </section>

        <section class="panel">
            <h2 class="panel-title">Spending by Category</h2>
            <div class="chart-container" style="position: relative; height:400px; width:100%;">
                <canvas id="categoryChart" aria-label="Spending by category chart" role="img"></canvas>
            </div>
        </section>

        <section class="panel">
            <h2 class="panel-title">Recent Transactions</h2>
            <div class="table-wrapper">
                <table class="table" id="recentTransactionsTable">
                    <thead>
                    <tr>
                        <th>Date</th>
                        <th>Type</th>
                        <th>Category</th>
                        <th class="align-right">Amount</th>
                        <th>Note</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($recentTransactions as $tx): ?>
                        <tr data-type="<?php echo htmlspecialchars(strtolower($tx['type'])); ?>">
                            <td><span data-date="<?php echo htmlspecialchars(substr($tx['date'], 0, 10)); ?>"></span></td>
                            <td>
                            <span class="type-badge type-<?php echo htmlspecialchars(strtolower($tx['type'])); ?>">
                                <?php echo htmlspecialchars($tx['type']); ?>
                            </span>
                            </td>
                            <td><span class="category-tag"><?php echo htmlspecialchars($tx['category']); ?></span></td>
                            <td class="align-right amount-cell">
                                <span data-money-signed="<?php echo htmlspecialchars(number_format($tx['amount'], 2, '.', '')); ?>" data-type="<?php echo htmlspecialchars($tx['type']); ?>"></span>
                            </td>
                            <td class="note-cell"><?php echo htmlspecialchars($tx['note']); ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </section>

    </main>

    <script>
        /* Formatting helper (exposed as FMAppSettings.applyFormatting) */
        (function() {
            var defaults = {
                currency: 'USD',
                dateFormat: 'DD/MM/YYYY',
                numberFormat: '1,234.56',
                showCurrencySymbol: true,
                showThousandsSeparator: true
            };

            function getSettings() {
                try {
                    var s = localStorage.getItem('appSettings');
                    if (!s) return defaults;
                    var obj = JSON.parse(s);
                    return Object.assign({}, defaults, obj || {});
                } catch (e) {
                    return defaults;
                }
            }

            function currencySymbol(code) {
                var map = { USD: '$', EUR: '€', GBP: '£', ILS: '₪' };
                return map[code] || '$';
            }

            function formatNumber(num, settings) {
                var n = Number(num || 0);
                var formatted = n.toFixed(2);

                if (!settings.showThousandsSeparator) {
                    if (settings.numberFormat === '1.234,56') {
                        formatted = formatted.replace('.', ',');
                    }
                    return formatted;
                }

                if (settings.numberFormat === '1,234.56') {
                    formatted = formatted.replace(/\B(?=(\d{3})+(?!\d))/g, ',');
                    return formatted;
                }

                if (settings.numberFormat === '1.234,56') {
                    formatted = formatted.replace('.', ',');
                    formatted = formatted.replace(/\B(?=(\d{3})+(?!\d))/g, '.');
                    return formatted;
                }

                if (settings.numberFormat === '1 234.56') {
                    formatted = formatted.replace(/\B(?=(\d{3})+(?!\d))/g, ' ');
                    return formatted;
                }

                return formatted;
            }

            function formatMoney(num, settings) {
                var sym = currencySymbol(settings.currency);
                var amount = formatNumber(num, settings);
                if (settings.showCurrencySymbol) return sym + ' ' + amount;
                return amount + ' ' + settings.currency;
            }

            function formatDate(ymd, settings) {
                var s = String(ymd || '');
                if (!/^\d{4}-\d{2}-\d{2}$/.test(s)) return s;
                var y = s.slice(0, 4);
                var m = s.slice(5, 7);
                var d = s.slice(8, 10);

                if (settings.dateFormat === 'DD/MM/YYYY') return d + '/' + m + '/' + y;
                if (settings.dateFormat === 'MM/DD/YYYY') return m + '/' + d + '/' + y;
                if (settings.dateFormat === 'YYYY-MM-DD') return y + '-' + m + '-' + d;
                if (settings.dateFormat === 'DD-MM-YYYY') return d + '-' + m + '-' + y;
                return d + '/' + m + '/' + y;
            }

            function applyFormatting() {
                var settings = getSettings();

                document.querySelectorAll('[data-money]').forEach(function(el) {
                    var v = el.getAttribute('data-money');
                    el.textContent = formatMoney(v, settings);
                });

                document.querySelectorAll('[data-money-signed]').forEach(function(el) {
                    var v = Number(el.getAttribute('data-money-signed') || 0);
                    var t = String(el.getAttribute('data-type') || '');
                    var sign = (t === 'Income') ? '+' : '-';
                    el.textContent = sign + ' ' + formatMoney(v, settings);
                });

                document.querySelectorAll('[data-date]').forEach(function(el) {
                    el.textContent = formatDate(el.getAttribute('data-date'), settings);
                });
            }

            window.FMAppSettings = window.FMAppSettings || {};
            window.FMAppSettings.applyFormatting = applyFormatting;

            applyFormatting();
            window.addEventListener('storage', function(e) {
                if (e && e.key === 'appSettings') applyFormatting();
            });
        })();

        /* Category chart - uses aggregated expense totals so it updates when transactions change */
        (function() {
            function getTextColor() {
                return document.body.classList.contains('dark-mode') ? '#e5e7eb' : '#374151';
            }

            var categories = <?php echo json_encode($categoryNames); ?>;
            var amounts = <?php echo json_encode($categoryAmounts); ?>;
            var categoryChartInstance = null;

            function drawCategoryChart() {
                var ctxEl = document.getElementById('categoryChart');
                if (!ctxEl) return;

                if (categoryChartInstance) categoryChartInstance.destroy();

                if (!categories || categories.length === 0 || amounts.reduce((a,b)=>a+Number(b||0),0) <= 0) {
                    var c = ctxEl.getContext('2d');
                    // clear and show friendly message
                    c.clearRect(0, 0, ctxEl.width, ctxEl.height);
                    c.font = "16px Arial";
                    c.fillStyle = getTextColor();
                    c.textAlign = "center";
                    c.fillText("No spending data available", ctxEl.width / 2, ctxEl.height / 2);
                    return;
                }

                categoryChartInstance = new Chart(ctxEl, {
                    type: 'pie',
                    data: {
                        labels: categories,
                        datasets: [{
                            data: amounts,
                            backgroundColor: [
                                '#3b82f6', '#10b981', '#f59e0b', '#ef4444', '#8b5cf6', '#06b6d4', '#ec4899', '#84cc16'
                            ],
                            borderWidth: 0
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: {
                                position: 'right',
                                labels: {
                                    color: getTextColor(),
                                    font: { size: 12 },
                                    padding: 12
                                }
                            },
                            tooltip: {
                                callbacks: {
                                    label: function(context) {
                                        var label = context.label || '';
                                        if (label) label += ': ';
                                        label += '$' + (context.parsed).toFixed(2);
                                        return label;
                                    }
                                }
                            }
                        }
                    }
                });
            }

            document.addEventListener('DOMContentLoaded', function() {
                drawCategoryChart();
            });

            var themeToggle = document.getElementById('themeToggle');
            if (themeToggle) {
                themeToggle.addEventListener('click', function() {
                    setTimeout(function() {
                        drawCategoryChart();
                        if (window.FMAppSettings && typeof window.FMAppSettings.applyFormatting === 'function') {
                            window.FMAppSettings.applyFormatting();
                        }
                    }, 60);
                });
            }

            window.addEventListener('resize', function() {
                if (categoryChartInstance) {
                    setTimeout(drawCategoryChart, 80);
                }
            });
        })();
    </script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>