<?php
session_start();
require_once 'includes/db.php';
require_once 'includes/csrf.php';

generateCSRFToken();

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $error = "Requête non valide. Veuillez réessayer.";
    } else {
        $identifier = trim($_POST['username_or_email'] ?? '');
        $password = $_POST['password'] ?? '';

        if (empty($identifier) || empty($password)) {
            $error = "Veuillez remplir tous les champs.";
        } else {
            // Fetch id, username, password_hash, and is_suspended in one query
            $stmt = $pdo->prepare("SELECT id, username, password_hash, is_suspended FROM users WHERE username = ? OR email = ?");
            $stmt->execute([$identifier, $identifier]);
            $user = $stmt->fetch();

            if ($user) {
                if ($user['is_suspended']) {
                    // Block login for suspended users
                    $error = "Votre compte est suspendu. Veuillez contacter l'administrateur.";
                } elseif (password_verify($password, $user['password_hash'])) {
                    $_SESSION['user_id'] = $user['id'];
                    $_SESSION['username'] = $user['username'];

                    // Fetch user role
                    $stmtRole = $pdo->prepare("SELECT role FROM users WHERE id = ?");
                    $stmtRole->execute([$user['id']]);
                    $role = $stmtRole->fetchColumn();

                    $_SESSION['user_role'] = $role;

                    if ($role === 'employee') {
                        header('Location: employee_space.php');
                    } elseif ($role === 'admin') {
                        header('Location: admin_dashboard.php');
                    } else {
                        header('Location: profile.php');
                    }
                    exit;
                } else {
                    $error = "Identifiants incorrects.";
                }
            } else {
                $error = "Identifiants incorrects.";
            }
        }
    }
}
?>

<?php include 'includes/header.php'; ?>

<main class="container py-5" style="max-width: 400px;">
    <h2 class="mb-4 text-center">Connexion à EcoRide</h2>

    <?php if (!empty($error)): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <form method="POST" action="login.php" novalidate>
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">

        <div class="mb-3">
            <label for="username_or_email" class="form-label">Nom d'utilisateur ou Email</label>
            <input type="text" class="form-control" id="username_or_email" name="username_or_email" required value="<?= htmlspecialchars($_POST['username_or_email'] ?? '') ?>">
        </div>

        <div class="mb-3">
            <label for="password" class="form-label">Mot de passe</label>
            <input type="password" class="form-control" id="password" name="password" required>
        </div>

        <button type="submit" class="btn btn-success w-100">Se connecter</button>
    </form>

    <div class="text-center mt-3">
        <a href="register.php">Pas encore de compte ? Inscrivez-vous</a>
    </div>
</main>

<?php include 'includes/footer.php'; ?>
