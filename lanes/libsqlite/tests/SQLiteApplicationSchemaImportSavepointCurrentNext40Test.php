<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteSchemaImportSavepointPlan;

$batches = static fn (): array => [
    [
        'name' => 'core_schema',
        'dump' => <<<'SQL'
CREATE TABLE wp_options (
  option_id INTEGER PRIMARY KEY AUTOINCREMENT,
  option_name TEXT NOT NULL UNIQUE COLLATE NOCASE,
  option_value TEXT NOT NULL DEFAULT '',
  autoload TEXT NOT NULL DEFAULT 'yes'
);
CREATE UNIQUE INDEX wp_options_option_name ON wp_options(option_name COLLATE NOCASE);
SQL,
    ],
    [
        'name' => 'plugin_schema',
        'dump' => <<<'SQL'
CREATE TABLE wp_plugin_settings (
  option_name TEXT NOT NULL,
  setting_key TEXT NOT NULL,
  setting_value TEXT NOT NULL,
  PRIMARY KEY(option_name, setting_key)
);
CREATE INDEX wp_plugin_settings_option ON wp_plugin_settings(option_name);
CREATE VIEW wp_plugin_autoloaded AS SELECT option_name FROM wp_options WHERE autoload = 'yes';
CREATE TRIGGER wp_plugin_settings_ai AFTER INSERT ON wp_plugin_settings BEGIN
  SELECT 'plugin;setting';
END;
SQL,
        'release' => false,
    ],
    [
        'name' => 'duplicate_plugin',
        'dump' => 'CREATE TABLE wp_plugin_settings(id INTEGER);',
        'on_error' => 'rollback',
    ],
    [
        'name' => 'post_schema',
        'dump' => <<<'SQL'
CREATE TABLE wp_posts (
  ID INTEGER PRIMARY KEY,
  post_title TEXT NOT NULL DEFAULT '',
  post_content TEXT NOT NULL DEFAULT ''
);
CREATE INDEX wp_posts_title ON wp_posts(post_title);
INSERT INTO wp_posts(ID, post_title) VALUES(1, 'ignored data row');
SQL,
    ],
];

$plan = static fn (array $extra = [], ?array $sourceBatches = null): array => SQLiteSchemaImportSavepointPlan::plan(
    ['wp_commentmeta' => ['type' => 'table', 'sql' => 'CREATE TABLE wp_commentmeta(meta_id INTEGER);']],
    $sourceBatches ?? $batches(),
    array_replace(['schema_version' => 30, 'data_version' => 6, 'next_rootpage' => 12, 'page_size' => 1024], $extra)
);

$valueAt = static function (array $value, string $path): mixed {
    foreach (explode('.', $path) as $part) {
        if ($part === 'count') {
            $value = count($value);
            continue;
        }
        if (ctype_digit($part)) {
            $part = (int) $part;
        }
        $value = $value[$part];
    }

    return $value;
};

$cases = [
    'status is planned' => static fn (): mixed => $plan()['status'],
    'batch count includes rollback batch' => static fn (): mixed => $plan()['batch_count'],
    'applied count excludes rolled back duplicate' => static fn (): mixed => $plan()['applied_count'],
    'skipped count starts zero' => static fn (): mixed => $plan()['skipped_count'],
    'warning count includes data insert warning' => static fn (): mixed => $plan()['warning_count'],
    'released batches preserve only released names' => static fn (): mixed => $plan()['released_batches'],
    'open batches preserves unreleased plugin schema' => static fn (): mixed => $plan()['open_batches'],
    'rolled back batches reports duplicate savepoint' => static fn (): mixed => $plan()['rolled_back_batches'],
    'schema version advances per applied object only' => static fn (): mixed => $plan()['schema_version_after'],
    'data version advances per applying batch' => static fn (): mixed => $plan()['data_version_after'],
    'next rootpage advances across rolled back gapless import' => static fn (): mixed => $plan()['next_rootpage_after'],
    'visible names include existing table' => static fn (): mixed => in_array('wp_commentmeta', $plan()['visible_names'], true),
    'visible names include plugin table kept by open savepoint' => static fn (): mixed => in_array('wp_plugin_settings', $plan()['visible_names'], true),
    'visible names include post schema after rollback' => static fn (): mixed => in_array('wp_posts', $plan()['visible_names'], true),
    'released names include plugin schema after nested child release' => static fn (): mixed => in_array('wp_plugin_settings', $plan()['released_names'], true),
    'released names include post schema' => static fn (): mixed => in_array('wp_posts', $plan()['released_names'], true),
    'dirty pages include sqlite schema root' => static fn (): mixed => in_array(1, $plan()['dirty_pages'], true),
    'dirty pages include first table rootpage' => static fn (): mixed => in_array(12, $plan()['dirty_pages'], true),
    'dirty pages include plugin index rootpage' => static fn (): mixed => in_array(15, $plan()['dirty_pages'], true),
    'dirty pages include post index rootpage' => static fn (): mixed => in_array(17, $plan()['dirty_pages'], true),
    'journal bytes scale by dirty pages and page size' => static fn (): mixed => $plan()['journal_bytes'],
    'first batch released' => static fn (): mixed => $valueAt($plan(), 'batches.0.status'),
    'first batch applies two objects' => static fn (): mixed => $valueAt($plan(), 'batches.0.applied_count'),
    'first batch schema before preserved' => static fn (): mixed => $valueAt($plan(), 'batches.0.schema_version_before'),
    'first batch schema after increments' => static fn (): mixed => $valueAt($plan(), 'batches.0.schema_version_after'),
    'first batch rootpage before preserved' => static fn (): mixed => $valueAt($plan(), 'batches.0.next_rootpage_before'),
    'first batch rootpage after increments' => static fn (): mixed => $valueAt($plan(), 'batches.0.next_rootpage_after'),
    'first batch ordered names tables before indexes' => static fn (): mixed => $valueAt($plan(), 'batches.0.ordered_names'),
    'second batch remains open' => static fn (): mixed => $valueAt($plan(), 'batches.1.status'),
    'second batch applies table index view trigger' => static fn (): mixed => $valueAt($plan(), 'batches.1.applied_count'),
    'second batch warning count zero' => static fn (): mixed => $valueAt($plan(), 'batches.1.warning_count'),
    'second batch ordered view before trigger' => static fn (): mixed => array_slice($valueAt($plan(), 'batches.1.ordered_names'), -2),
    'second batch dirty pages include schema root' => static fn (): mixed => in_array(1, $valueAt($plan(), 'batches.1.dirty_pages'), true),
    'second batch dirty pages include table root' => static fn (): mixed => in_array(14, $valueAt($plan(), 'batches.1.dirty_pages'), true),
    'second batch release flag false' => static fn (): mixed => $valueAt($plan(), 'batches.1.released'),
    'duplicate batch rolls back' => static fn (): mixed => $valueAt($plan(), 'batches.2.status'),
    'duplicate batch reports object exists' => static fn (): mixed => str_contains($valueAt($plan(), 'batches.2.error'), 'Schema object already exists'),
    'duplicate batch applies no objects' => static fn (): mixed => $valueAt($plan(), 'batches.2.applied_count'),
    'duplicate batch preserves schema version' => static fn (): mixed => $valueAt($plan(), 'batches.2.schema_version_after'),
    'duplicate batch preserves data version' => static fn (): mixed => $valueAt($plan(), 'batches.2.data_version_after'),
    'duplicate batch preserves rootpage' => static fn (): mixed => $valueAt($plan(), 'batches.2.next_rootpage_after'),
    'duplicate batch retained depth reports active outer transaction' => static fn (): mixed => $valueAt($plan(), 'batches.2.retained_depth'),
    'duplicate batch rollback pages are empty before writes' => static fn (): mixed => $valueAt($plan(), 'batches.2.rollback_page_numbers'),
    'post batch released after rollback' => static fn (): mixed => $valueAt($plan(), 'batches.3.status'),
    'post batch applies table and index' => static fn (): mixed => $valueAt($plan(), 'batches.3.applied_count'),
    'post batch records unsupported data warning' => static fn (): mixed => $valueAt($plan(), 'batches.3.warning_count'),
    'post batch schema version starts after open plugin batch' => static fn (): mixed => $valueAt($plan(), 'batches.3.schema_version_before'),
    'post batch data version starts after open plugin batch' => static fn (): mixed => $valueAt($plan(), 'batches.3.data_version_before'),
    'savepoint state keeps open plugin savepoint' => static fn (): mixed => count($plan()['savepoint_state']),
    'dependency includes schema import savepoint marker' => static fn (): mixed => in_array('sqlite-application-schema-import-savepoint-current', $plan()['dependencies'], true),
    'dependency includes savepoint rollback marker' => static fn (): mixed => in_array('sqlite-savepoint-current-rollback', $plan()['dependencies'], true),
    'empty batch list rejected' => static function (): mixed {
        try {
            SQLiteSchemaImportSavepointPlan::plan([], []);
        } catch (InvalidArgumentException) {
            return 'rejected';
        }
        return 'missed';
    },
    'bad savepoint name rejected' => static function (): mixed {
        try {
            SQLiteSchemaImportSavepointPlan::plan([], [['name' => 'bad-name', 'dump' => 'CREATE TABLE x(id);']]);
        } catch (InvalidArgumentException) {
            return 'rejected';
        }
        return 'missed';
    },
    'missing SQL dump rejected' => static function (): mixed {
        try {
            SQLiteSchemaImportSavepointPlan::plan([], [['name' => 'missing']]);
        } catch (InvalidArgumentException) {
            return 'rejected';
        }
        return 'missed';
    },
    'bad on error action rejected' => static function (): mixed {
        try {
            SQLiteSchemaImportSavepointPlan::plan([], [['name' => 'b', 'dump' => 'CREATE TABLE x(id);', 'on_error' => 'ignore']]);
        } catch (InvalidArgumentException) {
            return 'rejected';
        }
        return 'missed';
    },
    'bad page size rejected' => static function () use ($plan): mixed {
        try {
            $plan(['page_size' => 513]);
        } catch (InvalidArgumentException) {
            return 'rejected';
        }
        return 'missed';
    },
    'abort on duplicate rethrows' => static function (): mixed {
        try {
            SQLiteSchemaImportSavepointPlan::plan(['wp_options' => []], [[
                'name' => 'abort_duplicate',
                'dump' => 'CREATE TABLE wp_options(id INTEGER);',
                'on_error' => 'abort',
            ]]);
        } catch (InvalidArgumentException) {
            return 'rejected';
        }
        return 'missed';
    },
    'if not exists duplicate skips and releases' => static fn (): mixed => SQLiteSchemaImportSavepointPlan::plan(['wp_options' => []], [[
        'name' => 'skip_existing',
        'dump' => 'CREATE TABLE IF NOT EXISTS wp_options(id INTEGER);',
    ]])['batches'][0]['status'],
    'if not exists duplicate increments no schema version' => static fn (): mixed => SQLiteSchemaImportSavepointPlan::plan(['wp_options' => []], [[
        'name' => 'skip_existing',
        'dump' => 'CREATE TABLE IF NOT EXISTS wp_options(id INTEGER);',
    ]], ['schema_version' => 4])['schema_version_after'],
    'if not exists duplicate creates no dirty pages' => static fn (): mixed => SQLiteSchemaImportSavepointPlan::plan(['wp_options' => []], [[
        'name' => 'skip_existing',
        'dump' => 'CREATE TABLE IF NOT EXISTS wp_options(id INTEGER);',
    ]])['dirty_pages'],
];

$expected = [
    'status is planned' => 'planned',
    'batch count includes rollback batch' => 4,
    'applied count excludes rolled back duplicate' => 8,
    'skipped count starts zero' => 0,
    'warning count includes data insert warning' => 1,
    'released batches preserve only released names' => ['core_schema', 'post_schema'],
    'open batches preserves unreleased plugin schema' => ['plugin_schema'],
    'rolled back batches reports duplicate savepoint' => ['duplicate_plugin'],
    'schema version advances per applied object only' => 38,
    'data version advances per applying batch' => 9,
    'next rootpage advances across rolled back gapless import' => 18,
    'visible names include existing table' => true,
    'visible names include plugin table kept by open savepoint' => true,
    'visible names include post schema after rollback' => true,
    'released names include plugin schema after nested child release' => true,
    'released names include post schema' => true,
    'dirty pages include sqlite schema root' => true,
    'dirty pages include first table rootpage' => true,
    'dirty pages include plugin index rootpage' => true,
    'dirty pages include post index rootpage' => true,
    'journal bytes scale by dirty pages and page size' => 28 + (7 * (1024 + 8)),
    'first batch released' => 'released',
    'first batch applies two objects' => 2,
    'first batch schema before preserved' => 30,
    'first batch schema after increments' => 32,
    'first batch rootpage before preserved' => 12,
    'first batch rootpage after increments' => 14,
    'first batch ordered names tables before indexes' => ['wp_options', 'wp_options_option_name'],
    'second batch remains open' => 'open',
    'second batch applies table index view trigger' => 4,
    'second batch warning count zero' => 0,
    'second batch ordered view before trigger' => ['wp_plugin_autoloaded', 'wp_plugin_settings_ai'],
    'second batch dirty pages include schema root' => true,
    'second batch dirty pages include table root' => true,
    'second batch release flag false' => false,
    'duplicate batch rolls back' => 'rolled_back',
    'duplicate batch reports object exists' => true,
    'duplicate batch applies no objects' => 0,
    'duplicate batch preserves schema version' => 36,
    'duplicate batch preserves data version' => 8,
    'duplicate batch preserves rootpage' => 16,
    'duplicate batch retained depth reports active outer transaction' => 3,
    'duplicate batch rollback pages are empty before writes' => [],
    'post batch released after rollback' => 'released',
    'post batch applies table and index' => 2,
    'post batch records unsupported data warning' => 1,
    'post batch schema version starts after open plugin batch' => 36,
    'post batch data version starts after open plugin batch' => 8,
    'savepoint state keeps open plugin savepoint' => 3,
    'dependency includes schema import savepoint marker' => true,
    'dependency includes savepoint rollback marker' => true,
    'empty batch list rejected' => 'rejected',
    'bad savepoint name rejected' => 'rejected',
    'missing SQL dump rejected' => 'rejected',
    'bad on error action rejected' => 'rejected',
    'bad page size rejected' => 'rejected',
    'abort on duplicate rethrows' => 'rejected',
    'if not exists duplicate skips and releases' => 'released',
    'if not exists duplicate increments no schema version' => 4,
    'if not exists duplicate creates no dirty pages' => [],
];

$tests = [];
foreach ($cases as $name => $callback) {
    $tests['sqlite application schema import savepoint current next40 ' . $name] = static function (TestRunner $t) use ($callback, $expected, $name): void {
        $t->same($expected[$name], $callback());
    };
}

return $tests;
