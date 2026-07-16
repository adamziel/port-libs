<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteTriggerRecursiveViewReturningCurrentSourceNextPlan;

$rows195 = [
    ['setting_id' => 1, 'key_name' => 'base_url', 'key_value' => 'https://old.test', 'load_policy' => 'yes', 'source' => 'seed'],
    ['setting_id' => 2, 'key_name' => 'landing_url', 'key_value' => 'https://landing_url.test', 'load_policy' => 'yes', 'source' => 'seed'],
];
$currentView195 = [
    'name' => 'app_setting_import_view',
    'source' => 'main@view-cookie-195-current',
    'trigger' => 'app_setting_import_view_io_insert',
    'trigger_source' => 'main@trigger-cookie-195-current',
    'columns' => ['import_id', 'name', 'value', 'load_policy_flag'],
    'mapping' => ['import_id' => 'setting_id', 'name' => 'key_name', 'value' => 'key_value', 'load_policy_flag' => 'load_policy'],
    'recursive_column' => 'name',
    'recursive_suffix' => '_retry',
    'audit_label' => 'current-recursive-trigger-receipt-195',
];
$nextView195 = [
    'name' => 'app_setting_import_view',
    'source' => 'main@view-cookie-195-next',
    'trigger' => 'app_setting_import_view_io_insert',
    'trigger_source' => 'main@trigger-cookie-195-next',
    'columns' => ['import_id', 'name', 'value', 'load_policy_flag', 'origin'],
    'mapping' => ['import_id' => 'setting_id', 'name' => 'key_name', 'value' => 'key_value', 'load_policy_flag' => 'load_policy', 'origin' => 'source'],
    'recursive_column' => 'name',
    'recursive_suffix' => '_next_retry',
    'audit_label' => 'next-recursive-trigger-receipt-195',
];
$currentInput195 = [
    ['import_id' => 10, 'name' => 'module_seed', 'value' => 'enabled', 'load_policy_flag' => 'yes', 'spawn_child' => true],
    ['import_id' => 11, 'name' => 'skip_me', 'value' => 'disabled', 'load_policy_flag' => 'skip', 'spawn_child' => true],
    ['import_id' => 12, 'name' => 'base_url', 'value' => 'https://current.test', 'load_policy_flag' => 'yes', 'spawn_child' => false],
];
$nextInput195 = [
    ['import_id' => 20, 'name' => 'routing_rules', 'value' => 'cached', 'load_policy_flag' => 'yes', 'origin' => 'next-import', 'spawn_child' => true],
    ['import_id' => 21, 'name' => 'landing_url', 'value' => 'https://next-landing_url.test', 'load_policy_flag' => 'yes', 'origin' => 'next-import', 'spawn_child' => false],
    ['import_id' => 22, 'name' => 'next_skip', 'value' => 'ignored', 'load_policy_flag' => 'skip', 'origin' => 'next-import', 'spawn_child' => true],
];
$returning195 = [
    'new.key_name',
    ['expr' => 'new.key_value', 'as' => 'value'],
    ['expr' => 'old.key_value', 'as' => 'old_value'],
    ['expr' => 'view.name', 'as' => 'view_name'],
    ['expr' => 'event', 'as' => 'event_name'],
    ['expr' => 'ordinal', 'as' => 'ordinal_value'],
    ['expr' => 'depth', 'as' => 'depth_value'],
    ['expr' => 'trigger_source', 'as' => 'trigger_source_alias'],
];

$plan195 = static fn (array $options = []): array => SQLiteTriggerRecursiveViewReturningCurrentSourceNextPlan::executeCurrentSourceReceiptResumeFence(
    $rows195,
    $currentInput195,
    $nextInput195,
    $currentView195,
    $nextView195,
    $returning195,
    $options + [
        'key' => 'key_name',
        'savepoint' => 'app_recursive_view_195',
        'max_depth' => 2,
        'page_size' => 2,
        'drained_current_pages' => 2,
        'savepoint_action' => 'release',
        'current_source_epoch' => 19,
        'restart_cursor' => 'app-recursive-view-returning-restart-195',
        'snapshot_token' => 'app.recursive.view.returning.snapshot.195',
        'expected_snapshot_token' => 'app.recursive.view.returning.snapshot.195',
        'current_schema_cookie' => 195,
        'expected_current_schema_cookie' => 195,
        'current_source_generation' => 'app.recursive.view.current.195',
        'expected_current_source_generation' => 'app.recursive.view.current.195',
        'trigger_source_generation' => 'app.recursive.trigger.current.195',
        'expected_trigger_source_generation' => 'app.recursive.trigger.current.195',
        'returning_cursor_generation' => 'app.recursive.returning.cursor.195',
        'nested_epoch' => 'app.recursive.view.nested.195',
        'expected_nested_epoch' => 'app.recursive.view.nested.195',
        'required_nested_depths' => [1, 2],
        'drained_nested_depths' => [1, 2],
        'current_watermark' => 'app.recursive.view.current.watermark.195',
        'expected_current_watermark' => 'app.recursive.view.current.watermark.195',
        'auto_ack_current_ordinals' => true,
        'fingerprint_salt' => 'app.recursive.view.returning.fingerprint.195',
        'expected_fingerprint_salt' => 'app.recursive.view.returning.fingerprint.195',
        'auto_ack_current_fingerprints' => true,
        'current_source_token_source_resume' => 'app.recursive.view.current.source.195',
        'expected_current_source_token_source_resume' => 'app.recursive.view.current.source.195',
        'next_resume_token_source_resume' => 'app.recursive.view.next.resume.195',
        'expected_next_resume_token_source_resume' => 'app.recursive.view.next.resume.195',
    ],
);

$receipts195 = static fn (): array => $plan195()['required_current_source_receipts_source_resume'];
$released195 = static fn (): array => $plan195(['auto_ack_current_source_receipts_source_resume' => true]);
$missing195 = static fn (): array => $plan195(['acknowledged_current_source_receipts_source_resume' => array_slice($receipts195(), 0, 3)]);
$unexpected195 = static fn (): array => $plan195(['acknowledged_current_source_receipts_source_resume' => array_merge($receipts195(), ['abcdefabcdefabcdefabcdefabcd'])]);
$reordered195 = static fn (): array => $plan195(['acknowledged_current_source_receipts_source_resume' => array_reverse($receipts195())]);
$reorderedAllowed195 = static fn (): array => $plan195(['acknowledged_current_source_receipts_source_resume' => array_reverse($receipts195()), 'require_receipt_order_source_resume' => false]);
$sourceHeld195 = static fn (): array => $plan195(['auto_ack_current_source_receipts_source_resume' => true, 'expected_current_source_token_source_resume' => 'app.recursive.view.current.source.stale.195']);
$resumeHeld195 = static fn (): array => $plan195(['auto_ack_current_source_receipts_source_resume' => true, 'expected_next_resume_token_source_resume' => 'app.recursive.view.next.resume.stale.195']);
$baseHeld195 = static fn (): array => $plan195(['auto_ack_current_source_receipts_source_resume' => true, 'auto_ack_current_fingerprints' => false, 'acknowledged_current_fingerprints' => array_slice($plan195()['required_current_fingerprints_next191'], 0, 2)]);
$nonRecursive195 = static fn (): array => $plan195(['recursive_triggers' => false, 'required_nested_depths' => [], 'drained_nested_depths' => [], 'drained_current_pages' => 1, 'auto_ack_current_source_receipts_source_resume' => true]);

$cases195 = [
    'released status' => [static fn (): mixed => $released195()['status_source_resume'], 'trigger-recursive-view-returning-current-source-receipts-released-source_resume'],
    'missing status' => [static fn (): mixed => $missing195()['status_source_resume'], 'trigger-recursive-view-returning-current-source-receipts-held-source_resume'],
    'unexpected status' => [static fn (): mixed => $unexpected195()['status_source_resume'], 'trigger-recursive-view-returning-current-source-receipts-held-source_resume'],
    'reordered status' => [static fn (): mixed => $reordered195()['status_source_resume'], 'trigger-recursive-view-returning-current-source-receipts-held-source_resume'],
    'reordered allowed status' => [static fn (): mixed => $reorderedAllowed195()['status_source_resume'], 'trigger-recursive-view-returning-current-source-receipts-released-source_resume'],
    'source held status' => [static fn (): mixed => $sourceHeld195()['status_source_resume'], 'trigger-recursive-view-returning-current-source-receipts-source-held-source_resume'],
    'resume held status' => [static fn (): mixed => $resumeHeld195()['status_source_resume'], 'trigger-recursive-view-returning-current-source-receipts-resume-held-source_resume'],
    'base held status' => [static fn (): mixed => $baseHeld195()['status_source_resume'], 'trigger-recursive-view-returning-current-source-receipts-base-held-source_resume'],
    'base next191 released' => [static fn (): mixed => $released195()['status_next191'], 'trigger-recursive-view-returning-current-source-fingerprints-released-next191'],
    'base held keeps next191 held' => [static fn (): mixed => $baseHeld195()['status_next191'], 'trigger-recursive-view-returning-current-source-fingerprints-held-next191'],
    'source token retained' => [static fn (): mixed => $released195()['current_source_token_source_resume'], 'app.recursive.view.current.source.195'],
    'expected source token retained' => [static fn (): mixed => $released195()['expected_current_source_token_source_resume'], 'app.recursive.view.current.source.195'],
    'source token matches' => [static fn (): mixed => $released195()['current_source_token_matches_source_resume'], true],
    'source token mismatch' => [static fn (): mixed => $sourceHeld195()['current_source_token_matches_source_resume'], false],
    'resume token retained' => [static fn (): mixed => $released195()['next_resume_token_source_resume'], 'app.recursive.view.next.resume.195'],
    'expected resume token retained' => [static fn (): mixed => $released195()['expected_next_resume_token_source_resume'], 'app.recursive.view.next.resume.195'],
    'resume token matches' => [static fn (): mixed => $released195()['next_resume_token_matches_source_resume'], true],
    'resume token mismatch' => [static fn (): mixed => $resumeHeld195()['next_resume_token_matches_source_resume'], false],
    'required receipt count' => [static fn (): mixed => count($released195()['required_current_source_receipts_source_resume']), 4],
    'required receipts are 28 hex' => [static fn (): mixed => array_map(static fn (string $v): int => preg_match('/^[a-f0-9]{28}$/', $v), $released195()['required_current_source_receipts_source_resume']), [1, 1, 1, 1]],
    'auto acknowledged equals required' => [static fn (): mixed => $released195()['acknowledged_current_source_receipts_source_resume'], $receipts195()],
    'missing acknowledged count' => [static fn (): mixed => count($missing195()['acknowledged_current_source_receipts_source_resume']), 3],
    'missing receipt recorded' => [static fn (): mixed => $missing195()['missing_current_source_receipts_source_resume'], [array_slice($receipts195(), -1)[0]]],
    'unexpected receipt recorded' => [static fn (): mixed => $unexpected195()['unexpected_current_source_receipts_source_resume'], ['abcdefabcdefabcdefabcdefabcd']],
    'released missing empty' => [static fn (): mixed => $released195()['missing_current_source_receipts_source_resume'], []],
    'released unexpected empty' => [static fn (): mixed => $released195()['unexpected_current_source_receipts_source_resume'], []],
    'order required default' => [static fn (): mixed => $released195()['require_receipt_order_source_resume'], true],
    'order matches released' => [static fn (): mixed => $released195()['current_source_receipt_order_matches_source_resume'], true],
    'order mismatch detected' => [static fn (): mixed => $reordered195()['current_source_receipt_order_matches_source_resume'], false],
    'order disabled flag' => [static fn (): mixed => $reorderedAllowed195()['require_receipt_order_source_resume'], false],
    'receipt fence released' => [static fn (): mixed => $released195()['current_source_receipt_fence_clear_source_resume'], true],
    'receipt fence missing blocked' => [static fn (): mixed => $missing195()['current_source_receipt_fence_clear_source_resume'], false],
    'receipt fence reordered blocked' => [static fn (): mixed => $reordered195()['current_source_receipt_fence_clear_source_resume'], false],
    'receipt fence reordered allowed' => [static fn (): mixed => $reorderedAllowed195()['current_source_receipt_fence_clear_source_resume'], true],
    'resume allowed released' => [static fn (): mixed => $released195()['next_source_resume_allowed_source_resume'], true],
    'resume denied missing' => [static fn (): mixed => $missing195()['next_source_resume_allowed_source_resume'], false],
    'resume denied source' => [static fn (): mixed => $sourceHeld195()['next_source_resume_allowed_source_resume'], false],
    'resume denied resume token' => [static fn (): mixed => $resumeHeld195()['next_source_resume_allowed_source_resume'], false],
    'resume denied base held' => [static fn (): mixed => $baseHeld195()['next_source_resume_allowed_source_resume'], false],
    'current row count' => [static fn (): mixed => $released195()['current_source_receipt_row_count_source_resume'], 4],
    'attempted next row count' => [static fn (): mixed => $released195()['attempted_next_source_receipt_row_count_source_resume'], 4],
    'visible released count' => [static fn (): mixed => $released195()['visible_row_count_source_resume'], 8],
    'held released count' => [static fn (): mixed => $released195()['held_next_row_count_source_resume'], 0],
    'visible missing count current only' => [static fn (): mixed => $missing195()['visible_row_count_source_resume'], 4],
    'held missing count next only' => [static fn (): mixed => $missing195()['held_next_row_count_source_resume'], 4],
    'current phases' => [static fn (): mixed => array_values(array_unique(array_column($released195()['current_source_receipt_rows_source_resume'], 'receipt_phase_source_resume'))), ['current']],
    'next phases' => [static fn (): mixed => array_values(array_unique(array_column($released195()['attempted_next_source_receipt_rows_source_resume'], 'receipt_phase_source_resume'))), ['next']],
    'current visible while missing' => [static fn (): mixed => array_values(array_unique(array_column($missing195()['current_source_receipt_rows_source_resume'], 'visible_after_current_source_receipts_source_resume'))), [true]],
    'next visible released' => [static fn (): mixed => array_values(array_unique(array_column($released195()['attempted_next_source_receipt_rows_source_resume'], 'visible_after_current_source_receipts_source_resume'))), [true]],
    'next held missing' => [static fn (): mixed => array_values(array_unique(array_column($missing195()['attempted_next_source_receipt_rows_source_resume'], 'visible_after_current_source_receipts_source_resume'))), [false]],
    'current row receipts tagged' => [static fn (): mixed => array_column($released195()['current_source_receipt_rows_source_resume'], 'current_source_receipt_source_resume'), $receipts195()],
    'next row receipts null' => [static fn (): mixed => array_values(array_unique(array_column($released195()['attempted_next_source_receipt_rows_source_resume'], 'current_source_receipt_source_resume'))), [null]],
    'visible payload names released' => [static fn (): mixed => array_column($released195()['visible_returning_payloads_source_resume'], 'key_name'), ['module_seed', 'base_url', 'module_seed_retry', 'module_seed_retry_retry', 'routing_rules', 'landing_url', 'routing_rules_next_retry', 'routing_rules_next_retry_next_retry']],
    'held payload names missing' => [static fn (): mixed => array_column($missing195()['held_next_returning_payloads_source_resume'], 'key_name'), ['routing_rules', 'landing_url', 'routing_rules_next_retry', 'routing_rules_next_retry_next_retry']],
    'blocked reasons missing' => [static fn (): mixed => $missing195()['blocked_reasons_source_resume'], ['current-source-receipt-missing', 'current-source-receipt-order-mismatch']],
    'blocked reasons unexpected' => [static fn (): mixed => $unexpected195()['blocked_reasons_source_resume'], ['current-source-receipt-unexpected', 'current-source-receipt-order-mismatch']],
    'blocked reasons reordered' => [static fn (): mixed => $reordered195()['blocked_reasons_source_resume'], ['current-source-receipt-order-mismatch']],
    'blocked reasons source' => [static fn (): mixed => $sourceHeld195()['blocked_reasons_source_resume'], ['current-source-token-mismatch']],
    'blocked reasons resume' => [static fn (): mixed => $resumeHeld195()['blocked_reasons_source_resume'], ['next-resume-token-mismatch']],
    'blocked reasons base' => [static fn (): mixed => $baseHeld195()['blocked_reasons_source_resume'], ['current-fingerprint-missing', 'current-fingerprint-order-mismatch']],
    'plan decision released' => [static fn (): mixed => $released195()['current_source_receipt_plan_source_resume']['decision'], 'resume-next-source-after-current-source-receipts'],
    'plan decision missing' => [static fn (): mixed => $missing195()['current_source_receipt_plan_source_resume']['decision'], 'hold-next-source-until-current-source-receipts'],
    'plan base allowed' => [static fn (): mixed => $released195()['current_source_receipt_plan_source_resume']['base_publish_allowed'], true],
    'plan base held' => [static fn (): mixed => $baseHeld195()['current_source_receipt_plan_source_resume']['base_publish_allowed'], false],
    'plan required echoed' => [static fn (): mixed => $released195()['current_source_receipt_plan_source_resume']['required_receipts'], $receipts195()],
    'plan acknowledged echoed' => [static fn (): mixed => $missing195()['current_source_receipt_plan_source_resume']['acknowledged_receipts'], array_slice($receipts195(), 0, 3)],
    'plan next allowed echoed' => [static fn (): mixed => $released195()['current_source_receipt_plan_source_resume']['next_source_resume_allowed'], true],
    'yield boundary released' => [static fn (): mixed => $released195()['yield_boundary_source_resume'], 'recursive-view-returning-source_resume-current-source-receipts-then-next'],
    'yield boundary held' => [static fn (): mixed => $missing195()['yield_boundary_source_resume'], 'recursive-view-returning-source_resume-current-source-receipts-fence-next'],
    'dependency closure marker' => [static fn (): mixed => $released195()['dependency_closure_source_resume'], 'no-new-support-component-reuses-native-recursive-view-returning-current-source-fingerprint-fence-and-adds-drain-receipt-resume-model'],
    'dependency includes source_resume' => [static fn (): mixed => in_array('sqlite-trigger-recursive-view-returning-current-source-source_resume', $released195()['dependencies_source_resume'], true), true],
    'dependency includes drain receipts' => [static fn (): mixed => in_array('sqlite-returning-current-source-drain-receipts', $released195()['dependencies_source_resume'], true), true],
    'dependency includes resume token' => [static fn (): mixed => in_array('sqlite-view-trigger-next-source-resume-token', $released195()['dependencies_source_resume'], true), true],
    'dependency includes application' => [static fn (): mixed => in_array('application-recursive-view-returning-current-source-source_resume', $released195()['dependencies_source_resume'], true), true],
    'non overlap names next191' => [static fn (): mixed => str_contains($released195()['non_overlap_source_resume'], 'next191 fingerprint'), true],
    'non recursive current count' => [static fn (): mixed => $nonRecursive195()['current_source_receipt_row_count_source_resume'], 2],
    'non recursive visible names' => [static fn (): mixed => array_column($nonRecursive195()['visible_returning_payloads_source_resume'], 'key_name'), ['module_seed', 'base_url', 'routing_rules', 'landing_url']],
    'bad source token throws' => [static fn (): mixed => $plan195(['current_source_token_source_resume' => 'bad token']), InvalidArgumentException::class],
    'bad expected source token throws' => [static fn (): mixed => $plan195(['expected_current_source_token_source_resume' => 'bad token']), InvalidArgumentException::class],
    'bad resume token throws' => [static fn (): mixed => $plan195(['next_resume_token_source_resume' => 'bad token']), InvalidArgumentException::class],
    'bad expected resume token throws' => [static fn (): mixed => $plan195(['expected_next_resume_token_source_resume' => 'bad token']), InvalidArgumentException::class],
    'bad receipt list throws' => [static fn (): mixed => $plan195(['acknowledged_current_source_receipts_source_resume' => ['x' => 'abcdefabcdefabcdefabcdefabcd']]), InvalidArgumentException::class],
    'bad short receipt throws' => [static fn (): mixed => $plan195(['acknowledged_current_source_receipts_source_resume' => ['abc']]), InvalidArgumentException::class],
    'bad non hex receipt throws' => [static fn (): mixed => $plan195(['acknowledged_current_source_receipts_source_resume' => ['zzzzzzzzzzzzzzzzzzzzzzzzzzzz']]), InvalidArgumentException::class],
];

$tests = [];
foreach ($cases195 as $name => [$callback, $expected]) {
    $tests['trigger recursive view returning current source source_resume ' . $name] = static function (TestRunner $t) use ($callback, $expected): void {
        if (is_string($expected) && is_a($expected, Throwable::class, true)) {
            $t->throws($expected, $callback);
            return;
        }
        $t->same($expected, $callback());
    };
}

return $tests;
