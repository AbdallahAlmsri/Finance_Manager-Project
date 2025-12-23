<?php

require_once __DIR__ . '/../includes/web_boot.php';
require_once __DIR__ . '/../includes/auth.php';
require_login();

$conn = db_conn();
$userId = current_user_id();

$currentPage = 'calendar';
$pageTitle   = 'Calendar';
require_once __DIR__ . '/../includes/header.php';

function safe_ymd($s) {
    $s = trim((string)$s);
    if ($s === '') return null;
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $s)) return null;
    return $s;
}

function safe_ym($s) {
    $s = trim((string)$s);
    if ($s === '') return null;
    if (!preg_match('/^\d{4}-\d{2}$/', $s)) return null;
    return $s;
}

$monthParam = safe_ym($_GET['m'] ?? '');
if (!$monthParam) $monthParam = date('Y-m');

$selectedDate = safe_ymd($_GET['d'] ?? '');
if (!$selectedDate) $selectedDate = date('Y-m-d');

$monthStart = $monthParam . '-01';
$monthStartTs = strtotime($monthStart);
if (!$monthStartTs) {
    $monthParam = date('Y-m');
    $monthStart = $monthParam . '-01';
    $monthStartTs = strtotime($monthStart);
}

$daysInMonth = (int)date('t', $monthStartTs);
$monthEnd = date('Y-m-d', strtotime($monthStart . ' +' . ($daysInMonth - 1) . ' day'));

$firstDow = (int)date('N', $monthStartTs);
$gridStartTs = strtotime($monthStart . ' -' . ($firstDow - 1) . ' day');
$gridEndTs = strtotime($monthEnd . ' +' . (7 - (int)date('N', strtotime($monthEnd))) . ' day');

$monthLabel = date('F Y', $monthStartTs);
$prevMonth = date('Y-m', strtotime($monthStart . ' -1 month'));
$nextMonth = date('Y-m', strtotime($monthStart . ' +1 month'));

$txSummaryByDate = [];
$payByDate = [];
$goalsByDate = [];

$sqlTxSummary = "
    SELECT
        tx_date::date AS d,
        COUNT(*) AS cnt,
        COALESCE(SUM(CASE WHEN tx_type = 'Income' THEN amount ELSE 0 END), 0) AS income,
        COALESCE(SUM(CASE WHEN tx_type = 'Expense' THEN amount ELSE 0 END), 0) AS expense
    FROM transactions
    WHERE user_id = $1
      AND tx_date >= $2::date
      AND tx_date <= $3::date
    GROUP BY tx_date::date
    ORDER BY d ASC
";
$resTxSummary = pg_query_params($conn, $sqlTxSummary, [$userId, $monthStart, $monthEnd]);
if ($resTxSummary) {
    while ($r = pg_fetch_assoc($resTxSummary)) {
        $d = (string)$r['d'];
        $txSummaryByDate[$d] = [
                'cnt' => (int)$r['cnt'],
                'income' => (float)$r['income'],
                'expense' => (float)$r['expense'],
        ];
    }
}

$sqlMonthTotals = "
    SELECT
        COALESCE(SUM(CASE WHEN tx_type = 'Income' THEN amount ELSE 0 END), 0) AS income,
        COALESCE(SUM(CASE WHEN tx_type = 'Expense' THEN amount ELSE 0 END), 0) AS expense,
        COUNT(*) AS cnt
    FROM transactions
    WHERE user_id = $1
      AND tx_date >= $2::date
      AND tx_date <= $3::date
";
$resMonthTotals = pg_query_params($conn, $sqlMonthTotals, [$userId, $monthStart, $monthEnd]);
$monthIncome = 0.0;
$monthExpense = 0.0;
$monthTxCount = 0;
if ($resMonthTotals) {
    $monthIncome = (float)pg_fetch_result($resMonthTotals, 0, 0);
    $monthExpense = (float)pg_fetch_result($resMonthTotals, 0, 1);
    $monthTxCount = (int)pg_fetch_result($resMonthTotals, 0, 2);
}

$sqlPays = "
    SELECT payment_name, payment_type, total_amount, paid_amount, monthly_payment, due_date
    FROM payments
    WHERE user_id = $1
      AND due_date IS NOT NULL
      AND due_date >= $2::date
      AND due_date <= $3::date
    ORDER BY due_date ASC, payment_name ASC
";
$resPays = pg_query_params($conn, $sqlPays, [$userId, $monthStart, $monthEnd]);
if ($resPays) {
    while ($r = pg_fetch_assoc($resPays)) {
        $d = (string)$r['due_date'];
        if (!isset($payByDate[$d])) $payByDate[$d] = [];
        $payByDate[$d][] = [
                'name' => (string)$r['payment_name'],
                'type' => (string)$r['payment_type'],
                'total' => (float)$r['total_amount'],
                'paid' => (float)$r['paid_amount'],
                'monthly' => (float)$r['monthly_payment'],
                'due' => (string)$r['due_date'],
        ];
    }
}

$sqlGoals = "
    SELECT goal_name, target_amount, saved_amount, deadline
    FROM goals
    WHERE user_id = $1
      AND deadline IS NOT NULL
      AND deadline >= $2::date
      AND deadline <= $3::date
    ORDER BY deadline ASC, goal_name ASC
";
$resGoals = pg_query_params($conn, $sqlGoals, [$userId, $monthStart, $monthEnd]);
if ($resGoals) {
    while ($r = pg_fetch_assoc($resGoals)) {
        $d = (string)$r['deadline'];
        if (!isset($goalsByDate[$d])) $goalsByDate[$d] = [];
        $goalsByDate[$d][] = [
                'name' => (string)$r['goal_name'],
                'target' => (float)$r['target_amount'],
                'saved' => (float)$r['saved_amount'],
                'deadline' => (string)$r['deadline'],
        ];
    }
}

$sqlTxDay = "
    SELECT tx_date, tx_type, category, amount, COALESCE(note, '') AS note
    FROM transactions
    WHERE user_id = $1
      AND tx_date::date = $2::date
    ORDER BY tx_date DESC, id DESC
";
$resTxDay = pg_query_params($conn, $sqlTxDay, [$userId, $selectedDate]);
$dayTxs = [];
if ($resTxDay) {
    while ($r = pg_fetch_assoc($resTxDay)) {
        $dayTxs[] = [
                'date' => (string)$r['tx_date'],
                'type' => (string)$r['tx_type'],
                'category' => (string)$r['category'],
                'amount' => (float)$r['amount'],
                'note' => (string)$r['note'],
        ];
    }
}

$dayPays = $payByDate[$selectedDate] ?? [];
$dayGoals = $goalsByDate[$selectedDate] ?? [];

$dayIncome = 0.0;
$dayExpense = 0.0;
foreach ($dayTxs as $t) {
    if ($t['type'] === 'Income') $dayIncome += $t['amount'];
    if ($t['type'] === 'Expense') $dayExpense += $t['amount'];
}

?>

<main class="main-content">

    <section class="panel">
        <div class="panel-header" style="display:flex; justify-content:space-between; align-items:center; gap:1rem; flex-wrap:wrap;">
            <div>
                <h2 class="panel-title" style="margin-bottom:0.25rem;">Calendar</h2>
                <div class="panel-text" style="opacity:0.85;">Transactions, payment due dates, and goal deadlines</div>
            </div>

            <div style="display:flex; gap:0.5rem; align-items:center; flex-wrap:wrap;">
                <a class="btn-ghost btn-small" href="calendar.php?m=<?php echo htmlspecialchars($prevMonth); ?>&d=<?php echo htmlspecialchars($selectedDate); ?>">←</a>
                <div class="form-control" style="width:auto; display:inline-block; padding:0.55rem 0.9rem;"><?php echo htmlspecialchars($monthLabel); ?></div>
                <a class="btn-ghost btn-small" href="calendar.php?m=<?php echo htmlspecialchars($nextMonth); ?>&d=<?php echo htmlspecialchars($selectedDate); ?>">→</a>
                <a class="btn-primary btn-small" href="calendar.php">Today</a>
            </div>
        </div>

        <div style="display:grid; grid-template-columns: repeat(7, minmax(0, 1fr)); gap:8px; margin-top:14px;">
            <?php
            $dow = ['Mon','Tue','Wed','Thu','Fri','Sat','Sun'];
            foreach ($dow as $x) {
                echo '<div style="opacity:0.85; font-weight:600; padding:6px 10px;">' . htmlspecialchars($x) . '</div>';
            }

            for ($ts = $gridStartTs; $ts <= $gridEndTs; $ts = strtotime('+1 day', $ts)) {
                $d = date('Y-m-d', $ts);
                $inMonth = (date('Y-m', $ts) === $monthParam);
                $isSelected = ($d === $selectedDate);
                $isToday = ($d === date('Y-m-d'));

                $txSum = $txSummaryByDate[$d] ?? ['cnt' => 0, 'income' => 0.0, 'expense' => 0.0];
                $hasTx = ($txSum['cnt'] > 0);
                $hasPay = !empty($payByDate[$d] ?? []);
                $hasGoal = !empty($goalsByDate[$d] ?? []);

                $baseBg = $inMonth ? 'rgba(255,255,255,0.03)' : 'rgba(255,255,255,0.015)';
                $border = $isSelected ? 'rgba(59,130,246,0.9)' : 'rgba(255,255,255,0.08)';
                $ring = $isToday ? 'box-shadow: 0 0 0 1px rgba(16,185,129,0.55) inset;' : '';
                $opacity = $inMonth ? '1' : '0.5';

                $badges = '';
                if ($hasTx) $badges .= '<span style="display:inline-block; padding:2px 8px; border-radius:999px; font-size:12px; background:rgba(59,130,246,0.15); border:1px solid rgba(59,130,246,0.25);">Tx ' . (int)$txSum['cnt'] . '</span>';
                if ($hasPay) $badges .= '<span style="display:inline-block; padding:2px 8px; border-radius:999px; font-size:12px; margin-left:6px; background:rgba(245,158,11,0.15); border:1px solid rgba(245,158,11,0.25);">Pay</span>';
                if ($hasGoal) $badges .= '<span style="display:inline-block; padding:2px 8px; border-radius:999px; font-size:12px; margin-left:6px; background:rgba(16,185,129,0.15); border:1px solid rgba(16,185,129,0.25);">Goal</span>';

                $link = 'calendar.php?m=' . urlencode($monthParam) . '&d=' . urlencode($d);

                echo '<a href="' . htmlspecialchars($link) . '" style="text-decoration:none; color:inherit;">';
                echo '<div style="min-height:92px; border-radius:12px; border:1px solid ' . $border . '; background:' . $baseBg . '; padding:10px; ' . $ring . ' opacity:' . $opacity . ';">';
                echo '<div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:8px;">';
                echo '<div style="font-weight:700;">' . (int)date('j', $ts) . '</div>';
                echo $isSelected ? '<div style="font-size:12px; opacity:0.8;">Selected</div>' : ($isToday ? '<div style="font-size:12px; opacity:0.8;">Today</div>' : '<div></div>');
                echo '</div>';
                echo '<div style="display:flex; flex-wrap:wrap; gap:6px;">' . $badges . '</div>';
                echo '</div>';
                echo '</a>';
            }
            ?>
        </div>
    </section>

    <section class="panel">
        <h2 class="panel-title">Details for <span data-date="<?php echo htmlspecialchars($selectedDate); ?>"></span></h2>

        <div style="display:flex; gap:1.25rem; flex-wrap:wrap; margin-bottom:1rem;">
            <div class="card card-income" style="min-width:220px;">
                <h2 class="card-title">Income (Day)</h2>
                <p class="card-value"><span data-money="<?php echo htmlspecialchars(number_format($dayIncome, 2, '.', '')); ?>"></span></p>
            </div>
            <div class="card card-expense" style="min-width:220px;">
                <h2 class="card-title">Expenses (Day)</h2>
                <p class="card-value"><span data-money="<?php echo htmlspecialchars(number_format($dayExpense, 2, '.', '')); ?>"></span></p>
            </div>
            <div class="card" style="min-width:220px;">
                <h2 class="card-title">Transactions (Day)</h2>
                <p class="card-value"><?php echo count($dayTxs); ?></p>
            </div>
        </div>

        <div style="display:flex; gap:1.25rem; flex-wrap:wrap; margin-bottom:1.25rem;">
            <div class="card card-income" style="min-width:220px;">
                <h2 class="card-title">Income (Month)</h2>
                <p class="card-value"><span data-money="<?php echo htmlspecialchars(number_format($monthIncome, 2, '.', '')); ?>"></span></p>
            </div>
            <div class="card card-expense" style="min-width:220px;">
                <h2 class="card-title">Expenses (Month)</h2>
                <p class="card-value"><span data-money="<?php echo htmlspecialchars(number_format($monthExpense, 2, '.', '')); ?>"></span></p>
            </div>
            <div class="card" style="min-width:220px;">
                <h2 class="card-title">Transactions (Month)</h2>
                <p class="card-value"><?php echo $monthTxCount; ?></p>
            </div>
        </div>

        <?php if (!empty($dayPays)): ?>
            <div style="margin-bottom:1.25rem;">
                <h3 style="margin:0 0 0.6rem; font-size:1.05rem;">Due Payments</h3>
                <div class="table-wrapper">
                    <table class="table">
                        <thead>
                        <tr>
                            <th>Payment</th>
                            <th>Type</th>
                            <th class="align-right">Monthly</th>
                            <th class="align-right">Paid / Total</th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($dayPays as $p): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($p['name']); ?></td>
                                <td><?php echo htmlspecialchars($p['type']); ?></td>
                                <td class="align-right"><span data-money="<?php echo htmlspecialchars(number_format($p['monthly'], 2, '.', '')); ?>"></span></td>
                                <td class="align-right">
                                    <span data-money="<?php echo htmlspecialchars(number_format($p['paid'], 2, '.', '')); ?>"></span>
                                    /
                                    <span data-money="<?php echo htmlspecialchars(number_format($p['total'], 2, '.', '')); ?>"></span>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        <?php endif; ?>

        <?php if (!empty($dayGoals)): ?>
            <div style="margin-bottom:1.25rem;">
                <h3 style="margin:0 0 0.6rem; font-size:1.05rem;">Goal Deadlines</h3>
                <div class="table-wrapper">
                    <table class="table">
                        <thead>
                        <tr>
                            <th>Goal</th>
                            <th class="align-right">Saved / Target</th>
                            <th class="align-right">Progress</th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($dayGoals as $g): ?>
                            <?php $pct = $g['target'] > 0 ? round(($g['saved'] / $g['target']) * 100) : 0; ?>
                            <tr>
                                <td><?php echo htmlspecialchars($g['name']); ?></td>
                                <td class="align-right">
                                    <span data-money="<?php echo htmlspecialchars(number_format($g['saved'], 2, '.', '')); ?>"></span>
                                    /
                                    <span data-money="<?php echo htmlspecialchars(number_format($g['target'], 2, '.', '')); ?>"></span>
                                </td>
                                <td class="align-right"><?php echo (int)min($pct, 100); ?>%</td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        <?php endif; ?>

        <h3 style="margin:0 0 0.6rem; font-size:1.05rem;">Transactions</h3>

        <?php if (count($dayTxs) > 0): ?>
            <div class="table-wrapper">
                <table class="table">
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
                    <?php foreach ($dayTxs as $tx): ?>
                        <tr>
                            <td><span data-date="<?php echo htmlspecialchars(substr($tx['date'], 0, 10)); ?>"></span></td>
                            <td><?php echo htmlspecialchars($tx['type']); ?></td>
                            <td><?php echo htmlspecialchars($tx['category']); ?></td>
                            <td class="align-right">
                                <span data-money-signed="<?php echo htmlspecialchars(number_format($tx['amount'], 2, '.', '')); ?>" data-type="<?php echo htmlspecialchars($tx['type']); ?>"></span>
                            </td>
                            <td><?php echo htmlspecialchars($tx['note']); ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <div style="opacity:0.85; padding:10px 0;">No transactions on this day.</div>
        <?php endif; ?>

    </section>

</main>

<script>
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

        function apply() {
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

        apply();

        window.addEventListener('storage', function(e) {
            if (e && e.key === 'appSettings') apply();
        });
    })();
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
