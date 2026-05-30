<?php

declare(strict_types=1);

require_once dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\LibSqlite\SQLiteCompoundWindowFilterCurrentSourcePlan;

$currentTables = [
    'wp_options' => [
        ['option_id' => 1, 'option_name' => 'siteurl', 'autoload' => 'yes', 'bytes' => 10, 'enabled' => 1],
        ['option_id' => 2, 'option_name' => 'home', 'autoload' => 'yes', 'bytes' => 20, 'enabled' => 1],
        ['option_id' => 4, 'option_name' => 'active_plugins', 'autoload' => 'no', 'bytes' => 40, 'enabled' => 1],
    ],
    'wp_options_stage' => [
        ['stage_id' => 10, 'option_name' => 'siteurl', 'autoload' => 'yes', 'bytes' => 12, 'enabled' => 1],
        ['stage_id' => 11, 'option_name' => 'plugin_cache', 'autoload' => 'no', 'bytes' => 24, 'enabled' => 1],
    ],
];
$nextTables = $currentTables;
$nextTables['wp_options'][] = ['option_id' => 5, 'option_name' => 'new_plugin_flag', 'autoload' => 'no', 'bytes' => 50, 'enabled' => 1];
$nextTables['wp_options_stage'][] = ['stage_id' => 13, 'option_name' => 'new_plugin_flag', 'autoload' => 'no', 'bytes' => 42, 'enabled' => 1];

$sql = <<<'SQL'
SELECT option_id AS id,
       option_name AS name,
       autoload,
       sum(bytes) FILTER (
           WHERE enabled
             AND EXISTS (
                 SELECT 1 FROM wp_options_stage
                  WHERE wp_options_stage.autoload = wp_options.autoload
                    AND wp_options_stage.option_name = wp_options.option_name
             )
       ) OVER (
           PARTITION BY autoload
           ORDER BY option_id
           ROWS BETWEEN CURRENT ROW AND 1 FOLLOWING
       ) AS filtered_bytes
  FROM wp_options
 WHERE EXISTS (
       SELECT 1 FROM wp_options_stage
        WHERE wp_options_stage.autoload = wp_options.autoload
          AND wp_options_stage.option_name = wp_options.option_name
       )
UNION ALL
SELECT stage_id AS id,
       option_name AS name,
       autoload,
       sum(bytes) FILTER (
           WHERE enabled
             AND bytes > (
                 SELECT avg(bytes) FROM wp_options
                  WHERE wp_options.autoload = wp_options_stage.autoload
             )
       ) OVER (
           PARTITION BY autoload
           ORDER BY stage_id
           ROWS BETWEEN CURRENT ROW AND CURRENT ROW
       ) AS filtered_bytes
  FROM wp_options_stage
 WHERE EXISTS (
       SELECT 1 FROM wp_options
        WHERE wp_options.option_name = wp_options_stage.option_name
       )
 ORDER BY id
SQL;

$plan = SQLiteCompoundWindowFilterCurrentSourcePlan::compare($sql, $currentTables, $nextTables);

if (in_array('--self-test', $argv, true)) {
    if (($plan['status'] ?? null) !== 'compound-window-filter-current-source-ready') {
        fwrite(STDERR, "unexpected status\n");
        exit(1);
    }
    if (array_column($plan['nextRows'], 'id') !== [1, 5, 10, 13]) {
        fwrite(STDERR, "unexpected next compound rows\n");
        exit(1);
    }
    if (!in_array('correlated-window-filter-source', $plan['replanReasons'], true)) {
        fwrite(STDERR, "missing correlated filter replan reason\n");
        exit(1);
    }
    fwrite(STDOUT, "application-compound-window-filter-current-source-next126 self-test passed\n");
    exit(0);
}

echo json_encode([
    'scenario' => 'application-compound-window-filter-current-source-next126',
    'applicationUse' => 'Preview copied wp_options current and staged rows through a compound SELECT whose window FILTER clauses use correlated subqueries before choosing the next import source.',
    'status' => $plan['status'],
    'currentIds' => array_column($plan['currentRows'], 'id'),
    'nextIds' => array_column($plan['nextRows'], 'id'),
    'filteredAliases' => $plan['windows']['filteredAliases'],
    'replanReasons' => $plan['replanReasons'],
    'dependencyClosure' => 'no new support component needed; reuses native SELECT SQL compound execution, correlated subquery predicates, and window FILTER evaluation',
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
