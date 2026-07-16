<?php

declare(strict_types=1);

foreach (glob(__DIR__ . '/../src/SQLiteTriggerRecursiveViewReturningCurrentSourceNext*.php') ?: [] as $file) {
    require_once $file;
}
foreach (glob(__DIR__ . '/../src/SQLiteTriggerRecursiveViewUpsertCurrentSourceNext*Plan.php') ?: [] as $file) {
    require_once $file;
}

use PortLibs\LibSqlite\SQLiteTriggerRecursiveViewUpsertCurrentSourceNextPlan;

$rows254 = [
    ['setting_id' => 1, 'key_name' => 'base_url', 'key_value' => 'https://old.test', 'load_policy' => 'yes'],
    ['setting_id' => 2, 'key_name' => 'landing_url', 'key_value' => 'https://landing_url.test', 'load_policy' => 'yes'],
];
$currentView254 = [
    'name' => 'app_recursive_setting_import',
    'source' => 'main@view-cookie-254-current',
    'trigger' => 'app_recursive_setting_import_io_insert',
    'trigger_source' => 'main@trigger-cookie-254-current',
    'columns' => ['import_id', 'name', 'value', 'load_policy_flag', 'spawn_child'],
    'mapping' => ['import_id' => 'setting_id', 'name' => 'key_name', 'value' => 'key_value', 'load_policy_flag' => 'load_policy', 'spawn_child' => 'spawn_child'],
    'audit_label' => 'current-recursive-view-upsert-mapping-254',
];
$nextView254 = $currentView254;
$nextView254['source'] = 'main@view-cookie-254-next';
$nextView254['trigger_source'] = 'main@trigger-cookie-254-next';
$postResetView254 = $currentView254;
$postResetView254['source'] = 'main@view-cookie-254-post-reset';
$postResetView254['trigger_source'] = 'main@trigger-cookie-254-post-reset';
$followingView254 = $currentView254;
$followingView254['source'] = 'main@view-cookie-254-following';
$followingView254['trigger_source'] = 'main@trigger-cookie-254-following';
$currentInput254 = [
    ['import_id' => 10, 'name' => 'base_url', 'value' => 'https://current.test', 'load_policy_flag' => 'yes', 'spawn_child' => true],
    ['import_id' => 11, 'name' => 'current_module', 'value' => 'enabled', 'load_policy_flag' => 'no', 'spawn_child' => true],
];
$nextInput254 = [
    ['import_id' => 20, 'name' => 'landing_url', 'value' => 'https://next.test', 'load_policy_flag' => 'yes', 'spawn_child' => true],
    ['import_id' => 21, 'name' => 'next_module', 'value' => 'active', 'load_policy_flag' => 'no', 'spawn_child' => false],
];
$returning254 = [
    ['expr' => 'new.key_name', 'as' => 'name'],
    ['expr' => 'new.key_value', 'as' => 'value'],
    ['expr' => 'event', 'as' => 'event_name'],
    ['expr' => 'depth', 'as' => 'depth_value'],
    ['expr' => 'ordinal', 'as' => 'ordinal_value'],
    ['expr' => 'trigger_source', 'as' => 'trigger_source_alias'],
    ['expr' => 'spawn_child', 'as' => 'spawn_child'],
];
$baseOptions254 = [
    'key' => 'key_name',
    'savepoint' => 'app_recursive_view_254',
    'cursor_name' => 'app_recursive_view_returning_cursor_254',
    'admit_next_source' => true,
    'rollback_token' => 'app.rollback.current.254',
    'reset_generation' => 'app-current-reset-254',
    'post_reset_current_source_token' => 'app.current.source.postreset.254',
    'post_reset_cursor' => 'app.returning.postreset.cursor.254',
    'post_reset_view' => $postResetView254,
    'post_reset_input' => [
        ['import_id' => 30, 'name' => 'base_url', 'value' => 'https://fresh.test', 'load_policy_flag' => 'yes', 'spawn_child' => false],
        ['import_id' => 31, 'name' => 'routing_rules', 'value' => 'fresh-rules', 'load_policy_flag' => 'no', 'spawn_child' => false],
    ],
    'fresh_acknowledged_ordinals' => [0, 1],
    'next_source_token' => 'app.next.source.254',
    'next_cursor' => 'app.returning.next.cursor.254',
    'next_acknowledged_ordinals' => [0, 1],
    'close_next_cursor' => 'app.returning.next.cursor.254',
    'following_current_source_token' => 'app.current.source.following.254',
    'following_cursor' => 'app.returning.following.cursor.254',
    'following_current_view' => $followingView254,
    'following_current_input' => [
        ['import_id' => 40, 'name' => 'app_summary', 'value' => 'after-next', 'load_policy_flag' => 'yes', 'spawn_child' => true],
        ['import_id' => 41, 'name' => 'theme_style_key', 'value' => 'modern_theme', 'load_policy_flag' => 'yes', 'spawn_child' => false],
        ['import_id' => 42, 'name' => 'template', 'value' => 'modern_theme', 'load_policy_flag' => 'yes', 'spawn_child' => true],
    ],
    'following_generation' => 'app-following-current-254',
    'recursive_child_acknowledged_ordinals' => [0, 1],
    'recursive_child_source_token' => 'app.current.source.recursive.child.254',
    'recursive_child_cursor' => 'app.returning.recursive.child.cursor.254',
    'recursive_child_generation' => 'app-recursive-child-current-254',
    'current_generation_next203' => 'app.current.recursive.returning.generation.254',
    'expected_current_generation_next203' => 'app.current.recursive.returning.generation.254',
    'current_handoff_cursor_next203' => 'app.returning.current.handoff.cursor.254',
    'current_generation_commit_marker_next203' => 'app.current.recursive.returning.commit.254',
    'auto_ack_current_generation_receipts_next203' => true,
    'current_source_drain_token_next209' => 'app.current.source.drain.254',
    'current_view_cookie_next209' => 'main@view-cookie-254-current',
    'current_trigger_cookie_next209' => 'main@trigger-cookie-254-current',
    'auto_ack_current_source_watermarks_next209' => true,
    'current_source_yield_token_next212' => 'app.current.source.yield.254',
    'current_view_yield_cursor_next212' => 'app.returning.view.yield.cursor.254',
    'current_trigger_yield_cursor_next212' => 'app.returning.trigger.yield.cursor.254',
    'auto_ack_current_source_yields_next212' => true,
    'current_source_epoch_next218' => 'app.current.source.epoch.254',
    'current_view_epoch_next218' => 'app.returning.view.epoch.cursor.254',
    'current_trigger_epoch_next218' => 'app.returning.trigger.epoch.cursor.254',
    'auto_ack_current_source_epochs_next218' => true,
    'current_source_ticket_next222' => 'app.current.source.ticket.254',
    'current_view_source_next222' => 'main@view-cookie-254-current',
    'current_trigger_source_next222' => 'main@trigger-cookie-254-current',
    'auto_ack_current_source_tickets_next222' => true,
    'current_source_cursor_source_close' => 'app.returning.current.cursor.254',
    'current_source_close_token_source_close' => 'app.current.source.close.254',
    'current_view_cookie_source_close' => 'main@view-cookie-254-current',
    'current_trigger_cookie_source_close' => 'main@trigger-cookie-254-current',
    'auto_ack_current_source_closures_source_close' => true,
    'current_source_upsert_token_next234' => 'app.current.source.upsert.254',
    'current_upsert_view_cookie_next234' => 'main@view-cookie-254-current',
    'current_upsert_trigger_cookie_next234' => 'main@trigger-cookie-254-current',
    'auto_ack_current_source_upserts_next234' => true,
    'current_source_upsert_action_token_next237' => 'app.current.source.upsert.action.254',
    'current_upsert_action_view_cookie_next237' => 'main@view-cookie-254-current',
    'current_upsert_action_trigger_cookie_next237' => 'main@trigger-cookie-254-current',
    'auto_ack_current_source_upsert_actions_next237' => true,
    'current_source_upsert_close_token_next241' => 'app.current.source.upsert.close.254',
    'current_source_upsert_generation_next241' => 'main@source-generation-254-current',
    'current_upsert_close_view_cookie_next241' => 'main@view-cookie-254-current',
    'current_upsert_close_trigger_cookie_next241' => 'main@trigger-cookie-254-current',
    'auto_ack_current_source_upsert_closes_next241' => true,
    'current_source_upsert_statement_id_next244' => 'app.current.source.upsert.statement.254',
    'current_source_upsert_commit_watermark_next244' => 'app.current.source.upsert.commit.254',
    'current_upsert_commit_view_cookie_next244' => 'main@view-cookie-254-current',
    'current_upsert_commit_trigger_cookie_next244' => 'main@trigger-cookie-254-current',
    'auto_ack_current_source_upsert_commits_next244' => true,
    'current_source_statement_sequence_next247' => 254,
    'next_source_statement_sequence_next247' => 255,
    'current_source_sequence_view_cookie_next247' => 'main@view-cookie-254-current',
    'current_source_sequence_trigger_cookie_next247' => 'main@trigger-cookie-254-current',
    'current_source_sequence_cursor_next247' => 'app.returning.current.sequence.cursor.254',
    'auto_ack_current_source_statement_sequences_next247' => true,
    'current_source_rowid_provenance_token_next250' => 'app.current.source.rowid.provenance.254',
    'auto_ack_current_source_rowid_provenance_next250' => true,
    'current_view_mapping_source_token_next254' => 'main@view-cookie-254-current',
    'current_view_mapping_trigger_token_next254' => 'main@trigger-cookie-254-current',
];

$plan254 = static fn (array $options = [], array $view = null): array => SQLiteTriggerRecursiveViewUpsertCurrentSourceNextPlan::executeCurrentViewMappingReceipt(
    $rows254,
    $currentInput254,
    $nextInput254,
    $view ?? $currentView254,
    $nextView254,
    $returning254,
    $options + $baseOptions254,
);

$receipts254 = static fn (): array => $plan254()['required_current_view_mapping_receipts_next254'];
$released254 = static fn (): array => $plan254(['auto_ack_current_view_mapping_receipts_next254' => true]);
$missing254 = static fn (): array => $plan254(['acknowledged_current_view_mapping_receipts_next254' => array_slice($receipts254(), 0, 1)]);
$unexpectedReceipt254 = 'abcdefabcdefabcdefabcdefabcdefabcdefabcdefabcdefabcd';
$unexpected254 = static fn (): array => $plan254(['acknowledged_current_view_mapping_receipts_next254' => array_merge($receipts254(), [$unexpectedReceipt254])]);
$mappingHeld254 = static fn (): array => $plan254([
    'auto_ack_current_view_mapping_receipts_next254' => true,
    'expected_current_view_mapping_next254' => ['import_id' => 'setting_id', 'name' => 'key_name', 'value' => 'key_value'],
]);
$sourceHeld254 = static fn (): array => $plan254([
    'auto_ack_current_view_mapping_receipts_next254' => true,
    'expected_current_view_mapping_source_token_next254' => 'main@view-cookie-254-stale',
]);
$triggerHeld254 = static fn (): array => $plan254([
    'auto_ack_current_view_mapping_receipts_next254' => true,
    'expected_current_view_mapping_trigger_token_next254' => 'main@trigger-cookie-254-stale',
]);
$columnsHeld254 = static fn (): array => $plan254([
    'auto_ack_current_view_mapping_receipts_next254' => true,
    'required_current_view_mapping_columns_next254' => ['import_id', 'name', 'value', 'missing_column'],
]);
$baseHeld254 = static fn (): array => $plan254([
    'auto_ack_current_view_mapping_receipts_next254' => true,
    'auto_ack_current_source_rowid_provenance_next250' => false,
]);
$custom254 = static fn (): array => $plan254([
    'auto_ack_current_view_mapping_receipts_next254' => true,
    'required_current_view_mapping_columns_next254' => ['name', 'value'],
]);

$cases254 = [
    'released status' => [static fn (): mixed => $released254()['status_next254'], 'trigger-recursive-view-upsert-current-source-next254-view-mapping-released'],
    'missing status' => [static fn (): mixed => $missing254()['status_next254'], 'trigger-recursive-view-upsert-current-source-next254-receipts-missing-held'],
    'unexpected status' => [static fn (): mixed => $unexpected254()['status_next254'], 'trigger-recursive-view-upsert-current-source-next254-receipts-unexpected-held'],
    'mapping held status' => [static fn (): mixed => $mappingHeld254()['status_next254'], 'trigger-recursive-view-upsert-current-source-next254-view-mapping-held'],
    'source held status' => [static fn (): mixed => $sourceHeld254()['status_next254'], 'trigger-recursive-view-upsert-current-source-next254-source-token-held'],
    'trigger held status' => [static fn (): mixed => $triggerHeld254()['status_next254'], 'trigger-recursive-view-upsert-current-source-next254-trigger-token-held'],
    'columns held status' => [static fn (): mixed => $columnsHeld254()['status_next254'], 'trigger-recursive-view-upsert-current-source-next254-columns-held'],
    'base held status' => [static fn (): mixed => $baseHeld254()['status_next254'], 'trigger-recursive-view-upsert-current-source-next254-base-held'],
    'savepoint retained' => [static fn (): mixed => $released254()['savepoint'], 'app_recursive_view_254'],
    'base next250 released' => [static fn (): mixed => $released254()['base']['status_next250'], 'trigger-recursive-view-upsert-current-source-next250-rowid-provenance-released'],
    'base visible released' => [static fn (): mixed => $released254()['base_next_source_visible_next254'], true],
    'base visible held' => [static fn (): mixed => $baseHeld254()['base_next_source_visible_next254'], false],
    'mapping sorted keys' => [static fn (): mixed => array_keys($released254()['current_view_mapping_next254']), ['import_id', 'load_policy_flag', 'name', 'spawn_child', 'value']],
    'expected mapping retained' => [static fn (): mixed => $released254()['expected_current_view_mapping_next254']['name'], 'key_name'],
    'mapping matches released' => [static fn (): mixed => $released254()['current_view_mapping_matches_next254'], true],
    'mapping mismatch detected' => [static fn (): mixed => $mappingHeld254()['current_view_mapping_matches_next254'], false],
    'source token retained' => [static fn (): mixed => $released254()['current_view_mapping_source_token_next254'], 'main@view-cookie-254-current'],
    'source token mismatch detected' => [static fn (): mixed => $sourceHeld254()['current_view_mapping_source_token_matches_next254'], false],
    'trigger token retained' => [static fn (): mixed => $released254()['current_view_mapping_trigger_token_next254'], 'main@trigger-cookie-254-current'],
    'trigger token mismatch detected' => [static fn (): mixed => $triggerHeld254()['current_view_mapping_trigger_token_matches_next254'], false],
    'required columns default' => [static fn (): mixed => $released254()['required_current_view_mapping_columns_next254'], ['import_id', 'name', 'value', 'load_policy_flag']],
    'required columns custom' => [static fn (): mixed => $custom254()['required_current_view_mapping_columns_next254'], ['name', 'value']],
    'missing columns recorded' => [static fn (): mixed => $columnsHeld254()['missing_current_view_mapping_columns_next254'], ['missing_column']],
    'released missing columns empty' => [static fn (): mixed => $released254()['missing_current_view_mapping_columns_next254'], []],
    'mapping row count' => [static fn (): mixed => count($released254()['current_view_mapping_rows_next254']), 2],
    'first mapping name target' => [static fn (): mixed => $released254()['current_view_mapping_rows_next254'][0]['mapping']['name']['target'], 'key_name'],
    'first mapping value payload' => [static fn (): mixed => $released254()['current_view_mapping_rows_next254'][0]['mapping']['value']['value'], 'after-next_child'],
    'first mapping rowid receipt copied' => [static fn (): mixed => is_string($released254()['current_view_mapping_rows_next254'][0]['rowid_receipt']), true],
    'mapping row source token copied' => [static fn (): mixed => $released254()['current_view_mapping_rows_next254'][0]['source_token'], 'main@view-cookie-254-current'],
    'required receipt count' => [static fn (): mixed => count($released254()['required_current_view_mapping_receipts_next254']), 2],
    'receipts are fifty two hex' => [static fn (): mixed => array_map(static fn (string $v): int => preg_match('/^[a-f0-9]{52}$/', $v), $released254()['required_current_view_mapping_receipts_next254']), [1, 1]],
    'auto acknowledged equals required' => [static fn (): mixed => $released254()['acknowledged_current_view_mapping_receipts_next254'], $receipts254()],
    'missing acknowledged count' => [static fn (): mixed => count($missing254()['acknowledged_current_view_mapping_receipts_next254']), 1],
    'missing receipt recorded' => [static fn (): mixed => $missing254()['missing_current_view_mapping_receipts_next254'], [array_slice($receipts254(), -1)[0]]],
    'unexpected receipt recorded' => [static fn (): mixed => $unexpected254()['unexpected_current_view_mapping_receipts_next254'], [$unexpectedReceipt254]],
    'mapping complete released' => [static fn (): mixed => $released254()['current_view_mapping_complete_next254'], true],
    'mapping complete missing false' => [static fn (): mixed => $missing254()['current_view_mapping_complete_next254'], false],
    'mapping complete columns false' => [static fn (): mixed => $columnsHeld254()['current_view_mapping_complete_next254'], false],
    'next visible released' => [static fn (): mixed => $released254()['next_source_visible_after_current_view_mapping_next254'], true],
    'next denied missing' => [static fn (): mixed => $missing254()['next_source_visible_after_current_view_mapping_next254'], false],
    'current row count' => [static fn (): mixed => $released254()['current_source_row_count_next254'], 2],
    'attempted next row count' => [static fn (): mixed => $released254()['attempted_next_source_row_count_next254'], 2],
    'visible released count' => [static fn (): mixed => $released254()['visible_row_count_next254'], 4],
    'held missing count' => [static fn (): mixed => $missing254()['held_next_row_count_next254'], 2],
    'current phases' => [static fn (): mixed => array_values(array_unique(array_column($released254()['current_source_rows_next254'], 'view_mapping_phase_next254'))), ['current-view-mapping']],
    'next phases' => [static fn (): mixed => array_values(array_unique(array_column($released254()['attempted_next_source_rows_next254'], 'view_mapping_phase_next254'))), ['next-source']],
    'current receipts tagged' => [static fn (): mixed => array_column($released254()['current_source_rows_next254'], 'current_view_mapping_receipt_next254'), $receipts254()],
    'next receipts null' => [static fn (): mixed => array_values(array_unique(array_column($released254()['attempted_next_source_rows_next254'], 'current_view_mapping_receipt_next254'))), [null]],
    'current visible while missing' => [static fn (): mixed => array_values(array_unique(array_column($missing254()['current_source_rows_next254'], 'visible_after_current_view_mapping_next254'))), [true]],
    'next held while missing' => [static fn (): mixed => array_values(array_unique(array_column($missing254()['attempted_next_source_rows_next254'], 'visible_after_current_view_mapping_next254'))), [false]],
    'visible payload names released' => [static fn (): mixed => array_column($released254()['visible_returning_payloads_next254'], 'name'), ['app_summary_child', 'template_child', 'landing_url', 'next_module']],
    'held payload names missing' => [static fn (): mixed => array_column($missing254()['held_next_returning_payloads_next254'], 'name'), ['landing_url', 'next_module']],
    'blocked reasons released' => [static fn (): mixed => $released254()['blocked_reasons_next254'], []],
    'blocked reasons missing' => [static fn (): mixed => $missing254()['blocked_reasons_next254'], ['current-view-mapping-receipts-missing']],
    'blocked reasons mapping' => [static fn (): mixed => $mappingHeld254()['blocked_reasons_next254'], ['current-view-mapping-mismatch']],
    'blocked reasons source' => [static fn (): mixed => $sourceHeld254()['blocked_reasons_next254'], ['current-view-mapping-source-token-mismatch']],
    'blocked reasons trigger' => [static fn (): mixed => $triggerHeld254()['blocked_reasons_next254'], ['current-view-mapping-trigger-token-mismatch']],
    'blocked reasons columns' => [static fn (): mixed => $columnsHeld254()['blocked_reasons_next254'], ['current-view-mapping-required-columns-missing']],
    'held row reason copied' => [static fn (): mixed => $missing254()['held_next_source_rows_next254'][0]['held_by_current_view_mapping_reasons_next254'], ['current-view-mapping-receipts-missing']],
    'plan decision released' => [static fn (): mixed => $released254()['current_view_mapping_plan_next254']['decision'], 'publish-next-source-after-current-recursive-view-upsert-mapping'],
    'plan decision held' => [static fn (): mixed => $missing254()['current_view_mapping_plan_next254']['decision'], 'hold-next-source-until-current-recursive-view-upsert-mapping'],
    'plan required echoed' => [static fn (): mixed => $released254()['current_view_mapping_plan_next254']['required_receipts'], $receipts254()],
    'yield boundary released' => [static fn (): mixed => $released254()['yield_boundary_next254'], 'recursive-view-upsert-next254-current-view-mapping-then-next'],
    'yield boundary held' => [static fn (): mixed => $missing254()['yield_boundary_next254'], 'recursive-view-upsert-next254-current-view-mapping-fence-next'],
    'dependency closure marker' => [static fn (): mixed => $released254()['dependency_closure_next254'], 'no-new-support-component-reuses-native-recursive-view-upsert-rowid-provenance-and-adds-current-view-mapping-receipts'],
    'dependency includes next254' => [static fn (): mixed => in_array('sqlite-trigger-recursive-view-upsert-current-source-next254', $released254()['dependencies_next254'], true), true],
    'dependency includes mapping receipts' => [static fn (): mixed => in_array('sqlite-instead-of-view-trigger-current-mapping-receipts', $released254()['dependencies_next254'], true), true],
    'dependency includes next250' => [static fn (): mixed => in_array('sqlite-trigger-recursive-view-upsert-current-source-next250', $released254()['dependencies_next254'], true), true],
    'non overlap mentions next250' => [static fn (): mixed => str_contains($released254()['non_overlap_next254'], 'next250 rowid provenance'), true],
    'bad source token rejected' => [static fn (): mixed => $plan254(['current_view_mapping_source_token_next254' => 'bad token']), InvalidArgumentException::class],
    'bad trigger token rejected' => [static fn (): mixed => $plan254(['current_view_mapping_trigger_token_next254' => 'bad token']), InvalidArgumentException::class],
    'bad required columns rejected' => [static fn (): mixed => $plan254(['required_current_view_mapping_columns_next254' => []]), InvalidArgumentException::class],
    'bad required column name rejected' => [static fn (): mixed => $plan254(['required_current_view_mapping_columns_next254' => ['bad-name']]), InvalidArgumentException::class],
    'bad receipt list rejected' => [static fn (): mixed => $plan254(['acknowledged_current_view_mapping_receipts_next254' => ['x' => $unexpectedReceipt254]]), InvalidArgumentException::class],
    'bad short receipt rejected' => [static fn (): mixed => $plan254(['acknowledged_current_view_mapping_receipts_next254' => ['abc']]), InvalidArgumentException::class],
    'bad non hex receipt rejected' => [static fn (): mixed => $plan254(['acknowledged_current_view_mapping_receipts_next254' => ['zzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzz']]), InvalidArgumentException::class],
];

$tests = [];
foreach ($cases254 as $name => [$callback, $expected]) {
    $tests['trigger recursive view upsert current source next254 ' . $name] = static function (TestRunner $t) use ($callback, $expected): void {
        if (is_string($expected) && is_a($expected, Throwable::class, true)) {
            $t->throws($expected, $callback);
            return;
        }
        $t->same($expected, $callback());
    };
}

return $tests;
