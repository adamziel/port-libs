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

$pageSize158 = 1024;
$header158 = static function (int $pageCount, int $largestRootPage, int $firstFreelist = 0, int $freelistCount = 0) use ($pageSize158): string {
    $page = str_repeat("\0", $pageSize158);
    $page = substr_replace($page, "SQLite format 3\0", 0, 16);
    $page = substr_replace($page, pack('n', $pageSize158), 16, 2);
    $page[18] = "\x01";
    $page[19] = "\x01";
    $page = substr_replace($page, pack('N', $pageCount), 28, 4);
    $page = substr_replace($page, pack('N', $firstFreelist), 32, 4);
    $page = substr_replace($page, pack('N', $freelistCount), 36, 4);
    $page = substr_replace($page, pack('N', $largestRootPage), 52, 4);
    $page = substr_replace($page, pack('N', 1), 56, 4);

    return $page;
};
$putPointerMap158 = static fn (string $page, int $pageNumber, int $type, int $parent): string => substr_replace($page, chr($type) . pack('N', $parent), 5 * ($pageNumber - 3), 5);
$schemaCell158 = static fn (array $values, int $rowId): string => SQLiteTableLeafCell::encode($rowId, SQLiteRecord::encode($values));
$database158 = static function (
    array $schemaRows,
    int $pageCount,
    int $largestRootPage,
    array $pointerMapEntries,
    array $pageImages = [],
    int $firstFreelist = 0,
    int $freelistCount = 0,
) use ($header158, $putPointerMap158, $schemaCell158, $pageSize158): string {
    $pages = [
        1 => SQLiteTableLeafPage::assemble(
            array_map(static fn (array $row, int $index): string => $schemaCell158($row, $index + 1), $schemaRows, array_keys($schemaRows)),
            $pageSize158,
            100,
            $header158($pageCount, $largestRootPage, $firstFreelist, $freelistCount),
        ),
        2 => str_repeat("\0", $pageSize158),
    ];
    foreach ($pointerMapEntries as [$pageNumber, $type, $parent]) {
        $pages[2] = $putPointerMap158($pages[2], $pageNumber, $type, $parent);
    }
    for ($pageNumber = 3; $pageNumber <= $pageCount; $pageNumber++) {
        $pages[$pageNumber] = $pageImages[$pageNumber] ?? SQLiteTableLeafPage::assemble([], $pageSize158);
    }
    ksort($pages);

    return implode('', $pages);
};

$schemaRows158 = [
    ['table', 'wp_options', 'wp_options', 4, 'CREATE TABLE wp_options(option_id integer primary key, option_name text, option_value text, autoload text)'],
    ['index', 'wp_options_name', 'wp_options', 5, 'CREATE UNIQUE INDEX wp_options_name ON wp_options(option_name COLLATE nocase DESC, autoload)'],
    ['index', 'wp_options_alias', 'wp_options', 4, 'CREATE INDEX wp_options_alias ON wp_options(autoload)'],
    ['table', 'wp_free_root', 'wp_free_root', 6, 'CREATE TABLE wp_free_root(id integer primary key)'],
];
$dirtyDatabase158 = $database158($schemaRows158, 6, 6, [
    [3, SQLitePointerMapEntry::BTREE_PAGE, 4],
    [4, SQLitePointerMapEntry::ROOT_PAGE, 0],
    [5, SQLitePointerMapEntry::BTREE_PAGE, 4],
    [6, SQLitePointerMapEntry::ROOT_PAGE, 0],
], [
    3 => SQLiteFreelistTrunkPage::assemble(null, [6], $pageSize158),
    5 => SQLiteIndexLeafPage::assemble([], $pageSize158),
], 3, 2);
$cleanDatabase158 = $database158(array_slice($schemaRows158, 0, 2), 5, 5, [
    [3, SQLitePointerMapEntry::BTREE_PAGE, 4],
    [4, SQLitePointerMapEntry::ROOT_PAGE, 0],
    [5, SQLitePointerMapEntry::ROOT_PAGE, 0],
], [
    5 => SQLiteIndexLeafPage::assemble([], $pageSize158),
]);

$record158 = static fn (string $type, string $name, string $table, int $root, ?string $sql = null): SQLiteSchemaRecord => new SQLiteSchemaRecord($type, $name, $table, $root, $sql ?? 'CREATE ' . strtoupper($type) . ' ' . $name, $root);
$catalog158 = static fn (bool $withIndex = true, bool $withParent = true): SQLiteAttachedSchemaCatalog => new SQLiteAttachedSchemaCatalog(array_values(array_filter([
    $record158('table', 'wp_options', 'wp_options', 4, 'CREATE TABLE wp_options(option_id integer primary key, option_name text, option_value text, autoload text)'),
    $withIndex ? $record158('index', 'wp_options_name', 'wp_options', 5, 'CREATE UNIQUE INDEX wp_options_name ON wp_options(option_name COLLATE nocase DESC, autoload)') : null,
    $withParent ? $record158('table', 'wp_option_names', 'wp_option_names', 7, 'CREATE TABLE wp_option_names(name text primary key)') : null,
])));
$schemas158 = static function (int $missing = 4): array {
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
                ['id' => 158, 'table' => 'wp_options', 'parent' => 'wp_option_names', 'columns' => [
                    ['child' => 'option_name', 'parent' => 'name', 'affinity' => 'text', 'collation' => 'nocase'],
                ]],
            ],
        ],
    ];
};

$page158 = static fn (
    int $offset = 0,
    int $limit = 158,
    ?array $cursor = null,
    string $indexSql = 'PRAGMA main.index_xinfo(wp_options_name)',
    string $fkSql = 'PRAGMA main.foreign_key_check(wp_options)',
    ?string $currentDatabase = null,
    ?array $currentSchemas = null,
    ?SQLiteAttachedSchemaCatalog $currentCatalog = null,
    ?string $nextDatabase = null,
    ?array $nextSchemas = null,
    ?SQLiteAttachedSchemaCatalog $nextCatalog = null,
    bool $tableValued = false,
): array => SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext::currentNextPage158(
    $currentCatalog ?? $catalog158(),
    $currentDatabase ?? $dirtyDatabase158,
    $currentSchemas ?? $schemas158(),
    $nextCatalog ?? $catalog158(),
    $nextDatabase ?? $cleanDatabase158,
    $nextSchemas ?? $schemas158(0),
    $indexSql,
    $fkSql,
    $offset,
    $limit,
    'PRAGMA integrity_check',
    $tableValued,
    $cursor,
    $currentCatalog ?? $catalog158(),
    $nextCatalog ?? $catalog158(),
);

$valueAt158 = static function (mixed $value, string $path): mixed {
    foreach (explode('.', $path) as $part) {
        if (is_array($value) && array_key_exists($part, $value)) {
            $value = $value[$part];
            continue;
        }
        $value = $value[(int) $part];
    }

    return $value;
};

$default158 = static fn (): array => $page158();
$blockedNext158 = static fn (): array => $page158(nextDatabase: $dirtyDatabase158, nextSchemas: $schemas158(1));
$cases158 = [
    'status ok after repair' => [$default158, 'status', 'ok'],
    'limit default' => [$default158, 'limit', 158],
    'total current plus next rows' => [$default158, 'total', 14],
    'count all rows' => [$default158, 'count', 14],
    'complete all rows' => [$default158, 'complete', true],
    'next null on full page' => [$default158, 'next', null],
    'next state ready' => [$default158, 'next_state.ready', true],
    'current xinfo count' => [$default158, 'current.index_xinfo', 3],
    'current integrity blockers' => [$default158, 'current.integrity_root', 4],
    'current fk blockers' => [$default158, 'current.foreign_key', 4],
    'current total blockers' => [$default158, 'current.blockers', 8],
    'current schema' => [$default158, 'current.target_schema', 'main'],
    'current target index' => [$default158, 'current.target_index', 'wp_options_name'],
    'current target table from fk' => [$default158, 'current.target_table', 'wp_options'],
    'next xinfo preserved' => [$default158, 'next_counts.index_xinfo', 3],
    'next root clean' => [$default158, 'next_counts.integrity_root', 0],
    'next fk clean' => [$default158, 'next_counts.foreign_key', 0],
    'next blockers clean' => [$default158, 'next_counts.blockers', 0],
    'delta root cleared' => [$default158, 'delta.integrity_root', -4],
    'delta fk cleared' => [$default158, 'delta.foreign_key', -4],
    'delta blockers cleared' => [$default158, 'delta.blockers', -8],
    'delta cleared true' => [$default158, 'delta.cleared', true],
    'source index normalized' => [$default158, 'current_source.index_xinfo_sql', 'pragma main.index_xinfo(wp_options_name)'],
    'source fk normalized' => [$default158, 'current_source.foreign_key_sql', 'pragma main.foreign_key_check(wp_options)'],
    'source integrity normalized' => [$default158, 'current_source.integrity_sql', 'pragma integrity_check'],
    'source table valued false' => [$default158, 'current_source.index_table_valued', false],
    'next source database differs' => [static fn (): array => ['diff' => $page158()['current_source']['database'] !== $page158()['next_source']['database']], 'diff', true],
    'source id length' => [static fn (): array => ['len' => strlen($page158()['source_id'])], 'len', 64],
    'row0 side current' => [$default158, 'rows.0.side', 'current'],
    'row0 xinfo kind' => [$default158, 'rows.0.kind', 'index_xinfo'],
    'row0 target' => [$default158, 'rows.0.target', 'wp_options_name'],
    'row0 name' => [$default158, 'rows.0.name', 'option_name'],
    'row0 coll' => [$default158, 'rows.0.coll', 'NOCASE'],
    'row1 name autoload' => [$default158, 'rows.1.name', 'autoload'],
    'row2 rowid cid' => [$default158, 'rows.2.cid', -1],
    'row3 integrity kind' => [$default158, 'rows.3.kind', 'integrity_root'],
    'row3 duplicate source' => [$default158, 'rows.3.source', 'duplicate_rootpage'],
    'row4 duplicate index' => [$default158, 'rows.4.name', 'wp_options_alias'],
    'row5 pointer conflict' => [$default158, 'rows.5.source', 'pointer_map_conflict'],
    'row6 freelist conflict' => [$default158, 'rows.6.source', 'freelist_conflict'],
    'row7 fk kind' => [$default158, 'rows.7.kind', 'foreign_key_check'],
    'row7 fk side current' => [$default158, 'rows.7.side', 'current'],
    'row7 fk rowid' => [$default158, 'rows.7.rowid', 'missing-1'],
    'row7 fk id' => [$default158, 'rows.7.fkid', 158],
    'row10 last current fk' => [$default158, 'rows.10.rowid', 'missing-4'],
    'row11 next side' => [$default158, 'rows.11.side', 'next'],
    'row11 next kind' => [$default158, 'rows.11.kind', 'index_xinfo'],
    'blocked next status' => [$blockedNext158, 'status', 'blocked'],
    'blocked next blockers include root' => [$blockedNext158, 'next_state.blocking.0', 'index_root_integrity'],
    'blocked next blockers include fk' => [$blockedNext158, 'next_state.blocking.1', 'foreign_key_check'],
    'blocked next fk one' => [$blockedNext158, 'next_counts.foreign_key', 1],
];

$tests = [];
foreach ($cases158 as $name => [$factory, $path, $expected]) {
    $tests['pragma index xinfo foreignkey current source next158 ' . $name] = static function (TestRunner $t) use ($factory, $path, $expected, $valueAt158): void {
        $t->same($expected, $valueAt158($factory(), $path));
    };
}

$tests['pragma index xinfo foreignkey current source next158 paginates stable current next source'] = static function (TestRunner $t) use ($page158): void {
    $first = $page158(0, 5);
    $second = $page158(5, 5, $first['next']);
    $third = $page158(10, 5, $second['next']);

    $t->same(5, $first['count']);
    $t->same(['source_id' => $first['source_id'], 'offset' => 5], $first['next']);
    $t->same('pointer_map_conflict', $second['rows'][0]['source']);
    $t->same('foreign_key_check', $third['rows'][0]['kind']);
    $t->same(null, $third['next']);
};

$tests['pragma index xinfo foreignkey current source next158 accepts table valued index SQL'] = static function (TestRunner $t) use ($page158): void {
    $result = $page158(
        indexSql: "pragma_index_xinfo('wp_options_name','main')",
        fkSql: "SELECT * FROM pragma_foreign_key_check('main.wp_options')",
        tableValued: true,
    );

    $t->same(true, $result['current_source']['index_table_valued']);
    $t->same("pragma_index_xinfo('wp_options_name','main')", $result['current_source']['index_xinfo_sql']);
    $t->same("select * from pragma_foreign_key_check('main.wp_options')", $result['current_source']['foreign_key_sql']);
    $t->same(4, $result['current']['foreign_key']);
};

$tests['pragma index xinfo foreignkey current source next158 source changes with next schema'] = static function (TestRunner $t) use ($page158, $schemas158): void {
    $first = $page158(0, 4);
    $second = $page158(0, 4, nextSchemas: $schemas158(2));

    $t->same(true, $first['source_id'] !== $second['source_id']);
    $t->same(true, $first['next_source']['schema_hash'] !== $second['next_source']['schema_hash']);
};

$tests['pragma index xinfo foreignkey current source next158 rejects stale source cursor'] = static function (TestRunner $t) use ($page158, $dirtyDatabase158): void {
    $first = $page158(0, 4);
    $t->throws(InvalidArgumentException::class, static fn (): array => $page158(4, 4, $first['next'], nextDatabase: $dirtyDatabase158));
};

$tests['pragma index xinfo foreignkey current source next158 rejects stale offset cursor'] = static function (TestRunner $t) use ($page158): void {
    $first = $page158(0, 4);
    $t->throws(InvalidArgumentException::class, static fn (): array => $page158(5, 4, $first['next']));
};

$tests['pragma index xinfo foreignkey current source next158 reports missing index target as blocker'] = static function (TestRunner $t) use ($page158): void {
    $result = $page158(indexSql: 'PRAGMA main.index_xinfo(wp_missing_name)');

    $t->same('blocked', $result['status']);
    $t->same(0, $result['current']['index_xinfo']);
    $t->same('index_xinfo', $result['next_state']['blocking'][0]);
};

$tests['pragma index xinfo foreignkey current source next158 rejects negative offset'] = static function (TestRunner $t) use ($catalog158, $dirtyDatabase158, $cleanDatabase158, $schemas158): void {
    $t->throws(InvalidArgumentException::class, static fn (): array => SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext::currentNextPage158(
        $catalog158(),
        $dirtyDatabase158,
        $schemas158(),
        $catalog158(),
        $cleanDatabase158,
        $schemas158(0),
        'PRAGMA main.index_xinfo(wp_options_name)',
        'PRAGMA main.foreign_key_check(wp_options)',
        -1,
    ));
};

$tests['pragma index xinfo foreignkey current source next158 rejects zero limit'] = static function (TestRunner $t) use ($catalog158, $dirtyDatabase158, $cleanDatabase158, $schemas158): void {
    $t->throws(InvalidArgumentException::class, static fn (): array => SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext::currentNextPage158(
        $catalog158(),
        $dirtyDatabase158,
        $schemas158(),
        $catalog158(),
        $cleanDatabase158,
        $schemas158(0),
        'PRAGMA main.index_xinfo(wp_options_name)',
        'PRAGMA main.foreign_key_check(wp_options)',
        0,
        0,
    ));
};

return $tests;
