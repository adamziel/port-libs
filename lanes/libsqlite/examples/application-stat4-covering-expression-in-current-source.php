<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\LibSqlite\SQLitePlannerStat4CoveringExpressionInCurrentSourceNextPlan;

$lower = ['function' => 'lower', 'column' => 'key_name'];
$predicate = [
    'operator' => 'IN',
    'left' => $lower,
    'values' => ['plugin_cache', 'plugin_forms', 'plugin_mail', 'plugin_seo'],
];
$prepared = [
    'name' => 'prepared-app-settings-stat4-covering-expression-in-',
    'schemaCookie' => 1260,
    'stat4Generation' => 42,
    'indexes' => [[
        'name' => 'idx_app_settings_lower_in_covering_stat4_',
        'rootPage' => 12601,
        'estimatedRows' => 480,
        'coveringColumns' => ['key_name', 'load_policy', 'key_value', 'setting_id', 'tenant_id'],
        'coveringExpressions' => [$lower],
        'stat4Samples' => [
            ['neq' => '1 1', 'nlt' => '0 0', 'ndlt' => '0 0', 'sample' => ['plugin_cache', 201]],
            ['neq' => '2 1', 'nlt' => '1 1', 'ndlt' => '1 1', 'sample' => ['plugin_forms', 202]],
            ['neq' => '1 1', 'nlt' => '3 2', 'ndlt' => '2 2', 'sample' => ['plugin_seo', 203]],
        ],
        'sql' => 'CREATE INDEX idx_app_settings_lower_in_covering_stat4_ ON app_settings(lower(key_name), setting_id, key_value, tenant_id, load_policy)',
    ]],
];
$current = $prepared;
$current['name'] = 'current-app-settings-stat4-covering-expression-in-';
$current['schemaCookie'] = 1264;
$current['stat4Generation'] = 45;
$current['indexes'][0]['rootPage'] = 12644;
$current['indexes'][0]['stat4Samples'] = [
    ['neq' => '2 1', 'nlt' => '0 0', 'ndlt' => '0 0', 'sample' => ['plugin_cache', 401]],
    ['neq' => '3 1', 'nlt' => '2 1', 'ndlt' => '1 1', 'sample' => ['plugin_forms', 402]],
    ['neq' => '1 1', 'nlt' => '5 2', 'ndlt' => '2 2', 'sample' => ['plugin_mail', 403]],
    ['neq' => '2 1', 'nlt' => '6 3', 'ndlt' => '3 3', 'sample' => ['plugin_seo', 404]],
];
$rows = [
    ['rowid' => 51, 'key_name' => 'plugin_seo', 'load_policy' => 'yes', 'key_value' => 'seo-enabled', 'setting_id' => 51, 'tenant_id' => 1],
    ['rowid' => 21, 'key_name' => 'plugin_cache', 'load_policy' => 'yes', 'key_value' => 'cache-enabled', 'setting_id' => 21, 'tenant_id' => 1],
    ['rowid' => 41, 'key_name' => 'Plugin_Mail', 'load_policy' => 'yes', 'key_value' => 'mail-enabled', 'setting_id' => 41, 'tenant_id' => 2],
    ['rowid' => 31, 'key_name' => 'Plugin_Forms', 'load_policy' => 'yes', 'key_value' => 'forms-enabled', 'setting_id' => 31, 'tenant_id' => 1],
    ['rowid' => 71, 'key_name' => 'plugin_beta', 'load_policy' => 'yes', 'key_value' => 'beta', 'setting_id' => 71, 'tenant_id' => 1],
];

$plan = SQLitePlannerStat4CoveringExpressionInCurrentSourceNextPlan::materialize(
    $prepared,
    $current,
    $predicate,
    $rows,
    ['key_name', 'load_policy', 'key_value', 'setting_id', 'tenant_id'],
    [$lower],
);

if (($argv[1] ?? '') === '--self-test') {
    assert($plan['status'] === 'stat4-covering-expression-in-current-source-ready');
    assert($plan['selectedSource'] === 'current');
    assert($plan['tableLookupElided'] === true);
    assert($plan['cursorTape']['seekKeys'] === ['plugin_cache', 'plugin_forms', 'plugin_mail', 'plugin_seo']);
    assert($plan['cursorTape']['matchedKeys'] === ['plugin_cache', 'plugin_forms', 'plugin_mail', 'plugin_seo']);
    echo "application-stat4-covering-expression-in-current-source self-test passed\n";
    return;
}

echo json_encode([
    'scenario' => 'application-stat4-covering-expression-in-current-source',
    'status' => $plan['status'],
    'selectedSource' => $plan['selectedSource'],
    'index' => $plan['selectedPlan']['name'] ?? null,
    'seekKeys' => $plan['cursorTape']['seekKeys'],
    'matchedKeys' => $plan['cursorTape']['matchedKeys'],
    'tableLookupElided' => $plan['tableLookupElided'],
    'applicationUse' => 'Preview copied app_settings plugin key-name IN scans after ANALYZE refresh: stale prepared expression-index plans reprepare to current STAT4 samples and read setting payload columns from the covering index cursor.',
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
