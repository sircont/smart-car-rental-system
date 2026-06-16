<?php
require_once dirname(dirname(__DIR__)) . '/bootstrap.php';

use App\Auth;
use App\Csrf;
use App\Database;
use App\Helpers;

Auth::requireStaff();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    Csrf::validateOrAbort();
    if (isset($_POST['update_status'])) {
        $id = (int)$_POST['task_id'];
        $status = $_POST['status'] ?? '';
        if (in_array($status, ['scheduled','in_progress','completed','cancelled'], true)) {
            $set = 'status = ?';
            if ($status === 'completed') $set .= ', completed_date = CURDATE()';
            Database::run("UPDATE maintenance SET {$set} WHERE id = ?", [$status, $id]);
            Helpers::flash('success', 'Task updated.');
        }
    }
    Helpers::redirect(Helpers::baseUrl() . '/staff/maintenance.php');
}

$tasks = Database::run(
    'SELECT m.*, v.model AS vehicle_model, v.registration_number, s.full_name AS assigned_name
     FROM maintenance m
     JOIN vehicles v ON m.vehicle_id = v.id
     LEFT JOIN staff s ON m.staff_id = s.id
     ORDER BY m.scheduled_date IS NULL, m.scheduled_date ASC, m.created_at DESC'
)->fetchAll();

$pageTitle = 'Maintenance';
require __DIR__ . '/layout/header.php';
?>
<h1 class="admin-page-title">Maintenance tasks</h1>
<?php $flash = Helpers::getFlash(); if ($flash): ?>
    <div class="admin-alert <?= $flash['type'] === 'error' ? 'danger' : 'success' ?>"><?= Helpers::e($flash['message']) ?></div>
<?php endif; ?>
<div class="admin-table-wrap">
    <div class="table-responsive">
        <table class="table admin-table">
            <thead><tr><th>Vehicle</th><th>Title</th><th>Scheduled</th><th>Assigned</th><th>Status</th><th>Action</th></tr></thead>
            <tbody>
                <?php foreach ($tasks as $t): ?>
                    <tr>
                        <td><?= Helpers::e($t['vehicle_model']) ?> (<?= Helpers::e($t['registration_number'] ?? '—') ?>)</td>
                        <td><?= Helpers::e($t['title']) ?></td>
                        <td><?= $t['scheduled_date'] ? Helpers::e($t['scheduled_date']) : '—' ?></td>
                        <td><?= Helpers::e($t['assigned_name'] ?? '—') ?></td>
                        <td><span class="status-pill <?= $t['status'] === 'completed' ? 'completed' : ($t['status'] === 'in_progress' ? 'waiting' : 'secondary') ?>"><?= Helpers::e($t['status']) ?></span></td>
                        <td>
                            <?php if ($t['status'] !== 'completed' && $t['status'] !== 'cancelled'): ?>
                            <form method="post" class="d-inline">
                                <?= Csrf::field() ?>
                                <input type="hidden" name="task_id" value="<?= (int)$t['id'] ?>">
                                <input type="hidden" name="update_status" value="1">
                                <select name="status" class="form-select form-select-sm d-inline-block w-auto" onchange="this.form.submit()">
                                    <option value="scheduled" <?= $t['status'] === 'scheduled' ? 'selected' : '' ?>>Scheduled</option>
                                    <option value="in_progress" <?= $t['status'] === 'in_progress' ? 'selected' : '' ?>>In progress</option>
                                    <option value="completed" <?= $t['status'] === 'completed' ? 'selected' : '' ?>>Completed</option>
                                    <option value="cancelled" <?= $t['status'] === 'cancelled' ? 'selected' : '' ?>>Cancelled</option>
                                </select>
                            </form>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php if (empty($tasks)): ?><tr><td colspan="6" class="text-muted py-4 text-center">No maintenance tasks.</td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
<?php require __DIR__ . '/layout/footer.php'; ?>
