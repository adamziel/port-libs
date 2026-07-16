<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext;
use PortLibs\LibSqlite\SQLiteSchemaRecord;

$record196 = static fn (string $type, string $name, string $table, ?int $root, ?string $sql, int $rowId): SQLiteSchemaRecord => new SQLiteSchemaRecord($type, $name, $table, $root, $sql, $rowId);

$currentRecords196 = [
    $record196('table', 'wp_terms', 'wp_terms', 2, 'CREATE TABLE wp_terms(term_id INTEGER PRIMARY KEY, slug TEXT UNIQUE)', 1),
    $record196('index', 'sqlite_autoindex_wp_terms_1', 'wp_terms', 3, null, 2),
    $record196('table', 'wp_posts', 'wp_posts', 4, 'CREATE TABLE wp_posts(ID INTEGER PRIMARY KEY, post_name TEXT UNIQUE)', 3),
    $record196('index', 'sqlite_autoindex_wp_posts_1', 'wp_posts', 5, null, 4),
    $record196('table', 'wp_term_relationships', 'wp_term_relationships', 6, "CREATE TABLE wp_term_relationships(
        object_id INTEGER NOT NULL REFERENCES wp_posts(ID) ON DELETE CASCADE ON UPDATE NO ACTION,
        term_taxonomy_id INTEGER NOT NULL,
        term_slug TEXT,
        locale TEXT DEFAULT 'en_US',
        FOREIGN KEY(term_slug) REFERENCES wp_terms(slug) ON DELETE SET NULL ON UPDATE CASCADE
    )", 5),
    $record196('index', 'wp_tr_lookup', 'wp_term_relationships', 7, "CREATE INDEX wp_tr_lookup ON wp_term_relationships(lower(term_slug) COLLATE nocase, object_id DESC)", 6),
];

$nextRecords196 = [
    $record196('table', 'wp_terms', 'wp_terms', 2, 'CREATE TABLE wp_terms(term_id INTEGER PRIMARY KEY, slug TEXT UNIQUE, locale TEXT DEFAULT \'en_US\', UNIQUE(slug, locale))', 1),
    $record196('index', 'sqlite_autoindex_wp_terms_1', 'wp_terms', 3, null, 2),
    $record196('index', 'sqlite_autoindex_wp_terms_2', 'wp_terms', 8, null, 3),
    $record196('table', 'wp_posts', 'wp_posts', 4, 'CREATE TABLE wp_posts(ID INTEGER PRIMARY KEY, post_name TEXT UNIQUE)', 4),
    $record196('index', 'sqlite_autoindex_wp_posts_1', 'wp_posts', 5, null, 5),
    $record196('table', 'wp_term_relationships', 'wp_term_relationships', 6, "CREATE TABLE wp_term_relationships(
        object_id INTEGER NOT NULL REFERENCES wp_posts(ID) ON DELETE CASCADE ON UPDATE NO ACTION,
        term_taxonomy_id INTEGER NOT NULL,
        term_slug TEXT,
        locale TEXT DEFAULT 'en_US',
        FOREIGN KEY(term_slug, locale) REFERENCES wp_terms(slug, locale) ON DELETE SET NULL ON UPDATE CASCADE MATCH SIMPLE
    )", 6),
    $record196('index', 'wp_tr_lookup', 'wp_term_relationships', 7, "CREATE INDEX wp_tr_lookup ON wp_term_relationships(lower(term_slug) COLLATE nocase, locale COLLATE rtrim, object_id DESC)", 7),
];

$page196 = static function (
    int $offset = 0,
    int $limit = 50,
    ?array $resume = null,
    ?array $currentRecords = null,
    ?array $nextRecords = null,
    string $indexSql = 'PRAGMA main.index_xinfo(wp_tr_lookup)',
    string $foreignKeySql = 'PRAGMA main.foreign_key_list(wp_term_relationships)',
) use ($currentRecords196, $nextRecords196): array {
    return SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext::page196(
        $currentRecords ?? $currentRecords196,
        $nextRecords ?? $nextRecords196,
        $indexSql,
        $foreignKeySql,
        $offset,
        $limit,
        $resume,
    );
};

$valueAt196 = static function (array $value, string $path): mixed {
    foreach (explode('.', $path) as $part) {
        $value = $value[is_numeric($part) ? (int) $part : $part];
    }

    return $value;
};

$tests = [];

foreach ([
    'status ok' => ['status', 'ok'],
    'operation marker' => ['operation', 'pragma-index-xinfo-foreignkey-current-source-next196'],
    'normalized index sql' => ['current_source.index_xinfo_sql', 'pragma main.index_xinfo("wp_tr_lookup")'],
    'normalized foreign key sql' => ['current_source.foreign_key_sql', 'pragma main.foreign_key_list("wp_term_relationships")'],
    'schema main' => ['current_source.schema', 'main'],
    'index target' => ['current_source.index', 'wp_tr_lookup'],
    'table target' => ['current_source.table', 'wp_term_relationships'],
    'offset' => ['offset', 0],
    'limit' => ['limit', 50],
    'count' => ['count', 12],
    'total' => ['total', 12],
    'next null' => ['next', null],
    'current index rows' => ['current.index_xinfo', 3],
    'current fk rows' => ['current.foreign_key_list', 2],
    'current expression terms' => ['current.expression_terms', 1],
    'current auxiliary terms' => ['current.auxiliary_terms', 1],
    'current fk groups' => ['current.foreign_key_groups', 2],
    'next index rows' => ['next_counts.index_xinfo', 4],
    'next fk rows' => ['next_counts.foreign_key_list', 3],
    'next expression terms' => ['next_counts.expression_terms', 1],
    'next auxiliary terms' => ['next_counts.auxiliary_terms', 1],
    'next fk groups' => ['next_counts.foreign_key_groups', 2],
    'delta index rows' => ['delta.index_xinfo', 1],
    'delta fk rows' => ['delta.foreign_key_list', 1],
    'delta expression terms' => ['delta.expression_terms', 0],
    'delta auxiliary terms' => ['delta.auxiliary_terms', 0],
    'delta fk groups' => ['delta.foreign_key_groups', 0],
    'delta total' => ['delta.total', 2],
    'dependencies index' => ['dependencies.0', 'sqlite-pragma-index-xinfo-current-source'],
    'dependencies fk' => ['dependencies.1', 'sqlite-pragma-foreign-key-list-current-source'],
] as $name => [$path, $expected]) {
    $tests['pragma index xinfo foreignkey current source next196 summary ' . $name] = static function (TestRunner $t) use ($page196, $valueAt196, $path, $expected): void {
        $t->same($expected, $valueAt196($page196(), $path));
    };
}

foreach ([
    'row0 current index kind' => ['rows.0.kind', 'index_xinfo'],
    'row0 expression cid' => ['rows.0.cid', -2],
    'row0 expression flag' => ['rows.0.is_expression', 1],
    'row0 collation' => ['rows.0.coll', 'NOCASE'],
    'row1 object id name' => ['rows.1.name', 'object_id'],
    'row1 desc flag' => ['rows.1.desc', 1],
    'row2 auxiliary key' => ['rows.2.key', 0],
    'row2 auxiliary cid' => ['rows.2.cid', -1],
    'row3 first fk kind' => ['rows.3.kind', 'foreign_key_list'],
    'row3 first fk parent' => ['rows.3.foreign_table', 'wp_posts'],
    'row3 first fk from' => ['rows.3.from', 'object_id'],
    'row3 first fk delete' => ['rows.3.on_delete', 'CASCADE'],
    'row4 second fk parent' => ['rows.4.foreign_table', 'wp_terms'],
    'row4 second fk from' => ['rows.4.from', 'term_slug'],
    'row4 second fk update' => ['rows.4.on_update', 'CASCADE'],
    'row5 next index phase' => ['rows.5.phase', 'next'],
    'row5 next expression collation' => ['rows.5.coll', 'NOCASE'],
    'row6 next locale name' => ['rows.6.name', 'locale'],
    'row6 next locale collation' => ['rows.6.coll', 'RTRIM'],
    'row7 next object desc' => ['rows.7.desc', 1],
    'row8 next auxiliary cid' => ['rows.8.cid', -1],
    'row9 next posts fk id' => ['rows.9.foreign_key_id', 0],
    'row10 next composite fk seq0' => ['rows.10.foreign_key_seq', 0],
    'row10 next composite fk to slug' => ['rows.10.to', 'slug'],
    'row11 next composite fk seq1' => ['rows.11.foreign_key_seq', 1],
    'row11 next composite fk from locale' => ['rows.11.from', 'locale'],
    'row11 next composite fk match simple' => ['rows.11.match', 'SIMPLE'],
] as $name => [$path, $expected]) {
    $tests['pragma index xinfo foreignkey current source next196 rows ' . $name] = static function (TestRunner $t) use ($page196, $valueAt196, $path, $expected): void {
        $t->same($expected, $valueAt196($page196(), $path));
    };
}

$tests['pragma index xinfo foreignkey current source next196 paginates with resume cursor'] = static function (TestRunner $t) use ($page196): void {
    $first = $page196(0, 4);
    $second = $page196(4, 4, $first['next']);
    $third = $page196(8, 4, $second['next']);

    $t->same(4, $first['count']);
    $t->same(4, $first['next']['offset']);
    $t->same('foreign_key_list', $first['next_row']['kind']);
    $t->same(4, $second['count']);
    $t->same('foreign_key_list', $second['rows'][0]['kind']);
    $t->same('next', $second['rows'][1]['phase']);
    $t->same(8, $second['next']['offset']);
    $t->same('index_xinfo', $second['next_row']['kind']);
    $t->same(4, $third['count']);
    $t->same(null, $third['next']);
    $t->same('foreign_key_list', $third['rows'][3]['kind']);
    $t->same('locale', $third['rows'][3]['from']);
};

$tests['pragma index xinfo foreignkey current source next196 rejects stale resume cursor'] = static function (TestRunner $t) use ($page196, $currentRecords196): void {
    $first = $page196(0, 4);
    $changed = $currentRecords196;
    $changed[] = new SQLiteSchemaRecord('index', 'wp_tr_locale', 'wp_term_relationships', 9, 'CREATE INDEX wp_tr_locale ON wp_term_relationships(locale)', 7);

    $t->throws(InvalidArgumentException::class, static fn () => $page196(4, 4, $first['next'], $changed));
};

$tests['pragma index xinfo foreignkey current source next196 rejects resume offset mismatch'] = static function (TestRunner $t) use ($page196): void {
    $first = $page196(0, 4);

    $t->throws(InvalidArgumentException::class, static fn () => $page196(5, 4, $first['next']));
};

$tests['pragma index xinfo foreignkey current source next196 rejects invalid pragmas'] = static function (TestRunner $t) use ($page196): void {
    $t->throws(InvalidArgumentException::class, static fn () => $page196(0, 4, null, null, null, 'PRAGMA index_info(wp_tr_lookup)'));
    $t->throws(InvalidArgumentException::class, static fn () => $page196(0, 4, null, null, null, 'PRAGMA index_xinfo(wp_tr_lookup)', 'PRAGMA table_info(wp_term_relationships)'));
};

$tests['pragma index xinfo foreignkey current source next196 rejects invalid bounds'] = static function (TestRunner $t) use ($page196): void {
    $t->throws(InvalidArgumentException::class, static fn () => $page196(-1, 4));
    $t->throws(InvalidArgumentException::class, static fn () => $page196(0, 0));
};

return $tests;
