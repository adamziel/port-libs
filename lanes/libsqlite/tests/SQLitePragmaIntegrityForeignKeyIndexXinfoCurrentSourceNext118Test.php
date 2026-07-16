<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteAttachedSchemaCatalog;
use PortLibs\LibSqlite\SQLitePragmaIntegrityForeignKeyIndexCurrentSourceYield;
use PortLibs\LibSqlite\SQLiteSchemaRecord;

$record = static fn (string $type, string $name, string $table, ?int $root, ?string $sql = null, int $rowid = 1): SQLiteSchemaRecord => new SQLiteSchemaRecord(
    $type,
    $name,
    $table,
    $root,
    $sql,
    $rowid,
);

$catalogFactory = static function (bool $archiveUnique = true) use ($record): SQLiteAttachedSchemaCatalog {
    $catalog = new SQLiteAttachedSchemaCatalog([
        $record('table', 'wp_option_names', 'wp_option_names', 2, 'CREATE TABLE wp_option_names(name TEXT COLLATE NOCASE)', 1),
        $record('index', 'wp_option_names_name_u', 'wp_option_names', 3, 'CREATE UNIQUE INDEX wp_option_names_name_u ON wp_option_names(name COLLATE NOCASE)', 2),
        $record('table', 'wp_options', 'wp_options', 4, 'CREATE TABLE wp_options(option_id INTEGER PRIMARY KEY, option_name TEXT)', 3),
    ]);
    $catalog->attach('wp.archive', '/tmp/wp.archive.sqlite', [
        $record('table', 'wp_option_names', 'wp_option_names', 5, 'CREATE TABLE wp_option_names(name TEXT COLLATE NOCASE, blog_id INTEGER, PRIMARY KEY(name, blog_id)) WITHOUT ROWID', 1),
        $record(
            'index',
            $archiveUnique ? 'wp.archive.option_names.name.u' : 'wp.archive.option_names.name.idx',
            'wp_option_names',
            6,
            ($archiveUnique ? 'CREATE UNIQUE INDEX ' : 'CREATE INDEX ')
                . '"wp.archive.option_names.name.' . ($archiveUnique ? 'u' : 'idx') . '" ON wp_option_names(name COLLATE NOCASE DESC)',
            2,
        ),
        $record('table', 'wp_options', 'wp_options', 7, 'CREATE TABLE wp_options(option_id INTEGER PRIMARY KEY, option_name TEXT)', 3),
    ]);

    return $catalog;
};

$schemasFactory = static function (int $archiveMissing = 2): array {
    $archiveRows = [
        ['rowid' => 'archive-1', 'option_id' => 1, 'option_name' => 'legacy_siteurl'],
    ];
    for ($i = 1; $i <= $archiveMissing; $i++) {
        $archiveRows[] = ['rowid' => 'archive-missing-' . $i, 'option_id' => 10 + $i, 'option_name' => 'missing_' . $i];
    }

    return [
        'main' => [
            'tables' => [
                'wp_option_names' => [['rowid' => 1, 'name' => 'siteurl']],
                'wp_options' => [
                    ['rowid' => 'main-1', 'option_id' => 1, 'option_name' => 'siteurl'],
                    ['rowid' => 'main-missing', 'option_id' => 2, 'option_name' => 'missing_main'],
                ],
            ],
            'foreignKeys' => [
                ['id' => 1, 'table' => 'wp_options', 'parent' => 'wp_option_names', 'columns' => [
                    ['child' => 'option_name', 'parent' => 'name', 'affinity' => 'text', 'collation' => 'nocase'],
                ]],
            ],
        ],
        'wp.archive' => [
            'tables' => [
                'wp_option_names' => [['rowid' => 'archive-parent', 'name' => 'legacy_siteurl', 'blog_id' => 1]],
                'wp_options' => $archiveRows,
            ],
            'foreignKeys' => [
                ['id' => 8, 'table' => 'wp_options', 'parent' => 'wp_option_names', 'columns' => [
                    ['child' => 'option_name', 'parent' => 'name', 'affinity' => 'text', 'collation' => 'nocase'],
                ]],
            ],
        ],
    ];
};

$database = str_repeat("\0", 512);
$database = substr_replace($database, "SQLite format 3\0", 0, 16);
$database = substr_replace($database, pack('n', 512), 16, 2);
$database[18] = "\x01";
$database[19] = "\x01";
$database = substr_replace($database, pack('N', 1), 28, 4);
$database = substr_replace($database, pack('N', 1), 56, 4);

$catalog = $catalogFactory();
$schemas = $schemasFactory();
$page = static fn (int $offset = 0, int $limit = 118, ?array $cursor = null, string $integritySql = 'PRAGMA quick_check'): array => SQLitePragmaIntegrityForeignKeyIndexCurrentSourceYield::pageWithSourceCursor(
    $database,
    $schemas,
    $catalog,
    $offset,
    $limit,
    $integritySql,
    $cursor,
);
$mutatedCatalogPage = static fn (): array => SQLitePragmaIntegrityForeignKeyIndexCurrentSourceYield::pageWithSourceCursor(
    $database,
    $schemas,
    $catalogFactory(false),
    0,
    118,
    'PRAGMA quick_check',
);
$mutatedSchemasPage = static fn (): array => SQLitePragmaIntegrityForeignKeyIndexCurrentSourceYield::pageWithSourceCursor(
    $database,
    $schemasFactory(3),
    $catalog,
    0,
    118,
    'PRAGMA quick_check',
);

$valueAt = static function (mixed $value, string $path): mixed {
    foreach (explode('.', $path) as $part) {
        if ($part === 'count') {
            $value = count($value);
            continue;
        }
        $value = is_numeric($part) ? $value[(int) $part] : $value[$part];
    }

    return $value;
};

$cases = [
    'direct quoted qualified index schema' => [static fn (): array => $catalog->executeTableValuedPragma('pragma_index_xinfo("\'wp.archive\'.\'wp.archive.option_names.name.u\'")'), 'schema', 'wp.archive'],
    'direct quoted qualified index target' => [static fn (): array => $catalog->executeTableValuedPragma('pragma_index_xinfo("\'wp.archive\'.\'wp.archive.option_names.name.u\'")'), 'target', 'wp.archive.option_names.name.u'],
    'direct quoted qualified index row count' => [static fn (): array => $catalog->executeTableValuedPragma('pragma_index_xinfo("\'wp.archive\'.\'wp.archive.option_names.name.u\'")'), 'rows.count', 2],
    'direct quoted qualified index key collation' => [static fn (): array => $catalog->executeTableValuedPragma('pragma_index_xinfo("\'wp.archive\'.\'wp.archive.option_names.name.u\'")'), 'rows.0.coll', 'NOCASE'],
    'direct quoted qualified index desc' => [static fn (): array => $catalog->executeTableValuedPragma('pragma_index_xinfo("\'wp.archive\'.\'wp.archive.option_names.name.u\'")'), 'rows.0.desc', 1],
    'direct quoted qualified index without-rowid pk auxiliary' => [static fn (): array => $catalog->executeTableValuedPragma('pragma_index_xinfo("\'wp.archive\'.\'wp.archive.option_names.name.u\'")'), 'rows.1.name', 'blog_id'],
    'two-arg quoted schema index schema' => [static fn (): array => $catalog->executeTableValuedPragma("pragma_index_xinfo('wp.archive.option_names.name.u', 'wp.archive')"), 'schema', 'wp.archive'],
    'two-arg quoted schema index key name' => [static fn (): array => $catalog->executeTableValuedPragma("pragma_index_xinfo('wp.archive.option_names.name.u', 'wp.archive')"), 'rows.0.name', 'name'],
    'two-arg quoted schema index pk auxiliary' => [static fn (): array => $catalog->executeTableValuedPragma("pragma_index_xinfo('wp.archive.option_names.name.u', 'wp.archive')"), 'rows.1.cid', 1],
    'schema-qualified missing schema throws' => [static function () use ($catalog): array {
        try {
            $catalog->executeTableValuedPragma("pragma_index_xinfo('wp.archive.option_names.name.u')");
        } catch (InvalidArgumentException $exception) {
            return ['message' => $exception->getMessage()];
        }

        return ['message' => ''];
    }, 'message', 'SQLite schema wp is not attached'],
    'schema-qualified direct pragma index schema' => [static fn (): array => $catalog->executeSchemaPragma('PRAGMA index_xinfo("\'wp.archive\'.\'wp.archive.option_names.name.u\'")'), 'schema', 'wp.archive'],
    'schema-qualified direct pragma key collation' => [static fn (): array => $catalog->executeSchemaPragma('PRAGMA index_xinfo("\'wp.archive\'.\'wp.archive.option_names.name.u\'")'), 'rows.0.coll', 'NOCASE'],
    'schema-qualified direct pragma pk auxiliary key false' => [static fn (): array => $catalog->executeSchemaPragma('PRAGMA index_xinfo("\'wp.archive\'.\'wp.archive.option_names.name.u\'")'), 'rows.1.key', 0],
    'combined status blocked' => [$page, 'status', 'blocked'],
    'combined source id length' => [static fn (): array => ['len' => strlen($page()['source_id'])], 'len', 64],
    'combined catalog hash length' => [static fn (): array => ['len' => strlen($page()['current_source']['catalog'])], 'len', 64],
    'combined schema hash length' => [static fn (): array => ['len' => strlen($page()['current_source']['schemas'])], 'len', 64],
    'combined integrity normalized' => [$page, 'current_source.integrity_sql', 'pragma quick_check'],
    'combined total rows' => [$page, 'total', 5],
    'combined index admissions' => [$page, 'current.index_admissions', 2],
    'combined index blockers zero' => [$page, 'current.index_blockers', 0],
    'combined fk violations' => [$page, 'current.foreign_key_violations', 3],
    'combined integrity errors zero' => [$page, 'current.integrity_errors', 0],
    'combined main schema first' => [$page, 'current.schemas.0', 'main'],
    'combined archive schema second' => [$page, 'current.schemas.1', 'wp.archive'],
    'combined next ready false' => [$page, 'next_state.ready', false],
    'combined blocker fk' => [$page, 'next_state.blocking.0', 'foreign_key_check'],
    'combined row0 main index' => [$page, 'rows.0.index', 'wp_option_names_name_u'],
    'combined row1 main missing rowid' => [$page, 'rows.1.rowid', 'main-missing'],
    'combined row2 archive index' => [$page, 'rows.2.index', 'wp.archive.option_names.name.u'],
    'combined row2 archive collation' => [$page, 'rows.2.collations.0', 'NOCASE'],
    'combined row3 archive missing rowid' => [$page, 'rows.3.rowid', 'archive-missing-1'],
    'combined row4 archive missing rowid' => [$page, 'rows.4.rowid', 'archive-missing-2'],
    'mutated catalog changes source' => [static fn (): array => ['changed' => $page()['source_id'] !== $mutatedCatalogPage()['source_id']], 'changed', true],
    'mutated catalog adds index blocker' => [$mutatedCatalogPage, 'current.index_blockers', 1],
    'mutated catalog keeps archive schema name' => [$mutatedCatalogPage, 'rows.2.schema', 'wp.archive'],
    'mutated schema changes source' => [static fn (): array => ['changed' => $page()['source_id'] !== $mutatedSchemasPage()['source_id']], 'changed', true],
    'mutated schema adds fk row' => [$mutatedSchemasPage, 'current.foreign_key_violations', 4],
    'mutated schema final rowid' => [$mutatedSchemasPage, 'rows.5.rowid', 'archive-missing-3'],
];

$tests = [];
foreach ($cases as $name => [$callback, $path, $expected]) {
    $tests['pragma integrity foreign key index xinfo current source next118 ' . $name] = static function (TestRunner $t) use ($callback, $valueAt, $path, $expected): void {
        $t->same($expected, $valueAt($callback(), $path));
    };
}

$tests['pragma integrity foreign key index xinfo current source next118 resumes quoted attached source'] = static function (TestRunner $t) use ($page): void {
    $first = $page(0, 3);
    $second = $page(3, 3, ['source_id' => $first['source_id'], 'next_offset' => 3]);

    $t->same(3, $first['count']);
    $t->same(['source_id' => $first['source_id'], 'offset' => 3], $first['next']);
    $t->same('foreign_key', $second['rows'][0]['source']);
    $t->same('wp.archive', $second['rows'][0]['schema']);
    $t->same('archive-missing-1', $second['rows'][0]['rowid']);
    $t->same(null, $second['next']);
};

$tests['pragma integrity foreign key index xinfo current source next118 rejects stale quoted catalog cursor'] = static function (TestRunner $t) use ($page, $database, $schemas, $catalogFactory): void {
    $first = $page(0, 3);
    $t->throws(InvalidArgumentException::class, static fn () => SQLitePragmaIntegrityForeignKeyIndexCurrentSourceYield::pageWithSourceCursor($database, $schemas, $catalogFactory(false), 3, 3, 'PRAGMA quick_check', ['source_id' => $first['source_id'], 'next_offset' => 3]));
};

$tests['pragma integrity foreign key index xinfo current source next118 rejects stale quoted schema cursor'] = static function (TestRunner $t) use ($page, $database, $schemasFactory, $catalog): void {
    $first = $page(0, 3);
    $t->throws(InvalidArgumentException::class, static fn () => SQLitePragmaIntegrityForeignKeyIndexCurrentSourceYield::pageWithSourceCursor($database, $schemasFactory(3), $catalog, 3, 3, 'PRAGMA quick_check', ['source_id' => $first['source_id'], 'next_offset' => 3]));
};

$tests['pragma integrity foreign key index xinfo current source next118 rejects stale quoted offset'] = static function (TestRunner $t) use ($page): void {
    $first = $page(0, 3);
    $t->throws(InvalidArgumentException::class, static fn () => $page(4, 3, ['source_id' => $first['source_id'], 'next_offset' => 3]));
};

$tests['pragma integrity foreign key index xinfo current source next118 rejects missing quoted schema'] = static function (TestRunner $t) use ($catalog): void {
    $t->throws(InvalidArgumentException::class, static fn () => $catalog->executeTableValuedPragma('pragma_index_xinfo("\'missing.schema\'.\'wp.archive.option_names.name.u\'")'));
};

return $tests;
