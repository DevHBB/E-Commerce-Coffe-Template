<?php
require_once __DIR__ . '/../includes/config.php';
header('Content-Type: application/json');
$cfg = cfg_get();
$result = [
    'paypal_client_id_set' => !empty($cfg['paypal_client_id']),
    'paypal_secret_set'    => !empty($cfg['paypal_client_secret']),
    'paypal_mode'          => $cfg['paypal_mode'] ?? 'sandbox',
    'curl_available'       => function_exists('curl_init'),
    'manual_enabled'       => !empty($cfg['manual_payment_enabled']),
    'stripe_pk_set'        => !empty($cfg['stripe_pk']),
];
if (!empty($cfg['paypal_client_id']) && !empty($cfg['paypal_client_secret'])) {
    $mode    = $cfg['paypal_mode'] ?? 'sandbox';
    $base    = $mode === 'live' ? 'https://api-m.paypal.com' : 'https://api-m.sandbox.paypal.com';
    $ch = curl_init("$base/v1/oauth2/token");
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_USERPWD        => $cfg['paypal_client_id'] . ':' . $cfg['paypal_client_secret'],
        CURLOPT_POSTFIELDS     => 'grant_type=client_credentials',
        CURLOPT_TIMEOUT        => 10,
    ]);
    $raw = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err  = curl_error($ch);
    curl_close($ch);
    $tok  = json_decode($raw, true);
    $result['paypal_auth_http'] = $code;
    $result['paypal_auth_ok']   = isset($tok['access_token']);
    $result['paypal_auth_error']= $tok['error'] ?? $err ?? '';
}
echo json_encode($result, JSON_PRETTY_PRINT);
