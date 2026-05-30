<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteSelectProjection;
use PortLibs\LibSqlite\SQLiteSelectResult;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$joinedRows = [
    [
        'wp_options.option_id' => 1,
        'wp_options.option_name' => 'siteurl',
        'wp_options.option_value' => 'https://example.test',
        'wp_options.autoload' => 'yes',
        'meta.option_id' => 1,
        'meta.visibility' => 'public',
        'meta.priority' => 10,
    ],
    [
        'wp_options.option_id' => 2,
        'wp_options.option_name' => 'home',
        'wp_options.option_value' => null,
        'wp_options.autoload' => 'yes',
        'meta.option_id' => 2,
        'meta.visibility' => 'public',
        'meta.priority' => 20,
    ],
    [
        'wp_options.option_id' => 3,
        'wp_options.option_name' => '_transient_api',
        'wp_options.option_value' => 'cached',
        'wp_options.autoload' => 'no',
        'meta.option_id' => 3,
        'meta.visibility' => 'private',
        'meta.priority' => 30,
    ],
];

$projected = SQLiteSelectProjection::project($joinedRows, [
    ['type' => 'wildcard', 'prefix' => 'wp_options'],
    ['type' => 'function', 'name' => 'lower', 'alias' => 'normalized_name', 'arguments' => [
        ['type' => 'column', 'name' => 'wp_options.option_name'],
    ]],
    ['type' => 'case', 'alias' => 'visibility_bucket', 'base' => ['type' => 'column', 'name' => 'meta.visibility'], 'branches' => [
        ['when' => 'public', 'then' => 'visible'],
        ['when' => 'private', 'then' => 'hidden'],
    ], 'else' => 'unknown'],
]);

$ordered = SQLiteSelectResult::execute($projected, null, [
    ['column' => 'visibility_bucket'],
    ['column' => 'option_name', 'direction' => 'DESC'],
]);

echo json_encode([
    'applicationUse' => 'Preview copied wp_options rows projected through SQLite table-star wildcard expansion after metadata joins, without requiring ext/sqlite.',
    'projectedRows' => $projected,
    'orderedRows' => $ordered,
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
