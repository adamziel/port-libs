<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext;
use PortLibs\LibSqlite\SQLiteSchemaRecord;

$record10711086 = static fn (string $type, string $name, string $table, ?int $root, ?string $sql, int $rowId): SQLiteSchemaRecord => new SQLiteSchemaRecord($type, $name, $table, $root, $sql, $rowId);

$scenario10711086 = static function (int $slice, string $updateAction, string $deleteAction, string $indexKind, bool $validLookup) use ($record10711086): array {
    $childColumns = $indexKind === 'collation' ? 'post_title' : 'site_id, post_id';
    $parentColumns = $indexKind === 'collation' ? 'title' : 'site_id, id';
    $childColumnSql = $indexKind === 'collation'
        ? 'post_title TEXT COLLATE nocase DEFAULT ""'
        : 'site_id INTEGER DEFAULT 0, post_id INTEGER DEFAULT 0';
    $parentColumnSql = $indexKind === 'collation'
        ? 'title TEXT COLLATE nocase PRIMARY KEY'
        : 'site_id INTEGER, id INTEGER, PRIMARY KEY(site_id, id)';
    $indexSql = match (true) {
        $validLookup && $indexKind === 'collation' => "CREATE INDEX wp_comments_{$slice}_fk_ok ON wp_comments_{$slice}(post_title)",
        $validLookup => "CREATE INDEX wp_comments_{$slice}_fk_ok ON wp_comments_{$slice}(site_id, post_id)",
        $indexKind === 'order' => "CREATE INDEX wp_comments_{$slice}_fk_order ON wp_comments_{$slice}(post_id, site_id)",
        $indexKind === 'collation' => "CREATE INDEX wp_comments_{$slice}_fk_collation ON wp_comments_{$slice}(post_title COLLATE rtrim)",
        default => "CREATE INDEX wp_comments_{$slice}_fk_desc ON wp_comments_{$slice}(site_id DESC, post_id)",
    };
    $indexName = match (true) {
        $validLookup => "wp_comments_{$slice}_fk_ok",
        $indexKind === 'order' => "wp_comments_{$slice}_fk_order",
        $indexKind === 'collation' => "wp_comments_{$slice}_fk_collation",
        default => "wp_comments_{$slice}_fk_desc",
    };

    return [
        $record10711086('table', "wp_posts_{$slice}", "wp_posts_{$slice}", 2, "CREATE TABLE wp_posts_{$slice}({$parentColumnSql})", 1),
        $record10711086('table', "wp_comments_{$slice}", "wp_comments_{$slice}", 3, "CREATE TABLE wp_comments_{$slice}({$childColumnSql}, FOREIGN KEY({$childColumns}) REFERENCES wp_posts_{$slice}({$parentColumns}) ON UPDATE {$updateAction} ON DELETE {$deleteAction})", 2),
        $record10711086('index', $indexName, "wp_comments_{$slice}", 4, $indexSql, 3),
    ];
};

$cases10711086 = [
    1071 => ['CASCADE', 'RESTRICT', 'order', 'on_update', 'CASCADE', 'update_cascade_order_mismatch_child_lookup_index'],
    1072 => ['CASCADE', 'RESTRICT', 'order', 'on_delete', 'RESTRICT', 'delete_restrict_order_mismatch_child_lookup_index'],
    1073 => ['CASCADE', 'RESTRICT', 'collation', 'on_update', 'CASCADE', 'update_cascade_collation_mismatch_child_lookup_index'],
    1074 => ['CASCADE', 'RESTRICT', 'collation', 'on_delete', 'RESTRICT', 'delete_restrict_collation_mismatch_child_lookup_index'],
    1075 => ['NO ACTION', 'SET NULL', 'order', 'on_update', 'NO ACTION', 'update_no_action_order_mismatch_child_lookup_index'],
    1076 => ['NO ACTION', 'SET NULL', 'order', 'on_delete', 'SET NULL', 'delete_set_null_order_mismatch_child_lookup_index'],
    1077 => ['NO ACTION', 'SET NULL', 'collation', 'on_update', 'NO ACTION', 'update_no_action_collation_mismatch_child_lookup_index'],
    1078 => ['NO ACTION', 'SET NULL', 'collation', 'on_delete', 'SET NULL', 'delete_set_null_collation_mismatch_child_lookup_index'],
    1079 => ['SET DEFAULT', 'CASCADE', 'order', 'on_update', 'SET DEFAULT', 'update_set_default_order_mismatch_child_lookup_index'],
    1080 => ['SET DEFAULT', 'CASCADE', 'order', 'on_delete', 'CASCADE', 'delete_cascade_order_mismatch_child_lookup_index'],
    1081 => ['SET DEFAULT', 'CASCADE', 'collation', 'on_update', 'SET DEFAULT', 'update_set_default_collation_mismatch_child_lookup_index'],
    1082 => ['SET DEFAULT', 'CASCADE', 'collation', 'on_delete', 'CASCADE', 'delete_cascade_collation_mismatch_child_lookup_index'],
    1083 => ['RESTRICT', 'NO ACTION', 'desc', 'on_update', 'RESTRICT', 'update_restrict_desc_mismatch_child_lookup_index'],
    1084 => ['RESTRICT', 'NO ACTION', 'desc', 'on_delete', 'NO ACTION', 'delete_no_action_desc_mismatch_child_lookup_index'],
    1085 => ['SET NULL', 'SET DEFAULT', 'desc', 'on_update', 'SET NULL', 'update_set_null_desc_mismatch_child_lookup_index'],
    1086 => ['SET NULL', 'SET DEFAULT', 'desc', 'on_delete', 'SET DEFAULT', 'delete_set_default_desc_mismatch_child_lookup_index'],
];

$tests = [];
foreach ($cases10711086 as $slice => [$updateAction, $deleteAction, $indexKind, $actionColumn, $action, $status]) {
    $tests["pragma index xinfo foreignkey current source next{$slice} reports next-only staged mixed-action {$status}"] = static function (TestRunner $t) use ($slice, $updateAction, $deleteAction, $indexKind, $actionColumn, $action, $status, $scenario10711086): void {
        $currentRecords = $scenario10711086($slice, $updateAction, $deleteAction, $indexKind, true);
        $nextRecords = $scenario10711086($slice, $updateAction, $deleteAction, $indexKind, false);
        $method = 'page' . $slice;

        $page = SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext::$method(
            $currentRecords,
            $nextRecords,
            'PRAGMA main.index_xinfo(dummy)',
            "PRAGMA main.foreign_key_list(wp_comments_{$slice})",
            0,
            560,
        );

        $t->same("pragma-index-xinfo-foreignkey-current-source-next{$slice}", $page['operation']);
        $t->same(0, $page['current']["foreign_key_action_relationship_diagnostics_next{$slice}"]['rows']);
        $t->same(1, $page['next_counts']["foreign_key_action_relationship_diagnostics_next{$slice}"]['rows']);
        $t->same(1, $page['next_counts']["foreign_key_action_relationship_diagnostics_next{$slice}"][$status]);
        $t->same(1, $page['delta']["foreign_key_action_relationship_diagnostic_rows_next{$slice}"]);
        $t->same(true, $page['delta']["foreign_key_action_relationship_diagnostic_changed_next{$slice}"]);
        $t->same($status, $page['rows'][$page['total'] - 1]['status']);
        $t->same($actionColumn, $page['rows'][$page['total'] - 1]['action_column']);
        $t->same($action, $page['rows'][$page['total'] - 1]['action']);
    };
}

return $tests;
