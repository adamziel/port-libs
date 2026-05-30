<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteAffinityComparison;
use PortLibs\LibSqlite\SQLiteBlobValue;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$rows = [
    ['option_name' => 'siteurl', 'option_value' => '10', 'expected' => 10, 'affinity' => 'NUMERIC'],
    ['option_name' => 'home', 'option_value' => '10x', 'expected' => 10, 'affinity' => 'NUMERIC'],
    ['option_name' => 'autoload_flag', 'option_value' => 1, 'expected' => '1', 'affinity' => 'TEXT'],
    ['option_name' => 'plugin_blob', 'option_value' => new SQLiteBlobValue('10'), 'expected' => '10', 'affinity' => 'NONE'],
];

$summary = [];
foreach ($rows as $row) {
    $comparison = SQLiteAffinityComparison::compare($row['expected'], $row['option_value'], $row['affinity'], 'NONE');
    $pair = SQLiteAffinityComparison::coercedPair($row['expected'], $row['option_value'], $row['affinity'], 'NONE');
    $summary[] = [
        'option_name' => $row['option_name'],
        'left_storage' => $pair['leftStorageClass'],
        'right_storage' => $pair['rightStorageClass'],
        'matches' => $comparison === 0,
    ];
}

if (in_array('--self-test', $argv, true)) {
    $expected = [
        ['option_name' => 'siteurl', 'left_storage' => 'integer', 'right_storage' => 'integer', 'matches' => true],
        ['option_name' => 'home', 'left_storage' => 'integer', 'right_storage' => 'text', 'matches' => false],
        ['option_name' => 'autoload_flag', 'left_storage' => 'text', 'right_storage' => 'text', 'matches' => true],
        ['option_name' => 'plugin_blob', 'left_storage' => 'text', 'right_storage' => 'blob', 'matches' => false],
    ];
    if ($summary !== $expected) {
        fwrite(STDERR, json_encode($summary, JSON_PRETTY_PRINT) . "\n");
        exit(1);
    }

    echo "OK application affinity comparison storage-class smoke\n";
    exit(0);
}

echo json_encode($summary, JSON_PRETTY_PRINT) . "\n";
