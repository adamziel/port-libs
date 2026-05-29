<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteAttachedSchemaCatalog;
use PortLibs\LibSqlite\SQLiteFreelistTrunkPage;
use PortLibs\LibSqlite\SQLiteIndexLeafPage;
use PortLibs\LibSqlite\SQLitePointerMapEntry;
use PortLibs\LibSqlite\SQLitePragmaForeignKeyRootpageIntegrityCurrentSourceNext;
use PortLibs\LibSqlite\SQLiteRecord;
use PortLibs\LibSqlite\SQLiteSchemaRecord;
use PortLibs\LibSqlite\SQLiteTableLeafCell;
use PortLibs\LibSqlite\SQLiteTableLeafPage;

$pageSize = 1024;

$headerPage = static function (int $pageCount, int $largestRootPage, int $firstFreelist = 0, int $freelistCount = 0) use ($pageSize): string {
    $page = str_repeat("\0", $pageSize);
    $page = substr_replace($page, "SQLite format 3\0", 0, 16);
    $page = substr_replace($page, pack('n', $pageSize), 16, 2);
    $page[18] = "\x01";
    $page[19] = "\x01";
    $page = substr_replace($page, pack('N', $pageCount), 28, 4);
    $page = substr_replace($page, pack('N', $firstFreelist), 32, 4);
    $page = substr_replace($page, pack('N', $freelistCount), 36, 4);
    $page = substr_replace($page, pack('N', $largestRootPage), 52, 4);
    $page = substr_replace($page, pack('N', 1), 56, 4);

    return $page;
};

$putPointerMapEntry = static fn (string $page, int $pageNumber, int $type, int $parent): string => substr_replace($page, chr($type) . pack('N', $parent), 5 * ($pageNumber - 3), 5);
$schemaCell = static fn (array $values, int $rowId): string => SQLiteTableLeafCell::encode($rowId, SQLiteRecord::encode($values));
$currentSchemaRows = [
    ['table', 'wp_options', 'wp_options', 4, 'CREATE TABLE wp_options(option_id integer primary key, option_name text, autoload text)'],
    ['index', 'wp_options_alias', 'wp_options', 4, 'CREATE INDEX wp_options_alias ON wp_options(autoload)'],
    ['table', 'wp_option_names', 'wp_option_names', 6, 'CREATE TABLE wp_option_names(name text primary key)'],
];
$nextSchemaRows = [
    ['table', 'wp_options', 'wp_options', 4, 'CREATE TABLE wp_options(option_id integer primary key, option_name text, autoload text)'],
    ['index', 'wp_options_alias', 'wp_options', 5, 'CREATE INDEX wp_options_alias ON wp_options(autoload)'],
    ['table', 'wp_option_names', 'wp_option_names', 6, 'CREATE TABLE wp_option_names(name text primary key)'],
];

$schemaDatabase = static function (array $schemaRows, array $pointerRows, int $firstFreelist, int $freelistCount, array $pageOverrides = []) use ($headerPage, $putPointerMapEntry, $schemaCell, $pageSize): string {
    $pageCount = 8;
    $pages = [
        1 => SQLiteTableLeafPage::assemble(
            array_map(static fn (array $row, int $index): string => $schemaCell($row, $index + 1), $schemaRows, array_keys($schemaRows)),
            $pageSize,
            100,
            $headerPage($pageCount, 6, $firstFreelist, $freelistCount),
        ),
        2 => str_repeat("\0", $pageSize),
    ];
    foreach ($pointerRows as $entry) {
        $pages[2] = $putPointerMapEntry($pages[2], $entry[0], $entry[1], $entry[2]);
    }
    for ($pageNumber = 3; $pageNumber <= $pageCount; $pageNumber++) {
        $pages[$pageNumber] = $pageOverrides[$pageNumber] ?? SQLiteTableLeafPage::assemble([], $pageSize);
    }
    ksort($pages);

    return implode('', $pages);
};

$currentDatabase = $schemaDatabase($currentSchemaRows, [
    [3, SQLitePointerMapEntry::BTREE_PAGE, 4],
    [4, SQLitePointerMapEntry::BTREE_PAGE, 3],
    [5, SQLitePointerMapEntry::ROOT_PAGE, 0],
    [6, SQLitePointerMapEntry::BTREE_PAGE, 4],
], 3, 2, [
    3 => SQLiteFreelistTrunkPage::assemble(null, [6], $pageSize),
]);
$nextDatabase = $schemaDatabase($nextSchemaRows, [
    [3, SQLitePointerMapEntry::BTREE_PAGE, 4],
    [4, SQLitePointerMapEntry::ROOT_PAGE, 0],
    [5, SQLitePointerMapEntry::ROOT_PAGE, 0],
    [6, SQLitePointerMapEntry::ROOT_PAGE, 0],
], 0, 0, [
    5 => SQLiteIndexLeafPage::assemble([], $pageSize),
]);

$record = static fn (string $type, string $name, string $table, int $root, ?string $sql = null, int $rowid = 1): SQLiteSchemaRecord => new SQLiteSchemaRecord($type, $name, $table, $root, $sql ?? 'CREATE ' . strtoupper($type) . ' ' . $name, $rowid);
$catalog = new SQLiteAttachedSchemaCatalog([
    $record('table', 'wp_options', 'wp_options', 4, null, 1),
    $record('table', 'wp_option_names', 'wp_option_names', 6, null, 2),
]);
$missingParentCatalog = new SQLiteAttachedSchemaCatalog([
    $record('table', 'wp_options', 'wp_options', 4, null, 1),
]);

$schemas = static function (int $missing = 3): array {
    $options = [['rowid' => 1, 'option_name' => 'siteurl']];
    for ($i = 1; $i <= $missing; $i++) {
        $options[] = ['rowid' => 'missing-option-' . $i, 'option_name' => 'missing_' . $i];
    }

    return [
        'main' => [
            'tables' => [
                'wp_option_names' => [['rowid' => 1, 'name' => 'siteurl']],
                'wp_options' => $options,
            ],
            'foreignKeys' => [
                ['id' => 147, 'table' => 'wp_options', 'parent' => 'wp_option_names', 'columns' => [
                    ['child' => 'option_name', 'parent' => 'name', 'affinity' => 'text', 'collation' => 'nocase'],
                ]],
            ],
        ],
    ];
};
$cleanSchemas = $schemas(0);

$page = static fn (
    int $offset = 0,
    int $limit = 147,
    ?array $cursor = null,
    ?string $nextBytes = null,
    ?array $nextSchemasValue = null,
    ?SQLiteAttachedSchemaCatalog $nextCatalog = null,
    string $foreignKeySql = 'PRAGMA foreign_key_check(wp_options)',
    string $integritySql = 'PRAGMA integrity_check',
): array => SQLitePragmaForeignKeyRootpageIntegrityCurrentSourceNext::currentNextPage(
    $currentDatabase,
    $schemas(),
    $catalog,
    $nextBytes ?? $nextDatabase,
    $nextSchemasValue ?? $cleanSchemas,
    $nextCatalog ?? $catalog,
    $foreignKeySql,
    $integritySql,
    $offset,
    $limit,
    $cursor,
);

$valueAt = static function (mixed $value, string $path): mixed {
    foreach (explode('.', $path) as $part) {
        $value = is_array($value) && array_key_exists($part, $value) ? $value[$part] : $value[(int) $part];
    }

    return $value;
};

$default = static fn (): array => $page();
$blocked = static fn (): array => $page(0, 147, null, $currentDatabase, $cleanSchemas);
$missingParent = static fn (): array => $page(0, 147, null, $nextDatabase, $schemas(), $missingParentCatalog);

$cases = [
    'status ok after next repair' => [$default, 'status', 'ok'],
    'limit default next147' => [$default, 'limit', 147],
    'complete true' => [$default, 'complete', true],
    'next null' => [$default, 'next', null],
    'source id length' => [static fn (): array => ['len' => strlen($default()['source_id'])], 'len', 64],
    'current database source length' => [static fn (): array => ['len' => strlen($default()['current_source']['database'])], 'len', 64],
    'next database source length' => [static fn (): array => ['len' => strlen($default()['next_source']['database'])], 'len', 64],
    'current catalog source length' => [static fn (): array => ['len' => strlen($default()['current_source']['catalog'])], 'len', 64],
    'next catalog source length' => [static fn (): array => ['len' => strlen($default()['next_source']['catalog'])], 'len', 64],
    'current schemas source length' => [static fn (): array => ['len' => strlen($default()['current_source']['schemas'])], 'len', 64],
    'fk sql normalized' => [$default, 'current_source.foreign_key_sql', 'pragma foreign_key_check(wp_options)'],
    'integrity sql normalized' => [$default, 'current_source.integrity_sql', 'pragma integrity_check'],
    'current integrity root count' => [$default, 'current.integrity_root', 3],
    'current fk rootpage count' => [$default, 'current.foreign_key_rootpage', 3],
    'current child root errors' => [$default, 'current.child_rootpage_errors', 3],
    'current parent root errors' => [$default, 'current.parent_rootpage_errors', 3],
    'current missing catalog roots' => [$default, 'current.missing_catalog_rootpages', 0],
    'current pointer conflicts' => [$default, 'current.pointer_map_conflicts', 3],
    'current total blockers' => [$default, 'current.total_blockers', 6],
    'current schema main' => [$default, 'current.schemas.0', 'main'],
    'current table options' => [$default, 'current.tables.0', 'wp_options'],
    'current table names' => [$default, 'current.tables.1', 'wp_option_names'],
    'next integrity clears' => [$default, 'next_counts.integrity_root', 0],
    'next fk clears' => [$default, 'next_counts.foreign_key_rootpage', 0],
    'next pointer clears' => [$default, 'next_counts.pointer_map_conflicts', 0],
    'delta integrity clears' => [$default, 'delta.integrity_root', -3],
    'delta fk clears' => [$default, 'delta.foreign_key_rootpage', -3],
    'delta pointer clears' => [$default, 'delta.pointer_map_conflicts', -3],
    'delta total clears' => [$default, 'delta.total_blockers', -6],
    'delta cleared true' => [$default, 'delta.cleared', true],
    'next state ready' => [$default, 'next_state.ready', true],
    'next blocking empty' => [$default, 'next_state.blocking', []],
    'total rows current only after repair' => [$default, 'total', 6],
    'count rows current only after repair' => [$default, 'count', 6],
    'row0 is integrity' => [$default, 'rows.0.kind', 'integrity_root'],
    'row0 current side' => [$default, 'rows.0.side', 'current'],
    'row0 table' => [$default, 'rows.0.table', 'wp_options'],
    'row0 rootpage' => [$default, 'rows.0.rootpage', 4],
    'row0 status pointer map' => [$default, 'rows.0.page_status', 'pointer_map'],
    'row1 integrity duplicate index' => [$default, 'rows.1.name', 'wp_options_alias'],
    'row2 integrity freelist' => [$default, 'rows.2.page_status', 'freelist'],
    'row3 is fk pointer' => [$default, 'rows.3.kind', 'foreign_key_rootpage_pointer_map'],
    'row3 rowid' => [$default, 'rows.3.rowid', 'missing-option-1'],
    'row3 child status' => [$default, 'rows.3.child_rootpage_status', 'pointer_map'],
    'row3 parent status' => [$default, 'rows.3.parent_rootpage_status', 'freelist'],
    'row3 child pointer parent' => [$default, 'rows.3.child_pointer_map_parent', 3],
    'row3 parent pointer parent' => [$default, 'rows.3.parent_pointer_map_parent', null],
    'row5 final fk row' => [$default, 'rows.5.rowid', 'missing-option-3'],
    'blocked status remains blocked' => [$blocked, 'status', 'blocked'],
    'blocked next not ready' => [$blocked, 'next_state.ready', false],
    'blocked blockers ordered' => [$blocked, 'next_state.blocking', ['integrity_rootpage']],
    'blocked next total blockers' => [$blocked, 'next_counts.total_blockers', 3],
    'missing parent status blocked' => [$missingParent, 'status', 'blocked'],
    'missing parent blocking includes catalog' => [$missingParent, 'next_state.blocking', ['foreign_key_check', 'foreign_key_rootpage_catalog']],
    'missing parent count' => [$missingParent, 'next_counts.missing_catalog_rootpages', 3],
];

$tests = [];
foreach ($cases as $name => [$callback, $path, $expected]) {
    $tests['pragma foreignkey rootpage integrity current source next147 ' . $name] = static function (TestRunner $t) use ($callback, $path, $expected, $valueAt): void {
        $t->same($expected, $valueAt($callback(), $path));
    };
}

$tests['pragma foreignkey rootpage integrity current source next147 paginates through integrity then fk rows'] = static function (TestRunner $t) use ($page): void {
    $first = $page(0, 2);
    $second = $page(2, 2, $first['next']);
    $third = $page(4, 2, $second['next']);

    $t->same(2, $first['count']);
    $t->same(['source_id' => $first['source_id'], 'offset' => 2], $first['next']);
    $t->same('integrity_root', $second['rows'][0]['kind']);
    $t->same('foreign_key_rootpage_pointer_map', $second['rows'][1]['kind']);
    $t->same('missing-option-2', $third['rows'][0]['rowid']);
    $t->same(true, $third['complete']);
};

$tests['pragma foreignkey rootpage integrity current source next147 rejects stale next database cursor'] = static function (TestRunner $t) use ($page, $currentDatabase): void {
    $first = $page(0, 2);
    $t->throws(InvalidArgumentException::class, static fn () => $page(2, 2, $first['next'], $currentDatabase));
};

$tests['pragma foreignkey rootpage integrity current source next147 rejects stale next catalog cursor'] = static function (TestRunner $t) use ($page, $nextDatabase, $missingParentCatalog): void {
    $first = $page(0, 2);
    $t->throws(InvalidArgumentException::class, static fn () => $page(2, 2, $first['next'], $nextDatabase, null, $missingParentCatalog));
};

$tests['pragma foreignkey rootpage integrity current source next147 rejects stale fk sql cursor'] = static function (TestRunner $t) use ($page): void {
    $first = $page(0, 2);
    $t->throws(InvalidArgumentException::class, static fn () => $page(2, 2, $first['next'], null, null, null, 'PRAGMA foreign_key_check'));
};

$tests['pragma foreignkey rootpage integrity current source next147 rejects stale integrity sql cursor'] = static function (TestRunner $t) use ($page): void {
    $first = $page(0, 2);
    $t->throws(InvalidArgumentException::class, static fn () => $page(2, 2, $first['next'], null, null, null, 'PRAGMA foreign_key_check(wp_options)', 'PRAGMA quick_check'));
};

$tests['pragma foreignkey rootpage integrity current source next147 rejects stale offset cursor'] = static function (TestRunner $t) use ($page): void {
    $first = $page(0, 2);
    $t->throws(InvalidArgumentException::class, static fn () => $page(3, 2, $first['next']));
};

$tests['pragma foreignkey rootpage integrity current source next147 accepts explicit offset cursor key'] = static function (TestRunner $t) use ($page): void {
    $first = $page(0, 2);
    $second = $page(2, 2, ['source_id' => $first['source_id'], 'offset' => 2]);

    $t->same(2, $second['offset']);
    $t->same('wp_option_names', $second['rows'][0]['table']);
};

$tests['pragma foreignkey rootpage integrity current source next147 rejects negative offset'] = static function (TestRunner $t) use ($currentDatabase, $schemas, $catalog, $nextDatabase, $cleanSchemas): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLitePragmaForeignKeyRootpageIntegrityCurrentSourceNext::currentNextPage($currentDatabase, $schemas(), $catalog, $nextDatabase, $cleanSchemas, $catalog, 'PRAGMA foreign_key_check(wp_options)', 'PRAGMA integrity_check', -1));
};

$tests['pragma foreignkey rootpage integrity current source next147 rejects zero limit'] = static function (TestRunner $t) use ($currentDatabase, $schemas, $catalog, $nextDatabase, $cleanSchemas): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLitePragmaForeignKeyRootpageIntegrityCurrentSourceNext::currentNextPage($currentDatabase, $schemas(), $catalog, $nextDatabase, $cleanSchemas, $catalog, 'PRAGMA foreign_key_check(wp_options)', 'PRAGMA integrity_check', 0, 0));
};

return $tests;
