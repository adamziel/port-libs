<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteAttachedSchemaCatalog;
use PortLibs\LibSqlite\SQLiteIndexLeafPage;
use PortLibs\LibSqlite\SQLitePointerMapEntry;
use PortLibs\LibSqlite\SQLitePragmaForeignKeyRootIntegrityCurrentSourceYield;
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
    $pageCount = 8;
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
    ['index', 'wp_options_autoload', 'wp_options', 6, 'CREATE INDEX wp_options_autoload ON wp_options(autoload)'],
    ['table', 'wp_posts', 'wp_posts', 7, 'CREATE TABLE wp_posts(ID integer primary key, post_title text)'],
    ['index', 'wp_posts_title', 'wp_posts', 12, 'CREATE INDEX wp_posts_title ON wp_posts(post_title)'],
];

$database = $schemaDatabase($schemaRows, [
    [3, SQLitePointerMapEntry::BTREE_PAGE, 4],
    [4, SQLitePointerMapEntry::BTREE_PAGE, 3],
    [5, SQLitePointerMapEntry::ROOT_PAGE, 0],
    [6, SQLitePointerMapEntry::BTREE_PAGE, 4],
    [7, SQLitePointerMapEntry::ROOT_PAGE, 0],
    [8, SQLitePointerMapEntry::ROOT_PAGE, 0],
], [
    5 => SQLiteIndexLeafPage::assemble([], $pageSize),
    6 => SQLiteIndexLeafPage::assemble([], $pageSize),
]);

$record = static fn (string $type, string $name, string $table, int $root): SQLiteSchemaRecord => new SQLiteSchemaRecord(
    $type,
    $name,
    $table,
    $root,
    'CREATE ' . strtoupper($type) . ' ' . $name,
    $root,
);

$catalog = new SQLiteAttachedSchemaCatalog([
    $record('table', 'wp_options', 'wp_options', 4),
    $record('table', 'wp_option_names', 'wp_option_names', 8),
    $record('table', 'wp_posts', 'wp_posts', 7),
]);

$schemas = static function (int $missing = 4): array {
    $options = [['rowid' => 1, 'option_name' => 'siteurl']];
    for ($i = 1; $i <= $missing; $i++) {
        $options[] = ['rowid' => 'missing-' . $i, 'option_name' => 'missing_' . $i];
    }

    return [
        'main' => [
            'tables' => [
                'wp_option_names' => [['rowid' => 1, 'name' => 'siteurl']],
                'wp_options' => $options,
            ],
            'foreignKeys' => [
                ['id' => 128, 'table' => 'wp_options', 'parent' => 'wp_option_names', 'columns' => [
                    ['child' => 'option_name', 'parent' => 'name', 'affinity' => 'text', 'collation' => 'nocase'],
                ]],
            ],
        ],
    ];
};

$schema = $schemas();
$foreignKeySql = 'PRAGMA foreign_key_check(wp_options)';
$partialIntegritySql = 'PRAGMA integrity_check(wp_options)';
$quickPartialSql = 'PRAGMA main.quick_check("wp_options")';
$globalIntegritySql = 'PRAGMA integrity_check';

$page = static fn (
    int $offset = 0,
    int $limit = 128,
    ?array $cursor = null,
    string $integritySql = 'PRAGMA integrity_check(wp_options)',
    ?array $runSchemas = null,
): array => SQLitePragmaForeignKeyRootIntegrityCurrentSourceYield::page($database, $runSchemas ?? $schema, $foreignKeySql, $offset, $limit, $cursor, $catalog, $integritySql);

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
    'status ok' => [static fn (): array => $page(), 'status', 'ok'],
    'limit next128' => [static fn (): array => $page(), 'limit', 128],
    'partial total roots plus fk' => [static fn (): array => $page(), 'total', 6],
    'partial root count excludes posts' => [static fn (): array => $page(), 'current.integrity_root', 2],
    'partial fk count' => [static fn (): array => $page(), 'current.foreign_key', 4],
    'source integrity sql normalized' => [static fn (): array => $page(), 'current_source.integrity_sql', 'pragma integrity_check(wp_options)'],
    'source integrity scope table' => [static fn (): array => $page(), 'current_source.integrity_scope', 'table'],
    'source integrity target' => [static fn (): array => $page(), 'current_source.integrity_target', 'wp_options'],
    'source fk sql normalized' => [static fn (): array => $page(), 'current_source.foreign_key_sql', 'pragma foreign_key_check(wp_options)'],
    'source id length' => [static fn (): array => ['length' => strlen($page()['source_id'])], 'length', 64],
    'database hash length' => [static fn (): array => ['length' => strlen($page()['current_source']['database'])], 'length', 64],
    'schema hash length' => [static fn (): array => ['length' => strlen($page()['current_source']['schema_hash'])], 'length', 64],
    'catalog hash length' => [static fn (): array => ['length' => strlen((string) $page()['current_source']['catalog_hash'])], 'length', 64],
    'row0 kind root' => [static fn (): array => $page(), 'rows.0.kind', 'integrity_root'],
    'row0 source pointer map' => [static fn (): array => $page(), 'rows.0.source', 'pointer_map_conflict'],
    'row0 name table' => [static fn (): array => $page(), 'rows.0.name', 'wp_options'],
    'row0 table' => [static fn (): array => $page(), 'rows.0.table', 'wp_options'],
    'row0 rootpage' => [static fn (): array => $page(), 'rows.0.rootpage', 4],
    'row0 page status' => [static fn (): array => $page(), 'rows.0.page_status', 'pointer_map'],
    'row1 source pointer map' => [static fn (): array => $page(), 'rows.1.source', 'pointer_map_conflict'],
    'row1 name index' => [static fn (): array => $page(), 'rows.1.name', 'wp_options_autoload'],
    'row1 type index' => [static fn (): array => $page(), 'rows.1.type', 'index'],
    'row1 rootpage' => [static fn (): array => $page(), 'rows.1.rootpage', 6],
    'row2 fk kind' => [static fn (): array => $page(), 'rows.2.kind', 'foreign_key_check'],
    'row2 fk schema' => [static fn (): array => $page(), 'rows.2.schema', 'main'],
    'row2 fk rowid' => [static fn (): array => $page(), 'rows.2.rowid', 'missing-1'],
    'row2 fk parent' => [static fn (): array => $page(), 'rows.2.parent', 'wp_option_names'],
    'row2 fk fkid' => [static fn (): array => $page(), 'rows.2.fkid', 128],
    'row2 fk message' => [static fn (): array => $page(), 'rows.2.message', 'foreign key mismatch in main.wp_options rowid missing-1 references wp_option_names fkid 128'],
    'last fk rowid' => [static fn (): array => $page(), 'rows.5.rowid', 'missing-4'],
    'global total includes posts blocker' => [static fn (): array => $page(0, 128, null, $globalIntegritySql), 'total', 8],
    'global root count includes unrelated roots' => [static fn (): array => $page(0, 128, null, $globalIntegritySql), 'current.integrity_root', 4],
    'global source scope database' => [static fn (): array => $page(0, 128, null, $globalIntegritySql), 'current_source.integrity_scope', 'database'],
    'global target null' => [static fn (): array => $page(0, 128, null, $globalIntegritySql), 'current_source.integrity_target', null],
    'quick partial source pragma' => [static fn (): array => $page(0, 128, null, $quickPartialSql), 'current_source.integrity_sql', 'pragma main.quick_check("wp_options")'],
    'quick partial scope table' => [static fn (): array => $page(0, 128, null, $quickPartialSql), 'current_source.integrity_scope', 'table'],
    'quick partial target' => [static fn (): array => $page(0, 128, null, $quickPartialSql), 'current_source.integrity_target', 'wp_options'],
    'pagination first count' => [static fn (): array => $page(0, 3), 'count', 3],
    'pagination first next offset' => [static fn (): array => $page(0, 3), 'next_offset', 3],
    'pagination next cursor offset' => [static fn (): array => $page(0, 3), 'next.offset', 3],
    'pagination second offset' => [static fn (): array => $page(3, 3, $page(0, 3)['next']), 'offset', 3],
    'pagination second first rowid' => [static fn (): array => $page(3, 3, $page(0, 3)['next']), 'rows.0.rowid', 'missing-2'],
    'pagination complete' => [static fn (): array => $page(3, 3, $page(0, 3)['next']), 'complete', true],
    'pagination final next null' => [static fn (): array => $page(3, 3, $page(0, 3)['next']), 'next', null],
];

$tests = [];
foreach ($cases as $name => [$factory, $path, $expected]) {
    $tests['pragma foreignkey integrity partial root current source next128 ' . $name] = static function (TestRunner $t) use ($factory, $path, $expected, $valueAt): void {
        $t->same($expected, $valueAt($factory(), $path));
    };
}

$tests['pragma foreignkey integrity partial root current source next128 source changes with integrity scope'] = static function (TestRunner $t) use ($page, $globalIntegritySql): void {
    $partial = $page();
    $global = $page(0, 128, null, $globalIntegritySql);

    $t->same(true, $partial['source_id'] !== $global['source_id']);
    $t->same($partial['current_source']['database'], $global['current_source']['database']);
    $t->same($partial['current_source']['foreign_key_sql'], $global['current_source']['foreign_key_sql']);
};

$tests['pragma foreignkey integrity partial root current source next128 source changes with fk rows'] = static function (TestRunner $t) use ($page, $schemas): void {
    $first = $page(0, 128, null, 'PRAGMA integrity_check(wp_options)', $schemas(4));
    $second = $page(0, 128, null, 'PRAGMA integrity_check(wp_options)', $schemas(5));

    $t->same(true, $first['source_id'] !== $second['source_id']);
    $t->same(6, $first['total']);
    $t->same(7, $second['total']);
    $t->same(4, $first['current']['foreign_key']);
    $t->same(5, $second['current']['foreign_key']);
};

$tests['pragma foreignkey integrity partial root current source next128 rejects stale partial cursor'] = static function (TestRunner $t) use ($page, $globalIntegritySql): void {
    $first = $page(0, 3);

    $t->throws(InvalidArgumentException::class, static fn () => $page(3, 3, $first['next'], $globalIntegritySql));
};

$tests['pragma foreignkey integrity partial root current source next128 rejects missing integrity target'] = static function (TestRunner $t) use ($page): void {
    $t->throws(InvalidArgumentException::class, static fn () => $page(0, 128, null, 'PRAGMA integrity_check(wp_missing)'));
};

$tests['pragma foreignkey integrity partial root current source next128 rejects malformed integrity sql'] = static function (TestRunner $t) use ($page): void {
    $t->throws(InvalidArgumentException::class, static fn () => $page(0, 128, null, 'PRAGMA table_info(wp_options)'));
};

foreach (range(1, 16) as $index) {
    $tests['pragma foreignkey integrity partial root current source next128 repeated scoped fk count ' . $index] = static function (TestRunner $t) use ($page, $schemas, $index): void {
        $result = $page(0, 128, null, $index % 2 === 0 ? 'PRAGMA integrity_check(wp_options)' : 'PRAGMA quick_check(wp_options)', $schemas($index));
        $t->same($index, $result['current']['foreign_key']);
        $t->same(2, $result['current']['integrity_root']);
        $t->same(2 + $index, $result['total']);
    };
}

return $tests;
