<?php

declare(strict_types=1);

require_once dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\LibSqlite\SQLiteCompoundZeroLimitRecursiveWindowCurrentSourceNextPlan;

$current = [
    'wp_options' => [
        ['option_id' => 1, 'option_name' => 'siteurl', 'autoload' => 'yes', 'weight' => 45],
        ['option_id' => 2, 'option_name' => 'home', 'autoload' => 'yes', 'weight' => 40],
        ['option_id' => 3, 'option_name' => 'blogname', 'autoload' => 'yes', 'weight' => 30],
    ],
];
$next = [
    'wp_options' => [
        ...$current['wp_options'],
        ['option_id' => 4, 'option_name' => 'rewrite_rules', 'autoload' => 'yes', 'weight' => 43],
    ],
];

$sql = <<<'SQL'
WITH RECURSIVE q(id, label, weight) AS (
    VALUES (1, 'seed', 50)
    UNION ALL
    SELECT id + 1, label || ':' || (id + 1), weight - 5
      FROM q
     WHERE id < 5
     LIMIT 4
)
SELECT id,
       label,
       row_number() OVER (ORDER BY weight DESC) AS bucket
  FROM q
UNION ALL
SELECT option_id AS id,
       option_name AS label,
       dense_rank() OVER (ORDER BY weight DESC, option_id) AS bucket
  FROM wp_options
 WHERE autoload = 'yes'
 ORDER BY bucket, id
 LIMIT 0 OFFSET 2
SQL;

$plan = SQLiteCompoundZeroLimitRecursiveWindowCurrentSourceNextPlan::compareNext174($sql, $current, $next);

if (($argv[1] ?? null) === '--self-test') {
    assert($plan['currentRows'] === []);
    assert($plan['nextRows'] === []);
    assert($plan['compound']['zeroLimitSuppressesRows'] === true);
    assert(in_array('rewrite_rules', $plan['sourceDelta']['suppressedAddedLabels'], true));
    echo "wordpress-compound-zero-limit-recursive-window-current-source-next174 self-test passed\n";
    return;
}

echo json_encode([
    'status' => $plan['status'],
    'currentVisibleRows' => count($plan['currentRows']),
    'nextVisibleRows' => count($plan['nextRows']),
    'currentSuppressedRows' => $plan['limitTrace']['current']['suppressedCount'],
    'nextSuppressedRows' => $plan['limitTrace']['next']['suppressedCount'],
    'suppressedAddedLabels' => $plan['sourceDelta']['suppressedAddedLabels'],
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
