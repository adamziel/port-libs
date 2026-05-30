<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/SQLiteBlobValue.php';
require_once __DIR__ . '/../src/SQLiteWindowFunction.php';

use PortLibs\LibSqlite\SQLiteWindowFunction;

$optionRows = [
    ['option_name' => 'siteurl', 'autoload' => 'yes', 'priority' => 1, 'size' => 10],
    ['option_name' => 'home', 'autoload' => 'yes', 'priority' => 1, 'size' => 20],
    ['option_name' => 'blogname', 'autoload' => 'yes', 'priority' => 2, 'size' => 30],
    ['option_name' => '_transient_feed', 'autoload' => 'no', 'priority' => 4, 'size' => 40],
    ['option_name' => '_transient_timeout_feed', 'autoload' => 'no', 'priority' => 4, 'size' => 50],
    ['option_name' => 'rewrite_rules', 'autoload' => 'yes', 'priority' => 7, 'size' => 60],
];

$sizes = array_column($optionRows, 'size');
$priorities = array_column($optionRows, 'priority');
$autoloadFilters = array_map(static fn (array $row): int => $row['autoload'] === 'yes' ? 1 : 0, $optionRows);

$rangeRows = SQLiteWindowFunction::aggregateFrameRows($sizes, $priorities, 'RANGE', 1, 2, 'TIES', $autoloadFilters);
$groupRows = SQLiteWindowFunction::aggregateFrameRows($sizes, $priorities, 'GROUPS', 1, 1, 'GROUP');

$summary = [
    'rangeTiesAutoloadFrames' => array_column($rangeRows, 'frame'),
    'rangeTiesAutoloadSums' => array_column($rangeRows, 'sum'),
    'groupsExcludePeerFrames' => array_column($groupRows, 'frame'),
    'groupsExcludePeerSums' => array_column($groupRows, 'sum'),
];

if (($argv[1] ?? null) === '--self-test') {
    assert($summary['rangeTiesAutoloadFrames'] === [[0, 2], [2], [0, 1, 2], [], [], [5]]);
    assert($summary['rangeTiesAutoloadSums'] === [40, 30, 60, null, null, 60]);
    assert($summary['groupsExcludePeerFrames'] === [[2], [2], [0, 1, 3, 4], [2, 5], [2, 5], [3, 4]]);
    assert($summary['groupsExcludePeerSums'] === [30, 30, 120, 90, 90, 90]);

    echo "application window RANGE/GROUPS EXCLUDE smoke passed\n";
    exit(0);
}

echo json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
