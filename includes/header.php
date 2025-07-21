<?php
// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$currentPage = basename($_SERVER['PHP_SELF']);
$isLoggedIn = isset($_SESSION['user_id']);

// Default credits display
$userCredits = null;

if ($isLoggedIn) {
    require 'db.php';  // Adjust path if needed

    // Fetch only the credits to avoid overwriting the full user array in profile.php
    $stmt = $pdo->prepare("SELECT credits FROM users WHERE id = :id LIMIT 1");
    $stmt->execute(['id' => $_SESSION['user_id']]);
    $userCreditsRow = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($userCreditsRow) {
        $userCredits = (int)$userCreditsRow['credits'];
        $_SESSION['user_credits'] = $userCredits;  // Optionally keep it in session
    }
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8" />
  <title>EcoRide</title>
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" />
  <link rel="stylesheet" href="assets/css/style.css" />
  <script src="https://kit.fontawesome.com/0786b37048.js" crossorigin="anonymous"></script>
</head>

<body class="<?= in_array($currentPage, ['index.php','covoiturages.php']) 
                 ? ($currentPage === 'index.php' ? 'home-page' : 'carpool-page')
                 : 'other-page' ?>">

<header>
  <nav class="navbar navbar-expand-lg navbar-dark bg-success sticky-top">
    <div class="container">
      <a class="navbar-brand" href="/ecoride-project-v2/index.php">EcoRide</a>
      <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#mainNav">
        <span class="navbar-toggler-icon"></span>
      </button>
      <div class="collapse navbar-collapse" id="mainNav">
        <ul class="navbar-nav ms-auto align-items-center">
          <li class="nav-item">
            <a class="nav-link <?= $currentPage === 'index.php' ? 'active' : '' ?>"
               href="/ecoride-project-v2/index.php">Accueil</a>
          </li>
          <li class="nav-item">
            <a class="nav-link <?= $currentPage === 'covoiturages.php' ? 'active' : '' ?>"
               href="/ecoride-project-v2/covoiturages.php">Covoiturages</a>
          </li>
          <li class="nav-item">
            <a class="nav-link <?= $currentPage === 'contact.php' ? 'active' : '' ?>"
               href="/ecoride-project-v2/contact.php">Contact</a>
          </li>
          <li class="nav-item d-flex align-items-center">
            <?php if (!$isLoggedIn): ?>
              <a class="nav-link <?= $currentPage === 'login.php' ? 'active' : '' ?>"
                 href="/ecoride-project-v2/login.php" title="Connexion" style="font-size:1.2rem;">
                <i class="fas fa-user"></i>
              </a>
            <?php else: ?>
              <?php
                // Check if user is employee
                $isEmployee = isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'employee';
                $profileLink = $isEmployee ? 'employee_space.php' : 'profile.php';
              ?>
              <a class="nav-link <?= $currentPage === basename($profileLink) ? 'active' : '' ?>"
                 href="/ecoride-project-v2/<?= $profileLink ?>" title="Mon Profil"
                 style="font-size:1.2rem; display: flex; align-items: center;">
                <i class="fas fa-user"></i>
                <?php if ($userCredits !== null && !$isEmployee): ?>
                  <span class="credits-badge ms-1" title="Crédits disponibles"><?= $userCredits ?></span>
                <?php endif; ?>
              </a>
            <?php endif; ?>
          </li>
          <?php if ($isLoggedIn): ?>
          <li class="nav-item">
            <a class="nav-link" href="/ecoride-project-v2/includes/logout.php"
               title="Déconnexion" onclick="return confirm('Voulez-vous vous déconnecter ?');"
               style="font-size:1.2rem">
              <i class="fas fa-sign-out-alt"></i>
            </a>
          </li>
          <?php endif; ?>
        </ul>
      </div>
    </div>
  </nav>
</header>
