<?php
require_once __DIR__ . '/../includes/config.php';
require_auth();

$cfg  = cfg_get();
$prds = db('products');
$arts = db('articles');
$wks  = db('workshops');
$msgs = db('messages');

$at = 'Dashboard'; $cur_adm = 'dash';
include ROOT . '/admin/inc/layout.php';
?>

<div class="adm-top">
  <div><h2>Tableau de bord</h2><p>Bienvenue · <?= date('l d F Y') ?></p></div>
  <div class="adm-acts">
    <a href="../" target="_blank" class="a-btn">Voir le site</a>
    <a href="./articles.php?a=new" class="a-btn prim">+ Article</a>
  </div>
</div>

<div class="adm-content">
  <div class="stats">
    <?php
    $stats = [
      ['Produits actifs', count(array_filter($prds, fn($p) => $p['active'] ?? true)), count($prds).' total', false],
      ['Articles publiés', count(array_filter($arts, fn($a) => $a['published'] ?? false)), count($arts).' total', false],
      ['Ateliers actifs', count(array_filter($wks, fn($w) => $w['active'] ?? true)), count($wks).' total', false],
      ['Messages non lus', count(array_filter($msgs, fn($m) => !($m['read'] ?? false))), 'À traiter', true],
    ];
    foreach ($stats as [$l,$v,$s,$neg]): ?>
    <div class="st">
      <div class="st-lbl"><?= $l ?></div>
      <div class="st-val" <?= $neg&&$v>0 ? 'style="color:var(--or)"':'' ?>><?= $v ?></div>
      <div class="st-sub <?= $neg&&$v>0?'neg':'' ?>"><?= $s ?></div>
    </div>
    <?php endforeach; ?>
  </div>

  <div class="panels">
    <!-- Messages récents -->
    <div class="pnl">
      <div class="pnl-hd"><span class="pnl-t">Messages récents</span><a href="./messages.php" class="pnl-a">Voir tout</a></div>
      <?php $last5 = array_slice(array_reverse($msgs), 0, 5);
      if (!$last5): ?>
      <div style="padding:1.5rem;text-align:center;color:var(--gy2);font-size:.83rem;">Aucun message reçu</div>
      <?php else: foreach ($last5 as $m): ?>
      <div class="msg-row">
        <div class="msg-dot" style="background:<?= !($m['read']??false)?'var(--or)':'transparent;border:1px solid var(--gy3)' ?>"></div>
        <div style="flex:1;min-width:0;">
          <div class="msg-name"><?= e($m['name']) ?></div>
          <div class="msg-preview"><?= e($m['sujet'] ?? '') ?> — <?= e(substr($m['message'],0,50)) ?>…</div>
        </div>
        <div class="msg-date"><?= date('d/m', strtotime($m['date'])) ?></div>
      </div>
      <?php endforeach; endif; ?>
    </div>

    <!-- Actions rapides -->
    <div class="pnl">
      <div class="pnl-hd"><span class="pnl-t">Actions rapides</span></div>
      <div class="pnl-body" style="display:grid;gap:.8rem;padding:1.2rem 1.4rem;">
        <a href="./articles.php?a=new" class="a-btn prim" style="justify-content:center;">+ Nouvel article</a>
        <a href="./products.php?a=new" class="a-btn" style="justify-content:center;">+ Nouveau produit</a>
        <a href="./workshops.php" class="a-btn" style="justify-content:center;">Gérer les ateliers</a>
        <a href="./settings.php" class="a-btn" style="justify-content:center;">⚙ Paramètres</a>
      </div>
    </div>
  </div>

  <!-- Derniers articles -->
  <div class="tbl-wrap">
    <div class="tbl-hd"><span class="pnl-t">Articles récents</span><a href="./articles.php" class="a-btn">Gérer</a></div>
    <table>
      <thead><tr><th>Titre</th><th>Page</th><th>Catégorie</th><th>Statut</th><th>Date</th><th>—</th></tr></thead>
      <tbody>
        <?php foreach (array_slice(array_reverse($arts), 0, 5) as $a): ?>
        <tr>
          <td style="font-weight:500;max-width:280px;"><?= e($a['title']) ?></td>
          <td><?= e(ucfirst($a['page'] ?? 'blog')) ?></td>
          <td><?= e($a['cat'] ?? '') ?></td>
          <td><span class="bdg <?= ($a['published']??false)?'bdg-g':'bdg-gy' ?>"><?= ($a['published']??false)?'Publié':'Brouillon' ?></span></td>
          <td style="color:var(--gy2);font-size:.78rem;"><?= e($a['created'] ?? '') ?></td>
          <td><a href="./articles.php?a=edit&id=<?= e($a['id']) ?>" class="act-btn">Éditer</a></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<?php include ROOT . '/admin/inc/layout_end.php'; ?>
