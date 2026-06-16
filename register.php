<?php
require_once dirname(__DIR__) . '/bootstrap.php';

use App\Auth;
use App\Csrf;
use App\Database;
use App\Helpers;

if (Auth::check()) {
    Helpers::redirect(Helpers::baseUrl() . '/index.php');
}

$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    Csrf::validateOrAbort();
    $fullName = trim($_POST['full_name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '') ?: null;
    $password = $_POST['password'] ?? '';
    $confirm = $_POST['password_confirm'] ?? '';
    $address = trim($_POST['address'] ?? '') ?: null;
    $city = trim($_POST['city'] ?? '') ?: null;
    $region = trim($_POST['region'] ?? '') ?: null;

    if ($fullName === '') $errors[] = 'Full name is required.';
    if ($email === '') $errors[] = 'Email is required.';
    if (strlen($password) < 6) $errors[] = 'Password must be at least 6 characters.';
    if ($password !== $confirm) $errors[] = 'Passwords do not match.';

    if (empty($errors)) {
        $exists = Database::run('SELECT id FROM users WHERE email = ?', [$email])->fetch();
        if ($exists) {
            $errors[] = 'Email already registered.';
        } else {
            $hash = password_hash($password, PASSWORD_DEFAULT);
            // SSRN schema: users table has full_name, no role (customers only)
            Database::run(
                'INSERT INTO users (full_name, email, phone, password, address, city, region) VALUES (?,?,?,?,?,?,?)',
                [$fullName, $email, $phone, $hash, $address, $city, $region]
            );
            Helpers::flash('success', 'Account created. Please log in.');
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
    <title>Register | DriveSmart Rentals</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
    <link href="<?= $base ?>/assets/portal.css" rel="stylesheet">
</head>
<body class="bg-light auth-page">
<div class="container">
    <div class="row justify-content-center">
        <div class="col-md-6">
            <div class="card shadow">
                <div class="card-body p-4">
                    <h2 class="mb-4">Create account</h2>
                    <?php if (!empty($errors)): ?>
                        <div class="alert alert-danger"><?= implode('<br>', array_map([Helpers::class, 'e'], $errors)) ?></div>
                    <?php endif; ?>
                    <form method="post">
                        <?= Csrf::field() ?>
                        <div class="mb-3">
                            <label class="form-label">Full name</label>
                            <input type="text" class="form-control" name="full_name" value="<?= Helpers::e($_POST['full_name'] ?? '') ?>" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Email</label>
                            <input type="email" class="form-control" name="email" value="<?= Helpers::e($_POST['email'] ?? '') ?>" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Phone (optional)</label>
                            <input type="text" class="form-control" name="phone" value="<?= Helpers::e($_POST['phone'] ?? '') ?>" placeholder="e.g. 0201234567">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Address (optional)</label>
                            <input type="text" class="form-control" name="address" value="<?= Helpers::e($_POST['address'] ?? '') ?>">
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">City (optional)</label>
                                <input type="text" class="form-control" name="city" value="<?= Helpers::e($_POST['city'] ?? '') ?>">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Region (optional)</label>
                                <input type="text" class="form-control" name="region" value="<?= Helpers::e($_POST['region'] ?? '') ?>" placeholder="e.g. Greater Accra">
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label" for="reg-password">Password</label>
                            <div class="input-group password-toggle-wrap">
                                <input type="password" class="form-control" id="reg-password" name="password" required minlength="6" autocomplete="new-password">
                                <button type="button" class="btn btn-outline-secondary password-toggle-btn" aria-label="Show password" aria-pressed="false" title="Show password">
                                    <i class="bi bi-eye" aria-hidden="true"></i>
                                </button>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label" for="reg-password-confirm">Confirm password</label>
                            <div class="input-group password-toggle-wrap">
                                <input type="password" class="form-control" id="reg-password-confirm" name="password_confirm" required autocomplete="new-password">
                                <button type="button" class="btn btn-outline-secondary password-toggle-btn" aria-label="Show password" aria-pressed="false" title="Show password">
                                    <i class="bi bi-eye" aria-hidden="true"></i>
                                </button>
                            </div>
                        </div>
                        <button type="submit" class="btn btn-danger w-100">Register</button>
                    </form>
                    <p class="mt-3 mb-0 text-center"><a href="<?= $base ?>/login.php">Already have an account? Log in</a></p>
                </div>
            </div>
        </div>
    </div>
</div>
<script src="<?= $base ?>/assets/password-toggle.js"></script>
</body>
</html>
