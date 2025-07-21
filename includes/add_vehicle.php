<?php
session_start();
require_once __DIR__.'/db.php';
require_once __DIR__.'/csrf.php';
require_once __DIR__.'/functions.php';
require_once __DIR__ . '/js_helpers.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit;
}
$userId = $_SESSION['user_id'];

ensureDriver($pdo, $userId);
generateCSRFToken();


$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $errors[] = "Échec CSRF.";
    } else {
        $lp    = trim($_POST['license_plate']     ?? '');
        $reg   = $_POST['first_registration']     ?? '';
        $brand = trim($_POST['brand']             ?? '');
        $model = trim($_POST['model']             ?? '');
        $fuel  = $_POST['fuel']                   ?? '';
        $color = trim($_POST['color']             ?? '');
        $seats = intval($_POST['seats']           ?? 0);
        $smk   = isset($_POST['allow_smoking'])   ? 1 : 0;
        $pet   = isset($_POST['allow_pets'])      ? 1 : 0;
        $mus   = isset($_POST['allow_music'])     ? 1 : 0;

        if ($lp === '' || $brand === '' || $model === '' || !in_array($fuel, ['Essence','Diesel','Hybride','Électrique']) || $seats < 1) {
            $errors[] = "Veuillez remplir tous les champs obligatoires correctement.";
        }

        // Process single-field preferences
        try {
            $prefs = $_POST['custom_prefs'] ?? [];
            $cleanedPrefs = cleanCustomPreferences($prefs);
        } catch (Exception $e) {
            $errors[] = $e->getMessage();
        }

        $cpJson = null;
        if (empty($errors) && !empty($cleanedPrefs)) {
            $cpJson = json_encode($cleanedPrefs, JSON_UNESCAPED_UNICODE);
            if ($cpJson === false) {
                $errors[] = "Erreur lors de l'encodage des préférences personnalisées.";
            }
        }
    }

    if (empty($errors)) {
        $insV = $pdo->prepare(
          "INSERT INTO vehicles
            (user_id, license_plate, first_registration, brand, model, fuel, color, seats)
           VALUES (?, ?, ?, ?, ?, ?, ?, ?)"
        );
        $insV->execute([$userId, $lp, $reg, $brand, $model, $fuel, $color, $seats]);
        $vid = $pdo->lastInsertId();

        $insP = $pdo->prepare(
          "INSERT INTO vehicle_preferences
             (vehicle_id, allow_smoking, allow_pets, allow_music, custom_preferences)
           VALUES (?, ?, ?, ?, ?)"
        );
        $insP->execute([$vid, $smk, $pet, $mus, $cpJson]);

        header('Location: ../profile.php?success=1');
        exit;
    }
}

include 'header.php';
?>

<main class="container py-5" style="max-width: 600px;">
  <h2 class="mb-4">Ajouter un véhicule</h2>
  <?php if ($errors): ?>
    <div class="alert alert-danger">
      <ul>
        <?php foreach ($errors as $e) echo '<li>' . htmlspecialchars($e) . '</li>'; ?>
      </ul>
    </div>
  <?php endif; ?>

  <form method="post" novalidate>
    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">

    <div class="mb-3">
      <label class="form-label">Plaque d'immatriculation</label>
      <input name="license_plate" class="form-control" required value="<?= htmlspecialchars($_POST['license_plate'] ?? '') ?>">
    </div>

    <div class="mb-3">
      <label class="form-label">1ère immatriculation</label>
      <input name="first_registration" type="date" class="form-control" value="<?= htmlspecialchars($_POST['first_registration'] ?? '') ?>">
    </div>

    <div class="row">
      <div class="col mb-3">
        <label class="form-label">Marque</label>
        <input name="brand" class="form-control" required value="<?= htmlspecialchars($_POST['brand'] ?? '') ?>">
      </div>
      <div class="col mb-3">
        <label class="form-label">Modèle</label>
        <input name="model" class="form-control" required value="<?= htmlspecialchars($_POST['model'] ?? '') ?>">
      </div>
      <div class="col mb-3">
        <label class="form-label">Carburant</label>
        <select name="fuel" class="form-select" required>
          <?php
          $fuels = ['Essence', 'Diesel', 'Hybride', 'Électrique'];
          $selFuel = $_POST['fuel'] ?? '';
          foreach ($fuels as $opt): ?>
            <option <?= ($selFuel === $opt) ? 'selected' : '' ?>><?= htmlspecialchars($opt) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
    </div>

    <div class="row">
      <div class="col mb-3">
        <label class="form-label">Couleur</label>
        <input name="color" class="form-control" required value="<?= htmlspecialchars($_POST['color'] ?? '') ?>">
      </div>
      <div class="col mb-3">
        <label class="form-label">Places</label>
        <input name="seats" type="number" min="1" class="form-control" required value="<?= (int)($_POST['seats'] ?? '') ?>">
      </div>
    </div>

    <div class="form-check form-check-inline">
      <input class="form-check-input" type="checkbox" name="allow_smoking" id="allow_smoking"
        <?= isset($_POST['allow_smoking']) ? 'checked' : '' ?>>
      <label class="form-check-label" for="allow_smoking">Fumer autorisé</label>
    </div>
    <div class="form-check form-check-inline">
      <input class="form-check-input" type="checkbox" name="allow_pets" id="allow_pets"
        <?= isset($_POST['allow_pets']) ? 'checked' : '' ?>>
      <label class="form-check-label" for="allow_pets">Animaux autorisés</label>
    </div>
    <div class="form-check form-check-inline mb-3">
      <input class="form-check-input" type="checkbox" name="allow_music" id="allow_music"
        <?= isset($_POST['allow_music']) ? 'checked' : '' ?>>
      <label class="form-check-label" for="allow_music">Musique autorisée</label>
    </div>

    <label class="form-label">Préférences personnalisées</label>
    <div id="customPrefsContainer">
      <?php
      $prefs = $_POST['custom_prefs'] ?? [''];
      foreach ($prefs as $p):
      ?>
      <div class="custom-pref-row mb-2 d-flex gap-2">
        <input type="text" name="custom_prefs[]" placeholder="Préférence (ex: calme, pas d’animaux)" class="form-control" value="<?= htmlspecialchars($p) ?>" />
        <button type="button" class="btn btn-outline-danger btn-sm remove-pref">×</button>
      </div>
      <?php endforeach; ?>
    </div>
    <button type="button" id="addPrefBtn" class="btn btn-outline-secondary btn-sm mb-3">Ajouter une préférence</button>

    <div class="mt-4">
      <button class="btn btn-success">Ajouter</button>
      <a href="../profile.php" class="btn btn-secondary ms-2">Annuler</a>
    </div>
  </form>

  <?php echoCustomPrefsScript(); ?>

</main>

<?php include 'footer.php'; ?>
