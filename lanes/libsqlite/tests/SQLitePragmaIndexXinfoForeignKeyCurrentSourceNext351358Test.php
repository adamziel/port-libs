<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext;
use PortLibs\LibSqlite\SQLiteSchemaRecord;

$record351358 = static fn (string $type, string $name, string $table, ?int $root, ?string $sql, int $rowId): SQLiteSchemaRecord => new SQLiteSchemaRecord($type, $name, $table, $root, $sql, $rowId);

$scenario351358 = static function (int $slice, string $actionColumn, string $action, string $indexKind) use ($record351358): array {
    $childColumns = $indexKind === 'order' ? 'site_id, post_id' : 'post_title';
    $parentColumns = $indexKind === 'order' ? 'site_id, id' : 'title';
    $childColumnSql = $indexKind === 'order'
        ? 'site_id INTEGER DEFAULT 0, post_id INTEGER DEFAULT 0'
        : 'post_title TEXT COLLATE nocase DEFAULT ""';
    $parentColumnSql = $indexKind === 'order'
        ? 'site_id INTEGER, id INTEGER, PRIMARY KEY(site_id, id)'
        : 'title TEXT COLLATE nocase PRIMARY KEY';
    $clause = $actionColumn === 'update' ? "ON UPDATE {$action}" : "ON DELETE {$action}";
    $indexSql = $indexKind === 'order'
        ? "CREATE INDEX wp_comments_{$slice}_fk_order ON wp_comments_{$slice}(post_id, site_id)"
        : "CREATE INDEX wp_comments_{$slice}_fk_collation ON wp_comments_{$slice}(post_title COLLATE rtrim)";

    return [
        $record351358('table', "wp_posts_{$slice}", "wp_posts_{$slice}", 2, "CREATE TABLE wp_posts_{$slice}({$parentColumnSql})", 1),
        $record351358('table', "wp_comments_{$slice}", "wp_comments_{$slice}", 3, "CREATE TABLE wp_comments_{$slice}({$childColumnSql}, FOREIGN KEY({$childColumns}) REFERENCES wp_posts_{$slice}({$parentColumns}) {$clause})", 2),
        $record351358('index', $indexKind === 'order' ? "wp_comments_{$slice}_fk_order" : "wp_comments_{$slice}_fk_collation", "wp_comments_{$slice}", 4, $indexSql, 3),
    ];
};

$cases351358 = [
    351 => ['update', 'SET NULL', 'order', 'update_set_null_order_mismatch_child_lookup_index'],
    352 => ['delete', 'SET NULL', 'order', 'delete_set_null_order_mismatch_child_lookup_index'],
    353 => ['update', 'SET NULL', 'collation', 'update_set_null_collation_mismatch_child_lookup_index'],
    354 => ['delete', 'SET NULL', 'collation', 'delete_set_null_collation_mismatch_child_lookup_index'],
    355 => ['update', 'SET DEFAULT', 'order', 'update_set_default_order_mismatch_child_lookup_index'],
    356 => ['delete', 'SET DEFAULT', 'order', 'delete_set_default_order_mismatch_child_lookup_index'],
    357 => ['update', 'SET DEFAULT', 'collation', 'update_set_default_collation_mismatch_child_lookup_index'],
    358 => ['delete', 'SET DEFAULT', 'collation', 'delete_set_default_collation_mismatch_child_lookup_index'],
];

$tests = [];
foreach ($cases351358 as $slice => [$actionColumn, $action, $indexKind, $status]) {
    $tests["pragma index xinfo foreignkey current source next{$slice} reports {$status}"] = static function (TestRunner $t) use ($slice, $actionColumn, $action, $indexKind, $status, $scenario351358): void {
        $records = $scenario351358($slice, $actionColumn, $action, $indexKind);
        $method = 'page' . $slice;

        $page = SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext::$method(
            [],
            $records,
            'PRAGMA main.index_xinfo(dummy)',
            "PRAGMA main.foreign_key_list(wp_comments_{$slice})",
            0,
            200,
        );

        $t->same("pragma-index-xinfo-foreignkey-current-source-next{$slice}", $page['operation']);
        $t->same(1, $page['next_counts']["foreign_key_action_relationship_diagnostics_next{$slice}"]['rows']);
        $t->same(1, $page['next_counts']["foreign_key_action_relationship_diagnostics_next{$slice}"][$status]);
        $t->same($status, $page['rows'][$page['total'] - 1]['status']);
        $t->same($actionColumn === 'update' ? 'on_update' : 'on_delete', $page['rows'][$page['total'] - 1]['action_column']);
        $t->same($action, $page['rows'][$page['total'] - 1]['action']);
    };
}

return $tests;
