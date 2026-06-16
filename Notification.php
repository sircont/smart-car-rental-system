<?php
namespace App;

/**
 * Notifications for admin, staff (e.g. vehicle in for maintenance) and users (e.g. return reminder).
 * Uses notifications table: admin_id, staff_id, user_id nullable; NULL = broadcast to all of that role.
 */
final class Notification
{
    /** @var bool */
    private static $prunedStaleReturnAlerts = false;

    /** Customer reported returning the car — notify all admins and staff to confirm in Bookings. */
    public static function notifyReturnRequested(int $bookingId, string $customerName, string $vehicleInfo, ?string $bookingNumber = null): void
    {
        $ref = $bookingNumber !== null && $bookingNumber !== ''
            ? ' (ref ' . $bookingNumber . ')'
            : ' (booking #' . $bookingId . ')';
        $title = 'Customer reported vehicle return';
        // Trailing tag so dismiss always matches (message may use ref code instead of numeric #id in text)
        $tag = ' [booking:' . (int) $bookingId . ']';
        $message = $customerName . ' reported returning ' . $vehicleInfo . $ref . '. Confirm return in Bookings.' . $tag;
        Database::run(
            'INSERT INTO notifications (admin_id, staff_id, type, title, message, data) VALUES (NULL, NULL, ?, ?, ?, ?)',
            ['alert', $title, $message, json_encode(['booking_id' => $bookingId, 'kind' => 'return_request'])]
        );
    }

    /** Alert all admins and staff when a vehicle is in for maintenance/work. */
    public static function notifyVehicleMaintenance(int $maintenanceId, string $vehicleInfo, string $title = 'Vehicle in for maintenance'): void
    {
        $message = 'Vehicle: ' . $vehicleInfo . '. Please check the maintenance task.';
        Database::run(
            'INSERT INTO notifications (admin_id, staff_id, type, title, message, data) VALUES (NULL, NULL, ?, ?, ?, ?)',
            ['alert', $title, $message, json_encode(['maintenance_id' => $maintenanceId])]
        );
    }

    /** Get unread notifications for current admin (broadcast + own). */
    public static function getUnreadForAdmin(): array
    {
        $id = Auth::id();
        if (!$id || !Auth::isAdmin()) {
            return [];
        }
        self::pruneStaleReturnRequestAlerts();
        return Database::run(
            'SELECT * FROM notifications WHERE type = ? AND COALESCE(is_read, 0) = 0 AND (admin_id IS NULL OR admin_id = ?) ORDER BY created_at DESC LIMIT 20',
            ['alert', $id]
        )->fetchAll(\PDO::FETCH_ASSOC);
    }

    /** Get unread notifications for current staff (broadcast + own). */
    public static function getUnreadForStaff(): array
    {
        $id = Auth::id();
        if (!$id || !Auth::isStaff()) {
            return [];
        }
        self::pruneStaleReturnRequestAlerts();
        return Database::run(
            'SELECT * FROM notifications WHERE type = ? AND COALESCE(is_read, 0) = 0 AND (staff_id IS NULL OR staff_id = ?) ORDER BY created_at DESC LIMIT 20',
            ['alert', $id]
        )->fetchAll(\PDO::FETCH_ASSOC);
    }

    /** Mark a notification as read. */
    public static function markRead(int $notificationId): void
    {
        Database::run('UPDATE notifications SET is_read = 1, read_at = NOW() WHERE id = ?', [$notificationId]);
    }

    /**
     * Mark read any stale "return requested" broadcast rows whose booking is already completed.
     * Heals missed dismissals (e.g. JSON matching failed on confirm). Runs once per request.
     */
    private static function pruneStaleReturnRequestAlerts(): void
    {
        if (self::$prunedStaleReturnAlerts) {
            return;
        }
        self::$prunedStaleReturnAlerts = true;
        $title = 'Customer reported vehicle return';
        try {
            $rows = Database::run(
                "SELECT id, data, message FROM notifications WHERE type = 'alert' AND COALESCE(is_read, 0) = 0 AND title = ?",
                [$title]
            )->fetchAll(\PDO::FETCH_ASSOC);
        } catch (\Throwable $e) {
            return;
        }
        foreach ($rows as $r) {
            $bid = self::extractBookingIdFromReturnAlertRow($r);
            if ($bid < 1) {
                continue;
            }
            $status = Database::run('SELECT booking_status FROM bookings WHERE id = ?', [$bid])->fetchColumn();
            if ($status === 'completed') {
                Database::run('UPDATE notifications SET is_read = 1, read_at = NOW() WHERE id = ?', [(int) $r['id']]);
            }
        }
    }

    private static function extractBookingIdFromReturnAlertRow(array $r): int
    {
        $msg = (string) ($r['message'] ?? '');
        if (preg_match('/\[booking:(\d+)\]/', $msg, $m)) {
            return (int) $m[1];
        }
        if (preg_match('/\(booking #(\d+)\)/', $msg, $m)) {
            return (int) $m[1];
        }
        $raw = $r['data'] ?? null;
        if (is_string($raw)) {
            $data = json_decode($raw, true);
        } elseif (is_array($raw)) {
            $data = $raw;
        } else {
            $data = null;
        }
        if (is_array($data) && isset($data['booking_id'])) {
            return (int) $data['booking_id'];
        }

        return 0;
    }

    /**
     * When return is confirmed (booking completed), mark matching alerts read for everyone
     * (single broadcast row is shared by admin + staff dashboards).
     */
    public static function dismissReturnAlertsForBooking(int $bookingId): void
    {
        $bookingId = (int) $bookingId;
        if ($bookingId < 1) {
            return;
        }
        $title = 'Customer reported vehicle return';
        $tag = '[booking:' . $bookingId . ']';
        $likeLegacy = '%(booking #' . $bookingId . ')%';
        $likeJsonCompact = '%"booking_id":' . $bookingId . '%';
        $likeJsonSpaced = '%"booking_id": ' . $bookingId . '%';

        // 1) SQL: match message tag / legacy ref / JSON substring (no JSON_VALID — MariaDB/MySQL differ)
        try {
            Database::run(
                "UPDATE notifications SET is_read = 1, read_at = NOW()
                 WHERE type = 'alert'
                 AND COALESCE(is_read, 0) = 0
                 AND title = ?
                 AND (
                     LOCATE(?, message) > 0
                     OR message LIKE ?
                     OR (data IS NOT NULL AND CAST(data AS CHAR(16383)) LIKE ?)
                     OR (data IS NOT NULL AND CAST(data AS CHAR(16383)) LIKE ?)
                 )",
                [$title, $tag, $likeLegacy, $likeJsonCompact, $likeJsonSpaced]
            );
        } catch (\Throwable $e) {
            // CAST/LIKE may fail on odd server configs — PHP scan below
        }

        // 2) PHP scan: double-encoded JSON, JSON column returned oddly, or SQL partial failure
        try {
            $rows = Database::run(
                "SELECT id, data, message FROM notifications WHERE type = 'alert' AND COALESCE(is_read, 0) = 0 AND title = ?",
                [$title]
            )->fetchAll(\PDO::FETCH_ASSOC);
        } catch (\Throwable $e) {
            return;
        }
        foreach ($rows as $r) {
            $match = false;
            $msg = (string) ($r['message'] ?? '');
            if (strpos($msg, $tag) !== false || strpos($msg, '(booking #' . $bookingId . ')') !== false) {
                $match = true;
            }
            if (!$match) {
                $raw = $r['data'] ?? null;
                if (is_string($raw)) {
                    $data = json_decode($raw, true);
                } elseif (is_array($raw)) {
                    $data = $raw;
                } else {
                    $data = null;
                }
                if (is_array($data)
                    && ($data['kind'] ?? '') === 'return_request'
                    && (int) ($data['booking_id'] ?? 0) === $bookingId) {
                    $match = true;
                }
            }
            if ($match) {
                Database::run('UPDATE notifications SET is_read = 1, read_at = NOW() WHERE id = ?', [(int) $r['id']]);
            }
        }
    }

    /** Get active bookings for user where return date is today or tomorrow (for reminder banner). */
    public static function getReturnDueForUser(int $userId): array
    {
        return Database::run(
            'SELECT b.id, b.booking_number, b.return_date, b.pickup_date, v.model AS vehicle_model, br.name AS brand_name
             FROM bookings b
             JOIN vehicles v ON b.vehicle_id = v.id
             JOIN brands br ON v.brand_id = br.id
             WHERE b.user_id = ? AND b.payment_status = ? AND b.booking_status IN (\'confirmed\',\'active\')
             AND DATE(b.return_date) BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 1 DAY)
             ORDER BY b.return_date ASC',
            [$userId, 'paid']
        )->fetchAll(\PDO::FETCH_ASSOC);
    }
}
