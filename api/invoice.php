<?php
/**
 * Génération de facture PDF en pur PHP (sans dépendance)
 * Génère un HTML converti en PDF via la fonction native wkhtmltopdf si dispo,
 * sinon retourne un HTML bien formaté imprimable.
 *
 * Usage : /api/invoice.php?id=ORDER_ID[&action=send|download|view]
 *   - view : afficher dans le navigateur
 *   - download : télécharger
 *   - send : envoyer par email (admin seulement)
 */
define('ROOT', dirname(__DIR__));
require_once ROOT . '/includes/config.php';
require_once ROOT . '/includes/mailer.php';

// Auth : admin ou token valide
$is_admin = is_logged();
$oid = clean($_GET['id'] ?? '', 40);
$action = clean($_GET['action'] ?? 'view', 10);

if (!$oid) { http_response_code(400); die('ID manquant'); }

// Trouver la commande
$orders = db('orders');
$order  = null;
foreach ($orders as $o) { if ($o['id'] === $oid) { $order = $o; break; } }
if (!$order) { http_response_code(404); die('Commande introuvable'); }

// Seulement admin peut envoyer, tout le monde peut voir/download avec l'id
if ($action === 'send' && !$is_admin) { http_response_code(403); die('Accès refusé'); }

$cfg = cfg_get();
$c   = $order['customer'] ?? [];
$sn  = $cfg['site_name']  ?? 'Café Maison';

// Générer ou récupérer le numéro de facture
if (empty($order['invoice_num'])) {
    $counter = (int)($cfg['invoice_counter'] ?? 1);
    $prefix  = $cfg['invoice_prefix'] ?? 'CM';
    $year    = date('Y');
    $inv_num = $prefix . '-' . $year . '-' . str_pad($counter, 4, '0', STR_PAD_LEFT);
    // Incrémenter le compteur
    $cfg['invoice_counter'] = $counter + 1;
    cfg_save($cfg);
    // Sauvegarder le numéro dans la commande
    foreach ($orders as &$o) {
        if ($o['id'] === $oid) { $o['invoice_num'] = $inv_num; $o['invoice_date'] = date('Y-m-d'); }
    } unset($o);
    db_save('orders', $orders);
    $order['invoice_num']  = $inv_num;
    $order['invoice_date'] = date('Y-m-d');
}

$inv_num  = $order['invoice_num'];
$inv_date = date('d/m/Y', strtotime($order['invoice_date'] ?? $order['created_at'] ?? 'now'));
$inv_color = $cfg['invoice_color'] ?? '#C8561E';
$inv_logo  = $cfg['invoice_logo']  ?? '';
$inv_note  = $cfg['invoice_note']  ?? '';
$inv_footer= $cfg['invoice_footer']?? '';
$show_tva  = !empty($cfg['invoice_show_tva'] ?? true);

// Calculer totaux
$subtotal = 0;
$tva_lines = [];
// price en DB = TTC. HT = TTC / (1 + tva/100)
$tva_total = 0;
foreach ($order['cart'] ?? [] as $it) {
    $line_ttc  = round(($it['price']??0) * ($it['qty']??1), 2);
    $tva_r     = (int)($it['tva'] ?? ($cfg['tva_default'] ?? 20));
    $line_ht   = round($line_ttc / (1 + $tva_r/100), 2);
    $line_tva  = round($line_ttc - $line_ht, 2);
    $subtotal += $line_ht;
    if (!isset($tva_lines[$tva_r])) $tva_lines[$tva_r] = 0;
    $tva_lines[$tva_r] += $line_tva;
    $tva_total += $line_tva;
}
$discount = (float)($order['discount'] ?? 0);
$total    = (float)($order['amount'] ?? ($subtotal + $tva_total));
$shipping = round($total - $subtotal - $tva_total - $discount, 2);
if ($shipping < 0) $shipping = 0;

// ── Générer le HTML de la facture ────────────────────────────────────
ob_start();
?><!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width">
<title>Facture <?= e($inv_num) ?></title>
<style>
* { box-sizing:border-box; margin:0; padding:0; }
body { font-family: 'Helvetica Neue', Arial, sans-serif; color: #1a1a1a; background: #fff; font-size: 13px; line-height:1.5; }
.invoice { max-width:800px; margin:0 auto; padding:40px; }
.inv-header { display:flex; justify-content:space-between; align-items:flex-start; margin-bottom:40px; padding-bottom:24px; border-bottom:3px solid <?= $inv_color ?>; }
.inv-logo { font-family:Georgia,serif; font-size:22px; font-weight:700; color:<?= $inv_color ?>; }
.inv-logo img { max-height:60px; max-width:180px; }
.inv-meta { text-align:right; }
.inv-num { font-size:20px; font-weight:700; color:<?= $inv_color ?>; }
.inv-date { color:#666; font-size:12px; margin-top:4px; }
.inv-parties { display:grid; grid-template-columns:1fr 1fr; gap:40px; margin-bottom:32px; }
.inv-party h4 { font-size:10px; text-transform:uppercase; letter-spacing:.12em; color:#999; margin-bottom:8px; }
.inv-party strong { display:block; font-size:14px; margin-bottom:4px; }
.inv-party p { color:#555; font-size:12px; line-height:1.7; }
table { width:100%; border-collapse:collapse; margin-bottom:24px; }
thead th { background:<?= $inv_color ?>; color:#fff; padding:10px 12px; text-align:left; font-size:11px; text-transform:uppercase; letter-spacing:.08em; }
tbody td { padding:10px 12px; border-bottom:1px solid #f0f0f0; }
tbody tr:nth-child(even) td { background:#fafafa; }
.text-right { text-align:right; }
.totals { margin-left:auto; width:280px; }
.totals tr td { padding:6px 12px; font-size:12px; }
.totals tr:last-child td { font-weight:700; font-size:15px; border-top:2px solid <?= $inv_color ?>; padding-top:10px; color:<?= $inv_color ?>; }
.note { background:#f8f8f8; border-left:3px solid <?= $inv_color ?>; padding:12px 16px; margin:24px 0; font-size:12px; color:#555; }
.inv-footer { margin-top:40px; padding-top:20px; border-top:1px solid #eee; font-size:10px; color:#aaa; text-align:center; line-height:2; }
.status-badge { display:inline-block; padding:3px 10px; border-radius:100px; font-size:10px; font-weight:700; text-transform:uppercase; letter-spacing:.1em; background:<?= $inv_color ?>22; color:<?= $inv_color ?>; }
@media print {
  body { font-size:11px; }
  .no-print { display:none; }
  .invoice { padding:20px; }
}
</style>
</head>
<body>
<div class="invoice">
  <!-- En-tête -->
  <div class="inv-header">
    <div>
      <?php if ($inv_logo): ?>
      <img src="<?= e($inv_logo) ?>" alt="<?= e($sn) ?>" class="inv-logo">
      <?php else: ?>
      <div class="inv-logo">☕ <?= e($sn) ?></div>
      <?php endif; ?>
      <?php if ($cfg['company_legal']??''): ?>
      <div style="font-size:11px;color:#888;margin-top:4px"><?= e($cfg['company_legal']??'') ?></div>
      <?php endif; ?>
    </div>
    <div class="inv-meta">
      <div class="inv-num">FACTURE N° <?= e($inv_num) ?></div>
      <div class="inv-date">Date : <?= $inv_date ?></div>
      <div class="inv-date">Commande : <?= e(date('d/m/Y', strtotime($order['created_at']??'now'))) ?></div>
      <div style="margin-top:6px"><span class="status-badge">
        <?php $st_labels=['paid'=>'Payé','preparing'=>'En préparation','shipped'=>'Expédié','delivered'=>'Livré'];
        echo e($st_labels[$order['status']??'paid'] ?? 'Payé'); ?>
      </span></div>
    </div>
  </div>

  <!-- Vendeur / Client -->
  <div class="inv-parties">
    <div class="inv-party">
      <h4>Émetteur</h4>
      <strong><?= e($cfg['company_name'] ?: $sn) ?></strong>
      <p>
        <?= e($cfg['address']??'') ?><br>
        <?= e($cfg['email']??'') ?><br>
        <?= e($cfg['phone']??'') ?>
        <?php if ($cfg['company_siret']??''): ?><br>SIRET : <?= e($cfg['company_siret']) ?><?php endif; ?>
        <?php if ($cfg['company_vat']??''): ?><br>TVA : <?= e($cfg['company_vat']) ?><?php endif; ?>
      </p>
    </div>
    <div class="inv-party">
      <h4>Client</h4>
      <strong><?= e(trim(($c['fname']??'').' '.($c['lname']??''))) ?></strong>
      <p>
        <?= e($c['email']??'') ?><br>
        <?= e($c['phone']??'') ?>
        <?php if (!empty($c['addr'])): ?>
        <br><?= e($c['addr']) ?>, <?= e($c['zip']??'') ?> <?= e($c['city']??'') ?>
        <?php endif; ?>
      </p>
    </div>
  </div>

  <!-- Tableau des articles -->
  <table>
    <thead>
      <tr>
        <th>Description</th>
        <th class="text-right">Prix unit. TTC</th>
        <th class="text-right">Qté</th>
        <?php if ($show_tva): ?><th class="text-right">TVA</th><?php endif; ?>
        <th class="text-right">Total</th>
      </tr>
    </thead>
    <tbody>
    <?php foreach ($order['cart']??[] as $it):
      $ttc_u = (float)($it['price']??0);            // price = TTC
      $tva_r = (int)($it['tva'] ?? ($cfg['tva_default'] ?? 20));
      $ht_u  = round($ttc_u / (1 + $tva_r/100), 4); // HT = TTC / (1 + tva)
      $line  = round($ttc_u * ($it['qty']??1), 2);   // total TTC
    ?>
    <tr>
      <td>
        <strong><?= e($it['name']??'') ?></strong>
        <?php if (!empty($it['weight'])): ?><br><span style="font-size:11px;color:#888"><?= e($it['weight']) ?></span><?php endif; ?>
      </td>
      <td class="text-right"><?= number_format($ht_u,2,',',' ') ?> € HT</td>
      <td class="text-right"><?= (int)($it['qty']??1) ?></td>
      <?php if ($show_tva): ?><td class="text-right"><?= $tva_r ?> %</td><?php endif; ?>
      <td class="text-right"><strong><?= number_format($line,2,',',' ') ?> €</strong></td>
    </tr>
    <?php endforeach; ?>
    </tbody>
  </table>

  <!-- Totaux -->
  <div style="display:flex;justify-content:flex-end">
    <table class="totals">
      <tbody>
        <tr><td>Sous-total TTC</td><td class="text-right"><?= number_format($subtotal,2,',',' ') ?> €</td></tr>
        <?php if (($order['discount']??0)>0): ?>
        <tr><td style="color:<?= $inv_color ?>">Remise</td><td class="text-right" style="color:<?= $inv_color ?>">−<?= number_format($order['discount'],2,',',' ') ?> €</td></tr>
        <?php endif; ?>
        <?php if ($shipping>0): ?>
        <tr><td>Livraison</td><td class="text-right"><?= number_format($shipping,2,',',' ') ?> €</td></tr>
        <?php elseif ($shipping==0): ?>
        <tr><td style="color:#7AAD5C">Livraison</td><td class="text-right" style="color:#7AAD5C">Gratuite</td></tr>
        <?php endif; ?>
        <?php if ($show_tva && $tva_lines): ?>
        <tr style="font-size:11px;color:#888"><td>Sous-total HT</td><td class="text-right"><?= number_format($subtotal - ($discount??0), 2, ',', ' ') ?> €</td></tr>
        <?php foreach ($tva_lines as $t=>$mt): ?>
        <tr style="font-size:11px;color:#888"><td>TVA <?= $t ?> %</td><td class="text-right">+ <?= number_format($mt,2,',',' ') ?> €</td></tr>
        <?php endforeach; ?>
        <?php endif; ?>
        <tr><td>TOTAL TTC</td><td class="text-right"><?= number_format($total,2,',',' ') ?> €</td></tr>
      </tbody>
    </table>
  </div>

  <!-- Mode de paiement -->
  <div style="margin-top:16px;font-size:12px;color:#888;text-align:right">
    Payé par : <?= e(ucfirst($order['method']??'carte')) ?> ·
    Réf. : <?= e($order['intent_id']??$order['paypal_id']??$order['id']??'') ?>
  </div>

  <?php if ($inv_note): ?>
  <div class="note"><?= e($inv_note) ?></div>
  <?php endif; ?>

  <!-- Pied de page légal -->
  <div class="inv-footer">
    <?= e($cfg['company_name']?:$sn) ?>
    <?php if ($cfg['company_legal']??''): ?> · <?= e($cfg['company_legal']) ?><?php endif; ?>
    <?php if ($cfg['company_siret']??''): ?> · SIRET <?= e($cfg['company_siret']) ?><?php endif; ?>
    <?php if ($cfg['company_rcs']??''): ?> · <?= e($cfg['company_rcs']) ?><?php endif; ?>
    <?php if ($cfg['company_vat']??''): ?> · TVA <?= e($cfg['company_vat']) ?><?php endif; ?>
    <br><?= e($cfg['address']??'') ?>
    <?php if ($inv_footer): ?><br><?= nl2br(e($inv_footer)) ?><?php endif; ?>
    <?php if ($cfg['company_capital']??''): ?><br>Capital social : <?= e($cfg['company_capital']) ?><?php endif; ?>
  </div>

  <!-- Boutons (non imprimés) -->
  <?php if ($is_admin): ?>
  <div class="no-print" style="margin-top:24px;display:flex;gap:.8rem;justify-content:flex-end;padding-top:16px;border-top:1px solid #eee">
    <button onclick="window.print()" style="background:<?= $inv_color ?>;color:#fff;border:none;padding:.6rem 1.4rem;border-radius:6px;cursor:pointer;font-size:.85rem;font-weight:600">🖨 Imprimer / PDF</button>
    <a href="?id=<?= e($oid) ?>&action=send" onclick="return confirm('Envoyer la facture par email à <?= e(addslashes($c['email']??'')) ?> ?')"
      style="background:#2A4C1E;color:#fff;border:none;padding:.6rem 1.4rem;border-radius:6px;text-decoration:none;font-size:.85rem;font-weight:600">📧 Envoyer par email</a>
    <a href="../admin/orders.php?act=edit&id=<?= e($oid) ?>"
      style="background:#eee;color:#333;padding:.6rem 1.4rem;border-radius:6px;text-decoration:none;font-size:.85rem">← Retour commande</a>
  </div>
  <?php endif; ?>
</div>

<?php if ($action === 'send' && $is_admin):
  // Envoyer par email
  $to   = $c['email'] ?? '';
  $name = trim(($c['fname']??'').' '.($c['lname']??''));
  if (filter_var($to, FILTER_VALIDATE_EMAIL)) {
      $invoice_html = ob_get_contents();
      $subject = 'Votre facture N° ' . $inv_num . ' — ' . $sn;
      $body = "<p>Bonjour $name,</p><p>Veuillez trouver ci-joint votre facture N° <strong>$inv_num</strong> pour votre commande du $inv_date.</p><p>Montant total : <strong>" . number_format($total,2,',',' ') . " €</strong></p>";
      $body .= "<p>Vous pouvez également consulter votre facture en ligne en cliquant sur le bouton ci-dessous.</p>";
      $html_email = function_exists('mail_wrap') ? mail_wrap("Votre facture N° $inv_num", $body, 'Voir la facture', '') : $body;
      cm_mail($to, $name, $subject, $html_email);
      admin_log('invoice_sent', "Facture $inv_num envoyée à $to");
      echo '<script>alert("Facture envoyée à '.e($to).'");window.location="../admin/orders.php?act=edit&id='.e($oid).'";</script>';
  }
endif;
ob_end_flush();
?>
</body>
</html>
