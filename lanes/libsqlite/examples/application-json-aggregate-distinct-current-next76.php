<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\LibSqlite\SQLiteBlobValue;
use PortLibs\LibSqlite\SQLiteJsonB;
use PortLibs\LibSqlite\SQLiteJsonSubtypeValue;
use PortLibs\LibSqlite\SQLiteSelectSql;

$tables = [
    'wp_options' => [
        ['option_id' => 1, 'option_name' => 'siteurl', 'option_value' => 'https://example.test', 'autoload' => 'yes', 'option_size' => 20],
        ['option_id' => 2, 'option_name' => 'siteurl', 'option_value' => 'https://example.test', 'autoload' => 'yes', 'option_size' => 20],
        ['option_id' => 3, 'option_name' => 'plugin_rules', 'option_value' => new SQLiteJsonSubtypeValue('[{"name":"seo"},{"name":"cache"}]'), 'autoload' => 'no', 'option_size' => 30],
        ['option_id' => 4, 'option_name' => 'plugin_rules', 'option_value' => new SQLiteJsonSubtypeValue('[{"name":"seo"},{"name":"cache"}]'), 'autoload' => 'no', 'option_size' => 30],
        ['option_id' => 5, 'option_name' => 'plugin_queue', 'option_value' => new SQLiteBlobValue(SQLiteJsonB::encode(['pending' => 2])), 'autoload' => 'no', 'option_size' => 25],
        ['option_id' => 6, 'option_name' => 'empty_option', 'option_value' => null, 'autoload' => 'no', 'option_size' => 0],
        ['option_id' => 7, 'option_name' => 'empty_option', 'option_value' => null, 'autoload' => 'no', 'option_size' => 0],
    ],
];

$summary = SQLiteSelectSql::execute(
    'SELECT autoload, json_group_array(DISTINCT option_name ORDER BY option_name) AS unique_names, jsonb_group_array(DISTINCT option_value ORDER BY option_id) FILTER (WHERE option_size >= 0) AS unique_payloads FROM wp_options GROUP BY autoload ORDER BY autoload',
    $tables,
);

$report = [
    'groups' => [
        [
            'autoload' => $summary[0]['autoload'],
            'uniqueNames' => $summary[0]['unique_names'],
            'uniquePayloads' => SQLiteJsonB::decode($summary[0]['unique_payloads']->bytes),
        ],
        [
            'autoload' => $summary[1]['autoload'],
            'uniqueNames' => $summary[1]['unique_names'],
            'uniquePayloads' => SQLiteJsonB::decode($summary[1]['unique_payloads']->bytes),
        ],
    ],
    'applicationUse' => 'Copied wp_options rows execute parser-level json_group_array(DISTINCT ... ORDER BY ...) and jsonb_group_array(DISTINCT ... ORDER BY ...) FILTER summaries for duplicate option cleanup/import diagnostics without requiring ext/sqlite.',
];

if ($report['groups'][0]['uniqueNames'] !== '["empty_option","plugin_queue","plugin_rules"]') {
    fwrite(STDERR, 'Unexpected no-autoload distinct names: ' . $report['groups'][0]['uniqueNames'] . PHP_EOL);
    exit(1);
}

echo json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
