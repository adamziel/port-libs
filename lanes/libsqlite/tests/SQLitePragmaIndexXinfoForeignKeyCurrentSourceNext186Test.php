<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext;
use PortLibs\LibSqlite\SQLiteSchemaRecord;

$record186 = static fn (string $type, string $name, string $table, int $root, string $sql, int $rowid): SQLiteSchemaRecord => new SQLiteSchemaRecord($type, $name, $table, $root, $sql, $rowid);

$currentRecords186 = [
    $record186('table', 'wp_terms', 'wp_terms', 4, 'CREATE TABLE wp_terms(term_id INTEGER PRIMARY KEY, slug TEXT COLLATE NOCASE, taxonomy TEXT COLLATE RTRIM, scope TEXT)', 1),
    $record186('table', 'wp_posts', 'wp_posts', 5, 'CREATE TABLE wp_posts(ID INTEGER PRIMARY KEY, post_name TEXT COLLATE NOCASE, post_type TEXT COLLATE RTRIM, post_parent INTEGER)', 2),
    $record186('table', 'wp_term_relationships', 'wp_term_relationships', 6, 'CREATE TABLE wp_term_relationships(object_id INTEGER, term_slug TEXT COLLATE NOCASE, term_taxonomy TEXT COLLATE RTRIM, post_slug TEXT COLLATE NOCASE, post_type TEXT COLLATE RTRIM, missing_slug TEXT COLLATE NOCASE, FOREIGN KEY(term_slug, term_taxonomy) REFERENCES wp_terms(slug, taxonomy), FOREIGN KEY(post_slug, post_type) REFERENCES wp_posts(post_name, post_type), FOREIGN KEY(missing_slug) REFERENCES wp_terms(slug))', 3),
    $record186('index', 'wp_terms_slug_taxonomy_key', 'wp_terms', 7, 'CREATE UNIQUE INDEX wp_terms_slug_taxonomy_key ON wp_terms(slug COLLATE NOCASE, taxonomy COLLATE RTRIM)', 4),
    $record186('index', 'wp_posts_slug_type_key', 'wp_posts', 8, 'CREATE UNIQUE INDEX wp_posts_slug_type_key ON wp_posts(post_name COLLATE NOCASE, post_type COLLATE RTRIM)', 5),
    $record186('index', 'wp_terms_slug_key', 'wp_terms', 14, 'CREATE UNIQUE INDEX wp_terms_slug_key ON wp_terms(slug COLLATE NOCASE)', 11),
    $record186('index', 'wp_term_relationships_term_lookup_bad', 'wp_term_relationships', 9, 'CREATE INDEX wp_term_relationships_term_lookup_bad ON wp_term_relationships(term_slug, term_taxonomy)', 6),
    $record186('index', 'wp_term_relationships_post_lookup_bad', 'wp_term_relationships', 10, 'CREATE INDEX wp_term_relationships_post_lookup_bad ON wp_term_relationships(post_slug COLLATE NOCASE, post_type)', 7),
];
$nextRecords186 = [
    $currentRecords186[0],
    $currentRecords186[1],
    $currentRecords186[2],
    $currentRecords186[3],
    $currentRecords186[4],
    $currentRecords186[5],
    $record186('index', 'wp_term_relationships_term_lookup', 'wp_term_relationships', 11, 'CREATE INDEX wp_term_relationships_term_lookup ON wp_term_relationships(term_slug COLLATE NOCASE, term_taxonomy COLLATE RTRIM)', 8),
    $record186('index', 'wp_term_relationships_post_lookup', 'wp_term_relationships', 12, 'CREATE INDEX wp_term_relationships_post_lookup ON wp_term_relationships(post_slug COLLATE NOCASE, post_type COLLATE RTRIM)', 9),
    $record186('index', 'wp_term_relationships_missing_lookup', 'wp_term_relationships', 13, 'CREATE INDEX wp_term_relationships_missing_lookup ON wp_term_relationships(missing_slug COLLATE NOCASE)', 10),
];

$currentTables186 = [
    'wp_terms' => [
        ['rowid' => 1, 'term_id' => 1, 'slug' => 'news', 'taxonomy' => 'category', 'scope' => 'site'],
    ],
    'wp_posts' => [
        ['rowid' => 1, 'ID' => 1, 'post_name' => 'hello-world', 'post_type' => 'post', 'post_parent' => 0],
    ],
    'wp_term_relationships' => [
        ['rowid' => 1, 'object_id' => 1, 'term_slug' => 'news', 'term_taxonomy' => 'category', 'post_slug' => 'hello-world', 'post_type' => 'post', 'missing_slug' => 'news'],
    ],
];
$nextTables186 = $currentTables186;

$page186 = static fn (
    int $offset = 0,
    int $limit = 186,
    ?array $cursor = null,
    ?array $nextRecords = null,
    string $indexSql = 'PRAGMA index_xinfo(wp_term_relationships_term_lookup)',
    bool $tableValued = false,
): array => SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext::currentNextPageFromCatalog186(
    $currentRecords186,
    $currentTables186,
    $nextRecords ?? $nextRecords186,
    $nextTables186,
    $indexSql,
    $offset,
    $limit,
    $cursor,
    $tableValued,
);

$valueAt186 = static function (mixed $value, string $path): mixed {
    foreach (explode('.', $path) as $part) {
        if (is_array($value) && array_key_exists($part, $value)) {
            $value = $value[$part];
            continue;
        }
        $value = $value[(int) $part];
    }

    return $value;
};

$default186 = static fn (): array => $page186();
$blocked186 = static fn (): array => $page186(nextRecords: $currentRecords186);
$childCollations186 = static fn (): array => SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext::childIndexCollationRows186($currentRecords186);
$nextChildCollations186 = static fn (): array => SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext::childIndexCollationRows186($nextRecords186, 'next');
$tableValued186 = static fn (): array => $page186(indexSql: "pragma_index_xinfo('wp_term_relationships_term_lookup')", tableValued: true);

$cases186 = [
    'status ok after child collation repair' => [$default186, 'status', 'ok'],
    'default limit' => [$default186, 'limit', 186],
    'total rows include child collation rows' => [$default186, 'total', 49],
    'count rows include child collation rows' => [$default186, 'count', 49],
    'complete true' => [$default186, 'complete', true],
    'next null' => [$default186, 'next', null],
    'current child collation source' => [$default186, 'current_source.foreign_key_child_collation_source', 'create_table_column_collate_and_pragma_index_xinfo'],
    'next child collation source' => [$default186, 'next_source.foreign_key_child_collation_source', 'create_table_column_collate_and_pragma_index_xinfo'],
    'current child collation row count' => [$default186, 'current.foreign_key_child_collation_rows', 5],
    'next child collation row count' => [$default186, 'next_counts.foreign_key_child_collation_rows', 5],
    'current matched child collations' => [$default186, 'current.foreign_key_child_collations.matched', 1],
    'current mismatched child collations' => [$default186, 'current.foreign_key_child_collations.mismatch', 3],
    'current missing child collation index' => [$default186, 'current.foreign_key_child_collations.missing_child_index', 1],
    'next matched child collations' => [$default186, 'next_counts.foreign_key_child_collations.matched', 5],
    'next mismatched child collations cleared' => [$default186, 'next_counts.foreign_key_child_collations.mismatch', 0],
    'next missing child collation index cleared' => [$default186, 'next_counts.foreign_key_child_collations.missing_child_index', 0],
    'current nocase child columns' => [$default186, 'current.foreign_key_child_collations.nocase', 3],
    'current rtrim child columns' => [$default186, 'current.foreign_key_child_collations.rtrim', 2],
    'current binary child columns' => [$default186, 'current.foreign_key_child_collations.binary', 0],
    'delta rows unchanged' => [$default186, 'delta.foreign_key_child_collation_rows', 0],
    'delta mismatches repaired' => [$default186, 'delta.foreign_key_child_collation_mismatches', -3],
    'delta repaired true' => [$default186, 'delta.foreign_key_child_collation_repaired', true],
    'delta changed true' => [$default186, 'delta.foreign_key_child_collation_changed', true],
    'next state ready true' => [$default186, 'next_state.ready', true],
    'next state blocking empty' => [$default186, 'next_state.blocking', []],
    'decorates current first child index row with child collation' => [$default186, 'rows.29.child_column_collation', 'NOCASE'],
    'decorates current first child index row with mismatch status' => [$default186, 'rows.29.child_index_collation_status', 'collation_mismatch'],
    'decorates current second child index row with rtrim expected' => [$default186, 'rows.30.child_column_collation', 'RTRIM'],
    'decorates current second child index row mismatch false' => [$default186, 'rows.30.child_index_collation_matches', false],
    'decorates current post name row ok' => [$default186, 'rows.31.child_index_collation_status', 'ok'],
    'decorates current post type row mismatch' => [$default186, 'rows.32.child_index_collation_status', 'collation_mismatch'],
    'decorates current missing child index row' => [$default186, 'rows.33.child_index_collation_status', 'missing_child_index'],
    'current collation row first kind' => [$default186, 'rows.39.kind', 'foreign_key_child_collation'],
    'current collation row first index coll' => [$default186, 'rows.39.index_coll', 'BINARY'],
    'current collation row first child coll' => [$default186, 'rows.39.child_column_collation', 'NOCASE'],
    'current collation row first status' => [$default186, 'rows.39.status', 'collation_mismatch'],
    'current collation row second rtrim mismatch' => [$default186, 'rows.40.status', 'collation_mismatch'],
    'current collation row third ok' => [$default186, 'rows.41.status', 'ok'],
    'current collation row fourth post type mismatch' => [$default186, 'rows.42.status', 'collation_mismatch'],
    'current collation row fifth missing' => [$default186, 'rows.43.status', 'missing_child_index'],
    'next collation row first repaired' => [$default186, 'rows.44.status', 'ok'],
    'next collation row second repaired' => [$default186, 'rows.45.index_coll', 'RTRIM'],
    'next collation row third repaired' => [$default186, 'rows.46.status', 'ok'],
    'next collation row fourth repaired' => [$default186, 'rows.47.index_coll', 'RTRIM'],
    'next collation row fifth repaired' => [$default186, 'rows.48.index_coll', 'NOCASE'],
    'blocked status remains blocked' => [$blocked186, 'status', 'blocked'],
    'blocked next mismatches remain' => [$blocked186, 'next_counts.foreign_key_child_collations.mismatch', 3],
    'blocked next missing remains' => [$blocked186, 'next_counts.foreign_key_child_collations.missing_child_index', 1],
    'blocked contains child collation blocker' => [$blocked186, 'next_state.blocking.0', 'foreign_key_child_index_collation'],
    'blocked repaired false' => [$blocked186, 'delta.foreign_key_child_collation_repaired', false],
    'helper current first mismatch' => [$childCollations186, '0.status', 'collation_mismatch'],
    'helper current second expected rtrim' => [$childCollations186, '1.child_column_collation', 'RTRIM'],
    'helper current post name ok' => [$childCollations186, '2.collation_matches', true],
    'helper current post type mismatch message' => [$childCollations186, '3.status', 'collation_mismatch'],
    'helper current missing child index' => [$childCollations186, '4.status', 'missing_child_index'],
    'helper next first repaired' => [$nextChildCollations186, '0.status', 'ok'],
    'helper next second repaired coll' => [$nextChildCollations186, '1.index_coll', 'RTRIM'],
    'helper next missing repaired' => [$nextChildCollations186, '4.status', 'ok'],
    'table valued flag preserved' => [$tableValued186, 'current_source.table_valued_index_xinfo', true],
    'table valued child collation source' => [$tableValued186, 'current_source.foreign_key_child_collation_source', 'create_table_column_collate_and_pragma_index_xinfo'],
];

$tests = [];
foreach ($cases186 as $name => [$factory, $path, $expected]) {
    $tests['pragma index xinfo foreignkey child collation current source next186 ' . $name] = static function (TestRunner $t) use ($factory, $path, $expected, $valueAt186): void {
        $t->same($expected, $valueAt186($factory(), $path));
    };
}

$tests['pragma index xinfo foreignkey child collation current source next186 paginates into child collation rows'] = static function (TestRunner $t) use ($page186): void {
    $first = $page186(0, 40);
    $second = $page186(40, 5, $first['next']);
    $third = $page186(45, 4, $second['next']);

    $t->same(40, $first['count']);
    $t->same(['source_id' => $first['source_id'], 'offset' => 40], $first['next']);
    $t->same('foreign_key_child_collation', $second['rows'][0]['kind']);
    $t->same('collation_mismatch', $second['rows'][0]['status']);
    $t->same('missing_child_index', $second['rows'][3]['status']);
    $t->same('ok', $third['rows'][0]['status']);
    $t->same('NOCASE', $third['rows'][3]['index_coll']);
    $t->same(null, $third['next']);
};

$tests['pragma index xinfo foreignkey child collation current source next186 source changes with child collation repair'] = static function (TestRunner $t) use ($page186, $currentRecords186): void {
    $changed = $page186();
    $same = $page186(nextRecords: $currentRecords186);

    $t->same(true, $changed['source_id'] !== $same['source_id']);
    $t->same(true, $changed['delta']['foreign_key_child_collation_changed']);
    $t->same(false, $same['delta']['foreign_key_child_collation_changed']);
};

$tests['pragma index xinfo foreignkey child collation current source next186 rejects stale child collation cursor'] = static function (TestRunner $t) use ($page186, $currentRecords186): void {
    $first = $page186(0, 40);

    $t->throws(InvalidArgumentException::class, static fn (): array => $page186(40, 5, $first['next'], nextRecords: $currentRecords186));
};

$tests['pragma index xinfo foreignkey child collation current source next186 rejects stale offset cursor'] = static function (TestRunner $t) use ($page186): void {
    $first = $page186(0, 40);

    $t->throws(InvalidArgumentException::class, static fn (): array => $page186(41, 5, $first['next']));
};

$tests['pragma index xinfo foreignkey child collation current source next186 rejects malformed records'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn (): array => SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext::childIndexCollationRows186([['bad' => 'record']]));
};

$tests['pragma index xinfo foreignkey child collation current source next186 rejects negative offset'] = static function (TestRunner $t) use ($page186): void {
    $t->throws(InvalidArgumentException::class, static fn (): array => $page186(offset: -1));
};

$tests['pragma index xinfo foreignkey child collation current source next186 rejects zero limit'] = static function (TestRunner $t) use ($page186): void {
    $t->throws(InvalidArgumentException::class, static fn (): array => $page186(limit: 0));
};

return $tests;
