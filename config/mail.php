<?php
/**
 * SMTP / email settings for outgoing notifications (e.g. a new missed-date
 * fill request → email to the Admin).
 *
 * Email is DISABLED until you set 'enabled' => true and fill in real SMTP
 * credentials below. While disabled (or if PHPMailer isn't installed), the
 * app still works — notifications are written to logs/mail_outbox.log instead
 * of being sent, so nothing breaks and you can see what would have gone out.
 *
 * To actually send:
 *   1. Install PHPMailer (either `composer require phpmailer/phpmailer`, which
 *      creates vendor/, or drop the library into lib/PHPMailer/src/).
 *   2. Fill host/port/username/password with your company or Gmail SMTP.
 *      (Gmail needs an App Password, not your normal password.)
 *   3. Set 'enabled' => true.
 */
return [
    'enabled'    => false,

    'host'       => 'smtp.example.com', // e.g. smtp.gmail.com or your Yanmar relay
    'port'       => 587,
    'secure'     => 'tls',              // 'tls' (587), 'ssl' (465), or '' for none
    'username'   => '',
    'password'   => '',

    'from_email' => 'no-reply@yanmar.com',
    'from_name'  => 'Daily Production Checksheet',

    // Where request notifications are sent.
    'admin_email' => 'sintiara_zharadiva@yanmar.com',
    'admin_name'  => 'Sintiara Zharadiva',
];
