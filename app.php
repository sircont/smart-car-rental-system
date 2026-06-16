<?php
return [
    'env' => getenv('APP_ENV') ?: 'development',
    'debug' => (bool)(getenv('APP_DEBUG') ?: true),
    'url' => getenv('APP_URL') ?: 'http://localhost/Car_Rental_System/public',
    'timezone' => 'Africa/Accra',
    'csrf_key' => getenv('CSRF_KEY') ?: 'car_rental_csrf_secret_change_in_production',
    'session_lifetime' => 7200,
    'support_email' => getenv('SUPPORT_EMAIL') ?: 'info@drivesmartrentals.gh',
    'support_phone' => getenv('SUPPORT_PHONE') ?: '+233 50 1029 863 ',
    'toll_free' => getenv('TOLL_FREE') ?: '1029 863',
    'whatsapp_number' => getenv('WHATSAPP_NUMBER') ?: '', /* e.g. +233 50 1029 863 for Ghana */
];
