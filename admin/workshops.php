<?php
require_once __DIR__ . '/../includes/config.php';
require_auth();

$wks  = db('workshops');
$act  = clean($_GET['a'] ?? 'list', 10);
$id   = clean($_GET['id'] ?? '', 20);
$errs = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $pa = clean($_POST['pa'] ?? '', 10);

    if ($pa === 'delete') {
        $did = clean($_POST['did'] ?? '', 20);
        db_save('workshops', array_values(array_filter($wks, fn($x) => $x['id'] !== $did)));
        header('Location: ./workshops.php?del=1'); exit;
    }

    if ($pa === 'save') {
        $eid    = clean($_POST['eid']      ?? '', 20);
        $name   = clean($_POST['name']     ?? '', 120);
        $slug   = clean($_POST['slug']     ?? '', 120) ?: slug($name);
        $desc   = clean($_POST['desc']     ?? '', 600);
        $duration = clean($_POST['duration'] ?? '', 20);
        $schedule = clean($_POST['schedule'] ?? '', 120);
        $level    = clean($_POST['level']    ?? 'Tous niveaux', 30);
        $price    = clean_float($_POST['price'] ?? 0, 0, 9999);
        $capacity = clean_int($_POST['capacity'] ?? 8, 1, 100);
        $emoji         = clean($_POST['emoji']         ?? '☕', 8);
        $image         = clean($_POST['image']         ?? '', 500);
        $delivery_mode = in_array($_POST['delivery_mode'] ?? '', ['pickup','both','delivery']) ? $_POST['delivery_mode'] : 'pickup';
        $active        = isset($_POST['active']);

        if (!$name)      $errs[] = 'Le nom est requis.';
        if ($price <= 0) $errs[] = 'Le prix doit être supérieur à 0.';

        if (!$errs) {
            $row = [
                'name'          => $name,
                'slug'          => $slug,
                'desc'          => $desc,
                'duration'      => $duration,
                'schedule'      => $schedule,
                'level'         => $level,
                'price'         => $price,
                'capacity'      => $capacity,
                'emoji'         => $emoji,
                'image'         => $image,
                'delivery_mode' => $delivery_mode,
                'active'        => $active,
            ];
            if ($eid) {
                foreach ($wks as &$x) {
                    if ($x['id'] === $eid) { $x = array_merge($x, $row); }
                }
                unset($x);
            } else {
                $row['id'] = new_id();
                $wks[]     = $row;
            }
            db_save('workshops', $wks);
            header('Location: ./workshops.php?saved=1'); exit;
        }
    }
}

$ed = null;
if ($act === 'edit' && $id) {
    foreach ($wks as $x) { if ($x['id'] === $id) { $ed = $x; break; } }
}

$at = 'Ateliers'; $cur_adm = 'wk';
include ROOT . '/admin/inc/layout.php';
?>

<?php if ($act === 'new' || $act === 'edit'): ?>
<div class="adm-top">
  <div><h2><?= $act === 'new' ? 'Nouvel atelier' : 'Modifier l\'atelier' ?></h2>
  <p><?= $ed ? e($ed['name']) : 'Créer un nouvel atelier' ?></p></div>
  <div class="adm-acts"><a href="./workshops.php" class="a-btn">← Retour</a></div>
</div>
<div class="adm-content">
  <?php if ($errs): ?>
  <div style="background:rgba(200,86,30,.1);border:1px solid var(--or);color:var(--or3);padding:.9rem 1rem;border-radius:var(--r);margin-bottom:1.2rem">
    <?php foreach ($errs as $e) echo '<div>✗ ' . htmlspecialchars($e) . '</div>'; ?>
  </div>
  <?php endif; ?>
  <form method="POST">
    <?= csrf_field() ?>
    <input type="hidden" name="pa"  value="save">
    <input type="hidden" name="eid" value="<?= e($ed['id'] ?? '') ?>">
    <div class="edit-grid">
      <div>
        <div class="editor-wrap">
          <div class="frm-row">
            <div class="frm-g">
              <label class="frm-l">Nom de l'atelier *</label>
              <input class="frm-i" id="f_name" name="name" required maxlength="120"
                value="<?= e($ed['name'] ?? '') ?>" placeholder="Ex: Introduction au V60">
            </div>
            <div class="frm-g">
              <label class="frm-l">Slug URL</label>
              <input class="frm-i" id="f_slug" name="slug" maxlength="120"
                value="<?= e($ed['slug'] ?? '') ?>">
            </div>
          </div>
          <div class="frm-g">
            <label class="frm-l">Description</label>
            <textarea class="frm-i" name="desc" rows="5" maxlength="600"
              style="resize:vertical"><?= e($ed['desc'] ?? '') ?></textarea>
          </div>
          <div class="frm-g">
            <label class="frm-l">Planning / Horaires</label>
            <input class="frm-i" name="schedule" maxlength="120"
              value="<?= e($ed['schedule'] ?? $ed['sched'] ?? '') ?>" placeholder="Ex: Tous les samedis 10h–12h">
          </div>
        </div>
      </div>
      <div>
        <div class="editor-wrap" style="margin-bottom:1rem">
          <div class="tog-wrap">
            <label class="tog">
              <input type="checkbox" name="active" <?= ($ed['active'] ?? true) ? 'checked' : '' ?>>
              <span class="tog-sl"></span>
            </label>
            <span class="tog-lbl">Atelier actif (visible sur le site)</span>
          </div>
        </div>
        <div class="editor-wrap">
          <div class="frm-row">
            <div class="frm-g">
              <label class="frm-l">Prix (€) *</label>
              <input class="frm-i" type="number" name="price" step="0.01" min="0"
                required value="<?= e($ed['price'] ?? '') ?>">
            </div>
            <div class="frm-g">
              <label class="frm-l">Durée</label>
              <input class="frm-i" name="duration" maxlength="20"
                value="<?= e($ed['duration'] ?? $ed['dur'] ?? '') ?>" placeholder="Ex: 2h">
            </div>
          </div>
          <div class="frm-row">
            <div class="frm-g">
              <label class="frm-l">Niveau</label>
              <select class="frm-sel" name="level">
                <?php foreach (['Tous niveaux','Débutant','Intermédiaire','Avancé'] as $l): ?>
                <option <?= ($ed['level'] ?? 'Tous niveaux') === $l ? 'selected' : '' ?>><?= $l ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="frm-g">
              <label class="frm-l">Capacité max</label>
              <input class="frm-i" type="number" name="capacity" min="1" max="100"
                value="<?= e($ed['capacity'] ?? $ed['cap'] ?? 8) ?>">
            </div>
          </div>
          <div class="frm-g">
            <label class="frm-l">Image de l'atelier</label>
            <input type="hidden" name="image" id="fld_image" value="<?= e($ed['image']??'') ?>">
            <?php if (!empty($ed['image'])): ?>
            <img id="prev_image" src="<?= e($ed['image']) ?>" style="width:72px;height:56px;object-fit:cover;border-radius:8px;margin-bottom:.5rem">
            <?php else: ?>
            <img id="prev_image" style="display:none;width:72px;height:56px;object-fit:cover;border-radius:8px;margin-bottom:.5rem">
            <?php endif; ?>
            <div class="img-uploader" data-field="image" data-preview="prev_image"></div>
          </div>
          <div class="frm-g"><label class="frm-l">Emoji (si pas d'image)</label>
            <input class="frm-i" name="emoji" maxlength="8" value="<?= e($ed['emoji']??'☕') ?>" style="width:70px;text-align:center;font-size:1.3rem">
          </div>
          <div class="frm-g"><label class="frm-l">Mode de livraison / accès</label>
            <select class="frm-sel" name="delivery_mode">
              <option value="pickup"   <?= ($ed['delivery_mode']??'pickup')==='pickup'   ?'selected':'' ?>>Présentiel uniquement (retrait/venue)</option>
              <option value="both"     <?= ($ed['delivery_mode']??'pickup')==='both'     ?'selected':'' ?>>Présentiel + En ligne</option>
              <option value="delivery" <?= ($ed['delivery_mode']??'pickup')==='delivery' ?'selected':'' ?>>En ligne uniquement</option>
            </select></div>
          <button type="submit" class="save-btn">💾 Enregistrer l'atelier</button>
        </div>
      </div>
    </div>
  </form>
</div>

<?php else: ?>
<div class="adm-top">
  <div><h2>Ateliers</h2><p><?= count($wks) ?> atelier(s)</p></div>
  <div class="adm-acts">
    <a href="./workshops.php?a=new" class="a-btn prim">+ Nouvel atelier</a>
  </div>
</div>
<div class="adm-content">
  <?php if (!$wks): ?>
  <div class="empty-st"><div class="empty-ico">🎓</div><h4>Aucun atelier</h4></div>
  <?php else: ?>
  <div class="tbl-wrap">
    <table>
      <thead>
        <tr><th>Atelier</th><th>Niveau</th><th>Durée</th><th>Prix</th><th>Capacité</th><th>Statut</th><th>Actions</th></tr>
      </thead>
      <tbody>
        <?php foreach ($wks as $w): ?>
        <tr>
          <td style="font-weight:500"><?= e($w['emoji'] ?? '☕') ?> <?= e($w['name']) ?></td>
          <td style="font-size:.83rem;color:var(--gy2)"><?= e($w['level'] ?? '') ?></td>
          <td style="font-size:.83rem"><?= e($w['dur'] ?? '') ?></td>
          <td style="font-weight:600"><?= number_format($w['price'] ?? 0, 2, ',', ' ') ?> €</td>
          <td><?= (int)($w['cap'] ?? 0) ?> pers.</td>
          <td><span class="bdg <?= ($w['active'] ?? true) ? 'bdg-g' : 'bdg-gy' ?>">
            <?= ($w['active'] ?? true) ? 'Actif' : 'Inactif' ?>
          </span></td>
          <td>
            <div class="act-btns">
              <a href="./workshops.php?a=edit&id=<?= e($w['id']) ?>" class="act-btn">Éditer</a>
              <form method="POST" style="display:inline">
                <?= csrf_field() ?>
                <input type="hidden" name="pa"  value="delete">
                <input type="hidden" name="did" value="<?= e($w['id']) ?>">
                <button type="submit" class="act-btn del"
                  data-confirm="Supprimer « <?= e(addslashes($w['name'])) ?> » ?">✕</button>
              </form>
            </div>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?php endif; ?>
</div>
<?php endif; ?>
<?php include ROOT . '/admin/inc/layout_end.php'; ?>
