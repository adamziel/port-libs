<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\LibSqlite\SQLiteSelectSql;

$currentOptions = [
    ['option_id' => 1, 'option_name' => 'siteurl', 'autoload' => 'yes'],
    ['option_id' => 2, 'option_name' => 'home', 'autoload' => 'yes'],
    ['option_id' => 4, 'option_name' => 'active_plugins', 'autoload' => 'yes'],
];
$nextOptions = [
    ['option_id' => 1, 'option_name' => 'siteurl', 'autoload' => 'yes'],
    ['option_id' => 2, 'option_name' => 'home', 'autoload' => 'yes'],
    ['option_id' => 5, 'option_name' => 'new_plugin_flag', 'autoload' => 'no'],
];

$sql = "SELECT incoming.id, incoming.name FROM (VALUES (4, 'active_plugins'), (5, 'new_plugin_flag')) AS incoming(id, name) UNION ALL SELECT option_id, option_name FROM wp_options WHERE autoload = 'no' ORDER BY id";

$currentRows = SQLiteSelectSql::execute($sql, ['wp_options' => $currentOptions]);
$nextRows = SQLiteSelectSql::execute($sql, ['wp_options' => $nextOptions]);

$result = [
    'scenario' => 'application-compound-values-name-resolution-current-source-next123',
    'sql' => $sql,
    'currentRows' => $currentRows,
    'nextRows' => $nextRows,
    'currentNames' => array_column($currentRows, 'name'),
    'nextNames' => array_column($nextRows, 'name'),
    'behavior' => 'VALUES source column aliases resolve through qualified names before compound row names are taken from the left-most arm; the next source table arm is still renamed positionally.',
];

if (PHP_SAPI === 'cli' && in_array('--self-test', $_SERVER['argv'] ?? [], true)) {
    if ($result['currentNames'] !== ['active_plugins', 'new_plugin_flag']) {
        throw new RuntimeException('current source VALUES compound rows did not match expected names');
    }
    if ($result['nextNames'] !== ['active_plugins', 'new_plugin_flag', 'new_plugin_flag']) {
        throw new RuntimeException('next source table arm was not positionally renamed after VALUES arm');
    }

    echo "application-compound-values-name-resolution-current-source-next123 self-test passed\n";
    return;
}

echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
