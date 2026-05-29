<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext;
use PortLibs\LibSqlite\SQLiteSchemaRecord;

$record209 = static fn (string $type, string $name, string $table, ?int $root, ?string $sql, int $rowId): SQLiteSchemaRecord => new SQLiteSchemaRecord($type, $name, $table, $root, $sql, $rowId);

$currentRecords209 = [
    $record209('table', 'wp_terms', 'wp_terms', 2, 'CREATE TABLE wp_terms(term_id INTEGER PRIMARY KEY, slug TEXT COLLATE NOCASE NOT NULL)', 1),
    $record209('table', 'wp_option_keys', 'wp_option_keys', 3, 'CREATE TABLE wp_option_keys(blog_id INTEGER NOT NULL, option_name TEXT COLLATE NOCASE NOT NULL, PRIMARY KEY(blog_id, option_name)) WITHOUT ROWID', 2),
    $record209('index', 'sqlite_autoindex_wp_option_keys_1', 'wp_option_keys', 4, null, 3),
    $record209('table', 'wp_option_import', 'wp_option_import', 5, "CREATE TABLE wp_option_import(
        option_id INTEGER PRIMARY KEY,
        term_id INTEGER NOT NULL REFERENCES wp_terms,
        option_name TEXT NOT NULL REFERENCES wp_option_keys,
        blog_id INTEGER NOT NULL,
        FOREIGN KEY(blog_id, option_name) REFERENCES wp_option_keys
    )", 4),
    $record209('index', 'wp_option_import_lookup', 'wp_option_import', 6, 'CREATE INDEX wp_option_import_lookup ON wp_option_import(term_id, blog_id, option_name)', 5),
];

$nextRecords209 = [
    $currentRecords209[0],
    $currentRecords209[1],
    $currentRecords209[2],
    $record209('table', 'wp_option_import', 'wp_option_import', 5, "CREATE TABLE wp_option_import(
        option_id INTEGER PRIMARY KEY,
        term_id INTEGER NOT NULL REFERENCES wp_terms,
        option_name TEXT NOT NULL,
        blog_id INTEGER NOT NULL,
        FOREIGN KEY(blog_id, option_name) REFERENCES wp_option_keys
    )", 4),
    $currentRecords209[4],
];

$badNextRecords209 = [
    $currentRecords209[0],
    $currentRecords209[1],
    $currentRecords209[2],
    $currentRecords209[3],
    $currentRecords209[4],
];

$page209 = static fn (
    int $offset = 0,
    int $limit = 80,
    ?array $resume = null,
    ?array $nextRecords = null,
): array => SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext::page209(
    $currentRecords209,
    $nextRecords ?? $nextRecords209,
    'PRAGMA main.index_xinfo(wp_option_import_lookup)',
    'PRAGMA main.foreign_key_list(wp_option_import)',
    $offset,
    $limit,
    $resume,
);

$valueAt209 = static function (mixed $value, string $path): mixed {
    foreach (explode('.', $path) as $part) {
        if (is_array($value) && array_key_exists($part, $value)) {
            $value = $value[$part];
            continue;
        }
        $value = $value[(int) $part];
    }

    return $value;
};

$default209 = static fn (): array => $page209();
$blocked209 = static fn (): array => $page209(nextRecords: $badNextRecords209);
$implicitCurrent209 = static fn (): array => SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext::implicitParentPrimaryKeyRows209($currentRecords209);
$implicitNext209 = static fn (): array => SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext::implicitParentPrimaryKeyRows209($nextRecords209, 'next');

$cases209 = [
    'status ok' => [$default209, 'status', 'ok'],
    'operation marker' => [$default209, 'operation', 'pragma-index-xinfo-foreignkey-current-source-next209'],
    'normalized index sql retained' => [$default209, 'current_source.index_xinfo_sql', 'pragma main.index_xinfo("wp_option_import_lookup")'],
    'normalized fk sql retained' => [$default209, 'current_source.foreign_key_sql', 'pragma main.foreign_key_list("wp_option_import")'],
    'implicit pk source current' => [$default209, 'current_source.foreign_key_implicit_parent_primary_key_source', 'pragma_foreign_key_list_null_to_plus_pragma_table_info_primary_key_arity'],
    'implicit pk source next' => [$default209, 'next_source.foreign_key_implicit_parent_primary_key_source', 'pragma_foreign_key_list_null_to_plus_pragma_table_info_primary_key_arity'],
    'dependency appended' => [$default209, 'dependencies.5', 'sqlite-pragma-foreign-key-implicit-parent-primary-key-arity'],
    'base rowid retained current rows' => [$default209, 'current.foreign_key_parent_rowid_alias.rows', 3],
    'base coverage retained next rows' => [$default209, 'next_counts.foreign_key_parent_coverage.rows', 2],
    'current implicit rows' => [$default209, 'current.foreign_key_implicit_parent_primary_key.rows', 3],
    'current implicit valid count' => [$default209, 'current.foreign_key_implicit_parent_primary_key.valid_implicit_parent_key', 2],
    'current implicit mismatch count' => [$default209, 'current.foreign_key_implicit_parent_primary_key.arity_mismatch', 1],
    'current implicit missing pk count' => [$default209, 'current.foreign_key_implicit_parent_primary_key.missing_parent_primary_key', 0],
    'current implicit single child count' => [$default209, 'current.foreign_key_implicit_parent_primary_key.single_child', 2],
    'current implicit composite child count' => [$default209, 'current.foreign_key_implicit_parent_primary_key.composite_child', 1],
    'next implicit rows' => [$default209, 'next_counts.foreign_key_implicit_parent_primary_key.rows', 2],
    'next implicit valid count' => [$default209, 'next_counts.foreign_key_implicit_parent_primary_key.valid_implicit_parent_key', 2],
    'next implicit mismatch count' => [$default209, 'next_counts.foreign_key_implicit_parent_primary_key.arity_mismatch', 0],
    'delta implicit row removed' => [$default209, 'delta.foreign_key_implicit_parent_primary_key_rows', -1],
    'delta implicit valid unchanged' => [$default209, 'delta.foreign_key_implicit_parent_primary_key_valid', 0],
    'delta implicit mismatch repaired' => [$default209, 'delta.foreign_key_implicit_parent_primary_key_mismatches', -1],
    'delta implicit repaired true' => [$default209, 'delta.foreign_key_implicit_parent_primary_key_repaired', true],
    'delta implicit changed true' => [$default209, 'delta.foreign_key_implicit_parent_primary_key_changed', true],
    'total includes implicit rows' => [$default209, 'total', 30],
    'count default' => [$default209, 'count', 30],
    'next null complete' => [$default209, 'next', null],
    'current source first summary' => [$default209, 'current_source.foreign_key_implicit_parent_primary_key.0', 'current:wp_option_import#0->wp_terms:child=term_id:parent-pk=term_id:valid_implicit_parent_key'],
    'current source mismatch summary' => [$default209, 'current_source.foreign_key_implicit_parent_primary_key.1', 'current:wp_option_import#1->wp_option_keys:child=option_name:parent-pk=blog_id,option_name:arity_mismatch'],
    'next source composite summary' => [$default209, 'next_source.foreign_key_implicit_parent_primary_key.1', 'next:wp_option_import#1->wp_option_keys:child=blog_id,option_name:parent-pk=blog_id,option_name:valid_implicit_parent_key'],
    'first implicit row kind' => [$default209, 'rows.25.kind', 'foreign_key_implicit_parent_primary_key'],
    'first implicit row phase' => [$default209, 'rows.25.phase', 'current'],
    'first implicit row table' => [$default209, 'rows.25.table', 'wp_option_import'],
    'first implicit row status' => [$default209, 'rows.25.status', 'valid_implicit_parent_key'],
    'first implicit row child count' => [$default209, 'rows.25.child_column_count', 1],
    'first implicit row parent pk count' => [$default209, 'rows.25.parent_primary_key_count', 1],
    'first implicit row parent pk column' => [$default209, 'rows.25.parent_primary_key_columns.0', 'term_id'],
    'mismatch row status' => [$default209, 'rows.26.status', 'arity_mismatch'],
    'mismatch row child column' => [$default209, 'rows.26.child_columns.0', 'option_name'],
    'mismatch row parent first pk' => [$default209, 'rows.26.parent_primary_key_columns.0', 'blog_id'],
    'mismatch row parent second pk' => [$default209, 'rows.26.parent_primary_key_columns.1', 'option_name'],
    'current composite valid row' => [$default209, 'rows.27.status', 'valid_implicit_parent_key'],
    'next first valid row' => [$default209, 'rows.28.status', 'valid_implicit_parent_key'],
    'next second valid row' => [$default209, 'rows.29.status', 'valid_implicit_parent_key'],
    'blocked next missing parent pk' => [$blocked209, 'next_counts.foreign_key_implicit_parent_primary_key.missing_parent_primary_key', 0],
    'blocked next mismatches retained' => [$blocked209, 'next_counts.foreign_key_implicit_parent_primary_key.arity_mismatch', 1],
    'blocked repaired false' => [$blocked209, 'delta.foreign_key_implicit_parent_primary_key_repaired', false],
    'blocked mismatch row status' => [$blocked209, 'rows.29.status', 'arity_mismatch'],
    'helper current kind' => [$implicitCurrent209, '0.kind', 'foreign_key_implicit_parent_primary_key'],
    'helper current valid' => [$implicitCurrent209, '0.status', 'valid_implicit_parent_key'],
    'helper current mismatch' => [$implicitCurrent209, '1.status', 'arity_mismatch'],
    'helper current composite valid' => [$implicitCurrent209, '2.status', 'valid_implicit_parent_key'],
    'helper next phase' => [$implicitNext209, '0.phase', 'next'],
    'helper next valid' => [$implicitNext209, '1.status', 'valid_implicit_parent_key'],
];

$tests = [];
foreach ($cases209 as $name => [$factory, $path, $expected]) {
    $tests['pragma index xinfo foreignkey implicit parent primary key current source next209 ' . $name] = static function (TestRunner $t) use ($factory, $path, $expected, $valueAt209): void {
        $t->same($expected, $valueAt209($factory(), $path));
    };
}

$tests['pragma index xinfo foreignkey implicit parent primary key current source next209 paginates implicit rows'] = static function (TestRunner $t) use ($page209): void {
    $first = $page209(0, 25);
    $second = $page209(25, 3, $first['next']);
    $third = $page209(28, 3, $second['next']);

    $t->same(25, $first['count']);
    $t->same('foreign_key_implicit_parent_primary_key', $first['next_row']['kind']);
    $t->same(['source_id' => $first['source_id'], 'offset' => 25], $first['next']);
    $t->same('current', $second['rows'][0]['phase']);
    $t->same('arity_mismatch', $second['rows'][1]['status']);
    $t->same('next', $third['rows'][0]['phase']);
    $t->same(null, $third['next']);
};

$tests['pragma index xinfo foreignkey implicit parent primary key current source next209 rejects stale cursor'] = static function (TestRunner $t) use ($page209, $badNextRecords209): void {
    $first = $page209(0, 25);

    $t->throws(InvalidArgumentException::class, static fn (): array => $page209(25, 3, $first['next'], $badNextRecords209));
};

$tests['pragma index xinfo foreignkey implicit parent primary key current source next209 rejects stale offset'] = static function (TestRunner $t) use ($page209): void {
    $first = $page209(0, 25);

    $t->throws(InvalidArgumentException::class, static fn (): array => $page209(26, 3, $first['next']));
};

$tests['pragma index xinfo foreignkey implicit parent primary key current source next209 ignores explicit parent columns'] = static function (TestRunner $t) use ($record209): void {
    $records = [
        $record209('table', 'parent', 'parent', 2, 'CREATE TABLE parent(a INTEGER, b INTEGER, UNIQUE(a, b))', 1),
        $record209('index', 'parent_ab', 'parent', 3, 'CREATE UNIQUE INDEX parent_ab ON parent(a, b)', 2),
        $record209('table', 'child', 'child', 4, 'CREATE TABLE child(a INTEGER, b INTEGER, FOREIGN KEY(a, b) REFERENCES parent(a, b))', 3),
    ];

    $t->same([], SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext::implicitParentPrimaryKeyRows209($records));
};

$tests['pragma index xinfo foreignkey implicit parent primary key current source next209 rejects invalid records'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn (): array => SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext::implicitParentPrimaryKeyRows209([['bad' => true]]));
};

$tests['pragma index xinfo foreignkey implicit parent primary key current source next209 rejects invalid bounds'] = static function (TestRunner $t) use ($page209): void {
    $t->throws(InvalidArgumentException::class, static fn (): array => $page209(-1, 10));
    $t->throws(InvalidArgumentException::class, static fn (): array => $page209(0, 0));
};

return $tests;
