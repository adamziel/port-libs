<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteAttachedSchemaCatalog;
use PortLibs\LibSqlite\SQLiteAttachTempViewTriggerResolution;
use PortLibs\LibSqlite\SQLiteSchemaRecord;

$record = static fn (string $type, string $name, string $table, ?int $root, string $sql, int $rowid): SQLiteSchemaRecord => new SQLiteSchemaRecord(
    $type,
    $name,
    $table,
    $root,
    $sql,
    $rowid,
);

$baseCatalog = static function () use ($record): SQLiteAttachedSchemaCatalog {
    $catalog = new SQLiteAttachedSchemaCatalog(
        [
            $record('table', 'wp_options', 'wp_options', 2, 'CREATE TABLE wp_options(option_id integer primary key, option_name text, option_value text, autoload text)', 1),
            $record('table', 'wp_option_audit', 'wp_option_audit', 3, 'CREATE TABLE wp_option_audit(option_id integer, option_name text, source text)', 2),
            $record('view', 'active_options', 'active_options', 0, 'CREATE VIEW active_options(option_id, option_name, option_value, autoload) AS SELECT option_id, option_name, option_value, autoload FROM wp_options WHERE autoload = "yes"', 3),
            $record('trigger', 'active_options_insert', 'active_options', 0, 'CREATE TRIGGER active_options_insert INSTEAD OF INSERT ON active_options BEGIN INSERT INTO wp_options(option_id, option_name, option_value, autoload) VALUES(new.option_id, new.option_name, new.option_value, new.autoload); INSERT INTO wp_option_audit(option_id, option_name, source) VALUES(new.option_id, new.option_name, "main"); END', 4),
        ],
        [
            $record('table', 'wp_options', 'wp_options', 10, 'CREATE TEMP TABLE wp_options(option_id integer, temp_name text, option_value text, autoload text)', 5),
            $record('view', 'active_options', 'active_options', null, 'CREATE TEMP VIEW active_options(option_id, temp_name, option_value, autoload) AS SELECT option_id, temp_name, option_value, autoload FROM temp.wp_options', 6),
            $record('trigger', 'temp_active_options_insert', 'active_options', null, 'CREATE TEMP TRIGGER temp_active_options_insert INSTEAD OF INSERT ON active_options BEGIN INSERT INTO wp_options(option_id, temp_name, option_value, autoload) VALUES(new.option_id, new.temp_name, new.option_value, new.autoload); END', 7),
        ],
    );
    $catalog->attach('archive', '/srv/archive.sqlite', [
        $record('table', 'wp_options', 'wp_options', 20, 'CREATE TABLE archive.wp_options(blog_id integer, option_name text, option_value text, autoload text)', 8),
        $record('table', 'wp_option_audit', 'wp_option_audit', 21, 'CREATE TABLE archive.wp_option_audit(blog_id integer, option_name text, source text)', 9),
        $record('view', 'active_options', 'active_options', 0, 'CREATE VIEW archive.active_options(blog_id, option_name, option_value, autoload) AS SELECT blog_id, option_name, option_value, autoload FROM wp_options', 10),
        $record('trigger', 'archive_active_options_insert', 'active_options', 0, 'CREATE TRIGGER archive_active_options_insert INSTEAD OF INSERT ON active_options BEGIN INSERT INTO archive.wp_options(blog_id, option_name, option_value, autoload) VALUES(new.blog_id, new.option_name, new.option_value, new.autoload); INSERT INTO archive.wp_option_audit(blog_id, option_name, source) VALUES(new.blog_id, new.option_name, "archive"); END', 11),
    ]);

    return $catalog;
};

$nextWithoutMainTrigger = static fn () => new SQLiteAttachedSchemaCatalog(
    [
        $record('table', 'wp_options', 'wp_options', 2, 'CREATE TABLE wp_options(option_id integer primary key, option_name text, option_value text, autoload text)', 1),
        $record('table', 'wp_option_audit', 'wp_option_audit', 3, 'CREATE TABLE wp_option_audit(option_id integer, option_name text, source text)', 2),
        $record('view', 'active_options', 'active_options', 0, 'CREATE VIEW active_options(option_id, option_name, option_value, autoload) AS SELECT option_id, option_name, option_value, autoload FROM wp_options WHERE autoload = "yes"', 3),
    ],
    [],
);

$nextWithoutTempTrigger = static fn () => new SQLiteAttachedSchemaCatalog(
    [],
    [
        $record('table', 'wp_options', 'wp_options', 10, 'CREATE TEMP TABLE wp_options(option_id integer, temp_name text, option_value text, autoload text)', 5),
        $record('view', 'active_options', 'active_options', null, 'CREATE TEMP VIEW active_options(option_id, temp_name, option_value, autoload) AS SELECT option_id, temp_name, option_value, autoload FROM temp.wp_options', 6),
    ],
);

$nextWithoutMainView = static fn () => new SQLiteAttachedSchemaCatalog(
    [
        $record('table', 'wp_options', 'wp_options', 2, 'CREATE TABLE wp_options(option_id integer primary key, option_name text, option_value text, autoload text)', 1),
        $record('table', 'wp_option_audit', 'wp_option_audit', 3, 'CREATE TABLE wp_option_audit(option_id integer, option_name text, source text)', 2),
        $record('trigger', 'active_options_insert', 'active_options', 0, 'CREATE TRIGGER active_options_insert INSTEAD OF INSERT ON active_options BEGIN INSERT INTO wp_options(option_id, option_name, option_value, autoload) VALUES(new.option_id, new.option_name, new.option_value, new.autoload); END', 4),
    ],
    [],
);

$nextWithoutArchive = static function () use ($record): SQLiteAttachedSchemaCatalog {
    $catalog = new SQLiteAttachedSchemaCatalog([
        $record('table', 'wp_options', 'wp_options', 2, 'CREATE TABLE wp_options(option_id integer primary key, option_name text, option_value text, autoload text)', 1),
        $record('view', 'active_options', 'active_options', 0, 'CREATE VIEW active_options(option_id, option_name, option_value, autoload) AS SELECT option_id, option_name, option_value, autoload FROM wp_options', 2),
        $record('trigger', 'active_options_insert', 'active_options', 0, 'CREATE TRIGGER active_options_insert INSTEAD OF INSERT ON active_options BEGIN INSERT INTO wp_options(option_id, option_name, option_value, autoload) VALUES(new.option_id, new.option_name, new.option_value, new.autoload); END', 3),
    ]);

    return $catalog;
};

$current = $baseCatalog();
$mainDropped = static fn (): array => SQLiteAttachTempViewTriggerResolution::currentNextSourcePlan($current, $nextWithoutMainTrigger(), 'main.active_options_insert');
$tempDropped = static fn (): array => SQLiteAttachTempViewTriggerResolution::currentNextSourcePlan($current, $nextWithoutTempTrigger(), 'temp.temp_active_options_insert');
$targetDropped = static fn (): array => SQLiteAttachTempViewTriggerResolution::currentNextSourcePlan($current, $nextWithoutMainView(), 'main.active_options_insert');
$archiveDetached = static fn (): array => SQLiteAttachTempViewTriggerResolution::currentNextSourcePlan($current, $nextWithoutArchive(), 'archive.archive_active_options_insert');

return [
    'attach wal temp view trigger current source next101 main dropped status' => static fn (TestRunner $t) => $t->same('reprepare-required', $mainDropped()['status']),
    'attach wal temp view trigger current source next101 main current step ok' => static fn (TestRunner $t) => $t->same('SQLITE_OK', $mainDropped()['sqliteResultOnCurrentStep']),
    'attach wal temp view trigger current source next101 main next abort reprepare' => static fn (TestRunner $t) => $t->same('abort-reset-and-reprepare', $mainDropped()['nextStepAction']),
    'attach wal temp view trigger current source next101 main exists current' => static fn (TestRunner $t) => $t->same(true, $mainDropped()['current']['exists']),
    'attach wal temp view trigger current source next101 main missing next' => static fn (TestRunner $t) => $t->same(false, $mainDropped()['next']['exists']),
    'attach wal temp view trigger current source next101 main missing status' => static fn (TestRunner $t) => $t->same('missing', $mainDropped()['next']['status']),
    'attach wal temp view trigger current source next101 main changed exists' => static fn (TestRunner $t) => $t->same(true, in_array('exists', $mainDropped()['changedFields'], true)),
    'attach wal temp view trigger current source next101 main changed target' => static fn (TestRunner $t) => $t->same(true, in_array('target', $mainDropped()['changedFields'], true)),
    'attach wal temp view trigger current source next101 main invalidates main' => static fn (TestRunner $t) => $t->same(['main'], $mainDropped()['invalidatedSources']),
    'attach wal temp view trigger current source next101 main wal schema retained' => static fn (TestRunner $t) => $t->same(['main'], $mainDropped()['walSchemas']),
    'attach wal temp view trigger current source next101 main no temp schema' => static fn (TestRunner $t) => $t->same([], $mainDropped()['tempSchemas']),
    'attach wal temp view trigger current source next101 main missing reason names trigger' => static fn (TestRunner $t) => $t->same(true, str_contains($mainDropped()['next']['missingReason'], 'active_options_insert')),

    'attach wal temp view trigger current source next101 temp dropped status' => static fn (TestRunner $t) => $t->same('reprepare-required', $tempDropped()['status']),
    'attach wal temp view trigger current source next101 temp current temporary' => static fn (TestRunner $t) => $t->same(true, $tempDropped()['current']['triggerTemporary']),
    'attach wal temp view trigger current source next101 temp next missing' => static fn (TestRunner $t) => $t->same(false, $tempDropped()['next']['exists']),
    'attach wal temp view trigger current source next101 temp invalidates temp' => static fn (TestRunner $t) => $t->same(['temp'], $tempDropped()['invalidatedSources']),
    'attach wal temp view trigger current source next101 temp temp schema only' => static fn (TestRunner $t) => $t->same(['temp'], $tempDropped()['tempSchemas']),
    'attach wal temp view trigger current source next101 temp no wal schema' => static fn (TestRunner $t) => $t->same([], $tempDropped()['walSchemas']),
    'attach wal temp view trigger current source next101 temp current step ok' => static fn (TestRunner $t) => $t->same('SQLITE_OK', $tempDropped()['sqliteResultOnCurrentStep']),
    'attach wal temp view trigger current source next101 temp next action' => static fn (TestRunner $t) => $t->same('abort-reset-and-reprepare', $tempDropped()['nextStepAction']),
    'attach wal temp view trigger current source next101 temp current target temp' => static fn (TestRunner $t) => $t->same('temp', $tempDropped()['current']['targetSchema']),
    'attach wal temp view trigger current source next101 temp missing schema temp' => static fn (TestRunner $t) => $t->same('temp', $tempDropped()['next']['missingSchema']),

    'attach wal temp view trigger current source next101 target dropped status' => static fn (TestRunner $t) => $t->same('reprepare-required', $targetDropped()['status']),
    'attach wal temp view trigger current source next101 target current trigger exists' => static fn (TestRunner $t) => $t->same(true, $targetDropped()['current']['exists']),
    'attach wal temp view trigger current source next101 target next missing' => static fn (TestRunner $t) => $t->same(false, $targetDropped()['next']['exists']),
    'attach wal temp view trigger current source next101 target missing reason target' => static fn (TestRunner $t) => $t->same(true, str_contains($targetDropped()['next']['missingReason'], 'target does not resolve')),
    'attach wal temp view trigger current source next101 target changed columns' => static fn (TestRunner $t) => $t->same(true, in_array('columns', $targetDropped()['changedFields'], true)),
    'attach wal temp view trigger current source next101 target invalidates main' => static fn (TestRunner $t) => $t->same(['main'], $targetDropped()['invalidatedSources']),
    'attach wal temp view trigger current source next101 target keeps current columns' => static fn (TestRunner $t) => $t->same(['option_id', 'option_name', 'option_value', 'autoload'], $targetDropped()['current']['columns']),
    'attach wal temp view trigger current source next101 target next columns empty' => static fn (TestRunner $t) => $t->same([], $targetDropped()['next']['columns']),
    'attach wal temp view trigger current source next101 target current step ok' => static fn (TestRunner $t) => $t->same('SQLITE_OK', $targetDropped()['sqliteResultOnCurrentStep']),
    'attach wal temp view trigger current source next101 target next action' => static fn (TestRunner $t) => $t->same('abort-reset-and-reprepare', $targetDropped()['nextStepAction']),

    'attach wal temp view trigger current source next101 archive detached status' => static fn (TestRunner $t) => $t->same('reprepare-required', $archiveDetached()['status']),
    'attach wal temp view trigger current source next101 archive current schema' => static fn (TestRunner $t) => $t->same('archive', $archiveDetached()['current']['triggerSchema']),
    'attach wal temp view trigger current source next101 archive next missing schema' => static fn (TestRunner $t) => $t->same('archive', $archiveDetached()['next']['missingSchema']),
    'attach wal temp view trigger current source next101 archive invalidates archive' => static fn (TestRunner $t) => $t->same(['archive'], $archiveDetached()['invalidatedSources']),
    'attach wal temp view trigger current source next101 archive attached schema retained' => static fn (TestRunner $t) => $t->same(['archive'], $archiveDetached()['attachedSchemas']),
    'attach wal temp view trigger current source next101 archive wal schema retained' => static fn (TestRunner $t) => $t->same(['archive'], $archiveDetached()['walSchemas']),
    'attach wal temp view trigger current source next101 archive no temp schema' => static fn (TestRunner $t) => $t->same([], $archiveDetached()['tempSchemas']),
    'attach wal temp view trigger current source next101 archive current body dependency' => static fn (TestRunner $t) => $t->same(['schema' => 'archive', 'name' => 'wp_options'], $archiveDetached()['current']['bodyDependencies'][0]),
    'attach wal temp view trigger current source next101 archive next body empty' => static fn (TestRunner $t) => $t->same([], $archiveDetached()['next']['bodyDependencies']),
    'attach wal temp view trigger current source next101 archive current step ok' => static fn (TestRunner $t) => $t->same('SQLITE_OK', $archiveDetached()['sqliteResultOnCurrentStep']),
    'attach wal temp view trigger current source next101 archive next action' => static fn (TestRunner $t) => $t->same('abort-reset-and-reprepare', $archiveDetached()['nextStepAction']),
];
