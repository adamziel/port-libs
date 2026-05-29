<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteTriggerRecursiveViewReturningCurrentSourceNextPlan;

$rows217 = [
    ['option_id' => 1, 'option_name' => 'siteurl', 'option_value' => 'https://old.test', 'autoload' => 'yes'],
    ['option_id' => 2, 'option_name' => 'home', 'option_value' => 'https://home.test', 'autoload' => 'yes'],
];
$currentView217 = [
    'name' => 'wp_recursive_option_import',
    'source' => 'main@view-cookie-217-current',
    'trigger' => 'wp_recursive_option_import_io_insert',
    'trigger_source' => 'main@trigger-cookie-217-current',
    'columns' => ['import_id', 'name', 'value', 'autoload_flag', 'spawn_child'],
    'mapping' => ['import_id' => 'option_id', 'name' => 'option_name', 'value' => 'option_value', 'autoload_flag' => 'autoload', 'spawn_child' => 'spawn_child'],
    'audit_label' => 'current-recursive-view-trigger-217',
];
$nextView217 = $currentView217;
$nextView217['source'] = 'main@view-cookie-217-next';
$nextView217['trigger_source'] = 'main@trigger-cookie-217-next';
$postResetView217 = $currentView217;
$postResetView217['source'] = 'main@view-cookie-217-post-reset';
$postResetView217['trigger_source'] = 'main@trigger-cookie-217-post-reset';
$followingView217 = $currentView217;
$followingView217['source'] = 'main@view-cookie-217-following';
$followingView217['trigger_source'] = 'main@trigger-cookie-217-following';
$currentInput217 = [
    ['import_id' => 10, 'name' => 'siteurl', 'value' => 'https://current.test', 'autoload_flag' => 'yes', 'spawn_child' => true],
    ['import_id' => 11, 'name' => 'current_plugin', 'value' => 'enabled', 'autoload_flag' => 'no', 'spawn_child' => true],
];
$nextInput217 = [
    ['import_id' => 20, 'name' => 'home', 'value' => 'https://next.test', 'autoload_flag' => 'yes', 'spawn_child' => true],
    ['import_id' => 21, 'name' => 'next_plugin', 'value' => 'active', 'autoload_flag' => 'no', 'spawn_child' => false],
];
$returning217 = [
    ['expr' => 'new.option_name', 'as' => 'name'],
    ['expr' => 'new.option_value', 'as' => 'value'],
    ['expr' => 'event', 'as' => 'event_name'],
    ['expr' => 'depth', 'as' => 'depth_value'],
    ['expr' => 'ordinal', 'as' => 'ordinal_value'],
    ['expr' => 'trigger_source', 'as' => 'trigger_source_alias'],
    ['expr' => 'spawn_child', 'as' => 'spawn_child'],
];

$plan217 = static fn (array $options = []): array => SQLiteTriggerRecursiveViewReturningCurrentSourceNextPlan::executeCurrentSourceProvenanceFence(
    $rows217,
    $currentInput217,
    $nextInput217,
    $currentView217,
    $nextView217,
    $returning217,
    $options + [
        'key' => 'option_name',
        'savepoint' => 'wp_recursive_view_217',
        'cursor_name' => 'wp_recursive_view_returning_cursor_217',
        'admit_next_source' => true,
        'rollback_token' => 'wp.rollback.current.217',
        'reset_generation' => 'wp-current-reset-217',
        'post_reset_current_source_token' => 'wp.current.source.postreset.217',
        'post_reset_cursor' => 'wp.returning.postreset.cursor.217',
        'post_reset_view' => $postResetView217,
        'post_reset_input' => [
            ['import_id' => 30, 'name' => 'siteurl', 'value' => 'https://fresh.test', 'autoload_flag' => 'yes', 'spawn_child' => false],
            ['import_id' => 31, 'name' => 'rewrite_rules', 'value' => 'fresh-rules', 'autoload_flag' => 'no', 'spawn_child' => false],
        ],
        'fresh_acknowledged_ordinals' => [0, 1],
        'next_source_token' => 'wp.next.source.217',
        'next_cursor' => 'wp.returning.next.cursor.217',
        'next_acknowledged_ordinals' => [0, 1],
        'close_next_cursor' => 'wp.returning.next.cursor.217',
        'following_current_source_token' => 'wp.current.source.following.217',
        'following_cursor' => 'wp.returning.following.cursor.217',
        'following_current_view' => $followingView217,
        'following_current_input' => [
            ['import_id' => 40, 'name' => 'blogdescription', 'value' => 'after-next', 'autoload_flag' => 'yes', 'spawn_child' => true],
            ['import_id' => 41, 'name' => 'stylesheet', 'value' => 'twentytwentyfive', 'autoload_flag' => 'yes', 'spawn_child' => false],
            ['import_id' => 42, 'name' => 'template', 'value' => 'twentytwentyfive', 'autoload_flag' => 'yes', 'spawn_child' => true],
        ],
        'following_generation' => 'wp-following-current-217',
        'recursive_child_acknowledged_ordinals' => [0, 1],
        'recursive_child_source_token' => 'wp.current.source.recursive.child.217',
        'recursive_child_cursor' => 'wp.returning.recursive.child.cursor.217',
        'recursive_child_generation' => 'wp-recursive-child-current-217',
        'current_generation_next203' => 'wp.current.recursive.returning.generation.217',
        'expected_current_generation_next203' => 'wp.current.recursive.returning.generation.217',
        'current_handoff_cursor_next203' => 'wp.returning.current.handoff.cursor.217',
        'current_generation_commit_marker_next203' => 'wp.current.recursive.returning.commit.217',
        'auto_ack_current_generation_receipts_next203' => true,
        'current_source_drain_token_next209' => 'wp.current.source.drain.217',
        'current_view_cookie_next209' => 'main@view-cookie-217-current',
        'current_trigger_cookie_next209' => 'main@trigger-cookie-217-current',
        'auto_ack_current_source_watermarks_next209' => true,
        'current_source_yield_token_next212' => 'wp.current.source.yield.217',
        'current_view_yield_cursor_next212' => 'wp.returning.view.yield.cursor.217',
        'current_trigger_yield_cursor_next212' => 'wp.returning.trigger.yield.cursor.217',
        'auto_ack_current_source_yields_next212' => true,
        'current_source_provenance_token_next217' => 'wp.current.source.provenance.217',
    ],
);

$receipts217 = static fn (): array => $plan217()['required_current_source_provenance_next217'];
$released217 = static fn (): array => $plan217(['auto_ack_current_source_provenance_next217' => true]);
$missing217 = static fn (): array => $plan217(['acknowledged_current_source_provenance_next217' => array_slice($receipts217(), 0, 1)]);
$unexpected217 = static fn (): array => $plan217(['acknowledged_current_source_provenance_next217' => array_merge($receipts217(), ['abcdefabcdefabcdefabcdefabcdefabcd'])]);
$reversed217 = static fn (): array => $plan217(['acknowledged_current_source_provenance_next217' => array_reverse($receipts217())]);
$unordered217 = static fn (): array => $plan217(['require_current_source_provenance_order_next217' => false, 'acknowledged_current_source_provenance_next217' => array_reverse($receipts217())]);
$baseHeld217 = static fn (): array => $plan217(['auto_ack_current_source_provenance_next217' => true, 'auto_ack_current_source_yields_next212' => false]);
$tamperedValue217 = static fn (): array => $plan217([
    'auto_ack_current_source_provenance_next217' => true,
    'expected_current_source_provenance_next217' => $receipts217(),
    'tamper_current_returning_payloads_next217' => [0 => ['value' => 'https://tampered.test']],
]);
$tamperedSource217 = static fn (): array => $plan217([
    'auto_ack_current_source_provenance_next217' => true,
    'expected_current_source_provenance_next217' => $receipts217(),
    'tamper_current_returning_payloads_next217' => [1 => ['trigger_source_alias' => 'main@stale-trigger-cookie-217']],
]);
$custom217 = static fn (): array => $plan217([
    'auto_ack_current_source_provenance_next217' => true,
    'current_source_provenance_token_next217' => 'wp.current.source.provenance.custom.217',
]);

$cases217 = [
    'released status' => [static fn (): mixed => $released217()['status_next217'], 'trigger-recursive-view-returning-current-source-next217-provenance-released'],
    'missing status' => [static fn (): mixed => $missing217()['status_next217'], 'trigger-recursive-view-returning-current-source-next217-provenance-held'],
    'unexpected status' => [static fn (): mixed => $unexpected217()['status_next217'], 'trigger-recursive-view-returning-current-source-next217-provenance-held'],
    'reversed status' => [static fn (): mixed => $reversed217()['status_next217'], 'trigger-recursive-view-returning-current-source-next217-provenance-order-held'],
    'unordered reversed releases' => [static fn (): mixed => $unordered217()['status_next217'], 'trigger-recursive-view-returning-current-source-next217-provenance-released'],
    'base held status' => [static fn (): mixed => $baseHeld217()['status_next217'], 'trigger-recursive-view-returning-current-source-next217-base-held'],
    'tampered value status' => [static fn (): mixed => $tamperedValue217()['status_next217'], 'trigger-recursive-view-returning-current-source-next217-provenance-mismatch-held'],
    'tampered source status' => [static fn (): mixed => $tamperedSource217()['status_next217'], 'trigger-recursive-view-returning-current-source-next217-provenance-mismatch-held'],
    'base next212 released' => [static fn (): mixed => $released217()['base']['status_next212'], 'trigger-recursive-view-returning-current-source-next212-yield-released'],
    'base next212 held' => [static fn (): mixed => $baseHeld217()['base']['status_next212'], 'trigger-recursive-view-returning-current-source-next212-yield-held'],
    'base visible released' => [static fn (): mixed => $released217()['base_next_source_visible_next217'], true],
    'base visible held' => [static fn (): mixed => $baseHeld217()['base_next_source_visible_next217'], false],
    'savepoint retained' => [static fn (): mixed => $released217()['savepoint'], 'wp_recursive_view_217'],
    'provenance token retained' => [static fn (): mixed => $released217()['current_source_provenance_token_next217'], 'wp.current.source.provenance.217'],
    'custom token retained' => [static fn (): mixed => $custom217()['current_source_provenance_token_next217'], 'wp.current.source.provenance.custom.217'],
    'view source retained' => [static fn (): mixed => $released217()['current_view_source_next217'], 'main@view-cookie-217-current'],
    'trigger source retained' => [static fn (): mixed => $released217()['current_trigger_source_next217'], 'main@trigger-cookie-217-current'],
    'required receipt count' => [static fn (): mixed => count($released217()['required_current_source_provenance_next217']), 2],
    'receipts are 34 hex' => [static fn (): mixed => array_map(static fn (string $v): int => preg_match('/^[a-f0-9]{34}$/', $v), $released217()['required_current_source_provenance_next217']), [1, 1]],
    'auto acknowledged equals required' => [static fn (): mixed => $released217()['acknowledged_current_source_provenance_next217'], $receipts217()],
    'expected defaults required' => [static fn (): mixed => $released217()['expected_current_source_provenance_next217'], $receipts217()],
    'missing acknowledged count' => [static fn (): mixed => count($missing217()['acknowledged_current_source_provenance_next217']), 1],
    'missing receipt recorded' => [static fn (): mixed => $missing217()['missing_current_source_provenance_next217'], [array_slice($receipts217(), -1)[0]]],
    'unexpected receipt recorded' => [static fn (): mixed => $unexpected217()['unexpected_current_source_provenance_next217'], ['abcdefabcdefabcdefabcdefabcdefabcd']],
    'tampered value expected missing count' => [static fn (): mixed => count($tamperedValue217()['expected_missing_current_source_provenance_next217']), 1],
    'tampered value expected unexpected count' => [static fn (): mixed => count($tamperedValue217()['expected_unexpected_current_source_provenance_next217']), 1],
    'tampered source expected mismatch' => [static fn (): mixed => $tamperedSource217()['current_source_provenance_expected_matches_next217'], false],
    'released expected matches' => [static fn (): mixed => $released217()['current_source_provenance_expected_matches_next217'], true],
    'released missing empty' => [static fn (): mixed => $released217()['missing_current_source_provenance_next217'], []],
    'released unexpected empty' => [static fn (): mixed => $released217()['unexpected_current_source_provenance_next217'], []],
    'require order default' => [static fn (): mixed => $released217()['require_current_source_provenance_order_next217'], true],
    'order matches released' => [static fn (): mixed => $released217()['current_source_provenance_order_matches_next217'], true],
    'order mismatch reversed' => [static fn (): mixed => $reversed217()['current_source_provenance_order_matches_next217'], false],
    'unordered disables order' => [static fn (): mixed => $unordered217()['require_current_source_provenance_order_next217'], false],
    'unordered order considered matched' => [static fn (): mixed => $unordered217()['current_source_provenance_order_matches_next217'], true],
    'provenance complete released' => [static fn (): mixed => $released217()['current_source_provenance_complete_next217'], true],
    'provenance incomplete missing' => [static fn (): mixed => $missing217()['current_source_provenance_complete_next217'], false],
    'provenance incomplete unexpected' => [static fn (): mixed => $unexpected217()['current_source_provenance_complete_next217'], false],
    'provenance incomplete reversed' => [static fn (): mixed => $reversed217()['current_source_provenance_complete_next217'], false],
    'next visible released' => [static fn (): mixed => $released217()['next_source_visible_after_current_source_provenance_next217'], true],
    'next denied missing' => [static fn (): mixed => $missing217()['next_source_visible_after_current_source_provenance_next217'], false],
    'next denied unexpected' => [static fn (): mixed => $unexpected217()['next_source_visible_after_current_source_provenance_next217'], false],
    'next denied reversed' => [static fn (): mixed => $reversed217()['next_source_visible_after_current_source_provenance_next217'], false],
    'next denied tampered value' => [static fn (): mixed => $tamperedValue217()['next_source_visible_after_current_source_provenance_next217'], false],
    'current row count' => [static fn (): mixed => $released217()['current_source_row_count_next217'], 2],
    'attempted next row count' => [static fn (): mixed => $released217()['attempted_next_source_row_count_next217'], 2],
    'visible released count' => [static fn (): mixed => $released217()['visible_row_count_next217'], 4],
    'held released count' => [static fn (): mixed => $released217()['held_next_row_count_next217'], 0],
    'visible missing count current only' => [static fn (): mixed => $missing217()['visible_row_count_next217'], 2],
    'held missing count next only' => [static fn (): mixed => $missing217()['held_next_row_count_next217'], 2],
    'current phases' => [static fn (): mixed => array_values(array_unique(array_column($released217()['current_source_rows_next217'], 'source_provenance_phase_next217'))), ['current']],
    'next phases' => [static fn (): mixed => array_values(array_unique(array_column($released217()['attempted_next_source_rows_next217'], 'source_provenance_phase_next217'))), ['next']],
    'current visible while missing' => [static fn (): mixed => array_values(array_unique(array_column($missing217()['current_source_rows_next217'], 'visible_after_current_source_provenance_next217'))), [true]],
    'next visible released unique' => [static fn (): mixed => array_values(array_unique(array_column($released217()['attempted_next_source_rows_next217'], 'visible_after_current_source_provenance_next217'))), [true]],
    'next held missing unique' => [static fn (): mixed => array_values(array_unique(array_column($missing217()['attempted_next_source_rows_next217'], 'visible_after_current_source_provenance_next217'))), [false]],
    'current receipts tagged' => [static fn (): mixed => array_column($released217()['current_source_rows_next217'], 'current_source_provenance_receipt_next217'), $receipts217()],
    'next receipts null' => [static fn (): mixed => array_values(array_unique(array_column($released217()['attempted_next_source_rows_next217'], 'current_source_provenance_receipt_next217'))), [null]],
    'current provenance token stamped' => [static fn (): mixed => array_values(array_unique(array_column($released217()['current_source_rows_next217'], 'current_source_provenance_token_next217'))), ['wp.current.source.provenance.217']],
    'next provenance token stamped' => [static fn (): mixed => array_values(array_unique(array_column($released217()['attempted_next_source_rows_next217'], 'current_source_provenance_token_next217'))), ['wp.current.source.provenance.217']],
    'current view source stamped' => [static fn (): mixed => array_values(array_unique(array_column($released217()['current_source_rows_next217'], 'current_view_source_next217'))), ['main@view-cookie-217-current']],
    'next trigger source stamped' => [static fn (): mixed => array_values(array_unique(array_column($released217()['attempted_next_source_rows_next217'], 'current_trigger_source_next217'))), ['main@trigger-cookie-217-current']],
    'visible payload names released' => [static fn (): mixed => array_column($released217()['visible_returning_payloads_next217'], 'name'), ['blogdescription_child', 'template_child', 'home', 'next_plugin']],
    'held payload names missing' => [static fn (): mixed => array_column($missing217()['held_next_returning_payloads_next217'], 'name'), ['home', 'next_plugin']],
    'tampered value current name retained' => [static fn (): mixed => $tamperedValue217()['current_source_rows_next217'][0]['returning']['name'], 'blogdescription_child'],
    'tampered value current value changed' => [static fn (): mixed => $tamperedValue217()['current_source_rows_next217'][0]['returning']['value'], 'https://tampered.test'],
    'tampered source alias changed' => [static fn (): mixed => $tamperedSource217()['current_source_rows_next217'][1]['returning']['trigger_source_alias'], 'main@stale-trigger-cookie-217'],
    'blocked reasons missing' => [static fn (): mixed => $missing217()['blocked_reasons_next217'], ['current-source-provenance-missing']],
    'blocked reasons unexpected' => [static fn (): mixed => $unexpected217()['blocked_reasons_next217'], ['current-source-provenance-unexpected']],
    'blocked reasons reversed' => [static fn (): mixed => $reversed217()['blocked_reasons_next217'], ['current-source-provenance-order-mismatch']],
    'blocked reasons tampered' => [static fn (): mixed => $tamperedValue217()['blocked_reasons_next217'], ['current-source-provenance-expected-mismatch']],
    'blocked reasons base held' => [static fn (): mixed => $baseHeld217()['blocked_reasons_next217'], ['current-source-yield-missing']],
    'released reasons empty' => [static fn (): mixed => $released217()['blocked_reasons_next217'], []],
    'held next reason tagged' => [static fn (): mixed => $missing217()['attempted_next_source_rows_next217'][0]['held_by_current_source_provenance_reasons_next217'], ['current-source-provenance-missing']],
    'released next reason empty' => [static fn (): mixed => $released217()['attempted_next_source_rows_next217'][0]['held_by_current_source_provenance_reasons_next217'], []],
    'plan decision released' => [static fn (): mixed => $released217()['current_source_provenance_plan_next217']['decision'], 'publish-next-source-after-current-returning-provenance'],
    'plan decision missing' => [static fn (): mixed => $missing217()['current_source_provenance_plan_next217']['decision'], 'hold-next-source-until-current-returning-provenance'],
    'plan expected matches released' => [static fn (): mixed => $released217()['current_source_provenance_plan_next217']['expected_matches'], true],
    'plan expected matches tampered false' => [static fn (): mixed => $tamperedValue217()['current_source_provenance_plan_next217']['expected_matches'], false],
    'plan required echoed' => [static fn (): mixed => $released217()['current_source_provenance_plan_next217']['required_provenance'], $receipts217()],
    'plan acknowledged echoed' => [static fn (): mixed => $missing217()['current_source_provenance_plan_next217']['acknowledged_provenance'], array_slice($receipts217(), 0, 1)],
    'plan next visible echoed' => [static fn (): mixed => $released217()['current_source_provenance_plan_next217']['next_source_visible'], true],
    'yield boundary released' => [static fn (): mixed => $released217()['yield_boundary_next217'], 'recursive-view-returning-next217-current-source-provenance-then-next'],
    'yield boundary held' => [static fn (): mixed => $missing217()['yield_boundary_next217'], 'recursive-view-returning-next217-current-source-provenance-fences-next'],
    'dependency closure marker' => [static fn (): mixed => $released217()['dependency_closure_next217'], 'no-new-support-component-reuses-native-recursive-view-returning-current-source-yield-and-adds-returning-payload-provenance-fence'],
    'dependency includes next217' => [static fn (): mixed => in_array('sqlite-trigger-recursive-view-returning-current-source-next217', $released217()['dependencies_next217'], true), true],
    'dependency includes provenance fence' => [static fn (): mixed => in_array('sqlite-returning-current-source-provenance-fence', $released217()['dependencies_next217'], true), true],
    'dependency includes next212' => [static fn (): mixed => in_array('sqlite-trigger-recursive-view-returning-current-source-next212', $released217()['dependencies_next217'], true), true],
    'non overlap mentions next212' => [static fn (): mixed => str_contains($released217()['non_overlap_next217'], 'next212 yield receipts'), true],
    'bad provenance token rejected' => [static fn (): mixed => $plan217(['current_source_provenance_token_next217' => 'bad token']), InvalidArgumentException::class],
    'bad view source rejected' => [static fn (): mixed => SQLiteTriggerRecursiveViewReturningCurrentSourceNextPlan::executeCurrentSourceProvenanceFence($rows217, $currentInput217, $nextInput217, array_replace($currentView217, ['source' => 'bad source']), $nextView217, $returning217), InvalidArgumentException::class],
    'bad ack list rejected' => [static fn (): mixed => $plan217(['acknowledged_current_source_provenance_next217' => ['x' => 'abcdefabcdefabcdefabcdefabcdefabcd']]), InvalidArgumentException::class],
    'bad short ack rejected' => [static fn (): mixed => $plan217(['acknowledged_current_source_provenance_next217' => ['abc']]), InvalidArgumentException::class],
    'bad non hex ack rejected' => [static fn (): mixed => $plan217(['acknowledged_current_source_provenance_next217' => ['zzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzz']]), InvalidArgumentException::class],
    'bad expected list rejected' => [static fn (): mixed => $plan217(['expected_current_source_provenance_next217' => ['x' => 'abcdefabcdefabcdefabcdefabcdefabcd']]), InvalidArgumentException::class],
    'bad payload override rejected' => [static fn (): mixed => $plan217(['tamper_current_returning_payloads_next217' => [0 => ['bad' => ['array']]]]), InvalidArgumentException::class],
    'bad payload index rejected' => [static fn (): mixed => $plan217(['tamper_current_returning_payloads_next217' => [99 => ['value' => 'x']]]), InvalidArgumentException::class],
];

$tests = [];
foreach ($cases217 as $name => [$callback, $expected]) {
    $tests['trigger recursive view returning current source next217 ' . $name] = static function (TestRunner $t) use ($callback, $expected): void {
        if (is_string($expected) && is_a($expected, Throwable::class, true)) {
            $t->throws($expected, $callback);
            return;
        }
        $t->same($expected, $callback());
    };
}

return $tests;
