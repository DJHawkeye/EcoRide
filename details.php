<?php
// details.php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require 'includes/db.php'; // gives you $pdo
require_once 'includes/functions.php';
require_once 'includes/csrf.php';
require_once 'includes/js_helpers.php';
generateCSRFToken();

$carpoolId = getIntParam('id');

if ($carpoolId === 0) {
    $carpool = null;
} else {
    // 1) Fetch ride + driver info
    $stmt = $pdo->prepare("
        SELECT
          r.id,
          r.departure,
          r.destination,
          r.departure_time,
          r.arrival_time,
          r.seats_available,
          r.price,
          r.vehicle_id,
          u.id   AS driver_id,
          u.username,
          u.photo
        FROM rides r
        JOIN users u ON r.driver_id = u.id
        WHERE r.id = :ride_id
        LIMIT 1
    ");
    $stmt->execute(['ride_id' => $carpoolId]);
    $carpool = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($carpool) {
        formatRideTimestamps($carpool);
        $carpool['remaining_seats'] = (int)$carpool['seats_available'];

        // 2) Fetch vehicle
        if ($carpool['vehicle_id']) {
            $vStmt = $pdo->prepare("SELECT * FROM vehicles WHERE id = :vehicle_id LIMIT 1");
            $vStmt->execute(['vehicle_id' => $carpool['vehicle_id']]);
            $vehicle = $vStmt->fetch(PDO::FETCH_ASSOC) ?: null;
        } else {
            $vehicle = null;
        }

        if ($vehicle) {
            // 3) Fetch vehicle prefs
            $pStmt = $pdo->prepare("
              SELECT allow_smoking, allow_pets, allow_music, custom_preferences
              FROM vehicle_preferences
              WHERE vehicle_id = :vid
              LIMIT 1
            ");
            $pStmt->execute(['vid' => $vehicle['id']]);
            $prefs = $pStmt->fetch(PDO::FETCH_ASSOC) ?: [];
        } else {
            $prefs = [];
        }

        $carpool['vehicle']     = $vehicle;
        $carpool['preferences'] = $prefs;
    }
}

// Fetch driver reviews
$driverReviews = [];
if ($carpool && $carpool['driver_id']) {
    $stmtReviews = $pdo->prepare("
        SELECT rv.rating, rv.comment, rv.created_at, u.username as passenger_name
        FROM reviews rv
        JOIN users u ON rv.passenger_id = u.id
        WHERE rv.ride_id IN (
            SELECT id FROM rides WHERE driver_id = ?
        )
        AND rv.status = 'approved'
        ORDER BY rv.created_at DESC
        LIMIT 20
    ");
    $stmtReviews->execute([$carpool['driver_id']]);
    $driverReviews = $stmtReviews->fetchAll(PDO::FETCH_ASSOC);
}

$isLoggedIn = isset($_SESSION['user_id']);
$userHasBooked = false;
$isDriver = false;

if ($isLoggedIn && $carpool) {
    $userId = $_SESSION['user_id'];
    $isDriver = ($carpool['driver_id'] == $userId);

    // Check if user already booked this ride
    $stmt = $pdo->prepare("SELECT id FROM bookings WHERE ride_id = ? AND passenger_id = ?");
    $stmt->execute([$carpoolId, $userId]);
    $userHasBooked = $stmt->fetch() ? true : false;
}

$avgRating = $carpool ? getDriverAverageRating($pdo, $carpool['driver_id']) : 0.0;
?>
<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8" />
  <title>Détails du trajet - EcoRide</title>
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" />
  <link rel="stylesheet" href="assets\css\style.css">
</head>
<body class="details-page">
  
<?php include 'includes/header.php'; ?>

<main class="container my-5">

<?php if (isset($_GET['booking'])): ?>
  <?php if ($_GET['booking'] === 'success'): ?>
    <div class="alert alert-success text-center">Réservation effectuée avec succès !</div>
  <?php elseif ($_GET['booking'] === 'insufficient'): ?>
    <div class="alert alert-danger text-center">Crédits insuffisants pour réserver ce trajet.</div>
  <?php endif; ?>
<?php endif; ?>

<?php if (isset($_GET['cancel']) && $_GET['cancel'] === 'success'): ?>
  <div class="alert alert-success text-center">Réservation annulée avec succès.</div>
<?php endif; ?>

<?php if (!$carpool): ?>
  <div class="alert alert-danger text-center">
    <p>Trajet introuvable. <a href="covoiturages.php">Retour à la recherche</a></p>
  </div>
<?php else: ?>
  <div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="m-0">Détails du trajet</h1>
    <div>
      <?php if ($isDriver): ?>
        <button class="btn btn-secondary" disabled>Vous êtes le conducteur</button>
      <?php elseif ($userHasBooked): ?>
        <form method="POST" action="includes/cancel_booking.php">
          <input type="hidden" name="carpool_id" value="<?= $carpoolId ?>">
          <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
          <button type="submit" class="btn btn-danger">Annuler la réservation</button>
        </form>
      <?php elseif ($carpool['remaining_seats'] > 0): ?>
        <?php if ($isLoggedIn): ?>
          <button class="btn btn-success" onclick="confirmJoin()">Réserver</button>
          <form id="joinForm" action="includes/join.php" method="POST" style="display:none;">
            <input type="hidden" name="carpool_id" value="<?= $carpoolId ?>">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
          </form>
        <?php else: ?>
          <a href="login.php?redirect=details.php%3Fid%3D<?= $carpoolId ?>" class="btn btn-success">Se connecter pour réserver</a>
        <?php endif; ?>
      <?php else: ?>
        <span class="badge bg-danger fs-5">Complet</span>
      <?php endif; ?>
    </div>
  </div>

  <div class="two-col">
    <!-- Left Column: Driver Info + Reviews -->
    <div class="col-left">
      <div class="card shadow-sm mb-4">
        <div class="card-body d-flex align-items-start gap-3">
          <img src="<?= htmlspecialchars($carpool['photo']) ?>" alt="Photo de <?= htmlspecialchars($carpool['username']) ?>" class="rounded-circle" style="width:100px;height:100px;object-fit:cover; border:3px solid #a67b5b;">
          <div>
            <h4 class="mb-1">
              <?= htmlspecialchars($carpool['username']) ?>
              <small class="text-muted"><?= number_format($avgRating, 1) ?> ★</small>
            </h4>
          </div>
        </div>
      </div>

      <div id="review-container" class="mb-4">
        <h5>Avis des passagers</h5>
        <?php if (empty($driverReviews)): ?>
          <p><em>Aucun avis disponible.</em></p>
        <?php else: ?>
          <div class="card shadow-sm p-3">
            <div id="review-content" style="min-height:120px;"></div>
            <div class="review-navigation mt-3 d-flex justify-content-between align-items-center">
              <button id="prev-review" class="btn btn-outline-primary btn-sm" disabled>&larr; Précédent</button>
              <span id="review-counter"></span>
              <button id="next-review" class="btn btn-outline-primary btn-sm" <?= count($driverReviews) <= 1 ? 'disabled' : '' ?>>Suivant &rarr;</button>
            </div>
          </div>
        <?php endif; ?>
      </div>
    </div>

    <!-- Right Column: Trip Info + Vehicle Info + Preferences -->
    <div class="col-right">
      <div class="card shadow-sm mb-4 p-3">
        <h5>Informations sur le trajet</h5>
        <div class="trip-info-grid">
          <div><span class="label">Date :</span><span class="value"><?= htmlspecialchars($carpool['departure_date']) ?></span></div>
          <div><span class="label">Heure de départ :</span><span class="value"><?= htmlspecialchars($carpool['departure_time']) ?></span></div>
          <div><span class="label">Départ :</span><span class="value"><?= htmlspecialchars($carpool['departure']) ?></span></div>
          <div><span class="label">Destination :</span><span class="value"><?= htmlspecialchars($carpool['destination']) ?></span></div>
          <div><span class="label">Places disponibles :</span><span class="value"><?= $carpool['remaining_seats'] ?></span></div>
          <div><span class="label">Prix :</span><span class="value"><?= number_format($carpool['price'],2) ?> Crédits</span></div>
        </div>
      </div>

      <?php if ($carpool['vehicle']): ?>
      <div class="card shadow-sm mb-4 p-3">
        <h5>Véhicule Éco <span class="ms-2"><?= displayEcoBadge(trim($carpool['vehicle']['fuel'] ?? '')) ?></span></h5>
        <div class="vehicle-info-grid">
          <div class="vehicle-info-row"><span class="label">Plaque :</span><span class="value"><?= htmlspecialchars($carpool['vehicle']['license_plate']) ?></span></div>
          <div class="vehicle-info-row"><span class="label">1ère immatriculation :</span><span class="value"><?= htmlspecialchars($carpool['vehicle']['first_registration']) ?></span></div>
          <div class="vehicle-info-row"><span class="label">Marque :</span><span class="value"><?= htmlspecialchars($carpool['vehicle']['brand']) ?></span></div>
          <div class="vehicle-info-row"><span class="label">Modèle :</span><span class="value"><?= htmlspecialchars($carpool['vehicle']['model']) ?></span></div>
          <div class="vehicle-info-row"><span class="label">Carburant :</span><span class="value"><?= htmlspecialchars($carpool['vehicle']['fuel']) ?></span></div>
          <div class="vehicle-info-row"><span class="label">Couleur :</span><span class="value"><?= htmlspecialchars($carpool['vehicle']['color']) ?></span></div>
          <div class="vehicle-info-row"><span class="label">Places :</span><span class="value"><?= htmlspecialchars($carpool['vehicle']['seats']) ?></span></div>
        </div>
      </div>

      <div class="card shadow-sm mb-4 p-3">
        <h5>Préférences du véhicule</h5>
        <ul class="vehicle-prefs-list">
          <li><?= $carpool['preferences']['allow_smoking'] ? 'Fumer autorisé' : 'Non-fumeur' ?></li>
          <li><?= $carpool['preferences']['allow_pets'] ? 'Animaux autorisés' : 'Pas d’animaux' ?></li>
          <li><?= $carpool['preferences']['allow_music'] ? 'Musique autorisée' : 'Pas de musique' ?></li>
        </ul>
        <?php
        if (!empty($carpool['preferences']['custom_preferences'])):
          $prefs = json_decode($carpool['preferences']['custom_preferences'], true);
          if ($prefs): ?>
            <h6>Préférences supplémentaires :</h6>
            <ul class="vehicle-custom-prefs-list">
              <?php foreach ($prefs as $pref): ?>
                <li><?= htmlspecialchars($pref) ?></li>
              <?php endforeach; ?>
            </ul>
          <?php endif;
        endif; ?>
      </div>
      <?php endif; ?>
    </div>
  </div>
<?php endif; ?>
</main>

<script>
function confirmJoin() {
  if (confirm('Voulez-vous confirmer votre réservation ?')) {
    document.getElementById('joinForm').submit();
  }
}

<?php if (!empty($driverReviews)): ?>
const reviews = <?= json_encode($driverReviews, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG) ?>;
let currentIndex = 0;

function renderReview() {
  const review = reviews[currentIndex];
  const container = document.getElementById('review-content');
  container.innerHTML = `
    <p><strong>${review.passenger_name}</strong> a donné <strong>${review.rating}★</strong></p>
    <p>${review.comment}</p>
    <small class="text-muted">Posté le ${new Date(review.created_at).toLocaleDateString('fr-FR')}</small>
  `;
  document.getElementById('review-counter').textContent = `${currentIndex + 1} / ${reviews.length}`;
  document.getElementById('prev-review').disabled = (currentIndex === 0);
  document.getElementById('next-review').disabled = (currentIndex === reviews.length - 1);
}

document.getElementById('prev-review').addEventListener('click', () => {
  if (currentIndex > 0) currentIndex--;
  renderReview();
});

document.getElementById('next-review').addEventListener('click', () => {
  if (currentIndex < reviews.length - 1) currentIndex++;
  renderReview();
});

window.addEventListener('DOMContentLoaded', renderReview);
<?php endif; ?>
</script>

<?php include 'includes/footer.php'; ?>
</body>
</html>
