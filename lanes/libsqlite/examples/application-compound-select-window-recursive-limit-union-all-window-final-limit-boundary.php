<?php

declare(strict_types=1);

require_once dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\LibSqlite\SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNextPlan;

$current = [
    'wp_options' => [
        ['option_id' => 1, 'option_name' => 'siteurl', 'autoload' => 'yes', 'weight' => 60],
        ['option_id' => 2, 'option_name' => 'home', 'autoload' => 'yes', 'weight' => 55],
        ['option_id' => 3, 'option_name' => 'blogname', 'autoload' => 'yes', 'weight' => 50],
    ],
];
$next = [
    'wp_options' => [
        ...$current['wp_options'],
        ['option_id' => 5, 'option_name' => 'rewrite_rules', 'autoload' => 'yes', 'weight' => 68],
    ],
];

$sql = <<<'SQL'
WITH RECURSIVE q(id, label, weight) AS (
    VALUES (1, 'seed', 70)
    UNION ALL
    SELECT id + 1, label || ':' || (id + 1), weight - 5
      FROM q
     WHERE id < 6
     LIMIT 5
)
SELECT id,
       label,
       row_number() OVER (ORDER BY weight DESC, id) AS bucket
  FROM q
UNION ALL
SELECT option_id AS id,
       option_name AS label,
       dense_rank() OVER (ORDER BY weight DESC, option_id) AS bucket
  FROM wp_options
 WHERE autoload = 'yes'
 ORDER BY bucket, id
 LIMIT 8
SQL;

$plan = SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNextPlan::compareUnionAllWindowFinalLimitBoundary($sql, $current, $next);

if (($argv[1] ?? null) === '--self-test') {
    assert($plan['compound']['currentLimitExactlyFilled'] === true);
    assert($plan['compound']['nextPreLimitOverflowsFinalLimit'] === true);
    assert($plan['limitTrace']['next']['firstTruncated']['label'] === 'seed:2:3:4:5');
    assert(array_key_exists('rewrite_rules', $plan['rankDelta']['nextBucketsByLabel']));
    echo "application-compound-select-window-recursive-limit-union-all-window-final-limit-boundary self-test passed\n";
    return;
}

echo json_encode([
    'status' => $plan['status'],
    'currentRows' => count($plan['currentRows']),
    'nextRows' => count($plan['nextRows']),
    'nextFirstAddedLabel' => $plan['boundary']['gainedRows'][0] ?? null,
    'nextFirstTruncatedLabel' => $plan['limitTrace']['next']['firstTruncated']['label'] ?? null,
    'replanReasons' => $plan['replanReasons'],
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
