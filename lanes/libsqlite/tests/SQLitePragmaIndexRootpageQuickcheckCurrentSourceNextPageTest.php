<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteAttachedSchemaCatalog;
use PortLibs\LibSqlite\SQLiteIndexLeafPage;
use PortLibs\LibSqlite\SQLitePointerMapEntry;
use PortLibs\LibSqlite\SQLitePragmaIndexRootpageQuickcheckCurrentSourceNext;
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
        $record('table', 'wp_options', 'wp_options', 7, 'CREATE TABLE wp_options(option_name TEXT, option_value TEXT, autoload TEXT)', 1),
        $record('index', 'wp_options_temp_expr', 'wp_options', 8, 'CREATE INDEX wp_options_temp_expr ON wp_options(upper(option_name) COLLATE rtrim, autoload DESC)', 2),
    ]);
    $catalog->attach('archive', '/srv/wp/archive.sqlite', [
        $record('table', 'wp_options', 'wp_options', 9, 'CREATE TABLE wp_options(option_id INTEGER PRIMARY KEY, option_name TEXT, option_value TEXT, autoload TEXT)', 1),
        $record('index', $archiveShadow ? 'wp_options_value_expr' : 'wp_options_archive_expr', 'wp_options', 10, "CREATE INDEX " . ($archiveShadow ? 'wp_options_value_expr' : 'wp_options_archive_expr') . " ON wp_options(json_extract(option_value, '$.legacy'), option_name COLLATE rtrim DESC)", 2),
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
$putPointerMapEntry = static fn (string $page, int $pageNumber, int $type, int $parent): string => substr_replace($page, chr($type) . pack('N', $parent), 5 * ($pageNumber - 3), 5);
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
    ['index', 'wp_options_name', 'wp_options', 5, 'CREATE UNIQUE INDEX wp_options_name ON wp_options(option_name)'],
    ['index', 'wp_options_value_expr', 'wp_options', 6, "CREATE INDEX wp_options_value_expr ON wp_options(json_extract(option_value, '$.plugin'), option_name)"],
    ['table', 'wp_posts', 'wp_posts', 7, 'CREATE TABLE wp_posts(ID integer primary key, post_title text)'],
    ['index', 'wp_posts_title', 'wp_posts', 8, 'CREATE INDEX wp_posts_title ON wp_posts(post_title)'],
];
$validDatabase = $schemaDatabase($schemaRows, 10, 8, [
    [3, SQLitePointerMapEntry::BTREE_PAGE, 4],
    [4, SQLitePointerMapEntry::ROOT_PAGE, 0],
    [5, SQLitePointerMapEntry::ROOT_PAGE, 0],
    [6, SQLitePointerMapEntry::ROOT_PAGE, 0],
    [7, SQLitePointerMapEntry::ROOT_PAGE, 0],
    [8, SQLitePointerMapEntry::ROOT_PAGE, 0],
    [9, SQLitePointerMapEntry::ROOT_PAGE, 0],
    [10, SQLitePointerMapEntry::ROOT_PAGE, 0],
]);
$pointerMismatchDatabase = $schemaDatabase($schemaRows, 10, 8, [
    [3, SQLitePointerMapEntry::BTREE_PAGE, 4],
    [4, SQLitePointerMapEntry::ROOT_PAGE, 0],
    [5, SQLitePointerMapEntry::BTREE_PAGE, 4],
    [6, SQLitePointerMapEntry::BTREE_PAGE, 4],
    [7, SQLitePointerMapEntry::ROOT_PAGE, 0],
    [8, SQLitePointerMapEntry::ROOT_PAGE, 0],
    [9, SQLitePointerMapEntry::ROOT_PAGE, 0],
    [10, SQLitePointerMapEntry::ROOT_PAGE, 0],
]);
$limitedDatabase = $schemaDatabase($schemaRows, 10, 4, [
    [3, SQLitePointerMapEntry::BTREE_PAGE, 4],
    [4, SQLitePointerMapEntry::ROOT_PAGE, 0],
    [5, SQLitePointerMapEntry::BTREE_PAGE, 4],
    [6, SQLitePointerMapEntry::BTREE_PAGE, 4],
    [7, SQLitePointerMapEntry::ROOT_PAGE, 0],
    [8, SQLitePointerMapEntry::ROOT_PAGE, 0],
    [9, SQLitePointerMapEntry::ROOT_PAGE, 0],
    [10, SQLitePointerMapEntry::ROOT_PAGE, 0],
]);
$beyondDatabase = $schemaDatabase([
    $schemaRows[0],
    $schemaRows[1],
    ['index', 'wp_options_value_expr', 'wp_options', 12, $schemaRows[2][4]],
    $schemaRows[3],
    $schemaRows[4],
], 10, 8, [
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
    int $limit = 129,
    string $quickCheckSql = 'PRAGMA quick_check',
    bool $tableValued = false,
    ?array $cursor = null,
    ?SQLiteAttachedSchemaCatalog $catalog = null,
): array => SQLitePragmaIndexRootpageQuickcheckCurrentSourceNext::page(
    $catalog ?? $catalogFactory(),
    $sql ?? 'PRAGMA main.index_xinfo(wp_options_value_expr)',
    $db ?? $pointerMismatchDatabase,
    $offset,
    $limit,
    $quickCheckSql,
    $tableValued,
    $cursor,
);

$default = static fn (): array => $page();
$valid = static fn (): array => $page('PRAGMA main.index_xinfo(wp_options_value_expr)', $validDatabase);
$limited = static fn (): array => $page('PRAGMA main.index_xinfo(wp_options_value_expr)', $limitedDatabase, 0, 129, 'PRAGMA quick_check(1)');
$beyond = static fn (): array => $page('PRAGMA main.index_xinfo(wp_options_value_expr)', $beyondDatabase);
$temp = static fn (): array => $page('PRAGMA index_xinfo(wp_options_temp_expr)', $validDatabase);
$archive = static fn (): array => $page("pragma_index_xinfo('wp_options_archive_expr','archive')", $validDatabase, 0, 129, 'PRAGMA main.quick_check', true);
$changedCatalog = static fn (): array => $page('PRAGMA index_xinfo(wp_options_value_expr)', $validDatabase, 0, 129, 'PRAGMA quick_check', false, null, $catalogFactory(true));
$mutated = static fn (): array => $page('PRAGMA main.index_xinfo(wp_options_value_expr)', $mutatedDatabase);

$cases = [
    'default status blocked' => [$default, 'status', 'blocked'],
    'default limit next129' => [$default, 'limit', 129],
    'default total' => [$default, 'total', 6],
    'default count' => [$default, 'count', 6],
    'default complete' => [$default, 'complete', true],
    'default next null' => [$default, 'next', null],
    'source id length' => [static fn (): array => ['length' => strlen($default()['source_id'])], 'length', 64],
    'database source length' => [static fn (): array => ['length' => strlen($default()['current_source']['database'])], 'length', 64],
    'catalog source length' => [static fn (): array => ['length' => strlen($default()['current_source']['catalog'])], 'length', 64],
    'normalized index sql' => [$default, 'current_source.index_xinfo_sql', 'pragma main.index_xinfo(wp_options_value_expr)'],
    'normalized integrity sql' => [$default, 'current_source.integrity_sql', 'pragma quick_check'],
    'normalized quick sql' => [$default, 'current_source.quick_check_sql', 'pragma quick_check'],
    'source mode' => [$default, 'current_source.source_mode', 'index_rootpage_quickcheck_current_source_next129'],
    'table valued false' => [$default, 'current_source.table_valued', false],
    'current index xinfo count' => [$default, 'current.index_xinfo', 4],
    'current quick count' => [$default, 'current.quick_check', 2],
    'current quick errors' => [$default, 'current.quick_check_errors', 2],
    'current target schema' => [$default, 'current.target_schema', 'main'],
    'current target index' => [$default, 'current.target_index', 'wp_options_value_expr'],
    'current target table' => [$default, 'current.target_table', 'wp_options'],
    'row0 source index' => [$default, 'rows.0.source', 'index_xinfo'],
    'row0 expression cid' => [$default, 'rows.0.cid', -2],
    'row1 collation nocase' => [$default, 'rows.1.coll', 'NOCASE'],
    'row2 autoload desc' => [$default, 'rows.2.desc', 1],
    'row3 rowid target match' => [$default, 'rows.3.target_match', true],
    'row4 source quick' => [$default, 'rows.4.source', 'quick_check'],
    'row4 quick target match' => [$default, 'rows.4.target_match', false],
    'row4 page status pointer' => [$default, 'rows.4.page_status', 'pointer_map'],
    'row4 pointer type' => [$default, 'rows.4.pointer_map_type', 'btree-page'],
    'row4 pointer parent' => [$default, 'rows.4.pointer_map_parent', 4],
    'row4 message' => [$default, 'rows.4.message', 'pointer-map type btree-page for page 5 does not match expected root-page'],
    'row5 second quick message' => [$default, 'rows.5.message', 'pointer-map type btree-page for page 6 does not match expected root-page'],
    'valid status ok' => [$valid, 'status', 'ok'],
    'valid total index plus ok' => [$valid, 'total', 5],
    'valid quick ok count' => [$valid, 'current.quick_check', 1],
    'valid quick errors zero' => [$valid, 'current.quick_check_errors', 0],
    'valid ok row message' => [$valid, 'rows.4.message', 'ok'],
    'valid ok page status' => [$valid, 'rows.4.page_status', 'ok'],
    'limited quick status blocked' => [$limited, 'status', 'blocked'],
    'limited total index plus one quick row' => [$limited, 'total', 5],
    'limited quick errors one' => [$limited, 'current.quick_check_errors', 1],
    'limited first quick message' => [$limited, 'rows.4.message', 'largest root btree page 4 does not match sqlite_schema max rootpage 8'],
    'limited first quick target match false' => [$limited, 'rows.4.target_match', false],
    'beyond status blocked' => [$beyond, 'status', 'blocked'],
    'beyond quick status' => [$beyond, 'rows.4.page_status', 'beyond_image'],
    'beyond quick rootpage' => [$beyond, 'rows.4.rootpage', 12],
    'temp schema' => [$temp, 'current.target_schema', 'temp'],
    'temp target index' => [$temp, 'current.target_index', 'wp_options_temp_expr'],
    'temp row collation rtrim' => [$temp, 'rows.0.coll', 'RTRIM'],
    'archive table valued true' => [$archive, 'current_source.table_valued', true],
    'archive schema' => [$archive, 'current.target_schema', 'archive'],
    'archive normalized sql' => [$archive, 'current_source.index_xinfo_sql', "pragma_index_xinfo('wp_options_archive_expr','archive')"],
    'archive target' => [$archive, 'current.target_index', 'wp_options_archive_expr'],
    'archive rtrim collation' => [$archive, 'rows.1.coll', 'RTRIM'],
    'catalog shadow main wins' => [$changedCatalog, 'current.target_schema', 'main'],
    'catalog shadow source changed' => [static fn (): array => ['changed' => $valid()['source_id'] !== $changedCatalog()['source_id']], 'changed', true],
    'database mutation source changed' => [static fn (): array => ['changed' => $valid()['source_id'] !== $mutated()['source_id']], 'changed', true],
    'limit two count' => [static fn (): array => $page('PRAGMA main.index_xinfo(wp_options_value_expr)', $pointerMismatchDatabase, 0, 2), 'count', 2],
    'limit two next offset' => [static fn (): array => $page('PRAGMA main.index_xinfo(wp_options_value_expr)', $pointerMismatchDatabase, 0, 2), 'next.offset', 2],
    'offset four quick current' => [static fn (): array => $page('PRAGMA main.index_xinfo(wp_options_value_expr)', $pointerMismatchDatabase, 4, 2), 'rows.0.kind', 'quick_check'],
    'offset four next row second quick' => [static fn (): array => $page('PRAGMA main.index_xinfo(wp_options_value_expr)', $pointerMismatchDatabase, 4, 2), 'next_row.rootpage', 6],
];

$tests = [];
foreach ($cases as $name => [$callback, $path, $expected]) {
    $tests['pragma index rootpage quickcheck current source next129 ' . $name] = static function (TestRunner $t) use ($callback, $valueAt, $path, $expected): void {
        $t->same($expected, $valueAt($callback(), $path));
    };
}

$tests['pragma index rootpage quickcheck current source next129 resumes with source cursor'] = static function (TestRunner $t) use ($page, $pointerMismatchDatabase): void {
    $first = $page('PRAGMA main.index_xinfo(wp_options_value_expr)', $pointerMismatchDatabase, 0, 2);
    $second = $page('PRAGMA main.index_xinfo(wp_options_value_expr)', $pointerMismatchDatabase, 2, 2, 'PRAGMA quick_check', false, $first['next']);
    $third = $page('PRAGMA main.index_xinfo(wp_options_value_expr)', $pointerMismatchDatabase, 4, 2, 'PRAGMA quick_check', false, $second['next']);

    $t->same(2, $first['count']);
    $t->same(['source_id' => $first['source_id'], 'offset' => 2], $first['next']);
    $t->same('autoload', $second['rows'][0]['name']);
    $t->same(-1, $second['rows'][1]['cid']);
    $t->same(['source_id' => $first['source_id'], 'offset' => 4], $second['next']);
    $t->same('quick_check', $third['rows'][0]['kind']);
    $t->same(6, $third['rows'][1]['rootpage']);
    $t->same(null, $third['next']);
};

$tests['pragma index rootpage quickcheck current source next129 accepts cursor offset key'] = static function (TestRunner $t) use ($page, $pointerMismatchDatabase): void {
    $first = $page('PRAGMA main.index_xinfo(wp_options_value_expr)', $pointerMismatchDatabase, 0, 3);
    $second = $page('PRAGMA main.index_xinfo(wp_options_value_expr)', $pointerMismatchDatabase, 3, 3, 'PRAGMA quick_check', false, ['source_id' => $first['source_id'], 'offset' => 3]);

    $t->same(3, $second['offset']);
    $t->same(-1, $second['rows'][0]['cid']);
    $t->same('quick_check', $second['rows'][1]['kind']);
};

$tests['pragma index rootpage quickcheck current source next129 rejects stale database cursor'] = static function (TestRunner $t) use ($page, $pointerMismatchDatabase, $validDatabase): void {
    $first = $page('PRAGMA main.index_xinfo(wp_options_value_expr)', $pointerMismatchDatabase, 0, 2);
    $t->throws(InvalidArgumentException::class, static fn () => $page('PRAGMA main.index_xinfo(wp_options_value_expr)', $validDatabase, 2, 2, 'PRAGMA quick_check', false, $first['next']));
};

$tests['pragma index rootpage quickcheck current source next129 rejects stale catalog cursor'] = static function (TestRunner $t) use ($page, $validDatabase, $catalogFactory): void {
    $first = $page('PRAGMA index_xinfo(wp_options_value_expr)', $validDatabase, 0, 2);
    $t->throws(InvalidArgumentException::class, static fn () => $page('PRAGMA index_xinfo(wp_options_value_expr)', $validDatabase, 2, 2, 'PRAGMA quick_check', false, $first['next'], $catalogFactory(true)));
};

$tests['pragma index rootpage quickcheck current source next129 rejects stale sql cursor'] = static function (TestRunner $t) use ($page, $pointerMismatchDatabase): void {
    $first = $page('PRAGMA main.index_xinfo(wp_options_value_expr)', $pointerMismatchDatabase, 0, 2);
    $t->throws(InvalidArgumentException::class, static fn () => $page('PRAGMA index_xinfo(wp_options_value_expr)', $pointerMismatchDatabase, 2, 2, 'PRAGMA quick_check', false, $first['next']));
};

$tests['pragma index rootpage quickcheck current source next129 rejects stale quick sql cursor'] = static function (TestRunner $t) use ($page, $pointerMismatchDatabase): void {
    $first = $page('PRAGMA main.index_xinfo(wp_options_value_expr)', $pointerMismatchDatabase, 0, 2);
    $t->throws(InvalidArgumentException::class, static fn () => $page('PRAGMA main.index_xinfo(wp_options_value_expr)', $pointerMismatchDatabase, 2, 2, 'PRAGMA quick_check(1)', false, $first['next']));
};

$tests['pragma index rootpage quickcheck current source next129 rejects stale offset cursor'] = static function (TestRunner $t) use ($page, $pointerMismatchDatabase): void {
    $first = $page('PRAGMA main.index_xinfo(wp_options_value_expr)', $pointerMismatchDatabase, 0, 2);
    $t->throws(InvalidArgumentException::class, static fn () => $page('PRAGMA main.index_xinfo(wp_options_value_expr)', $pointerMismatchDatabase, 3, 2, 'PRAGMA quick_check', false, $first['next']));
};

$tests['pragma index rootpage quickcheck current source next129 rejects integrity_check sql'] = static function (TestRunner $t) use ($catalogFactory, $validDatabase): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLitePragmaIndexRootpageQuickcheckCurrentSourceNext::page($catalogFactory(), 'PRAGMA index_xinfo(wp_options_name)', $validDatabase, 0, 129, 'PRAGMA integrity_check'));
};

$tests['pragma index rootpage quickcheck current source next129 rejects negative offset'] = static function (TestRunner $t) use ($catalogFactory, $validDatabase): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLitePragmaIndexRootpageQuickcheckCurrentSourceNext::page($catalogFactory(), 'PRAGMA index_xinfo(wp_options_name)', $validDatabase, -1, 129));
};

$tests['pragma index rootpage quickcheck current source next129 rejects zero limit'] = static function (TestRunner $t) use ($catalogFactory, $validDatabase): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLitePragmaIndexRootpageQuickcheckCurrentSourceNext::page($catalogFactory(), 'PRAGMA index_xinfo(wp_options_name)', $validDatabase, 0, 0));
};

return $tests;
