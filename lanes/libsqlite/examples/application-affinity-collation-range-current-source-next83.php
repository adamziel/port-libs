<?php

declare(strict_types=1);

require_once dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\LibSqlite\SQLiteAffinityRangeCurrentSourceCursor;

$rows = [
    ['setting_id' => 1, 'key_name' => 'siteurl', 'key_value' => '10', 'load_policy' => 'yes'],
    ['setting_id' => 2, 'key_name' => 'home', 'key_value' => '02', 'load_policy' => 'yes'],
    ['setting_id' => 3, 'key_name' => 'plugin_alpha', 'key_value' => '9', 'load_policy' => 'no'],
    ['setting_id' => 4, 'key_name' => 'PLUGIN_ALPHA', 'key_value' => '09', 'load_policy' => 'yes'],
    ['setting_id' => 5, 'key_name' => 'theme_mods_twenty', 'key_value' => '10e0', 'load_policy' => 'yes'],
];

$numeric = SQLiteAffinityRangeCurrentSourceCursor::keyValueRowRange(
    $rows,
    'key_value',
    2,
    10,
    'NUMERIC',
    'BINARY',
    ['load_policy' => 'yes']
);

$names = SQLiteAffinityRangeCurrentSourceCursor::keyValueRowRange(
    $rows,
    'key_name',
    'plugin_',
    'theme_',
    'TEXT',
    'NOCASE'
);

$summary = [
    'scenario' => 'application-affinity-collation-range-current-source-next83',
    'numeric_load_policy_setting_ids' => array_column($numeric, 'rowid'),
    'numeric_load_policy_values' => array_map(static fn (array $row): mixed => $row['payload']['key_value'], $numeric),
    'nocase_name_range_setting_ids' => array_column($names, 'rowid'),
    'nocase_name_range_names' => array_map(static fn (array $row): mixed => $row['payload']['key_name'], $names),
    'dependencies' => [
        'native SQLite affinity comparison',
        'native BINARY/NOCASE/RTRIM collation comparison',
        'no ext/sqlite required',
    ],
];

if (($argv[1] ?? '') === '--self-test') {
    if ($summary['numeric_load_policy_setting_ids'] !== [2, 4]) {
        throw new RuntimeException('Unexpected numeric load_policy range rows');
    }
    if ($summary['nocase_name_range_setting_ids'] !== [3, 4, 1]) {
        throw new RuntimeException('Unexpected NOCASE option-name range rows');
    }
    echo "application-affinity-collation-range-current-source-next83 self-test passed\n";
    return;
}

echo json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
