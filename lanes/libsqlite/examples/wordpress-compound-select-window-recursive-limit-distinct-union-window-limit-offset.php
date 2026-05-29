<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\LibSqlite\SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNextPlan;

$currentTables = [
    'wp_options' => [
        ['option_id' => 1, 'option_name' => 'home', 'autoload' => 'yes', 'weight' => 30],
        ['option_id' => 2, 'option_name' => 'siteurl', 'autoload' => 'yes', 'weight' => 20],
        ['option_id' => 3, 'option_name' => 'rewrite_rules', 'autoload' => 'no', 'weight' => 15],
    ],
];
$nextTables = [
    'wp_options' => [
        ...$currentTables['wp_options'],
        ['option_id' => 4, 'option_name' => 'plugin_cache', 'autoload' => 'yes', 'weight' => 12],
    ],
];

$sql = <<<'SQL'
WITH RECURSIVE wanted(pos, label, weight) AS (
    VALUES (0, 'skip_anchor', 99)
    UNION ALL
    SELECT pos + 1,
           CASE pos + 1
                WHEN 1 THEN 'home'
                WHEN 2 THEN 'siteurl'
                WHEN 3 THEN 'rewrite_rules'
                WHEN 4 THEN 'plugin_cache'
           END,
           weight - 20
      FROM wanted
     WHERE pos < 4
     LIMIT 4 OFFSET 1
)
SELECT label,
       pos,
       row_number() OVER (ORDER BY pos) AS rn
  FROM wanted
UNION
SELECT option_name AS label,
       option_id AS pos,
       row_number() OVER (ORDER BY option_id) AS rn
  FROM wp_options
 WHERE autoload = 'yes'
 ORDER BY rn, label
 LIMIT 4 OFFSET 1
SQL;

$plan = SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNextPlan::compareDistinctUnionWindowLimitOffset($sql, $currentTables, $nextTables);

echo json_encode([
    'status' => $plan['status'],
    'currentLabels' => array_column($plan['currentRows'], 'label'),
    'nextLabels' => array_column($plan['nextRows'], 'label'),
    'recursiveSkipped' => $plan['recursive']['currentSkippedLabels'],
    'unionCurrentDuplicates' => $plan['unionTrace']['currentDuplicateLabels'],
    'unionNextDuplicates' => $plan['unionTrace']['nextDuplicateLabels'],
    'dependency_closure' => $plan['dependency_closure'],
], JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR) . "\n";
