<?php
session_start();
require 'db.php';
require_once 'csrf.php';
require_once __DIR__ . '/functions.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

generateCSRFToken(); // Ensures token exists in session

$userId = $_SESSION['user_id'];
$rideId = isset($_POST['carpool_id']) ? intval($_POST['carpool_id']) : 0;
$csrfToken = $_POST['csrf_token'] ?? '';

if ($rideId <= 0) {
    die('Trajet invalide.');
}

if (!verifyCSRFToken($csrfToken)) {
    die('Token CSRF invalide.');
}

try {
    $booking = getUserBooking($pdo, $rideId, $userId);
    if (!$booking) {
        die('Réservation introuvable.');
    }

    cancelBooking($pdo, $booking['id'], $rideId);

    header('Location: ../details.php?id=' . $rideId . '&cancel=success');
    exit;

} catch (Exception $e) {
    die('Erreur lors de l\'annulation : ' . $e->getMessage());
}
