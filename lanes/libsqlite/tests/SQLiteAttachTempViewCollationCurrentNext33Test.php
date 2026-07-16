<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteAttachedSchemaCatalog;
use PortLibs\LibSqlite\SQLiteAttachTempViewCollationPlan;
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
            $record('table', 'wp_options', 'wp_options', 2, 'CREATE TABLE main.wp_options(option_id integer primary key, option_name text COLLATE NOCASE, option_value text COLLATE RTRIM, autoload text COLLATE BINARY)', 1),
            $record('table', 'wp_option_audit', 'wp_option_audit', 3, 'CREATE TABLE wp_option_audit(option_id integer, audit_name text COLLATE RTRIM, option_name text COLLATE NOCASE, source text)', 2),
            $record('view', 'active_options', 'active_options', 0, 'CREATE VIEW active_options(option_id, option_name, option_value) AS SELECT option_id, option_name COLLATE NOCASE, option_value COLLATE RTRIM FROM wp_options', 3),
            $record('trigger', 'active_options_insert_collate', 'active_options', 0, "CREATE TRIGGER active_options_insert_collate INSTEAD OF INSERT ON active_options BEGIN INSERT INTO wp_options(option_id, option_name, option_value, autoload) VALUES(new.option_id, new.option_name, new.option_value, 'yes'); INSERT INTO wp_option_audit(option_id, audit_name, option_name, source) VALUES(new.option_id, new.option_name, new.option_name, 'main'); SELECT new.option_name COLLATE NOCASE, new.option_value COLLATE RTRIM; END", 4),
            $record('trigger', 'options_update_collate', 'wp_options', 0, "CREATE TRIGGER options_update_collate AFTER UPDATE OF option_name ON wp_options BEGIN UPDATE wp_option_audit SET audit_name = new.option_name WHERE option_name COLLATE NOCASE = old.option_name COLLATE NOCASE; SELECT new.option_name COLLATE NOCASE; END", 5),
        ],
        [
            $record('table', 'wp_options', 'wp_options', 10, 'CREATE TEMP TABLE wp_options(option_id integer primary key, temp_name text COLLATE RTRIM, option_value text COLLATE NOCASE)', 6),
            $record('table', 'wp_option_audit', 'wp_option_audit', 11, 'CREATE TEMP TABLE wp_option_audit(option_id integer, temp_name text COLLATE RTRIM, source text COLLATE BINARY)', 7),
            $record('view', 'active_options', 'active_options', null, 'CREATE TEMP VIEW active_options(option_id, temp_name, option_value) AS SELECT option_id, temp_name COLLATE RTRIM, option_value COLLATE NOCASE FROM temp.wp_options', 8),
            $record('trigger', 'temp_active_insert_collate', 'active_options', null, "CREATE TEMP TRIGGER temp_active_insert_collate INSTEAD OF INSERT ON active_options BEGIN INSERT INTO wp_options(option_id, temp_name, option_value) VALUES(new.option_id, new.temp_name, new.option_value); INSERT INTO wp_option_audit(option_id, temp_name, source) VALUES(new.option_id, new.temp_name, 'temp'); SELECT new.temp_name COLLATE RTRIM, new.option_value COLLATE NOCASE; END", 9),
            $record('trigger', 'temp_main_delete_collate', 'wp_options', null, "CREATE TEMP TRIGGER temp_main_delete_collate AFTER DELETE ON main.wp_options BEGIN DELETE FROM wp_option_audit WHERE temp_name COLLATE RTRIM = old.option_name COLLATE NOCASE; INSERT INTO main.wp_option_audit(option_id, audit_name, option_name, source) VALUES(old.option_id, old.option_name, old.option_name, 'delete'); END", 10),
        ],
    );

    $catalog->attach('site', '/srv/site.sqlite', [
        $record('table', 'wp_options', 'wp_options', 20, 'CREATE TABLE site.wp_options(blog_id integer, option_name text COLLATE NOCASE, option_value text COLLATE BINARY)', 11),
        $record('table', 'wp_option_audit', 'wp_option_audit', 21, 'CREATE TABLE site.wp_option_audit(blog_id integer, option_name text COLLATE NOCASE, source text COLLATE RTRIM)', 12),
        $record('view', 'active_options', 'active_options', 0, 'CREATE VIEW site.active_options(blog_id, option_name, option_value) AS SELECT blog_id, option_name COLLATE NOCASE, option_value FROM wp_options', 13),
        $record('trigger', 'site_active_insert_collate', 'active_options', 0, "CREATE TRIGGER site_active_insert_collate INSTEAD OF INSERT ON active_options BEGIN INSERT INTO wp_options(blog_id, option_name, option_value) VALUES(new.blog_id, new.option_name, new.option_value); INSERT INTO site.wp_option_audit(blog_id, option_name, source) VALUES(new.blog_id, new.option_name, 'site'); SELECT new.option_name COLLATE NOCASE, new.option_value; END", 14),
    ]);

    $catalog->attach('archive', '/srv/archive.sqlite', [
        $record('table', 'wp_options_archive', 'wp_options_archive', 30, 'CREATE TABLE archive.wp_options_archive(option_id integer, option_name text COLLATE RTRIM, archived_at text COLLATE BINARY)', 15),
        $record('trigger', 'archive_cleanup_collate', 'wp_options_archive', 0, "CREATE TRIGGER archive_cleanup_collate AFTER DELETE ON wp_options_archive BEGIN SELECT old.option_name COLLATE RTRIM, old.archived_at; END", 16),
    ]);

    return $catalog;
};

$plan = static fn (string $name): array => SQLiteAttachTempViewCollationPlan::forTrigger($catalog(), $name);
$summary = static fn (): array => SQLiteAttachTempViewCollationPlan::summary($catalog());

return [
    'attach temp view collation current next33 main trigger schema' => static fn (TestRunner $t) => $t->same('main', $plan('active_options_insert_collate')['triggerSchema']),
    'attach temp view collation current next33 main target view' => static fn (TestRunner $t) => $t->same('active_options', $plan('active_options_insert_collate')['target']),
    'attach temp view collation current next33 main target schema' => static fn (TestRunner $t) => $t->same('main', $plan('active_options_insert_collate')['targetSchema']),
    'attach temp view collation current next33 main target type' => static fn (TestRunner $t) => $t->same('view', $plan('active_options_insert_collate')['targetType']),
    'attach temp view collation current next33 main view option id binary' => static fn (TestRunner $t) => $t->same('BINARY', $plan('active_options_insert_collate')['targetCollations']['option_id']),
    'attach temp view collation current next33 main view option name nocase' => static fn (TestRunner $t) => $t->same('NOCASE', $plan('active_options_insert_collate')['targetCollations']['option_name']),
    'attach temp view collation current next33 main view value rtrim' => static fn (TestRunner $t) => $t->same('RTRIM', $plan('active_options_insert_collate')['targetCollations']['option_value']),
    'attach temp view collation current next33 main first body kind' => static fn (TestRunner $t) => $t->same('insert', $plan('active_options_insert_collate')['body'][0]['kind']),
    'attach temp view collation current next33 main first body schema' => static fn (TestRunner $t) => $t->same('main', $plan('active_options_insert_collate')['body'][0]['schema']),
    'attach temp view collation current next33 main first body option name nocase' => static fn (TestRunner $t) => $t->same('NOCASE', $plan('active_options_insert_collate')['body'][0]['collations']['option_name']),
    'attach temp view collation current next33 main first body value rtrim' => static fn (TestRunner $t) => $t->same('RTRIM', $plan('active_options_insert_collate')['body'][0]['collations']['option_value']),
    'attach temp view collation current next33 main audit body rtrim' => static fn (TestRunner $t) => $t->same('RTRIM', $plan('active_options_insert_collate')['body'][1]['collations']['audit_name']),
    'attach temp view collation current next33 main audit body nocase' => static fn (TestRunner $t) => $t->same('NOCASE', $plan('active_options_insert_collate')['body'][1]['collations']['option_name']),
    'attach temp view collation current next33 main select first nocase' => static fn (TestRunner $t) => $t->same('NOCASE', $plan('active_options_insert_collate')['selectCollations'][0]['collation']),
    'attach temp view collation current next33 main select second rtrim' => static fn (TestRunner $t) => $t->same('RTRIM', $plan('active_options_insert_collate')['selectCollations'][1]['collation']),
    'attach temp view collation current next33 main body by schema tables' => static fn (TestRunner $t) => $t->same(['wp_option_audit', 'wp_options'], array_keys($plan('active_options_insert_collate')['bodyCollationsBySchema']['main'])),

    'attach temp view collation current next33 temp trigger schema' => static fn (TestRunner $t) => $t->same('temp', $plan('temp_active_insert_collate')['triggerSchema']),
    'attach temp view collation current next33 temp target schema' => static fn (TestRunner $t) => $t->same('temp', $plan('temp_active_insert_collate')['targetSchema']),
    'attach temp view collation current next33 temp view temp name rtrim' => static fn (TestRunner $t) => $t->same('RTRIM', $plan('temp_active_insert_collate')['targetCollations']['temp_name']),
    'attach temp view collation current next33 temp view value nocase' => static fn (TestRunner $t) => $t->same('NOCASE', $plan('temp_active_insert_collate')['targetCollations']['option_value']),
    'attach temp view collation current next33 temp unqualified body stays temp' => static fn (TestRunner $t) => $t->same('temp', $plan('temp_active_insert_collate')['body'][0]['schema']),
    'attach temp view collation current next33 temp body temp name rtrim' => static fn (TestRunner $t) => $t->same('RTRIM', $plan('temp_active_insert_collate')['body'][0]['collations']['temp_name']),
    'attach temp view collation current next33 temp body value nocase' => static fn (TestRunner $t) => $t->same('NOCASE', $plan('temp_active_insert_collate')['body'][0]['collations']['option_value']),
    'attach temp view collation current next33 temp audit shadow rtrim' => static fn (TestRunner $t) => $t->same('RTRIM', $plan('temp_active_insert_collate')['body'][1]['collations']['temp_name']),
    'attach temp view collation current next33 temp select first rtrim' => static fn (TestRunner $t) => $t->same('RTRIM', $plan('temp_active_insert_collate')['selectCollations'][0]['collation']),
    'attach temp view collation current next33 temp select second nocase' => static fn (TestRunner $t) => $t->same('NOCASE', $plan('temp_active_insert_collate')['selectCollations'][1]['collation']),

    'attach temp view collation current next33 temp pinned main target' => static fn (TestRunner $t) => $t->same('main', $plan('temp_main_delete_collate')['targetSchema']),
    'attach temp view collation current next33 temp pinned main target nocase' => static fn (TestRunner $t) => $t->same('NOCASE', $plan('temp_main_delete_collate')['targetCollations']['option_name']),
    'attach temp view collation current next33 temp delete uses temp audit shadow' => static fn (TestRunner $t) => $t->same('temp', $plan('temp_main_delete_collate')['body'][0]['schema']),
    'attach temp view collation current next33 temp delete where rtrim' => static fn (TestRunner $t) => $t->same('RTRIM', $plan('temp_main_delete_collate')['body'][0]['whereCollations'][0]['collation']),
    'attach temp view collation current next33 temp qualified insert uses main' => static fn (TestRunner $t) => $t->same('main', $plan('temp_main_delete_collate')['body'][1]['schema']),
    'attach temp view collation current next33 temp qualified insert audit rtrim' => static fn (TestRunner $t) => $t->same('RTRIM', $plan('temp_main_delete_collate')['body'][1]['collations']['audit_name']),
    'attach temp view collation current next33 temp qualified insert option nocase' => static fn (TestRunner $t) => $t->same('NOCASE', $plan('temp_main_delete_collate')['body'][1]['collations']['option_name']),

    'attach temp view collation current next33 attached trigger schema' => static fn (TestRunner $t) => $t->same('site', $plan('site.site_active_insert_collate')['triggerSchema']),
    'attach temp view collation current next33 attached target schema' => static fn (TestRunner $t) => $t->same('site', $plan('site.site_active_insert_collate')['targetSchema']),
    'attach temp view collation current next33 attached view name nocase' => static fn (TestRunner $t) => $t->same('NOCASE', $plan('site.site_active_insert_collate')['targetCollations']['option_name']),
    'attach temp view collation current next33 attached view value binary' => static fn (TestRunner $t) => $t->same('BINARY', $plan('site.site_active_insert_collate')['targetCollations']['option_value']),
    'attach temp view collation current next33 attached unqualified body stays site' => static fn (TestRunner $t) => $t->same('site', $plan('site.site_active_insert_collate')['body'][0]['schema']),
    'attach temp view collation current next33 attached body option nocase' => static fn (TestRunner $t) => $t->same('NOCASE', $plan('site.site_active_insert_collate')['body'][0]['collations']['option_name']),
    'attach temp view collation current next33 attached qualified audit source rtrim' => static fn (TestRunner $t) => $t->same('RTRIM', $plan('site.site_active_insert_collate')['body'][1]['collations']['source']),
    'attach temp view collation current next33 attached select nocase' => static fn (TestRunner $t) => $t->same('NOCASE', $plan('site.site_active_insert_collate')['selectCollations'][0]['collation']),
    'attach temp view collation current next33 attached select binary fallback' => static fn (TestRunner $t) => $t->same('BINARY', $plan('site.site_active_insert_collate')['selectCollations'][1]['collation']),

    'attach temp view collation current next33 update target table' => static fn (TestRunner $t) => $t->same('wp_options', $plan('options_update_collate')['target']),
    'attach temp view collation current next33 update target option nocase' => static fn (TestRunner $t) => $t->same('NOCASE', $plan('options_update_collate')['targetCollations']['option_name']),
    'attach temp view collation current next33 update body kind' => static fn (TestRunner $t) => $t->same('update', $plan('options_update_collate')['body'][0]['kind']),
    'attach temp view collation current next33 update set rtrim audit name' => static fn (TestRunner $t) => $t->same('RTRIM', $plan('options_update_collate')['body'][0]['collations']['audit_name']),
    'attach temp view collation current next33 update where nocase' => static fn (TestRunner $t) => $t->same('NOCASE', $plan('options_update_collate')['body'][0]['whereCollations'][0]['collation']),
    'attach temp view collation current next33 update select nocase' => static fn (TestRunner $t) => $t->same('NOCASE', $plan('options_update_collate')['selectCollations'][0]['collation']),

    'attach temp view collation current next33 archive target rtrim' => static fn (TestRunner $t) => $t->same('RTRIM', $plan('archive.archive_cleanup_collate')['targetCollations']['option_name']),
    'attach temp view collation current next33 archive target binary date' => static fn (TestRunner $t) => $t->same('BINARY', $plan('archive.archive_cleanup_collate')['targetCollations']['archived_at']),
    'attach temp view collation current next33 archive select rtrim' => static fn (TestRunner $t) => $t->same('RTRIM', $plan('archive.archive_cleanup_collate')['selectCollations'][0]['collation']),
    'attach temp view collation current next33 archive select binary fallback' => static fn (TestRunner $t) => $t->same('BINARY', $plan('archive.archive_cleanup_collate')['selectCollations'][1]['collation']),

    'attach temp view collation current next33 summary main trigger count' => static fn (TestRunner $t) => $t->same(2, $summary()['main']['triggers']),
    'attach temp view collation current next33 summary temp trigger count' => static fn (TestRunner $t) => $t->same(2, $summary()['temp']['triggers']),
    'attach temp view collation current next33 summary site trigger count' => static fn (TestRunner $t) => $t->same(1, $summary()['site']['triggers']),
    'attach temp view collation current next33 summary archive trigger count' => static fn (TestRunner $t) => $t->same(1, $summary()['archive']['triggers']),
    'attach temp view collation current next33 summary main target counts' => static fn (TestRunner $t) => $t->same(['BINARY' => 3, 'NOCASE' => 2, 'RTRIM' => 2], $summary()['main']['targetCollations']),
    'attach temp view collation current next33 summary temp body counts' => static fn (TestRunner $t) => $t->same(['BINARY' => 7, 'NOCASE' => 2, 'RTRIM' => 4], $summary()['temp']['bodyCollations']),
    'attach temp view collation current next33 summary temp select counts' => static fn (TestRunner $t) => $t->same(['NOCASE' => 1, 'RTRIM' => 1], $summary()['temp']['selectCollations']),
    'attach temp view collation current next33 summary site body counts' => static fn (TestRunner $t) => $t->same(['BINARY' => 3, 'NOCASE' => 2, 'RTRIM' => 1], $summary()['site']['bodyCollations']),
    'attach temp view collation current next33 summary archive select counts' => static fn (TestRunner $t) => $t->same(['BINARY' => 1, 'RTRIM' => 1], $summary()['archive']['selectCollations']),
    'attach temp view collation current next33 missing trigger throws' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => $plan('missing_collation_trigger')),
    'attach temp view collation current next33 missing view source throws' => static function (TestRunner $t) use ($record): void {
        $broken = new SQLiteAttachedSchemaCatalog([
            $record('view', 'broken_view', 'broken_view', 0, 'CREATE VIEW broken_view(name) AS SELECT name COLLATE NOCASE FROM missing_source', 1),
            $record('trigger', 'broken_trigger', 'broken_view', 0, 'CREATE TRIGGER broken_trigger INSTEAD OF INSERT ON broken_view BEGIN SELECT new.name COLLATE NOCASE; END', 2),
        ]);

        $t->throws(InvalidArgumentException::class, static fn () => SQLiteAttachTempViewCollationPlan::forTrigger($broken, 'broken_trigger'));
    },
];
