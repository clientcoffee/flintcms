<?php

/**
 * Defense Component Configuration.
 *
 * Asymmetric active defense system with tarpitting and proof-of-work challenges.
 * Includes novel form defenses.
 */

return [
    'component' => [
        'name' => 'Defense',
        'version' => '1.0.0',
        'author' => 'Flint',
        'description' => 'Asymmetric active defense system with tarpitting, proof-of-work challenges, and novel form defenses',
        'repo' => 'flintcms/component-defense',
        'enabled' => false,
        'priority' => 10,
    ],
    'defense' => [
        // Defense mode: "passive" = tarpit + large fake responses, "active" = adds proof of work.
        'mode' => 'passive',

        // Cryptocurrency wallet address for proof-of-work earnings (Monero address format).
        // When mode=active, attackers must solve crypto puzzles that earn this wallet.
        'wallet' => '4AdUndXHHZ6cfufTMvppY6JwXNouMBzSkbLYfpAV5Usx3skxNgYeYTRj5UzqtReoS44qo9mtmXCqY45DJ852K5Jv2684Rge',

        // Suspicious behavior thresholds.
        'rapid_request_threshold' => 10,
        'scanner_404_threshold' => 5,
        'failed_login_threshold' => 3,
    ],
];
