<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteAttachedSchemaCatalog;
use PortLibs\LibSqlite\SQLitePragmaSchemaCatalog;
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
            $record('table', 'wp_sites', 'wp_sites', 2, 'CREATE TABLE wp_sites(blog_id INTEGER PRIMARY KEY, domain TEXT NOT NULL UNIQUE)'),
            $record('index', 'sqlite_autoindex_wp_sites_1', 'wp_sites', 3, null),
            $record('table', 'wp_options', 'wp_options', 4, "CREATE TABLE wp_options(
                option_id INTEGER PRIMARY KEY,
                blog_id INTEGER REFERENCES wp_sites(blog_id) ON UPDATE CASCADE ON DELETE RESTRICT MATCH SIMPLE,
                option_name TEXT NOT NULL,
                option_value TEXT,
                UNIQUE(blog_id, option_name),
                FOREIGN KEY(option_name) REFERENCES wp_option_names(name) ON DELETE SET NULL
            )"),
            $record('index', 'sqlite_autoindex_wp_options_1', 'wp_options', 5, null),
            $record('table', 'wp_option_names', 'wp_option_names', 6, 'CREATE TABLE wp_option_names(name TEXT PRIMARY KEY)'),
            $record('index', 'sqlite_autoindex_wp_option_names_1', 'wp_option_names', 7, null),
        ],
        [
            $record('table', 'wp_options', 'wp_options', 8, "CREATE TABLE wp_options(
                option_name TEXT PRIMARY KEY REFERENCES wp_option_names(name) ON DELETE CASCADE,
                option_value TEXT,
                transient_timeout INTEGER
            )"),
            $record('index', 'sqlite_autoindex_wp_options_1', 'wp_options', 9, null),
        ],
    );
    $catalog->attach('network', '/srv/www/network.sqlite', [
        $record('table', 'wp_blogs', 'wp_blogs', 10, 'CREATE TABLE wp_blogs(blog_id INTEGER PRIMARY KEY, domain TEXT NOT NULL)'),
        $record('table', 'wp_sitemeta', 'wp_sitemeta', 11, "CREATE TABLE wp_sitemeta(
            meta_id INTEGER PRIMARY KEY,
            blog_id INTEGER NOT NULL,
            meta_key TEXT,
            locale TEXT,
            FOREIGN KEY(blog_id, meta_key) REFERENCES wp_blogmeta(blog_id, meta_key) ON UPDATE SET DEFAULT ON DELETE CASCADE MATCH custom
        )"),
        $record('index', 'wp_sitemeta_lookup', 'wp_sitemeta', 12, 'CREATE INDEX wp_sitemeta_lookup ON wp_sitemeta(blog_id, meta_key)'),
        $record('table', 'wp_blogmeta', 'wp_blogmeta', 13, 'CREATE TABLE wp_blogmeta(blog_id INTEGER, meta_key TEXT, PRIMARY KEY(blog_id, meta_key)) WITHOUT ROWID'),
        $record('index', 'sqlite_autoindex_wp_blogmeta_1', 'wp_blogmeta', 14, null),
    ]);
    $catalog->attach('archive', '/srv/www/archive.sqlite', [
        $record('table', 'wp_archived_options', 'wp_archived_options', 15, "CREATE TABLE wp_archived_options(
            option_name TEXT,
            blog_id INTEGER,
            FOREIGN KEY(blog_id) REFERENCES wp_blogs(blog_id) ON UPDATE NO ACTION ON DELETE SET DEFAULT
        )"),
    ]);

    return $catalog;
};

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
    'database-list status ok' => ['PRAGMA database_list', 'status', 'ok'],
    'database-list pragma name' => ['PRAGMA database_list', 'pragma', 'database_list'],
    'database-list row count includes temp and attachments' => ['PRAGMA database_list', 'rows.count', 4],
    'database-list main sequence' => ['PRAGMA database_list', 'rows.0.seq', 0],
    'database-list main name' => ['PRAGMA database_list', 'rows.0.name', 'main'],
    'database-list temp sequence' => ['PRAGMA database_list', 'rows.1.seq', 1],
    'database-list temp file is empty string' => ['PRAGMA database_list', 'rows.1.file', ''],
    'database-list first attach sequence' => ['PRAGMA database_list', 'rows.2.seq', 2],
    'database-list first attach name' => ['PRAGMA database_list', 'rows.2.name', 'network'],
    'database-list first attach file' => ['PRAGMA database_list', 'rows.2.file', '/srv/www/network.sqlite'],
    'database-list second attach sequence' => ['PRAGMA database_list', 'rows.3.seq', 3],
    'database-list second attach name' => ['PRAGMA database_list', 'rows.3.name', 'archive'],
    'database-list accepts trailing semicolon' => ['PRAGMA database_list;', 'rows.3.file', '/srv/www/archive.sqlite'],
    'unqualified foreign-key-list uses temp table shadow' => ['PRAGMA foreign_key_list(wp_options)', 'schema', 'temp'],
    'temp foreign-key-list row count' => ['PRAGMA foreign_key_list(wp_options)', 'rows.count', 1],
    'temp foreign-key-list table' => ['PRAGMA foreign_key_list(wp_options)', 'rows.0.table', 'wp_option_names'],
    'temp foreign-key-list from column' => ['PRAGMA foreign_key_list(wp_options)', 'rows.0.from', 'option_name'],
    'temp foreign-key-list target column from REFERENCES clause' => ['PRAGMA foreign_key_list(wp_options)', 'rows.0.to', 'name'],
    'temp foreign-key-list on-delete cascade' => ['PRAGMA foreign_key_list(wp_options)', 'rows.0.on_delete', 'CASCADE'],
    'temp foreign-key-list default on-update' => ['PRAGMA foreign_key_list(wp_options)', 'rows.0.on_update', 'NO ACTION'],
    'temp foreign-key-list default match' => ['PRAGMA foreign_key_list(wp_options)', 'rows.0.match', 'NONE'],
    'explicit main foreign-key-list bypasses temp' => ['PRAGMA main.foreign_key_list(wp_options)', 'schema', 'main'],
    'main foreign-key-list row count' => ['PRAGMA main.foreign_key_list(wp_options)', 'rows.count', 2],
    'main column foreign-key id' => ['PRAGMA main.foreign_key_list(wp_options)', 'rows.0.id', 0],
    'main column foreign-key seq' => ['PRAGMA main.foreign_key_list(wp_options)', 'rows.0.seq', 0],
    'main column foreign-key table' => ['PRAGMA main.foreign_key_list(wp_options)', 'rows.0.table', 'wp_sites'],
    'main column foreign-key from' => ['PRAGMA main.foreign_key_list(wp_options)', 'rows.0.from', 'blog_id'],
    'main column foreign-key to' => ['PRAGMA main.foreign_key_list(wp_options)', 'rows.0.to', 'blog_id'],
    'main column foreign-key on update' => ['PRAGMA main.foreign_key_list(wp_options)', 'rows.0.on_update', 'CASCADE'],
    'main column foreign-key on delete' => ['PRAGMA main.foreign_key_list(wp_options)', 'rows.0.on_delete', 'RESTRICT'],
    'main column foreign-key match' => ['PRAGMA main.foreign_key_list(wp_options)', 'rows.0.match', 'SIMPLE'],
    'main table foreign-key id increments' => ['PRAGMA main.foreign_key_list(wp_options)', 'rows.1.id', 1],
    'main table foreign-key from' => ['PRAGMA main.foreign_key_list(wp_options)', 'rows.1.from', 'option_name'],
    'main table foreign-key to' => ['PRAGMA main.foreign_key_list(wp_options)', 'rows.1.to', 'name'],
    'main table foreign-key on delete set null' => ['PRAGMA main.foreign_key_list(wp_options)', 'rows.1.on_delete', 'SET NULL'],
    'attached composite foreign-key schema' => ['PRAGMA network.foreign_key_list(wp_sitemeta)', 'schema', 'network'],
    'attached composite foreign-key first seq' => ['PRAGMA network.foreign_key_list(wp_sitemeta)', 'rows.0.seq', 0],
    'attached composite foreign-key first from' => ['PRAGMA network.foreign_key_list(wp_sitemeta)', 'rows.0.from', 'blog_id'],
    'attached composite foreign-key first to' => ['PRAGMA network.foreign_key_list(wp_sitemeta)', 'rows.0.to', 'blog_id'],
    'attached composite foreign-key second seq' => ['PRAGMA network.foreign_key_list(wp_sitemeta)', 'rows.1.seq', 1],
    'attached composite foreign-key second from' => ['PRAGMA network.foreign_key_list(wp_sitemeta)', 'rows.1.from', 'meta_key'],
    'attached composite foreign-key second to' => ['PRAGMA network.foreign_key_list(wp_sitemeta)', 'rows.1.to', 'meta_key'],
    'attached composite foreign-key shared id' => ['PRAGMA network.foreign_key_list(wp_sitemeta)', 'rows.1.id', 0],
    'attached composite foreign-key update action' => ['PRAGMA network.foreign_key_list(wp_sitemeta)', 'rows.0.on_update', 'SET DEFAULT'],
    'attached composite foreign-key delete action' => ['PRAGMA network.foreign_key_list(wp_sitemeta)', 'rows.0.on_delete', 'CASCADE'],
    'attached composite foreign-key match name' => ['PRAGMA network.foreign_key_list(wp_sitemeta)', 'rows.0.match', 'CUSTOM'],
    'unqualified attached-only foreign-key resolves after main' => ['PRAGMA foreign_key_list(wp_archived_options)', 'schema', 'archive'],
    'attached archive foreign-key target table' => ['PRAGMA foreign_key_list(wp_archived_options)', 'rows.0.table', 'wp_blogs'],
    'attached archive foreign-key set default delete' => ['PRAGMA foreign_key_list(wp_archived_options)', 'rows.0.on_delete', 'SET DEFAULT'],
    'attached archive foreign-key no action update' => ['PRAGMA foreign_key_list(wp_archived_options)', 'rows.0.on_update', 'NO ACTION'],
    'equals syntax resolves temp foreign-key target' => ['PRAGMA foreign_key_list = wp_options', 'schema', 'temp'],
    'quoted target resolves attached foreign-key table' => ["PRAGMA foreign_key_list('wp_archived_options')", 'schema', 'archive'],
    'case-insensitive target resolves temp foreign-key table' => ['PRAGMA foreign_key_list(WP_OPTIONS)', 'schema', 'temp'],
    'missing foreign-key table uses main empty rowset' => ['PRAGMA foreign_key_list(missing)', 'schema', 'main'],
    'missing foreign-key rows empty' => ['PRAGMA foreign_key_list(missing)', 'rows.count', 0],
    'table without foreign keys returns empty' => ['PRAGMA foreign_key_list(wp_sites)', 'rows.count', 0],
    'foreign-key-list parse target' => ['PRAGMA foreign_key_list(wp_options)', 'target', 'wp_options'],
    'foreign-key-list parse pragma' => ['PRAGMA foreign_key_list(wp_options)', 'pragma', 'foreign_key_list'],
    'foreign-key-list table-level id does not split rows' => ['PRAGMA network.foreign_key_list(wp_sitemeta)', 'rows.count', 2],
];

$tests = [];
foreach ($cases as $name => [$sql, $path, $expected]) {
    $tests['pragma catalog rebase current next16 ' . $name] = static function (TestRunner $t) use ($makeCatalog, $valueAt, $sql, $path, $expected): void {
        $t->same($expected, $valueAt($makeCatalog()->executeSchemaPragma($sql), $path));
    };
}

$tests['pragma catalog rebase current next16 standalone foreign key list works'] = static function (TestRunner $t) use ($record): void {
    $catalog = new SQLitePragmaSchemaCatalog([
        $record('table', 'wp_posts', 'wp_posts', 2, 'CREATE TABLE wp_posts(ID INTEGER PRIMARY KEY, post_parent INTEGER REFERENCES wp_posts(ID) ON DELETE SET NULL)'),
    ]);

    $rows = $catalog->execute('PRAGMA foreign_key_list(wp_posts)')['rows'];
    $t->same(1, count($rows));
    $t->same('wp_posts', $rows[0]['table']);
    $t->same('post_parent', $rows[0]['from']);
    $t->same('ID', $rows[0]['to']);
    $t->same('SET NULL', $rows[0]['on_delete']);
};

$tests['pragma catalog rebase current next16 explicit missing schema raises'] = static function (TestRunner $t) use ($makeCatalog): void {
    $t->throws(InvalidArgumentException::class, static fn () => $makeCatalog()->executeSchemaPragma('PRAGMA missing.foreign_key_list(wp_options)'));
};

$tests['pragma catalog rebase current next16 malformed database list raises'] = static function (TestRunner $t) use ($makeCatalog): void {
    $t->throws(InvalidArgumentException::class, static fn () => $makeCatalog()->executeSchemaPragma('PRAGMA database_list(main)'));
};

return $tests;
