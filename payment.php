<?php
/**
 * Payment gateway configuration (Flutterwave, Paystack)
 * Use in payment.php for redirect/verify.
 */
return [
    'flutterwave_public_key' => getenv('FLUTTERWAVE_PUBLIC_KEY') ?: '',
    'flutterwave_secret_key' => getenv('FLUTTERWAVE_SECRET_KEY') ?: '',
    'paystack_public_key' => getenv('PAYSTACK_PUBLIC_KEY') ?: '',
    'paystack_secret_key' => getenv('PAYSTACK_SECRET_KEY') ?: '',
    'currency' => getenv('PAYMENT_CURRENCY') ?: 'GHS',
];
