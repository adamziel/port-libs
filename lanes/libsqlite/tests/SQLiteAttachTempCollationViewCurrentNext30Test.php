<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteAttachedSchemaCatalog;
use PortLibs\LibSqlite\SQLiteAttachTempCollationViewResolution;
use PortLibs\LibSqlite\SQLiteSchemaRecord;

$record = static fn (string $type, string $name, string $table, ?int $root, string $sql, int $rowid): SQLiteSchemaRecord => new SQLiteSchemaRecord(
    $type,
    $name,
    $table,
    $root,
    $sql,
    $rowid,
);

$makeCatalog = static function () use ($record): SQLiteAttachedSchemaCatalog {
    $catalog = new SQLiteAttachedSchemaCatalog(
        [
            $record('table', 'wp_options', 'wp_options', 2, 'CREATE TABLE wp_options(option_id integer primary key, option_name text, option_value text, autoload text)', 1),
            $record('table', 'wp_optionmeta', 'wp_optionmeta', 3, 'CREATE TABLE wp_optionmeta(option_id integer, meta_key text, meta_value text)', 2),
            $record('view', 'active_options', 'active_options', 0, "CREATE VIEW active_options(option_id, option_name, meta_value) AS SELECT o.option_id, o.option_name COLLATE NOCASE, m.meta_value FROM wp_options AS o JOIN wp_optionmeta AS m ON m.option_id = o.option_id WHERE o.autoload = 'yes' ORDER BY o.option_name COLLATE RTRIM", 3),
            $record('view', 'bad_archive_options', 'bad_archive_options', 0, "CREATE VIEW bad_archive_options AS SELECT option_name COLLATE NOCASE FROM archive.wp_options", 4),
        ],
        [
            $record('table', 'wp_options', 'wp_options', 10, 'CREATE TEMP TABLE wp_options(option_id integer, option_name text, option_value text)', 5),
            $record('view', 'active_options', 'active_options', null, "CREATE TEMP VIEW active_options(option_id, option_name, site_value) AS SELECT t.option_id, t.option_name COLLATE WPSLUG, s.option_value FROM temp.wp_options AS t JOIN site.wp_options AS s ON s.option_name COLLATE NOCASE = t.option_name COLLATE NOCASE", 6),
            $record('view', 'scratch_archive', 'scratch_archive', null, "CREATE TEMP VIEW scratch_archive AS SELECT option_name COLLATE RTRIM FROM archive.wp_options WHERE archived_at > '2026-01-01'", 7),
            $record('view', 'bad_custom_collation', 'bad_custom_collation', null, "CREATE TEMP VIEW bad_custom_collation AS SELECT option_name COLLATE WPMISSING FROM wp_options", 8),
        ],
    );

    $catalog->attach('site', '/srv/www/site.sqlite', [
        $record('table', 'wp_options', 'wp_options', 20, 'CREATE TABLE site.wp_options(blog_id integer, option_name text, option_value text)', 9),
        $record('view', 'site_active', 'site_active', 0, "CREATE VIEW site_active(blog_id, option_name) AS SELECT blog_id, option_name COLLATE NOCASE FROM wp_options WHERE option_value <> ''", 10),
        $record('view', 'bad_temp_read', 'bad_temp_read', 0, "CREATE VIEW bad_temp_read AS SELECT option_name COLLATE NOCASE FROM temp.wp_options", 11),
    ]);
    $catalog->attach('archive', '/srv/www/archive.sqlite', [
        $record('table', 'wp_options', 'wp_options', 30, 'CREATE TABLE archive.wp_options(option_name text, archived_at text)', 12),
        $record('view', 'archive_options', 'archive_options', 0, "CREATE VIEW archive_options AS SELECT option_name COLLATE RTRIM AS option_name FROM wp_options", 13),
    ]);

    return $catalog;
};

$collations = ['BINARY', 'NOCASE', 'RTRIM', 'WPSLUG'];
$resolve = static fn (string $name): array => SQLiteAttachTempCollationViewResolution::resolve($makeCatalog(), $name, $collations);
$summary = static fn (): array => SQLiteAttachTempCollationViewResolution::summary($makeCatalog(), $collations);
$valueAt = static function (mixed $value, string $path): mixed {
    foreach (explode('.', $path) as $part) {
        if ($part === 'count') {
            $value = count($value);
            continue;
        }
        if (is_array($value)) {
            $value = is_numeric($part) ? $value[(int) $part] : $value[$part];
        }
    }

    return $value;
};

$tests = [];

foreach ([
    'temp view wins unqualified lookup' => ['active_options', 'viewSchema', 'temp'],
    'temp view is temporary' => ['active_options', 'temporary', true],
    'temp view column count' => ['active_options', 'columns.count', 3],
    'temp view third column' => ['active_options', 'columns.2', 'site_value'],
    'temp view first explicit source schema' => ['active_options', 'sourceReferences.0.schema', 'temp'],
    'temp view first source name' => ['active_options', 'sourceReferences.0.name', 'wp_options'],
    'temp view second explicit source schema' => ['active_options', 'sourceReferences.1.schema', 'site'],
    'temp view second source name' => ['active_options', 'sourceReferences.1.name', 'wp_options'],
    'temp view first resolved source temp' => ['active_options', 'resolvedSources.0.schema', 'temp'],
    'temp view second resolved source site' => ['active_options', 'resolvedSources.1.schema', 'site'],
    'temp view second source is table' => ['active_options', 'resolvedSources.1.type', 'table'],
    'temp view source temp flag' => ['active_options', 'resolvedSources.0.temporary', true],
    'temp view collations count' => ['active_options', 'collations.count', 2],
    'temp view custom collation captured' => ['active_options', 'collations.0', 'WPSLUG'],
    'temp view nocase collation captured' => ['active_options', 'collations.1', 'NOCASE'],
    'temp view has no missing collation' => ['active_options', 'missingCollations.count', 0],
    'temp view allows cross-schema sources' => ['active_options', 'crossSchemaReferences.count', 0],
    'temp view status resolved' => ['active_options', 'status', 'resolved'],
    'main view explicit lookup stays main' => ['main.active_options', 'viewSchema', 'main'],
    'main view is persistent' => ['main.active_options', 'temporary', false],
    'main view columns second' => ['main.active_options', 'columns.1', 'option_name'],
    'main view unqualified source stays main' => ['main.active_options', 'resolvedSources.0.schema', 'main'],
    'main view joined source stays main' => ['main.active_options', 'resolvedSources.1.schema', 'main'],
    'main view captures nocase' => ['main.active_options', 'collations.0', 'NOCASE'],
    'main view captures rtrim' => ['main.active_options', 'collations.1', 'RTRIM'],
    'main view resolved' => ['main.active_options', 'status', 'resolved'],
    'site view stays site' => ['site.site_active', 'viewSchema', 'site'],
    'site view source stays site' => ['site.site_active', 'resolvedSources.0.schema', 'site'],
    'site view attached not temp' => ['site.site_active', 'temporary', false],
    'site view resolved' => ['site.site_active', 'status', 'resolved'],
    'archive view resolves archive source' => ['archive.archive_options', 'resolvedSources.0.schema', 'archive'],
    'archive view inferred column alias' => ['archive.archive_options', 'columns.0', 'option_name'],
    'archive view resolved' => ['archive.archive_options', 'status', 'resolved'],
    'temp scratch can read archive' => ['scratch_archive', 'resolvedSources.0.schema', 'archive'],
    'temp scratch rtrim collation' => ['scratch_archive', 'collations.0', 'RTRIM'],
    'temp scratch resolved' => ['scratch_archive', 'status', 'resolved'],
    'main cross-schema view source schema' => ['main.bad_archive_options', 'resolvedSources.0.schema', 'archive'],
    'main cross-schema view records cross count' => ['main.bad_archive_options', 'crossSchemaReferences.count', 1],
    'main cross-schema view unresolved' => ['main.bad_archive_options', 'status', 'unresolved'],
    'attached cross-schema view source schema' => ['site.bad_temp_read', 'resolvedSources.0.schema', 'temp'],
    'attached cross-schema view records cross count' => ['site.bad_temp_read', 'crossSchemaReferences.count', 1],
    'attached cross-schema view unresolved' => ['site.bad_temp_read', 'status', 'unresolved'],
    'missing custom collation name' => ['bad_custom_collation', 'missingCollations.0', 'WPMISSING'],
    'missing custom collation unresolved' => ['bad_custom_collation', 'status', 'unresolved'],
] as $name => [$viewName, $path, $expected]) {
    $tests['attach temp collation view current next30 ' . $name] = static function (TestRunner $t) use ($resolve, $valueAt, $viewName, $path, $expected): void {
        $t->same($expected, $valueAt($resolve($viewName), $path));
    };
}

foreach ([
    'summary resolved count' => ['resolved', 5],
    'summary unresolved count' => ['unresolved', 3],
    'summary temp view count' => ['tempViews', 3],
    'summary attached view count' => ['attachedViews', 3],
    'summary nocase use count' => ['collations.NOCASE', 5],
    'summary rtrim use count' => ['collations.RTRIM', 3],
    'summary wpslug use count' => ['collations.WPSLUG', 1],
] as $name => [$path, $expected]) {
    $tests['attach temp collation view current next30 ' . $name] = static function (TestRunner $t) use ($summary, $valueAt, $path, $expected): void {
        $t->same($expected, $valueAt($summary(), $path));
    };
}

$tests['attach temp collation view current next30 summary cross main key'] = static function (TestRunner $t) use ($summary): void {
    $t->same('archive.wp_options', $summary()['crossSchemaViews']['main.bad_archive_options'][0]);
};

$tests['attach temp collation view current next30 summary cross attached key'] = static function (TestRunner $t) use ($summary): void {
    $t->same('temp.wp_options', $summary()['crossSchemaViews']['site.bad_temp_read'][0]);
};

$tests['attach temp collation view current next30 summary missing collation key'] = static function (TestRunner $t) use ($summary): void {
    $t->same('WPMISSING', $summary()['missingCollationViews']['temp.bad_custom_collation'][0]);
};

$tests['attach temp collation view current next30 unqualified missing view throws'] = static function (TestRunner $t) use ($makeCatalog, $collations): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteAttachTempCollationViewResolution::resolve($makeCatalog(), 'missing_view', $collations));
};

$tests['attach temp collation view current next30 missing source throws'] = static function (TestRunner $t) use ($record, $collations): void {
    $catalog = new SQLiteAttachedSchemaCatalog([
        $record('view', 'bad_source', 'bad_source', 0, 'CREATE VIEW bad_source AS SELECT option_name COLLATE NOCASE FROM missing_options', 30),
    ]);
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteAttachTempCollationViewResolution::resolve($catalog, 'bad_source', $collations));
};

$tests['attach temp collation view current next30 malformed view throws'] = static function (TestRunner $t) use ($record, $collations): void {
    $catalog = new SQLiteAttachedSchemaCatalog([
        $record('view', 'bad_sql', 'bad_sql', 0, 'CREATE VIEW bad_sql(option_name)', 31),
    ]);
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteAttachTempCollationViewResolution::resolve($catalog, 'bad_sql', $collations));
};

return $tests;
