<?php
require_once __DIR__ . '/../includes/config.php';
require_once ROOT . '/includes/mailer.php';
require_auth();

$orders_all = array_reverse(db('orders'));
$allowed_st = ['pending','paid','preparing','ready','shipped','delivered','cancelled'];
$status_labels = [
    'pending'   => ['label'=>'En attente',    'cls'=>'bdg-o',  'icon'=>'⏳'],
    'paid'      => ['label'=>'Payé',          'cls'=>'bdg-g',  'icon'=>'✅'],
    'preparing' => ['label'=>'En préparation','cls'=>'bdg-o',  'icon'=>'🔧'],
    'ready'     => ['label'=>'Prêt',          'cls'=>'bdg-g',  'icon'=>'📦'],
    'shipped'   => ['label'=>'Expédié',       'cls'=>'bdg-g',  'icon'=>'🚚'],
    'delivered' => ['label'=>'Livré',         'cls'=>'bdg-g',  'icon'=>'🏠'],
    'cancelled' => ['label'=>'Annulé',        'cls'=>'bdg-r',  'icon'=>'✗'],
];

// ── Export CSV ──────────────────────────────────────────────────────
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    csrf_check_get();
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="commandes_'.date('Y-m-d').'.csv"');
    $flt = clean($_GET['s'] ?? 'all', 20);
    $out = fopen('php://output','w');
    fputs($out,"\xEF\xBB\xBF");
    fputcsv($out,['ID','Date','Méthode','Mode','Prénom','Nom','Email','Téléphone','Adresse','CP','Ville','Articles','Total','Devise','Statut'],';');
    foreach ($orders_all as $o) {
        if ($flt!=='all' && ($o['status']??'')!==$flt) continue;
        $c=$o['customer']??[];
        fputcsv($out,[$o['id']??'',$o['created_at']??'',$o['method']??'',$o['delivery_mode']??'',$c['fname']??'',$c['lname']??'',$c['email']??'',$c['phone']??'',$c['addr']??'',$c['zip']??'',$c['city']??'',implode(' / ',array_map(fn($i)=>$i['name'].' x'.$i['qty'],$o['cart']??[])),number_format($o['amount']??0,2,'.',''),$o['currency']??'EUR',$o['status']??''],';');
    }
    fclose($out); exit;
}

// ── POST actions ────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $pa = clean($_POST['pa'] ?? '', 20);

    // Statut unique
    if ($pa === 'status') {
        $oid   = clean($_POST['oid'] ?? '', 30);
        $new_s = in_array($_POST['status']??'', $allowed_st,true) ? $_POST['status'] : '';
        if ($oid && $new_s) {
            $orders = db('orders');
            foreach ($orders as &$o) {
                if ($o['id']===$oid) {
                    $old_s = $o['status'] ?? '';
                    $o['status']=$new_s; $o['updated_at']=date('Y-m-d H:i:s');
                    // Envoyer email pour tout changement de statut
                    if ($new_s !== $old_s) {
                        require_once ROOT . '/includes/mailer.php';
                        if ($new_s === 'shipped' && function_exists('mail_order_shipped')) {
                            mail_order_shipped($o);
                        } elseif (function_exists('mail_order_status')) {
                            mail_order_status($o);
                        }
                    }
                }
            } unset($o);
            db_save('orders',$orders);
            admin_log('order_status',"#$oid → $new_s");
        }
        header('Location: ./orders.php?saved=1'); exit;
    }

    // Action bulk (plusieurs commandes)
    if ($pa === 'bulk') {
        $ids   = array_map(fn($id)=>clean($id,30), $_POST['ids'] ?? []);
        $new_s = in_array($_POST['bulk_status']??'', $allowed_st,true) ? $_POST['bulk_status'] : '';
        $do_del= ($_POST['bulk_action']??'')==='delete';
        if ($ids && ($new_s || $do_del)) {
            $orders = db('orders');
            if ($do_del) {
                $orders = array_values(array_filter($orders, fn($o)=>!in_array($o['id']??'',$ids)));
                admin_log('order_bulk_delete', count($ids).' commandes supprimées');
            } else {
                require_once ROOT . '/includes/mailer.php';
                foreach ($orders as &$o) {
                    if (in_array($o['id']??'',$ids)) {
                        $old_s = $o['status'] ?? '';
                        $o['status']=$new_s; $o['updated_at']=date('Y-m-d H:i:s');
                        if ($new_s !== $old_s) {
                            if ($new_s === 'shipped' && function_exists('mail_order_shipped')) {
                                mail_order_shipped($o);
                            } elseif (function_exists('mail_order_status')) {
                                mail_order_status($o);
                            }
                        }
                    }
                } unset($o);
                admin_log('order_bulk_status',"→ $new_s sur ".count($ids)." commandes");
            }
            db_save('orders',$orders);
        }
        header('Location: ./orders.php?saved=1'); exit;
    }

    // Supprimer une commande
    if ($pa === 'delete') {
        $oid = clean($_POST['oid'] ?? '', 30);
        if ($oid) {
            $orders = array_values(array_filter(db('orders'), fn($o)=>$o['id']!==$oid));
            db_save('orders',$orders);
            admin_log('order_delete',"Commande $oid supprimée");
        }
        header('Location: ./orders.php?del=1'); exit;
    }

    // Éditer les données client d'une commande
    if ($pa === 'edit_customer') {
        $oid = clean($_POST['oid'] ?? '', 30);
        if ($oid) {
            $orders = db('orders');
            foreach ($orders as &$o) {
                if ($o['id']===$oid) {
                    $o['customer'] = [
                        'fname' => clean($_POST['fname']??'',80),
                        'lname' => clean($_POST['lname']??'',80),
                        'email' => filter_var($_POST['email']??'', FILTER_VALIDATE_EMAIL) ?: ($o['customer']['email']??''),
                        'phone' => clean($_POST['phone']??'',30),
                        'addr'  => clean($_POST['addr']??'',200),
                        'zip'   => preg_replace('/[^0-9]/','',$_POST['zip']??''),
                        'city'  => clean($_POST['city']??'',100),
                    ];
                    $o['delivery_mode'] = in_array($_POST['delivery_mode']??'',['delivery','pickup'],true)
                        ? $_POST['delivery_mode'] : ($o['delivery_mode']??'delivery');
                    $o['admin_note'] = clean($_POST['admin_note']??'',500);
                    $o['updated_at'] = date('Y-m-d H:i:s');
                    admin_log('order_edit_customer',"Données client commande $oid modifiées");
                }
            } unset($o);
            db_save('orders',$orders);
        }
        header('Location: ./orders.php?saved=1&act=edit&id='.$oid); exit;
    }
}

// ── Vue nouvelle commande manuelle ──────────────────────────────────
if ($act === 'new') {
    if ($_SERVER['REQUEST_METHOD']==='POST') {
        csrf_check();
        $prds_all = db('products');
        $prd_idx  = [];
        foreach ($prds_all as $p) $prd_idx[$p['id']] = $p;
        $cart = [];
        foreach ($_POST['items']??[] as $pid => $qty) {
            $qty = (int)$qty;
            if ($qty <= 0 || !isset($prd_idx[$pid])) continue;
            $p = $prd_idx[$pid];
            $cart[] = ['id'=>$pid,'name'=>$p['name'],'price'=>$p['price'],'qty'=>$qty,'emoji'=>$p['emoji']??'☕','tva'=>$p['tva']??20];
        }
        $total = array_sum(array_map(fn($i)=>$i['price']*$i['qty'],$cart));
        $cfg_s = cfg_get();
        $counter = (int)($cfg_s['invoice_counter']??1);
        $prefix  = $cfg_s['invoice_prefix']??'CM';
        $inv_num = $prefix.'-'.date('Y').'-'.str_pad($counter,4,'0',STR_PAD_LEFT);
        $cfg_s['invoice_counter'] = $counter+1;
        cfg_save($cfg_s);
        $new_order = [
            'id'           => new_id(),
            'intent_id'    => 'MAN-'.strtoupper(bin2hex(random_bytes(4))),
            'invoice_num'  => $inv_num,
            'invoice_date' => date('Y-m-d'),
            'amount'       => $total,
            'currency'     => 'EUR',
            'cart'         => $cart,
            'customer'     => [
                'fname' => clean($_POST['fname']??'',80),
                'lname' => clean($_POST['lname']??'',80),
                'email' => filter_var($_POST['email']??'',FILTER_VALIDATE_EMAIL)?:clean($_POST['email']??'',150),
                'phone' => clean($_POST['phone']??'',30),
                'addr'  => clean($_POST['addr']??'',200),
                'zip'   => preg_replace('/[^0-9]/','',($_POST['zip']??'')),
                'city'  => clean($_POST['city']??'',100),
            ],
            'delivery_mode' => in_array($_POST['delivery_mode']??'delivery',['delivery','pickup'],true)?$_POST['delivery_mode']:'delivery',
            'status'        => clean($_POST['status']??'paid',20),
            'method'        => 'manual',
            'admin_note'    => clean($_POST['admin_note']??'',500),
            'created_at'    => date('Y-m-d H:i:s'),
        ];
        $orders_all = db('orders');
        $orders_all[] = $new_order;
        db_save('orders', $orders_all);
        admin_log('order_create_manual', "Commande manuelle créée #".$new_order['id']." facture $inv_num");
        header('Location: ./orders.php?act=edit&id='.$new_order['id'].'&saved=1'); exit;
    }
    $prds_all = db('products');
    $at = 'Nouvelle commande'; $cur_adm = 'orders';
    include ROOT . '/admin/inc/layout.php';
    $cfg_s = cfg_get();
    $prefix = $cfg_s['invoice_prefix']??'CM';
    $counter = (int)($cfg_s['invoice_counter']??1);
    $next_inv = $prefix.'-'.date('Y').'-'.str_pad($counter,4,'0',STR_PAD_LEFT);
    ?>
<div class="adm-top">
  <div><h2>Nouvelle commande manuelle</h2><p>Facture : <strong><?= e($next_inv) ?></strong> (auto-incrémenté)</p></div>
  <div class="adm-acts"><a href="./orders.php" class="a-btn">← Retour</a></div>
</div>
<div class="adm-content">
  <div style="display:grid;grid-template-columns:1fr 1fr;gap:1.5rem">
    <form method="POST">
      <?= csrf_field() ?>
      <div class="editor-wrap">
        <h4 style="font-family:var(--f1);font-size:1.1rem;margin-bottom:1rem">🧑 Client</h4>
        <div class="frm-row">
          <div class="frm-g"><label class="frm-l">Prénom</label><input class="frm-i" name="fname" maxlength="80"></div>
          <div class="frm-g"><label class="frm-l">Nom</label><input class="frm-i" name="lname" maxlength="80"></div>
        </div>
        <div class="frm-row">
          <div class="frm-g"><label class="frm-l">Email</label><input class="frm-i" type="email" name="email" maxlength="150"></div>
          <div class="frm-g"><label class="frm-l">Téléphone</label><input class="frm-i" name="phone" maxlength="30"></div>
        </div>
        <div class="frm-g"><label class="frm-l">Adresse</label><input class="frm-i" name="addr" maxlength="200"></div>
        <div class="frm-row">
          <div class="frm-g"><label class="frm-l">CP</label><input class="frm-i" name="zip" maxlength="10"></div>
          <div class="frm-g"><label class="frm-l">Ville</label><input class="frm-i" name="city" maxlength="100"></div>
        </div>
        <div class="frm-row">
          <div class="frm-g"><label class="frm-l">Mode de livraison</label>
            <select class="frm-sel" name="delivery_mode">
              <option value="delivery">🚚 Livraison</option>
              <option value="pickup">🏪 Retrait</option>
            </select></div>
          <div class="frm-g"><label class="frm-l">Statut initial</label>
            <select class="frm-sel" name="status">
              <option value="paid">✅ Payé</option>
              <option value="pending">⏳ En attente</option>
              <option value="preparing">🔧 En préparation</option>
            </select></div>
        </div>
        <div class="frm-g"><label class="frm-l">Note interne</label><textarea class="frm-i" name="admin_note" rows="2" maxlength="500"></textarea></div>
      </div>
      <div class="editor-wrap">
        <h4 style="font-family:var(--f1);font-size:1.1rem;margin-bottom:1rem">📦 Produits</h4>
        <?php foreach ($prds_all as $p): if (!($p['active']??true)) continue; ?>
        <div style="display:flex;align-items:center;justify-content:space-between;padding:.6rem 0;border-bottom:1px solid var(--cr)">
          <label style="flex:1;cursor:pointer"><?= e($p['emoji']??'☕') ?> <?= e($p['name']) ?> — <?= number_format($p['price'],2,',',' ') ?> €</label>
          <input type="number" name="items[<?= e($p['id']) ?>]" min="0" max="99" value="0" style="width:64px;padding:.35rem;border:1px solid var(--gy3);border-radius:var(--r);text-align:center">
        </div>
        <?php endforeach; ?>
        <button type="submit" class="save-btn" style="margin-top:1rem" onclick="return confirm('Créer cette commande manuelle ?')">✅ Créer la commande</button>
      </div>
    </form>
    <div>
      <div class="editor-wrap">
        <h4 style="font-family:var(--f1);font-size:1.1rem;margin-bottom:.8rem">ℹ️ Infos</h4>
        <p style="font-size:.82rem;color:var(--gy)">Cette commande sera créée avec la méthode <strong>manual</strong>. La facture <strong><?= e($next_inv) ?></strong> sera générée automatiquement. Vous pouvez l'envoyer par email depuis la vue détail.</p>
      </div>
    </div>
  </div>
</div>
    <?php
    include ROOT . '/admin/inc/layout_end.php';
    exit;
}

// ── Vue détail / édition ─────────────────────────────────────────────
$act = clean($_GET['act'] ?? 'list', 10);
$eid = clean($_GET['id']  ?? '', 30);

if ($act === 'edit' && $eid) {
    $order_ed = null;
    foreach (db('orders') as $o) { if ($o['id']===$eid) { $order_ed=$o; break; } }
    if ($order_ed) {
        $at = 'Commandes'; $cur_adm = 'orders';
        include ROOT . '/admin/inc/layout.php';
        $c  = $order_ed['customer'] ?? [];
        $st = $order_ed['status'] ?? 'pending';
        $sl = $status_labels[$st] ?? ['label'=>$st,'cls'=>'bdg-gy','icon'=>'?'];
        ?>
<div class="adm-top">
  <div><h2>Commande — <?= e(trim(($c['fname']??'').' '.($c['lname']??''))?:'Sans nom') ?></h2>
  <p><?= e(date('d/m/Y H:i', strtotime($order_ed['created_at']??'now'))) ?> · <span class="bdg <?= $sl['cls'] ?>"><?= $sl['icon'] ?> <?= $sl['label'] ?></span></p></div>
  <div class="adm-acts">
    <a href="../api/invoice.php?id=<?= e($order_ed['id']) ?>" target="_blank" class="a-btn prim">🧾 Voir la facture</a>
    <a href="../api/invoice.php?id=<?= e($order_ed['id']) ?>&action=send" class="a-btn"
       onclick="return confirm('Envoyer la facture par email ?')">📧 Envoyer facture</a>
    <a href="./orders.php" class="a-btn">← Retour</a>
  </div>
</div>
<div class="adm-content">
  <div style="display:grid;grid-template-columns:1fr 1fr;gap:1.5rem">

    <!-- Éditer les données client -->
    <div class="editor-wrap">
      <h4 style="font-family:var(--f1);font-size:1.1rem;margin-bottom:1rem">🧑 Données client</h4>
      <form method="POST">
        <?= csrf_field() ?>
        <input type="hidden" name="pa" value="edit_customer">
        <input type="hidden" name="oid" value="<?= e($order_ed['id']) ?>">
        <div class="frm-row">
          <div class="frm-g"><label class="frm-l">Prénom</label><input class="frm-i" name="fname" maxlength="80" value="<?= e($c['fname']??'') ?>"></div>
          <div class="frm-g"><label class="frm-l">Nom</label><input class="frm-i" name="lname" maxlength="80" value="<?= e($c['lname']??'') ?>"></div>
        </div>
        <div class="frm-row">
          <div class="frm-g"><label class="frm-l">Email</label><input class="frm-i" type="email" name="email" maxlength="150" value="<?= e($c['email']??'') ?>"></div>
          <div class="frm-g"><label class="frm-l">Téléphone</label><input class="frm-i" name="phone" maxlength="30" value="<?= e($c['phone']??'') ?>"></div>
        </div>
        <div class="frm-g"><label class="frm-l">Adresse</label><input class="frm-i" name="addr" maxlength="200" value="<?= e($c['addr']??'') ?>"></div>
        <div class="frm-row">
          <div class="frm-g"><label class="frm-l">Code postal</label><input class="frm-i" name="zip" maxlength="10" value="<?= e($c['zip']??'') ?>"></div>
          <div class="frm-g"><label class="frm-l">Ville</label><input class="frm-i" name="city" maxlength="100" value="<?= e($c['city']??'') ?>"></div>
        </div>
        <div class="frm-g"><label class="frm-l">Mode de livraison</label>
          <select class="frm-sel" name="delivery_mode">
            <option value="delivery" <?= ($order_ed['delivery_mode']??'delivery')==='delivery'?'selected':'' ?>>🚚 Livraison</option>
            <option value="pickup"   <?= ($order_ed['delivery_mode']??'delivery')==='pickup'  ?'selected':'' ?>>🏪 Retrait</option>
          </select></div>
        <div class="frm-g"><label class="frm-l">Note interne (admin)</label>
          <textarea class="frm-i" name="admin_note" rows="3" maxlength="500"><?= e($order_ed['admin_note']??'') ?></textarea></div>
        <button type="submit" class="save-btn">💾 Enregistrer les modifications</button>
      </form>
    </div>

    <!-- Résumé commande -->
    <div>
      <div class="editor-wrap">
        <h4 style="font-family:var(--f1);font-size:1.1rem;margin-bottom:1rem">📦 Contenu de la commande</h4>
        <table style="width:100%;border-collapse:collapse">
          <thead><tr><th style="text-align:left;padding:.5rem 0;font-size:.72rem;color:var(--gy2);border-bottom:1px solid #EAE3D9">Produit</th><th style="text-align:center;padding:.5rem;font-size:.72rem;color:var(--gy2);border-bottom:1px solid #EAE3D9">Qté</th><th style="text-align:right;padding:.5rem 0;font-size:.72rem;color:var(--gy2);border-bottom:1px solid #EAE3D9">Prix</th></tr></thead>
          <tbody>
          <?php foreach ($order_ed['cart']??[] as $it): ?>
          <tr><td style="padding:.6rem 0;font-size:.88rem"><?= e($it['emoji']??'') ?> <?= e($it['name']??'') ?></td><td style="text-align:center;font-size:.88rem">×<?= (int)($it['qty']??1) ?></td><td style="text-align:right;font-size:.88rem;font-weight:600"><?= number_format(($it['price']??0)*($it['qty']??1),2,',',' ') ?> €</td></tr>
          <?php endforeach; ?>
          <tr><td colspan="2" style="padding:.8rem 0 0;font-family:var(--f1);font-size:1.1rem"><strong>Total</strong></td><td style="text-align:right;padding:.8rem 0 0;font-family:var(--f1);font-size:1.1rem;border-top:1px solid #EAE3D9"><strong><?= number_format($order_ed['amount']??0,2,',',' ') ?> €</strong></td></tr>
          </tbody>
        </table>
        <div style="margin-top:1.2rem;font-size:.8rem;color:var(--gy2)">
          <div>Méthode : <?= e(ucfirst($order_ed['method']??'')) ?></div>
          <div>Réf : <code><?= e($order_ed['intent_id']??$order_ed['paypal_id']??$order_ed['id']??'') ?></code></div>
          <?php if (!empty($order_ed['invoice_num'])): ?>
          <div style="margin-top:.5rem;color:var(--vr2)">🧾 Facture : <code><?= e($order_ed['invoice_num']) ?></code></div>
          <?php else: ?>
          <div style="margin-top:.5rem;color:var(--or)">🧾 Facture non générée</div>
          <?php endif; ?>
        </div>
      </div>
      <!-- Changer le statut -->
      <div class="editor-wrap">
        <h4 style="font-family:var(--f1);font-size:1.1rem;margin-bottom:1rem">🔄 Statut</h4>
        <form method="POST" style="display:flex;gap:.5rem;flex-wrap:wrap">
          <?= csrf_field() ?>
          <input type="hidden" name="pa" value="status">
          <input type="hidden" name="oid" value="<?= e($order_ed['id']) ?>">
          <select name="status" class="frm-sel">
            <?php foreach ($status_labels as $sk=>$sv): ?>
            <option value="<?= $sk ?>" <?= $st===$sk?'selected':'' ?>><?= $sv['icon'].' '.$sv['label'] ?></option>
            <?php endforeach; ?>
          </select>
          <button type="submit" class="save-btn" style="flex:1">Mettre à jour</button>
        </form>
        <!-- Supprimer -->
        <form method="POST" style="margin-top:.7rem">
          <?= csrf_field() ?>
          <input type="hidden" name="pa" value="delete">
          <input type="hidden" name="oid" value="<?= e($order_ed['id']) ?>">
          <button type="submit" class="a-btn" style="color:#c0392b;border-color:#c0392b;width:100%;justify-content:center" data-confirm="Supprimer définitivement cette commande ?">🗑 Supprimer la commande</button>
        </form>
      </div>
    </div>
  </div>
</div>
        <?php include ROOT . '/admin/inc/layout_end.php'; ?>
        <?php return; // Ne pas continuer
    }
}

// ── Vue liste ────────────────────────────────────────────────────────
$flt_status = in_array($_GET['s']??'', array_merge(['all'],$allowed_st),true) ? ($_GET['s']??'all') : 'all';
$flt_mode   = in_array($_GET['m']??'', ['all','delivery','pickup'],true) ? ($_GET['m']??'all') : 'all';

$orders = array_filter($orders_all, function($o) use ($flt_status, $flt_mode) {
    if ($flt_status!=='all' && ($o['status']??')') !== $flt_status) return false;
    if ($flt_mode  !=='all' && ($o['delivery_mode']??'delivery') !== $flt_mode) return false;
    return true;
});

$stats = [
    'total'  => count($orders_all),
    'pending'=> count(array_filter($orders_all, fn($o)=>($o['status']??'')==='pending')),
    'paid'   => count(array_filter($orders_all, fn($o)=>($o['status']??'')==='paid')),
    'shipped'=> count(array_filter($orders_all, fn($o)=>($o['status']??'')==='shipped')),
    'delivered'=>count(array_filter($orders_all, fn($o)=>($o['status']??'')==='delivered')),
    'ca'     => array_sum(array_column(array_values(array_filter($orders_all, fn($o)=>in_array($o['status']??'',['paid','preparing','ready','shipped','delivered']))),'amount')),
];

$at = 'Commandes'; $cur_adm = 'orders';
include ROOT . '/admin/inc/layout.php';
?>

<div class="adm-top">
  <div><h2>Commandes</h2><p><?= count($orders) ?>/<?= count($orders_all) ?></p></div>
  <div class="adm-acts">
    <a href="?act=new" class="a-btn prim">+ Commande manuelle</a>
    <a href="?export=csv&s=<?= e($flt_status) ?>&_csrf=<?= urlencode(csrf_token()) ?>" class="a-btn">⬇ CSV</a>
  </div>
</div>

<div class="adm-content">
  <?php if (isset($_GET['saved'])): ?><div class="alert-ok" style="margin-bottom:1rem">✓ Enregistré.</div><?php endif; ?>
  <?php if (isset($_GET['del'])):   ?><div class="alert-ok" style="margin-bottom:1rem">✓ Commande supprimée.</div><?php endif; ?>

  <!-- KPIs -->
  <div class="stats" style="grid-template-columns:repeat(6,1fr);margin-bottom:1.5rem">
    <div class="st"><div class="st-lbl">Total</div><div class="st-val"><?= $stats['total'] ?></div></div>
    <div class="st"><div class="st-lbl">En attente</div><div class="st-val" style="color:var(--or)"><?= $stats['pending'] ?></div></div>
    <div class="st"><div class="st-lbl">Payé</div><div class="st-val"><?= $stats['paid'] ?></div></div>
    <div class="st"><div class="st-lbl">Expédié</div><div class="st-val"><?= $stats['shipped'] ?></div></div>
    <div class="st"><div class="st-lbl">Livré</div><div class="st-val"><?= $stats['delivered'] ?></div></div>
    <div class="st"><div class="st-lbl">CA</div><div class="st-val" style="font-size:1.3rem"><?= number_format($stats['ca'],0,',',' ') ?> €</div></div>
  </div>

  <!-- Filtres -->
  <div style="display:flex;gap:.4rem;flex-wrap:wrap;margin-bottom:1rem;align-items:center">
    <span style="font-size:.7rem;text-transform:uppercase;letter-spacing:.1em;color:var(--gy2)">Statut :</span>
    <?php foreach(['all'=>'Tout','pending'=>'⏳ Attente','paid'=>'✅ Payé','preparing'=>'🔧 Prépa.','ready'=>'📦 Prêt','shipped'=>'🚚 Expédié','delivered'=>'🏠 Livré','cancelled'=>'✗ Annulé'] as $k=>$l): ?>
    <a href="?s=<?= $k ?>&m=<?= e($flt_mode) ?>" class="flt <?= $flt_status===$k?'on':'' ?>" style="padding:.3rem .8rem;font-size:.7rem"><?= $l ?></a>
    <?php endforeach; ?>
    <span style="font-size:.7rem;text-transform:uppercase;letter-spacing:.1em;color:var(--gy2);margin-left:.5rem">Mode :</span>
    <?php foreach(['all'=>'Tous','delivery'=>'🚚','pickup'=>'🏪'] as $k=>$l): ?>
    <a href="?s=<?= e($flt_status) ?>&m=<?= $k ?>" class="flt <?= $flt_mode===$k?'on':'' ?>" style="padding:.3rem .8rem;font-size:.7rem"><?= $l ?></a>
    <?php endforeach; ?>
  </div>

  <?php if (!$orders): ?>
  <div class="empty-st"><div class="empty-ico">📦</div><h4>Aucune commande</h4></div>
  <?php else: ?>

  <!-- Actions bulk -->
  <form method="POST" id="bulkForm">
    <?= csrf_field() ?>
    <input type="hidden" name="pa" value="bulk">
    <div style="display:flex;gap:.6rem;align-items:center;padding:.7rem 1rem;background:#fff;border:1px solid #EAE3D9;border-radius:var(--r);margin-bottom:.8rem;flex-wrap:wrap">
      <label style="font-size:.78rem;color:var(--gy2);display:flex;align-items:center;gap:.4rem">
        <input type="checkbox" id="checkAll" onchange="document.querySelectorAll('.row-chk').forEach(c=>c.checked=this.checked)">
        Tout sélectionner
      </label>
      <span style="font-size:.75rem;color:var(--gy2)" id="selCount">0 sélectionné(s)</span>
      <div style="margin-left:auto;display:flex;gap:.5rem;align-items:center;flex-wrap:wrap">
        <select name="bulk_status" class="frm-sel" style="font-size:.73rem;padding:.3rem .6rem">
          <option value="">— Changer le statut —</option>
          <?php foreach ($status_labels as $sk=>$sv): ?>
          <option value="<?= $sk ?>"><?= $sv['icon'].' '.$sv['label'] ?></option>
          <?php endforeach; ?>
        </select>
        <input type="hidden" name="bulk_action" id="bulk_action_input" value="status">
        <button type="submit" class="a-btn prim" onclick="return confirmBulk('status')">Appliquer</button>
        <button type="button" class="a-btn" style="color:#c0392b;border-color:#c0392b"
          onclick="if(confirmBulk('delete')){document.getElementById('bulk_action_input').value='delete';document.getElementById('bulkForm').submit();}">🗑 Supprimer</button>
      </div>
    </div>

    <div class="tbl-wrap">
      <table>
        <thead>
          <tr><th style="width:30px"></th><th>Date</th><th>Client</th><th>Mode</th><th>Méthode</th><th>Articles</th><th>Total</th><th>Statut</th><th>—</th></tr>
        </thead>
        <tbody>
        <?php foreach ($orders as $o):
          $c  = $o['customer'] ?? [];
          $st = $o['status'] ?? 'pending';
          $sl = $status_labels[$st] ?? ['label'=>$st,'cls'=>'bdg-gy','icon'=>'?'];
          $dm = $o['delivery_mode'] ?? 'delivery';
        ?>
        <tr>
          <td><input type="checkbox" name="ids[]" value="<?= e($o['id']) ?>" class="row-chk" onchange="updateSelCount()"></td>
          <td>
            <div style="font-size:.82rem;font-weight:500"><?= e(date('d/m/Y', strtotime($o['created_at']??'now'))) ?></div>
            <div style="font-size:.69rem;color:var(--gy2)"><?= e(date('H:i', strtotime($o['created_at']??'now'))) ?></div>
            <?php if($o['method']==='test'): ?><span class="bdg bdg-gy" style="font-size:.58rem">TEST</span><?php endif; ?>
          </td>
          <td>
            <div style="font-weight:500;font-size:.88rem"><?= e(trim(($c['fname']??'').' '.($c['lname']??'')))?:'<em style="color:var(--gy2)">—</em>' ?></div>
            <div style="font-size:.75rem;color:var(--gy2)"><?= e($c['email']??'') ?></div>
          </td>
          <td><span class="bdg <?= $dm==='pickup'?'bdg-g':'bdg-gy' ?>"><?= $dm==='pickup'?'🏪':'🚚' ?></span></td>
          <td style="font-size:.8rem"><?= ucfirst($o['method']??'') ?></td>
          <td style="font-size:.8rem">
            <?php foreach(array_slice($o['cart']??[],0,2) as $it): ?>
            <div><?= e($it['emoji']??'') ?> <?= e(mb_substr($it['name']??'',0,16,'UTF-8')) ?> ×<?= (int)$it['qty'] ?></div>
            <?php endforeach; ?>
            <?php if(count($o['cart']??[])>2): ?><div style="color:var(--gy2);font-size:.7rem">+<?= count($o['cart'])-2 ?> autre(s)</div><?php endif; ?>
          </td>
          <td style="font-weight:700;font-family:var(--f1);font-size:1.05rem;white-space:nowrap">
            <?= number_format($o['amount']??0,2,',',' ') ?> €
          </td>
          <td><span class="bdg <?= $sl['cls'] ?>"><?= $sl['icon'] ?> <?= $sl['label'] ?></span></td>
          <td>
            <a href="?act=edit&id=<?= e($o['id']) ?>" class="act-btn">✏ Éditer</a>
          </td>
        </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </form>
  <?php endif; ?>
</div>

<script>
function updateSelCount(){
  const n=document.querySelectorAll('.row-chk:checked').length;
  document.getElementById('selCount').textContent=n+' sélectionné(s)';
}
function confirmBulk(action){
  const n=document.querySelectorAll('.row-chk:checked').length;
  if(n===0){alert('Sélectionnez au moins une commande.');return false;}
  if(action==='delete') return confirm(`Supprimer ${n} commande(s) définitivement ?`);
  const st=document.querySelector('[name=bulk_status]').value;
  if(!st){alert('Choisissez un statut.');return false;}
  return confirm(`Mettre ${n} commande(s) en "${st}" ?`);
}
// Confirmer les suppression individuelles
document.querySelectorAll('[data-confirm]').forEach(btn=>{
  btn.addEventListener('click',function(e){
    if(!confirm(this.dataset.confirm))e.preventDefault();
  });
});
</script>

<?php include ROOT . '/admin/inc/layout_end.php'; ?>
