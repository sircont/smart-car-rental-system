<?php
require_once dirname(__DIR__) . '/bootstrap.php';

use App\Helpers;

$base = Helpers::baseUrl();
$pageTitle = 'About Us';
require __DIR__ . '/inc/portal_header.php';
?>
<main class="container-fluid px-4 px-lg-5 py-5">
    <h1 class="mb-4">About Us</h1>
    <p class="lead">DriveSmart Rentals Portal provides reliable, affordable vehicle rental across Ghana.</p>
    <p>We offer a wide range of vehicles from economy cars to SUVs—with transparent pricing, online booking, and secure payment. Our team is committed to making your rental experience smooth and hassle-free.</p>
    <p>Contact us for support or visit our <a href="<?= $base ?>/cars.php">Car Listing</a> to find the right car for you.</p>
</main>
<?php require __DIR__ . '/inc/portal_footer.php'; ?>
