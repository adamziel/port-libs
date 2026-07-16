<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext;
use PortLibs\LibSqlite\SQLiteSchemaRecord;

$record202 = static fn (string $type, string $name, string $table, ?int $root, ?string $sql, int $rowId): SQLiteSchemaRecord => new SQLiteSchemaRecord($type, $name, $table, $root, $sql, $rowId);

$currentRecords202 = [
    $record202('table', 'wp_blogs', 'wp_blogs', 2, 'CREATE TABLE wp_blogs(blog_id INTEGER PRIMARY KEY, domain TEXT UNIQUE)', 1),
    $record202('index', 'sqlite_autoindex_wp_blogs_1', 'wp_blogs', 3, null, 2),
    $record202('table', 'wp_terms', 'wp_terms', 4, 'CREATE TABLE wp_terms(term_id INTEGER PRIMARY KEY, slug TEXT UNIQUE)', 3),
    $record202('index', 'sqlite_autoindex_wp_terms_1', 'wp_terms', 5, null, 4),
    $record202('table', 'wp_term_taxonomy', 'wp_term_taxonomy', 6, "CREATE TABLE wp_term_taxonomy(
        taxonomy_id INTEGER PRIMARY KEY,
        blog_id INTEGER REFERENCES wp_blogs(blog_id) ON UPDATE CASCADE ON DELETE CASCADE,
        term_slug TEXT REFERENCES wp_terms(slug) ON UPDATE NO ACTION ON DELETE SET NULL,
        taxonomy TEXT DEFAULT 'category'
    )", 5),
    $record202('index', 'wp_taxonomy_lookup', 'wp_term_taxonomy', 7, 'CREATE INDEX wp_taxonomy_lookup ON wp_term_taxonomy(lower(term_slug) COLLATE nocase, blog_id DESC)', 6),
];

$nextRecords202 = [
    $currentRecords202[0],
    $currentRecords202[1],
    $record202('table', 'wp_terms', 'wp_terms', 4, 'CREATE TABLE wp_terms(term_id INTEGER PRIMARY KEY, slug TEXT, locale TEXT DEFAULT \'en_US\', UNIQUE(slug, locale))', 3),
    $record202('index', 'sqlite_autoindex_wp_terms_1', 'wp_terms', 5, null, 4),
    $record202('table', 'wp_term_taxonomy', 'wp_term_taxonomy', 6, "CREATE TABLE wp_term_taxonomy(
        taxonomy_id INTEGER PRIMARY KEY,
        blog_id INTEGER REFERENCES wp_blogs(blog_id) ON UPDATE CASCADE ON DELETE CASCADE,
        term_slug TEXT,
        locale TEXT DEFAULT 'en_US',
        taxonomy TEXT DEFAULT 'category',
        FOREIGN KEY(term_slug, locale) REFERENCES wp_terms(slug, locale) ON UPDATE CASCADE ON DELETE SET NULL MATCH SIMPLE
    )", 5),
    $record202('index', 'wp_taxonomy_lookup', 'wp_term_taxonomy', 7, 'CREATE INDEX wp_taxonomy_lookup ON wp_term_taxonomy(lower(term_slug) COLLATE nocase, locale COLLATE rtrim, blog_id DESC)', 6),
];

$page202 = static function (
    int $offset = 0,
    int $limit = 50,
    ?array $resume = null,
    ?array $currentRecords = null,
    ?array $nextRecords = null,
    string $indexSql = "pragma_index_xinfo('wp_taxonomy_lookup','main')",
    string $foreignKeySql = "pragma_foreign_key_list('wp_term_taxonomy','main')",
    bool $tableValuedIndex = true,
    bool $tableValuedFk = true,
) use ($currentRecords202, $nextRecords202): array {
    return SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext::page202(
        $currentRecords ?? $currentRecords202,
        $nextRecords ?? $nextRecords202,
        $indexSql,
        $foreignKeySql,
        $offset,
        $limit,
        $resume,
        $tableValuedIndex,
        $tableValuedFk,
    );
};

$valueAt202 = static function (array $value, string $path): mixed {
    foreach (explode('.', $path) as $part) {
        $value = $value[is_numeric($part) ? (int) $part : $part];
    }

    return $value;
};

$default202 = static fn (): array => $page202();
$pragmaForm202 = static fn (): array => $page202(
    indexSql: 'PRAGMA main.index_xinfo(wp_taxonomy_lookup)',
    foreignKeySql: 'PRAGMA main.foreign_key_list(wp_term_taxonomy)',
    tableValuedIndex: false,
    tableValuedFk: false,
);

$tests = [];

foreach ([
    'status ok' => [$default202, 'status', 'ok'],
    'operation marker' => [$default202, 'operation', 'pragma-index-xinfo-foreignkey-current-source-next202'],
    'normalized table valued index sql' => [$default202, 'current_source.index_xinfo_sql', "pragma_index_xinfo('wp_taxonomy_lookup','main')"],
    'normalized table valued fk sql' => [$default202, 'current_source.foreign_key_sql', "pragma_foreign_key_list('wp_term_taxonomy','main')"],
    'schema main' => [$default202, 'current_source.schema', 'main'],
    'index target' => [$default202, 'current_source.index', 'wp_taxonomy_lookup'],
    'table target' => [$default202, 'current_source.table', 'wp_term_taxonomy'],
    'table valued index flag' => [$default202, 'current_source.table_valued_index_xinfo', true],
    'table valued fk flag' => [$default202, 'current_source.table_valued_foreign_key_list', true],
    'fk source label' => [$default202, 'current_source.foreign_key_source', 'pragma_foreign_key_list'],
    'offset' => [$default202, 'offset', 0],
    'limit' => [$default202, 'limit', 50],
    'count' => [$default202, 'count', 12],
    'total' => [$default202, 'total', 12],
    'next null' => [$default202, 'next', null],
    'current index rows' => [$default202, 'current.index_xinfo', 3],
    'current fk rows' => [$default202, 'current.foreign_key_list', 2],
    'current expression rows' => [$default202, 'current.expression_terms', 1],
    'current auxiliary rows' => [$default202, 'current.auxiliary_terms', 1],
    'current fk groups' => [$default202, 'current.foreign_key_groups', 2],
    'current table valued fk rows' => [$default202, 'current.table_valued_foreign_key_rows', 2],
    'next index rows' => [$default202, 'next_counts.index_xinfo', 4],
    'next fk rows' => [$default202, 'next_counts.foreign_key_list', 3],
    'next expression rows' => [$default202, 'next_counts.expression_terms', 1],
    'next auxiliary rows' => [$default202, 'next_counts.auxiliary_terms', 1],
    'next fk groups' => [$default202, 'next_counts.foreign_key_groups', 2],
    'next table valued fk rows' => [$default202, 'next_counts.table_valued_foreign_key_rows', 3],
    'delta index rows' => [$default202, 'delta.index_xinfo', 1],
    'delta fk rows' => [$default202, 'delta.foreign_key_list', 1],
    'delta expression rows' => [$default202, 'delta.expression_terms', 0],
    'delta auxiliary rows' => [$default202, 'delta.auxiliary_terms', 0],
    'delta fk groups' => [$default202, 'delta.foreign_key_groups', 0],
    'delta table valued fk rows' => [$default202, 'delta.table_valued_foreign_key_rows', 1],
    'delta total' => [$default202, 'delta.total', 2],
    'dependency table valued fk' => [$default202, 'dependencies.1', 'sqlite-pragma-foreign-key-list-table-valued-current-source'],
    'pragma form index sql' => [$pragmaForm202, 'current_source.index_xinfo_sql', 'pragma main.index_xinfo("wp_taxonomy_lookup")'],
    'pragma form fk sql' => [$pragmaForm202, 'current_source.foreign_key_sql', 'pragma main.foreign_key_list("wp_term_taxonomy")'],
    'pragma form table valued count zero' => [$pragmaForm202, 'current.table_valued_foreign_key_rows', 0],
    'pragma form fk source label' => [$pragmaForm202, 'current_source.foreign_key_source', 'pragma foreign_key_list'],
] as $name => [$factory, $path, $expected]) {
    $tests['pragma index xinfo foreignkey table valued current source next202 summary ' . $name] = static function (TestRunner $t) use ($factory, $path, $expected, $valueAt202): void {
        $t->same($expected, $valueAt202($factory(), $path));
    };
}

foreach ([
    'row0 current expression kind' => ['rows.0.kind', 'index_xinfo'],
    'row0 current expression cid' => ['rows.0.cid', -2],
    'row0 current expression collation' => ['rows.0.coll', 'NOCASE'],
    'row1 current blog name' => ['rows.1.name', 'blog_id'],
    'row1 current blog desc' => ['rows.1.desc', 1],
    'row2 current auxiliary' => ['rows.2.key', 0],
    'row3 current blog fk source' => ['rows.3.source', 'pragma_foreign_key_list'],
    'row3 current blog fk table' => ['rows.3.foreign_table', 'wp_blogs'],
    'row3 current blog fk delete' => ['rows.3.on_delete', 'CASCADE'],
    'row4 current term fk source marker' => ['rows.4.foreign_key_source', 'table_valued'],
    'row4 current term fk from' => ['rows.4.from', 'term_slug'],
    'row5 next phase' => ['rows.5.phase', 'next'],
    'row5 next expression collation' => ['rows.5.coll', 'NOCASE'],
    'row6 next locale name' => ['rows.6.name', 'locale'],
    'row6 next locale collation' => ['rows.6.coll', 'RTRIM'],
    'row7 next blog desc' => ['rows.7.desc', 1],
    'row8 next auxiliary cid' => ['rows.8.cid', -1],
    'row9 next first fk id' => ['rows.9.foreign_key_id', 0],
    'row9 next first fk from' => ['rows.9.from', 'blog_id'],
    'row10 next composite seq0' => ['rows.10.foreign_key_seq', 0],
    'row10 next composite to slug' => ['rows.10.to', 'slug'],
    'row10 next composite update cascade' => ['rows.10.on_update', 'CASCADE'],
    'row11 next composite seq1' => ['rows.11.foreign_key_seq', 1],
    'row11 next composite from locale' => ['rows.11.from', 'locale'],
    'row11 next match simple' => ['rows.11.match', 'SIMPLE'],
] as $name => [$path, $expected]) {
    $tests['pragma index xinfo foreignkey table valued current source next202 rows ' . $name] = static function (TestRunner $t) use ($page202, $path, $expected, $valueAt202): void {
        $t->same($expected, $valueAt202($page202(), $path));
    };
}

$tests['pragma index xinfo foreignkey table valued current source next202 paginates with resume cursor'] = static function (TestRunner $t) use ($page202): void {
    $first = $page202(0, 5);
    $second = $page202(5, 4, $first['next']);
    $third = $page202(9, 4, $second['next']);

    $t->same(5, $first['count']);
    $t->same(5, $first['next']['offset']);
    $t->same('next', $first['next_row']['phase']);
    $t->same(4, $second['count']);
    $t->same('index_xinfo', $second['rows'][0]['kind']);
    $t->same(9, $second['next']['offset']);
    $t->same('foreign_key_list', $second['next_row']['kind']);
    $t->same(3, $third['count']);
    $t->same(null, $third['next']);
    $t->same('locale', $third['rows'][2]['from']);
};

$tests['pragma index xinfo foreignkey table valued current source next202 source differs from pragma form'] = static function (TestRunner $t) use ($default202, $pragmaForm202): void {
    $tableValued = $default202();
    $pragma = $pragmaForm202();

    $t->same(true, $tableValued['source_id'] !== $pragma['source_id']);
    $t->same($tableValued['delta']['total'], $pragma['delta']['total']);
    $t->same(2, $tableValued['current']['table_valued_foreign_key_rows']);
    $t->same(0, $pragma['current']['table_valued_foreign_key_rows']);
};

$tests['pragma index xinfo foreignkey table valued current source next202 rejects stale resume cursor'] = static function (TestRunner $t) use ($page202, $currentRecords202): void {
    $first = $page202(0, 5);
    $changed = $currentRecords202;
    $changed[] = new SQLiteSchemaRecord('index', 'wp_taxonomy_locale', 'wp_term_taxonomy', 8, 'CREATE INDEX wp_taxonomy_locale ON wp_term_taxonomy(locale)', 7);

    $t->throws(InvalidArgumentException::class, static fn () => $page202(5, 4, $first['next'], $changed));
};

$tests['pragma index xinfo foreignkey table valued current source next202 rejects resume offset mismatch'] = static function (TestRunner $t) use ($page202): void {
    $first = $page202(0, 5);

    $t->throws(InvalidArgumentException::class, static fn () => $page202(6, 4, $first['next']));
};

$tests['pragma index xinfo foreignkey table valued current source next202 rejects invalid pragmas'] = static function (TestRunner $t) use ($page202): void {
    $t->throws(InvalidArgumentException::class, static fn () => $page202(indexSql: "pragma_index_info('wp_taxonomy_lookup','main')"));
    $t->throws(InvalidArgumentException::class, static fn () => $page202(foreignKeySql: "pragma_table_info('wp_term_taxonomy','main')"));
};

$tests['pragma index xinfo foreignkey table valued current source next202 rejects invalid bounds'] = static function (TestRunner $t) use ($page202): void {
    $t->throws(InvalidArgumentException::class, static fn () => $page202(-1, 5));
    $t->throws(InvalidArgumentException::class, static fn () => $page202(0, 0));
};

$tests['pragma index xinfo foreignkey table valued current source next202 rejects malformed records'] = static function (TestRunner $t) use ($nextRecords202): void {
    $t->throws(InvalidArgumentException::class, static fn (): array => SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext::page202(
        [['bad' => 'record']],
        $nextRecords202,
        "pragma_index_xinfo('wp_taxonomy_lookup','main')",
        "pragma_foreign_key_list('wp_term_taxonomy','main')",
        tableValuedIndexXinfo: true,
        tableValuedForeignKeyList: true,
    ));
};

return $tests;
