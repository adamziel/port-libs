<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteSchemaRecord;
use PortLibs\LibSqlite\SQLiteViewTriggerNameResolution;

$records = [
    new SQLiteSchemaRecord('table', 'wp_options', 'wp_options', 2, 'CREATE TABLE wp_options(option_id integer, option_name text, option_value text, autoload text)', 1),
    new SQLiteSchemaRecord('table', 'wp_options', 'wp_options', null, 'CREATE TEMP TABLE wp_options(option_id integer, option_name text, scratch_value text)', 2),
    new SQLiteSchemaRecord('table', 'wp_options', 'wp_options', 4, 'CREATE TABLE archive.wp_options(option_id integer, option_name text, archived_value text)', 3),
    new SQLiteSchemaRecord('view', 'option_names', 'option_names', 0, 'CREATE VIEW option_names(option_id, main_name) AS SELECT option_id, option_name FROM main.wp_options', 4),
    new SQLiteSchemaRecord('view', 'option_names', 'option_names', null, 'CREATE TEMP VIEW option_names(option_id, temp_name) AS SELECT option_id, option_name FROM temp.wp_options', 5),
    new SQLiteSchemaRecord('view', 'option_names', 'option_names', 0, 'CREATE VIEW archive.option_names(option_id, archived_name) AS SELECT option_id, option_name FROM archive.wp_options', 6),
    new SQLiteSchemaRecord('trigger', 'temp_unqualified_option_names', 'option_names', null, 'CREATE TEMP TRIGGER temp_unqualified_option_names INSTEAD OF INSERT ON option_names BEGIN SELECT new.option_id, new.temp_name; END', 7),
    new SQLiteSchemaRecord('trigger', 'temp_main_option_names', 'option_names', null, 'CREATE TEMP TRIGGER temp_main_option_names INSTEAD OF INSERT ON main.option_names BEGIN SELECT new.option_id, new.main_name; END', 8),
    new SQLiteSchemaRecord('trigger', 'temp_archive_option_names', 'option_names', null, 'CREATE TEMP TRIGGER temp_archive_option_names INSTEAD OF INSERT ON archive.option_names BEGIN SELECT new.option_id, new.archived_name; END', 9),
    new SQLiteSchemaRecord('trigger', 'main_unqualified_option_names', 'option_names', 0, 'CREATE TRIGGER main_unqualified_option_names INSTEAD OF INSERT ON option_names BEGIN SELECT new.option_id, new.main_name; END', 10),
    new SQLiteSchemaRecord('trigger', 'archive_unqualified_option_names', 'option_names', 0, 'CREATE TRIGGER archive.archive_unqualified_option_names INSTEAD OF INSERT ON option_names BEGIN SELECT new.option_id, new.main_name; END', 11),
    new SQLiteSchemaRecord('trigger', 'temp_unqualified_table', 'wp_options', null, 'CREATE TEMP TRIGGER temp_unqualified_table AFTER INSERT ON wp_options BEGIN SELECT new.scratch_value; END', 12),
    new SQLiteSchemaRecord('trigger', 'temp_main_table', 'wp_options', null, 'CREATE TEMP TRIGGER temp_main_table AFTER INSERT ON main.wp_options BEGIN SELECT new.option_value; END', 13),
    new SQLiteSchemaRecord('trigger', 'temp_archive_table', 'wp_options', null, 'CREATE TEMP TRIGGER temp_archive_table AFTER INSERT ON archive.wp_options BEGIN SELECT new.archived_value; END', 14),
    new SQLiteSchemaRecord('trigger', 'main_unqualified_table', 'wp_options', 0, 'CREATE TRIGGER main_unqualified_table AFTER INSERT ON wp_options BEGIN SELECT new.option_value; END', 15),
    new SQLiteSchemaRecord('trigger', 'temp_bad_main_column', 'option_names', null, 'CREATE TEMP TRIGGER temp_bad_main_column INSTEAD OF INSERT ON main.option_names BEGIN SELECT new.temp_name; END', 16),
    new SQLiteSchemaRecord('trigger', 'temp_bad_unqualified_column', 'option_names', null, 'CREATE TEMP TRIGGER temp_bad_unqualified_column INSTEAD OF INSERT ON option_names BEGIN SELECT new.main_name; END', 17),
    new SQLiteSchemaRecord('trigger', 'quoted_temp_main_view', 'option_names', null, 'CREATE TEMP TRIGGER "quoted_temp_main_view" INSTEAD OF INSERT ON "main"."option_names" BEGIN SELECT new."main_name"; END', 18),
    new SQLiteSchemaRecord('trigger', 'bracket_archive_view', 'option_names', null, 'CREATE TEMP TRIGGER [bracket_archive_view] INSTEAD OF INSERT ON [archive].[option_names] BEGIN SELECT new.[archived_name]; END', 19),
];

$resolved = static fn (string $name): array => SQLiteViewTriggerNameResolution::resolveTrigger($records, $name);
$summary = static fn (): array => SQLiteViewTriggerNameResolution::summary($records);

return [
    'trigger view temp current next31 temp unqualified view chooses temp schema' => static fn (TestRunner $t) => $t->same('temp', $resolved('temp_unqualified_option_names')['targetSchema']),
    'trigger view temp current next31 temp unqualified view target is temp' => static fn (TestRunner $t) => $t->same(true, $resolved('temp_unqualified_option_names')['targetTemporary']),
    'trigger view temp current next31 temp unqualified view columns' => static fn (TestRunner $t) => $t->same(['option_id', 'temp_name'], $resolved('temp_unqualified_option_names')['columns']),
    'trigger view temp current next31 temp unqualified view references' => static fn (TestRunner $t) => $t->same(['option_id', 'temp_name'], $resolved('temp_unqualified_option_names')['referencedNew']),
    'trigger view temp current next31 temp unqualified view status' => static fn (TestRunner $t) => $t->same('resolved', $resolved('temp_unqualified_option_names')['status']),
    'trigger view temp current next31 temp trigger schema reported temp' => static fn (TestRunner $t) => $t->same('temp', $resolved('temp_unqualified_option_names')['triggerSchema']),

    'trigger view temp current next31 temp trigger can qualify main view' => static fn (TestRunner $t) => $t->same('main', $resolved('temp_main_option_names')['targetSchema']),
    'trigger view temp current next31 temp main view is not temporary' => static fn (TestRunner $t) => $t->same(false, $resolved('temp_main_option_names')['targetTemporary']),
    'trigger view temp current next31 temp main view columns' => static fn (TestRunner $t) => $t->same(['option_id', 'main_name'], $resolved('temp_main_option_names')['columns']),
    'trigger view temp current next31 temp main view references' => static fn (TestRunner $t) => $t->same(['option_id', 'main_name'], $resolved('temp_main_option_names')['referencedNew']),
    'trigger view temp current next31 temp main view status' => static fn (TestRunner $t) => $t->same('resolved', $resolved('temp_main_option_names')['status']),

    'trigger view temp current next31 temp trigger can qualify attached view' => static fn (TestRunner $t) => $t->same('archive', $resolved('temp_archive_option_names')['targetSchema']),
    'trigger view temp current next31 temp attached view columns' => static fn (TestRunner $t) => $t->same(['option_id', 'archived_name'], $resolved('temp_archive_option_names')['columns']),
    'trigger view temp current next31 temp attached view references' => static fn (TestRunner $t) => $t->same(['option_id', 'archived_name'], $resolved('temp_archive_option_names')['referencedNew']),
    'trigger view temp current next31 temp attached view status' => static fn (TestRunner $t) => $t->same('resolved', $resolved('temp_archive_option_names')['status']),

    'trigger view temp current next31 main trigger skips temp shadow view' => static fn (TestRunner $t) => $t->same('main', $resolved('main_unqualified_option_names')['targetSchema']),
    'trigger view temp current next31 main trigger target is not temp' => static fn (TestRunner $t) => $t->same(false, $resolved('main_unqualified_option_names')['targetTemporary']),
    'trigger view temp current next31 main trigger view columns' => static fn (TestRunner $t) => $t->same(['option_id', 'main_name'], $resolved('main_unqualified_option_names')['columns']),
    'trigger view temp current next31 main trigger view status' => static fn (TestRunner $t) => $t->same('resolved', $resolved('main_unqualified_option_names')['status']),
    'trigger view temp current next31 qualified trigger schema inferred archive' => static fn (TestRunner $t) => $t->same('archive', $resolved('archive_unqualified_option_names')['triggerSchema']),
    'trigger view temp current next31 attached trigger without target qualifier keeps main search' => static fn (TestRunner $t) => $t->same('main', $resolved('archive_unqualified_option_names')['targetSchema']),

    'trigger view temp current next31 temp unqualified table chooses temp table' => static fn (TestRunner $t) => $t->same('temp', $resolved('temp_unqualified_table')['targetSchema']),
    'trigger view temp current next31 temp unqualified table columns' => static fn (TestRunner $t) => $t->same(['option_id', 'option_name', 'scratch_value'], $resolved('temp_unqualified_table')['columns']),
    'trigger view temp current next31 temp unqualified table references scratch' => static fn (TestRunner $t) => $t->same(['scratch_value'], $resolved('temp_unqualified_table')['referencedNew']),
    'trigger view temp current next31 temp unqualified table status' => static fn (TestRunner $t) => $t->same('resolved', $resolved('temp_unqualified_table')['status']),

    'trigger view temp current next31 temp qualified main table' => static fn (TestRunner $t) => $t->same('main', $resolved('temp_main_table')['targetSchema']),
    'trigger view temp current next31 temp qualified main table columns' => static fn (TestRunner $t) => $t->same(['option_id', 'option_name', 'option_value', 'autoload'], $resolved('temp_main_table')['columns']),
    'trigger view temp current next31 temp qualified main table references option value' => static fn (TestRunner $t) => $t->same(['option_value'], $resolved('temp_main_table')['referencedNew']),
    'trigger view temp current next31 temp qualified main table status' => static fn (TestRunner $t) => $t->same('resolved', $resolved('temp_main_table')['status']),

    'trigger view temp current next31 temp qualified archive table' => static fn (TestRunner $t) => $t->same('archive', $resolved('temp_archive_table')['targetSchema']),
    'trigger view temp current next31 temp qualified archive table columns' => static fn (TestRunner $t) => $t->same(['option_id', 'option_name', 'archived_value'], $resolved('temp_archive_table')['columns']),
    'trigger view temp current next31 temp qualified archive table references archive value' => static fn (TestRunner $t) => $t->same(['archived_value'], $resolved('temp_archive_table')['referencedNew']),
    'trigger view temp current next31 temp qualified archive table status' => static fn (TestRunner $t) => $t->same('resolved', $resolved('temp_archive_table')['status']),

    'trigger view temp current next31 main table trigger skips temp table' => static fn (TestRunner $t) => $t->same('main', $resolved('main_unqualified_table')['targetSchema']),
    'trigger view temp current next31 main table trigger columns' => static fn (TestRunner $t) => $t->same(['option_id', 'option_name', 'option_value', 'autoload'], $resolved('main_unqualified_table')['columns']),
    'trigger view temp current next31 main table trigger status' => static fn (TestRunner $t) => $t->same('resolved', $resolved('main_unqualified_table')['status']),

    'trigger view temp current next31 qualified main view rejects temp-only column' => static fn (TestRunner $t) => $t->same(['temp_name'], $resolved('temp_bad_main_column')['missingNew']),
    'trigger view temp current next31 qualified main view unresolved' => static fn (TestRunner $t) => $t->same('unresolved', $resolved('temp_bad_main_column')['status']),
    'trigger view temp current next31 unqualified temp view rejects main-only column' => static fn (TestRunner $t) => $t->same(['main_name'], $resolved('temp_bad_unqualified_column')['missingNew']),
    'trigger view temp current next31 unqualified temp view unresolved' => static fn (TestRunner $t) => $t->same('unresolved', $resolved('temp_bad_unqualified_column')['status']),

    'trigger view temp current next31 double quoted schema qualifier parses' => static fn (TestRunner $t) => $t->same('main', $resolved('quoted_temp_main_view')['targetSchema']),
    'trigger view temp current next31 double quoted pseudo column parses' => static fn (TestRunner $t) => $t->same(['main_name'], $resolved('quoted_temp_main_view')['referencedNew']),
    'trigger view temp current next31 bracket schema qualifier parses' => static fn (TestRunner $t) => $t->same('archive', $resolved('bracket_archive_view')['targetSchema']),
    'trigger view temp current next31 bracket pseudo column parses' => static fn (TestRunner $t) => $t->same(['archived_name'], $resolved('bracket_archive_view')['referencedNew']),

    'trigger view temp current next31 resolve trigger count' => static fn (TestRunner $t) => $t->same(13, count(SQLiteViewTriggerNameResolution::resolveTriggers($records))),
    'trigger view temp current next31 summary resolved count' => static fn (TestRunner $t) => $t->same(11, $summary()['resolved']),
    'trigger view temp current next31 summary unresolved count' => static fn (TestRunner $t) => $t->same(2, $summary()['unresolved']),
    'trigger view temp current next31 summary instead of count' => static fn (TestRunner $t) => $t->same(9, $summary()['insteadOf']),
    'trigger view temp current next31 summary temp target count' => static fn (TestRunner $t) => $t->same(3, $summary()['tempTargets']),
    'trigger view temp current next31 summary missing keys' => static fn (TestRunner $t) => $t->same(['temp_bad_main_column', 'temp_bad_unqualified_column'], array_keys($summary()['missingReferences'])),
    'trigger view temp current next31 summary main qualifier missing temp' => static fn (TestRunner $t) => $t->same(['temp_name'], $summary()['missingReferences']['temp_bad_main_column']['new']),
    'trigger view temp current next31 summary unqualified temp missing main' => static fn (TestRunner $t) => $t->same(['main_name'], $summary()['missingReferences']['temp_bad_unqualified_column']['new']),

    'trigger view temp current next31 missing qualified schema throws' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => SQLiteViewTriggerNameResolution::resolveTrigger([
        new SQLiteSchemaRecord('view', 'option_names', 'option_names', 0, 'CREATE VIEW option_names(option_id) AS SELECT option_id FROM wp_options', 20),
        new SQLiteSchemaRecord('trigger', 'missing_schema', 'option_names', null, 'CREATE TEMP TRIGGER missing_schema INSTEAD OF INSERT ON missing.option_names BEGIN SELECT new.option_id; END', 21),
    ], 'missing_schema')),
    'trigger view temp current next31 missing qualified temp target throws' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => SQLiteViewTriggerNameResolution::resolveTrigger([
        new SQLiteSchemaRecord('view', 'option_names', 'option_names', 0, 'CREATE VIEW option_names(option_id) AS SELECT option_id FROM wp_options', 22),
        new SQLiteSchemaRecord('trigger', 'missing_temp', 'option_names', null, 'CREATE TEMP TRIGGER missing_temp INSTEAD OF INSERT ON temp.option_names BEGIN SELECT new.option_id; END', 23),
    ], 'missing_temp')),
    'trigger view temp current next31 case insensitive schema qualifier' => static fn (TestRunner $t) => $t->same('archive', SQLiteViewTriggerNameResolution::resolveTrigger($records, 'temp_archive_option_names')['targetSchema']),
];
