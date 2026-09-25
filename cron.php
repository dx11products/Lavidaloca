<?php
/**
 * Vendetta — Cron (volledig)
 *
 * Roep aan via:
 *   http://localhost:8000/php/cron.php?key=dx11test_2026
 *
 * Taken:
 *   1.  Energie regenereren
 *   2.  Bank rente uitkeren
 *   3.  Oorlogen afsluiten
 *   4.  Meldingen voor klaar-zijnde productie
 *   5.  Markt fluctuaties + wereld-events
 *   6.  Seizoens-effecten
 *   7.  Wereld-events afsluiten
 *   8.  Cleanup
 *   9.  Race cleanup (auto-refund)
 *   10. Ziekenhuis auto-heal
 *   11. Surprise gifts
 *   12. Random world events
 *   13. Dagelijkse statistieken
 *   14. Weekelijkse ranking
 */

require_once __DIR__ . '/config/db.php';

// ============================================================
// GEHEIME SLEUTEL
// ============================================================
const CRON_KEY = 'vendetta_geheim_2026';

if (($_GET['key'] ?? '') !== CRON_KEY) {
    http_response_code(403);
    die('Geen toegang');
}

// ============================================================
// HULPFUNCTIE
// ============================================================
function logCron(PDO $pdo, string $job, string $msg, int $affected = 0): void {
    try {
        $pdo->prepare("INSERT INTO cron_log (job_name, message, affected) VALUES (?, ?, ?)")
            ->execute([$job, $msg, $affected]);
    } catch (Exception $e) {
        // Tabel bestaat misschien nog niet — stil doorgaan
    }
}

$results = [];

// ============================================================
// 1. ENERGIE REGENEREREN
// ============================================================
try {
    $stmt = $pdo->query("
        SELECT id, energy, max_energy, energy_updated
        FROM users
        WHERE energy < max_energy
          AND (energy_updated IS NULL OR energy_updated <= NOW() - INTERVAL 60 SECOND)
    ");
    $users = $stmt->fetchAll();
    $affected = 0;

    foreach ($users as $u) {
        $now    = time();
        $last   = $u['energy_updated'] ? strtotime($u['energy_updated']) : $now;
        $regain = intdiv($now - $last, 60);
        if ($regain < 1) continue;

        $newEnergy = min((int)$u['max_energy'], (int)$u['energy'] + $regain);
        $newTime   = date('Y-m-d H:i:s', $last + ($regain * 60));

        $pdo->prepare("UPDATE users SET energy = ?, energy_updated = ? WHERE id = ?")
            ->execute([$newEnergy, $newTime, $u['id']]);
        $affected++;
    }

    logCron($pdo, 'energy', "{$affected} users bijgewerkt", $affected);
    $results[] = "⚡ Energie: {$affected} users";
} catch (Exception $e) {
    logCron($pdo, 'energy', 'FOUT: ' . $e->getMessage());
    $results[] = "⚡ Energie: FOUT";
}

// ============================================================
// 1B. BTC PRODUCTIE
// ============================================================
try {
    // Pak alle users met miners
    $stmt = $pdo->query("SELECT DISTINCT user_id FROM user_btc_miners");
    $users = $stmt->fetchAll();

    $totalGained = 0;
    $affected = 0;

    foreach ($users as $u) {
        $gained = updateUserBtcProduction($pdo, (int)$u['user_id']);
        if ($gained > 0) {
            $totalGained += $gained;
            $affected++;
        }
    }

    logCron($pdo, 'btc', "{$affected} users kregen BTC (totaal " . formatBtc($totalGained) . ")", $affected);
    if ($affected > 0) $results[] = "₿ BTC: " . formatBtc($totalGained) . " gemined";
} catch (Exception $e) {
    logCron($pdo, 'btc', 'FOUT: ' . $e->getMessage());
    $results[] = "₿ BTC: FOUT";
}

// ============================================================
// 2. BANK RENTE
// ============================================================
try {
    $stmt = $pdo->query("
        SELECT id, bank_money FROM users
        WHERE bank_money > 0
          AND (last_interest IS NULL OR last_interest < CURDATE())
    ");
    $users = $stmt->fetchAll();
    $affected = 0;

    foreach ($users as $u) {
        $interest = (int)floor((int)$u['bank_money'] * BANK_INTEREST_RATE);
        if ($interest <= 0) continue;

        $pdo->prepare("UPDATE users SET bank_money = bank_money + ?, last_interest = CURDATE() WHERE id = ?")
            ->execute([$interest, $u['id']]);
        notify($pdo, $u['id'], "🏦 Rente: €" . number_format($interest, 0, ',', '.'), '🏦');
        $affected++;
    }

    logCron($pdo, 'interest', "{$affected} users kregen rente", $affected);
    $results[] = "🏦 Rente: {$affected} users";
} catch (Exception $e) {
    logCron($pdo, 'interest', 'FOUT: ' . $e->getMessage());
    $results[] = "🏦 Rente: FOUT";
}

// ============================================================
// 3. OORLOGEN AFSLUITEN
// ============================================================
try {
    $stmt = $pdo->query("SELECT * FROM family_wars WHERE status = 'active' AND ends_at <= NOW()");
    $wars = $stmt->fetchAll();
    $affected = 0;

    foreach ($wars as $war) {
        $winnerId = null;
        if ($war['score_a'] > $war['score_b'])     $winnerId = $war['family_a_id'];
        elseif ($war['score_b'] > $war['score_a']) $winnerId = $war['family_b_id'];

        $prize = 0;
        if ($winnerId) {
            $loserId = ($winnerId == $war['family_a_id']) ? $war['family_b_id'] : $war['family_a_id'];

            $stmt2 = $pdo->prepare("SELECT money FROM families WHERE id = ?");
            $stmt2->execute([$loserId]);
            $loserMoney = (int)$stmt2->fetchColumn();

            $prize = min($loserMoney, (int)floor($loserMoney * 0.25));

            $pdo->prepare("UPDATE families SET money = money - ? WHERE id = ?")->execute([$prize, $loserId]);
            $pdo->prepare("UPDATE families SET money = money + ? WHERE id = ?")->execute([$prize, $winnerId]);

            foreach ([$winnerId, $loserId] as $fid) {
                $stmt2 = $pdo->prepare("SELECT user_id FROM family_members WHERE family_id = ?");
                $stmt2->execute([$fid]);
                foreach ($stmt2->fetchAll() as $m) {
                    notify($pdo, $m['user_id'],
                        $fid == $winnerId
                            ? "🏆 Oorlog gewonnen! Prijs: €" . number_format($prize, 0, ',', '.')
                            : "💀 Oorlog verloren. Verlies: €" . number_format($prize, 0, ',', '.'),
                        $fid == $winnerId ? '🏆' : '💀');
                }
            }
        }

        $pdo->prepare("UPDATE family_wars SET status = 'finished', winner_id = ?, prize_money = ? WHERE id = ?")
            ->execute([$winnerId, $prize, $war['id']]);
        $affected++;
    }

    logCron($pdo, 'wars', "{$affected} oorlogen afgesloten", $affected);
    $results[] = "⚔️ Oorlogen: {$affected}";
} catch (Exception $e) {
    logCron($pdo, 'wars', 'FOUT: ' . $e->getMessage());
    $results[] = "⚔️ Oorlogen: FOUT";
}

// ============================================================
// 4. MELDINGEN VOOR KLAAR-ZIJNDE PRODUCTIE
// ============================================================
try {
    $notified = 0;

    // Wiet planten
    $stmt = $pdo->query("
        SELECT up.id, up.user_id, up.yield_amount
        FROM user_plants up
        WHERE up.harvested = 0 AND up.harvest_at <= NOW()
          AND NOT EXISTS (
              SELECT 1 FROM notifications n
              WHERE n.user_id = up.user_id AND n.message LIKE CONCAT('%plant #', up.id, '%')
          )
    ");
    foreach ($stmt->fetchAll() as $p) {
        notify($pdo, $p['user_id'], "🌿 Plant #{$p['id']} klaar ({$p['yield_amount']} wiet)", '🌿');
        $notified++;
    }

    // Lab producties
    $stmt = $pdo->query("
        SELECT ulp.id, ulp.user_id, ulp.batch_amount, l.lab_type
        FROM user_lab_production ulp
        JOIN labs l ON l.`key` = ulp.lab_key
        WHERE ulp.collected = 0 AND ulp.ready_at <= NOW()
          AND NOT EXISTS (
              SELECT 1 FROM notifications n
              WHERE n.user_id = ulp.user_id AND n.message LIKE CONCAT('%batch #', ulp.id, '%')
          )
    ");
    foreach ($stmt->fetchAll() as $p) {
        notify($pdo, $p['user_id'], "🧪 Batch #{$p['id']} klaar ({$p['batch_amount']}x {$p['lab_type']})", '🧪');
        $notified++;
    }

    // Kogel producties
    $stmt = $pdo->query("
        SELECT ubp.id, ubp.user_id, ubp.batch_amount
        FROM user_bullet_production ubp
        WHERE ubp.collected = 0 AND ubp.ready_at <= NOW()
          AND NOT EXISTS (
              SELECT 1 FROM notifications n
              WHERE n.user_id = ubp.user_id AND n.message LIKE CONCAT('%kogels #', ubp.id, '%')
          )
    ");
    foreach ($stmt->fetchAll() as $p) {
        notify($pdo, $p['user_id'], "🏭 Kogels #{$p['id']} klaar ({$p['batch_amount']})", '🏭');
        $notified++;
    }

    logCron($pdo, 'plants', "{$notified} meldingen verstuurd", $notified);
    $results[] = "🌿 Meldingen: {$notified}";
} catch (Exception $e) {
    logCron($pdo, 'plants', 'FOUT: ' . $e->getMessage());
    $results[] = "🌿 Meldingen: FOUT";
}

// ============================================================
// 5. MARKT FLUCTUATIES (elke 30 min)
// ============================================================
try {
    $stmt = $pdo->query("
        SELECT ran_at FROM cron_log
        WHERE job_name = 'market'
        ORDER BY id DESC LIMIT 1
    ");
    $lastRun = $stmt->fetchColumn();
    $shouldRun = !$lastRun || (time() - strtotime($lastRun)) >= (30 * 60);

    $changed = 0;

    if ($shouldRun) {
        $stmt = $pdo->query("SELECT * FROM drug_prices");
        $prices = $stmt->fetchAll();

        foreach ($prices as $p) {
            $stmt2 = $pdo->prepare("
                SELECT AVG(buy_price) AS avg_buy, AVG(sell_price) AS avg_sell
                FROM drug_price_history
                WHERE country_key = ? AND drug_key = ?
            ");
            $stmt2->execute([$p['country_key'], $p['drug_key']]);
            $avg = $stmt2->fetch();

            $baseBuy  = $avg['avg_buy']  ? (int)$avg['avg_buy']  : (int)$p['buy_price'];
            $baseSell = $avg['avg_sell'] ? (int)$avg['avg_sell'] : (int)$p['sell_price'];

            $fluctuation = random_int(-15, 15);
            $newBuy  = max(1, (int)round($baseBuy  * (1 + $fluctuation / 100)));
            $newSell = max(1, (int)round($baseSell * (1 + $fluctuation / 100)));

            $pdo->prepare("
                UPDATE drug_prices SET buy_price = ?, sell_price = ?
                WHERE country_key = ? AND drug_key = ?
            ")->execute([$newBuy, $newSell, $p['country_key'], $p['drug_key']]);

            $pdo->prepare("
                INSERT INTO drug_price_history (country_key, drug_key, buy_price, sell_price)
                VALUES (?, ?, ?, ?)
            ")->execute([$p['country_key'], $p['drug_key'], $newBuy, $newSell]);

            $changed++;
        }

        logCron($pdo, 'market', "{$changed} prijzen gefluctueerd", $changed);
        $results[] = "📈 Markt: {$changed} prijzen";

        if (random_int(1, 100) <= 25) {
            $eventTypes = [
                ['police_raid', 'Politie-inval',  'Grote politie-inval — drugs zijn schaars, prijzen stijgen.', '🚔', 6],
                ['new_supply',  'Nieuwe lading',  'Een grote lading is binnengekomen — prijzen dalen.',          '📦', 6],
                ['market_boom', 'Markt hoogtepunt','De markt is booming — verkoopprijzen stijgen.',             '📈', 4],
                ['cartel_war',  'Kartel-oorlog',  'Kartels vechten — handel is riskant.',                       '⚔️', 8],
            ];
            $ev = $eventTypes[array_rand($eventTypes)];

            $pdo->prepare("
                INSERT INTO world_events (event_key, title, description, icon, expires_at)
                VALUES (?, ?, ?, ?, DATE_ADD(NOW(), INTERVAL ? HOUR))
            ")->execute([$ev[0], $ev[1], $ev[2], $ev[3], $ev[4]]);

            $stmt2 = $pdo->query("SELECT id FROM users WHERE last_login > NOW() - INTERVAL 7 DAY");
            foreach ($stmt2->fetchAll() as $u) {
                notify($pdo, $u['id'], "{$ev[3]} {$ev[1]} — {$ev[2]}", $ev[3]);
            }

            $results[] = "🌟 Wereld-event: {$ev[1]}";
        }
    } else {
        logCron($pdo, 'market', 'Overgeslagen (nog geen 30 min)', 0);
        $results[] = "📈 Markt: overgeslagen";
    }
} catch (Exception $e) {
    logCron($pdo, 'market', 'FOUT: ' . $e->getMessage());
    $results[] = "📈 Markt: FOUT";
}

// ============================================================
// 6. SEIZOENS-EFFECTEN
// ============================================================
try {
    $stmt = $pdo->query("SELECT * FROM season_status WHERE is_active = 1 ORDER BY id DESC LIMIT 1");
    $season = $stmt->fetch();

    if ($season && strtotime($season['ends_at']) <= time()) {
        $seasons = [
            ['Zomer 2026',  'weed_bonus',   10],
            ['Herfst 2026', 'coke_bonus',   15],
            ['Winter 2026', 'travel_bonus', 20],
            ['Lente 2027',  'weed_bonus',   25],
        ];
        $new = $seasons[array_rand($seasons)];

        $pdo->prepare("UPDATE season_status SET is_active = 0 WHERE id = ?")->execute([$season['id']]);
        $pdo->prepare("
            INSERT INTO season_status (season_name, ends_at, effect_key, effect_value, is_active)
            VALUES (?, DATE_ADD(NOW(), INTERVAL 30 DAY), ?, ?, 1)
        ")->execute([$new[0], $new[1], $new[2]]);

        logCron($pdo, 'season', "Nieuw seizoen: {$new[0]}", 1);
        $results[] = "🍂 Seizoen: {$new[0]}";
    } elseif (!$season) {
        $pdo->query("
            INSERT INTO season_status (season_name, ends_at, effect_key, effect_value, is_active)
            VALUES ('Lente 2026', DATE_ADD(NOW(), INTERVAL 30 DAY), 'weed_bonus', 20, 1)
        ");
        logCron($pdo, 'season', 'Eerste seizoen gestart', 1);
        $results[] = "🍂 Seizoen: gestart";
    }
} catch (Exception $e) {
    logCron($pdo, 'season', 'FOUT: ' . $e->getMessage());
    $results[] = "🍂 Seizoen: FOUT";
}

// ============================================================
// 7. WERELD-EVENTS AFSLUITEN
// ============================================================
try {
    $stmt = $pdo->query("
        UPDATE world_events SET is_active = 0
        WHERE is_active = 1 AND expires_at <= NOW()
    ");
    $ended = $stmt->rowCount();

    logCron($pdo, 'events', "{$ended} events afgesloten", $ended);
    if ($ended > 0) $results[] = "🌟 Events: {$ended} afgesloten";
} catch (Exception $e) {
    logCron($pdo, 'events', 'FOUT: ' . $e->getMessage());
    $results[] = "🌟 Events: FOUT";
}

// ============================================================
// 8. CLEANUP
// ============================================================
try {
    $freed = 0;

    // Gevangenis verlopen (alleen als kolommen bestaan)
    try {
        $stmt = $pdo->query("
            UPDATE users SET in_prison = 0, prison_until = NULL
            WHERE in_prison = 1 AND prison_until < NOW()
        ");
        $freed = $stmt->rowCount();
    } catch (Exception $e) {
        // Kolommen bestaan niet — negeer
    }

    // Oude logs (>7 dagen)
    $pdo->query("DELETE FROM cron_log WHERE ran_at < NOW() - INTERVAL 7 DAY");

    // Oude notificaties (>30 dagen, gelezen)
    $pdo->query("DELETE FROM notifications WHERE is_read = 1 AND created_at < NOW() - INTERVAL 30 DAY");

    // Oude prijshistorie (>30 dagen)
    try {
        $pdo->query("DELETE FROM drug_price_history WHERE recorded_at < NOW() - INTERVAL 30 DAY");
    } catch (Exception $e) {}

    logCron($pdo, 'cleanup', "{$freed} users vrijgelaten", $freed);
    $results[] = "🧹 Cleanup: {$freed} vrijgelaten";
} catch (Exception $e) {
    logCron($pdo, 'cleanup', 'FOUT: ' . $e->getMessage());
    $results[] = "🧹 Cleanup: FOUT";
}

// ============================================================
// 9. RACE CLEANUP — oude wachtende races opruimen
// ============================================================
try {
    $stmt = $pdo->query("
        SELECT r.id, r.prize_pool, r.host_id
        FROM races r
        WHERE r.status = 'waiting'
          AND r.created_at < NOW() - INTERVAL 30 MINUTE
    ");
    $abandoned = $stmt->fetchAll();
    $refunded = 0;
    $totalRefund = 0;

    foreach ($abandoned as $race) {
        $stmt2 = $pdo->prepare("SELECT user_id FROM race_participants WHERE race_id = ?");
        $stmt2->execute([$race['id']]);
        $players = $stmt2->fetchAll();

        $entryFee = count($players) > 0 ? (int)($race['prize_pool'] / count($players)) : 0;

        foreach ($players as $p) {
            $pdo->prepare("UPDATE users SET money = money + ? WHERE id = ?")
                ->execute([$entryFee, $p['user_id']]);
            notify($pdo, $p['user_id'],
                "🏁 Race geannuleerd — €" . number_format($entryFee, 0, ',', '.') . " terugbetaald.",
                '🏁');
            $totalRefund += $entryFee;
            $refunded++;
        }

        $pdo->prepare("UPDATE races SET status = 'cancelled' WHERE id = ?")
            ->execute([$race['id']]);
    }

    logCron($pdo, 'race_cleanup', "{$refunded} races geannuleerd, €" . number_format($totalRefund, 0, ',', '.') . " terugbetaald", $refunded);
    if ($refunded > 0) $results[] = "🏁 Races opgeruimd: {$refunded}";
} catch (Exception $e) {
    logCron($pdo, 'race_cleanup', 'FOUT: ' . $e->getMessage());
    $results[] = "🏁 Races: FOUT";
}

// ============================================================
// 10. HOSPITAL AUTO-HEAL
// ============================================================
try {
    $stmt = $pdo->query("
        SELECT id, health, max_health, hospital_until
        FROM users
        WHERE hospital_until IS NOT NULL
          AND hospital_until <= NOW()
    ");
    $healed = 0;

    foreach ($stmt->fetchAll() as $u) {
        $pdo->prepare("
            UPDATE users
            SET hospital_until = NULL, health = max_health
            WHERE id = ?
        ")->execute([$u['id']]);
        $healed++;
    }

    logCron($pdo, 'hospital', "{$healed} users ontslagen uit ziekenhuis", $healed);
    if ($healed > 0) $results[] = "🏥 Hersteld: {$healed}";
} catch (Exception $e) {
    logCron($pdo, 'hospital', 'FOUT: ' . $e->getMessage());
    $results[] = "🏥 Hersteld: FOUT";
}

// ============================================================
// 11. SURPRISE GIFTS
// ============================================================
try {
    $stmt = $pdo->query("
        SELECT ran_at FROM cron_log
        WHERE job_name = 'gifts'
        ORDER BY id DESC LIMIT 1
    ");
    $lastGift = $stmt->fetchColumn();
    $shouldGive = !$lastGift || (time() - strtotime($lastGift)) >= (4 * 3600);

    $gifts = 0;

    if ($shouldGive) {
        if (random_int(1, 100) <= 20) {
            $stmt = $pdo->query("
                SELECT id FROM users
                WHERE last_login > NOW() - INTERVAL 24 HOUR
                ORDER BY RAND()
                LIMIT 5
            ");
            $users = $stmt->fetchAll();

            $giftTypes = [
                ['cash',   5000,  '💰 Je hebt een geldbedrag gevonden op straat!'],
                ['cash',   10000, '💰 Een onbekende heeft je geld gegeven!'],
                ['ammo',   50,    '🔫 Je vond een doos kogels in een steeg!'],
                ['drugs',  10,    '💊 Je vond drugs in een oud pakhuis!'],
                ['energy', 50,    '⚡ Je voelt je plotseling energiek!'],
            ];

            foreach ($users as $u) {
                $gift = $giftTypes[array_rand($giftTypes)];

                if ($gift[0] === 'cash') {
                    $pdo->prepare("UPDATE users SET money = money + ? WHERE id = ?")
                        ->execute([$gift[1], $u['id']]);
                } elseif ($gift[0] === 'ammo') {
                    addAmmo($pdo, $u['id'], 'pistool', $gift[1]);
                } elseif ($gift[0] === 'drugs') {
                    addDrug($pdo, $u['id'], 'wiet', $gift[1]);
                } elseif ($gift[0] === 'energy') {
                    $pdo->prepare("
                        UPDATE users
                        SET energy = LEAST(max_energy, energy + ?), energy_updated = NOW()
                        WHERE id = ?
                    ")->execute([$gift[1], $u['id']]);
                }

                notify($pdo, $u['id'], $gift[2] . " (+" . $gift[1] . ")", '🎁');
                logActivity($pdo, $u['id'], "🎁 " . $gift[2]);

                $pdo->prepare("
                    INSERT INTO gift_log (user_id, gift_type, amount, message)
                    VALUES (?, ?, ?, ?)
                ")->execute([$u['id'], $gift[0], $gift[1], $gift[2]]);

                $gifts++;
            }

            logCron($pdo, 'gifts', "{$gifts} cadeaus uitgedeeld", $gifts);
            if ($gifts > 0) $results[] = "🎁 Cadeaus: {$gifts}";
        } else {
            logCron($pdo, 'gifts', 'Geen gift deze ronde (random)', 0);
        }
    }
} catch (Exception $e) {
    logCron($pdo, 'gifts', 'FOUT: ' . $e->getMessage())