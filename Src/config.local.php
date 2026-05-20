<?php
declare(strict_types=1);

return [
  'app' => [
    'base_url' => '',
  ],
  'otp' => [
    // Choose 'smtp' for Gmail/Outlook SMTP, or 'resend' for Resend API.
    'provider' => 'smtp',

    // Turn this off after your real email delivery is working.
    'debug_show_code' => false,

    // This email should match your SMTP sender account or verified domain sender.
    'from_email' => 'rainurey@gmail.com',
    'from_name' => 'Expense Tracker',
  ],
  'smtp' => [
    'host' => 'smtp.gmail.com',
    'port' => 587,
    'username' => 'rainurey@gmail.com',
    'password' => 'npgerlafwbjoesit',
    'encryption' => 'tls',
    'auth' => true,
  ],
  'ai' => [
    'enabled' => true,
    'provider' => 'gemini',
    'gemini_api_key' => 'AIzaSyAbbZUyScAVZB7BVKbYetfJMJy5Bq9nJUI',
    'gemini_model' => 'gemini-2.0-flash',
    'gemini_timeout_seconds' => 30,
  ],
];
