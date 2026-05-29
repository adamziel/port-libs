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
     LIMIT 0
)
SELECT id,
       label,
       row_number() OVER (ORDER BY id) AS metric
  FROM q
UNION ALL
SELECT option_id AS id,
       option_name AS label,
       rank() OVER (PARTITION BY autoload ORDER BY score DESC, option_id) AS metric
  FROM wp_options
 WHERE autoload = 'yes'
UNION ALL
SELECT option_id AS id,
       option_name AS label,
       dense_rank() OVER (ORDER BY score DESC) AS metric
  FROM wp_options
 WHERE score >= 60
 ORDER BY metric, id
 LIMIT 4 OFFSET 1
SQL;

$plan = SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNextPlan::compareUnionAllEmptyRecursiveArm($sql, $currentTables, $nextTables);

if (in_array('--self-test', $argv, true)) {
    if (($plan['status'] ?? null) !== 'compound-select-window-recursive-limit-current-source-union-all-empty-recursive-arm-ready') {
        fwrite(STDERR, "unexpected compound union-all-empty-recursive-arm status\n");
        exit(1);
    }
    if (($plan['recursive']['currentRows'] ?? null) !== []) {
        fwrite(STDERR, "unexpected compound union-all-empty-recursive-arm recursive rows\n");
        exit(1);
    }
    if (array_column($plan['nextRows'], 'label') !== ['siteurl', 'plugin_alpha', 'plugin_alpha', 'home']) {
        fwrite(STDERR, "unexpected compound union-all-empty-recursive-arm next rows\n");
        exit(1);
    }
    echo "wordpress-compound-select-window-recursive-limit-current-source-union-all-empty-recursive-arm self-test passed\n";
    return;
}

echo json_encode([
    'scenario' => 'wordpress-compound-select-window-recursive-limit-current-source-union-all-empty-recursive-arm',
    'wordpressUse' => 'Copied wp_options import previews can keep a recursive arm syntactically present but exhausted by LIMIT 0, while windowed UNION ALL table arms and the final tail LIMIT/OFFSET determine which changed plugin/theme rows appear.',
    'dependencyClosure' => 'no new support component needed; reuses native PHP SELECT SQL recursive CTE, UNION ALL compound, window, ORDER BY, and LIMIT/OFFSET helpers',
    'status' => $plan['status'],
    'currentLabels' => array_column($plan['currentRows'], 'label'),
    'nextLabels' => array_column($plan['nextRows'], 'label'),
    'recursiveSuppressed' => $plan['recursive']['currentSuppressedLabels'],
    'replanReasons' => $plan['replanReasons'],
], JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR) . PHP_EOL;
