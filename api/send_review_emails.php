<?php
/**
 * api/send_review_emails.php
 * Envoyer les emails de demande d'avis (J+7 après commande)
 * Appeler via cron : 0 8 * * * curl https://votre-site.fr/cafe/api/send_review_emails.php?key=SECRET
 * Ou depuis l'admin manuellement
 */
define('ROOT', dirname(__DIR__));
require_once ROOT . '/includes/config.php';
require_once ROOT . '/includes/mailer.php';

// Clé secrète ou admin connecté
$key = $_GET['key'] ?? '';
$cfg_key = cfg_get()['review_cron_key'] ?? '';
$is_admin = is_logged();
$is_cron  = $cfg_key && hash_equals($cfg_key, $key);

if (!$is_admin && !$is_cron) {
    http_response_code(403);
    die('Accès refusé');
}

$cfg = cfg_get();
if (empty($cfg['email_review_enabled'])) {
    echo json_encode(['skipped' => true, 'reason' => 'email_review_enabled is false']);
    exit;
}

$delay_days = (int)($cfg['email_review_delay_days'] ?? 7);
$threshold  = date('Y-m-d H:i:s', strtotime("-$delay_days days"));
$threshold_end = date('Y-m-d H:i:s', strtotime("-" . ($delay_days - 1) . " days"));

$orders  = db('orders');
$sent_log = DATA_DIR . '/.review_sent.json';
$sent    = file_exists($sent_log) ? (json_decode(file_get_contents($sent_log), true) ?? []) : [];

$sent_ct = 0;
$skip_ct = 0;

foreach ($orders as $o) {
    // Seulement les commandes payées
    if (!in_array($o['status'] ?? '', ['paid','preparing','ready','shipped','delivered'])) continue;

    $created = $o['created_at'] ?? '';
    // Commandes créées dans la fenêtre J+7 ± 1 jour
    if ($created < $threshold_end && $created > $threshold) {
        $oid = $o['id'] ?? '';
        if (!$oid || isset($sent[$oid])) { $skip_ct++; continue; }

        if (mail_review_request($o)) {
            $sent[$oid] = date('Y-m-d H:i:s');
            $sent_ct++;
            usleep(200000); // 200ms entre chaque
        }
    }
}

// Sauvegarder les IDs traités (garder 90 jours max)
$cutoff = date('Y-m-d', strtotime('-90 days'));
$sent = array_filter($sent, fn($d) => $d >= $cutoff);
file_put_contents($sent_log, json_encode($sent), LOCK_EX);
@chmod($sent_log, 0600);

header('Content-Type: application/json');
echo json_encode(['sent' => $sent_ct, 'skipped' => $skip_ct, 'total_eligible' => $sent_ct + $skip_ct]);
