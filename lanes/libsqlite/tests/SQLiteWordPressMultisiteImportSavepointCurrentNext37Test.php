<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteMultisiteImportSavepointPlan;

$sites = static fn (): array => [
    [
        'blog_id' => 1,
        'current_rows' => [
            ['option_id' => 1, 'option_name' => 'siteurl', 'option_value' => 'https://main.old', 'autoload' => 'yes'],
            ['option_id' => 2, 'option_name' => 'home', 'option_value' => 'https://main.old', 'autoload' => 'yes'],
            ['option_id' => 65, 'option_name' => 'active_plugins', 'option_value' => 'a:0:{}', 'autoload' => 'no'],
        ],
        'batches' => [
            ['name' => 'urls', 'rows' => [
                ['option_id' => 1, 'option_name' => 'siteurl', 'option_value' => 'https://main.new', 'autoload' => 'yes'],
                ['option_name' => 'blogname', 'option_value' => 'Main Import', 'autoload' => 'yes'],
            ]],
            ['name' => 'plugins', 'rows' => [
                ['option_id' => 65, 'option_name' => 'active_plugins', 'option_value' => 'a:1:{i:0;s:8:"seo.php";}', 'autoload' => 'no'],
                ['option_id' => 130, 'option_name' => 'home', 'option_value' => 'duplicate', 'autoload' => 'yes'],
            ], 'on_conflict' => 'rollback'],
        ],
    ],
    [
        'blog_id' => 2,
        'current_rows' => [
            ['option_id' => 1, 'option_name' => 'siteurl', 'option_value' => 'https://second.old', 'autoload' => 'yes'],
            ['option_id' => 2, 'option_name' => 'home', 'option_value' => 'https://second.old', 'autoload' => 'yes'],
        ],
        'batches' => [
            ['name' => 'urls', 'rows' => [
                ['option_id' => 1, 'option_name' => 'siteurl', 'option_value' => 'https://second.new', 'autoload' => 'yes'],
                ['option_name' => 'blogdescription', 'option_value' => 'Imported child site', 'autoload' => 'no'],
            ]],
        ],
    ],
];

$globalBatches = static fn (): array => [
    ['name' => 'network_meta', 'rows' => [
        ['option_name' => 'site_admins', 'option_value' => 'a:1:{i:0;s:5:"admin";}', 'autoload' => 'no'],
        ['option_name' => 'registration', 'option_value' => 'none', 'autoload' => 'no'],
    ]],
];

$plan = static fn (array $siteRows = null, array $options = []): array => SQLiteMultisiteImportSavepointPlan::plan(
    $siteRows ?? $sites(),
    $options + [
        'database_path' => '/tmp/wp-multisite-import.sqlite',
        'page_size' => 1024,
        'global_batches' => $globalBatches(),
    ]
);

$cases = [
    'status is planned' => [static fn (): mixed => $plan()['status'], 'planned'],
    'database path is preserved' => [static fn (): mixed => $plan()['database_path'], '/tmp/wp-multisite-import.sqlite'],
    'page size is preserved' => [static fn (): mixed => $plan()['page_size'], 1024],
    'journal mode defaults to delete' => [static fn (): mixed => $plan()['journal_mode'], 'delete'],
    'sync mode defaults to full' => [static fn (): mixed => $plan()['sync_mode'], 'full'],
    'site count is two' => [static fn (): mixed => $plan()['site_count'], 2],
    'table names include main options' => [static fn (): mixed => $plan()['table_names'][0], 'wp_2_options'],
    'table names include child options' => [static fn (): mixed => $plan()['table_names'][1], 'wp_options'],
    'first site uses main table' => [static fn (): mixed => $plan()['sites'][0]['table'], 'wp_options'],
    'second site uses numbered table' => [static fn (): mixed => $plan()['sites'][1]['table'], 'wp_2_options'],
    'first savepoint prefix includes blog id' => [static fn (): mixed => $plan()['sites'][0]['savepoint_prefix'], 'blog1'],
    'second savepoint prefix includes blog id' => [static fn (): mixed => $plan()['sites'][1]['savepoint_prefix'], 'blog2'],
    'main first batch name is prefixed' => [static fn (): mixed => $plan()['sites'][0]['plan']['batches'][0]['name'], 'blog1_urls'],
    'main failed batch name is prefixed' => [static fn (): mixed => $plan()['sites'][0]['plan']['batches'][1]['name'], 'blog1_plugins'],
    'child batch name is prefixed' => [static fn (): mixed => $plan()['sites'][1]['plan']['batches'][0]['name'], 'blog2_urls'],
    'main site is partial after plugin rollback' => [static fn (): mixed => $plan()['sites'][0]['status'], 'partial'],
    'child site is released' => [static fn (): mixed => $plan()['sites'][1]['status'], 'released'],
    'released sites list child only' => [static fn (): mixed => $plan()['released_sites'], [2]],
    'rolled back sites list main partial site' => [static fn (): mixed => $plan()['rolled_back_sites'], [1]],
    'main released batch is preserved' => [static fn (): mixed => $plan()['sites'][0]['plan']['released_batches'], ['blog1_urls']],
    'main rolled back batch is isolated' => [static fn (): mixed => $plan()['sites'][0]['plan']['rolled_back_batches'], ['blog1_plugins']],
    'plugin rollback reports conflict' => [static fn (): mixed => str_contains($plan()['sites'][0]['plan']['batches'][1]['error'], 'unique option_name conflict'), true],
    'main final rows include blogname' => [static fn (): mixed => in_array('blogname', $plan()['sites'][0]['plan']['final_option_names'], true), true],
    'main final rows omit duplicate home value' => [static fn (): mixed => in_array('duplicate', array_column($plan()['sites'][0]['plan']['final_rows'], 'option_value'), true), false],
    'main active plugins remains original after rollback' => [static fn (): mixed => $plan()['sites'][0]['plan']['final_rows'][2]['option_value'], 'a:0:{}'],
    'child final rows include description' => [static fn (): mixed => in_array('blogdescription', $plan()['sites'][1]['plan']['final_option_names'], true), true],
    'child siteurl is updated' => [static fn (): mixed => $plan()['sites'][1]['plan']['final_rows'][0]['option_value'], 'https://second.new'],
    'main final rows are keyed under wp_options' => [static fn (): mixed => array_column($plan()['final_rows_by_table']['wp_options'], 'option_name'), ['siteurl', 'home', 'active_plugins', 'blogname']],
    'child final rows are keyed under wp_2_options' => [static fn (): mixed => array_column($plan()['final_rows_by_table']['wp_2_options'], 'option_name'), ['siteurl', 'home', 'blogdescription']],
    'released rows preserve only released main changes' => [static fn (): mixed => array_column($plan()['released_rows_by_table']['wp_options'], 'option_name'), ['siteurl', 'home', 'active_plugins', 'blogname']],
    'global plan is present' => [static fn (): mixed => is_array($plan()['global_plan']), true],
    'global savepoint name is prefixed' => [static fn (): mixed => $plan()['global_plan']['batches'][0]['name'], 'network_network_meta'],
    'global plan writes sitemeta table' => [static fn (): mixed => array_column($plan()['final_rows_by_table']['wp_sitemeta'], 'option_name'), ['site_admins', 'registration']],
    'global released rows are stored separately' => [static fn (): mixed => array_column($plan()['released_rows_by_table']['wp_sitemeta'], 'option_name'), ['site_admins', 'registration']],
    'dirty pages are namespaced by blog id' => [static fn (): mixed => $plan()['dirty_pages'], [2, 100002, 100003, 200002]],
    'journal bytes count multisite page namespace' => [static fn (): mixed => $plan()['journal_bytes'], 4156],
    'dependency includes multisite import' => [static fn (): mixed => in_array('sqlite-application-multisite-import-savepoint-current', $plan()['dependencies'], true), true],
    'dependency includes bulk import savepoint' => [static fn (): mixed => in_array('sqlite-application-bulk-import-savepoint-current', $plan()['dependencies'], true), true],
    'dependency includes savepoint rollback' => [static fn (): mixed => in_array('sqlite-savepoint-current-rollback', $plan()['dependencies'], true), true],
    'persist journal option is preserved' => [static fn (): mixed => $plan(null, ['journal_mode' => 'persist'])['journal_mode'], 'persist'],
    'normal sync option is preserved' => [static fn (): mixed => $plan(null, ['sync_mode' => 'normal'])['sync_mode'], 'normal'],
    'replace conflicts releases main plugin batch' => [static fn (): mixed => $plan(null, ['replace_conflicts' => true])['sites'][0]['plan']['batches'][1]['status'], 'released'],
    'replace conflicts clears rolled back sites' => [static fn (): mixed => $plan(null, ['replace_conflicts' => true])['rolled_back_sites'], []],
    'replace conflicts releases both sites' => [static fn (): mixed => $plan(null, ['replace_conflicts' => true])['released_sites'], [1, 2]],
    'replace conflicts updates active plugins' => [static fn (): mixed => $plan(null, ['replace_conflicts' => true])['sites'][0]['plan']['final_rows'][1]['option_value'], 'a:1:{i:0;s:8:"seo.php";}'],
    'continue on site error preserves later sites' => [static fn (): mixed => $plan([
        array_replace($sites()[0], ['batches' => [['name' => 'bad-name', 'rows' => []]]]),
        $sites()[1],
    ])['sites'][1]['status'], 'released'],
    'site error is reported when continuing' => [static fn (): mixed => str_contains($plan([
        array_replace($sites()[0], ['batches' => [['name' => 'bad-name', 'rows' => []]]]),
        $sites()[1],
    ])['sites'][0]['plan']['error'], 'savepoint names'), true],
    'continue off rethrows site error' => [static fn (): mixed => $plan([
        array_replace($sites()[0], ['batches' => [['name' => 'bad-name', 'rows' => []]]]),
        $sites()[1],
    ], ['continue_on_site_error' => false]), InvalidArgumentException::class],
    'empty site list rejected' => [static fn (): mixed => SQLiteMultisiteImportSavepointPlan::plan([]), InvalidArgumentException::class],
    'duplicate blog id rejected' => [static fn (): mixed => $plan([$sites()[0], $sites()[0]]), InvalidArgumentException::class],
    'zero blog id rejected' => [static fn (): mixed => $plan([['blog_id' => 0, 'current_rows' => [], 'batches' => []]]), InvalidArgumentException::class],
    'string blog id is accepted' => [static fn (): mixed => $plan([['blog_id' => '3', 'current_rows' => [['option_id' => 1, 'option_name' => 'siteurl', 'option_value' => 'x', 'autoload' => 'yes']], 'batches' => [['rows' => [['option_name' => 'blogname', 'option_value' => 'Third', 'autoload' => 'yes']]]]]], ['global_batches' => []])['sites'][0]['table'], 'wp_3_options'],
    'missing current rows rejected' => [static fn (): mixed => $plan([['blog_id' => 3, 'batches' => []]]), InvalidArgumentException::class],
    'missing batches rejected' => [static fn (): mixed => $plan([['blog_id' => 3, 'current_rows' => []]]), InvalidArgumentException::class],
    'global batches can be omitted' => [static fn (): mixed => $plan(null, ['global_batches' => []])['global_plan'], null],
    'relative database path rejected' => [static fn (): mixed => $plan(null, ['database_path' => 'wp.sqlite']), InvalidArgumentException::class],
    'invalid page size rejected' => [static fn (): mixed => $plan(null, ['page_size' => 1000]), InvalidArgumentException::class],
    'invalid journal mode rejected' => [static fn (): mixed => $plan(null, ['journal_mode' => 'wal']), InvalidArgumentException::class],
    'invalid sync mode rejected' => [static fn (): mixed => $plan(null, ['sync_mode' => 'extra']), InvalidArgumentException::class],
];

$tests = [];
foreach ($cases as $name => [$callback, $expected]) {
    $tests['application multisite import savepoint current next37 ' . $name] = static function (TestRunner $t) use ($callback, $expected): void {
        if (is_string($expected) && is_a($expected, Throwable::class, true)) {
            $t->throws($expected, $callback);
            return;
        }

        $t->same($expected, $callback());
    };
}

return $tests;
