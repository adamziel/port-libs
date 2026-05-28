<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteTriggerRecursiveViewUpsertCurrentSourceNext247Plan;

$rows247 = [
    ['option_id' => 1, 'option_name' => 'siteurl', 'option_value' => 'https://old.test', 'autoload' => 'yes'],
    ['option_id' => 2, 'option_name' => 'home', 'option_value' => 'https://home.test', 'autoload' => 'yes'],
];
$currentView247 = [
    'name' => 'wp_recursive_option_import',
    'source' => 'main@view-cookie-247-current',
    'trigger' => 'wp_recursive_option_import_io_insert',
    'trigger_source' => 'main@trigger-cookie-247-current',
    'columns' => ['import_id', 'name', 'value', 'autoload_flag', 'spawn_child'],
    'mapping' => ['import_id' => 'option_id', 'name' => 'option_name', 'value' => 'option_value', 'autoload_flag' => 'autoload', 'spawn_child' => 'spawn_child'],
    'audit_label' => 'current-recursive-view-upsert-sequence-247',
];
$nextView247 = $currentView247;
$nextView247['source'] = 'main@view-cookie-247-next';
$nextView247['trigger_source'] = 'main@trigger-cookie-247-next';
$postResetView247 = $currentView247;
$postResetView247['source'] = 'main@view-cookie-247-post-reset';
$postResetView247['trigger_source'] = 'main@trigger-cookie-247-post-reset';
$followingView247 = $currentView247;
$followingView247['source'] = 'main@view-cookie-247-following';
$followingView247['trigger_source'] = 'main@trigger-cookie-247-following';
$currentInput247 = [
    ['import_id' => 10, 'name' => 'siteurl', 'value' => 'https://current.test', 'autoload_flag' => 'yes', 'spawn_child' => true],
    ['import_id' => 11, 'name' => 'current_plugin', 'value' => 'enabled', 'autoload_flag' => 'no', 'spawn_child' => true],
];
$nextInput247 = [
    ['import_id' => 20, 'name' => 'home', 'value' => 'https://next.test', 'autoload_flag' => 'yes', 'spawn_child' => true],
    ['import_id' => 21, 'name' => 'next_plugin', 'value' => 'active', 'autoload_flag' => 'no', 'spawn_child' => false],
];
$returning247 = [
    ['expr' => 'new.option_name', 'as' => 'name'],
    ['expr' => 'new.option_value', 'as' => 'value'],
    ['expr' => 'event', 'as' => 'event_name'],
    ['expr' => 'depth', 'as' => 'depth_value'],
    ['expr' => 'ordinal', 'as' => 'ordinal_value'],
    ['expr' => 'trigger_source', 'as' => 'trigger_source_alias'],
    ['expr' => 'spawn_child', 'as' => 'spawn_child'],
];

$baseOptions247 = [
    'key' => 'option_name',
    'savepoint' => 'wp_recursive_view_247',
    'cursor_name' => 'wp_recursive_view_returning_cursor_247',
    'admit_next_source' => true,
    'rollback_token' => 'wp.rollback.current.247',
    'reset_generation' => 'wp-current-reset-247',
    'post_reset_current_source_token' => 'wp.current.source.postreset.247',
    'post_reset_cursor' => 'wp.returning.postreset.cursor.247',
    'post_reset_view' => $postResetView247,
    'post_reset_input' => [
        ['import_id' => 30, 'name' => 'siteurl', 'value' => 'https://fresh.test', 'autoload_flag' => 'yes', 'spawn_child' => false],
        ['import_id' => 31, 'name' => 'rewrite_rules', 'value' => 'fresh-rules', 'autoload_flag' => 'no', 'spawn_child' => false],
    ],
    'fresh_acknowledged_ordinals' => [0, 1],
    'next_source_token' => 'wp.next.source.247',
    'next_cursor' => 'wp.returning.next.cursor.247',
    'next_acknowledged_ordinals' => [0, 1],
    'close_next_cursor' => 'wp.returning.next.cursor.247',
    'following_current_source_token' => 'wp.current.source.following.247',
    'following_cursor' => 'wp.returning.following.cursor.247',
    'following_current_view' => $followingView247,
    'following_current_input' => [
        ['import_id' => 40, 'name' => 'blogdescription', 'value' => 'after-next', 'autoload_flag' => 'yes', 'spawn_child' => true],
        ['import_id' => 41, 'name' => 'stylesheet', 'value' => 'twentytwentyfive', 'autoload_flag' => 'yes', 'spawn_child' => false],
        ['import_id' => 42, 'name' => 'template', 'value' => 'twentytwentyfive', 'autoload_flag' => 'yes', 'spawn_child' => true],
    ],
    'following_generation' => 'wp-following-current-247',
    'recursive_child_acknowledged_ordinals' => [0, 1],
    'recursive_child_source_token' => 'wp.current.source.recursive.child.247',
    'recursive_child_cursor' => 'wp.returning.recursive.child.cursor.247',
    'recursive_child_generation' => 'wp-recursive-child-current-247',
    'current_generation_next203' => 'wp.current.recursive.returning.generation.247',
    'expected_current_generation_next203' => 'wp.current.recursive.returning.generation.247',
    'current_handoff_cursor_next203' => 'wp.returning.current.handoff.cursor.247',
    'current_generation_commit_marker_next203' => 'wp.current.recursive.returning.commit.247',
    'auto_ack_current_generation_receipts_next203' => true,
    'current_source_drain_token_next209' => 'wp.current.source.drain.247',
    'current_view_cookie_next209' => 'main@view-cookie-247-current',
    'current_trigger_cookie_next209' => 'main@trigger-cookie-247-current',
    'auto_ack_current_source_watermarks_next209' => true,
    'current_source_yield_token_next212' => 'wp.current.source.yield.247',
    'current_view_yield_cursor_next212' => 'wp.returning.view.yield.cursor.247',
    'current_trigger_yield_cursor_next212' => 'wp.returning.trigger.yield.cursor.247',
    'auto_ack_current_source_yields_next212' => true,
    'current_source_epoch_next218' => 'wp.current.source.epoch.247',
    'current_view_epoch_next218' => 'wp.returning.view.epoch.cursor.247',
    'current_trigger_epoch_next218' => 'wp.returning.trigger.epoch.cursor.247',
    'auto_ack_current_source_epochs_next218' => true,
    'current_source_ticket_next222' => 'wp.current.source.ticket.247',
    'current_view_source_next222' => 'main@view-cookie-247-current',
    'current_trigger_source_next222' => 'main@trigger-cookie-247-current',
    'auto_ack_current_source_tickets_next222' => true,
    'current_source_cursor_next231' => 'wp.returning.current.cursor.247',
    'current_source_close_token_next231' => 'wp.current.source.close.247',
    'current_view_cookie_next231' => 'main@view-cookie-247-current',
    'current_trigger_cookie_next231' => 'main@trigger-cookie-247-current',
    'auto_ack_current_source_closures_next231' => true,
    'current_source_upsert_token_next234' => 'wp.current.source.upsert.247',
    'current_upsert_view_cookie_next234' => 'main@view-cookie-247-current',
    'current_upsert_trigger_cookie_next234' => 'main@trigger-cookie-247-current',
    'auto_ack_current_source_upserts_next234' => true,
    'current_source_upsert_action_token_next237' => 'wp.current.source.upsert.action.247',
    'current_upsert_action_view_cookie_next237' => 'main@view-cookie-247-current',
    'current_upsert_action_trigger_cookie_next237' => 'main@trigger-cookie-247-current',
    'auto_ack_current_source_upsert_actions_next237' => true,
    'current_source_upsert_close_token_next241' => 'wp.current.source.upsert.close.247',
    'current_source_upsert_generation_next241' => 'main@source-generation-247-current',
    'current_upsert_close_view_cookie_next241' => 'main@view-cookie-247-current',
    'current_upsert_close_trigger_cookie_next241' => 'main@trigger-cookie-247-current',
    'auto_ack_current_source_upsert_closes_next241' => true,
    'current_source_upsert_statement_id_next244' => 'wp.current.source.upsert.statement.247',
    'current_source_upsert_commit_watermark_next244' => 'wp.current.source.upsert.commit.247',
    'current_upsert_commit_view_cookie_next244' => 'main@view-cookie-247-current',
    'current_upsert_commit_trigger_cookie_next244' => 'main@trigger-cookie-247-current',
    'auto_ack_current_source_upsert_commits_next244' => true,
    'current_source_statement_sequence_next247' => 247,
    'next_source_statement_sequence_next247' => 248,
    'current_source_sequence_view_cookie_next247' => 'main@view-cookie-247-current',
    'current_source_sequence_trigger_cookie_next247' => 'main@trigger-cookie-247-current',
    'current_source_sequence_cursor_next247' => 'wp.returning.current.sequence.cursor.247',
];

$plan247 = static fn (array $options = []): array => SQLiteTriggerRecursiveViewUpsertCurrentSourceNext247Plan::execute(
    $rows247,
    $currentInput247,
    $nextInput247,
    $currentView247,
    $nextView247,
    $returning247,
    $options + $baseOptions247,
);

$receipts247 = static fn (): array => $plan247()['required_current_source_sequence_receipts_next247'];
$released247 = static fn (): array => $plan247(['auto_ack_current_source_statement_sequences_next247' => true]);
$missing247 = static fn (): array => $plan247(['acknowledged_current_source_sequence_receipts_next247' => array_slice($receipts247(), 0, 1)]);
$unexpectedReceipt247 = 'abcdefabcdefabcdefabcdefabcdefabcdefabcdefabcdef';
$unexpected247 = static fn (): array => $plan247(['acknowledged_current_source_sequence_receipts_next247' => array_merge($receipts247(), [$unexpectedReceipt247])]);
$sequenceHeld247 = static fn (): array => $plan247(['auto_ack_current_source_statement_sequences_next247' => true, 'expected_current_source_statement_sequence_next247' => 246]);
$nextSequenceHeld247 = static fn (): array => $plan247(['auto_ack_current_source_statement_sequences_next247' => true, 'next_source_statement_sequence_next247' => 247]);
$nonMonotonicAllowed247 = static fn (): array => $plan247(['auto_ack_current_source_statement_sequences_next247' => true, 'next_source_statement_sequence_next247' => 247, 'require_monotonic_statement_sequence_next247' => false]);
$baseHeld247 = static fn (): array => $plan247(['auto_ack_current_source_statement_sequences_next247' => true, 'auto_ack_current_source_upsert_commits_next244' => false]);
$custom247 = static fn (): array => $plan247([
    'auto_ack_current_source_statement_sequences_next247' => true,
    'current_source_statement_sequence_next247' => 900,
    'next_source_statement_sequence_next247' => 901,
    'current_source_sequence_view_cookie_next247' => 'main@view-cookie-247-custom',
    'current_source_sequence_trigger_cookie_next247' => 'main@trigger-cookie-247-custom',
    'current_source_sequence_cursor_next247' => 'wp.returning.current.sequence.cursor.custom.247',
]);

$cases247 = [
    'released status' => [static fn (): mixed => $released247()['status_next247'], 'trigger-recursive-view-upsert-current-source-next247-sequence-released'],
    'missing status' => [static fn (): mixed => $missing247()['status_next247'], 'trigger-recursive-view-upsert-current-source-next247-sequence-missing-held'],
    'unexpected status' => [static fn (): mixed => $unexpected247()['status_next247'], 'trigger-recursive-view-upsert-current-source-next247-sequence-unexpected-held'],
    'sequence held status' => [static fn (): mixed => $sequenceHeld247()['status_next247'], 'trigger-recursive-view-upsert-current-source-next247-sequence-held'],
    'next sequence held status' => [static fn (): mixed => $nextSequenceHeld247()['status_next247'], 'trigger-recursive-view-upsert-current-source-next247-next-sequence-held'],
    'non monotonic allowed status' => [static fn (): mixed => $nonMonotonicAllowed247()['status_next247'], 'trigger-recursive-view-upsert-current-source-next247-sequence-released'],
    'base held status' => [static fn (): mixed => $baseHeld247()['status_next247'], 'trigger-recursive-view-upsert-current-source-next247-base-held'],
    'savepoint retained' => [static fn (): mixed => $released247()['savepoint'], 'wp_recursive_view_247'],
    'base next244 released' => [static fn (): mixed => $released247()['base']['status_next244'], 'trigger-recursive-view-upsert-current-source-next244-commit-released'],
    'base next244 held' => [static fn (): mixed => $baseHeld247()['base']['status_next244'], 'trigger-recursive-view-upsert-current-source-next244-commit-held'],
    'base visible released' => [static fn (): mixed => $released247()['base_next_source_visible_next247'], true],
    'base visible held' => [static fn (): mixed => $baseHeld247()['base_next_source_visible_next247'], false],
    'sequence retained' => [static fn (): mixed => $released247()['current_source_statement_sequence_next247'], 247],
    'custom sequence retained' => [static fn (): mixed => $custom247()['current_source_statement_sequence_next247'], 900],
    'expected sequence retained' => [static fn (): mixed => $released247()['expected_current_source_statement_sequence_next247'], 247],
    'next sequence retained' => [static fn (): mixed => $released247()['next_source_statement_sequence_next247'], 248],
    'custom next sequence retained' => [static fn (): mixed => $custom247()['next_source_statement_sequence_next247'], 901],
    'sequence matches released' => [static fn (): mixed => $released247()['current_source_statement_sequence_matches_next247'], true],
    'sequence mismatch detected' => [static fn (): mixed => $sequenceHeld247()['current_source_statement_sequence_matches_next247'], false],
    'monotonic required default' => [static fn (): mixed => $released247()['require_monotonic_statement_sequence_next247'], true],
    'next sequence future released' => [static fn (): mixed => $released247()['next_source_statement_sequence_is_future_next247'], true],
    'next sequence not future detected' => [static fn (): mixed => $nextSequenceHeld247()['next_source_statement_sequence_is_future_next247'], false],
    'non monotonic disabled' => [static fn (): mixed => $nonMonotonicAllowed247()['require_monotonic_statement_sequence_next247'], false],
    'view cookie retained' => [static fn (): mixed => $released247()['current_source_sequence_view_cookie_next247'], 'main@view-cookie-247-current'],
    'custom view cookie retained' => [static fn (): mixed => $custom247()['current_source_sequence_view_cookie_next247'], 'main@view-cookie-247-custom'],
    'trigger cookie retained' => [static fn (): mixed => $released247()['current_source_sequence_trigger_cookie_next247'], 'main@trigger-cookie-247-current'],
    'custom trigger cookie retained' => [static fn (): mixed => $custom247()['current_source_sequence_trigger_cookie_next247'], 'main@trigger-cookie-247-custom'],
    'cursor retained' => [static fn (): mixed => $released247()['current_source_sequence_cursor_next247'], 'wp.returning.current.sequence.cursor.247'],
    'custom cursor retained' => [static fn (): mixed => $custom247()['current_source_sequence_cursor_next247'], 'wp.returning.current.sequence.cursor.custom.247'],
    'required receipt count' => [static fn (): mixed => count($released247()['required_current_source_sequence_receipts_next247']), 2],
    'receipts are forty eight hex' => [static fn (): mixed => array_map(static fn (string $v): int => preg_match('/^[a-f0-9]{48}$/', $v), $released247()['required_current_source_sequence_receipts_next247']), [1, 1]],
    'auto acknowledged equals required' => [static fn (): mixed => $released247()['acknowledged_current_source_sequence_receipts_next247'], $receipts247()],
    'missing acknowledged count' => [static fn (): mixed => count($missing247()['acknowledged_current_source_sequence_receipts_next247']), 1],
    'missing receipt recorded' => [static fn (): mixed => $missing247()['missing_current_source_sequence_receipts_next247'], [array_slice($receipts247(), -1)[0]]],
    'unexpected receipt recorded' => [static fn (): mixed => $unexpected247()['unexpected_current_source_sequence_receipts_next247'], [$unexpectedReceipt247]],
    'released missing empty' => [static fn (): mixed => $released247()['missing_current_source_sequence_receipts_next247'], []],
    'released unexpected empty' => [static fn (): mixed => $released247()['unexpected_current_source_sequence_receipts_next247'], []],
    'sequence complete released' => [static fn (): mixed => $released247()['current_source_statement_sequence_complete_next247'], true],
    'sequence complete missing false' => [static fn (): mixed => $missing247()['current_source_statement_sequence_complete_next247'], false],
    'sequence complete unexpected false' => [static fn (): mixed => $unexpected247()['current_source_statement_sequence_complete_next247'], false],
    'sequence complete mismatch false' => [static fn (): mixed => $sequenceHeld247()['current_source_statement_sequence_complete_next247'], false],
    'sequence complete non future false' => [static fn (): mixed => $nextSequenceHeld247()['current_source_statement_sequence_complete_next247'], false],
    'next visible released' => [static fn (): mixed => $released247()['next_source_visible_after_current_source_statement_sequence_next247'], true],
    'next denied missing' => [static fn (): mixed => $missing247()['next_source_visible_after_current_source_statement_sequence_next247'], false],
    'next denied sequence mismatch' => [static fn (): mixed => $sequenceHeld247()['next_source_visible_after_current_source_statement_sequence_next247'], false],
    'current row count' => [static fn (): mixed => $released247()['current_source_row_count_next247'], 2],
    'attempted next row count' => [static fn (): mixed => $released247()['attempted_next_source_row_count_next247'], 2],
    'visible released count' => [static fn (): mixed => $released247()['visible_row_count_next247'], 4],
    'held released count' => [static fn (): mixed => $released247()['held_next_row_count_next247'], 0],
    'visible missing count current only' => [static fn (): mixed => $missing247()['visible_row_count_next247'], 2],
    'held missing count next only' => [static fn (): mixed => $missing247()['held_next_row_count_next247'], 2],
    'current phases' => [static fn (): mixed => array_values(array_unique(array_column($released247()['current_source_rows_next247'], 'statement_sequence_phase_next247'))), ['current-sequence']],
    'next phases' => [static fn (): mixed => array_values(array_unique(array_column($released247()['attempted_next_source_rows_next247'], 'statement_sequence_phase_next247'))), ['next-source']],
    'current sequence receipts tagged' => [static fn (): mixed => array_column($released247()['current_source_rows_next247'], 'current_source_sequence_receipt_next247'), $receipts247()],
    'next sequence receipts null' => [static fn (): mixed => array_values(array_unique(array_column($released247()['attempted_next_source_rows_next247'], 'current_source_sequence_receipt_next247'))), [null]],
    'current visible while missing' => [static fn (): mixed => array_values(array_unique(array_column($missing247()['current_source_rows_next247'], 'visible_after_current_source_statement_sequence_next247'))), [true]],
    'next held while missing' => [static fn (): mixed => array_values(array_unique(array_column($missing247()['attempted_next_source_rows_next247'], 'visible_after_current_source_statement_sequence_next247'))), [false]],
    'visible payload names released' => [static fn (): mixed => array_column($released247()['visible_returning_payloads_next247'], 'name'), ['blogdescription_child', 'template_child', 'home', 'next_plugin']],
    'held payload names missing' => [static fn (): mixed => array_column($missing247()['held_next_returning_payloads_next247'], 'name'), ['home', 'next_plugin']],
    'blocked reasons missing' => [static fn (): mixed => $missing247()['blocked_reasons_next247'], ['current-source-statement-sequence-missing']],
    'blocked reasons unexpected' => [static fn (): mixed => $unexpected247()['blocked_reasons_next247'], ['current-source-statement-sequence-unexpected']],
    'blocked reasons sequence mismatch' => [static fn (): mixed => $sequenceHeld247()['blocked_reasons_next247'], ['current-source-statement-sequence-mismatch']],
    'blocked reasons next sequence' => [static fn (): mixed => $nextSequenceHeld247()['blocked_reasons_next247'], ['next-source-statement-sequence-not-future']],
    'blocked reasons base held' => [static fn (): mixed => $baseHeld247()['blocked_reasons_next247'], ['current-source-upsert-commit-missing']],
    'released reasons empty' => [static fn (): mixed => $released247()['blocked_reasons_next247'], []],
    'plan decision released' => [static fn (): mixed => $released247()['current_source_statement_sequence_plan_next247']['decision'], 'publish-next-source-after-current-recursive-view-upsert-sequence'],
    'plan decision missing' => [static fn (): mixed => $missing247()['current_source_statement_sequence_plan_next247']['decision'], 'hold-next-source-until-current-recursive-view-upsert-sequence'],
    'plan required echoed' => [static fn (): mixed => $released247()['current_source_statement_sequence_plan_next247']['required_receipts'], $receipts247()],
    'yield boundary released' => [static fn (): mixed => $released247()['yield_boundary_next247'], 'recursive-view-upsert-next247-current-sequence-then-next'],
    'yield boundary held' => [static fn (): mixed => $missing247()['yield_boundary_next247'], 'recursive-view-upsert-next247-current-sequence-fence-next'],
    'dependency closure marker' => [static fn (): mixed => $released247()['dependency_closure_next247'], 'no-new-support-component-reuses-native-recursive-view-upsert-commit-watermark-and-adds-statement-source-sequence-fencing'],
    'dependency includes next247' => [static fn (): mixed => in_array('sqlite-trigger-recursive-view-upsert-current-source-next247', $released247()['dependencies_next247'], true), true],
    'dependency includes sequence' => [static fn (): mixed => in_array('sqlite-instead-of-view-trigger-upsert-statement-source-sequence', $released247()['dependencies_next247'], true), true],
    'dependency includes next244' => [static fn (): mixed => in_array('sqlite-trigger-recursive-view-upsert-current-source-next244', $released247()['dependencies_next247'], true), true],
    'non overlap mentions next244' => [static fn (): mixed => str_contains($released247()['non_overlap_next247'], 'next244 commit watermark receipts'), true],
    'bad sequence rejected' => [static fn (): mixed => $plan247(['current_source_statement_sequence_next247' => -1]), InvalidArgumentException::class],
    'bad next sequence rejected' => [static fn (): mixed => $plan247(['next_source_statement_sequence_next247' => '248']), InvalidArgumentException::class],
    'bad view cookie rejected' => [static fn (): mixed => $plan247(['current_source_sequence_view_cookie_next247' => 'bad view']), InvalidArgumentException::class],
    'bad trigger cookie rejected' => [static fn (): mixed => $plan247(['current_source_sequence_trigger_cookie_next247' => 'bad trigger']), InvalidArgumentException::class],
    'bad cursor rejected' => [static fn (): mixed => $plan247(['current_source_sequence_cursor_next247' => 'bad cursor']), InvalidArgumentException::class],
    'bad receipt list rejected' => [static fn (): mixed => $plan247(['acknowledged_current_source_sequence_receipts_next247' => ['x' => $unexpectedReceipt247]]), InvalidArgumentException::class],
    'bad short receipt rejected' => [static fn (): mixed => $plan247(['acknowledged_current_source_sequence_receipts_next247' => ['abc']]), InvalidArgumentException::class],
    'bad non hex receipt rejected' => [static fn (): mixed => $plan247(['acknowledged_current_source_sequence_receipts_next247' => ['zzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzz']]), InvalidArgumentException::class],
];

$tests = [];
foreach ($cases247 as $name => [$callback, $expected]) {
    $tests['trigger recursive view upsert current source next247 ' . $name] = static function (TestRunner $t) use ($callback, $expected): void {
        if (is_string($expected) && is_a($expected, Throwable::class, true)) {
            $t->throws($expected, $callback);
            return;
        }
        $t->same($expected, $callback());
    };
}

return $tests;
