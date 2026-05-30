<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\LibSqlite\SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext;
use PortLibs\LibSqlite\SQLiteSchemaRecord;

$record = static fn (string $type, string $name, string $table, ?int $root, ?string $sql, int $rowId): SQLiteSchemaRecord => new SQLiteSchemaRecord($type, $name, $table, $root, $sql, $rowId);

$current = [
    $record('table', 'wp_import_parent', 'wp_import_parent', 2, 'CREATE TABLE wp_import_parent(slug TEXT NOT NULL, taxonomy TEXT NOT NULL, term_id INTEGER PRIMARY KEY, UNIQUE(slug, taxonomy))', 1),
    $record('index', 'sqlite_autoindex_wp_import_parent_1', 'wp_import_parent', 3, null, 2),
    $record('table', 'wp_import_meta', 'wp_import_meta', 4, 'CREATE TABLE wp_import_meta(meta_id INTEGER PRIMARY KEY, parent_row INTEGER NOT NULL, FOREIGN KEY(parent_row) REFERENCES wp_import_parent(rowid))', 3),
];

$next = [
    $current[0],
    $current[1],
    $record('table', 'wp_import_meta', 'wp_import_meta', 4, 'CREATE TABLE wp_import_meta(meta_id INTEGER PRIMARY KEY, parent_row INTEGER NOT NULL, FOREIGN KEY(parent_row) REFERENCES wp_import_parent(term_id))', 3),
];

$page = SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext::page242(
    $current,
    $next,
    'PRAGMA main.index_xinfo(sqlite_autoindex_wp_import_parent_1)',
    'PRAGMA main.foreign_key_list(wp_import_meta)',
    0,
    220,
);

$summary = [
    'scenario' => 'application-pragma-index-xinfo-foreignkey-current-source-next242',
    'applicationUse' => 'Copied Application import schemas can distinguish PRAGMA index_xinfo key=0 rowid auxiliary rows from invalid explicit FOREIGN KEY parent rowid aliases.',
    'current_rowid_alias_rows' => $page['current']['foreign_key_parent_rowid_alias']['rows'],
    'current_rowid_alias_blockers' => $page['current']['foreign_key_parent_rowid_alias']['rowid_alias_parent_key'],
    'current_rowid_auxiliary_indexes' => $page['current']['foreign_key_parent_rowid_alias']['rowid_auxiliary_indexes'],
    'next_rowid_alias_rows' => $page['next_counts']['foreign_key_parent_rowid_alias']['rows'],
    'rowid_alias_repaired' => $page['delta']['foreign_key_parent_rowid_alias_repaired'],
    'source' => $page['current_source']['foreign_key_parent_rowid_alias_source'],
    'pragmas' => [
        'PRAGMA main.index_xinfo(sqlite_autoindex_wp_import_parent_1)',
        'PRAGMA main.foreign_key_list(wp_import_meta)',
    ],
];

if (PHP_SAPI === 'cli' && realpath($_SERVER['SCRIPT_FILENAME'] ?? '') === __FILE__) {
    if (
        $summary['current_rowid_alias_rows'] !== 1
        || $summary['current_rowid_alias_blockers'] !== 1
        || $summary['current_rowid_auxiliary_indexes'] !== 1
        || $summary['next_rowid_alias_rows'] !== 0
        || $summary['rowid_alias_repaired'] !== true
    ) {
        fwrite(STDERR, "application-pragma-index-xinfo-foreignkey-current-source-next242 self-test failed\n");
        exit(1);
    }

    fwrite(STDOUT, json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n");
    fwrite(STDOUT, "application-pragma-index-xinfo-foreignkey-current-source-next242 self-test passed\n");
}

return $summary;
