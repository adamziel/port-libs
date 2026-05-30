<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteBlobValue;
use PortLibs\LibSqlite\SQLiteJsonB;
use PortLibs\LibSqlite\SQLiteSelectSql;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$rows = [
    [
        'option_id' => 1,
        'option_name' => 'plugin_cache_settings',
        'option_value' => '{"plugin":{"name":"cache","channel":"stable"}}',
        'autoload' => 'yes',
    ],
    [
        'option_id' => 2,
        'option_name' => 'plugin_forms_settings',
        'option_value' => new SQLiteBlobValue(SQLiteJsonB::encode([
            'plugin' => ['name' => 'forms', 'channel' => 'beta'],
        ])),
        'autoload' => 'no',
    ],
];

$sql = "SELECT option_name, (option_value -> '$.plugin.channel') || ':' || (option_value ->> '$.plugin.name') AS label, option_value ->> ('$.plugin.' || 'channel') AS channel FROM wp_options ORDER BY option_id";
$result = SQLiteSelectSql::execute($sql, ['wp_options' => $rows]);

if (($argv[1] ?? null) === '--self-test') {
    $expected = [
        ['option_name' => 'plugin_cache_settings', 'label' => '"stable":cache', 'channel' => 'stable'],
        ['option_name' => 'plugin_forms_settings', 'label' => '"beta":forms', 'channel' => 'beta'],
    ];
    if ($result !== $expected) {
        fwrite(STDERR, json_encode(['expected' => $expected, 'actual' => $result], JSON_PRETTY_PRINT) . PHP_EOL);
        exit(1);
    }

    echo "application-json-path-operator-precedence self-test passed\n";
    exit(0);
}

echo json_encode([
    'scenario' => 'application-json-path-operator-precedence',
    'applicationUse' => 'Copied wp_options JSON diagnostics preserve SQLite precedence where ||, ->, and ->> share one left-associative precedence tier, while parenthesized concatenation can still build a JSON path RHS.',
    'sql' => $sql,
    'rows' => $result,
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
