<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteAttachedSchemaCatalog;
use PortLibs\LibSqlite\SQLiteFreelistTrunkPage;
use PortLibs\LibSqlite\SQLiteIndexLeafPage;
use PortLibs\LibSqlite\SQLitePointerMapEntry;
use PortLibs\LibSqlite\SQLitePragmaForeignKeyRootIntegrityCurrentSourceYield;
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

$putPointerMapEntry = static function (string $page, int $pageNumber, int $type, int $parent) use ($pageSize): string {
    $offset = 5 * ($pageNumber - 3);
    if ($offset < 0 || $offset + 5 > $pageSize) {
        throw new RuntimeException('test pointer-map entry offset is out of range');
    }

    return substr_replace($page, chr($type) . pack('N', $parent), $offset, 5);
};

$schemaCell = static fn (array $values, int $rowId): string => SQLiteTableLeafCell::encode($rowId, SQLiteRecord::encode($values));

$schemaDatabase = static function (
    array $schemaRows,
    int $pageCount,
    int $largestRootPage,
    array $pointerMapEntries,
    array $pageImages = [],
    int $firstFreelist = 0,
    int $freelistCount = 0,
) use ($headerPage, $putPointerMapEntry, $schemaCell, $pageSize): string {
    $pages = [
        1 => SQLiteTableLeafPage::assemble(
            array_map(static fn (array $row, int $index): string => $schemaCell($row, $index + 1), $schemaRows, array_keys($schemaRows)),
            $pageSize,
            100,
            $headerPage($pageCount, $largestRootPage, $firstFreelist, $freelistCount),
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
    ['index', 'wp_options_autoload', 'wp_options', 5, 'CREATE INDEX wp_options_autoload ON wp_options(autoload)'],
    ['index', 'wp_options_alias', 'wp_options', 4, 'CREATE INDEX wp_options_alias ON wp_options(option_name)'],
    ['table', 'wp_missing_root', 'wp_missing_root', 9, 'CREATE TABLE wp_missing_root(id integer primary key)'],
    ['table', 'wp_free_root', 'wp_free_root', 6, 'CREATE TABLE wp_free_root(id integer primary key)'],
];

$database = $schemaDatabase($schemaRows, 6, 6, [
    [3, SQLitePointerMapEntry::BTREE_PAGE, 4],
    [4, SQLitePointerMapEntry::ROOT_PAGE, 0],
    [5, SQLitePointerMapEntry::BTREE_PAGE, 3],
    [6, SQLitePointerMapEntry::ROOT_PAGE, 0],
], [
    3 => SQLiteFreelistTrunkPage::assemble(null, [6], $pageSize),
    5 => SQLiteIndexLeafPage::assemble([], $pageSize),
], 3, 2);

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
    $record('table', 'wp_option_names', 'wp_option_names', 7),
]);
$catalog->attach('archive', '/tmp/wp-archive.sqlite', [
    $record('table', 'wp_options', 'wp_options', 8),
    $record('table', 'wp_option_names', 'wp_option_names', 9),
]);

$schemas = static function (int $mainMissing = 6, int $archiveMissing = 4): array {
    $mainRows = [['rowid' => 1, 'option_name' => 'siteurl']];
    for ($i = 1; $i <= $mainMissing; $i++) {
        $mainRows[] = ['rowid' => 'main-' . $i, 'option_name' => 'missing_' . $i];
    }
    $archiveRows = [['rowid' => 'archive-ok', 'option_name' => 'legacy_siteurl']];
    for ($i = 1; $i <= $archiveMissing; $i++) {
        $archiveRows[] = ['rowid' => 'archive-' . $i, 'option_name' => 'archive_missing_' . $i];
    }

    return [
        'main' => [
            'tables' => [
                'wp_option_names' => [['rowid' => 1, 'name' => 'siteurl']],
                'wp_options' => $mainRows,
            ],
            'foreignKeys' => [
                ['id' => 120, 'table' => 'wp_options', 'parent' => 'wp_option_names', 'columns' => [
                    ['child' => 'option_name', 'parent' => 'name', 'affinity' => 'text', 'collation' => 'nocase'],
                ]],
            ],
        ],
        'archive' => [
            'tables' => [
                'wp_option_names' => [['rowid' => 1, 'name' => 'legacy_siteurl']],
                'wp_options' => $archiveRows,
            ],
            'foreignKeys' => [
                ['id' => 121, 'table' => 'wp_options', 'parent' => 'wp_option_names', 'columns' => [
                    ['child' => 'option_name', 'parent' => 'name', 'affinity' => 'text', 'collation' => 'nocase'],
                ]],
            ],
        ],
    ];
};

$schema = $schemas();
$tableValuedSql = "SELECT * FROM pragma_foreign_key_check('archive.wp_options')";
$qualifiedTableValuedSql = "SELECT * FROM archive.pragma_foreign_key_check('wp_options')";
$pragmaSql = 'PRAGMA main.foreign_key_check(wp_options)';

$page = static fn (int $offset = 0, int $limit = 120, ?array $cursor = null, string $sql = null): array => SQLitePragmaForeignKeyRootIntegrityCurrentSourceYield::page(
    $database,
    $schema,
    $sql ?? $tableValuedSql,
    $offset,
    $limit,
    $cursor,
    $catalog,
);
$pragmaPage = static fn (): array => SQLitePragmaForeignKeyRootIntegrityCurrentSourceYield::page($database, $schema, $pragmaSql, 0, 120, null, $catalog);
$qualifiedPage = static fn (): array => SQLitePragmaForeignKeyRootIntegrityCurrentSourceYield::page($database, $schema, $qualifiedTableValuedSql, 0, 120, null, $catalog);

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
    'status ok' => [$page, 'status', 'ok'],
    'limit next120' => [$page, 'limit', 120],
    'total combines root and archive fk rows' => [$page, 'total', 10],
    'current root count' => [$page, 'current.integrity_root', 6],
    'current fk count' => [$page, 'current.foreign_key', 4],
    'source sql normalized table valued' => [$page, 'current_source.foreign_key_sql', "select * from pragma_foreign_key_check('archive.wp_options')"],
    'source id length' => [static fn (): array => ['length' => strlen($page()['source_id'])], 'length', 64],
    'database hash length' => [static fn (): array => ['length' => strlen($page()['current_source']['database'])], 'length', 64],
    'schema hash length' => [static fn (): array => ['length' => strlen($page()['current_source']['schema_hash'])], 'length', 64],
    'catalog hash length' => [static fn (): array => ['length' => strlen((string) $page()['current_source']['catalog_hash'])], 'length', 64],
    'first row root duplicate' => [$page, 'rows.0.source', 'duplicate_rootpage'],
    'first row root page' => [$page, 'rows.0.rootpage', 4],
    'sixth row root schema rootpage' => [$page, 'rows.5.source', 'schema_rootpage'],
    'first fk kind' => [$page, 'rows.6.kind', 'foreign_key_check'],
    'first fk schema archive' => [$page, 'rows.6.schema', 'archive'],
    'first fk rowid' => [$page, 'rows.6.rowid', 'archive-1'],
    'first fk parent' => [$page, 'rows.6.parent', 'wp_option_names'],
    'first fk fkid' => [$page, 'rows.6.fkid', 121],
    'first fk message archive' => [$page, 'rows.6.message', 'foreign key mismatch in archive.wp_options rowid archive-1 references wp_option_names fkid 121'],
    'last fk rowid archive' => [$page, 'rows.9.rowid', 'archive-4'],
    'qualified table valued schema' => [$qualifiedPage, 'rows.6.schema', 'archive'],
    'qualified table valued sql normalized' => [$qualifiedPage, 'current_source.foreign_key_sql', "select * from archive.pragma_foreign_key_check('wp_options')"],
    'pragma statement schema main' => [$pragmaPage, 'rows.6.schema', 'main'],
    'pragma statement rowid' => [$pragmaPage, 'rows.6.rowid', 'main-1'],
    'pragma statement fk count' => [$pragmaPage, 'current.foreign_key', 6],
    'pragma statement total' => [$pragmaPage, 'total', 12],
];

$tests = [];
foreach ($cases as $name => [$callback, $path, $expected]) {
    $tests['pragma fk root integrity current source next120 ' . $name] = static function (TestRunner $t) use ($callback, $valueAt, $path, $expected): void {
        $t->same($expected, $valueAt($callback(), $path));
    };
}

$tests['pragma fk root integrity current source next120 paginates table valued rows'] = static function (TestRunner $t) use ($page): void {
    $first = $page(0, 7);
    $second = $page(7, 7, ['source_id' => $first['source_id'], 'next_offset' => 7]);

    $t->same(7, $first['count']);
    $t->same(7, $first['next_offset']);
    $t->same(['source_id' => $first['source_id'], 'offset' => 7], $first['next']);
    $t->same(7, $second['offset']);
    $t->same(3, $second['count']);
    $t->same(true, $second['complete']);
    $t->same(null, $second['next']);
    $t->same('archive-2', $second['rows'][0]['rowid']);
};

$tests['pragma fk root integrity current source next120 source changes with table valued target'] = static function (TestRunner $t) use ($page, $pragmaPage): void {
    $tableValued = $page();
    $pragma = $pragmaPage();

    $t->same(true, $tableValued['source_id'] !== $pragma['source_id']);
    $t->same($tableValued['current_source']['database'], $pragma['current_source']['database']);
    $t->same($tableValued['current_source']['schema_hash'], $pragma['current_source']['schema_hash']);
};

$tests['pragma fk root integrity current source next120 source changes with schema rows'] = static function (TestRunner $t) use ($database, $schemas, $tableValuedSql, $catalog): void {
    $first = SQLitePragmaForeignKeyRootIntegrityCurrentSourceYield::page($database, $schemas(6, 4), $tableValuedSql, 0, 120, null, $catalog);
    $second = SQLitePragmaForeignKeyRootIntegrityCurrentSourceYield::page($database, $schemas(6, 5), $tableValuedSql, 0, 120, null, $catalog);

    $t->same(true, $first['source_id'] !== $second['source_id']);
    $t->same(10, $first['total']);
    $t->same(11, $second['total']);
    $t->same(4, $first['current']['foreign_key']);
    $t->same(5, $second['current']['foreign_key']);
};

$tests['pragma fk root integrity current source next120 rejects stale table valued cursor'] = static function (TestRunner $t) use ($page, $qualifiedTableValuedSql): void {
    $first = $page(0, 7);
    $t->throws(InvalidArgumentException::class, static fn () => $page(7, 7, ['source_id' => $first['source_id'], 'next_offset' => 7], $qualifiedTableValuedSql));
};

$tests['pragma fk root integrity current source next120 rejects malformed select list'] = static function (TestRunner $t) use ($page): void {
    $t->throws(InvalidArgumentException::class, static fn () => $page(0, 120, null, "SELECT rowid FROM pragma_foreign_key_check('archive.wp_options')"));
};

foreach (range(1, 12) as $index) {
    $tests['pragma fk root integrity current source next120 repeated archive fk row ' . $index] = static function (TestRunner $t) use ($database, $schemas, $tableValuedSql, $catalog, $index): void {
        $result = SQLitePragmaForeignKeyRootIntegrityCurrentSourceYield::page($database, $schemas(6, $index), $tableValuedSql, 0, 120, null, $catalog);
        $t->same($index, $result['current']['foreign_key']);
        $t->same(6, $result['current']['integrity_root']);
        $t->same('archive-1', $result['rows'][6]['rowid']);
    };
}

return $tests;
