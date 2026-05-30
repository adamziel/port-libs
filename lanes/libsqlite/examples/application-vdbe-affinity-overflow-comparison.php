<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteBlobValue;
use PortLibs\LibSqlite\SQLiteVdbeSortCompare;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$rows = [
    ['option_id' => 1, 'option_name' => 'siteurl', 'autoload' => 'yes', 'priority' => '9223372036854775808'],
    ['option_id' => 2, 'option_name' => 'home', 'autoload' => 'yes', 'priority' => 9223372036854775807],
    ['option_id' => 3, 'option_name' => 'blogname', 'autoload' => 'yes', 'priority' => '900.0'],
    ['option_id' => 4, 'option_name' => 'stylesheet', 'autoload' => 'yes', 'priority' => new SQLiteBlobValue('9223372036854775808')],
    ['option_id' => 5, 'option_name' => 'template', 'autoload' => 'no', 'priority' => null],
];

$ordered = SQLiteVdbeSortCompare::sortRows($rows, ['autoload', 'priority', 'option_id'], 'GCD');
$summary = array_map(
    static fn (array $row): array => [
        'option_id' => $row['option_id'],
        'option_name' => $row['option_name'],
        'autoload' => $row['autoload'],
    ],
    $ordered
);

if (in_array('--self-test', $argv, true)) {
    $expected = [
        ['option_id' => 5, 'option_name' => 'template', 'autoload' => 'no'],
        ['option_id' => 3, 'option_name' => 'blogname', 'autoload' => 'yes'],
        ['option_id' => 2, 'option_name' => 'home', 'autoload' => 'yes'],
        ['option_id' => 1, 'option_name' => 'siteurl', 'autoload' => 'yes'],
        ['option_id' => 4, 'option_name' => 'stylesheet', 'autoload' => 'yes'],
    ];
    if ($summary !== $expected) {
        fwrite(STDERR, json_encode($summary, JSON_PRETTY_PRINT) . "\n");
        exit(1);
    }

    echo "OK application vdbe affinity overflow comparison smoke\n";
    exit(0);
}

echo json_encode($summary, JSON_PRETTY_PRINT) . "\n";
