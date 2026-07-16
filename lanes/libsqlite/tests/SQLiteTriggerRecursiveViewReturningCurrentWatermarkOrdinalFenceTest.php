<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteTriggerRecursiveViewReturningCurrentSourceNextPlan;

$rows188 = [
    ['setting_id' => 1, 'key_name' => 'base_url', 'key_value' => 'https://old.test', 'load_policy' => 'yes', 'source' => 'seed'],
    ['setting_id' => 2, 'key_name' => 'landing_url', 'key_value' => 'https://landing_url.test', 'load_policy' => 'yes', 'source' => 'seed'],
];
$currentView188 = [
    'name' => 'app_setting_import_view',
    'source' => 'main@view-cookie-188-current',
    'trigger' => 'app_setting_import_view_io_insert',
    'trigger_source' => 'main@trigger-cookie-188-current',
    'columns' => ['import_id', 'name', 'value', 'load_policy_flag'],
    'mapping' => ['import_id' => 'setting_id', 'name' => 'key_name', 'value' => 'key_value', 'load_policy_flag' => 'load_policy'],
    'recursive_column' => 'name',
    'recursive_suffix' => '_retry',
    'audit_label' => 'current-recursive-trigger-watermark-188',
];
$nextView188 = [
    'name' => 'app_setting_import_view',
    'source' => 'main@view-cookie-188-next',
    'trigger' => 'app_setting_import_view_io_insert',
    'trigger_source' => 'main@trigger-cookie-188-next',
    'columns' => ['import_id', 'name', 'value', 'load_policy_flag', 'origin'],
    'mapping' => ['import_id' => 'setting_id', 'name' => 'key_name', 'value' => 'key_value', 'load_policy_flag' => 'load_policy', 'origin' => 'source'],
    'recursive_column' => 'name',
    'recursive_suffix' => '_next_retry',
    'audit_label' => 'next-recursive-trigger-watermark-188',
];
$currentInput188 = [
    ['import_id' => 10, 'name' => 'module_seed', 'value' => 'enabled', 'load_policy_flag' => 'yes', 'spawn_child' => true],
    ['import_id' => 11, 'name' => 'skip_me', 'value' => 'disabled', 'load_policy_flag' => 'skip', 'spawn_child' => true],
    ['import_id' => 12, 'name' => 'base_url', 'value' => 'https://current.test', 'load_policy_flag' => 'yes', 'spawn_child' => false],
];
$nextInput188 = [
    ['import_id' => 20, 'name' => 'routing_rules', 'value' => 'cached', 'load_policy_flag' => 'yes', 'origin' => 'next-import', 'spawn_child' => true],
    ['import_id' => 21, 'name' => 'landing_url', 'value' => 'https://next-landing_url.test', 'load_policy_flag' => 'yes', 'origin' => 'next-import', 'spawn_child' => false],
    ['import_id' => 22, 'name' => 'next_skip', 'value' => 'ignored', 'load_policy_flag' => 'skip', 'origin' => 'next-import', 'spawn_child' => true],
];
$returning188 = [
    'new.key_name',
    ['expr' => 'new.key_value', 'as' => 'value'],
    ['expr' => 'old.key_value', 'as' => 'old_value'],
    ['expr' => 'view.name', 'as' => 'view_name'],
    ['expr' => 'event', 'as' => 'event_name'],
    ['expr' => 'ordinal', 'as' => 'ordinal_value'],
    ['expr' => 'depth', 'as' => 'depth_value'],
    ['expr' => 'trigger_source', 'as' => 'trigger_source_alias'],
];

$plan188 = static fn (array $options = []): array => SQLiteTriggerRecursiveViewReturningCurrentSourceNextPlan::executeCurrentWatermarkOrdinalFence(
    $rows188,
    $currentInput188,
    $nextInput188,
    $currentView188,
    $nextView188,
    $returning188,
    $options + [
        'key' => 'key_name',
        'savepoint' => 'app_recursive_view_188',
        'max_depth' => 2,
        'page_size' => 2,
        'drained_current_pages' => 2,
        'savepoint_action' => 'release',
        'current_source_epoch' => 18,
        'restart_cursor' => 'app-recursive-view-returning-restart-188',
        'snapshot_token' => 'app.recursive.view.returning.snapshot.188',
        'expected_snapshot_token' => 'app.recursive.view.returning.snapshot.188',
        'current_schema_cookie' => 188,
        'expected_current_schema_cookie' => 188,
        'current_source_generation' => 'app.recursive.view.current.188',
        'expected_current_source_generation' => 'app.recursive.view.current.188',
        'trigger_source_generation' => 'app.recursive.trigger.current.188',
        'expected_trigger_source_generation' => 'app.recursive.trigger.current.188',
        'returning_cursor_generation' => 'app.recursive.returning.cursor.188',
        'nested_epoch' => 'app.recursive.view.nested.188',
        'expected_nested_epoch' => 'app.recursive.view.nested.188',
        'required_nested_depths' => [1, 2],
        'drained_nested_depths' => [1, 2],
        'current_watermark' => 'app.recursive.view.current.watermark.188',
        'expected_current_watermark' => 'app.recursive.view.current.watermark.188',
    ],
);

$released188 = static fn (): array => $plan188(['auto_ack_current_ordinals' => true]);
$missing188 = static fn (): array => $plan188(['acknowledged_current_ordinals' => [0, 1, 2]]);
$unexpected188 = static fn (): array => $plan188(['acknowledged_current_ordinals' => [0, 1, 2, 3, 9]]);
$tokenHeld188 = static fn (): array => $plan188(['auto_ack_current_ordinals' => true, 'expected_current_watermark' => 'app.recursive.view.current.watermark.stale']);
$baseHeld188 = static fn (): array => $plan188(['auto_ack_current_ordinals' => true, 'drained_nested_depths' => [1]]);
$nonContiguousAllowed188 = static fn (): array => $plan188(['acknowledged_current_ordinals' => [0, 1, 2, 3], 'require_contiguous_ordinals' => false]);
$nonRecursive188 = static fn (): array => $plan188(['recursive_triggers' => false, 'required_nested_depths' => [], 'drained_nested_depths' => [], 'drained_current_pages' => 1, 'auto_ack_current_ordinals' => true]);

$cases188 = [
    'released status' => [static fn (): mixed => $released188()['status_next188'], 'trigger-recursive-view-returning-current-source-watermark-released-next188'],
    'missing status' => [static fn (): mixed => $missing188()['status_next188'], 'trigger-recursive-view-returning-current-source-watermark-ordinal-held-next188'],
    'unexpected status' => [static fn (): mixed => $unexpected188()['status_next188'], 'trigger-recursive-view-returning-current-source-watermark-ordinal-held-next188'],
    'token status' => [static fn (): mixed => $tokenHeld188()['status_next188'], 'trigger-recursive-view-returning-current-source-watermark-token-held-next188'],
    'base held status' => [static fn (): mixed => $baseHeld188()['status_next188'], 'trigger-recursive-view-returning-current-source-watermark-base-held-next188'],
    'non recursive released status' => [static fn (): mixed => $nonRecursive188()['status_next188'], 'trigger-recursive-view-returning-current-source-watermark-released-next188'],
    'keeps next185 released' => [static fn (): mixed => $released188()['status_next185'], 'trigger-recursive-view-returning-current-source-nested-drained-next185'],
    'base held keeps next185 held' => [static fn (): mixed => $baseHeld188()['status_next185'], 'trigger-recursive-view-returning-current-source-nested-held-next185'],
    'watermark retained' => [static fn (): mixed => $released188()['current_watermark_next188'], 'app.recursive.view.current.watermark.188'],
    'expected watermark retained' => [static fn (): mixed => $released188()['expected_current_watermark_next188'], 'app.recursive.view.current.watermark.188'],
    'watermark matches' => [static fn (): mixed => $released188()['current_watermark_matches_next188'], true],
    'stale watermark mismatch' => [static fn (): mixed => $tokenHeld188()['current_watermark_matches_next188'], false],
    'required ordinals' => [static fn (): mixed => $released188()['required_current_ordinals_next188'], [0, 1, 2, 3]],
    'auto ack ordinals' => [static fn (): mixed => $released188()['acknowledged_current_ordinals_next188'], [0, 1, 2, 3]],
    'missing ack ordinals' => [static fn (): mixed => $missing188()['acknowledged_current_ordinals_next188'], [0, 1, 2]],
    'missing ordinal recorded' => [static fn (): mixed => $missing188()['missing_current_ordinals_next188'], [3]],
    'unexpected ordinal recorded' => [static fn (): mixed => $unexpected188()['unexpected_current_ordinals_next188'], [9]],
    'released missing empty' => [static fn (): mixed => $released188()['missing_current_ordinals_next188'], []],
    'released unexpected empty' => [static fn (): mixed => $released188()['unexpected_current_ordinals_next188'], []],
    'contiguous required default' => [static fn (): mixed => $released188()['require_contiguous_ordinals_next188'], true],
    'released contiguous' => [static fn (): mixed => $released188()['current_ordinals_contiguous_next188'], true],
    'missing still contiguous prefix false' => [static fn (): mixed => $missing188()['current_ordinals_contiguous_next188'], false],
    'ordinal fence clear released' => [static fn (): mixed => $released188()['current_ordinal_fence_clear_next188'], true],
    'ordinal fence blocked missing' => [static fn (): mixed => $missing188()['current_ordinal_fence_clear_next188'], false],
    'non contiguous flag disabled' => [static fn (): mixed => $nonContiguousAllowed188()['require_contiguous_ordinals_next188'], false],
    'non contiguous allowed clears' => [static fn (): mixed => $nonContiguousAllowed188()['current_ordinal_fence_clear_next188'], true],
    'publish allowed released' => [static fn (): mixed => $released188()['next_source_publish_allowed_next188'], true],
    'publish denied missing' => [static fn (): mixed => $missing188()['next_source_publish_allowed_next188'], false],
    'publish denied token' => [static fn (): mixed => $tokenHeld188()['next_source_publish_allowed_next188'], false],
    'publish denied base held' => [static fn (): mixed => $baseHeld188()['next_source_publish_allowed_next188'], false],
    'current row count' => [static fn (): mixed => $released188()['current_watermark_row_count_next188'], 4],
    'attempted next row count' => [static fn (): mixed => $released188()['attempted_next_watermark_row_count_next188'], 4],
    'visible released count' => [static fn (): mixed => $released188()['visible_row_count_next188'], 8],
    'blocked released count' => [static fn (): mixed => $released188()['blocked_next_row_count_next188'], 0],
    'visible missing count current only' => [static fn (): mixed => $missing188()['visible_row_count_next188'], 4],
    'blocked missing count next only' => [static fn (): mixed => $missing188()['blocked_next_row_count_next188'], 4],
    'current watermark phases' => [static fn (): mixed => array_values(array_unique(array_column($released188()['current_watermark_rows_next188'], 'watermark_phase_next188'))), ['current']],
    'next watermark phases' => [static fn (): mixed => array_values(array_unique(array_column($released188()['attempted_next_watermark_rows_next188'], 'watermark_phase_next188'))), ['next']],
    'current visible flags' => [static fn (): mixed => array_values(array_unique(array_column($missing188()['current_watermark_rows_next188'], 'visible_after_current_watermark_next188'))), [true]],
    'next visible flags released' => [static fn (): mixed => array_values(array_unique(array_column($released188()['attempted_next_watermark_rows_next188'], 'visible_after_current_watermark_next188'))), [true]],
    'next visible flags missing' => [static fn (): mixed => array_values(array_unique(array_column($missing188()['attempted_next_watermark_rows_next188'], 'visible_after_current_watermark_next188'))), [false]],
    'visible payload names released' => [static fn (): mixed => array_column($released188()['visible_returning_payloads_next188'], 'key_name'), ['module_seed', 'base_url', 'module_seed_retry', 'module_seed_retry_retry', 'routing_rules', 'landing_url', 'routing_rules_next_retry', 'routing_rules_next_retry_next_retry']],
    'blocked payload names missing' => [static fn (): mixed => array_column($missing188()['blocked_next_returning_payloads_next188'], 'key_name'), ['routing_rules', 'landing_url', 'routing_rules_next_retry', 'routing_rules_next_retry_next_retry']],
    'blocked reasons missing' => [static fn (): mixed => $missing188()['blocked_reasons_next188'], ['current-watermark-ordinal-missing', 'current-watermark-ordinal-gap']],
    'blocked reasons unexpected' => [static fn (): mixed => $unexpected188()['blocked_reasons_next188'], ['current-watermark-ordinal-unexpected', 'current-watermark-ordinal-gap']],
    'blocked reasons token' => [static fn (): mixed => $tokenHeld188()['blocked_reasons_next188'], ['current-watermark-token-mismatch']],
    'blocked reasons base' => [static fn (): mixed => $baseHeld188()['blocked_reasons_next188'], ['nested-recursive-returning-depths-not-drained']],
    'plan decision released' => [static fn (): mixed => $released188()['watermark_plan_next188']['decision'], 'publish-next-after-current-row-watermark'],
    'plan decision missing' => [static fn (): mixed => $missing188()['watermark_plan_next188']['decision'], 'hold-next-until-current-row-watermark'],
    'plan base allowed' => [static fn (): mixed => $released188()['watermark_plan_next188']['base_publish_allowed'], true],
    'plan base held' => [static fn (): mixed => $baseHeld188()['watermark_plan_next188']['base_publish_allowed'], false],
    'plan ordinals echoed' => [static fn (): mixed => $released188()['watermark_plan_next188']['required_ordinals'], [0, 1, 2, 3]],
    'plan ack echoed' => [static fn (): mixed => $missing188()['watermark_plan_next188']['acknowledged_ordinals'], [0, 1, 2]],
    'plan next allowed echoed' => [static fn (): mixed => $released188()['watermark_plan_next188']['next_source_publish_allowed'], true],
    'yield boundary released' => [static fn (): mixed => $released188()['yield_boundary_next188'], 'recursive-view-returning-next188-current-row-watermark-then-next'],
    'yield boundary held' => [static fn (): mixed => $missing188()['yield_boundary_next188'], 'recursive-view-returning-next188-current-row-watermark-fences-next'],
    'dependency closure marker' => [static fn (): mixed => $released188()['dependency_closure_next188'], 'no-new-support-component-reuses-native-recursive-view-returning-current-source-generation-and-nested-depth-drain-model'],
    'dependency includes next188' => [static fn (): mixed => in_array('sqlite-trigger-recursive-view-returning-current-source-next188', $released188()['dependencies_next188'], true), true],
    'dependency includes watermark fence' => [static fn (): mixed => in_array('sqlite-returning-current-source-row-watermark-fence', $released188()['dependencies_next188'], true), true],
    'dependency includes ordinal contiguity' => [static fn (): mixed => in_array('sqlite-returning-current-source-ordinal-contiguity', $released188()['dependencies_next188'], true), true],
    'dependency includes application' => [static fn (): mixed => in_array('application-recursive-view-returning-current-source-next188', $released188()['dependencies_next188'], true), true],
    'non overlap names checkpoint ack' => [static fn (): mixed => str_contains($released188()['non_overlap_next188'], 'next184 checkpoint'), true],
    'non recursive current count' => [static fn (): mixed => $nonRecursive188()['current_watermark_row_count_next188'], 2],
    'non recursive visible names' => [static fn (): mixed => array_column($nonRecursive188()['visible_returning_payloads_next188'], 'key_name'), ['module_seed', 'base_url', 'routing_rules', 'landing_url']],
    'bad watermark throws' => [static fn (): mixed => $plan188(['current_watermark' => 'bad watermark']), InvalidArgumentException::class],
    'bad expected watermark throws' => [static fn (): mixed => $plan188(['expected_current_watermark' => 'bad watermark']), InvalidArgumentException::class],
    'bad ordinals shape throws' => [static fn (): mixed => $plan188(['acknowledged_current_ordinals' => ['x' => 1]]), InvalidArgumentException::class],
    'bad negative ordinal throws' => [static fn (): mixed => $plan188(['acknowledged_current_ordinals' => [-1]]), InvalidArgumentException::class],
    'bad string ordinal throws' => [static fn (): mixed => $plan188(['acknowledged_current_ordinals' => ['one']]), InvalidArgumentException::class],
];

$tests = [];
foreach ($cases188 as $name => [$callback, $expected]) {
    $tests['trigger recursive view returning current source next188 ' . $name] = static function (TestRunner $t) use ($callback, $expected): void {
        if (is_string($expected) && is_a($expected, Throwable::class, true)) {
            $t->throws($expected, $callback);
            return;
        }
        $t->same($expected, $callback());
    };
}

return $tests;
