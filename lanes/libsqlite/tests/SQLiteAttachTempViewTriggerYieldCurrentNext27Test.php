<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteAttachedSchemaCatalog;
use PortLibs\LibSqlite\SQLiteAttachTempViewTriggerYieldPlan;
use PortLibs\LibSqlite\SQLiteSchemaRecord;

$record = static fn (string $type, string $name, string $table, ?int $root, ?string $sql, int $rowid): SQLiteSchemaRecord => new SQLiteSchemaRecord(
    $type,
    $name,
    $table,
    $root,
    $sql,
    $rowid,
);

$catalog = static function () use ($record): SQLiteAttachedSchemaCatalog {
    $catalog = new SQLiteAttachedSchemaCatalog(
        [
            $record('table', 'wp_options', 'wp_options', 2, 'CREATE TABLE main.wp_options(option_id integer primary key, option_name text, option_value text, autoload text)', 1),
            $record('table', 'wp_option_audit', 'wp_option_audit', 3, 'CREATE TABLE wp_option_audit(option_id integer, label text, old_name text, new_name text)', 2),
            $record('view', 'active_options', 'active_options', 0, "CREATE VIEW active_options(option_id, option_name, option_value) AS SELECT option_id, option_name, option_value FROM wp_options WHERE autoload = 'yes'", 3),
            $record('trigger', 'active_options_insert_yield', 'active_options', 0, "CREATE TRIGGER active_options_insert_yield INSTEAD OF INSERT ON active_options BEGIN INSERT INTO wp_options(option_id, option_name, option_value, autoload) VALUES(new.option_id, new.option_name, new.option_value, 'yes'); INSERT INTO wp_option_audit(option_id, label, new_name) VALUES(new.option_id, 'main-view', new.option_name); SELECT new.option_id, new.option_name; END", 4),
            $record('trigger', 'options_update_yield', 'wp_options', 0, "CREATE TRIGGER options_update_yield AFTER UPDATE OF option_name ON wp_options BEGIN UPDATE wp_option_audit SET old_name = old.option_name WHERE option_id = old.option_id; INSERT INTO wp_option_audit(option_id, label, old_name, new_name) VALUES(new.option_id, 'main-update', old.option_name, new.option_name); END", 5),
            $record('trigger', 'bad_active_yield', 'active_options', 0, 'CREATE TRIGGER bad_active_yield INSTEAD OF INSERT ON active_options BEGIN INSERT INTO missing_audit(option_id) VALUES(new.option_id); END', 6),
        ],
        [
            $record('table', 'wp_options', 'wp_options', 10, 'CREATE TEMP TABLE wp_options(option_id integer, temp_name text, option_value text)', 7),
            $record('table', 'wp_option_audit', 'wp_option_audit', 11, 'CREATE TEMP TABLE wp_option_audit(option_id integer, label text, temp_name text)', 8),
            $record('view', 'active_options', 'active_options', null, 'CREATE TEMP VIEW active_options(option_id, temp_name, option_value) AS SELECT option_id, temp_name, option_value FROM temp.wp_options', 9),
            $record('trigger', 'temp_active_insert_yield', 'active_options', null, "CREATE TEMP TRIGGER temp_active_insert_yield INSTEAD OF INSERT ON active_options BEGIN INSERT INTO wp_options(option_id, temp_name, option_value) VALUES(new.option_id, new.temp_name, new.option_value); INSERT INTO wp_option_audit(option_id, label, temp_name) VALUES(new.option_id, 'temp-view', new.temp_name); SELECT new.option_id, new.temp_name; END", 10),
            $record('trigger', 'temp_main_delete_yield', 'wp_options', null, "CREATE TEMP TRIGGER temp_main_delete_yield AFTER DELETE ON main.wp_options BEGIN DELETE FROM wp_option_audit WHERE option_id = old.option_id; INSERT INTO main.wp_option_audit(option_id, label, old_name) VALUES(old.option_id, 'main-delete', old.option_name); END", 11),
        ],
    );

    $catalog->attach('site', '/srv/site.sqlite', [
        $record('table', 'wp_options', 'wp_options', 20, 'CREATE TABLE site.wp_options(blog_id integer, option_name text, option_value text)', 12),
        $record('table', 'wp_option_audit', 'wp_option_audit', 21, 'CREATE TABLE site.wp_option_audit(blog_id integer, label text, option_name text)', 13),
        $record('view', 'active_options', 'active_options', 0, 'CREATE VIEW site.active_options(blog_id, option_name, option_value) AS SELECT blog_id, option_name, option_value FROM wp_options', 14),
        $record('trigger', 'site_active_insert_yield', 'active_options', 0, "CREATE TRIGGER site_active_insert_yield INSTEAD OF INSERT ON active_options BEGIN INSERT INTO wp_options(blog_id, option_name, option_value) VALUES(new.blog_id, new.option_name, new.option_value); INSERT INTO site.wp_option_audit(blog_id, label, option_name) VALUES(new.blog_id, 'site-view', new.option_name); SELECT new.blog_id, new.option_name; END", 15),
    ]);

    $catalog->attach('archive', '/srv/archive.sqlite', [
        $record('table', 'wp_options_archive', 'wp_options_archive', 30, 'CREATE TABLE archive.wp_options_archive(option_id integer, option_name text, archived_at text)', 16),
        $record('trigger', 'archive_cleanup_yield', 'wp_options_archive', 0, "CREATE TRIGGER archive_cleanup_yield AFTER DELETE ON wp_options_archive BEGIN SELECT old.option_id, old.option_name; END", 17),
    ]);

    return $catalog;
};

$mainNew = ['option_id' => 7, 'option_name' => 'siteurl', 'option_value' => 'https://new.test', 'autoload' => 'yes'];
$mainOld = ['option_id' => 7, 'option_name' => 'old_siteurl', 'option_value' => 'https://old.test', 'autoload' => 'yes'];
$tempNew = ['option_id' => 9, 'temp_name' => 'plugin_cache', 'option_value' => '{"enabled":true}'];
$siteNew = ['blog_id' => 3, 'option_name' => 'home', 'option_value' => 'https://site.test'];
$archiveOld = ['option_id' => 44, 'option_name' => '_transient_feed', 'archived_at' => '2026-05-27'];

$yield = static fn (string $trigger, array $new = [], ?array $old = null): array => SQLiteAttachTempViewTriggerYieldPlan::yield($catalog(), $trigger, $new, $old);
$ops = static fn (string $trigger, array $new = [], ?array $old = null): array => SQLiteAttachTempViewTriggerYieldPlan::operations($catalog(), $trigger, $new, $old);

return [
    'attach temp view trigger yield current next27 main trigger schema' => static fn (TestRunner $t) => $t->same('main', $yield('active_options_insert_yield', $mainNew)['triggerSchema']),
    'attach temp view trigger yield current next27 main target schema' => static fn (TestRunner $t) => $t->same('main', $yield('active_options_insert_yield', $mainNew)['targetSchema']),
    'attach temp view trigger yield current next27 main target view' => static fn (TestRunner $t) => $t->same('active_options', $yield('active_options_insert_yield', $mainNew)['target']),
    'attach temp view trigger yield current next27 main operation count' => static fn (TestRunner $t) => $t->same(3, $yield('active_options_insert_yield', $mainNew)['operationCount']),
    'attach temp view trigger yield current next27 main writes by schema' => static fn (TestRunner $t) => $t->same(['main' => 2], $yield('active_options_insert_yield', $mainNew)['writesBySchema']),
    'attach temp view trigger yield current next27 main read count' => static fn (TestRunner $t) => $t->same(1, $yield('active_options_insert_yield', $mainNew)['readCount']),
    'attach temp view trigger yield current next27 main yielded status' => static fn (TestRunner $t) => $t->same('yielded', $yield('active_options_insert_yield', $mainNew)['status']),
    'attach temp view trigger yield current next27 main first op kind' => static fn (TestRunner $t) => $t->same('insert', $ops('active_options_insert_yield', $mainNew)[0]['kind']),
    'attach temp view trigger yield current next27 main first op table' => static fn (TestRunner $t) => $t->same('wp_options', $ops('active_options_insert_yield', $mainNew)[0]['table']),
    'attach temp view trigger yield current next27 main first op schema' => static fn (TestRunner $t) => $t->same('main', $ops('active_options_insert_yield', $mainNew)[0]['schema']),
    'attach temp view trigger yield current next27 main first op columns' => static fn (TestRunner $t) => $t->same(['option_id', 'option_name', 'option_value', 'autoload'], $ops('active_options_insert_yield', $mainNew)[0]['columns']),
    'attach temp view trigger yield current next27 main first op row' => static fn (TestRunner $t) => $t->same(['option_id' => 7, 'option_name' => 'siteurl', 'option_value' => 'https://new.test', 'autoload' => 'yes'], $ops('active_options_insert_yield', $mainNew)[0]['row']),
    'attach temp view trigger yield current next27 main audit op row' => static fn (TestRunner $t) => $t->same(['option_id' => 7, 'label' => 'main-view', 'new_name' => 'siteurl'], $ops('active_options_insert_yield', $mainNew)[1]['row']),
    'attach temp view trigger yield current next27 main select values' => static fn (TestRunner $t) => $t->same([7, 'siteurl'], $ops('active_options_insert_yield', $mainNew)[2]['values']),

    'attach temp view trigger yield current next27 temp trigger schema' => static fn (TestRunner $t) => $t->same('temp', $yield('temp_active_insert_yield', $tempNew)['triggerSchema']),
    'attach temp view trigger yield current next27 temp target schema' => static fn (TestRunner $t) => $t->same('temp', $yield('temp_active_insert_yield', $tempNew)['targetSchema']),
    'attach temp view trigger yield current next27 temp writes stay temp' => static fn (TestRunner $t) => $t->same(['temp' => 2], $yield('temp_active_insert_yield', $tempNew)['writesBySchema']),
    'attach temp view trigger yield current next27 temp first op schema' => static fn (TestRunner $t) => $t->same('temp', $ops('temp_active_insert_yield', $tempNew)[0]['schema']),
    'attach temp view trigger yield current next27 temp first op table' => static fn (TestRunner $t) => $t->same('wp_options', $ops('temp_active_insert_yield', $tempNew)[0]['table']),
    'attach temp view trigger yield current next27 temp first op row' => static fn (TestRunner $t) => $t->same(['option_id' => 9, 'temp_name' => 'plugin_cache', 'option_value' => '{"enabled":true}'], $ops('temp_active_insert_yield', $tempNew)[0]['row']),
    'attach temp view trigger yield current next27 temp audit table shadows main' => static fn (TestRunner $t) => $t->same('temp', $ops('temp_active_insert_yield', $tempNew)[1]['schema']),
    'attach temp view trigger yield current next27 temp audit row' => static fn (TestRunner $t) => $t->same(['option_id' => 9, 'label' => 'temp-view', 'temp_name' => 'plugin_cache'], $ops('temp_active_insert_yield', $tempNew)[1]['row']),
    'attach temp view trigger yield current next27 temp select values' => static fn (TestRunner $t) => $t->same([9, 'plugin_cache'], $ops('temp_active_insert_yield', $tempNew)[2]['values']),

    'attach temp view trigger yield current next27 temp trigger can pin main target' => static fn (TestRunner $t) => $t->same('main', $yield('temp_main_delete_yield', [], $mainOld)['targetSchema']),
    'attach temp view trigger yield current next27 temp pinned delete has two writes' => static fn (TestRunner $t) => $t->same(2, $yield('temp_main_delete_yield', [], $mainOld)['operationCount']),
    'attach temp view trigger yield current next27 temp pinned writes split schemas' => static fn (TestRunner $t) => $t->same(['main' => 1, 'temp' => 1], $yield('temp_main_delete_yield', [], $mainOld)['writesBySchema']),
    'attach temp view trigger yield current next27 temp unqualified delete uses temp audit' => static fn (TestRunner $t) => $t->same('temp', $ops('temp_main_delete_yield', [], $mainOld)[0]['schema']),
    'attach temp view trigger yield current next27 temp delete predicate binds old' => static fn (TestRunner $t) => $t->same(['column' => 'option_id', 'operator' => '=', 'value' => 7], $ops('temp_main_delete_yield', [], $mainOld)[0]['where']),
    'attach temp view trigger yield current next27 temp qualified insert uses main' => static fn (TestRunner $t) => $t->same('main', $ops('temp_main_delete_yield', [], $mainOld)[1]['schema']),
    'attach temp view trigger yield current next27 temp qualified insert row binds old' => static fn (TestRunner $t) => $t->same(['option_id' => 7, 'label' => 'main-delete', 'old_name' => 'old_siteurl'], $ops('temp_main_delete_yield', [], $mainOld)[1]['row']),

    'attach temp view trigger yield current next27 attached trigger schema' => static fn (TestRunner $t) => $t->same('site', $yield('site.site_active_insert_yield', $siteNew)['triggerSchema']),
    'attach temp view trigger yield current next27 attached target schema' => static fn (TestRunner $t) => $t->same('site', $yield('site.site_active_insert_yield', $siteNew)['targetSchema']),
    'attach temp view trigger yield current next27 attached writes stay attached' => static fn (TestRunner $t) => $t->same(['site' => 2], $yield('site.site_active_insert_yield', $siteNew)['writesBySchema']),
    'attach temp view trigger yield current next27 attached unqualified insert stays site' => static fn (TestRunner $t) => $t->same('site', $ops('site.site_active_insert_yield', $siteNew)[0]['schema']),
    'attach temp view trigger yield current next27 attached unqualified insert row' => static fn (TestRunner $t) => $t->same(['blog_id' => 3, 'option_name' => 'home', 'option_value' => 'https://site.test'], $ops('site.site_active_insert_yield', $siteNew)[0]['row']),
    'attach temp view trigger yield current next27 attached qualified audit schema' => static fn (TestRunner $t) => $t->same('site', $ops('site.site_active_insert_yield', $siteNew)[1]['schema']),
    'attach temp view trigger yield current next27 attached qualified audit row' => static fn (TestRunner $t) => $t->same(['blog_id' => 3, 'label' => 'site-view', 'option_name' => 'home'], $ops('site.site_active_insert_yield', $siteNew)[1]['row']),
    'attach temp view trigger yield current next27 attached select values' => static fn (TestRunner $t) => $t->same([3, 'home'], $ops('site.site_active_insert_yield', $siteNew)[2]['values']),

    'attach temp view trigger yield current next27 main update operation count' => static fn (TestRunner $t) => $t->same(2, $yield('options_update_yield', $mainNew, $mainOld)['operationCount']),
    'attach temp view trigger yield current next27 main update first kind' => static fn (TestRunner $t) => $t->same('update', $ops('options_update_yield', $mainNew, $mainOld)[0]['kind']),
    'attach temp view trigger yield current next27 main update set binds old' => static fn (TestRunner $t) => $t->same(['old_name' => 'old_siteurl'], $ops('options_update_yield', $mainNew, $mainOld)[0]['set']),
    'attach temp view trigger yield current next27 main update where binds old' => static fn (TestRunner $t) => $t->same(['column' => 'option_id', 'operator' => '=', 'value' => 7], $ops('options_update_yield', $mainNew, $mainOld)[0]['where']),
    'attach temp view trigger yield current next27 main update audit row' => static fn (TestRunner $t) => $t->same(['option_id' => 7, 'label' => 'main-update', 'old_name' => 'old_siteurl', 'new_name' => 'siteurl'], $ops('options_update_yield', $mainNew, $mainOld)[1]['row']),

    'attach temp view trigger yield current next27 archive select has no writes' => static fn (TestRunner $t) => $t->same([], $yield('archive.archive_cleanup_yield', [], $archiveOld)['writesBySchema']),
    'attach temp view trigger yield current next27 archive select count' => static fn (TestRunner $t) => $t->same(1, $yield('archive.archive_cleanup_yield', [], $archiveOld)['readCount']),
    'attach temp view trigger yield current next27 archive select schema' => static fn (TestRunner $t) => $t->same('archive', $ops('archive.archive_cleanup_yield', [], $archiveOld)[0]['schema']),
    'attach temp view trigger yield current next27 archive select old values' => static fn (TestRunner $t) => $t->same([44, '_transient_feed'], $ops('archive.archive_cleanup_yield', [], $archiveOld)[0]['values']),

    'attach temp view trigger yield current next27 qualified temp trigger lookup works' => static fn (TestRunner $t) => $t->same('temp', $yield('temp.temp_active_insert_yield', $tempNew)['triggerSchema']),
    'attach temp view trigger yield current next27 unqualified temp trigger wins' => static fn (TestRunner $t) => $t->same('temp', $yield('temp_active_insert_yield', $tempNew)['triggerSchema']),
    'attach temp view trigger yield current next27 missing body table throws' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => $yield('bad_active_yield', $mainNew)),
    'attach temp view trigger yield current next27 missing new row column throws' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => $yield('active_options_insert_yield', ['option_id' => 1])),
    'attach temp view trigger yield current next27 missing old row throws' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => $yield('options_update_yield', $mainNew)),
    'attach temp view trigger yield current next27 missing old column throws' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => $yield('options_update_yield', $mainNew, ['option_id' => 7])),
    'attach temp view trigger yield current next27 missing trigger throws' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => $yield('missing_yield', $mainNew)),
    'attach temp view trigger yield current next27 unresolved trigger references throw' => static function (TestRunner $t) use ($record): void {
        $broken = new SQLiteAttachedSchemaCatalog([
            $record('view', 'active_options', 'active_options', 0, 'CREATE VIEW active_options(option_id) AS SELECT option_id FROM wp_options', 1),
            $record('trigger', 'bad_new_column', 'active_options', 0, 'CREATE TRIGGER bad_new_column INSTEAD OF INSERT ON active_options BEGIN SELECT new.option_name; END', 2),
        ]);

        $t->throws(InvalidArgumentException::class, static fn () => SQLiteAttachTempViewTriggerYieldPlan::yield($broken, 'bad_new_column', ['option_id' => 1]));
    },
];
