<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteAttachedSchemaCatalog;
use PortLibs\LibSqlite\SQLiteIndexLeafPage;
use PortLibs\LibSqlite\SQLitePointerMapEntry;
use PortLibs\LibSqlite\SQLitePragmaIndexForeignKeyIntegrityCurrentSourceNext;
use PortLibs\LibSqlite\SQLiteRecord;
use PortLibs\LibSqlite\SQLiteSchemaRecord;
use PortLibs\LibSqlite\SQLiteTableLeafCell;
use PortLibs\LibSqlite\SQLiteTableLeafPage;

$record = static fn (string $type, string $name, string $table, ?int $root, ?string $sql = null, int $rowid = 1): SQLiteSchemaRecord => new SQLiteSchemaRecord($type, $name, $table, $root, $sql, $rowid);

$records = [
    $record('table', 'wp_sites', 'wp_sites', 2, 'CREATE TABLE wp_sites(blog_id INTEGER PRIMARY KEY, domain TEXT)', 1),
    $record('table', 'wp_option_names', 'wp_option_names', 3, 'CREATE TABLE wp_option_names(name TEXT COLLATE NOCASE, source TEXT)', 2),
    $record('index', 'wp_option_names_name_u', 'wp_option_names', 4, 'CREATE UNIQUE INDEX wp_option_names_name_u ON wp_option_names(name COLLATE nocase)', 3),
    $record('table', 'wp_broken_parent', 'wp_broken_parent', 7, 'CREATE TABLE wp_broken_parent(code TEXT COLLATE NOCASE)', 4),
    $record('index', 'wp_broken_parent_code', 'wp_broken_parent', 8, 'CREATE INDEX wp_broken_parent_code ON wp_broken_parent(code COLLATE nocase)', 5),
    $record('table', 'wp_options', 'wp_options', 13, 'CREATE TABLE wp_options(option_id INTEGER PRIMARY KEY, blog_id INTEGER, option_name TEXT, broken_code TEXT)', 6),
    $record('index', 'wp_options_name', 'wp_options', 5, 'CREATE UNIQUE INDEX wp_options_name ON wp_options(option_name COLLATE NOCASE DESC, autoload)', 7),
    $record('index', 'wp_options_value_expr', 'wp_options', 6, "CREATE INDEX wp_options_value_expr ON wp_options(json_extract(option_value, '$.plugin'), lower(option_name) COLLATE nocase, autoload DESC)", 8),
];

$catalog = new SQLiteAttachedSchemaCatalog([
    $record('table', 'wp_options', 'wp_options', 4, 'CREATE TABLE wp_options(option_id INTEGER PRIMARY KEY, option_name TEXT, option_value TEXT, autoload TEXT)', 1),
    $record('index', 'wp_options_name', 'wp_options', 5, 'CREATE UNIQUE INDEX wp_options_name ON wp_options(option_name COLLATE NOCASE DESC, autoload)', 2),
    $record('index', 'wp_options_value_expr', 'wp_options', 6, "CREATE INDEX wp_options_value_expr ON wp_options(json_extract(option_value, '$.plugin'), lower(option_name) COLLATE nocase, autoload DESC)", 3),
]);

$catalogFactory = static fn (): SQLiteAttachedSchemaCatalog => new SQLiteAttachedSchemaCatalog([
    $record('table', 'wp_options', 'wp_options', 4, 'CREATE TABLE wp_options(option_id INTEGER PRIMARY KEY, option_name TEXT, option_value TEXT, autoload TEXT)', 1),
    $record('index', 'wp_options_name', 'wp_options', 5, 'CREATE UNIQUE INDEX wp_options_name ON wp_options(option_name COLLATE NOCASE DESC, autoload)', 2),
    $record('index', 'wp_options_value_expr', 'wp_options', 6, "CREATE INDEX wp_options_value_expr ON wp_options(json_extract(option_value, '$.plugin'), lower(option_name) COLLATE nocase, autoload DESC)", 3),
]);

$foreignKeys = [
    ['id' => 0, 'table' => 'wp_options', 'parent' => 'wp_sites', 'columns' => ['blog_id' => 'blog_id']],
    ['id' => 1, 'table' => 'wp_options', 'parent' => 'wp_option_names', 'columns' => [['child' => 'option_name', 'parent' => 'name', 'affinity' => 'text', 'collation' => 'nocase']]],
    ['id' => 2, 'table' => 'wp_options', 'parent' => 'wp_broken_parent', 'columns' => ['broken_code' => 'code']],
];

$tables = [
    'wp_sites' => [
        ['rowid' => 1, 'blog_id' => 1, 'domain' => 'example.test'],
    ],
    'wp_option_names' => [
        ['rowid' => 1, 'name' => 'siteurl', 'source' => 'core'],
    ],
    'wp_broken_parent' => [
        ['rowid' => 1, 'code' => 'legacy'],
    ],
    'wp_options' => [
        ['rowid' => 101, 'option_id' => 101, 'blog_id' => 1, 'option_name' => 'SITEURL', 'broken_code' => 'legacy'],
        ['rowid' => 102, 'option_id' => 102, 'blog_id' => 404, 'option_name' => 'missing-name', 'broken_code' => null],
        ['rowid' => 103, 'option_id' => 103, 'blog_id' => null, 'option_name' => null, 'broken_code' => 'missing-parent'],
    ],
];

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
$putPointerMapEntry = static fn (string $page, int $pageNumber, int $type, int $parent): string => substr_replace($page, chr($type) . pack('N', $parent), 5 * ($pageNumber - 3), 5);
$schemaCell = static fn (array $values, int $rowId): string => SQLiteTableLeafCell::encode($rowId, SQLiteRecord::encode($values));
$schemaDatabase = static function (array $pointerMapEntries) use ($headerPage, $putPointerMapEntry, $schemaCell, $pageSize): string {
    $schemaRows = [
        ['table', 'wp_options', 'wp_options', 4, 'CREATE TABLE wp_options(option_id integer primary key, option_name text, option_value text, autoload text)'],
        ['index', 'wp_options_name', 'wp_options', 5, 'CREATE UNIQUE INDEX wp_options_name ON wp_options(option_name, autoload)'],
        ['index', 'wp_options_value_expr', 'wp_options', 6, "CREATE INDEX wp_options_value_expr ON wp_options(json_extract(option_value, '$.plugin'), lower(option_name), autoload)"],
    ];
    $pages = [
        1 => SQLiteTableLeafPage::assemble(
            array_map(static fn (array $row, int $index): string => $schemaCell($row, $index + 1), $schemaRows, array_keys($schemaRows)),
            $pageSize,
            100,
            $headerPage(8, 6),
        ),
        2 => str_repeat("\0", $pageSize),
    ];
    foreach ($pointerMapEntries as $entry) {
        $pages[2] = $putPointerMapEntry($pages[2], $entry[0], $entry[1], $entry[2]);
    }
    for ($pageNumber = 3; $pageNumber <= 8; $pageNumber++) {
        $pages[$pageNumber] ??= in_array($pageNumber, [5, 6], true)
            ? SQLiteIndexLeafPage::assemble([], $pageSize)
            : SQLiteTableLeafPage::assemble([], $pageSize);
    }
    ksort($pages);

    return implode('', $pages);
};

$validDatabase = $schemaDatabase([
    [3, SQLitePointerMapEntry::BTREE_PAGE, 4],
    [4, SQLitePointerMapEntry::ROOT_PAGE, 0],
    [5, SQLitePointerMapEntry::ROOT_PAGE, 0],
    [6, SQLitePointerMapEntry::ROOT_PAGE, 0],
]);
$pointerMismatchDatabase = $schemaDatabase([
    [3, SQLitePointerMapEntry::BTREE_PAGE, 4],
    [4, SQLitePointerMapEntry::ROOT_PAGE, 0],
    [5, SQLitePointerMapEntry::ROOT_PAGE, 0],
    [6, SQLitePointerMapEntry::BTREE_PAGE, 4],
]);

$currentSource = '00d5ed7b9dc734b0d9546507a661722feb05840f';
$nextSource = 'pragma-index-foreignkey-integrity-current-source-next137';
$page = static fn (
    int $offset = 0,
    int $limit = 137,
    ?array $cursor = null,
    ?string $database = null,
    ?array $recordsValue = null,
    ?array $foreignKeysValue = null,
    ?array $tablesValue = null,
    ?SQLiteAttachedSchemaCatalog $catalogValue = null,
): array => SQLitePragmaIndexForeignKeyIntegrityCurrentSourceNext::page(
    $catalogValue ?? $catalog,
    'PRAGMA main.index_list(wp_options)',
    $database ?? $pointerMismatchDatabase,
    $recordsValue ?? $records,
    $foreignKeysValue ?? $foreignKeys,
    $tablesValue ?? $tables,
    $currentSource,
    $nextSource,
    $offset,
    $limit,
    'PRAGMA integrity_check',
    false,
    $cursor,
);

$cleanTables = [
    ...$tables,
    'wp_options' => [
        ['rowid' => 201, 'option_id' => 201, 'blog_id' => 1, 'option_name' => 'siteurl', 'broken_code' => null],
    ],
];
$cleanForeignKeys = array_slice($foreignKeys, 0, 2);
$cleanPage = static fn (): array => $page(0, 137, null, $validDatabase, null, $cleanForeignKeys, $cleanTables);

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
    'status blocked' => [$page, 'status', 'blocked'],
    'source id length' => [static fn (): array => ['len' => strlen($page()['source_id'])], 'len', 64],
    'current source retained' => [$page, 'current_source.current', $currentSource],
    'next source retained' => [$page, 'current_source.next', $nextSource],
    'catalog hash length' => [static fn (): array => ['len' => strlen($page()['current_source']['catalog'])], 'len', 64],
    'database hash length' => [static fn (): array => ['len' => strlen($page()['current_source']['database'])], 'len', 64],
    'records hash length' => [static fn (): array => ['len' => strlen($page()['current_source']['records_hash'])], 'len', 64],
    'foreign key hash length' => [static fn (): array => ['len' => strlen($page()['current_source']['foreign_key_hash'])], 'len', 64],
    'table hash length' => [static fn (): array => ['len' => strlen($page()['current_source']['table_hash'])], 'len', 64],
    'normalized index sql' => [$page, 'current_source.index_list_sql', 'pragma main.index_list(wp_options)'],
    'normalized integrity sql' => [$page, 'current_source.integrity_sql', 'pragma integrity_check'],
    'table valued false' => [$page, 'current_source.table_valued_index_list', false],
    'offset zero' => [$page, 'offset', 0],
    'limit default next137' => [$page, 'limit', 137],
    'total rows' => [$page, 'total', 19],
    'count rows' => [$page, 'count', 19],
    'complete true' => [$page, 'complete', true],
    'next null complete' => [$page, 'next', null],
    'index list count' => [$page, 'current.index_list', 2],
    'index xinfo count' => [$page, 'current.index_xinfo', 7],
    'rootpage count' => [$page, 'current.rootpage', 4],
    'rootpage errors' => [$page, 'current.rootpage_errors', 1],
    'index admissions' => [$page, 'current.index_admissions', 3],
    'index blockers' => [$page, 'current.index_blockers', 1],
    'foreign key violations' => [$page, 'current.foreign_key_violations', 3],
    'target schema' => [$page, 'current.target_schema', 'main'],
    'target table' => [$page, 'current.target_table', 'wp_options'],
    'index names' => [$page, 'current.indexes', ['wp_options_name', 'wp_options_value_expr']],
    'row0 group' => [$page, 'rows.0.group', 'pragma_index_integrity'],
    'row0 source' => [$page, 'rows.0.source', 'index_index_list'],
    'row0 kind' => [$page, 'rows.0.kind', 'index_list'],
    'row1 xinfo source' => [$page, 'rows.1.source', 'index_index_xinfo'],
    'row1 xinfo collate' => [$page, 'rows.1.coll', 'NOCASE'],
    'row4 root source' => [$page, 'rows.4.source', 'index_rootpage_integrity'],
    'row12 pointer map status' => [$page, 'rows.12.page_status', 'pointer_map'],
    'row12 pointer map parent' => [$page, 'rows.12.pointer_map_parent', 4],
    'row13 fk group' => [$page, 'rows.13.group', 'pragma_foreign_key_integrity'],
    'row13 fk parent index source' => [$page, 'rows.13.source', 'foreign_key_parent_index'],
    'row13 fk parent rowid index' => [$page, 'rows.13.index', 'rowid-primary-key'],
    'row14 fk unique index' => [$page, 'rows.14.index', 'wp_option_names_name_u'],
    'row14 fk collation' => [$page, 'rows.14.collations.0', 'NOCASE'],
    'row15 fk blocker parent' => [$page, 'rows.15.parent', 'wp_broken_parent'],
    'row15 fk blocker status' => [$page, 'rows.15.status', 'blocked'],
    'row16 fk violation source' => [$page, 'rows.16.source', 'foreign_key_check'],
    'row16 fk violation rowid' => [$page, 'rows.16.rowid', 102],
    'row17 fk violation parent' => [$page, 'rows.17.parent', 'wp_option_names'],
    'row18 fk violation parent' => [$page, 'rows.18.parent', 'wp_broken_parent'],
    'clean status ok' => [$cleanPage, 'status', 'ok'],
    'clean rootpage errors zero' => [$cleanPage, 'current.rootpage_errors', 0],
    'clean index blockers zero' => [$cleanPage, 'current.index_blockers', 0],
    'clean fk violations zero' => [$cleanPage, 'current.foreign_key_violations', 0],
    'small page count' => [static fn (): array => $page(0, 5), 'count', 5],
    'small page next offset' => [static fn (): array => $page(0, 5), 'next_offset', 5],
    'small page next ready false' => [static fn (): array => $page(0, 5), 'next.ready', false],
    'small page first row boundary kind' => [static fn (): array => $page(0, 5), 'next.first_row.kind', 'index_list'],
    'small page last row boundary source' => [static fn (): array => $page(0, 5), 'next.last_row.source', 'index_rootpage_integrity'],
    'small page blocker root' => [static fn (): array => $page(0, 5), 'next.blocking.0', 'index_rootpage_integrity'],
    'small page blocker parent index' => [static fn (): array => $page(0, 5), 'next.blocking.1', 'foreign_key_parent_unique_index'],
    'small page blocker fk' => [static fn (): array => $page(0, 5), 'next.blocking.2', 'foreign_key_check'],
    'offset thirteen starts fk' => [static fn (): array => $page(13, 4), 'current_row.group', 'pragma_foreign_key_integrity'],
    'offset thirteen next row source' => [static fn (): array => $page(13, 4), 'next_row.source', 'foreign_key_parent_index'],
    'tail complete' => [static fn (): array => $page(20, 5), 'complete', true],
    'tail count' => [static fn (): array => $page(17, 5), 'count', 2],
    'past tail count zero' => [static fn (): array => $page(30, 5), 'count', 0],
];

$tests = [];
foreach ($cases as $name => [$callback, $path, $expected]) {
    $tests['pragma index foreignkey integrity current source next137 ' . $name] = static function (TestRunner $t) use ($callback, $valueAt, $path, $expected): void {
        $t->same($expected, $valueAt($callback(), $path));
    };
}

$tests['pragma index foreignkey integrity current source next137 resumes stable mixed cursor'] = static function (TestRunner $t) use ($page): void {
    $first = $page(0, 5);
    $second = $page(5, 8, ['source_id' => $first['source_id'], 'next_offset' => $first['next_offset']]);
    $third = $page(13, 4, $second['next']);

    $t->same(5, $second['offset']);
    $t->same($first['source_id'], $second['source_id']);
    $t->same('rootpage', $second['rows'][0]['kind']);
    $t->same('rootpage', $second['rows'][7]['kind']);
    $t->same('index_admission', $third['rows'][0]['kind']);
};

$tests['pragma index foreignkey integrity current source next137 accepts source-only cursor'] = static function (TestRunner $t) use ($page): void {
    $first = $page(0, 5);
    $second = $page(5, 5, ['source_id' => $first['source_id']]);

    $t->same(5, $second['offset']);
    $t->same($first['source_id'], $second['source_id']);
};

$tests['pragma index foreignkey integrity current source next137 source changes with database'] = static function (TestRunner $t) use ($page, $validDatabase, $pointerMismatchDatabase): void {
    $first = $page(0, 137, null, $validDatabase);
    $mutated = $page(0, 137, null, $pointerMismatchDatabase);

    $t->same(true, $first['source_id'] !== $mutated['source_id']);
    $t->same(0, $first['current']['rootpage_errors']);
    $t->same(1, $mutated['current']['rootpage_errors']);
};

$tests['pragma index foreignkey integrity current source next137 source changes with catalog'] = static function (TestRunner $t) use ($page, $catalogFactory, $record): void {
    $first = $page();
    $catalog = $catalogFactory();
    $catalog->attach('archive', '/tmp/archive.sqlite', [
        $record('table', 'wp_options', 'wp_options', 40, 'CREATE TABLE wp_options(option_name TEXT)', 1),
    ]);
    $mutated = $page(0, 137, null, null, null, null, null, $catalog);

    $t->same(true, $first['source_id'] !== $mutated['source_id']);
};

$tests['pragma index foreignkey integrity current source next137 source changes with table rows'] = static function (TestRunner $t) use ($page, $tables): void {
    $first = $page();
    $mutatedTables = $tables;
    $mutatedTables['wp_sites'][] = ['rowid' => 2, 'blog_id' => 404, 'domain' => 'staging.example'];
    $mutated = $page(0, 137, null, null, null, null, $mutatedTables);

    $t->same(true, $first['source_id'] !== $mutated['source_id']);
    $t->same(2, $mutated['current']['foreign_key_violations']);
};

$tests['pragma index foreignkey integrity current source next137 rejects stale cursor source'] = static function (TestRunner $t) use ($page, $validDatabase, $pointerMismatchDatabase): void {
    $first = $page(0, 5, null, $validDatabase);

    $t->throws(InvalidArgumentException::class, static fn () => $page(5, 5, ['source_id' => $first['source_id'], 'next_offset' => 5], $pointerMismatchDatabase));
};

$tests['pragma index foreignkey integrity current source next137 rejects stale cursor offset'] = static function (TestRunner $t) use ($page): void {
    $first = $page(0, 5);

    $t->throws(InvalidArgumentException::class, static fn () => $page(6, 5, ['source_id' => $first['source_id'], 'next_offset' => 5]));
};

$tests['pragma index foreignkey integrity current source next137 rejects empty source'] = static function (TestRunner $t) use ($catalog, $pointerMismatchDatabase, $records, $foreignKeys, $tables, $nextSource): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLitePragmaIndexForeignKeyIntegrityCurrentSourceNext::page($catalog, 'PRAGMA main.index_list(wp_options)', $pointerMismatchDatabase, $records, $foreignKeys, $tables, '', $nextSource));
};

$tests['pragma index foreignkey integrity current source next137 rejects negative offset'] = static function (TestRunner $t) use ($page): void {
    $t->throws(InvalidArgumentException::class, static fn () => $page(-1, 137));
};

$tests['pragma index foreignkey integrity current source next137 rejects zero limit'] = static function (TestRunner $t) use ($page): void {
    $t->throws(InvalidArgumentException::class, static fn () => $page(0, 0));
};

return $tests;
