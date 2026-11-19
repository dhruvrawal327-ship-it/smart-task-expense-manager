<?php
session_start();
require_once "includes/db.php";
require_once "includes/functions.php";

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$logFile = __DIR__ . '/email_logs.txt';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['clear_logs'])) {
    verifyCsrf();
    if (file_exists($logFile)) {
        unlink($logFile);
    }
    header("Location: view_emails.php");
    exit;
}

$logs = file_exists($logFile) ? file_get_contents($logFile) : "No simulated emails sent yet.";

include "includes/header.php";
?>

<div class="container mt-4">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h3 class="page-title mb-0">Simulated Email Inbox</h3>
        <form method="POST">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrfToken()) ?>">
            <button type="submit" name="clear_logs" class="btn btn-outline-danger btn-sm">Clear Inbox</button>
        </form>
    </div>

    <div class="card p-4">
        <pre class="mb-0" style="white-space: pre-wrap; font-family: monospace; color: inherit; background: transparent; border: none;"><?= htmlspecialchars($logs) ?></pre>
    </div>
</div>

<?php include "includes/footer.php"; ?>
