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
    SELECT id + 1, label || ':' || (id + 1), score - 15
      FROM q
     WHERE id < 8
     LIMIT 1 OFFSET 3
)
SELECT id,
       label,
       row_number() OVER (ORDER BY score DESC) AS metric
  FROM q
UNION
SELECT option_id AS id,
       option_name AS label,
       dense_rank() OVER (ORDER BY score DESC) AS metric
  FROM wp_options
 WHERE autoload = 'yes'
UNION ALL
SELECT option_id AS id,
       option_name AS label,
       rank() OVER (PARTITION BY autoload ORDER BY score DESC, option_id) AS metric
  FROM wp_options
 WHERE score >= 70
 ORDER BY metric, id
 LIMIT 5 OFFSET 1
SQL;

$plan = SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNextPlan::compareNext185($sql, $currentTables, $nextTables);

if (($argv[1] ?? null) === '--self-test') {
    if (($plan['status'] ?? null) !== 'compound-select-window-recursive-limit-current-source-next185-ready') {
        fwrite(STDERR, "unexpected compound/window recursive LIMIT next185 status\n");
        exit(1);
    }
    if (array_column($plan['nextRows'], 'label') !== ['siteurl', 'seed:2:3:4', 'plugin_alpha', 'plugin_alpha', 'home']) {
        fwrite(STDERR, "unexpected next compound/window recursive LIMIT next185 boundary\n");
        exit(1);
    }
    if (!in_array('union-distinct-arm-collapsed-duplicates-before-union-all-tail', $plan['replanReasons'], true)) {
        fwrite(STDERR, "missing UNION distinct replan reason\n");
        exit(1);
    }

    echo "wordpress-compound-select-window-recursive-limit-current-source-next185 self-test passed\n";
    return;
}

echo json_encode([
    'scenario' => 'wordpress-compound-select-window-recursive-limit-current-source-next185',
    'currentLabels' => array_column($plan['currentRows'], 'label'),
    'nextLabels' => array_column($plan['nextRows'], 'label'),
    'recursiveEmitted' => $plan['recursive']['currentEmittedLabels'],
    'duplicateLabels' => $plan['distinctUnion']['nextDuplicateLabels'],
    'replanReasons' => $plan['replanReasons'],
    'dependencyClosure' => $plan['dependency_closure'],
], JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR) . PHP_EOL;
