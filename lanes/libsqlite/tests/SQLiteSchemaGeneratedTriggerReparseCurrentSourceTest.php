<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteSchemaGeneratedTriggerReparsePlan;
use PortLibs\LibSqlite\SQLiteSchemaRecord;

$record = static fn (string $type, string $name, string $table, ?int $root, ?string $sql, int $rowid): SQLiteSchemaRecord => new SQLiteSchemaRecord($type, $name, $table, $root, $sql, $rowid);

$currentRecords = static fn (): array => [
    $record('table', 'wp_options', 'wp_options', 2, <<<'SQL'
CREATE TABLE wp_options(
  option_id INTEGER PRIMARY KEY,
  option_name TEXT NOT NULL,
  option_value TEXT NOT NULL DEFAULT '',
  autoload TEXT NOT NULL DEFAULT 'yes',
  option_slug TEXT GENERATED ALWAYS AS (lower(option_name)) VIRTUAL
)
SQL, 1),
    $record('table', 'wp_option_audit', 'wp_option_audit', 3, 'CREATE TABLE wp_option_audit(option_id INTEGER, option_slug TEXT, option_value_len INTEGER, bucket TEXT, action TEXT)', 2),
    $record('trigger', 'wp_options_audit_update', 'wp_options', 0, <<<'SQL'
CREATE TRIGGER wp_options_audit_update AFTER UPDATE ON wp_options BEGIN
  INSERT INTO wp_option_audit(option_id, option_slug, option_value_len, bucket, action)
  VALUES(new.option_id, new.option_slug, new.option_value_len, old.option_bucket, 'update');
END
SQL, 3),
];

$nextRecords = static fn (): array => [
    $record('table', 'wp_options', 'wp_options', 2, <<<'SQL'
CREATE TABLE wp_options(
  option_id INTEGER PRIMARY KEY,
  option_name TEXT NOT NULL,
  option_value TEXT NOT NULL DEFAULT '',
  autoload TEXT NOT NULL DEFAULT 'yes',
  option_slug TEXT GENERATED ALWAYS AS (lower(option_name)) STORED,
  option_value_len INTEGER GENERATED ALWAYS AS (length(option_value)) VIRTUAL,
  option_bucket TEXT AS (CASE WHEN autoload = 'yes' THEN 'autoloaded' ELSE 'manual' END) VIRTUAL
)
SQL, 1),
    $record('table', 'wp_option_audit', 'wp_option_audit', 3, 'CREATE TABLE wp_option_audit(option_id INTEGER, option_slug TEXT, option_value_len INTEGER, bucket TEXT, action TEXT)', 2),
    $record('trigger', 'wp_options_audit_update', 'wp_options', 0, <<<'SQL'
CREATE TRIGGER wp_options_audit_update AFTER UPDATE ON wp_options BEGIN
  INSERT INTO wp_option_audit(option_id, option_slug, option_value_len, bucket, action)
  VALUES(new.option_id, new.option_slug, new.option_value_len, old.option_bucket, 'update');
END
SQL, 3),
];

$stableRecords = static fn (): array => [
    $record('table', 'wp_options', 'wp_options', 2, <<<'SQL'
CREATE TABLE wp_options(
  option_id INTEGER PRIMARY KEY,
  option_name TEXT NOT NULL,
  option_value TEXT NOT NULL DEFAULT '',
  autoload TEXT NOT NULL DEFAULT 'yes',
  option_slug TEXT GENERATED ALWAYS AS (lower(option_name)) STORED,
  option_value_len INTEGER GENERATED ALWAYS AS (length(option_value)) VIRTUAL,
  option_bucket TEXT AS (CASE WHEN autoload = 'yes' THEN 'autoloaded' ELSE 'manual' END) VIRTUAL
)
SQL, 1),
    $record('table', 'wp_option_audit', 'wp_option_audit', 3, 'CREATE TABLE wp_option_audit(option_id INTEGER, option_slug TEXT, option_value_len INTEGER, bucket TEXT, action TEXT)', 2),
    $record('trigger', 'wp_options_audit_update', 'wp_options', 0, <<<'SQL'
CREATE TRIGGER wp_options_audit_update AFTER UPDATE ON wp_options BEGIN
  INSERT INTO wp_option_audit(option_id, option_slug, option_value_len, bucket, action)
  VALUES(new.option_id, new.option_slug, new.option_value_len, old.option_bucket, 'update');
END
SQL, 3),
];

$plan = static fn (): array => SQLiteSchemaGeneratedTriggerReparsePlan::currentNext(
    $currentRecords(),
    $nextRecords(),
    'wp_options_audit_update',
    ['schema_version_before' => 106, 'schema_version_after' => 107],
);

$stable = static fn (): array => SQLiteSchemaGeneratedTriggerReparsePlan::currentNext(
    $stableRecords(),
    $stableRecords(),
    'wp_options_audit_update',
    ['schema_version_before' => 107, 'schema_version_after' => 107],
);

$deletePlan = static fn (): array => SQLiteSchemaGeneratedTriggerReparsePlan::currentNext([
    $record('table', 'wp_posts', 'wp_posts', 4, 'CREATE TABLE wp_posts(id INTEGER, title TEXT, title_key TEXT AS (lower(title)) STORED)', 1),
    $record('trigger', 'wp_posts_ad', 'wp_posts', 0, 'CREATE TRIGGER wp_posts_ad AFTER DELETE ON wp_posts BEGIN INSERT INTO wp_option_audit(option_slug) VALUES(old.title_key); END', 2),
], [
    $record('table', 'wp_posts', 'wp_posts', 4, 'CREATE TABLE wp_posts(id INTEGER, title TEXT)', 1),
    $record('trigger', 'wp_posts_ad', 'wp_posts', 0, 'CREATE TRIGGER wp_posts_ad AFTER DELETE ON wp_posts BEGIN INSERT INTO wp_option_audit(option_slug) VALUES(old.title_key); END', 2),
], 'wp_posts_ad', ['schema_version_before' => 12, 'schema_version_after' => 13]);

return [
    'schema generated trigger reparse current source operation' => static fn (TestRunner $t) => $t->same('schema-ddl-reparse-generated-trigger-current-source', $plan()['operation']),
    'schema generated trigger reparse current source trigger name' => static fn (TestRunner $t) => $t->same('wp_options_audit_update', $plan()['trigger']),
    'schema generated trigger reparse current source target name' => static fn (TestRunner $t) => $t->same('wp_options', $plan()['target']),
    'schema generated trigger reparse current source schema version before' => static fn (TestRunner $t) => $t->same(106, $plan()['schema_version_before']),
    'schema generated trigger reparse current source schema version after' => static fn (TestRunner $t) => $t->same(107, $plan()['schema_version_after']),
    'schema generated trigger reparse current source cookie changed' => static fn (TestRunner $t) => $t->same(true, $plan()['schema_cookie_changed']),
    'schema generated trigger reparse current source changed' => static fn (TestRunner $t) => $t->same(true, $plan()['changed']),
    'schema generated trigger reparse current source requires reparse' => static fn (TestRunner $t) => $t->same(true, $plan()['requiresReparse']),
    'schema generated trigger reparse current source status reparse' => static fn (TestRunner $t) => $t->same('reparse-required', $plan()['status']),
    'schema generated trigger reparse current source current unresolved' => static fn (TestRunner $t) => $t->same('unresolved', $plan()['current']['status']),
    'schema generated trigger reparse current source next resolved' => static fn (TestRunner $t) => $t->same('resolved', $plan()['next']['status']),
    'schema generated trigger reparse current source event update' => static fn (TestRunner $t) => $t->same('update', $plan()['current']['event']),
    'schema generated trigger reparse current source timing after' => static fn (TestRunner $t) => $t->same('after', $plan()['current']['timing']),
    'schema generated trigger reparse current source current generated columns' => static fn (TestRunner $t) => $t->same(['option_slug'], $plan()['current']['generatedColumns']),
    'schema generated trigger reparse current source next generated columns' => static fn (TestRunner $t) => $t->same(['option_slug', 'option_value_len', 'option_bucket'], $plan()['next']['generatedColumns']),
    'schema generated trigger reparse current source added generated columns' => static fn (TestRunner $t) => $t->same(['option_value_len', 'option_bucket'], $plan()['generatedAdded']),
    'schema generated trigger reparse current source no removed generated columns' => static fn (TestRunner $t) => $t->same([], $plan()['generatedRemoved']),
    'schema generated trigger reparse current source generated references sorted' => static fn (TestRunner $t) => $t->same(['option_bucket', 'option_slug', 'option_value_len'], $plan()['generatedReferences']),
    'schema generated trigger reparse current source current referenced new' => static fn (TestRunner $t) => $t->same(['option_id', 'option_slug', 'option_value_len'], $plan()['current']['referencedNew']),
    'schema generated trigger reparse current source current referenced old' => static fn (TestRunner $t) => $t->same(['option_bucket'], $plan()['current']['referencedOld']),
    'schema generated trigger reparse current source current generated new' => static fn (TestRunner $t) => $t->same(['option_slug'], $plan()['current']['referencedGeneratedNew']),
    'schema generated trigger reparse current source next generated new' => static fn (TestRunner $t) => $t->same(['option_slug', 'option_value_len'], $plan()['next']['referencedGeneratedNew']),
    'schema generated trigger reparse current source next generated old' => static fn (TestRunner $t) => $t->same(['option_bucket'], $plan()['next']['referencedGeneratedOld']),
    'schema generated trigger reparse current source missing new before' => static fn (TestRunner $t) => $t->same(['option_value_len'], $plan()['current']['missingNew']),
    'schema generated trigger reparse current source missing old before' => static fn (TestRunner $t) => $t->same(['option_bucket'], $plan()['current']['missingOld']),
    'schema generated trigger reparse current source missing new after empty' => static fn (TestRunner $t) => $t->same([], $plan()['next']['missingNew']),
    'schema generated trigger reparse current source missing old after empty' => static fn (TestRunner $t) => $t->same([], $plan()['next']['missingOld']),
    'schema generated trigger reparse current source changed columns field' => static fn (TestRunner $t) => $t->same(true, in_array('columns', $plan()['changedFields'], true)),
    'schema generated trigger reparse current source changed generated field' => static fn (TestRunner $t) => $t->same(true, in_array('generatedColumns', $plan()['changedFields'], true)),
    'schema generated trigger reparse current source changed missing new field' => static fn (TestRunner $t) => $t->same(true, in_array('missingNew', $plan()['changedFields'], true)),
    'schema generated trigger reparse current source changed missing old field' => static fn (TestRunner $t) => $t->same(true, in_array('missingOld', $plan()['changedFields'], true)),
    'schema generated trigger reparse current source dependency audit table' => static fn (TestRunner $t) => $t->same(['schema' => null, 'name' => 'wp_option_audit'], $plan()['current']['bodyDependencies'][0]),
    'schema generated trigger reparse current source stored detail after ddl' => static fn (TestRunner $t) => $t->same('STORED', $plan()['next']['generatedDetails'][0]['storage']),
    'schema generated trigger reparse current source virtual detail after ddl' => static fn (TestRunner $t) => $t->same('VIRTUAL', $plan()['next']['generatedDetails'][1]['storage']),
    'schema generated trigger reparse current source expression length retained' => static fn (TestRunner $t) => $t->same('length(option_value)', $plan()['next']['generatedDetails'][1]['expression']),
    'schema generated trigger reparse current source expression case retained' => static fn (TestRunner $t) => $t->same("CASE WHEN autoload = 'yes' THEN 'autoloaded' ELSE 'manual' END", $plan()['next']['generatedDetails'][2]['expression']),
    'schema generated trigger reparse current source dependency ddl reparse' => static fn (TestRunner $t) => $t->same(true, in_array('sqlite-schema-ddl-reparse', $plan()['dependencies'], true)),
    'schema generated trigger reparse current source dependency generated catalog' => static fn (TestRunner $t) => $t->same(true, in_array('sqlite-generated-column-catalog', $plan()['dependencies'], true)),
    'schema generated trigger reparse current source dependency trigger current source' => static fn (TestRunner $t) => $t->same(true, in_array('sqlite-trigger-current-source', $plan()['dependencies'], true)),

    'schema generated trigger reparse current source stable status' => static fn (TestRunner $t) => $t->same('stable', $stable()['status']),
    'schema generated trigger reparse current source stable no cookie change' => static fn (TestRunner $t) => $t->same(false, $stable()['schema_cookie_changed']),
    'schema generated trigger reparse current source stable no reparse' => static fn (TestRunner $t) => $t->same(false, $stable()['requiresReparse']),
    'schema generated trigger reparse current source stable no fields' => static fn (TestRunner $t) => $t->same([], $stable()['changedFields']),
    'schema generated trigger reparse current source stable no added' => static fn (TestRunner $t) => $t->same([], $stable()['generatedAdded']),
    'schema generated trigger reparse current source stable resolved current' => static fn (TestRunner $t) => $t->same('resolved', $stable()['current']['status']),
    'schema generated trigger reparse current source stable resolved next' => static fn (TestRunner $t) => $t->same('resolved', $stable()['next']['status']),

    'schema generated trigger reparse current source delete old generated current' => static fn (TestRunner $t) => $t->same(['title_key'], $deletePlan()['current']['referencedGeneratedOld']),
    'schema generated trigger reparse current source delete old generated removed' => static fn (TestRunner $t) => $t->same(['title_key'], $deletePlan()['generatedRemoved']),
    'schema generated trigger reparse current source delete next unresolved' => static fn (TestRunner $t) => $t->same('unresolved', $deletePlan()['next']['status']),
    'schema generated trigger reparse current source delete missing old after drop' => static fn (TestRunner $t) => $t->same(['title_key'], $deletePlan()['next']['missingOld']),
    'schema generated trigger reparse current source delete reparse required' => static fn (TestRunner $t) => $t->same(true, $deletePlan()['requiresReparse']),
    'schema generated trigger reparse current source delete changed old field' => static fn (TestRunner $t) => $t->same(true, in_array('missingOld', $deletePlan()['changedFields'], true)),

    'schema generated trigger reparse current source rejects missing trigger' => static function (TestRunner $t) use ($currentRecords, $nextRecords): void {
        try {
            SQLiteSchemaGeneratedTriggerReparsePlan::currentNext($currentRecords(), $nextRecords(), 'missing_trigger');
        } catch (InvalidArgumentException) {
            $t->same('rejected', 'rejected');
            return;
        }
        $t->same('rejected', 'missed');
    },
    'schema generated trigger reparse current source rejects missing table' => static function (TestRunner $t) use ($record): void {
        try {
            SQLiteSchemaGeneratedTriggerReparsePlan::currentNext([
                $record('trigger', 'bad_trigger', 'missing', 0, 'CREATE TRIGGER bad_trigger AFTER INSERT ON missing BEGIN SELECT new.id; END', 1),
            ], [
                $record('trigger', 'bad_trigger', 'missing', 0, 'CREATE TRIGGER bad_trigger AFTER INSERT ON missing BEGIN SELECT new.id; END', 1),
            ], 'bad_trigger');
        } catch (InvalidArgumentException) {
            $t->same('rejected', 'rejected');
            return;
        }
        $t->same('rejected', 'missed');
    },
    'schema generated trigger reparse current source rejects bad schema version' => static function (TestRunner $t) use ($currentRecords, $nextRecords): void {
        try {
            SQLiteSchemaGeneratedTriggerReparsePlan::currentNext($currentRecords(), $nextRecords(), 'wp_options_audit_update', ['schema_version_before' => -1]);
        } catch (InvalidArgumentException) {
            $t->same('rejected', 'rejected');
            return;
        }
        $t->same('rejected', 'missed');
    },
];
