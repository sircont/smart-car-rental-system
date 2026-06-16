<?php
require_once dirname(dirname(__DIR__)) . '/bootstrap.php';

use App\Auth;
use App\Csrf;
use App\Database;
use App\Helpers;

Auth::requireAdmin();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    Csrf::validateOrAbort();
    if (isset($_POST['update_status'])) {
        $id = (int)$_POST['id'];
        $status = $_POST['status'] ?? '';
        if (in_array($status, ['new','read','replied','closed'], true)) {
            Database::run('UPDATE contact_queries SET status = ? WHERE id = ?', [$status, $id]);
            Helpers::flash('success', 'Status updated.');
        }
    }
    Helpers::redirect(Helpers::baseUrl() . '/admin/contacts.php');
}

$contacts = Database::run('SELECT * FROM contact_queries ORDER BY created_at DESC')->fetchAll();

$pageTitle = 'Contact queries';
require __DIR__ . '/layout/header.php';
?>
<h1 class="admin-page-title">Contact queries</h1>
<?php $flash = Helpers::getFlash(); if ($flash): ?>
    <div class="admin-alert <?= $flash['type'] === 'error' ? 'danger' : 'success' ?>"><?= Helpers::e($flash['message']) ?></div>
<?php endif; ?>
<div class="admin-table-wrap">
    <div class="table-responsive">
        <table class="table admin-table">
            <thead><tr><th>From</th><th>Subject</th><th>Message</th><th>Status</th><th>Date</th><th>Action</th></tr></thead>
            <tbody>
                <?php foreach ($contacts as $c): ?>
                    <tr>
                        <td><?= Helpers::e($c['name']) ?><br><small class="text-muted"><?= Helpers::e($c['email']) ?></small></td>
                        <td><?= Helpers::e($c['subject'] ?? '—') ?></td>
                        <td><?= Helpers::e(mb_substr($c['message'], 0, 80)) ?>...</td>
                        <td><span class="status-pill <?= $c['status'] === 'new' ? 'waiting' : 'secondary' ?>"><?= Helpers::e($c['status']) ?></span></td>
                        <td><?= date('M j, Y', strtotime($c['created_at'])) ?></td>
                        <td>
                            <form method="post" class="d-inline">
                                <?= Csrf::field() ?>
                                <input type="hidden" name="id" value="<?= (int)$c['id'] ?>">
                                <input type="hidden" name="update_status" value="1">
                                <select name="status" class="form-select form-select-sm d-inline-block w-auto" onchange="this.form.submit()">
                                    <option value="new" <?= $c['status'] === 'new' ? 'selected' : '' ?>>New</option>
                                    <option value="read" <?= $c['status'] === 'read' ? 'selected' : '' ?>>Read</option>
                                    <option value="replied" <?= $c['status'] === 'replied' ? 'selected' : '' ?>>Replied</option>
                                    <option value="closed" <?= $c['status'] === 'closed' ? 'selected' : '' ?>>Closed</option>
                                </select>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php if (empty($contacts)): ?><tr><td colspan="6" class="text-muted py-4 text-center">No contact submissions yet.</td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
<?php require __DIR__ . '/layout/footer.php'; ?>
