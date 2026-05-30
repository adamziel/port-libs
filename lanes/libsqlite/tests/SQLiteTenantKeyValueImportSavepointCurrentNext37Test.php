<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteTenantImportSavepointPlan;

$sites = static fn (): array => [
    [
        'tenant_id' => 1,
        'current_rows' => [
            ['setting_id' => 1, 'key_name' => 'siteurl', 'key_value' => 'https://main.old', 'load_policy' => 'yes'],
            ['setting_id' => 2, 'key_name' => 'home', 'key_value' => 'https://main.old', 'load_policy' => 'yes'],
            ['setting_id' => 65, 'key_name' => 'active_plugins', 'key_value' => 'a:0:{}', 'load_policy' => 'no'],
        ],
        'batches' => [
            ['name' => 'urls', 'rows' => [
                ['setting_id' => 1, 'key_name' => 'siteurl', 'key_value' => 'https://main.new', 'load_policy' => 'yes'],
                ['key_name' => 'blogname', 'key_value' => 'Main Import', 'load_policy' => 'yes'],
            ]],
            ['name' => 'plugins', 'rows' => [
                ['setting_id' => 65, 'key_name' => 'active_plugins', 'key_value' => 'a:1:{i:0;s:8:"seo.php";}', 'load_policy' => 'no'],
                ['setting_id' => 130, 'key_name' => 'home', 'key_value' => 'duplicate', 'load_policy' => 'yes'],
            ], 'on_conflict' => 'rollback'],
        ],
    ],
    [
        'tenant_id' => 2,
        'current_rows' => [
            ['setting_id' => 1, 'key_name' => 'siteurl', 'key_value' => 'https://second.old', 'load_policy' => 'yes'],
            ['setting_id' => 2, 'key_name' => 'home', 'key_value' => 'https://second.old', 'load_policy' => 'yes'],
        ],
        'batches' => [
            ['name' => 'urls', 'rows' => [
                ['setting_id' => 1, 'key_name' => 'siteurl', 'key_value' => 'https://second.new', 'load_policy' => 'yes'],
                ['key_name' => 'blogdescription', 'key_value' => 'Imported child site', 'load_policy' => 'no'],
            ]],
        ],
    ],
];

$globalBatches = static fn (): array => [
    ['name' => 'network_meta', 'rows' => [
        ['key_name' => 'site_admins', 'key_value' => 'a:1:{i:0;s:5:"admin";}', 'load_policy' => 'no'],
        ['key_name' => 'registration', 'key_value' => 'none', 'load_policy' => 'no'],
    ]],
];

$plan = static fn (array $siteRows = null, array $options = []): array => SQLiteTenantImportSavepointPlan::plan(
    $siteRows ?? $sites(),
    $options + [
        'database_path' => '/tmp/app-tenant-import.sqlite',
        'page_size' => 1024,
        'global_batches' => $globalBatches(),
    ]
);

$cases = [
    'status is planned' => [static fn (): mixed => $plan()['status'], 'planned'],
    'database path is preserved' => [static fn (): mixed => $plan()['database_path'], '/tmp/app-tenant-import.sqlite'],
    'page size is preserved' => [static fn (): mixed => $plan()['page_size'], 1024],
    'journal mode defaults to delete' => [static fn (): mixed => $plan()['journal_mode'], 'delete'],
    'sync mode defaults to full' => [static fn (): mixed => $plan()['sync_mode'], 'full'],
    'tenant count is two' => [static fn (): mixed => $plan()['tenant_count'], 2],
    'table names include tenant settings' => [static fn (): mixed => $plan()['table_names'][0], 'app_settings'],
    'table names include child tenant settings' => [static fn (): mixed => $plan()['table_names'][1], 'app_tenant_2_settings'],
    'first tenant uses main table' => [static fn (): mixed => $plan()['tenants'][0]['table'], 'app_settings'],
    'second tenant uses numbered table' => [static fn (): mixed => $plan()['tenants'][1]['table'], 'app_tenant_2_settings'],
    'first savepoint prefix includes tenant id' => [static fn (): mixed => $plan()['tenants'][0]['savepoint_prefix'], 'tenant1'],
    'second savepoint prefix includes tenant id' => [static fn (): mixed => $plan()['tenants'][1]['savepoint_prefix'], 'tenant2'],
    'main first batch name is prefixed' => [static fn (): mixed => $plan()['tenants'][0]['plan']['batches'][0]['name'], 'tenant1_urls'],
    'main failed batch name is prefixed' => [static fn (): mixed => $plan()['tenants'][0]['plan']['batches'][1]['name'], 'tenant1_plugins'],
    'child batch name is prefixed' => [static fn (): mixed => $plan()['tenants'][1]['plan']['batches'][0]['name'], 'tenant2_urls'],
    'main tenant is partial after plugin rollback' => [static fn (): mixed => $plan()['tenants'][0]['status'], 'partial'],
    'child tenant is released' => [static fn (): mixed => $plan()['tenants'][1]['status'], 'released'],
    'released tenants list child only' => [static fn (): mixed => $plan()['released_tenants'], [2]],
    'rolled back tenants list main partial tenant' => [static fn (): mixed => $plan()['rolled_back_tenants'], [1]],
    'main released batch is preserved' => [static fn (): mixed => $plan()['tenants'][0]['plan']['released_batches'], ['tenant1_urls']],
    'main rolled back batch is isolated' => [static fn (): mixed => $plan()['tenants'][0]['plan']['rolled_back_batches'], ['tenant1_plugins']],
    'plugin rollback reports conflict' => [static fn (): mixed => str_contains($plan()['tenants'][0]['plan']['batches'][1]['error'], 'unique key_name conflict'), true],
    'main final rows include blogname' => [static fn (): mixed => in_array('blogname', $plan()['tenants'][0]['plan']['final_key_names'], true), true],
    'main final rows omit duplicate home value' => [static fn (): mixed => in_array('duplicate', array_column($plan()['tenants'][0]['plan']['final_rows'], 'key_value'), true), false],
    'main active plugins remains original after rollback' => [static fn (): mixed => $plan()['tenants'][0]['plan']['final_rows'][2]['key_value'], 'a:0:{}'],
    'child final rows include description' => [static fn (): mixed => in_array('blogdescription', $plan()['tenants'][1]['plan']['final_key_names'], true), true],
    'child siteurl is updated' => [static fn (): mixed => $plan()['tenants'][1]['plan']['final_rows'][0]['key_value'], 'https://second.new'],
    'main final rows are keyed under app settings' => [static fn (): mixed => array_column($plan()['final_rows_by_table']['app_settings'], 'key_name'), ['siteurl', 'home', 'active_plugins', 'blogname']],
    'child final rows are keyed under tenant settings' => [static fn (): mixed => array_column($plan()['final_rows_by_table']['app_tenant_2_settings'], 'key_name'), ['siteurl', 'home', 'blogdescription']],
    'released rows preserve only released main changes' => [static fn (): mixed => array_column($plan()['released_rows_by_table']['app_settings'], 'key_name'), ['siteurl', 'home', 'active_plugins', 'blogname']],
    'global plan is present' => [static fn (): mixed => is_array($plan()['global_plan']), true],
    'global savepoint name is prefixed' => [static fn (): mixed => $plan()['global_plan']['batches'][0]['name'], 'global_network_meta'],
    'global plan writes tenant settings table' => [static fn (): mixed => array_column($plan()['final_rows_by_table']['app_tenant_settings'], 'key_name'), ['site_admins', 'registration']],
    'global released rows are stored separately' => [static fn (): mixed => array_column($plan()['released_rows_by_table']['app_tenant_settings'], 'key_name'), ['site_admins', 'registration']],
    'dirty pages are namespaced by tenant id' => [static fn (): mixed => $plan()['dirty_pages'], [1, 2, 100001, 100065, 100066, 200001, 200003]],
    'journal bytes count tenant page namespace' => [static fn (): mixed => $plan()['journal_bytes'], 7252],
    'dependency includes tenant import' => [static fn (): mixed => in_array('sqlite-application-tenant-import-savepoint-current', $plan()['dependencies'], true), true],
    'dependency includes key-value bulk import savepoint' => [static fn (): mixed => in_array('sqlite-application-keyvalue-bulk-import-savepoint-current', $plan()['dependencies'], true), true],
    'dependency includes savepoint rollback' => [static fn (): mixed => in_array('sqlite-savepoint-current-rollback', $plan()['dependencies'], true), true],
    'persist journal option is preserved' => [static fn (): mixed => $plan(null, ['journal_mode' => 'persist'])['journal_mode'], 'persist'],
    'normal sync option is preserved' => [static fn (): mixed => $plan(null, ['sync_mode' => 'normal'])['sync_mode'], 'normal'],
    'replace conflicts releases main plugin batch' => [static fn (): mixed => $plan(null, ['replace_conflicts' => true])['tenants'][0]['plan']['batches'][1]['status'], 'released'],
    'replace conflicts clears rolled back tenants' => [static fn (): mixed => $plan(null, ['replace_conflicts' => true])['rolled_back_tenants'], []],
    'replace conflicts releases both tenants' => [static fn (): mixed => $plan(null, ['replace_conflicts' => true])['released_tenants'], [1, 2]],
    'replace conflicts updates active plugins' => [static fn (): mixed => $plan(null, ['replace_conflicts' => true])['tenants'][0]['plan']['final_rows'][1]['key_value'], 'a:1:{i:0;s:8:"seo.php";}'],
    'continue on site error preserves later sites' => [static fn (): mixed => $plan([
        array_replace($sites()[0], ['batches' => [['name' => 'bad-name', 'rows' => []]]]),
        $sites()[1],
    ])['tenants'][1]['status'], 'released'],
    'site error is reported when continuing' => [static fn (): mixed => str_contains($plan([
        array_replace($sites()[0], ['batches' => [['name' => 'bad-name', 'rows' => []]]]),
        $sites()[1],
    ])['tenants'][0]['plan']['error'], 'savepoint names'), true],
    'continue off rethrows site error' => [static fn (): mixed => $plan([
        array_replace($sites()[0], ['batches' => [['name' => 'bad-name', 'rows' => []]]]),
        $sites()[1],
    ], ['continue_on_site_error' => false]), InvalidArgumentException::class],
    'empty site list rejected' => [static fn (): mixed => SQLiteTenantImportSavepointPlan::plan([]), InvalidArgumentException::class],
    'duplicate tenant id rejected' => [static fn (): mixed => $plan([$sites()[0], $sites()[0]]), InvalidArgumentException::class],
    'zero tenant id rejected' => [static fn (): mixed => $plan([['tenant_id' => 0, 'current_rows' => [], 'batches' => []]]), InvalidArgumentException::class],
    'string tenant id is accepted' => [static fn (): mixed => $plan([['tenant_id' => '3', 'current_rows' => [['setting_id' => 1, 'key_name' => 'siteurl', 'key_value' => 'x', 'load_policy' => 'yes']], 'batches' => [['rows' => [['key_name' => 'blogname', 'key_value' => 'Third', 'load_policy' => 'yes']]]]]], ['global_batches' => []])['tenants'][0]['table'], 'app_tenant_3_settings'],
    'missing current rows rejected' => [static fn (): mixed => $plan([['tenant_id' => 3, 'batches' => []]]), InvalidArgumentException::class],
    'missing batches rejected' => [static fn (): mixed => $plan([['tenant_id' => 3, 'current_rows' => []]]), InvalidArgumentException::class],
    'global batches can be omitted' => [static fn (): mixed => $plan(null, ['global_batches' => []])['global_plan'], null],
    'relative database path rejected' => [static fn (): mixed => $plan(null, ['database_path' => 'app.sqlite']), InvalidArgumentException::class],
    'invalid page size rejected' => [static fn (): mixed => $plan(null, ['page_size' => 1000]), InvalidArgumentException::class],
    'invalid journal mode rejected' => [static fn (): mixed => $plan(null, ['journal_mode' => 'wal']), InvalidArgumentException::class],
    'invalid sync mode rejected' => [static fn (): mixed => $plan(null, ['sync_mode' => 'extra']), InvalidArgumentException::class],
];

$tests = [];
foreach ($cases as $name => [$callback, $expected]) {
    $tests['application tenant import savepoint current next37 ' . $name] = static function (TestRunner $t) use ($callback, $expected): void {
        if (is_string($expected) && is_a($expected, Throwable::class, true)) {
            $t->throws($expected, $callback);
            return;
        }

        $t->same($expected, $callback());
    };
}

return $tests;
