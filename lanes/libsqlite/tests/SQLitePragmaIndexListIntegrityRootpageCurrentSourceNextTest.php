<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteAttachedSchemaCatalog;
use PortLibs\LibSqlite\SQLiteIndexLeafPage;
use PortLibs\LibSqlite\SQLitePointerMapEntry;
use PortLibs\LibSqlite\SQLitePragmaIndexListIntegrityRootpageCurrentSourceNext;
use PortLibs\LibSqlite\SQLiteRecord;
use PortLibs\LibSqlite\SQLiteSchemaRecord;
use PortLibs\LibSqlite\SQLiteTableLeafCell;
use PortLibs\LibSqlite\SQLiteTableLeafPage;

$pageSize = 1024;
$record = static fn (string $type, string $name, string $table, ?int $root, ?string $sql = null, int $rowid = 1): SQLiteSchemaRecord => new SQLiteSchemaRecord($type, $name, $table, $root, $sql, $rowid);

$catalogFactory = static function (bool $partialRepair = false) use ($record): SQLiteAttachedSchemaCatalog {
    return new SQLiteAttachedSchemaCatalog([
        $record('table', 'wp_options', 'wp_options', 4, 'CREATE TABLE wp_options(option_id INTEGER PRIMARY KEY, option_name TEXT, option_value TEXT, autoload TEXT)', 1),
        $record('index', 'wp_options_name', 'wp_options', 5, 'CREATE UNIQUE INDEX wp_options_name ON wp_options(option_name COLLATE NOCASE)', 2),
        $record('index', 'wp_options_autoload_partial', 'wp_options', $partialRepair ? 12 : 6, "CREATE INDEX wp_options_autoload_partial ON wp_options(autoload, option_name) WHERE autoload = 'yes'", 3),
        $record('index', 'sqlite_autoindex_wp_options_1', 'wp_options', 7, null, 4),
    ], [
        $record('table', 'wp_options', 'wp_options', 8, 'CREATE TABLE wp_options(option_name TEXT, autoload TEXT)', 1),
        $record('index', 'wp_options_temp_autoload', 'wp_options', 9, 'CREATE INDEX wp_options_temp_autoload ON wp_options(autoload)', 2),
    ]);
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
        $pages[$pageNumber] ??= in_array($pageNumber, [5, 6, 7, 9, 12], true)
            ? SQLiteIndexLeafPage::assemble([], $pageSize)
            : SQLiteTableLeafPage::assemble([], $pageSize);
    }
    ksort($pages);

    return implode('', $pages);
};

$schemaRows = [
    ['table', 'wp_options', 'wp_options', 4, 'CREATE TABLE wp_options(option_id integer primary key, option_name text, option_value text, autoload text)'],
    ['index', 'wp_options_name', 'wp_options', 5, 'CREATE UNIQUE INDEX wp_options_name ON wp_options(option_name COLLATE NOCASE)'],
    ['index', 'wp_options_autoload_partial', 'wp_options', 6, "CREATE INDEX wp_options_autoload_partial ON wp_options(autoload, option_name) WHERE autoload = 'yes'"],
    ['index', 'sqlite_autoindex_wp_options_1', 'wp_options', 7, null],
];
$currentDatabase = $schemaDatabase($schemaRows, 12, 7, [
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
]);
$nextDatabase = $schemaDatabase($schemaRows, 12, 7, [
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
]);
$stillBlockedDatabase = $currentDatabase;
$wrongNextCatalog = $catalogFactory(true);

$valueAt = static function (mixed $value, string $path): mixed {
    foreach (explode('.', $path) as $part) {
        $value = is_array($value) && array_key_exists($part, $value) ? $value[$part] : $value[(int) $part];
    }

    return $value;
};

$page = static fn (
    int $offset = 0,
    int $limit = 143,
    ?array $cursor = null,
    ?string $nextBytes = null,
    ?SQLiteAttachedSchemaCatalog $nextCatalog = null,
    string $sql = 'PRAGMA main.index_list(wp_options)',
    string $integritySql = 'PRAGMA integrity_check',
): array => SQLitePragmaIndexListIntegrityRootpageCurrentSourceNext::currentNextPage(
    $catalogFactory(),
    $currentDatabase,
    $nextCatalog ?? $catalogFactory(),
    $nextBytes ?? $nextDatabase,
    $sql,
    $offset,
    $limit,
    $integritySql,
    false,
    $cursor,
);

$default = static fn (): array => $page();
$blocked = static fn (): array => $page(0, 143, null, $stillBlockedDatabase);
$catalogChanged = static fn (): array => $page(0, 143, null, $nextDatabase, $wrongNextCatalog);

$cases = [
    'status ok after next repair' => [$default, 'status', 'ok'],
    'limit default next143' => [$default, 'limit', 143],
    'total current plus next' => [$default, 'total', 14],
    'count all rows' => [$default, 'count', 14],
    'complete true' => [$default, 'complete', true],
    'next null' => [$default, 'next', null],
    'source id length' => [static fn (): array => ['length' => strlen($default()['source_id'])], 'length', 64],
    'current source database hash length' => [static fn (): array => ['length' => strlen($default()['current_source']['database'])], 'length', 64],
    'next source database hash length' => [static fn (): array => ['length' => strlen($default()['next_source']['database'])], 'length', 64],
    'current source catalog hash length' => [static fn (): array => ['length' => strlen($default()['current_source']['catalog'])], 'length', 64],
    'next source catalog hash length' => [static fn (): array => ['length' => strlen($default()['next_source']['catalog'])], 'length', 64],
    'current sql normalized' => [$default, 'current_source.index_list_sql', 'pragma main.index_list(wp_options)'],
    'next sql normalized' => [$default, 'next_source.index_list_sql', 'pragma main.index_list(wp_options)'],
    'current integrity normalized' => [$default, 'current_source.integrity_sql', 'pragma integrity_check'],
    'next integrity normalized' => [$default, 'next_source.integrity_sql', 'pragma integrity_check'],
    'current index count' => [$default, 'current.index_list', 3],
    'current root count' => [$default, 'current.rootpage', 4],
    'current root errors' => [$default, 'current.rootpage_errors', 1],
    'next root errors clear' => [$default, 'next_counts.rootpage_errors', 0],
    'delta root errors' => [$default, 'delta.rootpage_errors', -1],
    'delta cleared true' => [$default, 'delta.cleared', true],
    'next state ready' => [$default, 'next_state.ready', true],
    'next blocking empty' => [$default, 'next_state.blocking', []],
    'current target schema' => [$default, 'current.target_schema', 'main'],
    'current target table' => [$default, 'current.target_table', 'wp_options'],
    'current target indexes' => [$default, 'current.target_indexes', ['wp_options_name', 'wp_options_autoload_partial', 'sqlite_autoindex_wp_options_1']],
    'next target indexes' => [$default, 'next_counts.target_indexes', ['wp_options_name', 'wp_options_autoload_partial', 'sqlite_autoindex_wp_options_1']],
    'current unique indexes' => [$default, 'current.unique_indexes', 2],
    'current partial indexes' => [$default, 'current.partial_indexes', 1],
    'row0 current side' => [$default, 'rows.0.side', 'current'],
    'row0 current index kind' => [$default, 'rows.0.kind', 'index_list'],
    'row1 partial flag' => [$default, 'rows.1.partial', 1],
    'row3 current table root' => [$default, 'rows.3.name', 'wp_options'],
    'row5 current pointer mismatch' => [$default, 'rows.5.page_status', 'pointer_map'],
    'row5 current pointer parent' => [$default, 'rows.5.pointer_map_parent', 4],
    'row7 next side' => [$default, 'rows.7.side', 'next'],
    'row10 next table root' => [$default, 'rows.10.name', 'wp_options'],
    'row12 next repaired root ok' => [$default, 'rows.12.page_status', 'ok'],
    'blocked next status' => [$blocked, 'status', 'blocked'],
    'blocked next state false' => [$blocked, 'next_state.ready', false],
    'blocked next blocking rootpage' => [$blocked, 'next_state.blocking', ['integrity_rootpage']],
    'blocked delta not cleared' => [$blocked, 'delta.cleared', false],
    'changed next catalog source changed' => [static fn (): array => ['changed' => $default()['source_id'] !== $catalogChanged()['source_id']], 'changed', true],
    'changed next catalog stays ready' => [$catalogChanged, 'next_state.ready', true],
    'limit five count' => [static fn (): array => $page(0, 5), 'count', 5],
    'limit five next offset' => [static fn (): array => $page(0, 5), 'next.offset', 5],
    'offset five first row mismatch' => [static fn (): array => $page(5, 5), 'rows.0.page_status', 'pointer_map'],
    'offset ten next row current-to-next' => [static fn (): array => $page(10, 5), 'rows.0.side', 'next'],
];

$tests = [];
foreach ($cases as $name => [$callback, $path, $expected]) {
    $tests['pragma index list integrity rootpage current source next143 ' . $name] = static function (TestRunner $t) use ($callback, $path, $expected, $valueAt): void {
        $t->same($expected, $valueAt($callback(), $path));
    };
}

$tests['pragma index list integrity rootpage current source next143 resumes with source cursor'] = static function (TestRunner $t) use ($page): void {
    $first = $page(0, 5);
    $second = $page(5, 5, $first['next']);
    $third = $page(10, 5, $second['next']);

    $t->same(['source_id' => $first['source_id'], 'offset' => 5], $first['next']);
    $t->same('wp_options_autoload_partial', $second['rows'][0]['name']);
    $t->same('next', $third['rows'][0]['side']);
    $t->same(null, $third['next']);
};

$tests['pragma index list integrity rootpage current source next143 accepts cursor offset key'] = static function (TestRunner $t) use ($page): void {
    $first = $page(0, 5);
    $second = $page(5, 5, ['source_id' => $first['source_id'], 'offset' => 5]);

    $t->same(5, $second['offset']);
    $t->same('pointer_map', $second['rows'][0]['page_status']);
};

$tests['pragma index list integrity rootpage current source next143 rejects stale next database cursor'] = static function (TestRunner $t) use ($page, $stillBlockedDatabase): void {
    $first = $page(0, 5);
    $t->throws(InvalidArgumentException::class, static fn () => $page(5, 5, $first['next'], $stillBlockedDatabase));
};

$tests['pragma index list integrity rootpage current source next143 rejects stale next catalog cursor'] = static function (TestRunner $t) use ($page, $nextDatabase, $wrongNextCatalog): void {
    $first = $page(0, 5);
    $t->throws(InvalidArgumentException::class, static fn () => $page(5, 5, $first['next'], $nextDatabase, $wrongNextCatalog));
};

$tests['pragma index list integrity rootpage current source next143 rejects stale sql cursor'] = static function (TestRunner $t) use ($page): void {
    $first = $page(0, 5);
    $t->throws(InvalidArgumentException::class, static fn () => $page(5, 5, $first['next'], null, null, 'PRAGMA index_list(wp_options)'));
};

$tests['pragma index list integrity rootpage current source next143 rejects stale integrity cursor'] = static function (TestRunner $t) use ($page): void {
    $first = $page(0, 5);
    $t->throws(InvalidArgumentException::class, static fn () => $page(5, 5, $first['next'], null, null, 'PRAGMA main.index_list(wp_options)', 'PRAGMA quick_check'));
};

$tests['pragma index list integrity rootpage current source next143 rejects stale offset cursor'] = static function (TestRunner $t) use ($page): void {
    $first = $page(0, 5);
    $t->throws(InvalidArgumentException::class, static fn () => $page(6, 5, $first['next']));
};

$tests['pragma index list integrity rootpage current source next143 rejects negative offset'] = static function (TestRunner $t) use ($catalogFactory, $currentDatabase, $nextDatabase): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLitePragmaIndexListIntegrityRootpageCurrentSourceNext::currentNextPage($catalogFactory(), $currentDatabase, $catalogFactory(), $nextDatabase, 'PRAGMA main.index_list(wp_options)', -1));
};

$tests['pragma index list integrity rootpage current source next143 rejects zero limit'] = static function (TestRunner $t) use ($catalogFactory, $currentDatabase, $nextDatabase): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLitePragmaIndexListIntegrityRootpageCurrentSourceNext::currentNextPage($catalogFactory(), $currentDatabase, $catalogFactory(), $nextDatabase, 'PRAGMA main.index_list(wp_options)', 0, 0));
};

return $tests;
