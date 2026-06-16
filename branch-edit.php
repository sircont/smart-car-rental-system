<?php
require_once dirname(dirname(__DIR__)) . '/bootstrap.php';

use App\Auth;
use App\Csrf;
use App\Database;
use App\Helpers;

Auth::requireAdmin();

$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
$row = null;
if ($id > 0) {
    try {
        $row = Database::run('SELECT * FROM branches WHERE id = ?', [$id])->fetch();
    } catch (\Throwable $e) {
        $row = null;
    }
    if (!$row) {
        Helpers::flash('error', 'Branch not found.');
        Helpers::redirect(Helpers::baseUrl() . '/admin/branches.php');
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    Csrf::validateOrAbort();
    $name = trim($_POST['name'] ?? '');
    $address = trim($_POST['address'] ?? '') ?: null;
    $phone = trim($_POST['phone'] ?? '') ?: null;
    $sortOrder = (int) ($_POST['sort_order'] ?? 0);
    $isActive = isset($_POST['is_active']) ? 1 : 0;
    $postId = (int) ($_POST['id'] ?? 0);

    if ($name === '') {
        Helpers::flash('error', 'Branch name is required.');
        Helpers::redirect(Helpers::baseUrl() . '/admin/branch-edit.php' . ($postId ? '?id=' . $postId : ''));
    }

    try {
        if ($postId > 0) {
            Database::run(
                'UPDATE branches SET name = ?, address = ?, phone = ?, sort_order = ?, is_active = ? WHERE id = ?',
                [$name, $address, $phone, $sortOrder, $isActive, $postId]
            );
            Helpers::flash('success', 'Branch updated.');
        } else {
            Database::run(
                'INSERT INTO branches (name, address, phone, sort_order, is_active) VALUES (?,?,?,?,?)',
                [$name, $address, $phone, $sortOrder, $isActive]
            );
            Helpers::flash('success', 'Branch added.');
        }
    } catch (\Throwable $e) {
        Helpers::flash('error', 'Could not save branch. If the name already exists, choose another name.');
        Helpers::redirect(Helpers::baseUrl() . '/admin/branch-edit.php' . ($postId ? '?id=' . $postId : ''));
    }
    Helpers::redirect(Helpers::baseUrl() . '/admin/branches.php');
}

$pageTitle = $row ? 'Edit branch' : 'Add branch';
require __DIR__ . '/layout/header.php';
$v = function (string $key, $default = '') use ($row) {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if ($key === 'is_active') {
            return isset($_POST['is_active']) ? '1' : '0';
        }
        return $_POST[$key] ?? $default;
    }
    return $row !== null && array_key_exists($key, $row) ? $row[$key] : $default;
};
?>
<h1 class="admin-page-title"><?= $row ? 'Edit branch' : 'Add branch' ?></h1>
<?php $flash = Helpers::getFlash(); if ($flash): ?>
    <div class="admin-alert <?= $flash['type'] === 'error' ? 'danger' : 'success' ?>"><?= Helpers::e($flash['message']) ?></div>
<?php endif; ?>
<form method="post" class="row g-3" style="max-width: 640px;">
    <?= Csrf::field() ?>
    <?php if ($row): ?><input type="hidden" name="id" value="<?= (int) $row['id'] ?>"><?php endif; ?>
    <div class="col-12">
        <label class="form-label">Branch name <span class="text-danger">*</span></label>
        <input type="text" name="name" class="form-control" required maxlength="100" value="<?= Helpers::e((string) $v('name')) ?>">
    </div>
    <div class="col-12">
        <label class="form-label">Address</label>
        <textarea name="address" class="form-control" rows="3"><?= Helpers::e((string) $v('address')) ?></textarea>
    </div>
    <div class="col-md-6">
        <label class="form-label">Phone</label>
        <input type="text" name="phone" class="form-control" maxlength="40" value="<?= Helpers::e((string) $v('phone')) ?>">
    </div>
    <div class="col-md-6">
        <label class="form-label">Display order</label>
        <input type="number" name="sort_order" class="form-control" value="<?= Helpers::e((string) $v('sort_order', '0')) ?>">
    </div>
    <div class="col-12">
        <div class="form-check">
            <input class="form-check-input" type="checkbox" name="is_active" id="is_active" value="1" <?= ((int) $v('is_active', '1')) ? 'checked' : '' ?>>
            <label class="form-check-label" for="is_active">Active (shown to customers)</label>
        </div>
    </div>
    <div class="col-12">
        <button type="submit" class="btn btn-admin-primary">Save</button>
        <a href="<?= Helpers::e(Helpers::baseUrl()) ?>/admin/branches.php" class="btn btn-admin-outline">Cancel</a>
    </div>
</form>
<?php require __DIR__ . '/layout/footer.php'; ?>
