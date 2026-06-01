<?php

declare(strict_types=1);

require_once dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\LibSqlite\SQLiteCompoundHavingWindowCurrentSourceNextPlan;

$currentTables = [
    'app_settings' => [
        ['setting_id' => 1, 'key_name' => 'base_url', 'load_policy' => 'yes', 'bytes' => 10, 'enabled' => 1],
        ['setting_id' => 2, 'key_name' => 'landing_url', 'load_policy' => 'yes', 'bytes' => 20, 'enabled' => 1],
        ['setting_id' => 4, 'key_name' => 'module_registry', 'load_policy' => 'no', 'bytes' => 40, 'enabled' => 1],
    ],
    'app_settings_stage' => [
        ['stage_id' => 10, 'key_name' => 'base_url', 'load_policy' => 'yes', 'bytes' => 12, 'enabled' => 1],
        ['stage_id' => 11, 'key_name' => 'module_cache', 'load_policy' => 'no', 'bytes' => 24, 'enabled' => 1],
    ],
];
$nextTables = $currentTables;
$nextTables['app_settings'][] = ['setting_id' => 5, 'key_name' => 'new_module_flag', 'load_policy' => 'no', 'bytes' => 50, 'enabled' => 1];
$nextTables['app_settings_stage'][] = ['stage_id' => 13, 'key_name' => 'new_module_flag', 'load_policy' => 'no', 'bytes' => 42, 'enabled' => 1];

$sql = <<<'SQL'
SELECT load_policy,
       sum(bytes) AS total_bytes,
       count(*) OVER (
           ORDER BY load_policy
           ROWS BETWEEN CURRENT ROW AND CURRENT ROW
       ) AS source_enabled
  FROM app_settings
 GROUP BY load_policy
HAVING sum(bytes) >= (
       SELECT count(*) * 15 FROM app_settings_stage
        WHERE app_settings_stage.load_policy = app_settings.load_policy
          AND app_settings_stage.enabled = 1
       )
UNION ALL
SELECT load_policy,
       count(*) AS total_bytes,
       count(*) OVER (
           ORDER BY load_policy
           ROWS BETWEEN CURRENT ROW AND CURRENT ROW
       ) AS source_enabled
  FROM app_settings_stage
 GROUP BY load_policy
HAVING count(*) <= (
       SELECT count(*) FROM app_settings
        WHERE app_settings.load_policy = app_settings_stage.load_policy
          AND app_settings.enabled = 1
       )
 ORDER BY load_policy, total_bytes DESC
SQL;

$plan = SQLiteCompoundHavingWindowCurrentSourceNextPlan::compareHavingWindow($sql, $currentTables, $nextTables);

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
    fwrite(STDOUT, "application-compound-having-window-current-source-next128 self-test passed\n");
    exit(0);
}

echo json_encode([
    'scenario' => 'application-compound-having-window-current-source-next128',
    'applicationUse' => 'Preview copied app_settings current and staged rows through compound SELECT arms whose HAVING clauses use correlated aggregate gates while window projections preserve source order diagnostics.',
    'status' => $plan['status'],
    'currentTotals' => array_column($plan['currentRows'], 'total_bytes'),
    'nextTotals' => array_column($plan['nextRows'], 'total_bytes'),
    'havingArms' => $plan['having']['arms'],
    'correlatedHavingArms' => $plan['having']['correlatedArms'],
    'windowAliases' => $plan['windows']['aliases'],
    'replanReasons' => $plan['replanReasons'],
    'dependencyClosure' => 'no new support component needed; reuses native SELECT SQL compound execution, aggregate HAVING predicates, correlated subqueries, and window projection evaluation',
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
