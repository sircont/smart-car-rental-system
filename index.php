<?php
require_once dirname(__DIR__) . '/bootstrap.php';

use App\Auth;
use App\Database;
use App\Helpers;
use App\Notification;

$base = Helpers::baseUrl();
$config = require dirname(__DIR__) . '/config/app.php';
$supportEmail = $config['support_email'] ?? 'info@drivesmartrentals@mail.com';
$supportPhone = $config['support_phone'] ?? '+233 50 1029 863';
$tollFree = $config['toll_free'] ?? '102 9863';
$whatsapp = !empty($config['whatsapp_number']) ? 'https://wa.me/' . preg_replace('/\D/', '', $config['whatsapp_number']) : '#';
$returnDueBookings = [];
$portalUser = Auth::check() ? Auth::user() : null;
$portalUserName = $portalUser ? Helpers::e($portalUser['name']) : '';
$portalUserInitial = $portalUserName ? mb_substr($portalUserName, 0, 1) : 'U';
$portalUserAvatarUrl = null;
if ($portalUser && isset($portalUser['profile_image']) && $portalUser['profile_image'] !== '' && $portalUser['profile_image'] !== 'default.png') {
    $portalUserAvatarUrl = $base . '/uploads/profile/' . Helpers::e($portalUser['profile_image']);
}
if (Auth::check() && Auth::role() === 'customer') {
    $returnDueBookings = Notification::getReturnDueForUser(Auth::id());
}

$vehicles = Database::run(
    'SELECT v.id, v.model, v.year, v.registration_number, v.price_per_day, v.primary_image, b.name AS brand_name
     FROM vehicles v
     JOIN brands b ON v.brand_id = b.id
     LEFT JOIN bookings bk ON bk.vehicle_id = v.id
       AND bk.booking_status NOT IN (\'cancelled\', \'completed\')
       AND bk.return_date >= CURDATE()
     WHERE v.is_available = 1 AND (v.status = ? OR v.status IS NULL)
       AND bk.id IS NULL
     ORDER BY b.name, v.model LIMIT 8',
    ['available']
)->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title> DriveSmart Rentals | Find the right car for you</title>
    <link rel="manifest" href="<?= $base ?>/manifest.json">
    <meta name="theme-color" content="#c41e3a">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
    <link href="<?= $base ?>/assets/portal.css" rel="stylesheet">
</head>
<body class="home-page">

<!-- Top bar (dark) -->
<header class="portal-topbar portal-topbar-dark py-2">
    <div class="container-fluid px-4 px-lg-5">
        <div class="d-flex flex-wrap align-items-center justify-content-between gap-2">
           <!-- <div class="d-flex align-items-center gap-3">
                <a href="#" class="text-white-50 hover-white" aria-label="Twitter"><i class="bi bi-twitter-x"></i></a>
                <a href="#" class="text-white-50 hover-white" aria-label="Facebook"><i class="bi bi-facebook"></i></a>
                <a href="#" class="text-white-50 hover-white" aria-label="LinkedIn"><i class="bi bi-linkedin"></i></a>
                <a href="#" class="text-white-50 hover-white" aria-label="Instagram"><i class="bi bi-instagram"></i></a>
                <a href="#" class="text-white-50 hover-white" aria-label="WhatsApp"><i class="bi bi-whatsapp"></i></a>
                <span class="vr text-white-50 d-none d-md-inline"></span>
                <div class="d-flex align-items-center gap-2 lang-select">
                    <!--span class="text-white-50 small">|</span-->
                </div>
            </div> -->
            <div class="d-flex align-items-center gap-3 flex-wrap">
                <a href="mailto:<?= Helpers::e($supportEmail) ?>" class="text-white-50 small hover-white text-decoration-none"><i class="bi bi-envelope me-1"></i><?= Helpers::e($supportEmail) ?></a>
                <a href="tel:<?= preg_replace('/\s+/', '', $supportPhone) ?>" class="text-white-50 small hover-white text-decoration-none"><i class="bi bi-telephone me-1"></i><?= Helpers::e($supportPhone) ?></a>
                <a href="<?= $base ?>/cars.php" class="btn btn-home-book btn-sm">BOOK A CAR</a>
                <a href="tel:<?= preg_replace('/\s+/', '', $tollFree) ?>" class="btn btn-home-phone btn-sm"><i class="bi bi-telephone-fill me-1"></i><?= Helpers::e($tollFree) ?></a>
            </div>
        </div>
    </div>
</header>

<!-- Main nav (white) -->
<nav class="navbar navbar-expand-lg portal-nav portal-nav-light">
    <div class="container-fluid px-4 px-lg-5">
        <a class="navbar-brand home-brand" href="<?= $base ?>/index.php">
            <span class="brand-speedy">DriveSmart Rentals</span>
            <span class="brand-tagline d-block small">FIND THE RIGHT CAR</span>
        </a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#nav" aria-label="Toggle navigation">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="nav">
            <ul class="navbar-nav me-auto">
                <li class="nav-item"><a class="nav-link" href="<?= $base ?>/index.php">Home</a></li>
                <li class="nav-item"><a class="nav-link" href="<?= $base ?>/about.php">About Us</a></li>
                <li class="nav-item dropdown">

                </li>
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle" href="#" data-bs-toggle="dropdown">Cars for Rent</a>
                    <ul class="dropdown-menu dropdown-menu-wide">
                        <li><a class="dropdown-item" href="<?= $base ?>/cars.php">Show all cars <i class="bi bi-chevron-right float-end"></i></a></li>
                        <li><a class="dropdown-item" href="<?= $base ?>/cars.php?type=luxury">Luxury Cars for Rent <i class="bi bi-chevron-right float-end"></i></a></li>
                        <li><a class="dropdown-item" href="<?= $base ?>/cars.php?type=economy">Cheap Cars for Rent <i class="bi bi-chevron-right float-end"></i></a></li>
                        <li><a class="dropdown-item" href="<?= $base ?>/cars.php?type=suv">SUV Car Rental <i class="bi bi-chevron-right float-end"></i></a></li>
                        <li><a class="dropdown-item" href="<?= $base ?>/cars.php">Sedan Car Rental <i class="bi bi-chevron-right float-end"></i></a></li>
                        <li><a class="dropdown-item" href="<?= $base ?>/cars.php">Hatchbacks for Rent <i class="bi bi-chevron-right float-end"></i></a></li>
                    </ul>
                </li>
                
                <li class="nav-item"><a class="nav-link" href="<?= $base ?>/faqs.php">Blog</a></li>
                <li class="nav-item"><a class="nav-link" href="<?= $base ?>/contact.php">Contact Us</a></li>
            </ul>
            <div class="d-flex align-items-center gap-2">
                <?php if ($portalUser): ?>
                    <?php if (Auth::isAdmin()): ?>
                        <a href="<?= $base ?>/admin/" class="btn btn-outline-dark btn-sm"><i class="bi bi-speedometer2 me-1"></i><span class="d-none d-sm-inline">Admin</span></a>
                    <?php elseif (Auth::isStaff()): ?>
                        <a href="<?= $base ?>/staff/" class="btn btn-outline-dark btn-sm"><i class="bi bi-speedometer2 me-1"></i><span class="d-none d-sm-inline">Staff</span></a>
                    <?php endif; ?>
                    <div class="dropdown">
                        <button class="btn btn-portal-profile dropdown-toggle d-flex align-items-center gap-2" type="button" id="homeProfileMenu" data-bs-toggle="dropdown" aria-expanded="false">
                            <?php if ($portalUserAvatarUrl): ?>
                                <img src="<?= $portalUserAvatarUrl ?>" alt="" class="portal-user-avatar portal-user-avatar-img">
                            <?php else: ?>
                                <span class="portal-user-avatar"><?= $portalUserInitial ?></span>
                            <?php endif; ?>
                            <span class="d-none d-sm-inline text-dark"><?= $portalUserName ?></span>
                            <i class="bi bi-chevron-down small"></i>
                        </button>
                        <ul class="dropdown-menu dropdown-menu-end portal-dropdown" aria-labelledby="homeProfileMenu">
                            <li><span class="dropdown-item-text small text-muted"><?= Helpers::e($portalUser['email']) ?></span></li>
                            <li><hr class="dropdown-divider"></li>
                            <?php if (Auth::isAdmin()): ?>
                                <li><a class="dropdown-item" href="<?= $base ?>/admin/"><i class="bi bi-speedometer2 me-2"></i>Admin dashboard</a></li>
                            <?php elseif (Auth::isStaff()): ?>
                                <li><a class="dropdown-item" href="<?= $base ?>/staff/"><i class="bi bi-speedometer2 me-2"></i>Staff dashboard</a></li>
                            <?php endif; ?>
                            <?php if (($portalUser['role'] ?? '') === 'customer'): ?>
                                <li><a class="dropdown-item" href="<?= $base ?>/profile.php"><i class="bi bi-person me-2"></i>My profile</a></li>
                                <li><a class="dropdown-item" href="<?= $base ?>/my-bookings.php"><i class="bi bi-calendar-check me-2"></i>My bookings</a></li>
                            <?php endif; ?>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item text-danger" href="<?= $base ?>/logout.php"><i class="bi bi-box-arrow-right me-2"></i>Logout</a></li>
                        </ul>
                    </div>
                <?php else: ?>
                    <a href="<?= $base ?>/login.php" class="btn btn-outline-danger btn-sm">Login</a>
                    <a href="<?= $base ?>/register.php" class="btn btn-danger btn-sm">Register</a>
                <?php endif; ?>
            </div>
        </div>
    </div>
</nav>

<?php if (!empty($returnDueBookings)): ?>
<div class="portal-reminder-banner container-fluid px-4 px-lg-5 py-2">
    <div class="alert alert-warning alert-dismissible fade show mb-0 d-flex align-items-center flex-wrap gap-2" role="alert">
        <i class="bi bi-calendar-event me-2"></i>
        <strong>Reminder:</strong>
        <?php
        $msgs = [];
        foreach ($returnDueBookings as $r) {
            $date = date('M j, Y', strtotime($r['return_date']));
            $isToday = (date('Y-m-d', strtotime($r['return_date'])) === date('Y-m-d'));
            $msgs[] = 'Your rental ' . Helpers::e($r['brand_name'] . ' ' . $r['vehicle_model']) . ' is due for return ' . ($isToday ? 'today' : 'tomorrow') . ' (' . $date . ')';
        }
        echo Helpers::e(implode('. ', $msgs));
        ?>
        <a href="<?= $base ?>/my-bookings.php" class="btn btn-sm btn-outline-dark ms-2">View my bookings</a>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
</div>
<?php endif; ?>

<!-- Hero section -->
<section class="home-hero">
    <div class="home-hero-pattern"></div>
    <div class="container-fluid position-relative px-4 px-lg-5">
        <div class="row align-items-center min-vh-50 py-5">
            <!-- Search widget -->
            <div class="col-lg-4 mb-4 mb-lg-0">
                <div class="home-search-card card border-0 shadow">
                    <div class="card-body p-4">
                        <div class="d-flex gap-2 mb-3 home-car-type-tabs">
                            <button type="button" class="btn btn-outline-secondary flex-grow-1 active" data-type="economy">
                                <i class="bi bi-car-front d-block mb-1"></i>
                                <span class="small">Economy</span>
                            </button>
                            <button type="button" class="btn btn-outline-secondary flex-grow-1" data-type="luxury">
                                <i class="bi bi-car-front d-block mb-1"></i>
                                <span class="small">Luxury</span>
                            </button>
                            <button type="button" class="btn btn-outline-secondary flex-grow-1" data-type="suv">
                                <i class="bi bi-truck d-block mb-1"></i>
                                <span class="small">SUV</span>
                            </button>
                            <button type="button" class="btn btn-outline-secondary flex-grow-1" data-type="suv">
                                <i class="bi bi-truck d-block mb-1"></i>
                                <span class="small">Hatchbacks</span>
                            </button>
                            <button type="button" class="btn btn-outline-secondary flex-grow-1" data-type="suv">
                                <i class="bi bi-truck d-block mb-1"></i>
                                <span class="small">Sedan</span>
                            </button>
                            
                        </div>
                        <form action="<?= $base ?>/cars.php" method="get" id="heroSearchForm">
                            <input type="hidden" name="type" id="heroType" value="economy">
                            <div class="mb-3">
                                <label class="form-label small text-muted">Start at</label>
                                <div class="input-group">
                                    <span class="input-group-text bg-white"><i class="bi bi-calendar3 text-muted"></i></span>
                                    <input type="date" name="start" class="form-control" id="heroStart">
                                </div>
                            </div>
                            <div class="mb-3">
                                <label class="form-label small text-muted">End at</label>
                                <div class="input-group">
                                    <span class="input-group-text bg-white"><i class="bi bi-calendar3 text-muted"></i></span>
                                    <input type="date" name="end" class="form-control" id="heroEnd">
                                </div>
                            </div>
                            <div class="form-check mb-3">
                                <input class="form-check-input" type="checkbox" name="age" value="1" id="ageCheck" checked>
                                <label class="form-check-label small" for="ageCheck">Age 18 to 65</label>
                            </div>
                            <button type="submit" class="btn btn-hero-search w-100 py-3 fw-bold">SEARCH</button>
                        </form>
                    </div>
                </div>
            </div>
            <!-- Slogan -->
            <div class="col-lg-4 text-center mb-4 mb-lg-0">
                <h1 class="home-slogan mb-0">
                    <span class="d-block">WAIT IS OVER!</span>
                    <span class="d-block">ENJOY BIG SPACE,</span>
                    <span class="d-block">IN SMALL PRICE</span>
                </h1>
            </div>
            <!-- Car + BOOK NOW -->
            <div class="col-lg-4 text-center position-relative">
                <div class="home-hero-car-wrap">
                    <img src="<?= $base ?>../admin/assets/images/background.png" alt="Car" class="img-fluid home-hero-car">
                    <a href="<?= $base ?>/cars.php" class="home-book-now-badge">BOOK NOW</a>
                </div>
            </div>
        </div>
    </div>
    <!-- Feature highlights -->
    <div class="home-features-strip">
        <div class="container">
            <div class="row g-3 py-4">
                <div class="col-6 col-md-3 text-center">
                    <div class="home-feature-icon mb-2"><i class="bi bi-arrow-down-circle"></i></div>
                    <span class="home-feature-text">LOWEST RATE GUARANTEED</span>
                </div>
                <div class="col-6 col-md-3 text-center">
                    <div class="home-feature-icon mb-2"><i class="bi bi-shield-check"></i></div>
                    <span class="home-feature-text">NO DEPOSIT</span>
                </div>
                <div class="col-6 col-md-3 text-center">
                    <div class="home-feature-icon mb-2"><i class="bi bi-credit-card"></i></div>
                    <span class="home-feature-text">RENTAL FROM 500/DAY</span>
                </div>
                <div class="col-6 col-md-3 text-center">
                    <div class="home-feature-icon mb-2"><i class="bi bi-lightning-charge"></i></div>
                    <span class="home-feature-text">FAST DELIVERY</span>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Main content - Featured vehicles -->
<main class="container-fluid px-4 px-lg-5 py-5">
    <?php $flash = Helpers::getFlash(); if ($flash): ?>
        <div class="alert alert-<?= $flash['type'] === 'error' ? 'danger' : 'success' ?>"><?= Helpers::e($flash['message']) ?></div>
    <?php endif; ?>
    <h2 class="mb-4">Available vehicles</h2>
    <div class="row g-4">
        <?php foreach ($vehicles as $v):
            $vehicleImgUrl = !empty($v['primary_image']) ? $base . '/uploads/vehicles/' . Helpers::e($v['primary_image']) : 'https://images.unsplash.com/photo-1549317661-bd32c8ce0db2?w=400&auto=format&fit=crop';
        ?>
            <div class="col-md-6 col-lg-3">
                <div class="card h-100 shadow-sm border-0 portal-vehicle-card">
                    <div class="portal-vehicle-card-img-wrap">
                        <img src="<?= $vehicleImgUrl ?>" alt="<?= Helpers::e($v['brand_name'] . ' ' . $v['model']) ?>" class="portal-vehicle-card-img">
                    </div>
                    <div class="card-body">
                        <h5 class="card-title"><?= Helpers::e($v['brand_name']) ?> <?= Helpers::e($v['model']) ?></h5>
                        <p class="card-text text-muted"><?= (int)$v['year'] ? (int)$v['year'] . ' · ' : '' ?><?= Helpers::e($v['registration_number'] ?? '—') ?></p>
                        <p class="mb-2"><strong>GHS <?= number_format((float)$v['price_per_day'], 2) ?></strong> / day</p>
                        <a href="<?= $base ?>/book.php?vehicle=<?= (int)$v['id'] ?>" class="btn btn-danger btn-sm w-100">Book now</a>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
        <?php if (empty($vehicles)): ?>
            <div class="col-12"><p class="text-muted">No vehicles available at the moment. Check back later.</p></div>
        <?php endif; ?>
    </div>
    <div class="text-center mt-4">
        <a href="<?= $base ?>/cars.php" class="btn btn-outline-danger btn-lg">View all cars</a>
    </div>
</main>

<footer class="bg-dark text-light py-4 mt-5">
    <div class="container-fluid px-4 px-lg-5 text-center">
        <p class="mb-0">&copy; <?= date('Y') ?> DriveSmart Rentals Portal</p>
    </div>
</footer>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
(function() {
    var typeBtns = document.querySelectorAll('.home-car-type-tabs button');
    var hiddenInput = document.getElementById('heroType');
    if (hiddenInput && typeBtns.length) {
        typeBtns.forEach(function(btn) {
            btn.addEventListener('click', function() {
                typeBtns.forEach(function(b) { b.classList.remove('active'); });
                btn.classList.add('active');
                hiddenInput.value = btn.getAttribute('data-type') || 'economy';
            });
        });
    }
    var chatBtn = document.getElementById('chatBtn');
    var chatBubble = document.getElementById('chatBubble');
    if (chatBtn && chatBubble) {
        chatBtn.addEventListener('click', function() {
            chatBubble.classList.toggle('d-none');
        });
    }
})();
if ('serviceWorker' in navigator) navigator.serviceWorker.register('<?= $base ?>/sw.js');
</script>
</body>
</html>
