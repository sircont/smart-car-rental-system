<?php
require_once dirname(__DIR__) . '/bootstrap.php';

use App\Auth;
use App\Csrf;
use App\Database;
use App\Helpers;

Auth::requireLogin();
if (Auth::isAdmin() || Auth::isStaff()) {
    Helpers::redirect(Helpers::baseUrl() . (Auth::isAdmin() ? '/admin/' : '/staff/'));
}

$user = Database::run('SELECT id, full_name, email, phone, address, city, region, profile_image FROM users WHERE id = ?', [Auth::id()])->fetch();
$uploadDir = __DIR__ . '/uploads/profile';
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    Csrf::validateOrAbort();
    $fullName = trim($_POST['full_name'] ?? '');
    $phone = trim($_POST['phone'] ?? '') ?: null;
    $address = trim($_POST['address'] ?? '') ?: null;
    $city = trim($_POST['city'] ?? '') ?: null;
    $region = trim($_POST['region'] ?? '') ?: null;
    if ($fullName === '') $errors[] = 'Full name is required.';

    $profileImage = $user['profile_image'] ?? 'default.png';

    if (empty($errors) && !empty($_FILES['profile_image']['name']) && $_FILES['profile_image']['error'] === UPLOAD_ERR_OK) {
        $allowed = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
        $maxSize = 2 * 1024 * 1024;
        $fi = $_FILES['profile_image'];
        if ($fi['size'] > $maxSize) {
            $errors[] = 'Profile picture must be 2 MB or smaller.';
        } elseif (!in_array($fi['type'], $allowed, true)) {
            $errors[] = 'Please upload a JPEG, PNG, GIF or WebP image.';
        } else {
            if (!is_dir($uploadDir)) {
                @mkdir($uploadDir, 0755, true);
            }
            $ext = pathinfo($fi['name'], PATHINFO_EXTENSION) ?: 'jpg';
            $ext = strtolower(preg_replace('/[^a-z0-9]/', '', $ext)) ?: 'jpg';
            if (!in_array($ext, ['jpeg', 'jpg', 'png', 'gif', 'webp'], true)) $ext = 'jpg';
            $newName = (int) Auth::id() . '_' . time() . '.' . $ext;
            $path = $uploadDir . '/' . $newName;
            if (move_uploaded_file($fi['tmp_name'], $path)) {
                if ($profileImage !== 'default.png') {
                    $oldPath = $uploadDir . '/' . $profileImage;
                    if (is_file($oldPath)) @unlink($oldPath);
                }
                $profileImage = $newName;
            } else {
                $errors[] = 'Could not save the uploaded image.';
            }
        }
    }

    if (empty($errors)) {
        Database::run('UPDATE users SET full_name = ?, phone = ?, address = ?, city = ?, region = ?, profile_image = ? WHERE id = ?', [$fullName, $phone, $address, $city, $region, $profileImage, Auth::id()]);
        Helpers::flash('success', 'Profile updated.');
        Helpers::redirect(Helpers::baseUrl() . '/profile.php');
    } else {
        $user['full_name'] = $fullName;
        $user['phone'] = $phone;
        $user['address'] = $address;
        $user['city'] = $city;
        $user['region'] = $region;
    }
}

$base = Helpers::baseUrl();
$pageTitle = 'My profile';
$profileImageUrl = ($user['profile_image'] ?? '') && ($user['profile_image'] !== 'default.png')
    ? $base . '/uploads/profile/' . Helpers::e($user['profile_image'])
    : null;
require __DIR__ . '/inc/portal_header.php';
?>
<main class="container-fluid px-4 px-lg-5 py-5">
    <h1 class="mb-4">My profile</h1>
    <?php $flash = Helpers::getFlash(); if ($flash): ?>
        <div class="alert alert-<?= $flash['type'] === 'error' ? 'danger' : 'success' ?>"><?= Helpers::e($flash['message']) ?></div>
    <?php endif; ?>
    <?php if (!empty($errors)): ?>
        <div class="alert alert-danger"><?= implode('<br>', array_map([Helpers::class, 'e'], $errors)) ?></div>
    <?php endif; ?>
    <div class="card shadow-sm">
        <div class="card-body">
            <form method="post" enctype="multipart/form-data">
                <?= Csrf::field() ?>
                <div class="mb-4">
                    <label class="form-label">Profile picture</label>
                    <div class="d-flex align-items-center gap-4 flex-wrap">
                        <?php if ($profileImageUrl): ?>
                            <img src="<?= $profileImageUrl ?>" alt="Profile" class="profile-current-avatar rounded-circle border">
                        <?php else: ?>
                            <div class="profile-current-avatar profile-avatar-placeholder rounded-circle border d-flex align-items-center justify-content-center bg-light text-muted fw-bold">
                                <?= Helpers::e(mb_substr($user['full_name'] ?? 'U', 0, 1)) ?>
                            </div>
                        <?php endif; ?>
                        <div>
                            <input type="file" class="form-control" name="profile_image" accept="image/jpeg,image/png,image/gif,image/webp">
                            <small class="text-muted">JPEG, PNG, GIF or WebP. Max 2 MB.</small>
                        </div>
                    </div>
                </div>
                <div class="mb-3">
                    <label class="form-label">Full name</label>
                    <input type="text" class="form-control" name="full_name" value="<?= Helpers::e($user['full_name'] ?? '') ?>" required>
                </div>
                <div class="mb-3">
                    <label class="form-label">Email</label>
                    <input type="text" class="form-control" value="<?= Helpers::e($user['email']) ?>" disabled>
                    <small class="text-muted">Email cannot be changed.</small>
                </div>
                <div class="mb-3">
                    <label class="form-label">Phone</label>
                    <input type="text" class="form-control" name="phone" value="<?= Helpers::e($user['phone'] ?? '') ?>">
                </div>
                <div class="mb-3">
                    <label class="form-label">Address (optional)</label>
                    <input type="text" class="form-control" name="address" value="<?= Helpers::e($user['address'] ?? '') ?>">
                </div>
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label">City (optional)</label>
                        <input type="text" class="form-control" name="city" value="<?= Helpers::e($user['city'] ?? '') ?>">
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Region (optional)</label>
                        <input type="text" class="form-control" name="region" value="<?= Helpers::e($user['region'] ?? '') ?>">
                    </div>
                </div>
                <button type="submit" class="btn btn-primary">Save</button>
                <a href="<?= $base ?>/change-password.php" class="btn btn-outline-secondary">Change password</a>
            </form>
        </div>
    </div>
</main>
<?php require __DIR__ . '/inc/portal_footer.php'; ?>
