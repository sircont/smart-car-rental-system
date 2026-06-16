<?php
require_once dirname(dirname(__DIR__)) . '/bootstrap.php';

use App\Auth;
use App\Csrf;
use App\Database;
use App\Helpers;

Auth::requireAdmin();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_branch'])) {
    Csrf::validateOrAbort();
    $id = (int) ($_POST['branch_id'] ?? 0);
    if ($id > 0) {
        Database::run('DELETE FROM branches WHERE id = ?', [$id]);
        Helpers::flash('success', 'Branch removed.');
    }
    Helpers::redirect(Helpers::baseUrl() . '/admin/branches.php');
}

try {
    $branches = Database::run('SELECT * FROM branches ORDER BY sort_order ASC, name ASC')->fetchAll();
} catch (\Throwable $e) {
    $branches = [];
}

$flash = Helpers::getFlash();
$pageTitle = 'Branches';
require __DIR__ . '/layout/header.php';
?>
<h1 class="admin-page-title">Branches</h1>
<p class="text-muted mb-3">Pickup and return locations shown to customers on the booking form. Add at least one active branch.</p>
<?php if ($flash): ?>
    <div class="admin-alert <?= $flash['type'] === 'error' ? 'danger' : 'success' ?>"><?= Helpers::e($flash['message']) ?></div>
<?php endif; ?>
<?php if (empty($branches)): ?>
    <div class="admin-alert danger mb-3">The branches table is missing or empty. Run <code>database/migrations/005_branches.sql</code> on your database, or reinstall from <code>schema.sql</code>.</div>
<?php endif; ?>
<p class="mb-3"><a href="<?= Helpers::e(Helpers::baseUrl()) ?>/admin/branch-edit.php" class="btn btn-admin-primary">Add branch</a></p>
<div class="admin-table-wrap">
    <div class="table-responsive">
        <table class="table admin-table">
            <thead>
                <tr><th>Name</th><th>Address</th><th>Phone</th><th>Order</th><th>Active</th><th></th></tr>
            </thead>
            <tbody>
                <?php foreach ($branches as $b): ?>
                    <tr>
                        <td><strong><?= Helpers::e($b['name']) ?></strong></td>
                        <td><?= Helpers::e(mb_substr($b['address'] ?? '', 0, 80)) ?><?= mb_strlen($b['address'] ?? '') > 80 ? '…' : '' ?></td>
                        <td><?= Helpers::e($b['phone'] ?? '—') ?></td>
                        <td><?= (int) ($b['sort_order'] ?? 0) ?></td>
                        <td><span class="status-pill <?= (int) ($b['is_active'] ?? 1) ? 'completed' : 'cancelled' ?>"><?= (int) ($b['is_active'] ?? 1) ? 'Yes' : 'No' ?></span></td>
                        <td class="text-nowrap">
                            <a href="<?= Helpers::e(Helpers::baseUrl()) ?>/admin/branch-edit.php?id=<?= (int) $b['id'] ?>" class="btn btn-admin-outline btn-sm">Edit</a>
                            <form method="post" class="d-inline ms-1" onsubmit="return confirm('Delete this branch? Bookings keep the saved location text.');">
                                <?= Csrf::field() ?>
                                <input type="hidden" name="branch_id" value="<?= (int) $b['id'] ?>">
                                <input type="hidden" name="delete_branch" value="1">
                                <button type="submit" class="btn btn-admin-outline btn-sm text-danger border-danger">Delete</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php if (empty($branches)): ?>
                    <tr><td colspan="6" class="text-muted py-4 text-center">No branches yet.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
<?php require __DIR__ . '/layout/footer.php'; ?>
