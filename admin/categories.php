<?php
require_once __DIR__ . '/../includes/config.php';
require_auth();

$cats = db('categories');
$prds = db('products');
$errs = [];
$act  = clean($_GET['a'] ?? 'list', 10);
$id   = clean($_GET['id'] ?? '', 30);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $pa = clean($_POST['pa'] ?? '', 10);

    if ($pa === 'save') {
        $eid   = clean($_POST['eid']   ?? '', 30);
        $name  = clean($_POST['name']  ?? '', 80);
        $slug  = clean($_POST['slug']  ?? '', 80) ?: slug($name);
        $emoji = clean($_POST['emoji'] ?? '🏷', 8);
        $order = clean_int($_POST['order'] ?? 99, 0, 999);
        $active = isset($_POST['active']);
        if (!$name) $errs[] = 'Le nom est requis.';
        if (!$errs) {
            $row = ['name'=>$name,'slug'=>$slug,'emoji'=>$emoji,'order'=>$order,'active'=>$active];
            if ($eid) {
                foreach ($cats as &$c) { if ($c['id']===$eid) $c = array_merge($c,$row); }
                unset($c);
                admin_log('cat_edit', "Catégorie $name modifiée");
            } else {
                $row['id'] = 'cat_'.new_id();
                $cats[]    = $row;
                admin_log('cat_add', "Catégorie $name créée");
            }
            usort($cats, fn($a,$b) => ($a['order']??99) - ($b['order']??99));
            db_save('categories', $cats);
            header('Location: ./categories.php?saved=1'); exit;
        }
    }
    if ($pa === 'delete') {
        $did = clean($_POST['did'] ?? '', 30);
        // Retirer la catégorie des produits liés
        $prds2 = db('products');
        foreach ($prds2 as &$p) {
            if (($p['cat_id']??'') === $did) { $p['cat_id'] = ''; }
        } unset($p);
        db_save('products', $prds2);
        db_save('categories', array_values(array_filter($cats, fn($c) => $c['id'] !== $did)));
        admin_log('cat_delete', "Catégorie $did supprimée");
        header('Location: ./categories.php?del=1'); exit;
    }
}

$ed = null;
if ($act === 'edit' && $id) {
    foreach ($cats as $c) { if ($c['id'] === $id) { $ed = $c; break; } }
}

$at = 'Catégories'; $cur_adm = 'categories';
include ROOT . '/admin/inc/layout.php';
?>
<?php if ($act === 'new' || $act === 'edit'): ?>
<div class="adm-top">
  <div><h2><?= $act==='new'?'Nouvelle catégorie':'Modifier la catégorie' ?></h2></div>
  <div class="adm-acts"><a href="./categories.php" class="a-btn">← Retour</a></div>
</div>
<div class="adm-content">
  <?php if ($errs): ?><div class="alert-err"><?php foreach($errs as $e) echo '<div>✗ '.htmlspecialchars($e).'</div>'; ?></div><?php endif; ?>
  <form method="POST" style="max-width:560px">
    <?= csrf_field() ?>
    <input type="hidden" name="pa" value="save">
    <input type="hidden" name="eid" value="<?= e($ed['id']??'') ?>">
    <div class="editor-wrap">
      <div class="frm-row">
        <div class="frm-g"><label class="frm-l">Nom *</label><input class="frm-i" name="name" required maxlength="80" value="<?= e($ed['name']??'') ?>" placeholder="Ex: Café, Thé, Machines…"></div>
        <div class="frm-g"><label class="frm-l">Emoji</label><input class="frm-i" name="emoji" maxlength="8" value="<?= e($ed['emoji']??'🏷') ?>" style="font-size:1.3rem;text-align:center"></div>
      </div>
      <div class="frm-row">
        <div class="frm-g"><label class="frm-l">Slug URL</label><input class="frm-i" name="slug" maxlength="80" value="<?= e($ed['slug']??'') ?>" placeholder="auto-généré"></div>
        <div class="frm-g"><label class="frm-l">Ordre d'affichage</label><input class="frm-i" type="number" name="order" min="0" max="999" value="<?= e($ed['order']??99) ?>"></div>
      </div>
      <div class="tog-wrap"><label class="tog"><input type="checkbox" name="active" <?= ($ed['active']??true)?'checked':'' ?>><span class="tog-sl"></span></label><span class="tog-lbl">Catégorie visible en boutique</span></div>
      <button type="submit" class="save-btn" style="margin-top:1rem">💾 Enregistrer</button>
    </div>
  </form>
</div>
<?php else: ?>
<div class="adm-top">
  <div><h2>Catégories</h2><p><?= count($cats) ?> catégorie(s) — s'appliquent aux produits et aux filtres boutique</p></div>
  <div class="adm-acts"><a href="./categories.php?a=new" class="a-btn prim">+ Nouvelle catégorie</a></div>
</div>
<div class="adm-content">
  <?php if (isset($_GET['saved'])): ?><div class="alert-ok" style="margin-bottom:1rem">✓ Enregistré.</div><?php endif; ?>
  <?php if (!$cats): ?>
  <div class="empty-st"><div class="empty-ico">🏷</div><h4>Aucune catégorie</h4></div>
  <?php else: ?>
  <div class="tbl-wrap"><table>
    <thead><tr><th>Ordre</th><th>Catégorie</th><th>Slug</th><th>Produits</th><th>Statut</th><th>—</th></tr></thead>
    <tbody>
    <?php foreach ($cats as $c):
      $cnt = count(array_filter($prds, fn($p) => ($p['cat_id']??'') === $c['id']));
    ?>
    <tr>
      <td style="text-align:center;color:var(--gy2);font-size:.85rem"><?= e($c['order']??'') ?></td>
      <td style="font-weight:500"><?= e($c['emoji']??'') ?> <?= e($c['name']) ?></td>
      <td style="font-family:monospace;font-size:.8rem;color:var(--gy2)"><?= e($c['slug']??'') ?></td>
      <td style="text-align:center"><?= $cnt ?></td>
      <td><span class="bdg <?= ($c['active']??true)?'bdg-g':'bdg-gy' ?>"><?= ($c['active']??true)?'Active':'Inactive' ?></span></td>
      <td><div class="act-btns">
        <a href="./categories.php?a=edit&id=<?= e($c['id']) ?>" class="act-btn">Éditer</a>
        <form method="POST" style="display:inline"><?= csrf_field() ?>
          <input type="hidden" name="pa" value="delete">
          <input type="hidden" name="did" value="<?= e($c['id']) ?>">
          <button type="submit" class="act-btn del" data-confirm="Supprimer «<?= e($c['name']) ?>» ? Les produits liés perdront leur catégorie.">✕</button>
        </form>
      </div></td>
    </tr>
    <?php endforeach; ?>
    </tbody>
  </table></div>
  <?php endif; ?>
</div>
<?php endif; ?>
<?php include ROOT . '/admin/inc/layout_end.php'; ?>
