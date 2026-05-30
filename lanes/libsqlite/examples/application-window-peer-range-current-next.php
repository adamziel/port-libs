<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/SQLiteWindowFunction.php';

use PortLibs\LibSqlite\SQLiteWindowFunction;

$optionRows = [
    ['option_name' => 'siteurl', 'autoload' => 'yes', 'priority' => 1.00, 'size' => 10],
    ['option_name' => 'home', 'autoload' => 'yes', 'priority' => 1.00, 'size' => 20],
    ['option_name' => 'blogname', 'autoload' => 'yes', 'priority' => 1.25, 'size' => 30],
    ['option_name' => 'rewrite_rules', 'autoload' => 'yes', 'priority' => 1.50, 'size' => 40],
    ['option_name' => '_transient_feed', 'autoload' => 'no', 'priority' => 2.00, 'size' => 50],
    ['option_name' => '_transient_timeout_feed', 'autoload' => 'no', 'priority' => 2.00, 'size' => 60],
];

$sizes = array_column($optionRows, 'size');
$priorities = array_column($optionRows, 'priority');
$autoloadFilters = array_map(static fn (array $row): int => $row['autoload'] === 'yes' ? 1 : 0, $optionRows);

$currentNextRows = SQLiteWindowFunction::aggregateFrameRows($sizes, $priorities, 'RANGE', 0.0, 0.5);
$autoloadCurrentNextRows = SQLiteWindowFunction::aggregateFrameRows($sizes, $priorities, 'RANGE', 0.0, 0.5, 'NO OTHERS', $autoloadFilters);
$excludePeerRows = SQLiteWindowFunction::aggregateFrameRows($sizes, $priorities, 'RANGE', 0.0, 0.5, 'GROUP');

$summary = [
    'scenario' => 'application-window-peer-range-current-next',
    'rangeCurrentNextFrames' => array_column($currentNextRows, 'frame'),
    'rangeCurrentNextSums' => array_column($currentNextRows, 'sum'),
    'autoloadCurrentNextFrames' => array_column($autoloadCurrentNextRows, 'frame'),
    'autoloadCurrentNextSums' => array_column($autoloadCurrentNextRows, 'sum'),
    'excludeCurrentPeerFrames' => array_column($excludePeerRows, 'frame'),
    'applicationUse' => 'Copied wp_options import diagnostics can preview SQLite RANGE BETWEEN CURRENT ROW AND fractional FOLLOWING peer windows over priority scores without requiring ext/sqlite.',
];

if (($argv[1] ?? null) === '--self-test') {
    assert($summary['rangeCurrentNextFrames'] === [[0, 1, 2, 3], [0, 1, 2, 3], [2, 3], [3, 4, 5], [4, 5], [4, 5]]);
    assert($summary['rangeCurrentNextSums'] === [100, 100, 70, 150, 110, 110]);
    assert($summary['autoloadCurrentNextFrames'] === [[0, 1, 2, 3], [0, 1, 2, 3], [2, 3], [3], [], []]);
    assert($summary['autoloadCurrentNextSums'] === [100, 100, 70, 40, null, null]);
    assert($summary['excludeCurrentPeerFrames'] === [[2, 3], [2, 3], [3], [4, 5], [], []]);

    echo "application window peer RANGE current/following smoke passed\n";
    exit(0);
}

echo json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
