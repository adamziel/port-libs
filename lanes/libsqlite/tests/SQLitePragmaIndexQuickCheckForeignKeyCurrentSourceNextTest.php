<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteAttachedSchemaCatalog;
use PortLibs\LibSqlite\SQLiteIndexLeafPage;
use PortLibs\LibSqlite\SQLitePointerMapEntry;
use PortLibs\LibSqlite\SQLitePragmaIndexQuickCheckForeignKeyCurrentSourceNext;
use PortLibs\LibSqlite\SQLiteRecord;
use PortLibs\LibSqlite\SQLiteSchemaRecord;
use PortLibs\LibSqlite\SQLiteTableLeafCell;
use PortLibs\LibSqlite\SQLiteTableLeafPage;

$pageSize = 1024;
$record = static fn (string $type, string $name, string $table, int $root, ?string $sql = null, int $rowid = 1): SQLiteSchemaRecord => new SQLiteSchemaRecord($type, $name, $table, $root, $sql ?? 'CREATE ' . strtoupper($type) . ' ' . $name, $rowid);

$catalogFactory = static function (bool $extraIndex = false) use ($record): SQLiteAttachedSchemaCatalog {
    $records = [
        $record('table', 'wp_options', 'wp_options', 4, 'CREATE TABLE wp_options(option_id INTEGER PRIMARY KEY, option_name TEXT, option_value TEXT, autoload TEXT)', 1),
        $record('table', 'wp_option_names', 'wp_option_names', 8, 'CREATE TABLE wp_option_names(name TEXT PRIMARY KEY)', 2),
        $record('index', 'wp_options_name', 'wp_options', 5, 'CREATE UNIQUE INDEX wp_options_name ON wp_options(option_name COLLATE NOCASE)', 3),
        $record('index', 'wp_options_autoload', 'wp_options', 6, 'CREATE INDEX wp_options_autoload ON wp_options(autoload DESC)', 4),
    ];
    if ($extraIndex) {
        $records[] = $record('index', 'wp_options_value_expr', 'wp_options', 7, "CREATE INDEX wp_options_value_expr ON wp_options(json_extract(option_value, '$.plugin'))", 5);
    }

    return new SQLiteAttachedSchemaCatalog($records);
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
$schemaDatabase = static function (array $pointerMapEntries, int $pageCount = 8) use ($headerPage, $putPointerMapEntry, $schemaCell, $pageSize): string {
    $schemaRows = [
        ['table', 'wp_options', 'wp_options', 4, 'CREATE TABLE wp_options(option_id integer primary key, option_name text, option_value text, autoload text)'],
        ['table', 'wp_option_names', 'wp_option_names', 8, 'CREATE TABLE wp_option_names(name text primary key)'],
        ['index', 'wp_options_name', 'wp_options', 5, 'CREATE UNIQUE INDEX wp_options_name ON wp_options(option_name COLLATE NOCASE)'],
        ['index', 'wp_options_autoload', 'wp_options', 6, 'CREATE INDEX wp_options_autoload ON wp_options(autoload DESC)'],
    ];
    $pages = [
        1 => SQLiteTableLeafPage::assemble(
            array_map(static fn (array $row, int $index): string => $schemaCell($row, $index + 1), $schemaRows, array_keys($schemaRows)),
            $pageSize,
            100,
            $headerPage($pageCount, $pageCount),
        ),
        2 => str_repeat("\0", $pageSize),
    ];
    foreach ($pointerMapEntries as $entry) {
        $pages[2] = $putPointerMapEntry($pages[2], $entry[0], $entry[1], $entry[2]);
    }
    for ($pageNumber = 3; $pageNumber <= $pageCount; $pageNumber++) {
        $pages[$pageNumber] = in_array($pageNumber, [5, 6], true)
            ? SQLiteIndexLeafPage::assemble([], $pageSize)
            : SQLiteTableLeafPage::assemble([], $pageSize);
    }
    ksort($pages);

    return implode('', $pages);
};

$currentDatabase = $schemaDatabase([
    [3, SQLitePointerMapEntry::BTREE_PAGE, 4],
    [4, SQLitePointerMapEntry::BTREE_PAGE, 3],
    [5, SQLitePointerMapEntry::ROOT_PAGE, 0],
    [6, SQLitePointerMapEntry::BTREE_PAGE, 4],
    [8, SQLitePointerMapEntry::ROOT_PAGE, 0],
]);
$nextDatabase = $schemaDatabase([
    [3, SQLitePointerMapEntry::BTREE_PAGE, 4],
    [4, SQLitePointerMapEntry::ROOT_PAGE, 0],
    [5, SQLitePointerMapEntry::ROOT_PAGE, 0],
    [6, SQLitePointerMapEntry::ROOT_PAGE, 0],
    [8, SQLitePointerMapEntry::ROOT_PAGE, 0],
]);
$mutatedNextDatabase = $nextDatabase;
$mutatedNextDatabase[48] = "\x02";

$schemas = static function (int $missing = 3): array {
    $rows = [['rowid' => 1, 'option_name' => 'siteurl', 'autoload' => 'yes']];
    for ($i = 1; $i <= $missing; $i++) {
        $rows[] = ['rowid' => 'missing-' . $i, 'option_name' => 'missing_' . $i, 'autoload' => 'no'];
    }

    return [
        'main' => [
            'tables' => [
                'wp_option_names' => [['rowid' => 1, 'name' => 'siteurl']],
                'wp_options' => $rows,
            ],
            'foreignKeys' => [
                ['id' => 141, 'table' => 'wp_options', 'parent' => 'wp_option_names', 'columns' => [
                    ['child' => 'option_name', 'parent' => 'name', 'affinity' => 'text', 'collation' => 'nocase'],
                ]],
            ],
        ],
    ];
};

$cleanSchemas = $schemas(0);
$indexListSql = 'PRAGMA main.index_list(wp_options)';
$foreignKeySql = 'PRAGMA foreign_key_check(wp_options)';
$quickCheckSql = 'PRAGMA quick_check(wp_options)';
$page = static fn (
    int $offset = 0,
    int $limit = 141,
    ?array $cursor = null,
    ?string $nextBytes = null,
    ?array $nextSchemasValue = null,
    ?SQLiteAttachedSchemaCatalog $currentCatalog = null,
    ?SQLiteAttachedSchemaCatalog $nextCatalog = null,
    ?string $indexSql = null,
    ?string $quickSql = null,
): array => SQLitePragmaIndexQuickCheckForeignKeyCurrentSourceNext::currentNextPage(
    $currentCatalog ?? $catalogFactory(),
    $nextCatalog ?? $catalogFactory(),
    $indexSql ?? $indexListSql,
    $currentDatabase,
    $schemas(),
    $nextBytes ?? $nextDatabase,
    $nextSchemasValue ?? $cleanSchemas,
    $foreignKeySql,
    $quickSql ?? $quickCheckSql,
    $offset,
    $limit,
    $cursor,
);

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

$cases = [
    'status ok after next repair' => [static fn (): array => $page(), 'status', 'ok'],
    'default limit' => [static fn (): array => $page(), 'limit', 141],
    'total current plus next rows' => [static fn (): array => $page(), 'total', 25],
    'complete true' => [static fn (): array => $page(), 'complete', true],
    'source id length' => [static fn (): array => ['len' => strlen($page()['source_id'])], 'len', 64],
    'current database hash length' => [static fn (): array => ['len' => strlen($page()['current_source']['database'])], 'len', 64],
    'next database hash length' => [static fn (): array => ['len' => strlen($page()['next_source']['database'])], 'len', 64],
    'current catalog hash length' => [static fn (): array => ['len' => strlen($page()['current_source']['catalog'])], 'len', 64],
    'next schema hash length' => [static fn (): array => ['len' => strlen($page()['next_source']['schemas'])], 'len', 64],
    'normalized index sql' => [static fn (): array => $page(), 'current_source.index_list_sql', 'pragma main.index_list(wp_options)'],
    'normalized fk sql' => [static fn (): array => $page(), 'current_source.foreign_key_sql', 'pragma foreign_key_check(wp_options)'],
    'normalized quick sql' => [static fn (): array => $page(), 'current_source.quick_check_sql', 'pragma quick_check(wp_options)'],
    'index list count current' => [static fn (): array => $page(), 'current.index_list', 2],
    'index xinfo count current' => [static fn (): array => $page(), 'current.index_xinfo', 4],
    'index root count current' => [static fn (): array => $page(), 'current.index_root', 4],
    'index root errors current' => [static fn (): array => $page(), 'current.index_root_errors', 3],
    'quick root blockers current' => [static fn (): array => $page(), 'current.integrity_root', 2],
    'fk blockers current' => [static fn (): array => $page(), 'current.foreign_key', 3],
    'total blockers current' => [static fn (): array => $page(), 'current.total_blockers', 8],
    'current target table' => [static fn (): array => $page(), 'current.target_table', 'wp_options'],
    'current indexes' => [static fn (): array => $page(), 'current.indexes', ['wp_options_name', 'wp_options_autoload']],
    'next index roots ok' => [static fn (): array => $page(), 'next_counts.index_root_errors', 0],
    'next quick roots clear' => [static fn (): array => $page(), 'next_counts.integrity_root', 0],
    'next fk clear' => [static fn (): array => $page(), 'next_counts.foreign_key', 0],
    'next blockers clear' => [static fn (): array => $page(), 'next_counts.total_blockers', 0],
    'delta index root errors' => [static fn (): array => $page(), 'delta.index_root_errors', -3],
    'delta quick root' => [static fn (): array => $page(), 'delta.integrity_root', -2],
    'delta foreign key' => [static fn (): array => $page(), 'delta.foreign_key', -3],
    'delta total blockers' => [static fn (): array => $page(), 'delta.total_blockers', -8],
    'delta cleared' => [static fn (): array => $page(), 'delta.cleared', true],
    'next state ready' => [static fn (): array => $page(), 'next_state.ready', true],
    'next state blocking empty' => [static fn (): array => $page(), 'next_state.blocking', []],
    'row0 side current' => [static fn (): array => $page(), 'rows.0.side', 'current'],
    'row0 kind index list' => [static fn (): array => $page(), 'rows.0.kind', 'index_list'],
    'row1 kind xinfo' => [static fn (): array => $page(), 'rows.1.kind', 'index_xinfo'],
    'row1 collation nocase' => [static fn (): array => $page(), 'rows.1.coll', 'NOCASE'],
    'row3 first index root' => [static fn (): array => $page(), 'rows.3.kind', 'rootpage'],
    'row3 table root pointer conflict' => [static fn (): array => $page(), 'rows.3.page_status', 'pointer_map'],
    'row4 first index root ok' => [static fn (): array => $page(), 'rows.4.page_status', 'ok'],
    'row8 repeated table root conflict' => [static fn (): array => $page(), 'rows.8.page_status', 'pointer_map'],
    'row9 second index pointer conflict' => [static fn (): array => $page(), 'rows.9.page_status', 'pointer_map'],
    'row9 pointer parent' => [static fn (): array => $page(), 'rows.9.pointer_map_parent', 4],
    'row10 quick root source' => [static fn (): array => $page(), 'rows.10.kind', 'integrity_root'],
    'row12 fk source' => [static fn (): array => $page(), 'rows.12.kind', 'foreign_key_check'],
    'row12 fk rowid' => [static fn (): array => $page(), 'rows.12.rowid', 'missing-1'],
    'row12 fkid' => [static fn (): array => $page(), 'rows.12.fkid', 141],
    'row15 next starts' => [static fn (): array => $page(), 'rows.15.side', 'next'],
    'page first count' => [static fn (): array => $page(0, 6), 'count', 6],
    'page first next offset' => [static fn (): array => $page(0, 6), 'next.offset', 6],
    'page second offset' => [static fn (): array => $page(6, 6, $page(0, 6)['next']), 'offset', 6],
    'page second first row xinfo' => [static fn (): array => $page(6, 6, $page(0, 6)['next']), 'rows.0.kind', 'index_xinfo'],
    'page past tail count zero' => [static fn (): array => $page(40, 5), 'count', 0],
];

$tests = [];
foreach ($cases as $name => [$factory, $path, $expected]) {
    $tests['pragma index quickcheck foreignkey current source next141 ' . $name] = static function (TestRunner $t) use ($factory, $path, $expected, $valueAt): void {
        $t->same($expected, $valueAt($factory(), $path));
    };
}

$tests['pragma index quickcheck foreignkey current source next141 blocked next source reports all blockers'] = static function (TestRunner $t) use ($page, $currentDatabase, $schemas): void {
    $result = $page(0, 141, null, $currentDatabase, $schemas(2));

    $t->same('blocked', $result['status']);
    $t->same(false, $result['next_state']['ready']);
    $t->same(['index_rootpage', 'quick_check_root', 'foreign_key_check'], $result['next_state']['blocking']);
    $t->same(3, $result['next_counts']['index_root_errors']);
    $t->same(2, $result['next_counts']['integrity_root']);
    $t->same(2, $result['next_counts']['foreign_key']);
};

$tests['pragma index quickcheck foreignkey current source next141 source changes with next database'] = static function (TestRunner $t) use ($page, $mutatedNextDatabase): void {
    $first = $page();
    $second = $page(0, 141, null, $mutatedNextDatabase);

    $t->same(true, $first['source_id'] !== $second['source_id']);
    $t->same($first['current_source']['database'], $second['current_source']['database']);
    $t->same(true, $first['next_source']['database'] !== $second['next_source']['database']);
};

$tests['pragma index quickcheck foreignkey current source next141 source changes with next schema'] = static function (TestRunner $t) use ($page, $cleanSchemas, $schemas): void {
    $first = $page();
    $second = $page(0, 141, null, null, $schemas(1));

    $t->same(true, $first['source_id'] !== $second['source_id']);
    $t->same(true, $first['next_source']['schemas'] !== $second['next_source']['schemas']);
    $t->same(0, $first['next_counts']['foreign_key']);
    $t->same(1, $second['next_counts']['foreign_key']);
    $t->same($cleanSchemas['main']['tables']['wp_option_names'], $schemas(1)['main']['tables']['wp_option_names']);
};

$tests['pragma index quickcheck foreignkey current source next141 source changes with catalog'] = static function (TestRunner $t) use ($page, $catalogFactory): void {
    $first = $page();
    $second = $page(0, 141, null, null, null, null, $catalogFactory(true));

    $t->same(true, $first['source_id'] !== $second['source_id']);
    $t->same(true, $first['next_source']['catalog'] !== $second['next_source']['catalog']);
};

$tests['pragma index quickcheck foreignkey current source next141 source changes with index sql'] = static function (TestRunner $t) use ($page): void {
    $first = $page();
    $second = $page(0, 141, null, null, null, null, null, 'PRAGMA main.index_list("wp_options")');

    $t->same(true, $first['source_id'] !== $second['source_id']);
    $t->same('pragma main.index_list("wp_options")', $second['current_source']['index_list_sql']);
};

$tests['pragma index quickcheck foreignkey current source next141 rejects stale next database cursor'] = static function (TestRunner $t) use ($page, $mutatedNextDatabase): void {
    $first = $page(0, 6);
    $t->throws(InvalidArgumentException::class, static fn () => $page(6, 6, $first['next'], $mutatedNextDatabase));
};

$tests['pragma index quickcheck foreignkey current source next141 rejects stale offset cursor'] = static function (TestRunner $t) use ($page): void {
    $first = $page(0, 6);
    $t->throws(InvalidArgumentException::class, static fn () => $page(7, 6, $first['next']));
};

$tests['pragma index quickcheck foreignkey current source next141 rejects non index list pragma'] = static function (TestRunner $t) use ($page): void {
    $t->throws(InvalidArgumentException::class, static fn () => $page(0, 141, null, null, null, null, null, 'PRAGMA main.index_xinfo(wp_options_name)'));
};

$tests['pragma index quickcheck foreignkey current source next141 rejects non quickcheck pragma'] = static function (TestRunner $t) use ($page): void {
    $t->throws(InvalidArgumentException::class, static fn () => $page(0, 141, null, null, null, null, null, null, 'PRAGMA table_info(wp_options)'));
};

return $tests;
