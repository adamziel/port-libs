<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteBlobValue;
use PortLibs\LibSqlite\SQLiteJsonB;
use PortLibs\LibSqlite\SQLiteJsonSubtypeValue;
use PortLibs\LibSqlite\SQLiteTenantJsonWalCurrentNextPlan;

$currentRows = static fn (): array => [
    ['scope' => 'tenant', 'tenant_id' => 1, 'key_name' => 'siteurl', 'key_value' => 'https://example.test', 'load_policy' => 'yes'],
    ['scope' => 'tenant', 'tenant_id' => 2, 'key_name' => 'siteurl', 'key_value' => 'https://sub.example.test', 'load_policy' => 'yes'],
    ['scope' => 'global', 'group_id' => 1, 'key_name' => 'site_name', 'key_value' => 'Example Network'],
];

$jsonRows = static fn (array $rows): string => json_encode(['rows' => $rows], JSON_THROW_ON_ERROR);
$plan = static fn (array $imports, array $options = []) => SQLiteTenantJsonWalCurrentNextPlan::plan(
    $currentRows(),
    $imports,
    $options + ['database_path' => '/tmp/app-global-json-current-next.sqlite', 'page_size' => 1024],
);

$tests = [
    'plans tenant JSON import into the main app_settings table' => static function (TestRunner $t) use ($plan, $jsonRows): void {
        $result = $plan([
            ['scope' => 'tenant', 'tenant_id' => 1, 'json' => $jsonRows([
                ['key_name' => 'plugin_settings', 'key_value' => '{"enabled":true}', 'load_policy' => 'yes'],
            ]), 'path' => '$.rows'],
        ]);

        $t->same('planned', $result['status']);
        $t->same(['tenant_1_json'], $result['released_batches']);
        $t->same('app_settings', $result['batches'][0]['table']);
        $t->same(1, $result['wal']['frame_count']);
    },
    'plans subsite JSON import into the tenant settings table' => static function (TestRunner $t) use ($plan, $jsonRows): void {
        $result = $plan([
            ['scope' => 'tenant', 'tenant_id' => 7, 'json' => $jsonRows([
                ['key_name' => 'theme_mods_global_child', 'key_value' => '{"color":"blue"}'],
            ]), 'path' => '$.rows'],
        ]);

        $t->same('app_tenant_7_settings', $result['batches'][0]['table']);
        $t->same(7, $result['batches'][0]['tenant_id']);
        $t->same('app_tenant_7_settings:theme_mods_global_child', $result['batches'][0]['changed_row_keys'][0]);
    },
    'plans global JSON import into app_tenant_settings' => static function (TestRunner $t) use ($plan, $jsonRows): void {
        $result = $plan([
            ['scope' => 'global', 'group_id' => 1, 'json' => $jsonRows([
                ['key_name' => 'registration', 'key_value' => '{"open":false}'],
            ]), 'path' => '$.rows'],
        ]);

        $t->same('app_tenant_settings', $result['batches'][0]['table']);
        $t->same(1, $result['batches'][0]['group_id']);
        $t->same('app_tenant_settings:registration', $result['batches'][0]['changed_row_keys'][0]);
    },
    'keeps current readers pinned before global JSON WAL frames' => static function (TestRunner $t) use ($plan, $jsonRows): void {
        $result = $plan([
            ['scope' => 'global', 'json' => $jsonRows([
                ['key_name' => 'global_plugins', 'key_value' => '["a/a.php"]'],
            ]), 'path' => '$.rows'],
        ], ['first_frame' => 12]);

        $t->same(12, $result['reader_visibility']['current_end_frame']);
        $t->same(13, $result['reader_visibility']['next_end_frame']);
        $t->same(12, $result['wal']['start_frame']);
    },
    'rolls malformed global JSON back to the current WAL frame' => static function (TestRunner $t) use ($plan): void {
        $result = $plan([
            ['scope' => 'global', 'json' => '{"rows":[', 'path' => '$.rows'],
        ], ['first_frame' => 4]);

        $t->same(['global_1_json'], $result['rolled_back_batches']);
        $t->same(4, $result['batches'][0]['wal_rollback_to_frame']);
        $t->same(0, $result['wal']['frame_count']);
    },
    'aborts malformed global JSON when requested' => static function (TestRunner $t) use ($plan): void {
        $t->throws(LogicException::class, static fn () => $plan([
            ['scope' => 'global', 'json' => '{"rows":[', 'path' => '$.rows', 'on_error' => 'abort'],
        ]));
    },
    'keeps open tenant JSON batches visible but unreleased' => static function (TestRunner $t) use ($plan, $jsonRows): void {
        $result = $plan([
            ['scope' => 'tenant', 'tenant_id' => 2, 'json' => $jsonRows([
                ['key_name' => 'open_stage', 'key_value' => '1'],
            ]), 'path' => '$.rows', 'release' => false],
        ]);

        $t->same('open', $result['batches'][0]['status']);
        $t->same(4, $result['reader_visibility']['next_rows_visible']);
        $t->same(3, $result['reader_visibility']['released_rows_visible']);
    },
    'tracks same-statement conflicts against current global rows' => static function (TestRunner $t) use ($plan, $jsonRows): void {
        $result = $plan([
            ['scope' => 'global', 'json' => $jsonRows([
                ['key_name' => 'site_name', 'key_value' => 'Renamed Network'],
            ]), 'path' => '$.rows'],
        ]);

        $t->same(['app_tenant_settings:site_name'], $result['batches'][0]['conflict_row_keys']);
        $t->same('Renamed Network', $result['final_rows'][2]['key_value']);
    },
    'accepts JSON subtype global payloads' => static function (TestRunner $t) use ($currentRows): void {
        $result = SQLiteTenantJsonWalCurrentNextPlan::plan($currentRows(), [
            ['scope' => 'global', 'json' => new SQLiteJsonSubtypeValue('{"rows":[{"key_name":"json_subtype","key_value":"ok"}]}'), 'path' => '$.rows'],
        ], ['database_path' => '/tmp/app-global-json-subtype-current-next.sqlite']);

        $t->same(1, $result['wal']['frame_count']);
        $t->same('app_tenant_settings:json_subtype', $result['batches'][0]['changed_row_keys'][0]);
    },
    'accepts JSONB tenant payloads' => static function (TestRunner $t) use ($currentRows): void {
        $blob = new SQLiteBlobValue(SQLiteJsonB::encode(['rows' => [
            ['key_name' => 'jsonb_tenant_setting', 'key_value' => 'ok'],
        ]]));
        $result = SQLiteTenantJsonWalCurrentNextPlan::plan($currentRows(), [
            ['scope' => 'tenant', 'tenant_id' => 3, 'json' => $blob, 'path' => '$.rows'],
        ], ['database_path' => '/tmp/app-global-jsonb-current-next.sqlite']);

        $t->same('app_tenant_3_settings', $result['batches'][0]['table']);
        $t->same(1, $result['wal']['frame_count']);
    },
];

foreach (range(1, 18) as $tenantId) {
    $tests["maps tenant {$tenantId} JSON setting writes to isolated WAL pages"] = static function (TestRunner $t) use ($currentRows, $jsonRows, $tenantId): void {
        $result = SQLiteTenantJsonWalCurrentNextPlan::plan($currentRows(), [
            ['scope' => 'tenant', 'tenant_id' => $tenantId, 'json' => $jsonRows([
                ['key_name' => 'plugin_' . $tenantId . '_settings', 'key_value' => '{"tenant":' . $tenantId . '}', 'load_policy' => $tenantId % 2 === 0 ? 'yes' : 'no'],
            ]), 'path' => '$.rows'],
        ], ['database_path' => '/tmp/app-global-tenant-' . $tenantId . '-current-next.sqlite']);

        $t->same('tenant_' . $tenantId . '_json', $result['batches'][0]['name']);
        $t->same($tenantId === 1 ? 'app_settings' : 'app_tenant_' . $tenantId . '_settings', $result['batches'][0]['table']);
        $t->same(1, $result['wal']['frame_count']);
    };
}

foreach (range(1, 12) as $siteId) {
    $tests["maps global {$siteId} JSON setting writes to shared settings WAL pages"] = static function (TestRunner $t) use ($currentRows, $jsonRows, $siteId): void {
        $result = SQLiteTenantJsonWalCurrentNextPlan::plan($currentRows(), [
            ['scope' => 'global', 'group_id' => $siteId, 'json' => $jsonRows([
                ['key_name' => 'global_' . $siteId . '_setting', 'key_value' => '{"site":' . $siteId . '}'],
            ]), 'path' => '$.rows'],
        ], ['database_path' => '/tmp/app-global-site-' . $siteId . '-current-next.sqlite']);

        $t->same('global_' . $siteId . '_json', $result['batches'][0]['name']);
        $t->same('app_tenant_settings:global_' . $siteId . '_setting', $result['batches'][0]['changed_row_keys'][0]);
        $t->same(true, $result['wal']['frames'][0]['commit_frame']);
    };
}

foreach ([
    '$' => [['key_name' => 'root_path', 'key_value' => '1']],
    '$.rows' => ['rows' => [['key_name' => 'rows_path', 'key_value' => '2']]],
    '$.payload.rows' => ['payload' => ['rows' => [['key_name' => 'payload_path', 'key_value' => '3']]]],
] as $path => $payload) {
    $tests["extracts tenant JSON rows from path {$path}"] = static function (TestRunner $t) use ($plan, $path, $payload): void {
        $result = $plan([
            ['scope' => 'tenant', 'tenant_id' => 1, 'json' => json_encode($payload, JSON_THROW_ON_ERROR), 'path' => $path],
        ]);

        $t->same(1, $result['batches'][0]['json']['row_count']);
        $t->same(1, $result['wal']['frame_count']);
    };
}

foreach ([
    'missing path' => ['{"payload":[]}', '$.rows'],
    'scalar path' => ['{"rows":3}', '$.rows'],
    'empty key name' => ['{"rows":[{"key_name":""}]}', '$.rows'],
    'missing global key' => ['{"rows":[{"value":"x"}]}', '$.rows'],
    'non json input' => [17, '$.rows'],
] as $label => [$json, $path]) {
    $tests["rolls back {$label} without advancing WAL"] = static function (TestRunner $t) use ($plan, $label, $json, $path): void {
        $scope = $label === 'missing global key' ? 'global' : 'tenant';
        $result = $plan([
            ['scope' => $scope, 'json' => $json, 'path' => $path],
        ]);

        $t->same('rolled_back', $result['batches'][0]['status']);
        $t->same(0, $result['wal']['frame_count']);
    };
}

$tests['combines released tenant and global batches into one current-next WAL stream'] = static function (TestRunner $t) use ($plan, $jsonRows): void {
    $result = $plan([
        ['scope' => 'tenant', 'tenant_id' => 2, 'json' => $jsonRows([
            ['key_name' => 'tenant_two_setting', 'key_value' => 'yes'],
        ]), 'path' => '$.rows'],
        ['scope' => 'global', 'group_id' => 1, 'json' => $jsonRows([
            ['key_name' => 'global_flag', 'key_value' => 'yes'],
        ]), 'path' => '$.rows'],
    ], ['first_frame' => 30]);

    $t->same(['tenant_2_json', 'global_1_json'], $result['released_batches']);
    $t->same(32, $result['wal']['current_frame']);
    $t->same(['tenant', 'global'], array_column($result['wal']['frames'], 'scope'));
};

$tests['rejects empty imports'] = static function (TestRunner $t) use ($currentRows): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteTenantJsonWalCurrentNextPlan::plan($currentRows(), []));
};

$tests['rejects unsafe database paths'] = static function (TestRunner $t) use ($currentRows, $jsonRows): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteTenantJsonWalCurrentNextPlan::plan($currentRows(), [
        ['scope' => 'tenant', 'json' => $jsonRows([])],
    ], ['database_path' => '../app.sqlite']));
};

$tests['rejects invalid page sizes'] = static function (TestRunner $t) use ($currentRows, $jsonRows): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteTenantJsonWalCurrentNextPlan::plan($currentRows(), [
        ['scope' => 'tenant', 'json' => $jsonRows([])],
    ], ['page_size' => 1000]));
};

$tests['rejects invalid scopes'] = static function (TestRunner $t) use ($plan, $jsonRows): void {
    $t->throws(InvalidArgumentException::class, static fn () => $plan([
        ['scope' => 'user', 'json' => $jsonRows([])],
    ]));
};

$tests['rejects invalid global ids'] = static function (TestRunner $t) use ($plan, $jsonRows): void {
    $t->throws(InvalidArgumentException::class, static fn () => $plan([
        ['scope' => 'global', 'group_id' => 0, 'json' => $jsonRows([])],
    ]));
};

$tests['rejects invalid tenant ids'] = static function (TestRunner $t) use ($plan, $jsonRows): void {
    $t->throws(InvalidArgumentException::class, static fn () => $plan([
        ['scope' => 'tenant', 'tenant_id' => 0, 'json' => $jsonRows([])],
    ]));
};

$tests['rejects invalid on-error policies'] = static function (TestRunner $t) use ($plan, $jsonRows): void {
    $t->throws(InvalidArgumentException::class, static fn () => $plan([
        ['scope' => 'tenant', 'json' => $jsonRows([]), 'on_error' => 'ignore'],
    ]));
};

return $tests;
