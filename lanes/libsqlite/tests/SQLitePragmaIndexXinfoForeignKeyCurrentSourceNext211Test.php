<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext;
use PortLibs\LibSqlite\SQLiteSchemaRecord;

$record211 = static fn (string $type, string $name, string $table, ?int $root, ?string $sql, int $rowId): SQLiteSchemaRecord => new SQLiteSchemaRecord($type, $name, $table, $root, $sql, $rowId);

$currentRecords211 = [
    $record211('table', 'wp_terms', 'wp_terms', 2, 'CREATE TABLE wp_terms(term_id INTEGER PRIMARY KEY, slug TEXT COLLATE NOCASE NOT NULL)', 1),
    $record211('table', 'wp_option_keys', 'wp_option_keys', 3, 'CREATE TABLE wp_option_keys(blog_id INTEGER NOT NULL, option_name TEXT COLLATE NOCASE NOT NULL, PRIMARY KEY(blog_id, option_name)) WITHOUT ROWID', 2),
    $record211('index', 'sqlite_autoindex_wp_option_keys_1', 'wp_option_keys', 4, null, 3),
    $record211('table', 'wp_option_import', 'wp_option_import', 5, "CREATE TABLE wp_option_import(
        option_id INTEGER PRIMARY KEY,
        term_id INTEGER NOT NULL REFERENCES wp_terms,
        option_name TEXT REFERENCES wp_option_keys,
        blog_id INTEGER,
        FOREIGN KEY(blog_id, option_name) REFERENCES wp_option_keys
    )", 4),
    $record211('index', 'wp_option_import_lookup', 'wp_option_import', 6, 'CREATE INDEX wp_option_import_lookup ON wp_option_import(term_id, blog_id, option_name)', 5),
];

$nextRecords211 = [
    $currentRecords211[0],
    $currentRecords211[1],
    $currentRecords211[2],
    $record211('table', 'wp_option_import', 'wp_option_import', 5, "CREATE TABLE wp_option_import(
        option_id INTEGER PRIMARY KEY,
        term_id INTEGER NOT NULL REFERENCES wp_terms,
        option_name TEXT NOT NULL REFERENCES wp_option_keys,
        blog_id INTEGER NOT NULL,
        FOREIGN KEY(blog_id, option_name) REFERENCES wp_option_keys
    )", 4),
    $currentRecords211[4],
];

$page211 = static fn (
    int $offset = 0,
    int $limit = 90,
    ?array $resume = null,
    ?array $nextRecords = null,
): array => SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext::page211(
    $currentRecords211,
    $nextRecords ?? $nextRecords211,
    'PRAGMA main.index_xinfo(wp_option_import_lookup)',
    'PRAGMA main.foreign_key_list(wp_option_import)',
    $offset,
    $limit,
    $resume,
);

$valueAt211 = static function (mixed $value, string $path): mixed {
    foreach (explode('.', $path) as $part) {
        if (is_array($value) && array_key_exists($part, $value)) {
            $value = $value[$part];
            continue;
        }
        $value = $value[(int) $part];
    }

    return $value;
};

$default211 = static fn (): array => $page211();
$blocked211 = static fn (): array => $page211(nextRecords: $currentRecords211);
$rows211 = static fn (): array => SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext::childNullabilityRows211($currentRecords211);
$nextRows211 = static fn (): array => SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext::childNullabilityRows211($nextRecords211, 'next');

$cases211 = [
    'status ok' => [$default211, 'status', 'ok'],
    'operation marker' => [$default211, 'operation', 'pragma-index-xinfo-foreignkey-current-source-next211'],
    'base operation replaced' => [$default211, 'current_source.index_xinfo_sql', 'pragma main.index_xinfo("wp_option_import_lookup")'],
    'dependency appended' => [$default211, 'dependencies.6', 'sqlite-pragma-foreign-key-child-nullability'],
    'source current label' => [$default211, 'current_source.foreign_key_child_nullability_source', 'pragma_foreign_key_list_child_groups_plus_pragma_table_info_notnull'],
    'source next label' => [$default211, 'next_source.foreign_key_child_nullability_source', 'pragma_foreign_key_list_child_groups_plus_pragma_table_info_notnull'],
    'current summary not null row' => [$default211, 'current_source.foreign_key_child_nullability.0', 'current:wp_option_import#0->wp_terms:child=term_id:nullable=:all_not_null_child_key'],
    'current summary nullable single row' => [$default211, 'current_source.foreign_key_child_nullability.1', 'current:wp_option_import#1->wp_option_keys:child=option_name:nullable=option_name:nullable_child_key'],
    'current summary nullable composite row' => [$default211, 'current_source.foreign_key_child_nullability.2', 'current:wp_option_import#2->wp_option_keys:child=blog_id,option_name:nullable=blog_id,option_name:nullable_child_key'],
    'next summary composite repaired' => [$default211, 'next_source.foreign_key_child_nullability.2', 'next:wp_option_import#2->wp_option_keys:child=blog_id,option_name:nullable=:all_not_null_child_key'],
    'current rows' => [$default211, 'current.foreign_key_child_nullability.rows', 3],
    'current nullable groups' => [$default211, 'current.foreign_key_child_nullability.nullable_child_key', 2],
    'current all not null groups' => [$default211, 'current.foreign_key_child_nullability.all_not_null_child_key', 1],
    'current single groups' => [$default211, 'current.foreign_key_child_nullability.single_column', 2],
    'current composite groups' => [$default211, 'current.foreign_key_child_nullability.composite', 1],
    'current nullable columns' => [$default211, 'current.foreign_key_child_nullability.nullable_columns', 3],
    'current not null columns' => [$default211, 'current.foreign_key_child_nullability.not_null_columns', 1],
    'next rows' => [$default211, 'next_counts.foreign_key_child_nullability.rows', 3],
    'next nullable cleared' => [$default211, 'next_counts.foreign_key_child_nullability.nullable_child_key', 0],
    'next all not null groups' => [$default211, 'next_counts.foreign_key_child_nullability.all_not_null_child_key', 3],
    'next nullable columns cleared' => [$default211, 'next_counts.foreign_key_child_nullability.nullable_columns', 0],
    'next not null columns' => [$default211, 'next_counts.foreign_key_child_nullability.not_null_columns', 4],
    'delta rows unchanged' => [$default211, 'delta.foreign_key_child_nullability_rows', 0],
    'delta nullable repaired' => [$default211, 'delta.foreign_key_child_nullability_nullable', -2],
    'delta all not null increased' => [$default211, 'delta.foreign_key_child_nullability_all_not_null', 2],
    'delta repaired true' => [$default211, 'delta.foreign_key_child_nullability_repaired', true],
    'delta changed true' => [$default211, 'delta.foreign_key_child_nullability_changed', true],
    'total includes nullability rows' => [$default211, 'total', 40],
    'count default' => [$default211, 'count', 40],
    'next null complete' => [$default211, 'next', null],
    'first appended kind' => [$default211, 'rows.34.kind', 'foreign_key_child_nullability'],
    'first appended phase' => [$default211, 'rows.34.phase', 'current'],
    'first appended status' => [$default211, 'rows.34.status', 'all_not_null_child_key'],
    'first appended child' => [$default211, 'rows.34.child_columns.0', 'term_id'],
    'first appended nullable count' => [$default211, 'rows.34.nullable_count', 0],
    'second appended status' => [$default211, 'rows.35.status', 'nullable_child_key'],
    'second appended nullable column' => [$default211, 'rows.35.nullable_columns.0', 'option_name'],
    'third appended status' => [$default211, 'rows.36.status', 'nullable_child_key'],
    'third appended nullable first' => [$default211, 'rows.36.nullable_columns.0', 'blog_id'],
    'third appended nullable second' => [$default211, 'rows.36.nullable_columns.1', 'option_name'],
    'next appended phase' => [$default211, 'rows.37.phase', 'next'],
    'next appended not null status' => [$default211, 'rows.39.status', 'all_not_null_child_key'],
    'blocked nullable retained' => [$blocked211, 'next_counts.foreign_key_child_nullability.nullable_child_key', 2],
    'blocked repaired false' => [$blocked211, 'delta.foreign_key_child_nullability_repaired', false],
    'helper first status' => [$rows211, '0.status', 'all_not_null_child_key'],
    'helper second nullable' => [$rows211, '1.nullable_columns.0', 'option_name'],
    'helper third nullable count' => [$rows211, '2.nullable_count', 2],
    'helper next phase' => [$nextRows211, '0.phase', 'next'],
    'helper next nullable cleared' => [$nextRows211, '2.nullable_count', 0],
];

$tests = [];
foreach ($cases211 as $name => [$factory, $path, $expected]) {
    $tests['pragma index xinfo foreignkey child nullability current source next211 ' . $name] = static function (TestRunner $t) use ($factory, $path, $expected, $valueAt211): void {
        $t->same($expected, $valueAt211($factory(), $path));
    };
}

$tests['pragma index xinfo foreignkey child nullability current source next211 paginates child nullability rows'] = static function (TestRunner $t) use ($page211): void {
    $first = $page211(0, 34);
    $second = $page211(34, 3, $first['next']);
    $third = $page211(37, 3, $second['next']);

    $t->same(34, $first['count']);
    $t->same('foreign_key_child_nullability', $first['next_row']['kind']);
    $t->same(['source_id' => $first['source_id'], 'offset' => 34], $first['next']);
    $t->same('current', $second['rows'][0]['phase']);
    $t->same('nullable_child_key', $second['rows'][1]['status']);
    $t->same('next', $third['rows'][0]['phase']);
    $t->same(null, $third['next']);
};

$tests['pragma index xinfo foreignkey child nullability current source next211 rejects stale cursor'] = static function (TestRunner $t) use ($page211, $currentRecords211): void {
    $first = $page211(0, 30);

    $t->throws(InvalidArgumentException::class, static fn (): array => $page211(30, 3, $first['next'], $currentRecords211));
};

$tests['pragma index xinfo foreignkey child nullability current source next211 rejects stale offset'] = static function (TestRunner $t) use ($page211): void {
    $first = $page211(0, 30);

    $t->throws(InvalidArgumentException::class, static fn (): array => $page211(31, 3, $first['next']));
};

$tests['pragma index xinfo foreignkey child nullability current source next211 treats integer primary key child as not null'] = static function (TestRunner $t) use ($record211): void {
    $records = [
        $record211('table', 'parent', 'parent', 2, 'CREATE TABLE parent(id INTEGER PRIMARY KEY)', 1),
        $record211('table', 'child', 'child', 3, 'CREATE TABLE child(id INTEGER PRIMARY KEY REFERENCES parent(id))', 2),
    ];

    $rows = SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext::childNullabilityRows211($records);
    $t->same('all_not_null_child_key', $rows[0]['status']);
    $t->same(['id'], $rows[0]['not_null_columns']);
};

$tests['pragma index xinfo foreignkey child nullability current source next211 rejects invalid records'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn (): array => SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext::childNullabilityRows211([['bad' => true]]));
};

$tests['pragma index xinfo foreignkey child nullability current source next211 rejects invalid bounds'] = static function (TestRunner $t) use ($page211): void {
    $t->throws(InvalidArgumentException::class, static fn (): array => $page211(-1, 10));
    $t->throws(InvalidArgumentException::class, static fn (): array => $page211(0, 0));
};

return $tests;
