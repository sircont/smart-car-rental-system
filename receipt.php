<?php
require_once dirname(__DIR__) . '/bootstrap.php';

use App\Auth;
use App\Database;
use App\Helpers;

Auth::requireLogin();

$bookingId = isset($_GET['booking']) ? (int)$_GET['booking'] : 0;
if ($bookingId < 1) {
    Helpers::flash('error', 'Invalid booking.');
    Helpers::redirect(Helpers::baseUrl() . '/my-bookings.php');
}

$booking = Database::run(
    'SELECT b.*, v.model AS vehicle_model, v.registration_number, br.name AS brand_name
     FROM bookings b
     JOIN vehicles v ON b.vehicle_id = v.id
     JOIN brands br ON v.brand_id = br.id
     WHERE b.id = ? AND b.user_id = ?',
    [$bookingId, Auth::id()]
)->fetch();
if (!$booking) {
    Helpers::flash('error', 'Booking not found.');
    Helpers::redirect(Helpers::baseUrl() . '/my-bookings.php');
}
if ($booking['payment_status'] !== 'paid') {
    Helpers::flash('error', 'No payment recorded for this booking. Receipt is available after payment.');
    Helpers::redirect(Helpers::baseUrl() . '/my-bookings.php');
}

$payments = Database::run(
    'SELECT * FROM payments WHERE booking_id = ? AND payment_status = ? ORDER BY paid_at ASC',
    [$bookingId, 'success']
)->fetchAll();

$config = require dirname(__DIR__) . '/config/app.php';
$siteName = 'DriveSmart Rentals Portal';
$supportEmail = $config['support_email'] ?? 'info@drivesmartrentals.gh';
$supportPhone = $config['support_phone'] ?? '+233 0501029863';
$user = Auth::user();
$customerName = $user['name'] ?? 'Customer';
$receiptNumber = $booking['booking_number'] ?: ('RCP-' . $bookingId);
$base = Helpers::baseUrl();
$pageTitle = 'Payment Receipt';
require __DIR__ . '/inc/portal_header.php';
?>
<main class="container-fluid px-4 px-lg-5 py-5">
    <div class="receipt-container mx-auto" style="max-width: 700px;">
        <div class="d-flex no-print justify-content-between align-items-center mb-4">
            <h1 class="mb-0">Payment Receipt</h1>
            <div class="d-flex gap-2">
                <button type="button" class="btn btn-danger" onclick="window.print();">
                    <i class="bi bi-printer me-1"></i> Print Receipt
                </button>
                <a href="<?= $base ?>/my-bookings.php" class="btn btn-outline-secondary">Back to My Bookings</a>
            </div>
        </div>

        <div id="receipt-content" class="card border shadow-sm">
            <div class="card-body p-4 p-md-5">
                <!-- Receipt header -->
                <div class="text-center border-bottom pb-4 mb-4">
                    <h2 class="mb-1"><?= Helpers::e($siteName) ?></h2>
                    <p class="text-muted small mb-0"><?= Helpers::e($supportEmail) ?> &bull; <?= Helpers::e($supportPhone) ?></p>
                </div>

                <h5 class="text-uppercase text-muted mb-3">Payment Receipt</h5>
                <p class="mb-2"><strong>Receipt No:</strong> <?= Helpers::e($receiptNumber) ?></p>
                <p class="mb-2"><strong>Date:</strong> <?= date('F j, Y \a\t g:i A', strtotime($payments[0]['paid_at'] ?? 'now')) ?></p>
                <p class="mb-4"><strong>Customer:</strong> <?= Helpers::e($customerName) ?></p>

                <!-- Booking details -->
                <table class="table table-bordered mb-4">
                    <thead class="table-light">
                        <tr>
                            <th>Booking Details</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr><td>Vehicle</td><td><?= Helpers::e($booking['brand_name']) ?> <?= Helpers::e($booking['vehicle_model']) ?> (<?= Helpers::e($booking['registration_number'] ?? '—') ?>)</td></tr>
                        <tr><td>Pickup date</td><td><?= Helpers::e($booking['pickup_date']) ?></td></tr>
                        <tr><td>Return date</td><td><?= Helpers::e($booking['return_date']) ?></td></tr>
                        <tr><td>Duration</td><td><?= (int)($booking['days'] ?? 0) ?> days</td></tr>
                        <tr><td>Total amount</td><td><strong>GHS <?= number_format((float)$booking['total_amount'], 2) ?></strong></td></tr>
                    </tbody>
                </table>

                <!-- Payment(s) -->
                <table class="table table-bordered">
                    <thead class="table-light">
                        <tr>
                            <th>Transaction ID</th>
                            <th>Method</th>
                            <th>Amount</th>
                            <th>Date paid</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($payments as $p): ?>
                            <tr>
                                <td><?= Helpers::e($p['transaction_id'] ?? '—') ?></td>
                                <td><?= Helpers::e(ucfirst(str_replace('_', ' ', $p['payment_method'] ?? ''))) ?></td>
                                <td>GHS <?= number_format((float)($p['amount'] ?? 0), 2) ?></td>
                                <td><?= $p['paid_at'] ? date('M j, Y g:i A', strtotime($p['paid_at'])) : '—' ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>

                <p class="text-muted small mt-4 mb-0">Thank you for your payment. Please present this receipt when picking up your vehicle.</p>
            </div>
        </div>
    </div>
</main>
<style>
@media print {
    body * { visibility: hidden; }
    #receipt-content, #receipt-content * { visibility: visible; }
    #receipt-content { position: absolute; left: 0; top: 0; width: 100%; max-width: none; box-shadow: none; border: 1px solid #ddd !important; }
    .no-print, .portal-topbar, .portal-navbar, .portal-footer, .btn, main .d-flex.no-print { display: none !important; }
    main { padding: 0 !important; }
    .receipt-container { max-width: 100% !important; }
}
</style>
<?php require __DIR__ . '/inc/portal_footer.php'; ?>
