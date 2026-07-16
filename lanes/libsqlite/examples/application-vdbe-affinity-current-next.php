<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\LibSqlite\SQLiteVdbeSorterCursor;

$rows = [
    ['option_id' => 1, 'autoload' => 'YES', 'priority' => '01', 'option_name' => 'siteurl'],
    ['option_id' => 2, 'autoload' => 'yes', 'priority' => 1, 'option_name' => 'home'],
    ['option_id' => 3, 'autoload' => 'yes', 'priority' => '2', 'option_name' => 'blogname'],
    ['option_id' => 4, 'autoload' => 'no', 'priority' => null, 'option_name' => 'plugin_a'],
];

$cursor = new SQLiteVdbeSorterCursor($rows);
$boundaries = [];
while (!$cursor->eof()) {
    $row = $cursor->current();
    $comparison = $cursor->compareCurrentToNext(
        ['autoload', 'priority'],
        'GC',
        ['NOCASE', 'BINARY'],
        [],
        ['LAST', 'FIRST']
    );
    $boundaries[] = [
        'optionId' => $row['option_id'],
        'nextBoundary' => $comparison === null ? null : ($comparison <=> 0),
    ];
    $cursor->next();
}

echo json_encode([
    'scenario' => 'application copied wp_options VDBE affinity current-next boundaries',
    'boundaries' => $boundaries,
    'dependencyClosure' => 'no new support component needed; reuses VDBE affinity comparison and sorter cursor primitives',
], JSON_PRETTY_PRINT) . "\n";
