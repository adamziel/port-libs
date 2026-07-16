<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext;
use PortLibs\LibSqlite\SQLiteSchemaRecord;

$record250 = static fn (string $type, string $name, string $table, ?int $root, ?string $sql, int $rowId): SQLiteSchemaRecord => new SQLiteSchemaRecord($type, $name, $table, $root, $sql, $rowId);

$currentRecords250 = [
    $record250('table', 'wp_terms', 'wp_terms', 2, 'CREATE TABLE wp_terms(slug_key TEXT PRIMARY KEY, taxonomy_key TEXT UNIQUE)', 1),
    $record250('index', 'sqlite_autoindex_wp_terms_1', 'wp_terms', 3, null, 2),
    $record250('index', 'sqlite_autoindex_wp_terms_2', 'wp_terms', 4, null, 3),
    $record250('table', 'wp_termmeta_import', 'wp_termmeta_import', 5, "CREATE TABLE wp_termmeta_import(
        meta_id INTEGER PRIMARY KEY,
        raw_slug TEXT NOT NULL,
        raw_taxonomy TEXT NOT NULL,
        slug_ref TEXT GENERATED ALWAYS AS (lower(raw_slug)) VIRTUAL NOT NULL REFERENCES wp_terms(slug_key),
        taxonomy_ref TEXT AS (lower(raw_taxonomy)) STORED,
        FOREIGN KEY(slug_ref, taxonomy_ref) REFERENCES wp_terms(slug_key, taxonomy_key)
    )", 4),
];

$nextRecords250 = [
    $currentRecords250[0],
    $currentRecords250[1],
    $currentRecords250[2],
    $record250('table', 'wp_termmeta_import', 'wp_termmeta_import', 5, "CREATE TABLE wp_termmeta_import(
        meta_id INTEGER PRIMARY KEY,
        raw_slug TEXT NOT NULL,
        raw_taxonomy TEXT NOT NULL,
        slug_ref TEXT NOT NULL REFERENCES wp_terms(slug_key),
        taxonomy_ref TEXT,
        FOREIGN KEY(slug_ref, taxonomy_ref) REFERENCES wp_terms(slug_key, taxonomy_key)
    )", 4),
];

$blockedNextRecords250 = [
    $currentRecords250[0],
    $currentRecords250[1],
    $currentRecords250[2],
    $currentRecords250[3],
];

$page250 = static fn (
    int $offset = 0,
    int $limit = 360,
    ?array $resume = null,
    ?array $nextRecords = null,
): array => SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext::page250(
    $currentRecords250,
    $nextRecords ?? $nextRecords250,
    'PRAGMA main.index_xinfo(sqlite_autoindex_wp_terms_1)',
    'PRAGMA main.foreign_key_list(wp_termmeta_import)',
    $offset,
    $limit,
    $resume,
);

$valueAt250 = static function (mixed $value, string $path): mixed {
    foreach (explode('.', $path) as $part) {
        if (is_array($value) && array_key_exists($part, $value)) {
            $value = $value[$part];
            continue;
        }
        $value = $value[(int) $part];
    }

    return $value;
};

$default250 = static fn (): array => $page250();
$blocked250 = static fn (): array => $page250(nextRecords: $blockedNextRecords250);
$currentRows250 = static fn (): array => SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext::generatedChildColumnRows250($currentRecords250);
$nextRows250 = static fn (): array => SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext::generatedChildColumnRows250($nextRecords250, 'next');
$currentPageRows250 = static fn (): array => array_values(array_filter(
    $page250()['rows'],
    static fn (array $row): bool => ($row['kind'] ?? null) === 'foreign_key_generated_child_column'
        && ($row['phase'] ?? null) === 'current',
));

$cases250 = [
    'status ok' => [$default250, 'status', 'ok'],
    'operation marker' => [$default250, 'operation', 'pragma-index-xinfo-foreignkey-current-source-next250'],
    'source id length' => [static fn (): array => ['len' => strlen($page250()['source_id'])], 'len', 64],
    'offset default' => [$default250, 'offset', 0],
    'limit default' => [$default250, 'limit', 360],
    'base generated parent retained' => [$default250, 'current.foreign_key_generated_parent_columns.rows', 0],
    'dependency appended' => [static fn (): array => ['has' => in_array('sqlite-pragma-foreign-key-table-xinfo-generated-child-columns', $page250()['dependencies'], true)], 'has', true],
    'generated source current' => [$default250, 'current_source.foreign_key_generated_child_column_source', 'pragma_foreign_key_list_child_columns_plus_pragma_table_xinfo_generated_columns'],
    'generated source next' => [$default250, 'next_source.foreign_key_generated_child_column_source', 'pragma_foreign_key_list_child_columns_plus_pragma_table_xinfo_generated_columns'],
    'current generated rows' => [$default250, 'current.foreign_key_generated_child_columns.rows', 3],
    'current generated columns' => [$default250, 'current.foreign_key_generated_child_columns.generated_child_column', 3],
    'current visible generated zero' => [$default250, 'current.foreign_key_generated_child_columns.visible_child_column', 0],
    'current virtual count' => [$default250, 'current.foreign_key_generated_child_columns.virtual', 2],
    'current stored count' => [$default250, 'current.foreign_key_generated_child_columns.stored', 1],
    'current notnull count' => [$default250, 'current.foreign_key_generated_child_columns.notnull', 2],
    'next rows cleared' => [$default250, 'next_counts.foreign_key_generated_child_columns.rows', 0],
    'next generated cleared' => [$default250, 'next_counts.foreign_key_generated_child_columns.generated_child_column', 0],
    'delta rows decreased' => [$default250, 'delta.foreign_key_generated_child_rows', -3],
    'delta generated decreased' => [$default250, 'delta.foreign_key_generated_child_columns', -3],
    'delta repaired true' => [$default250, 'delta.foreign_key_generated_child_repaired', true],
    'delta changed true' => [$default250, 'delta.foreign_key_generated_child_changed', true],
    'complete next null' => [$default250, 'next', null],
    'current summary single' => [$default250, 'current_source.foreign_key_generated_child_columns.0', 'current:wp_termmeta_import#0.0:slug_ref->wp_terms.slug_key:hidden=2:storage=virtual:generated_child_column'],
    'current summary composite first' => [$default250, 'current_source.foreign_key_generated_child_columns.1', 'current:wp_termmeta_import#1.0:slug_ref->wp_terms.slug_key:hidden=2:storage=virtual:generated_child_column'],
    'current summary composite second' => [$default250, 'current_source.foreign_key_generated_child_columns.2', 'current:wp_termmeta_import#1.1:taxonomy_ref->wp_terms.taxonomy_key:hidden=3:storage=stored:generated_child_column'],
    'first row kind' => [$currentPageRows250, '0.kind', 'foreign_key_generated_child_column'],
    'first row status' => [$currentPageRows250, '0.status', 'generated_child_column'],
    'first row hidden' => [$currentPageRows250, '0.child_hidden', 2],
    'first row storage' => [$currentPageRows250, '0.child_generated_storage', 'virtual'],
    'first row notnull' => [$currentPageRows250, '0.child_notnull', 1],
    'first row table info invisible' => [$currentPageRows250, '0.table_info_visible', false],
    'third row storage' => [$currentPageRows250, '2.child_generated_storage', 'stored'],
    'third row nullable' => [$currentPageRows250, '2.child_notnull', 0],
    'helper current count' => [static fn (): array => ['count' => count($currentRows250())], 'count', 3],
    'helper current first message' => [$currentRows250, '0.message', 'foreign key wp_termmeta_import.slug_ref uses generated child column; PRAGMA table_info omits it but table_xinfo exposes hidden code 2'],
    'helper current second seq' => [$currentRows250, '1.seq', 0],
    'helper current third from' => [$currentRows250, '2.from', 'taxonomy_ref'],
    'helper next empty' => [static fn (): array => ['count' => count($nextRows250())], 'count', 0],
    'blocked next rows remain' => [$blocked250, 'next_counts.foreign_key_generated_child_columns.rows', 3],
    'blocked next generated remain' => [$blocked250, 'next_counts.foreign_key_generated_child_columns.generated_child_column', 3],
    'blocked repaired false' => [$blocked250, 'delta.foreign_key_generated_child_repaired', false],
];

$tests = [];
foreach ($cases250 as $name => [$factory, $path, $expected]) {
    $tests['pragma index xinfo foreignkey generated child current source next250 ' . $name] = static function (TestRunner $t) use ($factory, $path, $expected, $valueAt250): void {
        $t->same($expected, $valueAt250($factory(), $path));
    };
}

$tests['pragma index xinfo foreignkey generated child current source next250 paginates generated rows'] = static function (TestRunner $t) use ($page250): void {
    $full = $page250();
    $baseCount = $full['total'] - 3;
    $first = $page250(0, $baseCount);
    $second = $page250($baseCount, 2, $first['next']);
    $third = $page250($baseCount + 2, 2, $second['next']);

    $t->same($baseCount, $first['count']);
    $t->same('foreign_key_generated_child_column', $first['next_row']['kind']);
    $t->same(['source_id' => $first['source_id'], 'offset' => $baseCount], $first['next']);
    $t->same('current', $second['rows'][0]['phase']);
    $t->same('generated_child_column', $second['rows'][1]['status']);
    $t->same('stored', $third['rows'][0]['child_generated_storage']);
    $t->same(null, $third['next']);
};

$tests['pragma index xinfo foreignkey generated child current source next250 ignores visible child columns'] = static function (TestRunner $t) use ($record250): void {
    $records = [
        $record250('table', 'parent', 'parent', 2, 'CREATE TABLE parent(slug TEXT PRIMARY KEY)', 1),
        $record250('index', 'sqlite_autoindex_parent_1', 'parent', 3, null, 2),
        $record250('table', 'child', 'child', 4, 'CREATE TABLE child(slug TEXT REFERENCES parent(slug))', 3),
    ];

    $t->same([], SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext::generatedChildColumnRows250($records));
};

$tests['pragma index xinfo foreignkey generated child current source next250 ignores generated columns not in foreign key list'] = static function (TestRunner $t) use ($record250): void {
    $records = [
        $record250('table', 'parent', 'parent', 2, 'CREATE TABLE parent(slug TEXT PRIMARY KEY)', 1),
        $record250('index', 'sqlite_autoindex_parent_1', 'parent', 3, null, 2),
        $record250('table', 'child', 'child', 4, 'CREATE TABLE child(slug TEXT REFERENCES parent(slug), slug_key TEXT AS (lower(slug)) STORED)', 3),
    ];

    $t->same([], SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext::generatedChildColumnRows250($records));
};

$tests['pragma index xinfo foreignkey generated child current source next250 rejects stale cursor'] = static function (TestRunner $t) use ($page250, $blockedNextRecords250): void {
    $full = $page250();
    $first = $page250(0, $full['total'] - 3);

    $t->throws(InvalidArgumentException::class, static fn (): array => $page250($full['total'] - 3, 2, $first['next'], $blockedNextRecords250));
};

$tests['pragma index xinfo foreignkey generated child current source next250 rejects stale offset'] = static function (TestRunner $t) use ($page250): void {
    $full = $page250();
    $first = $page250(0, $full['total'] - 3);

    $t->throws(InvalidArgumentException::class, static fn (): array => $page250($full['total'] - 2, 2, $first['next']));
};

$tests['pragma index xinfo foreignkey generated child current source next250 rejects invalid records'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn (): array => SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext::generatedChildColumnRows250([['bad' => true]]));
};

$tests['pragma index xinfo foreignkey generated child current source next250 rejects invalid bounds'] = static function (TestRunner $t) use ($page250): void {
    $t->throws(InvalidArgumentException::class, static fn (): array => $page250(-1, 10));
    $t->throws(InvalidArgumentException::class, static fn (): array => $page250(0, 0));
};

return $tests;
