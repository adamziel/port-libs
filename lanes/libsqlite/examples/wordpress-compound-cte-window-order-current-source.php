<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteCompoundCteWindowOrderCurrentSourceNextPlan;

require_once __DIR__ . '/../../../tools/bootstrap.php';

$currentOptions = [
    ['option_id' => 1, 'option_name' => 'siteurl', 'autoload' => 'yes', 'bytes' => 20, 'priority' => 3],
    ['option_id' => 2, 'option_name' => 'home', 'autoload' => 'yes', 'bytes' => 15, 'priority' => 2],
    ['option_id' => 3, 'option_name' => 'theme_mods', 'autoload' => 'no', 'bytes' => 40, 'priority' => 5],
    ['option_id' => 4, 'option_name' => 'plugin_alpha', 'autoload' => 'no', 'bytes' => 10, 'priority' => 4],
    ['option_id' => 5, 'option_name' => 'plugin_beta', 'autoload' => 'yes', 'bytes' => 8, 'priority' => 1],
];
$nextOptions = [
    ...$currentOptions,
    ['option_id' => 6, 'option_name' => 'plugin_gamma', 'autoload' => 'yes', 'bytes' => 50, 'priority' => 8],
    ['option_id' => 7, 'option_name' => 'transient_cleanup', 'autoload' => 'no', 'bytes' => 35, 'priority' => 6],
];

$sql = <<<'SQL'
WITH ranked AS MATERIALIZED (
    SELECT option_id, option_name, autoload, bytes, priority
      FROM wp_options
     WHERE bytes >= 8
),
yes_rows AS (
    SELECT option_id, option_name, bytes, priority
      FROM ranked
     WHERE autoload = 'yes'
),
no_rows AS (
    SELECT option_id, option_name, bytes, priority
      FROM ranked
     WHERE autoload = 'no'
)
SELECT option_id AS id,
       option_name AS name,
       row_number() OVER (ORDER BY priority DESC, option_id ASC) AS source_rank,
       sum(bytes) OVER (
           ORDER BY priority DESC, option_id ASC
           ROWS BETWEEN CURRENT ROW AND 1 FOLLOWING
       ) AS frame_bytes
  FROM yes_rows
UNION ALL
SELECT option_id AS id,
       option_name AS name,
       row_number() OVER (ORDER BY priority DESC, option_id ASC) AS source_rank,
       sum(bytes) OVER (
           ORDER BY priority DESC, option_id ASC
           ROWS BETWEEN CURRENT ROW AND 1 FOLLOWING
       ) AS frame_bytes
  FROM no_rows
 ORDER BY source_rank ASC, frame_bytes DESC, name ASC
 LIMIT 6
SQL;

$result = SQLiteCompoundCteWindowOrderCurrentSourceNextPlan::compareCteWindowOrder(
    $sql,
    ['wp_options' => $currentOptions],
    ['wp_options' => $nextOptions],
);

$summary = [
    'scenario' => 'wordpress-compound-cte-window-order-current-source',
    'wordpressUse' => 'Copied wp_options import previews can materialize option subsets in CTEs, run ordered window frames inside compound SELECT arms, and apply the final compound ORDER BY against current and next source snapshots without requiring ext/sqlite.',
    'currentFirst' => $result['orderBoundary']['currentFirst'],
    'nextFirst' => $result['orderBoundary']['nextFirst'],
    'changedSignatures' => $result['changedSignatures'],
    'compoundOrder' => $result['compound']['orderColumns'],
    'windowAliases' => $result['windows']['orderedAliases'],
    'dependency' => 'native PHP SQLite SELECT SQL CTE, compound, window, and final ORDER execution; no ext/sqlite required',
];

if (PHP_SAPI === 'cli' && realpath($_SERVER['SCRIPT_FILENAME'] ?? '') === __FILE__) {
    if (($summary['currentFirst']['id'] ?? null) !== 3 || ($summary['nextFirst']['id'] ?? null) !== 7) {
        fwrite(STDERR, 'unexpected compound CTE window order boundary' . PHP_EOL);
        exit(1);
    }
    if (!in_array('source_rank', $summary['compoundOrder'], true) || !in_array('frame_bytes', $summary['windowAliases'], true)) {
        fwrite(STDERR, 'unexpected compound CTE window order metadata' . PHP_EOL);
        exit(1);
    }
    echo "wordpress-compound-cte-window-order-current-source-self-test passed\n";
}

return $summary;
