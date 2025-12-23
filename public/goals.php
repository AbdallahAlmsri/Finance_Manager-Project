<?php
require_once __DIR__ . '/../includes/web_boot.php';
require_once __DIR__ . '/../includes/auth.php';
require_login();

$conn = db_conn();
$userId = current_user_id();

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $action = $_POST['action'] ?? 'add_goal';

    if ($action === 'add_saved_tx') {
        $gName  = $_POST['goal_name'];
        $amount = (float)$_POST['ex_amount'];
        $date   = $_POST['ex_date'] ?: date('Y-m-d');

        if ($amount <= 0) {
            header("Location: goals.php?error=invalid_amount");
            exit;
        }

        $res = pg_query_params($conn, "SELECT target_amount, saved_amount FROM goals WHERE user_id = $1 AND goal_name = $2", [$userId, $gName]);
        if ($res && $row = pg_fetch_assoc($res)) {
            $newSaved = $row['saved_amount'] + $amount;
            pg_query_params($conn, "UPDATE goals SET saved_amount = $1 WHERE user_id = $2 AND goal_name = $3", [$newSaved, $userId, $gName]);
        }

        pg_query_params($conn, "INSERT INTO transactions (user_id, category, amount, tx_date, tx_type, note) VALUES ($1, 'Savings', $2, $3, 'Expense', $4)", [$userId, $amount, $date, "Saved for: $gName"]);

        header("Location: goals.php?success=saved");
        exit;
    } else {
        $name = trim($_POST["name"] ?? "");
        $target = (float)$_POST["target"];
        $saved = (float)($_POST["saved"] ?? 0);
        $deadline = $_POST['deadline'] ?: null;

        if (empty($name) || $target <= 0) {
            header("Location: goals.php?error=invalid_goal");
            exit;
        }

        pg_query_params($conn, "INSERT INTO goals (user_id, goal_name, target_amount, saved_amount, deadline) VALUES ($1,$2,$3,$4,$5)", [$userId, $name, $target, $saved, $deadline]);
        header("Location: goals.php?success=goal_added");
        exit;
    }
}

$currentPage = 'goals';
$pageTitle = 'Savings Goals';
require_once __DIR__ . '/../includes/header.php';

$res = pg_query_params($conn, "SELECT goal_name, target_amount, saved_amount, deadline FROM goals WHERE user_id = $1", [$userId]);
$goals = pg_fetch_all($res) ?: [];

$successMessage = '';
$errorMessage = '';
if (isset($_GET['success'])) {
    if ($_GET['success'] == 'goal_added') $successMessage = "Goal added successfully!";
    if ($_GET['success'] == 'saved') $successMessage = "Savings added successfully!";
}
if (isset($_GET['error'])) {
    if ($_GET['error'] == 'invalid_amount') $errorMessage = "Amount must be greater than 0.";
    if ($_GET['error'] == 'invalid_goal') $errorMessage = "Please provide a valid goal name and target.";
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
        <h2 class="panel-title">Add Savings Goal</h2>
        <form class="form-grid" method="post">
            <div class="form-group"><label>Goal Name *</label><input type="text" name="name" required class="form-control"></div>
            <div class="form-group"><label>Target Amount ($) *</label><input type="number" name="target" step="0.01" required class="form-control"></div>
            <div class="form-actions"><button type="submit" class="btn-primary">Add Goal</button></div>
        </form>
    </section>

    <section class="panel">
        <h2 class="panel-title">Current Goals</h2>
        <?php if (count($goals) > 0): ?>
            <?php foreach ($goals as $g):
                $pct = $g['target_amount'] > 0 ? round(((float)$g['saved_amount'] / (float)$g['target_amount']) * 100) : 0;
                ?>
                <div class="goal-item" style="margin-bottom:20px;">
                    <div style="display:flex; justify-content:space-between; align-items:center;">
                        <strong><?php echo htmlspecialchars($g['goal_name']); ?>
                            <button type="button" class="btn-sm" onclick="openGoalModal('<?php echo htmlspecialchars($g['goal_name']); ?>')">
                                + Add Saved
                            </button>
                        </strong>
                        <span>
                            <span data-money="<?php echo htmlspecialchars((string)$g['saved_amount']); ?>"></span>
                            /
                            <span data-money="<?php echo htmlspecialchars((string)$g['target_amount']); ?>"></span>
                        </span>
                    </div>
                    <div style="background:#111827; height:8px; border-radius:4px; margin-top:8px;">
                        <div style="width:<?php echo min($pct, 100); ?>%; background:#10b981; height:100%; border-radius:4px;"></div>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <p class="panel-text">No savings goals yet. Add one above to get started!</p>
        <?php endif; ?>
    </section>
</main>

<div id="goalModal" class="modal">
    <div class="modal-content">
        <span class="close-btn" onclick="closeGoalModal()">&times;</span>
        <h3>Add Savings</h3>
        <form method="POST" action="goals.php" style="display:flex; flex-direction:column; gap:1rem; margin-top:1rem;">
            <input type="hidden" name="action" value="add_saved_tx">
            <input type="hidden" name="goal_name" id="modalGoalName">
            <div><label>Goal</label><input type="text" id="modalGoalDisplay" readonly class="form-control" style="background:#374151; color:#9ca3af;"></div>
            <div><label>Amount Saved ($)</label><input type="number" step="0.01" name="ex_amount" required class="form-control" placeholder="0.00" autofocus></div>
            <div><label>Date</label><input type="date" name="ex_date" value="<?php echo date('Y-m-d'); ?>" required class="form-control"></div>
            <button type="submit" class="btn-primary" style="margin-top:10px;">Save Amount</button>
        </form>
    </div>
</div>

<script>
    const modal = document.getElementById("goalModal");
    const goalInput = document.getElementById("modalGoalName");
    const goalDisplay = document.getElementById("modalGoalDisplay");
    function openGoalModal(goalName) {
        modal.style.display = "flex";
        goalInput.value = goalName;
        goalDisplay.value = goalName;
    }
    function closeGoalModal() { modal.style.display = "none"; }
    window.onclick = function(event) { if (event.target == modal) closeGoalModal(); }

    if (window.FMAppSettings && typeof window.FMAppSettings.applyFormatting === 'function') {
        window.FMAppSettings.applyFormatting();
    }
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
