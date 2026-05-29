<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteAttachedSchemaCatalog;
use PortLibs\LibSqlite\SQLiteAttachTempCollationCachePlan;
use PortLibs\LibSqlite\SQLiteSchemaRecord;

$record = static fn (string $type, string $name, string $table, ?int $root, ?string $sql, int $rowid): SQLiteSchemaRecord => new SQLiteSchemaRecord(
    $type,
    $name,
    $table,
    $root,
    $sql,
    $rowid,
);

$catalog = static function (array $tempRecords = []) use ($record): SQLiteAttachedSchemaCatalog {
    $catalog = new SQLiteAttachedSchemaCatalog(
        [
            $record('table', 'wp_options', 'wp_options', 2, 'CREATE TABLE wp_options(option_id INTEGER PRIMARY KEY, option_name TEXT COLLATE NOCASE, option_value TEXT COLLATE RTRIM, autoload TEXT COLLATE BINARY)', 1),
            $record('table', 'wp_sitemeta', 'wp_sitemeta', 3, 'CREATE TABLE wp_sitemeta(meta_id INTEGER PRIMARY KEY, meta_key TEXT COLLATE RTRIM, meta_value TEXT)', 2),
        ],
        $tempRecords,
    );
    $catalog->attach('site', '/srv/site.sqlite', [
        $record('table', 'wp_options', 'wp_options', 20, 'CREATE TABLE site.wp_options(blog_id INTEGER, option_name TEXT COLLATE BINARY, option_value TEXT COLLATE NOCASE, autoload TEXT COLLATE RTRIM)', 3),
        $record('table', 'wp_blogmeta', 'wp_blogmeta', 21, 'CREATE TABLE site.wp_blogmeta(meta_key TEXT COLLATE NOCASE, meta_value TEXT COLLATE BINARY)', 4),
    ]);
    $catalog->attach('archive', '/srv/archive.sqlite', [
        $record('table', 'wp_options_archive', 'wp_options_archive', 30, 'CREATE TABLE archive.wp_options_archive(option_name TEXT COLLATE RTRIM, option_value TEXT COLLATE BINARY)', 5),
    ]);

    return $catalog;
};

$tempShadow = static fn (): array => [
    $record('table', 'wp_options', 'wp_options', 10, 'CREATE TEMP TABLE wp_options(option_id INTEGER PRIMARY KEY, option_name TEXT COLLATE RTRIM, option_value TEXT COLLATE NOCASE, autoload TEXT COLLATE BINARY)', 6),
];

$tempSameCollation = static fn (): array => [
    $record('table', 'wp_options', 'wp_options', 11, 'CREATE TEMP TABLE wp_options(option_id INTEGER PRIMARY KEY, option_name TEXT COLLATE NOCASE, option_value TEXT COLLATE RTRIM, autoload TEXT COLLATE BINARY)', 7),
];

$tempDifferentRoot = static fn (): array => [
    $record('table', 'wp_options', 'wp_options', 12, 'CREATE TEMP TABLE wp_options(option_id INTEGER PRIMARY KEY, option_name TEXT COLLATE NOCASE, option_value TEXT COLLATE RTRIM, autoload TEXT COLLATE BINARY)', 8),
];

$preparedMain = static fn (): array => SQLiteAttachTempCollationCachePlan::prepare($catalog(), 'wp_options', ['option_name', 'option_value', 'autoload']);
$preparedAttached = static fn (): array => SQLiteAttachTempCollationCachePlan::prepare($catalog(), 'site.wp_options', ['option_name', 'option_value', 'autoload']);
$currentMain = static fn (): array => SQLiteAttachTempCollationCachePlan::currentNext($catalog(), $preparedMain());
$shadowMain = static fn (): array => SQLiteAttachTempCollationCachePlan::currentNext($catalog($tempShadow()), $preparedMain());
$sameTempMain = static fn (): array => SQLiteAttachTempCollationCachePlan::currentNext($catalog($tempSameCollation()), $preparedMain());
$differentRootMain = static fn (): array => SQLiteAttachTempCollationCachePlan::currentNext($catalog($tempDifferentRoot()), $preparedMain());

return [
    'attach temp collation cache current next40 prepares main status' => static fn (TestRunner $t) => $t->same('prepared', $preparedMain()['status']),
    'attach temp collation cache current next40 prepares main generation after two attaches' => static fn (TestRunner $t) => $t->same(2, $preparedMain()['generation']),
    'attach temp collation cache current next40 prepares main schema' => static fn (TestRunner $t) => $t->same('main', $preparedMain()['schema']),
    'attach temp collation cache current next40 prepares main table' => static fn (TestRunner $t) => $t->same('wp_options', $preparedMain()['table']),
    'attach temp collation cache current next40 prepares main record' => static fn (TestRunner $t) => $t->same('wp_options', $preparedMain()['record']),
    'attach temp collation cache current next40 prepares main root page' => static fn (TestRunner $t) => $t->same(2, $preparedMain()['root_page']),
    'attach temp collation cache current next40 normalizes column order' => static fn (TestRunner $t) => $t->same(['autoload', 'option_name', 'option_value'], $preparedMain()['columns']),
    'attach temp collation cache current next40 main option name nocase' => static fn (TestRunner $t) => $t->same('NOCASE', $preparedMain()['collations']['option_name']),
    'attach temp collation cache current next40 main option value rtrim' => static fn (TestRunner $t) => $t->same('RTRIM', $preparedMain()['collations']['option_value']),
    'attach temp collation cache current next40 main autoload binary' => static fn (TestRunner $t) => $t->same('BINARY', $preparedMain()['collations']['autoload']),
    'attach temp collation cache current next40 search order includes attached schemas' => static fn (TestRunner $t) => $t->same(['temp', 'main', 'site', 'archive'], $preparedMain()['search_order']),
    'attach temp collation cache current next40 cache key is sha256 length' => static fn (TestRunner $t) => $t->same(64, strlen($preparedMain()['cache_key'])),

    'attach temp collation cache current next40 current status' => static fn (TestRunner $t) => $t->same('current', $currentMain()['status']),
    'attach temp collation cache current next40 current flag true' => static fn (TestRunner $t) => $t->same(true, $currentMain()['current']),
    'attach temp collation cache current next40 current does not reprepare' => static fn (TestRunner $t) => $t->same(false, $currentMain()['reprepare_required']),
    'attach temp collation cache current next40 current reason' => static fn (TestRunner $t) => $t->same('cache entry is current', $currentMain()['reason']),
    'attach temp collation cache current next40 current generation unchanged' => static fn (TestRunner $t) => $t->same(false, $currentMain()['generation_changed']),
    'attach temp collation cache current next40 current before generation' => static fn (TestRunner $t) => $t->same(2, $currentMain()['before_generation']),
    'attach temp collation cache current next40 current after generation' => static fn (TestRunner $t) => $t->same(2, $currentMain()['after_generation']),
    'attach temp collation cache current next40 current schema remains main' => static fn (TestRunner $t) => $t->same('main', $currentMain()['after_schema']),
    'attach temp collation cache current next40 current root remains two' => static fn (TestRunner $t) => $t->same(2, $currentMain()['after_root_page']),
    'attach temp collation cache current next40 current changed columns empty' => static fn (TestRunner $t) => $t->same([], $currentMain()['changed_columns']),
    'attach temp collation cache current next40 current added columns empty' => static fn (TestRunner $t) => $t->same([], $currentMain()['added_columns']),
    'attach temp collation cache current next40 current removed columns empty' => static fn (TestRunner $t) => $t->same([], $currentMain()['removed_columns']),
    'attach temp collation cache current next40 current cache key stable' => static fn (TestRunner $t) => $t->same($currentMain()['before_cache_key'], $currentMain()['after_cache_key']),

    'attach temp collation cache current next40 temp shadow status stale' => static fn (TestRunner $t) => $t->same('stale', $shadowMain()['status']),
    'attach temp collation cache current next40 temp shadow reprepare required' => static fn (TestRunner $t) => $t->same(true, $shadowMain()['reprepare_required']),
    'attach temp collation cache current next40 temp shadow reason schema' => static fn (TestRunner $t) => $t->same('resolved schema changed', $shadowMain()['reason']),
    'attach temp collation cache current next40 temp shadow before schema main' => static fn (TestRunner $t) => $t->same('main', $shadowMain()['before_schema']),
    'attach temp collation cache current next40 temp shadow after schema temp' => static fn (TestRunner $t) => $t->same('temp', $shadowMain()['after_schema']),
    'attach temp collation cache current next40 temp shadow before root two' => static fn (TestRunner $t) => $t->same(2, $shadowMain()['before_root_page']),
    'attach temp collation cache current next40 temp shadow after root ten' => static fn (TestRunner $t) => $t->same(10, $shadowMain()['after_root_page']),
    'attach temp collation cache current next40 temp shadow changed option name' => static fn (TestRunner $t) => $t->same(['option_name', 'option_value'], $shadowMain()['changed_columns']),
    'attach temp collation cache current next40 temp shadow option name rtrim' => static fn (TestRunner $t) => $t->same('RTRIM', $shadowMain()['after_collations']['option_name']),
    'attach temp collation cache current next40 temp shadow option value nocase' => static fn (TestRunner $t) => $t->same('NOCASE', $shadowMain()['after_collations']['option_value']),
    'attach temp collation cache current next40 temp shadow autoload remains binary' => static fn (TestRunner $t) => $t->same('BINARY', $shadowMain()['after_collations']['autoload']),
    'attach temp collation cache current next40 temp shadow cache key changes' => static fn (TestRunner $t) => $t->same(false, $shadowMain()['before_cache_key'] === $shadowMain()['after_cache_key']),

    'attach temp collation cache current next40 temp same collation still stale' => static fn (TestRunner $t) => $t->same('stale', $sameTempMain()['status']),
    'attach temp collation cache current next40 temp same collation reason schema' => static fn (TestRunner $t) => $t->same('resolved schema changed', $sameTempMain()['reason']),
    'attach temp collation cache current next40 temp same collation changed columns empty' => static fn (TestRunner $t) => $t->same([], $sameTempMain()['changed_columns']),
    'attach temp collation cache current next40 temp same collation after schema temp' => static fn (TestRunner $t) => $t->same('temp', $sameTempMain()['after_schema']),
    'attach temp collation cache current next40 temp same collation after root eleven' => static fn (TestRunner $t) => $t->same(11, $sameTempMain()['after_root_page']),
    'attach temp collation cache current next40 temp same collation after nocase' => static fn (TestRunner $t) => $t->same('NOCASE', $sameTempMain()['after_collations']['option_name']),
    'attach temp collation cache current next40 temp same collation after rtrim' => static fn (TestRunner $t) => $t->same('RTRIM', $sameTempMain()['after_collations']['option_value']),

    'attach temp collation cache current next40 root change status stale' => static fn (TestRunner $t) => $t->same('stale', $differentRootMain()['status']),
    'attach temp collation cache current next40 root change after root twelve' => static fn (TestRunner $t) => $t->same(12, $differentRootMain()['after_root_page']),
    'attach temp collation cache current next40 root change no collation changes' => static fn (TestRunner $t) => $t->same([], $differentRootMain()['changed_columns']),
    'attach temp collation cache current next40 root change reprepare required' => static fn (TestRunner $t) => $t->same(true, $differentRootMain()['reprepare_required']),

    'attach temp collation cache current next40 attached prepares schema site' => static fn (TestRunner $t) => $t->same('site', $preparedAttached()['schema']),
    'attach temp collation cache current next40 attached option name binary' => static fn (TestRunner $t) => $t->same('BINARY', $preparedAttached()['collations']['option_name']),
    'attach temp collation cache current next40 attached option value nocase' => static fn (TestRunner $t) => $t->same('NOCASE', $preparedAttached()['collations']['option_value']),
    'attach temp collation cache current next40 attached autoload rtrim' => static fn (TestRunner $t) => $t->same('RTRIM', $preparedAttached()['collations']['autoload']),
    'attach temp collation cache current next40 attached root page twenty' => static fn (TestRunner $t) => $t->same(20, $preparedAttached()['root_page']),
    'attach temp collation cache current next40 qualified attached ignores temp shadow' => static fn (TestRunner $t) => $t->same('site', SQLiteAttachTempCollationCachePlan::currentNext($catalog($tempShadow()), $preparedAttached())['after_schema']),
    'attach temp collation cache current next40 qualified attached stays current with temp shadow' => static fn (TestRunner $t) => $t->same(true, SQLiteAttachTempCollationCachePlan::currentNext($catalog($tempShadow()), $preparedAttached())['current']),

    'attach temp collation cache current next40 attach bumps generation stale' => static function (TestRunner $t) use ($catalog): void {
        $c = $catalog();
        $prepared = SQLiteAttachTempCollationCachePlan::prepare($c, 'wp_options', ['option_name']);
        $c->attach('tenant', '/srv/tenant.sqlite', []);
        $plan = SQLiteAttachTempCollationCachePlan::currentNext($c, $prepared);
        $t->same('schema generation changed', $plan['reason']);
    },
    'attach temp collation cache current next40 attach generation changed true' => static function (TestRunner $t) use ($catalog): void {
        $c = $catalog();
        $prepared = SQLiteAttachTempCollationCachePlan::prepare($c, 'wp_options', ['option_name']);
        $c->attach('tenant', '/srv/tenant.sqlite', []);
        $plan = SQLiteAttachTempCollationCachePlan::currentNext($c, $prepared);
        $t->same(true, $plan['generation_changed']);
    },
    'attach temp collation cache current next40 attach after generation three' => static function (TestRunner $t) use ($catalog): void {
        $c = $catalog();
        $prepared = SQLiteAttachTempCollationCachePlan::prepare($c, 'wp_options', ['option_name']);
        $c->attach('tenant', '/srv/tenant.sqlite', []);
        $plan = SQLiteAttachTempCollationCachePlan::currentNext($c, $prepared);
        $t->same(3, $plan['after_generation']);
    },
    'attach temp collation cache current next40 detach generation stale' => static function (TestRunner $t) use ($catalog): void {
        $c = $catalog();
        $prepared = SQLiteAttachTempCollationCachePlan::prepare($c, 'wp_options', ['option_name']);
        $c->detach('archive');
        $plan = SQLiteAttachTempCollationCachePlan::currentNext($c, $prepared);
        $t->same('schema generation changed', $plan['reason']);
    },
    'attach temp collation cache current next40 detach after search order' => static function (TestRunner $t) use ($catalog): void {
        $c = $catalog();
        $prepared = SQLiteAttachTempCollationCachePlan::prepare($c, 'wp_options', ['option_name']);
        $c->detach('archive');
        $plan = SQLiteAttachTempCollationCachePlan::currentNext($c, $prepared);
        $t->same(['temp', 'main', 'site'], $plan['after_search_order']);
    },

    'attach temp collation cache current next40 yield has current and next rows' => static fn (TestRunner $t) => $t->same(['current', 'temp_shadow', 'temp_same'], array_column(SQLiteAttachTempCollationCachePlan::yieldCurrentNext($catalog(), 'wp_options', ['option_name'], [['label' => 'temp_shadow', 'catalog' => $catalog($tempShadow())], ['label' => 'temp_same', 'catalog' => $catalog($tempSameCollation())]]), 'label')),
    'attach temp collation cache current next40 yield current row is current' => static fn (TestRunner $t) => $t->same(true, SQLiteAttachTempCollationCachePlan::yieldCurrentNext($catalog(), 'wp_options', ['option_name'], [['label' => 'temp_shadow', 'catalog' => $catalog($tempShadow())]])[0]['validation']['current']),
    'attach temp collation cache current next40 yield next row is stale' => static fn (TestRunner $t) => $t->same('stale', SQLiteAttachTempCollationCachePlan::yieldCurrentNext($catalog(), 'wp_options', ['option_name'], [['label' => 'temp_shadow', 'catalog' => $catalog($tempShadow())]])[1]['validation']['status']),
    'attach temp collation cache current next40 quoted column normalized' => static fn (TestRunner $t) => $t->same(['option_name'], SQLiteAttachTempCollationCachePlan::prepare($catalog(), 'wp_options', ['"OPTION_NAME"', '`option_name`'])['columns']),
    'attach temp collation cache current next40 unknown column binary fallback' => static fn (TestRunner $t) => $t->same('BINARY', SQLiteAttachTempCollationCachePlan::prepare($catalog(), 'wp_options', ['missing_column'])['collations']['missing_column']),
    'attach temp collation cache current next40 missing table throws' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => SQLiteAttachTempCollationCachePlan::prepare($catalog(), 'missing_options', ['option_name'])),
    'attach temp collation cache current next40 empty columns throws' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => SQLiteAttachTempCollationCachePlan::prepare($catalog(), 'wp_options', [])),
    'attach temp collation cache current next40 validation without table throws' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => SQLiteAttachTempCollationCachePlan::currentNext($catalog(), ['columns' => ['option_name']])),
];
