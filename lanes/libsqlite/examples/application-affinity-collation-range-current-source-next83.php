<?php

declare(strict_types=1);

require_once dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\LibSqlite\SQLiteAffinityRangeCurrentSourceCursor;

$rows = [
    ['option_id' => 1, 'option_name' => 'siteurl', 'option_value' => '10', 'autoload' => 'yes'],
    ['option_id' => 2, 'option_name' => 'home', 'option_value' => '02', 'autoload' => 'yes'],
    ['option_id' => 3, 'option_name' => 'plugin_alpha', 'option_value' => '9', 'autoload' => 'no'],
    ['option_id' => 4, 'option_name' => 'PLUGIN_ALPHA', 'option_value' => '09', 'autoload' => 'yes'],
    ['option_id' => 5, 'option_name' => 'theme_mods_twenty', 'option_value' => '10e0', 'autoload' => 'yes'],
];

$numeric = SQLiteAffinityRangeCurrentSourceCursor::optionRowRange(
    $rows,
    'option_value',
    2,
    10,
    'NUMERIC',
    'BINARY',
    ['autoload' => 'yes']
);

$names = SQLiteAffinityRangeCurrentSourceCursor::optionRowRange(
    $rows,
    'option_name',
    'plugin_',
    'theme_',
    'TEXT',
    'NOCASE'
);

$summary = [
    'scenario' => 'application-affinity-collation-range-current-source-next83',
    'numeric_autoload_option_ids' => array_column($numeric, 'rowid'),
    'numeric_autoload_values' => array_map(static fn (array $row): mixed => $row['payload']['option_value'], $numeric),
    'nocase_name_range_option_ids' => array_column($names, 'rowid'),
    'nocase_name_range_names' => array_map(static fn (array $row): mixed => $row['payload']['option_name'], $names),
    'dependencies' => [
        'native SQLite affinity comparison',
        'native BINARY/NOCASE/RTRIM collation comparison',
        'no ext/sqlite required',
    ],
];

if (($argv[1] ?? '') === '--self-test') {
    if ($summary['numeric_autoload_option_ids'] !== [2, 4]) {
        throw new RuntimeException('Unexpected numeric autoload range rows');
    }
    if ($summary['nocase_name_range_option_ids'] !== [3, 4, 1]) {
        throw new RuntimeException('Unexpected NOCASE option-name range rows');
    }
    echo "application-affinity-collation-range-current-source-next83 self-test passed\n";
    return;
}

echo json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
