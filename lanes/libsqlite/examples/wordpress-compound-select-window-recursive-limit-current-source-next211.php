<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\LibSqlite\SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNextPlan;

$currentTables = [
    'wp_options' => [
        ['option_id' => 1, 'option_name' => 'siteurl', 'autoload' => 'yes', 'score' => 120],
        ['option_id' => 2, 'option_name' => 'home', 'autoload' => 'yes', 'score' => 95],
        ['option_id' => 3, 'option_name' => 'rewrite_rules', 'autoload' => 'yes', 'score' => 72],
        ['option_id' => 4, 'option_name' => 'cache_seed', 'autoload' => 'no', 'score' => 40],
    ],
];
$nextTables = [
    'wp_options' => [
        ...$currentTables['wp_options'],
        ['option_id' => 5, 'option_name' => 'plugin_prime', 'autoload' => 'yes', 'score' => 112],
        ['option_id' => 6, 'option_name' => 'theme_mods_next', 'autoload' => 'no', 'score' => 84],
    ],
];

$sql = <<<'SQL'
WITH RECURSIVE q(id, label, score) AS (
    VALUES (1, 'seed', 134)
    UNION ALL
    SELECT id + 1, label || ':' || (id + 1), score - 11
      FROM q
     WHERE id < 8
     LIMIT 6 OFFSET 1
)
SELECT id,
       label,
       sum(score) FILTER (WHERE score >= 100) OVER (
           ORDER BY score DESC, id
           ROWS BETWEEN 2 PRECEDING AND CURRENT ROW
       ) AS metric
  FROM q
UNION ALL
SELECT option_id AS id,
       option_name AS label,
       count(*) FILTER (WHERE autoload = 'yes') OVER (
           ORDER BY score DESC, option_id
           ROWS BETWEEN 2 PRECEDING AND CURRENT ROW
       ) AS metric
  FROM wp_options
EXCEPT
SELECT option_id AS id,
       option_name AS label,
       0 AS metric
  FROM wp_options
 WHERE autoload = 'no'
UNION
SELECT id,
       label,
       score AS metric
  FROM q
 ORDER BY metric DESC, id
 LIMIT 7 OFFSET 1
SQL;

$plan = SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNextPlan::compareFilteredWindowExceptUnionLimit($sql, $currentTables, $nextTables);

if (($argv[1] ?? null) === '--self-test') {
    if (($plan['status'] ?? null) !== 'compound-select-window-recursive-limit-current-source-next211-ready') {
        fwrite(STDERR, "unexpected compound/window recursive LIMIT next211 status\n");
        exit(1);
    }
    if (($plan['windows']['filteredFunctions'] ?? []) !== ['sum', 'count']) {
        fwrite(STDERR, "unexpected filtered window functions\n");
        exit(1);
    }
    if (($plan['sourceWindow']['nextOnlyPreLimitLabels'] ?? []) !== ['plugin_prime', 'theme_mods_next']) {
        fwrite(STDERR, "unexpected next-source filtered compound boundary\n");
        exit(1);
    }

    echo "wordpress-compound-select-window-recursive-limit-current-source-next211 self-test passed\n";
    return;
}

echo json_encode([
    'scenario' => 'wordpress-compound-select-window-recursive-limit-current-source-next211',
    'currentLabels' => array_column($plan['currentRows'], 'label'),
    'currentMetrics' => array_column($plan['currentRows'], 'metric'),
    'nextOnlyPreLimitLabels' => $plan['sourceWindow']['nextOnlyPreLimitLabels'],
    'filteredFunctions' => $plan['windows']['filteredFunctions'],
    'recursiveEmitted' => $plan['recursiveQueue']['currentEmittedLabels'],
    'replanReasons' => $plan['replanReasons'],
    'dependencyClosure' => $plan['dependency_closure'],
], JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR) . PHP_EOL;
