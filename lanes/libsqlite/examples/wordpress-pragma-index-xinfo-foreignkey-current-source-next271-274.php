<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\LibSqlite\SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext;
use PortLibs\LibSqlite\SQLiteSchemaRecord;

$record = static fn (string $type, string $name, string $table, ?int $root, ?string $sql, int $rowId): SQLiteSchemaRecord => new SQLiteSchemaRecord($type, $name, $table, $root, $sql, $rowId);

$current = [
    $record('table', 'wp_terms', 'wp_terms', 2, 'CREATE TABLE wp_terms(term_id INTEGER PRIMARY KEY)', 1),
    $record('table', 'wp_termmeta_import', 'wp_termmeta_import', 3, 'CREATE TABLE wp_termmeta_import(term_id INTEGER, FOREIGN KEY(term_id) REFERENCES wp_terms(term_id) ON DELETE CASCADE)', 2),
];

$next = [
    ...$current,
    $record('index', 'wp_termmeta_import_term_id', 'wp_termmeta_import', 4, 'CREATE INDEX wp_termmeta_import_term_id ON wp_termmeta_import(term_id)', 3),
];

$page = SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext::page271(
    $current,
    $next,
    'PRAGMA index_xinfo(wp_termmeta_import_term_id)',
    'PRAGMA foreign_key_list(wp_termmeta_import)',
);

if (($argv[1] ?? '') === '--self-test') {
    if ($page['operation'] !== 'pragma-index-xinfo-foreignkey-current-source-next271' || $page['current']['foreign_key_child_action_lookup_after_current_next271']['repaired'] !== 1) {
        fwrite(STDERR, "wordpress-pragma-index-xinfo-foreignkey-current-source-next271-274 self-test failed\n");
        exit(1);
    }
    echo "wordpress-pragma-index-xinfo-foreignkey-current-source-next271-274 self-test passed\n";
}

return [
    'status' => 'wordpress-pragma-index-xinfo-foreignkey-current-source-next271-274',
    'operation' => $page['operation'],
    'afterCurrent' => $page['current']['foreign_key_child_action_lookup_after_current_next271'],
    'ready' => $page['delta']['foreign_key_child_action_lookup_after_current_ready_next271'],
    'wordpressUse' => 'Copied wp_termmeta imports can prove an ON DELETE CASCADE child lookup index was repaired after the current source using PRAGMA foreign_key_list plus PRAGMA index_xinfo.',
];
