<?php
session_start();
require_once 'includes/db.php';
require_once 'includes/csrf.php';
generateCSRFToken();

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

// Verify role
$stmt = $pdo->prepare("SELECT role FROM users WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$role = $stmt->fetchColumn();

if ($role !== 'employee') {
    http_response_code(403);
    echo "Accès refusé.";
    exit;
}

$adminUserId = 1; // Admin account for site credits

// Handle POST actions (approve/reject)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        die("Token CSRF invalide.");
    }

    $action = $_POST['action'] ?? '';
    if ($action !== 'approve' && $action !== 'reject') {
        die("Action invalide.");
    }

    try {
        $pdo->beginTransaction();

        $isApprove = $action === 'approve';
        $new_status = $isApprove ? 'approved' : 'rejected';

        // Handle review validation
        if (isset($_POST['review_id'])) {
            $review_id = (int)$_POST['review_id'];

            // Update review status
            $stmtUpdate = $pdo->prepare("UPDATE reviews SET status = ? WHERE id = ?");
            $stmtUpdate->execute([$new_status, $review_id]);

            // Fetch review and ride details
            $stmtReviewInfo = $pdo->prepare("
                SELECT rv.ride_id, rv.passenger_id, r.driver_id, r.price
                FROM reviews rv
                JOIN rides r ON rv.ride_id = r.id
                WHERE rv.id = ?
            ");
            $stmtReviewInfo->execute([$review_id]);
            $reviewInfo = $stmtReviewInfo->fetch(PDO::FETCH_ASSOC);
            if (!$reviewInfo) throw new Exception("Review info not found.");

            // Check if a problem was reported
            $stmtValidation = $pdo->prepare("
                SELECT problem_reported FROM ride_validations
                WHERE ride_id = ? AND passenger_id = ?
                LIMIT 1
            ");
            $stmtValidation->execute([$reviewInfo['ride_id'], $reviewInfo['passenger_id']]);
            $validation = $stmtValidation->fetch(PDO::FETCH_ASSOC);
            $problemReported = ($validation && $validation['problem_reported'] == 1);

            $ridePrice = (float)$reviewInfo['price'];
            $driverCredits = max(0, $ridePrice - 2);
            $siteCredits = 2;

            // Load user credits
            $stmtCredits = $pdo->prepare("SELECT id, credits FROM users WHERE id IN (?, ?, ?)");
            $stmtCredits->execute([$reviewInfo['passenger_id'], $reviewInfo['driver_id'], $adminUserId]);
            $creditsData = [];
            while ($row = $stmtCredits->fetch(PDO::FETCH_ASSOC)) {
                $creditsData[$row['id']] = (float)$row['credits'];
            }

            if (!isset($creditsData[$reviewInfo['passenger_id']]) || !isset($creditsData[$reviewInfo['driver_id']]) || !isset($creditsData[$adminUserId])) {
                throw new Exception("User credits not found.");
            }

            $passengerCredits = $creditsData[$reviewInfo['passenger_id']];
            $driverCreditsCurrent = $creditsData[$reviewInfo['driver_id']];
            $adminCredits = $creditsData[$adminUserId];

            if ($passengerCredits < ($isApprove || $problemReported ? $ridePrice : $siteCredits)) {
                throw new Exception("Passenger does not have enough credits.");
            }

            $newPassengerCredits = $passengerCredits - ($isApprove || $problemReported ? $ridePrice : $siteCredits);
            $newDriverCredits = $isApprove || $problemReported ? $driverCreditsCurrent + $driverCredits : $driverCreditsCurrent;
            $newAdminCredits = $adminCredits + $siteCredits;

            // Update credits
            $stmtUpdateCredits = $pdo->prepare("UPDATE users SET credits = ? WHERE id = ?");
            $stmtUpdateCredits->execute([$newPassengerCredits, $reviewInfo['passenger_id']]);
            $stmtUpdateCredits->execute([$newDriverCredits, $reviewInfo['driver_id']]);
            $stmtUpdateCredits->execute([$newAdminCredits, $adminUserId]);
        }

        // Handle problem report validation
        if (isset($_POST['problem_ride_id'])) {
            $ride_id = (int)$_POST['problem_ride_id'];

            // Update problem status in `problems` table
            $stmtUpdateProblem = $pdo->prepare("
                UPDATE problems
                SET status = ?
                WHERE ride_id = ? AND status = 'pending'
            ");
            $stmtUpdateProblem->execute([$new_status, $ride_id]);

            // Get ride + user details
            $stmtProblemDetails = $pdo->prepare("
                SELECT rv.passenger_id, r.driver_id, r.price
                FROM ride_validations rv
                JOIN rides r ON rv.ride_id = r.id
                WHERE rv.ride_id = ? AND rv.problem_reported = 1
                LIMIT 1
            ");
            $stmtProblemDetails->execute([$ride_id]);
            $problemInfo = $stmtProblemDetails->fetch(PDO::FETCH_ASSOC);
            if (!$problemInfo) throw new Exception("Problème info non trouvé.");

            $ridePrice = (float)$problemInfo['price'];
            $driverCredits = max(0, $ridePrice - 2);
            $siteCredits = 2;

            // Get user credits
            $stmtCredits = $pdo->prepare("SELECT id, credits FROM users WHERE id IN (?, ?, ?)");
            $stmtCredits->execute([$problemInfo['passenger_id'], $problemInfo['driver_id'], $adminUserId]);
            $creditsData = [];
            while ($row = $stmtCredits->fetch(PDO::FETCH_ASSOC)) {
                $creditsData[$row['id']] = (float)$row['credits'];
            }

            if (!isset($creditsData[$problemInfo['passenger_id']]) || !isset($creditsData[$problemInfo['driver_id']]) || !isset($creditsData[$adminUserId])) {
                throw new Exception("User credits not found.");
            }

            $passengerCredits = $creditsData[$problemInfo['passenger_id']];
            $driverCreditsCurrent = $creditsData[$problemInfo['driver_id']];
            $adminCredits = $creditsData[$adminUserId];

            if ($isApprove && $passengerCredits < $siteCredits) {
                throw new Exception("Passenger does not have enough credits for maintenance fee.");
            } elseif (!$isApprove && $passengerCredits < $ridePrice) {
                throw new Exception("Passenger does not have enough credits.");
            }

            $newPassengerCredits = $passengerCredits - ($isApprove ? $siteCredits : $ridePrice);
            $newDriverCredits = $isApprove ? $driverCreditsCurrent : $driverCreditsCurrent + $driverCredits;
            $newAdminCredits = $adminCredits + $siteCredits;

            // Update credits
            $stmtUpdateCredits = $pdo->prepare("UPDATE users SET credits = ? WHERE id = ?");
            $stmtUpdateCredits->execute([$newPassengerCredits, $problemInfo['passenger_id']]);
            $stmtUpdateCredits->execute([$newDriverCredits, $problemInfo['driver_id']]);
            $stmtUpdateCredits->execute([$newAdminCredits, $adminUserId]);
        }

        $pdo->commit();
    } catch (Exception $e) {
        $pdo->rollBack();
        die("Erreur lors de la validation : " . $e->getMessage());
    }

    header('Location: employee_space.php');
    exit;
}

// Fetch pending reviews
$stmtReviews = $pdo->prepare("
    SELECT rv.id as review_id, rv.comment, rv.rating, u.username as passenger_name, r.id as ride_id, r.departure_time, r.arrival_time, r.departure, r.destination
    FROM reviews rv
    JOIN users u ON rv.passenger_id = u.id
    JOIN rides r ON rv.ride_id = r.id
    JOIN ride_validations v ON rv.ride_id = v.ride_id AND rv.passenger_id = v.passenger_id
    WHERE rv.status = 'pending' AND v.problem_reported = 0
    ORDER BY r.departure_time DESC
");
$stmtReviews->execute();
$pendingReviews = $stmtReviews->fetchAll(PDO::FETCH_ASSOC);

// Fetch reported rides (problems with status = 'pending')
$stmtProblemRides = $pdo->prepare("
    SELECT rv.ride_id, r.departure_time, r.arrival_time, r.departure, r.destination,
           d.username as driver_name, d.email as driver_email,
           u.username as passenger_name, u.email as passenger_email,
           p.comment AS problem_comment
    FROM ride_validations rv
    JOIN problems p ON p.ride_id = rv.ride_id AND p.passenger_id = rv.passenger_id
    JOIN rides r ON rv.ride_id = r.id
    JOIN users d ON r.driver_id = d.id
    JOIN users u ON rv.passenger_id = u.id
    WHERE rv.problem_reported = 1 AND p.status = 'pending'
    ORDER BY r.departure_time DESC
");
$stmtProblemRides->execute();
$problemRides = $stmtProblemRides->fetchAll(PDO::FETCH_ASSOC);

include 'includes/header.php';
?>

<main class="container py-5">
  <h2>Espace Employé</h2>

  <section class="mb-5">
    <h3>Avis à valider</h3>
    <?php if (empty($pendingReviews)): ?>
      <p>Aucun avis en attente.</p>
    <?php else: ?>
      <table class="table table-striped">
        <thead>
          <tr>
            <th>Passager</th>
            <th>Trajet (ID & dates)</th>
            <th>Commentaire</th>
            <th>Note</th>
            <th>Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($pendingReviews as $review): ?>
            <tr>
              <td><?= htmlspecialchars($review['passenger_name']) ?></td>
              <td>
                #<?= (int)$review['ride_id'] ?><br>
                <?= htmlspecialchars($review['departure']) ?> → <?= htmlspecialchars($review['destination']) ?><br>
                <?= date('d/m/Y H:i', strtotime($review['departure_time'])) ?> - <?= date('d/m/Y H:i', strtotime($review['arrival_time'])) ?>
              </td>
              <td><?= nl2br(htmlspecialchars($review['comment'])) ?></td>
              <td><?= (int)$review['rating'] ?>/5</td>
              <td>
                <form action="employee_space.php" method="post" class="d-inline">
                  <input type="hidden" name="review_id" value="<?= (int)$review['review_id'] ?>">
                  <input type="hidden" name="action" value="approve">
                  <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                  <button type="submit" class="btn btn-success btn-sm">Valider</button>
                </form>
                <form action="employee_space.php" method="post" class="d-inline ms-1">
                  <input type="hidden" name="review_id" value="<?= (int)$review['review_id'] ?>">
                  <input type="hidden" name="action" value="reject">
                  <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                  <button type="submit" class="btn btn-danger btn-sm">Refuser</button>
                </form>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    <?php endif; ?>
  </section>

  <section>
    <h3>Covoiturages signalés</h3>
    <?php if (empty($problemRides)): ?>
      <p>Aucun covoiturage signalé.</p>
    <?php else: ?>
      <table class="table table-bordered">
        <thead>
          <tr>
            <th>ID Trajet</th>
            <th>Conducteur</th>
            <th>Passager</th>
            <th>Dates & Lieux</th>
            <th>Description du problème</th>
            <th>Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($problemRides as $pr): ?>
            <tr>
              <td><?= (int)$pr['ride_id'] ?></td>
              <td>
                <?= htmlspecialchars($pr['driver_name']) ?><br>
                <?= htmlspecialchars($pr['driver_email']) ?>
                <!-- Contact Driver -->
                <button class="btn btn-outline-primary btn-sm" 
                        data-bs-toggle="modal" 
                        data-bs-target="#contactModal"
                        data-username="<?= htmlspecialchars($pr['driver_name']) ?>"
                        data-email="<?= htmlspecialchars($pr['driver_email']) ?>"
                        data-subject="Problème signalé : <?= htmlspecialchars($pr['departure']) ?> → <?= htmlspecialchars($pr['destination']) ?>">
                    Contacter Conducteur
                </button>
              </td>
              <td>
                <?= htmlspecialchars($pr['passenger_name']) ?><br>
                <?= htmlspecialchars($pr['passenger_email']) ?>
                <!-- Contact Passenger -->
                <button class="btn btn-outline-secondary btn-sm" 
                        data-bs-toggle="modal" 
                        data-bs-target="#contactModal"
                        data-username="<?= htmlspecialchars($pr['passenger_name']) ?>"
                        data-email="<?= htmlspecialchars($pr['passenger_email']) ?>"
                        data-subject="Problème signalé <?= htmlspecialchars($pr['departure']) ?> → <?= htmlspecialchars($pr['destination']) ?>">
                    Contacter Passager
                </button>
              </td>
              <td>
                <?= htmlspecialchars($pr['departure']) ?> → <?= htmlspecialchars($pr['destination']) ?><br>
                <?= date('d/m/Y H:i', strtotime($pr['departure_time'])) ?> - <?= date('d/m/Y H:i', strtotime($pr['arrival_time'])) ?>
              </td>
              <td><?= nl2br(htmlspecialchars($pr['problem_comment'])) ?></td>
              <td>
                <div class="d-flex gap-2">
                  <form action="employee_space.php" method="post" class="m-0">
                    <input type="hidden" name="problem_ride_id" value="<?= (int)$pr['ride_id'] ?>">
                    <input type="hidden" name="action" value="approve">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                    <button type="submit" class="btn btn-success btn-sm w-100">Valider</button>
                  </form>
                  <form action="employee_space.php" method="post" class="m-0">
                    <input type="hidden" name="problem_ride_id" value="<?= (int)$pr['ride_id'] ?>">
                    <input type="hidden" name="action" value="reject">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                    <button type="submit" class="btn btn-danger btn-sm w-100">Refuser</button>
                  </form>
                </div>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    <?php endif; ?>
  </section>

<!-- Contact Modal -->
<div class="modal fade" id="contactModal" tabindex="-1" aria-labelledby="contactModalLabel" aria-hidden="true">
  <div class="modal-dialog">
    <form method="post" action="send_message.php">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title" id="contactModalLabel">Contacter l'utilisateur</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fermer"></button>
        </div>
        <div class="modal-body">
          <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
          <div class="mb-3">
            <label for="to_email" class="form-label">Email</label>
            <input type="email" class="form-control" name="to_email" id="to_email" readonly required>
          </div>
          <div class="mb-3">
            <label for="to_name" class="form-label">Nom</label>
            <input type="text" class="form-control" name="to_name" id="to_name" readonly>
          </div>
          <div class="mb-3">
            <label for="subject" class="form-label">Objet</label>
            <input type="text" class="form-control" name="subject" id="subject" required>
          </div>
          <div class="mb-3">
            <label for="message" class="form-label">Message</label>
            <textarea class="form-control" name="message" id="message" rows="5" required></textarea>
          </div>
        </div>
        <div class="modal-footer">
          <button type="submit" class="btn btn-primary">Envoyer</button>
        </div>
      </div>
    </form>
  </div>
</div>

</main>



<?php include 'includes/footer.php'; ?>
