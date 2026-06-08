<?php
require_once __DIR__ . '/../includes/config.php';
require_auth();

$orders   = db('orders');
$products = db('products');
$bookings = db('bookings');
$promos   = db('promos');
$reviews  = db('reviews');
$nl       = db('newsletter');

// ── Calculs analytics ─────────────────────────────────────
$now = time();
$paid_orders = array_filter($orders, fn($o) => in_array($o['status']??'', ['paid','preparing','ready','shipped','delivered']));

// CA par semaine (12 dernières semaines)
$weekly = [];
for ($w = 11; $w >= 0; $w--) {
    $start = strtotime("monday -$w weeks");
    $end   = $start + 7*86400;
    $label = date('d/m', $start);
    $ca    = 0;
    foreach ($paid_orders as $o) {
        $t = strtotime($o['created_at']??'');
        if ($t >= $start && $t < $end) $ca += $o['amount']??0;
    }
    $weekly[] = ['label'=>$label, 'ca'=>round($ca,2)];
}

// CA par mois (6 derniers mois)
$monthly = [];
for ($m = 5; $m >= 0; $m--) {
    $month = date('Y-m', strtotime("-$m months"));
    $label = date('M Y', strtotime("-$m months"));
    $ca    = 0;
    foreach ($paid_orders as $o) {
        if ((strpos($o['created_at']??'', $month) === 0)) $ca += $o['amount']??0;
    }
    $monthly[] = ['label'=>$label,'ca'=>round($ca,2)];
}

// Produits les plus vendus
$prod_sales = [];
foreach ($paid_orders as $o) {
    foreach ($o['cart']??[] as $it) {
        $id = $it['id']??'';
        $prod_sales[$id] = ($prod_sales[$id]??0) + (int)($it['qty']??1);
    }
}
arsort($prod_sales);
$top_products = array_slice($prod_sales, 0, 5, true);
$prod_idx = [];
foreach ($products as $p) $prod_idx[$p['id']] = $p;

// Panier moyen
$amounts = array_column(array_values($paid_orders), 'amount');
$avg_cart = count($amounts) ? round(array_sum($amounts)/count($amounts),2) : 0;

// CA total, mois en cours, mois précédent
$this_month  = date('Y-m');
$last_month  = date('Y-m', strtotime('-1 month'));
$ca_total    = array_sum(array_column(array_values($paid_orders),'amount'));
$ca_month    = array_sum(array_column(array_values(array_filter($paid_orders, fn($o)=>(strpos($o['created_at']??'', $this_month) === 0))),'amount'));
$ca_lastmonth= array_sum(array_column(array_values(array_filter($paid_orders, fn($o)=>(strpos($o['created_at']??'', $last_month) === 0))),'amount'));
$evol = $ca_lastmonth>0 ? round(($ca_month-$ca_lastmonth)/$ca_lastmonth*100,1) : null;

// Codes promo les plus utilisés
usort($promos, fn($a,$b)=>($b['usage_ct']??0)-($a['usage_ct']??0));
$top_promos = array_slice($promos, 0, 5);

// Note moyenne avis
$appr = array_filter($reviews, fn($r)=>$r['approved']??false);
$avg_rating = count($appr) ? round(array_sum(array_column(array_values($appr),'rating'))/count($appr),1) : 0;

$at = 'Analytics'; $cur_adm = 'analytics';
include ROOT . '/admin/inc/layout.php';
?>
<div class="adm-top">
  <div><h2>Analytics</h2><p>Performance du site en temps réel</p></div>
  <div class="adm-acts"><a href="../" target="_blank" class="a-btn">Voir le site</a></div>
</div>
<div class="adm-content">

  <!-- KPIs principaux -->
  <div style="display:grid;grid-template-columns:repeat(5,1fr);gap:1rem;margin-bottom:2rem">
    <?php foreach ([
      ['CA Total','€ '.number_format($ca_total,0,',',' '), ''],
      ['CA ce mois','€ '.number_format($ca_month,0,',',' '), $evol!==null?($evol>=0?'+'.$evol.'% vs M-1':$evol.'% vs M-1'):''],
      ['Panier moyen','€ '.number_format($avg_cart,2,',',' '),''],
      ['Commandes',count($paid_orders),'payées'],
      ['Abonnés NL',count($nl),'actifs: '.count(array_filter($nl,fn($s)=>$s['active']??true))],
    ] as [$l,$v,$s]): ?>
    <div class="st"><div class="st-lbl"><?= $l ?></div><div class="st-val"><?= $v ?></div><?php if($s): ?><div class="st-sub <?= (strpos($s, '-') === 0)?'neg':'' ?>"><?= $s ?></div><?php endif; ?></div>
    <?php endforeach; ?>
  </div>

  <div style="display:grid;grid-template-columns:2fr 1fr;gap:1.5rem;margin-bottom:1.5rem">
    <!-- Graphique CA mensuel -->
    <div class="pnl">
      <div class="pnl-hd"><span class="pnl-t">Chiffre d'affaires — 6 derniers mois</span></div>
      <div class="pnl-body" style="padding:1.2rem">
        <?php $max_ca = max(1, max(array_column($monthly,'ca'))); ?>
        <div style="display:flex;align-items:flex-end;gap:.5rem;height:140px">
          <?php foreach ($monthly as $mo): ?>
          <div style="flex:1;display:flex;flex-direction:column;align-items:center;gap:.3rem">
            <span style="font-size:.65rem;color:var(--gy2)"><?= $mo['ca']>0?'€ '.number_format($mo['ca'],0,',',' '):'' ?></span>
            <div style="width:100%;background:linear-gradient(to top,var(--or),var(--or2));border-radius:4px 4px 0 0;height:<?= round($mo['ca']/$max_ca*110) ?>px;min-height:2px;transition:height .4s ease"></div>
            <span style="font-size:.65rem;color:var(--gy2);white-space:nowrap"><?= $mo['label'] ?></span>
          </div>
          <?php endforeach; ?>
        </div>
      </div>
    </div>

    <!-- Top produits -->
    <div class="pnl">
      <div class="pnl-hd"><span class="pnl-t">Top produits vendus</span></div>
      <div class="pnl-body">
        <?php if (!$top_products): ?>
        <p style="font-size:.83rem;color:var(--gy2);padding:.5rem 0">Aucune vente</p>
        <?php else:
        $max_sales = max($top_products);
        foreach ($top_products as $pid => $qty):
          $p = $prod_idx[$pid] ?? ['name'=>$pid,'emoji'=>'☕'];
          $pct = round($qty/$max_sales*100);
        ?>
        <div style="margin-bottom:.8rem">
          <div style="display:flex;justify-content:space-between;font-size:.82rem;margin-bottom:.25rem">
            <span><?= e($p['emoji']??'☕') ?> <?= e(mb_substr($p['name']??'',0,22,'UTF-8')) ?></span>
            <span style="font-weight:600;color:var(--or)"><?= $qty ?> vte<?= $qty>1?'s':'' ?></span>
          </div>
          <div style="height:5px;background:#EAE3D9;border-radius:3px">
            <div style="height:5px;border-radius:3px;background:var(--or);width:<?= $pct ?>%"></div>
          </div>
        </div>
        <?php endforeach; endif; ?>
      </div>
    </div>
  </div>

  <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:1.5rem;margin-bottom:1.5rem">
    <!-- CA hebdomadaire -->
    <div class="pnl">
      <div class="pnl-hd"><span class="pnl-t">CA hebdomadaire (12 sem.)</span></div>
      <div class="pnl-body" style="padding:1rem">
        <?php $mw = max(1, max(array_column($weekly,'ca'))); ?>
        <div style="display:flex;align-items:flex-end;gap:2px;height:80px">
          <?php foreach ($weekly as $wk): ?>
          <div style="flex:1;background:linear-gradient(to top,var(--vr),var(--vr3));border-radius:2px 2px 0 0;height:<?= round($wk['ca']/$mw*70) ?>px;min-height:1px" title="<?= $wk['label'] ?> : <?= $wk['ca'] ?> €"></div>
          <?php endforeach; ?>
        </div>
        <div style="display:flex;justify-content:space-between;font-size:.62rem;color:var(--gy2);margin-top:.3rem">
          <span><?= $weekly[0]['label']??'' ?></span><span><?= end($weekly)['label']??'' ?></span>
        </div>
      </div>
    </div>

    <!-- Codes promo -->
    <div class="pnl">
      <div class="pnl-hd"><span class="pnl-t">Top codes promo</span></div>
      <div class="pnl-body">
        <?php if (!$top_promos || !$top_promos[0]['usage_ct']): ?>
        <p style="font-size:.83rem;color:var(--gy2)">Aucun code utilisé</p>
        <?php else: foreach ($top_promos as $pr): if (!($pr['usage_ct']??0)) continue; ?>
        <div style="display:flex;justify-content:space-between;padding:.5rem 0;border-bottom:1px solid #FAF0E8;font-size:.84rem">
          <span style="font-family:monospace;font-weight:700;color:var(--or)"><?= e($pr['code']) ?></span>
          <span><?= $pr['usage_ct'] ?> utilisation<?= $pr['usage_ct']>1?'s':'' ?></span>
        </div>
        <?php endforeach; endif; ?>
      </div>
    </div>

    <!-- Avis & Réservations -->
    <div class="pnl">
      <div class="pnl-hd"><span class="pnl-t">Satisfaction & Ateliers</span></div>
      <div class="pnl-body">
        <div style="text-align:center;padding:1rem 0;border-bottom:1px solid #FAF0E8">
          <div style="font-family:var(--f1);font-size:2.5rem;color:var(--or)"><?= $avg_rating ?: '—' ?></div>
          <div style="color:var(--or);font-size:1.1rem"><?= str_repeat('★',round($avg_rating)) ?></div>
          <div style="font-size:.75rem;color:var(--gy2)"><?= count($appr) ?> avis publiés</div>
        </div>
        <div style="padding:.8rem 0;font-size:.84rem">
          <div style="display:flex;justify-content:space-between;margin-bottom:.4rem">
            <span>Réservations totales</span><strong><?= count($bookings) ?></strong>
          </div>
          <div style="display:flex;justify-content:space-between">
            <span>Confirmées</span>
            <strong><?= count(array_filter($bookings,fn($b)=>($b['status']??'')==='confirmed')) ?></strong>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>
<?php include ROOT . '/admin/inc/layout_end.php'; ?>
