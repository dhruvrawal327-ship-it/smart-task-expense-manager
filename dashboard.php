
<?php
require_once "includes/auth.php";
require_once "includes/functions.php";

$userId = $_SESSION['user_id'];

// Total tasks
$stmt = $pdo->prepare(
    "SELECT COUNT(*) FROM tasks WHERE user_id=?"
);
$stmt->execute([$userId]);
$totalTasks = $stmt->fetchColumn();

// Completed tasks
$stmt = $pdo->prepare(
    "SELECT COUNT(*) FROM tasks WHERE user_id=? AND status='Completed'"
);
$stmt->execute([$userId]);
$completedTasks = $stmt->fetchColumn();

// Pending tasks
$stmt = $pdo->prepare(
    "SELECT COUNT(*) FROM tasks WHERE user_id=? AND status='Pending'"
);
$stmt->execute([$userId]);
$pendingTasks = $stmt->fetchColumn();

// Total expenses
$stmt = $pdo->prepare(
    "SELECT COALESCE(SUM(amount),0) FROM expenses WHERE user_id=?"
);
$stmt->execute([$userId]);
$totalExpenses = $stmt->fetchColumn();

// Recent tasks
$stmt = $pdo->prepare(
    "SELECT title, status, priority, created_at
     FROM tasks WHERE user_id=?
     ORDER BY created_at DESC LIMIT 5"
);
$stmt->execute([$userId]);
$recentTasks = $stmt->fetchAll();

// Recent activities
$stmt = $pdo->prepare(
    "SELECT activity, created_at
     FROM activity_logs WHERE user_id=?
     ORDER BY created_at DESC LIMIT 5"
);
$stmt->execute([$userId]);
$activities = $stmt->fetchAll();

// Expense category summary
$stmt = $pdo->prepare(
    "SELECT category, SUM(amount) AS total
     FROM expenses WHERE user_id=?
     GROUP BY category ORDER BY total DESC"
);
$stmt->execute([$userId]);
$categorySummary = $stmt->fetchAll();

include "includes/header.php";
?>

<h2 class="page-title">
    Welcome, <?= htmlspecialchars($_SESSION['user_name']) ?>!
</h2>
<?php showMessage(); ?>

<p class="text-muted">
    Here's an overview of your tasks and expenses.
</p>

<?php showMessage(); ?>

<!-- Statistics Cards -->

<div class="row g-3 mb-4">

    <div class="col-md-6 col-lg-3">
        <div class="card p-3">
            <p class="text-muted mb-1">Total Tasks</p>
            <div class="stat-number text-primary">
                <?= $totalTasks ?>
            </div>
            <small>All your tasks</small>
        </div>
    </div>

    <div class="col-md-6 col-lg-3">
        <div class="card p-3">
            <p class="text-muted mb-1">Completed Tasks</p>
            <div class="stat-number text-success">
                <?= $completedTasks ?>
            </div>
            <small>Successfully completed</small>
        </div>
    </div>

    <div class="col-md-6 col-lg-3">
        <div class="card p-3">
            <p class="text-muted mb-1">Pending Tasks</p>
            <div class="stat-number text-warning">
                <?= $pendingTasks ?>
            </div>
            <small>Tasks remaining</small>
        </div>
    </div>

    <div class="col-md-6 col-lg-3">
        <div class="card p-3">
            <p class="text-muted mb-1">Total Expenses</p>
            <div class="stat-number text-danger">
                ₹<?= number_format((float)$totalExpenses, 2) ?>
            </div>
            <small>Your recorded expenses</small>
        </div>
    </div>

</div>

<!-- Quick Actions -->

<div class="card p-3 mb-4">
    <h5 class="mb-3">Quick Actions</h5>

    <div class="d-flex gap-2 flex-wrap">
        <a href="tasks/index.php" class="btn btn-primary">
            + Manage Tasks
        </a>

        <a href="expenses/index.php" class="btn btn-success">
            + Manage Expenses
        </a>

        <a href="reports.php" class="btn btn-dark">
            View Reports
        </a>
    </div>
</div>

<div class="row g-4">

    <!-- Recent Tasks -->

    <div class="col-lg-7">
        <div class="card p-3 h-100">

            <div class="d-flex justify-content-between mb-3">
                <h5>Recent Tasks</h5>
                <a href="tasks/index.php">View All</a>
            </div>

            <?php if ($recentTasks): ?>

                <div class="table-responsive">
                    <table class="table table-hover">

                        <thead>
                            <tr>
                                <th>Task</th>
                                <th>Priority</th>
                                <th>Status</th>
                            </tr>
                        </thead>

                        <tbody>
                        <?php foreach ($recentTasks as $task): ?>
                            <tr>
                                <td>
                                    <?= htmlspecialchars($task['title']) ?>
                                </td>

                                <td>
                                    <?= htmlspecialchars($task['priority']) ?>
                                </td>

                                <td>
                                    <span class="badge <?= $task['status'] === 'Completed' ? 'bg-success' : 'bg-warning text-dark' ?>">
                                        <?= htmlspecialchars($task['status']) ?>
                                    </span>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>

                    </table>
                </div>

            <?php else: ?>
                <p class="text-muted">No tasks available.</p>
            <?php endif; ?>

        </div>
    </div>

    <!-- Expense Category Summary -->

    <div class="col-lg-5">
        <div class="card p-3 h-100">

            <h5 class="mb-3">Expense Category Summary</h5>

            <?php if ($categorySummary): ?>

                <?php foreach ($categorySummary as $category): ?>

                    <div class="d-flex justify-content-between border-bottom py-2">
                        <span>
                            <?= htmlspecialchars($category['category']) ?>
                        </span>

                        <strong>
                            ₹<?= number_format((float)$category['total'], 2) ?>
                        </strong>
                    </div>

                <?php endforeach; ?>

            <?php else: ?>
                <p class="text-muted">No expenses recorded yet.</p>
            <?php endif; ?>

        </div>
    </div>

</div>

<!-- Recent Activities -->

<div class="card p-3 mt-4">

    <h5 class="mb-3">Recent Activities</h5>

    <?php if ($activities): ?>

        <?php foreach ($activities as $activity): ?>

            <div class="d-flex justify-content-between border-bottom py-2">
                <span>
                    <i class="text-primary">●</i>
                    <?= htmlspecialchars($activity['activity']) ?>
                </span>

                <small class="text-muted">
                    <?= date('d M, h:i A', strtotime($activity['created_at'])) ?>
                </small>
            </div>

        <?php endforeach; ?>

    <?php else: ?>

        <p class="text-muted">No recent activities.</p>

    <?php endif; ?>

</div>

<?php include "includes/footer.php"; ?>
