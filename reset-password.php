<?php
require_once dirname(__DIR__) . '/bootstrap.php';

use App\Auth;
use App\Csrf;
use App\Database;
use App\Helpers;

if (Auth::check()) {
    Helpers::redirect(Helpers::baseUrl() . '/index.php');
}

$token = trim($_GET['token'] ?? '');
$valid = false;
$errors = [];
if ($token !== '') {
    $row = Database::run(
        'SELECT email FROM password_resets WHERE token = ? AND expires_at > NOW() AND (used = 0 OR used IS NULL)',
        [$token]
    )->fetch();
    $valid = (bool) $row;
}

if (!$valid && $token !== '') {
    $errors[] = 'Invalid or expired reset link.';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $valid) {
    Csrf::validateOrAbort();
    $password = $_POST['password'] ?? '';
    $confirm = $_POST['password_confirm'] ?? '';
    if (strlen($password) < 6) {
        $errors[] = 'Password must be at least 6 characters.';
    } elseif ($password !== $confirm) {
        $errors[] = 'Passwords do not match.';
    } else {
        $row = Database::run('SELECT email FROM password_resets WHERE token = ? AND (used = 0 OR used IS NULL)', [$token])->fetch();
        if ($row) {
            $hash = password_hash($password, PASSWORD_DEFAULT);
            Database::run('UPDATE users SET password = ? WHERE email = ?', [$hash, $row['email']]);
            Database::run('UPDATE password_resets SET used = TRUE WHERE token = ?', [$token]);
            Helpers::flash('success', 'Password updated. You can log in now.');
            Helpers::redirect(Helpers::baseUrl() . '/login.php');
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
    <title>Reset Password | DriveSmart Rentals</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
    <link href="<?= $base ?>/assets/portal.css" rel="stylesheet">
</head>
<body class="bg-light auth-page">
<div class="container">
    <div class="row justify-content-center">
        <div class="col-md-5">
            <div class="card shadow">
                <div class="card-body p-4">
                    <h2 class="mb-4">Reset password</h2>
                    <?php if (!empty($errors)): ?>
                        <div class="alert alert-danger"><?= implode('<br>', array_map([Helpers::class, 'e'], $errors)) ?></div>
                    <?php endif; ?>
                    <?php if ($valid): ?>
                        <form method="post">
                            <?= Csrf::field() ?>
                            <div class="mb-3">
                                <label class="form-label" for="reset-password">New password</label>
                                <div class="input-group password-toggle-wrap">
                                    <input type="password" class="form-control" id="reset-password" name="password" required minlength="6" autocomplete="new-password">
                                    <button type="button" class="btn btn-outline-secondary password-toggle-btn" aria-label="Show password" aria-pressed="false" title="Show password">
                                        <i class="bi bi-eye" aria-hidden="true"></i>
                                    </button>
                                </div>
                            </div>
                            <div class="mb-3">
                                <label class="form-label" for="reset-password-confirm">Confirm password</label>
                                <div class="input-group password-toggle-wrap">
                                    <input type="password" class="form-control" id="reset-password-confirm" name="password_confirm" required autocomplete="new-password">
                                    <button type="button" class="btn btn-outline-secondary password-toggle-btn" aria-label="Show password" aria-pressed="false" title="Show password">
                                        <i class="bi bi-eye" aria-hidden="true"></i>
                                    </button>
                                </div>
                            </div>
                            <button type="submit" class="btn btn-primary w-100">Update password</button>
                        </form>
                    <?php endif; ?>
                    <p class="mt-3 mb-0 text-center"><a href="<?= $base ?>/login.php">Back to login</a></p>
                </div>
            </div>
        </div>
    </div>
</div>
<script src="<?= $base ?>/assets/password-toggle.js"></script>
</body>
</html>
