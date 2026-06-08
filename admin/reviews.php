<?php
require_once __DIR__ . '/../includes/config.php';
require_auth();

$reviews = db('reviews');
$orders  = db('orders');
$cfg_r   = cfg_get();
$errs = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $pa = clean($_POST['pa'] ?? '', 10);
    if ($pa === 'approve') {
        $rid = clean($_POST['rid']??'',20);
        foreach ($reviews as &$r) { if ($r['id']===$rid) $r['approved']=true; } unset($r);
        db_save('reviews', $reviews);
    }
    if ($pa === 'reject') {
        $rid = clean($_POST['rid']??'',20);
        $reviews = array_values(array_filter($reviews, fn($r) => $r['id'] !== $rid));
        db_save('reviews', $reviews);
    }
    if ($pa === 'reply') {
        $rid = clean($_POST['rid']??'',20);
        $rep = clean($_POST['reply']??'',500);
        foreach ($reviews as &$r) { if ($r['id']===$rid) { $r['reply']=$rep; $r['reply_date']=date('Y-m-d'); } } unset($r);
        db_save('reviews', $reviews);
    }
    header('Location: ./reviews.php?saved=1'); exit;
}

$pending  = array_filter($reviews, fn($r) => !($r['approved']??false));
$approved = array_filter($reviews, fn($r)=>  ($r['approved']??false));
$avg      = count($approved) ? round(array_sum(array_column(array_values($approved),'rating'))/count($approved),1) : 0;

$at = 'Avis clients'; $cur_adm = 'reviews';
include ROOT . '/admin/inc/layout.php';
?>
<div class="adm-top">
  <div><h2>Avis clients</h2>
  <p><?= count($pending) ?> en attente · <?= count($approved) ?> publiés · Note moyenne : <?= $avg ?>/5 ⭐</p></div>
  <div class="adm-acts">
    <?php if (!empty($cfg_r['email_review_enabled'])): ?>
    <a href="<?= '../api/send_review_emails.php' ?>" class="a-btn" onclick="return confirm('Envoyer les emails de demande d\'avis aux clients J+<?= (int)($cfg_r['email_review_delay_days']??7) ?> ?')">
      📧 Envoyer emails J+<?= (int)($cfg_r['email_review_delay_days']??7) ?>
    </a>
    <?php endif; ?>
    <a href="../admin/settings.php" class="a-btn">⚙ Paramètres avis</a>
  </div>
</div>
<?php $cfg_r = cfg_get(); ?>
<div class="adm-content">
  <?php if ($pending): ?>
  <h3 style="font-family:var(--f1);font-size:1.2rem;margin-bottom:1rem;color:var(--or)">⏳ En attente de modération (<?= count($pending) ?>)</h3>
  <?php foreach (array_reverse(array_values($pending)) as $rv):
    $stars = str_repeat('★', $rv['rating']??5) . str_repeat('☆', 5-($rv['rating']??5));
    $verified = !empty($rv['order_id']);
  ?>
  <div style="background:#fff;border:1px solid #EAE3D9;border-radius:var(--r);padding:1.4rem;margin-bottom:1rem;border-left:3px solid var(--or)">
    <div style="display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:.8rem">
      <div>
        <strong><?= e($rv['author']??'Anonyme') ?></strong>
        <?php if($verified): ?><span class="bdg bdg-g" style="margin-left:.4rem;font-size:.62rem">✓ Achat vérifié</span><?php endif; ?>
        <div style="color:var(--or);font-size:1rem;margin-top:.2rem"><?= $stars ?></div>
        <div style="font-size:.73rem;color:var(--gy2)"><?= e($rv['product_name']??$rv['target_name']??'') ?> · <?= e($rv['date']??'') ?></div>
      </div>
      <div class="act-btns">
        <form method="POST" style="display:inline"><?= csrf_field() ?><input type="hidden" name="pa" value="approve"><input type="hidden" name="rid" value="<?= e($rv['id']) ?>"><button type="submit" class="act-btn" style="background:var(--vr);color:#fff;border-color:var(--vr)">✓ Approuver</button></form>
        <form method="POST" style="display:inline"><?= csrf_field() ?><input type="hidden" name="pa" value="reject"><input type="hidden" name="rid" value="<?= e($rv['id']) ?>"><button type="submit" class="act-btn del" data-confirm="Rejeter cet avis ?">✕ Rejeter</button></form>
      </div>
    </div>
    <p style="font-size:.9rem;color:var(--gy);margin:0"><?= e($rv['content']??'') ?></p>
  </div>
  <?php endforeach; ?>
  <?php endif; ?>

  <h3 style="font-family:var(--f1);font-size:1.2rem;margin:1.5rem 0 1rem">✅ Publiés (<?= count($approved) ?>)</h3>
  <?php if (!$approved): ?>
  <div class="empty-st"><div class="empty-ico">⭐</div><h4>Aucun avis publié</h4></div>
  <?php else: ?>
  <div class="tbl-wrap"><table>
    <thead><tr><th>Auteur</th><th>Note</th><th>Produit / Atelier</th><th>Commentaire</th><th>Vérifié</th><th>Date</th><th>—</th></tr></thead>
    <tbody>
    <?php foreach (array_reverse(array_values($approved)) as $rv): ?>
    <tr>
      <td style="font-weight:500"><?= e($rv['author']??'') ?></td>
      <td style="color:var(--or)"><?= str_repeat('★', $rv['rating']??5) ?></td>
      <td style="font-size:.82rem;color:var(--gy2)"><?= e($rv['product_name']??$rv['target_name']??'') ?></td>
      <td style="font-size:.82rem;max-width:200px"><?= e(mb_substr($rv['content']??'',0,80,'UTF-8')) ?>…</td>
      <td><?= !empty($rv['order_id'])?'<span class="bdg bdg-g">✓</span>':'' ?></td>
      <td style="font-size:.78rem;color:var(--gy2)"><?= e($rv['date']??'') ?></td>
      <td><form method="POST" style="display:inline"><?= csrf_field() ?><input type="hidden" name="pa" value="reject"><input type="hidden" name="rid" value="<?= e($rv['id']) ?>"><button type="submit" class="act-btn del" data-confirm="Retirer cet avis ?">✕</button></form></td>
    </tr>
    <?php endforeach; ?>
    </tbody>
  </table></div>
  <?php endif; ?>
</div>
<?php include ROOT . '/admin/inc/layout_end.php'; ?>
