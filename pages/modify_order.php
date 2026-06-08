<?php
require_once __DIR__ . '/../includes/config.php';
security_headers();
$cfg = cfg_get();
$B   = base_path();

$token = clean($_GET['token'] ?? '', 100);
$err   = '';
$ok    = false;
$order = null;
$expired = false;

// Vérifier le token
if (!$token) { $err = 'Lien invalide.'; }
else {
    $tokens = json_decode(@file_get_contents(DATA_DIR.'/.modify_tokens.json') ?: '{}', true) ?: [];
    if (!isset($tokens[$token])) { $err = 'Lien invalide ou déjà utilisé.'; }
    elseif ($tokens[$token]['expires'] < time()) {
        $expired = true;
        $err = 'Ce lien a expiré (valable 15 minutes seulement).';
        // Marquer la commande comme payée si encore en attente
        $oid = $tokens[$token]['order_id'] ?? '';
        if ($oid) {
            $orders = db('orders');
            foreach ($orders as &$o) {
                if ($o['id']===$oid && ($o['status']??'')==='pending') {
                    $o['status']='paid'; $o['updated_at']=date('Y-m-d H:i:s');
                }
            } unset($o);
            db_save('orders', $orders);
        }
    } elseif ($tokens[$token]['used']) {
        $err = 'Ce lien a déjà été utilisé.';
    } else {
        // Token valide
        $oid = $tokens[$token]['order_id'] ?? '';
        foreach (db('orders') as $o) { if ($o['id']===$oid) { $order=$o; break; } }
        if (!$order) $err = 'Commande introuvable.';
    }
}

// Traitement formulaire
if ($order && $_SERVER['REQUEST_METHOD']==='POST') {
    $ip = $_SERVER['REMOTE_ADDR'] ?? '';
    if (!rate_ok('modify_'.$ip, 5, 300)) { $err = 'Trop de tentatives.'; }
    else {
        $action = clean($_POST['action'] ?? '', 20);
        if ($action === 'cancel') {
            $orders = db('orders');
            foreach ($orders as &$o) {
                if ($o['id']===$order['id']) { $o['status']='cancelled'; $o['updated_at']=date('Y-m-d H:i:s'); $o['cancel_reason']=clean($_POST['reason']??'',300); }
            } unset($o);
            db_save('orders', $orders);
            // Invalider le token
            $tokens[$token]['used'] = true;
            file_put_contents(DATA_DIR.'/.modify_tokens.json', json_encode($tokens), LOCK_EX);
            $ok = true; $order['status'] = 'cancelled';
        } elseif ($action === 'update_address') {
            $orders = db('orders');
            foreach ($orders as &$o) {
                if ($o['id']===$order['id']) {
                    $o['customer']['fname'] = clean($_POST['fname']??'',80);
                    $o['customer']['lname'] = clean($_POST['lname']??'',80);
                    $o['customer']['email'] = filter_var($_POST['email']??'',FILTER_VALIDATE_EMAIL) ?: $o['customer']['email'];
                    $o['customer']['phone'] = clean($_POST['phone']??'',30);
                    $o['customer']['addr']  = clean($_POST['addr']??'',200);
                    $o['customer']['zip']   = preg_replace('/[^0-9]/','', $_POST['zip']??'');
                    $o['customer']['city']  = clean($_POST['city']??'',100);
                    $o['updated_at'] = date('Y-m-d H:i:s');
                    $order = $o;
                }
            } unset($o);
            db_save('orders', $orders);
            // Invalider le token
            $tokens[$token]['used'] = true;
            file_put_contents(DATA_DIR.'/.modify_tokens.json', json_encode($tokens), LOCK_EX);
            $ok = true;
        }
    }
}

$pt  = 'Modifier ma commande';
$cur = '';
include ROOT . '/includes/header.php';
?>
<section class="page-hero" style="min-height:40vh">
  <div class="wrap" style="position:relative;z-index:1">
    <p class="lbl">Commande</p>
    <h1>Modifier ma <em>commande</em></h1>
  </div>
</section>
<section class="sec">
  <div class="wrap" style="max-width:680px">

    <?php if ($ok): ?>
    <div style="text-align:center;padding:3rem 2rem;background:rgba(42,76,30,.05);border:1px solid var(--vr3);border-radius:14px">
      <div style="font-size:3rem;margin-bottom:1rem"><?= $order['status']==='cancelled'?'❌':'✅' ?></div>
      <h2 style="font-family:var(--f1);"><?= $order['status']==='cancelled' ? 'Commande annulée' : 'Modifications enregistrées !' ?></h2>
      <p style="color:var(--gy);margin-bottom:2rem"><?= $order['status']==='cancelled'?'Votre commande a bien été annulée. Vous serez remboursé(e) selon votre mode de paiement.':'Vos nouvelles informations ont été prises en compte.' ?></p>
      <a href="<?= $B ?>" class="btn btn-or">Retour à l'accueil</a>
    </div>

    <?php elseif ($err): ?>
    <div style="text-align:center;padding:3rem 2rem;background:rgba(200,86,30,.05);border:1px solid var(--or);border-radius:14px">
      <div style="font-size:3rem;margin-bottom:1rem"><?= $expired?'⏰':'⚠️' ?></div>
      <h2 style="font-family:var(--f1);"><?= $expired?'Lien expiré':'Lien invalide' ?></h2>
      <p style="color:var(--gy);margin-bottom:2rem"><?= e($err) ?></p>
      <?php if ($expired): ?>
      <p style="font-size:.84rem;color:var(--gy2)">Votre commande a été confirmée. Pour toute modification, contactez-nous directement à <a href="mailto:<?= e($cfg['email']??'') ?>" style="color:var(--or)"><?= e($cfg['email']??'') ?></a></p>
      <?php endif; ?>
      <a href="<?= $B ?>" class="btn btn-out" style="margin-top:1.5rem">Retour à l'accueil</a>
    </div>

    <?php else: ?>
    <!-- Récap commande -->
    <div style="background:var(--cr);border-radius:14px;padding:1.5rem;margin-bottom:2rem">
      <h3 style="font-family:var(--f1);margin-bottom:.8rem">Votre commande</h3>
      <?php foreach ($order['cart']??[] as $it): ?>
      <div style="display:flex;justify-content:space-between;padding:.5rem 0;border-bottom:1px solid var(--gy3);font-size:.88rem">
        <span><?= e($it['emoji']??'') ?> <?= e($it['name']??'') ?> ×<?= (int)$it['qty'] ?></span>
        <span style="font-weight:600"><?= number_format(($it['price']??0)*($it['qty']??1),2,',',' ') ?> €</span>
      </div>
      <?php endforeach; ?>
      <div style="display:flex;justify-content:space-between;padding:.8rem 0 0;font-family:var(--f1);font-size:1.1rem">
        <span>Total</span><span><?= number_format($order['amount']??0,2,',',' ') ?> €</span>
      </div>
    </div>

    <?php $c = $order['customer']??[]; $time_left = ($tokens[$token]['expires'] - time()); ?>
    <div style="background:rgba(200,86,30,.08);border:1px solid rgba(200,86,30,.2);border-radius:var(--r);padding:.9rem 1rem;margin-bottom:1.5rem;font-size:.85rem;text-align:center">
      ⏰ Ce lien expire dans <strong id="countdown"><?= gmdate('i:s',$time_left) ?></strong>
    </div>

    <!-- Modifier l'adresse -->
    <div style="background:#fff;border:1px solid var(--cr);border-radius:14px;padding:1.8rem;margin-bottom:1.5rem">
      <h3 style="font-family:var(--f1);font-size:1.3rem;margin-bottom:1.2rem">✏ Modifier mes informations</h3>
      <form method="POST">
        <input type="hidden" name="action" value="update_address">
        <div class="form-2">
          <div class="form-g"><label class="form-l">Prénom</label><input class="form-i" name="fname" maxlength="80" value="<?= e($c['fname']??'') ?>"></div>
          <div class="form-g"><label class="form-l">Nom</label><input class="form-i" name="lname" maxlength="80" value="<?= e($c['lname']??'') ?>"></div>
        </div>
        <div class="form-2">
          <div class="form-g"><label class="form-l">Email</label><input class="form-i" type="email" name="email" maxlength="150" value="<?= e($c['email']??'') ?>"></div>
          <div class="form-g"><label class="form-l">Téléphone</label><input class="form-i" name="phone" maxlength="30" value="<?= e($c['phone']??'') ?>"></div>
        </div>
        <?php if (($order['delivery_mode']??'delivery')!=='pickup'): ?>
        <div class="form-g"><label class="form-l">Adresse</label><input class="form-i" name="addr" maxlength="200" value="<?= e($c['addr']??'') ?>"></div>
        <div class="form-2">
          <div class="form-g"><label class="form-l">Code postal</label><input class="form-i" name="zip" maxlength="10" value="<?= e($c['zip']??'') ?>"></div>
          <div class="form-g"><label class="form-l">Ville</label><input class="form-i" name="city" maxlength="100" value="<?= e($c['city']??'') ?>"></div>
        </div>
        <?php endif; ?>
        <button type="submit" class="btn btn-or" style="width:100%;justify-content:center;margin-top:.5rem">Enregistrer les modifications</button>
      </form>
    </div>

    <!-- Annuler -->
    <div style="background:#fff;border:1px solid rgba(192,57,43,.2);border-radius:14px;padding:1.8rem">
      <h3 style="font-family:var(--f1);font-size:1.3rem;margin-bottom:.5rem;color:#c0392b">🗑 Annuler ma commande</h3>
      <p style="font-size:.84rem;color:var(--gy);margin-bottom:1rem">L'annulation est définitive. Vous serez remboursé(e) selon votre mode de paiement initial.</p>
      <form method="POST">
        <input type="hidden" name="action" value="cancel">
        <div class="form-g"><label class="form-l">Raison (optionnel)</label>
          <input class="form-i" name="reason" maxlength="300" placeholder="Erreur de commande, changement d'avis…"></div>
        <button type="submit" class="btn" style="background:#c0392b;color:#fff;width:100%;justify-content:center;margin-top:.5rem"
          onclick="return confirm('Confirmer l\'annulation de votre commande ?')">Annuler ma commande</button>
      </form>
    </div>
    <?php endif; ?>
  </div>
</section>

<script>
<?php if ($order && !$ok): ?>
let t = <?= max(0,$tokens[$token]['expires']-time()) ?>;
const cd = document.getElementById('countdown');
const iv = setInterval(()=>{
  t--;
  if(t<=0){clearInterval(iv);cd.textContent='00:00';location.reload();}
  else cd.textContent=String(Math.floor(t/60)).padStart(2,'0')+':'+String(t%60).padStart(2,'0');
},1000);
<?php endif; ?>
</script>
<?php include ROOT . '/includes/footer.php'; ?>
