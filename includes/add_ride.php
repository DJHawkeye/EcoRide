<?php
session_start();
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/csrf.php';
require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/js_helpers.php';
require_once __DIR__ . '/../vendor/autoload.php';

$dotenv = Dotenv\Dotenv::createImmutable(dirname(__DIR__));
$dotenv->load();

generateCSRFToken();

if (!isset($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit;
}

$userId = $_SESSION['user_id'];

ensureDriver($pdo, $userId);
$vehicles = getUserVehicles($pdo, $userId);

$error = '';
$debugInfo = '';

$apiKey = $_ENV['ORS_API_KEY'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        die('Token CSRF invalide');
    }

    $departure       = trim($_POST['departure'] ?? '');
    $destination     = trim($_POST['destination'] ?? '');
    $departure_date  = $_POST['departure_date'] ?? '';
    $departure_time  = $_POST['departure_time'] ?? '';
    $vehicle_id      = intval($_POST['vehicle_id'] ?? 0);
    $price           = floatval($_POST['price'] ?? 0);

    $vehicle = null;
    foreach ($vehicles as $v) {
        if ($v['id'] == $vehicle_id) {
            $vehicle = $v;
            break;
        }
    }

    if (!$vehicle) {
        $error = "Veuillez sélectionner un véhicule valide.";
    } elseif ($departure === '' || $destination === '' || !$departure_date || !$departure_time) {
        $error = "Veuillez remplir tous les champs obligatoires correctement.";
    } else {
        $seats_available = $vehicle['seats'];
        $departure_datetime_str = "$departure_date $departure_time";

        $startCoords = getCoordinates($departure, $apiKey);
        $endCoords = getCoordinates($destination, $apiKey);

        $debugInfo .= "<p>Start coords: " . json_encode($startCoords) . "</p>";
        $debugInfo .= "<p>End coords: " . json_encode($endCoords) . "</p>";

        if (!$startCoords || !$endCoords) {
            $error = "Impossible de localiser les villes spécifiées.";
        } else {
            $durationSeconds = getTravelDuration($startCoords, $endCoords, $apiKey);

            $debugInfo .= "<p>Duration seconds: " . htmlspecialchars($durationSeconds) . "</p>";

            if (!$durationSeconds) {
                $error = "Erreur lors du calcul de la durée du trajet.";
            } else {
                $dt = DateTime::createFromFormat('Y-m-d H:i', $departure_datetime_str);
                if (!$dt) {
                    $error = "Format de date/heure invalide.";
                } else {
                    $durationSecondsInt = (int) round($durationSeconds);
                    $dt->modify("+{$durationSecondsInt} seconds");
                    $arrival_datetime = $dt->format('Y-m-d H:i:s');

                    $debugInfo .= "<p>Calculated arrival time: " . htmlspecialchars($arrival_datetime) . "</p>";

                    $stmt = $pdo->prepare("INSERT INTO rides 
                      (driver_id, vehicle_id, departure, destination, departure_time, arrival_time, seats_available, price, created_at) 
                      VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())");
                    $stmt->execute([
                        $userId, $vehicle_id, $departure, $destination, $departure_datetime_str, $arrival_datetime, $seats_available, $price
                    ]);

                    header('Location: ../profile.php');
                    exit;
                }
            }
        }
    }
}

include '../includes/header.php';
?>

<main class="container py-5">
  <h1>Ajouter un nouveau trajet</h1>
  <?php if ($error): ?>
    <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
  <?php endif; ?>

  <?= $debugInfo ?>

  <form method="post" id="rideForm">
    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">

    <div class="mb-3">
      <label for="vehicle_id" class="form-label">Véhicule</label>
      <select name="vehicle_id" id="vehicle_id" class="form-select" required>
        <option value="">-- Choisissez un véhicule --</option>
        <?php foreach ($vehicles as $v): ?>
          <option value="<?= $v['id'] ?>" data-fuel="<?= htmlspecialchars($v['fuel']) ?>" data-seats="<?= $v['seats'] ?>"
            <?= (isset($_POST['vehicle_id']) && $_POST['vehicle_id'] == $v['id']) ? 'selected' : '' ?>>
            <?= htmlspecialchars($v['brand'] . ' ' . $v['model'] . ' (' . $v['fuel'] . ')') ?>
          </option>
        <?php endforeach; ?>
      </select>
    </div>

    <div class="mb-3">
      <label for="departure" class="form-label">Lieu de départ</label>
      <input type="text" name="departure" id="departure" class="form-control" required value="<?= htmlspecialchars($_POST['departure'] ?? '') ?>">
    </div>

    <div class="mb-3">
      <label for="destination" class="form-label">Destination</label>
      <input type="text" name="destination" id="destination" class="form-control" required value="<?= htmlspecialchars($_POST['destination'] ?? '') ?>">
    </div>

    <div class="row mb-3">
      <div class="col">
        <label for="departure_date" class="form-label">Date de départ</label>
        <input type="date" name="departure_date" id="departure_date" class="form-control" required
          value="<?= htmlspecialchars($_POST['departure_date'] ?? date('Y-m-d')) ?>">
      </div>
      <div class="col">
        <label for="departure_time" class="form-label">Heure de départ</label>
        <select name="departure_time" id="departure_time" class="form-select" required>
          <?php
          $selectedTime = $_POST['departure_time'] ?? '09:00';
          echo generateTimeOptions($selectedTime);
          ?>
        </select>
      </div>
    </div>

    <div class="mb-3">
      <label for="price" class="form-label">Prix (Crédits)</label>
      <input type="number" step="0.01" min="0" name="price" id="price" class="form-control" required value="<?= htmlspecialchars($_POST['price'] ?? '') ?>">
    </div>

    <div class="mb-3">
      <label for="seats_available" class="form-label">Nombre de places disponibles</label>
      <input type="number" min="1" id="seats_available" name="seats_available" class="form-control" required
        value="<?= htmlspecialchars($_POST['seats_available'] ?? '') ?>">
    </div>

    <button type="submit" class="btn btn-primary">Ajouter le trajet</button>
    <a href="../profile.php" class="btn btn-secondary ms-2">Annuler</a>
  </form>

  <?php echoVehicleSeatsScript(); ?>

</main>

<?php include '../includes/footer.php'; ?>
