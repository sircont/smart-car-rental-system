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
        $name = trim($_POST['name'] ?? '');
        $price = (float)($_POST['price_per_day'] ?? 0);
        $desc = trim($_POST['description'] ?? '') ?: null;
        $coverage = $_POST['coverage_amount'] !== '' ? (float)$_POST['coverage_amount'] : null;
        $excess = $_POST['excess_amount'] !== '' ? (float)$_POST['excess_amount'] : null;
        $isActive = isset($_POST['is_active']) ? 1 : 0;
        if ($name !== '' && $price >= 0) {
            if ($id > 0) {
                Database::run(
                    'UPDATE insurance_options SET name=?, description=?, price_per_day=?, coverage_amount=?, excess_amount=?, is_active=? WHERE id=?',
                    [$name, $desc, $price, $coverage, $excess, $isActive, $id]
                );
            } else {
                Database::run(
                    'INSERT INTO insurance_options (name, description, price_per_day, coverage_amount, excess_amount, is_active) VALUES (?,?,?,?,?,1)',
                    [$name, $desc, $price, $coverage, $excess]
                );
            }
            Helpers::flash('success', 'Insurance option saved.');
        }
    } elseif (isset($_POST['toggle_active']) && $id > 0) {
        Database::run('UPDATE insurance_options SET is_active = IF(is_active=1,0,1) WHERE id = ?', [$id]);
        Helpers::flash('success', 'Status updated.');
    }
    Helpers::redirect(Helpers::baseUrl() . '/admin/insurance.php');
}

$rows = Database::run('SELECT * FROM insurance_options ORDER BY is_active DESC, price_per_day ASC')->fetchAll();

$pageTitle = 'Insurance options';
require __DIR__ . '/layout/header.php';
?>
<h1 class="admin-page-title">Insurance options</h1>
<?php $flash = Helpers::getFlash(); if ($flash): ?>
    <div class="admin-alert <?= $flash['type'] === 'error' ? 'danger' : 'success' ?>"><?= Helpers::e($flash['message']) ?></div>
<?php endif; ?>

<div class="row g-4">
    <div class="col-lg-5">
        <div class="card">
            <div class="card-body">
                <h5>Add new option</h5>
                <form method="post" class="row g-3">
                    <?= Csrf::field() ?>
                    <input type="hidden" name="id" value="0">
                    <div class="col-12">
                        <label class="form-label">Name</label>
                        <input type="text" name="name" class="form-control" required>
                    </div>
                    <div class="col-12">
                        <label class="form-label">Description (optional)</label>
                        <textarea name="description" class="form-control" rows="2"></textarea>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Price per day (GHS)</label>
                        <input type="number" step="0.01" min="0" name="price_per_day" class="form-control" required>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Coverage (GHS)</label>
                        <input type="number" step="0.01" min="0" name="coverage_amount" class="form-control">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Excess (GHS)</label>
                        <input type="number" step="0.01" min="0" name="excess_amount" class="form-control">
                    </div>
                    <div class="col-12">
                        <button type="submit" name="save" class="btn btn-admin-primary">Save option</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <div class="col-lg-7">
        <div class="admin-table-wrap">
            <div class="table-responsive">
                <table class="table admin-table">
                    <thead><tr><th>Name</th><th>Price/day</th><th>Coverage</th><th>Excess</th><th>Status</th><th>Action</th></tr></thead>
                    <tbody>
                    <?php foreach ($rows as $r): ?>
                        <tr>
                            <td><?= Helpers::e($r['name']) ?></td>
                            <td>GHS <?= number_format((float)$r['price_per_day'], 2) ?></td>
                            <td><?= $r['coverage_amount'] !== null ? 'GHS ' . number_format((float)$r['coverage_amount'], 2) : '—' ?></td>
                            <td><?= $r['excess_amount'] !== null ? 'GHS ' . number_format((float)$r['excess_amount'], 2) : '—' ?></td>
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
                        <tr><td colspan="6" class="text-muted py-4 text-center">No insurance options yet.</td></tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
<?php require __DIR__ . '/layout/footer.php'; ?>
