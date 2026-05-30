<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteBlobValue;
use PortLibs\LibSqlite\SQLiteJsonB;
use PortLibs\LibSqlite\SQLiteJsonSubtypeValue;
use PortLibs\LibSqlite\SQLiteSelectSql;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$tables = [
    'wp_options' => [
        ['option_id' => 1, 'option_name' => 'siteurl', 'option_value' => 'https://example.test', 'autoload' => 'yes', 'option_size' => 20],
        ['option_id' => 2, 'option_name' => 'blogname', 'option_value' => 'Port Fixture', 'autoload' => 'yes', 'option_size' => 12],
        ['option_id' => 3, 'option_name' => 'plugin_rules', 'option_value' => new SQLiteJsonSubtypeValue('[{"name":"seo"},{"name":"cache"}]'), 'autoload' => 'no', 'option_size' => 30],
        ['option_id' => 4, 'option_name' => 'plugin_queue', 'option_value' => new SQLiteBlobValue(SQLiteJsonB::encode(['pending' => 2, 'ok' => true])), 'autoload' => 'no', 'option_size' => 25],
        ['option_id' => 5, 'option_name' => 'empty_option', 'option_value' => null, 'autoload' => 'no', 'option_size' => 0],
    ],
];

$autoloadGroups = SQLiteSelectSql::execute(
    "SELECT autoload, json_group_array(option_name ORDER BY option_id) FILTER (WHERE option_size > 0) AS option_names FROM wp_options GROUP BY autoload ORDER BY autoload",
    $tables,
);
$pluginPayloads = SQLiteSelectSql::execute(
    "SELECT json_group_array(option_value ORDER BY option_id) FILTER (WHERE autoload = 'no') AS payloads FROM wp_options",
    $tables,
);
$jsonbNames = SQLiteSelectSql::execute(
    "SELECT jsonb_group_array(option_name ORDER BY option_name) FILTER (WHERE autoload = 'no') AS names FROM wp_options",
    $tables,
);

echo json_encode([
    'autoloadGroups' => $autoloadGroups,
    'pluginPayloads' => $pluginPayloads[0]['payloads'],
    'pluginNamesJsonbDecoded' => SQLiteJsonB::decode($jsonbNames[0]['names']->bytes),
    'applicationUse' => 'Copied wp_options rows execute parser-level json_group_array()/jsonb_group_array() with aggregate-local ORDER BY and FILTER clauses for local import summaries without requiring ext/sqlite.',
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n";
