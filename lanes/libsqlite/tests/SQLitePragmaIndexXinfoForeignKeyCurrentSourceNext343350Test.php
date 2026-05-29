<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext;
use PortLibs\LibSqlite\SQLiteSchemaRecord;

$record343350 = static fn (string $type, string $name, string $table, ?int $root, ?string $sql, int $rowId): SQLiteSchemaRecord => new SQLiteSchemaRecord($type, $name, $table, $root, $sql, $rowId);

$partialIndex343350 = static fn (string $name, string $table, string $column, int $rowId) => $record343350(
    'index',
    $name,
    $table,
    $rowId + 10,
    "CREATE INDEX {$name} ON {$table}({$column}) WHERE {$column} IS NOT NULL",
    $rowId,
);
$expressionIndex343350 = static fn (string $name, string $table, string $column, int $rowId) => $record343350(
    'index',
    $name,
    $table,
    $rowId + 10,
    "CREATE INDEX {$name} ON {$table}({$column} + 0)",
    $rowId,
);

$scenario343350 = static function (int $slice, string $actionColumn, string $action, SQLiteSchemaRecord $index) use ($record343350): array {
    $clause = $actionColumn === 'update' ? "ON UPDATE {$action}" : "ON DELETE {$action}";
    $default = $action === 'SET DEFAULT' ? ' DEFAULT 0' : '';

    return [
        $record343350('table', "wp_posts_{$slice}", "wp_posts_{$slice}", 2, "CREATE TABLE wp_posts_{$slice}(ID INTEGER PRIMARY KEY)", 1),
        $record343350('table', "wp_comments_{$slice}", "wp_comments_{$slice}", 3, "CREATE TABLE wp_comments_{$slice}(comment_post_ID INTEGER{$default} REFERENCES wp_posts_{$slice}(ID) {$clause})", 2),
        $index,
    ];
};

$cases343350 = [
    343 => ['update', 'SET NULL', 'partial', 'update_set_null_partial_child_lookup_index'],
    344 => ['delete', 'SET NULL', 'partial', 'delete_set_null_partial_child_lookup_index'],
    345 => ['update', 'SET NULL', 'expression', 'update_set_null_expression_child_lookup_index'],
    346 => ['delete', 'SET NULL', 'expression', 'delete_set_null_expression_child_lookup_index'],
    347 => ['update', 'SET DEFAULT', 'partial', 'update_set_default_partial_child_lookup_index'],
    348 => ['delete', 'SET DEFAULT', 'partial', 'delete_set_default_partial_child_lookup_index'],
    349 => ['update', 'SET DEFAULT', 'expression', 'update_set_default_expression_child_lookup_index'],
    350 => ['delete', 'SET DEFAULT', 'expression', 'delete_set_default_expression_child_lookup_index'],
];

$tests = [];
foreach ($cases343350 as $slice => [$actionColumn, $action, $indexKind, $status]) {
    $tests["pragma index xinfo foreignkey current source next{$slice} exposes {$status}"] = static function (TestRunner $t) use ($slice, $actionColumn, $action, $indexKind, $status, $scenario343350, $partialIndex343350, $expressionIndex343350): void {
        $table = "wp_comments_{$slice}";
        $index = $indexKind === 'partial'
            ? $partialIndex343350("{$table}_post_partial", $table, 'comment_post_ID', 3)
            : $expressionIndex343350("{$table}_post_expr", $table, 'comment_post_ID', 3);
        $records = $scenario343350($slice, $actionColumn, $action, $index);
        $method = 'page' . $slice;

        $page = SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext::$method(
            [],
            $records,
            'PRAGMA main.index_xinfo(dummy)',
            "PRAGMA main.foreign_key_list({$table})",
            0,
            200,
        );

        $t->same("pragma-index-xinfo-foreignkey-current-source-next{$slice}", $page['operation']);
        $t->same(1, $page['next_counts']["foreign_key_action_relationship_diagnostics_next{$slice}"]['rows']);
        $t->same(1, $page['next_counts']["foreign_key_action_relationship_diagnostics_next{$slice}"]['blocked']);
        $t->same($status, $page['rows'][$page['total'] - 1]['status']);
        $t->same($actionColumn === 'update' ? 'on_update' : 'on_delete', $page['rows'][$page['total'] - 1]['action_column']);
    };
}

return $tests;
