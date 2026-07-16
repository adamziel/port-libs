<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteSelectProjection;
use PortLibs\LibSqlite\SQLiteSelectResult;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$rows = [
    ['option_id' => 1, 'option_name' => 'SiteURL', 'option_value' => 'https://example.test', 'autoload' => 'yes'],
    ['option_id' => 2, 'option_name' => '_Transient_API', 'option_value' => null, 'autoload' => 'no'],
    ['option_id' => 3, 'option_name' => 'emoji_💡', 'option_value' => 'enabled', 'autoload' => 'yes'],
];

$projected = SQLiteSelectProjection::project($rows, [
    ['type' => 'column', 'name' => 'option_id'],
    ['type' => 'function', 'name' => 'lower', 'alias' => 'normalizedName', 'arguments' => [
        ['type' => 'column', 'name' => 'option_name'],
    ]],
    ['type' => 'function', 'name' => 'coalesce', 'alias' => 'effectiveValue', 'arguments' => [
        ['type' => 'column', 'name' => 'option_value'],
        'default',
    ]],
    ['type' => 'function', 'name' => 'printf', 'alias' => 'diagnostic', 'arguments' => [
        'row=%03d name=%Q autoload=%s',
        ['type' => 'column', 'name' => 'option_id'],
        ['type' => 'column', 'name' => 'option_name'],
        ['type' => 'column', 'name' => 'autoload'],
    ]],
    ['type' => 'function', 'name' => 'iif', 'alias' => 'autoloadLabel', 'arguments' => [
        ['type' => 'function', 'name' => 'like', 'arguments' => ['y%', ['type' => 'column', 'name' => 'autoload']]],
        'autoloaded',
        'manual',
    ]],
]);

$ordered = SQLiteSelectResult::execute($projected, null, [
    ['column' => 'autoloadLabel', 'direction' => 'ASC'],
    ['column' => 'normalizedName', 'direction' => 'DESC'],
]);

echo json_encode([
    'applicationUse' => 'Preview SELECT row production for copied wp_options imports where scalar expressions are evaluated as projected result columns before ORDER BY, without requiring ext/sqlite.',
    'projectedRows' => $projected,
    'orderedRows' => $ordered,
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
