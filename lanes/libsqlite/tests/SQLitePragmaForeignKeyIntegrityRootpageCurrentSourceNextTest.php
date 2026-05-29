<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteAttachedSchemaCatalog;
use PortLibs\LibSqlite\SQLiteIndexLeafPage;
use PortLibs\LibSqlite\SQLitePointerMapEntry;
use PortLibs\LibSqlite\SQLitePragmaForeignKeyIntegrityRootpageCurrentSourceNextPlan;
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

$schemaDatabase = static function (array $schemaRows, array $pointerMapEntries, array $pageImages = []) use ($headerPage, $putPointerMapEntry, $schemaCell, $pageSize): string {
    $pageCount = 9;
    $pages = [
        1 => SQLiteTableLeafPage::assemble(
            array_map(static fn (array $row, int $index): string => $schemaCell($row, $index + 1), $schemaRows, array_keys($schemaRows)),
            $pageSize,
            100,
            $headerPage($pageCount, $pageCount),
        ),
    ];

    $pointerMap = str_repeat("\0", $pageSize);
    foreach ($pointerMapEntries as $entry) {
        $pointerMap = $putPointerMapEntry($pointerMap, $entry[0], $entry[1], $entry[2]);
    }
    $pages[2] = $pointerMap;

    for ($pageNumber = 3; $pageNumber <= $pageCount; $pageNumber++) {
        $pages[$pageNumber] = $pageImages[$pageNumber] ?? SQLiteTableLeafPage::assemble([], $pageSize);
    }
    ksort($pages);

    return implode('', $pages);
};

$schemaRows = [
    ['table', 'wp_options', 'wp_options', 4, 'CREATE TABLE wp_options(option_id integer primary key, option_name text, autoload text)'],
    ['index', 'wp_options_name', 'wp_options', 5, 'CREATE UNIQUE INDEX wp_options_name ON wp_options(option_name)'],
    ['table', 'wp_option_names', 'wp_option_names', 6, 'CREATE TABLE wp_option_names(name text primary key)'],
    ['index', 'wp_option_names_name', 'wp_option_names', 7, 'CREATE UNIQUE INDEX wp_option_names_name ON wp_option_names(name)'],
];

$currentDatabase = $schemaDatabase($schemaRows, [
    [3, SQLitePointerMapEntry::BTREE_PAGE, 4],
    [4, SQLitePointerMapEntry::BTREE_PAGE, 3],
    [5, SQLitePointerMapEntry::ROOT_PAGE, 0],
    [6, SQLitePointerMapEntry::BTREE_PAGE, 4],
    [7, SQLitePointerMapEntry::BTREE_PAGE, 6],
], [
    5 => SQLiteIndexLeafPage::assemble([], $pageSize),
    7 => SQLiteIndexLeafPage::assemble([], $pageSize),
]);

$nextDatabase = $schemaDatabase($schemaRows, [
    [3, SQLitePointerMapEntry::BTREE_PAGE, 4],
    [4, SQLitePointerMapEntry::ROOT_PAGE, 0],
    [5, SQLitePointerMapEntry::ROOT_PAGE, 0],
    [6, SQLitePointerMapEntry::ROOT_PAGE, 0],
    [7, SQLitePointerMapEntry::ROOT_PAGE, 0],
], [
    5 => SQLiteIndexLeafPage::assemble([], $pageSize),
    7 => SQLiteIndexLeafPage::assemble([], $pageSize),
]);

$record = static fn (string $type, string $name, string $table, int $root): SQLiteSchemaRecord => new SQLiteSchemaRecord(
    $type,
    $name,
    $table,
    $root,
    'CREATE ' . strtoupper($type) . ' ' . $name,
    $root,
);

$currentCatalog = new SQLiteAttachedSchemaCatalog([
    $record('table', 'wp_options', 'wp_options', 4),
    $record('table', 'wp_option_names', 'wp_option_names', 6),
]);
$nextCatalog = new SQLiteAttachedSchemaCatalog([
    $record('table', 'wp_options', 'wp_options', 4),
    $record('table', 'wp_option_names', 'wp_option_names', 6),
]);
$missingParentCatalog = new SQLiteAttachedSchemaCatalog([
    $record('table', 'wp_options', 'wp_options', 4),
]);

$schemas = static function (int $missing = 4): array {
    $options = [['rowid' => 1, 'option_name' => 'siteurl']];
    for ($i = 1; $i <= $missing; $i++) {
        $options[] = ['rowid' => 'autoload-missing-' . $i, 'option_name' => 'missing_' . $i];
    }

    return [
        'main' => [
            'tables' => [
                'wp_option_names' => [['rowid' => 1, 'name' => 'siteurl']],
                'wp_options' => $options,
            ],
            'foreignKeys' => [
                ['id' => 140, 'table' => 'wp_options', 'parent' => 'wp_option_names', 'columns' => [
                    ['child' => 'option_name', 'parent' => 'name', 'affinity' => 'text', 'collation' => 'nocase'],
                ]],
            ],
        ],
    ];
};

$cleanSchemas = $schemas(0);
$foreignKeySql = 'PRAGMA foreign_key_check(wp_options)';
$page = static fn (
    int $offset = 0,
    int $limit = 140,
    ?array $cursor = null,
    ?string $nextBytes = null,
    ?array $nextSchemasValue = null,
    ?SQLiteAttachedSchemaCatalog $nextCatalogValue = null,
): array => SQLitePragmaForeignKeyIntegrityRootpageCurrentSourceNextPlan::page(
    $currentDatabase,
    $schemas(),
    $currentCatalog,
    $nextBytes ?? $nextDatabase,
    $nextSchemasValue ?? $cleanSchemas,
    $nextCatalogValue ?? $nextCatalog,
    $foreignKeySql,
    $offset,
    $limit,
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
    'status ok after repaired rootpages and fk rows' => [static fn (): array => $page(), 'status', 'ok'],
    'limit default next140' => [static fn (): array => $page(), 'limit', 140],
    'total current rows only after repair' => [static fn (): array => $page(), 'total', 4],
    'count current rows only after repair' => [static fn (): array => $page(), 'count', 4],
    'complete true' => [static fn (): array => $page(), 'complete', true],
    'next null' => [static fn (): array => $page(), 'next', null],
    'source id length' => [static fn (): array => ['len' => strlen($page()['source_id'])], 'len', 64],
    'current database hash length' => [static fn (): array => ['len' => strlen($page()['current_source']['database'])], 'len', 64],
    'next database hash length' => [static fn (): array => ['len' => strlen($page()['next_source']['database'])], 'len', 64],
    'current catalog hash length' => [static fn (): array => ['len' => strlen($page()['current_source']['catalog'])], 'len', 64],
    'next catalog hash length' => [static fn (): array => ['len' => strlen($page()['next_source']['catalog'])], 'len', 64],
    'current schema hash length' => [static fn (): array => ['len' => strlen($page()['current_source']['schemas'])], 'len', 64],
    'next schema hash length' => [static fn (): array => ['len' => strlen($page()['next_source']['schemas'])], 'len', 64],
    'current fk sql normalized' => [static fn (): array => $page(), 'current_source.foreign_key_sql', 'pragma foreign_key_check(wp_options)'],
    'next fk sql normalized' => [static fn (): array => $page(), 'next_source.foreign_key_sql', 'pragma foreign_key_check(wp_options)'],
    'current fk violations' => [static fn (): array => $page(), 'current.foreign_key_violations', 4],
    'current child rootpage errors' => [static fn (): array => $page(), 'current.child_rootpage_errors', 4],
    'current parent rootpage errors' => [static fn (): array => $page(), 'current.parent_rootpage_errors', 4],
    'current pointer map conflicts' => [static fn (): array => $page(), 'current.pointer_map_conflicts', 8],
    'current missing catalog roots' => [static fn (): array => $page(), 'current.missing_catalog_rootpages', 0],
    'current schemas main' => [static fn (): array => $page(), 'current.schemas.0', 'main'],
    'next fk violations cleared' => [static fn (): array => $page(), 'next_counts.foreign_key_violations', 0],
    'next child root errors cleared' => [static fn (): array => $page(), 'next_counts.child_rootpage_errors', 0],
    'next parent root errors cleared' => [static fn (): array => $page(), 'next_counts.parent_rootpage_errors', 0],
    'next pointer conflicts cleared' => [static fn (): array => $page(), 'next_counts.pointer_map_conflicts', 0],
    'delta fk cleared' => [static fn (): array => $page(), 'delta.foreign_key_violations', -4],
    'delta child root cleared' => [static fn (): array => $page(), 'delta.child_rootpage_errors', -4],
    'delta parent root cleared' => [static fn (): array => $page(), 'delta.parent_rootpage_errors', -4],
    'delta pointer conflicts cleared' => [static fn (): array => $page(), 'delta.pointer_map_conflicts', -8],
    'delta total cleared' => [static fn (): array => $page(), 'delta.total', -4],
    'delta cleared true' => [static fn (): array => $page(), 'delta.cleared', true],
    'next state ready' => [static fn (): array => $page(), 'next_state.ready', true],
    'next state no blockers' => [static fn (): array => $page(), 'next_state.blocking', []],
    'row0 side current' => [static fn (): array => $page(), 'rows.0.side', 'current'],
    'row0 kind' => [static fn (): array => $page(), 'rows.0.kind', 'foreign_key_rootpage_pointer_map'],
    'row0 schema' => [static fn (): array => $page(), 'rows.0.schema', 'main'],
    'row0 table' => [static fn (): array => $page(), 'rows.0.table', 'wp_options'],
    'row0 rowid' => [static fn (): array => $page(), 'rows.0.rowid', 'autoload-missing-1'],
    'row0 parent' => [static fn (): array => $page(), 'rows.0.parent', 'wp_option_names'],
    'row0 fkid' => [static fn (): array => $page(), 'rows.0.fkid', 140],
    'row0 child rootpage' => [static fn (): array => $page(), 'rows.0.child_rootpage', 4],
    'row0 parent rootpage' => [static fn (): array => $page(), 'rows.0.parent_rootpage', 6],
    'row0 child status pointer' => [static fn (): array => $page(), 'rows.0.child_rootpage_status', 'pointer_map'],
    'row0 parent status pointer' => [static fn (): array => $page(), 'rows.0.parent_rootpage_status', 'pointer_map'],
    'row0 child pointer type' => [static fn (): array => $page(), 'rows.0.child_pointer_map_type', 'btree-page'],
    'row0 parent pointer type' => [static fn (): array => $page(), 'rows.0.parent_pointer_map_type', 'btree-page'],
    'row0 message mentions child' => [static fn (): array => ['has' => str_contains($page()['rows'][0]['message'], 'child pointer_map')], 'has', true],
    'row3 rowid' => [static fn (): array => $page(), 'rows.3.rowid', 'autoload-missing-4'],
    'pagination first count' => [static fn (): array => $page(0, 2), 'count', 2],
    'pagination first next offset' => [static fn (): array => $page(0, 2), 'next_offset', 2],
    'pagination first next source' => [static fn (): array => $page(0, 2), 'next.source_id', $page(0, 2)['source_id']],
    'pagination second offset' => [static fn (): array => $page(2, 2, $page(0, 2)['next']), 'offset', 2],
    'pagination second first rowid' => [static fn (): array => $page(2, 2, $page(0, 2)['next']), 'rows.0.rowid', 'autoload-missing-3'],
    'pagination second complete' => [static fn (): array => $page(2, 2, $page(0, 2)['next']), 'complete', true],
    'pagination second next null' => [static fn (): array => $page(2, 2, $page(0, 2)['next']), 'next', null],
];

$tests = [];
foreach ($cases as $name => [$factory, $path, $expected]) {
    $tests['pragma foreignkey integrity rootpage current source next140 ' . $name] = static function (TestRunner $t) use ($factory, $path, $expected, $valueAt): void {
        $t->same($expected, $valueAt($factory(), $path));
    };
}

$tests['pragma foreignkey integrity rootpage current source next140 blocked next catalog reports missing parent'] = static function (TestRunner $t) use ($page, $schemas, $missingParentCatalog): void {
    $result = $page(0, 140, null, null, $schemas(), $missingParentCatalog);

    $t->same('blocked', $result['status']);
    $t->same(false, $result['next_state']['ready']);
    $t->same(['foreign_key_check', 'foreign_key_rootpage_catalog'], $result['next_state']['blocking']);
    $t->same(4, $result['next_counts']['foreign_key_violations']);
    $t->same(4, $result['next_counts']['missing_catalog_rootpages']);
    $t->same(8, $result['total']);
    $t->same('next', $result['rows'][4]['side']);
    $t->same('missing_catalog_rootpage', $result['rows'][4]['parent_rootpage_status']);
};

$tests['pragma foreignkey integrity rootpage current source next140 blocked next bytes keep pointer map conflicts'] = static function (TestRunner $t) use ($page, $currentDatabase, $cleanSchemas): void {
    $result = $page(0, 140, null, $currentDatabase, $cleanSchemas);

    $t->same('ok', $result['status']);
    $t->same(0, $result['next_counts']['foreign_key_violations']);
    $t->same(0, $result['next_counts']['pointer_map_conflicts']);
    $t->same(-4, $result['delta']['foreign_key_violations']);
    $t->same(true, $result['delta']['cleared']);
};

$tests['pragma foreignkey integrity rootpage current source next140 source changes with repaired database'] = static function (TestRunner $t) use ($page, $currentDatabase): void {
    $repaired = $page();
    $unrepaired = $page(0, 140, null, $currentDatabase);

    $t->same(true, $repaired['source_id'] !== $unrepaired['source_id']);
    $t->same($repaired['current_source']['database'], $unrepaired['current_source']['database']);
    $t->same(true, $repaired['next_source']['database'] !== $unrepaired['next_source']['database']);
};

$tests['pragma foreignkey integrity rootpage current source next140 source changes with next rows'] = static function (TestRunner $t) use ($page, $schemas): void {
    $clean = $page();
    $dirty = $page(0, 140, null, null, $schemas(2));

    $t->same(true, $clean['source_id'] !== $dirty['source_id']);
    $t->same(2, $dirty['next_counts']['foreign_key_violations']);
    $t->same('blocked', $dirty['status']);
};

$tests['pragma foreignkey integrity rootpage current source next140 rejects stale cursor'] = static function (TestRunner $t) use ($page, $schemas): void {
    $first = $page(0, 2);
    $t->throws(InvalidArgumentException::class, static fn () => $page(2, 2, $first['next'], null, $schemas(1)));
};

$tests['pragma foreignkey integrity rootpage current source next140 rejects stale offset cursor'] = static function (TestRunner $t) use ($page): void {
    $first = $page(0, 2);
    $t->throws(InvalidArgumentException::class, static fn () => $page(3, 2, $first['next']));
};

$tests['pragma foreignkey integrity rootpage current source next140 rejects bad foreign key sql'] = static function (TestRunner $t) use ($currentDatabase, $nextDatabase, $schemas, $cleanSchemas, $currentCatalog, $nextCatalog): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLitePragmaForeignKeyIntegrityRootpageCurrentSourceNextPlan::page(
        $currentDatabase,
        $schemas(),
        $currentCatalog,
        $nextDatabase,
        $cleanSchemas,
        $nextCatalog,
        'PRAGMA table_info(wp_options)',
    ));
};

return $tests;
