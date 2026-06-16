<?php
namespace App;

final class Auth
{
    private const SESSION_USER = 'user_id';
    private const SESSION_ROLE = 'user_role';

    public static function login(int $userId, string $role): void
    {
        $_SESSION[self::SESSION_USER] = $userId;
        $_SESSION[self::SESSION_ROLE] = $role;
    }

    public static function logout(): void
    {
        unset($_SESSION[self::SESSION_USER], $_SESSION[self::SESSION_ROLE]);
    }

    public static function id(): ?int
    {
        return isset($_SESSION[self::SESSION_USER]) ? (int) $_SESSION[self::SESSION_USER] : null;
    }

    public static function role(): ?string
    {
        return $_SESSION[self::SESSION_ROLE] ?? null;
    }

    public static function check(): bool
    {
        return self::id() !== null;
    }

    public static function isAdmin(): bool
    {
        return self::role() === 'admin';
    }

    public static function isStaff(): bool
    {
        return self::role() === 'staff';
    }

    public static function requireLogin(): void
    {
        if (!self::check()) {
            Helpers::flash('error', 'Please log in to continue.');
            Helpers::redirect(Helpers::baseUrl() . '/login.php');
        }
    }

    public static function requireAdmin(): void
    {
        self::requireLogin();
        if (!self::isAdmin()) {
            Helpers::flash('error', 'Access denied.');
            Helpers::redirect(Helpers::baseUrl() . '/index.php');
        }
    }

    public static function requireStaff(): void
    {
        self::requireLogin();
        if (!self::isStaff() && !self::isAdmin()) {
            Helpers::flash('error', 'Access denied.');
            Helpers::redirect(Helpers::baseUrl() . '/index.php');
        }
    }

    /** Check if staff user has a specific permission (admin has all). SSRN: staff.permissions JSON. */
    public static function can(string $permission): bool
    {
        if (self::isAdmin()) {
            return true;
        }
        if (!self::isStaff() || self::id() === null) {
            return false;
        }
        $row = Database::run('SELECT permissions FROM staff WHERE id = ?', [self::id()])->fetch();
        if (!$row || empty($row['permissions'])) {
            return false;
        }
        $perms = json_decode($row['permissions'], true);
        return is_array($perms) && in_array($permission, $perms, true);
    }

    /** Load current user info from admin, staff, or users table (SSRN). Returns name, email, phone, role. */
    public static function user(): ?array
    {
        $id = self::id();
        $role = self::role();
        if ($id === null) {
            return null;
        }
        if ($role === 'admin') {
            $row = Database::run('SELECT id, full_name, email FROM admin WHERE id = ?', [$id])->fetch();
            return $row ? ['id' => (int)$row['id'], 'name' => $row['full_name'] ?? $row['email'], 'email' => $row['email'], 'phone' => null, 'role' => 'admin'] : null;
        }
        if ($role === 'staff') {
            $row = Database::run('SELECT id, full_name, email, phone FROM staff WHERE id = ?', [$id])->fetch();
            return $row ? ['id' => (int)$row['id'], 'name' => $row['full_name'] ?? $row['email'], 'email' => $row['email'], 'phone' => $row['phone'] ?? null, 'role' => 'staff'] : null;
        }
        $row = Database::run('SELECT id, full_name, email, phone, profile_image FROM users WHERE id = ?', [$id])->fetch();
        return $row ? ['id' => (int)$row['id'], 'name' => $row['full_name'] ?? $row['email'], 'email' => $row['email'], 'phone' => $row['phone'] ?? null, 'profile_image' => $row['profile_image'] ?? 'default.png', 'role' => 'customer'] : null;
    }
}
