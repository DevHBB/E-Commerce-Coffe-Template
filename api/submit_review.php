<?php
/**
 * api/submit_review.php — Soumettre un avis client
 */
require_once __DIR__ . '/../includes/config.php';
require_once ROOT . '/includes/captcha.php';
security_headers();
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405); echo json_encode(['error' => 'Method not allowed']); exit;
}

$ip = $_SERVER['REMOTE_ADDR'] ?? '';
if (!rate_ok('review_' . $ip, 3, 600)) {
    echo json_encode(['ok' => false, 'error' => 'Trop de soumissions. Réessayez dans 10 minutes.']); exit;
}

$cfg  = cfg_get();
$body = json_decode(file_get_contents('php://input'), true) ?: [];

// Vérification reCAPTCHA v3 si activé pour les avis
if (!empty($cfg['captcha_reviews']) && !empty($cfg['captcha_site_key'])) {
    $cap_tok = $body['captcha_token'] ?? ($_POST['g-recaptcha-response'] ?? '');
    $cap_res = captcha_verify($cfg, $cap_tok);
    if (!$cap_res['ok']) {
        echo json_encode(['ok' => false, 'error' => $cap_res['error']]); exit;
    }
}

// Validation des champs
$author  = clean($body['author']  ?? '', 80);
$email   = filter_var($body['email'] ?? '', FILTER_VALIDATE_EMAIL) ?: '';
$rating  = max(1, min(5, (int)($body['rating'] ?? 5)));
$content = clean($body['content'] ?? '', 1000);
$target  = clean($body['target']  ?? 'product', 30); // product | workshop | blog

if (!$author || !$content) {
    echo json_encode(['ok' => false, 'error' => 'Prénom et avis requis.']); exit;
}

// Sauvegarder l'avis (en attente de modération)
$reviews = db('reviews');
$reviews[] = [
    'id'        => new_id(),
    'author'    => $author,
    'email'     => $email,
    'rating'    => $rating,
    'content'   => $content,
    'target'    => $target,
    'status'    => 'pending', // pending → approved via l'admin
    'ip'        => hash('sha256', $ip),
    'created'   => date('Y-m-d H:i'),
];
db_save('reviews', $reviews);

echo json_encode(['ok' => true]);
