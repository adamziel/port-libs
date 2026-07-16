<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\LibSqlite\SQLiteForeignKeySetDefaultRecursivePlan;

$tables = [
    'categories' => [
        ['id' => 0, 'name' => 'Uncategorized'],
        ['id' => 1, 'name' => 'Plugin imports'],
    ],
    'posts' => [
        ['id' => 10, 'category_id' => 1, 'title' => 'Imported plugin settings'],
        ['id' => 11, 'category_id' => 1, 'title' => 'Imported plugin cleanup'],
    ],
    'postmeta' => [
        ['id' => 100, 'post_id' => 10, 'meta_key' => '_source'],
        ['id' => 101, 'post_id' => 11, 'meta_key' => '_source'],
    ],
];

$foreignKeys = [
    [
        'parent_table' => 'categories',
        'parent_key' => 'id',
        'child_table' => 'posts',
        'child_row_key' => 'id',
        'child_key' => 'category_id',
        'on_delete' => 'SET DEFAULT',
        'default' => 0,
    ],
    [
        'parent_table' => 'posts',
        'parent_key' => 'id',
        'child_table' => 'postmeta',
        'child_row_key' => 'id',
        'child_key' => 'post_id',
        'on_delete' => 'SET DEFAULT',
        'default' => 0,
    ],
];

$result = SQLiteForeignKeySetDefaultRecursivePlan::apply(
    $tables,
    $foreignKeys,
    [['table' => 'categories', 'key' => 1]],
);

if (($argv[1] ?? '') === '--self-test') {
    assert(array_column($result['tables']['posts'], 'category_id') === [0, 0]);
    assert($result['violations'] === []);
    assert($result['changes'] === 3);
    echo "application-fk-set-default-recursive self-test passed\n";
    exit(0);
}

echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
