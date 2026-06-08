<?php
require_once __DIR__ . '/../includes/config.php';
require_auth();

$cfg  = cfg_get();
$errs = [];
$ok   = false;
$tab  = clean($_GET['t'] ?? 'questions', 20);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $t = clean($_POST['tab'] ?? 'questions', 20);

    if ($t === 'general') {
        $cfg['quiz_enabled']  = isset($_POST['quiz_enabled']);
        $cfg['quiz_title']    = clean($_POST['quiz_title']    ?? 'Quel café êtes-vous ?', 150);
        $cfg['quiz_subtitle'] = clean($_POST['quiz_subtitle'] ?? '', 300);
        admin_log('quiz_general', 'Paramètres généraux quiz mis à jour');
    }

    if ($t === 'questions') {
        // Reconstruire les questions depuis le formulaire
        $questions = [];
        $raw_q = $_POST['questions'] ?? [];
        foreach ($raw_q as $qdata) {
            $q_text = clean($qdata['q'] ?? '', 300);
            if (!$q_text) continue;
            $opts = array_values(array_filter(array_map(fn($o)=>clean($o,150), $qdata['opts']??[])));
            if (count($opts) < 2) continue;
            $questions[] = ['q' => $q_text, 'opts' => $opts];
        }
        if ($questions) {
            $cfg['quiz_questions'] = json_encode($questions, JSON_UNESCAPED_UNICODE);
            admin_log('quiz_questions', count($questions).' questions sauvegardées');
        } else {
            $errs[] = 'Au moins une question avec 2 options est requise.';
        }
    }

    if ($t === 'profiles') {
        $profiles = [];
        $raw_p = $_POST['profiles'] ?? [];
        foreach ($raw_p as $pd) {
            $title = clean($pd['title'] ?? '', 80);
            if (!$title) continue;
            $profiles[] = [
                'id'      => slug($title),
                'title'   => $title,
                'emoji'   => clean($pd['emoji']  ?? '☕', 8),
                'desc'    => clean($pd['desc']   ?? '', 500),
                'cta'     => clean($pd['cta']    ?? 'Voir la sélection →', 100),
                'cta_url' => clean($pd['cta_url']?? 'shop.php', 200),
                'color'   => clean($pd['color']  ?? '#C8561E', 20),
            ];
        }
        if ($profiles) {
            $cfg['quiz_profiles'] = json_encode($profiles, JSON_UNESCAPED_UNICODE);
            admin_log('quiz_profiles', count($profiles).' profils sauvegardés');
        } else {
            $errs[] = 'Au moins un profil est requis.';
        }
    }

    if (!$errs) {
        cfg_save($cfg);
        $cfg = cfg_get();
        $ok  = true;
        $tab = $t;
    }
}

$questions = json_decode($cfg['quiz_questions'] ?? '[]', true) ?: [];
$profiles  = json_decode($cfg['quiz_profiles']  ?? '[]', true) ?: [];

$at = 'Quiz'; $cur_adm = 'quiz';
include ROOT . '/admin/inc/layout.php';
?>
<div class="adm-top">
  <div><h2>Quiz "Quel café êtes-vous ?"</h2><p>Personnalisez les questions et les profils résultats</p></div>
  <div class="adm-acts">
    <a href="../pages/quiz.php" target="_blank" class="a-btn">👁 Voir le quiz</a>
  </div>
</div>
<div class="adm-content">
  <?php if ($ok): ?><div class="alert-ok">✓ Enregistré.</div><?php endif; ?>
  <?php if ($errs): ?><div class="alert-err"><?php foreach($errs as $e) echo '<div>✗ '.htmlspecialchars($e).'</div>'; ?></div><?php endif; ?>

  <!-- Onglets -->
  <div style="display:flex;gap:.4rem;margin-bottom:1.8rem">
    <?php foreach(['general'=>'⚙ Général','questions'=>'❓ Questions','profiles'=>'🎭 Profils résultats'] as $k=>$l): ?>
    <a href="?t=<?= $k ?>" class="flt <?= $tab===$k?'on':'' ?>"><?= $l ?></a>
    <?php endforeach; ?>
  </div>

  <?php if ($tab === 'general'): ?>
  <form method="POST" style="max-width:600px">
    <?= csrf_field() ?><input type="hidden" name="tab" value="general">
    <div class="editor-wrap">
      <div class="tog-wrap"><label class="tog"><input type="checkbox" name="quiz_enabled" <?= !empty($cfg['quiz_enabled'])?'checked':'' ?>><span class="tog-sl"></span></label><span class="tog-lbl">Quiz activé (visible sur le site)</span></div>
      <div class="frm-g" style="margin-top:1rem"><label class="frm-l">Titre du quiz</label>
        <input class="frm-i" name="quiz_title" maxlength="150" value="<?= e($cfg['quiz_title']??'Quel café êtes-vous ?') ?>"></div>
      <div class="frm-g"><label class="frm-l">Sous-titre</label>
        <input class="frm-i" name="quiz_subtitle" maxlength="300" value="<?= e($cfg['quiz_subtitle']??'') ?>"></div>
      <button type="submit" class="save-btn">💾 Enregistrer</button>
    </div>
  </form>

  <?php elseif ($tab === 'questions'): ?>
  <form method="POST" id="qForm">
    <?= csrf_field() ?><input type="hidden" name="tab" value="questions">
    <div id="questions-container">
      <?php foreach ($questions as $qi => $q): ?>
      <div class="q-block editor-wrap" style="position:relative;margin-bottom:1rem">
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:.8rem">
          <strong style="font-size:.85rem">Question <?= $qi+1 ?></strong>
          <button type="button" onclick="removeBlock(this)" class="act-btn del">✕ Supprimer</button>
        </div>
        <div class="frm-g"><label class="frm-l">Texte de la question *</label>
          <input class="frm-i" name="questions[<?= $qi ?>][q]" required maxlength="300" value="<?= e($q['q']) ?>"></div>
        <label class="frm-l">Options de réponse (min. 2)</label>
        <div class="opts-list">
          <?php foreach ($q['opts'] as $oi => $opt): ?>
          <div style="display:flex;gap:.4rem;margin-bottom:.4rem">
            <input class="frm-i" name="questions[<?= $qi ?>][opts][<?= $oi ?>]" maxlength="150" value="<?= e($opt) ?>" placeholder="Option <?= $oi+1 ?>">
            <button type="button" onclick="this.closest('div').remove()" style="background:none;border:1px solid var(--gy3);border-radius:var(--r);padding:.4rem .7rem;cursor:pointer;color:var(--gy);flex-shrink:0">✕</button>
          </div>
          <?php endforeach; ?>
        </div>
        <button type="button" onclick="addOpt(this,<?= $qi ?>)" class="act-btn" style="margin-top:.4rem">+ Option</button>
      </div>
      <?php endforeach; ?>
    </div>
    <div style="display:flex;gap:.8rem;margin-top:.5rem">
      <button type="button" onclick="addQuestion()" class="a-btn prim">+ Ajouter une question</button>
      <button type="submit" class="save-btn" style="flex:1">💾 Enregistrer les questions</button>
    </div>
  </form>

  <?php elseif ($tab === 'profiles'): ?>
  <p style="font-size:.82rem;color:var(--gy2);margin-bottom:1.2rem">
    Chaque profil correspond à un "type" de buveur de café. Le quiz oriente le client vers le profil qui correspond le mieux à ses réponses.
    Le lien CTA l'emmène vers la sélection correspondante de la boutique.
  </p>
  <form method="POST" id="pForm">
    <?= csrf_field() ?><input type="hidden" name="tab" value="profiles">
    <div id="profiles-container">
      <?php foreach ($profiles as $pi => $p): ?>
      <div class="p-block editor-wrap" style="position:relative;margin-bottom:1rem;border-left:4px solid <?= e($p['color']??'var(--or)') ?>">
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:.8rem">
          <strong style="font-size:.85rem"><?= e($p['emoji']??'☕') ?> <?= e($p['title']??'Profil '.($pi+1)) ?></strong>
          <button type="button" onclick="removeBlock(this)" class="act-btn del">✕ Supprimer</button>
        </div>
        <div class="frm-row">
          <div class="frm-g"><label class="frm-l">Titre du profil *</label>
            <input class="frm-i" name="profiles[<?= $pi ?>][title]" required maxlength="80" value="<?= e($p['title']??'') ?>"></div>
          <div class="frm-g" style="display:flex;gap:.5rem">
            <div style="flex:1"><label class="frm-l">Emoji</label>
              <input class="frm-i" name="profiles[<?= $pi ?>][emoji]" maxlength="8" value="<?= e($p['emoji']??'☕') ?>" style="text-align:center;font-size:1.3rem"></div>
            <div><label class="frm-l">Couleur</label>
              <div style="display:flex;gap:.4rem;align-items:center">
                <input type="color" name="profiles[<?= $pi ?>][color]" value="<?= e($p['color']??'#C8561E') ?>" style="width:40px;height:40px;border:none;border-radius:4px;cursor:pointer">
                <input class="frm-i" name="profiles[<?= $pi ?>][color]" maxlength="20" value="<?= e($p['color']??'#C8561E') ?>" style="font-family:monospace;width:100px">
              </div>
            </div>
          </div>
        </div>
        <div class="frm-g"><label class="frm-l">Description affichée au client</label>
          <textarea class="frm-i" name="profiles[<?= $pi ?>][desc]" rows="3" maxlength="500" style="resize:vertical"><?= e($p['desc']??'') ?></textarea></div>
        <div class="frm-row">
          <div class="frm-g"><label class="frm-l">Texte du bouton CTA</label>
            <input class="frm-i" name="profiles[<?= $pi ?>][cta]" maxlength="100" value="<?= e($p['cta']??'Voir la sélection →') ?>"></div>
          <div class="frm-g"><label class="frm-l">URL du bouton (relative au dossier pages/)</label>
            <input class="frm-i" name="profiles[<?= $pi ?>][cta_url]" maxlength="200" value="<?= e($p['cta_url']??'shop.php') ?>" placeholder="shop.php ou shop.php?c=origine"></div>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
    <div style="display:flex;gap:.8rem;margin-top:.5rem">
      <button type="button" onclick="addProfile()" class="a-btn prim">+ Ajouter un profil</button>
      <button type="submit" class="save-btn" style="flex:1">💾 Enregistrer les profils</button>
    </div>
  </form>
  <?php endif; ?>
</div>

<script>
let qCount = <?= count($questions) ?>;
let pCount = <?= count($profiles) ?>;

function removeBlock(btn) {
  if (!confirm('Supprimer ?')) return;
  btn.closest('.q-block,.p-block').remove();
  // Renuméroter
  renumber();
}

function renumber() {
  document.querySelectorAll('#questions-container .q-block').forEach((b,i)=>{
    b.querySelectorAll('[name]').forEach(el=>{
      el.name = el.name.replace(/questions\[\d+\]/g,'questions['+i+']');
    });
    b.querySelector('strong').textContent = 'Question '+(i+1);
  });
  document.querySelectorAll('#profiles-container .p-block').forEach((b,i)=>{
    b.querySelectorAll('[name]').forEach(el=>{
      el.name = el.name.replace(/profiles\[\d+\]/g,'profiles['+i+']');
    });
  });
}

function addOpt(btn, qi) {
  const list = btn.previousElementSibling;
  const idx  = list.children.length;
  const div  = document.createElement('div');
  div.style.cssText = 'display:flex;gap:.4rem;margin-bottom:.4rem';
  div.innerHTML = `<input class="frm-i" name="questions[${qi}][opts][${idx}]" maxlength="150" placeholder="Option ${idx+1}">
    <button type="button" onclick="this.closest('div').remove()" style="background:none;border:1px solid var(--gy3);border-radius:var(--r);padding:.4rem .7rem;cursor:pointer;color:var(--gy);flex-shrink:0">✕</button>`;
  list.appendChild(div);
}

function addQuestion() {
  const i = qCount++;
  const div = document.createElement('div');
  div.className = 'q-block editor-wrap';
  div.style.cssText = 'position:relative;margin-bottom:1rem';
  div.innerHTML = `
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:.8rem">
      <strong style="font-size:.85rem">Question ${i+1}</strong>
      <button type="button" onclick="removeBlock(this)" class="act-btn del">✕ Supprimer</button>
    </div>
    <div class="frm-g"><label class="frm-l">Texte de la question *</label>
      <input class="frm-i" name="questions[${i}][q]" required maxlength="300" placeholder="Votre question..."></div>
    <label class="frm-l">Options de réponse</label>
    <div class="opts-list">
      <div style="display:flex;gap:.4rem;margin-bottom:.4rem">
        <input class="frm-i" name="questions[${i}][opts][0]" maxlength="150" placeholder="Option 1">
        <button type="button" onclick="this.closest('div').remove()" style="background:none;border:1px solid var(--gy3);border-radius:var(--r);padding:.4rem .7rem;cursor:pointer;color:var(--gy);flex-shrink:0">✕</button>
      </div>
      <div style="display:flex;gap:.4rem;margin-bottom:.4rem">
        <input class="frm-i" name="questions[${i}][opts][1]" maxlength="150" placeholder="Option 2">
        <button type="button" onclick="this.closest('div').remove()" style="background:none;border:1px solid var(--gy3);border-radius:var(--r);padding:.4rem .7rem;cursor:pointer;color:var(--gy);flex-shrink:0">✕</button>
      </div>
    </div>
    <button type="button" onclick="addOpt(this,${i})" class="act-btn" style="margin-top:.4rem">+ Option</button>`;
  document.getElementById('questions-container').appendChild(div);
}

function addProfile() {
  const i = pCount++;
  const div = document.createElement('div');
  div.className = 'p-block editor-wrap';
  div.style.cssText = 'position:relative;margin-bottom:1rem;border-left:4px solid var(--or)';
  div.innerHTML = `
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:.8rem">
      <strong style="font-size:.85rem">☕ Nouveau profil</strong>
      <button type="button" onclick="removeBlock(this)" class="act-btn del">✕ Supprimer</button>
    </div>
    <div class="frm-row">
      <div class="frm-g"><label class="frm-l">Titre *</label>
        <input class="frm-i" name="profiles[${i}][title]" required maxlength="80" placeholder="Ex: L'Aventurier"></div>
      <div class="frm-g" style="display:flex;gap:.5rem">
        <div style="flex:1"><label class="frm-l">Emoji</label>
          <input class="frm-i" name="profiles[${i}][emoji]" maxlength="8" value="☕" style="text-align:center;font-size:1.3rem"></div>
        <div><label class="frm-l">Couleur</label>
          <div style="display:flex;gap:.4rem;align-items:center">
            <input type="color" name="profiles[${i}][color]" value="#C8561E" style="width:40px;height:40px;border:none;border-radius:4px;cursor:pointer">
            <input class="frm-i" name="profiles[${i}][color]" maxlength="20" value="#C8561E" style="font-family:monospace;width:100px">
          </div>
        </div>
      </div>
    </div>
    <div class="frm-g"><label class="frm-l">Description</label>
      <textarea class="frm-i" name="profiles[${i}][desc]" rows="3" maxlength="500" style="resize:vertical" placeholder="Décrivez ce profil..."></textarea></div>
    <div class="frm-row">
      <div class="frm-g"><label class="frm-l">Texte bouton CTA</label>
        <input class="frm-i" name="profiles[${i}][cta]" maxlength="100" value="Voir la sélection →"></div>
      <div class="frm-g"><label class="frm-l">URL CTA</label>
        <input class="frm-i" name="profiles[${i}][cta_url]" maxlength="200" placeholder="shop.php"></div>
    </div>`;
  document.getElementById('profiles-container').appendChild(div);
}
</script>
<?php include ROOT . '/admin/inc/layout_end.php'; ?>
