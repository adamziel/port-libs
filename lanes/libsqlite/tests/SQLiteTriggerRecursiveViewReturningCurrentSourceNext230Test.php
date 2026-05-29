<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteTriggerRecursiveViewReturningCurrentSourceNextPlan;

$rows230 = [
    ['option_id' => 1, 'option_name' => 'siteurl', 'option_value' => 'https://old.test', 'autoload' => 'yes'],
    ['option_id' => 2, 'option_name' => 'home', 'option_value' => 'https://home.test', 'autoload' => 'yes'],
];
$currentView230 = [
    'name' => 'wp_recursive_option_import',
    'source' => 'main@view-cookie-230-current',
    'trigger' => 'wp_recursive_option_import_io_insert',
    'trigger_source' => 'main@trigger-cookie-230-current',
    'columns' => ['import_id', 'name', 'value', 'autoload_flag', 'spawn_child'],
    'mapping' => ['import_id' => 'option_id', 'name' => 'option_name', 'value' => 'option_value', 'autoload_flag' => 'autoload', 'spawn_child' => 'spawn_child'],
    'audit_label' => 'current-recursive-view-trigger-230',
];
$nextView230 = $currentView230;
$nextView230['source'] = 'main@view-cookie-230-next';
$nextView230['trigger_source'] = 'main@trigger-cookie-230-next';
$postResetView230 = $currentView230;
$postResetView230['source'] = 'main@view-cookie-230-post-reset';
$postResetView230['trigger_source'] = 'main@trigger-cookie-230-post-reset';
$followingView230 = $currentView230;
$followingView230['source'] = 'main@view-cookie-230-following';
$followingView230['trigger_source'] = 'main@trigger-cookie-230-following';
$subsequentNextView230 = $nextView230;
$subsequentNextView230['source'] = 'main@view-cookie-230-subsequent-next';
$subsequentNextView230['trigger_source'] = 'main@trigger-cookie-230-subsequent-next';
$currentInput230 = [
    ['import_id' => 10, 'name' => 'siteurl', 'value' => 'https://current.test', 'autoload_flag' => 'yes', 'spawn_child' => true],
    ['import_id' => 11, 'name' => 'current_plugin', 'value' => 'enabled', 'autoload_flag' => 'no', 'spawn_child' => true],
];
$nextInput230 = [
    ['import_id' => 20, 'name' => 'home', 'value' => 'https://next.test', 'autoload_flag' => 'yes', 'spawn_child' => true],
    ['import_id' => 21, 'name' => 'next_plugin', 'value' => 'active', 'autoload_flag' => 'no', 'spawn_child' => false],
];
$returning230 = [
    ['expr' => 'new.option_name', 'as' => 'name'],
    ['expr' => 'new.option_value', 'as' => 'value'],
    ['expr' => 'event', 'as' => 'event_name'],
    ['expr' => 'depth', 'as' => 'depth_value'],
    ['expr' => 'ordinal', 'as' => 'ordinal_value'],
    ['expr' => 'trigger_source', 'as' => 'trigger_source_alias'],
    ['expr' => 'spawn_child', 'as' => 'spawn_child'],
];

$plan230 = static fn (array $options = []): array => SQLiteTriggerRecursiveViewReturningCurrentSourceNextPlan::executeNext230(
    $rows230,
    $currentInput230,
    $nextInput230,
    $currentView230,
    $nextView230,
    $returning230,
    $options + [
        'key' => 'option_name',
        'savepoint' => 'wp_recursive_view_230',
        'cursor_name' => 'wp_recursive_view_returning_cursor_230',
        'admit_next_source' => true,
        'rollback_token' => 'wp.rollback.current.230',
        'reset_generation' => 'wp-current-reset-230',
        'post_reset_current_source_token' => 'wp.current.source.postreset.230',
        'post_reset_cursor' => 'wp.returning.postreset.cursor.230',
        'post_reset_view' => $postResetView230,
        'post_reset_input' => [
            ['import_id' => 30, 'name' => 'siteurl', 'value' => 'https://fresh.test', 'autoload_flag' => 'yes', 'spawn_child' => false],
            ['import_id' => 31, 'name' => 'rewrite_rules', 'value' => 'fresh-rules', 'autoload_flag' => 'no', 'spawn_child' => false],
        ],
        'fresh_acknowledged_ordinals' => [0, 1],
        'next_source_token' => 'wp.next.source.230',
        'next_cursor' => 'wp.returning.next.cursor.230',
        'next_acknowledged_ordinals' => [0, 1],
        'close_next_cursor' => 'wp.returning.next.cursor.230',
        'following_current_source_token' => 'wp.current.source.following.base.230',
        'following_cursor' => 'wp.returning.following.cursor.230',
        'following_current_view' => $followingView230,
        'following_current_input' => [
            ['import_id' => 40, 'name' => 'blogdescription', 'value' => 'after-next', 'autoload_flag' => 'yes', 'spawn_child' => true],
            ['import_id' => 41, 'name' => 'stylesheet', 'value' => 'twentytwentyfive', 'autoload_flag' => 'yes', 'spawn_child' => false],
            ['import_id' => 42, 'name' => 'template', 'value' => 'twentytwentyfive', 'autoload_flag' => 'yes', 'spawn_child' => true],
        ],
        'following_generation' => 'wp-following-current-230',
        'recursive_child_acknowledged_ordinals' => [0, 1],
        'recursive_child_source_token' => 'wp.current.source.recursive.child.230',
        'recursive_child_cursor' => 'wp.returning.recursive.child.cursor.230',
        'recursive_child_generation' => 'wp-recursive-child-current-230',
        'current_generation_next203' => 'wp.current.recursive.returning.generation.230',
        'expected_current_generation_next203' => 'wp.current.recursive.returning.generation.230',
        'current_handoff_cursor_next203' => 'wp.returning.current.handoff.cursor.230',
        'current_generation_commit_marker_next203' => 'wp.current.recursive.returning.commit.230',
        'auto_ack_current_generation_receipts_next203' => true,
        'current_source_drain_token_next209' => 'wp.current.source.drain.230',
        'current_view_cookie_next209' => 'main@view-cookie-230-current',
        'current_trigger_cookie_next209' => 'main@trigger-cookie-230-current',
        'auto_ack_current_source_watermarks_next209' => true,
        'current_source_yield_token_next212' => 'wp.current.source.yield.230',
        'current_view_yield_cursor_next212' => 'wp.returning.view.yield.cursor.230',
        'current_trigger_yield_cursor_next212' => 'wp.returning.trigger.yield.cursor.230',
        'auto_ack_current_source_yields_next212' => true,
        'current_source_provenance_token_next217' => 'wp.current.source.provenance.230',
        'auto_ack_current_source_provenance_next217' => true,
        'next_source_reset_token_next219' => 'wp.next.source.reset.230',
        'next_source_reset_cursor_next219' => 'wp.returning.next.reset.cursor.230',
        'following_current_source_token_next219' => 'wp.current.source.following.230',
        'following_current_view_next219' => $followingView230,
        'following_current_input_next219' => [
            ['import_id' => 50, 'name' => 'active_plugins', 'value' => 'plugin-a/plugin.php', 'autoload_flag' => 'yes', 'spawn_child' => false],
            ['import_id' => 51, 'name' => 'rewrite_rules', 'value' => 'post-name-rules', 'autoload_flag' => 'yes', 'spawn_child' => true],
            ['import_id' => 52, 'name' => 'theme_mods_twentytwentyfive', 'value' => 'serialized-theme-mods', 'autoload_flag' => 'no', 'spawn_child' => false],
        ],
        'auto_ack_next_source_reset_next219' => true,
        'following_current_seal_token_next226' => 'wp.following.current.seal.230',
        'following_current_seal_cursor_next226' => 'wp.returning.following.current.cursor.230',
        'auto_ack_following_current_seal_next226' => true,
        'subsequent_next_source_token_next226' => 'wp.subsequent.next.source.230',
        'subsequent_next_view_next226' => $subsequentNextView230,
        'subsequent_next_input_next226' => [
            ['import_id' => 60, 'name' => 'cron', 'value' => 'sealed-next-cron', 'autoload_flag' => 'yes', 'spawn_child' => false],
            ['import_id' => 61, 'name' => 'widget_block', 'value' => 'sealed-widget-state', 'autoload_flag' => 'yes', 'spawn_child' => true],
        ],
        'current_source_epoch_next230' => 'wp.current.source.epoch.230',
        'current_source_epoch_cursor_next230' => 'wp.returning.current.epoch.cursor.230',
    ],
);

$receipts230 = static fn (): array => $plan230()['required_current_source_epoch_receipts_next230'];
$released230 = static fn (): array => $plan230(['auto_ack_current_source_epoch_next230' => true]);
$missing230 = static fn (): array => $plan230(['acknowledged_current_source_epoch_receipts_next230' => array_slice($receipts230(), 0, 1)]);
$unexpected230 = static fn (): array => $plan230(['acknowledged_current_source_epoch_receipts_next230' => array_merge($receipts230(), ['abcdefabcdefabcdefabcdefabcdefabcd'])]);
$reversed230 = static fn (): array => $plan230(['acknowledged_current_source_epoch_receipts_next230' => array_reverse($receipts230())]);
$unordered230 = static fn (): array => $plan230(['require_current_source_epoch_order_next230' => false, 'acknowledged_current_source_epoch_receipts_next230' => array_reverse($receipts230())]);
$baseHeld230 = static fn (): array => $plan230(['auto_ack_following_current_seal_next226' => false, 'auto_ack_current_source_epoch_next230' => true]);
$badEpoch230 = static fn (): array => $plan230(['auto_ack_current_source_epoch_next230' => true, 'expected_current_source_epoch_next230' => 'wp.current.source.epoch.other.230']);
$badCursor230 = static fn (): array => $plan230(['auto_ack_current_source_epoch_next230' => true, 'expected_current_source_epoch_cursor_next230' => 'wp.returning.current.epoch.cursor.other.230']);
$custom230 = static fn (): array => $plan230(['auto_ack_current_source_epoch_next230' => true, 'current_source_epoch_next230' => 'wp.current.source.epoch.custom.230']);

$cases230 = [
    'released status' => [static fn (): mixed => $released230()['status_next230'], 'trigger-recursive-view-returning-current-source-next230-subsequent-next-visible'],
    'missing status' => [static fn (): mixed => $missing230()['status_next230'], 'trigger-recursive-view-returning-current-source-next230-epoch-receipt-held'],
    'unexpected status' => [static fn (): mixed => $unexpected230()['status_next230'], 'trigger-recursive-view-returning-current-source-next230-epoch-receipt-held'],
    'reversed status' => [static fn (): mixed => $reversed230()['status_next230'], 'trigger-recursive-view-returning-current-source-next230-epoch-order-held'],
    'unordered released status' => [static fn (): mixed => $unordered230()['status_next230'], 'trigger-recursive-view-returning-current-source-next230-subsequent-next-visible'],
    'base held status' => [static fn (): mixed => $baseHeld230()['status_next230'], 'trigger-recursive-view-returning-current-source-next230-base-held'],
    'bad epoch status' => [static fn (): mixed => $badEpoch230()['status_next230'], 'trigger-recursive-view-returning-current-source-next230-epoch-held'],
    'bad cursor status' => [static fn (): mixed => $badCursor230()['status_next230'], 'trigger-recursive-view-returning-current-source-next230-cursor-held'],
    'base next226 released' => [static fn (): mixed => $released230()['base']['status_next226'], 'trigger-recursive-view-returning-current-source-next226-subsequent-next-visible'],
    'base next226 held' => [static fn (): mixed => $baseHeld230()['base']['status_next226'], 'trigger-recursive-view-returning-current-source-next226-seal-held'],
    'savepoint retained' => [static fn (): mixed => $released230()['savepoint'], 'wp_recursive_view_230'],
    'base subsequent visible true' => [static fn (): mixed => $released230()['base_subsequent_next_visible_next230'], true],
    'base subsequent visible false' => [static fn (): mixed => $baseHeld230()['base_subsequent_next_visible_next230'], false],
    'epoch retained' => [static fn (): mixed => $released230()['current_source_epoch_next230'], 'wp.current.source.epoch.230'],
    'custom epoch retained' => [static fn (): mixed => $custom230()['current_source_epoch_next230'], 'wp.current.source.epoch.custom.230'],
    'expected epoch retained' => [static fn (): mixed => $released230()['expected_current_source_epoch_next230'], 'wp.current.source.epoch.230'],
    'epoch matches released' => [static fn (): mixed => $released230()['current_source_epoch_matches_next230'], true],
    'epoch mismatch false' => [static fn (): mixed => $badEpoch230()['current_source_epoch_matches_next230'], false],
    'cursor retained' => [static fn (): mixed => $released230()['current_source_epoch_cursor_next230'], 'wp.returning.current.epoch.cursor.230'],
    'cursor matches released' => [static fn (): mixed => $released230()['current_source_epoch_cursor_matches_next230'], true],
    'cursor mismatch false' => [static fn (): mixed => $badCursor230()['current_source_epoch_cursor_matches_next230'], false],
    'required receipt count' => [static fn (): mixed => count($released230()['required_current_source_epoch_receipts_next230']), 3],
    'receipts are 34 hex' => [static fn (): mixed => array_map(static fn (string $v): int => preg_match('/^[a-f0-9]{34}$/', $v), $receipts230()), [1, 1, 1]],
    'auto ack equals required' => [static fn (): mixed => $released230()['acknowledged_current_source_epoch_receipts_next230'], $receipts230()],
    'missing ack count' => [static fn (): mixed => count($missing230()['acknowledged_current_source_epoch_receipts_next230']), 1],
    'missing receipts recorded' => [static fn (): mixed => $missing230()['missing_current_source_epoch_receipts_next230'], array_slice($receipts230(), 1)],
    'unexpected receipt recorded' => [static fn (): mixed => $unexpected230()['unexpected_current_source_epoch_receipts_next230'], ['abcdefabcdefabcdefabcdefabcdefabcd']],
    'released missing empty' => [static fn (): mixed => $released230()['missing_current_source_epoch_receipts_next230'], []],
    'released unexpected empty' => [static fn (): mixed => $released230()['unexpected_current_source_epoch_receipts_next230'], []],
    'require order default' => [static fn (): mixed => $released230()['require_current_source_epoch_order_next230'], true],
    'order matches released' => [static fn (): mixed => $released230()['current_source_epoch_order_matches_next230'], true],
    'order mismatch reversed' => [static fn (): mixed => $reversed230()['current_source_epoch_order_matches_next230'], false],
    'unordered considered matched' => [static fn (): mixed => $unordered230()['current_source_epoch_order_matches_next230'], true],
    'epoch complete released' => [static fn (): mixed => $released230()['current_source_epoch_complete_next230'], true],
    'epoch incomplete missing' => [static fn (): mixed => $missing230()['current_source_epoch_complete_next230'], false],
    'epoch incomplete unexpected' => [static fn (): mixed => $unexpected230()['current_source_epoch_complete_next230'], false],
    'subsequent visible released' => [static fn (): mixed => $released230()['subsequent_next_source_visible_after_epoch_next230'], true],
    'subsequent held missing' => [static fn (): mixed => $missing230()['subsequent_next_source_visible_after_epoch_next230'], false],
    'following row count' => [static fn (): mixed => $released230()['following_current_row_count_next230'], 3],
    'subsequent row count' => [static fn (): mixed => $released230()['subsequent_next_row_count_next230'], 2],
    'visible released count' => [static fn (): mixed => $released230()['visible_row_count_next230'], 9],
    'visible held count' => [static fn (): mixed => $missing230()['visible_row_count_next230'], 7],
    'held missing count' => [static fn (): mixed => $missing230()['held_subsequent_next_row_count_next230'], 2],
    'held released count' => [static fn (): mixed => $released230()['held_subsequent_next_row_count_next230'], 0],
    'following phase stamped' => [static fn (): mixed => array_values(array_unique(array_column($released230()['following_current_rows_next230'], 'source_epoch_phase_next230'))), ['following-current']],
    'subsequent phase stamped' => [static fn (): mixed => array_values(array_unique(array_column($released230()['subsequent_next_rows_next230'], 'source_epoch_phase_next230'))), ['subsequent-next']],
    'following visible stamped' => [static fn (): mixed => array_values(array_unique(array_column($released230()['following_current_rows_next230'], 'visible_after_current_source_epoch_next230'))), [true]],
    'subsequent visible stamped' => [static fn (): mixed => array_values(array_unique(array_column($released230()['subsequent_next_rows_next230'], 'visible_after_current_source_epoch_next230'))), [true]],
    'subsequent held stamped' => [static fn (): mixed => array_values(array_unique(array_column($missing230()['subsequent_next_rows_next230'], 'visible_after_current_source_epoch_next230'))), [false]],
    'following receipt stamped' => [static fn (): mixed => array_column($released230()['following_current_rows_next230'], 'current_source_epoch_receipt_next230'), $receipts230()],
    'subsequent receipt null' => [static fn (): mixed => array_values(array_unique(array_column($released230()['subsequent_next_rows_next230'], 'current_source_epoch_receipt_next230'))), [null]],
    'visible names released' => [static fn (): mixed => array_slice(array_column($released230()['visible_returning_payloads_next230'], 'name'), -2), ['cron', 'widget_block']],
    'visible names held excludes subsequent' => [static fn (): mixed => in_array('cron', array_column($missing230()['visible_returning_payloads_next230'], 'name'), true), false],
    'held names missing' => [static fn (): mixed => array_column($missing230()['held_subsequent_next_payloads_next230'], 'name'), ['cron', 'widget_block']],
    'blocked missing' => [static fn (): mixed => $missing230()['blocked_reasons_next230'], ['current-source-epoch-missing']],
    'blocked unexpected' => [static fn (): mixed => $unexpected230()['blocked_reasons_next230'], ['current-source-epoch-unexpected']],
    'blocked reversed' => [static fn (): mixed => $reversed230()['blocked_reasons_next230'], ['current-source-epoch-order-mismatch']],
    'blocked epoch' => [static fn (): mixed => $badEpoch230()['blocked_reasons_next230'], ['current-source-epoch-mismatch']],
    'blocked cursor' => [static fn (): mixed => $badCursor230()['blocked_reasons_next230'], ['current-source-epoch-cursor-mismatch']],
    'blocked released empty' => [static fn (): mixed => $released230()['blocked_reasons_next230'], []],
    'held row reasons copied' => [static fn (): mixed => $missing230()['subsequent_next_rows_next230'][0]['held_by_current_source_epoch_reasons_next230'], ['current-source-epoch-missing']],
    'plan decision released' => [static fn (): mixed => $released230()['current_source_epoch_plan_next230']['decision'], 'publish-subsequent-next-source-after-current-epoch'],
    'plan decision held' => [static fn (): mixed => $missing230()['current_source_epoch_plan_next230']['decision'], 'hold-subsequent-next-source-until-current-epoch'],
    'plan epoch complete echoed' => [static fn (): mixed => $released230()['current_source_epoch_plan_next230']['epoch_complete'], true],
    'yield boundary released' => [static fn (): mixed => $released230()['yield_boundary_next230'], 'recursive-view-returning-next230-current-source-epoch-then-subsequent-next'],
    'yield boundary held' => [static fn (): mixed => $missing230()['yield_boundary_next230'], 'recursive-view-returning-next230-current-source-epoch-fences-subsequent-next'],
    'dependency closure marker' => [static fn (): mixed => $released230()['dependency_closure_next230'], 'no-new-support-component-reuses-native-recursive-view-returning-next226-and-adds-current-source-epoch-admission'],
    'dependency includes next230' => [static fn (): mixed => in_array('sqlite-trigger-recursive-view-returning-current-source-next230', $released230()['dependencies_next230'], true), true],
    'dependency includes epoch fence' => [static fn (): mixed => in_array('sqlite-returning-current-source-epoch-fence', $released230()['dependencies_next230'], true), true],
    'non overlap mentions next226' => [static fn (): mixed => str_contains($released230()['non_overlap_next230'], 'next226 following-current seal'), true],
    'bad epoch rejected' => [static fn (): mixed => $plan230(['current_source_epoch_next230' => 'bad epoch']), InvalidArgumentException::class],
    'bad expected epoch rejected' => [static fn (): mixed => $plan230(['expected_current_source_epoch_next230' => 'bad epoch']), InvalidArgumentException::class],
    'bad cursor rejected' => [static fn (): mixed => $plan230(['current_source_epoch_cursor_next230' => 'bad cursor']), InvalidArgumentException::class],
    'bad receipt list rejected' => [static fn (): mixed => $plan230(['acknowledged_current_source_epoch_receipts_next230' => ['x' => 'abcdefabcdefabcdefabcdefabcdefabcd']]), InvalidArgumentException::class],
    'bad receipt value rejected' => [static fn (): mixed => $plan230(['acknowledged_current_source_epoch_receipts_next230' => ['abc']]), InvalidArgumentException::class],
];

$tests = [];
foreach ($cases230 as $name => [$callback, $expected]) {
    $tests['trigger recursive view returning current source next230 ' . $name] = static function (TestRunner $t) use ($callback, $expected): void {
        if (is_string($expected) && is_a($expected, Throwable::class, true)) {
            $t->throws($expected, $callback);
            return;
        }
        $t->same($expected, $callback());
    };
}

return $tests;
