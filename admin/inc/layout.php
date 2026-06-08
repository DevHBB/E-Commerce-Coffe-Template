<?php
// admin/inc/layout.php — header de l'interface admin
$msgs_all  = db('messages');
$unread_ct = count(array_filter($msgs_all, fn($m) => !($m['read'] ?? false)));
$cur_adm   = $cur_adm ?? '';
// Admin est toujours à 1 niveau de profondeur (admin/)
// les assets sont à ../../assets/ depuis admin/inc/ ou ../assets/ depuis admin/
$A = '../'; // base vers racine depuis admin/*.php
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title><?= isset($at) ? e($at).' — ' : '' ?>Admin · Café Maison</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@0,400;0,500;1,400&family=Jost:wght@300;400;500;600&display=swap" rel="stylesheet">
<link rel="stylesheet" href="<?= $A ?>assets/css/style.css">
<meta name="robots" content="noindex,nofollow">
</head>
<body class="adm-body">

<aside class="adm-sb" id="admSb">
  <div class="sb-hd">
    <div class="sb-logo">Café <span>Maison</span></div>
    <div class="sb-role">Administration</div>
  </div>
  <nav class="sb-nav">
    <div class="sb-sec">Général</div>
    <a href="<?= $A ?>admin/" class="sb-a <?= $cur_adm==='dash'?'on':'' ?>">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/></svg>
      Tableau de bord
    </a>
    <a href="<?= $A ?>admin/analytics.php" class="sb-a <?= $cur_adm==='analytics'?'on':'' ?>">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/></svg>
      Analytics
    </a>
    <div class="sb-sec">Contenu</div>
    <a href="<?= $A ?>admin/articles.php" class="sb-a <?= $cur_adm==='art'?'on':'' ?>">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></svg>
      Articles / Blog
    </a>
    <a href="<?= $A ?>admin/articles.php?a=new" class="sb-a" style="padding-left:2.7rem;font-size:.79rem;">+ Nouvel article</a>
    <div class="sb-sec">Boutique</div>
    <a href="<?= $A ?>admin/categories.php" class="sb-a <?= $cur_adm==='categories'?'on':'' ?>">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M4 6h16M4 10h16M4 14h16M4 18h16"/></svg>
      Catégories
    </a>
    <a href="<?= $A ?>admin/products.php" class="sb-a <?= $cur_adm==='prd'?'on':'' ?>">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M6 2L3 6v14a2 2 0 002 2h14a2 2 0 002-2V6l-3-4z"/><line x1="3" y1="6" x2="21" y2="6"/><path d="M16 10a4 4 0 01-8 0"/></svg>
      Produits
    </a>
    <a href="<?= $A ?>admin/featured.php" class="sb-a <?= $cur_adm==='featured'?'on':'' ?>">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/></svg>
      Mise en avant ⭐
    </a>
    <a href="<?= $A ?>admin/products.php?a=new" class="sb-a" style="padding-left:2.7rem;font-size:.79rem;">+ Nouveau produit</a>
    <div class="sb-sec">Ateliers</div>
    <a href="<?= $A ?>admin/workshops.php" class="sb-a <?= $cur_adm==='wk'?'on':'' ?>">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
      Ateliers
    </a>
    <a href="<?= $A ?>admin/calendar.php" class="sb-a <?= $cur_adm==='calendar'?'on':'' ?>">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
      Calendrier & Résa
    </a>
    <div class="sb-sec">Clients & Commandes</div>
    <a href="<?= $A ?>admin/clients.php" class="sb-a <?= $cur_adm==='clients'?'on':'' ?>">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M17 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 00-3-3.87"/><path d="M16 3.13a4 4 0 010 7.75"/></svg>
      Fiches clients
    </a>
    <a href="<?= $A ?>admin/orders.php" class="sb-a <?= $cur_adm==='orders'?'on':'' ?>">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M6 2L3 6v14a2 2 0 002 2h14a2 2 0 002-2V6l-3-4z"/><line x1="3" y1="6" x2="21" y2="6"/><path d="M16 10a4 4 0 01-8 0"/></svg>
      Commandes
    </a>
    <a href="<?= $A ?>admin/giftcards.php" class="sb-a <?= $cur_adm==='giftcards'?'on':'' ?>">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><rect x="2" y="7" width="20" height="14" rx="2"/><path d="M16 7V5a2 2 0 00-4 0v2M8 7V5a2 2 0 014 0v2"/><line x1="12" y1="12" x2="12" y2="17"/><line x1="9.5" y1="14.5" x2="14.5" y2="14.5"/></svg>
      Cartes cadeaux
    </a>
    <a href="<?= $A ?>admin/invoices.php" class="sb-a <?= $cur_adm==='invoices'?'on':'' ?>">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/><polyline points="10 9 9 9 8 9"/></svg>
      Facturation
    </a>
    <a href="<?= $A ?>admin/test_order.php" class="sb-a <?= $cur_adm==='test_order'?'on':'' ?>" style="opacity:.7">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M9 3H5a2 2 0 00-2 2v4m6-6h10a2 2 0 012 2v4M9 3v18m0 0h10a2 2 0 002-2V9M9 21H5a2 2 0 01-2-2V9m0 0h18"/></svg>
      Commande test
    </a>
    <div class="sb-sec">Communication</div>
    <a href="<?= $A ?>admin/messages.php" class="sb-a <?= $cur_adm==='msg'?'on':'' ?>">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/></svg>
      Messages
      <?php if ($unread_ct): ?><span style="background:var(--or);color:#fff;font-size:.58rem;padding:.13rem .42rem;border-radius:100px;margin-left:auto;font-weight:700;"><?= $unread_ct ?></span><?php endif; ?>
    </a>
    <a href="<?= $A ?>admin/newsletter.php" class="sb-a <?= in_array($cur_adm,['newsletter','nl_history'])?'on':'' ?>">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/></svg>
      Newsletter
      <?php $nl_ct=count(array_filter(db('newsletter'),fn($s)=>$s['active']??true)); if($nl_ct): ?><span style="background:var(--vr2);color:#fff;font-size:.55rem;padding:.1rem .38rem;border-radius:100px;margin-left:auto"><?= $nl_ct ?></span><?php endif; ?>
    </a>
    <a href="<?= $A ?>admin/reviews.php" class="sb-a <?= $cur_adm==='reviews'?'on':'' ?>">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/></svg>
      Avis clients
      <?php $rv_p=count(array_filter(db('reviews'),fn($r)=>!($r['approved']??false))); if($rv_p): ?><span style="background:var(--or);color:#fff;font-size:.55rem;padding:.1rem .38rem;border-radius:100px;margin-left:auto"><?= $rv_p ?></span><?php endif; ?>
    </a>
    <div class="sb-sec">Quiz & Découverte</div>
    <a href="<?= $A ?>admin/quizzes.php" class="sb-a <?= $cur_adm==='quizzes'?'on':'' ?>">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><circle cx="12" cy="12" r="10"/><path d="M9.09 9a3 3 0 015.83 1c0 2-3 3-3 3"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
      Quiz guidés
    </a>
    <div class="sb-sec">Codes promo</div>
    <a href="<?= $A ?>admin/promos.php" class="sb-a <?= $cur_adm==='promos'?'on':'' ?>">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M20.59 13.41l-7.17 7.17a2 2 0 01-2.83 0L2 12V2h10l8.59 8.59a2 2 0 010 2.82z"/><line x1="7" y1="7" x2="7.01" y2="7"/></svg>
      Codes promo
    </a>
    <div class="sb-sec">Configuration</div>
    <a href="<?= $A ?>admin/pages.php" class="sb-a <?= $cur_adm==='pages'?'on':'' ?>">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><rect x="3" y="3" width="18" height="18" rx="2"/><path d="M3 9h18M9 21V9"/></svg>
      Modification des pages
    </a>
    <a href="<?= $A ?>admin/update.php" class="sb-a <?= $cur_adm==='update'?'on':'' ?>">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><polyline points="1 4 1 10 7 10"/><polyline points="23 20 23 14 17 14"/><path d="M20.49 9A9 9 0 0 0 5.64 5.64L1 10m22 4l-4.64 4.36A9 9 0 0 1 3.51 15"/></svg>
      Mise à jour
    </a>
    <a href="<?= $A ?>admin/log.php" class="sb-a <?= $cur_adm==='log'?'on':'' ?>">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></svg>
      Journal admin
    </a>
    <a href="<?= $A ?>admin/settings.php" class="sb-a <?= $cur_adm==='cfg'?'on':'' ?>">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 00.33 1.82l.06.06a2 2 0 010 2.83 2 2 0 01-2.83 0l-.06-.06a1.65 1.65 0 00-1.82-.33 1.65 1.65 0 00-1 1.51V21a2 2 0 01-4 0v-.09A1.65 1.65 0 009 19.4a1.65 1.65 0 00-1.82.33l-.06.06a2 2 0 01-2.83 0 2 2 0 010-2.83l.06-.06A1.65 1.65 0 004.68 15a1.65 1.65 0 00-1.51-1H3a2 2 0 010-4h.09A1.65 1.65 0 004.6 9a1.65 1.65 0 00-.33-1.82l-.06-.06a2 2 0 010-2.83 2 2 0 012.83 0l.06.06A1.65 1.65 0 009 4.68a1.65 1.65 0 001-1.51V3a2 2 0 014 0v.09a1.65 1.65 0 001 1.51 1.65 1.65 0 001.82-.33l.06-.06a2 2 0 012.83 0 2 2 0 010 2.83l-.06.06A1.65 1.65 0 0019.4 9a1.65 1.65 0 001.51 1H21a2 2 0 010 4h-.09a1.65 1.65 0 00-1.51 1z"/></svg>
      Paramètres
    </a>
    <a href="<?= $A ?>admin/payment.php" class="sb-a <?= $cur_adm==='payment'?'on':'' ?>">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><rect x="1" y="4" width="22" height="16" rx="2"/><line x1="1" y1="10" x2="23" y2="10"/></svg>
      Paiement
    </a>
    <a href="<?= $A ?>admin/shipping.php" class="sb-a <?= $cur_adm==='shipping'?'on':'' ?>">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><rect x="1" y="3" width="15" height="13" rx="1"/><path d="M16 8h4l3 3v5h-7V8z"/><circle cx="5.5" cy="18.5" r="2.5"/><circle cx="18.5" cy="18.5" r="2.5"/></svg>
      Livraison
    </a>
    <?php if (admin_can('all')): ?>
    <a href="<?= $A ?>admin/admins.php" class="sb-a <?= $cur_adm==='admins'?'on':'' ?>">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
      Comptes admin
    </a>
    <?php endif; ?>
  </nav>
  <div class="sb-ft">
    <form method="POST" action="<?= $A ?>admin/logout.php">
      <?= csrf_field() ?>
      <button type="submit" class="sb-out">
        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M9 21H5a2 2 0 01-2-2V5a2 2 0 012-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>
        Déconnexion
      </button>
    </form>

    <a href="<?= $A ?>" target="_blank" style="font-size:.72rem;color:rgba(255,255,255,.22);text-decoration:none;display:block;margin-top:.6rem;">Voir le site →</a>
  </div>
</aside>

<!-- Overlay mobile sidebar -->
<div id="admOverlay" onclick="closeSb()" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.5);z-index:999;cursor:pointer"></div>

<div class="adm-main">
  <!-- Header mobile -->
  <div class="adm-mob-bar" id="admMobBar" style="position:relative;z-index:1001">
    <button id="admToggle" onclick="toggleSb()" aria-label="Menu" style="background:none;border:none;cursor:pointer;padding:.4rem;color:var(--bk);display:flex;flex-direction:column;gap:5px">
      <span style="display:block;width:22px;height:2px;background:currentColor;border-radius:2px;transition:all .25s" id="hbLine1"></span>
      <span style="display:block;width:22px;height:2px;background:currentColor;border-radius:2px;transition:all .25s" id="hbLine2"></span>
      <span style="display:block;width:16px;height:2px;background:currentColor;border-radius:2px;transition:all .25s" id="hbLine3"></span>
    </button>
    <span style="font-family:var(--f1);font-size:1rem;font-weight:500;color:var(--bk)"><?= isset($at) ? e($at) : 'Admin' ?></span>
    <a href="../admin/" style="font-size:.75rem;color:var(--gy2);text-decoration:none">☕ Café Maison</a>
  </div>
