<?php
session_start();
require 'includes/db.php';   // Your PDO connection

// Access control: only admin
if (!isset($_SESSION['user_id'], $_SESSION['user_role']) || $_SESSION['user_role'] !== 'admin') {
    header('Location: login.php');
    exit;
}

$message = '';
$error = '';

// Function to cancel user's upcoming rides as driver and passenger
function cancelUserUpcomingRides(PDO $pdo, int $userId) {
    // Cancel upcoming rides where user is driver
    $stmtDriver = $pdo->prepare("
        UPDATE rides
        SET status = 'cancelled'
        WHERE driver_id = :user_id
          AND status IN ('planned', 'started')
          AND departure_time > NOW()
    ");
    $stmtDriver->execute(['user_id' => $userId]);

    // Delete upcoming bookings where user is passenger
    $stmtPassenger = $pdo->prepare("
        DELETE b FROM bookings b
        JOIN rides r ON b.ride_id = r.id
        WHERE b.passenger_id = :user_id
          AND r.departure_time > NOW()
    ");
    $stmtPassenger->execute(['user_id' => $userId]);
}

// Handle employee creation form POST
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_employee'])) {
    $username = trim($_POST['username'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    // Basic validation
    if (!$username || !$email || !$password) {
        $error = 'Veuillez remplir tous les champs pour créer un employé.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Email invalide.';
    } else {
        // Check if username or email already exists
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE username = ? OR email = ?");
        $stmt->execute([$username, $email]);
        if ($stmt->fetchColumn() > 0) {
            $error = 'Nom d\'utilisateur ou email déjà utilisé.';
        } else {
            // Insert new employee
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("INSERT INTO users (username, email, password_hash, role, is_suspended) VALUES (?, ?, ?, 'employee', 0)");
            $stmt->execute([$username, $email, $hash]);
            $message = 'Employé créé avec succès.';
        }
    }
}

// Handle suspend/reactivate user POST
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['toggle_suspend'], $_POST['user_id'])) {
    $userId = intval($_POST['user_id']);
    
    // Prevent admin suspending self
    if ($userId === $_SESSION['user_id']) {
        $error = 'Vous ne pouvez pas suspendre votre propre compte.';
    } else {
        // Fetch current suspension status
        $stmt = $pdo->prepare("SELECT is_suspended FROM users WHERE id = ? LIMIT 1");
        $stmt->execute([$userId]);
        $status = $stmt->fetchColumn();

        if ($status === false) {
            $error = 'Utilisateur introuvable.';
        } else {
            // Toggle suspend
            $newStatus = $status ? 0 : 1;
            $stmt = $pdo->prepare("UPDATE users SET is_suspended = ? WHERE id = ?");
            $stmt->execute([$newStatus, $userId]);
            $message = $newStatus ? 'Compte suspendu.' : 'Compte réactivé.';

            // If suspended, cancel their upcoming rides and bookings
            if ($newStatus === 1) {
                cancelUserUpcomingRides($pdo, $userId);
            }
        }
    }
}

// Fetch all users (users + employees, exclude admins)
$stmt = $pdo->query("SELECT id, username, email, role, is_suspended FROM users WHERE role IN ('user', 'employee') ORDER BY role, username");
$users = $stmt->fetchAll(PDO::FETCH_ASSOC);

// === Month selection ===
// Default to current month (YYYY-MM)
$selectedMonth = $_GET['month'] ?? date('Y-m');
if (!preg_match('/^\d{4}-\d{2}$/', $selectedMonth)) {
    $selectedMonth = date('Y-m');
}

// Calculate start and end dates for the month
$startDate = $selectedMonth . '-01';
$endDate = date('Y-m-d', strtotime("$startDate +1 month -1 day"));

// Prepare date range for SQL
$startDateTime = $startDate . ' 00:00:00';
$endDateTime = $endDate . ' 23:59:59';

// === Fetch rides counts by status per day for selected month ===
$stmt = $pdo->prepare("
    SELECT 
        DATE(departure_time) AS ride_date,
        status,
        COUNT(*) AS count
    FROM rides
    WHERE departure_time BETWEEN :start AND :end
    GROUP BY ride_date, status
    ORDER BY ride_date ASC
");
$stmt->execute(['start' => $startDateTime, 'end' => $endDateTime]);
$rideStatusRaw = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Organize counts into arrays for each status keyed by date
$ridesByStatus = [
    'ended' => [],
    'planned' => [],
    'cancelled' => [],
    'started' => []
];

// Initialize all dates in month with 0 for each status
$period = new DatePeriod(
    new DateTime($startDate),
    new DateInterval('P1D'),
    (new DateTime($endDate))->modify('+1 day')
);
foreach ($period as $dt) {
    $dateStr = $dt->format('Y-m-d');
    foreach ($ridesByStatus as $statusKey => $_) {
        $ridesByStatus[$statusKey][$dateStr] = 0;
    }
}

// Fill actual data
foreach ($rideStatusRaw as $row) {
    $date = $row['ride_date'];
    $status = $row['status'];
    $count = (int)$row['count'];
    if (isset($ridesByStatus[$status])) {
        $ridesByStatus[$status][$date] = $count;
    }
}

// === Fetch credits earned per day for selected month ===
// Credits for admin = 2 credits per ride that is ended, validated by passenger, and approved by employee review
$stmt = $pdo->prepare("
    SELECT 
        DATE(r.departure_time) AS ride_date,
        COUNT(*) * 2 AS credits_earned
    FROM rides r
    JOIN ride_validations rv ON r.id = rv.ride_id
    JOIN reviews rev ON r.id = rev.ride_id
    WHERE r.status = 'ended'
      AND rv.validated = 1
      AND rev.status = 'approved'
      AND r.departure_time BETWEEN :start AND :end
    GROUP BY ride_date
    ORDER BY ride_date ASC
");
$stmt->execute(['start' => $startDateTime, 'end' => $endDateTime]);
$creditsDataRaw = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Organize credits data keyed by date
$creditsData = [];
foreach ($period as $dt) {
    $creditsData[$dt->format('Y-m-d')] = 0;
}
foreach ($creditsDataRaw as $row) {
    $creditsData[$row['ride_date']] = (float)$row['credits_earned'];
}

// === Fetch total admin credits earned dynamically (all time) ===
$stmt = $pdo->query("
    SELECT COUNT(*) * 2
    FROM rides r
    JOIN ride_validations rv ON r.id = rv.ride_id
    JOIN reviews rev ON r.id = rev.ride_id
    WHERE r.status = 'ended'
      AND rv.validated = 1
      AND rev.status = 'approved'
");
$totalCredits = $stmt->fetchColumn();

?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8" />
    <title>Admin Dashboard - EcoRide</title>
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" />
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body>
<header class="bg-success text-white p-3 mb-4">
    <div class="container d-flex justify-content-between align-items-center">
        <h1>Tableau de bord Administrateur</h1>
        <a href="includes/logout.php" class="btn btn-light" onclick="return confirm('Voulez-vous vous déconnecter ?');">Déconnexion</a>
    </div>
</header>

<main class="container">

    <?php if ($message): ?>
        <div class="alert alert-success"><?= htmlspecialchars($message) ?></div>
    <?php endif; ?>
    <?php if ($error): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <section class="mb-5">
        <h2>Créer un compte employé</h2>
        <form method="POST" class="row g-3">
            <input type="hidden" name="create_employee" value="1" />
            <div class="col-md-4">
                <label for="username" class="form-label">Nom d'utilisateur</label>
                <input type="text" id="username" name="username" required class="form-control" />
            </div>
            <div class="col-md-4">
                <label for="email" class="form-label">Email</label>
                <input type="email" id="email" name="email" required class="form-control" />
            </div>
            <div class="col-md-4">
                <label for="password" class="form-label">Mot de passe</label>
                <input type="password" id="password" name="password" required class="form-control" />
            </div>
            <div class="col-12">
                <button type="submit" class="btn btn-primary">Créer employé</button>
            </div>
        </form>
    </section>

    <section class="mb-5">
        <h2>Liste des utilisateurs et employés</h2>
        <table class="table table-striped table-bordered align-middle">
            <thead>
                <tr>
                    <th>Nom d'utilisateur</th>
                    <th>Email</th>
                    <th>Rôle</th>
                    <th>Statut</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($users as $u): ?>
                <tr>
                    <td><?= htmlspecialchars($u['username']) ?></td>
                    <td><?= htmlspecialchars($u['email']) ?></td>
                    <td><?= htmlspecialchars($u['role']) ?></td>
                    <td><?= $u['is_suspended'] ? '<span class="badge bg-danger">Suspendu</span>' : '<span class="badge bg-success">Actif</span>' ?></td>
                    <td>
                        <form method="POST" style="margin:0;">
                            <input type="hidden" name="user_id" value="<?= (int)$u['id'] ?>" />
                            <button type="submit" name="toggle_suspend" class="btn btn-sm <?= $u['is_suspended'] ? 'btn-success' : 'btn-danger' ?>" 
                                    onclick="return confirm('Êtes-vous sûr de vouloir <?= $u['is_suspended'] ? 'réactiver' : 'suspendre' ?> ce compte ?');">
                                <?= $u['is_suspended'] ? 'Réactiver' : 'Suspendre' ?>
                            </button>
                        </form>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php if (count($users) === 0): ?>
                <tr><td colspan="5" class="text-center">Aucun utilisateur trouvé.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </section>

    <section class="mb-5">
        <h2>Statistiques de la plateforme</h2>
        <form method="GET" class="mb-3 row g-3 align-items-center">
            <div class="col-auto">
                <label for="month" class="form-label">Mois :</label>
                <input type="month" id="month" name="month" value="<?= htmlspecialchars($selectedMonth) ?>" class="form-control" />
            </div>
            <div class="col-auto">
                <button type="submit" class="btn btn-primary">Afficher</button>
            </div>
        </form>

        <p><strong>Crédits totaux gagnés par l'administration :</strong> <?= number_format($totalCredits, 2) ?> Crédits</p>

        <div class="mb-3">
            <strong>Nombre total de covoiturages pour le mois sélectionné :</strong>
            <ul>
                <li>Terminés : <?= array_sum($ridesByStatus['ended']) ?></li>
                <li>Planifiés : <?= array_sum($ridesByStatus['planned']) ?></li>
                <li>Annulés : <?= array_sum($ridesByStatus['cancelled']) ?></li>
                <li>Commencés : <?= array_sum($ridesByStatus['started']) ?></li>
            </ul>
        </div>

        <div class="row">
            <div class="col-md-6">
                <h5>Nombre de covoiturages par jour (<?= htmlspecialchars($selectedMonth) ?>)</h5>
                <canvas id="ridesChart"></canvas>
            </div>
            <div class="col-md-6">
                <h5>Crédits gagnés par jour (<?= htmlspecialchars($selectedMonth) ?>)</h5>
                <canvas id="creditsChart"></canvas>
            </div>
        </div>
    </section>
</main>

<script>
// Prepare labels (dates) for charts (days of month)
const labels = <?= json_encode(array_keys($ridesByStatus['ended'])) ?>;

// Prepare datasets for rides by status
const datasets = [
    {
        label: 'Terminés',
        data: <?= json_encode(array_values($ridesByStatus['ended'])) ?>,
        borderColor: 'rgba(40, 167, 69, 1)', // Green
        backgroundColor: 'rgba(40, 167, 69, 0.2)',
        tension: 0.3,
        fill: true,
        pointRadius: 3
    },
    {
        label: 'Planifiés',
        data: <?= json_encode(array_values($ridesByStatus['planned'])) ?>,
        borderColor: 'rgba(0, 123, 255, 1)', // Blue
        backgroundColor: 'rgba(0, 123, 255, 0.2)',
        tension: 0.3,
        fill: true,
        pointRadius: 3
    },
    {
        label: 'Annulés',
        data: <?= json_encode(array_values($ridesByStatus['cancelled'])) ?>,
        borderColor: 'rgba(220, 53, 69, 1)', // Red
        backgroundColor: 'rgba(220, 53, 69, 0.2)',
        tension: 0.3,
        fill: true,
        pointRadius: 3
    },
    {
        label: 'Commencés',
        data: <?= json_encode(array_values($ridesByStatus['started'])) ?>,
        borderColor: 'rgba(255, 193, 7, 1)', // Yellow
        backgroundColor: 'rgba(255, 193, 7, 0.2)',
        tension: 0.3,
        fill: true,
        pointRadius: 3
    }
];

// Rides chart
const ctxRides = document.getElementById('ridesChart').getContext('2d');
const ridesChart = new Chart(ctxRides, {
    type: 'line',
    data: {
        labels: labels,
        datasets: datasets
    },
    options: {
        interaction: {
            mode: 'nearest',
            intersect: false
        },
        plugins: {
            tooltip: {
                mode: 'index',
                intersect: false
            }
        },
        scales: {
            x: { 
                display: true, 
                title: { display: true, text: 'Date' },
                ticks: {
                    maxRotation: 90,
                    minRotation: 45
                }
            },
            y: { 
                beginAtZero: true, 
                title: { display: true, text: 'Nombre de covoiturages' },
                precision: 0
            }
        }
    }
});

// Credits chart
const ctxCredits = document.getElementById('creditsChart').getContext('2d');
const creditsChart = new Chart(ctxCredits, {
    type: 'bar',
    data: {
        labels: labels,
        datasets: [{
            label: 'Crédits gagnés',
            data: <?= json_encode(array_values($creditsData)) ?>,
            backgroundColor: 'rgba(166, 123, 91, 0.7)',
            borderColor: 'rgba(166, 123, 91, 1)',
            borderWidth: 1
        }]
    },
    options: {
        scales: {
            x: { 
                display: true, 
                title: { display: true, text: 'Date' }
            },
            y: { 
                beginAtZero: true, 
                title: { display: true, text: 'Crédits' }
            }
        }
    }
});
</script>

</body>
</html>
