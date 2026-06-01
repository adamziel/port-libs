<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\LibSqlite\SQLiteBlobValue;
use PortLibs\LibSqlite\SQLiteJsonB;
use PortLibs\LibSqlite\SQLiteJsonbCheckCurrentNextPlan;

$jsonb = static fn (array $value): SQLiteBlobValue => new SQLiteBlobValue(SQLiteJsonB::encode($value));

$schema = <<<'SQL'
CREATE TABLE app_settings(
  setting_id INTEGER PRIMARY KEY,
  key_name TEXT NOT NULL,
  key_value BLOB,
  load_policy TEXT,
  CHECK(json_valid(key_value, 8)),
  CHECK(json_extract(key_value, '$.module.channel') = 'stable' OR json_extract(key_value, '$.module.channel') = 'beta'),
  CHECK(NOT json_extract(key_value, '$.module.deprecated')),
  CHECK(json_extract(key_value, '$.module.requires') IS NULL OR json_extract(key_value, '$.module.requires') <= 6.7),
  CHECK(NOT (json_extract(key_value, '$.module.channel') = 'beta' AND json_extract(key_value, '$.module.rank') > 50))
)
SQL;

$plan = SQLiteJsonbCheckCurrentNextPlan::plan($schema, [
    ['setting_id' => 301, 'key_name' => 'module_alpha_settings', 'key_value' => $jsonb(['module' => ['channel' => 'stable', 'rank' => 10, 'deprecated' => false, 'requires' => 6.5]]), 'load_policy' => 'yes'],
    ['setting_id' => 302, 'key_name' => 'module_beta_settings', 'key_value' => $jsonb(['module' => ['channel' => 'beta', 'rank' => 40, 'deprecated' => false]]), 'load_policy' => 'yes'],
], [
    ['op' => 'UPDATE', 'rowid' => 301, 'mutations' => [
        ['function' => 'jsonb_set', 'path' => '$.module.channel', 'value' => 'beta'],
        ['function' => 'jsonb_set', 'path' => '$.module.rank', 'value' => 35],
    ]],
    ['op' => 'UPDATE', 'rowid' => 302, 'mutations' => [
        ['function' => 'jsonb_set', 'path' => '$.module.rank', 'value' => 75],
    ]],
    ['op' => 'INSERT', 'row' => ['setting_id' => 303, 'key_name' => 'module_release_settings', 'key_value' => $jsonb(['module' => ['channel' => 'stable', 'rank' => 15, 'deprecated' => false]]), 'load_policy' => 'yes']],
]);

echo json_encode([
    'scenario' => 'application-jsonb-check-current-next67',
    'changes' => $plan['changes'],
    'rejectedChanges' => $plan['rejectedChanges'],
    'acceptedRowids' => array_column($plan['accepted'], 'rowid'),
    'rejectedRowids' => array_column($plan['rejected'], 'rowid'),
    'failedCheck' => $plan['rejected'][0]['checks'][4]['sql'],
    'applicationUse' => 'Preflight application settings JSONB module settings with OR/NOT CHECK guards before next import rows are admitted to storage.',
], JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR) . PHP_EOL;
