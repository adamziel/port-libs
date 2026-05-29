<?php

declare(strict_types=1);

require_once dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\LibSqlite\SQLiteCompoundHavingWindowCurrentSourceNextPlan;

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
SELECT autoload,
       sum(bytes) AS total_bytes,
       count(*) OVER (
           ORDER BY autoload
           ROWS BETWEEN CURRENT ROW AND CURRENT ROW
       ) AS source_enabled
  FROM wp_options
 GROUP BY autoload
HAVING sum(bytes) >= (
       SELECT count(*) * 15 FROM wp_options_stage
        WHERE wp_options_stage.autoload = wp_options.autoload
          AND wp_options_stage.enabled = 1
       )
UNION ALL
SELECT autoload,
       count(*) AS total_bytes,
       count(*) OVER (
           ORDER BY autoload
           ROWS BETWEEN CURRENT ROW AND CURRENT ROW
       ) AS source_enabled
  FROM wp_options_stage
 GROUP BY autoload
HAVING count(*) <= (
       SELECT count(*) FROM wp_options
        WHERE wp_options.autoload = wp_options_stage.autoload
          AND wp_options.enabled = 1
       )
 ORDER BY autoload, total_bytes DESC
SQL;

$plan = SQLiteCompoundHavingWindowCurrentSourceNextPlan::compareNext128($sql, $currentTables, $nextTables);

if (in_array('--self-test', $argv, true)) {
    if (($plan['status'] ?? null) !== 'compound-having-window-current-source-next128') {
        fwrite(STDERR, "unexpected status\n");
        exit(1);
    }
    if (array_column($plan['nextRows'], 'total_bytes') !== [90, 2, 30, 1]) {
        fwrite(STDERR, "unexpected next compound HAVING rows\n");
        exit(1);
    }
    if (($plan['having']['correlatedArms'] ?? []) !== [0, 1]) {
        fwrite(STDERR, "missing correlated HAVING arms\n");
        exit(1);
    }
    fwrite(STDOUT, "wordpress-compound-having-window-current-source-next128 self-test passed\n");
    exit(0);
}

echo json_encode([
    'scenario' => 'wordpress-compound-having-window-current-source-next128',
    'wordpressUse' => 'Preview copied wp_options current and staged rows through compound SELECT arms whose HAVING clauses use correlated aggregate gates while window projections preserve source order diagnostics.',
    'status' => $plan['status'],
    'currentTotals' => array_column($plan['currentRows'], 'total_bytes'),
    'nextTotals' => array_column($plan['nextRows'], 'total_bytes'),
    'havingArms' => $plan['having']['arms'],
    'correlatedHavingArms' => $plan['having']['correlatedArms'],
    'windowAliases' => $plan['windows']['aliases'],
    'replanReasons' => $plan['replanReasons'],
    'dependencyClosure' => 'no new support component needed; reuses native SELECT SQL compound execution, aggregate HAVING predicates, correlated subqueries, and window projection evaluation',
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
