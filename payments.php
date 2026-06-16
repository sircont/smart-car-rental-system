<?php
require_once dirname(dirname(__DIR__)) . '/bootstrap.php';

use App\Auth;
use App\Database;
use App\Helpers;

Auth::requireAdmin();

$payments = Database::run(
    'SELECT p.*, b.id AS booking_id, u.full_name AS customer_name, v.model AS vehicle_model, v.registration_number
     FROM payments p
     JOIN bookings b ON p.booking_id = b.id
     JOIN users u ON b.user_id = u.id
     JOIN vehicles v ON b.vehicle_id = v.id
     ORDER BY p.created_at DESC'
)->fetchAll();

$pageTitle = 'Payments';
require __DIR__ . '/layout/header.php';
?>
<h1 class="admin-page-title">Payment monitoring</h1>
<div class="admin-table-wrap">
    <div class="table-responsive">
        <table class="table admin-table">
            <thead><tr><th>ID</th><th>Booking</th><th>Customer</th><th>Vehicle</th><th>Amount (GHS)</th><th>Method</th><th>Transaction</th><th>Status</th><th>Date</th></tr></thead>
            <tbody>
                <?php foreach ($payments as $p): ?>
                    <tr>
                        <td>#<?= (int)$p['id'] ?></td>
                        <td>#<?= (int)$p['booking_id'] ?></td>
                        <td><?= Helpers::e($p['customer_name']) ?></td>
                        <td><?= Helpers::e($p['vehicle_model']) ?> (<?= Helpers::e($p['registration_number'] ?? '—') ?>)</td>
                        <td><?= number_format((float)$p['amount'], 2) ?></td>
                        <td><?= Helpers::e($p['payment_method']) ?></td>
                        <td><?= Helpers::e($p['transaction_id'] ?? '—') ?></td>
                        <td><span class="status-pill <?= $p['payment_status'] === 'success' ? 'completed' : ($p['payment_status'] === 'failed' ? 'cancelled' : 'waiting') ?>"><?= Helpers::e($p['payment_status']) ?></span></td>
                        <td><?= $p['paid_at'] ? date('M j, Y H:i', strtotime($p['paid_at'])) : (date('M j, Y H:i', strtotime($p['created_at']))) ?></td>
                    </tr>
                <?php endforeach; ?>
                <?php if (empty($payments)): ?><tr><td colspan="9" class="text-muted py-4 text-center">No payments recorded yet.</td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
<?php require __DIR__ . '/layout/footer.php'; ?>
