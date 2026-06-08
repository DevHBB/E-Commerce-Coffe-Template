<?php
require_once __DIR__ . '/../includes/config.php';
require_auth();

$prds      = array_values(array_filter(db('products'), fn($p) => $p['active'] ?? true));
$feat_raw  = db('featured');
$feat_en   = !empty($feat_raw['enabled']);
$feat_mode = $feat_raw['mode']       ?? 'manual';
$feat_n    = (int)($feat_raw['count']?? 3);
$show_idx  = !empty($feat_raw['show_index'] ?? true);
$show_shop = !empty($feat_raw['show_shop']  ?? false);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();

    $new_en    = isset($_POST['enabled']);
    $new_mode  = in_array($_POST['mode']??'manual', ['manual','auto'], true) ? $_POST['mode'] : 'manual';
    $new_n     = max(1, min(12, (int)($_POST['count'] ?? 3)));
    $new_idx   = isset($_POST['show_index']);
    $new_shop  = isset($_POST['show_shop']);
    $selected  = array_values(array_filter($_POST['selected'] ?? [], fn($x) => trim($x) !== ''));

    // Sauvegarder les options dans featured.json
    db_save('featured', [
        'enabled'             => $new_en,
        'mode'                => $new_mode,
        'count'               => $new_n,
        'show_index'          => $new_idx,
        'show_shop'           => $new_shop,
        'hero_product_enabled'=> isset($_POST['hero_product_enabled']),
        'hero_product_id'     => clean($_POST['hero_product_id'] ?? '', 30),
        'hero_label'          => clean($_POST['hero_label'] ?? 'Sélection du mois', 60),
        'hero_notes'          => clean($_POST['hero_notes'] ?? '', 120),
        'hero_btn_text'       => clean($_POST['hero_btn_text'] ?? '+ Panier', 40),
    ]);

    // Marquer les produits sélectionnés dans products.json
    $all_prds = db('products');
    foreach ($all_prds as &$p) {
        $p['featured'] = in_array($p['id'], $selected);
    }
    unset($p);
    db_save('products', $all_prds);

    admin_log('featured_save',
        'featured=' . ($new_en?'actif':'inactif') .
        ' mode=' . $new_mode .
        ' index=' . ($new_idx?'oui':'non') .
        ' shop=' . ($new_shop?'oui':'non') .
        ' sel=' . count($selected)
    );

    // Recharger
    $feat_raw  = db('featured');
    $feat_en   = $new_en; $feat_mode = $new_mode; $feat_n = $new_n;
    $show_idx  = $new_idx; $show_shop = $new_shop;
    $prds      = array_values(array_filter(db('products'), fn($p) => $p['active'] ?? true));
    $ok        = true;
}

$at = 'Mise en avant'; $cur_adm = 'featured';
include ROOT . '/admin/inc/layout.php';
?>
<div class="adm-top">
  <div>
    <h2>⭐ Produits mis en avant</h2>
    <p>Configurez quels produits apparaissent en priorité et où</p>
  </div>
</div>
<div class="adm-content">
  <?php if (isset($ok)): ?>
  <div class="alert-ok" style="margin-bottom:1.2rem">✓ Enregistré !</div>
  <?php endif; ?>

  <form method="POST" action="./featured.php">
    <?= csrf_field() ?>

    <div style="display:grid;grid-template-columns:1fr 1fr;gap:1.2rem;margin-bottom:1.2rem">

      <!-- Options principales -->
      <div class="editor-wrap">
        <h4 style="font-family:var(--f1);font-size:1.1rem;margin-bottom:1rem">⚙️ Options</h4>

        <div class="tog-wrap" style="margin-bottom:1rem">
          <label class="tog">
            <input type="checkbox" name="enabled" <?= $feat_en ? 'checked' : '' ?>>
            <span class="tog-sl"></span>
          </label>
          <span class="tog-lbl" style="font-weight:600">Activer la mise en avant</span>
        </div>

        <div class="frm-g">
          <label class="frm-l">Mode de sélection</label>
          <select name="mode" class="frm-sel">
            <option value="manual" <?= $feat_mode === 'manual' ? 'selected' : '' ?>>✋ Manuel — je choisis ci-dessous</option>
            <option value="auto"   <?= $feat_mode === 'auto'   ? 'selected' : '' ?>>🤖 Auto — produits les + vendus</option>
          </select>
        </div>

        <div class="frm-g">
          <label class="frm-l">Nombre de produits à afficher</label>
          <input type="number" name="count" class="frm-i" min="1" max="12" value="<?= $feat_n ?>" style="max-width:80px">
        </div>
      </div>

      <!-- Où afficher -->
      <div class="editor-wrap">
        <h4 style="font-family:var(--f1);font-size:1.1rem;margin-bottom:1rem">📍 Où afficher</h4>

        <div class="tog-wrap" style="margin-bottom:.8rem">
          <label class="tog">
            <input type="checkbox" name="show_index" <?= $show_idx ? 'checked' : '' ?>>
            <span class="tog-sl"></span>
          </label>
          <span class="tog-lbl">
            <strong>Page d'accueil</strong>
            <small style="display:block;color:var(--gy2);margin-top:.1rem">Section produits sur l'accueil</small>
          </span>
        </div>

        <div class="tog-wrap">
          <label class="tog">
            <input type="checkbox" name="show_shop" <?= $show_shop ? 'checked' : '' ?>>
            <span class="tog-sl"></span>
          </label>
          <span class="tog-lbl">
            <strong>Page boutique</strong>
            <small style="display:block;color:var(--gy2);margin-top:.1rem">Badge ⭐ + triés en premier dans la boutique</small>
          </span>
        </div>

        <div style="margin-top:1rem;padding:.7rem;background:var(--cr);border-radius:var(--r);font-size:.78rem;color:var(--gy)">
          <?php if ($feat_en && ($show_idx || $show_shop)): ?>
          <strong style="color:var(--vr2)">● Actif</strong> — affiché sur :
          <?= $show_idx ? ' Accueil' : '' ?><?= ($show_idx && $show_shop) ? ' +' : '' ?><?= $show_shop ? ' Boutique' : '' ?>
          <?php else: ?>
          Désactivé ou aucune page sélectionnée
          <?php endif; ?>
        </div>
      </div>
    </div>

    <!-- Sélection manuelle -->
    <div class="editor-wrap" style="margin-bottom:1.2rem">
      <h4 style="font-family:var(--f1);font-size:1.1rem;margin-bottom:.3rem">
        ✋ Sélection manuelle
        <small style="font-weight:400;font-size:.75rem;color:var(--gy2)"> — utilisé si Mode = Manuel</small>
      </h4>
      <p style="font-size:.8rem;color:var(--gy2);margin-bottom:.8rem">Cochez les produits à mettre en avant.</p>
      <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(220px,1fr));gap:.5rem">
        <?php foreach ($prds as $p): $is_sel = !empty($p['featured']); ?>
        <label style="
          display:flex;align-items:center;gap:.7rem;padding:.6rem .8rem;
          border:2px solid <?= $is_sel ? 'var(--or)' : 'var(--gy3)' ?>;
          border-radius:var(--r);cursor:pointer;
          background:<?= $is_sel ? 'rgba(200,86,30,.06)' : 'var(--wh)' ?>;
          transition:all .15s;font-size:.85rem">
          <input type="checkbox" name="selected[]" value="<?= e($p['id']) ?>"
            <?= $is_sel ? 'checked' : '' ?>
            onchange="var l=this.closest('label');l.style.borderColor=this.checked?'var(--or)':'var(--gy3)';l.style.background=this.checked?'rgba(200,86,30,.06)':'var(--wh)'">
          <span style="font-size:1.3rem"><?= e($p['emoji'] ?? '☕') ?></span>
          <span style="flex:1">
            <strong><?= e($p['name']) ?></strong><br>
            <small style="color:var(--gy2)"><?= number_format((float)($p['price'] ?? 0), 2, ',', ' ') ?> €</small>
          </span>
          <?php if ($is_sel): ?>
          <span style="color:var(--or);font-size:.85rem">⭐</span>
          <?php endif; ?>
        </label>
        <?php endforeach; ?>
      </div>
    </div>

    <!-- SECTION CAFÉ HERO -->
    <div class="editor-wrap" style="margin-bottom:1.2rem;border:2px solid rgba(200,86,30,.2)">
      <div style="display:flex;align-items:center;gap:.8rem;margin-bottom:.8rem">
        <h4 style="font-family:var(--f1);font-size:1.1rem;margin:0">🦸 Café Hero</h4>
        <span style="font-size:.75rem;color:var(--gy2)">Le produit affiché dans la carte centrale du Hero de l'accueil</span>
      </div>
      <div style="background:var(--cr);border-radius:var(--r);padding:.8rem 1rem;margin-bottom:1rem;font-size:.78rem;color:var(--gy2)">
        ℹ️ Le <strong>Hero</strong> est la grande section d'accueil en haut de page avec la carte produit tournante.
        Activez cette option pour choisir quel café y apparaît, personnaliser le texte et le bouton.
      </div>

      <div class="tog-wrap" style="margin-bottom:.8rem">
        <label class="tog">
          <input type="checkbox" name="hero_product_enabled" <?= !empty($feat_raw['hero_product_enabled']) ? 'checked' : '' ?>>
          <span class="tog-sl"></span>
        </label>
        <span class="tog-lbl" style="font-weight:600">Activer le Café Hero</span>
        <small style="display:block;color:var(--gy2);margin-left:48px;margin-top:.15rem">Si désactivé, le premier produit actif s'affiche par défaut</small>
      </div>

      <div class="frm-g">
        <label class="frm-l">Produit à afficher dans le Hero</label>
        <select name="hero_product_id" class="frm-sel">
          <?php foreach ($prds as $hp): ?>
          <option value="<?= e($hp['id']) ?>" <?= ($feat_raw['hero_product_id']??'') === $hp['id'] ? 'selected' : '' ?>>
            <?= e($hp['emoji']??'☕') ?> <?= e($hp['name']) ?> — <?= number_format((float)($hp['price']??0),2,',',' ') ?> €
          </option>
          <?php endforeach; ?>
        </select>
      </div>

      <div class="frm-row">
        <div class="frm-g">
          <label class="frm-l">Libellé badge <small style="color:var(--gy2)">(ex: Sélection du mois)</small></label>
          <input class="frm-i" name="hero_label" maxlength="60" value="<?= e($feat_raw['hero_label'] ?? 'Sélection du mois') ?>" placeholder="Sélection du mois">
        </div>
        <div class="frm-g">
          <label class="frm-l">Texte bouton</label>
          <input class="frm-i" name="hero_btn_text" maxlength="40" value="<?= e($feat_raw['hero_btn_text'] ?? '+ Panier') ?>" placeholder="+ Panier">
        </div>
      </div>

      <div class="frm-g">
        <label class="frm-l">Notes de dégustation <small style="color:var(--gy2)">(affiché sous le nom, laisser vide pour masquer)</small></label>
        <input class="frm-i" name="hero_notes" maxlength="120" value="<?= e($feat_raw['hero_notes'] ?? '') ?>" placeholder="🍓 Fraise · 🍫 Cacao · 🌸 Jasmin">
      </div>
    </div>

    <button type="submit" class="save-btn" style="width:100%;justify-content:center;padding:.8rem;font-size:1rem">
      💾 Enregistrer
    </button>
  </form>
</div>
<?php include ROOT . '/admin/inc/layout_end.php'; ?>
