<?php
session_start();
require_once "includes/db.php";
require_once "includes/functions.php";

// 1. Auto-login check via Remember Me cookie
if (!isset($_SESSION['user_id']) && !empty($_COOKIE['remember_me'])) {
    $parts = explode(':', $_COOKIE['remember_me']);
    if (count($parts) === 2) {
        [$selector, $validator] = $parts;

        $stmt = $pdo->prepare("SELECT * FROM remember_tokens WHERE selector = ? AND expires_at > NOW()");
        $stmt->execute([$selector]);
        $tokenRow = $stmt->fetch();

        if ($tokenRow && hash_equals($tokenRow['hashed_validator'], hash('sha256', $validator))) {
            $userStmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
            $userStmt->execute([$tokenRow['user_id']]);
            $user = $userStmt->fetch();

            if ($user) {
                session_regenerate_id(true);
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['user_name'] = $user['name'];
                logActivity($pdo, $user['id'], "Auto-logged in via Remember Me");
            }
        }
    }
}

if (isset($_SESSION['user_id'])) {
    header("Location: dashboard.php");
    exit;
}

$error = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    verifyCsrf();

    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $remember = isset($_POST['remember_me']);

    $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
    $stmt->execute([$email]);
    $user = $stmt->fetch();

    if ($user && password_verify($password, $user['password'])) {
        session_regenerate_id(true);

        $_SESSION['user_id'] = $user['id'];
        $_SESSION['user_name'] = $user['name'];

        // 2. Create Remember Me token if checked
        if ($remember) {
            $selector = bin2hex(random_bytes(12));
            $validator = bin2hex(random_bytes(32));
            $hashedValidator = hash('sha256', $validator);

            $tokenStmt = $pdo->prepare("INSERT INTO remember_tokens (user_id, selector, hashed_validator, expires_at) VALUES (?, ?, ?, DATE_ADD(NOW(), INTERVAL 30 DAY))");
            $tokenStmt->execute([$user['id'], $selector, $hashedValidator]);

            setcookie(
                'remember_me',
                $selector . ':' . $validator,
                [
                    'expires' => time() + (86400 * 30),
                    'path' => '/',
                    'secure' => isset($_SERVER['HTTPS']),
                    'httponly' => true,
                    'samesite' => 'Lax'
                ]
            );
        }

        logActivity($pdo, $user['id'], "Logged in");

        // 3. Log simulated login notification email
        sendMockEmail(
            $user['email'],
            "New Login Detected",
            "Hi " . $user['name'] . ", a new login to your account was just recorded."
        );

        header("Location: dashboard.php");
        exit;
    } else {
        $error = "Invalid email or password.";
    }
}

include "includes/header.php";
?>

<div class="row justify-content-center">
    <div class="col-md-5">
        <div class="card p-4">
            <h2 class="text-center mb-4">Welcome Back</h2>

            <?php if ($error): ?>
                <div class="alert alert-danger">
                    <?= htmlspecialchars($error) ?>
                </div>
            <?php endif; ?>

            <form method="POST">
                <input type="hidden" name="csrf_token"
                       value="<?= htmlspecialchars(csrfToken()) ?>">

                <div class="mb-3">
                    <label class="form-label">Email</label>
                    <input type="email" name="email"
                           class="form-control" required>
                </div>

                <div class="mb-3">
                    <label class="form-label">Password</label>
                    <input type="password" name="password"
                           class="form-control" required>
                </div>

                <div class="d-flex justify-content-between align-items-center mb-3">
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" name="remember_me" id="remember_me">
                        <label class="form-check-label" for="remember_me">
                            Remember Me
                        </label>
                    </div>
                    <a href="forgot_password.php" class="text-decoration-none">Forgot Password?</a>
                </div>

                <button class="btn btn-primary w-100">Login</button>
            </form>

            <p class="text-center mt-3">
                Don't have an account?
                <a href="register.php">Register</a>
            </p>
        </div>
    </div>
</div>

<?php include "includes/footer.php"; ?>
