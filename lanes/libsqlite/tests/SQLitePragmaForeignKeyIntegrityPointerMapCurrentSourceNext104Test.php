<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteAttachedSchemaCatalog;
use PortLibs\LibSqlite\SQLitePointerMapEntry;
use PortLibs\LibSqlite\SQLitePragmaForeignKeyIntegrityPointerMapCurrentSourceYield;
use PortLibs\LibSqlite\SQLiteSchemaRecord;

$pageSize = 512;
$pageCount = 12;
$currentSource = '908aa30c6c7fe78015274fb8c1517585dbb50c17';
$nextSource = 'pragma-foreign-key-integrity-pointermap-current-source-next104';

$putPointerMapEntry = static function (string $page, int $pageNumber, int $type, int $parent): string {
    return substr_replace($page, chr($type) . pack('N', $parent), 5 * ($pageNumber - 3), 5);
};

$databaseFactory = static function (int $pageCount = 12, int $badParent = 0) use ($pageSize, $putPointerMapEntry): string {
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
    $pointerMap = $putPointerMapEntry($pointerMap, 4, SQLitePointerMapEntry::BTREE_PAGE, $badParent);
    $pointerMap = $putPointerMapEntry($pointerMap, 5, SQLitePointerMapEntry::BTREE_PAGE, 3);
    $pointerMap = $putPointerMapEntry($pointerMap, 6, SQLitePointerMapEntry::FIRST_OVERFLOW_PAGE, $badParent);
    $pointerMap = $putPointerMapEntry($pointerMap, 7, SQLitePointerMapEntry::OVERFLOW_PAGE, 6);
    for ($pageNumber = 8; $pageNumber <= $pageCount; $pageNumber++) {
        $pointerMap = $putPointerMapEntry($pointerMap, $pageNumber, SQLitePointerMapEntry::BTREE_PAGE, 3);
    }

    $pages = [1 => $header, 2 => $pointerMap];
    for ($pageNumber = 3; $pageNumber <= $pageCount; $pageNumber++) {
        $pages[$pageNumber] = str_repeat("\0", $pageSize);
    }
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

$catalogFactory = static function () use ($record): SQLiteAttachedSchemaCatalog {
    $catalog = new SQLiteAttachedSchemaCatalog([
        $record('wp_options', 3),
        $record('wp_option_names', 4),
    ]);
    $catalog->attach('archive', '/srv/wp/archive.sqlite', [
        $record('wp_options', 5),
        $record('wp_option_names', 6),
    ]);

    return $catalog;
};

$schemasFactory = static function (int $archiveExtra = 0): array {
    $archiveRows = [
        ['rowid' => 'archive-1', 'option_name' => 'legacy_siteurl'],
        ['rowid' => 'archive-2', 'option_name' => 'missing_archive'],
    ];
    for ($i = 1; $i <= $archiveExtra; $i++) {
        $archiveRows[] = ['rowid' => 'archive-extra-' . $i, 'option_name' => 'missing_extra_' . $i];
    }

    return [
        'main' => [
            'tables' => [
                'wp_option_names' => [['rowid' => 1, 'name' => 'siteurl']],
                'wp_options' => [
                    ['rowid' => 1, 'option_name' => 'siteurl'],
                    ['rowid' => 2, 'option_name' => 'missing_main'],
                ],
            ],
            'foreignKeys' => [
                ['id' => 4, 'table' => 'wp_options', 'parent' => 'wp_option_names', 'columns' => [
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
                ['id' => 9, 'table' => 'wp_options', 'parent' => 'wp_option_names', 'columns' => [
                    ['child' => 'option_name', 'parent' => 'name', 'affinity' => 'text', 'collation' => 'nocase'],
                ]],
            ],
        ],
    ];
};

$database = $databaseFactory($pageCount, 0);
$mutatedDatabase = $databaseFactory($pageCount, 99);
$schemas = $schemasFactory();
$catalog = $catalogFactory();
$statementSql = 'PRAGMA foreign_key_check(archive.wp_options)';
$tableValuedSql = "SELECT * FROM pragma_foreign_key_check('archive.wp_options')";

$page = static fn (int $offset = 0, int $limit = 2, ?array $cursor = null, bool $tableValued = true, string $integritySql = 'PRAGMA integrity_check'): array => SQLitePragmaForeignKeyIntegrityPointerMapCurrentSourceYield::page(
    $database,
    $schemas,
    $tableValued ? $tableValuedSql : $statementSql,
    $offset,
    $limit,
    $integritySql,
    $cursor,
    $catalog,
    $tableValued,
);
$changedSchemaPage = static fn (): array => SQLitePragmaForeignKeyIntegrityPointerMapCurrentSourceYield::page($database, $schemasFactory(2), $tableValuedSql, 0, 99, 'PRAGMA integrity_check', null, $catalog, true);
$changedDatabasePage = static fn (): array => SQLitePragmaForeignKeyIntegrityPointerMapCurrentSourceYield::page($mutatedDatabase, $schemas, $tableValuedSql, 0, 99, 'PRAGMA integrity_check', null, $catalog, true);
$quickPage = static fn (): array => SQLitePragmaForeignKeyIntegrityPointerMapCurrentSourceYield::page($database, $schemas, $tableValuedSql, 0, 99, 'PRAGMA quick_check', null, $catalog, true);

$valueAt = static function (mixed $value, string $path): mixed {
    foreach (explode('.', $path) as $part) {
        if ($part === 'count' && is_array($value) && !array_key_exists('count', $value)) {
            $value = count($value);
            continue;
        }
        $value = is_array($value) && array_key_exists($part, $value) ? $value[$part] : $value[(int) $part];
    }

    return $value;
};

$cases = [
    'blocked status' => [$page, 'status', 'blocked'],
    'source id length' => [static fn (): array => ['len' => strlen($page()['source_id'])], 'len', 64],
    'database source length' => [static fn (): array => ['len' => strlen($page()['current_source']['database'])], 'len', 64],
    'schema source length' => [static fn (): array => ['len' => strlen($page()['current_source']['schemas'])], 'len', 64],
    'catalog source length' => [static fn (): array => ['len' => strlen((string) $page()['current_source']['catalog'])], 'len', 64],
    'integrity sql normalized' => [$page, 'current_source.integrity_sql', 'pragma integrity_check'],
    'foreign key sql normalized' => [$page, 'current_source.foreign_key_sql', "select * from pragma_foreign_key_check('archive.wp_options')"],
    'table valued true' => [$page, 'current_source.table_valued', true],
    'offset zero' => [$page, 'offset', 0],
    'limit two' => [$page, 'limit', 2],
    'count two' => [$page, 'count', 2],
    'complete false' => [$page, 'complete', false],
    'next offset two' => [$page, 'next_offset', 2],
    'next token offset two' => [$page, 'next.offset', 2],
    'pointer map count' => [$page, 'current.pointer_map', 2],
    'foreign key count' => [$page, 'current.foreign_key', 1],
    'current blocked' => [$page, 'current.blocked', true],
    'current schema archive' => [$page, 'current.schemas.0', 'archive'],
    'current table wp_options' => [$page, 'current.tables.0', 'wp_options'],
    'next ready false' => [$page, 'next_state.ready', false],
    'blocker pointer map' => [$page, 'next_state.blocking.0', 'integrity_pointer_map'],
    'blocker foreign key' => [$page, 'next_state.blocking.1', 'foreign_key_check'],
    'first row source' => [$page, 'rows.0.source', 'pointer_map'],
    'first row page' => [$page, 'rows.0.page', 4],
    'first row pointer map page' => [$page, 'rows.0.pointer_map_page', 2],
    'first row ordinal' => [$page, 'rows.0.ordinal', 0],
    'first row blocking' => [$page, 'rows.0.blocking', true],
    'first row source tag' => [$page, 'rows.0.source_id', $page()['source_id']],
    'second row source' => [$page, 'rows.1.source', 'pointer_map'],
    'second row page' => [$page, 'rows.1.page', 6],
    'foreign key row source' => [static fn (): array => $page(2, 2, $page()['next']), 'rows.0.source', 'foreign_key'],
    'foreign key row schema' => [static fn (): array => $page(2, 2, $page()['next']), 'rows.0.schema', 'archive'],
    'foreign key row table' => [static fn (): array => $page(2, 2, $page()['next']), 'rows.0.table', 'wp_options'],
    'foreign key row rowid' => [static fn (): array => $page(2, 2, $page()['next']), 'rows.0.rowid', 'archive-2'],
    'foreign key row parent' => [static fn (): array => $page(2, 2, $page()['next']), 'rows.0.parent', 'wp_option_names'],
    'foreign key row fkid' => [static fn (): array => $page(2, 2, $page()['next']), 'rows.0.fkid', 9],
    'foreign key row ordinal' => [static fn (): array => $page(2, 2, $page()['next']), 'rows.0.ordinal', 2],
    'foreign key row blocking' => [static fn (): array => $page(2, 2, $page()['next']), 'rows.0.blocking', true],
    'quick skips pointer map' => [$quickPage, 'current.pointer_map', 0],
    'quick keeps foreign key' => [$quickPage, 'current.foreign_key', 1],
    'quick blocker is fk' => [$quickPage, 'next_state.blocking.0', 'foreign_key_check'],
    'quick table valued source differs' => [static fn (): array => ['changed' => $quickPage()['source_id'] !== $page()['source_id']], 'changed', true],
    'statement source table valued false' => [static fn (): array => SQLitePragmaForeignKeyIntegrityPointerMapCurrentSourceYield::page($database, $schemas, $statementSql, 0, 7, 'PRAGMA integrity_check', null, $catalog, false), 'current_source.table_valued', false],
    'statement source differs' => [static fn (): array => ['changed' => SQLitePragmaForeignKeyIntegrityPointerMapCurrentSourceYield::page($database, $schemas, $statementSql, 0, 7, 'PRAGMA integrity_check', null, $catalog, false)['source_id'] !== $page()['source_id']], 'changed', true],
    'schema change source differs' => [static fn (): array => ['changed' => $changedSchemaPage()['source_id'] !== $page()['source_id']], 'changed', true],
    'schema change database same' => [static fn (): array => ['same' => $changedSchemaPage()['current_source']['database'] === $page()['current_source']['database']], 'same', true],
    'schema change fk count' => [$changedSchemaPage, 'current.foreign_key', 3],
    'database change source differs' => [static fn (): array => ['changed' => $changedDatabasePage()['source_id'] !== $page()['source_id']], 'changed', true],
    'database change schema same' => [static fn (): array => ['same' => $changedDatabasePage()['current_source']['schemas'] === $page()['current_source']['schemas']], 'same', true],
    'database change pointer rows' => [$changedDatabasePage, 'current.pointer_map', 2],
    'tail page complete' => [static fn (): array => $page(2, 2, $page()['next']), 'complete', true],
    'tail count' => [static fn (): array => $page(2, 2, $page()['next']), 'count', 1],
    'tail first ordinal' => [static fn (): array => $page(2, 2, $page()['next']), 'rows.0.ordinal', 2],
    'tail next null' => [static fn (): array => $page(2, 2, $page()['next']), 'next', null],
];

$tests = [];
foreach ($cases as $name => [$callback, $path, $expected]) {
    $tests['pragma foreign key integrity pointermap current source next104 ' . $name] = static function (TestRunner $t) use ($callback, $valueAt, $path, $expected): void {
        $t->same($expected, $valueAt($callback(), $path));
    };
}

$tests['pragma foreign key integrity pointermap current source next104 accepts legacy next_offset cursor'] = static function (TestRunner $t) use ($page): void {
    $first = $page(0, 2);
    $second = $page(2, 2, ['source_id' => $first['source_id'], 'next_offset' => 2]);

    $t->same(2, $second['offset']);
    $t->same(2, $second['rows'][0]['ordinal']);
    $t->same(null, $second['next']);
};

$tests['pragma foreign key integrity pointermap current source next104 accepts source-only manual seek cursor'] = static function (TestRunner $t) use ($page): void {
    $first = $page(0, 2);
    $manual = $page(2, 3, ['source_id' => $first['source_id']]);

    $t->same(2, $manual['offset']);
    $t->same('foreign_key', $manual['rows'][0]['source']);
    $t->same(2, $manual['rows'][0]['ordinal']);
};

$tests['pragma foreign key integrity pointermap current source next104 rejects stale offset cursor'] = static function (TestRunner $t) use ($page): void {
    $first = $page(0, 2);
    $t->throws(InvalidArgumentException::class, static fn () => $page(3, 2, $first['next']));
};

$tests['pragma foreign key integrity pointermap current source next104 rejects stale schema cursor'] = static function (TestRunner $t) use ($database, $schemasFactory, $catalog, $tableValuedSql, $page): void {
    $first = $page(0, 2);
    $t->throws(InvalidArgumentException::class, static fn () => SQLitePragmaForeignKeyIntegrityPointerMapCurrentSourceYield::page($database, $schemasFactory(2), $tableValuedSql, 2, 2, 'PRAGMA integrity_check', $first['next'], $catalog, true));
};

$tests['pragma foreign key integrity pointermap current source next104 rejects stale database cursor'] = static function (TestRunner $t) use ($mutatedDatabase, $schemas, $catalog, $tableValuedSql, $page): void {
    $first = $page(0, 2);
    $t->throws(InvalidArgumentException::class, static fn () => SQLitePragmaForeignKeyIntegrityPointerMapCurrentSourceYield::page($mutatedDatabase, $schemas, $tableValuedSql, 2, 2, 'PRAGMA integrity_check', $first['next'], $catalog, true));
};

$tests['pragma foreign key integrity pointermap current source next104 rejects negative offset'] = static function (TestRunner $t) use ($database, $schemas, $catalog, $tableValuedSql): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLitePragmaForeignKeyIntegrityPointerMapCurrentSourceYield::page($database, $schemas, $tableValuedSql, -1, 7, 'PRAGMA integrity_check', null, $catalog, true));
};

$tests['pragma foreign key integrity pointermap current source next104 rejects zero limit'] = static function (TestRunner $t) use ($database, $schemas, $catalog, $tableValuedSql): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLitePragmaForeignKeyIntegrityPointerMapCurrentSourceYield::page($database, $schemas, $tableValuedSql, 0, 0, 'PRAGMA integrity_check', null, $catalog, true));
};

return $tests;
