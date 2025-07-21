<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// Composer autoloader
require_once __DIR__ . '/../vendor/autoload.php';

function sendEmail($to, $subject, $body, $from = 'no-reply@ecoride.example.com', $fromName = 'EcoRide') {
    $mail = new PHPMailer(true);

    try {
        // Server settings
        $mail->isSMTP();
        $mail->Host       = 'smtp.example.com';  // Replace with your SMTP host
        $mail->SMTPAuth   = true;
        $mail->Username   = 'your@email.com';    // SMTP username
        $mail->Password   = 'your_password';     // SMTP password
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = 587;

        // Recipients
        $mail->setFrom($from, $fromName);
        $mail->addAddress($to);

        // Content
        $mail->isHTML(false); // Set to true if sending HTML
        $mail->Subject = $subject;
        $mail->Body    = $body;

        $mail->send();
        return true;
    } catch (Exception $e) {
        error_log("Email error: {$mail->ErrorInfo}");
        return false;
    }
}
