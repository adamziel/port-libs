<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\LibSqlite\SQLiteCompoundExceptOrderAffinityCurrentSourceNextPlan;

$currentTables = [
    'wp_options' => [
        ['option_id' => 1, 'option_name' => 'siteurl', 'autoload' => 'yes', 'weight' => 1],
        ['option_id' => 2, 'option_name' => 'home', 'autoload' => 'yes', 'weight' => '1'],
        ['option_id' => 3, 'option_name' => 'blogname', 'autoload' => 'yes', 'weight' => 1.5],
        ['option_id' => 4, 'option_name' => 'Plugin_Flag', 'autoload' => 'yes', 'weight' => '02'],
    ],
    'network_options' => [
        ['option_id' => 10, 'option_name' => 'SiteURL', 'autoload' => 'yes', 'weight' => 1.0],
        ['option_id' => 11, 'option_name' => 'HOME', 'autoload' => 'yes', 'weight' => 1],
    ],
];
$nextTables = $currentTables;
$nextTables['wp_options'][] = ['option_id' => 5, 'option_name' => 'new_plugin_flag', 'autoload' => 'yes', 'weight' => '2'];
$nextTables['network_options'][] = ['option_id' => 12, 'option_name' => 'blogname', 'autoload' => 'yes', 'weight' => 1.5];

$sql = <<<'SQL'
SELECT option_name COLLATE NOCASE AS name,
       weight AS class_value
  FROM wp_options
 WHERE autoload = 'yes'
EXCEPT
SELECT option_name AS name,
       weight AS class_value
  FROM network_options
 WHERE autoload = 'yes'
 ORDER BY class_value ASC NULLS LAST, name COLLATE NOCASE DESC
SQL;

$result = [
    'scenario' => 'wordpress-compound-except-order-affinity-current-source-next',
    'wordpressUse' => 'Copied wp_options migration checks can subtract network-level options with EXCEPT, then apply SQLite tail ORDER BY storage-class and NOCASE rules so changed current-source rows are replayed in deterministic import order without ext/sqlite.',
    'plan' => SQLiteCompoundExceptOrderAffinityCurrentSourceNextPlan::compare($sql, $currentTables, $nextTables),
    'dependency' => 'native PHP compound SELECT EXCEPT, tail ORDER BY, and SQLite storage-class comparison; no new support component required',
];

if (PHP_SAPI === 'cli' && basename(__FILE__) === basename($_SERVER['SCRIPT_FILENAME'] ?? '')) {
    $currentNames = array_column($result['plan']['currentRows'], 'name');
    $nextNames = array_column($result['plan']['nextRows'], 'name');
    if ($currentNames !== ['blogname', 'Plugin_Flag', 'home'] || $nextNames !== ['Plugin_Flag', 'home', 'new_plugin_flag']) {
        fwrite(STDERR, 'Unexpected compound EXCEPT ORDER affinity current/next rows: ' . json_encode([$currentNames, $nextNames], JSON_UNESCAPED_SLASHES) . PHP_EOL);
        exit(1);
    }

    echo "wordpress-compound-except-order-affinity-current-source-next self-test passed\n";
}

return $result;
