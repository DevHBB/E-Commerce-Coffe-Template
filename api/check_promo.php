<?php
require_once __DIR__ . '/../includes/config.php';
security_headers();
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405); echo json_encode(['error'=>'Method not allowed']); exit;
}

$ip = $_SERVER['REMOTE_ADDR'] ?? '';
if (!rate_ok('promo_'.$ip, 20, 60)) {
    http_response_code(429); echo json_encode(['error'=>'Trop de tentatives']); exit;
}

$body  = json_decode(file_get_contents('php://input'), true) ?: [];
$code  = strtoupper(preg_replace('/[^A-Z0-9_-]/','', $body['code'] ?? ''));
$total = max(0, (float)($body['total'] ?? 0));
$cart  = $body['cart'] ?? [];

if (!$code) { echo json_encode(['error'=>'Code manquant']); exit; }

$promos = db('promos');
$promo  = null;
foreach ($promos as $p) {
    if (($p['code'] ?? '') === $code) { $promo = $p; break; }
}

if (!$promo) { echo json_encode(['error'=>'Code invalide']); exit; }
if (!($promo['active'] ?? true)) { echo json_encode(['error'=>'Code inactif']); exit; }
if (($promo['expires'] ?? '') && $promo['expires'] < date('Y-m-d')) {
    echo json_encode(['error'=>'Code expiré']); exit;
}
$usage_max = (int)($promo['usage_max'] ?? 0);
$usage_ct  = (int)($promo['usage_ct']  ?? 0);
if ($usage_max > 0 && $usage_ct >= $usage_max) {
    echo json_encode(['error'=>'Code épuisé']); exit;
}
$min_order = (float)($promo['min_order'] ?? 0);
if ($min_order > 0 && $total < $min_order) {
    echo json_encode(['error'=>'Minimum de commande '.number_format($min_order,2,',','.').' € non atteint']); exit;
}

// Calculer la remise
$discount = 0;
$scope    = $promo['scope'] ?? 'all';
$scope_id = $promo['scope_id'] ?? '';

if ($scope === 'all') {
    $base = $total;
} elseif ($scope === 'product' && $scope_id) {
    $base = 0;
    foreach ($cart as $item) {
        if (($item['id'] ?? '') === $scope_id) $base += (float)($item['price'] ?? 0) * (int)($item['qty'] ?? 1);
    }
} elseif ($scope === 'workshop' && $scope_id) {
    $base = 0;
    foreach ($cart as $item) {
        if (($item['id'] ?? '') === $scope_id) $base += (float)($item['price'] ?? 0) * (int)($item['qty'] ?? 1);
    }
} else {
    $base = $total;
}

if ($promo['type'] === 'percent') {
    $discount = round($base * $promo['value'] / 100, 2);
} else {
    $discount = min($base, round((float)$promo['value'], 2));
}

echo json_encode([
    'ok'          => true,
    'code'        => $code,
    'type'        => $promo['type'],
    'value'       => $promo['value'],
    'discount'    => $discount,
    'scope'       => $scope,
    'label'       => $promo['type']==='percent' ? '-'.($promo['value']+0).'%' : '-'.number_format($discount,2,',','.').' €',
]);
