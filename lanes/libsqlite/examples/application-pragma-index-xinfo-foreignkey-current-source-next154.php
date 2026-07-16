<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/SQLiteSchemaRecord.php';
require_once __DIR__ . '/../src/SQLitePragmaSchemaCatalog.php';
require_once __DIR__ . '/../src/SQLiteAttachedSchemaCatalog.php';
require_once __DIR__ . '/../src/SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext.php';

use PortLibs\LibSqlite\SQLiteAttachedSchemaCatalog;
use PortLibs\LibSqlite\SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext;
use PortLibs\LibSqlite\SQLiteSchemaRecord;

$record = static fn (string $type, string $name, string $table, ?int $root, ?string $sql = null, int $rowid = 1): SQLiteSchemaRecord => new SQLiteSchemaRecord(
    $type,
    $name,
    $table,
    $root,
    $sql,
    $rowid,
);

$catalog = static function (bool $stable = true) use ($record): SQLiteAttachedSchemaCatalog {
    return new SQLiteAttachedSchemaCatalog([
        $record('table', 'wp_option_names', 'wp_option_names', 2, 'CREATE TABLE wp_option_names(name TEXT PRIMARY KEY)', 1),
        $record(
            'table',
            'wp_options',
            'wp_options',
            3,
            $stable
                ? 'CREATE TABLE wp_options(option_id INTEGER PRIMARY KEY, option_name TEXT COLLATE NOCASE, autoload TEXT, FOREIGN KEY(option_name) REFERENCES wp_option_names(name) ON UPDATE CASCADE ON DELETE RESTRICT)'
                : 'CREATE TABLE wp_options(option_id INTEGER PRIMARY KEY, option_name TEXT COLLATE NOCASE, autoload TEXT, FOREIGN KEY(option_name) REFERENCES wp_option_names(name) ON UPDATE NO ACTION ON DELETE SET NULL)',
            2,
        ),
        $record(
            'index',
            'wp_options_name_expr154',
            'wp_options',
            4,
            $stable
                ? 'CREATE INDEX wp_options_name_expr154 ON wp_options(lower(option_name) COLLATE NOCASE DESC, autoload)'
                : 'CREATE INDEX wp_options_name_expr154 ON wp_options(lower(option_name) COLLATE BINARY, autoload DESC)',
            3,
        ),
    ]);
};

$result = SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext::currentNextPage154(
    $catalog(),
    $catalog(false),
    'PRAGMA main.index_xinfo(wp_options_name_expr154)',
    'PRAGMA main.foreign_key_list(wp_options)',
    0,
    154,
);

if (($argv[1] ?? null) === '--self-test') {
    if (
        $result['status'] !== 'blocked'
        || $result['current']['index_xinfo'] !== 3
        || $result['current']['foreign_key_list'] !== 1
        || $result['delta']['index_changed'] !== true
        || $result['delta']['foreign_key_changed'] !== true
    ) {
        fwrite(STDERR, "application-pragma-index-xinfo-foreignkey-current-source-next154 self-test failed\n");
        exit(1);
    }

    fwrite(STDOUT, "application-pragma-index-xinfo-foreignkey-current-source-next154 self-test passed\n");
    exit(0);
}

echo json_encode([
    'scenario' => 'application-pragma-index-xinfo-foreignkey-current-source-next154',
    'applicationUse' => 'Detect copied wp_options index metadata and declared foreign-key action drift before resuming import diagnostics from a stale schema catalog.',
    'status' => $result['status'],
    'current' => $result['current'],
    'next' => $result['next_counts'],
    'delta' => $result['delta'],
    'blocking' => $result['next_state']['blocking'],
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
