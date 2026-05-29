<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext;
use PortLibs\LibSqlite\SQLiteSchemaRecord;

$record367374 = static fn (string $type, string $name, string $table, ?int $root, ?string $sql, int $rowId): SQLiteSchemaRecord => new SQLiteSchemaRecord($type, $name, $table, $root, $sql, $rowId);

$scenario367374 = static function (int $slice, string $actionColumn, string $action, string $indexKind) use ($record367374): array {
    $childColumns = $indexKind === 'collation' ? 'post_title' : 'site_id, post_id';
    $parentColumns = $indexKind === 'collation' ? 'title' : 'site_id, id';
    $childColumnSql = $indexKind === 'collation'
        ? 'post_title TEXT COLLATE nocase DEFAULT ""'
        : 'site_id INTEGER DEFAULT 0, post_id INTEGER DEFAULT 0';
    $parentColumnSql = $indexKind === 'collation'
        ? 'title TEXT COLLATE nocase PRIMARY KEY'
        : 'site_id INTEGER, id INTEGER, PRIMARY KEY(site_id, id)';
    $clause = $actionColumn === 'update' ? "ON UPDATE {$action}" : "ON DELETE {$action}";
    $indexSql = match ($indexKind) {
        'order' => "CREATE INDEX wp_comments_{$slice}_fk_order ON wp_comments_{$slice}(post_id, site_id)",
        'collation' => "CREATE INDEX wp_comments_{$slice}_fk_collation ON wp_comments_{$slice}(post_title COLLATE rtrim)",
        default => "CREATE INDEX wp_comments_{$slice}_fk_desc ON wp_comments_{$slice}(site_id DESC, post_id)",
    };
    $indexName = match ($indexKind) {
        'order' => "wp_comments_{$slice}_fk_order",
        'collation' => "wp_comments_{$slice}_fk_collation",
        default => "wp_comments_{$slice}_fk_desc",
    };

    return [
        $record367374('table', "wp_posts_{$slice}", "wp_posts_{$slice}", 2, "CREATE TABLE wp_posts_{$slice}({$parentColumnSql})", 1),
        $record367374('table', "wp_comments_{$slice}", "wp_comments_{$slice}", 3, "CREATE TABLE wp_comments_{$slice}({$childColumnSql}, FOREIGN KEY({$childColumns}) REFERENCES wp_posts_{$slice}({$parentColumns}) {$clause})", 2),
        $record367374('index', $indexName, "wp_comments_{$slice}", 4, $indexSql, 3),
    ];
};

$cases367374 = [
    367 => ['update', 'NO ACTION', 'order', 'update_no_action_order_mismatch_child_lookup_index'],
    368 => ['delete', 'NO ACTION', 'order', 'delete_no_action_order_mismatch_child_lookup_index'],
    369 => ['update', 'NO ACTION', 'collation', 'update_no_action_collation_mismatch_child_lookup_index'],
    370 => ['delete', 'NO ACTION', 'collation', 'delete_no_action_collation_mismatch_child_lookup_index'],
    371 => ['update', 'CASCADE', 'desc', 'update_cascade_desc_mismatch_child_lookup_index'],
    372 => ['delete', 'CASCADE', 'desc', 'delete_cascade_desc_mismatch_child_lookup_index'],
    373 => ['update', 'RESTRICT', 'desc', 'update_restrict_desc_mismatch_child_lookup_index'],
    374 => ['delete', 'RESTRICT', 'desc', 'delete_restrict_desc_mismatch_child_lookup_index'],
];

$tests = [];
foreach ($cases367374 as $slice => [$actionColumn, $action, $indexKind, $status]) {
    $tests["pragma index xinfo foreignkey current source next{$slice} reports {$status}"] = static function (TestRunner $t) use ($slice, $actionColumn, $action, $indexKind, $status, $scenario367374): void {
        $records = $scenario367374($slice, $actionColumn, $action, $indexKind);
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
