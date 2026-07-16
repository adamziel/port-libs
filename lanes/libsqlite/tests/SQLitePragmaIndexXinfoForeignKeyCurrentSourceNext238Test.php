<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext;
use PortLibs\LibSqlite\SQLiteSchemaRecord;

$record238 = static fn (string $type, string $name, string $table, ?int $root, ?string $sql, int $rowId): SQLiteSchemaRecord => new SQLiteSchemaRecord($type, $name, $table, $root, $sql, $rowId);

$currentRecords238 = [
    $record238('table', 'wp_slug_parent', 'wp_slug_parent', 2, 'CREATE TABLE wp_slug_parent(site_id INTEGER NOT NULL, slug TEXT COLLATE NOCASE NOT NULL, locale TEXT NOT NULL)', 1),
    $record238('index', 'wp_slug_parent_site_slug_desc_unique', 'wp_slug_parent', 3, 'CREATE UNIQUE INDEX wp_slug_parent_site_slug_desc_unique ON wp_slug_parent(site_id DESC, slug COLLATE NOCASE DESC)', 2),
    $record238('index', 'wp_slug_parent_slug_desc_unique', 'wp_slug_parent', 4, 'CREATE UNIQUE INDEX wp_slug_parent_slug_desc_unique ON wp_slug_parent(slug COLLATE NOCASE DESC)', 3),
    $record238('index', 'wp_slug_parent_slug_site_desc_unique', 'wp_slug_parent', 5, 'CREATE UNIQUE INDEX wp_slug_parent_slug_site_desc_unique ON wp_slug_parent(slug COLLATE NOCASE DESC, site_id DESC)', 4),
    $record238('table', 'wp_import_slugmeta', 'wp_import_slugmeta', 6, "CREATE TABLE wp_import_slugmeta(
        meta_id INTEGER PRIMARY KEY,
        site_id INTEGER NOT NULL,
        slug TEXT COLLATE NOCASE NOT NULL,
        meta_key TEXT NOT NULL,
        FOREIGN KEY(slug) REFERENCES wp_slug_parent(slug),
        FOREIGN KEY(site_id, slug) REFERENCES wp_slug_parent(site_id, slug) ON DELETE CASCADE
    )", 5),
];

$nextRecords238 = [
    $currentRecords238[0],
    $record238('index', 'wp_slug_parent_site_slug_unique', 'wp_slug_parent', 7, 'CREATE UNIQUE INDEX wp_slug_parent_site_slug_unique ON wp_slug_parent(site_id, slug COLLATE NOCASE)', 6),
    $record238('index', 'wp_slug_parent_slug_unique', 'wp_slug_parent', 8, 'CREATE UNIQUE INDEX wp_slug_parent_slug_unique ON wp_slug_parent(slug COLLATE NOCASE)', 7),
    $currentRecords238[4],
];

$permutedNextRecords238 = [
    $currentRecords238[0],
    $record238('index', 'wp_slug_parent_slug_site_desc_unique', 'wp_slug_parent', 7, 'CREATE UNIQUE INDEX wp_slug_parent_slug_site_desc_unique ON wp_slug_parent(slug COLLATE NOCASE DESC, site_id DESC)', 6),
    $currentRecords238[4],
];

$page238 = static fn (
    int $offset = 0,
    int $limit = 180,
    ?array $resume = null,
    ?array $nextRecords = null,
): array => SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext::page238(
    $currentRecords238,
    $nextRecords ?? $nextRecords238,
    'PRAGMA main.index_xinfo(wp_slug_parent_site_slug_desc_unique)',
    'PRAGMA main.foreign_key_list(wp_import_slugmeta)',
    $offset,
    $limit,
    $resume,
);

$valueAt238 = static function (mixed $value, string $path): mixed {
    foreach (explode('.', $path) as $part) {
        if (is_array($value) && array_key_exists($part, $value)) {
            $value = $value[$part];
            continue;
        }
        $value = $value[(int) $part];
    }

    return $value;
};

$default238 = static fn (): array => $page238();
$permuted238 = static fn (): array => $page238(nextRecords: $permutedNextRecords238);
$currentDescending238 = static fn (): array => SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext::parentDescendingKeyRows238($currentRecords238);
$nextDescending238 = static fn (): array => SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext::parentDescendingKeyRows238($nextRecords238, 'next');

$cases238 = [
    'status ok' => [$default238, 'status', 'ok'],
    'operation marker' => [$default238, 'operation', 'pragma-index-xinfo-foreignkey-current-source-next238'],
    'source id length' => [static fn (): array => ['len' => strlen($page238()['source_id'])], 'len', 64],
    'offset default' => [$default238, 'offset', 0],
    'limit default' => [$default238, 'limit', 180],
    'dependency appended' => [$default238, 'dependencies.12', 'sqlite-pragma-foreign-key-parent-desc-index-admission'],
    'base expression parent retained' => [$default238, 'current.foreign_key_expression_parent_key.rows', 3],
    'descending source current' => [$default238, 'current_source.foreign_key_parent_descending_key_source', 'pragma_foreign_key_list_parent_columns_plus_pragma_index_xinfo_desc_flags'],
    'descending source next' => [$default238, 'next_source.foreign_key_parent_descending_key_source', 'pragma_foreign_key_list_parent_columns_plus_pragma_index_xinfo_desc_flags'],
    'current descending rows' => [$default238, 'current.foreign_key_parent_descending_key.rows', 3],
    'current ok rows' => [$default238, 'current.foreign_key_parent_descending_key.ok', 3],
    'current desc ok rows' => [$default238, 'current.foreign_key_parent_descending_key.ok_desc_parent_unique_index', 3],
    'current blockers zero' => [$default238, 'current.foreign_key_parent_descending_key.blocked', 0],
    'current descending terms' => [$default238, 'current.foreign_key_parent_descending_key.descending_terms', 3],
    'current all descending rows' => [$default238, 'current.foreign_key_parent_descending_key.all_descending_rows', 3],
    'current admissible rows' => [$default238, 'current.foreign_key_parent_descending_key.admissible', 3],
    'next rows' => [$default238, 'next_counts.foreign_key_parent_descending_key.rows', 3],
    'next ok rows' => [$default238, 'next_counts.foreign_key_parent_descending_key.ok', 3],
    'next desc ok zero' => [$default238, 'next_counts.foreign_key_parent_descending_key.ok_desc_parent_unique_index', 0],
    'next descending terms zero' => [$default238, 'next_counts.foreign_key_parent_descending_key.descending_terms', 0],
    'next blockers zero' => [$default238, 'next_counts.foreign_key_parent_descending_key.blocked', 0],
    'delta rows unchanged' => [$default238, 'delta.foreign_key_parent_descending_key_rows', 0],
    'delta blockers unchanged' => [$default238, 'delta.foreign_key_parent_descending_key_blockers', 0],
    'delta repaired false because current admissible' => [$default238, 'delta.foreign_key_parent_descending_key_repaired', false],
    'delta changed true' => [$default238, 'delta.foreign_key_parent_descending_key_changed', true],
    'total includes descending rows' => [$default238, 'total', 55],
    'count complete' => [$default238, 'count', 55],
    'next complete null' => [$default238, 'next', null],
    'current summary single desc' => [$default238, 'current_source.foreign_key_parent_descending_key.0', 'current:wp_import_slugmeta#0.0:slug->wp_slug_parent.slug:index=wp_slug_parent_slug_desc_unique:candidate=:desc=1:ok_desc_parent_unique_index'],
    'current summary composite first desc' => [$default238, 'current_source.foreign_key_parent_descending_key.1', 'current:wp_import_slugmeta#1.0:site_id->wp_slug_parent.site_id:index=wp_slug_parent_site_slug_desc_unique:candidate=:desc=1:ok_desc_parent_unique_index'],
    'current summary composite second desc' => [$default238, 'current_source.foreign_key_parent_descending_key.2', 'current:wp_import_slugmeta#1.1:slug->wp_slug_parent.slug:index=wp_slug_parent_site_slug_desc_unique:candidate=:desc=1:ok_desc_parent_unique_index'],
    'next summary single asc' => [$default238, 'next_source.foreign_key_parent_descending_key.0', 'next:wp_import_slugmeta#0.0:slug->wp_slug_parent.slug:index=wp_slug_parent_slug_unique:candidate=:desc=0:ok'],
    'first appended row kind' => [$default238, 'rows.49.kind', 'foreign_key_parent_descending_key'],
    'first appended row status' => [$default238, 'rows.49.status', 'ok_desc_parent_unique_index'],
    'first appended index' => [$default238, 'rows.49.parent_unique_index', 'wp_slug_parent_slug_desc_unique'],
    'first appended desc flag' => [$default238, 'rows.49.index_column_desc', 1],
    'first appended desc admissible' => [$default238, 'rows.49.desc_admissible', true],
    'composite first index' => [$default238, 'rows.50.parent_unique_index', 'wp_slug_parent_site_slug_desc_unique'],
    'composite first collation' => [$default238, 'rows.50.index_column_collation', 'BINARY'],
    'composite second collation' => [$default238, 'rows.51.index_column_collation', 'NOCASE'],
    'next first status asc ok' => [$default238, 'rows.52.status', 'ok'],
    'next first desc false' => [$default238, 'rows.52.this_term_descending', false],
    'permuted next blockers' => [$permuted238, 'next_counts.foreign_key_parent_descending_key.permuted_desc_parent_unique_index', 2],
    'permuted next blocked total' => [$permuted238, 'next_counts.foreign_key_parent_descending_key.blocked', 3],
    'permuted next ok zero' => [$permuted238, 'next_counts.foreign_key_parent_descending_key.ok', 0],
    'permuted repaired false' => [$permuted238, 'delta.foreign_key_parent_descending_key_repaired', false],
    'helper current first kind' => [$currentDescending238, '0.kind', 'foreign_key_parent_descending_key'],
    'helper current first status' => [$currentDescending238, '0.status', 'ok_desc_parent_unique_index'],
    'helper current first message' => [$currentDescending238, '0.message', 'foreign key wp_import_slugmeta->wp_slug_parent may use descending UNIQUE parent index wp_slug_parent_slug_desc_unique'],
    'helper current first all desc' => [$currentDescending238, '0.all_parent_terms_descending', true],
    'helper current composite second term desc' => [$currentDescending238, '2.this_term_descending', true],
    'helper next first phase' => [$nextDescending238, '0.phase', 'next'],
    'helper next first status' => [$nextDescending238, '0.status', 'ok'],
    'helper next first desc admissible' => [$nextDescending238, '0.desc_admissible', true],
];

$tests = [];
foreach ($cases238 as $name => [$factory, $path, $expected]) {
    $tests['pragma index xinfo foreignkey descending parent key current source next238 ' . $name] = static function (TestRunner $t) use ($factory, $path, $expected, $valueAt238): void {
        $t->same($expected, $valueAt238($factory(), $path));
    };
}

$tests['pragma index xinfo foreignkey descending parent key current source next238 paginates descending rows'] = static function (TestRunner $t) use ($page238): void {
    $first = $page238(0, 49);
    $second = $page238(49, 3, $first['next']);
    $third = $page238(52, 3, $second['next']);

    $t->same(49, $first['count']);
    $t->same('foreign_key_parent_descending_key', $first['next_row']['kind']);
    $t->same(['source_id' => $first['source_id'], 'offset' => 49], $first['next']);
    $t->same('current', $second['rows'][0]['phase']);
    $t->same('ok_desc_parent_unique_index', $second['rows'][2]['status']);
    $t->same('next', $third['rows'][0]['phase']);
    $t->same('ok', $third['rows'][2]['status']);
    $t->same(null, $third['next']);
};

$tests['pragma index xinfo foreignkey descending parent key current source next238 reports permuted desc parent key'] = static function (TestRunner $t) use ($record238): void {
    $records = [
        $record238('table', 'parent', 'parent', 2, 'CREATE TABLE parent(a INTEGER, b INTEGER)', 1),
        $record238('index', 'parent_b_a_desc_unique', 'parent', 3, 'CREATE UNIQUE INDEX parent_b_a_desc_unique ON parent(b DESC, a DESC)', 2),
        $record238('table', 'child', 'child', 4, 'CREATE TABLE child(a INTEGER, b INTEGER, FOREIGN KEY(a,b) REFERENCES parent(a,b))', 3),
    ];

    $rows = SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext::parentDescendingKeyRows238($records);
    $t->same(2, count($rows));
    $t->same('permuted_desc_parent_unique_index', $rows[0]['status']);
    $t->same('parent_b_a_desc_unique', $rows[0]['candidate_index']);
    $t->same(['b', 'a'], $rows[0]['candidate_columns']);
};

$tests['pragma index xinfo foreignkey descending parent key current source next238 ignores partial desc parent key'] = static function (TestRunner $t) use ($record238): void {
    $records = [
        $record238('table', 'parent', 'parent', 2, 'CREATE TABLE parent(code TEXT, active INTEGER)', 1),
        $record238('index', 'parent_code_desc_partial', 'parent', 3, 'CREATE UNIQUE INDEX parent_code_desc_partial ON parent(code DESC) WHERE active = 1', 2),
        $record238('table', 'child', 'child', 4, 'CREATE TABLE child(code TEXT, FOREIGN KEY(code) REFERENCES parent(code))', 3),
    ];

    $rows = SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext::parentDescendingKeyRows238($records);
    $t->same(1, count($rows));
    $t->same('missing_parent_unique_index', $rows[0]['status']);
    $t->same(null, $rows[0]['candidate_index']);
};

$tests['pragma index xinfo foreignkey descending parent key current source next238 accepts mixed descending parent key'] = static function (TestRunner $t) use ($record238): void {
    $records = [
        $record238('table', 'parent', 'parent', 2, 'CREATE TABLE parent(a INTEGER, b INTEGER)', 1),
        $record238('index', 'parent_a_b_mixed_unique', 'parent', 3, 'CREATE UNIQUE INDEX parent_a_b_mixed_unique ON parent(a DESC, b)', 2),
        $record238('table', 'child', 'child', 4, 'CREATE TABLE child(a INTEGER, b INTEGER, FOREIGN KEY(a,b) REFERENCES parent(a,b))', 3),
    ];

    $rows = SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext::parentDescendingKeyRows238($records);
    $t->same(2, count($rows));
    $t->same('ok_desc_parent_unique_index', $rows[0]['status']);
    $t->same(false, $rows[0]['all_parent_terms_descending']);
    $t->same(true, $rows[0]['this_term_descending']);
    $t->same(false, $rows[1]['this_term_descending']);
};

$tests['pragma index xinfo foreignkey descending parent key current source next238 reports implicit primary key without explicit unique index'] = static function (TestRunner $t) use ($record238): void {
    $records = [
        $record238('table', 'parent', 'parent', 2, 'CREATE TABLE parent(id INTEGER PRIMARY KEY)', 1),
        $record238('table', 'child', 'child', 3, 'CREATE TABLE child(parent_id INTEGER REFERENCES parent)', 2),
    ];

    $rows = SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext::parentDescendingKeyRows238($records);
    $t->same(1, count($rows));
    $t->same('missing_parent_unique_index', $rows[0]['status']);
    $t->same('id', $rows[0]['to']);
};

$tests['pragma index xinfo foreignkey descending parent key current source next238 rejects stale cursor'] = static function (TestRunner $t) use ($page238, $permutedNextRecords238): void {
    $first = $page238(0, 49);

    $t->throws(InvalidArgumentException::class, static fn (): array => $page238(49, 3, $first['next'], $permutedNextRecords238));
};

$tests['pragma index xinfo foreignkey descending parent key current source next238 rejects stale offset'] = static function (TestRunner $t) use ($page238): void {
    $first = $page238(0, 49);

    $t->throws(InvalidArgumentException::class, static fn (): array => $page238(57, 3, $first['next']));
};

$tests['pragma index xinfo foreignkey descending parent key current source next238 rejects invalid records'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn (): array => SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext::parentDescendingKeyRows238([['bad' => true]]));
};

$tests['pragma index xinfo foreignkey descending parent key current source next238 rejects invalid bounds'] = static function (TestRunner $t) use ($page238): void {
    $t->throws(InvalidArgumentException::class, static fn (): array => $page238(-1, 10));
    $t->throws(InvalidArgumentException::class, static fn (): array => $page238(0, 0));
};

return $tests;
