<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext;
use PortLibs\LibSqlite\SQLitePragmaSchemaCatalog;
use PortLibs\LibSqlite\SQLiteSchemaRecord;

$record168 = static fn (string $type, string $name, string $table, int $root, ?string $sql, int $rowid): SQLiteSchemaRecord => new SQLiteSchemaRecord($type, $name, $table, $root, $sql, $rowid);

$currentRecords168 = [
    $record168('table', 'wp_option_names', 'wp_option_names', 4, 'CREATE TABLE wp_option_names(name TEXT COLLATE NOCASE, blog_id INTEGER, UNIQUE(name, blog_id))', 1),
    $record168('index', 'sqlite_autoindex_wp_option_names_1', 'wp_option_names', 5, null, 2),
    $record168('table', 'wp_options', 'wp_options', 6, 'CREATE TABLE wp_options(option_id INTEGER PRIMARY KEY, option_name TEXT, blog_id TEXT, autoload TEXT, FOREIGN KEY(option_name, blog_id) REFERENCES wp_option_names(name, blog_id))', 3),
    $record168('index', 'wp_options_autoload_name', 'wp_options', 7, 'CREATE INDEX wp_options_autoload_name ON wp_options(autoload, option_name)', 4),
];
$nextRecords168 = $currentRecords168;

$currentTables168 = [
    'wp_option_names' => [
        ['rowid' => 1, 'name' => 'siteurl', 'blog_id' => 1],
        ['rowid' => 2, 'name' => 'home', 'blog_id' => 1],
    ],
    'wp_options' => [
        ['rowid' => 1, 'option_id' => 1, 'option_name' => 'SITEURL', 'blog_id' => '1', 'autoload' => 'yes'],
        ['rowid' => 2, 'option_id' => 2, 'option_name' => 'HOME', 'blog_id' => '1', 'autoload' => 'yes'],
        ['rowid' => 3, 'option_id' => 3, 'option_name' => 'transient_missing', 'blog_id' => '2', 'autoload' => 'no'],
    ],
];
$nextTables168 = [
    'wp_option_names' => [
        ['rowid' => 1, 'name' => 'siteurl', 'blog_id' => 1],
        ['rowid' => 2, 'name' => 'home', 'blog_id' => 1],
        ['rowid' => 3, 'name' => 'TRANSIENT_MISSING', 'blog_id' => 2],
    ],
    'wp_options' => $currentTables168['wp_options'],
];

$page168 = static fn (
    int $offset = 0,
    int $limit = 168,
    ?array $cursor = null,
    ?array $nextTables = null,
    string $indexSql = 'PRAGMA index_xinfo(sqlite_autoindex_wp_option_names_1)',
    bool $tableValued = false,
): array => SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext::currentNextPageFromCatalog164(
    $currentRecords168,
    $currentTables168,
    $nextRecords168,
    $nextTables ?? $nextTables168,
    $indexSql,
    $offset,
    $limit,
    $cursor,
    $tableValued,
);

$catalog168 = static fn (): SQLitePragmaSchemaCatalog => new SQLitePragmaSchemaCatalog($currentRecords168);
$foreignKeys168 = static fn (): array => PortLibs\LibSqlite\SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext::foreignKeysFromCatalog161($currentRecords168);

$valueAt168 = static function (mixed $value, string $path): mixed {
    foreach (explode('.', $path) as $part) {
        if (is_array($value) && array_key_exists($part, $value)) {
            $value = $value[$part];
            continue;
        }
        $value = $value[(int) $part];
    }

    return $value;
};

$default168 = static fn (): array => $page168();
$blocked168 = static fn (): array => $page168(nextTables: $currentTables168);
$xinfo168 = static fn (): array => $catalog168()->execute('PRAGMA index_xinfo(sqlite_autoindex_wp_option_names_1)');
$xinfoTvf168 = static fn (): array => $catalog168()->executeTableValuedPragma("pragma_index_xinfo('sqlite_autoindex_wp_option_names_1')");

$cases168 = [
    'status ok after nocase parent repair' => [$default168, 'status', 'ok'],
    'limit default' => [$default168, 'limit', 168],
    'total current next rows' => [$default168, 'total', 9],
    'count current next rows' => [$default168, 'count', 9],
    'complete default page' => [$default168, 'complete', true],
    'no next cursor on complete page' => [$default168, 'next', null],
    'current index xinfo count' => [$default168, 'current.index_xinfo', 3],
    'next index xinfo count' => [$default168, 'next_counts.index_xinfo', 3],
    'current index admission count' => [$default168, 'current.index_admissions', 1],
    'next index admission count' => [$default168, 'next_counts.index_admissions', 1],
    'current has no nocase admission blocker' => [$default168, 'current.index_blockers', 0],
    'next has no nocase admission blocker' => [$default168, 'next_counts.index_blockers', 0],
    'current has one missing parent violation' => [$default168, 'current.foreign_key_violations', 1],
    'next clears missing parent violation' => [$default168, 'next_counts.foreign_key_violations', 0],
    'current total blockers' => [$default168, 'current.total_blockers', 1],
    'next total blockers' => [$default168, 'next_counts.total_blockers', 0],
    'delta index xinfo unchanged' => [$default168, 'delta.index_xinfo', 0],
    'delta admission unchanged' => [$default168, 'delta.index_admissions', 0],
    'delta blockers unchanged' => [$default168, 'delta.index_blockers', 0],
    'delta fk clears one row' => [$default168, 'delta.foreign_key_violations', -1],
    'delta total blockers clears one row' => [$default168, 'delta.total_blockers', -1],
    'delta cleared true' => [$default168, 'delta.cleared', true],
    'next ready true' => [$default168, 'next_state.ready', true],
    'next blocking empty' => [$default168, 'next_state.blocking', []],
    'current target schema' => [$default168, 'current.target_schema', 'main'],
    'current target index' => [$default168, 'current.target_index', 'sqlite_autoindex_wp_option_names_1'],
    'current fk table list' => [$default168, 'current.foreign_key_tables', ['wp_options']],
    'current parent index list' => [$default168, 'current.parent_indexes', ['sqlite_autoindex_wp_option_names_1']],
    'source foreign key source' => [$default168, 'current_source.foreign_key_source', 'pragma_foreign_key_list'],
    'source table canonicalizer' => [$default168, 'current_source.table_key_source', 'sqlite_schema_casefold'],
    'source column canonicalizer' => [$default168, 'current_source.column_key_source', 'pragma_table_xinfo_casefold'],
    'source derived fk count' => [$default168, 'current_source.derived_foreign_keys', 1],
    'source normalized index sql' => [$default168, 'current_source.index_xinfo_sql', 'pragma index_xinfo(sqlite_autoindex_wp_option_names_1)'],
    'xinfo pragma status' => [$xinfo168, 'status', 'ok'],
    'xinfo pragma row count' => [$xinfo168, 'rows.2.seqno', 2],
    'xinfo first key name' => [$xinfo168, 'rows.0.name', 'name'],
    'xinfo first key cid' => [$xinfo168, 'rows.0.cid', 0],
    'xinfo first key collates nocase' => [$xinfo168, 'rows.0.coll', 'NOCASE'],
    'xinfo first key flag' => [$xinfo168, 'rows.0.key', 1],
    'xinfo second key name' => [$xinfo168, 'rows.1.name', 'blog_id'],
    'xinfo second key cid' => [$xinfo168, 'rows.1.cid', 1],
    'xinfo second key collates binary' => [$xinfo168, 'rows.1.coll', 'BINARY'],
    'xinfo rowid aux name null' => [$xinfo168, 'rows.2.name', null],
    'xinfo rowid aux cid' => [$xinfo168, 'rows.2.cid', -1],
    'xinfo rowid aux key flag' => [$xinfo168, 'rows.2.key', 0],
    'table valued xinfo pragma status' => [$xinfoTvf168, 'status', 'ok'],
    'table valued xinfo target' => [$xinfoTvf168, 'target', 'sqlite_autoindex_wp_option_names_1'],
    'table valued xinfo first collates nocase' => [$xinfoTvf168, 'rows.0.coll', 'NOCASE'],
    'row0 current side' => [$default168, 'rows.0.side', 'current'],
    'row0 current xinfo kind' => [$default168, 'rows.0.kind', 'index_xinfo'],
    'row0 current xinfo collates nocase' => [$default168, 'rows.0.coll', 'NOCASE'],
    'row1 current xinfo collates binary' => [$default168, 'rows.1.coll', 'BINARY'],
    'row2 current xinfo aux rowid' => [$default168, 'rows.2.cid', -1],
    'row3 current admission ok' => [$default168, 'rows.3.status', 'ok'],
    'row3 current admission index' => [$default168, 'rows.3.index', 'sqlite_autoindex_wp_option_names_1'],
    'row3 current admission columns' => [$default168, 'rows.3.columns', ['name', 'blog_id']],
    'row3 current admission collations' => [$default168, 'rows.3.collations', ['NOCASE', 'BINARY']],
    'row4 current violation rowid' => [$default168, 'rows.4.rowid', 3],
    'row4 current violation parent' => [$default168, 'rows.4.parent', 'wp_option_names'],
    'row4 current violation fkid' => [$default168, 'rows.4.fkid', 0],
    'row5 next side starts' => [$default168, 'rows.5.side', 'next'],
    'row8 next admission ok' => [$default168, 'rows.8.status', 'ok'],
    'row8 next admission collations' => [$default168, 'rows.8.collations', ['NOCASE', 'BINARY']],
    'blocked status when repair absent' => [$blocked168, 'status', 'blocked'],
    'blocked next ready false' => [$blocked168, 'next_state.ready', false],
    'blocked reason is only foreign key check' => [$blocked168, 'next_state.blocking', ['foreign_key_check']],
    'blocked keeps nocase parent index admitted' => [$blocked168, 'next_counts.index_blockers', 0],
    'blocked has one next violation' => [$blocked168, 'next_counts.foreign_key_violations', 1],
    'derived fk parent collation nocase' => [$foreignKeys168, '0.columns.0.collation', 'nocase'],
    'derived fk parent collation binary' => [$foreignKeys168, '0.columns.1.collation', 'binary'],
    'derived fk parent affinity text' => [$foreignKeys168, '0.columns.0.affinity', 'text'],
    'derived fk parent affinity integer' => [$foreignKeys168, '0.columns.1.affinity', 'integer'],
];

$tests = [];
foreach ($cases168 as $name => [$factory, $path, $expected]) {
    $tests['pragma index xinfo foreignkey current source next168 autoindex nocase ' . $name] = static function (TestRunner $t) use ($factory, $path, $expected, $valueAt168): void {
        $t->same($expected, $valueAt168($factory(), $path));
    };
}

$tests['pragma index xinfo foreignkey current source next168 paginates nocase autoindex stream'] = static function (TestRunner $t) use ($page168): void {
    $first = $page168(0, 4);
    $second = $page168(4, 4, $first['next']);
    $third = $page168(8, 4, $second['next']);

    $t->same(4, $first['count']);
    $t->same(['source_id' => $first['source_id'], 'offset' => 4], $first['next']);
    $t->same('foreign_key_check', $second['rows'][0]['kind']);
    $t->same('index_admission', $third['rows'][0]['kind']);
    $t->same(null, $third['next']);
};

$tests['pragma index xinfo foreignkey current source next168 supports table-valued xinfo source'] = static function (TestRunner $t) use ($page168): void {
    $result = $page168(indexSql: "pragma_index_xinfo('sqlite_autoindex_wp_option_names_1')", tableValued: true);

    $t->same('ok', $result['status']);
    $t->same(true, $result['current_source']['table_valued_index_xinfo']);
    $t->same("pragma_index_xinfo('sqlite_autoindex_wp_option_names_1')", $result['current_source']['index_xinfo_sql']);
    $t->same('NOCASE', $result['rows'][0]['coll']);
    $t->same(['NOCASE', 'BINARY'], $result['rows'][3]['collations']);
};

$tests['pragma index xinfo foreignkey current source next168 rejects stale source cursor'] = static function (TestRunner $t) use ($page168, $currentTables168): void {
    $first = $page168(0, 4);
    $t->throws(InvalidArgumentException::class, static fn (): array => $page168(4, 4, $first['next'], nextTables: $currentTables168));
};

$tests['pragma index xinfo foreignkey current source next168 rejects stale offset cursor'] = static function (TestRunner $t) use ($page168): void {
    $first = $page168(0, 4);
    $t->throws(InvalidArgumentException::class, static fn (): array => $page168(5, 4, $first['next']));
};

return $tests;
