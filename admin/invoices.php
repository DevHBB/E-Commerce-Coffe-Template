<?php
require_once __DIR__ . '/../includes/config.php';
require_auth();

$cfg    = cfg_get();
$orders = array_reverse(db('orders'));
// Seulement les commandes avec facture ou payées
$invoiced  = array_filter($orders, fn($o)=>!empty($o['invoice_num']));
$no_inv    = array_filter($orders, fn($o)=>empty($o['invoice_num'])&&in_array($o['status']??'',['paid','preparing','ready','shipped','delivered','cancelled']));

$at = 'Facturation'; $cur_adm = 'invoices';
include ROOT . '/admin/inc/layout.php';
?>
<div class="adm-top">
  <div><h2>Facturation</h2><p><?= count($invoiced) ?> facture(s) émise(s)</p></div>
</div>
<div class="adm-content">

  <!-- Config facturation rapide -->
  <div class="editor-wrap" style="margin-bottom:1.5rem">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:1rem">
      <h4 style="font-family:var(--f1);font-size:1.1rem">⚙ Paramètres de facturation</h4>
      <a href="./settings.php" class="a-btn">Modifier dans Paramètres →</a>
    </div>
    <div style="display:grid;grid-template-columns:repeat(4,1fr);gap:.8rem;font-size:.82rem">
      <div><div class="frm-l">Préfixe</div><strong><?= e($cfg['invoice_prefix']??'CM') ?></strong></div>
      <div><div class="frm-l">Prochain n°</div><strong><?= e($cfg['invoice_prefix']??'CM') ?>-<?= date('Y') ?>-<?= str_pad($cfg['invoice_counter']??1,4,'0',STR_PAD_LEFT) ?></strong></div>
      <div><div class="frm-l">TVA sur factures</div><strong><?= !empty($cfg['invoice_show_tva']??true)?'Oui':'Non' ?></strong></div>
      <div><div class="frm-l">Logo</div><strong><?= !empty($cfg['invoice_logo'])?'✓ Configuré':'— Non configuré' ?></strong></div>
    </div>
  </div>

  <!-- Factures émises -->
  <?php if ($invoiced): ?>
  <h3 style="font-family:var(--f1);font-size:1.1rem;margin-bottom:.8rem">🧾 Factures émises</h3>
  <div class="tbl-wrap" style="margin-bottom:2rem"><table>
    <thead><tr><th>N° Facture</th><th>Date</th><th>Client</th><th>Montant</th><th>Statut</th><th>—</th></tr></thead>
    <tbody>
    <?php foreach (array_values($invoiced) as $o):
      $c = $o['customer']??[];
    ?>
    <tr>
      <td style="font-family:monospace;font-weight:700;color:var(--or)"><?= e($o['invoice_num']) ?></td>
      <td style="font-size:.82rem"><?= e(date('d/m/Y', strtotime($o['invoice_date']??$o['created_at']??'now'))) ?></td>
      <td>
        <div style="font-weight:500"><?= e(trim(($c['fname']??'').' '.($c['lname']??''))) ?></div>
        <div style="font-size:.75rem;color:var(--gy2)"><?= e($c['email']??'') ?></div>
      </td>
      <td style="font-weight:700;font-family:var(--f1)"><?= number_format($o['amount']??0,2,',',' ') ?> €</td>
      <td><span class="bdg <?= in_array($o['status']??'',['paid','shipped','delivered'])?'bdg-g':'bdg-o' ?>">
        <?= e(ucfirst($o['status']??'')) ?>
      </span></td>
      <td>
        <div class="act-btns">
          <a href="../api/invoice.php?id=<?= e($o['id']) ?>" target="_blank" class="act-btn">🧾 Voir</a>
          <a href="../api/invoice.php?id=<?= e($o['id']) ?>&action=send" class="act-btn"
             onclick="return confirm('Renvoyer la facture à <?= e(addslashes($c['email']??'')) ?> ?')">📧 Renvoyer</a>
          <a href="./orders.php?act=edit&id=<?= e($o['id']) ?>" class="act-btn">✏</a>
        </div>
      </td>
    </tr>
    <?php endforeach; ?>
    </tbody>
  </table></div>
  <?php endif; ?>

  <!-- Commandes sans facture -->
  <?php if ($no_inv): ?>
  <h3 style="font-family:var(--f1);font-size:1.1rem;margin-bottom:.5rem;color:var(--or)">⚠ Commandes sans facture</h3>
  <p style="font-size:.8rem;color:var(--gy2);margin-bottom:.8rem">Cliquez sur "Générer" pour créer et envoyer la facture.</p>
  <div class="tbl-wrap"><table>
    <thead><tr><th>Date</th><th>Client</th><th>Montant</th><th>—</th></tr></thead>
    <tbody>
    <?php foreach (array_values($no_inv) as $o): $c=$o['customer']??[]; ?>
    <tr>
      <td style="font-size:.82rem"><?= e(date('d/m/Y', strtotime($o['created_at']??'now'))) ?></td>
      <td>
        <div style="font-weight:500"><?= e(trim(($c['fname']??'').' '.($c['lname']??''))) ?></div>
        <div style="font-size:.75rem;color:var(--gy2)"><?= e($c['email']??'') ?></div>
      </td>
      <td style="font-weight:700"><?= number_format($o['amount']??0,2,',',' ') ?> €</td>
      <td>
        <div class="act-btns">
          <a href="../api/invoice.php?id=<?= e($o['id']) ?>" target="_blank" class="act-btn prim">🧾 Générer</a>
          <a href="../api/invoice.php?id=<?= e($o['id']) ?>&action=send" class="act-btn"
             onclick="return confirm('Générer et envoyer la facture à <?= e(addslashes($c['email']??'')) ?> ?')">📧 Générer + Envoyer</a>
        </div>
      </td>
    </tr>
    <?php endforeach; ?>
    </tbody>
  </table></div>
  <?php endif; ?>

  <?php if (!$invoiced && !$no_inv): ?>
  <div class="empty-st"><div class="empty-ico">🧾</div><h4>Aucune facture</h4><p>Les factures seront générées à partir des commandes payées.</p></div>
  <?php endif; ?>
</div>
<?php include ROOT . '/admin/inc/layout_end.php'; ?>
