<?php
/**
 * Vendetta — Game configuratie
 * Alle ranks, crimes en game-instellingen op één plek.
 */

// ============================================================
// RANKS — altijd op te halen via getRanksArray()
// ============================================================
function getRanksArray(): array {
    return [
        1  => ['name' => 'Straatveger',   'xp' => 0],
        2  => ['name' => 'Kruimeldief',   'xp' => 100],
        3  => ['name' => 'Dief',          'xp' => 300],
        4  => ['name' => 'Gangster',      'xp' => 700],
        5  => ['name' => 'Crimineel',     'xp' => 1500],
        6  => ['name' => 'Bendelid',      'xp' => 3000],
        7  => ['name' => 'Capo',          'xp' => 6000],
        8  => ['name' => 'Onderbaas',     'xp' => 12000],
        9  => ['name' => 'Baas',          'xp' => 25000],
        10 => ['name' => 'Godfather',     'xp' => 50000],
    ];
}

// ============================================================
// CRIMES — altijd op te halen via getCrimesArray()
// ============================================================
function getCrimesArray(): array {
    return [
        'zakkenrollen' => [
            'name'         => 'Zakkenrollen',
            'desc'         => 'Steel een portemonnee van een toerist op het strand.',
            'min_rank'     => 1,
            'energy_cost'  => 5,
            'min_reward'   => 20,
            'max_reward'   => 60,
            'xp_reward'    => 3,
            'success_rate' => 90,
        ],
        'winkeldiefstal' => [
            'name'         => 'Winkeldiefstal',
            'desc'         => 'Jat wat spullen uit een drukke supermarkt.',
            'min_rank'     => 2,
            'energy_cost'  => 10,
            'min_reward'   => 80,
            'max_reward'   => 180,
            'xp_reward'    => 6,
            'success_rate' => 80,
        ],
        'autodiefstal' => [
            'name'         => 'Autodiefstal',
            'desc'         => 'Kraak een BMW open in de parkeergarage.',
            'min_rank'     => 3,
            'energy_cost'  => 20,
            'min_reward'   => 300,
            'max_reward'   => 600,
            'xp_reward'    => 12,
            'success_rate' => 70,
        ],
        'overval' => [
            'name'         => 'Overval tankstation',
            'desc'         => 'Bewapende overval op een tankstation aan de snelweg.',
            'min_rank'     => 4,
            'energy_cost'  => 35,
            'min_reward'   => 800,
            'max_reward'   => 1500,
            'xp_reward'    => 25,
            'success_rate' => 60,
        ],
        'juwelier' => [
            'name'         => 'Juwelier kraken',
            'desc'         => 'Breek in bij een juwelier in de binnenstad.',
            'min_rank'     => 5,
            'energy_cost'  => 50,
            'min_reward'   => 2000,
            'max_reward'   => 3500,
            'xp_reward'    => 45,
            'success_rate' => 55,
        ],
        'bankoverval' => [
            'name'         => 'Bankoverval',
            'desc'         => 'De grote klapper — een bank op het industriegebied.',
            'min_rank'     => 7,
            'energy_cost'  => 90,
            'min_reward'   => 8000,
            'max_reward'   => 14000,
            'xp_reward'    => 150,
            'success_rate' => 40,
        ],
    ];
}

// ============================================================
// GLOBALE VARIABELEN — voor compatibiliteit met bestaande code
// ============================================================
$RANKS  = getRanksArray();
$CRIMES = getCrimesArray();

// ============================================================
// GAME INSTELLINGEN
// ============================================================

const ENERGY_REGEN_SECONDS = 60;
const START_MONEY          = 500;
const START_ENERGY         = 100;
const START_HEALTH         = 100;

// ============================================================
// HULPFUNCTIES
// ============================================================

/**
 * Bepaal huidige rank op basis van XP.
 * Als $RANKS niet wordt meegegeven, wordt het intern opgehaald.
 */
function getRankData(int $xp, ?array $RANKS = null): array {
    if (!is_array($RANKS) || empty($RANKS)) {
        $RANKS = getRanksArray();
    }

    $current = $RANKS[1];
    $currentLevel = 1;

    foreach ($RANKS as $level => $r) {
        if ($xp >= $r['xp']) {
            $current = $r;
            $currentLevel = $level;
        }
    }

    $next = $RANKS[$currentLevel + 1] ?? null;

    return [
        'level'      => $currentLevel,
        'name'       => $current['name'],
        'next'       => $next,
        'next_level' => $next ? $currentLevel + 1 : null,
    ];
}