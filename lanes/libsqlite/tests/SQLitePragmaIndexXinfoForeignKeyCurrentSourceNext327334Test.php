<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext;
use PortLibs\LibSqlite\SQLiteSchemaRecord;

$record = static fn (string $type, string $name, string $table, ?int $root, ?string $sql, int $rowId): SQLiteSchemaRecord => new SQLiteSchemaRecord($type, $name, $table, $root, $sql, $rowId);

$tests = [];

$cases = [
    327 => ['on_update', 'CASCADE', 'update_cascade_partial_child_lookup_index', 'partial'],
    328 => ['on_delete', 'CASCADE', 'delete_cascade_partial_child_lookup_index', 'partial'],
    329 => ['on_update', 'CASCADE', 'update_cascade_expression_child_lookup_index', 'expression'],
    330 => ['on_delete', 'CASCADE', 'delete_cascade_expression_child_lookup_index', 'expression'],
    331 => ['on_update', 'RESTRICT', 'update_restrict_expression_child_lookup_index', 'expression'],
    332 => ['on_delete', 'RESTRICT', 'delete_restrict_expression_child_lookup_index', 'expression'],
    333 => ['on_update', 'NO ACTION', 'update_no_action_expression_child_lookup_index', 'expression'],
    334 => ['on_delete', 'NO ACTION', 'delete_no_action_expression_child_lookup_index', 'expression'],
];

foreach ($cases as $slice => [$actionColumn, $action, $status, $indexKind]) {
    $tests["pragma index xinfo foreignkey next{$slice} reports {$status}"] = static function (TestRunner $t) use ($record, $slice, $actionColumn, $action, $status, $indexKind): void {
        $suffix = (string) $slice;
        $clause = $actionColumn === 'on_update' ? "ON UPDATE {$action}" : "ON DELETE {$action}";
        $indexSql = $indexKind === 'partial'
            ? "CREATE INDEX child{$suffix}_parent_id_partial ON child{$suffix}(parent_id) WHERE parent_id IS NOT NULL"
            : "CREATE INDEX child{$suffix}_parent_id_expr ON child{$suffix}(parent_id + 0)";
        $records = [
            $record('table', "parent{$suffix}", "parent{$suffix}", 2, "CREATE TABLE parent{$suffix}(id INTEGER PRIMARY KEY)", 1),
            $record('table', "child{$suffix}", "child{$suffix}", 3, "CREATE TABLE child{$suffix}(parent_id INTEGER REFERENCES parent{$suffix}(id) {$clause})", 2),
            $record('index', "child{$suffix}_parent_id_" . ($indexKind === 'partial' ? 'partial' : 'expr'), "child{$suffix}", 4, $indexSql, 3),
        ];

        $rows = SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext::actionRelationshipDiagnosticRows311($records, 'next', $status);

        $t->same(1, count($rows));
        $t->same($actionColumn, $rows[0]['action_column']);
        $t->same($action, $rows[0]['action']);
        $t->same($status, $rows[0]['status']);
    };
}

$tests['pragma index xinfo foreignkey next327-334 page wrappers expose expression and partial deltas'] = static function (TestRunner $t) use ($record): void {
    $current = [
        $record('table', 'parent327p', 'parent327p', 2, 'CREATE TABLE parent327p(id INTEGER PRIMARY KEY)', 1),
        $record('table', 'child327p', 'child327p', 3, 'CREATE TABLE child327p(parent_id INTEGER REFERENCES parent327p(id) ON UPDATE CASCADE)', 2),
        $record('index', 'child327p_parent_id', 'child327p', 4, 'CREATE INDEX child327p_parent_id ON child327p(parent_id)', 3),
    ];
    $next = [
        $record('table', 'parent327p', 'parent327p', 2, 'CREATE TABLE parent327p(id INTEGER PRIMARY KEY)', 1),
        $record('table', 'child327p', 'child327p', 3, 'CREATE TABLE child327p(parent_id INTEGER REFERENCES parent327p(id) ON UPDATE CASCADE)', 2),
        $record('index', 'child327p_parent_id_partial', 'child327p', 4, 'CREATE INDEX child327p_parent_id_partial ON child327p(parent_id) WHERE parent_id IS NOT NULL', 3),
    ];

    $page = SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext::page327(
        $current,
        $next,
        'PRAGMA index_xinfo(child327p_parent_id_partial)',
        'PRAGMA foreign_key_list(child327p)',
    );

    $t->same('pragma-index-xinfo-foreignkey-current-source-next327', $page['operation']);
    $t->same(1, $page['next_counts']['foreign_key_action_relationship_diagnostics_next327']['update_cascade_partial_child_lookup_index']);
    $t->same(true, $page['delta']['foreign_key_action_relationship_diagnostic_changed_next327']);
    $t->same(true, method_exists(SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext::class, 'page334'));
};

return $tests;
