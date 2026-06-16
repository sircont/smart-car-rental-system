<?php
$pageTitle = $pageTitle ?? 'Staff';
$base = \App\Helpers::baseUrl();
$current = basename($_SERVER['PHP_SELF'], '.php');
if ($current === 'index') $current = 'dashboard';
$user = \App\Auth::user();
$userName = $user ? \App\Helpers::e($user['name']) : 'Staff';
$userInitial = $userName ? mb_substr($userName, 0, 1) : 'S';
$adminCssVersion = @filemtime(__DIR__ . '/../../admin/assets/admin.css') ?: time();
?>
<!DOCTYPE html>
<html lang="en" class="admin-layout">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <meta name="admin-base" content="<?= \App\Helpers::e($base) ?>">
    <title><?= \App\Helpers::e($pageTitle) ?> | Staff - Smart Car Rental</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
    <link href="<?= $base ?>/admin/assets/admin.css?v=<?= (int)$adminCssVersion ?>" rel="stylesheet">
</head>
<body class="admin-dark">
<div class="admin-mobile-bar">
    <button type="button" class="btn btn-admin-outline border-0" id="sidebarToggle" aria-label="Menu"><i class="bi bi-list fs-4"></i></button>
    <span class="ms-2 fw-600">Staff Portal</span>
</div>
<aside class="admin-sidebar" id="adminSidebar">
    <div class="brand">Staff</div>
    <div class="admin-sidebar-scroll">
    <nav class="nav flex-column">
        <a class="nav-link <?= $current === 'dashboard' ? 'active' : '' ?>" href="<?= $base ?>/staff/"><i class="bi bi-speedometer2"></i> Dashboard</a>
        <a class="nav-link <?= $current === 'maintenance' ? 'active' : '' ?>" href="<?= $base ?>/staff/maintenance.php"><i class="bi bi-wrench"></i> Maintenance</a>
        <a class="nav-link <?= $current === 'bookings' ? 'active' : '' ?>" href="<?= $base ?>/staff/bookings.php"><i class="bi bi-calendar-check"></i> Bookings</a>
        <a class="nav-link <?= $current === 'checklist' ? 'active' : '' ?>" href="<?= $base ?>/staff/checklist.php"><i class="bi bi-list-check"></i> Daily checklist</a>
        <a class="nav-link" href="<?= $base ?>/index.php"><i class="bi bi-house"></i> Site</a>
    </nav>
    </div>
</aside>
<div class="admin-sidebar-backdrop" id="adminSidebarBackdrop" aria-hidden="true"></div>
<main class="admin-main">
    <header class="admin-main-header admin-main-header--staff-toolbar">
        <div class="admin-main-header-user dropstart ms-auto">
            <button class="dropdown-toggle" type="button" id="staffMainProfileMenu" data-bs-toggle="dropdown" aria-expanded="false">
                <div class="user-avatar"><?= \App\Helpers::e($userInitial) ?></div>
                <div class="user-info text-start">
                    <div class="name"><?= $userName ?></div>
                    <div class="role">Staff</div>
                </div>
                <i class="bi bi-chevron-down text-muted ms-2" style="font-size:0.8rem;"></i>
            </button>
            <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="staffMainProfileMenu">
                <li><span class="dropdown-item-text small text-muted"><?= $user ? \App\Helpers::e($user['email']) : '' ?></span></li>
                <li><hr class="dropdown-divider"></li>
                <li><a class="dropdown-item" href="<?= $base ?>/index.php"><i class="bi bi-house me-2"></i>View site</a></li>
                <li><a class="dropdown-item text-danger" href="<?= $base ?>/logout.php"><i class="bi bi-box-arrow-right me-2"></i>Logout</a></li>
            </ul>
        </div>
    </header>
    <div class="admin-main-content">
