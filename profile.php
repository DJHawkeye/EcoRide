<?php
// profile.php
session_start();
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/csrf.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/js_helpers.php';
generateCSRFToken();

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}
$userId = $_SESSION['user_id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['role'])) {
    if (verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $role = $_POST['role'];
        if (in_array($role, ['passenger','driver','both'], true)) {
            $is_driver = in_array($role, ['driver','both']) ? 1 : 0;
            $is_passenger = in_array($role, ['passenger','both']) ? 1 : 0;
            $stmt = $pdo->prepare("UPDATE users SET is_driver=?, is_passenger=? WHERE id=?");
            $stmt->execute([$is_driver, $is_passenger, $userId]);
        }
    }
    header('Location: profile.php');
    exit;
}
// Fetch user data
$stmt = $pdo->prepare("SELECT username, photo, is_driver, is_passenger FROM users WHERE id=?");
$stmt->execute([$userId]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$user || empty($user['username'])) {
    echo "Utilisateur introuvable.";
    exit;
}

$is_driver = (bool)$user['is_driver'];
$is_passenger = (bool)$user['is_passenger'];

$driverRating = $is_driver ? getDriverAverageRating($pdo, $userId) : 0.0;

// Vehicles for driver
$vehicles = [];
if ($is_driver) {
    $stmtV = $pdo->prepare("
      SELECT v.*, vp.allow_smoking, vp.allow_pets, vp.allow_music, vp.custom_preferences
      FROM vehicles v
      LEFT JOIN vehicle_preferences vp ON vp.vehicle_id = v.id
      WHERE v.user_id = ?
      ORDER BY v.id DESC
    ");
    $stmtV->execute([$userId]);
    $vehicles = $stmtV->fetchAll(PDO::FETCH_ASSOC);
}

// Driver rides
$driver_future_rides = [];
$driver_past_rides = [];
if ($is_driver) {
    $stmtR = $pdo->prepare("
        SELECT r.*, v.fuel
        FROM rides r
        JOIN vehicles v ON r.vehicle_id = v.id
        WHERE r.driver_id = ?
          AND r.status != 'cancelled'
        ORDER BY r.departure_time ASC
    ");
    $stmtR->execute([$userId]);
    $all_driver_rides = $stmtR->fetchAll(PDO::FETCH_ASSOC);
    $now = date('Y-m-d H:i:s');
    foreach ($all_driver_rides as $ride) {
        if ($ride['departure_time'] >= $now) {
            $driver_future_rides[] = $ride;
        } else {
            $driver_past_rides[] = $ride;
        }
    }
}

// Passenger rides
$passenger_future_rides = [];
$passenger_past_rides = [];
if ($is_passenger) {
    $stmtP = $pdo->prepare("
        SELECT r.*, v.fuel, u.username AS driver_name, rv.validated AS validation_status
        FROM bookings b
        JOIN rides r ON b.ride_id = r.id
        JOIN vehicles v ON r.vehicle_id = v.id
        JOIN users u ON r.driver_id = u.id
        LEFT JOIN ride_validations rv ON rv.ride_id = r.id AND rv.passenger_id = b.passenger_id
        WHERE b.passenger_id = ?
          AND r.status != 'cancelled'
        ORDER BY r.departure_time ASC
    ");
    $stmtP->execute([$userId]);
    $all_passenger_rides = $stmtP->fetchAll(PDO::FETCH_ASSOC);
    $now = date('Y-m-d H:i:s');
    foreach ($all_passenger_rides as $ride) {
        if ($ride['departure_time'] >= $now) {
            $passenger_future_rides[] = $ride;
        } else {
            $passenger_past_rides[] = $ride;
        }
    }
}

function roleLabel(bool $is_driver, bool $is_passenger): string {
    if ($is_driver && $is_passenger) return "Conducteur & Passager";
    if ($is_driver) return "Conducteur";
    if ($is_passenger) return "Passager";
    return "Aucun rôle";
}

?>
<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Profil utilisateur - <?= htmlspecialchars($user['username']) ?></title>
  <link href="css/bootstrap.min.css" rel="stylesheet" />
  <link href="assets/css/style.css" rel="stylesheet" />
  <script src="js/bootstrap.bundle.min.js" defer></script>
  <style>
    @media (max-width: 767.98px) {
      .main-flex {
        flex-direction: column;
      }
      .ride-card-body {
        flex-direction: column;
      }
      .ride-info-left,
      .ride-info-right {
        flex-basis: 100%;
      }
      .ride-card-buttons {
        text-align: left;
      }
    }
  </style>
</head>
<body class="details-page">

<header>
  <?php include 'includes/header.php'; ?>
</header>

<main class="container py-5">

  <!-- User info + role selection -->
  <div class="profile-header">
    <div class="d-flex align-items-center mb-3 mb-md-0">
      <form id="photoUploadForm" method="post" enctype="multipart/form-data" action="includes/upload_photo.php" class="me-3" style="cursor:pointer;">
        <label for="profilePhotoInput" style="display:inline-block;">
          <img src="<?= htmlspecialchars($user['photo'] ?: 'images/profile_default.jpg') ?>"
              alt="Photo de profil"
              class="rounded-circle"
              style="width:80px; height:80px; object-fit:cover;"
              title="Cliquez pour changer la photo" />
        </label>
        <input type="file" name="photo" id="profilePhotoInput" accept="image/*" style="display:none;" onchange="document.getElementById('photoUploadForm').submit();" />
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
      </form>
      <h1 class="mb-0"><?= htmlspecialchars($user['username']) ?></h1>
      <?php if ($is_driver): ?>
        <?php if ($driverRating === 0.0): ?>
          <span class="text-muted ms-3">Aucune note</span>
        <?php else: ?>
          <span class="text-muted ms-3">Note moyenne : <?= number_format($driverRating,1) ?> / 5 ★</span>
        <?php endif; ?>
      <?php endif; ?>
    </div>

    <form id="roleForm" method="post" class="mb-0 d-flex align-items-center role-form-inline">
      <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
      <?php foreach (['passenger' => 'Passager', 'driver' => 'Conducteur', 'both' => 'Les deux'] as $val => $label): ?>
        <div class="form-check form-check-inline mb-0">
          <input class="form-check-input" 
                 type="radio" name="role" value="<?= $val ?>"
                 id="role_<?= $val ?>"
                 <?= 
                   ($val === 'passenger' && $is_passenger && !$is_driver)
                   || ($val === 'driver' && $is_driver && !$is_passenger)
                   || ($val === 'both' && $is_driver && $is_passenger)
                   ? 'checked' : '' ?>
                 onchange="document.getElementById('roleForm').submit()">
          <label class="form-check-label" for="role_<?= $val ?>"><?= $label ?></label>
        </div>
      <?php endforeach; ?>
    </form>
  </div>

  <?php if ($is_passenger && !$is_driver): ?>
    <!-- Passenger only layout -->
    <div class="main-flex">
      <div class="col-left">
        <h3>Réservations à venir</h3>
        <?php if (empty($passenger_future_rides)): ?>
          <p>Aucune réservation à venir.</p>
        <?php else: ?>
          <?php foreach ($passenger_future_rides as $ride): ?>
            <?php renderBookingCard($ride); ?>
          <?php endforeach; ?>
        <?php endif; ?>
      </div>
      <div class="col-right">
        <h3>Historique des réservations</h3>
        <?php if (empty($passenger_past_rides)): ?>
          <p>Aucun trajet passé.</p>
        <?php else: ?>
          <?php foreach ($passenger_past_rides as $ride): ?>
            <?php renderBookingCard($ride); ?>
          <?php endforeach; ?>
        <?php endif; ?>
      </div>
    </div>

  <?php elseif ($is_driver && !$is_passenger): ?>
    <!-- Driver only layout -->
    <div class="main-flex">
      <div class="col-left vehicles-section">
        <div class="d-flex justify-content-between align-items-center mb-3">
          <h3 class="mb-0">Mes véhicules</h3>
          <a href="includes/add_vehicle.php" title="Ajouter un véhicule" class="btn btn-sm btn-success" style="font-size: 1.2rem;">
            <i class="fas fa-plus-circle"></i>
          </a>
        </div>
        <?php if (empty($vehicles)): ?>
          <p>Aucun véhicule pour le moment.</p>
        <?php else: ?>
          <?php foreach ($vehicles as $v): ?>
            <div class="card mb-3">
              <div class="card-body">
                <p><strong><?= htmlspecialchars($v['brand']) ?> <?= htmlspecialchars($v['model']) ?></strong><br>
                Plaque : <?= htmlspecialchars($v['license_plate']) ?><br>
                Carburant : <?= htmlspecialchars($v['fuel']) ?><br>
                Couleur : <?= htmlspecialchars($v['color']) ?><br>
                Places : <?= (int)$v['seats'] ?></p>
                <p><strong>Préférences :</strong><br>
                  <?= $v['allow_smoking'] ? 'Fumer autorisé' : 'Non-fumeur' ?><br>
                  <?= $v['allow_pets'] ? 'Animaux autorisés' : 'Pas d’animaux' ?><br>
                  <?= $v['allow_music'] ? 'Musique autorisée' : 'Pas de musique' ?></p>
                <a href="includes/edit_vehicle.php?id=<?= (int)$v['id'] ?>" class="btn btn-sm btn-secondary">Modifier</a>
                <a href="includes/delete_vehicle.php?id=<?= (int)$v['id'] ?>" class="btn btn-sm btn-danger" onclick="return confirm('Supprimer ce véhicule ?');">Supprimer</a>
              </div>
            </div>
          <?php endforeach; ?>
        <?php endif; ?>
      </div>

      <div class="col-right">
        <div class="d-flex justify-content-between align-items-center mb-3">
          <h3 class="mb-0">Trajets à venir</h3>
          <a href="includes/add_ride.php" title="Ajouter un trajet" class="btn btn-sm btn-success" style="font-size: 1.2rem;">
            <i class="fas fa-plus-circle"></i>
          </a>
        </div>
        <?php if (empty($driver_future_rides)): ?>
          <p>Aucun trajet à venir.</p>
        <?php else: ?>
          <?php foreach ($driver_future_rides as $ride): ?>
            <?php renderRideCard($ride, 'driver', true); ?>
          <?php endforeach; ?>
        <?php endif; ?>

        <h3 class="mt-4">Historique des trajets</h3>
        <?php if (empty($driver_past_rides)): ?>
          <p>Aucun trajet passé.</p>
        <?php else: ?>
          <?php foreach ($driver_past_rides as $ride): ?>
            <?php renderRideCard($ride, 'driver', false); ?>
          <?php endforeach; ?>
        <?php endif; ?>
      </div>
    </div>

  <?php else: ?>
    <!-- Both roles layout -->
    <div class="main-flex">
      <div class="col-left vehicles-section">
        <div class="d-flex justify-content-between align-items-center mb-3">
          <h3 class="mb-0">Mes véhicules</h3>
          <a href="includes/add_vehicle.php" title="Ajouter un véhicule" class="btn btn-sm btn-success" style="font-size: 1.2rem;">
            <i class="fas fa-plus-circle"></i>
          </a>
        </div>
        <?php if (empty($vehicles)): ?>
          <p>Aucun véhicule pour le moment.</p>
        <?php else: ?>
          <?php foreach ($vehicles as $v): ?>
            <div class="card mb-3">
              <div class="card-body">
                <p><strong><?= htmlspecialchars($v['brand']) ?> <?= htmlspecialchars($v['model']) ?></strong><br>
                Plaque : <?= htmlspecialchars($v['license_plate']) ?><br>
                Carburant : <?= htmlspecialchars($v['fuel']) ?><br>
                Couleur : <?= htmlspecialchars($v['color']) ?><br>
                Places : <?= (int)$v['seats'] ?></p>
                <p><strong>Préférences :</strong><br>
                  <?= $v['allow_smoking'] ? 'Fumer autorisé' : 'Non-fumeur' ?><br>
                  <?= $v['allow_pets'] ? 'Animaux autorisés' : 'Pas d’animaux' ?><br>
                  <?= $v['allow_music'] ? 'Musique autorisée' : 'Pas de musique' ?></p>
              </div>
            </div>
          <?php endforeach; ?>
        <?php endif; ?>

        <h3 class="mt-4">Réservations à venir</h3>
        <?php if (empty($passenger_future_rides)): ?>
          <p>Aucune réservation à venir.</p>
        <?php else: ?>
          <?php foreach ($passenger_future_rides as $ride): ?>
            <?php renderBookingCard($ride); ?>
          <?php endforeach; ?>
        <?php endif; ?>

        <h3 class="mt-4">Historique des réservations</h3>
        <?php if (empty($passenger_past_rides)): ?>
          <p>Aucun trajet passé.</p>
        <?php else: ?>
          <?php foreach ($passenger_past_rides as $ride): ?>
            <?php renderBookingCard($ride); ?>
          <?php endforeach; ?>
        <?php endif; ?>
      </div>

      <div class="col-right">
        <div class="d-flex justify-content-between align-items-center mb-3">
          <h3 class="mb-0">Trajets à venir</h3>
          <a href="includes/add_ride.php" title="Ajouter un trajet" class="btn btn-sm btn-success" style="font-size: 1.2rem;">
            <i class="fas fa-plus-circle"></i>
          </a>
        </div>
        <?php if (empty($driver_future_rides)): ?>
          <p>Aucun trajet à venir.</p>
        <?php else: ?>
          <?php foreach ($driver_future_rides as $ride): ?>
            <?php renderRideCard($ride, 'driver', true); ?>
          <?php endforeach; ?>
        <?php endif; ?>

        <h3 class="mt-4">Historique des trajets</h3>
        <?php if (empty($driver_past_rides)): ?>
          <p>Aucun trajet passé.</p>
        <?php else: ?>
          <?php foreach ($driver_past_rides as $ride): ?>
            <?php renderRideCard($ride, 'driver', false); ?>
          <?php endforeach; ?>
        <?php endif; ?>
      </div>
    </div>
  <?php endif; ?>

</main>

<footer class="mt-5 text-center text-muted">
  &copy; <?= date('Y') ?> EcoRide
</footer>

</body>
</html>
