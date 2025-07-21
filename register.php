<?php
session_start();
require_once 'includes/db.php';
require_once 'includes/csrf.php';

generateCSRFToken();

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $errors[] = "Échec de la vérification CSRF.";
    } else {
        $username = trim($_POST['username'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';

        if (empty($username) || strlen($username) < 3) {
            $errors[] = "Nom d'utilisateur invalide (au moins 3 caractères).";
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = "Adresse email invalide.";
        }

        if (
            strlen($password) < 10 ||
            !preg_match('/[A-Z]/', $password) ||
            !preg_match('/\d/', $password) ||
            !preg_match('/[^A-Za-z0-9]/', $password)
        ) {
            $errors[] = "Le mot de passe doit contenir au moins 10 caractères, une majuscule, un chiffre et un caractère spécial.";
        }

        // Check if username or email already exists (case-insensitive)
        if (empty($errors)) {
            $stmt = $pdo->prepare("SELECT username, email FROM users WHERE LOWER(email) = LOWER(?) OR LOWER(username) = LOWER(?)");
            $stmt->execute([$email, $username]);
            $existingUsers = $stmt->fetchAll();

            foreach ($existingUsers as $user) {
                if (strcasecmp($user['email'], $email) === 0) {
                    $errors[] = "Un compte existe déjà avec cet email.";
                }
                if (strcasecmp($user['username'], $username) === 0) {
                    $errors[] = "Ce nom d'utilisateur est déjà pris.";
                }
            }
        }

        if (empty($errors)) {
            $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
            $initialCredits = 20;

            // Insert user with initial credits
            $stmt = $pdo->prepare("INSERT INTO users (username, email, password_hash, credits) VALUES (?, ?, ?, ?)");
            $stmt->execute([$username, $email, $hashedPassword, $initialCredits]);

            // Auto-login
            $userId = $pdo->lastInsertId();
            $_SESSION['user_id'] = $userId;
            $_SESSION['username'] = $username;

            header("Location: profile.php");
            exit;
        }
    }
}
?>

<?php include 'includes/header.php'; ?>

<main class="container py-5" style="max-width: 400px;">
  <div class="card bg-white p-4 shadow rounded">
    <h2 class="text-center mb-4">Créer un compte</h2>

    <?php if (!empty($errors)): ?>
      <div class="alert alert-danger">
        <ul class="mb-0">
          <?php foreach ($errors as $error): ?>
            <li><?= htmlspecialchars($error) ?></li>
          <?php endforeach; ?>
        </ul>
      </div>
    <?php endif; ?>

    <form method="post" onsubmit="return validatePassword()">
      <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">

      <div class="mb-3">
        <label for="username" class="form-label">Nom d'utilisateur</label>
        <input type="text" name="username" id="username" class="form-control" required>
      </div>

      <div class="mb-3">
        <label for="email" class="form-label">Adresse email</label>
        <input type="email" name="email" id="email" class="form-control" required>
      </div>

      <div class="mb-3">
        <label for="password" class="form-label">Mot de passe</label>
        <input type="password" name="password" id="password" class="form-control" required oninput="checkPasswordStrength(this.value)">
        <div id="password-strength-text" class="text-muted mb-1"></div>
        <div class="progress mb-3">
          <div id="password-strength-bar" class="progress-bar" role="progressbar" style="width: 0%"></div>
        </div>
      </div>

      <button type="submit" class="btn btn-success w-100">S'inscrire</button>
    </form>
  </div>
</main>

<script>
function checkPasswordStrength(password) {
  const bar = document.getElementById("password-strength-bar");
  const text = document.getElementById("password-strength-text");

  let strength = 0;
  if (password.length >= 10) strength++;
  if (/[A-Z]/.test(password)) strength++;
  if (/\d/.test(password)) strength++;
  if (/[^A-Za-z0-9]/.test(password)) strength++;

  const labels = ["Très faible", "Faible", "Moyen", "Fort", "Très fort"];
  const colors = ["bg-danger", "bg-warning", "bg-info", "bg-primary", "bg-success"];

  bar.style.width = `${(strength / 4) * 100}%`;
  bar.className = `progress-bar ${colors[strength]}`;
  text.textContent = labels[strength];
}

function validatePassword() {
  const password = document.getElementById("password").value;
  const valid = password.length >= 10 &&
                /[A-Z]/.test(password) &&
                /\d/.test(password) &&
                /[^A-Za-z0-9]/.test(password);

  if (!valid) {
    alert("Le mot de passe doit contenir au moins 10 caractères, une majuscule, un chiffre et un caractère spécial.");
    return false;
  }
  return true;
}
</script>

<?php include 'includes/footer.php'; ?>
