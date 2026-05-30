<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteBulkImportSavepointPlan;

$currentRows = static fn (): array => [
    ['option_id' => 1, 'option_name' => 'siteurl', 'option_value' => 'https://old.example', 'autoload' => 'yes'],
    ['option_id' => 2, 'option_name' => 'home', 'option_value' => 'https://old.example', 'autoload' => 'yes'],
    ['option_id' => 65, 'option_name' => 'active_plugins', 'option_value' => 'a:0:{}', 'autoload' => 'no'],
    ['option_id' => 130, 'option_name' => '_transient_feed', 'option_value' => 'stale', 'autoload' => 'no'],
];

$batches = static fn (): array => [
    [
        'name' => 'core_urls',
        'rows' => [
            ['option_id' => 1, 'option_name' => 'siteurl', 'option_value' => 'https://new.example', 'autoload' => 'yes'],
            ['option_name' => 'blogname', 'option_value' => 'Imported Site', 'autoload' => 'yes'],
        ],
    ],
    [
        'name' => 'plugins',
        'rows' => [
            ['option_id' => 65, 'option_name' => 'active_plugins', 'option_value' => 'a:1:{i:0;s:19:"plugin/plugin.php";}', 'autoload' => 'no'],
            ['option_id' => 131, 'option_name' => 'home', 'option_value' => 'duplicate', 'autoload' => 'yes'],
        ],
        'on_conflict' => 'rollback',
    ],
    [
        'name' => 'theme_mods',
        'rows' => [
            ['option_name' => 'theme_mods_twentytwentyfour', 'option_value' => 'a:1:{s:5:"color";s:4:"blue";}', 'autoload' => 'yes'],
            ['option_name' => 'rewrite_rules', 'option_value' => 'rules', 'autoload' => 'no'],
        ],
    ],
];

$plan = static fn (array $extraBatches = null, array $options = []): array => SQLiteBulkImportSavepointPlan::plan(
    $currentRows(),
    $extraBatches ?? $batches(),
    $options + ['database_path' => '/tmp/wp-bulk-import.sqlite', 'page_size' => 1024]
);

$cases = [
    'status is planned' => [static fn (): mixed => $plan()['status'], 'planned'],
    'database path is preserved' => [static fn (): mixed => $plan()['database_path'], '/tmp/wp-bulk-import.sqlite'],
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
    'failed batch error reports unique conflict' => [static fn (): mixed => str_contains($plan()['batches'][1]['error'], 'unique option_name conflict'), true],
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
    'final names omit rolled back duplicate' => [static fn (): mixed => in_array('duplicate', $plan()['final_option_names'], true), false],
    'final names keep active plugins original after rollback' => [static fn (): mixed => $plan()['final_rows'][2]['option_value'], 'a:0:{}'],
    'final names include blogname' => [static fn (): mixed => in_array('blogname', $plan()['final_option_names'], true), true],
    'final names include theme mods' => [static fn (): mixed => in_array('theme_mods_twentytwentyfour', $plan()['final_option_names'], true), true],
    'final names include rewrite rules' => [static fn (): mixed => in_array('rewrite_rules', $plan()['final_option_names'], true), true],
    'released names mirror final names' => [static fn (): mixed => $plan()['released_option_names'], $plan()['final_option_names']],
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
    'replace conflicts inserts renamed home row' => [static fn (): mixed => in_array('home', $plan(null, ['replace_conflicts' => true])['final_option_names'], true), true],
    'open batch remains visible but unreleased' => [static fn (): mixed => $plan([['name' => 'open_tail', 'release' => false, 'rows' => [['option_name' => 'open_marker', 'option_value' => '1', 'autoload' => 'no']]]])['batches'][0]['status'], 'open'],
    'open batch does not update released image' => [static fn (): mixed => $plan([['name' => 'open_tail', 'release' => false, 'rows' => [['option_name' => 'open_marker', 'option_value' => '1', 'autoload' => 'no']]]])['released_option_names'], ['siteurl', 'home', 'active_plugins', '_transient_feed']],
    'open batch remains in final image' => [static fn (): mixed => in_array('open_marker', $plan([['name' => 'open_tail', 'release' => false, 'rows' => [['option_name' => 'open_marker', 'option_value' => '1', 'autoload' => 'no']]]])['final_option_names'], true), true],
    'last duplicate staged row wins inside batch' => [static fn (): mixed => $plan([['name' => 'dups', 'rows' => [['option_id' => 1, 'option_name' => 'siteurl', 'option_value' => 'first', 'autoload' => 'yes'], ['option_id' => 1, 'option_name' => 'siteurl', 'option_value' => 'last', 'autoload' => 'yes']]]])['final_rows'][0]['option_value'], 'last'],
    'batch name default is generated' => [static fn (): mixed => $plan([['rows' => [['option_name' => 'generated_name', 'option_value' => '1', 'autoload' => 'no']]]])['batches'][0]['name'], 'wp_bulk_1'],
    'abort conflict throws' => [static fn (): mixed => $plan([['name' => 'abort_batch', 'on_conflict' => 'abort', 'rows' => [['option_id' => 131, 'option_name' => 'home', 'option_value' => 'dup', 'autoload' => 'yes']]]]), LogicException::class],
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
