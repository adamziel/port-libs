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
  CHECK(json_extract(key_value, '$.module.channel') NOT IN ('nightly','dev','blocked')),
  CHECK(json_extract(key_value, '$.module.rank') NOT BETWEEN 51 AND 99),
  CHECK(json_extract(key_value, '$.module.min_app') NOT BETWEEN 6.8 AND 7.9)
)
SQL;

$plan = SQLiteJsonbCheckCurrentNextPlan::plan($schema, [
    ['setting_id' => 401, 'key_name' => 'module_alpha_settings', 'key_value' => $jsonb(['module' => ['channel' => 'stable', 'rank' => 25, 'min_app' => 6.5]]), 'load_policy' => 'yes'],
    ['setting_id' => 402, 'key_name' => 'module_beta_settings', 'key_value' => $jsonb(['module' => ['channel' => 'beta', 'rank' => 50, 'min_app' => 6.7]]), 'load_policy' => 'yes'],
], [
    ['op' => 'UPDATE', 'rowid' => 401, 'mutations' => [
        ['function' => 'jsonb_set', 'path' => '$.module.channel', 'value' => 'beta'],
        ['function' => 'jsonb_set', 'path' => '$.module.rank', 'value' => 45],
    ]],
    ['op' => 'UPDATE', 'rowid' => 402, 'mutations' => [
        ['function' => 'jsonb_set', 'path' => '$.module.channel', 'value' => 'nightly'],
    ]],
    ['op' => 'INSERT', 'row' => ['setting_id' => 403, 'key_name' => 'module_future_settings', 'key_value' => $jsonb(['module' => ['channel' => 'stable', 'rank' => 10, 'min_app' => 7.0]]), 'load_policy' => 'no']],
    ['op' => 'INSERT', 'row' => ['setting_id' => 404, 'key_name' => 'module_safe_settings', 'key_value' => $jsonb(['module' => ['channel' => 'stable', 'rank' => 100, 'min_app' => 8.0]]), 'load_policy' => 'yes']],
]);

echo json_encode([
    'scenario' => 'application-jsonb-check-current-next69',
    'changes' => $plan['changes'],
    'rejectedChanges' => $plan['rejectedChanges'],
    'acceptedRowids' => array_column($plan['accepted'], 'rowid'),
    'rejectedRowids' => array_column($plan['rejected'], 'rowid'),
    'failedChecks' => array_map(static fn (array $row): string => $row['checks'][array_search(false, array_column($row['checks'], 'ok'), true)]['sql'], $plan['rejected']),
    'applicationUse' => 'Preflight application settings JSONB module settings with SQLite NOT IN and NOT BETWEEN CHECK guards before import rows are admitted.',
], JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR) . PHP_EOL;
