<?php

declare(strict_types=1);

require_once dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\LibSqlite\SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNextPlan;

$currentTables = [
    'wp_options' => [
        ['option_id' => 1, 'option_name' => 'siteurl', 'autoload' => 'yes', 'score' => 101],
        ['option_id' => 2, 'option_name' => 'home', 'autoload' => 'yes', 'score' => 84],
        ['option_id' => 3, 'option_name' => 'rewrite_rules', 'autoload' => 'yes', 'score' => 67],
        ['option_id' => 4, 'option_name' => 'cache_seed', 'autoload' => 'no', 'score' => 20],
    ],
];
$nextTables = [
    'wp_options' => [
        ...$currentTables['wp_options'],
        ['option_id' => 5, 'option_name' => 'plugin_ranked', 'autoload' => 'yes', 'score' => 96],
        ['option_id' => 6, 'option_name' => 'theme_mods_next', 'autoload' => 'yes', 'score' => 73],
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
SELECT option_id AS id,
       option_name AS label,
       lead(score, 1, score) OVER (PARTITION BY autoload ORDER BY score DESC, option_id) AS metric
  FROM wp_options
 WHERE autoload = 'yes'
UNION ALL
SELECT id,
       label,
       rank() OVER (ORDER BY score DESC, id) AS metric
  FROM q
UNION ALL
SELECT option_id AS id,
       option_name AS label,
       dense_rank() OVER (PARTITION BY autoload ORDER BY score DESC, option_id) AS metric
  FROM wp_options
 WHERE autoload = 'yes'
UNION
SELECT option_id AS id,
       option_name AS label,
       score AS metric
  FROM wp_options
 WHERE score >= 67
 ORDER BY metric DESC, id
 LIMIT 3, 6
SQL;

$plan = SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNextPlan::compareNext186($sql, $currentTables, $nextTables);

if (($argv[1] ?? null) === '--self-test') {
    assert($plan['status'] === 'compound-select-window-recursive-limit-current-source-next186-ready');
    assert($plan['compound']['commaLimit'] === ['offset' => 3, 'count' => 6]);
    assert($plan['sourceBoundary']['addedAdmittedLabels'] === ['siteurl', 'plugin_ranked']);
    echo "wordpress-compound-select-window-recursive-limit-current-source-next186 self-test passed\n";
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
