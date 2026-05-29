<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteTriggerRecursiveViewReturningCurrentSourceNextPlan;

$rows195 = [
    ['option_id' => 1, 'option_name' => 'siteurl', 'option_value' => 'https://old.test', 'autoload' => 'yes', 'source' => 'seed'],
    ['option_id' => 2, 'option_name' => 'home', 'option_value' => 'https://home.test', 'autoload' => 'yes', 'source' => 'seed'],
];
$currentView195 = [
    'name' => 'wp_option_import_view',
    'source' => 'main@view-cookie-195-current',
    'trigger' => 'wp_option_import_view_io_insert',
    'trigger_source' => 'main@trigger-cookie-195-current',
    'columns' => ['import_id', 'name', 'value', 'autoload_flag'],
    'mapping' => ['import_id' => 'option_id', 'name' => 'option_name', 'value' => 'option_value', 'autoload_flag' => 'autoload'],
    'recursive_column' => 'name',
    'recursive_suffix' => '_retry',
    'audit_label' => 'current-recursive-trigger-receipt-195',
];
$nextView195 = [
    'name' => 'wp_option_import_view',
    'source' => 'main@view-cookie-195-next',
    'trigger' => 'wp_option_import_view_io_insert',
    'trigger_source' => 'main@trigger-cookie-195-next',
    'columns' => ['import_id', 'name', 'value', 'autoload_flag', 'origin'],
    'mapping' => ['import_id' => 'option_id', 'name' => 'option_name', 'value' => 'option_value', 'autoload_flag' => 'autoload', 'origin' => 'source'],
    'recursive_column' => 'name',
    'recursive_suffix' => '_next_retry',
    'audit_label' => 'next-recursive-trigger-receipt-195',
];
$currentInput195 = [
    ['import_id' => 10, 'name' => 'plugin_seed', 'value' => 'enabled', 'autoload_flag' => 'yes', 'spawn_child' => true],
    ['import_id' => 11, 'name' => 'skip_me', 'value' => 'disabled', 'autoload_flag' => 'skip', 'spawn_child' => true],
    ['import_id' => 12, 'name' => 'siteurl', 'value' => 'https://current.test', 'autoload_flag' => 'yes', 'spawn_child' => false],
];
$nextInput195 = [
    ['import_id' => 20, 'name' => 'rewrite_rules', 'value' => 'cached', 'autoload_flag' => 'yes', 'origin' => 'next-import', 'spawn_child' => true],
    ['import_id' => 21, 'name' => 'home', 'value' => 'https://next-home.test', 'autoload_flag' => 'yes', 'origin' => 'next-import', 'spawn_child' => false],
    ['import_id' => 22, 'name' => 'next_skip', 'value' => 'ignored', 'autoload_flag' => 'skip', 'origin' => 'next-import', 'spawn_child' => true],
];
$returning195 = [
    'new.option_name',
    ['expr' => 'new.option_value', 'as' => 'value'],
    ['expr' => 'old.option_value', 'as' => 'old_value'],
    ['expr' => 'view.name', 'as' => 'view_name'],
    ['expr' => 'event', 'as' => 'event_name'],
    ['expr' => 'ordinal', 'as' => 'ordinal_value'],
    ['expr' => 'depth', 'as' => 'depth_value'],
    ['expr' => 'trigger_source', 'as' => 'trigger_source_alias'],
];

$plan195 = static fn (array $options = []): array => SQLiteTriggerRecursiveViewReturningCurrentSourceNextPlan::executeNext195(
    $rows195,
    $currentInput195,
    $nextInput195,
    $currentView195,
    $nextView195,
    $returning195,
    $options + [
        'key' => 'option_name',
        'savepoint' => 'wp_recursive_view_195',
        'max_depth' => 2,
        'page_size' => 2,
        'drained_current_pages' => 2,
        'savepoint_action' => 'release',
        'current_source_epoch' => 19,
        'restart_cursor' => 'wp-recursive-view-returning-restart-195',
        'snapshot_token' => 'wp.recursive.view.returning.snapshot.195',
        'expected_snapshot_token' => 'wp.recursive.view.returning.snapshot.195',
        'current_schema_cookie' => 195,
        'expected_current_schema_cookie' => 195,
        'current_source_generation' => 'wp.recursive.view.current.195',
        'expected_current_source_generation' => 'wp.recursive.view.current.195',
        'trigger_source_generation' => 'wp.recursive.trigger.current.195',
        'expected_trigger_source_generation' => 'wp.recursive.trigger.current.195',
        'returning_cursor_generation' => 'wp.recursive.returning.cursor.195',
        'nested_epoch' => 'wp.recursive.view.nested.195',
        'expected_nested_epoch' => 'wp.recursive.view.nested.195',
        'required_nested_depths' => [1, 2],
        'drained_nested_depths' => [1, 2],
        'current_watermark' => 'wp.recursive.view.current.watermark.195',
        'expected_current_watermark' => 'wp.recursive.view.current.watermark.195',
        'auto_ack_current_ordinals' => true,
        'fingerprint_salt' => 'wp.recursive.view.returning.fingerprint.195',
        'expected_fingerprint_salt' => 'wp.recursive.view.returning.fingerprint.195',
        'auto_ack_current_fingerprints' => true,
        'current_source_token_next195' => 'wp.recursive.view.current.source.195',
        'expected_current_source_token_next195' => 'wp.recursive.view.current.source.195',
        'next_resume_token_next195' => 'wp.recursive.view.next.resume.195',
        'expected_next_resume_token_next195' => 'wp.recursive.view.next.resume.195',
    ],
);

$receipts195 = static fn (): array => $plan195()['required_current_source_receipts_next195'];
$released195 = static fn (): array => $plan195(['auto_ack_current_source_receipts_next195' => true]);
$missing195 = static fn (): array => $plan195(['acknowledged_current_source_receipts_next195' => array_slice($receipts195(), 0, 3)]);
$unexpected195 = static fn (): array => $plan195(['acknowledged_current_source_receipts_next195' => array_merge($receipts195(), ['abcdefabcdefabcdefabcdefabcd'])]);
$reordered195 = static fn (): array => $plan195(['acknowledged_current_source_receipts_next195' => array_reverse($receipts195())]);
$reorderedAllowed195 = static fn (): array => $plan195(['acknowledged_current_source_receipts_next195' => array_reverse($receipts195()), 'require_receipt_order_next195' => false]);
$sourceHeld195 = static fn (): array => $plan195(['auto_ack_current_source_receipts_next195' => true, 'expected_current_source_token_next195' => 'wp.recursive.view.current.source.stale.195']);
$resumeHeld195 = static fn (): array => $plan195(['auto_ack_current_source_receipts_next195' => true, 'expected_next_resume_token_next195' => 'wp.recursive.view.next.resume.stale.195']);
$baseHeld195 = static fn (): array => $plan195(['auto_ack_current_source_receipts_next195' => true, 'auto_ack_current_fingerprints' => false, 'acknowledged_current_fingerprints' => array_slice($plan195()['required_current_fingerprints_next191'], 0, 2)]);
$nonRecursive195 = static fn (): array => $plan195(['recursive_triggers' => false, 'required_nested_depths' => [], 'drained_nested_depths' => [], 'drained_current_pages' => 1, 'auto_ack_current_source_receipts_next195' => true]);

$cases195 = [
    'released status' => [static fn (): mixed => $released195()['status_next195'], 'trigger-recursive-view-returning-current-source-receipts-released-next195'],
    'missing status' => [static fn (): mixed => $missing195()['status_next195'], 'trigger-recursive-view-returning-current-source-receipts-held-next195'],
    'unexpected status' => [static fn (): mixed => $unexpected195()['status_next195'], 'trigger-recursive-view-returning-current-source-receipts-held-next195'],
    'reordered status' => [static fn (): mixed => $reordered195()['status_next195'], 'trigger-recursive-view-returning-current-source-receipts-held-next195'],
    'reordered allowed status' => [static fn (): mixed => $reorderedAllowed195()['status_next195'], 'trigger-recursive-view-returning-current-source-receipts-released-next195'],
    'source held status' => [static fn (): mixed => $sourceHeld195()['status_next195'], 'trigger-recursive-view-returning-current-source-receipts-source-held-next195'],
    'resume held status' => [static fn (): mixed => $resumeHeld195()['status_next195'], 'trigger-recursive-view-returning-current-source-receipts-resume-held-next195'],
    'base held status' => [static fn (): mixed => $baseHeld195()['status_next195'], 'trigger-recursive-view-returning-current-source-receipts-base-held-next195'],
    'base next191 released' => [static fn (): mixed => $released195()['status_next191'], 'trigger-recursive-view-returning-current-source-fingerprints-released-next191'],
    'base held keeps next191 held' => [static fn (): mixed => $baseHeld195()['status_next191'], 'trigger-recursive-view-returning-current-source-fingerprints-held-next191'],
    'source token retained' => [static fn (): mixed => $released195()['current_source_token_next195'], 'wp.recursive.view.current.source.195'],
    'expected source token retained' => [static fn (): mixed => $released195()['expected_current_source_token_next195'], 'wp.recursive.view.current.source.195'],
    'source token matches' => [static fn (): mixed => $released195()['current_source_token_matches_next195'], true],
    'source token mismatch' => [static fn (): mixed => $sourceHeld195()['current_source_token_matches_next195'], false],
    'resume token retained' => [static fn (): mixed => $released195()['next_resume_token_next195'], 'wp.recursive.view.next.resume.195'],
    'expected resume token retained' => [static fn (): mixed => $released195()['expected_next_resume_token_next195'], 'wp.recursive.view.next.resume.195'],
    'resume token matches' => [static fn (): mixed => $released195()['next_resume_token_matches_next195'], true],
    'resume token mismatch' => [static fn (): mixed => $resumeHeld195()['next_resume_token_matches_next195'], false],
    'required receipt count' => [static fn (): mixed => count($released195()['required_current_source_receipts_next195']), 4],
    'required receipts are 28 hex' => [static fn (): mixed => array_map(static fn (string $v): int => preg_match('/^[a-f0-9]{28}$/', $v), $released195()['required_current_source_receipts_next195']), [1, 1, 1, 1]],
    'auto acknowledged equals required' => [static fn (): mixed => $released195()['acknowledged_current_source_receipts_next195'], $receipts195()],
    'missing acknowledged count' => [static fn (): mixed => count($missing195()['acknowledged_current_source_receipts_next195']), 3],
    'missing receipt recorded' => [static fn (): mixed => $missing195()['missing_current_source_receipts_next195'], [array_slice($receipts195(), -1)[0]]],
    'unexpected receipt recorded' => [static fn (): mixed => $unexpected195()['unexpected_current_source_receipts_next195'], ['abcdefabcdefabcdefabcdefabcd']],
    'released missing empty' => [static fn (): mixed => $released195()['missing_current_source_receipts_next195'], []],
    'released unexpected empty' => [static fn (): mixed => $released195()['unexpected_current_source_receipts_next195'], []],
    'order required default' => [static fn (): mixed => $released195()['require_receipt_order_next195'], true],
    'order matches released' => [static fn (): mixed => $released195()['current_source_receipt_order_matches_next195'], true],
    'order mismatch detected' => [static fn (): mixed => $reordered195()['current_source_receipt_order_matches_next195'], false],
    'order disabled flag' => [static fn (): mixed => $reorderedAllowed195()['require_receipt_order_next195'], false],
    'receipt fence released' => [static fn (): mixed => $released195()['current_source_receipt_fence_clear_next195'], true],
    'receipt fence missing blocked' => [static fn (): mixed => $missing195()['current_source_receipt_fence_clear_next195'], false],
    'receipt fence reordered blocked' => [static fn (): mixed => $reordered195()['current_source_receipt_fence_clear_next195'], false],
    'receipt fence reordered allowed' => [static fn (): mixed => $reorderedAllowed195()['current_source_receipt_fence_clear_next195'], true],
    'resume allowed released' => [static fn (): mixed => $released195()['next_source_resume_allowed_next195'], true],
    'resume denied missing' => [static fn (): mixed => $missing195()['next_source_resume_allowed_next195'], false],
    'resume denied source' => [static fn (): mixed => $sourceHeld195()['next_source_resume_allowed_next195'], false],
    'resume denied resume token' => [static fn (): mixed => $resumeHeld195()['next_source_resume_allowed_next195'], false],
    'resume denied base held' => [static fn (): mixed => $baseHeld195()['next_source_resume_allowed_next195'], false],
    'current row count' => [static fn (): mixed => $released195()['current_source_receipt_row_count_next195'], 4],
    'attempted next row count' => [static fn (): mixed => $released195()['attempted_next_source_receipt_row_count_next195'], 4],
    'visible released count' => [static fn (): mixed => $released195()['visible_row_count_next195'], 8],
    'held released count' => [static fn (): mixed => $released195()['held_next_row_count_next195'], 0],
    'visible missing count current only' => [static fn (): mixed => $missing195()['visible_row_count_next195'], 4],
    'held missing count next only' => [static fn (): mixed => $missing195()['held_next_row_count_next195'], 4],
    'current phases' => [static fn (): mixed => array_values(array_unique(array_column($released195()['current_source_receipt_rows_next195'], 'receipt_phase_next195'))), ['current']],
    'next phases' => [static fn (): mixed => array_values(array_unique(array_column($released195()['attempted_next_source_receipt_rows_next195'], 'receipt_phase_next195'))), ['next']],
    'current visible while missing' => [static fn (): mixed => array_values(array_unique(array_column($missing195()['current_source_receipt_rows_next195'], 'visible_after_current_source_receipts_next195'))), [true]],
    'next visible released' => [static fn (): mixed => array_values(array_unique(array_column($released195()['attempted_next_source_receipt_rows_next195'], 'visible_after_current_source_receipts_next195'))), [true]],
    'next held missing' => [static fn (): mixed => array_values(array_unique(array_column($missing195()['attempted_next_source_receipt_rows_next195'], 'visible_after_current_source_receipts_next195'))), [false]],
    'current row receipts tagged' => [static fn (): mixed => array_column($released195()['current_source_receipt_rows_next195'], 'current_source_receipt_next195'), $receipts195()],
    'next row receipts null' => [static fn (): mixed => array_values(array_unique(array_column($released195()['attempted_next_source_receipt_rows_next195'], 'current_source_receipt_next195'))), [null]],
    'visible payload names released' => [static fn (): mixed => array_column($released195()['visible_returning_payloads_next195'], 'option_name'), ['plugin_seed', 'siteurl', 'plugin_seed_retry', 'plugin_seed_retry_retry', 'rewrite_rules', 'home', 'rewrite_rules_next_retry', 'rewrite_rules_next_retry_next_retry']],
    'held payload names missing' => [static fn (): mixed => array_column($missing195()['held_next_returning_payloads_next195'], 'option_name'), ['rewrite_rules', 'home', 'rewrite_rules_next_retry', 'rewrite_rules_next_retry_next_retry']],
    'blocked reasons missing' => [static fn (): mixed => $missing195()['blocked_reasons_next195'], ['current-source-receipt-missing', 'current-source-receipt-order-mismatch']],
    'blocked reasons unexpected' => [static fn (): mixed => $unexpected195()['blocked_reasons_next195'], ['current-source-receipt-unexpected', 'current-source-receipt-order-mismatch']],
    'blocked reasons reordered' => [static fn (): mixed => $reordered195()['blocked_reasons_next195'], ['current-source-receipt-order-mismatch']],
    'blocked reasons source' => [static fn (): mixed => $sourceHeld195()['blocked_reasons_next195'], ['current-source-token-mismatch']],
    'blocked reasons resume' => [static fn (): mixed => $resumeHeld195()['blocked_reasons_next195'], ['next-resume-token-mismatch']],
    'blocked reasons base' => [static fn (): mixed => $baseHeld195()['blocked_reasons_next195'], ['current-fingerprint-missing', 'current-fingerprint-order-mismatch']],
    'plan decision released' => [static fn (): mixed => $released195()['current_source_receipt_plan_next195']['decision'], 'resume-next-source-after-current-source-receipts'],
    'plan decision missing' => [static fn (): mixed => $missing195()['current_source_receipt_plan_next195']['decision'], 'hold-next-source-until-current-source-receipts'],
    'plan base allowed' => [static fn (): mixed => $released195()['current_source_receipt_plan_next195']['base_publish_allowed'], true],
    'plan base held' => [static fn (): mixed => $baseHeld195()['current_source_receipt_plan_next195']['base_publish_allowed'], false],
    'plan required echoed' => [static fn (): mixed => $released195()['current_source_receipt_plan_next195']['required_receipts'], $receipts195()],
    'plan acknowledged echoed' => [static fn (): mixed => $missing195()['current_source_receipt_plan_next195']['acknowledged_receipts'], array_slice($receipts195(), 0, 3)],
    'plan next allowed echoed' => [static fn (): mixed => $released195()['current_source_receipt_plan_next195']['next_source_resume_allowed'], true],
    'yield boundary released' => [static fn (): mixed => $released195()['yield_boundary_next195'], 'recursive-view-returning-next195-current-source-receipts-then-next'],
    'yield boundary held' => [static fn (): mixed => $missing195()['yield_boundary_next195'], 'recursive-view-returning-next195-current-source-receipts-fence-next'],
    'dependency closure marker' => [static fn (): mixed => $released195()['dependency_closure_next195'], 'no-new-support-component-reuses-native-recursive-view-returning-current-source-fingerprint-fence-and-adds-drain-receipt-resume-model'],
    'dependency includes next195' => [static fn (): mixed => in_array('sqlite-trigger-recursive-view-returning-current-source-next195', $released195()['dependencies_next195'], true), true],
    'dependency includes drain receipts' => [static fn (): mixed => in_array('sqlite-returning-current-source-drain-receipts', $released195()['dependencies_next195'], true), true],
    'dependency includes resume token' => [static fn (): mixed => in_array('sqlite-view-trigger-next-source-resume-token', $released195()['dependencies_next195'], true), true],
    'dependency includes wordpress' => [static fn (): mixed => in_array('wordpress-recursive-view-returning-current-source-next195', $released195()['dependencies_next195'], true), true],
    'non overlap names next191' => [static fn (): mixed => str_contains($released195()['non_overlap_next195'], 'next191 fingerprint'), true],
    'non recursive current count' => [static fn (): mixed => $nonRecursive195()['current_source_receipt_row_count_next195'], 2],
    'non recursive visible names' => [static fn (): mixed => array_column($nonRecursive195()['visible_returning_payloads_next195'], 'option_name'), ['plugin_seed', 'siteurl', 'rewrite_rules', 'home']],
    'bad source token throws' => [static fn (): mixed => $plan195(['current_source_token_next195' => 'bad token']), InvalidArgumentException::class],
    'bad expected source token throws' => [static fn (): mixed => $plan195(['expected_current_source_token_next195' => 'bad token']), InvalidArgumentException::class],
    'bad resume token throws' => [static fn (): mixed => $plan195(['next_resume_token_next195' => 'bad token']), InvalidArgumentException::class],
    'bad expected resume token throws' => [static fn (): mixed => $plan195(['expected_next_resume_token_next195' => 'bad token']), InvalidArgumentException::class],
    'bad receipt list throws' => [static fn (): mixed => $plan195(['acknowledged_current_source_receipts_next195' => ['x' => 'abcdefabcdefabcdefabcdefabcd']]), InvalidArgumentException::class],
    'bad short receipt throws' => [static fn (): mixed => $plan195(['acknowledged_current_source_receipts_next195' => ['abc']]), InvalidArgumentException::class],
    'bad non hex receipt throws' => [static fn (): mixed => $plan195(['acknowledged_current_source_receipts_next195' => ['zzzzzzzzzzzzzzzzzzzzzzzzzzzz']]), InvalidArgumentException::class],
];

$tests = [];
foreach ($cases195 as $name => [$callback, $expected]) {
    $tests['trigger recursive view returning current source next195 ' . $name] = static function (TestRunner $t) use ($callback, $expected): void {
        if (is_string($expected) && is_a($expected, Throwable::class, true)) {
            $t->throws($expected, $callback);
            return;
        }
        $t->same($expected, $callback());
    };
}

return $tests;
