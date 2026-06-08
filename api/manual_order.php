<?php
ob_start();
error_reporting(0);
require_once __DIR__ . '/../includes/config.php';
require_once ROOT . '/includes/shipping.php';
require_once ROOT . '/includes/mailer.php';
header('Content-Type: application/json; charset=utf-8');

function out(array $d) {
    ob_end_clean();
    echo json_encode($d, JSON_UNESCAPED_UNICODE);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') out(['error' => 'Method not allowed']);

$ip = $_SERVER['REMOTE_ADDR'] ?? '';
if (!rate_ok('manual_'.$ip, 5, 300)) out(['error' => 'Trop de requêtes, réessayez dans 5 minutes']);

$cfg = cfg_get();
if (empty($cfg['manual_payment_enabled'])) out(['error' => 'Paiement manuel non disponible']);

// Lire le body JSON
$raw  = file_get_contents('php://input');
$body = json_decode($raw, true);
if (!is_array($body)) out(['error' => 'Données invalides']);

// CSRF
$csrf_session = $_SESSION['_csrf'] ?? '';
$csrf_post    = $body['_csrf'] ?? '';
if (!$csrf_session || !hash_equals($csrf_session, $csrf_post)) {
    out(['error' => 'Session expirée, rechargez la page']);
}

// Panier
$cart_raw = $body['cart'] ?? [];
if (empty($cart_raw) || !is_array($cart_raw)) out(['error' => 'Panier vide']);

// Recalcul côté serveur
$products = db('products');
$prod_idx = [];
foreach ($products as $p) $prod_idx[$p['id']] = $p;

$server_total = 0.0;
$clean_cart   = [];
foreach ($cart_raw as $item) {
    $id  = (string)($item['id'] ?? '');
    $qty = max(1, min(99, (int)($item['qty'] ?? 1)));
    // Carte cadeau
    if ((strncmp($id, 'gc-', 3) === 0)) {
        $price = round((float)($item['price'] ?? 0), 2);
        if ($price <= 0) continue;
        $server_total += $price * $qty;
        $clean_cart[] = ['id'=>$id,'name'=>'Carte cadeau','qty'=>$qty,'price'=>$price,'emoji'=>'🎁'];
        continue;
    }
    if (!isset($prod_idx[$id])) continue;
    $p = $prod_idx[$id];
    if (!($p['active'] ?? true)) continue;
    $price = round((float)$p['price'], 2); // déjà TTC
    $server_total += $price * $qty;
    $clean_cart[] = ['id'=>$id,'name'=>(string)$p['name'],'qty'=>$qty,'price'=>$price,'emoji'=>(string)($p['emoji']??'')];
}
if (empty($clean_cart)) out(['error' => 'Aucun produit valide dans le panier']);

// Livraison
$mode    = in_array($body['delivery_mode']??'delivery',['delivery','pickup'],true) ? $body['delivery_mode'] : 'delivery';
$country = preg_replace('/[^A-Z]/','',(string)strtoupper($body['country']??'FR')) ?: 'FR';
$wg      = cart_total_weight($clean_cart, $prod_idx, (int)($cfg['shipping_packaging_g']??150));
$ship    = calculate_shipping($country, $server_total, $mode, $cfg, $wg);
if ($ship['blocked']) out(['error' => $ship['message']]);
$shipping     = $ship['cost'];
$server_total = round($server_total + $shipping, 2);

// Coordonnées
$fname = trim((string)($body['fname']??''));
$lname = trim((string)($body['lname']??''));
$email = filter_var($body['email']??'', FILTER_VALIDATE_EMAIL);
if (!$fname || !$lname || !$email) out(['error' => 'Prénom, nom et email requis']);

$customer = [
    'fname'   => mb_substr($fname, 0, 80),
    'lname'   => mb_substr($lname, 0, 80),
    'email'   => (string)$email,
    'phone'   => mb_substr((string)($body['phone']??''), 0, 20),
    'addr'    => $mode==='pickup' ? '' : mb_substr((string)($body['addr']??''), 0, 200),
    'zip'     => $mode==='pickup' ? '' : mb_substr((string)($body['zip']??''), 0, 10),
    'city'    => $mode==='pickup' ? '' : mb_substr((string)($body['city']??''), 0, 100),
    'country' => $country,
];

// Créer la commande
$order = [
    'id'            => new_id(),
    'amount'        => $server_total,
    'shipping'      => $shipping,
    'currency'      => 'EUR',
    'cart'          => $clean_cart,
    'customer'      => $customer,
    'delivery_mode' => $mode,
    'status'        => 'pending',
    'method'        => 'manual',
    'created_at'    => date('Y-m-d H:i:s'),
];

$orders   = db('orders');
$orders[] = $order;
db_save('orders', $orders);
admin_log('order_new', "Manuel: {$order['id']} {$server_total}€ {$email}");

// Email (silencieux si échoue)
try { mail_order($order); } catch (Throwable $t) {}

out(['success' => true, 'order_id' => $order['id']]);
