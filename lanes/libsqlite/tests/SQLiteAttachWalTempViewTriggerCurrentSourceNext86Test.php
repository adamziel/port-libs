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

$catalog = static function (bool $next = false) use ($record): SQLiteAttachedSchemaCatalog {
    $mainView = $next
        ? "CREATE VIEW active_options(option_id, option_name, option_value, autoload, blog_id) AS SELECT option_id, option_name, option_value, autoload, 1 AS blog_id FROM wp_options WHERE autoload = 'yes'"
        : "CREATE VIEW active_options(option_id, option_name, option_value, autoload) AS SELECT option_id, option_name, option_value, autoload FROM wp_options WHERE autoload = 'yes'";
    $mainTrigger = $next
        ? "CREATE TRIGGER active_options_insert INSTEAD OF INSERT ON active_options WHEN new.autoload = 'yes' BEGIN INSERT INTO wp_options(option_id, option_name, option_value, autoload) VALUES(new.option_id, new.option_name, new.option_value, new.autoload); INSERT INTO wp_option_audit(option_id, label, option_name, blog_id) VALUES(new.option_id, 'main-next', new.option_name, new.blog_id); END"
        : "CREATE TRIGGER active_options_insert INSTEAD OF INSERT ON active_options WHEN new.autoload = 'yes' BEGIN INSERT INTO wp_options(option_id, option_name, option_value, autoload) VALUES(new.option_id, new.option_name, new.option_value, new.autoload); INSERT INTO wp_option_audit(option_id, label, option_name) VALUES(new.option_id, 'main-current', new.option_name); END";
    $tempView = $next
        ? 'CREATE TEMP VIEW active_options(option_id, temp_name, option_value, autoload, source) AS SELECT option_id, temp_name, option_value, autoload, "temp" AS source FROM temp.wp_options'
        : 'CREATE TEMP VIEW active_options(option_id, temp_name, option_value, autoload) AS SELECT option_id, temp_name, option_value, autoload FROM temp.wp_options';
    $tempTrigger = $next
        ? "CREATE TEMP TRIGGER temp_active_options_insert INSTEAD OF INSERT ON active_options WHEN new.autoload = 'yes' BEGIN INSERT INTO wp_options(option_id, temp_name, option_value, autoload) VALUES(new.option_id, new.temp_name, new.option_value, new.autoload); INSERT INTO wp_option_audit(option_id, label, temp_name, source) VALUES(new.option_id, 'temp-next', new.temp_name, new.source); END"
        : "CREATE TEMP TRIGGER temp_active_options_insert INSTEAD OF INSERT ON active_options WHEN new.autoload = 'yes' BEGIN INSERT INTO wp_options(option_id, temp_name, option_value, autoload) VALUES(new.option_id, new.temp_name, new.option_value, new.autoload); INSERT INTO wp_option_audit(option_id, label, temp_name) VALUES(new.option_id, 'temp-current', new.temp_name); END";

    $catalog = new SQLiteAttachedSchemaCatalog(
        [
            $record('table', 'wp_options', 'wp_options', 2, 'CREATE TABLE wp_options(option_id integer primary key, option_name text, option_value text, autoload text)', 1),
            $record('table', 'wp_option_audit', 'wp_option_audit', 3, 'CREATE TABLE wp_option_audit(option_id integer, label text, option_name text, blog_id integer)', 2),
            $record('view', 'active_options', 'active_options', 0, $mainView, 3),
            $record('trigger', 'active_options_insert', 'active_options', 0, $mainTrigger, 4),
        ],
        [
            $record('table', 'wp_options', 'wp_options', 10, 'CREATE TEMP TABLE wp_options(option_id integer, temp_name text, option_value text, autoload text)', 5),
            $record('table', 'wp_option_audit', 'wp_option_audit', 11, 'CREATE TEMP TABLE wp_option_audit(option_id integer, label text, temp_name text, source text)', 6),
            $record('view', 'active_options', 'active_options', null, $tempView, 7),
            $record('trigger', 'temp_active_options_insert', 'active_options', null, $tempTrigger, 8),
        ],
    );

    $catalog->attach('archive', '/srv/archive.sqlite', [
        $record('table', 'wp_options', 'wp_options', 20, 'CREATE TABLE archive.wp_options(blog_id integer, option_name text, option_value text, autoload text)', 9),
        $record('table', 'wp_option_audit', 'wp_option_audit', 21, 'CREATE TABLE archive.wp_option_audit(blog_id integer, label text, option_name text, next_source text)', 10),
        $record('view', 'active_options', 'active_options', 0, $next
            ? 'CREATE VIEW archive.active_options(blog_id, option_name, option_value, autoload, next_source) AS SELECT blog_id, option_name, option_value, autoload, "wal" AS next_source FROM wp_options'
            : 'CREATE VIEW archive.active_options(blog_id, option_name, option_value, autoload) AS SELECT blog_id, option_name, option_value, autoload FROM wp_options', 11),
        $record('trigger', 'archive_active_options_insert', 'active_options', 0, $next
            ? "CREATE TRIGGER archive_active_options_insert INSTEAD OF INSERT ON active_options WHEN new.blog_id = 3 BEGIN INSERT INTO wp_options(blog_id, option_name, option_value, autoload) VALUES(new.blog_id, new.option_name, new.option_value, new.autoload); INSERT INTO archive.wp_option_audit(blog_id, label, option_name, next_source) VALUES(new.blog_id, 'archive-next', new.option_name, new.next_source); END"
            : "CREATE TRIGGER archive_active_options_insert INSTEAD OF INSERT ON active_options WHEN new.blog_id = 3 BEGIN INSERT INTO wp_options(blog_id, option_name, option_value, autoload) VALUES(new.blog_id, new.option_name, new.option_value, new.autoload); INSERT INTO archive.wp_option_audit(blog_id, label, option_name) VALUES(new.blog_id, 'archive-current', new.option_name); END", 12),
    ]);

    return $catalog;
};

$stableCatalog = static function () use ($record): SQLiteAttachedSchemaCatalog {
    $catalog = new SQLiteAttachedSchemaCatalog([
        $record('table', 'wp_options', 'wp_options', 2, 'CREATE TABLE wp_options(option_id integer primary key, option_name text, option_value text, autoload text)', 1),
        $record('view', 'active_options', 'active_options', 0, 'CREATE VIEW active_options(option_id, option_name, option_value, autoload) AS SELECT option_id, option_name, option_value, autoload FROM wp_options', 2),
        $record('trigger', 'active_options_insert', 'active_options', 0, "CREATE TRIGGER active_options_insert INSTEAD OF INSERT ON active_options BEGIN INSERT INTO wp_options(option_id, option_name, option_value, autoload) VALUES(new.option_id, new.option_name, new.option_value, new.autoload); END", 3),
    ]);

    return $catalog;
};

$current = $catalog(false);
$next = $catalog(true);
$stableCurrent = $stableCatalog();
$stableNext = $stableCatalog();

$main = static fn (): array => SQLiteAttachTempViewTriggerResolution::currentNextSourcePlan($current, $next, 'main.active_options_insert');
$temp = static fn (): array => SQLiteAttachTempViewTriggerResolution::currentNextSourcePlan($current, $next, 'temp.temp_active_options_insert');
$archive = static fn (): array => SQLiteAttachTempViewTriggerResolution::currentNextSourcePlan($current, $next, 'archive.archive_active_options_insert');
$stable = static fn (): array => SQLiteAttachTempViewTriggerResolution::currentNextSourcePlan($stableCurrent, $stableNext, 'active_options_insert');

return [
    'attach wal temp view trigger current source next86 main status reprepare' => static fn (TestRunner $t) => $t->same('reprepare-required', $main()['status']),
    'attach wal temp view trigger current source next86 main changed' => static fn (TestRunner $t) => $t->same(true, $main()['changed']),
    'attach wal temp view trigger current source next86 main requires reprepare' => static fn (TestRunner $t) => $t->same(true, $main()['requiresReprepare']),
    'attach wal temp view trigger current source next86 main trigger stable' => static fn (TestRunner $t) => $t->same('active_options_insert', $main()['trigger']),
    'attach wal temp view trigger current source next86 main current schema' => static fn (TestRunner $t) => $t->same('main', $main()['current']['triggerSchema']),
    'attach wal temp view trigger current source next86 main next schema' => static fn (TestRunner $t) => $t->same('main', $main()['next']['triggerSchema']),
    'attach wal temp view trigger current source next86 main current columns' => static fn (TestRunner $t) => $t->same(['option_id', 'option_name', 'option_value', 'autoload'], $main()['current']['columns']),
    'attach wal temp view trigger current source next86 main next columns' => static fn (TestRunner $t) => $t->same(['option_id', 'option_name', 'option_value', 'autoload', 'blog_id'], $main()['next']['columns']),
    'attach wal temp view trigger current source next86 main changed columns' => static fn (TestRunner $t) => $t->same(true, in_array('columns', $main()['changedFields'], true)),
    'attach wal temp view trigger current source next86 main body dependencies stable' => static fn (TestRunner $t) => $t->same(false, in_array('bodyDependencies', $main()['changedFields'], true)),
    'attach wal temp view trigger current source next86 main current first dependency' => static fn (TestRunner $t) => $t->same(['schema' => null, 'name' => 'wp_options'], $main()['current']['bodyDependencies'][0]),
    'attach wal temp view trigger current source next86 main next audit dependency' => static fn (TestRunner $t) => $t->same(['schema' => null, 'name' => 'wp_option_audit'], $main()['next']['bodyDependencies'][1]),
    'attach wal temp view trigger current source next86 main wal schema main' => static fn (TestRunner $t) => $t->same(['main'], $main()['walSchemas']),
    'attach wal temp view trigger current source next86 main no temp schemas' => static fn (TestRunner $t) => $t->same([], $main()['tempSchemas']),
    'attach wal temp view trigger current source next86 main invalidated main' => static fn (TestRunner $t) => $t->same(['main'], $main()['invalidatedSources']),
    'attach wal temp view trigger current source next86 main target view' => static fn (TestRunner $t) => $t->same('view', $main()['current']['targetType']),
    'attach wal temp view trigger current source next86 main instead of' => static fn (TestRunner $t) => $t->same(true, $main()['current']['insteadOf']),

    'attach wal temp view trigger current source next86 temp status reprepare' => static fn (TestRunner $t) => $t->same('reprepare-required', $temp()['status']),
    'attach wal temp view trigger current source next86 temp trigger temporary' => static fn (TestRunner $t) => $t->same(true, $temp()['current']['triggerTemporary']),
    'attach wal temp view trigger current source next86 temp target temporary' => static fn (TestRunner $t) => $t->same(true, $temp()['current']['targetTemporary']),
    'attach wal temp view trigger current source next86 temp current columns' => static fn (TestRunner $t) => $t->same(['option_id', 'temp_name', 'option_value', 'autoload'], $temp()['current']['columns']),
    'attach wal temp view trigger current source next86 temp next columns' => static fn (TestRunner $t) => $t->same(['option_id', 'temp_name', 'option_value', 'autoload', 'source'], $temp()['next']['columns']),
    'attach wal temp view trigger current source next86 temp changed columns' => static fn (TestRunner $t) => $t->same(true, in_array('columns', $temp()['changedFields'], true)),
    'attach wal temp view trigger current source next86 temp body dependencies stable' => static fn (TestRunner $t) => $t->same(false, in_array('bodyDependencies', $temp()['changedFields'], true)),
    'attach wal temp view trigger current source next86 temp first dependency' => static fn (TestRunner $t) => $t->same(['schema' => null, 'name' => 'wp_options'], $temp()['current']['bodyDependencies'][0]),
    'attach wal temp view trigger current source next86 temp audit dependency' => static fn (TestRunner $t) => $t->same(['schema' => null, 'name' => 'wp_option_audit'], $temp()['next']['bodyDependencies'][1]),
    'attach wal temp view trigger current source next86 temp schemas' => static fn (TestRunner $t) => $t->same(['temp'], $temp()['tempSchemas']),
    'attach wal temp view trigger current source next86 temp no wal schemas' => static fn (TestRunner $t) => $t->same([], $temp()['walSchemas']),
    'attach wal temp view trigger current source next86 temp invalidated temp' => static fn (TestRunner $t) => $t->same(['temp'], $temp()['invalidatedSources']),
    'attach wal temp view trigger current source next86 temp target schema' => static fn (TestRunner $t) => $t->same('temp', $temp()['current']['targetSchema']),
    'attach wal temp view trigger current source next86 temp instead of' => static fn (TestRunner $t) => $t->same(true, $temp()['current']['insteadOf']),

    'attach wal temp view trigger current source next86 archive status reprepare' => static fn (TestRunner $t) => $t->same('reprepare-required', $archive()['status']),
    'attach wal temp view trigger current source next86 archive schema' => static fn (TestRunner $t) => $t->same('archive', $archive()['current']['triggerSchema']),
    'attach wal temp view trigger current source next86 archive target schema' => static fn (TestRunner $t) => $t->same('archive', $archive()['current']['targetSchema']),
    'attach wal temp view trigger current source next86 archive attached schemas' => static fn (TestRunner $t) => $t->same(['archive'], $archive()['attachedSchemas']),
    'attach wal temp view trigger current source next86 archive wal schemas' => static fn (TestRunner $t) => $t->same(['archive'], $archive()['walSchemas']),
    'attach wal temp view trigger current source next86 archive current columns' => static fn (TestRunner $t) => $t->same(['blog_id', 'option_name', 'option_value', 'autoload'], $archive()['current']['columns']),
    'attach wal temp view trigger current source next86 archive next columns' => static fn (TestRunner $t) => $t->same(['blog_id', 'option_name', 'option_value', 'autoload', 'next_source'], $archive()['next']['columns']),
    'attach wal temp view trigger current source next86 archive invalidated source' => static fn (TestRunner $t) => $t->same(['archive'], $archive()['invalidatedSources']),
    'attach wal temp view trigger current source next86 archive body dependency qualified' => static fn (TestRunner $t) => $t->same(['schema' => 'archive', 'name' => 'wp_option_audit'], $archive()['next']['bodyDependencies'][1]),
    'attach wal temp view trigger current source next86 archive target type view' => static fn (TestRunner $t) => $t->same('view', $archive()['next']['targetType']),
    'attach wal temp view trigger current source next86 archive current status resolved' => static fn (TestRunner $t) => $t->same('resolved', $archive()['current']['status']),
    'attach wal temp view trigger current source next86 archive next status resolved' => static fn (TestRunner $t) => $t->same('resolved', $archive()['next']['status']),

    'attach wal temp view trigger current source next86 stable status' => static fn (TestRunner $t) => $t->same('stable', $stable()['status']),
    'attach wal temp view trigger current source next86 stable unchanged' => static fn (TestRunner $t) => $t->same(false, $stable()['changed']),
    'attach wal temp view trigger current source next86 stable no reprepare' => static fn (TestRunner $t) => $t->same(false, $stable()['requiresReprepare']),
    'attach wal temp view trigger current source next86 stable no changed fields' => static fn (TestRunner $t) => $t->same([], $stable()['changedFields']),
    'attach wal temp view trigger current source next86 stable no invalidated sources' => static fn (TestRunner $t) => $t->same([], $stable()['invalidatedSources']),
    'attach wal temp view trigger current source next86 stable current equals next columns' => static fn (TestRunner $t) => $t->same($stable()['current']['columns'], $stable()['next']['columns']),
    'attach wal temp view trigger current source next86 stable main wal source retained' => static fn (TestRunner $t) => $t->same(['main'], $stable()['walSchemas']),
    'attach wal temp view trigger current source next86 stable no temp source' => static fn (TestRunner $t) => $t->same([], $stable()['tempSchemas']),
    'attach wal temp view trigger current source next86 stable no attached source' => static fn (TestRunner $t) => $t->same([], $stable()['attachedSchemas']),
    'attach wal temp view trigger current source next86 stable current status resolved' => static fn (TestRunner $t) => $t->same('resolved', $stable()['current']['status']),
    'attach wal temp view trigger current source next86 stable next status resolved' => static fn (TestRunner $t) => $t->same('resolved', $stable()['next']['status']),
];
