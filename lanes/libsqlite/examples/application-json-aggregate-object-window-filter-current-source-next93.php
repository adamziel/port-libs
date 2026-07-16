<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\LibSqlite\SQLiteBlobValue;
use PortLibs\LibSqlite\SQLiteJsonB;
use PortLibs\LibSqlite\SQLiteJsonSubtypeValue;
use PortLibs\LibSqlite\SQLiteSelectSql;

$tables = [
    'wp_options' => [
        ['option_id' => 1, 'autoload' => 'yes', 'option_name' => 'siteurl', 'option_value' => 'https://example.test', 'priority' => 40, 'enabled' => 1],
        ['option_id' => 2, 'autoload' => 'yes', 'option_name' => 'blogname', 'option_value' => 'Port Fixture', 'priority' => 30, 'enabled' => 1],
        ['option_id' => 3, 'autoload' => 'no', 'option_name' => 'plugin_rules', 'option_value' => new SQLiteJsonSubtypeValue('{"kind":"rules"}'), 'priority' => 20, 'enabled' => 1],
        ['option_id' => 4, 'autoload' => 'no', 'option_name' => 'plugin_queue', 'option_value' => new SQLiteBlobValue(SQLiteJsonB::encode(['kind' => 'queue'])), 'priority' => 10, 'enabled' => 1],
        ['option_id' => 5, 'autoload' => 'no', 'option_name' => 'empty_option', 'option_value' => null, 'priority' => 60, 'enabled' => 0],
    ],
];

$sql = 'SELECT option_id, json_group_object(option_name, option_value ORDER BY priority DESC) FILTER (WHERE enabled) OVER (PARTITION BY autoload ORDER BY option_id ROWS BETWEEN CURRENT ROW AND 2 FOLLOWING) AS option_window FROM wp_options ORDER BY option_id';
$rows = SQLiteSelectSql::execute($sql, $tables);

$summary = [
    'scenario' => 'application-json-aggregate-object-window-filter-current-source-next93',
    'sqlShape' => 'json_group_object(label, value ORDER BY aggregate_key) FILTER (WHERE predicate) OVER (... ROWS BETWEEN CURRENT ROW AND N FOLLOWING)',
    'rows' => $rows,
    'applicationUse' => 'Copied wp_options import previews can build current-source option summary objects from the active window frame while FILTER removes disabled rows before JSON object aggregation.',
    'dependencyClosure' => 'No new support component required; reuses native SELECT SQL, window frame, JSON aggregate, JSON subtype, and JSONB helpers.',
];

if (($argv[1] ?? null) === '--self-test') {
    if (($rows[2]['option_window'] ?? null) !== '{"plugin_rules":{"kind":"rules"},"plugin_queue":{"kind":"queue"}}') {
        fwrite(STDERR, "unexpected plugin option window\n");
        exit(1);
    }
    if (($rows[4]['option_window'] ?? null) !== '{}') {
        fwrite(STDERR, "disabled tail row should produce an empty object frame\n");
        exit(1);
    }
    echo "application-json-aggregate-object-window-filter-current-source-next93 self-test passed\n";
    return;
}

echo json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES), "\n";
