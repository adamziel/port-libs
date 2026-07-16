<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteAttachedSchemaCatalog;
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
    return new SQLiteAttachedSchemaCatalog(
        [
            $record('table', 'wp_options', 'wp_options', 2, 'CREATE TABLE wp_options(option_id INTEGER PRIMARY KEY, option_name TEXT, autoload TEXT)'),
            $record('index', 'wp_options_name', 'wp_options', 3, 'CREATE INDEX wp_options_name ON wp_options(option_name)'),
            $record('table', 'wp_posts', 'wp_posts', 4, 'CREATE TABLE wp_posts(ID INTEGER PRIMARY KEY, post_title TEXT)'),
        ],
        [
            $record('table', 'wp_options', 'wp_options', 20, 'CREATE TEMP TABLE wp_options(option_name TEXT, option_value TEXT)'),
            $record('index', 'wp_options_temp_name', 'wp_options', 21, 'CREATE INDEX wp_options_temp_name ON wp_options(option_name)'),
            $record('view', 'wp_temp_options', 'wp_temp_options', null, 'CREATE VIEW wp_temp_options AS SELECT option_name FROM wp_options'),
        ],
    );
};

$loader = static function (string $file, string $schema) use ($record): array {
    return match ($schema) {
        'archive' => [
            $record('table', 'wp_options', 'wp_options', 40, 'CREATE TABLE wp_options(option_name TEXT, archived_at TEXT)'),
            $record('index', 'wp_options_archive_name', 'wp_options', 41, 'CREATE INDEX wp_options_archive_name ON wp_options(option_name)'),
            $record('table', 'wp_comments', 'wp_comments', 42, 'CREATE TABLE wp_comments(comment_ID INTEGER PRIMARY KEY, comment_post_ID INTEGER)'),
        ],
        'shadow' => [
            $record('table', 'wp_network_posts', 'wp_network_posts', 50, 'CREATE TABLE wp_network_posts(ID INTEGER PRIMARY KEY, post_title TEXT, source TEXT)'),
            $record('index', 'wp_network_posts_title', 'wp_network_posts', 51, 'CREATE INDEX wp_network_posts_title ON wp_network_posts(post_title)'),
        ],
        'cache' => [
            $record('table', 'wp_cache', 'wp_cache', 60, 'CREATE TABLE wp_cache(cache_key TEXT PRIMARY KEY, cache_value TEXT)'),
            $record('index', 'wp_cache_key', 'wp_cache', 61, 'CREATE INDEX wp_cache_key ON wp_cache(cache_key)'),
        ],
        default => [],
    };
};

$valueAt = static function (mixed $value, string $path): mixed {
    $parts = explode('.', $path);
    for ($i = 0; $i < count($parts); $i++) {
        $part = $parts[$i];
        if (is_array($value)) {
            if (!array_key_exists($part, $value) && isset($parts[$i + 1]) && array_key_exists($part . '.' . $parts[$i + 1], $value)) {
                $part .= '.' . $parts[++$i];
            }
            $value = is_numeric($part) ? $value[(int) $part] : $value[$part];
            continue;
        }
        $value = $value->{$part};
    }

    return $value;
};

$afterArchiveAttach = static function () use ($makeCatalog, $loader): array {
    $catalog = $makeCatalog();
    $snapshot = $catalog->schemaCacheResolutionSnapshot(
        ['wp_options', 'main.wp_options', 'archive.wp_options', 'sqlite_schema', 'wp_comments'],
        ['wp_options_name', 'wp_options_archive_name'],
    );
    $catalog->executeAttachDetachSql("ATTACH '/srv/archive.sqlite' AS archive", $loader);

    return $catalog->schemaCacheResolutionInvalidation($snapshot);
};

$afterArchiveDetach = static function () use ($makeCatalog, $loader): array {
    $catalog = $makeCatalog();
    $catalog->executeAttachDetachSql("ATTACH '/srv/archive.sqlite' AS archive", $loader);
    $snapshot = $catalog->schemaCacheResolutionSnapshot(
        ['wp_options', 'main.wp_options', 'archive.wp_options', 'sqlite_schema', 'wp_comments'],
        ['wp_options_name', 'wp_options_archive_name'],
        'archive',
    );
    $catalog->executeAttachDetachSql('DETACH archive');

    return $catalog->schemaCacheResolutionInvalidation($snapshot);
};

$afterShadowDetach = static function () use ($makeCatalog, $loader): array {
    $catalog = $makeCatalog();
    $catalog->executeAttachDetachSql("ATTACH '/srv/shadow.sqlite' AS shadow", $loader);
    $snapshot = $catalog->schemaCacheResolutionSnapshot(['wp_network_posts', 'shadow.wp_network_posts'], ['wp_network_posts_title'], 'shadow');
    $catalog->executeAttachDetachSql('DETACH shadow');

    return $catalog->schemaCacheResolutionInvalidation($snapshot);
};

$tests = [];

foreach ([
    'snapshot generation starts zero' => [static fn () => $makeCatalog()->schemaCacheResolutionSnapshot(['wp_options'])['generation'], 0],
    'snapshot source defaults main' => [static fn () => $makeCatalog()->schemaCacheResolutionSnapshot(['wp_options'])['source'], 'main'],
    'snapshot quoted temp source normalizes' => [static fn () => $makeCatalog()->schemaCacheResolutionSnapshot(['wp_options'], [], '"TEMP"')['source'], 'temp'],
    'snapshot search order starts temp main' => [static fn () => $makeCatalog()->schemaCacheResolutionSnapshot(['wp_options'])['search_order'], ['temp', 'main']],
    'snapshot database list has temp row' => [static fn () => $makeCatalog()->schemaCacheResolutionSnapshot(['wp_options'])['database_list'][1], ['seq' => 1, 'name' => 'temp', 'file' => '']],
    'snapshot temp shadows main table schema' => [static fn () => $makeCatalog()->schemaCacheResolutionSnapshot(['wp_options'])['tables']['wp_options']['schema'], 'temp'],
    'snapshot temp shadows main table rootpage' => [static fn () => $makeCatalog()->schemaCacheResolutionSnapshot(['wp_options'])['tables']['wp_options']['rootpage'], 20],
    'snapshot explicit main table rootpage' => [static fn () => $makeCatalog()->schemaCacheResolutionSnapshot(['main.wp_options'])['tables']['main.wp_options']['rootpage'], 2],
    'snapshot main index remains main' => [static fn () => $makeCatalog()->schemaCacheResolutionSnapshot([], ['wp_options_name'])['indexes']['wp_options_name']['schema'], 'main'],
    'snapshot temp index remains temp when named' => [static fn () => $makeCatalog()->schemaCacheResolutionSnapshot([], ['wp_options_temp_name'])['indexes']['wp_options_temp_name']['schema'], 'temp'],
    'snapshot missing attached table is null' => [static fn () => $makeCatalog()->schemaCacheResolutionSnapshot(['archive.wp_options'])['tables']['archive.wp_options']['schema'], null],
    'snapshot missing attached index is null' => [static fn () => $makeCatalog()->schemaCacheResolutionSnapshot([], ['wp_options_archive_name'])['indexes']['wp_options_archive_name']['schema'], null],
    'snapshot bare sqlite schema remains main' => [static fn () => $makeCatalog()->schemaCacheResolutionSnapshot(['sqlite_schema'])['tables']['sqlite_schema']['schema'], 'main'],
    'snapshot temp schema alias remains temp' => [static fn () => $makeCatalog()->schemaCacheResolutionSnapshot(['sqlite_temp_schema'])['tables']['sqlite_temp_schema']['schema'], 'temp'],
] as $name => [$actual, $expected]) {
    $tests['attach temp main shadow cache current next35 ' . $name] = static function (TestRunner $t) use ($actual, $expected): void {
        $t->same($expected, $actual());
    };
}

foreach ([
    'attach invalidation marks stale' => ['stale', true],
    'attach invalidation current false' => ['current', false],
    'attach invalidation before generation zero' => ['before_generation', 0],
    'attach invalidation after generation one' => ['after_generation', 1],
    'attach invalidation adds archive' => ['added_schemas', ['archive']],
    'attach invalidation removes none' => ['removed_schemas', []],
    'attach invalidation schema list includes archive only' => ['invalidated_schemas', ['archive']],
    'attach invalidation search order includes archive' => ['after_search_order', ['temp', 'main', 'archive']],
    'attach invalidation database count grows' => ['after_database_count', 3],
    'attach invalidation sequence changes' => ['sequence_changed', true],
    'attach leaves temp shadowed wp_options unchanged' => ['table_changes.wp_options.changed', false],
    'attach keeps temp wp_options before schema' => ['table_changes.wp_options.before.schema', 'temp'],
    'attach keeps temp wp_options after schema' => ['table_changes.wp_options.after.schema', 'temp'],
    'attach keeps explicit main wp_options unchanged' => ['table_changes.main.wp_options.changed', false],
    'attach keeps explicit main rootpage' => ['table_changes.main.wp_options.after.rootpage', 2],
    'attach changes explicit archive table from missing' => ['table_changes.archive.wp_options.changed', true],
    'attach explicit archive table before missing' => ['table_changes.archive.wp_options.before.schema', null],
    'attach explicit archive table after archive' => ['table_changes.archive.wp_options.after.schema', 'archive'],
    'attach explicit archive table rootpage' => ['table_changes.archive.wp_options.after.rootpage', 40],
    'attach keeps sqlite_schema current main' => ['table_changes.sqlite_schema.changed', false],
    'attach sqlite_schema after rootpage canonical' => ['table_changes.sqlite_schema.after.rootpage', 1],
    'attach changes attached-only comments table' => ['table_changes.wp_comments.changed', true],
    'attach comments table before missing' => ['table_changes.wp_comments.before.schema', null],
    'attach comments table after archive' => ['table_changes.wp_comments.after.schema', 'archive'],
    'attach keeps main index unchanged' => ['index_changes.wp_options_name.changed', false],
    'attach changes archive index from missing' => ['index_changes.wp_options_archive_name.changed', true],
    'attach archive index after schema' => ['index_changes.wp_options_archive_name.after.schema', 'archive'],
    'attach archive index after rootpage' => ['index_changes.wp_options_archive_name.after.rootpage', 41],
    'attach changed tables list' => ['changed_tables', ['archive.wp_options', 'wp_comments']],
    'attach unchanged tables list' => ['unchanged_tables', ['wp_options', 'main.wp_options', 'sqlite_schema']],
    'attach changed indexes list' => ['changed_indexes', ['wp_options_archive_name']],
    'attach unchanged indexes list' => ['unchanged_indexes', ['wp_options_name']],
] as $name => [$path, $expected]) {
    $tests['attach temp main shadow cache current next35 ' . $name] = static function (TestRunner $t) use ($afterArchiveAttach, $valueAt, $path, $expected): void {
        $t->same($expected, $valueAt($afterArchiveAttach(), $path));
    };
}

foreach ([
    'detach invalidation marks stale' => ['stale', true],
    'detach invalidation removes archive' => ['removed_schemas', ['archive']],
    'detach invalidation adds none' => ['added_schemas', []],
    'detach invalidation search order returns temp main' => ['after_search_order', ['temp', 'main']],
    'detach leaves temp wp_options unchanged' => ['table_changes.wp_options.changed', false],
    'detach keeps explicit main unchanged' => ['table_changes.main.wp_options.changed', false],
    'detach changes explicit archive table to missing' => ['table_changes.archive.wp_options.changed', true],
    'detach archive table before schema archive' => ['table_changes.archive.wp_options.before.schema', 'archive'],
    'detach archive table after missing' => ['table_changes.archive.wp_options.after.schema', null],
    'detach changes attached-only comments to missing' => ['table_changes.wp_comments.changed', true],
    'detach comments before archive' => ['table_changes.wp_comments.before.schema', 'archive'],
    'detach comments after missing' => ['table_changes.wp_comments.after.schema', null],
    'detach keeps sqlite schema main' => ['table_changes.sqlite_schema.changed', false],
    'detach changes archive index to missing' => ['index_changes.wp_options_archive_name.changed', true],
    'detach archive index before schema' => ['index_changes.wp_options_archive_name.before.schema', 'archive'],
    'detach archive index after missing' => ['index_changes.wp_options_archive_name.after.schema', null],
    'detach changed tables list' => ['changed_tables', ['archive.wp_options', 'wp_comments']],
    'detach unchanged tables list' => ['unchanged_tables', ['wp_options', 'main.wp_options', 'sqlite_schema']],
    'detach changed indexes list' => ['changed_indexes', ['wp_options_archive_name']],
    'detach unchanged indexes list' => ['unchanged_indexes', ['wp_options_name']],
] as $name => [$path, $expected]) {
    $tests['attach temp main shadow cache current next35 ' . $name] = static function (TestRunner $t) use ($afterArchiveDetach, $valueAt, $path, $expected): void {
        $t->same($expected, $valueAt($afterArchiveDetach(), $path));
    };
}

foreach ([
    'detaching shadow removes unqualified attached-only table' => ['table_changes.wp_network_posts.changed', true],
    'shadow network posts before schema shadow' => ['table_changes.wp_network_posts.before.schema', 'shadow'],
    'shadow network posts after missing' => ['table_changes.wp_network_posts.after.schema', null],
    'shadow network posts before rootpage' => ['table_changes.wp_network_posts.before.rootpage', 50],
    'explicit shadow table becomes missing' => ['table_changes.shadow.wp_network_posts.changed', true],
    'explicit shadow table after missing' => ['table_changes.shadow.wp_network_posts.after.schema', null],
    'shadow index becomes missing' => ['index_changes.wp_network_posts_title.changed', true],
    'shadow index before schema shadow' => ['index_changes.wp_network_posts_title.before.schema', 'shadow'],
    'shadow index after missing' => ['index_changes.wp_network_posts_title.after.schema', null],
    'shadow detach changed tables' => ['changed_tables', ['wp_network_posts', 'shadow.wp_network_posts']],
    'shadow detach changed indexes' => ['changed_indexes', ['wp_network_posts_title']],
] as $name => [$path, $expected]) {
    $tests['attach temp main shadow cache current next35 ' . $name] = static function (TestRunner $t) use ($afterShadowDetach, $valueAt, $path, $expected): void {
        $t->same($expected, $valueAt($afterShadowDetach(), $path));
    };
}

$tests['attach temp main shadow cache current next35 fresh resolution invalidation is current'] = static function (TestRunner $t) use ($makeCatalog): void {
    $catalog = $makeCatalog();
    $snapshot = $catalog->schemaCacheResolutionSnapshot(['wp_options', 'sqlite_schema'], ['wp_options_name']);
    $plan = $catalog->schemaCacheResolutionInvalidation($snapshot);

    $t->same(true, $plan['current']);
    $t->same(false, $plan['stale']);
    $t->same([], $plan['changed_tables']);
    $t->same(['wp_options', 'sqlite_schema'], $plan['unchanged_tables']);
    $t->same([], $plan['changed_indexes']);
    $t->same(['wp_options_name'], $plan['unchanged_indexes']);
};

$tests['attach temp main shadow cache current next35 missing source schema raises'] = static function (TestRunner $t) use ($makeCatalog): void {
    $t->throws(InvalidArgumentException::class, static fn () => $makeCatalog()->schemaCacheResolutionSnapshot(['wp_options'], [], 'missing'));
};

$tests['attach temp main shadow cache current next35 partial legacy snapshot remains stale'] = static function (TestRunner $t) use ($makeCatalog): void {
    $plan = $makeCatalog()->schemaCacheResolutionInvalidation(['generation' => -1]);

    $t->same(false, $plan['current']);
    $t->same(true, $plan['stale']);
    $t->same([], $plan['changed_tables']);
    $t->same([], $plan['changed_indexes']);
};

return $tests;
