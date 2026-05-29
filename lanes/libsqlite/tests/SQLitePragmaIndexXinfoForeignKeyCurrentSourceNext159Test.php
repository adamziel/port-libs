<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext;
use PortLibs\LibSqlite\SQLiteSchemaRecord;

$record159 = static fn (string $type, string $name, string $table, int $root, string $sql, int $rowid): SQLiteSchemaRecord => new SQLiteSchemaRecord($type, $name, $table, $root, $sql, $rowid);

$currentRecords159 = [
    $record159('table', 'wp_option_names', 'wp_option_names', 4, 'CREATE TABLE wp_option_names(name TEXT COLLATE NOCASE, blog_id INTEGER, PRIMARY KEY(name, blog_id))', 1),
    $record159('table', 'wp_sites', 'wp_sites', 5, 'CREATE TABLE wp_sites(blog_id INTEGER PRIMARY KEY, domain TEXT)', 2),
    $record159('table', 'wp_options', 'wp_options', 6, 'CREATE TABLE wp_options(option_id INTEGER PRIMARY KEY, option_name TEXT, blog_id TEXT, site_id TEXT, autoload TEXT, FOREIGN KEY(option_name, blog_id) REFERENCES wp_option_names(name, blog_id), FOREIGN KEY(site_id) REFERENCES wp_sites(blog_id))', 3),
    $record159('index', 'wp_option_names_lookup', 'wp_option_names', 7, 'CREATE UNIQUE INDEX wp_option_names_lookup ON wp_option_names(name COLLATE BINARY, blog_id)', 4),
    $record159('index', 'wp_sites_domain', 'wp_sites', 8, 'CREATE INDEX wp_sites_domain ON wp_sites(domain)', 5),
];
$nextRecords159 = [
    $record159('table', 'wp_option_names', 'wp_option_names', 4, 'CREATE TABLE wp_option_names(name TEXT COLLATE NOCASE, blog_id INTEGER, PRIMARY KEY(name, blog_id))', 1),
    $record159('table', 'wp_sites', 'wp_sites', 5, 'CREATE TABLE wp_sites(blog_id INTEGER PRIMARY KEY, domain TEXT)', 2),
    $record159('table', 'wp_options', 'wp_options', 6, 'CREATE TABLE wp_options(option_id INTEGER PRIMARY KEY, option_name TEXT, blog_id TEXT, site_id TEXT, autoload TEXT, FOREIGN KEY(option_name, blog_id) REFERENCES wp_option_names(name, blog_id), FOREIGN KEY(site_id) REFERENCES wp_sites(blog_id))', 3),
    $record159('index', 'wp_option_names_lookup', 'wp_option_names', 7, 'CREATE UNIQUE INDEX wp_option_names_lookup ON wp_option_names(name COLLATE NOCASE, blog_id)', 4),
    $record159('index', 'wp_sites_domain', 'wp_sites', 8, 'CREATE INDEX wp_sites_domain ON wp_sites(domain)', 5),
];
$currentTables159 = [
    'wp_option_names' => [
        ['rowid' => 1, 'name' => 'siteurl', 'blog_id' => 1],
        ['rowid' => 2, 'name' => 'home', 'blog_id' => 1],
    ],
    'wp_sites' => [
        ['rowid' => 1, 'blog_id' => 1, 'domain' => 'example.test'],
    ],
    'wp_options' => [
        ['rowid' => 1, 'option_id' => 1, 'option_name' => 'SITEURL', 'blog_id' => '1', 'site_id' => '1', 'autoload' => 'yes'],
        ['rowid' => 2, 'option_id' => 2, 'option_name' => 'HOME', 'blog_id' => '1', 'site_id' => '99', 'autoload' => 'yes'],
        ['rowid' => 3, 'option_id' => 3, 'option_name' => 'missing_plugin', 'blog_id' => '2', 'site_id' => '1', 'autoload' => 'no'],
    ],
];
$nextTables159 = [
    'wp_option_names' => [
        ['rowid' => 1, 'name' => 'siteurl', 'blog_id' => 1],
        ['rowid' => 2, 'name' => 'home', 'blog_id' => 1],
        ['rowid' => 3, 'name' => 'missing_plugin', 'blog_id' => 2],
    ],
    'wp_sites' => [
        ['rowid' => 1, 'blog_id' => 1, 'domain' => 'example.test'],
        ['rowid' => 99, 'blog_id' => 99, 'domain' => 'network.example.test'],
    ],
    'wp_options' => $currentTables159['wp_options'],
];

$page159 = static fn (
    int $offset = 0,
    int $limit = 159,
    ?array $cursor = null,
    ?array $nextRecords = null,
    ?array $nextTables = null,
    string $indexSql = 'PRAGMA index_xinfo(wp_option_names_lookup)',
    bool $tableValued = false,
): array => SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext::currentNextPageFromCatalog159(
    $currentRecords159,
    $currentTables159,
    $nextRecords ?? $nextRecords159,
    $nextTables ?? $nextTables159,
    $indexSql,
    $offset,
    $limit,
    $cursor,
    $tableValued,
);

$valueAt159 = static function (mixed $value, string $path): mixed {
    foreach (explode('.', $path) as $part) {
        if (is_array($value) && array_key_exists($part, $value)) {
            $value = $value[$part];
            continue;
        }
        $value = $value[(int) $part];
    }

    return $value;
};

$default159 = static fn (): array => $page159();
$blockedNext159 = static fn (): array => $page159(nextRecords: $currentRecords159, nextTables: $currentTables159);
$foreignKeys159 = static fn (): array => SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext::foreignKeysFromCatalog159($nextRecords159);

$cases159 = [
    'status ok after catalog repair' => [$default159, 'status', 'ok'],
    'limit default' => [$default159, 'limit', 159],
    'total rows' => [$default159, 'total', 12],
    'count rows' => [$default159, 'count', 12],
    'complete' => [$default159, 'complete', true],
    'next null' => [$default159, 'next', null],
    'current source type' => [$default159, 'current_source.foreign_key_source', 'pragma_foreign_key_list'],
    'next source type' => [$default159, 'next_source.foreign_key_source', 'pragma_foreign_key_list'],
    'current derived fks' => [$default159, 'current_source.derived_foreign_keys', 2],
    'next derived fks' => [$default159, 'next_source.derived_foreign_keys', 2],
    'normalized source sql' => [$default159, 'current_source.index_xinfo_sql', 'pragma index_xinfo(wp_option_names_lookup)'],
    'table valued false' => [$default159, 'current_source.table_valued_index_xinfo', false],
    'source id length' => [static fn (): array => ['len' => strlen($page159()['source_id'])], 'len', 64],
    'current records hash length' => [static fn (): array => ['len' => strlen($page159()['current_source']['records'])], 'len', 64],
    'next tables hash length' => [static fn (): array => ['len' => strlen($page159()['next_source']['tables'])], 'len', 64],
    'current xinfo rows' => [$default159, 'current.index_xinfo', 3],
    'next xinfo rows' => [$default159, 'next_counts.index_xinfo', 3],
    'current admissions' => [$default159, 'current.index_admissions', 2],
    'next admissions' => [$default159, 'next_counts.index_admissions', 2],
    'current index blockers' => [$default159, 'current.index_blockers', 1],
    'next index blockers' => [$default159, 'next_counts.index_blockers', 0],
    'current fk violations' => [$default159, 'current.foreign_key_violations', 2],
    'next fk violations' => [$default159, 'next_counts.foreign_key_violations', 0],
    'current total blockers' => [$default159, 'current.total_blockers', 3],
    'next total blockers' => [$default159, 'next_counts.total_blockers', 0],
    'target schema' => [$default159, 'current.target_schema', 'main'],
    'target index' => [$default159, 'current.target_index', 'wp_option_names_lookup'],
    'foreign key tables' => [$default159, 'current.foreign_key_tables', ['wp_options']],
    'parent indexes' => [$default159, 'next_counts.parent_indexes', ['wp_option_names_lookup', 'rowid-primary-key']],
    'delta xinfo' => [$default159, 'delta.index_xinfo', 0],
    'delta admissions' => [$default159, 'delta.index_admissions', 0],
    'delta blockers' => [$default159, 'delta.index_blockers', -1],
    'delta fk' => [$default159, 'delta.foreign_key_violations', -2],
    'delta total' => [$default159, 'delta.total_blockers', -3],
    'delta cleared' => [$default159, 'delta.cleared', true],
    'next ready' => [$default159, 'next_state.ready', true],
    'next blocking' => [$default159, 'next_state.blocking', []],
    'row0 current xinfo' => [$default159, 'rows.0.kind', 'index_xinfo'],
    'row0 collation binary current' => [$default159, 'rows.0.coll', 'BINARY'],
    'row1 blog id key' => [$default159, 'rows.1.name', 'blog_id'],
    'row2 aux rowid' => [$default159, 'rows.2.key', 0],
    'row3 admission blocked' => [$default159, 'rows.3.status', 'blocked'],
    'row3 admission collation' => [$default159, 'rows.3.collations', ['NOCASE', 'BINARY']],
    'row4 rowid admission ok' => [$default159, 'rows.4.index', 'rowid-primary-key'],
    'row5 fk option violation' => [$default159, 'rows.5.rowid', 3],
    'row6 fk site violation' => [$default159, 'rows.6.rowid', 2],
    'row7 next side' => [$default159, 'rows.7.side', 'next'],
    'row7 collation nocase next' => [$default159, 'rows.7.coll', 'NOCASE'],
    'row10 next admission ok' => [$default159, 'rows.10.status', 'ok'],
    'row11 rowid admission next ok' => [$default159, 'rows.11.index', 'rowid-primary-key'],
    'blocked status' => [$blockedNext159, 'status', 'blocked'],
    'blocked ready' => [$blockedNext159, 'next_state.ready', false],
    'blocked blockers' => [$blockedNext159, 'next_state.blocking', ['foreign_key_parent_unique_index', 'foreign_key_check']],
    'blocked next index blockers' => [$blockedNext159, 'next_counts.index_blockers', 1],
    'blocked next fk violations' => [$blockedNext159, 'next_counts.foreign_key_violations', 2],
    'derived fk0 table' => [$foreignKeys159, '0.table', 'wp_options'],
    'derived fk0 parent' => [$foreignKeys159, '0.parent', 'wp_option_names'],
    'derived fk0 child0' => [$foreignKeys159, '0.columns.0.child', 'option_name'],
    'derived fk0 parent0' => [$foreignKeys159, '0.columns.0.parent', 'name'],
    'derived fk0 affinity0' => [$foreignKeys159, '0.columns.0.affinity', 'text'],
    'derived fk0 collation0' => [$foreignKeys159, '0.columns.0.collation', 'nocase'],
    'derived fk0 child1' => [$foreignKeys159, '0.columns.1.child', 'blog_id'],
    'derived fk0 affinity1' => [$foreignKeys159, '0.columns.1.affinity', 'integer'],
    'derived fk1 parent' => [$foreignKeys159, '1.parent', 'wp_sites'],
    'derived fk1 rowid affinity' => [$foreignKeys159, '1.columns.0.affinity', 'integer'],
];

$tests = [];
foreach ($cases159 as $name => [$factory, $path, $expected]) {
    $tests['pragma index xinfo foreignkey current source next159 ' . $name] = static function (TestRunner $t) use ($factory, $path, $expected, $valueAt159): void {
        $t->same($expected, $valueAt159($factory(), $path));
    };
}

$tests['pragma index xinfo foreignkey current source next159 paginates catalog-derived source'] = static function (TestRunner $t) use ($page159): void {
    $first = $page159(0, 5);
    $second = $page159(5, 5, $first['next']);
    $third = $page159(10, 5, $second['next']);

    $t->same(5, $first['count']);
    $t->same(['source_id' => $first['source_id'], 'offset' => 5], $first['next']);
    $t->same('foreign_key_check', $second['rows'][0]['kind']);
    $t->same('next', $second['rows'][2]['side']);
    $t->same(null, $third['next']);
};

$tests['pragma index xinfo foreignkey current source next159 table-valued index_xinfo source'] = static function (TestRunner $t) use ($page159): void {
    $result = $page159(indexSql: "pragma_index_xinfo('wp_option_names_lookup')", tableValued: true);

    $t->same('ok', $result['status']);
    $t->same(true, $result['current_source']['table_valued_index_xinfo']);
    $t->same("pragma_index_xinfo('wp_option_names_lookup')", $result['current_source']['index_xinfo_sql']);
};

$tests['pragma index xinfo foreignkey current source next159 source changes with catalog ddl'] = static function (TestRunner $t) use ($page159, $currentRecords159): void {
    $first = $page159();
    $second = $page159(nextRecords: $currentRecords159);

    $t->same(true, $first['source_id'] !== $second['source_id']);
    $t->same(true, $first['next_source']['records'] !== $second['next_source']['records']);
};

$tests['pragma index xinfo foreignkey current source next159 source changes with table data'] = static function (TestRunner $t) use ($page159, $currentTables159): void {
    $first = $page159();
    $second = $page159(nextTables: $currentTables159);

    $t->same(true, $first['source_id'] !== $second['source_id']);
    $t->same(true, $first['next_source']['tables'] !== $second['next_source']['tables']);
};

$tests['pragma index xinfo foreignkey current source next159 rejects stale source cursor'] = static function (TestRunner $t) use ($page159, $currentRecords159): void {
    $first = $page159(0, 5);
    $t->throws(InvalidArgumentException::class, static fn (): array => $page159(5, 5, $first['next'], nextRecords: $currentRecords159));
};

$tests['pragma index xinfo foreignkey current source next159 rejects stale offset cursor'] = static function (TestRunner $t) use ($page159): void {
    $first = $page159(0, 5);
    $t->throws(InvalidArgumentException::class, static fn (): array => $page159(6, 5, $first['next']));
};

$tests['pragma index xinfo foreignkey current source next159 resolves implicit rowid parent columns'] = static function (TestRunner $t) use ($record159): void {
    $records = [
        $record159('table', 'parent', 'parent', 2, 'CREATE TABLE parent(id INTEGER PRIMARY KEY)', 1),
        $record159('table', 'child', 'child', 3, 'CREATE TABLE child(parent_id INTEGER REFERENCES parent)', 2),
    ];

    $foreignKeys = SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext::foreignKeysFromCatalog159($records);

    $t->same('parent_id', $foreignKeys[0]['columns'][0]['child']);
    $t->same('id', $foreignKeys[0]['columns'][0]['parent']);
    $t->same('integer', $foreignKeys[0]['columns'][0]['affinity']);
};

$tests['pragma index xinfo foreignkey current source next159 rejects negative offset'] = static function (TestRunner $t) use ($page159): void {
    $t->throws(InvalidArgumentException::class, static fn (): array => $page159(-1));
};

$tests['pragma index xinfo foreignkey current source next159 rejects zero limit'] = static function (TestRunner $t) use ($page159): void {
    $t->throws(InvalidArgumentException::class, static fn (): array => $page159(0, 0));
};

return $tests;
