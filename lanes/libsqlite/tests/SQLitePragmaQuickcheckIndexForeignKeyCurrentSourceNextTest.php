<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteAttachedSchemaCatalog;
use PortLibs\LibSqlite\SQLiteIndexLeafPage;
use PortLibs\LibSqlite\SQLitePointerMapEntry;
use PortLibs\LibSqlite\SQLitePragmaQuickcheckIndexForeignKeyCurrentSourceNext;
use PortLibs\LibSqlite\SQLiteRecord;
use PortLibs\LibSqlite\SQLiteSchemaRecord;
use PortLibs\LibSqlite\SQLiteTableLeafCell;
use PortLibs\LibSqlite\SQLiteTableLeafPage;

$pageSize138 = 1024;
$record138 = static fn (string $type, string $name, string $table, ?int $root, ?string $sql = null, int $rowid = 1): SQLiteSchemaRecord => new SQLiteSchemaRecord($type, $name, $table, $root, $sql, $rowid);

$catalog138 = static function (bool $archiveShadow = false) use ($record138): SQLiteAttachedSchemaCatalog {
    $catalog = new SQLiteAttachedSchemaCatalog([
        $record138('table', 'wp_options', 'wp_options', 4, 'CREATE TABLE wp_options(option_id INTEGER PRIMARY KEY, option_name TEXT, option_value TEXT, autoload TEXT)', 1),
        $record138('table', 'wp_option_names', 'wp_option_names', 5, 'CREATE TABLE wp_option_names(name TEXT PRIMARY KEY)', 2),
        $record138('index', 'wp_options_name', 'wp_options', 6, 'CREATE UNIQUE INDEX wp_options_name ON wp_options(option_name COLLATE NOCASE DESC, autoload)', 3),
        $record138('index', 'wp_options_value_expr', 'wp_options', 7, "CREATE INDEX wp_options_value_expr ON wp_options(json_extract(option_value, '$.plugin'), lower(option_name) COLLATE nocase, autoload DESC)", 4),
    ]);
    $catalog->attach('archive', '/srv/wp/archive.sqlite', [
        $record138('table', 'wp_options', 'wp_options', 8, 'CREATE TABLE wp_options(option_id INTEGER PRIMARY KEY, option_name TEXT, autoload TEXT)', 1),
        $record138('table', 'wp_option_names', 'wp_option_names', 9, 'CREATE TABLE wp_option_names(name TEXT PRIMARY KEY)', 2),
        $record138('index', $archiveShadow ? 'wp_options_value_expr' : 'wp_options_archive_name', 'wp_options', 10, 'CREATE INDEX ' . ($archiveShadow ? 'wp_options_value_expr' : 'wp_options_archive_name') . ' ON wp_options(option_name COLLATE RTRIM, autoload DESC)', 3),
    ]);

    return $catalog;
};

$header138 = static function (int $pageCount, int $largestRootPage) use ($pageSize138): string {
    $page = str_repeat("\0", $pageSize138);
    $page = substr_replace($page, "SQLite format 3\0", 0, 16);
    $page = substr_replace($page, pack('n', $pageSize138), 16, 2);
    $page[18] = "\x01";
    $page[19] = "\x01";
    $page = substr_replace($page, pack('N', $pageCount), 28, 4);
    $page = substr_replace($page, pack('N', $largestRootPage), 52, 4);
    $page = substr_replace($page, pack('N', 1), 56, 4);

    return $page;
};
$putPointer138 = static fn (string $page, int $pageNumber, int $type, int $parent): string => substr_replace($page, chr($type) . pack('N', $parent), 5 * ($pageNumber - 3), 5);
$schemaCell138 = static fn (array $values, int $rowId): string => SQLiteTableLeafCell::encode($rowId, SQLiteRecord::encode($values));
$database138 = static function (array $pointerMapEntries, int $largestRootPage = 10) use ($header138, $putPointer138, $schemaCell138, $pageSize138): string {
    $schemaRows = [
        ['table', 'wp_options', 'wp_options', 4, 'CREATE TABLE wp_options(option_id integer primary key, option_name text, option_value text, autoload text)'],
        ['table', 'wp_option_names', 'wp_option_names', 5, 'CREATE TABLE wp_option_names(name text primary key)'],
        ['index', 'wp_options_name', 'wp_options', 6, 'CREATE UNIQUE INDEX wp_options_name ON wp_options(option_name, autoload)'],
        ['index', 'wp_options_value_expr', 'wp_options', 7, "CREATE INDEX wp_options_value_expr ON wp_options(json_extract(option_value, '$.plugin'), lower(option_name), autoload DESC)"],
        ['table', 'wp_archive_shadow', 'wp_archive_shadow', 8, 'CREATE TABLE wp_archive_shadow(id integer primary key)'],
        ['table', 'wp_option_names_archive', 'wp_option_names_archive', 9, 'CREATE TABLE wp_option_names_archive(name text primary key)'],
        ['index', 'wp_archive_index', 'wp_archive_shadow', 10, 'CREATE INDEX wp_archive_index ON wp_archive_shadow(id)'],
    ];
    $pointerMap = str_repeat("\0", $pageSize138);
    foreach ($pointerMapEntries as $entry) {
        $pointerMap = $putPointer138($pointerMap, $entry[0], $entry[1], $entry[2]);
    }
    $pages = [
        1 => SQLiteTableLeafPage::assemble(
            array_map(static fn (array $row, int $index): string => $schemaCell138($row, $index + 1), $schemaRows, array_keys($schemaRows)),
            $pageSize138,
            100,
            $header138(10, $largestRootPage),
        ),
        2 => $pointerMap,
    ];
    for ($pageNumber = 3; $pageNumber <= 10; $pageNumber++) {
        $pages[$pageNumber] = in_array($pageNumber, [6, 7, 10], true)
            ? SQLiteIndexLeafPage::assemble([], $pageSize138)
            : SQLiteTableLeafPage::assemble([], $pageSize138);
    }
    ksort($pages);

    return implode('', $pages);
};

$cleanDatabase138 = $database138([
    [3, SQLitePointerMapEntry::BTREE_PAGE, 4],
    [4, SQLitePointerMapEntry::ROOT_PAGE, 0],
    [5, SQLitePointerMapEntry::ROOT_PAGE, 0],
    [6, SQLitePointerMapEntry::ROOT_PAGE, 0],
    [7, SQLitePointerMapEntry::ROOT_PAGE, 0],
    [8, SQLitePointerMapEntry::ROOT_PAGE, 0],
    [9, SQLitePointerMapEntry::ROOT_PAGE, 0],
    [10, SQLitePointerMapEntry::ROOT_PAGE, 0],
]);
$dirtyDatabase138 = $database138([
    [3, SQLitePointerMapEntry::BTREE_PAGE, 4],
    [4, SQLitePointerMapEntry::ROOT_PAGE, 0],
    [5, SQLitePointerMapEntry::ROOT_PAGE, 0],
    [6, SQLitePointerMapEntry::ROOT_PAGE, 0],
    [7, SQLitePointerMapEntry::BTREE_PAGE, 4],
    [8, SQLitePointerMapEntry::ROOT_PAGE, 0],
    [9, SQLitePointerMapEntry::ROOT_PAGE, 0],
    [10, SQLitePointerMapEntry::ROOT_PAGE, 0],
]);
$limitedDatabase138 = $database138([
    [3, SQLitePointerMapEntry::BTREE_PAGE, 4],
    [4, SQLitePointerMapEntry::ROOT_PAGE, 0],
    [5, SQLitePointerMapEntry::ROOT_PAGE, 0],
    [6, SQLitePointerMapEntry::ROOT_PAGE, 0],
    [7, SQLitePointerMapEntry::BTREE_PAGE, 4],
    [8, SQLitePointerMapEntry::ROOT_PAGE, 0],
    [9, SQLitePointerMapEntry::ROOT_PAGE, 0],
    [10, SQLitePointerMapEntry::ROOT_PAGE, 0],
], 6);
$mutatedDatabase138 = $cleanDatabase138;
$mutatedDatabase138[48] = "\x02";

$schemas138 = static function (int $optionMisses = 2, bool $archiveMiss = true): array {
    $options = [['rowid' => 1, 'option_id' => 1, 'option_name' => 'siteurl', 'option_value' => 'https://example.test', 'autoload' => 'yes']];
    for ($i = 1; $i <= $optionMisses; $i++) {
        $options[] = ['rowid' => 'option-' . $i, 'option_id' => $i + 1, 'option_name' => 'missing_' . $i, 'option_value' => '{}', 'autoload' => 'no'];
    }

    return [
        'main' => [
            'tables' => [
                'wp_option_names' => [['rowid' => 1, 'name' => 'siteurl']],
                'wp_options' => $options,
            ],
            'foreignKeys' => [
                ['id' => 1, 'table' => 'wp_options', 'parent' => 'wp_option_names', 'columns' => [['child' => 'option_name', 'parent' => 'name', 'affinity' => 'text', 'collation' => 'nocase']]],
            ],
        ],
        'archive' => [
            'tables' => [
                'wp_option_names' => [['rowid' => 1, 'name' => 'legacy']],
                'wp_options' => [
                    ['rowid' => 31, 'option_id' => 31, 'option_name' => 'legacy', 'autoload' => 'yes'],
                    ['rowid' => 32, 'option_id' => 32, 'option_name' => $archiveMiss ? 'orphan' : 'legacy', 'autoload' => 'no'],
                ],
            ],
            'foreignKeys' => [
                ['id' => 2, 'table' => 'wp_options', 'parent' => 'wp_option_names', 'columns' => [['child' => 'option_name', 'parent' => 'name', 'affinity' => 'text', 'collation' => 'binary']]],
            ],
        ],
    ];
};

$valueAt138 = static function (mixed $value, string $path): mixed {
    foreach (explode('.', $path) as $part) {
        if (is_array($value) && array_key_exists($part, $value)) {
            $value = $value[$part];
            continue;
        }
        $value = is_numeric($part) ? $value[(int) $part] : $value[$part];
    }

    return $value;
};

$page138 = static fn (
    ?string $indexSql = null,
    ?string $db = null,
    ?array $schemas = null,
    int $offset = 0,
    int $limit = 138,
    string $fkSql = 'PRAGMA foreign_key_check',
    string $quickSql = 'PRAGMA quick_check',
    bool $tableValuedIndexList = false,
    ?array $cursor = null,
    ?SQLiteAttachedSchemaCatalog $catalog = null,
): array => SQLitePragmaQuickcheckIndexForeignKeyCurrentSourceNext::page(
    $catalog ?? $catalog138(),
    $indexSql ?? 'PRAGMA main.index_list(wp_options)',
    $db ?? $dirtyDatabase138,
    $schemas ?? $schemas138(),
    $fkSql,
    $offset,
    $limit,
    $quickSql,
    $tableValuedIndexList,
    $cursor,
);

$default138 = static fn (): array => $page138();
$clean138 = static fn (): array => $page138(null, $cleanDatabase138);
$limited138 = static fn (): array => $page138(null, $limitedDatabase138, null, 0, 138, 'PRAGMA foreign_key_check', 'PRAGMA quick_check(1)');
$archive138 = static fn (): array => $page138("pragma_index_list('wp_options','archive')", $cleanDatabase138, $schemas138(0), 0, 138, "SELECT * FROM pragma_foreign_key_check('archive.wp_options')", 'PRAGMA quick_check', true);
$archiveClean138 = static fn (): array => $page138("pragma_index_list('wp_options','archive')", $cleanDatabase138, $schemas138(0, false), 0, 138, "SELECT * FROM pragma_foreign_key_check('archive.wp_options')", 'PRAGMA quick_check', true);

$cases138 = [
    'status blocked' => [$default138, 'status', 'blocked'],
    'limit next138' => [$default138, 'limit', 138],
    'total rows' => [$default138, 'total', 15],
    'count rows' => [$default138, 'count', 15],
    'complete true' => [$default138, 'complete', true],
    'next null' => [$default138, 'next', null],
    'source id length' => [static fn (): array => ['length' => strlen($default138()['source_id'])], 'length', 64],
    'mode' => [$default138, 'current_source.mode', 'quickcheck_index_foreignkey_current_source_next138'],
    'index source length' => [static fn (): array => ['length' => strlen($default138()['current_source']['index_source_id'])], 'length', 64],
    'database source length' => [static fn (): array => ['length' => strlen($default138()['current_source']['database'])], 'length', 64],
    'catalog source length' => [static fn (): array => ['length' => strlen($default138()['current_source']['catalog'])], 'length', 64],
    'schemas source length' => [static fn (): array => ['length' => strlen($default138()['current_source']['schemas'])], 'length', 64],
    'index sql normalized' => [$default138, 'current_source.index_list_sql', 'pragma main.index_list(wp_options)'],
    'quick sql normalized' => [$default138, 'current_source.quick_check_sql', 'pragma quick_check'],
    'fk sql normalized' => [$default138, 'current_source.foreign_key_sql', 'pragma foreign_key_check'],
    'index table valued false' => [$default138, 'current_source.table_valued_index_list', false],
    'fk table valued false' => [$default138, 'current_source.table_valued_foreign_key', false],
    'next state ready false' => [$default138, 'next_state.ready', false],
    'blocking quick first' => [$default138, 'next_state.blocking.0', 'quick_check'],
    'blocking fk second' => [$default138, 'next_state.blocking.1', 'foreign_key_check'],
    'current index list count' => [$default138, 'current.index_list', 2],
    'current index xinfo count' => [$default138, 'current.index_xinfo', 7],
    'current quick rootpage count' => [$default138, 'current.quick_check_rootpages', 4],
    'current quick errors count' => [$default138, 'current.quick_check_errors', 1],
    'current fk count' => [$default138, 'current.foreign_key_violations', 2],
    'target schema main' => [$default138, 'current.target_schema', 'main'],
    'target table options' => [$default138, 'current.target_table', 'wp_options'],
    'indexes list' => [$default138, 'current.indexes', ['wp_options_name', 'wp_options_value_expr']],
    'fk schema main first' => [$default138, 'current.foreign_key_schemas.0', 'main'],
    'phase index list count' => [$default138, 'current.row_phases.index_list', 2],
    'phase index xinfo count' => [$default138, 'current.row_phases.index_xinfo', 7],
    'phase quick rootpage count' => [$default138, 'current.row_phases.quick_check_rootpage', 4],
    'phase fk count' => [$default138, 'current.row_phases.foreign_key_check', 2],
    'row0 index list phase' => [$default138, 'rows.0.phase', 'index_list'],
    'row1 xinfo phase' => [$default138, 'rows.1.phase', 'index_xinfo'],
    'row1 collation' => [$default138, 'rows.1.coll', 'NOCASE'],
    'row3 xinfo rowid phase' => [$default138, 'rows.3.phase', 'index_xinfo'],
    'row4 quick root ok' => [$default138, 'rows.4.phase', 'quick_check_rootpage'],
    'row7 expression index list' => [$default138, 'rows.7.index', 'wp_options_value_expr'],
    'row8 expression cid' => [$default138, 'rows.8.cid', -2],
    'row9 expression collation' => [$default138, 'rows.9.coll', 'BINARY'],
    'row12 pointer quick message' => [$default138, 'rows.12.message', 'sqlite_schema index wp_options_value_expr rootpage 7 pointer-map btree-page parent 4 does not match expected root-page parent 0'],
    'row12 pointer status' => [$default138, 'rows.12.page_status', 'pointer_map'],
    'row13 fk phase' => [$default138, 'rows.13.phase', 'foreign_key_check'],
    'row13 fk rowid' => [$default138, 'rows.13.rowid', 'option-1'],
    'row13 fk message' => [$default138, 'rows.13.message', 'foreign key mismatch in main.wp_options rowid option-1 references wp_option_names fkid 1'],
    'clean quick errors zero' => [$clean138, 'current.quick_check_errors', 0],
    'clean first blocker fk' => [$clean138, 'next_state.blocking.0', 'foreign_key_check'],
    'clean status still blocked' => [$clean138, 'status', 'blocked'],
    'limited quick errors three' => [$limited138, 'current.quick_check_errors', 3],
    'limited root row message' => [$limited138, 'rows.12.message', 'sqlite_schema table wp_options rootpage 4 ok'],
    'archive table valued index true' => [$archive138, 'current_source.table_valued_index_list', true],
    'archive table valued fk true' => [$archive138, 'current_source.table_valued_foreign_key', true],
    'archive schema target' => [$archive138, 'current.target_schema', 'archive'],
    'archive target table' => [$archive138, 'current.target_table', 'wp_options'],
    'archive index row' => [$archive138, 'rows.0.index', 'wp_options_archive_name'],
    'archive collation' => [$archive138, 'rows.1.coll', 'RTRIM'],
    'archive fk count' => [$archive138, 'current.foreign_key_violations', 1],
    'archive clean ready' => [$archiveClean138, 'next_state.ready', true],
    'archive clean status ok' => [$archiveClean138, 'status', 'ok'],
    'limit five next offset' => [static fn (): array => $page138(null, null, null, 0, 5), 'next.offset', 5],
    'offset five current row rootpage' => [static fn (): array => $page138(null, null, null, 5, 5), 'current_row.kind', 'rootpage'],
    'past tail count zero' => [static fn (): array => $page138(null, null, null, 40, 5), 'count', 0],
];

$tests = [];
foreach ($cases138 as $name => [$callback, $path, $expected]) {
    $tests['pragma quickcheck index foreignkey current source next138 ' . $name] = static function (TestRunner $t) use ($callback, $valueAt138, $path, $expected): void {
        $t->same($expected, $valueAt138($callback(), $path));
    };
}

$tests['pragma quickcheck index foreignkey current source next138 paginates with source cursor'] = static function (TestRunner $t) use ($page138): void {
    $first = $page138(null, null, null, 0, 5);
    $second = $page138(null, null, null, 5, 5, 'PRAGMA foreign_key_check', 'PRAGMA quick_check', false, $first['next']);
    $third = $page138(null, null, null, 10, 5, 'PRAGMA foreign_key_check', 'PRAGMA quick_check', false, $second['next']);

    $t->same(['source_id' => $first['source_id'], 'offset' => 5], $first['next']);
    $t->same('rootpage', $second['rows'][0]['kind']);
    $t->same('index_xinfo', $second['rows'][2]['kind']);
    $t->same(['source_id' => $first['source_id'], 'offset' => 10], $second['next']);
    $t->same('index_xinfo', $third['rows'][0]['phase']);
    $t->same('foreign_key_check', $third['rows'][3]['phase']);
    $t->same(null, $third['next']);
};

$tests['pragma quickcheck index foreignkey current source next138 accepts source-only cursor'] = static function (TestRunner $t) use ($page138): void {
    $first = $page138(null, null, null, 0, 5);
    $second = $page138(null, null, null, 5, 5, 'PRAGMA foreign_key_check', 'PRAGMA quick_check', false, ['source_id' => $first['source_id']]);

    $t->same(5, $second['offset']);
    $t->same('rootpage', $second['rows'][0]['kind']);
};

$tests['pragma quickcheck index foreignkey current source next138 changes source with schemas'] = static function (TestRunner $t) use ($page138, $schemas138): void {
    $two = $page138(null, null, $schemas138(2));
    $three = $page138(null, null, $schemas138(3));

    $t->same(true, $two['source_id'] !== $three['source_id']);
    $t->same(2, $two['current']['foreign_key_violations']);
    $t->same(3, $three['current']['foreign_key_violations']);
};

$tests['pragma quickcheck index foreignkey current source next138 rejects stale database cursor'] = static function (TestRunner $t) use ($page138, $mutatedDatabase138): void {
    $first = $page138(null, null, null, 0, 5);
    $t->throws(InvalidArgumentException::class, static fn (): mixed => $page138(null, $mutatedDatabase138, null, 5, 5, 'PRAGMA foreign_key_check', 'PRAGMA quick_check', false, $first['next']));
};

$tests['pragma quickcheck index foreignkey current source next138 rejects stale catalog cursor'] = static function (TestRunner $t) use ($page138, $catalog138): void {
    $first = $page138(null, null, null, 0, 5);
    $t->throws(InvalidArgumentException::class, static fn (): mixed => $page138(null, null, null, 5, 5, 'PRAGMA foreign_key_check', 'PRAGMA quick_check', false, $first['next'], $catalog138(true)));
};

$tests['pragma quickcheck index foreignkey current source next138 rejects stale sql cursor'] = static function (TestRunner $t) use ($page138): void {
    $first = $page138(null, null, null, 0, 5);
    $t->throws(InvalidArgumentException::class, static fn (): mixed => $page138('PRAGMA temp.index_list(wp_options)', null, null, 5, 5, 'PRAGMA foreign_key_check', 'PRAGMA quick_check', false, $first['next']));
};

$tests['pragma quickcheck index foreignkey current source next138 rejects stale fk cursor'] = static function (TestRunner $t) use ($page138): void {
    $first = $page138(null, null, null, 0, 5);
    $t->throws(InvalidArgumentException::class, static fn (): mixed => $page138(null, null, null, 5, 5, "PRAGMA foreign_key_check('wp_options')", 'PRAGMA quick_check', false, $first['next']));
};

$tests['pragma quickcheck index foreignkey current source next138 rejects stale quick cursor'] = static function (TestRunner $t) use ($page138): void {
    $first = $page138(null, null, null, 0, 5);
    $t->throws(InvalidArgumentException::class, static fn (): mixed => $page138(null, null, null, 5, 5, 'PRAGMA foreign_key_check', 'PRAGMA quick_check(1)', false, $first['next']));
};

$tests['pragma quickcheck index foreignkey current source next138 rejects stale offset cursor'] = static function (TestRunner $t) use ($page138): void {
    $first = $page138(null, null, null, 0, 5);
    $t->throws(InvalidArgumentException::class, static fn (): mixed => $page138(null, null, null, 6, 5, 'PRAGMA foreign_key_check', 'PRAGMA quick_check', false, $first['next']));
};

$tests['pragma quickcheck index foreignkey current source next138 rejects integrity check'] = static function (TestRunner $t) use ($page138): void {
    $t->throws(InvalidArgumentException::class, static fn (): mixed => $page138(null, null, null, 0, 5, 'PRAGMA foreign_key_check', 'PRAGMA integrity_check'));
};

$tests['pragma quickcheck index foreignkey current source next138 rejects negative offset'] = static function (TestRunner $t) use ($page138): void {
    $t->throws(InvalidArgumentException::class, static fn (): mixed => $page138(null, null, null, -1, 5));
};

$tests['pragma quickcheck index foreignkey current source next138 rejects zero limit'] = static function (TestRunner $t) use ($page138): void {
    $t->throws(InvalidArgumentException::class, static fn (): mixed => $page138(null, null, null, 0, 0));
};

return $tests;
