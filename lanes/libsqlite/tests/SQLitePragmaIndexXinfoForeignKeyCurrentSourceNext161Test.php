<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext;
use PortLibs\LibSqlite\SQLiteSchemaRecord;

$record161 = static fn (string $type, string $name, string $table, int $root, string $sql, int $rowid): SQLiteSchemaRecord => new SQLiteSchemaRecord($type, $name, $table, $root, $sql, $rowid);

$currentRecords161 = [
    $record161('table', 'wp_sites', 'wp_sites', 4, 'CREATE TABLE wp_sites(blog_id INTEGER PRIMARY KEY, domain TEXT COLLATE NOCASE)', 1),
    $record161('table', 'wp_option_names', 'wp_option_names', 5, 'CREATE TABLE wp_option_names(name TEXT COLLATE NOCASE, blog_id INTEGER, PRIMARY KEY(name, blog_id)) WITHOUT ROWID', 2),
    $record161('table', 'wp_options', 'wp_options', 6, 'CREATE TABLE wp_options(option_id INTEGER PRIMARY KEY, option_name TEXT, blog_id TEXT, site_id TEXT, autoload TEXT, FOREIGN KEY(site_id) REFERENCES wp_sites, FOREIGN KEY(option_name, blog_id) REFERENCES wp_option_names)', 3),
    $record161('index', 'wp_sites_domain', 'wp_sites', 7, 'CREATE INDEX wp_sites_domain ON wp_sites(domain COLLATE NOCASE)', 4),
    $record161('index', 'wp_option_names_lookup', 'wp_option_names', 8, 'CREATE UNIQUE INDEX wp_option_names_lookup ON wp_option_names(name COLLATE NOCASE, blog_id)', 5),
];
$nextRecords161 = $currentRecords161;

$currentTables161 = [
    'wp_sites' => [
        ['rowid' => 1, 'blog_id' => 1, 'domain' => 'example.test'],
    ],
    'wp_option_names' => [
        ['name' => 'siteurl', 'blog_id' => 1],
    ],
    'wp_options' => [
        ['rowid' => 1, 'option_id' => 1, 'option_name' => 'SITEURL', 'blog_id' => '1', 'site_id' => '1', 'autoload' => 'yes'],
        ['rowid' => 2, 'option_id' => 2, 'option_name' => 'home', 'blog_id' => '1', 'site_id' => '404', 'autoload' => 'yes'],
        ['rowid' => 3, 'option_id' => 3, 'option_name' => 'missing', 'blog_id' => '2', 'site_id' => '1', 'autoload' => 'no'],
    ],
];
$nextTables161 = [
    'wp_sites' => [
        ['rowid' => 1, 'blog_id' => 1, 'domain' => 'example.test'],
        ['rowid' => 404, 'blog_id' => 404, 'domain' => 'network.example.test'],
    ],
    'wp_option_names' => [
        ['name' => 'siteurl', 'blog_id' => 1],
        ['name' => 'home', 'blog_id' => 1],
        ['name' => 'missing', 'blog_id' => 2],
    ],
    'wp_options' => $currentTables161['wp_options'],
];

$page161 = static fn (
    int $offset = 0,
    int $limit = 161,
    ?array $cursor = null,
    ?array $nextRecords = null,
    ?array $nextTables = null,
    string $indexSql = 'PRAGMA index_xinfo(wp_option_names_lookup)',
    bool $tableValued = false,
): array => SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext::currentNextPageFromCatalog161(
    $currentRecords161,
    $currentTables161,
    $nextRecords ?? $nextRecords161,
    $nextTables ?? $nextTables161,
    $indexSql,
    $offset,
    $limit,
    $cursor,
    $tableValued,
);

$valueAt161 = static function (mixed $value, string $path): mixed {
    foreach (explode('.', $path) as $part) {
        if (is_array($value) && array_key_exists($part, $value)) {
            $value = $value[$part];
            continue;
        }
        $value = $value[(int) $part];
    }

    return $value;
};

$default161 = static fn (): array => $page161();
$blocked161 = static fn (): array => $page161(nextTables: $currentTables161);
$foreignKeys161 = static fn (): array => SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext::foreignKeysFromCatalog161($currentRecords161);

$cases161 = [
    'status ok after implicit primary key repair' => [$default161, 'status', 'ok'],
    'limit default' => [$default161, 'limit', 161],
    'total rows' => [$default161, 'total', 11],
    'count rows' => [$default161, 'count', 11],
    'complete true' => [$default161, 'complete', true],
    'next null' => [$default161, 'next', null],
    'source type current' => [$default161, 'current_source.foreign_key_source', 'pragma_foreign_key_list'],
    'source type next' => [$default161, 'next_source.foreign_key_source', 'pragma_foreign_key_list'],
    'derived current foreign keys' => [$default161, 'current_source.derived_foreign_keys', 2],
    'derived next foreign keys' => [$default161, 'next_source.derived_foreign_keys', 2],
    'source normalized sql' => [$default161, 'current_source.index_xinfo_sql', 'pragma index_xinfo(wp_option_names_lookup)'],
    'table valued false' => [$default161, 'current_source.table_valued_index_xinfo', false],
    'source id length' => [static fn (): array => ['len' => strlen($page161()['source_id'])], 'len', 64],
    'current records hash length' => [static fn (): array => ['len' => strlen($page161()['current_source']['records'])], 'len', 64],
    'next tables hash length' => [static fn (): array => ['len' => strlen($page161()['next_source']['tables'])], 'len', 64],
    'current xinfo rows' => [$default161, 'current.index_xinfo', 2],
    'next xinfo rows' => [$default161, 'next_counts.index_xinfo', 2],
    'current index admissions' => [$default161, 'current.index_admissions', 2],
    'next index admissions' => [$default161, 'next_counts.index_admissions', 2],
    'current index blockers' => [$default161, 'current.index_blockers', 0],
    'next index blockers' => [$default161, 'next_counts.index_blockers', 0],
    'current fk violations' => [$default161, 'current.foreign_key_violations', 3],
    'next fk violations' => [$default161, 'next_counts.foreign_key_violations', 0],
    'current total blockers' => [$default161, 'current.total_blockers', 3],
    'next total blockers' => [$default161, 'next_counts.total_blockers', 0],
    'delta fk cleared' => [$default161, 'delta.foreign_key_violations', -3],
    'delta blockers cleared' => [$default161, 'delta.total_blockers', -3],
    'delta cleared true' => [$default161, 'delta.cleared', true],
    'next ready true' => [$default161, 'next_state.ready', true],
    'next blocking empty' => [$default161, 'next_state.blocking', []],
    'target index' => [$default161, 'current.target_index', 'wp_option_names_lookup'],
    'foreign key tables' => [$default161, 'current.foreign_key_tables', ['wp_options']],
    'parent indexes' => [$default161, 'next_counts.parent_indexes', ['rowid-primary-key', 'wp_option_names_lookup']],
    'row0 current xinfo' => [$default161, 'rows.0.kind', 'index_xinfo'],
    'row0 collation nocase' => [$default161, 'rows.0.coll', 'NOCASE'],
    'row1 name blog id' => [$default161, 'rows.1.name', 'blog_id'],
    'row2 rowid admission' => [$default161, 'rows.2.index', 'rowid-primary-key'],
    'row2 admission ok' => [$default161, 'rows.2.status', 'ok'],
    'row3 composite admission' => [$default161, 'rows.3.index', 'wp_option_names_lookup'],
    'row3 collations' => [$default161, 'rows.3.collations', ['NOCASE', 'BINARY']],
    'row4 first violation' => [$default161, 'rows.4.rowid', 2],
    'row4 site parent' => [$default161, 'rows.4.parent', 'wp_sites'],
    'row5 composite violation' => [$default161, 'rows.5.parent', 'wp_option_names'],
    'row7 next side' => [$default161, 'rows.7.side', 'next'],
    'row9 next rowid admission' => [$default161, 'rows.9.index', 'rowid-primary-key'],
    'row10 next composite admission' => [$default161, 'rows.10.index', 'wp_option_names_lookup'],
    'blocked status' => [$blocked161, 'status', 'blocked'],
    'blocked ready false' => [$blocked161, 'next_state.ready', false],
    'blocked reason' => [$blocked161, 'next_state.blocking', ['foreign_key_check']],
    'blocked next violations' => [$blocked161, 'next_counts.foreign_key_violations', 3],
    'fk0 table' => [$foreignKeys161, '0.table', 'wp_options'],
    'fk0 parent' => [$foreignKeys161, '0.parent', 'wp_sites'],
    'fk0 implicit parent column' => [$foreignKeys161, '0.columns.0.parent', 'blog_id'],
    'fk0 implicit affinity' => [$foreignKeys161, '0.columns.0.affinity', 'integer'],
    'fk1 parent' => [$foreignKeys161, '1.parent', 'wp_option_names'],
    'fk1 implicit composite first parent' => [$foreignKeys161, '1.columns.0.parent', 'name'],
    'fk1 implicit composite second parent' => [$foreignKeys161, '1.columns.1.parent', 'blog_id'],
    'fk1 implicit composite collation' => [$foreignKeys161, '1.columns.0.collation', 'nocase'],
];

$tests = [];
foreach ($cases161 as $name => [$factory, $path, $expected]) {
    $tests['pragma index xinfo foreignkey current source next161 ' . $name] = static function (TestRunner $t) use ($factory, $path, $expected, $valueAt161): void {
        $t->same($expected, $valueAt161($factory(), $path));
    };
}

$tests['pragma index xinfo foreignkey current source next161 paginates implicit pk source'] = static function (TestRunner $t) use ($page161): void {
    $first = $page161(0, 5);
    $second = $page161(5, 5, $first['next']);
    $third = $page161(10, 5, $second['next']);

    $t->same(5, $first['count']);
    $t->same(['source_id' => $first['source_id'], 'offset' => 5], $first['next']);
    $t->same('foreign_key_check', $second['rows'][0]['kind']);
    $t->same('next', $third['rows'][0]['side']);
    $t->same(null, $third['next']);
};

$tests['pragma index xinfo foreignkey current source next161 supports table valued index xinfo'] = static function (TestRunner $t) use ($page161): void {
    $result = $page161(indexSql: "pragma_index_xinfo('wp_option_names_lookup')", tableValued: true);

    $t->same('ok', $result['status']);
    $t->same(true, $result['current_source']['table_valued_index_xinfo']);
    $t->same("pragma_index_xinfo('wp_option_names_lookup')", $result['current_source']['index_xinfo_sql']);
};

$tests['pragma index xinfo foreignkey current source next161 source changes with repaired rows'] = static function (TestRunner $t) use ($page161, $currentTables161): void {
    $first = $page161();
    $second = $page161(nextTables: $currentTables161);

    $t->same(true, $first['source_id'] !== $second['source_id']);
    $t->same(true, $first['next_source']['tables'] !== $second['next_source']['tables']);
};

$tests['pragma index xinfo foreignkey current source next161 rejects stale source cursor'] = static function (TestRunner $t) use ($page161, $currentTables161): void {
    $first = $page161(0, 5);
    $t->throws(InvalidArgumentException::class, static fn (): array => $page161(5, 5, $first['next'], nextTables: $currentTables161));
};

$tests['pragma index xinfo foreignkey current source next161 rejects stale offset cursor'] = static function (TestRunner $t) use ($page161): void {
    $first = $page161(0, 5);
    $t->throws(InvalidArgumentException::class, static fn (): array => $page161(6, 5, $first['next']));
};

$tests['pragma index xinfo foreignkey current source next161 rejects implicit parent without primary key'] = static function (TestRunner $t) use ($record161): void {
    $records = [
        $record161('table', 'parent', 'parent', 2, 'CREATE TABLE parent(id INTEGER)', 1),
        $record161('table', 'child', 'child', 3, 'CREATE TABLE child(parent_id INTEGER REFERENCES parent)', 2),
    ];

    $t->throws(InvalidArgumentException::class, static fn (): array => SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext::foreignKeysFromCatalog161($records));
};

$tests['pragma index xinfo foreignkey current source next161 rejects negative offset'] = static function (TestRunner $t) use ($page161): void {
    $t->throws(InvalidArgumentException::class, static fn (): array => $page161(-1));
};

$tests['pragma index xinfo foreignkey current source next161 rejects zero limit'] = static function (TestRunner $t) use ($page161): void {
    $t->throws(InvalidArgumentException::class, static fn (): array => $page161(0, 0));
};

return $tests;
