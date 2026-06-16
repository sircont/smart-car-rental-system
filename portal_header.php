<?php
if (!isset($base)) {
    require_once dirname(dirname(__DIR__)) . '/bootstrap.php';
    $base = \App\Helpers::baseUrl();
}
$config = require dirname(dirname(__DIR__)) . '/config/app.php';
$supportEmail = $config['support_email'] ?? 'info@Drivesmartrentals.com';
$supportPhone = $config['support_phone'] ?? '+233 50 1029 863';
$pageTitle = $pageTitle ?? 'DriveSmart Rentals Portal';
$portalUser = \App\Auth::check() ? \App\Auth::user() : null;
$portalUserName = $portalUser ? \App\Helpers::e($portalUser['name']) : '';
$portalUserInitial = $portalUserName ? mb_substr($portalUserName, 0, 1) : 'U';
$returnDueBookings = [];
if ($portalUser && isset($portalUser['role']) && $portalUser['role'] === 'customer') {
    $returnDueBookings = \App\Notification::getReturnDueForUser(\App\Auth::id());
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= isset($pageTitle) && $pageTitle !== 'DriveSmart Rentals Portal' ? \App\Helpers::e($pageTitle) . ' | ' : '' ?>DriveSmart Rentals Portal</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
    <link href="<?= $base ?>/assets/portal.css" rel="stylesheet">
</head>
<body>
<div class="portal-topbar py-2">
    <div class="container-fluid px-4 px-lg-5">
        <div class="d-flex flex-wrap align-items-center justify-content-between gap-2">
            <a href="<?= $base ?>/index.php" class="d-flex align-items-center gap-2 text-dark text-decoration-none fw-bold">
                <i class="bi bi-car-front-fill portal-brand-icon"></i>
                <span>DriveSmart Rentals Portal</span>
            </a>
            <div class="d-flex align-items-center gap-4 flex-wrap small">
                <span class="text-muted">FOR SUPPORT MAIL US:</span>
                <a href="mailto:<?= \App\Helpers::e($supportEmail) ?>" class="text-dark"><i class="bi bi-envelope me-1"></i><?= \App\Helpers::e($supportEmail) ?></a>
                <span class="text-muted">SERVICE HELPLINE:</span>
                <a href="tel:<?= preg_replace('/\s+/', '', $supportPhone) ?>" class="text-dark"><i class="bi bi-telephone me-1"></i><?= \App\Helpers::e($supportPhone) ?></a>
            </div>
            <div class="d-flex align-items-center gap-2 flex-wrap justify-content-end">
                <?php if ($portalUser && \App\Auth::isAdmin()): ?>
                    <a href="<?= $base ?>/admin/" class="btn btn-sm btn-outline-secondary"><i class="bi bi-speedometer2 me-1"></i><span class="d-none d-sm-inline">Admin</span></a>
                <?php elseif ($portalUser && \App\Auth::isStaff()): ?>
                    <a href="<?= $base ?>/staff/" class="btn btn-sm btn-outline-secondary"><i class="bi bi-speedometer2 me-1"></i><span class="d-none d-sm-inline">Staff</span></a>
                <?php endif; ?>
                <?php if ($portalUser): ?>
                    <?php
                    $portalUserAvatarUrl = (isset($portalUser['profile_image']) && $portalUser['profile_image'] !== '' && $portalUser['profile_image'] !== 'default.png')
                        ? $base . '/uploads/profile/' . \App\Helpers::e($portalUser['profile_image'])
                        : null;
                    ?>
                    <div class="dropdown">
                        <button class="btn btn-portal-profile dropdown-toggle d-flex align-items-center gap-2" type="button" id="portalProfileMenu" data-bs-toggle="dropdown" aria-expanded="false">
                            <?php if ($portalUserAvatarUrl): ?>
                                <img src="<?= $portalUserAvatarUrl ?>" alt="" class="portal-user-avatar portal-user-avatar-img">
                            <?php else: ?>
                                <span class="portal-user-avatar"><?= \App\Helpers::e($portalUserInitial) ?></span>
                            <?php endif; ?>
                            <span class="d-none d-sm-inline"><?= $portalUserName ?></span>
                            <i class="bi bi-chevron-down small"></i>
                        </button>
                        <ul class="dropdown-menu dropdown-menu-end portal-dropdown" aria-labelledby="portalProfileMenu">
                            <li><span class="dropdown-item-text small text-muted"><?= \App\Helpers::e($portalUser['email']) ?></span></li>
                            <li><hr class="dropdown-divider"></li>
                            <?php if (\App\Auth::isAdmin()): ?>
                                <li><a class="dropdown-item" href="<?= $base ?>/admin/"><i class="bi bi-speedometer2 me-2"></i>Admin dashboard</a></li>
                            <?php elseif (\App\Auth::isStaff()): ?>
                                <li><a class="dropdown-item" href="<?= $base ?>/staff/"><i class="bi bi-speedometer2 me-2"></i>Staff dashboard</a></li>
                            <?php endif; ?>
                            <?php if (isset($portalUser['role']) && $portalUser['role'] === 'customer'): ?>
                                <li><a class="dropdown-item" href="<?= $base ?>/profile.php"><i class="bi bi-person me-2"></i>My profile</a></li>
                                <li><a class="dropdown-item" href="<?= $base ?>/my-bookings.php"><i class="bi bi-calendar-check me-2"></i>My bookings</a></li>
                            <?php endif; ?>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item text-danger" href="<?= $base ?>/logout.php"><i class="bi bi-box-arrow-right me-2"></i>Logout</a></li>
                        </ul>
                    </div>
                <?php else: ?>
                    <a href="<?= $base ?>/login.php" class="portal-topbar btn btn-login btn-sm me-1">Login</a>
                    <a href="<?= $base ?>/register.php" class="portal-topbar btn btn-register btn-sm">Register</a>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>
<nav class="navbar navbar-expand-lg portal-nav">
    <div class="container-fluid px-4 px-lg-5">
        <a class="navbar-brand d-flex align-items-center" href="<?= $base ?>/index.php">
            <i class="bi bi-gear-fill brand-icon"></i>
            <span class="brand-text">DriveSmart Rentals Portal</span>
        </a>
        <button class="navbar-toggler border-light" type="button" data-bs-toggle="collapse" data-bs-target="#nav" aria-label="Toggle navigation">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="nav">
            <ul class="navbar-nav me-auto">
                <li class="nav-item"><a class="nav-link <?= basename($_SERVER['PHP_SELF']) === 'index.php' ? 'active' : '' ?>" href="<?= $base ?>/index.php">HOME</a></li>
                <li class="nav-item"><a class="nav-link <?= basename($_SERVER['PHP_SELF']) === 'about.php' ? 'active' : '' ?>" href="<?= $base ?>/about.php">ABOUT US</a></li>
                <li class="nav-item"><a class="nav-link <?= basename($_SERVER['PHP_SELF']) === 'cars.php' ? 'active' : '' ?>" href="<?= $base ?>/cars.php">CAR LISTING</a></li>
                <li class="nav-item"><a class="nav-link <?= basename($_SERVER['PHP_SELF']) === 'faqs.php' ? 'active' : '' ?>" href="<?= $base ?>/faqs.php">FAQS</a></li>
                <li class="nav-item"><a class="nav-link <?= basename($_SERVER['PHP_SELF']) === 'feedback.php' ? 'active' : '' ?>" href="<?= $base ?>/feedback.php">REVIEWS</a></li>
                <li class="nav-item"><a class="nav-link <?= basename($_SERVER['PHP_SELF']) === 'contact.php' ? 'active' : '' ?>" href="<?= $base ?>/contact.php">CONTACT US</a></li>
            </ul>
            <form action="<?= $base ?>/cars.php" method="get" class="search-wrap d-flex">
                <input type="text" name="q" class="form-control form-control-sm" placeholder="Search..." value="<?= \App\Helpers::e($_GET['q'] ?? '') ?>">
                <button type="submit" class="btn btn-search btn-sm px-3"><i class="bi bi-search"></i></button>
            </form>
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
            $msgs[] = 'Your rental ' . \App\Helpers::e($r['brand_name'] . ' ' . $r['vehicle_model']) . ' is due for return ' . ($isToday ? 'today' : 'tomorrow') . ' (' . $date . ')';
        }
        echo \App\Helpers::e(implode('. ', $msgs));
        ?>
        <a href="<?= $base ?>/my-bookings.php" class="btn btn-sm btn-outline-dark ms-2">View my bookings</a>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
</div>
<?php endif; ?>
