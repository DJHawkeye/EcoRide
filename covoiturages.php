<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require 'includes/db.php';
require_once 'includes/functions.php';
require_once 'includes/csrf.php';
generateCSRFToken();

function getParam(string $key, string $default = ''): string {
    return isset($_GET[$key])
      ? htmlspecialchars(trim($_GET[$key]), ENT_QUOTES, 'UTF-8')
      : $default;
}

// Fetch inputs
$departure = getParam('departure');
$arrival = getParam('arrival');
$date = getParam('date');
$filterEco = isset($_GET['eco']);
$filterMaxPrice = isset($_GET['max_price']) && $_GET['max_price'] !== '' ? floatval($_GET['max_price']) : null;
$filterMinRating = isset($_GET['min_rating']) && $_GET['min_rating'] !== '' ? floatval($_GET['min_rating']) : null;
$filterMaxDuration = isset($_GET['max_duration']) && $_GET['max_duration'] !== '' ? floatval($_GET['max_duration']) : null;
$useNextAvailable = isset($_GET['use_next_available']);

// Build WHERE clauses
$whereClauses = ["r.seats_available > 0", "r.status = 'planned'"];
$params = [];
if ($departure !== '') {
    $whereClauses[] = "r.departure LIKE :departure";
    $params['departure'] = "%{$departure}%";
}
if ($arrival !== '') {
    $whereClauses[] = "r.destination LIKE :arrival";
    $params['arrival'] = "%{$arrival}%";
}
if ($date !== '') {
    $whereClauses[] = "DATE(r.departure_time) = :date";
    $params['date'] = $date;
}
if ($filterEco) {
    $whereClauses[] = "v.fuel IN ('Électrique', 'Hybride')";
}
if ($filterMaxPrice !== null) {
    $whereClauses[] = "r.price <= :max_price";
    $params['max_price'] = $filterMaxPrice;
}
if ($filterMaxDuration !== null && $filterMaxDuration < 12) {
    $whereClauses[] = "TIMESTAMPDIFF(SECOND, r.departure_time, r.arrival_time) <= :max_duration_seconds";
    $params['max_duration_seconds'] = $filterMaxDuration * 3600;
}
$whereSql = implode(' AND ', $whereClauses);

// Main query: fetch rides + driver info
$sql = <<<SQL
SELECT
  r.id,
  r.driver_id,
  r.vehicle_id,
  u.username,
  u.photo,
  r.seats_available AS remaining_seats,
  r.price,
  TIME(r.departure_time) AS departure_time,
  TIME(r.arrival_time) AS arrival_time,
  DATE(r.departure_time) AS departure_date,
  r.departure AS departure_city,
  r.destination AS arrival_city,
  TRIM(v.fuel) AS fuel_type,
  TIMESTAMPDIFF(SECOND, r.departure_time, r.arrival_time) AS duration_seconds
FROM rides r
JOIN users u   ON r.driver_id = u.id
JOIN vehicles v ON v.id = r.vehicle_id
WHERE $whereSql
ORDER BY r.departure_time ASC
SQL;

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$filteredCarpools = $stmt->fetchAll(PDO::FETCH_ASSOC);

// If empty and not yet using next available, try to find the next one
$nextAvailableDate = null;
if (empty($filteredCarpools) && $departure && $arrival && $date && !$useNextAvailable) {
    $nextSql = <<<SQL
    SELECT MIN(DATE(r.departure_time)) AS next_date
    FROM rides r
    WHERE r.seats_available > 0
      AND r.departure LIKE :departure
      AND r.destination LIKE :arrival
      AND DATE(r.departure_time) > :date
SQL;
    $stmtNext = $pdo->prepare($nextSql);
    $stmtNext->execute([
        'departure' => "%{$departure}%",
        'arrival' => "%{$arrival}%",
        'date' => $date,
    ]);
    $nextAvailableDate = $stmtNext->fetchColumn();
    if ($nextAvailableDate) {
        $_SESSION['next_available'] = $nextAvailableDate;
    }
}

// Helper to build URLs with preserved filters and added params
function buildUrl(array $params): string {
    return htmlspecialchars('?' . http_build_query($params));
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8" />
  <title>EcoRide - Résultats de recherche</title>
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" />
  <link rel="stylesheet" href="assets/css/style.css" />
</head>
<body>
<?php include 'includes/header.php'; ?>

<main class="container my-5">
  <h1 class="mb-4 text-center">Rechercher un covoiturage</h1>

  <!-- Search Form -->
  <form action="covoiturages.php" method="GET" class="row g-3 align-items-center mb-4">
    <div class="col-md-3 col-6">
      <input type="text" name="departure" class="form-control" placeholder="Ville de départ" required
             value="<?= $departure ?>" />
    </div>
    <div class="col-md-3 col-6">
      <input type="text" name="arrival" class="form-control" placeholder="Ville d'arrivée" required
             value="<?= $arrival ?>" />
    </div>
    <div class="col-md-2 col-6">
      <?php $today = date('Y-m-d'); ?>
        <input
          type="date"
          name="date"
          class="form-control"
          required
          min="<?= $today ?>"
          value="<?= $date ?>"
        />
    </div>

    <div class="col-md-2 col-6 d-grid">
      <button type="submit" class="btn btn-success">Rechercher</button>
    </div>

    <div class="col-md-2 col-6 d-grid">
      <button id="toggleFiltersBtn" class="btn btn-outline-secondary" type="button">
        <span class="btn-label">
          <?= ($filterEco || $filterMaxPrice !== null || $filterMinRating !== null || ($filterMaxDuration !== null && $filterMaxDuration < 12))
                ? 'Masquer les filtres'
                : 'Afficher les filtres' ?>
        </span>
      </button>
    </div>

    <div class="col-12">
      <div class="collapse" id="advancedFilters">
        <div class="card p-3 shadow-sm mt-3">
          <h5 class="mb-3">Filtres avancés</h5>
          <div class="row g-3 align-items-center">
            <!-- Eco filter -->
            <div class="col-md-3 col-12">
              <div class="form-check">
                <input class="form-check-input" type="checkbox" id="eco" name="eco" <?= $filterEco ? 'checked' : '' ?>>
                <label class="form-check-label" for="eco">Trajets écologiques uniquement</label>
              </div>
            </div>

            <!-- Max price -->
            <div class="col-md-3 col-6">
              <label for="max_price" class="form-label mb-1">Prix maximum (Crédits)</label>
              <input type="number" step="0.01" min="0" name="max_price" id="max_price" class="form-control"
                    value="<?= $filterMaxPrice !== null ? htmlspecialchars($filterMaxPrice) : '' ?>">
            </div>

            <!-- Min rating -->
            <div class="col-md-3 col-6">
              <label for="min_rating" class="form-label mb-1">Note minimale</label>
              <select name="min_rating" id="min_rating" class="form-select">
                <option value="">Aucune</option>
                <?php for ($i = 1; $i <= 5; $i += 0.5): ?>
                  <option value="<?= $i ?>" <?= ($filterMinRating == $i) ? 'selected' : '' ?>>
                    <?= $i ?> ★
                  </option>
                <?php endfor; ?>
              </select>
            </div>

            <!-- Max duration -->
            <div class="col-md-3 col-12">
              <label for="max_duration" class="form-label mb-1">Durée maximale du trajet (heures)</label>
              <input type="range" min="0.25" max="12" step="0.25" id="max_duration" name="max_duration"
                value="<?= $filterMaxDuration !== null ? htmlspecialchars($filterMaxDuration) : '12' ?>">
              <div id="durationLabel" class="form-text mt-1"></div>
            </div>
          </div>

          <div class="mt-3 text-end">
            <button type="submit" class="btn btn-primary">Appliquer les filtres</button>
          </div>
        </div>
      </div>
    </div>
  </form>

  <hr/>

<section id="search-results">
  <?php if (!$departure || !$arrival || !$date): ?>
    <!-- No search yet -->
    <p class="text-center text-muted">
      Veuillez saisir vos critères de recherche ci‑dessous pour voir les trajets.
    </p>

  <?php elseif (empty($filteredCarpools)): ?>
  <?php if ($nextAvailableDate): ?>
    <div class="text-center mt-4">
      <p class="fw-bold">
        Aucun trajet le <?= htmlspecialchars($date) ?>,  
        mais des places sont disponibles le  
        <strong><?= htmlspecialchars($nextAvailableDate) ?></strong>.
      </p>
      <?php
        $nextParams = [
          'departure'          => $departure,
          'arrival'            => $arrival,
          'date'               => $nextAvailableDate,
          'use_next_available' => 1,
        ];
        if ($filterEco)         $nextParams['eco']        = 1;
        if ($filterMaxPrice)    $nextParams['max_price']  = $filterMaxPrice;
        if ($filterMinRating)   $nextParams['min_rating'] = $filterMinRating;
        if ($filterMaxDuration) $nextParams['max_duration'] = $filterMaxDuration;
      ?>
      <a href="<?= buildUrl($nextParams) ?>" class="btn btn-outline-success">
        Voir les trajets pour cette date
      </a>
    </div>
  <?php else: ?>
    <p class="text-center text-danger">
      Aucun covoiturage trouvé pour ces critères.
    </p>
  <?php endif; ?>

  <?php else: ?>
    <!-- We have results, so render them -->
    <div class="row">
      <?php foreach ($filteredCarpools as $ride): ?>
        <div class="col-md-6 mb-4">
          <div class="card h-100 shadow-sm">
            <div class="card-body d-flex">
              <img src="<?= htmlspecialchars($ride['photo'] ?? 'images/drivers/default.jpg') ?>"
                   alt="Photo de <?= htmlspecialchars($ride['username']) ?>"
                   class="rounded-circle me-3"
                   style="width:70px;height:70px;object-fit:cover;">
              <div class="flex-grow-1">
                <h5>
                  <?= htmlspecialchars($ride['username']) ?>
                  <small class="text-muted">
                    <?= number_format(getDriverAverageRating($pdo, (int)$ride['driver_id']), 1) ?> ★
                  </small>
                  <?= displayEcoBadge($ride['fuel_type']) ?>
                </h5>
                <p class="mb-1">
                  Départ : <?= htmlspecialchars($ride['departure_city']) ?> à <?= htmlspecialchars($ride['departure_time']) ?>
                </p>
                <p class="mb-1">
                  Arrivée : <?= htmlspecialchars($ride['arrival_city']) ?> à <?= htmlspecialchars($ride['arrival_time']) ?>
                </p>
                <p class="mb-1">Date : <?= htmlspecialchars($ride['departure_date']) ?></p>
                <p class="mb-1">
                  Durée estimée : <?= formatDurationHMS((int)$ride['duration_seconds']) ?>
                </p>
                <p class="mb-1">
                  Places restantes : <?= (int)$ride['remaining_seats'] ?>
                </p>
                <p class="mb-1">Prix : <?= number_format($ride['price'], 2) ?> Crédits</p>
              </div>
              <div class="d-flex align-items-center">
                <a href="details.php?id=<?= (int)$ride['id'] ?>" class="btn btn-primary">
                  Détails
                </a>
              </div>
            </div>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</section>

</main>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
  document.addEventListener('DOMContentLoaded', () => {
    const toggleBtn = document.getElementById('toggleFiltersBtn');
    const labelSpan = toggleBtn.querySelector('.btn-label');
    const collapseEl = document.getElementById('advancedFilters');
    const bsCollapse = new bootstrap.Collapse(collapseEl, { toggle: false });

    collapseEl.addEventListener('show.bs.collapse', () => {
      labelSpan.textContent = 'Masquer les filtres';
    });
    collapseEl.addEventListener('hide.bs.collapse', () => {
      labelSpan.textContent = 'Afficher les filtres';
    });

    const durationActive = <?= ($filterMaxDuration !== null && $filterMaxDuration < 12) ? 'true' : 'false' ?>;
    if (<?= $filterEco ? 'true' : 'false' ?> || <?= $filterMaxPrice !== null ? 'true' : 'false' ?> || <?= $filterMinRating !== null ? 'true' : 'false' ?> || durationActive) {
      bsCollapse.show();
    }

    toggleBtn.addEventListener('click', () => bsCollapse.toggle());

    // Duration slider display update
    const durationInput = document.getElementById('max_duration');
    const durationLabel = document.getElementById('durationLabel');

    function updateDurationLabel() {
      const val = parseFloat(durationInput.value);
      const hours = Math.floor(val);
      const minutes = Math.round((val - hours) * 60);
      let text = '';
      if (hours > 0) text += hours + 'h';
      if (minutes > 0) text += minutes + 'm';
      if (text === '') text = '0m';
      durationLabel.textContent = text;
    }

    durationInput.addEventListener('input', updateDurationLabel);
    updateDurationLabel();
  });
</script>
<?php include 'includes/footer.php'; ?>

