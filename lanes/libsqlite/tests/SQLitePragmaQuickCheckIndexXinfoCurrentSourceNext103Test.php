<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteAttachedSchemaCatalog;
use PortLibs\LibSqlite\SQLitePointerMapEntry;
use PortLibs\LibSqlite\SQLitePragmaIndexXinfoIntegrityRootYield;
use PortLibs\LibSqlite\SQLiteRecord;
use PortLibs\LibSqlite\SQLiteSchemaRecord;
use PortLibs\LibSqlite\SQLiteTableLeafCell;
use PortLibs\LibSqlite\SQLiteTableLeafPage;

$record = static fn (string $type, string $name, string $table, ?int $root, ?string $sql = null, int $rowid = 1): SQLiteSchemaRecord => new SQLiteSchemaRecord(
    $type,
    $name,
    $table,
    $root,
    $sql,
    $rowid,
);

$catalogFactory = static function (bool $archiveShadow = false) use ($record): SQLiteAttachedSchemaCatalog {
    $catalog = new SQLiteAttachedSchemaCatalog([
        $record('table', 'wp_options', 'wp_options', 4, 'CREATE TABLE wp_options(option_id INTEGER PRIMARY KEY, option_name TEXT, option_value TEXT, autoload TEXT, updated_at TEXT)', 1),
        $record('index', 'wp_options_name', 'wp_options', 5, 'CREATE UNIQUE INDEX wp_options_name ON wp_options(option_name COLLATE NOCASE DESC, autoload)', 2),
        $record('index', 'wp_options_json_expr', 'wp_options', 6, "CREATE INDEX wp_options_json_expr ON wp_options(json_extract(option_value, '$.plugin'), lower(option_name) COLLATE nocase, updated_at DESC)", 3),
    ], [
        $record('table', 'wp_options', 'wp_options', 7, 'CREATE TABLE wp_options(option_name TEXT, option_value TEXT, autoload TEXT)', 1),
        $record('index', 'wp_options_temp_expr', 'wp_options', 8, 'CREATE INDEX wp_options_temp_expr ON wp_options(upper(option_name) COLLATE rtrim, autoload DESC)', 2),
    ]);
    $catalog->attach('archive', '/srv/wp/archive.sqlite', [
        $record('table', 'wp_options', 'wp_options', 9, 'CREATE TABLE wp_options(option_id INTEGER PRIMARY KEY, option_name TEXT, option_value TEXT, autoload TEXT)', 1),
        $record('index', $archiveShadow ? 'wp_options_json_expr' : 'wp_options_archive_expr', 'wp_options', 10, "CREATE INDEX " . ($archiveShadow ? 'wp_options_json_expr' : 'wp_options_archive_expr') . " ON wp_options(json_extract(option_value, '$.legacy'), option_name COLLATE rtrim DESC)", 2),
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
    ['index', 'wp_options_json_expr', 'wp_options', 6, "CREATE INDEX wp_options_json_expr ON wp_options(json_extract(option_value, '$.plugin'), option_name)"],
];
$database = $schemaDatabase($schemaRows, 7, 6, [
    [3, SQLitePointerMapEntry::BTREE_PAGE, 4],
    [4, SQLitePointerMapEntry::ROOT_PAGE, 0],
    [5, SQLitePointerMapEntry::ROOT_PAGE, 0],
    [6, SQLitePointerMapEntry::ROOT_PAGE, 0],
    [7, SQLitePointerMapEntry::BTREE_PAGE, 6],
]);
$rootMismatchDatabase = $schemaDatabase($schemaRows, 7, 4, [
    [3, SQLitePointerMapEntry::BTREE_PAGE, 4],
    [4, SQLitePointerMapEntry::ROOT_PAGE, 0],
    [5, SQLitePointerMapEntry::BTREE_PAGE, 4],
    [6, SQLitePointerMapEntry::BTREE_PAGE, 4],
    [7, SQLitePointerMapEntry::BTREE_PAGE, 6],
]);
$rootBeyondDatabase = $schemaDatabase([
    $schemaRows[0],
    $schemaRows[1],
    ['index', 'wp_options_json_expr', 'wp_options', 12, $schemaRows[2][4]],
], 7, 5, [
    [3, SQLitePointerMapEntry::BTREE_PAGE, 4],
    [4, SQLitePointerMapEntry::ROOT_PAGE, 0],
    [5, SQLitePointerMapEntry::ROOT_PAGE, 0],
    [6, SQLitePointerMapEntry::BTREE_PAGE, 4],
    [7, SQLitePointerMapEntry::BTREE_PAGE, 6],
]);
$mutatedDatabase = $database;
$mutatedDatabase[40] = "\x01";

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

$page = static fn (
    ?string $sql = null,
    ?string $db = null,
    int $offset = 0,
    int $limit = 103,
    string $integritySql = 'PRAGMA quick_check',
    bool $tableValued = false,
    ?array $cursor = null,
    ?SQLiteAttachedSchemaCatalog $catalog = null,
): array => SQLitePragmaIndexXinfoIntegrityRootYield::pageWithSourceCursor(
    $catalog ?? $catalogFactory(),
    $sql ?? 'PRAGMA main.index_xinfo(wp_options_json_expr)',
    $db ?? $rootMismatchDatabase,
    $offset,
    $limit,
    $integritySql,
    $tableValued,
    $cursor,
);

$default = static fn (): array => $page();
$valid = static fn (): array => $page('PRAGMA main.index_xinfo(wp_options_json_expr)', $database);
$rootBeyond = static fn (): array => $page('PRAGMA main.index_xinfo(wp_options_json_expr)', $rootBeyondDatabase);
$temp = static fn (): array => $page('PRAGMA index_xinfo(wp_options_temp_expr)', $database);
$archiveTableValued = static fn (): array => $page("pragma_index_xinfo('wp_options_archive_expr','archive')", $database, 0, 103, 'PRAGMA main.quick_check', true);
$mutatedDatabasePage = static fn (): array => $page('PRAGMA main.index_xinfo(wp_options_json_expr)', $mutatedDatabase);
$changedCatalogPage = static fn (): array => $page('PRAGMA index_xinfo(wp_options_json_expr)', $database, 0, 103, 'PRAGMA quick_check', false, null, $catalogFactory(true));

$cases = [
    'status ok' => [$default, 'status', 'ok'],
    'source id length' => [static fn (): array => ['len' => strlen($default()['source_id'])], 'len', 64],
    'database hash length' => [static fn (): array => ['len' => strlen($default()['current_source']['database'])], 'len', 64],
    'catalog hash length' => [static fn (): array => ['len' => strlen($default()['current_source']['catalog'])], 'len', 64],
    'normalized index sql' => [$default, 'current_source.index_xinfo_sql', 'pragma main.index_xinfo(wp_options_json_expr)'],
    'normalized quick sql' => [$default, 'current_source.integrity_sql', 'pragma quick_check'],
    'statement table valued false' => [$default, 'current_source.table_valued', false],
    'limit default next103' => [$default, 'limit', 103],
    'total includes quick root messages' => [$default, 'total', 7],
    'count includes all rows' => [$default, 'count', 7],
    'complete all rows' => [$default, 'complete', true],
    'next cursor null complete' => [$default, 'next', null],
    'current row kind index xinfo' => [$default, 'current.kind', 'index_xinfo'],
    'current row schema main' => [$default, 'current.schema', 'main'],
    'current row expression cid' => [$default, 'current.cid', -2],
    'current row key one' => [$default, 'current.key', 1],
    'next row expression name null' => [$default, 'next_row.name', null],
    'row one lower collation nocase' => [$default, 'rows.1.coll', 'NOCASE'],
    'row two updated desc' => [$default, 'rows.2.desc', 1],
    'row three rowid auxiliary' => [$default, 'rows.3.cid', -1],
    'row three rowid key false' => [$default, 'rows.3.key', 0],
    'row four quick kind' => [$default, 'rows.4.kind', 'quick_check'],
    'row four quick message' => [$default, 'rows.4.message', 'largest root btree page 4 does not match sqlite_schema max rootpage 6'],
    'valid total omits integrity rows' => [$valid, 'total', 4],
    'valid complete' => [$valid, 'complete', true],
    'valid rowid message' => [$valid, 'rows.3.message', 'index_xinfo main.wp_options_json_expr seqno 3 cid -1 expression/rowid coll BINARY key 0'],
    'root beyond total' => [$rootBeyond, 'total', 5],
    'root beyond quick kind' => [$rootBeyond, 'rows.4.kind', 'quick_check'],
    'root beyond message' => [$rootBeyond, 'rows.4.message', 'sqlite_schema index wp_options_json_expr rootpage 12 is beyond the database image'],
    'temp unqualified resolves temp schema' => [$temp, 'current.schema', 'temp'],
    'temp unqualified first collation rtrim' => [$temp, 'current.coll', 'RTRIM'],
    'temp unqualified next autoload desc' => [$temp, 'next_row.desc', 1],
    'temp unqualified total' => [$temp, 'total', 3],
    'archive table valued source true' => [$archiveTableValued, 'current_source.table_valued', true],
    'archive table valued normalized sql' => [$archiveTableValued, 'current_source.index_xinfo_sql', "pragma_index_xinfo('wp_options_archive_expr','archive')"],
    'archive table valued schema' => [$archiveTableValued, 'current.schema', 'archive'],
    'archive table valued expression cid' => [$archiveTableValued, 'current.cid', -2],
    'archive table valued rtrim desc' => [$archiveTableValued, 'next_row.coll', 'RTRIM'],
    'archive table valued total' => [$archiveTableValued, 'total', 3],
    'limit two count' => [static fn (): array => $page('PRAGMA main.index_xinfo(wp_options_json_expr)', $rootMismatchDatabase, 0, 2), 'count', 2],
    'limit two next offset' => [static fn (): array => $page('PRAGMA main.index_xinfo(wp_options_json_expr)', $rootMismatchDatabase, 0, 2), 'next_offset', 2],
    'offset two current updated' => [static fn (): array => $page('PRAGMA main.index_xinfo(wp_options_json_expr)', $rootMismatchDatabase, 2, 2), 'current.name', 'updated_at'],
    'offset two next rowid' => [static fn (): array => $page('PRAGMA main.index_xinfo(wp_options_json_expr)', $rootMismatchDatabase, 2, 2), 'next_row.cid', -1],
    'offset four current quick' => [static fn (): array => $page('PRAGMA main.index_xinfo(wp_options_json_expr)', $rootMismatchDatabase, 4, 2), 'current.kind', 'quick_check'],
    'offset four leaves final quick page' => [static fn (): array => $page('PRAGMA main.index_xinfo(wp_options_json_expr)', $rootMismatchDatabase, 4, 2), 'complete', false],
    'missing index valid total zero' => [static fn (): array => $page('PRAGMA main.index_xinfo(missing_index)', $database), 'total', 0],
    'missing index mismatch total three' => [static fn (): array => $page('PRAGMA main.index_xinfo(missing_index)', $rootMismatchDatabase), 'total', 3],
    'missing index mismatch current quick' => [static fn (): array => $page('PRAGMA main.index_xinfo(missing_index)', $rootMismatchDatabase), 'current.kind', 'quick_check'],
    'database mutation changes source id' => [static fn (): array => ['changed' => $default()['source_id'] !== $mutatedDatabasePage()['source_id']], 'changed', true],
    'database mutation changes database hash' => [static fn (): array => ['changed' => $default()['current_source']['database'] !== $mutatedDatabasePage()['current_source']['database']], 'changed', true],
    'catalog mutation changes source id' => [static fn (): array => ['changed' => $valid()['source_id'] !== $changedCatalogPage()['source_id']], 'changed', true],
    'catalog mutation changes catalog hash' => [static fn (): array => ['changed' => $valid()['current_source']['catalog'] !== $changedCatalogPage()['current_source']['catalog']], 'changed', true],
    'catalog shadow keeps main before attached schema' => [$changedCatalogPage, 'current.schema', 'main'],
    'catalog shadow source sql unqualified' => [$changedCatalogPage, 'current_source.index_xinfo_sql', 'pragma index_xinfo(wp_options_json_expr)'],
];

$tests = [];
foreach ($cases as $name => [$callback, $path, $expected]) {
    $tests['pragma quickcheck index_xinfo current source next103 ' . $name] = static function (TestRunner $t) use ($callback, $valueAt, $path, $expected): void {
        $t->same($expected, $valueAt($callback(), $path));
    };
}

$tests['pragma quickcheck index_xinfo current source next103 resumes through source cursor'] = static function (TestRunner $t) use ($page, $rootMismatchDatabase): void {
    $first = $page('PRAGMA main.index_xinfo(wp_options_json_expr)', $rootMismatchDatabase, 0, 2);
    $second = $page('PRAGMA main.index_xinfo(wp_options_json_expr)', $rootMismatchDatabase, 2, 2, 'PRAGMA quick_check', false, $first['next']);
    $third = $page('PRAGMA main.index_xinfo(wp_options_json_expr)', $rootMismatchDatabase, 4, 2, 'PRAGMA quick_check', false, $second['next']);
    $fourth = $page('PRAGMA main.index_xinfo(wp_options_json_expr)', $rootMismatchDatabase, 6, 2, 'PRAGMA quick_check', false, $third['next']);

    $t->same(2, $first['count']);
    $t->same(['source_id' => $first['source_id'], 'offset' => 2], $first['next']);
    $t->same('updated_at', $second['current']['name']);
    $t->same(-1, $second['next_row']['cid']);
    $t->same(['source_id' => $first['source_id'], 'offset' => 4], $second['next']);
    $t->same('quick_check', $third['current']['kind']);
    $t->same(['source_id' => $first['source_id'], 'offset' => 6], $third['next']);
    $t->same('quick_check', $fourth['current']['kind']);
    $t->same(null, $fourth['next']);
};

$tests['pragma quickcheck index_xinfo current source next103 accepts cursor offset key'] = static function (TestRunner $t) use ($page, $rootMismatchDatabase): void {
    $first = $page('PRAGMA main.index_xinfo(wp_options_json_expr)', $rootMismatchDatabase, 0, 3);
    $second = $page('PRAGMA main.index_xinfo(wp_options_json_expr)', $rootMismatchDatabase, 3, 3, 'PRAGMA quick_check', false, ['source_id' => $first['source_id'], 'offset' => 3]);

    $t->same(3, $second['offset']);
    $t->same(-1, $second['current']['cid']);
    $t->same('quick_check', $second['next_row']['kind']);
};

$tests['pragma quickcheck index_xinfo current source next103 rejects stale database cursor'] = static function (TestRunner $t) use ($page, $rootMismatchDatabase, $mutatedDatabase): void {
    $first = $page('PRAGMA main.index_xinfo(wp_options_json_expr)', $rootMismatchDatabase, 0, 2);
    $t->throws(InvalidArgumentException::class, static fn () => $page('PRAGMA main.index_xinfo(wp_options_json_expr)', $mutatedDatabase, 2, 2, 'PRAGMA quick_check', false, $first['next']));
};

$tests['pragma quickcheck index_xinfo current source next103 rejects stale catalog cursor'] = static function (TestRunner $t) use ($page, $database, $catalogFactory): void {
    $first = $page('PRAGMA index_xinfo(wp_options_json_expr)', $database, 0, 2);
    $t->throws(InvalidArgumentException::class, static fn () => $page('PRAGMA index_xinfo(wp_options_json_expr)', $database, 2, 2, 'PRAGMA quick_check', false, $first['next'], $catalogFactory(true)));
};

$tests['pragma quickcheck index_xinfo current source next103 rejects stale sql cursor'] = static function (TestRunner $t) use ($page, $rootMismatchDatabase): void {
    $first = $page('PRAGMA main.index_xinfo(wp_options_json_expr)', $rootMismatchDatabase, 0, 2);
    $t->throws(InvalidArgumentException::class, static fn () => $page('PRAGMA index_xinfo(wp_options_json_expr)', $rootMismatchDatabase, 2, 2, 'PRAGMA quick_check', false, $first['next']));
};

$tests['pragma quickcheck index_xinfo current source next103 rejects stale integrity cursor'] = static function (TestRunner $t) use ($page, $rootMismatchDatabase): void {
    $first = $page('PRAGMA main.index_xinfo(wp_options_json_expr)', $rootMismatchDatabase, 0, 2);
    $t->throws(InvalidArgumentException::class, static fn () => $page('PRAGMA main.index_xinfo(wp_options_json_expr)', $rootMismatchDatabase, 2, 2, 'PRAGMA integrity_check', false, $first['next']));
};

$tests['pragma quickcheck index_xinfo current source next103 rejects stale offset cursor'] = static function (TestRunner $t) use ($page, $rootMismatchDatabase): void {
    $first = $page('PRAGMA main.index_xinfo(wp_options_json_expr)', $rootMismatchDatabase, 0, 2);
    $t->throws(InvalidArgumentException::class, static fn () => $page('PRAGMA main.index_xinfo(wp_options_json_expr)', $rootMismatchDatabase, 3, 2, 'PRAGMA quick_check', false, $first['next']));
};

$tests['pragma quickcheck index_xinfo current source next103 rejects negative offset'] = static function (TestRunner $t) use ($catalogFactory, $database): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLitePragmaIndexXinfoIntegrityRootYield::pageWithSourceCursor($catalogFactory(), 'PRAGMA index_xinfo(wp_options_name)', $database, -1, 103));
};

$tests['pragma quickcheck index_xinfo current source next103 rejects zero limit'] = static function (TestRunner $t) use ($catalogFactory, $database): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLitePragmaIndexXinfoIntegrityRootYield::pageWithSourceCursor($catalogFactory(), 'PRAGMA index_xinfo(wp_options_name)', $database, 0, 0));
};

return $tests;
