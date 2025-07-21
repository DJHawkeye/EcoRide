<?php
session_start();
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/send_email.php';
require_once __DIR__ . '/functions.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit;
}

$userId = $_SESSION['user_id'];
$rideId = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($rideId <= 0) {
    header('Location: ../profile.php');
    exit;
}

$ride = getUserRide($pdo, $rideId, $userId);
if (!$ride) {
    header('Location: ../profile.php?error=unauthorized');
    exit;
}

try {
    $passengers = getRidePassengers($pdo, $rideId);
    cancelRide($pdo, $rideId);
    notifyPassengersRideCancelled($passengers, $ride);

    header('Location: ../profile.php?cancelled=1');
    exit;

} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log("Erreur lors de l'annulation du trajet: " . $e->getMessage());
    header('Location: ../profile.php?error=cancel_failed');
    exit;
}
