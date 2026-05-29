<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteCompoundWindowExceptOrderCurrentSourceNextPlan;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$currentTables = [
    'wp_options' => [
        ['option_id' => 1, 'option_name' => 'siteurl', 'autoload' => 'yes', 'freshness' => 90],
        ['option_id' => 2, 'option_name' => 'home', 'autoload' => 'yes', 'freshness' => 80],
        ['option_id' => 3, 'option_name' => 'blogname', 'autoload' => 'yes', 'freshness' => 70],
        ['option_id' => 4, 'option_name' => 'active_plugins', 'autoload' => 'no', 'freshness' => 60],
        ['option_id' => 5, 'option_name' => 'rewrite_rules', 'autoload' => 'no', 'freshness' => 50],
    ],
    'wp_option_current' => [
        ['option_name' => 'home', 'autoload' => 'yes', 'source_rank' => 2],
        ['option_name' => 'rewrite_rules', 'autoload' => 'no', 'source_rank' => 2],
    ],
];
$nextTables = $currentTables;
$nextTables['wp_options'][] = ['option_id' => 6, 'option_name' => 'plugin_alpha', 'autoload' => 'yes', 'freshness' => 95];
$nextTables['wp_options'][] = ['option_id' => 7, 'option_name' => 'plugin_beta', 'autoload' => 'no', 'freshness' => 65];

$sql = <<<'SQL'
SELECT option_name AS name,
       autoload,
       row_number() OVER (
           PARTITION BY autoload
           ORDER BY freshness DESC, option_id
       ) AS source_rank
  FROM wp_options
EXCEPT
SELECT option_name AS name,
       autoload,
       source_rank
  FROM wp_option_current
 ORDER BY source_rank DESC, name
SQL;

$summary = SQLiteCompoundWindowExceptOrderCurrentSourceNextPlan::compare($sql, $currentTables, $nextTables);

if (($argv[1] ?? '') === '--self-test') {
    if (array_column($summary['currentRows'], 'name') !== ['blogname', 'active_plugins', 'siteurl']) {
        fwrite(STDERR, "unexpected current EXCEPT/window/ORDER boundary\n");
        exit(1);
    }
    if (array_column($summary['nextRows'], 'name') !== ['blogname', 'home', 'rewrite_rules', 'active_plugins', 'siteurl', 'plugin_alpha', 'plugin_beta']) {
        fwrite(STDERR, "unexpected next EXCEPT/window/ORDER boundary\n");
        exit(1);
    }
    if (($summary['exceptTrace']['currentRemoved'] ?? []) !== ['home', 'rewrite_rules']) {
        fwrite(STDERR, "missing current-source EXCEPT removal diagnostic\n");
        exit(1);
    }
    echo "wordpress-compound-window-except-order-current-source self-test passed\n";
    exit(0);
}

echo json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
