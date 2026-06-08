<?php
require_once __DIR__ . '/../includes/config.php';
require_auth();

$quizzes = db('quizzes');
$cats    = db('categories');
$prds    = array_filter(db('products'), fn($p) => $p['active'] ?? true);
$act     = clean($_GET['a'] ?? 'list', 10);
$id      = clean($_GET['id'] ?? '', 30);
$errs    = [];

$cat_idx = [];
foreach ($cats as $c) $cat_idx[$c['id']] = $c;
$prd_arr = array_values($prds);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $pa = clean($_POST['pa'] ?? '', 10);

    if ($pa === 'delete') {
        db_save('quizzes', array_values(array_filter($quizzes, fn($q) => $q['id'] !== clean($_POST['did']??'',30))));
        header('Location: ./quizzes.php?del=1'); exit;
    }

    if ($pa === 'save') {
        $eid    = clean($_POST['eid']    ?? '', 30);
        $title  = clean($_POST['title']  ?? '', 150);
        $sub    = clean($_POST['subtitle']?? '', 300);
        $cat_id = clean($_POST['cat_id'] ?? '', 40);
        $active = isset($_POST['active']);

        // Questions : texte + options (séparées par |)
        $questions = [];
        foreach ($_POST['questions'] ?? [] as $qraw) {
            $qtxt  = clean($qraw['q'] ?? '', 300);
            $qopts = array_values(array_filter(array_map('trim', explode('|', $qraw['opts'] ?? ''))));
            if ($qtxt && count($qopts) >= 2) {
                $questions[] = ['q' => $qtxt, 'opts' => $qopts];
            }
        }

        // Profils résultats
        $profiles = [];
        foreach ($_POST['profiles'] ?? [] as $praw) {
            $ptitle = clean($praw['title'] ?? '', 80);
            if (!$ptitle) continue;
            $profiles[] = [
                'id'       => 'p_' . bin2hex(random_bytes(4)),
                'title'    => $ptitle,
                'emoji'    => clean($praw['emoji']   ?? '☕', 8),
                'desc'     => clean($praw['desc']    ?? '', 500),
                'cta'      => clean($praw['cta']     ?? 'Voir la sélection', 100),
                'cta_url'  => clean($praw['cta_url'] ?? 'shop.php', 200),
                'color'    => clean($praw['color']   ?? '#C8561E', 20),
                'products' => array_values(array_filter(explode(',', $praw['products'] ?? ''))),
            ];
        }

        if (!$title)              $errs[] = 'Le titre est requis.';
        if (!$questions)          $errs[] = 'Au moins une question est requise.';
        if (!$profiles)           $errs[] = 'Au moins un profil résultat est requis.';

        if (!$errs) {
            $row = [
                'title'     => $title,
                'subtitle'  => $sub,
                'cat_id'    => $cat_id,
                'active'    => $active,
                'questions' => $questions,
                'profiles'  => $profiles,
            ];
            if ($eid) {
                foreach ($quizzes as &$q) {
                    if ($q['id'] === $eid) $q = array_merge($q, $row);
                } unset($q);
            } else {
                $row['id'] = 'qz_' . new_id();
                $quizzes[] = $row;
            }
            db_save('quizzes', $quizzes);
            admin_log('quiz_save', "Quiz '$title' enregistré");
            header('Location: ./quizzes.php?saved=1'); exit;
        }
    }
}

$ed = null;
if ($act === 'edit' && $id) {
    foreach ($quizzes as $q) { if ($q['id'] === $id) { $ed = $q; break; } }
}

$at = 'Quiz guidés'; $cur_adm = 'quizzes';
include ROOT . '/admin/inc/layout.php';
?>

<?php if ($act === 'new' || $act === 'edit'): ?>
<div class="adm-top">
  <div><h2><?= $act === 'new' ? 'Nouveau quiz' : 'Modifier le quiz' ?></h2></div>
  <div class="adm-acts">
    <?php if ($ed && $ed['active']): ?><a href="../pages/quiz.php?id=<?= e($ed['id']) ?>" target="_blank" class="a-btn">👁 Voir</a><?php endif; ?>
    <a href="./quizzes.php" class="a-btn">← Retour</a>
  </div>
</div>
<div class="adm-content">
  <!-- AIDE -->
  <div style="background:rgba(42,76,30,.07);border:1px solid rgba(42,76,30,.2);border-radius:var(--r);padding:1rem 1.2rem;margin-bottom:1.5rem;font-size:.84rem;color:var(--gy)">
    <strong style="color:var(--bk)">💡 Comment fonctionne le quiz ?</strong><br>
    1. Vous créez des <strong>questions</strong> avec des options de réponse<br>
    2. Vous créez des <strong>profils résultats</strong> (ex: "L'Amateur", "L'Explorateur", "L'Intenso")<br>
    3. <strong>Règle d'attribution :</strong> la 1ère option de chaque question vote pour le 1er profil, la 2ème option vote pour le 2ème profil, etc.<br>
    &nbsp;&nbsp;&nbsp;→ Si vous avez 3 profils et 4 options par question : A→Profil1, B→Profil2, C→Profil3, D→Profil1<br>
    4. À la fin, le profil avec le plus de votes gagne et ses produits recommandés sont affichés<br>
    <strong style="color:var(--or)">Conseil :</strong> Ordonnez vos options du plus doux au plus intense pour que Profil1=doux, Profil2=équilibré, Profil3=intense<br>
    <strong>Exemple :</strong> Question: <code>Quelle intensité ?</code> | Options: <code>Légère|Équilibrée|Intense</code> → 3 profils = parfait !
  </div>

  <?php if ($errs): ?><div class="alert-err"><?php foreach($errs as $e) echo '<div>✗ '.htmlspecialchars($e).'</div>'; ?></div><?php endif; ?>

  <form method="POST">
    <?= csrf_field() ?>
    <input type="hidden" name="pa" value="save">
    <input type="hidden" name="eid" value="<?= e($ed['id'] ?? '') ?>">

    <!-- Infos générales -->
    <div class="editor-wrap" style="margin-bottom:1.2rem">
      <h4 style="font-family:var(--f1);font-size:1.1rem;margin-bottom:1rem">⚙️ Informations du quiz</h4>
      <div class="frm-row">
        <div class="frm-g">
          <label class="frm-l">Titre du quiz *</label>
          <input class="frm-i" name="title" required maxlength="150" value="<?= e($ed['title'] ?? '') ?>" placeholder="Ex: Quel café vous correspond ?">
        </div>
        <div class="frm-g">
          <label class="frm-l">Catégorie de produits liée</label>
          <select class="frm-sel" name="cat_id">
            <option value="">— Toutes catégories —</option>
            <?php foreach ($cats as $c): ?>
            <option value="<?= e($c['id']) ?>" <?= ($ed['cat_id'] ?? '') === $c['id'] ? 'selected' : '' ?>>
              <?= e($c['emoji'] ?? '') ?> <?= e($c['name']) ?>
            </option>
            <?php endforeach; ?>
          </select>
        </div>
      </div>
      <div class="frm-g">
        <label class="frm-l">Sous-titre (affiché sous le titre sur le site)</label>
        <input class="frm-i" name="subtitle" maxlength="300" value="<?= e($ed['subtitle'] ?? '') ?>" placeholder="Ex: Répondez à 4 questions pour trouver votre café idéal">
      </div>
      <div class="tog-wrap">
        <label class="tog"><input type="checkbox" name="active" <?= ($ed['active'] ?? true) ? 'checked' : '' ?>><span class="tog-sl"></span></label>
        <span class="tog-lbl">Quiz actif (visible sur le site)</span>
      </div>
    </div>

    <!-- Questions -->
    <div class="editor-wrap" style="margin-bottom:1.2rem">
      <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:.8rem">
        <div>
          <h4 style="font-family:var(--f1);font-size:1.1rem;margin-bottom:.2rem">❓ Questions</h4>
          <p style="font-size:.75rem;color:var(--gy2)">Format : <code style="background:var(--cr);padding:.1rem .4rem;border-radius:3px">Question|Option A|Option B|Option C</code> — séparés par des barres verticales |</p>
        </div>
        <button type="button" onclick="addQuestion()" class="a-btn prim" style="font-size:.75rem;white-space:nowrap">+ Question</button>
      </div>
      <div id="qList">
        <?php foreach ($ed['questions'] ?? [] as $qi => $q): ?>
        <div class="q-item" style="display:grid;grid-template-columns:1fr auto;gap:.4rem;margin-bottom:.5rem;align-items:center">
          <div>
            <label class="frm-l" style="font-size:.68rem">Question <?= $qi + 1 ?> — Texte | Option 1 | Option 2 | Option 3…</label>
            <input class="frm-i" name="questions[<?= $qi ?>][q]" required maxlength="300"
              value="<?= e($q['q']) ?>" placeholder="Question ici">
            <input class="frm-i" name="questions[<?= $qi ?>][opts]" maxlength="500" style="margin-top:.3rem"
              value="<?= e(implode('|', $q['opts'] ?? [])) ?>" placeholder="Option 1|Option 2|Option 3|Option 4">
          </div>
          <button type="button" onclick="this.closest('.q-item').remove()" class="act-btn del" style="margin-top:1.2rem">✕</button>
        </div>
        <?php endforeach; ?>
      </div>
    </div>

    <!-- Profils résultats -->
    <div class="editor-wrap" style="margin-bottom:1.2rem">
      <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:.8rem">
        <div>
          <h4 style="font-family:var(--f1);font-size:1.1rem;margin-bottom:.2rem">🎭 Profils résultats</h4>
          <p style="font-size:.75rem;color:var(--gy2)">À chaque profil, associez des produits recommandés. Le client verra son profil + ces produits à la fin du quiz.</p>
        </div>
        <button type="button" onclick="addProfile()" class="a-btn prim" style="font-size:.75rem;white-space:nowrap">+ Profil</button>
      </div>
      <div id="pList">
        <?php foreach ($ed['profiles'] ?? [] as $pi => $p): ?>
        <div class="p-item editor-wrap" style="margin-bottom:.8rem;padding:1rem;border-left:3px solid <?= e($p['color'] ?? 'var(--or)') ?>">
          <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:.7rem">
            <strong style="font-size:.85rem"><?= e($p['emoji'] ?? '☕') ?> <?= e($p['title'] ?? 'Profil '.($pi+1)) ?></strong>
            <button type="button" onclick="this.closest('.p-item').remove()" class="act-btn del">✕</button>
          </div>
          <div class="frm-row">
            <div class="frm-g">
              <label class="frm-l">Titre du profil *</label>
              <input class="frm-i" name="profiles[<?= $pi ?>][title]" required maxlength="80" value="<?= e($p['title'] ?? '') ?>" placeholder="Ex: L'Amateur de douceur">
            </div>
            <div class="frm-g" style="display:flex;gap:.5rem">
              <div style="flex:1"><label class="frm-l">Emoji</label><input class="frm-i" name="profiles[<?= $pi ?>][emoji]" maxlength="8" value="<?= e($p['emoji'] ?? '☕') ?>" style="font-size:1.2rem;text-align:center"></div>
              <div><label class="frm-l">Couleur</label><input type="color" name="profiles[<?= $pi ?>][color]" value="<?= e($p['color'] ?? '#C8561E') ?>" style="width:44px;height:40px;border:none;border-radius:4px;cursor:pointer;margin-top:2px"></div>
            </div>
          </div>
          <div class="frm-g">
            <label class="frm-l">Description (affichée au client)</label>
            <textarea class="frm-i" name="profiles[<?= $pi ?>][desc]" rows="2" maxlength="500"><?= e($p['desc'] ?? '') ?></textarea>
          </div>
          <div class="frm-row">
            <div class="frm-g"><label class="frm-l">Texte bouton</label><input class="frm-i" name="profiles[<?= $pi ?>][cta]" maxlength="100" value="<?= e($p['cta'] ?? 'Voir la sélection →') ?>"></div>
            <div class="frm-g"><label class="frm-l">URL bouton (rel. pages/)</label><input class="frm-i" name="profiles[<?= $pi ?>][cta_url]" maxlength="200" value="<?= e($p['cta_url'] ?? 'shop.php') ?>"></div>
          </div>
          <div class="frm-g">
            <label class="frm-l">Produits recommandés pour ce profil</label>
            <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(180px,1fr));gap:.3rem;max-height:160px;overflow-y:auto;padding:.4rem;border:1px solid var(--gy3);border-radius:var(--r);background:var(--wh)">
              <?php
              $p_reco = $p['products'] ?? [];
              foreach ($prd_arr as $pr):
              ?>
              <label style="display:flex;align-items:center;gap:.4rem;font-size:.78rem;cursor:pointer;padding:.2rem">
                <input type="checkbox" name="profiles[<?= $pi ?>][products][]" value="<?= e($pr['id']) ?>" <?= in_array($pr['id'], $p_reco) ? 'checked' : '' ?>>
                <?= e($pr['emoji'] ?? '☕') ?> <?= e(mb_substr($pr['name'], 0, 20, 'UTF-8')) ?>
              </label>
              <?php endforeach; ?>
            </div>
            <small style="font-size:.68rem;color:var(--gy2)">Ces produits seront proposés au client à la fin du quiz</small>
          </div>
        </div>
        <?php endforeach; ?>
      </div>
    </div>

    <button type="submit" class="save-btn">💾 Enregistrer le quiz</button>
  </form>
</div>

<script>
let qCount = <?= count($ed['questions'] ?? []) ?>;
let pCount = <?= count($ed['profiles']  ?? []) ?>;
const allPrds = <?= json_encode(array_values(array_map(fn($p)=>['id'=>$p['id'],'name'=>($p['emoji']??'☕').' '.($p['name']??''), 'checked'=>false], $prd_arr)), JSON_UNESCAPED_UNICODE) ?>;

function addQuestion() {
  const i = qCount++;
  const d = document.createElement('div');
  d.className = 'q-item';
  d.style.cssText = 'display:grid;grid-template-columns:1fr auto;gap:.4rem;margin-bottom:.5rem;align-items:center';
  d.innerHTML = `<div>
    <label class="frm-l" style="font-size:.68rem">Question ${i+1} — Texte | Option 1 | Option 2…</label>
    <input class="frm-i" name="questions[${i}][q]" required maxlength="300" placeholder="Votre question ici">
    <input class="frm-i" name="questions[${i}][opts]" maxlength="500" style="margin-top:.3rem" placeholder="Option 1|Option 2|Option 3|Option 4">
  </div>
  <button type="button" onclick="this.closest('.q-item').remove()" class="act-btn del" style="margin-top:1.2rem">✕</button>`;
  document.getElementById('qList').appendChild(d);
}

function addProfile() {
  const i = pCount++;
  const prdCheckboxes = allPrds.map(p => `<label style="display:flex;align-items:center;gap:.4rem;font-size:.78rem;cursor:pointer;padding:.2rem"><input type="checkbox" name="profiles[${i}][products][]" value="${p.id}"> ${p.name}</label>`).join('');
  const d = document.createElement('div');
  d.className = 'p-item editor-wrap';
  d.style.cssText = 'margin-bottom:.8rem;padding:1rem;border-left:3px solid var(--or)';
  d.innerHTML = `
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:.7rem">
      <strong style="font-size:.85rem">☕ Nouveau profil</strong>
      <button type="button" onclick="this.closest('.p-item').remove()" class="act-btn del">✕</button>
    </div>
    <div class="frm-row">
      <div class="frm-g"><label class="frm-l">Titre *</label><input class="frm-i" name="profiles[${i}][title]" required maxlength="80" placeholder="Ex: L'Explorateur"></div>
      <div class="frm-g" style="display:flex;gap:.5rem">
        <div style="flex:1"><label class="frm-l">Emoji</label><input class="frm-i" name="profiles[${i}][emoji]" maxlength="8" value="☕" style="font-size:1.2rem;text-align:center"></div>
        <div><label class="frm-l">Couleur</label><input type="color" name="profiles[${i}][color]" value="#C8561E" style="width:44px;height:40px;border:none;border-radius:4px;cursor:pointer;margin-top:2px"></div>
      </div>
    </div>
    <div class="frm-g"><label class="frm-l">Description</label><textarea class="frm-i" name="profiles[${i}][desc]" rows="2" maxlength="500" placeholder="Décrivez ce type de client…"></textarea></div>
    <div class="frm-row">
      <div class="frm-g"><label class="frm-l">Texte bouton</label><input class="frm-i" name="profiles[${i}][cta]" maxlength="100" value="Voir la sélection →"></div>
      <div class="frm-g"><label class="frm-l">URL bouton</label><input class="frm-i" name="profiles[${i}][cta_url]" maxlength="200" value="shop.php"></div>
    </div>
    <div class="frm-g">
      <label class="frm-l">Produits recommandés</label>
      <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(180px,1fr));gap:.3rem;max-height:160px;overflow-y:auto;padding:.4rem;border:1px solid var(--gy3);border-radius:var(--r);background:var(--wh)">${prdCheckboxes}</div>
    </div>`;
  document.getElementById('pList').appendChild(d);
}
</script>

<?php else: ?>
<!-- ═══ LISTE DES QUIZ ══════════════════════════════════════════════ -->
<div class="adm-top">
  <div><h2>Quiz guidés</h2><p>Orientez vos clients vers les bons produits · <?= count($quizzes) ?> quiz</p></div>
  <div class="adm-acts"><a href="./quizzes.php?a=new" class="a-btn prim">+ Nouveau quiz</a></div>
</div>
<div class="adm-content">
  <?php if (isset($_GET['saved'])): ?><div class="alert-ok" style="margin-bottom:1rem">✓ Enregistré.</div><?php endif; ?>

  <!-- Explication -->
  <div style="background:var(--cr);border-radius:var(--r);padding:1rem 1.2rem;margin-bottom:1.5rem;font-size:.84rem;color:var(--gy)">
    <strong style="color:var(--bk)">🧭 Principe du quiz guidé</strong><br>
    Chaque option de réponse correspond à un profil dans l'ordre : <strong>Option A → Profil 1, Option B → Profil 2…</strong><br>
    À la fin du quiz, le profil qui a reçu le plus de votes est affiché avec ses produits recommandés.<br>
    <a href="../pages/quiz.php" target="_blank" style="color:var(--or)">→ Voir le quiz sur le site</a>
  </div>

  <?php if (!$quizzes): ?>
  <div class="empty-st"><div class="empty-ico">❓</div><h4>Aucun quiz créé</h4>
    <p style="color:var(--gy2)">Créez votre premier quiz pour guider vos clients.</p>
    <a href="./quizzes.php?a=new" class="btn btn-or" style="margin-top:1rem">+ Créer un quiz</a>
  </div>
  <?php else: ?>
  <div class="tbl-wrap"><table>
    <thead><tr><th>Quiz</th><th>Catégorie</th><th>Questions</th><th>Profils</th><th>Statut</th><th>—</th></tr></thead>
    <tbody>
    <?php foreach ($quizzes as $q): ?>
    <tr>
      <td style="font-weight:500"><?= e($q['title'] ?? '') ?></td>
      <td style="font-size:.82rem"><?php $dc = $cat_idx[$q['cat_id'] ?? ''] ?? null; echo $dc ? e($dc['emoji']??'').' '.e($dc['name']) : '<em style="color:var(--gy2)">Toutes</em>'; ?></td>
      <td style="text-align:center"><?= count($q['questions'] ?? []) ?> question<?= count($q['questions']??[])>1?'s':'' ?></td>
      <td style="text-align:center"><?= count($q['profiles'] ?? []) ?> profil<?= count($q['profiles']??[])>1?'s':'' ?></td>
      <td><span class="bdg <?= ($q['active'] ?? true) ? 'bdg-g' : 'bdg-gy' ?>"><?= ($q['active'] ?? true) ? '✓ Actif' : 'Inactif' ?></span></td>
      <td><div class="act-btns">
        <a href="./quizzes.php?a=edit&id=<?= e($q['id']) ?>" class="act-btn">✏ Éditer</a>
        <a href="../pages/quiz.php?id=<?= e($q['id']) ?>" target="_blank" class="act-btn">👁 Voir</a>
        <form method="POST" style="display:inline"><?= csrf_field() ?>
          <input type="hidden" name="pa" value="delete">
          <input type="hidden" name="did" value="<?= e($q['id']) ?>">
          <button type="submit" class="act-btn del" data-confirm="Supprimer ce quiz ?">✕</button>
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
