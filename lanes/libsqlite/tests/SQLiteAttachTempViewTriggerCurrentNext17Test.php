<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteAttachedSchemaCatalog;
use PortLibs\LibSqlite\SQLiteAttachTempViewTriggerResolution;
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
            $record('trigger', 'audit_options_update', 'wp_options', 0, 'CREATE TRIGGER audit_options_update AFTER UPDATE OF option_name ON wp_options BEGIN INSERT INTO wp_option_audit(option_id, old_name, new_name) VALUES(old.option_id, old.option_name, new.option_name); END', 4),
            $record('trigger', 'active_options_insert', 'active_options', 0, "CREATE TRIGGER active_options_insert INSTEAD OF INSERT ON active_options BEGIN INSERT INTO wp_options(option_id, option_name, option_value, autoload) VALUES(new.option_id, new.option_name, new.option_value, 'yes'); END", 5),
            $record('trigger', 'bad_active_update', 'active_options', 0, 'CREATE TRIGGER bad_active_update INSTEAD OF UPDATE ON active_options BEGIN SELECT new.missing_column, old.option_name; END', 6),
        ],
        [
            $record('table', 'wp_options', 'wp_options', 10, 'CREATE TEMP TABLE wp_options(option_id integer, temp_name text, option_value text)', 7),
            $record('view', 'active_options', 'active_options', null, 'CREATE TEMP VIEW active_options(option_id, temp_name) AS SELECT option_id, temp_name FROM temp.wp_options', 8),
            $record('trigger', 'temp_options_insert', 'wp_options', null, 'CREATE TEMP TRIGGER temp_options_insert AFTER INSERT ON wp_options BEGIN INSERT INTO temp.wp_options(option_id, temp_name, option_value) VALUES(new.option_id, new.temp_name, new.option_value); END', 9),
            $record('trigger', 'temp_main_options_delete', 'wp_options', null, 'CREATE TEMP TRIGGER temp_main_options_delete AFTER DELETE ON main.wp_options BEGIN INSERT INTO wp_option_audit(option_id, label) VALUES(old.option_id, old.option_name); END', 10),
            $record('trigger', 'bad_temp_active_insert', 'active_options', null, 'CREATE TEMP TRIGGER bad_temp_active_insert INSTEAD OF INSERT ON active_options BEGIN SELECT new.option_name; END', 11),
        ],
    );

    $catalog->attach('site', '/srv/www/site.sqlite', [
        $record('table', 'wp_options', 'wp_options', 20, 'CREATE TABLE site.wp_options(blog_id integer, option_name text, option_value text)', 12),
        $record('view', 'active_options', 'active_options', 0, "CREATE VIEW site.active_options(blog_id, option_name) AS SELECT blog_id, option_name FROM wp_options WHERE option_value <> ''", 13),
        $record('trigger', 'site_options_update', 'wp_options', 0, 'CREATE TRIGGER site_options_update AFTER UPDATE ON wp_options BEGIN INSERT INTO site.wp_options(blog_id, option_name, option_value) VALUES(new.blog_id, new.option_name, new.option_value); END', 14),
        $record('trigger', 'site_active_insert', 'active_options', 0, 'CREATE TRIGGER site_active_insert INSTEAD OF INSERT ON active_options BEGIN SELECT new.blog_id, new.option_name; END', 15),
        $record('trigger', 'bad_site_active_insert', 'active_options', 0, 'CREATE TRIGGER bad_site_active_insert INSTEAD OF INSERT ON active_options BEGIN SELECT new.option_id; END', 16),
    ]);

    $catalog->attach('archive', '/srv/www/archive.sqlite', [
        $record('table', 'wp_options', 'wp_options', 30, 'CREATE TABLE archive.wp_options(option_name text, archived_at text)', 17),
        $record('trigger', 'archive_options_insert', 'wp_options', 0, 'CREATE TRIGGER archive_options_insert AFTER INSERT ON wp_options BEGIN SELECT new.option_name, new.archived_at; END', 18),
    ]);

    return $catalog;
};

$resolved = static fn (string $name): array => SQLiteAttachTempViewTriggerResolution::resolve($catalog(), $name);
$summary = static fn (): array => SQLiteAttachTempViewTriggerResolution::summary($catalog());

return [
    'attach temp view trigger current next17 resolves temp trigger from temp schema' => static fn (TestRunner $t) => $t->same('temp', $resolved('temp_options_insert')['triggerSchema']),
    'attach temp view trigger current next17 temp trigger is temporary' => static fn (TestRunner $t) => $t->same(true, $resolved('temp_options_insert')['triggerTemporary']),
    'attach temp view trigger current next17 temp trigger chooses temp target first' => static fn (TestRunner $t) => $t->same('temp', $resolved('temp_options_insert')['targetSchema']),
    'attach temp view trigger current next17 temp trigger target is table' => static fn (TestRunner $t) => $t->same('table', $resolved('temp_options_insert')['targetType']),
    'attach temp view trigger current next17 temp target columns' => static fn (TestRunner $t) => $t->same(['option_id', 'temp_name', 'option_value'], $resolved('temp_options_insert')['columns']),
    'attach temp view trigger current next17 temp trigger new columns' => static fn (TestRunner $t) => $t->same(['option_id', 'temp_name', 'option_value'], $resolved('temp_options_insert')['referencedNew']),
    'attach temp view trigger current next17 temp trigger resolved status' => static fn (TestRunner $t) => $t->same('resolved', $resolved('temp_options_insert')['status']),
    'attach temp view trigger current next17 temp trigger body dependency keeps schema' => static fn (TestRunner $t) => $t->same([['schema' => 'temp', 'name' => 'wp_options']], $resolved('temp_options_insert')['bodyDependencies']),

    'attach temp view trigger current next17 temp trigger can pin main target' => static fn (TestRunner $t) => $t->same('main', $resolved('temp_main_options_delete')['targetSchema']),
    'attach temp view trigger current next17 pinned main target not temporary' => static fn (TestRunner $t) => $t->same(false, $resolved('temp_main_options_delete')['targetTemporary']),
    'attach temp view trigger current next17 pinned main delete old columns' => static fn (TestRunner $t) => $t->same(['option_id', 'option_name'], $resolved('temp_main_options_delete')['referencedOld']),
    'attach temp view trigger current next17 pinned main dependency unqualified' => static fn (TestRunner $t) => $t->same([['schema' => null, 'name' => 'wp_option_audit']], $resolved('temp_main_options_delete')['bodyDependencies']),
    'attach temp view trigger current next17 pinned main delete resolved' => static fn (TestRunner $t) => $t->same('resolved', $resolved('temp_main_options_delete')['status']),

    'attach temp view trigger current next17 main trigger ignores temp shadow' => static fn (TestRunner $t) => $t->same('main', $resolved('main.audit_options_update')['targetSchema']),
    'attach temp view trigger current next17 main trigger schema stays main' => static fn (TestRunner $t) => $t->same('main', $resolved('audit_options_update')['triggerSchema']),
    'attach temp view trigger current next17 main trigger is not temporary' => static fn (TestRunner $t) => $t->same(false, $resolved('audit_options_update')['triggerTemporary']),
    'attach temp view trigger current next17 main trigger old columns' => static fn (TestRunner $t) => $t->same(['option_id', 'option_name'], $resolved('audit_options_update')['referencedOld']),
    'attach temp view trigger current next17 main trigger new columns' => static fn (TestRunner $t) => $t->same(['option_name'], $resolved('audit_options_update')['referencedNew']),
    'attach temp view trigger current next17 main body dependency audit table' => static fn (TestRunner $t) => $t->same([['schema' => null, 'name' => 'wp_option_audit']], $resolved('audit_options_update')['bodyDependencies']),
    'attach temp view trigger current next17 main trigger resolved' => static fn (TestRunner $t) => $t->same('resolved', $resolved('audit_options_update')['status']),

    'attach temp view trigger current next17 main view trigger targets main view' => static fn (TestRunner $t) => $t->same('main', $resolved('active_options_insert')['targetSchema']),
    'attach temp view trigger current next17 main view trigger type view' => static fn (TestRunner $t) => $t->same('view', $resolved('active_options_insert')['targetType']),
    'attach temp view trigger current next17 main view trigger instead of' => static fn (TestRunner $t) => $t->same(true, $resolved('active_options_insert')['insteadOf']),
    'attach temp view trigger current next17 main explicit view columns' => static fn (TestRunner $t) => $t->same(['option_id', 'option_name', 'option_value'], $resolved('active_options_insert')['columns']),
    'attach temp view trigger current next17 main view trigger new refs' => static fn (TestRunner $t) => $t->same(['option_id', 'option_name', 'option_value'], $resolved('active_options_insert')['referencedNew']),
    'attach temp view trigger current next17 main view trigger dependency main table' => static fn (TestRunner $t) => $t->same([['schema' => null, 'name' => 'wp_options']], $resolved('active_options_insert')['bodyDependencies']),
    'attach temp view trigger current next17 main view trigger resolved' => static fn (TestRunner $t) => $t->same('resolved', $resolved('active_options_insert')['status']),

    'attach temp view trigger current next17 bad main view missing new column' => static fn (TestRunner $t) => $t->same(['missing_column'], $resolved('bad_active_update')['missingNew']),
    'attach temp view trigger current next17 bad main view old valid' => static fn (TestRunner $t) => $t->same([], $resolved('bad_active_update')['missingOld']),
    'attach temp view trigger current next17 bad main view unresolved' => static fn (TestRunner $t) => $t->same('unresolved', $resolved('bad_active_update')['status']),
    'attach temp view trigger current next17 bad temp view uses temp columns' => static fn (TestRunner $t) => $t->same(['option_name'], $resolved('bad_temp_active_insert')['missingNew']),
    'attach temp view trigger current next17 bad temp view unresolved' => static fn (TestRunner $t) => $t->same('unresolved', $resolved('bad_temp_active_insert')['status']),

    'attach temp view trigger current next17 attached trigger schema is site' => static fn (TestRunner $t) => $t->same('site', $resolved('site.site_options_update')['triggerSchema']),
    'attach temp view trigger current next17 attached unqualified target stays site' => static fn (TestRunner $t) => $t->same('site', $resolved('site.site_options_update')['targetSchema']),
    'attach temp view trigger current next17 attached target ignores temp shadow' => static fn (TestRunner $t) => $t->same(false, $resolved('site.site_options_update')['targetTemporary']),
    'attach temp view trigger current next17 attached table columns' => static fn (TestRunner $t) => $t->same(['blog_id', 'option_name', 'option_value'], $resolved('site.site_options_update')['columns']),
    'attach temp view trigger current next17 attached update new refs' => static fn (TestRunner $t) => $t->same(['blog_id', 'option_name', 'option_value'], $resolved('site.site_options_update')['referencedNew']),
    'attach temp view trigger current next17 attached dependency keeps site schema' => static fn (TestRunner $t) => $t->same([['schema' => 'site', 'name' => 'wp_options']], $resolved('site.site_options_update')['bodyDependencies']),
    'attach temp view trigger current next17 attached trigger resolved' => static fn (TestRunner $t) => $t->same('resolved', $resolved('site.site_options_update')['status']),

    'attach temp view trigger current next17 attached view trigger target site view' => static fn (TestRunner $t) => $t->same('site', $resolved('site.site_active_insert')['targetSchema']),
    'attach temp view trigger current next17 attached view columns' => static fn (TestRunner $t) => $t->same(['blog_id', 'option_name'], $resolved('site.site_active_insert')['columns']),
    'attach temp view trigger current next17 attached view instead of' => static fn (TestRunner $t) => $t->same(true, $resolved('site.site_active_insert')['insteadOf']),
    'attach temp view trigger current next17 attached view new refs' => static fn (TestRunner $t) => $t->same(['blog_id', 'option_name'], $resolved('site.site_active_insert')['referencedNew']),
    'attach temp view trigger current next17 bad attached view missing option id' => static fn (TestRunner $t) => $t->same(['option_id'], $resolved('site.bad_site_active_insert')['missingNew']),
    'attach temp view trigger current next17 bad attached view unresolved' => static fn (TestRunner $t) => $t->same('unresolved', $resolved('site.bad_site_active_insert')['status']),

    'attach temp view trigger current next17 archive trigger stays archive' => static fn (TestRunner $t) => $t->same('archive', $resolved('archive.archive_options_insert')['targetSchema']),
    'attach temp view trigger current next17 archive columns' => static fn (TestRunner $t) => $t->same(['option_name', 'archived_at'], $resolved('archive.archive_options_insert')['columns']),
    'attach temp view trigger current next17 archive resolved' => static fn (TestRunner $t) => $t->same('resolved', $resolved('archive.archive_options_insert')['status']),
    'attach temp view trigger current next17 qualified temp trigger lookup' => static fn (TestRunner $t) => $t->same('temp_options_insert', SQLiteAttachTempViewTriggerResolution::resolveTrigger($catalog(), 'temp.temp_options_insert')['record']->name),
    'attach temp view trigger current next17 unqualified trigger search uses temp first' => static fn (TestRunner $t) => $t->same('temp', SQLiteAttachTempViewTriggerResolution::resolveTrigger($catalog(), 'temp_options_insert')['schema']),
    'attach temp view trigger current next17 missing trigger throws' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => SQLiteAttachTempViewTriggerResolution::resolve($catalog(), 'missing_trigger')),
    'attach temp view trigger current next17 missing target throws' => static function (TestRunner $t) use ($record): void {
        $t->throws(InvalidArgumentException::class, static fn () => SQLiteAttachTempViewTriggerResolution::resolve(new SQLiteAttachedSchemaCatalog([
        $record('trigger', 'orphan', 'missing_options', 0, 'CREATE TRIGGER orphan AFTER INSERT ON missing_options BEGIN SELECT new.option_id; END', 31),
    ]), 'orphan'));
    },
    'attach temp view trigger current next17 malformed trigger throws' => static function (TestRunner $t) use ($record): void {
        $t->throws(InvalidArgumentException::class, static fn () => SQLiteAttachTempViewTriggerResolution::resolve(new SQLiteAttachedSchemaCatalog([
        $record('trigger', 'bad', 'wp_options', 0, 'CREATE TRIGGER bad BEGIN SELECT 1; END', 32),
    ]), 'bad'));
    },
    'attach temp view trigger current next17 summary resolved count' => static fn (TestRunner $t) => $t->same(7, $summary()['resolved']),
    'attach temp view trigger current next17 summary unresolved count' => static fn (TestRunner $t) => $t->same(3, $summary()['unresolved']),
    'attach temp view trigger current next17 summary temp trigger count' => static fn (TestRunner $t) => $t->same(3, $summary()['tempTriggers']),
    'attach temp view trigger current next17 summary temp target count' => static fn (TestRunner $t) => $t->same(2, $summary()['tempTargets']),
    'attach temp view trigger current next17 summary attached target counts' => static fn (TestRunner $t) => $t->same(['archive' => 1, 'site' => 3], $summary()['attachedTargets']),
    'attach temp view trigger current next17 summary missing keys' => static fn (TestRunner $t) => $t->same(['bad_temp_active_insert', 'bad_active_update', 'bad_site_active_insert'], array_keys($summary()['missingReferences'])),
];
