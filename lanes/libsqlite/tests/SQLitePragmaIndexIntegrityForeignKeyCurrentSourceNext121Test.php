<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteAttachedSchemaCatalog;
use PortLibs\LibSqlite\SQLiteFreelistTrunkPage;
use PortLibs\LibSqlite\SQLiteIndexLeafPage;
use PortLibs\LibSqlite\SQLitePointerMapEntry;
use PortLibs\LibSqlite\SQLitePragmaIndexIntegrityForeignKeyCurrentSourceYield;
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

$putPointerMapEntry = static function (string $page, int $pageNumber, int $type, int $parent): string {
    return substr_replace($page, chr($type) . pack('N', $parent), 5 * ($pageNumber - 3), 5);
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
        2 => str_repeat("\0", $pageSize),
    ];
    foreach ($pointerMapEntries as $entry) {
        $pages[2] = $putPointerMapEntry($pages[2], $entry[0], $entry[1], $entry[2]);
    }
    for ($pageNumber = 3; $pageNumber <= $pageCount; $pageNumber++) {
        $pages[$pageNumber] = $pageImages[$pageNumber] ?? SQLiteTableLeafPage::assemble([], $pageSize);
    }
    ksort($pages);

    return implode('', $pages);
};

$schemaRows = [
    ['table', 'wp_options', 'wp_options', 4, 'CREATE TABLE wp_options(option_id integer primary key, option_name text, option_value text, autoload text)'],
    ['index', 'wp_options_name', 'wp_options', 5, 'CREATE UNIQUE INDEX wp_options_name ON wp_options(option_name COLLATE nocase DESC, autoload)'],
    ['index', 'wp_options_alias', 'wp_options', 4, 'CREATE INDEX wp_options_alias ON wp_options(autoload)'],
    ['table', 'wp_free_root', 'wp_free_root', 6, 'CREATE TABLE wp_free_root(id integer primary key)'],
];

$dirtyDatabase = $schemaDatabase($schemaRows, 6, 6, [
    [3, SQLitePointerMapEntry::BTREE_PAGE, 4],
    [4, SQLitePointerMapEntry::ROOT_PAGE, 0],
    [5, SQLitePointerMapEntry::BTREE_PAGE, 4],
    [6, SQLitePointerMapEntry::ROOT_PAGE, 0],
], [
    3 => SQLiteFreelistTrunkPage::assemble(null, [6], $pageSize),
    5 => SQLiteIndexLeafPage::assemble([], $pageSize),
], 3, 2);

$cleanDatabase = $schemaDatabase(array_slice($schemaRows, 0, 2), 5, 5, [
    [3, SQLitePointerMapEntry::BTREE_PAGE, 4],
    [4, SQLitePointerMapEntry::ROOT_PAGE, 0],
    [5, SQLitePointerMapEntry::ROOT_PAGE, 0],
], [
    5 => SQLiteIndexLeafPage::assemble([], $pageSize),
]);

$record = static fn (string $type, string $name, string $table, int $root, ?string $sql = null): SQLiteSchemaRecord => new SQLiteSchemaRecord(
    $type,
    $name,
    $table,
    $root,
    $sql ?? 'CREATE ' . strtoupper($type) . ' ' . $name,
    $root,
);

$catalog = new SQLiteAttachedSchemaCatalog([
    $record('table', 'wp_options', 'wp_options', 4, 'CREATE TABLE wp_options(option_id integer primary key, option_name text, option_value text, autoload text)'),
    $record('index', 'wp_options_name', 'wp_options', 5, 'CREATE UNIQUE INDEX wp_options_name ON wp_options(option_name COLLATE nocase DESC, autoload)'),
    $record('table', 'wp_option_names', 'wp_option_names', 7, 'CREATE TABLE wp_option_names(name text primary key)'),
], [
    $record('table', 'wp_options', 'wp_options', 8),
    $record('index', 'wp_options_temp_name', 'wp_options', 9, 'CREATE INDEX wp_options_temp_name ON wp_options(upper(option_name) COLLATE rtrim, autoload DESC)'),
]);
$catalog->attach('archive', '/srv/wp/archive.sqlite', [
    $record('table', 'wp_options', 'wp_options', 10),
    $record('index', 'wp_options_archive_name', 'wp_options', 11, 'CREATE INDEX wp_options_archive_name ON wp_options(option_name, autoload DESC)'),
]);

$schemas = static function (int $missing = 4): array {
    $rows = [
        ['rowid' => 1, 'option_name' => 'siteurl'],
    ];
    for ($i = 1; $i <= $missing; $i++) {
        $rows[] = ['rowid' => 'missing-' . $i, 'option_name' => 'missing_' . $i];
    }
    $rows[] = ['rowid' => 'null-option', 'option_name' => null];

    return [
        'main' => [
            'tables' => [
                'wp_option_names' => [
                    ['rowid' => 1, 'name' => 'siteurl'],
                ],
                'wp_options' => $rows,
            ],
            'foreignKeys' => [
                ['id' => 121, 'table' => 'wp_options', 'parent' => 'wp_option_names', 'columns' => [
                    ['child' => 'option_name', 'parent' => 'name', 'affinity' => 'text', 'collation' => 'nocase'],
                ]],
            ],
        ],
    ];
};

$sql = 'PRAGMA main.index_xinfo(wp_options_name)';
$fkSql = 'PRAGMA main.foreign_key_check(wp_options)';
$page = static fn (int $offset = 0, int $limit = 121, ?array $cursor = null, string $indexSql = null, string $database = null, ?array $schemaRows = null, bool $tableValued = false): array => SQLitePragmaIndexIntegrityForeignKeyCurrentSourceYield::page(
    $catalog,
    $indexSql ?? $sql,
    $database ?? $dirtyDatabase,
    $schemaRows ?? $schemas(),
    $fkSql,
    $offset,
    $limit,
    'PRAGMA integrity_check',
    $tableValued,
    $cursor,
    $catalog,
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
    'status ok' => [$page, 'status', 'ok'],
    'default limit next121' => [$page, 'limit', 121],
    'total combines xinfo roots and fk' => [$page, 'total', 11],
    'count full page' => [$page, 'count', 11],
    'complete full page' => [$page, 'complete', true],
    'current xinfo count' => [$page, 'current.index_xinfo', 3],
    'current root count' => [$page, 'current.integrity_root', 4],
    'current fk count' => [$page, 'current.foreign_key', 4],
    'source id length' => [static fn (): array => ['len' => strlen($page()['source_id'])], 'len', 64],
    'database hash length' => [static fn (): array => ['len' => strlen($page()['current_source']['database'])], 'len', 64],
    'catalog hash length' => [static fn (): array => ['len' => strlen($page()['current_source']['catalog_hash'])], 'len', 64],
    'schema hash length' => [static fn (): array => ['len' => strlen($page()['current_source']['schema_hash'])], 'len', 64],
    'fk catalog hash length' => [static fn (): array => ['len' => strlen((string) $page()['current_source']['foreign_key_catalog_hash'])], 'len', 64],
    'source normalized index sql' => [$page, 'current_source.index_xinfo_sql', 'pragma main.index_xinfo(wp_options_name)'],
    'source normalized fk sql' => [$page, 'current_source.foreign_key_sql', 'pragma main.foreign_key_check(wp_options)'],
    'source normalized integrity sql' => [$page, 'current_source.integrity_sql', 'pragma integrity_check'],
    'source table valued false' => [$page, 'current_source.index_table_valued', false],
    'row0 xinfo kind' => [$page, 'rows.0.kind', 'index_xinfo'],
    'row0 xinfo source' => [$page, 'rows.0.source', 'index_xinfo'],
    'row0 xinfo schema' => [$page, 'rows.0.schema', 'main'],
    'row0 xinfo target' => [$page, 'rows.0.target', 'wp_options_name'],
    'row0 xinfo name' => [$page, 'rows.0.name', 'option_name'],
    'row0 xinfo coll' => [$page, 'rows.0.coll', 'NOCASE'],
    'row1 xinfo name' => [$page, 'rows.1.name', 'autoload'],
    'row1 xinfo desc' => [$page, 'rows.1.desc', 0],
    'row2 xinfo rowid cid' => [$page, 'rows.2.cid', -1],
    'row3 root kind' => [$page, 'rows.3.kind', 'integrity_root'],
    'row3 duplicate root source' => [$page, 'rows.3.source', 'duplicate_rootpage'],
    'row3 duplicate root table' => [$page, 'rows.3.table', 'wp_options'],
    'row4 duplicate index name' => [$page, 'rows.4.name', 'wp_options_alias'],
    'row5 pointer conflict' => [$page, 'rows.5.source', 'pointer_map_conflict'],
    'row5 pointer page type' => [$page, 'rows.5.page_type', 'index-leaf'],
    'row6 freelist conflict' => [$page, 'rows.6.source', 'freelist_conflict'],
    'row7 fk kind' => [$page, 'rows.7.kind', 'foreign_key_check'],
    'row7 fk source' => [$page, 'rows.7.source', 'foreign_key'],
    'row7 fk schema' => [$page, 'rows.7.schema', 'main'],
    'row7 fk table' => [$page, 'rows.7.table', 'wp_options'],
    'row7 fk rowid' => [$page, 'rows.7.rowid', 'missing-1'],
    'row7 fk parent' => [$page, 'rows.7.parent', 'wp_option_names'],
    'row7 fk id' => [$page, 'rows.7.fkid', 121],
    'row7 fk message' => [$page, 'rows.7.message', 'foreign key mismatch in main.wp_options rowid missing-1 references wp_option_names fkid 121'],
    'last fk rowid' => [$page, 'rows.10.rowid', 'missing-4'],
    'offset three starts root' => [static fn (): array => $page(3, 4), 'current.index_xinfo', 3],
    'offset three current row kind' => [static fn (): array => $page(3, 4), 'rows.0.kind', 'integrity_root'],
    'offset three next offset' => [static fn (): array => $page(3, 4), 'next_offset', 7],
    'offset eight starts fk' => [static fn (): array => $page(8, 2), 'rows.0.kind', 'foreign_key_check'],
    'offset eight next offset' => [static fn (): array => $page(8, 2), 'next_offset', 10],
    'tail complete' => [static fn (): array => $page(10, 10), 'complete', true],
    'tail next null' => [static fn (): array => $page(10, 10), 'next', null],
    'past tail count zero' => [static fn (): array => $page(20, 3), 'count', 0],
    'clean total has index plus fk' => [static fn (): array => $page(0, 121, null, null, $cleanDatabase), 'total', 7],
    'clean root count zero' => [static fn (): array => $page(0, 121, null, null, $cleanDatabase), 'current.integrity_root', 0],
    'clean fk count four' => [static fn (): array => $page(0, 121, null, null, $cleanDatabase), 'current.foreign_key', 4],
    'table valued archive schema' => [static fn (): array => $page(0, 121, null, "pragma_index_xinfo('wp_options_archive_name','archive')", $cleanDatabase, null, true), 'rows.0.schema', 'archive'],
    'table valued archive target' => [static fn (): array => $page(0, 121, null, "pragma_index_xinfo('wp_options_archive_name','archive')", $cleanDatabase, null, true), 'rows.0.target', 'wp_options_archive_name'],
    'table valued archive total' => [static fn (): array => $page(0, 121, null, "pragma_index_xinfo('wp_options_archive_name','archive')", $cleanDatabase, null, true), 'total', 7],
];

$tests = [];
foreach ($cases as $name => [$callback, $path, $expected]) {
    $tests['pragma index integrity foreign key current source next121 ' . $name] = static function (TestRunner $t) use ($callback, $valueAt, $path, $expected): void {
        $t->same($expected, $valueAt($callback(), $path));
    };
}

$tests['pragma index integrity foreign key current source next121 resumes stable cursor'] = static function (TestRunner $t) use ($page): void {
    $first = $page(0, 5);
    $second = $page(5, 5, ['source_id' => $first['source_id'], 'next_offset' => $first['next_offset']]);

    $t->same(5, $second['offset']);
    $t->same($first['source_id'], $second['source_id']);
    $t->same('pointer_map_conflict', $second['rows'][0]['source']);
};

$tests['pragma index integrity foreign key current source next121 accepts source-only cursor'] = static function (TestRunner $t) use ($page): void {
    $first = $page(0, 5);
    $second = $page(5, 5, ['source_id' => $first['source_id']]);

    $t->same(5, $second['offset']);
    $t->same('pointer_map_conflict', $second['rows'][0]['source']);
};

$tests['pragma index integrity foreign key current source next121 source changes with database'] = static function (TestRunner $t) use ($page, $cleanDatabase): void {
    $dirty = $page();
    $clean = $page(0, 121, null, null, $cleanDatabase);

    $t->same(true, $dirty['source_id'] !== $clean['source_id']);
    $t->same(true, $dirty['current_source']['database'] !== $clean['current_source']['database']);
};

$tests['pragma index integrity foreign key current source next121 source changes with schemas'] = static function (TestRunner $t) use ($page, $schemas): void {
    $first = $page(0, 121, null, null, null, $schemas(4));
    $second = $page(0, 121, null, null, null, $schemas(5));

    $t->same(true, $first['source_id'] !== $second['source_id']);
    $t->same(11, $first['total']);
    $t->same(12, $second['total']);
};

$tests['pragma index integrity foreign key current source next121 source changes with index sql'] = static function (TestRunner $t) use ($page, $cleanDatabase): void {
    $first = $page(0, 121, null, 'PRAGMA main.index_xinfo(wp_options_name)', $cleanDatabase);
    $second = $page(0, 121, null, 'PRAGMA temp.index_xinfo(wp_options_temp_name)', $cleanDatabase);

    $t->same(true, $first['source_id'] !== $second['source_id']);
    $t->same('temp', $second['rows'][0]['schema']);
};

$tests['pragma index integrity foreign key current source next121 rejects stale source cursor'] = static function (TestRunner $t) use ($page, $cleanDatabase): void {
    $first = $page(0, 5);
    $t->throws(InvalidArgumentException::class, static fn () => $page(5, 5, ['source_id' => $first['source_id'], 'next_offset' => 5], null, $cleanDatabase));
};

$tests['pragma index integrity foreign key current source next121 rejects stale offset cursor'] = static function (TestRunner $t) use ($page): void {
    $first = $page(0, 5);
    $t->throws(InvalidArgumentException::class, static fn () => $page(6, 5, ['source_id' => $first['source_id'], 'next_offset' => 5]));
};

$tests['pragma index integrity foreign key current source next121 rejects negative offset'] = static function (TestRunner $t) use ($page): void {
    $t->throws(InvalidArgumentException::class, static fn () => $page(-1, 121));
};

$tests['pragma index integrity foreign key current source next121 rejects zero limit'] = static function (TestRunner $t) use ($page): void {
    $t->throws(InvalidArgumentException::class, static fn () => $page(0, 0));
};

$tests['pragma index integrity foreign key current source next121 rejects non index pragma'] = static function (TestRunner $t) use ($page): void {
    $t->throws(InvalidArgumentException::class, static fn () => $page(0, 121, null, 'PRAGMA table_info(wp_options)'));
};

return $tests;
