<?php
require_once dirname(dirname(__DIR__)) . '/bootstrap.php';

use App\Auth;
use App\Database;
use App\Helpers;

Auth::requireAdmin();

$report = $_GET['report'] ?? 'bookings';
$from = $_GET['from'] ?? date('Y-m-01');
$to = $_GET['to'] ?? date('Y-m-d');

$pageTitle = 'Reports';
require __DIR__ . '/layout/header.php';

$summary = Database::run(
    'SELECT COUNT(*) AS total_bookings, COALESCE(SUM(total_amount),0) AS total_revenue FROM bookings WHERE payment_status = ? AND pickup_date BETWEEN ? AND ?',
    ['paid', $from, $to]
)->fetch();
?>
<h1 class="admin-page-title">Reports &amp; Analytics</h1>
<form method="get" class="row g-3 mb-4">
    <div class="col-auto">
        <label class="form-label">From</label>
        <input type="date" name="from" class="form-control" value="<?= Helpers::e($from) ?>">
    </div>
    <div class="col-auto">
        <label class="form-label">To</label>
        <input type="date" name="to" class="form-control" value="<?= Helpers::e($to) ?>">
    </div>
    <div class="col-auto d-flex align-items-end">
        <button type="submit" class="btn btn-admin-primary">Apply</button>
    </div>
</form>
<div class="admin-card">
    <div class="card-label">Period summary</div>
    <div class="card-value"><?= Helpers::e($from) ?> to <?= Helpers::e($to) ?></div>
    <p class="mb-0 mt-2">Bookings (paid/confirmed/completed): <strong><?= (int)$summary['total_bookings'] ?></strong> &nbsp;·&nbsp; Revenue (GHS): <strong><?= number_format((float)$summary['total_revenue'], 2) ?></strong></p>
</div>
<p class="text-muted small">PDF export can be added via TCPDF for printable reports.</p>
<?php require __DIR__ . '/layout/footer.php'; ?>
