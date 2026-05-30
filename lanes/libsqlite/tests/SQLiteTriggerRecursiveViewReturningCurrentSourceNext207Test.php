<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteTriggerRecursiveViewReturningCurrentSourceNextPlan;

$rows207 = [
    ['option_id' => 1, 'option_name' => 'siteurl', 'option_value' => 'https://old.test', 'autoload' => 'yes'],
    ['option_id' => 2, 'option_name' => 'home', 'option_value' => 'https://home.test', 'autoload' => 'yes'],
];
$currentView207 = [
    'name' => 'wp_recursive_option_import',
    'source' => 'main@view-cookie-207-current',
    'trigger' => 'wp_recursive_option_import_io_insert',
    'trigger_source' => 'main@trigger-cookie-207-current',
    'columns' => ['import_id', 'name', 'value', 'autoload_flag', 'spawn_child'],
    'mapping' => ['import_id' => 'option_id', 'name' => 'option_name', 'value' => 'option_value', 'autoload_flag' => 'autoload', 'spawn_child' => 'spawn_child'],
    'audit_label' => 'current-recursive-view-trigger-207',
];
$nextView207 = $currentView207;
$nextView207['source'] = 'main@view-cookie-207-next';
$nextView207['trigger_source'] = 'main@trigger-cookie-207-next';
$postResetView207 = $currentView207;
$postResetView207['source'] = 'main@view-cookie-207-post-reset';
$postResetView207['trigger_source'] = 'main@trigger-cookie-207-post-reset';
$followingView207 = $currentView207;
$followingView207['source'] = 'main@view-cookie-207-following';
$followingView207['trigger_source'] = 'main@trigger-cookie-207-following';
$currentInput207 = [
    ['import_id' => 10, 'name' => 'siteurl', 'value' => 'https://current.test', 'autoload_flag' => 'yes', 'spawn_child' => true],
    ['import_id' => 11, 'name' => 'current_plugin', 'value' => 'enabled', 'autoload_flag' => 'no', 'spawn_child' => true],
];
$nextInput207 = [
    ['import_id' => 20, 'name' => 'home', 'value' => 'https://next.test', 'autoload_flag' => 'yes', 'spawn_child' => true],
    ['import_id' => 21, 'name' => 'next_plugin', 'value' => 'active', 'autoload_flag' => 'no', 'spawn_child' => false],
];
$returning207 = [
    ['expr' => 'new.option_name', 'as' => 'name'],
    ['expr' => 'new.option_value', 'as' => 'value'],
    ['expr' => 'old.option_value', 'as' => 'old_value'],
    ['expr' => 'event', 'as' => 'event_name'],
    ['expr' => 'depth', 'as' => 'depth_value'],
    ['expr' => 'ordinal', 'as' => 'ordinal_value'],
    ['expr' => 'trigger_source', 'as' => 'trigger_source_alias'],
    ['expr' => 'spawn_child', 'as' => 'spawn_child'],
];

$plan207 = static fn (array $options = []): array => SQLiteTriggerRecursiveViewReturningCurrentSourceNextPlan::executeCurrentSourceDrainFence(
    $rows207,
    $currentInput207,
    $nextInput207,
    $currentView207,
    $nextView207,
    $returning207,
    $options + [
        'key' => 'option_name',
        'savepoint' => 'wp_recursive_view_207',
        'cursor_name' => 'wp_recursive_view_returning_cursor_207',
        'current_generation' => 'wp-current-returning-207',
        'next_generation' => 'wp-next-returning-207',
        'page_size' => 3,
        'admit_next_source' => true,
        'current_source_token' => 'wp.current.source.207',
        'drain_ack_token' => 'wp.returning.drain.207.base',
        'rollback_token' => 'wp.rollback.current.207',
        'reset_generation' => 'wp-current-reset-207',
        'post_reset_current_source_token' => 'wp.current.source.postreset.207',
        'post_reset_cursor' => 'wp.returning.postreset.cursor.207',
        'post_reset_view' => $postResetView207,
        'post_reset_input' => [
            ['import_id' => 30, 'name' => 'siteurl', 'value' => 'https://fresh.test', 'autoload_flag' => 'yes', 'spawn_child' => false],
            ['import_id' => 31, 'name' => 'rewrite_rules', 'value' => 'fresh-rules', 'autoload_flag' => 'no', 'spawn_child' => false],
        ],
        'fresh_acknowledged_ordinals' => [0, 1],
        'next_source_token' => 'wp.next.source.207',
        'next_cursor' => 'wp.returning.next.cursor.207',
        'next_acknowledged_ordinals' => [0, 1],
        'close_next_cursor' => 'wp.returning.next.cursor.207',
        'following_current_source_token' => 'wp.current.source.following.207',
        'following_cursor' => 'wp.returning.following.cursor.207',
        'following_current_view' => $followingView207,
        'following_current_input' => [
            ['import_id' => 40, 'name' => 'blogdescription', 'value' => 'after-next', 'autoload_flag' => 'yes', 'spawn_child' => true],
            ['import_id' => 41, 'name' => 'stylesheet', 'value' => 'twentytwentyfive', 'autoload_flag' => 'yes', 'spawn_child' => false],
            ['import_id' => 42, 'name' => 'template', 'value' => 'twentytwentyfive', 'autoload_flag' => 'yes', 'spawn_child' => true],
        ],
        'recursive_child_acknowledged_ordinals' => [0, 1],
        'recursive_child_source_token' => 'wp.current.source.recursive.child.207',
        'recursive_child_cursor' => 'wp.returning.recursive.child.cursor.207',
        'recursive_child_generation' => 'wp-recursive-child-current-207',
        'current_generation_next203' => 'wp.current.recursive.returning.generation.207',
        'expected_current_generation_next203' => 'wp.current.recursive.returning.generation.207',
        'current_handoff_cursor_next203' => 'wp.returning.current.handoff.cursor.207',
        'current_generation_commit_marker_next203' => 'wp.current.recursive.returning.commit.207',
        'auto_ack_current_generation_receipts_next203' => true,
        'yield_current_source_token_next206' => 'wp.current.recursive.returning.source.207',
        'yield_current_cursor_next206' => 'wp.returning.current.cursor.207',
        'yield_statement_token_next206' => 'wp.recursive.view.returning.statement.207',
        'current_returning_drain_token_next207' => 'wp.current.returning.drain.207',
        'expected_current_returning_drain_token_next207' => 'wp.current.returning.drain.207',
        'current_returning_cursor_next207' => 'wp.current.returning.cursor.207',
        'returning_statement_token_next207' => 'wp.recursive.view.returning.statement.207',
    ],
);

$drainKeys207 = static fn (): array => $plan207(['auto_ack_current_returning_drain_next207' => true])['current_returning_drain_keys_next207'];
$released207 = static fn (): array => $plan207(['auto_ack_current_returning_drain_next207' => true]);
$missing207 = static fn (): array => $plan207(['acknowledged_current_returning_drain_keys_next207' => array_slice($drainKeys207(), 0, 1)]);
$unexpected207 = static fn (): array => $plan207(['acknowledged_current_returning_drain_keys_next207' => array_merge($drainKeys207(), ['abcdefabcdefabcdefabcdefabcdefab'])]);
$reordered207 = static fn (): array => $plan207(['acknowledged_current_returning_drain_keys_next207' => array_reverse($drainKeys207())]);
$reorderedAllowed207 = static fn (): array => $plan207(['acknowledged_current_returning_drain_keys_next207' => array_reverse($drainKeys207()), 'require_returning_drain_order_next207' => false]);
$tokenHeld207 = static fn (): array => $plan207(['auto_ack_current_returning_drain_next207' => true, 'expected_current_returning_drain_token_next207' => 'wp.current.returning.drain.stale.207']);
$countHeld207 = static fn (): array => $plan207(['auto_ack_current_returning_drain_next207' => true, 'expected_current_returning_drain_count_next207' => 3]);
$baseHeld207 = static fn (): array => $plan207(['auto_ack_current_returning_drain_next207' => true, 'expected_yield_row_count_next206' => 3]);
$custom207 = static fn (): array => $plan207([
    'auto_ack_current_returning_drain_next207' => true,
    'current_returning_drain_token_next207' => 'wp.current.returning.drain.custom.207',
    'expected_current_returning_drain_token_next207' => 'wp.current.returning.drain.custom.207',
    'current_returning_cursor_next207' => 'wp.current.returning.cursor.custom.207',
    'returning_statement_token_next207' => 'wp.recursive.view.returning.statement.custom.207',
]);

$cases207 = [
    'released status' => [static fn (): mixed => $released207()['status_next207'], 'trigger-recursive-view-returning-current-source-next207-drain-released'],
    'missing status' => [static fn (): mixed => $missing207()['status_next207'], 'trigger-recursive-view-returning-current-source-next207-drain-held'],
    'unexpected status' => [static fn (): mixed => $unexpected207()['status_next207'], 'trigger-recursive-view-returning-current-source-next207-drain-held'],
    'reordered status' => [static fn (): mixed => $reordered207()['status_next207'], 'trigger-recursive-view-returning-current-source-next207-drain-held'],
    'reordered allowed status' => [static fn (): mixed => $reorderedAllowed207()['status_next207'], 'trigger-recursive-view-returning-current-source-next207-drain-released'],
    'token held status' => [static fn (): mixed => $tokenHeld207()['status_next207'], 'trigger-recursive-view-returning-current-source-next207-token-held'],
    'count held status' => [static fn (): mixed => $countHeld207()['status_next207'], 'trigger-recursive-view-returning-current-source-next207-count-held'],
    'base held status' => [static fn (): mixed => $baseHeld207()['status_next207'], 'trigger-recursive-view-returning-current-source-next207-base-held'],
    'base next206 released' => [static fn (): mixed => $released207()['base']['status_next206'], 'trigger-recursive-view-returning-current-source-next206-watermark-released'],
    'base next206 held' => [static fn (): mixed => $baseHeld207()['base']['status_next206'], 'trigger-recursive-view-returning-current-source-next206-row-count-held'],
    'savepoint retained' => [static fn (): mixed => $released207()['savepoint'], 'wp_recursive_view_207'],
    'base visible true' => [static fn (): mixed => $released207()['base_next_source_visible_next207'], true],
    'base visible false' => [static fn (): mixed => $baseHeld207()['base_next_source_visible_next207'], false],
    'drain token retained' => [static fn (): mixed => $released207()['current_returning_drain_token_next207'], 'wp.current.returning.drain.207'],
    'expected token retained' => [static fn (): mixed => $released207()['expected_current_returning_drain_token_next207'], 'wp.current.returning.drain.207'],
    'token matches' => [static fn (): mixed => $released207()['current_returning_drain_token_matches_next207'], true],
    'token mismatch' => [static fn (): mixed => $tokenHeld207()['current_returning_drain_token_matches_next207'], false],
    'cursor retained' => [static fn (): mixed => $released207()['current_returning_cursor_next207'], 'wp.current.returning.cursor.207'],
    'statement retained' => [static fn (): mixed => $released207()['returning_statement_token_next207'], 'wp.recursive.view.returning.statement.207'],
    'custom token retained' => [static fn (): mixed => $custom207()['current_returning_drain_token_next207'], 'wp.current.returning.drain.custom.207'],
    'custom cursor retained' => [static fn (): mixed => $custom207()['current_returning_cursor_next207'], 'wp.current.returning.cursor.custom.207'],
    'custom statement retained' => [static fn (): mixed => $custom207()['returning_statement_token_next207'], 'wp.recursive.view.returning.statement.custom.207'],
    'drain key count' => [static fn (): mixed => count($released207()['current_returning_drain_keys_next207']), 2],
    'drain keys are hex' => [static fn (): mixed => array_map(static fn (string $v): int => preg_match('/^[a-f0-9]{32}$/', $v), $released207()['current_returning_drain_keys_next207']), [1, 1]],
    'auto ack equals keys' => [static fn (): mixed => $released207()['acknowledged_current_returning_drain_keys_next207'], $drainKeys207()],
    'missing key recorded' => [static fn (): mixed => $missing207()['missing_current_returning_drain_keys_next207'], [array_slice($drainKeys207(), -1)[0]]],
    'unexpected key recorded' => [static fn (): mixed => $unexpected207()['unexpected_current_returning_drain_keys_next207'], ['abcdefabcdefabcdefabcdefabcdefab']],
    'released missing empty' => [static fn (): mixed => $released207()['missing_current_returning_drain_keys_next207'], []],
    'released unexpected empty' => [static fn (): mixed => $released207()['unexpected_current_returning_drain_keys_next207'], []],
    'order required default' => [static fn (): mixed => $released207()['require_returning_drain_order_next207'], true],
    'order matches released' => [static fn (): mixed => $released207()['current_returning_drain_order_matches_next207'], true],
    'order mismatch detected' => [static fn (): mixed => $reordered207()['current_returning_drain_order_matches_next207'], false],
    'order disabled flag' => [static fn (): mixed => $reorderedAllowed207()['require_returning_drain_order_next207'], false],
    'drain count' => [static fn (): mixed => $released207()['current_returning_drain_count_next207'], 2],
    'expected drain count' => [static fn (): mixed => $released207()['expected_current_returning_drain_count_next207'], 2],
    'drain count matches' => [static fn (): mixed => $released207()['current_returning_drain_count_matches_next207'], true],
    'drain count mismatch' => [static fn (): mixed => $countHeld207()['current_returning_drain_count_matches_next207'], false],
    'drain clear released' => [static fn (): mixed => $released207()['current_returning_drain_clear_next207'], true],
    'drain clear missing false' => [static fn (): mixed => $missing207()['current_returning_drain_clear_next207'], false],
    'drain clear reordered false' => [static fn (): mixed => $reordered207()['current_returning_drain_clear_next207'], false],
    'drain clear reordered allowed' => [static fn (): mixed => $reorderedAllowed207()['current_returning_drain_clear_next207'], true],
    'next visible released' => [static fn (): mixed => $released207()['next_source_visible_after_current_drain_next207'], true],
    'next visible missing false' => [static fn (): mixed => $missing207()['next_source_visible_after_current_drain_next207'], false],
    'next visible token false' => [static fn (): mixed => $tokenHeld207()['next_source_visible_after_current_drain_next207'], false],
    'next visible count false' => [static fn (): mixed => $countHeld207()['next_source_visible_after_current_drain_next207'], false],
    'current row count' => [static fn (): mixed => count($released207()['current_returning_drain_rows_next207']), 2],
    'attempted next row count' => [static fn (): mixed => count($released207()['attempted_next_returning_drain_rows_next207']), 2],
    'visible released count' => [static fn (): mixed => count($released207()['visible_returning_rows_next207']), 4],
    'visible held count' => [static fn (): mixed => count($missing207()['visible_returning_rows_next207']), 2],
    'held released count' => [static fn (): mixed => count($released207()['held_next_source_rows_next207']), 0],
    'held missing count' => [static fn (): mixed => count($missing207()['held_next_source_rows_next207']), 2],
    'visible payload names released' => [static fn (): mixed => array_column($released207()['visible_returning_payloads_next207'], 'name'), ['blogdescription_child', 'template_child', 'home', 'next_plugin']],
    'visible payload names held current only' => [static fn (): mixed => array_column($missing207()['visible_returning_payloads_next207'], 'name'), ['blogdescription_child', 'template_child']],
    'held payload names' => [static fn (): mixed => array_column($missing207()['held_next_returning_payloads_next207'], 'name'), ['home', 'next_plugin']],
    'current phase' => [static fn (): mixed => array_values(array_unique(array_column($released207()['current_returning_drain_rows_next207'], 'drain_phase_next207'))), ['current']],
    'next phase' => [static fn (): mixed => array_values(array_unique(array_column($released207()['attempted_next_returning_drain_rows_next207'], 'drain_phase_next207'))), ['next']],
    'current visible held' => [static fn (): mixed => array_values(array_unique(array_column($missing207()['current_returning_drain_rows_next207'], 'visible_after_current_drain_next207'))), [true]],
    'next visible released' => [static fn (): mixed => array_values(array_unique(array_column($released207()['attempted_next_returning_drain_rows_next207'], 'visible_after_current_drain_next207'))), [true]],
    'next visible held' => [static fn (): mixed => array_values(array_unique(array_column($missing207()['attempted_next_returning_drain_rows_next207'], 'visible_after_current_drain_next207'))), [false]],
    'current drain keys tagged' => [static fn (): mixed => array_column($released207()['current_returning_drain_rows_next207'], 'current_returning_drain_key_next207'), $drainKeys207()],
    'next drain key null' => [static fn (): mixed => array_values(array_unique(array_column($released207()['attempted_next_returning_drain_rows_next207'], 'current_returning_drain_key_next207'))), [null]],
    'held reasons missing' => [static fn (): mixed => $missing207()['blocked_reasons_next207'], ['current-returning-drain-missing', 'current-returning-drain-order-mismatch']],
    'held reasons unexpected' => [static fn (): mixed => $unexpected207()['blocked_reasons_next207'], ['current-returning-drain-unexpected', 'current-returning-drain-order-mismatch']],
    'held reasons reordered' => [static fn (): mixed => $reordered207()['blocked_reasons_next207'], ['current-returning-drain-order-mismatch']],
    'held reasons token' => [static fn (): mixed => $tokenHeld207()['blocked_reasons_next207'], ['current-returning-drain-token-mismatch']],
    'held reasons count' => [static fn (): mixed => $countHeld207()['blocked_reasons_next207'], ['current-returning-drain-count-mismatch']],
    'held reasons base' => [static fn (): mixed => $baseHeld207()['blocked_reasons_next207'], ['current-yield-row-count-mismatch']],
    'released reasons empty' => [static fn (): mixed => $released207()['blocked_reasons_next207'], []],
    'held row reason tagged' => [static fn (): mixed => $missing207()['attempted_next_returning_drain_rows_next207'][0]['held_by_current_drain_reasons_next207'], ['current-returning-drain-missing', 'current-returning-drain-order-mismatch']],
    'released next reason empty' => [static fn (): mixed => $released207()['attempted_next_returning_drain_rows_next207'][0]['held_by_current_drain_reasons_next207'], []],
    'plan current rows' => [static fn (): mixed => $released207()['current_drain_plan_next207']['current_rows'], 2],
    'plan next rows' => [static fn (): mixed => $released207()['current_drain_plan_next207']['attempted_next_rows'], 2],
    'plan visible rows' => [static fn (): mixed => $released207()['current_drain_plan_next207']['visible_rows'], 4],
    'plan held rows' => [static fn (): mixed => $missing207()['current_drain_plan_next207']['held_next_rows'], 2],
    'plan token matches' => [static fn (): mixed => $released207()['current_drain_plan_next207']['drain_token_matches'], true],
    'plan count matches' => [static fn (): mixed => $released207()['current_drain_plan_next207']['drain_count_matches'], true],
    'plan order matches' => [static fn (): mixed => $released207()['current_drain_plan_next207']['drain_order_matches'], true],
    'plan drain clear' => [static fn (): mixed => $released207()['current_drain_plan_next207']['drain_clear'], true],
    'plan decision released' => [static fn (): mixed => $released207()['current_drain_plan_next207']['decision'], 'publish-next-source-after-current-returning-drain'],
    'plan decision held' => [static fn (): mixed => $missing207()['current_drain_plan_next207']['decision'], 'hold-next-source-until-current-returning-drain'],
    'yield boundary released' => [static fn (): mixed => $released207()['yield_boundary_next207'], 'recursive-view-returning-next207-current-drain-then-next'],
    'yield boundary held' => [static fn (): mixed => $missing207()['yield_boundary_next207'], 'recursive-view-returning-next207-current-drain-fences-next'],
    'dependency closure marker' => [static fn (): mixed => $released207()['dependency_closure_next207'], 'no new support component needed; reuses next206 recursive view RETURNING watermark rows and adds current RETURNING drain fencing'],
    'dependency includes next207' => [static fn (): mixed => in_array('sqlite-trigger-recursive-view-returning-current-source-next207', $released207()['dependencies_next207'], true), true],
    'dependency includes drain fence' => [static fn (): mixed => in_array('sqlite-returning-current-source-drain-fence', $released207()['dependencies_next207'], true), true],
    'dependency includes next206' => [static fn (): mixed => in_array('sqlite-trigger-recursive-view-returning-current-source-next206', $released207()['dependencies_next207'], true), true],
    'dependency includes application' => [static fn (): mixed => in_array('application-recursive-view-returning-current-source-next207', $released207()['dependencies_next207'], true), true],
    'non overlap mentions next206' => [static fn (): mixed => str_contains($released207()['non_overlap_next207'], 'next206 yield watermark'), true],
    'explicit drain keys accepted' => [static fn (): mixed => $plan207(['acknowledged_current_returning_drain_keys_next207' => $drainKeys207()])['current_returning_drain_clear_next207'], true],
    'explicit count accepted' => [static fn (): mixed => $plan207(['auto_ack_current_returning_drain_next207' => true, 'expected_current_returning_drain_count_next207' => 2])['current_returning_drain_count_matches_next207'], true],
    'bad drain token rejected' => [static fn (): mixed => $plan207(['current_returning_drain_token_next207' => 'bad token']), InvalidArgumentException::class],
    'bad expected token rejected' => [static fn (): mixed => $plan207(['expected_current_returning_drain_token_next207' => 'bad token']), InvalidArgumentException::class],
    'bad cursor rejected' => [static fn (): mixed => $plan207(['current_returning_cursor_next207' => 'bad cursor']), InvalidArgumentException::class],
    'bad statement rejected' => [static fn (): mixed => $plan207(['returning_statement_token_next207' => 'bad statement']), InvalidArgumentException::class],
    'bad key list shape rejected' => [static fn (): mixed => $plan207(['acknowledged_current_returning_drain_keys_next207' => ['x' => 'abcdefabcdefabcdefabcdefabcdefab']]), InvalidArgumentException::class],
    'bad short key rejected' => [static fn (): mixed => $plan207(['acknowledged_current_returning_drain_keys_next207' => ['abc']]), InvalidArgumentException::class],
    'bad non hex key rejected' => [static fn (): mixed => $plan207(['acknowledged_current_returning_drain_keys_next207' => ['zzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzz']]), InvalidArgumentException::class],
    'bad expected count rejected' => [static fn (): mixed => $plan207(['expected_current_returning_drain_count_next207' => -1]), InvalidArgumentException::class],
];

$tests = [];
foreach ($cases207 as $name => [$callback, $expected]) {
    $tests['trigger recursive view returning current source next207 ' . $name] = static function (TestRunner $t) use ($callback, $expected): void {
        if (is_string($expected) && is_a($expected, Throwable::class, true)) {
            $t->throws($expected, $callback);
            return;
        }
        $t->same($expected, $callback());
    };
}

return $tests;
