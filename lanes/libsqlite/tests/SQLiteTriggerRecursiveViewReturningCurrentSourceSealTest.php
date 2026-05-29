<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteTriggerRecursiveViewReturningCurrentSourceNextPlan;

$rows224 = [
    ['option_id' => 1, 'option_name' => 'siteurl', 'option_value' => 'https://old.test', 'autoload' => 'yes'],
    ['option_id' => 2, 'option_name' => 'home', 'option_value' => 'https://home.test', 'autoload' => 'yes'],
];
$currentView224 = [
    'name' => 'wp_recursive_option_import',
    'source' => 'main@view-cookie-224-current',
    'trigger' => 'wp_recursive_option_import_io_insert',
    'trigger_source' => 'main@trigger-cookie-224-current',
    'columns' => ['import_id', 'name', 'value', 'autoload_flag', 'spawn_child'],
    'mapping' => ['import_id' => 'option_id', 'name' => 'option_name', 'value' => 'option_value', 'autoload_flag' => 'autoload', 'spawn_child' => 'spawn_child'],
    'audit_label' => 'current-recursive-view-trigger-224',
];
$nextView224 = $currentView224;
$nextView224['source'] = 'main@view-cookie-224-next';
$nextView224['trigger_source'] = 'main@trigger-cookie-224-next';
$postResetView224 = $currentView224;
$postResetView224['source'] = 'main@view-cookie-224-post-reset';
$postResetView224['trigger_source'] = 'main@trigger-cookie-224-post-reset';
$followingView224 = $currentView224;
$followingView224['source'] = 'main@view-cookie-224-following';
$followingView224['trigger_source'] = 'main@trigger-cookie-224-following';
$currentInput224 = [
    ['import_id' => 10, 'name' => 'siteurl', 'value' => 'https://current.test', 'autoload_flag' => 'yes', 'spawn_child' => true],
    ['import_id' => 11, 'name' => 'current_plugin', 'value' => 'enabled', 'autoload_flag' => 'no', 'spawn_child' => true],
];
$nextInput224 = [
    ['import_id' => 20, 'name' => 'home', 'value' => 'https://next.test', 'autoload_flag' => 'yes', 'spawn_child' => true],
    ['import_id' => 21, 'name' => 'next_plugin', 'value' => 'active', 'autoload_flag' => 'no', 'spawn_child' => false],
];
$returning224 = [
    ['expr' => 'new.option_name', 'as' => 'name'],
    ['expr' => 'new.option_value', 'as' => 'value'],
    ['expr' => 'event', 'as' => 'event_name'],
    ['expr' => 'depth', 'as' => 'depth_value'],
    ['expr' => 'ordinal', 'as' => 'ordinal_value'],
    ['expr' => 'trigger_source', 'as' => 'trigger_source_alias'],
    ['expr' => 'spawn_child', 'as' => 'spawn_child'],
];

$plan224 = static fn (array $options = []): array => SQLiteTriggerRecursiveViewReturningCurrentSourceNextPlan::executeCurrentReturningSourceSeal(
    $rows224,
    $currentInput224,
    $nextInput224,
    $currentView224,
    $nextView224,
    $returning224,
    $options + [
        'key' => 'option_name',
        'savepoint' => 'wp_recursive_view_224',
        'cursor_name' => 'wp_recursive_view_returning_cursor_224',
        'admit_next_source' => true,
        'rollback_token' => 'wp.rollback.current.224',
        'reset_generation' => 'wp-current-reset-224',
        'post_reset_current_source_token' => 'wp.current.source.postreset.224',
        'post_reset_cursor' => 'wp.returning.postreset.cursor.224',
        'post_reset_view' => $postResetView224,
        'post_reset_input' => [
            ['import_id' => 30, 'name' => 'siteurl', 'value' => 'https://fresh.test', 'autoload_flag' => 'yes', 'spawn_child' => false],
            ['import_id' => 31, 'name' => 'rewrite_rules', 'value' => 'fresh-rules', 'autoload_flag' => 'no', 'spawn_child' => false],
        ],
        'fresh_acknowledged_ordinals' => [0, 1],
        'next_source_token' => 'wp.next.source.224',
        'next_cursor' => 'wp.returning.next.cursor.224',
        'next_acknowledged_ordinals' => [0, 1],
        'close_next_cursor' => 'wp.returning.next.cursor.224',
        'following_current_source_token' => 'wp.current.source.following.224',
        'following_cursor' => 'wp.returning.following.cursor.224',
        'following_current_view' => $followingView224,
        'following_current_input' => [
            ['import_id' => 40, 'name' => 'blogdescription', 'value' => 'after-next', 'autoload_flag' => 'yes', 'spawn_child' => true],
            ['import_id' => 41, 'name' => 'stylesheet', 'value' => 'twentytwentyfive', 'autoload_flag' => 'yes', 'spawn_child' => false],
            ['import_id' => 42, 'name' => 'template', 'value' => 'twentytwentyfive', 'autoload_flag' => 'yes', 'spawn_child' => true],
        ],
        'recursive_child_acknowledged_ordinals' => [0, 1],
        'recursive_child_source_token' => 'wp.current.source.recursive.child.224',
        'recursive_child_cursor' => 'wp.returning.recursive.child.cursor.224',
        'recursive_child_generation' => 'wp-recursive-child-current-224',
        'current_generation_next203' => 'wp.current.recursive.returning.generation.224',
        'expected_current_generation_next203' => 'wp.current.recursive.returning.generation.224',
        'current_handoff_cursor_next203' => 'wp.returning.current.handoff.cursor.224',
        'current_generation_commit_marker_next203' => 'wp.current.recursive.returning.commit.224',
        'auto_ack_current_generation_receipts_next203' => true,
        'current_source_drain_token_next209' => 'wp.current.source.drain.224',
        'current_view_cookie_next209' => 'main@view-cookie-224-current',
        'current_trigger_cookie_next209' => 'main@trigger-cookie-224-current',
        'auto_ack_current_source_watermarks_next209' => true,
        'auto_ack_current_source_yields_next212' => true,
        'current_source_epoch_next218' => 'wp.current.source.epoch.224',
        'current_view_epoch_next218' => 'wp.returning.view.epoch.cursor.224',
        'current_trigger_epoch_next218' => 'wp.returning.trigger.epoch.cursor.224',
        'auto_ack_current_source_epochs_next218' => true,
        'current_returning_source_token_source_seal' => 'wp.current.returning.source.224',
        'current_returning_view_source_source_seal' => 'main@view-cookie-224-current',
        'current_returning_trigger_source_source_seal' => 'main@trigger-cookie-224-current',
    ],
);

$seals224 = static fn (): array => $plan224()['required_current_returning_source_seals_source_seal'];
$released224 = static fn (): array => $plan224(['auto_ack_current_returning_source_seals_source_seal' => true]);
$missing224 = static fn (): array => $plan224(['acknowledged_current_returning_source_seals_source_seal' => array_slice($seals224(), 0, 1)]);
$unexpected224 = static fn (): array => $plan224(['acknowledged_current_returning_source_seals_source_seal' => array_merge($seals224(), ['abcdefabcdefabcdefabcdefabcdefabcdefabcd'])]);
$sourceHeld224 = static fn (): array => $plan224(['auto_ack_current_returning_source_seals_source_seal' => true, 'expected_current_returning_source_token_source_seal' => 'wp.current.returning.source.stale.224']);
$viewHeld224 = static fn (): array => $plan224(['auto_ack_current_returning_source_seals_source_seal' => true, 'expected_current_returning_view_source_source_seal' => 'main@view-cookie-224-stale']);
$triggerHeld224 = static fn (): array => $plan224(['auto_ack_current_returning_source_seals_source_seal' => true, 'expected_current_returning_trigger_source_source_seal' => 'main@trigger-cookie-224-stale']);
$baseHeld224 = static fn (): array => $plan224(['auto_ack_current_returning_source_seals_source_seal' => true, 'auto_ack_current_source_epochs_next218' => false]);
$custom224 = static fn (): array => $plan224([
    'auto_ack_current_returning_source_seals_source_seal' => true,
    'current_returning_source_token_source_seal' => 'wp.current.returning.source.custom.224',
    'current_returning_view_source_source_seal' => 'main@view-cookie-224-custom',
    'current_returning_trigger_source_source_seal' => 'main@trigger-cookie-224-custom',
]);

$cases224 = [
    'released status' => [static fn (): mixed => $released224()['status_source_seal'], 'trigger-recursive-view-returning-current-source-source_seal-source-released'],
    'missing status' => [static fn (): mixed => $missing224()['status_source_seal'], 'trigger-recursive-view-returning-current-source-source_seal-seal-held'],
    'unexpected status' => [static fn (): mixed => $unexpected224()['status_source_seal'], 'trigger-recursive-view-returning-current-source-source_seal-seal-held'],
    'source held status' => [static fn (): mixed => $sourceHeld224()['status_source_seal'], 'trigger-recursive-view-returning-current-source-source_seal-source-held'],
    'view held status' => [static fn (): mixed => $viewHeld224()['status_source_seal'], 'trigger-recursive-view-returning-current-source-source_seal-view-held'],
    'trigger held status' => [static fn (): mixed => $triggerHeld224()['status_source_seal'], 'trigger-recursive-view-returning-current-source-source_seal-trigger-held'],
    'base held status' => [static fn (): mixed => $baseHeld224()['status_source_seal'], 'trigger-recursive-view-returning-current-source-source_seal-base-held'],
    'savepoint retained' => [static fn (): mixed => $released224()['savepoint'], 'wp_recursive_view_224'],
    'base next218 released' => [static fn (): mixed => $released224()['base']['status_next218'], 'trigger-recursive-view-returning-current-source-next218-epoch-released'],
    'base next218 held' => [static fn (): mixed => $baseHeld224()['base']['status_next218'], 'trigger-recursive-view-returning-current-source-next218-epoch-held'],
    'base visible released' => [static fn (): mixed => $released224()['base_next_source_visible_source_seal'], true],
    'base visible held' => [static fn (): mixed => $baseHeld224()['base_next_source_visible_source_seal'], false],
    'source token retained' => [static fn (): mixed => $released224()['current_returning_source_token_source_seal'], 'wp.current.returning.source.224'],
    'custom source token retained' => [static fn (): mixed => $custom224()['current_returning_source_token_source_seal'], 'wp.current.returning.source.custom.224'],
    'expected source token defaults' => [static fn (): mixed => $released224()['expected_current_returning_source_token_source_seal'], 'wp.current.returning.source.224'],
    'view source retained' => [static fn (): mixed => $released224()['current_returning_view_source_source_seal'], 'main@view-cookie-224-current'],
    'custom view source retained' => [static fn (): mixed => $custom224()['current_returning_view_source_source_seal'], 'main@view-cookie-224-custom'],
    'trigger source retained' => [static fn (): mixed => $released224()['current_returning_trigger_source_source_seal'], 'main@trigger-cookie-224-current'],
    'custom trigger source retained' => [static fn (): mixed => $custom224()['current_returning_trigger_source_source_seal'], 'main@trigger-cookie-224-custom'],
    'source matches released' => [static fn (): mixed => $released224()['current_returning_source_matches_source_seal'], true],
    'source mismatch detected' => [static fn (): mixed => $sourceHeld224()['current_returning_source_matches_source_seal'], false],
    'view matches released' => [static fn (): mixed => $released224()['current_returning_view_source_matches_source_seal'], true],
    'view mismatch detected' => [static fn (): mixed => $viewHeld224()['current_returning_view_source_matches_source_seal'], false],
    'trigger matches released' => [static fn (): mixed => $released224()['current_returning_trigger_source_matches_source_seal'], true],
    'trigger mismatch detected' => [static fn (): mixed => $triggerHeld224()['current_returning_trigger_source_matches_source_seal'], false],
    'required seal count' => [static fn (): mixed => count($released224()['required_current_returning_source_seals_source_seal']), 2],
    'source seals are 40 hex' => [static fn (): mixed => array_map(static fn (string $v): int => preg_match('/^[a-f0-9]{40}$/', $v), $released224()['required_current_returning_source_seals_source_seal']), [1, 1]],
    'auto acknowledged equals required' => [static fn (): mixed => $released224()['acknowledged_current_returning_source_seals_source_seal'], $seals224()],
    'missing acknowledged count' => [static fn (): mixed => count($missing224()['acknowledged_current_returning_source_seals_source_seal']), 1],
    'missing seal recorded' => [static fn (): mixed => $missing224()['missing_current_returning_source_seals_source_seal'], [array_slice($seals224(), -1)[0]]],
    'unexpected seal recorded' => [static fn (): mixed => $unexpected224()['unexpected_current_returning_source_seals_source_seal'], ['abcdefabcdefabcdefabcdefabcdefabcdefabcd']],
    'released missing empty' => [static fn (): mixed => $released224()['missing_current_returning_source_seals_source_seal'], []],
    'released unexpected empty' => [static fn (): mixed => $released224()['unexpected_current_returning_source_seals_source_seal'], []],
    'source complete released' => [static fn (): mixed => $released224()['current_returning_source_complete_source_seal'], true],
    'source incomplete missing' => [static fn (): mixed => $missing224()['current_returning_source_complete_source_seal'], false],
    'source incomplete unexpected' => [static fn (): mixed => $unexpected224()['current_returning_source_complete_source_seal'], false],
    'source incomplete source mismatch' => [static fn (): mixed => $sourceHeld224()['current_returning_source_complete_source_seal'], false],
    'source incomplete view mismatch' => [static fn (): mixed => $viewHeld224()['current_returning_source_complete_source_seal'], false],
    'source incomplete trigger mismatch' => [static fn (): mixed => $triggerHeld224()['current_returning_source_complete_source_seal'], false],
    'next visible released' => [static fn (): mixed => $released224()['next_source_visible_after_current_returning_source_source_seal'], true],
    'next denied missing' => [static fn (): mixed => $missing224()['next_source_visible_after_current_returning_source_source_seal'], false],
    'next denied unexpected' => [static fn (): mixed => $unexpected224()['next_source_visible_after_current_returning_source_source_seal'], false],
    'next denied source' => [static fn (): mixed => $sourceHeld224()['next_source_visible_after_current_returning_source_source_seal'], false],
    'next denied view' => [static fn (): mixed => $viewHeld224()['next_source_visible_after_current_returning_source_source_seal'], false],
    'next denied trigger' => [static fn (): mixed => $triggerHeld224()['next_source_visible_after_current_returning_source_source_seal'], false],
    'next denied base' => [static fn (): mixed => $baseHeld224()['next_source_visible_after_current_returning_source_source_seal'], false],
    'current row count' => [static fn (): mixed => $released224()['current_source_row_count_source_seal'], 2],
    'attempted next row count' => [static fn (): mixed => $released224()['attempted_next_source_row_count_source_seal'], 2],
    'visible released count' => [static fn (): mixed => $released224()['visible_row_count_source_seal'], 4],
    'held released count' => [static fn (): mixed => $released224()['held_next_row_count_source_seal'], 0],
    'visible missing count current only' => [static fn (): mixed => $missing224()['visible_row_count_source_seal'], 2],
    'held missing count next only' => [static fn (): mixed => $missing224()['held_next_row_count_source_seal'], 2],
    'current phases' => [static fn (): mixed => array_values(array_unique(array_column($released224()['current_source_rows_source_seal'], 'returning_source_phase_source_seal'))), ['current']],
    'next phases' => [static fn (): mixed => array_values(array_unique(array_column($released224()['attempted_next_source_rows_source_seal'], 'returning_source_phase_source_seal'))), ['next']],
    'current visible while missing' => [static fn (): mixed => array_values(array_unique(array_column($missing224()['current_source_rows_source_seal'], 'visible_after_current_returning_source_source_seal'))), [true]],
    'next visible released' => [static fn (): mixed => array_values(array_unique(array_column($released224()['attempted_next_source_rows_source_seal'], 'visible_after_current_returning_source_source_seal'))), [true]],
    'next held missing' => [static fn (): mixed => array_values(array_unique(array_column($missing224()['attempted_next_source_rows_source_seal'], 'visible_after_current_returning_source_source_seal'))), [false]],
    'current seals tagged' => [static fn (): mixed => array_column($released224()['current_source_rows_source_seal'], 'current_returning_source_seal_source_seal'), $seals224()],
    'next seals null' => [static fn (): mixed => array_values(array_unique(array_column($released224()['attempted_next_source_rows_source_seal'], 'current_returning_source_seal_source_seal'))), [null]],
    'current source token stamped' => [static fn (): mixed => array_values(array_unique(array_column($released224()['current_source_rows_source_seal'], 'current_returning_source_token_source_seal'))), ['wp.current.returning.source.224']],
    'next source token stamped' => [static fn (): mixed => array_values(array_unique(array_column($released224()['attempted_next_source_rows_source_seal'], 'current_returning_source_token_source_seal'))), ['wp.current.returning.source.224']],
    'current view source stamped' => [static fn (): mixed => array_values(array_unique(array_column($released224()['current_source_rows_source_seal'], 'current_returning_view_source_source_seal'))), ['main@view-cookie-224-current']],
    'next trigger source stamped' => [static fn (): mixed => array_values(array_unique(array_column($released224()['attempted_next_source_rows_source_seal'], 'current_returning_trigger_source_source_seal'))), ['main@trigger-cookie-224-current']],
    'visible payload names released' => [static fn (): mixed => array_column($released224()['visible_returning_payloads_source_seal'], 'name'), ['blogdescription_child', 'template_child', 'home', 'next_plugin']],
    'held payload names missing' => [static fn (): mixed => array_column($missing224()['held_next_returning_payloads_source_seal'], 'name'), ['home', 'next_plugin']],
    'blocked reasons missing' => [static fn (): mixed => $missing224()['blocked_reasons_source_seal'], ['current-returning-source-seal-missing']],
    'blocked reasons unexpected' => [static fn (): mixed => $unexpected224()['blocked_reasons_source_seal'], ['current-returning-source-seal-unexpected']],
    'blocked reasons source' => [static fn (): mixed => $sourceHeld224()['blocked_reasons_source_seal'], ['current-returning-source-token-mismatch']],
    'blocked reasons view' => [static fn (): mixed => $viewHeld224()['blocked_reasons_source_seal'], ['current-returning-view-source-mismatch']],
    'blocked reasons trigger' => [static fn (): mixed => $triggerHeld224()['blocked_reasons_source_seal'], ['current-returning-trigger-source-mismatch']],
    'blocked reasons base held' => [static fn (): mixed => $baseHeld224()['blocked_reasons_source_seal'], ['current-source-epoch-missing']],
    'released reasons empty' => [static fn (): mixed => $released224()['blocked_reasons_source_seal'], []],
    'held next reason tagged' => [static fn (): mixed => $missing224()['attempted_next_source_rows_source_seal'][0]['held_by_current_returning_source_reasons_source_seal'], ['current-returning-source-seal-missing']],
    'released next reason empty' => [static fn (): mixed => $released224()['attempted_next_source_rows_source_seal'][0]['held_by_current_returning_source_reasons_source_seal'], []],
    'plan decision released' => [static fn (): mixed => $released224()['current_returning_source_plan_source_seal']['decision'], 'publish-next-source-after-current-returning-source-seal'],
    'plan decision missing' => [static fn (): mixed => $missing224()['current_returning_source_plan_source_seal']['decision'], 'hold-next-source-until-current-returning-source-seal'],
    'plan base visible' => [static fn (): mixed => $released224()['current_returning_source_plan_source_seal']['base_next_source_visible'], true],
    'plan base held' => [static fn (): mixed => $baseHeld224()['current_returning_source_plan_source_seal']['base_next_source_visible'], false],
    'plan required echoed' => [static fn (): mixed => $released224()['current_returning_source_plan_source_seal']['required_seals'], $seals224()],
    'plan acknowledged echoed' => [static fn (): mixed => $missing224()['current_returning_source_plan_source_seal']['acknowledged_seals'], array_slice($seals224(), 0, 1)],
    'plan next visible echoed' => [static fn (): mixed => $released224()['current_returning_source_plan_source_seal']['next_source_visible'], true],
    'yield boundary released' => [static fn (): mixed => $released224()['yield_boundary_source_seal'], 'recursive-view-returning-source_seal-current-source-sealed-then-next'],
    'yield boundary held' => [static fn (): mixed => $missing224()['yield_boundary_source_seal'], 'recursive-view-returning-source_seal-current-source-seal-fences-next'],
    'dependency closure marker' => [static fn (): mixed => $released224()['dependency_closure_source_seal'], 'no-new-support-component-reuses-native-recursive-view-returning-current-source-epoch-and-adds-source-seal'],
    'dependency includes source_seal' => [static fn (): mixed => in_array('sqlite-trigger-recursive-view-returning-current-source-source_seal', $released224()['dependencies_source_seal'], true), true],
    'dependency includes source seal' => [static fn (): mixed => in_array('sqlite-returning-current-source-seal', $released224()['dependencies_source_seal'], true), true],
    'dependency includes next218' => [static fn (): mixed => in_array('sqlite-trigger-recursive-view-returning-current-source-next218', $released224()['dependencies_source_seal'], true), true],
    'non overlap mentions next218' => [static fn (): mixed => str_contains($released224()['non_overlap_source_seal'], 'next218 epoch receipts'), true],
    'bad source token rejected' => [static fn (): mixed => $plan224(['current_returning_source_token_source_seal' => 'bad token']), InvalidArgumentException::class],
    'bad expected source token rejected' => [static fn (): mixed => $plan224(['expected_current_returning_source_token_source_seal' => 'bad token']), InvalidArgumentException::class],
    'bad view source rejected' => [static fn (): mixed => $plan224(['current_returning_view_source_source_seal' => 'bad source']), InvalidArgumentException::class],
    'bad trigger source rejected' => [static fn (): mixed => $plan224(['current_returning_trigger_source_source_seal' => 'bad source']), InvalidArgumentException::class],
    'bad seal list rejected' => [static fn (): mixed => $plan224(['acknowledged_current_returning_source_seals_source_seal' => ['x' => 'abcdefabcdefabcdefabcdefabcdefabcdefabcd']]), InvalidArgumentException::class],
    'bad short seal rejected' => [static fn (): mixed => $plan224(['acknowledged_current_returning_source_seals_source_seal' => ['abc']]), InvalidArgumentException::class],
    'bad non hex seal rejected' => [static fn (): mixed => $plan224(['acknowledged_current_returning_source_seals_source_seal' => ['zzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzz']]), InvalidArgumentException::class],
];

$tests = [];
foreach ($cases224 as $name => [$callback, $expected]) {
    $tests['trigger recursive view returning current source source_seal ' . $name] = static function (TestRunner $t) use ($callback, $expected): void {
        if (is_string($expected) && is_a($expected, Throwable::class, true)) {
            $t->throws($expected, $callback);
            return;
        }
        $t->same($expected, $callback());
    };
}

return $tests;
