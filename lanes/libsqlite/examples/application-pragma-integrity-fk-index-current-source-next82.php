<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\LibSqlite\SQLiteAttachedSchemaCatalog;
use PortLibs\LibSqlite\SQLitePragmaIntegrityCurrentNextYield;
use PortLibs\LibSqlite\SQLiteSchemaRecord;

$record = static fn (string $type, string $name, string $table, ?int $root, ?string $sql = null, int $rowid = 1): SQLiteSchemaRecord => new SQLiteSchemaRecord(
    $type,
    $name,
    $table,
    $root,
    $sql,
    $rowid,
);

$catalog = new SQLiteAttachedSchemaCatalog(
    [
        $record('table', 'wp_posts', 'wp_posts', 2, 'CREATE TABLE wp_posts(ID INTEGER PRIMARY KEY)', 1),
        $record('table', 'wp_postmeta', 'wp_postmeta', 3, 'CREATE TABLE wp_postmeta(meta_id INTEGER PRIMARY KEY, post_id INTEGER)', 2),
    ],
    [
        $record('table', 'wp_options', 'wp_options', 4, 'CREATE TABLE wp_options(option_id INTEGER PRIMARY KEY, option_name TEXT)', 1),
        $record('table', 'wp_option_names', 'wp_option_names', 5, 'CREATE TABLE wp_option_names(name TEXT PRIMARY KEY)', 2),
    ],
);
$catalog->attach('archive', '/tmp/wp-archive.sqlite', [
    $record('table', 'wp_options', 'wp_options', 6, 'CREATE TABLE wp_options(option_id INTEGER PRIMARY KEY, option_name TEXT)', 1),
    $record('table', 'wp_option_names', 'wp_option_names', 7, 'CREATE TABLE wp_option_names(name TEXT PRIMARY KEY)', 2),
]);

$schemas = [
    'temp' => [
        'tables' => [
            'wp_option_names' => [['rowid' => 1, 'name' => 'siteurl']],
            'wp_options' => [
                ['rowid' => 'temp-1', 'option_id' => 1, 'option_name' => 'siteurl'],
                ['rowid' => 'temp-2', 'option_id' => 2, 'option_name' => 'missing_temp'],
            ],
        ],
        'foreignKeys' => [
            ['id' => 7, 'table' => 'wp_options', 'parent' => 'wp_option_names', 'columns' => [['child' => 'option_name', 'parent' => 'name', 'affinity' => 'text', 'collation' => 'nocase']]],
        ],
    ],
    'archive' => [
        'tables' => [
            'wp_option_names' => [['rowid' => 1, 'name' => 'legacy_siteurl']],
            'wp_options' => [
                ['rowid' => 'archive-1', 'option_id' => 1, 'option_name' => 'legacy_siteurl'],
                ['rowid' => 'archive-2', 'option_id' => 2, 'option_name' => 'missing_archive'],
            ],
        ],
        'foreignKeys' => [
            ['id' => 9, 'table' => 'wp_options', 'parent' => 'wp_option_names', 'columns' => [['child' => 'option_name', 'parent' => 'name', 'affinity' => 'text', 'collation' => 'nocase']]],
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

$currentTemp = SQLitePragmaIntegrityCurrentNextYield::pageForForeignKeyPragma(
    $database,
    $schemas,
    'PRAGMA foreign_key_check(wp_options)',
    0,
    82,
    'PRAGMA quick_check',
    $catalog,
);
$qualifiedArchive = SQLitePragmaIntegrityCurrentNextYield::pageForForeignKeyPragma(
    $database,
    $schemas,
    'PRAGMA foreign_key_check(archive.wp_options)',
    0,
    82,
    'PRAGMA quick_check',
    $catalog,
);

if (($argv[1] ?? null) === '--self-test') {
    if (
        $currentTemp['rows'][0]['schema'] !== 'temp'
        || $currentTemp['rows'][0]['rowid'] !== 'temp-2'
        || $qualifiedArchive['rows'][0]['schema'] !== 'archive'
        || $qualifiedArchive['rows'][0]['rowid'] !== 'archive-2'
        || $qualifiedArchive['limit'] !== 82
    ) {
        fwrite(STDERR, "application-pragma-integrity-fk-index-current-source-next82 self-test failed\n");
        exit(1);
    }

    fwrite(STDOUT, "application-pragma-integrity-fk-index-current-source-next82 self-test passed\n");
    exit(0);
}

echo json_encode([
    'scenario' => 'application foreign_key_check current-source schema resolution',
    'unqualified_current_source' => [
        'schema' => $currentTemp['rows'][0]['schema'],
        'rowid' => $currentTemp['rows'][0]['rowid'],
        'message' => $currentTemp['rows'][0]['message'],
    ],
    'qualified_archive_source' => [
        'schema' => $qualifiedArchive['rows'][0]['schema'],
        'rowid' => $qualifiedArchive['rows'][0]['rowid'],
        'message' => $qualifiedArchive['rows'][0]['message'],
    ],
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
