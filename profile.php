
<?php
require_once "includes/auth.php";
require_once "includes/functions.php";

$userId = $_SESSION['user_id'];
$error = "";

// Fetch user details
$stmt = $pdo->prepare(
    "SELECT name, email, profile_picture
     FROM users WHERE id = ?"
);
$stmt->execute([$userId]);
$user = $stmt->fetch();

if (!$user) {
    exit("User not found.");
}

// Handle profile picture upload
if ($_SERVER["REQUEST_METHOD"] === "POST") {

    verifyCsrf();

    if (
        !isset($_FILES['profile_picture']) ||
        $_FILES['profile_picture']['error'] === UPLOAD_ERR_NO_FILE
    ) {
        $error = "Please select an image.";

    } elseif ($_FILES['profile_picture']['error'] !== UPLOAD_ERR_OK) {
        $error = "Image upload failed. Please try again.";

    } elseif ($_FILES['profile_picture']['size'] > 2 * 1024 * 1024) {
        $error = "Image size must be less than 2 MB.";

    } else {

        $tmpPath = $_FILES['profile_picture']['tmp_name'];

        $imageInfo = @getimagesize($tmpPath);

        $allowedTypes = [
            IMAGETYPE_JPEG => 'jpg',
            IMAGETYPE_PNG => 'png',
            IMAGETYPE_WEBP => 'webp'
        ];

        if (
            !$imageInfo ||
            !isset($allowedTypes[$imageInfo[2]])
        ) {
            $error = "Only JPG, PNG and WEBP images are allowed.";

        } else {

            $uploadDir = __DIR__ . "/uploads/profile/";

            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0755, true);
            }

            $extension = $allowedTypes[$imageInfo[2]];
            $fileName = bin2hex(random_bytes(16)) . "." . $extension;
            $destination = $uploadDir . $fileName;

            if (move_uploaded_file($tmpPath, $destination)) {

                // Delete previous profile picture
                if (!empty($user['profile_picture'])) {
                    $oldFile = $uploadDir . basename($user['profile_picture']);

                    if (is_file($oldFile)) {
                        unlink($oldFile);
                    }
                }

                $stmt = $pdo->prepare(
                    "UPDATE users SET profile_picture = ?
                     WHERE id = ?"
                );

                $stmt->execute([$fileName, $userId]);

                logActivity($pdo, $userId, "Updated profile picture");

                setMessage("success", "Profile picture updated successfully!");

                header("Location: profile.php");
                exit;

            } else {
                $error = "Unable to save image. Please try again.";
            }
        }
    }
}

include "includes/header.php";
?>

<h2 class="page-title text-center">My Profile</h2>

<?php showMessage(); ?>

<?php if ($error): ?>
    <div class="alert alert-danger">
        <?= htmlspecialchars($error) ?>
    </div>
<?php endif; ?>

<div class="card p-4 mx-auto" style="max-width:600px;">

    <div class="text-center mb-4">

        <?php if (!empty($user['profile_picture'])): ?>

            <img
                src="uploads/profile/<?= htmlspecialchars(basename($user['profile_picture'])) ?>"
                alt="Profile Picture"
                class="rounded-circle"
                width="130"
                height="130"
                style="object-fit:cover;"
            >

        <?php else: ?>

            <div class="rounded-circle bg-secondary text-white
                        d-flex align-items-center justify-content-center mx-auto"
                 style="width:130px;height:130px;font-size:45px;">
                <?= strtoupper(substr($user['name'], 0, 1)) ?>
            </div>

        <?php endif; ?>

    </div>

    <h4 class="text-center mb-1">
        <?= htmlspecialchars($user['name']) ?>
    </h4>

    <p class="text-muted text-center mb-4">
        <?= htmlspecialchars($user['email']) ?>
    </p>

    <hr>

    <h5 class="mb-3">Change Profile Picture</h5>

    <form method="POST" enctype="multipart/form-data">

        <input type="hidden" name="csrf_token"
               value="<?= htmlspecialchars(csrfToken()) ?>">

        <div class="mb-3">
            <label class="form-label">Choose Image</label>

            <input type="file"
                   name="profile_picture"
                   class="form-control"
                   accept="image/jpeg,image/png,image/webp"
                   required>
            <small class="text-muted">
                JPG, PNG or WEBP. Maximum size: 2 MB.
            </small>
        </div>

        <button type="submit" class="btn btn-primary">
            Upload Profile Picture
        </button>

    </form>

</div>

<?php include "includes/footer.php"; ?>
