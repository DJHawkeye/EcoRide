<?php
session_start();
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/csrf.php';
require_once __DIR__ . '/functions.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit;
}

// Check employee role
if (!isEmployee($pdo, $_SESSION['user_id'])) {
    http_response_code(403);
    echo "Accès refusé.";
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../employee_space.php');
    exit;
}

if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
    die("Token CSRF invalide.");
}

$action = $_POST['action'] ?? '';
if (!in_array($action, ['approve', 'reject'], true)) {
    die("Action invalide.");
}

$status = $action === 'approve' ? 'approved' : 'rejected';

// Check if this is a review action
if (isset($_POST['review_id'])) {
    $review_id = (int)$_POST['review_id'];
    try {
        $pdo->beginTransaction();

        // Update review status
        $stmt = $pdo->prepare("UPDATE reviews SET status = ? WHERE id = ?");
        $stmt->execute([$status, $review_id]);

        // Get driver_id related to this review
        $stmt2 = $pdo->prepare("
            SELECT r.driver_id 
            FROM reviews rv
            JOIN rides r ON rv.ride_id = r.id
            WHERE rv.id = ?
        ");
        $stmt2->execute([$review_id]);
        $driverId = $stmt2->fetchColumn();

        if ($driverId) {
            updateUserRating($pdo, (int)$driverId);
        }

        $pdo->commit();

        header('Location: ../employee_space.php');
        exit;
    } catch (Exception $e) {
        $pdo->rollBack();
        die("Erreur lors de la validation de la revue : " . $e->getMessage());
    }
}

// Check if this is a problem report action
if (isset($_POST['validation_id'])) {
    $validation_id = (int)$_POST['validation_id'];

    // Assume you added a 'problem_status' column in ride_validations
    $stmt = $pdo->prepare("UPDATE ride_validations SET problem_status = ? WHERE id = ?");
    $problem_status = $status === 'approved' ? 'approved' : 'rejected';
    $stmt->execute([$problem_status, $validation_id]);

    header('Location: ../employee_space.php');
    exit;
}

die("Aucun ID de revue ou validation fourni.");
