<?php

declare(strict_types=1);

foreach (glob(__DIR__ . '/../src/SQLiteTriggerRecursiveViewReturningCurrentSourceNext*.php') ?: [] as $file) {
    require_once $file;
}
require_once __DIR__ . '/../src/SQLiteTriggerRecursiveViewUpsertCurrentSourceNextPlan.php';
require_once __DIR__ . '/../src/SQLiteTriggerRecursiveViewUpsertCurrentSourceNextPlan.php';
require_once __DIR__ . '/../src/SQLiteTriggerRecursiveViewUpsertCurrentSourceNextPlan.php';
require_once __DIR__ . '/../src/SQLiteTriggerRecursiveViewUpsertCurrentSourceNextPlan.php';

use PortLibs\LibSqlite\SQLiteTriggerRecursiveViewUpsertCurrentSourceNextPlan;

$rows249 = [
    ['option_id' => 1, 'option_name' => 'siteurl', 'option_value' => 'https://old.test', 'autoload' => 'yes'],
    ['option_id' => 2, 'option_name' => 'home', 'option_value' => 'https://home.test', 'autoload' => 'yes'],
];
$currentView249 = [
    'name' => 'wp_recursive_option_import',
    'source' => 'main@view-cookie-249-current',
    'trigger' => 'wp_recursive_option_import_io_insert',
    'trigger_source' => 'main@trigger-cookie-249-current',
    'columns' => ['import_id', 'name', 'value', 'autoload_flag', 'spawn_child'],
    'mapping' => ['import_id' => 'option_id', 'name' => 'option_name', 'value' => 'option_value', 'autoload_flag' => 'autoload', 'spawn_child' => 'spawn_child'],
    'audit_label' => 'current-recursive-view-upsert-249',
];
$nextView249 = $currentView249;
$nextView249['source'] = 'main@view-cookie-249-next';
$nextView249['trigger_source'] = 'main@trigger-cookie-249-next';
$postResetView249 = $currentView249;
$postResetView249['source'] = 'main@view-cookie-249-post-reset';
$postResetView249['trigger_source'] = 'main@trigger-cookie-249-post-reset';
$followingView249 = $currentView249;
$followingView249['source'] = 'main@view-cookie-249-following';
$followingView249['trigger_source'] = 'main@trigger-cookie-249-following';
$currentInput249 = [
    ['import_id' => 10, 'name' => 'siteurl', 'value' => 'https://current.test', 'autoload_flag' => 'yes', 'spawn_child' => true],
    ['import_id' => 11, 'name' => 'current_plugin', 'value' => 'enabled', 'autoload_flag' => 'no', 'spawn_child' => true],
];
$nextInput249 = [
    ['import_id' => 20, 'name' => 'home', 'value' => 'https://next.test', 'autoload_flag' => 'yes', 'spawn_child' => true],
    ['import_id' => 21, 'name' => 'next_plugin', 'value' => 'active', 'autoload_flag' => 'no', 'spawn_child' => false],
];
$returning249 = [
    ['expr' => 'new.option_name', 'as' => 'name'],
    ['expr' => 'new.option_value', 'as' => 'value'],
    ['expr' => 'event', 'as' => 'event_name'],
    ['expr' => 'depth', 'as' => 'depth_value'],
    ['expr' => 'ordinal', 'as' => 'ordinal_value'],
    ['expr' => 'trigger_source', 'as' => 'trigger_source_alias'],
    ['expr' => 'spawn_child', 'as' => 'spawn_child'],
];

$plan249 = static fn (array $options = []): array => SQLiteTriggerRecursiveViewUpsertCurrentSourceNextPlan::executeCurrentAssignmentImageReceipt(
    $rows249,
    $currentInput249,
    $nextInput249,
    $currentView249,
    $nextView249,
    $returning249,
    $options + [
        'key' => 'option_name',
        'savepoint' => 'wp_recursive_view_249',
        'cursor_name' => 'wp_recursive_view_returning_cursor_249',
        'admit_next_source' => true,
        'rollback_token' => 'wp.rollback.current.249',
        'reset_generation' => 'wp-current-reset-249',
        'post_reset_current_source_token' => 'wp.current.source.postreset.249',
        'post_reset_cursor' => 'wp.returning.postreset.cursor.249',
        'post_reset_view' => $postResetView249,
        'post_reset_input' => [
            ['import_id' => 30, 'name' => 'siteurl', 'value' => 'https://fresh.test', 'autoload_flag' => 'yes', 'spawn_child' => false],
            ['import_id' => 31, 'name' => 'rewrite_rules', 'value' => 'fresh-rules', 'autoload_flag' => 'no', 'spawn_child' => false],
        ],
        'fresh_acknowledged_ordinals' => [0, 1],
        'next_source_token' => 'wp.next.source.249',
        'next_cursor' => 'wp.returning.next.cursor.249',
        'next_acknowledged_ordinals' => [0, 1],
        'close_next_cursor' => 'wp.returning.next.cursor.249',
        'following_current_source_token' => 'wp.current.source.following.249',
        'following_cursor' => 'wp.returning.following.cursor.249',
        'following_current_view' => $followingView249,
        'following_current_input' => [
            ['import_id' => 40, 'name' => 'blogdescription', 'value' => 'after-next', 'autoload_flag' => 'yes', 'spawn_child' => true],
            ['import_id' => 41, 'name' => 'stylesheet', 'value' => 'twentytwentyfive', 'autoload_flag' => 'yes', 'spawn_child' => false],
            ['import_id' => 42, 'name' => 'template', 'value' => 'twentytwentyfive', 'autoload_flag' => 'yes', 'spawn_child' => true],
        ],
        'following_generation' => 'wp-following-current-249',
        'recursive_child_acknowledged_ordinals' => [0, 1],
        'recursive_child_source_token' => 'wp.current.source.recursive.child.249',
        'recursive_child_cursor' => 'wp.returning.recursive.child.cursor.249',
        'recursive_child_generation' => 'wp-recursive-child-current-249',
        'current_generation_next203' => 'wp.current.recursive.returning.generation.249',
        'expected_current_generation_next203' => 'wp.current.recursive.returning.generation.249',
        'current_handoff_cursor_next203' => 'wp.returning.current.handoff.cursor.249',
        'current_generation_commit_marker_next203' => 'wp.current.recursive.returning.commit.249',
        'auto_ack_current_generation_receipts_next203' => true,
        'current_source_drain_token_next209' => 'wp.current.source.drain.249',
        'current_view_cookie_next209' => 'main@view-cookie-249-current',
        'current_trigger_cookie_next209' => 'main@trigger-cookie-249-current',
        'auto_ack_current_source_watermarks_next209' => true,
        'current_source_yield_token_next212' => 'wp.current.source.yield.249',
        'current_view_yield_cursor_next212' => 'wp.returning.view.yield.cursor.249',
        'current_trigger_yield_cursor_next212' => 'wp.returning.trigger.yield.cursor.249',
        'auto_ack_current_source_yields_next212' => true,
        'current_source_epoch_next218' => 'wp.current.source.epoch.249',
        'current_view_epoch_next218' => 'wp.returning.view.epoch.cursor.249',
        'current_trigger_epoch_next218' => 'wp.returning.trigger.epoch.cursor.249',
        'auto_ack_current_source_epochs_next218' => true,
        'current_source_ticket_next222' => 'wp.current.source.ticket.249',
        'current_view_source_next222' => 'main@view-cookie-249-current',
        'current_trigger_source_next222' => 'main@trigger-cookie-249-current',
        'auto_ack_current_source_tickets_next222' => true,
        'current_source_cursor_source_close' => 'wp.returning.current.cursor.249',
        'current_source_close_token_source_close' => 'wp.current.source.close.249',
        'current_view_cookie_source_close' => 'main@view-cookie-249-current',
        'current_trigger_cookie_source_close' => 'main@trigger-cookie-249-current',
        'auto_ack_current_source_closures_source_close' => true,
        'current_source_upsert_cursor_next240' => 'wp.upsert.current.cursor.249',
        'current_view_upsert_cookie_next240' => 'main@view-cookie-249-current',
        'current_trigger_upsert_cookie_next240' => 'main@trigger-cookie-249-current',
        'upsert_conflict_columns_next240' => ['name'],
        'auto_ack_current_source_upserts_next240' => true,
        'current_source_view_cookie_next243' => 'main@view-cookie-249-current',
        'expected_current_source_view_cookie_next243' => 'main@view-cookie-249-current',
        'current_source_trigger_cookie_next243' => 'main@trigger-cookie-249-current',
        'expected_current_source_trigger_cookie_next243' => 'main@trigger-cookie-249-current',
        'next_source_view_cookie_next243' => 'main@view-cookie-249-next',
        'upsert_source_cursor_next243' => 'wp.upsert.source.cursor.249',
        'current_source_conflict_image_token_next246' => 'wp.current.source.conflict.image.249',
        'upsert_conflict_columns_next246' => ['name'],
        'upsert_excluded_columns_next246' => ['value', 'spawn_child'],
        'auto_ack_current_source_conflict_images_next246' => true,
        'current_source_assignment_token_next249' => 'wp.current.source.assignment.249',
        'upsert_assignment_columns_next249' => ['value', 'spawn_child'],
    ],
);

$receipts249 = static fn (): array => $plan249()['required_current_source_assignment_receipts_next249'];
$released249 = static fn (): array => $plan249(['auto_ack_current_source_assignments_next249' => true]);
$missing249 = static fn (): array => $plan249(['acknowledged_current_source_assignment_receipts_next249' => array_slice($receipts249(), 0, 1)]);
$unexpectedReceipt249 = '1234567890abcdef1234567890abcdef1234567890abcd';
$unexpected249 = static fn (): array => $plan249(['acknowledged_current_source_assignment_receipts_next249' => array_merge($receipts249(), [$unexpectedReceipt249])]);
$reversed249 = static fn (): array => $plan249(['acknowledged_current_source_assignment_receipts_next249' => array_reverse($receipts249())]);
$unordered249 = static fn (): array => $plan249(['require_current_source_assignment_order_next249' => false, 'acknowledged_current_source_assignment_receipts_next249' => array_reverse($receipts249())]);
$tokenHeld249 = static fn (): array => $plan249(['auto_ack_current_source_assignments_next249' => true, 'expected_current_source_assignment_token_next249' => 'wp.current.source.assignment.stale.249']);
$baseHeld249 = static fn (): array => $plan249(['auto_ack_current_source_assignments_next249' => true, 'auto_ack_current_source_conflict_images_next246' => false]);
$custom249 = static fn (): array => $plan249([
    'auto_ack_current_source_assignments_next249' => true,
    'current_source_assignment_token_next249' => 'wp.current.source.assignment.custom.249',
    'expected_current_source_assignment_token_next249' => 'wp.current.source.assignment.custom.249',
    'upsert_assignment_columns_next249' => ['value'],
]);

$cases249 = [
    'released status' => [static fn (): mixed => $released249()['status_next249'], 'trigger-recursive-view-upsert-current-source-next249-assignments-released'],
    'missing status' => [static fn (): mixed => $missing249()['status_next249'], 'trigger-recursive-view-upsert-current-source-next249-assignment-receipts-held'],
    'unexpected status' => [static fn (): mixed => $unexpected249()['status_next249'], 'trigger-recursive-view-upsert-current-source-next249-assignment-receipts-held'],
    'reversed status' => [static fn (): mixed => $reversed249()['status_next249'], 'trigger-recursive-view-upsert-current-source-next249-assignment-order-held'],
    'unordered reversed releases' => [static fn (): mixed => $unordered249()['status_next249'], 'trigger-recursive-view-upsert-current-source-next249-assignments-released'],
    'token held status' => [static fn (): mixed => $tokenHeld249()['status_next249'], 'trigger-recursive-view-upsert-current-source-next249-assignment-token-held'],
    'base held status' => [static fn (): mixed => $baseHeld249()['status_next249'], 'trigger-recursive-view-upsert-current-source-next249-base-held'],
    'base next246 released' => [static fn (): mixed => $released249()['base']['status_next246'], 'trigger-recursive-view-upsert-current-source-next246-conflict-images-released'],
    'base next246 held' => [static fn (): mixed => $baseHeld249()['base']['status_next246'], 'trigger-recursive-view-upsert-current-source-next246-conflict-image-receipts-held'],
    'savepoint retained' => [static fn (): mixed => $released249()['savepoint'], 'wp_recursive_view_249'],
    'base visible released' => [static fn (): mixed => $released249()['base_next_source_visible_next249'], true],
    'base visible held' => [static fn (): mixed => $baseHeld249()['base_next_source_visible_next249'], false],
    'token retained' => [static fn (): mixed => $released249()['current_source_assignment_token_next249'], 'wp.current.source.assignment.249'],
    'expected token defaults actual' => [static fn (): mixed => $released249()['expected_current_source_assignment_token_next249'], 'wp.current.source.assignment.249'],
    'token matches released' => [static fn (): mixed => $released249()['current_source_assignment_token_matches_next249'], true],
    'token mismatch detected' => [static fn (): mixed => $tokenHeld249()['current_source_assignment_token_matches_next249'], false],
    'custom token retained' => [static fn (): mixed => $custom249()['current_source_assignment_token_next249'], 'wp.current.source.assignment.custom.249'],
    'assignment columns retained' => [static fn (): mixed => $released249()['upsert_assignment_columns_next249'], ['value', 'spawn_child']],
    'custom assignment column retained' => [static fn (): mixed => $custom249()['upsert_assignment_columns_next249'], ['value']],
    'assignment image count' => [static fn (): mixed => count($released249()['current_source_assignment_images_next249']), 2],
    'required receipt count' => [static fn (): mixed => count($released249()['required_current_source_assignment_receipts_next249']), 2],
    'receipts are forty six hex' => [static fn (): mixed => array_map(static fn (string $v): int => preg_match('/^[a-f0-9]{46}$/', $v), $released249()['required_current_source_assignment_receipts_next249']), [1, 1]],
    'auto acknowledged equals required' => [static fn (): mixed => $released249()['acknowledged_current_source_assignment_receipts_next249'], $receipts249()],
    'missing acknowledged count' => [static fn (): mixed => count($missing249()['acknowledged_current_source_assignment_receipts_next249']), 1],
    'missing receipt recorded' => [static fn (): mixed => $missing249()['missing_current_source_assignment_receipts_next249'], [array_slice($receipts249(), -1)[0]]],
    'unexpected receipt recorded' => [static fn (): mixed => $unexpected249()['unexpected_current_source_assignment_receipts_next249'], [$unexpectedReceipt249]],
    'released missing empty' => [static fn (): mixed => $released249()['missing_current_source_assignment_receipts_next249'], []],
    'released unexpected empty' => [static fn (): mixed => $released249()['unexpected_current_source_assignment_receipts_next249'], []],
    'require order default' => [static fn (): mixed => $released249()['require_current_source_assignment_order_next249'], true],
    'order matches released' => [static fn (): mixed => $released249()['current_source_assignment_order_matches_next249'], true],
    'order mismatch reversed' => [static fn (): mixed => $reversed249()['current_source_assignment_order_matches_next249'], false],
    'unordered disables order' => [static fn (): mixed => $unordered249()['require_current_source_assignment_order_next249'], false],
    'assignments complete released' => [static fn (): mixed => $released249()['current_source_assignments_complete_next249'], true],
    'assignments incomplete missing' => [static fn (): mixed => $missing249()['current_source_assignments_complete_next249'], false],
    'assignments incomplete unexpected' => [static fn (): mixed => $unexpected249()['current_source_assignments_complete_next249'], false],
    'assignments incomplete token' => [static fn (): mixed => $tokenHeld249()['current_source_assignments_complete_next249'], false],
    'next visible released' => [static fn (): mixed => $released249()['next_source_visible_after_current_source_assignment_next249'], true],
    'next denied missing' => [static fn (): mixed => $missing249()['next_source_visible_after_current_source_assignment_next249'], false],
    'visible released count' => [static fn (): mixed => $released249()['visible_row_count_next249'], 4],
    'held released count' => [static fn (): mixed => $released249()['held_next_row_count_next249'], 0],
    'visible missing count current only' => [static fn (): mixed => $missing249()['visible_row_count_next249'], 2],
    'held missing count next only' => [static fn (): mixed => $missing249()['held_next_row_count_next249'], 2],
    'current phases' => [static fn (): mixed => array_values(array_unique(array_column($released249()['current_source_rows_next249'], 'assignment_phase_next249'))), ['current-assignment']],
    'next phases' => [static fn (): mixed => array_values(array_unique(array_column($released249()['attempted_next_source_rows_next249'], 'assignment_phase_next249'))), ['next-source']],
    'current receipts tagged' => [static fn (): mixed => array_column($released249()['current_source_rows_next249'], 'current_source_assignment_receipt_next249'), $receipts249()],
    'next receipts null' => [static fn (): mixed => array_values(array_unique(array_column($released249()['attempted_next_source_rows_next249'], 'current_source_assignment_receipt_next249'))), [null]],
    'current rows visible while held' => [static fn (): mixed => array_values(array_unique(array_column($missing249()['current_source_rows_next249'], 'visible_after_current_source_assignment_next249'))), [true]],
    'next rows held missing' => [static fn (): mixed => array_values(array_unique(array_column($missing249()['attempted_next_source_rows_next249'], 'visible_after_current_source_assignment_next249'))), [false]],
    'visible payload names released' => [static fn (): mixed => array_column($released249()['visible_returning_payloads_next249'], 'name'), ['blogdescription_child', 'template_child', 'home', 'next_plugin']],
    'held payload names missing' => [static fn (): mixed => array_column($missing249()['held_next_returning_payloads_next249'], 'name'), ['home', 'next_plugin']],
    'first assignment key' => [static fn (): mixed => $released249()['current_source_assignment_images_next249'][0]['conflict_key'], 'TEXT:blogdescription_child'],
    'first assignment value final' => [static fn (): mixed => $released249()['current_source_assignment_images_next249'][0]['assignments']['value']['final'], 'after-next_child'],
    'first assignment spawn final' => [static fn (): mixed => $released249()['current_source_assignment_images_next249'][0]['assignments']['spawn_child']['final'], false],
    'first assignment value source' => [static fn (): mixed => $released249()['current_source_assignment_images_next249'][0]['assignments']['value']['source'], 'excluded'],
    'custom assignment omits spawn' => [static fn (): mixed => array_keys($custom249()['current_source_assignment_images_next249'][0]['assignments']), ['value']],
    'blocked reasons released' => [static fn (): mixed => $released249()['blocked_reasons_next249'], []],
    'blocked reasons missing' => [static fn (): mixed => $missing249()['blocked_reasons_next249'], ['current-source-assignment-receipt-missing', 'current-source-assignment-receipt-order-mismatch']],
    'blocked reasons unexpected' => [static fn (): mixed => $unexpected249()['blocked_reasons_next249'], ['current-source-assignment-receipt-unexpected', 'current-source-assignment-receipt-order-mismatch']],
    'blocked reasons reversed' => [static fn (): mixed => $reversed249()['blocked_reasons_next249'], ['current-source-assignment-receipt-order-mismatch']],
    'blocked reasons token' => [static fn (): mixed => $tokenHeld249()['blocked_reasons_next249'], ['current-source-assignment-token-mismatch']],
    'held row reason copied' => [static fn (): mixed => $missing249()['held_next_source_rows_next249'][0]['held_by_current_source_assignment_reasons_next249'], ['current-source-assignment-receipt-missing', 'current-source-assignment-receipt-order-mismatch']],
    'plan decision released' => [static fn (): mixed => $released249()['current_source_assignment_plan_next249']['decision'], 'publish-next-source-after-current-upsert-assignments'],
    'plan decision held' => [static fn (): mixed => $missing249()['current_source_assignment_plan_next249']['decision'], 'hold-next-source-until-current-upsert-assignments'],
    'plan required echoed' => [static fn (): mixed => $released249()['current_source_assignment_plan_next249']['required_receipts'], $receipts249()],
    'plan next visible echoed' => [static fn (): mixed => $released249()['current_source_assignment_plan_next249']['next_source_visible'], true],
    'yield boundary released' => [static fn (): mixed => $released249()['yield_boundary_next249'], 'recursive-view-upsert-next249-current-assignments-then-next'],
    'yield boundary held' => [static fn (): mixed => $missing249()['yield_boundary_next249'], 'recursive-view-upsert-next249-current-assignments-fence-next'],
    'dependency closure marker' => [static fn (): mixed => $released249()['dependency_closure_next249'], 'no-new-support-component-reuses-native-recursive-view-upsert-current-source-conflict-images-and-adds-do-update-assignment-receipts'],
    'dependency includes next249' => [static fn (): mixed => in_array('sqlite-trigger-recursive-view-upsert-current-source-next249', $released249()['dependencies_next249'], true), true],
    'dependency includes assignment receipts' => [static fn (): mixed => in_array('sqlite-instead-of-view-upsert-do-update-assignment-receipts', $released249()['dependencies_next249'], true), true],
    'dependency includes next246' => [static fn (): mixed => in_array('sqlite-trigger-recursive-view-upsert-current-source-next246', $released249()['dependencies_next249'], true), true],
    'non overlap mentions next246' => [static fn (): mixed => str_contains($released249()['non_overlap_next249'], 'next246 conflict-image'), true],
    'bad token rejected' => [static fn (): mixed => $plan249(['current_source_assignment_token_next249' => 'bad token']), InvalidArgumentException::class],
    'bad expected token rejected' => [static fn (): mixed => $plan249(['expected_current_source_assignment_token_next249' => 'bad token']), InvalidArgumentException::class],
    'bad assignment columns rejected' => [static fn (): mixed => $plan249(['upsert_assignment_columns_next249' => []]), InvalidArgumentException::class],
    'bad assignment column name rejected' => [static fn (): mixed => $plan249(['upsert_assignment_columns_next249' => ['bad column']]), InvalidArgumentException::class],
    'bad receipt list rejected' => [static fn (): mixed => $plan249(['acknowledged_current_source_assignment_receipts_next249' => ['x' => $unexpectedReceipt249]]), InvalidArgumentException::class],
    'bad short receipt rejected' => [static fn (): mixed => $plan249(['acknowledged_current_source_assignment_receipts_next249' => ['abc']]), InvalidArgumentException::class],
    'bad non hex receipt rejected' => [static fn (): mixed => $plan249(['acknowledged_current_source_assignment_receipts_next249' => ['zzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzz']]), InvalidArgumentException::class],
];

$tests = [];
foreach ($cases249 as $name => [$callback, $expected]) {
    $tests['trigger recursive view upsert current source next249 ' . $name] = static function (TestRunner $t) use ($callback, $expected): void {
        if (is_string($expected) && is_a($expected, Throwable::class, true)) {
            $t->throws($expected, $callback);
            return;
        }
        $t->same($expected, $callback());
    };
}

return $tests;
