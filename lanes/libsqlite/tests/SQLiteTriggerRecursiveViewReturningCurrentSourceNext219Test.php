<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteTriggerRecursiveViewReturningCurrentSourceNextPlan;

$rows219 = [
    ['setting_id' => 1, 'key_name' => 'base_url', 'key_value' => 'https://old.test', 'load_policy' => 'yes'],
    ['setting_id' => 2, 'key_name' => 'landing_url', 'key_value' => 'https://landing_url.test', 'load_policy' => 'yes'],
];
$currentView219 = [
    'name' => 'app_recursive_setting_import',
    'source' => 'main@view-cookie-219-current',
    'trigger' => 'app_recursive_setting_import_io_insert',
    'trigger_source' => 'main@trigger-cookie-219-current',
    'columns' => ['import_id', 'name', 'value', 'load_policy_flag', 'spawn_child'],
    'mapping' => ['import_id' => 'setting_id', 'name' => 'key_name', 'value' => 'key_value', 'load_policy_flag' => 'load_policy', 'spawn_child' => 'spawn_child'],
    'audit_label' => 'current-recursive-view-trigger-219',
];
$nextView219 = $currentView219;
$nextView219['source'] = 'main@view-cookie-219-next';
$nextView219['trigger_source'] = 'main@trigger-cookie-219-next';
$postResetView219 = $currentView219;
$postResetView219['source'] = 'main@view-cookie-219-post-reset';
$postResetView219['trigger_source'] = 'main@trigger-cookie-219-post-reset';
$followingView219 = $currentView219;
$followingView219['source'] = 'main@view-cookie-219-following';
$followingView219['trigger_source'] = 'main@trigger-cookie-219-following';
$currentInput219 = [
    ['import_id' => 10, 'name' => 'base_url', 'value' => 'https://current.test', 'load_policy_flag' => 'yes', 'spawn_child' => true],
    ['import_id' => 11, 'name' => 'current_module', 'value' => 'enabled', 'load_policy_flag' => 'no', 'spawn_child' => true],
];
$nextInput219 = [
    ['import_id' => 20, 'name' => 'landing_url', 'value' => 'https://next.test', 'load_policy_flag' => 'yes', 'spawn_child' => true],
    ['import_id' => 21, 'name' => 'next_module', 'value' => 'active', 'load_policy_flag' => 'no', 'spawn_child' => false],
];
$returning219 = [
    ['expr' => 'new.key_name', 'as' => 'name'],
    ['expr' => 'new.key_value', 'as' => 'value'],
    ['expr' => 'event', 'as' => 'event_name'],
    ['expr' => 'depth', 'as' => 'depth_value'],
    ['expr' => 'ordinal', 'as' => 'ordinal_value'],
    ['expr' => 'trigger_source', 'as' => 'trigger_source_alias'],
    ['expr' => 'spawn_child', 'as' => 'spawn_child'],
];

$plan219 = static fn (array $options = []): array => SQLiteTriggerRecursiveViewReturningCurrentSourceNextPlan::executeFollowingCurrentResetFence(
    $rows219,
    $currentInput219,
    $nextInput219,
    $currentView219,
    $nextView219,
    $returning219,
    $options + [
        'key' => 'key_name',
        'savepoint' => 'app_recursive_view_219',
        'cursor_name' => 'app_recursive_view_returning_cursor_219',
        'admit_next_source' => true,
        'rollback_token' => 'app.rollback.current.219',
        'reset_generation' => 'app-current-reset-219',
        'post_reset_current_source_token' => 'app.current.source.postreset.219',
        'post_reset_cursor' => 'app.returning.postreset.cursor.219',
        'post_reset_view' => $postResetView219,
        'post_reset_input' => [
            ['import_id' => 30, 'name' => 'base_url', 'value' => 'https://fresh.test', 'load_policy_flag' => 'yes', 'spawn_child' => false],
            ['import_id' => 31, 'name' => 'routing_rules', 'value' => 'fresh-rules', 'load_policy_flag' => 'no', 'spawn_child' => false],
        ],
        'fresh_acknowledged_ordinals' => [0, 1],
        'next_source_token' => 'app.next.source.219',
        'next_cursor' => 'app.returning.next.cursor.219',
        'next_acknowledged_ordinals' => [0, 1],
        'close_next_cursor' => 'app.returning.next.cursor.219',
        'following_current_source_token' => 'app.current.source.following.base.219',
        'following_cursor' => 'app.returning.following.cursor.219',
        'following_current_view' => $followingView219,
        'following_current_input' => [
            ['import_id' => 40, 'name' => 'app_summary', 'value' => 'after-next', 'load_policy_flag' => 'yes', 'spawn_child' => true],
            ['import_id' => 41, 'name' => 'theme_style_key', 'value' => 'modern_theme', 'load_policy_flag' => 'yes', 'spawn_child' => false],
            ['import_id' => 42, 'name' => 'template', 'value' => 'modern_theme', 'load_policy_flag' => 'yes', 'spawn_child' => true],
        ],
        'following_generation' => 'app-following-current-219',
        'recursive_child_acknowledged_ordinals' => [0, 1],
        'recursive_child_source_token' => 'app.current.source.recursive.child.219',
        'recursive_child_cursor' => 'app.returning.recursive.child.cursor.219',
        'recursive_child_generation' => 'app-recursive-child-current-219',
        'current_generation_next203' => 'app.current.recursive.returning.generation.219',
        'expected_current_generation_next203' => 'app.current.recursive.returning.generation.219',
        'current_handoff_cursor_next203' => 'app.returning.current.handoff.cursor.219',
        'current_generation_commit_marker_next203' => 'app.current.recursive.returning.commit.219',
        'auto_ack_current_generation_receipts_next203' => true,
        'current_source_drain_token_next209' => 'app.current.source.drain.219',
        'current_view_cookie_next209' => 'main@view-cookie-219-current',
        'current_trigger_cookie_next209' => 'main@trigger-cookie-219-current',
        'auto_ack_current_source_watermarks_next209' => true,
        'current_source_yield_token_next212' => 'app.current.source.yield.219',
        'current_view_yield_cursor_next212' => 'app.returning.view.yield.cursor.219',
        'current_trigger_yield_cursor_next212' => 'app.returning.trigger.yield.cursor.219',
        'auto_ack_current_source_yields_next212' => true,
        'current_source_provenance_token_next217' => 'app.current.source.provenance.219',
        'auto_ack_current_source_provenance_next217' => true,
        'next_source_reset_token_next219' => 'app.next.source.reset.219',
        'next_source_reset_cursor_next219' => 'app.returning.next.reset.cursor.219',
        'following_current_source_token_next219' => 'app.current.source.following.219',
        'following_current_view_next219' => $followingView219,
        'following_current_input_next219' => [
            ['import_id' => 50, 'name' => 'active_modules', 'value' => 'module-a/module.php', 'load_policy_flag' => 'yes', 'spawn_child' => false],
            ['import_id' => 51, 'name' => 'routing_rules', 'value' => 'post-name-rules', 'load_policy_flag' => 'yes', 'spawn_child' => true],
            ['import_id' => 52, 'name' => 'theme_mods_modern_theme', 'value' => 'serialized-theme-mods', 'load_policy_flag' => 'no', 'spawn_child' => false],
        ],
    ],
);

$receipts219 = static fn (): array => $plan219()['required_next_source_reset_receipts_next219'];
$released219 = static fn (): array => $plan219(['auto_ack_next_source_reset_next219' => true]);
$missing219 = static fn (): array => $plan219(['acknowledged_next_source_reset_receipts_next219' => array_slice($receipts219(), 0, 1)]);
$unexpected219 = static fn (): array => $plan219(['acknowledged_next_source_reset_receipts_next219' => array_merge($receipts219(), ['abcdefabcdefabcdefabcdefabcdefabcd'])]);
$reversed219 = static fn (): array => $plan219(['acknowledged_next_source_reset_receipts_next219' => array_reverse($receipts219())]);
$unordered219 = static fn (): array => $plan219(['require_next_source_reset_order_next219' => false, 'acknowledged_next_source_reset_receipts_next219' => array_reverse($receipts219())]);
$baseHeld219 = static fn (): array => $plan219(['auto_ack_current_source_provenance_next217' => false, 'auto_ack_next_source_reset_next219' => true]);
$badCursor219 = static fn (): array => $plan219(['auto_ack_next_source_reset_next219' => true, 'expected_next_source_reset_cursor_next219' => 'app.returning.next.reset.cursor.other.219']);
$badFollowingToken219 = static fn (): array => $plan219(['auto_ack_next_source_reset_next219' => true, 'expected_following_current_source_token_next219' => 'app.current.source.following.other.219']);
$custom219 = static fn (): array => $plan219(['auto_ack_next_source_reset_next219' => true, 'next_source_reset_token_next219' => 'app.next.source.reset.custom.219']);

$cases219 = [
    'released status' => [static fn (): mixed => $released219()['status_next219'], 'trigger-recursive-view-returning-current-source-next219-following-current-visible'],
    'missing status' => [static fn (): mixed => $missing219()['status_next219'], 'trigger-recursive-view-returning-current-source-next219-reset-held'],
    'unexpected status' => [static fn (): mixed => $unexpected219()['status_next219'], 'trigger-recursive-view-returning-current-source-next219-reset-held'],
    'reversed status' => [static fn (): mixed => $reversed219()['status_next219'], 'trigger-recursive-view-returning-current-source-next219-reset-order-held'],
    'unordered reversed releases' => [static fn (): mixed => $unordered219()['status_next219'], 'trigger-recursive-view-returning-current-source-next219-following-current-visible'],
    'base held status' => [static fn (): mixed => $baseHeld219()['status_next219'], 'trigger-recursive-view-returning-current-source-next219-base-held'],
    'bad cursor status' => [static fn (): mixed => $badCursor219()['status_next219'], 'trigger-recursive-view-returning-current-source-next219-reset-cursor-held'],
    'bad following token status' => [static fn (): mixed => $badFollowingToken219()['status_next219'], 'trigger-recursive-view-returning-current-source-next219-following-token-held'],
    'base next217 released' => [static fn (): mixed => $released219()['base']['status_next217'], 'trigger-recursive-view-returning-current-source-next217-provenance-released'],
    'base visible released' => [static fn (): mixed => $released219()['base_next_source_visible_next219'], true],
    'base visible held' => [static fn (): mixed => $baseHeld219()['base_next_source_visible_next219'], false],
    'savepoint retained' => [static fn (): mixed => $released219()['savepoint'], 'app_recursive_view_219'],
    'reset token retained' => [static fn (): mixed => $released219()['next_source_reset_token_next219'], 'app.next.source.reset.219'],
    'custom token retained' => [static fn (): mixed => $custom219()['next_source_reset_token_next219'], 'app.next.source.reset.custom.219'],
    'reset cursor retained' => [static fn (): mixed => $released219()['next_source_reset_cursor_next219'], 'app.returning.next.reset.cursor.219'],
    'expected cursor retained' => [static fn (): mixed => $released219()['expected_next_source_reset_cursor_next219'], 'app.returning.next.reset.cursor.219'],
    'cursor mismatch false' => [static fn (): mixed => $badCursor219()['next_source_reset_cursor_matches_next219'], false],
    'cursor released matches' => [static fn (): mixed => $released219()['next_source_reset_cursor_matches_next219'], true],
    'following token retained' => [static fn (): mixed => $released219()['following_current_source_token_next219'], 'app.current.source.following.219'],
    'following token mismatch false' => [static fn (): mixed => $badFollowingToken219()['following_current_source_token_matches_next219'], false],
    'required receipt count' => [static fn (): mixed => count($released219()['required_next_source_reset_receipts_next219']), 2],
    'receipts are 34 hex' => [static fn (): mixed => array_map(static fn (string $v): int => preg_match('/^[a-f0-9]{34}$/', $v), $released219()['required_next_source_reset_receipts_next219']), [1, 1]],
    'auto acknowledged equals required' => [static fn (): mixed => $released219()['acknowledged_next_source_reset_receipts_next219'], $receipts219()],
    'missing acknowledged count' => [static fn (): mixed => count($missing219()['acknowledged_next_source_reset_receipts_next219']), 1],
    'missing receipt recorded' => [static fn (): mixed => $missing219()['missing_next_source_reset_receipts_next219'], [array_slice($receipts219(), -1)[0]]],
    'unexpected receipt recorded' => [static fn (): mixed => $unexpected219()['unexpected_next_source_reset_receipts_next219'], ['abcdefabcdefabcdefabcdefabcdefabcd']],
    'released missing empty' => [static fn (): mixed => $released219()['missing_next_source_reset_receipts_next219'], []],
    'released unexpected empty' => [static fn (): mixed => $released219()['unexpected_next_source_reset_receipts_next219'], []],
    'require order default' => [static fn (): mixed => $released219()['require_next_source_reset_order_next219'], true],
    'order matches released' => [static fn (): mixed => $released219()['next_source_reset_order_matches_next219'], true],
    'order mismatch reversed' => [static fn (): mixed => $reversed219()['next_source_reset_order_matches_next219'], false],
    'unordered disables order' => [static fn (): mixed => $unordered219()['require_next_source_reset_order_next219'], false],
    'unordered order considered matched' => [static fn (): mixed => $unordered219()['next_source_reset_order_matches_next219'], true],
    'reset complete released' => [static fn (): mixed => $released219()['next_source_reset_complete_next219'], true],
    'reset incomplete missing' => [static fn (): mixed => $missing219()['next_source_reset_complete_next219'], false],
    'reset incomplete unexpected' => [static fn (): mixed => $unexpected219()['next_source_reset_complete_next219'], false],
    'following visible released' => [static fn (): mixed => $released219()['following_current_source_visible_next219'], true],
    'following denied missing' => [static fn (): mixed => $missing219()['following_current_source_visible_next219'], false],
    'following denied unexpected' => [static fn (): mixed => $unexpected219()['following_current_source_visible_next219'], false],
    'following denied reversed' => [static fn (): mixed => $reversed219()['following_current_source_visible_next219'], false],
    'following denied cursor' => [static fn (): mixed => $badCursor219()['following_current_source_visible_next219'], false],
    'following denied token' => [static fn (): mixed => $badFollowingToken219()['following_current_source_visible_next219'], false],
    'attempted next row count' => [static fn (): mixed => $released219()['attempted_next_source_row_count_next219'], 2],
    'following current row count' => [static fn (): mixed => $released219()['following_current_row_count_next219'], 3],
    'following held row count' => [static fn (): mixed => $missing219()['following_current_row_count_next219'], 0],
    'visible released count includes following' => [static fn (): mixed => $released219()['visible_row_count_next219'], 7],
    'visible held count excludes following' => [static fn (): mixed => $missing219()['visible_row_count_next219'], 4],
    'attempted next receipts tagged' => [static fn (): mixed => array_column($released219()['attempted_next_source_rows_next219'], 'next_source_reset_receipt_next219'), $receipts219()],
    'attempted next reset token tagged' => [static fn (): mixed => array_values(array_unique(array_column($released219()['attempted_next_source_rows_next219'], 'next_source_reset_token_next219'))), ['app.next.source.reset.219']],
    'attempted next reset cursor tagged' => [static fn (): mixed => array_values(array_unique(array_column($released219()['attempted_next_source_rows_next219'], 'next_source_reset_cursor_next219'))), ['app.returning.next.reset.cursor.219']],
    'released next reasons empty' => [static fn (): mixed => $released219()['attempted_next_source_rows_next219'][0]['next_source_reset_reasons_next219'], []],
    'held next reason missing' => [static fn (): mixed => $missing219()['attempted_next_source_rows_next219'][0]['next_source_reset_reasons_next219'], ['next-source-reset-missing']],
    'held next reason unexpected' => [static fn (): mixed => $unexpected219()['attempted_next_source_rows_next219'][0]['next_source_reset_reasons_next219'], ['next-source-reset-unexpected']],
    'held next reason order' => [static fn (): mixed => $reversed219()['attempted_next_source_rows_next219'][0]['next_source_reset_reasons_next219'], ['next-source-reset-order-mismatch']],
    'held next reason cursor' => [static fn (): mixed => $badCursor219()['attempted_next_source_rows_next219'][0]['next_source_reset_reasons_next219'], ['next-source-reset-cursor-mismatch']],
    'held next reason following token' => [static fn (): mixed => $badFollowingToken219()['attempted_next_source_rows_next219'][0]['next_source_reset_reasons_next219'], ['following-current-source-token-mismatch']],
    'blocked reasons released empty' => [static fn (): mixed => $released219()['blocked_reasons_next219'], []],
    'blocked reasons missing' => [static fn (): mixed => $missing219()['blocked_reasons_next219'], ['next-source-reset-missing']],
    'blocked reasons base held' => [static fn (): mixed => $baseHeld219()['blocked_reasons_next219'], ['current-source-provenance-missing']],
    'following source label' => [static fn (): mixed => array_values(array_unique(array_column($released219()['following_current_rows_next219'], 'statement_source'))), ['following-current-after-next-reset']],
    'following row ordinals' => [static fn (): mixed => array_column($released219()['following_current_rows_next219'], 'returning_row_ordinal'), [0, 1, 2]],
    'following payload names' => [static fn (): mixed => array_column($released219()['following_current_payloads_next219'], 'name'), ['active_modules', 'routing_rules', 'theme_mods_modern_theme']],
    'following payload event' => [static fn (): mixed => array_values(array_unique(array_column($released219()['following_current_payloads_next219'], 'event_name'))), ['following-current-after-next-reset']],
    'following trigger source' => [static fn (): mixed => array_values(array_unique(array_column($released219()['following_current_payloads_next219'], 'trigger_source_alias'))), ['main@trigger-cookie-219-following']],
    'following spawn child values' => [static fn (): mixed => array_column($released219()['following_current_payloads_next219'], 'spawn_child'), [false, true, false]],
    'following token stamped' => [static fn (): mixed => array_values(array_unique(array_column($released219()['following_current_rows_next219'], 'following_current_source_token_next219'))), ['app.current.source.following.219']],
    'following reset token stamped' => [static fn (): mixed => array_values(array_unique(array_column($released219()['following_current_rows_next219'], 'next_source_reset_token_next219'))), ['app.next.source.reset.219']],
    'following reset cursor stamped' => [static fn (): mixed => array_values(array_unique(array_column($released219()['following_current_rows_next219'], 'next_source_reset_cursor_next219'))), ['app.returning.next.reset.cursor.219']],
    'following view source stamped' => [static fn (): mixed => array_values(array_unique(array_column($released219()['following_current_rows_next219'], 'current_view_source_next219'))), ['main@view-cookie-219-following']],
    'following trigger source stamped' => [static fn (): mixed => array_values(array_unique(array_column($released219()['following_current_rows_next219'], 'current_trigger_source_next219'))), ['main@trigger-cookie-219-following']],
    'visible payload names released' => [static fn (): mixed => array_column($released219()['visible_returning_payloads_next219'], 'name'), ['app_summary_child', 'template_child', 'landing_url', 'next_module', 'active_modules', 'routing_rules', 'theme_mods_modern_theme']],
    'visible payload names held' => [static fn (): mixed => array_column($missing219()['visible_returning_payloads_next219'], 'name'), ['app_summary_child', 'template_child', 'landing_url', 'next_module']],
    'plan decision released' => [static fn (): mixed => $released219()['next_source_reset_plan_next219']['decision'], 'admit-following-current-source-after-next-returning-reset'],
    'plan decision missing' => [static fn (): mixed => $missing219()['next_source_reset_plan_next219']['decision'], 'hold-following-current-source-until-next-returning-reset'],
    'plan reset complete released' => [static fn (): mixed => $released219()['next_source_reset_plan_next219']['reset_complete'], true],
    'plan reset complete missing' => [static fn (): mixed => $missing219()['next_source_reset_plan_next219']['reset_complete'], false],
    'plan following visible echoed' => [static fn (): mixed => $released219()['next_source_reset_plan_next219']['following_current_source_visible'], true],
    'plan required echoed' => [static fn (): mixed => $released219()['next_source_reset_plan_next219']['required_reset_receipts'], $receipts219()],
    'plan acknowledged echoed' => [static fn (): mixed => $missing219()['next_source_reset_plan_next219']['acknowledged_reset_receipts'], array_slice($receipts219(), 0, 1)],
    'yield boundary released' => [static fn (): mixed => $released219()['yield_boundary_next219'], 'recursive-view-returning-next219-next-source-reset-then-following-current'],
    'yield boundary held' => [static fn (): mixed => $missing219()['yield_boundary_next219'], 'recursive-view-returning-next219-next-source-reset-fences-following-current'],
    'dependency closure marker' => [static fn (): mixed => $released219()['dependency_closure_next219'], 'no-new-support-component-reuses-native-recursive-view-returning-provenance-and-adds-next-source-reset-admission-fence'],
    'dependency includes next219' => [static fn (): mixed => in_array('sqlite-trigger-recursive-view-returning-current-source-next219', $released219()['dependencies_next219'], true), true],
    'dependency includes reset fence' => [static fn (): mixed => in_array('sqlite-returning-next-source-reset-following-current-fence', $released219()['dependencies_next219'], true), true],
    'dependency includes next217' => [static fn (): mixed => in_array('sqlite-trigger-recursive-view-returning-current-source-next217', $released219()['dependencies_next219'], true), true],
    'non overlap mentions next217' => [static fn (): mixed => str_contains($released219()['non_overlap_next219'], 'next217 provenance'), true],
    'bad reset token rejected' => [static fn (): mixed => $plan219(['next_source_reset_token_next219' => 'bad token']), InvalidArgumentException::class],
    'bad reset cursor rejected' => [static fn (): mixed => $plan219(['next_source_reset_cursor_next219' => 'bad cursor']), InvalidArgumentException::class],
    'bad following token rejected' => [static fn (): mixed => $plan219(['following_current_source_token_next219' => 'bad token']), InvalidArgumentException::class],
    'bad ack list rejected' => [static fn (): mixed => $plan219(['acknowledged_next_source_reset_receipts_next219' => ['x' => 'abcdefabcdefabcdefabcdefabcdefabcd']]), InvalidArgumentException::class],
    'bad short ack rejected' => [static fn (): mixed => $plan219(['acknowledged_next_source_reset_receipts_next219' => ['abc']]), InvalidArgumentException::class],
    'bad non hex ack rejected' => [static fn (): mixed => $plan219(['acknowledged_next_source_reset_receipts_next219' => ['zzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzz']]), InvalidArgumentException::class],
    'bad following view rejected' => [static fn (): mixed => $plan219(['following_current_view_next219' => ['source' => 'main@only-source']]), InvalidArgumentException::class],
    'bad following input rejected' => [static fn (): mixed => $plan219(['following_current_input_next219' => ['x' => []]]), InvalidArgumentException::class],
];

$tests = [];
foreach ($cases219 as $name => [$callback, $expected]) {
    $tests['trigger recursive view returning current source next219 ' . $name] = static function (TestRunner $t) use ($callback, $expected): void {
        if (is_string($expected) && is_a($expected, Throwable::class, true)) {
            $t->throws($expected, $callback);
            return;
        }
        $t->same($expected, $callback());
    };
}

return $tests;
