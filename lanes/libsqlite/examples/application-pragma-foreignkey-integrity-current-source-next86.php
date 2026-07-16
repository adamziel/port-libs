<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\LibSqlite\SQLiteAttachedSchemaCatalog;
use PortLibs\LibSqlite\SQLitePragmaForeignKeyIntegrity;
use PortLibs\LibSqlite\SQLitePragmaIntegrityCurrentNextYield;
use PortLibs\LibSqlite\SQLiteSchemaRecord;

$record = static fn (string $type, string $name, string $table, int $root): SQLiteSchemaRecord => new SQLiteSchemaRecord(
    $type,
    $name,
    $table,
    $root,
    'CREATE ' . strtoupper($type) . ' ' . $name,
    $root,
);

$catalog = new SQLiteAttachedSchemaCatalog(
    [
        $record('table', 'wp_options', 'wp_options', 2),
        $record('table', 'wp_option_names', 'wp_option_names', 3),
    ],
    [
        $record('table', 'wp_options', 'wp_options', 4),
        $record('table', 'wp_option_names', 'wp_option_names', 5),
    ],
);
$catalog->attach('archive', '/tmp/wp-archive.sqlite', [
    $record('table', 'wp_options', 'wp_options', 6),
    $record('table', 'wp_option_names', 'wp_option_names', 7),
]);

$schemas = [
    'main' => [
        'tables' => [
            'wp_option_names' => [['rowid' => 1, 'name' => 'siteurl']],
            'wp_options' => [
                ['rowid' => 101, 'option_name' => 'siteurl'],
                ['rowid' => 102, 'option_name' => 'main_missing'],
            ],
        ],
        'foreignKeys' => [
            ['id' => 1, 'table' => 'wp_options', 'parent' => 'wp_option_names', 'columns' => [['child' => 'option_name', 'parent' => 'name', 'collation' => 'nocase']]],
        ],
    ],
    'temp' => [
        'tables' => [
            'wp_option_names' => [['rowid' => 1, 'name' => 'siteurl']],
            'wp_options' => [
                ['rowid' => 'temp-1', 'option_name' => 'siteurl'],
                ['rowid' => 'temp-2', 'option_name' => 'temp_missing'],
            ],
        ],
        'foreignKeys' => [
            ['id' => 2, 'table' => 'wp_options', 'parent' => 'wp_option_names', 'columns' => [['child' => 'option_name', 'parent' => 'name', 'collation' => 'nocase']]],
        ],
    ],
    'archive' => [
        'tables' => [
            'wp_option_names' => [['rowid' => 1, 'name' => 'legacy_siteurl']],
            'wp_options' => [
                ['rowid' => 'archive-1', 'option_name' => 'legacy_siteurl'],
                ['rowid' => 'archive-2', 'option_name' => 'archive_missing'],
            ],
        ],
        'foreignKeys' => [
            ['id' => 3, 'table' => 'wp_options', 'parent' => 'wp_option_names', 'columns' => [['child' => 'option_name', 'parent' => 'name', 'collation' => 'nocase']]],
        ],
    ],
];

$database = str_repeat("\0", 512);
$database = substr_replace($database, "SQLite format 3\0", 0, 16);
$database = substr_replace($database, pack('n', 512), 16, 2);
$database[18] = "\x01";
$database[19] = "\x01";
$database = substr_replace($database, pack('N', 1), 28, 4);
$database = substr_replace($database, pack('N', 1), 56, 4);

$current = SQLitePragmaForeignKeyIntegrity::executeTableValued(
    "SELECT * FROM pragma_foreign_key_check('wp_options')",
    $schemas,
    $catalog,
);
$archivePage = SQLitePragmaIntegrityCurrentNextYield::pageForForeignKeyTableValuedPragma(
    $database,
    $schemas,
    "SELECT * FROM pragma_foreign_key_check('archive.wp_options')",
    0,
    86,
    'PRAGMA quick_check',
    $catalog,
);

$summary = [
    'scenario' => 'copied wp_options table-valued pragma foreign_key_check current source next86',
    'currentSource' => [
        'schema' => $current['schema'],
        'target_source' => $current['target_source'],
        'rowids' => array_column($current['rows'], 'rowid'),
    ],
    'archivePage' => [
        'count' => $archivePage['count'],
        'complete' => $archivePage['complete'],
        'messages' => array_column($archivePage['rows'], 'message'),
    ],
];

if (in_array('--self-test', $argv, true)) {
    assert($summary['currentSource']['schema'] === 'temp');
    assert($summary['currentSource']['target_source'] === 'catalog-current');
    assert($summary['currentSource']['rowids'] === ['temp-2']);
    assert($summary['archivePage']['messages'] === ['foreign key mismatch in archive.wp_options rowid archive-2 references wp_option_names fkid 3']);
    echo "application-pragma-foreignkey-integrity-current-source-next86 self-test passed\n";
    return;
}

echo json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
