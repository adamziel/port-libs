<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext;
use PortLibs\LibSqlite\SQLiteSchemaRecord;

$record192 = static fn (string $type, string $name, string $table, int $root, string $sql, int $rowid): SQLiteSchemaRecord => new SQLiteSchemaRecord($type, $name, $table, $root, $sql, $rowid);

$currentRecords192 = [
    $record192('table', 'wp_terms', 'wp_terms', 4, 'CREATE TABLE wp_terms(term_id INTEGER PRIMARY KEY, slug TEXT COLLATE NOCASE, taxonomy TEXT COLLATE RTRIM)', 1),
    $record192('table', 'wp_termmeta', 'wp_termmeta', 5, 'CREATE TABLE wp_termmeta(meta_id INTEGER PRIMARY KEY, term_slug TEXT COLLATE NOCASE, taxonomy TEXT COLLATE RTRIM, meta_key TEXT, FOREIGN KEY(term_slug, taxonomy) REFERENCES wp_terms(slug, taxonomy))', 2),
    $record192('index', 'wp_terms_slug_taxonomy_unique', 'wp_terms', 6, 'CREATE UNIQUE INDEX wp_terms_slug_taxonomy_unique ON wp_terms(slug COLLATE BINARY, taxonomy COLLATE RTRIM)', 3),
    $record192('index', 'wp_termmeta_term_lookup', 'wp_termmeta', 7, 'CREATE INDEX wp_termmeta_term_lookup ON wp_termmeta(term_slug COLLATE NOCASE, taxonomy COLLATE RTRIM)', 4),
];
$nextRecords192 = [
    $currentRecords192[0],
    $currentRecords192[1],
    $record192('index', 'wp_terms_slug_taxonomy_unique', 'wp_terms', 6, 'CREATE UNIQUE INDEX wp_terms_slug_taxonomy_unique ON wp_terms(slug COLLATE NOCASE, taxonomy COLLATE RTRIM)', 3),
    $currentRecords192[3],
];
$currentTables192 = [
    'wp_terms' => [
        ['rowid' => 1, 'term_id' => 1, 'slug' => 'News', 'taxonomy' => 'category'],
    ],
    'wp_termmeta' => [
        ['rowid' => 1, 'meta_id' => 1, 'term_slug' => 'news', 'taxonomy' => 'category', 'meta_key' => '_wp_attachment_metadata'],
    ],
];
$nextTables192 = $currentTables192;

$page192 = static fn (
    int $offset = 0,
    int $limit = 192,
    ?array $cursor = null,
    ?array $nextRecords = null,
    string $indexSql = 'PRAGMA index_xinfo(wp_terms_slug_taxonomy_unique)',
    bool $tableValued = false,
): array => SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext::currentNextPageFromCatalog192(
    $currentRecords192,
    $currentTables192,
    $nextRecords ?? $nextRecords192,
    $nextTables192,
    $indexSql,
    $offset,
    $limit,
    $cursor,
    $tableValued,
);

$valueAt192 = static function (mixed $value, string $path): mixed {
    foreach (explode('.', $path) as $part) {
        if (is_array($value) && array_key_exists($part, $value)) {
            $value = $value[$part];
            continue;
        }
        $value = $value[(int) $part];
    }

    return $value;
};

$default192 = static fn (): array => $page192();
$blocked192 = static fn (): array => $page192(nextRecords: $currentRecords192);
$rejected192 = static fn (): array => SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext::rejectedParentCollationRows192($currentRecords192);
$nextRejected192 = static fn (): array => SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext::rejectedParentCollationRows192($nextRecords192, 'next');
$tableValued192 = static fn (): array => $page192(indexSql: "pragma_index_xinfo('wp_terms_slug_taxonomy_unique')", tableValued: true);

$cases192 = [
    'status ok after parent collation repair' => [$default192, 'status', 'ok'],
    'default limit' => [$default192, 'limit', 192],
    'complete true' => [$default192, 'complete', true],
    'next null' => [$default192, 'next', null],
    'next ready true' => [$default192, 'next_state.ready', true],
    'next blocking empty' => [$default192, 'next_state.blocking', []],
    'source id length' => [static fn (): array => ['len' => strlen($page192()['source_id'])], 'len', 64],
    'source label current' => [$default192, 'current_source.foreign_key_rejected_parent_collation_source', 'create_table_column_collate_and_pragma_index_xinfo_parent_unique'],
    'source label next' => [$default192, 'next_source.foreign_key_rejected_parent_collation_source', 'create_table_column_collate_and_pragma_index_xinfo_parent_unique'],
    'current summary first column mismatch' => [$default192, 'current_source.foreign_key_rejected_parent_collations.0', 'current:wp_termmeta#0.0->wp_terms.slug:wp_terms_slug_taxonomy_unique:NOCASE!=BINARY'],
    'current summary second column matched' => [$default192, 'current_source.foreign_key_rejected_parent_collations.1', 'current:wp_termmeta#0.1->wp_terms.taxonomy:wp_terms_slug_taxonomy_unique:RTRIM!=RTRIM'],
    'next summary empty' => [$default192, 'next_source.foreign_key_rejected_parent_collations', []],
    'current collation rows' => [$default192, 'current.foreign_key_rejected_parent_collations.rows', 2],
    'current mismatch count' => [$default192, 'current.foreign_key_rejected_parent_collations.mismatch', 1],
    'current matched columns' => [$default192, 'current.foreign_key_rejected_parent_collations.matched_columns', 1],
    'current nocase expected' => [$default192, 'current.foreign_key_rejected_parent_collations.nocase_expected', 1],
    'current rtrim expected' => [$default192, 'current.foreign_key_rejected_parent_collations.rtrim_expected', 1],
    'current binary actual' => [$default192, 'current.foreign_key_rejected_parent_collations.binary_actual', 1],
    'next collation rows cleared' => [$default192, 'next_counts.foreign_key_rejected_parent_collations.rows', 0],
    'next mismatch cleared' => [$default192, 'next_counts.foreign_key_rejected_parent_collations.mismatch', 0],
    'delta rows cleared' => [$default192, 'delta.foreign_key_rejected_parent_collation_rows', -2],
    'delta mismatch repaired' => [$default192, 'delta.foreign_key_rejected_parent_collation_mismatches', -1],
    'delta repaired true' => [$default192, 'delta.foreign_key_rejected_parent_collation_repaired', true],
    'delta changed true' => [$default192, 'delta.foreign_key_rejected_parent_collation_changed', true],
    'decorates missing parent key index' => [$default192, 'rows.12.rejected_parent_unique_index', 'wp_terms_slug_taxonomy_unique'],
    'decorates missing parent key reason' => [$default192, 'rows.12.rejected_parent_unique_reason', 'parent_collation_mismatch'],
    'first appended row kind' => [$default192, 'rows.24.kind', 'foreign_key_rejected_parent_collation'],
    'first appended row status' => [$default192, 'rows.24.status', 'rejected'],
    'first appended row reason' => [$default192, 'rows.24.reason', 'parent_collation_mismatch'],
    'first appended row index' => [$default192, 'rows.24.index', 'wp_terms_slug_taxonomy_unique'],
    'first appended row from' => [$default192, 'rows.24.from', 'term_slug'],
    'first appended row to' => [$default192, 'rows.24.to', 'slug'],
    'first appended expected nocase' => [$default192, 'rows.24.parent_column_collation', 'NOCASE'],
    'first appended actual binary' => [$default192, 'rows.24.index_coll', 'BINARY'],
    'first appended mismatch false' => [$default192, 'rows.24.collation_matches', false],
    'first appended mismatch column' => [$default192, 'rows.24.mismatched_columns.0.column', 'slug'],
    'first appended mismatch expected' => [$default192, 'rows.24.mismatched_columns.0.expected', 'NOCASE'],
    'first appended mismatch actual' => [$default192, 'rows.24.mismatched_columns.0.actual', 'BINARY'],
    'second appended row matched' => [$default192, 'rows.25.collation_matches', true],
    'second appended expected rtrim' => [$default192, 'rows.25.parent_column_collation', 'RTRIM'],
    'second appended actual rtrim' => [$default192, 'rows.25.index_coll', 'RTRIM'],
    'blocked status' => [$blocked192, 'status', 'blocked'],
    'blocked next mismatch remains' => [$blocked192, 'next_counts.foreign_key_rejected_parent_collations.mismatch', 1],
    'blocked has base parent blocker' => [$blocked192, 'next_state.blocking.0', 'foreign_key_parent_unique_index'],
    'blocked has collation blocker' => [$blocked192, 'next_state.blocking.1', 'foreign_key_parent_collation_mismatch'],
    'helper first side' => [$rejected192, '0.side', 'current'],
    'helper first message' => [$rejected192, '0.message', 'foreign key wp_termmeta->wp_terms cannot use UNIQUE index wp_terms_slug_taxonomy_unique because parent column collations do not match'],
    'helper second matched' => [$rejected192, '1.collation_matches', true],
    'helper next rejected empty' => [$nextRejected192, '', []],
    'table valued flag preserved' => [$tableValued192, 'current_source.table_valued_index_xinfo', true],
];

$tests = [];
foreach ($cases192 as $name => [$factory, $path, $expected]) {
    $tests['pragma index xinfo foreignkey parent collation current source next192 ' . $name] = static function (TestRunner $t) use ($factory, $path, $expected, $valueAt192): void {
        $value = $factory();
        if ($path !== '') {
            $value = $valueAt192($value, $path);
        }
        $t->same($expected, $value);
    };
}

$tests['pragma index xinfo foreignkey parent collation current source next192 paginates rejected rows'] = static function (TestRunner $t) use ($page192): void {
    $first = $page192(0, 25);
    $second = $page192(25, 2, $first['next']);

    $t->same(25, $first['count']);
    $t->same(['source_id' => $first['source_id'], 'offset' => 25], $first['next']);
    $t->same('foreign_key_rejected_parent_collation', $second['rows'][0]['kind']);
    $t->same('taxonomy', $second['rows'][0]['to']);
    $t->same(null, $second['next']);
};

$tests['pragma index xinfo foreignkey parent collation current source next192 source changes with collation repair'] = static function (TestRunner $t) use ($page192, $currentRecords192): void {
    $changed = $page192();
    $same = $page192(nextRecords: $currentRecords192);

    $t->same(true, $changed['source_id'] !== $same['source_id']);
    $t->same(true, $changed['delta']['foreign_key_rejected_parent_collation_changed']);
    $t->same(false, $same['delta']['foreign_key_rejected_parent_collation_changed']);
};

$tests['pragma index xinfo foreignkey parent collation current source next192 rejects stale cursor'] = static function (TestRunner $t) use ($page192, $currentRecords192): void {
    $first = $page192(0, 25);

    $t->throws(InvalidArgumentException::class, static fn (): array => $page192(25, 2, $first['next'], nextRecords: $currentRecords192));
};

$tests['pragma index xinfo foreignkey parent collation current source next192 rejects stale offset cursor'] = static function (TestRunner $t) use ($page192): void {
    $first = $page192(0, 25);

    $t->throws(InvalidArgumentException::class, static fn (): array => $page192(26, 2, $first['next']));
};

$tests['pragma index xinfo foreignkey parent collation current source next192 rejects malformed records'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn (): array => SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext::rejectedParentCollationRows192([['bad' => 'record']]));
};

$tests['pragma index xinfo foreignkey parent collation current source next192 rejects negative offset'] = static function (TestRunner $t) use ($page192): void {
    $t->throws(InvalidArgumentException::class, static fn (): array => $page192(offset: -1));
};

$tests['pragma index xinfo foreignkey parent collation current source next192 rejects zero limit'] = static function (TestRunner $t) use ($page192): void {
    $t->throws(InvalidArgumentException::class, static fn (): array => $page192(limit: 0));
};

return $tests;
