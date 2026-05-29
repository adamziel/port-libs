<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteAttachedSchemaCatalog;
use PortLibs\LibSqlite\SQLiteIndexLeafPage;
use PortLibs\LibSqlite\SQLitePointerMapEntry;
use PortLibs\LibSqlite\SQLitePragmaIndexListRootpageIntegrityCurrentSourceNext;
use PortLibs\LibSqlite\SQLiteRecord;
use PortLibs\LibSqlite\SQLiteSchemaRecord;
use PortLibs\LibSqlite\SQLiteTableLeafCell;
use PortLibs\LibSqlite\SQLiteTableLeafPage;

$pageSize = 1024;
$record = static fn (string $type, string $name, string $table, ?int $root, ?string $sql = null, int $rowid = 1): SQLiteSchemaRecord => new SQLiteSchemaRecord($type, $name, $table, $root, $sql, $rowid);

$catalogFactory = static function (bool $archiveShadow = false) use ($record): SQLiteAttachedSchemaCatalog {
    $catalog = new SQLiteAttachedSchemaCatalog([
        $record('table', 'wp_options', 'wp_options', 4, 'CREATE TABLE wp_options(option_id INTEGER PRIMARY KEY, option_name TEXT, option_value TEXT, autoload TEXT, blog_id INTEGER)', 1),
        $record('index', 'wp_options_name', 'wp_options', 5, 'CREATE UNIQUE INDEX wp_options_name ON wp_options(option_name COLLATE NOCASE)', 2),
        $record('index', 'wp_options_autoload_partial', 'wp_options', 6, "CREATE INDEX wp_options_autoload_partial ON wp_options(autoload, option_name) WHERE autoload = 'yes'", 3),
        $record('index', 'sqlite_autoindex_wp_options_1', 'wp_options', 7, null, 4),
    ], [
        $record('table', 'wp_options', 'wp_options', 8, 'CREATE TABLE wp_options(option_name TEXT, autoload TEXT)', 1),
        $record('index', 'wp_options_temp_autoload', 'wp_options', 9, 'CREATE INDEX wp_options_temp_autoload ON wp_options(autoload)', 2),
    ]);
    $catalog->attach('archive', '/srv/wp/archive.sqlite', [
        $record('table', 'wp_options', 'wp_options', 10, 'CREATE TABLE wp_options(option_name TEXT, autoload TEXT)', 1),
        $record('index', $archiveShadow ? 'wp_options_name' : 'wp_options_archive_name', 'wp_options', 11, 'CREATE INDEX ' . ($archiveShadow ? 'wp_options_name' : 'wp_options_archive_name') . ' ON wp_options(option_name)', 2),
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
        $pages[$pageNumber] ??= in_array($pageNumber, [5, 6, 7, 9, 11], true)
            ? SQLiteIndexLeafPage::assemble([], $pageSize)
            : SQLiteTableLeafPage::assemble([], $pageSize);
    }
    ksort($pages);

    return implode('', $pages);
};

$schemaRows = [
    ['table', 'wp_options', 'wp_options', 4, 'CREATE TABLE wp_options(option_id integer primary key, option_name text, option_value text, autoload text, blog_id integer)'],
    ['index', 'wp_options_name', 'wp_options', 5, 'CREATE UNIQUE INDEX wp_options_name ON wp_options(option_name COLLATE NOCASE)'],
    ['index', 'wp_options_autoload_partial', 'wp_options', 6, "CREATE INDEX wp_options_autoload_partial ON wp_options(autoload, option_name) WHERE autoload = 'yes'"],
    ['index', 'sqlite_autoindex_wp_options_1', 'wp_options', 7, null],
    ['table', 'wp_posts', 'wp_posts', 12, 'CREATE TABLE wp_posts(ID integer primary key, post_title text)'],
    ['index', 'wp_posts_title', 'wp_posts', 13, 'CREATE INDEX wp_posts_title ON wp_posts(post_title)'],
];
$validDatabase = $schemaDatabase($schemaRows, 13, 13, [
    [3, SQLitePointerMapEntry::BTREE_PAGE, 4],
    [4, SQLitePointerMapEntry::ROOT_PAGE, 0],
    [5, SQLitePointerMapEntry::ROOT_PAGE, 0],
    [6, SQLitePointerMapEntry::ROOT_PAGE, 0],
    [7, SQLitePointerMapEntry::ROOT_PAGE, 0],
    [8, SQLitePointerMapEntry::ROOT_PAGE, 0],
    [9, SQLitePointerMapEntry::ROOT_PAGE, 0],
    [10, SQLitePointerMapEntry::ROOT_PAGE, 0],
    [11, SQLitePointerMapEntry::ROOT_PAGE, 0],
    [12, SQLitePointerMapEntry::ROOT_PAGE, 0],
    [13, SQLitePointerMapEntry::ROOT_PAGE, 0],
]);
$pointerMismatchDatabase = $schemaDatabase($schemaRows, 13, 13, [
    [3, SQLitePointerMapEntry::BTREE_PAGE, 4],
    [4, SQLitePointerMapEntry::ROOT_PAGE, 0],
    [5, SQLitePointerMapEntry::ROOT_PAGE, 0],
    [6, SQLitePointerMapEntry::BTREE_PAGE, 4],
    [7, SQLitePointerMapEntry::ROOT_PAGE, 0],
    [8, SQLitePointerMapEntry::ROOT_PAGE, 0],
    [9, SQLitePointerMapEntry::ROOT_PAGE, 0],
    [10, SQLitePointerMapEntry::ROOT_PAGE, 0],
    [11, SQLitePointerMapEntry::ROOT_PAGE, 0],
    [12, SQLitePointerMapEntry::ROOT_PAGE, 0],
    [13, SQLitePointerMapEntry::ROOT_PAGE, 0],
]);
$wrongTypeDatabase = substr_replace($validDatabase, SQLiteTableLeafPage::assemble([], $pageSize), $pageSize * 4, $pageSize);
$beyondDatabase = $schemaDatabase([
    $schemaRows[0],
    $schemaRows[1],
    ['index', 'wp_options_autoload_partial', 'wp_options', 16, $schemaRows[2][4]],
    $schemaRows[3],
    $schemaRows[4],
    $schemaRows[5],
], 13, 13, [
    [3, SQLitePointerMapEntry::BTREE_PAGE, 4],
    [4, SQLitePointerMapEntry::ROOT_PAGE, 0],
    [5, SQLitePointerMapEntry::ROOT_PAGE, 0],
    [6, SQLitePointerMapEntry::ROOT_PAGE, 0],
    [7, SQLitePointerMapEntry::ROOT_PAGE, 0],
    [8, SQLitePointerMapEntry::ROOT_PAGE, 0],
    [9, SQLitePointerMapEntry::ROOT_PAGE, 0],
    [10, SQLitePointerMapEntry::ROOT_PAGE, 0],
    [11, SQLitePointerMapEntry::ROOT_PAGE, 0],
    [12, SQLitePointerMapEntry::ROOT_PAGE, 0],
    [13, SQLitePointerMapEntry::ROOT_PAGE, 0],
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
    int $limit = 139,
    string $integritySql = 'PRAGMA integrity_check',
    bool $tableValued = false,
    ?array $cursor = null,
    ?SQLiteAttachedSchemaCatalog $catalog = null,
): array => SQLitePragmaIndexListRootpageIntegrityCurrentSourceNext::page(
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
$wrongType = static fn (): array => $page('PRAGMA main.index_list(wp_options)', $wrongTypeDatabase);
$beyond = static fn (): array => $page('PRAGMA main.index_list(wp_options)', $beyondDatabase);
$temp = static fn (): array => $page('PRAGMA index_list(wp_options)', $validDatabase);
$archive = static fn (): array => $page("pragma_index_list('wp_options','archive')", $validDatabase, 0, 139, 'PRAGMA quick_check', true);
$changedCatalog = static fn (): array => $page('PRAGMA main.index_list(wp_options)', $validDatabase, 0, 139, 'PRAGMA integrity_check', false, null, $catalogFactory(true));
$mutated = static fn (): array => $page('PRAGMA main.index_list(wp_options)', $mutatedDatabase);

$cases = [
    'default status blocked' => [$default, 'status', 'blocked'],
    'default limit next139' => [$default, 'limit', 139],
    'default total rows' => [$default, 'total', 7],
    'default count rows' => [$default, 'count', 7],
    'default complete' => [$default, 'complete', true],
    'default next null' => [$default, 'next', null],
    'source id length' => [static fn (): array => ['length' => strlen($default()['source_id'])], 'length', 64],
    'database source length' => [static fn (): array => ['length' => strlen($default()['current_source']['database'])], 'length', 64],
    'catalog source length' => [static fn (): array => ['length' => strlen($default()['current_source']['catalog'])], 'length', 64],
    'normalized index list sql' => [$default, 'current_source.index_list_sql', 'pragma main.index_list(wp_options)'],
    'normalized integrity sql' => [$default, 'current_source.integrity_sql', 'pragma integrity_check'],
    'table valued false' => [$default, 'current_source.table_valued', false],
    'current index count' => [$default, 'current.index_list', 3],
    'current rootpage count' => [$default, 'current.rootpage', 4],
    'current rootpage errors' => [$default, 'current.rootpage_errors', 1],
    'current unique count' => [$default, 'current.unique_indexes', 2],
    'current partial count' => [$default, 'current.partial_indexes', 1],
    'current target schema' => [$default, 'current.target_schema', 'main'],
    'current target table' => [$default, 'current.target_table', 'wp_options'],
    'current target indexes' => [$default, 'current.target_indexes', ['wp_options_name', 'wp_options_autoload_partial', 'sqlite_autoindex_wp_options_1']],
    'row0 kind' => [$default, 'rows.0.kind', 'index_list'],
    'row0 source' => [$default, 'rows.0.source', 'index_list'],
    'row0 schema' => [$default, 'rows.0.schema', 'main'],
    'row0 target' => [$default, 'rows.0.target', 'wp_options'],
    'row0 unique index name' => [$default, 'rows.0.name', 'wp_options_name'],
    'row0 unique flag' => [$default, 'rows.0.unique', 1],
    'row0 origin create' => [$default, 'rows.0.origin', 'c'],
    'row0 partial false' => [$default, 'rows.0.partial', 0],
    'row1 partial index name' => [$default, 'rows.1.name', 'wp_options_autoload_partial'],
    'row1 partial flag' => [$default, 'rows.1.partial', 1],
    'row2 autoindex origin' => [$default, 'rows.2.origin', 'u'],
    'row2 autoindex unique' => [$default, 'rows.2.unique', 1],
    'row3 table root source' => [$default, 'rows.3.source', 'rootpage_integrity'],
    'row3 table root ok' => [$default, 'rows.3.page_status', 'ok'],
    'row3 table name' => [$default, 'rows.3.name', 'wp_options'],
    'row4 first index root ok' => [$default, 'rows.4.page_status', 'ok'],
    'row4 first index name' => [$default, 'rows.4.name', 'wp_options_name'],
    'row5 partial root pointer mismatch' => [$default, 'rows.5.page_status', 'pointer_map'],
    'row5 pointer type' => [$default, 'rows.5.pointer_map_type', 'btree-page'],
    'row5 pointer parent' => [$default, 'rows.5.pointer_map_parent', 4],
    'row5 message' => [$default, 'rows.5.message', 'sqlite_schema index wp_options_autoload_partial rootpage 6 pointer-map btree-page parent 4 does not match expected root-page parent 0'],
    'row6 autoindex root ok' => [$default, 'rows.6.page_status', 'ok'],
    'valid status ok' => [$valid, 'status', 'ok'],
    'valid root errors zero' => [$valid, 'current.rootpage_errors', 0],
    'valid partial root ok' => [$valid, 'rows.5.page_status', 'ok'],
    'wrong type blocked' => [$wrongType, 'status', 'blocked'],
    'wrong type status' => [$wrongType, 'rows.4.page_status', 'wrong_btree_type'],
    'wrong type page type' => [$wrongType, 'rows.4.page_type', 'table-leaf'],
    'beyond blocked' => [$beyond, 'status', 'blocked'],
    'beyond first index root remains ok' => [$beyond, 'rows.4.page_status', 'ok'],
    'beyond root status' => [$beyond, 'rows.7.page_status', 'beyond_image'],
    'beyond root message' => [$beyond, 'rows.7.message', 'sqlite_schema index wp_options_autoload_partial rootpage 16 is beyond the database image'],
    'temp schema selected by unqualified table' => [$temp, 'current.target_schema', 'temp'],
    'temp target index' => [$temp, 'current.target_indexes', ['wp_options_temp_autoload']],
    'temp total rows' => [$temp, 'total', 2],
    'archive table valued true' => [$archive, 'current_source.table_valued', true],
    'archive quick source' => [$archive, 'current_source.integrity_sql', 'pragma quick_check'],
    'archive schema selected' => [$archive, 'current.target_schema', 'archive'],
    'archive target index' => [$archive, 'current.target_indexes', ['wp_options_archive_name']],
    'changed catalog source changed' => [static fn (): array => ['changed' => $valid()['source_id'] !== $changedCatalog()['source_id']], 'changed', true],
    'mutated database source changed' => [static fn (): array => ['changed' => $valid()['source_id'] !== $mutated()['source_id']], 'changed', true],
    'limit three count' => [static fn (): array => $page('PRAGMA main.index_list(wp_options)', $pointerMismatchDatabase, 0, 3), 'count', 3],
    'limit three next offset' => [static fn (): array => $page('PRAGMA main.index_list(wp_options)', $pointerMismatchDatabase, 0, 3), 'next.offset', 3],
    'offset three first root row' => [static fn (): array => $page('PRAGMA main.index_list(wp_options)', $pointerMismatchDatabase, 3, 3), 'rows.0.kind', 'rootpage'],
    'offset three next row name' => [static fn (): array => $page('PRAGMA main.index_list(wp_options)', $pointerMismatchDatabase, 3, 3), 'next_row.name', 'wp_options_name'],
];

$tests = [];
foreach ($cases as $name => [$callback, $path, $expected]) {
    $tests['pragma index list rootpage integrity current source next139 ' . $name] = static function (TestRunner $t) use ($callback, $valueAt, $path, $expected): void {
        $t->same($expected, $valueAt($callback(), $path));
    };
}

$tests['pragma index list rootpage integrity current source next139 resumes with source cursor'] = static function (TestRunner $t) use ($page, $pointerMismatchDatabase): void {
    $first = $page('PRAGMA main.index_list(wp_options)', $pointerMismatchDatabase, 0, 3);
    $second = $page('PRAGMA main.index_list(wp_options)', $pointerMismatchDatabase, 3, 3, 'PRAGMA integrity_check', false, $first['next']);
    $third = $page('PRAGMA main.index_list(wp_options)', $pointerMismatchDatabase, 6, 3, 'PRAGMA integrity_check', false, $second['next']);

    $t->same(['source_id' => $first['source_id'], 'offset' => 3], $first['next']);
    $t->same('wp_options', $second['rows'][0]['name']);
    $t->same('wp_options_autoload_partial', $second['rows'][2]['name']);
    $t->same(['source_id' => $first['source_id'], 'offset' => 6], $second['next']);
    $t->same('sqlite_autoindex_wp_options_1', $third['rows'][0]['name']);
    $t->same(null, $third['next']);
};

$tests['pragma index list rootpage integrity current source next139 accepts cursor offset key'] = static function (TestRunner $t) use ($page, $pointerMismatchDatabase): void {
    $first = $page('PRAGMA main.index_list(wp_options)', $pointerMismatchDatabase, 0, 3);
    $second = $page('PRAGMA main.index_list(wp_options)', $pointerMismatchDatabase, 3, 3, 'PRAGMA integrity_check', false, ['source_id' => $first['source_id'], 'offset' => 3]);

    $t->same(3, $second['offset']);
    $t->same('rootpage', $second['rows'][0]['kind']);
};

$tests['pragma index list rootpage integrity current source next139 rejects stale database cursor'] = static function (TestRunner $t) use ($page, $pointerMismatchDatabase, $validDatabase): void {
    $first = $page('PRAGMA main.index_list(wp_options)', $pointerMismatchDatabase, 0, 3);
    $t->throws(InvalidArgumentException::class, static fn () => $page('PRAGMA main.index_list(wp_options)', $validDatabase, 3, 3, 'PRAGMA integrity_check', false, $first['next']));
};

$tests['pragma index list rootpage integrity current source next139 rejects stale catalog cursor'] = static function (TestRunner $t) use ($page, $validDatabase, $catalogFactory): void {
    $first = $page('PRAGMA main.index_list(wp_options)', $validDatabase, 0, 3);
    $t->throws(InvalidArgumentException::class, static fn () => $page('PRAGMA main.index_list(wp_options)', $validDatabase, 3, 3, 'PRAGMA integrity_check', false, $first['next'], $catalogFactory(true)));
};

$tests['pragma index list rootpage integrity current source next139 rejects stale sql cursor'] = static function (TestRunner $t) use ($page, $pointerMismatchDatabase): void {
    $first = $page('PRAGMA main.index_list(wp_options)', $pointerMismatchDatabase, 0, 3);
    $t->throws(InvalidArgumentException::class, static fn () => $page('PRAGMA index_list(wp_options)', $pointerMismatchDatabase, 3, 3, 'PRAGMA integrity_check', false, $first['next']));
};

$tests['pragma index list rootpage integrity current source next139 rejects stale integrity cursor'] = static function (TestRunner $t) use ($page, $pointerMismatchDatabase): void {
    $first = $page('PRAGMA main.index_list(wp_options)', $pointerMismatchDatabase, 0, 3);
    $t->throws(InvalidArgumentException::class, static fn () => $page('PRAGMA main.index_list(wp_options)', $pointerMismatchDatabase, 3, 3, 'PRAGMA quick_check', false, $first['next']));
};

$tests['pragma index list rootpage integrity current source next139 rejects stale offset cursor'] = static function (TestRunner $t) use ($page, $pointerMismatchDatabase): void {
    $first = $page('PRAGMA main.index_list(wp_options)', $pointerMismatchDatabase, 0, 3);
    $t->throws(InvalidArgumentException::class, static fn () => $page('PRAGMA main.index_list(wp_options)', $pointerMismatchDatabase, 4, 3, 'PRAGMA integrity_check', false, $first['next']));
};

$tests['pragma index list rootpage integrity current source next139 rejects negative offset'] = static function (TestRunner $t) use ($catalogFactory, $validDatabase): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLitePragmaIndexListRootpageIntegrityCurrentSourceNext::page($catalogFactory(), 'PRAGMA index_list(wp_options)', $validDatabase, -1, 139));
};

$tests['pragma index list rootpage integrity current source next139 rejects zero limit'] = static function (TestRunner $t) use ($catalogFactory, $validDatabase): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLitePragmaIndexListRootpageIntegrityCurrentSourceNext::page($catalogFactory(), 'PRAGMA index_list(wp_options)', $validDatabase, 0, 0));
};

return $tests;
