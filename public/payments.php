<?php
require_once __DIR__ . '/../includes/web_boot.php';
require_once __DIR__ . '/../includes/auth.php';
require_login();

$conn = db_conn();
$userId = current_user_id();

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $action = $_POST['action'] ?? 'add_payment';

    if ($action === 'add_expense') {
        $pName   = $_POST['payment_name'];
        $amount  = (float)$_POST['ex_amount'];
        $date    = $_POST['ex_date'] ?: date('Y-m-d');

        if ($amount <= 0) {
            header("Location: payments.php?error=invalid_amount");
            exit;
        }

        $res = pg_query_params($conn, "SELECT total_amount, paid_amount FROM payments WHERE user_id = $1 AND payment_name = $2", [$userId, $pName]);
        if ($res && $row = pg_fetch_assoc($res)) {
            $newPaid = $row['paid_amount'] + $amount;
            pg_query_params($conn, "UPDATE payments SET paid_amount = $1 WHERE user_id = $2 AND payment_name = $3", [$newPaid, $userId, $pName]);
        }

        pg_query_params($conn, "INSERT INTO transactions (user_id, category, amount, tx_date, tx_type, note) VALUES ($1, 'Debt/Payments', $2, $3, 'Expense', $4)", [$userId, $amount, $date, "Payment for: $pName"]);

        header("Location: payments.php?success=paid");
        exit;
    } else {
        $name = trim($_POST["name"] ?? "");
        $type = trim($_POST["type"] ?? "");
        $total = (float)$_POST["totalAmount"];
        $paid = (float)($_POST["paidAmount"] ?? 0);
        $monthly = (float)$_POST["monthlyPayment"];
        $due = $_POST["dueDate"];

        if (empty($name) || $total <= 0 || empty($type)) {
            header("Location: payments.php?error=invalid_payment");
            exit;
        }

        pg_query_params(
                $conn,
                "INSERT INTO payments (user_id, payment_name, payment_type, total_amount, paid_amount, monthly_payment, due_date) VALUES ($1, $2, $3, $4, $5, $6, $7)",
                [$userId, $name, $type, $total, $paid, $monthly, $due]
        );

        header("Location: payments.php?success=payment_added");
        exit;
    }
}

$currentPage = 'payments';
$pageTitle = 'Payments';
require_once __DIR__ . '/../includes/header.php';

$res = pg_query_params($conn, "SELECT payment_name, payment_type, total_amount, paid_amount, monthly_payment, due_date FROM payments WHERE user_id = $1 ORDER BY due_date ASC", [$userId]);
$payments = pg_fetch_all($res) ?: [];

$successMessage = '';
$errorMessage = '';
if (isset($_GET['success'])) {
    if ($_GET['success'] == 'payment_added') $successMessage = "Payment added successfully!";
    if ($_GET['success'] == 'paid') $successMessage = "Payment added successfully!";
}
if (isset($_GET['error'])) {
    if ($_GET['error'] == 'invalid_amount') $errorMessage = "Amount must be greater than 0.";
    if ($_GET['error'] == 'invalid_payment') $errorMessage = "Please provide a valid payment name, type, and total.";
}
?>

<style>
    .modal { display: none; position: fixed; z-index: 1000; left: 0; top: 0; width: 100%; height: 100%; overflow: auto; background-color: rgba(0,0,0,0.6); align-items: center; justify-content: center; }
    .modal-content { background-color: #1f2937; margin: 15% auto; padding: 20px; border: 1px solid #374151; width: 90%; max-width: 500px; border-radius: 8px; color: white; }
    .close-btn { color: #aaa; float: right; font-size: 28px; font-weight: bold; cursor: pointer; }
    .close-btn:hover { color: #fff; }
    .btn-sm { padding: 0.25rem 0.5rem; font-size: 0.875rem; border-radius: 4px; border: none; cursor: pointer; background: #374151; color: white; margin-left: 10px; transition: background 0.2s; }
    .btn-sm:hover { background: #4b5563; }
    .btn-ghost { background: transparent; color: #9ca3af; border: 1px solid #374151; margin-left: 10px; }
    .btn-ghost:hover { background: #374151; }
</style>

<main class="main-content">

    <?php if (!empty($successMessage)): ?>
        <div style="background: #064e3b; border: 1px solid #059669; color: #a7f3d0; padding: 1rem; border-radius: 8px; margin-bottom: 1.5rem;">
            ✅ <?php echo htmlspecialchars($successMessage); ?>
        </div>
    <?php endif; ?>

    <?php if (!empty($errorMessage)): ?>
        <div style="background: #7f1d1d; border: 1px solid #b91c1c; color: #fecaca; padding: 1rem; border-radius: 8px; margin-bottom: 1.5rem;">
            ❌ <?php echo htmlspecialchars($errorMessage); ?>
        </div>
    <?php endif; ?>

    <section class="panel panel-form">
        <h2 class="panel-title">Add Payment</h2>
        <form class="form-grid" method="post">
            <input type="hidden" name="action" value="add_payment">
            <div class="form-group"><label>Payment Name *</label><input type="text" name="name" required class="form-control"></div>
            <div class="form-group"><label>Type *</label><select name="type" required class="form-control"><option value="Loan">Loan</option><option value="Mortgage">Mortgage</option></select></div>
            <div class="form-group"><label>Total Amount ($) *</label><input type="number" name="totalAmount" step="0.01" required class="form-control"></div>
            <div class="form-group"><label>Monthly Payment ($) *</label><input type="number" name="monthlyPayment" step="0.01" required class="form-control"></div>
            <div class="form-group"><label>Due Date *</label><input type="date" name="dueDate" required class="form-control"></div>
            <div class="form-actions"><button type="submit" class="btn-primary">Add Payment</button></div>
        </form>
    </section>

    <section class="panel">
        <h2 class="panel-title">Active Payments</h2>
        <?php if (count($payments) > 0): ?>
            <div class="payments-list">
                <?php foreach ($payments as $p):
                    $pct = $p['total_amount'] > 0 ? round(((float)$p['paid_amount'] / (float)$p['total_amount']) * 100) : 0;
                    ?>
                    <div class="payment-item" style="margin-bottom:20px; border-bottom:1px solid #374151; padding-bottom:15px;">
                        <div style="display:flex; justify-content:space-between; align-items:center;">
                            <div class="payment-title">
                                <?php echo htmlspecialchars($p['payment_name']); ?>
                                <button type="button" class="btn-sm" onclick="openPayModal('<?php echo htmlspecialchars($p['payment_name']); ?>')">
                                    + Add Paid
                                </button>
                            </div>
                            <div class="payment-value">
                                <span data-money="<?php echo htmlspecialchars((string)$p['paid_amount']); ?>"></span>
                                /
                                <span data-money="<?php echo htmlspecialchars((string)$p['total_amount']); ?>"></span>
                            </div>
                        </div>
                        <div class="progress-wrapper" style="background:#111827; height:8px; border-radius:4px; margin-top:8px;">
                            <div class="progress-bar" style="width:<?php echo min($pct, 100); ?>%; background:#3b82f6; height:100%; border-radius:4px;"></div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <p class="panel-text">No active payments yet. Add one above to get started!</p>
        <?php endif; ?>
    </section>
</main>

<div id="payModal" class="modal">
    <div class="modal-content">
        <span class="close-btn" onclick="closePayModal()">&times;</span>
        <h3>Add Payment Expense</h3>
        <form method="POST" action="payments.php" style="display:flex; flex-direction:column; gap:1rem; margin-top:1rem;">
            <input type="hidden" name="action" value="add_expense">
            <input type="hidden" name="payment_name" id="modalPayName">
            <div><label>Payment</label><input type="text" id="modalPayDisplay" readonly class="form-control" style="background:#374151; color:#9ca3af;"></div>
            <div><label>Amount Paid ($)</label><input type="number" step="0.01" name="ex_amount" required class="form-control" placeholder="0.00" autofocus></div>
            <div><label>Date</label><input type="date" name="ex_date" value="<?php echo date('Y-m-d'); ?>" required class="form-control"></div>
            <button type="submit" class="btn-primary" style="margin-top:10px;">Save Expense</button>
        </form>
    </div>
</div>

<script>
    const modal = document.getElementById("payModal");
    const payInput = document.getElementById("modalPayName");
    const payDisplay = document.getElementById("modalPayDisplay");
    function openPayModal(payName) {
        modal.style.display = "flex";
        payInput.value = payName;
        payDisplay.value = payName;
    }
    function closePayModal() { modal.style.display = "none"; }
    window.onclick = function(event) { if (event.target == modal) closePayModal(); }

    if (window.FMAppSettings && typeof window.FMAppSettings.applyFormatting === 'function') {
        window.FMAppSettings.applyFormatting();
    }
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
