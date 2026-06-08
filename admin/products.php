<?php
require_once __DIR__ . '/../includes/config.php';
require_auth();

$prds = db('products');
$act  = clean($_GET['a'] ?? 'list', 10);
$id   = clean($_GET['id'] ?? '', 20);
$errs = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $pa = clean($_POST['pa'] ?? '', 10);

    if ($pa === 'delete') {
        $did = clean($_POST['did'] ?? '', 20);
        db_save('products', array_values(array_filter($prds, fn($x) => $x['id'] !== $did)));
        admin_log('product_delete', "Produit $did supprimé");
        header('Location: ./products.php?del=1'); exit;
    }

    // Gérer les produits mis en avant
    if ($pa === 'featured_save') {
        // Marquer chaque produit comme featured ou non dans products.json
        $prds_up = db('products');
        $checked = $_POST['featured_ids'] ?? [];
        foreach ($prds_up as &$p) {
            $p['featured'] = in_array($p['id'], $checked);
        }
        unset($p);
        db_save('products', $prds_up);
        admin_log('featured_save', 'Produits mis en avant: ' . count($checked) . ' sélectionnés');
        header('Location: ./products.php?saved=featured'); exit;
    }

    if ($pa === 'save') {
        $eid           = clean($_POST['eid']           ?? '', 20);
        $name          = clean($_POST['name']          ?? '', 150);
        $slug          = clean($_POST['slug']          ?? '', 150) ?: slug($name);
        $cat           = clean($_POST['cat']    ?? '', 30);  // legacy
        $cat_id        = clean($_POST['cat_id'] ?? '', 40);  // nouveau système
        $price         = clean_float($_POST['price']   ?? 0, 0, 9999);
        $weight_g      = max(1, min(30000, (int)($_POST['weight_g'] ?? 250)));
        // Générer l'affichage automatiquement: 250g → "250g", 1000g → "1kg", 1250g → "1,25kg"
        if ($weight_g >= 1000 && $weight_g % 1000 === 0) {
            $weight = ($weight_g/1000) . 'kg';
        } elseif ($weight_g >= 1000) {
            $weight = number_format($weight_g/1000, $weight_g%100===0?1:2, ',', '') . 'kg';
        } else {
            $weight = $weight_g . 'g';
        }
        $desc          = clean($_POST['desc']          ?? '', 500);
        $origin        = clean($_POST['origin']        ?? '', 80);
        $roast         = clean($_POST['roast']         ?? '', 30);
        $badge         = clean($_POST['badge']         ?? '', 40);
        $stock         = clean_int($_POST['stock']     ?? 0, 0, 99999);
        $emoji         = clean($_POST['emoji']         ?? '☕', 8);
        $image         = clean($_POST['image']         ?? '', 500);
        $active        = isset($_POST['active']);
        $tva           = clean_int($_POST['tva']       ?? 20, 0, 100);
        $delivery_mode = in_array($_POST['delivery_mode'] ?? '', ['both','delivery','pickup']) ? $_POST['delivery_mode'] : 'both';

        if (!$name)      $errs[] = 'Le nom est requis.';
        if ($price <= 0) $errs[] = 'Le prix doit être supérieur à 0.';
        foreach ($prds as $x) {
            if ($x['slug'] === $slug && $x['id'] !== $eid) { $errs[] = 'Slug déjà utilisé.'; break; }
        }

        if (!$errs) {
            $ht  = round($price / (1 + $tva / 100), 4);
            $row = [
                'name'          => $name,
                'slug'          => $slug,
                'cat'           => $cat_id,   // utilise cat_id maintenant
                'cat_id'        => $cat_id,
                'price'         => $price,   // TTC
                'price_ht'      => $ht,
                'tva'           => $tva,
                'weight'        => $weight,
                'weight_g'      => $weight_g,
                'desc'          => $desc,
                'origin'        => $origin,
                'roast'         => $roast,
                'badge'         => $badge,
                'stock'         => $stock,
                'emoji'         => $emoji,
                'image'         => $image,
                'active'        => $active,
                'delivery_mode' => $delivery_mode,
            ];
            if ($eid) {
                foreach ($prds as &$x) {
                    if ($x['id'] === $eid) { $x = array_merge($x, $row); $x['updated'] = date('Y-m-d'); }
                }
                unset($x);
            } else {
                $row['id']      = new_id();
                $row['created'] = date('Y-m-d');
                $prds[]         = $row;
            }
            db_save('products', $prds);
            header('Location: ./products.php?saved=1'); exit;
        }
    }
}

$ed = null;
if ($act === 'edit' && $id) foreach ($prds as $x) { if ($x['id'] === $id) { $ed = $x; break; } }

$cfg = cfg_get();
$tva_default = (int)($cfg['tva_default'] ?? 20);
$at = 'Produits'; $cur_adm = 'prd';
include ROOT . '/admin/inc/layout.php';
?>

<?php if ($act === 'new' || $act === 'edit'): ?>
<div class="adm-top">
  <div><h2><?= $act==='new'?'Nouveau produit':'Modifier le produit' ?></h2>
  <p><?= $ed ? e($ed['name']) : 'Ajouter un café ou accessoire' ?></p></div>
  <div class="adm-acts"><a href="./products.php" class="a-btn">← Retour</a></div>
</div>
<div class="adm-content">
  <?php if ($errs): ?><div class="alert-err"><?php foreach ($errs as $e) echo '<div>✗ '.htmlspecialchars($e).'</div>'; ?></div><?php endif; ?>
  <form method="POST">
    <?= csrf_field() ?>
    <input type="hidden" name="pa"  value="save">
    <input type="hidden" name="eid" value="<?= e($ed['id'] ?? '') ?>">
    <div class="edit-grid">
      <div>
        <div class="editor-wrap">
          <div class="frm-row">
            <div class="frm-g"><label class="frm-l">Nom *</label>
              <input class="frm-i" id="f_name" name="name" required maxlength="150" value="<?= e($ed['name'] ?? '') ?>" placeholder="Nom du produit"></div>
            <div class="frm-g"><label class="frm-l">Slug URL</label>
              <input class="frm-i" id="f_slug" name="slug" maxlength="150" value="<?= e($ed['slug'] ?? '') ?>"></div>
          </div>
          <div class="frm-g"><label class="frm-l">Description</label>
            <textarea class="frm-i" name="desc" rows="4" maxlength="500" style="resize:vertical"><?= e($ed['desc'] ?? '') ?></textarea></div>
          <div class="frm-row">
            <div class="frm-g"><label class="frm-l">Origine</label>
              <input class="frm-i" name="origin" maxlength="80" value="<?= e($ed['origin'] ?? '') ?>" placeholder="Ex: Éthiopie"></div>
            <div class="frm-g"><label class="frm-l">Torréfaction</label>
              <select class="frm-sel" name="roast">
                <?php foreach (['Légère','Médium','Médium-forte','Forte','-'] as $r): ?>
                <option <?= ($ed['roast']??'')===$r?'selected':'' ?>><?= $r ?></option>
                <?php endforeach; ?>
              </select></div>
          </div>
          <!-- Image : upload ou URL + Emoji -->
          <div class="frm-g">
            <label class="frm-l">Image du produit</label>
            <input type="hidden" name="image" id="fld_image" value="<?= e($ed['image']??'') ?>">
            <?php if (!empty($ed['image'])): ?>
            <div style="margin-bottom:.6rem;display:flex;align-items:center;gap:.8rem">
              <img id="prev_image" src="<?= e($ed['image']) ?>" style="width:72px;height:72px;object-fit:cover;border-radius:8px">
              <div>
                <div style="font-size:.75rem;color:var(--gy2);margin-bottom:.3rem">Image actuelle</div>
                <button type="button" onclick="document.getElementById('fld_image').value='';document.getElementById('prev_image').remove();this.closest('div').remove()" class="act-btn del" style="font-size:.7rem">✕ Supprimer</button>
              </div>
            </div>
            <?php else: ?>
            <img id="prev_image" style="display:none;width:72px;height:72px;object-fit:cover;border-radius:8px;margin-bottom:.5rem">
            <?php endif; ?>
            <div class="img-uploader" data-field="image" data-preview="prev_image"></div>
            <div style="display:flex;align-items:center;gap:.5rem;margin-top:.4rem">
              <span style="font-size:.72rem;color:var(--gy2)">ou URL directe :</span>
              <input class="frm-i" placeholder="https://..." style="flex:1;font-size:.78rem"
                onchange="document.getElementById('fld_image').value=this.value;document.getElementById('prev_image').src=this.value;document.getElementById('prev_image').style.display='block'">
            </div>
          </div>
          <div class="frm-g"><label class="frm-l">Emoji (si pas d'image)</label>
            <input class="frm-i" name="emoji" maxlength="8" value="<?= e($ed['emoji']??'☕') ?>" style="width:70px;text-align:center;font-size:1.3rem">
          </div>
        </div>
      </div>
      <div>
        <div class="editor-wrap" style="margin-bottom:1rem">
          <div class="tog-wrap"><label class="tog"><input type="checkbox" name="active" <?= ($ed['active']??true)?'checked':'' ?>><span class="tog-sl"></span></label>
          <span class="tog-lbl">Visible en boutique</span></div>
        </div>
        <div class="editor-wrap">
          <?php $dyn_cats = array_filter(db('categories'), fn($c)=>$c['active']??true); ?>
        <div class="frm-g"><label class="frm-l">Catégorie</label>
          <select class="frm-sel" name="cat_id">
            <option value="">— Aucune —</option>
            <?php foreach ($dyn_cats as $dc): ?>
            <option value="<?= e($dc['id']) ?>" <?= ($ed['cat_id']??'')===$dc['id']?'selected':'' ?>>
              <?= e($dc['emoji']??'') ?> <?= e($dc['name']) ?>
            </option>
            <?php endforeach; ?>
          </select></div>

          <!-- Prix TTC / HT / TVA -->
          <div style="background:rgba(42,76,30,.06);border:1px solid rgba(42,76,30,.15);border-radius:var(--r);padding:1rem;margin-bottom:1rem">
            <div class="frm-row">
              <div class="frm-g"><label class="frm-l">Prix TTC (€) *</label>
                <input class="frm-i" type="number" id="f_price" name="price" step="0.01" min="0.01" required
                  value="<?= e($ed['price'] ?? '') ?>" oninput="calcHT()"></div>
              <div class="frm-g"><label class="frm-l">TVA (%)</label>
                <select class="frm-sel" id="f_tva" name="tva" onchange="calcHT()">
                  <?php foreach ([0,5,10,20] as $t): ?>
                  <option value="<?= $t ?>" <?= ($ed['tva']??$tva_default)==$t?'selected':'' ?>><?= $t ?> %<?= $t===5?' (alimentaire)':($t===20?' (standard)':'') ?></option>
                  <?php endforeach; ?>
                  <option value="custom" id="tva_custom_opt" <?= (!in_array($ed['tva']??$tva_default,[0,5,10,20]))?'selected':'' ?>>Personnalisé</option>
                </select></div>
            </div>
            <div id="tva_custom_box" style="display:<?= (!in_array($ed['tva']??$tva_default,[0,5,10,20]))?'block':'none' ?>;margin-bottom:.7rem">
              <label class="frm-l">Taux personnalisé (%)</label>
              <input class="frm-i" type="number" min="0" max="100" step="0.1" id="f_tva_custom"
                value="<?= (!in_array($ed['tva']??20,[0,5,10,20]))?e($ed['tva']??20):'' ?>"
                oninput="document.getElementById('f_tva').value=this.value;calcHT()">
            </div>
            <div style="display:flex;gap:1.5rem;font-size:.82rem;color:var(--gy)">
              <div>Prix HT : <strong id="disp_ht" style="color:var(--bk)"><?= number_format(($ed['price_ht']??($ed['price']??0)/1.2),2,',',' ') ?> €</strong></div>
              <div>TVA : <strong id="disp_tva_amt" style="color:var(--bk)"><?= number_format(($ed['price']??0)-($ed['price_ht']??0),2,',',' ') ?> €</strong></div>
            </div>
          </div>

          <div class="frm-row">
            <div class="frm-g">
              <label class="frm-l">Poids du produit (grammes) <small style="color:var(--gy2)">→ affiché sur le site + calcul livraison</small></label>
              <div style="display:flex;align-items:center;gap:.4rem">
                <input class="frm-i" type="number" name="weight_g" min="1" max="30000" step="1"
                  value="<?= (int)($ed['weight_g'] ?? 250) ?>" style="max-width:100px"
                  oninput="updateWeightDisplay(this.value)">
                <span id="weight_display" style="font-size:.82rem;color:var(--gy2);min-width:60px"><?php
                  $wg = (int)($ed['weight_g'] ?? 250);
                  if ($wg >= 1000 && $wg % 1000 === 0) echo ($wg/1000).'kg';
                  elseif ($wg >= 1000) echo number_format($wg/1000,2,',','').'kg';
                  else echo $wg.'g';
                ?></span>
                <small style="color:var(--gy2)">g</small>
              </div>
              <small style="color:var(--gy2);margin-top:.2rem;display:block">Emballage compris. Détermine les frais de livraison Colissimo.</small>
            </div>
            <div class="frm-g"><label class="frm-l">Stock</label>
              <input class="frm-i" type="number" name="stock" min="0" value="<?= e($ed['stock'] ?? 0) ?>"></div>
          </div>
          <div class="frm-g"><label class="frm-l">Badge promo</label>
            <input class="frm-i" name="badge" maxlength="40" value="<?= e($ed['badge'] ?? '') ?>" placeholder="Nouveauté, Coup de cœur…"></div>

          <!-- Mode de livraison par produit -->
          <div class="frm-g"><label class="frm-l">Mode de livraison pour ce produit</label>
            <select class="frm-sel" name="delivery_mode">
              <option value="both"     <?= ($ed['delivery_mode']??'both')==='both'    ?'selected':'' ?>>Livraison + Retrait (au choix du client)</option>
              <option value="delivery" <?= ($ed['delivery_mode']??'both')==='delivery'?'selected':'' ?>>Livraison uniquement</option>
              <option value="pickup"   <?= ($ed['delivery_mode']??'both')==='pickup'  ?'selected':'' ?>>Retrait en boutique uniquement</option>
            </select></div>

          <button type="submit" class="save-btn">💾 Enregistrer</button>
        </div>
      </div>
    </div>
  </form>
</div>
<script>
function calcHT() {
  const p = parseFloat(document.getElementById('f_price').value) || 0;
  const tvaEl = document.getElementById('f_tva');
  let t = parseFloat(tvaEl.value);
  if (tvaEl.value === 'custom') t = parseFloat(document.getElementById('f_tva_custom').value) || 0;
  const ht = p / (1 + t/100);
  document.getElementById('disp_ht').textContent = ht.toLocaleString('fr-FR',{minimumFractionDigits:2,maximumFractionDigits:2}) + ' €';
  document.getElementById('disp_tva_amt').textContent = (p-ht).toLocaleString('fr-FR',{minimumFractionDigits:2,maximumFractionDigits:2}) + ' €';
}
document.getElementById('f_tva').addEventListener('change', function() {
  document.getElementById('tva_custom_box').style.display = this.value==='custom'?'block':'none';
  calcHT();
});
function updatePreview() {
  const img = document.getElementById('f_image').value;
  const emoji = document.getElementById('f_emoji').value || '☕';
  const box = document.getElementById('imgPreview');
  box.innerHTML = img ? `<img src="${img}" style="width:100%;height:100%;object-fit:cover" onerror="this.remove()">` : emoji;
}
</script>

<?php else: ?>
<div class="adm-top">
  <div><h2>Produits boutique</h2><p><?= count($prds) ?> produit(s)</p></div>
  <div class="adm-acts">
    <a href="./featured.php" class="a-btn" style="color:var(--or);border-color:var(--or)">⭐ Mise en avant</a>
    <a href="./products.php?a=new" class="a-btn prim">+ Nouveau produit</a>
  </div>
</div>
<div class="adm-content">
  <?php if (isset($_GET['saved'])): ?><div class="alert-ok" style="margin-bottom:1rem">✓ Enregistré.</div><?php endif; ?>
  <?php if (isset($_GET['del'])):   ?><div class="alert-ok" style="margin-bottom:1rem">✓ Supprimé.</div><?php endif; ?>

  <div class="tbl-wrap"><table>
    <thead><tr><th>Produit</th><th>Cat.</th><th>Prix TTC</th><th>Prix HT</th><th>TVA</th><th>Stock</th><th>Livraison</th><th>Statut</th><th>—</th></tr></thead>
    <tbody>
    <?php foreach ($prds as $p):
      $dm = $p['delivery_mode'] ?? 'both';
      $dm_labels = ['both'=>'Livr.+Retrait','delivery'=>'🚚 Livraison','pickup'=>'🏪 Retrait'];
    ?>
    <tr>
      <td style="font-weight:500">
        <?php if (!empty($p['image'])): ?><img src="<?= e($p['image']) ?>" style="width:32px;height:32px;object-fit:cover;border-radius:4px;vertical-align:middle;margin-right:.4rem"><?php else: ?><?= e($p['emoji']??'☕') ?><?php endif; ?>
        <?= e($p['name']) ?>
        <?php if ($p['badge']??''): ?><span class="bdg bdg-o" style="margin-left:.3rem"><?= e($p['badge']) ?></span><?php endif; ?>
      </td>
      <td style="font-size:.8rem;color:var(--gy2)"><?php
        $cat_name = '';
        foreach ($all_cats as $ac) {
            if ($ac['id'] === ($p['cat'] ?? '')) { $cat_name = $ac['name']; break; }
        }
        echo $cat_name ? e($cat_name) : e(ucfirst($p['cat'] ?? '—'));
      ?></td>
      <td style="font-weight:600"><?= number_format($p['price']??0,2,',',' ') ?> €</td>
      <td style="font-size:.82rem;color:var(--gy2)">
        <?php $wg=(int)($p['weight_g']??250); echo $wg>=1000 ? number_format($wg/1000,($wg%1000?2:0),',','').'kg' : $wg.'g'; ?>
      </td>
      <td style="font-size:.8rem"><?= (int)($p['tva']??20) ?> %</td>
      <td style="color:<?= ($p['stock']??0)<=5?'var(--or)':'var(--vr2)' ?>;font-weight:600"><?= (int)($p['stock']??0) ?></td>
      <td style="font-size:.76rem"><?= $dm_labels[$dm] ?? $dm ?></td>
      <td><span class="bdg <?= ($p['active']??true)?'bdg-g':'bdg-gy' ?>"><?= ($p['active']??true)?'Actif':'Inactif' ?></span></td>
      <td><div class="act-btns">
        <a href="./products.php?a=edit&id=<?= e($p['id']) ?>" class="act-btn">Éditer</a>
        <form method="POST" style="display:inline"><?= csrf_field() ?>
          <input type="hidden" name="pa" value="delete"><input type="hidden" name="did" value="<?= e($p['id']) ?>">
          <button type="submit" class="act-btn del" data-confirm="Supprimer « <?= e(addslashes($p['name'])) ?> » ?">✕</button>
        </form>
      </div></td>
    </tr>
    <?php endforeach; ?>
    </tbody>
  </table></div>
</div>
<?php endif; ?>
<?php include ROOT . '/admin/inc/layout_end.php'; ?>
