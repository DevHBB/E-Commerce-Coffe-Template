<?php
require_once __DIR__ . '/../includes/config.php';
security_headers();
$cfg = cfg_get();
$B   = base_path();
$all = array_filter(db('products'), fn($p) => ($p['active']??true) && in_array($p['cat']??'', ['origine','blend','decafeine']));
$pt  = 'Comparer les cafés';
$cur = 'shop';
include ROOT . '/includes/header.php';
?>
<section class="page-hero">
  <div class="wrap" style="position:relative;z-index:1">
    <p class="lbl">Boutique</p>
    <h1>Comparez nos <em>cafés</em></h1>
    <p>Sélectionnez 2 ou 3 cafés pour les comparer côte à côte.</p>
  </div>
</section>
<section class="sec">
  <div class="wrap">
    <!-- Sélection -->
    <div style="display:flex;gap:.6rem;flex-wrap:wrap;margin-bottom:2.5rem;align-items:center">
      <span style="font-size:.8rem;text-transform:uppercase;letter-spacing:.1em;color:var(--gy2)">Choisir :</span>
      <?php foreach ($all as $p): ?>
      <label style="cursor:pointer">
        <input type="checkbox" class="cmp-chk" value="<?= e($p['id']) ?>" style="display:none">
        <div class="cmp-pill" data-id="<?= e($p['id']) ?>">
          <?= e($p['emoji']??'☕') ?> <?= e($p['name']) ?>
        </div>
      </label>
      <?php endforeach; ?>
    </div>

    <!-- Table comparaison -->
    <div id="cmpTable" style="display:none;overflow-x:auto">
      <table id="cmpT" style="width:100%;border-collapse:collapse;min-width:400px">
        <thead>
          <tr>
            <th style="text-align:left;padding:.8rem;background:var(--cr);font-size:.72rem;text-transform:uppercase;letter-spacing:.1em;color:var(--gy2)">Critère</th>
          </tr>
        </thead>
        <tbody id="cmpBody"></tbody>
      </table>
    </div>
    <div id="cmpEmpty" style="text-align:center;padding:3rem;color:var(--gy2)">
      <div style="font-size:3rem;margin-bottom:1rem">☕</div>
      <p>Sélectionnez au moins 2 cafés pour les comparer</p>
    </div>
  </div>
</section>

<style>
.cmp-pill{padding:.4rem .9rem;border:1.5px solid var(--gy3);border-radius:100px;font-size:.78rem;transition:all var(--t);white-space:nowrap;background:var(--wh)}
.cmp-chk:checked + .cmp-pill{background:var(--or);border-color:var(--or);color:#fff}
</style>
<script>
const products = <?= json_encode(array_values($all), JSON_UNESCAPED_UNICODE) ?>;
const pIdx = {};
products.forEach(p => pIdx[p.id] = p);
const fields = [
  ['emoji',''],
  ['name','Nom'],
  ['origin','Origine'],
  ['roast','Torréfaction'],
  ['price','Prix TTC'],
  ['price_ht','Prix HT'],
  ['tva','TVA'],
  ['weight','Format'],
  ['desc','Description'],
];

function render() {
  const sel = [...document.querySelectorAll('.cmp-chk:checked')].map(c=>c.value);
  const tbl = document.getElementById('cmpTable');
  const empty = document.getElementById('cmpEmpty');
  if (sel.length < 2) { tbl.style.display='none'; empty.style.display='block'; return; }
  tbl.style.display='block'; empty.style.display='none';

  // En-têtes
  const thead = document.querySelector('#cmpT thead tr');
  thead.innerHTML = '<th style="text-align:left;padding:.8rem;background:var(--cr);font-size:.72rem;text-transform:uppercase;letter-spacing:.1em;color:var(--gy2)">Critère</th>';
  sel.forEach(id => {
    const p = pIdx[id];
    const th = document.createElement('th');
    th.style.cssText = 'padding:.8rem;background:var(--cr);font-family:var(--f1);font-size:1rem;min-width:160px';
    th.innerHTML = `<div style="font-size:2rem">${p.emoji||'☕'}</div>${p.name}`;
    thead.appendChild(th);
  });

  // Lignes
  const tbody = document.getElementById('cmpBody');
  tbody.innerHTML = '';
  fields.filter(([k])=>k!=='emoji').forEach(([key, label]) => {
    const tr = document.createElement('tr');
    let html = `<td style="padding:.8rem;font-size:.8rem;font-weight:600;text-transform:uppercase;letter-spacing:.08em;color:var(--gy2);background:#FAFAF8;border-bottom:1px solid var(--cr)">${label}</td>`;
    sel.forEach(id => {
      const p = pIdx[id];
      let v = p[key] ?? '—';
      if (key==='price'||key==='price_ht') v = parseFloat(v||0).toLocaleString('fr-FR',{style:'currency',currency:'EUR'});
      if (key==='tva') v = (v||20) + ' %';
      html += `<td style="padding:.8rem;border-bottom:1px solid var(--cr);font-size:.88rem">${v}</td>`;
    });
    tr.innerHTML = html;
    tbody.appendChild(tr);
  });

  // Ligne "Ajouter au panier"
  const trBtn = document.createElement('tr');
  let btnHtml = '<td style="padding:.8rem;background:#FAFAF8"></td>';
  sel.forEach(id => {
    const p = pIdx[id];
    btnHtml += `<td style="padding:.8rem;background:#FAFAF8"><button class="btn btn-or btn-sm" onclick='window.addToCart&&addToCart({id:"${p.id}",name:"${p.name.replace(/"/g,"'")}",price:${p.price||0},emoji:"${p.emoji||'☕'}",qty:1})'>+ Panier</button></td>`;
  });
  trBtn.innerHTML = btnHtml;
  tbody.appendChild(trBtn);
}

document.querySelectorAll('.cmp-chk').forEach(chk => {
  chk.addEventListener('change', function() {
    const checked = document.querySelectorAll('.cmp-chk:checked').length;
    if (checked > 3) { this.checked = false; return; }
    render();
  });
});
render();
</script>
<?php include ROOT . '/includes/footer.php'; ?>
