<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteTriggerRecursiveViewReturningCurrentSourceNextPlan;

$rows205 = [
    ['option_id' => 1, 'option_name' => 'siteurl', 'option_value' => 'https://old.test', 'autoload' => 'yes'],
    ['option_id' => 2, 'option_name' => 'home', 'option_value' => 'https://home.test', 'autoload' => 'yes'],
];
$currentView205 = [
    'name' => 'wp_recursive_option_import',
    'source' => 'main@view-cookie-205-current',
    'trigger' => 'wp_recursive_option_import_io_insert',
    'trigger_source' => 'main@trigger-cookie-205-current',
    'columns' => ['import_id', 'name', 'value', 'autoload_flag', 'spawn_child'],
    'mapping' => ['import_id' => 'option_id', 'name' => 'option_name', 'value' => 'option_value', 'autoload_flag' => 'autoload', 'spawn_child' => 'spawn_child'],
    'audit_label' => 'current-recursive-view-trigger-205',
];
$nextView205 = $currentView205;
$nextView205['source'] = 'main@view-cookie-205-next';
$nextView205['trigger_source'] = 'main@trigger-cookie-205-next';
$postResetView205 = $currentView205;
$postResetView205['source'] = 'main@view-cookie-205-post-reset';
$postResetView205['trigger_source'] = 'main@trigger-cookie-205-post-reset';
$followingView205 = $currentView205;
$followingView205['source'] = 'main@view-cookie-205-following';
$followingView205['trigger_source'] = 'main@trigger-cookie-205-following';
$currentInput205 = [
    ['import_id' => 10, 'name' => 'siteurl', 'value' => 'https://current.test', 'autoload_flag' => 'yes', 'spawn_child' => true],
    ['import_id' => 11, 'name' => 'current_plugin', 'value' => 'enabled', 'autoload_flag' => 'no', 'spawn_child' => true],
];
$nextInput205 = [
    ['import_id' => 20, 'name' => 'home', 'value' => 'https://next.test', 'autoload_flag' => 'yes', 'spawn_child' => true],
    ['import_id' => 21, 'name' => 'next_plugin', 'value' => 'active', 'autoload_flag' => 'no', 'spawn_child' => false],
];
$postResetInput205 = [
    ['import_id' => 30, 'name' => 'siteurl', 'value' => 'https://fresh.test', 'autoload_flag' => 'yes', 'spawn_child' => false],
    ['import_id' => 31, 'name' => 'rewrite_rules', 'value' => 'fresh-rules', 'autoload_flag' => 'no', 'spawn_child' => false],
];
$followingInput205 = [
    ['import_id' => 40, 'name' => 'blogdescription', 'value' => 'after-next', 'autoload_flag' => 'yes', 'spawn_child' => true],
    ['import_id' => 41, 'name' => 'stylesheet', 'value' => 'twentytwentyfive', 'autoload_flag' => 'yes', 'spawn_child' => false],
    ['import_id' => 42, 'name' => 'template', 'value' => 'twentytwentyfive', 'autoload_flag' => 'yes', 'spawn_child' => true],
];
$returning205 = [
    ['expr' => 'new.option_name', 'as' => 'name'],
    ['expr' => 'new.option_value', 'as' => 'value'],
    ['expr' => 'old.option_value', 'as' => 'old_value'],
    ['expr' => 'event', 'as' => 'event_name'],
    ['expr' => 'depth', 'as' => 'depth_value'],
    ['expr' => 'ordinal', 'as' => 'ordinal_value'],
    ['expr' => 'trigger_source', 'as' => 'trigger_source_alias'],
    ['expr' => 'spawn_child', 'as' => 'spawn_child'],
];

$plan205 = static fn (array $options = []): array => SQLiteTriggerRecursiveViewReturningCurrentSourceNextPlan::executeNext205(
    $rows205,
    $currentInput205,
    $nextInput205,
    $currentView205,
    $nextView205,
    $returning205,
    $options + [
        'key' => 'option_name',
        'savepoint' => 'wp_recursive_view_205',
        'cursor_name' => 'wp_recursive_view_returning_cursor_205',
        'current_generation' => 'wp-current-returning-205',
        'next_generation' => 'wp-next-returning-205',
        'page_size' => 3,
        'admit_next_source' => true,
        'current_source_token' => 'wp.current.source.205',
        'drain_ack_token' => 'wp.returning.drain.205',
        'rollback_token' => 'wp.rollback.current.205',
        'reset_generation' => 'wp-current-reset-205',
        'post_reset_current_source_token' => 'wp.current.source.postreset.205',
        'post_reset_cursor' => 'wp.returning.postreset.cursor.205',
        'post_reset_view' => $postResetView205,
        'post_reset_input' => $postResetInput205,
        'fresh_acknowledged_ordinals' => [0, 1],
        'next_source_token' => 'wp.next.source.205',
        'next_cursor' => 'wp.returning.next.cursor.205',
        'next_acknowledged_ordinals' => [0, 1],
        'close_next_cursor' => 'wp.returning.next.cursor.205',
        'following_current_source_token' => 'wp.current.source.following.205',
        'following_cursor' => 'wp.returning.following.cursor.205',
        'following_current_view' => $followingView205,
        'following_current_input' => $followingInput205,
        'following_generation' => 'wp-following-current-205',
        'recursive_child_acknowledged_ordinals' => [0, 1],
        'recursive_child_source_token' => 'wp.current.source.recursive.child.205',
        'recursive_child_cursor' => 'wp.returning.recursive.child.cursor.205',
        'recursive_child_generation' => 'wp-recursive-child-current-205',
        'current_generation_next203' => 'wp.current.recursive.returning.generation.205',
        'expected_current_generation_next203' => 'wp.current.recursive.returning.generation.205',
        'current_handoff_cursor_next203' => 'wp.returning.current.handoff.cursor.205',
        'current_generation_commit_marker_next203' => 'wp.current.recursive.returning.commit.205',
        'auto_ack_current_generation_receipts_next203' => true,
        'current_source_sequence_token_next205' => 'wp.current.returning.source.sequence.205',
        'expected_current_source_sequence_token_next205' => 'wp.current.returning.source.sequence.205',
        'next_source_sequence_token_next205' => 'wp.next.returning.source.sequence.205',
        'expected_next_source_sequence_token_next205' => 'wp.next.returning.source.sequence.205',
        'source_sequence_cursor_next205' => 'wp.returning.source.sequence.cursor.205',
    ],
);

$sequence205 = static fn (): array => $plan205()['required_current_source_sequence_next205'];
$released205 = static fn (): array => $plan205(['auto_ack_current_source_sequence_next205' => true]);
$missing205 = static fn (): array => $plan205(['acknowledged_current_source_sequence_next205' => array_slice($sequence205(), 0, 1)]);
$unexpected205 = static fn (): array => $plan205(['acknowledged_current_source_sequence_next205' => array_merge($sequence205(), ['abcdefabcdefabcdefabcdefabcdefab'])]);
$reordered205 = static fn (): array => $plan205(['acknowledged_current_source_sequence_next205' => array_reverse($sequence205())]);
$reorderedAllowed205 = static fn (): array => $plan205(['acknowledged_current_source_sequence_next205' => array_reverse($sequence205()), 'require_source_sequence_order_next205' => false]);
$currentTokenHeld205 = static fn (): array => $plan205(['auto_ack_current_source_sequence_next205' => true, 'expected_current_source_sequence_token_next205' => 'wp.current.returning.source.sequence.stale.205']);
$nextTokenHeld205 = static fn (): array => $plan205(['auto_ack_current_source_sequence_next205' => true, 'expected_next_source_sequence_token_next205' => 'wp.next.returning.source.sequence.stale.205']);
$baseHeld205 = static fn (): array => $plan205(['auto_ack_current_source_sequence_next205' => true, 'recursive_child_acknowledged_ordinals' => [0]]);
$custom205 = static fn (): array => $plan205([
    'auto_ack_current_source_sequence_next205' => true,
    'current_source_sequence_token_next205' => 'wp.current.returning.source.sequence.custom.205',
    'expected_current_source_sequence_token_next205' => 'wp.current.returning.source.sequence.custom.205',
    'next_source_sequence_token_next205' => 'wp.next.returning.source.sequence.custom.205',
    'expected_next_source_sequence_token_next205' => 'wp.next.returning.source.sequence.custom.205',
    'source_sequence_cursor_next205' => 'wp.returning.source.sequence.cursor.custom.205',
]);

$cases205 = [
    'released status' => [static fn (): mixed => $released205()['status_next205'], 'trigger-recursive-view-returning-current-source-next205-source-sequence-released'],
    'missing status' => [static fn (): mixed => $missing205()['status_next205'], 'trigger-recursive-view-returning-current-source-next205-sequence-held'],
    'unexpected status' => [static fn (): mixed => $unexpected205()['status_next205'], 'trigger-recursive-view-returning-current-source-next205-sequence-held'],
    'reordered status' => [static fn (): mixed => $reordered205()['status_next205'], 'trigger-recursive-view-returning-current-source-next205-sequence-held'],
    'reordered allowed status' => [static fn (): mixed => $reorderedAllowed205()['status_next205'], 'trigger-recursive-view-returning-current-source-next205-source-sequence-released'],
    'current token held status' => [static fn (): mixed => $currentTokenHeld205()['status_next205'], 'trigger-recursive-view-returning-current-source-next205-current-source-held'],
    'next token held status' => [static fn (): mixed => $nextTokenHeld205()['status_next205'], 'trigger-recursive-view-returning-current-source-next205-next-source-held'],
    'base held status' => [static fn (): mixed => $baseHeld205()['status_next205'], 'trigger-recursive-view-returning-current-source-next205-base-held'],
    'base next203 released' => [static fn (): mixed => $released205()['base']['status_next203'], 'trigger-recursive-view-returning-current-source-next203-generation-released'],
    'base held keeps next203 held' => [static fn (): mixed => $baseHeld205()['base']['status_next203'], 'trigger-recursive-view-returning-current-source-next203-base-held'],
    'savepoint retained' => [static fn (): mixed => $released205()['savepoint'], 'wp_recursive_view_205'],
    'base visible released' => [static fn (): mixed => $released205()['base_next_source_visible_next205'], true],
    'base visible denied' => [static fn (): mixed => $baseHeld205()['base_next_source_visible_next205'], false],
    'current source token retained' => [static fn (): mixed => $released205()['current_source_sequence_token_next205'], 'wp.current.returning.source.sequence.205'],
    'expected current source token retained' => [static fn (): mixed => $released205()['expected_current_source_sequence_token_next205'], 'wp.current.returning.source.sequence.205'],
    'current source token matches' => [static fn (): mixed => $released205()['current_source_sequence_token_matches_next205'], true],
    'current source token mismatch' => [static fn (): mixed => $currentTokenHeld205()['current_source_sequence_token_matches_next205'], false],
    'next source token retained' => [static fn (): mixed => $released205()['next_source_sequence_token_next205'], 'wp.next.returning.source.sequence.205'],
    'expected next source token retained' => [static fn (): mixed => $released205()['expected_next_source_sequence_token_next205'], 'wp.next.returning.source.sequence.205'],
    'next source token matches' => [static fn (): mixed => $released205()['next_source_sequence_token_matches_next205'], true],
    'next source token mismatch' => [static fn (): mixed => $nextTokenHeld205()['next_source_sequence_token_matches_next205'], false],
    'cursor retained' => [static fn (): mixed => $released205()['source_sequence_cursor_next205'], 'wp.returning.source.sequence.cursor.205'],
    'custom current token retained' => [static fn (): mixed => $custom205()['current_source_sequence_token_next205'], 'wp.current.returning.source.sequence.custom.205'],
    'custom next token retained' => [static fn (): mixed => $custom205()['next_source_sequence_token_next205'], 'wp.next.returning.source.sequence.custom.205'],
    'custom cursor retained' => [static fn (): mixed => $custom205()['source_sequence_cursor_next205'], 'wp.returning.source.sequence.cursor.custom.205'],
    'required sequence count' => [static fn (): mixed => count($released205()['required_current_source_sequence_next205']), 2],
    'required sequence is 32 hex' => [static fn (): mixed => array_map(static fn (string $v): int => preg_match('/^[a-f0-9]{32}$/', $v), $released205()['required_current_source_sequence_next205']), [1, 1]],
    'auto acknowledged equals required' => [static fn (): mixed => $released205()['acknowledged_current_source_sequence_next205'], $sequence205()],
    'missing acknowledged count' => [static fn (): mixed => count($missing205()['acknowledged_current_source_sequence_next205']), 1],
    'missing sequence recorded' => [static fn (): mixed => $missing205()['missing_current_source_sequence_next205'], [array_slice($sequence205(), -1)[0]]],
    'unexpected sequence recorded' => [static fn (): mixed => $unexpected205()['unexpected_current_source_sequence_next205'], ['abcdefabcdefabcdefabcdefabcdefab']],
    'released missing empty' => [static fn (): mixed => $released205()['missing_current_source_sequence_next205'], []],
    'released unexpected empty' => [static fn (): mixed => $released205()['unexpected_current_source_sequence_next205'], []],
    'order required default' => [static fn (): mixed => $released205()['require_source_sequence_order_next205'], true],
    'order matches released' => [static fn (): mixed => $released205()['current_source_sequence_order_matches_next205'], true],
    'order mismatch detected' => [static fn (): mixed => $reordered205()['current_source_sequence_order_matches_next205'], false],
    'order disabled flag' => [static fn (): mixed => $reorderedAllowed205()['require_source_sequence_order_next205'], false],
    'fence released' => [static fn (): mixed => $released205()['current_source_sequence_fence_clear_next205'], true],
    'fence missing blocked' => [static fn (): mixed => $missing205()['current_source_sequence_fence_clear_next205'], false],
    'fence reordered blocked' => [static fn (): mixed => $reordered205()['current_source_sequence_fence_clear_next205'], false],
    'fence reordered allowed' => [static fn (): mixed => $reorderedAllowed205()['current_source_sequence_fence_clear_next205'], true],
    'next visible released' => [static fn (): mixed => $released205()['next_source_visible_after_source_sequence_next205'], true],
    'next denied missing' => [static fn (): mixed => $missing205()['next_source_visible_after_source_sequence_next205'], false],
    'next denied current token' => [static fn (): mixed => $currentTokenHeld205()['next_source_visible_after_source_sequence_next205'], false],
    'next denied next token' => [static fn (): mixed => $nextTokenHeld205()['next_source_visible_after_source_sequence_next205'], false],
    'current row count' => [static fn (): mixed => $released205()['current_source_sequence_row_count_next205'], 2],
    'attempted next row count' => [static fn (): mixed => $released205()['attempted_next_source_sequence_row_count_next205'], 2],
    'visible released count' => [static fn (): mixed => $released205()['visible_row_count_next205'], 4],
    'held released count' => [static fn (): mixed => $released205()['held_next_row_count_next205'], 0],
    'visible missing current only' => [static fn (): mixed => $missing205()['visible_row_count_next205'], 2],
    'held missing next only' => [static fn (): mixed => $missing205()['held_next_row_count_next205'], 2],
    'current phase' => [static fn (): mixed => array_values(array_unique(array_column($released205()['current_source_sequence_rows_next205'], 'source_sequence_phase_next205'))), ['current']],
    'next phase' => [static fn (): mixed => array_values(array_unique(array_column($released205()['attempted_next_source_sequence_rows_next205'], 'source_sequence_phase_next205'))), ['next']],
    'current rows visible while missing' => [static fn (): mixed => array_values(array_unique(array_column($missing205()['current_source_sequence_rows_next205'], 'visible_after_source_sequence_next205'))), [true]],
    'next rows visible released' => [static fn (): mixed => array_values(array_unique(array_column($released205()['attempted_next_source_sequence_rows_next205'], 'visible_after_source_sequence_next205'))), [true]],
    'next rows held missing' => [static fn (): mixed => array_values(array_unique(array_column($missing205()['attempted_next_source_sequence_rows_next205'], 'visible_after_source_sequence_next205'))), [false]],
    'current sequences tagged' => [static fn (): mixed => array_column($released205()['current_source_sequence_rows_next205'], 'current_source_sequence_next205'), $sequence205()],
    'next sequences null' => [static fn (): mixed => array_values(array_unique(array_column($released205()['attempted_next_source_sequence_rows_next205'], 'current_source_sequence_next205'))), [null]],
    'visible payload names released' => [static fn (): mixed => array_column($released205()['visible_returning_payloads_next205'], 'name'), ['blogdescription_child', 'template_child', 'home', 'next_plugin']],
    'held payload names missing' => [static fn (): mixed => array_column($missing205()['held_next_returning_payloads_next205'], 'name'), ['home', 'next_plugin']],
    'blocked reasons missing' => [static fn (): mixed => $missing205()['blocked_reasons_next205'], ['current-source-sequence-missing', 'current-source-sequence-order-mismatch']],
    'blocked reasons unexpected' => [static fn (): mixed => $unexpected205()['blocked_reasons_next205'], ['current-source-sequence-unexpected', 'current-source-sequence-order-mismatch']],
    'blocked reasons reordered' => [static fn (): mixed => $reordered205()['blocked_reasons_next205'], ['current-source-sequence-order-mismatch']],
    'blocked reasons current token' => [static fn (): mixed => $currentTokenHeld205()['blocked_reasons_next205'], ['current-source-sequence-token-mismatch']],
    'blocked reasons next token' => [static fn (): mixed => $nextTokenHeld205()['blocked_reasons_next205'], ['next-source-sequence-token-mismatch']],
    'blocked reasons base' => [static fn (): mixed => $baseHeld205()['blocked_reasons_next205'], ['recursive-child-returning-rows-not-acknowledged']],
    'released reasons empty' => [static fn (): mixed => $released205()['blocked_reasons_next205'], []],
    'held row reasons copied' => [static fn (): mixed => $missing205()['held_next_source_rows_next205'][0]['held_by_source_sequence_reasons_next205'], ['current-source-sequence-missing', 'current-source-sequence-order-mismatch']],
    'plan decision released' => [static fn (): mixed => $released205()['source_sequence_plan_next205']['decision'], 'publish-next-source-after-current-source-sequence'],
    'plan decision missing' => [static fn (): mixed => $missing205()['source_sequence_plan_next205']['decision'], 'hold-next-source-until-current-source-sequence'],
    'plan base visible' => [static fn (): mixed => $released205()['source_sequence_plan_next205']['base_next_source_visible'], true],
    'plan base held' => [static fn (): mixed => $baseHeld205()['source_sequence_plan_next205']['base_next_source_visible'], false],
    'plan current token matches' => [static fn (): mixed => $released205()['source_sequence_plan_next205']['current_source_token_matches'], true],
    'plan next token matches' => [static fn (): mixed => $released205()['source_sequence_plan_next205']['next_source_token_matches'], true],
    'plan required echoed' => [static fn (): mixed => $released205()['source_sequence_plan_next205']['required_sequence'], $sequence205()],
    'plan acknowledged echoed' => [static fn (): mixed => $missing205()['source_sequence_plan_next205']['acknowledged_sequence'], array_slice($sequence205(), 0, 1)],
    'plan next visible echoed' => [static fn (): mixed => $released205()['source_sequence_plan_next205']['next_source_visible'], true],
    'yield boundary released' => [static fn (): mixed => $released205()['yield_boundary_next205'], 'recursive-view-returning-next205-current-source-sequence-then-next'],
    'yield boundary held' => [static fn (): mixed => $missing205()['yield_boundary_next205'], 'recursive-view-returning-next205-current-source-sequence-fences-next'],
    'dependency closure marker' => [static fn (): mixed => $released205()['dependency_closure_next205'], 'no new support component needed; reuses native recursive view RETURNING current-source sequence fencing'],
    'dependency includes next205' => [static fn (): mixed => in_array('sqlite-trigger-recursive-view-returning-current-source-next205', $released205()['dependencies_next205'], true), true],
    'dependency includes sequence fence' => [static fn (): mixed => in_array('sqlite-returning-current-source-sequence-fence', $released205()['dependencies_next205'], true), true],
    'dependency includes next203' => [static fn (): mixed => in_array('sqlite-trigger-recursive-view-returning-current-source-next203', $released205()['dependencies_next205'], true), true],
    'dependency includes wordpress' => [static fn (): mixed => in_array('wordpress-recursive-view-returning-current-source-next205', $released205()['dependencies_next205'], true), true],
    'non overlap mentions next203' => [static fn (): mixed => str_contains($released205()['non_overlap_next205'], 'next203 generation'), true],
    'bad current source token rejected' => [static fn (): mixed => $plan205(['current_source_sequence_token_next205' => 'bad token']), InvalidArgumentException::class],
    'bad expected current source token rejected' => [static fn (): mixed => $plan205(['expected_current_source_sequence_token_next205' => 'bad token']), InvalidArgumentException::class],
    'bad next source token rejected' => [static fn (): mixed => $plan205(['next_source_sequence_token_next205' => 'bad token']), InvalidArgumentException::class],
    'bad expected next source token rejected' => [static fn (): mixed => $plan205(['expected_next_source_sequence_token_next205' => 'bad token']), InvalidArgumentException::class],
    'bad cursor rejected' => [static fn (): mixed => $plan205(['source_sequence_cursor_next205' => 'bad cursor']), InvalidArgumentException::class],
    'bad sequence list rejected' => [static fn (): mixed => $plan205(['acknowledged_current_source_sequence_next205' => ['x' => 'abcdefabcdefabcdefabcdefabcdefab']]), InvalidArgumentException::class],
    'bad short sequence rejected' => [static fn (): mixed => $plan205(['acknowledged_current_source_sequence_next205' => ['abc']]), InvalidArgumentException::class],
    'bad non hex sequence rejected' => [static fn (): mixed => $plan205(['acknowledged_current_source_sequence_next205' => ['zzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzz']]), InvalidArgumentException::class],
];

$tests = [];
foreach ($cases205 as $name => [$callback, $expected]) {
    $tests['trigger recursive view returning current source next205 ' . $name] = static function (TestRunner $t) use ($callback, $expected): void {
        if (is_string($expected) && is_a($expected, Throwable::class, true)) {
            $t->throws($expected, $callback);
            return;
        }
        $t->same($expected, $callback());
    };
}

return $tests;
