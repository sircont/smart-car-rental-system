<?php
require_once dirname(dirname(__DIR__)) . '/bootstrap.php';

use App\Auth;
use App\Csrf;
use App\Database;
use App\Helpers;

Auth::requireAdmin();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    Csrf::validateOrAbort();
    if (isset($_POST['approve_id'])) {
        $id = (int)$_POST['approve_id'];
        Database::run('UPDATE testimonials SET is_approved = 1, approved_by = ?, approved_at = NOW() WHERE id = ?', [Auth::id(), $id]);
        Helpers::flash('success', 'Testimonial approved.');
    } elseif (isset($_POST['reject_id'])) {
        $id = (int)$_POST['reject_id'];
        Database::run('UPDATE testimonials SET is_approved = 0, approved_by = NULL, approved_at = NULL WHERE id = ?', [$id]);
        Helpers::flash('success', 'Testimonial unapproved.');
    } elseif (isset($_POST['delete_id'])) {
        $id = (int)$_POST['delete_id'];
        Database::run('DELETE FROM testimonials WHERE id = ?', [$id]);
        Helpers::flash('success', 'Testimonial deleted.');
    }
    Helpers::redirect(Helpers::baseUrl() . '/admin/testimonials.php');
}

$testimonials = Database::run(
    'SELECT t.*, u.full_name AS author_name, u.email AS author_email FROM testimonials t LEFT JOIN users u ON t.user_id = u.id ORDER BY t.created_at DESC'
)->fetchAll();

$pageTitle = 'Testimonials';
require __DIR__ . '/layout/header.php';
?>
<h1 class="admin-page-title">Testimonial moderation</h1>
<?php $flash = Helpers::getFlash(); if ($flash): ?>
    <div class="admin-alert <?= $flash['type'] === 'error' ? 'danger' : 'success' ?>"><?= Helpers::e($flash['message']) ?></div>
<?php endif; ?>
<div class="admin-table-wrap">
    <div class="table-responsive">
        <table class="table admin-table">
            <thead><tr><th>Author</th><th>Rating</th><th>Content</th><th>Approved</th><th>Date</th><th>Action</th></tr></thead>
            <tbody>
                <?php foreach ($testimonials as $t): ?>
                    <tr>
                        <td><?= Helpers::e($t['author_name'] ?? '—') ?><br><small class="text-muted"><?= Helpers::e($t['author_email'] ?? '') ?></small></td>
                        <td><?= (int)$t['rating'] ?> ★</td>
                        <td><?= Helpers::e(mb_substr($t['content'], 0, 100)) ?>...</td>
                        <td><?= (int)$t['is_approved'] ? 'Yes' : 'No' ?></td>
                        <td><?= date('M j, Y', strtotime($t['created_at'])) ?></td>
                        <td>
                            <?php if (!(int)$t['is_approved']): ?>
                            <form method="post" class="d-inline"><input type="hidden" name="approve_id" value="<?= (int)$t['id'] ?>"><?= Csrf::field() ?><button type="submit" class="btn btn-admin-outline btn-sm">Approve</button></form>
                            <?php else: ?>
                            <form method="post" class="d-inline"><input type="hidden" name="reject_id" value="<?= (int)$t['id'] ?>"><?= Csrf::field() ?><button type="submit" class="btn btn-admin-outline btn-sm">Unapprove</button></form>
                            <?php endif; ?>
                            <form method="post" class="d-inline" onsubmit="return confirm('Delete this testimonial?');"><input type="hidden" name="delete_id" value="<?= (int)$t['id'] ?>"><?= Csrf::field() ?><button type="submit" class="btn btn-admin-outline btn-sm text-danger border-danger">Delete</button></form>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php if (empty($testimonials)): ?><tr><td colspan="6" class="text-muted py-4 text-center">No testimonials yet.</td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
<?php require __DIR__ . '/layout/footer.php'; ?>
