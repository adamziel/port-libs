<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteAttachedSchemaCatalog;
use PortLibs\LibSqlite\SQLiteAttachWalTempViewCachePlan;
use PortLibs\LibSqlite\SQLiteSchemaRecord;

$record82 = static fn (string $type, string $name, string $table, ?int $root, ?string $sql, int $rowid): SQLiteSchemaRecord => new SQLiteSchemaRecord(
    $type,
    $name,
    $table,
    $root,
    $sql,
    $rowid,
);

$catalog82 = static function () use ($record82): SQLiteAttachedSchemaCatalog {
    $catalog = new SQLiteAttachedSchemaCatalog(
        [
            $record82('table', 'wp_options', 'wp_options', 2, 'CREATE TABLE main.wp_options(option_id integer primary key, option_name text, option_value text, autoload text)', 1),
            $record82('table', 'wp_posts', 'wp_posts', 3, 'CREATE TABLE main.wp_posts(ID integer primary key, post_title text)', 2),
            $record82('view', 'autoloaded_options', 'autoloaded_options', 0, "CREATE VIEW main.autoloaded_options AS SELECT option_id, option_name, option_value FROM wp_options WHERE autoload = 'yes'", 3),
            $record82('view', 'post_titles', 'post_titles', 0, 'CREATE VIEW main.post_titles AS SELECT ID, post_title FROM main.wp_posts', 4),
        ],
        [
            $record82('table', 'wp_options', 'wp_options', 10, 'CREATE TEMP TABLE wp_options(option_id integer, option_name text, option_value text)', 5),
            $record82('view', 'autoloaded_options', 'autoloaded_options', 0, 'CREATE TEMP VIEW autoloaded_options AS SELECT option_id, option_name, option_value FROM wp_options', 6),
        ],
    );
    $catalog->attach('site', '/srv/wp/site.sqlite', [
        $record82('table', 'wp_options', 'wp_options', 20, 'CREATE TABLE site.wp_options(blog_id integer, option_name text, option_value text)', 7),
        $record82('table', 'wp_blogs', 'wp_blogs', 21, 'CREATE TABLE site.wp_blogs(blog_id integer, domain text)', 8),
        $record82('view', 'site_options', 'site_options', 0, 'CREATE VIEW site.site_options AS SELECT blog_id, option_name, option_value FROM wp_options', 9),
        $record82('view', 'site_blog_options', 'site_blog_options', 0, 'CREATE VIEW site.site_blog_options AS SELECT b.domain, o.option_value FROM wp_blogs b JOIN wp_options o ON o.blog_id = b.blog_id', 10),
    ]);
    $catalog->attach('archive', '/srv/wp/archive.sqlite', [
        $record82('table', 'wp_options', 'wp_options', 30, 'CREATE TABLE archive.wp_options(blog_id integer, option_name text, option_value text)', 11),
        $record82('view', 'archived_options', 'archived_options', 0, 'CREATE VIEW archive.archived_options AS SELECT blog_id, option_name, option_value FROM wp_options', 12),
    ]);

    return $catalog;
};

$mainNext82 = static fn (int $root = 40) => [
    $record82('table', 'wp_options', 'wp_options', $root, 'CREATE TABLE main.wp_options(option_id integer primary key, option_name text, option_value text, autoload text, migrated integer)', $root + 1),
    $record82('table', 'wp_posts', 'wp_posts', 3, 'CREATE TABLE main.wp_posts(ID integer primary key, post_title text)', $root + 2),
    $record82('view', 'autoloaded_options', 'autoloaded_options', 0, "CREATE VIEW main.autoloaded_options AS SELECT option_id, option_name, option_value FROM wp_options WHERE autoload = 'yes'", $root + 3),
    $record82('view', 'post_titles', 'post_titles', 0, 'CREATE VIEW main.post_titles AS SELECT ID, post_title FROM main.wp_posts', $root + 4),
];

$tempNext82 = static fn (int $root = 50) => [
    $record82('table', 'wp_options', 'wp_options', $root, 'CREATE TEMP TABLE wp_options(option_id integer, option_name text, option_value text, expires integer)', $root + 1),
    $record82('view', 'autoloaded_options', 'autoloaded_options', 0, 'CREATE TEMP VIEW autoloaded_options AS SELECT option_id, option_name, option_value FROM wp_options', $root + 2),
];

$siteNext82 = static fn (int $optionRoot = 60, int $blogRoot = 21) => [
    $record82('table', 'wp_options', 'wp_options', $optionRoot, 'CREATE TABLE site.wp_options(blog_id integer, option_name text, option_value text, migrated integer)', $optionRoot + 1),
    $record82('table', 'wp_blogs', 'wp_blogs', $blogRoot, 'CREATE TABLE site.wp_blogs(blog_id integer, domain text)', $optionRoot + 2),
    $record82('view', 'site_options', 'site_options', 0, 'CREATE VIEW site.site_options AS SELECT blog_id, option_name, option_value FROM wp_options', $optionRoot + 3),
    $record82('view', 'site_blog_options', 'site_blog_options', 0, 'CREATE VIEW site.site_blog_options AS SELECT b.domain, o.option_value FROM wp_blogs b JOIN wp_options o ON o.blog_id = b.blog_id', $optionRoot + 4),
];

$states82 = static fn (): array => [
    'main' => ['schema_cookie' => 12, 'wal_schema_cookie' => 13],
    'temp' => ['schema_cookie' => 3],
    'site' => ['schema_cookie' => 8, 'wal_frames' => [['page' => 1, 'schema_cookie' => 9, 'commit' => true]]],
    'archive' => ['schema_cookie' => 4, 'wal_frames' => [['page' => 2, 'schema_cookie' => 99, 'commit' => true]]],
];

$plan82 = static fn (array $views, array $next = [], ?array $states = null): array => SQLiteAttachWalTempViewCachePlan::preparedViewCacheRepreparePlan(
    $catalog82(),
    $views,
    $next,
    $states ?? $states82(),
);

$stable82 = static fn (): array => $plan82([
    ['name' => 'post_titles', 'source' => 'main', 'active' => true],
    ['name' => 'archive.archived_options', 'source' => 'archive'],
]);
$mainChanged82 = static fn (): array => $plan82([
    ['name' => 'main.autoloaded_options', 'source' => 'main', 'active' => true],
    ['name' => 'post_titles', 'source' => 'main'],
], ['main' => $mainNext82(40)]);
$tempChanged82 = static fn (): array => $plan82([
    ['name' => 'autoloaded_options', 'source' => 'temp', 'active' => true],
    ['name' => 'main.autoloaded_options', 'source' => 'main'],
], ['temp' => $tempNext82(50)]);
$siteChanged82 = static fn (): array => $plan82([
    ['name' => 'site.site_options', 'source' => 'site', 'active' => true],
    ['name' => 'site.site_blog_options', 'source' => 'site'],
], ['site' => $siteNext82(60, 61)]);
$unrelatedArchive82 = static fn (): array => $plan82([
    ['name' => 'main.autoloaded_options', 'source' => 'main', 'active' => true],
], ['archive' => [
    $record82('table', 'wp_options', 'wp_options', 99, 'CREATE TABLE archive.wp_options(blog_id integer, option_name text, option_value text, migrated integer)', 99),
    $record82('view', 'archived_options', 'archived_options', 0, 'CREATE VIEW archive.archived_options AS SELECT blog_id, option_name, option_value FROM wp_options', 100),
]]);

$value82 = static function (array $data, string $path): mixed {
    $cursor = $data;
    $parts = explode('.', $path);
    while ($parts !== []) {
        if (!is_array($cursor)) {
            return null;
        }
        for ($length = count($parts); $length >= 1; $length--) {
            $candidate = implode('.', array_slice($parts, 0, $length));
            if (array_key_exists($candidate, $cursor)) {
                $cursor = $cursor[$candidate];
                $parts = array_slice($parts, $length);
                continue 2;
            }
        }

        return null;
    }

    return $cursor;
};

$pathCases82 = [
    'stable status' => [$stable82, 'status', 'view_cache_stable'],
    'stable operation' => [$stable82, 'operation', 'attach-wal-temp-schema-view-cache-reprepare'],
    'stable source main' => [$stable82, 'source_schema', 'main'],
    'stable view count' => [$stable82, 'view_count', 2],
    'stable active count' => [$stable82, 'active_view_count', 1],
    'stable no reprepare' => [$stable82, 'requires_reprepare', false],
    'stable views list' => [$stable82, 'stable_views', ['post_titles', 'archive.archived_options']],
    'stable active current snapshot empty' => [$stable82, 'active_current_snapshot_views', []],
    'stable archive source tracked' => [$stable82, 'source_schemas.archive.archived_options', 'archive'],
    'stable wal sources preserved' => [$stable82, 'wal_schema_cookie_sources', ['main', 'site', 'archive']],
    'stable site page one cookie' => [$stable82, 'schema_cookies_next.site', 9],
    'stable dependency marker' => [$stable82, 'dependencies.0', 'sqlite-attach-wal-temp-schema-view-cache-reprepare'],

    'main change status expired' => [$mainChanged82, 'status', 'view_cache_expired'],
    'main change active current source' => [$mainChanged82, 'active_current_snapshot_views', ['main.autoloaded_options']],
    'main change reset schema list' => [$mainChanged82, 'reset_schema_views', ['main.autoloaded_options']],
    'main change inactive next step empty' => [$mainChanged82, 'next_step_schema_views', []],
    'main change stable post titles' => [$mainChanged82, 'stable_views', ['post_titles']],
    'main change reprepare view' => [$mainChanged82, 'reprepare_views', ['main.autoloaded_options']],
    'main change action' => [$mainChanged82, 'views.main.autoloaded_options.next_step_action', 'finish_current_source_then_sqlite_schema_on_reset'],
    'main change current result ok' => [$mainChanged82, 'views.main.autoloaded_options.current_step_result', 'SQLITE_OK'],
    'main change post title action reuse' => [$mainChanged82, 'views.post_titles.next_step_action', 'reuse_prepared_view'],
    'main change dependency after root' => [$mainChanged82, 'views.main.autoloaded_options.dependencies_after.main.wp_options.rootpage', 40],

    'temp change active current source' => [$tempChanged82, 'active_current_snapshot_views', ['autoloaded_options']],
    'temp change main view stable' => [$tempChanged82, 'stable_views', ['main.autoloaded_options']],
    'temp change unqualified dependency after root' => [$tempChanged82, 'views.autoloaded_options.dependencies_after.wp_options.rootpage', 50],
    'temp change source temp' => [$tempChanged82, 'views.autoloaded_options.source_schema', 'temp'],
    'temp change main dependency stays main' => [$tempChanged82, 'views.main.autoloaded_options.dependencies_after.main.wp_options.rootpage', 2],

    'site change reprepare both' => [$siteChanged82, 'reprepare_views', ['site.site_options', 'site.site_blog_options']],
    'site change active reset only first' => [$siteChanged82, 'reset_schema_views', ['site.site_options']],
    'site change inactive next step second' => [$siteChanged82, 'next_step_schema_views', ['site.site_blog_options']],
    'site change inactive result schema' => [$siteChanged82, 'views.site.site_blog_options.current_step_result', 'SQLITE_SCHEMA'],
    'site change blog dependency after' => [$siteChanged82, 'views.site.site_blog_options.dependencies_after.site.wp_blogs.rootpage', 61],
    'site change option dependency after' => [$siteChanged82, 'views.site.site_blog_options.dependencies_after.site.wp_options.rootpage', 60],

    'unrelated archive status stable' => [$unrelatedArchive82, 'status', 'view_cache_stable'],
    'unrelated archive no reprepare' => [$unrelatedArchive82, 'requires_reprepare', false],
    'unrelated archive stable active main' => [$unrelatedArchive82, 'stable_views', ['main.autoloaded_options']],
    'unrelated archive changed schemas archive main site' => [$unrelatedArchive82, 'changed_schemas', ['archive', 'main', 'site']],
];

foreach ($pathCases82 as $name => [$factory, $path, $expected]) {
    $tests['attach wal temp schema view cache current source next82 ' . $name] = static function (TestRunner $t) use ($factory, $value82, $path, $expected): void {
        $t->same($expected, $value82($factory(), $path));
    };
}

$predicateCases82 = [
    'main active view keeps old source until reset' => static fn (): bool => $mainChanged82()['views']['main.autoloaded_options']['current_source_kept_until_reset'] === true,
    'main active view dependencies changed' => static fn (): bool => $mainChanged82()['views']['main.autoloaded_options']['dependencies_changed'] === true,
    'main stable view dependencies unchanged' => static fn (): bool => $mainChanged82()['views']['post_titles']['dependencies_changed'] === false,
    'temp active view changed by temp table only' => static fn (): bool => $tempChanged82()['views']['autoloaded_options']['dependencies_before']['wp_options']['schema'] === 'temp',
    'temp active view after remains temp' => static fn (): bool => $tempChanged82()['views']['autoloaded_options']['dependencies_after']['wp_options']['schema'] === 'temp',
    'temp write does not expire qualified main view' => static fn (): bool => $tempChanged82()['views']['main.autoloaded_options']['requires_reprepare'] === false,
    'site active view action is reset schema' => static fn (): bool => $siteChanged82()['views']['site.site_options']['next_step_action'] === 'finish_current_source_then_sqlite_schema_on_reset',
    'site inactive view action is next step schema' => static fn (): bool => $siteChanged82()['views']['site.site_blog_options']['next_step_action'] === 'sqlite_schema_on_next_step',
    'site inactive view does not keep current source' => static fn (): bool => $siteChanged82()['views']['site.site_blog_options']['current_source_kept_until_reset'] === false,
    'site source map records attached source' => static fn (): bool => $siteChanged82()['source_schemas']['site.site_options'] === 'site',
    'archive replacement does not expire main dependency' => static fn (): bool => $unrelatedArchive82()['views']['main.autoloaded_options']['dependencies_changed'] === false,
    'archive replacement still records schema update' => static fn (): bool => $unrelatedArchive82()['schema_record_updates'] === ['archive'],
    'uncommitted page one wal cookie ignored' => static function () use ($plan82, $states82): bool {
        $states = $states82();
        $states['site']['wal_frames'] = [['page' => 1, 'schema_cookie' => 44, 'commit' => false]];
        return $plan82([['name' => 'site.site_options', 'source' => 'site']], [], $states)['schema_cookies_next']['site'] === 8;
    },
    'committed non page one wal cookie ignored for schema' => static function () use ($plan82, $states82): bool {
        $states = $states82();
        $states['archive']['wal_frames'] = [['page' => 2, 'schema_cookie' => 44, 'commit' => true]];
        return $plan82([['name' => 'archive.archived_options', 'source' => 'archive']], [], $states)['schema_cookies_next']['archive'] === 4;
    },
    'explicit wal cookie wins over frame' => static function () use ($plan82, $states82): bool {
        $states = $states82();
        $states['site']['wal_schema_cookie'] = 55;
        return $plan82([['name' => 'site.site_options', 'source' => 'site']], [], $states)['schema_cookies_next']['site'] === 55;
    },
    'missing qualified view is stable null entry' => static function () use ($plan82): bool {
        $plan = $plan82([['name' => 'missing.nope', 'source' => 'main']]);
        return $plan['views']['missing.nope']['before']['schema'] === null
            && $plan['views']['missing.nope']['requires_reprepare'] === false;
    },
    'constant view has no dependency tables' => static function () use ($catalog82, $record82): bool {
        $catalog = $catalog82();
        $records = $catalog->schemaRecords('main');
        $records[] = $record82('view', 'constant_view', 'constant_view', 0, 'CREATE VIEW main.constant_view AS SELECT 1 AS one', 200);
        $catalog->replaceSchemaRecords('main', $records);
        $plan = SQLiteAttachWalTempViewCachePlan::preparedViewCacheRepreparePlan($catalog, [['name' => 'constant_view', 'source' => 'main', 'active' => true]]);
        return $plan['views']['constant_view']['dependency_tables_before'] === [];
    },
    'quoted attached view source is normalized' => static function () use ($catalog82): bool {
        $plan = SQLiteAttachWalTempViewCachePlan::preparedViewCacheRepreparePlan($catalog82(), [['name' => 'site.site_options', 'source' => '"site"']]);
        return $plan['source_schemas']['site.site_options'] === 'site';
    },
];

foreach ($predicateCases82 as $name => $predicate) {
    $tests['attach wal temp schema view cache current source next82 ' . $name] = static function (TestRunner $t) use ($predicate): void {
        $t->same(true, $predicate());
    };
}

$errorCases82 = [
    'rejects empty prepared view list' => static fn () => SQLiteAttachWalTempViewCachePlan::preparedViewCacheRepreparePlan($catalog82(), []),
    'rejects missing view name' => static fn () => SQLiteAttachWalTempViewCachePlan::preparedViewCacheRepreparePlan($catalog82(), [['source' => 'main']]),
    'rejects empty view name' => static fn () => SQLiteAttachWalTempViewCachePlan::preparedViewCacheRepreparePlan($catalog82(), [['name' => '']]),
    'rejects missing source schema' => static fn () => SQLiteAttachWalTempViewCachePlan::preparedViewCacheRepreparePlan($catalog82(), [['name' => 'autoloaded_options', 'source' => 'missing']]),
    'rejects replacement missing schema' => static fn () => SQLiteAttachWalTempViewCachePlan::preparedViewCacheRepreparePlan($catalog82(), [['name' => 'autoloaded_options']], ['missing' => []]),
    'rejects non integer schema cookie' => static fn () => SQLiteAttachWalTempViewCachePlan::preparedViewCacheRepreparePlan($catalog82(), [['name' => 'autoloaded_options']], [], ['main' => ['schema_cookie' => '12']]),
    'rejects non integer wal frame page' => static fn () => SQLiteAttachWalTempViewCachePlan::preparedViewCacheRepreparePlan($catalog82(), [['name' => 'autoloaded_options']], [], ['main' => ['schema_cookie' => 12, 'wal_frames' => [['page' => '1']]]]),
    'rejects non integer wal schema cookie' => static fn () => SQLiteAttachWalTempViewCachePlan::preparedViewCacheRepreparePlan($catalog82(), [['name' => 'autoloaded_options']], [], ['main' => ['schema_cookie' => 12, 'wal_schema_cookie' => '13']]),
];

foreach ($errorCases82 as $name => $callback) {
    $tests['attach wal temp schema view cache current source next82 ' . $name] = static function (TestRunner $t) use ($callback): void {
        $t->throws(InvalidArgumentException::class, $callback);
    };
}

return $tests;
