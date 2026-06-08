<?php
require_once __DIR__ . '/../includes/config.php';
require_auth();

$history = array_reverse(db('newsletter_history'));
$view = clean($_GET['id'] ?? '', 40);
$viewed = null;
if ($view) {
    foreach ($history as $h) {
        if ($h['id'] === $view) { $viewed = $h; break; }
    }
}

$at = 'Historique newsletters'; $cur_adm = 'newsletter';
include ROOT . '/admin/inc/layout.php';
?>
<div class="adm-top">
  <div>
    <h2>📨 Historique des campagnes</h2>
    <p><?= count($history) ?> campagne<?= count($history)>1?'s':'' ?> envoyée<?= count($history)>1?'s':'' ?></p>
  </div>
  <div class="adm-acts">
    <a href="./newsletter.php" class="a-btn">← Newsletter</a>
  </div>
</div>
<div class="adm-content">
  <?php if ($viewed): ?>
  <!-- Vue d'une campagne -->
  <div class="editor-wrap" style="margin-bottom:1.2rem">
    <div style="display:flex;align-items:center;gap:.8rem;margin-bottom:1rem">
      <a href="./newsletter_history.php" class="act-btn">← Retour</a>
      <h4 style="font-family:var(--f1);margin:0"><?= e($viewed['subject']) ?></h4>
    </div>
    <div style="display:flex;gap:2rem;font-size:.8rem;color:var(--gy2);margin-bottom:1rem;flex-wrap:wrap">
      <span>📅 <?= e($viewed['date']) ?></span>
      <span>👤 <?= e($viewed['admin'] ?? 'admin') ?></span>
      <span style="color:var(--vr2)">✓ <?= $viewed['sent'] ?> envoyés</span>
      <?php if ($viewed['failed']??0): ?><span style="color:var(--or)">✗ <?= $viewed['failed'] ?> échecs</span><?php endif; ?>
      <span>📬 Total: <?= $viewed['total'] ?? '—' ?></span>
    </div>
    <div style="background:#1a1a1a;border-radius:var(--r);padding:1.2rem;color:#fff;line-height:1.7">
      <strong style="display:block;margin-bottom:.8rem;color:rgba(255,255,255,.5);font-size:.72rem;text-transform:uppercase;letter-spacing:.1em">Contenu</strong>
      <div style="font-size:.88rem;white-space:pre-wrap"><?= nl2br(e($viewed['content'])) ?></div>
    </div>
  </div>

  <?php else: ?>
  <!-- Liste des campagnes -->
  <?php if (!$history): ?>
  <div class="empty-st">
    <div class="empty-ico">📭</div>
    <h4>Aucune campagne envoyée</h4>
    <p>L'historique apparaîtra ici après chaque envoi de newsletter.</p>
    <a href="./newsletter.php" class="a-btn prim" style="margin-top:.8rem">Envoyer une newsletter</a>
  </div>
  <?php else: ?>
  <div class="tbl-wrap">
    <table class="tbl">
      <thead><tr>
        <th>Date</th><th>Sujet</th><th>Envoyé par</th>
        <th>✓ Envoyés</th><th>✗ Échecs</th><th>Total</th><th></th>
      </tr></thead>
      <tbody>
      <?php foreach ($history as $h): ?>
      <tr>
        <td style="font-size:.78rem;color:var(--gy2);white-space:nowrap"><?= e($h['date']) ?></td>
        <td><strong><?= e($h['subject']) ?></strong></td>
        <td style="font-size:.78rem"><?= e($h['admin'] ?? 'admin') ?></td>
        <td style="color:var(--vr2);font-weight:600"><?= $h['sent'] ?></td>
        <td style="color:<?= ($h['failed']??0)?'var(--or)':'var(--gy3)' ?>"><?= $h['failed']??0 ?></td>
        <td style="color:var(--gy2)"><?= $h['total']??'—' ?></td>
        <td>
          <a href="?id=<?= e($h['id']) ?>" class="act-btn">👁 Voir</a>
        </td>
      </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?php endif; ?>
  <?php endif; ?>
</div>
<?php include ROOT . '/admin/inc/layout_end.php'; ?>
