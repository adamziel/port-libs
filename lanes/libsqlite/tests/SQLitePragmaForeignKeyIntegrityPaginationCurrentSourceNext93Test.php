<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteAttachedSchemaCatalog;
use PortLibs\LibSqlite\SQLitePointerMapEntry;
use PortLibs\LibSqlite\SQLitePragmaForeignKeyIntegrityPaginationCurrentSourceYield;
use PortLibs\LibSqlite\SQLiteSchemaRecord;

$record = static fn (string $type, string $name, string $table, ?int $root, ?string $sql = null, int $rowid = 1): SQLiteSchemaRecord => new SQLiteSchemaRecord(
    $type,
    $name,
    $table,
    $root,
    $sql,
    $rowid,
);

$pageSize = 512;
$pageCount = 58;
$putPointerMapEntry = static function (string $page, int $pageNumber, int $type, int $parent): string {
    return substr_replace($page, chr($type) . pack('N', $parent), 5 * ($pageNumber - 3), 5);
};
$makeDatabase = static function (bool $validPointers = false) use ($pageSize, $pageCount, $putPointerMapEntry): string {
    $header = str_repeat("\0", $pageSize);
    $header = substr_replace($header, "SQLite format 3\0", 0, 16);
    $header = substr_replace($header, pack('n', $pageSize), 16, 2);
    $header[18] = "\x01";
    $header[19] = "\x01";
    $header = substr_replace($header, pack('N', $pageCount), 28, 4);
    $header = substr_replace($header, pack('N', 3), 52, 4);
    $header = substr_replace($header, pack('N', 1), 56, 4);

    $pointerMap = str_repeat("\0", $pageSize);
    $pointerMap = $putPointerMapEntry($pointerMap, 3, SQLitePointerMapEntry::ROOT_PAGE, 0);
    for ($pageNumber = 4; $pageNumber <= $pageCount; $pageNumber++) {
        $pointerMap = $putPointerMapEntry(
            $pointerMap,
            $pageNumber,
            SQLitePointerMapEntry::BTREE_PAGE,
            $validPointers ? 3 : 0,
        );
    }

    $pages = [$header, $pointerMap];
    for ($pageNumber = 3; $pageNumber <= $pageCount; $pageNumber++) {
        $pages[] = str_repeat("\0", $pageSize);
    }

    return implode('', $pages);
};

$mainRecords = [
    $record('table', 'wp_posts', 'wp_posts', 2, 'CREATE TABLE wp_posts(ID INTEGER PRIMARY KEY)', 1),
    $record('table', 'wp_postmeta', 'wp_postmeta', 3, 'CREATE TABLE wp_postmeta(meta_id INTEGER PRIMARY KEY, post_id INTEGER)', 2),
    $record('table', 'wp_option_names', 'wp_option_names', 4, 'CREATE TABLE wp_option_names(name TEXT PRIMARY KEY)', 3),
    $record('table', 'wp_options', 'wp_options', 5, 'CREATE TABLE wp_options(option_id INTEGER PRIMARY KEY, option_name TEXT)', 4),
];
$tempRecords = [
    $record('table', 'wp_option_names', 'wp_option_names', 6, 'CREATE TABLE wp_option_names(name TEXT PRIMARY KEY)', 1),
    $record('table', 'wp_options', 'wp_options', 7, 'CREATE TABLE wp_options(option_id INTEGER PRIMARY KEY, option_name TEXT)', 2),
];
$archiveRecords = [
    $record('table', 'wp_option_names', 'wp_option_names', 8, 'CREATE TABLE wp_option_names(name TEXT PRIMARY KEY)', 1),
    $record('table', 'wp_options', 'wp_options', 9, 'CREATE TABLE wp_options(option_id INTEGER PRIMARY KEY, option_name TEXT)', 2),
];

$catalog = new SQLiteAttachedSchemaCatalog($mainRecords, $tempRecords);
$catalog->attach('archive', '/tmp/wp-archive.sqlite', $archiveRecords);

$schemas = [
    'main' => [
        'tables' => [
            'wp_posts' => [['rowid' => 1, 'ID' => 1]],
            'wp_postmeta' => [
                ['rowid' => 11, 'meta_id' => 11, 'post_id' => 1],
                ['rowid' => 12, 'meta_id' => 12, 'post_id' => 404],
            ],
            'wp_option_names' => [['rowid' => 1, 'name' => 'siteurl']],
            'wp_options' => [
                ['rowid' => 21, 'option_id' => 21, 'option_name' => 'siteurl'],
                ['rowid' => 22, 'option_id' => 22, 'option_name' => 'missing_main'],
            ],
        ],
        'foreignKeys' => [
            ['id' => 1, 'table' => 'wp_postmeta', 'parent' => 'wp_posts', 'columns' => [['child' => 'post_id', 'parent' => 'ID', 'affinity' => 'integer']]],
            ['id' => 2, 'table' => 'wp_options', 'parent' => 'wp_option_names', 'columns' => [['child' => 'option_name', 'parent' => 'name', 'affinity' => 'text']]],
        ],
    ],
    'temp' => [
        'tables' => [
            'wp_option_names' => [['rowid' => 1, 'name' => 'siteurl']],
            'wp_options' => [
                ['rowid' => 'temp-1', 'option_id' => 1, 'option_name' => 'siteurl'],
                ['rowid' => 'temp-2', 'option_id' => 2, 'option_name' => 'temp_missing'],
                ['rowid' => 'temp-3', 'option_id' => 3, 'option_name' => 'temp_missing_2'],
            ],
        ],
        'foreignKeys' => [
            ['id' => 3, 'table' => 'wp_options', 'parent' => 'wp_option_names', 'columns' => [['child' => 'option_name', 'parent' => 'name', 'affinity' => 'text']]],
        ],
    ],
    'archive' => [
        'tables' => [
            'wp_option_names' => [['rowid' => 1, 'name' => 'legacy_siteurl']],
            'wp_options' => [
                ['rowid' => 'archive-1', 'option_id' => 1, 'option_name' => 'legacy_siteurl'],
                ['rowid' => 'archive-2', 'option_id' => 2, 'option_name' => 'archive_missing'],
            ],
        ],
        'foreignKeys' => [
            ['id' => 4, 'table' => 'wp_options', 'parent' => 'wp_option_names', 'columns' => [['child' => 'option_name', 'parent' => 'name', 'affinity' => 'text']]],
        ],
    ],
];

$database = $makeDatabase();
$cleanDatabase = $makeDatabase(true);
$generation = $catalog->schemaGeneration();

$page = static fn (int $offset = 0, int $limit = 93, string $sql = "SELECT * FROM pragma_foreign_key_check('wp_options')", ?int $expectedGeneration = null, string $integritySql = 'PRAGMA integrity_check'): array => SQLitePragmaForeignKeyIntegrityPaginationCurrentSourceYield::page(
    $database,
    $schemas,
    $catalog,
    $sql,
    $offset,
    $limit,
    $integritySql,
    $expectedGeneration,
);
$cleanPage = static fn (): array => SQLitePragmaForeignKeyIntegrityPaginationCurrentSourceYield::page($cleanDatabase, $schemas, $catalog, 'PRAGMA archive.foreign_key_check(wp_options)', 0, 93, 'PRAGMA quick_check', $generation);

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
    'status ok' => [$page, 'status', 'ok'],
    'default limit next93' => [$page, 'limit', 93],
    'offset zero' => [$page, 'offset', 0],
    'total pointer plus current source fks' => [$page, 'total', 57],
    'count first page' => [$page, 'count', 57],
    'complete first page' => [$page, 'complete', true],
    'next offset null' => [$page, 'next_offset', null],
    'current pointer map count' => [$page, 'current.pointer_map', 55],
    'current foreign key count' => [$page, 'current.foreign_key', 2],
    'current source pragma' => [$page, 'current_source.pragma', 'foreign_key_check'],
    'current source resolves temp' => [$page, 'current_source.schema', 'temp'],
    'current source target schema' => [$page, 'current_source.target_schema', 'temp'],
    'current source target' => [$page, 'current_source.target', 'wp_options'],
    'current source catalog' => [$page, 'current_source.target_source', 'catalog-current'],
    'current source generation' => [$page, 'current_source.schema_generation', $generation],
    'current source expected null' => [$page, 'current_source.expected_schema_generation', null],
    'current source current true' => [$page, 'current_source.schema_current', true],
    'current source search temp' => [$page, 'current_source.search_order.0', 'temp'],
    'current source search main' => [$page, 'current_source.search_order.1', 'main'],
    'current source search archive' => [$page, 'current_source.search_order.2', 'archive'],
    'first row pointer source' => [$page, 'rows.0.source', 'pointer_map'],
    'first row page' => [$page, 'rows.0.page', 4],
    'last pointer page' => [$page, 'rows.54.page', 58],
    'first fk source' => [$page, 'rows.55.source', 'foreign_key'],
    'first fk schema' => [$page, 'rows.55.schema', 'temp'],
    'first fk rowid' => [$page, 'rows.55.rowid', 'temp-2'],
    'second fk rowid' => [$page, 'rows.56.rowid', 'temp-3'],
    'second fk message' => [$page, 'rows.56.message', 'foreign key mismatch in temp.wp_options rowid temp-3 references wp_option_names fkid 3'],
    'next ready complete true' => [$page, 'next.ready', true],
    'next blocking complete empty' => [$page, 'next.blocking.count', 0],
    'next resume offset null' => [$page, 'next.resume_offset', null],
    'next first boundary source' => [$page, 'next.first_row.source', 'pointer_map'],
    'next first boundary page' => [$page, 'next.first_row.page', 4],
    'next last boundary source' => [$page, 'next.last_row.source', 'foreign_key'],
    'next last boundary rowid' => [$page, 'next.last_row.rowid', 'temp-3'],
    'middle page count' => [static fn (): array => $page(53, 3), 'count', 3],
    'middle page next offset' => [static fn (): array => $page(53, 3), 'next_offset', 56],
    'middle page incomplete' => [static fn (): array => $page(53, 3), 'complete', false],
    'middle page next ready false' => [static fn (): array => $page(53, 3), 'next.ready', false],
    'middle page blocker pending' => [static fn (): array => $page(53, 3), 'next.blocking.0', 'foreign_key_integrity_page_pending'],
    'middle page boundary first page' => [static fn (): array => $page(53, 3), 'next.first_row.page', 57],
    'middle page boundary last rowid' => [static fn (): array => $page(53, 3), 'next.last_row.rowid', 'temp-2'],
    'tail page count' => [static fn (): array => $page(56, 93), 'count', 1],
    'tail page complete' => [static fn (): array => $page(56, 93), 'complete', true],
    'tail page rowid' => [static fn (): array => $page(56, 93), 'rows.0.rowid', 'temp-3'],
    'qualified pragma source' => [static fn (): array => $page(0, 93, 'PRAGMA main.foreign_key_check(wp_options)'), 'current_source.target_source', 'pragma-schema'],
    'qualified pragma schema' => [static fn (): array => $page(0, 93, 'PRAGMA main.foreign_key_check(wp_options)'), 'current_source.schema', 'main'],
    'qualified pragma rowid' => [static fn (): array => $page(0, 93, 'PRAGMA main.foreign_key_check(wp_options)'), 'rows.55.rowid', 22],
    'archive clean status' => [$cleanPage, 'status', 'ok'],
    'archive clean expected generation' => [$cleanPage, 'current_source.expected_schema_generation', $generation],
    'archive clean pointer zero' => [$cleanPage, 'current.pointer_map', 0],
    'archive clean fk count' => [$cleanPage, 'current.foreign_key', 1],
    'archive clean schema source' => [$cleanPage, 'current_source.target_source', 'pragma-schema'],
    'archive clean rowid' => [$cleanPage, 'rows.0.rowid', 'archive-2'],
    'archive clean boundary rowid' => [$cleanPage, 'next.last_row.rowid', 'archive-2'],
];

$tests = [];
foreach ($cases as $name => [$callback, $path, $expected]) {
    $tests['pragma foreign key integrity pagination current source next93 ' . $name] = static function (TestRunner $t) use ($callback, $valueAt, $path, $expected): void {
        $t->same($expected, $valueAt($callback(), $path));
    };
}

$tests['pragma foreign key integrity pagination current source next93 stale generation blocks resume'] = static function (TestRunner $t) use ($page, $generation): void {
    $stale = $page(12, 5, "SELECT * FROM pragma_foreign_key_check('wp_options')", $generation - 1);
    $t->same([
        'status' => 'stale',
        'count' => 0,
        'total' => 0,
        'schema_current' => false,
        'blocking' => ['schema_cache_stale'],
        'rows' => [],
    ], [
        'status' => $stale['status'],
        'count' => $stale['count'],
        'total' => $stale['total'],
        'schema_current' => $stale['current_source']['schema_current'],
        'blocking' => $stale['next']['blocking'],
        'rows' => $stale['rows'],
    ]);
};

$tests['pragma foreign key integrity pagination current source next93 rejects invalid sql'] = static function (TestRunner $t) use ($page): void {
    $t->throws(InvalidArgumentException::class, static fn () => $page(0, 93, 'SELECT rowid FROM pragma_foreign_key_check(wp_options)'));
};

$tests['pragma foreign key integrity pagination current source next93 rejects negative offset'] = static function (TestRunner $t) use ($page): void {
    $t->throws(InvalidArgumentException::class, static fn () => $page(-1, 93));
};

$tests['pragma foreign key integrity pagination current source next93 rejects zero limit'] = static function (TestRunner $t) use ($page): void {
    $t->throws(InvalidArgumentException::class, static fn () => $page(0, 0));
};

return $tests;
