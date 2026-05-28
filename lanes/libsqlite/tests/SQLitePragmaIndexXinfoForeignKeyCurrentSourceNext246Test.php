<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext246;
use PortLibs\LibSqlite\SQLiteSchemaRecord;

$record246 = static fn (string $type, string $name, string $table, ?int $root, ?string $sql, int $rowId): SQLiteSchemaRecord => new SQLiteSchemaRecord($type, $name, $table, $root, $sql, $rowId);

$currentRecords246 = [
    $record246('table', 'wp_terms', 'wp_terms', 2, "CREATE TABLE wp_terms(
        term_id INTEGER PRIMARY KEY,
        slug TEXT NOT NULL,
        taxonomy TEXT NOT NULL,
        slug_key TEXT GENERATED ALWAYS AS (lower(slug)) VIRTUAL NOT NULL,
        taxonomy_key TEXT AS (lower(taxonomy)) STORED,
        UNIQUE(slug_key),
        UNIQUE(slug_key, taxonomy_key)
    )", 1),
    $record246('index', 'sqlite_autoindex_wp_terms_1', 'wp_terms', 3, null, 2),
    $record246('index', 'sqlite_autoindex_wp_terms_2', 'wp_terms', 4, null, 3),
    $record246('table', 'wp_termmeta_import', 'wp_termmeta_import', 5, "CREATE TABLE wp_termmeta_import(
        meta_id INTEGER PRIMARY KEY,
        slug_ref TEXT REFERENCES wp_terms(slug_key),
        taxonomy_ref TEXT,
        FOREIGN KEY(slug_ref, taxonomy_ref) REFERENCES wp_terms(slug_key, taxonomy_key)
    )", 4),
];

$nextRecords246 = [
    $record246('table', 'wp_terms', 'wp_terms', 2, "CREATE TABLE wp_terms(
        term_id INTEGER PRIMARY KEY,
        slug TEXT NOT NULL,
        taxonomy TEXT NOT NULL,
        slug_key TEXT NOT NULL,
        taxonomy_key TEXT,
        UNIQUE(slug_key),
        UNIQUE(slug_key, taxonomy_key)
    )", 1),
    $currentRecords246[1],
    $currentRecords246[2],
    $currentRecords246[3],
];

$blockedNextRecords246 = [
    $currentRecords246[0],
    $currentRecords246[1],
    $currentRecords246[2],
    $currentRecords246[3],
];

$page246 = static fn (
    int $offset = 0,
    int $limit = 320,
    ?array $resume = null,
    ?array $nextRecords = null,
): array => SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext246::page(
    $currentRecords246,
    $nextRecords ?? $nextRecords246,
    'PRAGMA main.index_xinfo(sqlite_autoindex_wp_terms_2)',
    'PRAGMA main.foreign_key_list(wp_termmeta_import)',
    $offset,
    $limit,
    $resume,
);

$valueAt246 = static function (mixed $value, string $path): mixed {
    foreach (explode('.', $path) as $part) {
        if (is_array($value) && array_key_exists($part, $value)) {
            $value = $value[$part];
            continue;
        }
        $value = $value[(int) $part];
    }

    return $value;
};

$default246 = static fn (): array => $page246();
$blocked246 = static fn (): array => $page246(nextRecords: $blockedNextRecords246);
$currentRows246 = static fn (): array => SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext246::generatedParentColumnRows($currentRecords246);
$nextRows246 = static fn (): array => SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext246::generatedParentColumnRows($nextRecords246, 'next');
$currentPageRows246 = static fn (): array => array_values(array_filter(
    $page246()['rows'],
    static fn (array $row): bool => ($row['kind'] ?? null) === 'foreign_key_generated_parent_column'
        && ($row['phase'] ?? null) === 'current',
));

$cases246 = [
    'status ok' => [$default246, 'status', 'ok'],
    'operation marker' => [$default246, 'operation', 'pragma-index-xinfo-foreignkey-current-source-next246'],
    'source id length' => [static fn (): array => ['len' => strlen($page246()['source_id'])], 'len', 64],
    'offset default' => [$default246, 'offset', 0],
    'limit default' => [$default246, 'limit', 320],
    'base affinity retained' => [$default246, 'current.foreign_key_affinity.rows', 3],
    'dependency appended' => [static fn (): array => ['has' => in_array('sqlite-pragma-foreign-key-table-xinfo-generated-parent-columns', $page246()['dependencies'], true)], 'has', true],
    'generated source current' => [$default246, 'current_source.foreign_key_generated_parent_column_source', 'pragma_foreign_key_list_parent_columns_plus_pragma_table_xinfo_generated_columns'],
    'generated source next' => [$default246, 'next_source.foreign_key_generated_parent_column_source', 'pragma_foreign_key_list_parent_columns_plus_pragma_table_xinfo_generated_columns'],
    'current generated rows' => [$default246, 'current.foreign_key_generated_parent_columns.rows', 3],
    'current generated blockers' => [$default246, 'current.foreign_key_generated_parent_columns.generated_parent_column', 3],
    'current visible generated zero' => [$default246, 'current.foreign_key_generated_parent_columns.visible_parent_column', 0],
    'current virtual count' => [$default246, 'current.foreign_key_generated_parent_columns.virtual', 2],
    'current stored count' => [$default246, 'current.foreign_key_generated_parent_columns.stored', 1],
    'current notnull count' => [$default246, 'current.foreign_key_generated_parent_columns.notnull', 2],
    'next rows cleared' => [$default246, 'next_counts.foreign_key_generated_parent_columns.rows', 0],
    'next blockers cleared' => [$default246, 'next_counts.foreign_key_generated_parent_columns.generated_parent_column', 0],
    'delta rows decreased' => [$default246, 'delta.foreign_key_generated_parent_rows', -3],
    'delta blockers decreased' => [$default246, 'delta.foreign_key_generated_parent_blockers', -3],
    'delta repaired true' => [$default246, 'delta.foreign_key_generated_parent_repaired', true],
    'delta changed true' => [$default246, 'delta.foreign_key_generated_parent_changed', true],
    'complete next null' => [$default246, 'next', null],
    'current summary single' => [$default246, 'current_source.foreign_key_generated_parent_columns.0', 'current:wp_termmeta_import#0.0:slug_ref->wp_terms.slug_key:hidden=2:storage=virtual:generated_parent_column'],
    'current summary composite first' => [$default246, 'current_source.foreign_key_generated_parent_columns.1', 'current:wp_termmeta_import#1.0:slug_ref->wp_terms.slug_key:hidden=2:storage=virtual:generated_parent_column'],
    'current summary composite second' => [$default246, 'current_source.foreign_key_generated_parent_columns.2', 'current:wp_termmeta_import#1.1:taxonomy_ref->wp_terms.taxonomy_key:hidden=3:storage=stored:generated_parent_column'],
    'first row kind' => [$currentPageRows246, '0.kind', 'foreign_key_generated_parent_column'],
    'first row status' => [$currentPageRows246, '0.status', 'generated_parent_column'],
    'first row hidden' => [$currentPageRows246, '0.parent_hidden', 2],
    'first row storage' => [$currentPageRows246, '0.parent_generated_storage', 'virtual'],
    'first row notnull' => [$currentPageRows246, '0.parent_notnull', 1],
    'first row table info invisible' => [$currentPageRows246, '0.table_info_visible', false],
    'third row storage' => [$currentPageRows246, '2.parent_generated_storage', 'stored'],
    'third row nullable' => [$currentPageRows246, '2.parent_notnull', 0],
    'helper current count' => [static fn (): array => ['count' => count($currentRows246())], 'count', 3],
    'helper current first message' => [$currentRows246, '0.message', 'foreign key wp_termmeta_import.slug_ref references generated parent column wp_terms.slug_key; PRAGMA table_info omits it but table_xinfo exposes hidden code 2'],
    'helper current second seq' => [$currentRows246, '1.seq', 0],
    'helper current third to' => [$currentRows246, '2.to', 'taxonomy_key'],
    'helper next empty' => [static fn (): array => ['count' => count($nextRows246())], 'count', 0],
    'blocked next rows remain' => [$blocked246, 'next_counts.foreign_key_generated_parent_columns.rows', 3],
    'blocked next blockers remain' => [$blocked246, 'next_counts.foreign_key_generated_parent_columns.generated_parent_column', 3],
    'blocked repaired false' => [$blocked246, 'delta.foreign_key_generated_parent_repaired', false],
];

$tests = [];
foreach ($cases246 as $name => [$factory, $path, $expected]) {
    $tests['pragma index xinfo foreignkey generated parent current source next246 ' . $name] = static function (TestRunner $t) use ($factory, $path, $expected, $valueAt246): void {
        $t->same($expected, $valueAt246($factory(), $path));
    };
}

$tests['pragma index xinfo foreignkey generated parent current source next246 paginates generated rows'] = static function (TestRunner $t) use ($page246): void {
    $full = $page246();
    $baseCount = $full['total'] - 3;
    $first = $page246(0, $baseCount);
    $second = $page246($baseCount, 2, $first['next']);
    $third = $page246($baseCount + 2, 2, $second['next']);

    $t->same($baseCount, $first['count']);
    $t->same('foreign_key_generated_parent_column', $first['next_row']['kind']);
    $t->same(['source_id' => $first['source_id'], 'offset' => $baseCount], $first['next']);
    $t->same('current', $second['rows'][0]['phase']);
    $t->same('generated_parent_column', $second['rows'][1]['status']);
    $t->same('stored', $third['rows'][0]['parent_generated_storage']);
    $t->same(null, $third['next']);
};

$tests['pragma index xinfo foreignkey generated parent current source next246 ignores visible parent columns'] = static function (TestRunner $t) use ($record246): void {
    $records = [
        $record246('table', 'parent', 'parent', 2, 'CREATE TABLE parent(slug TEXT NOT NULL UNIQUE)', 1),
        $record246('index', 'sqlite_autoindex_parent_1', 'parent', 3, null, 2),
        $record246('table', 'child', 'child', 4, 'CREATE TABLE child(slug TEXT REFERENCES parent(slug))', 3),
    ];

    $t->same([], SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext246::generatedParentColumnRows($records));
};

$tests['pragma index xinfo foreignkey generated parent current source next246 ignores expression parent indexes without generated columns'] = static function (TestRunner $t) use ($record246): void {
    $records = [
        $record246('table', 'parent', 'parent', 2, 'CREATE TABLE parent(slug TEXT NOT NULL)', 1),
        $record246('index', 'parent_lower_slug_unique', 'parent', 3, 'CREATE UNIQUE INDEX parent_lower_slug_unique ON parent(lower(slug))', 2),
        $record246('table', 'child', 'child', 4, 'CREATE TABLE child(slug TEXT REFERENCES parent(slug))', 3),
    ];

    $t->same([], SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext246::generatedParentColumnRows($records));
};

$tests['pragma index xinfo foreignkey generated parent current source next246 rejects stale cursor'] = static function (TestRunner $t) use ($page246, $blockedNextRecords246): void {
    $full = $page246();
    $first = $page246(0, $full['total'] - 3);

    $t->throws(InvalidArgumentException::class, static fn (): array => $page246($full['total'] - 3, 2, $first['next'], $blockedNextRecords246));
};

$tests['pragma index xinfo foreignkey generated parent current source next246 rejects stale offset'] = static function (TestRunner $t) use ($page246): void {
    $full = $page246();
    $first = $page246(0, $full['total'] - 3);

    $t->throws(InvalidArgumentException::class, static fn (): array => $page246($full['total'] - 2, 2, $first['next']));
};

$tests['pragma index xinfo foreignkey generated parent current source next246 rejects invalid records'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn (): array => SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext246::generatedParentColumnRows([['bad' => true]]));
};

$tests['pragma index xinfo foreignkey generated parent current source next246 rejects invalid bounds'] = static function (TestRunner $t) use ($page246): void {
    $t->throws(InvalidArgumentException::class, static fn (): array => $page246(-1, 10));
    $t->throws(InvalidArgumentException::class, static fn (): array => $page246(0, 0));
};

return $tests;
