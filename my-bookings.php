<?php
require_once dirname(__DIR__) . '/bootstrap.php';

use App\Auth;
use App\Booking;
use App\Csrf;
use App\Database;
use App\Helpers;
use App\Notification;

Auth::requireLogin();
Booking::ensureReturnFlowSchema();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['request_vehicle_return'])) {
    Csrf::validateOrAbort();
    if (Auth::role() !== 'customer') {
        Helpers::flash('error', 'Only customer accounts can report a return from this page.');
        Helpers::redirect(Helpers::baseUrl() . '/my-bookings.php');
    }
    $bid = (int) ($_POST['booking_id'] ?? 0);
    $booking = Database::run(
        'SELECT b.*, v.model AS vehicle_model, br.name AS brand_name
         FROM bookings b
         JOIN vehicles v ON b.vehicle_id = v.id
         JOIN brands br ON v.brand_id = br.id
         WHERE b.id = ? AND b.user_id = ?',
        [$bid, Auth::id()]
    )->fetch();
    if (!$booking || $booking['payment_status'] !== 'paid' || !in_array($booking['booking_status'], ['confirmed', 'active'], true)) {
        Helpers::flash('error', 'This booking is not eligible for return reporting.');
    } elseif (($booking['booking_status'] ?? '') === 'returned' || !empty($booking['return_requested_at'])) {
        Helpers::flash('error', 'You already reported a return. Our team will confirm it soon.');
    } else {
        try {
            Database::run(
                'UPDATE bookings SET booking_status = ?, return_requested_at = NOW() WHERE id = ?',
                ['returned', $bid]
            );
        } catch (\Throwable $e) {
            Helpers::flash('error', 'Could not save your return request. Ask the site admin to run database migrations 004 and 006 (return column + returned status).');
            Helpers::redirect(Helpers::baseUrl() . '/my-bookings.php');
        }
        $vehicleInfo = trim(($booking['brand_name'] ?? '') . ' ' . ($booking['vehicle_model'] ?? ''));
        $customer = Auth::user();
        $customerName = $customer['name'] ?? 'Customer';
        Notification::notifyReturnRequested(
            $bid,
            $customerName,
            $vehicleInfo,
            $booking['booking_number'] ?? null
        );
        Helpers::flash('success', 'Thank you. We notified our team to confirm your return.');
    }
    Helpers::redirect(Helpers::baseUrl() . '/my-bookings.php');
}

$bookings = Database::run(
    'SELECT b.*, v.model AS vehicle_model, v.registration_number, br.name AS brand_name
     FROM bookings b
     JOIN vehicles v ON b.vehicle_id = v.id
     JOIN brands br ON v.brand_id = br.id
     WHERE b.user_id = ?
     ORDER BY b.created_at DESC',
    [Auth::id()]
)->fetchAll();

$base = Helpers::baseUrl();
$pageTitle = 'My Bookings';
require __DIR__ . '/inc/portal_header.php';
?>
<main class="container-fluid px-4 px-lg-5 py-5">
    <h1 class="mb-4">My Bookings</h1>
    <?php $flash = Helpers::getFlash(); if ($flash): ?>
        <div class="alert alert-<?= $flash['type'] === 'error' ? 'danger' : $flash['type'] ?>"><?= Helpers::e($flash['message']) ?></div>
    <?php endif; ?>
    <div class="table-responsive">
        <table class="table table-striped">
            <thead>
                <tr><th>ID</th><th>Vehicle</th><th>Pickup</th><th>Return date</th><th>Amount (GHS)</th><th>Booking status</th><th>Payment</th><th>Return status</th><th>Action</th></tr>
            </thead>
            <tbody>
                <?php foreach ($bookings as $b): ?>
                    <?php
                    $canReportReturn = Auth::role() === 'customer'
                        && $b['payment_status'] === 'paid'
                        && in_array($b['booking_status'], ['confirmed', 'active'], true);
                    $isReturned = ($b['booking_status'] ?? '') === 'returned'
                        || (!empty($b['return_requested_at']) && ($b['booking_status'] ?? '') !== 'completed');
                    ?>
                    <tr>
                        <td><?= (int)$b['id'] ?> <?= $b['booking_number'] ? '(' . Helpers::e($b['booking_number']) . ')' : '' ?></td>
                        <td><?= Helpers::e($b['brand_name']) ?> <?= Helpers::e($b['vehicle_model']) ?> (<?= Helpers::e($b['registration_number'] ?? '—') ?>)</td>
                        <td><?= Helpers::e($b['pickup_date']) ?></td>
                        <td><?= Helpers::e($b['return_date']) ?></td>
                        <td><?= number_format((float)($b['total_amount'] ?? 0), 2) ?></td>
                        <td>
                            <?php
                            $bs = $b['booking_status'] ?? '';
                            $displayStatus = $bs;
                            if ($bs !== 'completed' && $bs !== 'returned' && !empty($b['return_requested_at'])) {
                                $displayStatus = 'returned';
                            }
                            if ($displayStatus === 'completed') {
                                $bg = 'success';
                            } elseif ($displayStatus === 'pending') {
                                $bg = 'warning';
                            } elseif ($displayStatus === 'returned') {
                                $bg = 'info';
                            } elseif (in_array($displayStatus, ['confirmed', 'active'], true)) {
                                $bg = 'primary';
                            } else {
                                $bg = 'secondary';
                            }
                            ?>
                            <span class="badge bg-<?= $bg ?> <?= $displayStatus === 'returned' ? 'text-dark' : '' ?>"><?= Helpers::e(Helpers::humanizeSnake($displayStatus)) ?></span>
                        </td>
                        <td><span class="badge bg-<?= $b['payment_status'] === 'paid' ? 'success' : 'warning' ?>"><?= Helpers::e($b['payment_status']) ?></span></td>
                        <td>
                            <?php if (($b['booking_status'] ?? '') === 'completed'): ?>
                                <span class="badge bg-success">Return confirmed</span>
                            <?php elseif ($isReturned): ?>
                                <span class="badge bg-info text-dark">Awaiting staff confirmation</span>
                            <?php else: ?>
                                <span class="text-muted">—</span>
                            <?php endif; ?>
                        </td>
                        <td class="d-flex flex-wrap gap-1">
                            <?php if ($b['payment_status'] === 'paid'): ?>
                                <a href="<?= $base ?>/receipt.php?booking=<?= (int)$b['id'] ?>" class="btn btn-sm btn-outline-primary" target="_blank"><i class="bi bi-receipt me-1"></i>Print receipt</a>
                            <?php else: ?>
                                <a href="<?= $base ?>/payment.php?booking=<?= (int)$b['id'] ?>" class="btn btn-sm btn-danger">Pay</a>
                            <?php endif; ?>
                            <?php if ($canReportReturn): ?>
                                <form method="post" class="d-inline" onsubmit="return confirm('Report that you have returned the vehicle? Our team will confirm and close the booking.');">
                                    <?= Csrf::field() ?>
                                    <input type="hidden" name="request_vehicle_return" value="1">
                                    <input type="hidden" name="booking_id" value="<?= (int)$b['id'] ?>">
                                    <button type="submit" class="btn btn-sm btn-outline-success"><i class="bi bi-arrow-return-left me-1"></i>I returned the car</button>
                                </form>
                            <?php endif; ?>
                            <?php if ($b['booking_status'] === 'completed'): ?>
                                <a href="<?= $base ?>/feedback.php?booking=<?= (int)$b['id'] ?>" class="btn btn-sm btn-outline-success">Leave feedback</a>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php if (empty($bookings)): ?>
                    <tr><td colspan="9" class="text-muted">No bookings yet. <a href="<?= $base ?>/cars.php">Browse cars</a> to book.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</main>
<?php require __DIR__ . '/inc/portal_footer.php'; ?>
