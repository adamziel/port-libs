<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteTriggerRecursiveViewReturningCurrentSourceNextPlan;

$rows229 = [
    ['option_id' => 1, 'option_name' => 'siteurl', 'option_value' => 'https://old.test', 'autoload' => 'yes'],
    ['option_id' => 2, 'option_name' => 'home', 'option_value' => 'https://home.test', 'autoload' => 'yes'],
];
$currentView229 = [
    'name' => 'wp_recursive_option_import',
    'source' => 'main@view-cookie-229-current',
    'trigger' => 'wp_recursive_option_import_io_insert',
    'trigger_source' => 'main@trigger-cookie-229-current',
    'columns' => ['import_id', 'name', 'value', 'autoload_flag', 'spawn_child'],
    'mapping' => ['import_id' => 'option_id', 'name' => 'option_name', 'value' => 'option_value', 'autoload_flag' => 'autoload', 'spawn_child' => 'spawn_child'],
    'audit_label' => 'current-recursive-view-trigger-229',
];
$nextView229 = $currentView229;
$nextView229['source'] = 'main@view-cookie-229-next';
$nextView229['trigger_source'] = 'main@trigger-cookie-229-next';
$postResetView229 = $currentView229;
$postResetView229['source'] = 'main@view-cookie-229-post-reset';
$postResetView229['trigger_source'] = 'main@trigger-cookie-229-post-reset';
$followingView229 = $currentView229;
$followingView229['source'] = 'main@view-cookie-229-following';
$followingView229['trigger_source'] = 'main@trigger-cookie-229-following';
$currentInput229 = [
    ['import_id' => 10, 'name' => 'siteurl', 'value' => 'https://current.test', 'autoload_flag' => 'yes', 'spawn_child' => true],
    ['import_id' => 11, 'name' => 'current_plugin', 'value' => 'enabled', 'autoload_flag' => 'no', 'spawn_child' => true],
];
$nextInput229 = [
    ['import_id' => 20, 'name' => 'home', 'value' => 'https://next.test', 'autoload_flag' => 'yes', 'spawn_child' => true],
    ['import_id' => 21, 'name' => 'next_plugin', 'value' => 'active', 'autoload_flag' => 'no', 'spawn_child' => false],
];
$returning229 = [
    ['expr' => 'new.option_name', 'as' => 'name'],
    ['expr' => 'new.option_value', 'as' => 'value'],
    ['expr' => 'event', 'as' => 'event_name'],
    ['expr' => 'depth', 'as' => 'depth_value'],
    ['expr' => 'ordinal', 'as' => 'ordinal_value'],
    ['expr' => 'trigger_source', 'as' => 'trigger_source_alias'],
    ['expr' => 'spawn_child', 'as' => 'spawn_child'],
];

$plan229 = static fn (array $options = []): array => SQLiteTriggerRecursiveViewReturningCurrentSourceNextPlan::executeNext229(
    $rows229,
    $currentInput229,
    $nextInput229,
    $currentView229,
    $nextView229,
    $returning229,
    $options + [
        'key' => 'option_name',
        'savepoint' => 'wp_recursive_view_229',
        'cursor_name' => 'wp_recursive_view_returning_cursor_229',
        'admit_next_source' => true,
        'rollback_token' => 'wp.rollback.current.229',
        'reset_generation' => 'wp-current-reset-229',
        'post_reset_current_source_token' => 'wp.current.source.postreset.229',
        'post_reset_cursor' => 'wp.returning.postreset.cursor.229',
        'post_reset_view' => $postResetView229,
        'post_reset_input' => [
            ['import_id' => 30, 'name' => 'siteurl', 'value' => 'https://fresh.test', 'autoload_flag' => 'yes', 'spawn_child' => false],
            ['import_id' => 31, 'name' => 'rewrite_rules', 'value' => 'fresh-rules', 'autoload_flag' => 'no', 'spawn_child' => false],
        ],
        'fresh_acknowledged_ordinals' => [0, 1],
        'next_source_token' => 'wp.next.source.229',
        'next_cursor' => 'wp.returning.next.cursor.229',
        'next_acknowledged_ordinals' => [0, 1],
        'close_next_cursor' => 'wp.returning.next.cursor.229',
        'following_current_source_token' => 'wp.current.source.following.229',
        'following_cursor' => 'wp.returning.following.cursor.229',
        'following_current_view' => $followingView229,
        'following_current_input' => [
            ['import_id' => 40, 'name' => 'blogdescription', 'value' => 'after-next', 'autoload_flag' => 'yes', 'spawn_child' => true],
            ['import_id' => 41, 'name' => 'stylesheet', 'value' => 'twentytwentyfive', 'autoload_flag' => 'yes', 'spawn_child' => false],
            ['import_id' => 42, 'name' => 'template', 'value' => 'twentytwentyfive', 'autoload_flag' => 'yes', 'spawn_child' => true],
        ],
        'recursive_child_acknowledged_ordinals' => [0, 1],
        'recursive_child_source_token' => 'wp.current.source.recursive.child.229',
        'recursive_child_cursor' => 'wp.returning.recursive.child.cursor.229',
        'recursive_child_generation' => 'wp-recursive-child-current-229',
        'current_generation_next203' => 'wp.current.recursive.returning.generation.229',
        'expected_current_generation_next203' => 'wp.current.recursive.returning.generation.229',
        'current_handoff_cursor_next203' => 'wp.returning.current.handoff.cursor.229',
        'current_generation_commit_marker_next203' => 'wp.current.recursive.returning.commit.229',
        'auto_ack_current_generation_receipts_next203' => true,
        'current_source_drain_token_next209' => 'wp.current.source.drain.229',
        'current_view_cookie_next209' => 'main@view-cookie-229-current',
        'current_trigger_cookie_next209' => 'main@trigger-cookie-229-current',
        'auto_ack_current_source_watermarks_next209' => true,
        'auto_ack_current_source_yields_next212' => true,
        'current_source_epoch_next218' => 'wp.current.source.epoch.229',
        'current_view_epoch_next218' => 'wp.returning.view.epoch.cursor.229',
        'current_trigger_epoch_next218' => 'wp.returning.trigger.epoch.cursor.229',
        'auto_ack_current_source_epochs_next218' => true,
        'auto_ack_current_returning_source_seals_next224' => true,
        'current_returning_source_generation_next229' => 'wp.current.returning.source.generation.229',
        'current_returning_view_generation_next229' => 'main@view-cookie-229-current',
        'current_returning_trigger_generation_next229' => 'main@trigger-cookie-229-current',
    ],
);

$seals229 = static fn (): array => $plan229()['required_current_returning_generation_seals_next229'];
$released229 = static fn (): array => $plan229(['auto_ack_current_returning_generation_seals_next229' => true]);
$missing229 = static fn (): array => $plan229(['acknowledged_current_returning_generation_seals_next229' => array_slice($seals229(), 0, 1)]);
$unexpectedSeal229 = 'abcdefabcdefabcdefabcdefabcdefabcdefabcdefab';
$unexpected229 = static fn (): array => $plan229(['acknowledged_current_returning_generation_seals_next229' => array_merge($seals229(), [$unexpectedSeal229])]);
$reversed229 = static fn (): array => $plan229(['acknowledged_current_returning_generation_seals_next229' => array_reverse($seals229())]);
$unordered229 = static fn (): array => $plan229(['require_current_returning_generation_order_next229' => false, 'acknowledged_current_returning_generation_seals_next229' => array_reverse($seals229())]);
$sourceHeld229 = static fn (): array => $plan229(['auto_ack_current_returning_generation_seals_next229' => true, 'expected_current_returning_source_generation_next229' => 'wp.current.returning.source.generation.stale.229']);
$viewHeld229 = static fn (): array => $plan229(['auto_ack_current_returning_generation_seals_next229' => true, 'expected_current_returning_view_generation_next229' => 'main@view-cookie-229-stale']);
$triggerHeld229 = static fn (): array => $plan229(['auto_ack_current_returning_generation_seals_next229' => true, 'expected_current_returning_trigger_generation_next229' => 'main@trigger-cookie-229-stale']);
$baseHeld229 = static fn (): array => $plan229(['auto_ack_current_returning_generation_seals_next229' => true, 'auto_ack_current_source_epochs_next218' => false]);
$custom229 = static fn (): array => $plan229([
    'auto_ack_current_returning_generation_seals_next229' => true,
    'current_returning_source_generation_next229' => 'wp.current.returning.source.generation.custom.229',
    'current_returning_view_generation_next229' => 'main@view-generation-229-custom',
    'current_returning_trigger_generation_next229' => 'main@trigger-generation-229-custom',
]);

$cases229 = [
    'released status' => [static fn (): mixed => $released229()['status_next229'], 'trigger-recursive-view-returning-current-source-next229-generation-released'],
    'missing status' => [static fn (): mixed => $missing229()['status_next229'], 'trigger-recursive-view-returning-current-source-next229-generation-seal-held'],
    'unexpected status' => [static fn (): mixed => $unexpected229()['status_next229'], 'trigger-recursive-view-returning-current-source-next229-generation-seal-held'],
    'reversed status' => [static fn (): mixed => $reversed229()['status_next229'], 'trigger-recursive-view-returning-current-source-next229-generation-order-held'],
    'unordered reversed releases' => [static fn (): mixed => $unordered229()['status_next229'], 'trigger-recursive-view-returning-current-source-next229-generation-released'],
    'source generation held status' => [static fn (): mixed => $sourceHeld229()['status_next229'], 'trigger-recursive-view-returning-current-source-next229-source-generation-held'],
    'view generation held status' => [static fn (): mixed => $viewHeld229()['status_next229'], 'trigger-recursive-view-returning-current-source-next229-view-generation-held'],
    'trigger generation held status' => [static fn (): mixed => $triggerHeld229()['status_next229'], 'trigger-recursive-view-returning-current-source-next229-trigger-generation-held'],
    'base held status' => [static fn (): mixed => $baseHeld229()['status_next229'], 'trigger-recursive-view-returning-current-source-next229-base-held'],
    'savepoint retained' => [static fn (): mixed => $released229()['savepoint'], 'wp_recursive_view_229'],
    'base next224 released' => [static fn (): mixed => $released229()['base']['status_next224'], 'trigger-recursive-view-returning-current-source-next224-source-released'],
    'base next218 retained' => [static fn (): mixed => $released229()['base']['base']['status_next218'], 'trigger-recursive-view-returning-current-source-next218-epoch-released'],
    'base next218 held' => [static fn (): mixed => $baseHeld229()['base']['base']['status_next218'], 'trigger-recursive-view-returning-current-source-next218-epoch-held'],
    'base visible released' => [static fn (): mixed => $released229()['base_next_source_visible_next229'], true],
    'base visible held' => [static fn (): mixed => $baseHeld229()['base_next_source_visible_next229'], false],
    'source generation retained' => [static fn (): mixed => $released229()['current_returning_source_generation_next229'], 'wp.current.returning.source.generation.229'],
    'custom source generation retained' => [static fn (): mixed => $custom229()['current_returning_source_generation_next229'], 'wp.current.returning.source.generation.custom.229'],
    'expected source generation defaults' => [static fn (): mixed => $released229()['expected_current_returning_source_generation_next229'], 'wp.current.returning.source.generation.229'],
    'view generation retained' => [static fn (): mixed => $released229()['current_returning_view_generation_next229'], 'main@view-cookie-229-current'],
    'custom view generation retained' => [static fn (): mixed => $custom229()['current_returning_view_generation_next229'], 'main@view-generation-229-custom'],
    'trigger generation retained' => [static fn (): mixed => $released229()['current_returning_trigger_generation_next229'], 'main@trigger-cookie-229-current'],
    'custom trigger generation retained' => [static fn (): mixed => $custom229()['current_returning_trigger_generation_next229'], 'main@trigger-generation-229-custom'],
    'source matches released' => [static fn (): mixed => $released229()['current_returning_source_generation_matches_next229'], true],
    'source mismatch detected' => [static fn (): mixed => $sourceHeld229()['current_returning_source_generation_matches_next229'], false],
    'view matches released' => [static fn (): mixed => $released229()['current_returning_view_generation_matches_next229'], true],
    'view mismatch detected' => [static fn (): mixed => $viewHeld229()['current_returning_view_generation_matches_next229'], false],
    'trigger matches released' => [static fn (): mixed => $released229()['current_returning_trigger_generation_matches_next229'], true],
    'trigger mismatch detected' => [static fn (): mixed => $triggerHeld229()['current_returning_trigger_generation_matches_next229'], false],
    'required seal count' => [static fn (): mixed => count($released229()['required_current_returning_generation_seals_next229']), 2],
    'generation seals are 44 hex' => [static fn (): mixed => array_map(static fn (string $v): int => preg_match('/^[a-f0-9]{44}$/', $v), $released229()['required_current_returning_generation_seals_next229']), [1, 1]],
    'auto acknowledged equals required' => [static fn (): mixed => $released229()['acknowledged_current_returning_generation_seals_next229'], $seals229()],
    'missing acknowledged count' => [static fn (): mixed => count($missing229()['acknowledged_current_returning_generation_seals_next229']), 1],
    'missing seal recorded' => [static fn (): mixed => $missing229()['missing_current_returning_generation_seals_next229'], [array_slice($seals229(), -1)[0]]],
    'unexpected seal recorded' => [static fn (): mixed => $unexpected229()['unexpected_current_returning_generation_seals_next229'], [$unexpectedSeal229]],
    'released missing empty' => [static fn (): mixed => $released229()['missing_current_returning_generation_seals_next229'], []],
    'released unexpected empty' => [static fn (): mixed => $released229()['unexpected_current_returning_generation_seals_next229'], []],
    'require order default' => [static fn (): mixed => $released229()['require_current_returning_generation_order_next229'], true],
    'order matches released' => [static fn (): mixed => $released229()['current_returning_generation_order_matches_next229'], true],
    'order mismatch reversed' => [static fn (): mixed => $reversed229()['current_returning_generation_order_matches_next229'], false],
    'unordered disables order' => [static fn (): mixed => $unordered229()['require_current_returning_generation_order_next229'], false],
    'unordered order considered matched' => [static fn (): mixed => $unordered229()['current_returning_generation_order_matches_next229'], true],
    'generation complete released' => [static fn (): mixed => $released229()['current_returning_generation_complete_next229'], true],
    'source incomplete missing' => [static fn (): mixed => $missing229()['current_returning_generation_complete_next229'], false],
    'source incomplete unexpected' => [static fn (): mixed => $unexpected229()['current_returning_generation_complete_next229'], false],
    'source incomplete reversed' => [static fn (): mixed => $reversed229()['current_returning_generation_complete_next229'], false],
    'source incomplete source mismatch' => [static fn (): mixed => $sourceHeld229()['current_returning_generation_complete_next229'], false],
    'source incomplete view mismatch' => [static fn (): mixed => $viewHeld229()['current_returning_generation_complete_next229'], false],
    'source incomplete trigger mismatch' => [static fn (): mixed => $triggerHeld229()['current_returning_generation_complete_next229'], false],
    'next visible released' => [static fn (): mixed => $released229()['next_source_visible_after_current_returning_generation_next229'], true],
    'next denied missing' => [static fn (): mixed => $missing229()['next_source_visible_after_current_returning_generation_next229'], false],
    'next denied unexpected' => [static fn (): mixed => $unexpected229()['next_source_visible_after_current_returning_generation_next229'], false],
    'next denied reversed' => [static fn (): mixed => $reversed229()['next_source_visible_after_current_returning_generation_next229'], false],
    'next denied source' => [static fn (): mixed => $sourceHeld229()['next_source_visible_after_current_returning_generation_next229'], false],
    'next denied view' => [static fn (): mixed => $viewHeld229()['next_source_visible_after_current_returning_generation_next229'], false],
    'next denied trigger' => [static fn (): mixed => $triggerHeld229()['next_source_visible_after_current_returning_generation_next229'], false],
    'next denied base' => [static fn (): mixed => $baseHeld229()['next_source_visible_after_current_returning_generation_next229'], false],
    'current row count' => [static fn (): mixed => $released229()['current_source_row_count_next229'], 2],
    'attempted next row count' => [static fn (): mixed => $released229()['attempted_next_source_row_count_next229'], 2],
    'visible released count' => [static fn (): mixed => $released229()['visible_row_count_next229'], 4],
    'held released count' => [static fn (): mixed => $released229()['held_next_row_count_next229'], 0],
    'visible missing count current only' => [static fn (): mixed => $missing229()['visible_row_count_next229'], 2],
    'held missing count next only' => [static fn (): mixed => $missing229()['held_next_row_count_next229'], 2],
    'current phases' => [static fn (): mixed => array_values(array_unique(array_column($released229()['current_source_rows_next229'], 'returning_generation_phase_next229'))), ['current']],
    'next phases' => [static fn (): mixed => array_values(array_unique(array_column($released229()['attempted_next_source_rows_next229'], 'returning_generation_phase_next229'))), ['next']],
    'current visible while missing' => [static fn (): mixed => array_values(array_unique(array_column($missing229()['current_source_rows_next229'], 'visible_after_current_returning_generation_next229'))), [true]],
    'next visible released' => [static fn (): mixed => array_values(array_unique(array_column($released229()['attempted_next_source_rows_next229'], 'visible_after_current_returning_generation_next229'))), [true]],
    'next held missing' => [static fn (): mixed => array_values(array_unique(array_column($missing229()['attempted_next_source_rows_next229'], 'visible_after_current_returning_generation_next229'))), [false]],
    'current seals tagged' => [static fn (): mixed => array_column($released229()['current_source_rows_next229'], 'current_returning_generation_seal_next229'), $seals229()],
    'next seals null' => [static fn (): mixed => array_values(array_unique(array_column($released229()['attempted_next_source_rows_next229'], 'current_returning_generation_seal_next229'))), [null]],
    'current source generation stamped' => [static fn (): mixed => array_values(array_unique(array_column($released229()['current_source_rows_next229'], 'current_returning_source_generation_next229'))), ['wp.current.returning.source.generation.229']],
    'next source generation stamped' => [static fn (): mixed => array_values(array_unique(array_column($released229()['attempted_next_source_rows_next229'], 'current_returning_source_generation_next229'))), ['wp.current.returning.source.generation.229']],
    'current view generation stamped' => [static fn (): mixed => array_values(array_unique(array_column($released229()['current_source_rows_next229'], 'current_returning_view_generation_next229'))), ['main@view-cookie-229-current']],
    'next trigger generation stamped' => [static fn (): mixed => array_values(array_unique(array_column($released229()['attempted_next_source_rows_next229'], 'current_returning_trigger_generation_next229'))), ['main@trigger-cookie-229-current']],
    'visible payload names released' => [static fn (): mixed => array_column($released229()['visible_returning_payloads_next229'], 'name'), ['blogdescription_child', 'template_child', 'home', 'next_plugin']],
    'held payload names missing' => [static fn (): mixed => array_column($missing229()['held_next_returning_payloads_next229'], 'name'), ['home', 'next_plugin']],
    'blocked reasons missing' => [static fn (): mixed => $missing229()['blocked_reasons_next229'], ['current-returning-generation-seal-missing']],
    'blocked reasons unexpected' => [static fn (): mixed => $unexpected229()['blocked_reasons_next229'], ['current-returning-generation-seal-unexpected']],
    'blocked reasons reversed' => [static fn (): mixed => $reversed229()['blocked_reasons_next229'], ['current-returning-generation-seal-order-mismatch']],
    'blocked reasons source' => [static fn (): mixed => $sourceHeld229()['blocked_reasons_next229'], ['current-returning-source-generation-mismatch']],
    'blocked reasons view' => [static fn (): mixed => $viewHeld229()['blocked_reasons_next229'], ['current-returning-view-generation-mismatch']],
    'blocked reasons trigger' => [static fn (): mixed => $triggerHeld229()['blocked_reasons_next229'], ['current-returning-trigger-generation-mismatch']],
    'blocked reasons base held' => [static fn (): mixed => $baseHeld229()['blocked_reasons_next229'], ['current-source-epoch-missing']],
    'released reasons empty' => [static fn (): mixed => $released229()['blocked_reasons_next229'], []],
    'held next reason tagged' => [static fn (): mixed => $missing229()['attempted_next_source_rows_next229'][0]['held_by_current_returning_generation_reasons_next229'], ['current-returning-generation-seal-missing']],
    'released next reason empty' => [static fn (): mixed => $released229()['attempted_next_source_rows_next229'][0]['held_by_current_returning_generation_reasons_next229'], []],
    'plan decision released' => [static fn (): mixed => $released229()['current_returning_source_plan_next229']['decision'], 'publish-next-source-after-current-returning-generation-seal'],
    'plan decision missing' => [static fn (): mixed => $missing229()['current_returning_source_plan_next229']['decision'], 'hold-next-source-until-current-returning-generation-seal'],
    'plan base visible' => [static fn (): mixed => $released229()['current_returning_source_plan_next229']['base_next_source_visible'], true],
    'plan base held' => [static fn (): mixed => $baseHeld229()['current_returning_source_plan_next229']['base_next_source_visible'], false],
    'plan required echoed' => [static fn (): mixed => $released229()['current_returning_source_plan_next229']['required_seals'], $seals229()],
    'plan acknowledged echoed' => [static fn (): mixed => $missing229()['current_returning_source_plan_next229']['acknowledged_seals'], array_slice($seals229(), 0, 1)],
    'plan next visible echoed' => [static fn (): mixed => $released229()['current_returning_source_plan_next229']['next_source_visible'], true],
    'yield boundary released' => [static fn (): mixed => $released229()['yield_boundary_next229'], 'recursive-view-returning-next229-current-source-generation-sealed-then-next'],
    'yield boundary held' => [static fn (): mixed => $missing229()['yield_boundary_next229'], 'recursive-view-returning-next229-current-source-generation-seal-fences-next'],
    'dependency closure marker' => [static fn (): mixed => $released229()['dependency_closure_next229'], 'no-new-support-component-reuses-native-recursive-view-returning-current-source-seal-and-adds-generation-seal'],
    'dependency includes next229' => [static fn (): mixed => in_array('sqlite-trigger-recursive-view-returning-current-source-next229', $released229()['dependencies_next229'], true), true],
    'dependency includes generation seal' => [static fn (): mixed => in_array('sqlite-returning-current-source-generation-seal', $released229()['dependencies_next229'], true), true],
    'dependency includes next224' => [static fn (): mixed => in_array('sqlite-trigger-recursive-view-returning-current-source-next224', $released229()['dependencies_next229'], true), true],
    'non overlap mentions next224' => [static fn (): mixed => str_contains($released229()['non_overlap_next229'], 'next224 source seals'), true],
    'bad source generation rejected' => [static fn (): mixed => $plan229(['current_returning_source_generation_next229' => 'bad token']), InvalidArgumentException::class],
    'bad expected source generation rejected' => [static fn (): mixed => $plan229(['expected_current_returning_source_generation_next229' => 'bad token']), InvalidArgumentException::class],
    'bad view generation rejected' => [static fn (): mixed => $plan229(['current_returning_view_generation_next229' => 'bad source']), InvalidArgumentException::class],
    'bad trigger generation rejected' => [static fn (): mixed => $plan229(['current_returning_trigger_generation_next229' => 'bad source']), InvalidArgumentException::class],
    'bad seal list rejected' => [static fn (): mixed => $plan229(['acknowledged_current_returning_generation_seals_next229' => ['x' => 'abcdefabcdefabcdefabcdefabcdefabcdefabcdefab']]), InvalidArgumentException::class],
    'bad short seal rejected' => [static fn (): mixed => $plan229(['acknowledged_current_returning_generation_seals_next229' => ['abc']]), InvalidArgumentException::class],
    'bad non hex seal rejected' => [static fn (): mixed => $plan229(['acknowledged_current_returning_generation_seals_next229' => ['zzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzz']]), InvalidArgumentException::class],
];

$tests = [];
foreach ($cases229 as $name => [$callback, $expected]) {
    $tests['trigger recursive view returning current source next229 ' . $name] = static function (TestRunner $t) use ($callback, $expected): void {
        if (is_string($expected) && is_a($expected, Throwable::class, true)) {
            $t->throws($expected, $callback);
            return;
        }
        $t->same($expected, $callback());
    };
}

return $tests;
