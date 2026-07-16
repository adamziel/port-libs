<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\LibSqlite\SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNextPlan;

$currentTables = [
    'app_settings' => [
        ['setting_id' => 1, 'key_name' => 'home', 'load_policy' => 'eager', 'weight' => 30],
        ['setting_id' => 2, 'key_name' => 'siteurl', 'load_policy' => 'eager', 'weight' => 20],
        ['setting_id' => 3, 'key_name' => 'rewrite_rules', 'load_policy' => 'lazy', 'weight' => 15],
    ],
];
$nextTables = [
    'app_settings' => [
        ...$currentTables['app_settings'],
        ['setting_id' => 4, 'key_name' => 'plugin_cache', 'load_policy' => 'eager', 'weight' => 12],
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
SELECT key_name AS label,
       setting_id AS pos,
       row_number() OVER (ORDER BY setting_id) AS rn
  FROM app_settings
 WHERE load_policy = 'eager'
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
