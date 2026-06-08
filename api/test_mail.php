<?php
require_once __DIR__ . '/../includes/config.php';
require_once ROOT . '/includes/mailer.php';
require_auth();
header('Content-Type: application/json');

$cfg = cfg_get();
$to  = clean($_POST['to'] ?? ($cfg['email'] ?? ''), 150);
if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
    echo json_encode(['ok'=>false, 'msg'=>'Email de destination invalide']);
    exit;
}

// Vérifier la config SMTP
if (empty($cfg['smtp_host'])) {
    echo json_encode(['ok'=>false, 'msg'=>'SMTP non configuré (smtp_host vide)']);
    exit;
}

$result = cm_mail($to, 'Test', 'Test email — Café Maison',
    '<h2>Test email</h2><p>Si vous recevez cet email, la configuration SMTP est correcte.</p>');

echo json_encode([
    'ok'  => $result,
    'msg' => $result ? "Email envoyé à $to avec succès !" : "Échec d'envoi. Vérifiez host/user/pass SMTP et consultez les logs PHP.",
    'smtp_host' => $cfg['smtp_host'] ?? '',
    'smtp_from' => $cfg['smtp_from'] ?? '',
]);
