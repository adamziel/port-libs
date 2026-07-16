<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\LibSqlite\SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext;
use PortLibs\LibSqlite\SQLiteSchemaRecord;

$record = static fn (string $type, string $name, string $table, ?int $root, ?string $sql, int $rowId): SQLiteSchemaRecord => new SQLiteSchemaRecord($type, $name, $table, $root, $sql, $rowId);

$next228Records = [
    $record('table', 'wp_fk_parent_228', 'wp_fk_parent_228', 2, 'CREATE TABLE wp_fk_parent_228(code TEXT COLLATE NOCASE NOT NULL)', 1),
    $record('index', 'wp_fk_parent_228_code_desc_unique', 'wp_fk_parent_228', 3, 'CREATE UNIQUE INDEX wp_fk_parent_228_code_desc_unique ON wp_fk_parent_228(code COLLATE NOCASE DESC)', 2),
    $record('table', 'wp_fk_child_228', 'wp_fk_child_228', 4, 'CREATE TABLE wp_fk_child_228(code TEXT NOT NULL, FOREIGN KEY(code) REFERENCES wp_fk_parent_228(code))', 3),
];

$next229Records = [
    $record('table', 'wp_fk_parent_229', 'wp_fk_parent_229', 5, 'CREATE TABLE wp_fk_parent_229(code TEXT NOT NULL, locale TEXT NOT NULL)', 4),
    $record('index', 'wp_fk_parent_229_code_locale_unique', 'wp_fk_parent_229', 6, 'CREATE UNIQUE INDEX wp_fk_parent_229_code_locale_unique ON wp_fk_parent_229(code, locale)', 5),
    $record('table', 'wp_fk_child_229', 'wp_fk_child_229', 7, 'CREATE TABLE wp_fk_child_229(code TEXT NOT NULL, FOREIGN KEY(code) REFERENCES wp_fk_parent_229(code))', 6),
];

$next230Records = [
    $record('table', 'wp_fk_parent_230', 'wp_fk_parent_230', 8, 'CREATE TABLE wp_fk_parent_230(id INTEGER PRIMARY KEY, title TEXT)', 7),
    $record('table', 'wp_fk_child_230', 'wp_fk_child_230', 9, 'CREATE TABLE wp_fk_child_230(parent_id INTEGER, FOREIGN KEY(parent_id) REFERENCES wp_fk_parent_230(rowid))', 8),
];

$next231Records = [
    $record('table', 'wp_fk_parent_231', 'wp_fk_parent_231', 10, 'CREATE TABLE wp_fk_parent_231(code TEXT NOT NULL)', 9),
    $record('index', 'wp_fk_parent_231_lower_code_unique', 'wp_fk_parent_231', 11, 'CREATE UNIQUE INDEX wp_fk_parent_231_lower_code_unique ON wp_fk_parent_231(lower(code))', 10),
    $record('table', 'wp_fk_child_231', 'wp_fk_child_231', 12, 'CREATE TABLE wp_fk_child_231(code TEXT NOT NULL, FOREIGN KEY(code) REFERENCES wp_fk_parent_231(code))', 11),
];

$next228Rows = SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext::parentKeySortOrderRows228($next228Records);
$next229Rows = SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext::parentKeyExactArityRows229($next229Records);
$next230Rows = SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext::pseudoRowidParentRows230($next230Records);
$next231Rows = SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext::parentExpressionUniqueRows231($next231Records);

$summary = [
    'scenario' => 'application-pragma-index-xinfo-foreignkey-current-source-next228-231',
    'applicationUse' => 'Bulk Application schema import checks can separate DESC-compatible parent UNIQUE indexes from wider-prefix, pseudo-rowid, and expression-index FK parent-key blockers.',
    'next228_status' => $next228Rows[0]['status'],
    'next228_desc_compatible' => $next228Rows[0]['sort_order_ignored_for_fk'],
    'next229_status' => $next229Rows[0]['status'],
    'next230_status' => $next230Rows[0]['status'],
    'next231_status' => $next231Rows[0]['status'],
];

if (($argv[1] ?? null) === '--self-test') {
    if (
        $summary['next228_status'] !== 'ok'
        || $summary['next228_desc_compatible'] !== true
        || $summary['next229_status'] !== 'wider_parent_unique_index'
        || $summary['next230_status'] !== 'pseudo_rowid_parent_key'
        || $summary['next231_status'] !== 'expression_unique_index'
    ) {
        fwrite(STDERR, "application-pragma-index-xinfo-foreignkey-current-source-next228-231 self-test failed\n");
        exit(1);
    }

    fwrite(STDOUT, "application-pragma-index-xinfo-foreignkey-current-source-next228-231 self-test passed\n");
    exit(0);
}

echo json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
