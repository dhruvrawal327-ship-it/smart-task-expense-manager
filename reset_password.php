<?php
session_start();
require_once "includes/db.php";
require_once "includes/functions.php";

$token = $_GET['token'] ?? $_POST['token'] ?? '';
$tokenHash = hash('sha256', $token);
$error = "";
$success = "";

$stmt = $pdo->prepare("SELECT * FROM password_resets WHERE token_hash = ? AND expires_at > NOW()");
$stmt->execute([$tokenHash]);
$resetRequest = $stmt->fetch();

if (!$resetRequest) {
    $error = "This password reset link is invalid or has expired.";
} elseif ($_SERVER["REQUEST_METHOD"] === "POST") {
    verifyCsrf();
    $newPassword = $_POST['password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';

    if (strlen($newPassword) < 8) {
        $error = "Password must be at least 8 characters long.";
    } elseif ($newPassword !== $confirmPassword) {
        $error = "Passwords do not match.";
    } else {
        $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);

        $update = $pdo->prepare("UPDATE users SET password = ? WHERE email = ?");
        $update->execute([$hashedPassword, $resetRequest['email']]);

        $pdo->prepare("DELETE FROM password_resets WHERE email = ?")->execute([$resetRequest['email']]);

        // Log simulated password change alert
        sendMockEmail(
            $resetRequest['email'],
            "Security Alert: Password Updated",
            "Your Smart Task & Expense Manager password was recently changed."
        );

        $success = "Your password has been updated! You can now log in.";
    }
}

include "includes/header.php";
?>

<div class="row justify-content-center">
    <div class="col-md-5">
        <div class="card p-4">
            <h2 class="text-center mb-4">Choose New Password</h2>

            <?php if ($error): ?>
                <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>

            <?php if ($success): ?>
                <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
                <a href="login.php" class="btn btn-primary w-100">Go to Login</a>
            <?php elseif ($resetRequest): ?>
                <form method="POST">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrfToken()) ?>">
                    <input type="hidden" name="token" value="<?= htmlspecialchars($token) ?>">

                    <div class="mb-3">
                        <label class="form-label">New Password</label>
                        <input type="password" name="password" class="form-control" required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Confirm New Password</label>
                        <input type="password" name="confirm_password" class="form-control" required>
                    </div>

                    <button class="btn btn-primary w-100">Update Password</button>
                </form>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php include "includes/footer.php"; ?>
