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
            $record('table', 'wp_option_names', 'wp_option_names', 3, 'CREATE TABLE wp_option_names(name TEXT PRIMARY KEY)'),
            $record('table', 'wp_options', 'wp_options', 4, "CREATE TABLE wp_options(
                option_id INTEGER PRIMARY KEY,
                blog_id INTEGER REFERENCES wp_sites(blog_id) ON UPDATE CASCADE ON DELETE RESTRICT MATCH simple,
                option_name TEXT NOT NULL,
                option_value TEXT,
                FOREIGN KEY(option_name) REFERENCES wp_option_names(name) ON UPDATE NO ACTION ON DELETE SET NULL
            )"),
        ],
        [
            $record('table', 'wp_options', 'wp_options', 5, "CREATE TABLE wp_options(
                option_name TEXT PRIMARY KEY REFERENCES wp_option_names(name) ON DELETE CASCADE,
                option_value TEXT,
                transient_timeout INTEGER
            )"),
        ],
    );
    $catalog->attach('network', '/srv/www/network.sqlite', [
        $record('table', 'wp_blogmeta', 'wp_blogmeta', 6, 'CREATE TABLE wp_blogmeta(blog_id INTEGER, meta_key TEXT, PRIMARY KEY(blog_id, meta_key)) WITHOUT ROWID'),
        $record('table', 'wp_sitemeta', 'wp_sitemeta', 7, "CREATE TABLE wp_sitemeta(
            meta_id INTEGER PRIMARY KEY,
            blog_id INTEGER NOT NULL,
            meta_key TEXT,
            locale TEXT,
            CONSTRAINT sitemeta_blogmeta_fk FOREIGN KEY(blog_id, meta_key) REFERENCES wp_blogmeta(blog_id, meta_key) ON UPDATE SET DEFAULT ON DELETE CASCADE MATCH custom
        )"),
    ]);
    $catalog->attach('archive', '/srv/www/archive.sqlite', [
        $record('table', 'wp archived options', 'wp archived options', 8, "CREATE TABLE \"wp archived options\"(
            option_name TEXT,
            blog_id INTEGER,
            FOREIGN KEY(blog_id) REFERENCES wp_sites ON DELETE SET DEFAULT
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
    'table valued status ok' => ["pragma_foreign_key_list('wp_options')", 'status', 'ok'],
    'table valued pragma name' => ["pragma_foreign_key_list('wp_options')", 'pragma', 'foreign_key_list'],
    'unqualified function follows temp shadow table' => ["pragma_foreign_key_list('wp_options')", 'schema', 'temp'],
    'unqualified function preserves target' => ["pragma_foreign_key_list('wp_options')", 'target', 'wp_options'],
    'temp shadow has one foreign key' => ["pragma_foreign_key_list('wp_options')", 'rows.count', 1],
    'temp shadow id starts at zero' => ["pragma_foreign_key_list('wp_options')", 'rows.0.id', 0],
    'temp shadow seq starts at zero' => ["pragma_foreign_key_list('wp_options')", 'rows.0.seq', 0],
    'temp shadow target table' => ["pragma_foreign_key_list('wp_options')", 'rows.0.table', 'wp_option_names'],
    'temp shadow from column' => ["pragma_foreign_key_list('wp_options')", 'rows.0.from', 'option_name'],
    'temp shadow to column' => ["pragma_foreign_key_list('wp_options')", 'rows.0.to', 'name'],
    'temp shadow default update action' => ["pragma_foreign_key_list('wp_options')", 'rows.0.on_update', 'NO ACTION'],
    'temp shadow delete action' => ["pragma_foreign_key_list('wp_options')", 'rows.0.on_delete', 'CASCADE'],
    'temp shadow default match' => ["pragma_foreign_key_list('wp_options')", 'rows.0.match', 'NONE'],
    'two argument main schema is pinned' => ["pragma_foreign_key_list('wp_options','main')", 'schema', 'main'],
    'two argument main row count bypasses temp' => ["pragma_foreign_key_list('wp_options','main')", 'rows.count', 2],
    'main first foreign key target table' => ["pragma_foreign_key_list('wp_options','main')", 'rows.0.table', 'wp_sites'],
    'main first foreign key from' => ["pragma_foreign_key_list('wp_options','main')", 'rows.0.from', 'blog_id'],
    'main first foreign key to' => ["pragma_foreign_key_list('wp_options','main')", 'rows.0.to', 'blog_id'],
    'main first foreign key update cascade' => ["pragma_foreign_key_list('wp_options','main')", 'rows.0.on_update', 'CASCADE'],
    'main first foreign key delete restrict' => ["pragma_foreign_key_list('wp_options','main')", 'rows.0.on_delete', 'RESTRICT'],
    'main first foreign key match simple' => ["pragma_foreign_key_list('wp_options','main')", 'rows.0.match', 'SIMPLE'],
    'main second foreign key id increments' => ["pragma_foreign_key_list('wp_options','main')", 'rows.1.id', 1],
    'main second foreign key from' => ["pragma_foreign_key_list('wp_options','main')", 'rows.1.from', 'option_name'],
    'main second foreign key to' => ["pragma_foreign_key_list('wp_options','main')", 'rows.1.to', 'name'],
    'main second foreign key delete set null' => ["pragma_foreign_key_list('wp_options','main')", 'rows.1.on_delete', 'SET NULL'],
    'quoted schema argument resolves network' => ["pragma_foreign_key_list('wp_sitemeta', \"network\")", 'schema', 'network'],
    'network composite row count' => ["pragma_foreign_key_list('wp_sitemeta', \"network\")", 'rows.count', 2],
    'network composite first shared id' => ["pragma_foreign_key_list('wp_sitemeta', \"network\")", 'rows.0.id', 0],
    'network composite first seq' => ["pragma_foreign_key_list('wp_sitemeta', \"network\")", 'rows.0.seq', 0],
    'network composite first from' => ["pragma_foreign_key_list('wp_sitemeta', \"network\")", 'rows.0.from', 'blog_id'],
    'network composite first to' => ["pragma_foreign_key_list('wp_sitemeta', \"network\")", 'rows.0.to', 'blog_id'],
    'network composite second shared id' => ["pragma_foreign_key_list('wp_sitemeta', \"network\")", 'rows.1.id', 0],
    'network composite second seq' => ["pragma_foreign_key_list('wp_sitemeta', \"network\")", 'rows.1.seq', 1],
    'network composite second from' => ["pragma_foreign_key_list('wp_sitemeta', \"network\")", 'rows.1.from', 'meta_key'],
    'network composite second to' => ["pragma_foreign_key_list('wp_sitemeta', \"network\")", 'rows.1.to', 'meta_key'],
    'network composite set default update' => ["pragma_foreign_key_list('wp_sitemeta', \"network\")", 'rows.0.on_update', 'SET DEFAULT'],
    'network composite cascade delete' => ["pragma_foreign_key_list('wp_sitemeta', \"network\")", 'rows.0.on_delete', 'CASCADE'],
    'network composite custom match uppercases' => ["pragma_foreign_key_list('wp_sitemeta', \"network\")", 'rows.0.match', 'CUSTOM'],
    'quoted table target with spaces resolves attached schema' => ["pragma_foreign_key_list('wp archived options')", 'schema', 'archive'],
    'quoted table target with spaces preserves target' => ["pragma_foreign_key_list('wp archived options')", 'target', 'wp archived options'],
    'references without column reports null to' => ["pragma_foreign_key_list('wp archived options')", 'rows.0.to', null],
    'archive delete action set default' => ["pragma_foreign_key_list('wp archived options')", 'rows.0.on_delete', 'SET DEFAULT'],
    'archive update action defaults no action' => ["pragma_foreign_key_list('wp archived options')", 'rows.0.on_update', 'NO ACTION'],
    'missing table resolves main empty rowset' => ["pragma_foreign_key_list('missing_options')", 'schema', 'main'],
    'missing table rows are empty' => ["pragma_foreign_key_list('missing_options')", 'rows.count', 0],
    'table without foreign keys returns empty rows' => ["pragma_foreign_key_list('wp_sites','main')", 'rows.count', 0],
    'function accepts trailing semicolon' => ["pragma_foreign_key_list('wp_options','main');", 'schema', 'main'],
    'function accepts bare table argument' => ['pragma_foreign_key_list(wp_options)', 'target', 'wp_options'],
    'function name is case insensitive' => ["PRAGMA_FOREIGN_KEY_LIST('wp_options')", 'schema', 'temp'],
];

$tests = [];
foreach ($cases as $name => [$sql, $path, $expected]) {
    $tests['pragma foreign key list current next18 ' . $name] = static function (TestRunner $t) use ($makeCatalog, $valueAt, $sql, $path, $expected): void {
        $t->same($expected, $valueAt($makeCatalog()->executeTableValuedPragma($sql), $path));
    };
}

$tests['pragma foreign key list current next18 standalone catalog table function'] = static function (TestRunner $t) use ($record): void {
    $catalog = new SQLitePragmaSchemaCatalog([
        $record('table', 'wp_postmeta', 'wp_postmeta', 2, 'CREATE TABLE wp_postmeta(meta_id INTEGER PRIMARY KEY, post_id INTEGER REFERENCES wp_posts(ID) ON DELETE CASCADE)'),
    ]);

    $rows = $catalog->executeTableValuedPragma("pragma_foreign_key_list('wp_postmeta')")['rows'];
    $t->same(1, count($rows));
    $t->same('wp_posts', $rows[0]['table']);
    $t->same('post_id', $rows[0]['from']);
    $t->same('ID', $rows[0]['to']);
    $t->same('CASCADE', $rows[0]['on_delete']);
};

$tests['pragma foreign key list current next18 cursor parity'] = static function (TestRunner $t) use ($makeCatalog): void {
    $result = $makeCatalog()->executeTableValuedPragma("pragma_foreign_key_list('wp_options','main')");
    $cursor = new PortLibs\LibSqlite\SQLitePragmaRowCursor($result);

    $t->same($result['rows'][0], $cursor->current());
    $cursor->next();
    $t->same($result['rows'][1], $cursor->current());
    $cursor->next();
    $t->same(false, $cursor->valid());
    $t->same(true, $cursor->metadata()['eof']);
};

$tests['pragma foreign key list current next18 rejects malformed table valued shapes'] = static function (TestRunner $t) use ($makeCatalog): void {
    $catalog = $makeCatalog();

    $t->same([], $catalog->executeTableValuedPragma('pragma_foreign_key_list()')['rows']);
    $t->throws(InvalidArgumentException::class, static fn () => $catalog->executeTableValuedPragma("pragma_foreign_key_list('wp_options','main','extra')"));
    $t->throws(InvalidArgumentException::class, static fn () => $catalog->executeTableValuedPragma('pragma_foreign_key_list'));
    $t->same('table_info', $catalog->executeTableValuedPragma("pragma_table_info('wp_options')")['pragma']);
    $t->throws(InvalidArgumentException::class, static fn () => $catalog->executeTableValuedPragma("foreign_key_list('wp_options')"));
    $t->throws(InvalidArgumentException::class, static fn () => $catalog->executeTableValuedPragma("pragma_foreign_key_list('wp_options', '')"));
    $t->throws(InvalidArgumentException::class, static fn () => $catalog->executeTableValuedPragma("pragma_foreign_key_list('wp_options','missing')"));
};

return $tests;
