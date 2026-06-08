<?php
require_once __DIR__ . '/../includes/config.php';
require_auth();

$promos = db('promos');
$act    = clean($_GET['a'] ?? 'list', 10);
$id     = clean($_GET['id'] ?? '', 20);
$errs   = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $pa = clean($_POST['pa'] ?? '', 10);

    if ($pa === 'delete') {
        $did = clean($_POST['did'] ?? '', 20);
        db_save('promos', array_values(array_filter($promos, fn($x) => $x['id'] !== $did)));
        header('Location: ./promos.php?del=1'); exit;
    }

    if ($pa === 'toggle') {
        $did = clean($_POST['did'] ?? '', 20);
        foreach ($promos as &$p) { if ($p['id'] === $did) $p['active'] = !($p['active'] ?? true); }
        unset($p);
        db_save('promos', $promos);
        header('Location: ./promos.php?saved=1'); exit;
    }

    if ($pa === 'save') {
        $eid        = clean($_POST['eid']        ?? '', 20);
        $code       = strtoupper(preg_replace('/[^A-Z0-9_-]/i','',clean($_POST['code'] ?? '', 30)));
        $type       = in_array($_POST['type']??'', ['percent','fixed']) ? $_POST['type'] : 'percent';
        $value      = clean_float($_POST['value'] ?? 0, 0, 100000);
        $min_order  = clean_float($_POST['min_order'] ?? 0, 0, 9999);
        $scope      = in_array($_POST['scope']??'', ['all','product','workshop']) ? $_POST['scope'] : 'all';
        $scope_id   = clean($_POST['scope_id'] ?? '', 50);
        $usage_max  = clean_int($_POST['usage_max'] ?? 0, 0, 99999); // 0 = illimité
        $expires    = clean($_POST['expires'] ?? '', 20);
        $active     = isset($_POST['active']);

        if (!$code)   $errs[] = 'Le code est requis.';
        if ($value<=0) $errs[] = 'La valeur doit être > 0.';
        if ($type==='percent' && $value>100) $errs[] = '% ne peut pas dépasser 100.';
        // Code unique
        foreach ($promos as $x) {
            if ($x['code'] === $code && $x['id'] !== $eid) { $errs[] = 'Ce code existe déjà.'; break; }
        }
        if (!$errs) {
            $row = [
                'code'      => $code,
                'type'      => $type,
                'value'     => $value,
                'min_order' => $min_order,
                'scope'     => $scope,
                'scope_id'  => $scope_id,
                'usage_max' => $usage_max,
                'usage_ct'  => 0,
                'expires'   => $expires,
                'active'    => $active,
            ];
            if ($eid) {
                foreach ($promos as &$x) {
                    if ($x['id']===$eid) { $old_ct=$x['usage_ct']??0; $x=array_merge($x,$row); $x['usage_ct']=$old_ct; }
                }
                unset($x);
            } else {
                $row['id']      = new_id();
                $row['created'] = date('Y-m-d');
                $promos[]       = $row;
            }
            db_save('promos', $promos);
            header('Location: ./promos.php?saved=1'); exit;
        }
    }
}

$ed = null;
if ($act==='edit' && $id) foreach ($promos as $x) { if ($x['id']===$id) { $ed=$x; break; } }
$prds = db('products');
$wks  = db('workshops');
$at = 'Codes promo'; $cur_adm = 'promos';
include ROOT . '/admin/inc/layout.php';
?>

<?php if ($act==='new'||$act==='edit'): ?>
<div class="adm-top">
  <div><h2><?= $act==='new'?'Nouveau code promo':'Modifier le code' ?></h2></div>
  <div class="adm-acts"><a href="./promos.php" class="a-btn">← Retour</a></div>
</div>
<div class="adm-content">
  <?php if ($errs): ?><div class="alert-err"><?php foreach($errs as $e) echo '<div>✗ '.htmlspecialchars($e).'</div>'; ?></div><?php endif; ?>
  <form method="POST" style="max-width:680px">
    <?= csrf_field() ?>
    <input type="hidden" name="pa" value="save">
    <input type="hidden" name="eid" value="<?= e($ed['id']??'') ?>">
    <div class="editor-wrap">
      <div class="frm-row">
        <div class="frm-g"><label class="frm-l">Code promo *</label>
          <input class="frm-i" name="code" required maxlength="30" value="<?= e($ed['code']??'') ?>"
            placeholder="EX: CAFE20" style="text-transform:uppercase;font-family:monospace;font-weight:700;font-size:1.05rem"
            oninput="this.value=this.value.toUpperCase()"></div>
        <div class="frm-g" style="display:flex;flex-direction:column;justify-content:flex-end">
          <div class="tog-wrap"><label class="tog"><input type="checkbox" name="active" <?= ($ed['active']??true)?'checked':'' ?>><span class="tog-sl"></span></label>
          <span class="tog-lbl">Code actif</span></div>
        </div>
      </div>
      <div class="frm-row">
        <div class="frm-g"><label class="frm-l">Type de réduction</label>
          <select class="frm-sel" name="type" id="promo_type" onchange="toggleType()">
            <option value="percent" <?= ($ed['type']??'')==='percent'?'selected':'' ?>>% Pourcentage</option>
            <option value="fixed"   <?= ($ed['type']??'')==='fixed'  ?'selected':'' ?>>€ Montant fixe</option>
          </select></div>
        <div class="frm-g"><label class="frm-l">Valeur <span id="type_suffix"><?= ($ed['type']??'percent')==='percent'?'(%)':'(€)' ?></span></label>
          <input class="frm-i" type="number" name="value" step="0.01" min="0.01" required value="<?= e($ed['value']??'') ?>"></div>
      </div>
      <div class="frm-row">
        <div class="frm-g"><label class="frm-l">Commande minimum (€, 0 = aucun)</label>
          <input class="frm-i" type="number" name="min_order" step="0.01" min="0" value="<?= e($ed['min_order']??0) ?>"></div>
        <div class="frm-g"><label class="frm-l">Utilisations max (0 = illimité)</label>
          <input class="frm-i" type="number" name="usage_max" min="0" value="<?= e($ed['usage_max']??0) ?>"></div>
      </div>
      <div class="frm-row">
        <div class="frm-g"><label class="frm-l">Date d'expiration (vide = sans limite)</label>
          <input class="frm-i" type="date" name="expires" value="<?= e($ed['expires']??'') ?>"></div>
        <div class="frm-g"><label class="frm-l">Utilisations actuelles</label>
          <input class="frm-i" value="<?= (int)($ed['usage_ct']??0) ?>" disabled style="background:var(--cr)"></div>
      </div>
      <!-- Portée -->
      <div class="frm-g"><label class="frm-l">Applicable à</label>
        <select class="frm-sel" name="scope" id="promo_scope" onchange="toggleScope()">
          <option value="all"      <?= ($ed['scope']??'all')==='all'     ?'selected':'' ?>>Tout le panier</option>
          <option value="product"  <?= ($ed['scope']??'all')==='product' ?'selected':'' ?>>Un produit spécifique</option>
          <option value="workshop" <?= ($ed['scope']??'all')==='workshop'?'selected':'' ?>>Un atelier spécifique</option>
        </select></div>
      <div id="scope_product" style="display:<?= ($ed['scope']??'all')==='product'?'block':'none' ?>">
        <div class="frm-g"><label class="frm-l">Produit concerné</label>
          <select class="frm-sel" name="scope_id">
            <option value="">— Choisir un produit —</option>
            <?php foreach ($prds as $p): ?>
            <option value="<?= e($p['id']) ?>" <?= ($ed['scope_id']??'')===$p['id']?'selected':'' ?>><?= e($p['name']) ?></option>
            <?php endforeach; ?>
          </select></div>
      </div>
      <div id="scope_workshop" style="display:<?= ($ed['scope']??'all')==='workshop'?'block':'none' ?>">
        <div class="frm-g"><label class="frm-l">Atelier concerné</label>
          <select class="frm-sel" name="scope_id">
            <option value="">— Choisir un atelier —</option>
            <?php foreach ($wks as $w): ?>
            <option value="<?= e($w['id']) ?>" <?= ($ed['scope_id']??'')===$w['id']?'selected':'' ?>><?= e($w['name']) ?></option>
            <?php endforeach; ?>
          </select></div>
      </div>
      <button type="submit" class="save-btn">💾 Enregistrer le code</button>
    </div>
  </form>
</div>
<script>
function toggleType() {
  const t = document.getElementById('promo_type').value;
  document.getElementById('type_suffix').textContent = t==='percent'?'(%)':'(€)';
}
function toggleScope() {
  const s = document.getElementById('promo_scope').value;
  document.getElementById('scope_product').style.display  = s==='product' ?'block':'none';
  document.getElementById('scope_workshop').style.display = s==='workshop'?'block':'none';
}
</script>

<?php else: ?>
<div class="adm-top">
  <div><h2>Codes promotionnels</h2><p><?= count($promos) ?> code(s)</p></div>
  <div class="adm-acts"><a href="./promos.php?a=new" class="a-btn prim">+ Nouveau code</a></div>
</div>
<div class="adm-content">
  <?php if (!$promos): ?>
  <div class="empty-st"><div class="empty-ico">🏷</div><h4>Aucun code promo</h4></div>
  <?php else: ?>
  <div class="tbl-wrap"><table>
    <thead><tr><th>Code</th><th>Réduction</th><th>Portée</th><th>Min.</th><th>Exp.</th><th>Usages</th><th>Statut</th><th>—</th></tr></thead>
    <tbody>
    <?php foreach ($promos as $p):
      $scope_names = ['all'=>'Tout le panier','product'=>'Produit','workshop'=>'Atelier'];
      $expired = ($p['expires'] ?? '') && $p['expires'] < date('Y-m-d');
      $maxed   = ($p['usage_max']??0) > 0 && ($p['usage_ct']??0) >= $p['usage_max'];
    ?>
    <tr>
      <td style="font-family:monospace;font-weight:700;font-size:1rem;color:var(--or)"><?= e($p['code']) ?></td>
      <td style="font-weight:600">
        <?= $p['type']==='percent' ? '-'.($p['value']+0).'%' : '-'.number_format($p['value'],2,',',' ').' €' ?>
      </td>
      <td style="font-size:.8rem"><?= $scope_names[$p['scope']??'all'] ?></td>
      <td style="font-size:.8rem"><?= ($p['min_order']??0)>0 ? number_format($p['min_order'],2,',',' ').' €' : '—' ?></td>
      <td style="font-size:.78rem;color:<?= $expired?'var(--or)':'var(--gy2)' ?>"><?= ($p['expires']??'') ?: '∞' ?></td>
      <td style="font-size:.82rem"><?= (int)($p['usage_ct']??0) ?><?= ($p['usage_max']??0)>0?' / '.$p['usage_max']:' / ∞' ?></td>
      <td>
        <?php if ($expired): ?><span class="bdg bdg-r">Expiré</span>
        <?php elseif ($maxed): ?><span class="bdg bdg-gy">Épuisé</span>
        <?php elseif ($p['active']??true): ?><span class="bdg bdg-g">✓ Actif</span>
        <?php else: ?><span class="bdg bdg-gy">Inactif</span><?php endif; ?>
      </td>
      <td><div class="act-btns">
        <a href="./promos.php?a=edit&id=<?= e($p['id']) ?>" class="act-btn">Éditer</a>
        <form method="POST" style="display:inline"><?= csrf_field() ?>
          <input type="hidden" name="pa" value="toggle"><input type="hidden" name="did" value="<?= e($p['id']) ?>">
          <button type="submit" class="act-btn"><?= ($p['active']??true)?'Désactiver':'Activer' ?></button>
        </form>
        <form method="POST" style="display:inline"><?= csrf_field() ?>
          <input type="hidden" name="pa" value="delete"><input type="hidden" name="did" value="<?= e($p['id']) ?>">
          <button type="submit" class="act-btn del" data-confirm="Supprimer le code <?= e($p['code']) ?> ?">✕</button>
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
