<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext;
use PortLibs\LibSqlite\SQLiteSchemaRecord;

$record172 = static fn (string $type, string $name, string $table, int $root, string $sql, int $rowid): SQLiteSchemaRecord => new SQLiteSchemaRecord($type, $name, $table, $root, $sql, $rowid);

$currentRecords172 = [
    $record172('table', 'wp_sites', 'wp_sites', 4, 'CREATE TABLE wp_sites(blog_id INTEGER PRIMARY KEY, domain TEXT COLLATE NOCASE)', 1),
    $record172('table', 'wp_option_names', 'wp_option_names', 5, 'CREATE TABLE wp_option_names(name TEXT COLLATE NOCASE, blog_id INTEGER, PRIMARY KEY(name, blog_id)) WITHOUT ROWID', 2),
    $record172('table', 'wp_post_names', 'wp_post_names', 6, 'CREATE TABLE wp_post_names(post_name TEXT PRIMARY KEY)', 3),
    $record172('table', 'wp_options', 'wp_options', 7, 'CREATE TABLE wp_options(option_id INTEGER PRIMARY KEY, option_name TEXT, blog_id TEXT, site_id TEXT, autoload TEXT, FOREIGN KEY(option_name, blog_id) REFERENCES wp_option_names(name, blog_id), FOREIGN KEY(site_id) REFERENCES wp_sites(blog_id))', 4),
    $record172('table', 'wp_posts', 'wp_posts', 8, 'CREATE TABLE wp_posts(ID INTEGER PRIMARY KEY, post_name TEXT, FOREIGN KEY(post_name) REFERENCES wp_post_names(post_name))', 5),
    $record172('index', 'wp_option_names_lookup', 'wp_option_names', 9, 'CREATE UNIQUE INDEX wp_option_names_lookup ON wp_option_names(name COLLATE NOCASE, blog_id)', 6),
    $record172('index', 'wp_post_names_lookup', 'wp_post_names', 10, 'CREATE UNIQUE INDEX wp_post_names_lookup ON wp_post_names(post_name)', 7),
];
$nextRecords172 = $currentRecords172;

$currentTables172 = [
    'wp_sites' => [
        ['rowid' => 1, 'blog_id' => 1, 'domain' => 'example.test'],
    ],
    'wp_option_names' => [
        ['name' => 'siteurl', 'blog_id' => 1],
    ],
    'wp_post_names' => [
        ['rowid' => 1, 'post_name' => 'hello-world'],
    ],
    'wp_options' => [
        ['rowid' => 1, 'option_id' => 1, 'option_name' => 'SITEURL', 'blog_id' => '1', 'site_id' => '1', 'autoload' => 'yes'],
        ['rowid' => 2, 'option_id' => 2, 'option_name' => 'missing', 'blog_id' => '2', 'site_id' => '404', 'autoload' => 'no'],
    ],
    'wp_posts' => [
        ['rowid' => 1, 'ID' => 1, 'post_name' => 'hello-world'],
        ['rowid' => 2, 'ID' => 2, 'post_name' => 'missing-post'],
    ],
];
$nextTables172 = [
    'wp_sites' => [
        ['rowid' => 1, 'blog_id' => 1, 'domain' => 'example.test'],
        ['rowid' => 404, 'blog_id' => 404, 'domain' => 'network.example.test'],
    ],
    'wp_option_names' => [
        ['name' => 'siteurl', 'blog_id' => 1],
        ['name' => 'missing', 'blog_id' => 2],
    ],
    'wp_post_names' => [
        ['rowid' => 1, 'post_name' => 'hello-world'],
    ],
    'wp_options' => $currentTables172['wp_options'],
    'wp_posts' => $currentTables172['wp_posts'],
];
$nextTablesPostsRepaired172 = [
    ...$nextTables172,
    'wp_post_names' => [
        ['rowid' => 1, 'post_name' => 'hello-world'],
        ['rowid' => 2, 'post_name' => 'missing-post'],
    ],
];

$page172 = static fn (
    string $foreignKeySql = 'PRAGMA foreign_key_check(wp_options)',
    int $offset = 0,
    int $limit = 172,
    ?array $cursor = null,
    ?array $nextTables = null,
    string $indexSql = 'PRAGMA temp.index_xinfo(wp_option_names_lookup)',
    bool $tableValued = false,
): array => SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext::currentNextPageFromCatalog172(
    $currentRecords172,
    $currentTables172,
    $nextRecords172,
    $nextTables ?? $nextTables172,
    $indexSql,
    $foreignKeySql,
    $offset,
    $limit,
    $cursor,
    $tableValued,
);

$valueAt172 = static function (mixed $value, string $path): mixed {
    foreach (explode('.', $path) as $part) {
        if (is_array($value) && array_key_exists($part, $value)) {
            $value = $value[$part];
            continue;
        }
        $value = $value[(int) $part];
    }

    return $value;
};

$targetOptions172 = static fn (): array => $page172();
$targetPostsBlocked172 = static fn (): array => $page172('PRAGMA foreign_key_check("wp_posts")');
$targetPostsOk172 = static fn (): array => $page172('PRAGMA foreign_key_check("wp_posts")', nextTables: $nextTablesPostsRepaired172);
$allTables172 = static fn (): array => $page172('PRAGMA foreign_key_check');

$cases172 = [
    'target options status ok' => [$targetOptions172, 'status', 'ok'],
    'target options total rows' => [$targetOptions172, 'total', 10],
    'target options current xinfo' => [$targetOptions172, 'current.index_xinfo', 2],
    'target options next xinfo' => [$targetOptions172, 'next_counts.index_xinfo', 2],
    'target options current admissions' => [$targetOptions172, 'current.index_admissions', 2],
    'target options next admissions' => [$targetOptions172, 'next_counts.index_admissions', 2],
    'target options current violations' => [$targetOptions172, 'current.foreign_key_violations', 2],
    'target options next violations' => [$targetOptions172, 'next_counts.foreign_key_violations', 0],
    'target options current blockers' => [$targetOptions172, 'current.total_blockers', 2],
    'target options next blockers' => [$targetOptions172, 'next_counts.total_blockers', 0],
    'target options delta violations' => [$targetOptions172, 'delta.foreign_key_violations', -2],
    'target options delta blockers' => [$targetOptions172, 'delta.total_blockers', -2],
    'target options cleared' => [$targetOptions172, 'delta.cleared', true],
    'target options ready' => [$targetOptions172, 'next_state.ready', true],
    'target options blocking empty' => [$targetOptions172, 'next_state.blocking', []],
    'target options source sql' => [$targetOptions172, 'current_source.foreign_key_sql', 'pragma foreign_key_check(wp_options)'],
    'target options source target' => [$targetOptions172, 'current_source.foreign_key_target', 'wp_options'],
    'target options next target' => [$targetOptions172, 'next_source.foreign_key_target', 'wp_options'],
    'target options schema from index xinfo' => [$targetOptions172, 'current.target_schema', 'temp'],
    'target options index name' => [$targetOptions172, 'current.target_index', 'wp_option_names_lookup'],
    'target options tables only options' => [$targetOptions172, 'current.foreign_key_tables', ['wp_options']],
    'target options parent indexes' => [$targetOptions172, 'current.parent_indexes', ['wp_option_names_lookup', 'rowid-primary-key']],
    'target options row0 xinfo source' => [$targetOptions172, 'rows.0.source', 'index_xinfo'],
    'target options row2 admission table' => [$targetOptions172, 'rows.2.table', 'wp_options'],
    'target options row3 admission parent' => [$targetOptions172, 'rows.3.parent', 'wp_sites'],
    'target options row4 violation rowid' => [$targetOptions172, 'rows.4.rowid', 2],
    'target options row5 second violation parent' => [$targetOptions172, 'rows.5.parent', 'wp_sites'],
    'target options row6 next side' => [$targetOptions172, 'rows.6.side', 'next'],
    'target posts blocked status' => [$targetPostsBlocked172, 'status', 'blocked'],
    'target posts current admissions' => [$targetPostsBlocked172, 'current.index_admissions', 1],
    'target posts current violations' => [$targetPostsBlocked172, 'current.foreign_key_violations', 1],
    'target posts next violations remains' => [$targetPostsBlocked172, 'next_counts.foreign_key_violations', 1],
    'target posts blocking' => [$targetPostsBlocked172, 'next_state.blocking', ['foreign_key_check']],
    'target posts table list' => [$targetPostsBlocked172, 'current.foreign_key_tables', ['wp_posts']],
    'target posts source target' => [$targetPostsBlocked172, 'current_source.foreign_key_target', 'wp_posts'],
    'target posts ok after repair' => [$targetPostsOk172, 'status', 'ok'],
    'target posts ok next violations' => [$targetPostsOk172, 'next_counts.foreign_key_violations', 0],
    'target posts ok delta' => [$targetPostsOk172, 'delta.foreign_key_violations', -1],
    'all tables target null' => [$allTables172, 'current_source.foreign_key_target', null],
    'all tables current violations' => [$allTables172, 'current.foreign_key_violations', 3],
    'all tables next violations' => [$allTables172, 'next_counts.foreign_key_violations', 1],
    'all tables target list' => [$allTables172, 'current.foreign_key_tables', ['wp_options', 'wp_posts']],
];

$tests = [];
foreach ($cases172 as $name => [$factory, $path, $expected]) {
    $tests['pragma index xinfo foreignkey targeted current source next172 ' . $name] = static function (TestRunner $t) use ($factory, $path, $expected, $valueAt172): void {
        $t->same($expected, $valueAt172($factory(), $path));
    };
}

$tests['pragma index xinfo foreignkey targeted current source next172 paginates target rows'] = static function (TestRunner $t) use ($page172): void {
    $first = $page172(offset: 0, limit: 5);
    $second = $page172(offset: 5, limit: 5, cursor: $first['next']);
    $third = $page172(offset: 10, limit: 5, cursor: $second['next']);

    $t->same(5, $first['count']);
    $t->same(['source_id' => $first['source_id'], 'offset' => 5], $first['next']);
    $t->same('foreign_key_check', $second['rows'][0]['kind']);
    $t->same('next', $second['rows'][1]['side']);
    $t->same(null, $third['next']);
};

$tests['pragma index xinfo foreignkey targeted current source next172 table-valued index source'] = static function (TestRunner $t) use ($page172): void {
    $result = $page172(indexSql: "pragma_index_xinfo('wp_option_names_lookup')", tableValued: true);

    $t->same('ok', $result['status']);
    $t->same(true, $result['current_source']['table_valued_index_xinfo']);
    $t->same("pragma_index_xinfo('wp_option_names_lookup')", $result['current_source']['index_xinfo_sql']);
};

$tests['pragma index xinfo foreignkey targeted current source next172 source changes with target'] = static function (TestRunner $t) use ($page172): void {
    $options = $page172();
    $posts = $page172('PRAGMA foreign_key_check(wp_posts)');

    $t->same(true, $options['source_id'] !== $posts['source_id']);
    $t->same('wp_options', $options['current_source']['foreign_key_target']);
    $t->same('wp_posts', $posts['current_source']['foreign_key_target']);
};

$tests['pragma index xinfo foreignkey targeted current source next172 rejects stale target cursor'] = static function (TestRunner $t) use ($page172): void {
    $first = $page172(offset: 0, limit: 5);
    $t->throws(InvalidArgumentException::class, static fn (): array => $page172('PRAGMA foreign_key_check(wp_posts)', 5, 5, $first['next']));
};

$tests['pragma index xinfo foreignkey targeted current source next172 rejects stale offset cursor'] = static function (TestRunner $t) use ($page172): void {
    $first = $page172(offset: 0, limit: 5);
    $t->throws(InvalidArgumentException::class, static fn (): array => $page172(offset: 6, limit: 5, cursor: $first['next']));
};

$tests['pragma index xinfo foreignkey targeted current source next172 rejects unsupported foreign key pragma'] = static function (TestRunner $t) use ($page172): void {
    $t->throws(InvalidArgumentException::class, static fn (): array => $page172('PRAGMA foreign_key_list(wp_options)'));
};

$tests['pragma index xinfo foreignkey targeted current source next172 rejects negative offset'] = static function (TestRunner $t) use ($page172): void {
    $t->throws(InvalidArgumentException::class, static fn (): array => $page172(offset: -1));
};

$tests['pragma index xinfo foreignkey targeted current source next172 rejects zero limit'] = static function (TestRunner $t) use ($page172): void {
    $t->throws(InvalidArgumentException::class, static fn (): array => $page172(limit: 0));
};

return $tests;
