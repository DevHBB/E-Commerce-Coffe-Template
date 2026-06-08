<?php
/**
 * admin/test_order.php — Simuler une commande sans paiement
 * Accessible uniquement aux administrateurs connectés
 */
require_once __DIR__ . '/../includes/config.php';
require_once ROOT . '/includes/mailer.php';
require_auth();

$cfg  = cfg_get();
$prds = array_filter(db('products'), fn($p)=>$p['active']??true);
$ok   = false; $order_data = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $items = $_POST['items'] ?? [];
    $cart  = [];
    foreach ($prds as $p) {
        $qty = (int)($items[$p['id']] ?? 0);
        if ($qty > 0) $cart[] = ['id'=>$p['id'],'name'=>$p['name'],'price'=>$p['price'],'qty'=>$qty,'emoji'=>$p['emoji']??'☕'];
    }
    if (!$cart) { $err = 'Sélectionnez au moins un produit.'; }
    else {
        $total = array_sum(array_map(fn($i)=>$i['price']*$i['qty'],$cart));
        $order_data = [
            'id'          => new_id(),
            'intent_id'   => 'TEST_'.strtoupper(bin2hex(random_bytes(4))),
            'amount'      => $total,
            'currency'    => $cfg['currency']??'EUR',
            'cart'        => $cart,
            'customer'    => ['fname'=>'Test','lname'=>'Admin','email'=>$cfg['email']??'admin@test.fr','addr'=>$cfg['address']??'','zip'=>'75001','city'=>'Paris','phone'=>''],
            'delivery_mode'=> 'delivery',
            'status'      => 'paid',
            'method'      => 'test',
            'created_at'  => date('Y-m-d H:i:s'),
        ];
        $orders = db('orders');
        $orders[] = $order_data;
        db_save('orders', $orders);
        security_log('test_order', 'Admin placed test order #'.$order_data['id']);
        $ok = true;
    }
}

$at = 'Commande test'; $cur_adm = 'orders';
include ROOT . '/admin/inc/layout.php';
?>
<div class="adm-top">
  <div><h2>Commande test</h2><p>Simuler une commande sans paiement réel</p></div>
  <div class="adm-acts"><a href="./orders.php" class="a-btn">← Commandes</a></div>
</div>
<div class="adm-content">
  <div style="background:rgba(200,86,30,.08);border:1px solid rgba(200,86,30,.2);border-radius:var(--r);padding:.9rem 1rem;font-size:.84rem;color:var(--gy);margin-bottom:1.5rem">
    ⚠ Cette commande sera enregistrée comme "<strong>payée</strong>" avec la méthode "test". Elle apparaîtra dans vos commandes réelles.
    Utilisez-la pour tester les emails de confirmation et le tunnel de commande.
  </div>

  <?php if ($ok && $order_data): ?>
  <div class="alert-ok">
    ✓ Commande test créée (ID : <?= e($order_data['intent_id']) ?>) · Total : <?= number_format($order_data['amount'],2,',',' ') ?> €
    <?php if (mail_order($order_data)): ?><br>📧 Email de confirmation envoyé à <?= e($order_data['customer']['email']) ?><?php endif; ?>
  </div>
  <?php elseif (isset($err)): ?>
  <div class="alert-err">✗ <?= e($err) ?></div>
  <?php endif; ?>

  <form method="POST" style="max-width:600px">
    <?= csrf_field() ?>
    <div class="editor-wrap">
      <h4 style="font-family:var(--f1);font-size:1.1rem;margin-bottom:1rem">Sélectionner les produits</h4>
      <?php foreach ($prds as $p): ?>
      <div style="display:flex;align-items:center;justify-content:space-between;padding:.7rem 0;border-bottom:1px solid var(--cr)">
        <label style="display:flex;align-items:center;gap:.7rem;cursor:pointer;flex:1">
          <span style="font-size:1.3rem"><?= e($p['emoji']??'☕') ?></span>
          <span><?= e($p['name']) ?> — <?= number_format($p['price'],2,',',' ') ?> €</span>
        </label>
        <input type="number" name="items[<?= e($p['id']) ?>]" min="0" max="10" value="0"
          style="width:60px;padding:.4rem;border:1px solid var(--gy3);border-radius:var(--r);text-align:center">
      </div>
      <?php endforeach; ?>
      <button type="submit" class="save-btn" style="margin-top:1rem">🧪 Créer la commande test</button>
    </div>
  </form>
</div>
<?php include ROOT . '/admin/inc/layout_end.php'; ?>
