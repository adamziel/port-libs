<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteAttachedSchemaCatalog;
use PortLibs\LibSqlite\SQLiteIndexLeafPage;
use PortLibs\LibSqlite\SQLitePointerMapEntry;
use PortLibs\LibSqlite\SQLitePragmaRootpagePointerMapForeignKeyCurrentSourceNext;
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
    ['table', 'wp_options', 'wp_options', 4, 'CREATE TABLE wp_options(option_id INTEGER PRIMARY KEY, option_name TEXT)'],
    ['table', 'wp_option_names', 'wp_option_names', 5, 'CREATE TABLE wp_option_names(name TEXT PRIMARY KEY)'],
    ['table', 'wp_terms', 'wp_terms', 6, 'CREATE TABLE wp_terms(term_id INTEGER PRIMARY KEY)'],
    ['table', 'wp_term_taxonomy', 'wp_term_taxonomy', 7, 'CREATE TABLE wp_term_taxonomy(term_taxonomy_id INTEGER PRIMARY KEY, term_id INTEGER)'],
    ['index', 'wp_options_name', 'wp_options', 8, 'CREATE UNIQUE INDEX wp_options_name ON wp_options(option_name)'],
];

$schemaDatabase = static function (array $entries) use ($headerPage, $putPointerMapEntry, $schemaCell, $schemaRows, $pageSize): string {
    $pointerMap = str_repeat("\0", $pageSize);
    foreach ($entries as $entry) {
        $pointerMap = $putPointerMapEntry($pointerMap, $entry[0], $entry[1], $entry[2]);
    }

    $pages = [
        1 => SQLiteTableLeafPage::assemble(
            array_map(static fn (array $row, int $index): string => $schemaCell($row, $index + 1), $schemaRows, array_keys($schemaRows)),
            $pageSize,
            100,
            $headerPage(8, 8),
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
]);

$cleanDatabase = $schemaDatabase([
    [3, SQLitePointerMapEntry::BTREE_PAGE, 4],
    [4, SQLitePointerMapEntry::ROOT_PAGE, 0],
    [5, SQLitePointerMapEntry::ROOT_PAGE, 0],
    [6, SQLitePointerMapEntry::ROOT_PAGE, 0],
    [7, SQLitePointerMapEntry::ROOT_PAGE, 0],
    [8, SQLitePointerMapEntry::ROOT_PAGE, 0],
]);

$record = static fn (string $name, int $root): SQLiteSchemaRecord => new SQLiteSchemaRecord(
    'table',
    $name,
    $name,
    $root,
    'CREATE TABLE ' . $name,
    $root,
);

$catalog = new SQLiteAttachedSchemaCatalog([
    $record('wp_options', 4),
    $record('wp_option_names', 5),
    $record('wp_terms', 6),
    $record('wp_term_taxonomy', 7),
]);
$missingCatalog = new SQLiteAttachedSchemaCatalog([
    $record('wp_options', 4),
    $record('wp_terms', 6),
    $record('wp_term_taxonomy', 7),
]);

$schemas = static function (int $optionMisses = 3): array {
    $options = [['rowid' => 1, 'option_id' => 1, 'option_name' => 'siteurl']];
    for ($i = 1; $i <= $optionMisses; $i++) {
        $options[] = ['rowid' => 'option-' . $i, 'option_id' => $i + 1, 'option_name' => 'missing_' . $i];
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

$page = static fn (int $offset = 0, int $limit = 122, ?array $cursor = null, string $sql = 'PRAGMA foreign_key_check'): array => SQLitePragmaRootpagePointerMapForeignKeyCurrentSourceNext::page($database, $schemas(), $catalog, $sql, $offset, $limit, $cursor);
$clean = static fn (): array => SQLitePragmaRootpagePointerMapForeignKeyCurrentSourceNext::page($cleanDatabase, $schemas(), $catalog);
$missing = static fn (): array => SQLitePragmaRootpagePointerMapForeignKeyCurrentSourceNext::page($cleanDatabase, $schemas(), $missingCatalog, "PRAGMA foreign_key_check('wp_options')");

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
    'default limit next122' => [$page, 'limit', 122],
    'total violations' => [$page, 'total', 4],
    'count violations' => [$page, 'count', 4],
    'complete true' => [$page, 'complete', true],
    'next null' => [$page, 'next', null],
    'next ready false' => [$page, 'next_state.ready', false],
    'blocker fk' => [$page, 'next_state.blocking.0', 'foreign_key_check'],
    'blocker pointer map' => [$page, 'next_state.blocking.1', 'rootpage_pointer_map'],
    'blocker rootpage' => [$page, 'next_state.blocking.2', 'rootpage_integrity'],
    'current fk violations' => [$page, 'current.foreign_key_violations', 4],
    'current child root errors' => [$page, 'current.child_rootpage_errors', 1],
    'current parent root errors' => [$page, 'current.parent_rootpage_errors', 0],
    'current missing catalog' => [$page, 'current.missing_catalog_rootpages', 0],
    'current pointer map conflicts' => [$page, 'current.pointer_map_conflicts', 1],
    'current schema main' => [$page, 'current.schemas.0', 'main'],
    'source id length' => [static fn (): array => ['length' => strlen($page()['source_id'])], 'length', 64],
    'database source length' => [static fn (): array => ['length' => strlen($page()['current_source']['database'])], 'length', 64],
    'catalog source length' => [static fn (): array => ['length' => strlen($page()['current_source']['catalog'])], 'length', 64],
    'schemas source length' => [static fn (): array => ['length' => strlen($page()['current_source']['schemas'])], 'length', 64],
    'sql normalized' => [$page, 'current_source.foreign_key_sql', 'pragma foreign_key_check'],
    'row0 kind' => [$page, 'rows.0.kind', 'foreign_key_rootpage_pointer_map'],
    'row0 source' => [$page, 'rows.0.source', 'foreign_key'],
    'row0 table' => [$page, 'rows.0.table', 'wp_options'],
    'row0 rowid' => [$page, 'rows.0.rowid', 'option-1'],
    'row0 parent' => [$page, 'rows.0.parent', 'wp_option_names'],
    'row0 fkid' => [$page, 'rows.0.fkid', 1],
    'row0 child rootpage' => [$page, 'rows.0.child_rootpage', 4],
    'row0 parent rootpage' => [$page, 'rows.0.parent_rootpage', 5],
    'row0 child status' => [$page, 'rows.0.child_rootpage_status', 'ok'],
    'row0 parent status' => [$page, 'rows.0.parent_rootpage_status', 'ok'],
    'row0 child pointer type' => [$page, 'rows.0.child_pointer_map_type', 'root-page'],
    'row0 child pointer parent' => [$page, 'rows.0.child_pointer_map_parent', 0],
    'row0 child pointer page' => [$page, 'rows.0.child_pointer_map_page', 2],
    'row0 parent pointer type' => [$page, 'rows.0.parent_pointer_map_type', 'root-page'],
    'row3 taxonomy table' => [$page, 'rows.3.table', 'wp_term_taxonomy'],
    'row3 taxonomy child status pointer' => [$page, 'rows.3.child_rootpage_status', 'pointer_map'],
    'row3 taxonomy child pointer type' => [$page, 'rows.3.child_pointer_map_type', 'btree-page'],
    'row3 taxonomy child pointer parent' => [$page, 'rows.3.child_pointer_map_parent', 6],
    'row3 taxonomy parent status' => [$page, 'rows.3.parent_rootpage_status', 'ok'],
    'row3 taxonomy message' => [$page, 'rows.3.message', 'foreign key mismatch in main.wp_term_taxonomy rowid 12 references wp_terms fkid 2 (child pointer_map pointer-map btree-page parent 6 page 2; parent ok pointer-map root-page parent 0 page 2)'],
    'clean current pointer conflicts zero' => [$clean, 'current.pointer_map_conflicts', 0],
    'clean child errors zero' => [$clean, 'current.child_rootpage_errors', 0],
    'clean only fk blocker status' => [$clean, 'next_state.blocking.0', 'foreign_key_check'],
    'missing catalog count' => [$missing, 'current.missing_catalog_rootpages', 3],
    'missing catalog blocker' => [$missing, 'next_state.blocking.1', 'foreign_key_rootpage_catalog'],
    'missing parent status' => [$missing, 'rows.0.parent_rootpage_status', 'missing_catalog_rootpage'],
    'missing parent pointer type' => [$missing, 'rows.0.parent_pointer_map_type', null],
];

$tests = [];
foreach ($cases as $name => [$callback, $path, $expected]) {
    $tests['pragma rootpage pointermap fk current source next122 ' . $name] = static function (TestRunner $t) use ($callback, $valueAt, $path, $expected): void {
        $t->same($expected, $valueAt($callback(), $path));
    };
}

$tests['pragma rootpage pointermap fk current source next122 paginates with source cursor'] = static function (TestRunner $t) use ($page): void {
    $first = $page(0, 2);
    $second = $page(2, 2, ['source_id' => $first['source_id'], 'next_offset' => 2]);

    $t->same(2, $first['count']);
    $t->same(['source_id' => $first['source_id'], 'offset' => 2], $first['next']);
    $t->same('option-3', $second['rows'][0]['rowid']);
    $t->same(2, $second['count']);
    $t->same(null, $second['next']);
};

$tests['pragma rootpage pointermap fk current source next122 accepts table valued pragma'] = static function (TestRunner $t) use ($database, $schemas, $catalog): void {
    $result = SQLitePragmaRootpagePointerMapForeignKeyCurrentSourceNext::page($database, $schemas(), $catalog, "SELECT * FROM pragma_foreign_key_check('wp_term_taxonomy')");

    $t->same(1, $result['total']);
    $t->same('wp_term_taxonomy', $result['rows'][0]['table']);
    $t->same('pointer_map', $result['rows'][0]['child_rootpage_status']);
};

$tests['pragma rootpage pointermap fk current source next122 source changes with database'] = static function (TestRunner $t) use ($database, $cleanDatabase, $schemas, $catalog): void {
    $dirty = SQLitePragmaRootpagePointerMapForeignKeyCurrentSourceNext::page($database, $schemas(), $catalog);
    $clean = SQLitePragmaRootpagePointerMapForeignKeyCurrentSourceNext::page($cleanDatabase, $schemas(), $catalog);

    $t->same(true, $dirty['source_id'] !== $clean['source_id']);
    $t->same(true, $dirty['current_source']['database'] !== $clean['current_source']['database']);
    $t->same($dirty['current_source']['catalog'], $clean['current_source']['catalog']);
};

$tests['pragma rootpage pointermap fk current source next122 source changes with catalog'] = static function (TestRunner $t) use ($cleanDatabase, $schemas, $catalog, $missingCatalog): void {
    $full = SQLitePragmaRootpagePointerMapForeignKeyCurrentSourceNext::page($cleanDatabase, $schemas(), $catalog);
    $missing = SQLitePragmaRootpagePointerMapForeignKeyCurrentSourceNext::page($cleanDatabase, $schemas(), $missingCatalog, "PRAGMA foreign_key_check('wp_options')");

    $t->same(true, $full['source_id'] !== $missing['source_id']);
    $t->same(true, $full['current_source']['catalog'] !== $missing['current_source']['catalog']);
};

$tests['pragma rootpage pointermap fk current source next122 source changes with schemas'] = static function (TestRunner $t) use ($cleanDatabase, $schemas, $catalog): void {
    $three = SQLitePragmaRootpagePointerMapForeignKeyCurrentSourceNext::page($cleanDatabase, $schemas(3), $catalog);
    $four = SQLitePragmaRootpagePointerMapForeignKeyCurrentSourceNext::page($cleanDatabase, $schemas(4), $catalog);

    $t->same(true, $three['source_id'] !== $four['source_id']);
    $t->same(4, $three['total']);
    $t->same(5, $four['total']);
};

$tests['pragma rootpage pointermap fk current source next122 rejects stale source cursor'] = static function (TestRunner $t) use ($page, $cleanDatabase, $schemas, $catalog): void {
    $first = $page(0, 2);
    $t->throws(InvalidArgumentException::class, static fn () => SQLitePragmaRootpagePointerMapForeignKeyCurrentSourceNext::page($cleanDatabase, $schemas(), $catalog, 'PRAGMA foreign_key_check', 2, 2, ['source_id' => $first['source_id'], 'next_offset' => 2]));
};

$tests['pragma rootpage pointermap fk current source next122 rejects stale offset cursor'] = static function (TestRunner $t) use ($page): void {
    $first = $page(0, 2);
    $t->throws(InvalidArgumentException::class, static fn () => $page(3, 2, ['source_id' => $first['source_id'], 'next_offset' => 2]));
};

$tests['pragma rootpage pointermap fk current source next122 rejects negative offset'] = static function (TestRunner $t) use ($database, $schemas, $catalog): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLitePragmaRootpagePointerMapForeignKeyCurrentSourceNext::page($database, $schemas(), $catalog, 'PRAGMA foreign_key_check', -1, 122));
};

$tests['pragma rootpage pointermap fk current source next122 rejects zero limit'] = static function (TestRunner $t) use ($database, $schemas, $catalog): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLitePragmaRootpagePointerMapForeignKeyCurrentSourceNext::page($database, $schemas(), $catalog, 'PRAGMA foreign_key_check', 0, 0));
};

return $tests;
