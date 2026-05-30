<?php

declare(strict_types=1);

require __DIR__ . '/../../../tools/bootstrap.php';

use PortLibs\LibSqlite\SQLiteVdbeWindowAggregateCursor;
use PortLibs\LibSqlite\SQLiteVdbeWindowChainFramePlan;

$rows = [
    ['rowid' => 1, 'blog_id' => 1, 'option_name' => 'active_plugins', 'bytes' => 120, 'autoloaded' => 1, 'large' => 0],
    ['rowid' => 2, 'blog_id' => 1, 'option_name' => 'cron', 'bytes' => 420, 'autoloaded' => 1, 'large' => 1],
    ['rowid' => 3, 'blog_id' => 1, 'option_name' => 'rewrite_rules', 'bytes' => 900, 'autoloaded' => 0, 'large' => 1],
    ['rowid' => 4, 'blog_id' => 2, 'option_name' => 'active_plugins', 'bytes' => 140, 'autoloaded' => 1, 'large' => 0],
    ['rowid' => 5, 'blog_id' => 2, 'option_name' => 'cron', 'bytes' => 390, 'autoloaded' => 1, 'large' => 1],
    ['rowid' => 6, 'blog_id' => 2, 'option_name' => 'rewrite_rules', 'bytes' => 850, 'autoloaded' => 0, 'large' => 1],
];

$cursor = static fn (string $filter, int $preceding, int $following, string $unit, string $exclude): SQLiteVdbeWindowAggregateCursor => new SQLiteVdbeWindowAggregateCursor(
    $rows,
    'bytes',
    ['blog_id'],
    ['bytes', 'option_name'],
    $filter,
    $preceding,
    $following,
    ['INTEGER'],
    [],
    ['NUMERIC', 'TEXT'],
    ['BINARY', 'NOCASE'],
    [false, false],
    [],
    $unit,
    $exclude,
);

$plan = new SQLiteVdbeWindowChainFramePlan([
    'autoload_rows' => $cursor('autoloaded', 1, 1, 'ROWS', 'NO OTHERS'),
    'large_next_group' => $cursor('large', 0, 1, 'GROUPS', 'CURRENT ROW'),
]);

$summary = $plan->currentNext();
$payload = [
    'autoload_rows' => [
        'frame_rowids' => $summary['autoload_rows']['frameRowids'],
        'next_frame_rowids' => $summary['autoload_rows']['nextFrameRowids'],
        'total' => $summary['autoload_rows']['total'],
    ],
    'large_next_group' => [
        'frame_rowids' => $summary['large_next_group']['frameRowids'],
        'next_frame_rowids' => $summary['large_next_group']['nextFrameRowids'],
        'total' => $summary['large_next_group']['total'],
    ],
];

if (($argv[1] ?? null) === '--self-test') {
    $expected = [
        'autoload_rows' => [
            'frame_rowids' => [1, 2],
            'next_frame_rowids' => [1, 2],
            'total' => 540.0,
        ],
        'large_next_group' => [
            'frame_rowids' => [2],
            'next_frame_rowids' => [3],
            'total' => 420.0,
        ],
    ];
    if ($payload !== $expected) {
        fwrite(STDERR, json_encode($payload, JSON_PRETTY_PRINT) . "\n");
        exit(1);
    }

    echo "application-vdbe-window-chain-frame-filter-current-next54 self-test passed\n";
    exit(0);
}

echo json_encode($payload, JSON_PRETTY_PRINT) . "\n";
