<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext;
use PortLibs\LibSqlite\SQLiteSchemaRecord;

$record163 = static fn (string $type, string $name, string $table, int $root, ?string $sql, int $rowid): SQLiteSchemaRecord => new SQLiteSchemaRecord($type, $name, $table, $root, $sql, $rowid);

$currentRecords163 = [
    $record163('table', 'wp_blogs', 'wp_blogs', 4, 'CREATE TABLE wp_blogs(blog_id INTEGER PRIMARY KEY, domain TEXT)', 1),
    $record163('table', 'wp_option_scope', 'wp_option_scope', 5, 'CREATE TABLE wp_option_scope(site_key TEXT, locale TEXT, PRIMARY KEY(site_key, locale)) WITHOUT ROWID', 2),
    $record163('index', 'sqlite_autoindex_wp_option_scope_1', 'wp_option_scope', 6, null, 3),
    $record163('table', 'wp_options', 'wp_options', 7, 'CREATE TABLE wp_options(option_id INTEGER PRIMARY KEY, blog_id INTEGER REFERENCES wp_blogs, site_key TEXT, locale TEXT, option_name TEXT, FOREIGN KEY(site_key, locale) REFERENCES wp_option_scope)', 4),
    $record163('index', 'wp_options_name', 'wp_options', 8, 'CREATE INDEX wp_options_name ON wp_options(option_name, blog_id)', 5),
];
$nextRecords163 = $currentRecords163;

$currentTables163 = [
    'wp_blogs' => [
        ['rowid' => 1, 'blog_id' => 1, 'domain' => 'main.example'],
    ],
    'wp_option_scope' => [
        ['rowid' => 'main-en', 'site_key' => 'main', 'locale' => 'en_US'],
    ],
    'wp_options' => [
        ['rowid' => 1, 'option_id' => 1, 'blog_id' => 1, 'site_key' => 'main', 'locale' => 'en_US', 'option_name' => 'siteurl'],
        ['rowid' => 2, 'option_id' => 2, 'blog_id' => 99, 'site_key' => 'main', 'locale' => 'fr_FR', 'option_name' => 'home'],
        ['rowid' => 3, 'option_id' => 3, 'blog_id' => null, 'site_key' => 'missing', 'locale' => 'en_US', 'option_name' => 'active_plugins'],
    ],
];
$nextTables163 = [
    'wp_blogs' => [
        ['rowid' => 1, 'blog_id' => 1, 'domain' => 'main.example'],
        ['rowid' => 99, 'blog_id' => 99, 'domain' => 'archive.example'],
    ],
    'wp_option_scope' => [
        ['rowid' => 'main-en', 'site_key' => 'main', 'locale' => 'en_US'],
        ['rowid' => 'main-fr', 'site_key' => 'main', 'locale' => 'fr_FR'],
        ['rowid' => 'missing-en', 'site_key' => 'missing', 'locale' => 'en_US'],
    ],
    'wp_options' => $currentTables163['wp_options'],
];

$page163 = static fn (
    int $offset = 0,
    int $limit = 163,
    ?array $cursor = null,
    ?array $nextTables = null,
    ?array $nextRecords = null,
    string $indexSql = 'PRAGMA index_xinfo(wp_options_name)',
    bool $tableValued = false,
): array => SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext::currentNextPageFromCatalog163(
    $currentRecords163,
    $currentTables163,
    $nextRecords ?? $nextRecords163,
    $nextTables ?? $nextTables163,
    $indexSql,
    $offset,
    $limit,
    $cursor,
    $tableValued,
);

$valueAt163 = static function (mixed $value, string $path): mixed {
    foreach (explode('.', $path) as $part) {
        if (is_array($value) && array_key_exists($part, $value)) {
            $value = $value[$part];
            continue;
        }
        $value = $value[(int) $part];
    }

    return $value;
};

$default163 = static fn (): array => $page163();
$blockedNext163 = static fn (): array => $page163(nextTables: $currentTables163);
$foreignKeys163 = static fn (): array => SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext::foreignKeysFromCatalog163($currentRecords163);
$tableValued163 = static fn (): array => $page163(indexSql: "pragma_index_xinfo('wp_options_name')", tableValued: true);

$cases163 = [
    'status ok after implicit parent repair' => [$default163, 'status', 'ok'],
    'default limit' => [$default163, 'limit', 163],
    'total rows' => [$default163, 'total', 13],
    'count rows' => [$default163, 'count', 13],
    'complete rows' => [$default163, 'complete', true],
    'next cursor null' => [$default163, 'next', null],
    'current fk source' => [$default163, 'current_source.foreign_key_source', 'pragma_foreign_key_list'],
    'current implicit parent count' => [$default163, 'current_source.implicit_parent_keys', 3],
    'next implicit parent count' => [$default163, 'next_source.implicit_parent_keys', 3],
    'derived fks count' => [$default163, 'current_source.derived_foreign_keys', 2],
    'index sql normalized' => [$default163, 'current_source.index_xinfo_sql', 'pragma index_xinfo(wp_options_name)'],
    'table valued false' => [$default163, 'current_source.table_valued_index_xinfo', false],
    'source id length' => [static fn (): array => ['len' => strlen($page163()['source_id'])], 'len', 64],
    'records hash length' => [static fn (): array => ['len' => strlen($page163()['current_source']['records'])], 'len', 64],
    'tables hash length' => [static fn (): array => ['len' => strlen($page163()['current_source']['tables'])], 'len', 64],
    'current xinfo count' => [$default163, 'current.index_xinfo', 3],
    'next xinfo count' => [$default163, 'next_counts.index_xinfo', 3],
    'current admissions' => [$default163, 'current.index_admissions', 2],
    'next admissions' => [$default163, 'next_counts.index_admissions', 2],
    'current index blockers' => [$default163, 'current.index_blockers', 0],
    'next index blockers' => [$default163, 'next_counts.index_blockers', 0],
    'current fk violations' => [$default163, 'current.foreign_key_violations', 3],
    'next fk violations' => [$default163, 'next_counts.foreign_key_violations', 0],
    'current total blockers' => [$default163, 'current.total_blockers', 3],
    'next total blockers' => [$default163, 'next_counts.total_blockers', 0],
    'target schema' => [$default163, 'current.target_schema', 'main'],
    'target index' => [$default163, 'current.target_index', 'wp_options_name'],
    'foreign key tables' => [$default163, 'current.foreign_key_tables', ['wp_options']],
    'parent indexes' => [$default163, 'current.parent_indexes', ['rowid-primary-key', 'sqlite_autoindex_wp_option_scope_1']],
    'delta fk' => [$default163, 'delta.foreign_key_violations', -3],
    'delta total' => [$default163, 'delta.total_blockers', -3],
    'delta cleared' => [$default163, 'delta.cleared', true],
    'next ready' => [$default163, 'next_state.ready', true],
    'next blocking empty' => [$default163, 'next_state.blocking', []],
    'row0 xinfo' => [$default163, 'rows.0.kind', 'index_xinfo'],
    'row0 option name' => [$default163, 'rows.0.name', 'option_name'],
    'row1 blog id' => [$default163, 'rows.1.name', 'blog_id'],
    'row2 rowid aux' => [$default163, 'rows.2.key', 0],
    'row3 rowid admission' => [$default163, 'rows.3.index', 'rowid-primary-key'],
    'row3 columns blog id' => [$default163, 'rows.3.columns', ['blog_id']],
    'row4 autoindex admission' => [$default163, 'rows.4.index', 'sqlite_autoindex_wp_option_scope_1'],
    'row4 implicit composite columns' => [$default163, 'rows.4.columns', ['site_key', 'locale']],
    'row5 missing blog fk' => [$default163, 'rows.5.rowid', 2],
    'row6 second missing scope fk' => [$default163, 'rows.6.rowid', 2],
    'row7 missing scope fk' => [$default163, 'rows.7.rowid', 3],
    'row8 next side' => [$default163, 'rows.8.side', 'next'],
    'row11 next rowid admission' => [$default163, 'rows.11.index', 'rowid-primary-key'],
    'row12 next autoindex admission' => [$default163, 'rows.12.index', 'sqlite_autoindex_wp_option_scope_1'],
    'blocked status' => [$blockedNext163, 'status', 'blocked'],
    'blocked ready' => [$blockedNext163, 'next_state.ready', false],
    'blocked next fk violations' => [$blockedNext163, 'next_counts.foreign_key_violations', 3],
    'blocked next blocking' => [$blockedNext163, 'next_state.blocking', ['foreign_key_check']],
    'fk0 parent' => [$foreignKeys163, '0.parent', 'wp_blogs'],
    'fk0 implicit parent' => [$foreignKeys163, '0.columns.0.implicit_parent', true],
    'fk0 parent column blog id' => [$foreignKeys163, '0.columns.0.parent', 'blog_id'],
    'fk0 affinity integer' => [$foreignKeys163, '0.columns.0.affinity', 'integer'],
    'fk1 parent' => [$foreignKeys163, '1.parent', 'wp_option_scope'],
    'fk1 first implicit parent' => [$foreignKeys163, '1.columns.0.parent', 'site_key'],
    'fk1 second implicit parent' => [$foreignKeys163, '1.columns.1.parent', 'locale'],
    'fk1 without rowid child flag false' => [$foreignKeys163, '1.without_rowid', false],
    'table valued flag' => [$tableValued163, 'current_source.table_valued_index_xinfo', true],
    'table valued sql' => [$tableValued163, 'current_source.index_xinfo_sql', "pragma_index_xinfo('wp_options_name')"],
];

$tests = [];
foreach ($cases163 as $name => [$factory, $path, $expected]) {
    $tests['pragma index xinfo foreignkey current source next163 ' . $name] = static function (TestRunner $t) use ($factory, $path, $expected, $valueAt163): void {
        $t->same($expected, $valueAt163($factory(), $path));
    };
}

$tests['pragma index xinfo foreignkey current source next163 paginates implicit parent source'] = static function (TestRunner $t) use ($page163): void {
    $first = $page163(0, 5);
    $second = $page163(5, 5, $first['next']);
    $third = $page163(10, 5, $second['next']);

    $t->same(5, $first['count']);
    $t->same(['source_id' => $first['source_id'], 'offset' => 5], $first['next']);
    $t->same('foreign_key_check', $second['rows'][0]['kind']);
    $t->same('next', $third['rows'][0]['side']);
    $t->same(null, $third['next']);
};

$tests['pragma index xinfo foreignkey current source next163 source changes with next implicit parent rows'] = static function (TestRunner $t) use ($page163, $currentTables163): void {
    $first = $page163();
    $second = $page163(nextTables: $currentTables163);

    $t->same(true, $first['source_id'] !== $second['source_id']);
    $t->same(0, $first['next_counts']['foreign_key_violations']);
    $t->same(3, $second['next_counts']['foreign_key_violations']);
};

$tests['pragma index xinfo foreignkey current source next163 source changes with parent primary key catalog'] = static function (TestRunner $t) use ($page163, $currentRecords163, $record163): void {
    $first = $page163();
    $changed = $currentRecords163;
    $changed[1] = $record163('table', 'wp_option_scope', 'wp_option_scope', 5, 'CREATE TABLE wp_option_scope(site_key TEXT, locale TEXT, PRIMARY KEY(locale, site_key)) WITHOUT ROWID', 2);
    $second = $page163(nextRecords: $changed);

    $t->same(true, $first['source_id'] !== $second['source_id']);
    $t->same(['locale', 'site_key'], array_column(SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext::foreignKeysFromCatalog163($changed)[1]['columns'], 'parent'));
};

$tests['pragma index xinfo foreignkey current source next163 rejects stale source cursor'] = static function (TestRunner $t) use ($page163, $currentTables163): void {
    $first = $page163(0, 5);
    $t->throws(InvalidArgumentException::class, static fn (): array => $page163(5, 5, $first['next'], nextTables: $currentTables163));
};

$tests['pragma index xinfo foreignkey current source next163 rejects stale offset cursor'] = static function (TestRunner $t) use ($page163): void {
    $first = $page163(0, 5);
    $t->throws(InvalidArgumentException::class, static fn (): array => $page163(6, 5, $first['next']));
};

$tests['pragma index xinfo foreignkey current source next163 rejects unresolved implicit parent key'] = static function (TestRunner $t) use ($record163): void {
    $records = [
        $record163('table', 'parent', 'parent', 2, 'CREATE TABLE parent(value TEXT)', 1),
        $record163('table', 'child', 'child', 3, 'CREATE TABLE child(parent_value TEXT REFERENCES parent)', 2),
    ];

    $t->throws(InvalidArgumentException::class, static fn (): array => SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext::foreignKeysFromCatalog163($records));
};

$tests['pragma index xinfo foreignkey current source next163 rejects negative offset'] = static function (TestRunner $t) use ($page163): void {
    $t->throws(InvalidArgumentException::class, static fn (): array => $page163(-1));
};

$tests['pragma index xinfo foreignkey current source next163 rejects zero limit'] = static function (TestRunner $t) use ($page163): void {
    $t->throws(InvalidArgumentException::class, static fn (): array => $page163(0, 0));
};

return $tests;
