<?php
require_once dirname(__DIR__) . '/bootstrap.php';

use App\Auth;
use App\Csrf;
use App\Database;
use App\Helpers;

Auth::requireLogin();

$vehicleId = isset($_GET['vehicle']) ? (int)$_GET['vehicle'] : 0;
if ($vehicleId < 1) {
    Helpers::flash('error', 'Invalid vehicle.');
    Helpers::redirect(Helpers::baseUrl() . '/cars.php');
}

$vehicle = Database::run(
    'SELECT v.*, b.name AS brand_name FROM vehicles v JOIN brands b ON v.brand_id = b.id WHERE v.id = ?',
    [$vehicleId]
)->fetch();
if (!$vehicle || !(int)$vehicle['is_available']) {
    Helpers::flash('error', 'Vehicle not found or not available.');
    Helpers::redirect(Helpers::baseUrl() . '/cars.php');
}

$branches = [];
try {
    $branches = Database::run(
        'SELECT id, name, address, phone FROM branches WHERE is_active = 1 ORDER BY sort_order ASC, name ASC'
    )->fetchAll();
} catch (\Throwable $e) {
    $branches = [];
}

$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    Csrf::validateOrAbort();
    $pickup = trim($_POST['pickup_date'] ?? '');
    $return = trim($_POST['return_date'] ?? '');
    $pickupBranchId = (int) ($_POST['pickup_branch_id'] ?? 0);
    $returnBranchId = (int) ($_POST['return_branch_id'] ?? 0);
    $notes = trim($_POST['special_requests'] ?? '') ?: null;
    $driverName = trim($_POST['driver_name'] ?? '') ?: null;
    $driverLicense = trim($_POST['driver_license'] ?? '') ?: null;
    $driverPhone = trim($_POST['driver_phone'] ?? '') ?: null;

    $pickupBranch = null;
    $returnBranch = null;
    if (empty($branches)) {
        $errors[] = 'Pickup locations are not configured yet. Please contact support.';
    } else {
        $pickupBranch = Database::run(
            'SELECT id, name, address, phone FROM branches WHERE id = ? AND is_active = 1',
            [$pickupBranchId]
        )->fetch();
        $returnBranch = Database::run(
            'SELECT id, name, address, phone FROM branches WHERE id = ? AND is_active = 1',
            [$returnBranchId]
        )->fetch();
        if (!$pickupBranch || !$returnBranch) {
            $errors[] = 'Please select a valid pickup branch and return branch.';
        }
    }

    if ($pickup === '' || $return === '') {
        $errors[] = 'Pickup and return dates are required.';
    } else {
        $pickupTs = strtotime($pickup);
        $returnTs = strtotime($return);
        $today = strtotime(date('Y-m-d'));
        if ($pickupTs < $today) {
            $errors[] = 'Pickup date cannot be in the past.';
        }
        if ($returnTs <= $pickupTs) {
            $errors[] = 'Return date must be after pickup date.';
        }
    }

    if (empty($errors) && $pickupBranch && $returnBranch) {
        $pickupDt = $pickup . ' 09:00:00';
        $returnDt = $return . ' 18:00:00';
        $overlap = Database::run(
            'SELECT id FROM bookings WHERE vehicle_id = ? AND booking_status NOT IN (\'cancelled\') AND ((pickup_date <= ? AND return_date >= ?) OR (pickup_date <= ? AND return_date >= ?))',
            [$vehicleId, $returnDt, $pickupDt, $returnDt, $pickupDt]
        )->fetch();
        if ($overlap) {
            $errors[] = 'This vehicle is already booked for part of the selected period.';
        }
    }

    if (empty($errors) && $pickupBranch && $returnBranch) {
        $pickupLocation = Helpers::branchLocationLabel($pickupBranch);
        $returnLocation = Helpers::branchLocationLabel($returnBranch);
        $bookingNumber = 'BR-' . strtoupper(bin2hex(random_bytes(4))) . '-' . date('ymd');
        Database::run(
            'INSERT INTO bookings (user_id, vehicle_id, pickup_location, return_location, pickup_date, return_date, price_per_day, booking_number, payment_status, booking_status, special_requests, driver_name, driver_license, driver_phone) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?)',
            [
                Auth::id(),
                $vehicleId,
                $pickupLocation,
                $returnLocation,
                $pickup . ' 09:00:00',
                $return . ' 18:00:00',
                $vehicle['price_per_day'],
                $bookingNumber,
                'pending',
                'pending',
                $notes,
                $driverName,
                $driverLicense,
                $driverPhone,
            ]
        );
        $bookingId = (int) Database::pdo()->lastInsertId();
        Helpers::flash('success', 'Booking created! Please complete payment to confirm.');
        Helpers::redirect(Helpers::baseUrl() . '/payment.php?booking=' . $bookingId);
    }
}

$base = Helpers::baseUrl();
$minDate = date('Y-m-d');
$defaultBranchId = !empty($branches) ? (int) $branches[0]['id'] : 0;
$pageTitle = 'Complete Your Booking';
require __DIR__ . '/inc/portal_header.php';
?>

<main class="booking-page">
    <div class="container py-5">
        <!-- Breadcrumb -->
        <nav aria-label="breadcrumb" class="mb-4">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="<?= $base ?>/index.php" class="text-decoration-none">Home</a></li>
                <li class="breadcrumb-item"><a href="<?= $base ?>/cars.php" class="text-decoration-none">Cars</a></li>
                <li class="breadcrumb-item active" aria-current="page">Book <?= Helpers::e($vehicle['brand_name']) ?> <?= Helpers::e($vehicle['model']) ?></li>
            </ol>
        </nav>

        <div class="row g-4">
            <!-- LEFT COLUMN - Booking Form -->
            <div class="col-lg-8">
                <div class="card border-0 shadow-sm rounded-4">
                    <div class="card-body p-4 p-lg-5">
                        <h2 class="h3 mb-4">Complete Your Booking</h2>
                        
                        <?php if (!empty($errors)): ?>
                            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                                <i class="bi bi-exclamation-triangle-fill me-2"></i>
                                <?= implode('<br>', array_map([Helpers::class, 'e'], $errors)) ?>
                                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                            </div>
                        <?php endif; ?>
                        
                        <?php if (empty($branches)): ?>
                            <div class="alert alert-warning">
                                <i class="bi bi-info-circle-fill me-2"></i>
                                Branches are not set up yet. Please contact support.
                            </div>
                        <?php endif; ?>

                        <form method="post" id="bookForm">
                            <?= Csrf::field() ?>
                            
                            <!-- Step 1: Dates -->
                            <div class="booking-section mb-4 pb-2">
                                <div class="d-flex align-items-center gap-2 mb-3">
                                    <div class="step-number">1</div>
                                    <h3 class="h5 mb-0">Select Dates</h3>
                                </div>
                                <div class="row g-3">
                                    <div class="col-md-6">
                                        <label class="form-label fw-semibold">
                                            <i class="bi bi-calendar-check text-danger me-1"></i> Pickup Date
                                        </label>
                                        <input type="date" class="form-control form-control-lg" 
                                               name="pickup_date" id="pickup_date" 
                                               value="<?= Helpers::e($_POST['pickup_date'] ?? $minDate) ?>" 
                                               min="<?= $minDate ?>" required>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label fw-semibold">
                                            <i class="bi bi-calendar-x text-danger me-1"></i> Return Date
                                        </label>
                                        <input type="date" class="form-control form-control-lg" 
                                               name="return_date" id="return_date" 
                                               value="<?= Helpers::e($_POST['return_date'] ?? '') ?>" 
                                               min="<?= $minDate ?>" required>
                                    </div>
                                </div>
                            </div>

                            <!-- Step 2: Branches -->
                            <?php if (!empty($branches)): ?>
                            <div class="booking-section mb-4 pb-2">
                                <div class="d-flex align-items-center gap-2 mb-3">
                                    <div class="step-number">2</div>
                                    <h3 class="h5 mb-0">Choose Locations</h3>
                                </div>
                                
                                <!-- Pickup Branch -->
                                <div class="mb-4">
                                    <label class="form-label fw-semibold mb-2">
                                        <i class="bi bi-geo-alt-fill text-danger me-1"></i> Pickup Branch
                                    </label>
                                    <div class="row g-3">
                                        <?php foreach ($branches as $br):
                                            $bid = (int) $br['id'];
                                            $selPu = isset($_POST['pickup_branch_id'])
                                                ? (int) $_POST['pickup_branch_id'] === $bid
                                                : ($bid === $defaultBranchId);
                                        ?>
                                        <div class="col-md-6">
                                            <input type="radio" class="btn-check" name="pickup_branch_id" 
                                                   id="pu_<?= $bid ?>" value="<?= $bid ?>" <?= $selPu ? 'checked' : '' ?> required>
                                            <label class="branch-card" for="pu_<?= $bid ?>">
                                                <div class="d-flex justify-content-between align-items-start">
                                                    <div>
                                                        <strong class="d-block mb-1"><?= Helpers::e($br['name']) ?></strong>
                                                        <?php if (!empty($br['address'])): ?>
                                                            <small class="text-muted d-block">
                                                                <i class="bi bi-building me-1"></i><?= Helpers::e($br['address']) ?>
                                                            </small>
                                                        <?php endif; ?>
                                                        <?php if (!empty($br['phone'])): ?>
                                                            <small class="text-muted d-block">
                                                                <i class="bi bi-telephone me-1"></i><?= Helpers::e($br['phone']) ?>
                                                            </small>
                                                        <?php endif; ?>
                                                    </div>
                                                    <i class="bi bi-check-circle-fill text-success check-icon"></i>
                                                </div>
                                            </label>
                                        </div>
                                        <?php endforeach; ?>
                                    </div>
                                </div>

                                <!-- Same Branch Checkbox -->
                                <div class="form-check mb-3">
                                    <input class="form-check-input" type="checkbox" id="same_return_branch">
                                    <label class="form-check-label" for="same_return_branch">
                                        <i class="bi bi-arrow-return-left me-1"></i> Return to the same branch
                                    </label>
                                </div>

                                <!-- Return Branch -->
                                <div>
                                    <label class="form-label fw-semibold mb-2">
                                        <i class="bi bi-geo-alt-fill text-danger me-1"></i> Return Branch
                                    </label>
                                    <div class="row g-3" id="return_branch_group">
                                        <?php foreach ($branches as $br):
                                            $bid = (int) $br['id'];
                                            $selRet = isset($_POST['return_branch_id'])
                                                ? (int) $_POST['return_branch_id'] === $bid
                                                : ($bid === $defaultBranchId);
                                        ?>
                                        <div class="col-md-6">
                                            <input type="radio" class="btn-check" name="return_branch_id" 
                                                   id="ret_<?= $bid ?>" value="<?= $bid ?>" <?= $selRet ? 'checked' : '' ?> required>
                                            <label class="branch-card" for="ret_<?= $bid ?>">
                                                <div class="d-flex justify-content-between align-items-start">
                                                    <div>
                                                        <strong class="d-block mb-1"><?= Helpers::e($br['name']) ?></strong>
                                                        <?php if (!empty($br['address'])): ?>
                                                            <small class="text-muted d-block"><?= Helpers::e($br['address']) ?></small>
                                                        <?php endif; ?>
                                                    </div>
                                                    <i class="bi bi-check-circle-fill text-success check-icon"></i>
                                                </div>
                                            </label>
                                        </div>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            </div>
                            <?php endif; ?>

                            <!-- Step 3: Driver Details -->
                            <div class="booking-section mb-4 pb-2">
                                <div class="d-flex align-items-center gap-2 mb-3">
                                    <div class="step-number">3</div>
                                    <h3 class="h5 mb-0">Driver Information</h3>
                                </div>
                                <p class="text-muted small mb-3">
                                    <i class="bi bi-info-circle me-1"></i> If someone else will drive, add their details below.
                                </p>
                                <div class="row g-3">
                                    <div class="col-md-4">
                                        <label class="form-label">Full Name</label>
                                        <input type="text" class="form-control" name="driver_name" 
                                               value="<?= Helpers::e($_POST['driver_name'] ?? '') ?>" 
                                               placeholder="Your name if left blank">
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label">License Number</label>
                                        <input type="text" class="form-control" name="driver_license" 
                                               value="<?= Helpers::e($_POST['driver_license'] ?? '') ?>">
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label">Phone Number</label>
                                        <input type="tel" class="form-control" name="driver_phone" 
                                               value="<?= Helpers::e($_POST['driver_phone'] ?? '') ?>">
                                    </div>
                                </div>
                            </div>

                            <!-- Step 4: Special Requests -->
                            <div class="booking-section mb-4">
                                <div class="d-flex align-items-center gap-2 mb-3">
                                    <div class="step-number">4</div>
                                    <h3 class="h5 mb-0">Special Requests</h3>
                                </div>
                                <textarea class="form-control" name="special_requests" rows="3" 
                                          placeholder="Child seat, GPS navigation, late pickup, etc..."><?= Helpers::e($_POST['special_requests'] ?? '') ?></textarea>
                            </div>

                            <!-- Submit Buttons -->
                            <div class="d-flex gap-3 mt-4">
                                <?php if (!empty($branches)): ?>
                                    <button type="submit" class="btn btn-danger btn-lg px-5">
                                        <i class="bi bi-credit-card me-2"></i>Proceed to Payment
                                    </button>
                                <?php endif; ?>
                                <a href="<?= $base ?>/cars.php" class="btn btn-outline-secondary btn-lg px-4">
                                    <i class="bi bi-arrow-left me-2"></i>Cancel
                                </a>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            <!-- RIGHT COLUMN - Vehicle Summary -->
            <div class="col-lg-4">
                <div class="card border-0 shadow-sm rounded-4 sticky-top" style="top: 20px;">
                    <div class="vehicle-summary-card">
                        <!-- Vehicle Image -->
                        <div class="vehicle-image-wrapper">
                            <?php 
                            $imgUrl = !empty($vehicle['primary_image']) 
                                ? $base . '/uploads/vehicles/' . Helpers::e($vehicle['primary_image']) 
                                : 'https://images.unsplash.com/photo-1549317661-bd32c8ce0db2?w=600&auto=format';
                            ?>
                            <img src="<?= $imgUrl ?>" alt="<?= Helpers::e($vehicle['brand_name'] . ' ' . $vehicle['model']) ?>" class="vehicle-summary-img">
                        </div>
                        
                        <div class="p-4">
                            <h3 class="h4 mb-1"><?= Helpers::e($vehicle['brand_name']) ?> <?= Helpers::e($vehicle['model']) ?></h3>
                            <p class="text-muted mb-3">
                                <i class="bi bi-calendar me-1"></i> <?= (int)$vehicle['year'] ?: 'Current' ?>
                                <span class="mx-2">•</span>
                                <i class="bi bi-fuel-pump me-1"></i> <?= Helpers::e($vehicle['fuel_type'] ?? 'Petrol') ?>
                                <span class="mx-2">•</span>
                                <i class="bi bi-gear me-1"></i> <?= Helpers::e($vehicle['transmission'] ?? 'Manual') ?>
                            </p>
                            
                            <div class="price-summary mb-3">
                                <div class="d-flex justify-content-between align-items-center py-2 border-bottom">
                                    <span>Daily Rate</span>
                                    <strong class="text-danger">GHS <?= number_format((float)$vehicle['price_per_day'], 2) ?></strong>
                                </div>
                                <div class="d-flex justify-content-between align-items-center py-2 border-bottom">
                                    <span>Number of Days</span>
                                    <strong id="daysCount">—</strong>
                                </div>
                                <div class="d-flex justify-content-between align-items-center py-2 pt-3">
                                    <span class="fw-bold">Total Amount</span>
                                    <span class="h4 text-danger mb-0" id="totalAmount">GHS —</span>
                                </div>
                            </div>
                            
                            <div class="features-list mt-3">
                                <h6 class="fw-semibold mb-2">Includes:</h6>
                                <ul class="list-unstyled small">
                                    <li><i class="bi bi-check-circle-fill text-success me-2"></i> Free cancellation up to 24h</li>
                                    <li><i class="bi bi-check-circle-fill text-success me-2"></i> Insurance coverage included</li>
                                    <li><i class="bi bi-check-circle-fill text-success me-2"></i> 24/7 roadside assistance</li>
                                    <li><i class="bi bi-check-circle-fill text-success me-2"></i> No hidden fees</li>
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</main>

<style>
.booking-page {
    background: linear-gradient(135deg, #f5f7fa 0%, #e9edf2 100%);
    min-height: 100vh;
}

.step-number {
    width: 32px;
    height: 32px;
    background: #c41e3a;
    color: white;
    border-radius: 50%;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    font-weight: bold;
    font-size: 14px;
}

.booking-section {
    border-bottom: 1px solid #e9ecef;
}

.branch-card {
    display: block;
    padding: 1rem;
    border: 2px solid #e9ecef;
    border-radius: 12px;
    cursor: pointer;
    transition: all 0.2s ease;
    background: white;
}

.branch-card:hover {
    border-color: #c41e3a;
    background: #fff5f6;
}

input.btn-check:checked + .branch-card {
    border-color: #c41e3a;
    background: #fff5f6;
}

.check-icon {
    opacity: 0;
    transition: opacity 0.2s;
    font-size: 1.2rem;
}

input.btn-check:checked + .branch-card .check-icon {
    opacity: 1;
}

.vehicle-image-wrapper {
    background: linear-gradient(135deg, #1a1a2e 0%, #16213e 100%);
    border-radius: 16px 16px 0 0;
    padding: 2rem;
    text-align: center;
}

.vehicle-summary-img {
    max-height: 180px;
    width: auto;
    object-fit: contain;
}

.sticky-top {
    position: sticky;
    top: 20px;
}

/* Responsive */
@media (max-width: 768px) {
    .sticky-top {
        position: relative;
        top: 0;
        margin-top: 1rem;
    }
    
    .btn-lg {
        width: 100%;
    }
    
    .d-flex.gap-3 {
        flex-direction: column;
    }
}
</style>

<script>
const dailyRate = <?= (float)$vehicle['price_per_day'] ?>;

function updateTotal() {
    const pickup = document.getElementById('pickup_date').value;
    const ret = document.getElementById('return_date').value;
    
    if (!pickup || !ret) {
        document.getElementById('totalDisplay').value = '—';
        document.getElementById('daysCount').innerText = '—';
        document.getElementById('totalAmount').innerHTML = 'GHS —';
        return;
    }
    
    const pickupDate = new Date(pickup);
    const returnDate = new Date(ret);
    const diffTime = Math.abs(returnDate - pickupDate);
    const days = Math.ceil(diffTime / (1000 * 60 * 60 * 24)) || 1;
    const total = days * dailyRate;
    
    document.getElementById('totalDisplay').value = total.toFixed(2);
    document.getElementById('daysCount').innerText = days + ' day' + (days > 1 ? 's' : '');
    document.getElementById('totalAmount').innerHTML = 'GHS ' + total.toFixed(2);
}

document.getElementById('pickup_date').addEventListener('change', updateTotal);
document.getElementById('return_date').addEventListener('change', updateTotal);
updateTotal();

// Same branch functionality
(function(){
    var same = document.getElementById('same_return_branch');
    if (!same) return;
    
    function syncReturnToPickup() {
        if (!same.checked) return;
        var pu = document.querySelector('input[name="pickup_branch_id"]:checked');
        if (!pu) return;
        var ret = document.getElementById('ret_' + pu.value);
        if (ret) { ret.checked = true; }
    }
    
    same.addEventListener('change', syncReturnToPickup);
    document.querySelectorAll('input[name="pickup_branch_id"]').forEach(function(r) {
        r.addEventListener('change', function() { 
            if (same.checked) syncReturnToPickup(); 
        });
    });
})();
</script>

<?php require __DIR__ . '/inc/portal_footer.php'; ?>