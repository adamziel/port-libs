<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteSelectQuery;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$options = [
    ['option_id' => 1, 'option_name' => 'siteurl', 'autoload' => 'yes', 'bytes' => 24],
    ['option_id' => 2, 'option_name' => 'home', 'autoload' => 'yes', 'bytes' => 24],
    ['option_id' => 3, 'option_name' => 'blogname', 'autoload' => 'yes', 'bytes' => 9],
    ['option_id' => 4, 'option_name' => '_transient_feed', 'autoload' => 'no', 'bytes' => 12],
    ['option_id' => 5, 'option_name' => '_site_transient_update_plugins', 'autoload' => 'no', 'bytes' => 110],
    ['option_id' => 6, 'option_name' => 'legacy_null', 'autoload' => 'no', 'bytes' => null],
    ['option_id' => 7, 'option_name' => 'orphaned', 'autoload' => null, 'bytes' => 3],
    ['option_id' => 8, 'option_name' => 'orphaned_again', 'autoload' => null, 'bytes' => 7],
];

$rows = SQLiteSelectQuery::execute([
    'from' => $options,
    'groupBy' => [
        'column' => 'autoload',
        'valueColumn' => 'bytes',
        'having' => [
            'operator' => '>=',
            'left' => ['column' => 'countAll'],
            'right' => 2,
        ],
        'orderBy' => [
            ['column' => 'sum', 'direction' => 'DESC'],
            ['column' => 'group'],
        ],
    ],
    'select' => [
        ['type' => 'column', 'name' => 'group', 'alias' => 'autoload'],
        ['type' => 'column', 'name' => 'countAll', 'alias' => 'rows'],
        ['type' => 'column', 'name' => 'countValue', 'alias' => 'nonnullByteRows'],
        ['type' => 'column', 'name' => 'sum', 'alias' => 'byteSum'],
        ['type' => 'function', 'name' => 'printf', 'alias' => 'label', 'arguments' => [
            'autoload:%s rows:%d',
            ['type' => 'function', 'name' => 'coalesce', 'arguments' => [
                ['type' => 'column', 'name' => 'group'],
                'NULL',
            ]],
            ['type' => 'column', 'name' => 'countAll'],
        ]],
    ],
    'orderBy' => [['column' => 'byteSum', 'direction' => 'DESC']],
]);

echo json_encode([
    'wordpressUse' => 'Preview copied wp_options rows through bounded SQLite SELECT GROUP BY/HAVING aggregate dispatch with projected summary columns before import diagnostics, without requiring ext/sqlite.',
    'selectedAutoloadBuckets' => array_column($rows, 'autoload'),
    'rows' => $rows,
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
