<?php
require_once __DIR__ . '/../includes/config.php';
require_auth();
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405); echo json_encode(['error'=>'Method not allowed']); exit;
}

$file = $_FILES['file'] ?? null;
if (!$file || $file['error'] !== UPLOAD_ERR_OK) {
    echo json_encode(['error'=>'Fichier manquant ou erreur upload']); exit;
}

// Vérifications sécurité
$max_size = 5 * 1024 * 1024; // 5 MB
if ($file['size'] > $max_size) {
    echo json_encode(['error'=>'Fichier trop volumineux (max 5 MB)']); exit;
}

// Vérifier le type MIME réel (pas le Content-Type envoyé par le client)
$finfo = new finfo(FILEINFO_MIME_TYPE);
$mime  = $finfo->file($file['tmp_name']);
$allowed_mimes = ['image/jpeg','image/png','image/gif','image/webp'];
if (!in_array($mime, $allowed_mimes, true)) {
    echo json_encode(['error'=>'Type de fichier non autorisé. Utilisez JPG, PNG, GIF ou WebP.']); exit;
}

// Extension sécurisée basée sur le MIME (jamais sur le nom du fichier client)
$ext_map = ['image/jpeg'=>'jpg','image/png'=>'png','image/gif'=>'gif','image/webp'=>'webp'];
$ext  = $ext_map[$mime];

// Nom de fichier aléatoire (jamais le nom original)
$filename = bin2hex(random_bytes(12)) . '.' . $ext;
$dest_dir = ROOT . '/assets/uploads/';
$dest     = $dest_dir . $filename;

// Double vérification que la destination est bien dans uploads/
if ((strpos(realpath($dest_dir), realpath(ROOT . '/assets/uploads'))) === false) {
    echo json_encode(['error'=>'Erreur de chemin']); exit;
}

// Déplacer le fichier
if (!move_uploaded_file($file['tmp_name'], $dest)) {
    echo json_encode(['error'=>'Erreur lors du déplacement du fichier']); exit;
}

@chmod($dest, 0644);

// Retourner l'URL relative à la racine du script (pas hardcodée /cafe/)
// Calcul: chemin du dossier uploads relatif à la racine web
$script_path = str_replace('\\', '/', $_SERVER['SCRIPT_FILENAME'] ?? '');
$doc_root    = str_replace('\\', '/', $_SERVER['DOCUMENT_ROOT'] ?? '');
$dest_rel    = '/' . ltrim(str_replace($doc_root, '', str_replace('\\', '/', ROOT . '/assets/uploads/' . $filename)), '/');
// Fallback: construire depuis REQUEST_URI
if (!$doc_root || strpos($dest_rel, 'WINDOWS') !== false) {
    $uri_dir = dirname(dirname($_SERVER['REQUEST_URI'] ?? '/api/upload.php'));
    $dest_rel = rtrim($uri_dir, '/') . '/assets/uploads/' . $filename;
}
$url = $dest_rel;

echo json_encode(['ok'=>true, 'url'=>$url, 'filename'=>$filename]);
