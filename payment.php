<?php
require_once dirname(__DIR__) . '/bootstrap.php';

use App\Auth;
use App\Csrf;
use App\Database;
use App\Helpers;

Auth::requireLogin();

$bookingId = isset($_GET['booking']) ? (int)$_GET['booking'] : 0;
if ($bookingId < 1) {
    Helpers::flash('error', 'Invalid booking.');
    Helpers::redirect(Helpers::baseUrl() . '/my-bookings.php');
}

$booking = Database::run(
    'SELECT b.*, v.model AS vehicle_model, v.registration_number, u.full_name AS customer_name, u.email AS customer_email
     FROM bookings b
     JOIN vehicles v ON b.vehicle_id = v.id
     JOIN users u ON b.user_id = u.id
     WHERE b.id = ? AND b.user_id = ?',
    [$bookingId, Auth::id()]
)->fetch();
if (!$booking) {
    Helpers::flash('error', 'Booking not found.');
    Helpers::redirect(Helpers::baseUrl() . '/my-bookings.php');
}
if ($booking['payment_status'] === 'paid') {
    Helpers::flash('success', 'This booking is already paid.');
    Helpers::redirect(Helpers::baseUrl() . '/my-bookings.php');
}

$base = Helpers::baseUrl();
$days = (int)($booking['days'] ?? 0);
if ($days < 1 && !empty($booking['pickup_date']) && !empty($booking['return_date'])) {
    $days = max(1, (int)round((strtotime($booking['return_date']) - strtotime($booking['pickup_date'])) / 86400));
}
$totalAmount = (float)($booking['total_amount'] ?? $booking['price_per_day'] * $days);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    Csrf::validateOrAbort();
    $method = $_POST['payment_method'] ?? '';
    if (!in_array($method, ['flutterwave', 'mobile_money', 'card', 'cash'], true)) {
        Helpers::flash('error', 'Please select a payment method.');
    } elseif ($method === 'flutterwave') {
        // Initialize Flutterwave payment (sandbox/live based on keys)
        $payConfig = require dirname(__DIR__) . '/config/payment.php';
        $publicKey = $payConfig['flutterwave_public_key'] ?? '';
        $secretKey = $payConfig['flutterwave_secret_key'] ?? '';
        $currency = $payConfig['currency'] ?? 'GHS';
        if (!$publicKey || !$secretKey) {
            Helpers::flash('error', 'Payment gateway not configured. Contact support.');
        } else {
            $txRef = 'BOOK-' . $bookingId . '-' . time();
            $callbackUrl = $base . '/flutterwave-callback.php';
            $payload = [
                'tx_ref' => $txRef,
                'amount' => $totalAmount,
                'currency' => $currency,
                'redirect_url' => $callbackUrl,
                'customer' => [
                    'email' => $booking['customer_email'] ?? '',
                    'name' => $booking['customer_name'] ?? '',
                ],
                'customizations' => [
                    'title' => 'Car Rental Booking #' . $bookingId,
                    'description' => 'Car rental payment',
                ],
            ];
            $ch = curl_init();
            curl_setopt_array($ch, [
                CURLOPT_URL => 'https://api.flutterwave.com/v3/payments',
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_POST => true,
                CURLOPT_HTTPHEADER => [
                    'Authorization: Bearer ' . $secretKey,
                    'Content-Type: application/json',
                ],
                CURLOPT_POSTFIELDS => json_encode($payload),
            ]);
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            $data = $response ? json_decode($response, true) : null;
            if ($httpCode === 200 && is_array($data) && ($data['status'] ?? '') === 'success') {
                $link = $data['data']['link'] ?? null;
                if ($link) {
                    Helpers::redirect($link);
                }
            }
            Helpers::flash('error', 'Could not start payment. Please try again or contact support.');
        }
    } elseif ($method === 'mobile_money') {
        // Direct Mobile Money (e.g. MTN / Vodafone) placeholder flow:
        // collect wallet details and mark payment as pending for now.
        $walletNumber = trim($_POST['mobile_money_number'] ?? '');
        $provider = $_POST['mobile_money_provider'] ?? '';
        if ($walletNumber === '' || !preg_match('/^[0-9]{8,15}$/', $walletNumber)) {
            Helpers::flash('error', 'Enter a valid mobile money number.');
        } elseif (!in_array($provider, ['mtn', 'vodafone', 'airteltigo'], true)) {
            Helpers::flash('error', 'Select a valid mobile money provider.');
        } else {
            $txnId = 'MOMO-' . time() . '-' . $bookingId;
            Database::run(
                'INSERT INTO payments (booking_id, transaction_id, amount, payment_method, mobile_money_number, mobile_money_provider, payment_status, payment_details) VALUES (?,?,?,?,?,?,?,?)',
                [
                    $bookingId,
                    $txnId,
                    $totalAmount,
                    'mobile_money',
                    $walletNumber,
                    $provider,
                    'pending',
                    json_encode(['note' => 'Awaiting MTN/Vodafone confirmation']),
                ]
            );
            Database::run('UPDATE bookings SET payment_status = ? WHERE id = ?', ['pending', $bookingId]);
            Helpers::flash('success', 'Mobile money request recorded. Please complete the payment on your phone. An admin can confirm once received.');
            Helpers::redirect(Helpers::baseUrl() . '/my-bookings.php');
        }
    } else {
        // Other non-Flutterwave methods: simple local record
        $paymentMethod = $method === 'card' ? 'card' : 'cash';
        $txnId = 'TXN-' . time() . '-' . $bookingId;
        Database::run(
            'INSERT INTO payments (booking_id, transaction_id, amount, payment_method, payment_status, paid_at) VALUES (?,?,?,?,?,NOW())',
            [$bookingId, $txnId, $totalAmount, $paymentMethod, 'success']
        );
        Database::run('UPDATE bookings SET payment_status = ? WHERE id = ?', ['paid', $bookingId]);
        Helpers::flash('success', 'Payment recorded. Your booking is confirmed.');
        Helpers::redirect(Helpers::baseUrl() . '/receipt.php?booking=' . $bookingId);
    }
}
$pageTitle = 'Payment';
require __DIR__ . '/inc/portal_header.php';
?>
<main class="container-fluid px-4 px-lg-5 py-5">
    <h1 class="mb-4">Payment</h1>
    <div class="card mb-4">
        <div class="card-body">
            <p><strong>Booking #<?= (int)$booking['id'] ?></strong> <?= $booking['booking_number'] ? '(' . Helpers::e($booking['booking_number']) . ')' : '' ?> — <?= Helpers::e($booking['vehicle_model']) ?></p>
            <p>Pickup: <?= Helpers::e($booking['pickup_date']) ?> — Return: <?= Helpers::e($booking['return_date']) ?> (<?= $days ?> days)</p>
            <h4>Total: GHS <?= number_format($totalAmount, 2) ?></h4>
        </div>
    </div>
    <?php $flash = Helpers::getFlash(); if ($flash): ?>
        <div class="alert alert-<?= $flash['type'] === 'error' ? 'danger' : $flash['type'] ?>"><?= Helpers::e($flash['message']) ?></div>
    <?php endif; ?>
    <form method="post">
        <?= Csrf::field() ?>
        <div class="mb-3">
            <div class="form-check">
                <input class="form-check-input" type="radio" name="payment_method" id="pm1" value="flutterwave">
                <label class="form-check-label" for="pm1">Flutterwave (Card)</label>
            </div>
        </div>
        <div class="mb-3">
            <div class="form-check">
                <input class="form-check-input" type="radio" name="payment_method" id="pm2" value="mobile_money">
                <label class="form-check-label" for="pm2">Mobile Money (MTN / AirtelTigo / Vodafone)</label>
            </div>
            <div class="row mt-2">
                <div class="col-md-6">
                    <label class="form-label">Mobile money number</label>
                    <input type="text" class="form-control" name="mobile_money_number" placeholder="e.g. 0241234567" value="<?= Helpers::e($_POST['mobile_money_number'] ?? '') ?>">
                </div>
                <div class="col-md-6">
                    <label class="form-label">Provider</label>
                    <select class="form-select" name="mobile_money_provider">
                        <option value="">-- Select provider --</option>
                        <option value="mtn" <?= (($_POST['mobile_money_provider'] ?? '') === 'mtn') ? 'selected' : '' ?>>MTN</option>
                        <option value="vodafone" <?= (($_POST['mobile_money_provider'] ?? '') === 'vodafone') ? 'selected' : '' ?>>Vodafone</option>
                        <option value="airteltigo" <?= (($_POST['mobile_money_provider'] ?? '') === 'airteltigo') ? 'selected' : '' ?>>AirtelTigo</option>
                    </select>
                </div>
            </div>
        </div>
        <div class="mb-3">
            <div class="form-check">
                <input class="form-check-input" type="radio" name="payment_method" id="pm3" value="cash">
                <label class="form-check-label" for="pm3">Cash (pay on pickup)</label>
            </div>
        </div>
        <p class="text-muted small">Flutterwave payments use sandbox/test mode when you provide test keys.</p>
        <button type="submit" class="btn btn-danger">Pay GHS <?= number_format($totalAmount, 2) ?></button>
        <a href="<?= $base ?>/my-bookings.php" class="btn btn-outline-secondary">Back to My Bookings</a>
    </form>
</main>
<?php require __DIR__ . '/inc/portal_footer.php'; ?>
