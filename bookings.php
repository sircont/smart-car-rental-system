<?php
require_once dirname(dirname(__DIR__)) . '/bootstrap.php';

use App\Auth;
use App\Booking;
use App\Csrf;
use App\Database;
use App\Helpers;

Auth::requireStaff();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['confirm_booking_return'])) {
    Csrf::validateOrAbort();
    $bid = (int) ($_POST['booking_id'] ?? 0);
    $row = Database::run('SELECT return_requested_at, booking_status FROM bookings WHERE id = ?', [$bid])->fetch();
    if ($row && $row['booking_status'] !== 'completed'
        && (($row['booking_status'] ?? '') === 'returned' || !empty($row['return_requested_at']))) {
        Booking::markCompleted($bid);
        Helpers::flash('success', 'Return confirmed. Booking completed and vehicle marked available.');
    } else {
        Helpers::flash('error', 'No pending customer return request for this booking.');
    }
    Helpers::redirect(Helpers::baseUrl() . '/staff/bookings.php');
}

$bookings = Database::run(
    'SELECT b.*, u.full_name AS customer_name, u.phone, u.email, v.model AS vehicle_model, v.registration_number, br.name AS brand_name
     FROM bookings b
     JOIN users u ON b.user_id = u.id
     JOIN vehicles v ON b.vehicle_id = v.id
     JOIN brands br ON v.brand_id = br.id
     WHERE b.booking_status IN (\'confirmed\',\'active\',\'returned\',\'completed\') OR b.payment_status = ?
     ORDER BY b.pickup_date DESC, b.id DESC',
    ['paid']
)->fetchAll();

$pageTitle = 'Bookings';
require __DIR__ . '/layout/header.php';
?>
<h1 class="admin-page-title">Bookings</h1>
<?php $flash = Helpers::getFlash(); if ($flash): ?>
    <div class="admin-alert <?= $flash['type'] === 'error' ? 'danger' : 'success' ?>"><?= Helpers::e($flash['message']) ?></div>
<?php endif; ?>
<div class="admin-table-wrap">
    <div class="table-responsive">
        <table class="table admin-table">
            <thead><tr><th>Vehicle</th><th>Pickup</th><th>Return</th><th>Amount (GHS)</th><th>Booking</th><th>Payment</th><th>Customer return</th><th>Action</th></tr></thead>
            <tbody>
                <?php foreach ($bookings as $b): ?>
                    <tr>
                        <td>
                            <div><?= Helpers::e($b['brand_name']) ?> <?= Helpers::e($b['vehicle_model']) ?> (<?= Helpers::e($b['registration_number'] ?? '—') ?>)</div>
                            <small class="text-muted d-block"><?= Helpers::e($b['customer_name']) ?></small>
                            <?php if (!empty($b['booking_number'])): ?>
                                <small class="text-muted d-block"><?= Helpers::e($b['booking_number']) ?></small>
                            <?php endif; ?>
                        </td>
                        <td><?= Helpers::e($b['pickup_date']) ?></td>
                        <td><?= Helpers::e($b['return_date']) ?></td>
                        <td><?= number_format((float)($b['total_amount'] ?? 0), 2) ?></td>
                        <td><span class="status-pill <?= Helpers::e(Helpers::bookingStatusPillClass($b['booking_status'] ?? '')) ?>"><?= Helpers::e(Helpers::humanizeSnake($b['booking_status'] ?? '')) ?></span></td>
                        <td><span class="status-pill <?= Helpers::e(Helpers::paymentStatusPillClass($b['payment_status'] ?? '')) ?>"><?= Helpers::e(Helpers::humanizeSnake($b['payment_status'] ?? '')) ?></span></td>
                        <td>
                            <?php if (($b['booking_status'] ?? '') === 'completed'): ?>
                                <span class="status-pill completed d-inline-block">Return confirmed</span>
                            <?php elseif (($b['booking_status'] ?? '') === 'returned' || (!empty($b['return_requested_at']) && ($b['booking_status'] ?? '') !== 'completed')): ?>
                                <span class="status-pill pending d-inline-block">Return reported</span>
                            <?php else: ?>
                                <span class="text-muted">—</span>
                            <?php endif; ?>
                        </td>
                        <td class="admin-bookings-action-panel">
                            <div class="admin-bookings-staff-action">
                                <span class="admin-bookings-chip-btn"><?= Helpers::e(Helpers::humanizeSnake($b['booking_status'] ?? '')) ?></span>
                                <span class="admin-bookings-chip-btn"><?= Helpers::e(Helpers::humanizeSnake($b['payment_status'] ?? '')) ?></span>
                            </div>
                            <?php if (($b['booking_status'] ?? '') !== 'completed'
                                && (($b['booking_status'] ?? '') === 'returned' || !empty($b['return_requested_at']))): ?>
                                <form method="post" class="admin-bookings-staff-confirm mt-2 mb-0">
                                    <?= Csrf::field() ?>
                                    <input type="hidden" name="booking_id" value="<?= (int)$b['id'] ?>">
                                    <input type="hidden" name="confirm_booking_return" value="1">
                                    <button type="submit" class="btn btn-sm btn-admin-confirm-return">Confirm return</button>
                                </form>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php if (empty($bookings)): ?><tr><td colspan="8" class="text-muted py-4 text-center">No bookings.</td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
<?php require __DIR__ . '/layout/footer.php'; ?>
