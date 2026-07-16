<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\LibSqlite\SQLiteAttachedSchemaCatalog;
use PortLibs\LibSqlite\SQLitePragmaIntegrityRootpageForeignKeyCurrentSourceYield;
use PortLibs\LibSqlite\SQLiteSchemaRecord;

$record = static fn (string $name, int $root): SQLiteSchemaRecord => new SQLiteSchemaRecord(
    'table',
    $name,
    $name,
    $root,
    'CREATE TABLE ' . $name,
    $root,
);

$catalog = new SQLiteAttachedSchemaCatalog([
    $record('wp_terms', 2),
    $record('wp_term_taxonomy', 3),
    $record('wp_option_names', 4),
    $record('wp_options', 5),
]);

$schemas = [
    'main' => [
        'tables' => [
            'wp_terms' => [['rowid' => 1, 'term_id' => 1, 'slug' => 'news']],
            'wp_term_taxonomy' => [
                ['rowid' => 11, 'term_taxonomy_id' => 11, 'term_id' => 1],
                ['rowid' => 12, 'term_taxonomy_id' => 12, 'term_id' => 404],
            ],
            'wp_option_names' => [['rowid' => 1, 'name' => 'siteurl']],
            'wp_options' => [
                ['rowid' => 21, 'option_id' => 21, 'option_name' => 'siteurl'],
                ['rowid' => 22, 'option_id' => 22, 'option_name' => 'missing_option'],
            ],
        ],
        'foreignKeys' => [
            ['id' => 1, 'table' => 'wp_term_taxonomy', 'parent' => 'wp_terms', 'columns' => [['child' => 'term_id', 'parent' => 'term_id', 'affinity' => 'integer']]],
            ['id' => 2, 'table' => 'wp_options', 'parent' => 'wp_option_names', 'columns' => [['child' => 'option_name', 'parent' => 'name', 'affinity' => 'text', 'collation' => 'nocase']]],
        ],
    ],
];

$database = str_repeat("\0", 512);
$database = substr_replace($database, "SQLite format 3\0", 0, 16);
$database = substr_replace($database, pack('n', 512), 16, 2);
$database[18] = "\x01";
$database[19] = "\x01";
$database = substr_replace($database, pack('N', 1), 28, 4);
$database = substr_replace($database, pack('N', 5), 56, 4);

$page = SQLitePragmaIntegrityRootpageForeignKeyCurrentSourceYield::page(
    $database,
    $schemas,
    $catalog,
    'PRAGMA foreign_key_check',
    0,
    114,
    'PRAGMA quick_check',
);

$summary = [
    'scenario' => 'copied Application taxonomy/options FK diagnostics with schema rootpage context',
    'status' => $page['status'],
    'blocking' => $page['next_state']['blocking'],
    'rootpages' => array_map(
        static fn (array $row): array => [
            'table' => $row['table'],
            'rowid' => $row['rowid'],
            'child_rootpage' => $row['child_rootpage'],
            'parent_rootpage' => $row['parent_rootpage'],
            'message' => $row['message'],
        ],
        $page['rows'],
    ),
];

if (in_array('--self-test', $argv, true)) {
    assert($summary['status'] === 'blocked');
    assert($summary['blocking'] === ['foreign_key_check']);
    assert($summary['rootpages'][0]['child_rootpage'] === 3);
    assert($summary['rootpages'][0]['parent_rootpage'] === 2);
    assert($summary['rootpages'][1]['child_rootpage'] === 5);
    assert($summary['rootpages'][1]['parent_rootpage'] === 4);
    echo "application-pragma-integrity-rootpage-fk-current-source-next114 self-test passed\n";
    return;
}

echo json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
