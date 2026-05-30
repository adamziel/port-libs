<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteBulkImportSavepointPlan;

$currentRows = static fn (): array => [
    ['setting_id' => 1, 'key_name' => 'siteurl', 'key_value' => 'https://old.example', 'load_policy' => 'yes'],
    ['setting_id' => 2, 'key_name' => 'home', 'key_value' => 'https://old.example', 'load_policy' => 'yes'],
    ['setting_id' => 65, 'key_name' => 'active_plugins', 'key_value' => 'a:0:{}', 'load_policy' => 'no'],
    ['setting_id' => 130, 'key_name' => '_transient_feed', 'key_value' => 'stale', 'load_policy' => 'no'],
];

$batches = static fn (): array => [
    [
        'name' => 'core_urls',
        'rows' => [
            ['setting_id' => 1, 'key_name' => 'siteurl', 'key_value' => 'https://new.example', 'load_policy' => 'yes'],
            ['key_name' => 'blogname', 'key_value' => 'Imported Site', 'load_policy' => 'yes'],
        ],
    ],
    [
        'name' => 'plugins',
        'rows' => [
            ['setting_id' => 65, 'key_name' => 'active_plugins', 'key_value' => 'a:1:{i:0;s:19:"plugin/plugin.php";}', 'load_policy' => 'no'],
            ['setting_id' => 131, 'key_name' => 'home', 'key_value' => 'duplicate', 'load_policy' => 'yes'],
        ],
        'on_conflict' => 'rollback',
    ],
    [
        'name' => 'theme_mods',
        'rows' => [
            ['key_name' => 'theme_mods_twentytwentyfour', 'key_value' => 'a:1:{s:5:"color";s:4:"blue";}', 'load_policy' => 'yes'],
            ['key_name' => 'rewrite_rules', 'key_value' => 'rules', 'load_policy' => 'no'],
        ],
    ],
];

$plan = static fn (array $extraBatches = null, array $options = []): array => SQLiteBulkImportSavepointPlan::plan(
    $currentRows(),
    $extraBatches ?? $batches(),
    $options + ['database_path' => '/tmp/app-bulk-import.sqlite', 'page_size' => 1024]
);

$cases = [
    'status is planned' => [static fn (): mixed => $plan()['status'], 'planned'],
    'database path is preserved' => [static fn (): mixed => $plan()['database_path'], '/tmp/app-bulk-import.sqlite'],
    'page size is preserved' => [static fn (): mixed => $plan()['page_size'], 1024],
    'default journal mode is delete' => [static fn (): mixed => $plan()['journal_mode'], 'delete'],
    'default sync mode is full' => [static fn (): mixed => $plan()['sync_mode'], 'full'],
    'batch count is three' => [static fn (): mixed => $plan()['batch_count'], 3],
    'first batch is released' => [static fn (): mixed => $plan()['batches'][0]['status'], 'released'],
    'second batch rolls back' => [static fn (): mixed => $plan()['batches'][1]['status'], 'rolled_back'],
    'third batch is released' => [static fn (): mixed => $plan()['batches'][2]['status'], 'released'],
    'released batch names skip failed batch' => [static fn (): mixed => $plan()['released_batches'], ['core_urls', 'theme_mods']],
    'rolled back batch is named' => [static fn (): mixed => $plan()['rolled_back_batches'], ['plugins']],
    'first batch before names show current image' => [static fn (): mixed => $plan()['batches'][0]['before_names'], ['siteurl', 'home', 'active_plugins', '_transient_feed']],
    'first batch after names include blogname' => [static fn (): mixed => $plan()['batches'][0]['after_names'], ['siteurl', 'home', 'active_plugins', '_transient_feed', 'blogname']],
    'first batch updates siteurl' => [static fn (): mixed => $plan()['batches'][0]['updated'], 1],
    'first batch inserts blogname' => [static fn (): mixed => $plan()['batches'][0]['inserted'], 1],
    'first batch deletes none' => [static fn (): mixed => $plan()['batches'][0]['deleted'], 0],
    'first batch dirty pages include page two' => [static fn (): mixed => $plan()['batches'][0]['dirty_pages'], [2, 4]],
    'failed batch sees released blogname' => [static fn (): mixed => in_array('blogname', $plan()['batches'][1]['before_names'], true), true],
    'failed batch error reports unique conflict' => [static fn (): mixed => str_contains($plan()['batches'][1]['error'], 'unique key_name conflict'), true],
    'failed batch has no retained update count' => [static fn (): mixed => $plan()['batches'][1]['updated'], 0],
    'failed batch has no retained insert count' => [static fn (): mixed => $plan()['batches'][1]['inserted'], 0],
    'failed batch restores released image' => [static fn (): mixed => $plan()['batches'][1]['after_names'], ['siteurl', 'home', 'active_plugins', '_transient_feed', 'blogname']],
    'failed batch records no dirty pages after rollback' => [static fn (): mixed => $plan()['batches'][1]['dirty_pages'], []],
    'failed batch retains savepoint depth' => [static fn (): mixed => $plan()['batches'][1]['retained_depth'], 2],
    'failed batch is not released' => [static fn (): mixed => $plan()['batches'][1]['released'], false],
    'third batch starts from released image' => [static fn (): mixed => $plan()['batches'][2]['before_names'], ['siteurl', 'home', 'active_plugins', '_transient_feed', 'blogname']],
    'third batch inserts two rows' => [static fn (): mixed => $plan()['batches'][2]['inserted'], 2],
    'third batch updates none' => [static fn (): mixed => $plan()['batches'][2]['updated'], 0],
    'third batch dirties final leaf' => [static fn (): mixed => $plan()['batches'][2]['dirty_pages'], [4]],
    'final names omit rolled back duplicate' => [static fn (): mixed => in_array('duplicate', $plan()['final_key_names'], true), false],
    'final names keep active plugins original after rollback' => [static fn (): mixed => $plan()['final_rows'][2]['key_value'], 'a:0:{}'],
    'final names include blogname' => [static fn (): mixed => in_array('blogname', $plan()['final_key_names'], true), true],
    'final names include theme mods' => [static fn (): mixed => in_array('theme_mods_twentytwentyfour', $plan()['final_key_names'], true), true],
    'final names include rewrite rules' => [static fn (): mixed => in_array('rewrite_rules', $plan()['final_key_names'], true), true],
    'released names mirror final names' => [static fn (): mixed => $plan()['released_key_names'], $plan()['final_key_names']],
    'dirty pages coalesce released batches' => [static fn (): mixed => $plan()['dirty_pages'], [2, 4]],
    'journal bytes count coalesced pages' => [static fn (): mixed => $plan()['journal_bytes'], 2092],
    'dependency includes bulk import savepoint' => [static fn (): mixed => in_array('sqlite-application-bulk-import-savepoint-current', $plan()['dependencies'], true), true],
    'dependency includes current import transaction' => [static fn (): mixed => in_array('sqlite-application-import-transaction-current', $plan()['dependencies'], true), true],
    'dependency includes savepoint rollback' => [static fn (): mixed => in_array('sqlite-savepoint-current-rollback', $plan()['dependencies'], true), true],
    'persist journal option is preserved' => [static fn (): mixed => $plan(null, ['journal_mode' => 'persist'])['journal_mode'], 'persist'],
    'normal sync option is preserved' => [static fn (): mixed => $plan(null, ['sync_mode' => 'normal'])['sync_mode'], 'normal'],
    'replace conflicts lets duplicate batch commit' => [static fn (): mixed => $plan(null, ['replace_conflicts' => true])['batches'][1]['status'], 'released'],
    'replace conflicts deletes conflicting current owner' => [static fn (): mixed => $plan(null, ['replace_conflicts' => true])['batches'][1]['deleted'], 1],
    'replace conflicts updates both staged current rows' => [static fn (): mixed => $plan(null, ['replace_conflicts' => true])['batches'][1]['updated'], 2],
    'replace conflicts inserts renamed home row' => [static fn (): mixed => in_array('home', $plan(null, ['replace_conflicts' => true])['final_key_names'], true), true],
    'open batch remains visible but unreleased' => [static fn (): mixed => $plan([['name' => 'open_tail', 'release' => false, 'rows' => [['key_name' => 'open_marker', 'key_value' => '1', 'load_policy' => 'no']]]])['batches'][0]['status'], 'open'],
    'open batch does not update released image' => [static fn (): mixed => $plan([['name' => 'open_tail', 'release' => false, 'rows' => [['key_name' => 'open_marker', 'key_value' => '1', 'load_policy' => 'no']]]])['released_key_names'], ['siteurl', 'home', 'active_plugins', '_transient_feed']],
    'open batch remains in final image' => [static fn (): mixed => in_array('open_marker', $plan([['name' => 'open_tail', 'release' => false, 'rows' => [['key_name' => 'open_marker', 'key_value' => '1', 'load_policy' => 'no']]]])['final_key_names'], true), true],
    'last duplicate staged row wins inside batch' => [static fn (): mixed => $plan([['name' => 'dups', 'rows' => [['setting_id' => 1, 'key_name' => 'siteurl', 'key_value' => 'first', 'load_policy' => 'yes'], ['setting_id' => 1, 'key_name' => 'siteurl', 'key_value' => 'last', 'load_policy' => 'yes']]]])['final_rows'][0]['key_value'], 'last'],
    'batch name default is generated' => [static fn (): mixed => $plan([['rows' => [['key_name' => 'generated_name', 'key_value' => '1', 'load_policy' => 'no']]]])['batches'][0]['name'], 'app_bulk_1'],
    'abort conflict throws' => [static fn (): mixed => $plan([['name' => 'abort_batch', 'on_conflict' => 'abort', 'rows' => [['setting_id' => 131, 'key_name' => 'home', 'key_value' => 'dup', 'load_policy' => 'yes']]]]), LogicException::class],
    'empty batch list rejected' => [static fn (): mixed => SQLiteBulkImportSavepointPlan::plan($currentRows(), []), InvalidArgumentException::class],
    'bad conflict action rejected' => [static fn (): mixed => $plan([['name' => 'bad_action', 'on_conflict' => 'ignore', 'rows' => []]]), InvalidArgumentException::class],
    'bad savepoint name rejected' => [static fn (): mixed => $plan([['name' => 'bad-name', 'rows' => []]]), InvalidArgumentException::class],
    'empty savepoint name rejected' => [static fn (): mixed => $plan([['name' => '', 'rows' => []]]), InvalidArgumentException::class],
    'missing rows rejected' => [static fn (): mixed => $plan([['name' => 'missing_rows']]), InvalidArgumentException::class],
    'relative path rejected' => [static fn (): mixed => $plan(null, ['database_path' => 'wp.sqlite']), InvalidArgumentException::class],
    'invalid page size rejected' => [static fn (): mixed => $plan(null, ['page_size' => 1000]), InvalidArgumentException::class],
    'invalid journal mode rejected' => [static fn (): mixed => $plan(null, ['journal_mode' => 'wal']), InvalidArgumentException::class],
    'invalid sync mode rejected' => [static fn (): mixed => $plan(null, ['sync_mode' => 'extra']), InvalidArgumentException::class],
];

$tests = [];
foreach ($cases as $name => [$callback, $expected]) {
    $tests['application bulk import savepoint current next28 ' . $name] = static function (TestRunner $t) use ($callback, $expected): void {
        if (is_string($expected) && is_a($expected, Throwable::class, true)) {
            $t->throws($expected, $callback);
            return;
        }

        $t->same($expected, $callback());
    };
}

return $tests;
