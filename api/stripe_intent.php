<?php
/**
 * api/stripe_intent.php — Création PaymentIntent Stripe
 */
require_once __DIR__ . '/../includes/config.php';
require_once ROOT . '/includes/shipping.php';
security_headers();
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405); echo json_encode(['error'=>'Method not allowed']); exit;
}

// Rate limit par IP
$ip = $_SERVER['REMOTE_ADDR'] ?? '';
if (!rate_ok('stripe_' . $ip, 10, 60)) {
    http_response_code(429); echo json_encode(['error'=>'Trop de requêtes']); exit;
}

$cfg = cfg_get(); // Déchiffre les clés API
$sk  = $cfg['stripe_sk'] ?? '';

if (!$sk || !preg_match('/^(sk|rk)_(live|test)_/', $sk)) {
    http_response_code(500); echo json_encode(['error'=>'Stripe non configuré']); exit;
}

$body = json_decode(file_get_contents('php://input'), true);
if (!is_array($body)) {
    http_response_code(400); echo json_encode(['error'=>'JSON invalide']); exit;
}

// Validation du panier
$cart = $body['cart'] ?? [];
if (!is_array($cart) || empty($cart)) {
    http_response_code(400); echo json_encode(['error'=>'Panier vide']); exit;
}

// Recalcul serveur du total (NE JAMAIS faire confiance au total client)
$products = db('products');
$prod_idx  = [];
foreach ($products as $p) $prod_idx[$p['id']] = $p;

$server_total = 0;
$line_items   = [];
foreach ($cart as $item) {
    $id  = clean($item['id'] ?? '', 30);
    $qty = max(1, min(99, (int)($item['qty'] ?? 1)));

    // Carte cadeau : id commence par 'gc-'
    if ((strncmp($id, 'gc-', 3) === 0)) {
        $gc_amount = max(5, min(500, round((float)($item['price'] ?? 0), 2)));
        if ($gc_amount <= 0) continue;
        $server_total += $gc_amount * $qty;
        $line_items[]  = 'Carte cadeau ' . number_format($gc_amount,2,',',' ') . ' € x' . $qty;
        $clean_cart[]  = [
            'id'    => $id,
            'name'  => $item['name'] ?? 'Carte cadeau',
            'qty'   => $qty,
            'price' => $gc_amount,
            'tva'   => 20,
            'emoji' => '🎁',
            'gc_to'     => clean($item['gc_to'] ?? '', 80),
            'gc_from'   => clean($item['gc_from'] ?? '', 80),
            'gc_email'  => filter_var($item['gc_email'] ?? '', FILTER_VALIDATE_EMAIL) ?: '',
            'gc_message'=> clean($item['gc_message'] ?? '', 500),
            'gc_type'   => clean($item['gc_type'] ?? 'libre', 30),
        ];
        continue;
    }

    if (!isset($prod_idx[$id])) continue;
    $p    = $prod_idx[$id];
    if (!($p['active'] ?? true)) continue;
    $ttc_u = (float)$p['price']; // prix déjà TTC
    $line  = round($ttc_u * $qty, 2);
    $server_total += $line;
    $line_items[]  = $p['name'] . ' x' . $qty;
}

if ($server_total <= 0) {
    http_response_code(400); echo json_encode(['error'=>'Panier invalide']); exit;
}

// Livraison (retrait = gratuit)
// Calcul livraison via includes/shipping.php
// Infos client
$delivery_mode   = in_array($body['delivery_mode'] ?? 'delivery', ['delivery','pickup'], true)
    ? $body['delivery_mode'] : 'delivery';
$country_code    = preg_replace('/[^A-Z]/', '', strtoupper($body['country'] ?? 'FR'));
// Calculer le poids total du panier pour les frais Colissimo
$weight_g        = cart_total_weight($clean_cart, $prod_idx, (int)($cfg['shipping_packaging_g'] ?? 150));
$ship_result     = calculate_shipping($country_code, $server_total, $delivery_mode, $cfg, $weight_g ?? 0);

// Bloquer si livraison internationale désactivée
if ($ship_result['blocked']) {
    http_response_code(400);
    echo json_encode(['error' => $ship_result['message']]);
    exit;
}

$shipping = $ship_result['cost'];
// Code promo: vérification côté serveur
$promo_code     = strtoupper(preg_replace('/[^A-Z0-9_-]/', '', $body['promo_code'] ?? ''));
$promo_discount = 0;
if ($promo_code) {
    $promos = db('promos');
    foreach ($promos as $pr) {
        if (($pr['code'] ?? '') === $promo_code && !empty($pr['active'])
            && (!$pr['expires'] || $pr['expires'] >= date('Y-m-d'))
            && (!(int)($pr['usage_max']??0) || ($pr['usage_ct']??0) < (int)$pr['usage_max'])) {
            $promo_discount = $pr['type'] === 'percent'
                ? round($server_total * (float)$pr['value'] / 100, 2)
                : min($server_total, (float)$pr['value']);
            // Incrémenter le compteur d'utilisation
            $pr2s = db('promos');
            foreach ($pr2s as &$pr2) {
                if ($pr2['code'] === $promo_code) { $pr2['usage_ct'] = ($pr2['usage_ct']??0)+1; break; }
            } unset($pr2);
            db_save('promos', $pr2s);
            break;
        }
    }
}
$total    = round(max(0, $server_total - $promo_discount) + $shipping, 2);
$amount_ct = (int)round($total * 100); // centimes
$customer = [
    'fname' => clean($body['fname'] ?? '', 80),
    'lname' => clean($body['lname'] ?? '', 80),
    'email' => filter_var($body['email'] ?? '', FILTER_VALIDATE_EMAIL) ?: '',
    'phone' => preg_replace('/[^0-9+\s\-()]/', '', $body['phone'] ?? ''),
    'addr'  => $delivery_mode === 'pickup' ? '' : clean($body['addr'] ?? '', 200),
    'zip'   => $delivery_mode === 'pickup' ? '' : preg_replace('/[^0-9]/', '', $body['zip'] ?? ''),
    'city'  => $delivery_mode === 'pickup' ? '' : clean($body['city'] ?? '', 100),
];

// Appel Stripe
$params = [
    'amount'   => $amount_ct,
    'currency' => strtolower($cfg['currency'] ?? 'eur'),
    'automatic_payment_methods[enabled]' => 'true',
    'description' => 'Commande Café Maison — ' . implode(', ', array_slice($line_items, 0, 3)),
    'metadata[source]'  => 'cafe_maison',
    'metadata[mode]'    => $delivery_mode,
    'metadata[items]'   => implode(', ', array_slice($line_items, 0, 5)),
    'metadata[total]'   => $total,
    'metadata[email]'   => $customer['email'],
    'metadata[name]'    => trim($customer['fname'] . ' ' . $customer['lname']),
];
if ($customer['email']) {
    $params['receipt_email'] = $customer['email'];
}

$ch = curl_init('https://api.stripe.com/v1/payment_intents');
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST           => true,
    CURLOPT_USERPWD        => $sk . ':',
    CURLOPT_HTTPHEADER     => ['Content-Type: application/x-www-form-urlencoded'],
    CURLOPT_POSTFIELDS     => http_build_query($params),
    CURLOPT_SSL_VERIFYPEER => true,
    CURLOPT_TIMEOUT        => 20,
]);
$resp   = curl_exec($ch);
$status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$err_c  = curl_error($ch);
curl_close($ch);

if ($err_c) {
    security_log('stripe_curl_error', $err_c);
    http_response_code(500); echo json_encode(['error'=>'Erreur réseau']); exit;
}

$data = json_decode($resp, true);
if ($status !== 200 || empty($data['client_secret'])) {
    $msg = $data['error']['message'] ?? 'Erreur Stripe';
    security_log('stripe_error', "HTTP=$status msg=$msg");
    http_response_code(500); echo json_encode(['error'=>$msg]); exit;
}

// Sauvegarder commande en attente (avec total serveur)
// Construire le panier avec prix TTC recalculés
$clean_cart = [];
foreach ($cart as $item) {
    $id  = clean($item['id'] ?? '', 30);
    $qty = max(1, min(99, (int)($item['qty'] ?? 1)));
    if (!isset($prod_idx[$id])) continue;
    $p    = $prod_idx[$id];
    $tva  = (int)($p['tva'] ?? ($cfg['tva_default'] ?? 20));
    $ttc  = round($p['price'] * (1 + $tva/100), 2);
    $clean_cart[] = [
        'id'    => $id,
        'name'  => $p['name'],
        'qty'   => $qty,
        'price' => $ttc,
        'tva'   => $tva,
        'emoji' => $p['emoji'] ?? '',
    ];
}

$orders   = db('orders');
$orders[] = [
    'id'            => new_id(),
    'intent_id'     => $data['id'],
    'amount'        => $total,
    'currency'      => strtoupper($cfg['currency'] ?? 'EUR'),
    'cart'          => array_slice($clean_cart, 0, 50),
    'customer'      => $customer,
    'delivery_mode' => $delivery_mode,
    'discount'      => $promo_discount,
    'promo_code'    => $promo_code ?: null,
    'status'        => 'pending', // → 'paid' via webhook payment_intent.succeeded
    'method'        => 'stripe',
    'created_at'    => date('Y-m-d H:i:s'),
];
db_save('orders', $orders);

echo json_encode(['client_secret' => $data['client_secret'], 'total' => $total]);
