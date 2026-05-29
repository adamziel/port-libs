<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteAttachedSchemaCatalog;
use PortLibs\LibSqlite\SQLiteFreelistTrunkPage;
use PortLibs\LibSqlite\SQLiteIndexLeafPage;
use PortLibs\LibSqlite\SQLitePointerMapEntry;
use PortLibs\LibSqlite\SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext;
use PortLibs\LibSqlite\SQLiteRecord;
use PortLibs\LibSqlite\SQLiteSchemaRecord;
use PortLibs\LibSqlite\SQLiteTableLeafCell;
use PortLibs\LibSqlite\SQLiteTableLeafPage;

$pageSize157 = 1024;
$header157 = static function (int $pageCount, int $largestRootPage, int $firstFreelist = 0, int $freelistCount = 0) use ($pageSize157): string {
    $page = str_repeat("\0", $pageSize157);
    $page = substr_replace($page, "SQLite format 3\0", 0, 16);
    $page = substr_replace($page, pack('n', $pageSize157), 16, 2);
    $page[18] = "\x01";
    $page[19] = "\x01";
    $page = substr_replace($page, pack('N', $pageCount), 28, 4);
    $page = substr_replace($page, pack('N', $firstFreelist), 32, 4);
    $page = substr_replace($page, pack('N', $freelistCount), 36, 4);
    $page = substr_replace($page, pack('N', $largestRootPage), 52, 4);
    $page = substr_replace($page, pack('N', 1), 56, 4);

    return $page;
};
$putPointer157 = static fn (string $page, int $pageNumber, int $type, int $parent): string => substr_replace($page, chr($type) . pack('N', $parent), 5 * ($pageNumber - 3), 5);
$schemaCell157 = static fn (array $values, int $rowId): string => SQLiteTableLeafCell::encode($rowId, SQLiteRecord::encode($values));
$schemaRows157 = [
    ['table', 'wp_options', 'wp_options', 4, 'CREATE TABLE wp_options(option_id integer primary key, option_name text, option_value text, autoload text)'],
    ['index', 'wp_options_name', 'wp_options', 5, 'CREATE UNIQUE INDEX wp_options_name ON wp_options(option_name COLLATE nocase DESC, autoload)'],
    ['index', 'wp_options_value_expr', 'wp_options', 6, 'CREATE INDEX wp_options_value_expr ON wp_options(lower(option_value), autoload DESC)'],
    ['table', 'wp_option_names', 'wp_option_names', 7, 'CREATE TABLE wp_option_names(name text primary key)'],
    ['index', 'wp_options_alias', 'wp_options', 4, 'CREATE INDEX wp_options_alias ON wp_options(autoload)'],
    ['table', 'wp_free_root', 'wp_free_root', 8, 'CREATE TABLE wp_free_root(id integer primary key)'],
];
$database157 = static function (array $schemaRows, array $pointerEntries, int $largestRootPage, int $firstFreelist = 0, int $freelistCount = 0) use ($pageSize157, $header157, $putPointer157, $schemaCell157): string {
    $pages = [
        1 => SQLiteTableLeafPage::assemble(
            array_map(static fn (array $row, int $index): string => $schemaCell157($row, $index + 1), $schemaRows, array_keys($schemaRows)),
            $pageSize157,
            100,
            $header157(8, $largestRootPage, $firstFreelist, $freelistCount),
        ),
        2 => str_repeat("\0", $pageSize157),
        3 => SQLiteFreelistTrunkPage::assemble(null, [8], $pageSize157),
        4 => SQLiteTableLeafPage::assemble([], $pageSize157),
        5 => SQLiteIndexLeafPage::assemble([], $pageSize157),
        6 => SQLiteIndexLeafPage::assemble([], $pageSize157),
        7 => SQLiteTableLeafPage::assemble([], $pageSize157),
        8 => SQLiteTableLeafPage::assemble([], $pageSize157),
    ];
    foreach ($pointerEntries as [$pageNumber, $type, $parent]) {
        $pages[2] = $putPointer157($pages[2], $pageNumber, $type, $parent);
    }
    ksort($pages);

    return implode('', $pages);
};

$currentDatabase157 = $database157($schemaRows157, [
    [3, SQLitePointerMapEntry::BTREE_PAGE, 4],
    [4, SQLitePointerMapEntry::ROOT_PAGE, 0],
    [5, SQLitePointerMapEntry::BTREE_PAGE, 4],
    [6, SQLitePointerMapEntry::ROOT_PAGE, 0],
    [7, SQLitePointerMapEntry::ROOT_PAGE, 0],
    [8, SQLitePointerMapEntry::BTREE_PAGE, 7],
], 7, 3, 2);
$nextDatabase157 = $database157(array_slice($schemaRows157, 0, 4), [
    [3, SQLitePointerMapEntry::BTREE_PAGE, 4],
    [4, SQLitePointerMapEntry::ROOT_PAGE, 0],
    [5, SQLitePointerMapEntry::ROOT_PAGE, 0],
    [6, SQLitePointerMapEntry::ROOT_PAGE, 0],
    [7, SQLitePointerMapEntry::ROOT_PAGE, 0],
    [8, SQLitePointerMapEntry::FREE_PAGE, 0],
], 7);

$record157 = static fn (string $type, string $name, string $table, int $root, ?string $sql = null, int $rowid = 1): SQLiteSchemaRecord => new SQLiteSchemaRecord($type, $name, $table, $root, $sql, $rowid);
$catalog157 = static function () use ($record157): SQLiteAttachedSchemaCatalog {
    $catalog = new SQLiteAttachedSchemaCatalog([
        $record157('table', 'wp_options', 'wp_options', 4, 'CREATE TABLE wp_options(option_id integer primary key, option_name text, option_value text, autoload text)', 1),
        $record157('index', 'wp_options_name', 'wp_options', 5, 'CREATE UNIQUE INDEX wp_options_name ON wp_options(option_name COLLATE nocase DESC, autoload)', 2),
        $record157('index', 'wp_options_value_expr', 'wp_options', 6, 'CREATE INDEX wp_options_value_expr ON wp_options(lower(option_value), autoload DESC)', 3),
        $record157('table', 'wp_option_names', 'wp_option_names', 7, 'CREATE TABLE wp_option_names(name text primary key)', 4),
    ], [
        $record157('table', 'wp_options', 'wp_options', 9, 'CREATE TABLE wp_options(option_name text, autoload text)', 5),
        $record157('index', 'wp_options_temp_name', 'wp_options', 10, 'CREATE INDEX wp_options_temp_name ON wp_options(upper(option_name) COLLATE rtrim, autoload DESC)', 6),
    ]);
    $catalog->attach('archive', '/srv/wp/archive.sqlite', [
        $record157('table', 'wp_options', 'wp_options', 11, 'CREATE TABLE wp_options(option_name text, autoload text)', 7),
        $record157('index', 'wp_options_archive_name', 'wp_options', 12, 'CREATE INDEX wp_options_archive_name ON wp_options(option_name, autoload DESC)', 8),
    ]);

    return $catalog;
};
$schemas157 = static function (int $missing = 5): array {
    $rows = [['rowid' => 1, 'option_name' => 'siteurl']];
    for ($i = 1; $i <= $missing; $i++) {
        $rows[] = ['rowid' => 'missing-' . $i, 'option_name' => 'missing_' . $i];
    }
    $rows[] = ['rowid' => 'null-option', 'option_name' => null];

    return [
        'main' => [
            'tables' => [
                'wp_option_names' => [['rowid' => 1, 'name' => 'siteurl']],
                'wp_options' => $rows,
            ],
            'foreignKeys' => [
                ['id' => 157, 'table' => 'wp_options', 'parent' => 'wp_option_names', 'columns' => [
                    ['child' => 'option_name', 'parent' => 'name', 'affinity' => 'text', 'collation' => 'nocase'],
                ]],
            ],
        ],
    ];
};
$page157 = static fn (
    int $offset = 0,
    int $limit = 157,
    ?array $cursor = null,
    ?string $currentDatabase = null,
    ?array $currentSchemas = null,
    ?string $nextDatabase = null,
    ?array $nextSchemas = null,
    string $indexSql = 'PRAGMA main.index_xinfo(wp_options_name)',
    string $foreignKeySql = 'PRAGMA main.foreign_key_check(wp_options)',
    bool $tableValued = false,
): array => SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext::currentNextPage157(
    $catalog157(),
    $currentDatabase ?? $currentDatabase157,
    $currentSchemas ?? $schemas157(),
    $catalog157(),
    $nextDatabase ?? $nextDatabase157,
    $nextSchemas ?? $schemas157(0),
    $indexSql,
    $foreignKeySql,
    $offset,
    $limit,
    'PRAGMA integrity_check',
    $tableValued,
    $cursor,
    $catalog157(),
    $catalog157(),
);
$valueAt157 = static function (mixed $value, string $path): mixed {
    foreach (explode('.', $path) as $part) {
        if (is_array($value) && array_key_exists($part, $value)) {
            $value = $value[$part];
            continue;
        }
        $value = $value[(int) $part];
    }

    return $value;
};

$default157 = static fn (): array => $page157();
$blockedNext157 = static fn (): array => $page157(nextDatabase: $currentDatabase157, nextSchemas: $schemas157(2));
$tableValued157 = static fn (): array => $page157(indexSql: "pragma_index_xinfo('wp_options_archive_name','archive')", tableValued: true);
$cases157 = [
    'status ok after next repair' => [$default157, 'status', 'ok'],
    'default limit' => [$default157, 'limit', 157],
    'total combines current and next rowsets' => [$default157, 'total', 16],
    'count full page' => [$default157, 'count', 16],
    'complete full page' => [$default157, 'complete', true],
    'next cursor null' => [$default157, 'next', null],
    'next ready true' => [$default157, 'next_state.ready', true],
    'current xinfo count' => [$default157, 'current.index_xinfo', 3],
    'current root errors' => [$default157, 'current.rootpage_errors', 5],
    'current fk count' => [$default157, 'current.foreign_key', 5],
    'current key columns' => [$default157, 'current.key_columns', 2],
    'current auxiliary rowid' => [$default157, 'current.rowid_auxiliary', 1],
    'current target index' => [$default157, 'current.target_index', 'wp_options_name'],
    'current target schema' => [$default157, 'current.target_schema', 'main'],
    'current collations include nocase' => [$default157, 'current.collations.0', 'NOCASE'],
    'next fk cleared' => [$default157, 'next_counts.foreign_key', 0],
    'next root errors cleared' => [$default157, 'next_counts.rootpage_errors', 0],
    'next xinfo retained' => [$default157, 'next_counts.index_xinfo', 3],
    'delta fk cleared count' => [$default157, 'delta.foreign_key', -5],
    'delta root errors cleared count' => [$default157, 'delta.rootpage_errors', -5],
    'delta fk cleared true' => [$default157, 'delta.foreign_key_cleared', true],
    'delta integrity cleared true' => [$default157, 'delta.integrity_cleared', true],
    'delta ready true' => [$default157, 'delta.ready', true],
    'source index sql normalized' => [$default157, 'current_source.index_xinfo_sql', 'pragma main.index_xinfo(wp_options_name)'],
    'source fk sql normalized' => [$default157, 'current_source.foreign_key_sql', 'pragma main.foreign_key_check(wp_options)'],
    'source table valued false' => [$default157, 'current_source.index_table_valued', false],
    'next source differs from current' => [static fn (): array => ['diff' => $page157()['current_source']['database'] !== $page157()['next_source']['database']], 'diff', true],
    'row0 current side' => [$default157, 'rows.0.side', 'current'],
    'row0 xinfo kind' => [$default157, 'rows.0.kind', 'index_xinfo'],
    'row0 xinfo name' => [$default157, 'rows.0.name', 'option_name'],
    'row0 xinfo desc' => [$default157, 'rows.0.desc', 1],
    'row0 xinfo coll' => [$default157, 'rows.0.coll', 'NOCASE'],
    'row2 rowid auxiliary' => [$default157, 'rows.2.cid', -1],
    'row3 integrity duplicate' => [$default157, 'rows.3.source', 'duplicate_rootpage'],
    'row5 pointer conflict' => [$default157, 'rows.5.source', 'pointer_map_conflict'],
    'row6 largest root mismatch' => [$default157, 'rows.6.source', 'largest_root_mismatch'],
    'row7 freelist conflict' => [$default157, 'rows.7.source', 'freelist_conflict'],
    'row8 fk kind' => [$default157, 'rows.8.kind', 'foreign_key_check'],
    'row8 fk id' => [$default157, 'rows.8.fkid', 157],
    'row8 fk rowid' => [$default157, 'rows.8.rowid', 'missing-1'],
    'row13 next side' => [$default157, 'rows.13.side', 'next'],
    'row13 next xinfo' => [$default157, 'rows.13.kind', 'index_xinfo'],
    'blocked next status' => [$blockedNext157, 'status', 'blocked'],
    'blocked next first blocker' => [$blockedNext157, 'next_state.blocking.0', 'index_rootpage_integrity'],
    'blocked next fk blocker' => [$blockedNext157, 'next_state.blocking.1', 'foreign_key_check'],
    'blocked next fk count' => [$blockedNext157, 'next_counts.foreign_key', 2],
    'table valued source flag' => [$tableValued157, 'current_source.index_table_valued', true],
    'table valued source sql' => [$tableValued157, 'current_source.index_xinfo_sql', "pragma_index_xinfo('wp_options_archive_name','archive')"],
    'table valued archive schema' => [$tableValued157, 'rows.0.schema', 'archive'],
    'table valued archive target' => [$tableValued157, 'rows.0.target', 'wp_options_archive_name'],
    'table valued archive autoload desc' => [$tableValued157, 'rows.1.desc', 1],
];

$tests = [];
foreach ($cases157 as $name => [$factory, $path, $expected]) {
    $tests['pragma index xinfo foreignkey current source next157 ' . $name] = static function (TestRunner $t) use ($factory, $path, $expected, $valueAt157): void {
        $t->same($expected, $valueAt157($factory(), $path));
    };
}

$tests['pragma index xinfo foreignkey current source next157 paginates stable current next source'] = static function (TestRunner $t) use ($page157): void {
    $first = $page157(0, 6);
    $second = $page157(6, 6, $first['next']);
    $third = $page157(13, 6, ['source_id' => $second['source_id'], 'offset' => 13]);

    $t->same(6, $first['count']);
    $t->same(['source_id' => $first['source_id'], 'offset' => 6], $first['next']);
    $t->same('largest_root_mismatch', $second['rows'][0]['source']);
    $t->same('next', $third['rows'][0]['side']);
    $t->same(null, $third['next']);
};

$tests['pragma index xinfo foreignkey current source next157 accepts source only cursor'] = static function (TestRunner $t) use ($page157): void {
    $first = $page157(0, 6);
    $second = $page157(6, 6, ['source_id' => $first['source_id']]);

    $t->same(6, $second['offset']);
    $t->same($first['source_id'], $second['source_id']);
    $t->same('largest_root_mismatch', $second['rows'][0]['source']);
};

$tests['pragma index xinfo foreignkey current source next157 source changes with next schema rows'] = static function (TestRunner $t) use ($page157, $schemas157): void {
    $first = $page157();
    $second = $page157(nextSchemas: $schemas157(1));

    $t->same(true, $first['source_id'] !== $second['source_id']);
    $t->same(0, $first['next_counts']['foreign_key']);
    $t->same(1, $second['next_counts']['foreign_key']);
};

$tests['pragma index xinfo foreignkey current source next157 rejects stale source cursor'] = static function (TestRunner $t) use ($page157, $currentDatabase157): void {
    $first = $page157(0, 6);
    $t->throws(InvalidArgumentException::class, static fn (): array => $page157(6, 6, $first['next'], nextDatabase: $currentDatabase157));
};

$tests['pragma index xinfo foreignkey current source next157 rejects stale offset cursor'] = static function (TestRunner $t) use ($page157): void {
    $first = $page157(0, 6);
    $t->throws(InvalidArgumentException::class, static fn (): array => $page157(7, 6, $first['next']));
};

$tests['pragma index xinfo foreignkey current source next157 rejects negative offset'] = static function (TestRunner $t) use ($page157): void {
    $t->throws(InvalidArgumentException::class, static fn (): array => $page157(-1, 157));
};

$tests['pragma index xinfo foreignkey current source next157 rejects zero limit'] = static function (TestRunner $t) use ($page157): void {
    $t->throws(InvalidArgumentException::class, static fn (): array => $page157(0, 0));
};

$tests['pragma index xinfo foreignkey current source next157 rejects non index pragma'] = static function (TestRunner $t) use ($page157): void {
    $t->throws(InvalidArgumentException::class, static fn (): array => $page157(indexSql: 'PRAGMA table_info(wp_options)'));
};

return $tests;
