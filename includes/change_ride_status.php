<?php
session_start();
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/csrf.php';
require_once __DIR__ . '/functions.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit;
}

$userId = $_SESSION['user_id'];

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../profile.php');
    exit;
}

if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
    die('CSRF token invalide.');
}

$ride_id = (int)($_POST['ride_id'] ?? 0);
$new_status = $_POST['new_status'] ?? '';

if (!in_array($new_status, ['started', 'ended'], true)) {
    die('Statut invalide.');
}

// Verify user is driver of the ride
$ride = isUserRideDriver($pdo, $ride_id, $userId);
if (!$ride) {
    die('Trajet introuvable ou vous n’êtes pas le conducteur.');
}

$current_status = $ride['status'];

// Enforce status transitions
if (!isValidRideStatusTransition($current_status, $new_status)) {
    die("Transition de statut invalide.");
}

// Update status
$stmt = $pdo->prepare("UPDATE rides SET status = ? WHERE id = ?");
$stmt->execute([$new_status, $ride_id]);

// If ended, send emails to passengers
if ($new_status === 'ended') {
    notifyRideEndedPassengers($pdo, $ride_id);
}

header('Location: ../profile.php');
exit;
