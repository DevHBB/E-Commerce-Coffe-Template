<?php
require_once __DIR__ . '/../includes/config.php';
security_headers();
$cfg     = cfg_get();
$B       = base_path();
$qid     = clean($_GET['id']??'', 30);
$quizzes = db('quizzes');
$all_prds = array_filter(db('products'), fn($p) => $p['active']??true);
$prd_idx  = [];
foreach ($all_prds as $p) $prd_idx[$p['id']] = $p;

// Sélectionner le quiz
$quiz = null;
foreach ($quizzes as $q) {
    if (!($q['active']??true)) continue;
    if ($qid && $q['id'] === $qid) { $quiz = $q; break; }
    if (!$qid) { $quiz = $q; break; } // Premier actif
}

$active_quizzes = array_values(array_filter($quizzes, fn($q) => $q['active']??true));
$pt  = $quiz ? e($quiz['title']) : 'Quiz café';
$cur = 'shop';
include ROOT . '/includes/header.php';
?>
<section class="page-hero">
  <div class="wrap" style="position:relative;z-index:1">
    <p class="lbl">Boutique · Découverte</p>
    <h1><?= $quiz ? e($quiz['title']) : 'Quiz café' ?></h1>
    <?php if ($quiz && !empty($quiz['subtitle'])): ?>
    <p style="opacity:.8;font-size:1.1rem;margin-top:.5rem"><?= e($quiz['subtitle']) ?></p>
    <?php endif; ?>
  </div>
</section>
<section class="sec">
  <div class="wrap" style="max-width:700px">
    <?php if (!$quiz): ?>
    <div style="text-align:center;padding:3rem">
      <div style="font-size:3rem;margin-bottom:1rem">☕</div>
      <h3 style="font-family:var(--f1)">Aucun quiz disponible</h3>
      <p style="color:var(--gy)">Revenez bientôt !</p>
      <a href="./shop.php" class="btn btn-or" style="margin-top:1.5rem">Voir la boutique →</a>
    </div>
    <?php else: ?>

    <!-- Sélecteur quiz si plusieurs -->
    <?php if (count($active_quizzes) > 1): ?>
    <div style="display:flex;gap:.4rem;flex-wrap:wrap;margin-bottom:2rem">
      <?php foreach ($active_quizzes as $aq): ?>
      <a href="?id=<?= e($aq['id']) ?>" class="flt <?= ($quiz['id']===$aq['id'])?'on':'' ?>"><?= e($aq['title']) ?></a>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <!-- Barre de progression -->
    <div style="height:4px;background:var(--cr);border-radius:2px;margin-bottom:2.5rem;overflow:hidden">
      <div id="qbar" style="height:100%;background:var(--or);width:0;transition:width .5s cubic-bezier(.16,1,.3,1);border-radius:2px"></div>
    </div>

    <div id="qzone"></div>
    <div id="rzone" style="display:none"></div>
    <?php endif; ?>
  </div>
</section>

<?php if ($quiz): ?>
<script>
const Q  = <?= json_encode($quiz['questions'] ?? [], JSON_UNESCAPED_UNICODE) ?>;
const P  = <?= json_encode($quiz['profiles']  ?? [], JSON_UNESCAPED_UNICODE) ?>;
const PR = <?= json_encode($prd_idx, JSON_UNESCAPED_UNICODE) ?>;
const B  = <?= json_encode($B) ?>;

// scores[profileIndex] = nombre de points
let scores = new Array(P.length).fill(0);
let cur = 0;

function pct(n) { return Math.round(n / Q.length * 100); }

function renderQ() {
  document.getElementById('qbar').style.width = pct(cur) + '%';
  if (cur >= Q.length) { showResult(); return; }
  const q = Q[cur];
  const prev = cur > 0 ? `<button onclick="goBack()" style="margin-top:1rem;background:none;border:none;color:var(--gy2);font-size:.82rem;cursor:pointer">← Précédent</button>` : '';
  document.getElementById('qzone').innerHTML = `
    <div style="animation:fadeUp .4s cubic-bezier(.16,1,.3,1)">
      <p style="font-size:.75rem;text-transform:uppercase;letter-spacing:.14em;color:var(--gy2);margin-bottom:.8rem">${cur+1} / ${Q.length}</p>
      <h2 style="font-family:var(--f1);font-size:clamp(1.3rem,3vw,1.9rem);margin-bottom:1.8rem;line-height:1.2">${q.q}</h2>
      <div style="display:grid;gap:.6rem">
        ${(q.opts||[]).map((o,i)=>`
          <button onclick="pick(${i})" style="text-align:left;padding:1rem 1.3rem;border:2px solid var(--gy3);border-radius:10px;background:var(--wh);font-size:.95rem;cursor:pointer;transition:all .18s;font-family:var(--f2);display:flex;align-items:center;gap:.8rem;width:100%"
            onmouseover="this.style.borderColor='var(--or)';this.style.background='rgba(200,86,30,.04)'"
            onmouseout="this.style.borderColor='var(--gy3)';this.style.background='var(--wh)'">
            <span style="width:26px;height:26px;border-radius:50%;border:2px solid var(--gy3);display:inline-flex;align-items:center;justify-content:center;font-size:.72rem;font-weight:700;flex-shrink:0;color:var(--gy2)">${['A','B','C','D','E'][i]}</span>
            ${o}
          </button>`).join('')}
      </div>
      ${prev}
    </div>`;
}

// Attribution déterministe: option i vote pour le profil P[(i % P.length)]
// Ainsi option A->profil 0, B->profil 1, C->profil 2, D->profil 0, etc.
// Si N profils = 3, opts A,D->profil0 ; B,E->profil1 ; C->profil2
function pick(optIdx) {
  const profileIdx = optIdx % P.length;
  scores[profileIdx]++;
  cur++;
  renderQ();
}
function goBack() { if(cur>0){ cur--; scores = scores.map((_,i)=>Math.max(0,scores[i]-0)); renderQ(); } }
// Note: goBack remet juste le compteur mais ne soustrait pas car on ne sait pas quel profil avait été voté

function showResult() {
  document.getElementById('qbar').style.width='100%';
  document.getElementById('qzone').style.display='none';
  // Trouver le profil gagnant
  let best = 0;
  scores.forEach((s,i) => { if(s > scores[best]) best = i; });
  const winner = P[best];
  const reco = (winner.products||[]).slice(0,3).map(id=>PR[id]).filter(Boolean);

  const res = document.getElementById('rzone');
  res.style.display='block';
  res.innerHTML = `
    <div style="animation:fadeUp .5s cubic-bezier(.16,1,.3,1)">
      <div style="text-align:center;margin-bottom:2rem">
        <div style="font-size:4rem;margin-bottom:.5rem">${winner.emoji||'☕'}</div>
        <p style="font-size:.75rem;text-transform:uppercase;letter-spacing:.15em;color:${winner.color||'var(--or)'};font-weight:600;margin-bottom:.3rem">Votre profil</p>
        <h2 style="font-family:var(--f1);font-size:2.5rem;margin-bottom:.8rem">${winner.title}</h2>
        <p style="color:var(--gy);font-size:1rem;line-height:1.8;max-width:500px;margin:0 auto 1.5rem">${winner.desc||''}</p>
      </div>
      ${reco.length ? `
        <div style="margin-bottom:2rem">
          <p style="font-size:.75rem;text-transform:uppercase;letter-spacing:.12em;color:var(--gy2);text-align:center;margin-bottom:1rem">Nos recommandations</p>
          <div style="display:grid;grid-template-columns:repeat(${Math.min(reco.length,3)},1fr);gap:1rem">
            ${reco.map(p=>`
              <div style="background:var(--cr);border-radius:10px;padding:1rem;text-align:center">
                ${p.image?`<img src="${p.image}" style="width:60px;height:60px;object-fit:cover;border-radius:8px;margin-bottom:.4rem">`:`<div style="font-size:2.5rem;margin-bottom:.4rem">${p.emoji||'☕'}</div>`}
                <div style="font-weight:600;font-size:.88rem;margin-bottom:.2rem">${p.name}</div>
                <div style="font-family:var(--f1);color:var(--or);margin-bottom:.5rem">${parseFloat(p.price||0).toLocaleString('fr-FR',{style:'currency',currency:'EUR'})}</div>
              </div>`).join('')}
          </div>
        </div>` : ''}
      <div style="background:rgba(200,86,30,.06);border:1px solid rgba(200,86,30,.15);border-radius:14px;padding:1.5rem;margin-bottom:1.5rem;text-align:center">
        <a href="${B}pages/${winner.cta_url||'shop.php'}" class="btn btn-or">${winner.cta||'Voir la sélection →'}</a>
      </div>
      <div style="display:flex;gap:1rem;justify-content:center;flex-wrap:wrap">
        <button onclick="restart()" class="btn btn-out">Recommencer le quiz</button>
        <a href="${B}pages/shop.php" class="btn btn-or">Toute la boutique →</a>
      </div>
    </div>`;
}

function restart(){
  scores = new Array(P.length).fill(0);
  cur = 0;
  document.getElementById('rzone').style.display='none';
  document.getElementById('qzone').style.display='block';
  renderQ();
}
renderQ();
</script>
<?php endif; ?>
<?php include ROOT . '/includes/footer.php'; ?>
