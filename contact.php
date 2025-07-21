<?php
session_start();

require 'includes/db.php';
require_once 'includes/csrf.php';
generateCSRFToken();

$errors = [];
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $errors[] = "Jeton CSRF invalide. Veuillez réessayer.";
    } else {
        $name = trim($_POST['name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $subject = trim($_POST['subject'] ?? '');
        $message = trim($_POST['message'] ?? '');

        if ($name === '') {
            $errors[] = "Le nom est requis.";
        }
        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = "Un email valide est requis.";
        }
        if ($subject === '') {
            $errors[] = "Le sujet est requis.";
        }
        if ($message === '') {
            $errors[] = "Le message est requis.";
        }

        if (empty($errors)) {
            // Send email to your support or admin email
            $to = "support@ecoride.example.com";  // Replace with your actual support email
            $headers = "From: " . htmlspecialchars($email) . "\r\n"
                     . "Reply-To: " . htmlspecialchars($email) . "\r\n"
                     . "Content-Type: text/plain; charset=utf-8";

            $body = "Nom: $name\n";
            $body .= "Email: $email\n";
            $body .= "Sujet: $subject\n\n";
            $body .= "Message:\n$message\n";

            if (mail($to, "[Contact EcoRide] $subject", $body, $headers)) {
                $success = true;
            } else {
                $errors[] = "Erreur lors de l'envoi du message. Veuillez réessayer plus tard.";
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8" />
  <title>Contactez-nous - EcoRide</title>
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" />
  <link rel="stylesheet" href="assets/css/style.css" />
</head>
<body>
<?php include 'includes/header.php'; ?>

<main class="container my-5">
  <h1 class="mb-4 text-center">Contactez-nous</h1>

  <?php if ($success): ?>
    <div class="alert alert-success" role="alert">
      Merci pour votre message ! Nous vous répondrons dès que possible.
    </div>
  <?php else: ?>
    <?php if (!empty($errors)): ?>
      <div class="alert alert-danger">
        <ul>
          <?php foreach ($errors as $error): ?>
            <li><?= htmlspecialchars($error) ?></li>
          <?php endforeach; ?>
        </ul>
      </div>
    <?php endif; ?>

    <form action="contact.php" method="POST" class="mx-auto" style="max-width: 600px;">
      <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>" />

      <div class="mb-3">
        <label for="name" class="form-label">Nom</label>
        <input type="text" id="name" name="name" class="form-control" required
               value="<?= htmlspecialchars($_POST['name'] ?? '') ?>" />
      </div>

      <div class="mb-3">
        <label for="email" class="form-label">Adresse email</label>
        <input type="email" id="email" name="email" class="form-control" required
               value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" />
      </div>

      <div class="mb-3">
        <label for="subject" class="form-label">Sujet</label>
        <input type="text" id="subject" name="subject" class="form-control" required
               value="<?= htmlspecialchars($_POST['subject'] ?? '') ?>" />
      </div>

      <div class="mb-3">
        <label for="message" class="form-label">Message</label>
        <textarea id="message" name="message" class="form-control" rows="6" required><?= htmlspecialchars($_POST['message'] ?? '') ?></textarea>
      </div>

      <button type="submit" class="btn btn-success">Envoyer</button>
    </form>
  <?php endif; ?>
</main>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<?php include 'includes/footer.php'; ?>
</body>
</html>
