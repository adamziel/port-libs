<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteBlobValue;
use PortLibs\LibSqlite\SQLiteJsonB;
use PortLibs\LibSqlite\SQLiteJsonSubtypeValue;
use PortLibs\LibSqlite\SQLiteNetworkJsonWalCurrentNextPlan;

$currentRows = static fn (): array => [
    ['scope' => 'blog', 'blog_id' => 1, 'option_name' => 'siteurl', 'option_value' => 'https://example.test', 'autoload' => 'yes'],
    ['scope' => 'blog', 'blog_id' => 2, 'option_name' => 'siteurl', 'option_value' => 'https://sub.example.test', 'autoload' => 'yes'],
    ['scope' => 'network', 'site_id' => 1, 'meta_key' => 'site_name', 'meta_value' => 'Example Network'],
];

$jsonRows = static fn (array $rows): string => json_encode(['rows' => $rows], JSON_THROW_ON_ERROR);
$plan = static fn (array $imports, array $options = []) => SQLiteNetworkJsonWalCurrentNextPlan::plan(
    $currentRows(),
    $imports,
    $options + ['database_path' => '/tmp/wp-network-json-current-next45.sqlite', 'page_size' => 1024],
);

$tests = [
    'plans blog JSON import into the main wp_options table' => static function (TestRunner $t) use ($plan, $jsonRows): void {
        $result = $plan([
            ['scope' => 'blog', 'blog_id' => 1, 'json' => $jsonRows([
                ['option_name' => 'plugin_settings', 'option_value' => '{"enabled":true}', 'autoload' => 'yes'],
            ]), 'path' => '$.rows'],
        ]);

        $t->same('planned', $result['status']);
        $t->same(['blog_1_json'], $result['released_batches']);
        $t->same('wp_options', $result['batches'][0]['table']);
        $t->same(1, $result['wal']['frame_count']);
    },
    'plans subsite JSON import into the numbered options table' => static function (TestRunner $t) use ($plan, $jsonRows): void {
        $result = $plan([
            ['scope' => 'blog', 'blog_id' => 7, 'json' => $jsonRows([
                ['option_name' => 'theme_mods_network_child', 'option_value' => '{"color":"blue"}'],
            ]), 'path' => '$.rows'],
        ]);

        $t->same('wp_7_options', $result['batches'][0]['table']);
        $t->same(7, $result['batches'][0]['blog_id']);
        $t->same('wp_7_options:theme_mods_network_child', $result['batches'][0]['changed_row_keys'][0]);
    },
    'plans network JSON import into wp_sitemeta' => static function (TestRunner $t) use ($plan, $jsonRows): void {
        $result = $plan([
            ['scope' => 'network', 'site_id' => 1, 'json' => $jsonRows([
                ['meta_key' => 'registration', 'meta_value' => '{"open":false}'],
            ]), 'path' => '$.rows'],
        ]);

        $t->same('wp_sitemeta', $result['batches'][0]['table']);
        $t->same(1, $result['batches'][0]['site_id']);
        $t->same('wp_sitemeta:registration', $result['batches'][0]['changed_row_keys'][0]);
    },
    'keeps current readers pinned before network JSON WAL frames' => static function (TestRunner $t) use ($plan, $jsonRows): void {
        $result = $plan([
            ['scope' => 'network', 'json' => $jsonRows([
                ['meta_key' => 'network_plugins', 'meta_value' => '["a/a.php"]'],
            ]), 'path' => '$.rows'],
        ], ['first_frame' => 12]);

        $t->same(12, $result['reader_visibility']['current_end_frame']);
        $t->same(13, $result['reader_visibility']['next_end_frame']);
        $t->same(12, $result['wal']['start_frame']);
    },
    'rolls malformed network JSON back to the current WAL frame' => static function (TestRunner $t) use ($plan): void {
        $result = $plan([
            ['scope' => 'network', 'json' => '{"rows":[', 'path' => '$.rows'],
        ], ['first_frame' => 4]);

        $t->same(['network_1_json'], $result['rolled_back_batches']);
        $t->same(4, $result['batches'][0]['wal_rollback_to_frame']);
        $t->same(0, $result['wal']['frame_count']);
    },
    'aborts malformed network JSON when requested' => static function (TestRunner $t) use ($plan): void {
        $t->throws(LogicException::class, static fn () => $plan([
            ['scope' => 'network', 'json' => '{"rows":[', 'path' => '$.rows', 'on_error' => 'abort'],
        ]));
    },
    'keeps open blog JSON batches visible but unreleased' => static function (TestRunner $t) use ($plan, $jsonRows): void {
        $result = $plan([
            ['scope' => 'blog', 'blog_id' => 2, 'json' => $jsonRows([
                ['option_name' => 'open_stage', 'option_value' => '1'],
            ]), 'path' => '$.rows', 'release' => false],
        ]);

        $t->same('open', $result['batches'][0]['status']);
        $t->same(4, $result['reader_visibility']['next_rows_visible']);
        $t->same(3, $result['reader_visibility']['released_rows_visible']);
    },
    'tracks same-statement conflicts against current network rows' => static function (TestRunner $t) use ($plan, $jsonRows): void {
        $result = $plan([
            ['scope' => 'network', 'json' => $jsonRows([
                ['meta_key' => 'site_name', 'meta_value' => 'Renamed Network'],
            ]), 'path' => '$.rows'],
        ]);

        $t->same(['wp_sitemeta:site_name'], $result['batches'][0]['conflict_row_keys']);
        $t->same('Renamed Network', $result['final_rows'][2]['meta_value']);
    },
    'accepts JSON subtype network payloads' => static function (TestRunner $t) use ($currentRows): void {
        $result = SQLiteNetworkJsonWalCurrentNextPlan::plan($currentRows(), [
            ['scope' => 'network', 'json' => new SQLiteJsonSubtypeValue('{"rows":[{"meta_key":"json_subtype","meta_value":"ok"}]}'), 'path' => '$.rows'],
        ], ['database_path' => '/tmp/wp-network-json-subtype-current-next45.sqlite']);

        $t->same(1, $result['wal']['frame_count']);
        $t->same('wp_sitemeta:json_subtype', $result['batches'][0]['changed_row_keys'][0]);
    },
    'accepts JSONB blog payloads' => static function (TestRunner $t) use ($currentRows): void {
        $blob = new SQLiteBlobValue(SQLiteJsonB::encode(['rows' => [
            ['option_name' => 'jsonb_blog_setting', 'option_value' => 'ok'],
        ]]));
        $result = SQLiteNetworkJsonWalCurrentNextPlan::plan($currentRows(), [
            ['scope' => 'blog', 'blog_id' => 3, 'json' => $blob, 'path' => '$.rows'],
        ], ['database_path' => '/tmp/wp-network-jsonb-current-next45.sqlite']);

        $t->same('wp_3_options', $result['batches'][0]['table']);
        $t->same(1, $result['wal']['frame_count']);
    },
];

foreach (range(1, 18) as $blogId) {
    $tests["maps blog {$blogId} JSON option writes to isolated WAL pages"] = static function (TestRunner $t) use ($currentRows, $jsonRows, $blogId): void {
        $result = SQLiteNetworkJsonWalCurrentNextPlan::plan($currentRows(), [
            ['scope' => 'blog', 'blog_id' => $blogId, 'json' => $jsonRows([
                ['option_name' => 'plugin_' . $blogId . '_settings', 'option_value' => '{"blog":' . $blogId . '}', 'autoload' => $blogId % 2 === 0 ? 'yes' : 'no'],
            ]), 'path' => '$.rows'],
        ], ['database_path' => '/tmp/wp-network-blog-' . $blogId . '-current-next45.sqlite']);

        $t->same('blog_' . $blogId . '_json', $result['batches'][0]['name']);
        $t->same($blogId === 1 ? 'wp_options' : 'wp_' . $blogId . '_options', $result['batches'][0]['table']);
        $t->same(1, $result['wal']['frame_count']);
    };
}

foreach (range(1, 12) as $siteId) {
    $tests["maps network {$siteId} JSON meta writes to sitemeta WAL pages"] = static function (TestRunner $t) use ($currentRows, $jsonRows, $siteId): void {
        $result = SQLiteNetworkJsonWalCurrentNextPlan::plan($currentRows(), [
            ['scope' => 'network', 'site_id' => $siteId, 'json' => $jsonRows([
                ['meta_key' => 'network_' . $siteId . '_setting', 'meta_value' => '{"site":' . $siteId . '}'],
            ]), 'path' => '$.rows'],
        ], ['database_path' => '/tmp/wp-network-site-' . $siteId . '-current-next45.sqlite']);

        $t->same('network_' . $siteId . '_json', $result['batches'][0]['name']);
        $t->same('wp_sitemeta:network_' . $siteId . '_setting', $result['batches'][0]['changed_row_keys'][0]);
        $t->same(true, $result['wal']['frames'][0]['commit_frame']);
    };
}

foreach ([
    '$' => [['option_name' => 'root_path', 'option_value' => '1']],
    '$.rows' => ['rows' => [['option_name' => 'rows_path', 'option_value' => '2']]],
    '$.payload.rows' => ['payload' => ['rows' => [['option_name' => 'payload_path', 'option_value' => '3']]]],
] as $path => $payload) {
    $tests["extracts blog JSON rows from path {$path}"] = static function (TestRunner $t) use ($plan, $path, $payload): void {
        $result = $plan([
            ['scope' => 'blog', 'blog_id' => 1, 'json' => json_encode($payload, JSON_THROW_ON_ERROR), 'path' => $path],
        ]);

        $t->same(1, $result['batches'][0]['json']['row_count']);
        $t->same(1, $result['wal']['frame_count']);
    };
}

foreach ([
    'missing path' => ['{"payload":[]}', '$.rows'],
    'scalar path' => ['{"rows":3}', '$.rows'],
    'empty option name' => ['{"rows":[{"option_name":""}]}', '$.rows'],
    'missing network key' => ['{"rows":[{"value":"x"}]}', '$.rows'],
    'non json input' => [17, '$.rows'],
] as $label => [$json, $path]) {
    $tests["rolls back {$label} without advancing WAL"] = static function (TestRunner $t) use ($plan, $label, $json, $path): void {
        $scope = $label === 'missing network key' ? 'network' : 'blog';
        $result = $plan([
            ['scope' => $scope, 'json' => $json, 'path' => $path],
        ]);

        $t->same('rolled_back', $result['batches'][0]['status']);
        $t->same(0, $result['wal']['frame_count']);
    };
}

$tests['combines released blog and network batches into one current-next WAL stream'] = static function (TestRunner $t) use ($plan, $jsonRows): void {
    $result = $plan([
        ['scope' => 'blog', 'blog_id' => 2, 'json' => $jsonRows([
            ['option_name' => 'blog_two_setting', 'option_value' => 'yes'],
        ]), 'path' => '$.rows'],
        ['scope' => 'network', 'site_id' => 1, 'json' => $jsonRows([
            ['meta_key' => 'network_flag', 'meta_value' => 'yes'],
        ]), 'path' => '$.rows'],
    ], ['first_frame' => 30]);

    $t->same(['blog_2_json', 'network_1_json'], $result['released_batches']);
    $t->same(32, $result['wal']['current_frame']);
    $t->same(['blog', 'network'], array_column($result['wal']['frames'], 'scope'));
};

$tests['rejects empty imports'] = static function (TestRunner $t) use ($currentRows): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteNetworkJsonWalCurrentNextPlan::plan($currentRows(), []));
};

$tests['rejects unsafe database paths'] = static function (TestRunner $t) use ($currentRows, $jsonRows): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteNetworkJsonWalCurrentNextPlan::plan($currentRows(), [
        ['scope' => 'blog', 'json' => $jsonRows([])],
    ], ['database_path' => '../wp.sqlite']));
};

$tests['rejects invalid page sizes'] = static function (TestRunner $t) use ($currentRows, $jsonRows): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteNetworkJsonWalCurrentNextPlan::plan($currentRows(), [
        ['scope' => 'blog', 'json' => $jsonRows([])],
    ], ['page_size' => 1000]));
};

$tests['rejects invalid scopes'] = static function (TestRunner $t) use ($plan, $jsonRows): void {
    $t->throws(InvalidArgumentException::class, static fn () => $plan([
        ['scope' => 'user', 'json' => $jsonRows([])],
    ]));
};

$tests['rejects invalid network ids'] = static function (TestRunner $t) use ($plan, $jsonRows): void {
    $t->throws(InvalidArgumentException::class, static fn () => $plan([
        ['scope' => 'network', 'site_id' => 0, 'json' => $jsonRows([])],
    ]));
};

$tests['rejects invalid blog ids'] = static function (TestRunner $t) use ($plan, $jsonRows): void {
    $t->throws(InvalidArgumentException::class, static fn () => $plan([
        ['scope' => 'blog', 'blog_id' => 0, 'json' => $jsonRows([])],
    ]));
};

$tests['rejects invalid on-error policies'] = static function (TestRunner $t) use ($plan, $jsonRows): void {
    $t->throws(InvalidArgumentException::class, static fn () => $plan([
        ['scope' => 'blog', 'json' => $jsonRows([]), 'on_error' => 'ignore'],
    ]));
};

return $tests;
