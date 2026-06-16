<?php
namespace App;

final class ActivityLog
{
    /** SSRN: activity_logs has user_id, staff_id, admin_id; no details column, use new_values JSON. */
    public static function log(string $action, ?string $entityType = null, ?int $entityId = null, ?string $details = null): void
    {
        $role = Auth::role();
        $id = Auth::id();
        $ip = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? null;
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? null;
        $newValues = $details !== null ? json_encode(['details' => $details]) : null;

        $userId = $role === 'customer' ? $id : null;
        $staffId = $role === 'staff' ? $id : null;
        $adminId = $role === 'admin' ? $id : null;

        Database::run(
            'INSERT INTO activity_logs (user_id, staff_id, admin_id, action, entity_type, entity_id, new_values, ip_address, user_agent) VALUES (?,?,?,?,?,?,?,?,?)',
            [$userId, $staffId, $adminId, $action, $entityType, $entityId, $newValues, $ip, $userAgent]
        );
    }
}
