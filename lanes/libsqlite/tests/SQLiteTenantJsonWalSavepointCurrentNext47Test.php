<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteBlobValue;
use PortLibs\LibSqlite\SQLiteJsonB;
use PortLibs\LibSqlite\SQLiteJsonSubtypeValue;
use PortLibs\LibSqlite\SQLiteTenantJsonWalSavepointPlan;

$tenantRows = static fn (): array => [
    [
        'tenant_id' => 1,
        'current_rows' => [
            ['setting_id' => 1, 'key_name' => 'base_url', 'key_value' => 'https://main.old', 'load_policy' => 'yes'],
            ['setting_id' => 2, 'key_name' => 'landing_url', 'key_value' => 'https://main.old', 'load_policy' => 'yes'],
            ['setting_id' => 65, 'key_name' => 'active_modules', 'key_value' => 'a:0:{}', 'load_policy' => 'no'],
        ],
        'json_imports' => [
            ['name' => 'settings', 'json' => '{"rows":[{"key_name":"main_module_settings","key_value":"{\"enabled\":true}","load_policy":"yes"}]}', 'path' => '$.rows'],
            ['name' => 'bad_payload', 'json' => '{"rows":[', 'path' => '$.rows'],
        ],
    ],
    [
        'tenant_id' => 2,
        'current_rows' => [
            ['setting_id' => 1, 'key_name' => 'base_url', 'key_value' => 'https://child.old', 'load_policy' => 'yes'],
            ['setting_id' => 2, 'key_name' => 'landing_url', 'key_value' => 'https://child.old', 'load_policy' => 'yes'],
        ],
        'json_imports' => [
            ['name' => 'settings', 'json' => '{"payload":{"rows":[{"key_name":"child_module_settings","key_value":"{\"mode\":\"child\"}","load_policy":"no"}]}}', 'path' => '$.payload.rows'],
        ],
    ],
];

$globalImports = static fn (): array => [
    ['name' => 'global_flags', 'json' => '{"rows":[{"key_name":"tenant_admins","key_value":"a:1:{i:0;s:5:\"admin\";}","load_policy":"no"},{"key_name":"registration","key_value":"none","load_policy":"no"}]}', 'path' => '$.rows'],
];

$plan = static fn (array $tenants = null, array $options = []): array => SQLiteTenantJsonWalSavepointPlan::plan(
    $tenants ?? $tenantRows(),
    $options + [
        'database_path' => '/tmp/sqlite-tenant-json-current.sqlite',
        'page_size' => 1024,
        'global_json_imports' => $globalImports(),
    ],
);

$cases = [
    'status is planned' => [static fn (): mixed => $plan()['status'], 'planned'],
    'database path is preserved' => [static fn (): mixed => $plan()['database_path'], '/tmp/sqlite-tenant-json-current.sqlite'],
    'page size is preserved' => [static fn (): mixed => $plan()['page_size'], 1024],
    'journal mode defaults to WAL' => [static fn (): mixed => $plan()['journal_mode'], 'wal'],
    'sync mode defaults to normal' => [static fn (): mixed => $plan()['sync_mode'], 'normal'],
    'tenant count is two' => [static fn (): mixed => $plan()['tenant_count'], 2],
    'legacy tenant count alias is absent' => [static fn (): mixed => array_key_exists('site' . '_count', $plan()), false],
    'table names are sorted' => [static fn (): mixed => $plan()['table_names'], ['kv_tenant_1', 'kv_tenant_2']],
    'tenants alias exposes first tenant id' => [static fn (): mixed => $plan()['tenants'][0]['tenant_id'], 1],
    'first tenant table is main key value table' => [static fn (): mixed => $plan()['tenants'][0]['table'], 'kv_tenant_1'],
    'second tenant table is numbered key value table' => [static fn (): mixed => $plan()['tenants'][1]['table'], 'kv_tenant_2'],
    'first prefix names tenant' => [static fn (): mixed => $plan()['tenants'][0]['savepoint_prefix'], 'tenant1'],
    'second prefix names tenant' => [static fn (): mixed => $plan()['tenants'][1]['savepoint_prefix'], 'tenant2'],
    'main released JSON import is prefixed' => [static fn (): mixed => $plan()['tenants'][0]['plan']['batches'][0]['name'], 'tenant1_settings'],
    'main malformed JSON import is prefixed' => [static fn (): mixed => $plan()['tenants'][0]['plan']['batches'][1]['name'], 'tenant1_bad_payload'],
    'child JSON import is prefixed' => [static fn (): mixed => $plan()['tenants'][1]['plan']['batches'][0]['name'], 'tenant2_settings'],
    'main tenant is partial after malformed JSON rollback' => [static fn (): mixed => $plan()['tenants'][0]['status'], 'partial'],
    'child tenant is released' => [static fn (): mixed => $plan()['tenants'][1]['status'], 'released'],
    'released tenants contain child only' => [static fn (): mixed => $plan()['released_tenants'], [2]],
    'rolled back tenants contain main partial tenant' => [static fn (): mixed => $plan()['rolled_back_tenants'], [1]],
    'main released batch survives later rollback' => [static fn (): mixed => $plan()['tenants'][0]['plan']['released_batches'], ['tenant1_settings']],
    'main bad batch rolls back only itself' => [static fn (): mixed => $plan()['tenants'][0]['plan']['rolled_back_batches'], ['tenant1_bad_payload']],
    'main bad batch is marked rolled back' => [static fn (): mixed => $plan()['tenants'][0]['plan']['batches'][1]['status'], 'rolled_back'],
    'main bad batch reports malformed JSON' => [static fn (): mixed => str_contains($plan()['tenants'][0]['plan']['batches'][1]['error'], 'malformed'), true],
    'main final rows include imported module settings' => [static fn (): mixed => array_column($plan()['final_rows_by_table']['kv_tenant_1'], 'key_name'), ['base_url', 'landing_url', 'active_modules', 'main_module_settings']],
    'main released rows match final rows after bad rollback' => [static fn (): mixed => array_column($plan()['released_rows_by_table']['kv_tenant_1'], 'key_name'), ['base_url', 'landing_url', 'active_modules', 'main_module_settings']],
    'child final rows include imported module settings' => [static fn (): mixed => array_column($plan()['final_rows_by_table']['kv_tenant_2'], 'key_name'), ['base_url', 'landing_url', 'child_module_settings']],
    'child imported load_policy is preserved' => [static fn (): mixed => $plan()['final_rows_by_table']['kv_tenant_2'][2]['load_policy'], 'no'],
    'global plan is present' => [static fn (): mixed => is_array($plan()['global_plan']), true],
    'global savepoint is global prefixed' => [static fn (): mixed => $plan()['global_plan']['batches'][0]['name'], 'global_global_flags'],
    'global rows map to global key value table' => [static fn (): mixed => array_column($plan()['final_rows_by_table']['kv_global'], 'key_name'), ['tenant_admins', 'registration']],
    'global released rows map to global key value table' => [static fn (): mixed => array_column($plan()['released_rows_by_table']['kv_global'], 'key_name'), ['tenant_admins', 'registration']],
    'main dirty page is global namespaced' => [static fn (): mixed => in_array(100003, $plan()['dirty_pages'], true), true],
    'child dirty page is global namespaced' => [static fn (): mixed => in_array(200002, $plan()['dirty_pages'], true), true],
    'global dirty page remains database namespace' => [static fn (): mixed => in_array(2, $plan()['dirty_pages'], true), true],
    'aggregate WAL path follows database path' => [static fn (): mixed => $plan()['aggregate_wal']['path'], '/tmp/sqlite-tenant-json-current.sqlite-wal'],
    'aggregate WAL has three committed frames' => [static fn (): mixed => $plan()['aggregate_wal']['frame_count'], 3],
    'aggregate WAL reports frames without source-specific marker' => [static fn (): mixed => array_key_exists('current_next47', $plan()['aggregate_wal']), false],
    'aggregate rollback is disabled by default' => [static fn (): mixed => $plan()['aggregate_rollback']['enabled'], false],
    'aggregate rollback is required by partial tenant by default' => [static fn (): mixed => $plan()['aggregate_rollback']['required'], true],
    'aggregate rollback is not applied by default' => [static fn (): mixed => $plan()['aggregate_rollback']['applied'], false],
    'aggregate rollback before frame count records all tenant frames' => [static fn (): mixed => $plan()['aggregate_rollback']['frame_count_before'], 3],
    'aggregate rollback after frame count preserves frames by default' => [static fn (): mixed => $plan()['aggregate_rollback']['frame_count_after'], 3],
    'aggregate rollback before bytes records published WAL bytes' => [static fn (): mixed => $plan()['aggregate_rollback']['wal_bytes_before'], 3240],
    'aggregate rollback after bytes preserves published WAL bytes by default' => [static fn (): mixed => $plan()['aggregate_rollback']['wal_bytes_after'], 3240],
    'aggregate rollback discards no frames by default' => [static fn (): mixed => $plan()['aggregate_rollback']['discarded_frame_count'], 0],
    'first WAL frame belongs to main tenant' => [static fn (): mixed => $plan()['aggregate_wal']['frames'][0]['tenant_id'], 1],
    'first WAL frame names kv_tenant_1' => [static fn (): mixed => $plan()['aggregate_wal']['frames'][0]['table'], 'kv_tenant_1'],
    'first WAL frame has aggregate frame index' => [static fn (): mixed => $plan()['aggregate_wal']['frames'][0]['aggregate_frame_index'], 1],
    'first WAL frame namespaces page number' => [static fn (): mixed => $plan()['aggregate_wal']['frames'][0]['aggregate_page_number'], 100003],
    'second WAL frame belongs to child tenant' => [static fn (): mixed => $plan()['aggregate_wal']['frames'][1]['tenant_id'], 2],
    'second WAL frame names child table' => [static fn (): mixed => $plan()['aggregate_wal']['frames'][1]['table'], 'kv_tenant_2'],
    'second WAL frame has aggregate frame index' => [static fn (): mixed => $plan()['aggregate_wal']['frames'][1]['aggregate_frame_index'], 2],
    'second WAL frame namespaces page number' => [static fn (): mixed => $plan()['aggregate_wal']['frames'][1]['aggregate_page_number'], 200002],
    'global first WAL frame belongs to global namespace' => [static fn (): mixed => $plan()['aggregate_wal']['frames'][2]['tenant_id'], 0],
    'global first WAL frame names global key value table' => [static fn (): mixed => $plan()['aggregate_wal']['frames'][2]['table'], 'kv_global'],
    'global WAL frame receives next aggregate index' => [static fn (): mixed => $plan()['aggregate_wal']['frames'][2]['aggregate_frame_index'], 3],
    'global WAL bytes include per table WAL headers' => [static fn (): mixed => $plan()['aggregate_wal']['bytes'], 3240],
    'dependency names tenant JSON WAL slice' => [static fn (): mixed => in_array('sqlite-tenant-json-wal-savepoint', $plan()['dependencies'], true), true],
    'dependency names JSON WAL import planner' => [static fn (): mixed => in_array('sqlite-json-import-wal-savepoint', $plan()['dependencies'], true), true],
    'dependency names WAL rollback primitive' => [static fn (): mixed => in_array('sqlite-savepoint-wal-rollback', $plan()['dependencies'], true), true],
    'delete journal mode is preserved while WAL model is reported' => [static fn (): mixed => $plan(null, ['journal_mode' => 'delete'])['journal_mode'], 'delete'],
    'full sync mode is preserved' => [static fn (): mixed => $plan(null, ['sync_mode' => 'full'])['sync_mode'], 'full'],
    'omitting global imports removes global table plan' => [static fn (): mixed => $plan(null, ['global_json_imports' => []])['global_plan'], null],
    'custom tenant table name is preserved' => [static fn (): mixed => $plan([array_replace($tenantRows()[0], ['table_name' => 'tenant_settings'])], ['global_json_imports' => []])['tenants'][0]['table'], 'tenant_settings'],
    'custom global table name is preserved' => [static fn (): mixed => array_key_exists('tenant_registry', $plan(null, ['global_table_name' => 'tenant_registry'])['final_rows_by_table']), true],
    'aggregate rollback option is enabled' => [static fn (): mixed => $plan(null, ['rollback_all_on_error' => true])['aggregate_rollback']['enabled'], true],
    'aggregate rollback option applies when a tenant is partial' => [static fn (): mixed => $plan(null, ['rollback_all_on_error' => true])['aggregate_rollback']['applied'], true],
    'aggregate rollback reason names tenant JSON error' => [static fn (): mixed => $plan(null, ['rollback_all_on_error' => true])['aggregate_rollback']['reason'], 'tenant_json_import_error'],
    'aggregate rollback truncates aggregate wal to header' => [static fn (): mixed => $plan(null, ['rollback_all_on_error' => true])['aggregate_wal']['bytes'], 32],
    'aggregate rollback hides aggregate wal frames' => [static fn (): mixed => $plan(null, ['rollback_all_on_error' => true])['aggregate_wal']['frame_count'], 0],
    'aggregate rollback records pre rollback frames' => [static fn (): mixed => $plan(null, ['rollback_all_on_error' => true])['aggregate_rollback']['frame_count_before'], 3],
    'aggregate rollback records post rollback frames' => [static fn (): mixed => $plan(null, ['rollback_all_on_error' => true])['aggregate_rollback']['frame_count_after'], 0],
    'aggregate rollback records discarded frame count' => [static fn (): mixed => $plan(null, ['rollback_all_on_error' => true])['aggregate_rollback']['discarded_frame_count'], 3],
    'aggregate rollback records post rollback bytes' => [static fn (): mixed => $plan(null, ['rollback_all_on_error' => true])['aggregate_rollback']['wal_bytes_after'], 32],
    'aggregate rollback records discarded tenant tables' => [static fn (): mixed => $plan(null, ['rollback_all_on_error' => true])['aggregate_rollback']['discarded_tables'], ['kv_tenant_1', 'kv_tenant_2', 'kv_global']],
    'aggregate rollback keeps tenant final rows for diagnostics' => [static fn (): mixed => array_column($plan(null, ['rollback_all_on_error' => true])['final_rows_by_table']['kv_tenant_1'], 'key_name'), ['base_url', 'landing_url', 'active_modules', 'main_module_settings']],
    'aggregate rollback keeps released rows for diagnostics' => [static fn (): mixed => array_column($plan(null, ['rollback_all_on_error' => true])['released_rows_by_table']['kv_tenant_2'], 'key_name'), ['base_url', 'landing_url', 'child_module_settings']],
    'all released tenants do not require aggregate rollback' => [static fn (): mixed => $plan([
        array_replace($tenantRows()[1], ['tenant_id' => 4, 'json_imports' => [['name' => 'settings', 'json' => '{"rows":[{"key_name":"tenant_module_settings","key_value":"{}"}]}', 'path' => '$.rows']]]),
    ], ['global_json_imports' => [], 'rollback_all_on_error' => true])['aggregate_rollback']['required'], false],
    'all released tenants keep aggregate frames with rollback option' => [static fn (): mixed => $plan([
        array_replace($tenantRows()[1], ['tenant_id' => 4, 'json_imports' => [['name' => 'settings', 'json' => '{"rows":[{"key_name":"tenant_module_settings","key_value":"{}"}]}', 'path' => '$.rows']]]),
    ], ['global_json_imports' => [], 'rollback_all_on_error' => true])['aggregate_wal']['frame_count'], 1],
    'global abort rolls back global plan when continuation is enabled' => [static fn (): mixed => $plan(null, ['global_json_imports' => [['name' => 'bad-global', 'json' => '{"rows":[]}', 'on_conflict' => 'abort']]])['global_plan']['status'], 'rolled_back'],
    'global abort requires aggregate rollback' => [static fn (): mixed => $plan(null, ['global_json_imports' => [['name' => 'bad-global', 'json' => '{"rows":[]}', 'on_conflict' => 'abort']], 'rollback_all_on_error' => true])['aggregate_rollback']['required'], true],
    'global abort aggregate rollback discards tenant frames' => [static fn (): mixed => $plan(null, ['global_json_imports' => [['name' => 'bad-global', 'json' => '{"rows":[]}', 'on_conflict' => 'abort']], 'rollback_all_on_error' => true])['aggregate_rollback']['discarded_frame_count'], 2],
    'global abort aggregate rollback hides frames' => [static fn (): mixed => $plan(null, ['global_json_imports' => [['name' => 'bad-global', 'json' => '{"rows":[]}', 'on_conflict' => 'abort']], 'rollback_all_on_error' => true])['aggregate_wal']['frames'], []],
    'global abort aggregate rollback records tables before truncation' => [static fn (): mixed => $plan(null, ['global_json_imports' => [['name' => 'bad-global', 'json' => '{"rows":[]}', 'on_conflict' => 'abort']], 'rollback_all_on_error' => true])['aggregate_rollback']['discarded_tables'], ['kv_tenant_1', 'kv_tenant_2']],
    'continue off rethrows global error' => [static fn (): mixed => $plan(null, ['global_json_imports' => [['name' => 'bad-global', 'json' => '{"rows":[]}', 'on_conflict' => 'abort']], 'continue_on_global_error' => false]), LogicException::class],
    'string tenant id maps to numbered key value table' => [static fn (): mixed => $plan([['tenant_id' => '3', 'current_rows' => [['setting_id' => 1, 'key_name' => 'base_url', 'key_value' => 'https://third.old', 'load_policy' => 'yes']], 'json_imports' => [['json' => '{"rows":[{"key_name":"third_module_settings","key_value":"{}"}]}', 'path' => '$.rows']]]], ['global_json_imports' => []])['tenants'][0]['table'], 'kv_tenant_3'],
    'JSON subtype payload imports into tenant table' => [static fn (): mixed => array_column($plan([array_replace($tenantRows()[0], ['json_imports' => [['name' => 'subtype', 'json' => new SQLiteJsonSubtypeValue('{"rows":[{"key_name":"subtype_global_settings","key_value":"{}"}]}'), 'path' => '$.rows']]])], ['global_json_imports' => []])['final_rows_by_table']['kv_tenant_1'], 'key_name'), ['base_url', 'landing_url', 'active_modules', 'subtype_global_settings']],
    'JSONB payload imports into tenant table' => [static fn (): mixed => array_column($plan([array_replace($tenantRows()[1], ['json_imports' => [['name' => 'jsonb', 'json' => new SQLiteBlobValue(SQLiteJsonB::encode(['rows' => [['key_name' => 'jsonb_global_settings', 'key_value' => '{}']]])), 'path' => '$.rows']]])], ['global_json_imports' => []])['final_rows_by_table']['kv_tenant_2'], 'key_name'), ['base_url', 'landing_url', 'jsonb_global_settings']],
    'tenant error continuing preserves later tenant' => [static fn (): mixed => $plan([
        array_replace($tenantRows()[0], ['json_imports' => [['name' => 'bad-name', 'json' => '{"rows":[]}']]]),
        $tenantRows()[1],
    ])['tenants'][1]['status'], 'released'],
    'tenant error continuing reports rolled back tenant' => [static fn (): mixed => $plan([
        array_replace($tenantRows()[0], ['json_imports' => [['name' => 'bad-name', 'json' => '{"rows":[]}']]]),
        $tenantRows()[1],
    ])['tenants'][0]['status'], 'rolled_back'],
    'tenant error continuing reports savepoint error' => [static fn (): mixed => str_contains($plan([
        array_replace($tenantRows()[0], ['json_imports' => [['name' => 'bad-name', 'json' => '{"rows":[]}']]]),
        $tenantRows()[1],
    ])['tenants'][0]['plan']['error'], 'savepoint'), true],
    'tenant error continuing records no WAL frames for failed tenant' => [static fn (): mixed => $plan([
        array_replace($tenantRows()[0], ['json_imports' => [['name' => 'bad-name', 'json' => '{"rows":[]}']]]),
        $tenantRows()[1],
    ])['tenants'][0]['plan']['wal']['frame_count'], 0],
    'continue off rethrows tenant error' => [static fn (): mixed => $plan([
        array_replace($tenantRows()[0], ['json_imports' => [['name' => 'bad-name', 'json' => '{"rows":[]}']]]),
        $tenantRows()[1],
    ], ['continue_on_tenant_error' => false]), InvalidArgumentException::class],
    'empty tenant list rejected' => [static fn (): mixed => SQLiteTenantJsonWalSavepointPlan::plan([]), InvalidArgumentException::class],
    'duplicate tenant id rejected' => [static fn (): mixed => $plan([$tenantRows()[0], $tenantRows()[0]]), InvalidArgumentException::class],
    'zero tenant id rejected' => [static fn (): mixed => $plan([['tenant_id' => 0, 'current_rows' => [], 'json_imports' => []]]), InvalidArgumentException::class],
    'missing current rows rejected' => [static fn (): mixed => $plan([['tenant_id' => 3, 'json_imports' => []]]), InvalidArgumentException::class],
    'missing imports rejected' => [static fn (): mixed => $plan([['tenant_id' => 3, 'current_rows' => []]]), InvalidArgumentException::class],
    'relative database path rejected by nested JSON WAL planner' => [static fn (): mixed => $plan(null, ['database_path' => 'tenant.sqlite']), InvalidArgumentException::class],
    'invalid page size rejected by nested JSON WAL planner' => [static fn (): mixed => $plan(null, ['page_size' => 1000]), InvalidArgumentException::class],
    'invalid global imports rejected' => [static fn (): mixed => $plan(null, ['global_json_imports' => 'bad']), InvalidArgumentException::class],
];

$tests = [];
foreach ($cases as $name => [$callback, $expected]) {
    $tests['tenant json wal savepoint source neutral ' . $name] = static function (TestRunner $t) use ($callback, $expected): void {
        if (is_string($expected) && is_a($expected, Throwable::class, true)) {
            $t->throws($expected, $callback);
            return;
        }

        $t->same($expected, $callback());
    };
}

return $tests;
