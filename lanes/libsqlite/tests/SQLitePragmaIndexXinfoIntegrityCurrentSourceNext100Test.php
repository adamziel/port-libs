<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteAttachedSchemaCatalog;
use PortLibs\LibSqlite\SQLitePointerMapEntry;
use PortLibs\LibSqlite\SQLitePragmaIndexXinfoIntegrityCurrentSourceYield;
use PortLibs\LibSqlite\SQLiteRecord;
use PortLibs\LibSqlite\SQLiteSchemaRecord;
use PortLibs\LibSqlite\SQLiteTableLeafCell;
use PortLibs\LibSqlite\SQLiteTableLeafPage;

$currentSource = '796e75f2553d88aeff452968c875521a537dba2d';
$nextSource = 'pragma-index-xinfo-integrity-current-source-next100';

$record = static fn (string $type, string $name, string $table, ?int $root, ?string $sql = null, int $rowId = 1): SQLiteSchemaRecord => new SQLiteSchemaRecord(
    $type,
    $name,
    $table,
    $root,
    $sql,
    $rowId,
);

$makeCatalog = static function () use ($record): SQLiteAttachedSchemaCatalog {
    $catalog = new SQLiteAttachedSchemaCatalog([
        $record('table', 'wp_options', 'wp_options', 4, 'CREATE TABLE wp_options(option_id INTEGER PRIMARY KEY, option_name TEXT, option_value TEXT, autoload TEXT, updated_at TEXT)', 1),
        $record('index', 'wp_options_name', 'wp_options', 5, 'CREATE UNIQUE INDEX wp_options_name ON wp_options(option_name COLLATE nocase DESC, autoload)', 2),
        $record('index', 'wp_options_expr', 'wp_options', 6, "CREATE INDEX wp_options_expr ON wp_options(lower(option_name), json_extract(option_value, '$.enabled') COLLATE nocase, updated_at DESC)", 3),
    ], [
        $record('table', 'wp_options', 'wp_options', 7, 'CREATE TABLE wp_options(option_name TEXT, option_value TEXT, autoload TEXT)', 1),
        $record('index', 'wp_options_temp_expr', 'wp_options', 8, 'CREATE INDEX wp_options_temp_expr ON wp_options(upper(option_name) COLLATE rtrim, autoload DESC)', 2),
    ]);
    $catalog->attach('archive', '/srv/wp/archive.sqlite', [
        $record('table', 'wp_sitemeta', 'wp_sitemeta', 9, 'CREATE TABLE wp_sitemeta(meta_id INTEGER PRIMARY KEY, meta_key TEXT, meta_value TEXT, autoload TEXT)', 1),
        $record('index', 'wp_sitemeta_expr', 'wp_sitemeta', 10, "CREATE INDEX wp_sitemeta_expr ON wp_sitemeta(json_extract(meta_value, '$.plugin'), lower(meta_key) COLLATE nocase, autoload DESC)", 2),
    ]);

    return $catalog;
};

$pageSize = 1024;
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
$schemaCell = static fn (array $values, int $rowId): string => SQLiteTableLeafCell::encode($rowId, SQLiteRecord::encode($values));
$schemaDatabase = static function (array $schemaRows, int $pageCount, int $largestRootPage, array $pointerMapEntries) use ($headerPage, $schemaCell, $pageSize): string {
    $pages = [
        1 => SQLiteTableLeafPage::assemble(
            array_map(static fn (array $row, int $index): string => $schemaCell($row, $index + 1), $schemaRows, array_keys($schemaRows)),
            $pageSize,
            100,
            $headerPage($pageCount, $largestRootPage),
        ),
        2 => str_repeat("\0", $pageSize),
    ];
    foreach ($pointerMapEntries as [$pageNumber, $type, $parent]) {
        $pages[2] = substr_replace($pages[2], chr($type) . pack('N', $parent), 5 * ($pageNumber - 3), 5);
    }
    for ($pageNumber = 3; $pageNumber <= $pageCount; $pageNumber++) {
        $pages[$pageNumber] ??= SQLiteTableLeafPage::assemble([], $pageSize);
    }
    ksort($pages);

    return implode('', $pages);
};

$schemaRows = [
    ['table', 'wp_options', 'wp_options', 4, 'CREATE TABLE wp_options(option_id integer primary key, option_name text, option_value text, autoload text)'],
    ['index', 'wp_options_name', 'wp_options', 5, 'CREATE UNIQUE INDEX wp_options_name ON wp_options(option_name)'],
];
$validDatabase = $schemaDatabase($schemaRows, 6, 5, [
    [3, SQLitePointerMapEntry::BTREE_PAGE, 4],
    [4, SQLitePointerMapEntry::ROOT_PAGE, 0],
    [5, SQLitePointerMapEntry::ROOT_PAGE, 0],
    [6, SQLitePointerMapEntry::BTREE_PAGE, 5],
]);
$rootMismatchDatabase = $schemaDatabase($schemaRows, 6, 4, [
    [3, SQLitePointerMapEntry::BTREE_PAGE, 4],
    [4, SQLitePointerMapEntry::ROOT_PAGE, 0],
    [5, SQLitePointerMapEntry::BTREE_PAGE, 4],
    [6, SQLitePointerMapEntry::BTREE_PAGE, 5],
]);
$beyondRootDatabase = $schemaDatabase([
    $schemaRows[0],
    ['index', 'wp_options_name', 'wp_options', 12, $schemaRows[1][4]],
], 6, 5, [
    [3, SQLitePointerMapEntry::BTREE_PAGE, 4],
    [4, SQLitePointerMapEntry::ROOT_PAGE, 0],
    [5, SQLitePointerMapEntry::ROOT_PAGE, 0],
    [6, SQLitePointerMapEntry::BTREE_PAGE, 5],
]);

$valueAt = static function (mixed $value, string $path): mixed {
    foreach (explode('.', $path) as $part) {
        if ($part === 'count' && is_array($value) && !array_key_exists('count', $value)) {
            $value = count($value);
            continue;
        }
        $value = is_array($value) && array_key_exists($part, $value) ? $value[$part] : $value[(int) $part];
    }

    return $value;
};

$snapshot = static function (string $sql, string $database, int $offset = 0, int $limit = 100, string $integritySql = 'PRAGMA integrity_check', bool $tableValued = false) use ($makeCatalog, $currentSource, $nextSource): array {
    return SQLitePragmaIndexXinfoIntegrityCurrentSourceYield::page($makeCatalog(), $sql, $database, $currentSource, $nextSource, $offset, $limit, $integritySql, $tableValued);
};

$cases = [
    'valid status ok' => ['PRAGMA main.index_xinfo(wp_options_name)', $validDatabase, 0, 100, 'status', 'ok'],
    'valid default limit' => ['PRAGMA main.index_xinfo(wp_options_name)', $validDatabase, 0, 100, 'limit', 100],
    'valid total metadata only' => ['PRAGMA main.index_xinfo(wp_options_name)', $validDatabase, 0, 100, 'total', 3],
    'valid current source' => ['PRAGMA main.index_xinfo(wp_options_name)', $validDatabase, 0, 100, 'current.source', $currentSource],
    'valid next source' => ['PRAGMA main.index_xinfo(wp_options_name)', $validDatabase, 0, 100, 'next.source', $nextSource],
    'valid next ready' => ['PRAGMA main.index_xinfo(wp_options_name)', $validDatabase, 0, 100, 'next.ready', true],
    'valid next blocking empty' => ['PRAGMA main.index_xinfo(wp_options_name)', $validDatabase, 0, 100, 'next.blocking.count', 0],
    'valid current metadata rows' => ['PRAGMA main.index_xinfo(wp_options_name)', $validDatabase, 0, 100, 'current.metadata_rows', 3],
    'valid current integrity errors' => ['PRAGMA main.index_xinfo(wp_options_name)', $validDatabase, 0, 100, 'current.integrity_errors', 0],
    'valid current index' => ['PRAGMA main.index_xinfo(wp_options_name)', $validDatabase, 0, 100, 'current.index', 'wp_options_name'],
    'row0 current source' => ['PRAGMA main.index_xinfo(wp_options_name)', $validDatabase, 0, 100, 'rows.0.current_source', $currentSource],
    'row0 next source' => ['PRAGMA main.index_xinfo(wp_options_name)', $validDatabase, 0, 100, 'rows.0.next_source', $nextSource],
    'row0 status metadata' => ['PRAGMA main.index_xinfo(wp_options_name)', $validDatabase, 0, 100, 'rows.0.status', 'metadata'],
    'row0 index target' => ['PRAGMA main.index_xinfo(wp_options_name)', $validDatabase, 0, 100, 'rows.0.index', 'wp_options_name'],
    'row0 name' => ['PRAGMA main.index_xinfo(wp_options_name)', $validDatabase, 0, 100, 'rows.0.name', 'option_name'],
    'row0 desc' => ['PRAGMA main.index_xinfo(wp_options_name)', $validDatabase, 0, 100, 'rows.0.desc', 1],
    'row0 coll nocase' => ['PRAGMA main.index_xinfo(wp_options_name)', $validDatabase, 0, 100, 'rows.0.coll', 'NOCASE'],
    'row1 autoload' => ['PRAGMA main.index_xinfo(wp_options_name)', $validDatabase, 0, 100, 'rows.1.name', 'autoload'],
    'row1 key' => ['PRAGMA main.index_xinfo(wp_options_name)', $validDatabase, 0, 100, 'rows.1.key', 1],
    'row2 auxiliary cid' => ['PRAGMA main.index_xinfo(wp_options_name)', $validDatabase, 0, 100, 'rows.2.cid', -1],
    'row2 auxiliary status' => ['PRAGMA main.index_xinfo(wp_options_name)', $validDatabase, 0, 100, 'rows.2.status', 'metadata'],
    'mismatch status blocked' => ['PRAGMA main.index_xinfo(wp_options_expr)', $rootMismatchDatabase, 0, 100, 'status', 'blocked'],
    'mismatch total' => ['PRAGMA main.index_xinfo(wp_options_expr)', $rootMismatchDatabase, 0, 100, 'total', 6],
    'mismatch metadata rows' => ['PRAGMA main.index_xinfo(wp_options_expr)', $rootMismatchDatabase, 0, 100, 'current.metadata_rows', 4],
    'mismatch integrity errors' => ['PRAGMA main.index_xinfo(wp_options_expr)', $rootMismatchDatabase, 0, 100, 'current.integrity_errors', 2],
    'mismatch next ready false' => ['PRAGMA main.index_xinfo(wp_options_expr)', $rootMismatchDatabase, 0, 100, 'next.ready', false],
    'mismatch blocker count' => ['PRAGMA main.index_xinfo(wp_options_expr)', $rootMismatchDatabase, 0, 100, 'next.blocking.count', 1],
    'mismatch blocker name' => ['PRAGMA main.index_xinfo(wp_options_expr)', $rootMismatchDatabase, 0, 100, 'next.blocking.0', 'index_root_integrity'],
    'mismatch expression cid' => ['PRAGMA main.index_xinfo(wp_options_expr)', $rootMismatchDatabase, 0, 100, 'rows.0.cid', -2],
    'mismatch json coll' => ['PRAGMA main.index_xinfo(wp_options_expr)', $rootMismatchDatabase, 0, 100, 'rows.1.coll', 'NOCASE'],
    'mismatch updated desc' => ['PRAGMA main.index_xinfo(wp_options_expr)', $rootMismatchDatabase, 0, 100, 'rows.2.desc', 1],
    'mismatch integrity row status' => ['PRAGMA main.index_xinfo(wp_options_expr)', $rootMismatchDatabase, 0, 100, 'rows.4.status', 'integrity_error'],
    'mismatch integrity row index retained' => ['PRAGMA main.index_xinfo(wp_options_expr)', $rootMismatchDatabase, 0, 100, 'rows.4.index', 'wp_options_expr'],
    'mismatch integrity row source current' => ['PRAGMA main.index_xinfo(wp_options_expr)', $rootMismatchDatabase, 0, 100, 'rows.4.current_source', $currentSource],
    'mismatch integrity row source next' => ['PRAGMA main.index_xinfo(wp_options_expr)', $rootMismatchDatabase, 0, 100, 'rows.4.next_source', $nextSource],
    'mismatch integrity row message' => ['PRAGMA main.index_xinfo(wp_options_expr)', $rootMismatchDatabase, 0, 100, 'rows.4.message', 'largest root btree page 4 does not match sqlite_schema max rootpage 5'],
    'mismatch pointer root message status' => ['PRAGMA main.index_xinfo(wp_options_expr)', $rootMismatchDatabase, 0, 100, 'rows.5.status', 'integrity_error'],
    'offset three current rowid' => ['PRAGMA main.index_xinfo(wp_options_expr)', $rootMismatchDatabase, 3, 2, 'rows.0.name', null],
    'offset three next integrity' => ['PRAGMA main.index_xinfo(wp_options_expr)', $rootMismatchDatabase, 3, 2, 'rows.1.status', 'integrity_error'],
    'offset three next offset' => ['PRAGMA main.index_xinfo(wp_options_expr)', $rootMismatchDatabase, 3, 2, 'next_offset', 5],
    'offset five current integrity' => ['PRAGMA main.index_xinfo(wp_options_expr)', $rootMismatchDatabase, 5, 2, 'rows.0.status', 'integrity_error'],
    'offset five complete' => ['PRAGMA main.index_xinfo(wp_options_expr)', $rootMismatchDatabase, 5, 2, 'complete', true],
    'offset tail empty count' => ['PRAGMA main.index_xinfo(wp_options_expr)', $rootMismatchDatabase, 9, 2, 'count', 0],
    'offset tail complete' => ['PRAGMA main.index_xinfo(wp_options_expr)', $rootMismatchDatabase, 9, 2, 'complete', true],
    'temp unqualified schema' => ['PRAGMA index_xinfo(wp_options_temp_expr)', $validDatabase, 0, 100, 'rows.0.schema', 'temp'],
    'temp unqualified current index' => ['PRAGMA index_xinfo(wp_options_temp_expr)', $validDatabase, 0, 100, 'current.index', 'wp_options_temp_expr'],
    'temp unqualified coll rtrim' => ['PRAGMA index_xinfo(wp_options_temp_expr)', $validDatabase, 0, 100, 'rows.0.coll', 'RTRIM'],
    'temp unqualified desc' => ['PRAGMA index_xinfo(wp_options_temp_expr)', $validDatabase, 0, 100, 'rows.1.desc', 1],
    'archive table valued schema' => ["pragma_index_xinfo('wp_sitemeta_expr','archive')", $validDatabase, 0, 100, 'rows.0.schema', 'archive', true],
    'archive table valued index' => ["pragma_index_xinfo('wp_sitemeta_expr','archive')", $validDatabase, 0, 100, 'current.index', 'wp_sitemeta_expr', true],
    'archive table valued second coll' => ["pragma_index_xinfo('wp_sitemeta_expr','archive')", $validDatabase, 0, 100, 'rows.1.coll', 'NOCASE', true],
    'archive table valued total' => ["pragma_index_xinfo('wp_sitemeta_expr','archive')", $validDatabase, 0, 100, 'total', 4, true],
    'archive rowid auxiliary status' => ["pragma_index_xinfo('wp_sitemeta_expr','archive')", $validDatabase, 3, 1, 'rows.0.status', 'metadata', true],
    'root beyond status blocked' => ['PRAGMA main.index_xinfo(wp_options_name)', $beyondRootDatabase, 0, 100, 'status', 'blocked'],
    'root beyond integrity errors' => ['PRAGMA main.index_xinfo(wp_options_name)', $beyondRootDatabase, 0, 100, 'current.integrity_errors', 3],
    'root beyond appended status' => ['PRAGMA main.index_xinfo(wp_options_name)', $beyondRootDatabase, 3, 4, 'rows.0.status', 'integrity_error'],
    'root beyond appended index' => ['PRAGMA main.index_xinfo(wp_options_name)', $beyondRootDatabase, 3, 4, 'current.index', 'wp_options_name'],
    'root beyond appended message' => ['PRAGMA main.index_xinfo(wp_options_name)', $beyondRootDatabase, 3, 4, 'rows.0.message', 'sqlite_schema index wp_options_name rootpage 12 is beyond the database image'],
    'quick root beyond kind' => ['PRAGMA main.index_xinfo(wp_options_name)', $beyondRootDatabase, 3, 4, 'rows.0.kind', 'quick_check', false, 'PRAGMA quick_check(2)'],
    'quick root beyond status' => ['PRAGMA main.index_xinfo(wp_options_name)', $beyondRootDatabase, 3, 4, 'rows.0.status', 'integrity_error', false, 'PRAGMA quick_check(2)'],
    'missing index mismatch status' => ['PRAGMA main.index_xinfo(wp_missing)', $rootMismatchDatabase, 0, 100, 'status', 'blocked'],
    'missing index mismatch current index null' => ['PRAGMA main.index_xinfo(wp_missing)', $rootMismatchDatabase, 0, 100, 'current.index', null],
    'missing index mismatch row index null' => ['PRAGMA main.index_xinfo(wp_missing)', $rootMismatchDatabase, 0, 100, 'rows.0.index', null],
    'missing index valid total zero' => ['PRAGMA main.index_xinfo(wp_missing)', $validDatabase, 0, 100, 'total', 0],
    'missing index valid status ok' => ['PRAGMA main.index_xinfo(wp_missing)', $validDatabase, 0, 100, 'status', 'ok'],
];

$tests = [];
foreach ($cases as $name => $case) {
    [$sql, $database, $offset, $limit, $path, $expected] = $case;
    $tableValued = $case[6] ?? false;
    $integritySql = $case[7] ?? 'PRAGMA integrity_check';
    $tests['pragma index_xinfo integrity current source next100 ' . $name] = static function (TestRunner $t) use ($snapshot, $valueAt, $sql, $database, $offset, $limit, $path, $expected, $tableValued, $integritySql): void {
        $t->same($expected, $valueAt($snapshot($sql, $database, $offset, $limit, $integritySql, $tableValued), $path));
    };
}

$tests['pragma index_xinfo integrity current source next100 collect tags every row'] = static function (TestRunner $t) use ($makeCatalog, $rootMismatchDatabase, $currentSource, $nextSource): void {
    $rows = SQLitePragmaIndexXinfoIntegrityCurrentSourceYield::collect($makeCatalog(), 'PRAGMA main.index_xinfo(wp_options_expr)', $rootMismatchDatabase, $currentSource, $nextSource);
    $t->same(6, count($rows));
    foreach ($rows as $row) {
        $t->same($currentSource, $row['current_source']);
        $t->same($nextSource, $row['next_source']);
    }
};

$tests['pragma index_xinfo integrity current source next100 rejects negative offset'] = static function (TestRunner $t) use ($makeCatalog, $validDatabase, $currentSource, $nextSource): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLitePragmaIndexXinfoIntegrityCurrentSourceYield::page($makeCatalog(), 'PRAGMA index_xinfo(wp_options_name)', $validDatabase, $currentSource, $nextSource, -1, 100));
};

$tests['pragma index_xinfo integrity current source next100 rejects zero limit'] = static function (TestRunner $t) use ($makeCatalog, $validDatabase, $currentSource, $nextSource): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLitePragmaIndexXinfoIntegrityCurrentSourceYield::page($makeCatalog(), 'PRAGMA index_xinfo(wp_options_name)', $validDatabase, $currentSource, $nextSource, 0, 0));
};

$tests['pragma index_xinfo integrity current source next100 rejects missing current source'] = static function (TestRunner $t) use ($makeCatalog, $validDatabase, $nextSource): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLitePragmaIndexXinfoIntegrityCurrentSourceYield::page($makeCatalog(), 'PRAGMA index_xinfo(wp_options_name)', $validDatabase, '', $nextSource));
};

$tests['pragma index_xinfo integrity current source next100 rejects non index pragma'] = static function (TestRunner $t) use ($makeCatalog, $validDatabase, $currentSource, $nextSource): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLitePragmaIndexXinfoIntegrityCurrentSourceYield::page($makeCatalog(), 'PRAGMA table_info(wp_options)', $validDatabase, $currentSource, $nextSource));
};

return $tests;
