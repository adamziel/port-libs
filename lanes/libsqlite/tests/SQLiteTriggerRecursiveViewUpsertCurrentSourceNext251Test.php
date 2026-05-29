<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteTriggerRecursiveViewUpsertCurrentSourceNextPlan;

$rows251 = [
    ['option_id' => 1, 'option_name' => 'siteurl', 'option_value' => 'https://old.test', 'autoload' => 'yes'],
    ['option_id' => 2, 'option_name' => 'home', 'option_value' => 'https://home.test', 'autoload' => 'yes'],
];
$currentView251 = [
    'name' => 'wp_recursive_option_import',
    'source' => 'main@view-cookie-251-current',
    'trigger' => 'wp_recursive_option_import_io_insert',
    'trigger_source' => 'main@trigger-cookie-251-current',
    'columns' => ['import_id', 'name', 'value', 'autoload_flag', 'spawn_child'],
    'mapping' => ['import_id' => 'option_id', 'name' => 'option_name', 'value' => 'option_value', 'autoload_flag' => 'autoload', 'spawn_child' => 'spawn_child'],
    'audit_label' => 'current-recursive-view-upsert-change-counter-251',
];
$nextView251 = $currentView251;
$nextView251['source'] = 'main@view-cookie-251-next';
$nextView251['trigger_source'] = 'main@trigger-cookie-251-next';
$postResetView251 = $currentView251;
$postResetView251['source'] = 'main@view-cookie-251-post-reset';
$postResetView251['trigger_source'] = 'main@trigger-cookie-251-post-reset';
$followingView251 = $currentView251;
$followingView251['source'] = 'main@view-cookie-251-following';
$followingView251['trigger_source'] = 'main@trigger-cookie-251-following';
$currentInput251 = [
    ['import_id' => 10, 'name' => 'siteurl', 'value' => 'https://current.test', 'autoload_flag' => 'yes', 'spawn_child' => true],
    ['import_id' => 11, 'name' => 'current_plugin', 'value' => 'enabled', 'autoload_flag' => 'no', 'spawn_child' => true],
];
$nextInput251 = [
    ['import_id' => 20, 'name' => 'home', 'value' => 'https://next.test', 'autoload_flag' => 'yes', 'spawn_child' => true],
    ['import_id' => 21, 'name' => 'next_plugin', 'value' => 'active', 'autoload_flag' => 'no', 'spawn_child' => false],
];
$returning251 = [
    ['expr' => 'new.option_name', 'as' => 'name'],
    ['expr' => 'new.option_value', 'as' => 'value'],
    ['expr' => 'event', 'as' => 'event_name'],
    ['expr' => 'depth', 'as' => 'depth_value'],
    ['expr' => 'ordinal', 'as' => 'ordinal_value'],
    ['expr' => 'trigger_source', 'as' => 'trigger_source_alias'],
    ['expr' => 'spawn_child', 'as' => 'spawn_child'],
];

$baseOptions251 = [
    'key' => 'option_name',
    'savepoint' => 'wp_recursive_view_251',
    'cursor_name' => 'wp_recursive_view_returning_cursor_251',
    'admit_next_source' => true,
    'rollback_token' => 'wp.rollback.current.251',
    'reset_generation' => 'wp-current-reset-251',
    'post_reset_current_source_token' => 'wp.current.source.postreset.251',
    'post_reset_cursor' => 'wp.returning.postreset.cursor.251',
    'post_reset_view' => $postResetView251,
    'post_reset_input' => [
        ['import_id' => 30, 'name' => 'siteurl', 'value' => 'https://fresh.test', 'autoload_flag' => 'yes', 'spawn_child' => false],
        ['import_id' => 31, 'name' => 'rewrite_rules', 'value' => 'fresh-rules', 'autoload_flag' => 'no', 'spawn_child' => false],
    ],
    'fresh_acknowledged_ordinals' => [0, 1],
    'next_source_token' => 'wp.next.source.251',
    'next_cursor' => 'wp.returning.next.cursor.251',
    'next_acknowledged_ordinals' => [0, 1],
    'close_next_cursor' => 'wp.returning.next.cursor.251',
    'following_current_source_token' => 'wp.current.source.following.251',
    'following_cursor' => 'wp.returning.following.cursor.251',
    'following_current_view' => $followingView251,
    'following_current_input' => [
        ['import_id' => 40, 'name' => 'blogdescription', 'value' => 'after-next', 'autoload_flag' => 'yes', 'spawn_child' => true],
        ['import_id' => 41, 'name' => 'stylesheet', 'value' => 'twentytwentyfive', 'autoload_flag' => 'yes', 'spawn_child' => false],
        ['import_id' => 42, 'name' => 'template', 'value' => 'twentytwentyfive', 'autoload_flag' => 'yes', 'spawn_child' => true],
    ],
    'following_generation' => 'wp-following-current-251',
    'recursive_child_acknowledged_ordinals' => [0, 1],
    'recursive_child_source_token' => 'wp.current.source.recursive.child.251',
    'recursive_child_cursor' => 'wp.returning.recursive.child.cursor.251',
    'recursive_child_generation' => 'wp-recursive-child-current-251',
    'current_generation_next203' => 'wp.current.recursive.returning.generation.251',
    'expected_current_generation_next203' => 'wp.current.recursive.returning.generation.251',
    'current_handoff_cursor_next203' => 'wp.returning.current.handoff.cursor.251',
    'current_generation_commit_marker_next203' => 'wp.current.recursive.returning.commit.251',
    'auto_ack_current_generation_receipts_next203' => true,
    'current_source_drain_token_next209' => 'wp.current.source.drain.251',
    'current_view_cookie_next209' => 'main@view-cookie-251-current',
    'current_trigger_cookie_next209' => 'main@trigger-cookie-251-current',
    'auto_ack_current_source_watermarks_next209' => true,
    'current_source_yield_token_next212' => 'wp.current.source.yield.251',
    'current_view_yield_cursor_next212' => 'wp.returning.view.yield.cursor.251',
    'current_trigger_yield_cursor_next212' => 'wp.returning.trigger.yield.cursor.251',
    'auto_ack_current_source_yields_next212' => true,
    'current_source_epoch_next218' => 'wp.current.source.epoch.251',
    'current_view_epoch_next218' => 'wp.returning.view.epoch.cursor.251',
    'current_trigger_epoch_next218' => 'wp.returning.trigger.epoch.cursor.251',
    'auto_ack_current_source_epochs_next218' => true,
    'current_source_ticket_next222' => 'wp.current.source.ticket.251',
    'current_view_source_next222' => 'main@view-cookie-251-current',
    'current_trigger_source_next222' => 'main@trigger-cookie-251-current',
    'auto_ack_current_source_tickets_next222' => true,
    'current_source_cursor_next231' => 'wp.returning.current.cursor.251',
    'current_source_close_token_next231' => 'wp.current.source.close.251',
    'current_view_cookie_next231' => 'main@view-cookie-251-current',
    'current_trigger_cookie_next231' => 'main@trigger-cookie-251-current',
    'auto_ack_current_source_closures_next231' => true,
    'current_source_upsert_token_next234' => 'wp.current.source.upsert.251',
    'current_upsert_view_cookie_next234' => 'main@view-cookie-251-current',
    'current_upsert_trigger_cookie_next234' => 'main@trigger-cookie-251-current',
    'auto_ack_current_source_upserts_next234' => true,
    'current_source_upsert_action_token_next237' => 'wp.current.source.upsert.action.251',
    'current_upsert_action_view_cookie_next237' => 'main@view-cookie-251-current',
    'current_upsert_action_trigger_cookie_next237' => 'main@trigger-cookie-251-current',
    'auto_ack_current_source_upsert_actions_next237' => true,
    'current_source_upsert_close_token_next241' => 'wp.current.source.upsert.close.251',
    'current_source_upsert_generation_next241' => 'main@source-generation-251-current',
    'current_upsert_close_view_cookie_next241' => 'main@view-cookie-251-current',
    'current_upsert_close_trigger_cookie_next241' => 'main@trigger-cookie-251-current',
    'auto_ack_current_source_upsert_closes_next241' => true,
    'current_source_upsert_statement_id_next244' => 'wp.current.source.upsert.statement.251',
    'current_source_upsert_commit_watermark_next244' => 'wp.current.source.upsert.commit.251',
    'current_upsert_commit_view_cookie_next244' => 'main@view-cookie-251-current',
    'current_upsert_commit_trigger_cookie_next244' => 'main@trigger-cookie-251-current',
    'auto_ack_current_source_upsert_commits_next244' => true,
    'current_source_statement_sequence_next247' => 251,
    'next_source_statement_sequence_next247' => 252,
    'current_source_sequence_view_cookie_next247' => 'main@view-cookie-251-current',
    'current_source_sequence_trigger_cookie_next247' => 'main@trigger-cookie-251-current',
    'current_source_sequence_cursor_next247' => 'wp.returning.current.sequence.cursor.251',
    'auto_ack_current_source_statement_sequences_next247' => true,
    'current_source_changes_next251' => 2,
    'expected_current_source_changes_next251' => 2,
    'current_source_total_changes_next251' => 4,
    'minimum_current_source_total_changes_next251' => 2,
    'current_source_change_view_cookie_next251' => 'main@view-cookie-251-current',
    'current_source_change_trigger_cookie_next251' => 'main@trigger-cookie-251-current',
    'current_source_change_counter_cursor_next251' => 'wp.returning.current.change.counter.251',
];

$plan251 = static fn (array $options = []): array => SQLiteTriggerRecursiveViewUpsertCurrentSourceNextPlan::executeNext251(
    $rows251,
    $currentInput251,
    $nextInput251,
    $currentView251,
    $nextView251,
    $returning251,
    $options + $baseOptions251,
);

$receipts251 = static fn (): array => $plan251()['required_current_source_change_receipts_next251'];
$released251 = static fn (): array => $plan251(['auto_ack_current_source_change_counters_next251' => true]);
$missing251 = static fn (): array => $plan251(['acknowledged_current_source_change_receipts_next251' => array_slice($receipts251(), 0, 1)]);
$unexpectedReceipt251 = 'abcdefabcdefabcdefabcdefabcdefabcdefabcdefabcdefabcdefab';
$unexpected251 = static fn (): array => $plan251(['acknowledged_current_source_change_receipts_next251' => array_merge($receipts251(), [$unexpectedReceipt251])]);
$changesHeld251 = static fn (): array => $plan251(['auto_ack_current_source_change_counters_next251' => true, 'expected_current_source_changes_next251' => 3]);
$totalHeld251 = static fn (): array => $plan251(['auto_ack_current_source_change_counters_next251' => true, 'current_source_total_changes_next251' => 1]);
$totalAllowed251 = static fn (): array => $plan251(['auto_ack_current_source_change_counters_next251' => true, 'current_source_total_changes_next251' => 1, 'require_total_changes_monotonic_next251' => false]);
$baseHeld251 = static fn (): array => $plan251(['auto_ack_current_source_change_counters_next251' => true, 'auto_ack_current_source_statement_sequences_next247' => false]);
$custom251 = static fn (): array => $plan251([
    'auto_ack_current_source_change_counters_next251' => true,
    'current_source_changes_next251' => 7,
    'expected_current_source_changes_next251' => 7,
    'current_source_total_changes_next251' => 11,
    'minimum_current_source_total_changes_next251' => 10,
    'current_source_change_view_cookie_next251' => 'main@view-cookie-251-custom',
    'current_source_change_trigger_cookie_next251' => 'main@trigger-cookie-251-custom',
    'current_source_change_counter_cursor_next251' => 'wp.returning.current.change.counter.custom.251',
]);

$cases251 = [
    'released status' => [static fn (): mixed => $released251()['status_next251'], 'trigger-recursive-view-upsert-current-source-next251-change-counters-released'],
    'missing status' => [static fn (): mixed => $missing251()['status_next251'], 'trigger-recursive-view-upsert-current-source-next251-change-counters-missing-held'],
    'unexpected status' => [static fn (): mixed => $unexpected251()['status_next251'], 'trigger-recursive-view-upsert-current-source-next251-change-counters-unexpected-held'],
    'changes held status' => [static fn (): mixed => $changesHeld251()['status_next251'], 'trigger-recursive-view-upsert-current-source-next251-changes-held'],
    'total held status' => [static fn (): mixed => $totalHeld251()['status_next251'], 'trigger-recursive-view-upsert-current-source-next251-total-changes-held'],
    'total allowed status' => [static fn (): mixed => $totalAllowed251()['status_next251'], 'trigger-recursive-view-upsert-current-source-next251-change-counters-released'],
    'base held status' => [static fn (): mixed => $baseHeld251()['status_next251'], 'trigger-recursive-view-upsert-current-source-next251-base-held'],
    'savepoint retained' => [static fn (): mixed => $released251()['savepoint'], 'wp_recursive_view_251'],
    'base next247 released' => [static fn (): mixed => $released251()['base']['status_next247'], 'trigger-recursive-view-upsert-current-source-next247-sequence-released'],
    'base next247 held' => [static fn (): mixed => $baseHeld251()['base']['status_next247'], 'trigger-recursive-view-upsert-current-source-next247-sequence-missing-held'],
    'base visible released' => [static fn (): mixed => $released251()['base_next_source_visible_next251'], true],
    'base visible held' => [static fn (): mixed => $baseHeld251()['base_next_source_visible_next251'], false],
    'changes retained' => [static fn (): mixed => $released251()['current_source_changes_next251'], 2],
    'expected changes retained' => [static fn (): mixed => $released251()['expected_current_source_changes_next251'], 2],
    'custom changes retained' => [static fn (): mixed => $custom251()['current_source_changes_next251'], 7],
    'custom expected changes retained' => [static fn (): mixed => $custom251()['expected_current_source_changes_next251'], 7],
    'changes match released' => [static fn (): mixed => $released251()['current_source_changes_match_next251'], true],
    'changes mismatch detected' => [static fn (): mixed => $changesHeld251()['current_source_changes_match_next251'], false],
    'total retained' => [static fn (): mixed => $released251()['current_source_total_changes_next251'], 4],
    'minimum total retained' => [static fn (): mixed => $released251()['minimum_current_source_total_changes_next251'], 2],
    'custom total retained' => [static fn (): mixed => $custom251()['current_source_total_changes_next251'], 11],
    'custom minimum total retained' => [static fn (): mixed => $custom251()['minimum_current_source_total_changes_next251'], 10],
    'total monotonic default' => [static fn (): mixed => $released251()['require_total_changes_monotonic_next251'], true],
    'total monotonic released' => [static fn (): mixed => $released251()['current_source_total_changes_monotonic_next251'], true],
    'total monotonic failure' => [static fn (): mixed => $totalHeld251()['current_source_total_changes_monotonic_next251'], false],
    'total monotonic disabled' => [static fn (): mixed => $totalAllowed251()['require_total_changes_monotonic_next251'], false],
    'view cookie retained' => [static fn (): mixed => $released251()['current_source_change_view_cookie_next251'], 'main@view-cookie-251-current'],
    'custom view cookie retained' => [static fn (): mixed => $custom251()['current_source_change_view_cookie_next251'], 'main@view-cookie-251-custom'],
    'trigger cookie retained' => [static fn (): mixed => $released251()['current_source_change_trigger_cookie_next251'], 'main@trigger-cookie-251-current'],
    'custom trigger cookie retained' => [static fn (): mixed => $custom251()['current_source_change_trigger_cookie_next251'], 'main@trigger-cookie-251-custom'],
    'counter cursor retained' => [static fn (): mixed => $released251()['current_source_change_counter_cursor_next251'], 'wp.returning.current.change.counter.251'],
    'custom counter cursor retained' => [static fn (): mixed => $custom251()['current_source_change_counter_cursor_next251'], 'wp.returning.current.change.counter.custom.251'],
    'required receipt count' => [static fn (): mixed => count($released251()['required_current_source_change_receipts_next251']), 2],
    'receipts are fifty six hex' => [static fn (): mixed => array_map(static fn (string $v): int => preg_match('/^[a-f0-9]{56}$/', $v), $released251()['required_current_source_change_receipts_next251']), [1, 1]],
    'auto acknowledged equals required' => [static fn (): mixed => $released251()['acknowledged_current_source_change_receipts_next251'], $receipts251()],
    'missing acknowledged count' => [static fn (): mixed => count($missing251()['acknowledged_current_source_change_receipts_next251']), 1],
    'missing receipt recorded' => [static fn (): mixed => $missing251()['missing_current_source_change_receipts_next251'], [array_slice($receipts251(), -1)[0]]],
    'unexpected receipt recorded' => [static fn (): mixed => $unexpected251()['unexpected_current_source_change_receipts_next251'], [$unexpectedReceipt251]],
    'released missing empty' => [static fn (): mixed => $released251()['missing_current_source_change_receipts_next251'], []],
    'released unexpected empty' => [static fn (): mixed => $released251()['unexpected_current_source_change_receipts_next251'], []],
    'changes complete released' => [static fn (): mixed => $released251()['current_source_changes_complete_next251'], true],
    'changes complete missing false' => [static fn (): mixed => $missing251()['current_source_changes_complete_next251'], false],
    'changes complete unexpected false' => [static fn (): mixed => $unexpected251()['current_source_changes_complete_next251'], false],
    'changes complete mismatch false' => [static fn (): mixed => $changesHeld251()['current_source_changes_complete_next251'], false],
    'changes complete total false' => [static fn (): mixed => $totalHeld251()['current_source_changes_complete_next251'], false],
    'next visible released' => [static fn (): mixed => $released251()['next_source_visible_after_current_source_changes_next251'], true],
    'next denied missing' => [static fn (): mixed => $missing251()['next_source_visible_after_current_source_changes_next251'], false],
    'next denied changes mismatch' => [static fn (): mixed => $changesHeld251()['next_source_visible_after_current_source_changes_next251'], false],
    'current row count' => [static fn (): mixed => $released251()['current_source_row_count_next251'], 2],
    'attempted next row count' => [static fn (): mixed => $released251()['attempted_next_source_row_count_next251'], 2],
    'visible released count' => [static fn (): mixed => $released251()['visible_row_count_next251'], 4],
    'held released count' => [static fn (): mixed => $released251()['held_next_row_count_next251'], 0],
    'visible missing count current only' => [static fn (): mixed => $missing251()['visible_row_count_next251'], 2],
    'held missing count next only' => [static fn (): mixed => $missing251()['held_next_row_count_next251'], 2],
    'current phases' => [static fn (): mixed => array_values(array_unique(array_column($released251()['current_source_rows_next251'], 'change_counter_phase_next251'))), ['current-change-counter']],
    'next phases' => [static fn (): mixed => array_values(array_unique(array_column($released251()['attempted_next_source_rows_next251'], 'change_counter_phase_next251'))), ['next-source']],
    'current change receipts tagged' => [static fn (): mixed => array_column($released251()['current_source_rows_next251'], 'current_source_change_receipt_next251'), $receipts251()],
    'next change receipts null' => [static fn (): mixed => array_values(array_unique(array_column($released251()['attempted_next_source_rows_next251'], 'current_source_change_receipt_next251'))), [null]],
    'current visible while missing' => [static fn (): mixed => array_values(array_unique(array_column($missing251()['current_source_rows_next251'], 'visible_after_current_source_changes_next251'))), [true]],
    'next held while missing' => [static fn (): mixed => array_values(array_unique(array_column($missing251()['attempted_next_source_rows_next251'], 'visible_after_current_source_changes_next251'))), [false]],
    'visible payload names released' => [static fn (): mixed => array_column($released251()['visible_returning_payloads_next251'], 'name'), ['blogdescription_child', 'template_child', 'home', 'next_plugin']],
    'held payload names missing' => [static fn (): mixed => array_column($missing251()['held_next_returning_payloads_next251'], 'name'), ['home', 'next_plugin']],
    'blocked reasons missing' => [static fn (): mixed => $missing251()['blocked_reasons_next251'], ['current-source-change-counter-missing']],
    'blocked reasons unexpected' => [static fn (): mixed => $unexpected251()['blocked_reasons_next251'], ['current-source-change-counter-unexpected']],
    'blocked reasons changes mismatch' => [static fn (): mixed => $changesHeld251()['blocked_reasons_next251'], ['current-source-changes-mismatch']],
    'blocked reasons total mismatch' => [static fn (): mixed => $totalHeld251()['blocked_reasons_next251'], ['current-source-total-changes-not-monotonic']],
    'blocked reasons base held' => [static fn (): mixed => $baseHeld251()['blocked_reasons_next251'], ['current-source-statement-sequence-missing']],
    'released reasons empty' => [static fn (): mixed => $released251()['blocked_reasons_next251'], []],
    'plan decision released' => [static fn (): mixed => $released251()['current_source_change_counter_plan_next251']['decision'], 'publish-next-source-after-current-recursive-view-upsert-change-counters'],
    'plan decision missing' => [static fn (): mixed => $missing251()['current_source_change_counter_plan_next251']['decision'], 'hold-next-source-until-current-recursive-view-upsert-change-counters'],
    'plan required echoed' => [static fn (): mixed => $released251()['current_source_change_counter_plan_next251']['required_receipts'], $receipts251()],
    'yield boundary released' => [static fn (): mixed => $released251()['yield_boundary_next251'], 'recursive-view-upsert-next251-current-change-counters-then-next'],
    'yield boundary held' => [static fn (): mixed => $missing251()['yield_boundary_next251'], 'recursive-view-upsert-next251-current-change-counters-fence-next'],
    'dependency closure marker' => [static fn (): mixed => $released251()['dependency_closure_next251'], 'no-new-support-component-reuses-native-recursive-view-upsert-statement-sequence-and-adds-change-counter-fencing'],
    'dependency includes next251' => [static fn (): mixed => in_array('sqlite-trigger-recursive-view-upsert-current-source-next251', $released251()['dependencies_next251'], true), true],
    'dependency includes change fence' => [static fn (): mixed => in_array('sqlite-instead-of-view-trigger-upsert-change-counter-fence', $released251()['dependencies_next251'], true), true],
    'dependency includes next247' => [static fn (): mixed => in_array('sqlite-trigger-recursive-view-upsert-current-source-next247', $released251()['dependencies_next251'], true), true],
    'non overlap mentions next247' => [static fn (): mixed => str_contains($released251()['non_overlap_next251'], 'next247 statement sequence fencing'), true],
    'bad changes rejected' => [static fn (): mixed => $plan251(['current_source_changes_next251' => -1]), InvalidArgumentException::class],
    'bad expected changes rejected' => [static fn (): mixed => $plan251(['expected_current_source_changes_next251' => '2']), InvalidArgumentException::class],
    'bad total changes rejected' => [static fn (): mixed => $plan251(['current_source_total_changes_next251' => -1]), InvalidArgumentException::class],
    'bad minimum total rejected' => [static fn (): mixed => $plan251(['minimum_current_source_total_changes_next251' => '2']), InvalidArgumentException::class],
    'bad view cookie rejected' => [static fn (): mixed => $plan251(['current_source_change_view_cookie_next251' => 'bad view']), InvalidArgumentException::class],
    'bad trigger cookie rejected' => [static fn (): mixed => $plan251(['current_source_change_trigger_cookie_next251' => 'bad trigger']), InvalidArgumentException::class],
    'bad counter cursor rejected' => [static fn (): mixed => $plan251(['current_source_change_counter_cursor_next251' => 'bad cursor']), InvalidArgumentException::class],
    'bad receipt list rejected' => [static fn (): mixed => $plan251(['acknowledged_current_source_change_receipts_next251' => ['x' => $unexpectedReceipt251]]), InvalidArgumentException::class],
    'bad short receipt rejected' => [static fn (): mixed => $plan251(['acknowledged_current_source_change_receipts_next251' => ['abc']]), InvalidArgumentException::class],
    'bad non hex receipt rejected' => [static fn (): mixed => $plan251(['acknowledged_current_source_change_receipts_next251' => ['zzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzz']]), InvalidArgumentException::class],
];

$tests = [];
foreach ($cases251 as $name => [$callback, $expected]) {
    $tests['trigger recursive view upsert current source next251 ' . $name] = static function (TestRunner $t) use ($callback, $expected): void {
        if (is_string($expected) && is_a($expected, Throwable::class, true)) {
            $t->throws($expected, $callback);
            return;
        }
        $t->same($expected, $callback());
    };
}

return $tests;
