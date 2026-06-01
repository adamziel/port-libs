<?php

declare(strict_types=1);

foreach (glob(__DIR__ . '/../src/SQLiteTriggerRecursiveViewReturningCurrentSourceNext*.php') ?: [] as $file) {
    require_once $file;
}
require_once __DIR__ . '/../src/SQLiteTriggerRecursiveViewUpsertCurrentSourceNextPlan.php';
require_once __DIR__ . '/../src/SQLiteTriggerRecursiveViewUpsertCurrentSourceNextPlan.php';
require_once __DIR__ . '/../src/SQLiteTriggerRecursiveViewUpsertCurrentSourceNextPlan.php';

use PortLibs\LibSqlite\SQLiteTriggerRecursiveViewUpsertCurrentSourceNextPlan;

$rows246 = [
    ['setting_id' => 1, 'key_name' => 'base_url', 'key_value' => 'https://old.test', 'load_policy' => 'yes'],
    ['setting_id' => 2, 'key_name' => 'landing_url', 'key_value' => 'https://landing_url.test', 'load_policy' => 'yes'],
];
$currentView246 = [
    'name' => 'app_recursive_setting_import',
    'source' => 'main@view-cookie-246-current',
    'trigger' => 'app_recursive_setting_import_io_insert',
    'trigger_source' => 'main@trigger-cookie-246-current',
    'columns' => ['import_id', 'name', 'value', 'load_policy_flag', 'spawn_child'],
    'mapping' => ['import_id' => 'setting_id', 'name' => 'key_name', 'value' => 'key_value', 'load_policy_flag' => 'load_policy', 'spawn_child' => 'spawn_child'],
    'audit_label' => 'current-recursive-view-upsert-246',
];
$nextView246 = $currentView246;
$nextView246['source'] = 'main@view-cookie-246-next';
$nextView246['trigger_source'] = 'main@trigger-cookie-246-next';
$postResetView246 = $currentView246;
$postResetView246['source'] = 'main@view-cookie-246-post-reset';
$postResetView246['trigger_source'] = 'main@trigger-cookie-246-post-reset';
$followingView246 = $currentView246;
$followingView246['source'] = 'main@view-cookie-246-following';
$followingView246['trigger_source'] = 'main@trigger-cookie-246-following';
$currentInput246 = [
    ['import_id' => 10, 'name' => 'base_url', 'value' => 'https://current.test', 'load_policy_flag' => 'yes', 'spawn_child' => true],
    ['import_id' => 11, 'name' => 'current_module', 'value' => 'enabled', 'load_policy_flag' => 'no', 'spawn_child' => true],
];
$nextInput246 = [
    ['import_id' => 20, 'name' => 'landing_url', 'value' => 'https://next.test', 'load_policy_flag' => 'yes', 'spawn_child' => true],
    ['import_id' => 21, 'name' => 'next_module', 'value' => 'active', 'load_policy_flag' => 'no', 'spawn_child' => false],
];
$returning246 = [
    ['expr' => 'new.key_name', 'as' => 'name'],
    ['expr' => 'new.key_value', 'as' => 'value'],
    ['expr' => 'event', 'as' => 'event_name'],
    ['expr' => 'depth', 'as' => 'depth_value'],
    ['expr' => 'ordinal', 'as' => 'ordinal_value'],
    ['expr' => 'trigger_source', 'as' => 'trigger_source_alias'],
    ['expr' => 'spawn_child', 'as' => 'spawn_child'],
];

$plan246 = static fn (array $options = []): array => SQLiteTriggerRecursiveViewUpsertCurrentSourceNextPlan::executeCurrentConflictImageReceipt(
    $rows246,
    $currentInput246,
    $nextInput246,
    $currentView246,
    $nextView246,
    $returning246,
    $options + [
        'key' => 'key_name',
        'savepoint' => 'app_recursive_view_246',
        'cursor_name' => 'app_recursive_view_returning_cursor_246',
        'admit_next_source' => true,
        'rollback_token' => 'app.rollback.current.246',
        'reset_generation' => 'app-current-reset-246',
        'post_reset_current_source_token' => 'app.current.source.postreset.246',
        'post_reset_cursor' => 'app.returning.postreset.cursor.246',
        'post_reset_view' => $postResetView246,
        'post_reset_input' => [
            ['import_id' => 30, 'name' => 'base_url', 'value' => 'https://fresh.test', 'load_policy_flag' => 'yes', 'spawn_child' => false],
            ['import_id' => 31, 'name' => 'routing_rules', 'value' => 'fresh-rules', 'load_policy_flag' => 'no', 'spawn_child' => false],
        ],
        'fresh_acknowledged_ordinals' => [0, 1],
        'next_source_token' => 'app.next.source.246',
        'next_cursor' => 'app.returning.next.cursor.246',
        'next_acknowledged_ordinals' => [0, 1],
        'close_next_cursor' => 'app.returning.next.cursor.246',
        'following_current_source_token' => 'app.current.source.following.246',
        'following_cursor' => 'app.returning.following.cursor.246',
        'following_current_view' => $followingView246,
        'following_current_input' => [
            ['import_id' => 40, 'name' => 'app_summary', 'value' => 'after-next', 'load_policy_flag' => 'yes', 'spawn_child' => true],
            ['import_id' => 41, 'name' => 'theme_style_key', 'value' => 'modern_theme', 'load_policy_flag' => 'yes', 'spawn_child' => false],
            ['import_id' => 42, 'name' => 'template', 'value' => 'modern_theme', 'load_policy_flag' => 'yes', 'spawn_child' => true],
        ],
        'following_generation' => 'app-following-current-246',
        'recursive_child_acknowledged_ordinals' => [0, 1],
        'recursive_child_source_token' => 'app.current.source.recursive.child.246',
        'recursive_child_cursor' => 'app.returning.recursive.child.cursor.246',
        'recursive_child_generation' => 'app-recursive-child-current-246',
        'current_generation_next203' => 'app.current.recursive.returning.generation.246',
        'expected_current_generation_next203' => 'app.current.recursive.returning.generation.246',
        'current_handoff_cursor_next203' => 'app.returning.current.handoff.cursor.246',
        'current_generation_commit_marker_next203' => 'app.current.recursive.returning.commit.246',
        'auto_ack_current_generation_receipts_next203' => true,
        'current_source_drain_token_next209' => 'app.current.source.drain.246',
        'current_view_cookie_next209' => 'main@view-cookie-246-current',
        'current_trigger_cookie_next209' => 'main@trigger-cookie-246-current',
        'auto_ack_current_source_watermarks_next209' => true,
        'current_source_yield_token_next212' => 'app.current.source.yield.246',
        'current_view_yield_cursor_next212' => 'app.returning.view.yield.cursor.246',
        'current_trigger_yield_cursor_next212' => 'app.returning.trigger.yield.cursor.246',
        'auto_ack_current_source_yields_next212' => true,
        'current_source_epoch_next218' => 'app.current.source.epoch.246',
        'current_view_epoch_next218' => 'app.returning.view.epoch.cursor.246',
        'current_trigger_epoch_next218' => 'app.returning.trigger.epoch.cursor.246',
        'auto_ack_current_source_epochs_next218' => true,
        'current_source_ticket_next222' => 'app.current.source.ticket.246',
        'current_view_source_next222' => 'main@view-cookie-246-current',
        'current_trigger_source_next222' => 'main@trigger-cookie-246-current',
        'auto_ack_current_source_tickets_next222' => true,
        'current_source_cursor_source_close' => 'app.returning.current.cursor.246',
        'current_source_close_token_source_close' => 'app.current.source.close.246',
        'current_view_cookie_source_close' => 'main@view-cookie-246-current',
        'current_trigger_cookie_source_close' => 'main@trigger-cookie-246-current',
        'auto_ack_current_source_closures_source_close' => true,
        'current_source_upsert_cursor_next240' => 'app.upsert.current.cursor.246',
        'current_view_upsert_cookie_next240' => 'main@view-cookie-246-current',
        'current_trigger_upsert_cookie_next240' => 'main@trigger-cookie-246-current',
        'upsert_conflict_columns_next240' => ['name'],
        'auto_ack_current_source_upserts_next240' => true,
        'current_source_view_cookie_next243' => 'main@view-cookie-246-current',
        'expected_current_source_view_cookie_next243' => 'main@view-cookie-246-current',
        'current_source_trigger_cookie_next243' => 'main@trigger-cookie-246-current',
        'expected_current_source_trigger_cookie_next243' => 'main@trigger-cookie-246-current',
        'next_source_view_cookie_next243' => 'main@view-cookie-246-next',
        'upsert_source_cursor_next243' => 'app.upsert.source.cursor.246',
        'current_source_conflict_image_token_next246' => 'app.current.source.conflict.image.246',
        'upsert_conflict_columns_next246' => ['name'],
        'upsert_excluded_columns_next246' => ['value', 'spawn_child'],
    ],
);

$receipts246 = static fn (): array => $plan246()['required_current_source_conflict_image_receipts_next246'];
$released246 = static fn (): array => $plan246(['auto_ack_current_source_conflict_images_next246' => true]);
$missing246 = static fn (): array => $plan246(['acknowledged_current_source_conflict_image_receipts_next246' => array_slice($receipts246(), 0, 1)]);
$unexpectedReceipt246 = 'abcdefabcdefabcdefabcdefabcdefabcdefabcdefabcd';
$unexpected246 = static fn (): array => $plan246(['acknowledged_current_source_conflict_image_receipts_next246' => array_merge($receipts246(), [$unexpectedReceipt246])]);
$reversed246 = static fn (): array => $plan246(['acknowledged_current_source_conflict_image_receipts_next246' => array_reverse($receipts246())]);
$unordered246 = static fn (): array => $plan246(['require_current_source_conflict_image_order_next246' => false, 'acknowledged_current_source_conflict_image_receipts_next246' => array_reverse($receipts246())]);
$tokenHeld246 = static fn (): array => $plan246(['auto_ack_current_source_conflict_images_next246' => true, 'expected_current_source_conflict_image_token_next246' => 'app.current.source.conflict.image.stale.246']);
$baseHeld246 = static fn (): array => $plan246(['auto_ack_current_source_conflict_images_next246' => true, 'expected_current_source_view_cookie_next243' => 'main@view-cookie-246-stale']);
$custom246 = static fn (): array => $plan246([
    'auto_ack_current_source_conflict_images_next246' => true,
    'current_source_conflict_image_token_next246' => 'app.current.source.conflict.image.custom.246',
    'expected_current_source_conflict_image_token_next246' => 'app.current.source.conflict.image.custom.246',
    'upsert_excluded_columns_next246' => ['value'],
]);

$cases246 = [
    'released status' => [static fn (): mixed => $released246()['status_next246'], 'trigger-recursive-view-upsert-current-source-next246-conflict-images-released'],
    'missing status' => [static fn (): mixed => $missing246()['status_next246'], 'trigger-recursive-view-upsert-current-source-next246-conflict-image-receipts-held'],
    'unexpected status' => [static fn (): mixed => $unexpected246()['status_next246'], 'trigger-recursive-view-upsert-current-source-next246-conflict-image-receipts-held'],
    'reversed status' => [static fn (): mixed => $reversed246()['status_next246'], 'trigger-recursive-view-upsert-current-source-next246-conflict-image-order-held'],
    'unordered reversed releases' => [static fn (): mixed => $unordered246()['status_next246'], 'trigger-recursive-view-upsert-current-source-next246-conflict-images-released'],
    'token held status' => [static fn (): mixed => $tokenHeld246()['status_next246'], 'trigger-recursive-view-upsert-current-source-next246-conflict-image-token-held'],
    'base held status' => [static fn (): mixed => $baseHeld246()['status_next246'], 'trigger-recursive-view-upsert-current-source-next246-base-held'],
    'base next243 released' => [static fn (): mixed => $released246()['base']['status_next243'], 'trigger-recursive-view-upsert-current-source-next243-source-released'],
    'base next243 held' => [static fn (): mixed => $baseHeld246()['base']['status_next243'], 'trigger-recursive-view-upsert-current-source-next243-view-source-held'],
    'savepoint retained' => [static fn (): mixed => $released246()['savepoint'], 'app_recursive_view_246'],
    'base visible released' => [static fn (): mixed => $released246()['base_next_source_visible_next246'], true],
    'base visible held' => [static fn (): mixed => $baseHeld246()['base_next_source_visible_next246'], false],
    'token retained' => [static fn (): mixed => $released246()['current_source_conflict_image_token_next246'], 'app.current.source.conflict.image.246'],
    'expected token defaults actual' => [static fn (): mixed => $released246()['expected_current_source_conflict_image_token_next246'], 'app.current.source.conflict.image.246'],
    'token matches released' => [static fn (): mixed => $released246()['current_source_conflict_image_token_matches_next246'], true],
    'token mismatch detected' => [static fn (): mixed => $tokenHeld246()['current_source_conflict_image_token_matches_next246'], false],
    'custom token retained' => [static fn (): mixed => $custom246()['current_source_conflict_image_token_next246'], 'app.current.source.conflict.image.custom.246'],
    'conflict columns retained' => [static fn (): mixed => $released246()['upsert_conflict_columns_next246'], ['name']],
    'excluded columns retained' => [static fn (): mixed => $released246()['upsert_excluded_columns_next246'], ['value', 'spawn_child']],
    'custom excluded column retained' => [static fn (): mixed => $custom246()['upsert_excluded_columns_next246'], ['value']],
    'required receipt count' => [static fn (): mixed => count($released246()['required_current_source_conflict_image_receipts_next246']), 2],
    'receipts are forty six hex' => [static fn (): mixed => array_map(static fn (string $v): int => preg_match('/^[a-f0-9]{46}$/', $v), $released246()['required_current_source_conflict_image_receipts_next246']), [1, 1]],
    'auto acknowledged equals required' => [static fn (): mixed => $released246()['acknowledged_current_source_conflict_image_receipts_next246'], $receipts246()],
    'missing acknowledged count' => [static fn (): mixed => count($missing246()['acknowledged_current_source_conflict_image_receipts_next246']), 1],
    'missing receipt recorded' => [static fn (): mixed => $missing246()['missing_current_source_conflict_image_receipts_next246'], [array_slice($receipts246(), -1)[0]]],
    'unexpected receipt recorded' => [static fn (): mixed => $unexpected246()['unexpected_current_source_conflict_image_receipts_next246'], [$unexpectedReceipt246]],
    'released missing empty' => [static fn (): mixed => $released246()['missing_current_source_conflict_image_receipts_next246'], []],
    'released unexpected empty' => [static fn (): mixed => $released246()['unexpected_current_source_conflict_image_receipts_next246'], []],
    'require order default' => [static fn (): mixed => $released246()['require_current_source_conflict_image_order_next246'], true],
    'order matches released' => [static fn (): mixed => $released246()['current_source_conflict_image_order_matches_next246'], true],
    'order mismatch reversed' => [static fn (): mixed => $reversed246()['current_source_conflict_image_order_matches_next246'], false],
    'unordered disables order' => [static fn (): mixed => $unordered246()['require_current_source_conflict_image_order_next246'], false],
    'images complete released' => [static fn (): mixed => $released246()['current_source_conflict_images_complete_next246'], true],
    'images incomplete missing' => [static fn (): mixed => $missing246()['current_source_conflict_images_complete_next246'], false],
    'images incomplete unexpected' => [static fn (): mixed => $unexpected246()['current_source_conflict_images_complete_next246'], false],
    'images incomplete token' => [static fn (): mixed => $tokenHeld246()['current_source_conflict_images_complete_next246'], false],
    'next visible released' => [static fn (): mixed => $released246()['next_source_visible_after_current_source_conflict_image_next246'], true],
    'next denied missing' => [static fn (): mixed => $missing246()['next_source_visible_after_current_source_conflict_image_next246'], false],
    'current row count' => [static fn (): mixed => $released246()['current_source_row_count_next246'], 2],
    'attempted next row count' => [static fn (): mixed => $released246()['attempted_next_source_row_count_next246'], 2],
    'visible released count' => [static fn (): mixed => $released246()['visible_row_count_next246'], 4],
    'held released count' => [static fn (): mixed => $released246()['held_next_row_count_next246'], 0],
    'visible missing count current only' => [static fn (): mixed => $missing246()['visible_row_count_next246'], 2],
    'held missing count next only' => [static fn (): mixed => $missing246()['held_next_row_count_next246'], 2],
    'current phases' => [static fn (): mixed => array_values(array_unique(array_column($released246()['current_source_rows_next246'], 'conflict_image_phase_next246'))), ['current-conflict-image']],
    'next phases' => [static fn (): mixed => array_values(array_unique(array_column($released246()['attempted_next_source_rows_next246'], 'conflict_image_phase_next246'))), ['next-source']],
    'current receipts tagged' => [static fn (): mixed => array_column($released246()['current_source_rows_next246'], 'current_source_conflict_image_receipt_next246'), $receipts246()],
    'next receipts null' => [static fn (): mixed => array_values(array_unique(array_column($released246()['attempted_next_source_rows_next246'], 'current_source_conflict_image_receipt_next246'))), [null]],
    'current rows visible while held' => [static fn (): mixed => array_values(array_unique(array_column($missing246()['current_source_rows_next246'], 'visible_after_current_source_conflict_image_next246'))), [true]],
    'next rows held missing' => [static fn (): mixed => array_values(array_unique(array_column($missing246()['attempted_next_source_rows_next246'], 'visible_after_current_source_conflict_image_next246'))), [false]],
    'visible payload names released' => [static fn (): mixed => array_column($released246()['visible_returning_payloads_next246'], 'name'), ['app_summary_child', 'template_child', 'landing_url', 'next_module']],
    'held payload names missing' => [static fn (): mixed => array_column($missing246()['held_next_returning_payloads_next246'], 'name'), ['landing_url', 'next_module']],
    'first conflict image key' => [static fn (): mixed => $released246()['current_source_conflict_images_next246'][0]['conflict_key'], 'TEXT:app_summary_child'],
    'first conflict image action insert' => [static fn (): mixed => $released246()['current_source_conflict_images_next246'][0]['upsert_action'], 'insert'],
    'second conflict image action insert' => [static fn (): mixed => $released246()['current_source_conflict_images_next246'][1]['upsert_action'], 'insert'],
    'excluded value captured' => [static fn (): mixed => $released246()['current_source_conflict_images_next246'][0]['excluded_values']['value'], 'after-next_child'],
    'excluded spawn child captured' => [static fn (): mixed => $released246()['current_source_conflict_images_next246'][0]['excluded_values']['spawn_child'], false],
    'blocked reasons released' => [static fn (): mixed => $released246()['blocked_reasons_next246'], []],
    'blocked reasons missing' => [static fn (): mixed => $missing246()['blocked_reasons_next246'], ['current-source-conflict-image-receipt-missing', 'current-source-conflict-image-receipt-order-mismatch']],
    'blocked reasons unexpected' => [static fn (): mixed => $unexpected246()['blocked_reasons_next246'], ['current-source-conflict-image-receipt-unexpected', 'current-source-conflict-image-receipt-order-mismatch']],
    'blocked reasons reversed' => [static fn (): mixed => $reversed246()['blocked_reasons_next246'], ['current-source-conflict-image-receipt-order-mismatch']],
    'blocked reasons token' => [static fn (): mixed => $tokenHeld246()['blocked_reasons_next246'], ['current-source-conflict-image-token-mismatch']],
    'held row reason copied' => [static fn (): mixed => $missing246()['held_next_source_rows_next246'][0]['held_by_current_source_conflict_image_reasons_next246'], ['current-source-conflict-image-receipt-missing', 'current-source-conflict-image-receipt-order-mismatch']],
    'plan decision released' => [static fn (): mixed => $released246()['current_source_conflict_image_plan_next246']['decision'], 'publish-next-source-after-current-upsert-conflict-images'],
    'plan decision held' => [static fn (): mixed => $missing246()['current_source_conflict_image_plan_next246']['decision'], 'hold-next-source-until-current-upsert-conflict-images'],
    'plan required echoed' => [static fn (): mixed => $released246()['current_source_conflict_image_plan_next246']['required_receipts'], $receipts246()],
    'plan next visible echoed' => [static fn (): mixed => $released246()['current_source_conflict_image_plan_next246']['next_source_visible'], true],
    'yield boundary released' => [static fn (): mixed => $released246()['yield_boundary_next246'], 'recursive-view-upsert-next246-current-conflict-images-then-next'],
    'yield boundary held' => [static fn (): mixed => $missing246()['yield_boundary_next246'], 'recursive-view-upsert-next246-current-conflict-images-fence-next'],
    'dependency closure marker' => [static fn (): mixed => $released246()['dependency_closure_next246'], 'no-new-support-component-reuses-native-recursive-view-upsert-current-source-cookies-and-adds-conflict-image-receipts'],
    'dependency includes next246' => [static fn (): mixed => in_array('sqlite-trigger-recursive-view-upsert-current-source-next246', $released246()['dependencies_next246'], true), true],
    'dependency includes image receipts' => [static fn (): mixed => in_array('sqlite-instead-of-view-upsert-conflict-image-receipts', $released246()['dependencies_next246'], true), true],
    'dependency includes next243' => [static fn (): mixed => in_array('sqlite-trigger-recursive-view-upsert-current-source-next243', $released246()['dependencies_next246'], true), true],
    'non overlap mentions next243' => [static fn (): mixed => str_contains($released246()['non_overlap_next246'], 'next243 source-cookie'), true],
    'bad token rejected' => [static fn (): mixed => $plan246(['current_source_conflict_image_token_next246' => 'bad token']), InvalidArgumentException::class],
    'bad expected token rejected' => [static fn (): mixed => $plan246(['expected_current_source_conflict_image_token_next246' => 'bad token']), InvalidArgumentException::class],
    'bad conflict columns rejected' => [static fn (): mixed => $plan246(['upsert_conflict_columns_next246' => []]), InvalidArgumentException::class],
    'bad excluded columns rejected' => [static fn (): mixed => $plan246(['upsert_excluded_columns_next246' => ['bad column']]), InvalidArgumentException::class],
    'bad receipt list rejected' => [static fn (): mixed => $plan246(['acknowledged_current_source_conflict_image_receipts_next246' => ['x' => $unexpectedReceipt246]]), InvalidArgumentException::class],
    'bad short receipt rejected' => [static fn (): mixed => $plan246(['acknowledged_current_source_conflict_image_receipts_next246' => ['abc']]), InvalidArgumentException::class],
    'bad non hex receipt rejected' => [static fn (): mixed => $plan246(['acknowledged_current_source_conflict_image_receipts_next246' => ['zzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzz']]), InvalidArgumentException::class],
];

$tests = [];
foreach ($cases246 as $name => [$callback, $expected]) {
    $tests['trigger recursive view upsert current source next246 ' . $name] = static function (TestRunner $t) use ($callback, $expected): void {
        if (is_string($expected) && is_a($expected, Throwable::class, true)) {
            $t->throws($expected, $callback);
            return;
        }
        $t->same($expected, $callback());
    };
}

return $tests;
