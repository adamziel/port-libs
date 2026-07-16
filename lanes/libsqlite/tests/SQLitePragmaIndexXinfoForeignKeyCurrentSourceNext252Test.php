<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext;
use PortLibs\LibSqlite\SQLiteSchemaRecord;

$record252 = static fn (string $type, string $name, string $table, ?int $root, ?string $sql, int $rowId): SQLiteSchemaRecord => new SQLiteSchemaRecord($type, $name, $table, $root, $sql, $rowId);

$currentRecords252 = [
    $record252('table', 'wp_terms', 'wp_terms', 2, 'CREATE TABLE wp_terms(slug_key TEXT PRIMARY KEY, taxonomy_key TEXT UNIQUE)', 1),
    $record252('index', 'sqlite_autoindex_wp_terms_1', 'wp_terms', 3, null, 2),
    $record252('index', 'sqlite_autoindex_wp_terms_2', 'wp_terms', 4, null, 3),
    $record252('table', 'wp_termmeta_import', 'wp_termmeta_import', 5, "CREATE TABLE wp_termmeta_import(
        meta_id INTEGER PRIMARY KEY,
        raw_slug TEXT NOT NULL,
        raw_taxonomy TEXT NOT NULL,
        slug_ref TEXT REFERENCES wp_terms(slug_key),
        FOREIGN KEY(taxonomy_ref) REFERENCES wp_terms(taxonomy_key),
        FOREIGN KEY(slug_ref, term_group_ref) REFERENCES wp_terms(slug_key, taxonomy_key)
    )", 4),
];

$nextRecords252 = [
    $currentRecords252[0],
    $currentRecords252[1],
    $currentRecords252[2],
    $record252('table', 'wp_termmeta_import', 'wp_termmeta_import', 5, "CREATE TABLE wp_termmeta_import(
        meta_id INTEGER PRIMARY KEY,
        raw_slug TEXT NOT NULL,
        raw_taxonomy TEXT NOT NULL,
        slug_ref TEXT REFERENCES wp_terms(slug_key),
        taxonomy_ref TEXT REFERENCES wp_terms(taxonomy_key),
        term_group_ref TEXT,
        FOREIGN KEY(slug_ref, term_group_ref) REFERENCES wp_terms(slug_key, taxonomy_key)
    )", 4),
];

$blockedNextRecords252 = $currentRecords252;

$page252 = static fn (
    int $offset = 0,
    int $limit = 360,
    ?array $resume = null,
    ?array $nextRecords = null,
): array => SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext::page252(
    $currentRecords252,
    $nextRecords ?? $nextRecords252,
    'PRAGMA main.index_xinfo(sqlite_autoindex_wp_terms_1)',
    'PRAGMA main.foreign_key_list(wp_termmeta_import)',
    $offset,
    $limit,
    $resume,
);

$valueAt252 = static function (mixed $value, string $path): mixed {
    foreach (explode('.', $path) as $part) {
        if (is_array($value) && array_key_exists($part, $value)) {
            $value = $value[$part];
            continue;
        }
        $value = $value[(int) $part];
    }

    return $value;
};

$default252 = static fn (): array => $page252();
$blocked252 = static fn (): array => $page252(nextRecords: $blockedNextRecords252);
$currentRows252 = static fn (): array => SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext::missingChildColumnRows252($currentRecords252);
$nextRows252 = static fn (): array => SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext::missingChildColumnRows252($nextRecords252, 'next');
$currentPageRows252 = static fn (): array => array_values(array_filter(
    $page252()['rows'],
    static fn (array $row): bool => ($row['kind'] ?? null) === 'foreign_key_missing_child_column'
        && ($row['phase'] ?? null) === 'current',
));

$cases252 = [
    'status ok' => [$default252, 'status', 'ok'],
    'operation marker' => [$default252, 'operation', 'pragma-index-xinfo-foreignkey-current-source-next252'],
    'source id length' => [static fn (): array => ['len' => strlen($page252()['source_id'])], 'len', 64],
    'offset default' => [$default252, 'offset', 0],
    'limit default' => [$default252, 'limit', 360],
    'base generated child retained' => [$default252, 'current.foreign_key_generated_child_columns.rows', 0],
    'dependency appended' => [static fn (): array => ['has' => in_array('sqlite-pragma-foreign-key-table-xinfo-missing-child-columns', $page252()['dependencies'], true)], 'has', true],
    'missing source current' => [$default252, 'current_source.foreign_key_missing_child_column_source', 'pragma_foreign_key_list_child_columns_plus_pragma_table_xinfo'],
    'missing source next' => [$default252, 'next_source.foreign_key_missing_child_column_source', 'pragma_foreign_key_list_child_columns_plus_pragma_table_xinfo'],
    'current missing rows' => [$default252, 'current.foreign_key_missing_child_columns.rows', 2],
    'current missing columns' => [$default252, 'current.foreign_key_missing_child_columns.missing_child_column', 2],
    'current missing foreign keys' => [$default252, 'current.foreign_key_missing_child_columns.foreign_keys', 2],
    'current missing tables' => [$default252, 'current.foreign_key_missing_child_columns.tables', 1],
    'next rows cleared' => [$default252, 'next_counts.foreign_key_missing_child_columns.rows', 0],
    'next missing cleared' => [$default252, 'next_counts.foreign_key_missing_child_columns.missing_child_column', 0],
    'delta rows decreased' => [$default252, 'delta.foreign_key_missing_child_rows', -2],
    'delta missing decreased' => [$default252, 'delta.foreign_key_missing_child_columns', -2],
    'delta repaired true' => [$default252, 'delta.foreign_key_missing_child_repaired', true],
    'delta changed true' => [$default252, 'delta.foreign_key_missing_child_changed', true],
    'complete next null' => [$default252, 'next', null],
    'current summary first' => [$default252, 'current_source.foreign_key_missing_child_columns.0', 'current:wp_termmeta_import#1.0:taxonomy_ref->wp_terms.taxonomy_key:available=meta_id,raw_slug,raw_taxonomy,slug_ref:missing_child_column'],
    'current summary second' => [$default252, 'current_source.foreign_key_missing_child_columns.1', 'current:wp_termmeta_import#2.1:term_group_ref->wp_terms.taxonomy_key:available=meta_id,raw_slug,raw_taxonomy,slug_ref:missing_child_column'],
    'first row kind' => [$currentPageRows252, '0.kind', 'foreign_key_missing_child_column'],
    'first row status' => [$currentPageRows252, '0.status', 'missing_child_column'],
    'first row table' => [$currentPageRows252, '0.table', 'wp_termmeta_import'],
    'first row parent' => [$currentPageRows252, '0.parent', 'wp_terms'],
    'first row from' => [$currentPageRows252, '0.from', 'taxonomy_ref'],
    'first row to' => [$currentPageRows252, '0.to', 'taxonomy_key'],
    'first row available count' => [$currentPageRows252, '0.available_child_column_count', 4],
    'first row available first' => [$currentPageRows252, '0.available_child_columns.0', 'meta_id'],
    'second row fk id' => [$currentPageRows252, '1.foreign_key_id', 2],
    'second row seq' => [$currentPageRows252, '1.seq', 1],
    'second row from' => [$currentPageRows252, '1.from', 'term_group_ref'],
    'helper current count' => [static fn (): array => ['count' => count($currentRows252())], 'count', 2],
    'helper current first message' => [$currentRows252, '0.message', 'foreign key wp_termmeta_import.taxonomy_ref is not present in PRAGMA table_xinfo(wp_termmeta_import)'],
    'helper current second message' => [$currentRows252, '1.message', 'foreign key wp_termmeta_import.term_group_ref is not present in PRAGMA table_xinfo(wp_termmeta_import)'],
    'helper next empty' => [static fn (): array => ['count' => count($nextRows252())], 'count', 0],
    'blocked next rows remain' => [$blocked252, 'next_counts.foreign_key_missing_child_columns.rows', 2],
    'blocked next missing remain' => [$blocked252, 'next_counts.foreign_key_missing_child_columns.missing_child_column', 2],
    'blocked repaired false' => [$blocked252, 'delta.foreign_key_missing_child_repaired', false],
    'blocked changed false' => [$blocked252, 'delta.foreign_key_missing_child_changed', false],
];

$tests = [];
foreach ($cases252 as $name => [$factory, $path, $expected]) {
    $tests['pragma index xinfo foreignkey missing child current source next252 ' . $name] = static function (TestRunner $t) use ($factory, $path, $expected, $valueAt252): void {
        $t->same($expected, $valueAt252($factory(), $path));
    };
}

$tests['pragma index xinfo foreignkey missing child current source next252 paginates missing rows'] = static function (TestRunner $t) use ($page252): void {
    $full = $page252();
    $baseCount = $full['total'] - 2;
    $first = $page252(0, $baseCount);
    $second = $page252($baseCount, 1, $first['next']);
    $third = $page252($baseCount + 1, 1, $second['next']);

    $t->same($baseCount, $first['count']);
    $t->same('foreign_key_missing_child_column', $first['next_row']['kind']);
    $t->same(['source_id' => $first['source_id'], 'offset' => $baseCount], $first['next']);
    $t->same('taxonomy_ref', $second['rows'][0]['from']);
    $t->same('term_group_ref', $third['rows'][0]['from']);
    $t->same(null, $third['next']);
};

$tests['pragma index xinfo foreignkey missing child current source next252 ignores visible child columns'] = static function (TestRunner $t) use ($record252): void {
    $records = [
        $record252('table', 'parent', 'parent', 2, 'CREATE TABLE parent(slug TEXT PRIMARY KEY)', 1),
        $record252('index', 'sqlite_autoindex_parent_1', 'parent', 3, null, 2),
        $record252('table', 'child', 'child', 4, 'CREATE TABLE child(slug TEXT REFERENCES parent(slug))', 3),
    ];

    $t->same([], SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext::missingChildColumnRows252($records));
};

$tests['pragma index xinfo foreignkey missing child current source next252 ignores generated child columns'] = static function (TestRunner $t) use ($record252): void {
    $records = [
        $record252('table', 'parent', 'parent', 2, 'CREATE TABLE parent(slug TEXT PRIMARY KEY)', 1),
        $record252('index', 'sqlite_autoindex_parent_1', 'parent', 3, null, 2),
        $record252('table', 'child', 'child', 4, 'CREATE TABLE child(raw_slug TEXT, slug_key TEXT AS (lower(raw_slug)) STORED REFERENCES parent(slug))', 3),
    ];

    $t->same([], SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext::missingChildColumnRows252($records));
};

$tests['pragma index xinfo foreignkey missing child current source next252 rejects stale cursor'] = static function (TestRunner $t) use ($page252, $blockedNextRecords252): void {
    $full = $page252();
    $first = $page252(0, $full['total'] - 2);

    $t->throws(InvalidArgumentException::class, static fn (): array => $page252($full['total'] - 2, 1, $first['next'], $blockedNextRecords252));
};

$tests['pragma index xinfo foreignkey missing child current source next252 rejects stale offset'] = static function (TestRunner $t) use ($page252): void {
    $full = $page252();
    $first = $page252(0, $full['total'] - 2);

    $t->throws(InvalidArgumentException::class, static fn (): array => $page252($full['total'] - 1, 1, $first['next']));
};

$tests['pragma index xinfo foreignkey missing child current source next252 rejects invalid records'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn (): array => SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext::missingChildColumnRows252([['bad' => true]]));
};

$tests['pragma index xinfo foreignkey missing child current source next252 rejects invalid bounds'] = static function (TestRunner $t) use ($page252): void {
    $t->throws(InvalidArgumentException::class, static fn (): array => $page252(-1, 10));
    $t->throws(InvalidArgumentException::class, static fn (): array => $page252(0, 0));
};

return $tests;
