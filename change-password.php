<?php
require_once dirname(__DIR__) . '/bootstrap.php';

use App\Auth;
use App\Csrf;
use App\Database;
use App\Helpers;

Auth::requireLogin();

$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    Csrf::validateOrAbort();
    $current = $_POST['current_password'] ?? '';
    $new = $_POST['new_password'] ?? '';
    $confirm = $_POST['confirm_password'] ?? '';
    $role = Auth::role();
    $id = Auth::id();
    $table = $role === 'admin' ? 'admin' : ($role === 'staff' ? 'staff' : 'users');
    $row = Database::run("SELECT password FROM {$table} WHERE id = ?", [$id])->fetch();
    if (!$row || !password_verify($current, $row['password'])) {
        $errors[] = 'Current password is incorrect.';
    } elseif (strlen($new) < 6) {
        $errors[] = 'New password must be at least 6 characters.';
    } elseif ($new !== $confirm) {
        $errors[] = 'New passwords do not match.';
    } else {
        $hash = password_hash($new, PASSWORD_DEFAULT);
        Database::run("UPDATE {$table} SET password = ? WHERE id = ?", [$hash, $id]);
        Helpers::flash('success', 'Password updated.');
        $redirect = $role === 'admin' ? '/admin/' : ($role === 'staff' ? '/staff/' : '/profile.php');
        Helpers::redirect(Helpers::baseUrl() . $redirect);
    }
}

$base = Helpers::baseUrl();
$pageTitle = 'Change password';
require __DIR__ . '/inc/portal_header.php';
?>
<main class="container-fluid px-4 px-lg-5 py-5">
    <h1 class="mb-4">Change password</h1>
    <?php if (!empty($errors)): ?>
        <div class="alert alert-danger"><?= implode('<br>', array_map([Helpers::class, 'e'], $errors)) ?></div>
    <?php endif; ?>
    <div class="card shadow-sm" style="max-width: 400px;">
        <div class="card-body">
            <form method="post">
                <?= Csrf::field() ?>
                <div class="mb-3">
                    <label class="form-label" for="cp-current">Current password</label>
                    <div class="input-group password-toggle-wrap">
                        <input type="password" class="form-control" id="cp-current" name="current_password" required autocomplete="current-password">
                        <button type="button" class="btn btn-outline-secondary password-toggle-btn" aria-label="Show password" aria-pressed="false" title="Show password">
                            <i class="bi bi-eye" aria-hidden="true"></i>
                        </button>
                    </div>
                </div>
                <div class="mb-3">
                    <label class="form-label" for="cp-new">New password</label>
                    <div class="input-group password-toggle-wrap">
                        <input type="password" class="form-control" id="cp-new" name="new_password" required minlength="6" autocomplete="new-password">
                        <button type="button" class="btn btn-outline-secondary password-toggle-btn" aria-label="Show password" aria-pressed="false" title="Show password">
                            <i class="bi bi-eye" aria-hidden="true"></i>
                        </button>
                    </div>
                </div>
                <div class="mb-3">
                    <label class="form-label" for="cp-confirm">Confirm new password</label>
                    <div class="input-group password-toggle-wrap">
                        <input type="password" class="form-control" id="cp-confirm" name="confirm_password" required autocomplete="new-password">
                        <button type="button" class="btn btn-outline-secondary password-toggle-btn" aria-label="Show password" aria-pressed="false" title="Show password">
                            <i class="bi bi-eye" aria-hidden="true"></i>
                        </button>
                    </div>
                </div>
                <button type="submit" class="btn btn-danger">Update password</button>
                <a href="<?= $base ?>/profile.php" class="btn btn-outline-secondary">Cancel</a>
            </form>
        </div>
    </div>
</main>
<?php
$portalPasswordToggle = true;
require __DIR__ . '/inc/portal_footer.php';
?>
