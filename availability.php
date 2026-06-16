<?php
/**
 * Real-time availability check (AJAX) — SSRN schema
 * Returns whether a vehicle is available for given date range.
 */
header('Content-Type: application/json');
require_once dirname(dirname(__DIR__)) . '/bootstrap.php';

use App\Database;

$vehicleId = isset($_GET['vehicle_id']) ? (int)$_GET['vehicle_id'] : 0;
$pickup = trim($_GET['pickup_date'] ?? '');
$return = trim($_GET['return_date'] ?? '');

$ok = true;
$message = 'available';
if ($vehicleId < 1 || $pickup === '' || $return === '') {
    $ok = false;
    $message = 'Missing parameters';
} else {
    $pickupTs = strtotime($pickup);
    $returnTs = strtotime($return);
    if ($returnTs <= $pickupTs) {
        $ok = false;
        $message = 'Invalid date range';
    } else {
        $pickupDt = $pickup . ' 00:00:00';
        $returnDt = $return . ' 23:59:59';
        $overlap = Database::run(
            'SELECT id FROM bookings WHERE vehicle_id = ? AND booking_status NOT IN (\'cancelled\') AND ((pickup_date <= ? AND return_date >= ?) OR (pickup_date <= ? AND return_date >= ?))',
            [$vehicleId, $returnDt, $pickupDt, $returnDt, $pickupDt]
        )->fetch();
        if ($overlap) {
            $ok = false;
            $message = 'Vehicle not available for selected dates';
        }
    }
}

echo json_encode(['success' => $ok, 'available' => $ok, 'message' => $message]);
