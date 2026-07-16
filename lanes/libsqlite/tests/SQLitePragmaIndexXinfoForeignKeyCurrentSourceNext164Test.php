<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext;
use PortLibs\LibSqlite\SQLiteSchemaRecord;

$record164 = static fn (string $type, string $name, string $table, int $root, string $sql, int $rowid): SQLiteSchemaRecord => new SQLiteSchemaRecord($type, $name, $table, $root, $sql, $rowid);

$currentRecords164 = [
    $record164('table', 'WpSites', 'WpSites', 4, 'CREATE TABLE WpSites(Blog_ID INTEGER PRIMARY KEY, Domain TEXT COLLATE NOCASE)', 1),
    $record164('table', 'WpOptionNames', 'WpOptionNames', 5, 'CREATE TABLE WpOptionNames(Name TEXT COLLATE NOCASE, Blog_ID INTEGER, PRIMARY KEY(Name, Blog_ID)) WITHOUT ROWID', 2),
    $record164('table', 'WpOptions', 'WpOptions', 6, 'CREATE TABLE WpOptions(Option_ID INTEGER PRIMARY KEY, Option_Name TEXT, Blog_ID TEXT, Site_ID TEXT, Autoload TEXT, FOREIGN KEY(Site_ID) REFERENCES WpSites, FOREIGN KEY(Option_Name, Blog_ID) REFERENCES WpOptionNames)', 3),
    $record164('index', 'WpOptionNamesLookup', 'WpOptionNames', 7, 'CREATE UNIQUE INDEX WpOptionNamesLookup ON WpOptionNames(Name COLLATE NOCASE, Blog_ID)', 4),
];
$nextRecords164 = $currentRecords164;

$currentTables164 = [
    'wpsites' => [
        ['rowid' => 1, 'blog_id' => 1, 'domain' => 'example.test'],
    ],
    'wpoptionnames' => [
        ['name' => 'siteurl', 'blog_id' => 1],
    ],
    'wpoptions' => [
        ['rowid' => 1, 'option_id' => 1, 'option_name' => 'SITEURL', 'blog_id' => '1', 'site_id' => '1', 'autoload' => 'yes'],
        ['rowid' => 2, 'option_id' => 2, 'option_name' => 'home', 'blog_id' => '1', 'site_id' => '404', 'autoload' => 'yes'],
        ['rowid' => 3, 'option_id' => 3, 'option_name' => 'missing', 'blog_id' => '2', 'site_id' => '1', 'autoload' => 'no'],
    ],
];
$nextTables164 = [
    'WPSITES' => [
        ['ROWID' => 1, 'BLOG_ID' => 1, 'DOMAIN' => 'example.test'],
        ['ROWID' => 404, 'BLOG_ID' => 404, 'DOMAIN' => 'network.example.test'],
    ],
    'WPOPTIONNAMES' => [
        ['NAME' => 'siteurl', 'BLOG_ID' => 1],
        ['NAME' => 'home', 'BLOG_ID' => 1],
        ['NAME' => 'missing', 'BLOG_ID' => 2],
    ],
    'WPOPTIONS' => $currentTables164['wpoptions'],
];

$page164 = static fn (
    int $offset = 0,
    int $limit = 164,
    ?array $cursor = null,
    ?array $nextRecords = null,
    ?array $nextTables = null,
    string $indexSql = 'PRAGMA index_xinfo(WpOptionNamesLookup)',
    bool $tableValued = false,
): array => SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext::currentNextPageFromCatalog164(
    $currentRecords164,
    $currentTables164,
    $nextRecords ?? $nextRecords164,
    $nextTables ?? $nextTables164,
    $indexSql,
    $offset,
    $limit,
    $cursor,
    $tableValued,
);

$valueAt164 = static function (mixed $value, string $path): mixed {
    foreach (explode('.', $path) as $part) {
        if (is_array($value) && array_key_exists($part, $value)) {
            $value = $value[$part];
            continue;
        }
        $value = $value[(int) $part];
    }

    return $value;
};

$default164 = static fn (): array => $page164();
$blocked164 = static fn (): array => $page164(nextTables: $currentTables164);
$canonical164 = static fn (): array => SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext::canonicalTables164($currentRecords164, $currentTables164);
$canonicalNext164 = static fn (): array => SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext::canonicalTables164($currentRecords164, $nextTables164);

$cases164 = [
    'status ok after casefolded repair' => [$default164, 'status', 'ok'],
    'limit default' => [$default164, 'limit', 164],
    'total rows' => [$default164, 'total', 11],
    'count rows' => [$default164, 'count', 11],
    'complete true' => [$default164, 'complete', true],
    'next null' => [$default164, 'next', null],
    'current source foreign key source' => [$default164, 'current_source.foreign_key_source', 'pragma_foreign_key_list'],
    'next source foreign key source' => [$default164, 'next_source.foreign_key_source', 'pragma_foreign_key_list'],
    'current source table key source' => [$default164, 'current_source.table_key_source', 'sqlite_schema_casefold'],
    'next source column key source' => [$default164, 'next_source.column_key_source', 'pragma_table_xinfo_casefold'],
    'derived current foreign keys' => [$default164, 'current_source.derived_foreign_keys', 2],
    'derived next foreign keys' => [$default164, 'next_source.derived_foreign_keys', 2],
    'source normalized sql' => [$default164, 'current_source.index_xinfo_sql', 'pragma index_xinfo(wpoptionnameslookup)'],
    'source id length' => [static fn (): array => ['len' => strlen($page164()['source_id'])], 'len', 64],
    'current records hash length' => [static fn (): array => ['len' => strlen($page164()['current_source']['records'])], 'len', 64],
    'next tables hash length' => [static fn (): array => ['len' => strlen($page164()['next_source']['tables'])], 'len', 64],
    'current xinfo rows' => [$default164, 'current.index_xinfo', 2],
    'next xinfo rows' => [$default164, 'next_counts.index_xinfo', 2],
    'current index admissions' => [$default164, 'current.index_admissions', 2],
    'next index admissions' => [$default164, 'next_counts.index_admissions', 2],
    'current index blockers' => [$default164, 'current.index_blockers', 0],
    'next index blockers' => [$default164, 'next_counts.index_blockers', 0],
    'current fk violations' => [$default164, 'current.foreign_key_violations', 3],
    'next fk violations' => [$default164, 'next_counts.foreign_key_violations', 0],
    'current total blockers' => [$default164, 'current.total_blockers', 3],
    'next total blockers' => [$default164, 'next_counts.total_blockers', 0],
    'delta fk cleared' => [$default164, 'delta.foreign_key_violations', -3],
    'delta blockers cleared' => [$default164, 'delta.total_blockers', -3],
    'delta cleared true' => [$default164, 'delta.cleared', true],
    'next ready true' => [$default164, 'next_state.ready', true],
    'next blocking empty' => [$default164, 'next_state.blocking', []],
    'target index' => [$default164, 'current.target_index', 'WpOptionNamesLookup'],
    'foreign key tables' => [$default164, 'current.foreign_key_tables', ['WpOptions']],
    'parent indexes' => [$default164, 'next_counts.parent_indexes', ['rowid-primary-key', 'WpOptionNamesLookup']],
    'row0 xinfo collation' => [$default164, 'rows.0.coll', 'NOCASE'],
    'row1 xinfo second column' => [$default164, 'rows.1.name', 'Blog_ID'],
    'row2 rowid admission' => [$default164, 'rows.2.index', 'rowid-primary-key'],
    'row3 composite admission' => [$default164, 'rows.3.index', 'WpOptionNamesLookup'],
    'row4 first current violation rowid' => [$default164, 'rows.4.rowid', 2],
    'row4 first current violation parent' => [$default164, 'rows.4.parent', 'WpSites'],
    'row5 composite current violation' => [$default164, 'rows.5.parent', 'WpOptionNames'],
    'row7 next side' => [$default164, 'rows.7.side', 'next'],
    'row9 next rowid admission' => [$default164, 'rows.9.index', 'rowid-primary-key'],
    'row10 next composite admission' => [$default164, 'rows.10.index', 'WpOptionNamesLookup'],
    'blocked status' => [$blocked164, 'status', 'blocked'],
    'blocked ready false' => [$blocked164, 'next_state.ready', false],
    'blocked reason' => [$blocked164, 'next_state.blocking', ['foreign_key_check']],
    'blocked next violations' => [$blocked164, 'next_counts.foreign_key_violations', 3],
    'canonical current table key' => [$canonical164, 'WpOptions.0.Option_ID', 1],
    'canonical current child column' => [$canonical164, 'WpOptions.0.Option_Name', 'SITEURL'],
    'canonical current parent row column' => [$canonical164, 'WpSites.0.Blog_ID', 1],
    'canonical next uppercase parent row column' => [$canonicalNext164, 'WpSites.1.Blog_ID', 404],
    'canonical preserves original lower key' => [$canonical164, 'WpOptions.0.option_name', 'SITEURL'],
    'canonical preserves original upper key' => [$canonicalNext164, 'WpOptionNames.1.NAME', 'home'],
];

$tests = [];
foreach ($cases164 as $name => [$factory, $path, $expected]) {
    $tests['pragma index xinfo foreignkey current source next164 ' . $name] = static function (TestRunner $t) use ($factory, $path, $expected, $valueAt164): void {
        $t->same($expected, $valueAt164($factory(), $path));
    };
}

$tests['pragma index xinfo foreignkey current source next164 paginates casefolded rows'] = static function (TestRunner $t) use ($page164): void {
    $first = $page164(0, 4);
    $second = $page164(4, 4, $first['next']);
    $third = $page164(8, 4, $second['next']);

    $t->same(4, $first['count']);
    $t->same(['source_id' => $first['source_id'], 'offset' => 4], $first['next']);
    $t->same('foreign_key_check', $second['rows'][0]['kind']);
    $t->same('next', $third['rows'][0]['side']);
    $t->same(null, $third['next']);
};

$tests['pragma index xinfo foreignkey current source next164 supports table valued index xinfo'] = static function (TestRunner $t) use ($page164): void {
    $result = $page164(indexSql: "pragma_index_xinfo('WpOptionNamesLookup')", tableValued: true);

    $t->same('ok', $result['status']);
    $t->same(true, $result['current_source']['table_valued_index_xinfo']);
    $t->same("pragma_index_xinfo('wpoptionnameslookup')", $result['current_source']['index_xinfo_sql']);
};

$tests['pragma index xinfo foreignkey current source next164 source changes with repaired mixed-case rows'] = static function (TestRunner $t) use ($page164, $currentTables164): void {
    $first = $page164();
    $second = $page164(nextTables: $currentTables164);

    $t->same(true, $first['source_id'] !== $second['source_id']);
    $t->same(true, $first['next_source']['tables'] !== $second['next_source']['tables']);
};

$tests['pragma index xinfo foreignkey current source next164 rejects stale source cursor'] = static function (TestRunner $t) use ($page164, $currentTables164): void {
    $first = $page164(0, 4);
    $t->throws(InvalidArgumentException::class, static fn (): array => $page164(4, 4, $first['next'], nextTables: $currentTables164));
};

$tests['pragma index xinfo foreignkey current source next164 rejects stale offset cursor'] = static function (TestRunner $t) use ($page164): void {
    $first = $page164(0, 4);
    $t->throws(InvalidArgumentException::class, static fn (): array => $page164(5, 4, $first['next']));
};

$tests['pragma index xinfo foreignkey current source next164 rejects negative offset'] = static function (TestRunner $t) use ($page164): void {
    $t->throws(InvalidArgumentException::class, static fn (): array => $page164(-1));
};

$tests['pragma index xinfo foreignkey current source next164 rejects zero limit'] = static function (TestRunner $t) use ($page164): void {
    $t->throws(InvalidArgumentException::class, static fn (): array => $page164(0, 0));
};

return $tests;
