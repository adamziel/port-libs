<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteAttachedSchemaCatalog;
use PortLibs\LibSqlite\SQLiteAttachWalTempViewCachePlan;
use PortLibs\LibSqlite\SQLiteSchemaRecord;

$record78 = static fn (string $type, string $name, string $table, ?int $root, ?string $sql, int $rowid): SQLiteSchemaRecord => new SQLiteSchemaRecord(
    $type,
    $name,
    $table,
    $root,
    $sql,
    $rowid,
);

$catalog78 = static function () use ($record78): SQLiteAttachedSchemaCatalog {
    $catalog = new SQLiteAttachedSchemaCatalog(
        [
            $record78('table', 'wp_options', 'wp_options', 2, 'CREATE TABLE main.wp_options(option_id integer primary key, option_name text, option_value text, autoload text)', 1),
            $record78('table', 'wp_posts', 'wp_posts', 3, 'CREATE TABLE main.wp_posts(ID integer primary key, post_title text)', 2),
            $record78('view', 'autoloaded_options', 'autoloaded_options', 0, "CREATE VIEW main.autoloaded_options AS SELECT option_id, option_name, option_value FROM wp_options WHERE autoload = 'yes'", 3),
            $record78('view', 'post_titles', 'post_titles', 0, 'CREATE VIEW main.post_titles AS SELECT ID, post_title FROM main.wp_posts', 4),
        ],
        [
            $record78('table', 'wp_options', 'wp_options', 10, 'CREATE TEMP TABLE wp_options(option_id integer, option_name text, option_value text)', 5),
            $record78('view', 'autoloaded_options', 'autoloaded_options', 0, 'CREATE TEMP VIEW autoloaded_options AS SELECT option_id, option_name, option_value FROM wp_options', 6),
        ],
    );
    $catalog->attach('site', '/srv/wp/site.sqlite', [
        $record78('table', 'wp_options', 'wp_options', 20, 'CREATE TABLE site.wp_options(blog_id integer, option_name text, option_value text)', 7),
        $record78('table', 'wp_blogs', 'wp_blogs', 21, 'CREATE TABLE site.wp_blogs(blog_id integer, domain text)', 8),
        $record78('view', 'site_options', 'site_options', 0, 'CREATE VIEW site.site_options AS SELECT blog_id, option_name, option_value FROM wp_options', 9),
        $record78('view', 'site_blog_options', 'site_blog_options', 0, 'CREATE VIEW site.site_blog_options AS SELECT b.domain, o.option_value FROM wp_blogs b JOIN wp_options o ON o.blog_id = b.blog_id', 10),
    ]);
    $catalog->attach('archive', '/srv/wp/archive.sqlite', [
        $record78('table', 'wp_options', 'wp_options', 30, 'CREATE TABLE archive.wp_options(blog_id integer, option_name text, option_value text)', 11),
        $record78('view', 'archived_options', 'archived_options', 0, 'CREATE VIEW archive.archived_options AS SELECT blog_id, option_name, option_value FROM wp_options', 12),
    ]);

    return $catalog;
};

$mainNext78 = static fn (int $root = 40) => [
    $record78('table', 'wp_options', 'wp_options', $root, 'CREATE TABLE main.wp_options(option_id integer primary key, option_name text, option_value text, autoload text, migrated integer)', $root + 1),
    $record78('table', 'wp_posts', 'wp_posts', 3, 'CREATE TABLE main.wp_posts(ID integer primary key, post_title text)', $root + 2),
    $record78('view', 'autoloaded_options', 'autoloaded_options', 0, "CREATE VIEW main.autoloaded_options AS SELECT option_id, option_name, option_value FROM wp_options WHERE autoload = 'yes'", $root + 3),
    $record78('view', 'post_titles', 'post_titles', 0, 'CREATE VIEW main.post_titles AS SELECT ID, post_title FROM main.wp_posts', $root + 4),
];

$tempNext78 = static fn (int $root = 50) => [
    $record78('table', 'wp_options', 'wp_options', $root, 'CREATE TEMP TABLE wp_options(option_id integer, option_name text, option_value text, expires integer)', $root + 1),
    $record78('view', 'autoloaded_options', 'autoloaded_options', 0, 'CREATE TEMP VIEW autoloaded_options AS SELECT option_id, option_name, option_value FROM wp_options', $root + 2),
];

$siteNext78 = static fn (int $optionRoot = 60, int $blogRoot = 21) => [
    $record78('table', 'wp_options', 'wp_options', $optionRoot, 'CREATE TABLE site.wp_options(blog_id integer, option_name text, option_value text, migrated integer)', $optionRoot + 1),
    $record78('table', 'wp_blogs', 'wp_blogs', $blogRoot, 'CREATE TABLE site.wp_blogs(blog_id integer, domain text)', $optionRoot + 2),
    $record78('view', 'site_options', 'site_options', 0, 'CREATE VIEW site.site_options AS SELECT blog_id, option_name, option_value FROM wp_options', $optionRoot + 3),
    $record78('view', 'site_blog_options', 'site_blog_options', 0, 'CREATE VIEW site.site_blog_options AS SELECT b.domain, o.option_value FROM wp_blogs b JOIN wp_options o ON o.blog_id = b.blog_id', $optionRoot + 4),
];

$schemaStates78 = static fn (): array => [
    'main' => ['schema_cookie' => 12, 'wal_schema_cookie' => 13],
    'temp' => ['schema_cookie' => 3],
    'site' => ['schema_cookie' => 8, 'wal_frames' => [['page' => 1, 'schema_cookie' => 9, 'commit' => true]]],
    'archive' => ['schema_cookie' => 4, 'wal_frames' => [['page' => 2, 'schema_cookie' => 99, 'commit' => true]]],
];

$plan78 = static fn (array $views, array $next = [], ?array $states = null): array => SQLiteAttachWalTempViewCachePlan::viewDependencyCachePlan(
    $catalog78(),
    $views,
    $next,
    $states ?? $schemaStates78(),
);

$stable78 = static fn (): array => $plan78(['post_titles', 'archive.archived_options']);
$mainChanged78 = static fn (): array => $plan78(['main.autoloaded_options'], ['main' => $mainNext78(40)]);
$tempChanged78 = static fn (): array => $plan78(['autoloaded_options'], ['temp' => $tempNext78(50)]);
$siteChanged78 = static fn (): array => $plan78(['site.site_options', 'site.site_blog_options'], ['site' => $siteNext78(60, 61)]);
$mixedChanged78 = static fn (): array => $plan78(['autoloaded_options', 'main.autoloaded_options', 'site.site_options'], [
    'temp' => $tempNext78(70),
    'main' => $mainNext78(80),
    'site' => $siteNext78(90, 21),
]);

$value78 = static function (array $data, string $path): mixed {
    $cursor = $data;
    foreach (explode('.', $path) as $part) {
        if (is_array($cursor) && array_key_exists($part, $cursor)) {
            $cursor = $cursor[$part];
            continue;
        }

        return null;
    }

    return $cursor;
};

$pathCases78 = [
    'stable status' => [$stable78, 'status', 'planned'],
    'stable operation' => [$stable78, 'operation', 'attach-temp-main-wal-view-cache-dependency-plan'],
    'stable view count' => [$stable78, 'view_count', 2],
    'stable requires reprepare false' => [$stable78, 'requires_reprepare', false],
    'stable reprepare views empty' => [$stable78, 'reprepare_views', []],
    'stable archive non page one wal unchanged' => [$stable78, 'schema_cookies_next.archive', 4],
    'stable wal sources' => [$stable78, 'wal_schema_cookie_sources', ['main', 'site', 'archive']],
    'stable main wal cookie next' => [$stable78, 'schema_cookies_next.main', 13],
    'stable site page one cookie next' => [$stable78, 'schema_cookies_next.site', 9],
    'stable dependency marker' => [$stable78, 'dependencies.1', 'sqlite-view-dependency-cache-reprepare'],

    'main changed requires reprepare' => [$mainChanged78, 'requires_reprepare', true],
    'main changed view listed' => [$mainChanged78, 'reprepare_views', ['main.autoloaded_options']],
    'main changed schema update' => [$mainChanged78, 'schema_record_updates', ['main']],
    'main changed schemas include main and site cookie' => [$mainChanged78, 'changed_schemas', ['main', 'site']],

    'temp changed unqualified view reprepare' => [$tempChanged78, 'reprepare_views', ['autoloaded_options']],
    'temp view resolves temp before' => [$tempChanged78, 'views.autoloaded_options.before.schema', 'temp'],
    'temp dependency before temp root' => [$tempChanged78, 'views.autoloaded_options.dependencies_before.wp_options.rootpage', 10],
    'temp dependency after temp root' => [$tempChanged78, 'views.autoloaded_options.dependencies_after.wp_options.rootpage', 50],
    'temp changed view row stable' => [$tempChanged78, 'views.autoloaded_options.view_changed', false],
    'temp changed schemas plus wal cookies' => [$tempChanged78, 'changed_schemas', ['temp', 'main', 'site']],

    'site changed both views reprepare' => [$siteChanged78, 'reprepare_views', ['site.site_options', 'site.site_blog_options']],
    'site page one wal cookie changed schema' => [$siteChanged78, 'schema_cookies_next.site', 9],

    'mixed all reprepare' => [$mixedChanged78, 'reprepare_views', ['autoloaded_options', 'main.autoloaded_options', 'site.site_options']],
    'mixed temp dependency after' => [$mixedChanged78, 'views.autoloaded_options.dependencies_after.wp_options.rootpage', 70],
    'mixed schema updates' => [$mixedChanged78, 'schema_record_updates', ['temp', 'main', 'site']],
];

foreach ($pathCases78 as $name => [$factory, $path, $expected]) {
    $tests['attach temp main wal view cache current next78 ' . $name] = static function (TestRunner $t) use ($factory, $value78, $path, $expected): void {
        $t->same($expected, $value78($factory(), $path));
    };
}

$predicateCases78 = [
    'main changed preserves view dependency name' => static fn (): bool => $mainChanged78()['views']['main.autoloaded_options']['dependency_tables_before'] === ['main.wp_options'],
    'stable main dependency root' => static fn (): bool => $stable78()['views']['post_titles']['dependencies_before']['main.wp_posts']['rootpage'] === 3,
    'stable main dependency after root' => static fn (): bool => $stable78()['views']['post_titles']['dependencies_after']['main.wp_posts']['rootpage'] === 3,
    'stable attached dependency root' => static fn (): bool => $stable78()['views']['archive.archived_options']['dependencies_before']['archive.wp_options']['rootpage'] === 30,
    'main changed view row stable' => static fn (): bool => $mainChanged78()['views']['main.autoloaded_options']['view_changed'] === false,
    'main changed dependencies changed' => static fn (): bool => $mainChanged78()['views']['main.autoloaded_options']['dependencies_changed'] === true,
    'main dependency before root' => static fn (): bool => $mainChanged78()['views']['main.autoloaded_options']['dependencies_before']['main.wp_options']['rootpage'] === 2,
    'main dependency after root' => static fn (): bool => $mainChanged78()['views']['main.autoloaded_options']['dependencies_after']['main.wp_options']['rootpage'] === 40,
    'temp changed keeps unqualified dependency name' => static fn (): bool => $tempChanged78()['views']['autoloaded_options']['dependency_tables_after'] === ['wp_options'],
    'site join records both dependencies' => static fn (): bool => $siteChanged78()['views']['site.site_blog_options']['dependency_tables_before'] === ['site.wp_blogs', 'site.wp_options'],
    'site simple dependency before' => static fn (): bool => $siteChanged78()['views']['site.site_options']['dependencies_before']['site.wp_options']['rootpage'] === 20,
    'site simple dependency after' => static fn (): bool => $siteChanged78()['views']['site.site_options']['dependencies_after']['site.wp_options']['rootpage'] === 60,
    'site join first dependency before' => static fn (): bool => $siteChanged78()['views']['site.site_blog_options']['dependencies_before']['site.wp_blogs']['rootpage'] === 21,
    'site join first dependency after' => static fn (): bool => $siteChanged78()['views']['site.site_blog_options']['dependencies_after']['site.wp_blogs']['rootpage'] === 61,
    'site join second dependency after' => static fn (): bool => $siteChanged78()['views']['site.site_blog_options']['dependencies_after']['site.wp_options']['rootpage'] === 60,
    'mixed main dependency after' => static fn (): bool => $mixedChanged78()['views']['main.autoloaded_options']['dependencies_after']['main.wp_options']['rootpage'] === 80,
    'mixed site dependency after' => static fn (): bool => $mixedChanged78()['views']['site.site_options']['dependencies_after']['site.wp_options']['rootpage'] === 90,
    'stable view row unchanged' => static fn (): bool => $stable78()['views']['post_titles']['view_changed'] === false,
    'stable dependencies unchanged' => static fn (): bool => $stable78()['views']['archive.archived_options']['dependencies_changed'] === false,
    'uncommitted page one frame does not advance cookie' => static function () use ($plan78, $schemaStates78): bool {
        $states = $schemaStates78();
        $states['site']['wal_frames'] = [['page' => 1, 'schema_cookie' => 10, 'commit' => false]];
        return $plan78(['site.site_options'], [], $states)['schema_cookies_next']['site'] === 8;
    },
    'explicit wal schema cookie wins over frame' => static function () use ($plan78, $schemaStates78): bool {
        $states = $schemaStates78();
        $states['site']['wal_schema_cookie'] = 11;
        return $plan78(['site.site_options'], [], $states)['schema_cookies_next']['site'] === 11;
    },
    'missing qualified view becomes null entry' => static function () use ($plan78): bool {
        $plan = $plan78(['missing.nope']);
        return $plan['views']['missing.nope']['before']['schema'] === null
            && $plan['views']['missing.nope']['requires_reprepare'] === false;
    },
    'view sql without from has no dependencies' => static function () use ($catalog78, $record78): bool {
        $catalog = $catalog78();
        $records = $catalog->schemaRecords('main');
        $records[] = $record78('view', 'constant_view', 'constant_view', 0, 'CREATE VIEW main.constant_view AS SELECT 1 AS one', 100);
        $catalog->replaceSchemaRecords('main', $records);
        $plan = SQLiteAttachWalTempViewCachePlan::viewDependencyCachePlan($catalog, ['constant_view']);
        return $plan['views']['constant_view']['dependency_tables_before'] === [];
    },
    'source schema can be attached' => static function () use ($catalog78): bool {
        return SQLiteAttachWalTempViewCachePlan::viewDependencyCachePlan($catalog78(), ['site.site_options'], [], [], 'site')['source_schema'] === 'site';
    },
];

foreach ($predicateCases78 as $name => $predicate) {
    $tests['attach temp main wal view cache current next78 ' . $name] = static function (TestRunner $t) use ($predicate): void {
        $t->same(true, $predicate());
    };
}

$errorCases78 = [
    'rejects empty view list' => static fn () => SQLiteAttachWalTempViewCachePlan::viewDependencyCachePlan($catalog78(), []),
    'rejects empty view name' => static fn () => SQLiteAttachWalTempViewCachePlan::viewDependencyCachePlan($catalog78(), ['']),
    'rejects missing source schema' => static fn () => SQLiteAttachWalTempViewCachePlan::viewDependencyCachePlan($catalog78(), ['autoloaded_options'], [], [], 'missing'),
    'rejects missing replacement schema' => static fn () => SQLiteAttachWalTempViewCachePlan::viewDependencyCachePlan($catalog78(), ['autoloaded_options'], ['missing' => []]),
    'rejects non integer schema cookie' => static fn () => SQLiteAttachWalTempViewCachePlan::viewDependencyCachePlan($catalog78(), ['autoloaded_options'], [], ['main' => ['schema_cookie' => 'x']]),
    'rejects bad wal frame page' => static fn () => SQLiteAttachWalTempViewCachePlan::viewDependencyCachePlan($catalog78(), ['autoloaded_options'], [], ['main' => ['schema_cookie' => 1, 'wal_frames' => [['page' => '1']]]]),
    'rejects bad wal cookie' => static fn () => SQLiteAttachWalTempViewCachePlan::viewDependencyCachePlan($catalog78(), ['autoloaded_options'], [], ['main' => ['schema_cookie' => 1, 'wal_schema_cookie' => '2']]),
];

foreach ($errorCases78 as $name => $callback) {
    $tests['attach temp main wal view cache current next78 ' . $name] = static function (TestRunner $t) use ($callback): void {
        $t->throws(InvalidArgumentException::class, $callback);
    };
}

return $tests;
