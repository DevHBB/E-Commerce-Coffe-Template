<?php
/**
 * api/colissimo_test.php — Test connexion API Colissimo
 */
require_once __DIR__ . '/../includes/config.php';
require_auth();
security_headers();
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405); echo json_encode(['error'=>'Method not allowed']); exit;
}

$cfg      = cfg_get();
$login    = $cfg['colissimo_api_login']    ?? '';
$password = $cfg['colissimo_api_password'] ?? '';
$zip      = $cfg['colissimo_sender_zip']   ?? '75001';

if (!$login || !$password) {
    echo json_encode(['ok'=>false, 'error'=>'Identifiants API non configurés.']);
    exit;
}

// Appel API Colissimo pour vérifier les identifiants
// Endpoint de test: calculateDeliveryDate (requête simple)
$payload = json_encode([
    'login'    => $login,
    'password' => $password,
    'date'     => date('d/m/Y'),
    'departurePostalCode' => $zip,
    'depositDate'         => date('Y-m-d'),
    'countryCode'         => 'FR',
    'product'             => 'DOM',
]);

$ch = curl_init('https://ws.colissimo.fr/sls-ws/SlsServiceWSImpl/2.0/calculateDeliveryDate');
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST           => true,
    CURLOPT_HTTPHEADER     => ['Content-Type: application/json','Accept: application/json'],
    CURLOPT_POSTFIELDS     => $payload,
    CURLOPT_SSL_VERIFYPEER => true,
    CURLOPT_TIMEOUT        => 10,
]);
$response = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curl_error = curl_error($ch);
curl_close($ch);

if ($curl_error) {
    echo json_encode(['ok'=>false, 'error'=>"Erreur cURL: $curl_error"]);
    exit;
}

$data = json_decode($response, true);

// L'API Colissimo retourne des messages d'erreur dans messages[].id
if (isset($data['messages']) && !empty($data['messages'])) {
    $msg = $data['messages'][0]['messageContent'] ?? 'Identifiants invalides';
    if (($data['messages'][0]['type'] ?? '') === 'INFOS') {
        echo json_encode(['ok'=>true, 'message'=>'Connexion OK']);
    } else {
        echo json_encode(['ok'=>false, 'error'=>$msg]);
    }
    exit;
}

echo json_encode(['ok' => $http_code === 200, 'http_code' => $http_code]);
