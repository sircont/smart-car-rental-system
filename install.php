<?php
/**
 * One-time setup: create database and run schema.
 * Run in browser: http://localhost/Car_Rental_System/public/install.php
 * DELETE this file after successful install for security.
 */
$config = require dirname(__DIR__) . '/config/database.php';
$host = $config['host'];
$port = $config['port'];
$name = $config['name'];
$user = $config['user'];
$pass = $config['pass'];

try {
    $dsn = "mysql:host=$host;port=$port;charset=utf8mb4";
    $pdo = new PDO($dsn, $user, $pass, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    $pdo->exec("CREATE DATABASE IF NOT EXISTS `$name` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    $pdo->exec("USE `$name`");

    $schema = file_get_contents(dirname(__DIR__) . '/database/schema.sql');
    // Remove comment lines and split into statements (semicolon at end of line)
    $schema = preg_replace('/^\s*--.*$/m', '', $schema);
    $statements = array_filter(array_map('trim', preg_split('/;\s*[\r\n]+/', $schema)));
    foreach ($statements as $sql) {
        $sql = trim($sql);
        if ($sql === '') continue;
        // Skip CREATE DATABASE and USE (already done)
        if (preg_match('/^CREATE DATABASE/i', $sql)) continue;
        if (preg_match('/^USE\s+/i', $sql)) continue;
        $pdo->exec($sql);
    }

    echo "<!DOCTYPE html><html><head><meta charset='UTF-8'><title>Install</title></head><body>";
    echo "<p style='color:green;'><strong>Database \"" . htmlspecialchars($name) . "\" created and schema applied successfully.</strong></p>";
    echo "<p>Delete <code>public/install.php</code> for security, then <a href='index.php'>go to the site</a>.</p>";
    echo "</body></html>";
} catch (PDOException $e) {
    echo "<!DOCTYPE html><html><head><meta charset='UTF-8'><title>Install Error</title></head><body>";
    echo "<p style='color:red;'>Error: " . htmlspecialchars($e->getMessage()) . "</p>";
    echo "<p>Check config/database.php (host, user, password) and that MySQL is running.</p>";
    echo "</body></html>";
}
