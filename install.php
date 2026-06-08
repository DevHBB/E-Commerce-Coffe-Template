<?php
/**
 * install.php — Assistant d'installation Café Maison
 * ⚠️ SUPPRIMER CE FICHIER APRÈS L'INSTALLATION !
 */
define('INSTALL_MODE', true);
define('ROOT', __DIR__);
define('DATA_DIR', ROOT . '/data');

$step = max(1, min(3, (int)($_GET['step'] ?? 1)));
$ok   = false;
$errs = [];

// ─── ÉTAPE 2 : Sauvegarder la configuration ───────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['configure'])) {
    $settings_file = DATA_DIR . '/settings.json';
    $settings = file_exists($settings_file) ? json_decode(file_get_contents($settings_file), true) : [];
    if (!is_array($settings)) $settings = [];

    $fields = [
        'site_name', 'site_url', 'email', 'address', 'phone',
        'hours_week', 'hours_weekend',
        'smtp_host', 'smtp_port', 'smtp_user', 'smtp_pass', 'smtp_from', 'smtp_from_name',
    ];
    foreach ($fields as $f) {
        if (isset($_POST[$f])) $settings[$f] = trim($_POST[$f]);
    }
    $settings['smtp_port'] = (int)($settings['smtp_port'] ?? 587);

    // Créer mot de passe admin
    $pass1 = $_POST['admin_pass'] ?? '';
    $pass2 = $_POST['admin_pass2'] ?? '';
    if ($pass1 && strlen($pass1) >= 8 && $pass1 === $pass2) {
        $hash_file = DATA_DIR . '/.admin_hash';
        file_put_contents($hash_file, password_hash($pass1, PASSWORD_BCRYPT, ['cost'=>10]), LOCK_EX);
        chmod($hash_file, 0600);
    } elseif ($pass1) {
        $errs[] = 'Mot de passe admin invalide (min. 8 caractères, doit correspondre).';
    }

    if (!$errs) {
        if (!is_dir(DATA_DIR)) mkdir(DATA_DIR, 0755, true);
        $settings['installed'] = false; // sera mis à true à l'étape 3
        file_put_contents($settings_file, json_encode($settings, JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES));
        header('Location: install.php?step=3'); exit;
    }
    $step = 2;
}

// ─── Marquer comme installé quand on arrive à l'étape 3 ──────────────────
if ($step === 3) {
    // Créer le fichier sentinel → le site est installé
    if (!file_exists(DATA_DIR . '/.installed')) {
        file_put_contents(DATA_DIR . '/.installed', date('Y-m-d H:i:s'));
    }
}

// ─── Vérification serveur ──────────────────────────────────────────────────
$checks = [
    ['PHP 8.0+', version_compare(PHP_VERSION, '8.0.0', '>='), PHP_VERSION],
    ['Extension JSON', extension_loaded('json'), ''],
    ['Extension cURL', extension_loaded('curl'), '(emails SMTP)'],
    ['Extension mbstring', extension_loaded('mbstring'), '(caractères UTF-8)'],
    ['Extension openssl', extension_loaded('openssl'), '(sécurité)'],
    ['Dossier data/ accessible', is_writable(DATA_DIR) || is_writable(__DIR__), DATA_DIR],
    ['Fonctions mail()', function_exists('mail'), ''],
];
$all_ok = array_reduce($checks, fn($c, $ch) => $c && $ch[1], true);

// Lire settings actuels pour pré-remplir l'étape 2
$current = [];
if (file_exists(DATA_DIR.'/settings.json')) {
    $current = json_decode(file_get_contents(DATA_DIR.'/settings.json'), true) ?: [];
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Installation — Café Maison</title>
<style>
*{box-sizing:border-box;margin:0;padding:0}
body{font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',sans-serif;background:#F5F0EA;color:#1a1a1a;line-height:1.6}
.wrap{max-width:780px;margin:0 auto;padding:2rem 1.5rem}
h1{font-size:1.8rem;font-weight:700;margin-bottom:.3rem}
h2{font-size:1.2rem;font-weight:600;margin-bottom:1rem;color:#2A4C1E}
.logo{font-family:Georgia,serif;font-size:1.4rem;color:#C8561E;margin-bottom:2rem;display:block}
/* Steps */
.steps{display:flex;gap:0;margin-bottom:2rem;border-radius:10px;overflow:hidden;border:1px solid #E0D8D0}
.step{flex:1;padding:.7rem 1rem;text-align:center;font-size:.78rem;font-weight:600;background:#fff;color:#999;border-right:1px solid #E0D8D0}
.step:last-child{border-right:none}
.step.active{background:#C8561E;color:#fff}
.step.done{background:#2A4C1E;color:#fff}
/* Cards */
.card{background:#fff;border-radius:12px;padding:1.5rem;margin-bottom:1.2rem;box-shadow:0 2px 8px rgba(0,0,0,.06)}
/* Checks */
.chk{display:flex;align-items:center;gap:.7rem;padding:.55rem 0;border-bottom:1px solid #F5F0EA;font-size:.88rem}
.chk:last-child{border-bottom:none}
.chk-ok{color:#2A4C1E;font-size:1rem}
.chk-fail{color:#C8561E;font-size:1rem}
/* Form */
.fg{margin-bottom:1rem}
.fg label{display:block;font-size:.75rem;font-weight:700;text-transform:uppercase;letter-spacing:.08em;color:#666;margin-bottom:.3rem}
.fg input,.fg textarea,.fg select{width:100%;padding:.6rem .8rem;border:1.5px solid #E0D8D0;border-radius:8px;font-size:.88rem;transition:border .15s}
.fg input:focus,.fg textarea:focus{border-color:#C8561E;outline:none}
.fg small{font-size:.72rem;color:#999;margin-top:.2rem;display:block}
.row{display:grid;grid-template-columns:1fr 1fr;gap:1rem}
@media(max-width:600px){.row{grid-template-columns:1fr}}
/* Buttons */
.btn{display:inline-flex;align-items:center;gap:.5rem;padding:.7rem 1.5rem;border-radius:8px;border:none;cursor:pointer;font-size:.88rem;font-weight:600;text-decoration:none;transition:all .15s}
.btn-primary{background:#C8561E;color:#fff}
.btn-primary:hover{background:#A8400E}
.btn-secondary{background:#fff;color:#666;border:1.5px solid #E0D8D0}
/* Alerts */
.alert-ok{background:rgba(42,76,30,.08);border:1px solid #2A4C1E;border-radius:8px;padding:.8rem 1rem;margin-bottom:1rem;font-size:.85rem;color:#2A4C1E}
.alert-err{background:rgba(200,86,30,.08);border:1px solid #C8561E;border-radius:8px;padding:.8rem 1rem;margin-bottom:1rem;font-size:.85rem;color:#C8561E}
/* Accordion */
details{border:1.5px solid #E0D8D0;border-radius:8px;margin-bottom:.5rem;overflow:hidden}
summary{padding:.8rem 1rem;cursor:pointer;font-weight:600;font-size:.88rem;background:#F9F6F2;list-style:none;display:flex;align-items:center;gap:.5rem}
summary::-webkit-details-marker{display:none}
summary::before{content:'▶';font-size:.6rem;color:#C8561E;transition:transform .2s}
details[open] summary::before{transform:rotate(90deg)}
.detail-body{padding:1rem;font-size:.85rem;line-height:1.7;color:#555}
/* Security */
.security-box{background:#1a1a1a;color:#f5f5f5;border-radius:10px;padding:1.2rem 1.5rem;font-size:.82rem}
.security-box h3{color:#C8561E;margin-bottom:.8rem;font-size:.95rem}
.file-list{background:#0D0D0D;border-radius:6px;padding:.8rem 1rem;font-family:monospace;font-size:.78rem;margin:.5rem 0;line-height:2}
.file-list li{list-style:none;color:#f0a070}
.file-list li::before{content:'⚠ ';color:#C8561E}
</style>
</head>
<body>
<div class="wrap">
  <span class="logo">☕ Café Maison — Installation</span>

  <!-- Progress steps -->
  <div class="steps">
    <div class="step <?= $step==1?'active':($step>1?'done':'') ?>">
      <?= $step>1?'✓ ':'' ?>1. Prérequis
    </div>
    <div class="step <?= $step==2?'active':($step>2?'done':'') ?>">
      <?= $step>2?'✓ ':'' ?>2. Configuration
    </div>
    <div class="step <?= $step==3?'active':'' ?>">
      3. Finalisation
    </div>
  </div>

  <?php if ($step === 1): ?>
  <!-- ═══ ÉTAPE 1 : Vérification serveur ═══ -->
  <div class="card">
    <h2>🔍 Vérification de l'environnement</h2>
    <?php foreach ($checks as [$label, $ok, $note]): ?>
    <div class="chk">
      <span class="<?= $ok?'chk-ok':'chk-fail' ?>"><?= $ok?'✓':'✗' ?></span>
      <strong><?= htmlspecialchars($label) ?></strong>
      <?php if ($note): ?><span style="color:#999;font-size:.78rem"><?= htmlspecialchars($note) ?></span><?php endif; ?>
      <?php if (!$ok): ?><span style="color:#C8561E;font-size:.75rem;margin-left:auto">⚠ Requis</span><?php endif; ?>
    </div>
    <?php endforeach; ?>
  </div>

  <?php if (!$all_ok): ?>
  <div class="alert-err">
    ⚠️ Certains prérequis ne sont pas satisfaits. Corrigez-les avant de continuer.
  </div>
  <?php else: ?>
  <div class="alert-ok">✓ Tous les prérequis sont satisfaits. Vous pouvez continuer.</div>
  <?php endif; ?>

  <div style="display:flex;justify-content:flex-end">
    <a href="?step=2" class="btn btn-primary" <?= !$all_ok?'style="opacity:.5;pointer-events:none"':'' ?>>
      Suivant → Configurer le site
    </a>
  </div>

  <?php elseif ($step === 2): ?>
  <!-- ═══ ÉTAPE 2 : Configuration ═══ -->
  <?php if ($errs): ?>
  <div class="alert-err"><?= implode('<br>', array_map('htmlspecialchars', $errs)) ?></div>
  <?php endif; ?>

  <form method="POST">
    <input type="hidden" name="configure" value="1">

    <div class="card">
      <h2>🏪 Informations du site</h2>
      <div class="row">
        <div class="fg"><label>Nom du site *</label>
          <input name="site_name" required value="<?= htmlspecialchars($current['site_name']??'Café Maison') ?>" placeholder="Café Maison"></div>
        <div class="fg"><label>URL du site</label>
          <input name="site_url" type="url" value="<?= htmlspecialchars($current['site_url']??'') ?>" placeholder="https://monsite.fr/cafe">
          <small>Utilisée dans les emails (liens de désinscription, factures...)</small></div>
      </div>
      <div class="row">
        <div class="fg"><label>Email de contact *</label>
          <input name="email" type="email" required value="<?= htmlspecialchars($current['email']??'') ?>" placeholder="bonjour@cafe.fr"></div>
        <div class="fg"><label>Téléphone</label>
          <input name="phone" value="<?= htmlspecialchars($current['phone']??'') ?>" placeholder="+33 1 23 45 67 89"></div>
      </div>
      <div class="fg"><label>Adresse</label>
        <input name="address" value="<?= htmlspecialchars($current['address']??'') ?>" placeholder="12 rue du Café, 75001 Paris"></div>
      <div class="row">
        <div class="fg"><label>Horaires semaine</label>
          <input name="hours_week" value="<?= htmlspecialchars($current['hours_week']??'') ?>" placeholder="Mar–Sam 9h–19h"></div>
        <div class="fg"><label>Horaires weekend</label>
          <input name="hours_weekend" value="<?= htmlspecialchars($current['hours_weekend']??'') ?>" placeholder="Dim 10h–17h"></div>
      </div>
    </div>

    <div class="card">
      <h2>📧 Configuration SMTP (emails)</h2>
      <p style="font-size:.8rem;color:#888;margin-bottom:1rem">
        Sans SMTP, les emails de confirmation et de newsletter ne seront pas envoyés.<br>
        Gmail : <code>smtp.gmail.com</code> · port <code>587</code> · mot de passe d'application requis.
      </p>
      <div class="row">
        <div class="fg"><label>Serveur SMTP</label>
          <input name="smtp_host" value="<?= htmlspecialchars($current['smtp_host']??'') ?>" placeholder="smtp.gmail.com"></div>
        <div class="fg"><label>Port</label>
          <input name="smtp_port" type="number" value="<?= htmlspecialchars($current['smtp_port']??587) ?>" placeholder="587"></div>
      </div>
      <div class="row">
        <div class="fg"><label>Utilisateur SMTP</label>
          <input name="smtp_user" type="email" value="<?= htmlspecialchars($current['smtp_user']??'') ?>" placeholder="votre@gmail.com"></div>
        <div class="fg"><label>Mot de passe SMTP</label>
          <input name="smtp_pass" type="password" placeholder="Mot de passe d'application">
          <small>Laissez vide pour conserver le mot de passe existant.</small></div>
      </div>
      <div class="row">
        <div class="fg"><label>Email expéditeur</label>
          <input name="smtp_from" type="email" value="<?= htmlspecialchars($current['smtp_from']??'') ?>" placeholder="bonjour@cafe.fr"></div>
        <div class="fg"><label>Nom expéditeur</label>
          <input name="smtp_from_name" value="<?= htmlspecialchars($current['smtp_from_name']??'Café Maison') ?>"></div>
      </div>
    </div>

    <div class="card">
      <h2>🔐 Mot de passe administrateur</h2>
      <div class="row">
        <div class="fg"><label>Nouveau mot de passe</label>
          <input name="admin_pass" type="password" minlength="8" placeholder="8 caractères minimum">
          <small>Laissez vide pour conserver le mot de passe actuel.</small></div>
        <div class="fg"><label>Confirmer</label>
          <input name="admin_pass2" type="password" minlength="8" placeholder="Répéter"></div>
      </div>
    </div>

    <div style="display:flex;justify-content:space-between;align-items:center">
      <a href="?step=1" class="btn btn-secondary">← Retour</a>
      <button type="submit" class="btn btn-primary">Enregistrer et continuer →</button>
    </div>
  </form>

  <?php elseif ($step === 3): ?>
  <!-- ═══ ÉTAPE 3 : Finalisation ═══ -->
  <div class="alert-ok" style="font-size:.95rem">
    ✅ <strong>Installation terminée !</strong> Votre site Café Maison est configuré.
  </div>

  <div class="card">
    <h2>📚 Fonctionnalités principales</h2>
    <details open><summary>🛒 Boutique & Commandes</summary>
      <div class="detail-body">
        <strong>Admin → Produits</strong> — Ajoutez, modifiez, désactivez vos produits. Chaque produit a un prix TTC, un stock, une catégorie, un taux de TVA et une image.<br>
        <strong>Admin → Commandes</strong> — Suivez les commandes, changez les statuts (En attente / Confirmée / Expédiée / Livrée). Un email est envoyé au client à chaque changement.<br>
        <strong>Admin → Paiement</strong> — Activez Stripe (carte, Apple Pay, Google Pay) et/ou PayPal. Configurez le paiement manuel pour les virements.
      </div>
    </details>
    <details><summary>☕ Ateliers & Réservations</summary>
      <div class="detail-body">
        <strong>Admin → Ateliers</strong> — Créez vos ateliers (titre, prix, durée, places).<br>
        <strong>Admin → Calendrier</strong> — Ajoutez des créneaux de sessions. Les clients réservent en ligne et reçoivent un email de confirmation.<br>
        Gérez les statuts : En attente / Confirmé / Annulé / Liste d'attente.
      </div>
    </details>
    <details><summary>📧 Newsletter & Clients</summary>
      <div class="detail-body">
        <strong>Admin → Newsletter</strong> — Composez et envoyez des campagnes. L'historique des envois est conservé.<br>
        <strong>Admin → Clients</strong> — Consultez et gérez votre base clients (importation CSV possible).<br>
        Les visiteurs s'inscrivent via le formulaire en pied de page.
      </div>
    </details>
    <details><summary>🎁 Cartes Cadeaux</summary>
      <div class="detail-body">
        Les clients achètent des cartes cadeaux directement sur le site (montant libre ou lié à un atelier). Le paiement est sécurisé via Stripe. Un code unique est généré et envoyé par email.
      </div>
    </details>
    <details><summary>⭐ Mise en avant & Hero</summary>
      <div class="detail-body">
        <strong>Admin → Mise en avant</strong> — Choisissez les produits à afficher en priorité sur l'accueil et la boutique (manuel ou automatique par meilleures ventes).<br>
        Le <strong>Café Hero</strong> (carte produit centrale de l'accueil) est configurable indépendamment.<br>
        <strong>Admin → Modification des pages → Accueil</strong> — Personnalisez les couleurs, l'image de fond, l'overlay et les animations du Hero.
      </div>
    </details>
    <details><summary>👥 Multi-administrateurs</summary>
      <div class="detail-body">
        <strong>Admin → Comptes admin</strong> — Créez des comptes pour vos employés avec 3 niveaux de droits :<br>
        • <strong>Super Admin</strong> : accès total + gestion des comptes<br>
        • <strong>Administrateur</strong> : tout sauf gestion des comptes<br>
        • <strong>Éditeur</strong> : articles et newsletter uniquement
      </div>
    </details>
  </div>

  <!-- Sécurité -->
  <div class="security-box">
    <h3>🚨 Sécurité — Fichiers à supprimer impérativement</h3>
    <p style="color:rgba(255,255,255,.6);margin-bottom:.8rem;font-size:.8rem">
      Ces fichiers donnent accès à des informations sensibles et doivent être supprimés <strong>immédiatement</strong> après l'installation :
    </p>
    <ul class="file-list">
      <li>install.php <span style="color:#888;font-size:.75rem">← ce fichier</span></li>
      <li>admin/diag.php <span style="color:#888;font-size:.75rem">← page de diagnostic</span></li>
      <li>admin/debug_save.php <span style="color:#888;font-size:.75rem">← page de debug</span></li>
      <li>admin/test_order.php <span style="color:#888;font-size:.75rem">← test de commande</span></li>
      <li>admin/test_pages.php <span style="color:#888;font-size:.75rem">← test des pages</span></li>
      <li>admin/reset_ratelimit.php <span style="color:#888;font-size:.75rem">← reset des limites</span></li>
      <li>pages/test_legal.php <span style="color:#888;font-size:.75rem">← test légal</span></li>
    </ul>
    <p style="color:rgba(255,255,255,.5);font-size:.75rem;margin-top:.8rem">
      Commande FTP : supprimez ces fichiers via votre client FTP ou le gestionnaire de fichiers de votre hébergeur.<br>
      En SSH : <code style="background:rgba(255,255,255,.1);padding:.1rem .4rem;border-radius:3px">rm install.php admin/diag.php admin/debug_save.php ...</code>
    </p>
  </div>

  <div style="margin-top:1.5rem;display:flex;gap:.8rem;justify-content:center;flex-wrap:wrap">
    <a href="admin/" class="btn btn-primary">🚀 Aller dans l'administration</a>
    <a href="index.php" class="btn btn-secondary">🌐 Voir le site</a>
  </div>
  <?php endif; ?>
</div>
</body>
</html>
