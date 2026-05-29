<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteAttachedSchemaCatalog;
use PortLibs\LibSqlite\SQLiteAttachWalSchemaCachePlan;
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
            $record('table', 'wp_postmeta', 'wp_postmeta', 4, 'CREATE TABLE wp_postmeta(meta_id INTEGER PRIMARY KEY, post_id INTEGER, meta_key TEXT)'),
        ],
        [
            $record('table', 'wp_options', 'wp_options', 20, 'CREATE TEMP TABLE wp_options(option_name TEXT, option_value TEXT)'),
            $record('index', 'wp_options_temp_name', 'wp_options', 21, 'CREATE INDEX wp_options_temp_name ON wp_options(option_name)'),
        ],
    );
};

$loader = static function (string $file, string $schema) use ($record): array {
    return match ($schema) {
        'site' => [
            $record('table', 'wp_options', 'wp_options', 40, 'CREATE TABLE wp_options(option_name TEXT, option_value TEXT)'),
            $record('index', 'wp_options_site_name', 'wp_options', 41, 'CREATE INDEX wp_options_site_name ON wp_options(option_name)'),
            $record('table', 'wp_blogs', 'wp_blogs', 42, 'CREATE TABLE wp_blogs(blog_id INTEGER PRIMARY KEY, domain TEXT)'),
        ],
        'network' => [
            $record('table', 'wp_sitemeta', 'wp_sitemeta', 50, 'CREATE TABLE wp_sitemeta(meta_id INTEGER PRIMARY KEY, meta_key TEXT)'),
            $record('index', 'wp_sitemeta_key', 'wp_sitemeta', 51, 'CREATE INDEX wp_sitemeta_key ON wp_sitemeta(meta_key)'),
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

$attachPlan = static function () use ($makeCatalog, $loader): array {
    $catalog = $makeCatalog();
    $snapshot = SQLiteAttachWalSchemaCachePlan::snapshot(
        $catalog,
        ['wp_options', 'main.wp_options', 'site.wp_options', 'wp_blogs', 'sqlite_schema'],
        ['wp_options_name', 'wp_options_site_name'],
        ['main' => 9, 'temp' => 2],
        'main',
        4,
    );
    $catalog->executeAttachDetachSql("ATTACH '/srv/wp/site.sqlite' AS site", $loader);

    return SQLiteAttachWalSchemaCachePlan::currentNext(
        $catalog,
        $snapshot,
        ['main' => 10, 'temp' => 2, 'site' => 1],
        6,
    );
};

$detachPlan = static function () use ($makeCatalog, $loader): array {
    $catalog = $makeCatalog();
    $catalog->executeAttachDetachSql("ATTACH '/srv/wp/site.sqlite' AS site", $loader);
    $snapshot = SQLiteAttachWalSchemaCachePlan::snapshot(
        $catalog,
        ['wp_options', 'main.wp_options', 'site.wp_options', 'wp_blogs', 'sqlite_schema'],
        ['wp_options_name', 'wp_options_site_name'],
        ['main' => 10, 'temp' => 2, 'site' => 1],
        'site',
        6,
    );
    $catalog->executeAttachDetachSql('DETACH site');

    return SQLiteAttachWalSchemaCachePlan::currentNext(
        $catalog,
        $snapshot,
        ['main' => 11, 'temp' => 2],
        8,
    );
};

$stablePlan = static function () use ($makeCatalog): array {
    $catalog = $makeCatalog();
    $snapshot = SQLiteAttachWalSchemaCachePlan::snapshot(
        $catalog,
        ['wp_options', 'main.wp_options', 'sqlite_schema'],
        ['wp_options_name'],
        ['main' => 9, 'temp' => 2],
        'main',
        4,
    );

    return SQLiteAttachWalSchemaCachePlan::currentNext($catalog, $snapshot, ['main' => 9, 'temp' => 2], 4);
};

$networkPlan = static function () use ($makeCatalog, $loader): array {
    $catalog = $makeCatalog();
    $catalog->executeAttachDetachSql("ATTACH '/srv/wp/network.sqlite' AS network", $loader);
    $snapshot = SQLiteAttachWalSchemaCachePlan::snapshot(
        $catalog,
        ['wp_sitemeta', 'network.wp_sitemeta', 'wp_options'],
        ['wp_sitemeta_key', 'wp_options_temp_name'],
        ['main' => 9, 'temp' => 2, 'network' => 4],
        'network',
        3,
    );

    return SQLiteAttachWalSchemaCachePlan::currentNext($catalog, $snapshot, ['main' => 9, 'temp' => 3, 'network' => 5], 5);
};

$tests = [];

foreach ([
    'snapshot records dependency' => [static fn (): mixed => SQLiteAttachWalSchemaCachePlan::snapshot($makeCatalog(), ['wp_options'], [], ['main' => 1])['dependencies'], ['sqlite-attach-wal-schema-cache-current-next']],
    'snapshot preserves wal end frame' => [static fn (): mixed => SQLiteAttachWalSchemaCachePlan::snapshot($makeCatalog(), ['wp_options'], [], ['main' => 1], 'main', 7)['wal_end_frame'], 7],
    'snapshot normalizes cookie schema names' => [static fn (): mixed => SQLiteAttachWalSchemaCachePlan::snapshot($makeCatalog(), ['wp_options'], [], ['MAIN' => 4, ' temp ' => 5])['schema_cookies'], ['main' => 4, 'temp' => 5]],
    'snapshot temp shadows main table' => [static fn (): mixed => SQLiteAttachWalSchemaCachePlan::snapshot($makeCatalog(), ['wp_options'], [], ['main' => 1])['tables']['wp_options']['schema'], 'temp'],
    'snapshot explicit main table stays main' => [static fn (): mixed => SQLiteAttachWalSchemaCachePlan::snapshot($makeCatalog(), ['main.wp_options'], [], ['main' => 1])['tables']['main.wp_options']['schema'], 'main'],
    'snapshot missing attached table is cacheable null' => [static fn (): mixed => SQLiteAttachWalSchemaCachePlan::snapshot($makeCatalog(), ['site.wp_options'], [], ['main' => 1])['tables']['site.wp_options']['schema'], null],
] as $name => [$actual, $expected]) {
    $tests['attach wal schema cache current next47 ' . $name] = static function (TestRunner $t) use ($actual, $expected): void {
        $t->same($expected, $actual());
    };
}

foreach ([
    'attach status planned' => ['status', 'planned'],
    'attach requires reprepare' => ['requires_reprepare', true],
    'attach reasons' => ['reasons', ['attach-detach-generation', 'attached-schema-added', 'wal-schema-cookie-changed', 'wal-end-frame-advanced']],
    'attach current wal frame' => ['current_reader.wal_end_frame', 4],
    'attach next wal frame' => ['next_reader.wal_end_frame', 6],
    'attach current cookies' => ['current_reader.schema_cookies', ['main' => 9, 'temp' => 2]],
    'attach next cookies' => ['next_reader.schema_cookies', ['main' => 10, 'site' => 1, 'temp' => 2]],
    'attach changed cookie schemas' => ['changed_cookie_schemas', ['main', 'site']],
    'attach main cookie before' => ['schema_cookie_changes.main.before', 9],
    'attach main cookie after' => ['schema_cookie_changes.main.after', 10],
    'attach site cookie before missing' => ['schema_cookie_changes.site.before', null],
    'attach site cookie after one' => ['schema_cookie_changes.site.after', 1],
    'attach temp cookie unchanged' => ['schema_cookie_changes.temp.changed', false],
    'attach search order next' => ['next_reader.search_order', ['temp', 'main', 'site']],
    'attach added schemas' => ['schema_cache.added_schemas', ['site']],
    'attach temp wp_options stays stable' => ['schema_cache.table_changes.wp_options.changed', false],
    'attach explicit main wp_options stable' => ['schema_cache.table_changes.main.wp_options.changed', false],
    'attach explicit site table changes' => ['schema_cache.table_changes.site.wp_options.changed', true],
    'attach site blogs changes' => ['schema_cache.table_changes.wp_blogs.changed', true],
    'attach sqlite schema stable' => ['schema_cache.table_changes.sqlite_schema.changed', false],
    'attach stable temp table not reprepared' => ['stable_tables', ['wp_options']],
    'attach main table reprepares for cookie' => ['table_reprepare.main.wp_options.reason', 'schema-cookie-changed'],
    'attach site table reprepares for resolution' => ['table_reprepare.site.wp_options.reason', 'resolution-changed'],
    'attach blogs reprepares for resolution' => ['table_reprepare.wp_blogs.schema', 'site'],
    'attach sqlite schema reprepares for main cookie' => ['table_reprepare.sqlite_schema.reason', 'schema-cookie-changed'],
    'attach main index reprepares for cookie' => ['index_reprepare.wp_options_name.reason', 'schema-cookie-changed'],
    'attach site index reprepares for resolution' => ['index_reprepare.wp_options_site_name.reason', 'resolution-changed'],
    'attach dependencies' => ['dependencies', ['sqlite-attach-wal-schema-cache-current-next', 'sqlite-wal-reader-current-next', 'sqlite-schema-cache']],
] as $name => [$path, $expected]) {
    $tests['attach wal schema cache current next47 ' . $name] = static function (TestRunner $t) use ($attachPlan, $valueAt, $path, $expected): void {
        $t->same($expected, $valueAt($attachPlan(), $path));
    };
}

foreach ([
    'detach requires reprepare' => ['requires_reprepare', true],
    'detach reasons' => ['reasons', ['attach-detach-generation', 'attached-schema-removed', 'wal-schema-cookie-changed', 'wal-end-frame-advanced']],
    'detach removed schemas' => ['schema_cache.removed_schemas', ['site']],
    'detach changed cookie schemas' => ['changed_cookie_schemas', ['main', 'site']],
    'detach site cookie removed' => ['schema_cookie_changes.site.after', null],
    'detach main cookie advanced' => ['schema_cookie_changes.main.after', 11],
    'detach search order next' => ['next_reader.search_order', ['temp', 'main']],
    'detach temp wp_options stable' => ['schema_cache.table_changes.wp_options.changed', false],
    'detach explicit site table changes' => ['schema_cache.table_changes.site.wp_options.changed', true],
    'detach blogs missing' => ['schema_cache.table_changes.wp_blogs.after.schema', null],
    'detach main table cookie reprepare' => ['table_reprepare.main.wp_options.reason', 'schema-cookie-changed'],
    'detach site table resolution reprepare' => ['table_reprepare.site.wp_options.reason', 'resolution-changed'],
    'detach blogs resolution reprepare' => ['table_reprepare.wp_blogs.reason', 'resolution-changed'],
    'detach stable temp table remains stable' => ['stable_tables', ['wp_options']],
    'detach site index resolution reprepare' => ['index_reprepare.wp_options_site_name.reason', 'resolution-changed'],
] as $name => [$path, $expected]) {
    $tests['attach wal schema cache current next47 ' . $name] = static function (TestRunner $t) use ($detachPlan, $valueAt, $path, $expected): void {
        $t->same($expected, $valueAt($detachPlan(), $path));
    };
}

foreach ([
    'stable plan does not require reprepare' => ['requires_reprepare', false],
    'stable plan has no reasons' => ['reasons', []],
    'stable current remains true' => ['schema_cache.current', true],
    'stable changed cookies empty' => ['changed_cookie_schemas', []],
    'stable temp wp_options unchanged' => ['schema_cache.table_changes.wp_options.changed', false],
    'stable main wp_options unchanged' => ['schema_cache.table_changes.main.wp_options.changed', false],
    'stable sqlite schema unchanged' => ['schema_cache.table_changes.sqlite_schema.changed', false],
    'stable table reprepare empty' => ['table_reprepare', []],
    'stable index reprepare empty' => ['index_reprepare', []],
    'stable table list' => ['stable_tables', ['wp_options', 'main.wp_options', 'sqlite_schema']],
    'stable index list' => ['stable_indexes', ['wp_options_name']],
] as $name => [$path, $expected]) {
    $tests['attach wal schema cache current next47 ' . $name] = static function (TestRunner $t) use ($stablePlan, $valueAt, $path, $expected): void {
        $t->same($expected, $valueAt($stablePlan(), $path));
    };
}

foreach ([
    'network plan reprepare from cookies only' => ['requires_reprepare', true],
    'network plan reasons are wal only' => ['reasons', ['wal-schema-cookie-changed', 'wal-end-frame-advanced']],
    'network schema cache current' => ['schema_cache.current', true],
    'network changed cookie schemas' => ['changed_cookie_schemas', ['network', 'temp']],
    'network table resolution unchanged' => ['schema_cache.table_changes.wp_sitemeta.changed', false],
    'network explicit table resolution unchanged' => ['schema_cache.table_changes.network.wp_sitemeta.changed', false],
    'network temp wp_options unchanged' => ['schema_cache.table_changes.wp_options.changed', false],
    'network unqualified sitemeta cookie reprepare' => ['table_reprepare.wp_sitemeta.reason', 'schema-cookie-changed'],
    'network explicit sitemeta cookie reprepare' => ['table_reprepare.network.wp_sitemeta.reason', 'schema-cookie-changed'],
    'network temp wp_options cookie reprepare' => ['table_reprepare.wp_options.reason', 'schema-cookie-changed'],
    'network sitemeta index cookie reprepare' => ['index_reprepare.wp_sitemeta_key.reason', 'schema-cookie-changed'],
    'network temp index cookie reprepare' => ['index_reprepare.wp_options_temp_name.reason', 'schema-cookie-changed'],
] as $name => [$path, $expected]) {
    $tests['attach wal schema cache current next47 ' . $name] = static function (TestRunner $t) use ($networkPlan, $valueAt, $path, $expected): void {
        $t->same($expected, $valueAt($networkPlan(), $path));
    };
}

$tests['attach wal schema cache current next47 missing source schema raises'] = static function (TestRunner $t) use ($makeCatalog): void {
    $t->throws(InvalidArgumentException::class, static fn (): mixed => SQLiteAttachWalSchemaCachePlan::snapshot($makeCatalog(), ['wp_options'], [], ['main' => 1], 'missing'));
};

return $tests;
