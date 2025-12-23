<?php
require_once __DIR__ . '/../includes/web_boot.php';
require_once __DIR__ . '/../includes/auth.php';
require_login();

$conn = db_conn();
$userId = current_user_id();

$successMessage = '';
$errorMessage = '';

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $action = $_POST['action'] ?? 'add_budget';

    if ($action === 'add_expense') {
        $exCategory = trim($_POST["ex_category"] ?? "");
        $exAmount   = (float)($_POST["ex_amount"] ?? 0);
        $exDate     = $_POST["ex_date"] ?? date('Y-m-d');
        $exNote     = trim($_POST["ex_note"] ?? "");

        if ($exAmount <= 0) {
            $errorMessage = "Expense amount must be greater than 0.";
        } else {
            $sql = "
                INSERT INTO transactions (user_id, category, amount, tx_date, tx_type, note)
                VALUES ($1, $2, $3, $4, 'Expense', $5)
            ";
            $result = pg_query_params($conn, $sql, [$userId, $exCategory, $exAmount, $exDate, $exNote]);

            if ($result) {
                header("Location: budgets.php?success=expense_added");
                exit;
            } else {
                $errorMessage = "Error adding expense: " . pg_last_error($conn);
            }
        }

    } else {
        $category = trim($_POST["category"] ?? "");
        $limit    = (float)($_POST["limit"] ?? 0);
        $period   = trim($_POST["period"] ?? "");

        if (empty($category) || $limit <= 0 || empty($period)) {
            $errorMessage = "Please fill in all required fields correctly.";
        } else {
            $currentMonth = date('Y-m-01');
            $sql = "
                INSERT INTO budgets (user_id, category, budget_limit, period, budget_month)
                VALUES ($1, $2, $3, $4, $5)
            ";
            $result = pg_query_params($conn, $sql, [$userId, $category, $limit, $period, $currentMonth]);

            if ($result) {
                header("Location: budgets.php?success=budget_added");
                exit;
            } else {
                $errorMessage = "Error adding budget: " . pg_last_error($conn);
            }
        }
    }
}

if (isset($_GET['success'])) {
    if ($_GET['success'] == 'budget_added') $successMessage = "Budget added successfully!";
    if ($_GET['success'] == 'expense_added') $successMessage = "Expense tracked successfully!";
}

$currentPage = 'budgets';
$pageTitle   = 'Budgets';
require_once __DIR__ . '/../includes/header.php';

$sql = "
    SELECT id, category, budget_limit, period, budget_month
    FROM budgets
    WHERE user_id = $1
    ORDER BY budget_month DESC, category ASC
";
$res = pg_query_params($conn, $sql, [$userId]);
$budgets = [];
while ($row = pg_fetch_assoc($res)) {
    $budgets[] = [
            'id' => (int)$row['id'],
            'category' => $row['category'],
            'limit' => (float)$row['budget_limit'],
            'period' => $row['period'],
            'month' => $row['budget_month'],
            'spent' => 0
    ];
}

foreach ($budgets as &$budget) {
    $budgetDate = new DateTime($budget['month']);
    $sqlSpent = "
        SELECT COALESCE(SUM(amount), 0) as total
        FROM transactions
        WHERE user_id = $1 
        AND category = $2 
        AND tx_type = 'Expense'
        AND EXTRACT(MONTH FROM tx_date) = $3
        AND EXTRACT(YEAR FROM tx_date) = $4
    ";
    $resSpent = pg_query_params($conn, $sqlSpent, [$userId, $budget['category'], $budgetDate->format('m'), $budgetDate->format('Y')]);
    if ($resSpent) {
        $spentRow = pg_fetch_assoc($resSpent);
        $budget['spent'] = (float)$spentRow['total'];
    }
}
unset($budget);

$budgetCategories = array_column($budgets, 'category');
$budgetSpent = array_column($budgets, 'spent');
?>
    <!-- Chart.js CDN -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

    <style>
        /* Local small rules used by this page (kept minimal, don't modify global style) */
        .modal { display: none; position: fixed; z-index: 1000; left: 0; top: 0; width: 100%; height: 100%; overflow: auto; background-color: rgba(0,0,0,0.6); align-items: center; justify-content: center; }
        .modal-content { background-color: #1f2937; margin: 15% auto; padding: 20px; border: 1px solid #374151; width: 90%; max-width: 500px; border-radius: 8px; color: white; }
        .close-btn { color: #aaa; float: right; font-size: 28px; font-weight: bold; cursor: pointer; }
        .close-btn:hover { color: #fff; }
        .btn-sm { padding: 0.25rem 0.5rem; font-size: 0.875rem; border-radius: 4px; border: none; cursor: pointer; background: #374151; color: white; margin-left: 10px; transition: background 0.2s; }
        .btn-sm:hover { background: #4b5563; }

        /* ensure the canvas container has height so ChartJS can render */
        .budget-chart-container { position: relative; height: 350px; width: 100%; }
        #budgetChart { width: 100% !important; height: 100% !important; display: block; }
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
            <h2 class="panel-title">Add Budget</h2>
            <form class="form-grid" method="POST" action="budgets.php">
                <input type="hidden" name="action" value="add_budget">
                <div class="form-group">
                    <label for="budgetCategory">Category *</label>
                    <input type="text" id="budgetCategory" name="category" placeholder="e.g., Food" required class="form-control">
                </div>
                <div class="form-group">
                    <label for="budgetLimit">Limit ($) *</label>
                    <input type="number" id="budgetLimit" name="limit" min="0.01" step="0.01" required class="form-control">
                </div>
                <div class="form-group">
                    <label for="budgetPeriod">Period *</label>
                    <select id="budgetPeriod" name="period" required class="form-control">
                        <option value="monthly">Monthly</option>
                        <option value="weekly">Weekly</option>
                        <option value="yearly">Yearly</option>
                    </select>
                </div>
                <div class="form-actions">
                    <button type="submit" class="btn-primary btn-large"><span>➕</span> Add Budget</button>
                </div>
            </form>
        </section>

        <?php if (count($budgets) > 0): ?>
            <section class="panel">
                <h2 class="panel-title">Spending Breakdown</h2>
                <div class="budget-chart-container">
                    <canvas id="budgetChart" aria-label="Budget breakdown chart" role="img"></canvas>
                </div>
            </section>

            <section class="panel">
                <h2 class="panel-title">Budget Details</h2>
                <div class="budget-list">
                    <?php foreach ($budgets as $budget): ?>
                        <?php
                        $percent = $budget['limit'] > 0 ? round(($budget['spent'] / $budget['limit']) * 100) : 0;
                        $statusClass = $percent >= 100 ? 'budget-status-danger' : ($percent >= 80 ? 'budget-status-warning' : 'budget-status-safe');
                        $barClass    = $percent >= 100 ? 'progress-bar-danger' : ($percent >= 80 ? 'progress-bar-warning' : 'progress-bar-safe');
                        ?>
                        <div class="budget-item">
                            <div class="budget-header">
                                <div class="budget-title" style="display:flex; align-items:center;">
                                    <?php echo htmlspecialchars($budget['category']); ?>
                                    <button type="button" class="btn-sm" onclick="openExpenseModal('<?php echo htmlspecialchars($budget['category']); ?>')">
                                        + Add Spent
                                    </button>
                                </div>
                                <div class="budget-amounts">
                                    <span class="budget-spent" data-money="<?php echo htmlspecialchars((string)$budget['spent']); ?>"></span>
                                    <span class="budget-separator">/</span>
                                    <span class="budget-limit" data-money="<?php echo htmlspecialchars((string)$budget['limit']); ?>"></span>
                                    <span class="budget-percent <?php echo $statusClass; ?>"><?php echo $percent; ?>%</span>
                                </div>
                            </div>
                            <div class="progress-wrapper">
                                <div class="progress-bar <?php echo $barClass; ?>" style="width: <?php echo min($percent, 100); ?>%;"></div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </section>
        <?php else: ?>
            <section class="panel">
                <h2 class="panel-title">No Budgets Yet</h2>
                <p class="panel-text">Create your first budget above to start tracking your spending!</p>
            </section>
        <?php endif; ?>

    </main>

    <div id="expenseModal" class="modal">
        <div class="modal-content">
            <span class="close-btn" onclick="closeExpenseModal()">&times;</span>
            <h3>Add Expense</h3>
            <form method="POST" action="budgets.php" style="display:flex; flex-direction:column; gap:1rem; margin-top:1rem;">
                <input type="hidden" name="action" value="add_expense">
                <div>
                    <label>Category</label>
                    <input type="text" id="modalCategory" name="ex_category" readonly class="form-control" style="background:#374151; color:#9ca3af;">
                </div>
                <div>
                    <label>Amount ($)</label>
                    <input type="number" step="0.01" name="ex_amount" required class="form-control" placeholder="0.00" autofocus>
                </div>
                <div>
                    <label>Date</label>
                    <input type="date" name="ex_date" value="<?php echo date('Y-m-d'); ?>" required class="form-control">
                </div>
                <div>
                    <label>Note</label>
                    <input type="text" name="ex_note" class="form-control" placeholder="Description...">
                </div>
                <button type="submit" class="btn-primary" style="margin-top:10px;">Save Expense</button>
            </form>
        </div>
    </div>

    <script>
        (function() {
            // Data injected by PHP
            const categories = <?php echo json_encode($budgetCategories); ?> || [];
            const spent = <?php echo json_encode($budgetSpent); ?> || [];

            let budgetChartInstance = null;

            function getTextColor() {
                return document.body.classList.contains('dark-mode') ? '#e5e7eb' : '#374151';
            }

            function drawBudgetChart() {
                try {
                    const canvas = document.getElementById('budgetChart');
                    if (!canvas) return;

                    // Ensure canvas has explicit pixel size for clear rendering
                    const parent = canvas.parentElement;
                    canvas.width = parent.clientWidth * devicePixelRatio;
                    canvas.height = parent.clientHeight * devicePixelRatio;
                    canvas.style.width = parent.clientWidth + 'px';
                    canvas.style.height = parent.clientHeight + 'px';

                    if (budgetChartInstance) {
                        budgetChartInstance.destroy();
                        budgetChartInstance = null;
                    }

                    const total = (spent || []).reduce((a, b) => a + Number(b || 0), 0);

                    // If no meaningful data, draw friendly message
                    if (!categories || categories.length === 0 || total <= 0) {
                        const ctx = canvas.getContext('2d');
                        ctx.clearRect(0, 0, canvas.width, canvas.height);
                        ctx.save();
                        ctx.scale(devicePixelRatio, devicePixelRatio);
                        ctx.font = "16px Arial";
                        ctx.fillStyle = getTextColor();
                        ctx.textAlign = "center";
                        ctx.fillText("No budget data available", parent.clientWidth / 2, parent.clientHeight / 2);
                        ctx.restore();
                        return;
                    }

                    budgetChartInstance = new Chart(canvas.getContext('2d'), {
                        type: 'pie',
                        data: {
                            labels: categories,
                            datasets: [{
                                data: spent,
                                backgroundColor: [
                                    '#3b82f6','#10b981','#f59e0b','#ef4444','#8b5cf6','#06b6d4','#ec4899','#84cc16'
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
                                            let label = context.label || '';
                                            if (label) label += ': ';
                                            label += '$' + Number(context.parsed).toFixed(2);
                                            return label;
                                        }
                                    }
                                }
                            }
                        }
                    });
                } catch (err) {
                    console.error("drawBudgetChart error:", err);
                }
            }

            // Expose modal helpers
            const modal = document.getElementById("expenseModal");
            const categoryInput = document.getElementById("modalCategory");
            window.openExpenseModal = function(category) { modal.style.display = "flex"; categoryInput.value = category; };
            window.closeExpenseModal = function() { modal.style.display = "none"; };
            window.onclick = function(event) { if (event.target == modal) window.closeExpenseModal(); };

            // Ensure Chart draws after DOM ready and Chart.js loaded
            document.addEventListener('DOMContentLoaded', function() {
                drawBudgetChart();
            });

            // Redraw when theme toggle is used (if your UI toggles 'dark-mode' on body)
            const themeToggle = document.getElementById('themeToggle');
            if (themeToggle) {
                themeToggle.addEventListener('click', function() {
                    // allow theme class to update
                    setTimeout(drawBudgetChart, 80);
                });
            }

            // Also redraw on window resize
            window.addEventListener('resize', function() {
                setTimeout(drawBudgetChart, 80);
            });

            // Apply currency formatting if FMAppSettings present (fallback applied earlier)
            if (window.FMAppSettings && typeof window.FMAppSettings.applyFormatting === 'function') {
                window.FMAppSettings.applyFormatting();
            } else {
                // fallback minimal formatter
                document.querySelectorAll('[data-money]').forEach(function(el) {
                    var v = Number(el.getAttribute('data-money') || 0).toFixed(2);
                    v = v.replace(/\B(?=(\d{3})+(?!\d))/g, ',');
                    el.textContent = '$ ' + v;
                });
            }
        })();
    </script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>