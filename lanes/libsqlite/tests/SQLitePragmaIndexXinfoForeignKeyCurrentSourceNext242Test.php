<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/SQLiteSchemaRecord.php';
require_once __DIR__ . '/../src/SQLiteCreateTable.php';
require_once __DIR__ . '/../src/SQLiteIndexColumn.php';
require_once __DIR__ . '/../src/SQLitePragmaSchemaCatalog.php';
require_once __DIR__ . '/../src/SQLitePragmaForeignKeyCheck.php';
require_once __DIR__ . '/../src/SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext.php';

use PortLibs\LibSqlite\SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext;
use PortLibs\LibSqlite\SQLiteSchemaRecord;

$record242 = static fn (string $type, string $name, string $table, ?int $root, ?string $sql, int $rowId): SQLiteSchemaRecord => new SQLiteSchemaRecord($type, $name, $table, $root, $sql, $rowId);

$currentRecords242 = [
    $record242('table', 'wp_import_parent', 'wp_import_parent', 2, 'CREATE TABLE wp_import_parent(slug TEXT NOT NULL, taxonomy TEXT NOT NULL, term_id INTEGER PRIMARY KEY, UNIQUE(slug, taxonomy), UNIQUE(slug))', 1),
    $record242('index', 'sqlite_autoindex_wp_import_parent_1', 'wp_import_parent', 3, null, 2),
    $record242('index', 'sqlite_autoindex_wp_import_parent_2', 'wp_import_parent', 4, null, 3),
    $record242('table', 'wp_import_meta', 'wp_import_meta', 5, 'CREATE TABLE wp_import_meta(meta_id INTEGER PRIMARY KEY, parent_row INTEGER NOT NULL, parent_oid INTEGER NOT NULL, parent_hidden INTEGER NOT NULL, FOREIGN KEY(parent_row) REFERENCES wp_import_parent(rowid), FOREIGN KEY(parent_oid) REFERENCES wp_import_parent(oid), FOREIGN KEY(parent_hidden) REFERENCES wp_import_parent(_rowid_))', 4),
];

$nextRecords242 = [
    $record242('table', 'wp_import_parent', 'wp_import_parent', 2, 'CREATE TABLE wp_import_parent(slug TEXT NOT NULL, taxonomy TEXT NOT NULL, term_id INTEGER PRIMARY KEY, UNIQUE(slug, taxonomy), UNIQUE(slug))', 1),
    $currentRecords242[1],
    $currentRecords242[2],
    $record242('table', 'wp_import_meta', 'wp_import_meta', 5, 'CREATE TABLE wp_import_meta(meta_id INTEGER PRIMARY KEY, parent_row INTEGER NOT NULL, parent_oid INTEGER NOT NULL, parent_hidden INTEGER NOT NULL, FOREIGN KEY(parent_row) REFERENCES wp_import_parent(term_id), FOREIGN KEY(parent_oid) REFERENCES wp_import_parent(term_id), FOREIGN KEY(parent_hidden) REFERENCES wp_import_parent(term_id))', 4),
];

$shadowRecords242 = [
    $record242('table', 'wp_shadow_parent', 'wp_shadow_parent', 2, 'CREATE TABLE wp_shadow_parent(rowid INTEGER NOT NULL UNIQUE, slug TEXT NOT NULL)', 1),
    $record242('index', 'sqlite_autoindex_wp_shadow_parent_1', 'wp_shadow_parent', 3, null, 2),
    $record242('table', 'wp_shadow_child', 'wp_shadow_child', 4, 'CREATE TABLE wp_shadow_child(parent_row INTEGER, FOREIGN KEY(parent_row) REFERENCES wp_shadow_parent(rowid))', 3),
];

$blockedNextRecords242 = [
    $currentRecords242[0],
    $currentRecords242[1],
    $currentRecords242[2],
    $record242('table', 'wp_import_meta', 'wp_import_meta', 5, 'CREATE TABLE wp_import_meta(meta_id INTEGER PRIMARY KEY, parent_row INTEGER NOT NULL, parent_oid INTEGER NOT NULL, parent_hidden INTEGER NOT NULL, FOREIGN KEY(parent_row) REFERENCES wp_import_parent(rowid), FOREIGN KEY(parent_oid) REFERENCES wp_import_parent(term_id), FOREIGN KEY(parent_hidden) REFERENCES wp_import_parent(_rowid_))', 4),
];

$page242 = static fn (
    int $offset = 0,
    int $limit = 220,
    ?array $resume = null,
    ?array $nextRecords = null,
): array => SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext::page242(
    $currentRecords242,
    $nextRecords ?? $nextRecords242,
    'PRAGMA main.index_xinfo(sqlite_autoindex_wp_import_parent_1)',
    'PRAGMA main.foreign_key_list(wp_import_meta)',
    $offset,
    $limit,
    $resume,
);

$valueAt242 = static function (mixed $value, string $path): mixed {
    foreach (explode('.', $path) as $part) {
        if (is_array($value) && array_key_exists($part, $value)) {
            $value = $value[$part];
            continue;
        }
        $value = $value[(int) $part];
    }

    return $value;
};

$default242 = static fn (): array => $page242();
$blocked242 = static fn (): array => $page242(nextRecords: $blockedNextRecords242);
$currentRowid242 = static fn (): array => SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext::rowidParentKeyRows242($currentRecords242);
$nextRowid242 = static fn (): array => SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext::rowidParentKeyRows242($nextRecords242, 'next');
$shadowRowid242 = static fn (): array => SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext::rowidParentKeyRows242($shadowRecords242);
$currentPageRowid242 = static fn (): array => array_values(array_filter(
    $page242()['rows'],
    static fn (array $row): bool => ($row['kind'] ?? null) === 'foreign_key_parent_rowid_alias'
        && ($row['phase'] ?? null) === 'current'
        && array_key_exists('rowid_alias', $row),
));

$cases242 = [
    'status ok' => [$default242, 'status', 'ok'],
    'operation marker' => [$default242, 'operation', 'pragma-index-xinfo-foreignkey-current-source-next242'],
    'source id length' => [static fn (): array => ['len' => strlen($page242()['source_id'])], 'len', 64],
    'offset default' => [$default242, 'offset', 0],
    'limit default' => [$default242, 'limit', 220],
    'dependency appended' => [$default242, 'dependencies.13', 'sqlite-pragma-foreign-key-rowid-parent-alias-rejection'],
    'base auxiliary retained' => [$default242, 'current.foreign_key_parent_auxiliary_index.rows', 3],
    'rowid source current' => [$default242, 'current_source.foreign_key_parent_rowid_alias_source', 'pragma_foreign_key_list_parent_columns_plus_pragma_index_xinfo_rowid_auxiliary_rejection'],
    'rowid source next' => [$default242, 'next_source.foreign_key_parent_rowid_alias_source', 'pragma_foreign_key_list_parent_columns_plus_pragma_index_xinfo_rowid_auxiliary_rejection'],
    'current rowid rows' => [$default242, 'current.foreign_key_parent_rowid_alias.rows', 3],
    'current blockers' => [$default242, 'current.foreign_key_parent_rowid_alias.rowid_alias_parent_key', 3],
    'current declared ok zero' => [$default242, 'current.foreign_key_parent_rowid_alias.ok_declared_parent_column', 0],
    'current aux indexes' => [$default242, 'current.foreign_key_parent_rowid_alias.rowid_auxiliary_indexes', 1],
    'current aux rows counted per fk' => [$default242, 'current.foreign_key_parent_rowid_alias.rowid_auxiliary_rows', 3],
    'next rowid rows cleared' => [$default242, 'next_counts.foreign_key_parent_rowid_alias.rows', 0],
    'next blockers cleared' => [$default242, 'next_counts.foreign_key_parent_rowid_alias.rowid_alias_parent_key', 0],
    'delta rows decreased' => [$default242, 'delta.foreign_key_parent_rowid_alias_rows', -3],
    'delta blockers decreased' => [$default242, 'delta.foreign_key_parent_rowid_alias_blockers', -3],
    'delta repaired true' => [$default242, 'delta.foreign_key_parent_rowid_alias_repaired', true],
    'delta changed true' => [$default242, 'delta.foreign_key_parent_rowid_alias_changed', true],
    'current summary rowid' => [$default242, 'current_source.foreign_key_parent_rowid_alias.0', 'current:wp_import_meta#0.0:parent_row->wp_import_parent.rowid:alias=rowid:aux=sqlite_autoindex_wp_import_parent_1:rowid_alias_parent_key'],
    'current summary oid' => [$default242, 'current_source.foreign_key_parent_rowid_alias.1', 'current:wp_import_meta#1.0:parent_oid->wp_import_parent.oid:alias=oid:aux=sqlite_autoindex_wp_import_parent_1:rowid_alias_parent_key'],
    'current summary hidden rowid' => [$default242, 'current_source.foreign_key_parent_rowid_alias.2', 'current:wp_import_meta#2.0:parent_hidden->wp_import_parent._rowid_:alias=_rowid_:aux=sqlite_autoindex_wp_import_parent_1:rowid_alias_parent_key'],
    'current first row kind' => [$currentPageRowid242, '0.kind', 'foreign_key_parent_rowid_alias'],
    'current first row status' => [$currentPageRowid242, '0.status', 'rowid_alias_parent_key'],
    'current first row alias' => [$currentPageRowid242, '0.rowid_alias', 'rowid'],
    'current first row aux index' => [$currentPageRowid242, '0.rowid_auxiliary_index', 'sqlite_autoindex_wp_import_parent_1'],
    'current first row aux columns' => [$currentPageRowid242, '0.rowid_auxiliary_columns', ['rowid']],
    'current first row aux cids' => [$currentPageRowid242, '0.rowid_auxiliary_cids', [-1]],
    'current second row alias' => [$currentPageRowid242, '1.rowid_alias', 'oid'],
    'current third row alias' => [$currentPageRowid242, '2.rowid_alias', '_rowid_'],
    'blocked next rows remain' => [$blocked242, 'next_counts.foreign_key_parent_rowid_alias.rows', 2],
    'blocked next blockers remain' => [$blocked242, 'next_counts.foreign_key_parent_rowid_alias.rowid_alias_parent_key', 2],
    'blocked repaired false' => [$blocked242, 'delta.foreign_key_parent_rowid_alias_repaired', false],
    'helper current first status' => [$currentRowid242, '0.status', 'rowid_alias_parent_key'],
    'helper current second to' => [$currentRowid242, '1.to', 'oid'],
    'helper current third message' => [$currentRowid242, '2.message', 'foreign key wp_import_meta->wp_import_parent references rowid alias _rowid_; PRAGMA index_xinfo rowid auxiliary rows are not named parent-key columns'],
    'helper next empty' => [static fn (): array => ['count' => count($nextRowid242())], 'count', 0],
    'helper declared rowid column ok' => [$shadowRowid242, '0.status', 'ok_declared_parent_column'],
    'helper declared column flag' => [$shadowRowid242, '0.parent_declares_column', true],
];

$tests = [];
foreach ($cases242 as $name => [$factory, $path, $expected]) {
    $tests['pragma index xinfo foreignkey rowid parent alias current source next242 ' . $name] = static function (TestRunner $t) use ($factory, $path, $expected, $valueAt242): void {
        $t->same($expected, $valueAt242($factory(), $path));
    };
}

$tests['pragma index xinfo foreignkey rowid parent alias current source next242 paginates appended rows'] = static function (TestRunner $t) use ($page242): void {
    $full = $page242();
    $baseCount = $full['total'] - 3;
    $first = $page242(0, $baseCount);
    $second = $page242($baseCount, 2, $first['next']);
    $third = $page242($baseCount + 2, 2, $second['next']);

    $t->same($baseCount, $first['count']);
    $t->same('foreign_key_parent_rowid_alias', $first['next_row']['kind']);
    $t->same(['source_id' => $first['source_id'], 'offset' => $baseCount], $first['next']);
    $t->same('current', $second['rows'][0]['phase']);
    $t->same('rowid_alias_parent_key', $second['rows'][1]['status']);
    $t->same('current', $third['rows'][0]['phase']);
    $t->same('_rowid_', $third['rows'][0]['rowid_alias']);
    $t->same(null, $third['next']);
};

$tests['pragma index xinfo foreignkey rowid parent alias current source next242 reports no auxiliary row when parent has no unique index'] = static function (TestRunner $t) use ($record242): void {
    $records = [
        $record242('table', 'parent', 'parent', 2, 'CREATE TABLE parent(name TEXT)', 1),
        $record242('table', 'child', 'child', 3, 'CREATE TABLE child(parent_row INTEGER REFERENCES parent(rowid))', 2),
    ];

    $rows = SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext::rowidParentKeyRows242($records);
    $t->same(1, count($rows));
    $t->same('rowid_alias_parent_key', $rows[0]['status']);
    $t->same(null, $rows[0]['rowid_auxiliary_index']);
    $t->same([], $rows[0]['rowid_auxiliary_columns']);
};

$tests['pragma index xinfo foreignkey rowid parent alias current source next242 accepts quoted declared oid column'] = static function (TestRunner $t) use ($record242): void {
    $records = [
        $record242('table', 'parent', 'parent', 2, 'CREATE TABLE parent("oid" INTEGER NOT NULL UNIQUE, slug TEXT)', 1),
        $record242('index', 'sqlite_autoindex_parent_1', 'parent', 3, null, 2),
        $record242('table', 'child', 'child', 4, 'CREATE TABLE child(parent_oid INTEGER, FOREIGN KEY(parent_oid) REFERENCES parent("oid"))', 3),
    ];

    $rows = SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext::rowidParentKeyRows242($records);
    $t->same(1, count($rows));
    $t->same('ok_declared_parent_column', $rows[0]['status']);
    $t->same(true, $rows[0]['parent_declares_column']);
};

$tests['pragma index xinfo foreignkey rowid parent alias current source next242 rejects stale cursor'] = static function (TestRunner $t) use ($page242, $blockedNextRecords242): void {
    $full = $page242();
    $first = $page242(0, $full['total'] - 3);

    $t->throws(InvalidArgumentException::class, static fn (): array => $page242($full['total'] - 3, 2, $first['next'], $blockedNextRecords242));
};

$tests['pragma index xinfo foreignkey rowid parent alias current source next242 rejects stale offset'] = static function (TestRunner $t) use ($page242): void {
    $full = $page242();
    $first = $page242(0, $full['total'] - 3);

    $t->throws(InvalidArgumentException::class, static fn (): array => $page242($full['total'] - 2, 2, $first['next']));
};

$tests['pragma index xinfo foreignkey rowid parent alias current source next242 rejects invalid records'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn (): array => SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext::rowidParentKeyRows242([['bad' => true]]));
};

$tests['pragma index xinfo foreignkey rowid parent alias current source next242 rejects invalid bounds'] = static function (TestRunner $t) use ($page242): void {
    $t->throws(InvalidArgumentException::class, static fn (): array => $page242(-1, 10));
    $t->throws(InvalidArgumentException::class, static fn (): array => $page242(0, 0));
};

return $tests;
