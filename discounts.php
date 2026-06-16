<?php
require_once dirname(dirname(__DIR__)) . '/bootstrap.php';

use App\Auth;
use App\Csrf;
use App\Database;
use App\Helpers;

Auth::requireAdmin();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    Csrf::validateOrAbort();
    $id = (int)($_POST['id'] ?? 0);
    if (isset($_POST['save'])) {
        $code = strtoupper(trim($_POST['code'] ?? ''));
        $desc = trim($_POST['description'] ?? '') ?: null;
        $type = in_array($_POST['discount_type'] ?? '', ['percentage', 'fixed'], true) ? $_POST['discount_type'] : 'percentage';
        $value = (float)($_POST['discount_value'] ?? 0);
        $min = $_POST['min_booking_amount'] !== '' ? (float)$_POST['min_booking_amount'] : null;
        $max = $_POST['max_discount_amount'] !== '' ? (float)$_POST['max_discount_amount'] : null;
        $validFrom = trim($_POST['valid_from'] ?? '') ?: null;
        $validTo = trim($_POST['valid_to'] ?? '') ?: null;
        $usageLimit = $_POST['usage_limit'] !== '' ? (int)$_POST['usage_limit'] : null;
        $isActive = isset($_POST['is_active']) ? 1 : 0;
        if ($code !== '' && $value >= 0) {
            if ($id > 0) {
                Database::run(
                    'UPDATE discounts SET code=?, description=?, discount_type=?, discount_value=?, min_booking_amount=?, max_discount_amount=?, valid_from=?, valid_to=?, usage_limit=?, is_active=? WHERE id=?',
                    [$code, $desc, $type, $value, $min, $max, $validFrom, $validTo, $usageLimit, $isActive, $id]
                );
                Helpers::flash('success', 'Discount saved.');
            } else {
                $exists = Database::run('SELECT id FROM discounts WHERE code = ?', [$code])->fetch();
                if ($exists) {
                    Helpers::flash('error', 'A discount with that code already exists.');
                } else {
                    Database::run(
                        'INSERT INTO discounts (code, description, discount_type, discount_value, min_booking_amount, max_discount_amount, valid_from, valid_to, usage_limit, is_active) VALUES (?,?,?,?,?,?,?,?,?,1)',
                        [$code, $desc, $type, $value, $min, $max, $validFrom, $validTo, $usageLimit]
                    );
                    Helpers::flash('success', 'Discount saved.');
                }
            }
        }
    } elseif (isset($_POST['toggle_active']) && $id > 0) {
        Database::run('UPDATE discounts SET is_active = IF(is_active=1,0,1) WHERE id = ?', [$id]);
        Helpers::flash('success', 'Status updated.');
    }
    Helpers::redirect(Helpers::baseUrl() . '/admin/discounts.php');
}

$rows = Database::run('SELECT * FROM discounts ORDER BY created_at DESC')->fetchAll();

$pageTitle = 'Discount codes';
require __DIR__ . '/layout/header.php';
?>
<h1 class="admin-page-title">Discount codes</h1>
<?php $flash = Helpers::getFlash(); if ($flash): ?>
    <div class="admin-alert <?= $flash['type'] === 'error' ? 'danger' : 'success' ?>"><?= Helpers::e($flash['message']) ?></div>
<?php endif; ?>

<div class="row g-4">
    <div class="col-lg-6">
        <div class="card">
            <div class="card-body">
                <h5>Create discount</h5>
                <form method="post" class="row g-3">
                    <?= Csrf::field() ?>
                    <input type="hidden" name="id" value="0">
                    <div class="col-md-4">
                        <label class="form-label">Code</label>
                        <input type="text" name="code" class="form-control" required placeholder="e.g. WELCOME10">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Type</label>
                        <select name="discount_type" class="form-select">
                            <option value="percentage">Percentage</option>
                            <option value="fixed">Fixed amount</option>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Value</label>
                        <input type="number" step="0.01" min="0" name="discount_value" class="form-control" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Min booking amount (GHS)</label>
                        <input type="number" step="0.01" min="0" name="min_booking_amount" class="form-control">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Max discount amount (GHS)</label>
                        <input type="number" step="0.01" min="0" name="max_discount_amount" class="form-control">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Valid from</label>
                        <input type="datetime-local" name="valid_from" class="form-control">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Valid to</label>
                        <input type="datetime-local" name="valid_to" class="form-control">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Usage limit</label>
                        <input type="number" min="0" name="usage_limit" class="form-control" placeholder="Unlimited">
                    </div>
                    <div class="col-12">
                        <label class="form-label">Description (optional)</label>
                        <textarea name="description" class="form-control" rows="2"></textarea>
                    </div>
                    <div class="col-12">
                        <button type="submit" name="save" class="btn btn-admin-primary">Save discount</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <div class="col-lg-6">
        <div class="admin-table-wrap">
            <div class="table-responsive">
                <table class="table admin-table">
                    <thead><tr><th>Code</th><th>Type</th><th>Value</th><th>Used</th><th>Status</th><th>Action</th></tr></thead>
                    <tbody>
                    <?php foreach ($rows as $r): ?>
                        <tr>
                            <td><strong><?= Helpers::e($r['code']) ?></strong></td>
                            <td><?= Helpers::e($r['discount_type']) ?></td>
                            <td><?= $r['discount_type'] === 'percentage' ? number_format((float)$r['discount_value'], 0) . '%' : 'GHS ' . number_format((float)$r['discount_value'], 2) ?></td>
                            <td><?= (int)$r['used_count'] ?><?= $r['usage_limit'] ? ' / ' . (int)$r['usage_limit'] : '' ?></td>
                            <td><?= $r['is_active'] ? 'Active' : 'Inactive' ?></td>
                            <td>
                                <form method="post" class="d-inline">
                                    <?= Csrf::field() ?>
                                    <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
                                    <button type="submit" name="toggle_active" class="btn btn-admin-outline btn-sm">
                                        <?= $r['is_active'] ? 'Deactivate' : 'Activate' ?>
                                    </button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (empty($rows)): ?>
                        <tr><td colspan="6" class="text-muted py-4 text-center">No discount codes yet.</td></tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
<?php require __DIR__ . '/layout/footer.php'; ?>
