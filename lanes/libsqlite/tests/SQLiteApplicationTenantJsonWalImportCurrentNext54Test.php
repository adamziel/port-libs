<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteBlobValue;
use PortLibs\LibSqlite\SQLiteJsonB;
use PortLibs\LibSqlite\SQLiteJsonSubtypeValue;
use PortLibs\LibSqlite\SQLiteTenantJsonWalImportPlan;

$currentRows = static fn (): array => [
    ['group_id' => 1, 'tenant_id' => 1, 'setting_id' => 1, 'key_name' => 'base_url', 'key_value' => 'https://example.test', 'load_policy' => 'yes'],
    ['group_id' => 1, 'tenant_id' => 1, 'setting_id' => 2, 'key_name' => 'module_visual_profile', 'key_value' => '{"accent":"blue"}', 'load_policy' => 'yes'],
    ['group_id' => 1, 'tenant_id' => 2, 'setting_id' => 1, 'key_name' => 'base_url', 'key_value' => 'https://site2.example.test', 'load_policy' => 'yes'],
    ['group_id' => 1, 'tenant_id' => 2, 'setting_id' => 2, 'key_name' => 'module_settings', 'key_value' => '{"enabled":false}', 'load_policy' => 'no'],
    ['scope' => 'global', 'group_id' => 1, 'setting_id' => 1, 'key_name' => 'app_name', 'key_value' => 'Global'],
];

$jsonRows = static fn (array $rows): string => json_encode(['rows' => $rows], JSON_THROW_ON_ERROR);
$plan = static fn (array $imports, array $options = []): array => SQLiteTenantJsonWalImportPlan::plan(
    $currentRows(),
    $imports,
    ['database_path' => '/tmp/app-tenant-json-wal-current-next54.sqlite'] + $options
);

$rowByKey = static function (array $result, string $key): array {
    foreach ($result['final_rows'] as $row) {
        $rowKey = (($row['scope'] ?? 'tenant') === 'global')
            ? 'global:' . $row['group_id'] . ':' . $row['key_name']
            : 'tenant:' . $row['group_id'] . ':' . $row['tenant_id'] . ':' . $row['key_name'];
        if ($rowKey === $key) {
            return $row;
        }
    }

    throw new RuntimeException("Missing row {$key}");
};

$cases = [
    'released tenant import is visible in final keys' => static fn (): mixed => $plan([
        ['name' => 'tenant_one', 'tenant_id' => 1, 'json' => $jsonRows([
            ['key_name' => 'module_settings', 'key_value' => '{"enabled":true}', 'load_policy' => 'yes'],
        ])],
    ])['final_keys'],
    'released tenant import records one WAL frame' => static fn (): mixed => $plan([
        ['name' => 'tenant_one', 'tenant_id' => 1, 'json' => $jsonRows([
            ['key_name' => 'module_settings', 'key_value' => '{"enabled":true}', 'load_policy' => 'yes'],
        ])],
    ])['wal']['frame_count'],
    'tenant two writes use the tenant two settings table' => static fn (): mixed => $plan([
        ['name' => 'tenant_two', 'tenant_id' => 2, 'json' => $jsonRows([
            ['key_name' => 'component_recent', 'key_value' => '{"count":3}', 'load_policy' => 'no'],
        ])],
    ])['batches'][0]['writes'][0]['table'],
    'global import targets global settings table' => static fn (): mixed => $plan([
        ['name' => 'global_meta', 'scope' => 'global', 'json' => $jsonRows([
            ['key_name' => 'site_settings', 'key_value' => '{"lang":"en"}'],
        ])],
    ])['batches'][0]['writes'][0]['table'],
    'global import key is isolated from tenant key' => static fn (): mixed => $plan([
        ['name' => 'global_settings', 'scope' => 'global', 'json' => $jsonRows([
            ['key_name' => 'module_settings', 'key_value' => '{"global":true}'],
        ])],
    ])['final_keys'],
    'current batch release list retains savepoint name' => static fn (): mixed => $plan([
        ['name' => 'current_site', 'tenant_id' => 1, 'json' => $jsonRows([
            ['key_name' => 'current_settings', 'key_value' => '{"ok":true}'],
        ])],
    ])['released_batches'],
    'open next batch is final-visible but unreleased' => static fn (): mixed => [
        $plan([
            ['name' => 'next_site', 'tenant_id' => 2, 'release' => false, 'json' => $jsonRows([
                ['key_name' => 'next_settings', 'key_value' => '{"ok":true}'],
            ])],
        ])['final_keys'],
        $plan([
            ['name' => 'next_site', 'tenant_id' => 2, 'release' => false, 'json' => $jsonRows([
                ['key_name' => 'next_settings', 'key_value' => '{"ok":true}'],
            ])],
        ])['released_keys'],
    ],
    'malformed next batch rolls back without removing released current batch' => static fn (): mixed => [
        $plan([
            ['name' => 'current_ok', 'tenant_id' => 1, 'json' => $jsonRows([
                ['key_name' => 'current_json', 'key_value' => '{"ok":true}'],
            ])],
            ['name' => 'next_bad', 'tenant_id' => 2, 'json' => '{"rows":['],
        ])['released_batches'],
        $plan([
            ['name' => 'current_ok', 'tenant_id' => 1, 'json' => $jsonRows([
                ['key_name' => 'current_json', 'key_value' => '{"ok":true}'],
            ])],
            ['name' => 'next_bad', 'tenant_id' => 2, 'json' => '{"rows":['],
        ])['rolled_back_batches'],
    ],
    'malformed settings JSON rolls back only its batch' => static fn (): mixed => $plan([
        ['name' => 'bad_value', 'tenant_id' => 1, 'json' => $jsonRows([
            ['key_name' => 'module_bad', 'key_value' => '{bad}', 'load_policy' => 'yes'],
        ])],
    ])['batches'][0]['status'],
    'JSONB source rows are accepted' => static function () use ($currentRows): mixed {
        $result = SQLiteTenantJsonWalImportPlan::plan($currentRows(), [
            ['name' => 'jsonb_tenant', 'tenant_id' => 2, 'json' => new SQLiteBlobValue(SQLiteJsonB::encode(['rows' => [
                ['key_name' => 'jsonb_settings', 'key_value' => '{"ok":true}', 'load_policy' => 'no'],
            ]]))],
        ]);

        return $result['batches'][0]['json']['key_names'];
    },
    'JSON subtype source rows are accepted' => static function () use ($currentRows): mixed {
        $result = SQLiteTenantJsonWalImportPlan::plan($currentRows(), [
            ['name' => 'subtype_tenant', 'tenant_id' => 1, 'json' => new SQLiteJsonSubtypeValue('{"rows":[{"key_name":"subtype_settings","key_value":"{\"ok\":true}","load_policy":"no"}]}')],
        ]);

        return $result['batches'][0]['json']['key_names'];
    },
    'path extraction can target nested rows' => static fn (): mixed => $plan([
        ['name' => 'nested_path', 'tenant_id' => 1, 'path' => '$.payload.rows', 'json' => '{"payload":{"rows":[{"key_name":"nested_settings","key_value":"{\"ok\":true}"}]}}'],
    ])['batches'][0]['json']['key_names'],
    'missing path rolls batch back' => static fn (): mixed => $plan([
        ['name' => 'missing_path', 'tenant_id' => 1, 'path' => '$.missing.rows', 'json' => '{"payload":{"rows":[]}}'],
    ])['rolled_back_batches'],
    'abort conflict rolls back duplicate settings batch' => static fn (): mixed => $plan([
        ['name' => 'abort_conflict', 'tenant_id' => 2, 'on_conflict' => 'abort', 'json' => $jsonRows([
            ['key_name' => 'module_settings', 'key_value' => '{"enabled":true}'],
        ])],
    ])['batches'][0]['status'],
    'replace conflict records conflict key' => static fn (): mixed => $plan([
        ['name' => 'replace_conflict', 'tenant_id' => 2, 'json' => $jsonRows([
            ['key_name' => 'module_settings', 'key_value' => '{"enabled":true}'],
        ])],
    ])['batches'][0]['conflicts'],
    'replacement row updates tenant two value' => static function () use ($plan, $jsonRows, $rowByKey): mixed {
        $result = $plan([
            ['name' => 'replace_conflict', 'tenant_id' => 2, 'json' => $jsonRows([
                ['key_name' => 'module_settings', 'key_value' => '{"enabled":true}', 'load_policy' => 'yes'],
            ])],
        ]);

        return $rowByKey($result, 'tenant:1:2:module_settings')['load_policy'];
    },
    'WAL frames preserve savepoint names in order' => static fn (): mixed => array_column($plan([
        ['name' => 'first_tenant', 'tenant_id' => 1, 'json' => $jsonRows([
            ['key_name' => 'first_settings', 'key_value' => '{"ok":1}'],
        ])],
        ['name' => 'second_tenant', 'tenant_id' => 2, 'json' => $jsonRows([
            ['key_name' => 'second_settings', 'key_value' => '{"ok":2}'],
        ])],
    ])['wal']['frames'], 'savepoint'),
    'page numbers isolate tenant tables' => static fn (): mixed => array_column($plan([
        ['name' => 'first_tenant', 'tenant_id' => 1, 'json' => $jsonRows([
            ['setting_id' => 70, 'key_name' => 'first_settings', 'key_value' => '{"ok":1}'],
        ])],
        ['name' => 'second_tenant', 'tenant_id' => 2, 'json' => $jsonRows([
            ['setting_id' => 70, 'key_name' => 'second_settings', 'key_value' => '{"ok":2}'],
        ])],
    ])['wal']['frames'], 'page_number'),
    'global page numbers use global range' => static fn (): mixed => $plan([
        ['name' => 'global_page', 'scope' => 'global', 'json' => $jsonRows([
            ['setting_id' => 70, 'key_name' => 'global_settings', 'key_value' => '{"ok":true}'],
        ])],
    ])['wal']['frames'][0]['page_number'],
    'dependency marker names tenant JSON WAL import' => static fn (): mixed => in_array('sqlite-application-tenant-json-wal-import', $plan([
        ['name' => 'deps', 'tenant_id' => 1, 'json' => $jsonRows([
            ['key_name' => 'deps_settings', 'key_value' => '{"ok":true}'],
        ])],
    ])['dependencies'], true),
    'bad source type rolls back batch as JSON admission failure' => static fn (): mixed => $plan([
        ['name' => 'bad_source_type', 'tenant_id' => 1, 'json' => ['rows' => []]],
    ])['batches'][0]['status'],
    'bad tenant id rolls back row normalization failure' => static fn (): mixed => $plan([
        ['name' => 'bad_tenant_id', 'tenant_id' => 0, 'json' => '{"rows":[{"key_name":"bad_tenant_settings","key_value":"{\"ok\":true}"}]}'],
    ])['batches'][0]['status'],
    'later global release does not release open next tenant preview' => static fn (): mixed => $plan([
        ['name' => 'next_preview', 'tenant_id' => 2, 'release' => false, 'json' => $jsonRows([
            ['key_name' => 'preview_settings', 'key_value' => '{"ok":true}'],
        ])],
        ['name' => 'global_release', 'scope' => 'global', 'json' => $jsonRows([
            ['key_name' => 'global_config', 'key_value' => '{"ok":true}'],
        ])],
    ])['released_keys'],
];

$expected = [
    'released tenant import is visible in final keys' => [
        'global:1:app_name',
        'tenant:1:1:base_url',
        'tenant:1:1:module_settings',
        'tenant:1:1:module_visual_profile',
        'tenant:1:2:base_url',
        'tenant:1:2:module_settings',
    ],
    'released tenant import records one WAL frame' => 1,
    'tenant two writes use the tenant two settings table' => 'app_tenant_2_settings',
    'global import targets global settings table' => 'app_tenant_settings',
    'global import key is isolated from tenant key' => [
        'global:1:app_name',
        'global:1:module_settings',
        'tenant:1:1:base_url',
        'tenant:1:1:module_visual_profile',
        'tenant:1:2:base_url',
        'tenant:1:2:module_settings',
    ],
    'current batch release list retains savepoint name' => ['current_site'],
    'open next batch is final-visible but unreleased' => [
        [
            'global:1:app_name',
            'tenant:1:1:base_url',
            'tenant:1:1:module_visual_profile',
            'tenant:1:2:base_url',
            'tenant:1:2:module_settings',
            'tenant:1:2:next_settings',
        ],
        [
            'global:1:app_name',
            'tenant:1:1:base_url',
            'tenant:1:1:module_visual_profile',
            'tenant:1:2:base_url',
            'tenant:1:2:module_settings',
        ],
    ],
    'malformed next batch rolls back without removing released current batch' => [['current_ok'], ['next_bad']],
    'malformed settings JSON rolls back only its batch' => 'rolled_back',
    'JSONB source rows are accepted' => ['jsonb_settings'],
    'JSON subtype source rows are accepted' => ['subtype_settings'],
    'path extraction can target nested rows' => ['nested_settings'],
    'missing path rolls batch back' => ['missing_path'],
    'abort conflict rolls back duplicate settings batch' => 'rolled_back',
    'replace conflict records conflict key' => ['tenant:1:2:module_settings'],
    'replacement row updates tenant two value' => 'yes',
    'WAL frames preserve savepoint names in order' => ['first_tenant', 'second_tenant'],
    'page numbers isolate tenant tables' => [3, 19],
    'global page numbers use global range' => 41,
    'dependency marker names tenant JSON WAL import' => true,
    'bad source type rolls back batch as JSON admission failure' => 'rolled_back',
    'bad tenant id rolls back row normalization failure' => 'rolled_back',
    'later global release does not release open next tenant preview' => [
        'global:1:app_name',
        'global:1:global_config',
        'tenant:1:1:base_url',
        'tenant:1:1:module_visual_profile',
        'tenant:1:2:base_url',
        'tenant:1:2:module_settings',
    ],
];

$tests = [];
foreach ($cases as $name => $callback) {
    $tests['sqlite application tenant json wal import current next54 ' . $name] = static function (TestRunner $t) use ($callback, $expected, $name): void {
        $t->same($expected[$name], $callback());
    };
}

foreach (range(1, 30) as $tenantId) {
    $tests["sqlite application tenant json wal import current next54 generated tenant {$tenantId} maps settings page"] = static function (TestRunner $t) use ($currentRows, $jsonRows, $tenantId): void {
        $result = SQLiteTenantJsonWalImportPlan::plan($currentRows(), [
            ['name' => 'tenant_' . $tenantId, 'tenant_id' => $tenantId, 'json' => $jsonRows([
                ['setting_id' => 65, 'key_name' => 'tenant_' . $tenantId . '_settings', 'key_value' => '{"tenant":' . $tenantId . '}'],
            ])],
        ]);

        $t->same('tenant:' . 1 . ':' . $tenantId . ':tenant_' . $tenantId . '_settings', $result['batches'][0]['writes'][0]['key']);
        $t->same(3 + (($tenantId - 1) * 16), $result['wal']['frames'][0]['page_number']);
    };
}

foreach ([
    'bad scope' => ['scope' => 'user', 'json' => '{"rows":[]}'],
    'bad savepoint' => ['name' => 'bad-name', 'json' => '{"rows":[]}'],
] as $label => $import) {
    $tests["sqlite application tenant json wal import current next54 rejects {$label}"] = static function (TestRunner $t) use ($currentRows, $import): void {
        $t->throws(InvalidArgumentException::class, static fn () => SQLiteTenantJsonWalImportPlan::plan($currentRows(), [$import]));
    };
}

return $tests;
