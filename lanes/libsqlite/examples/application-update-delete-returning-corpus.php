<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/SQLiteUpdateDeleteLimitPlan.php';
require_once __DIR__ . '/../src/SQLiteSelectResult.php';

use PortLibs\LibSqlite\SQLiteUpdateDeleteLimitPlan;

$options = [
    ['option_id' => 1, 'option_name' => 'siteurl', 'autoload' => 'yes', 'bytes' => 24, 'option_value' => 'https://example.test'],
    ['option_id' => 2, 'option_name' => 'home', 'autoload' => 'yes', 'bytes' => 24, 'option_value' => 'https://example.test'],
    ['option_id' => 3, 'option_name' => '_transient_feed', 'autoload' => 'no', 'bytes' => 12, 'option_value' => 'feed'],
    ['option_id' => 4, 'option_name' => '_transient_big', 'autoload' => 'no', 'bytes' => 110, 'option_value' => str_repeat('x', 8)],
    ['option_id' => 5, 'option_name' => '_transient_small', 'autoload' => 'no', 'bytes' => 7, 'option_value' => 'tiny'],
];

$deleted = SQLiteUpdateDeleteLimitPlan::delete(
    $options,
    static fn (array $row): bool => $row['autoload'] === 'no',
    [['column' => 'bytes', 'direction' => 'DESC']],
    limit: 2,
    offset: 1,
    rowIdColumn: 'option_id',
);

$updated = SQLiteUpdateDeleteLimitPlan::update(
    $options,
    static fn (array $row): bool => $row['autoload'] === 'yes',
    [
        'autoload' => 'no',
        'returning_note' => static fn (array $row): string => $row['option_name'] . ':copied',
    ],
    [['column' => 'option_name']],
    limit: 1,
    rowIdColumn: 'option_id',
);

echo json_encode([
    'applicationUse' => 'Preview copied wp_options UPDATE/DELETE RETURNING rows after bounded mutation selection, preserving old DELETE values, new UPDATE assignment values, aliases, and computed RETURNING columns without requiring ext/sqlite.',
    'deleteReturning' => $deleted->returningRows([
        'deleted_id' => 'option_id',
        'deleted_name' => 'option_name',
        'bytes',
    ]),
    'deleteRemainingIds' => array_column($deleted->resultRows, 'option_id'),
    'updateReturning' => $updated->returningRows([
        'updated_id' => 'option_id',
        'name' => 'option_name',
        'autoload',
        'note' => 'returning_note',
    ]),
    'updateResultAutoload' => array_column($updated->resultRows, 'autoload'),
], JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR) . PHP_EOL;
