<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteTriggerRecursiveViewReturningCurrentSourceNextPlan;

$rows191 = [
    ['setting_id' => 1, 'key_name' => 'base_url', 'key_value' => 'https://old.test', 'load_policy' => 'yes', 'source' => 'seed'],
    ['setting_id' => 2, 'key_name' => 'landing_url', 'key_value' => 'https://landing_url.test', 'load_policy' => 'yes', 'source' => 'seed'],
];
$currentView191 = [
    'name' => 'app_setting_import_view',
    'source' => 'main@view-cookie-191-current',
    'trigger' => 'app_setting_import_view_io_insert',
    'trigger_source' => 'main@trigger-cookie-191-current',
    'columns' => ['import_id', 'name', 'value', 'load_policy_flag'],
    'mapping' => ['import_id' => 'setting_id', 'name' => 'key_name', 'value' => 'key_value', 'load_policy_flag' => 'load_policy'],
    'recursive_column' => 'name',
    'recursive_suffix' => '_retry',
    'audit_label' => 'current-recursive-trigger-fingerprint-191',
];
$nextView191 = [
    'name' => 'app_setting_import_view',
    'source' => 'main@view-cookie-191-next',
    'trigger' => 'app_setting_import_view_io_insert',
    'trigger_source' => 'main@trigger-cookie-191-next',
    'columns' => ['import_id', 'name', 'value', 'load_policy_flag', 'origin'],
    'mapping' => ['import_id' => 'setting_id', 'name' => 'key_name', 'value' => 'key_value', 'load_policy_flag' => 'load_policy', 'origin' => 'source'],
    'recursive_column' => 'name',
    'recursive_suffix' => '_next_retry',
    'audit_label' => 'next-recursive-trigger-fingerprint-191',
];
$currentInput191 = [
    ['import_id' => 10, 'name' => 'module_seed', 'value' => 'enabled', 'load_policy_flag' => 'yes', 'spawn_child' => true],
    ['import_id' => 11, 'name' => 'skip_me', 'value' => 'disabled', 'load_policy_flag' => 'skip', 'spawn_child' => true],
    ['import_id' => 12, 'name' => 'base_url', 'value' => 'https://current.test', 'load_policy_flag' => 'yes', 'spawn_child' => false],
];
$nextInput191 = [
    ['import_id' => 20, 'name' => 'routing_rules', 'value' => 'cached', 'load_policy_flag' => 'yes', 'origin' => 'next-import', 'spawn_child' => true],
    ['import_id' => 21, 'name' => 'landing_url', 'value' => 'https://next-landing_url.test', 'load_policy_flag' => 'yes', 'origin' => 'next-import', 'spawn_child' => false],
    ['import_id' => 22, 'name' => 'next_skip', 'value' => 'ignored', 'load_policy_flag' => 'skip', 'origin' => 'next-import', 'spawn_child' => true],
];
$returning191 = [
    'new.key_name',
    ['expr' => 'new.key_value', 'as' => 'value'],
    ['expr' => 'old.key_value', 'as' => 'old_value'],
    ['expr' => 'view.name', 'as' => 'view_name'],
    ['expr' => 'event', 'as' => 'event_name'],
    ['expr' => 'ordinal', 'as' => 'ordinal_value'],
    ['expr' => 'depth', 'as' => 'depth_value'],
    ['expr' => 'trigger_source', 'as' => 'trigger_source_alias'],
];

$plan191 = static fn (array $options = []): array => SQLiteTriggerRecursiveViewReturningCurrentSourceNextPlan::executeCurrentSourceFingerprintFence(
    $rows191,
    $currentInput191,
    $nextInput191,
    $currentView191,
    $nextView191,
    $returning191,
    $options + [
        'key' => 'key_name',
        'savepoint' => 'app_recursive_view_191',
        'max_depth' => 2,
        'page_size' => 2,
        'drained_current_pages' => 2,
        'savepoint_action' => 'release',
        'current_source_epoch' => 19,
        'restart_cursor' => 'app-recursive-view-returning-restart-191',
        'snapshot_token' => 'app.recursive.view.returning.snapshot.191',
        'expected_snapshot_token' => 'app.recursive.view.returning.snapshot.191',
        'current_schema_cookie' => 191,
        'expected_current_schema_cookie' => 191,
        'current_source_generation' => 'app.recursive.view.current.191',
        'expected_current_source_generation' => 'app.recursive.view.current.191',
        'trigger_source_generation' => 'app.recursive.trigger.current.191',
        'expected_trigger_source_generation' => 'app.recursive.trigger.current.191',
        'returning_cursor_generation' => 'app.recursive.returning.cursor.191',
        'nested_epoch' => 'app.recursive.view.nested.191',
        'expected_nested_epoch' => 'app.recursive.view.nested.191',
        'required_nested_depths' => [1, 2],
        'drained_nested_depths' => [1, 2],
        'current_watermark' => 'app.recursive.view.current.watermark.191',
        'expected_current_watermark' => 'app.recursive.view.current.watermark.191',
        'auto_ack_current_ordinals' => true,
        'fingerprint_salt' => 'app.recursive.view.returning.fingerprint.191',
        'expected_fingerprint_salt' => 'app.recursive.view.returning.fingerprint.191',
    ],
);

$fingerprints191 = static fn (): array => $plan191()['required_current_fingerprints_next191'];
$released191 = static fn (): array => $plan191(['auto_ack_current_fingerprints' => true]);
$missing191 = static fn (): array => $plan191(['acknowledged_current_fingerprints' => array_slice($fingerprints191(), 0, 3)]);
$unexpected191 = static fn (): array => $plan191(['acknowledged_current_fingerprints' => array_merge($fingerprints191(), ['abcdefabcdefabcdefabcdef'])]);
$reordered191 = static fn (): array => $plan191(['acknowledged_current_fingerprints' => array_reverse($fingerprints191())]);
$reorderedAllowed191 = static fn (): array => $plan191(['acknowledged_current_fingerprints' => array_reverse($fingerprints191()), 'require_fingerprint_order' => false]);
$saltHeld191 = static fn (): array => $plan191(['auto_ack_current_fingerprints' => true, 'expected_fingerprint_salt' => 'app.recursive.view.returning.fingerprint.stale']);
$baseHeld191 = static fn (): array => $plan191(['auto_ack_current_fingerprints' => true, 'drained_nested_depths' => [1]]);
$nonRecursive191 = static fn (): array => $plan191(['recursive_triggers' => false, 'required_nested_depths' => [], 'drained_nested_depths' => [], 'drained_current_pages' => 1, 'auto_ack_current_fingerprints' => true]);

$cases191 = [
    'released status' => [static fn (): mixed => $released191()['status_next191'], 'trigger-recursive-view-returning-current-source-fingerprints-released-next191'],
    'missing status' => [static fn (): mixed => $missing191()['status_next191'], 'trigger-recursive-view-returning-current-source-fingerprints-held-next191'],
    'unexpected status' => [static fn (): mixed => $unexpected191()['status_next191'], 'trigger-recursive-view-returning-current-source-fingerprints-held-next191'],
    'reordered status' => [static fn (): mixed => $reordered191()['status_next191'], 'trigger-recursive-view-returning-current-source-fingerprints-held-next191'],
    'reordered allowed status' => [static fn (): mixed => $reorderedAllowed191()['status_next191'], 'trigger-recursive-view-returning-current-source-fingerprints-released-next191'],
    'salt held status' => [static fn (): mixed => $saltHeld191()['status_next191'], 'trigger-recursive-view-returning-current-source-fingerprints-salt-held-next191'],
    'base held status' => [static fn (): mixed => $baseHeld191()['status_next191'], 'trigger-recursive-view-returning-current-source-fingerprints-base-held-next191'],
    'keeps next188 released' => [static fn (): mixed => $released191()['status_next188'], 'trigger-recursive-view-returning-current-source-watermark-released-next188'],
    'base held keeps next188 base held' => [static fn (): mixed => $baseHeld191()['status_next188'], 'trigger-recursive-view-returning-current-source-watermark-base-held-next188'],
    'fingerprint salt retained' => [static fn (): mixed => $released191()['fingerprint_salt_next191'], 'app.recursive.view.returning.fingerprint.191'],
    'expected salt retained' => [static fn (): mixed => $released191()['expected_fingerprint_salt_next191'], 'app.recursive.view.returning.fingerprint.191'],
    'salt matches' => [static fn (): mixed => $released191()['fingerprint_salt_matches_next191'], true],
    'salt mismatch' => [static fn (): mixed => $saltHeld191()['fingerprint_salt_matches_next191'], false],
    'required fingerprint count' => [static fn (): mixed => count($released191()['required_current_fingerprints_next191']), 4],
    'required fingerprints are 24 hex' => [static fn (): mixed => array_map(static fn (string $v): int => preg_match('/^[a-f0-9]{24}$/', $v), $released191()['required_current_fingerprints_next191']), [1, 1, 1, 1]],
    'auto acknowledged equals required' => [static fn (): mixed => $released191()['acknowledged_current_fingerprints_next191'], $fingerprints191()],
    'missing acknowledged count' => [static fn (): mixed => count($missing191()['acknowledged_current_fingerprints_next191']), 3],
    'missing fingerprint recorded' => [static fn (): mixed => $missing191()['missing_current_fingerprints_next191'], [array_slice($fingerprints191(), -1)[0]]],
    'unexpected fingerprint recorded' => [static fn (): mixed => $unexpected191()['unexpected_current_fingerprints_next191'], ['abcdefabcdefabcdefabcdef']],
    'released missing empty' => [static fn (): mixed => $released191()['missing_current_fingerprints_next191'], []],
    'released unexpected empty' => [static fn (): mixed => $released191()['unexpected_current_fingerprints_next191'], []],
    'order required default' => [static fn (): mixed => $released191()['require_fingerprint_order_next191'], true],
    'order matches released' => [static fn (): mixed => $released191()['current_fingerprint_order_matches_next191'], true],
    'order mismatch detected' => [static fn (): mixed => $reordered191()['current_fingerprint_order_matches_next191'], false],
    'order disabled flag' => [static fn (): mixed => $reorderedAllowed191()['require_fingerprint_order_next191'], false],
    'fingerprint gate released' => [static fn (): mixed => $released191()['current_fingerprint_fence_clear_next191'], true],
    'fingerprint gate missing blocked' => [static fn (): mixed => $missing191()['current_fingerprint_fence_clear_next191'], false],
    'fingerprint gate reordered blocked' => [static fn (): mixed => $reordered191()['current_fingerprint_fence_clear_next191'], false],
    'fingerprint gate reordered allowed' => [static fn (): mixed => $reorderedAllowed191()['current_fingerprint_fence_clear_next191'], true],
    'publish allowed released' => [static fn (): mixed => $released191()['next_source_publish_allowed_next191'], true],
    'publish denied missing' => [static fn (): mixed => $missing191()['next_source_publish_allowed_next191'], false],
    'publish denied salt' => [static fn (): mixed => $saltHeld191()['next_source_publish_allowed_next191'], false],
    'publish denied base held' => [static fn (): mixed => $baseHeld191()['next_source_publish_allowed_next191'], false],
    'current row count' => [static fn (): mixed => $released191()['current_fingerprint_row_count_next191'], 4],
    'attempted next row count' => [static fn (): mixed => $released191()['attempted_next_fingerprint_row_count_next191'], 4],
    'visible released count' => [static fn (): mixed => $released191()['visible_row_count_next191'], 8],
    'held released count' => [static fn (): mixed => $released191()['held_next_row_count_next191'], 0],
    'visible missing count current only' => [static fn (): mixed => $missing191()['visible_row_count_next191'], 4],
    'held missing count next only' => [static fn (): mixed => $missing191()['held_next_row_count_next191'], 4],
    'current phases' => [static fn (): mixed => array_values(array_unique(array_column($released191()['current_fingerprint_rows_next191'], 'fingerprint_phase_next191'))), ['current']],
    'next phases' => [static fn (): mixed => array_values(array_unique(array_column($released191()['attempted_next_fingerprint_rows_next191'], 'fingerprint_phase_next191'))), ['next']],
    'current visible while missing' => [static fn (): mixed => array_values(array_unique(array_column($missing191()['current_fingerprint_rows_next191'], 'visible_after_current_fingerprint_next191'))), [true]],
    'next visible released' => [static fn (): mixed => array_values(array_unique(array_column($released191()['attempted_next_fingerprint_rows_next191'], 'visible_after_current_fingerprint_next191'))), [true]],
    'next held missing' => [static fn (): mixed => array_values(array_unique(array_column($missing191()['attempted_next_fingerprint_rows_next191'], 'visible_after_current_fingerprint_next191'))), [false]],
    'current row fingerprints tagged' => [static fn (): mixed => array_column($released191()['current_fingerprint_rows_next191'], 'current_row_fingerprint_next191'), $fingerprints191()],
    'next row fingerprints null' => [static fn (): mixed => array_values(array_unique(array_column($released191()['attempted_next_fingerprint_rows_next191'], 'current_row_fingerprint_next191'))), [null]],
    'visible payload names released' => [static fn (): mixed => array_column($released191()['visible_returning_payloads_next191'], 'key_name'), ['module_seed', 'base_url', 'module_seed_retry', 'module_seed_retry_retry', 'routing_rules', 'landing_url', 'routing_rules_next_retry', 'routing_rules_next_retry_next_retry']],
    'held payload names missing' => [static fn (): mixed => array_column($missing191()['held_next_returning_payloads_next191'], 'key_name'), ['routing_rules', 'landing_url', 'routing_rules_next_retry', 'routing_rules_next_retry_next_retry']],
    'blocked reasons missing' => [static fn (): mixed => $missing191()['blocked_reasons_next191'], ['current-fingerprint-missing', 'current-fingerprint-order-mismatch']],
    'blocked reasons unexpected' => [static fn (): mixed => $unexpected191()['blocked_reasons_next191'], ['current-fingerprint-unexpected', 'current-fingerprint-order-mismatch']],
    'blocked reasons reordered' => [static fn (): mixed => $reordered191()['blocked_reasons_next191'], ['current-fingerprint-order-mismatch']],
    'blocked reasons salt' => [static fn (): mixed => $saltHeld191()['blocked_reasons_next191'], ['current-fingerprint-salt-mismatch']],
    'blocked reasons base' => [static fn (): mixed => $baseHeld191()['blocked_reasons_next191'], ['nested-recursive-returning-depths-not-drained']],
    'plan decision released' => [static fn (): mixed => $released191()['fingerprint_plan_next191']['decision'], 'publish-next-after-current-row-fingerprints'],
    'plan decision missing' => [static fn (): mixed => $missing191()['fingerprint_plan_next191']['decision'], 'hold-next-until-current-row-fingerprints'],
    'plan base allowed' => [static fn (): mixed => $released191()['fingerprint_plan_next191']['base_publish_allowed'], true],
    'plan base held' => [static fn (): mixed => $baseHeld191()['fingerprint_plan_next191']['base_publish_allowed'], false],
    'plan required echoed' => [static fn (): mixed => $released191()['fingerprint_plan_next191']['required_fingerprints'], $fingerprints191()],
    'plan acknowledged echoed' => [static fn (): mixed => $missing191()['fingerprint_plan_next191']['acknowledged_fingerprints'], array_slice($fingerprints191(), 0, 3)],
    'plan next allowed echoed' => [static fn (): mixed => $released191()['fingerprint_plan_next191']['next_source_publish_allowed'], true],
    'yield boundary released' => [static fn (): mixed => $released191()['yield_boundary_next191'], 'recursive-view-returning-next191-current-fingerprints-then-next'],
    'yield boundary held' => [static fn (): mixed => $missing191()['yield_boundary_next191'], 'recursive-view-returning-next191-current-fingerprints-fence-next'],
    'dependency closure marker' => [static fn (): mixed => $released191()['dependency_closure_next191'], 'no-new-support-component-reuses-native-recursive-view-returning-current-source-row-watermark-and-payload-fingerprint-model'],
    'dependency includes fingerprint implementation' => [static fn (): mixed => in_array('sqlite-trigger-recursive-view-returning-current-source-next191', $released191()['dependencies_next191'], true), true],
    'dependency includes fingerprint implementation' => [static fn (): mixed => in_array('sqlite-returning-current-source-row-fingerprint-fence', $released191()['dependencies_next191'], true), true],
    'dependency includes payload order' => [static fn (): mixed => in_array('sqlite-returning-current-source-payload-order', $released191()['dependencies_next191'], true), true],
    'dependency includes application' => [static fn (): mixed => in_array('application-recursive-view-returning-current-source-next191', $released191()['dependencies_next191'], true), true],
    'non overlap names ordinal fencing' => [static fn (): mixed => str_contains($released191()['non_overlap_next191'], 'next188 ordinal fencing'), true],
    'non recursive current count' => [static fn (): mixed => $nonRecursive191()['current_fingerprint_row_count_next191'], 2],
    'non recursive visible names' => [static fn (): mixed => array_column($nonRecursive191()['visible_returning_payloads_next191'], 'key_name'), ['module_seed', 'base_url', 'routing_rules', 'landing_url']],
    'bad salt throws' => [static fn (): mixed => $plan191(['fingerprint_salt' => 'bad salt']), InvalidArgumentException::class],
    'bad expected salt throws' => [static fn (): mixed => $plan191(['expected_fingerprint_salt' => 'bad salt']), InvalidArgumentException::class],
    'bad fingerprint shape throws' => [static fn (): mixed => $plan191(['acknowledged_current_fingerprints' => ['x' => 'abcdefabcdefabcdefabcdef']]), InvalidArgumentException::class],
    'bad short fingerprint throws' => [static fn (): mixed => $plan191(['acknowledged_current_fingerprints' => ['abc']]), InvalidArgumentException::class],
    'bad non hex fingerprint throws' => [static fn (): mixed => $plan191(['acknowledged_current_fingerprints' => ['zzzzzzzzzzzzzzzzzzzzzzzz']]), InvalidArgumentException::class],
];

$tests = [];
foreach ($cases191 as $name => [$callback, $expected]) {
    $tests['trigger recursive view returning current source fingerprint fence ' . $name] = static function (TestRunner $t) use ($callback, $expected): void {
        if (is_string($expected) && is_a($expected, Throwable::class, true)) {
            $t->throws($expected, $callback);
            return;
        }
        $t->same($expected, $callback());
    };
}

return $tests;
