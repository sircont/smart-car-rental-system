<?php
namespace App;

/**
 * Booking lifecycle helpers (e.g. completing a rental after return).
 */
final class Booking
{
    /**
     * Adds return_requested_at if missing (older DBs before migration 004).
     * Safe to call every request; runs at most one ALTER per process.
     */
    public static function ensureReturnRequestedColumn(): void
    {
        static $done = false;
        if ($done) {
            return;
        }
        $done = true;
        $add = function (string $sql): bool {
            try {
                Database::pdo()->exec($sql);
                return true;
            } catch (\PDOException $e) {
                $msg = $e->getMessage();
                if (strpos($msg, '1060') !== false
                    || stripos($msg, 'Duplicate column') !== false
                    || stripos($msg, 'duplicate column name') !== false) {
                    return true;
                }
                return false;
            }
        };
        if (!$add('ALTER TABLE bookings ADD COLUMN return_requested_at DATETIME NULL DEFAULT NULL AFTER cancelled_at')) {
            $add('ALTER TABLE bookings ADD COLUMN return_requested_at DATETIME NULL DEFAULT NULL');
        }
    }

    /**
     * Ensures booking_status ENUM includes "returned" (customer-reported return).
     */
    public static function ensureReturnedBookingStatus(): void
    {
        static $done = false;
        if ($done) {
            return;
        }
        $done = true;
        try {
            Database::pdo()->exec(
                "ALTER TABLE bookings MODIFY COLUMN booking_status ENUM("
                . "'pending','confirmed','active','returned','completed','cancelled','no_show'"
                . ") DEFAULT 'pending'"
            );
        } catch (\PDOException $e) {
            // ignore (already migrated or insufficient privileges)
        }
    }

    public static function ensureReturnFlowSchema(): void
    {
        self::ensureReturnRequestedColumn();
        self::ensureReturnedBookingStatus();
    }

    /** Set booking completed, clear return request flag, mark vehicle available for rent. */
    public static function markCompleted(int $bookingId): bool
    {
        self::ensureReturnFlowSchema();
        $b = Database::run('SELECT vehicle_id FROM bookings WHERE id = ?', [$bookingId])->fetch();
        if (!$b) {
            return false;
        }
        Database::run(
            'UPDATE bookings SET booking_status = ?, return_requested_at = NULL WHERE id = ?',
            ['completed', $bookingId]
        );
        Database::run('UPDATE vehicles SET is_available = 1 WHERE id = ?', [(int) $b['vehicle_id']]);
        Notification::dismissReturnAlertsForBooking($bookingId);
        return true;
    }
}
