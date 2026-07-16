<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteTriggerRecursiveViewReturningCurrentSourceNextPlan;

$rowsSourceSequence = [
    ['setting_id' => 1, 'key_name' => 'base_url', 'key_value' => 'https://old.test', 'load_policy' => 'yes'],
    ['setting_id' => 2, 'key_name' => 'landing_url', 'key_value' => 'https://landing_url.test', 'load_policy' => 'yes'],
];
$currentViewSourceSequence = [
    'name' => 'app_recursive_setting_import',
    'source' => 'main@view-cookie-source-sequence-current',
    'trigger' => 'app_recursive_setting_import_io_insert',
    'trigger_source' => 'main@trigger-cookie-source-sequence-current',
    'columns' => ['import_id', 'name', 'value', 'load_policy_flag', 'spawn_child'],
    'mapping' => ['import_id' => 'setting_id', 'name' => 'key_name', 'value' => 'key_value', 'load_policy_flag' => 'load_policy', 'spawn_child' => 'spawn_child'],
    'audit_label' => 'current-recursive-view-trigger-source-sequence',
];
$nextViewSourceSequence = $currentViewSourceSequence;
$nextViewSourceSequence['source'] = 'main@view-cookie-source-sequence-next';
$nextViewSourceSequence['trigger_source'] = 'main@trigger-cookie-source-sequence-next';
$postResetViewSourceSequence = $currentViewSourceSequence;
$postResetViewSourceSequence['source'] = 'main@view-cookie-source-sequence-post-reset';
$postResetViewSourceSequence['trigger_source'] = 'main@trigger-cookie-source-sequence-post-reset';
$followingViewSourceSequence = $currentViewSourceSequence;
$followingViewSourceSequence['source'] = 'main@view-cookie-source-sequence-following';
$followingViewSourceSequence['trigger_source'] = 'main@trigger-cookie-source-sequence-following';
$currentInputSourceSequence = [
    ['import_id' => 10, 'name' => 'base_url', 'value' => 'https://current.test', 'load_policy_flag' => 'yes', 'spawn_child' => true],
    ['import_id' => 11, 'name' => 'current_module', 'value' => 'enabled', 'load_policy_flag' => 'no', 'spawn_child' => true],
];
$nextInputSourceSequence = [
    ['import_id' => 20, 'name' => 'landing_url', 'value' => 'https://next.test', 'load_policy_flag' => 'yes', 'spawn_child' => true],
    ['import_id' => 21, 'name' => 'next_module', 'value' => 'active', 'load_policy_flag' => 'no', 'spawn_child' => false],
];
$postResetInputSourceSequence = [
    ['import_id' => 30, 'name' => 'base_url', 'value' => 'https://fresh.test', 'load_policy_flag' => 'yes', 'spawn_child' => false],
    ['import_id' => 31, 'name' => 'routing_rules', 'value' => 'fresh-rules', 'load_policy_flag' => 'no', 'spawn_child' => false],
];
$followingInputSourceSequence = [
    ['import_id' => 40, 'name' => 'app_summary', 'value' => 'after-next', 'load_policy_flag' => 'yes', 'spawn_child' => true],
    ['import_id' => 41, 'name' => 'theme_style_key', 'value' => 'modern_theme', 'load_policy_flag' => 'yes', 'spawn_child' => false],
    ['import_id' => 42, 'name' => 'template', 'value' => 'modern_theme', 'load_policy_flag' => 'yes', 'spawn_child' => true],
];
$returningSourceSequence = [
    ['expr' => 'new.key_name', 'as' => 'name'],
    ['expr' => 'new.key_value', 'as' => 'value'],
    ['expr' => 'old.key_value', 'as' => 'old_value'],
    ['expr' => 'event', 'as' => 'event_name'],
    ['expr' => 'depth', 'as' => 'depth_value'],
    ['expr' => 'ordinal', 'as' => 'ordinal_value'],
    ['expr' => 'trigger_source', 'as' => 'trigger_source_alias'],
    ['expr' => 'spawn_child', 'as' => 'spawn_child'],
];

$planSourceSequence = static fn (array $options = []): array => SQLiteTriggerRecursiveViewReturningCurrentSourceNextPlan::executeSourceSequenceFence(
    $rowsSourceSequence,
    $currentInputSourceSequence,
    $nextInputSourceSequence,
    $currentViewSourceSequence,
    $nextViewSourceSequence,
    $returningSourceSequence,
    $options + [
        'key' => 'key_name',
        'savepoint' => 'app_recursive_view_source_sequence',
        'cursor_name' => 'app_recursive_view_returning_cursor_source_sequence',
        'current_generation' => 'app-current-returning-source-sequence',
        'next_generation' => 'app-next-returning-source-sequence',
        'page_size' => 3,
        'admit_next_source' => true,
        'current_source_token' => 'app.current.source.source_sequence',
        'drain_ack_token' => 'app.returning.drain.source_sequence',
        'rollback_token' => 'app.rollback.current.source_sequence',
        'reset_generation' => 'app-current-reset-source-sequence',
        'post_reset_current_source_token' => 'app.current.source.postreset.source_sequence',
        'post_reset_cursor' => 'app.returning.postreset.cursor.source_sequence',
        'post_reset_view' => $postResetViewSourceSequence,
        'post_reset_input' => $postResetInputSourceSequence,
        'fresh_acknowledged_ordinals' => [0, 1],
        'next_source_token' => 'app.next.source.source_sequence',
        'next_cursor' => 'app.returning.next.cursor.source_sequence',
        'next_acknowledged_ordinals' => [0, 1],
        'close_next_cursor' => 'app.returning.next.cursor.source_sequence',
        'following_current_source_token' => 'app.current.source.following.source_sequence',
        'following_cursor' => 'app.returning.following.cursor.source_sequence',
        'following_current_view' => $followingViewSourceSequence,
        'following_current_input' => $followingInputSourceSequence,
        'following_generation' => 'app-following-current-source-sequence',
        'recursive_child_acknowledged_ordinals' => [0, 1],
        'recursive_child_source_token' => 'app.current.source.recursive.child.source_sequence',
        'recursive_child_cursor' => 'app.returning.recursive.child.cursor.source_sequence',
        'recursive_child_generation' => 'app-recursive-child-current-source-sequence',
        'current_generation_next203' => 'app.current.recursive.returning.generation.source_sequence',
        'expected_current_generation_next203' => 'app.current.recursive.returning.generation.source_sequence',
        'current_handoff_cursor_next203' => 'app.returning.current.handoff.cursor.source_sequence',
        'current_generation_commit_marker_next203' => 'app.current.recursive.returning.commit.source_sequence',
        'auto_ack_current_generation_receipts_next203' => true,
        'current_source_sequence_fence_token_source_sequence_fence' => 'app.current.returning.source.sequence.source_sequence',
        'expected_current_source_sequence_fence_token_source_sequence_fence' => 'app.current.returning.source.sequence.source_sequence',
        'next_source_sequence_fence_token_source_sequence_fence' => 'app.next.returning.source.sequence.source_sequence',
        'expected_next_source_sequence_fence_token_source_sequence_fence' => 'app.next.returning.source.sequence.source_sequence',
        'source_sequence_cursor_source_sequence_fence' => 'app.returning.source.sequence.cursor.source_sequence',
    ],
);

$sequenceSourceSequence = static fn (): array => $planSourceSequence()['required_current_source_sequence_fence_source_sequence_fence'];
$releasedSourceSequence = static fn (): array => $planSourceSequence(['auto_ack_current_source_sequence_fence_source_sequence_fence' => true]);
$missingSourceSequence = static fn (): array => $planSourceSequence(['acknowledged_current_source_sequence_fence_source_sequence_fence' => array_slice($sequenceSourceSequence(), 0, 1)]);
$unexpectedSourceSequence = static fn (): array => $planSourceSequence(['acknowledged_current_source_sequence_fence_source_sequence_fence' => array_merge($sequenceSourceSequence(), ['abcdefabcdefabcdefabcdefabcdefab'])]);
$reorderedSourceSequence = static fn (): array => $planSourceSequence(['acknowledged_current_source_sequence_fence_source_sequence_fence' => array_reverse($sequenceSourceSequence())]);
$reorderedAllowedSourceSequence = static fn (): array => $planSourceSequence(['acknowledged_current_source_sequence_fence_source_sequence_fence' => array_reverse($sequenceSourceSequence()), 'require_source_sequence_fence_order_source_sequence_fence' => false]);
$currentTokenHeldSourceSequence = static fn (): array => $planSourceSequence(['auto_ack_current_source_sequence_fence_source_sequence_fence' => true, 'expected_current_source_sequence_fence_token_source_sequence_fence' => 'app.current.returning.source.sequence.stale.source_sequence']);
$nextTokenHeldSourceSequence = static fn (): array => $planSourceSequence(['auto_ack_current_source_sequence_fence_source_sequence_fence' => true, 'expected_next_source_sequence_fence_token_source_sequence_fence' => 'app.next.returning.source.sequence.stale.source_sequence']);
$baseHeldSourceSequence = static fn (): array => $planSourceSequence(['auto_ack_current_source_sequence_fence_source_sequence_fence' => true, 'recursive_child_acknowledged_ordinals' => [0]]);
$customSourceSequence = static fn (): array => $planSourceSequence([
    'auto_ack_current_source_sequence_fence_source_sequence_fence' => true,
    'current_source_sequence_fence_token_source_sequence_fence' => 'app.current.returning.source.sequence.custom.source_sequence',
    'expected_current_source_sequence_fence_token_source_sequence_fence' => 'app.current.returning.source.sequence.custom.source_sequence',
    'next_source_sequence_fence_token_source_sequence_fence' => 'app.next.returning.source.sequence.custom.source_sequence',
    'expected_next_source_sequence_fence_token_source_sequence_fence' => 'app.next.returning.source.sequence.custom.source_sequence',
    'source_sequence_cursor_source_sequence_fence' => 'app.returning.source.sequence.cursor.custom.source_sequence',
]);

$casesSourceSequence = [
    'released status' => [static fn (): mixed => $releasedSourceSequence()['status_source_sequence_fence'], 'trigger-recursive-view-returning-current-source-source-sequence-source-sequence-released'],
    'missing status' => [static fn (): mixed => $missingSourceSequence()['status_source_sequence_fence'], 'trigger-recursive-view-returning-current-source-source-sequence-sequence-held'],
    'unexpected status' => [static fn (): mixed => $unexpectedSourceSequence()['status_source_sequence_fence'], 'trigger-recursive-view-returning-current-source-source-sequence-sequence-held'],
    'reordered status' => [static fn (): mixed => $reorderedSourceSequence()['status_source_sequence_fence'], 'trigger-recursive-view-returning-current-source-source-sequence-sequence-held'],
    'reordered allowed status' => [static fn (): mixed => $reorderedAllowedSourceSequence()['status_source_sequence_fence'], 'trigger-recursive-view-returning-current-source-source-sequence-source-sequence-released'],
    'current token held status' => [static fn (): mixed => $currentTokenHeldSourceSequence()['status_source_sequence_fence'], 'trigger-recursive-view-returning-current-source-source-sequence-current-source-held'],
    'next token held status' => [static fn (): mixed => $nextTokenHeldSourceSequence()['status_source_sequence_fence'], 'trigger-recursive-view-returning-current-source-source-sequence-next-source-held'],
    'base held status' => [static fn (): mixed => $baseHeldSourceSequence()['status_source_sequence_fence'], 'trigger-recursive-view-returning-current-source-source-sequence-base-held'],
    'base next203 released' => [static fn (): mixed => $releasedSourceSequence()['base']['status_next203'], 'trigger-recursive-view-returning-current-source-next203-generation-released'],
    'base held keeps next203 held' => [static fn (): mixed => $baseHeldSourceSequence()['base']['status_next203'], 'trigger-recursive-view-returning-current-source-next203-base-held'],
    'savepoint retained' => [static fn (): mixed => $releasedSourceSequence()['savepoint'], 'app_recursive_view_source_sequence'],
    'base visible released' => [static fn (): mixed => $releasedSourceSequence()['base_next_source_visible_source_sequence_fence'], true],
    'base visible denied' => [static fn (): mixed => $baseHeldSourceSequence()['base_next_source_visible_source_sequence_fence'], false],
    'current source token retained' => [static fn (): mixed => $releasedSourceSequence()['current_source_sequence_fence_token_source_sequence_fence'], 'app.current.returning.source.sequence.source_sequence'],
    'expected current source token retained' => [static fn (): mixed => $releasedSourceSequence()['expected_current_source_sequence_fence_token_source_sequence_fence'], 'app.current.returning.source.sequence.source_sequence'],
    'current source token matches' => [static fn (): mixed => $releasedSourceSequence()['current_source_sequence_fence_token_matches_source_sequence_fence'], true],
    'current source token mismatch' => [static fn (): mixed => $currentTokenHeldSourceSequence()['current_source_sequence_fence_token_matches_source_sequence_fence'], false],
    'next source token retained' => [static fn (): mixed => $releasedSourceSequence()['next_source_sequence_fence_token_source_sequence_fence'], 'app.next.returning.source.sequence.source_sequence'],
    'expected next source token retained' => [static fn (): mixed => $releasedSourceSequence()['expected_next_source_sequence_fence_token_source_sequence_fence'], 'app.next.returning.source.sequence.source_sequence'],
    'next source token matches' => [static fn (): mixed => $releasedSourceSequence()['next_source_sequence_fence_token_matches_source_sequence_fence'], true],
    'next source token mismatch' => [static fn (): mixed => $nextTokenHeldSourceSequence()['next_source_sequence_fence_token_matches_source_sequence_fence'], false],
    'cursor retained' => [static fn (): mixed => $releasedSourceSequence()['source_sequence_cursor_source_sequence_fence'], 'app.returning.source.sequence.cursor.source_sequence'],
    'custom current token retained' => [static fn (): mixed => $customSourceSequence()['current_source_sequence_fence_token_source_sequence_fence'], 'app.current.returning.source.sequence.custom.source_sequence'],
    'custom next token retained' => [static fn (): mixed => $customSourceSequence()['next_source_sequence_fence_token_source_sequence_fence'], 'app.next.returning.source.sequence.custom.source_sequence'],
    'custom cursor retained' => [static fn (): mixed => $customSourceSequence()['source_sequence_cursor_source_sequence_fence'], 'app.returning.source.sequence.cursor.custom.source_sequence'],
    'required sequence count' => [static fn (): mixed => count($releasedSourceSequence()['required_current_source_sequence_fence_source_sequence_fence']), 2],
    'required sequence is 32 hex' => [static fn (): mixed => array_map(static fn (string $v): int => preg_match('/^[a-f0-9]{32}$/', $v), $releasedSourceSequence()['required_current_source_sequence_fence_source_sequence_fence']), [1, 1]],
    'auto acknowledged equals required' => [static fn (): mixed => $releasedSourceSequence()['acknowledged_current_source_sequence_fence_source_sequence_fence'], $sequenceSourceSequence()],
    'missing acknowledged count' => [static fn (): mixed => count($missingSourceSequence()['acknowledged_current_source_sequence_fence_source_sequence_fence']), 1],
    'missing sequence recorded' => [static fn (): mixed => $missingSourceSequence()['missing_current_source_sequence_fence_source_sequence_fence'], [array_slice($sequenceSourceSequence(), -1)[0]]],
    'unexpected sequence recorded' => [static fn (): mixed => $unexpectedSourceSequence()['unexpected_current_source_sequence_fence_source_sequence_fence'], ['abcdefabcdefabcdefabcdefabcdefab']],
    'released missing empty' => [static fn (): mixed => $releasedSourceSequence()['missing_current_source_sequence_fence_source_sequence_fence'], []],
    'released unexpected empty' => [static fn (): mixed => $releasedSourceSequence()['unexpected_current_source_sequence_fence_source_sequence_fence'], []],
    'order required default' => [static fn (): mixed => $releasedSourceSequence()['require_source_sequence_fence_order_source_sequence_fence'], true],
    'order matches released' => [static fn (): mixed => $releasedSourceSequence()['current_source_sequence_fence_order_matches_source_sequence_fence'], true],
    'order mismatch detected' => [static fn (): mixed => $reorderedSourceSequence()['current_source_sequence_fence_order_matches_source_sequence_fence'], false],
    'order disabled flag' => [static fn (): mixed => $reorderedAllowedSourceSequence()['require_source_sequence_fence_order_source_sequence_fence'], false],
    'fence released' => [static fn (): mixed => $releasedSourceSequence()['current_source_sequence_fence_fence_clear_source_sequence_fence'], true],
    'fence missing blocked' => [static fn (): mixed => $missingSourceSequence()['current_source_sequence_fence_fence_clear_source_sequence_fence'], false],
    'fence reordered blocked' => [static fn (): mixed => $reorderedSourceSequence()['current_source_sequence_fence_fence_clear_source_sequence_fence'], false],
    'fence reordered allowed' => [static fn (): mixed => $reorderedAllowedSourceSequence()['current_source_sequence_fence_fence_clear_source_sequence_fence'], true],
    'next visible released' => [static fn (): mixed => $releasedSourceSequence()['next_source_visible_after_source_sequence_fence_source_sequence_fence'], true],
    'next denied missing' => [static fn (): mixed => $missingSourceSequence()['next_source_visible_after_source_sequence_fence_source_sequence_fence'], false],
    'next denied current token' => [static fn (): mixed => $currentTokenHeldSourceSequence()['next_source_visible_after_source_sequence_fence_source_sequence_fence'], false],
    'next denied next token' => [static fn (): mixed => $nextTokenHeldSourceSequence()['next_source_visible_after_source_sequence_fence_source_sequence_fence'], false],
    'current row count' => [static fn (): mixed => $releasedSourceSequence()['current_source_sequence_fence_row_count_source_sequence_fence'], 2],
    'attempted next row count' => [static fn (): mixed => $releasedSourceSequence()['attempted_next_source_sequence_fence_row_count_source_sequence_fence'], 2],
    'visible released count' => [static fn (): mixed => $releasedSourceSequence()['visible_row_count_source_sequence_fence'], 4],
    'held released count' => [static fn (): mixed => $releasedSourceSequence()['held_next_row_count_source_sequence_fence'], 0],
    'visible missing current only' => [static fn (): mixed => $missingSourceSequence()['visible_row_count_source_sequence_fence'], 2],
    'held missing next only' => [static fn (): mixed => $missingSourceSequence()['held_next_row_count_source_sequence_fence'], 2],
    'current phase' => [static fn (): mixed => array_values(array_unique(array_column($releasedSourceSequence()['current_source_sequence_fence_rows_source_sequence_fence'], 'source_sequence_phase_source_sequence_fence'))), ['current']],
    'next phase' => [static fn (): mixed => array_values(array_unique(array_column($releasedSourceSequence()['attempted_next_source_sequence_fence_rows_source_sequence_fence'], 'source_sequence_phase_source_sequence_fence'))), ['next']],
    'current rows visible while missing' => [static fn (): mixed => array_values(array_unique(array_column($missingSourceSequence()['current_source_sequence_fence_rows_source_sequence_fence'], 'visible_after_source_sequence_fence_source_sequence_fence'))), [true]],
    'next rows visible released' => [static fn (): mixed => array_values(array_unique(array_column($releasedSourceSequence()['attempted_next_source_sequence_fence_rows_source_sequence_fence'], 'visible_after_source_sequence_fence_source_sequence_fence'))), [true]],
    'next rows held missing' => [static fn (): mixed => array_values(array_unique(array_column($missingSourceSequence()['attempted_next_source_sequence_fence_rows_source_sequence_fence'], 'visible_after_source_sequence_fence_source_sequence_fence'))), [false]],
    'current sequences tagged' => [static fn (): mixed => array_column($releasedSourceSequence()['current_source_sequence_fence_rows_source_sequence_fence'], 'current_source_sequence_fence_source_sequence_fence'), $sequenceSourceSequence()],
    'next sequences null' => [static fn (): mixed => array_values(array_unique(array_column($releasedSourceSequence()['attempted_next_source_sequence_fence_rows_source_sequence_fence'], 'current_source_sequence_fence_source_sequence_fence'))), [null]],
    'visible payload names released' => [static fn (): mixed => array_column($releasedSourceSequence()['visible_returning_payloads_source_sequence_fence'], 'name'), ['app_summary_child', 'template_child', 'landing_url', 'next_module']],
    'held payload names missing' => [static fn (): mixed => array_column($missingSourceSequence()['held_next_returning_payloads_source_sequence_fence'], 'name'), ['landing_url', 'next_module']],
    'blocked reasons missing' => [static fn (): mixed => $missingSourceSequence()['blocked_reasons_source_sequence_fence'], ['current-source-sequence-missing', 'current-source-sequence-order-mismatch']],
    'blocked reasons unexpected' => [static fn (): mixed => $unexpectedSourceSequence()['blocked_reasons_source_sequence_fence'], ['current-source-sequence-unexpected', 'current-source-sequence-order-mismatch']],
    'blocked reasons reordered' => [static fn (): mixed => $reorderedSourceSequence()['blocked_reasons_source_sequence_fence'], ['current-source-sequence-order-mismatch']],
    'blocked reasons current token' => [static fn (): mixed => $currentTokenHeldSourceSequence()['blocked_reasons_source_sequence_fence'], ['current-source-sequence-token-mismatch']],
    'blocked reasons next token' => [static fn (): mixed => $nextTokenHeldSourceSequence()['blocked_reasons_source_sequence_fence'], ['next-source-sequence-token-mismatch']],
    'blocked reasons base' => [static fn (): mixed => $baseHeldSourceSequence()['blocked_reasons_source_sequence_fence'], ['recursive-child-returning-rows-not-acknowledged']],
    'released reasons empty' => [static fn (): mixed => $releasedSourceSequence()['blocked_reasons_source_sequence_fence'], []],
    'held row reasons copied' => [static fn (): mixed => $missingSourceSequence()['held_next_source_rows_source_sequence_fence'][0]['held_by_source_sequence_fence_reasons_source_sequence_fence'], ['current-source-sequence-missing', 'current-source-sequence-order-mismatch']],
    'plan decision released' => [static fn (): mixed => $releasedSourceSequence()['source_sequence_plan_source_sequence_fence']['decision'], 'publish-next-source-after-current-source-sequence'],
    'plan decision missing' => [static fn (): mixed => $missingSourceSequence()['source_sequence_plan_source_sequence_fence']['decision'], 'hold-next-source-until-current-source-sequence'],
    'plan base visible' => [static fn (): mixed => $releasedSourceSequence()['source_sequence_plan_source_sequence_fence']['base_next_source_visible'], true],
    'plan base held' => [static fn (): mixed => $baseHeldSourceSequence()['source_sequence_plan_source_sequence_fence']['base_next_source_visible'], false],
    'plan current token matches' => [static fn (): mixed => $releasedSourceSequence()['source_sequence_plan_source_sequence_fence']['current_source_token_matches'], true],
    'plan next token matches' => [static fn (): mixed => $releasedSourceSequence()['source_sequence_plan_source_sequence_fence']['next_source_token_matches'], true],
    'plan required echoed' => [static fn (): mixed => $releasedSourceSequence()['source_sequence_plan_source_sequence_fence']['required_sequence'], $sequenceSourceSequence()],
    'plan acknowledged echoed' => [static fn (): mixed => $missingSourceSequence()['source_sequence_plan_source_sequence_fence']['acknowledged_sequence'], array_slice($sequenceSourceSequence(), 0, 1)],
    'plan next visible echoed' => [static fn (): mixed => $releasedSourceSequence()['source_sequence_plan_source_sequence_fence']['next_source_visible'], true],
    'yield boundary released' => [static fn (): mixed => $releasedSourceSequence()['yield_boundary_source_sequence_fence'], 'recursive-view-returning-source-sequence-current-source-sequence-then-next'],
    'yield boundary held' => [static fn (): mixed => $missingSourceSequence()['yield_boundary_source_sequence_fence'], 'recursive-view-returning-source-sequence-current-source-sequence-fences-next'],
    'dependency closure marker' => [static fn (): mixed => $releasedSourceSequence()['dependency_closure_source_sequence_fence'], 'no new support component needed; reuses native recursive view RETURNING current-source-sequence fencing'],
    'dependency includes source-sequence' => [static fn (): mixed => in_array('sqlite-trigger-recursive-view-returning-current-source-source-sequence', $releasedSourceSequence()['dependencies_source_sequence_fence'], true), true],
    'dependency includes sequence fence' => [static fn (): mixed => in_array('sqlite-returning-current-source-sequence-fence', $releasedSourceSequence()['dependencies_source_sequence_fence'], true), true],
    'dependency includes next203' => [static fn (): mixed => in_array('sqlite-trigger-recursive-view-returning-current-source-next203', $releasedSourceSequence()['dependencies_source_sequence_fence'], true), true],
    'dependency includes application' => [static fn (): mixed => in_array('application-recursive-view-returning-current-source-source-sequence', $releasedSourceSequence()['dependencies_source_sequence_fence'], true), true],
    'non overlap mentions next203' => [static fn (): mixed => str_contains($releasedSourceSequence()['non_overlap_source_sequence_fence'], 'next203 generation'), true],
    'bad current source token rejected' => [static fn (): mixed => $planSourceSequence(['current_source_sequence_fence_token_source_sequence_fence' => 'bad token']), InvalidArgumentException::class],
    'bad expected current source token rejected' => [static fn (): mixed => $planSourceSequence(['expected_current_source_sequence_fence_token_source_sequence_fence' => 'bad token']), InvalidArgumentException::class],
    'bad next source token rejected' => [static fn (): mixed => $planSourceSequence(['next_source_sequence_fence_token_source_sequence_fence' => 'bad token']), InvalidArgumentException::class],
    'bad expected next source token rejected' => [static fn (): mixed => $planSourceSequence(['expected_next_source_sequence_fence_token_source_sequence_fence' => 'bad token']), InvalidArgumentException::class],
    'bad cursor rejected' => [static fn (): mixed => $planSourceSequence(['source_sequence_cursor_source_sequence_fence' => 'bad cursor']), InvalidArgumentException::class],
    'bad sequence list rejected' => [static fn (): mixed => $planSourceSequence(['acknowledged_current_source_sequence_fence_source_sequence_fence' => ['x' => 'abcdefabcdefabcdefabcdefabcdefab']]), InvalidArgumentException::class],
    'bad short sequence rejected' => [static fn (): mixed => $planSourceSequence(['acknowledged_current_source_sequence_fence_source_sequence_fence' => ['abc']]), InvalidArgumentException::class],
    'bad non hex sequence rejected' => [static fn (): mixed => $planSourceSequence(['acknowledged_current_source_sequence_fence_source_sequence_fence' => ['zzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzz']]), InvalidArgumentException::class],
];

$tests = [];
foreach ($casesSourceSequence as $name => [$callback, $expected]) {
    $tests['trigger recursive view returning current source source-sequence ' . $name] = static function (TestRunner $t) use ($callback, $expected): void {
        if (is_string($expected) && is_a($expected, Throwable::class, true)) {
            $t->throws($expected, $callback);
            return;
        }
        $t->same($expected, $callback());
    };
}

return $tests;
