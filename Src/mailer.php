<?php
declare(strict_types=1);

require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/../vendor/autoload.php';

use PHPMailer\PHPMailer\Exception;
use PHPMailer\PHPMailer\PHPMailer;

function send_email_with_smtp(string $toEmail, string $toName, string $subject, string $message): array
{
  $host = str_trim((string)config('smtp.host', ''));
  $port = (int)config('smtp.port', 587);
  $username = str_trim((string)config('smtp.username', ''));
  $password = (string)config('smtp.password', '');
  $encryption = strtolower(str_trim((string)config('smtp.encryption', 'tls')));
  $smtpAuth = (bool)config('smtp.auth', true);
  $fromEmail = str_trim((string)config('otp.from_email', ''));
  $fromName = str_trim((string)config('otp.from_name', 'Expense Tracker'));

  if ($host === '') {
    return ['ok' => false, 'message' => 'SMTP host is missing. Update Src/config.local.php.'];
  }

  if ($fromEmail === '') {
    return ['ok' => false, 'message' => 'SMTP sender email is missing. Update Src/config.local.php.'];
  }

  if ($smtpAuth && ($username === '' || $password === '')) {
    return ['ok' => false, 'message' => 'SMTP username or password is missing. Update Src/config.local.php.'];
  }

  try {
    $mail = new PHPMailer(true);
    $mail->isSMTP();
    $mail->Host = $host;
    $mail->Port = $port > 0 ? $port : 587;
    $mail->SMTPAuth = $smtpAuth;

    if ($smtpAuth) {
      $mail->Username = $username;
      $mail->Password = $password;
    }

    if ($encryption === 'ssl') {
      $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
    } elseif ($encryption === 'tls') {
      $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
    }

    $mail->CharSet = 'UTF-8';
    $mail->setFrom($fromEmail, $fromName !== '' ? $fromName : 'Expense Tracker');
    $mail->addAddress($toEmail, $toName);
    $mail->Subject = $subject;
    $mail->Body = $message;
    $mail->isHTML(false);
    $mail->send();

    return ['ok' => true, 'message' => 'OTP sent to your email address.'];
  } catch (Exception $e) {
    return ['ok' => false, 'message' => 'SMTP send failed: ' . $e->getMessage()];
  }
}
