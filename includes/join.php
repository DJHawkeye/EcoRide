<?php
session_start();
require_once 'db.php';
require_once 'csrf.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

generateCSRFToken(); // Ensure token is generated for session

$userId = $_SESSION['user_id'];
$rideId = isset($_POST['carpool_id']) ? intval($_POST['carpool_id']) : 0;
$csrfToken = $_POST['csrf_token'] ?? '';

if (!$rideId) {
    die('Trajet invalide.');
}

if (!verifyCSRFToken($csrfToken)) {
    die('Token CSRF invalide.');
}

// Fetch ride info
$stmt = $pdo->prepare("SELECT driver_id, seats_available, price FROM rides WHERE id = ?");
$stmt->execute([$rideId]);
$ride = $stmt->fetch();

if (!$ride) {
    die('Trajet non trouvé.');
}

// Prevent driver from booking own ride
if ($ride['driver_id'] == $userId) {
    die('Vous ne pouvez pas réserver votre propre trajet.');
}

// Check user role
$stmt = $pdo->prepare("SELECT is_passenger FROM users WHERE id = ?");
$stmt->execute([$userId]);
$isPassenger = (bool)$stmt->fetchColumn();

if (!$isPassenger) {
    die('Seuls les utilisateurs avec un rôle passager peuvent réserver un trajet.');
}

// Prevent duplicate booking
$stmt = $pdo->prepare("SELECT id FROM bookings WHERE ride_id = ? AND passenger_id = ?");
$stmt->execute([$rideId, $userId]);
if ($stmt->fetch()) {
    die('Vous avez déjà réservé une place pour ce trajet.');
}

// Check available seats
if ($ride['seats_available'] < 1) {
    die('Aucune place disponible pour ce trajet.');
}

// Fetch user's credit balance
$stmt = $pdo->prepare("SELECT credits FROM users WHERE id = ?");
$stmt->execute([$userId]);
$user = $stmt->fetch();

if (!$user) {
    die('Utilisateur introuvable.');
}

// Calculate user's total pending booking costs
$stmt = $pdo->prepare("
    SELECT COALESCE(SUM(r.price), 0) AS pending_total
    FROM rides r
    JOIN bookings b ON b.ride_id = r.id
    LEFT JOIN reviews rv ON rv.ride_id = r.id AND rv.passenger_id = b.passenger_id
    WHERE b.passenger_id = ? AND (rv.status IS NULL OR rv.status = 'pending')
");
$stmt->execute([$userId]);
$pendingTotal = (float)$stmt->fetchColumn();

$effectiveCredits = $user['credits'] - $pendingTotal;

// Ensure they have enough credits to reserve this ride
if ($effectiveCredits < $ride['price']) {
    header('Location: ../details.php?id=' . $rideId . '&booking=insufficient');
    exit;
}

// Transaction: book ride and decrement seats
try {
    $pdo->beginTransaction();

    $insert = $pdo->prepare("INSERT INTO bookings (ride_id, passenger_id, seats_booked, booked_at) VALUES (?, ?, 1, NOW())");
    $insert->execute([$rideId, $userId]);

    $update = $pdo->prepare("UPDATE rides SET seats_available = seats_available - 1 WHERE id = ? AND seats_available >= 1");
    $update->execute([$rideId]);

    if ($update->rowCount() === 0) {
        $pdo->rollBack();
        die('Plus de places disponibles au moment de la réservation.');
    }

    $pdo->commit();

    header('Location: ../details.php?id=' . $rideId . '&booking=success');
    exit;

} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    die('Erreur lors de la réservation : ' . $e->getMessage());
}
