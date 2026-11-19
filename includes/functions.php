
<?php

function logActivity($pdo, $userId, $activity) {
    $stmt = $pdo->prepare(
        "INSERT INTO activity_logs (user_id, activity)
         VALUES (?, ?)"
    );
    $stmt->execute([$userId, $activity]);
}

function setMessage($type, $message) {
    $_SESSION['flash'] = [
        'type' => $type,
        'message' => $message
    ];
}


function showMessage() {
    if (isset($_SESSION['flash'])) {
        $flash = $_SESSION['flash'];

        $allowedTypes = ['success', 'danger', 'warning', 'info'];
        $type = in_array($flash['type'], $allowedTypes, true)
            ? $flash['type']
            : 'info';

        $message = htmlspecialchars(
            $flash['message'],
            ENT_QUOTES,
            'UTF-8'
        );

        echo "
        <div class='alert alert-$type alert-dismissible fade show custom-alert'
             role='alert'>
            <strong>" .
                ($type === 'success' ? 'Success!' :
                ($type === 'danger' ? 'Error!' :
                ($type === 'warning' ? 'Warning!' : 'Notice!'))) .
            "</strong>
            $message

            <button type='button' class='btn-close'
                    data-bs-dismiss='alert'></button>
        </div>";

        unset($_SESSION['flash']);
    }
}

function csrfToken() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['csrf_token'];
}

function verifyCsrf() {
    $token = $_POST['csrf_token'] ?? '';

    if (
        !is_string($token) ||
        !hash_equals($_SESSION['csrf_token'] ?? '', $token)
    ) {
        http_response_code(403);
        exit("Invalid security token. Please refresh the page.");
    }
}

function sendMockEmail($to, $subject, $message) {
    $timestamp = date('Y-m-d H:i:s');
    $logEntry = "========================================\n"
              . "DATE:    $timestamp\n"
              . "TO:      $to\n"
              . "SUBJECT: $subject\n"
              . "MESSAGE:\n$message\n"
              . "========================================\n\n";

    file_put_contents(__DIR__ . '/../email_logs.txt', $logEntry, FILE_APPEND);
    return true;
}
