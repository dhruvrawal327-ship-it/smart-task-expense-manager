<?php
require_once "../includes/auth.php";
require_once "../includes/functions.php";

$userId = $_SESSION['user_id'];
$error = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    verifyCsrf();
    $action = $_POST['action'] ?? '';

    try {
        if ($action === "add" || $action === "update") {
            $title = trim($_POST['title'] ?? '');
            $description = trim($_POST['description'] ?? '');
            $priority = $_POST['priority'] ?? 'Medium';
            $dueDate = $_POST['due_date'] ?? '';

            if (
                $title === '' ||
                strlen($title) > 150 ||
                !in_array($priority, ['Low', 'Medium', 'High'], true) ||
                ($dueDate !== '' && !DateTime::createFromFormat('Y-m-d', $dueDate))
            ) {
                $error = "Please enter valid task details.";
            } else {
                if ($action === "add") {
                    $stmt = $pdo->prepare(
                        "INSERT INTO tasks (user_id, title, description, priority, due_date)
                         VALUES (?, ?, ?, ?, ?)"
                    );
                    $stmt->execute([$userId, $title, $description, $priority, $dueDate ?: null]);
                    logActivity($pdo, $userId, "Added task: " . $title);
                    setMessage("success", "Task added successfully!");
                } else {
                    $taskId = (int)($_POST['task_id'] ?? 0);
                    $stmt = $pdo->prepare(
                        "UPDATE tasks SET title=?, description=?, priority=?, due_date=?
                         WHERE id=? AND user_id=?"
                    );
                    $stmt->execute([$title, $description, $priority, $dueDate ?: null, $taskId, $userId]);
                    logActivity($pdo, $userId, "Updated task: " . $title);

                    $uStmt = $pdo->prepare("SELECT name, email FROM users WHERE id = ?");
                    $uStmt->execute([$userId]);
                    $currentUser = $uStmt->fetch();

                    if ($currentUser) {
                        $dueText = $dueDate ?: 'No due date';
                        sendMockEmail(
                            $currentUser['email'],
                            "Task Updated: " . $title,
                            "Hi " . $currentUser['name'] . ", your task '" . $title . "' has been updated.\nPriority: " . $priority . "\nDue Date: " . $dueText
                        );
                    }

                    setMessage("success", "Task updated successfully!");
                }

                header("Location: index.php");
                exit;
            }
        } elseif ($action === "delete") {
            $taskId = (int)($_POST['task_id'] ?? 0);
            $stmt = $pdo->prepare("DELETE FROM tasks WHERE id=? AND user_id=?");
            $stmt->execute([$taskId, $userId]);

            if ($stmt->rowCount() > 0) {
                logActivity($pdo, $userId, "Deleted a task");
                setMessage("success", "Task deleted successfully!");
            } else {
                setMessage("warning", "Task not found or already deleted.");
            }
            header("Location: index.php");
            exit;
        } elseif ($action === "complete" || $action === "pending") {
            $taskId = (int)($_POST['task_id'] ?? 0);
            $status = $action === "complete" ? "Completed" : "Pending";
            $stmt = $pdo->prepare("UPDATE tasks SET status=? WHERE id=? AND user_id=?");
            $stmt->execute([$status, $taskId, $userId]);

            if ($stmt->rowCount() > 0) {
                logActivity($pdo, $userId, "Changed task status to " . $status);
                setMessage("success", "Task marked as " . $status . "!");
            } else {
                setMessage("warning", "Task not found or status unchanged.");
            }
            header("Location: index.php");
            exit;
        }
    } catch (Throwable $e) {
        error_log($e->getMessage());
        setMessage("danger", "Something went wrong. Please try again.");
        header("Location: index.php");
        exit;
    }
}

$editTask = null;
if (isset($_GET['edit'])) {
    $stmt = $pdo->prepare("SELECT * FROM tasks WHERE id=? AND user_id=?");
    $stmt->execute([(int)$_GET['edit'], $userId]);
    $editTask = $stmt->fetch();
}

$search = trim($_GET['search'] ?? '');
$status = $_GET['status'] ?? '';
$priorityFilter = $_GET['priority'] ?? '';

$sql = "SELECT * FROM tasks WHERE user_id=?";
$params = [$userId];

if ($search !== '') {
    $sql .= " AND (title LIKE ? OR description LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
}
if (in_array($status, ['Pending', 'Completed'], true)) {
    $sql .= " AND status=?";
    $params[] = $status;
}
if (in_array($priorityFilter, ['Low', 'Medium', 'High'], true)) {
    $sql .= " AND priority=?";
    $params[] = $priorityFilter;
}

$sql .= " ORDER BY id DESC";

$countSql = "SELECT COUNT(*) FROM tasks WHERE user_id=?";
$countParams = [$userId];

if ($search !== '') {
    $countSql .= " AND (title LIKE ? OR description LIKE ?)";
    $countParams[] = "%$search%";
    $countParams[] = "%$search%";
}
if (in_array($status, ['Pending', 'Completed'], true)) {
    $countSql .= " AND status=?";
    $countParams[] = $status;
}
if (in_array($priorityFilter, ['Low', 'Medium', 'High'], true)) {
    $countSql .= " AND priority=?";
    $countParams[] = $priorityFilter;
}

$countStmt = $pdo->prepare($countSql);
$countStmt->execute($countParams);

$taskTotalCount = (int)$countStmt->fetchColumn();
$taskPerPage = 5;
$taskTotalPages = max(1, (int)ceil($taskTotalCount / $taskPerPage));
$taskCurrentPage = max(1, (int)($_GET['page'] ?? 1));
$taskCurrentPage = min($taskCurrentPage, $taskTotalPages);
$taskOffset = ($taskCurrentPage - 1) * $taskPerPage;

$sql .= " LIMIT $taskPerPage OFFSET $taskOffset";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$tasks = $stmt->fetchAll();

include "../includes/header.php";
?>
<h2 class="page-title">Task Management</h2>

<?php showMessage(); ?>

<?php if ($error): ?>
    <div class="alert alert-danger custom-alert" role="alert">
        <strong>Error!</strong> <?= htmlspecialchars($error) ?>
    </div>
<?php endif; ?>

<div class="card p-4 mb-4">
    <h5><?= $editTask ? "Edit Task" : "Add New Task" ?></h5>

    <form method="POST">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrfToken()) ?>">
        <input type="hidden" name="action" value="<?= $editTask ? 'update' : 'add' ?>">

        <?php if ($editTask): ?>
            <input type="hidden" name="task_id" value="<?= $editTask['id'] ?>">
        <?php endif; ?>

        <div class="mb-3">
            <label class="form-label">Task Title</label>
            <input type="text" name="title" class="form-control" maxlength="150" required
                   value="<?= htmlspecialchars($editTask['title'] ?? '') ?>">
        </div>

        <div class="mb-3">
            <label class="form-label">Description</label>
            <textarea name="description" class="form-control" rows="3"><?= htmlspecialchars($editTask['description'] ?? '') ?></textarea>
        </div>

        <div class="row">
            <div class="col-md-6 mb-3">
                <label class="form-label">Priority</label>
                <select name="priority" class="form-select">
                    <?php foreach (['Low', 'Medium', 'High'] as $p): ?>
                        <option value="<?= $p ?>" <?= ($editTask['priority'] ?? 'Medium') === $p ? 'selected' : '' ?>>
                            <?= $p ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="col-md-6 mb-3">
                <label class="form-label">Due Date</label>
                <input type="date" name="due_date" class="form-control"
                       value="<?= htmlspecialchars($editTask['due_date'] ?? '') ?>">
            </div>
        </div>

        <button class="btn btn-primary"><?= $editTask ? "Update Task" : "Add Task" ?></button>
        <?php if ($editTask): ?>
            <a href="index.php" class="btn btn-secondary">Cancel</a>
        <?php endif; ?>
    </form>
</div>

<div class="card p-3 mb-4">
    <form method="GET" class="row g-2">
        <div class="col-md-4">
            <input type="text" name="search" class="form-control" placeholder="Search tasks..."
                   value="<?= htmlspecialchars($search) ?>">
        </div>
        <div class="col-md-3">
            <select name="status" class="form-select">
                <option value="">All Status</option>
                <option value="Pending" <?= $status === 'Pending' ? 'selected' : '' ?>>Pending</option>
                <option value="Completed" <?= $status === 'Completed' ? 'selected' : '' ?>>Completed</option>
            </select>
        </div>
        <div class="col-md-3">
            <select name="priority" class="form-select">
                <option value="">All Priorities</option>
                <?php foreach (['Low', 'Medium', 'High'] as $p): ?>
                    <option value="<?= $p ?>" <?= $priorityFilter === $p ? 'selected' : '' ?>><?= $p ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-2 d-flex gap-2">
            <button class="btn btn-outline-primary w-100">Filter</button>
            <a href="index.php" class="btn btn-outline-secondary">Reset</a>
        </div>
    </form>
</div>

<div class="card p-4">
    <h5 class="mb-3">Your Tasks (<?= $taskTotalCount ?>)</h5>

    <?php if (empty($tasks)): ?>
        <p class="text-muted mb-0">No tasks found.</p>
    <?php else: ?>
        <div class="table-responsive">
            <table class="table table-hover align-middle mobile-card-table">
                <thead>
                    <tr>
                        <th>Title</th>
                        <th>Priority</th>
                        <th>Due Date</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($tasks as $t): ?>
                        <tr>
                            <td class="mobile-card-header">
                                <strong><?= htmlspecialchars($t['title']) ?></strong>
                                <?php if (!empty($t['description'])): ?>
                                    <div class="small text-muted"><?= htmlspecialchars($t['description']) ?></div>
                                <?php endif; ?>
                            </td>
                            <td data-label="Priority">
                                <?php
                                $badgeClass = 'bg-info text-dark';
                                if (($t['priority'] ?? '') === 'High') {
                                    $badgeClass = 'bg-danger';
                                } elseif (($t['priority'] ?? '') === 'Medium') {
                                    $badgeClass = 'bg-warning text-dark';
                                }
                                ?>
                                <span class="badge <?= $badgeClass ?>"><?= htmlspecialchars($t['priority']) ?></span>
                            </td>
                            <td data-label="Due Date" class="text-nowrap"><?= htmlspecialchars($t['due_date'] ?? '—') ?></td>
                            <td data-label="Status">
                                <span class="badge <?= ($t['status'] ?? '') === 'Completed' ? 'bg-success' : 'bg-secondary' ?>">
                                    <?= htmlspecialchars($t['status'] ?? 'Pending') ?>
                                </span>
                            </td>
                            <td>
                                <div class="d-flex gap-1 action-buttons">
                                    <form method="POST">
                                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrfToken()) ?>">
                                        <input type="hidden" name="task_id" value="<?= $t['id'] ?>">
                                        <input type="hidden" name="action" value="<?= ($t['status'] ?? '') === 'Completed' ? 'pending' : 'complete' ?>">
                                        <button class="btn btn-sm btn-outline-success">
                                            <?= ($t['status'] ?? '') === 'Completed' ? 'Undo' : 'Complete' ?>
                                        </button>
                                    </form>

                                    <a href="?edit=<?= $t['id'] ?>" class="btn btn-sm btn-warning">Edit</a>

                                    <form method="POST" onsubmit="return confirm('Delete this task?');">
                                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrfToken()) ?>">
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="task_id" value="<?= $t['id'] ?>">
                                        <button class="btn btn-sm btn-danger">Delete</button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <?php if ($taskTotalPages > 1): ?>
            <nav class="mt-3">
                <ul class="pagination justify-content-center mb-0">
                    <?php for ($i = 1; $i <= $taskTotalPages; $i++): ?>
                        <li class="page-item <?= $i === $taskCurrentPage ? 'active' : '' ?>">
                            <a class="page-link" href="?page=<?= $i ?>&search=<?= urlencode($search) ?>&status=<?= urlencode($status) ?>&priority=<?= urlencode($priorityFilter) ?>">
                                <?= $i ?>
                            </a>
                        </li>
                    <?php endfor; ?>
                </ul>
            </nav>
        <?php endif; ?>
    <?php endif; ?>
</div>

<?php include "../includes/footer.php"; ?>
