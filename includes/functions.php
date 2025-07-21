<?php

function ensureDriver(PDO $pdo, int $userId): void {
    $stmt = $pdo->prepare("SELECT is_driver FROM users WHERE id=?");
    $stmt->execute([$userId]);
    $user = $stmt->fetch();
    if (!$user || !$user['is_driver']) {
        die('Accès refusé: Vous devez être conducteur.');
    }
}

function getUserVehicles(PDO $pdo, int $userId): array {
    $stmt = $pdo->prepare("SELECT id, brand, model, fuel, seats FROM vehicles WHERE user_id=?");
    $stmt->execute([$userId]);
    return $stmt->fetchAll();
}

function getCoordinates(string $city, string $apiKey): ?array {
    $url = "https://api.openrouteservice.org/geocode/search?text=" . urlencode($city);
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => [
            "Authorization: $apiKey",
            "Accept: application/json",
        ],
    ]);
    $response = curl_exec($ch);
    if ($response === false) {
        error_log("cURL error fetching coordinates for '$city': " . curl_error($ch));
        curl_close($ch);
        return null;
    }
    curl_close($ch);

    $data = json_decode($response, true);
    return $data['features'][0]['geometry']['coordinates'] ?? null;
}

function getTravelDuration(array $startCoords, array $endCoords, string $apiKey): ?float {
    $url = "https://api.openrouteservice.org/v2/directions/driving-car";
    $body = json_encode([
        "coordinates" => [$startCoords, $endCoords]
    ]);

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_HTTPHEADER => [
            "Content-Type: application/json",
            "Authorization: $apiKey"
        ],
        CURLOPT_POSTFIELDS => $body
    ]);

    $response = curl_exec($ch);
    if ($response === false) {
        error_log("cURL error fetching travel duration: " . curl_error($ch));
        curl_close($ch);
        return null;
    }
    curl_close($ch);

    $data = json_decode($response, true);
    return $data['routes'][0]['summary']['duration'] ?? null;
}

function generateTimeOptions(string $selectedTime = '09:00'): string {
    $times = [];
    for ($h = 0; $h < 24; $h++) {
        for ($m = 0; $m < 60; $m += 15) {
            $times[] = sprintf('%02d:%02d', $h, $m);
        }
    }

    $options = '';
    foreach ($times as $time) {
        $selected = ($time === $selectedTime) ? 'selected' : '';
        $options .= "<option value=\"$time\" $selected>$time</option>";
    }
    return $options;
}

function cleanCustomPreferences(array $prefs): array {
    $cleaned = [];
    foreach ($prefs as $p) {
        $p = trim($p);
        if ($p !== '') {
            if (mb_strlen($p) > 100) {
                throw new Exception("Une préférence est trop longue.");
            }
            $cleaned[] = $p;
        }
    }
    return $cleaned;
}

function getDriverAverageRating(PDO $pdo, int $driverId): float {
    $sql = "
        SELECT IFNULL(AVG(rv.rating), 0) AS avg_rating
        FROM reviews rv
        JOIN rides r ON rv.ride_id = r.id
        WHERE r.driver_id = :driver_id
          AND rv.status = 'approved'
    ";
    $stmt = $pdo->prepare($sql);
    $stmt->execute(['driver_id' => $driverId]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    return $result ? (float)$result['avg_rating'] : 0.0;
}

function getUserBooking(PDO $pdo, int $rideId, int $userId): ?array {
    $stmt = $pdo->prepare("
        SELECT b.id, r.driver_id, r.departure, r.destination, r.departure_time, u.email AS driver_email
        FROM bookings b
        JOIN rides r ON b.ride_id = r.id
        JOIN users u ON r.driver_id = u.id
        WHERE b.ride_id = ? AND b.passenger_id = ?
    ");
    $stmt->execute([$rideId, $userId]);
    return $stmt->fetch() ?: null;
}

function cancelBooking(PDO $pdo, int $bookingId, int $rideId): void {
    $pdo->beginTransaction();
    try {
        $del = $pdo->prepare("DELETE FROM bookings WHERE id = ?");
        $del->execute([$bookingId]);

        $upd = $pdo->prepare("UPDATE rides SET seats_available = seats_available + 1 WHERE id = ?");
        $upd->execute([$rideId]);

        $pdo->commit();
    } catch (Exception $e) {
        $pdo->rollBack();
        throw $e;
    }
}

function isUserRideDriver(PDO $pdo, int $rideId, int $userId): ?array {
    $stmt = $pdo->prepare("SELECT * FROM rides WHERE id = ? AND driver_id = ?");
    $stmt->execute([$rideId, $userId]);
    return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
}

function isValidRideStatusTransition(string $current, string $new): bool {
    $allowed = [
        'planned' => ['started'],
        'started' => ['ended'],
    ];
    return isset($allowed[$current]) && in_array($new, $allowed[$current], true);
}

function notifyRideEndedPassengers(PDO $pdo, int $rideId): void {
    $stmt = $pdo->prepare("
        SELECT u.email, u.username 
        FROM bookings b 
        JOIN users u ON b.passenger_id = u.id 
        WHERE b.ride_id = ?
    ");
    $stmt->execute([$rideId]);
    $passengers = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($passengers as $p) {
        $to = $p['email'];
        $subject = "Confirmation de trajet à valider";
        $message = "Bonjour {$p['username']},\n\n" .
                   "Le trajet que vous avez réservé est terminé.\n" .
                   "Merci de vous rendre sur votre espace utilisateur pour confirmer que tout s'est bien passé ou pour signaler un problème.\n\n" .
                   "Cordialement,\nEcoRide";
        $headers = "From: no-reply@ecoride.example.com";

        mail($to, $subject, $message, $headers);
    }
}

function getUserRide(PDO $pdo, int $rideId, int $userId): ?array {
    $stmt = $pdo->prepare("SELECT id, departure, destination, departure_time FROM rides WHERE id = ? AND driver_id = ?");
    $stmt->execute([$rideId, $userId]);
    $ride = $stmt->fetch(PDO::FETCH_ASSOC);
    return $ride ?: null;
}

function getRidePassengers(PDO $pdo, int $rideId): array {
    $stmt = $pdo->prepare("
        SELECT u.email, u.username
        FROM bookings b
        JOIN users u ON b.passenger_id = u.id
        WHERE b.ride_id = ?
    ");
    $stmt->execute([$rideId]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function cancelRide(PDO $pdo, int $rideId): void {
    $pdo->beginTransaction();

    $delBookings = $pdo->prepare("DELETE FROM bookings WHERE ride_id = ?");
    $delBookings->execute([$rideId]);

    $updateRide = $pdo->prepare("UPDATE rides SET status = 'cancelled' WHERE id = ?");
    $updateRide->execute([$rideId]);

    $pdo->commit();
}

function notifyPassengersRideCancelled(array $passengers, array $ride): void {
    $formattedDate = date('d/m/Y H:i', strtotime($ride['departure_time']));
    $subject = "Annulation de votre covoiturage EcoRide";

    foreach ($passengers as $p) {
        $body = "Bonjour " . htmlspecialchars($p['username']) . ",\n\n"
              . "Le conducteur a annulé le covoiturage prévu le {$formattedDate} entre "
              . htmlspecialchars($ride['departure']) . " et " . htmlspecialchars($ride['destination']) . ".\n\n"
              . "Vous pouvez consulter d'autres trajets disponibles sur EcoRide.\n\n"
              . "Merci de votre compréhension,\nL'équipe EcoRide";

        sendEmail($p['email'], $subject, $body);
    }
}

function userOwnsVehicle(PDO $pdo, int $vehicleId, int $userId): bool {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM vehicles WHERE id = ? AND user_id = ?");
    $stmt->execute([$vehicleId, $userId]);
    return (bool)$stmt->fetchColumn();
}

function deleteVehicle(PDO $pdo, int $vehicleId): void {
    $pdo->prepare("DELETE FROM vehicle_preferences WHERE vehicle_id = ?")->execute([$vehicleId]);
    $pdo->prepare("DELETE FROM vehicles WHERE id = ?")->execute([$vehicleId]);
}

function displayEcoBadge(string $fuel): string {
    $ecoFuels = ['électrique', 'hybride'];
    if (in_array(mb_strtolower($fuel), $ecoFuels)) {
        return '<span class="badge bg-success ms-2">Éco</span>';
    }
    return '';
}

function updateRide(PDO $pdo, int $rideId, int $userId, array $data, string $apiKey): array {
    // Returns ['success' => bool, 'error' => string|null]

    // Validate vehicle belongs to user
    $stmt = $pdo->prepare("SELECT id FROM vehicles WHERE user_id = ? AND id = ?");
    $stmt->execute([$userId, $data['vehicle_id']]);
    if (!$stmt->fetch()) {
        return ['success' => false, 'error' => "Véhicule invalide."];
    }

    $departure_datetime_str = $data['departure_date'] . ' ' . $data['departure_time'];

    $startCoords = getCoordinates($data['departure'], $apiKey);
    $endCoords = getCoordinates($data['destination'], $apiKey);
    if (!$startCoords || !$endCoords) {
        return ['success' => false, 'error' => "Impossible de localiser les villes spécifiées."];
    }

    $durationSeconds = getTravelDuration($startCoords, $endCoords, $apiKey);
    if (!$durationSeconds) {
        return ['success' => false, 'error' => "Erreur lors du calcul de la durée du trajet."];
    }

    $dt = DateTime::createFromFormat('Y-m-d H:i', $departure_datetime_str);
    if (!$dt) {
        return ['success' => false, 'error' => "Format de date/heure invalide."];
    }
    $dt->modify("+" . round($durationSeconds) . " seconds");
    $arrival_datetime = $dt->format('Y-m-d H:i:s');

    $stmt = $pdo->prepare("UPDATE rides SET 
        vehicle_id = ?, departure = ?, destination = ?, departure_time = ?, arrival_time = ?, seats_available = ?, price = ?
        WHERE id = ? AND driver_id = ?");

    $stmt->execute([
        $data['vehicle_id'], $data['departure'], $data['destination'], $departure_datetime_str, 
        $arrival_datetime, $data['seats_available'], $data['price'], $rideId, $userId
    ]);

    return ['success' => true, 'error' => null];
}

function cleanAndValidateCustomPreferences($prefs, &$errors) {
    $cleanedPrefs = [];

    foreach ($prefs as $p) {
        $p = trim($p);
        if ($p !== '') {
            if (mb_strlen($p) > 100) {
                $errors[] = "Une préférence est trop longue.";
                break;
            }
            $cleanedPrefs[] = $p;
        }
    }

    return empty($errors) ? $cleanedPrefs : null;
}

function getFuelTypes() {
    return ['Essence', 'Diesel', 'Hybride', 'Électrique'];
}

function updateUserRating(PDO $pdo, int $driverId): void {
    $stmt = $pdo->prepare("
        SELECT AVG(rv.rating) AS avg_rating
        FROM reviews rv
        JOIN rides r ON rv.ride_id = r.id
        WHERE r.driver_id = :driver_id
          AND rv.status = 'approved'
    ");
    $stmt->execute(['driver_id' => $driverId]);
    $avgRating = $stmt->fetchColumn();

    $avgRating = $avgRating !== null ? (float)$avgRating : 0;

    $stmtUpdate = $pdo->prepare("UPDATE users SET rating = :rating WHERE id = :driver_id");
    $stmtUpdate->execute(['rating' => $avgRating, 'driver_id' => $driverId]);
}

function isEmployee(PDO $pdo, int $userId): bool {
    $stmt = $pdo->prepare("SELECT role FROM users WHERE id = ?");
    $stmt->execute([$userId]);
    return $stmt->fetchColumn() === 'employee';
}

function getIntParam(string $key, int $default = 0): int {
    return isset($_GET[$key]) ? intval($_GET[$key]) : $default;
}

function formatRideTimestamps(array &$ride): void {
    $ride['departure_date']  = date('Y-m-d', strtotime($ride['departure_time']));
    $ride['departure_time']  = date('H:i',   strtotime($ride['departure_time']));
    $ride['arrival_time']    = date('H:i',   strtotime($ride['arrival_time']));
}

// Format duration like "1h15m"
function formatDurationHMS(int $seconds): string {
    $h = floor($seconds / 3600);
    $m = floor(($seconds % 3600) / 60);
    $parts = [];
    if ($h > 0) $parts[] = $h . 'h';
    if ($m > 0) $parts[] = $m . 'm';
    if (empty($parts)) return '0m';
    return implode('', $parts);
}

// Helper to render a ride card (driver or passenger)
function renderRideCard(array $ride, string $role, bool $withActions = false): void {
    // Extract fields to variables for convenience
    $departure = htmlspecialchars($ride['departure']);
    $destination = htmlspecialchars($ride['destination']);
    $fuel = htmlspecialchars($ride['fuel']);
    $departure_time = date('d/m/Y H:i', strtotime($ride['departure_time']));
    $arrival_time = date('d/m/Y H:i', strtotime($ride['arrival_time']));
    $duration = formatDurationHMS(strtotime($ride['arrival_time']) - strtotime($ride['departure_time']));
    $id = (int)($ride['id'] ?? 0);

    echo '<div class="card mb-3">';
    echo '<div class="card-body ride-card-body">';
    echo '<div class="ride-info-left">';
    echo "<p><strong>{$departure} → {$destination}</strong></p>";

    if ($role === 'passenger') {
        $driver_name = htmlspecialchars($ride['driver_name'] ?? 'N/A');
        echo "<p>Conducteur : {$driver_name}</p>";
    } elseif ($role === 'driver') {
        $seats = (int)($ride['seats_available'] ?? 0);
        echo "<p>Places : {$seats}</p>";
    }

    echo "<p>Carburant : {$fuel}</p>";
    echo '</div>'; // ride-info-left

    echo '<div class="ride-info-right">';
    echo "<p>Date de départ : {$departure_time}</p>";
    echo "<p>Date d'arrivée : {$arrival_time}</p>";
    echo "<p>Durée estimée : {$duration}</p>";
    echo '</div>'; // ride-info-right

    if ($role === 'driver' && $withActions) {
        echo '<div class="ride-card-buttons">';
        echo "<a href=\"includes/edit_ride.php?id={$id}\" class=\"btn btn-sm btn-secondary me-2\">Modifier</a>";
        echo "<a href=\"includes/delete_ride.php?id={$id}\" class=\"btn btn-sm btn-danger\" onclick=\"return confirm('Supprimer ce trajet ?');\">Supprimer</a>";
        echo '</div>';
    }

    echo '</div>'; // card-body
    echo '</div>'; // card
}

// Helper to render booking card for passenger (without actions)
function renderBookingCard(array $ride): void {
    // This just calls renderRideCard with role passenger and no actions
    renderRideCard($ride, 'passenger', false);
}

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