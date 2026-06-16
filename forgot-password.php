<?php
require_once dirname(__DIR__) . '/bootstrap.php';

use App\Auth;
use App\Csrf;
use App\Database;
use App\Helpers;

if (Auth::check()) {
    Helpers::redirect(Helpers::baseUrl() . '/index.php');
}

$sent = false;
$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    Csrf::validateOrAbort();
    $email = trim($_POST['email'] ?? '');
    if ($email === '') {
        $errors[] = 'Email is required.';
    } else {
        // SSRN: only users (customers) have password reset; admin/staff use different tables
        $user = Database::run('SELECT id FROM users WHERE email = ?', [$email])->fetch();
        if ($user) {
            $token = bin2hex(random_bytes(32));
            $expires = date('Y-m-d H:i:s', strtotime('+1 hour'));
            Database::run('INSERT INTO password_resets (email, token, expires_at) VALUES (?,?,?)', [$email, $token, $expires]);
            $resetLink = Helpers::baseUrl() . '/reset-password.php?token=' . urlencode($token);
            if (function_exists('mail') && getenv('SEND_EMAIL')) {
                $subject = 'Password reset - Smart Car Rental';
                $body = "Reset your password: $resetLink (valid 1 hour).";
                @mail($email, $subject, $body);
            }
            $sent = true;
        } else {
            $errors[] = 'No account found with that email.';
        }
    }
}

$base = Helpers::baseUrl();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Forgot Password | DriveSmart Rentals</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light auth-page">
<div class="container">
    <div class="row justify-content-center">
        <div class="col-md-5">
            <div class="card shadow">
                <div class="card-body p-4">
                    <h2 class="mb-4">Forgot password</h2>
                    <?php if ($sent): ?>
                        <div class="alert alert-success">If an account exists for that email, we sent a reset link. Check your inbox (and spam).</div>
                        <p class="mb-0"><a href="<?= $base ?>/login.php">Back to login</a></p>
                    <?php else: ?>
                        <?php if (!empty($errors)): ?>
                            <div class="alert alert-danger"><?= implode('<br>', array_map([Helpers::class, 'e'], $errors)) ?></div>
                        <?php endif; ?>
                        <form method="post">
                            <?= Csrf::field() ?>
                            <div class="mb-3">
                                <label class="form-label">Email</label>
                                <input type="email" class="form-control" name="email" value="<?= Helpers::e($_POST['email'] ?? '') ?>" required>
                            </div>
                            <button type="submit" class="btn btn-primary w-100">Send reset link</button>
                        </form>
                        <p class="mt-3 mb-0 text-center"><a href="<?= $base ?>/login.php">Back to login</a></p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>
</body>
</html>
