<?php
/**
 * Email configuration (PHPMailer / SMTP)
 * Use for booking confirmations, password reset, contact replies.
 */
return [
    'enabled' => (bool)(getenv('MAIL_ENABLED') ?: false),
    'driver' => getenv('MAIL_DRIVER') ?: 'smtp',
    'host' => getenv('MAIL_HOST') ?: 'smtp.gmail.com',
    'port' => (int)(getenv('MAIL_PORT') ?: 587),
    'encryption' => getenv('MAIL_ENCRYPTION') ?: 'tls',
    'username' => getenv('MAIL_USERNAME') ?: '',
    'password' => getenv('MAIL_PASSWORD') ?: '',
    'from_address' => getenv('MAIL_FROM_ADDRESS') ?: 'noreply@drivesmartrentals.gh',
    'from_name' => getenv('MAIL_FROM_NAME') ?: 'DriveSmart Rentals',
];
