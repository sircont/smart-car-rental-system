<?php
require_once dirname(dirname(__DIR__)) . '/bootstrap.php';

use App\Auth;
use App\Csrf;
use App\Database;
use App\Helpers;

Auth::requireAdmin();

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$vehicle = null;
if ($id > 0) {
    $vehicle = Database::run('SELECT * FROM vehicles WHERE id = ?', [$id])->fetch();
    if (!$vehicle) {
        Helpers::flash('error', 'Vehicle not found.');
        Helpers::redirect(Helpers::baseUrl() . '/admin/vehicles.php');
    }
}

$brands = Database::run('SELECT id, name FROM brands ORDER BY name')->fetchAll();

$hasVehicleTypeColumn = (int) Database::run(
    "SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'vehicles' AND COLUMN_NAME = 'vehicle_type'"
)->fetchColumn() > 0;

$uploadDir = dirname(__DIR__) . '/uploads/vehicles';
$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    Csrf::validateOrAbort();
    $brand_id = (int)($_POST['brand_id'] ?? 0);
    $model = trim($_POST['model'] ?? '');
    $year = (int)($_POST['year'] ?? 0);
    $registration_number = trim($_POST['registration_number'] ?? '') ?: null;
    $vin = trim($_POST['vin'] ?? '') ?: null;
    $color = trim($_POST['color'] ?? '') ?: null;
    $seats = (int)($_POST['seats'] ?? 0) ?: null;
    $price_per_day = (float)($_POST['price_per_day'] ?? 0);
    $price_per_week = $_POST['price_per_week'] !== '' ? (float)$_POST['price_per_week'] : null;
    $price_per_month = $_POST['price_per_month'] !== '' ? (float)$_POST['price_per_month'] : null;
    $security_deposit = $_POST['security_deposit'] !== '' ? (float)$_POST['security_deposit'] : 0.0;
    $mileage_limit_per_day = $_POST['mileage_limit_per_day'] !== '' ? (int)$_POST['mileage_limit_per_day'] : 100;
    $excess_mileage_charge = $_POST['excess_mileage_charge'] !== '' ? (float)$_POST['excess_mileage_charge'] : 0.50;
    $mileage = $_POST['mileage'] !== '' ? (int)$_POST['mileage'] : 0;
    $fuel_type = trim($_POST['fuel_type'] ?? '') ?: 'Petrol';
    $transmission = trim($_POST['transmission'] ?? '') ?: 'Manual';
    $featuresRaw = trim($_POST['features'] ?? '');
    $features = $featuresRaw !== '' ? json_encode(array_values(array_filter(array_map('trim', explode(',', $featuresRaw))))) : null;
    $description = trim($_POST['description'] ?? '') ?: null;
    $location_address = trim($_POST['location_address'] ?? '') ?: null;
    $is_available = isset($_POST['is_available']) ? 1 : 0;
    $status = in_array($_POST['status'] ?? '', ['available','rented','maintenance','retired'], true) ? $_POST['status'] : 'available';
    $vehicle_type = $hasVehicleTypeColumn && in_array($_POST['vehicle_type'] ?? '', ['economy', 'luxury', 'suv'], true) ? $_POST['vehicle_type'] : 'economy';

    $primary_image = $vehicle['primary_image'] ?? null;

    if ($brand_id < 1) $errors[] = 'Select a brand.';
    if ($model === '') $errors[] = 'Model is required.';
    if ($year > 0 && ($year < 1990 || $year > (int)date('Y') + 1)) $errors[] = 'Invalid year.';
    if ($price_per_day <= 0) $errors[] = 'Price per day must be greater than 0.';

    if (empty($errors) && $registration_number !== null) {
        $check = Database::run('SELECT id FROM vehicles WHERE registration_number = ? AND id != ?', [$registration_number, $id])->fetch();
        if ($check) $errors[] = 'Registration number already in use.';
    }

    if (empty($errors) && !empty($_FILES['primary_image']['name']) && $_FILES['primary_image']['error'] === UPLOAD_ERR_OK) {
        $allowed = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
        $maxSize = 5 * 1024 * 1024;
        $fi = $_FILES['primary_image'];
        if ($fi['size'] > $maxSize) {
            $errors[] = 'Car image must be 5 MB or smaller.';
        } elseif (!in_array($fi['type'], $allowed, true)) {
            $errors[] = 'Please upload a JPEG, PNG, GIF or WebP image.';
        } else {
            if (!is_dir($uploadDir)) {
                @mkdir($uploadDir, 0755, true);
            }
            $ext = strtolower(pathinfo($fi['name'], PATHINFO_EXTENSION) ?: 'jpg');
            $ext = preg_replace('/[^a-z0-9]/', '', $ext) ?: 'jpg';
            if (!in_array($ext, ['jpeg', 'jpg', 'png', 'gif', 'webp'], true)) $ext = 'jpg';
            $newName = 'v_' . ($id ?: 'new') . '_' . time() . '.' . $ext;
            $path = $uploadDir . '/' . $newName;
            if (move_uploaded_file($fi['tmp_name'], $path)) {
                if ($primary_image && is_file($uploadDir . '/' . $primary_image)) {
                    @unlink($uploadDir . '/' . $primary_image);
                }
                $primary_image = $newName;
            } else {
                $errors[] = 'Could not save the uploaded image.';
            }
        }
    }

    if (empty($errors)) {
        if ($hasVehicleTypeColumn) {
            if ($id > 0) {
                Database::run(
                    'UPDATE vehicles SET brand_id=?, model=?, year=?, registration_number=?, vin=?, color=?, fuel_type=?, transmission=?, vehicle_type=?, seats=?, price_per_day=?, price_per_week=?, price_per_month=?, security_deposit=?, mileage=?, mileage_limit_per_day=?, excess_mileage_charge=?, description=?, features=?, location_address=?, is_available=?, status=?, primary_image=? WHERE id=?',
                    [
                        $brand_id,
                        $model,
                        $year ?: null,
                        $registration_number,
                        $vin,
                        $color,
                        $fuel_type,
                        $transmission,
                        $vehicle_type,
                        $seats,
                        $price_per_day,
                        $price_per_week,
                        $price_per_month,
                        $security_deposit,
                        $mileage,
                        $mileage_limit_per_day,
                        $excess_mileage_charge,
                        $description,
                        $features,
                        $location_address,
                        $is_available,
                        $status,
                        $primary_image,
                        $id
                    ]
                );
            } else {
                Database::run(
                    'INSERT INTO vehicles (brand_id, model, year, registration_number, vin, color, fuel_type, transmission, vehicle_type, seats, price_per_day, price_per_week, price_per_month, security_deposit, mileage, mileage_limit_per_day, excess_mileage_charge, description, features, location_address, is_available, status, primary_image) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)',
                    [
                        $brand_id,
                        $model,
                        $year ?: null,
                        $registration_number,
                        $vin,
                        $color,
                        $fuel_type,
                        $transmission,
                        $vehicle_type,
                        $seats,
                        $price_per_day,
                        $price_per_week,
                        $price_per_month,
                        $security_deposit,
                        $mileage,
                        $mileage_limit_per_day,
                        $excess_mileage_charge,
                        $description,
                        $features,
                        $location_address,
                        $is_available,
                        $status,
                        $primary_image
                    ]
                );
            }
        } else {
            if ($id > 0) {
                Database::run(
                    'UPDATE vehicles SET brand_id=?, model=?, year=?, registration_number=?, vin=?, color=?, fuel_type=?, transmission=?, seats=?, price_per_day=?, price_per_week=?, price_per_month=?, security_deposit=?, mileage=?, mileage_limit_per_day=?, excess_mileage_charge=?, description=?, features=?, location_address=?, is_available=?, status=?, primary_image=? WHERE id=?',
                    [
                        $brand_id,
                        $model,
                        $year ?: null,
                        $registration_number,
                        $vin,
                        $color,
                        $fuel_type,
                        $transmission,
                        $seats,
                        $price_per_day,
                        $price_per_week,
                        $price_per_month,
                        $security_deposit,
                        $mileage,
                        $mileage_limit_per_day,
                        $excess_mileage_charge,
                        $description,
                        $features,
                        $location_address,
                        $is_available,
                        $status,
                        $primary_image,
                        $id
                    ]
                );
            } else {
                Database::run(
                    'INSERT INTO vehicles (brand_id, model, year, registration_number, vin, color, fuel_type, transmission, seats, price_per_day, price_per_week, price_per_month, security_deposit, mileage, mileage_limit_per_day, excess_mileage_charge, description, features, location_address, is_available, status, primary_image) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)',
                    [
                        $brand_id,
                        $model,
                        $year ?: null,
                        $registration_number,
                        $vin,
                        $color,
                        $fuel_type,
                        $transmission,
                        $seats,
                        $price_per_day,
                        $price_per_week,
                        $price_per_month,
                        $security_deposit,
                        $mileage,
                        $mileage_limit_per_day,
                        $excess_mileage_charge,
                        $description,
                        $features,
                        $location_address,
                        $is_available,
                        $status,
                        $primary_image
                    ]
                );
            }
        }
        Helpers::flash('success', $id > 0 ? 'Vehicle updated.' : 'Vehicle added.');
        Helpers::redirect(Helpers::baseUrl() . '/admin/vehicles.php');
    }
}

$pageTitle = $id ? 'Edit Vehicle' : 'Add Vehicle';
require __DIR__ . '/layout/header.php';
?>
<h1 class="admin-page-title"><?= $id ? 'Edit' : 'Add' ?> Vehicle</h1>
<?php if (!empty($errors)): ?>
    <div class="admin-alert danger"><?= implode('<br>', array_map([Helpers::class, 'e'], $errors)) ?></div>
<?php endif; ?>
<form method="post" enctype="multipart/form-data">
    <?= Csrf::field() ?>
    <div class="row g-3">
        <div class="col-12">
            <label class="form-label">Car picture</label>
            <?php
            $currentImage = $vehicle['primary_image'] ?? null;
            $imageUrl = $currentImage ? Helpers::baseUrl() . '/uploads/vehicles/' . Helpers::e($currentImage) : null;
            ?>
            <?php if ($imageUrl): ?>
                <div class="mb-2">
                    <img src="<?= $imageUrl ?>" alt="Current" class="vehicle-edit-preview img-thumbnail">
                    <p class="small text-muted mt-1">Current image. Upload a new file to replace.</p>
                </div>
            <?php endif; ?>
            <input type="file" class="form-control" name="primary_image" accept="image/jpeg,image/png,image/gif,image/webp">
            <small class="text-muted">JPEG, PNG, GIF or WebP. Max 5 MB. Optional.</small>
        </div>
        <div class="col-md-4">
            <label class="form-label">Brand</label>
            <select class="form-select" name="brand_id" required>
                <option value="">-- Select --</option>
                <?php foreach ($brands as $b): ?>
                    <option value="<?= (int)$b['id'] ?>" <?= (int)($vehicle['brand_id'] ?? $_POST['brand_id'] ?? 0) === (int)$b['id'] ? 'selected' : '' ?>><?= Helpers::e($b['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-4">
            <label class="form-label">Model</label>
            <input type="text" class="form-control" name="model" value="<?= Helpers::e($vehicle['model'] ?? $_POST['model'] ?? '') ?>" required>
        </div>
        <div class="col-md-2">
            <label class="form-label">Year</label>
            <input type="number" class="form-control" name="year" value="<?= Helpers::e($vehicle['year'] ?? $_POST['year'] ?? '') ?>" min="1990" max="<?= date('Y')+1 ?>" placeholder="Optional">
        </div>
        <div class="col-md-4">
            <label class="form-label">Registration number (optional)</label>
            <input type="text" class="form-control" name="registration_number" value="<?= Helpers::e($vehicle['registration_number'] ?? $_POST['registration_number'] ?? '') ?>">
        </div>
        <div class="col-md-4">
            <label class="form-label">VIN / Chassis number (optional)</label>
            <input type="text" class="form-control" name="vin" value="<?= Helpers::e($vehicle['vin'] ?? $_POST['vin'] ?? '') ?>">
        </div>
        <div class="col-md-3">
            <label class="form-label">Color (optional)</label>
            <input type="text" class="form-control" name="color" value="<?= Helpers::e($vehicle['color'] ?? $_POST['color'] ?? '') ?>">
        </div>
        <div class="col-md-2">
            <label class="form-label">Seats</label>
            <input type="number" class="form-control" name="seats" min="2" max="15" value="<?= Helpers::e($vehicle['seats'] ?? $_POST['seats'] ?? 5) ?>">
        </div>
        <div class="col-md-3">
            <label class="form-label">Price per day (GHS)</label>
            <input type="number" class="form-control" name="price_per_day" step="0.01" min="0" value="<?= Helpers::e($vehicle['price_per_day'] ?? $_POST['price_per_day'] ?? '') ?>" required>
        </div>
        <div class="col-md-3">
            <label class="form-label">Price per week (GHS, optional)</label>
            <input type="number" class="form-control" name="price_per_week" step="0.01" min="0" value="<?= Helpers::e($vehicle['price_per_week'] ?? $_POST['price_per_week'] ?? '') ?>">
        </div>
        <div class="col-md-3">
            <label class="form-label">Price per month (GHS, optional)</label>
            <input type="number" class="form-control" name="price_per_month" step="0.01" min="0" value="<?= Helpers::e($vehicle['price_per_month'] ?? $_POST['price_per_month'] ?? '') ?>">
        </div>
        <div class="col-md-2">
            <label class="form-label">Fuel type</label>
            <select class="form-select" name="fuel_type">
                <option value="Petrol" <?= ($vehicle['fuel_type'] ?? $_POST['fuel_type'] ?? '') === 'Petrol' ? 'selected' : '' ?>>Petrol</option>
                <option value="Diesel" <?= ($vehicle['fuel_type'] ?? $_POST['fuel_type'] ?? '') === 'Diesel' ? 'selected' : '' ?>>Diesel</option>
                <option value="Hybrid" <?= ($vehicle['fuel_type'] ?? $_POST['fuel_type'] ?? '') === 'Hybrid' ? 'selected' : '' ?>>Hybrid</option>
                <option value="Electric" <?= ($vehicle['fuel_type'] ?? $_POST['fuel_type'] ?? '') === 'Electric' ? 'selected' : '' ?>>Electric</option>
            </select>
        </div>
        <div class="col-md-2">
            <label class="form-label">Transmission</label>
            <select class="form-select" name="transmission">
                <option value="Automatic" <?= ($vehicle['transmission'] ?? $_POST['transmission'] ?? '') === 'Automatic' ? 'selected' : '' ?>>Automatic</option>
                <option value="Manual" <?= ($vehicle['transmission'] ?? $_POST['transmission'] ?? '') === 'Manual' ? 'selected' : '' ?>>Manual</option>
            </select>
        </div>
        <?php if ($hasVehicleTypeColumn): ?>
        <div class="col-md-3">
            <label class="form-label">Vehicle type</label>
            <select class="form-select" name="vehicle_type">
                <option value="economy" <?= ($vehicle['vehicle_type'] ?? $_POST['vehicle_type'] ?? 'economy') === 'economy' ? 'selected' : '' ?>>Economy</option>
                <option value="luxury" <?= ($vehicle['vehicle_type'] ?? $_POST['vehicle_type'] ?? '') === 'luxury' ? 'selected' : '' ?>>Luxury</option>
                <option value="suv" <?= ($vehicle['vehicle_type'] ?? $_POST['vehicle_type'] ?? '') === 'suv' ? 'selected' : '' ?>>SUV</option>
            </select>
        </div>
        <?php endif; ?>
        <div class="col-md-3">
            <label class="form-label">Status</label>
            <select class="form-select" name="status">
                <option value="available" <?= ($vehicle['status'] ?? $_POST['status'] ?? '') === 'available' ? 'selected' : '' ?>>Available</option>
                <option value="rented" <?= ($vehicle['status'] ?? $_POST['status'] ?? '') === 'rented' ? 'selected' : '' ?>>Rented</option>
                <option value="maintenance" <?= ($vehicle['status'] ?? $_POST['status'] ?? '') === 'maintenance' ? 'selected' : '' ?>>Maintenance</option>
                <option value="retired" <?= ($vehicle['status'] ?? $_POST['status'] ?? '') === 'retired' ? 'selected' : '' ?>>Retired</option>
            </select>
        </div>
        <div class="col-md-3">
            <label class="form-label">Security deposit (GHS)</label>
            <input type="number" class="form-control" name="security_deposit" step="0.01" min="0" value="<?= Helpers::e($vehicle['security_deposit'] ?? $_POST['security_deposit'] ?? 0) ?>">
        </div>
        <div class="col-md-3">
            <label class="form-label">Mileage limit per day (km)</label>
            <input type="number" class="form-control" name="mileage_limit_per_day" min="0" value="<?= Helpers::e($vehicle['mileage_limit_per_day'] ?? $_POST['mileage_limit_per_day'] ?? 100) ?>">
        </div>
        <div class="col-md-3">
            <label class="form-label">Excess mileage charge (per km)</label>
            <input type="number" class="form-control" name="excess_mileage_charge" step="0.01" min="0" value="<?= Helpers::e($vehicle['excess_mileage_charge'] ?? $_POST['excess_mileage_charge'] ?? 0.50) ?>">
        </div>
        <div class="col-md-3">
            <label class="form-label">Current mileage (km)</label>
            <input type="number" class="form-control" name="mileage" min="0" value="<?= Helpers::e($vehicle['mileage'] ?? $_POST['mileage'] ?? 0) ?>">
        </div>
        <div class="col-12">
            <label class="form-label">Description (optional)</label>
            <textarea class="form-control" name="description" rows="2"><?= Helpers::e($vehicle['description'] ?? $_POST['description'] ?? '') ?></textarea>
        </div>
        <div class="col-12">
            <label class="form-label">Features (comma separated, optional)</label>
            <?php
            $featuresValue = $_POST['features'] ?? '';
            if ($featuresValue === '' && !empty($vehicle['features'])) {
                $decoded = json_decode($vehicle['features'], true);
                if (is_array($decoded)) {
                    $featuresValue = implode(', ', $decoded);
                }
            }
            ?>
            <textarea class="form-control" name="features" rows="2"><?= Helpers::e($featuresValue) ?></textarea>
            <small class="text-muted">Example: Air conditioning, Bluetooth, Reverse camera</small>
        </div>
        <div class="col-12">
            <label class="form-label">Location address (optional)</label>
            <input type="text" class="form-control" name="location_address" value="<?= Helpers::e($vehicle['location_address'] ?? $_POST['location_address'] ?? '') ?>">
        </div>
        <div class="col-12">
            <div class="form-check">
                <input type="checkbox" class="form-check-input" name="is_available" id="av" value="1" <?= ($vehicle['is_available'] ?? $_POST['is_available'] ?? 1) ? 'checked' : '' ?>>
                <label class="form-check-label" for="av">Available for rent</label>
            </div>
        </div>
        <div class="col-12">
            <button type="submit" class="btn btn-admin-primary">Save</button>
            <a href="<?= Helpers::baseUrl() ?>/admin/vehicles.php" class="btn btn-admin-outline">Cancel</a>
        </div>
    </div>
</form>
<?php require __DIR__ . '/layout/footer.php'; ?>
