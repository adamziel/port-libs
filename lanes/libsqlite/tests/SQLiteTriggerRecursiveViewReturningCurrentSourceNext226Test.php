<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteTriggerRecursiveViewReturningCurrentSourceNextPlan;

$rows226 = [
    ['option_id' => 1, 'option_name' => 'siteurl', 'option_value' => 'https://old.test', 'autoload' => 'yes'],
    ['option_id' => 2, 'option_name' => 'home', 'option_value' => 'https://home.test', 'autoload' => 'yes'],
];
$currentView226 = [
    'name' => 'wp_recursive_option_import',
    'source' => 'main@view-cookie-226-current',
    'trigger' => 'wp_recursive_option_import_io_insert',
    'trigger_source' => 'main@trigger-cookie-226-current',
    'columns' => ['import_id', 'name', 'value', 'autoload_flag', 'spawn_child'],
    'mapping' => ['import_id' => 'option_id', 'name' => 'option_name', 'value' => 'option_value', 'autoload_flag' => 'autoload', 'spawn_child' => 'spawn_child'],
    'audit_label' => 'current-recursive-view-trigger-226',
];
$nextView226 = $currentView226;
$nextView226['source'] = 'main@view-cookie-226-next';
$nextView226['trigger_source'] = 'main@trigger-cookie-226-next';
$postResetView226 = $currentView226;
$postResetView226['source'] = 'main@view-cookie-226-post-reset';
$postResetView226['trigger_source'] = 'main@trigger-cookie-226-post-reset';
$followingView226 = $currentView226;
$followingView226['source'] = 'main@view-cookie-226-following';
$followingView226['trigger_source'] = 'main@trigger-cookie-226-following';
$subsequentNextView226 = $nextView226;
$subsequentNextView226['source'] = 'main@view-cookie-226-subsequent-next';
$subsequentNextView226['trigger_source'] = 'main@trigger-cookie-226-subsequent-next';
$currentInput226 = [
    ['import_id' => 10, 'name' => 'siteurl', 'value' => 'https://current.test', 'autoload_flag' => 'yes', 'spawn_child' => true],
    ['import_id' => 11, 'name' => 'current_plugin', 'value' => 'enabled', 'autoload_flag' => 'no', 'spawn_child' => true],
];
$nextInput226 = [
    ['import_id' => 20, 'name' => 'home', 'value' => 'https://next.test', 'autoload_flag' => 'yes', 'spawn_child' => true],
    ['import_id' => 21, 'name' => 'next_plugin', 'value' => 'active', 'autoload_flag' => 'no', 'spawn_child' => false],
];
$returning226 = [
    ['expr' => 'new.option_name', 'as' => 'name'],
    ['expr' => 'new.option_value', 'as' => 'value'],
    ['expr' => 'event', 'as' => 'event_name'],
    ['expr' => 'depth', 'as' => 'depth_value'],
    ['expr' => 'ordinal', 'as' => 'ordinal_value'],
    ['expr' => 'trigger_source', 'as' => 'trigger_source_alias'],
    ['expr' => 'spawn_child', 'as' => 'spawn_child'],
];

$plan226 = static fn (array $options = []): array => SQLiteTriggerRecursiveViewReturningCurrentSourceNextPlan::executeNext226(
    $rows226,
    $currentInput226,
    $nextInput226,
    $currentView226,
    $nextView226,
    $returning226,
    $options + [
        'key' => 'option_name',
        'savepoint' => 'wp_recursive_view_226',
        'cursor_name' => 'wp_recursive_view_returning_cursor_226',
        'admit_next_source' => true,
        'rollback_token' => 'wp.rollback.current.226',
        'reset_generation' => 'wp-current-reset-226',
        'post_reset_current_source_token' => 'wp.current.source.postreset.226',
        'post_reset_cursor' => 'wp.returning.postreset.cursor.226',
        'post_reset_view' => $postResetView226,
        'post_reset_input' => [
            ['import_id' => 30, 'name' => 'siteurl', 'value' => 'https://fresh.test', 'autoload_flag' => 'yes', 'spawn_child' => false],
            ['import_id' => 31, 'name' => 'rewrite_rules', 'value' => 'fresh-rules', 'autoload_flag' => 'no', 'spawn_child' => false],
        ],
        'fresh_acknowledged_ordinals' => [0, 1],
        'next_source_token' => 'wp.next.source.226',
        'next_cursor' => 'wp.returning.next.cursor.226',
        'next_acknowledged_ordinals' => [0, 1],
        'close_next_cursor' => 'wp.returning.next.cursor.226',
        'following_current_source_token' => 'wp.current.source.following.base.226',
        'following_cursor' => 'wp.returning.following.cursor.226',
        'following_current_view' => $followingView226,
        'following_current_input' => [
            ['import_id' => 40, 'name' => 'blogdescription', 'value' => 'after-next', 'autoload_flag' => 'yes', 'spawn_child' => true],
            ['import_id' => 41, 'name' => 'stylesheet', 'value' => 'twentytwentyfive', 'autoload_flag' => 'yes', 'spawn_child' => false],
            ['import_id' => 42, 'name' => 'template', 'value' => 'twentytwentyfive', 'autoload_flag' => 'yes', 'spawn_child' => true],
        ],
        'following_generation' => 'wp-following-current-226',
        'recursive_child_acknowledged_ordinals' => [0, 1],
        'recursive_child_source_token' => 'wp.current.source.recursive.child.226',
        'recursive_child_cursor' => 'wp.returning.recursive.child.cursor.226',
        'recursive_child_generation' => 'wp-recursive-child-current-226',
        'current_generation_next203' => 'wp.current.recursive.returning.generation.226',
        'expected_current_generation_next203' => 'wp.current.recursive.returning.generation.226',
        'current_handoff_cursor_next203' => 'wp.returning.current.handoff.cursor.226',
        'current_generation_commit_marker_next203' => 'wp.current.recursive.returning.commit.226',
        'auto_ack_current_generation_receipts_next203' => true,
        'current_source_drain_token_next209' => 'wp.current.source.drain.226',
        'current_view_cookie_next209' => 'main@view-cookie-226-current',
        'current_trigger_cookie_next209' => 'main@trigger-cookie-226-current',
        'auto_ack_current_source_watermarks_next209' => true,
        'current_source_yield_token_next212' => 'wp.current.source.yield.226',
        'current_view_yield_cursor_next212' => 'wp.returning.view.yield.cursor.226',
        'current_trigger_yield_cursor_next212' => 'wp.returning.trigger.yield.cursor.226',
        'auto_ack_current_source_yields_next212' => true,
        'current_source_provenance_token_next217' => 'wp.current.source.provenance.226',
        'auto_ack_current_source_provenance_next217' => true,
        'next_source_reset_token_next219' => 'wp.next.source.reset.226',
        'next_source_reset_cursor_next219' => 'wp.returning.next.reset.cursor.226',
        'following_current_source_token_next219' => 'wp.current.source.following.226',
        'following_current_view_next219' => $followingView226,
        'following_current_input_next219' => [
            ['import_id' => 50, 'name' => 'active_plugins', 'value' => 'plugin-a/plugin.php', 'autoload_flag' => 'yes', 'spawn_child' => false],
            ['import_id' => 51, 'name' => 'rewrite_rules', 'value' => 'post-name-rules', 'autoload_flag' => 'yes', 'spawn_child' => true],
            ['import_id' => 52, 'name' => 'theme_mods_twentytwentyfive', 'value' => 'serialized-theme-mods', 'autoload_flag' => 'no', 'spawn_child' => false],
        ],
        'auto_ack_next_source_reset_next219' => true,
        'following_current_seal_token_next226' => 'wp.following.current.seal.226',
        'following_current_seal_cursor_next226' => 'wp.returning.following.current.cursor.226',
        'subsequent_next_source_token_next226' => 'wp.subsequent.next.source.226',
        'subsequent_next_view_next226' => $subsequentNextView226,
        'subsequent_next_input_next226' => [
            ['import_id' => 60, 'name' => 'cron', 'value' => 'sealed-next-cron', 'autoload_flag' => 'yes', 'spawn_child' => false],
            ['import_id' => 61, 'name' => 'widget_block', 'value' => 'sealed-widget-state', 'autoload_flag' => 'yes', 'spawn_child' => true],
        ],
    ],
);

$receipts226 = static fn (): array => $plan226()['required_following_current_seal_receipts_next226'];
$released226 = static fn (): array => $plan226(['auto_ack_following_current_seal_next226' => true]);
$missing226 = static fn (): array => $plan226(['acknowledged_following_current_seal_receipts_next226' => array_slice($receipts226(), 0, 1)]);
$unexpected226 = static fn (): array => $plan226(['acknowledged_following_current_seal_receipts_next226' => array_merge($receipts226(), ['abcdefabcdefabcdefabcdefabcdefabcd'])]);
$reversed226 = static fn (): array => $plan226(['acknowledged_following_current_seal_receipts_next226' => array_reverse($receipts226())]);
$unordered226 = static fn (): array => $plan226(['require_following_current_seal_order_next226' => false, 'acknowledged_following_current_seal_receipts_next226' => array_reverse($receipts226())]);
$baseHeld226 = static fn (): array => $plan226(['auto_ack_next_source_reset_next219' => false, 'auto_ack_following_current_seal_next226' => true]);
$badCursor226 = static fn (): array => $plan226(['auto_ack_following_current_seal_next226' => true, 'expected_following_current_seal_cursor_next226' => 'wp.returning.following.current.cursor.other.226']);
$badToken226 = static fn (): array => $plan226(['auto_ack_following_current_seal_next226' => true, 'expected_subsequent_next_source_token_next226' => 'wp.subsequent.next.source.other.226']);
$custom226 = static fn (): array => $plan226(['auto_ack_following_current_seal_next226' => true, 'following_current_seal_token_next226' => 'wp.following.current.seal.custom.226']);

$cases226 = [
    'released status' => [static fn (): mixed => $released226()['status_next226'], 'trigger-recursive-view-returning-current-source-next226-subsequent-next-visible'],
    'missing status' => [static fn (): mixed => $missing226()['status_next226'], 'trigger-recursive-view-returning-current-source-next226-seal-held'],
    'unexpected status' => [static fn (): mixed => $unexpected226()['status_next226'], 'trigger-recursive-view-returning-current-source-next226-seal-held'],
    'reversed status' => [static fn (): mixed => $reversed226()['status_next226'], 'trigger-recursive-view-returning-current-source-next226-seal-order-held'],
    'unordered reversed releases' => [static fn (): mixed => $unordered226()['status_next226'], 'trigger-recursive-view-returning-current-source-next226-subsequent-next-visible'],
    'base held status' => [static fn (): mixed => $baseHeld226()['status_next226'], 'trigger-recursive-view-returning-current-source-next226-base-held'],
    'bad cursor status' => [static fn (): mixed => $badCursor226()['status_next226'], 'trigger-recursive-view-returning-current-source-next226-seal-cursor-held'],
    'bad subsequent token status' => [static fn (): mixed => $badToken226()['status_next226'], 'trigger-recursive-view-returning-current-source-next226-subsequent-token-held'],
    'base next219 released' => [static fn (): mixed => $released226()['base']['status_next219'], 'trigger-recursive-view-returning-current-source-next219-following-current-visible'],
    'base following visible released' => [static fn (): mixed => $released226()['base_following_current_visible_next226'], true],
    'base following held' => [static fn (): mixed => $baseHeld226()['base_following_current_visible_next226'], false],
    'savepoint retained' => [static fn (): mixed => $released226()['savepoint'], 'wp_recursive_view_226'],
    'seal token retained' => [static fn (): mixed => $released226()['following_current_seal_token_next226'], 'wp.following.current.seal.226'],
    'custom seal token retained' => [static fn (): mixed => $custom226()['following_current_seal_token_next226'], 'wp.following.current.seal.custom.226'],
    'seal cursor retained' => [static fn (): mixed => $released226()['following_current_seal_cursor_next226'], 'wp.returning.following.current.cursor.226'],
    'expected seal cursor retained' => [static fn (): mixed => $released226()['expected_following_current_seal_cursor_next226'], 'wp.returning.following.current.cursor.226'],
    'cursor mismatch false' => [static fn (): mixed => $badCursor226()['following_current_seal_cursor_matches_next226'], false],
    'cursor released matches' => [static fn (): mixed => $released226()['following_current_seal_cursor_matches_next226'], true],
    'subsequent token retained' => [static fn (): mixed => $released226()['subsequent_next_source_token_next226'], 'wp.subsequent.next.source.226'],
    'subsequent token mismatch false' => [static fn (): mixed => $badToken226()['subsequent_next_source_token_matches_next226'], false],
    'required seal count' => [static fn (): mixed => count($released226()['required_following_current_seal_receipts_next226']), 3],
    'seals are 34 hex' => [static fn (): mixed => array_map(static fn (string $v): int => preg_match('/^[a-f0-9]{34}$/', $v), $released226()['required_following_current_seal_receipts_next226']), [1, 1, 1]],
    'auto acknowledged equals required' => [static fn (): mixed => $released226()['acknowledged_following_current_seal_receipts_next226'], $receipts226()],
    'missing acknowledged count' => [static fn (): mixed => count($missing226()['acknowledged_following_current_seal_receipts_next226']), 1],
    'missing receipts recorded' => [static fn (): mixed => $missing226()['missing_following_current_seal_receipts_next226'], array_slice($receipts226(), 1)],
    'unexpected receipt recorded' => [static fn (): mixed => $unexpected226()['unexpected_following_current_seal_receipts_next226'], ['abcdefabcdefabcdefabcdefabcdefabcd']],
    'released missing empty' => [static fn (): mixed => $released226()['missing_following_current_seal_receipts_next226'], []],
    'released unexpected empty' => [static fn (): mixed => $released226()['unexpected_following_current_seal_receipts_next226'], []],
    'require order default' => [static fn (): mixed => $released226()['require_following_current_seal_order_next226'], true],
    'order matches released' => [static fn (): mixed => $released226()['following_current_seal_order_matches_next226'], true],
    'order mismatch reversed' => [static fn (): mixed => $reversed226()['following_current_seal_order_matches_next226'], false],
    'unordered disables order' => [static fn (): mixed => $unordered226()['require_following_current_seal_order_next226'], false],
    'unordered order considered matched' => [static fn (): mixed => $unordered226()['following_current_seal_order_matches_next226'], true],
    'seal complete released' => [static fn (): mixed => $released226()['following_current_seal_complete_next226'], true],
    'seal incomplete missing' => [static fn (): mixed => $missing226()['following_current_seal_complete_next226'], false],
    'seal incomplete unexpected' => [static fn (): mixed => $unexpected226()['following_current_seal_complete_next226'], false],
    'subsequent visible released' => [static fn (): mixed => $released226()['subsequent_next_source_visible_next226'], true],
    'subsequent denied missing' => [static fn (): mixed => $missing226()['subsequent_next_source_visible_next226'], false],
    'subsequent denied unexpected' => [static fn (): mixed => $unexpected226()['subsequent_next_source_visible_next226'], false],
    'subsequent denied reversed' => [static fn (): mixed => $reversed226()['subsequent_next_source_visible_next226'], false],
    'subsequent denied cursor' => [static fn (): mixed => $badCursor226()['subsequent_next_source_visible_next226'], false],
    'subsequent denied token' => [static fn (): mixed => $badToken226()['subsequent_next_source_visible_next226'], false],
    'following row count' => [static fn (): mixed => $released226()['following_current_row_count_next226'], 3],
    'subsequent next row count' => [static fn (): mixed => $released226()['subsequent_next_row_count_next226'], 2],
    'subsequent held row count' => [static fn (): mixed => $missing226()['subsequent_next_row_count_next226'], 0],
    'visible released count includes subsequent' => [static fn (): mixed => $released226()['visible_row_count_next226'], 9],
    'visible held count excludes subsequent' => [static fn (): mixed => $missing226()['visible_row_count_next226'], 7],
    'following receipts tagged' => [static fn (): mixed => array_column($released226()['following_current_rows_next226'], 'following_current_seal_receipt_next226'), $receipts226()],
    'following seal token tagged' => [static fn (): mixed => array_values(array_unique(array_column($released226()['following_current_rows_next226'], 'following_current_seal_token_next226'))), ['wp.following.current.seal.226']],
    'following seal cursor tagged' => [static fn (): mixed => array_values(array_unique(array_column($released226()['following_current_rows_next226'], 'following_current_seal_cursor_next226'))), ['wp.returning.following.current.cursor.226']],
    'released following reasons empty' => [static fn (): mixed => $released226()['following_current_rows_next226'][0]['following_current_seal_reasons_next226'], []],
    'held following reason missing' => [static fn (): mixed => $missing226()['following_current_rows_next226'][0]['following_current_seal_reasons_next226'], ['following-current-seal-missing']],
    'held following reason unexpected' => [static fn (): mixed => $unexpected226()['following_current_rows_next226'][0]['following_current_seal_reasons_next226'], ['following-current-seal-unexpected']],
    'held following reason order' => [static fn (): mixed => $reversed226()['following_current_rows_next226'][0]['following_current_seal_reasons_next226'], ['following-current-seal-order-mismatch']],
    'held following reason cursor' => [static fn (): mixed => $badCursor226()['following_current_rows_next226'][0]['following_current_seal_reasons_next226'], ['following-current-seal-cursor-mismatch']],
    'held following reason token' => [static fn (): mixed => $badToken226()['following_current_rows_next226'][0]['following_current_seal_reasons_next226'], ['subsequent-next-source-token-mismatch']],
    'blocked reasons released empty' => [static fn (): mixed => $released226()['blocked_reasons_next226'], []],
    'blocked reasons missing' => [static fn (): mixed => $missing226()['blocked_reasons_next226'], ['following-current-seal-missing']],
    'blocked reasons base held' => [static fn (): mixed => $baseHeld226()['blocked_reasons_next226'], ['next-source-reset-missing']],
    'subsequent source label' => [static fn (): mixed => array_values(array_unique(array_column($released226()['subsequent_next_rows_next226'], 'statement_source'))), ['subsequent-next-after-following-current-seal']],
    'subsequent row ordinals' => [static fn (): mixed => array_column($released226()['subsequent_next_rows_next226'], 'returning_row_ordinal'), [0, 1]],
    'subsequent payload names' => [static fn (): mixed => array_column($released226()['subsequent_next_payloads_next226'], 'name'), ['cron', 'widget_block']],
    'subsequent payload event' => [static fn (): mixed => array_values(array_unique(array_column($released226()['subsequent_next_payloads_next226'], 'event_name'))), ['subsequent-next-after-following-current-seal']],
    'subsequent trigger source' => [static fn (): mixed => array_values(array_unique(array_column($released226()['subsequent_next_payloads_next226'], 'trigger_source_alias'))), ['main@trigger-cookie-226-subsequent-next']],
    'subsequent spawn child values' => [static fn (): mixed => array_column($released226()['subsequent_next_payloads_next226'], 'spawn_child'), [false, true]],
    'subsequent token stamped' => [static fn (): mixed => array_values(array_unique(array_column($released226()['subsequent_next_rows_next226'], 'subsequent_next_source_token_next226'))), ['wp.subsequent.next.source.226']],
    'subsequent seal token stamped' => [static fn (): mixed => array_values(array_unique(array_column($released226()['subsequent_next_rows_next226'], 'following_current_seal_token_next226'))), ['wp.following.current.seal.226']],
    'subsequent seal cursor stamped' => [static fn (): mixed => array_values(array_unique(array_column($released226()['subsequent_next_rows_next226'], 'following_current_seal_cursor_next226'))), ['wp.returning.following.current.cursor.226']],
    'subsequent view source stamped' => [static fn (): mixed => array_values(array_unique(array_column($released226()['subsequent_next_rows_next226'], 'next_view_source_next226'))), ['main@view-cookie-226-subsequent-next']],
    'subsequent trigger source stamped' => [static fn (): mixed => array_values(array_unique(array_column($released226()['subsequent_next_rows_next226'], 'next_trigger_source_next226'))), ['main@trigger-cookie-226-subsequent-next']],
    'visible payload names released' => [static fn (): mixed => array_column($released226()['visible_returning_payloads_next226'], 'name'), ['blogdescription_child', 'template_child', 'home', 'next_plugin', 'active_plugins', 'rewrite_rules', 'theme_mods_twentytwentyfive', 'cron', 'widget_block']],
    'visible payload names held' => [static fn (): mixed => array_column($missing226()['visible_returning_payloads_next226'], 'name'), ['blogdescription_child', 'template_child', 'home', 'next_plugin', 'active_plugins', 'rewrite_rules', 'theme_mods_twentytwentyfive']],
    'plan decision released' => [static fn (): mixed => $released226()['following_current_seal_plan_next226']['decision'], 'admit-subsequent-next-source-after-following-current-seal'],
    'plan decision missing' => [static fn (): mixed => $missing226()['following_current_seal_plan_next226']['decision'], 'hold-subsequent-next-source-until-following-current-seal'],
    'plan seal complete released' => [static fn (): mixed => $released226()['following_current_seal_plan_next226']['seal_complete'], true],
    'plan seal complete missing' => [static fn (): mixed => $missing226()['following_current_seal_plan_next226']['seal_complete'], false],
    'plan subsequent visible echoed' => [static fn (): mixed => $released226()['following_current_seal_plan_next226']['subsequent_next_source_visible'], true],
    'plan required echoed' => [static fn (): mixed => $released226()['following_current_seal_plan_next226']['required_seal_receipts'], $receipts226()],
    'plan acknowledged echoed' => [static fn (): mixed => $missing226()['following_current_seal_plan_next226']['acknowledged_seal_receipts'], array_slice($receipts226(), 0, 1)],
    'yield boundary released' => [static fn (): mixed => $released226()['yield_boundary_next226'], 'recursive-view-returning-next226-following-current-sealed-then-subsequent-next'],
    'yield boundary held' => [static fn (): mixed => $missing226()['yield_boundary_next226'], 'recursive-view-returning-next226-following-current-seal-fences-subsequent-next'],
    'dependency closure marker' => [static fn (): mixed => $released226()['dependency_closure_next226'], 'no-new-support-component-reuses-native-recursive-view-returning-next219-and-adds-following-current-seal-admission'],
    'dependency includes next226' => [static fn (): mixed => in_array('sqlite-trigger-recursive-view-returning-current-source-next226', $released226()['dependencies_next226'], true), true],
    'dependency includes seal fence' => [static fn (): mixed => in_array('sqlite-returning-following-current-seal-subsequent-next-fence', $released226()['dependencies_next226'], true), true],
    'dependency includes next219' => [static fn (): mixed => in_array('sqlite-trigger-recursive-view-returning-current-source-next219', $released226()['dependencies_next226'], true), true],
    'non overlap mentions next219' => [static fn (): mixed => str_contains($released226()['non_overlap_next226'], 'next219 next-source reset'), true],
    'bad seal token rejected' => [static fn (): mixed => $plan226(['following_current_seal_token_next226' => 'bad token']), InvalidArgumentException::class],
    'bad seal cursor rejected' => [static fn (): mixed => $plan226(['following_current_seal_cursor_next226' => 'bad cursor']), InvalidArgumentException::class],
    'bad subsequent token rejected' => [static fn (): mixed => $plan226(['subsequent_next_source_token_next226' => 'bad token']), InvalidArgumentException::class],
    'bad ack list rejected' => [static fn (): mixed => $plan226(['acknowledged_following_current_seal_receipts_next226' => ['x' => 'abcdefabcdefabcdefabcdefabcdefabcd']]), InvalidArgumentException::class],
    'bad short ack rejected' => [static fn (): mixed => $plan226(['acknowledged_following_current_seal_receipts_next226' => ['abc']]), InvalidArgumentException::class],
    'bad non hex ack rejected' => [static fn (): mixed => $plan226(['acknowledged_following_current_seal_receipts_next226' => ['zzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzz']]), InvalidArgumentException::class],
    'bad subsequent view rejected' => [static fn (): mixed => $plan226(['subsequent_next_view_next226' => ['source' => 'main@only-source']]), InvalidArgumentException::class],
    'bad subsequent input rejected' => [static fn (): mixed => $plan226(['subsequent_next_input_next226' => ['x' => []]]), InvalidArgumentException::class],
];

$tests = [];
foreach ($cases226 as $name => [$callback, $expected]) {
    $tests['trigger recursive view returning current source next226 ' . $name] = static function (TestRunner $t) use ($callback, $expected): void {
        if (is_string($expected) && is_a($expected, Throwable::class, true)) {
            $t->throws($expected, $callback);
            return;
        }
        $t->same($expected, $callback());
    };
}

return $tests;
