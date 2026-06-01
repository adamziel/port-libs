<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteTriggerRecursiveViewReturningCurrentSourceNextPlan;

$rows192 = [
    ['setting_id' => 1, 'key_name' => 'base_url', 'key_value' => 'https://old.test', 'load_policy' => 'yes'],
    ['setting_id' => 2, 'key_name' => 'landing_url', 'key_value' => 'https://landing_url.test', 'load_policy' => 'yes'],
];
$currentView192 = [
    'name' => 'app_recursive_setting_import',
    'source' => 'main@view-cookie-192-current',
    'trigger' => 'app_recursive_setting_import_io_insert',
    'trigger_source' => 'main@trigger-cookie-192-current',
    'columns' => ['import_id', 'name', 'value', 'load_policy_flag', 'spawn_child'],
    'mapping' => ['import_id' => 'setting_id', 'name' => 'key_name', 'value' => 'key_value', 'load_policy_flag' => 'load_policy'],
    'audit_label' => 'current-recursive-view-trigger-192',
];
$nextView192 = $currentView192;
$nextView192['source'] = 'main@view-cookie-192-next';
$nextView192['trigger_source'] = 'main@trigger-cookie-192-next';
$nextView192['audit_label'] = 'next-recursive-view-trigger-192';
$postResetView192 = $currentView192;
$postResetView192['source'] = 'main@view-cookie-192-post-reset';
$postResetView192['trigger_source'] = 'main@trigger-cookie-192-post-reset';
$postResetView192['audit_label'] = 'post-reset-recursive-view-trigger-192';
$followingView192 = $currentView192;
$followingView192['source'] = 'main@view-cookie-192-following';
$followingView192['trigger_source'] = 'main@trigger-cookie-192-following';
$followingView192['audit_label'] = 'following-recursive-view-trigger-192';
$currentInput192 = [
    ['import_id' => 10, 'name' => 'base_url', 'value' => 'https://current.test', 'load_policy_flag' => 'yes', 'spawn_child' => true],
    ['import_id' => 11, 'name' => 'current_module', 'value' => 'enabled', 'load_policy_flag' => 'no', 'spawn_child' => true],
];
$nextInput192 = [
    ['import_id' => 20, 'name' => 'landing_url', 'value' => 'https://next.test', 'load_policy_flag' => 'yes', 'spawn_child' => true],
    ['import_id' => 21, 'name' => 'next_module', 'value' => 'active', 'load_policy_flag' => 'no', 'spawn_child' => false],
];
$postResetInput192 = [
    ['import_id' => 30, 'name' => 'base_url', 'value' => 'https://fresh.test', 'load_policy_flag' => 'yes', 'spawn_child' => false],
    ['import_id' => 31, 'name' => 'routing_rules', 'value' => 'fresh-rules', 'load_policy_flag' => 'no', 'spawn_child' => false],
];
$followingInput192 = [
    ['import_id' => 40, 'name' => 'app_summary', 'value' => 'after-next', 'load_policy_flag' => 'yes', 'spawn_child' => false],
    ['import_id' => 41, 'name' => 'theme_style_key', 'value' => 'modern_theme', 'load_policy_flag' => 'yes', 'spawn_child' => false],
];
$returning192 = [
    ['expr' => 'new.key_name', 'as' => 'name'],
    ['expr' => 'new.key_value', 'as' => 'value'],
    ['expr' => 'old.key_value', 'as' => 'old_value'],
    ['expr' => 'event', 'as' => 'event_name'],
    ['expr' => 'ordinal', 'as' => 'ordinal_value'],
    ['expr' => 'trigger_source', 'as' => 'trigger_source_alias'],
];

$plan192 = static fn (array $options = []): array => SQLiteTriggerRecursiveViewReturningCurrentSourceNextPlan::executeFollowingCurrentAfterNextCursorClose(
    $rows192,
    $currentInput192,
    $nextInput192,
    $currentView192,
    $nextView192,
    $returning192,
    $options + [
        'key' => 'key_name',
        'savepoint' => 'app_recursive_view_192',
        'cursor_name' => 'app_recursive_view_returning_cursor_192',
        'current_generation' => 'app-current-returning-192',
        'next_generation' => 'app-next-returning-192',
        'page_size' => 3,
        'admit_next_source' => true,
        'current_source_token' => 'app.current.source.192',
        'drain_ack_token' => 'app.returning.drain.192',
        'rollback_token' => 'app.rollback.current.192',
        'reset_generation' => 'app-current-reset-192',
        'post_reset_current_source_token' => 'app.current.source.postreset.192',
        'post_reset_cursor' => 'app.returning.postreset.cursor.192',
        'post_reset_view' => $postResetView192,
        'post_reset_input' => $postResetInput192,
        'fresh_acknowledged_ordinals' => [0, 1],
        'next_source_token' => 'app.next.source.192',
        'next_cursor' => 'app.returning.next.cursor.192',
        'next_acknowledged_ordinals' => [0, 1],
        'close_next_cursor' => 'app.returning.next.cursor.192',
        'following_current_source_token' => 'app.current.source.following.192',
        'following_cursor' => 'app.returning.following.cursor.192',
        'following_current_view' => $followingView192,
        'following_current_input' => $followingInput192,
        'following_generation' => 'app-following-current-192',
    ],
);

$admitted192 = static fn (): array => $plan192();
$partial192 = static fn (): array => $plan192(['next_acknowledged_ordinals' => [0]]);
$none192 = static fn (): array => $plan192(['next_acknowledged_ordinals' => []]);
$cursorHeld192 = static fn (): array => $plan192(['close_next_cursor' => 'app.returning.next.cursor.expected.192']);
$tokenHeld192 = static fn (): array => $plan192(['expected_following_current_source_token' => 'app.current.source.expected.192']);
$nextHeld192 = static fn (): array => $plan192(['fresh_acknowledged_ordinals' => [0]]);
$custom192 = static fn (): array => $plan192([
    'following_current_source_token' => 'app.current.source.following.custom.192',
    'expected_following_current_source_token' => 'app.current.source.following.custom.192',
    'following_cursor' => 'app.returning.following.custom.cursor.192',
]);

$cases192 = [
    'admitted status' => [static fn (): mixed => $admitted192()['status_next192'], 'trigger-recursive-view-returning-current-source-next192-following-current-visible'],
    'partial status' => [static fn (): mixed => $partial192()['status_next192'], 'trigger-recursive-view-returning-current-source-next192-awaiting-next-row-acks'],
    'none status' => [static fn (): mixed => $none192()['status_next192'], 'trigger-recursive-view-returning-current-source-next192-awaiting-next-row-acks'],
    'cursor held status' => [static fn (): mixed => $cursorHeld192()['status_next192'], 'trigger-recursive-view-returning-current-source-next192-next-cursor-held'],
    'token held status' => [static fn (): mixed => $tokenHeld192()['status_next192'], 'trigger-recursive-view-returning-current-source-next192-following-token-held'],
    'next held status' => [static fn (): mixed => $nextHeld192()['status_next192'], 'trigger-recursive-view-returning-current-source-next192-next-source-held'],
    'savepoint retained' => [static fn (): mixed => $admitted192()['savepoint'], 'app_recursive_view_192'],
    'base next189 admitted' => [static fn (): mixed => $admitted192()['base']['status_next189'], 'trigger-recursive-view-returning-current-source-next189-next-source-visible'],
    'required next ordinals' => [static fn (): mixed => $admitted192()['next_required_ordinals_next192'], [0, 1]],
    'acknowledged next ordinals' => [static fn (): mixed => $admitted192()['next_acknowledged_ordinals_next192'], [0, 1]],
    'duplicate next ordinals coalesced' => [static fn (): mixed => $plan192(['next_acknowledged_ordinals' => [0, 0, 1]])['next_acknowledged_ordinals_next192'], [0, 1]],
    'partial next ordinals retained' => [static fn (): mixed => $partial192()['next_acknowledged_ordinals_next192'], [0]],
    'next rows acknowledged' => [static fn (): mixed => $admitted192()['next_source_rows_acknowledged_next192'], true],
    'partial next rows not acknowledged' => [static fn (): mixed => $partial192()['next_source_rows_acknowledged_next192'], false],
    'next cursor retained' => [static fn (): mixed => $admitted192()['next_cursor_next192'], 'app.returning.next.cursor.192'],
    'close cursor retained' => [static fn (): mixed => $admitted192()['close_next_cursor_next192'], 'app.returning.next.cursor.192'],
    'next cursor close matches' => [static fn (): mixed => $admitted192()['next_cursor_close_matches_next192'], true],
    'next cursor close mismatch' => [static fn (): mixed => $cursorHeld192()['next_cursor_close_matches_next192'], false],
    'following token retained' => [static fn (): mixed => $admitted192()['following_current_source_token_next192'], 'app.current.source.following.192'],
    'expected following token retained' => [static fn (): mixed => $admitted192()['expected_following_current_source_token_next192'], 'app.current.source.following.192'],
    'following token matches' => [static fn (): mixed => $admitted192()['following_current_source_token_matches_next192'], true],
    'following token mismatch' => [static fn (): mixed => $tokenHeld192()['following_current_source_token_matches_next192'], false],
    'following cursor retained' => [static fn (): mixed => $admitted192()['following_cursor_next192'], 'app.returning.following.cursor.192'],
    'custom following cursor retained' => [static fn (): mixed => $custom192()['following_cursor_next192'], 'app.returning.following.custom.cursor.192'],
    'following current row count' => [static fn (): mixed => $admitted192()['following_current_row_count_next192'], 2],
    'partial has no following rows' => [static fn (): mixed => $partial192()['following_current_rows_next192'], []],
    'cursor held has no following rows' => [static fn (): mixed => $cursorHeld192()['following_current_rows_next192'], []],
    'token held has no following rows' => [static fn (): mixed => $tokenHeld192()['following_current_rows_next192'], []],
    'next held has no following rows' => [static fn (): mixed => $nextHeld192()['following_current_rows_next192'], []],
    'following payload names' => [static fn (): mixed => array_column($admitted192()['following_current_payloads_next192'], 'name'), ['app_summary', 'theme_style_key']],
    'following payload values' => [static fn (): mixed => array_column($admitted192()['following_current_payloads_next192'], 'value'), ['after-next', 'modern_theme']],
    'following old values null' => [static fn (): mixed => array_column($admitted192()['following_current_payloads_next192'], 'old_value'), [null, null]],
    'following events' => [static fn (): mixed => array_values(array_unique(array_column($admitted192()['following_current_payloads_next192'], 'event_name'))), ['following-current']],
    'following ordinals' => [static fn (): mixed => array_column($admitted192()['following_current_payloads_next192'], 'ordinal_value'), [0, 1]],
    'following trigger source' => [static fn (): mixed => array_values(array_unique(array_column($admitted192()['following_current_payloads_next192'], 'trigger_source_alias'))), ['main@trigger-cookie-192-following']],
    'following statement sources' => [static fn (): mixed => array_column($admitted192()['following_current_rows_next192'], 'statement_source'), ['following-current', 'following-current']],
    'following token stamped' => [static fn (): mixed => array_values(array_unique(array_column($admitted192()['following_current_rows_next192'], 'following_current_source_token_next192'))), ['app.current.source.following.192']],
    'following cursor stamped' => [static fn (): mixed => array_values(array_unique(array_column($admitted192()['following_current_rows_next192'], 'following_cursor_next192'))), ['app.returning.following.cursor.192']],
    'following generation stamped' => [static fn (): mixed => array_values(array_unique(array_column($admitted192()['following_current_rows_next192'], 'following_generation_next192'))), ['app-following-current-192']],
    'following setting keys stamped' => [static fn (): mixed => array_column($admitted192()['following_current_rows_next192'], 'returning_key_name'), ['app_summary', 'theme_style_key']],
    'following signatures stable' => [static fn (): mixed => count(array_unique(array_column($admitted192()['following_current_rows_next192'], 'source_signature_next192'))), 1],
    'partial blocked reason' => [static fn (): mixed => $partial192()['blocked_reasons_next192'], ['next-source-returning-rows-not-acknowledged']],
    'none blocked reason' => [static fn (): mixed => $none192()['blocked_reasons_next192'], ['next-source-returning-rows-not-acknowledged']],
    'cursor blocked reason' => [static fn (): mixed => $cursorHeld192()['blocked_reasons_next192'], ['next-cursor-close-token-mismatch']],
    'token blocked reason' => [static fn (): mixed => $tokenHeld192()['blocked_reasons_next192'], ['following-current-source-token-mismatch']],
    'next held includes inherited and next ack reasons' => [static fn (): mixed => $nextHeld192()['blocked_reasons_next192'], ['fresh-current-returning-rows-not-acknowledged', 'next-source-returning-rows-not-acknowledged']],
    'admitted reasons empty' => [static fn (): mixed => $admitted192()['blocked_reasons_next192'], []],
    'close plan next rows required' => [static fn (): mixed => $admitted192()['cursor_close_plan_next192']['next_rows_required'], 2],
    'close plan next rows acknowledged' => [static fn (): mixed => $admitted192()['cursor_close_plan_next192']['next_rows_acknowledged'], 2],
    'partial close plan acknowledged' => [static fn (): mixed => $partial192()['cursor_close_plan_next192']['next_rows_acknowledged'], 1],
    'close plan cursor matches' => [static fn (): mixed => $admitted192()['cursor_close_plan_next192']['next_cursor_matches_close_token'], true],
    'close plan following visible' => [static fn (): mixed => $admitted192()['cursor_close_plan_next192']['following_rows_visible'], 2],
    'partial close plan following hidden' => [static fn (): mixed => $partial192()['cursor_close_plan_next192']['following_rows_visible'], 0],
    'close plan decision admitted' => [static fn (): mixed => $admitted192()['cursor_close_plan_next192']['decision'], 'admit-following-current-after-next-cursor-close'],
    'close plan decision partial' => [static fn (): mixed => $partial192()['cursor_close_plan_next192']['decision'], 'hold-following-current-until-next-returning-acks'],
    'close plan decision cursor held' => [static fn (): mixed => $cursorHeld192()['cursor_close_plan_next192']['decision'], 'hold-following-current-next-cursor-close-token'],
    'close plan decision token held' => [static fn (): mixed => $tokenHeld192()['cursor_close_plan_next192']['decision'], 'hold-following-current-source-token'],
    'close plan decision next held' => [static fn (): mixed => $nextHeld192()['cursor_close_plan_next192']['decision'], 'hold-following-current-until-next-source-visible'],
    'close plan resume after next ordinal' => [static fn (): mixed => $admitted192()['cursor_close_plan_next192']['resume_after_next_ordinal'], 1],
    'close plan following cursor' => [static fn (): mixed => $admitted192()['cursor_close_plan_next192']['following_cursor'], 'app.returning.following.cursor.192'],
    'yield boundary admitted' => [static fn (): mixed => $admitted192()['yield_boundary_next192'], 'recursive-view-returning-next192-next-cursor-drained-following-current-visible'],
    'yield boundary fenced' => [static fn (): mixed => $partial192()['yield_boundary_next192'], 'recursive-view-returning-next192-next-cursor-fences-following-current'],
    'dependency closure marker' => [static fn (): mixed => $admitted192()['dependency_closure_next192'], 'no new support component needed; reuses next189 next-source admission and adds next-cursor close fencing for the following current source'],
    'dependency includes next192' => [static fn (): mixed => in_array('sqlite-trigger-recursive-view-returning-current-source-next192', $admitted192()['dependencies_next192'], true), true],
    'dependency includes cursor close' => [static fn (): mixed => in_array('sqlite-returning-next-cursor-close-following-current-source-admission', $admitted192()['dependencies_next192'], true), true],
    'dependency includes next189' => [static fn (): mixed => in_array('sqlite-trigger-recursive-view-returning-current-source-next189', $admitted192()['dependencies_next192'], true), true],
    'non overlap mentions next189' => [static fn (): mixed => str_contains($admitted192()['non_overlap_next192'], 'next189 row-ack'), true],
    'bad next cursor rejected' => [static fn (): mixed => $plan192(['next_cursor' => 'bad cursor']), InvalidArgumentException::class],
    'bad close cursor rejected' => [static fn (): mixed => $plan192(['close_next_cursor' => 'bad cursor']), InvalidArgumentException::class],
    'bad following token rejected' => [static fn (): mixed => $plan192(['following_current_source_token' => 'bad token']), InvalidArgumentException::class],
    'bad expected following token rejected' => [static fn (): mixed => $plan192(['expected_following_current_source_token' => 'bad token']), InvalidArgumentException::class],
    'bad following cursor rejected' => [static fn (): mixed => $plan192(['following_cursor' => 'bad cursor']), InvalidArgumentException::class],
    'bad acknowledged list rejected' => [static fn (): mixed => $plan192(['next_acknowledged_ordinals' => 'bad-list']), InvalidArgumentException::class],
    'bad acknowledged ordinal rejected' => [static fn (): mixed => $plan192(['next_acknowledged_ordinals' => [-1]]), InvalidArgumentException::class],
    'bad following input rejected' => [static fn (): mixed => $plan192(['following_current_input' => 'bad-list']), InvalidArgumentException::class],
    'bad following view rejected' => [static fn (): mixed => $plan192(['following_current_view' => ['mapping' => ['bad' => '']]]), InvalidArgumentException::class],
];

$tests = [];
foreach ($cases192 as $name => [$callback, $expected]) {
    $tests['trigger recursive view returning current source next192 ' . $name] = static function (TestRunner $t) use ($callback, $expected): void {
        if (is_string($expected) && is_a($expected, Throwable::class, true)) {
            $t->throws($expected, $callback);
            return;
        }
        $t->same($expected, $callback());
    };
}

return $tests;
