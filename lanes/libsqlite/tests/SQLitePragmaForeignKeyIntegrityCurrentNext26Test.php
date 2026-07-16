<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteAttachedSchemaCatalog;
use PortLibs\LibSqlite\SQLitePragmaForeignKeyIntegrity;
use PortLibs\LibSqlite\SQLiteSchemaRecord;

$record = static fn (string $type, string $name, string $table, ?int $root, ?string $sql = null): SQLiteSchemaRecord => new SQLiteSchemaRecord(
    $type,
    $name,
    $table,
    $root,
    $sql,
    1,
);

$makeCatalog = static function () use ($record): SQLiteAttachedSchemaCatalog {
    $catalog = new SQLiteAttachedSchemaCatalog(
        [
            $record('table', 'wp_posts', 'wp_posts', 2, 'CREATE TABLE wp_posts(ID INTEGER PRIMARY KEY, blog_id INTEGER)'),
            $record('table', 'wp_postmeta', 'wp_postmeta', 3, 'CREATE TABLE wp_postmeta(meta_id INTEGER PRIMARY KEY, post_id INTEGER REFERENCES wp_posts(ID))'),
            $record('table', 'wp_options', 'wp_options', 4, 'CREATE TABLE wp_options(option_id INTEGER PRIMARY KEY, blog_id INTEGER REFERENCES wp_sites(blog_id), option_name TEXT)'),
            $record('table', 'wp_sites', 'wp_sites', 5, 'CREATE TABLE wp_sites(blog_id INTEGER PRIMARY KEY, domain TEXT)'),
        ],
        [
            $record('table', 'wp_options', 'wp_options', 6, 'CREATE TABLE wp_options(option_name TEXT PRIMARY KEY REFERENCES wp_option_names(name), option_value TEXT)'),
            $record('table', 'wp_option_names', 'wp_option_names', 7, 'CREATE TABLE wp_option_names(name TEXT PRIMARY KEY)'),
        ],
    );
    $catalog->attach('archive', '/srv/archive.sqlite', [
        $record('table', 'wp_options_archive', 'wp_options_archive', 8, 'CREATE TABLE wp_options_archive(option_name TEXT, blog_id INTEGER REFERENCES wp_blogs(blog_id))'),
        $record('table', 'wp_blogs', 'wp_blogs', 9, 'CREATE TABLE wp_blogs(blog_id INTEGER PRIMARY KEY)'),
    ]);
    $catalog->attach('network', '/srv/network.sqlite', [
        $record('table', 'wp_sitemeta', 'wp_sitemeta', 10, 'CREATE TABLE wp_sitemeta(meta_id INTEGER PRIMARY KEY, blog_id INTEGER, meta_key TEXT, FOREIGN KEY(blog_id, meta_key) REFERENCES wp_blogmeta(blog_id, meta_key))'),
        $record('table', 'wp_blogmeta', 'wp_blogmeta', 11, 'CREATE TABLE wp_blogmeta(blog_id INTEGER, meta_key TEXT, PRIMARY KEY(blog_id, meta_key)) WITHOUT ROWID'),
    ]);

    return $catalog;
};

$schemas = [
    'main' => [
        'tables' => [
            'wp_posts' => [
                ['rowid' => 1, 'ID' => 1, 'blog_id' => 1],
                ['rowid' => 2, 'ID' => 2, 'blog_id' => 1],
            ],
            'wp_postmeta' => [
                ['rowid' => 101, 'meta_id' => 101, 'post_id' => 1],
                ['rowid' => 102, 'meta_id' => 102, 'post_id' => 404],
                ['rowid' => 103, 'meta_id' => 103, 'post_id' => null],
            ],
            'wp_sites' => [
                ['rowid' => 201, 'blog_id' => 1, 'domain' => 'example.test'],
            ],
            'wp_options' => [
                ['rowid' => 301, 'option_id' => 301, 'blog_id' => 1, 'option_name' => 'siteurl'],
                ['rowid' => 302, 'option_id' => 302, 'blog_id' => 9, 'option_name' => 'missing_site'],
            ],
        ],
        'foreignKeys' => [
            ['id' => 0, 'table' => 'wp_postmeta', 'parent' => 'wp_posts', 'columns' => ['post_id' => 'ID']],
            ['id' => 1, 'table' => 'wp_options', 'parent' => 'wp_sites', 'columns' => ['blog_id' => 'blog_id']],
        ],
    ],
    'temp' => [
        'tables' => [
            'wp_option_names' => [
                ['rowid' => 1, 'name' => 'siteurl'],
                ['rowid' => 2, 'name' => 'home'],
            ],
            'wp_options' => [
                ['rowid' => 401, 'option_name' => 'siteurl', 'option_value' => 'https://example.test'],
                ['rowid' => 402, 'option_name' => 'missing_plugin_option', 'option_value' => '1'],
                ['rowid' => 403, 'option_name' => null, 'option_value' => 'draft'],
            ],
        ],
        'foreignKeys' => [
            ['id' => 0, 'table' => 'wp_options', 'parent' => 'wp_option_names', 'columns' => [['child' => 'option_name', 'parent' => 'name', 'affinity' => 'text', 'collation' => 'nocase']]],
        ],
    ],
    'archive' => [
        'tables' => [
            'wp_blogs' => [
                ['rowid' => 1, 'blog_id' => 1],
                ['rowid' => 2, 'blog_id' => 2],
            ],
            'wp_options_archive' => [
                ['rowid' => 501, 'option_name' => 'siteurl', 'blog_id' => 1],
                ['rowid' => 502, 'option_name' => 'old_plugin', 'blog_id' => 7],
                ['rowid' => 503, 'option_name' => 'detached', 'blog_id' => null],
            ],
        ],
        'foreignKeys' => [
            ['id' => 0, 'table' => 'wp_options_archive', 'parent' => 'wp_blogs', 'columns' => [['child' => 'blog_id', 'parent' => 'blog_id', 'affinity' => 'integer']]],
        ],
    ],
    'network' => [
        'tables' => [
            'wp_blogmeta' => [
                ['blog_id' => 1, 'meta_key' => 'site_name'],
                ['blog_id' => 2, 'meta_key' => 'admin_email'],
            ],
            'wp_sitemeta' => [
                ['rowid' => 601, 'meta_id' => 601, 'blog_id' => '1', 'meta_key' => 'site_name'],
                ['rowid' => 602, 'meta_id' => 602, 'blog_id' => '2', 'meta_key' => 'missing'],
                ['rowid' => 603, 'meta_id' => 603, 'blog_id' => null, 'meta_key' => 'ignored'],
                ['rowid' => 604, 'meta_id' => 604, 'blog_id' => '2', 'meta_key' => null],
            ],
        ],
        'foreignKeys' => [
            [
                'id' => 0,
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

$execute = static fn (string $sql): array => SQLitePragmaForeignKeyIntegrity::execute($sql, $schemas, $makeCatalog());
$allSchemas = static fn (): array => SQLitePragmaForeignKeyIntegrity::executeAllSchemas($schemas);
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
    'bare pragma status ok' => ['PRAGMA foreign_key_check', 'status', 'ok'],
    'bare pragma name' => ['PRAGMA foreign_key_check', 'pragma', 'foreign_key_check'],
    'bare pragma defaults main schema' => ['PRAGMA foreign_key_check', 'schema', 'main'],
    'bare pragma has two main violations' => ['PRAGMA foreign_key_check', 'rows.count', 2],
    'bare pragma first main table' => ['PRAGMA foreign_key_check', 'rows.0.table', 'wp_postmeta'],
    'bare pragma first main rowid' => ['PRAGMA foreign_key_check', 'rows.0.rowid', 102],
    'bare pragma first main parent' => ['PRAGMA foreign_key_check', 'rows.0.parent', 'wp_posts'],
    'bare pragma first main fkid' => ['PRAGMA foreign_key_check', 'rows.0.fkid', 0],
    'bare pragma first main schema tag' => ['PRAGMA foreign_key_check', 'rows.0.schema', 'main'],
    'bare pragma second main table' => ['PRAGMA foreign_key_check', 'rows.1.table', 'wp_options'],
    'bare pragma second main rowid' => ['PRAGMA foreign_key_check', 'rows.1.rowid', 302],
    'bare pragma second main parent' => ['PRAGMA foreign_key_check', 'rows.1.parent', 'wp_sites'],
    'targeted temp shadow resolves schema' => ['PRAGMA foreign_key_check(wp_options)', 'schema', 'temp'],
    'targeted temp shadow target' => ['PRAGMA foreign_key_check(wp_options)', 'target', 'wp_options'],
    'targeted temp shadow row count' => ['PRAGMA foreign_key_check(wp_options)', 'rows.count', 1],
    'targeted temp shadow table' => ['PRAGMA foreign_key_check(wp_options)', 'rows.0.table', 'wp_options'],
    'targeted temp shadow rowid' => ['PRAGMA foreign_key_check(wp_options)', 'rows.0.rowid', 402],
    'targeted temp shadow parent' => ['PRAGMA foreign_key_check(wp_options)', 'rows.0.parent', 'wp_option_names'],
    'targeted temp shadow schema tag' => ['PRAGMA foreign_key_check(wp_options)', 'rows.0.schema', 'temp'],
    'explicit main bypasses temp schema' => ['PRAGMA main.foreign_key_check(wp_options)', 'schema', 'main'],
    'explicit main bypasses temp rowid' => ['PRAGMA main.foreign_key_check(wp_options)', 'rows.0.rowid', 302],
    'explicit temp keeps temp rowid' => ['PRAGMA temp.foreign_key_check(wp_options)', 'rows.0.rowid', 402],
    'attached archive schema' => ['PRAGMA archive.foreign_key_check(wp_options_archive)', 'schema', 'archive'],
    'attached archive target row count' => ['PRAGMA archive.foreign_key_check(wp_options_archive)', 'rows.count', 1],
    'attached archive target rowid' => ['PRAGMA archive.foreign_key_check(wp_options_archive)', 'rows.0.rowid', 502],
    'attached archive target parent' => ['PRAGMA archive.foreign_key_check(wp_options_archive)', 'rows.0.parent', 'wp_blogs'],
    'attached-only target resolves archive' => ['PRAGMA foreign_key_check(wp_options_archive)', 'schema', 'archive'],
    'attached-only target rowid' => ['PRAGMA foreign_key_check(wp_options_archive)', 'rows.0.rowid', 502],
    'network composite schema' => ['PRAGMA network.foreign_key_check(wp_sitemeta)', 'schema', 'network'],
    'network composite row count' => ['PRAGMA network.foreign_key_check(wp_sitemeta)', 'rows.count', 1],
    'network composite rowid' => ['PRAGMA network.foreign_key_check(wp_sitemeta)', 'rows.0.rowid', 602],
    'network composite parent' => ['PRAGMA network.foreign_key_check(wp_sitemeta)', 'rows.0.parent', 'wp_blogmeta'],
    'network composite fkid' => ['PRAGMA network.foreign_key_check(wp_sitemeta)', 'rows.0.fkid', 0],
    'quoted target resolves temp shadow' => ["PRAGMA foreign_key_check('wp_options')", 'schema', 'temp'],
    'double quoted target resolves archive' => ['PRAGMA foreign_key_check("wp_options_archive")', 'schema', 'archive'],
    'bracket quoted target resolves network' => ['PRAGMA foreign_key_check([wp_sitemeta])', 'schema', 'network'],
    'backtick quoted target resolves main' => ['PRAGMA foreign_key_check(`wp_postmeta`)', 'schema', 'main'],
    'case-insensitive target resolves temp' => ['PRAGMA foreign_key_check(WP_OPTIONS)', 'schema', 'temp'],
    'trailing semicolon accepted' => ['PRAGMA foreign_key_check(wp_options);', 'rows.0.rowid', 402],
    'missing target falls back main empty' => ['PRAGMA foreign_key_check(wp_missing)', 'schema', 'main'],
    'missing target rows empty' => ['PRAGMA foreign_key_check(wp_missing)', 'rows.count', 0],
];

$tests = [];
foreach ($cases as $name => [$sql, $path, $expected]) {
    $tests['pragma foreign key integrity current next26 ' . $name] = static function (TestRunner $t) use ($execute, $valueAt, $sql, $path, $expected): void {
        $t->same($expected, $valueAt($execute($sql), $path));
    };
}

$tests['pragma foreign key integrity current next26 all schemas reports five violations'] = static function (TestRunner $t) use ($allSchemas): void {
    $t->same(5, count($allSchemas()['rows']));
};

$tests['pragma foreign key integrity current next26 all schemas preserves temp before main'] = static function (TestRunner $t) use ($allSchemas): void {
    $t->same(['temp', 'main', 'main', 'archive', 'network'], array_column(array_slice($allSchemas()['rows'], 0, 5), 'schema'));
};

$tests['pragma foreign key integrity current next26 all schemas includes temp staging violation'] = static function (TestRunner $t) use ($allSchemas): void {
    $t->same(402, $allSchemas()['rows'][0]['rowid']);
};

$tests['pragma foreign key integrity current next26 all schemas includes main postmeta violation'] = static function (TestRunner $t) use ($allSchemas): void {
    $t->same(102, $allSchemas()['rows'][1]['rowid']);
};

$tests['pragma foreign key integrity current next26 all schemas includes main options violation'] = static function (TestRunner $t) use ($allSchemas): void {
    $t->same(302, $allSchemas()['rows'][2]['rowid']);
};

$tests['pragma foreign key integrity current next26 all schemas includes archive violation'] = static function (TestRunner $t) use ($allSchemas): void {
    $t->same(502, $allSchemas()['rows'][3]['rowid']);
};

$tests['pragma foreign key integrity current next26 all schemas includes network violation'] = static function (TestRunner $t) use ($allSchemas): void {
    $t->same(602, $allSchemas()['rows'][4]['rowid']);
};

$tests['pragma foreign key integrity current next26 explicit schema without target checks archive'] = static function (TestRunner $t) use ($execute): void {
    $rows = $execute('PRAGMA archive.foreign_key_check')['rows'];
    $t->same([502], array_column($rows, 'rowid'));
};

$tests['pragma foreign key integrity current next26 explicit schema without target checks network'] = static function (TestRunner $t) use ($execute): void {
    $rows = $execute('PRAGMA network.foreign_key_check')['rows'];
    $t->same([602], array_column($rows, 'rowid'));
};

$tests['pragma foreign key integrity current next26 missing schema raises'] = static function (TestRunner $t) use ($schemas): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLitePragmaForeignKeyIntegrity::execute('PRAGMA missing.foreign_key_check(wp_options)', $schemas));
};

$tests['pragma foreign key integrity current next26 malformed pragma raises'] = static function (TestRunner $t) use ($schemas): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLitePragmaForeignKeyIntegrity::execute('PRAGMA foreign_key_check = wp_options', $schemas));
};

$tests['pragma foreign key integrity current next26 unsupported table valued wrapper raises'] = static function (TestRunner $t) use ($schemas): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLitePragmaForeignKeyIntegrity::execute('SELECT * FROM pragma_foreign_key_check', $schemas));
};

return $tests;
