<?php
session_start();
require_once 'includes/db.php';
require_once 'includes/csrf.php';
generateCSRFToken();

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$userId = $_SESSION['user_id'];

// Ensure user is a passenger
$stmt = $pdo->prepare("SELECT is_passenger FROM users WHERE id = ?");
$stmt->execute([$userId]);
if (!(bool)$stmt->fetchColumn()) {
    http_response_code(403);
    echo "Accès refusé.";
    exit;
}

$ride_id = (int)($_GET['ride_id'] ?? 0);
if ($ride_id <= 0) {
    die("ID de trajet invalide.");
}

// Default review values
$defaultComment = "Parfait (généré automatiquement).";
$defaultRating = 5;

$error = '';
$success = '';

// ------------------
// HANDLE FORM SUBMIT
// ------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $error = "Token CSRF invalide.";
    } else {
        $ride_id = (int)($_POST['ride_id'] ?? 0);
        $choice = $_POST['validation_choice'] ?? ''; // 'ok' or 'problem'

        $rating = (int)($_POST['rating'] ?? $defaultRating);
        $rating = max(1, min(5, $rating)); // Ensure 1-5
        $comment = trim($_POST['comment'] ?? '');

        if ($choice !== 'ok' && $choice !== 'problem') {
            $error = "Vous devez choisir une option.";
        } else {
            // ---------------
            // USER CHOSE OK
            // ---------------
            if ($choice === 'ok') {
                if ($comment === '') {
                    $comment = $defaultComment;
                }

                // Mark ride as validated with no problem
                $stmt = $pdo->prepare("
                    INSERT INTO ride_validations (ride_id, passenger_id, validated, problem_reported)
                    VALUES (?, ?, 1, 0)
                    ON DUPLICATE KEY UPDATE validated = 1, problem_reported = 0
                ");
                $stmt->execute([$ride_id, $userId]);

                // Check for existing review
                $stmtCheck = $pdo->prepare("SELECT id FROM reviews WHERE ride_id = ? AND passenger_id = ?");
                $stmtCheck->execute([$ride_id, $userId]);
                $existingReview = $stmtCheck->fetch(PDO::FETCH_ASSOC);

                $autoApprove = ($rating === $defaultRating) && ($comment === $defaultComment);

                if ($existingReview) {
                    // Update existing review
                    $stmtUpdate = $pdo->prepare("
                        UPDATE reviews SET rating = ?, comment = ?, created_at = NOW(), status = ?
                        WHERE id = ?
                    ");
                    $stmtUpdate->execute([
                        $rating,
                        $comment,
                        $autoApprove ? 'approved' : 'pending',
                        $existingReview['id']
                    ]);
                } else {
                    // Insert new review
                    $stmtInsert = $pdo->prepare("
                        INSERT INTO reviews (ride_id, passenger_id, rating, comment, created_at, status)
                        VALUES (?, ?, ?, ?, NOW(), ?)
                    ");
                    $stmtInsert->execute([
                        $ride_id,
                        $userId,
                        $rating,
                        $comment,
                        $autoApprove ? 'approved' : 'pending'
                    ]);
                }

                $_SESSION['success_message'] = $autoApprove
                    ? "Validation enregistrée avec succès."
                    : "Validation enregistrée et en attente d'approbation.";
                header('Location: profile.php');
                exit;

            // ------------------
            // USER REPORTED ISSUE
            // ------------------
            } elseif ($choice === 'problem') {
                if ($comment === '') {
                    $comment = "Problème signalé sans commentaire.";
                }

                // Check if problem already exists
                $stmtCheck = $pdo->prepare("
                    SELECT COUNT(*) FROM problems
                    WHERE ride_id = ? AND passenger_id = ?
                ");
                $stmtCheck->execute([$ride_id, $userId]);
                $exists = $stmtCheck->fetchColumn() > 0;

                if (!$exists) {
                    // Insert new problem
                    $stmtInsert = $pdo->prepare("
                        INSERT INTO problems (ride_id, passenger_id, comment, created_at, status)
                        VALUES (?, ?, ?, NOW(), 'pending')
                    ");
                    $stmtInsert->execute([$ride_id, $userId, $comment]);
                }

                // Mark ride as validated with problem
                $stmtValidation = $pdo->prepare("
                    INSERT INTO ride_validations (ride_id, passenger_id, validated, problem_reported)
                    VALUES (?, ?, 1, 1)
                    ON DUPLICATE KEY UPDATE validated = 1, problem_reported = 1
                ");
                $stmtValidation->execute([$ride_id, $userId]);

                $_SESSION['success_message'] = "Problème signalé. Un employé va examiner votre signalement.";
                header('Location: profile.php');
                exit;
            }
        }
    }
}
?>

<?php include 'includes/header.php'; ?>

<main class="container py-5" style="max-width: 600px;">
    <h2>Valider le trajet <?= htmlspecialchars($ride_id) ?></h2>

    <?php if ($error): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <form method="post" novalidate>
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
        <input type="hidden" name="ride_id" value="<?= htmlspecialchars($ride_id) ?>">

        <div class="mb-3">
            <label class="form-label d-block">Choisissez une option</label>
            <div class="form-check form-check-inline">
                <input class="form-check-input" type="radio" name="validation_choice" id="choice_ok" value="ok"
                    <?= (isset($_POST['validation_choice']) && $_POST['validation_choice'] === 'ok') ? 'checked' : '' ?> required>
                <label class="form-check-label" for="choice_ok">
                    <i class="fas fa-check-circle text-success"></i> Tout s'est bien passé
                </label>
            </div>
            <div class="form-check form-check-inline">
                <input class="form-check-input" type="radio" name="validation_choice" id="choice_problem" value="problem"
                    <?= (isset($_POST['validation_choice']) && $_POST['validation_choice'] === 'problem') ? 'checked' : '' ?> required>
                <label class="form-check-label" for="choice_problem">
                    <i class="fas fa-exclamation-triangle text-danger"></i> J'ai rencontré un problème
                </label>
            </div>
        </div>

        <div class="mb-3">
            <label for="rating" class="form-label">Note (1 à 5 étoiles)</label>
            <select name="rating" id="rating" class="form-select">
                <?php
                $selectedRating = $_POST['rating'] ?? $defaultRating;
                for ($i = 1; $i <= 5; $i++): ?>
                    <option value="<?= $i ?>" <?= $selectedRating == $i ? 'selected' : '' ?>><?= $i ?> ⭐</option>
                <?php endfor; ?>
            </select>
        </div>

        <div class="mb-3">
            <label for="comment" class="form-label">Commentaire</label>
            <textarea name="comment" id="comment" class="form-control" rows="4"><?= htmlspecialchars($_POST['comment'] ?? $defaultComment) ?></textarea>
        </div>

        <button type="submit" class="btn btn-primary">Valider</button>
        <a href="profile.php" class="btn btn-secondary ms-2">Annuler</a>
    </form>
</main>

<script>
document.addEventListener('DOMContentLoaded', () => {
    const choiceOk = document.getElementById('choice_ok');
    const choiceProblem = document.getElementById('choice_problem');
    const ratingField = document.getElementById('rating').closest('.mb-3');
    const commentField = document.getElementById('comment').closest('.mb-3');

    function toggleFields() {
        if (choiceOk.checked) {
            ratingField.style.display = '';
            commentField.style.display = '';
        } else if (choiceProblem.checked) {
            ratingField.style.display = 'none';
            commentField.style.display = '';
        }
    }

    choiceOk.addEventListener('change', toggleFields);
    choiceProblem.addEventListener('change', toggleFields);

    toggleFields(); // on load
});
</script>

<?php include 'includes/footer.php'; ?>
