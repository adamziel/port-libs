<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteTriggerRecursiveViewUpsertCurrentSourceNextPlan;

$rows236 = [
    ['option_id' => 1, 'option_name' => 'siteurl', 'option_value' => 'https://old.test', 'autoload' => 'yes'],
    ['option_id' => 2, 'option_name' => 'home', 'option_value' => 'https://home.test', 'autoload' => 'yes'],
];
$currentView236 = [
    'name' => 'wp_recursive_option_import',
    'source' => 'main@view-cookie-236-current',
    'trigger' => 'wp_recursive_option_import_io_insert',
    'trigger_source' => 'main@trigger-cookie-236-current',
    'columns' => ['import_id', 'name', 'value', 'autoload_flag', 'spawn_child'],
    'mapping' => ['import_id' => 'option_id', 'name' => 'option_name', 'value' => 'option_value', 'autoload_flag' => 'autoload', 'spawn_child' => 'spawn_child'],
    'audit_label' => 'current-recursive-view-upsert-trigger-236',
];
$nextView236 = $currentView236;
$nextView236['source'] = 'main@view-cookie-236-next';
$nextView236['trigger_source'] = 'main@trigger-cookie-236-next';
$postResetView236 = $currentView236;
$postResetView236['source'] = 'main@view-cookie-236-post-reset';
$postResetView236['trigger_source'] = 'main@trigger-cookie-236-post-reset';
$followingView236 = $currentView236;
$followingView236['source'] = 'main@view-cookie-236-following';
$followingView236['trigger_source'] = 'main@trigger-cookie-236-following';
$currentInput236 = [
    ['import_id' => 10, 'name' => 'siteurl', 'value' => 'https://current.test', 'autoload_flag' => 'yes', 'spawn_child' => true],
    ['import_id' => 11, 'name' => 'current_plugin', 'value' => 'enabled', 'autoload_flag' => 'no', 'spawn_child' => true],
];
$nextInput236 = [
    ['import_id' => 20, 'name' => 'home', 'value' => 'https://next.test', 'autoload_flag' => 'yes', 'spawn_child' => true],
    ['import_id' => 21, 'name' => 'next_plugin', 'value' => 'active', 'autoload_flag' => 'no', 'spawn_child' => false],
];
$returning236 = [
    ['expr' => 'new.option_name', 'as' => 'name'],
    ['expr' => 'new.option_value', 'as' => 'value'],
    ['expr' => 'event', 'as' => 'event_name'],
    ['expr' => 'depth', 'as' => 'depth_value'],
    ['expr' => 'ordinal', 'as' => 'ordinal_value'],
    ['expr' => 'trigger_source', 'as' => 'trigger_source_alias'],
    ['expr' => 'spawn_child', 'as' => 'spawn_child'],
];

$plan236 = static fn (array $options = []): array => SQLiteTriggerRecursiveViewUpsertCurrentSourceNextPlan::executeCurrentRowImageReceipt(
    $rows236,
    $currentInput236,
    $nextInput236,
    $currentView236,
    $nextView236,
    $returning236,
    $options + [
        'key' => 'option_name',
        'savepoint' => 'wp_recursive_view_236',
        'cursor_name' => 'wp_recursive_view_returning_cursor_236',
        'admit_next_source' => true,
        'rollback_token' => 'wp.rollback.current.236',
        'reset_generation' => 'wp-current-reset-236',
        'post_reset_current_source_token' => 'wp.current.source.postreset.236',
        'post_reset_cursor' => 'wp.returning.postreset.cursor.236',
        'post_reset_view' => $postResetView236,
        'post_reset_input' => [
            ['import_id' => 30, 'name' => 'siteurl', 'value' => 'https://fresh.test', 'autoload_flag' => 'yes', 'spawn_child' => false],
            ['import_id' => 31, 'name' => 'rewrite_rules', 'value' => 'fresh-rules', 'autoload_flag' => 'no', 'spawn_child' => false],
        ],
        'fresh_acknowledged_ordinals' => [0, 1],
        'next_source_token' => 'wp.next.source.236',
        'next_cursor' => 'wp.returning.next.cursor.236',
        'next_acknowledged_ordinals' => [0, 1],
        'close_next_cursor' => 'wp.returning.next.cursor.236',
        'following_current_source_token' => 'wp.current.source.following.236',
        'following_cursor' => 'wp.returning.following.cursor.236',
        'following_current_view' => $followingView236,
        'following_current_input' => [
            ['import_id' => 40, 'name' => 'blogdescription', 'value' => 'after-next', 'autoload_flag' => 'yes', 'spawn_child' => true],
            ['import_id' => 41, 'name' => 'stylesheet', 'value' => 'twentytwentyfive', 'autoload_flag' => 'yes', 'spawn_child' => false],
            ['import_id' => 42, 'name' => 'template', 'value' => 'twentytwentyfive', 'autoload_flag' => 'yes', 'spawn_child' => true],
        ],
        'recursive_child_acknowledged_ordinals' => [0, 1],
        'recursive_child_source_token' => 'wp.current.source.recursive.child.236',
        'recursive_child_cursor' => 'wp.returning.recursive.child.cursor.236',
        'recursive_child_generation' => 'wp-recursive-child-current-236',
        'current_generation_next203' => 'wp.current.recursive.returning.generation.236',
        'expected_current_generation_next203' => 'wp.current.recursive.returning.generation.236',
        'current_handoff_cursor_next203' => 'wp.returning.current.handoff.cursor.236',
        'current_generation_commit_marker_next203' => 'wp.current.recursive.returning.commit.236',
        'auto_ack_current_generation_receipts_next203' => true,
        'current_source_drain_token_next209' => 'wp.current.source.drain.236',
        'current_view_cookie_next209' => 'main@view-cookie-236-current',
        'current_trigger_cookie_next209' => 'main@trigger-cookie-236-current',
        'auto_ack_current_source_watermarks_next209' => true,
        'auto_ack_current_source_yields_next212' => true,
        'current_source_epoch_next218' => 'wp.current.source.epoch.236',
        'current_view_epoch_next218' => 'wp.returning.view.epoch.cursor.236',
        'current_trigger_epoch_next218' => 'wp.returning.trigger.epoch.cursor.236',
        'auto_ack_current_source_epochs_next218' => true,
        'auto_ack_current_returning_source_seals_next224' => true,
        'current_returning_source_generation_next229' => 'wp.current.returning.source.generation.236',
        'current_returning_view_generation_next229' => 'main@view-cookie-236-current',
        'current_returning_trigger_generation_next229' => 'main@trigger-cookie-236-current',
        'auto_ack_current_returning_generation_seals_next229' => true,
        'current_upsert_source_token_next233' => 'wp.current.upsert.source.236',
        'current_upsert_view_source_next233' => 'main@view-cookie-236-current',
        'current_upsert_trigger_source_next233' => 'main@trigger-cookie-236-current',
        'current_upsert_conflict_target_next233' => ['option_name'],
        'current_upsert_update_columns_next233' => ['option_value', 'autoload'],
        'auto_ack_current_upsert_seals_next233' => true,
        'current_upsert_row_image_token_next236' => 'wp.current.upsert.row.image.236',
        'current_upsert_row_image_view_source_next236' => 'main@view-cookie-236-current',
        'current_upsert_row_image_trigger_source_next236' => 'main@trigger-cookie-236-current',
    ],
);

$receipts236 = static fn (): array => $plan236()['required_current_upsert_row_image_receipts_next236'];
$released236 = static fn (): array => $plan236(['auto_ack_current_upsert_row_image_receipts_next236' => true]);
$missing236 = static fn (): array => $plan236(['acknowledged_current_upsert_row_image_receipts_next236' => array_slice($receipts236(), 0, 1)]);
$unexpectedReceipt236 = 'abcdefabcdefabcdefabcdefabcdefabcdefabcdefabcdef';
$unexpected236 = static fn (): array => $plan236(['acknowledged_current_upsert_row_image_receipts_next236' => array_merge($receipts236(), [$unexpectedReceipt236])]);
$reversed236 = static fn (): array => $plan236(['acknowledged_current_upsert_row_image_receipts_next236' => array_reverse($receipts236())]);
$unordered236 = static fn (): array => $plan236(['require_current_upsert_row_image_order_next236' => false, 'acknowledged_current_upsert_row_image_receipts_next236' => array_reverse($receipts236())]);
$tokenHeld236 = static fn (): array => $plan236(['auto_ack_current_upsert_row_image_receipts_next236' => true, 'expected_current_upsert_row_image_token_next236' => 'wp.current.upsert.row.image.stale.236']);
$viewHeld236 = static fn (): array => $plan236(['auto_ack_current_upsert_row_image_receipts_next236' => true, 'expected_current_upsert_row_image_view_source_next236' => 'main@view-cookie-236-stale']);
$triggerHeld236 = static fn (): array => $plan236(['auto_ack_current_upsert_row_image_receipts_next236' => true, 'expected_current_upsert_row_image_trigger_source_next236' => 'main@trigger-cookie-236-stale']);
$baseHeld236 = static fn (): array => $plan236(['auto_ack_current_upsert_row_image_receipts_next236' => true, 'auto_ack_current_upsert_seals_next233' => false]);
$custom236 = static fn (): array => $plan236([
    'auto_ack_current_upsert_row_image_receipts_next236' => true,
    'current_upsert_row_image_token_next236' => 'wp.current.upsert.row.image.custom.236',
    'current_upsert_row_image_view_source_next236' => 'main@view-cookie-236-custom',
    'current_upsert_row_image_trigger_source_next236' => 'main@trigger-cookie-236-custom',
]);

$cases236 = [
    'released status' => [static fn (): mixed => $released236()['status_next236'], 'trigger-recursive-view-upsert-current-source-next236-row-image-released'],
    'missing status' => [static fn (): mixed => $missing236()['status_next236'], 'trigger-recursive-view-upsert-current-source-next236-row-image-held'],
    'unexpected status' => [static fn (): mixed => $unexpected236()['status_next236'], 'trigger-recursive-view-upsert-current-source-next236-row-image-held'],
    'reversed status' => [static fn (): mixed => $reversed236()['status_next236'], 'trigger-recursive-view-upsert-current-source-next236-row-image-order-held'],
    'unordered reversed releases' => [static fn (): mixed => $unordered236()['status_next236'], 'trigger-recursive-view-upsert-current-source-next236-row-image-released'],
    'token held status' => [static fn (): mixed => $tokenHeld236()['status_next236'], 'trigger-recursive-view-upsert-current-source-next236-row-image-token-held'],
    'view held status' => [static fn (): mixed => $viewHeld236()['status_next236'], 'trigger-recursive-view-upsert-current-source-next236-view-source-held'],
    'trigger held status' => [static fn (): mixed => $triggerHeld236()['status_next236'], 'trigger-recursive-view-upsert-current-source-next236-trigger-source-held'],
    'base held status' => [static fn (): mixed => $baseHeld236()['status_next236'], 'trigger-recursive-view-upsert-current-source-next236-base-held'],
    'savepoint retained' => [static fn (): mixed => $released236()['savepoint'], 'wp_recursive_view_236'],
    'base next233 released' => [static fn (): mixed => $released236()['base']['status_next233'], 'trigger-recursive-view-upsert-current-source-next233-upsert-sealed'],
    'base visible released' => [static fn (): mixed => $released236()['base_next_source_visible_next236'], true],
    'base visible held' => [static fn (): mixed => $baseHeld236()['base_next_source_visible_next236'], false],
    'image token retained' => [static fn (): mixed => $released236()['current_upsert_row_image_token_next236'], 'wp.current.upsert.row.image.236'],
    'custom image token retained' => [static fn (): mixed => $custom236()['current_upsert_row_image_token_next236'], 'wp.current.upsert.row.image.custom.236'],
    'view source retained' => [static fn (): mixed => $released236()['current_upsert_row_image_view_source_next236'], 'main@view-cookie-236-current'],
    'custom view source retained' => [static fn (): mixed => $custom236()['current_upsert_row_image_view_source_next236'], 'main@view-cookie-236-custom'],
    'trigger source retained' => [static fn (): mixed => $released236()['current_upsert_row_image_trigger_source_next236'], 'main@trigger-cookie-236-current'],
    'custom trigger source retained' => [static fn (): mixed => $custom236()['current_upsert_row_image_trigger_source_next236'], 'main@trigger-cookie-236-custom'],
    'token matches released' => [static fn (): mixed => $released236()['current_upsert_row_image_token_matches_next236'], true],
    'token mismatch detected' => [static fn (): mixed => $tokenHeld236()['current_upsert_row_image_token_matches_next236'], false],
    'view matches released' => [static fn (): mixed => $released236()['current_upsert_row_image_view_source_matches_next236'], true],
    'view mismatch detected' => [static fn (): mixed => $viewHeld236()['current_upsert_row_image_view_source_matches_next236'], false],
    'trigger matches released' => [static fn (): mixed => $released236()['current_upsert_row_image_trigger_source_matches_next236'], true],
    'trigger mismatch detected' => [static fn (): mixed => $triggerHeld236()['current_upsert_row_image_trigger_source_matches_next236'], false],
    'row image total' => [static fn (): mixed => $released236()['current_upsert_row_images_next236']['total'], 2],
    'row image insert count' => [static fn (): mixed => $released236()['current_upsert_row_images_next236']['insert'], 2],
    'row image update count' => [static fn (): mixed => $released236()['current_upsert_row_images_next236']['update'], 0],
    'row image recursive count' => [static fn (): mixed => $released236()['current_upsert_row_images_next236']['recursive'], 2],
    'row image max depth' => [static fn (): mixed => $released236()['current_upsert_row_images_next236']['max_depth'], 1],
    'row image names' => [static fn (): mixed => $released236()['current_upsert_row_images_next236']['names'], ['blogdescription_child', 'template_child']],
    'row image events' => [static fn (): mixed => $released236()['current_upsert_row_images_next236']['events'], ['insert', 'insert']],
    'has row images' => [static fn (): mixed => $released236()['current_upsert_row_image_has_rows_next236'], true],
    'required receipt count' => [static fn (): mixed => count($released236()['required_current_upsert_row_image_receipts_next236']), 2],
    'receipts are forty eight hex' => [static fn (): mixed => array_map(static fn (string $v): int => preg_match('/^[a-f0-9]{48}$/', $v), $released236()['required_current_upsert_row_image_receipts_next236']), [1, 1]],
    'auto acknowledged equals required' => [static fn (): mixed => $released236()['acknowledged_current_upsert_row_image_receipts_next236'], $receipts236()],
    'missing acknowledged count' => [static fn (): mixed => count($missing236()['acknowledged_current_upsert_row_image_receipts_next236']), 1],
    'missing receipt recorded' => [static fn (): mixed => $missing236()['missing_current_upsert_row_image_receipts_next236'], [array_slice($receipts236(), -1)[0]]],
    'unexpected receipt recorded' => [static fn (): mixed => $unexpected236()['unexpected_current_upsert_row_image_receipts_next236'], [$unexpectedReceipt236]],
    'released missing empty' => [static fn (): mixed => $released236()['missing_current_upsert_row_image_receipts_next236'], []],
    'released unexpected empty' => [static fn (): mixed => $released236()['unexpected_current_upsert_row_image_receipts_next236'], []],
    'require order default' => [static fn (): mixed => $released236()['require_current_upsert_row_image_order_next236'], true],
    'order matches released' => [static fn (): mixed => $released236()['current_upsert_row_image_order_matches_next236'], true],
    'order mismatch reversed' => [static fn (): mixed => $reversed236()['current_upsert_row_image_order_matches_next236'], false],
    'unordered disables order' => [static fn (): mixed => $unordered236()['require_current_upsert_row_image_order_next236'], false],
    'unordered order considered matched' => [static fn (): mixed => $unordered236()['current_upsert_row_image_order_matches_next236'], true],
    'complete released' => [static fn (): mixed => $released236()['current_upsert_row_image_complete_next236'], true],
    'complete missing false' => [static fn (): mixed => $missing236()['current_upsert_row_image_complete_next236'], false],
    'complete unexpected false' => [static fn (): mixed => $unexpected236()['current_upsert_row_image_complete_next236'], false],
    'complete reversed false' => [static fn (): mixed => $reversed236()['current_upsert_row_image_complete_next236'], false],
    'next visible released' => [static fn (): mixed => $released236()['next_source_visible_after_current_upsert_row_image_next236'], true],
    'next denied missing' => [static fn (): mixed => $missing236()['next_source_visible_after_current_upsert_row_image_next236'], false],
    'next denied unexpected' => [static fn (): mixed => $unexpected236()['next_source_visible_after_current_upsert_row_image_next236'], false],
    'next denied reversed' => [static fn (): mixed => $reversed236()['next_source_visible_after_current_upsert_row_image_next236'], false],
    'current row count' => [static fn (): mixed => $released236()['current_source_row_count_next236'], 2],
    'attempted next row count' => [static fn (): mixed => $released236()['attempted_next_source_row_count_next236'], 2],
    'visible released count' => [static fn (): mixed => $released236()['visible_row_count_next236'], 4],
    'held missing count' => [static fn (): mixed => $missing236()['held_next_row_count_next236'], 2],
    'visible payload names released' => [static fn (): mixed => array_column($released236()['visible_returning_payloads_next236'], 'name'), ['blogdescription_child', 'template_child', 'home', 'next_plugin']],
    'held payload names missing' => [static fn (): mixed => array_column($missing236()['held_next_returning_payloads_next236'], 'name'), ['home', 'next_plugin']],
    'current phases' => [static fn (): mixed => array_values(array_unique(array_column($released236()['current_source_rows_next236'], 'upsert_row_image_phase_next236'))), ['current']],
    'next phases' => [static fn (): mixed => array_values(array_unique(array_column($released236()['attempted_next_source_rows_next236'], 'upsert_row_image_phase_next236'))), ['next']],
    'current receipts tagged' => [static fn (): mixed => array_column($released236()['current_source_rows_next236'], 'current_upsert_row_image_receipt_next236'), $receipts236()],
    'next receipts null' => [static fn (): mixed => array_values(array_unique(array_column($released236()['attempted_next_source_rows_next236'], 'current_upsert_row_image_receipt_next236'))), [null]],
    'blocked reasons missing' => [static fn (): mixed => $missing236()['blocked_reasons_next236'], ['current-upsert-row-image-receipt-missing']],
    'blocked reasons unexpected' => [static fn (): mixed => $unexpected236()['blocked_reasons_next236'], ['current-upsert-row-image-receipt-unexpected']],
    'blocked reasons reversed' => [static fn (): mixed => $reversed236()['blocked_reasons_next236'], ['current-upsert-row-image-receipt-order-mismatch']],
    'blocked reasons token' => [static fn (): mixed => $tokenHeld236()['blocked_reasons_next236'], ['current-upsert-row-image-token-mismatch']],
    'blocked reasons view' => [static fn (): mixed => $viewHeld236()['blocked_reasons_next236'], ['current-upsert-row-image-view-source-mismatch']],
    'blocked reasons trigger' => [static fn (): mixed => $triggerHeld236()['blocked_reasons_next236'], ['current-upsert-row-image-trigger-source-mismatch']],
    'plan decision released' => [static fn (): mixed => $released236()['current_upsert_row_image_plan_next236']['decision'], 'publish-next-source-after-current-view-upsert-row-images'],
    'plan decision missing' => [static fn (): mixed => $missing236()['current_upsert_row_image_plan_next236']['decision'], 'hold-next-source-until-current-view-upsert-row-images'],
    'plan required echoed' => [static fn (): mixed => $released236()['current_upsert_row_image_plan_next236']['required_receipts'], $receipts236()],
    'plan row image names echoed' => [static fn (): mixed => $released236()['current_upsert_row_image_plan_next236']['row_images']['names'], ['blogdescription_child', 'template_child']],
    'boundary released' => [static fn (): mixed => $released236()['yield_boundary_next236'], 'recursive-view-upsert-next236-current-row-images-then-next'],
    'boundary held' => [static fn (): mixed => $missing236()['yield_boundary_next236'], 'recursive-view-upsert-next236-current-row-image-fences-next'],
    'dependency closure marker' => [static fn (): mixed => $released236()['dependency_closure_next236'], 'no-new-support-component-reuses-native-recursive-view-upsert-current-source-and-adds-row-image-receipts'],
    'dependency includes next236' => [static fn (): mixed => in_array('sqlite-trigger-recursive-view-upsert-current-source-next236', $released236()['dependencies_next236'], true), true],
    'dependency includes row images' => [static fn (): mixed => in_array('sqlite-current-view-upsert-row-image-receipts', $released236()['dependencies_next236'], true), true],
    'dependency includes next233' => [static fn (): mixed => in_array('sqlite-trigger-recursive-view-upsert-current-source-next233', $released236()['dependencies_next236'], true), true],
    'non overlap mentions next233' => [static fn (): mixed => str_contains($released236()['non_overlap_next236'], 'next233 conflict-target'), true],
    'bad image token rejected' => [static fn (): mixed => $plan236(['current_upsert_row_image_token_next236' => 'bad token']), InvalidArgumentException::class],
    'bad view source rejected' => [static fn (): mixed => $plan236(['current_upsert_row_image_view_source_next236' => 'bad source']), InvalidArgumentException::class],
    'bad trigger source rejected' => [static fn (): mixed => $plan236(['current_upsert_row_image_trigger_source_next236' => 'bad source']), InvalidArgumentException::class],
    'bad receipt list rejected' => [static fn (): mixed => $plan236(['acknowledged_current_upsert_row_image_receipts_next236' => ['x' => $unexpectedReceipt236]]), InvalidArgumentException::class],
    'bad short receipt rejected' => [static fn (): mixed => $plan236(['acknowledged_current_upsert_row_image_receipts_next236' => ['abc']]), InvalidArgumentException::class],
    'bad non hex receipt rejected' => [static fn (): mixed => $plan236(['acknowledged_current_upsert_row_image_receipts_next236' => ['zzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzz']]), InvalidArgumentException::class],
];

$tests = [];
foreach ($cases236 as $name => [$callback, $expected]) {
    $tests['trigger recursive view upsert current source next236 ' . $name] = static function (TestRunner $t) use ($callback, $expected): void {
        if (is_string($expected) && is_a($expected, Throwable::class, true)) {
            $t->throws($expected, $callback);
            return;
        }
        $t->same($expected, $callback());
    };
}

return $tests;
