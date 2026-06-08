<?php
require_once __DIR__ . '/../includes/config.php';
require_auth();

$arts = db('articles');
$act  = clean($_GET['a'] ?? 'list', 10);
$id   = clean($_GET['id'] ?? '', 20);
$errs = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $pa = clean($_POST['pa'] ?? '', 10);

    if ($pa === 'delete') {
        $did = clean($_POST['did'] ?? '', 20);
        db_save('articles', array_values(array_filter($arts, fn($x) => $x['id'] !== $did)));
        header('Location: ./articles.php?del=1'); exit;
    }

    if ($pa === 'save') {
        $eid      = clean($_POST['eid']     ?? '', 20);
        $title    = clean($_POST['title']   ?? '', 200);
        $slug     = clean($_POST['slug']    ?? '', 200) ?: slug($title);
        $excerpt  = clean($_POST['excerpt'] ?? '', 500);
        $author   = clean($_POST['author']  ?? '', 100);
        $cat      = clean($_POST['cat']     ?? 'Conseil', 60);
        $page     = in_array($_POST['page'] ?? '', ['blog','home','atelier','shop'])
                    ? $_POST['page'] : 'blog';
        $pub      = isset($_POST['pub']);
        $feat     = isset($_POST['feat']);
        $image    = clean($_POST['image'] ?? '', 500);
        $emoji    = clean($_POST['emoji'] ?? '📖', 8);
        // Contenu HTML de l'éditeur admin (source de confiance interne)
        $content  = $_POST['content'] ?? '';

        if (!$title) $errs[] = 'Le titre est requis.';
        foreach ($arts as $x) {
            if ($x['slug'] === $slug && $x['id'] !== $eid) {
                $errs[] = 'Ce slug est déjà utilisé.'; break;
            }
        }

        if (!$errs) {
            $row = [
                'title'     => $title,
                'slug'      => $slug,
                'excerpt'   => $excerpt,
                'author'    => $author,
                'cat'       => $cat,
                'page'      => $page,
                'published' => $pub,
                'featured'  => $feat,
                'content'   => $content,
                'image'     => $image,
                'emoji'     => $emoji,
            ];
            if ($eid) {
                foreach ($arts as &$x) {
                    if ($x['id'] === $eid) {
                        $x = array_merge($x, $row);
                        $x['updated'] = date('Y-m-d');
                    }
                }
                unset($x);
            } else {
                $row['id']      = new_id();
                $row['created'] = date('Y-m-d');
                $row['updated'] = date('Y-m-d');
                $arts[]         = $row;
            }
            db_save('articles', $arts);
            header('Location: ./articles.php?saved=1'); exit;
        }
    }
}

$ed = null;
if ($act === 'edit' && $id) {
    foreach ($arts as $x) { if ($x['id'] === $id) { $ed = $x; break; } }
}

$at = 'Articles'; $cur_adm = 'art';
include ROOT . '/admin/inc/layout.php';
?>

<?php if ($act === 'new' || $act === 'edit'): ?>
<div class="adm-top">
  <div><h2><?= $act === 'new' ? 'Nouvel article' : 'Modifier l\'article' ?></h2>
  <p><?= $ed ? e($ed['title']) : 'Rédiger un nouvel article' ?></p></div>
  <div class="adm-acts">
    <?php if ($ed && ($ed['published'] ?? false)): ?>
    <a href="../pages/article.php?s=<?= e($ed['slug']) ?>" target="_blank" class="a-btn">👁 Voir</a>
    <?php endif; ?>
    <a href="./articles.php" class="a-btn">← Retour</a>
  </div>
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
          <div class="frm-g">
            <label class="frm-l">Titre *</label>
            <input class="frm-i" id="f_title" name="title" required maxlength="200"
              value="<?= e($ed['title'] ?? '') ?>" placeholder="Titre de l'article">
          </div>
          <div class="frm-g">
            <label class="frm-l">Slug URL</label>
            <input class="frm-i" id="f_slug" name="slug" maxlength="200"
              value="<?= e($ed['slug'] ?? '') ?>">
            <small style="font-size:.69rem;color:var(--gy2);margin-top:.2rem;display:block">
              /pages/article.php?s=<strong id="slugPreview"><?= e($ed['slug'] ?? '') ?></strong>
            </small>
          </div>
          <div class="frm-g">
            <label class="frm-l">Extrait</label>
            <textarea class="frm-i" name="excerpt" rows="3" maxlength="500"
              style="resize:vertical"><?= e($ed['excerpt'] ?? '') ?></textarea>
          </div>
          <div class="frm-g">
            <label class="frm-l">Image de l'article</label>
            <input type="hidden" name="image" id="fld_image" value="<?= e($ed['image']??'') ?>">
            <?php if (!empty($ed['image'])): ?>
            <img id="prev_image" src="<?= e($ed['image']) ?>" style="width:80px;height:56px;object-fit:cover;border-radius:8px;margin-bottom:.5rem">
            <?php else: ?>
            <img id="prev_image" style="display:none;width:80px;height:56px;object-fit:cover;border-radius:8px;margin-bottom:.5rem">
            <?php endif; ?>
            <div class="img-uploader" data-field="image" data-preview="prev_image"></div>
          </div>
          <div class="frm-g"><label class="frm-l">Emoji (si pas d'image)</label>
            <input class="frm-i" name="emoji" maxlength="8" value="<?= e($ed['emoji']??'📖') ?>" style="width:70px;text-align:center;font-size:1.3rem">
          </div>
          <div class="frm-g">
            <label class="frm-l">Contenu (HTML)</label>
            <div class="tbar">
              <?php foreach ([
                ['B','<strong>','</strong>'], ['I','<em>','</em>'],
                ['H2','<h2>','</h2>'],        ['H3','<h3>','</h3>'],
                ['§','<p>','</p>'],            ['—','<hr>',''],
              ] as [$lbl,$o,$c]): ?>
              <button type="button" class="tb" data-o="<?= e($o) ?>" data-c="<?= e($c) ?>"><?= $lbl ?></button>
              <?php endforeach; ?>
              <button type="button" class="tb" data-cmd="link">🔗</button>
            </div>
            <textarea class="editor" id="f_content" name="content"
              rows="20"><?= htmlspecialchars($ed['content'] ?? '') ?></textarea>
          </div>
        </div>
      </div>
      <div>
        <div class="editor-wrap" style="margin-bottom:1rem">
          <div class="tog-wrap">
            <label class="tog">
              <input type="checkbox" name="pub" <?= ($ed['published'] ?? false) ? 'checked' : '' ?>>
              <span class="tog-sl"></span>
            </label>
            <span class="tog-lbl">Publié (visible sur le site)</span>
          </div>
          <div class="tog-wrap" style="margin-top:.5rem">
            <label class="tog">
              <input type="checkbox" name="feat" <?= ($ed['featured'] ?? false) ? 'checked' : '' ?>>
              <span class="tog-sl"></span>
            </label>
            <span class="tog-lbl">Article mis en avant</span>
          </div>
        </div>
        <div class="editor-wrap">
          <div class="frm-g">
            <label class="frm-l">Page d'affichage</label>
            <select class="frm-sel" name="page">
              <?php foreach (['blog'=>'Journal (Blog)','home'=>'Accueil','atelier'=>'Ateliers','shop'=>'Boutique'] as $v=>$l): ?>
              <option value="<?= $v ?>" <?= ($ed['page'] ?? 'blog') === $v ? 'selected' : '' ?>><?= $l ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="frm-g">
            <label class="frm-l">Catégorie</label>
            <select class="frm-sel" name="cat">
              <?php foreach (['Conseil','Voyage','Éducation','Actualité','Recette','Événement'] as $c): ?>
              <option <?= ($ed['cat'] ?? '') === $c ? 'selected' : '' ?>><?= $c ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="frm-g">
            <label class="frm-l">Auteur</label>
            <input class="frm-i" name="author" maxlength="100"
              value="<?= e($ed['author'] ?? "L'équipe Café Maison") ?>">
          </div>
          <button type="submit" class="save-btn">💾 Enregistrer l'article</button>
        </div>
      </div>
    </div>
  </form>
</div>
<script>
document.querySelectorAll('.tb').forEach(btn => {
  btn.addEventListener('click', () => {
    const area = document.getElementById('f_content');
    const s = area.selectionStart, e = area.selectionEnd;
    const sel = area.value.substring(s, e) || 'texte';
    let ins = '';
    if (btn.dataset.cmd === 'link') {
      const u = prompt('URL :', 'https://'); if (!u) return;
      ins = `<a href="${u}">${sel}</a>`;
    } else {
      ins = btn.dataset.o + sel + btn.dataset.c;
    }
    area.value = area.value.substring(0, s) + ins + area.value.substring(e);
    area.focus();
  });
});

function updateArtPreview() {
  const img = document.getElementById('a_image').value;
  const emoji = document.getElementById('a_emoji').value || '📖';
  document.getElementById('artPreview').innerHTML = img
    ? `<img src="${img}" style="width:100%;height:100%;object-fit:cover" onerror="this.remove()">`
    : emoji;
}
</script>

<?php else: ?>
<div class="adm-top">
  <div><h2>Articles</h2><p><?= count($arts) ?> article(s)</p></div>
  <div class="adm-acts">
    <a href="./articles.php?a=new" class="a-btn prim">+ Nouvel article</a>
  </div>
</div>
<div class="adm-content">
  <?php if (!$arts): ?>
  <div class="empty-st"><div class="empty-ico">📝</div><h4>Aucun article</h4></div>
  <?php else: ?>
  <div class="tbl-wrap">
    <table>
      <thead>
        <tr><th>Titre</th><th>Page</th><th>Catégorie</th><th>Auteur</th><th>Statut</th><th>Date</th><th>Actions</th></tr>
      </thead>
      <tbody>
        <?php foreach (array_reverse($arts) as $a): ?>
        <tr>
          <td style="font-weight:500;max-width:260px">
            <?= e($a['title']) ?>
            <?php if ($a['featured'] ?? false): ?>
            <span class="bdg bdg-o" style="margin-left:.3rem">★</span>
            <?php endif; ?>
          </td>
          <td style="font-size:.82rem"><?= e(ucfirst($a['page'] ?? 'blog')) ?></td>
          <td style="font-size:.82rem;color:var(--gy2)"><?= e($a['cat'] ?? '') ?></td>
          <td style="font-size:.78rem;color:var(--gy2)"><?= e($a['author'] ?? '') ?></td>
          <td><span class="bdg <?= ($a['published'] ?? false) ? 'bdg-g' : 'bdg-gy' ?>">
            <?= ($a['published'] ?? false) ? 'Publié' : 'Brouillon' ?>
          </span></td>
          <td style="font-size:.78rem;color:var(--gy2)"><?= e($a['created'] ?? '') ?></td>
          <td>
            <div class="act-btns">
              <a href="./articles.php?a=edit&id=<?= e($a['id']) ?>" class="act-btn">Éditer</a>
              <?php if ($a['published'] ?? false): ?>
              <a href="../pages/article.php?s=<?= e($a['slug']) ?>" target="_blank" class="act-btn">Voir</a>
              <?php endif; ?>
              <form method="POST" style="display:inline">
                <?= csrf_field() ?>
                <input type="hidden" name="pa"  value="delete">
                <input type="hidden" name="did" value="<?= e($a['id']) ?>">
                <button type="submit" class="act-btn del"
                  data-confirm="Supprimer « <?= e(addslashes($a['title'])) ?> » ?">✕</button>
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
