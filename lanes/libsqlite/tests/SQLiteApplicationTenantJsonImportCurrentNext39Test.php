<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteBlobValue;
use PortLibs\LibSqlite\SQLiteJsonB;
use PortLibs\LibSqlite\SQLiteJsonImportSavepointPlan;

$currentRows = static fn (): array => [
    [
        'tenant_id' => 1,
        'setting_id' => 101,
        'key_name' => 'plugin_settings',
        'key_value' => '{"enabled":false,"network":false,"version":1,"modules":["core"],"locale":"en_US"}',
        'load_policy' => 'yes',
        'page_number' => 8,
    ],
    [
        'tenant_id' => 2,
        'setting_id' => 201,
        'key_name' => 'plugin_settings',
        'key_value' => '{"enabled":false,"network":true,"version":2,"modules":["core"],"locale":"fr_FR"}',
        'load_policy' => 'yes',
        'page_number' => 12,
    ],
    [
        'tenant_id' => 2,
        'setting_id' => 202,
        'key_name' => 'theme_mods_twenty',
        'key_value' => new SQLiteBlobValue(SQLiteJsonB::encode(['colors' => ['accent' => 'blue'], 'layout' => 'wide'])),
        'load_policy' => 'yes',
        'page_number' => 13,
    ],
    [
        'tenant_id' => 3,
        'setting_id' => 301,
        'key_name' => 'plugin_settings',
        'key_value' => '{"enabled":',
        'load_policy' => 'no',
        'page_number' => 18,
    ],
];

$mutations = static fn (): array => [
    [
        'statement' => 'site1_enable_plugin',
        'tenant_id' => 1,
        'key_name' => 'plugin_settings',
        'function' => 'json_set',
        'path' => '$.enabled',
        'value' => true,
        'wal_frame_index' => 4,
    ],
    [
        'statement' => 'site2_module_import',
        'tenant_id' => 2,
        'key_name' => 'plugin_settings',
        'function' => 'json_insert',
        'path' => '$.modules[#]',
        'value' => 'forms',
        'wal_frame_index' => 5,
    ],
    [
        'statement' => 'site2_theme_accent',
        'tenant_id' => 2,
        'key_name' => 'theme_mods_twenty',
        'function' => 'jsonb_set',
        'path' => '$.colors.accent',
        'value' => 'green',
        'wal_frame_index' => 6,
    ],
    [
        'statement' => 'site3_broken_settings',
        'tenant_id' => 3,
        'key_name' => 'plugin_settings',
        'function' => 'json_set',
        'path' => '$.enabled',
        'value' => true,
        'wal_frame_index' => 7,
    ],
];

$plan = static fn (array $keys = [], ?array $rows = null, ?array $steps = null): array => SQLiteJsonImportSavepointPlan::plan(
    $rows ?? $currentRows(),
    $steps ?? $mutations(),
    array_replace(['page_size' => 512, 'transaction' => 'network_json_import', 'savepoint' => 'current_next39'], $keys)
);

$decode = static function (mixed $value): mixed {
    if ($value instanceof SQLiteBlobValue) {
        return SQLiteJsonB::decode($value->bytes);
    }

    return json_decode((string) $value, true, 512, JSON_THROW_ON_ERROR);
};

$cases = [
    'status reports partial rollback for malformed site row' => static fn (): mixed => $plan()['status'],
    'transaction name is preserved' => static fn (): mixed => $plan()['transaction'],
    'savepoint name is preserved' => static fn (): mixed => $plan()['savepoint'],
    'page size is preserved' => static fn (): mixed => $plan()['page_size'],
    'three multitenant statements are applied' => static fn (): mixed => count($plan()['applied']),
    'one multitenant statement is rolled back' => static fn (): mixed => count($plan()['failed']),
    'first applied keeps tenant id one' => static fn (): mixed => $plan()['applied'][0]['tenant_id'],
    'second applied keeps tenant id two' => static fn (): mixed => $plan()['applied'][1]['tenant_id'],
    'third applied keeps tenant id two' => static fn (): mixed => $plan()['applied'][2]['tenant_id'],
    'first applied composite key key' => static fn (): mixed => $plan()['applied'][0]['setting_key'],
    'second applied composite key key' => static fn (): mixed => $plan()['applied'][1]['setting_key'],
    'third applied composite key key' => static fn (): mixed => $plan()['applied'][2]['setting_key'],
    'first applied page comes from site one row' => static fn (): mixed => $plan()['applied'][0]['page_number'],
    'second applied page comes from site two row' => static fn (): mixed => $plan()['applied'][1]['page_number'],
    'third applied page comes from theme row' => static fn (): mixed => $plan()['applied'][2]['page_number'],
    'explicit wal frame four is retained' => static fn (): mixed => $plan()['applied'][0]['wal_frame_index'],
    'explicit wal frame five is retained' => static fn (): mixed => $plan()['applied'][1]['wal_frame_index'],
    'explicit wal frame six is retained' => static fn (): mixed => $plan()['applied'][2]['wal_frame_index'],
    'site one enabled flag mutates only site one' => static fn (): mixed => $decode($plan()['final_rows'][0]['key_value'])['enabled'],
    'site two enabled flag remains false' => static fn (): mixed => $decode($plan()['final_rows'][1]['key_value'])['enabled'],
    'site one locale remains en us' => static fn (): mixed => $decode($plan()['final_rows'][0]['key_value'])['locale'],
    'site two locale remains fr fr' => static fn (): mixed => $decode($plan()['final_rows'][1]['key_value'])['locale'],
    'site two module append preserves core' => static fn (): mixed => $decode($plan()['final_rows'][1]['key_value'])['modules'][0],
    'site two module append adds forms' => static fn (): mixed => $decode($plan()['final_rows'][1]['key_value'])['modules'][1],
    'jsonb theme mutation remains blob' => static fn (): mixed => $plan()['final_rows'][2]['key_value'] instanceof SQLiteBlobValue,
    'jsonb theme accent mutates' => static fn (): mixed => $decode($plan()['final_rows'][2]['key_value'])['colors']['accent'],
    'jsonb theme layout is preserved' => static fn (): mixed => $decode($plan()['final_rows'][2]['key_value'])['layout'],
    'malformed site row remains original text' => static fn (): mixed => $plan()['final_rows'][3]['key_value'],
    'failed statement name is preserved' => static fn (): mixed => $plan()['failed'][0]['statement'],
    'failed statement tenant id is preserved' => static fn (): mixed => $plan()['failed'][0]['tenant_id'],
    'failed statement key key is preserved' => static fn (): mixed => $plan()['failed'][0]['setting_key'],
    'failed rollback restores site three page' => static fn (): mixed => $plan()['failed'][0]['rollback']['restored_page_numbers'],
    'failed rollback discards site three frame' => static fn (): mixed => array_column($plan()['failed'][0]['rollback']['discarded_wal_frames'], 'frame_index'),
    'failed rollback keeps transaction active' => static fn (): mixed => $plan()['failed'][0]['rollback']['transaction_active_after'],
    'statement plans retain all applied statements' => static fn (): mixed => count($plan()['statement_plans']),
    'first statement plan records composite key' => static fn (): mixed => $plan()['statement_plans'][0]['setting_key'],
    'second statement plan records composite key' => static fn (): mixed => $plan()['statement_plans'][1]['setting_key'],
    'third statement plan records composite key' => static fn (): mixed => $plan()['statement_plans'][2]['setting_key'],
    'statement plan tenant ids are retained' => static fn (): mixed => array_column($plan()['statement_plans'], 'tenant_id'),
    'statement rollback pages are site specific' => static fn (): mixed => array_column($plan()['statement_plans'], 'restored_page_numbers'),
    'savepoint frame tracks applied multitenant pages' => static fn (): mixed => $plan()['savepoint_state'][1]['page_numbers'],
    'rollback to savepoint restores applied pages' => static fn (): mixed => $plan()['rollback_to_savepoint']['restored_page_numbers'],
    'wal rollback to savepoint discards applied frames' => static fn (): mixed => array_column($plan()['wal_rollback_to_savepoint']['discarded_wal_frames'], 'frame_index'),
    'commit plan commits applied pages only' => static fn (): mixed => $plan()['commit']['committed_page_numbers'],
    'final rows retain duplicate key names across tenants' => static fn (): mixed => array_column($plan()['final_rows'], 'key_name'),
    'final rows retain tenant ids' => static fn (): mixed => array_column($plan()['final_rows'], 'tenant_id'),
    'multitenant dependency is advertised' => static fn (): mixed => in_array('sqlite-application-multitenant-json-import-current', $plan()['dependencies'], true),
    'single site dependency is not advertised for multitenant rows' => static fn (): mixed => in_array('sqlite-application-json-import-savepoint-current', $plan()['dependencies'], true),
    'database bytes changed after applied multitenant statements' => static fn (): mixed => $plan()['database_changed'],
    'all valid multitenant mutations are ready' => static fn (): mixed => $plan([], $currentRows(), array_slice($mutations(), 0, 3))['status'],
    'missing tenant id rolls back multitenant mutation' => static fn (): mixed => $plan([], $currentRows(), [['key_name' => 'plugin_settings', 'path' => '$.enabled', 'value' => true]])['status'],
    'missing tenant id records no ambiguous key key' => static fn (): mixed => $plan([], $currentRows(), [['key_name' => 'plugin_settings', 'path' => '$.enabled', 'value' => true]])['failed'][0]['setting_key'],
    'duplicate key name across different tenants is accepted' => static fn (): mixed => count($plan([], array_slice($currentRows(), 0, 2), array_slice($mutations(), 0, 2))['applied']),
    'duplicate key name within same tenant is rejected' => static function () use ($plan, $currentRows, $mutations): mixed {
        $rows = $currentRows();
        $rows[] = ['tenant_id' => 2, 'setting_id' => 250, 'key_name' => 'plugin_settings', 'key_value' => '{}', 'page_number' => 14];
        try {
            $plan([], $rows, $mutations());
        } catch (InvalidArgumentException) {
            return 'rejected';
        }
        return 'missed';
    },
    'string tenant id normalizes in rows and mutations' => static fn (): mixed => $plan([], [
        ['tenant_id' => '4', 'setting_id' => 401, 'key_name' => 'plugin_settings', 'key_value' => '{"enabled":false}', 'page_number' => 20],
    ], [
        ['tenant_id' => '4', 'key_name' => 'plugin_settings', 'path' => '$.enabled', 'value' => true],
    ])['applied'][0]['tenant_id'],
];

$expected = [
    'status reports partial rollback for malformed site row' => 'partial_rollback',
    'transaction name is preserved' => 'network_json_import',
    'savepoint name is preserved' => 'current_next39',
    'page size is preserved' => 512,
    'three multitenant statements are applied' => 3,
    'one multitenant statement is rolled back' => 1,
    'first applied keeps tenant id one' => 1,
    'second applied keeps tenant id two' => 2,
    'third applied keeps tenant id two' => 2,
    'first applied composite key key' => '1:plugin_settings',
    'second applied composite key key' => '2:plugin_settings',
    'third applied composite key key' => '2:theme_mods_twenty',
    'first applied page comes from site one row' => 8,
    'second applied page comes from site two row' => 12,
    'third applied page comes from theme row' => 13,
    'explicit wal frame four is retained' => 4,
    'explicit wal frame five is retained' => 5,
    'explicit wal frame six is retained' => 6,
    'site one enabled flag mutates only site one' => true,
    'site two enabled flag remains false' => false,
    'site one locale remains en us' => 'en_US',
    'site two locale remains fr fr' => 'fr_FR',
    'site two module append preserves core' => 'core',
    'site two module append adds forms' => 'forms',
    'jsonb theme mutation remains blob' => true,
    'jsonb theme accent mutates' => 'green',
    'jsonb theme layout is preserved' => 'wide',
    'malformed site row remains original text' => '{"enabled":',
    'failed statement name is preserved' => 'site3_broken_settings',
    'failed statement tenant id is preserved' => 3,
    'failed statement key key is preserved' => '3:plugin_settings',
    'failed rollback restores site three page' => [18],
    'failed rollback discards site three frame' => [7],
    'failed rollback keeps transaction active' => true,
    'statement plans retain all applied statements' => 3,
    'first statement plan records composite key' => '1:plugin_settings',
    'second statement plan records composite key' => '2:plugin_settings',
    'third statement plan records composite key' => '2:theme_mods_twenty',
    'statement plan tenant ids are retained' => [1, 2, 2],
    'statement rollback pages are site specific' => [[8], [12], [13]],
    'savepoint frame tracks applied multitenant pages' => [8, 12, 13],
    'rollback to savepoint restores applied pages' => [8, 12, 13],
    'wal rollback to savepoint discards applied frames' => [4, 5, 6],
    'commit plan commits applied pages only' => [8, 12, 13],
    'final rows retain duplicate key names across tenants' => ['plugin_settings', 'plugin_settings', 'theme_mods_twenty', 'plugin_settings'],
    'final rows retain tenant ids' => [1, 2, 2, 3],
    'multitenant dependency is advertised' => true,
    'single site dependency is not advertised for multitenant rows' => false,
    'database bytes changed after applied multitenant statements' => true,
    'all valid multitenant mutations are ready' => 'ready',
    'missing tenant id rolls back multitenant mutation' => 'partial_rollback',
    'missing tenant id records no ambiguous key key' => null,
    'duplicate key name across different tenants is accepted' => 2,
    'duplicate key name within same tenant is rejected' => 'rejected',
    'string tenant id normalizes in rows and mutations' => 4,
];

$tests = [];
foreach ($cases as $name => $callback) {
    $tests['sqlite application multitenant json import current next39 ' . $name] = static function (TestRunner $t) use ($callback, $expected, $name): void {
        $t->same($expected[$name], $callback());
    };
}

return $tests;
