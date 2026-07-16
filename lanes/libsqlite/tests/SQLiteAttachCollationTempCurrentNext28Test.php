<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteAttachCollationTempCurrentPlan;
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
    $catalog = new SQLiteAttachedSchemaCatalog(
        [
            $record('table', 'wp_options', 'wp_options', 2, 'CREATE TABLE wp_options(option_name TEXT, option_value TEXT)'),
            $record('index', 'main_option_name_nocase', 'wp_options', 3, 'CREATE INDEX main_option_name_nocase ON wp_options(option_name COLLATE NOCASE)'),
            $record('index', 'main_option_value_slug', 'wp_options', 4, 'CREATE INDEX main_option_value_slug ON wp_options(option_value COLLATE wp_slug)'),
            $record('table', 'wp_posts', 'wp_posts', 5, 'CREATE TABLE wp_posts(post_title TEXT)'),
            $record('index', 'main_post_title_binary', 'wp_posts', 6, 'CREATE INDEX main_post_title_binary ON wp_posts(post_title)'),
        ],
        [
            $record('table', 'wp_options', 'wp_options', 7, 'CREATE TEMP TABLE wp_options(option_name TEXT, option_value TEXT)'),
            $record('index', 'temp_option_name_binary', 'wp_options', 8, 'CREATE INDEX temp_option_name_binary ON wp_options(option_name)'),
            $record('index', 'temp_option_value_locale', 'wp_options', 9, 'CREATE INDEX temp_option_value_locale ON wp_options(option_value COLLATE wp_locale)'),
        ],
    );
    $catalog->attach('site', '/srv/site.sqlite', [
        $record('table', 'wp_options', 'wp_options', 20, 'CREATE TABLE wp_options(option_name TEXT, option_value TEXT, autoload TEXT)'),
        $record('index', 'site_option_name_reverse', 'wp_options', 21, 'CREATE INDEX site_option_name_reverse ON wp_options(option_name COLLATE wp_reverse)'),
        $record('index', 'site_option_autoload_rtrim', 'wp_options', 22, 'CREATE INDEX site_option_autoload_rtrim ON wp_options(autoload COLLATE RTRIM)'),
        $record('table', 'wp_sitemeta', 'wp_sitemeta', 23, 'CREATE TABLE wp_sitemeta(meta_key TEXT, meta_value TEXT)'),
        $record('index', 'site_meta_key_slug', 'wp_sitemeta', 24, 'CREATE INDEX site_meta_key_slug ON wp_sitemeta(meta_key COLLATE "wp_slug")'),
    ]);
    $catalog->attach('archive', '/srv/archive.sqlite', [
        $record('table', 'wp_options', 'wp_options', 30, 'CREATE TABLE wp_options(option_name TEXT, option_value TEXT, blog_id INTEGER)'),
        $record('index', 'archive_option_composite', 'wp_options', 31, 'CREATE INDEX archive_option_composite ON wp_options(blog_id, option_name COLLATE [wp_slug], option_value COLLATE nocase)'),
        $record('index', 'archive_option_expr', 'wp_options', 32, 'CREATE INDEX archive_option_expr ON wp_options(lower(option_name) COLLATE wp_slug, substr(option_value,1,3) COLLATE rtrim)'),
    ]);

    return $catalog;
};

$plan = static fn (string $table = 'wp_options', array $collations = []): array => SQLiteAttachCollationTempCurrentPlan::plan($makeCatalog(), $table, $collations);

$cases = [
    'unqualified target resolves temp shadow' => [$plan(), 'current_schema', 'temp'],
    'unqualified target keeps temp table name' => [$plan(), 'current_table', 'wp_options'],
    'search order starts temp main' => [$plan(), 'search_order.0', 'temp'],
    'search order includes main second' => [$plan(), 'search_order.1', 'main'],
    'search order includes first attach' => [$plan(), 'search_order.2', 'site'],
    'search order includes second attach' => [$plan(), 'search_order.3', 'archive'],
    'builtins include binary' => [$plan(), 'registered_collations.0', 'BINARY'],
    'builtins include nocase' => [$plan(), 'registered_collations.1', 'NOCASE'],
    'builtins include rtrim' => [$plan(), 'registered_collations.2', 'RTRIM'],
    'temp plan sees two current indexes' => [$plan(), 'indexes.count', 2],
    'temp binary index first' => [$plan(), 'indexes.0.name', 'temp_option_name_binary'],
    'temp binary collation extracted' => [$plan(), 'indexes.0.collations.0', 'BINARY'],
    'temp binary index usable' => [$plan(), 'indexes.0.usable', true],
    'temp locale index second' => [$plan(), 'indexes.1.name', 'temp_option_value_locale'],
    'temp locale collation extracted' => [$plan(), 'indexes.1.collations.0', 'WP_LOCALE'],
    'temp locale missing by default' => [$plan(), 'indexes.1.available', false],
    'temp locale blocked by default' => [$plan(), 'blocked_indexes.0', 'temp_option_value_locale'],
    'temp locale missing reported once' => [$plan(), 'missing_collations.0', 'WP_LOCALE'],
    'temp status reports missing collation' => [$plan(), 'status', 'missing-collation'],
    'registered temp locale makes status ok' => [$plan('wp_options', ['wp_locale']), 'status', 'ok'],
    'registered temp locale makes both usable' => [$plan('wp_options', ['wp_locale']), 'usable_indexes.count', 2],
    'registered temp locale appends uppercase' => [$plan('wp_options', ['wp_locale']), 'registered_collations.3', 'WP_LOCALE'],
    'main qualified target ignores temp shadow' => [$plan('main.wp_options', ['wp_slug']), 'current_schema', 'main'],
    'main qualified sees main indexes only' => [$plan('main.wp_options', ['wp_slug']), 'indexes.count', 2],
    'main nocase index is usable' => [$plan('main.wp_options', ['wp_slug']), 'indexes.0.usable', true],
    'main custom slug index is usable when registered' => [$plan('main.wp_options', ['wp_slug']), 'indexes.1.usable', true],
    'main custom slug blocks when missing' => [$plan('main.wp_options'), 'blocked_indexes.0', 'main_option_value_slug'],
    'main posts binary target ok' => [$plan('wp_posts'), 'status', 'ok'],
    'main posts target resolved after temp miss' => [$plan('wp_posts'), 'current_schema', 'main'],
    'main posts index collation defaults binary' => [$plan('wp_posts'), 'indexes.0.collations.0', 'BINARY'],
    'attached site target resolved by qualifier' => [$plan('site.wp_options', ['wp_reverse']), 'current_schema', 'site'],
    'attached site sees two indexes' => [$plan('site.wp_options', ['wp_reverse']), 'indexes.count', 2],
    'attached site custom reverse usable' => [$plan('site.wp_options', ['wp_reverse']), 'indexes.0.usable', true],
    'attached site rtrim builtin usable' => [$plan('site.wp_options', ['wp_reverse']), 'indexes.1.usable', true],
    'attached site blocks reverse when missing' => [$plan('site.wp_options'), 'missing_collations.0', 'WP_REVERSE'],
    'attached site sitemeta resolves after temp main miss' => [$plan('wp_sitemeta', ['wp_slug']), 'current_schema', 'site'],
    'attached site quoted slug unquotes' => [$plan('wp_sitemeta', ['wp_slug']), 'indexes.0.collations.0', 'WP_SLUG'],
    'attached site quoted slug usable' => [$plan('wp_sitemeta', ['wp_slug']), 'indexes.0.usable', true],
    'archive target resolved by qualifier' => [$plan('archive.wp_options', ['wp_slug']), 'current_schema', 'archive'],
    'archive composite has three terms' => [$plan('archive.wp_options', ['wp_slug']), 'indexes.0.collations.count', 3],
    'archive composite first term defaults binary' => [$plan('archive.wp_options', ['wp_slug']), 'indexes.0.collations.0', 'BINARY'],
    'archive composite bracket collation unquotes' => [$plan('archive.wp_options', ['wp_slug']), 'indexes.0.collations.1', 'WP_SLUG'],
    'archive composite nocase lowercases to builtin' => [$plan('archive.wp_options', ['wp_slug']), 'indexes.0.collations.2', 'NOCASE'],
    'archive expression index splits nested comma' => [$plan('archive.wp_options', ['wp_slug']), 'indexes.1.collations.count', 2],
    'archive expression index first custom collation' => [$plan('archive.wp_options', ['wp_slug']), 'indexes.1.collations.0', 'WP_SLUG'],
    'archive expression index second builtin rtrim' => [$plan('archive.wp_options', ['wp_slug']), 'indexes.1.collations.1', 'RTRIM'],
    'archive indexes usable with slug' => [$plan('archive.wp_options', ['wp_slug']), 'usable_indexes.count', 2],
    'archive blocks both indexes without slug' => [$plan('archive.wp_options'), 'blocked_indexes.count', 2],
    'archive missing collation is deduped' => [$plan('archive.wp_options'), 'missing_collations.count', 1],
    'missing table returns missing status' => [$plan('missing_options'), 'status', 'missing-table'],
    'missing table preserves search order' => [$plan('missing_options'), 'search_order.3', 'archive'],
    'missing table has no indexes' => [$plan('missing_options'), 'indexes.count', 0],
    'dependency marker is present' => [$plan(), 'dependencies.0', 'sqlite-attach-collation-temp-current'],
];

$valueAt = static function (array $value, string $path): mixed {
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
foreach ($cases as $name => [$casePlan, $path, $expected]) {
    $tests['attach collation temp current next28 ' . $name] = static function (TestRunner $t) use ($casePlan, $path, $expected, $valueAt): void {
        $t->same($expected, $valueAt($casePlan, $path));
    };
}

$tests['attach collation temp current next28 detach falls through to main custom index'] = static function (TestRunner $t) use ($makeCatalog): void {
    $catalog = $makeCatalog();
    $catalog->detach('site');
    $catalog->detach('archive');
    $plan = SQLiteAttachCollationTempCurrentPlan::plan($catalog, 'main.wp_options', ['wp_slug']);

    $t->same('main', $plan['current_schema']);
    $t->same(['main_option_name_nocase', 'main_option_value_slug'], $plan['usable_indexes']);
};

$tests['attach collation temp current next28 detach temp shadow keeps unqualified temp'] = static function (TestRunner $t) use ($makeCatalog): void {
    $catalog = $makeCatalog();
    $catalog->detach('site');
    $catalog->detach('archive');
    $plan = SQLiteAttachCollationTempCurrentPlan::plan($catalog, 'wp_options', ['wp_locale']);

    $t->same('temp', $plan['current_schema']);
    $t->same(['temp_option_name_binary', 'temp_option_value_locale'], $plan['usable_indexes']);
};

$tests['attach collation temp current next28 batch plan uses per lookup collations'] = static function (TestRunner $t) use ($makeCatalog): void {
    $plans = SQLiteAttachCollationTempCurrentPlan::batchPlan($makeCatalog(), [
        ['table' => 'wp_options', 'collations' => ['wp_locale']],
        ['table' => 'site.wp_options', 'collations' => ['wp_reverse']],
        ['table' => 'archive.wp_options', 'collations' => ['wp_slug']],
    ]);

    $t->same(['temp', 'site', 'archive'], array_column($plans, 'current_schema'));
    $t->same(['ok', 'ok', 'ok'], array_column($plans, 'status'));
};

$tests['attach collation temp current next28 batch plan rejects empty table'] = static function (TestRunner $t) use ($makeCatalog): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteAttachCollationTempCurrentPlan::batchPlan($makeCatalog(), [['table' => '']]));
};

$tests['attach collation temp current next28 rejects non string registered collation'] = static function (TestRunner $t) use ($makeCatalog): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteAttachCollationTempCurrentPlan::plan($makeCatalog(), 'wp_options', [42]));
};

return $tests;
