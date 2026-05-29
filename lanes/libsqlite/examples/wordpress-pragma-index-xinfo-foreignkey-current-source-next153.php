<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/SQLiteSchemaRecord.php';
require_once __DIR__ . '/../src/SQLitePragmaRowCursor.php';
require_once __DIR__ . '/../src/SQLitePragmaSchemaCatalog.php';
require_once __DIR__ . '/../src/SQLiteAttachedSchemaCatalog.php';
require_once __DIR__ . '/../src/SQLiteAffinityComparison.php';
require_once __DIR__ . '/../src/SQLitePragmaForeignKeyCheck.php';
require_once __DIR__ . '/../src/SQLitePragmaForeignKeyIntegrity.php';
require_once __DIR__ . '/../src/SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext.php';

use PortLibs\LibSqlite\SQLiteAttachedSchemaCatalog;
use PortLibs\LibSqlite\SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext;
use PortLibs\LibSqlite\SQLiteSchemaRecord;

$record = static fn (string $type, string $name, string $table, int $root, ?string $sql, int $rowid): SQLiteSchemaRecord => new SQLiteSchemaRecord($type, $name, $table, $root, $sql, $rowid);
$catalog = new SQLiteAttachedSchemaCatalog([
    $record('table', 'wp_options', 'wp_options', 4, 'CREATE TABLE wp_options(option_id INTEGER PRIMARY KEY, option_name TEXT, option_value TEXT, autoload TEXT)', 1),
    $record('table', 'wp_option_names', 'wp_option_names', 5, 'CREATE TABLE wp_option_names(name TEXT PRIMARY KEY)', 2),
    $record('index', 'wp_options_name_autoload', 'wp_options', 6, 'CREATE UNIQUE INDEX wp_options_name_autoload ON wp_options(option_name COLLATE nocase DESC, autoload)', 3),
]);
$currentSchemas = [
    'main' => [
        'tables' => [
            'wp_option_names' => [['rowid' => 1, 'name' => 'siteurl']],
            'wp_options' => [
                ['rowid' => 1, 'option_id' => 1, 'option_name' => 'siteurl', 'option_value' => 'https://example.test', 'autoload' => 'yes'],
                ['rowid' => 'stale-widget', 'option_id' => 2, 'option_name' => 'missing_widget', 'option_value' => 'a:0:{}', 'autoload' => 'no'],
                ['rowid' => 'stale-cache', 'option_id' => 3, 'option_name' => 'missing_cache', 'option_value' => 'a:0:{}', 'autoload' => 'no'],
            ],
        ],
        'foreignKeys' => [
            ['id' => 153, 'table' => 'wp_options', 'parent' => 'wp_option_names', 'columns' => [
                ['child' => 'option_name', 'parent' => 'name', 'affinity' => 'text', 'collation' => 'nocase'],
            ]],
        ],
    ],
];
$nextSchemas = $currentSchemas;
$nextSchemas['main']['tables']['wp_options'] = [
    ['rowid' => 1, 'option_id' => 1, 'option_name' => 'siteurl', 'option_value' => 'https://example.test', 'autoload' => 'yes'],
];

$first = SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext::page153(
    $catalog,
    $catalog,
    'PRAGMA main.index_xinfo(wp_options_name_autoload)',
    $currentSchemas,
    $nextSchemas,
    'PRAGMA main.foreign_key_check(wp_options)',
    0,
    4,
);
$second = SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext::page153(
    $catalog,
    $catalog,
    'PRAGMA main.index_xinfo(wp_options_name_autoload)',
    $currentSchemas,
    $nextSchemas,
    'PRAGMA main.foreign_key_check(wp_options)',
    4,
    153,
    false,
    $first['next'],
);

if (($argv[1] ?? null) === '--self-test') {
    assert($first['next'] === ['source_id' => $first['source_id'], 'offset' => 4]);
    assert($second['status'] === 'ok');
    assert($second['current']['foreign_key'] === 2);
    assert($second['next_counts']['foreign_key'] === 0);
    assert($second['delta']['foreign_keys_cleared'] === true);
    echo "wordpress-pragma-index-xinfo-foreignkey-current-source-next153 self-test passed\n";
    return;
}

echo json_encode([
    'scenario' => 'copied wp_options PRAGMA index_xinfo plus foreign_key_check current-source next153',
    'wordpressUse' => 'Resume a copied wp_options import only after unique option-name index metadata remains stable and foreign-key violations are cleared in the next database image.',
    'source_id' => $first['source_id'],
    'current' => $second['current'],
    'next_counts' => $second['next_counts'],
    'delta' => $second['delta'],
    'next_state' => $second['next_state'],
    'first_page' => [
        'count' => $first['count'],
        'next' => $first['next'],
        'rows' => $first['rows'],
    ],
    'second_page' => [
        'count' => $second['count'],
        'complete' => $second['complete'],
        'rows' => $second['rows'],
    ],
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
