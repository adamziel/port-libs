<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteAttachedSchemaCatalog;
use PortLibs\LibSqlite\SQLiteIndexLeafPage;
use PortLibs\LibSqlite\SQLitePointerMapEntry;
use PortLibs\LibSqlite\SQLitePragmaQuickcheckIndexRootpageCurrentSourceNext;
use PortLibs\LibSqlite\SQLiteRecord;
use PortLibs\LibSqlite\SQLiteSchemaRecord;
use PortLibs\LibSqlite\SQLiteTableLeafCell;
use PortLibs\LibSqlite\SQLiteTableLeafPage;

$pageSize = 1024;
$record = static fn (string $type, string $name, string $table, ?int $root, ?string $sql = null, int $rowid = 1): SQLiteSchemaRecord => new SQLiteSchemaRecord($type, $name, $table, $root, $sql, $rowid);

$catalogFactory = static function (bool $archiveShadow = false) use ($record): SQLiteAttachedSchemaCatalog {
    $catalog = new SQLiteAttachedSchemaCatalog([
        $record('table', 'wp_options', 'wp_options', 4, 'CREATE TABLE wp_options(option_id INTEGER PRIMARY KEY, option_name TEXT, option_value TEXT, autoload TEXT)', 1),
        $record('index', 'wp_options_plugin_expr', 'wp_options', 5, "CREATE INDEX wp_options_plugin_expr ON wp_options(json_extract(option_value, '$.plugin'), option_name COLLATE nocase, autoload DESC)", 2),
    ], [
        $record('table', 'wp_options', 'wp_options', 7, 'CREATE TABLE wp_options(option_name TEXT, option_value TEXT, autoload TEXT)', 1),
        $record('index', 'wp_options_temp_autoload', 'wp_options', 8, 'CREATE INDEX wp_options_temp_autoload ON wp_options(autoload COLLATE rtrim DESC)', 2),
    ]);
    $catalog->attach('archive', '/srv/wp/archive.sqlite', [
        $record('table', 'wp_options', 'wp_options', 9, 'CREATE TABLE wp_options(option_id INTEGER PRIMARY KEY, option_name TEXT, option_value TEXT, autoload TEXT)', 1),
        $record('index', $archiveShadow ? 'wp_options_plugin_expr' : 'wp_options_archive_expr', 'wp_options', 10, 'CREATE INDEX ' . ($archiveShadow ? 'wp_options_plugin_expr' : 'wp_options_archive_expr') . ' ON wp_options(option_name COLLATE rtrim)', 2),
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
        1 => SQLiteTableLeafPage::assemble(array_map(static fn (array $row, int $index): string => $schemaCell($row, $index + 1), $schemaRows, array_keys($schemaRows)), $pageSize, 100, $headerPage($pageCount, $largestRootPage)),
        2 => str_repeat("\0", $pageSize),
    ];
    foreach ($pointerMapEntries as $entry) {
        $pages[2] = $putPointerMapEntry($pages[2], $entry[0], $entry[1], $entry[2]);
    }
    for ($pageNumber = 3; $pageNumber <= $pageCount; $pageNumber++) {
        $pages[$pageNumber] ??= in_array($pageNumber, [5, 8, 10], true)
            ? SQLiteIndexLeafPage::assemble([], $pageSize)
            : SQLiteTableLeafPage::assemble([], $pageSize);
    }
    ksort($pages);

    return implode('', $pages);
};

$schemaRows = [
    ['table', 'wp_options', 'wp_options', 4, 'CREATE TABLE wp_options(option_id integer primary key, option_name text, option_value text, autoload text)'],
    ['index', 'wp_options_plugin_expr', 'wp_options', 5, "CREATE INDEX wp_options_plugin_expr ON wp_options(json_extract(option_value, '$.plugin'), option_name, autoload)"],
    ['table', 'wp_posts', 'wp_posts', 7, 'CREATE TABLE wp_posts(ID integer primary key, post_title text)'],
    ['index', 'wp_posts_title', 'wp_posts', 8, 'CREATE INDEX wp_posts_title ON wp_posts(post_title)'],
];
$validDatabase = $schemaDatabase($schemaRows, 10, 8, [
    [3, SQLitePointerMapEntry::BTREE_PAGE, 4],
    [4, SQLitePointerMapEntry::ROOT_PAGE, 0],
    [5, SQLitePointerMapEntry::ROOT_PAGE, 0],
    [7, SQLitePointerMapEntry::ROOT_PAGE, 0],
    [8, SQLitePointerMapEntry::ROOT_PAGE, 0],
    [9, SQLitePointerMapEntry::ROOT_PAGE, 0],
    [10, SQLitePointerMapEntry::ROOT_PAGE, 0],
]);
$pointerMismatchDatabase = $schemaDatabase($schemaRows, 10, 8, [
    [3, SQLitePointerMapEntry::BTREE_PAGE, 4],
    [4, SQLitePointerMapEntry::ROOT_PAGE, 0],
    [5, SQLitePointerMapEntry::BTREE_PAGE, 4],
    [7, SQLitePointerMapEntry::ROOT_PAGE, 0],
    [8, SQLitePointerMapEntry::ROOT_PAGE, 0],
    [9, SQLitePointerMapEntry::ROOT_PAGE, 0],
    [10, SQLitePointerMapEntry::ROOT_PAGE, 0],
]);
$wrongTypeDatabase = substr_replace($validDatabase, SQLiteTableLeafPage::assemble([], $pageSize), $pageSize * 4, $pageSize);
$beyondDatabase = $schemaDatabase([
    $schemaRows[0],
    ['index', 'wp_options_plugin_expr', 'wp_options', 12, $schemaRows[1][4]],
    $schemaRows[2],
    $schemaRows[3],
], 10, 8, [
    [3, SQLitePointerMapEntry::BTREE_PAGE, 4],
    [4, SQLitePointerMapEntry::ROOT_PAGE, 0],
    [5, SQLitePointerMapEntry::ROOT_PAGE, 0],
    [7, SQLitePointerMapEntry::ROOT_PAGE, 0],
    [8, SQLitePointerMapEntry::ROOT_PAGE, 0],
    [9, SQLitePointerMapEntry::ROOT_PAGE, 0],
    [10, SQLitePointerMapEntry::ROOT_PAGE, 0],
]);
$mutatedDatabase = $validDatabase;
$mutatedDatabase[48] = "\x02";

$valueAt = static function (mixed $value, string $path): mixed {
    foreach (explode('.', $path) as $part) {
        $value = is_array($value) && array_key_exists($part, $value) ? $value[$part] : $value[(int) $part];
    }

    return $value;
};

$page = static fn (
    ?string $sql = null,
    ?string $db = null,
    int $offset = 0,
    int $limit = 135,
    bool $tableValued = false,
    ?array $cursor = null,
    ?SQLiteAttachedSchemaCatalog $catalog = null,
): array => SQLitePragmaQuickcheckIndexRootpageCurrentSourceNext::page(
    $catalog ?? $catalogFactory(),
    $sql ?? 'PRAGMA main.index_xinfo(wp_options_plugin_expr)',
    $db ?? $pointerMismatchDatabase,
    $offset,
    $limit,
    $tableValued,
    $cursor,
);

$default = static fn (): array => $page();
$valid = static fn (): array => $page('PRAGMA main.index_xinfo(wp_options_plugin_expr)', $validDatabase);
$wrongType = static fn (): array => $page('PRAGMA main.index_xinfo(wp_options_plugin_expr)', $wrongTypeDatabase);
$beyond = static fn (): array => $page('PRAGMA main.index_xinfo(wp_options_plugin_expr)', $beyondDatabase);
$temp = static fn (): array => $page('PRAGMA index_xinfo(wp_options_temp_autoload)', $validDatabase);
$archive = static fn (): array => $page("pragma_index_xinfo('wp_options_archive_expr','archive')", $validDatabase, 0, 135, true);
$changedCatalog = static fn (): array => $page('PRAGMA main.index_xinfo(wp_options_plugin_expr)', $validDatabase, 0, 135, false, null, $catalogFactory(true));
$mutated = static fn (): array => $page('PRAGMA main.index_xinfo(wp_options_plugin_expr)', $mutatedDatabase);

$cases = [
    'default status blocked' => [$default, 'status', 'blocked'],
    'default limit next135' => [$default, 'limit', 135],
    'default total' => [$default, 'total', 6],
    'default complete' => [$default, 'complete', true],
    'default current source quick check' => [$default, 'current_source.integrity_sql', 'pragma quick_check'],
    'default quick pragma' => [$default, 'quickcheck.pragma', 'quick_check'],
    'default source stable' => [$default, 'quickcheck.source_stable', true],
    'default needs integrity check' => [$default, 'quickcheck.needs_integrity_check', true],
    'default index xinfo count' => [$default, 'quickcheck.index_xinfo', 4],
    'default rootpage count' => [$default, 'quickcheck.rootpage', 2],
    'default rootpage errors' => [$default, 'quickcheck.rootpage_errors', 1],
    'default target schema' => [$default, 'quickcheck.target_schema', 'main'],
    'default target index' => [$default, 'quickcheck.target_index', 'wp_options_plugin_expr'],
    'default target table' => [$default, 'quickcheck.target_table', 'wp_options'],
    'row0 index source' => [$default, 'rows.0.source', 'index_xinfo'],
    'row0 quick source' => [$default, 'rows.0.quickcheck_source', 'pragma quick_check'],
    'row0 not rootpage' => [$default, 'rows.0.quickcheck_index_rootpage', false],
    'row0 does not require integrity check' => [$default, 'rows.0.quickcheck_requires_integrity_check', false],
    'row0 expression cid' => [$default, 'rows.0.cid', -2],
    'row1 collation nocase' => [$default, 'rows.1.coll', 'NOCASE'],
    'row2 autoload desc' => [$default, 'rows.2.desc', 1],
    'row3 rowid cid' => [$default, 'rows.3.cid', -1],
    'row4 rootpage source' => [$default, 'rows.4.source', 'rootpage_integrity'],
    'row4 rootpage quick flag' => [$default, 'rows.4.quickcheck_index_rootpage', true],
    'row4 rootpage status ok' => [$default, 'rows.4.page_status', 'ok'],
    'row4 no integrity escalation' => [$default, 'rows.4.quickcheck_requires_integrity_check', false],
    'row5 index rootpage' => [$default, 'rows.5.name', 'wp_options_plugin_expr'],
    'row5 pointer map status' => [$default, 'rows.5.page_status', 'pointer_map'],
    'row5 pointer type' => [$default, 'rows.5.pointer_map_type', 'btree-page'],
    'row5 pointer parent' => [$default, 'rows.5.pointer_map_parent', 4],
    'row5 integrity escalation' => [$default, 'rows.5.quickcheck_requires_integrity_check', true],
    'valid status ok' => [$valid, 'status', 'ok'],
    'valid no integrity check needed' => [$valid, 'quickcheck.needs_integrity_check', false],
    'valid root errors zero' => [$valid, 'quickcheck.rootpage_errors', 0],
    'valid root index ok' => [$valid, 'rows.5.page_status', 'ok'],
    'wrong type blocked' => [$wrongType, 'status', 'blocked'],
    'wrong type status' => [$wrongType, 'rows.5.page_status', 'wrong_btree_type'],
    'wrong type page type' => [$wrongType, 'rows.5.page_type', 'table-leaf'],
    'beyond blocked' => [$beyond, 'status', 'blocked'],
    'beyond table root still ok' => [$beyond, 'rows.4.page_status', 'ok'],
    'beyond header row status' => [$beyond, 'rows.5.page_status', 'header_mismatch'],
    'beyond root row status' => [$beyond, 'rows.6.page_status', 'beyond_image'],
    'temp schema selected' => [$temp, 'quickcheck.target_schema', 'temp'],
    'temp target index selected' => [$temp, 'quickcheck.target_index', 'wp_options_temp_autoload'],
    'temp rtrim collation' => [$temp, 'rows.0.coll', 'RTRIM'],
    'archive table valued true' => [$archive, 'current_source.table_valued', true],
    'archive schema selected' => [$archive, 'quickcheck.target_schema', 'archive'],
    'archive target selected' => [$archive, 'quickcheck.target_index', 'wp_options_archive_expr'],
    'archive normalized sql' => [$archive, 'current_source.index_xinfo_sql', "pragma_index_xinfo('wp_options_archive_expr','archive')"],
    'changed catalog source changed' => [static fn (): array => ['changed' => $valid()['source_id'] !== $changedCatalog()['source_id']], 'changed', true],
    'mutated database source changed' => [static fn (): array => ['changed' => $valid()['source_id'] !== $mutated()['source_id']], 'changed', true],
    'limit two count' => [static fn (): array => $page('PRAGMA main.index_xinfo(wp_options_plugin_expr)', $pointerMismatchDatabase, 0, 2), 'count', 2],
    'limit two next offset' => [static fn (): array => $page('PRAGMA main.index_xinfo(wp_options_plugin_expr)', $pointerMismatchDatabase, 0, 2), 'next.offset', 2],
    'offset four root row' => [static fn (): array => $page('PRAGMA main.index_xinfo(wp_options_plugin_expr)', $pointerMismatchDatabase, 4, 2), 'rows.0.kind', 'rootpage'],
    'offset four next row' => [static fn (): array => $page('PRAGMA main.index_xinfo(wp_options_plugin_expr)', $pointerMismatchDatabase, 4, 2), 'next_row.name', 'wp_options_plugin_expr'],
];

$tests = [];
foreach ($cases as $name => [$callback, $path, $expected]) {
    $tests['pragma quickcheck index rootpage current source next135 ' . $name] = static function (TestRunner $t) use ($callback, $valueAt, $path, $expected): void {
        $t->same($expected, $valueAt($callback(), $path));
    };
}

$tests['pragma quickcheck index rootpage current source next135 resumes with current source cursor'] = static function (TestRunner $t) use ($page, $pointerMismatchDatabase): void {
    $first = $page('PRAGMA main.index_xinfo(wp_options_plugin_expr)', $pointerMismatchDatabase, 0, 2);
    $second = $page('PRAGMA main.index_xinfo(wp_options_plugin_expr)', $pointerMismatchDatabase, 2, 2, false, $first['next']);
    $third = $page('PRAGMA main.index_xinfo(wp_options_plugin_expr)', $pointerMismatchDatabase, 4, 2, false, $second['next']);

    $t->same(['source_id' => $first['source_id'], 'offset' => 2], $first['next']);
    $t->same('autoload', $second['rows'][0]['name']);
    $t->same(-1, $second['rows'][1]['cid']);
    $t->same(['source_id' => $first['source_id'], 'offset' => 4], $second['next']);
    $t->same('rootpage', $third['rows'][0]['kind']);
    $t->same('wp_options_plugin_expr', $third['rows'][1]['name']);
    $t->same(null, $third['next']);
};

$tests['pragma quickcheck index rootpage current source next135 rejects stale database cursor'] = static function (TestRunner $t) use ($page, $pointerMismatchDatabase, $validDatabase): void {
    $first = $page('PRAGMA main.index_xinfo(wp_options_plugin_expr)', $pointerMismatchDatabase, 0, 2);
    $t->throws(InvalidArgumentException::class, static fn () => $page('PRAGMA main.index_xinfo(wp_options_plugin_expr)', $validDatabase, 2, 2, false, $first['next']));
};

$tests['pragma quickcheck index rootpage current source next135 rejects stale sql cursor'] = static function (TestRunner $t) use ($page, $pointerMismatchDatabase): void {
    $first = $page('PRAGMA main.index_xinfo(wp_options_plugin_expr)', $pointerMismatchDatabase, 0, 2);
    $t->throws(InvalidArgumentException::class, static fn () => $page('PRAGMA index_xinfo(wp_options_plugin_expr)', $pointerMismatchDatabase, 2, 2, false, $first['next']));
};

$tests['pragma quickcheck index rootpage current source next135 rejects stale offset cursor'] = static function (TestRunner $t) use ($page, $pointerMismatchDatabase): void {
    $first = $page('PRAGMA main.index_xinfo(wp_options_plugin_expr)', $pointerMismatchDatabase, 0, 2);
    $t->throws(InvalidArgumentException::class, static fn () => $page('PRAGMA main.index_xinfo(wp_options_plugin_expr)', $pointerMismatchDatabase, 3, 2, false, $first['next']));
};

$tests['pragma quickcheck index rootpage current source next135 rejects negative offset'] = static function (TestRunner $t) use ($catalogFactory, $validDatabase): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLitePragmaQuickcheckIndexRootpageCurrentSourceNext::page($catalogFactory(), 'PRAGMA index_xinfo(wp_options_plugin_expr)', $validDatabase, -1, 135));
};

$tests['pragma quickcheck index rootpage current source next135 rejects zero limit'] = static function (TestRunner $t) use ($catalogFactory, $validDatabase): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLitePragmaQuickcheckIndexRootpageCurrentSourceNext::page($catalogFactory(), 'PRAGMA index_xinfo(wp_options_plugin_expr)', $validDatabase, 0, 0));
};

return $tests;
