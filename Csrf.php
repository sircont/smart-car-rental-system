<?php
namespace App;

final class Csrf
{
    private static string $key = 'csrf_token';

    public static function init(string $secret): void
    {
        self::$key = $secret;
    }

    public static function token(): string
    {
        if (empty($_SESSION['_csrf'])) {
            $_SESSION['_csrf'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['_csrf'];
    }

    public static function field(): string
    {
        return '<input type="hidden" name="_csrf" value="' . htmlspecialchars(self::token()) . '">';
    }

    public static function validateOrAbort(): void
    {
        $token = $_POST['_csrf'] ?? $_GET['_csrf'] ?? '';
        if (!hash_equals(self::token(), $token)) {
            http_response_code(403);
            die('Invalid security token. Please try again.');
        }
    }
}
