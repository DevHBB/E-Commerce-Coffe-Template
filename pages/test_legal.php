<?php
require_once __DIR__ . "/../includes/config.php";
security_headers();
$cfg = cfg_get();
$B   = base_path();
echo "<h1>TEST OK</h1>";
echo "<p>cfg ok, ".count($cfg)." cles</p>";
// Maintenant tester le contenu de legal.php sans header/footer
$pkey = 'page_legal';
$pt   = $cfg[$pkey.'_title'] ?? 'Mentions légales';
echo "<p>pkey ok: ".htmlspecialchars($pt)."</p>";

// Tester le bloc FAQ qui était problématique
$items = [
  ['Vos cafés sont-ils torréfiés à la commande ?','Oui ! Chaque lot est torréfié après réception.'],
  ['Quelle est la durée de conservation ?',"6 à 12 mois dans un endroit frais et sec, à l'abri de la lumière."],
  ['Les ateliers sont-ils remboursables ?',"Oui, jusqu'à 48h avant la date de l'atelier."],
];
foreach ($items as [$q,$r]) {
    echo "<p><strong>".htmlspecialchars($q)."</strong>: ".htmlspecialchars($r)."</p>";
}
echo "<h2>TOUT OK - le probleme n'est PAS dans legal.php directement</h2>";
