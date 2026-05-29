<?php

declare(strict_types=1);

foreach (glob(__DIR__ . '/../src/SQLiteTriggerRecursiveViewReturningCurrentSourceNext*.php') ?: [] as $file) {
    require_once $file;
}
require_once __DIR__ . '/../src/SQLiteTriggerRecursiveViewUpsertCurrentSourceNextPlan.php';
require_once __DIR__ . '/../src/SQLiteTriggerRecursiveViewUpsertCurrentSourceNextPlan.php';

use PortLibs\LibSqlite\SQLiteTriggerRecursiveViewUpsertCurrentSourceNextPlan;

$rows243 = [
    ['option_id' => 1, 'option_name' => 'siteurl', 'option_value' => 'https://old.test', 'autoload' => 'yes'],
    ['option_id' => 2, 'option_name' => 'home', 'option_value' => 'https://home.test', 'autoload' => 'yes'],
];
$currentView243 = [
    'name' => 'wp_recursive_option_import',
    'source' => 'main@view-cookie-243-current',
    'trigger' => 'wp_recursive_option_import_io_insert',
    'trigger_source' => 'main@trigger-cookie-243-current',
    'columns' => ['import_id', 'name', 'value', 'autoload_flag', 'spawn_child'],
    'mapping' => ['import_id' => 'option_id', 'name' => 'option_name', 'value' => 'option_value', 'autoload_flag' => 'autoload', 'spawn_child' => 'spawn_child'],
    'audit_label' => 'current-recursive-view-upsert-243',
];
$nextView243 = $currentView243;
$nextView243['source'] = 'main@view-cookie-243-next';
$nextView243['trigger_source'] = 'main@trigger-cookie-243-next';
$postResetView243 = $currentView243;
$postResetView243['source'] = 'main@view-cookie-243-post-reset';
$postResetView243['trigger_source'] = 'main@trigger-cookie-243-post-reset';
$followingView243 = $currentView243;
$followingView243['source'] = 'main@view-cookie-243-following';
$followingView243['trigger_source'] = 'main@trigger-cookie-243-following';
$currentInput243 = [
    ['import_id' => 10, 'name' => 'siteurl', 'value' => 'https://current.test', 'autoload_flag' => 'yes', 'spawn_child' => true],
    ['import_id' => 11, 'name' => 'current_plugin', 'value' => 'enabled', 'autoload_flag' => 'no', 'spawn_child' => true],
];
$nextInput243 = [
    ['import_id' => 20, 'name' => 'home', 'value' => 'https://next.test', 'autoload_flag' => 'yes', 'spawn_child' => true],
    ['import_id' => 21, 'name' => 'next_plugin', 'value' => 'active', 'autoload_flag' => 'no', 'spawn_child' => false],
];
$returning243 = [
    ['expr' => 'new.option_name', 'as' => 'name'],
    ['expr' => 'new.option_value', 'as' => 'value'],
    ['expr' => 'event', 'as' => 'event_name'],
    ['expr' => 'depth', 'as' => 'depth_value'],
    ['expr' => 'ordinal', 'as' => 'ordinal_value'],
    ['expr' => 'trigger_source', 'as' => 'trigger_source_alias'],
    ['expr' => 'spawn_child', 'as' => 'spawn_child'],
];

$plan243 = static fn (array $options = []): array => SQLiteTriggerRecursiveViewUpsertCurrentSourceNextPlan::executeNext243(
    $rows243,
    $currentInput243,
    $nextInput243,
    $currentView243,
    $nextView243,
    $returning243,
    $options + [
        'key' => 'option_name',
        'savepoint' => 'wp_recursive_view_243',
        'cursor_name' => 'wp_recursive_view_returning_cursor_243',
        'admit_next_source' => true,
        'rollback_token' => 'wp.rollback.current.243',
        'reset_generation' => 'wp-current-reset-243',
        'post_reset_current_source_token' => 'wp.current.source.postreset.243',
        'post_reset_cursor' => 'wp.returning.postreset.cursor.243',
        'post_reset_view' => $postResetView243,
        'post_reset_input' => [
            ['import_id' => 30, 'name' => 'siteurl', 'value' => 'https://fresh.test', 'autoload_flag' => 'yes', 'spawn_child' => false],
            ['import_id' => 31, 'name' => 'rewrite_rules', 'value' => 'fresh-rules', 'autoload_flag' => 'no', 'spawn_child' => false],
        ],
        'fresh_acknowledged_ordinals' => [0, 1],
        'next_source_token' => 'wp.next.source.243',
        'next_cursor' => 'wp.returning.next.cursor.243',
        'next_acknowledged_ordinals' => [0, 1],
        'close_next_cursor' => 'wp.returning.next.cursor.243',
        'following_current_source_token' => 'wp.current.source.following.243',
        'following_cursor' => 'wp.returning.following.cursor.243',
        'following_current_view' => $followingView243,
        'following_current_input' => [
            ['import_id' => 40, 'name' => 'blogdescription', 'value' => 'after-next', 'autoload_flag' => 'yes', 'spawn_child' => true],
            ['import_id' => 41, 'name' => 'stylesheet', 'value' => 'twentytwentyfive', 'autoload_flag' => 'yes', 'spawn_child' => false],
            ['import_id' => 42, 'name' => 'template', 'value' => 'twentytwentyfive', 'autoload_flag' => 'yes', 'spawn_child' => true],
        ],
        'following_generation' => 'wp-following-current-243',
        'recursive_child_acknowledged_ordinals' => [0, 1],
        'recursive_child_source_token' => 'wp.current.source.recursive.child.243',
        'recursive_child_cursor' => 'wp.returning.recursive.child.cursor.243',
        'recursive_child_generation' => 'wp-recursive-child-current-243',
        'current_generation_next203' => 'wp.current.recursive.returning.generation.243',
        'expected_current_generation_next203' => 'wp.current.recursive.returning.generation.243',
        'current_handoff_cursor_next203' => 'wp.returning.current.handoff.cursor.243',
        'current_generation_commit_marker_next203' => 'wp.current.recursive.returning.commit.243',
        'auto_ack_current_generation_receipts_next203' => true,
        'current_source_drain_token_next209' => 'wp.current.source.drain.243',
        'current_view_cookie_next209' => 'main@view-cookie-243-current',
        'current_trigger_cookie_next209' => 'main@trigger-cookie-243-current',
        'auto_ack_current_source_watermarks_next209' => true,
        'current_source_yield_token_next212' => 'wp.current.source.yield.243',
        'current_view_yield_cursor_next212' => 'wp.returning.view.yield.cursor.243',
        'current_trigger_yield_cursor_next212' => 'wp.returning.trigger.yield.cursor.243',
        'auto_ack_current_source_yields_next212' => true,
        'current_source_epoch_next218' => 'wp.current.source.epoch.243',
        'current_view_epoch_next218' => 'wp.returning.view.epoch.cursor.243',
        'current_trigger_epoch_next218' => 'wp.returning.trigger.epoch.cursor.243',
        'auto_ack_current_source_epochs_next218' => true,
        'current_source_ticket_next222' => 'wp.current.source.ticket.243',
        'current_view_source_next222' => 'main@view-cookie-243-current',
        'current_trigger_source_next222' => 'main@trigger-cookie-243-current',
        'auto_ack_current_source_tickets_next222' => true,
        'current_source_cursor_next231' => 'wp.returning.current.cursor.243',
        'current_source_close_token_next231' => 'wp.current.source.close.243',
        'current_view_cookie_next231' => 'main@view-cookie-243-current',
        'current_trigger_cookie_next231' => 'main@trigger-cookie-243-current',
        'auto_ack_current_source_closures_next231' => true,
        'current_source_upsert_cursor_next240' => 'wp.upsert.current.cursor.243',
        'current_view_upsert_cookie_next240' => 'main@view-cookie-243-current',
        'current_trigger_upsert_cookie_next240' => 'main@trigger-cookie-243-current',
        'upsert_conflict_columns_next240' => ['name'],
        'auto_ack_current_source_upserts_next240' => true,
        'current_source_view_cookie_next243' => 'main@view-cookie-243-current',
        'expected_current_source_view_cookie_next243' => 'main@view-cookie-243-current',
        'current_source_trigger_cookie_next243' => 'main@trigger-cookie-243-current',
        'expected_current_source_trigger_cookie_next243' => 'main@trigger-cookie-243-current',
        'next_source_view_cookie_next243' => 'main@view-cookie-243-next',
        'upsert_source_cursor_next243' => 'wp.upsert.source.cursor.243',
    ],
);

$released243 = static fn (): array => $plan243();
$viewHeld243 = static fn (): array => $plan243(['expected_current_source_view_cookie_next243' => 'main@view-cookie-243-stale']);
$triggerHeld243 = static fn (): array => $plan243(['expected_current_source_trigger_cookie_next243' => 'main@trigger-cookie-243-stale']);
$bothHeld243 = static fn (): array => $plan243([
    'expected_current_source_view_cookie_next243' => 'main@view-cookie-243-stale',
    'expected_current_source_trigger_cookie_next243' => 'main@trigger-cookie-243-stale',
]);
$baseHeld243 = static fn (): array => $plan243(['auto_ack_current_source_upserts_next240' => false]);
$custom243 = static fn (): array => $plan243([
    'current_source_view_cookie_next243' => 'main@view-cookie-243-custom',
    'expected_current_source_view_cookie_next243' => 'main@view-cookie-243-custom',
    'current_source_trigger_cookie_next243' => 'main@trigger-cookie-243-custom',
    'expected_current_source_trigger_cookie_next243' => 'main@trigger-cookie-243-custom',
    'next_source_view_cookie_next243' => 'main@view-cookie-243-next-custom',
    'upsert_source_cursor_next243' => 'wp.upsert.source.cursor.custom.243',
]);

$cases243 = [
    'released status' => [static fn (): mixed => $released243()['status_next243'], 'trigger-recursive-view-upsert-current-source-next243-source-released'],
    'view held status' => [static fn (): mixed => $viewHeld243()['status_next243'], 'trigger-recursive-view-upsert-current-source-next243-view-source-held'],
    'trigger held status' => [static fn (): mixed => $triggerHeld243()['status_next243'], 'trigger-recursive-view-upsert-current-source-next243-trigger-source-held'],
    'both held status prefers view' => [static fn (): mixed => $bothHeld243()['status_next243'], 'trigger-recursive-view-upsert-current-source-next243-view-source-held'],
    'base held status' => [static fn (): mixed => $baseHeld243()['status_next243'], 'trigger-recursive-view-upsert-current-source-next243-base-held'],
    'base next240 released' => [static fn (): mixed => $released243()['base']['status_next240'], 'trigger-recursive-view-upsert-current-source-next240-conflict-source-released'],
    'base next240 held' => [static fn (): mixed => $baseHeld243()['base']['status_next240'], 'trigger-recursive-view-upsert-current-source-next240-conflict-source-held'],
    'savepoint retained' => [static fn (): mixed => $released243()['savepoint'], 'wp_recursive_view_243'],
    'base visible released' => [static fn (): mixed => $released243()['base_next_source_visible_next243'], true],
    'base visible held' => [static fn (): mixed => $baseHeld243()['base_next_source_visible_next243'], false],
    'view cookie retained' => [static fn (): mixed => $released243()['current_source_view_cookie_next243'], 'main@view-cookie-243-current'],
    'expected view cookie retained' => [static fn (): mixed => $released243()['expected_current_source_view_cookie_next243'], 'main@view-cookie-243-current'],
    'view cookie matches' => [static fn (): mixed => $released243()['current_source_view_cookie_matches_next243'], true],
    'view cookie mismatch' => [static fn (): mixed => $viewHeld243()['current_source_view_cookie_matches_next243'], false],
    'trigger cookie retained' => [static fn (): mixed => $released243()['current_source_trigger_cookie_next243'], 'main@trigger-cookie-243-current'],
    'expected trigger cookie retained' => [static fn (): mixed => $released243()['expected_current_source_trigger_cookie_next243'], 'main@trigger-cookie-243-current'],
    'trigger cookie matches' => [static fn (): mixed => $released243()['current_source_trigger_cookie_matches_next243'], true],
    'trigger cookie mismatch' => [static fn (): mixed => $triggerHeld243()['current_source_trigger_cookie_matches_next243'], false],
    'next cookie retained' => [static fn (): mixed => $released243()['next_source_view_cookie_next243'], 'main@view-cookie-243-next'],
    'cursor retained' => [static fn (): mixed => $released243()['upsert_source_cursor_next243'], 'wp.upsert.source.cursor.243'],
    'custom view retained' => [static fn (): mixed => $custom243()['current_source_view_cookie_next243'], 'main@view-cookie-243-custom'],
    'custom trigger retained' => [static fn (): mixed => $custom243()['current_source_trigger_cookie_next243'], 'main@trigger-cookie-243-custom'],
    'custom next retained' => [static fn (): mixed => $custom243()['next_source_view_cookie_next243'], 'main@view-cookie-243-next-custom'],
    'custom cursor retained' => [static fn (): mixed => $custom243()['upsert_source_cursor_next243'], 'wp.upsert.source.cursor.custom.243'],
    'source current released' => [static fn (): mixed => $released243()['current_source_still_current_next243'], true],
    'source current view denied' => [static fn (): mixed => $viewHeld243()['current_source_still_current_next243'], false],
    'source current trigger denied' => [static fn (): mixed => $triggerHeld243()['current_source_still_current_next243'], false],
    'next visible released' => [static fn (): mixed => $released243()['next_source_visible_after_upsert_current_source_next243'], true],
    'next denied view stale' => [static fn (): mixed => $viewHeld243()['next_source_visible_after_upsert_current_source_next243'], false],
    'next denied trigger stale' => [static fn (): mixed => $triggerHeld243()['next_source_visible_after_upsert_current_source_next243'], false],
    'next denied base held' => [static fn (): mixed => $baseHeld243()['next_source_visible_after_upsert_current_source_next243'], false],
    'current row count' => [static fn (): mixed => $released243()['current_source_row_count_next243'], 2],
    'attempted next row count' => [static fn (): mixed => $released243()['attempted_next_source_row_count_next243'], 2],
    'visible released count' => [static fn (): mixed => $released243()['visible_row_count_next243'], 4],
    'held released count' => [static fn (): mixed => $released243()['held_next_row_count_next243'], 0],
    'visible view held current only' => [static fn (): mixed => $viewHeld243()['visible_row_count_next243'], 2],
    'held view held next only' => [static fn (): mixed => $viewHeld243()['held_next_row_count_next243'], 2],
    'current phases' => [static fn (): mixed => array_values(array_unique(array_column($released243()['current_source_rows_next243'], 'upsert_source_phase_next243'))), ['current']],
    'next phases' => [static fn (): mixed => array_values(array_unique(array_column($released243()['attempted_next_source_rows_next243'], 'upsert_source_phase_next243'))), ['next']],
    'current rows visible while held' => [static fn (): mixed => array_values(array_unique(array_column($viewHeld243()['current_source_rows_next243'], 'visible_after_upsert_current_source_next243'))), [true]],
    'next rows visible released' => [static fn (): mixed => array_values(array_unique(array_column($released243()['attempted_next_source_rows_next243'], 'visible_after_upsert_current_source_next243'))), [true]],
    'next rows held stale view' => [static fn (): mixed => array_values(array_unique(array_column($viewHeld243()['attempted_next_source_rows_next243'], 'visible_after_upsert_current_source_next243'))), [false]],
    'tagged cursor copied' => [static fn (): mixed => array_values(array_unique(array_column($released243()['current_source_rows_next243'], 'upsert_source_cursor_next243'))), ['wp.upsert.source.cursor.243']],
    'tagged view cookie copied' => [static fn (): mixed => array_values(array_unique(array_column($released243()['current_source_rows_next243'], 'current_source_view_cookie_next243'))), ['main@view-cookie-243-current']],
    'tagged trigger cookie copied' => [static fn (): mixed => array_values(array_unique(array_column($released243()['current_source_rows_next243'], 'current_source_trigger_cookie_next243'))), ['main@trigger-cookie-243-current']],
    'tagged next cookie copied' => [static fn (): mixed => array_values(array_unique(array_column($released243()['attempted_next_source_rows_next243'], 'next_source_view_cookie_next243'))), ['main@view-cookie-243-next']],
    'visible payload names released' => [static fn (): mixed => array_column($released243()['visible_returning_payloads_next243'], 'name'), ['blogdescription_child', 'template_child', 'home', 'next_plugin']],
    'held payload names view stale' => [static fn (): mixed => array_column($viewHeld243()['held_next_returning_payloads_next243'], 'name'), ['home', 'next_plugin']],
    'blocked reasons released' => [static fn (): mixed => $released243()['blocked_reasons_next243'], []],
    'blocked reasons view stale' => [static fn (): mixed => $viewHeld243()['blocked_reasons_next243'], ['current-view-source-cookie-mismatch']],
    'blocked reasons trigger stale' => [static fn (): mixed => $triggerHeld243()['blocked_reasons_next243'], ['current-trigger-source-cookie-mismatch']],
    'blocked reasons both stale' => [static fn (): mixed => $bothHeld243()['blocked_reasons_next243'], ['current-view-source-cookie-mismatch', 'current-trigger-source-cookie-mismatch']],
    'blocked reasons base held' => [static fn (): mixed => $baseHeld243()['blocked_reasons_next243'], ['current-source-upsert-missing']],
    'held row reason view stale copied' => [static fn (): mixed => $viewHeld243()['held_next_source_rows_next243'][0]['held_by_upsert_current_source_reasons_next243'], ['current-view-source-cookie-mismatch']],
    'plan decision released' => [static fn (): mixed => $released243()['upsert_current_source_plan_next243']['decision'], 'publish-next-source-after-current-view-upsert-source-match'],
    'plan decision held' => [static fn (): mixed => $viewHeld243()['upsert_current_source_plan_next243']['decision'], 'hold-next-source-until-current-view-upsert-source-match'],
    'plan view match echoed' => [static fn (): mixed => $released243()['upsert_current_source_plan_next243']['current_view_cookie_matches'], true],
    'plan trigger match echoed' => [static fn (): mixed => $released243()['upsert_current_source_plan_next243']['current_trigger_cookie_matches'], true],
    'plan source current echoed' => [static fn (): mixed => $released243()['upsert_current_source_plan_next243']['current_source_still_current'], true],
    'plan next visible echoed' => [static fn (): mixed => $released243()['upsert_current_source_plan_next243']['next_source_visible'], true],
    'yield boundary released' => [static fn (): mixed => $released243()['yield_boundary_next243'], 'recursive-view-upsert-next243-current-source-then-next'],
    'yield boundary held' => [static fn (): mixed => $viewHeld243()['yield_boundary_next243'], 'recursive-view-upsert-next243-current-source-fences-next'],
    'dependency closure marker' => [static fn (): mixed => $released243()['dependency_closure_next243'], 'no-new-support-component-reuses-native-recursive-view-upsert-current-source-cookies'],
    'dependency includes next243' => [static fn (): mixed => in_array('sqlite-trigger-recursive-view-upsert-current-source-next243', $released243()['dependencies_next243'], true), true],
    'dependency includes cookie fence' => [static fn (): mixed => in_array('sqlite-instead-of-view-upsert-current-source-cookie-fence', $released243()['dependencies_next243'], true), true],
    'dependency includes next240' => [static fn (): mixed => in_array('sqlite-trigger-recursive-view-upsert-current-source-next240', $released243()['dependencies_next243'], true), true],
    'non overlap mentions next240' => [static fn (): mixed => str_contains($released243()['non_overlap_next243'], 'next240 UPSERT'), true],
    'bad current view cookie rejected' => [static fn (): mixed => $plan243(['current_source_view_cookie_next243' => 'bad cookie']), InvalidArgumentException::class],
    'bad expected view cookie rejected' => [static fn (): mixed => $plan243(['expected_current_source_view_cookie_next243' => 'bad cookie']), InvalidArgumentException::class],
    'bad current trigger cookie rejected' => [static fn (): mixed => $plan243(['current_source_trigger_cookie_next243' => 'bad cookie']), InvalidArgumentException::class],
    'bad expected trigger cookie rejected' => [static fn (): mixed => $plan243(['expected_current_source_trigger_cookie_next243' => 'bad cookie']), InvalidArgumentException::class],
    'bad next cookie rejected' => [static fn (): mixed => $plan243(['next_source_view_cookie_next243' => 'bad cookie']), InvalidArgumentException::class],
    'bad cursor rejected' => [static fn (): mixed => $plan243(['upsert_source_cursor_next243' => 'bad cursor']), InvalidArgumentException::class],
];

$tests = [];
foreach ($cases243 as $name => [$callback, $expected]) {
    $tests['trigger recursive view upsert current source next243 ' . $name] = static function (TestRunner $t) use ($callback, $expected): void {
        if (is_string($expected) && is_a($expected, Throwable::class, true)) {
            $t->throws($expected, $callback);
            return;
        }
        $t->same($expected, $callback());
    };
}

return $tests;
