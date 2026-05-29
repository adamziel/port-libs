<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteAttachedSchemaCatalog;
use PortLibs\LibSqlite\SQLiteIndexLeafPage;
use PortLibs\LibSqlite\SQLitePointerMapEntry;
use PortLibs\LibSqlite\SQLitePragmaQuickcheckForeignKeyRootpageCurrentSourceNext;
use PortLibs\LibSqlite\SQLiteRecord;
use PortLibs\LibSqlite\SQLiteSchemaRecord;
use PortLibs\LibSqlite\SQLiteTableLeafCell;
use PortLibs\LibSqlite\SQLiteTableLeafPage;

$pageSize132 = 1024;
$record132 = static fn (string $type, string $name, string $table, ?int $root, ?string $sql = null, int $rowid = 1): SQLiteSchemaRecord => new SQLiteSchemaRecord($type, $name, $table, $root, $sql, $rowid);
$catalog132 = static fn (bool $missingParent = false): SQLiteAttachedSchemaCatalog => new SQLiteAttachedSchemaCatalog(array_values(array_filter([
    $record132('table', 'wp_options', 'wp_options', 4, 'CREATE TABLE wp_options(option_id INTEGER PRIMARY KEY, option_name TEXT, option_value TEXT, autoload TEXT)', 1),
    $missingParent ? null : $record132('table', 'wp_option_names', 'wp_option_names', 5, 'CREATE TABLE wp_option_names(name TEXT PRIMARY KEY)', 2),
    $record132('index', 'wp_options_value_expr', 'wp_options', 6, "CREATE INDEX wp_options_value_expr ON wp_options(json_extract(option_value, '$.plugin'), lower(option_name) COLLATE nocase, autoload DESC)", 3),
    $record132('table', 'wp_terms', 'wp_terms', 7, 'CREATE TABLE wp_terms(term_id INTEGER PRIMARY KEY)', 4),
    $record132('table', 'wp_term_taxonomy', 'wp_term_taxonomy', 8, 'CREATE TABLE wp_term_taxonomy(term_taxonomy_id INTEGER PRIMARY KEY, term_id INTEGER)', 5),
])));

$header132 = static function (int $pageCount, int $largestRootPage) use ($pageSize132): string {
    $page = str_repeat("\0", $pageSize132);
    $page = substr_replace($page, "SQLite format 3\0", 0, 16);
    $page = substr_replace($page, pack('n', $pageSize132), 16, 2);
    $page[18] = "\x01";
    $page[19] = "\x01";
    $page = substr_replace($page, pack('N', $pageCount), 28, 4);
    $page = substr_replace($page, pack('N', $largestRootPage), 52, 4);
    $page = substr_replace($page, pack('N', 1), 56, 4);

    return $page;
};
$putPointer132 = static fn (string $page, int $pageNumber, int $type, int $parent): string => substr_replace($page, chr($type) . pack('N', $parent), 5 * ($pageNumber - 3), 5);
$schemaCell132 = static fn (array $values, int $rowId): string => SQLiteTableLeafCell::encode($rowId, SQLiteRecord::encode($values));
$database132 = static function (array $pointerMapEntries, int $largestRootPage = 8) use ($header132, $putPointer132, $schemaCell132, $pageSize132): string {
    $schemaRows = [
        ['table', 'wp_options', 'wp_options', 4, 'CREATE TABLE wp_options(option_id integer primary key, option_name text, option_value text, autoload text)'],
        ['table', 'wp_option_names', 'wp_option_names', 5, 'CREATE TABLE wp_option_names(name text primary key)'],
        ['index', 'wp_options_value_expr', 'wp_options', 6, "CREATE INDEX wp_options_value_expr ON wp_options(json_extract(option_value, '$.plugin'), lower(option_name), autoload DESC)"],
        ['table', 'wp_terms', 'wp_terms', 7, 'CREATE TABLE wp_terms(term_id integer primary key)'],
        ['table', 'wp_term_taxonomy', 'wp_term_taxonomy', 8, 'CREATE TABLE wp_term_taxonomy(term_taxonomy_id integer primary key, term_id integer)'],
    ];
    $pointerMap = str_repeat("\0", $pageSize132);
    foreach ($pointerMapEntries as $entry) {
        $pointerMap = $putPointer132($pointerMap, $entry[0], $entry[1], $entry[2]);
    }
    $pages = [
        1 => SQLiteTableLeafPage::assemble(
            array_map(static fn (array $row, int $index): string => $schemaCell132($row, $index + 1), $schemaRows, array_keys($schemaRows)),
            $pageSize132,
            100,
            $header132(8, $largestRootPage),
        ),
        2 => $pointerMap,
        3 => SQLiteTableLeafPage::assemble([], $pageSize132),
        4 => SQLiteTableLeafPage::assemble([], $pageSize132),
        5 => SQLiteTableLeafPage::assemble([], $pageSize132),
        6 => SQLiteIndexLeafPage::assemble([], $pageSize132),
        7 => SQLiteTableLeafPage::assemble([], $pageSize132),
        8 => SQLiteTableLeafPage::assemble([], $pageSize132),
    ];

    return implode('', $pages);
};
$cleanDatabase132 = $database132([
    [3, SQLitePointerMapEntry::BTREE_PAGE, 4],
    [4, SQLitePointerMapEntry::ROOT_PAGE, 0],
    [5, SQLitePointerMapEntry::ROOT_PAGE, 0],
    [6, SQLitePointerMapEntry::ROOT_PAGE, 0],
    [7, SQLitePointerMapEntry::ROOT_PAGE, 0],
    [8, SQLitePointerMapEntry::ROOT_PAGE, 0],
]);
$dirtyDatabase132 = $database132([
    [3, SQLitePointerMapEntry::BTREE_PAGE, 4],
    [4, SQLitePointerMapEntry::ROOT_PAGE, 0],
    [5, SQLitePointerMapEntry::BTREE_PAGE, 4],
    [6, SQLitePointerMapEntry::BTREE_PAGE, 4],
    [7, SQLitePointerMapEntry::ROOT_PAGE, 0],
    [8, SQLitePointerMapEntry::BTREE_PAGE, 7],
]);
$limitedDatabase132 = $database132([
    [3, SQLitePointerMapEntry::BTREE_PAGE, 4],
    [4, SQLitePointerMapEntry::ROOT_PAGE, 0],
    [5, SQLitePointerMapEntry::BTREE_PAGE, 4],
    [6, SQLitePointerMapEntry::BTREE_PAGE, 4],
    [7, SQLitePointerMapEntry::ROOT_PAGE, 0],
    [8, SQLitePointerMapEntry::BTREE_PAGE, 7],
], 4);

$schemas132 = static function (int $optionMisses = 2): array {
    $options = [['rowid' => 1, 'option_id' => 1, 'option_name' => 'siteurl', 'option_value' => 'https://example.test', 'autoload' => 'yes']];
    for ($i = 1; $i <= $optionMisses; $i++) {
        $options[] = ['rowid' => 'option-' . $i, 'option_id' => $i + 1, 'option_name' => 'missing_' . $i, 'option_value' => '{}', 'autoload' => 'no'];
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
$valueAt132 = static function (mixed $value, string $path): mixed {
    foreach (explode('.', $path) as $part) {
        if (is_array($value) && array_key_exists($part, $value)) {
            $value = $value[$part];
            continue;
        }
        $value = is_numeric($part) ? $value[(int) $part] : $value[$part];
    }

    return $value;
};
$page132 = static fn (
    ?string $db = null,
    ?array $schemas = null,
    ?SQLiteAttachedSchemaCatalog $catalog = null,
    int $offset = 0,
    int $limit = 132,
    string $foreignKeySql = 'PRAGMA foreign_key_check',
    string $quickCheckSql = 'PRAGMA quick_check',
    ?array $cursor = null,
): array => SQLitePragmaQuickcheckForeignKeyRootpageCurrentSourceNext::page(
    $catalog ?? $catalog132(),
    'PRAGMA main.index_xinfo(wp_options_value_expr)',
    $db ?? $dirtyDatabase132,
    $schemas ?? $schemas132(),
    $foreignKeySql,
    $offset,
    $limit,
    $quickCheckSql,
    false,
    $cursor,
);

$dirty132 = static fn (): array => $page132();
$clean132 = static fn (): array => $page132($cleanDatabase132);
$limited132 = static fn (): array => $page132($limitedDatabase132, null, null, 0, 132, 'PRAGMA foreign_key_check', 'PRAGMA quick_check(1)');
$missingCatalog132 = static fn (): array => $page132($cleanDatabase132, $schemas132(), $catalog132(true), 0, 132, "PRAGMA foreign_key_check('wp_options')");
$tableValued132 = static fn (): array => $page132($dirtyDatabase132, $schemas132(), $catalog132(), 0, 132, "SELECT * FROM pragma_foreign_key_check('wp_term_taxonomy')");

$cases132 = [
    'status blocked' => [$dirty132, 'status', 'blocked'],
    'limit next132' => [$dirty132, 'limit', 132],
    'total rows' => [$dirty132, 'total', 10],
    'count rows' => [$dirty132, 'count', 10],
    'complete true' => [$dirty132, 'complete', true],
    'next null' => [$dirty132, 'next', null],
    'next state ready false' => [$dirty132, 'next_state.ready', false],
    'blocking quick first' => [$dirty132, 'next_state.blocking.0', 'quick_check'],
    'blocking fk second' => [$dirty132, 'next_state.blocking.1', 'foreign_key_check'],
    'blocking root pointer third' => [$dirty132, 'next_state.blocking.2', 'rootpage_pointer_map'],
    'blocking root integrity fourth' => [$dirty132, 'next_state.blocking.3', 'rootpage_integrity'],
    'source id length' => [static fn (): array => ['length' => strlen($dirty132()['source_id'])], 'length', 64],
    'current source mode' => [$dirty132, 'current_source.mode', 'quickcheck_foreignkey_rootpage_current_source_next132'],
    'quick source id length' => [static fn (): array => ['length' => strlen($dirty132()['current_source']['quickcheck_source_id'])], 'length', 64],
    'foreign source id length' => [static fn (): array => ['length' => strlen($dirty132()['current_source']['foreign_key_source_id'])], 'length', 64],
    'database source length' => [static fn (): array => ['length' => strlen($dirty132()['current_source']['database'])], 'length', 64],
    'catalog source length' => [static fn (): array => ['length' => strlen($dirty132()['current_source']['catalog'])], 'length', 64],
    'schema source length' => [static fn (): array => ['length' => strlen($dirty132()['current_source']['schemas'])], 'length', 64],
    'index sql normalized' => [$dirty132, 'current_source.index_xinfo_sql', 'pragma main.index_xinfo(wp_options_value_expr)'],
    'quick sql normalized' => [$dirty132, 'current_source.quick_check_sql', 'pragma quick_check'],
    'fk sql normalized' => [$dirty132, 'current_source.foreign_key_sql', 'pragma foreign_key_check'],
    'index rows count' => [$dirty132, 'current.index_xinfo', 4],
    'quick rows count' => [$dirty132, 'current.quick_check', 3],
    'quick error count' => [$dirty132, 'current.quick_check_errors', 3],
    'fk violations count' => [$dirty132, 'current.foreign_key_violations', 3],
    'child root errors count' => [$dirty132, 'current.child_rootpage_errors', 1],
    'parent root errors count' => [$dirty132, 'current.parent_rootpage_errors', 2],
    'missing catalog count' => [$dirty132, 'current.missing_catalog_rootpages', 0],
    'pointer conflict count' => [$dirty132, 'current.pointer_map_conflicts', 3],
    'target schema main' => [$dirty132, 'current.target_schema', 'main'],
    'target index' => [$dirty132, 'current.target_index', 'wp_options_value_expr'],
    'target table' => [$dirty132, 'current.target_table', 'wp_options'],
    'foreign key schema' => [$dirty132, 'current.foreign_key_schemas.0', 'main'],
    'phase index count' => [$dirty132, 'current.row_phases.index_xinfo', 4],
    'phase quick count' => [$dirty132, 'current.row_phases.quick_check', 3],
    'phase fk root count' => [$dirty132, 'current.row_phases.foreign_key_rootpage', 3],
    'first table' => [$dirty132, 'current.tables.0', 'wp_option_names'],
    'options table present' => [$dirty132, 'current.tables.1', 'wp_options'],
    'taxonomy table present' => [$dirty132, 'current.tables.2', 'wp_term_taxonomy'],
    'row0 phase index' => [$dirty132, 'rows.0.phase', 'index_xinfo'],
    'row0 source index' => [$dirty132, 'rows.0.source', 'index_xinfo'],
    'row0 expression cid' => [$dirty132, 'rows.0.cid', -2],
    'row1 collation' => [$dirty132, 'rows.1.coll', 'NOCASE'],
    'row4 phase quick' => [$dirty132, 'rows.4.phase', 'quick_check'],
    'row4 quick message' => [$dirty132, 'rows.4.message', 'pointer-map type btree-page for page 5 does not match expected root-page'],
    'row5 quick rootpage' => [$dirty132, 'rows.5.rootpage', 6],
    'row6 quick rootpage' => [$dirty132, 'rows.6.rootpage', 8],
    'row7 phase fk root' => [$dirty132, 'rows.7.phase', 'foreign_key_rootpage'],
    'row7 rowid' => [$dirty132, 'rows.7.rowid', 'option-1'],
    'row7 parent status' => [$dirty132, 'rows.7.parent_rootpage_status', 'pointer_map'],
    'row9 taxonomy child status' => [$dirty132, 'rows.9.child_rootpage_status', 'pointer_map'],
    'clean quick ok count' => [$clean132, 'current.quick_check_errors', 0],
    'clean fk blocker first' => [$clean132, 'next_state.blocking.0', 'foreign_key_check'],
    'clean no pointer blocker count' => [$clean132, 'current.pointer_map_conflicts', 0],
    'limited quick count' => [$limited132, 'current.quick_check', 1],
    'limited quick errors' => [$limited132, 'current.quick_check_errors', 1],
    'limited quick message' => [$limited132, 'rows.4.message', 'largest root btree page 4 does not match sqlite_schema max rootpage 8'],
    'missing catalog blocker' => [$missingCatalog132, 'next_state.blocking.1', 'foreign_key_rootpage_catalog'],
    'missing catalog count scoped' => [$missingCatalog132, 'current.missing_catalog_rootpages', 2],
    'table valued total' => [$tableValued132, 'current.foreign_key_violations', 1],
    'table valued row table' => [$tableValued132, 'rows.7.table', 'wp_term_taxonomy'],
];

$tests = [];
foreach ($cases132 as $name => [$callback, $path, $expected]) {
    $tests['pragma quickcheck foreignkey rootpage current source next132 ' . $name] = static function (TestRunner $t) use ($callback, $valueAt132, $path, $expected): void {
        $t->same($expected, $valueAt132($callback(), $path));
    };
}

$tests['pragma quickcheck foreignkey rootpage current source next132 paginates with source cursor'] = static function (TestRunner $t) use ($page132): void {
    $first = $page132(null, null, null, 0, 4);
    $second = $page132(null, null, null, 4, 3, 'PRAGMA foreign_key_check', 'PRAGMA quick_check', $first['next']);
    $third = $page132(null, null, null, 7, 3, 'PRAGMA foreign_key_check', 'PRAGMA quick_check', $second['next']);

    $t->same(4, $first['count']);
    $t->same(['source_id' => $first['source_id'], 'offset' => 4], $first['next']);
    $t->same('quick_check', $second['rows'][0]['phase']);
    $t->same(8, $second['rows'][2]['rootpage']);
    $t->same('foreign_key_rootpage', $third['rows'][0]['phase']);
    $t->same(null, $third['next']);
};

$tests['pragma quickcheck foreignkey rootpage current source next132 accepts cursor offset key'] = static function (TestRunner $t) use ($page132): void {
    $first = $page132(null, null, null, 0, 5);
    $second = $page132(null, null, null, 5, 4, 'PRAGMA foreign_key_check', 'PRAGMA quick_check', ['source_id' => $first['source_id'], 'offset' => 5]);

    $t->same(5, $second['offset']);
    $t->same('quick_check', $second['rows'][0]['phase']);
    $t->same('foreign_key_rootpage', $second['rows'][2]['phase']);
};

$tests['pragma quickcheck foreignkey rootpage current source next132 changes source with schemas'] = static function (TestRunner $t) use ($page132, $schemas132): void {
    $two = $page132(null, $schemas132(2));
    $three = $page132(null, $schemas132(3));

    $t->same(true, $two['source_id'] !== $three['source_id']);
    $t->same(3, $two['current']['foreign_key_violations']);
    $t->same(4, $three['current']['foreign_key_violations']);
};

$tests['pragma quickcheck foreignkey rootpage current source next132 rejects stale database cursor'] = static function (TestRunner $t) use ($page132, $cleanDatabase132): void {
    $first = $page132(null, null, null, 0, 4);
    $t->throws(InvalidArgumentException::class, static fn (): mixed => $page132($cleanDatabase132, null, null, 4, 4, 'PRAGMA foreign_key_check', 'PRAGMA quick_check', $first['next']));
};

$tests['pragma quickcheck foreignkey rootpage current source next132 rejects stale quick sql cursor'] = static function (TestRunner $t) use ($page132): void {
    $first = $page132(null, null, null, 0, 4);
    $t->throws(InvalidArgumentException::class, static fn (): mixed => $page132(null, null, null, 4, 4, 'PRAGMA foreign_key_check', 'PRAGMA quick_check(1)', $first['next']));
};

$tests['pragma quickcheck foreignkey rootpage current source next132 rejects stale fk sql cursor'] = static function (TestRunner $t) use ($page132): void {
    $first = $page132(null, null, null, 0, 4);
    $t->throws(InvalidArgumentException::class, static fn (): mixed => $page132(null, null, null, 4, 4, "PRAGMA foreign_key_check('wp_options')", 'PRAGMA quick_check', $first['next']));
};

$tests['pragma quickcheck foreignkey rootpage current source next132 rejects stale offset cursor'] = static function (TestRunner $t) use ($page132): void {
    $first = $page132(null, null, null, 0, 4);
    $t->throws(InvalidArgumentException::class, static fn (): mixed => $page132(null, null, null, 5, 4, 'PRAGMA foreign_key_check', 'PRAGMA quick_check', $first['next']));
};

$tests['pragma quickcheck foreignkey rootpage current source next132 rejects integrity check sql'] = static function (TestRunner $t) use ($catalog132, $dirtyDatabase132, $schemas132): void {
    $t->throws(InvalidArgumentException::class, static fn (): mixed => SQLitePragmaQuickcheckForeignKeyRootpageCurrentSourceNext::page($catalog132(), 'PRAGMA index_xinfo(wp_options_value_expr)', $dirtyDatabase132, $schemas132(), 'PRAGMA foreign_key_check', 0, 132, 'PRAGMA integrity_check'));
};

$tests['pragma quickcheck foreignkey rootpage current source next132 rejects negative offset'] = static function (TestRunner $t) use ($catalog132, $dirtyDatabase132, $schemas132): void {
    $t->throws(InvalidArgumentException::class, static fn (): mixed => SQLitePragmaQuickcheckForeignKeyRootpageCurrentSourceNext::page($catalog132(), 'PRAGMA index_xinfo(wp_options_value_expr)', $dirtyDatabase132, $schemas132(), 'PRAGMA foreign_key_check', -1));
};

$tests['pragma quickcheck foreignkey rootpage current source next132 rejects zero limit'] = static function (TestRunner $t) use ($catalog132, $dirtyDatabase132, $schemas132): void {
    $t->throws(InvalidArgumentException::class, static fn (): mixed => SQLitePragmaQuickcheckForeignKeyRootpageCurrentSourceNext::page($catalog132(), 'PRAGMA index_xinfo(wp_options_value_expr)', $dirtyDatabase132, $schemas132(), 'PRAGMA foreign_key_check', 0, 0));
};

return $tests;
