<?php
/**
 * SMS configuration (Twilio or other provider)
 * Use for booking confirmations, alerts.
 */
return [
    'enabled' => (bool)(getenv('SMS_ENABLED') ?: false),
    'provider' => getenv('SMS_PROVIDER') ?: 'twilio',
    'twilio_sid' => getenv('TWILIO_SID') ?: '',
    'twilio_token' => getenv('TWILIO_TOKEN') ?: '',
    'twilio_from' => getenv('TWILIO_FROM') ?: '',
];
