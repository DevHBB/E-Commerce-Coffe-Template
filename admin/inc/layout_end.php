<?php // admin/inc/layout_end.php ?>
</div><!-- /.adm-main -->
</div><!-- /.adm-body -->

<div class="toast" id="toast"></div>

<script src="../assets/js/main.js"></script>
<script>
window.SITE_BASE = '../';
// main.js est chargé après DOMContentLoaded - on appelle initUploaders directement
if (typeof initUploaders === 'function') initUploaders();
</script>
<script>
function toast(msg, type='ok') {
  const t = document.getElementById('toast');
  t.textContent = msg;
  t.className = 'toast ' + type + ' on';
  clearTimeout(window._tt);
  window._tt = setTimeout(() => t.classList.remove('on'), 3000);
}

// Confirm delete
document.querySelectorAll('[data-confirm]').forEach(b => {
  b.addEventListener('click', e => { if (!confirm(b.dataset.confirm)) e.preventDefault(); });
});

// Auto-slug
const titleF = document.getElementById('f_title') || document.getElementById('f_name');
const slugF  = document.getElementById('f_slug');
if (titleF && slugF && !slugF.value) {
  titleF.addEventListener('input', () => {
    slugF.value = titleF.value.toLowerCase()
      .normalize('NFD').replace(/[\u0300-\u036f]/g,'')
      .replace(/[^a-z0-9]+/g,'-').replace(/^-|-$/g,'');
  });
}
slugF?.addEventListener('input', () => {
  const prev = document.getElementById('slugPreview');
  if (prev) prev.textContent = slugF.value;
});

// Mobile sidebar
var _sbOpen = false;
function toggleSb() {
  _sbOpen ? closeSb() : openSb();
}
function openSb() {
  var sb = document.getElementById('admSb');
  var ov = document.getElementById('admOverlay');
  if (!sb) return;
  _sbOpen = true;
  // Forcer l'affichage via style inline (plus fiable que classList sur mobile)
  sb.style.transform = 'translateX(0)';
  sb.style.boxShadow = '6px 0 30px rgba(0,0,0,.5)';
  if (ov) { ov.style.display = 'block'; }
  document.body.style.overflow = 'hidden';
  // ✕
  var l1=document.getElementById('hbLine1'),l2=document.getElementById('hbLine2'),l3=document.getElementById('hbLine3');
  if(l1){l1.style.transform='translateY(7px) rotate(45deg)';}
  if(l2){l2.style.opacity='0';}
  if(l3){l3.style.transform='translateY(-7px) rotate(-45deg)';l3.style.width='22px';}
}
function closeSb() {
  var sb = document.getElementById('admSb');
  var ov = document.getElementById('admOverlay');
  if (!sb) return;
  _sbOpen = false;
  sb.style.transform = 'translateX(-255px)';
  sb.style.boxShadow = '';
  if (ov) { ov.style.display = 'none'; }
  document.body.style.overflow = '';
  // ☰
  var l1=document.getElementById('hbLine1'),l2=document.getElementById('hbLine2'),l3=document.getElementById('hbLine3');
  if(l1){l1.style.transform='';}
  if(l2){l2.style.opacity='1';}
  if(l3){l3.style.transform='';l3.style.width='16px';}
}

function closeSb() {
  var sb = document.getElementById('admSb');
  var ov = document.getElementById('admOverlay');
  if (sb) sb.classList.remove('open');
  if (ov) ov.style.display = 'none';
  var l1=document.getElementById('hbLine1'),l2=document.getElementById('hbLine2'),l3=document.getElementById('hbLine3');
  if(l1){l1.style.transform='';} if(l2){l2.style.opacity='1';} if(l3){l3.style.transform='';}
}
// Fermer sidebar quand on clique un lien sur mobile
document.querySelectorAll('.adm-sb a').forEach(function(a){
  a.addEventListener('click', function(){
    if (window.innerWidth <= 768) { setTimeout(closeSb, 80); }
  });
});
// Fermer si clic en dehors sur desktop (redimensionnement)
window.addEventListener('resize', function(){
  if (window.innerWidth > 768) closeSb();
});

// ── Mémoriser la position de scroll de la sidebar ─────────────────────
(function(){
  var sb = document.getElementById('admSb');
  if (!sb) return;

  // Restaurer la position sauvegardée
  var saved = sessionStorage.getItem('sb_scroll');
  if (saved) sb.scrollTop = parseInt(saved, 10);

  // Sauvegarder au scroll (debounce 100ms)
  var _t;
  sb.addEventListener('scroll', function(){
    clearTimeout(_t);
    _t = setTimeout(function(){ sessionStorage.setItem('sb_scroll', sb.scrollTop); }, 100);
  });

  // Sauvegarder avant de quitter la page (clic sur un lien sidebar)
  sb.querySelectorAll('a').forEach(function(a){
    a.addEventListener('click', function(){
      sessionStorage.setItem('sb_scroll', sb.scrollTop);
    });
  });
})();

// Flash messages
<?php if (isset($_GET['saved'])): ?>toast('Enregistré ✓');<?php endif; ?>
<?php if (isset($_GET['del'])): ?>toast('Supprimé ✓');<?php endif; ?>
<?php if (isset($_GET['err'])): ?>toast('Erreur lors de l\'opération','err');<?php endif; ?>
</script>
</body></html>
