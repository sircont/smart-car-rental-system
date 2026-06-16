<?php
require_once dirname(dirname(__DIR__)) . '/bootstrap.php';

use App\Auth;
use App\Csrf;
use App\Database;
use App\Helpers;

Auth::requireStaff();

$today = date('Y-m-d');
$userId = Auth::id();
$hasChecklistTable = false;
$items = [];

try {
    Database::run('SELECT 1 FROM daily_checklists LIMIT 1')->fetch();
    $hasChecklistTable = true;
} catch (Throwable $e) {
    $hasChecklistTable = false;
}

if ($hasChecklistTable && $_SERVER['REQUEST_METHOD'] === 'POST') {
    Csrf::validateOrAbort();
    if (isset($_POST['add_item'])) {
        $item = trim($_POST['item_name'] ?? '');
        if ($item !== '') {
            Database::run('INSERT INTO daily_checklists (user_id, checklist_date, item_name, is_done) VALUES (?,?,?,0)', [$userId, $today, $item]);
            Helpers::flash('success', 'Item added.');
        }
    } elseif (isset($_POST['toggle'])) {
        $id = (int)$_POST['id'];
        Database::run('UPDATE daily_checklists SET is_done = NOT is_done WHERE id = ? AND user_id = ? AND checklist_date = ?', [$id, $userId, $today]);
        Helpers::redirect(Helpers::baseUrl() . '/staff/checklist.php');
    } elseif (isset($_POST['delete_id'])) {
        $id = (int)$_POST['delete_id'];
        Database::run('DELETE FROM daily_checklists WHERE id = ? AND user_id = ?', [$id, $userId]);
        Helpers::flash('success', 'Item removed.');
        Helpers::redirect(Helpers::baseUrl() . '/staff/checklist.php');
    }
}

if ($hasChecklistTable) {
    $items = Database::run('SELECT * FROM daily_checklists WHERE user_id = ? AND checklist_date = ? ORDER BY id ASC', [$userId, $today])->fetchAll();
}

$pageTitle = 'Daily checklist';
require __DIR__ . '/layout/header.php';
?>
<h1 class="admin-page-title">Daily checklist — <?= Helpers::e($today) ?></h1>
<?php $flash = Helpers::getFlash(); if ($flash): ?>
    <div class="admin-alert <?= $flash['type'] === 'error' ? 'danger' : 'success' ?>"><?= Helpers::e($flash['message']) ?></div>
<?php endif; ?>
<?php if (!$hasChecklistTable): ?>
    <div class="admin-alert danger">Daily checklist is not configured for this database. The <code>daily_checklists</code> table is not present in the SSRN schema. You can add it manually if needed.</div>
<?php else: ?>
<div class="admin-card mb-4">
    <form method="post">
        <?= Csrf::field() ?>
        <input type="hidden" name="add_item" value="1">
        <div class="row g-2">
            <div class="col">
                <input type="text" name="item_name" class="form-control" placeholder="New task (e.g. Inspect vehicle X)" required>
            </div>
            <div class="col-auto">
                <button type="submit" class="btn btn-admin-primary">Add</button>
            </div>
        </div>
    </form>
</div>
<div class="admin-table-wrap">
    <div class="table-responsive">
        <table class="table admin-table">
            <thead><tr><th width="50">Done</th><th>Task</th><th width="80"></th></tr></thead>
            <tbody>
                <?php foreach ($items as $i): ?>
                    <tr>
                        <td>
                            <form method="post" class="d-inline">
                                <?= Csrf::field() ?>
                                <input type="hidden" name="toggle" value="1">
                                <input type="hidden" name="id" value="<?= (int)$i['id'] ?>">
                                <button type="submit" class="btn btn-sm border-0 bg-transparent p-0">
                                    <i class="bi bi-<?= (int)$i['is_done'] ? 'check-circle-fill text-success' : 'circle' ?>"></i>
                                </button>
                            </form>
                        </td>
                        <td class="<?= (int)$i['is_done'] ? 'text-muted text-decoration-line-through' : '' ?>"><?= Helpers::e($i['item_name']) ?></td>
                        <td>
                            <form method="post" class="d-inline" onsubmit="return confirm('Remove this item?');">
                                <?= Csrf::field() ?>
                                <input type="hidden" name="delete_id" value="<?= (int)$i['id'] ?>">
                                <button type="submit" class="btn btn-admin-outline btn-sm text-danger border-danger">Remove</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php if (empty($items)): ?><tr><td colspan="3" class="text-muted py-4 text-center">No items for today. Add one above.</td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>
<?php require __DIR__ . '/layout/footer.php'; ?>
