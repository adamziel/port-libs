<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteTriggerRecursiveViewReturningCurrentSourceNextPlan;

$rows203 = [
    ['option_id' => 1, 'option_name' => 'siteurl', 'option_value' => 'https://old.test', 'autoload' => 'yes'],
    ['option_id' => 2, 'option_name' => 'home', 'option_value' => 'https://home.test', 'autoload' => 'yes'],
];
$currentView203 = [
    'name' => 'wp_recursive_option_import',
    'source' => 'main@view-cookie-203-current',
    'trigger' => 'wp_recursive_option_import_io_insert',
    'trigger_source' => 'main@trigger-cookie-203-current',
    'columns' => ['import_id', 'name', 'value', 'autoload_flag', 'spawn_child'],
    'mapping' => ['import_id' => 'option_id', 'name' => 'option_name', 'value' => 'option_value', 'autoload_flag' => 'autoload', 'spawn_child' => 'spawn_child'],
    'audit_label' => 'current-recursive-view-trigger-203',
];
$nextView203 = $currentView203;
$nextView203['source'] = 'main@view-cookie-203-next';
$nextView203['trigger_source'] = 'main@trigger-cookie-203-next';
$postResetView203 = $currentView203;
$postResetView203['source'] = 'main@view-cookie-203-post-reset';
$postResetView203['trigger_source'] = 'main@trigger-cookie-203-post-reset';
$followingView203 = $currentView203;
$followingView203['source'] = 'main@view-cookie-203-following';
$followingView203['trigger_source'] = 'main@trigger-cookie-203-following';
$currentInput203 = [
    ['import_id' => 10, 'name' => 'siteurl', 'value' => 'https://current.test', 'autoload_flag' => 'yes', 'spawn_child' => true],
    ['import_id' => 11, 'name' => 'current_plugin', 'value' => 'enabled', 'autoload_flag' => 'no', 'spawn_child' => true],
];
$nextInput203 = [
    ['import_id' => 20, 'name' => 'home', 'value' => 'https://next.test', 'autoload_flag' => 'yes', 'spawn_child' => true],
    ['import_id' => 21, 'name' => 'next_plugin', 'value' => 'active', 'autoload_flag' => 'no', 'spawn_child' => false],
];
$postResetInput203 = [
    ['import_id' => 30, 'name' => 'siteurl', 'value' => 'https://fresh.test', 'autoload_flag' => 'yes', 'spawn_child' => false],
    ['import_id' => 31, 'name' => 'rewrite_rules', 'value' => 'fresh-rules', 'autoload_flag' => 'no', 'spawn_child' => false],
];
$followingInput203 = [
    ['import_id' => 40, 'name' => 'blogdescription', 'value' => 'after-next', 'autoload_flag' => 'yes', 'spawn_child' => true],
    ['import_id' => 41, 'name' => 'stylesheet', 'value' => 'twentytwentyfive', 'autoload_flag' => 'yes', 'spawn_child' => false],
    ['import_id' => 42, 'name' => 'template', 'value' => 'twentytwentyfive', 'autoload_flag' => 'yes', 'spawn_child' => true],
];
$returning203 = [
    ['expr' => 'new.option_name', 'as' => 'name'],
    ['expr' => 'new.option_value', 'as' => 'value'],
    ['expr' => 'old.option_value', 'as' => 'old_value'],
    ['expr' => 'event', 'as' => 'event_name'],
    ['expr' => 'depth', 'as' => 'depth_value'],
    ['expr' => 'ordinal', 'as' => 'ordinal_value'],
    ['expr' => 'trigger_source', 'as' => 'trigger_source_alias'],
    ['expr' => 'spawn_child', 'as' => 'spawn_child'],
];

$plan203 = static fn (array $options = []): array => SQLiteTriggerRecursiveViewReturningCurrentSourceNextPlan::executeNext203(
    $rows203,
    $currentInput203,
    $nextInput203,
    $currentView203,
    $nextView203,
    $returning203,
    $options + [
        'key' => 'option_name',
        'savepoint' => 'wp_recursive_view_203',
        'cursor_name' => 'wp_recursive_view_returning_cursor_203',
        'current_generation' => 'wp-current-returning-203',
        'next_generation' => 'wp-next-returning-203',
        'page_size' => 3,
        'admit_next_source' => true,
        'current_source_token' => 'wp.current.source.203',
        'drain_ack_token' => 'wp.returning.drain.203',
        'rollback_token' => 'wp.rollback.current.203',
        'reset_generation' => 'wp-current-reset-203',
        'post_reset_current_source_token' => 'wp.current.source.postreset.203',
        'post_reset_cursor' => 'wp.returning.postreset.cursor.203',
        'post_reset_view' => $postResetView203,
        'post_reset_input' => $postResetInput203,
        'fresh_acknowledged_ordinals' => [0, 1],
        'next_source_token' => 'wp.next.source.203',
        'next_cursor' => 'wp.returning.next.cursor.203',
        'next_acknowledged_ordinals' => [0, 1],
        'close_next_cursor' => 'wp.returning.next.cursor.203',
        'following_current_source_token' => 'wp.current.source.following.203',
        'following_cursor' => 'wp.returning.following.cursor.203',
        'following_current_view' => $followingView203,
        'following_current_input' => $followingInput203,
        'following_generation' => 'wp-following-current-203',
        'recursive_child_acknowledged_ordinals' => [0, 1],
        'recursive_child_source_token' => 'wp.current.source.recursive.child.203',
        'recursive_child_cursor' => 'wp.returning.recursive.child.cursor.203',
        'recursive_child_generation' => 'wp-recursive-child-current-203',
        'current_generation_next203' => 'wp.current.recursive.returning.generation.203',
        'expected_current_generation_next203' => 'wp.current.recursive.returning.generation.203',
        'current_handoff_cursor_next203' => 'wp.returning.current.handoff.cursor.203',
        'current_generation_commit_marker_next203' => 'wp.current.recursive.returning.commit.203',
    ],
);

$receipts203 = static fn (): array => $plan203()['required_current_generation_receipts_next203'];
$released203 = static fn (): array => $plan203(['auto_ack_current_generation_receipts_next203' => true]);
$missing203 = static fn (): array => $plan203(['acknowledged_current_generation_receipts_next203' => array_slice($receipts203(), 0, 1)]);
$unexpected203 = static fn (): array => $plan203(['acknowledged_current_generation_receipts_next203' => array_merge($receipts203(), ['abcdefabcdefabcdefabcdefabcdef'])]);
$reordered203 = static fn (): array => $plan203(['acknowledged_current_generation_receipts_next203' => array_reverse($receipts203())]);
$reorderedAllowed203 = static fn (): array => $plan203(['acknowledged_current_generation_receipts_next203' => array_reverse($receipts203()), 'require_generation_receipt_order_next203' => false]);
$generationHeld203 = static fn (): array => $plan203(['auto_ack_current_generation_receipts_next203' => true, 'expected_current_generation_next203' => 'wp.current.recursive.returning.generation.stale.203']);
$baseHeld203 = static fn (): array => $plan203(['auto_ack_current_generation_receipts_next203' => true, 'recursive_child_acknowledged_ordinals' => [0]]);
$custom203 = static fn (): array => $plan203([
    'auto_ack_current_generation_receipts_next203' => true,
    'current_generation_next203' => 'wp.current.recursive.returning.generation.custom.203',
    'expected_current_generation_next203' => 'wp.current.recursive.returning.generation.custom.203',
    'current_handoff_cursor_next203' => 'wp.returning.current.handoff.cursor.custom.203',
    'current_generation_commit_marker_next203' => 'wp.current.recursive.returning.commit.custom.203',
]);

$cases203 = [
    'released status' => [static fn (): mixed => $released203()['status_next203'], 'trigger-recursive-view-returning-current-source-next203-generation-released'],
    'missing status' => [static fn (): mixed => $missing203()['status_next203'], 'trigger-recursive-view-returning-current-source-next203-receipts-held'],
    'unexpected status' => [static fn (): mixed => $unexpected203()['status_next203'], 'trigger-recursive-view-returning-current-source-next203-receipts-held'],
    'reordered status' => [static fn (): mixed => $reordered203()['status_next203'], 'trigger-recursive-view-returning-current-source-next203-receipts-held'],
    'reordered allowed status' => [static fn (): mixed => $reorderedAllowed203()['status_next203'], 'trigger-recursive-view-returning-current-source-next203-generation-released'],
    'generation held status' => [static fn (): mixed => $generationHeld203()['status_next203'], 'trigger-recursive-view-returning-current-source-next203-generation-held'],
    'base held status' => [static fn (): mixed => $baseHeld203()['status_next203'], 'trigger-recursive-view-returning-current-source-next203-base-held'],
    'base next196 visible' => [static fn (): mixed => $released203()['base']['status_next196'], 'trigger-recursive-view-returning-current-source-next196-next-source-visible'],
    'base held keeps next196 waiting' => [static fn (): mixed => $baseHeld203()['base']['status_next196'], 'trigger-recursive-view-returning-current-source-next196-awaiting-recursive-child-acks'],
    'savepoint retained' => [static fn (): mixed => $released203()['savepoint'], 'wp_recursive_view_203'],
    'base publish allowed' => [static fn (): mixed => $released203()['base_next_source_publish_allowed_next203'], true],
    'base publish denied' => [static fn (): mixed => $baseHeld203()['base_next_source_publish_allowed_next203'], false],
    'current generation retained' => [static fn (): mixed => $released203()['current_generation_next203'], 'wp.current.recursive.returning.generation.203'],
    'expected generation retained' => [static fn (): mixed => $released203()['expected_current_generation_next203'], 'wp.current.recursive.returning.generation.203'],
    'generation matches' => [static fn (): mixed => $released203()['current_generation_matches_next203'], true],
    'generation mismatch' => [static fn (): mixed => $generationHeld203()['current_generation_matches_next203'], false],
    'custom generation retained' => [static fn (): mixed => $custom203()['current_generation_next203'], 'wp.current.recursive.returning.generation.custom.203'],
    'handoff cursor retained' => [static fn (): mixed => $released203()['current_handoff_cursor_next203'], 'wp.returning.current.handoff.cursor.203'],
    'custom handoff cursor retained' => [static fn (): mixed => $custom203()['current_handoff_cursor_next203'], 'wp.returning.current.handoff.cursor.custom.203'],
    'commit marker retained' => [static fn (): mixed => $released203()['current_generation_commit_marker_next203'], 'wp.current.recursive.returning.commit.203'],
    'custom commit marker retained' => [static fn (): mixed => $custom203()['current_generation_commit_marker_next203'], 'wp.current.recursive.returning.commit.custom.203'],
    'required receipt count' => [static fn (): mixed => count($released203()['required_current_generation_receipts_next203']), 2],
    'required receipts are 30 hex' => [static fn (): mixed => array_map(static fn (string $v): int => preg_match('/^[a-f0-9]{30}$/', $v), $released203()['required_current_generation_receipts_next203']), [1, 1]],
    'auto acknowledged equals required' => [static fn (): mixed => $released203()['acknowledged_current_generation_receipts_next203'], $receipts203()],
    'missing acknowledged count' => [static fn (): mixed => count($missing203()['acknowledged_current_generation_receipts_next203']), 1],
    'missing receipt recorded' => [static fn (): mixed => $missing203()['missing_current_generation_receipts_next203'], [array_slice($receipts203(), -1)[0]]],
    'unexpected receipt recorded' => [static fn (): mixed => $unexpected203()['unexpected_current_generation_receipts_next203'], ['abcdefabcdefabcdefabcdefabcdef']],
    'released missing empty' => [static fn (): mixed => $released203()['missing_current_generation_receipts_next203'], []],
    'released unexpected empty' => [static fn (): mixed => $released203()['unexpected_current_generation_receipts_next203'], []],
    'order required default' => [static fn (): mixed => $released203()['require_generation_receipt_order_next203'], true],
    'order matches released' => [static fn (): mixed => $released203()['current_generation_receipt_order_matches_next203'], true],
    'order mismatch detected' => [static fn (): mixed => $reordered203()['current_generation_receipt_order_matches_next203'], false],
    'order disabled flag' => [static fn (): mixed => $reorderedAllowed203()['require_generation_receipt_order_next203'], false],
    'generation fence released' => [static fn (): mixed => $released203()['current_generation_fence_clear_next203'], true],
    'generation fence missing blocked' => [static fn (): mixed => $missing203()['current_generation_fence_clear_next203'], false],
    'generation fence reordered blocked' => [static fn (): mixed => $reordered203()['current_generation_fence_clear_next203'], false],
    'generation fence reordered allowed' => [static fn (): mixed => $reorderedAllowed203()['current_generation_fence_clear_next203'], true],
    'next visible released' => [static fn (): mixed => $released203()['next_source_visible_after_current_generation_next203'], true],
    'next denied missing' => [static fn (): mixed => $missing203()['next_source_visible_after_current_generation_next203'], false],
    'next denied generation' => [static fn (): mixed => $generationHeld203()['next_source_visible_after_current_generation_next203'], false],
    'next denied base held' => [static fn (): mixed => $baseHeld203()['next_source_visible_after_current_generation_next203'], false],
    'current generation row count' => [static fn (): mixed => $released203()['current_generation_row_count_next203'], 2],
    'attempted next row count' => [static fn (): mixed => $released203()['attempted_next_generation_row_count_next203'], 2],
    'visible released count' => [static fn (): mixed => $released203()['visible_row_count_next203'], 4],
    'held released count' => [static fn (): mixed => $released203()['held_next_row_count_next203'], 0],
    'visible missing count current only' => [static fn (): mixed => $missing203()['visible_row_count_next203'], 2],
    'held missing count next only' => [static fn (): mixed => $missing203()['held_next_row_count_next203'], 2],
    'current phases' => [static fn (): mixed => array_values(array_unique(array_column($released203()['current_generation_rows_next203'], 'generation_phase_next203'))), ['current']],
    'next phases' => [static fn (): mixed => array_values(array_unique(array_column($released203()['attempted_next_generation_rows_next203'], 'generation_phase_next203'))), ['next']],
    'current visible while missing' => [static fn (): mixed => array_values(array_unique(array_column($missing203()['current_generation_rows_next203'], 'visible_after_current_generation_next203'))), [true]],
    'next visible released' => [static fn (): mixed => array_values(array_unique(array_column($released203()['attempted_next_generation_rows_next203'], 'visible_after_current_generation_next203'))), [true]],
    'next held missing' => [static fn (): mixed => array_values(array_unique(array_column($missing203()['attempted_next_generation_rows_next203'], 'visible_after_current_generation_next203'))), [false]],
    'current receipts tagged' => [static fn (): mixed => array_column($released203()['current_generation_rows_next203'], 'current_generation_receipt_next203'), $receipts203()],
    'next receipts null' => [static fn (): mixed => array_values(array_unique(array_column($released203()['attempted_next_generation_rows_next203'], 'current_generation_receipt_next203'))), [null]],
    'current generation stamped' => [static fn (): mixed => array_values(array_unique(array_column($released203()['current_generation_rows_next203'], 'current_generation_next203'))), ['wp.current.recursive.returning.generation.203']],
    'next generation stamped' => [static fn (): mixed => array_values(array_unique(array_column($released203()['attempted_next_generation_rows_next203'], 'current_generation_next203'))), ['wp.current.recursive.returning.generation.203']],
    'current cursor stamped' => [static fn (): mixed => array_values(array_unique(array_column($released203()['current_generation_rows_next203'], 'current_handoff_cursor_next203'))), ['wp.returning.current.handoff.cursor.203']],
    'next cursor stamped' => [static fn (): mixed => array_values(array_unique(array_column($released203()['attempted_next_generation_rows_next203'], 'current_handoff_cursor_next203'))), ['wp.returning.current.handoff.cursor.203']],
    'visible payload names released' => [static fn (): mixed => array_column($released203()['visible_returning_payloads_next203'], 'name'), ['blogdescription_child', 'template_child', 'home', 'next_plugin']],
    'held payload names missing' => [static fn (): mixed => array_column($missing203()['held_next_returning_payloads_next203'], 'name'), ['home', 'next_plugin']],
    'blocked reasons missing' => [static fn (): mixed => $missing203()['blocked_reasons_next203'], ['current-generation-receipt-missing', 'current-generation-receipt-order-mismatch']],
    'blocked reasons unexpected' => [static fn (): mixed => $unexpected203()['blocked_reasons_next203'], ['current-generation-receipt-unexpected', 'current-generation-receipt-order-mismatch']],
    'blocked reasons reordered' => [static fn (): mixed => $reordered203()['blocked_reasons_next203'], ['current-generation-receipt-order-mismatch']],
    'blocked reasons generation' => [static fn (): mixed => $generationHeld203()['blocked_reasons_next203'], ['current-generation-mismatch']],
    'blocked reasons base' => [static fn (): mixed => $baseHeld203()['blocked_reasons_next203'], ['recursive-child-returning-rows-not-acknowledged']],
    'released reasons empty' => [static fn (): mixed => $released203()['blocked_reasons_next203'], []],
    'plan decision released' => [static fn (): mixed => $released203()['current_generation_plan_next203']['decision'], 'publish-next-source-after-current-generation-handoff'],
    'plan decision missing' => [static fn (): mixed => $missing203()['current_generation_plan_next203']['decision'], 'hold-next-source-until-current-generation-handoff'],
    'plan base allowed' => [static fn (): mixed => $released203()['current_generation_plan_next203']['base_next_source_publish_allowed'], true],
    'plan base held' => [static fn (): mixed => $baseHeld203()['current_generation_plan_next203']['base_next_source_publish_allowed'], false],
    'plan generation matches' => [static fn (): mixed => $released203()['current_generation_plan_next203']['current_generation_matches'], true],
    'plan generation mismatch' => [static fn (): mixed => $generationHeld203()['current_generation_plan_next203']['current_generation_matches'], false],
    'plan required echoed' => [static fn (): mixed => $released203()['current_generation_plan_next203']['required_generation_receipts'], $receipts203()],
    'plan acknowledged echoed' => [static fn (): mixed => $missing203()['current_generation_plan_next203']['acknowledged_generation_receipts'], array_slice($receipts203(), 0, 1)],
    'plan next visible echoed' => [static fn (): mixed => $released203()['current_generation_plan_next203']['next_source_visible'], true],
    'yield boundary released' => [static fn (): mixed => $released203()['yield_boundary_next203'], 'recursive-view-returning-next203-current-generation-handoff-then-next'],
    'yield boundary held' => [static fn (): mixed => $missing203()['yield_boundary_next203'], 'recursive-view-returning-next203-current-generation-handoff-fences-next'],
    'dependency closure marker' => [static fn (): mixed => $released203()['dependency_closure_next203'], 'no-new-support-component-reuses-native-recursive-view-returning-current-source-generation-handoff-fence'],
    'dependency includes next203' => [static fn (): mixed => in_array('sqlite-trigger-recursive-view-returning-current-source-next203', $released203()['dependencies_next203'], true), true],
    'dependency includes handoff' => [static fn (): mixed => in_array('sqlite-returning-current-source-generation-handoff', $released203()['dependencies_next203'], true), true],
    'dependency includes next196' => [static fn (): mixed => in_array('sqlite-trigger-recursive-view-returning-current-source-next196', $released203()['dependencies_next203'], true), true],
    'dependency includes wordpress' => [static fn (): mixed => in_array('wordpress-recursive-view-returning-current-source-next203', $released203()['dependencies_next203'], true), true],
    'non overlap mentions next196' => [static fn (): mixed => str_contains($released203()['non_overlap_next203'], 'next196 child drain'), true],
    'bad generation rejected' => [static fn (): mixed => $plan203(['current_generation_next203' => 'bad generation']), InvalidArgumentException::class],
    'bad expected generation rejected' => [static fn (): mixed => $plan203(['expected_current_generation_next203' => 'bad generation']), InvalidArgumentException::class],
    'bad handoff cursor rejected' => [static fn (): mixed => $plan203(['current_handoff_cursor_next203' => 'bad cursor']), InvalidArgumentException::class],
    'bad commit marker rejected' => [static fn (): mixed => $plan203(['current_generation_commit_marker_next203' => 'bad marker']), InvalidArgumentException::class],
    'bad receipt list rejected' => [static fn (): mixed => $plan203(['acknowledged_current_generation_receipts_next203' => ['x' => 'abcdefabcdefabcdefabcdefabcdef']]), InvalidArgumentException::class],
    'bad short receipt rejected' => [static fn (): mixed => $plan203(['acknowledged_current_generation_receipts_next203' => ['abc']]), InvalidArgumentException::class],
    'bad non hex receipt rejected' => [static fn (): mixed => $plan203(['acknowledged_current_generation_receipts_next203' => ['zzzzzzzzzzzzzzzzzzzzzzzzzzzzzz']]), InvalidArgumentException::class],
];

$tests = [];
foreach ($cases203 as $name => [$callback, $expected]) {
    $tests['trigger recursive view returning current source next203 ' . $name] = static function (TestRunner $t) use ($callback, $expected): void {
        if (is_string($expected) && is_a($expected, Throwable::class, true)) {
            $t->throws($expected, $callback);
            return;
        }
        $t->same($expected, $callback());
    };
}

return $tests;
