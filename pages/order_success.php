<?php
require_once __DIR__ . '/../includes/config.php';
security_headers();

$cfg = cfg_get();
$sn  = $cfg['site_name'] ?? 'Café Maison';
$pt  = 'Commande confirmée';
$cur = '';
$B   = base_path();

$method         = clean($_GET['method'] ?? 'card', 10);
$ref            = clean($_GET['ref'] ?? '', 60);
$payment_intent = clean($_GET['payment_intent'] ?? '', 60);
$status         = clean($_GET['redirect_status'] ?? '', 30);

// Confirmer via Stripe si paiement carte
$confirmed = false;
if ($payment_intent && $status === 'succeeded') {
    // Marquer la commande comme payée
    $orders = db('orders');
    foreach ($orders as &$o) {
        if (($o['intent_id'] ?? '') === $payment_intent) {
            $o['status'] = 'paid';
            $o['paid_at'] = date('Y-m-d H:i:s');
            $confirmed = true;
            $ref = $payment_intent;
        }
    } unset($o);
    if ($confirmed) {
        db_save('orders', $orders);
        security_log('stripe_paid', "PaymentIntent=$payment_intent marked paid");
    }
} elseif ($method === 'paypal' && $ref) {
    $confirmed = true;
}

include ROOT . '/includes/header.php';
?>
<div style="text-align:center;padding:calc(var(--hh) + 5rem) 2rem 5rem;max-width:560px;margin:0 auto">
  <div style="font-size:4rem;margin-bottom:1.5rem;">✅</div>
  <h1 style="font-family:var(--f1);font-size:clamp(2rem,5vw,3rem);margin-bottom:1rem;">
    Merci pour votre <em>commande&nbsp;!</em>
  </h1>
  <p style="font-size:1rem;color:var(--gy);line-height:1.9;margin-bottom:.8rem;">
    Votre commande a bien été reçue.<br>
    Elle sera préparée et expédiée sous <strong>48h</strong>.
  </p>
  <?php if ($ref): ?>
  <p style="font-size:.78rem;color:var(--gy3);margin-bottom:2rem;">
    Réf. <code style="background:var(--cr);padding:.1rem .5rem;border-radius:3px"><?= e(substr($ref,0,30)) ?></code>
  </p>
  <?php endif; ?>
  <div style="display:flex;gap:1rem;justify-content:center;flex-wrap:wrap">
    <a href="<?= $B ?>" class="btn btn-or">Retour à l'accueil</a>
    <a href="<?= $B ?>pages/shop.php" class="btn btn-out">Continuer mes achats</a>
  </div>
</div>
<script>
// Vider le panier côté client
try { localStorage.removeItem('cafe_cart_v2'); } catch(e) {}
</script>
<script>
// Vider le panier et le code promo après paiement réussi
try { localStorage.removeItem('cafe_cart_v2'); } catch(e) {}
try { localStorage.removeItem('cafe_promo_v1'); } catch(e) {}
if (window.cart) window.cart = [];
</script>
<?php include ROOT . '/includes/footer.php'; ?>
