<?php
require_once dirname(__DIR__) . '/bootstrap.php';

use App\Database;
use App\Helpers;

$base = Helpers::baseUrl();
$config = require dirname(__DIR__) . '/config/app.php';
$currency = $config['currency'] ?? 'GHS';

// Get filter parameters
$brandId = isset($_GET['brand']) ? (int)$_GET['brand'] : 0;
$searchQ = trim($_GET['q'] ?? '');
$priceRange = $_GET['price_range'] ?? '';
$duration = in_array($_GET['duration'] ?? '', ['monthly', 'weekly', 'daily']) ? $_GET['duration'] : 'daily';
$vehicleType = in_array($_GET['type'] ?? '', ['economy', 'luxury', 'suv']) ? $_GET['type'] : '';
$yearFrom = isset($_GET['year_from']) ? (int)$_GET['year_from'] : null;
$yearTo = isset($_GET['year_to']) ? (int)$_GET['year_to'] : null;
$modelFilter = trim($_GET['model'] ?? '');

// Check if vehicle_type column exists
$hasVehicleTypeColumn = (int) Database::run(
    "SELECT COUNT(*) FROM information_schema.COLUMNS 
     WHERE TABLE_SCHEMA = DATABASE() 
     AND TABLE_NAME = 'vehicles' 
     AND COLUMN_NAME = 'vehicle_type'"
)->fetchColumn() > 0;

// Build SQL query
$selectVehicleType = $hasVehicleTypeColumn 
    ? ", COALESCE(v.vehicle_type, 'economy') AS vehicle_type" 
    : ", 'economy' AS vehicle_type";

$sql = 'SELECT v.id, v.model, v.year, v.registration_number, v.price_per_day, 
        v.price_per_week, v.price_per_month, v.fuel_type, v.transmission, 
        v.description, v.primary_image, b.name AS brand_name' 
        . $selectVehicleType . '
        FROM vehicles v
        JOIN brands b ON v.brand_id = b.id
        WHERE v.is_available = 1 AND (v.status = ? OR v.status IS NULL)';
$params = ['available'];

// Brand filter
if ($brandId > 0) {
    $sql .= ' AND v.brand_id = ?';
    $params[] = $brandId;
}

// Price range filter (FIXED - using dropdown instead of number inputs)
if ($priceRange !== '') {
    switch ($priceRange) {
        case '0-100':
            $sql .= ' AND v.price_per_day <= 100';
            break;
        case '100-200':
            $sql .= ' AND v.price_per_day BETWEEN 100 AND 200';
            break;
        case '200-300':
            $sql .= ' AND v.price_per_day BETWEEN 200 AND 300';
            break;
        case '300-500':
            $sql .= ' AND v.price_per_day BETWEEN 300 AND 500';
            break;
        case '500-1000':
            $sql .= ' AND v.price_per_day BETWEEN 500 AND 1000';
            break;
        case '1000-2000':
            $sql .= ' AND v.price_per_day BETWEEN 1000 AND 2000';
            break;
        case '2000+':
            $sql .= ' AND v.price_per_day >= 2000';
            break;
    }
}

// Year filters
if ($yearFrom !== null && $yearFrom > 0) {
    $sql .= ' AND v.year >= ?';
    $params[] = $yearFrom;
}
if ($yearTo !== null && $yearTo > 0) {
    $sql .= ' AND v.year <= ?';
    $params[] = $yearTo;
}

// Model filter
if ($modelFilter !== '') {
    $sql .= ' AND v.model LIKE ?';
    $params[] = '%' . $modelFilter . '%';
}

// Search query
if ($searchQ !== '') {
    $sql .= ' AND (v.model LIKE ? OR v.description LIKE ? OR b.name LIKE ?)';
    $term = '%' . $searchQ . '%';
    $params[] = $term;
    $params[] = $term;
    $params[] = $term;
}

// Vehicle type filter
if ($vehicleType !== '') {
    if ($hasVehicleTypeColumn) {
        $sql .= ' AND v.vehicle_type = ?';
        $params[] = $vehicleType;
    } else {
        if ($vehicleType === 'economy') {
            $sql .= ' AND v.price_per_day < 200';
        } elseif ($vehicleType === 'luxury') {
            $sql .= ' AND v.price_per_day >= 200';
        } elseif ($vehicleType === 'suv') {
            $sql .= ' AND (v.model LIKE ? OR v.description LIKE ?)';
            $params[] = '%SUV%';
            $params[] = '%SUV%';
        }
    }
}

$sql .= ' ORDER BY b.name, v.model';
$vehicles = Database::run($sql, $params)->fetchAll();

// Get brands for filter dropdown
$brands = Database::run('SELECT id, name FROM brands WHERE is_active = 1 ORDER BY name')->fetchAll();

// Get price range for display (min/max)
$priceData = Database::run(
    'SELECT COALESCE(MIN(price_per_day), 0) AS min_price, 
            COALESCE(MAX(price_per_day), 1000) AS max_price 
     FROM vehicles 
     WHERE is_available = 1 AND (status = ? OR status IS NULL)',
    ['available']
)->fetch();
$minPrice = (float)($priceData['min_price'] ?? 0);
$maxPrice = (float)($priceData['max_price'] ?? 1000);

// Generate years for dropdown
$years = [];
$currentYear = (int)date('Y');
for ($y = $currentYear; $y >= $currentYear - 15; $y--) {
    $years[] = $y;
}

$pageTitle = 'Cars for Rent';
require __DIR__ . '/inc/portal_header.php';
?>

<main class="cars-listing-page">
    <div class="container-fluid cars-listing-wrap py-4 px-4 px-lg-5">
        
        <!-- Breadcrumb -->
        <nav aria-label="breadcrumb" class="mb-3">
            <ol class="breadcrumb cars-breadcrumb">
                <li class="breadcrumb-item"><a href="<?= $base ?>/index.php">Home</a></li>
                <li class="breadcrumb-item active" aria-current="page">Cars for Rent</li>
            </ol>
        </nav>
        
        <h1 class="cars-page-title mb-4">Rent a Car – Affordable Car Rental</h1>

        <div class="row">
            <!-- ============================================ -->
            <!-- LEFT SIDEBAR - FILTERS (FIXED VERSION)      -->
            <!-- ============================================ -->
            <aside class="col-lg-3 mb-4 mb-lg-0">
                <div class="cars-sidebar card border-0 shadow-sm">
                    <div class="card-body p-4">
                        <form method="get" id="carsFilterForm">
                            
                            <!-- Hidden search query if present -->
                            <?php if ($searchQ !== ''): ?>
                                <input type="hidden" name="q" value="<?= Helpers::e($searchQ) ?>">
                            <?php endif; ?>

                            <!-- ============================================ -->
                            <!-- PRICE RANGE - FIXED DROPDOWN (NOT THOUSANDS) -->
                            <!-- ============================================ -->
                            <div class="cars-filter-block mb-4">
                                <label class="form-label fw-semibold small text-uppercase text-muted">Price range (<?= Helpers::e($currency) ?>)</label>
                                <select name="price_range" class="form-select form-select-sm">
                                    <option value="">Any price</option>
                                    <option value="0-100" <?= $priceRange === '0-100' ? 'selected' : '' ?>>Under 100</option>
                                    <option value="100-200" <?= $priceRange === '100-200' ? 'selected' : '' ?>>100 - 200</option>
                                    <option value="200-300" <?= $priceRange === '200-300' ? 'selected' : '' ?>>200 - 300</option>
                                    <option value="300-500" <?= $priceRange === '300-500' ? 'selected' : '' ?>>300 - 500</option>
                                    <option value="500-1000" <?= $priceRange === '500-1000' ? 'selected' : '' ?>>500 - 1,000</option>
                                    <option value="1000-2000" <?= $priceRange === '1000-2000' ? 'selected' : '' ?>>1,000 - 2,000</option>
                                    <option value="2000+" <?= $priceRange === '2000+' ? 'selected' : '' ?>>2,000+</option>
                                </select>
                                
                                <!-- Duration selector (daily/weekly/monthly) -->
                                <div class="cars-duration-radio mt-3">
                                    <div class="form-check form-check-inline">
                                        <input class="form-check-input" type="radio" name="duration" id="durDaily" value="daily" <?= $duration === 'daily' ? 'checked' : '' ?>>
                                        <label class="form-check-label small" for="durDaily">Daily</label>
                                    </div>
                                    <div class="form-check form-check-inline">
                                        <input class="form-check-input" type="radio" name="duration" id="durWeekly" value="weekly" <?= $duration === 'weekly' ? 'checked' : '' ?>>
                                        <label class="form-check-label small" for="durWeekly">Weekly</label>
                                    </div>
                                    <div class="form-check form-check-inline">
                                        <input class="form-check-input" type="radio" name="duration" id="durMonthly" value="monthly" <?= $duration === 'monthly' ? 'checked' : '' ?>>
                                        <label class="form-check-label small" for="durMonthly">Monthly</label>
                                    </div>
                                </div>
                            </div>

                            <!-- ============================================ -->
                            <!-- VEHICLE TYPE FILTER                        -->
                            <!-- ============================================ -->
                            <div class="cars-filter-block mb-4">
                                <label class="form-label fw-semibold small text-uppercase text-muted">Vehicle type</label>
                                <div class="d-flex flex-wrap gap-2 cars-vehicle-type-btns">
                                    <input type="radio" class="btn-check" name="type" id="typeAny" value="" <?= $vehicleType === '' ? 'checked' : '' ?>>
                                    <label class="btn btn-outline-secondary btn-sm cars-type-btn" for="typeAny">Any</label>
                                    
                                    <input type="radio" class="btn-check" name="type" id="typeEconomy" value="economy" <?= $vehicleType === 'economy' ? 'checked' : '' ?>>
                                    <label class="btn btn-outline-secondary btn-sm cars-type-btn" for="typeEconomy">
                                        <i class="bi bi-car-front me-1"></i>Economy
                                    </label>
                                    
                                    <input type="radio" class="btn-check" name="type" id="typeLuxury" value="luxury" <?= $vehicleType === 'luxury' ? 'checked' : '' ?>>
                                    <label class="btn btn-outline-secondary btn-sm cars-type-btn" for="typeLuxury">
                                        <i class="bi bi-star me-1"></i>Luxury
                                    </label>
                                    
                                    <input type="radio" class="btn-check" name="type" id="typeSuv" value="suv" <?= $vehicleType === 'suv' ? 'checked' : '' ?>>
                                    <label class="btn btn-outline-secondary btn-sm cars-type-btn" for="typeSuv">
                                        <i class="bi bi-truck me-1"></i>SUV
                                    </label>
                                </div>
                            </div>

                            <!-- ============================================ -->
                            <!-- BRAND FILTER                               -->
                            <!-- ============================================ -->
                            <div class="cars-filter-block mb-3">
                                <label class="form-label small fw-semibold">Brand</label>
                                <select name="brand" class="form-select form-select-sm">
                                    <option value="">All Brands</option>
                                    <?php foreach ($brands as $b): ?>
                                        <option value="<?= (int)$b['id'] ?>" <?= $brandId === (int)$b['id'] ? 'selected' : '' ?>>
                                            <?= Helpers::e($b['name']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <!-- ============================================ -->
                            <!-- MODEL FILTER                               -->
                            <!-- ============================================ -->
                            <div class="cars-filter-block mb-3">
                                <label class="form-label small fw-semibold">Model</label>
                                <input type="text" name="model" class="form-control form-control-sm" 
                                       value="<?= Helpers::e($modelFilter) ?>" 
                                       placeholder="e.g., Camry">
                            </div>

                            <!-- ============================================ -->
                            <!-- YEAR RANGE FILTER                          -->
                            <!-- ============================================ -->
                            <div class="row g-2 mb-4">
                                <div class="col-6">
                                    <label class="form-label small fw-semibold">Year from</label>
                                    <select name="year_from" class="form-select form-select-sm">
                                        <option value="">Any</option>
                                        <?php foreach ($years as $y): ?>
                                            <option value="<?= $y ?>" <?= $yearFrom === $y ? 'selected' : '' ?>><?= $y ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-6">
                                    <label class="form-label small fw-semibold">Year to</label>
                                    <select name="year_to" class="form-select form-select-sm">
                                        <option value="">Any</option>
                                        <?php foreach ($years as $y): ?>
                                            <option value="<?= $y ?>" <?= $yearTo === $y ? 'selected' : '' ?>><?= $y ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>

                            <!-- ============================================ -->
                            <!-- FILTER BUTTONS                             -->
                            <!-- ============================================ -->
                            <div class="d-flex justify-content-between align-items-center">
                                <button type="button" class="btn btn-link btn-sm text-muted p-0 cars-reset-btn" 
                                        onclick="window.location.href='<?= $base ?>/cars.php';">
                                    <i class="bi bi-x-circle me-1"></i>Reset all
                                </button>
                                <button type="submit" class="btn btn-danger btn-sm px-4">
                                    <i class="bi bi-search me-1"></i>Search
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </aside>

            <!-- ============================================ -->
            <!-- RIGHT SIDE - VEHICLE LISTINGS                -->
            <!-- ============================================ -->
            <div class="col-lg-9">
                <div class="cars-list">
                    
                    <?php if (!empty($vehicles)): ?>
                        <?php foreach ($vehicles as $v):
                            $dayRate = (float)$v['price_per_day'];
                            $weekRate = $v['price_per_week'] ? (float)$v['price_per_week'] : $dayRate * 7 * 0.9;
                            $monthRate = $v['price_per_month'] ? (float)$v['price_per_month'] : $dayRate * 30 * 0.85;
                            
                            $imgUrl = !empty($v['primary_image']) 
                                ? $base . '/uploads/vehicles/' . Helpers::e($v['primary_image']) 
                                : 'https://images.unsplash.com/photo-1549317661-bd32c8ce0db2?w=400&auto=format&fit=crop';
                            
                            $categoryLabel = !empty($v['vehicle_type']) 
                                ? ucfirst(Helpers::e($v['vehicle_type'])) 
                                : ($dayRate >= 200 ? 'Luxury' : 'Economy');
                            
                            $tag = $v['transmission'] ? 'Fully ' . Helpers::e($v['transmission']) : 'Car rental';
                        ?>
                        <div class="card cars-card border-0 shadow-sm mb-4 overflow-hidden">
                            <div class="row g-0">
                                <div class="col-md-6 cars-card-img-wrap">
                                    <img src="<?= $imgUrl ?>" alt="<?= Helpers::e($v['brand_name'] . ' ' . $v['model']) ?>" class="cars-card-img">
                                </div>
                                <div class="col-md-6">
                                    <div class="card-body p-4">
                                        <h2 class="cars-card-title">
                                            <?= Helpers::e($v['brand_name']) ?> <?= Helpers::e($v['model']) ?>
                                            <?= (int)$v['year'] ? ' (' . (int)$v['year'] . ')' : '' ?>
                                        </h2>
                                        <p class="cars-card-category text-muted small mb-2"><?= $categoryLabel ?></p>
                                        
                                        <?php if ($tag): ?>
                                            <span class="cars-card-tag"><?= $tag ?></span>
                                        <?php endif; ?>
                                        
                                        <!-- Pricing (NO DISCOUNT LINES - CLEAN) -->
                                        <div class="cars-card-pricing mt-3">
                                            <div class="mt-1">
                                                <strong><?= number_format($dayRate, 0) ?> <?= $currency ?></strong> <span class="text-muted">/Daily</span>
                                                <span class="mx-2">|</span>
                                                <strong><?= number_format($weekRate, 0) ?> <?= $currency ?></strong> <span class="text-muted">/Weekly</span>
                                                <span class="mx-2">|</span>
                                                <strong><?= number_format($monthRate, 0) ?> <?= $currency ?></strong> <span class="text-muted">/Monthly</span>
                                            </div>
                                        </div>
                                        
                                        <!-- Features list -->
                                        <ul class="cars-card-features list-unstyled mt-3 mb-0">
                                            <li><i class="bi bi-check-circle-fill text-success me-2"></i>Minimum Documents Required</li>
                                            <li><i class="bi bi-check-circle-fill text-success me-2"></i>Free Cancellation</li>
                                            <li><i class="bi bi-check-circle-fill text-success me-2"></i>24/7 Roadside Assistance</li>
                                            <li><i class="bi bi-check-circle-fill text-success me-2"></i>Insurance Included</li>
                                        </ul>
                                        
                                        <!-- Action buttons -->
                                        <div class="d-flex flex-wrap gap-2 mt-3">
                                            <a href="<?= $base ?>/book.php?vehicle=<?= (int)$v['id'] ?>" class="btn btn-danger btn-sm">
                                                <i class="bi bi-calendar-check me-1"></i>BOOK NOW
                                            </a>
                                            <a href="<?= $base ?>/contact.php?enquiry=<?= (int)$v['id'] ?>" class="btn btn-outline-danger btn-sm">
                                                <i class="bi bi-envelope me-1"></i>ENQUIRY
                                            </a>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <!-- No results message -->
                        <div class="card border-0 shadow-sm p-5 text-center">
                            <i class="bi bi-car-front display-1 text-muted mb-3"></i>
                            <p class="text-muted mb-0">No vehicles match your filters. Try adjusting the criteria.</p>
                            <a href="<?= $base ?>/cars.php" class="btn btn-outline-danger mt-3">Clear all filters</a>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</main>

<?php require __DIR__ . '/inc/portal_footer.php'; ?>