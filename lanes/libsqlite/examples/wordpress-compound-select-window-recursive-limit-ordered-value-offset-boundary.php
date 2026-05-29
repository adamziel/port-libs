<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\LibSqlite\SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNextPlan;

$currentTables = [
    'wp_options' => [
        ['option_id' => 1, 'option_name' => 'siteurl', 'autoload' => 'yes', 'score' => 96],
        ['option_id' => 2, 'option_name' => 'home', 'autoload' => 'yes', 'score' => 76],
        ['option_id' => 3, 'option_name' => 'rewrite_rules', 'autoload' => 'yes', 'score' => 66],
        ['option_id' => 4, 'option_name' => 'cache_seed', 'autoload' => 'no', 'score' => 38],
    ],
];
$nextTables = [
    'wp_options' => [
        ...$currentTables['wp_options'],
        ['option_id' => 5, 'option_name' => 'plugin_alpha', 'autoload' => 'yes', 'score' => 89],
        ['option_id' => 6, 'option_name' => 'theme_mods', 'autoload' => 'yes', 'score' => 71],
    ],
];

$sql = <<<'SQL'
WITH RECURSIVE q(id, label, score) AS (
    VALUES (1, 'seed', 125)
    UNION ALL
    SELECT id + 1, label || ':' || (id + 1), score - 11
      FROM q
     WHERE id < 7
     ORDER BY 3 DESC
     LIMIT 5 OFFSET 1
)
SELECT id,
       label,
       nth_value(label, 2) OVER (ORDER BY score DESC ROWS BETWEEN CURRENT ROW AND 2 FOLLOWING) AS peer
  FROM q
UNION ALL
SELECT option_id AS id,
       option_name AS label,
       ntile(3) OVER (ORDER BY score DESC, option_id) AS peer
  FROM wp_options
 WHERE autoload = 'yes'
UNION
SELECT option_id AS id,
       option_name AS label,
       lead(option_name, 2, option_name) OVER (ORDER BY option_id) AS peer
  FROM wp_options
 WHERE score >= 60
 ORDER BY peer, id
 LIMIT 6 OFFSET 2
SQL;

$plan = SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNextPlan::compareOrderedValueOffsetCompoundBoundary($sql, $currentTables, $nextTables);

if (($argv[1] ?? null) === '--self-test') {
    if (($plan['status'] ?? null) !== 'compound-select-window-recursive-limit-current-source-ordered-value-offset-boundary-ready') {
        fwrite(STDERR, "unexpected compound/window recursive LIMIT ordered-value-offset-boundary status\n");
        exit(1);
    }
    if (array_column($plan['nextRows'], 'label') !== ['plugin_alpha', 'home', 'theme_mods', 'rewrite_rules', 'home', 'siteurl']) {
        fwrite(STDERR, "unexpected next compound/window recursive LIMIT ordered-value-offset-boundary boundary\n");
        exit(1);
    }
    if (($plan['valueOffsetTape']['peerBoundary']['nextFirstPeer'] ?? null) !== 1) {
        fwrite(STDERR, "unexpected next first value-offset peer boundary\n");
        exit(1);
    }

    echo "wordpress-compound-select-window-recursive-limit-current-source-ordered-value-offset-boundary self-test passed\n";
    return;
}

echo json_encode([
    'scenario' => 'wordpress-compound-select-window-recursive-limit-current-source-ordered-value-offset-boundary',
    'currentLabels' => array_column($plan['currentRows'], 'label'),
    'nextLabels' => array_column($plan['nextRows'], 'label'),
    'nextPeers' => $plan['valueOffsetTape']['peerBoundary']['nextPeers'],
    'recursiveEmitted' => $plan['recursive']['currentEmittedLabels'],
    'replanReasons' => $plan['replanReasons'],
    'dependencyClosure' => $plan['dependency_closure'],
], JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR) . PHP_EOL;
