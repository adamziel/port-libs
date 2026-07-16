<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteTriggerRecursiveViewReturningCurrentSourceNextPlan;

$rows186 = [
    ['setting_id' => 1, 'key_name' => 'base_url', 'key_value' => 'https://old.test', 'load_policy' => 'yes'],
    ['setting_id' => 2, 'key_name' => 'landing_url', 'key_value' => 'https://landing_url.test', 'load_policy' => 'yes'],
];
$currentView186 = [
    'name' => 'app_recursive_setting_import',
    'source' => 'main@view-cookie-186-current',
    'trigger' => 'app_recursive_setting_import_io_insert',
    'trigger_source' => 'main@trigger-cookie-186-current',
    'columns' => ['import_id', 'name', 'value', 'load_policy_flag', 'spawn_child'],
    'mapping' => ['import_id' => 'setting_id', 'name' => 'key_name', 'value' => 'key_value', 'load_policy_flag' => 'load_policy'],
    'audit_label' => 'current-recursive-view-trigger-186',
];
$nextView186 = $currentView186;
$nextView186['source'] = 'main@view-cookie-186-next';
$nextView186['trigger_source'] = 'main@trigger-cookie-186-next';
$nextView186['audit_label'] = 'next-recursive-view-trigger-186';
$postResetView186 = $currentView186;
$postResetView186['source'] = 'main@view-cookie-186-post-reset';
$postResetView186['trigger_source'] = 'main@trigger-cookie-186-post-reset';
$postResetView186['audit_label'] = 'post-reset-recursive-view-trigger-186';
$currentInput186 = [
    ['import_id' => 10, 'name' => 'base_url', 'value' => 'https://current.test', 'load_policy_flag' => 'yes', 'spawn_child' => true],
    ['import_id' => 11, 'name' => 'current_module', 'value' => 'enabled', 'load_policy_flag' => 'no', 'spawn_child' => true],
];
$nextInput186 = [
    ['import_id' => 20, 'name' => 'landing_url', 'value' => 'https://next.test', 'load_policy_flag' => 'yes', 'spawn_child' => true],
    ['import_id' => 21, 'name' => 'next_module', 'value' => 'active', 'load_policy_flag' => 'no', 'spawn_child' => false],
];
$postResetInput186 = [
    ['import_id' => 30, 'name' => 'base_url', 'value' => 'https://fresh.test', 'load_policy_flag' => 'yes', 'spawn_child' => false],
    ['import_id' => 31, 'name' => 'routing_rules', 'value' => 'fresh-rules', 'load_policy_flag' => 'no', 'spawn_child' => false],
];
$returning186 = [
    ['expr' => 'new.key_name', 'as' => 'name'],
    ['expr' => 'new.key_value', 'as' => 'value'],
    ['expr' => 'old.key_value', 'as' => 'old_value'],
    ['expr' => 'event', 'as' => 'event_name'],
    ['expr' => 'ordinal', 'as' => 'ordinal_value'],
    ['expr' => 'trigger_source', 'as' => 'trigger_source_alias'],
];

$plan186 = static fn (array $options = []): array => SQLiteTriggerRecursiveViewReturningCurrentSourceNextPlan::executePostResetCurrentSourceRebind(
    $rows186,
    $currentInput186,
    $nextInput186,
    $currentView186,
    $nextView186,
    $returning186,
    $options + [
        'key' => 'key_name',
        'savepoint' => 'app_recursive_view_186',
        'cursor_name' => 'app_recursive_view_returning_cursor_186',
        'current_generation' => 'app-current-returning-186',
        'next_generation' => 'app-next-returning-186',
        'page_size' => 3,
        'admit_next_source' => true,
        'current_source_token' => 'app.current.source.186',
        'drain_ack_token' => 'app.returning.drain.186',
        'rollback_token' => 'app.rollback.current.186',
        'reset_generation' => 'app-current-reset-186',
        'post_reset_current_source_token' => 'app.current.source.postreset.186',
        'post_reset_cursor' => 'app.returning.postreset.cursor.186',
        'post_reset_view' => $postResetView186,
        'post_reset_input' => $postResetInput186,
    ],
);

$rebound186 = static fn (): array => $plan186();
$tokenHeld186 = static fn (): array => $plan186(['expected_post_reset_current_source_token' => 'app.current.source.postreset.expected.186']);
$staleCursor186 = static fn (): array => $plan186(['reuse_stale_returning_cursor' => true]);
$noReset186 = static fn (): array => $plan186(['rollback_current_source' => false, 'commit_current_source' => true]);
$customPostReset186 = static fn (): array => $plan186([
    'post_reset_current_source_token' => 'app.current.source.custom.186',
    'post_reset_cursor' => 'app.returning.custom.cursor.186',
    'expected_post_reset_current_source_token' => 'app.current.source.custom.186',
]);

$cases186 = [
    'rebound status' => [static fn (): mixed => $rebound186()['status_next186'], 'trigger-recursive-view-returning-current-source-next186-post-reset-rebound'],
    'token held status' => [static fn (): mixed => $tokenHeld186()['status_next186'], 'trigger-recursive-view-returning-current-source-next186-token-held'],
    'stale cursor status' => [static fn (): mixed => $staleCursor186()['status_next186'], 'trigger-recursive-view-returning-current-source-next186-stale-cursor-rejected'],
    'no reset status' => [static fn (): mixed => $noReset186()['status_next186'], 'trigger-recursive-view-returning-current-source-next186-reset-held'],
    'savepoint retained' => [static fn (): mixed => $rebound186()['savepoint'], 'app_recursive_view_186'],
    'base next183 rolled back' => [static fn (): mixed => $rebound186()['base']['status_next183'], 'trigger-recursive-view-returning-current-source-next183-rolled-back'],
    'post reset token retained' => [static fn (): mixed => $rebound186()['post_reset_current_source_token_next186'], 'app.current.source.postreset.186'],
    'expected post reset token retained' => [static fn (): mixed => $rebound186()['expected_post_reset_current_source_token_next186'], 'app.current.source.postreset.186'],
    'post reset token matches' => [static fn (): mixed => $rebound186()['post_reset_current_source_token_matches_next186'], true],
    'post reset token mismatch' => [static fn (): mixed => $tokenHeld186()['post_reset_current_source_token_matches_next186'], false],
    'post reset cursor retained' => [static fn (): mixed => $rebound186()['post_reset_cursor_next186'], 'app.returning.postreset.cursor.186'],
    'custom cursor retained' => [static fn (): mixed => $customPostReset186()['post_reset_cursor_next186'], 'app.returning.custom.cursor.186'],
    'stale cursor flag default' => [static fn (): mixed => $rebound186()['reuse_stale_returning_cursor_next186'], false],
    'stale cursor flag true' => [static fn (): mixed => $staleCursor186()['reuse_stale_returning_cursor_next186'], true],
    'stale rows discarded' => [static fn (): mixed => $rebound186()['stale_returning_rows_discarded_next186'], true],
    'no reset stale rows not discarded' => [static fn (): mixed => $noReset186()['stale_returning_rows_discarded_next186'], false],
    'stale row count from next183 invalidation' => [static fn (): mixed => $rebound186()['stale_returning_row_count_next186'], 6],
    'stale row names retained for audit only' => [static fn (): mixed => array_column($rebound186()['stale_returning_rows_next186'], 'name'), ['base_url', 'current_module', 'base_url:child', 'current_module:child', 'base_url:child:child', 'current_module:child:child']],
    'fresh row count' => [static fn (): mixed => $rebound186()['fresh_returning_row_count_next186'], 2],
    'fresh row names' => [static fn (): mixed => array_column($rebound186()['fresh_returning_payloads_next186'], 'name'), ['base_url', 'routing_rules']],
    'fresh row values' => [static fn (): mixed => array_column($rebound186()['fresh_returning_payloads_next186'], 'value'), ['https://fresh.test', 'fresh-rules']],
    'fresh old values null' => [static fn (): mixed => array_column($rebound186()['fresh_returning_payloads_next186'], 'old_value'), [null, null]],
    'fresh events' => [static fn (): mixed => array_values(array_unique(array_column($rebound186()['fresh_returning_payloads_next186'], 'event_name'))), ['post-reset-current']],
    'fresh ordinals' => [static fn (): mixed => array_column($rebound186()['fresh_returning_payloads_next186'], 'ordinal_value'), [0, 1]],
    'fresh trigger source' => [static fn (): mixed => array_values(array_unique(array_column($rebound186()['fresh_returning_payloads_next186'], 'trigger_source_alias'))), ['main@trigger-cookie-186-post-reset']],
    'fresh statement sources' => [static fn (): mixed => array_column($rebound186()['fresh_returning_rows_next186'], 'statement_source'), ['post-reset-current', 'post-reset-current']],
    'fresh token stamped' => [static fn (): mixed => array_values(array_unique(array_column($rebound186()['fresh_returning_rows_next186'], 'post_reset_current_source_token_next186'))), ['app.current.source.postreset.186']],
    'fresh cursor stamped' => [static fn (): mixed => array_values(array_unique(array_column($rebound186()['fresh_returning_rows_next186'], 'post_reset_cursor_next186'))), ['app.returning.postreset.cursor.186']],
    'fresh reset generation stamped' => [static fn (): mixed => array_values(array_unique(array_column($rebound186()['fresh_returning_rows_next186'], 'reset_generation_next186'))), ['app-current-reset-186']],
    'fresh stale cursor flags false' => [static fn (): mixed => array_values(array_unique(array_column($rebound186()['fresh_returning_rows_next186'], 'stale_cursor_reused_next186'))), [false]],
    'token held no fresh rows' => [static fn (): mixed => $tokenHeld186()['fresh_returning_rows_next186'], []],
    'stale cursor no fresh rows' => [static fn (): mixed => $staleCursor186()['fresh_returning_rows_next186'], []],
    'no reset no fresh rows' => [static fn (): mixed => $noReset186()['fresh_returning_rows_next186'], []],
    'token held reason' => [static fn (): mixed => $tokenHeld186()['blocked_reasons_next186'], ['post-reset-current-source-token-mismatch']],
    'stale cursor reason' => [static fn (): mixed => $staleCursor186()['blocked_reasons_next186'], ['stale-returning-cursor-reuse-rejected']],
    'no reset reasons' => [static fn (): mixed => $noReset186()['blocked_reasons_next186'], ['current-source-reset-not-applied', 'current-source-committed-no-reset-rebind']],
    'rebound reasons empty' => [static fn (): mixed => $rebound186()['blocked_reasons_next186'], []],
    'plan reset generation' => [static fn (): mixed => $rebound186()['post_reset_rebind_plan_next186']['reset_generation'], 'app-current-reset-186'],
    'plan reset applied' => [static fn (): mixed => $rebound186()['post_reset_rebind_plan_next186']['reset_applied'], true],
    'plan stale rows discarded count' => [static fn (): mixed => $rebound186()['post_reset_rebind_plan_next186']['stale_rows_discarded'], 6],
    'plan fresh rows bound count' => [static fn (): mixed => $rebound186()['post_reset_rebind_plan_next186']['fresh_rows_bound'], 2],
    'plan decision rebound' => [static fn (): mixed => $rebound186()['post_reset_rebind_plan_next186']['decision'], 'bind-fresh-post-reset-current-source'],
    'plan decision token held' => [static fn (): mixed => $tokenHeld186()['post_reset_rebind_plan_next186']['decision'], 'hold-post-reset-current-source-token'],
    'plan decision stale rejected' => [static fn (): mixed => $staleCursor186()['post_reset_rebind_plan_next186']['decision'], 'reject-stale-returning-cursor'],
    'plan decision reset held' => [static fn (): mixed => $noReset186()['post_reset_rebind_plan_next186']['decision'], 'hold-until-current-source-reset'],
    'yield boundary rebound' => [static fn (): mixed => $rebound186()['yield_boundary_next186'], 'recursive-view-returning-next186-post-reset-current-source-rebound'],
    'yield boundary held' => [static fn (): mixed => $tokenHeld186()['yield_boundary_next186'], 'recursive-view-returning-next186-post-reset-current-source-held'],
    'dependency closure marker' => [static fn (): mixed => $rebound186()['dependency_closure_next186'], 'no new support component needed; reuses next183 reset-barrier rows and adds post-reset current-source RETURNING cursor rebinding'],
    'dependency includes next186' => [static fn (): mixed => in_array('sqlite-trigger-recursive-view-returning-current-source-next186', $rebound186()['dependencies_next186'], true), true],
    'dependency includes rebind' => [static fn (): mixed => in_array('sqlite-returning-post-reset-current-source-rebind', $rebound186()['dependencies_next186'], true), true],
    'dependency includes next183' => [static fn (): mixed => in_array('sqlite-trigger-recursive-view-returning-current-source-next183', $rebound186()['dependencies_next186'], true), true],
    'non overlap mentions next183' => [static fn (): mixed => str_contains($rebound186()['non_overlap_next186'], 'next183 rollback'), true],
    'signature changes with post reset view' => [static fn (): mixed => $rebound186()['post_reset_view_signature_next186'] !== $plan186(['post_reset_view' => $currentView186])['post_reset_view_signature_next186'], true],
    'bad post reset token rejected' => [static fn (): mixed => $plan186(['post_reset_current_source_token' => 'bad token']), InvalidArgumentException::class],
    'bad expected post reset token rejected' => [static fn (): mixed => $plan186(['expected_post_reset_current_source_token' => 'bad token']), InvalidArgumentException::class],
    'bad post reset cursor rejected' => [static fn (): mixed => $plan186(['post_reset_cursor' => 'bad cursor']), InvalidArgumentException::class],
    'bad post reset input rejected' => [static fn (): mixed => $plan186(['post_reset_input' => 'bad-input']), InvalidArgumentException::class],
    'bad post reset view rejected' => [static fn (): mixed => $plan186(['post_reset_view' => 'bad-view']), InvalidArgumentException::class],
];

$tests = [];
foreach ($cases186 as $name => [$callback, $expected]) {
    $tests['trigger recursive view returning current source next186 ' . $name] = static function (TestRunner $t) use ($callback, $expected): void {
        if (is_string($expected) && is_a($expected, Throwable::class, true)) {
            $t->throws($expected, $callback);
            return;
        }
        $t->same($expected, $callback());
    };
}

return $tests;
