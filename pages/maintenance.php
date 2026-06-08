<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Maintenance — <?= e($cfg_m['site_name'] ?? 'Café Maison') ?></title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@0,400;1,400&family=Jost:wght@300;400;500&display=swap" rel="stylesheet">
<link rel="stylesheet" href="<?= base_path() ?>assets/css/style.css">
<meta name="robots" content="noindex">
</head>
<body>
<div class="maint-page">
  <div class="maint-inner">
    <div class="maint-logo"><?= e($cfg_m['site_name'] ?? 'Café Maison') ?> <span>.</span></div>
    <div class="maint-icon">☕</div>
    <h1>Site en <em>maintenance</em></h1>
    <p><?= e($cfg_m['maintenance_msg'] ?? 'Nous effectuons une mise à jour. Revenez très bientôt !') ?></p>
    <p style="font-size:.8rem;color:rgba(255,255,255,.3);margin-top:2rem;">
      Une question urgente ? <a href="mailto:<?= e($cfg_m['email'] ?? '') ?>" style="color:var(--or)"><?= e($cfg_m['email'] ?? '') ?></a>
    </p>
  </div>
</div>
</body>
</html>
