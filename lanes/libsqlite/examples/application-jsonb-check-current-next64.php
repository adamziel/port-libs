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
  CHECK(json_type(key_value, '$.module') = 'object'),
  CHECK(json_extract(key_value, '$.module.slug') <> ''),
  CHECK(json_extract(key_value, '$.module.rank') >= 1 AND json_extract(key_value, '$.module.rank') <= 99),
  CHECK(json_extract(key_value, '$.module.channel') IN ('stable','beta','nightly')),
  CHECK(json_array_length(key_value, '$.module.rules') >= 1),
  CHECK(json_extract(key_value, '$.module.version') IS NOT NULL)
)
SQL;

$plan = SQLiteJsonbCheckCurrentNextPlan::plan($schema, [
    ['setting_id' => 201, 'key_name' => 'module_alpha_settings', 'key_value' => $jsonb(['module' => ['slug' => 'alpha', 'rank' => 10, 'channel' => 'stable', 'rules' => ['cache'], 'enabled' => true, 'version' => '1.0']]), 'load_policy' => 'yes'],
    ['setting_id' => 202, 'key_name' => 'module_beta_settings', 'key_value' => $jsonb(['module' => ['slug' => 'beta', 'rank' => 20, 'channel' => 'beta', 'rules' => ['seo'], 'enabled' => false, 'version' => '2.0']]), 'load_policy' => 'yes'],
], [
    ['op' => 'UPDATE', 'rowid' => 201, 'mutations' => [
        ['function' => 'jsonb_set', 'path' => '$.module.rank', 'value' => 15],
        ['function' => 'jsonb_set', 'path' => '$.module.channel', 'value' => 'beta'],
    ]],
    ['op' => 'UPDATE', 'rowid' => 202, 'mutations' => [
        ['function' => 'jsonb_set', 'path' => '$.module.rank', 'value' => 120],
    ]],
    ['op' => 'INSERT', 'row' => ['setting_id' => 203, 'key_name' => 'module_delta_settings', 'key_value' => $jsonb(['module' => ['slug' => 'delta', 'rank' => 40, 'channel' => 'stable', 'rules' => ['import'], 'enabled' => false, 'version' => '4.0']]), 'load_policy' => 'no']],
]);

echo json_encode([
    'scenario' => 'application-jsonb-check-current-next64',
    'changes' => $plan['changes'],
    'rejectedChanges' => $plan['rejectedChanges'],
    'acceptedRowids' => array_column($plan['accepted'], 'rowid'),
    'rejectedRowids' => array_column($plan['rejected'], 'rowid'),
    'failedCheck' => $plan['rejected'][0]['checks'][3]['sql'],
    'applicationUse' => 'Preflight application settings JSONB module settings against table CHECK constraints before current/next import rows are admitted to storage and index maintenance.',
], JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR) . PHP_EOL;
