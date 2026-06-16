<?php
require_once dirname(dirname(__DIR__)) . '/bootstrap.php';

use App\Auth;
use App\Csrf;
use App\Database;
use App\Helpers;
use App\ActivityLog;

Auth::requireAdmin();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    Csrf::validateOrAbort();
    if (isset($_POST['delete_id'])) {
        $uid = (int)$_POST['delete_id'];
        if ($uid !== Auth::id() || Auth::role() !== 'customer') {
            Database::run('DELETE FROM users WHERE id = ?', [$uid]);
            ActivityLog::log('user_delete', 'users', $uid);
            Helpers::flash('success', 'User deleted.');
        }
    }
    Helpers::redirect(Helpers::baseUrl() . '/admin/users.php');
}

$users = Database::run(
    'SELECT id, full_name, email, phone, address, city, region, is_active, created_at FROM users ORDER BY created_at DESC'
)->fetchAll();

$pageTitle = 'Users';
require __DIR__ . '/layout/header.php';
?>
<h1 class="admin-page-title">Customer accounts</h1>
<?php $flash = Helpers::getFlash(); if ($flash): ?>
    <div class="admin-alert <?= $flash['type'] === 'error' ? 'danger' : 'success' ?>"><?= Helpers::e($flash['message']) ?></div>
<?php endif; ?>
<div class="admin-table-wrap">
    <div class="table-responsive">
        <table class="table admin-table">
            <thead><tr><th>Name</th><th>Email</th><th>Phone</th><th>Location</th><th>Joined</th><th></th></tr></thead>
            <tbody>
                <?php foreach ($users as $u): ?>
                    <tr>
                        <td><?= Helpers::e($u['full_name']) ?></td>
                        <td><?= Helpers::e($u['email']) ?></td>
                        <td><?= Helpers::e($u['phone'] ?? '—') ?></td>
                        <td><?= Helpers::e(trim(($u['city'] ?? '') . ', ' . ($u['region'] ?? ''), ', ') ?: '—') ?></td>
                        <td><?= date('M j, Y', strtotime($u['created_at'])) ?></td>
                        <td>
                            <?php if ((int)$u['id'] !== Auth::id()): ?>
                            <form method="post" class="d-inline" onsubmit="return confirm('Delete this customer?');">
                                <?= Csrf::field() ?>
                                <input type="hidden" name="delete_id" value="<?= (int)$u['id'] ?>">
                                <button type="submit" class="btn btn-admin-outline btn-sm text-danger border-danger">Delete</button>
                            </form>
                            <?php else: ?>
                            <span class="text-muted">—</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php require __DIR__ . '/layout/footer.php'; ?>
