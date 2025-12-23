<?php

require_once __DIR__ . '/../includes/web_boot.php';
require_once __DIR__ . '/../includes/auth.php';
require_login();

$conn = db_conn();
$userId = current_user_id();

function fetch_one_assoc($res) {
    if (!$res) return null;
    $row = pg_fetch_assoc($res);
    return $row ?: null;
}

function tf($b) {
    return $b ? 't' : 'f';
}

function load_latest_notification_settings($conn, $userId) {
    $sql = "
        SELECT
            enable_budget_warnings,
            enable_bill_reminders,
            enable_goal_updates,
            enable_low_balance_alerts,
            budget_warning_threshold_pct,
            low_balance_threshold,
            bill_reminder_days_before,
            enable_balance_alerts,
            balance_threshold,
            reminder_days
        FROM notification_settings
        WHERE user_id = $1
        ORDER BY updated_at DESC NULLS LAST, id DESC
        LIMIT 1
    ";
    $res = pg_query_params($conn, $sql, [$userId]);
    $row = fetch_one_assoc($res);

    if ($row) {
        return [
                'enable_budget_warnings'        => ($row['enable_budget_warnings'] === 't'),
                'enable_bill_reminders'         => ($row['enable_bill_reminders'] === 't'),
                'enable_goal_updates'           => ($row['enable_goal_updates'] === 't'),
                'enable_low_balance_alerts'     => ($row['enable_low_balance_alerts'] === 't'),
                'budget_warning_threshold_pct'  => (int)($row['budget_warning_threshold_pct'] ?? 80),
                'low_balance_threshold'         => (float)($row['low_balance_threshold'] ?? 100),
                'bill_reminder_days_before'     => (int)($row['bill_reminder_days_before'] ?? 3),
                'enable_balance_alerts'         => ($row['enable_balance_alerts'] === 't'),
                'balance_threshold'             => (float)($row['balance_threshold'] ?? 100),
                'reminder_days'                 => (int)($row['reminder_days'] ?? 3),
        ];
    }

    return [
            'enable_budget_warnings'        => true,
            'enable_bill_reminders'         => true,
            'enable_goal_updates'           => true,
            'enable_low_balance_alerts'     => true,
            'budget_warning_threshold_pct'  => 80,
            'low_balance_threshold'         => 100.0,
            'bill_reminder_days_before'     => 3,
            'enable_balance_alerts'         => true,
            'balance_threshold'             => 100.0,
            'reminder_days'                 => 3,
    ];
}

function insert_notification_settings($conn, $userId, $s) {
    $sql = "
        INSERT INTO notification_settings (
            user_id,
            enable_budget_warnings,
            enable_bill_reminders,
            enable_goal_updates,
            enable_low_balance_alerts,
            budget_warning_threshold_pct,
            low_balance_threshold,
            bill_reminder_days_before,
            updated_at,
            enable_balance_alerts,
            balance_threshold,
            reminder_days
        )
        VALUES ($1,$2,$3,$4,$5,$6,$7,$8,NOW(),$9,$10,$11)
    ";
    $params = [
            $userId,
            tf($s['enable_budget_warnings']),
            tf($s['enable_bill_reminders']),
            tf($s['enable_goal_updates']),
            tf($s['enable_low_balance_alerts']),
            (int)$s['budget_warning_threshold_pct'],
            (float)$s['low_balance_threshold'],
            (int)$s['bill_reminder_days_before'],
            tf($s['enable_balance_alerts']),
            (float)$s['balance_threshold'],
            (int)$s['reminder_days'],
    ];
    pg_query_params($conn, $sql, $params);
}

function notif_exists($conn, $userId, $type, $message) {
    $sql = "
        SELECT 1
        FROM notifications
        WHERE user_id = $1
          AND notif_type = $2
          AND message = $3
        LIMIT 1
    ";
    $res = pg_query_params($conn, $sql, [$userId, $type, $message]);
    return $res && pg_fetch_row($res);
}

function create_notif_if_missing($conn, $userId, $type, $message, $isRead) {
    if (notif_exists($conn, $userId, $type, $message)) return;

    $sql = "
        INSERT INTO notifications (user_id, notif_type, message, is_read, created_at)
        VALUES ($1, $2, $3, $4, NOW())
    ";
    pg_query_params($conn, $sql, [$userId, $type, $message, tf($isRead)]);
}

function money2($n) { return number_format((float)$n, 2, '.', ''); }
function pct2($n) { return number_format((float)$n, 2, '.', ''); }

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $newSettings = [
            'enable_budget_warnings'        => isset($_POST["enableBudgetWarnings"]),
            'enable_bill_reminders'         => isset($_POST["enableBillReminders"]),
            'enable_goal_updates'           => isset($_POST["enableGoalUpdates"]),
            'enable_low_balance_alerts'     => isset($_POST["enableBalanceAlerts"]),
            'budget_warning_threshold_pct'  => (int)($_POST["budgetThreshold"] ?? 80),
            'low_balance_threshold'         => (float)($_POST["balanceThreshold"] ?? 100),
            'bill_reminder_days_before'     => (int)($_POST["reminderDays"] ?? 3),
            'enable_balance_alerts'         => true,
            'balance_threshold'             => (float)($_POST["balanceThreshold"] ?? 100),
            'reminder_days'                 => (int)($_POST["reminderDays"] ?? 3),
    ];

    insert_notification_settings($conn, $userId, $newSettings);

    header("Location: notifications.php?saved=1");
    exit;
}

$settings = load_latest_notification_settings($conn, $userId);

$enableBudgetWarnings = $settings['enable_budget_warnings'];
$enableBillReminders  = $settings['enable_bill_reminders'];
$enableGoalUpdates    = $settings['enable_goal_updates'];
$enableBalanceAlerts  = $settings['enable_low_balance_alerts'];

$budgetThreshold  = $settings['budget_warning_threshold_pct'];
$balanceThreshold = $settings['low_balance_threshold'];
$reminderDays     = $settings['bill_reminder_days_before'];

if ($settings['enable_budget_warnings']) {
    $sql = "
        WITH month_budgets AS (
            SELECT id, user_id, budget_month, category, budget_limit
            FROM budgets
            WHERE user_id = $1
        ),
        month_spend AS (
            SELECT
                t.user_id,
                date_trunc('month', t.tx_date)::date AS m,
                t.category,
                SUM(CASE WHEN t.tx_type = 'Expense' THEN t.amount ELSE 0 END) AS spent
            FROM transactions t
            WHERE t.user_id = $1
            GROUP BY t.user_id, date_trunc('month', t.tx_date)::date, t.category
        )
        SELECT
            b.budget_month,
            b.category,
            b.budget_limit,
            COALESCE(s.spent, 0) AS spent
        FROM month_budgets b
        LEFT JOIN month_spend s
          ON s.user_id = b.user_id
         AND s.m = b.budget_month
         AND s.category = b.category
    ";
    $res = pg_query_params($conn, $sql, [$userId]);
    if ($res) {
        while ($r = pg_fetch_assoc($res)) {
            $limit = (float)$r['budget_limit'];
            $spent = (float)$r['spent'];
            if ($limit <= 0) continue;

            $pct = ($spent / $limit) * 100.0;
            if ($pct >= (float)$settings['budget_warning_threshold_pct']) {
                $ym = date('Y-m', strtotime($r['budget_month']));
                $msg = "Budget warning: {$r['category']} reached " . pct2($pct) . "% (" . money2($spent) . " / " . money2($limit) . ") for {$ym}.";
                create_notif_if_missing($conn, $userId, 'Budget Warning', $msg, false);
            }
        }
    }
}

if ($settings['enable_goal_updates']) {
    $sql = "
        SELECT goal_name, target_amount, saved_amount, deadline
        FROM goals
        WHERE user_id = $1
        ORDER BY id DESC
    ";
    $res = pg_query_params($conn, $sql, [$userId]);
    if ($res) {
        while ($r = pg_fetch_assoc($res)) {
            $target = (float)$r['target_amount'];
            $saved  = (float)$r['saved_amount'];
            if ($target <= 0) continue;

            $pct = ($saved / $target) * 100.0;
            $deadline = $r['deadline'] ? $r['deadline'] : 'N/A';
            $msg = "Goal progress: {$r['goal_name']} is " . pct2($pct) . "% complete (" . money2($saved) . " / " . money2($target) . "). Deadline: {$deadline}.";
            create_notif_if_missing($conn, $userId, 'Goal Progress', $msg, false);
        }
    }
}

if ($settings['enable_bill_reminders']) {
    $sql = "
        SELECT payment_name, payment_type, total_amount, paid_amount
        FROM payments
        WHERE user_id = $1
        ORDER BY id DESC
    ";
    $res = pg_query_params($conn, $sql, [$userId]);
    if ($res) {
        while ($r = pg_fetch_assoc($res)) {
            $total = (float)$r['total_amount'];
            $paid  = (float)$r['paid_amount'];
            $remaining = $total - $paid;

            if ($total <= 0) continue;

            if ($remaining > 0.00001) {
                $msg = "Bill reminder: {$r['payment_name']} ({$r['payment_type']}) remaining " . money2($remaining) . " (paid " . money2($paid) . " / " . money2($total) . ").";
                create_notif_if_missing($conn, $userId, 'Bill Reminder', $msg, false);
            } else {
                $msg = "Bill reminder: {$r['payment_name']} ({$r['payment_type']}) fully paid (" . money2($paid) . " / " . money2($total) . ").";
                create_notif_if_missing($conn, $userId, 'Bill Reminder', $msg, true);
            }
        }
    }
}

$thresholdToUse = null;
if ($settings['enable_low_balance_alerts']) {
    $thresholdToUse = (float)$settings['low_balance_threshold'];
} elseif ($settings['enable_balance_alerts']) {
    $thresholdToUse = (float)$settings['balance_threshold'];
}

if ($thresholdToUse !== null) {
    $sql = "
        SELECT
            COALESCE(SUM(CASE WHEN tx_type = 'Income' THEN amount ELSE 0 END), 0) AS income_sum,
            COALESCE(SUM(CASE WHEN tx_type = 'Expense' THEN amount ELSE 0 END), 0) AS expense_sum
        FROM transactions
        WHERE user_id = $1
    ";
    $res = pg_query_params($conn, $sql, [$userId]);
    $r = fetch_one_assoc($res);
    if ($r) {
        $income = (float)$r['income_sum'];
        $expense = (float)$r['expense_sum'];
        $balance = $income - $expense;

        if ($balance <= $thresholdToUse) {
            $msg = "Low balance alert: Estimated balance is " . money2($balance) . ", below your threshold " . money2($thresholdToUse) . ".";
            create_notif_if_missing($conn, $userId, 'Low Balance Alert', $msg, false);
        }
    }
}

$notifSql = "
    SELECT id, notif_type, message, created_at, is_read
    FROM notifications
    WHERE user_id = $1
    ORDER BY created_at DESC, id DESC
    LIMIT 20
";
$notifRes = pg_query_params($conn, $notifSql, [$userId]);
$notifications = [];
if ($notifRes) {
    while ($r = pg_fetch_assoc($notifRes)) {
        $type = $r['notif_type'];
        $map = [
                'Budget Warning'    => 'budget',
                'Bill Reminder'     => 'bill',
                'Goal Progress'     => 'goal',
                'Low Balance Alert' => 'balance',
        ];

        $dt = new DateTime($r['created_at']);
        $dt->setTimezone(new DateTimeZone(date_default_timezone_get()));
        $prettyTime = $dt->format('Y-m-d H:i');

        $notifications[] = [
                'type' => $map[$type] ?? 'budget',
                'title' => $type,
                'message' => $r['message'],
                'time' => $prettyTime,
                'read' => ($r['is_read'] === 't'),
        ];
    }
}

$currentPage = 'notifications';
$pageTitle   = 'Notifications & Alerts';
require_once __DIR__ . '/../includes/header.php';

?>
<main class="main-content">

    <?php if (isset($_GET['saved'])): ?>
        <div style="background: #064e3b; border: 1px solid #059669; color: #a7f3d0; padding: 1rem; border-radius: 8px; margin-bottom: 1.5rem;">
            ✅ Alert settings saved successfully!
        </div>
    <?php endif; ?>

    <section class="panel panel-form">
        <h2 class="panel-title">Alert Settings</h2>
        <form class="form-grid" id="alertSettingsForm" method="post">
            <div class="form-group full-width">
                <label>
                    <input type="checkbox" id="enableBudgetWarnings" name="enableBudgetWarnings" <?php echo $enableBudgetWarnings ? 'checked' : ''; ?>>
                    <span style="margin-left: 0.5rem;">Enable budget warnings</span>
                </label>
            </div>

            <div class="form-group full-width">
                <label>
                    <input type="checkbox" id="enableBillReminders" name="enableBillReminders" <?php echo $enableBillReminders ? 'checked' : ''; ?>>
                    <span style="margin-left: 0.5rem;">Enable bill reminders</span>
                </label>
            </div>

            <div class="form-group full-width">
                <label>
                    <input type="checkbox" id="enableGoalUpdates" name="enableGoalUpdates" <?php echo $enableGoalUpdates ? 'checked' : ''; ?>>
                    <span style="margin-left: 0.5rem;">Enable goal progress updates</span>
                </label>
            </div>

            <div class="form-group full-width">
                <label>
                    <input type="checkbox" id="enableBalanceAlerts" name="enableBalanceAlerts" <?php echo $enableBalanceAlerts ? 'checked' : ''; ?>>
                    <span style="margin-left: 0.5rem;">Enable low balance alerts</span>
                </label>
            </div>

            <div class="form-group">
                <label for="budgetThreshold">Budget Warning Threshold (%)</label>
                <input type="number" id="budgetThreshold" name="budgetThreshold" min="0" max="100" value="<?php echo htmlspecialchars((string)$budgetThreshold); ?>" class="form-control">
            </div>

            <div class="form-group">
                <label for="balanceThreshold">Low Balance Threshold ($)</label>
                <input type="number" id="balanceThreshold" name="balanceThreshold" min="0" step="0.01" value="<?php echo htmlspecialchars((string)$balanceThreshold); ?>" class="form-control">
            </div>

            <div class="form-group">
                <label for="reminderDays">Bill Reminder Days Before Due</label>
                <input type="number" id="reminderDays" name="reminderDays" min="1" max="30" value="<?php echo htmlspecialchars((string)$reminderDays); ?>" class="form-control">
            </div>

            <div class="form-actions">
                <button type="submit" class="btn-primary btn-large">
                    Save Alert Settings
                </button>
            </div>
        </form>
    </section>

    <section class="panel">
        <h2 class="panel-title">Recent Notifications</h2>

        <div class="notifications-list">
            <?php if (count($notifications) === 0): ?>
                <div style="padding: 1rem; opacity: 0.8;">No notifications yet.</div>
            <?php endif; ?>

            <?php foreach ($notifications as $notification): ?>
                <div class="notification-item <?php echo $notification['read'] ? 'read' : 'unread'; ?> notification-<?php echo $notification['type']; ?>">
                    <div class="notification-icon">
                        <?php
                        $iconShapes = [
                                'budget' => '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="18" height="18" rx="2"></rect><path d="M3 9h18M9 21V9"></path></svg>',
                                'bill' => '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect><line x1="16" y1="2" x2="16" y2="6"></line><line x1="8" y1="2" x2="8" y2="6"></line><line x1="3" y1="10" x2="21" y2="10"></line></svg>',
                                'goal' => '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"></circle><path d="M12 6v6l4 2"></path></svg>',
                                'balance' => '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="1" x2="12" y2="23"></line><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"></path></svg>'
                        ];
                        echo $iconShapes[$notification['type']] ?? '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"></circle></svg>';
                        ?>
                    </div>
                    <div class="notification-content">
                        <div class="notification-title"><?php echo htmlspecialchars($notification['title']); ?></div>
                        <div class="notification-message"><?php echo htmlspecialchars($notification['message']); ?></div>
                        <div class="notification-time"><?php echo htmlspecialchars($notification['time']); ?></div>
                    </div>
                    <?php if (!$notification['read']): ?>
                        <div class="notification-badge"></div>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>

    </section>

</main>

<script>
    if (window.FMAppSettings && typeof window.FMAppSettings.applyFormatting === 'function') {
        window.FMAppSettings.applyFormatting();
    }
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
