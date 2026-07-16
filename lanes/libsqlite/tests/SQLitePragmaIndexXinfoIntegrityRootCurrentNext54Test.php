<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteAttachedSchemaCatalog;
use PortLibs\LibSqlite\SQLiteBTreePageHeader;
use PortLibs\LibSqlite\SQLitePointerMapEntry;
use PortLibs\LibSqlite\SQLitePragmaIndexXinfoIntegrityRootYield;
use PortLibs\LibSqlite\SQLiteRecord;
use PortLibs\LibSqlite\SQLiteSchemaRecord;
use PortLibs\LibSqlite\SQLiteTableLeafCell;
use PortLibs\LibSqlite\SQLiteTableLeafPage;

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
$putPointerMapEntry = static function (string $page, int $pageNumber, int $type, int $parent): string {
    $offset = 5 * ($pageNumber - 3);

    return substr_replace($page, chr($type) . pack('N', $parent), $offset, 5);
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
        if (is_array($value) && array_key_exists($part, $value)) {
            $value = $value[$part];
            continue;
        }
        if ($part === 'count') {
            $value = count($value);
            continue;
        }
        $value = is_numeric($part) ? $value[(int) $part] : $value[$part];
    }

    return $value;
};

$snapshot = static function (string $sql, string $database, int $offset = 0, int $limit = 54, string $integritySql = 'PRAGMA integrity_check', bool $tableValued = false) use ($makeCatalog): array {
    return SQLitePragmaIndexXinfoIntegrityRootYield::page($makeCatalog(), $sql, $database, $offset, $limit, $integritySql, $tableValued);
};

$tests = [
    'pragma index_xinfo integrity root current next54 current and next span xinfo rows' => static function (TestRunner $t) use ($snapshot, $validDatabase): void {
        $page = $snapshot('PRAGMA main.index_xinfo(wp_options_name)', $validDatabase, 0, 3);
        $t->same('index_xinfo', $page['current']['kind']);
        $t->same('option_name', $page['current']['name']);
        $t->same('autoload', $page['next']['name']);
        $t->same(3, $page['count']);
        $t->same(null, $page['next_offset']);
    },
    'pragma index_xinfo integrity root current next54 appends root diagnostics after index rows' => static function (TestRunner $t) use ($snapshot, $rootMismatchDatabase): void {
        $page = $snapshot('PRAGMA main.index_xinfo(wp_options_name)', $rootMismatchDatabase, 3, 4);
        $t->same('integrity_check', $page['current']['kind']);
        $t->same('largest root btree page 4 does not match sqlite_schema max rootpage 5', $page['current']['message']);
    },
    'pragma index_xinfo integrity root current next54 quick check keeps root diagnostics' => static function (TestRunner $t) use ($snapshot, $beyondRootDatabase): void {
        $page = $snapshot('PRAGMA main.index_xinfo(wp_options_expr)', $beyondRootDatabase, 4, 3, 'PRAGMA quick_check(2)');
        $t->same('quick_check', $page['current']['kind']);
        $t->same('sqlite_schema index wp_options_name rootpage 12 is beyond the database image', $page['current']['message']);
    },
    'pragma index_xinfo integrity root current next54 table valued attached archive' => static function (TestRunner $t) use ($snapshot, $validDatabase): void {
        $page = $snapshot("pragma_index_xinfo('wp_sitemeta_expr','archive')", $validDatabase, 0, 5, 'PRAGMA integrity_check', true);
        $t->same('archive', $page['current']['schema']);
        $t->same('wp_sitemeta_expr', $page['current']['target']);
        $t->same('NOCASE', $page['next']['coll']);
    },
];

$cases = [
    'main index total no integrity errors' => ['PRAGMA main.index_xinfo(wp_options_name)', $validDatabase, 0, 54, 'total', 3],
    'main index complete no errors' => ['PRAGMA main.index_xinfo(wp_options_name)', $validDatabase, 0, 54, 'complete', true],
    'main index next offset null no errors' => ['PRAGMA main.index_xinfo(wp_options_name)', $validDatabase, 0, 54, 'next_offset', null],
    'main index first schema main' => ['PRAGMA main.index_xinfo(wp_options_name)', $validDatabase, 0, 54, 'rows.0.schema', 'main'],
    'main index first target' => ['PRAGMA main.index_xinfo(wp_options_name)', $validDatabase, 0, 54, 'rows.0.target', 'wp_options_name'],
    'main index first desc' => ['PRAGMA main.index_xinfo(wp_options_name)', $validDatabase, 0, 54, 'rows.0.desc', 1],
    'main index first coll nocase' => ['PRAGMA main.index_xinfo(wp_options_name)', $validDatabase, 0, 54, 'rows.0.coll', 'NOCASE'],
    'main index second autoload' => ['PRAGMA main.index_xinfo(wp_options_name)', $validDatabase, 0, 54, 'rows.1.name', 'autoload'],
    'main index second key' => ['PRAGMA main.index_xinfo(wp_options_name)', $validDatabase, 0, 54, 'rows.1.key', 1],
    'main index rowid auxiliary' => ['PRAGMA main.index_xinfo(wp_options_name)', $validDatabase, 0, 54, 'rows.2.cid', -1],
    'main index rowid message' => ['PRAGMA main.index_xinfo(wp_options_name)', $validDatabase, 0, 54, 'rows.2.message', 'index_xinfo main.wp_options_name seqno 2 cid -1 expression/rowid coll BINARY key 0'],
    'main expression total with mismatch' => ['PRAGMA main.index_xinfo(wp_options_expr)', $rootMismatchDatabase, 0, 54, 'total', 6],
    'main expression first expression cid' => ['PRAGMA main.index_xinfo(wp_options_expr)', $rootMismatchDatabase, 0, 54, 'rows.0.cid', -2],
    'main expression first message' => ['PRAGMA main.index_xinfo(wp_options_expr)', $rootMismatchDatabase, 0, 54, 'rows.0.message', 'index_xinfo main.wp_options_expr seqno 0 cid -2 expression/rowid coll BINARY key 1'],
    'main expression json coll' => ['PRAGMA main.index_xinfo(wp_options_expr)', $rootMismatchDatabase, 0, 54, 'rows.1.coll', 'NOCASE'],
    'main expression updated desc' => ['PRAGMA main.index_xinfo(wp_options_expr)', $rootMismatchDatabase, 0, 54, 'rows.2.desc', 1],
    'main expression rowid auxiliary kind' => ['PRAGMA main.index_xinfo(wp_options_expr)', $rootMismatchDatabase, 0, 54, 'rows.3.kind', 'index_xinfo'],
    'main expression mismatch appended kind' => ['PRAGMA main.index_xinfo(wp_options_expr)', $rootMismatchDatabase, 0, 54, 'rows.4.kind', 'integrity_check'],
    'main expression mismatch appended message' => ['PRAGMA main.index_xinfo(wp_options_expr)', $rootMismatchDatabase, 0, 54, 'rows.4.message', 'largest root btree page 4 does not match sqlite_schema max rootpage 5'],
    'offset two starts updated_at' => ['PRAGMA main.index_xinfo(wp_options_expr)', $rootMismatchDatabase, 2, 2, 'current.name', 'updated_at'],
    'offset two next rowid' => ['PRAGMA main.index_xinfo(wp_options_expr)', $rootMismatchDatabase, 2, 2, 'next.cid', -1],
    'offset two next offset' => ['PRAGMA main.index_xinfo(wp_options_expr)', $rootMismatchDatabase, 2, 2, 'next_offset', 4],
    'offset four lands root message' => ['PRAGMA main.index_xinfo(wp_options_expr)', $rootMismatchDatabase, 4, 2, 'current.kind', 'integrity_check'],
    'offset four complete' => ['PRAGMA main.index_xinfo(wp_options_expr)', $rootMismatchDatabase, 4, 2, 'complete', true],
    'offset past tail current null' => ['PRAGMA main.index_xinfo(wp_options_expr)', $rootMismatchDatabase, 8, 2, 'current', null],
    'offset past tail count zero' => ['PRAGMA main.index_xinfo(wp_options_expr)', $rootMismatchDatabase, 8, 2, 'count', 0],
    'temp unqualified schema' => ['PRAGMA index_xinfo(wp_options_temp_expr)', $validDatabase, 0, 54, 'current.schema', 'temp'],
    'temp unqualified coll rtrim' => ['PRAGMA index_xinfo(wp_options_temp_expr)', $validDatabase, 0, 54, 'current.coll', 'RTRIM'],
    'temp unqualified autoload desc' => ['PRAGMA index_xinfo(wp_options_temp_expr)', $validDatabase, 0, 54, 'next.desc', 1],
    'temp unqualified total' => ['PRAGMA index_xinfo(wp_options_temp_expr)', $validDatabase, 0, 54, 'total', 3],
    'table valued archive first expression' => ["pragma_index_xinfo('wp_sitemeta_expr','archive')", $validDatabase, 0, 54, 'current.cid', -2, true],
    'table valued archive second expression coll' => ["pragma_index_xinfo('wp_sitemeta_expr','archive')", $validDatabase, 0, 54, 'next.coll', 'NOCASE', true],
    'table valued archive total' => ["pragma_index_xinfo('wp_sitemeta_expr','archive')", $validDatabase, 0, 54, 'total', 4, true],
    'table valued archive rowid auxiliary' => ["pragma_index_xinfo('wp_sitemeta_expr','archive')", $validDatabase, 3, 1, 'current.cid', -1, true],
    'root beyond total includes one root message' => ['PRAGMA main.index_xinfo(wp_options_name)', $beyondRootDatabase, 0, 54, 'total', 6],
    'root beyond appended kind' => ['PRAGMA main.index_xinfo(wp_options_name)', $beyondRootDatabase, 3, 4, 'current.kind', 'integrity_check'],
    'root beyond appended message' => ['PRAGMA main.index_xinfo(wp_options_name)', $beyondRootDatabase, 3, 4, 'current.message', 'sqlite_schema index wp_options_name rootpage 12 is beyond the database image'],
    'quick root beyond kind' => ['PRAGMA main.index_xinfo(wp_options_name)', $beyondRootDatabase, 3, 4, 'current.kind', 'quick_check', false, 'PRAGMA quick_check(2)'],
    'quick root beyond message' => ['PRAGMA main.index_xinfo(wp_options_name)', $beyondRootDatabase, 3, 4, 'current.message', 'sqlite_schema index wp_options_name rootpage 12 is beyond the database image', false, 'PRAGMA quick_check(2)'],
    'limit one first next offset' => ['PRAGMA main.index_xinfo(wp_options_name)', $validDatabase, 0, 1, 'next_offset', 1],
    'limit one first next null' => ['PRAGMA main.index_xinfo(wp_options_name)', $validDatabase, 0, 1, 'next', null],
    'limit one second current autoload' => ['PRAGMA main.index_xinfo(wp_options_name)', $validDatabase, 1, 1, 'current.name', 'autoload'],
    'limit one third complete' => ['PRAGMA main.index_xinfo(wp_options_name)', $validDatabase, 2, 1, 'complete', true],
    'missing index with root mismatch yields integrity only' => ['PRAGMA main.index_xinfo(wp_missing)', $rootMismatchDatabase, 0, 54, 'current.kind', 'integrity_check'],
    'missing index with valid db total zero' => ['PRAGMA main.index_xinfo(wp_missing)', $validDatabase, 0, 54, 'total', 0],
    'missing index with valid db complete' => ['PRAGMA main.index_xinfo(wp_missing)', $validDatabase, 0, 54, 'complete', true],
    'missing index root mismatch total one' => ['PRAGMA main.index_xinfo(wp_missing)', $rootMismatchDatabase, 0, 54, 'total', 2],
];

foreach ($cases as $name => $case) {
    [$sql, $database, $offset, $limit, $path, $expected] = $case;
    $tableValued = $case[6] ?? false;
    $integritySql = $case[7] ?? 'PRAGMA integrity_check';
    $tests['pragma index_xinfo integrity root current next54 ' . $name] = static function (TestRunner $t) use ($snapshot, $valueAt, $sql, $database, $offset, $limit, $path, $expected, $tableValued, $integritySql): void {
        $t->same($expected, $valueAt($snapshot($sql, $database, $offset, $limit, $integritySql, $tableValued), $path));
    };
}

$tests['pragma index_xinfo integrity root current next54 rejects negative offset'] = static function (TestRunner $t) use ($makeCatalog, $validDatabase): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLitePragmaIndexXinfoIntegrityRootYield::page($makeCatalog(), 'PRAGMA index_xinfo(wp_options_name)', $validDatabase, -1, 10));
};

$tests['pragma index_xinfo integrity root current next54 rejects zero limit'] = static function (TestRunner $t) use ($makeCatalog, $validDatabase): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLitePragmaIndexXinfoIntegrityRootYield::page($makeCatalog(), 'PRAGMA index_xinfo(wp_options_name)', $validDatabase, 0, 0));
};

$tests['pragma index_xinfo integrity root current next54 rejects non index pragma'] = static function (TestRunner $t) use ($makeCatalog, $validDatabase): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLitePragmaIndexXinfoIntegrityRootYield::page($makeCatalog(), 'PRAGMA table_info(wp_options)', $validDatabase));
};

$tests['pragma index_xinfo integrity root current next54 schema row fixture stays parseable'] = static function (TestRunner $t) use ($validDatabase, $pageSize): void {
    $firstPage = substr($validDatabase, 0, $pageSize);
    $cells = SQLiteTableLeafCell::parsePageCells($firstPage, SQLiteBTreePageHeader::parseFirstPage($firstPage));
    $t->same('wp_options', SQLiteRecord::parse($cells[0]->payload)->values[1]);
};

return $tests;
