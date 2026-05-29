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

$pageSize145 = 1024;
$header145 = static function (int $largestRootPage) use ($pageSize145): string {
    $page = str_repeat("\0", $pageSize145);
    $page = substr_replace($page, "SQLite format 3\0", 0, 16);
    $page = substr_replace($page, pack('n', $pageSize145), 16, 2);
    $page[18] = "\x01";
    $page[19] = "\x01";
    $page = substr_replace($page, pack('N', 9), 28, 4);
    $page = substr_replace($page, pack('N', $largestRootPage), 52, 4);
    $page = substr_replace($page, pack('N', 1), 56, 4);

    return $page;
};
$putPointer145 = static fn (string $page, int $pageNumber, int $type, int $parent): string => substr_replace($page, chr($type) . pack('N', $parent), 5 * ($pageNumber - 3), 5);
$schemaCell145 = static fn (array $values, int $rowId): string => SQLiteTableLeafCell::encode($rowId, SQLiteRecord::encode($values));
$schemaRows145 = [
    ['table', 'wp_options', 'wp_options', 4, 'CREATE TABLE wp_options(option_id integer primary key, option_name text)'],
    ['index', 'wp_options_name', 'wp_options', 5, 'CREATE INDEX wp_options_name ON wp_options(option_name)'],
    ['table', 'wp_archive_option_names', 'wp_archive_option_names', 6, 'CREATE TABLE wp_archive_option_names(name text primary key)'],
    ['index', 'wp_archive_option_names_name', 'wp_archive_option_names', 7, 'CREATE INDEX wp_archive_option_names_name ON wp_archive_option_names(name)'],
    ['table', 'wp_archive_options', 'wp_archive_options', 8, 'CREATE TABLE wp_archive_options(option_id integer primary key, option_name text)'],
];
$database145 = static function (array $entries, int $largestRootPage) use ($pageSize145, $header145, $putPointer145, $schemaCell145, $schemaRows145): string {
    $pointerMap = str_repeat("\0", $pageSize145);
    foreach ($entries as $entry) {
        $pointerMap = $putPointer145($pointerMap, $entry[0], $entry[1], $entry[2]);
    }
    $pages = [
        1 => SQLiteTableLeafPage::assemble(
            array_map(static fn (array $row, int $index): string => $schemaCell145($row, $index + 1), $schemaRows145, array_keys($schemaRows145)),
            $pageSize145,
            100,
            $header145($largestRootPage),
        ),
        2 => $pointerMap,
        3 => SQLiteTableLeafPage::assemble([], $pageSize145),
        4 => SQLiteTableLeafPage::assemble([], $pageSize145),
        5 => SQLiteIndexLeafPage::assemble([], $pageSize145),
        6 => SQLiteTableLeafPage::assemble([], $pageSize145),
        7 => SQLiteIndexLeafPage::assemble([], $pageSize145),
        8 => SQLiteTableLeafPage::assemble([], $pageSize145),
        9 => SQLiteTableLeafPage::assemble([], $pageSize145),
    ];
    ksort($pages);

    return implode('', $pages);
};

$dirty145 = $database145([
    [3, SQLitePointerMapEntry::BTREE_PAGE, 4],
    [4, SQLitePointerMapEntry::ROOT_PAGE, 0],
    [5, SQLitePointerMapEntry::ROOT_PAGE, 0],
    [6, SQLitePointerMapEntry::BTREE_PAGE, 4],
    [7, SQLitePointerMapEntry::ROOT_PAGE, 0],
    [8, SQLitePointerMapEntry::BTREE_PAGE, 6],
    [9, SQLitePointerMapEntry::ROOT_PAGE, 0],
], 5);
$clean145 = $database145([
    [3, SQLitePointerMapEntry::BTREE_PAGE, 4],
    [4, SQLitePointerMapEntry::ROOT_PAGE, 0],
    [5, SQLitePointerMapEntry::ROOT_PAGE, 0],
    [6, SQLitePointerMapEntry::ROOT_PAGE, 0],
    [7, SQLitePointerMapEntry::ROOT_PAGE, 0],
    [8, SQLitePointerMapEntry::ROOT_PAGE, 0],
    [9, SQLitePointerMapEntry::ROOT_PAGE, 0],
], 8);

$record145 = static fn (string $type, string $name, string $table, int $root): SQLiteSchemaRecord => new SQLiteSchemaRecord(
    $type,
    $name,
    $table,
    $root,
    'CREATE ' . strtoupper($type) . ' ' . $name,
    $root,
);
$catalog145 = new SQLiteAttachedSchemaCatalog([
    $record145('table', 'wp_options', 'wp_options', 4),
]);
$catalog145->attach('archive', '/tmp/wp-archive.sqlite', [
    $record145('table', 'wp_archive_option_names', 'wp_archive_option_names', 6),
    $record145('table', 'wp_archive_options', 'wp_archive_options', 8),
]);

$schemas145 = static function (int $missing = 3): array {
    $options = [['rowid' => 1, 'option_name' => 'legacy_siteurl']];
    for ($i = 1; $i <= $missing; $i++) {
        $options[] = ['rowid' => 'archive-missing-' . $i, 'option_name' => 'missing_' . $i];
    }

    return [
        'main' => [
            'tables' => ['wp_options' => [['rowid' => 1, 'option_name' => 'siteurl']]],
            'foreignKeys' => [],
        ],
        'archive' => [
            'tables' => [
                'wp_archive_option_names' => [['rowid' => 1, 'name' => 'legacy_siteurl']],
                'wp_archive_options' => $options,
            ],
            'foreignKeys' => [
                ['id' => 145, 'table' => 'wp_archive_options', 'parent' => 'wp_archive_option_names', 'columns' => [
                    ['child' => 'option_name', 'parent' => 'name', 'affinity' => 'text', 'collation' => 'nocase'],
                ]],
            ],
        ],
    ];
};

$page145 = static fn (
    int $offset = 0,
    int $limit = 145,
    ?array $cursor = null,
    ?string $nextDatabase = null,
    ?array $nextSchemas = null,
    string $foreignKeySql = "SELECT * FROM archive.pragma_foreign_key_check('wp_archive_options')",
    string $quickCheckSql = 'PRAGMA quick_check(wp_archive_options)',
): array => SQLitePragmaQuickcheckRootpageForeignKeyCurrentSourceNextPlan::page(
    $dirty145,
    $schemas145(),
    $catalog145,
    $nextDatabase ?? $clean145,
    $nextSchemas ?? $schemas145(0),
    $catalog145,
    $foreignKeySql,
    $quickCheckSql,
    $offset,
    $limit,
    $cursor,
);

$valueAt145 = static function (mixed $value, string $path): mixed {
    foreach (explode('.', $path) as $part) {
        $value = is_array($value) && array_key_exists($part, $value) ? $value[$part] : $value[(int) $part];
    }

    return $value;
};

$cases145 = [
    'status ok after attached archive repair' => [static fn (): array => $page145(), 'status', 'ok'],
    'total current archive rows only' => [static fn (): array => $page145(), 'total', 4],
    'current quick errors' => [static fn (): array => $page145(), 'current.quick_check_errors', 1],
    'current fk violations' => [static fn (): array => $page145(), 'current.foreign_key_violations', 3],
    'current pointer conflicts' => [static fn (): array => $page145(), 'current.pointer_map_conflicts', 7],
    'current child errors' => [static fn (): array => $page145(), 'current.child_rootpage_errors', 3],
    'current parent errors' => [static fn (): array => $page145(), 'current.parent_rootpage_errors', 3],
    'current schema archive' => [static fn (): array => $page145(), 'current.schemas.0', 'archive'],
    'next quick clears' => [static fn (): array => $page145(), 'next_counts.quick_check_errors', 0],
    'next fk clears' => [static fn (): array => $page145(), 'next_counts.foreign_key_violations', 0],
    'next ready true' => [static fn (): array => $page145(), 'next_state.ready', true],
    'delta cleared true' => [static fn (): array => $page145(), 'delta.cleared', true],
    'delta total clears four rows' => [static fn (): array => $page145(), 'delta.total', -4],
    'fk sql normalized table valued archive' => [static fn (): array => $page145(), 'current_source.foreign_key_sql', "select * from archive.pragma_foreign_key_check('wp_archive_options')"],
    'quick target archive table' => [static fn (): array => $page145(), 'current_source.quick_check_target', 'wp_archive_options'],
    'row0 quick table scoped archive' => [static fn (): array => $page145(), 'rows.0.name', 'wp_archive_options'],
    'row0 quick source' => [static fn (): array => $page145(), 'rows.0.source', 'quick_check'],
    'row1 fk phase' => [static fn (): array => $page145(), 'rows.1.phase', 'foreign_key_rootpage'],
    'row1 pragma schema archive' => [static fn (): array => $page145(), 'rows.1.pragma_schema', 'archive'],
    'row1 target schema archive' => [static fn (): array => $page145(), 'rows.1.target_schema', 'archive'],
    'row1 target table' => [static fn (): array => $page145(), 'rows.1.target', 'wp_archive_options'],
    'row1 target source pragma schema' => [static fn (): array => $page145(), 'rows.1.target_source', 'pragma-schema'],
    'row1 rowid' => [static fn (): array => $page145(), 'rows.1.rowid', 'archive-missing-1'],
    'row1 child rootpage' => [static fn (): array => $page145(), 'rows.1.child_rootpage', 8],
    'row1 parent rootpage' => [static fn (): array => $page145(), 'rows.1.parent_rootpage', 6],
    'row1 child status pointer' => [static fn (): array => $page145(), 'rows.1.child_rootpage_status', 'pointer_map'],
    'row1 parent status pointer' => [static fn (): array => $page145(), 'rows.1.parent_rootpage_status', 'pointer_map'],
    'row3 last archive violation' => [static fn (): array => $page145(), 'rows.3.rowid', 'archive-missing-3'],
];

$tests = [];
foreach ($cases145 as $name => [$factory, $path, $expected]) {
    $tests['pragma quickcheck foreignkey rootpage current source next145 ' . $name] = static function (TestRunner $t) use ($factory, $path, $expected, $valueAt145): void {
        $t->same($expected, $valueAt145($factory(), $path));
    };
}

$tests['pragma quickcheck foreignkey rootpage current source next145 paginates archive table valued rows'] = static function (TestRunner $t) use ($page145): void {
    $first = $page145(0, 2);
    $second = $page145(2, 2, $first['next']);

    $t->same(2, $first['count']);
    $t->same(['source_id' => $first['source_id'], 'offset' => 2], $first['next']);
    $t->same('foreign_key_rootpage', $first['rows'][1]['phase']);
    $t->same(2, $second['offset']);
    $t->same('archive-missing-2', $second['rows'][0]['rowid']);
    $t->same(null, $second['next']);
};

$tests['pragma quickcheck foreignkey rootpage current source next145 blocks attached next violations'] = static function (TestRunner $t) use ($page145, $schemas145): void {
    $result = $page145(0, 145, null, null, $schemas145(1));

    $t->same('blocked', $result['status']);
    $t->same(['foreign_key_check'], $result['next_state']['blocking']);
    $t->same(1, $result['next_counts']['foreign_key_violations']);
    $t->same('archive', $result['rows'][4]['schema']);
};

$tests['pragma quickcheck foreignkey rootpage current source next145 accepts qualified target string'] = static function (TestRunner $t) use ($page145): void {
    $result = $page145(0, 145, null, null, null, "SELECT * FROM pragma_foreign_key_check('archive.wp_archive_options')");

    $t->same('qualified-target', $result['rows'][1]['target_source']);
    $t->same('archive', $result['rows'][1]['target_schema']);
    $t->same('wp_archive_options', $result['rows'][1]['target']);
};

$tests['pragma quickcheck foreignkey rootpage current source next145 rejects mismatched pragma schema and target'] = static function (TestRunner $t) use ($page145): void {
    $t->throws(InvalidArgumentException::class, static fn () => $page145(0, 145, null, null, null, "SELECT * FROM main.pragma_foreign_key_check('archive.wp_archive_options')"));
};

$tests['pragma quickcheck foreignkey rootpage current source next145 rejects stale attached cursor'] = static function (TestRunner $t) use ($page145, $dirty145): void {
    $first = $page145(0, 3);

    $t->throws(InvalidArgumentException::class, static fn () => $page145(3, 3, $first['next'], $dirty145));
};

$tests['pragma quickcheck foreignkey rootpage current source next145 rejects stale offset cursor'] = static function (TestRunner $t) use ($page145): void {
    $first = $page145(0, 3);

    $t->throws(InvalidArgumentException::class, static fn () => $page145(4, 3, $first['next']));
};

return $tests;
