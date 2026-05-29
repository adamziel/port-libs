<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteAttachedSchemaCatalog;
use PortLibs\LibSqlite\SQLitePointerMapEntry;
use PortLibs\LibSqlite\SQLitePragmaRootpagePointerMapForeignKeyCurrentSourceNext;
use PortLibs\LibSqlite\SQLiteRecord;
use PortLibs\LibSqlite\SQLiteSchemaRecord;
use PortLibs\LibSqlite\SQLiteTableLeafCell;
use PortLibs\LibSqlite\SQLiteTableLeafPage;

$pageSize = 1024;

$headerPage = static function () use ($pageSize): string {
    $page = str_repeat("\0", $pageSize);
    $page = substr_replace($page, "SQLite format 3\0", 0, 16);
    $page = substr_replace($page, pack('n', $pageSize), 16, 2);
    $page[18] = "\x01";
    $page[19] = "\x01";
    $page = substr_replace($page, pack('N', 8), 28, 4);
    $page = substr_replace($page, pack('N', 8), 52, 4);
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

$database = static function (bool $archivePointerConflict = true) use ($headerPage, $putPointerMapEntry, $schemaCell, $pageSize): string {
    $schemaRows = [
        ['table', 'wp_options', 'wp_options', 4, 'CREATE TABLE wp_options(option_id INTEGER PRIMARY KEY, option_name TEXT)'],
        ['table', 'wp_option_names', 'wp_option_names', 5, 'CREATE TABLE wp_option_names(name TEXT PRIMARY KEY)'],
        ['table', 'wp_options', 'wp_options', 7, 'CREATE TABLE wp_options(option_id INTEGER PRIMARY KEY, option_name TEXT)'],
        ['table', 'wp_option_names', 'wp_option_names', 8, 'CREATE TABLE wp_option_names(name TEXT PRIMARY KEY)'],
    ];

    $pointerMap = str_repeat("\0", $pageSize);
    $pointerMap = $putPointerMapEntry($pointerMap, 3, SQLitePointerMapEntry::BTREE_PAGE, 4);
    $pointerMap = $putPointerMapEntry($pointerMap, 4, SQLitePointerMapEntry::ROOT_PAGE, 0);
    $pointerMap = $putPointerMapEntry($pointerMap, 5, SQLitePointerMapEntry::ROOT_PAGE, 0);
    $pointerMap = $putPointerMapEntry($pointerMap, 6, SQLitePointerMapEntry::BTREE_PAGE, 7);
    $pointerMap = $putPointerMapEntry($pointerMap, 7, $archivePointerConflict ? SQLitePointerMapEntry::BTREE_PAGE : SQLitePointerMapEntry::ROOT_PAGE, $archivePointerConflict ? 8 : 0);
    $pointerMap = $putPointerMapEntry($pointerMap, 8, SQLitePointerMapEntry::ROOT_PAGE, 0);

    $pages = [
        1 => SQLiteTableLeafPage::assemble(
            array_map(static fn (array $row, int $index): string => $schemaCell($row, $index + 1), $schemaRows, array_keys($schemaRows)),
            $pageSize,
            100,
            $headerPage(),
        ),
        2 => $pointerMap,
        3 => SQLiteTableLeafPage::assemble([], $pageSize),
        4 => SQLiteTableLeafPage::assemble([], $pageSize),
        5 => SQLiteTableLeafPage::assemble([], $pageSize),
        6 => SQLiteTableLeafPage::assemble([], $pageSize),
        7 => SQLiteTableLeafPage::assemble([], $pageSize),
        8 => SQLiteTableLeafPage::assemble([], $pageSize),
    ];
    ksort($pages);

    return implode('', $pages);
};

$record = static fn (string $name, int $root): SQLiteSchemaRecord => new SQLiteSchemaRecord(
    'table',
    $name,
    $name,
    $root,
    'CREATE TABLE ' . $name,
    $root,
);

$catalog = static function () use ($record): SQLiteAttachedSchemaCatalog {
    $catalog = new SQLiteAttachedSchemaCatalog([
        $record('wp_options', 4),
        $record('wp_option_names', 5),
    ]);
    $catalog->attach('archive', '/tmp/archive.sqlite', [
        $record('wp_options', 7),
        $record('wp_option_names', 8),
    ]);

    return $catalog;
};

$schemas = static function (int $archiveMisses = 3): array {
    $archiveRows = [
        ['rowid' => 'archive-siteurl', 'option_id' => 1, 'option_name' => 'legacy_siteurl'],
    ];
    for ($i = 1; $i <= $archiveMisses; $i++) {
        $archiveRows[] = ['rowid' => 'archive-missing-' . $i, 'option_id' => 100 + $i, 'option_name' => 'missing_' . $i];
    }

    return [
        'main' => [
            'tables' => [
                'wp_option_names' => [['rowid' => 1, 'name' => 'siteurl']],
                'wp_options' => [
                    ['rowid' => 'main-siteurl', 'option_id' => 1, 'option_name' => 'siteurl'],
                    ['rowid' => 'main-missing', 'option_id' => 2, 'option_name' => 'missing_main'],
                ],
            ],
            'foreignKeys' => [
                ['id' => 1, 'table' => 'wp_options', 'parent' => 'wp_option_names', 'columns' => [['child' => 'option_name', 'parent' => 'name', 'affinity' => 'text', 'collation' => 'nocase']]],
            ],
        ],
        'archive' => [
            'tables' => [
                'wp_option_names' => [['rowid' => 1, 'name' => 'legacy_siteurl']],
                'wp_options' => $archiveRows,
            ],
            'foreignKeys' => [
                ['id' => 2, 'table' => 'wp_options', 'parent' => 'wp_option_names', 'columns' => [['child' => 'option_name', 'parent' => 'name', 'affinity' => 'text', 'collation' => 'nocase']]],
            ],
        ],
    ];
};

$page = static fn (int $offset = 0, int $limit = 127, ?array $cursor = null, string $sql = 'PRAGMA foreign_key_check'): array => SQLitePragmaRootpagePointerMapForeignKeyCurrentSourceNext::page(
    $database(true),
    $schemas(),
    $catalog(),
    $sql,
    $offset,
    $limit,
    $cursor,
);
$cleanPage = static fn (): array => SQLitePragmaRootpagePointerMapForeignKeyCurrentSourceNext::page($database(false), $schemas(), $catalog());
$mutatedSchemaPage = static fn (): array => SQLitePragmaRootpagePointerMapForeignKeyCurrentSourceNext::page($database(true), $schemas(4), $catalog());
$mainOnly = static fn (): array => SQLitePragmaRootpagePointerMapForeignKeyCurrentSourceNext::page($database(true), $schemas(), $catalog(), "SELECT * FROM pragma_foreign_key_check('main.wp_options')");
$archiveOnly = static fn (int $offset = 0, int $limit = 127, ?array $cursor = null): array => SQLitePragmaRootpagePointerMapForeignKeyCurrentSourceNext::page($database(true), $schemas(), $catalog(), "SELECT * FROM pragma_foreign_key_check('archive.wp_options')", $offset, $limit, $cursor);

$valueAt = static function (mixed $value, string $path): mixed {
    foreach (explode('.', $path) as $part) {
        if (is_array($value) && array_key_exists($part, $value)) {
            $value = $value[$part];
            continue;
        }
        if ($part === 'count') {
            $value = count($value);
            continue;
        }
        $value = is_numeric($part) ? $value[(int) $part] : $value[$part];
    }

    return $value;
};

$cases = [
    'status blocked' => [$page, 'status', 'blocked'],
    'limit is next127 default' => [$page, 'limit', 127],
    'total violations' => [$page, 'total', 1],
    'count violations' => [$page, 'count', 1],
    'complete true' => [$page, 'complete', true],
    'blocking fk first' => [$page, 'next_state.blocking.0', 'foreign_key_check'],
    'blocking count one for default main' => [$page, 'next_state.blocking.count', 1],
    'current fk violations' => [$page, 'current.foreign_key_violations', 1],
    'current child rootpage errors zero' => [$page, 'current.child_rootpage_errors', 0],
    'current parent rootpage errors zero' => [$page, 'current.parent_rootpage_errors', 0],
    'current pointer conflicts zero for exact main rootpage' => [$page, 'current.pointer_map_conflicts', 0],
    'schemas include main' => [$page, 'current.schemas.0', 'main'],
    'schemas count one for default main' => [$page, 'current.schemas.count', 1],
    'row0 main schema' => [$page, 'rows.0.schema', 'main'],
    'row0 main rowid' => [$page, 'rows.0.rowid', 'main-missing'],
    'row0 main child rootpage exact' => [$page, 'rows.0.child_rootpage', 4],
    'row0 main parent rootpage exact' => [$page, 'rows.0.parent_rootpage', 5],
    'row0 main child status ok despite duplicate name' => [$page, 'rows.0.child_rootpage_status', 'ok'],
    'row0 main child pointer root page' => [$page, 'rows.0.child_pointer_map_type', 'root-page'],
    'row0 main child pointer parent zero' => [$page, 'rows.0.child_pointer_map_parent', 0],
    'row0 main parent status ok despite duplicate name' => [$page, 'rows.0.parent_rootpage_status', 'ok'],
    'row0 main parent pointer root page' => [$page, 'rows.0.parent_pointer_map_type', 'root-page'],
    'main only total' => [$mainOnly, 'total', 1],
    'main only child rootpage' => [$mainOnly, 'rows.0.child_rootpage', 4],
    'main only child status exact rootpage' => [$mainOnly, 'rows.0.child_rootpage_status', 'ok'],
    'clean status only fk blocked' => [$cleanPage, 'status', 'blocked'],
    'clean pointer conflicts zero' => [$cleanPage, 'current.pointer_map_conflicts', 0],
    'clean child errors zero' => [$cleanPage, 'current.child_rootpage_errors', 0],
    'clean blocker only fk' => [$cleanPage, 'next_state.blocking.count', 1],
    'archive only total' => [$archiveOnly, 'total', 3],
    'archive only first schema' => [$archiveOnly, 'rows.0.schema', 'archive'],
    'archive only current schema' => [$archiveOnly, 'current.schemas.0', 'archive'],
    'archive only blocker fk' => [$archiveOnly, 'next_state.blocking.0', 'foreign_key_check'],
    'archive only blocker pointer map' => [$archiveOnly, 'next_state.blocking.1', 'rootpage_pointer_map'],
    'archive only child errors three' => [$archiveOnly, 'current.child_rootpage_errors', 3],
    'archive only pointer conflicts three' => [$archiveOnly, 'current.pointer_map_conflicts', 3],
    'archive only first child rootpage' => [$archiveOnly, 'rows.0.child_rootpage', 7],
    'archive only first parent rootpage' => [$archiveOnly, 'rows.0.parent_rootpage', 8],
    'archive only first child status' => [$archiveOnly, 'rows.0.child_rootpage_status', 'pointer_map'],
    'archive only first child pointer btree' => [$archiveOnly, 'rows.0.child_pointer_map_type', 'btree-page'],
    'archive only first child pointer parent eight' => [$archiveOnly, 'rows.0.child_pointer_map_parent', 8],
    'archive only parent status ok' => [$archiveOnly, 'rows.0.parent_rootpage_status', 'ok'],
    'archive only message keeps archive schema' => [$archiveOnly, 'rows.0.message', 'foreign key mismatch in archive.wp_options rowid archive-missing-1 references wp_option_names fkid 2 (child pointer_map pointer-map btree-page parent 8 page 2; parent ok pointer-map root-page parent 0 page 2)'],
    'archive only final rowid' => [$archiveOnly, 'rows.2.rowid', 'archive-missing-3'],
    'source id length' => [static fn (): array => ['length' => strlen($page()['source_id'])], 'length', 64],
    'database hash length' => [static fn (): array => ['length' => strlen($page()['current_source']['database'])], 'length', 64],
    'catalog hash length' => [static fn (): array => ['length' => strlen($page()['current_source']['catalog'])], 'length', 64],
    'schemas hash length' => [static fn (): array => ['length' => strlen($page()['current_source']['schemas'])], 'length', 64],
    'foreign key sql normalized' => [$page, 'current_source.foreign_key_sql', 'pragma foreign_key_check'],
    'mutated schemas keeps default main total' => [$mutatedSchemaPage, 'total', 1],
    'mutated schemas keeps default main row' => [$mutatedSchemaPage, 'rows.0.rowid', 'main-missing'],
];

$tests = [];
foreach ($cases as $name => [$callback, $path, $expected]) {
    $tests['pragma rootpage foreignkey pointermap current source next127 ' . $name] = static function (TestRunner $t) use ($callback, $valueAt, $path, $expected): void {
        $t->same($expected, $valueAt($callback(), $path));
    };
}

$tests['pragma rootpage foreignkey pointermap current source next127 paginates duplicate names with source cursor'] = static function (TestRunner $t) use ($archiveOnly): void {
    $first = $archiveOnly(0, 2);
    $second = $archiveOnly(2, 2, ['source_id' => $first['source_id'], 'next_offset' => 2]);

    $t->same(2, $first['count']);
    $t->same(['source_id' => $first['source_id'], 'offset' => 2], $first['next']);
    $t->same('archive', $second['rows'][0]['schema']);
    $t->same('archive-missing-3', $second['rows'][0]['rowid']);
    $t->same(7, $second['rows'][0]['child_rootpage']);
    $t->same(null, $second['next']);
};

$tests['pragma rootpage foreignkey pointermap current source next127 source changes with duplicate-name schema rows'] = static function (TestRunner $t) use ($page, $mutatedSchemaPage): void {
    $first = $page();
    $second = $mutatedSchemaPage();

    $t->same(true, $first['source_id'] !== $second['source_id']);
    $t->same(true, $first['current_source']['schemas'] !== $second['current_source']['schemas']);
    $t->same($first['current_source']['database'], $second['current_source']['database']);
};

$tests['pragma rootpage foreignkey pointermap current source next127 rejects stale duplicate-name cursor'] = static function (TestRunner $t) use ($page, $database, $schemas, $catalog): void {
    $first = $page(0, 2);
    $t->throws(InvalidArgumentException::class, static fn () => SQLitePragmaRootpagePointerMapForeignKeyCurrentSourceNext::page($database(false), $schemas(), $catalog(), 'PRAGMA foreign_key_check', 2, 2, ['source_id' => $first['source_id'], 'next_offset' => 2]));
};

return $tests;
