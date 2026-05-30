<?php

declare(strict_types=1);

spl_autoload_register(static function (string $class): void {
    $prefix = 'PortLibs\\LibSqlite\\';
    if (!str_starts_with($class, $prefix)) {
        return;
    }

    $path = __DIR__ . '/../src/' . substr($class, strlen($prefix)) . '.php';
    if (is_file($path)) {
        require_once $path;
    }
});

use PortLibs\LibSqlite\SQLiteAttachedSchemaCatalog;
use PortLibs\LibSqlite\SQLitePragmaIntegritySourceCursor;
use PortLibs\LibSqlite\SQLiteSchemaRecord;

$database = str_repeat("\0", 512);
$database = substr_replace($database, "SQLite format 3\0", 0, 16);
$database = substr_replace($database, pack('n', 512), 16, 2);
$database[18] = "\x01";
$database[19] = "\x01";
$database = substr_replace($database, pack('N', 1), 28, 4);
$database = substr_replace($database, pack('N', 1), 56, 4);

$record = static fn (string $name, int $root, int $rowid): SQLiteSchemaRecord => new SQLiteSchemaRecord(
    'table',
    $name,
    $name,
    $root,
    'CREATE TABLE ' . $name,
    $rowid,
);

$catalog = new SQLiteAttachedSchemaCatalog([
    $record('wp_options', 2, 1),
    $record('wp_option_names', 3, 2),
]);
$catalog->attach('wp.archive', '/tmp/wp.archive.sqlite', [
    $record('wp_options', 4, 1),
    $record('wp_option_names', 5, 2),
]);

$schemas = [
    'main' => [
        'tables' => [
            'wp_option_names' => [['rowid' => 1, 'name' => 'siteurl']],
            'wp_options' => [['rowid' => 'main-ok', 'option_name' => 'siteurl']],
        ],
        'foreignKeys' => [],
    ],
    'wp.archive' => [
        'tables' => [
            'wp_option_names' => [['rowid' => 'archive-parent', 'name' => 'legacy_siteurl']],
            'wp_options' => [
                ['rowid' => 'archive-ok', 'option_name' => 'legacy_siteurl'],
                ['rowid' => 'archive-missing-1', 'option_name' => 'missing_one'],
                ['rowid' => 'archive-missing-2', 'option_name' => 'missing_two'],
            ],
        ],
        'foreignKeys' => [
            ['id' => 134, 'table' => 'wp_options', 'parent' => 'wp_option_names', 'columns' => [
                ['child' => 'option_name', 'parent' => 'name', 'affinity' => 'text', 'collation' => 'nocase'],
            ]],
        ],
    ],
];

$page = SQLitePragmaIntegritySourceCursor::pageForForeignKeyPragma(
    $database,
    $schemas,
    'PRAGMA "wp.archive".foreign_key_check(wp_options)',
    0,
    1,
    'PRAGMA quick_check',
    null,
    $catalog,
);
$next = SQLitePragmaIntegritySourceCursor::pageForForeignKeyPragma(
    $database,
    $schemas,
    'PRAGMA "wp.archive".foreign_key_check(wp_options)',
    1,
    1,
    'PRAGMA quick_check',
    $page['next'],
    $catalog,
);

$summary = [
    'scenario' => 'application-pragma-integrity-foreignkey-cursor-current-source-next134',
    'applicationUse' => 'Resume copied multisite/archive wp_options foreign-key diagnostics against quoted attached schema names without losing current-source cursor identity.',
    'total' => $page['total'],
    'firstRowid' => $page['rows'][0]['rowid'],
    'nextRowid' => $next['rows'][0]['rowid'],
    'schema' => $page['rows'][0]['schema'],
    'sourceIdLength' => strlen($page['source_id']),
];

if (($argv[1] ?? null) === '--self-test') {
    if (
        $summary['total'] !== 2
        || $summary['firstRowid'] !== 'archive-missing-1'
        || $summary['nextRowid'] !== 'archive-missing-2'
        || $summary['schema'] !== 'wp.archive'
        || $summary['sourceIdLength'] !== 64
    ) {
        fwrite(STDERR, "application-pragma-integrity-foreignkey-cursor-current-source-next134 self-test failed\n");
        exit(1);
    }

    fwrite(STDOUT, "application-pragma-integrity-foreignkey-cursor-current-source-next134 self-test passed\n");
    exit(0);
}

echo json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
