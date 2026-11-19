<?php
require_once "../includes/auth.php";
require_once "../includes/functions.php";

$userId = $_SESSION['user_id'];
$error = "";
$monthlyBudgetLimit = 1000;

$categories = [
    "Food", "Travel", "Shopping", "Bills",
    "Entertainment", "Health", "Education", "Other"
];

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    verifyCsrf();
    $action = $_POST['action'] ?? '';

    if ($action === "add" || $action === "update") {
        $title = trim($_POST['title'] ?? '');
        $amount = $_POST['amount'] ?? '';
        $category = $_POST['category'] ?? '';
        $date = $_POST['expense_date'] ?? '';
        $notes = trim($_POST['notes'] ?? '');

        $validDate = DateTime::createFromFormat('Y-m-d', $date);

        if (
            $title === '' ||
            strlen($title) > 150 ||
            !is_numeric($amount) ||
            $amount <= 0 ||
            $amount > 99999999.99 ||
            !in_array($category, $categories, true) ||
            !$validDate ||
            $validDate->format('Y-m-d') !== $date
        ) {
            $error = "Please enter valid expense details.";
        } else {
            if ($action === "add") {
                $stmt = $pdo->prepare(
                    "INSERT INTO expenses (user_id, title, amount, category, expense_date, notes)
                     VALUES (?, ?, ?, ?, ?, ?)"
                );
                $stmt->execute([$userId, $title, $amount, $category, $date, $notes]);
                logActivity($pdo, $userId, "Added expense: " . $title);
                setMessage("success", "Expense added successfully!");
            } else {
                $expenseId = (int)($_POST['expense_id'] ?? 0);
                $stmt = $pdo->prepare(
                    "UPDATE expenses SET title=?, amount=?, category=?, expense_date=?, notes=?
                     WHERE id=? AND user_id=?"
                );
                $stmt->execute([$title, $amount, $category, $date, $notes, $expenseId, $userId]);
                logActivity($pdo, $userId, "Updated expense: " . $title);
                setMessage("success", "Expense updated successfully!");
            }

            $monthYear = $validDate->format('Y-m');
            $sumStmt = $pdo->prepare(
                "SELECT SUM(amount) FROM expenses WHERE user_id = ? AND DATE_FORMAT(expense_date, '%Y-%m') = ?"
            );
            $sumStmt->execute([$userId, $monthYear]);
            $monthlyTotal = (float)$sumStmt->fetchColumn();

            if ($monthlyTotal > $monthlyBudgetLimit) {
                $uStmt = $pdo->prepare("SELECT name, email FROM users WHERE id = ?");
                $uStmt->execute([$userId]);
                $currentUser = $uStmt->fetch();

                if ($currentUser) {
                    sendMockEmail(
                        $currentUser['email'],
                        "Budget Alert: Monthly Expenses Exceeded (" . $validDate->format('F Y') . ")",
                        "Hi " . $currentUser['name'] . ",\n\n"
                        . "Your total expenses for " . $validDate->format('F Y') . " have reached ₹" . number_format($monthlyTotal, 2)
                        . ", which exceeds your monthly budget limit of ₹" . number_format($monthlyBudgetLimit, 2) . ".\n"
                        . "Latest entry: " . $title . " (₹" . number_format((float)$amount, 2) . ")"
                    );
                }
            }

            header("Location: index.php");
            exit;
        }
    }

    if ($action === "delete") {
        $expenseId = (int)($_POST['expense_id'] ?? 0);
        $stmt = $pdo->prepare("DELETE FROM expenses WHERE id=? AND user_id=?");
        $stmt->execute([$expenseId, $userId]);
        logActivity($pdo, $userId, "Deleted an expense");
        setMessage("success", "Expense deleted successfully!");
        header("Location: index.php");
        exit;
    }
}

$editExpense = null;
if (isset($_GET['edit'])) {
    $stmt = $pdo->prepare("SELECT * FROM expenses WHERE id=? AND user_id=?");
    $stmt->execute([(int)$_GET['edit'], $userId]);
    $editExpense = $stmt->fetch();
}

$search = trim($_GET['search'] ?? '');
$filterCategory = $_GET['category'] ?? '';
$fromDate = $_GET['from_date'] ?? '';
$toDate = $_GET['to_date'] ?? '';

$sql = "SELECT * FROM expenses WHERE user_id=?";
$params = [$userId];

if ($search !== '') {
    $sql .= " AND (title LIKE ? OR notes LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
}
if (in_array($filterCategory, $categories, true)) {
    $sql .= " AND category=?";
    $params[] = $filterCategory;
}
if ($fromDate !== '' && DateTime::createFromFormat('Y-m-d', $fromDate)) {
    $sql .= " AND expense_date >= ?";
    $params[] = $fromDate;
}
if ($toDate !== '' && DateTime::createFromFormat('Y-m-d', $toDate)) {
    $sql .= " AND expense_date <= ?";
    $params[] = $toDate;
}

$sql .= " ORDER BY expense_date DESC, id DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$expenses = $stmt->fetchAll();

$filteredTotal = array_sum(array_column($expenses, 'amount'));

include "../includes/header.php";
?>

<h2 class="page-title">Expense Management</h2>
<?php showMessage(); ?>

<?php if ($error): ?>
    <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
<?php endif; ?>

<div class="card p-4 mb-4">
    <h5><?= $editExpense ? "Edit Expense" : "Add New Expense" ?></h5>
    <form method="POST">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrfToken()) ?>">
        <input type="hidden" name="action" value="<?= $editExpense ? 'update' : 'add' ?>">
        <?php if ($editExpense): ?>
            <input type="hidden" name="expense_id" value="<?= $editExpense['id'] ?>">
        <?php endif; ?>

        <div class="mb-3">
            <label class="form-label">Expense Title</label>
            <input type="text" name="title" class="form-control" maxlength="150" required value="<?= htmlspecialchars($editExpense['title'] ?? '') ?>">
        </div>

        <div class="row">
            <div class="col-md-4 mb-3">
                <label class="form-label">Amount (₹)</label>
                <input type="number" name="amount" class="form-control" step="0.01" min="0.01" max="99999999.99" required value="<?= htmlspecialchars($editExpense['amount'] ?? '') ?>">
            </div>
            <div class="col-md-4 mb-3">
                <label class="form-label">Category</label>
                <select name="category" class="form-select" required>
                    <option value="">Select Category</option>
                    <?php foreach ($categories as $cat): ?>
                        <option value="<?= $cat ?>" <?= ($editExpense['category'] ?? '') === $cat ? 'selected' : '' ?>><?= $cat ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-4 mb-3">
                <label class="form-label">Date</label>
                <input type="date" name="expense_date" class="form-control" required value="<?= htmlspecialchars($editExpense['expense_date'] ?? date('Y-m-d')) ?>">
            </div>
        </div>

        <div class="mb-3">
            <label class="form-label">Notes</label>
            <textarea name="notes" class="form-control" rows="2"><?= htmlspecialchars($editExpense['notes'] ?? '') ?></textarea>
        </div>

        <button class="btn btn-primary"><?= $editExpense ? "Update Expense" : "Add Expense" ?></button>
        <?php if ($editExpense): ?>
            <a href="index.php" class="btn btn-secondary">Cancel</a>
        <?php endif; ?>
    </form>
</div>

<div class="card p-3 mb-4">
    <form method="GET" class="row g-2">
        <div class="col-md-3">
            <input type="text" name="search" class="form-control" placeholder="Search expenses..." value="<?= htmlspecialchars($search) ?>">
        </div>
        <div class="col-md-3">
            <select name="category" class="form-select">
                <option value="">All Categories</option>
                <?php foreach ($categories as $cat): ?>
                    <option value="<?= $cat ?>" <?= $filterCategory === $cat ? 'selected' : '' ?>><?= $cat ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-2">
            <input type="date" name="from_date" class="form-control" value="<?= htmlspecialchars($fromDate) ?>">
        </div>
        <div class="col-md-2">
            <input type="date" name="to_date" class="form-control" value="<?= htmlspecialchars($toDate) ?>">
        </div>
        <div class="col-md-2 d-flex gap-2">
            <button class="btn btn-outline-primary w-100">Filter</button>
            <a href="index.php" class="btn btn-outline-secondary">Reset</a>
        </div>
    </form>
</div>

<div class="card p-4">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h5 class="mb-0">Your Expenses</h5>
        <span class="badge bg-primary fs-6">Total: ₹<?= number_format($filteredTotal, 2) ?></span>
    </div>

    <?php if (empty($expenses)): ?>
        <p class="text-muted mb-0">No expenses found.</p>
    <?php else: ?>
        <div class="table-responsive">
            <table class="table table-hover align-middle">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Title</th>
                        <th>Category</th>
                        <th>Amount</th>
                        <th>Notes</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($expenses as $exp): ?>
                        <tr>
                            <td><?= htmlspecialchars($exp['expense_date']) ?></td>
                            <td><strong><?= htmlspecialchars($exp['title']) ?></strong></td>
                            <td><span class="badge bg-secondary"><?= htmlspecialchars($exp['category']) ?></span></td>
                            <td>₹<?= number_format((float)$exp['amount'], 2) ?></td>
                            <td><?= htmlspecialchars($exp['notes'] ?? '') ?></td>
                            <td>
                                <a href="?edit=<?= $exp['id'] ?>" class="btn btn-sm btn-warning">Edit</a>
                                <form method="POST" class="d-inline" onsubmit="return confirm('Delete this expense?');">
                                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrfToken()) ?>">
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="expense_id" value="<?= $exp['id'] ?>">
                                    <button class="btn btn-sm btn-danger">Delete</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<?php include "../includes/footer.php"; ?>
