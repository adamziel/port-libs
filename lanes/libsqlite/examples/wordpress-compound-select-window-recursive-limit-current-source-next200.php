<?php

declare(strict_types=1);

require_once dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\LibSqlite\SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNextPlan;

$currentTables = [
    'wp_options' => [
        ['option_id' => 1, 'option_name' => 'siteurl', 'autoload' => 'yes', 'score' => 100],
        ['option_id' => 2, 'option_name' => 'home', 'autoload' => 'yes', 'score' => 90],
        ['option_id' => 3, 'option_name' => 'rewrite_rules', 'autoload' => 'yes', 'score' => 70],
        ['option_id' => 4, 'option_name' => 'transient_seed', 'autoload' => 'no', 'score' => 30],
    ],
];
$nextTables = $currentTables;
$nextTables['wp_options'][] = ['option_id' => 5, 'option_name' => 'plugin_loaded', 'autoload' => 'yes', 'score' => 116];

$sql = <<<'SQL'
WITH RECURSIVE q(id, label, score) AS (
    VALUES (1, 'seed', 124)
    UNION ALL
    SELECT id + 1, label || ':' || (id + 1), score - 9
      FROM q
     WHERE id < 8
     ORDER BY score DESC
     LIMIT 6 OFFSET 1
)
SELECT id,
       label,
       rank() OVER (ORDER BY score DESC, id) AS metric
  FROM q
UNION ALL
SELECT option_id AS id,
       option_name AS label,
       last_value(score) OVER (
           PARTITION BY autoload
           ORDER BY score DESC, option_id
           ROWS BETWEEN CURRENT ROW AND 1 FOLLOWING
       ) AS metric
  FROM wp_options
 WHERE autoload = 'yes'
UNION
SELECT id,
       label,
       score AS metric
  FROM q
EXCEPT
SELECT 2 AS id,
       'home' AS label,
       90 AS metric
 ORDER BY metric DESC, id
 LIMIT 5 OFFSET 1
SQL;

$plan = SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNextPlan::compareNext200($sql, $currentTables, $nextTables);

if (($argv[1] ?? null) === '--self-test') {
    assert($plan['status'] === 'compound-select-window-recursive-limit-current-source-next200-ready');
    assert($plan['compound']['operators'] === ['UNION ALL', 'UNION', 'EXCEPT']);
    assert($plan['distinctExceptBoundary']['currentAdmittedLabels'][0] === 'seed:2:3');
    assert(in_array('plugin_loaded', $plan['distinctExceptBoundary']['nextAdmittedLabels'], true));
    assert($plan['distinctExceptBoundary']['currentToken'] !== $plan['distinctExceptBoundary']['nextToken']);
    echo "wordpress-compound-select-window-recursive-limit-current-source-next200 self-test passed\n";
    return;
}

echo json_encode([
    'status' => $plan['status'],
    'current' => $plan['distinctExceptBoundary']['currentAdmittedLabels'],
    'next' => $plan['distinctExceptBoundary']['nextAdmittedLabels'],
    'replanReasons' => $plan['replanReasons'],
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
