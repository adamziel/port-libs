<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteTriggerRecursiveViewReturningCurrentSourceNextPlan;

$rows228 = [
    ['option_id' => 1, 'option_name' => 'siteurl', 'option_value' => 'https://old.test', 'autoload' => 'yes'],
    ['option_id' => 2, 'option_name' => 'home', 'option_value' => 'https://home.test', 'autoload' => 'yes'],
];
$currentView228 = [
    'name' => 'wp_recursive_option_import',
    'source' => 'main@view-cookie-228-current',
    'trigger' => 'wp_recursive_option_import_io_insert',
    'trigger_source' => 'main@trigger-cookie-228-current',
    'columns' => ['import_id', 'name', 'value', 'autoload_flag', 'spawn_child'],
    'mapping' => ['import_id' => 'option_id', 'name' => 'option_name', 'value' => 'option_value', 'autoload_flag' => 'autoload', 'spawn_child' => 'spawn_child'],
    'audit_label' => 'current-recursive-view-trigger-228',
];
$nextView228 = $currentView228;
$nextView228['source'] = 'main@view-cookie-228-next';
$nextView228['trigger_source'] = 'main@trigger-cookie-228-next';
$postResetView228 = $currentView228;
$postResetView228['source'] = 'main@view-cookie-228-post-reset';
$postResetView228['trigger_source'] = 'main@trigger-cookie-228-post-reset';
$followingView228 = $currentView228;
$followingView228['source'] = 'main@view-cookie-228-following';
$followingView228['trigger_source'] = 'main@trigger-cookie-228-following';
$currentInput228 = [
    ['import_id' => 10, 'name' => 'siteurl', 'value' => 'https://current.test', 'autoload_flag' => 'yes', 'spawn_child' => true],
    ['import_id' => 11, 'name' => 'current_plugin', 'value' => 'enabled', 'autoload_flag' => 'no', 'spawn_child' => true],
];
$nextInput228 = [
    ['import_id' => 20, 'name' => 'home', 'value' => 'https://next.test', 'autoload_flag' => 'yes', 'spawn_child' => true],
    ['import_id' => 21, 'name' => 'next_plugin', 'value' => 'active', 'autoload_flag' => 'no', 'spawn_child' => false],
];
$returning228 = [
    ['expr' => 'new.option_name', 'as' => 'name'],
    ['expr' => 'new.option_value', 'as' => 'value'],
    ['expr' => 'event', 'as' => 'event_name'],
    ['expr' => 'depth', 'as' => 'depth_value'],
    ['expr' => 'ordinal', 'as' => 'ordinal_value'],
    ['expr' => 'trigger_source', 'as' => 'trigger_source_alias'],
    ['expr' => 'spawn_child', 'as' => 'spawn_child'],
];

$plan228 = static fn (array $options = []): array => SQLiteTriggerRecursiveViewReturningCurrentSourceNextPlan::currentReturningSnapshotAcknowledgement(
    $rows228,
    $currentInput228,
    $nextInput228,
    $currentView228,
    $nextView228,
    $returning228,
    $options + [
        'key' => 'option_name',
        'savepoint' => 'wp_recursive_view_228',
        'cursor_name' => 'wp_recursive_view_returning_cursor_228',
        'admit_next_source' => true,
        'rollback_token' => 'wp.rollback.current.228',
        'reset_generation' => 'wp-current-reset-228',
        'post_reset_current_source_token' => 'wp.current.source.postreset.228',
        'post_reset_cursor' => 'wp.returning.postreset.cursor.228',
        'post_reset_view' => $postResetView228,
        'post_reset_input' => [
            ['import_id' => 30, 'name' => 'siteurl', 'value' => 'https://fresh.test', 'autoload_flag' => 'yes', 'spawn_child' => false],
            ['import_id' => 31, 'name' => 'rewrite_rules', 'value' => 'fresh-rules', 'autoload_flag' => 'no', 'spawn_child' => false],
        ],
        'fresh_acknowledged_ordinals' => [0, 1],
        'next_source_token' => 'wp.next.source.228',
        'next_cursor' => 'wp.returning.next.cursor.228',
        'next_acknowledged_ordinals' => [0, 1],
        'close_next_cursor' => 'wp.returning.next.cursor.228',
        'following_current_source_token' => 'wp.current.source.following.228',
        'following_cursor' => 'wp.returning.following.cursor.228',
        'following_current_view' => $followingView228,
        'following_current_input' => [
            ['import_id' => 40, 'name' => 'blogdescription', 'value' => 'after-next', 'autoload_flag' => 'yes', 'spawn_child' => true],
            ['import_id' => 41, 'name' => 'stylesheet', 'value' => 'twentytwentyfive', 'autoload_flag' => 'yes', 'spawn_child' => false],
            ['import_id' => 42, 'name' => 'template', 'value' => 'twentytwentyfive', 'autoload_flag' => 'yes', 'spawn_child' => true],
        ],
        'recursive_child_acknowledged_ordinals' => [0, 1],
        'recursive_child_source_token' => 'wp.current.source.recursive.child.228',
        'recursive_child_cursor' => 'wp.returning.recursive.child.cursor.228',
        'recursive_child_generation' => 'wp-recursive-child-current-228',
        'current_generation_next203' => 'wp.current.recursive.returning.generation.228',
        'expected_current_generation_next203' => 'wp.current.recursive.returning.generation.228',
        'current_handoff_cursor_next203' => 'wp.returning.current.handoff.cursor.228',
        'current_generation_commit_marker_next203' => 'wp.current.recursive.returning.commit.228',
        'auto_ack_current_generation_receipts_next203' => true,
        'current_source_drain_token_next209' => 'wp.current.source.drain.228',
        'current_view_cookie_next209' => 'main@view-cookie-228-current',
        'current_trigger_cookie_next209' => 'main@trigger-cookie-228-current',
        'auto_ack_current_source_watermarks_next209' => true,
        'auto_ack_current_source_yields_next212' => true,
        'current_source_epoch_next218' => 'wp.current.source.epoch.228',
        'current_view_epoch_next218' => 'wp.returning.view.epoch.cursor.228',
        'current_trigger_epoch_next218' => 'wp.returning.trigger.epoch.cursor.228',
        'auto_ack_current_source_epochs_next218' => true,
        'current_returning_snapshot_token_snapshot_ack' => 'wp.current.returning.source.228',
        'current_returning_view_source_snapshot_ack' => 'main@view-cookie-228-current',
        'current_returning_trigger_source_snapshot_ack' => 'main@trigger-cookie-228-current',
        'current_returning_source_token_source_seal' => 'wp.current.returning.source.228',
        'current_returning_view_source_source_seal' => 'main@view-cookie-228-current',
        'current_returning_trigger_source_source_seal' => 'main@trigger-cookie-228-current',
        'auto_ack_current_returning_source_seals_source_seal' => true,
    ],
);

$acks228 = static fn (): array => $plan228()['required_current_returning_snapshot_acks_snapshot_ack'];
$released228 = static fn (): array => $plan228(['auto_ack_current_returning_snapshot_acks_snapshot_ack' => true]);
$missing228 = static fn (): array => $plan228(['acknowledged_current_returning_snapshot_acks_snapshot_ack' => array_slice($acks228(), 0, 1)]);
$unexpected228 = static fn (): array => $plan228(['acknowledged_current_returning_snapshot_acks_snapshot_ack' => array_merge($acks228(), ['abcdefabcdefabcdefabcdefabcdefabcdefabcd'])]);
$sourceHeld228 = static fn (): array => $plan228(['auto_ack_current_returning_snapshot_acks_snapshot_ack' => true, 'expected_current_returning_snapshot_token_snapshot_ack' => 'wp.current.returning.source.stale.228']);
$viewHeld228 = static fn (): array => $plan228(['auto_ack_current_returning_snapshot_acks_snapshot_ack' => true, 'expected_current_returning_view_source_snapshot_ack' => 'main@view-cookie-228-stale']);
$triggerHeld228 = static fn (): array => $plan228(['auto_ack_current_returning_snapshot_acks_snapshot_ack' => true, 'expected_current_returning_trigger_source_snapshot_ack' => 'main@trigger-cookie-228-stale']);
$baseHeld228 = static fn (): array => $plan228(['auto_ack_current_returning_snapshot_acks_snapshot_ack' => true, 'auto_ack_current_source_epochs_next218' => false]);
$custom228 = static fn (): array => $plan228([
    'auto_ack_current_returning_snapshot_acks_snapshot_ack' => true,
    'current_returning_snapshot_token_snapshot_ack' => 'wp.current.returning.source.custom.228',
    'current_returning_view_source_snapshot_ack' => 'main@view-cookie-228-custom',
    'current_returning_trigger_source_snapshot_ack' => 'main@trigger-cookie-228-custom',
]);

$cases228 = [
    'released status' => [static fn (): mixed => $released228()['status_snapshot_ack'], 'trigger-recursive-view-returning-current-source-snapshot-ack-source-released'],
    'missing status' => [static fn (): mixed => $missing228()['status_snapshot_ack'], 'trigger-recursive-view-returning-current-source-snapshot-ack-ack-held'],
    'unexpected status' => [static fn (): mixed => $unexpected228()['status_snapshot_ack'], 'trigger-recursive-view-returning-current-source-snapshot-ack-ack-held'],
    'source held status' => [static fn (): mixed => $sourceHeld228()['status_snapshot_ack'], 'trigger-recursive-view-returning-current-source-snapshot-ack-source-held'],
    'view held status' => [static fn (): mixed => $viewHeld228()['status_snapshot_ack'], 'trigger-recursive-view-returning-current-source-snapshot-ack-view-held'],
    'trigger held status' => [static fn (): mixed => $triggerHeld228()['status_snapshot_ack'], 'trigger-recursive-view-returning-current-source-snapshot-ack-trigger-held'],
    'base held status' => [static fn (): mixed => $baseHeld228()['status_snapshot_ack'], 'trigger-recursive-view-returning-current-source-snapshot-ack-base-held'],
    'savepoint retained' => [static fn (): mixed => $released228()['savepoint'], 'wp_recursive_view_228'],
    'base source_seal released' => [static fn (): mixed => $released228()['base']['status_source_seal'], 'trigger-recursive-view-returning-current-source-source_seal-source-released'],
    'base source_seal held' => [static fn (): mixed => $baseHeld228()['base']['status_source_seal'], 'trigger-recursive-view-returning-current-source-source_seal-base-held'],
    'nested base next218 released' => [static fn (): mixed => $released228()['base']['base']['status_next218'], 'trigger-recursive-view-returning-current-source-next218-epoch-released'],
    'base visible released' => [static fn (): mixed => $released228()['base_next_source_visible_snapshot_ack'], true],
    'base visible held' => [static fn (): mixed => $baseHeld228()['base_next_source_visible_snapshot_ack'], false],
    'snapshot token retained' => [static fn (): mixed => $released228()['current_returning_snapshot_token_snapshot_ack'], 'wp.current.returning.source.228'],
    'custom snapshot token retained' => [static fn (): mixed => $custom228()['current_returning_snapshot_token_snapshot_ack'], 'wp.current.returning.source.custom.228'],
    'expected snapshot token defaults' => [static fn (): mixed => $released228()['expected_current_returning_snapshot_token_snapshot_ack'], 'wp.current.returning.source.228'],
    'view source retained' => [static fn (): mixed => $released228()['current_returning_view_source_snapshot_ack'], 'main@view-cookie-228-current'],
    'custom view source retained' => [static fn (): mixed => $custom228()['current_returning_view_source_snapshot_ack'], 'main@view-cookie-228-custom'],
    'trigger source retained' => [static fn (): mixed => $released228()['current_returning_trigger_source_snapshot_ack'], 'main@trigger-cookie-228-current'],
    'custom trigger source retained' => [static fn (): mixed => $custom228()['current_returning_trigger_source_snapshot_ack'], 'main@trigger-cookie-228-custom'],
    'source matches released' => [static fn (): mixed => $released228()['current_returning_snapshot_matches_snapshot_ack'], true],
    'source mismatch detected' => [static fn (): mixed => $sourceHeld228()['current_returning_snapshot_matches_snapshot_ack'], false],
    'view matches released' => [static fn (): mixed => $released228()['current_returning_view_source_matches_snapshot_ack'], true],
    'view mismatch detected' => [static fn (): mixed => $viewHeld228()['current_returning_view_source_matches_snapshot_ack'], false],
    'trigger matches released' => [static fn (): mixed => $released228()['current_returning_trigger_source_matches_snapshot_ack'], true],
    'trigger mismatch detected' => [static fn (): mixed => $triggerHeld228()['current_returning_trigger_source_matches_snapshot_ack'], false],
    'required ack count' => [static fn (): mixed => count($released228()['required_current_returning_snapshot_acks_snapshot_ack']), 2],
    'snapshot acks are 40 hex' => [static fn (): mixed => array_map(static fn (string $v): int => preg_match('/^[a-f0-9]{40}$/', $v), $released228()['required_current_returning_snapshot_acks_snapshot_ack']), [1, 1]],
    'auto acknowledged equals required' => [static fn (): mixed => $released228()['acknowledged_current_returning_snapshot_acks_snapshot_ack'], $acks228()],
    'missing acknowledged count' => [static fn (): mixed => count($missing228()['acknowledged_current_returning_snapshot_acks_snapshot_ack']), 1],
    'missing ack recorded' => [static fn (): mixed => $missing228()['missing_current_returning_snapshot_acks_snapshot_ack'], [array_slice($acks228(), -1)[0]]],
    'unexpected ack recorded' => [static fn (): mixed => $unexpected228()['unexpected_current_returning_snapshot_acks_snapshot_ack'], ['abcdefabcdefabcdefabcdefabcdefabcdefabcd']],
    'released missing empty' => [static fn (): mixed => $released228()['missing_current_returning_snapshot_acks_snapshot_ack'], []],
    'released unexpected empty' => [static fn (): mixed => $released228()['unexpected_current_returning_snapshot_acks_snapshot_ack'], []],
    'source complete released' => [static fn (): mixed => $released228()['current_returning_snapshot_complete_snapshot_ack'], true],
    'source incomplete missing' => [static fn (): mixed => $missing228()['current_returning_snapshot_complete_snapshot_ack'], false],
    'source incomplete unexpected' => [static fn (): mixed => $unexpected228()['current_returning_snapshot_complete_snapshot_ack'], false],
    'source incomplete source mismatch' => [static fn (): mixed => $sourceHeld228()['current_returning_snapshot_complete_snapshot_ack'], false],
    'source incomplete view mismatch' => [static fn (): mixed => $viewHeld228()['current_returning_snapshot_complete_snapshot_ack'], false],
    'source incomplete trigger mismatch' => [static fn (): mixed => $triggerHeld228()['current_returning_snapshot_complete_snapshot_ack'], false],
    'next visible released' => [static fn (): mixed => $released228()['next_source_visible_after_current_returning_snapshot_snapshot_ack'], true],
    'next denied missing' => [static fn (): mixed => $missing228()['next_source_visible_after_current_returning_snapshot_snapshot_ack'], false],
    'next denied unexpected' => [static fn (): mixed => $unexpected228()['next_source_visible_after_current_returning_snapshot_snapshot_ack'], false],
    'next denied source' => [static fn (): mixed => $sourceHeld228()['next_source_visible_after_current_returning_snapshot_snapshot_ack'], false],
    'next denied view' => [static fn (): mixed => $viewHeld228()['next_source_visible_after_current_returning_snapshot_snapshot_ack'], false],
    'next denied trigger' => [static fn (): mixed => $triggerHeld228()['next_source_visible_after_current_returning_snapshot_snapshot_ack'], false],
    'next denied base' => [static fn (): mixed => $baseHeld228()['next_source_visible_after_current_returning_snapshot_snapshot_ack'], false],
    'current row count' => [static fn (): mixed => $released228()['current_source_row_count_snapshot_ack'], 2],
    'attempted next row count' => [static fn (): mixed => $released228()['attempted_next_source_row_count_snapshot_ack'], 2],
    'visible released count' => [static fn (): mixed => $released228()['visible_row_count_snapshot_ack'], 4],
    'held released count' => [static fn (): mixed => $released228()['held_next_row_count_snapshot_ack'], 0],
    'visible missing count current only' => [static fn (): mixed => $missing228()['visible_row_count_snapshot_ack'], 2],
    'held missing count next only' => [static fn (): mixed => $missing228()['held_next_row_count_snapshot_ack'], 2],
    'current phases' => [static fn (): mixed => array_values(array_unique(array_column($released228()['current_source_rows_snapshot_ack'], 'returning_snapshot_phase_snapshot_ack'))), ['current']],
    'next phases' => [static fn (): mixed => array_values(array_unique(array_column($released228()['attempted_next_source_rows_snapshot_ack'], 'returning_snapshot_phase_snapshot_ack'))), ['next']],
    'current visible while missing' => [static fn (): mixed => array_values(array_unique(array_column($missing228()['current_source_rows_snapshot_ack'], 'visible_after_current_returning_snapshot_snapshot_ack'))), [true]],
    'next visible released' => [static fn (): mixed => array_values(array_unique(array_column($released228()['attempted_next_source_rows_snapshot_ack'], 'visible_after_current_returning_snapshot_snapshot_ack'))), [true]],
    'next held missing' => [static fn (): mixed => array_values(array_unique(array_column($missing228()['attempted_next_source_rows_snapshot_ack'], 'visible_after_current_returning_snapshot_snapshot_ack'))), [false]],
    'current acks tagged' => [static fn (): mixed => array_column($released228()['current_source_rows_snapshot_ack'], 'current_returning_snapshot_ack_snapshot_ack'), $acks228()],
    'next acks null' => [static fn (): mixed => array_values(array_unique(array_column($released228()['attempted_next_source_rows_snapshot_ack'], 'current_returning_snapshot_ack_snapshot_ack'))), [null]],
    'current snapshot token stamped' => [static fn (): mixed => array_values(array_unique(array_column($released228()['current_source_rows_snapshot_ack'], 'current_returning_snapshot_token_snapshot_ack'))), ['wp.current.returning.source.228']],
    'next snapshot token stamped' => [static fn (): mixed => array_values(array_unique(array_column($released228()['attempted_next_source_rows_snapshot_ack'], 'current_returning_snapshot_token_snapshot_ack'))), ['wp.current.returning.source.228']],
    'current view source stamped' => [static fn (): mixed => array_values(array_unique(array_column($released228()['current_source_rows_snapshot_ack'], 'current_returning_view_source_snapshot_ack'))), ['main@view-cookie-228-current']],
    'next trigger source stamped' => [static fn (): mixed => array_values(array_unique(array_column($released228()['attempted_next_source_rows_snapshot_ack'], 'current_returning_trigger_source_snapshot_ack'))), ['main@trigger-cookie-228-current']],
    'visible payload names released' => [static fn (): mixed => array_column($released228()['visible_returning_payloads_snapshot_ack'], 'name'), ['blogdescription_child', 'template_child', 'home', 'next_plugin']],
    'held payload names missing' => [static fn (): mixed => array_column($missing228()['held_next_returning_payloads_snapshot_ack'], 'name'), ['home', 'next_plugin']],
    'blocked reasons missing' => [static fn (): mixed => $missing228()['blocked_reasons_snapshot_ack'], ['current-returning-source-ack-missing']],
    'blocked reasons unexpected' => [static fn (): mixed => $unexpected228()['blocked_reasons_snapshot_ack'], ['current-returning-source-ack-unexpected']],
    'blocked reasons source' => [static fn (): mixed => $sourceHeld228()['blocked_reasons_snapshot_ack'], ['current-returning-source-token-mismatch']],
    'blocked reasons view' => [static fn (): mixed => $viewHeld228()['blocked_reasons_snapshot_ack'], ['current-returning-view-source-mismatch']],
    'blocked reasons trigger' => [static fn (): mixed => $triggerHeld228()['blocked_reasons_snapshot_ack'], ['current-returning-trigger-source-mismatch']],
    'blocked reasons base held' => [static fn (): mixed => $baseHeld228()['blocked_reasons_snapshot_ack'], ['base-next218-current-source-epoch-not-published']],
    'released reasons empty' => [static fn (): mixed => $released228()['blocked_reasons_snapshot_ack'], []],
    'held next reason tagged' => [static fn (): mixed => $missing228()['attempted_next_source_rows_snapshot_ack'][0]['held_by_current_returning_snapshot_reasons_snapshot_ack'], ['current-returning-source-ack-missing']],
    'released next reason empty' => [static fn (): mixed => $released228()['attempted_next_source_rows_snapshot_ack'][0]['held_by_current_returning_snapshot_reasons_snapshot_ack'], []],
    'plan decision released' => [static fn (): mixed => $released228()['current_returning_snapshot_plan_snapshot_ack']['decision'], 'publish-next-source-after-current-returning-source-ack'],
    'plan decision missing' => [static fn (): mixed => $missing228()['current_returning_snapshot_plan_snapshot_ack']['decision'], 'hold-next-source-until-current-returning-source-ack'],
    'plan base visible' => [static fn (): mixed => $released228()['current_returning_snapshot_plan_snapshot_ack']['base_next_source_visible'], true],
    'plan base held' => [static fn (): mixed => $baseHeld228()['current_returning_snapshot_plan_snapshot_ack']['base_next_source_visible'], false],
    'plan required echoed' => [static fn (): mixed => $released228()['current_returning_snapshot_plan_snapshot_ack']['required_acks'], $acks228()],
    'plan acknowledged echoed' => [static fn (): mixed => $missing228()['current_returning_snapshot_plan_snapshot_ack']['acknowledged_acks'], array_slice($acks228(), 0, 1)],
    'plan next visible echoed' => [static fn (): mixed => $released228()['current_returning_snapshot_plan_snapshot_ack']['next_source_visible'], true],
    'yield boundary released' => [static fn (): mixed => $released228()['yield_boundary_snapshot_ack'], 'recursive-view-returning-snapshot-ack-current-source-acked-then-next'],
    'yield boundary held' => [static fn (): mixed => $missing228()['yield_boundary_snapshot_ack'], 'recursive-view-returning-snapshot-ack-current-source-ack-fences-next'],
    'dependency closure marker' => [static fn (): mixed => $released228()['dependency_closure_snapshot_ack'], 'no-new-support-component-reuses-native-recursive-view-returning-current-source-epoch-and-adds-source-ack'],
    'dependency includes snapshot-ack' => [static fn (): mixed => in_array('sqlite-trigger-recursive-view-returning-current-source-snapshot-ack', $released228()['dependencies_snapshot_ack'], true), true],
    'dependency includes snapshot ack' => [static fn (): mixed => in_array('sqlite-returning-current-source-snapshot-ack', $released228()['dependencies_snapshot_ack'], true), true],
    'dependency includes next218' => [static fn (): mixed => in_array('sqlite-trigger-recursive-view-returning-current-source-next218', $released228()['dependencies_snapshot_ack'], true), true],
    'non overlap mentions source_seal' => [static fn (): mixed => str_contains($released228()['non_overlap_snapshot_ack'], 'source_seal source seals'), true],
    'bad snapshot token rejected' => [static fn (): mixed => $plan228(['current_returning_snapshot_token_snapshot_ack' => 'bad token']), InvalidArgumentException::class],
    'bad expected snapshot token rejected' => [static fn (): mixed => $plan228(['expected_current_returning_snapshot_token_snapshot_ack' => 'bad token']), InvalidArgumentException::class],
    'bad view source rejected' => [static fn (): mixed => $plan228(['current_returning_view_source_snapshot_ack' => 'bad source']), InvalidArgumentException::class],
    'bad trigger source rejected' => [static fn (): mixed => $plan228(['current_returning_trigger_source_snapshot_ack' => 'bad source']), InvalidArgumentException::class],
    'bad ack list rejected' => [static fn (): mixed => $plan228(['acknowledged_current_returning_snapshot_acks_snapshot_ack' => ['x' => 'abcdefabcdefabcdefabcdefabcdefabcdefabcd']]), InvalidArgumentException::class],
    'bad short ack rejected' => [static fn (): mixed => $plan228(['acknowledged_current_returning_snapshot_acks_snapshot_ack' => ['abc']]), InvalidArgumentException::class],
    'bad non hex ack rejected' => [static fn (): mixed => $plan228(['acknowledged_current_returning_snapshot_acks_snapshot_ack' => ['zzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzz']]), InvalidArgumentException::class],
];

$tests = [];
foreach ($cases228 as $name => [$callback, $expected]) {
    $tests['trigger recursive view returning current source snapshot-ack ' . $name] = static function (TestRunner $t) use ($callback, $expected): void {
        if (is_string($expected) && is_a($expected, Throwable::class, true)) {
            $t->throws($expected, $callback);
            return;
        }
        $t->same($expected, $callback());
    };
}

return $tests;
