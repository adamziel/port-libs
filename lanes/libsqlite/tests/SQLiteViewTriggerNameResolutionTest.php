<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteSchemaRecord;
use PortLibs\LibSqlite\SQLiteViewTriggerNameResolution;

$records = [
    new SQLiteSchemaRecord('table', 'wp_options', 'wp_options', 2, 'CREATE TABLE wp_options(option_id integer primary key, option_name text, option_value text, autoload text, blog_id integer, PRIMARY KEY(option_id))', 1),
    new SQLiteSchemaRecord('table', 'wp_option_audit', 'wp_option_audit', 3, 'CREATE TABLE wp_option_audit(option_id integer, label text, old_name text, new_name text)', 2),
    new SQLiteSchemaRecord('view', 'autoloaded_options', 'autoloaded_options', 0, "CREATE VIEW autoloaded_options(option_id, option_name, option_value) AS SELECT option_id, option_name, option_value FROM wp_options WHERE autoload = 'yes'", 3),
    new SQLiteSchemaRecord('view', 'option_labels', 'option_labels', 0, 'CREATE VIEW option_labels AS SELECT option_id, option_name AS label, length(option_value) AS value_length FROM wp_options', 4),
    new SQLiteSchemaRecord('view', 'shadow_options', 'shadow_options', 0, 'CREATE VIEW shadow_options(option_id, source_name) AS SELECT option_id, option_name FROM wp_options', 5),
    new SQLiteSchemaRecord('view', 'shadow_options', 'shadow_options', null, 'CREATE TEMP VIEW shadow_options(option_id, temp_name) AS SELECT option_id, option_name FROM temp.wp_options_stage', 6),
    new SQLiteSchemaRecord('trigger', 'audit_options_update', 'wp_options', 0, "CREATE TRIGGER audit_options_update AFTER UPDATE OF option_name ON wp_options BEGIN INSERT INTO wp_option_audit(option_id, old_name, new_name) VALUES(old.option_id, old.option_name, new.option_name); END", 7),
    new SQLiteSchemaRecord('trigger', 'insert_autoloaded_option', 'autoloaded_options', 0, "CREATE TRIGGER insert_autoloaded_option INSTEAD OF INSERT ON autoloaded_options BEGIN INSERT INTO wp_options(option_id, option_name, option_value, autoload) VALUES(new.option_id, new.option_name, new.option_value, 'yes'); END", 8),
    new SQLiteSchemaRecord('trigger', 'bad_autoloaded_option', 'autoloaded_options', 0, 'CREATE TRIGGER bad_autoloaded_option INSTEAD OF UPDATE ON autoloaded_options BEGIN SELECT new.missing_column, old.option_name; END', 9),
    new SQLiteSchemaRecord('trigger', 'label_view_insert', 'option_labels', 0, "CREATE TRIGGER label_view_insert INSTEAD OF INSERT ON option_labels BEGIN SELECT new.option_id, new.label, new.value_length; END", 10),
    new SQLiteSchemaRecord('trigger', 'temp_shadow_insert', 'shadow_options', null, 'CREATE TEMP TRIGGER temp_shadow_insert INSTEAD OF INSERT ON shadow_options BEGIN SELECT new.option_id, new.temp_name; END', 11),
    new SQLiteSchemaRecord('trigger', 'main_shadow_insert', 'shadow_options', 0, 'CREATE TRIGGER main_shadow_insert INSTEAD OF INSERT ON shadow_options BEGIN SELECT new.option_id, new.source_name; END', 12),
    new SQLiteSchemaRecord('trigger', 'bad_shadow_insert', 'shadow_options', null, 'CREATE TEMP TRIGGER bad_shadow_insert INSTEAD OF INSERT ON shadow_options BEGIN SELECT new.source_name; END', 13),
];

$resolved = static fn (string $name): array => SQLiteViewTriggerNameResolution::resolveTrigger($records, $name);
$summary = static fn (): array => SQLiteViewTriggerNameResolution::summary($records);

return [
    'view trigger name resolution resolves table trigger status' => static fn (TestRunner $t) => $t->same('resolved', $resolved('audit_options_update')['status']),
    'view trigger name resolution table target name' => static fn (TestRunner $t) => $t->same('wp_options', $resolved('audit_options_update')['target']),
    'view trigger name resolution table target type' => static fn (TestRunner $t) => $t->same('table', $resolved('audit_options_update')['targetType']),
    'view trigger name resolution table columns include option id' => static fn (TestRunner $t) => $t->same('option_id', $resolved('audit_options_update')['columns'][0]),
    'view trigger name resolution table columns include option name' => static fn (TestRunner $t) => $t->same(true, in_array('option_name', $resolved('audit_options_update')['columns'], true)),
    'view trigger name resolution table columns skip table constraint' => static fn (TestRunner $t) => $t->same(false, in_array('PRIMARY', $resolved('audit_options_update')['columns'], true)),
    'view trigger name resolution update trigger new columns' => static fn (TestRunner $t) => $t->same(['option_name'], $resolved('audit_options_update')['referencedNew']),
    'view trigger name resolution update trigger old columns' => static fn (TestRunner $t) => $t->same(['option_id', 'option_name'], $resolved('audit_options_update')['referencedOld']),
    'view trigger name resolution update trigger no missing new' => static fn (TestRunner $t) => $t->same([], $resolved('audit_options_update')['missingNew']),
    'view trigger name resolution update trigger no missing old' => static fn (TestRunner $t) => $t->same([], $resolved('audit_options_update')['missingOld']),
    'view trigger name resolution update trigger body dependency' => static fn (TestRunner $t) => $t->same(['wp_option_audit'], $resolved('audit_options_update')['bodyDependencies']),
    'view trigger name resolution update trigger is not instead of' => static fn (TestRunner $t) => $t->same(false, $resolved('audit_options_update')['insteadOf']),

    'view trigger name resolution resolves instead of view status' => static fn (TestRunner $t) => $t->same('resolved', $resolved('insert_autoloaded_option')['status']),
    'view trigger name resolution instead of target name' => static fn (TestRunner $t) => $t->same('autoloaded_options', $resolved('insert_autoloaded_option')['target']),
    'view trigger name resolution instead of target type' => static fn (TestRunner $t) => $t->same('view', $resolved('insert_autoloaded_option')['targetType']),
    'view trigger name resolution explicit view columns' => static fn (TestRunner $t) => $t->same(['option_id', 'option_name', 'option_value'], $resolved('insert_autoloaded_option')['columns']),
    'view trigger name resolution instead of flag' => static fn (TestRunner $t) => $t->same(true, $resolved('insert_autoloaded_option')['insteadOf']),
    'view trigger name resolution instead of new references' => static fn (TestRunner $t) => $t->same(['option_id', 'option_name', 'option_value'], $resolved('insert_autoloaded_option')['referencedNew']),
    'view trigger name resolution instead of old references empty' => static fn (TestRunner $t) => $t->same([], $resolved('insert_autoloaded_option')['referencedOld']),
    'view trigger name resolution instead of body dependency insert' => static fn (TestRunner $t) => $t->same(['wp_options'], $resolved('insert_autoloaded_option')['bodyDependencies']),

    'view trigger name resolution detects missing new column' => static fn (TestRunner $t) => $t->same(['missing_column'], $resolved('bad_autoloaded_option')['missingNew']),
    'view trigger name resolution keeps valid old column beside missing new' => static fn (TestRunner $t) => $t->same([], $resolved('bad_autoloaded_option')['missingOld']),
    'view trigger name resolution unresolved status for missing new' => static fn (TestRunner $t) => $t->same('unresolved', $resolved('bad_autoloaded_option')['status']),

    'view trigger name resolution implicit view select aliases' => static fn (TestRunner $t) => $t->same(['option_id', 'label', 'value_length'], $resolved('label_view_insert')['columns']),
    'view trigger name resolution implicit view alias references' => static fn (TestRunner $t) => $t->same(['option_id', 'label', 'value_length'], $resolved('label_view_insert')['referencedNew']),
    'view trigger name resolution implicit view alias status' => static fn (TestRunner $t) => $t->same('resolved', $resolved('label_view_insert')['status']),

    'view trigger name resolution temp trigger chooses temp shadow' => static fn (TestRunner $t) => $t->same(true, $resolved('temp_shadow_insert')['targetTemporary']),
    'view trigger name resolution temp shadow columns' => static fn (TestRunner $t) => $t->same(['option_id', 'temp_name'], $resolved('temp_shadow_insert')['columns']),
    'view trigger name resolution temp shadow references' => static fn (TestRunner $t) => $t->same(['option_id', 'temp_name'], $resolved('temp_shadow_insert')['referencedNew']),
    'view trigger name resolution temp shadow status' => static fn (TestRunner $t) => $t->same('resolved', $resolved('temp_shadow_insert')['status']),
    'view trigger name resolution main trigger keeps main shadow' => static fn (TestRunner $t) => $t->same(false, $resolved('main_shadow_insert')['targetTemporary']),
    'view trigger name resolution main shadow columns' => static fn (TestRunner $t) => $t->same(['option_id', 'source_name'], $resolved('main_shadow_insert')['columns']),
    'view trigger name resolution main shadow references' => static fn (TestRunner $t) => $t->same(['option_id', 'source_name'], $resolved('main_shadow_insert')['referencedNew']),
    'view trigger name resolution main shadow status' => static fn (TestRunner $t) => $t->same('resolved', $resolved('main_shadow_insert')['status']),
    'view trigger name resolution temp shadow rejects main column' => static fn (TestRunner $t) => $t->same(['source_name'], $resolved('bad_shadow_insert')['missingNew']),
    'view trigger name resolution temp shadow unresolved' => static fn (TestRunner $t) => $t->same('unresolved', $resolved('bad_shadow_insert')['status']),

    'view trigger name resolution resolves all trigger count' => static fn (TestRunner $t) => $t->same(7, count(SQLiteViewTriggerNameResolution::resolveTriggers($records))),
    'view trigger name resolution summary resolved count' => static fn (TestRunner $t) => $t->same(5, $summary()['resolved']),
    'view trigger name resolution summary unresolved count' => static fn (TestRunner $t) => $t->same(2, $summary()['unresolved']),
    'view trigger name resolution summary instead of count' => static fn (TestRunner $t) => $t->same(6, $summary()['insteadOf']),
    'view trigger name resolution summary temp target count' => static fn (TestRunner $t) => $t->same(2, $summary()['tempTargets']),
    'view trigger name resolution summary missing trigger keys' => static fn (TestRunner $t) => $t->same(['bad_autoloaded_option', 'bad_shadow_insert'], array_keys($summary()['missingReferences'])),
    'view trigger name resolution summary bad autoloaded missing new' => static fn (TestRunner $t) => $t->same(['missing_column'], $summary()['missingReferences']['bad_autoloaded_option']['new']),
    'view trigger name resolution summary bad shadow missing new' => static fn (TestRunner $t) => $t->same(['source_name'], $summary()['missingReferences']['bad_shadow_insert']['new']),
    'view trigger name resolution summary bad shadow missing old empty' => static fn (TestRunner $t) => $t->same([], $summary()['missingReferences']['bad_shadow_insert']['old']),

    'view trigger name resolution trigger lookup is case insensitive' => static fn (TestRunner $t) => $t->same('audit_options_update', $resolved('AUDIT_OPTIONS_UPDATE')['trigger']),
    'view trigger name resolution throws for missing trigger' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => SQLiteViewTriggerNameResolution::resolveTrigger($records, 'missing_trigger')),
    'view trigger name resolution throws for missing target' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => SQLiteViewTriggerNameResolution::resolveTrigger([
        new SQLiteSchemaRecord('trigger', 'orphan', 'missing_options', 0, 'CREATE TRIGGER orphan AFTER INSERT ON missing_options BEGIN SELECT new.option_id; END', 20),
    ], 'orphan')),
    'view trigger name resolution throws for trigger without sql' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => SQLiteViewTriggerNameResolution::resolveTrigger([
        new SQLiteSchemaRecord('trigger', 'bodyless', 'wp_options', 0, null, 21),
    ], 'bodyless')),
    'view trigger name resolution throws for malformed trigger sql' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => SQLiteViewTriggerNameResolution::resolveTrigger([
        new SQLiteSchemaRecord('table', 'wp_options', 'wp_options', 2, 'CREATE TABLE wp_options(option_id integer)', 22),
        new SQLiteSchemaRecord('trigger', 'bad', 'wp_options', 0, 'CREATE TRIGGER bad BEGIN SELECT 1; END', 23),
    ], 'bad')),
    'view trigger name resolution quoted table column names' => static fn (TestRunner $t) => $t->same(['option name'], SQLiteViewTriggerNameResolution::resolveTrigger([
        new SQLiteSchemaRecord('table', 'quoted_options', 'quoted_options', 2, 'CREATE TABLE quoted_options("option name" text)', 24),
        new SQLiteSchemaRecord('trigger', 'quoted_insert', 'quoted_options', 0, 'CREATE TRIGGER quoted_insert AFTER INSERT ON quoted_options BEGIN SELECT new."option name"; END', 25),
    ], 'quoted_insert')['referencedNew']),
    'view trigger name resolution quoted view alias names' => static fn (TestRunner $t) => $t->same(['option label'], SQLiteViewTriggerNameResolution::resolveTrigger([
        new SQLiteSchemaRecord('table', 'wp_options', 'wp_options', 2, 'CREATE TABLE wp_options(option_name text)', 26),
        new SQLiteSchemaRecord('view', 'quoted_view', 'quoted_view', 0, 'CREATE VIEW quoted_view AS SELECT option_name AS "option label" FROM wp_options', 27),
        new SQLiteSchemaRecord('trigger', 'quoted_view_insert', 'quoted_view', 0, 'CREATE TRIGGER quoted_view_insert INSTEAD OF INSERT ON quoted_view BEGIN SELECT new."option label"; END', 28),
    ], 'quoted_view_insert')['columns']),
    'view trigger name resolution default timing trigger still resolves table' => static fn (TestRunner $t) => $t->same('resolved', SQLiteViewTriggerNameResolution::resolveTrigger([
        new SQLiteSchemaRecord('table', 'wp_options', 'wp_options', 2, 'CREATE TABLE wp_options(option_id integer)', 29),
        new SQLiteSchemaRecord('trigger', 'implicit_before', 'wp_options', 0, 'CREATE TRIGGER implicit_before INSERT ON wp_options BEGIN SELECT new.option_id; END', 30),
    ], 'implicit_before')['status']),
    'view trigger name resolution update of column list still parses target' => static fn (TestRunner $t) => $t->same('wp_options', $resolved('audit_options_update')['target']),
    'view trigger name resolution body dependency ignores pseudo table' => static fn (TestRunner $t) => $t->same(false, in_array('new', $resolved('audit_options_update')['bodyDependencies'], true)),
    'view trigger name resolution all statuses are stable' => static fn (TestRunner $t) => $t->same(['resolved', 'resolved', 'unresolved', 'resolved', 'resolved', 'resolved', 'unresolved'], array_column(SQLiteViewTriggerNameResolution::resolveTriggers($records), 'status')),
];
