<?php
require_once dirname(__DIR__) . '/bootstrap.php';

use App\Auth;
use App\Csrf;
use App\Database;
use App\Helpers;

if (Auth::check()) {
    $url = Auth::isAdmin() ? '/admin/' : (Auth::isStaff() ? '/staff/' : '/index.php');
    Helpers::redirect(Helpers::baseUrl() . $url);
}

$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    Csrf::validateOrAbort();
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($email === '' || $password === '') {
        $errors[] = 'Email and password are required.';
    } else {
        $role = null;
        $userId = null;

        // SSRN: check admin (email), then staff (email), then users (customers)
        $admin = Database::run('SELECT id, password FROM admin WHERE email = ?', [$email])->fetch();
        if ($admin && password_verify($password, $admin['password'])) {
            $role = 'admin';
            $userId = (int)$admin['id'];
            Database::run('UPDATE admin SET last_login = NOW() WHERE id = ?', [$userId]);
        } else {
            $staff = Database::run('SELECT id, password FROM staff WHERE email = ? AND is_active = 1', [$email])->fetch();
            if ($staff && password_verify($password, $staff['password'])) {
                $role = 'staff';
                $userId = (int)$staff['id'];
                Database::run('UPDATE staff SET last_login = NOW() WHERE id = ?', [$userId]);
            } else {
                $user = Database::run('SELECT id, password FROM users WHERE email = ? AND is_active = 1', [$email])->fetch();
                if ($user && password_verify($password, $user['password'])) {
                    $role = 'customer';
                    $userId = (int)$user['id'];
                    Database::run('UPDATE users SET last_login = NOW() WHERE id = ?', [$userId]);
                }
            }
        }

        if ($role && $userId) {
            Auth::login($userId, $role);
            $url = $role === 'admin' ? '/admin/' : ($role === 'staff' ? '/staff/' : '/index.php');
            Helpers::redirect(Helpers::baseUrl() . $url);
        }
        $errors[] = 'Invalid email or password.';
    }
}

$base = Helpers::baseUrl();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login | DriveSmart Rentals</title>
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
                    <h2 class="mb-4">Sign in</h2>
                    <?php if (!empty($errors)): ?>
                        <div class="alert alert-danger"><?= implode('<br>', array_map([Helpers::class, 'e'], $errors)) ?></div>
                    <?php endif; ?>
                    <form method="post">
                        <?= Csrf::field() ?>
                        <div class="mb-3">
                            <label class="form-label">Email</label>
                            <input type="email" class="form-control" name="email"  placeholder="Enter Your Email"value="<?= Helpers::e($_POST['email'] ?? '') ?>" required autofocus>
                        </div>
                        <div class="mb-3">
                            <label class="form-label" for="login-password">Password</label>
                            <div class="input-group password-toggle-wrap">
                                <input type="password" class="form-control" id="login-password" placeholder="Enter Your Password" name="password" required autocomplete="current-password">
                                <button type="button" class="btn btn-outline-secondary password-toggle-btn" aria-label="Show password" aria-pressed="false" title="Show password">
                                    <i class="bi bi-eye" aria-hidden="true"></i>
                                </button>
                            </div>
                        </div>
                        <button type="submit" class="btn btn-danger w-100">Login</button>
                    </form>
                    <p class="mt-2 mb-0 text-center"><a href="<?= $base ?>/forgot-password.php">Forgot password?</a></p>
                    <p class="mt-2 mb-0 text-center"><a href="<?= $base ?>/index.php">Back to home</a></p>
                </div>
            </div>
        </div>
    </div>
</div>
<script src="<?= $base ?>/assets/password-toggle.js"></script>
</body>
</html>
