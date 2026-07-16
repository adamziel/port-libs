<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteSelectProjection;
use PortLibs\LibSqlite\SQLiteSelectResult;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$rows = [
    ['option_id' => 1, 'option_name' => 'siteurl', 'option_value' => 'https://example.test', 'autoload' => 'yes', 'score' => 10],
    ['option_id' => 2, 'option_name' => 'home', 'option_value' => null, 'autoload' => 'yes', 'score' => 0],
    ['option_id' => 3, 'option_name' => '_transient_api', 'option_value' => 'cached', 'autoload' => 'no', 'score' => '2 days'],
    ['option_id' => 4, 'option_name' => 'orphaned_plugin_flag', 'option_value' => 'unused', 'autoload' => null, 'score' => null],
];

$projected = SQLiteSelectProjection::project($rows, [
    ['type' => 'column', 'name' => 'option_id'],
    ['type' => 'column', 'name' => 'option_name'],
    ['type' => 'case', 'alias' => 'autoloadStatus', 'base' => ['type' => 'column', 'name' => 'autoload'], 'branches' => [
        ['when' => 'yes', 'then' => 'autoloaded'],
        ['when' => 'no', 'then' => 'manual'],
    ], 'else' => 'unknown'],
    ['type' => 'case', 'alias' => 'reviewBucket', 'branches' => [
        ['when' => ['type' => 'column', 'name' => 'score'], 'then' => ['type' => 'function', 'name' => 'printf', 'arguments' => ['priority:%s', ['type' => 'column', 'name' => 'score']]]],
        ['when' => ['type' => 'function', 'name' => 'like', 'arguments' => ['orphaned%', ['type' => 'column', 'name' => 'option_name']]], 'then' => 'orphan-review'],
    ], 'else' => 'normal'],
    ['type' => 'case', 'alias' => 'valueBucket', 'base' => ['type' => 'column', 'name' => 'option_value'], 'branches' => [
        ['when' => 'https://example.test', 'then' => 'url'],
        ['when' => 'cached', 'then' => 'cache'],
        ['when' => 'unused', 'then' => 'orphan'],
    ], 'else' => 'missing'],
]);

$ordered = SQLiteSelectResult::execute($projected, null, [
    ['column' => 'autoloadStatus'],
    ['column' => 'option_name'],
]);

echo json_encode([
    'applicationUse' => 'Preview copied wp_options rows projected through SQLite CASE expressions before import diagnostics and final ordering, without requiring ext/sqlite.',
    'projectedRows' => $projected,
    'orderedRows' => $ordered,
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
