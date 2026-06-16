<?php
require_once dirname(dirname(__DIR__)) . '/bootstrap.php';

use App\Auth;
use App\Csrf;
use App\Database;
use App\Helpers;
use App\ActivityLog;

Auth::requireAdmin();

$staffList = Database::run('SELECT id, full_name, email, phone, role, permissions, is_active, created_at FROM staff ORDER BY full_name')->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    Csrf::validateOrAbort();
    
    // Create new staff member
    if (isset($_POST['create_staff'])) {
        $name = trim($_POST['full_name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $role = in_array($_POST['role'] ?? 'attendant', ['manager', 'mechanic', 'attendant'], true) ? $_POST['role'] : 'attendant';
        $password = $_POST['password'] ?? '';
        $errors = [];
        
        if ($name === '') $errors[] = 'Full name is required.';
        if ($email === '') $errors[] = 'Email is required.';
        if (strlen($password) < 6) $errors[] = 'Password must be at least 6 characters.';
        
        if (empty($errors)) {
            $exists = Database::run('SELECT id FROM staff WHERE email = ?', [$email])->fetch();
            if ($exists) {
                $errors[] = 'A staff member with that email already exists.';
            } else {
                $hash = password_hash($password, PASSWORD_DEFAULT);
                Database::run(
                    'INSERT INTO staff (username, password, email, full_name, phone, role, is_active) VALUES (?,?,?,?,?,?,1)',
                    [$email, $hash, $email, $name, $phone ?: null, $role]
                );
                // FIXED: Use Database::pdo()->lastInsertId() instead of Database::lastInsertId()
                $staffId = (int)Database::pdo()->lastInsertId();
                ActivityLog::log('staff_created', 'staff', $staffId);
                Helpers::flash('success', 'Staff member created.');
                Helpers::redirect(Helpers::baseUrl() . '/admin/staff.php');
            }
        }
        if (!empty($errors)) {
            Helpers::flash('error', implode(' ', $errors));
            Helpers::redirect(Helpers::baseUrl() . '/admin/staff.php');
        }
    }
    
    // Update permissions
    if (isset($_POST['permissions'])) {
        $staffId = (int)$_POST['staff_id'];
        $newPerms = array_filter(array_map('trim', explode(',', $_POST['permissions'] ?? '')));
        $json = json_encode(array_values($newPerms));
        Database::run('UPDATE staff SET permissions = ? WHERE id = ?', [$json, $staffId]);
        ActivityLog::log('staff_permissions_update', 'staff', $staffId);
        Helpers::flash('success', 'Permissions updated.');
        Helpers::redirect(Helpers::baseUrl() . '/admin/staff.php');
    }
}

$pageTitle = 'Staff';
require __DIR__ . '/layout/header.php';
?>

<h1 class="admin-page-title">Staff &amp; Permissions</h1>

<?php $flash = Helpers::getFlash(); if ($flash): ?>
    <div class="admin-alert <?= $flash['type'] === 'error' ? 'danger' : 'success' ?>">
        <?= Helpers::e($flash['message']) ?>
    </div>
<?php endif; ?>

<p class="text-muted mb-3">Staff members log in with their email. Permissions are stored as JSON (e.g., maintenance, bookings, checklist).</p>

<!-- Add New Staff Form -->
<div class="card mb-4 admin-form-narrow">
    <div class="card-body">
        <h5 class="card-title">Add New Staff Member</h5>
        <form method="post" class="row g-3">
            <?= Csrf::field() ?>
            <input type="hidden" name="create_staff" value="1">
            
            <div class="col-md-6">
                <label class="form-label">Full Name <span class="text-danger">*</span></label>
                <input type="text" name="full_name" class="form-control" required>
            </div>
            
            <div class="col-md-6">
                <label class="form-label">Email <span class="text-danger">*</span></label>
                <input type="email" name="email" class="form-control" required>
            </div>
            
            <div class="col-md-6">
                <label class="form-label">Phone (optional)</label>
                <input type="text" name="phone" class="form-control">
            </div>
            
            <div class="col-md-3">
                <label class="form-label">Role</label>
                <select name="role" class="form-select">
                    <option value="attendant">Attendant</option>
                    <option value="mechanic">Mechanic</option>
                    <option value="manager">Manager</option>
                </select>
            </div>
            
            <div class="col-md-3">
                <label class="form-label">Password <span class="text-danger">*</span></label>
                <div class="input-group password-toggle-wrap">
                    <input type="password" name="password" id="staff-create-password" class="form-control" minlength="6" required autocomplete="new-password">
                    <button type="button" class="btn btn-admin-outline password-toggle-btn" aria-label="Show password">
                        <i class="bi bi-eye" aria-hidden="true"></i>
                    </button>
                </div>
                <small class="text-muted">Minimum 6 characters</small>
            </div>
            
            <div class="col-12">
                <button type="submit" class="btn btn-admin-primary">Create Staff Account</button>
            </div>
        </form>
    </div>
</div>

<!-- Staff List Table -->
<div class="admin-table-wrap">
    <div class="table-responsive">
        <table class="table admin-table">
            <thead>
                <tr>
                    <th>Name</th>
                    <th>Email</th>
                    <th>Role</th>
                    <th>Permissions</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($staffList as $s): ?>
                    <?php
                    $perms = [];
                    if (!empty($s['permissions'])) {
                        $dec = json_decode($s['permissions'], true);
                        $perms = is_array($dec) ? $dec : [];
                    }
                    ?>
                    <tr>
                        <td>
                            <strong><?= Helpers::e($s['full_name']) ?></strong>
                            <br>
                            <small class="text-muted">ID: #<?= (int)$s['id'] ?></small>
                        </td>
                        <td><?= Helpers::e($s['email']) ?></td>
                        <td>
                            <span class="status-pill <?= $s['role'] === 'manager' ? 'warning' : 'secondary' ?>">
                                <?= Helpers::e(ucfirst($s['role'])) ?>
                            </span>
                        </td>
                        <td>
                            <?php if (!empty($perms)): ?>
                                <?php foreach ($perms as $p): ?>
                                    <span class="status-pill secondary small me-1"><?= Helpers::e($p) ?></span>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <span class="text-muted">No permissions set</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <button type="button" class="btn btn-admin-outline btn-sm" data-bs-toggle="modal" data-bs-target="#permModal<?= (int)$s['id'] ?>">
                                <i class="bi bi-pencil me-1"></i>Edit Permissions
                            </button>
                            
                            <!-- Edit Permissions Modal -->
                            <div class="modal fade" id="permModal<?= (int)$s['id'] ?>" tabindex="-1">
                                <div class="modal-dialog">
                                    <div class="modal-content" style="background:var(--admin-surface);border:1px solid var(--admin-border);color:var(--admin-text);">
                                        <div class="modal-header border-secondary">
                                            <h5 class="modal-title">Permissions: <?= Helpers::e($s['full_name']) ?></h5>
                                            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                                        </div>
                                        <form method="post">
                                            <?= Csrf::field() ?>
                                            <input type="hidden" name="staff_id" value="<?= (int)$s['id'] ?>">
                                            <div class="modal-body">
                                                <label class="form-label">Comma-separated permissions</label>
                                                <input type="text" name="permissions" class="form-control" 
                                                       value="<?= Helpers::e(implode(', ', $perms)) ?>" 
                                                       placeholder="maintenance, bookings, checklist, reports">
                                                <small class="text-muted d-block mt-2">
                                                    Available: maintenance, bookings, checklist, reports, customers
                                                </small>
                                            </div>
                                            <div class="modal-footer border-secondary">
                                                <button type="button" class="btn btn-admin-outline" data-bs-dismiss="modal">Cancel</button>
                                                <button type="submit" class="btn btn-admin-primary">Save Permissions</button>
                                            </div>
                                        </form>
                                    </div>
                                </div>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
                
                <?php if (empty($staffList)): ?>
                    <tr>
                        <td colspan="5" class="text-muted py-4 text-center">
                            <i class="bi bi-person-badge display-6 d-block mb-2"></i>
                            No staff members found. Create your first staff member above.
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<script>
// Initialize password toggle buttons
document.querySelectorAll('.password-toggle-btn').forEach(function(btn) {
    btn.addEventListener('click', function() {
        var input = this.closest('.input-group').querySelector('input');
        if (input) {
            var type = input.getAttribute('type') === 'password' ? 'text' : 'password';
            input.setAttribute('type', type);
            var icon = this.querySelector('i');
            if (icon) {
                icon.classList.toggle('bi-eye');
                icon.classList.toggle('bi-eye-slash');
            }
        }
    });
});
</script>

<?php require __DIR__ . '/layout/footer.php'; ?>