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
    ['rowid' => 3, 'site' => 1, 'bucket' => 1.2, 'option_name' => 'blogname', 'bytes' => 30, 'autoloaded' => '1'],
    ['rowid' => 4, 'site' => 1, 'bucket' => 1.4, 'option_name' => 'rewrite_rules', 'bytes' => 40, 'autoloaded' => '0.0'],
    ['rowid' => 5, 'site' => 1, 'bucket' => 1.6, 'option_name' => 'plugin_settings', 'bytes' => null, 'autoloaded' => '2'],
    ['rowid' => 6, 'site' => 1, 'bucket' => 2.0, 'option_name' => '_transient_feed', 'bytes' => 60, 'autoloaded' => true],
    ['rowid' => 7, 'site' => 2, 'bucket' => 1.0, 'option_name' => 'network_siteurl', 'bytes' => 70, 'autoloaded' => 1],
    ['rowid' => 8, 'site' => 2, 'bucket' => 1.3, 'option_name' => 'network_home', 'bytes' => 80, 'autoloaded' => null],
    ['rowid' => 9, 'site' => 2, 'bucket' => 1.3, 'option_name' => 'network_plugin', 'bytes' => 90, 'autoloaded' => '0x'],
    ['rowid' => 10, 'site' => 2, 'bucket' => 1.7, 'option_name' => 'network_cache', 'bytes' => 100, 'autoloaded' => '3'],
];

$cursor = new SQLiteVdbeWindowAggregateCursor(
    $optionRows,
    'bytes',
    ['site'],
    ['bucket'],
    'autoloaded',
    0.0,
    0.4,
    'D',
    [],
    'D',
    [],
    [],
    [],
    'RANGE',
    'CURRENT ROW',
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
        'autoloaded_count_star' => $cursor->countFilteredAll(),
        'autoloaded_count_value' => $cursor->countValue(),
        'autoloaded_bytes' => $cursor->sum(),
        'autoloaded_concat' => $cursor->groupConcat('|'),
    ];
    $cursor->next();
}

if (($argv[1] ?? '') === '--self-test') {
    $expectedFrames = [[2, 3, 4], [1, 3, 4], [4, 5], [5], [6], [], [8, 9], [9, 10], [8, 10], []];
    $expectedFiltered = [[3], [1, 3], [5], [5], [6], [], [], [10], [10], []];
    $expectedSums = [30, 40, null, null, 60, null, null, 100, 100, null];
    if (
        array_column($summary, 'frame_rowids') !== $expectedFrames
        || array_column($summary, 'autoloaded_frame_rowids') !== $expectedFiltered
        || array_column($summary, 'autoloaded_bytes') !== $expectedSums
    ) {
        fwrite(STDERR, "application VDBE window FILTER RANGE EXCLUDE CURRENT ROW smoke failed\n");
        exit(1);
    }

    echo "application VDBE window FILTER RANGE EXCLUDE CURRENT ROW smoke passed\n";
    exit(0);
}

echo json_encode([
    'scenario' => 'application-vdbe-window-filter-range-exclude-current-next53',
    'rows' => $summary,
], JSON_PRETTY_PRINT) . PHP_EOL;
