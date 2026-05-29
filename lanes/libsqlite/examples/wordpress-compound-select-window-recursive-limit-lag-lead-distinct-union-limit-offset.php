<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\LibSqlite\SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNextPlan;

$currentTables = [
    'wp_options' => [
        ['option_id' => 1, 'option_name' => 'siteurl', 'autoload' => 'yes', 'score' => 90],
        ['option_id' => 2, 'option_name' => 'home', 'autoload' => 'yes', 'score' => 75],
        ['option_id' => 3, 'option_name' => 'rewrite_rules', 'autoload' => 'yes', 'score' => 60],
        ['option_id' => 4, 'option_name' => 'cache_seed', 'autoload' => 'no', 'score' => 20],
    ],
];
$nextTables = [
    'wp_options' => [
        ...$currentTables['wp_options'],
        ['option_id' => 5, 'option_name' => 'plugin_alpha', 'autoload' => 'yes', 'score' => 85],
        ['option_id' => 6, 'option_name' => 'theme_mods', 'autoload' => 'yes', 'score' => 65],
    ],
];

$sql = <<<'SQL'
WITH RECURSIVE q(id, label, score) AS (
    VALUES (1, 'seed', 100)
    UNION ALL
    SELECT id + 1, label || ':' || (id + 1), score - 10
      FROM q
     WHERE id < 7
     LIMIT 5 OFFSET 2
)
SELECT id,
       label,
       lag(score, 1, score) OVER (ORDER BY id) AS metric
  FROM q
UNION ALL
SELECT option_id AS id,
       option_name AS label,
       lead(score, 1, score) OVER (PARTITION BY autoload ORDER BY score DESC, option_id) AS metric
  FROM wp_options
 WHERE autoload = 'yes'
UNION
SELECT option_id AS id,
       option_name AS label,
       score AS metric
  FROM wp_options
 WHERE score >= 65
 ORDER BY metric DESC, id
 LIMIT 5 OFFSET 1
SQL;

$plan = SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNextPlan::compareLagLeadDistinctUnionLimitOffset($sql, $currentTables, $nextTables);

if (in_array('--self-test', $argv, true)) {
    if (($plan['status'] ?? null) !== 'compound-select-window-recursive-limit-current-source-lag-lead-distinct-union-limit-offset-ready') {
        fwrite(STDERR, "unexpected compound lag-lead-distinct-union-limit-offset status\n");
        exit(1);
    }
    if (array_column($plan['nextRows'], 'label') !== ['rewrite_rules', 'siteurl', 'plugin_alpha', 'seed:2:3', 'seed:2:3:4']) {
        fwrite(STDERR, "unexpected compound lag-lead-distinct-union-limit-offset next rows\n");
        exit(1);
    }
    if (($plan['distinctUnion']['nextDuplicateLabels'] ?? []) !== []) {
        fwrite(STDERR, "unexpected compound lag-lead-distinct-union-limit-offset duplicate full-row diagnostic\n");
        exit(1);
    }
    echo "wordpress-compound-select-window-recursive-limit-current-source-lag-lead-distinct-union-limit-offset self-test passed\n";
    return;
}

echo json_encode([
    'scenario' => 'wordpress-compound-select-window-recursive-limit-current-source-lag-lead-distinct-union-limit-offset',
    'wordpressUse' => 'Copied wp_options import previews can compare current and next option trees while recursive LIMIT/OFFSET, lag/lead window metrics, UNION ALL streaming, UNION distinct de-duplication, and final tail LIMIT/OFFSET determine the rows shown before commit.',
    'dependencyClosure' => 'no new support component needed; reuses native PHP SELECT SQL recursive CTE, compound set operator, window, ORDER BY, and LIMIT/OFFSET helpers',
    'status' => $plan['status'],
    'currentLabels' => array_column($plan['currentRows'], 'label'),
    'nextLabels' => array_column($plan['nextRows'], 'label'),
    'duplicateLabels' => $plan['distinctUnion']['nextDuplicateLabels'],
    'replanReasons' => $plan['replanReasons'],
], JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR) . PHP_EOL;
