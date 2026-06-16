<?php
require_once dirname(dirname(__DIR__)) . '/bootstrap.php';

use App\Auth;
use App\Csrf;
use App\Database;
use App\Helpers;

Auth::requireAdmin();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_id'])) {
    Csrf::validateOrAbort();
    $id = (int)$_POST['delete_id'];
    Database::run('DELETE FROM vehicles WHERE id = ?', [$id]);
    Helpers::flash('success', 'Vehicle deleted.');
    Helpers::redirect(Helpers::baseUrl() . '/admin/vehicles.php');
}

$vehicles = Database::run(
    'SELECT v.id, v.model, v.year, v.registration_number, v.is_available, v.status, v.price_per_day, b.name AS brand_name
     FROM vehicles v JOIN brands b ON v.brand_id = b.id ORDER BY b.name, v.model'
)->fetchAll();

$pageTitle = 'Vehicles';
require __DIR__ . '/layout/header.php';
?>
<h1 class="admin-page-title">Vehicles</h1>
<?php $flash = Helpers::getFlash(); if ($flash): ?>
    <div class="admin-alert <?= $flash['type'] === 'error' ? 'danger' : 'success' ?>"><?= Helpers::e($flash['message']) ?></div>
<?php endif; ?>
<p class="mb-3"><a href="vehicle-edit.php" class="btn btn-admin-primary">Add vehicle</a></p>
<div class="admin-table-wrap">
    <div class="table-responsive">
        <table class="table admin-table">
            <thead>
                <tr><th>Brand / Model</th><th>Year</th><th>Reg. number</th><th>Price/day (GHS)</th><th>Status</th><th></th></tr>
            </thead>
            <tbody>
                <?php foreach ($vehicles as $v): ?>
                    <tr>
                        <td><?= Helpers::e($v['brand_name']) ?> <?= Helpers::e($v['model']) ?></td>
                        <td><?= (int)$v['year'] ?: '—' ?></td>
                        <td><?= Helpers::e($v['registration_number'] ?? '—') ?></td>
                        <td><?= number_format((float)$v['price_per_day'], 2) ?></td>
                        <td><span class="status-pill <?= (int)$v['is_available'] ? 'completed' : 'cancelled' ?>"><?= (int)$v['is_available'] ? 'Available' : 'Unavailable' ?></span> <?= $v['status'] ? Helpers::e($v['status']) : '' ?></td>
                        <td>
                            <a href="vehicle-edit.php?id=<?= (int)$v['id'] ?>" class="btn btn-admin-outline btn-sm">Edit</a>
                            <form method="post" class="d-inline" onsubmit="return confirm('Delete this vehicle?');">
                                <?= Csrf::field() ?>
                                <input type="hidden" name="delete_id" value="<?= (int)$v['id'] ?>">
                                <button type="submit" class="btn btn-admin-outline btn-sm text-danger border-danger">Delete</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php require __DIR__ . '/layout/footer.php'; ?>
