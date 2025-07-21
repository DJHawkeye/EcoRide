<?php
session_start();
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/csrf.php';
require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/../vendor/autoload.php';

$dotenv = Dotenv\Dotenv::createImmutable(dirname(__DIR__));
$dotenv->load();


generateCSRFToken();

if (!isset($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit;
}

$userId = $_SESSION['user_id'];
$rideId = $_GET['id'] ?? null;

if (!$rideId) {
    header('Location: ../profile.php');
    exit;
}

// Fetch ride and user vehicles
$stmt = $pdo->prepare("SELECT * FROM rides WHERE id=? AND driver_id=?");
$stmt->execute([$rideId, $userId]);
$ride = $stmt->fetch();

if (!$ride) {
    header('Location: ../profile.php');
    exit;
}

$stmtV = $pdo->prepare("SELECT id, brand, model, fuel, seats FROM vehicles WHERE user_id=?");
$stmtV->execute([$userId]);
$vehicles = $stmtV->fetchAll();

$error = '';

$apiKey = $_ENV['ORS_API_KEY'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        die('Token CSRF invalide');
    }

    $updateResult = updateRide($pdo, $rideId, $userId, [
        'vehicle_id' => intval($_POST['vehicle_id'] ?? 0),
        'departure' => trim($_POST['departure'] ?? ''),
        'destination' => trim($_POST['destination'] ?? ''),
        'departure_date' => $_POST['departure_date'] ?? '',
        'departure_time' => $_POST['departure_time'] ?? '',
        'seats_available' => intval($_POST['seats_available'] ?? 0),
        'price' => floatval($_POST['price'] ?? 0),
    ], $apiKey);

    if (!$updateResult['success']) {
        $error = $updateResult['error'];
    } else {
        header('Location: ../profile.php');
        exit;
    }
}

include __DIR__ . '/../includes/header.php';
?>

<main class="container py-5">
  <h1>Modifier le trajet</h1>
  <?php if ($error): ?>
    <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
  <?php endif; ?>

  <form method="post" id="rideForm">
    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">

    <div class="mb-3">
      <label for="vehicle_id" class="form-label">Véhicule</label>
      <select name="vehicle_id" id="vehicle_id" class="form-select" required>
        <option value="">-- Choisissez un véhicule --</option>
        <?php foreach ($vehicles as $v): ?>
          <option value="<?= $v['id'] ?>"
            <?= (isset($_POST['vehicle_id']) && $_POST['vehicle_id'] == $v['id']) || (!isset($_POST['vehicle_id']) && $ride['vehicle_id'] == $v['id']) ? 'selected' : '' ?>>
            <?= htmlspecialchars($v['brand'] . ' ' . $v['model'] . ' (' . $v['fuel'] . ')') ?>
          </option>
        <?php endforeach; ?>
      </select>
    </div>

    <div class="mb-3">
      <label for="departure" class="form-label">Lieu de départ</label>
      <input type="text" name="departure" id="departure" class="form-control" required
        value="<?= htmlspecialchars($_POST['departure'] ?? $ride['departure']) ?>">
    </div>

    <div class="mb-3">
      <label for="destination" class="form-label">Destination</label>
      <input type="text" name="destination" id="destination" class="form-control" required
        value="<?= htmlspecialchars($_POST['destination'] ?? $ride['destination']) ?>">
    </div>

    <div class="row mb-3">
      <div class="col">
        <label for="departure_date" class="form-label">Date de départ</label>
        <input type="date" name="departure_date" id="departure_date" class="form-control" required
          value="<?= htmlspecialchars($_POST['departure_date'] ?? date('Y-m-d', strtotime($ride['departure_time']))) ?>">
      </div>
      <div class="col">
        <label for="departure_time" class="form-label">Heure de départ</label>
        <select name="departure_time" id="departure_time" class="form-select" required>
          <?php
          $selectedTime = $_POST['departure_time'] ?? date('H:i', strtotime($ride['departure_time']));
          echo generateTimeOptions($selectedTime);
          ?>
        </select>
      </div>
    </div>

    <div class="mb-3">
      <label for="seats_available" class="form-label">Nombre de places disponibles</label>
      <input type="number" min="1" name="seats_available" id="seats_available" class="form-control" required
        value="<?= htmlspecialchars($_POST['seats_available'] ?? $ride['seats_available']) ?>">
    </div>

    <div class="mb-3">
      <label for="price" class="form-label">Prix (Crédits)</label>
      <input type="number" step="0.01" min="0" name="price" id="price" class="form-control" required
        value="<?= htmlspecialchars($_POST['price'] ?? $ride['price']) ?>">
    </div>

    <button type="submit" class="btn btn-primary">Mettre à jour le trajet</button>
    <a href="../profile.php" class="btn btn-secondary ms-2">Annuler</a>
  </form>
</main>

<?php include __DIR__ . '/../includes/footer.php'; ?>
