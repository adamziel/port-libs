<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\LibSqlite\SQLiteCompoundExceptWindowRecursiveLimitCurrentSourceNextPlan;

$currentTables = [
    'wp_options' => [
        ['option_id' => 1, 'option_name' => 'siteurl', 'autoload' => 'yes', 'weight' => 12],
        ['option_id' => 2, 'option_name' => 'home', 'autoload' => 'yes', 'weight' => 10],
        ['option_id' => 3, 'option_name' => 'skip_seed_3', 'autoload' => 'no', 'weight' => 99],
    ],
];
$nextTables = $currentTables;
$nextTables['wp_options'][] = ['option_id' => 4, 'option_name' => 'plugin_alpha', 'autoload' => 'yes', 'weight' => 18];
$nextTables['wp_options'][] = ['option_id' => 5, 'option_name' => 'skip_cache', 'autoload' => 'no', 'weight' => 88];

$sql = <<<'SQL'
WITH RECURSIVE q(id, label, score) AS (
    VALUES (1, 'seed', 20)
    UNION ALL
    SELECT id + 1, 'seed_' || (id + 1), score - 2
      FROM q
     WHERE id < 6
     LIMIT 5
)
SELECT id,
       label,
       row_number() OVER (ORDER BY score DESC, id) AS win
  FROM q
UNION ALL
SELECT option_id AS id,
       option_name AS label,
       dense_rank() OVER (ORDER BY weight DESC, option_id) AS win
  FROM wp_options
EXCEPT
SELECT option_id,
       option_name,
       1
  FROM wp_options
 WHERE option_name LIKE 'skip_%'
 ORDER BY win, id
 LIMIT 5 OFFSET 1
SQL;

$summary = SQLiteCompoundExceptWindowRecursiveLimitCurrentSourceNextPlan::compareNext161($sql, $currentTables, $nextTables);

$result = [
    'scenario' => 'wordpress-compound-except-window-recursive-limit-current-source-next161',
    'sqlShape' => 'WITH RECURSIVE queue feeding windowed UNION ALL rows, EXCEPT exclusion, and final ORDER BY/LIMIT/OFFSET',
    'wordpressUse' => 'Copied wp_options import previews can remove skipped option rows through an EXCEPT arm after window values are computed, then compare the current and next yield boundary.',
    'currentLabels' => array_column($summary['currentRows'], 'label'),
    'nextLabels' => array_column($summary['nextRows'], 'label'),
    'except' => $summary['except'],
    'yieldBoundary' => [
        'currentSkipped' => array_column($summary['yieldBoundary']['current']['skippedBeforeOffset'], 'label'),
        'nextSkipped' => array_column($summary['yieldBoundary']['next']['skippedBeforeOffset'], 'label'),
        'currentTruncated' => array_column($summary['yieldBoundary']['current']['truncatedAfterLimit'], 'label'),
        'nextTruncated' => array_column($summary['yieldBoundary']['next']['truncatedAfterLimit'], 'label'),
    ],
    'replanReasons' => $summary['replanReasons'],
    'dependency' => 'native PHP SELECT SQL recursive CTE/window/compound EXCEPT/LIMIT execution; no ext/sqlite required',
];

if (($argv[1] ?? '') === '--self-test') {
    if ($result['currentLabels'] !== ['siteurl', 'seed_2', 'home', 'seed_3', 'seed_4']) {
        fwrite(STDERR, "unexpected current compound EXCEPT labels\n");
        exit(1);
    }
    if ($result['nextLabels'] !== ['seed_2', 'skip_cache', 'seed_3', 'plugin_alpha', 'siteurl']) {
        fwrite(STDERR, "unexpected next compound EXCEPT labels\n");
        exit(1);
    }
    if (($result['except']['survivingSkipLabels'] ?? []) !== ['skip_cache']) {
        fwrite(STDERR, "missing surviving EXCEPT diagnostic\n");
        exit(1);
    }
    if (!in_array('compound-except-before-final-limit', $result['replanReasons'], true)) {
        fwrite(STDERR, "missing compound EXCEPT replan reason\n");
        exit(1);
    }
    echo "wordpress-compound-except-window-recursive-limit-current-source-next161 self-test passed\n";
    exit(0);
}

echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
