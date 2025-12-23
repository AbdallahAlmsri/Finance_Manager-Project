<?php

require_once __DIR__ . '/../includes/web_boot.php';
require_once __DIR__ . '/../includes/auth.php';
require_login();

$conn = db_conn();
$userId = current_user_id();

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $type = $_POST["type"] ?? "";
    $category = trim($_POST["category"] ?? "");
    $amount = (float)($_POST["amount"] ?? 0);
    $date = $_POST["date"] ?? "";
    $note = trim($_POST["note"] ?? "");

    if ($type !== "" && $category !== "" && $amount > 0 && $date !== "") {
        $sql = "
            INSERT INTO transactions (user_id, tx_date, tx_type, category, amount, note)
            VALUES ($1, $2, $3, $4, $5, $6)
        ";
        pg_query_params($conn, $sql, [$userId, $date, $type, $category, $amount, $note]);
    }

    header("Location: transactions.php");
    exit;
}

$currentPage = 'transactions';
$pageTitle   = 'Transactions';
require_once __DIR__ . '/../includes/header.php';

$sql = "
    SELECT id, tx_date, tx_type, category, amount, COALESCE(note, '') AS note
    FROM transactions
    WHERE user_id = $1
    ORDER BY tx_date DESC, id DESC
";

$res = pg_query_params($conn, $sql, [$userId]);

if (!$res) {
    die("Failed to load transactions");
}

$allTransactions = [];
while ($row = pg_fetch_assoc($res)) {
    $allTransactions[] = [
            'id'       => (int)$row['id'],
            'date'     => $row['tx_date'],
            'type'     => $row['tx_type'],
            'category' => $row['category'],
            'amount'   => (float)$row['amount'],
            'note'     => $row['note'],
    ];
}

$totalIncome = 0.0;
$totalExpense = 0.0;

foreach ($allTransactions as $tx) {
    if ($tx['type'] === 'Income') {
        $totalIncome += $tx['amount'];
    } else {
        $totalExpense += $tx['amount'];
    }
}

$transactionCount = count($allTransactions);
?>

<main class="main-content">

    <section class="cards-row">
        <div class="card">
            <h2 class="card-title">Total Transactions</h2>
            <p class="card-value"><?php echo $transactionCount; ?></p>
        </div>

        <div class="card card-income">
            <h2 class="card-title">Total Income</h2>
            <p class="card-value" data-money="<?php echo htmlspecialchars((string)$totalIncome); ?>"></p>
        </div>

        <div class="card card-expense">
            <h2 class="card-title">Total Expenses</h2>
            <p class="card-value" data-money="<?php echo htmlspecialchars((string)$totalExpense); ?>"></p>
        </div>
    </section>

    <section class="panel panel-form">
        <h2 class="panel-title">Add New Transaction</h2>
        <form id="transactionForm" class="form-grid" method="post">
            <div class="form-group">
                <label for="txType">Type *</label>
                <select id="txType" name="type" required class="form-control">
                    <option value="">Choose type…</option>
                    <option value="Income">Income</option>
                    <option value="Expense">Expense</option>
                </select>
            </div>

            <div class="form-group">
                <label for="txCategory">Category *</label>
                <input type="text" id="txCategory" name="category" required class="form-control">
            </div>

            <div class="form-group">
                <label for="txAmount">Amount *</label>
                <input type="number" id="txAmount" name="amount" min="0" step="0.01" required class="form-control">
            </div>

            <div class="form-group">
                <label for="txDate">Date *</label>
                <input type="date" id="txDate" name="date" value="<?php echo date('Y-m-d'); ?>" required class="form-control">
            </div>

            <div class="form-group full-width">
                <label for="txNote">Note</label>
                <input type="text" id="txNote" name="note" class="form-control">
            </div>

            <div class="form-actions">
                <button type="submit" class="btn-primary btn-large">Add Transaction</button>
            </div>
        </form>
    </section>

    <section class="panel">
        <h2 class="panel-title">All Transactions</h2>
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
                <?php foreach ($allTransactions as $tx):
                    $signedAmount = ($tx['type'] === 'Income') ? $tx['amount'] : -$tx['amount'];
                    ?>
                    <tr>
                        <td data-date="<?php echo htmlspecialchars($tx['date']); ?>"></td>
                        <td><?php echo htmlspecialchars($tx['type']); ?></td>
                        <td><?php echo htmlspecialchars($tx['category']); ?></td>
                        <td class="align-right" data-money="<?php echo htmlspecialchars((string)$signedAmount); ?>"></td>
                        <td><?php echo htmlspecialchars($tx['note']); ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </section>

</main>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
