<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteSchemaAlterGeneratedTriggerViewCurrentSourceNextPlan;
use PortLibs\LibSqlite\SQLiteSchemaRecord;

$record117 = static fn (string $type, string $name, string $table, ?int $root, ?string $sql, int $rowid): SQLiteSchemaRecord => new SQLiteSchemaRecord($type, $name, $table, $root, $sql, $rowid);

$records117 = static fn (): array => [
    $record117('table', 'wp_options', 'wp_options', 2, <<<'SQL'
CREATE TABLE wp_options(
  option_id INTEGER PRIMARY KEY,
  option_name TEXT NOT NULL,
  option_value TEXT NOT NULL DEFAULT '',
  autoload TEXT NOT NULL DEFAULT 'yes',
  option_slug TEXT GENERATED ALWAYS AS (lower(option_name)) STORED
)
SQL, 1),
    $record117('view', 'wp_autoloaded_options', 'wp_autoloaded_options', 0, <<<'SQL'
CREATE VIEW wp_autoloaded_options AS
  SELECT option_id, option_name, option_slug, option_value_len
  FROM wp_options
  WHERE autoload = 'yes'
SQL, 2),
    $record117('trigger', 'wp_options_au', 'wp_options', 0, <<<'SQL'
CREATE TRIGGER wp_options_au AFTER UPDATE OF option_value ON wp_options BEGIN
  INSERT INTO wp_option_audit(option_id, option_name, option_value_len)
  VALUES(new.option_id, new.option_name, new.option_value_len);
END
SQL, 3),
    $record117('view', 'wp_post_titles', 'wp_post_titles', 0, 'CREATE VIEW wp_post_titles AS SELECT post_title FROM wp_posts', 4),
    $record117('trigger', 'wp_posts_ai', 'wp_posts', 0, 'CREATE TRIGGER wp_posts_ai AFTER INSERT ON wp_posts BEGIN SELECT new.post_title; END', 5),
];

$prepared117 = static fn (): array => [
    ['name' => 'autoloaded-option-view', 'sql' => 'SELECT option_name, option_value_len FROM wp_autoloaded_options ORDER BY option_name', 'columns' => ['option_name', 'option_value_len'], 'schema_version' => 116],
    ['name' => 'direct-option-table', 'sql' => 'SELECT option_value_len FROM wp_options WHERE option_name = ?', 'columns' => ['option_value_len'], 'schema_version' => 116],
    ['name' => 'unrelated-post-view', 'sql' => 'SELECT post_title FROM wp_post_titles', 'columns' => ['post_title'], 'schema_version' => 116],
];

$plan117 = static fn (): array => SQLiteSchemaAlterGeneratedTriggerViewCurrentSourceNextPlan::plan(
    $records117(),
    'wp_options',
    'ALTER TABLE wp_options ADD COLUMN option_value_len INTEGER GENERATED ALWAYS AS (length(option_value)) VIRTUAL',
    $prepared117(),
    ['schema_version_before' => 116, 'schema_version_after' => 117],
);

$stable117 = static fn (): array => SQLiteSchemaAlterGeneratedTriggerViewCurrentSourceNextPlan::plan(
    $records117(),
    'wp_options',
    'ALTER TABLE wp_options ADD COLUMN option_value_len INTEGER GENERATED ALWAYS AS (length(option_value)) VIRTUAL',
    [],
    ['schema_version_before' => 117, 'schema_version_after' => 117],
);

return [
    'schema alter generated trigger view current source next117 operation' => static fn (TestRunner $t) => $t->same('schema-alter-generated-trigger-view-current-source-next117', $plan117()['operation']),
    'schema alter generated trigger view current source next117 table' => static fn (TestRunner $t) => $t->same('wp_options', $plan117()['table']),
    'schema alter generated trigger view current source next117 schema before' => static fn (TestRunner $t) => $t->same(116, $plan117()['schema_version_before']),
    'schema alter generated trigger view current source next117 schema after' => static fn (TestRunner $t) => $t->same(117, $plan117()['schema_version_after']),
    'schema alter generated trigger view current source next117 cookie changes' => static fn (TestRunner $t) => $t->same(true, $plan117()['schema_cookie_changed']),
    'schema alter generated trigger view current source next117 requires reparse' => static fn (TestRunner $t) => $t->same(true, $plan117()['requiresReparse']),
    'schema alter generated trigger view current source next117 status' => static fn (TestRunner $t) => $t->same('reparse-required', $plan117()['status']),
    'schema alter generated trigger view current source next117 added generated name' => static fn (TestRunner $t) => $t->same('option_value_len', $plan117()['addedGeneratedColumn']['name']),
    'schema alter generated trigger view current source next117 added generated storage' => static fn (TestRunner $t) => $t->same('VIRTUAL', $plan117()['addedGeneratedColumn']['storage']),
    'schema alter generated trigger view current source next117 added generated expression' => static fn (TestRunner $t) => $t->same('length(option_value)', $plan117()['addedGeneratedColumn']['expression']),
    'schema alter generated trigger view current source next117 source retained' => static fn (TestRunner $t) => $t->same('option_value_len INTEGER GENERATED ALWAYS AS (length(option_value)) VIRTUAL', $plan117()['addedGeneratedColumn']['source']),
    'schema alter generated trigger view current source next117 current generated columns' => static fn (TestRunner $t) => $t->same(['option_slug'], $plan117()['currentGeneratedColumns']),
    'schema alter generated trigger view current source next117 next generated columns' => static fn (TestRunner $t) => $t->same(['option_slug', 'option_value_len'], $plan117()['nextGeneratedColumns']),
    'schema alter generated trigger view current source next117 generated added' => static fn (TestRunner $t) => $t->same(['option_value_len'], $plan117()['generatedAdded']),
    'schema alter generated trigger view current source next117 one dependent view' => static fn (TestRunner $t) => $t->same(1, count($plan117()['views'])),
    'schema alter generated trigger view current source next117 view name' => static fn (TestRunner $t) => $t->same('wp_autoloaded_options', $plan117()['views'][0]['name']),
    'schema alter generated trigger view current source next117 view depends on target' => static fn (TestRunner $t) => $t->same(true, $plan117()['views'][0]['dependsOnTarget']),
    'schema alter generated trigger view current source next117 view references option id' => static fn (TestRunner $t) => $t->same(true, in_array('option_id', $plan117()['views'][0]['references'], true)),
    'schema alter generated trigger view current source next117 view references generated old' => static fn (TestRunner $t) => $t->same(true, in_array('option_slug', $plan117()['views'][0]['references'], true)),
    'schema alter generated trigger view current source next117 view references generated new' => static fn (TestRunner $t) => $t->same(true, in_array('option_value_len', $plan117()['views'][0]['references'], true)),
    'schema alter generated trigger view current source next117 view current unresolved' => static fn (TestRunner $t) => $t->same('unresolved', $plan117()['views'][0]['current']['status']),
    'schema alter generated trigger view current source next117 view next resolved' => static fn (TestRunner $t) => $t->same('resolved', $plan117()['views'][0]['next']['status']),
    'schema alter generated trigger view current source next117 view current missing generated' => static fn (TestRunner $t) => $t->same(['option_value_len'], $plan117()['views'][0]['current']['missing']),
    'schema alter generated trigger view current source next117 view next missing empty' => static fn (TestRunner $t) => $t->same([], $plan117()['views'][0]['next']['missing']),
    'schema alter generated trigger view current source next117 view next generated refs' => static fn (TestRunner $t) => $t->same(['option_slug', 'option_value_len'], $plan117()['views'][0]['next']['generated']),
    'schema alter generated trigger view current source next117 view ordinary refs include autoload' => static fn (TestRunner $t) => $t->same(true, in_array('autoload', $plan117()['views'][0]['next']['ordinary'], true)),
    'schema alter generated trigger view current source next117 resolved view listed' => static fn (TestRunner $t) => $t->same(['wp_autoloaded_options'], $plan117()['resolvedViews']),
    'schema alter generated trigger view current source next117 one dependent trigger' => static fn (TestRunner $t) => $t->same(1, count($plan117()['triggers'])),
    'schema alter generated trigger view current source next117 trigger name' => static fn (TestRunner $t) => $t->same('wp_options_au', $plan117()['triggers'][0]['name']),
    'schema alter generated trigger view current source next117 trigger event' => static fn (TestRunner $t) => $t->same('update', $plan117()['triggers'][0]['event']),
    'schema alter generated trigger view current source next117 trigger depends on target' => static fn (TestRunner $t) => $t->same(true, $plan117()['triggers'][0]['dependsOnTarget']),
    'schema alter generated trigger view current source next117 trigger references new generated' => static fn (TestRunner $t) => $t->same(true, in_array('option_value_len', $plan117()['triggers'][0]['references'], true)),
    'schema alter generated trigger view current source next117 trigger current unresolved' => static fn (TestRunner $t) => $t->same('unresolved', $plan117()['triggers'][0]['current']['status']),
    'schema alter generated trigger view current source next117 trigger next resolved' => static fn (TestRunner $t) => $t->same('resolved', $plan117()['triggers'][0]['next']['status']),
    'schema alter generated trigger view current source next117 trigger current missing' => static fn (TestRunner $t) => $t->same(['option_value_len'], $plan117()['triggers'][0]['current']['missing']),
    'schema alter generated trigger view current source next117 trigger next generated' => static fn (TestRunner $t) => $t->same(['option_value_len'], $plan117()['triggers'][0]['next']['generated']),
    'schema alter generated trigger view current source next117 resolved trigger listed' => static fn (TestRunner $t) => $t->same(['wp_options_au'], $plan117()['resolvedTriggers']),
    'schema alter generated trigger view current source next117 invalidates two statements' => static fn (TestRunner $t) => $t->same(2, count($plan117()['invalidatedStatements'])),
    'schema alter generated trigger view current source next117 invalidates view statement' => static fn (TestRunner $t) => $t->same('autoloaded-option-view', $plan117()['invalidatedStatements'][0]['name']),
    'schema alter generated trigger view current source next117 invalidates direct statement' => static fn (TestRunner $t) => $t->same('direct-option-table', $plan117()['invalidatedStatements'][1]['name']),
    'schema alter generated trigger view current source next117 invalidation reason' => static fn (TestRunner $t) => $t->same('schema-cookie-changed', $plan117()['invalidatedStatements'][0]['reason']),
    'schema alter generated trigger view current source next117 invalidation old version' => static fn (TestRunner $t) => $t->same(116, $plan117()['invalidatedStatements'][0]['schema_version']),
    'schema alter generated trigger view current source next117 invalidation columns retained' => static fn (TestRunner $t) => $t->same(['option_name', 'option_value_len'], $plan117()['invalidatedStatements'][0]['columns']),
    'schema alter generated trigger view current source next117 skips unrelated prepared statement' => static fn (TestRunner $t) => $t->same(false, in_array('unrelated-post-view', array_column($plan117()['invalidatedStatements'], 'name'), true)),
    'schema alter generated trigger view current source next117 dependency alter generated' => static fn (TestRunner $t) => $t->same(true, in_array('sqlite-alter-table-add-generated-column', $plan117()['dependencies'], true)),
    'schema alter generated trigger view current source next117 dependency cookie reprepare' => static fn (TestRunner $t) => $t->same(true, in_array('sqlite-schema-cookie-reprepare', $plan117()['dependencies'], true)),
    'schema alter generated trigger view current source next117 dependency trigger source' => static fn (TestRunner $t) => $t->same(true, in_array('sqlite-trigger-current-source', $plan117()['dependencies'], true)),
    'schema alter generated trigger view current source next117 dependency view source' => static fn (TestRunner $t) => $t->same(true, in_array('sqlite-view-current-source', $plan117()['dependencies'], true)),
    'schema alter generated trigger view current source next117 stable cookie unchanged' => static fn (TestRunner $t) => $t->same(false, $stable117()['schema_cookie_changed']),
    'schema alter generated trigger view current source next117 stable no reparse without cookie' => static fn (TestRunner $t) => $t->same(false, $stable117()['requiresReparse']),
    'schema alter generated trigger view current source next117 stable still reports dependent schema' => static fn (TestRunner $t) => $t->same('reparse-required', $stable117()['status']),
    'schema alter generated trigger view current source next117 stable no prepared invalidations' => static fn (TestRunner $t) => $t->same([], $stable117()['invalidatedStatements']),
    'schema alter generated trigger view current source next117 rejects missing table' => static function (TestRunner $t) use ($records117): void {
        $t->throws(InvalidArgumentException::class, static fn () => SQLiteSchemaAlterGeneratedTriggerViewCurrentSourceNextPlan::plan($records117(), 'missing', 'ALTER TABLE missing ADD COLUMN x TEXT AS (lower(y))'));
    },
    'schema alter generated trigger view current source next117 rejects target mismatch' => static function (TestRunner $t) use ($records117): void {
        $t->throws(InvalidArgumentException::class, static fn () => SQLiteSchemaAlterGeneratedTriggerViewCurrentSourceNextPlan::plan($records117(), 'wp_options', 'ALTER TABLE wp_posts ADD COLUMN x TEXT AS (lower(y))'));
    },
    'schema alter generated trigger view current source next117 rejects ordinary add column' => static function (TestRunner $t) use ($records117): void {
        $t->throws(InvalidArgumentException::class, static fn () => SQLiteSchemaAlterGeneratedTriggerViewCurrentSourceNextPlan::plan($records117(), 'wp_options', 'ALTER TABLE wp_options ADD COLUMN x TEXT'));
    },
    'schema alter generated trigger view current source next117 rejects duplicate column' => static function (TestRunner $t) use ($records117): void {
        $t->throws(InvalidArgumentException::class, static fn () => SQLiteSchemaAlterGeneratedTriggerViewCurrentSourceNextPlan::plan($records117(), 'wp_options', 'ALTER TABLE wp_options ADD COLUMN option_slug TEXT AS (lower(option_name))'));
    },
    'schema alter generated trigger view current source next117 rejects bad schema version' => static function (TestRunner $t) use ($records117): void {
        $t->throws(InvalidArgumentException::class, static fn () => SQLiteSchemaAlterGeneratedTriggerViewCurrentSourceNextPlan::plan($records117(), 'wp_options', 'ALTER TABLE wp_options ADD COLUMN x TEXT AS (lower(option_name))', [], ['schema_version_before' => -1]));
    },
];
