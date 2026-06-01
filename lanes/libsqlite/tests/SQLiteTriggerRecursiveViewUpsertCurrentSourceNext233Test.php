<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteTriggerRecursiveViewUpsertCurrentSourceNextPlan;

$rows233 = [
    ['setting_id' => 1, 'key_name' => 'base_url', 'key_value' => 'https://old.test', 'load_policy' => 'yes'],
    ['setting_id' => 2, 'key_name' => 'landing_url', 'key_value' => 'https://landing_url.test', 'load_policy' => 'yes'],
];
$currentView233 = [
    'name' => 'app_recursive_setting_import',
    'source' => 'main@view-cookie-233-current',
    'trigger' => 'app_recursive_setting_import_io_insert',
    'trigger_source' => 'main@trigger-cookie-233-current',
    'columns' => ['import_id', 'name', 'value', 'load_policy_flag', 'spawn_child'],
    'mapping' => ['import_id' => 'setting_id', 'name' => 'key_name', 'value' => 'key_value', 'load_policy_flag' => 'load_policy', 'spawn_child' => 'spawn_child'],
    'audit_label' => 'current-recursive-view-upsert-trigger-233',
];
$nextView233 = $currentView233;
$nextView233['source'] = 'main@view-cookie-233-next';
$nextView233['trigger_source'] = 'main@trigger-cookie-233-next';
$postResetView233 = $currentView233;
$postResetView233['source'] = 'main@view-cookie-233-post-reset';
$postResetView233['trigger_source'] = 'main@trigger-cookie-233-post-reset';
$followingView233 = $currentView233;
$followingView233['source'] = 'main@view-cookie-233-following';
$followingView233['trigger_source'] = 'main@trigger-cookie-233-following';
$currentInput233 = [
    ['import_id' => 10, 'name' => 'base_url', 'value' => 'https://current.test', 'load_policy_flag' => 'yes', 'spawn_child' => true],
    ['import_id' => 11, 'name' => 'current_module', 'value' => 'enabled', 'load_policy_flag' => 'no', 'spawn_child' => true],
];
$nextInput233 = [
    ['import_id' => 20, 'name' => 'landing_url', 'value' => 'https://next.test', 'load_policy_flag' => 'yes', 'spawn_child' => true],
    ['import_id' => 21, 'name' => 'next_module', 'value' => 'active', 'load_policy_flag' => 'no', 'spawn_child' => false],
];
$returning233 = [
    ['expr' => 'new.key_name', 'as' => 'name'],
    ['expr' => 'new.key_value', 'as' => 'value'],
    ['expr' => 'event', 'as' => 'event_name'],
    ['expr' => 'depth', 'as' => 'depth_value'],
    ['expr' => 'ordinal', 'as' => 'ordinal_value'],
    ['expr' => 'trigger_source', 'as' => 'trigger_source_alias'],
    ['expr' => 'spawn_child', 'as' => 'spawn_child'],
];

$plan233 = static fn (array $options = []): array => SQLiteTriggerRecursiveViewUpsertCurrentSourceNextPlan::executeCurrentUpsertConflictTarget(
    $rows233,
    $currentInput233,
    $nextInput233,
    $currentView233,
    $nextView233,
    $returning233,
    $options + [
        'key' => 'key_name',
        'savepoint' => 'app_recursive_view_233',
        'cursor_name' => 'app_recursive_view_returning_cursor_233',
        'admit_next_source' => true,
        'rollback_token' => 'app.rollback.current.233',
        'reset_generation' => 'app-current-reset-233',
        'post_reset_current_source_token' => 'app.current.source.postreset.233',
        'post_reset_cursor' => 'app.returning.postreset.cursor.233',
        'post_reset_view' => $postResetView233,
        'post_reset_input' => [
            ['import_id' => 30, 'name' => 'base_url', 'value' => 'https://fresh.test', 'load_policy_flag' => 'yes', 'spawn_child' => false],
            ['import_id' => 31, 'name' => 'routing_rules', 'value' => 'fresh-rules', 'load_policy_flag' => 'no', 'spawn_child' => false],
        ],
        'fresh_acknowledged_ordinals' => [0, 1],
        'next_source_token' => 'app.next.source.233',
        'next_cursor' => 'app.returning.next.cursor.233',
        'next_acknowledged_ordinals' => [0, 1],
        'close_next_cursor' => 'app.returning.next.cursor.233',
        'following_current_source_token' => 'app.current.source.following.233',
        'following_cursor' => 'app.returning.following.cursor.233',
        'following_current_view' => $followingView233,
        'following_current_input' => [
            ['import_id' => 40, 'name' => 'app_summary', 'value' => 'after-next', 'load_policy_flag' => 'yes', 'spawn_child' => true],
            ['import_id' => 41, 'name' => 'theme_style_key', 'value' => 'modern_theme', 'load_policy_flag' => 'yes', 'spawn_child' => false],
            ['import_id' => 42, 'name' => 'template', 'value' => 'modern_theme', 'load_policy_flag' => 'yes', 'spawn_child' => true],
        ],
        'recursive_child_acknowledged_ordinals' => [0, 1],
        'recursive_child_source_token' => 'app.current.source.recursive.child.233',
        'recursive_child_cursor' => 'app.returning.recursive.child.cursor.233',
        'recursive_child_generation' => 'app-recursive-child-current-233',
        'current_generation_next203' => 'app.current.recursive.returning.generation.233',
        'expected_current_generation_next203' => 'app.current.recursive.returning.generation.233',
        'current_handoff_cursor_next203' => 'app.returning.current.handoff.cursor.233',
        'current_generation_commit_marker_next203' => 'app.current.recursive.returning.commit.233',
        'auto_ack_current_generation_receipts_next203' => true,
        'current_source_drain_token_next209' => 'app.current.source.drain.233',
        'current_view_cookie_next209' => 'main@view-cookie-233-current',
        'current_trigger_cookie_next209' => 'main@trigger-cookie-233-current',
        'auto_ack_current_source_watermarks_next209' => true,
        'auto_ack_current_source_yields_next212' => true,
        'current_source_epoch_next218' => 'app.current.source.epoch.233',
        'current_view_epoch_next218' => 'app.returning.view.epoch.cursor.233',
        'current_trigger_epoch_next218' => 'app.returning.trigger.epoch.cursor.233',
        'auto_ack_current_source_epochs_next218' => true,
        'auto_ack_current_returning_source_seals_next224' => true,
        'current_returning_source_generation_next229' => 'app.current.returning.source.generation.233',
        'current_returning_view_generation_next229' => 'main@view-cookie-233-current',
        'current_returning_trigger_generation_next229' => 'main@trigger-cookie-233-current',
        'auto_ack_current_returning_generation_seals_next229' => true,
        'current_upsert_source_token_next233' => 'app.current.upsert.source.233',
        'current_upsert_view_source_next233' => 'main@view-cookie-233-current',
        'current_upsert_trigger_source_next233' => 'main@trigger-cookie-233-current',
        'current_upsert_conflict_target_next233' => ['key_name'],
        'current_upsert_update_columns_next233' => ['key_value', 'load_policy'],
    ],
);

$seals233 = static fn (): array => $plan233()['required_current_upsert_seals_next233'];
$released233 = static fn (): array => $plan233(['auto_ack_current_upsert_seals_next233' => true]);
$missing233 = static fn (): array => $plan233(['acknowledged_current_upsert_seals_next233' => array_slice($seals233(), 0, 1)]);
$unexpectedSeal233 = 'abcdefabcdefabcdefabcdefabcdefabcdefabcdefabcd';
$unexpected233 = static fn (): array => $plan233(['acknowledged_current_upsert_seals_next233' => array_merge($seals233(), [$unexpectedSeal233])]);
$reversed233 = static fn (): array => $plan233(['acknowledged_current_upsert_seals_next233' => array_reverse($seals233())]);
$unordered233 = static fn (): array => $plan233(['require_current_upsert_order_next233' => false, 'acknowledged_current_upsert_seals_next233' => array_reverse($seals233())]);
$tokenHeld233 = static fn (): array => $plan233(['auto_ack_current_upsert_seals_next233' => true, 'expected_current_upsert_source_token_next233' => 'app.current.upsert.source.stale.233']);
$viewHeld233 = static fn (): array => $plan233(['auto_ack_current_upsert_seals_next233' => true, 'expected_current_upsert_view_source_next233' => 'main@view-cookie-233-stale']);
$triggerHeld233 = static fn (): array => $plan233(['auto_ack_current_upsert_seals_next233' => true, 'expected_current_upsert_trigger_source_next233' => 'main@trigger-cookie-233-stale']);
$baseHeld233 = static fn (): array => $plan233(['auto_ack_current_upsert_seals_next233' => true, 'auto_ack_current_returning_generation_seals_next229' => false]);
$custom233 = static fn (): array => $plan233([
    'auto_ack_current_upsert_seals_next233' => true,
    'current_upsert_source_token_next233' => 'app.current.upsert.source.custom.233',
    'current_upsert_view_source_next233' => 'main@view-cookie-233-custom',
    'current_upsert_trigger_source_next233' => 'main@trigger-cookie-233-custom',
    'current_upsert_conflict_target_next233' => ['tenant_id', 'key_name'],
    'current_upsert_update_columns_next233' => ['key_value', 'load_policy', 'updated_at'],
]);

$cases233 = [
    'released status' => [static fn (): mixed => $released233()['status_next233'], 'trigger-recursive-view-upsert-current-source-next233-upsert-sealed'],
    'missing status' => [static fn (): mixed => $missing233()['status_next233'], 'trigger-recursive-view-upsert-current-source-next233-upsert-seal-held'],
    'unexpected status' => [static fn (): mixed => $unexpected233()['status_next233'], 'trigger-recursive-view-upsert-current-source-next233-upsert-seal-held'],
    'reversed status' => [static fn (): mixed => $reversed233()['status_next233'], 'trigger-recursive-view-upsert-current-source-next233-upsert-order-held'],
    'unordered reversed releases' => [static fn (): mixed => $unordered233()['status_next233'], 'trigger-recursive-view-upsert-current-source-next233-upsert-sealed'],
    'token held status' => [static fn (): mixed => $tokenHeld233()['status_next233'], 'trigger-recursive-view-upsert-current-source-next233-upsert-token-held'],
    'view held status' => [static fn (): mixed => $viewHeld233()['status_next233'], 'trigger-recursive-view-upsert-current-source-next233-view-source-held'],
    'trigger held status' => [static fn (): mixed => $triggerHeld233()['status_next233'], 'trigger-recursive-view-upsert-current-source-next233-trigger-source-held'],
    'base held status' => [static fn (): mixed => $baseHeld233()['status_next233'], 'trigger-recursive-view-upsert-current-source-next233-base-held'],
    'savepoint retained' => [static fn (): mixed => $released233()['savepoint'], 'app_recursive_view_233'],
    'base next229 released' => [static fn (): mixed => $released233()['base']['status_next229'], 'trigger-recursive-view-returning-current-source-next229-generation-released'],
    'base visible released' => [static fn (): mixed => $released233()['base_next_source_visible_next233'], true],
    'base visible held' => [static fn (): mixed => $baseHeld233()['base_next_source_visible_next233'], false],
    'upsert token retained' => [static fn (): mixed => $released233()['current_upsert_source_token_next233'], 'app.current.upsert.source.233'],
    'custom upsert token retained' => [static fn (): mixed => $custom233()['current_upsert_source_token_next233'], 'app.current.upsert.source.custom.233'],
    'view source retained' => [static fn (): mixed => $released233()['current_upsert_view_source_next233'], 'main@view-cookie-233-current'],
    'custom view source retained' => [static fn (): mixed => $custom233()['current_upsert_view_source_next233'], 'main@view-cookie-233-custom'],
    'trigger source retained' => [static fn (): mixed => $released233()['current_upsert_trigger_source_next233'], 'main@trigger-cookie-233-current'],
    'custom trigger source retained' => [static fn (): mixed => $custom233()['current_upsert_trigger_source_next233'], 'main@trigger-cookie-233-custom'],
    'conflict target retained' => [static fn (): mixed => $released233()['current_upsert_conflict_target_next233'], ['key_name']],
    'custom conflict target retained' => [static fn (): mixed => $custom233()['current_upsert_conflict_target_next233'], ['tenant_id', 'key_name']],
    'update columns retained' => [static fn (): mixed => $released233()['current_upsert_update_columns_next233'], ['key_value', 'load_policy']],
    'custom update columns retained' => [static fn (): mixed => $custom233()['current_upsert_update_columns_next233'], ['key_value', 'load_policy', 'updated_at']],
    'token matches released' => [static fn (): mixed => $released233()['current_upsert_source_matches_next233'], true],
    'token mismatch detected' => [static fn (): mixed => $tokenHeld233()['current_upsert_source_matches_next233'], false],
    'view matches released' => [static fn (): mixed => $released233()['current_upsert_view_source_matches_next233'], true],
    'view mismatch detected' => [static fn (): mixed => $viewHeld233()['current_upsert_view_source_matches_next233'], false],
    'trigger matches released' => [static fn (): mixed => $released233()['current_upsert_trigger_source_matches_next233'], true],
    'trigger mismatch detected' => [static fn (): mixed => $triggerHeld233()['current_upsert_trigger_source_matches_next233'], false],
    'current upsert event names' => [static fn (): mixed => $released233()['current_upsert_events_next233']['names'], ['app_summary_child', 'template_child']],
    'current upsert inserts counted' => [static fn (): mixed => $released233()['current_upsert_events_next233']['insert'], 2],
    'current upsert updates counted' => [static fn (): mixed => $released233()['current_upsert_events_next233']['update'], 0],
    'current upsert others zero' => [static fn (): mixed => $released233()['current_upsert_events_next233']['other'], 0],
    'current upsert has rows' => [static fn (): mixed => $released233()['current_upsert_has_rows_next233'], true],
    'required seal count' => [static fn (): mixed => count($released233()['required_current_upsert_seals_next233']), 2],
    'upsert seals are 46 hex' => [static fn (): mixed => array_map(static fn (string $v): int => preg_match('/^[a-f0-9]{46}$/', $v), $released233()['required_current_upsert_seals_next233']), [1, 1]],
    'auto acknowledged equals required' => [static fn (): mixed => $released233()['acknowledged_current_upsert_seals_next233'], $seals233()],
    'missing acknowledged count' => [static fn (): mixed => count($missing233()['acknowledged_current_upsert_seals_next233']), 1],
    'missing seal recorded' => [static fn (): mixed => $missing233()['missing_current_upsert_seals_next233'], [array_slice($seals233(), -1)[0]]],
    'unexpected seal recorded' => [static fn (): mixed => $unexpected233()['unexpected_current_upsert_seals_next233'], [$unexpectedSeal233]],
    'released missing empty' => [static fn (): mixed => $released233()['missing_current_upsert_seals_next233'], []],
    'released unexpected empty' => [static fn (): mixed => $released233()['unexpected_current_upsert_seals_next233'], []],
    'require order default' => [static fn (): mixed => $released233()['require_current_upsert_order_next233'], true],
    'order matches released' => [static fn (): mixed => $released233()['current_upsert_order_matches_next233'], true],
    'order mismatch reversed' => [static fn (): mixed => $reversed233()['current_upsert_order_matches_next233'], false],
    'unordered disables order' => [static fn (): mixed => $unordered233()['require_current_upsert_order_next233'], false],
    'unordered order considered matched' => [static fn (): mixed => $unordered233()['current_upsert_order_matches_next233'], true],
    'complete released' => [static fn (): mixed => $released233()['current_upsert_source_complete_next233'], true],
    'complete missing false' => [static fn (): mixed => $missing233()['current_upsert_source_complete_next233'], false],
    'complete unexpected false' => [static fn (): mixed => $unexpected233()['current_upsert_source_complete_next233'], false],
    'complete reversed false' => [static fn (): mixed => $reversed233()['current_upsert_source_complete_next233'], false],
    'next visible released' => [static fn (): mixed => $released233()['next_source_visible_after_current_upsert_source_next233'], true],
    'next denied missing' => [static fn (): mixed => $missing233()['next_source_visible_after_current_upsert_source_next233'], false],
    'next denied unexpected' => [static fn (): mixed => $unexpected233()['next_source_visible_after_current_upsert_source_next233'], false],
    'next denied reversed' => [static fn (): mixed => $reversed233()['next_source_visible_after_current_upsert_source_next233'], false],
    'current row count' => [static fn (): mixed => $released233()['current_source_row_count_next233'], 2],
    'attempted next row count' => [static fn (): mixed => $released233()['attempted_next_source_row_count_next233'], 2],
    'visible released count' => [static fn (): mixed => $released233()['visible_row_count_next233'], 4],
    'held missing count' => [static fn (): mixed => $missing233()['held_next_row_count_next233'], 2],
    'visible payload names released' => [static fn (): mixed => array_column($released233()['visible_returning_payloads_next233'], 'name'), ['app_summary_child', 'template_child', 'landing_url', 'next_module']],
    'held payload names missing' => [static fn (): mixed => array_column($missing233()['held_next_returning_payloads_next233'], 'name'), ['landing_url', 'next_module']],
    'current phases' => [static fn (): mixed => array_values(array_unique(array_column($released233()['current_source_rows_next233'], 'upsert_source_phase_next233'))), ['current']],
    'next phases' => [static fn (): mixed => array_values(array_unique(array_column($released233()['attempted_next_source_rows_next233'], 'upsert_source_phase_next233'))), ['next']],
    'current seals tagged' => [static fn (): mixed => array_column($released233()['current_source_rows_next233'], 'current_upsert_seal_next233'), $seals233()],
    'next seals null' => [static fn (): mixed => array_values(array_unique(array_column($released233()['attempted_next_source_rows_next233'], 'current_upsert_seal_next233'))), [null]],
    'blocked reasons missing' => [static fn (): mixed => $missing233()['blocked_reasons_next233'], ['current-upsert-seal-missing']],
    'blocked reasons unexpected' => [static fn (): mixed => $unexpected233()['blocked_reasons_next233'], ['current-upsert-seal-unexpected']],
    'blocked reasons reversed' => [static fn (): mixed => $reversed233()['blocked_reasons_next233'], ['current-upsert-seal-order-mismatch']],
    'blocked reasons token' => [static fn (): mixed => $tokenHeld233()['blocked_reasons_next233'], ['current-upsert-source-token-mismatch']],
    'blocked reasons view' => [static fn (): mixed => $viewHeld233()['blocked_reasons_next233'], ['current-upsert-view-source-mismatch']],
    'blocked reasons trigger' => [static fn (): mixed => $triggerHeld233()['blocked_reasons_next233'], ['current-upsert-trigger-source-mismatch']],
    'plan decision released' => [static fn (): mixed => $released233()['current_upsert_source_plan_next233']['decision'], 'publish-next-source-after-current-view-upsert-seal'],
    'plan decision missing' => [static fn (): mixed => $missing233()['current_upsert_source_plan_next233']['decision'], 'hold-next-source-until-current-view-upsert-seal'],
    'plan conflict target echoed' => [static fn (): mixed => $released233()['current_upsert_source_plan_next233']['conflict_target'], ['key_name']],
    'plan update columns echoed' => [static fn (): mixed => $released233()['current_upsert_source_plan_next233']['update_columns'], ['key_value', 'load_policy']],
    'boundary released' => [static fn (): mixed => $released233()['yield_boundary_next233'], 'recursive-view-upsert-next233-current-source-sealed-then-next'],
    'boundary held' => [static fn (): mixed => $missing233()['yield_boundary_next233'], 'recursive-view-upsert-next233-current-source-seal-fences-next'],
    'dependency closure marker' => [static fn (): mixed => $released233()['dependency_closure_next233'], 'no-new-support-component-reuses-native-recursive-view-returning-generation-and-adds-current-upsert-source-seal'],
    'dependency includes next233' => [static fn (): mixed => in_array('sqlite-trigger-recursive-view-upsert-current-source-next233', $released233()['dependencies_next233'], true), true],
    'dependency includes upsert seal' => [static fn (): mixed => in_array('sqlite-current-view-upsert-source-seal', $released233()['dependencies_next233'], true), true],
    'dependency includes next229' => [static fn (): mixed => in_array('sqlite-trigger-recursive-view-returning-current-source-next229', $released233()['dependencies_next233'], true), true],
    'non overlap mentions generation seals' => [static fn (): mixed => str_contains($released233()['non_overlap_next233'], 'next229 generation seals'), true],
    'bad upsert token rejected' => [static fn (): mixed => $plan233(['current_upsert_source_token_next233' => 'bad token']), InvalidArgumentException::class],
    'bad conflict target rejected' => [static fn (): mixed => $plan233(['current_upsert_conflict_target_next233' => ['bad column']]), InvalidArgumentException::class],
    'bad update columns rejected' => [static fn (): mixed => $plan233(['current_upsert_update_columns_next233' => []]), InvalidArgumentException::class],
    'bad seal list rejected' => [static fn (): mixed => $plan233(['acknowledged_current_upsert_seals_next233' => ['x' => $unexpectedSeal233]]), InvalidArgumentException::class],
    'bad short seal rejected' => [static fn (): mixed => $plan233(['acknowledged_current_upsert_seals_next233' => ['abc']]), InvalidArgumentException::class],
    'bad non hex seal rejected' => [static fn (): mixed => $plan233(['acknowledged_current_upsert_seals_next233' => ['zzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzz']]), InvalidArgumentException::class],
];

$tests = [];
foreach ($cases233 as $name => [$callback, $expected]) {
    $tests['trigger recursive view upsert current source next233 ' . $name] = static function (TestRunner $t) use ($callback, $expected): void {
        if (is_string($expected) && is_a($expected, Throwable::class, true)) {
            $t->throws($expected, $callback);
            return;
        }
        $t->same($expected, $callback());
    };
}

return $tests;
