<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext;
use PortLibs\LibSqlite\SQLiteSchemaRecord;

$record375382 = static fn (string $type, string $name, string $table, ?int $root, ?string $sql, int $rowId): SQLiteSchemaRecord => new SQLiteSchemaRecord($type, $name, $table, $root, $sql, $rowId);

$scenario375382 = static function (int $slice, string $actionColumn, string $action, string $status) use ($record375382): array {
    $clause = $actionColumn === 'update' ? "ON UPDATE {$action}" : "ON DELETE {$action}";
    if ($slice === 381) {
        $clause = 'ON UPDATE SET NULL ON DELETE NO ACTION';
    }
    if ($slice === 382) {
        $clause = 'ON UPDATE NO ACTION ON DELETE SET DEFAULT';
    }

    return [
        $record375382('table', "wp_posts_{$slice}", "wp_posts_{$slice}", 2, "CREATE TABLE wp_posts_{$slice}(site_id INTEGER, id INTEGER, PRIMARY KEY(site_id, id))", 1),
        $record375382('table', "wp_comments_{$slice}", "wp_comments_{$slice}", 3, "CREATE TABLE wp_comments_{$slice}(site_id INTEGER DEFAULT 0, post_id INTEGER DEFAULT 0, FOREIGN KEY(site_id, post_id) REFERENCES wp_posts_{$slice}(site_id, id) {$clause})", 2),
        $record375382('index', "wp_comments_{$slice}_fk_desc", "wp_comments_{$slice}", 4, "CREATE INDEX wp_comments_{$slice}_fk_desc ON wp_comments_{$slice}(site_id DESC, post_id)", 3),
    ];
};

$cases375382 = [
    375 => ['update', 'SET NULL', 'update_set_null_desc_mismatch_child_lookup_index'],
    376 => ['delete', 'SET NULL', 'delete_set_null_desc_mismatch_child_lookup_index'],
    377 => ['update', 'SET DEFAULT', 'update_set_default_desc_mismatch_child_lookup_index'],
    378 => ['delete', 'SET DEFAULT', 'delete_set_default_desc_mismatch_child_lookup_index'],
    379 => ['update', 'NO ACTION', 'update_no_action_desc_mismatch_child_lookup_index'],
    380 => ['delete', 'NO ACTION', 'delete_no_action_desc_mismatch_child_lookup_index'],
    381 => ['update', 'SET NULL', 'update_set_null_desc_mismatch_child_lookup_index'],
    382 => ['delete', 'SET DEFAULT', 'delete_set_default_desc_mismatch_child_lookup_index'],
];

$tests = [];
foreach ($cases375382 as $slice => [$actionColumn, $action, $status]) {
    $tests["pragma index xinfo foreignkey current source next{$slice} reports {$status}"] = static function (TestRunner $t) use ($slice, $actionColumn, $action, $status, $scenario375382): void {
        $records = $scenario375382($slice, $actionColumn, $action, $status);
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
