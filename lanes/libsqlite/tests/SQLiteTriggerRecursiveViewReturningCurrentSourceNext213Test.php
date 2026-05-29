<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteTriggerRecursiveViewReturningCurrentSourceNextPlan;

$rows213 = [
    ['option_id' => 1, 'option_name' => 'siteurl', 'option_value' => 'https://old.test', 'autoload' => 'yes'],
    ['option_id' => 2, 'option_name' => 'home', 'option_value' => 'https://home.test', 'autoload' => 'yes'],
];
$currentView213 = [
    'name' => 'wp_recursive_option_import',
    'source' => 'main@view-cookie-213-current',
    'trigger' => 'wp_recursive_option_import_io_insert',
    'trigger_source' => 'main@trigger-cookie-213-current',
    'columns' => ['import_id', 'name', 'value', 'autoload_flag', 'spawn_child'],
    'mapping' => ['import_id' => 'option_id', 'name' => 'option_name', 'value' => 'option_value', 'autoload_flag' => 'autoload', 'spawn_child' => 'spawn_child'],
    'audit_label' => 'current-recursive-view-trigger-213',
];
$nextView213 = $currentView213;
$nextView213['source'] = 'main@view-cookie-213-next';
$nextView213['trigger_source'] = 'main@trigger-cookie-213-next';
$postResetView213 = $currentView213;
$postResetView213['source'] = 'main@view-cookie-213-post-reset';
$postResetView213['trigger_source'] = 'main@trigger-cookie-213-post-reset';
$followingView213 = $currentView213;
$followingView213['source'] = 'main@view-cookie-213-following';
$followingView213['trigger_source'] = 'main@trigger-cookie-213-following';
$currentInput213 = [
    ['import_id' => 10, 'name' => 'siteurl', 'value' => 'https://current.test', 'autoload_flag' => 'yes', 'spawn_child' => true],
    ['import_id' => 11, 'name' => 'current_plugin', 'value' => 'enabled', 'autoload_flag' => 'no', 'spawn_child' => true],
];
$nextInput213 = [
    ['import_id' => 20, 'name' => 'home', 'value' => 'https://next.test', 'autoload_flag' => 'yes', 'spawn_child' => true],
    ['import_id' => 21, 'name' => 'next_plugin', 'value' => 'active', 'autoload_flag' => 'no', 'spawn_child' => false],
];
$returning213 = [
    ['expr' => 'new.option_name', 'as' => 'name'],
    ['expr' => 'new.option_value', 'as' => 'value'],
    ['expr' => 'event', 'as' => 'event_name'],
    ['expr' => 'depth', 'as' => 'depth_value'],
    ['expr' => 'ordinal', 'as' => 'ordinal_value'],
    ['expr' => 'trigger_source', 'as' => 'trigger_source_alias'],
    ['expr' => 'spawn_child', 'as' => 'spawn_child'],
];

$plan213 = static fn (array $options = []): array => SQLiteTriggerRecursiveViewReturningCurrentSourceNextPlan::executeNext213(
    $rows213,
    $currentInput213,
    $nextInput213,
    $currentView213,
    $nextView213,
    $returning213,
    $options + [
        'key' => 'option_name',
        'savepoint' => 'wp_recursive_view_213',
        'cursor_name' => 'wp_recursive_view_returning_cursor_213',
        'admit_next_source' => true,
        'rollback_token' => 'wp.rollback.current.213',
        'reset_generation' => 'wp-current-reset-213',
        'post_reset_current_source_token' => 'wp.current.source.postreset.213',
        'post_reset_cursor' => 'wp.returning.postreset.cursor.213',
        'post_reset_view' => $postResetView213,
        'post_reset_input' => [
            ['import_id' => 30, 'name' => 'siteurl', 'value' => 'https://fresh.test', 'autoload_flag' => 'yes', 'spawn_child' => false],
            ['import_id' => 31, 'name' => 'rewrite_rules', 'value' => 'fresh-rules', 'autoload_flag' => 'no', 'spawn_child' => false],
        ],
        'fresh_acknowledged_ordinals' => [0, 1],
        'next_source_token' => 'wp.next.source.213',
        'next_cursor' => 'wp.returning.next.cursor.213',
        'next_acknowledged_ordinals' => [0, 1],
        'close_next_cursor' => 'wp.returning.next.cursor.213',
        'following_current_source_token' => 'wp.current.source.following.213',
        'following_cursor' => 'wp.returning.following.cursor.213',
        'following_current_view' => $followingView213,
        'following_current_input' => [
            ['import_id' => 40, 'name' => 'blogdescription', 'value' => 'after-next', 'autoload_flag' => 'yes', 'spawn_child' => true],
            ['import_id' => 41, 'name' => 'stylesheet', 'value' => 'twentytwentyfive', 'autoload_flag' => 'yes', 'spawn_child' => false],
            ['import_id' => 42, 'name' => 'template', 'value' => 'twentytwentyfive', 'autoload_flag' => 'yes', 'spawn_child' => true],
        ],
        'following_generation' => 'wp-following-current-213',
        'recursive_child_acknowledged_ordinals' => [0, 1],
        'recursive_child_source_token' => 'wp.current.source.recursive.child.213',
        'recursive_child_cursor' => 'wp.returning.recursive.child.cursor.213',
        'recursive_child_generation' => 'wp-recursive-child-current-213',
        'current_generation_next203' => 'wp.current.recursive.returning.generation.213',
        'expected_current_generation_next203' => 'wp.current.recursive.returning.generation.213',
        'current_handoff_cursor_next203' => 'wp.returning.current.handoff.cursor.213',
        'current_generation_commit_marker_next203' => 'wp.current.recursive.returning.commit.213',
        'auto_ack_current_generation_receipts_next203' => true,
        'current_source_drain_token_next209' => 'wp.current.source.drain.213',
        'current_view_cookie_next209' => 'main@view-cookie-213-current',
        'current_trigger_cookie_next209' => 'main@trigger-cookie-213-current',
        'auto_ack_current_source_watermarks_next209' => true,
        'current_source_yield_token_next212' => 'wp.current.source.yield.213',
        'current_view_yield_cursor_next212' => 'wp.returning.view.yield.cursor.213',
        'current_trigger_yield_cursor_next212' => 'wp.returning.trigger.yield.cursor.213',
        'auto_ack_current_source_yields_next212' => true,
        'current_source_payload_seal_token_next213' => 'wp.current.source.payload.seal.213',
        'current_source_payload_seal_cursor_next213' => 'wp.returning.current.payload.cursor.213',
    ],
);

$seals213 = static fn (): array => $plan213()['required_current_source_payload_seals_next213'];
$released213 = static fn (): array => $plan213(['auto_ack_current_source_payload_seals_next213' => true]);
$missing213 = static fn (): array => $plan213(['acknowledged_current_source_payload_seals_next213' => array_slice($seals213(), 0, 1)]);
$unexpected213 = static fn (): array => $plan213(['acknowledged_current_source_payload_seals_next213' => array_merge($seals213(), ['abcdefabcdefabcdefabcdefabcdefabcdefabcd'])]);
$reversed213 = static fn (): array => $plan213(['acknowledged_current_source_payload_seals_next213' => array_reverse($seals213())]);
$unordered213 = static fn (): array => $plan213(['require_current_source_payload_seal_order_next213' => false, 'acknowledged_current_source_payload_seals_next213' => array_reverse($seals213())]);
$baseHeld213 = static fn (): array => $plan213(['auto_ack_current_source_payload_seals_next213' => true, 'auto_ack_current_source_yields_next212' => false]);
$custom213 = static fn (): array => $plan213([
    'auto_ack_current_source_payload_seals_next213' => true,
    'current_source_payload_seal_token_next213' => 'wp.current.source.payload.seal.custom.213',
    'current_source_payload_seal_cursor_next213' => 'wp.returning.current.payload.cursor.custom.213',
]);

$cases213 = [
    'released status' => [static fn (): mixed => $released213()['status_next213'], 'trigger-recursive-view-returning-current-source-next213-payload-seal-released'],
    'missing status' => [static fn (): mixed => $missing213()['status_next213'], 'trigger-recursive-view-returning-current-source-next213-payload-seal-held'],
    'unexpected status' => [static fn (): mixed => $unexpected213()['status_next213'], 'trigger-recursive-view-returning-current-source-next213-payload-seal-held'],
    'reversed status' => [static fn (): mixed => $reversed213()['status_next213'], 'trigger-recursive-view-returning-current-source-next213-payload-seal-order-held'],
    'unordered reversed releases' => [static fn (): mixed => $unordered213()['status_next213'], 'trigger-recursive-view-returning-current-source-next213-payload-seal-released'],
    'base held status' => [static fn (): mixed => $baseHeld213()['status_next213'], 'trigger-recursive-view-returning-current-source-next213-base-held'],
    'savepoint retained' => [static fn (): mixed => $released213()['savepoint'], 'wp_recursive_view_213'],
    'base next212 released' => [static fn (): mixed => $released213()['base']['status_next212'], 'trigger-recursive-view-returning-current-source-next212-yield-released'],
    'base next212 held' => [static fn (): mixed => $baseHeld213()['base']['status_next212'], 'trigger-recursive-view-returning-current-source-next212-yield-held'],
    'base visible released' => [static fn (): mixed => $released213()['base_next_source_visible_next213'], true],
    'base visible held' => [static fn (): mixed => $baseHeld213()['base_next_source_visible_next213'], false],
    'seal token retained' => [static fn (): mixed => $released213()['current_source_payload_seal_token_next213'], 'wp.current.source.payload.seal.213'],
    'custom seal token retained' => [static fn (): mixed => $custom213()['current_source_payload_seal_token_next213'], 'wp.current.source.payload.seal.custom.213'],
    'seal cursor retained' => [static fn (): mixed => $released213()['current_source_payload_seal_cursor_next213'], 'wp.returning.current.payload.cursor.213'],
    'custom seal cursor retained' => [static fn (): mixed => $custom213()['current_source_payload_seal_cursor_next213'], 'wp.returning.current.payload.cursor.custom.213'],
    'required seal count' => [static fn (): mixed => count($released213()['required_current_source_payload_seals_next213']), 2],
    'payload seals are forty hex' => [static fn (): mixed => array_map(static fn (string $v): int => preg_match('/^[a-f0-9]{40}$/', $v), $released213()['required_current_source_payload_seals_next213']), [1, 1]],
    'auto acknowledged equals required' => [static fn (): mixed => $released213()['acknowledged_current_source_payload_seals_next213'], $seals213()],
    'missing acknowledged count' => [static fn (): mixed => count($missing213()['acknowledged_current_source_payload_seals_next213']), 1],
    'missing payload seal recorded' => [static fn (): mixed => $missing213()['missing_current_source_payload_seals_next213'], [array_slice($seals213(), -1)[0]]],
    'unexpected payload seal recorded' => [static fn (): mixed => $unexpected213()['unexpected_current_source_payload_seals_next213'], ['abcdefabcdefabcdefabcdefabcdefabcdefabcd']],
    'released missing empty' => [static fn (): mixed => $released213()['missing_current_source_payload_seals_next213'], []],
    'released unexpected empty' => [static fn (): mixed => $released213()['unexpected_current_source_payload_seals_next213'], []],
    'require order default' => [static fn (): mixed => $released213()['require_current_source_payload_seal_order_next213'], true],
    'order matches released' => [static fn (): mixed => $released213()['current_source_payload_seal_order_matches_next213'], true],
    'order mismatch reversed' => [static fn (): mixed => $reversed213()['current_source_payload_seal_order_matches_next213'], false],
    'unordered disables order' => [static fn (): mixed => $unordered213()['require_current_source_payload_seal_order_next213'], false],
    'unordered order considered matched' => [static fn (): mixed => $unordered213()['current_source_payload_seal_order_matches_next213'], true],
    'seal complete released' => [static fn (): mixed => $released213()['current_source_payload_seal_complete_next213'], true],
    'seal incomplete missing' => [static fn (): mixed => $missing213()['current_source_payload_seal_complete_next213'], false],
    'seal incomplete unexpected' => [static fn (): mixed => $unexpected213()['current_source_payload_seal_complete_next213'], false],
    'seal incomplete reversed' => [static fn (): mixed => $reversed213()['current_source_payload_seal_complete_next213'], false],
    'next visible released' => [static fn (): mixed => $released213()['next_source_visible_after_current_source_payload_seal_next213'], true],
    'next denied missing' => [static fn (): mixed => $missing213()['next_source_visible_after_current_source_payload_seal_next213'], false],
    'next denied unexpected' => [static fn (): mixed => $unexpected213()['next_source_visible_after_current_source_payload_seal_next213'], false],
    'next denied reversed' => [static fn (): mixed => $reversed213()['next_source_visible_after_current_source_payload_seal_next213'], false],
    'next denied base held' => [static fn (): mixed => $baseHeld213()['next_source_visible_after_current_source_payload_seal_next213'], false],
    'current row count' => [static fn (): mixed => $released213()['current_source_row_count_next213'], 2],
    'attempted next row count' => [static fn (): mixed => $released213()['attempted_next_source_row_count_next213'], 2],
    'visible released count' => [static fn (): mixed => $released213()['visible_row_count_next213'], 4],
    'held released count' => [static fn (): mixed => $released213()['held_next_row_count_next213'], 0],
    'visible missing count current only' => [static fn (): mixed => $missing213()['visible_row_count_next213'], 2],
    'held missing count next only' => [static fn (): mixed => $missing213()['held_next_row_count_next213'], 2],
    'current phases' => [static fn (): mixed => array_values(array_unique(array_column($released213()['current_source_rows_next213'], 'payload_seal_phase_next213'))), ['current']],
    'next phases' => [static fn (): mixed => array_values(array_unique(array_column($released213()['attempted_next_source_rows_next213'], 'payload_seal_phase_next213'))), ['next']],
    'current visible while missing' => [static fn (): mixed => array_values(array_unique(array_column($missing213()['current_source_rows_next213'], 'visible_after_current_source_payload_seal_next213'))), [true]],
    'next visible released' => [static fn (): mixed => array_values(array_unique(array_column($released213()['attempted_next_source_rows_next213'], 'visible_after_current_source_payload_seal_next213'))), [true]],
    'next held missing' => [static fn (): mixed => array_values(array_unique(array_column($missing213()['attempted_next_source_rows_next213'], 'visible_after_current_source_payload_seal_next213'))), [false]],
    'current seals tagged' => [static fn (): mixed => array_column($released213()['current_source_rows_next213'], 'current_source_payload_seal_next213'), $seals213()],
    'next seals null' => [static fn (): mixed => array_values(array_unique(array_column($released213()['attempted_next_source_rows_next213'], 'current_source_payload_seal_next213'))), [null]],
    'current seal token stamped' => [static fn (): mixed => array_values(array_unique(array_column($released213()['current_source_rows_next213'], 'current_source_payload_seal_token_next213'))), ['wp.current.source.payload.seal.213']],
    'next seal token stamped' => [static fn (): mixed => array_values(array_unique(array_column($released213()['attempted_next_source_rows_next213'], 'current_source_payload_seal_token_next213'))), ['wp.current.source.payload.seal.213']],
    'current seal cursor stamped' => [static fn (): mixed => array_values(array_unique(array_column($released213()['current_source_rows_next213'], 'current_source_payload_seal_cursor_next213'))), ['wp.returning.current.payload.cursor.213']],
    'visible payload names released' => [static fn (): mixed => array_column($released213()['visible_returning_payloads_next213'], 'name'), ['blogdescription_child', 'template_child', 'home', 'next_plugin']],
    'held payload names missing' => [static fn (): mixed => array_column($missing213()['held_next_returning_payloads_next213'], 'name'), ['home', 'next_plugin']],
    'blocked reasons missing' => [static fn (): mixed => $missing213()['blocked_reasons_next213'], ['current-source-payload-seal-missing']],
    'blocked reasons unexpected' => [static fn (): mixed => $unexpected213()['blocked_reasons_next213'], ['current-source-payload-seal-unexpected']],
    'blocked reasons reversed' => [static fn (): mixed => $reversed213()['blocked_reasons_next213'], ['current-source-payload-seal-order-mismatch']],
    'blocked reasons base held' => [static fn (): mixed => $baseHeld213()['blocked_reasons_next213'], ['current-source-yield-missing']],
    'released reasons empty' => [static fn (): mixed => $released213()['blocked_reasons_next213'], []],
    'plan decision released' => [static fn (): mixed => $released213()['current_source_payload_seal_plan_next213']['decision'], 'publish-next-source-after-current-payload-seal'],
    'plan decision missing' => [static fn (): mixed => $missing213()['current_source_payload_seal_plan_next213']['decision'], 'hold-next-source-until-current-payload-seal'],
    'plan base visible' => [static fn (): mixed => $released213()['current_source_payload_seal_plan_next213']['base_next_source_visible'], true],
    'plan base held' => [static fn (): mixed => $baseHeld213()['current_source_payload_seal_plan_next213']['base_next_source_visible'], false],
    'plan required echoed' => [static fn (): mixed => $released213()['current_source_payload_seal_plan_next213']['required_payload_seals'], $seals213()],
    'plan acknowledged echoed' => [static fn (): mixed => $missing213()['current_source_payload_seal_plan_next213']['acknowledged_payload_seals'], array_slice($seals213(), 0, 1)],
    'plan next visible echoed' => [static fn (): mixed => $released213()['current_source_payload_seal_plan_next213']['next_source_visible'], true],
    'yield boundary released' => [static fn (): mixed => $released213()['yield_boundary_next213'], 'recursive-view-returning-next213-current-payload-seal-then-next'],
    'yield boundary held' => [static fn (): mixed => $missing213()['yield_boundary_next213'], 'recursive-view-returning-next213-current-payload-seal-fences-next'],
    'dependency closure marker' => [static fn (): mixed => $released213()['dependency_closure_next213'], 'no-new-support-component-reuses-native-recursive-view-returning-current-source-payload-seals'],
    'dependency includes next213' => [static fn (): mixed => in_array('sqlite-trigger-recursive-view-returning-current-source-next213', $released213()['dependencies_next213'], true), true],
    'dependency includes payload seal' => [static fn (): mixed => in_array('sqlite-returning-current-source-payload-seal', $released213()['dependencies_next213'], true), true],
    'dependency includes next212' => [static fn (): mixed => in_array('sqlite-trigger-recursive-view-returning-current-source-next212', $released213()['dependencies_next213'], true), true],
    'non overlap mentions next212' => [static fn (): mixed => str_contains($released213()['non_overlap_next213'], 'next212 yield receipts'), true],
    'bad seal token rejected' => [static fn (): mixed => $plan213(['current_source_payload_seal_token_next213' => 'bad token']), InvalidArgumentException::class],
    'bad seal cursor rejected' => [static fn (): mixed => $plan213(['current_source_payload_seal_cursor_next213' => 'bad cursor']), InvalidArgumentException::class],
    'bad seal list rejected' => [static fn (): mixed => $plan213(['acknowledged_current_source_payload_seals_next213' => ['x' => 'abcdefabcdefabcdefabcdefabcdefabcdefabcd']]), InvalidArgumentException::class],
    'bad short seal rejected' => [static fn (): mixed => $plan213(['acknowledged_current_source_payload_seals_next213' => ['abc']]), InvalidArgumentException::class],
    'bad non hex seal rejected' => [static fn (): mixed => $plan213(['acknowledged_current_source_payload_seals_next213' => ['zzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzz']]), InvalidArgumentException::class],
];

$tests = [];
foreach ($cases213 as $name => [$callback, $expected]) {
    $tests['trigger recursive view returning current source next213 ' . $name] = static function (TestRunner $t) use ($callback, $expected): void {
        if (is_string($expected) && is_a($expected, Throwable::class, true)) {
            $t->throws($expected, $callback);
            return;
        }
        $t->same($expected, $callback());
    };
}

return $tests;
