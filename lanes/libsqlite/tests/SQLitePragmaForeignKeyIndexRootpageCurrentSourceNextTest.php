<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteAttachedSchemaCatalog;
use PortLibs\LibSqlite\SQLiteIndexLeafPage;
use PortLibs\LibSqlite\SQLitePointerMapEntry;
use PortLibs\LibSqlite\SQLitePragmaForeignKeyIndexRootpageCurrentSourceNext;
use PortLibs\LibSqlite\SQLiteRecord;
use PortLibs\LibSqlite\SQLiteSchemaRecord;
use PortLibs\LibSqlite\SQLiteTableLeafCell;
use PortLibs\LibSqlite\SQLiteTableLeafPage;

$pageSize = 1024;
$record = static fn (string $type, string $name, string $table, int $root, ?string $sql = null, int $rowid = 1): SQLiteSchemaRecord => new SQLiteSchemaRecord($type, $name, $table, $root, $sql, $rowid);

$catalog = static function (bool $missingParent = false) use ($record): SQLiteAttachedSchemaCatalog {
    $records = [
        $record('table', 'wp_options', 'wp_options', 4, 'CREATE TABLE wp_options(option_id INTEGER PRIMARY KEY, option_name TEXT, autoload TEXT)', 1),
        $record('table', 'wp_terms', 'wp_terms', 6, 'CREATE TABLE wp_terms(term_id INTEGER PRIMARY KEY)', 2),
        $record('table', 'wp_term_taxonomy', 'wp_term_taxonomy', 7, 'CREATE TABLE wp_term_taxonomy(term_taxonomy_id INTEGER PRIMARY KEY, term_id INTEGER)', 3),
        $record('index', 'wp_options_name', 'wp_options', 8, 'CREATE UNIQUE INDEX wp_options_name ON wp_options(option_name COLLATE nocase DESC, autoload)', 4),
    ];
    if (!$missingParent) {
        $records[] = $record('table', 'wp_option_names', 'wp_option_names', 5, 'CREATE TABLE wp_option_names(name TEXT PRIMARY KEY)', 5);
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
$putPointerMapEntry = static fn (string $page, int $pageNumber, int $type, int $parent): string => substr_replace($page, chr($type) . pack('N', $parent), 5 * ($pageNumber - 3), 5);
$schemaCell = static fn (array $values, int $rowid): string => SQLiteTableLeafCell::encode($rowid, SQLiteRecord::encode($values));
$schemaDatabase = static function (array $entries, int $largestRootPage = 8) use ($headerPage, $putPointerMapEntry, $schemaCell, $pageSize): string {
    $schemaRows = [
        ['table', 'wp_options', 'wp_options', 4, 'CREATE TABLE wp_options(option_id INTEGER PRIMARY KEY, option_name TEXT, autoload TEXT)'],
        ['table', 'wp_option_names', 'wp_option_names', 5, 'CREATE TABLE wp_option_names(name TEXT PRIMARY KEY)'],
        ['table', 'wp_terms', 'wp_terms', 6, 'CREATE TABLE wp_terms(term_id INTEGER PRIMARY KEY)'],
        ['table', 'wp_term_taxonomy', 'wp_term_taxonomy', 7, 'CREATE TABLE wp_term_taxonomy(term_taxonomy_id INTEGER PRIMARY KEY, term_id INTEGER)'],
        ['index', 'wp_options_name', 'wp_options', 8, 'CREATE UNIQUE INDEX wp_options_name ON wp_options(option_name COLLATE nocase DESC, autoload)'],
    ];
    $pages = [
        1 => SQLiteTableLeafPage::assemble(
            array_map(static fn (array $row, int $index): string => $schemaCell($row, $index + 1), $schemaRows, array_keys($schemaRows)),
            $pageSize,
            100,
            $headerPage(8, $largestRootPage),
        ),
        2 => str_repeat("\0", $pageSize),
    ];
    foreach ($entries as $entry) {
        $pages[2] = $putPointerMapEntry($pages[2], $entry[0], $entry[1], $entry[2]);
    }
    for ($pageNumber = 3; $pageNumber <= 8; $pageNumber++) {
        $pages[$pageNumber] = $pageNumber === 8
            ? SQLiteIndexLeafPage::assemble([], $pageSize)
            : SQLiteTableLeafPage::assemble([], $pageSize);
    }
    ksort($pages);

    return implode('', $pages);
};

$currentDatabase = $schemaDatabase([
    [3, SQLitePointerMapEntry::BTREE_PAGE, 4],
    [4, SQLitePointerMapEntry::ROOT_PAGE, 0],
    [5, SQLitePointerMapEntry::ROOT_PAGE, 0],
    [6, SQLitePointerMapEntry::ROOT_PAGE, 0],
    [7, SQLitePointerMapEntry::BTREE_PAGE, 6],
    [8, SQLitePointerMapEntry::ROOT_PAGE, 0],
], 7);
$nextDatabase = $schemaDatabase([
    [3, SQLitePointerMapEntry::BTREE_PAGE, 4],
    [4, SQLitePointerMapEntry::ROOT_PAGE, 0],
    [5, SQLitePointerMapEntry::ROOT_PAGE, 0],
    [6, SQLitePointerMapEntry::ROOT_PAGE, 0],
    [7, SQLitePointerMapEntry::ROOT_PAGE, 0],
    [8, SQLitePointerMapEntry::ROOT_PAGE, 0],
], 8);
$mutatedNextDatabase = $nextDatabase;
$mutatedNextDatabase[48] = "\x02";

$schemas = static function (int $optionMisses = 3, bool $taxonomyMiss = true): array {
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
                'wp_term_taxonomy' => $taxonomyMiss
                    ? [
                        ['rowid' => 11, 'term_taxonomy_id' => 11, 'term_id' => 1],
                        ['rowid' => 12, 'term_taxonomy_id' => 12, 'term_id' => 404],
                    ]
                    : [['rowid' => 11, 'term_taxonomy_id' => 11, 'term_id' => 1]],
            ],
            'foreignKeys' => [
                ['id' => 1, 'table' => 'wp_options', 'parent' => 'wp_option_names', 'columns' => [['child' => 'option_name', 'parent' => 'name', 'affinity' => 'text', 'collation' => 'nocase']]],
                ['id' => 2, 'table' => 'wp_term_taxonomy', 'parent' => 'wp_terms', 'columns' => [['child' => 'term_id', 'parent' => 'term_id', 'affinity' => 'integer']]],
            ],
        ],
    ];
};

$page = static fn (
    int $offset = 0,
    int $limit = 144,
    ?array $cursor = null,
    ?string $nextBytes = null,
    ?array $nextSchemas = null,
    ?SQLiteAttachedSchemaCatalog $currentCatalog = null,
    ?SQLiteAttachedSchemaCatalog $nextCatalog = null,
    string $indexSql = 'PRAGMA main.index_xinfo(wp_options_name)',
    string $foreignKeySql = 'PRAGMA foreign_key_check',
    string $integritySql = 'PRAGMA integrity_check',
    bool $tableValued = false,
): array => SQLitePragmaForeignKeyIndexRootpageCurrentSourceNext::page(
    $currentCatalog ?? $catalog(),
    $nextCatalog ?? $catalog(),
    $indexSql,
    $currentDatabase,
    $schemas(),
    $nextBytes ?? $nextDatabase,
    $nextSchemas ?? $schemas(0, false),
    $foreignKeySql,
    $offset,
    $limit,
    $integritySql,
    $tableValued,
    $cursor,
);

$valueAt = static function (mixed $value, string $path): mixed {
    foreach (explode('.', $path) as $part) {
        if (is_array($value) && array_key_exists($part, $value)) {
            $value = $value[$part];
            continue;
        }
        $value = $value[(int) $part];
    }

    return $value;
};

$cases = [
    'status ok when next repaired' => [static fn (): array => $page(), 'status', 'ok'],
    'default limit next144' => [static fn (): array => $page(), 'limit', 144],
    'total current plus next rows' => [static fn (): array => $page(), 'total', 12],
    'count all rows' => [static fn (): array => $page(), 'count', 12],
    'complete true' => [static fn (): array => $page(), 'complete', true],
    'source id length' => [static fn (): array => ['length' => strlen($page()['source_id'])], 'length', 64],
    'current source database hash length' => [static fn (): array => ['length' => strlen($page()['current_source']['database'])], 'length', 64],
    'next source database hash length' => [static fn (): array => ['length' => strlen($page()['next_source']['database'])], 'length', 64],
    'normalized index sql' => [static fn (): array => $page(), 'current_source.index_xinfo_sql', 'pragma main.index_xinfo(wp_options_name)'],
    'normalized foreign key sql' => [static fn (): array => $page(), 'current_source.foreign_key_sql', 'pragma foreign_key_check'],
    'normalized integrity sql' => [static fn (): array => $page(), 'current_source.integrity_sql', 'pragma integrity_check'],
    'current index xinfo count' => [static fn (): array => $page(), 'current.index_xinfo', 3],
    'current index root blockers' => [static fn (): array => $page(), 'current.index_root_integrity', 2],
    'current foreign key blockers' => [static fn (): array => $page(), 'current.foreign_key_rootpage', 4],
    'current pointer map conflicts' => [static fn (): array => $page(), 'current.pointer_map_conflicts', 1],
    'next index xinfo count' => [static fn (): array => $page(), 'next_counts.index_xinfo', 3],
    'next index root blockers clear' => [static fn (): array => $page(), 'next_counts.index_root_integrity', 0],
    'next foreign key blockers clear' => [static fn (): array => $page(), 'next_counts.foreign_key_rootpage', 0],
    'next pointer map clear' => [static fn (): array => $page(), 'next_counts.pointer_map_conflicts', 0],
    'delta index xinfo stable' => [static fn (): array => $page(), 'delta.index_xinfo', 0],
    'delta index roots cleared' => [static fn (): array => $page(), 'delta.index_root_integrity', -2],
    'delta foreign keys cleared' => [static fn (): array => $page(), 'delta.foreign_key_rootpage', -4],
    'delta pointer map cleared' => [static fn (): array => $page(), 'delta.pointer_map_conflicts', -1],
    'delta cleared true' => [static fn (): array => $page(), 'delta.cleared', true],
    'next ready true' => [static fn (): array => $page(), 'next_state.ready', true],
    'next blocking empty' => [static fn (): array => $page(), 'next_state.blocking', []],
    'row0 current side' => [static fn (): array => $page(), 'rows.0.side', 'current'],
    'row0 xinfo kind' => [static fn (): array => $page(), 'rows.0.kind', 'index_xinfo'],
    'row0 collation nocase' => [static fn (): array => $page(), 'rows.0.coll', 'NOCASE'],
    'row1 autoload desc' => [static fn (): array => $page(), 'rows.1.desc', 0],
    'row2 rowid cid' => [static fn (): array => $page(), 'rows.2.cid', -1],
    'row3 root blocker kind' => [static fn (): array => $page(), 'rows.3.kind', 'index_root_integrity'],
    'row3 largest root message' => [static fn (): array => $page(), 'rows.3.message', 'largest root btree page 7 does not match sqlite_schema max rootpage 8'],
    'row5 first fk kind' => [static fn (): array => $page(), 'rows.5.kind', 'foreign_key_rootpage'],
    'row5 option rowid' => [static fn (): array => $page(), 'rows.5.rowid', 'option-1'],
    'row8 taxonomy child pointer' => [static fn (): array => $page(), 'rows.8.child_rootpage_status', 'pointer_map'],
    'row8 taxonomy parent ok' => [static fn (): array => $page(), 'rows.8.parent_rootpage_status', 'ok'],
    'row9 next side starts' => [static fn (): array => $page(), 'rows.9.side', 'next'],
    'row9 next xinfo' => [static fn (): array => $page(), 'rows.9.kind', 'index_xinfo'],
    'row11 next rowid xinfo' => [static fn (): array => $page(), 'rows.11.kind', 'index_xinfo'],
    'paged first count' => [static fn (): array => $page(0, 5), 'count', 5],
    'paged next offset' => [static fn (): array => $page(0, 5), 'next.offset', 5],
    'paged second offset' => [static fn (): array => $page(5, 5, $page(0, 5)['next']), 'offset', 5],
    'paged second first row fk' => [static fn (): array => $page(5, 5, $page(0, 5)['next']), 'rows.0.kind', 'foreign_key_rootpage'],
    'paged third complete' => [static fn (): array => $page(10, 5, $page(5, 5, $page(0, 5)['next'])['next']), 'complete', true],
    'past tail count zero' => [static fn (): array => $page(40, 5), 'count', 0],
];

$tests = [];
foreach ($cases as $name => [$factory, $path, $expected]) {
    $tests['pragma foreignkey index rootpage current source next144 ' . $name] = static function (TestRunner $t) use ($factory, $valueAt, $path, $expected): void {
        $t->same($expected, $valueAt($factory(), $path));
    };
}

$tests['pragma foreignkey index rootpage current source next144 blocked next reports blockers'] = static function (TestRunner $t) use ($page, $currentDatabase, $schemas): void {
    $result = $page(0, 144, null, $currentDatabase, $schemas(2));

    $t->same('blocked', $result['status']);
    $t->same(false, $result['next_state']['ready']);
    $t->same(['index_root_integrity', 'foreign_key_check', 'rootpage_pointer_map'], $result['next_state']['blocking']);
    $t->same(2, $result['next_counts']['index_root_integrity']);
    $t->same(3, $result['next_counts']['foreign_key_rootpage']);
    $t->same(1, $result['next_counts']['pointer_map_conflicts']);
};

$tests['pragma foreignkey index rootpage current source next144 missing next catalog parent blocks'] = static function (TestRunner $t) use ($page, $catalog, $schemas): void {
    $result = $page(0, 144, null, null, $schemas(2, false), null, $catalog(true));

    $t->same('blocked', $result['status']);
    $t->same(['foreign_key_check', 'foreign_key_rootpage_catalog'], $result['next_state']['blocking']);
    $t->same(2, $result['next_counts']['missing_catalog_rootpages']);
    $t->same(true, in_array('foreign_key_rootpage_catalog', $result['next_state']['blocking'], true));
};

$tests['pragma foreignkey index rootpage current source next144 accepts table valued index and scoped fk'] = static function (TestRunner $t) use ($page): void {
    $result = $page(
        0,
        144,
        null,
        null,
        null,
        null,
        null,
        "pragma_index_xinfo('wp_options_name','main')",
        "SELECT * FROM pragma_foreign_key_check('wp_term_taxonomy')",
        'PRAGMA quick_check',
        true,
    );

    $t->same(true, $result['current_source']['index_table_valued']);
    $t->same("pragma_index_xinfo('wp_options_name','main')", $result['current_source']['index_xinfo_sql']);
    $t->same('select * from pragma_foreign_key_check(\'wp_term_taxonomy\')', $result['current_source']['foreign_key_sql']);
    $t->same('pragma quick_check', $result['current_source']['integrity_sql']);
    $t->same(1, $result['current']['foreign_key_rootpage']);
};

$tests['pragma foreignkey index rootpage current source next144 source changes with next database'] = static function (TestRunner $t) use ($page, $mutatedNextDatabase): void {
    $first = $page(0, 5);
    $second = $page(0, 5, null, $mutatedNextDatabase);

    $t->same(true, $first['source_id'] !== $second['source_id']);
    $t->same($first['current_source']['database'], $second['current_source']['database']);
    $t->same(true, $first['next_source']['database'] !== $second['next_source']['database']);
};

$tests['pragma foreignkey index rootpage current source next144 rejects stale cursor'] = static function (TestRunner $t) use ($page): void {
    $first = $page(0, 4);

    $t->throws(InvalidArgumentException::class, static fn (): array => $page(5, 4, $first['next']));
    $t->throws(InvalidArgumentException::class, static fn (): array => $page(4, 4, ['source_id' => str_repeat('0', 64), 'offset' => 4]));
};

return $tests;
