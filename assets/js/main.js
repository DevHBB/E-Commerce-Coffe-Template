/* ══ CAFÉ MAISON v2 — main.js ══ */
'use strict';
const $ = s => document.querySelector(s);
const $$ = s => document.querySelectorAll(s);
const on = (el,ev,fn) => el?.addEventListener(ev,fn);

/* ── Cart (localStorage) ─────────── */
const CART_KEY = 'cafe_cart_v2';
let cart = [];
try { cart = JSON.parse(localStorage.getItem(CART_KEY))||[]; } catch(e){}
window.cart = cart;
function saveCart(){ try{localStorage.setItem(CART_KEY,JSON.stringify(cart));}catch(e){} window.cart=cart; }
function cartTotal(){ return cart.reduce((s,i)=>s+i.price*i.qty,0); }
function cartQty()  { return cart.reduce((s,i)=>s+i.qty,0); }
function fmt(n){ return n.toLocaleString('fr-FR',{style:'currency',currency:'EUR'}); }
function esc(s){ return String(s).replace(/[&<>"']/g,m=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[m])); }

function addToCart(p){
  if (window.SITE_CFG && window.SITE_CFG.ecommerce === false) return;
  const ex=cart.find(i=>i.id===p.id);
  if(ex) ex.qty++;
  else cart.push({...p,qty:1});
  saveCart(); renderCart();
  showToast(`<span>${esc(p.name)}</span> ajouté au panier`);
}

function renderCart(){
  const n=cartQty();
  $$('.cart-count').forEach(el=>{el.textContent=n;el.classList.toggle('on',n>0);});
  const items=$('#cartItems');
  if(!items)return;
  if(!cart.length){
    items.innerHTML='<div class="cart-empty"><div class="cart-empty-ico">🛒</div><p>Votre panier est vide</p></div>';
  } else {
    items.innerHTML=cart.map((it,idx)=>`
      <div class="cart-item">
        <div class="cart-item-img">${it.emoji||'☕'}</div>
        <div>
          <div class="cart-item-name">${esc(it.name)}</div>
          <div class="cart-item-price">${fmt(it.price*it.qty)}</div>
          <div class="cart-item-qty">
            <button class="qty-btn" data-a="dec" data-i="${idx}">−</button>
            <span class="qty-n">${it.qty}</span>
            <button class="qty-btn" data-a="inc" data-i="${idx}">+</button>
          </div>
        </div>
        <button class="cart-item-del" data-i="${idx}">×</button>
      </div>`).join('');
    $$('.qty-btn').forEach(b=>on(b,'click',()=>{
      const i=+b.dataset.i;
      if(b.dataset.a==='inc') cart[i].qty++;
      else if(--cart[i].qty<=0) cart.splice(i,1);
      saveCart();renderCart();
    }));
    $$('.cart-item-del').forEach(b=>on(b,'click',()=>{cart.splice(+b.dataset.i,1);saveCart();renderCart();}));
  }
  const cfg=window.SITE_CFG||{shipping_free:35,shipping:4.9};
  const sub=cartTotal();
  const disc=getPromoDiscount(sub);
  const subAfterDisc=Math.max(0,sub-disc);
  const ship=subAfterDisc>=cfg.shipping_free?0:cfg.shipping;
  const total=subAfterDisc+ship;
  const el=id=>document.getElementById(id);
  if(el('cartSub'))   el('cartSub').textContent=fmt(sub);
  if(el('cartPromoAmt'))  { el('cartPromoAmt').textContent=disc>0?'−'+fmt(disc):''; el('cartPromoLine')&&(el('cartPromoLine').style.display=disc>0?'':'none'); }
  if(el('cartShip'))  el('cartShip').textContent=ship===0?'Offerte':fmt(ship);
  if(el('cartTotal')) el('cartTotal').textContent=fmt(total);
  const btn=$('#cartCheckoutBtn');
  if(btn) btn.disabled=!cart.length;
}

function openCart(){$('#cartSidebar')?.classList.add('open');$('#cartOverlay')?.classList.add('on');}
function closeCart(){$('#cartSidebar')?.classList.remove('open');$('#cartOverlay')?.classList.remove('on');}

let _tt;
function showToast(html){
  const t=$('#cartToast');if(!t)return;
  t.innerHTML=html;t.classList.add('on');
  clearTimeout(_tt);_tt=setTimeout(()=>t.classList.remove('on'),2800);
}

/* ── Scroll reveal ─────────────── */
const rObs=new IntersectionObserver(entries=>{
  entries.forEach(e=>{
    if(e.isIntersecting){
      const d=parseInt(e.target.dataset.delay||0)*80;
      setTimeout(()=>{
        e.target.classList.add('vis');
        e.target.querySelectorAll('.line').forEach(l=>l.classList.add('vis'));
      },d);
      rObs.unobserve(e.target);
    }
  });
},{threshold:.1});

/* ── Header ─────────────────────── */
function initHeader(){
  const hdr=$('#hdr');
  if(!hdr)return;
  const u=()=>hdr.classList.toggle('sc',scrollY>55);
  u();window.addEventListener('scroll',u,{passive:true});
}

/* ── Mobile nav drawer ──────────── */
function initNav(){
  const hbg=$('#hbg'),drawer=$('#navDrawer'),close=$('#drawerClose');
  if(!hbg||!drawer)return;
  const open=()=>{drawer.classList.add('open');document.body.style.overflow='hidden';
    [hbg.children[0],hbg.children[1],hbg.children[2]].forEach((s,i)=>{
      if(i===0)s.style.cssText='transform:rotate(45deg) translate(5px,5px)';
      if(i===1)s.style.opacity='0';
      if(i===2)s.style.cssText='transform:rotate(-45deg) translate(5px,-5px)';
    });};
  const cl=()=>{drawer.classList.remove('open');document.body.style.overflow='';
    [...hbg.children].forEach(s=>s.style.cssText='');};
  on(hbg,'click',open);on(close,'click',cl);
  drawer.querySelectorAll('a').forEach(a=>on(a,'click',cl));
  let sx=0;
  drawer.addEventListener('touchstart',e=>sx=e.touches[0].clientX,{passive:true});
  drawer.addEventListener('touchend',e=>{if(e.changedTouches[0].clientX-sx>60)cl();},{passive:true});
}

/* ── Cart bindings ──────────────── */
function initCart(){
  // En mode vitrine, le panier est désactivé côté JS aussi
  if (window.SITE_CFG && window.SITE_CFG.ecommerce === false) return;
  $$('.cart-trigger').forEach(b=>on(b,'click',openCart));
  on($('#cartClose'),'click',closeCart);
  on($('#cartOverlay'),'click',closeCart);
  document.addEventListener('keydown',e=>{if(e.key==='Escape')closeCart();});
  on($('#cartCheckoutBtn'),'click',()=>{
    if(!cart.length)return;
    const B=window.SITE_BASE||'./';
    window.location.href=B+'pages/checkout.php';
  });
  $$('.btn-add').forEach(btn=>{
    on(btn,'click',()=>{
      const c=btn.closest('[data-product],[data-id],.prd-card');
      if(!c)return;
      addToCart({
        id:c.dataset.id||btn.dataset.id||Math.random().toString(36).slice(2),
        name:c.dataset.name||c.querySelector('.prd-name')?.textContent||'Café',
        price:parseFloat(c.dataset.price||btn.dataset.price||0),
        emoji:c.dataset.emoji||'☕',
      });
      const orig=btn.textContent;
      btn.textContent='✓ Ajouté';btn.style.background='#3D6E2B';
      setTimeout(()=>{btn.textContent=orig;btn.style.background='';},1600);
    });
  });
  renderCart();
}

/* ── Filters ─────────────────────── */
function initFilters(){
  $$('.flt').forEach(btn=>on(btn,'click',()=>{
    $$('.flt').forEach(b=>b.classList.remove('on'));
    btn.classList.add('on');
    const f=btn.dataset.filter;
    $$('.prd-card').forEach(c=>{
      const show=f==='all'||c.dataset.cat===f;
      c.style.cssText=show?'':'opacity:.2;transform:scale(.97);pointer-events:none';
      if(show)c.style.cssText='';
    });
  }));
}

/* ── Smooth scroll ─────────────── */
function initSmooth(){
  $$('a[href^="#"]').forEach(a=>on(a,'click',e=>{
    const t=document.querySelector(a.getAttribute('href'));
    if(t){e.preventDefault();t.scrollIntoView({behavior:'smooth'});}
  }));
}

/* ── Counters ───────────────────── */
function initCounters(){
  const stats=$('.h-stats');if(!stats)return;
  const obs=new IntersectionObserver(entries=>{
    if(!entries[0].isIntersecting)return;
    stats.querySelectorAll('[data-n]').forEach(el=>{
      const tg=parseInt(el.dataset.n),suf=el.dataset.suf||'';
      let n=0,step=tg/(1100/16);
      const t=setInterval(()=>{n=Math.min(n+step,tg);el.textContent=Math.floor(n)+suf;if(n>=tg)clearInterval(t);},16);
    });
    obs.disconnect();
  },{threshold:.5});
  obs.observe(stats);
}

/* ── Atelier → contact ─────────── */
function initAtelier(){
  $$('.atl-card[data-name]').forEach(c=>on(c,'click',()=>{
    const s=document.getElementById('sujet');
    if(s)s.value='Réservation atelier : '+c.dataset.name;
    document.getElementById('contact')?.scrollIntoView({behavior:'smooth'});
  }));
}

/* ── Contact form ───────────────── */
function initContact(){
  const cf=$('#contactForm');if(!cf)return;
  on(cf,'submit',function(){
    const b=this.querySelector('[type=submit]');
    if(b){b.disabled=true;b.textContent='Envoi…';}
  });
}

/* ── Checkout ───────────────────── */
function getDeliveryInfo(){
  const mode=getDeliveryMode();
  return {
    fname:         document.getElementById('fname')?.value.trim()||'',
    lname:         document.getElementById('lname')?.value.trim()||'',
    email:         document.getElementById('cemail')?.value.trim()||'',
    phone:         document.getElementById('cphone')?.value.trim()||'',
    addr:          mode==='pickup'?'':(document.getElementById('addr')?.value.trim()||''),
    zip:           mode==='pickup'?'':(document.getElementById('zip')?.value.trim()||''),
    city:          mode==='pickup'?'':(document.getElementById('city')?.value.trim()||''),
    delivery_mode: mode,
  };
}
function validateDelivery(){
  const d=getDeliveryInfo();
  const missing=[];
  if(!d.fname) missing.push('Prénom');
  if(!d.lname) missing.push('Nom');
  if(!d.email||!/^[^@]+@[^@]+\.[^@]+$/.test(d.email)) missing.push('Email valide');
  if(d.delivery_mode!=='pickup'){
    if(!d.addr)  missing.push('Adresse');
    if(!d.zip||!/^\d{5}$/.test(d.zip)) missing.push('Code postal (5 chiffres)');
    if(!d.city)  missing.push('Ville');
  }
  return missing;
}

function getDeliveryMode(){
  const r=document.querySelector('[name=delivery_mode]:checked');
  if(r) return r.value;
  const h=document.querySelector('[name=delivery_mode]');
  return h?h.value:'delivery';
}

function updateOrderSummary(){
  const cfg=window.SITE_CFG||{shipping_free:35,shipping:4.9,currency:'EUR'};
  const mode=getDeliveryMode();
  const sub=cartTotal();
  const disc=getPromoDiscount(sub);
  const subAfterDisc=Math.max(0,sub-disc);
  const ship=mode==='pickup'?0:(subAfterDisc>=cfg.shipping_free?0:cfg.shipping);
  const total=subAfterDisc+ship;
  const summary=$('#orderSummary');
  if(!summary) return;
  const discLine=disc>0?`<div class="order-line" style="color:var(--vr2)"><span>🏷 Remise (${activePromo?.code||''})</span><span>−${fmt(disc)}</span></div>`:'';
  summary.innerHTML=
    cart.map(i=>`<div class="order-item"><div><div class="order-item-name">${esc(i.emoji||'☕')} ${esc(i.name)}</div><div class="order-item-qty">× ${i.qty}</div></div><div style="font-weight:600">${fmt(i.price*i.qty)}</div></div>`).join('')+
    `<div class="order-line" style="margin-top:.5rem"><span>Sous-total</span><span>${fmt(sub)}</span></div>`+
    discLine+
    `<div class="order-line"><span>${mode==='pickup'?'Retrait boutique':'Livraison'}</span><span>${ship===0?'Gratuit ✓':fmt(ship)}</span></div>
     <div class="order-total"><span>Total</span><span>${fmt(total)}</span></div>`;
  // Afficher info pickup
  const info=$('#shippingInfo');
  if(info){
    if(mode==='pickup'){
      info.innerHTML=`🏪 Retrait : ${cfg.pickup_address||''}<br>${cfg.pickup_hours||''}`;
    } else {
      const fr=cfg.shipping_free_from||cfg.shipping_free||35;
      info.innerHTML=sub>=fr?'✅ Livraison offerte !':
        `Livraison offerte dès ${fmt(fr)}, sinon ${fmt(cfg.shipping||4.9)}`;
    }
  }
  // Afficher/masquer adresse livraison
  const addrBox=$('#addressBox');
  if(addrBox){
    addrBox.style.display=mode==='pickup'?'none':'block';
    addrBox.querySelectorAll('input').forEach(i=>i.required=mode!=='pickup');
  }
}

function initCheckout(){
  const wrap=$('.checkout-wrap');if(!wrap)return;
  if(!cart.length){
    const B=window.SITE_BASE||'./';
    wrap.innerHTML='<div style="text-align:center;padding:4rem 2rem"><p style="font-size:1.1rem;margin-bottom:1.5rem">Votre panier est vide.</p><a href="'+B+'pages/shop.php" class="btn btn-or">Voir la boutique</a></div>';
    return;
  }
  // Écouter le changement de mode livraison
  document.querySelectorAll('[name=delivery_mode]').forEach(r=>r.addEventListener('change',updateOrderSummary));
  updateOrderSummary();
  if(window.STRIPE_PK&&typeof Stripe!=='undefined') initStripe(window.STRIPE_PK);
  if(window.PAYPAL_CLIENT_ID&&typeof paypal!=='undefined') initPayPal();
}

async function initStripe(pk){
  const stripe=Stripe(pk);
  const B=window.SITE_BASE||'./';
  // Créer le PaymentIntent avec les vraies infos
  let clientSecret=null, realTotal=null;
  try{
    const delivery=getDeliveryInfo();
    const r=await fetch(B+'api/stripe_intent.php',{
      method:'POST',
      headers:{'Content-Type':'application/json'},
      body:JSON.stringify({cart,...delivery,
        promo_code: activePromo?.code || null,
        promo_discount: activePromo ? getPromoDiscount(cartTotal()) : 0
      })
    });
    const data=await r.json();
    if(data.error){
      document.getElementById('stripe-error').textContent='Erreur : '+data.error;
      return;
    }
    clientSecret=data.client_secret;
    realTotal=data.total;
  }catch(err){
    console.warn('Stripe init:',err);
    document.getElementById('stripe-error').textContent='Connexion impossible. Réessayez.';
    return;
  }
  const elements=stripe.elements({
    clientSecret,
    appearance:{theme:'stripe',variables:{colorPrimary:'#C8561E',borderRadius:'4px',fontFamily:'Jost, sans-serif'}}
  });
  const payEl=elements.create('payment');
  payEl.mount('#payment-element');
  const form=$('#stripeForm');
  if(!form) return;
  on(form,'submit',async e=>{
    e.preventDefault();
    // Valider les champs livraison
    const missing=validateDelivery();
    if(missing.length){
      document.getElementById('stripe-error').textContent='Champs manquants : '+missing.join(', ');
      return;
    }
    const btn=form.querySelector('[type=submit]');
    btn.disabled=true;btn.textContent='Traitement en cours…';
    document.getElementById('stripe-error').textContent='';
    const{error}=await stripe.confirmPayment({
      elements,
      confirmParams:{return_url:location.origin+B+'pages/order_success.php'}
    });
    if(error){
      document.getElementById('stripe-error').textContent=error.message;
      btn.disabled=false;btn.textContent='Payer par carte bancaire';
    }
  });
}

function initPayPal(){
  const cfg=window.SITE_CFG||{};
  const B=window.SITE_BASE||'./';
  paypal.Buttons({
    createOrder:async(d,a)=>{
      const missing=validateDelivery();
      if(missing.length){alert('Champs manquants : '+missing.join(', '));throw new Error('Validation failed');}
      const delivery=getDeliveryInfo();
      // Montant calculé côté client (PayPal le valide côté serveur à la capture)
      const sub=cartTotal(),cfg2=window.SITE_CFG||{},ship=sub>=(cfg2.shipping_free||35)?0:(cfg2.shipping||4.9);
      return a.order.create({purchase_units:[{
        amount:{value:(sub+ship).toFixed(2),currency_code:cfg.currency||'EUR'},
        description:'Commande Café Maison'
      }]});
    },
    onApprove:async(d,a)=>{
      const o=await a.order.capture();
      const delivery=getDeliveryInfo();
      await fetch(B+'api/paypal_capture.php',{
        method:'POST',headers:{'Content-Type':'application/json'},
        body:JSON.stringify({order_id:o.id,cart,...delivery})
      });
      localStorage.removeItem(CART_KEY);
      location.href=B+'pages/order_success.php?method=paypal&ref='+o.id;
    },
    onError:err=>{console.error(err);alert('Erreur PayPal. Vérifiez votre connexion et réessayez.');},
    style:{layout:'vertical',color:'gold',shape:'rect',label:'pay',height:44}
  }).render('#paypal-button-container');
}

/* ── Admin ──────────────────────── */
function initAdmin(){
  const t=document.getElementById('admToggle'),sb=document.getElementById('admSb');
  if(t&&sb){
    on(t,'click',()=>sb.classList.toggle('open'));
    document.addEventListener('click',e=>{if(sb.classList.contains('open')&&!sb.contains(e.target)&&e.target!==t)sb.classList.remove('open');});
  }
}

/* ── Boot ───────────────────────── */
document.addEventListener('DOMContentLoaded',()=>{
  $$('.fade-up,.line').forEach(el=>rObs.observe(el));
  initHeader();initNav();initCart();initFilters();
  initSmooth();initCounters();initAtelier();initContact();
  initCheckout();initAdmin();initPromo();
});


/* ══ HERO CANVAS — Particules de café ══ */
function initHeroCanvas() {
  const canvas = document.getElementById('heroCanvas');
  if (!canvas) return;
  const ctx = canvas.getContext('2d');
  let W, H, particles = [];

  const COLORS = ['rgba(200,86,30,', 'rgba(122,173,92,', 'rgba(255,255,255,', 'rgba(200,86,30,'];

  class Particle {
    constructor() { this.reset(true); }
    reset(init) {
      this.x = Math.random() * W;
      this.y = init ? Math.random() * H : H + 20;
      this.r = Math.random() * 2.5 + .5;
      this.vx = (Math.random() - .5) * .4;
      this.vy = -(Math.random() * .6 + .2);
      this.alpha = Math.random() * .5 + .1;
      this.color = COLORS[Math.floor(Math.random() * COLORS.length)];
      this.spin = (Math.random() - .5) * .02;
      this.angle = Math.random() * Math.PI * 2;
    }
    update() {
      this.x += this.vx; this.y += this.vy;
      this.angle += this.spin;
      this.alpha -= .0008;
      if (this.y < -20 || this.alpha <= 0) this.reset(false);
    }
    draw() {
      ctx.save();
      ctx.translate(this.x, this.y);
      ctx.rotate(this.angle);
      ctx.globalAlpha = Math.max(0, this.alpha);
      ctx.beginPath();
      // Forme grain de café stylisé
      ctx.ellipse(0, 0, this.r * 2, this.r, 0, 0, Math.PI * 2);
      ctx.fillStyle = this.color + this.alpha + ')';
      ctx.fill();
      ctx.restore();
    }
  }

  function resize() {
    W = canvas.width = canvas.offsetWidth;
    H = canvas.height = canvas.offsetHeight;
  }

  function init() {
    resize();
    particles = Array.from({length: 80}, () => new Particle());
  }

  let raf;
  function loop() {
    ctx.clearRect(0, 0, W, H);
    particles.forEach(p => { p.update(); p.draw(); });
    raf = requestAnimationFrame(loop);
  }

  init(); loop();
  window.addEventListener('resize', resize, {passive:true});

  // Stopper hors viewport (performance)
  const obs = new IntersectionObserver(([e]) => {
    if (e.isIntersecting) loop();
    else cancelAnimationFrame(raf);
  });
  obs.observe(canvas);
}

/* ══ HERO CARD — Tilt 3D au survol souris ══ */
function initCardTilt() {
  const card = document.getElementById('heroCard');
  if (!card) return;
  const wrap = card.closest('.h-card-wrap');
  on(wrap, 'mousemove', e => {
    const rect = card.getBoundingClientRect();
    const cx = rect.left + rect.width / 2;
    const cy = rect.top + rect.height / 2;
    const dx = (e.clientX - cx) / (rect.width / 2);
    const dy = (e.clientY - cy) / (rect.height / 2);
    card.style.transform = `perspective(800px) rotateY(${dx * 12}deg) rotateX(${-dy * 8}deg) scale(1.03)`;
  });
  on(wrap, 'mouseleave', () => {
    card.style.transform = 'perspective(800px) rotateY(0) rotateX(0) scale(1)';
  });
}

/* ══ HERO BAND — Scroll infini tags ══ */
function initHeroBand() {
  const band = document.querySelector('.hero-band .wrap');
  if (!band) return;
  // Dupliquer pour loop infini
  band.innerHTML += band.innerHTML;
  band.style.display = 'flex';
  band.style.animation = 'slideTagsL 22s linear infinite';
}

/* Ajouter au boot */
const _origBoot = document.addEventListener;
document.addEventListener('DOMContentLoaded', () => {
  initHeroCanvas();
  initCardTilt();
  initHeroBand();
}, {once: false});


/* ══ CODE PROMO ══ */
const PROMO_KEY = 'cafe_promo_v1';
let activePromo = null;
// Restaurer le code promo depuis localStorage (survit aux changements de page)
try { activePromo = JSON.parse(localStorage.getItem(PROMO_KEY)) || null; } catch(e) {}
window.getPromoDiscount = function(sub) { return activePromo ? Math.min(sub, activePromo.discount||0) : 0; };
window.getActivePromo = function() { return activePromo; };
window.clearPromo = function() { activePromo = null; try { localStorage.removeItem(PROMO_KEY); } catch(e) {} };

function initPromo() {
  const btn  = $('#applyPromo');
  const inp  = $('#promoInput');
  const msg  = $('#promoMsg');
  if (!btn || !inp) return;
  // Pré-remplir si un code était actif (restauré depuis localStorage)
  if (activePromo && inp) {
    inp.value = activePromo.code || '';
    if (msg) {
      msg.textContent = '✓ Code ' + activePromo.code + ' appliqué : ' + (activePromo.label||'');
      msg.style.color = 'var(--vr2)';
    }
    renderCart(); // Recalculer avec la remise restaurée
  }

  on(btn, 'click', async () => {
    const code = inp.value.trim().toUpperCase();
    if (!code) return;
    btn.disabled = true; btn.textContent = '…';
    msg.textContent = ''; msg.style.color = '';

    try {
      const cfg = window.SITE_CFG || {};
      const B   = window.SITE_BASE || './';
      const url = cfg.promo_url || B + 'api/check_promo.php';
      const r   = await fetch(url, {
        method: 'POST',
        headers: {'Content-Type':'application/json'},
        body: JSON.stringify({ code, total: cartTotal(), cart })
      });
      const data = await r.json();
      if (data.ok) {
        activePromo = data;
        try { localStorage.setItem(PROMO_KEY, JSON.stringify(data)); } catch(e) {}
        window.getPromoDiscount = function(sub) { return Math.min(sub, data.discount||0); };
        window.getActivePromo = function() { return activePromo; };
        msg.textContent = '✓ Code ' + data.code + ' appliqué : ' + data.label;
        msg.style.color = 'var(--vr2)';
        renderCart();
        updateOrderSummary?.();
      } else {
        msg.textContent = '✗ ' + data.error;
        msg.style.color = 'var(--or)';
        activePromo = null;
      }
    } catch(e) {
      msg.textContent = 'Erreur réseau.';
      msg.style.color = 'var(--or)';
    }
    btn.disabled = false; btn.textContent = 'Appliquer';
  });

  // Enlever le promo
  on($('#removePromo'), 'click', () => {
    activePromo = null;
    try { localStorage.removeItem(PROMO_KEY); } catch(e) {}
    window.getPromoDiscount = function(sub) { return 0; };
    inp.value = '';
    msg.textContent = ''; renderCart(); updateOrderSummary?.();
  });
}

function getPromoDiscount(subtotal) {
  if (!activePromo) return 0;
  return Math.min(subtotal, activePromo.discount || 0);
}

/* ══ HERO CANVAS PARTICLES ══ */
function initHeroCanvas() {
  const canvas = document.getElementById('heroCanvas');
  if (!canvas) return;
  const ctx = canvas.getContext('2d');
  let W, H, particles = [];

  function resize() {
    W = canvas.width  = canvas.offsetWidth;
    H = canvas.height = canvas.offsetHeight;
  }
  resize();
  window.addEventListener('resize', resize, { passive: true });

  const COLORS = ['rgba(200,86,30,', 'rgba(42,76,30,', 'rgba(122,173,92,', 'rgba(140,58,16,'];

  class Particle {
    constructor() { this.reset(); this.y = Math.random() * H; }
    reset() {
      this.x  = Math.random() * W;
      this.y  = H + 10;
      this.r  = Math.random() * 2.5 + .5;
      this.vx = (Math.random() - .5) * .4;
      this.vy = -(Math.random() * .6 + .3);
      this.a  = Math.random() * .5 + .1;
      this.c  = COLORS[Math.floor(Math.random() * COLORS.length)];
    }
    update() {
      this.x += this.vx; this.y += this.vy;
      this.a -= .001;
      if (this.y < -10 || this.a <= 0) this.reset();
    }
    draw() {
      ctx.beginPath();
      ctx.arc(this.x, this.y, this.r, 0, Math.PI * 2);
      ctx.fillStyle = this.c + Math.max(0, this.a) + ')';
      ctx.fill();
    }
  }

  for (let i = 0; i < 80; i++) particles.push(new Particle());

  function animate() {
    ctx.clearRect(0, 0, W, H);
    particles.forEach(p => { p.update(); p.draw(); });
    requestAnimationFrame(animate);
  }
  animate();
}

document.addEventListener('DOMContentLoaded', () => { initHeroCanvas(); });


/* ══ UPLOAD IMAGE DRAG & DROP ══
   Usage : <div class="img-uploader" data-field="image" data-preview="prev_id"></div>
   + <input type="hidden" name="image" id="fld_image" value="">
   + <img id="prev_id" ...>
*/
function initUploaders() {
  document.querySelectorAll('.img-uploader').forEach(box => {
    const field   = box.dataset.field;
    const prevId  = box.dataset.preview;
    const input   = document.getElementById('fld_' + field);
    const preview = prevId ? document.getElementById(prevId) : null;

    if (!input) return;

    box.innerHTML = `
      <div class="img-drop-zone" id="dz_${field}">
        <div class="img-drop-inner">
          <div style="font-size:2rem;margin-bottom:.4rem">📁</div>
          <div style="font-size:.82rem;font-weight:600">Glisser une image ici</div>
          <div style="font-size:.72rem;color:var(--gy2);margin:.2rem 0">ou</div>
          <label style="cursor:pointer">
            <span class="act-btn" style="display:inline-block">Choisir un fichier</span>
            <input type="file" accept="image/*" style="display:none" id="fi_${field}">
          </label>
          <div style="font-size:.65rem;color:var(--gy2);margin-top:.4rem">JPG, PNG, WebP · max 5 MB</div>
        </div>
        <div class="img-progress" id="prog_${field}" style="display:none">
          <div class="img-progress-bar" id="progbar_${field}"></div>
          <span id="progtxt_${field}">Envoi…</span>
        </div>
      </div>
    `;

    const dz  = document.getElementById('dz_' + field);
    const fi  = document.getElementById('fi_' + field);
    const prog = document.getElementById('prog_' + field);
    const pbar = document.getElementById('progbar_' + field);
    const ptxt = document.getElementById('progtxt_' + field);

    function uploadFile(file) {
      if (!file) return;
      const fd = new FormData();
      fd.append('file', file);
      // CSRF
      const csrf = document.querySelector('[name=_csrf]');
      if (csrf) fd.append('_csrf', csrf.value);

      prog.style.display = 'flex';
      pbar.style.width   = '0%';
      ptxt.textContent   = 'Envoi…';

      const xhr = new XMLHttpRequest();
      xhr.upload.addEventListener('progress', e => {
        if (e.lengthComputable) {
          const pct = Math.round(e.loaded/e.total*100);
          pbar.style.width = pct + '%';
        }
      });
      xhr.addEventListener('load', () => {
        try {
          const r = JSON.parse(xhr.responseText);
          if (r.ok) {
            input.value = r.url;
            if (preview) { preview.src = r.url; preview.style.display='block'; }
            ptxt.textContent = '✓ Image téléversée !';
            pbar.style.background = 'var(--vr2)';
            pbar.style.width = '100%';
            setTimeout(() => { prog.style.display='none'; pbar.style.background=''; }, 2000);
          } else {
            ptxt.textContent = '✗ ' + (r.error || 'Erreur');
            pbar.style.background = 'var(--or)';
          }
        } catch(e) {
          ptxt.textContent = '✗ Erreur serveur';
        }
      });
      const B = window.SITE_BASE || (window.location.pathname.includes('/admin/') ? '../' : './');
      xhr.open('POST', B + 'api/upload.php');
      xhr.send(fd);
    }

    // Drag & Drop
    dz.addEventListener('dragover', e => { e.preventDefault(); dz.classList.add('drag-over'); });
    dz.addEventListener('dragleave', () => dz.classList.remove('drag-over'));
    dz.addEventListener('drop', e => {
      e.preventDefault(); dz.classList.remove('drag-over');
      const f = e.dataTransfer.files[0];
      if (f) uploadFile(f);
    });
    fi.addEventListener('change', () => { if (fi.files[0]) uploadFile(fi.files[0]); });
  });
}
document.addEventListener('DOMContentLoaded', initUploaders);
