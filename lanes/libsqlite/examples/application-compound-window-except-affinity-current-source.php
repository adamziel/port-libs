<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';
require_once __DIR__ . '/../src/SQLiteVdbeWindowAggregateCursor.php';

use PortLibs\LibSqlite\SQLiteCompoundWindowExceptAffinityCurrentSourceNextPlan;

$currentTables = [
    'wp_options' => [
        ['option_id' => 1, 'option_name' => 'siteurl', 'autoload' => 'yes', 'weight' => 1],
        ['option_id' => 2, 'option_name' => 'home', 'autoload' => 'yes', 'weight' => '1'],
        ['option_id' => 3, 'option_name' => 'blogname', 'autoload' => 'yes', 'weight' => 1.5],
    ],
    'network_options' => [
        ['option_id' => 10, 'option_name' => 'SiteURL', 'autoload' => 'yes', 'weight' => 1.0],
        ['option_id' => 11, 'option_name' => 'home', 'autoload' => 'yes', 'weight' => 1],
    ],
];
$nextTables = $currentTables;
$nextTables['wp_options'][] = ['option_id' => 4, 'option_name' => 'new_plugin_flag', 'autoload' => 'yes', 'weight' => '2'];
$nextTables['network_options'][] = ['option_id' => 12, 'option_name' => 'blogname', 'autoload' => 'yes', 'weight' => 1.5];
$nextTables['network_options'][] = ['option_id' => 13, 'option_name' => 'new_plugin_flag', 'autoload' => 'yes', 'weight' => 2];

$sql = <<<'SQL'
SELECT option_name COLLATE NOCASE AS name,
       weight AS class_value,
       sum(CAST(weight AS REAL)) OVER (
           ORDER BY option_id
           ROWS BETWEEN CURRENT ROW AND CURRENT ROW
       ) AS frame_weight
  FROM wp_options
EXCEPT
SELECT option_name AS name,
       weight AS class_value,
       sum(CAST(weight AS REAL)) OVER (
           ORDER BY option_id
           ROWS BETWEEN CURRENT ROW AND CURRENT ROW
       ) AS frame_weight
  FROM network_options
 ORDER BY name COLLATE NOCASE, class_value
SQL;

$result = [
    'scenario' => 'application-compound-window-except-affinity-current-source-next133',
    'applicationUse' => 'Copied wp_options import diagnostics can subtract network-level options with SQLite EXCEPT while preserving window output and storage-class affinity distinctions before writing migrated options.',
    'plan' => SQLiteCompoundWindowExceptAffinityCurrentSourceNextPlan::compareWindowExceptAffinity($sql, $currentTables, $nextTables),
    'dependency' => 'native PHP SELECT compound/window/affinity execution; no ext/sqlite required',
];

if (PHP_SAPI === 'cli' && basename(__FILE__) === basename($_SERVER['SCRIPT_FILENAME'] ?? '')) {
    $currentNames = array_column($result['plan']['currentRows'], 'name');
    $nextNames = array_column($result['plan']['nextRows'], 'name');
    if ($currentNames !== ['blogname', 'home'] || $nextNames !== ['home', 'new_plugin_flag']) {
        fwrite(STDERR, 'Unexpected compound EXCEPT affinity current/next rows: ' . json_encode([$currentNames, $nextNames], JSON_UNESCAPED_SLASHES) . PHP_EOL);
        exit(1);
    }

    echo "application-compound-window-except-affinity-current-source-next133 self-test passed\n";
}

return $result;
