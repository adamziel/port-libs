<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteTriggerRecursiveViewReturningCurrentSourceNextPlan;

$rows210 = [
    ['option_id' => 1, 'option_name' => 'siteurl', 'option_value' => 'https://old.test', 'autoload' => 'yes'],
    ['option_id' => 2, 'option_name' => 'home', 'option_value' => 'https://home.test', 'autoload' => 'yes'],
];
$currentView210 = [
    'name' => 'wp_recursive_option_import',
    'source' => 'main@view-cookie-210-current',
    'trigger' => 'wp_recursive_option_import_io_insert',
    'trigger_source' => 'main@trigger-cookie-210-current',
    'columns' => ['import_id', 'name', 'value', 'autoload_flag', 'spawn_child'],
    'mapping' => ['import_id' => 'option_id', 'name' => 'option_name', 'value' => 'option_value', 'autoload_flag' => 'autoload', 'spawn_child' => 'spawn_child'],
    'audit_label' => 'current-recursive-view-trigger-210',
];
$nextView210 = $currentView210;
$nextView210['source'] = 'main@view-cookie-210-next';
$nextView210['trigger_source'] = 'main@trigger-cookie-210-next';
$postResetView210 = $currentView210;
$postResetView210['source'] = 'main@view-cookie-210-post-reset';
$postResetView210['trigger_source'] = 'main@trigger-cookie-210-post-reset';
$followingView210 = $currentView210;
$followingView210['source'] = 'main@view-cookie-210-following';
$followingView210['trigger_source'] = 'main@trigger-cookie-210-following';
$currentInput210 = [
    ['import_id' => 10, 'name' => 'siteurl', 'value' => 'https://current.test', 'autoload_flag' => 'yes', 'spawn_child' => true],
    ['import_id' => 11, 'name' => 'current_plugin', 'value' => 'enabled', 'autoload_flag' => 'no', 'spawn_child' => true],
];
$nextInput210 = [
    ['import_id' => 20, 'name' => 'home', 'value' => 'https://next.test', 'autoload_flag' => 'yes', 'spawn_child' => true],
    ['import_id' => 21, 'name' => 'next_plugin', 'value' => 'active', 'autoload_flag' => 'no', 'spawn_child' => false],
];
$returning210 = [
    ['expr' => 'new.option_name', 'as' => 'name'],
    ['expr' => 'new.option_value', 'as' => 'value'],
    ['expr' => 'event', 'as' => 'event_name'],
    ['expr' => 'depth', 'as' => 'depth_value'],
    ['expr' => 'ordinal', 'as' => 'ordinal_value'],
    ['expr' => 'trigger_source', 'as' => 'trigger_source_alias'],
    ['expr' => 'spawn_child', 'as' => 'spawn_child'],
];

$plan210 = static fn (array $options = []): array => SQLiteTriggerRecursiveViewReturningCurrentSourceNextPlan::executeNext210(
    $rows210,
    $currentInput210,
    $nextInput210,
    $currentView210,
    $nextView210,
    $returning210,
    $options + [
        'key' => 'option_name',
        'savepoint' => 'wp_recursive_view_210',
        'cursor_name' => 'wp_recursive_view_returning_cursor_210',
        'admit_next_source' => true,
        'rollback_token' => 'wp.rollback.current.210',
        'reset_generation' => 'wp-current-reset-210',
        'post_reset_current_source_token' => 'wp.current.source.postreset.210',
        'post_reset_cursor' => 'wp.returning.postreset.cursor.210',
        'post_reset_view' => $postResetView210,
        'post_reset_input' => [
            ['import_id' => 30, 'name' => 'siteurl', 'value' => 'https://fresh.test', 'autoload_flag' => 'yes', 'spawn_child' => false],
            ['import_id' => 31, 'name' => 'rewrite_rules', 'value' => 'fresh-rules', 'autoload_flag' => 'no', 'spawn_child' => false],
        ],
        'fresh_acknowledged_ordinals' => [0, 1],
        'next_source_token' => 'wp.next.source.210',
        'next_cursor' => 'wp.returning.next.cursor.210',
        'next_acknowledged_ordinals' => [0, 1],
        'close_next_cursor' => 'wp.returning.next.cursor.210',
        'following_current_source_token' => 'wp.current.source.following.210',
        'following_cursor' => 'wp.returning.following.cursor.210',
        'following_current_view' => $followingView210,
        'following_current_input' => [
            ['import_id' => 40, 'name' => 'blogdescription', 'value' => 'after-next', 'autoload_flag' => 'yes', 'spawn_child' => true],
            ['import_id' => 41, 'name' => 'stylesheet', 'value' => 'twentytwentyfive', 'autoload_flag' => 'yes', 'spawn_child' => false],
            ['import_id' => 42, 'name' => 'template', 'value' => 'twentytwentyfive', 'autoload_flag' => 'yes', 'spawn_child' => true],
        ],
        'following_generation' => 'wp-following-current-210',
        'recursive_child_acknowledged_ordinals' => [0, 1],
        'recursive_child_source_token' => 'wp.current.source.recursive.child.210',
        'recursive_child_cursor' => 'wp.returning.recursive.child.cursor.210',
        'recursive_child_generation' => 'wp-recursive-child-current-210',
        'current_generation_next203' => 'wp.current.recursive.returning.generation.210',
        'expected_current_generation_next203' => 'wp.current.recursive.returning.generation.210',
        'current_handoff_cursor_next203' => 'wp.returning.current.handoff.cursor.210',
        'current_generation_commit_marker_next203' => 'wp.current.recursive.returning.commit.210',
        'auto_ack_current_generation_receipts_next203' => true,
        'current_source_drain_token_next209' => 'wp.current.source.drain.210',
        'current_view_cookie_next209' => 'main@view-cookie-210-current',
        'current_trigger_cookie_next209' => 'main@trigger-cookie-210-current',
        'auto_ack_current_source_watermarks_next209' => true,
        'current_source_sequence_token_next210' => 'wp.current.source.sequence.210',
        'sequence_handoff_cursor_next210' => 'wp.returning.sequence.cursor.210',
    ],
);

$sequence210 = static fn (): array => $plan210()['required_current_source_sequence_next210'];
$signature210 = static fn (): string => $plan210()['current_source_signature_next210'];
$released210 = static fn (): array => $plan210(['auto_ack_current_source_sequence_next210' => true]);
$missing210 = static fn (): array => $plan210(['acknowledged_current_source_sequence_next210' => array_slice($sequence210(), 0, 1)]);
$unexpected210 = static fn (): array => $plan210(['acknowledged_current_source_sequence_next210' => array_merge($sequence210(), ['abcdefabcdefabcdefabcdefabcdefabcd'])]);
$reversed210 = static fn (): array => $plan210(['acknowledged_current_source_sequence_next210' => array_reverse($sequence210())]);
$reversedAllowed210 = static fn (): array => $plan210(['acknowledged_current_source_sequence_next210' => array_reverse($sequence210()), 'require_current_source_sequence_order_next210' => false]);
$cursorHeld210 = static fn (): array => $plan210(['auto_ack_current_source_sequence_next210' => true, 'expected_sequence_handoff_cursor_next210' => 'wp.returning.sequence.cursor.stale.210']);
$sourceHeld210 = static fn (): array => $plan210(['auto_ack_current_source_sequence_next210' => true, 'expected_current_source_signature_next210' => str_repeat('0', 34)]);
$baseHeld210 = static fn (): array => $plan210(['auto_ack_current_source_sequence_next210' => true, 'auto_ack_current_source_watermarks_next209' => false]);
$custom210 = static fn (): array => $plan210([
    'auto_ack_current_source_sequence_next210' => true,
    'current_source_sequence_token_next210' => 'wp.current.source.sequence.custom.210',
    'sequence_handoff_cursor_next210' => 'wp.returning.sequence.cursor.custom.210',
    'expected_sequence_handoff_cursor_next210' => 'wp.returning.sequence.cursor.custom.210',
]);

$cases210 = [
    'released status' => [static fn (): mixed => $released210()['status_next210'], 'trigger-recursive-view-returning-current-source-next210-sequence-released'],
    'missing status' => [static fn (): mixed => $missing210()['status_next210'], 'trigger-recursive-view-returning-current-source-next210-sequence-held'],
    'unexpected status' => [static fn (): mixed => $unexpected210()['status_next210'], 'trigger-recursive-view-returning-current-source-next210-sequence-held'],
    'reversed status' => [static fn (): mixed => $reversed210()['status_next210'], 'trigger-recursive-view-returning-current-source-next210-sequence-held'],
    'reversed allowed status' => [static fn (): mixed => $reversedAllowed210()['status_next210'], 'trigger-recursive-view-returning-current-source-next210-sequence-released'],
    'cursor held status' => [static fn (): mixed => $cursorHeld210()['status_next210'], 'trigger-recursive-view-returning-current-source-next210-cursor-held'],
    'source held status' => [static fn (): mixed => $sourceHeld210()['status_next210'], 'trigger-recursive-view-returning-current-source-next210-source-held'],
    'base held status' => [static fn (): mixed => $baseHeld210()['status_next210'], 'trigger-recursive-view-returning-current-source-next210-base-held'],
    'savepoint retained' => [static fn (): mixed => $released210()['savepoint'], 'wp_recursive_view_210'],
    'base next209 released' => [static fn (): mixed => $released210()['base']['status_next209'], 'trigger-recursive-view-returning-current-source-next209-drain-released'],
    'base next209 held' => [static fn (): mixed => $baseHeld210()['base']['status_next209'], 'trigger-recursive-view-returning-current-source-next209-drain-held'],
    'base visible true' => [static fn (): mixed => $released210()['base_next_source_visible_next210'], true],
    'base visible false' => [static fn (): mixed => $baseHeld210()['base_next_source_visible_next210'], false],
    'sequence token retained' => [static fn (): mixed => $released210()['current_source_sequence_token_next210'], 'wp.current.source.sequence.210'],
    'custom sequence token retained' => [static fn (): mixed => $custom210()['current_source_sequence_token_next210'], 'wp.current.source.sequence.custom.210'],
    'handoff cursor retained' => [static fn (): mixed => $released210()['sequence_handoff_cursor_next210'], 'wp.returning.sequence.cursor.210'],
    'expected cursor retained' => [static fn (): mixed => $released210()['expected_sequence_handoff_cursor_next210'], 'wp.returning.sequence.cursor.210'],
    'custom handoff cursor retained' => [static fn (): mixed => $custom210()['sequence_handoff_cursor_next210'], 'wp.returning.sequence.cursor.custom.210'],
    'cursor matches released' => [static fn (): mixed => $released210()['sequence_handoff_cursor_matches_next210'], true],
    'cursor mismatch detected' => [static fn (): mixed => $cursorHeld210()['sequence_handoff_cursor_matches_next210'], false],
    'source signature hex' => [static fn (): mixed => preg_match('/^[a-f0-9]{34}$/', $released210()['current_source_signature_next210']), 1],
    'expected signature defaults actual' => [static fn (): mixed => $released210()['expected_current_source_signature_next210'], $signature210()],
    'source signature matches released' => [static fn (): mixed => $released210()['current_source_signature_matches_next210'], true],
    'source signature mismatch detected' => [static fn (): mixed => $sourceHeld210()['current_source_signature_matches_next210'], false],
    'required sequence count' => [static fn (): mixed => count($released210()['required_current_source_sequence_next210']), 2],
    'sequence entries are 34 hex' => [static fn (): mixed => array_map(static fn (string $v): int => preg_match('/^[a-f0-9]{34}$/', $v), $released210()['required_current_source_sequence_next210']), [1, 1]],
    'auto ack equals required' => [static fn (): mixed => $released210()['acknowledged_current_source_sequence_next210'], $sequence210()],
    'missing ack count' => [static fn (): mixed => count($missing210()['acknowledged_current_source_sequence_next210']), 1],
    'missing sequence recorded' => [static fn (): mixed => $missing210()['missing_current_source_sequence_next210'], [array_slice($sequence210(), -1)[0]]],
    'unexpected sequence recorded' => [static fn (): mixed => $unexpected210()['unexpected_current_source_sequence_next210'], ['abcdefabcdefabcdefabcdefabcdefabcd']],
    'released missing empty' => [static fn (): mixed => $released210()['missing_current_source_sequence_next210'], []],
    'released unexpected empty' => [static fn (): mixed => $released210()['unexpected_current_source_sequence_next210'], []],
    'require order default true' => [static fn (): mixed => $released210()['require_current_source_sequence_order_next210'], true],
    'order matches released' => [static fn (): mixed => $released210()['current_source_sequence_order_matches_next210'], true],
    'order mismatch reversed' => [static fn (): mixed => $reversed210()['current_source_sequence_order_matches_next210'], false],
    'order ignored when disabled' => [static fn (): mixed => $reversedAllowed210()['current_source_sequence_order_matches_next210'], true],
    'sequence complete released' => [static fn (): mixed => $released210()['current_source_sequence_complete_next210'], true],
    'sequence incomplete missing' => [static fn (): mixed => $missing210()['current_source_sequence_complete_next210'], false],
    'sequence incomplete unexpected' => [static fn (): mixed => $unexpected210()['current_source_sequence_complete_next210'], false],
    'sequence incomplete reversed' => [static fn (): mixed => $reversed210()['current_source_sequence_complete_next210'], false],
    'next visible released' => [static fn (): mixed => $released210()['next_source_visible_after_current_source_sequence_next210'], true],
    'next visible reversed allowed' => [static fn (): mixed => $reversedAllowed210()['next_source_visible_after_current_source_sequence_next210'], true],
    'next held missing' => [static fn (): mixed => $missing210()['next_source_visible_after_current_source_sequence_next210'], false],
    'next held cursor' => [static fn (): mixed => $cursorHeld210()['next_source_visible_after_current_source_sequence_next210'], false],
    'next held source' => [static fn (): mixed => $sourceHeld210()['next_source_visible_after_current_source_sequence_next210'], false],
    'next held base' => [static fn (): mixed => $baseHeld210()['next_source_visible_after_current_source_sequence_next210'], false],
    'current row count' => [static fn (): mixed => $released210()['current_source_row_count_next210'], 2],
    'attempted next row count' => [static fn (): mixed => $released210()['attempted_next_source_row_count_next210'], 2],
    'visible released count' => [static fn (): mixed => $released210()['visible_row_count_next210'], 4],
    'held released count' => [static fn (): mixed => $released210()['held_next_row_count_next210'], 0],
    'visible missing count current only' => [static fn (): mixed => $missing210()['visible_row_count_next210'], 2],
    'held missing count next only' => [static fn (): mixed => $missing210()['held_next_row_count_next210'], 2],
    'current phases' => [static fn (): mixed => array_values(array_unique(array_column($released210()['current_source_rows_next210'], 'source_sequence_phase_next210'))), ['current']],
    'next phases' => [static fn (): mixed => array_values(array_unique(array_column($released210()['attempted_next_source_rows_next210'], 'source_sequence_phase_next210'))), ['next']],
    'current visible while missing' => [static fn (): mixed => array_values(array_unique(array_column($missing210()['current_source_rows_next210'], 'visible_after_current_source_sequence_next210'))), [true]],
    'next visible released unique' => [static fn (): mixed => array_values(array_unique(array_column($released210()['attempted_next_source_rows_next210'], 'visible_after_current_source_sequence_next210'))), [true]],
    'next held missing unique' => [static fn (): mixed => array_values(array_unique(array_column($missing210()['attempted_next_source_rows_next210'], 'visible_after_current_source_sequence_next210'))), [false]],
    'current sequence tagged' => [static fn (): mixed => array_column($released210()['current_source_rows_next210'], 'current_source_sequence_next210'), $sequence210()],
    'next sequence null' => [static fn (): mixed => array_values(array_unique(array_column($released210()['attempted_next_source_rows_next210'], 'current_source_sequence_next210'))), [null]],
    'current sequence token stamped' => [static fn (): mixed => array_values(array_unique(array_column($released210()['current_source_rows_next210'], 'current_source_sequence_token_next210'))), ['wp.current.source.sequence.210']],
    'next cursor stamped' => [static fn (): mixed => array_values(array_unique(array_column($released210()['attempted_next_source_rows_next210'], 'sequence_handoff_cursor_next210'))), ['wp.returning.sequence.cursor.210']],
    'current signature stamped' => [static fn (): mixed => array_values(array_unique(array_column($released210()['current_source_rows_next210'], 'current_source_signature_next210'))), [$signature210()]],
    'next signature stamped' => [static fn (): mixed => array_values(array_unique(array_column($released210()['attempted_next_source_rows_next210'], 'current_source_signature_next210'))), [$signature210()]],
    'visible payload names released' => [static fn (): mixed => array_column($released210()['visible_returning_payloads_next210'], 'name'), ['blogdescription_child', 'template_child', 'home', 'next_plugin']],
    'held payload names missing' => [static fn (): mixed => array_column($missing210()['held_next_returning_payloads_next210'], 'name'), ['home', 'next_plugin']],
    'blocked reasons missing' => [static fn (): mixed => $missing210()['blocked_reasons_next210'], ['current-source-sequence-missing', 'current-source-sequence-order-mismatch']],
    'blocked reasons unexpected' => [static fn (): mixed => $unexpected210()['blocked_reasons_next210'], ['current-source-sequence-unexpected', 'current-source-sequence-order-mismatch']],
    'blocked reasons reversed' => [static fn (): mixed => $reversed210()['blocked_reasons_next210'], ['current-source-sequence-order-mismatch']],
    'blocked reasons cursor' => [static fn (): mixed => $cursorHeld210()['blocked_reasons_next210'], ['current-source-sequence-cursor-mismatch']],
    'blocked reasons source' => [static fn (): mixed => $sourceHeld210()['blocked_reasons_next210'], ['current-source-signature-mismatch']],
    'blocked reasons base' => [static fn (): mixed => $baseHeld210()['blocked_reasons_next210'], ['current-source-watermark-missing']],
    'released reasons empty' => [static fn (): mixed => $released210()['blocked_reasons_next210'], []],
    'held next reason tagged' => [static fn (): mixed => $missing210()['attempted_next_source_rows_next210'][0]['held_by_current_source_sequence_reasons_next210'], ['current-source-sequence-missing', 'current-source-sequence-order-mismatch']],
    'released next reason empty' => [static fn (): mixed => $released210()['attempted_next_source_rows_next210'][0]['held_by_current_source_sequence_reasons_next210'], []],
    'plan decision released' => [static fn (): mixed => $released210()['current_source_sequence_plan_next210']['decision'], 'publish-next-source-after-current-source-sequence'],
    'plan decision held' => [static fn (): mixed => $missing210()['current_source_sequence_plan_next210']['decision'], 'hold-next-source-until-current-source-sequence'],
    'plan base visible' => [static fn (): mixed => $released210()['current_source_sequence_plan_next210']['base_next_source_visible'], true],
    'plan source signature echoed' => [static fn (): mixed => $released210()['current_source_sequence_plan_next210']['source_signature'], $signature210()],
    'plan required echoed' => [static fn (): mixed => $released210()['current_source_sequence_plan_next210']['required_sequence'], $sequence210()],
    'plan acknowledged echoed' => [static fn (): mixed => $missing210()['current_source_sequence_plan_next210']['acknowledged_sequence'], array_slice($sequence210(), 0, 1)],
    'plan sequence complete echoed' => [static fn (): mixed => $released210()['current_source_sequence_plan_next210']['sequence_complete'], true],
    'plan next visible echoed' => [static fn (): mixed => $released210()['current_source_sequence_plan_next210']['next_source_visible'], true],
    'yield boundary released' => [static fn (): mixed => $released210()['yield_boundary_next210'], 'recursive-view-returning-next210-current-source-sequence-then-next'],
    'yield boundary held' => [static fn (): mixed => $missing210()['yield_boundary_next210'], 'recursive-view-returning-next210-current-source-sequence-fences-next'],
    'dependency closure marker' => [static fn (): mixed => $released210()['dependency_closure_next210'], 'no-new-support-component-reuses-native-recursive-view-returning-current-source-drain-and-adds-ordered-source-sequence-fence'],
    'dependency includes next210' => [static fn (): mixed => in_array('sqlite-trigger-recursive-view-returning-current-source-next210', $released210()['dependencies_next210'], true), true],
    'dependency includes sequence fence' => [static fn (): mixed => in_array('sqlite-returning-current-source-ordered-sequence-fence', $released210()['dependencies_next210'], true), true],
    'dependency includes next209' => [static fn (): mixed => in_array('sqlite-trigger-recursive-view-returning-current-source-next209', $released210()['dependencies_next210'], true), true],
    'non overlap mentions next209' => [static fn (): mixed => str_contains($released210()['non_overlap_next210'], 'next209 drain watermarks'), true],
    'bad sequence token rejected' => [static fn (): mixed => $plan210(['current_source_sequence_token_next210' => 'bad token']), InvalidArgumentException::class],
    'bad cursor rejected' => [static fn (): mixed => $plan210(['sequence_handoff_cursor_next210' => 'bad cursor']), InvalidArgumentException::class],
    'bad expected cursor rejected' => [static fn (): mixed => $plan210(['expected_sequence_handoff_cursor_next210' => 'bad cursor']), InvalidArgumentException::class],
    'bad signature rejected' => [static fn (): mixed => $plan210(['expected_current_source_signature_next210' => 'bad signature']), InvalidArgumentException::class],
    'bad sequence list rejected' => [static fn (): mixed => $plan210(['acknowledged_current_source_sequence_next210' => ['x' => 'abcdefabcdefabcdefabcdefabcdefabcd']]), InvalidArgumentException::class],
    'bad short sequence rejected' => [static fn (): mixed => $plan210(['acknowledged_current_source_sequence_next210' => ['abc']]), InvalidArgumentException::class],
    'bad non hex sequence rejected' => [static fn (): mixed => $plan210(['acknowledged_current_source_sequence_next210' => ['zzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzz']]), InvalidArgumentException::class],
];

$tests = [];
foreach ($cases210 as $name => [$callback, $expected]) {
    $tests['trigger recursive view returning current source next210 ' . $name] = static function (TestRunner $t) use ($callback, $expected): void {
        if (is_string($expected) && is_a($expected, Throwable::class, true)) {
            $t->throws($expected, $callback);
            return;
        }
        $t->same($expected, $callback());
    };
}

return $tests;
