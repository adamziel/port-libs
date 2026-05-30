<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteBlobValue;
use PortLibs\LibSqlite\SQLiteJsonB;
use PortLibs\LibSqlite\SQLiteJsonSubtypeValue;
use PortLibs\LibSqlite\SQLiteTenantJsonWalImportPlan;

$currentRows = static fn (): array => [
    ['site_id' => 1, 'blog_id' => 1, 'option_id' => 1, 'option_name' => 'siteurl', 'option_value' => 'https://example.test', 'autoload' => 'yes'],
    ['site_id' => 1, 'blog_id' => 1, 'option_id' => 2, 'option_name' => 'theme_mods_twentyfive', 'option_value' => '{"accent":"blue"}', 'autoload' => 'yes'],
    ['site_id' => 1, 'blog_id' => 2, 'option_id' => 1, 'option_name' => 'siteurl', 'option_value' => 'https://site2.example.test', 'autoload' => 'yes'],
    ['site_id' => 1, 'blog_id' => 2, 'option_id' => 2, 'option_name' => 'plugin_settings', 'option_value' => '{"enabled":false}', 'autoload' => 'no'],
    ['scope' => 'network', 'site_id' => 1, 'meta_id' => 1, 'meta_key' => 'site_name', 'meta_value' => 'Network'],
];

$jsonRows = static fn (array $rows): string => json_encode(['rows' => $rows], JSON_THROW_ON_ERROR);
$plan = static fn (array $imports, array $options = []): array => SQLiteTenantJsonWalImportPlan::plan(
    $currentRows(),
    $imports,
    ['database_path' => '/tmp/wp-multisite-json-wal-current-next54.sqlite'] + $options
);

$rowByKey = static function (array $result, string $key): array {
    foreach ($result['final_rows'] as $row) {
        $rowKey = (($row['scope'] ?? 'blog') === 'network')
            ? 'network:' . $row['site_id'] . ':' . $row['option_name']
            : 'blog:' . $row['site_id'] . ':' . $row['blog_id'] . ':' . $row['option_name'];
        if ($rowKey === $key) {
            return $row;
        }
    }

    throw new RuntimeException("Missing row {$key}");
};

$cases = [
    'released blog import is visible in final keys' => static fn (): mixed => $plan([
        ['name' => 'blog_one', 'blog_id' => 1, 'json' => $jsonRows([
            ['option_name' => 'plugin_settings', 'option_value' => '{"enabled":true}', 'autoload' => 'yes'],
        ])],
    ])['final_keys'],
    'released blog import records one WAL frame' => static fn (): mixed => $plan([
        ['name' => 'blog_one', 'blog_id' => 1, 'json' => $jsonRows([
            ['option_name' => 'plugin_settings', 'option_value' => '{"enabled":true}', 'autoload' => 'yes'],
        ])],
    ])['wal']['frame_count'],
    'blog two writes use the blog two option table' => static fn (): mixed => $plan([
        ['name' => 'blog_two', 'blog_id' => 2, 'json' => $jsonRows([
            ['option_name' => 'widget_recent', 'option_value' => '{"count":3}', 'autoload' => 'no'],
        ])],
    ])['batches'][0]['writes'][0]['table'],
    'network import targets sitemeta table' => static fn (): mixed => $plan([
        ['name' => 'network_meta', 'scope' => 'network', 'json' => $jsonRows([
            ['meta_key' => 'site_settings', 'meta_value' => '{"lang":"en"}'],
        ])],
    ])['batches'][0]['writes'][0]['table'],
    'network import key is isolated from blog key' => static fn (): mixed => $plan([
        ['name' => 'network_settings', 'scope' => 'network', 'json' => $jsonRows([
            ['meta_key' => 'plugin_settings', 'meta_value' => '{"network":true}'],
        ])],
    ])['final_keys'],
    'current batch release list retains savepoint name' => static fn (): mixed => $plan([
        ['name' => 'current_site', 'blog_id' => 1, 'json' => $jsonRows([
            ['option_name' => 'current_settings', 'option_value' => '{"ok":true}'],
        ])],
    ])['released_batches'],
    'open next batch is final-visible but unreleased' => static fn (): mixed => [
        $plan([
            ['name' => 'next_site', 'blog_id' => 2, 'release' => false, 'json' => $jsonRows([
                ['option_name' => 'next_settings', 'option_value' => '{"ok":true}'],
            ])],
        ])['final_keys'],
        $plan([
            ['name' => 'next_site', 'blog_id' => 2, 'release' => false, 'json' => $jsonRows([
                ['option_name' => 'next_settings', 'option_value' => '{"ok":true}'],
            ])],
        ])['released_keys'],
    ],
    'malformed next batch rolls back without removing released current batch' => static fn (): mixed => [
        $plan([
            ['name' => 'current_ok', 'blog_id' => 1, 'json' => $jsonRows([
                ['option_name' => 'current_json', 'option_value' => '{"ok":true}'],
            ])],
            ['name' => 'next_bad', 'blog_id' => 2, 'json' => '{"rows":['],
        ])['released_batches'],
        $plan([
            ['name' => 'current_ok', 'blog_id' => 1, 'json' => $jsonRows([
                ['option_name' => 'current_json', 'option_value' => '{"ok":true}'],
            ])],
            ['name' => 'next_bad', 'blog_id' => 2, 'json' => '{"rows":['],
        ])['rolled_back_batches'],
    ],
    'malformed option JSON rolls back only its batch' => static fn (): mixed => $plan([
        ['name' => 'bad_value', 'blog_id' => 1, 'json' => $jsonRows([
            ['option_name' => 'theme_mods_bad', 'option_value' => '{bad}', 'autoload' => 'yes'],
        ])],
    ])['batches'][0]['status'],
    'JSONB source rows are accepted' => static function () use ($currentRows): mixed {
        $result = SQLiteTenantJsonWalImportPlan::plan($currentRows(), [
            ['name' => 'jsonb_blog', 'blog_id' => 2, 'json' => new SQLiteBlobValue(SQLiteJsonB::encode(['rows' => [
                ['option_name' => 'jsonb_settings', 'option_value' => '{"ok":true}', 'autoload' => 'no'],
            ]]))],
        ]);

        return $result['batches'][0]['json']['option_names'];
    },
    'JSON subtype source rows are accepted' => static function () use ($currentRows): mixed {
        $result = SQLiteTenantJsonWalImportPlan::plan($currentRows(), [
            ['name' => 'subtype_blog', 'blog_id' => 1, 'json' => new SQLiteJsonSubtypeValue('{"rows":[{"option_name":"subtype_settings","option_value":"{\"ok\":true}","autoload":"no"}]}')],
        ]);

        return $result['batches'][0]['json']['option_names'];
    },
    'path extraction can target nested rows' => static fn (): mixed => $plan([
        ['name' => 'nested_path', 'blog_id' => 1, 'path' => '$.payload.rows', 'json' => '{"payload":{"rows":[{"option_name":"nested_settings","option_value":"{\"ok\":true}"}]}}'],
    ])['batches'][0]['json']['option_names'],
    'missing path rolls batch back' => static fn (): mixed => $plan([
        ['name' => 'missing_path', 'blog_id' => 1, 'path' => '$.missing.rows', 'json' => '{"payload":{"rows":[]}}'],
    ])['rolled_back_batches'],
    'abort conflict rolls back duplicate option batch' => static fn (): mixed => $plan([
        ['name' => 'abort_conflict', 'blog_id' => 2, 'on_conflict' => 'abort', 'json' => $jsonRows([
            ['option_name' => 'plugin_settings', 'option_value' => '{"enabled":true}'],
        ])],
    ])['batches'][0]['status'],
    'replace conflict records conflict key' => static fn (): mixed => $plan([
        ['name' => 'replace_conflict', 'blog_id' => 2, 'json' => $jsonRows([
            ['option_name' => 'plugin_settings', 'option_value' => '{"enabled":true}'],
        ])],
    ])['batches'][0]['conflicts'],
    'replacement row updates blog two value' => static function () use ($plan, $jsonRows, $rowByKey): mixed {
        $result = $plan([
            ['name' => 'replace_conflict', 'blog_id' => 2, 'json' => $jsonRows([
                ['option_name' => 'plugin_settings', 'option_value' => '{"enabled":true}', 'autoload' => 'yes'],
            ])],
        ]);

        return $rowByKey($result, 'blog:1:2:plugin_settings')['autoload'];
    },
    'WAL frames preserve savepoint names in order' => static fn (): mixed => array_column($plan([
        ['name' => 'first_blog', 'blog_id' => 1, 'json' => $jsonRows([
            ['option_name' => 'first_settings', 'option_value' => '{"ok":1}'],
        ])],
        ['name' => 'second_blog', 'blog_id' => 2, 'json' => $jsonRows([
            ['option_name' => 'second_settings', 'option_value' => '{"ok":2}'],
        ])],
    ])['wal']['frames'], 'savepoint'),
    'page numbers isolate blog tables' => static fn (): mixed => array_column($plan([
        ['name' => 'first_blog', 'blog_id' => 1, 'json' => $jsonRows([
            ['option_id' => 70, 'option_name' => 'first_settings', 'option_value' => '{"ok":1}'],
        ])],
        ['name' => 'second_blog', 'blog_id' => 2, 'json' => $jsonRows([
            ['option_id' => 70, 'option_name' => 'second_settings', 'option_value' => '{"ok":2}'],
        ])],
    ])['wal']['frames'], 'page_number'),
    'network page numbers use network range' => static fn (): mixed => $plan([
        ['name' => 'network_page', 'scope' => 'network', 'json' => $jsonRows([
            ['meta_id' => 70, 'meta_key' => 'network_settings', 'meta_value' => '{"ok":true}'],
        ])],
    ])['wal']['frames'][0]['page_number'],
    'dependency marker names multisite JSON WAL import' => static fn (): mixed => in_array('sqlite-application-multisite-json-wal-import', $plan([
        ['name' => 'deps', 'blog_id' => 1, 'json' => $jsonRows([
            ['option_name' => 'deps_settings', 'option_value' => '{"ok":true}'],
        ])],
    ])['dependencies'], true),
    'bad source type rolls back batch as JSON admission failure' => static fn (): mixed => $plan([
        ['name' => 'bad_source_type', 'blog_id' => 1, 'json' => ['rows' => []]],
    ])['batches'][0]['status'],
    'bad blog id rolls back row normalization failure' => static fn (): mixed => $plan([
        ['name' => 'bad_blog_id', 'blog_id' => 0, 'json' => '{"rows":[{"option_name":"bad_blog_settings","option_value":"{\"ok\":true}"}]}'],
    ])['batches'][0]['status'],
    'later network release does not release open next blog preview' => static fn (): mixed => $plan([
        ['name' => 'next_preview', 'blog_id' => 2, 'release' => false, 'json' => $jsonRows([
            ['option_name' => 'preview_settings', 'option_value' => '{"ok":true}'],
        ])],
        ['name' => 'network_release', 'scope' => 'network', 'json' => $jsonRows([
            ['meta_key' => 'network_config', 'meta_value' => '{"ok":true}'],
        ])],
    ])['released_keys'],
];

$expected = [
    'released blog import is visible in final keys' => [
        'blog:1:1:plugin_settings',
        'blog:1:1:siteurl',
        'blog:1:1:theme_mods_twentyfive',
        'blog:1:2:plugin_settings',
        'blog:1:2:siteurl',
        'network:1:site_name',
    ],
    'released blog import records one WAL frame' => 1,
    'blog two writes use the blog two option table' => 'wp_2_options',
    'network import targets sitemeta table' => 'wp_sitemeta',
    'network import key is isolated from blog key' => [
        'blog:1:1:siteurl',
        'blog:1:1:theme_mods_twentyfive',
        'blog:1:2:plugin_settings',
        'blog:1:2:siteurl',
        'network:1:plugin_settings',
        'network:1:site_name',
    ],
    'current batch release list retains savepoint name' => ['current_site'],
    'open next batch is final-visible but unreleased' => [
        [
            'blog:1:1:siteurl',
            'blog:1:1:theme_mods_twentyfive',
            'blog:1:2:next_settings',
            'blog:1:2:plugin_settings',
            'blog:1:2:siteurl',
            'network:1:site_name',
        ],
        [
            'blog:1:1:siteurl',
            'blog:1:1:theme_mods_twentyfive',
            'blog:1:2:plugin_settings',
            'blog:1:2:siteurl',
            'network:1:site_name',
        ],
    ],
    'malformed next batch rolls back without removing released current batch' => [['current_ok'], ['next_bad']],
    'malformed option JSON rolls back only its batch' => 'rolled_back',
    'JSONB source rows are accepted' => ['jsonb_settings'],
    'JSON subtype source rows are accepted' => ['subtype_settings'],
    'path extraction can target nested rows' => ['nested_settings'],
    'missing path rolls batch back' => ['missing_path'],
    'abort conflict rolls back duplicate option batch' => 'rolled_back',
    'replace conflict records conflict key' => ['blog:1:2:plugin_settings'],
    'replacement row updates blog two value' => 'yes',
    'WAL frames preserve savepoint names in order' => ['first_blog', 'second_blog'],
    'page numbers isolate blog tables' => [3, 19],
    'network page numbers use network range' => 41,
    'dependency marker names multisite JSON WAL import' => true,
    'bad source type rolls back batch as JSON admission failure' => 'rolled_back',
    'bad blog id rolls back row normalization failure' => 'rolled_back',
    'later network release does not release open next blog preview' => [
        'blog:1:1:siteurl',
        'blog:1:1:theme_mods_twentyfive',
        'blog:1:2:plugin_settings',
        'blog:1:2:siteurl',
        'network:1:network_config',
        'network:1:site_name',
    ],
];

$tests = [];
foreach ($cases as $name => $callback) {
    $tests['sqlite application multisite json wal import current next54 ' . $name] = static function (TestRunner $t) use ($callback, $expected, $name): void {
        $t->same($expected[$name], $callback());
    };
}

foreach (range(1, 30) as $blogId) {
    $tests["sqlite application multisite json wal import current next54 generated blog {$blogId} maps option page"] = static function (TestRunner $t) use ($currentRows, $jsonRows, $blogId): void {
        $result = SQLiteTenantJsonWalImportPlan::plan($currentRows(), [
            ['name' => 'blog_' . $blogId, 'blog_id' => $blogId, 'json' => $jsonRows([
                ['option_id' => 65, 'option_name' => 'blog_' . $blogId . '_settings', 'option_value' => '{"blog":' . $blogId . '}'],
            ])],
        ]);

        $t->same('blog:' . 1 . ':' . $blogId . ':blog_' . $blogId . '_settings', $result['batches'][0]['writes'][0]['key']);
        $t->same(3 + (($blogId - 1) * 16), $result['wal']['frames'][0]['page_number']);
    };
}

foreach ([
    'bad scope' => ['scope' => 'user', 'json' => '{"rows":[]}'],
    'bad savepoint' => ['name' => 'bad-name', 'json' => '{"rows":[]}'],
] as $label => $import) {
    $tests["sqlite application multisite json wal import current next54 rejects {$label}"] = static function (TestRunner $t) use ($currentRows, $import): void {
        $t->throws(InvalidArgumentException::class, static fn () => SQLiteTenantJsonWalImportPlan::plan($currentRows(), [$import]));
    };
}

return $tests;
