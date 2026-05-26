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
                ['operator' => '=', 'left' => ['column' => 'autoload'], 'right' => 'yes'],
                ['operator' => 'BETWEEN', 'left' => ['column' => 'bytes'], 'lower' => 8, 'upper' => 24],
                ['operator' => 'NOT LIKE', 'left' => ['column' => 'option_name'], 'right' => '!_%', 'escape' => '!'],
            ],
        ],
        ['operator' => 'GLOB', 'left' => ['column' => 'option_name'], 'right' => '_site_transient_*'],
    ],
];

$filtered = SQLiteSelectPredicate::filter($options, $predicate);
$ordered = SQLiteSelectResult::execute($filtered, null, [
    ['column' => 'autoload', 'direction' => 'DESC'],
    ['column' => 'bytes', 'direction' => 'DESC'],
]);

echo json_encode([
    'wordpressUse' => 'Preview copied wp_options rows filtered through SQLite WHERE residual predicates before result ordering, without requiring ext/sqlite.',
    'filteredOptionNames' => array_column($filtered, 'option_name'),
    'orderedRows' => $ordered,
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
