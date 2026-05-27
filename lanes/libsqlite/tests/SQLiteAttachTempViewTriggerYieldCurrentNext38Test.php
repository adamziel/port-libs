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
            $record('table', 'wp_options', 'wp_options', 2, 'CREATE TABLE wp_options(option_id integer primary key, option_name text, option_value text, autoload text)', 1),
            $record('table', 'wp_option_audit', 'wp_option_audit', 3, 'CREATE TABLE wp_option_audit(option_id integer, label text, old_name text, new_name text, autoload text)', 2),
            $record('view', 'active_options', 'active_options', 0, "CREATE VIEW active_options(option_id, option_name, option_value, autoload) AS SELECT option_id, option_name, option_value, autoload FROM wp_options WHERE autoload = 'yes'", 3),
            $record('trigger', 'active_options_when_insert', 'active_options', 0, "CREATE TRIGGER active_options_when_insert INSTEAD OF INSERT ON active_options WHEN new.autoload = 'yes' BEGIN INSERT INTO wp_options(option_id, option_name, option_value, autoload) VALUES(new.option_id, new.option_name, new.option_value, new.autoload); INSERT INTO wp_option_audit(option_id, label, new_name, autoload) VALUES(new.option_id, 'main-when', new.option_name, new.autoload); END", 4),
            $record('trigger', 'options_when_update', 'wp_options', 0, "CREATE TRIGGER options_when_update AFTER UPDATE OF option_value ON wp_options WHEN old.option_value <> new.option_value AND new.autoload IS NOT NULL BEGIN UPDATE wp_option_audit SET old_name = old.option_name WHERE option_id = old.option_id; INSERT INTO wp_option_audit(option_id, label, old_name, new_name) VALUES(new.option_id, 'main-update-when', old.option_name, new.option_name); END", 5),
            $record('trigger', 'options_null_delete', 'wp_options', 0, "CREATE TRIGGER options_null_delete AFTER DELETE ON wp_options WHEN old.autoload IS NULL BEGIN INSERT INTO wp_option_audit(option_id, label, old_name) VALUES(old.option_id, 'main-null-delete', old.option_name); END", 6),
        ],
        [
            $record('table', 'wp_options', 'wp_options', 10, 'CREATE TEMP TABLE wp_options(option_id integer, temp_name text, option_value text, autoload text)', 7),
            $record('table', 'wp_option_audit', 'wp_option_audit', 11, 'CREATE TEMP TABLE wp_option_audit(option_id integer, label text, temp_name text)', 8),
            $record('view', 'active_options', 'active_options', null, 'CREATE TEMP VIEW active_options(option_id, temp_name, option_value, autoload) AS SELECT option_id, temp_name, option_value, autoload FROM temp.wp_options', 9),
            $record('trigger', 'temp_active_when_insert', 'active_options', null, "CREATE TEMP TRIGGER temp_active_when_insert INSTEAD OF INSERT ON active_options WHEN new.autoload = 'yes' OR new.temp_name = 'force' BEGIN INSERT INTO wp_options(option_id, temp_name, option_value, autoload) VALUES(new.option_id, new.temp_name, new.option_value, new.autoload); INSERT INTO wp_option_audit(option_id, label, temp_name) VALUES(new.option_id, 'temp-when', new.temp_name); END", 10),
            $record('trigger', 'temp_main_when_delete', 'wp_options', null, "CREATE TEMP TRIGGER temp_main_when_delete AFTER DELETE ON main.wp_options WHEN old.autoload IS NOT NULL BEGIN DELETE FROM wp_option_audit WHERE option_id = old.option_id; INSERT INTO main.wp_option_audit(option_id, label, old_name) VALUES(old.option_id, 'temp-main-delete-when', old.option_name); END", 11),
        ],
    );

    $catalog->attach('site', '/srv/site.sqlite', [
        $record('table', 'wp_options', 'wp_options', 20, 'CREATE TABLE site.wp_options(blog_id integer, option_name text, option_value text, autoload text)', 12),
        $record('table', 'wp_option_audit', 'wp_option_audit', 21, 'CREATE TABLE site.wp_option_audit(blog_id integer, label text, option_name text)', 13),
        $record('view', 'active_options', 'active_options', 0, 'CREATE VIEW site.active_options(blog_id, option_name, option_value, autoload) AS SELECT blog_id, option_name, option_value, autoload FROM wp_options', 14),
        $record('trigger', 'site_active_when_insert', 'active_options', 0, "CREATE TRIGGER site_active_when_insert INSTEAD OF INSERT ON active_options WHEN new.blog_id = 3 AND new.option_name != 'skip_me' BEGIN INSERT INTO wp_options(blog_id, option_name, option_value, autoload) VALUES(new.blog_id, new.option_name, new.option_value, new.autoload); INSERT INTO site.wp_option_audit(blog_id, label, option_name) VALUES(new.blog_id, 'site-when', new.option_name); END", 15),
        $record('trigger', 'site_numeric_truthy', 'wp_options', 0, "CREATE TRIGGER site_numeric_truthy AFTER INSERT ON wp_options WHEN new.blog_id BEGIN SELECT new.blog_id, new.option_name; END", 16),
    ]);

    return $catalog;
};

$mainNew = ['option_id' => 7, 'option_name' => 'siteurl', 'option_value' => 'https://new.test', 'autoload' => 'yes'];
$mainOld = ['option_id' => 7, 'option_name' => 'siteurl', 'option_value' => 'https://old.test', 'autoload' => 'yes'];
$mainNo = ['option_id' => 8, 'option_name' => 'home', 'option_value' => 'https://new.test', 'autoload' => 'no'];
$mainNullOld = ['option_id' => 9, 'option_name' => '_transient_empty', 'option_value' => '', 'autoload' => null];
$tempYes = ['option_id' => 21, 'temp_name' => 'plugin_cache', 'option_value' => '{"on":true}', 'autoload' => 'yes'];
$tempForce = ['option_id' => 22, 'temp_name' => 'force', 'option_value' => '{"on":false}', 'autoload' => 'no'];
$tempNo = ['option_id' => 23, 'temp_name' => 'ordinary', 'option_value' => '{}', 'autoload' => 'no'];
$siteNew = ['blog_id' => 3, 'option_name' => 'home', 'option_value' => 'https://site.test', 'autoload' => 'yes'];
$siteSkip = ['blog_id' => 3, 'option_name' => 'skip_me', 'option_value' => 'x', 'autoload' => 'yes'];
$siteWrongBlog = ['blog_id' => 4, 'option_name' => 'home', 'option_value' => 'x', 'autoload' => 'yes'];
$siteZero = ['blog_id' => 0, 'option_name' => 'zero', 'option_value' => 'x', 'autoload' => 'no'];

$yield = static fn (string $trigger, array $new = [], ?array $old = null): array => SQLiteAttachTempViewTriggerYieldPlan::yield($catalog(), $trigger, $new, $old);
$ops = static fn (string $trigger, array $new = [], ?array $old = null): array => SQLiteAttachTempViewTriggerYieldPlan::operations($catalog(), $trigger, $new, $old);

$tests = [
    'attach temp view trigger yield current next38 main when text captured' => static fn (TestRunner $t) => $t->same("new.autoload = 'yes'", $yield('active_options_when_insert', $mainNew)['when']),
    'attach temp view trigger yield current next38 main when matched' => static fn (TestRunner $t) => $t->same(true, $yield('active_options_when_insert', $mainNew)['whenMatched']),
    'attach temp view trigger yield current next38 main status yielded' => static fn (TestRunner $t) => $t->same('yielded', $yield('active_options_when_insert', $mainNew)['status']),
    'attach temp view trigger yield current next38 main operation count' => static fn (TestRunner $t) => $t->same(2, $yield('active_options_when_insert', $mainNew)['operationCount']),
    'attach temp view trigger yield current next38 main writes schema' => static fn (TestRunner $t) => $t->same(['main' => 2], $yield('active_options_when_insert', $mainNew)['writesBySchema']),
    'attach temp view trigger yield current next38 main insert table' => static fn (TestRunner $t) => $t->same('wp_options', $ops('active_options_when_insert', $mainNew)[0]['table']),
    'attach temp view trigger yield current next38 main insert schema' => static fn (TestRunner $t) => $t->same('main', $ops('active_options_when_insert', $mainNew)[0]['schema']),
    'attach temp view trigger yield current next38 main insert autoload from new' => static fn (TestRunner $t) => $t->same('yes', $ops('active_options_when_insert', $mainNew)[0]['row']['autoload']),
    'attach temp view trigger yield current next38 main audit label' => static fn (TestRunner $t) => $t->same('main-when', $ops('active_options_when_insert', $mainNew)[1]['row']['label']),
    'attach temp view trigger yield current next38 main audit new name' => static fn (TestRunner $t) => $t->same('siteurl', $ops('active_options_when_insert', $mainNew)[1]['row']['new_name']),
    'attach temp view trigger yield current next38 main skipped status' => static fn (TestRunner $t) => $t->same('skipped', $yield('active_options_when_insert', $mainNo)['status']),
    'attach temp view trigger yield current next38 main skipped when false' => static fn (TestRunner $t) => $t->same(false, $yield('active_options_when_insert', $mainNo)['whenMatched']),
    'attach temp view trigger yield current next38 main skipped no operations' => static fn (TestRunner $t) => $t->same([], $yield('active_options_when_insert', $mainNo)['operations']),
    'attach temp view trigger yield current next38 main skipped no writes' => static fn (TestRunner $t) => $t->same([], $yield('active_options_when_insert', $mainNo)['writesBySchema']),

    'attach temp view trigger yield current next38 update and is not matched' => static fn (TestRunner $t) => $t->same(true, $yield('options_when_update', $mainNew, $mainOld)['whenMatched']),
    'attach temp view trigger yield current next38 update operation count' => static fn (TestRunner $t) => $t->same(2, $yield('options_when_update', $mainNew, $mainOld)['operationCount']),
    'attach temp view trigger yield current next38 update first kind' => static fn (TestRunner $t) => $t->same('update', $ops('options_when_update', $mainNew, $mainOld)[0]['kind']),
    'attach temp view trigger yield current next38 update set old value' => static fn (TestRunner $t) => $t->same(['old_name' => 'siteurl'], $ops('options_when_update', $mainNew, $mainOld)[0]['set']),
    'attach temp view trigger yield current next38 update where old id' => static fn (TestRunner $t) => $t->same(['column' => 'option_id', 'operator' => '=', 'value' => 7], $ops('options_when_update', $mainNew, $mainOld)[0]['where']),
    'attach temp view trigger yield current next38 update audit old name' => static fn (TestRunner $t) => $t->same('siteurl', $ops('options_when_update', $mainNew, $mainOld)[1]['row']['old_name']),
    'attach temp view trigger yield current next38 update same value skipped' => static fn (TestRunner $t) => $t->same('skipped', $yield('options_when_update', $mainOld, $mainOld)['status']),
    'attach temp view trigger yield current next38 update null autoload skipped' => static fn (TestRunner $t) => $t->same('skipped', $yield('options_when_update', ['option_id' => 7, 'option_name' => 'siteurl', 'option_value' => 'changed', 'autoload' => null], $mainOld)['status']),

    'attach temp view trigger yield current next38 delete is null matched' => static fn (TestRunner $t) => $t->same(true, $yield('options_null_delete', [], $mainNullOld)['whenMatched']),
    'attach temp view trigger yield current next38 delete null row label' => static fn (TestRunner $t) => $t->same('main-null-delete', $ops('options_null_delete', [], $mainNullOld)[0]['row']['label']),
    'attach temp view trigger yield current next38 delete nonnull skipped' => static fn (TestRunner $t) => $t->same('skipped', $yield('options_null_delete', [], $mainOld)['status']),

    'attach temp view trigger yield current next38 temp or first branch matched' => static fn (TestRunner $t) => $t->same(true, $yield('temp_active_when_insert', $tempYes)['whenMatched']),
    'attach temp view trigger yield current next38 temp or second branch matched' => static fn (TestRunner $t) => $t->same(true, $yield('temp_active_when_insert', $tempForce)['whenMatched']),
    'attach temp view trigger yield current next38 temp or false skipped' => static fn (TestRunner $t) => $t->same('skipped', $yield('temp_active_when_insert', $tempNo)['status']),
    'attach temp view trigger yield current next38 temp writes stay temp' => static fn (TestRunner $t) => $t->same(['temp' => 2], $yield('temp_active_when_insert', $tempYes)['writesBySchema']),
    'attach temp view trigger yield current next38 temp insert schema' => static fn (TestRunner $t) => $t->same('temp', $ops('temp_active_when_insert', $tempYes)[0]['schema']),
    'attach temp view trigger yield current next38 temp audit schema' => static fn (TestRunner $t) => $t->same('temp', $ops('temp_active_when_insert', $tempForce)[1]['schema']),
    'attach temp view trigger yield current next38 temp force audit row' => static fn (TestRunner $t) => $t->same(['option_id' => 22, 'label' => 'temp-when', 'temp_name' => 'force'], $ops('temp_active_when_insert', $tempForce)[1]['row']),

    'attach temp view trigger yield current next38 temp main delete target main' => static fn (TestRunner $t) => $t->same('main', $yield('temp_main_when_delete', [], $mainOld)['targetSchema']),
    'attach temp view trigger yield current next38 temp main delete split writes' => static fn (TestRunner $t) => $t->same(['main' => 1, 'temp' => 1], $yield('temp_main_when_delete', [], $mainOld)['writesBySchema']),
    'attach temp view trigger yield current next38 temp main unqualified body shadows temp' => static fn (TestRunner $t) => $t->same('temp', $ops('temp_main_when_delete', [], $mainOld)[0]['schema']),
    'attach temp view trigger yield current next38 temp main qualified audit uses main' => static fn (TestRunner $t) => $t->same('main', $ops('temp_main_when_delete', [], $mainOld)[1]['schema']),
    'attach temp view trigger yield current next38 temp main null old skipped' => static fn (TestRunner $t) => $t->same('skipped', $yield('temp_main_when_delete', [], $mainNullOld)['status']),

    'attach temp view trigger yield current next38 attached and matched' => static fn (TestRunner $t) => $t->same(true, $yield('site.site_active_when_insert', $siteNew)['whenMatched']),
    'attach temp view trigger yield current next38 attached trigger schema' => static fn (TestRunner $t) => $t->same('site', $yield('site.site_active_when_insert', $siteNew)['triggerSchema']),
    'attach temp view trigger yield current next38 attached target schema' => static fn (TestRunner $t) => $t->same('site', $yield('site.site_active_when_insert', $siteNew)['targetSchema']),
    'attach temp view trigger yield current next38 attached writes stay site' => static fn (TestRunner $t) => $t->same(['site' => 2], $yield('site.site_active_when_insert', $siteNew)['writesBySchema']),
    'attach temp view trigger yield current next38 attached insert row' => static fn (TestRunner $t) => $t->same(['blog_id' => 3, 'option_name' => 'home', 'option_value' => 'https://site.test', 'autoload' => 'yes'], $ops('site.site_active_when_insert', $siteNew)[0]['row']),
    'attach temp view trigger yield current next38 attached audit row' => static fn (TestRunner $t) => $t->same(['blog_id' => 3, 'label' => 'site-when', 'option_name' => 'home'], $ops('site.site_active_when_insert', $siteNew)[1]['row']),
    'attach temp view trigger yield current next38 attached skip name skipped' => static fn (TestRunner $t) => $t->same('skipped', $yield('site.site_active_when_insert', $siteSkip)['status']),
    'attach temp view trigger yield current next38 attached wrong blog skipped' => static fn (TestRunner $t) => $t->same('skipped', $yield('site.site_active_when_insert', $siteWrongBlog)['status']),

    'attach temp view trigger yield current next38 numeric truthy matched' => static fn (TestRunner $t) => $t->same('yielded', $yield('site.site_numeric_truthy', $siteNew)['status']),
    'attach temp view trigger yield current next38 numeric truthy read count' => static fn (TestRunner $t) => $t->same(1, $yield('site.site_numeric_truthy', $siteNew)['readCount']),
    'attach temp view trigger yield current next38 numeric truthy select values' => static fn (TestRunner $t) => $t->same([3, 'home'], $ops('site.site_numeric_truthy', $siteNew)[0]['values']),
    'attach temp view trigger yield current next38 numeric zero skipped' => static fn (TestRunner $t) => $t->same('skipped', $yield('site.site_numeric_truthy', $siteZero)['status']),

    'attach temp view trigger yield current next38 missing when new column throws' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => $yield('active_options_when_insert', ['option_id' => 1])),
    'attach temp view trigger yield current next38 missing when old row throws' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => $yield('options_null_delete')),
    'attach temp view trigger yield current next38 missing when old column throws' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => $yield('options_null_delete', [], ['option_id' => 1])),
];

return $tests;
