<?php

declare(strict_types=1);

require_once dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\LibSqlite\SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNextPlan;

$currentTables = [
    'app_settings' => [
        ['setting_id' => 1, 'key_name' => 'siteurl', 'load_policy' => 'yes', 'score' => 101],
        ['setting_id' => 2, 'key_name' => 'home', 'load_policy' => 'yes', 'score' => 84],
        ['setting_id' => 3, 'key_name' => 'rewrite_rules', 'load_policy' => 'yes', 'score' => 67],
        ['setting_id' => 4, 'key_name' => 'cache_seed', 'load_policy' => 'no', 'score' => 20],
    ],
];
$nextTables = [
    'app_settings' => [
        ...$currentTables['app_settings'],
        ['setting_id' => 5, 'key_name' => 'plugin_ranked', 'load_policy' => 'yes', 'score' => 96],
        ['setting_id' => 6, 'key_name' => 'theme_mods_next', 'load_policy' => 'yes', 'score' => 73],
    ],
];

$sql = <<<'SQL'
WITH RECURSIVE q(id, label, score) AS (
    VALUES (1, 'seed', 118)
    UNION ALL
    SELECT id + 1, label || ':' || (id + 1), score - 8
      FROM q
     WHERE id < 9
     LIMIT 7 OFFSET 2
)
SELECT id,
       label,
       lag(score, 1, score) OVER (ORDER BY id) AS metric
  FROM q
UNION ALL
SELECT setting_id AS id,
       key_name AS label,
       lead(score, 1, score) OVER (PARTITION BY load_policy ORDER BY score DESC, setting_id) AS metric
  FROM app_settings
 WHERE load_policy = 'yes'
UNION ALL
SELECT id,
       label,
       rank() OVER (ORDER BY score DESC, id) AS metric
  FROM q
UNION ALL
SELECT setting_id AS id,
       key_name AS label,
       dense_rank() OVER (PARTITION BY load_policy ORDER BY score DESC, setting_id) AS metric
  FROM app_settings
 WHERE load_policy = 'yes'
UNION
SELECT setting_id AS id,
       key_name AS label,
       score AS metric
  FROM app_settings
 WHERE score >= 67
 ORDER BY metric DESC, id
 LIMIT 3, 6
SQL;

$plan = SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNextPlan::compareCommaLimitRecursiveWindowBoundary($sql, $currentTables, $nextTables);

if (($argv[1] ?? null) === '--self-test') {
    assert($plan['status'] === 'compound-select-window-recursive-limit-current-source-next186-ready');
    assert($plan['compound']['commaLimit'] === ['offset' => 3, 'count' => 6]);
    assert($plan['sourceBoundary']['addedAdmittedLabels'] === ['siteurl', 'plugin_ranked']);
    echo "application-compound-select-window-recursive-limit-current-source-next186 self-test passed\n";
    return;
}

echo json_encode([
    'status' => $plan['status'],
    'commaLimit' => $plan['compound']['commaLimit'],
    'currentAdmittedLabels' => $plan['sourceBoundary']['currentAdmittedLabels'],
    'nextAdmittedLabels' => $plan['sourceBoundary']['nextAdmittedLabels'],
    'addedAdmittedLabels' => $plan['sourceBoundary']['addedAdmittedLabels'],
    'dependencyClosure' => $plan['dependency_closure'],
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
