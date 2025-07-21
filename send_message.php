<?php
session_start();
require_once 'includes/db.php';
require_once 'includes/csrf.php';
require 'vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;

if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
    die("Token CSRF invalide.");
}

$toEmail = $_POST['to_email'] ?? '';
$toName = $_POST['to_name'] ?? '';
$subject = $_POST['subject'] ?? '';
$message = $_POST['message'] ?? '';

if (!filter_var($toEmail, FILTER_VALIDATE_EMAIL)) {
    die("Email invalide.");
}

// Get employee's email
$employeeId = $_SESSION['user_id'];
$stmt = $pdo->prepare("SELECT email FROM users WHERE id = ?");
$stmt->execute([$employeeId]);
$employeeEmail = $stmt->fetchColumn();

if (!$employeeEmail) {
    die("Adresse email employé introuvable.");
}

// Send email
$mail = new PHPMailer(true);
try {
    $mail->isSMTP();
    $mail->Host = 'smtp.yourdomain.com'; // replace
    $mail->SMTPAuth = true;
    $mail->Username = 'noreply@yourdomain.com'; // SMTP account
    $mail->Password = 'yourpassword';
    $mail->SMTPSecure = 'tls';
    $mail->Port = 587;

    $mail->setFrom($employeeEmail, 'Employé EcoRide');
    $mail->addAddress($toEmail, $toName);
    $mail->Subject = $subject;
    $mail->Body = $message;

    $mail->send();
    header('Location: employee_space.php?email=sent');
    exit;

} catch (Exception $e) {
    die("Erreur d'envoi : " . $mail->ErrorInfo);
}
