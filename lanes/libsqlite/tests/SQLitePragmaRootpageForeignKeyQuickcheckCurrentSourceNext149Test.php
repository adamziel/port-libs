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

$pageSize149 = 1024;
$header149 = static function (int $largestRootPage) use ($pageSize149): string {
    $page = str_repeat("\0", $pageSize149);
    $page = substr_replace($page, "SQLite format 3\0", 0, 16);
    $page = substr_replace($page, pack('n', $pageSize149), 16, 2);
    $page[18] = "\x01";
    $page[19] = "\x01";
    $page = substr_replace($page, pack('N', 9), 28, 4);
    $page = substr_replace($page, pack('N', $largestRootPage), 52, 4);
    $page = substr_replace($page, pack('N', 1), 56, 4);

    return $page;
};
$putPointer149 = static fn (string $page, int $pageNumber, int $type, int $parent): string => substr_replace($page, chr($type) . pack('N', $parent), 5 * ($pageNumber - 3), 5);
$schemaCell149 = static fn (array $values, int $rowId): string => SQLiteTableLeafCell::encode($rowId, SQLiteRecord::encode($values));
$schemaRows149 = [
    ['table', 'wp_options', 'wp_options', 4, 'CREATE TABLE wp_options(option_id integer primary key, option_name text, autoload text)'],
    ['index', 'wp_options_name', 'wp_options', 5, 'CREATE UNIQUE INDEX wp_options_name ON wp_options(option_name)'],
    ['table', 'wp_option_names', 'wp_option_names', 6, 'CREATE TABLE wp_option_names(name text primary key)'],
    ['index', 'wp_option_names_name', 'wp_option_names', 7, 'CREATE UNIQUE INDEX wp_option_names_name ON wp_option_names(name)'],
    ['table', 'wp_posts', 'wp_posts', 8, 'CREATE TABLE wp_posts(ID integer primary key)'],
];
$database149 = static function (array $entries, int $largestRootPage) use ($pageSize149, $header149, $putPointer149, $schemaCell149, $schemaRows149): string {
    $pointerMap = str_repeat("\0", $pageSize149);
    foreach ($entries as $entry) {
        $pointerMap = $putPointer149($pointerMap, $entry[0], $entry[1], $entry[2]);
    }
    $pages = [
        1 => SQLiteTableLeafPage::assemble(
            array_map(static fn (array $row, int $index): string => $schemaCell149($row, $index + 1), $schemaRows149, array_keys($schemaRows149)),
            $pageSize149,
            100,
            $header149($largestRootPage),
        ),
        2 => $pointerMap,
        3 => SQLiteTableLeafPage::assemble([], $pageSize149),
        4 => SQLiteTableLeafPage::assemble([], $pageSize149),
        5 => SQLiteIndexLeafPage::assemble([], $pageSize149),
        6 => SQLiteTableLeafPage::assemble([], $pageSize149),
        7 => SQLiteIndexLeafPage::assemble([], $pageSize149),
        8 => SQLiteTableLeafPage::assemble([], $pageSize149),
        9 => SQLiteTableLeafPage::assemble([], $pageSize149),
    ];
    ksort($pages);

    return implode('', $pages);
};

$dirty149 = $database149([
    [3, SQLitePointerMapEntry::BTREE_PAGE, 4],
    [4, SQLitePointerMapEntry::BTREE_PAGE, 3],
    [5, SQLitePointerMapEntry::ROOT_PAGE, 0],
    [6, SQLitePointerMapEntry::BTREE_PAGE, 4],
    [7, SQLitePointerMapEntry::BTREE_PAGE, 6],
    [8, SQLitePointerMapEntry::ROOT_PAGE, 0],
    [9, SQLitePointerMapEntry::ROOT_PAGE, 0],
], 4);
$clean149 = $database149([
    [3, SQLitePointerMapEntry::BTREE_PAGE, 4],
    [4, SQLitePointerMapEntry::ROOT_PAGE, 0],
    [5, SQLitePointerMapEntry::ROOT_PAGE, 0],
    [6, SQLitePointerMapEntry::ROOT_PAGE, 0],
    [7, SQLitePointerMapEntry::ROOT_PAGE, 0],
    [8, SQLitePointerMapEntry::ROOT_PAGE, 0],
    [9, SQLitePointerMapEntry::ROOT_PAGE, 0],
], 8);

$record149 = static fn (string $type, string $name, string $table, int $root): SQLiteSchemaRecord => new SQLiteSchemaRecord($type, $name, $table, $root, 'CREATE ' . strtoupper($type) . ' ' . $name, $root);
$catalog149 = new SQLiteAttachedSchemaCatalog([
    $record149('table', 'wp_options', 'wp_options', 4),
    $record149('table', 'wp_option_names', 'wp_option_names', 6),
    $record149('table', 'wp_posts', 'wp_posts', 8),
]);
$schemas149 = static function (int $missing = 4): array {
    $options = [['rowid' => 1, 'option_name' => 'siteurl']];
    for ($i = 1; $i <= $missing; $i++) {
        $options[] = ['rowid' => 'autoload-missing-' . $i, 'option_name' => 'missing_' . $i];
    }

    return [
        'main' => [
            'tables' => [
                'wp_option_names' => [['rowid' => 1, 'name' => 'siteurl']],
                'wp_options' => $options,
                'wp_posts' => [['rowid' => 1, 'ID' => 1]],
            ],
            'foreignKeys' => [
                ['id' => 149, 'table' => 'wp_options', 'parent' => 'wp_option_names', 'columns' => [
                    ['child' => 'option_name', 'parent' => 'name', 'affinity' => 'text', 'collation' => 'nocase'],
                ]],
            ],
        ],
    ];
};

$page149 = static fn (
    string $quickSql = 'PRAGMA quick_check(2)',
    int $offset = 0,
    int $limit = 149,
    ?array $cursor = null,
    ?string $nextDatabase = null,
    ?array $nextSchemas = null,
): array => SQLitePragmaQuickcheckRootpageForeignKeyCurrentSourceNextPlan::page(
    $dirty149,
    $schemas149(),
    $catalog149,
    $nextDatabase ?? $clean149,
    $nextSchemas ?? $schemas149(0),
    $catalog149,
    'PRAGMA foreign_key_check(wp_options)',
    $quickSql,
    $offset,
    $limit,
    $cursor,
);

$valueAt149 = static function (mixed $value, string $path): mixed {
    foreach (explode('.', $path) as $part) {
        $value = is_array($value) && array_key_exists($part, $value) ? $value[$part] : $value[(int) $part];
    }

    return $value;
};

$cases149 = [
    'status ok after limited quickcheck and fk repair' => [static fn (): array => $page149(), 'status', 'ok'],
    'limit default next149' => [static fn (): array => $page149(), 'limit', 149],
    'total limited current rows only' => [static fn (): array => $page149(), 'total', 6],
    'count limited current rows only' => [static fn (): array => $page149(), 'count', 6],
    'complete true' => [static fn (): array => $page149(), 'complete', true],
    'next null' => [static fn (): array => $page149(), 'next', null],
    'source id length' => [static fn (): array => ['length' => strlen($page149()['source_id'])], 'length', 64],
    'current database hash length' => [static fn (): array => ['length' => strlen($page149()['current_source']['database'])], 'length', 64],
    'next database hash length' => [static fn (): array => ['length' => strlen($page149()['next_source']['database'])], 'length', 64],
    'current quick sql normalized' => [static fn (): array => $page149(), 'current_source.quick_check_sql', 'pragma quick_check(2)'],
    'current quick limit' => [static fn (): array => $page149(), 'current_source.quick_check_limit', 2],
    'next quick limit' => [static fn (): array => $page149(), 'next_source.quick_check_limit', 2],
    'current quick scope database' => [static fn (): array => $page149(), 'current_source.quick_check_scope', 'database'],
    'current quick target null' => [static fn (): array => $page149(), 'current_source.quick_check_target', null],
    'current fk sql normalized' => [static fn (): array => $page149(), 'current_source.foreign_key_sql', 'pragma foreign_key_check(wp_options)'],
    'current limited quick rootpages' => [static fn (): array => $page149(), 'current.quick_check_rootpages', 2],
    'current limited quick errors' => [static fn (): array => $page149(), 'current.quick_check_errors', 2],
    'current fk violations preserved' => [static fn (): array => $page149(), 'current.foreign_key_violations', 4],
    'current child rootpage errors preserved' => [static fn (): array => $page149(), 'current.child_rootpage_errors', 4],
    'current parent rootpage errors preserved' => [static fn (): array => $page149(), 'current.parent_rootpage_errors', 4],
    'current pointer conflicts limited to quick plus fk' => [static fn (): array => $page149(), 'current.pointer_map_conflicts', 9],
    'current phase quick count limited' => [static fn (): array => $page149(), 'current.row_phases.quick_check_rootpage', 2],
    'current phase fk count preserved' => [static fn (): array => $page149(), 'current.row_phases.foreign_key_rootpage', 4],
    'current schema main' => [static fn (): array => $page149(), 'current.schemas.0', 'main'],
    'next quick errors clear' => [static fn (): array => $page149(), 'next_counts.quick_check_errors', 0],
    'next fk violations clear' => [static fn (): array => $page149(), 'next_counts.foreign_key_violations', 0],
    'next pointer conflicts clear' => [static fn (): array => $page149(), 'next_counts.pointer_map_conflicts', 0],
    'delta quick limited clear' => [static fn (): array => $page149(), 'delta.quick_check_errors', -2],
    'delta fk clear' => [static fn (): array => $page149(), 'delta.foreign_key_violations', -4],
    'delta child clear' => [static fn (): array => $page149(), 'delta.child_rootpage_errors', -4],
    'delta parent clear' => [static fn (): array => $page149(), 'delta.parent_rootpage_errors', -4],
    'delta pointer clear' => [static fn (): array => $page149(), 'delta.pointer_map_conflicts', -9],
    'delta total clear' => [static fn (): array => $page149(), 'delta.total', -6],
    'delta cleared true' => [static fn (): array => $page149(), 'delta.cleared', true],
    'next state ready' => [static fn (): array => $page149(), 'next_state.ready', true],
    'next state blockers empty' => [static fn (): array => $page149(), 'next_state.blocking', []],
    'row0 quick phase' => [static fn (): array => $page149(), 'rows.0.phase', 'quick_check_rootpage'],
    'row0 quick name options' => [static fn (): array => $page149(), 'rows.0.name', 'wp_options'],
    'row1 quick kind largest root mismatch' => [static fn (): array => $page149(), 'rows.1.kind', 'largest_root_mismatch'],
    'row2 first fk phase' => [static fn (): array => $page149(), 'rows.2.phase', 'foreign_key_rootpage'],
    'row2 first fk rowid' => [static fn (): array => $page149(), 'rows.2.rowid', 'autoload-missing-1'],
    'row2 child rootpage' => [static fn (): array => $page149(), 'rows.2.child_rootpage', 4],
    'row2 parent rootpage' => [static fn (): array => $page149(), 'rows.2.parent_rootpage', 6],
    'row5 last fk rowid' => [static fn (): array => $page149(), 'rows.5.rowid', 'autoload-missing-4'],
    'equals form quick sql normalized' => [static fn (): array => $page149('PRAGMA quick_check = 3'), 'current_source.quick_check_sql', 'pragma quick_check = 3'],
    'equals form quick limit' => [static fn (): array => $page149('PRAGMA quick_check = 3'), 'current_source.quick_check_limit', 3],
    'equals form quick errors' => [static fn (): array => $page149('PRAGMA quick_check = 3'), 'current.quick_check_errors', 3],
    'equals form total current rows' => [static fn (): array => $page149('PRAGMA quick_check = 3'), 'total', 7],
    'unlimited quick errors still available' => [static fn (): array => $page149('PRAGMA quick_check'), 'current.quick_check_errors', 4],
    'unlimited quick source limit null' => [static fn (): array => $page149('PRAGMA quick_check'), 'current_source.quick_check_limit', null],
];

$tests = [];
foreach ($cases149 as $name => [$factory, $path, $expected]) {
    $tests['pragma rootpage foreignkey quickcheck current source next149 ' . $name] = static function (TestRunner $t) use ($factory, $path, $expected, $valueAt149): void {
        $t->same($expected, $valueAt149($factory(), $path));
    };
}

$tests['pragma rootpage foreignkey quickcheck current source next149 paginates from limited quick rows into fk rows'] = static function (TestRunner $t) use ($page149): void {
    $first = $page149('PRAGMA quick_check(2)', 0, 3);
    $second = $page149('PRAGMA quick_check(2)', 3, 3, $first['next']);

    $t->same(3, $first['count']);
    $t->same(['source_id' => $first['source_id'], 'offset' => 3], $first['next']);
    $t->same('foreign_key_rootpage', $first['rows'][2]['phase']);
    $t->same(3, $second['offset']);
    $t->same('autoload-missing-2', $second['rows'][0]['rowid']);
    $t->same(null, $second['next']);
};

$tests['pragma rootpage foreignkey quickcheck current source next149 source changes when numeric limit changes'] = static function (TestRunner $t) use ($page149): void {
    $two = $page149('PRAGMA quick_check(2)');
    $three = $page149('PRAGMA quick_check(3)');

    $t->same(true, $two['source_id'] !== $three['source_id']);
    $t->same(2, $two['current_source']['quick_check_limit']);
    $t->same(3, $three['current_source']['quick_check_limit']);
};

$tests['pragma rootpage foreignkey quickcheck current source next149 rejects stale quickcheck limit cursor'] = static function (TestRunner $t) use ($page149): void {
    $first = $page149('PRAGMA quick_check(2)', 0, 3);

    $t->throws(InvalidArgumentException::class, static fn () => $page149('PRAGMA quick_check(3)', 3, 3, $first['next']));
};

$tests['pragma rootpage foreignkey quickcheck current source next149 blocks next remaining fk rows after quick limit clears'] = static function (TestRunner $t) use ($page149, $schemas149): void {
    $result = $page149('PRAGMA quick_check(2)', 0, 149, null, null, $schemas149(1));

    $t->same('blocked', $result['status']);
    $t->same(['foreign_key_check'], $result['next_state']['blocking']);
    $t->same(0, $result['next_counts']['quick_check_errors']);
    $t->same(1, $result['next_counts']['foreign_key_violations']);
    $t->same('next', $result['rows'][6]['side']);
};

return $tests;
