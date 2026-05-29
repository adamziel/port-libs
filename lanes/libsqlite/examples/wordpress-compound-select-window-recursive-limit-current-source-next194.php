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
$nextTables['wp_options'][] = ['option_id' => 5, 'option_name' => 'plugin_loaded', 'autoload' => 'yes', 'score' => 112];

$sql = <<<'SQL'
WITH RECURSIVE q(id, label, score) AS (
    VALUES (1, 'seed', 118)
    UNION ALL
    SELECT id + 1, label || ':' || (id + 1), score - 8
      FROM q
     WHERE id < 8
     LIMIT 6 OFFSET 1
)
SELECT id,
       label,
       row_number() OVER (ORDER BY score DESC, id) AS metric
  FROM q
UNION ALL
SELECT option_id AS id,
       option_name AS label,
       dense_rank() OVER (PARTITION BY autoload ORDER BY score DESC, option_id) AS metric
  FROM wp_options
 WHERE autoload = 'yes'
INTERSECT
SELECT option_id AS id,
       option_name AS label,
       1 AS metric
  FROM wp_options
 WHERE autoload = 'yes'
EXCEPT
SELECT 2 AS id,
       'home' AS label,
       1 AS metric
 ORDER BY metric DESC, id
 LIMIT 4 OFFSET 0
SQL;

$plan = SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNextPlan::compareNext194($sql, $currentTables, $nextTables);

if (($argv[1] ?? null) === '--self-test') {
    assert($plan['status'] === 'compound-select-window-recursive-limit-current-source-next194-ready');
    assert($plan['membershipBoundary']['currentAdmittedLabels'] === ['siteurl']);
    assert($plan['membershipBoundary']['nextAdmittedLabels'] === ['plugin_loaded']);
    assert($plan['membershipBoundary']['currentToken'] !== $plan['membershipBoundary']['nextToken']);
    echo "wordpress-compound-select-window-recursive-limit-current-source-next194 self-test passed\n";
    return;
}

echo json_encode([
    'status' => $plan['status'],
    'current' => $plan['membershipBoundary']['currentAdmittedLabels'],
    'next' => $plan['membershipBoundary']['nextAdmittedLabels'],
    'replanReasons' => $plan['replanReasons'],
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
