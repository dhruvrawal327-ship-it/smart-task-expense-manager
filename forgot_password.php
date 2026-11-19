<?php
session_start();
require_once "includes/db.php";
require_once "includes/functions.php";

$message = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    verifyCsrf();
    $email = trim($_POST['email'] ?? '');

    $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
    $stmt->execute([$email]);
    $user = $stmt->fetch();

    if ($user) {
        // Delete any old reset tokens for this email
        $pdo->prepare("DELETE FROM password_resets WHERE email = ?")->execute([$email]);

        $rawToken = bin2hex(random_bytes(32));
        $tokenHash = hash('sha256', $rawToken);

        $insert = $pdo->prepare("INSERT INTO password_resets (email, token_hash, expires_at) VALUES (?, ?, DATE_ADD(NOW(), INTERVAL 1 HOUR))");
        $insert->execute([$email, $tokenHash]);

        // Build reset URL
        $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https://' : 'http://';
        $dir = rtrim(dirname($_SERVER['PHP_SELF']), '/\\');
        $resetLink = $protocol . $_SERVER['HTTP_HOST'] . $dir . "/reset_password.php?token=" . $rawToken;

        // Log simulated reset email
        sendMockEmail(
            $email,
            "Password Reset Request",
            "Click this link to reset your password (valid for 1 hour):\n" . $resetLink
        );

        // Localhost Test Mode link
        $message = '<strong>Localhost Test Mode:</strong> <a href="' . htmlspecialchars($resetLink) . '" class="alert-link">Click here to reset your password</a>';
    } else {
        $message = "If an account exists with that email, a password reset link has been sent.";
    }
}

include "includes/header.php";
?>

<div class="row justify-content-center">
    <div class="col-md-5">
        <div class="card p-4">
            <h2 class="text-center mb-3">Reset Password</h2>
            <p class="text-muted text-center mb-4">Enter your email to receive a reset link.</p>

            <?php if ($message): ?>
                <div class="alert alert-info"><?= $message ?></div>
            <?php endif; ?>

            <form method="POST">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrfToken()) ?>">

                <div class="mb-3">
                    <label class="form-label">Email</label>
                    <input type="email" name="email" class="form-control" required>
                </div>

                <button class="btn btn-primary w-100">Send Reset Link</button>
            </form>

            <p class="text-center mt-3">
                <a href="login.php">Back to Login</a>
            </p>
        </div>
    </div>
</div>

<?php include "includes/footer.php"; ?>
