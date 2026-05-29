<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteSchemaAlterGeneratedTriggerViewPlan;
use PortLibs\LibSqlite\SQLiteSchemaRecord;

$recordFactory = static fn (string $type, string $name, string $table, ?int $root, ?string $sql, int $rowid): SQLiteSchemaRecord => new SQLiteSchemaRecord($type, $name, $table, $root, $sql, $rowid);

$recordsFactory = static fn (): array => [
    $recordFactory('table', 'wp_options', 'wp_options', 2, <<<'SQL'
CREATE TABLE wp_options(
  option_id INTEGER PRIMARY KEY,
  option_name TEXT NOT NULL,
  option_value TEXT NOT NULL DEFAULT '',
  autoload TEXT NOT NULL DEFAULT 'yes',
  option_slug TEXT GENERATED ALWAYS AS (lower(option_name)) STORED
)
SQL, 1),
    $recordFactory('view', 'wp_autoloaded_options', 'wp_autoloaded_options', 0, <<<'SQL'
CREATE VIEW wp_autoloaded_options AS
  SELECT option_id, option_name, option_slug, option_value_len
  FROM wp_options
  WHERE autoload = 'yes'
SQL, 2),
    $recordFactory('trigger', 'wp_options_au', 'wp_options', 0, <<<'SQL'
CREATE TRIGGER wp_options_au AFTER UPDATE OF option_value ON wp_options BEGIN
  INSERT INTO wp_option_audit(option_id, option_name, option_value_len)
  VALUES(new.option_id, new.option_name, new.option_value_len);
END
SQL, 3),
    $recordFactory('view', 'wp_post_titles', 'wp_post_titles', 0, 'CREATE VIEW wp_post_titles AS SELECT post_title FROM wp_posts', 4),
    $recordFactory('trigger', 'wp_posts_ai', 'wp_posts', 0, 'CREATE TRIGGER wp_posts_ai AFTER INSERT ON wp_posts BEGIN SELECT new.post_title; END', 5),
];

$preparedFactory = static fn (): array => [
    ['name' => 'autoloaded-option-view', 'sql' => 'SELECT option_name, option_value_len FROM wp_autoloaded_options ORDER BY option_name', 'columns' => ['option_name', 'option_value_len'], 'schema_version' => 116],
    ['name' => 'direct-option-table', 'sql' => 'SELECT option_value_len FROM wp_options WHERE option_name = ?', 'columns' => ['option_value_len'], 'schema_version' => 116],
    ['name' => 'unrelated-post-view', 'sql' => 'SELECT post_title FROM wp_post_titles', 'columns' => ['post_title'], 'schema_version' => 116],
];

$planFactory = static fn (): array => SQLiteSchemaAlterGeneratedTriggerViewPlan::plan(
    $recordsFactory(),
    'wp_options',
    'ALTER TABLE wp_options ADD COLUMN option_value_len INTEGER GENERATED ALWAYS AS (length(option_value)) VIRTUAL',
    $preparedFactory(),
    ['schema_version_before' => 116, 'schema_version_after' => 117],
);

$stableFactory = static fn (): array => SQLiteSchemaAlterGeneratedTriggerViewPlan::plan(
    $recordsFactory(),
    'wp_options',
    'ALTER TABLE wp_options ADD COLUMN option_value_len INTEGER GENERATED ALWAYS AS (length(option_value)) VIRTUAL',
    [],
    ['schema_version_before' => 117, 'schema_version_after' => 117],
);

return [
    'schema alter generated trigger view current source operation' => static fn (TestRunner $t) => $t->same('schema-alter-generated-trigger-view-current-source', $planFactory()['operation']),
    'schema alter generated trigger view current source table' => static fn (TestRunner $t) => $t->same('wp_options', $planFactory()['table']),
    'schema alter generated trigger view current source schema before' => static fn (TestRunner $t) => $t->same(116, $planFactory()['schema_version_before']),
    'schema alter generated trigger view current source schema after' => static fn (TestRunner $t) => $t->same(117, $planFactory()['schema_version_after']),
    'schema alter generated trigger view current source cookie changes' => static fn (TestRunner $t) => $t->same(true, $planFactory()['schema_cookie_changed']),
    'schema alter generated trigger view current source requires reparse' => static fn (TestRunner $t) => $t->same(true, $planFactory()['requiresReparse']),
    'schema alter generated trigger view current source status' => static fn (TestRunner $t) => $t->same('reparse-required', $planFactory()['status']),
    'schema alter generated trigger view current source added generated name' => static fn (TestRunner $t) => $t->same('option_value_len', $planFactory()['addedGeneratedColumn']['name']),
    'schema alter generated trigger view current source added generated storage' => static fn (TestRunner $t) => $t->same('VIRTUAL', $planFactory()['addedGeneratedColumn']['storage']),
    'schema alter generated trigger view current source added generated expression' => static fn (TestRunner $t) => $t->same('length(option_value)', $planFactory()['addedGeneratedColumn']['expression']),
    'schema alter generated trigger view current source source retained' => static fn (TestRunner $t) => $t->same('option_value_len INTEGER GENERATED ALWAYS AS (length(option_value)) VIRTUAL', $planFactory()['addedGeneratedColumn']['source']),
    'schema alter generated trigger view current source current generated columns' => static fn (TestRunner $t) => $t->same(['option_slug'], $planFactory()['currentGeneratedColumns']),
    'schema alter generated trigger view current source next generated columns' => static fn (TestRunner $t) => $t->same(['option_slug', 'option_value_len'], $planFactory()['nextGeneratedColumns']),
    'schema alter generated trigger view current source generated added' => static fn (TestRunner $t) => $t->same(['option_value_len'], $planFactory()['generatedAdded']),
    'schema alter generated trigger view current source one dependent view' => static fn (TestRunner $t) => $t->same(1, count($planFactory()['views'])),
    'schema alter generated trigger view current source view name' => static fn (TestRunner $t) => $t->same('wp_autoloaded_options', $planFactory()['views'][0]['name']),
    'schema alter generated trigger view current source view depends on target' => static fn (TestRunner $t) => $t->same(true, $planFactory()['views'][0]['dependsOnTarget']),
    'schema alter generated trigger view current source view references option id' => static fn (TestRunner $t) => $t->same(true, in_array('option_id', $planFactory()['views'][0]['references'], true)),
    'schema alter generated trigger view current source view references generated old' => static fn (TestRunner $t) => $t->same(true, in_array('option_slug', $planFactory()['views'][0]['references'], true)),
    'schema alter generated trigger view current source view references generated new' => static fn (TestRunner $t) => $t->same(true, in_array('option_value_len', $planFactory()['views'][0]['references'], true)),
    'schema alter generated trigger view current source view current unresolved' => static fn (TestRunner $t) => $t->same('unresolved', $planFactory()['views'][0]['current']['status']),
    'schema alter generated trigger view current source view next resolved' => static fn (TestRunner $t) => $t->same('resolved', $planFactory()['views'][0]['next']['status']),
    'schema alter generated trigger view current source view current missing generated' => static fn (TestRunner $t) => $t->same(['option_value_len'], $planFactory()['views'][0]['current']['missing']),
    'schema alter generated trigger view current source view next missing empty' => static fn (TestRunner $t) => $t->same([], $planFactory()['views'][0]['next']['missing']),
    'schema alter generated trigger view current source view next generated refs' => static fn (TestRunner $t) => $t->same(['option_slug', 'option_value_len'], $planFactory()['views'][0]['next']['generated']),
    'schema alter generated trigger view current source view ordinary refs include autoload' => static fn (TestRunner $t) => $t->same(true, in_array('autoload', $planFactory()['views'][0]['next']['ordinary'], true)),
    'schema alter generated trigger view current source resolved view listed' => static fn (TestRunner $t) => $t->same(['wp_autoloaded_options'], $planFactory()['resolvedViews']),
    'schema alter generated trigger view current source one dependent trigger' => static fn (TestRunner $t) => $t->same(1, count($planFactory()['triggers'])),
    'schema alter generated trigger view current source trigger name' => static fn (TestRunner $t) => $t->same('wp_options_au', $planFactory()['triggers'][0]['name']),
    'schema alter generated trigger view current source trigger event' => static fn (TestRunner $t) => $t->same('update', $planFactory()['triggers'][0]['event']),
    'schema alter generated trigger view current source trigger depends on target' => static fn (TestRunner $t) => $t->same(true, $planFactory()['triggers'][0]['dependsOnTarget']),
    'schema alter generated trigger view current source trigger references new generated' => static fn (TestRunner $t) => $t->same(true, in_array('option_value_len', $planFactory()['triggers'][0]['references'], true)),
    'schema alter generated trigger view current source trigger current unresolved' => static fn (TestRunner $t) => $t->same('unresolved', $planFactory()['triggers'][0]['current']['status']),
    'schema alter generated trigger view current source trigger next resolved' => static fn (TestRunner $t) => $t->same('resolved', $planFactory()['triggers'][0]['next']['status']),
    'schema alter generated trigger view current source trigger current missing' => static fn (TestRunner $t) => $t->same(['option_value_len'], $planFactory()['triggers'][0]['current']['missing']),
    'schema alter generated trigger view current source trigger next generated' => static fn (TestRunner $t) => $t->same(['option_value_len'], $planFactory()['triggers'][0]['next']['generated']),
    'schema alter generated trigger view current source resolved trigger listed' => static fn (TestRunner $t) => $t->same(['wp_options_au'], $planFactory()['resolvedTriggers']),
    'schema alter generated trigger view current source invalidates two statements' => static fn (TestRunner $t) => $t->same(2, count($planFactory()['invalidatedStatements'])),
    'schema alter generated trigger view current source invalidates view statement' => static fn (TestRunner $t) => $t->same('autoloaded-option-view', $planFactory()['invalidatedStatements'][0]['name']),
    'schema alter generated trigger view current source invalidates direct statement' => static fn (TestRunner $t) => $t->same('direct-option-table', $planFactory()['invalidatedStatements'][1]['name']),
    'schema alter generated trigger view current source invalidation reason' => static fn (TestRunner $t) => $t->same('schema-cookie-changed', $planFactory()['invalidatedStatements'][0]['reason']),
    'schema alter generated trigger view current source invalidation old version' => static fn (TestRunner $t) => $t->same(116, $planFactory()['invalidatedStatements'][0]['schema_version']),
    'schema alter generated trigger view current source invalidation columns retained' => static fn (TestRunner $t) => $t->same(['option_name', 'option_value_len'], $planFactory()['invalidatedStatements'][0]['columns']),
    'schema alter generated trigger view current source skips unrelated prepared statement' => static fn (TestRunner $t) => $t->same(false, in_array('unrelated-post-view', array_column($planFactory()['invalidatedStatements'], 'name'), true)),
    'schema alter generated trigger view current source dependency alter generated' => static fn (TestRunner $t) => $t->same(true, in_array('sqlite-alter-table-add-generated-column', $planFactory()['dependencies'], true)),
    'schema alter generated trigger view current source dependency cookie reprepare' => static fn (TestRunner $t) => $t->same(true, in_array('sqlite-schema-cookie-reprepare', $planFactory()['dependencies'], true)),
    'schema alter generated trigger view current source dependency trigger source' => static fn (TestRunner $t) => $t->same(true, in_array('sqlite-trigger-current-source', $planFactory()['dependencies'], true)),
    'schema alter generated trigger view current source dependency view source' => static fn (TestRunner $t) => $t->same(true, in_array('sqlite-view-current-source', $planFactory()['dependencies'], true)),
    'schema alter generated trigger view current source stable cookie unchanged' => static fn (TestRunner $t) => $t->same(false, $stableFactory()['schema_cookie_changed']),
    'schema alter generated trigger view current source stable no reparse without cookie' => static fn (TestRunner $t) => $t->same(false, $stableFactory()['requiresReparse']),
    'schema alter generated trigger view current source stable still reports dependent schema' => static fn (TestRunner $t) => $t->same('reparse-required', $stableFactory()['status']),
    'schema alter generated trigger view current source stable no prepared invalidations' => static fn (TestRunner $t) => $t->same([], $stableFactory()['invalidatedStatements']),
    'schema alter generated trigger view current source rejects missing table' => static function (TestRunner $t) use ($recordsFactory): void {
        $t->throws(InvalidArgumentException::class, static fn () => SQLiteSchemaAlterGeneratedTriggerViewPlan::plan($recordsFactory(), 'missing', 'ALTER TABLE missing ADD COLUMN x TEXT AS (lower(y))'));
    },
    'schema alter generated trigger view current source rejects target mismatch' => static function (TestRunner $t) use ($recordsFactory): void {
        $t->throws(InvalidArgumentException::class, static fn () => SQLiteSchemaAlterGeneratedTriggerViewPlan::plan($recordsFactory(), 'wp_options', 'ALTER TABLE wp_posts ADD COLUMN x TEXT AS (lower(y))'));
    },
    'schema alter generated trigger view current source rejects ordinary add column' => static function (TestRunner $t) use ($recordsFactory): void {
        $t->throws(InvalidArgumentException::class, static fn () => SQLiteSchemaAlterGeneratedTriggerViewPlan::plan($recordsFactory(), 'wp_options', 'ALTER TABLE wp_options ADD COLUMN x TEXT'));
    },
    'schema alter generated trigger view current source rejects duplicate column' => static function (TestRunner $t) use ($recordsFactory): void {
        $t->throws(InvalidArgumentException::class, static fn () => SQLiteSchemaAlterGeneratedTriggerViewPlan::plan($recordsFactory(), 'wp_options', 'ALTER TABLE wp_options ADD COLUMN option_slug TEXT AS (lower(option_name))'));
    },
    'schema alter generated trigger view current source rejects bad schema version' => static function (TestRunner $t) use ($recordsFactory): void {
        $t->throws(InvalidArgumentException::class, static fn () => SQLiteSchemaAlterGeneratedTriggerViewPlan::plan($recordsFactory(), 'wp_options', 'ALTER TABLE wp_options ADD COLUMN x TEXT AS (lower(option_name))', [], ['schema_version_before' => -1]));
    },
];
