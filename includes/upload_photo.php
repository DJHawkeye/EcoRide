<?php
session_start();
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/csrf.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit;
}

$userId = $_SESSION['user_id'];

// Verify CSRF token
if (!isset($_POST['csrf_token']) || !verifyCSRFToken($_POST['csrf_token'])) {
    $_SESSION['upload_error'] = "Jeton CSRF invalide.";
    header('Location: ../profile.php');
    exit;
}

if (!isset($_FILES['photo']) || $_FILES['photo']['error'] !== UPLOAD_ERR_OK) {
    $_SESSION['upload_error'] = "Erreur lors du téléchargement du fichier.";
    header('Location: ../profile.php');
    exit;
}

$photo = $_FILES['photo'];

// Validate file size (max 2MB)
$maxFileSize = 2 * 1024 * 1024; // 2MB
if ($photo['size'] > $maxFileSize) {
    $_SESSION['upload_error'] = "Le fichier est trop volumineux (max 2MB).";
    header('Location: ../profile.php');
    exit;
}

// Validate file type (allow common image MIME types)
$allowedMimeTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
$finfo = new finfo(FILEINFO_MIME_TYPE);
$mimeType = $finfo->file($photo['tmp_name']);

if (!in_array($mimeType, $allowedMimeTypes, true)) {
    $_SESSION['upload_error'] = "Format de fichier non supporté. Veuillez uploader une image JPG, PNG, GIF ou WEBP.";
    header('Location: ../profile.php');
    exit;
}

// Prepare destination directory
$uploadDir = __DIR__ . '/../images/profiles/';
if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0755, true);
}

// Generate a unique filename
$ext = pathinfo($photo['name'], PATHINFO_EXTENSION);
$filename = sprintf('%s_%s.%s', $userId, bin2hex(random_bytes(8)), strtolower($ext));
$destination = $uploadDir . $filename;

// Move uploaded file
if (!move_uploaded_file($photo['tmp_name'], $destination)) {
    $_SESSION['upload_error'] = "Erreur lors de la sauvegarde du fichier.";
    header('Location: ../profile.php');
    exit;
}

// Update user's photo in database (store relative path)
$relativePath = "images/profiles/" . $filename;
$stmt = $pdo->prepare("UPDATE users SET photo = ? WHERE id = ?");
$stmt->execute([$relativePath, $userId]);

$_SESSION['upload_success'] = "Photo de profil mise à jour avec succès.";
header('Location: ../profile.php');
exit;
