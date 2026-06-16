<?php
require_once dirname(dirname(__DIR__)) . '/bootstrap.php';

use App\Auth;
use App\Database;
use App\Helpers;

Auth::requireAdmin();

$brands = Database::run('SELECT id, name, description, is_active, created_at FROM brands ORDER BY name')->fetchAll();

$pageTitle = 'Brands';
require __DIR__ . '/layout/header.php';
?>
<h1 class="admin-page-title">Brands</h1>
<p class="text-muted mb-3">Vehicle brands. Add or edit brands in the database if needed; vehicles use these for the brand dropdown.</p>
<div class="admin-table-wrap">
    <div class="table-responsive">
        <table class="table admin-table">
            <thead>
                <tr><th>Name</th><th>Description</th><th>Active</th><th>Added</th></tr>
            </thead>
            <tbody>
                <?php foreach ($brands as $b): ?>
                    <tr>
                        <td><?= Helpers::e($b['name']) ?></td>
                        <td><?= Helpers::e(mb_substr($b['description'] ?? '', 0, 60)) ?><?= mb_strlen($b['description'] ?? '') > 60 ? '…' : '' ?></td>
                        <td><span class="status-pill <?= (int)($b['is_active'] ?? 1) ? 'completed' : 'cancelled' ?>"><?= (int)($b['is_active'] ?? 1) ? 'Yes' : 'No' ?></span></td>
                        <td><?= date('M j, Y', strtotime($b['created_at'])) ?></td>
                    </tr>
                <?php endforeach; ?>
                <?php if (empty($brands)): ?>
                    <tr><td colspan="4" class="text-muted py-4 text-center">No brands. Run migration 002 to seed default brands.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
<?php require __DIR__ . '/layout/footer.php'; ?>
