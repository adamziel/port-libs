<?php

declare(strict_types=1);

foreach (glob(__DIR__ . '/../src/SQLiteTriggerRecursiveViewReturningCurrentSourceNext*.php') ?: [] as $file) {
    require_once $file;
}
foreach (glob(__DIR__ . '/../src/SQLiteTriggerRecursiveViewUpsertCurrentSourceNext*Plan.php') ?: [] as $file) {
    require_once $file;
}

use PortLibs\LibSqlite\SQLiteTriggerRecursiveViewUpsertCurrentSourceNext250Plan;

$rows250 = [
    ['option_id' => 1, 'option_name' => 'siteurl', 'option_value' => 'https://old.test', 'autoload' => 'yes'],
    ['option_id' => 2, 'option_name' => 'home', 'option_value' => 'https://home.test', 'autoload' => 'yes'],
];
$currentView250 = [
    'name' => 'wp_recursive_option_import',
    'source' => 'main@view-cookie-250-current',
    'trigger' => 'wp_recursive_option_import_io_insert',
    'trigger_source' => 'main@trigger-cookie-250-current',
    'columns' => ['import_id', 'name', 'value', 'autoload_flag', 'spawn_child'],
    'mapping' => ['import_id' => 'option_id', 'name' => 'option_name', 'value' => 'option_value', 'autoload_flag' => 'autoload', 'spawn_child' => 'spawn_child'],
    'audit_label' => 'current-recursive-view-upsert-rowid-250',
];
$nextView250 = $currentView250;
$nextView250['source'] = 'main@view-cookie-250-next';
$nextView250['trigger_source'] = 'main@trigger-cookie-250-next';
$postResetView250 = $currentView250;
$postResetView250['source'] = 'main@view-cookie-250-post-reset';
$postResetView250['trigger_source'] = 'main@trigger-cookie-250-post-reset';
$followingView250 = $currentView250;
$followingView250['source'] = 'main@view-cookie-250-following';
$followingView250['trigger_source'] = 'main@trigger-cookie-250-following';
$currentInput250 = [
    ['import_id' => 10, 'name' => 'siteurl', 'value' => 'https://current.test', 'autoload_flag' => 'yes', 'spawn_child' => true],
    ['import_id' => 11, 'name' => 'current_plugin', 'value' => 'enabled', 'autoload_flag' => 'no', 'spawn_child' => true],
];
$nextInput250 = [
    ['import_id' => 20, 'name' => 'home', 'value' => 'https://next.test', 'autoload_flag' => 'yes', 'spawn_child' => true],
    ['import_id' => 21, 'name' => 'next_plugin', 'value' => 'active', 'autoload_flag' => 'no', 'spawn_child' => false],
];
$returning250 = [
    ['expr' => 'new.option_name', 'as' => 'name'],
    ['expr' => 'new.option_value', 'as' => 'value'],
    ['expr' => 'event', 'as' => 'event_name'],
    ['expr' => 'depth', 'as' => 'depth_value'],
    ['expr' => 'ordinal', 'as' => 'ordinal_value'],
    ['expr' => 'trigger_source', 'as' => 'trigger_source_alias'],
    ['expr' => 'spawn_child', 'as' => 'spawn_child'],
];

$baseOptions250 = [
    'key' => 'option_name',
    'savepoint' => 'wp_recursive_view_250',
    'cursor_name' => 'wp_recursive_view_returning_cursor_250',
    'admit_next_source' => true,
    'rollback_token' => 'wp.rollback.current.250',
    'reset_generation' => 'wp-current-reset-250',
    'post_reset_current_source_token' => 'wp.current.source.postreset.250',
    'post_reset_cursor' => 'wp.returning.postreset.cursor.250',
    'post_reset_view' => $postResetView250,
    'post_reset_input' => [
        ['import_id' => 30, 'name' => 'siteurl', 'value' => 'https://fresh.test', 'autoload_flag' => 'yes', 'spawn_child' => false],
        ['import_id' => 31, 'name' => 'rewrite_rules', 'value' => 'fresh-rules', 'autoload_flag' => 'no', 'spawn_child' => false],
    ],
    'fresh_acknowledged_ordinals' => [0, 1],
    'next_source_token' => 'wp.next.source.250',
    'next_cursor' => 'wp.returning.next.cursor.250',
    'next_acknowledged_ordinals' => [0, 1],
    'close_next_cursor' => 'wp.returning.next.cursor.250',
    'following_current_source_token' => 'wp.current.source.following.250',
    'following_cursor' => 'wp.returning.following.cursor.250',
    'following_current_view' => $followingView250,
    'following_current_input' => [
        ['import_id' => 40, 'name' => 'blogdescription', 'value' => 'after-next', 'autoload_flag' => 'yes', 'spawn_child' => true],
        ['import_id' => 41, 'name' => 'stylesheet', 'value' => 'twentytwentyfive', 'autoload_flag' => 'yes', 'spawn_child' => false],
        ['import_id' => 42, 'name' => 'template', 'value' => 'twentytwentyfive', 'autoload_flag' => 'yes', 'spawn_child' => true],
    ],
    'following_generation' => 'wp-following-current-250',
    'recursive_child_acknowledged_ordinals' => [0, 1],
    'recursive_child_source_token' => 'wp.current.source.recursive.child.250',
    'recursive_child_cursor' => 'wp.returning.recursive.child.cursor.250',
    'recursive_child_generation' => 'wp-recursive-child-current-250',
    'current_generation_next203' => 'wp.current.recursive.returning.generation.250',
    'expected_current_generation_next203' => 'wp.current.recursive.returning.generation.250',
    'current_handoff_cursor_next203' => 'wp.returning.current.handoff.cursor.250',
    'current_generation_commit_marker_next203' => 'wp.current.recursive.returning.commit.250',
    'auto_ack_current_generation_receipts_next203' => true,
    'current_source_drain_token_next209' => 'wp.current.source.drain.250',
    'current_view_cookie_next209' => 'main@view-cookie-250-current',
    'current_trigger_cookie_next209' => 'main@trigger-cookie-250-current',
    'auto_ack_current_source_watermarks_next209' => true,
    'current_source_yield_token_next212' => 'wp.current.source.yield.250',
    'current_view_yield_cursor_next212' => 'wp.returning.view.yield.cursor.250',
    'current_trigger_yield_cursor_next212' => 'wp.returning.trigger.yield.cursor.250',
    'auto_ack_current_source_yields_next212' => true,
    'current_source_epoch_next218' => 'wp.current.source.epoch.250',
    'current_view_epoch_next218' => 'wp.returning.view.epoch.cursor.250',
    'current_trigger_epoch_next218' => 'wp.returning.trigger.epoch.cursor.250',
    'auto_ack_current_source_epochs_next218' => true,
    'current_source_ticket_next222' => 'wp.current.source.ticket.250',
    'current_view_source_next222' => 'main@view-cookie-250-current',
    'current_trigger_source_next222' => 'main@trigger-cookie-250-current',
    'auto_ack_current_source_tickets_next222' => true,
    'current_source_cursor_next231' => 'wp.returning.current.cursor.250',
    'current_source_close_token_next231' => 'wp.current.source.close.250',
    'current_view_cookie_next231' => 'main@view-cookie-250-current',
    'current_trigger_cookie_next231' => 'main@trigger-cookie-250-current',
    'auto_ack_current_source_closures_next231' => true,
    'current_source_upsert_token_next234' => 'wp.current.source.upsert.250',
    'current_upsert_view_cookie_next234' => 'main@view-cookie-250-current',
    'current_upsert_trigger_cookie_next234' => 'main@trigger-cookie-250-current',
    'auto_ack_current_source_upserts_next234' => true,
    'current_source_upsert_action_token_next237' => 'wp.current.source.upsert.action.250',
    'current_upsert_action_view_cookie_next237' => 'main@view-cookie-250-current',
    'current_upsert_action_trigger_cookie_next237' => 'main@trigger-cookie-250-current',
    'auto_ack_current_source_upsert_actions_next237' => true,
    'current_source_upsert_close_token_next241' => 'wp.current.source.upsert.close.250',
    'current_source_upsert_generation_next241' => 'main@source-generation-250-current',
    'current_upsert_close_view_cookie_next241' => 'main@view-cookie-250-current',
    'current_upsert_close_trigger_cookie_next241' => 'main@trigger-cookie-250-current',
    'auto_ack_current_source_upsert_closes_next241' => true,
    'current_source_upsert_statement_id_next244' => 'wp.current.source.upsert.statement.250',
    'current_source_upsert_commit_watermark_next244' => 'wp.current.source.upsert.commit.250',
    'current_upsert_commit_view_cookie_next244' => 'main@view-cookie-250-current',
    'current_upsert_commit_trigger_cookie_next244' => 'main@trigger-cookie-250-current',
    'auto_ack_current_source_upsert_commits_next244' => true,
    'current_source_statement_sequence_next247' => 250,
    'next_source_statement_sequence_next247' => 251,
    'current_source_sequence_view_cookie_next247' => 'main@view-cookie-250-current',
    'current_source_sequence_trigger_cookie_next247' => 'main@trigger-cookie-250-current',
    'current_source_sequence_cursor_next247' => 'wp.returning.current.sequence.cursor.250',
    'auto_ack_current_source_statement_sequences_next247' => true,
    'current_source_rowid_provenance_token_next250' => 'wp.current.source.rowid.provenance.250',
];

$plan250 = static fn (array $options = []): array => SQLiteTriggerRecursiveViewUpsertCurrentSourceNext250Plan::execute(
    $rows250,
    $currentInput250,
    $nextInput250,
    $currentView250,
    $nextView250,
    $returning250,
    $options + $baseOptions250,
);

$receipts250 = static fn (): array => $plan250()['required_current_source_rowid_receipts_next250'];
$released250 = static fn (): array => $plan250(['auto_ack_current_source_rowid_provenance_next250' => true]);
$missing250 = static fn (): array => $plan250(['acknowledged_current_source_rowid_receipts_next250' => array_slice($receipts250(), 0, 1)]);
$unexpectedReceipt250 = 'abcdefabcdefabcdefabcdefabcdefabcdefabcdefabcdefab';
$unexpected250 = static fn (): array => $plan250(['acknowledged_current_source_rowid_receipts_next250' => array_merge($receipts250(), [$unexpectedReceipt250])]);
$tokenHeld250 = static fn (): array => $plan250(['auto_ack_current_source_rowid_provenance_next250' => true, 'expected_current_source_rowid_provenance_token_next250' => 'wp.current.source.rowid.provenance.stale.250']);
$existingHeld250 = static fn (): array => $plan250(['auto_ack_current_source_rowid_provenance_next250' => true, 'require_existing_rowid_for_update_next250' => true]);
$baseHeld250 = static fn (): array => $plan250(['auto_ack_current_source_rowid_provenance_next250' => true, 'auto_ack_current_source_statement_sequences_next247' => false]);
$custom250 = static fn (): array => $plan250([
    'auto_ack_current_source_rowid_provenance_next250' => true,
    'current_source_rowid_provenance_token_next250' => 'wp.current.source.rowid.provenance.custom.250',
    'expected_current_source_rowid_provenance_token_next250' => 'wp.current.source.rowid.provenance.custom.250',
    'rowid_column_next250' => 'option_id',
    'conflict_key_column_next250' => 'option_name',
]);

$cases250 = [
    'released status' => [static fn (): mixed => $released250()['status_next250'], 'trigger-recursive-view-upsert-current-source-next250-rowid-provenance-released'],
    'missing status' => [static fn (): mixed => $missing250()['status_next250'], 'trigger-recursive-view-upsert-current-source-next250-rowid-missing-held'],
    'unexpected status' => [static fn (): mixed => $unexpected250()['status_next250'], 'trigger-recursive-view-upsert-current-source-next250-rowid-unexpected-held'],
    'token held status' => [static fn (): mixed => $tokenHeld250()['status_next250'], 'trigger-recursive-view-upsert-current-source-next250-rowid-token-held'],
    'existing rowid held status' => [static fn (): mixed => $existingHeld250()['status_next250'], 'trigger-recursive-view-upsert-current-source-next250-rowid-existing-held'],
    'base held status' => [static fn (): mixed => $baseHeld250()['status_next250'], 'trigger-recursive-view-upsert-current-source-next250-base-held'],
    'savepoint retained' => [static fn (): mixed => $released250()['savepoint'], 'wp_recursive_view_250'],
    'base next247 released' => [static fn (): mixed => $released250()['base']['status_next247'], 'trigger-recursive-view-upsert-current-source-next247-sequence-released'],
    'base visible released' => [static fn (): mixed => $released250()['base_next_source_visible_next250'], true],
    'base visible held' => [static fn (): mixed => $baseHeld250()['base_next_source_visible_next250'], false],
    'token retained' => [static fn (): mixed => $released250()['current_source_rowid_provenance_token_next250'], 'wp.current.source.rowid.provenance.250'],
    'custom token retained' => [static fn (): mixed => $custom250()['current_source_rowid_provenance_token_next250'], 'wp.current.source.rowid.provenance.custom.250'],
    'expected token retained' => [static fn (): mixed => $released250()['expected_current_source_rowid_provenance_token_next250'], 'wp.current.source.rowid.provenance.250'],
    'token matches released' => [static fn (): mixed => $released250()['current_source_rowid_provenance_token_matches_next250'], true],
    'token mismatch detected' => [static fn (): mixed => $tokenHeld250()['current_source_rowid_provenance_token_matches_next250'], false],
    'rowid column retained' => [static fn (): mixed => $released250()['rowid_column_next250'], 'option_id'],
    'conflict key retained' => [static fn (): mixed => $released250()['conflict_key_column_next250'], 'option_name'],
    'require existing default false' => [static fn (): mixed => $released250()['require_existing_rowid_for_update_next250'], false],
    'require existing custom true' => [static fn (): mixed => $existingHeld250()['require_existing_rowid_for_update_next250'], true],
    'provenance count' => [static fn (): mixed => count($released250()['current_source_rowid_provenance_next250']), 2],
    'first provenance key' => [static fn (): mixed => $released250()['current_source_rowid_provenance_next250'][0]['conflict_key'], 'TEXT:blogdescription_child'],
    'second provenance key' => [static fn (): mixed => $released250()['current_source_rowid_provenance_next250'][1]['conflict_key'], 'TEXT:template_child'],
    'first action insert' => [static fn (): mixed => $released250()['current_source_rowid_provenance_next250'][0]['upsert_rowid_action'], 'insert-rowid'],
    'old rowid absent for child' => [static fn (): mixed => $released250()['current_source_rowid_provenance_next250'][0]['old_rowid'], null],
    'new rowid absent for returning child' => [static fn (): mixed => $released250()['current_source_rowid_provenance_next250'][0]['new_rowid'], null],
    'statement receipt copied' => [static fn (): mixed => is_string($released250()['current_source_rowid_provenance_next250'][0]['statement_sequence_receipt']), true],
    'required receipt count' => [static fn (): mixed => count($released250()['required_current_source_rowid_receipts_next250']), 2],
    'receipts are fifty hex' => [static fn (): mixed => array_map(static fn (string $v): int => preg_match('/^[a-f0-9]{50}$/', $v), $released250()['required_current_source_rowid_receipts_next250']), [1, 1]],
    'auto acknowledged equals required' => [static fn (): mixed => $released250()['acknowledged_current_source_rowid_receipts_next250'], $receipts250()],
    'missing acknowledged count' => [static fn (): mixed => count($missing250()['acknowledged_current_source_rowid_receipts_next250']), 1],
    'missing receipt recorded' => [static fn (): mixed => $missing250()['missing_current_source_rowid_receipts_next250'], [array_slice($receipts250(), -1)[0]]],
    'unexpected receipt recorded' => [static fn (): mixed => $unexpected250()['unexpected_current_source_rowid_receipts_next250'], [$unexpectedReceipt250]],
    'released missing empty' => [static fn (): mixed => $released250()['missing_current_source_rowid_receipts_next250'], []],
    'released unexpected empty' => [static fn (): mixed => $released250()['unexpected_current_source_rowid_receipts_next250'], []],
    'existing missing count' => [static fn (): mixed => count($existingHeld250()['missing_existing_rowid_provenance_next250']), 2],
    'existing missing released empty' => [static fn (): mixed => $released250()['missing_existing_rowid_provenance_next250'], []],
    'provenance complete released' => [static fn (): mixed => $released250()['current_source_rowid_provenance_complete_next250'], true],
    'provenance complete missing false' => [static fn (): mixed => $missing250()['current_source_rowid_provenance_complete_next250'], false],
    'provenance complete unexpected false' => [static fn (): mixed => $unexpected250()['current_source_rowid_provenance_complete_next250'], false],
    'provenance complete token false' => [static fn (): mixed => $tokenHeld250()['current_source_rowid_provenance_complete_next250'], false],
    'next visible released' => [static fn (): mixed => $released250()['next_source_visible_after_current_source_rowid_provenance_next250'], true],
    'next denied missing' => [static fn (): mixed => $missing250()['next_source_visible_after_current_source_rowid_provenance_next250'], false],
    'next denied existing' => [static fn (): mixed => $existingHeld250()['next_source_visible_after_current_source_rowid_provenance_next250'], false],
    'current row count' => [static fn (): mixed => $released250()['current_source_row_count_next250'], 2],
    'attempted next row count' => [static fn (): mixed => $released250()['attempted_next_source_row_count_next250'], 2],
    'visible released count' => [static fn (): mixed => $released250()['visible_row_count_next250'], 4],
    'held released count' => [static fn (): mixed => $released250()['held_next_row_count_next250'], 0],
    'visible missing count current only' => [static fn (): mixed => $missing250()['visible_row_count_next250'], 2],
    'held missing count next only' => [static fn (): mixed => $missing250()['held_next_row_count_next250'], 2],
    'current phases' => [static fn (): mixed => array_values(array_unique(array_column($released250()['current_source_rows_next250'], 'rowid_provenance_phase_next250'))), ['current-rowid-provenance']],
    'next phases' => [static fn (): mixed => array_values(array_unique(array_column($released250()['attempted_next_source_rows_next250'], 'rowid_provenance_phase_next250'))), ['next-source']],
    'current receipts tagged' => [static fn (): mixed => array_column($released250()['current_source_rows_next250'], 'current_source_rowid_receipt_next250'), $receipts250()],
    'next receipts null' => [static fn (): mixed => array_values(array_unique(array_column($released250()['attempted_next_source_rows_next250'], 'current_source_rowid_receipt_next250'))), [null]],
    'current visible while missing' => [static fn (): mixed => array_values(array_unique(array_column($missing250()['current_source_rows_next250'], 'visible_after_current_source_rowid_provenance_next250'))), [true]],
    'next held while missing' => [static fn (): mixed => array_values(array_unique(array_column($missing250()['attempted_next_source_rows_next250'], 'visible_after_current_source_rowid_provenance_next250'))), [false]],
    'visible payload names released' => [static fn (): mixed => array_column($released250()['visible_returning_payloads_next250'], 'name'), ['blogdescription_child', 'template_child', 'home', 'next_plugin']],
    'held payload names missing' => [static fn (): mixed => array_column($missing250()['held_next_returning_payloads_next250'], 'name'), ['home', 'next_plugin']],
    'blocked reasons released' => [static fn (): mixed => $released250()['blocked_reasons_next250'], []],
    'blocked reasons missing' => [static fn (): mixed => $missing250()['blocked_reasons_next250'], ['current-source-rowid-provenance-missing']],
    'blocked reasons unexpected' => [static fn (): mixed => $unexpected250()['blocked_reasons_next250'], ['current-source-rowid-provenance-unexpected']],
    'blocked reasons token' => [static fn (): mixed => $tokenHeld250()['blocked_reasons_next250'], ['current-source-rowid-provenance-token-mismatch']],
    'blocked reasons existing' => [static fn (): mixed => $existingHeld250()['blocked_reasons_next250'], ['current-source-rowid-provenance-existing-rowid-missing']],
    'held row reason copied' => [static fn (): mixed => $missing250()['held_next_source_rows_next250'][0]['held_by_current_source_rowid_provenance_reasons_next250'], ['current-source-rowid-provenance-missing']],
    'plan decision released' => [static fn (): mixed => $released250()['current_source_rowid_provenance_plan_next250']['decision'], 'publish-next-source-after-current-recursive-view-upsert-rowids'],
    'plan decision held' => [static fn (): mixed => $missing250()['current_source_rowid_provenance_plan_next250']['decision'], 'hold-next-source-until-current-recursive-view-upsert-rowids'],
    'plan required echoed' => [static fn (): mixed => $released250()['current_source_rowid_provenance_plan_next250']['required_receipts'], $receipts250()],
    'plan next visible echoed' => [static fn (): mixed => $released250()['current_source_rowid_provenance_plan_next250']['next_source_visible'], true],
    'yield boundary released' => [static fn (): mixed => $released250()['yield_boundary_next250'], 'recursive-view-upsert-next250-current-rowids-then-next'],
    'yield boundary held' => [static fn (): mixed => $missing250()['yield_boundary_next250'], 'recursive-view-upsert-next250-current-rowids-fence-next'],
    'dependency closure marker' => [static fn (): mixed => $released250()['dependency_closure_next250'], 'no-new-support-component-reuses-native-recursive-view-upsert-sequence-and-adds-current-rowid-provenance-receipts'],
    'dependency includes next250' => [static fn (): mixed => in_array('sqlite-trigger-recursive-view-upsert-current-source-next250', $released250()['dependencies_next250'], true), true],
    'dependency includes rowid provenance' => [static fn (): mixed => in_array('sqlite-instead-of-view-trigger-upsert-rowid-provenance', $released250()['dependencies_next250'], true), true],
    'dependency includes next247' => [static fn (): mixed => in_array('sqlite-trigger-recursive-view-upsert-current-source-next247', $released250()['dependencies_next250'], true), true],
    'non overlap mentions next247' => [static fn (): mixed => str_contains($released250()['non_overlap_next250'], 'next247 statement sequence'), true],
    'bad token rejected' => [static fn (): mixed => $plan250(['current_source_rowid_provenance_token_next250' => 'bad token']), InvalidArgumentException::class],
    'bad expected token rejected' => [static fn (): mixed => $plan250(['expected_current_source_rowid_provenance_token_next250' => 'bad token']), InvalidArgumentException::class],
    'bad rowid column rejected' => [static fn (): mixed => $plan250(['rowid_column_next250' => 'bad column']), InvalidArgumentException::class],
    'bad conflict key rejected' => [static fn (): mixed => $plan250(['conflict_key_column_next250' => 'bad-key']), InvalidArgumentException::class],
    'bad receipt list rejected' => [static fn (): mixed => $plan250(['acknowledged_current_source_rowid_receipts_next250' => ['x' => $unexpectedReceipt250]]), InvalidArgumentException::class],
    'bad short receipt rejected' => [static fn (): mixed => $plan250(['acknowledged_current_source_rowid_receipts_next250' => ['abc']]), InvalidArgumentException::class],
    'bad non hex receipt rejected' => [static fn (): mixed => $plan250(['acknowledged_current_source_rowid_receipts_next250' => ['zzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzz']]), InvalidArgumentException::class],
];

$tests = [];
foreach ($cases250 as $name => [$callback, $expected]) {
    $tests['trigger recursive view upsert current source next250 ' . $name] = static function (TestRunner $t) use ($callback, $expected): void {
        if (is_string($expected) && is_a($expected, Throwable::class, true)) {
            $t->throws($expected, $callback);
            return;
        }
        $t->same($expected, $callback());
    };
}

return $tests;
