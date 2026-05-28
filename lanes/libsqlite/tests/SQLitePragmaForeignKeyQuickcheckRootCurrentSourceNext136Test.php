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
];

$currentDatabase = $schemaDatabase($schemaRows, [
    [3, SQLitePointerMapEntry::BTREE_PAGE, 4],
    [4, SQLitePointerMapEntry::BTREE_PAGE, 3],
    [5, SQLitePointerMapEntry::ROOT_PAGE, 0],
    [6, SQLitePointerMapEntry::BTREE_PAGE, 4],
], [
    5 => SQLiteIndexLeafPage::assemble([], $pageSize),
    6 => SQLiteIndexLeafPage::assemble([], $pageSize),
]);

$nextDatabase = $schemaDatabase($schemaRows, [
    [3, SQLitePointerMapEntry::BTREE_PAGE, 4],
    [4, SQLitePointerMapEntry::ROOT_PAGE, 0],
    [5, SQLitePointerMapEntry::ROOT_PAGE, 0],
    [6, SQLitePointerMapEntry::ROOT_PAGE, 0],
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
]);

$schemas = static function (int $missing = 3): array {
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
                ['id' => 136, 'table' => 'wp_options', 'parent' => 'wp_option_names', 'columns' => [
                    ['child' => 'option_name', 'parent' => 'name', 'affinity' => 'text', 'collation' => 'nocase'],
                ]],
            ],
        ],
    ];
};

$cleanSchemas = $schemas(0);
$foreignKeySql = 'PRAGMA foreign_key_check(wp_options)';
$quickRootSql = 'PRAGMA quick_check(wp_options)';

$page = static fn (
    int $offset = 0,
    int $limit = 136,
    ?array $cursor = null,
    ?string $nextBytes = null,
    ?array $nextSchemasValue = null,
): array => SQLitePragmaForeignKeyRootIntegrityCurrentSourceYield::currentNextPage(
    $currentDatabase,
    $schemas(),
    $nextBytes ?? $nextDatabase,
    $nextSchemasValue ?? $cleanSchemas,
    $foreignKeySql,
    $offset,
    $limit,
    $cursor,
    $catalog,
    $catalog,
    $quickRootSql,
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
    'status ok after repair' => [static fn (): array => $page(), 'status', 'ok'],
    'limit default next136' => [static fn (): array => $page(), 'limit', 136],
    'total current rows only after repair' => [static fn (): array => $page(), 'total', 5],
    'count current rows only after repair' => [static fn (): array => $page(), 'count', 5],
    'complete true' => [static fn (): array => $page(), 'complete', true],
    'next null' => [static fn (): array => $page(), 'next', null],
    'source id length' => [static fn (): array => ['len' => strlen($page()['source_id'])], 'len', 64],
    'current database hash length' => [static fn (): array => ['len' => strlen($page()['current_source']['database'])], 'len', 64],
    'next database hash length' => [static fn (): array => ['len' => strlen($page()['next_source']['database'])], 'len', 64],
    'current schema hash length' => [static fn (): array => ['len' => strlen($page()['current_source']['schema_hash'])], 'len', 64],
    'next schema hash length' => [static fn (): array => ['len' => strlen($page()['next_source']['schema_hash'])], 'len', 64],
    'current catalog hash length' => [static fn (): array => ['len' => strlen((string) $page()['current_source']['catalog_hash'])], 'len', 64],
    'next catalog hash length' => [static fn (): array => ['len' => strlen((string) $page()['next_source']['catalog_hash'])], 'len', 64],
    'current fk sql normalized' => [static fn (): array => $page(), 'current_source.foreign_key_sql', 'pragma foreign_key_check(wp_options)'],
    'next fk sql normalized' => [static fn (): array => $page(), 'next_source.foreign_key_sql', 'pragma foreign_key_check(wp_options)'],
    'current quick sql normalized' => [static fn (): array => $page(), 'current_source.integrity_sql', 'pragma quick_check(wp_options)'],
    'next quick sql normalized' => [static fn (): array => $page(), 'next_source.integrity_sql', 'pragma quick_check(wp_options)'],
    'current integrity scope' => [static fn (): array => $page(), 'current_source.integrity_scope', 'table'],
    'next integrity scope' => [static fn (): array => $page(), 'next_source.integrity_scope', 'table'],
    'current integrity target' => [static fn (): array => $page(), 'current_source.integrity_target', 'wp_options'],
    'next integrity target' => [static fn (): array => $page(), 'next_source.integrity_target', 'wp_options'],
    'current root blockers' => [static fn (): array => $page(), 'current.integrity_root', 2],
    'current fk blockers' => [static fn (): array => $page(), 'current.foreign_key', 3],
    'next root blockers cleared' => [static fn (): array => $page(), 'next_counts.integrity_root', 0],
    'next fk blockers cleared' => [static fn (): array => $page(), 'next_counts.foreign_key', 0],
    'delta root cleared' => [static fn (): array => $page(), 'delta.integrity_root', -2],
    'delta fk cleared' => [static fn (): array => $page(), 'delta.foreign_key', -3],
    'delta total cleared' => [static fn (): array => $page(), 'delta.total', -5],
    'delta cleared true' => [static fn (): array => $page(), 'delta.cleared', true],
    'next state ready' => [static fn (): array => $page(), 'next_state.ready', true],
    'row0 side current' => [static fn (): array => $page(), 'rows.0.side', 'current'],
    'row0 kind root' => [static fn (): array => $page(), 'rows.0.kind', 'integrity_root'],
    'row0 source pointer' => [static fn (): array => $page(), 'rows.0.source', 'pointer_map_conflict'],
    'row0 table' => [static fn (): array => $page(), 'rows.0.table', 'wp_options'],
    'row0 rootpage' => [static fn (): array => $page(), 'rows.0.rootpage', 4],
    'row1 name autoload' => [static fn (): array => $page(), 'rows.1.name', 'wp_options_autoload'],
    'row1 rootpage' => [static fn (): array => $page(), 'rows.1.rootpage', 6],
    'row2 kind fk' => [static fn (): array => $page(), 'rows.2.kind', 'foreign_key_check'],
    'row2 rowid' => [static fn (): array => $page(), 'rows.2.rowid', 'missing-1'],
    'row2 parent' => [static fn (): array => $page(), 'rows.2.parent', 'wp_option_names'],
    'row2 fkid' => [static fn (): array => $page(), 'rows.2.fkid', 136],
    'row4 rowid' => [static fn (): array => $page(), 'rows.4.rowid', 'missing-3'],
    'page first limited count' => [static fn (): array => $page(0, 3), 'count', 3],
    'page first limited next offset' => [static fn (): array => $page(0, 3), 'next_offset', 3],
    'page first limited next source' => [static fn (): array => $page(0, 3), 'next.source_id', $page(0, 3)['source_id']],
    'page second offset' => [static fn (): array => $page(3, 3, $page(0, 3)['next']), 'offset', 3],
    'page second first row' => [static fn (): array => $page(3, 3, $page(0, 3)['next']), 'rows.0.rowid', 'missing-2'],
    'page second complete' => [static fn (): array => $page(3, 3, $page(0, 3)['next']), 'complete', true],
    'page second next null' => [static fn (): array => $page(3, 3, $page(0, 3)['next']), 'next', null],
];

$tests = [];
foreach ($cases as $name => [$factory, $path, $expected]) {
    $tests['pragma foreignkey quickcheck root current source next136 ' . $name] = static function (TestRunner $t) use ($factory, $path, $expected, $valueAt): void {
        $t->same($expected, $valueAt($factory(), $path));
    };
}

$tests['pragma foreignkey quickcheck root current source next136 blocked next source keeps blockers'] = static function (TestRunner $t) use ($page, $currentDatabase, $schemas): void {
    $result = $page(0, 136, null, $currentDatabase, $schemas(2));

    $t->same('blocked', $result['status']);
    $t->same(false, $result['next_state']['ready']);
    $t->same('quick_check_root', $result['next_state']['blocking'][0]);
    $t->same('foreign_key_check', $result['next_state']['blocking'][1]);
    $t->same(2, $result['next_counts']['integrity_root']);
    $t->same(2, $result['next_counts']['foreign_key']);
    $t->same(false, $result['delta']['cleared']);
    $t->same(9, $result['total']);
    $t->same('next', $result['rows'][5]['side']);
};

$tests['pragma foreignkey quickcheck root current source next136 source changes with next repair bytes'] = static function (TestRunner $t) use ($page, $currentDatabase): void {
    $repaired = $page();
    $unrepaired = $page(0, 136, null, $currentDatabase);

    $t->same(true, $repaired['source_id'] !== $unrepaired['source_id']);
    $t->same($repaired['current_source']['database'], $unrepaired['current_source']['database']);
    $t->same(true, $repaired['next_source']['database'] !== $unrepaired['next_source']['database']);
};

$tests['pragma foreignkey quickcheck root current source next136 rejects stale next cursor'] = static function (TestRunner $t) use ($page, $currentDatabase): void {
    $first = $page(0, 3);
    $t->throws(InvalidArgumentException::class, static fn () => $page(3, 3, $first['next'], $currentDatabase));
};

$tests['pragma foreignkey quickcheck root current source next136 rejects stale offset cursor'] = static function (TestRunner $t) use ($page): void {
    $first = $page(0, 3);
    $t->throws(InvalidArgumentException::class, static fn () => $page(4, 3, $first['next']));
};

$tests['pragma foreignkey quickcheck root current source next136 rejects bad quickcheck target'] = static function (TestRunner $t) use ($currentDatabase, $nextDatabase, $schemas, $cleanSchemas, $foreignKeySql, $catalog): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLitePragmaForeignKeyRootIntegrityCurrentSourceYield::currentNextPage(
        $currentDatabase,
        $schemas(),
        $nextDatabase,
        $cleanSchemas,
        $foreignKeySql,
        0,
        136,
        null,
        $catalog,
        $catalog,
        'PRAGMA table_info(wp_options)',
    ));
};

return $tests;
