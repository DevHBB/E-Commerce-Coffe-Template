<?php
/**
 * api/paypal_capture.php
 */
ob_start();
error_reporting(0);
ini_set('display_errors', 0);

require_once __DIR__ . '/../includes/config.php';
header('Content-Type: application/json; charset=utf-8');

function ppout(array $data) {
    $json = json_encode($data, JSON_UNESCAPED_UNICODE);
    ob_end_clean();
    echo $json;
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    ppout(['error' => 'Method not allowed'));
}

$ip = isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : '';
if (!rate_ok('pp_' . $ip, 15, 60)) {
    http_response_code(429);
    ppout(['error' => 'Trop de requetes'));
}

$cfg  = cfg_get();
$raw  = file_get_contents('php://input');
$body = json_decode($raw, true);

if (!is_array($body)) {
    http_response_code(400);
    ppout(['error' => 'JSON invalide'));
}

$client_id     = isset($cfg['paypal_client_id'])     ? $cfg['paypal_client_id']     : '';
$client_secret = isset($cfg['paypal_client_secret']) ? $cfg['paypal_client_secret'] : '';

if (!$client_id || !$client_secret) {
    http_response_code(500);
    ppout(['error' => 'PayPal non configure (cles manquantes)'));
}

$mode     = isset($cfg['paypal_mode']) ? $cfg['paypal_mode'] : 'sandbox';
$base_url = ($mode === 'live')
    ? 'https://api-m.paypal.com'
    : 'https://api-m.sandbox.paypal.com';

$action = isset($body['action']) ? $body['action'] : 'capture';

// ─── CREER UN ORDRE ─────────────────────────────────────────────────────────
if ($action === 'create') {

    // 1. Token OAuth2
    $ch = curl_init($base_url . '/v1/oauth2/token');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_USERPWD, $client_id . ':' . $client_secret);
    curl_setopt($ch, CURLOPT_POSTFIELDS, 'grant_type=client_credentials');
    curl_setopt($ch, CURLOPT_TIMEOUT, 15);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    $tok_raw = curl_exec($ch);
    $tok_err = curl_error($ch);
    curl_close($ch);

    if ($tok_raw === false) {
        ppout(['error' => 'cURL error: ' . $tok_err));
    }

    $tok = json_decode($tok_raw, true);
    if (!is_array($tok) || empty($tok['access_token'])) {
        $err_desc = isset($tok['error_description']) ? $tok['error_description'] : $tok_raw;
        ppout(['error' => 'Auth PayPal echouee: ' . $err_desc));
    }
    $access_token = $tok['access_token'];

    // 2. Calculer le montant (simple, sans shipping.php)
    $cart     = isset($body['cart']) && is_array($body['cart']) ? $body['cart'] : [);
    $products = db('products');
    $prod_idx = [);
    foreach ($products as $p) {
        $prod_idx[$p['id']] = $p;
    }

    $subtotal = 0.0;
    foreach ($cart as $item) {
        $id  = isset($item['id'])  ? (string)$item['id']      : '';
        $qty = isset($item['qty']) ? max(1, (int)$item['qty']) : 1;
        // Carte cadeau
        if (strncmp($id, 'gc-', 3) === 0) {
            $subtotal += round((float)isset($item['price']) ? $item['price'] : 0, 2) * $qty;
            continue;
        }
        if (!isset($prod_idx[$id])) continue;
        $p = $prod_idx[$id];
        if (empty($p['active'])) continue;
        $subtotal += round((float)$p['price'] * $qty, 2);
    }

    // Livraison (simple)
    $delivery_mode = isset($body['delivery_mode']) ? $body['delivery_mode'] : 'delivery';
    $shipping_cost = 0.0;
    if ($delivery_mode !== 'pickup') {
        $free_from = (float)isset($cfg['shipping_free_from']) ? $cfg['shipping_free_from'] : 35;
        $ship_cost = (float)isset($cfg['shipping_cost'])      ? $cfg['shipping_cost']      : 4.90;
        $shipping_cost = ($subtotal >= $free_from) ? 0.0 : $ship_cost;
    }

    // Promo
    $discount = 0.0;
    $promo_code = isset($body['promo_code']) ? strtoupper(preg_replace('/[^A-Z0-9_-]/', '', (string)$body['promo_code'])) : '';
    if ($promo_code) {
        $promos = db('promos');
        foreach ($promos as $pr) {
            if (!isset($pr['code']) || $pr['code'] !== $promo_code) continue;
            if (empty($pr['active'])) continue;
            if (!empty($pr['expires']) && $pr['expires'] < date('Y-m-d')) continue;
            if (!empty($pr['usage_max']) && (int)$pr['usage_max'] > 0 && (int)(isset($pr['usage_ct']) ? $pr['usage_ct'] : 0) >= (int)$pr['usage_max']) continue;
            if ($pr['type'] === 'percent') {
                $discount = round($subtotal * (float)$pr['value'] / 100, 2);
            } else {
                $discount = min($subtotal, (float)$pr['value']);
            }
            break;
        }
    }

    $total = round(max(0.01, $subtotal - $discount + $shipping_cost), 2);
    $currency = isset($cfg['currency']) ? strtoupper($cfg['currency']) : 'EUR';
    $sitename = isset($cfg['site_name']) ? $cfg['site_name'] : 'Cafe Maison';

    // 3. Creer l'ordre PayPal
    $order_data = [
        'intent' => 'CAPTURE',
        'purchase_units' => [
            [
                'amount' => [
                    'currency_code' => $currency,
                    'value'         => number_format($total, 2, '.', ''),
                ),
                'description' => $sitename . ' - commande',
            )
        ),
    );

    $ch = curl_init($base_url . '/v2/checkout/orders');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: Bearer ' . $access_token,
        'Content-Type: application/json',
    ));
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($order_data));
    curl_setopt($ch, CURLOPT_TIMEOUT, 20);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    $ord_raw  = curl_exec($ch);
    $ord_err  = curl_error($ch);
    $ord_http = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($ord_raw === false) {
        ppout(['error' => 'cURL order error: ' . $ord_err));
    }

    $ord = json_decode($ord_raw, true);
    if (!is_array($ord) || empty($ord['id'])) {
        $detail = isset($ord['message']) ? $ord['message'] : $ord_raw;
        ppout(['error' => 'PayPal order failed (HTTP ' . $ord_http . '): ' . $detail));
    }

    ppout(['id' => $ord['id']));
}

// ─── CAPTURER UN ORDRE ──────────────────────────────────────────────────────
require_once ROOT . '/includes/shipping.php';
require_once ROOT . '/includes/mailer.php';

$pp_order_id   = isset($body['orderID']) ? preg_replace('/[^A-Z0-9-]/', '', strtoupper($body['orderID'])) : '';
if (!$pp_order_id) {
    $pp_order_id = isset($body['order_id']) ? preg_replace('/[^A-Z0-9-]/', '', strtoupper($body['order_id'])) : '';
}
$delivery_mode = 'delivery';
if (isset($body['delivery_mode']) && in_array($body['delivery_mode'], ['delivery', 'pickup'), true)) {
    $delivery_mode = $body['delivery_mode'];
}
$cart = isset($body['cart']) && is_array($body['cart']) ? $body['cart'] : [);

if (!$pp_order_id || empty($cart)) {
    http_response_code(400);
    ppout(['error' => 'Donnees manquantes pour la capture'));
}

// Token OAuth2
$ch = curl_init($base_url . '/v1/oauth2/token');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_USERPWD, $client_id . ':' . $client_secret);
curl_setopt($ch, CURLOPT_POSTFIELDS, 'grant_type=client_credentials');
curl_setopt($ch, CURLOPT_TIMEOUT, 15);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
$token_resp = json_decode(curl_exec($ch), true);
curl_close($ch);

$access_token = isset($token_resp['access_token']) ? $token_resp['access_token'] : '';
if (!$access_token) {
    http_response_code(500);
    ppout(['error' => 'Erreur auth PayPal (capture)'));
}

// Capturer
$ch = curl_init($base_url . '/v2/checkout/orders/' . $pp_order_id . '/capture');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Authorization: Bearer ' . $access_token,
    'Content-Type: application/json',
));
curl_setopt($ch, CURLOPT_POSTFIELDS, '{}');
curl_setopt($ch, CURLOPT_TIMEOUT, 20);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
$capture_resp   = curl_exec($ch);
$capture_status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

$capture   = json_decode($capture_resp, true);
$pp_status = isset($capture['status']) ? $capture['status'] : '';

if ($capture_status !== 201 || $pp_status !== 'COMPLETED') {
    $msg = isset($capture['details'][0]['description']) ? $capture['details'][0]['description'] : 'Capture echouee HTTP=' . $capture_status;
    http_response_code(400);
    ppout(['error' => $msg));
}

// Recalculer total
$products = db('products');
$prod_idx = [);
foreach ($products as $p) $prod_idx[$p['id']] = $p;

$server_total = 0.0;
$clean_cart   = [);
foreach ($cart as $item) {
    $id  = isset($item['id'])  ? (string)$item['id']      : '';
    $qty = isset($item['qty']) ? max(1, (int)$item['qty']) : 1;
    if (!isset($prod_idx[$id]) || empty($prod_idx[$id]['active'])) continue;
    $p     = $prod_idx[$id];
    $price = round((float)$p['price'], 2);
    $server_total += $price * $qty;
    $clean_cart[] = ['id'=>$id,'name'=>(string)$p['name'],'qty'=>$qty,'price'=>$price,'emoji'=>isset($p['emoji']) ? $p['emoji'] : '');
    foreach ($products as &$prod) {
        if ($prod['id'] === $id) {
            $prod['stock'] = max(0, (isset($prod['stock']) ? (int)$prod['stock'] : 0) - $qty);
        }
    }
    unset($prod);
}

$country  = isset($body['country']) ? preg_replace('/[^A-Z]/', '', strtoupper($body['country'])) : 'FR';
$weight_g = cart_total_weight($clean_cart, $prod_idx, isset($cfg['shipping_packaging_g']) ? (int)$cfg['shipping_packaging_g'] : 150);
$ship     = calculate_shipping($country, $server_total, $delivery_mode, $cfg, $weight_g);
$total    = round($server_total + $ship['cost'], 2);

db_save('products', $products);

$payer    = isset($capture['payer']) ? $capture['payer'] : [);
$customer = [
    'fname'   => clean(isset($body['fname']) ? $body['fname'] : (isset($payer['name']['given_name']) ? $payer['name']['given_name'] : ''), 80),
    'lname'   => clean(isset($body['lname']) ? $body['lname'] : (isset($payer['name']['surname'])    ? $payer['name']['surname']    : ''), 80),
    'email'   => filter_var(isset($body['email']) ? $body['email'] : (isset($payer['email_address']) ? $payer['email_address'] : ''), FILTER_VALIDATE_EMAIL),
    'phone'   => clean(isset($body['phone']) ? $body['phone'] : '', 20),
    'addr'    => $delivery_mode === 'pickup' ? '' : clean(isset($body['addr']) ? $body['addr'] : '', 200),
    'zip'     => $delivery_mode === 'pickup' ? '' : clean(isset($body['zip'])  ? $body['zip']  : '', 10),
    'city'    => $delivery_mode === 'pickup' ? '' : clean(isset($body['city']) ? $body['city'] : '', 100),
    'country' => $country,
);

$order = [
    'id'             => new_id(),
    'paypal_id'      => $pp_order_id,
    'amount'         => $total,
    'currency'       => 'EUR',
    'cart'           => $clean_cart,
    'customer'       => $customer,
    'delivery_mode'  => $delivery_mode,
    'status'         => 'paid',
    'method'         => 'paypal',
    'created_at'     => date('Y-m-d H:i:s'),
    'paid_at'        => date('Y-m-d H:i:s'),
);

$orders   = db('orders');
$orders[] = $order;
db_save('orders', $orders);

try { mail_order($order); } catch (Exception $e) {}

admin_log('order_paid', 'PayPal ' . $pp_order_id . ' ' . $total . 'EUR');

ppout(['ok' => true, 'success' => true, 'order_id' => $order['id'], 'total' => $total));
