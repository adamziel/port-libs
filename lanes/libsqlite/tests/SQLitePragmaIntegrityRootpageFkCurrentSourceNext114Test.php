<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteAttachedSchemaCatalog;
use PortLibs\LibSqlite\SQLitePragmaIntegrityRootpageForeignKeyCurrentSourceYield;
use PortLibs\LibSqlite\SQLiteSchemaRecord;

$record = static fn (string $name, int $root, ?string $sql = null): SQLiteSchemaRecord => new SQLiteSchemaRecord(
    'table',
    $name,
    $name,
    $root,
    $sql ?? 'CREATE TABLE ' . $name,
    $root,
);

$catalogFactory = static function (bool $withTemp = false, bool $withParent = true) use ($record): SQLiteAttachedSchemaCatalog {
    $catalog = new SQLiteAttachedSchemaCatalog(
        [
            $record('wp_terms', 2, 'CREATE TABLE wp_terms(term_id INTEGER PRIMARY KEY, slug TEXT)'),
            $record('wp_term_taxonomy', 3, 'CREATE TABLE wp_term_taxonomy(term_taxonomy_id INTEGER PRIMARY KEY, term_id INTEGER)'),
            ...($withParent ? [$record('wp_option_names', 4, 'CREATE TABLE wp_option_names(name TEXT PRIMARY KEY)')] : []),
            $record('wp_options', 5, 'CREATE TABLE wp_options(option_id INTEGER PRIMARY KEY, option_name TEXT)'),
        ],
        $withTemp ? [
            $record('wp_option_names', 6, 'CREATE TABLE wp_option_names(name TEXT PRIMARY KEY)'),
            $record('wp_options', 7, 'CREATE TABLE wp_options(option_id INTEGER PRIMARY KEY, option_name TEXT)'),
        ] : [],
    );
    $catalog->attach('archive', '/tmp/wp-archive.sqlite', [
        $record('wp_terms', 8, 'CREATE TABLE wp_terms(term_id INTEGER PRIMARY KEY, slug TEXT)'),
        $record('wp_term_taxonomy', 9, 'CREATE TABLE wp_term_taxonomy(term_taxonomy_id INTEGER PRIMARY KEY, term_id INTEGER)'),
    ]);

    return $catalog;
};

$schemas = [
    'main' => [
        'tables' => [
            'wp_terms' => [['rowid' => 1, 'term_id' => 1, 'slug' => 'news']],
            'wp_term_taxonomy' => [
                ['rowid' => 11, 'term_taxonomy_id' => 11, 'term_id' => 1],
                ['rowid' => 12, 'term_taxonomy_id' => 12, 'term_id' => 404],
            ],
            'wp_option_names' => [['rowid' => 1, 'name' => 'siteurl']],
            'wp_options' => [
                ['rowid' => 21, 'option_id' => 21, 'option_name' => 'siteurl'],
                ['rowid' => 22, 'option_id' => 22, 'option_name' => 'missing_option'],
            ],
        ],
        'foreignKeys' => [
            ['id' => 1, 'table' => 'wp_term_taxonomy', 'parent' => 'wp_terms', 'columns' => [['child' => 'term_id', 'parent' => 'term_id', 'affinity' => 'integer']]],
            ['id' => 2, 'table' => 'wp_options', 'parent' => 'wp_option_names', 'columns' => [['child' => 'option_name', 'parent' => 'name', 'affinity' => 'text', 'collation' => 'nocase']]],
        ],
    ],
    'temp' => [
        'tables' => [
            'wp_option_names' => [['rowid' => 1, 'name' => 'siteurl']],
            'wp_options' => [
                ['rowid' => 'temp-1', 'option_id' => 1, 'option_name' => 'siteurl'],
                ['rowid' => 'temp-2', 'option_id' => 2, 'option_name' => 'temp_missing'],
            ],
        ],
        'foreignKeys' => [
            ['id' => 3, 'table' => 'wp_options', 'parent' => 'wp_option_names', 'columns' => [['child' => 'option_name', 'parent' => 'name', 'affinity' => 'text', 'collation' => 'nocase']]],
        ],
    ],
    'archive' => [
        'tables' => [
            'wp_terms' => [['rowid' => 1, 'term_id' => 1, 'slug' => 'legacy']],
            'wp_term_taxonomy' => [
                ['rowid' => 'archive-1', 'term_taxonomy_id' => 1, 'term_id' => 1],
                ['rowid' => 'archive-2', 'term_taxonomy_id' => 2, 'term_id' => 99],
            ],
        ],
        'foreignKeys' => [
            ['id' => 4, 'table' => 'wp_term_taxonomy', 'parent' => 'wp_terms', 'columns' => [['child' => 'term_id', 'parent' => 'term_id', 'affinity' => 'integer']]],
        ],
    ],
];

$database = str_repeat("\0", 512);
$database = substr_replace($database, "SQLite format 3\0", 0, 16);
$database = substr_replace($database, pack('n', 512), 16, 2);
$database[18] = "\x01";
$database[19] = "\x01";
$database = substr_replace($database, pack('N', 1), 28, 4);
$database = substr_replace($database, pack('N', 1), 56, 4);

$shortDatabase = str_repeat("\0", 20);
$rootErrorDatabase = substr_replace($database, pack('N', 9), 52, 4);
$catalog = $catalogFactory();
$tempCatalog = $catalogFactory(true);
$missingParentCatalog = $catalogFactory(false, false);

$page = static fn (int $offset = 0, int $limit = 114, ?array $cursor = null, string $sql = "PRAGMA foreign_key_check('wp_term_taxonomy')", string $integritySql = 'PRAGMA quick_check'): array => SQLitePragmaIntegrityRootpageForeignKeyCurrentSourceYield::page($database, $schemas, $catalog, $sql, $offset, $limit, $integritySql, $cursor);
$tempPage = static fn (): array => SQLitePragmaIntegrityRootpageForeignKeyCurrentSourceYield::page($database, $schemas, $tempCatalog, "SELECT * FROM pragma_foreign_key_check('wp_options')", 0, 114, 'PRAGMA quick_check');
$missingParentPage = static fn (): array => SQLitePragmaIntegrityRootpageForeignKeyCurrentSourceYield::page($database, $schemas, $missingParentCatalog, "PRAGMA foreign_key_check('wp_options')", 0, 114, 'PRAGMA quick_check');
$rootErrorPage = static fn (): array => SQLitePragmaIntegrityRootpageForeignKeyCurrentSourceYield::page($rootErrorDatabase, $schemas, $catalog, "PRAGMA foreign_key_check('archive.wp_term_taxonomy')", 0, 114, 'PRAGMA integrity_check');

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
    'statement status blocked' => [$page, 'status', 'blocked'],
    'source id length' => [static fn (): array => ['length' => strlen($page()['source_id'])], 'length', 64],
    'database source length' => [static fn (): array => ['length' => strlen($page()['current_source']['database'])], 'length', 64],
    'catalog source length' => [static fn (): array => ['length' => strlen($page()['current_source']['catalog'])], 'length', 64],
    'schemas source length' => [static fn (): array => ['length' => strlen($page()['current_source']['schemas'])], 'length', 64],
    'foreign key sql normalized' => [$page, 'current_source.foreign_key_sql', "pragma foreign_key_check('wp_term_taxonomy')"],
    'integrity sql normalized' => [$page, 'current_source.integrity_sql', 'pragma quick_check'],
    'statement total' => [$page, 'total', 1],
    'statement count' => [$page, 'count', 1],
    'statement complete' => [$page, 'complete', true],
    'statement next null' => [$page, 'next', null],
    'statement next ready false' => [$page, 'next_state.ready', false],
    'statement blocker fk' => [$page, 'next_state.blocking.0', 'foreign_key_check'],
    'statement current fk violations' => [$page, 'current.foreign_key_violations', 1],
    'statement current root errors' => [$page, 'current.rootpage_errors', 0],
    'statement current missing rootpages' => [$page, 'current.missing_rootpages', 0],
    'statement current schema' => [$page, 'current.schemas.0', 'main'],
    'statement row source' => [$page, 'rows.0.source', 'foreign_key'],
    'statement row schema' => [$page, 'rows.0.schema', 'main'],
    'statement row table' => [$page, 'rows.0.table', 'wp_term_taxonomy'],
    'statement rowid' => [$page, 'rows.0.rowid', 12],
    'statement parent' => [$page, 'rows.0.parent', 'wp_terms'],
    'statement fkid' => [$page, 'rows.0.fkid', 1],
    'statement child rootpage' => [$page, 'rows.0.child_rootpage', 3],
    'statement parent rootpage' => [$page, 'rows.0.parent_rootpage', 2],
    'statement rootpage ok' => [$page, 'rows.0.rootpage_status', 'ok'],
    'statement message with rootpages' => [$page, 'rows.0.message', 'foreign key mismatch in main.wp_term_taxonomy rowid 12 references wp_terms fkid 1 (child rootpage 3, parent rootpage 2)'],
    'table valued temp total' => [$tempPage, 'total', 1],
    'table valued temp schema' => [$tempPage, 'rows.0.schema', 'temp'],
    'table valued temp rowid' => [$tempPage, 'rows.0.rowid', 'temp-2'],
    'table valued temp child rootpage' => [$tempPage, 'rows.0.child_rootpage', 7],
    'table valued temp parent rootpage' => [$tempPage, 'rows.0.parent_rootpage', 6],
    'table valued temp sql normalized' => [$tempPage, 'current_source.foreign_key_sql', "select * from pragma_foreign_key_check('wp_options')"],
    'missing parent current missing count' => [$missingParentPage, 'current.missing_rootpages', 1],
    'missing parent blocker root catalog' => [$missingParentPage, 'next_state.blocking.1', 'foreign_key_rootpage_catalog'],
    'missing parent root status' => [$missingParentPage, 'rows.0.rootpage_status', 'missing'],
    'missing parent root null' => [$missingParentPage, 'rows.0.parent_rootpage', null],
    'missing parent message' => [$missingParentPage, 'rows.0.message', 'foreign key mismatch in main.wp_options rowid 22 references wp_option_names fkid 2 (child rootpage 5, missing parent rootpage)'],
    'root error total' => [$rootErrorPage, 'total', 2],
    'root error fk row first' => [$rootErrorPage, 'rows.0.rowid', 'archive-2'],
    'root error schema root row second' => [$rootErrorPage, 'rows.1.source', 'schema_root'],
    'root error current rootpage errors' => [$rootErrorPage, 'current.rootpage_errors', 1],
    'root error blocker rootpage' => [$rootErrorPage, 'next_state.blocking.1', 'integrity_rootpage'],
    'root error message' => [$rootErrorPage, 'rows.1.message', 'largest root btree page 9 is beyond the database image'],
];

$tests = [];
foreach ($cases as $name => [$callback, $path, $expected]) {
    $tests['pragma integrity rootpage fk current source next114 ' . $name] = static function (TestRunner $t) use ($callback, $valueAt, $path, $expected): void {
        $t->same($expected, $valueAt($callback(), $path));
    };
}

$tests['pragma integrity rootpage fk current source next114 paginates with source cursor'] = static function (TestRunner $t) use ($rootErrorPage, $rootErrorDatabase, $schemas, $catalog): void {
    $first = SQLitePragmaIntegrityRootpageForeignKeyCurrentSourceYield::page($rootErrorDatabase, $schemas, $catalog, "PRAGMA foreign_key_check('archive.wp_term_taxonomy')", 0, 1, 'PRAGMA integrity_check');
    $second = SQLitePragmaIntegrityRootpageForeignKeyCurrentSourceYield::page($rootErrorDatabase, $schemas, $catalog, "PRAGMA foreign_key_check('archive.wp_term_taxonomy')", 1, 1, 'PRAGMA integrity_check', ['source_id' => $first['source_id'], 'next_offset' => 1]);

    $t->same(2, $rootErrorPage()['total']);
    $t->same(1, $first['count']);
    $t->same(['source_id' => $first['source_id'], 'offset' => 1], $first['next']);
    $t->same('foreign_key', $first['rows'][0]['source']);
    $t->same(1, $second['count']);
    $t->same('schema_root', $second['rows'][0]['source']);
    $t->same(null, $second['next']);
};

$tests['pragma integrity rootpage fk current source next114 rejects stale catalog cursor'] = static function (TestRunner $t) use ($page, $database, $schemas, $tempCatalog): void {
    $first = $page(0, 1);
    $t->throws(InvalidArgumentException::class, static fn () => SQLitePragmaIntegrityRootpageForeignKeyCurrentSourceYield::page($database, $schemas, $tempCatalog, "PRAGMA foreign_key_check('wp_term_taxonomy')", 1, 1, 'PRAGMA quick_check', ['source_id' => $first['source_id'], 'next_offset' => 1]));
};

$tests['pragma integrity rootpage fk current source next114 rejects stale sql cursor'] = static function (TestRunner $t) use ($page, $database, $schemas, $catalog): void {
    $first = $page(0, 1);
    $t->throws(InvalidArgumentException::class, static fn () => SQLitePragmaIntegrityRootpageForeignKeyCurrentSourceYield::page($database, $schemas, $catalog, "PRAGMA foreign_key_check('wp_options')", 1, 1, 'PRAGMA quick_check', ['source_id' => $first['source_id'], 'next_offset' => 1]));
};

$tests['pragma integrity rootpage fk current source next114 rejects stale offset cursor'] = static function (TestRunner $t) use ($page): void {
    $first = $page(0, 1);
    $t->throws(InvalidArgumentException::class, static fn () => $page(2, 1, ['source_id' => $first['source_id'], 'next_offset' => 1]));
};

$tests['pragma integrity rootpage fk current source next114 rejects negative offset'] = static function (TestRunner $t) use ($database, $schemas, $catalog): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLitePragmaIntegrityRootpageForeignKeyCurrentSourceYield::page($database, $schemas, $catalog, 'PRAGMA foreign_key_check', -1, 1));
};

$tests['pragma integrity rootpage fk current source next114 rejects zero limit'] = static function (TestRunner $t) use ($database, $schemas, $catalog): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLitePragmaIntegrityRootpageForeignKeyCurrentSourceYield::page($database, $schemas, $catalog, 'PRAGMA foreign_key_check', 0, 0));
};

return $tests;
