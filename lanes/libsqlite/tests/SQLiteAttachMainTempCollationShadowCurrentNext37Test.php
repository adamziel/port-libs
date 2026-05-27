<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteAttachedSchemaCatalog;
use PortLibs\LibSqlite\SQLiteAttachMainTempCollationShadowPlan;
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
            $record('table', 'wp_options', 'wp_options', 2, 'CREATE TABLE wp_options(option_name TEXT, option_value TEXT, autoload TEXT)'),
            $record('index', 'main_options_name_nocase', 'wp_options', 3, 'CREATE INDEX main_options_name_nocase ON wp_options(option_name COLLATE NOCASE)'),
            $record('index', 'main_options_value_slug', 'wp_options', 4, 'CREATE INDEX main_options_value_slug ON wp_options(option_value COLLATE wp_slug)'),
            $record('table', 'wp_posts', 'wp_posts', 5, 'CREATE TABLE wp_posts(post_name TEXT)'),
            $record('index', 'main_posts_name_binary', 'wp_posts', 6, 'CREATE INDEX main_posts_name_binary ON wp_posts(post_name)'),
        ],
        [
            $record('table', 'wp_options', 'wp_options', 10, 'CREATE TEMP TABLE wp_options(option_name TEXT, option_value TEXT, autoload TEXT)'),
            $record('index', 'temp_options_name_locale', 'wp_options', 11, 'CREATE INDEX temp_options_name_locale ON wp_options(option_name COLLATE wp_locale)'),
            $record('index', 'temp_options_autoload_binary', 'wp_options', 12, 'CREATE INDEX temp_options_autoload_binary ON wp_options(autoload)'),
        ],
    );
    $catalog->attach('site', '/srv/site.sqlite', [
        $record('table', 'wp_options', 'wp_options', 20, 'CREATE TABLE wp_options(option_name TEXT, option_value TEXT, autoload TEXT)'),
        $record('index', 'site_options_name_slug', 'wp_options', 21, 'CREATE INDEX site_options_name_slug ON wp_options(option_name COLLATE wp_slug)'),
        $record('index', 'site_options_autoload_rtrim', 'wp_options', 22, 'CREATE INDEX site_options_autoload_rtrim ON wp_options(autoload COLLATE RTRIM)'),
        $record('table', 'wp_sitemeta', 'wp_sitemeta', 23, 'CREATE TABLE wp_sitemeta(meta_key TEXT, meta_value TEXT)'),
        $record('index', 'site_meta_key_nocase', 'wp_sitemeta', 24, 'CREATE INDEX site_meta_key_nocase ON wp_sitemeta(meta_key COLLATE NOCASE)'),
    ]);
    $catalog->attach('archive', '/srv/archive.sqlite', [
        $record('table', 'wp_options', 'wp_options', 30, 'CREATE TABLE wp_options(option_name TEXT, option_value TEXT, blog_id INTEGER)'),
        $record('index', 'archive_options_composite', 'wp_options', 31, 'CREATE INDEX archive_options_composite ON wp_options(blog_id, option_name COLLATE [wp_slug], option_value COLLATE nocase)'),
        $record('index', 'archive_options_expr', 'wp_options', 32, 'CREATE INDEX archive_options_expr ON wp_options(lower(option_name) COLLATE wp_slug, substr(option_value,1,3) COLLATE rtrim)'),
    ]);

    return $catalog;
};

$plan = static fn (string $table = 'wp_options', array $collations = []): array => SQLiteAttachMainTempCollationShadowPlan::plan($makeCatalog(), $table, $collations);
$summary = static fn (string $table = 'wp_options', array $collations = []): array => SQLiteAttachMainTempCollationShadowPlan::shadowSummary($plan($table, $collations));

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

$tests = [];

foreach ([
    'unqualified target uses temp current source' => [$plan(), 'current_schema', 'temp'],
    'unqualified target keeps table name' => [$plan(), 'current_table', 'wp_options'],
    'unqualified target is not qualified' => [$plan(), 'qualified', false],
    'search order starts temp' => [$plan(), 'search_order.0', 'temp'],
    'search order keeps main second' => [$plan(), 'search_order.1', 'main'],
    'search order keeps first attached third' => [$plan(), 'search_order.2', 'site'],
    'search order keeps second attached fourth' => [$plan(), 'search_order.3', 'archive'],
    'builtins include binary' => [$plan(), 'registered_collations.0', 'BINARY'],
    'builtins include nocase' => [$plan(), 'registered_collations.1', 'NOCASE'],
    'builtins include rtrim' => [$plan(), 'registered_collations.2', 'RTRIM'],
    'temp current has two indexes' => [$plan(), 'current_indexes.count', 2],
    'temp current first index name' => [$plan(), 'current_indexes.0.name', 'temp_options_name_locale'],
    'temp current first index schema' => [$plan(), 'current_indexes.0.schema', 'temp'],
    'temp current first index collation' => [$plan(), 'current_indexes.0.collations.0', 'WP_LOCALE'],
    'temp current first index missing collation' => [$plan(), 'current_indexes.0.missing_collations.0', 'WP_LOCALE'],
    'temp current first index unusable' => [$plan(), 'current_indexes.0.usable', false],
    'temp current binary index usable' => [$plan(), 'current_indexes.1.usable', true],
    'temp blocked current index recorded' => [$plan(), 'blocked_current_indexes.0', 'temp_options_name_locale'],
    'temp usable current index recorded' => [$plan(), 'usable_current_indexes.0', 'temp_options_autoload_binary'],
    'temp missing collation deduped' => [$plan(), 'missing_collations.0', 'WP_LOCALE'],
    'temp missing collation blocks usable fallback' => [$plan(), 'status', 'current-source-collation-blocked-by-shadow'],
    'shadowed table count includes main and two attached' => [$plan(), 'shadowed_tables.count', 3],
    'first shadowed table is main' => [$plan(), 'shadowed_tables.0.schema', 'main'],
    'first shadowed table name' => [$plan(), 'shadowed_tables.0.name', 'wp_options'],
    'first shadowed table root page' => [$plan(), 'shadowed_tables.0.root_page', 2],
    'first shadowed table index count' => [$plan(), 'shadowed_tables.0.indexes.count', 2],
    'second shadowed table is site' => [$plan(), 'shadowed_tables.1.schema', 'site'],
    'third shadowed table is archive' => [$plan(), 'shadowed_tables.2.schema', 'archive'],
    'shadowed indexes include main nocase' => [$plan(), 'shadowed_indexes.0.name', 'main_options_name_nocase'],
    'shadowed main nocase usable' => [$plan(), 'shadowed_indexes.0.usable', true],
    'shadowed main slug missing' => [$plan(), 'shadowed_indexes.1.missing_collations.0', 'WP_SLUG'],
    'shadowed site slug missing without registration' => [$plan(), 'shadowed_indexes.2.missing_collations.0', 'WP_SLUG'],
    'shadowed site rtrim usable builtin' => [$plan(), 'shadowed_indexes.3.usable', true],
    'shadowed archive composite has three collations' => [$plan(), 'shadowed_indexes.4.collations.count', 3],
    'shadowed archive composite first binary' => [$plan(), 'shadowed_indexes.4.collations.0', 'BINARY'],
    'shadowed archive composite second slug' => [$plan(), 'shadowed_indexes.4.collations.1', 'WP_SLUG'],
    'shadowed archive composite third nocase' => [$plan(), 'shadowed_indexes.4.collations.2', 'NOCASE'],
    'blocked fallback records main nocase' => [$plan(), 'blocked_shadowed_usable_indexes.0', 'main.main_options_name_nocase'],
    'blocked fallback records site rtrim' => [$plan(), 'blocked_shadowed_usable_indexes.1', 'site.site_options_autoload_rtrim'],
    'summary current names temp table' => [$summary(), 'current', 'temp.wp_options'],
    'summary first shadow target names main' => [$summary(), 'shadowed_by.0', 'main.wp_options'],
    'summary second shadow target names site' => [$summary(), 'shadowed_by.1', 'site.wp_options'],
    'summary blocked fallback preserves schema index' => [$summary(), 'blocked_fallback_indexes.0', 'main.main_options_name_nocase'],
    'summary missing collation records temp collation' => [$summary(), 'missing_collations.0', 'WP_LOCALE'],
    'summary marks reprepare needed' => [$summary(), 'requires_reprepare', true],
    'register temp locale clears blocked status' => [$plan('wp_options', ['wp_locale']), 'status', 'ok'],
    'register temp locale uppercases registration' => [$plan('wp_options', ['wp_locale']), 'registered_collations.3', 'WP_LOCALE'],
    'register temp locale makes locale index usable' => [$plan('wp_options', ['wp_locale']), 'current_indexes.0.usable', true],
    'register temp locale clears current blockers' => [$plan('wp_options', ['wp_locale']), 'blocked_current_indexes.count', 0],
    'register temp locale clears blocked fallback list' => [$plan('wp_options', ['wp_locale']), 'blocked_shadowed_usable_indexes.count', 0],
    'qualified main bypasses temp shadow' => [$plan('main.wp_options', ['wp_slug']), 'current_schema', 'main'],
    'qualified main is marked qualified' => [$plan('main.wp_options', ['wp_slug']), 'qualified', true],
    'qualified main has no shadow table list' => [$plan('main.wp_options', ['wp_slug']), 'shadowed_tables.count', 0],
    'qualified main uses main indexes' => [$plan('main.wp_options', ['wp_slug']), 'current_indexes.0.name', 'main_options_name_nocase'],
    'qualified main slug index usable' => [$plan('main.wp_options', ['wp_slug']), 'current_indexes.1.usable', true],
    'qualified attached bypasses temp and main' => [$plan('site.wp_options', ['wp_slug']), 'current_schema', 'site'],
    'qualified attached has no shadowed tables' => [$plan('site.wp_options', ['wp_slug']), 'shadowed_tables.count', 0],
    'unqualified attached-only table resolves site' => [$plan('wp_sitemeta'), 'current_schema', 'site'],
    'attached-only table has no shadowed same name' => [$plan('wp_sitemeta'), 'shadowed_tables.count', 0],
    'attached-only table status ok' => [$plan('wp_sitemeta'), 'status', 'ok'],
    'missing table status' => [$plan('missing_options'), 'status', 'missing-table'],
    'missing table preserves target' => [$plan('missing_options'), 'current_table', 'missing_options'],
    'missing table has no current indexes' => [$plan('missing_options'), 'current_indexes.count', 0],
    'dependency marker is present' => [$plan(), 'dependencies.0', 'sqlite-attach-main-temp-collation-shadow-current'],
] as $name => [$case, $path, $expected]) {
    $tests['attach main temp collation shadow current next37 ' . $name] = static function (TestRunner $t) use ($case, $path, $expected, $valueAt): void {
        $t->same($expected, $valueAt($case, $path));
    };
}

$tests['attach main temp collation shadow current next37 detach temp shadow exposes main'] = static function (TestRunner $t) use ($makeCatalog): void {
    $catalog = $makeCatalog();
    $catalog = new SQLiteAttachedSchemaCatalog($catalog->schemaRecords('main'), []);
    $plan = SQLiteAttachMainTempCollationShadowPlan::plan($catalog, 'wp_options', ['wp_slug']);

    $t->same('main', $plan['current_schema']);
    $t->same('ok', $plan['status']);
    $t->same(['main_options_name_nocase', 'main_options_value_slug'], $plan['usable_current_indexes']);
};

$tests['attach main temp collation shadow current next37 batch plan keeps independent registrations'] = static function (TestRunner $t) use ($makeCatalog): void {
    $plans = SQLiteAttachMainTempCollationShadowPlan::batchPlan($makeCatalog(), [
        ['table' => 'wp_options'],
        ['table' => 'wp_options', 'collations' => ['wp_locale']],
        ['table' => 'main.wp_options', 'collations' => ['wp_slug']],
        ['table' => 'site.wp_options', 'collations' => ['wp_slug']],
    ]);

    $t->same([
        'current-source-collation-blocked-by-shadow',
        'ok',
        'ok',
        'ok',
    ], array_column($plans, 'status'));
};

$tests['attach main temp collation shadow current next37 batch rejects empty table'] = static function (TestRunner $t) use ($makeCatalog): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteAttachMainTempCollationShadowPlan::batchPlan($makeCatalog(), [['table' => '']]));
};

$tests['attach main temp collation shadow current next37 rejects non string collation'] = static function (TestRunner $t) use ($makeCatalog): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteAttachMainTempCollationShadowPlan::plan($makeCatalog(), 'wp_options', [false]));
};

return $tests;
