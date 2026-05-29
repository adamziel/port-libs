<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteAttachedSchemaCatalog;
use PortLibs\LibSqlite\SQLiteIndexLeafPage;
use PortLibs\LibSqlite\SQLitePointerMapEntry;
use PortLibs\LibSqlite\SQLitePragmaIndexIntegrityCursorCurrentSourceNext;
use PortLibs\LibSqlite\SQLiteRecord;
use PortLibs\LibSqlite\SQLiteSchemaRecord;
use PortLibs\LibSqlite\SQLiteTableLeafCell;
use PortLibs\LibSqlite\SQLiteTableLeafPage;

$pageSize = 1024;
$record = static fn (string $type, string $name, string $table, ?int $root, ?string $sql = null, int $rowid = 1): SQLiteSchemaRecord => new SQLiteSchemaRecord($type, $name, $table, $root, $sql, $rowid);

$catalogFactory = static function (bool $archiveShadow = false) use ($record): SQLiteAttachedSchemaCatalog {
    $catalog = new SQLiteAttachedSchemaCatalog([
        $record('table', 'wp_options', 'wp_options', 4, 'CREATE TABLE wp_options(option_id INTEGER PRIMARY KEY, option_name TEXT, option_value TEXT, autoload TEXT)', 1),
        $record('index', 'wp_options_name', 'wp_options', 5, 'CREATE UNIQUE INDEX wp_options_name ON wp_options(option_name COLLATE NOCASE DESC, autoload)', 2),
        $record('index', 'wp_options_value_expr', 'wp_options', 6, "CREATE INDEX wp_options_value_expr ON wp_options(json_extract(option_value, '$.plugin'), lower(option_name) COLLATE nocase, autoload DESC)", 3),
    ], [
        $record('table', 'wp_options', 'wp_options', 7, 'CREATE TABLE wp_options(option_name TEXT, autoload TEXT)', 1),
        $record('index', 'wp_options_temp_name', 'wp_options', 8, 'CREATE INDEX wp_options_temp_name ON wp_options(upper(option_name) COLLATE rtrim, autoload DESC)', 2),
    ]);
    $catalog->attach('archive', '/srv/wp/archive.sqlite', [
        $record('table', 'wp_options', 'wp_options', 9, 'CREATE TABLE wp_options(option_name TEXT, autoload TEXT)', 1),
        $record('index', $archiveShadow ? 'wp_options_value_expr' : 'wp_options_archive_name', 'wp_options', 10, 'CREATE INDEX ' . ($archiveShadow ? 'wp_options_value_expr' : 'wp_options_archive_name') . ' ON wp_options(option_name COLLATE rtrim DESC, autoload)', 2),
    ]);

    return $catalog;
};

$headerPage = static function (int $pageCount, int $largestRootPage) use ($pageSize): string {
    $page = str_repeat("\0", $pageSize);
    $page = substr_replace($page, "SQLite format 3\0", 0, 16);
    $page = substr_replace($page, pack('n', $pageSize), 16, 2);
    $page[18] = "\x01";
    $page[19] = "\x01";
    $page = substr_replace($page, pack('N', $pageCount), 28, 4);
    $page = substr_replace($page, pack('N', $largestRootPage), 52, 4);
    $page = substr_replace($page, pack('N', 1), 56, 4);

    return $page;
};
$putPointerMapEntry = static function (string $page, int $pageNumber, int $type, int $parent): string {
    return substr_replace($page, chr($type) . pack('N', $parent), 5 * ($pageNumber - 3), 5);
};
$schemaCell = static fn (array $values, int $rowId): string => SQLiteTableLeafCell::encode($rowId, SQLiteRecord::encode($values));
$schemaDatabase = static function (array $schemaRows, int $pageCount, int $largestRootPage, array $pointerMapEntries) use ($headerPage, $putPointerMapEntry, $schemaCell, $pageSize): string {
    $pages = [
        1 => SQLiteTableLeafPage::assemble(
            array_map(static fn (array $row, int $index): string => $schemaCell($row, $index + 1), $schemaRows, array_keys($schemaRows)),
            $pageSize,
            100,
            $headerPage($pageCount, $largestRootPage),
        ),
        2 => str_repeat("\0", $pageSize),
    ];
    foreach ($pointerMapEntries as $entry) {
        $pages[2] = $putPointerMapEntry($pages[2], $entry[0], $entry[1], $entry[2]);
    }
    for ($pageNumber = 3; $pageNumber <= $pageCount; $pageNumber++) {
        $pages[$pageNumber] ??= in_array($pageNumber, [5, 6, 8, 10], true)
            ? SQLiteIndexLeafPage::assemble([], $pageSize)
            : SQLiteTableLeafPage::assemble([], $pageSize);
    }
    ksort($pages);

    return implode('', $pages);
};

$schemaRows = [
    ['table', 'wp_options', 'wp_options', 4, 'CREATE TABLE wp_options(option_id integer primary key, option_name text, option_value text, autoload text)'],
    ['index', 'wp_options_name', 'wp_options', 5, 'CREATE UNIQUE INDEX wp_options_name ON wp_options(option_name, autoload)'],
    ['index', 'wp_options_value_expr', 'wp_options', 6, "CREATE INDEX wp_options_value_expr ON wp_options(json_extract(option_value, '$.plugin'), lower(option_name), autoload)"],
];
$validDatabase = $schemaDatabase($schemaRows, 10, 6, [
    [3, SQLitePointerMapEntry::BTREE_PAGE, 4],
    [4, SQLitePointerMapEntry::ROOT_PAGE, 0],
    [5, SQLitePointerMapEntry::ROOT_PAGE, 0],
    [6, SQLitePointerMapEntry::ROOT_PAGE, 0],
    [7, SQLitePointerMapEntry::ROOT_PAGE, 0],
    [8, SQLitePointerMapEntry::ROOT_PAGE, 0],
    [9, SQLitePointerMapEntry::ROOT_PAGE, 0],
    [10, SQLitePointerMapEntry::ROOT_PAGE, 0],
]);
$pointerMismatchDatabase = $schemaDatabase($schemaRows, 10, 6, [
    [3, SQLitePointerMapEntry::BTREE_PAGE, 4],
    [4, SQLitePointerMapEntry::ROOT_PAGE, 0],
    [5, SQLitePointerMapEntry::ROOT_PAGE, 0],
    [6, SQLitePointerMapEntry::BTREE_PAGE, 4],
    [7, SQLitePointerMapEntry::ROOT_PAGE, 0],
    [8, SQLitePointerMapEntry::ROOT_PAGE, 0],
    [9, SQLitePointerMapEntry::ROOT_PAGE, 0],
    [10, SQLitePointerMapEntry::ROOT_PAGE, 0],
]);
$mutatedDatabase = $validDatabase;
$mutatedDatabase[48] = "\x02";

$valueAt = static function (mixed $value, string $path): mixed {
    foreach (explode('.', $path) as $part) {
        if (is_array($value) && array_key_exists($part, $value)) {
            $value = $value[$part];
            continue;
        }
        $value = is_numeric($part) ? $value[(int) $part] : $value[$part];
    }

    return $value;
};

$page = static fn (
    ?string $sql = null,
    ?string $db = null,
    int $offset = 0,
    int $limit = 133,
    string $integritySql = 'PRAGMA integrity_check',
    bool $tableValued = false,
    ?array $cursor = null,
    ?SQLiteAttachedSchemaCatalog $catalog = null,
): array => SQLitePragmaIndexIntegrityCursorCurrentSourceNext::page(
    $catalog ?? $catalogFactory(),
    $sql ?? 'PRAGMA main.index_list(wp_options)',
    $db ?? $pointerMismatchDatabase,
    $offset,
    $limit,
    $integritySql,
    $tableValued,
    $cursor,
);

$default = static fn (): array => $page();
$valid = static fn (): array => $page('PRAGMA main.index_list(wp_options)', $validDatabase);
$temp = static fn (): array => $page('PRAGMA index_list(wp_options)', $validDatabase);
$archive = static fn (): array => $page("pragma_index_list('wp_options','archive')", $validDatabase, 0, 133, 'PRAGMA quick_check', true);
$changedCatalog = static fn (): array => $page('PRAGMA main.index_list(wp_options)', $validDatabase, 0, 133, 'PRAGMA integrity_check', false, null, $catalogFactory(true));
$mutated = static fn (): array => $page('PRAGMA main.index_list(wp_options)', $mutatedDatabase);

$cases = [
    'default status blocked' => [$default, 'status', 'blocked'],
    'default total' => [$default, 'total', 13],
    'default count' => [$default, 'count', 13],
    'default complete' => [$default, 'complete', true],
    'default next null' => [$default, 'next', null],
    'source id length' => [static fn (): array => ['length' => strlen($default()['source_id'])], 'length', 64],
    'database hash length' => [static fn (): array => ['length' => strlen($default()['current_source']['database'])], 'length', 64],
    'catalog hash length' => [static fn (): array => ['length' => strlen($default()['current_source']['catalog'])], 'length', 64],
    'normalized index list sql' => [$default, 'current_source.index_list_sql', 'pragma main.index_list(wp_options)'],
    'normalized integrity sql' => [$default, 'current_source.integrity_sql', 'pragma integrity_check'],
    'table valued false' => [$default, 'current_source.table_valued', false],
    'current index list count' => [$default, 'current.index_list', 2],
    'current index xinfo count' => [$default, 'current.index_xinfo', 7],
    'current rootpage count' => [$default, 'current.rootpage', 4],
    'current rootpage errors' => [$default, 'current.rootpage_errors', 1],
    'current target schema' => [$default, 'current.target_schema', 'main'],
    'current target table' => [$default, 'current.target_table', 'wp_options'],
    'current index names' => [$default, 'current.indexes', ['wp_options_name', 'wp_options_value_expr']],
    'row0 index list kind' => [$default, 'rows.0.kind', 'index_list'],
    'row0 index list source' => [$default, 'rows.0.source', 'index_list'],
    'row0 index list unique' => [$default, 'rows.0.unique', 1],
    'row0 index list message' => [$default, 'rows.0.message', 'index_list main.wp_options seq 0 index wp_options_name unique 1 origin c partial 0'],
    'row1 xinfo kind' => [$default, 'rows.1.kind', 'index_xinfo'],
    'row1 xinfo source' => [$default, 'rows.1.source', 'index_xinfo'],
    'row1 xinfo table' => [$default, 'rows.1.table', 'wp_options'],
    'row1 xinfo index' => [$default, 'rows.1.index', 'wp_options_name'],
    'row1 xinfo collation' => [$default, 'rows.1.coll', 'NOCASE'],
    'row2 xinfo desc' => [$default, 'rows.2.desc', 0],
    'row3 xinfo rowid cid' => [$default, 'rows.3.cid', -1],
    'row4 table root source' => [$default, 'rows.4.source', 'rootpage_integrity'],
    'row4 table root status' => [$default, 'rows.4.page_status', 'ok'],
    'row5 name index root status' => [$default, 'rows.5.page_status', 'ok'],
    'row6 expression index list' => [$default, 'rows.6.index', 'wp_options_value_expr'],
    'row7 expression cid' => [$default, 'rows.7.cid', -2],
    'row8 expression lower collation' => [$default, 'rows.8.coll', 'NOCASE'],
    'row9 expression autoload desc' => [$default, 'rows.9.desc', 1],
    'row10 expression rowid' => [$default, 'rows.10.cid', -1],
    'row12 expression root pointer status' => [$default, 'rows.12.page_status', 'pointer_map'],
    'row12 expression root pointer parent' => [$default, 'rows.12.pointer_map_parent', 4],
    'valid status ok' => [$valid, 'status', 'ok'],
    'valid root errors zero' => [$valid, 'current.rootpage_errors', 0],
    'valid expression root ok' => [$valid, 'rows.12.page_status', 'ok'],
    'temp schema wins unqualified' => [$temp, 'current.target_schema', 'temp'],
    'temp table target' => [$temp, 'current.target_table', 'wp_options'],
    'temp index count' => [$temp, 'current.index_list', 1],
    'temp xinfo collation' => [$temp, 'rows.1.coll', 'RTRIM'],
    'archive table valued true' => [$archive, 'current_source.table_valued', true],
    'archive schema' => [$archive, 'current.target_schema', 'archive'],
    'archive normalized sql' => [$archive, 'current_source.index_list_sql', "pragma_index_list('wp_options','archive')"],
    'archive index' => [$archive, 'rows.0.index', 'wp_options_archive_name'],
    'archive collation' => [$archive, 'rows.1.coll', 'RTRIM'],
    'changed catalog source changes' => [static fn (): array => ['changed' => $valid()['source_id'] !== $changedCatalog()['source_id']], 'changed', true],
    'mutated database source changes' => [static fn (): array => ['changed' => $valid()['source_id'] !== $mutated()['source_id']], 'changed', true],
    'limit four count' => [static fn (): array => $page('PRAGMA main.index_list(wp_options)', $pointerMismatchDatabase, 0, 4), 'count', 4],
    'limit four next offset' => [static fn (): array => $page('PRAGMA main.index_list(wp_options)', $pointerMismatchDatabase, 0, 4), 'next.offset', 4],
    'offset six current row expression index list' => [static fn (): array => $page('PRAGMA main.index_list(wp_options)', $pointerMismatchDatabase, 6, 4), 'current_row.kind', 'index_list'],
    'offset six next row expression xinfo' => [static fn (): array => $page('PRAGMA main.index_list(wp_options)', $pointerMismatchDatabase, 6, 4), 'next_row.kind', 'index_xinfo'],
    'past tail count zero' => [static fn (): array => $page('PRAGMA main.index_list(wp_options)', $pointerMismatchDatabase, 20, 5), 'count', 0],
];

$tests = [];
foreach ($cases as $name => [$callback, $path, $expected]) {
    $tests['pragma index integrity cursor current source next133 ' . $name] = static function (TestRunner $t) use ($callback, $valueAt, $path, $expected): void {
        $t->same($expected, $valueAt($callback(), $path));
    };
}

$tests['pragma index integrity cursor current source next133 resumes stable cursor'] = static function (TestRunner $t) use ($page, $pointerMismatchDatabase): void {
    $first = $page('PRAGMA main.index_list(wp_options)', $pointerMismatchDatabase, 0, 4);
    $second = $page('PRAGMA main.index_list(wp_options)', $pointerMismatchDatabase, 4, 4, 'PRAGMA integrity_check', false, $first['next']);
    $third = $page('PRAGMA main.index_list(wp_options)', $pointerMismatchDatabase, 8, 4, 'PRAGMA integrity_check', false, $second['next']);

    $t->same(['source_id' => $first['source_id'], 'offset' => 4], $first['next']);
    $t->same('rootpage', $second['rows'][0]['kind']);
    $t->same('index_list', $second['rows'][2]['kind']);
    $t->same(['source_id' => $first['source_id'], 'offset' => 8], $second['next']);
    $t->same(-2, $third['rows'][0]['cid']);
    $t->same('rootpage', $third['rows'][3]['kind']);
};

$tests['pragma index integrity cursor current source next133 accepts source-only cursor'] = static function (TestRunner $t) use ($page, $pointerMismatchDatabase): void {
    $first = $page('PRAGMA main.index_list(wp_options)', $pointerMismatchDatabase, 0, 4);
    $second = $page('PRAGMA main.index_list(wp_options)', $pointerMismatchDatabase, 4, 4, 'PRAGMA integrity_check', false, ['source_id' => $first['source_id']]);

    $t->same(4, $second['offset']);
    $t->same('rootpage', $second['rows'][0]['kind']);
};

$tests['pragma index integrity cursor current source next133 rejects stale database cursor'] = static function (TestRunner $t) use ($page, $validDatabase, $mutatedDatabase): void {
    $first = $page('PRAGMA main.index_list(wp_options)', $validDatabase, 0, 4);
    $t->throws(InvalidArgumentException::class, static fn () => $page('PRAGMA main.index_list(wp_options)', $mutatedDatabase, 4, 4, 'PRAGMA integrity_check', false, $first['next']));
};

$tests['pragma index integrity cursor current source next133 rejects stale catalog cursor'] = static function (TestRunner $t) use ($page, $validDatabase, $catalogFactory): void {
    $first = $page('PRAGMA main.index_list(wp_options)', $validDatabase, 0, 4);
    $t->throws(InvalidArgumentException::class, static fn () => $page('PRAGMA main.index_list(wp_options)', $validDatabase, 4, 4, 'PRAGMA integrity_check', false, $first['next'], $catalogFactory(true)));
};

$tests['pragma index integrity cursor current source next133 rejects stale sql cursor'] = static function (TestRunner $t) use ($page, $validDatabase): void {
    $first = $page('PRAGMA main.index_list(wp_options)', $validDatabase, 0, 4);
    $t->throws(InvalidArgumentException::class, static fn () => $page('PRAGMA temp.index_list(wp_options)', $validDatabase, 4, 4, 'PRAGMA integrity_check', false, $first['next']));
};

$tests['pragma index integrity cursor current source next133 rejects stale integrity sql cursor'] = static function (TestRunner $t) use ($page, $validDatabase): void {
    $first = $page('PRAGMA main.index_list(wp_options)', $validDatabase, 0, 4);
    $t->throws(InvalidArgumentException::class, static fn () => $page('PRAGMA main.index_list(wp_options)', $validDatabase, 4, 4, 'PRAGMA quick_check', false, $first['next']));
};

$tests['pragma index integrity cursor current source next133 rejects stale offset cursor'] = static function (TestRunner $t) use ($page, $validDatabase): void {
    $first = $page('PRAGMA main.index_list(wp_options)', $validDatabase, 0, 4);
    $t->throws(InvalidArgumentException::class, static fn () => $page('PRAGMA main.index_list(wp_options)', $validDatabase, 5, 4, 'PRAGMA integrity_check', false, $first['next']));
};

$tests['pragma index integrity cursor current source next133 rejects negative offset'] = static function (TestRunner $t) use ($page): void {
    $t->throws(InvalidArgumentException::class, static fn () => $page('PRAGMA main.index_list(wp_options)', null, -1));
};

$tests['pragma index integrity cursor current source next133 rejects zero limit'] = static function (TestRunner $t) use ($page): void {
    $t->throws(InvalidArgumentException::class, static fn () => $page('PRAGMA main.index_list(wp_options)', null, 0, 0));
};

$tests['pragma index integrity cursor current source next133 rejects non index list pragma'] = static function (TestRunner $t) use ($page): void {
    $t->throws(InvalidArgumentException::class, static fn () => $page('PRAGMA main.index_xinfo(wp_options_name)'));
};

return $tests;
