<?php

declare(strict_types=1);

foreach (glob(__DIR__ . '/../src/SQLiteTriggerRecursiveViewReturningCurrentSourceNext*.php') ?: [] as $file) {
    require_once $file;
}
require_once __DIR__ . '/../src/SQLiteTriggerRecursiveViewUpsertCurrentSourceNext240Plan.php';
require_once __DIR__ . '/../src/SQLiteTriggerRecursiveViewUpsertCurrentSourceNext243Plan.php';
require_once __DIR__ . '/../src/SQLiteTriggerRecursiveViewUpsertCurrentSourceNext246Plan.php';

use PortLibs\LibSqlite\SQLiteTriggerRecursiveViewUpsertCurrentSourceNext246Plan;

$rows246 = [
    ['option_id' => 1, 'option_name' => 'siteurl', 'option_value' => 'https://old.test', 'autoload' => 'yes'],
    ['option_id' => 2, 'option_name' => 'home', 'option_value' => 'https://home.test', 'autoload' => 'yes'],
];
$currentView246 = [
    'name' => 'wp_recursive_option_import',
    'source' => 'main@view-cookie-246-current',
    'trigger' => 'wp_recursive_option_import_io_insert',
    'trigger_source' => 'main@trigger-cookie-246-current',
    'columns' => ['import_id', 'name', 'value', 'autoload_flag', 'spawn_child'],
    'mapping' => ['import_id' => 'option_id', 'name' => 'option_name', 'value' => 'option_value', 'autoload_flag' => 'autoload', 'spawn_child' => 'spawn_child'],
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
    ['import_id' => 10, 'name' => 'siteurl', 'value' => 'https://current.test', 'autoload_flag' => 'yes', 'spawn_child' => true],
    ['import_id' => 11, 'name' => 'current_plugin', 'value' => 'enabled', 'autoload_flag' => 'no', 'spawn_child' => true],
];
$nextInput246 = [
    ['import_id' => 20, 'name' => 'home', 'value' => 'https://next.test', 'autoload_flag' => 'yes', 'spawn_child' => true],
    ['import_id' => 21, 'name' => 'next_plugin', 'value' => 'active', 'autoload_flag' => 'no', 'spawn_child' => false],
];
$returning246 = [
    ['expr' => 'new.option_name', 'as' => 'name'],
    ['expr' => 'new.option_value', 'as' => 'value'],
    ['expr' => 'event', 'as' => 'event_name'],
    ['expr' => 'depth', 'as' => 'depth_value'],
    ['expr' => 'ordinal', 'as' => 'ordinal_value'],
    ['expr' => 'trigger_source', 'as' => 'trigger_source_alias'],
    ['expr' => 'spawn_child', 'as' => 'spawn_child'],
];

$plan246 = static fn (array $options = []): array => SQLiteTriggerRecursiveViewUpsertCurrentSourceNext246Plan::execute(
    $rows246,
    $currentInput246,
    $nextInput246,
    $currentView246,
    $nextView246,
    $returning246,
    $options + [
        'key' => 'option_name',
        'savepoint' => 'wp_recursive_view_246',
        'cursor_name' => 'wp_recursive_view_returning_cursor_246',
        'admit_next_source' => true,
        'rollback_token' => 'wp.rollback.current.246',
        'reset_generation' => 'wp-current-reset-246',
        'post_reset_current_source_token' => 'wp.current.source.postreset.246',
        'post_reset_cursor' => 'wp.returning.postreset.cursor.246',
        'post_reset_view' => $postResetView246,
        'post_reset_input' => [
            ['import_id' => 30, 'name' => 'siteurl', 'value' => 'https://fresh.test', 'autoload_flag' => 'yes', 'spawn_child' => false],
            ['import_id' => 31, 'name' => 'rewrite_rules', 'value' => 'fresh-rules', 'autoload_flag' => 'no', 'spawn_child' => false],
        ],
        'fresh_acknowledged_ordinals' => [0, 1],
        'next_source_token' => 'wp.next.source.246',
        'next_cursor' => 'wp.returning.next.cursor.246',
        'next_acknowledged_ordinals' => [0, 1],
        'close_next_cursor' => 'wp.returning.next.cursor.246',
        'following_current_source_token' => 'wp.current.source.following.246',
        'following_cursor' => 'wp.returning.following.cursor.246',
        'following_current_view' => $followingView246,
        'following_current_input' => [
            ['import_id' => 40, 'name' => 'blogdescription', 'value' => 'after-next', 'autoload_flag' => 'yes', 'spawn_child' => true],
            ['import_id' => 41, 'name' => 'stylesheet', 'value' => 'twentytwentyfive', 'autoload_flag' => 'yes', 'spawn_child' => false],
            ['import_id' => 42, 'name' => 'template', 'value' => 'twentytwentyfive', 'autoload_flag' => 'yes', 'spawn_child' => true],
        ],
        'following_generation' => 'wp-following-current-246',
        'recursive_child_acknowledged_ordinals' => [0, 1],
        'recursive_child_source_token' => 'wp.current.source.recursive.child.246',
        'recursive_child_cursor' => 'wp.returning.recursive.child.cursor.246',
        'recursive_child_generation' => 'wp-recursive-child-current-246',
        'current_generation_next203' => 'wp.current.recursive.returning.generation.246',
        'expected_current_generation_next203' => 'wp.current.recursive.returning.generation.246',
        'current_handoff_cursor_next203' => 'wp.returning.current.handoff.cursor.246',
        'current_generation_commit_marker_next203' => 'wp.current.recursive.returning.commit.246',
        'auto_ack_current_generation_receipts_next203' => true,
        'current_source_drain_token_next209' => 'wp.current.source.drain.246',
        'current_view_cookie_next209' => 'main@view-cookie-246-current',
        'current_trigger_cookie_next209' => 'main@trigger-cookie-246-current',
        'auto_ack_current_source_watermarks_next209' => true,
        'current_source_yield_token_next212' => 'wp.current.source.yield.246',
        'current_view_yield_cursor_next212' => 'wp.returning.view.yield.cursor.246',
        'current_trigger_yield_cursor_next212' => 'wp.returning.trigger.yield.cursor.246',
        'auto_ack_current_source_yields_next212' => true,
        'current_source_epoch_next218' => 'wp.current.source.epoch.246',
        'current_view_epoch_next218' => 'wp.returning.view.epoch.cursor.246',
        'current_trigger_epoch_next218' => 'wp.returning.trigger.epoch.cursor.246',
        'auto_ack_current_source_epochs_next218' => true,
        'current_source_ticket_next222' => 'wp.current.source.ticket.246',
        'current_view_source_next222' => 'main@view-cookie-246-current',
        'current_trigger_source_next222' => 'main@trigger-cookie-246-current',
        'auto_ack_current_source_tickets_next222' => true,
        'current_source_cursor_next231' => 'wp.returning.current.cursor.246',
        'current_source_close_token_next231' => 'wp.current.source.close.246',
        'current_view_cookie_next231' => 'main@view-cookie-246-current',
        'current_trigger_cookie_next231' => 'main@trigger-cookie-246-current',
        'auto_ack_current_source_closures_next231' => true,
        'current_source_upsert_cursor_next240' => 'wp.upsert.current.cursor.246',
        'current_view_upsert_cookie_next240' => 'main@view-cookie-246-current',
        'current_trigger_upsert_cookie_next240' => 'main@trigger-cookie-246-current',
        'upsert_conflict_columns_next240' => ['name'],
        'auto_ack_current_source_upserts_next240' => true,
        'current_source_view_cookie_next243' => 'main@view-cookie-246-current',
        'expected_current_source_view_cookie_next243' => 'main@view-cookie-246-current',
        'current_source_trigger_cookie_next243' => 'main@trigger-cookie-246-current',
        'expected_current_source_trigger_cookie_next243' => 'main@trigger-cookie-246-current',
        'next_source_view_cookie_next243' => 'main@view-cookie-246-next',
        'upsert_source_cursor_next243' => 'wp.upsert.source.cursor.246',
        'current_source_conflict_image_token_next246' => 'wp.current.source.conflict.image.246',
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
$tokenHeld246 = static fn (): array => $plan246(['auto_ack_current_source_conflict_images_next246' => true, 'expected_current_source_conflict_image_token_next246' => 'wp.current.source.conflict.image.stale.246']);
$baseHeld246 = static fn (): array => $plan246(['auto_ack_current_source_conflict_images_next246' => true, 'expected_current_source_view_cookie_next243' => 'main@view-cookie-246-stale']);
$custom246 = static fn (): array => $plan246([
    'auto_ack_current_source_conflict_images_next246' => true,
    'current_source_conflict_image_token_next246' => 'wp.current.source.conflict.image.custom.246',
    'expected_current_source_conflict_image_token_next246' => 'wp.current.source.conflict.image.custom.246',
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
    'savepoint retained' => [static fn (): mixed => $released246()['savepoint'], 'wp_recursive_view_246'],
    'base visible released' => [static fn (): mixed => $released246()['base_next_source_visible_next246'], true],
    'base visible held' => [static fn (): mixed => $baseHeld246()['base_next_source_visible_next246'], false],
    'token retained' => [static fn (): mixed => $released246()['current_source_conflict_image_token_next246'], 'wp.current.source.conflict.image.246'],
    'expected token defaults actual' => [static fn (): mixed => $released246()['expected_current_source_conflict_image_token_next246'], 'wp.current.source.conflict.image.246'],
    'token matches released' => [static fn (): mixed => $released246()['current_source_conflict_image_token_matches_next246'], true],
    'token mismatch detected' => [static fn (): mixed => $tokenHeld246()['current_source_conflict_image_token_matches_next246'], false],
    'custom token retained' => [static fn (): mixed => $custom246()['current_source_conflict_image_token_next246'], 'wp.current.source.conflict.image.custom.246'],
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
    'visible payload names released' => [static fn (): mixed => array_column($released246()['visible_returning_payloads_next246'], 'name'), ['blogdescription_child', 'template_child', 'home', 'next_plugin']],
    'held payload names missing' => [static fn (): mixed => array_column($missing246()['held_next_returning_payloads_next246'], 'name'), ['home', 'next_plugin']],
    'first conflict image key' => [static fn (): mixed => $released246()['current_source_conflict_images_next246'][0]['conflict_key'], 'TEXT:blogdescription_child'],
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
