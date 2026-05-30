<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\LibSqlite\SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNextPlan;

$currentTables = [
    'wp_options' => [
        ['option_id' => 1, 'option_name' => 'siteurl', 'autoload' => 'yes', 'score' => 95],
        ['option_id' => 2, 'option_name' => 'home', 'autoload' => 'yes', 'score' => 80],
        ['option_id' => 3, 'option_name' => 'rewrite_rules', 'autoload' => 'yes', 'score' => 60],
        ['option_id' => 4, 'option_name' => 'cache_seed', 'autoload' => 'no', 'score' => 30],
    ],
];
$nextTables = [
    'wp_options' => [
        ...$currentTables['wp_options'],
        ['option_id' => 5, 'option_name' => 'plugin_alpha', 'autoload' => 'yes', 'score' => 88],
        ['option_id' => 6, 'option_name' => 'theme_mods', 'autoload' => 'yes', 'score' => 70],
    ],
];

$sql = <<<'SQL'
WITH RECURSIVE q(id, label, score) AS (
    VALUES (1, 'seed', 120)
    UNION ALL
    SELECT id + 1, label || ':' || (id + 1), score - 10
      FROM q
     WHERE id < 7
     LIMIT 4 OFFSET 1
)
SELECT id,
       label,
       first_value(label) OVER (ORDER BY score DESC ROWS BETWEEN 1 PRECEDING AND 1 FOLLOWING) AS peer
  FROM q
UNION ALL
SELECT option_id AS id,
       option_name AS label,
       last_value(option_name) OVER (PARTITION BY autoload ORDER BY score DESC ROWS BETWEEN CURRENT ROW AND 1 FOLLOWING) AS peer
  FROM wp_options
 WHERE autoload = 'yes'
UNION
SELECT option_id AS id,
       option_name AS label,
       lag(option_name, 1, option_name) OVER (ORDER BY option_id) AS peer
  FROM wp_options
 WHERE score >= 70
 ORDER BY peer, id
 LIMIT 6 OFFSET 1
SQL;

$plan = SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNextPlan::compareNext188($sql, $currentTables, $nextTables);

if (($argv[1] ?? null) === '--self-test') {
    if (($plan['status'] ?? null) !== 'compound-select-window-recursive-limit-current-source-next188-ready') {
        fwrite(STDERR, "unexpected compound/window recursive LIMIT next188 status\n");
        exit(1);
    }
    if (array_column($plan['nextRows'], 'label') !== ['siteurl', 'theme_mods', 'rewrite_rules', 'theme_mods', 'seed:2', 'seed:2:3']) {
        fwrite(STDERR, "unexpected next compound/window recursive LIMIT next188 boundary\n");
        exit(1);
    }
    if (($plan['frameEndpointTape']['peerBoundary']['nextFirstPeer'] ?? null) !== 'plugin_alpha') {
        fwrite(STDERR, "unexpected next first peer boundary\n");
        exit(1);
    }

    echo "application-compound-select-window-recursive-limit-current-source-next188 self-test passed\n";
    return;
}

echo json_encode([
    'scenario' => 'application-compound-select-window-recursive-limit-current-source-next188',
    'currentLabels' => array_column($plan['currentRows'], 'label'),
    'nextLabels' => array_column($plan['nextRows'], 'label'),
    'nextPeers' => $plan['frameEndpointTape']['peerBoundary']['nextPeers'],
    'recursiveEmitted' => $plan['recursive']['currentEmittedLabels'],
    'replanReasons' => $plan['replanReasons'],
    'dependencyClosure' => $plan['dependency_closure'],
], JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR) . PHP_EOL;
