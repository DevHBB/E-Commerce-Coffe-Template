<?php
require_once __DIR__ . '/../includes/config.php';
security_headers();
header('Content-Type: application/xml; charset=utf-8');

$cfg = cfg_get();
$base = (isset($_SERVER['HTTPS'])&&$_SERVER['HTTPS']?'https':'http').'://'.($_SERVER['HTTP_HOST']??'localhost').'/cafe/';

$urls = [];
$now  = date('Y-m-d');

// Pages principales
$static = [
    [''                  ,1.0,'daily'],
    ['pages/shop.php'    ,0.9,'daily'],
    ['pages/atelier.php' ,0.8,'weekly'],
    ['pages/blog.php'    ,0.8,'weekly'],
    ['pages/quiz.php'    ,0.6,'monthly'],
    ['pages/compare.php' ,0.6,'monthly'],
    ['pages/giftcard.php',0.7,'monthly'],
    ['pages/booking.php' ,0.8,'weekly'],
];
if (!empty($cfg['page_story_enabled'])) $static[] = ['pages/story.php',0.6,'monthly'];
foreach ($static as [$url,$pri,$freq]) {
    $urls[] = "<url><loc>{$base}{$url}</loc><lastmod>$now</lastmod><changefreq>$freq</changefreq><priority>$pri</priority></url>";
}
// Produits actifs
foreach (db('products') as $p) {
    if (!($p['active']??true) || !($p['slug']??'')) continue;
    $urls[] = "<url><loc>{$base}pages/shop.php?p=".urlencode($p['slug'])."</loc><lastmod>".($p['updated']??$now)."</lastmod><changefreq>weekly</changefreq><priority>0.7</priority></url>";
}
// Articles publiés
foreach (db('articles') as $a) {
    if (!($a['published']??false) || !($a['slug']??'')) continue;
    $urls[] = "<url><loc>{$base}pages/article.php?s=".urlencode($a['slug'])."</loc><lastmod>".($a['updated']??$now)."</lastmod><changefreq>monthly</changefreq><priority>0.6</priority></url>";
}

echo "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n";
echo "<urlset xmlns=\"http://www.sitemaps.org/schemas/sitemap/0.9\">\n";
foreach ($urls as $u) echo "  $u\n";
echo "</urlset>";
