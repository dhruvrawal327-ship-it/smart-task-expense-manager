
<?php
session_start();
require_once "includes/db.php";
require_once "includes/functions.php";

if (isset($_SESSION['user_id'])) {
    header("Location: dashboard.php");
    exit;
}

$error = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    verifyCsrf();

    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($name === '' || !filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($password) < 6) {
        $error = "Enter valid details. Password must be at least 6 characters.";
    } else {
        $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->execute([$email]);

        if ($stmt->fetch()) {
            $error = "Email is already registered.";
        } else {
            $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

            $stmt = $pdo->prepare(
                "INSERT INTO users (name, email, password) VALUES (?, ?, ?)"
            );
            $stmt->execute([$name, $email, $hashedPassword]);

            $userId = $pdo->lastInsertId();

            session_regenerate_id(true);
            $_SESSION['user_id'] = $userId;
            $_SESSION['user_name'] = $name;

            logActivity($pdo, $userId, "Registered an account");

            header("Location: dashboard.php");
            exit;
        }
    }
}

include "includes/header.php";
?>

<div class="row justify-content-center">
    <div class="col-md-5">
        <div class="card p-4">
            <h2 class="text-center mb-4">Create Account</h2>

            <?php if ($error): ?>
                <div class="alert alert-danger">
                    <?= htmlspecialchars($error) ?>
                </div>
            <?php endif; ?>

            <form method="POST">
                <input type="hidden" name="csrf_token"
                       value="<?= htmlspecialchars(csrfToken()) ?>">

                <div class="mb-3">
                    <label class="form-label">Full Name</label>
                    <input type="text" name="name" class="form-control"
                           maxlength="100" required>
                </div>

                <div class="mb-3">
                    <label class="form-label">Email</label>
                    <input type="email" name="email" class="form-control"
                           maxlength="150" required>
                </div>

                <div class="mb-3">
                    <label class="form-label">Password</label>
                    <input type="password" name="password"
                           class="form-control" minlength="6" required>
                </div>

                <button class="btn btn-primary w-100">Register</button>
            </form>

            <p class="text-center mt-3">
                Already registered?
                <a href="login.php">Login</a>
            </p>
        </div>
    </div>
</div>

<?php include "includes/footer.php"; ?>
