<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteSchemaAlterGeneratedTriggerViewPlan;
use PortLibs\LibSqlite\SQLiteSchemaRecord;

$recordFactory = static fn (string $type, string $name, string $table, ?int $root, ?string $sql, int $rowid): SQLiteSchemaRecord => new SQLiteSchemaRecord($type, $name, $table, $root, $sql, $rowid);

$recordsFactory = static fn (): array => [
    $recordFactory('table', 'app_settings', 'app_settings', 2, <<<'SQL'
CREATE TABLE app_settings(
  setting_id INTEGER PRIMARY KEY,
  key_name TEXT NOT NULL,
  key_value TEXT NOT NULL DEFAULT '',
  load_policy TEXT NOT NULL DEFAULT 'yes',
  key_slug TEXT GENERATED ALWAYS AS (lower(key_name)) STORED
)
SQL, 1),
    $recordFactory('view', 'app_loadable_settings', 'app_loadable_settings', 0, <<<'SQL'
CREATE VIEW app_loadable_settings AS
  SELECT setting_id, key_name, key_slug, key_value_len
  FROM app_settings
  WHERE load_policy = 'yes'
SQL, 2),
    $recordFactory('trigger', 'app_settings_au', 'app_settings', 0, <<<'SQL'
CREATE TRIGGER app_settings_au AFTER UPDATE OF key_value ON app_settings BEGIN
  INSERT INTO app_setting_audit(setting_id, key_name, key_value_len)
  VALUES(new.setting_id, new.key_name, new.key_value_len);
END
SQL, 3),
    $recordFactory('view', 'app_article_titles', 'app_article_titles', 0, 'CREATE VIEW app_article_titles AS SELECT article_title FROM app_articles', 4),
    $recordFactory('trigger', 'app_articles_ai', 'app_articles', 0, 'CREATE TRIGGER app_articles_ai AFTER INSERT ON app_articles BEGIN SELECT new.article_title; END', 5),
];

$preparedFactory = static fn (): array => [
    ['name' => 'loadable-setting-view', 'sql' => 'SELECT key_name, key_value_len FROM app_loadable_settings ORDER BY key_name', 'columns' => ['key_name', 'key_value_len'], 'schema_version' => 116],
    ['name' => 'direct-setting-table', 'sql' => 'SELECT key_value_len FROM app_settings WHERE key_name = ?', 'columns' => ['key_value_len'], 'schema_version' => 116],
    ['name' => 'unrelated-article-view', 'sql' => 'SELECT article_title FROM app_article_titles', 'columns' => ['article_title'], 'schema_version' => 116],
];

$planFactory = static fn (): array => SQLiteSchemaAlterGeneratedTriggerViewPlan::plan(
    $recordsFactory(),
    'app_settings',
    'ALTER TABLE app_settings ADD COLUMN key_value_len INTEGER GENERATED ALWAYS AS (length(key_value)) VIRTUAL',
    $preparedFactory(),
    ['schema_version_before' => 116, 'schema_version_after' => 117],
);

$stableFactory = static fn (): array => SQLiteSchemaAlterGeneratedTriggerViewPlan::plan(
    $recordsFactory(),
    'app_settings',
    'ALTER TABLE app_settings ADD COLUMN key_value_len INTEGER GENERATED ALWAYS AS (length(key_value)) VIRTUAL',
    [],
    ['schema_version_before' => 117, 'schema_version_after' => 117],
);

return [
    'schema alter generated trigger view current source operation' => static fn (TestRunner $t) => $t->same('schema-alter-generated-trigger-view-current-source', $planFactory()['operation']),
    'schema alter generated trigger view current source table' => static fn (TestRunner $t) => $t->same('app_settings', $planFactory()['table']),
    'schema alter generated trigger view current source schema before' => static fn (TestRunner $t) => $t->same(116, $planFactory()['schema_version_before']),
    'schema alter generated trigger view current source schema after' => static fn (TestRunner $t) => $t->same(117, $planFactory()['schema_version_after']),
    'schema alter generated trigger view current source cookie changes' => static fn (TestRunner $t) => $t->same(true, $planFactory()['schema_cookie_changed']),
    'schema alter generated trigger view current source requires reparse' => static fn (TestRunner $t) => $t->same(true, $planFactory()['requiresReparse']),
    'schema alter generated trigger view current source status' => static fn (TestRunner $t) => $t->same('reparse-required', $planFactory()['status']),
    'schema alter generated trigger view current source added generated name' => static fn (TestRunner $t) => $t->same('key_value_len', $planFactory()['addedGeneratedColumn']['name']),
    'schema alter generated trigger view current source added generated storage' => static fn (TestRunner $t) => $t->same('VIRTUAL', $planFactory()['addedGeneratedColumn']['storage']),
    'schema alter generated trigger view current source added generated expression' => static fn (TestRunner $t) => $t->same('length(key_value)', $planFactory()['addedGeneratedColumn']['expression']),
    'schema alter generated trigger view current source source retained' => static fn (TestRunner $t) => $t->same('key_value_len INTEGER GENERATED ALWAYS AS (length(key_value)) VIRTUAL', $planFactory()['addedGeneratedColumn']['source']),
    'schema alter generated trigger view current source current generated columns' => static fn (TestRunner $t) => $t->same(['key_slug'], $planFactory()['currentGeneratedColumns']),
    'schema alter generated trigger view current source next generated columns' => static fn (TestRunner $t) => $t->same(['key_slug', 'key_value_len'], $planFactory()['nextGeneratedColumns']),
    'schema alter generated trigger view current source generated added' => static fn (TestRunner $t) => $t->same(['key_value_len'], $planFactory()['generatedAdded']),
    'schema alter generated trigger view current source one dependent view' => static fn (TestRunner $t) => $t->same(1, count($planFactory()['views'])),
    'schema alter generated trigger view current source view name' => static fn (TestRunner $t) => $t->same('app_loadable_settings', $planFactory()['views'][0]['name']),
    'schema alter generated trigger view current source view depends on target' => static fn (TestRunner $t) => $t->same(true, $planFactory()['views'][0]['dependsOnTarget']),
    'schema alter generated trigger view current source view references setting id' => static fn (TestRunner $t) => $t->same(true, in_array('setting_id', $planFactory()['views'][0]['references'], true)),
    'schema alter generated trigger view current source view references generated old' => static fn (TestRunner $t) => $t->same(true, in_array('key_slug', $planFactory()['views'][0]['references'], true)),
    'schema alter generated trigger view current source view references generated new' => static fn (TestRunner $t) => $t->same(true, in_array('key_value_len', $planFactory()['views'][0]['references'], true)),
    'schema alter generated trigger view current source view current unresolved' => static fn (TestRunner $t) => $t->same('unresolved', $planFactory()['views'][0]['current']['status']),
    'schema alter generated trigger view current source view next resolved' => static fn (TestRunner $t) => $t->same('resolved', $planFactory()['views'][0]['next']['status']),
    'schema alter generated trigger view current source view current missing generated' => static fn (TestRunner $t) => $t->same(['key_value_len'], $planFactory()['views'][0]['current']['missing']),
    'schema alter generated trigger view current source view next missing empty' => static fn (TestRunner $t) => $t->same([], $planFactory()['views'][0]['next']['missing']),
    'schema alter generated trigger view current source view next generated refs' => static fn (TestRunner $t) => $t->same(['key_slug', 'key_value_len'], $planFactory()['views'][0]['next']['generated']),
    'schema alter generated trigger view current source view ordinary refs include load_policy' => static fn (TestRunner $t) => $t->same(true, in_array('load_policy', $planFactory()['views'][0]['next']['ordinary'], true)),
    'schema alter generated trigger view current source resolved view listed' => static fn (TestRunner $t) => $t->same(['app_loadable_settings'], $planFactory()['resolvedViews']),
    'schema alter generated trigger view current source one dependent trigger' => static fn (TestRunner $t) => $t->same(1, count($planFactory()['triggers'])),
    'schema alter generated trigger view current source trigger name' => static fn (TestRunner $t) => $t->same('app_settings_au', $planFactory()['triggers'][0]['name']),
    'schema alter generated trigger view current source trigger event' => static fn (TestRunner $t) => $t->same('update', $planFactory()['triggers'][0]['event']),
    'schema alter generated trigger view current source trigger depends on target' => static fn (TestRunner $t) => $t->same(true, $planFactory()['triggers'][0]['dependsOnTarget']),
    'schema alter generated trigger view current source trigger references new generated' => static fn (TestRunner $t) => $t->same(true, in_array('key_value_len', $planFactory()['triggers'][0]['references'], true)),
    'schema alter generated trigger view current source trigger current unresolved' => static fn (TestRunner $t) => $t->same('unresolved', $planFactory()['triggers'][0]['current']['status']),
    'schema alter generated trigger view current source trigger next resolved' => static fn (TestRunner $t) => $t->same('resolved', $planFactory()['triggers'][0]['next']['status']),
    'schema alter generated trigger view current source trigger current missing' => static fn (TestRunner $t) => $t->same(['key_value_len'], $planFactory()['triggers'][0]['current']['missing']),
    'schema alter generated trigger view current source trigger next generated' => static fn (TestRunner $t) => $t->same(['key_value_len'], $planFactory()['triggers'][0]['next']['generated']),
    'schema alter generated trigger view current source resolved trigger listed' => static fn (TestRunner $t) => $t->same(['app_settings_au'], $planFactory()['resolvedTriggers']),
    'schema alter generated trigger view current source invalidates two statements' => static fn (TestRunner $t) => $t->same(2, count($planFactory()['invalidatedStatements'])),
    'schema alter generated trigger view current source invalidates view statement' => static fn (TestRunner $t) => $t->same('loadable-setting-view', $planFactory()['invalidatedStatements'][0]['name']),
    'schema alter generated trigger view current source invalidates direct statement' => static fn (TestRunner $t) => $t->same('direct-setting-table', $planFactory()['invalidatedStatements'][1]['name']),
    'schema alter generated trigger view current source invalidation reason' => static fn (TestRunner $t) => $t->same('schema-cookie-changed', $planFactory()['invalidatedStatements'][0]['reason']),
    'schema alter generated trigger view current source invalidation old version' => static fn (TestRunner $t) => $t->same(116, $planFactory()['invalidatedStatements'][0]['schema_version']),
    'schema alter generated trigger view current source invalidation columns retained' => static fn (TestRunner $t) => $t->same(['key_name', 'key_value_len'], $planFactory()['invalidatedStatements'][0]['columns']),
    'schema alter generated trigger view current source skips unrelated prepared statement' => static fn (TestRunner $t) => $t->same(false, in_array('unrelated-article-view', array_column($planFactory()['invalidatedStatements'], 'name'), true)),
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
        $t->throws(InvalidArgumentException::class, static fn () => SQLiteSchemaAlterGeneratedTriggerViewPlan::plan($recordsFactory(), 'app_settings', 'ALTER TABLE app_articles ADD COLUMN x TEXT AS (lower(y))'));
    },
    'schema alter generated trigger view current source rejects ordinary add column' => static function (TestRunner $t) use ($recordsFactory): void {
        $t->throws(InvalidArgumentException::class, static fn () => SQLiteSchemaAlterGeneratedTriggerViewPlan::plan($recordsFactory(), 'app_settings', 'ALTER TABLE app_settings ADD COLUMN x TEXT'));
    },
    'schema alter generated trigger view current source rejects duplicate column' => static function (TestRunner $t) use ($recordsFactory): void {
        $t->throws(InvalidArgumentException::class, static fn () => SQLiteSchemaAlterGeneratedTriggerViewPlan::plan($recordsFactory(), 'app_settings', 'ALTER TABLE app_settings ADD COLUMN key_slug TEXT AS (lower(key_name))'));
    },
    'schema alter generated trigger view current source rejects bad schema version' => static function (TestRunner $t) use ($recordsFactory): void {
        $t->throws(InvalidArgumentException::class, static fn () => SQLiteSchemaAlterGeneratedTriggerViewPlan::plan($recordsFactory(), 'app_settings', 'ALTER TABLE app_settings ADD COLUMN x TEXT AS (lower(key_name))', [], ['schema_version_before' => -1]));
    },
];
