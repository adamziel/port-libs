<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteAttachedSchemaCatalog;
use PortLibs\LibSqlite\SQLitePragmaForeignKeyIntegrity;
use PortLibs\LibSqlite\SQLitePragmaIntegrityCurrentNextYield;
use PortLibs\LibSqlite\SQLiteSchemaRecord;

$record = static fn (string $type, string $name, string $table, ?int $root, ?string $sql = null, int $rowid = 1): SQLiteSchemaRecord => new SQLiteSchemaRecord(
    $type,
    $name,
    $table,
    $root,
    $sql,
    $rowid,
);

$catalog = new SQLiteAttachedSchemaCatalog(
    [
        $record('table', 'wp_posts', 'wp_posts', 2, 'CREATE TABLE wp_posts(ID INTEGER PRIMARY KEY)', 1),
        $record('table', 'wp_postmeta', 'wp_postmeta', 3, 'CREATE TABLE wp_postmeta(meta_id INTEGER PRIMARY KEY, post_id INTEGER)', 2),
        $record('table', 'wp_options', 'wp_options', 4, 'CREATE TABLE wp_options(option_id INTEGER PRIMARY KEY, option_name TEXT)', 3),
        $record('table', 'wp_option_names', 'wp_option_names', 5, 'CREATE TABLE wp_option_names(name TEXT PRIMARY KEY)', 4),
    ],
    [
        $record('table', 'wp_options', 'wp_options', 6, 'CREATE TABLE wp_options(option_id INTEGER PRIMARY KEY, option_name TEXT)', 1),
        $record('table', 'wp_option_names', 'wp_option_names', 7, 'CREATE TABLE wp_option_names(name TEXT PRIMARY KEY)', 2),
    ],
);
$catalog->attach('archive', '/tmp/wp-archive.sqlite', [
    $record('table', 'wp_options', 'wp_options', 8, 'CREATE TABLE wp_options(option_id INTEGER PRIMARY KEY, option_name TEXT)', 1),
    $record('table', 'wp_option_names', 'wp_option_names', 9, 'CREATE TABLE wp_option_names(name TEXT PRIMARY KEY)', 2),
]);
$catalog->attach('network', '/tmp/wp-network.sqlite', [
    $record('table', 'wp_sitemeta', 'wp_sitemeta', 10, 'CREATE TABLE wp_sitemeta(meta_id INTEGER PRIMARY KEY, blog_id INTEGER, meta_key TEXT)', 1),
    $record('table', 'wp_blogmeta', 'wp_blogmeta', 11, 'CREATE TABLE wp_blogmeta(blog_id INTEGER, meta_key TEXT, PRIMARY KEY(blog_id, meta_key)) WITHOUT ROWID', 2),
]);

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
                ['rowid' => 22, 'option_id' => 22, 'option_name' => 'main_missing'],
            ],
        ],
        'foreignKeys' => [
            ['id' => 1, 'table' => 'wp_postmeta', 'parent' => 'wp_posts', 'columns' => [['child' => 'post_id', 'parent' => 'ID', 'affinity' => 'integer']]],
            ['id' => 2, 'table' => 'wp_options', 'parent' => 'wp_option_names', 'columns' => [['child' => 'option_name', 'parent' => 'name', 'affinity' => 'text', 'collation' => 'nocase']]],
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
            ['id' => 3, 'table' => 'wp_options', 'parent' => 'wp_option_names', 'columns' => [['child' => 'option_name', 'parent' => 'name', 'affinity' => 'text', 'collation' => 'nocase']]],
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
            ['id' => 4, 'table' => 'wp_options', 'parent' => 'wp_option_names', 'columns' => [['child' => 'option_name', 'parent' => 'name', 'affinity' => 'text', 'collation' => 'nocase']]],
        ],
    ],
    'network' => [
        'tables' => [
            'wp_blogmeta' => [
                ['blog_id' => 1, 'meta_key' => 'site_name'],
                ['blog_id' => 2, 'meta_key' => 'admin_email'],
            ],
            'wp_sitemeta' => [
                ['rowid' => 31, 'meta_id' => 31, 'blog_id' => '1', 'meta_key' => 'site_name'],
                ['rowid' => 32, 'meta_id' => 32, 'blog_id' => '2', 'meta_key' => 'missing_key'],
                ['rowid' => 33, 'meta_id' => 33, 'blog_id' => null, 'meta_key' => 'ignored'],
            ],
        ],
        'foreignKeys' => [
            [
                'id' => 5,
                'table' => 'wp_sitemeta',
                'parent' => 'wp_blogmeta',
                'columns' => [
                    ['child' => 'blog_id', 'parent' => 'blog_id', 'affinity' => 'integer'],
                    ['child' => 'meta_key', 'parent' => 'meta_key', 'collation' => 'binary'],
                ],
            ],
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

$execute = static fn (string $sql): array => SQLitePragmaForeignKeyIntegrity::executeTableValued($sql, $schemas, $catalog);
$implicit = static fn (): array => $execute("SELECT * FROM pragma_foreign_key_check('wp_options')");
$archive = static fn (): array => $execute("SELECT * FROM pragma_foreign_key_check('archive.wp_options')");
$network = static fn (): array => $execute('SELECT * FROM network.pragma_foreign_key_check(wp_sitemeta)');
$main = static fn (): array => $execute('SELECT * FROM main.pragma_foreign_key_check(wp_options)');
$bare = static fn (): array => $execute('SELECT * FROM pragma_foreign_key_check()');
$tableOnly = static fn (): array => $execute('pragma_foreign_key_check(`wp_postmeta`)');
$archivePage = static fn (): array => SQLitePragmaIntegrityCurrentNextYield::pageForForeignKeyTableValuedPragma($database, $schemas, "SELECT * FROM pragma_foreign_key_check('archive.wp_options')", 0, 86, 'PRAGMA quick_check', $catalog);
$tempPage = static fn (): array => SQLitePragmaIntegrityCurrentNextYield::pageForForeignKeyTableValuedPragma($database, $schemas, "SELECT * FROM pragma_foreign_key_check('wp_options')", 1, 86, 'PRAGMA quick_check', $catalog);
$networkPage = static fn (): array => SQLitePragmaIntegrityCurrentNextYield::pageForForeignKeyTableValuedPragma($database, $schemas, 'SELECT * FROM network.pragma_foreign_key_check(wp_sitemeta)', 0, 1, 'PRAGMA quick_check', $catalog);
$collectArchive = static fn (): array => SQLitePragmaIntegrityCurrentNextYield::collectForForeignKeyTableValuedPragma($database, $schemas, "SELECT * FROM pragma_foreign_key_check('archive.wp_options')", 'PRAGMA quick_check', $catalog);

$cases = [
    'implicit status' => [$implicit, 'status', 'ok'],
    'implicit pragma name' => [$implicit, 'pragma', 'foreign_key_check'],
    'implicit resolves temp current source' => [$implicit, 'schema', 'temp'],
    'implicit target schema temp' => [$implicit, 'target_schema', 'temp'],
    'implicit target source catalog' => [$implicit, 'target_source', 'catalog-current'],
    'implicit target name' => [$implicit, 'target', 'wp_options'],
    'implicit row count' => [$implicit, 'rows.count', 2],
    'implicit first rowid' => [$implicit, 'rows.0.rowid', 'temp-2'],
    'implicit second rowid' => [$implicit, 'rows.1.rowid', 'temp-3'],
    'implicit parent' => [$implicit, 'rows.0.parent', 'wp_option_names'],
    'implicit fkid' => [$implicit, 'rows.0.fkid', 3],
    'archive schema' => [$archive, 'schema', 'archive'],
    'archive source qualified target' => [$archive, 'target_source', 'qualified-target'],
    'archive row count' => [$archive, 'rows.count', 1],
    'archive rowid' => [$archive, 'rows.0.rowid', 'archive-2'],
    'archive parent' => [$archive, 'rows.0.parent', 'wp_option_names'],
    'archive fkid' => [$archive, 'rows.0.fkid', 4],
    'network schema' => [$network, 'schema', 'network'],
    'network source pragma schema' => [$network, 'target_source', 'pragma-schema'],
    'network row count' => [$network, 'rows.count', 1],
    'network rowid' => [$network, 'rows.0.rowid', 32],
    'network parent' => [$network, 'rows.0.parent', 'wp_blogmeta'],
    'network fkid' => [$network, 'rows.0.fkid', 5],
    'main schema bypasses temp' => [$main, 'schema', 'main'],
    'main source pragma schema' => [$main, 'target_source', 'pragma-schema'],
    'main row count' => [$main, 'rows.count', 1],
    'main rowid' => [$main, 'rows.0.rowid', 22],
    'bare default schema' => [$bare, 'schema', 'main'],
    'bare default target source' => [$bare, 'target_source', 'default'],
    'bare default row count' => [$bare, 'rows.count', 2],
    'table only source catalog main' => [$tableOnly, 'schema', 'main'],
    'table only rowid' => [$tableOnly, 'rows.0.rowid', 12],
    'archive page status' => [$archivePage, 'status', 'ok'],
    'archive page limit next86' => [$archivePage, 'limit', 86],
    'archive page count' => [$archivePage, 'count', 1],
    'archive page total' => [$archivePage, 'total', 1],
    'archive page complete' => [$archivePage, 'complete', true],
    'archive page current foreign key count' => [$archivePage, 'current.foreign_key', 1],
    'archive page message' => [$archivePage, 'rows.0.message', 'foreign key mismatch in archive.wp_options rowid archive-2 references wp_option_names fkid 4'],
    'temp page offset skips first row' => [$tempPage, 'offset', 1],
    'temp page count' => [$tempPage, 'count', 1],
    'temp page rowid' => [$tempPage, 'rows.0.rowid', 'temp-3'],
    'temp page complete' => [$tempPage, 'complete', true],
    'network page first count' => [$networkPage, 'count', 1],
    'network page next offset null' => [$networkPage, 'next_offset', null],
    'network page rowid' => [$networkPage, 'rows.0.rowid', 32],
    'collect archive count' => [$collectArchive, 'count', 1],
    'collect archive schema' => [$collectArchive, '0.schema', 'archive'],
    'collect archive kind' => [$collectArchive, '0.kind', 'foreign_key_check'],
    'collect archive source' => [$collectArchive, '0.source', 'foreign_key'],
];

$tests = [];
foreach ($cases as $name => [$callback, $path, $expected]) {
    $tests['pragma foreign key integrity current source next86 ' . $name] = static function (TestRunner $t) use ($callback, $valueAt, $path, $expected): void {
        $t->same($expected, $valueAt($callback(), $path));
    };
}

$tests['pragma foreign key integrity current source next86 supports trailing semicolon'] = static function (TestRunner $t) use ($execute): void {
    $t->same('temp-2', $execute("SELECT * FROM pragma_foreign_key_check('wp_options');")['rows'][0]['rowid']);
};

$tests['pragma foreign key integrity current source next86 supports bracket target'] = static function (TestRunner $t) use ($execute): void {
    $t->same(32, $execute('SELECT * FROM pragma_foreign_key_check([wp_sitemeta])')['rows'][0]['rowid']);
};

$tests['pragma foreign key integrity current source next86 rejects malformed select list'] = static function (TestRunner $t) use ($schemas, $catalog): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLitePragmaForeignKeyIntegrity::executeTableValued("SELECT rowid FROM pragma_foreign_key_check('wp_options')", $schemas, $catalog));
};

$tests['pragma foreign key integrity current source next86 rejects conflicting schemas'] = static function (TestRunner $t) use ($schemas, $catalog): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLitePragmaForeignKeyIntegrity::executeTableValued("SELECT * FROM main.pragma_foreign_key_check('archive.wp_options')", $schemas, $catalog));
};

$tests['pragma foreign key integrity current source next86 rejects malformed table valued target'] = static function (TestRunner $t) use ($schemas, $catalog): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLitePragmaForeignKeyIntegrity::executeTableValued('SELECT * FROM pragma_foreign_key_check(wp-options)', $schemas, $catalog));
};

$tests['pragma foreign key integrity current source next86 rejects negative page offset'] = static function (TestRunner $t) use ($database, $schemas, $catalog): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLitePragmaIntegrityCurrentNextYield::pageForForeignKeyTableValuedPragma($database, $schemas, "SELECT * FROM pragma_foreign_key_check('wp_options')", -1, 86, 'PRAGMA quick_check', $catalog));
};

$tests['pragma foreign key integrity current source next86 rejects zero page limit'] = static function (TestRunner $t) use ($database, $schemas, $catalog): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLitePragmaIntegrityCurrentNextYield::pageForForeignKeyTableValuedPragma($database, $schemas, "SELECT * FROM pragma_foreign_key_check('wp_options')", 0, 0, 'PRAGMA quick_check', $catalog));
};

return $tests;
