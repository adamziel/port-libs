<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext;
use PortLibs\LibSqlite\SQLiteSchemaRecord;

$record230 = static fn (string $type, string $name, string $table, ?int $root, ?string $sql, int $rowId): SQLiteSchemaRecord => new SQLiteSchemaRecord($type, $name, $table, $root, $sql, $rowId);

$currentRecords230 = [
    $record230('table', 'wp_posts_stage', 'wp_posts_stage', 2, 'CREATE TABLE wp_posts_stage(post_id INTEGER PRIMARY KEY, post_title TEXT NOT NULL)', 1),
    $record230('table', 'wp_users_stage', 'wp_users_stage', 3, 'CREATE TABLE wp_users_stage(user_id INTEGER PRIMARY KEY, user_login TEXT NOT NULL)', 2),
    $record230('table', 'wp_named_oid_parent', 'wp_named_oid_parent', 4, 'CREATE TABLE wp_named_oid_parent(oid INTEGER PRIMARY KEY, label TEXT)', 3),
    $record230('table', 'wp_postmeta_import', 'wp_postmeta_import', 5, "CREATE TABLE wp_postmeta_import(
        meta_id INTEGER PRIMARY KEY,
        post_id INTEGER NOT NULL,
        user_id INTEGER,
        oid_ref INTEGER,
        FOREIGN KEY(post_id) REFERENCES wp_posts_stage(rowid),
        FOREIGN KEY(user_id) REFERENCES wp_users_stage(_rowid_) ON UPDATE CASCADE,
        FOREIGN KEY(oid_ref) REFERENCES wp_named_oid_parent(oid)
    )", 4),
    $record230('index', 'wp_postmeta_import_post_id_idx', 'wp_postmeta_import', 6, 'CREATE INDEX wp_postmeta_import_post_id_idx ON wp_postmeta_import(post_id)', 5),
];

$nextRecords230 = [
    $record230('table', 'wp_posts_stage', 'wp_posts_stage', 2, 'CREATE TABLE wp_posts_stage(post_id INTEGER PRIMARY KEY, post_title TEXT NOT NULL)', 1),
    $record230('table', 'wp_users_stage', 'wp_users_stage', 3, 'CREATE TABLE wp_users_stage(user_id INTEGER PRIMARY KEY, user_login TEXT NOT NULL)', 2),
    $record230('table', 'wp_named_oid_parent', 'wp_named_oid_parent', 4, 'CREATE TABLE wp_named_oid_parent(oid INTEGER PRIMARY KEY, label TEXT)', 3),
    $record230('table', 'wp_postmeta_import', 'wp_postmeta_import', 5, "CREATE TABLE wp_postmeta_import(
        meta_id INTEGER PRIMARY KEY,
        post_id INTEGER NOT NULL,
        user_id INTEGER,
        oid_ref INTEGER,
        FOREIGN KEY(post_id) REFERENCES wp_posts_stage(post_id),
        FOREIGN KEY(user_id) REFERENCES wp_users_stage(user_id) ON UPDATE CASCADE,
        FOREIGN KEY(oid_ref) REFERENCES wp_named_oid_parent(oid)
    )", 4),
    $record230('index', 'wp_postmeta_import_post_id_idx', 'wp_postmeta_import', 6, 'CREATE INDEX wp_postmeta_import_post_id_idx ON wp_postmeta_import(post_id)', 5),
];

$unrepairedRecords230 = [
    $record230('table', 'wp_posts_stage', 'wp_posts_stage', 2, 'CREATE TABLE wp_posts_stage(post_id INTEGER PRIMARY KEY, post_title TEXT NOT NULL)', 1),
    $record230('table', 'wp_users_stage', 'wp_users_stage', 3, 'CREATE TABLE wp_users_stage(user_id INTEGER PRIMARY KEY, user_login TEXT NOT NULL)', 2),
    $record230('table', 'wp_named_oid_parent', 'wp_named_oid_parent', 4, 'CREATE TABLE wp_named_oid_parent(oid INTEGER PRIMARY KEY, label TEXT)', 3),
    $record230('table', 'wp_postmeta_import', 'wp_postmeta_import', 5, "CREATE TABLE wp_postmeta_import(
        meta_id INTEGER PRIMARY KEY,
        post_id INTEGER NOT NULL,
        user_id INTEGER,
        oid_ref INTEGER,
        FOREIGN KEY(post_id) REFERENCES wp_posts_stage(rowid),
        FOREIGN KEY(user_id) REFERENCES wp_users_stage(user_id) ON UPDATE CASCADE,
        FOREIGN KEY(oid_ref) REFERENCES wp_named_oid_parent(oid)
    )", 4),
];

$page230 = static fn (
    int $offset = 0,
    int $limit = 100,
    ?array $resume = null,
    ?array $nextRecords = null,
): array => SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext::page230(
    $currentRecords230,
    $nextRecords ?? $nextRecords230,
    'PRAGMA main.index_xinfo(wp_postmeta_import_post_id_idx)',
    'PRAGMA main.foreign_key_list(wp_postmeta_import)',
    $offset,
    $limit,
    $resume,
);

$valueAt230 = static function (mixed $value, string $path): mixed {
    foreach (explode('.', $path) as $part) {
        if (is_array($value) && array_key_exists($part, $value)) {
            $value = $value[$part];
            continue;
        }
        $value = $value[(int) $part];
    }

    return $value;
};

$default230 = static fn (): array => $page230();
$unrepaired230 = static fn (): array => $page230(nextRecords: $unrepairedRecords230);
$currentRows230 = static fn (): array => SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext::pseudoRowidParentRows230($currentRecords230);
$nextRows230 = static fn (): array => SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext::pseudoRowidParentRows230($nextRecords230, 'next');

$cases230 = [
    'status ok' => [$default230, 'status', 'ok'],
    'operation marker' => [$default230, 'operation', 'pragma-index-xinfo-foreignkey-current-source-next230'],
    'source id length' => [static fn (): array => ['len' => strlen($page230()['source_id'])], 'len', 64],
    'offset default' => [$default230, 'offset', 0],
    'limit default' => [$default230, 'limit', 100],
    'dependency appended' => [$default230, 'dependencies.10', 'sqlite-pragma-foreign-key-parent-pseudo-rowid-rejection'],
    'base suffix rows retained' => [$default230, 'current.foreign_key_child_suffix_indexes.rows', 0],
    'pseudo source current' => [$default230, 'current_source.foreign_key_parent_pseudo_rowid_source', 'pragma_foreign_key_list_parent_columns_plus_pragma_table_info_named_columns'],
    'pseudo source next' => [$default230, 'next_source.foreign_key_parent_pseudo_rowid_source', 'pragma_foreign_key_list_parent_columns_plus_pragma_table_info_named_columns'],
    'current pseudo rows' => [$default230, 'current.foreign_key_parent_pseudo_rowid.rows', 3],
    'current pseudo blockers' => [$default230, 'current.foreign_key_parent_pseudo_rowid.pseudo_rowid_parent_key', 2],
    'current declared row' => [$default230, 'current.foreign_key_parent_pseudo_rowid.declared_parent_column', 1],
    'current foreign keys' => [$default230, 'current.foreign_key_parent_pseudo_rowid.foreign_keys', 3],
    'current parent tables' => [$default230, 'current.foreign_key_parent_pseudo_rowid.parent_tables', 3],
    'next pseudo rows' => [$default230, 'next_counts.foreign_key_parent_pseudo_rowid.rows', 1],
    'next pseudo blockers' => [$default230, 'next_counts.foreign_key_parent_pseudo_rowid.pseudo_rowid_parent_key', 0],
    'next declared row' => [$default230, 'next_counts.foreign_key_parent_pseudo_rowid.declared_parent_column', 1],
    'delta row count' => [$default230, 'delta.foreign_key_parent_pseudo_rowid_rows', -2],
    'delta blocker count' => [$default230, 'delta.foreign_key_parent_pseudo_rowid_blockers', -2],
    'delta repaired true' => [$default230, 'delta.foreign_key_parent_pseudo_rowid_repaired', true],
    'delta changed true' => [$default230, 'delta.foreign_key_parent_pseudo_rowid_changed', true],
    'current summary rowid blocker' => [$default230, 'current_source.foreign_key_parent_pseudo_rowid.0', 'current:wp_postmeta_import#0.0:post_id->wp_posts_stage.rowid:declared=0:pseudo_rowid_parent_key'],
    'current summary underscore blocker' => [$default230, 'current_source.foreign_key_parent_pseudo_rowid.1', 'current:wp_postmeta_import#1.0:user_id->wp_users_stage._rowid_:declared=0:pseudo_rowid_parent_key'],
    'current summary oid declared' => [$default230, 'current_source.foreign_key_parent_pseudo_rowid.2', 'current:wp_postmeta_import#2.0:oid_ref->wp_named_oid_parent.oid:declared=1:declared_parent_column'],
    'next summary only declared oid' => [$default230, 'next_source.foreign_key_parent_pseudo_rowid.0', 'next:wp_postmeta_import#2.0:oid_ref->wp_named_oid_parent.oid:declared=1:declared_parent_column'],
    'first helper kind' => [$currentRows230, '0.kind', 'foreign_key_parent_pseudo_rowid'],
    'first helper blocker status' => [$currentRows230, '0.status', 'pseudo_rowid_parent_key'],
    'first helper to rowid' => [$currentRows230, '0.to', 'rowid'],
    'second helper to underscore rowid' => [$currentRows230, '1.to', '_rowid_'],
    'second helper status' => [$currentRows230, '1.status', 'pseudo_rowid_parent_key'],
    'second helper declared false' => [$currentRows230, '1.declared_parent_column', false],
    'second helper declared columns first' => [$currentRows230, '1.declared_parent_columns.0', 'user_id'],
    'third helper pseudo name' => [$currentRows230, '2.pseudo_rowid_name', 'oid'],
    'third helper parent' => [$currentRows230, '2.parent', 'wp_named_oid_parent'],
    'third helper from' => [$currentRows230, '2.from', 'oid_ref'],
    'next helper count' => [static fn (): array => ['count' => count($nextRows230())], 'count', 1],
    'next helper declared status' => [$nextRows230, '0.status', 'declared_parent_column'],
    'next helper phase' => [$nextRows230, '0.phase', 'next'],
    'unrepaired next blockers' => [$unrepaired230, 'next_counts.foreign_key_parent_pseudo_rowid.pseudo_rowid_parent_key', 1],
    'unrepaired repaired false' => [$unrepaired230, 'delta.foreign_key_parent_pseudo_rowid_repaired', false],
];

$tests = [];
foreach ($cases230 as $name => [$factory, $path, $expected]) {
    $tests['pragma index xinfo foreignkey parent pseudo rowid current source next230 ' . $name] = static function (TestRunner $t) use ($factory, $path, $expected, $valueAt230): void {
        $t->same($expected, $valueAt230($factory(), $path));
    };
}

$tests['pragma index xinfo foreignkey parent pseudo rowid current source next230 paginates appended rows'] = static function (TestRunner $t) use ($page230): void {
    $first = $page230(0, 30);
    $second = $page230(30, 2, $first['next']);
    $third = $page230(32, 1, $second['next']);
    $fourth = $page230(33, 1, $third['next']);

    $t->same(30, $first['count']);
    $t->same('foreign_key_parent_pseudo_rowid', $first['next_row']['kind']);
    $t->same('pseudo_rowid_parent_key', $second['rows'][0]['status']);
    $t->same('pseudo_rowid_parent_key', $second['rows'][1]['status']);
    $t->same('oid', $third['rows'][0]['to']);
    $t->same('next', $fourth['rows'][0]['phase']);
    $t->same(null, $fourth['next']);
};

$tests['pragma index xinfo foreignkey parent pseudo rowid current source next230 ignores ordinary named parent keys'] = static function (TestRunner $t) use ($record230): void {
    $records = [
        $record230('table', 'parent', 'parent', 2, 'CREATE TABLE parent(id INTEGER PRIMARY KEY)', 1),
        $record230('table', 'child', 'child', 3, 'CREATE TABLE child(parent_id INTEGER REFERENCES parent(id))', 2),
    ];

    $t->same([], SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext::pseudoRowidParentRows230($records));
};

$tests['pragma index xinfo foreignkey parent pseudo rowid current source next230 treats declared rowid as named column'] = static function (TestRunner $t) use ($record230): void {
    $records = [
        $record230('table', 'parent', 'parent', 2, 'CREATE TABLE parent(rowid INTEGER PRIMARY KEY, value TEXT)', 1),
        $record230('table', 'child', 'child', 3, 'CREATE TABLE child(parent_id INTEGER REFERENCES parent(rowid))', 2),
    ];

    $rows = SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext::pseudoRowidParentRows230($records);
    $t->same(1, count($rows));
    $t->same('declared_parent_column', $rows[0]['status']);
    $t->same(true, $rows[0]['declared_parent_column']);
};

$tests['pragma index xinfo foreignkey parent pseudo rowid current source next230 flags missing oid column'] = static function (TestRunner $t) use ($record230): void {
    $records = [
        $record230('table', 'parent', 'parent', 2, 'CREATE TABLE parent(id INTEGER PRIMARY KEY, value TEXT)', 1),
        $record230('table', 'child', 'child', 3, 'CREATE TABLE child(parent_id INTEGER REFERENCES parent(oid))', 2),
    ];

    $rows = SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext::pseudoRowidParentRows230($records);
    $t->same(1, count($rows));
    $t->same('pseudo_rowid_parent_key', $rows[0]['status']);
    $t->same(['id', 'value'], $rows[0]['declared_parent_columns']);
};

$tests['pragma index xinfo foreignkey parent pseudo rowid current source next230 rejects stale cursor'] = static function (TestRunner $t) use ($page230, $unrepairedRecords230): void {
    $first = $page230(0, 31);

    $t->throws(InvalidArgumentException::class, static fn (): array => $page230(31, 1, $first['next'], $unrepairedRecords230));
};

$tests['pragma index xinfo foreignkey parent pseudo rowid current source next230 rejects stale offset'] = static function (TestRunner $t) use ($page230): void {
    $first = $page230(0, 31);

    $t->throws(InvalidArgumentException::class, static fn (): array => $page230(32, 1, $first['next']));
};

$tests['pragma index xinfo foreignkey parent pseudo rowid current source next230 rejects invalid records'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn (): array => SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext::pseudoRowidParentRows230([['bad' => true]]));
};

$tests['pragma index xinfo foreignkey parent pseudo rowid current source next230 rejects invalid bounds'] = static function (TestRunner $t) use ($page230): void {
    $t->throws(InvalidArgumentException::class, static fn (): array => $page230(-1, 10));
    $t->throws(InvalidArgumentException::class, static fn (): array => $page230(0, 0));
};

return $tests;
