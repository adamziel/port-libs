<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteBlobValue;
use PortLibs\LibSqlite\SQLiteJsonB;
use PortLibs\LibSqlite\SQLiteJsonSubtypeValue;
use PortLibs\LibSqlite\SQLiteSelectSql;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$tables = [
    'wp_options' => [
        ['option_id' => 1, 'autoload' => 'yes', 'option_name' => 'siteurl', 'enabled' => 1, 'payload' => new SQLiteJsonSubtypeValue('{"kind":"site","enabled":true}')],
        ['option_id' => 2, 'autoload' => 'yes', 'option_name' => 'blogname', 'enabled' => 1, 'payload' => new SQLiteJsonSubtypeValue('{"kind":"blog","enabled":true}')],
        ['option_id' => 3, 'autoload' => 'yes', 'option_name' => 'siteurl', 'enabled' => 1, 'payload' => new SQLiteJsonSubtypeValue('{"kind":"site","enabled":true}')],
        ['option_id' => 4, 'autoload' => 'no', 'option_name' => 'plugin_rules', 'enabled' => 1, 'payload' => new SQLiteBlobValue(SQLiteJsonB::encode(['kind' => 'rules', 'enabled' => true]))],
        ['option_id' => 5, 'autoload' => 'no', 'option_name' => 'plugin_queue', 'enabled' => 1, 'payload' => new SQLiteBlobValue(SQLiteJsonB::encode(['kind' => 'queue', 'enabled' => true]))],
        ['option_id' => 6, 'autoload' => 'no', 'option_name' => 'plugin_rules', 'enabled' => 1, 'payload' => new SQLiteBlobValue(SQLiteJsonB::encode(['kind' => 'rules', 'enabled' => true]))],
        ['option_id' => 7, 'autoload' => 'no', 'option_name' => 'disabled_plugin', 'enabled' => 0, 'payload' => new SQLiteJsonSubtypeValue('{"kind":"disabled","enabled":false}')],
    ],
];

$sql = 'SELECT option_id, jsonb_group_array(DISTINCT payload) FILTER (WHERE enabled) OVER (PARTITION BY autoload ORDER BY option_id ROWS BETWEEN CURRENT ROW AND 2 FOLLOWING) AS payload_window FROM wp_options ORDER BY option_id';
$rows = SQLiteSelectSql::execute($sql, $tables);

$windows = array_map(
    static fn (array $row): array => [
        'option_id' => $row['option_id'],
        'payload_window' => SQLiteJsonB::decode($row['payload_window']->bytes),
    ],
    $rows,
);

$result = [
    'scenario' => 'application-json-aggregate-window-jsonb-distinct-current-source-next105',
    'applicationUse' => 'Copied wp_options diagnostics can summarize current-source JSONB option payload windows while DISTINCT collapses duplicate JSONB payloads and FILTER skips disabled plugin rows before import.',
    'sqlShape' => $sql,
    'windows' => $windows,
    'dependency' => 'native PHP SELECT SQL and JSONB aggregate window execution; no ext/sqlite required',
];

if (PHP_SAPI === 'cli' && basename($_SERVER['SCRIPT_FILENAME'] ?? '') === basename(__FILE__)) {
    if (($result['windows'][3]['payload_window'] ?? null) !== [['kind' => 'rules', 'enabled' => true], ['kind' => 'queue', 'enabled' => true]]) {
        fwrite(STDERR, "unexpected plugin JSONB DISTINCT window\n");
        exit(1);
    }
    if (($result['windows'][5]['payload_window'] ?? null) !== [['kind' => 'rules', 'enabled' => true]]) {
        fwrite(STDERR, "unexpected filtered JSONB DISTINCT tail window\n");
        exit(1);
    }

    echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
}

return $result;
