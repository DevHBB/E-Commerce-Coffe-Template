<?php
/**
 * api/stripe_webhook.php — Webhook Stripe sécurisé
 * Confirme les paiements et met à jour les commandes
 * URL à enregistrer dans Stripe Dashboard → Webhooks
 * Événements: payment_intent.succeeded, payment_intent.payment_failed
 */
require_once __DIR__ . '/../includes/config.php';
require_once ROOT . '/includes/mailer.php';

// Désactiver le buffering pour les webhooks
if (ob_get_level()) ob_end_clean();

$cfg     = cfg_get();
$payload = file_get_contents('php://input');
$sig     = $_SERVER['HTTP_STRIPE_SIGNATURE'] ?? '';
$secret  = $cfg['stripe_webhook_secret'] ?? '';

// Vérifier la signature Stripe (OBLIGATOIRE pour la sécurité)
if ($secret) {
    $parts     = [];
    foreach (explode(',', $sig) as $part) {
        [$k, $v] = explode('=', $part, 2) + ['', ''];
        $parts[$k][] = $v;
    }
    $timestamp   = (int)($parts['t'][0] ?? 0);
    $sig_hash    = $parts['v1'][0] ?? '';
    $expected    = hash_hmac('sha256', $timestamp . '.' . $payload, $secret);

    if (!hash_equals($expected, $sig_hash)) {
        http_response_code(400);
        error_log('[CAFE WEBHOOK] Signature Stripe invalide');
        exit('Signature invalide');
    }
    // Tolérance 5 minutes pour les décalages horaires
    if (abs(time() - $timestamp) > 300) {
        http_response_code(400);
        exit('Webhook expiré');
    }
}

$event = json_decode($payload, true);
if (!$event) {
    http_response_code(400); exit('JSON invalide');
}

$type   = $event['type'] ?? '';
$object = $event['data']['object'] ?? [];

error_log("[CAFE WEBHOOK] Événement: $type id=" . ($object['id'] ?? '?'));

switch ($type) {

    case 'payment_intent.succeeded':
        $intent_id = $object['id'] ?? '';
        $amount    = ($object['amount'] ?? 0) / 100; // centimes → euros

        $orders = db('orders');
        $found  = false;
        foreach ($orders as &$o) {
            if (($o['intent_id'] ?? '') === $intent_id) {
                $old_status  = $o['status'];
                $o['status'] = 'paid';
                $o['paid_at']= date('Y-m-d H:i:s');
                $o['method'] = 'stripe';
                $found = true;

                // Mise à jour stock
                $products = db('products');
                foreach ($o['cart'] ?? [] as $item) {
                    foreach ($products as &$prod) {
                        if ($prod['id'] === ($item['id'] ?? '')) {
                            $prod['stock'] = max(0, ($prod['stock'] ?? 0) - ($item['qty'] ?? 1));
                        }
                    } unset($prod);
                }
                db_save('products', $products);

                // Email confirmation commande
                if ($old_status !== 'paid') {
                    mail_order($o);
                }
                // Activer les cartes cadeaux liées à cet intent
                require_once ROOT . '/includes/mailer.php';
                $gcs_all = db('giftcards');
                $gc_updated = false;
                foreach ($gcs_all as &$gc) {
                    if (($gc['intent_id'] ?? '') === $intent_id && $gc['status'] === 'pending') {
                        $gc['status'] = 'active';
                        $gc['paid_at'] = date('Y-m-d H:i:s');
                        $gc_updated = true;
                        // Email avec le code cadeau
                        if (!empty($gc['email'])) {
                            $code = $gc['code'];
                            $amt  = number_format((float)$gc['amount'],2,',',' ');
                            $body = "<p>Bonjour {$gc['from']},</p>
<p>Votre carte cadeau <strong>Café Maison</strong> est prête !</p>
<div style='background:#1a1a1a;color:#fff;border-radius:10px;padding:1rem 1.5rem;text-align:center;margin:1.5rem 0'>
  <div style='font-size:.8rem;color:rgba(255,255,255,.5);margin-bottom:.4rem'>CODE CADEAU</div>
  <div style='font-family:monospace;font-size:1.6rem;letter-spacing:.2em;color:#C8561E'>$code</div>
  <div style='font-size:.85rem;color:rgba(255,255,255,.6);margin-top:.4rem'>Valeur : $amt €</div>
</div>
<p>Pour : <strong>{$gc['to']}</strong></p>" .
(empty($gc['message']) ? '' : "<p style='font-style:italic;color:#666'>« {$gc['message']} »</p>") . "
<p style='font-size:.8rem;color:#999'>Ce code est valable 1 an sur l'ensemble de notre boutique et de nos ateliers.</p>";
                            $html = mail_wrap("🎁 Votre carte cadeau Café Maison", $body);
                            cm_mail($gc['email'], $gc['from'], "🎁 Carte cadeau Café Maison — $code", $html);
                        }
                    }
                } unset($gc);
                if ($gc_updated) db_save('giftcards', $gcs_all);
                error_log("[CAFE WEBHOOK] Commande payée: $intent_id total=$amount");
                break;
            }
        } unset($o);

        if ($found) {
            db_save('orders', $orders);
        } else {
            // Commande non trouvée (peut arriver si création a échoué)
            error_log("[CAFE WEBHOOK] Commande introuvable pour intent: $intent_id");
        }
        break;

    case 'payment_intent.payment_failed':
        $intent_id = $object['id'] ?? '';
        $reason    = $object['last_payment_error']['message'] ?? 'Inconnu';

        $orders = db('orders');
        foreach ($orders as &$o) {
            if (($o['intent_id'] ?? '') === $intent_id) {
                $o['status']        = 'failed';
                $o['failure_reason']= $reason;
                break;
            }
        } unset($o);
        db_save('orders', $orders);
        error_log("[CAFE WEBHOOK] Paiement échoué: $intent_id raison=$reason");
        break;

    case 'charge.refunded':
        $intent_id = $object['payment_intent'] ?? '';
        if ($intent_id) {
            $orders = db('orders');
            foreach ($orders as &$o) {
                if (($o['intent_id'] ?? '') === $intent_id) {
                    $o['status'] = 'refunded';
                    break;
                }
            } unset($o);
            db_save('orders', $orders);
        }
        break;
}

http_response_code(200);
echo json_encode(['received' => true]);
