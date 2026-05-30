<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteBlobValue;
use PortLibs\LibSqlite\SQLiteJsonB;
use PortLibs\LibSqlite\SQLiteJsonSubtypeValue;
use PortLibs\LibSqlite\SQLiteNetworkJsonWalSavepointPlan;

$siteRows = static fn (): array => [
    [
        'blog_id' => 1,
        'current_rows' => [
            ['option_id' => 1, 'option_name' => 'siteurl', 'option_value' => 'https://main.old', 'autoload' => 'yes'],
            ['option_id' => 2, 'option_name' => 'home', 'option_value' => 'https://main.old', 'autoload' => 'yes'],
            ['option_id' => 65, 'option_name' => 'active_plugins', 'option_value' => 'a:0:{}', 'autoload' => 'no'],
        ],
        'json_imports' => [
            ['name' => 'settings', 'json' => '{"rows":[{"option_name":"main_plugin_settings","option_value":"{\"enabled\":true}","autoload":"yes"}]}', 'path' => '$.rows'],
            ['name' => 'bad_payload', 'json' => '{"rows":[', 'path' => '$.rows'],
        ],
    ],
    [
        'blog_id' => 2,
        'current_rows' => [
            ['option_id' => 1, 'option_name' => 'siteurl', 'option_value' => 'https://child.old', 'autoload' => 'yes'],
            ['option_id' => 2, 'option_name' => 'home', 'option_value' => 'https://child.old', 'autoload' => 'yes'],
        ],
        'json_imports' => [
            ['name' => 'settings', 'json' => '{"payload":{"rows":[{"option_name":"child_plugin_settings","option_value":"{\"mode\":\"child\"}","autoload":"no"}]}}', 'path' => '$.payload.rows'],
        ],
    ],
];

$globalImports = static fn (): array => [
    ['name' => 'network_flags', 'json' => '{"rows":[{"option_name":"site_admins","option_value":"a:1:{i:0;s:5:\"admin\";}","autoload":"no"},{"option_name":"registration","option_value":"none","autoload":"no"}]}', 'path' => '$.rows'],
];

$plan = static fn (array $sites = null, array $options = []): array => SQLiteNetworkJsonWalSavepointPlan::plan(
    $sites ?? $siteRows(),
    $options + [
        'database_path' => '/tmp/wp-network-json-current-next47.sqlite',
        'page_size' => 1024,
        'global_json_imports' => $globalImports(),
    ],
);

$cases = [
    'status is planned' => [static fn (): mixed => $plan()['status'], 'planned'],
    'database path is preserved' => [static fn (): mixed => $plan()['database_path'], '/tmp/wp-network-json-current-next47.sqlite'],
    'page size is preserved' => [static fn (): mixed => $plan()['page_size'], 1024],
    'journal mode defaults to WAL' => [static fn (): mixed => $plan()['journal_mode'], 'wal'],
    'sync mode defaults to normal' => [static fn (): mixed => $plan()['sync_mode'], 'normal'],
    'site count is two' => [static fn (): mixed => $plan()['site_count'], 2],
    'table names are sorted' => [static fn (): mixed => $plan()['table_names'], ['wp_2_options', 'wp_options']],
    'first site table is main options' => [static fn (): mixed => $plan()['sites'][0]['table'], 'wp_options'],
    'second site table is numbered options' => [static fn (): mixed => $plan()['sites'][1]['table'], 'wp_2_options'],
    'first prefix names blog' => [static fn (): mixed => $plan()['sites'][0]['savepoint_prefix'], 'blog1'],
    'second prefix names blog' => [static fn (): mixed => $plan()['sites'][1]['savepoint_prefix'], 'blog2'],
    'main released JSON import is prefixed' => [static fn (): mixed => $plan()['sites'][0]['plan']['batches'][0]['name'], 'blog1_settings'],
    'main malformed JSON import is prefixed' => [static fn (): mixed => $plan()['sites'][0]['plan']['batches'][1]['name'], 'blog1_bad_payload'],
    'child JSON import is prefixed' => [static fn (): mixed => $plan()['sites'][1]['plan']['batches'][0]['name'], 'blog2_settings'],
    'main site is partial after malformed JSON rollback' => [static fn (): mixed => $plan()['sites'][0]['status'], 'partial'],
    'child site is released' => [static fn (): mixed => $plan()['sites'][1]['status'], 'released'],
    'released sites contain child only' => [static fn (): mixed => $plan()['released_sites'], [2]],
    'rolled back sites contain main partial site' => [static fn (): mixed => $plan()['rolled_back_sites'], [1]],
    'main released batch survives later rollback' => [static fn (): mixed => $plan()['sites'][0]['plan']['released_batches'], ['blog1_settings']],
    'main bad batch rolls back only itself' => [static fn (): mixed => $plan()['sites'][0]['plan']['rolled_back_batches'], ['blog1_bad_payload']],
    'main bad batch is marked rolled back' => [static fn (): mixed => $plan()['sites'][0]['plan']['batches'][1]['status'], 'rolled_back'],
    'main bad batch reports malformed JSON' => [static fn (): mixed => str_contains($plan()['sites'][0]['plan']['batches'][1]['error'], 'malformed'), true],
    'main final rows include imported plugin settings' => [static fn (): mixed => array_column($plan()['final_rows_by_table']['wp_options'], 'option_name'), ['siteurl', 'home', 'active_plugins', 'main_plugin_settings']],
    'main released rows match final rows after bad rollback' => [static fn (): mixed => array_column($plan()['released_rows_by_table']['wp_options'], 'option_name'), ['siteurl', 'home', 'active_plugins', 'main_plugin_settings']],
    'child final rows include imported plugin settings' => [static fn (): mixed => array_column($plan()['final_rows_by_table']['wp_2_options'], 'option_name'), ['siteurl', 'home', 'child_plugin_settings']],
    'child imported autoload is preserved' => [static fn (): mixed => $plan()['final_rows_by_table']['wp_2_options'][2]['autoload'], 'no'],
    'global plan is present' => [static fn (): mixed => is_array($plan()['global_plan']), true],
    'global savepoint is network prefixed' => [static fn (): mixed => $plan()['global_plan']['batches'][0]['name'], 'network_network_flags'],
    'global rows map to sitemeta table' => [static fn (): mixed => array_column($plan()['final_rows_by_table']['wp_sitemeta'], 'option_name'), ['site_admins', 'registration']],
    'global released rows map to sitemeta table' => [static fn (): mixed => array_column($plan()['released_rows_by_table']['wp_sitemeta'], 'option_name'), ['site_admins', 'registration']],
    'main dirty page is network namespaced' => [static fn (): mixed => in_array(100003, $plan()['dirty_pages'], true), true],
    'child dirty page is network namespaced' => [static fn (): mixed => in_array(200002, $plan()['dirty_pages'], true), true],
    'global dirty page remains database namespace' => [static fn (): mixed => in_array(2, $plan()['dirty_pages'], true), true],
    'network WAL path follows database path' => [static fn (): mixed => $plan()['network_wal']['path'], '/tmp/wp-network-json-current-next47.sqlite-wal'],
    'network WAL has three committed frames' => [static fn (): mixed => $plan()['network_wal']['frame_count'], 3],
    'network WAL marks current next47' => [static fn (): mixed => $plan()['network_wal']['current_next47'], true],
    'first WAL frame belongs to main blog' => [static fn (): mixed => $plan()['network_wal']['frames'][0]['blog_id'], 1],
    'first WAL frame names wp_options' => [static fn (): mixed => $plan()['network_wal']['frames'][0]['table'], 'wp_options'],
    'first WAL frame has network frame index' => [static fn (): mixed => $plan()['network_wal']['frames'][0]['network_frame_index'], 1],
    'first WAL frame namespaces page number' => [static fn (): mixed => $plan()['network_wal']['frames'][0]['network_page_number'], 100003],
    'second WAL frame belongs to child blog' => [static fn (): mixed => $plan()['network_wal']['frames'][1]['blog_id'], 2],
    'second WAL frame names child table' => [static fn (): mixed => $plan()['network_wal']['frames'][1]['table'], 'wp_2_options'],
    'second WAL frame has network frame index' => [static fn (): mixed => $plan()['network_wal']['frames'][1]['network_frame_index'], 2],
    'second WAL frame namespaces page number' => [static fn (): mixed => $plan()['network_wal']['frames'][1]['network_page_number'], 200002],
    'global first WAL frame belongs to network namespace' => [static fn (): mixed => $plan()['network_wal']['frames'][2]['blog_id'], 0],
    'global first WAL frame names sitemeta' => [static fn (): mixed => $plan()['network_wal']['frames'][2]['table'], 'wp_sitemeta'],
    'global WAL frame receives next network index' => [static fn (): mixed => $plan()['network_wal']['frames'][2]['network_frame_index'], 3],
    'global WAL bytes include per table WAL headers' => [static fn (): mixed => $plan()['network_wal']['bytes'], 3240],
    'dependency names network JSON WAL slice' => [static fn (): mixed => in_array('sqlite-application-network-json-wal-savepoint', $plan()['dependencies'], true), true],
    'dependency names JSON WAL import planner' => [static fn (): mixed => in_array('sqlite-application-json-import-wal-savepoint', $plan()['dependencies'], true), true],
    'dependency names WAL rollback primitive' => [static fn (): mixed => in_array('sqlite-savepoint-wal-rollback', $plan()['dependencies'], true), true],
    'delete journal mode is preserved while WAL model is reported' => [static fn (): mixed => $plan(null, ['journal_mode' => 'delete'])['journal_mode'], 'delete'],
    'full sync mode is preserved' => [static fn (): mixed => $plan(null, ['sync_mode' => 'full'])['sync_mode'], 'full'],
    'omitting global imports removes sitemeta plan' => [static fn (): mixed => $plan(null, ['global_json_imports' => []])['global_plan'], null],
    'string blog id maps to numbered options table' => [static fn (): mixed => $plan([['blog_id' => '3', 'current_rows' => [['option_id' => 1, 'option_name' => 'siteurl', 'option_value' => 'https://third.old', 'autoload' => 'yes']], 'json_imports' => [['json' => '{"rows":[{"option_name":"third_plugin_settings","option_value":"{}"}]}', 'path' => '$.rows']]]], ['global_json_imports' => []])['sites'][0]['table'], 'wp_3_options'],
    'JSON subtype payload imports into site table' => [static fn (): mixed => array_column($plan([array_replace($siteRows()[0], ['json_imports' => [['name' => 'subtype', 'json' => new SQLiteJsonSubtypeValue('{"rows":[{"option_name":"subtype_network_settings","option_value":"{}"}]}'), 'path' => '$.rows']]])], ['global_json_imports' => []])['final_rows_by_table']['wp_options'], 'option_name'), ['siteurl', 'home', 'active_plugins', 'subtype_network_settings']],
    'JSONB payload imports into site table' => [static fn (): mixed => array_column($plan([array_replace($siteRows()[1], ['json_imports' => [['name' => 'jsonb', 'json' => new SQLiteBlobValue(SQLiteJsonB::encode(['rows' => [['option_name' => 'jsonb_network_settings', 'option_value' => '{}']]])), 'path' => '$.rows']]])], ['global_json_imports' => []])['final_rows_by_table']['wp_2_options'], 'option_name'), ['siteurl', 'home', 'jsonb_network_settings']],
    'site error continuing preserves later site' => [static fn (): mixed => $plan([
        array_replace($siteRows()[0], ['json_imports' => [['name' => 'bad-name', 'json' => '{"rows":[]}']]]),
        $siteRows()[1],
    ])['sites'][1]['status'], 'released'],
    'site error continuing reports rolled back site' => [static fn (): mixed => $plan([
        array_replace($siteRows()[0], ['json_imports' => [['name' => 'bad-name', 'json' => '{"rows":[]}']]]),
        $siteRows()[1],
    ])['sites'][0]['status'], 'rolled_back'],
    'site error continuing reports savepoint error' => [static fn (): mixed => str_contains($plan([
        array_replace($siteRows()[0], ['json_imports' => [['name' => 'bad-name', 'json' => '{"rows":[]}']]]),
        $siteRows()[1],
    ])['sites'][0]['plan']['error'], 'savepoint'), true],
    'site error continuing records no WAL frames for failed site' => [static fn (): mixed => $plan([
        array_replace($siteRows()[0], ['json_imports' => [['name' => 'bad-name', 'json' => '{"rows":[]}']]]),
        $siteRows()[1],
    ])['sites'][0]['plan']['wal']['frame_count'], 0],
    'continue off rethrows site error' => [static fn (): mixed => $plan([
        array_replace($siteRows()[0], ['json_imports' => [['name' => 'bad-name', 'json' => '{"rows":[]}']]]),
        $siteRows()[1],
    ], ['continue_on_site_error' => false]), InvalidArgumentException::class],
    'empty site list rejected' => [static fn (): mixed => SQLiteNetworkJsonWalSavepointPlan::plan([]), InvalidArgumentException::class],
    'duplicate blog id rejected' => [static fn (): mixed => $plan([$siteRows()[0], $siteRows()[0]]), InvalidArgumentException::class],
    'zero blog id rejected' => [static fn (): mixed => $plan([['blog_id' => 0, 'current_rows' => [], 'json_imports' => []]]), InvalidArgumentException::class],
    'missing current rows rejected' => [static fn (): mixed => $plan([['blog_id' => 3, 'json_imports' => []]]), InvalidArgumentException::class],
    'missing imports rejected' => [static fn (): mixed => $plan([['blog_id' => 3, 'current_rows' => []]]), InvalidArgumentException::class],
    'relative database path rejected by nested JSON WAL planner' => [static fn (): mixed => $plan(null, ['database_path' => 'wp.sqlite']), InvalidArgumentException::class],
    'invalid page size rejected by nested JSON WAL planner' => [static fn (): mixed => $plan(null, ['page_size' => 1000]), InvalidArgumentException::class],
    'invalid global imports rejected' => [static fn (): mixed => $plan(null, ['global_json_imports' => 'bad']), InvalidArgumentException::class],
];

$tests = [];
foreach ($cases as $name => [$callback, $expected]) {
    $tests['application network json wal savepoint current next47 ' . $name] = static function (TestRunner $t) use ($callback, $expected): void {
        if (is_string($expected) && is_a($expected, Throwable::class, true)) {
            $t->throws($expected, $callback);
            return;
        }

        $t->same($expected, $callback());
    };
}

return $tests;
