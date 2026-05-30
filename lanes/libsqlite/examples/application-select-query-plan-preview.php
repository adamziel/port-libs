<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteSelectQuery;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$options = [
    ['wp_options.option_id' => 1, 'wp_options.option_name' => 'siteurl', 'wp_options.option_value' => 'https://example.test', 'wp_options.autoload' => 'yes'],
    ['wp_options.option_id' => 2, 'wp_options.option_name' => 'home', 'wp_options.option_value' => 'https://example.test', 'wp_options.autoload' => 'yes'],
    ['wp_options.option_id' => 3, 'wp_options.option_name' => 'blogname', 'wp_options.option_value' => 'Example Site', 'wp_options.autoload' => 'yes'],
    ['wp_options.option_id' => 4, 'wp_options.option_name' => '_transient_feed', 'wp_options.option_value' => 'cached', 'wp_options.autoload' => 'no'],
    ['wp_options.option_id' => 5, 'wp_options.option_name' => 'orphaned', 'wp_options.option_value' => null, 'wp_options.autoload' => null],
];

$metadata = [
    ['meta.option_id' => 1, 'meta.visibility' => 'public', 'meta.priority' => 20],
    ['meta.option_id' => 2, 'meta.visibility' => 'public', 'meta.priority' => 10],
    ['meta.option_id' => 3, 'meta.visibility' => 'private', 'meta.priority' => 30],
    ['meta.option_id' => 4, 'meta.visibility' => 'cache', 'meta.priority' => 40],
];

$rows = SQLiteSelectQuery::execute([
    'from' => $options,
    'joins' => [[
        'type' => 'LEFT',
        'rows' => $metadata,
        'predicate' => static fn (array $option, array $meta): bool => $option['wp_options.option_id'] === $meta['meta.option_id'],
        'rightColumns' => ['meta.option_id', 'meta.visibility', 'meta.priority'],
    ]],
    'where' => [
        'operator' => 'AND',
        'terms' => [
            ['operator' => 'NOT LIKE', 'left' => ['column' => 'wp_options.option_name'], 'right' => '!_%', 'escape' => '!'],
            ['operator' => 'IN', 'left' => ['column' => 'wp_options.autoload'], 'values' => ['yes']],
        ],
    ],
    'select' => [
        ['type' => 'wildcard', 'prefix' => 'wp_options'],
        ['type' => 'function', 'name' => 'lower', 'alias' => 'normalized_name', 'arguments' => [
            ['type' => 'column', 'name' => 'wp_options.option_name'],
        ]],
        ['type' => 'case', 'alias' => 'visibility_bucket', 'base' => ['type' => 'column', 'name' => 'meta.visibility'], 'branches' => [
            ['when' => 'public', 'then' => 'visible'],
            ['when' => 'private', 'then' => 'hidden'],
        ], 'else' => 'other'],
        ['type' => 'column', 'name' => 'meta.priority', 'alias' => 'priority'],
    ],
    'distinct' => ['option_value', 'visibility_bucket'],
    'orderBy' => [
        ['column' => 'visibility_bucket', 'direction' => 'DESC'],
        ['column' => 'priority'],
        ['column' => 'option_name'],
    ],
    'limit' => 3,
]);

echo json_encode([
    'applicationUse' => 'Preview copied wp_options rows through one bounded SQLite SELECT plan with LEFT JOIN, WHERE residual predicates, table-star projection, DISTINCT, ORDER BY, and LIMIT before import diagnostics, without requiring ext/sqlite.',
    'selectedOptionNames' => array_column($rows, 'option_name'),
    'rows' => $rows,
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
