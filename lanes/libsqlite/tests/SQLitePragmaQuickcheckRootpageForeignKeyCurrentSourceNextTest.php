<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteAttachedSchemaCatalog;
use PortLibs\LibSqlite\SQLiteIndexLeafPage;
use PortLibs\LibSqlite\SQLitePointerMapEntry;
use PortLibs\LibSqlite\SQLitePragmaQuickcheckRootpageForeignKeyCurrentSourceNextPlan;
use PortLibs\LibSqlite\SQLiteRecord;
use PortLibs\LibSqlite\SQLiteSchemaRecord;
use PortLibs\LibSqlite\SQLiteTableLeafCell;
use PortLibs\LibSqlite\SQLiteTableLeafPage;

$pageSize142 = 1024;

$header142 = static function (int $pageCount, int $largestRootPage) use ($pageSize142): string {
    $page = str_repeat("\0", $pageSize142);
    $page = substr_replace($page, "SQLite format 3\0", 0, 16);
    $page = substr_replace($page, pack('n', $pageSize142), 16, 2);
    $page[18] = "\x01";
    $page[19] = "\x01";
    $page = substr_replace($page, pack('N', $pageCount), 28, 4);
    $page = substr_replace($page, pack('N', $largestRootPage), 52, 4);
    $page = substr_replace($page, pack('N', 1), 56, 4);

    return $page;
};

$putPointer142 = static function (string $page, int $pageNumber, int $type, int $parent) use ($pageSize142): string {
    $offset = 5 * ($pageNumber - 3);
    if ($offset < 0 || $offset + 5 > $pageSize142) {
        throw new RuntimeException('test pointer-map entry offset is out of range');
    }

    return substr_replace($page, chr($type) . pack('N', $parent), $offset, 5);
};

$schemaCell142 = static fn (array $values, int $rowId): string => SQLiteTableLeafCell::encode($rowId, SQLiteRecord::encode($values));

$schemaRows142 = [
    ['table', 'wp_options', 'wp_options', 4, 'CREATE TABLE wp_options(option_id integer primary key, option_name text, autoload text)'],
    ['index', 'wp_options_name', 'wp_options', 5, 'CREATE UNIQUE INDEX wp_options_name ON wp_options(option_name)'],
    ['table', 'wp_option_names', 'wp_option_names', 6, 'CREATE TABLE wp_option_names(name text primary key)'],
    ['index', 'wp_option_names_name', 'wp_option_names', 7, 'CREATE UNIQUE INDEX wp_option_names_name ON wp_option_names(name)'],
    ['table', 'wp_posts', 'wp_posts', 8, 'CREATE TABLE wp_posts(ID integer primary key)'],
];

$database142 = static function (array $pointerMapEntries, int $largestRootPage = 9) use ($header142, $putPointer142, $schemaCell142, $schemaRows142, $pageSize142): string {
    $pageCount = 9;
    $pointerMap = str_repeat("\0", $pageSize142);
    foreach ($pointerMapEntries as $entry) {
        $pointerMap = $putPointer142($pointerMap, $entry[0], $entry[1], $entry[2]);
    }
    $pages = [
        1 => SQLiteTableLeafPage::assemble(
            array_map(static fn (array $row, int $index): string => $schemaCell142($row, $index + 1), $schemaRows142, array_keys($schemaRows142)),
            $pageSize142,
            100,
            $header142($pageCount, $largestRootPage),
        ),
        2 => $pointerMap,
        3 => SQLiteTableLeafPage::assemble([], $pageSize142),
        4 => SQLiteTableLeafPage::assemble([], $pageSize142),
        5 => SQLiteIndexLeafPage::assemble([], $pageSize142),
        6 => SQLiteTableLeafPage::assemble([], $pageSize142),
        7 => SQLiteIndexLeafPage::assemble([], $pageSize142),
        8 => SQLiteTableLeafPage::assemble([], $pageSize142),
        9 => SQLiteTableLeafPage::assemble([], $pageSize142),
    ];
    ksort($pages);

    return implode('', $pages);
};

$currentDatabase142 = $database142([
    [3, SQLitePointerMapEntry::BTREE_PAGE, 4],
    [4, SQLitePointerMapEntry::BTREE_PAGE, 3],
    [5, SQLitePointerMapEntry::ROOT_PAGE, 0],
    [6, SQLitePointerMapEntry::BTREE_PAGE, 4],
    [7, SQLitePointerMapEntry::BTREE_PAGE, 6],
    [8, SQLitePointerMapEntry::ROOT_PAGE, 0],
    [9, SQLitePointerMapEntry::ROOT_PAGE, 0],
], 4);

$nextDatabase142 = $database142([
    [3, SQLitePointerMapEntry::BTREE_PAGE, 4],
    [4, SQLitePointerMapEntry::ROOT_PAGE, 0],
    [5, SQLitePointerMapEntry::ROOT_PAGE, 0],
    [6, SQLitePointerMapEntry::ROOT_PAGE, 0],
    [7, SQLitePointerMapEntry::ROOT_PAGE, 0],
    [8, SQLitePointerMapEntry::ROOT_PAGE, 0],
    [9, SQLitePointerMapEntry::ROOT_PAGE, 0],
], 8);

$schemaRecord142 = static fn (string $type, string $name, string $table, int $root): SQLiteSchemaRecord => new SQLiteSchemaRecord(
    $type,
    $name,
    $table,
    $root,
    'CREATE ' . strtoupper($type) . ' ' . $name,
    $root,
);

$catalog142 = new SQLiteAttachedSchemaCatalog([
    $schemaRecord142('table', 'wp_options', 'wp_options', 4),
    $schemaRecord142('table', 'wp_option_names', 'wp_option_names', 6),
    $schemaRecord142('table', 'wp_posts', 'wp_posts', 8),
]);
$missingParentCatalog142 = new SQLiteAttachedSchemaCatalog([
    $schemaRecord142('table', 'wp_options', 'wp_options', 4),
    $schemaRecord142('table', 'wp_posts', 'wp_posts', 8),
]);

$schemas142 = static function (int $missing = 4): array {
    $options = [['rowid' => 1, 'option_name' => 'siteurl']];
    for ($i = 1; $i <= $missing; $i++) {
        $options[] = ['rowid' => 'missing-option-' . $i, 'option_name' => 'missing_' . $i];
    }

    return [
        'main' => [
            'tables' => [
                'wp_option_names' => [['rowid' => 1, 'name' => 'siteurl']],
                'wp_options' => $options,
                'wp_posts' => [['rowid' => 1, 'ID' => 1]],
            ],
            'foreignKeys' => [
                ['id' => 142, 'table' => 'wp_options', 'parent' => 'wp_option_names', 'columns' => [
                    ['child' => 'option_name', 'parent' => 'name', 'affinity' => 'text', 'collation' => 'nocase'],
                ]],
            ],
        ],
    ];
};

$page142 = static fn (
    int $offset = 0,
    int $limit = 142,
    ?array $cursor = null,
    ?string $nextDatabase = null,
    ?array $nextSchemas = null,
    ?SQLiteAttachedSchemaCatalog $nextCatalog = null,
    string $foreignKeySql = 'PRAGMA foreign_key_check(wp_options)',
    string $quickCheckSql = 'PRAGMA quick_check',
): array => SQLitePragmaQuickcheckRootpageForeignKeyCurrentSourceNextPlan::page(
    $currentDatabase142,
    $schemas142(),
    $catalog142,
    $nextDatabase ?? $nextDatabase142,
    $nextSchemas ?? $schemas142(0),
    $nextCatalog ?? $catalog142,
    $foreignKeySql,
    $quickCheckSql,
    $offset,
    $limit,
    $cursor,
);

$valueAt142 = static function (mixed $value, string $path): mixed {
    foreach (explode('.', $path) as $part) {
        if (is_array($value) && array_key_exists($part, $value)) {
            $value = $value[$part];
            continue;
        }
        $value = is_numeric($part) ? $value[(int) $part] : $value[$part];
    }

    return $value;
};

$cases142 = [
    'status ok after quickcheck and fk repair' => [static fn (): array => $page142(), 'status', 'ok'],
    'default limit' => [static fn (): array => $page142(), 'limit', 142],
    'total current rows only' => [static fn (): array => $page142(), 'total', 8],
    'count current rows only' => [static fn (): array => $page142(), 'count', 8],
    'complete true' => [static fn (): array => $page142(), 'complete', true],
    'next null' => [static fn (): array => $page142(), 'next', null],
    'source id length' => [static fn (): array => ['length' => strlen($page142()['source_id'])], 'length', 64],
    'current database hash length' => [static fn (): array => ['length' => strlen($page142()['current_source']['database'])], 'length', 64],
    'next database hash length' => [static fn (): array => ['length' => strlen($page142()['next_source']['database'])], 'length', 64],
    'current catalog hash length' => [static fn (): array => ['length' => strlen($page142()['current_source']['catalog'])], 'length', 64],
    'next catalog hash length' => [static fn (): array => ['length' => strlen($page142()['next_source']['catalog'])], 'length', 64],
    'current schema hash length' => [static fn (): array => ['length' => strlen($page142()['current_source']['schemas'])], 'length', 64],
    'next schema hash length' => [static fn (): array => ['length' => strlen($page142()['next_source']['schemas'])], 'length', 64],
    'quick sql normalized' => [static fn (): array => $page142(), 'current_source.quick_check_sql', 'pragma quick_check'],
    'fk sql normalized' => [static fn (): array => $page142(), 'current_source.foreign_key_sql', 'pragma foreign_key_check(wp_options)'],
    'quick scope database' => [static fn (): array => $page142(), 'current_source.quick_check_scope', 'database'],
    'quick target null' => [static fn (): array => $page142(), 'current_source.quick_check_target', null],
    'current quick rootpage rows' => [static fn (): array => $page142(), 'current.quick_check_rootpages', 4],
    'current quick errors' => [static fn (): array => $page142(), 'current.quick_check_errors', 4],
    'current fk violations' => [static fn (): array => $page142(), 'current.foreign_key_violations', 4],
    'current child rootpage errors' => [static fn (): array => $page142(), 'current.child_rootpage_errors', 4],
    'current parent rootpage errors' => [static fn (): array => $page142(), 'current.parent_rootpage_errors', 4],
    'current pointer conflicts include both phases' => [static fn (): array => $page142(), 'current.pointer_map_conflicts', 11],
    'current phase quick count' => [static fn (): array => $page142(), 'current.row_phases.quick_check_rootpage', 4],
    'current phase fk count' => [static fn (): array => $page142(), 'current.row_phases.foreign_key_rootpage', 4],
    'current schema main' => [static fn (): array => $page142(), 'current.schemas.0', 'main'],
    'next quick errors clear' => [static fn (): array => $page142(), 'next_counts.quick_check_errors', 0],
    'next fk clears' => [static fn (): array => $page142(), 'next_counts.foreign_key_violations', 0],
    'next pointer conflicts clear' => [static fn (): array => $page142(), 'next_counts.pointer_map_conflicts', 0],
    'delta quick clear' => [static fn (): array => $page142(), 'delta.quick_check_errors', -4],
    'delta fk clear' => [static fn (): array => $page142(), 'delta.foreign_key_violations', -4],
    'delta child root clear' => [static fn (): array => $page142(), 'delta.child_rootpage_errors', -4],
    'delta parent root clear' => [static fn (): array => $page142(), 'delta.parent_rootpage_errors', -4],
    'delta pointer clear' => [static fn (): array => $page142(), 'delta.pointer_map_conflicts', -11],
    'delta total clear' => [static fn (): array => $page142(), 'delta.total', -8],
    'delta cleared true' => [static fn (): array => $page142(), 'delta.cleared', true],
    'next ready true' => [static fn (): array => $page142(), 'next_state.ready', true],
    'next blockers empty' => [static fn (): array => $page142(), 'next_state.blocking', []],
    'row0 side current' => [static fn (): array => $page142(), 'rows.0.side', 'current'],
    'row0 phase quick' => [static fn (): array => $page142(), 'rows.0.phase', 'quick_check_rootpage'],
    'row0 quick source' => [static fn (): array => $page142(), 'rows.0.source', 'quick_check'],
    'row0 name options' => [static fn (): array => $page142(), 'rows.0.name', 'wp_options'],
    'row0 page status pointer' => [static fn (): array => $page142(), 'rows.0.page_status', 'pointer_map'],
    'row1 largest root mismatch' => [static fn (): array => $page142(), 'rows.1.kind', 'largest_root_mismatch'],
    'row2 name option names table' => [static fn (): array => $page142(), 'rows.2.name', 'wp_option_names'],
    'row4 phase fk' => [static fn (): array => $page142(), 'rows.4.phase', 'foreign_key_rootpage'],
    'row4 kind fk root' => [static fn (): array => $page142(), 'rows.4.kind', 'foreign_key_rootpage_pointer_map'],
    'row4 rowid' => [static fn (): array => $page142(), 'rows.4.rowid', 'missing-option-1'],
    'row4 child rootpage' => [static fn (): array => $page142(), 'rows.4.child_rootpage', 4],
    'row4 parent rootpage' => [static fn (): array => $page142(), 'rows.4.parent_rootpage', 6],
    'row4 child status' => [static fn (): array => $page142(), 'rows.4.child_rootpage_status', 'pointer_map'],
    'row4 parent status' => [static fn (): array => $page142(), 'rows.4.parent_rootpage_status', 'pointer_map'],
    'row7 last fk' => [static fn (): array => $page142(), 'rows.7.rowid', 'missing-option-4'],
];

$tests = [];
foreach ($cases142 as $name => [$factory, $path, $expected]) {
    $tests['pragma quickcheck rootpage foreignkey current source next142 ' . $name] = static function (TestRunner $t) use ($factory, $path, $expected, $valueAt142): void {
        $t->same($expected, $valueAt142($factory(), $path));
    };
}

$tests['pragma quickcheck rootpage foreignkey current source next142 paginates across quick and foreign rows'] = static function (TestRunner $t) use ($page142): void {
    $first = $page142(0, 6);
    $second = $page142(6, 3, $first['next']);

    $t->same(6, $first['count']);
    $t->same(['source_id' => $first['source_id'], 'offset' => 6], $first['next']);
    $t->same('foreign_key_rootpage', $first['rows'][5]['phase']);
    $t->same(6, $second['offset']);
    $t->same('missing-option-3', $second['rows'][0]['rowid']);
    $t->same(null, $second['next']);
};

$tests['pragma quickcheck rootpage foreignkey current source next142 blocks dirty next image'] = static function (TestRunner $t) use ($page142, $currentDatabase142): void {
    $result = $page142(0, 142, null, $currentDatabase142);

    $t->same('blocked', $result['status']);
    $t->same(false, $result['next_state']['ready']);
    $t->same(['quick_check'], array_slice($result['next_state']['blocking'], 0, 1));
    $t->same(4, $result['next_counts']['quick_check_errors']);
    $t->same(0, $result['next_counts']['foreign_key_violations']);
};

$tests['pragma quickcheck rootpage foreignkey current source next142 table scoped quickcheck keeps fk scope'] = static function (TestRunner $t) use ($page142): void {
    $result = $page142(0, 142, null, null, null, null, 'PRAGMA foreign_key_check(wp_options)', 'PRAGMA quick_check(wp_options)');

    $t->same('table', $result['current_source']['quick_check_scope']);
    $t->same('wp_options', $result['current_source']['quick_check_target']);
    $t->same(1, $result['current']['quick_check_errors']);
    $t->same(4, $result['current']['foreign_key_violations']);
    $t->same('quick_check_rootpage', $result['rows'][0]['phase']);
    $t->same('foreign_key_rootpage', $result['rows'][1]['phase']);
};

$tests['pragma quickcheck rootpage foreignkey current source next142 reports missing next catalog rootpages'] = static function (TestRunner $t) use ($page142, $schemas142, $missingParentCatalog142): void {
    $result = $page142(0, 142, null, null, $schemas142(2), $missingParentCatalog142);

    $t->same('blocked', $result['status']);
    $t->same(['foreign_key_check', 'foreign_key_rootpage_catalog'], $result['next_state']['blocking']);
    $t->same(2, $result['next_counts']['foreign_key_violations']);
    $t->same(2, $result['next_counts']['missing_catalog_rootpages']);
    $t->same('next', $result['rows'][9]['side']);
    $t->same('missing_catalog_rootpage', $result['rows'][9]['parent_rootpage_status']);
};

$tests['pragma quickcheck rootpage foreignkey current source next142 rejects stale source cursor'] = static function (TestRunner $t) use ($page142, $currentDatabase142): void {
    $first = $page142(0, 4);

    $t->throws(InvalidArgumentException::class, static fn () => $page142(4, 4, $first['next'], $currentDatabase142));
};

$tests['pragma quickcheck rootpage foreignkey current source next142 rejects stale offset cursor'] = static function (TestRunner $t) use ($page142): void {
    $first = $page142(0, 4);

    $t->throws(InvalidArgumentException::class, static fn () => $page142(5, 4, $first['next']));
};

$tests['pragma quickcheck rootpage foreignkey current source next142 rejects integrity_check sql'] = static function (TestRunner $t) use ($currentDatabase142, $nextDatabase142, $schemas142, $catalog142): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLitePragmaQuickcheckRootpageForeignKeyCurrentSourceNextPlan::page(
        $currentDatabase142,
        $schemas142(),
        $catalog142,
        $nextDatabase142,
        $schemas142(0),
        $catalog142,
        'PRAGMA foreign_key_check',
        'PRAGMA integrity_check',
    ));
};

return $tests;
