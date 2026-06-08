<?php
/**
 * Café Maison — Mailer
 * Envoi d'emails via SMTP (PHPMailer-like en pur PHP) ou mail() natif
 * Toutes les fonctions retournent bool
 */
if (!function_exists('cm_mail')) {

// Retourne l'URL absolue de base du site (ex: https://bobbix.fr/storage/cafe)
function site_base_url(): string {
    $cfg  = cfg_get();
    $url  = rtrim($cfg['site_url'] ?? '', '/');
    if ($url) return $url;
    // Auto-détection depuis le contexte HTTP
    if (!isset($_SERVER['HTTP_HOST'])) return '';
    $proto    = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $doc_root = rtrim(str_replace('\\', '/', $_SERVER['DOCUMENT_ROOT'] ?? ''), '/');
    $root_abs = rtrim(str_replace('\\', '/', ROOT), '/');
    $rel      = $doc_root ? str_replace($doc_root, '', $root_abs) : '';
    return $proto . '://' . $_SERVER['HTTP_HOST'] . $rel;
}

function cm_mail_build_smtp(string $to, string $toName, string $subject, string $html, string $text = ''): bool {
    $cfg = cfg_get();
    $host    = $cfg['smtp_host']      ?? '';
    $port    = (int)($cfg['smtp_port']    ?? 587);
    $user    = $cfg['smtp_user']      ?? '';
    $pass    = $cfg['smtp_pass'] ?? ''; // stocké en clair depuis config v2
    $from    = $cfg['smtp_from']      ?? $cfg['email'] ?? '';
    $fromN   = $cfg['smtp_from_name'] ?? ($cfg['site_name'] ?? 'Café Maison');

    // Si pas de SMTP configuré → mail() natif
    if (!$host || !$user || !$from) {
        return cm_mail_native($to, $toName, $subject, $html, $from, $fromN);
    }

    // Connexion SMTP manuelle (sans dépendance)
    $errno = 0; $errstr = '';
    $sock = @stream_socket_client(
        ($port === 465 ? 'ssl://' : 'tcp://') . $host . ':' . $port,
        $errno, $errstr, 10
    );
    if (!$sock) {
        error_log("[CAFE MAIL] SMTP connection failed: $errstr");
        return cm_mail_native($to, $toName, $subject, $html, $from, $fromN);
    }

    $r = function() use ($sock) { return fgets($sock, 512); };
    $s = function(string $cmd) use ($sock) { fwrite($sock, $cmd . "\r\n"); };

    $r(); // Banner
    $s('EHLO ' . gethostname()); sleep(0);
    while ($line = $r()) { if ($line[3] === ' ') break; } // Read capabilities

    // STARTTLS si port 587
    if ($port === 587) {
        $s('STARTTLS');
        $r();
        stream_socket_enable_crypto($sock, true, STREAM_CRYPTO_METHOD_TLS_CLIENT);
        $s('EHLO ' . gethostname());
        while ($line = $r()) { if ($line[3] === ' ') break; }
    }

    // AUTH LOGIN
    $s('AUTH LOGIN');
    $r();
    $s(base64_encode($user));
    $r();
    $s(base64_encode($pass));
    $resp = $r();
    if (strpos($resp, '235') === false) {
        error_log("[CAFE MAIL] SMTP auth failed: $resp");
        fclose($sock);
        return false;
    }

    // Envoi
    $s("MAIL FROM:<$from>");      $r();
    $s("RCPT TO:<$to>");          $r();
    $s('DATA');                   $r();

    $boundary = 'cm_' . bin2hex(random_bytes(8));
    $msg  = "From: =?UTF-8?B?" . base64_encode($fromN) . "?= <$from>\r\n";
    $msg .= "To: =?UTF-8?B?" . base64_encode($toName) . "?= <$to>\r\n";
    $msg .= "Subject: =?UTF-8?B?" . base64_encode($subject) . "?=\r\n";
    $msg .= "MIME-Version: 1.0\r\n";
    $msg .= "Content-Type: multipart/alternative; boundary=\"$boundary\"\r\n";
    $msg .= "Date: " . date('r') . "\r\n\r\n";
    $msg .= "--$boundary\r\nContent-Type: text/plain; charset=UTF-8\r\nContent-Transfer-Encoding: base64\r\n\r\n";
    $msg .= chunk_split(base64_encode($text ?: strip_tags($html))) . "\r\n";
    $msg .= "--$boundary\r\nContent-Type: text/html; charset=UTF-8\r\nContent-Transfer-Encoding: base64\r\n\r\n";
    $msg .= chunk_split(base64_encode($html)) . "\r\n";
    $msg .= "--$boundary--\r\n.";
    $s($msg);
    $r();
    $s('QUIT');
    fclose($sock);
    return true;
}

function cm_mail_native(string $to, string $toName, string $subject, string $html, string $from = '', string $fromN = ''): bool {
    if (!$from) { $cfg = cfg_get(); $from = $cfg['email'] ?? 'noreply@example.com'; $fromN = $fromN ?: ($cfg['smtp_from_name'] ?? ($cfg['site_name'] ?? 'Café Maison')); }
    $headers  = "MIME-Version: 1.0\r\n";
    $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
    $headers .= "From: =?UTF-8?B?" . base64_encode($fromN ?: 'Café Maison') . "?= <$from>\r\n";
    $headers .= "Reply-To: $from\r\n";
    $headers .= "X-Mailer: CafeMaison/1.0\r\n";
    $subj = '=?UTF-8?B?' . base64_encode($subject) . '?=';
    $result = @mail($to, $subj, $html, $headers);
    error_log("[CAFE MAIL NATIVE] To=$to Result=" . ($result?'OK':'FAIL') . " (configurer SMTP pour la prod)");
    return $result;
}

function cm_mail(string $to, string $toName, string $subject, string $html): bool {
    try {
        $result = cm_mail_build_smtp($to, $toName, $subject, $html);
        if ($result) {
            error_log("[CAFE MAIL OK] To=$to Subject=$subject");
        } else {
            error_log("[CAFE MAIL FAIL] To=$to Subject=$subject — vérifiez config SMTP");
        }
        return $result;
    } catch (\Throwable $e) {
        error_log('[CAFE MAIL ERROR] ' . $e->getMessage() . " To=$to");
        return false;
    }
}

// ── Templates emails ─────────────────────────────────────────
function mail_wrap(string $title, string $body, string $cta_text = '', string $cta_url = ''): string {
    $cfg  = cfg_get();
    $sn   = htmlspecialchars($cfg['site_name'] ?? 'Café Maison');
    $addr = htmlspecialchars($cfg['address']   ?? '');
    $cta  = $cta_text ? "<div style='text-align:center;margin:28px 0'><a href='$cta_url' style='background:#C8561E;color:#fff;padding:13px 32px;border-radius:4px;text-decoration:none;font-weight:600;font-size:15px'>$cta_text</a></div>" : '';
    return "<!DOCTYPE html><html><head><meta charset='UTF-8'><meta name='viewport' content='width=device-width'></head>
<body style='margin:0;padding:0;background:#F3EDE4;font-family:Helvetica,Arial,sans-serif'>
<div style='max-width:600px;margin:0 auto;padding:40px 20px'>
  <div style='background:#0C0C0C;border-radius:12px 12px 0 0;padding:28px 36px;text-align:center'>
    <h1 style='color:#fff;font-size:22px;margin:0;font-weight:400;font-style:italic'>☕ $sn</h1>
  </div>
  <div style='background:#fff;padding:36px;border-radius:0 0 12px 12px'>
    <h2 style='color:#0C0C0C;font-size:20px;margin-top:0'>$title</h2>
    $body
    $cta
  </div>
  <div style='text-align:center;padding:20px;font-size:12px;color:#999'>
    $sn · $addr<br>
    <!-- Lien de désinscription dans mail_newsletter() uniquement -->
  </div>
</div></body></html>";
}

function generate_modify_token(string $order_id): string {
    // Générer un token à usage unique valable 15 minutes
    $tokens = json_decode(@file_get_contents(DATA_DIR.'/.modify_tokens.json') ?: '{}', true) ?: [];
    $token  = bin2hex(random_bytes(24));
    $tokens[$token] = ['order_id'=>$order_id,'expires'=>time()+900,'used'=>false];
    // Nettoyer les tokens expirés
    $tokens = array_filter($tokens, fn($t) => ($t['expires']??0) > time());
    file_put_contents(DATA_DIR.'/.modify_tokens.json', json_encode($tokens), LOCK_EX);
    @chmod(DATA_DIR.'/.modify_tokens.json', 0600);
    return $token;
}

function mail_order(array $order): bool {
    $cfg = cfg_get();
    if (empty($cfg['email_order_enabled'])) return false;
    $c   = $order['customer'] ?? [];
    $to  = $c['email'] ?? '';
    if (!filter_var($to, FILTER_VALIDATE_EMAIL)) return false;
    $name  = trim(($c['fname']??'') . ' ' . ($c['lname']??''));
    $ref   = substr($order['intent_id'] ?? $order['paypal_id'] ?? $order['id'] ?? '', 0, 20);
    $items = '';
    foreach ($order['cart'] ?? [] as $it) {
        $items .= "<tr><td style='padding:8px 0;border-bottom:1px solid #F3EDE4'>{$it['emoji']} " . htmlspecialchars($it['name']) . " × {$it['qty']}</td><td style='text-align:right;padding:8px 0;border-bottom:1px solid #F3EDE4'>" . number_format(($it['price']??0)*($it['qty']??1),2,',',' ') . " €</td></tr>";
    }
    $dm   = $order['delivery_mode'] ?? 'delivery';
    $mode = $dm === 'pickup' ? '🏪 Retrait en boutique' : '🚚 Livraison à domicile';
    
    // Générer lien de modification 15min
    $token    = generate_modify_token($order['id'] ?? '');
    $base_url = (isset($_SERVER['HTTPS'])&&$_SERVER['HTTPS']?'https':'http').'://'.($_SERVER['HTTP_HOST']??'localhost').'/';
    $modify_url = $base_url . 'pages/modify_order.php?token=' . urlencode($token);
    $modify_html = "<div style='background:#FFF8F5;border:1px solid #C8561E33;border-radius:8px;padding:16px;margin:20px 0;text-align:center'>
        <p style='margin:0 0 10px;font-size:14px;color:#666'>⏰ Vous avez <strong>15 minutes</strong> pour modifier votre commande ou l'annuler :</p>
        <a href='$modify_url' style='background:#C8561E;color:#fff;padding:10px 24px;border-radius:4px;text-decoration:none;font-weight:600;font-size:14px;display:inline-block'>Modifier ma commande →</a>
        <p style='margin:10px 0 0;font-size:12px;color:#999'>Ce lien expire dans 15 minutes</p>
    </div>";
    
    // Lien facture
    $site_url    = rtrim(site_base_url(), '/');
    if (!$site_url) $site_url = rtrim($cfg['site_url'] ?? '', '/');
    $invoice_url = $site_url . '/api/invoice.php?id=' . urlencode($order['id'] ?? '');
    
    $body = "<p>Bonjour $name,</p><p>Nous avons bien reçu votre commande et la préparons avec soin.</p>
$modify_html
<table style='width:100%;border-collapse:collapse;margin:20px 0'>$items</table>
<p><strong>Total : " . number_format($order['amount']??0,2,',',' ') . " €</strong></p>
<p>Mode : $mode</p>
<p style='color:#666;font-size:13px'>Référence : $ref</p>
<div style='margin:20px 0;padding:16px;background:#F3EDE4;border-radius:8px;text-align:center'>
  <a href='$invoice_url' style='background:#2A4C1E;color:#fff;padding:10px 24px;border-radius:4px;text-decoration:none;font-weight:600;font-size:14px'>📄 Voir ma facture</a>
</div>
<p>Nous vous confirmerons l'expédition par email dès que votre colis sera en route.</p>";
    $html = mail_wrap('Votre commande est confirmée !', $body, 'Voir nos produits', ($cfg['smtp_from'] ?? ''));
    $sent = cm_mail($to, $name, 'Commande confirmée — ' . ($cfg['site_name']??'Café Maison'), $html);
    
    // Envoyer aussi la facture par email si la commande a un ID
    // (généré lors du premier accès à invoice.php — pas encore disponible ici)
    // La facture sera envoyée via admin ou lors de la confirmation paiement
    return $sent;
}

function mail_booking(array $booking, array $session, array $workshop): bool {
    $cfg = cfg_get();
    if (empty($cfg['email_booking_enabled'])) return false;
    $to   = $booking['email'] ?? '';
    $name = trim(($booking['fname']??'') . ' ' . ($booking['lname']??''));
    if (!filter_var($to, FILTER_VALIDATE_EMAIL)) return false;
    $date = date('l d F Y', strtotime($session['date'] ?? ''));
    $hour = $session['time'] ?? '';
    $wname= htmlspecialchars($workshop['name'] ?? '');
    $body = "<p>Bonjour $name,</p>
<p>Votre réservation pour l'atelier <strong>$wname</strong> est confirmée !</p>
<div style='background:#F3EDE4;border-radius:8px;padding:20px;margin:20px 0'>
  <p style='margin:0'><strong>📅 Date :</strong> $date</p>
  <p style='margin:8px 0 0'><strong>⏰ Heure :</strong> $hour</p>
  <p style='margin:8px 0 0'><strong>📍 Lieu :</strong> " . htmlspecialchars($cfg['address']??'') . "</p>
  <p style='margin:8px 0 0'><strong>👤 Participants :</strong> {$booking['guests']}</p>
</div>
<p>En cas d'empêchement, merci de nous prévenir 48h avant.</p>";
    $html = mail_wrap('Réservation confirmée !', $body, 'Voir tous les ateliers', '');
    return cm_mail($to, $name, 'Réservation atelier — ' . ($cfg['site_name']??''), $html);
}


function mail_booking_mixed(array $booking, array $session, array $workshop, int $confirmed, int $waitlist): bool {
    $cfg  = cfg_get();
    if (empty($cfg['email_booking_enabled'])) return false;
    $to   = $booking['email'] ?? '';
    $name = trim(($booking['fname']??'') . ' ' . ($booking['lname']??''));
    if (!filter_var($to, FILTER_VALIDATE_EMAIL)) return false;
    $date  = date('l d F Y', strtotime($session['date'] ?? 'now'));
    $hour  = $session['time'] ?? '';
    $wname = htmlspecialchars($workshop['name'] ?? 'Atelier');
    $sn    = htmlspecialchars($cfg['site_name'] ?? 'Café Maison');
    $addr  = htmlspecialchars($cfg['address'] ?? '');
    $body = "<p>Bonjour $name,</p>
<p>Votre demande de réservation pour l'atelier <strong>$wname</strong> a bien été enregistrée.</p>
<div style='background:#FFF3E0;border-left:4px solid #C87820;border-radius:4px;padding:16px;margin:16px 0'>
  <strong>⚠️ Places limitées — réservation partielle</strong><br><br>
  Vous avez demandé <strong>" . (int)$booking['guests'] . " place" . ((int)$booking['guests']>1?'s':'') . "</strong>, mais seulement <strong>$confirmed place" . ($confirmed>1?'s':'') . "</strong> " . ($confirmed>1?'sont disponibles':'est disponible') . " pour ce créneau.<br><br>
  ✅ <strong>$confirmed participant" . ($confirmed>1?'s':'') . "</strong> " . ($confirmed>1?'sont confirmés':'est confirmé') . "<br>
  📋 <strong>$waitlist participant" . ($waitlist>1?'s':'') . "</strong> " . ($waitlist>1?'sont placés':'est placé') . " sur <strong>liste d'attente</strong>
</div>
<div style='background:#F3EDE4;border-radius:8px;padding:20px;margin:20px 0'>
  <p style='margin:0'><strong>📅 Date :</strong> $date</p>
  <p style='margin:8px 0 0'><strong>⏰ Heure :</strong> $hour</p>
  <p style='margin:8px 0 0'><strong>📍 Lieu :</strong> $addr</p>
</div>
<p>Nous vous contacterons dès que des places supplémentaires se libèrent.</p>
<p>Cordialement,<br><em>L'équipe $sn</em></p>";
    $html = mail_wrap('Réservation partielle — ' . $wname, $body);
    return cm_mail($to, $name, "Réservation partielle — $wname", $html);
}

function mail_booking_participant(string $to, array $booking, array $session, array $workshop): bool {
    $cfg  = cfg_get();
    if (empty($cfg['email_booking_enabled'])) return false;
    if (!filter_var($to, FILTER_VALIDATE_EMAIL)) return false;
    $organizer = trim(($booking['fname']??'') . ' ' . ($booking['lname']??''));
    $date  = date('l d F Y', strtotime($session['date'] ?? 'now'));
    $hour  = $session['time'] ?? '';
    $wname = htmlspecialchars($workshop['name'] ?? 'Atelier');
    $addr  = htmlspecialchars($cfg['address'] ?? '');
    $sn    = htmlspecialchars($cfg['site_name'] ?? 'Café Maison');
    $body = "<p>Bonjour,</p>
<p><strong>$organizer</strong> vous a réservé une place pour l'atelier <strong>$wname</strong> !</p>
<div style='background:#F3EDE4;border-radius:8px;padding:20px;margin:20px 0'>
  <p style='margin:0'><strong>📅 Date :</strong> $date</p>
  <p style='margin:8px 0 0'><strong>⏰ Heure :</strong> $hour</p>
  <p style='margin:8px 0 0'><strong>📍 Lieu :</strong> $addr</p>
</div>
<p>En cas d'empêchement, merci de contacter $organizer directement.</p>
<p>À très bientôt,<br><em>L'équipe $sn</em></p>";
    $html = mail_wrap("🎓 Vous êtes invité(e) à l'atelier $wname !", $body);
    return cm_mail($to, '', "Invitation — $wname · $date", $html);
}

function mail_booking_waitlist(array $booking, array $workshop): bool {
    $cfg  = cfg_get();
    $to   = $booking['email'] ?? '';
    $name = trim(($booking['fname']??'') . ' ' . ($booking['lname']??''));
    if (!filter_var($to, FILTER_VALIDATE_EMAIL)) return false;
    $wname = htmlspecialchars($workshop['name'] ?? '');
    $body  = "<p>Bonjour $name,</p>
<p>L'atelier <strong>$wname</strong> que vous souhaitez rejoindre est complet.</p>
<p>Nous vous avons inscrit(e) sur la <strong>liste d'attente</strong>. Vous serez prévenu(e) automatiquement dès qu'une place se libère.</p>";
    $html = mail_wrap('Vous êtes sur liste d\'attente', $body);
    return cm_mail($to, $name, 'Liste d\'attente — ' . ($cfg['site_name']??''), $html);
}

function mail_booking_confirmed(array $booking, array $session, array $workshop): bool {
    $cfg  = cfg_get();
    $to   = $booking['email'] ?? '';
    $name = trim(($booking['fname']??'') . ' ' . ($booking['lname']??''));
    if (!filter_var($to, FILTER_VALIDATE_EMAIL)) return false;
    $date  = date('l d F Y', strtotime($session['date'] ?? 'now'));
    $hour  = $session['time'] ?? '';
    $wname = htmlspecialchars($workshop['name'] ?? 'Atelier');
    $sn    = htmlspecialchars($cfg['site_name'] ?? 'Café Maison');
    $addr  = htmlspecialchars($cfg['address'] ?? '');
    $body  = "<p>Bonjour $name,</p>
<p>Bonne nouvelle ! Votre réservation pour l'atelier <strong>$wname</strong> est <strong>confirmée</strong>.</p>
<div style='background:#F3EDE4;border-radius:8px;padding:20px;margin:20px 0'>
  <p style='margin:0'><strong>📅 Date :</strong> $date</p>
  <p style='margin:8px 0 0'><strong>⏰ Heure :</strong> $hour</p>
  <p style='margin:8px 0 0'><strong>📍 Lieu :</strong> $addr</p>
  <p style='margin:8px 0 0'><strong>👥 Participants :</strong> {$booking['guests']}</p>
</div>
<p>En cas d'empêchement, merci de nous prévenir au moins 48h avant.</p>
<p>À très bientôt,<br><em>L'équipe $sn</em></p>";
    $html = mail_wrap('✅ Réservation confirmée !', $body, 'Voir tous les ateliers', '');
    return cm_mail($to, $name, "✅ Réservation confirmée — $wname", $html);
}

function mail_booking_cancelled(array $booking, array $workshop, string $reason = ''): bool {
    $cfg   = cfg_get();
    $to    = $booking['email'] ?? '';
    $name  = trim(($booking['fname']??'') . ' ' . ($booking['lname']??''));
    if (!filter_var($to, FILTER_VALIDATE_EMAIL)) return false;
    $wname = htmlspecialchars($workshop['name'] ?? 'Atelier');
    $sn    = htmlspecialchars($cfg['site_name'] ?? 'Café Maison');
    $body  = "<p>Bonjour $name,</p>
<p>Nous vous informons que votre réservation pour l'atelier <strong>$wname</strong> a été <strong>annulée</strong>.</p>"
    . ($reason ? "<p><strong>Motif :</strong> " . htmlspecialchars($reason) . "</p>" : '')
    . "<p>Si vous avez réglé cet atelier en ligne, le remboursement sera effectué sous 5 à 10 jours ouvrés.</p>
<p>N'hésitez pas à nous contacter pour toute question.</p>
<p>Cordialement,<br><em>L'équipe $sn</em></p>";
    $html = mail_wrap('Annulation de votre réservation', $body, 'Voir les prochains ateliers', '');
    return cm_mail($to, $name, "Annulation — $wname", $html);
}


function mail_contact_auto(string $to, string $name): bool {
    $cfg = cfg_get();
    if (empty($cfg['email_contact_enabled'])) return false;
    if (!filter_var($to, FILTER_VALIDATE_EMAIL)) return false;
    $sn  = $cfg['site_name'] ?? 'Café Maison';
    $body = "<p>Bonjour " . htmlspecialchars($name) . ",</p>
<p>Merci pour votre message ! Nous l'avons bien reçu et vous répondrons dans les plus brefs délais (généralement sous 24h).</p>
<p>À très vite !</p>
<p><em>L'équipe $sn</em></p>";
    $html = mail_wrap('Votre message a bien été reçu', $body);
    return cm_mail($to, $name, 'Votre message — ' . $sn, $html);
}

function mail_newsletter(string $to, string $name, string $subject, string $html_content): bool {
    $cfg      = cfg_get();
    $site_url = rtrim(site_base_url(), '/');
    // Fallback: lire site_url depuis cfg si auto-détection échoue
    if (!$site_url) $site_url = rtrim($cfg['site_url'] ?? '', '/');
    $token     = hash('sha256', $to . 'cafe-unsub-' . ($cfg['site_name'] ?? 'cafemaison'));
    $unsub_url = $site_url . '/pages/unsubscribe.php?email=' . urlencode($to) . '&token=' . $token;
    $body  = "<p>Bonjour " . htmlspecialchars($name ?: 'cher abonné') . ",</p>" . $html_content . "
<p style='font-size:11px;color:#aaa;margin-top:30px;border-top:1px solid #eee;padding-top:15px'>
Vous recevez cet email car vous êtes abonné(e) à la newsletter de " . htmlspecialchars($cfg['site_name'] ?? 'Café Maison') . ".<br>
<a href='" . htmlspecialchars($unsub_url) . "' style='color:#C8561E'>Se désinscrire</a></p>";
    $html  = mail_wrap($subject, $body);
    return cm_mail($to, $name, $subject, $html);
}

function mail_order_shipped(array $order): bool {
    $cfg = cfg_get();
    if (empty($cfg['email_order_enabled'])) return false;
    $c    = $order['customer'] ?? [];
    $to   = $c['email'] ?? '';
    $name = trim(($c['fname']??'').  ' '.($c['lname']??''));
    if (!filter_var($to, FILTER_VALIDATE_EMAIL)) return false;
    $tracking = $order['tracking'] ?? '';
    $site_url = rtrim(site_base_url(), '/');
    if (!$site_url) $site_url = rtrim($cfg['site_url'] ?? '', '/');
    $invoice_url = $site_url . '/api/invoice.php?id=' . urlencode($order['id'] ?? '');
    $body = "<p>Bonjour $name,</p>
<p>Bonne nouvelle ! Votre commande <strong>Café Maison</strong> est en route 🚀</p>
" . ($tracking ? "<p><strong>Numéro de suivi :</strong> $tracking</p>" : '') . "
<p>Vos cafés fraîchement torréfiés arrivent bientôt chez vous !</p>
<div style='margin:20px 0;padding:16px;background:#F3EDE4;border-radius:8px;text-align:center'>
  <a href='$invoice_url' style='background:#2A4C1E;color:#fff;padding:10px 24px;border-radius:4px;text-decoration:none;font-weight:600;font-size:14px'>📄 Voir ma facture</a>
</div>";
    $html = mail_wrap('Votre commande est expédiée !', $body);
    return cm_mail($to, $name, 'Expédition — ' . ($cfg['site_name']??''), $html);
}

// Envoyer email pour n'importe quel changement de statut
function mail_order_status(array $order): bool {
    $cfg = cfg_get();
    if (empty($cfg['email_order_enabled'])) return false;
    $c    = $order['customer'] ?? [];
    $to   = $c['email'] ?? '';
    $name = trim(($c['fname']??'') . ' ' . ($c['lname']??''));
    if (!filter_var($to, FILTER_VALIDATE_EMAIL)) return false;
    $status = $order['status'] ?? 'pending';
    $sn     = $cfg['site_name'] ?? 'Café Maison';
    $site_url = rtrim(site_base_url(), '/');
    if (!$site_url) $site_url = rtrim($cfg['site_url'] ?? '', '/');
    $invoice_url = $site_url . '/api/invoice.php?id=' . urlencode($order['id'] ?? '');
    
    $labels = [
        'pending'    => ['⏳ Commande reçue',         'Nous avons bien reçu votre commande et la traitons.'],
        'processing' => ['⚙️ En cours de préparation','Votre commande est en cours de préparation.'],
        'shipped'    => ['🚀 Commande expédiée',       'Votre commande est en route !'],
        'delivered'  => ['✅ Commande livrée',          'Votre commande a été livrée. Bon café !'],
        'cancelled'  => ['❌ Commande annulée',         'Votre commande a été annulée. Contactez-nous pour toute question.'],
    ];
    [$title, $msg] = $labels[$status] ?? ['📦 Mise à jour de commande', 'Votre commande a été mise à jour.'];
    
    $tracking = $order['tracking'] ?? '';
    $body = "<p>Bonjour $name,</p>
<p>$msg</p>
" . ($tracking ? "<p><strong>Numéro de suivi :</strong> $tracking</p>" : '') . "
<div style='margin:20px 0;padding:16px;background:#F3EDE4;border-radius:8px;text-align:center'>
  <strong>Statut :</strong> $title<br><br>
  <a href='$invoice_url' style='background:#2A4C1E;color:#fff;padding:10px 24px;border-radius:4px;text-decoration:none;font-weight:600;font-size:14px'>📄 Voir ma facture</a>
</div>";
    $html = mail_wrap($title, $body);
    return cm_mail($to, $name, $title . ' — ' . $sn, $html);
}


function mail_review_request(array $order): bool {
    $cfg = cfg_get();
    if (empty($cfg['email_review_enabled'])) return false;
    $c    = $order['customer'] ?? [];
    $to   = $c['email'] ?? '';
    $name = trim(($c['fname']??'') . ' ' . ($c['lname']??''));
    if (!filter_var($to, FILTER_VALIDATE_EMAIL)) return false;

    $sn  = $cfg['site_name'] ?? 'Café Maison';
    $B   = (!empty($_SERVER['HTTP_HOST'])) ? 'https://' . $_SERVER['HTTP_HOST'] . '/' : '';
    
    $items_html = '';
    foreach ($order['cart'] ?? [] as $it) {
        $review_url = $B . 'pages/shop.php?review=' . urlencode($it['id'] ?? '');
        $items_html .= "
        <div style='display:flex;align-items:center;gap:1rem;padding:12px 0;border-bottom:1px solid #F3EDE4'>
          <div style='font-size:2rem'>{$it['emoji']}</div>
          <div style='flex:1'>
            <strong>" . htmlspecialchars($it['name']) . "</strong>
          </div>
          <div>
            <a href='$review_url' style='background:#C8561E;color:#fff;padding:8px 16px;border-radius:4px;text-decoration:none;font-size:13px;font-weight:600'>⭐ Donner mon avis</a>
          </div>
        </div>";
    }
    
    $body = "<p>Bonjour $name,</p>
<p>Il y a une semaine, vous avez commandé chez <strong>$sn</strong>. Nous espérons que vous avez apprécié votre café !</p>
<p>Votre avis est précieux — il aide d'autres amateurs à choisir leur café idéal :</p>
$items_html
<p style='margin-top:20px;color:#999;font-size:13px'>Merci de prendre 30 secondes pour noter votre expérience.</p>";

    $html = mail_wrap("Alors, comment était votre café ? ☕", $body);
    return cm_mail($to, $name, "Votre avis nous intéresse — $sn", $html);
}

} // end if !function_exists
