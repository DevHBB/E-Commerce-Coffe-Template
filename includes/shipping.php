<?php
/**
 * includes/shipping.php — Calcul des frais de livraison par poids
 *
 * Système configurable via l'admin (tranches poids/prix).
 * Compatible Option A (tranches manuelles) et prévu pour Option B (API Colissimo).
 */

// ── Pays → Zone (A/B/C) ───────────────────────────────────────────────────────
function shipping_zone(string $cc): string {
    $cc = strtoupper(trim($cc));
    if ($cc === 'FR') return 'FR';
    $zone_a = ['DE','AT','BE','ES','IT','LU','NL','PT','GR','IE','PL','SE','DK',
                'FI','CZ','HU','RO','BG','SK','SI','HR','EE','LV','LT','MT','CY',
                'CH','MC','AD','LI','GB'];
    $zone_b = ['AL','RS','XK','BA','MK','ME','MD','UA','BY','NO','IS',
                'MA','DZ','TN','TR','AM','AZ','GE'];
    if (in_array($cc, $zone_a, true)) return 'A';
    if (in_array($cc, $zone_b, true)) return 'B';
    return 'C';
}

// ── Lire les tranches depuis la config ────────────────────────────────────────
function shipping_tiers(string $zone, array $cfg): array {
    if ($zone === 'FR')     $key = 'shipping_tiers_fr';
    elseif ($zone === 'A') $key = 'shipping_tiers_intl_a';
    elseif ($zone === 'B') $key = 'shipping_tiers_intl_b';
    else                   $key = 'shipping_tiers_intl_c';
    $raw = $cfg[$key] ?? '';
    if (!$raw) return [];
    $tiers = json_decode($raw, true);
    return is_array($tiers) ? $tiers : [];
}

// ── Calcul du tarif selon le poids ────────────────────────────────────────────
function shipping_cost_for_weight(int $weight_g, string $zone, array $cfg): float {
    $tiers = shipping_tiers($zone, $cfg);
    if (!$tiers) {
        // Fallback: tarif fixe si pas de tranches configurées
        if ($zone === 'FR')     return (float)($cfg['shipping_cost'] ?? 4.90);
        elseif ($zone === 'A') return (float)($cfg['shipping_intl_zone_a'] ?? 14.90);
        elseif ($zone === 'B') return (float)($cfg['shipping_intl_zone_b'] ?? 22.50);
        else                   return (float)($cfg['shipping_intl_zone_c'] ?? 32.00);
    }
    foreach ($tiers as $tier) {
        if ($weight_g <= (int)($tier['max_g'] ?? 0)) {
            return round((float)($tier['price'] ?? 0), 2);
        }
    }
    // Dépasse la dernière tranche: prendre le prix maximum
    return round((float)end($tiers)['price'], 2);
}

// ── Poids total du panier ─────────────────────────────────────────────────────
function cart_total_weight(array $cart, array $prod_idx, int $packaging_g = 150): int {
    $total = $packaging_g;
    foreach ($cart as $item) {
        $id  = $item['id'] ?? '';
        $qty = max(1, (int)($item['qty'] ?? 1));
        if ((strncmp($id, 'gc-', 3) === 0)) continue; // carte cadeau = pas de poids physique
        $wg     = (int)($prod_idx[$id]['weight_g'] ?? 250);
        $total += $wg * $qty;
    }
    return max(1, $total);
}

// ── Fonction principale ───────────────────────────────────────────────────────
function calculate_shipping(string $country_code, float $cart_total, string $mode,
                             array $cfg, int $weight_g = 0): array {
    if ($mode === 'pickup') {
        return ['cost' => 0, 'zone' => 'pickup', 'blocked' => false,
                'weight_g' => $weight_g, 'message' => 'Retrait gratuit en boutique'];
    }

    $zone = shipping_zone($country_code);

    // Vérifier si international autorisé
    if ($zone !== 'FR' && empty($cfg['shipping_international_enabled'])) {
        return ['cost' => 0, 'zone' => $zone, 'blocked' => true, 'weight_g' => $weight_g,
                'message' => $cfg['shipping_intl_blocked_msg']
                    ?? 'Les livraisons hors France ne sont actuellement pas disponibles.'];
    }

    // Option B: API Colissimo si activée et configurée
    if (!empty($cfg['colissimo_api_enabled']) && !empty($cfg['colissimo_api_login']) && $weight_g > 0) {
        $api_cost = colissimo_api_rate($country_code, $weight_g, $cfg);
        if ($api_cost !== null) {
            $free_from = (float)($cfg['shipping_free_from'] ?? 35);
            if ($zone === 'FR' && $cart_total >= $free_from && $free_from > 0) {
                return ['cost'=>0,'zone'=>$zone,'blocked'=>false,'weight_g'=>$weight_g,'message'=>'Livraison offerte'];
            }
            return ['cost'=>round($api_cost,2),'zone'=>$zone,'blocked'=>false,'weight_g'=>$weight_g,
                    'message'=>'Colissimo (tarif officiel '.$weight_g.'g) — '.number_format($api_cost,2,',','').' €'];
        }
        // Si l'API échoue → fallback sur les tranches manuelles
    }

    // Calculer le coût via tranches manuelles
    $use_weight = !empty($cfg['shipping_use_weight']) && $weight_g > 0;
    if ($use_weight) {
        $cost = shipping_cost_for_weight($weight_g, $zone, $cfg);
    } else {
        if ($zone === 'FR')     $cost = (float)($cfg['shipping_cost'] ?? 4.90);
        elseif ($zone === 'A') $cost = (float)($cfg['shipping_intl_zone_a'] ?? 14.90);
        elseif ($zone === 'B') $cost = (float)($cfg['shipping_intl_zone_b'] ?? 22.50);
        else                   $cost = (float)($cfg['shipping_intl_zone_c'] ?? 32.00);
    }

    // Supplément Brexit Royaume-Uni
    if (strtoupper($country_code) === 'GB') $cost += 3.00;

    // Livraison gratuite à partir d'un certain montant (France uniquement)
    $free_from = (float)($cfg['shipping_free_from'] ?? 35);
    if ($zone === 'FR' && $cart_total >= $free_from && $free_from > 0) {
        return ['cost' => 0, 'zone' => $zone, 'blocked' => false, 'weight_g' => $weight_g,
                'message' => 'Livraison offerte dès '
                    .number_format($free_from, 0, ',', ' ').' €'];
    }

    $weight_info = $weight_g > 0 ? ' ('.$weight_g.'g)' : '';
    $zone_labels = ['FR'=>'France','A'=>'Europe','B'=>'Europe Est & Maghreb','C'=>'International'];
    return [
        'cost'     => round($cost, 2),
        'zone'     => $zone,
        'blocked'  => false,
        'weight_g' => $weight_g,
        'message'  => 'Colissimo '.$zone_labels[$zone].$weight_info
                      .' — '.number_format($cost, 2, ',', ' ').' €',
    ];
}

// ── Appel API Colissimo pour le tarif officiel ───────────────────────────────
function colissimo_api_rate(string $country_code, int $weight_g, array $cfg): ?float {
    $login    = $cfg['colissimo_api_login']    ?? '';
    $password = $cfg['colissimo_api_password'] ?? '';
    $zip      = $cfg['colissimo_sender_zip']   ?? '75001';
    if (!$login || !$password) return null;

    $is_fr    = strtoupper($country_code) === 'FR';
    $product  = $is_fr ? 'DOM' : 'COLI';
    $weight_kg= round($weight_g / 1000, 3);

    $payload = json_encode([
        'login'      => $login,
        'password'   => $password,
        'date'       => date('d/m/Y'),
        'sender'     => ['postalCode' => $zip, 'countryCode' => 'FR'],
        'addressee'  => ['postalCode' => '00000', 'countryCode' => strtoupper($country_code)],
        'parcel'     => ['weight' => $weight_kg],
        'product'    => $product,
    ]);

    $ch = curl_init('https://ws.colissimo.fr/sls-ws/SlsServiceWSImpl/2.0/getPrice');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_HTTPHEADER     => ['Content-Type: application/json','Accept: application/json'],
        CURLOPT_POSTFIELDS     => $payload,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_TIMEOUT        => 5,
    ]);
    $response = curl_exec($ch);
    curl_close($ch);

    if (!$response) return null;
    $data = json_decode($response, true);
    // L'API retourne le prix dans data.price
    $price = (float)($data['price'] ?? $data['data']['price'] ?? 0);
    return $price > 0 ? $price : null;
}

// ── Liste pays pour le checkout ───────────────────────────────────────────────
function shipping_countries(): array {
    return [
        'FR' => '🇫🇷 France (métropole)',
        '_1' => '─────────────',
        'DE' => '🇩🇪 Allemagne',   'AT' => '🇦🇹 Autriche',    'BE' => '🇧🇪 Belgique',
        'BG' => '🇧🇬 Bulgarie',    'HR' => '🇭🇷 Croatie',      'CY' => '🇨🇾 Chypre',
        'DK' => '🇩🇰 Danemark',    'ES' => '🇪🇸 Espagne',      'EE' => '🇪🇪 Estonie',
        'FI' => '🇫🇮 Finlande',    'GR' => '🇬🇷 Grèce',        'HU' => '🇭🇺 Hongrie',
        'IE' => '🇮🇪 Irlande',     'IT' => '🇮🇹 Italie',       'LV' => '🇱🇻 Lettonie',
        'LT' => '🇱🇹 Lituanie',    'LU' => '🇱🇺 Luxembourg',   'MT' => '🇲🇹 Malte',
        'NL' => '🇳🇱 Pays-Bas',    'PL' => '🇵🇱 Pologne',      'PT' => '🇵🇹 Portugal',
        'CZ' => '🇨🇿 Rép. Tchèque','RO' => '🇷🇴 Roumanie',     'SK' => '🇸🇰 Slovaquie',
        'SI' => '🇸🇮 Slovénie',    'SE' => '🇸🇪 Suède',         'CH' => '🇨🇭 Suisse',
        'GB' => '🇬🇧 Royaume-Uni', 'NO' => '🇳🇴 Norvège',      'MC' => '🇲🇨 Monaco',
        'AD' => '🇦🇩 Andorre',
        '_2' => '─────────────',
        'MA' => '🇲🇦 Maroc',       'DZ' => '🇩🇿 Algérie',      'TN' => '🇹🇳 Tunisie',
        '_3' => '─────────────',
        'US' => '🇺🇸 États-Unis',  'CA' => '🇨🇦 Canada',       'JP' => '🇯🇵 Japon',
        'AU' => '🇦🇺 Australie',   'BR' => '🇧🇷 Brésil',       'SG' => '🇸🇬 Singapour',
    ];
}
