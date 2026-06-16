<?php
require_once dirname(dirname(__DIR__)) . '/bootstrap.php';

use App\Auth;
use App\Database;
use App\Helpers;

Auth::requireAdmin();

$logs = Database::run(
    'SELECT al.*,
      COALESCE(u.full_name, s.full_name, a.full_name, a.username) AS actor_name
     FROM activity_logs al
     LEFT JOIN users u ON al.user_id = u.id
     LEFT JOIN staff s ON al.staff_id = s.id
     LEFT JOIN admin a ON al.admin_id = a.id
     ORDER BY al.created_at DESC LIMIT 200'
)->fetchAll();

$pageTitle = 'Activity log';
require __DIR__ . '/layout/header.php';
?>
<h1 class="admin-page-title">Activity log</h1>
<div class="admin-table-wrap">
    <div class="table-responsive">
        <table class="table admin-table">
            <thead><tr><th>Time</th><th>User</th><th>Action</th><th>Entity</th><th>Details</th></tr></thead>
            <tbody>
                <?php foreach ($logs as $l): ?>
                    <?php
                    $details = '';
                    if (!empty($l['new_values'])) {
                        $dec = json_decode($l['new_values'], true);
                        $details = is_array($dec) && isset($dec['details']) ? $dec['details'] : $l['new_values'];
                    }
                    ?>
                    <tr>
                        <td><?= date('M j, Y H:i:s', strtotime($l['created_at'])) ?></td>
                        <td><?= Helpers::e($l['actor_name'] ?? '—') ?></td>
                        <td><?= Helpers::e($l['action']) ?></td>
                        <td><?= $l['entity_type'] ? Helpers::e($l['entity_type']) . ' #' . (int)$l['entity_id'] : '—' ?></td>
                        <td><?= Helpers::e(mb_substr($details, 0, 80)) ?></td>
                    </tr>
                <?php endforeach; ?>
                <?php if (empty($logs)): ?><tr><td colspan="5" class="text-muted py-4 text-center">No activity logged yet.</td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
<?php require __DIR__ . '/layout/footer.php'; ?>
