<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteCompoundWindowExceptOrderCurrentSourceNextPlan;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$currentTables = [
    'app_settings' => [
        ['setting_id' => 1, 'key_name' => 'base_url', 'load_policy' => 'yes', 'freshness' => 90],
        ['setting_id' => 2, 'key_name' => 'home', 'load_policy' => 'yes', 'freshness' => 80],
        ['setting_id' => 3, 'key_name' => 'site_title', 'load_policy' => 'yes', 'freshness' => 70],
        ['setting_id' => 4, 'key_name' => 'active_modules', 'load_policy' => 'no', 'freshness' => 60],
        ['setting_id' => 5, 'key_name' => 'route_rules', 'load_policy' => 'no', 'freshness' => 50],
    ],
    'app_settings_current' => [
        ['key_name' => 'home', 'load_policy' => 'yes', 'source_rank' => 2],
        ['key_name' => 'route_rules', 'load_policy' => 'no', 'source_rank' => 2],
    ],
];
$nextTables = $currentTables;
$nextTables['app_settings'][] = ['setting_id' => 6, 'key_name' => 'module_alpha', 'load_policy' => 'yes', 'freshness' => 95];
$nextTables['app_settings'][] = ['setting_id' => 7, 'key_name' => 'module_beta', 'load_policy' => 'no', 'freshness' => 65];

$sql = <<<'SQL'
SELECT key_name AS name,
       load_policy,
       row_number() OVER (
           PARTITION BY load_policy
           ORDER BY freshness DESC, setting_id
       ) AS source_rank
  FROM app_settings
EXCEPT
SELECT key_name AS name,
       load_policy,
       source_rank
  FROM app_settings_current
 ORDER BY source_rank DESC, name
SQL;

$summary = SQLiteCompoundWindowExceptOrderCurrentSourceNextPlan::compare($sql, $currentTables, $nextTables);

if (($argv[1] ?? '') === '--self-test') {
    if (array_column($summary['currentRows'], 'name') !== ['site_title', 'active_modules', 'base_url']) {
        fwrite(STDERR, "unexpected current EXCEPT/window/ORDER boundary\n");
        exit(1);
    }
    if (array_column($summary['nextRows'], 'name') !== ['site_title', 'home', 'route_rules', 'active_modules', 'base_url', 'module_alpha', 'module_beta']) {
        fwrite(STDERR, "unexpected next EXCEPT/window/ORDER boundary\n");
        exit(1);
    }
    if (($summary['exceptTrace']['currentRemoved'] ?? []) !== ['home', 'route_rules']) {
        fwrite(STDERR, "missing current-source EXCEPT removal diagnostic\n");
        exit(1);
    }
    echo "application-compound-window-except-order-current-source self-test passed\n";
    exit(0);
}

echo json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
