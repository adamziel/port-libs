<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\LibSqlite\SQLiteBlobValue;
use PortLibs\LibSqlite\SQLiteJsonB;
use PortLibs\LibSqlite\SQLiteJsonbGeneratedCheckIndexPlan;

$jsonb = static fn (array $value): SQLiteBlobValue => new SQLiteBlobValue(SQLiteJsonB::encode($value));

$schema = <<<'SQL'
CREATE TABLE app_settings(
  setting_id INTEGER PRIMARY KEY,
  key_name TEXT NOT NULL,
  key_value BLOB,
  load_policy TEXT,
  module_slug TEXT GENERATED ALWAYS AS (jsonb_extract(key_value, '$.module.slug')) STORED CHECK(module_slug <> ''),
  module_enabled INTEGER GENERATED ALWAYS AS (jsonb_extract(key_value, '$.module.enabled')) VIRTUAL CHECK(module_enabled >= 0),
  module_rank INTEGER GENERATED ALWAYS AS (jsonb_extract(key_value, '$.module.rank')) VIRTUAL CHECK(module_rank BETWEEN 1 AND 99)
)
SQL;

$rows = [
    ['setting_id' => 101, 'key_name' => 'module_alpha_settings', 'key_value' => $jsonb(['module' => ['slug' => 'alpha', 'enabled' => 1, 'rank' => 10]]), 'load_policy' => 'yes'],
    ['setting_id' => 102, 'key_name' => 'module_beta_settings', 'key_value' => $jsonb(['module' => ['slug' => 'beta', 'enabled' => 0, 'rank' => 20]]), 'load_policy' => 'yes'],
    ['setting_id' => 103, 'key_name' => 'module_gamma_settings', 'key_value' => $jsonb(['module' => ['slug' => 'gamma', 'enabled' => 1, 'rank' => 30]]), 'load_policy' => 'no'],
];

$plan = SQLiteJsonbGeneratedCheckIndexPlan::plan($schema, $rows, [
    ['name' => 'idx_module_slug_checked54', 'rootPage' => 54, 'unique' => true, 'sql' => 'CREATE UNIQUE INDEX idx_module_slug_checked54 ON app_settings(module_slug COLLATE NOCASE) WHERE module_slug IS NOT NULL'],
    ['name' => 'idx_module_enabled_checked54', 'rootPage' => 55, 'sql' => 'CREATE INDEX idx_module_enabled_checked54 ON app_settings(module_enabled) WHERE module_enabled = 1'],
    ['name' => 'idx_module_rank_checked54', 'rootPage' => 56, 'sql' => 'CREATE INDEX idx_module_rank_checked54 ON app_settings(module_rank DESC) WHERE module_rank IS NOT NULL'],
], [
    ['rowid' => 101, 'mutations' => [
        ['function' => 'jsonb_set', 'path' => '$.module.rank', 'value' => 15],
        ['function' => 'jsonb_set', 'path' => '$.module.enabled', 'value' => 0],
    ]],
    ['rowid' => 102, 'mutations' => [
        ['function' => 'jsonb_set', 'path' => '$.module.rank', 'value' => 120],
    ]],
    ['rowid' => 103, 'mutations' => [
        ['function' => 'jsonb_set', 'path' => '$.module.slug', 'value' => 'epsilon'],
    ]],
]);

echo json_encode([
    'scenario' => 'application-jsonb-generated-check-index-current-next54',
    'changes' => $plan['changes'],
    'acceptedRowids' => array_column($plan['accepted_updates'], 'rowid'),
    'rejectedRowids' => array_column($plan['rejected_updates'], 'rowid'),
    'indexActions' => $plan['index_action_count'],
    'finalSlugs' => array_column($plan['after'], 'module_slug'),
    'applicationUse' => 'Preflight application settings JSONB imports so generated-column CHECK constraints reject bad current/next rows before partial generated indexes are rewritten.',
], JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR) . PHP_EOL;
