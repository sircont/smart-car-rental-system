<?php
require_once dirname(__DIR__) . '/bootstrap.php';

use App\Database;
use App\Helpers;

$config = require dirname(__DIR__) . '/config/payment.php';
$secret = $config['flutterwave_secret_key'] ?? '';

// Flutterwave sends status and tx_ref on redirect
$status = $_GET['status'] ?? '';
$txRef = $_GET['tx_ref'] ?? '';
$transactionId = $_GET['transaction_id'] ?? '';

if ($status !== 'successful' || $txRef === '' || !$secret) {
    Helpers::flash('error', 'Payment was not completed.');
    Helpers::redirect(Helpers::baseUrl() . '/my-bookings.php');
}

// tx_ref format: BOOK-{bookingId}-{timestamp}
if (strpos($txRef, 'BOOK-') !== 0) {
    Helpers::flash('error', 'Invalid transaction reference.');
    Helpers::redirect(Helpers::baseUrl() . '/my-bookings.php');
}

$parts = explode('-', $txRef);
$bookingId = isset($parts[1]) ? (int)$parts[1] : 0;
if ($bookingId < 1) {
    Helpers::flash('error', 'Invalid booking reference.');
    Helpers::redirect(Helpers::baseUrl() . '/my-bookings.php');
}

// Verify with Flutterwave API (sandbox/live based on key)
$ch = curl_init();
curl_setopt_array($ch, [
    CURLOPT_URL => 'https://api.flutterwave.com/v3/transactions/' . urlencode($transactionId) . '/verify',
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER => [
        'Authorization: Bearer ' . $secret,
        'Content-Type: application/json',
    ],
]);
$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($httpCode !== 200 || !$response) {
    Helpers::flash('error', 'Could not verify payment. Please contact support.');
    Helpers::redirect(Helpers::baseUrl() . '/my-bookings.php');
}

$data = json_decode($response, true);
if (!is_array($data) || ($data['status'] ?? '') !== 'success') {
    Helpers::flash('error', 'Payment verification failed.');
    Helpers::redirect(Helpers::baseUrl() . '/my-bookings.php');
}

$tx = $data['data'] ?? [];
if (($tx['status'] ?? '') !== 'successful' || (string)($tx['tx_ref'] ?? '') !== $txRef) {
    Helpers::flash('error', 'Payment not successful.');
    Helpers::redirect(Helpers::baseUrl() . '/my-bookings.php');
}

$amount = (float)($tx['amount'] ?? 0);

// Upsert into payments and mark booking paid
Database::run(
    'INSERT INTO payments (booking_id, transaction_id, amount, payment_method, payment_status, paid_at)
     VALUES (?,?,?,?,\"success\",NOW())
     ON DUPLICATE KEY UPDATE amount = VALUES(amount), payment_status = \"success\", paid_at = NOW()',
    [$bookingId, (string)($tx['id'] ?? $transactionId), $amount, 'card']
);
Database::run('UPDATE bookings SET payment_status = ? WHERE id = ?', ['paid', $bookingId]);

Helpers::flash('success', 'Payment successful. Your booking is confirmed.');
Helpers::redirect(Helpers::baseUrl() . '/receipt.php?booking=' . $bookingId);

