<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteSelectPredicate;
use PortLibs\LibSqlite\SQLiteSelectResult;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$options = [
    ['option_id' => 1, 'option_name' => 'siteurl', 'autoload' => 'yes', 'bytes' => 24],
    ['option_id' => 2, 'option_name' => 'home', 'autoload' => 'yes', 'bytes' => 18],
    ['option_id' => 3, 'option_name' => 'blogname', 'autoload' => 'yes', 'bytes' => 8],
    ['option_id' => 4, 'option_name' => '_transient_feed', 'autoload' => 'no', 'bytes' => 42],
    ['option_id' => 5, 'option_name' => '_site_transient_update_plugins', 'autoload' => 'no', 'bytes' => 110],
    ['option_id' => 6, 'option_name' => 'orphaned', 'autoload' => null, 'bytes' => null],
];

$predicate = [
    'operator' => 'OR',
    'terms' => [
        [
            'operator' => 'AND',
            'terms' => [
                [
                    'operator' => '=',
                    'left' => ['type' => 'function', 'name' => 'upper', 'arguments' => [['type' => 'column', 'name' => 'autoload']]],
                    'right' => 'YES',
                ],
                [
                    'operator' => 'BETWEEN',
                    'left' => ['type' => 'function', 'name' => 'length', 'arguments' => [['type' => 'column', 'name' => 'option_name']]],
                    'lower' => 4,
                    'upper' => 8,
                ],
                [
                    'operator' => 'NOT LIKE',
                    'left' => ['type' => 'function', 'name' => 'lower', 'arguments' => [['type' => 'column', 'name' => 'option_name']]],
                    'right' => '!_%',
                    'escape' => ['type' => 'literal', 'value' => '!'],
                ],
            ],
        ],
        [
            'operator' => 'GLOB',
            'left' => ['type' => 'function', 'name' => 'replace', 'arguments' => [['type' => 'column', 'name' => 'option_name'], '_', '-']],
            'right' => '-site-transient-*',
        ],
    ],
];

$filtered = SQLiteSelectPredicate::filter($options, $predicate);
$ordered = SQLiteSelectResult::execute($filtered, null, [
    ['column' => 'autoload', 'direction' => 'DESC'],
    ['column' => 'bytes', 'direction' => 'DESC'],
]);

echo json_encode([
    'applicationUse' => 'Preview copied wp_options rows filtered through SQLite WHERE residual scalar expressions before result ordering, without requiring ext/sqlite.',
    'filteredOptionNames' => array_column($filtered, 'option_name'),
    'orderedRows' => $ordered,
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
