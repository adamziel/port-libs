<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteAttachedSchemaCatalog;
use PortLibs\LibSqlite\SQLiteIndexLeafPage;
use PortLibs\LibSqlite\SQLitePointerMapEntry;
use PortLibs\LibSqlite\SQLitePragmaIndexListForeignKeyRootpageCurrentSourceNext;
use PortLibs\LibSqlite\SQLiteRecord;
use PortLibs\LibSqlite\SQLiteSchemaRecord;
use PortLibs\LibSqlite\SQLiteTableLeafCell;
use PortLibs\LibSqlite\SQLiteTableLeafPage;

$pageSize148 = 1024;
$header148 = static function (int $pageCount, int $largestRootPage) use ($pageSize148): string {
    $page = str_repeat("\0", $pageSize148);
    $page = substr_replace($page, "SQLite format 3\0", 0, 16);
    $page = substr_replace($page, pack('n', $pageSize148), 16, 2);
    $page[18] = "\x01";
    $page[19] = "\x01";
    $page = substr_replace($page, pack('N', $pageCount), 28, 4);
    $page = substr_replace($page, pack('N', $largestRootPage), 52, 4);
    $page = substr_replace($page, pack('N', 1), 56, 4);

    return $page;
};
$putPointer148 = static function (string $page, int $pageNumber, int $type, int $parent) use ($pageSize148): string {
    $offset = 5 * ($pageNumber - 3);
    if ($offset < 0 || $offset + 5 > $pageSize148) {
        throw new RuntimeException('test pointer-map entry offset is out of range');
    }

    return substr_replace($page, chr($type) . pack('N', $parent), $offset, 5);
};
$schemaCell148 = static fn (array $values, int $rowId): string => SQLiteTableLeafCell::encode($rowId, SQLiteRecord::encode($values));
$schemaRows148 = [
    ['table', 'wp_options', 'wp_options', 4, 'CREATE TABLE wp_options(option_id INTEGER PRIMARY KEY, option_name TEXT, autoload TEXT)'],
    ['table', 'wp_option_names', 'wp_option_names', 5, 'CREATE TABLE wp_option_names(name TEXT PRIMARY KEY)'],
    ['table', 'wp_terms', 'wp_terms', 6, 'CREATE TABLE wp_terms(term_id INTEGER PRIMARY KEY)'],
    ['table', 'wp_term_taxonomy', 'wp_term_taxonomy', 7, 'CREATE TABLE wp_term_taxonomy(term_taxonomy_id INTEGER PRIMARY KEY, term_id INTEGER)'],
    ['index', 'wp_options_name', 'wp_options', 8, 'CREATE UNIQUE INDEX wp_options_name ON wp_options(option_name)'],
    ['index', 'wp_options_autoload', 'wp_options', 9, 'CREATE INDEX wp_options_autoload ON wp_options(autoload)'],
];
$database148 = static function (array $entries, int $largestRootPage) use ($pageSize148, $header148, $putPointer148, $schemaCell148, $schemaRows148): string {
    $pointerMap = str_repeat("\0", $pageSize148);
    foreach ($entries as [$pageNumber, $type, $parent]) {
        $pointerMap = $putPointer148($pointerMap, $pageNumber, $type, $parent);
    }
    $pages = [
        1 => SQLiteTableLeafPage::assemble(
            array_map(static fn (array $row, int $index): string => $schemaCell148($row, $index + 1), $schemaRows148, array_keys($schemaRows148)),
            $pageSize148,
            100,
            $header148(9, $largestRootPage),
        ),
        2 => $pointerMap,
        3 => SQLiteTableLeafPage::assemble([], $pageSize148),
        4 => SQLiteTableLeafPage::assemble([], $pageSize148),
        5 => SQLiteTableLeafPage::assemble([], $pageSize148),
        6 => SQLiteTableLeafPage::assemble([], $pageSize148),
        7 => SQLiteTableLeafPage::assemble([], $pageSize148),
        8 => SQLiteIndexLeafPage::assemble([], $pageSize148),
        9 => SQLiteIndexLeafPage::assemble([], $pageSize148),
    ];
    ksort($pages);

    return implode('', $pages);
};

$currentDatabase148 = $database148([
    [3, SQLitePointerMapEntry::BTREE_PAGE, 4],
    [4, SQLitePointerMapEntry::ROOT_PAGE, 0],
    [5, SQLitePointerMapEntry::ROOT_PAGE, 0],
    [6, SQLitePointerMapEntry::ROOT_PAGE, 0],
    [7, SQLitePointerMapEntry::BTREE_PAGE, 6],
    [8, SQLitePointerMapEntry::ROOT_PAGE, 0],
    [9, SQLitePointerMapEntry::BTREE_PAGE, 8],
], 8);
$nextDatabase148 = $database148([
    [3, SQLitePointerMapEntry::BTREE_PAGE, 4],
    [4, SQLitePointerMapEntry::ROOT_PAGE, 0],
    [5, SQLitePointerMapEntry::ROOT_PAGE, 0],
    [6, SQLitePointerMapEntry::ROOT_PAGE, 0],
    [7, SQLitePointerMapEntry::ROOT_PAGE, 0],
    [8, SQLitePointerMapEntry::ROOT_PAGE, 0],
    [9, SQLitePointerMapEntry::ROOT_PAGE, 0],
], 9);

$record148 = static fn (string $type, string $name, string $table, int $root, ?string $sql = null, int $rowId = 1): SQLiteSchemaRecord => new SQLiteSchemaRecord($type, $name, $table, $root, $sql, $rowId);
$catalog148 = static fn (bool $withParent = true): SQLiteAttachedSchemaCatalog => new SQLiteAttachedSchemaCatalog(array_values(array_filter([
    $record148('table', 'wp_options', 'wp_options', 4, 'CREATE TABLE wp_options(option_id INTEGER PRIMARY KEY, option_name TEXT, autoload TEXT)', 1),
    $withParent ? $record148('table', 'wp_option_names', 'wp_option_names', 5, 'CREATE TABLE wp_option_names(name TEXT PRIMARY KEY)', 2) : null,
    $record148('table', 'wp_terms', 'wp_terms', 6, 'CREATE TABLE wp_terms(term_id INTEGER PRIMARY KEY)', 3),
    $record148('table', 'wp_term_taxonomy', 'wp_term_taxonomy', 7, 'CREATE TABLE wp_term_taxonomy(term_taxonomy_id INTEGER PRIMARY KEY, term_id INTEGER)', 4),
    $record148('index', 'wp_options_name', 'wp_options', 8, 'CREATE UNIQUE INDEX wp_options_name ON wp_options(option_name)', 5),
    $record148('index', 'wp_options_autoload', 'wp_options', 9, 'CREATE INDEX wp_options_autoload ON wp_options(autoload)', 6),
])));
$schemas148 = static function (int $optionMisses = 2, bool $badTaxonomy = true): array {
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
                'wp_term_taxonomy' => $badTaxonomy
                    ? [['rowid' => 12, 'term_taxonomy_id' => 12, 'term_id' => 404]]
                    : [['rowid' => 11, 'term_taxonomy_id' => 11, 'term_id' => 1]],
            ],
            'foreignKeys' => [
                ['id' => 1, 'table' => 'wp_options', 'parent' => 'wp_option_names', 'columns' => [['child' => 'option_name', 'parent' => 'name']]],
                ['id' => 2, 'table' => 'wp_term_taxonomy', 'parent' => 'wp_terms', 'columns' => [['child' => 'term_id', 'parent' => 'term_id']]],
            ],
        ],
    ];
};

$page148 = static fn (
    int $offset = 0,
    int $limit = 148,
    ?array $cursor = null,
    ?string $currentDatabase = null,
    ?array $currentSchemas = null,
    ?SQLiteAttachedSchemaCatalog $currentCatalog = null,
    ?string $nextDatabase = null,
    ?array $nextSchemas = null,
    ?SQLiteAttachedSchemaCatalog $nextCatalog = null,
    string $indexSql = 'PRAGMA main.index_list(wp_options)',
    string $foreignKeySql = 'PRAGMA foreign_key_check',
): array => SQLitePragmaIndexListForeignKeyRootpageCurrentSourceNext::currentNextPage(
    $currentCatalog ?? $catalog148(),
    $currentDatabase ?? $currentDatabase148,
    $currentSchemas ?? $schemas148(),
    $nextCatalog ?? $catalog148(),
    $nextDatabase ?? $nextDatabase148,
    $nextSchemas ?? $schemas148(0, false),
    $indexSql,
    $foreignKeySql,
    $offset,
    $limit,
    'PRAGMA integrity_check',
    false,
    $cursor,
);

$valueAt148 = static function (mixed $value, string $path): mixed {
    foreach (explode('.', $path) as $part) {
        if (is_array($value) && array_key_exists($part, $value)) {
            $value = $value[$part];
            continue;
        }
        $value = $value[(int) $part];
    }

    return $value;
};

$default148 = static fn (): array => $page148();
$missingParent148 = static fn (): array => $page148(nextSchemas: $schemas148(1, false), nextCatalog: $catalog148(false));
$cases148 = [
    'status ok after next repair' => [$default148, 'status', 'ok'],
    'default limit' => [$default148, 'limit', 148],
    'total rows current plus next' => [$default148, 'total', 14],
    'count rows' => [$default148, 'count', 14],
    'complete true' => [$default148, 'complete', true],
    'next null' => [$default148, 'next', null],
    'next ready true' => [$default148, 'next_state.ready', true],
    'current index list' => [$default148, 'current.index_list', 2],
    'current index rootpages' => [$default148, 'current.index_rootpage', 4],
    'current index rootpage errors' => [$default148, 'current.index_rootpage_errors', 2],
    'current fk violations' => [$default148, 'current.foreign_key_violations', 3],
    'current fk rootpage errors' => [$default148, 'current.foreign_key_rootpage_errors', 1],
    'current pointer conflicts' => [$default148, 'current.pointer_map_conflicts', 1],
    'current blockers' => [$default148, 'current.total_blockers', 3],
    'next index list' => [$default148, 'next_counts.index_list', 2],
    'next rootpage errors zero' => [$default148, 'next_counts.index_rootpage_errors', 0],
    'next fk zero' => [$default148, 'next_counts.foreign_key_violations', 0],
    'next pointer conflicts zero' => [$default148, 'next_counts.pointer_map_conflicts', 0],
    'delta rootpage cleared' => [$default148, 'delta.index_rootpage_errors', -2],
    'delta fk cleared' => [$default148, 'delta.foreign_key_violations', -3],
    'delta pointer map cleared' => [$default148, 'delta.pointer_map_conflicts', -1],
    'delta cleared true' => [$default148, 'delta.cleared', true],
    'current source index sql' => [$default148, 'current_source.index_list_sql', 'pragma main.index_list(wp_options)'],
    'current source fk sql' => [$default148, 'current_source.foreign_key_sql', 'pragma foreign_key_check'],
    'current source integrity sql' => [$default148, 'current_source.integrity_sql', 'pragma integrity_check'],
    'current source table valued false' => [$default148, 'current_source.table_valued_index_list', false],
    'row0 side current' => [$default148, 'rows.0.side', 'current'],
    'row0 kind index list' => [$default148, 'rows.0.kind', 'index_list'],
    'row0 phase index list' => [$default148, 'rows.0.phase', 'index_list'],
    'row0 name option name' => [$default148, 'rows.0.name', 'wp_options_name'],
    'row2 phase index rootpage' => [$default148, 'rows.2.phase', 'index_rootpage'],
    'row5 rootpage error' => [$default148, 'rows.5.page_status', 'pointer_map'],
    'row6 phase fk rootpage' => [$default148, 'rows.6.phase', 'foreign_key_rootpage'],
    'row6 child status ok' => [$default148, 'rows.6.child_rootpage_status', 'ok'],
    'row8 taxonomy child pointer' => [$default148, 'rows.8.child_rootpage_status', 'pointer_map'],
    'row8 pointer parent' => [$default148, 'rows.8.child_pointer_map_parent', 6],
    'row9 side next' => [$default148, 'rows.9.side', 'next'],
    'row9 kind index list' => [$default148, 'rows.9.kind', 'index_list'],
    'missing catalog status blocked' => [$missingParent148, 'status', 'blocked'],
    'missing catalog blocker' => [$missingParent148, 'next_state.blocking.1', 'foreign_key_rootpage_catalog'],
    'missing catalog count' => [$missingParent148, 'next_counts.missing_catalog_rootpages', 1],
];

$tests = [];
foreach ($cases148 as $name => [$factory, $path, $expected]) {
    $tests['pragma index list foreignkey rootpage current source next148 ' . $name] = static function (TestRunner $t) use ($factory, $path, $expected, $valueAt148): void {
        $t->same($expected, $valueAt148($factory(), $path));
    };
}

$tests['pragma index list foreignkey rootpage current source next148 paginates stable current next source'] = static function (TestRunner $t) use ($page148): void {
    $first = $page148(0, 6);
    $second = $page148(6, 4, $first['next']);
    $third = $page148(10, 4, $second['next']);

    $t->same(6, $first['count']);
    $t->same(['source_id' => $first['source_id'], 'offset' => 6], $first['next']);
    $t->same('foreign_key_rootpage', $second['rows'][0]['kind']);
    $t->same('next', $third['rows'][0]['side']);
    $t->same(null, $third['next']);
};

$tests['pragma index list foreignkey rootpage current source next148 table valued index and fk SQL'] = static function (TestRunner $t) use ($catalog148, $currentDatabase148, $nextDatabase148, $schemas148): void {
    $result = SQLitePragmaIndexListForeignKeyRootpageCurrentSourceNext::currentNextPage(
        $catalog148(),
        $currentDatabase148,
        $schemas148(),
        $catalog148(),
        $nextDatabase148,
        $schemas148(0, false),
        "pragma_index_list('wp_options','main')",
        "SELECT * FROM pragma_foreign_key_check('main.wp_options')",
        0,
        148,
        'PRAGMA quick_check',
        true,
    );

    $t->same(true, $result['current_source']['table_valued_index_list']);
    $t->same("pragma_index_list('wp_options','main')", $result['current_source']['index_list_sql']);
    $t->same("select * from pragma_foreign_key_check('main.wp_options')", $result['current_source']['foreign_key_sql']);
    $t->same(2, $result['current']['foreign_key_violations']);
};

$tests['pragma index list foreignkey rootpage current source next148 source changes with next database'] = static function (TestRunner $t) use ($page148, $currentDatabase148): void {
    $first = $page148(0, 5);
    $second = $page148(0, 5, null, nextDatabase: $currentDatabase148);

    $t->same(true, $first['source_id'] !== $second['source_id']);
    $t->same(true, $first['next_source']['database'] !== $second['next_source']['database']);
};

$tests['pragma index list foreignkey rootpage current source next148 rejects stale source cursor'] = static function (TestRunner $t) use ($page148, $currentDatabase148): void {
    $first = $page148(0, 5);
    $t->throws(InvalidArgumentException::class, static fn (): array => $page148(5, 5, $first['next'], nextDatabase: $currentDatabase148));
};

$tests['pragma index list foreignkey rootpage current source next148 rejects stale offset cursor'] = static function (TestRunner $t) use ($page148): void {
    $first = $page148(0, 5);
    $t->throws(InvalidArgumentException::class, static fn (): array => $page148(6, 5, $first['next']));
};

$tests['pragma index list foreignkey rootpage current source next148 rejects negative offset'] = static function (TestRunner $t) use ($catalog148, $currentDatabase148, $nextDatabase148, $schemas148): void {
    $t->throws(InvalidArgumentException::class, static fn (): array => SQLitePragmaIndexListForeignKeyRootpageCurrentSourceNext::currentNextPage(
        $catalog148(),
        $currentDatabase148,
        $schemas148(),
        $catalog148(),
        $nextDatabase148,
        $schemas148(0, false),
        'PRAGMA main.index_list(wp_options)',
        'PRAGMA foreign_key_check',
        -1,
    ));
};

$tests['pragma index list foreignkey rootpage current source next148 rejects zero limit'] = static function (TestRunner $t) use ($catalog148, $currentDatabase148, $nextDatabase148, $schemas148): void {
    $t->throws(InvalidArgumentException::class, static fn (): array => SQLitePragmaIndexListForeignKeyRootpageCurrentSourceNext::currentNextPage(
        $catalog148(),
        $currentDatabase148,
        $schemas148(),
        $catalog148(),
        $nextDatabase148,
        $schemas148(0, false),
        'PRAGMA main.index_list(wp_options)',
        'PRAGMA foreign_key_check',
        0,
        0,
    ));
};

return $tests;
