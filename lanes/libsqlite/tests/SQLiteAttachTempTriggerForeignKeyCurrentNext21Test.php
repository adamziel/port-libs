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
            $record('table', 'wp_sites', 'wp_sites', 2, 'CREATE TABLE wp_sites(blog_id integer primary key, domain text)', 1),
            $record('table', 'wp_options', 'wp_options', 3, 'CREATE TABLE wp_options(option_id integer primary key, blog_id integer REFERENCES wp_sites(blog_id) ON UPDATE CASCADE ON DELETE RESTRICT DEFERRABLE INITIALLY DEFERRED, option_name text unique, option_value text)', 2),
            $record('table', 'wp_option_audit', 'wp_option_audit', 4, 'CREATE TABLE wp_option_audit(audit_id integer primary key, option_id integer, blog_id integer, FOREIGN KEY(blog_id) REFERENCES wp_sites(blog_id) ON DELETE SET NULL)', 3),
            $record('trigger', 'main_option_insert_audit', 'wp_options', 0, 'CREATE TRIGGER main_option_insert_audit AFTER INSERT ON wp_options BEGIN INSERT INTO wp_option_audit(option_id, blog_id) VALUES(new.option_id, new.blog_id); END', 4),
            $record('trigger', 'main_option_delete_audit', 'wp_options', 0, 'CREATE TRIGGER main_option_delete_audit AFTER DELETE ON wp_options BEGIN DELETE FROM wp_option_audit WHERE option_id = old.option_id; END', 5),
        ],
        [
            $record('table', 'wp_sites', 'wp_sites', 10, 'CREATE TEMP TABLE wp_sites(blog_id integer primary key, domain text)', 6),
            $record('table', 'wp_options', 'wp_options', 11, 'CREATE TEMP TABLE wp_options(option_id integer primary key, blog_id integer REFERENCES wp_sites(blog_id) ON DELETE CASCADE, option_name text)', 7),
            $record('table', 'wp_option_audit', 'wp_option_audit', 12, 'CREATE TEMP TABLE wp_option_audit(audit_id integer primary key, option_id integer, blog_id integer REFERENCES wp_sites(blog_id) ON UPDATE SET DEFAULT)', 8),
            $record('trigger', 'temp_option_insert_audit', 'wp_options', null, 'CREATE TEMP TRIGGER temp_option_insert_audit AFTER INSERT ON wp_options BEGIN INSERT INTO wp_option_audit(option_id, blog_id) VALUES(new.option_id, new.blog_id); END', 9),
            $record('trigger', 'temp_main_option_insert_audit', 'wp_options', null, 'CREATE TEMP TRIGGER temp_main_option_insert_audit AFTER INSERT ON main.wp_options BEGIN INSERT INTO wp_option_audit(option_id, blog_id) VALUES(new.option_id, new.blog_id); END', 10),
        ],
    );

    $catalog->attach('site', '/srv/www/site.sqlite', [
        $record('table', 'wp_sites', 'wp_sites', 20, 'CREATE TABLE site.wp_sites(blog_id integer primary key, domain text)', 11),
        $record('table', 'wp_options', 'wp_options', 21, 'CREATE TABLE site.wp_options(option_id integer primary key, blog_id integer, option_name text, FOREIGN KEY(blog_id) REFERENCES wp_sites(blog_id) ON UPDATE SET NULL ON DELETE CASCADE)', 12),
        $record('table', 'wp_option_audit', 'wp_option_audit', 22, 'CREATE TABLE site.wp_option_audit(audit_id integer primary key, option_id integer REFERENCES wp_options(option_id) ON DELETE CASCADE, blog_id integer)', 13),
        $record('trigger', 'site_option_insert_audit', 'wp_options', 0, 'CREATE TRIGGER site_option_insert_audit AFTER INSERT ON wp_options BEGIN INSERT INTO site.wp_option_audit(option_id, blog_id) VALUES(new.option_id, new.blog_id); END', 14),
        $record('trigger', 'site_option_cleanup', 'wp_options', 0, 'CREATE TRIGGER site_option_cleanup AFTER DELETE ON wp_options BEGIN DELETE FROM site.wp_option_audit WHERE option_id = old.option_id; END', 15),
    ]);

    $catalog->attach('archive', '/srv/www/archive.sqlite', [
        $record('table', 'wp_sites', 'wp_sites', 30, 'CREATE TABLE archive.wp_sites(blog_id integer primary key)', 16),
        $record('table', 'wp_options', 'wp_options', 31, 'CREATE TABLE archive.wp_options(option_id integer primary key, blog_id integer REFERENCES wp_sites(blog_id) ON DELETE SET DEFAULT, option_name text)', 17),
        $record('trigger', 'archive_option_insert', 'wp_options', 0, 'CREATE TRIGGER archive_option_insert AFTER INSERT ON wp_options BEGIN SELECT new.option_id, new.blog_id; END', 18),
    ]);

    return $catalog;
};

$context = static fn (string $name): array => SQLiteAttachTempViewTriggerResolution::foreignKeyContext($catalog(), $name);
$valueAt = static function (array $value, string $path): mixed {
    foreach (explode('.', $path) as $part) {
        if ($part === 'count') {
            $value = count($value);
            continue;
        }
        $value = $value[$part];
    }

    return $value;
};

$cases = [
    'main trigger target child schema' => ['main_option_insert_audit', 'targetForeignKeys.0.childSchema', 'main'],
    'main trigger target child table' => ['main_option_insert_audit', 'targetForeignKeys.0.childTable', 'wp_options'],
    'main trigger target child columns' => ['main_option_insert_audit', 'targetForeignKeys.0.childColumns', ['blog_id']],
    'main trigger target parent schema remains main' => ['main_option_insert_audit', 'targetForeignKeys.0.parentSchema', 'main'],
    'main trigger target parent table' => ['main_option_insert_audit', 'targetForeignKeys.0.parentTable', 'wp_sites'],
    'main trigger target parent columns' => ['main_option_insert_audit', 'targetForeignKeys.0.parentColumns', ['blog_id']],
    'main trigger target on update cascade' => ['main_option_insert_audit', 'targetForeignKeys.0.onUpdate', 'CASCADE'],
    'main trigger target on delete restrict' => ['main_option_insert_audit', 'targetForeignKeys.0.onDelete', 'RESTRICT'],
    'main trigger target deferred' => ['main_option_insert_audit', 'targetForeignKeys.0.deferred', true],
    'main trigger body has audit fk' => ['main_option_insert_audit', 'bodyForeignKeys.count', 1],
    'main trigger body fk child table' => ['main_option_insert_audit', 'bodyForeignKeys.0.childTable', 'wp_option_audit'],
    'main trigger body fk parent schema' => ['main_option_insert_audit', 'bodyForeignKeys.0.parentSchema', 'main'],
    'main trigger body fk delete action' => ['main_option_insert_audit', 'bodyForeignKeys.0.onDelete', 'SET NULL'],
    'main trigger schemas only main' => ['main_option_insert_audit', 'foreignKeySchemas', ['main']],
    'main trigger resolved status' => ['main_option_insert_audit', 'status', 'resolved'],

    'temp trigger chooses temp target fk' => ['temp_option_insert_audit', 'targetForeignKeys.0.childSchema', 'temp'],
    'temp trigger target parent schema temp' => ['temp_option_insert_audit', 'targetForeignKeys.0.parentSchema', 'temp'],
    'temp trigger target delete cascade' => ['temp_option_insert_audit', 'targetForeignKeys.0.onDelete', 'CASCADE'],
    'temp trigger target default update action' => ['temp_option_insert_audit', 'targetForeignKeys.0.onUpdate', 'NO ACTION'],
    'temp trigger body resolves temp audit' => ['temp_option_insert_audit', 'bodyForeignKeys.0.childSchema', 'temp'],
    'temp trigger body parent schema temp' => ['temp_option_insert_audit', 'bodyForeignKeys.0.parentSchema', 'temp'],
    'temp trigger body update set default' => ['temp_option_insert_audit', 'bodyForeignKeys.0.onUpdate', 'SET DEFAULT'],
    'temp trigger schemas only temp' => ['temp_option_insert_audit', 'foreignKeySchemas', ['temp']],
    'temp trigger resolved status' => ['temp_option_insert_audit', 'status', 'resolved'],

    'temp trigger pinned main target has main fk' => ['temp_main_option_insert_audit', 'targetForeignKeys.0.childSchema', 'main'],
    'temp trigger pinned main parent remains main' => ['temp_main_option_insert_audit', 'targetForeignKeys.0.parentSchema', 'main'],
    'temp trigger pinned main body still resolves temp audit' => ['temp_main_option_insert_audit', 'bodyForeignKeys.0.childSchema', 'temp'],
    'temp trigger pinned main body parent temp' => ['temp_main_option_insert_audit', 'bodyForeignKeys.0.parentSchema', 'temp'],
    'temp trigger pinned schemas include main and temp' => ['temp_main_option_insert_audit', 'foreignKeySchemas', ['main', 'temp']],
    'temp trigger pinned status resolved' => ['temp_main_option_insert_audit', 'status', 'resolved'],

    'site trigger target schema site' => ['site.site_option_insert_audit', 'targetForeignKeys.0.childSchema', 'site'],
    'site trigger target table site options' => ['site.site_option_insert_audit', 'targetForeignKeys.0.childTable', 'wp_options'],
    'site trigger table fk child column' => ['site.site_option_insert_audit', 'targetForeignKeys.0.childColumns', ['blog_id']],
    'site trigger table fk parent schema site' => ['site.site_option_insert_audit', 'targetForeignKeys.0.parentSchema', 'site'],
    'site trigger table fk parent table' => ['site.site_option_insert_audit', 'targetForeignKeys.0.parentTable', 'wp_sites'],
    'site trigger table fk update set null' => ['site.site_option_insert_audit', 'targetForeignKeys.0.onUpdate', 'SET NULL'],
    'site trigger table fk delete cascade' => ['site.site_option_insert_audit', 'targetForeignKeys.0.onDelete', 'CASCADE'],
    'site trigger body fk child schema site' => ['site.site_option_insert_audit', 'bodyForeignKeys.0.childSchema', 'site'],
    'site trigger body fk parent schema site' => ['site.site_option_insert_audit', 'bodyForeignKeys.0.parentSchema', 'site'],
    'site trigger schemas only site' => ['site.site_option_insert_audit', 'foreignKeySchemas', ['site']],
    'site trigger resolved status' => ['site.site_option_insert_audit', 'status', 'resolved'],

    'archive trigger target schema archive' => ['archive.archive_option_insert', 'targetForeignKeys.0.childSchema', 'archive'],
    'archive trigger parent schema archive' => ['archive.archive_option_insert', 'targetForeignKeys.0.parentSchema', 'archive'],
    'archive trigger delete set default' => ['archive.archive_option_insert', 'targetForeignKeys.0.onDelete', 'SET DEFAULT'],
    'archive trigger has no body fks' => ['archive.archive_option_insert', 'bodyForeignKeys.count', 0],
    'archive trigger schemas archive' => ['archive.archive_option_insert', 'foreignKeySchemas', ['archive']],
    'archive trigger resolved status' => ['archive.archive_option_insert', 'status', 'resolved'],

    'delete trigger target fk reused' => ['main_option_delete_audit', 'targetForeignKeys.0.parentTable', 'wp_sites'],
    'delete trigger body delete dependency resolves audit fk' => ['main_option_delete_audit', 'bodyForeignKeys.0.childTable', 'wp_option_audit'],
    'delete trigger body delete parent schema main' => ['main_option_delete_audit', 'bodyForeignKeys.0.parentSchema', 'main'],
    'site delete trigger body delete dependency resolves site audit fk' => ['site.site_option_cleanup', 'bodyForeignKeys.0.childSchema', 'site'],
    'site delete trigger body delete parent table options' => ['site.site_option_cleanup', 'bodyForeignKeys.0.parentTable', 'wp_options'],
    'site delete trigger status resolved' => ['site.site_option_cleanup', 'status', 'resolved'],
];

$tests = [];
foreach ($cases as $name => [$trigger, $path, $expected]) {
    $tests['attach temp trigger foreign key current next21 ' . $name] = static function (TestRunner $t) use ($context, $valueAt, $trigger, $path, $expected): void {
        $t->same($expected, $valueAt($context($trigger), $path));
    };
}

$tests['attach temp trigger foreign key current next21 cross schema reference is flagged'] = static function (TestRunner $t) use ($record): void {
    $catalog = new SQLiteAttachedSchemaCatalog([
        $record('table', 'wp_sites', 'wp_sites', 2, 'CREATE TABLE wp_sites(blog_id integer primary key)', 1),
        $record('table', 'wp_options', 'wp_options', 3, 'CREATE TABLE wp_options(blog_id integer REFERENCES site.wp_sites(blog_id))', 2),
        $record('trigger', 'bad_cross', 'wp_options', 0, 'CREATE TRIGGER bad_cross AFTER INSERT ON wp_options BEGIN SELECT new.blog_id; END', 3),
    ]);
    $catalog->attach('site', '/srv/www/site.sqlite', [
        $record('table', 'wp_sites', 'wp_sites', 20, 'CREATE TABLE site.wp_sites(blog_id integer primary key)', 4),
    ]);

    $context = SQLiteAttachTempViewTriggerResolution::foreignKeyContext($catalog, 'bad_cross');
    $t->same('unresolved', $context['status']);
};

$tests['attach temp trigger foreign key current next21 cross schema evidence preserves parent schema'] = static function (TestRunner $t) use ($record): void {
    $catalog = new SQLiteAttachedSchemaCatalog([
        $record('table', 'wp_options', 'wp_options', 3, 'CREATE TABLE wp_options(blog_id integer REFERENCES site.wp_sites(blog_id))', 1),
        $record('trigger', 'bad_cross', 'wp_options', 0, 'CREATE TRIGGER bad_cross AFTER INSERT ON wp_options BEGIN SELECT new.blog_id; END', 2),
    ]);
    $catalog->attach('site', '/srv/www/site.sqlite', [
        $record('table', 'wp_sites', 'wp_sites', 20, 'CREATE TABLE site.wp_sites(blog_id integer primary key)', 3),
    ]);

    $context = SQLiteAttachTempViewTriggerResolution::foreignKeyContext($catalog, 'bad_cross');
    $t->same('site', $context['crossSchemaReferences'][0]['parentSchema']);
};

$tests['attach temp trigger foreign key current next21 view targets report no target fks'] = static function (TestRunner $t) use ($record): void {
    $catalog = new SQLiteAttachedSchemaCatalog([
        $record('view', 'active_options', 'active_options', 0, 'CREATE VIEW active_options(option_id) AS SELECT 1 AS option_id', 1),
        $record('trigger', 'active_insert', 'active_options', 0, 'CREATE TRIGGER active_insert INSTEAD OF INSERT ON active_options BEGIN SELECT new.option_id; END', 2),
    ]);

    $t->same([], SQLiteAttachTempViewTriggerResolution::foreignKeyContext($catalog, 'active_insert')['targetForeignKeys']);
};

return $tests;
