<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteVdbeWindowAggregateCursor;

require_once __DIR__ . '/../src/SQLiteBlobValue.php';
require_once __DIR__ . '/../src/SQLiteAffinityComparison.php';
require_once __DIR__ . '/../src/SQLiteNumericAggregate.php';
require_once __DIR__ . '/../src/SQLiteTextAggregate.php';
require_once __DIR__ . '/../src/SQLiteTextAggregateState.php';
require_once __DIR__ . '/../src/SQLiteVdbeSortCompare.php';
require_once __DIR__ . '/../src/SQLiteVdbeWindowAggregateCursor.php';

$optionRows = [
    ['rowid' => 1, 'site' => 1, 'bucket' => 1.0, 'option_name' => 'siteurl', 'bytes' => 10, 'autoloaded' => 1],
    ['rowid' => 2, 'site' => 1, 'bucket' => 1.0, 'option_name' => 'home', 'bytes' => 20, 'autoloaded' => 0],
    ['rowid' => 3, 'site' => 1, 'bucket' => 1.25, 'option_name' => 'blogname', 'bytes' => 30, 'autoloaded' => 1],
    ['rowid' => 4, 'site' => 1, 'bucket' => 1.5, 'option_name' => 'rewrite_rules', 'bytes' => 40, 'autoloaded' => 1],
    ['rowid' => 5, 'site' => 1, 'bucket' => 2.0, 'option_name' => '_transient_cache', 'bytes' => 60, 'autoloaded' => 1],
    ['rowid' => 6, 'site' => 2, 'bucket' => 1.0, 'option_name' => 'network_siteurl', 'bytes' => 70, 'autoloaded' => 1],
    ['rowid' => 7, 'site' => 2, 'bucket' => 1.4, 'option_name' => 'network_home', 'bytes' => 80, 'autoloaded' => 0],
    ['rowid' => 8, 'site' => 2, 'bucket' => 1.4, 'option_name' => 'network_plugin', 'bytes' => 90, 'autoloaded' => 1],
];

$cursor = new SQLiteVdbeWindowAggregateCursor(
    $optionRows,
    'bytes',
    ['site'],
    ['bucket'],
    'autoloaded',
    0.0,
    0.5,
    'D',
    [],
    'D',
    [],
    [],
    [],
    'RANGE',
);

$summary = [];
while (!$cursor->eof()) {
    $row = $cursor->currentRow();
    $summary[] = [
        'option_name' => $row['option_name'],
        'site' => $row['site'],
        'bucket' => $row['bucket'],
        'frame_rowids' => array_column($cursor->currentFrameRows(false), 'rowid'),
        'autoloaded_frame_rowids' => array_column($cursor->currentFrameRows(true), 'rowid'),
        'autoloaded_bytes' => $cursor->sum(),
        'autoloaded_concat' => $cursor->groupConcat('|'),
    ];
    $cursor->next();
}

if (($argv[1] ?? '') === '--self-test') {
    $expectedFrames = [[1, 2, 3, 4], [1, 2, 3, 4], [3, 4], [4, 5], [5], [6, 7, 8], [7, 8], [7, 8]];
    $expectedSums = [80, 80, 70, 100, 60, 160, 90, 90];
    if (array_column($summary, 'frame_rowids') !== $expectedFrames || array_column($summary, 'autoloaded_bytes') !== $expectedSums) {
        fwrite(STDERR, "application VDBE window RANGE peer current/following smoke failed\n");
        exit(1);
    }

    echo "application VDBE window RANGE peer current/following smoke passed\n";
    exit(0);
}

echo json_encode([
    'scenario' => 'application-vdbe-window-range-peer-current-next34',
    'rows' => $summary,
], JSON_PRETTY_PRINT) . PHP_EOL;
