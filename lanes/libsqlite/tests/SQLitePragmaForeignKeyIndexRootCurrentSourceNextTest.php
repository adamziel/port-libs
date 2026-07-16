<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteAttachedSchemaCatalog;
use PortLibs\LibSqlite\SQLiteIndexLeafPage;
use PortLibs\LibSqlite\SQLitePointerMapEntry;
use PortLibs\LibSqlite\SQLitePragmaForeignKeyIndexRootCurrentSourceNext;
use PortLibs\LibSqlite\SQLiteRecord;
use PortLibs\LibSqlite\SQLiteSchemaRecord;
use PortLibs\LibSqlite\SQLiteTableLeafCell;
use PortLibs\LibSqlite\SQLiteTableLeafPage;

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
$putPointerMapEntry = static function (string $page, int $pageNumber, int $type, int $parent) use ($pageSize): string {
    $offset = 5 * ($pageNumber - 3);
    if ($offset < 0 || $offset + 5 > $pageSize) {
        throw new RuntimeException('test pointer-map entry offset is out of range');
    }

    return substr_replace($page, chr($type) . pack('N', $parent), $offset, 5);
};
$schemaCell = static fn (array $values, int $rowId): string => SQLiteTableLeafCell::encode($rowId, SQLiteRecord::encode($values));

$schemaRows = [
    ['table', 'wp_options', 'wp_options', 4, 'CREATE TABLE wp_options(option_id INTEGER PRIMARY KEY, option_name TEXT, autoload TEXT)'],
    ['table', 'wp_option_names', 'wp_option_names', 5, 'CREATE TABLE wp_option_names(name TEXT PRIMARY KEY)'],
    ['table', 'wp_terms', 'wp_terms', 6, 'CREATE TABLE wp_terms(term_id INTEGER PRIMARY KEY)'],
    ['table', 'wp_term_taxonomy', 'wp_term_taxonomy', 7, 'CREATE TABLE wp_term_taxonomy(term_taxonomy_id INTEGER PRIMARY KEY, term_id INTEGER)'],
    ['index', 'wp_options_name', 'wp_options', 8, 'CREATE UNIQUE INDEX wp_options_name ON wp_options(option_name COLLATE nocase DESC, autoload)'],
];

$schemaDatabase = static function (array $entries, int $largestRootPage = 8) use ($headerPage, $putPointerMapEntry, $schemaCell, $schemaRows, $pageSize): string {
    $pointerMap = str_repeat("\0", $pageSize);
    foreach ($entries as $entry) {
        $pointerMap = $putPointerMapEntry($pointerMap, $entry[0], $entry[1], $entry[2]);
    }

    $pages = [
        1 => SQLiteTableLeafPage::assemble(
            array_map(static fn (array $row, int $index): string => $schemaCell($row, $index + 1), $schemaRows, array_keys($schemaRows)),
            $pageSize,
            100,
            $headerPage(8, $largestRootPage),
        ),
        2 => $pointerMap,
        3 => SQLiteTableLeafPage::assemble([], $pageSize),
        4 => SQLiteTableLeafPage::assemble([], $pageSize),
        5 => SQLiteTableLeafPage::assemble([], $pageSize),
        6 => SQLiteTableLeafPage::assemble([], $pageSize),
        7 => SQLiteTableLeafPage::assemble([], $pageSize),
        8 => SQLiteIndexLeafPage::assemble([], $pageSize),
    ];
    ksort($pages);

    return implode('', $pages);
};

$database = $schemaDatabase([
    [3, SQLitePointerMapEntry::BTREE_PAGE, 4],
    [4, SQLitePointerMapEntry::ROOT_PAGE, 0],
    [5, SQLitePointerMapEntry::ROOT_PAGE, 0],
    [6, SQLitePointerMapEntry::ROOT_PAGE, 0],
    [7, SQLitePointerMapEntry::BTREE_PAGE, 6],
    [8, SQLitePointerMapEntry::ROOT_PAGE, 0],
], 7);
$cleanDatabase = $schemaDatabase([
    [3, SQLitePointerMapEntry::BTREE_PAGE, 4],
    [4, SQLitePointerMapEntry::ROOT_PAGE, 0],
    [5, SQLitePointerMapEntry::ROOT_PAGE, 0],
    [6, SQLitePointerMapEntry::ROOT_PAGE, 0],
    [7, SQLitePointerMapEntry::ROOT_PAGE, 0],
    [8, SQLitePointerMapEntry::ROOT_PAGE, 0],
], 8);

$record = static fn (string $type, string $name, string $table, int $root, ?string $sql = null, int $rowId = 1): SQLiteSchemaRecord => new SQLiteSchemaRecord($type, $name, $table, $root, $sql, $rowId);
$catalog = new SQLiteAttachedSchemaCatalog([
    $record('table', 'wp_options', 'wp_options', 4, 'CREATE TABLE wp_options(option_id INTEGER PRIMARY KEY, option_name TEXT, autoload TEXT)', 1),
    $record('table', 'wp_option_names', 'wp_option_names', 5, 'CREATE TABLE wp_option_names(name TEXT PRIMARY KEY)', 2),
    $record('table', 'wp_terms', 'wp_terms', 6, 'CREATE TABLE wp_terms(term_id INTEGER PRIMARY KEY)', 3),
    $record('table', 'wp_term_taxonomy', 'wp_term_taxonomy', 7, 'CREATE TABLE wp_term_taxonomy(term_taxonomy_id INTEGER PRIMARY KEY, term_id INTEGER)', 4),
    $record('index', 'wp_options_name', 'wp_options', 8, 'CREATE UNIQUE INDEX wp_options_name ON wp_options(option_name COLLATE nocase DESC, autoload)', 5),
]);
$missingCatalog = new SQLiteAttachedSchemaCatalog([
    $record('table', 'wp_options', 'wp_options', 4, 'CREATE TABLE wp_options(option_id INTEGER PRIMARY KEY, option_name TEXT, autoload TEXT)', 1),
    $record('table', 'wp_terms', 'wp_terms', 6, 'CREATE TABLE wp_terms(term_id INTEGER PRIMARY KEY)', 2),
    $record('table', 'wp_term_taxonomy', 'wp_term_taxonomy', 7, 'CREATE TABLE wp_term_taxonomy(term_taxonomy_id INTEGER PRIMARY KEY, term_id INTEGER)', 3),
    $record('index', 'wp_options_name', 'wp_options', 8, 'CREATE UNIQUE INDEX wp_options_name ON wp_options(option_name COLLATE nocase DESC, autoload)', 4),
]);

$schemas = static function (int $optionMisses = 3): array {
    $options = [['rowid' => 1, 'option_id' => 1, 'option_name' => 'siteurl', 'autoload' => 'yes']];
    for ($i = 1; $i <= $optionMisses; $i++) {
        $options[] = ['rowid' => 'option-' . $i, 'option_id' => $i + 1, 'option_name' => 'missing_' . $i, 'autoload' => 'no'];
    }

    return [
        'main' => [
            'tables' => [
                'wp_option_names' => [['rowid' => 1, 'name' => 'siteurl']],
                'wp_options' => $options,
                'wp_terms' => [['rowid' => 1, 'term_id' => 1]],
                'wp_term_taxonomy' => [
                    ['rowid' => 11, 'term_taxonomy_id' => 11, 'term_id' => 1],
                    ['rowid' => 12, 'term_taxonomy_id' => 12, 'term_id' => 404],
                ],
            ],
            'foreignKeys' => [
                ['id' => 1, 'table' => 'wp_options', 'parent' => 'wp_option_names', 'columns' => [['child' => 'option_name', 'parent' => 'name', 'affinity' => 'text', 'collation' => 'nocase']]],
                ['id' => 2, 'table' => 'wp_term_taxonomy', 'parent' => 'wp_terms', 'columns' => [['child' => 'term_id', 'parent' => 'term_id', 'affinity' => 'integer']]],
            ],
        ],
    ];
};

$page = static fn (int $offset = 0, int $limit = 125, ?array $cursor = null, string $indexSql = 'PRAGMA main.index_xinfo(wp_options_name)', string $databaseBytes = null, ?array $schemaRowsForRun = null, SQLiteAttachedSchemaCatalog $runCatalog = null): array => SQLitePragmaForeignKeyIndexRootCurrentSourceNext::page(
    $runCatalog ?? $catalog,
    $indexSql,
    $databaseBytes ?? $database,
    $schemaRowsForRun ?? $schemas(),
    'PRAGMA foreign_key_check',
    $offset,
    $limit,
    'PRAGMA integrity_check',
    false,
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
    'status blocked' => [$page, 'status', 'blocked'],
    'default limit next125' => [$page, 'limit', 125],
    'total rows' => [$page, 'total', 9],
    'count rows' => [$page, 'count', 9],
    'complete true' => [$page, 'complete', true],
    'next null' => [$page, 'next', null],
    'next ready false' => [$page, 'next_state.ready', false],
    'first blocker index root' => [$page, 'next_state.blocking.0', 'index_root_integrity'],
    'second blocker fk' => [$page, 'next_state.blocking.1', 'foreign_key_check'],
    'third blocker pointer map' => [$page, 'next_state.blocking.2', 'rootpage_pointer_map'],
    'current index xinfo count' => [$page, 'current.index_xinfo', 3],
    'current index root count' => [$page, 'current.index_root_integrity', 2],
    'current foreign key count' => [$page, 'current.foreign_key_rootpage', 4],
    'current pointer map conflicts' => [$page, 'current.pointer_map_conflicts', 1],
    'current missing catalog rootpages zero' => [$page, 'current.missing_catalog_rootpages', 0],
    'current schema main' => [$page, 'current.schemas.0', 'main'],
    'source id length' => [static fn (): array => ['length' => strlen($page()['source_id'])], 'length', 64],
    'database hash length' => [static fn (): array => ['length' => strlen($page()['current_source']['database'])], 'length', 64],
    'catalog hash length' => [static fn (): array => ['length' => strlen($page()['current_source']['catalog'])], 'length', 64],
    'schemas hash length' => [static fn (): array => ['length' => strlen($page()['current_source']['schemas'])], 'length', 64],
    'index sql normalized' => [$page, 'current_source.index_xinfo_sql', 'pragma main.index_xinfo(wp_options_name)'],
    'integrity sql normalized' => [$page, 'current_source.integrity_sql', 'pragma integrity_check'],
    'foreign key sql normalized' => [$page, 'current_source.foreign_key_sql', 'pragma foreign_key_check'],
    'not table valued' => [$page, 'current_source.index_table_valued', false],
    'row0 kind xinfo' => [$page, 'rows.0.kind', 'index_xinfo'],
    'row0 source xinfo' => [$page, 'rows.0.source', 'index_xinfo'],
    'row0 schema' => [$page, 'rows.0.schema', 'main'],
    'row0 target' => [$page, 'rows.0.target', 'wp_options_name'],
    'row0 name' => [$page, 'rows.0.name', 'option_name'],
    'row0 coll nocase' => [$page, 'rows.0.coll', 'NOCASE'],
    'row0 desc' => [$page, 'rows.0.desc', 1],
    'row1 name autoload' => [$page, 'rows.1.name', 'autoload'],
    'row1 key' => [$page, 'rows.1.key', 1],
    'row2 rowid cid' => [$page, 'rows.2.cid', -1],
    'row2 rowid key' => [$page, 'rows.2.key', 0],
    'row3 index root kind' => [$page, 'rows.3.kind', 'index_root_integrity'],
    'row3 source index root' => [$page, 'rows.3.source', 'index_root_integrity'],
    'row3 index root message' => [$page, 'rows.3.message', 'largest root btree page 7 does not match sqlite_schema max rootpage 8'],
    'row4 fk kind' => [$page, 'rows.5.kind', 'foreign_key_rootpage'],
    'row4 fk source' => [$page, 'rows.5.source', 'foreign_key'],
    'row4 fk table' => [$page, 'rows.5.table', 'wp_options'],
    'row4 fk rowid' => [$page, 'rows.5.rowid', 'option-1'],
    'row4 parent' => [$page, 'rows.5.parent', 'wp_option_names'],
    'row4 child status' => [$page, 'rows.5.child_rootpage_status', 'ok'],
    'row4 parent status' => [$page, 'rows.5.parent_rootpage_status', 'ok'],
    'row4 child pointer type' => [$page, 'rows.5.child_pointer_map_type', 'root-page'],
    'row7 taxonomy table' => [$page, 'rows.8.table', 'wp_term_taxonomy'],
    'row7 taxonomy child status pointer' => [$page, 'rows.8.child_rootpage_status', 'pointer_map'],
    'row7 taxonomy child pointer type' => [$page, 'rows.8.child_pointer_map_type', 'btree-page'],
    'row7 taxonomy child pointer parent' => [$page, 'rows.8.child_pointer_map_parent', 6],
    'row7 taxonomy parent status' => [$page, 'rows.8.parent_rootpage_status', 'ok'],
    'clean total no index root' => [static fn (): array => $page(0, 125, null, 'PRAGMA main.index_xinfo(wp_options_name)', $cleanDatabase), 'current.index_root_integrity', 0],
    'clean pointer conflicts zero' => [static fn (): array => $page(0, 125, null, 'PRAGMA main.index_xinfo(wp_options_name)', $cleanDatabase), 'current.pointer_map_conflicts', 0],
    'missing catalog rootpages' => [static fn (): array => $page(0, 125, null, 'PRAGMA main.index_xinfo(wp_options_name)', $cleanDatabase, null, $missingCatalog), 'current.missing_catalog_rootpages', 3],
    'missing catalog blocker' => [static fn (): array => $page(0, 125, null, 'PRAGMA main.index_xinfo(wp_options_name)', $cleanDatabase, null, $missingCatalog), 'next_state.blocking.1', 'foreign_key_rootpage_catalog'],
    'missing parent status' => [static fn (): array => $page(0, 125, null, 'PRAGMA main.index_xinfo(wp_options_name)', $cleanDatabase, null, $missingCatalog), 'rows.5.parent_rootpage_status', 'missing_catalog_rootpage'],
];

$tests = [];
foreach ($cases as $name => [$callback, $path, $expected]) {
    $tests['pragma foreignkey index root current source next125 ' . $name] = static function (TestRunner $t) use ($callback, $valueAt, $path, $expected): void {
        $t->same($expected, $valueAt($callback(), $path));
    };
}

$tests['pragma foreignkey index root current source next125 paginates with source cursor'] = static function (TestRunner $t) use ($page): void {
    $first = $page(0, 5);
    $second = $page(5, 4, ['source_id' => $first['source_id'], 'next_offset' => 5]);

    $t->same(5, $first['count']);
    $t->same(['source_id' => $first['source_id'], 'offset' => 5], $first['next']);
    $t->same('foreign_key_rootpage', $second['rows'][0]['kind']);
    $t->same('option-1', $second['rows'][0]['rowid']);
    $t->same(null, $second['next']);
};

$tests['pragma foreignkey index root current source next125 accepts table valued index pragma'] = static function (TestRunner $t) use ($catalog, $cleanDatabase, $schemas): void {
    $result = SQLitePragmaForeignKeyIndexRootCurrentSourceNext::page(
        $catalog,
        "pragma_index_xinfo('wp_options_name','main')",
        $cleanDatabase,
        $schemas(),
        "SELECT * FROM pragma_foreign_key_check('wp_term_taxonomy')",
        0,
        125,
        'PRAGMA quick_check',
        true,
    );

    $t->same(true, $result['current_source']['index_table_valued']);
    $t->same("pragma_index_xinfo('wp_options_name','main')", $result['current_source']['index_xinfo_sql']);
    $t->same('select * from pragma_foreign_key_check(\'wp_term_taxonomy\')', $result['current_source']['foreign_key_sql']);
    $t->same(3, $result['current']['index_xinfo']);
    $t->same(1, $result['current']['foreign_key_rootpage']);
};

$tests['pragma foreignkey index root current source next125 source changes with index sql'] = static function (TestRunner $t) use ($page): void {
    $first = $page(0, 4, null, 'PRAGMA main.index_xinfo(wp_options_name)');
    $second = $page(0, 4, null, 'PRAGMA index_xinfo(wp_options_name)');

    $t->same(true, $first['source_id'] !== $second['source_id']);
    $t->same(true, $first['current_source']['index_xinfo_sql'] !== $second['current_source']['index_xinfo_sql']);
};

$tests['pragma foreignkey index root current source next125 source changes with database'] = static function (TestRunner $t) use ($page, $cleanDatabase): void {
    $dirty = $page(0, 4);
    $clean = $page(0, 4, null, 'PRAGMA main.index_xinfo(wp_options_name)', $cleanDatabase);

    $t->same(true, $dirty['source_id'] !== $clean['source_id']);
    $t->same(true, $dirty['current_source']['database'] !== $clean['current_source']['database']);
};

$tests['pragma foreignkey index root current source next125 source changes with schema rows'] = static function (TestRunner $t) use ($page, $schemas): void {
    $three = $page(0, 4, null, 'PRAGMA main.index_xinfo(wp_options_name)', null, $schemas(3));
    $four = $page(0, 4, null, 'PRAGMA main.index_xinfo(wp_options_name)', null, $schemas(4));

    $t->same(true, $three['source_id'] !== $four['source_id']);
    $t->same(9, $three['total']);
    $t->same(10, $four['total']);
};

$tests['pragma foreignkey index root current source next125 rejects stale source cursor'] = static function (TestRunner $t) use ($page, $cleanDatabase): void {
    $first = $page(0, 4);
    $t->throws(InvalidArgumentException::class, static fn () => $page(4, 4, ['source_id' => $first['source_id'], 'next_offset' => 4], 'PRAGMA main.index_xinfo(wp_options_name)', $cleanDatabase));
};

$tests['pragma foreignkey index root current source next125 rejects stale offset cursor'] = static function (TestRunner $t) use ($page): void {
    $first = $page(0, 4);
    $t->throws(InvalidArgumentException::class, static fn () => $page(5, 4, ['source_id' => $first['source_id'], 'next_offset' => 4]));
};

$tests['pragma foreignkey index root current source next125 rejects negative offset'] = static function (TestRunner $t) use ($catalog, $database, $schemas): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLitePragmaForeignKeyIndexRootCurrentSourceNext::page($catalog, 'PRAGMA main.index_xinfo(wp_options_name)', $database, $schemas(), 'PRAGMA foreign_key_check', -1));
};

$tests['pragma foreignkey index root current source next125 rejects zero limit'] = static function (TestRunner $t) use ($catalog, $database, $schemas): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLitePragmaForeignKeyIndexRootCurrentSourceNext::page($catalog, 'PRAGMA main.index_xinfo(wp_options_name)', $database, $schemas(), 'PRAGMA foreign_key_check', 0, 0));
};

return $tests;
