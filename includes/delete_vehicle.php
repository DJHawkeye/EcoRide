<?php
session_start();
require_once __DIR__.'/db.php';
require_once __DIR__.'/functions.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit;
}

$userId = $_SESSION['user_id'];
$vehicleId = intval($_GET['id'] ?? 0);

if ($vehicleId && userOwnsVehicle($pdo, $vehicleId, $userId)) {
    deleteVehicle($pdo, $vehicleId);
}

header('Location: ../profile.php?deleted=1');
exit;
