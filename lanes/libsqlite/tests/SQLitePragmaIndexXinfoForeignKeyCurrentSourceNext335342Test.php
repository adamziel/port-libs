<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext;
use PortLibs\LibSqlite\SQLiteSchemaRecord;

$record = static fn (string $type, string $name, string $table, ?int $root, ?string $sql, int $rowId): SQLiteSchemaRecord => new SQLiteSchemaRecord($type, $name, $table, $root, $sql, $rowId);

$tests = [];

$cases = [
    335 => ['on_update', 'SET NULL', 'update_set_null_partial_child_lookup_index', 'partial', ''],
    336 => ['on_delete', 'SET NULL', 'delete_set_null_partial_child_lookup_index', 'partial', ''],
    337 => ['on_update', 'SET NULL', 'update_set_null_expression_child_lookup_index', 'expression', ''],
    338 => ['on_delete', 'SET NULL', 'delete_set_null_expression_child_lookup_index', 'expression', ''],
    339 => ['on_update', 'SET DEFAULT', 'update_set_default_partial_child_lookup_index', 'partial', ' DEFAULT 0'],
    340 => ['on_delete', 'SET DEFAULT', 'delete_set_default_partial_child_lookup_index', 'partial', ' DEFAULT 0'],
    341 => ['on_update', 'SET DEFAULT', 'update_set_default_expression_child_lookup_index', 'expression', ' DEFAULT 0'],
    342 => ['on_delete', 'SET DEFAULT', 'delete_set_default_expression_child_lookup_index', 'expression', ' DEFAULT 0'],
];

foreach ($cases as $slice => [$actionColumn, $action, $status, $indexKind, $columnDefault]) {
    $tests["pragma index xinfo foreignkey next{$slice} reports {$status}"] = static function (TestRunner $t) use ($record, $slice, $actionColumn, $action, $status, $indexKind, $columnDefault): void {
        $suffix = (string) $slice;
        $clause = $actionColumn === 'on_update' ? "ON UPDATE {$action}" : "ON DELETE {$action}";
        $indexName = "child{$suffix}_parent_id_" . ($indexKind === 'partial' ? 'partial' : 'expr');
        $indexSql = $indexKind === 'partial'
            ? "CREATE INDEX {$indexName} ON child{$suffix}(parent_id) WHERE parent_id IS NOT NULL"
            : "CREATE INDEX {$indexName} ON child{$suffix}(parent_id + 0)";
        $records = [
            $record('table', "parent{$suffix}", "parent{$suffix}", 2, "CREATE TABLE parent{$suffix}(id INTEGER PRIMARY KEY)", 1),
            $record('table', "child{$suffix}", "child{$suffix}", 3, "CREATE TABLE child{$suffix}(parent_id INTEGER{$columnDefault} REFERENCES parent{$suffix}(id) {$clause})", 2),
            $record('index', $indexName, "child{$suffix}", 4, $indexSql, 3),
        ];

        $rows = SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext::actionRelationshipDiagnosticRows311($records, 'next', $status);

        $t->same(1, count($rows));
        $t->same($actionColumn, $rows[0]['action_column']);
        $t->same($action, $rows[0]['action']);
        $t->same($status, $rows[0]['status']);
    };
}

$tests['pragma index xinfo foreignkey next335-342 page wrappers expose set null and set default lookup deltas'] = static function (TestRunner $t) use ($record): void {
    $current = [
        $record('table', 'parent335p', 'parent335p', 2, 'CREATE TABLE parent335p(id INTEGER PRIMARY KEY)', 1),
        $record('table', 'child335p', 'child335p', 3, 'CREATE TABLE child335p(parent_id INTEGER REFERENCES parent335p(id) ON UPDATE SET NULL)', 2),
        $record('index', 'child335p_parent_id', 'child335p', 4, 'CREATE INDEX child335p_parent_id ON child335p(parent_id)', 3),
    ];
    $next = [
        $record('table', 'parent335p', 'parent335p', 2, 'CREATE TABLE parent335p(id INTEGER PRIMARY KEY)', 1),
        $record('table', 'child335p', 'child335p', 3, 'CREATE TABLE child335p(parent_id INTEGER REFERENCES parent335p(id) ON UPDATE SET NULL)', 2),
        $record('index', 'child335p_parent_id_partial', 'child335p', 4, 'CREATE INDEX child335p_parent_id_partial ON child335p(parent_id) WHERE parent_id IS NOT NULL', 3),
    ];

    $page = SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext::page335(
        $current,
        $next,
        'PRAGMA index_xinfo(child335p_parent_id_partial)',
        'PRAGMA foreign_key_list(child335p)',
    );

    $t->same('pragma-index-xinfo-foreignkey-current-source-next335', $page['operation']);
    $t->same(1, $page['next_counts']['foreign_key_action_relationship_diagnostics_next335']['update_set_null_partial_child_lookup_index']);
    $t->same(true, $page['delta']['foreign_key_action_relationship_diagnostic_changed_next335']);
    $t->same(true, method_exists(SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext::class, 'page342'));
};

return $tests;
