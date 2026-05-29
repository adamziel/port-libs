<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteTriggerRecursiveViewReturningCurrentSourceNextPlan;

$rows189 = [
    ['option_id' => 1, 'option_name' => 'siteurl', 'option_value' => 'https://old.test', 'autoload' => 'yes'],
    ['option_id' => 2, 'option_name' => 'home', 'option_value' => 'https://home.test', 'autoload' => 'yes'],
];
$currentView189 = [
    'name' => 'wp_recursive_option_import',
    'source' => 'main@view-cookie-189-current',
    'trigger' => 'wp_recursive_option_import_io_insert',
    'trigger_source' => 'main@trigger-cookie-189-current',
    'columns' => ['import_id', 'name', 'value', 'autoload_flag', 'spawn_child'],
    'mapping' => ['import_id' => 'option_id', 'name' => 'option_name', 'value' => 'option_value', 'autoload_flag' => 'autoload'],
    'audit_label' => 'current-recursive-view-trigger-189',
];
$nextView189 = $currentView189;
$nextView189['source'] = 'main@view-cookie-189-next';
$nextView189['trigger_source'] = 'main@trigger-cookie-189-next';
$nextView189['audit_label'] = 'next-recursive-view-trigger-189';
$postResetView189 = $currentView189;
$postResetView189['source'] = 'main@view-cookie-189-post-reset';
$postResetView189['trigger_source'] = 'main@trigger-cookie-189-post-reset';
$postResetView189['audit_label'] = 'post-reset-recursive-view-trigger-189';
$currentInput189 = [
    ['import_id' => 10, 'name' => 'siteurl', 'value' => 'https://current.test', 'autoload_flag' => 'yes', 'spawn_child' => true],
    ['import_id' => 11, 'name' => 'current_plugin', 'value' => 'enabled', 'autoload_flag' => 'no', 'spawn_child' => true],
];
$nextInput189 = [
    ['import_id' => 20, 'name' => 'home', 'value' => 'https://next.test', 'autoload_flag' => 'yes', 'spawn_child' => true],
    ['import_id' => 21, 'name' => 'next_plugin', 'value' => 'active', 'autoload_flag' => 'no', 'spawn_child' => false],
];
$postResetInput189 = [
    ['import_id' => 30, 'name' => 'siteurl', 'value' => 'https://fresh.test', 'autoload_flag' => 'yes', 'spawn_child' => false],
    ['import_id' => 31, 'name' => 'rewrite_rules', 'value' => 'fresh-rules', 'autoload_flag' => 'no', 'spawn_child' => false],
];
$returning189 = [
    ['expr' => 'new.option_name', 'as' => 'name'],
    ['expr' => 'new.option_value', 'as' => 'value'],
    ['expr' => 'old.option_value', 'as' => 'old_value'],
    ['expr' => 'event', 'as' => 'event_name'],
    ['expr' => 'ordinal', 'as' => 'ordinal_value'],
    ['expr' => 'trigger_source', 'as' => 'trigger_source_alias'],
];

$plan189 = static fn (array $options = []): array => SQLiteTriggerRecursiveViewReturningCurrentSourceNextPlan::executeNextSourceAdmissionAfterFreshAck(
    $rows189,
    $currentInput189,
    $nextInput189,
    $currentView189,
    $nextView189,
    $returning189,
    $options + [
        'key' => 'option_name',
        'savepoint' => 'wp_recursive_view_189',
        'cursor_name' => 'wp_recursive_view_returning_cursor_189',
        'current_generation' => 'wp-current-returning-189',
        'next_generation' => 'wp-next-returning-189',
        'page_size' => 3,
        'admit_next_source' => true,
        'current_source_token' => 'wp.current.source.189',
        'drain_ack_token' => 'wp.returning.drain.189',
        'rollback_token' => 'wp.rollback.current.189',
        'reset_generation' => 'wp-current-reset-189',
        'post_reset_current_source_token' => 'wp.current.source.postreset.189',
        'post_reset_cursor' => 'wp.returning.postreset.cursor.189',
        'post_reset_view' => $postResetView189,
        'post_reset_input' => $postResetInput189,
        'fresh_acknowledged_ordinals' => [0, 1],
        'next_source_token' => 'wp.next.source.189',
        'next_cursor' => 'wp.returning.next.cursor.189',
    ],
);

$admitted189 = static fn (): array => $plan189();
$partial189 = static fn (): array => $plan189(['fresh_acknowledged_ordinals' => [0]]);
$none189 = static fn (): array => $plan189(['fresh_acknowledged_ordinals' => []]);
$tokenHeld189 = static fn (): array => $plan189(['expected_next_source_token' => 'wp.next.source.expected.189']);
$generationHeld189 = static fn (): array => $plan189(['expected_reset_generation' => 'wp-current-reset-expected-189']);
$postResetHeld189 = static fn (): array => $plan189(['reuse_stale_returning_cursor' => true]);
$custom189 = static fn (): array => $plan189(['next_source_token' => 'wp.next.source.custom.189', 'expected_next_source_token' => 'wp.next.source.custom.189', 'next_cursor' => 'wp.returning.next.custom.cursor.189']);

$cases189 = [
    'admitted status' => [static fn (): mixed => $admitted189()['status_next189'], 'trigger-recursive-view-returning-current-source-next189-next-source-visible'],
    'partial status' => [static fn (): mixed => $partial189()['status_next189'], 'trigger-recursive-view-returning-current-source-next189-awaiting-current-row-acks'],
    'none status' => [static fn (): mixed => $none189()['status_next189'], 'trigger-recursive-view-returning-current-source-next189-awaiting-current-row-acks'],
    'token held status' => [static fn (): mixed => $tokenHeld189()['status_next189'], 'trigger-recursive-view-returning-current-source-next189-next-token-held'],
    'generation held status' => [static fn (): mixed => $generationHeld189()['status_next189'], 'trigger-recursive-view-returning-current-source-next189-reset-generation-held'],
    'post reset held status' => [static fn (): mixed => $postResetHeld189()['status_next189'], 'trigger-recursive-view-returning-current-source-next189-post-reset-held'],
    'savepoint retained' => [static fn (): mixed => $admitted189()['savepoint'], 'wp_recursive_view_189'],
    'base next186 rebound' => [static fn (): mixed => $admitted189()['base']['status_next186'], 'trigger-recursive-view-returning-current-source-next186-post-reset-rebound'],
    'fresh required ordinals' => [static fn (): mixed => $admitted189()['fresh_required_ordinals_next189'], [0, 1]],
    'fresh acknowledged ordinals' => [static fn (): mixed => $admitted189()['fresh_acknowledged_ordinals_next189'], [0, 1]],
    'partial acknowledged ordinals' => [static fn (): mixed => $partial189()['fresh_acknowledged_ordinals_next189'], [0]],
    'duplicate acknowledged ordinals coalesced' => [static fn (): mixed => $plan189(['fresh_acknowledged_ordinals' => [0, 0, 1]])['fresh_acknowledged_ordinals_next189'], [0, 1]],
    'current rows acknowledged' => [static fn (): mixed => $admitted189()['fresh_current_rows_acknowledged_next189'], true],
    'partial current rows not acknowledged' => [static fn (): mixed => $partial189()['fresh_current_rows_acknowledged_next189'], false],
    'next token retained' => [static fn (): mixed => $admitted189()['next_source_token_next189'], 'wp.next.source.189'],
    'expected next token retained' => [static fn (): mixed => $admitted189()['expected_next_source_token_next189'], 'wp.next.source.189'],
    'next token matches' => [static fn (): mixed => $admitted189()['next_source_token_matches_next189'], true],
    'next token mismatch' => [static fn (): mixed => $tokenHeld189()['next_source_token_matches_next189'], false],
    'reset generation retained' => [static fn (): mixed => $admitted189()['expected_reset_generation_next189'], 'wp-current-reset-189'],
    'reset generation matches' => [static fn (): mixed => $admitted189()['reset_generation_matches_next189'], true],
    'reset generation mismatch' => [static fn (): mixed => $generationHeld189()['reset_generation_matches_next189'], false],
    'next cursor retained' => [static fn (): mixed => $admitted189()['next_cursor_next189'], 'wp.returning.next.cursor.189'],
    'custom cursor retained' => [static fn (): mixed => $custom189()['next_cursor_next189'], 'wp.returning.next.custom.cursor.189'],
    'next source row count' => [static fn (): mixed => $admitted189()['next_source_row_count_next189'], 2],
    'partial has no next rows' => [static fn (): mixed => $partial189()['next_source_rows_next189'], []],
    'token held has no next rows' => [static fn (): mixed => $tokenHeld189()['next_source_rows_next189'], []],
    'generation held has no next rows' => [static fn (): mixed => $generationHeld189()['next_source_rows_next189'], []],
    'post reset held has no next rows' => [static fn (): mixed => $postResetHeld189()['next_source_rows_next189'], []],
    'next payload names' => [static fn (): mixed => array_column($admitted189()['next_source_payloads_next189'], 'name'), ['home', 'next_plugin']],
    'next payload values' => [static fn (): mixed => array_column($admitted189()['next_source_payloads_next189'], 'value'), ['https://next.test', 'active']],
    'next old values null' => [static fn (): mixed => array_column($admitted189()['next_source_payloads_next189'], 'old_value'), [null, null]],
    'next events' => [static fn (): mixed => array_values(array_unique(array_column($admitted189()['next_source_payloads_next189'], 'event_name'))), ['next-source']],
    'next ordinals' => [static fn (): mixed => array_column($admitted189()['next_source_payloads_next189'], 'ordinal_value'), [0, 1]],
    'next trigger source' => [static fn (): mixed => array_values(array_unique(array_column($admitted189()['next_source_payloads_next189'], 'trigger_source_alias'))), ['main@trigger-cookie-189-next']],
    'next statement sources' => [static fn (): mixed => array_column($admitted189()['next_source_rows_next189'], 'statement_source'), ['next-source', 'next-source']],
    'next token stamped' => [static fn (): mixed => array_values(array_unique(array_column($admitted189()['next_source_rows_next189'], 'next_source_token_next189'))), ['wp.next.source.189']],
    'next cursor stamped' => [static fn (): mixed => array_values(array_unique(array_column($admitted189()['next_source_rows_next189'], 'next_cursor_next189'))), ['wp.returning.next.cursor.189']],
    'next generation stamped' => [static fn (): mixed => array_values(array_unique(array_column($admitted189()['next_source_rows_next189'], 'next_generation_next189'))), ['wp-next-returning-189']],
    'next option names stamped' => [static fn (): mixed => array_column($admitted189()['next_source_rows_next189'], 'returning_option_name'), ['home', 'next_plugin']],
    'next signatures stable' => [static fn (): mixed => count(array_unique(array_column($admitted189()['next_source_rows_next189'], 'source_signature_next189'))), 1],
    'partial blocked reason' => [static fn (): mixed => $partial189()['blocked_reasons_next189'], ['fresh-current-returning-rows-not-acknowledged']],
    'none blocked reason' => [static fn (): mixed => $none189()['blocked_reasons_next189'], ['fresh-current-returning-rows-not-acknowledged']],
    'token blocked reason' => [static fn (): mixed => $tokenHeld189()['blocked_reasons_next189'], ['next-source-token-mismatch']],
    'generation blocked reason' => [static fn (): mixed => $generationHeld189()['blocked_reasons_next189'], ['reset-generation-token-mismatch']],
    'post reset blocked reasons include stale cursor and ack fence' => [static fn (): mixed => $postResetHeld189()['blocked_reasons_next189'], ['stale-returning-cursor-reuse-rejected', 'fresh-current-returning-rows-not-acknowledged']],
    'admitted reasons empty' => [static fn (): mixed => $admitted189()['blocked_reasons_next189'], []],
    'handoff fresh rows required' => [static fn (): mixed => $admitted189()['handoff_plan_next189']['fresh_rows_required'], 2],
    'handoff fresh rows acknowledged' => [static fn (): mixed => $admitted189()['handoff_plan_next189']['fresh_rows_acknowledged'], 2],
    'partial handoff fresh rows acknowledged' => [static fn (): mixed => $partial189()['handoff_plan_next189']['fresh_rows_acknowledged'], 1],
    'handoff next rows visible' => [static fn (): mixed => $admitted189()['handoff_plan_next189']['next_rows_visible'], 2],
    'partial handoff next rows hidden' => [static fn (): mixed => $partial189()['handoff_plan_next189']['next_rows_visible'], 0],
    'handoff decision admitted' => [static fn (): mixed => $admitted189()['handoff_plan_next189']['decision'], 'admit-next-source-after-post-reset-current-acks'],
    'handoff decision partial' => [static fn (): mixed => $partial189()['handoff_plan_next189']['decision'], 'hold-next-source-until-fresh-current-returning-acks'],
    'handoff decision token held' => [static fn (): mixed => $tokenHeld189()['handoff_plan_next189']['decision'], 'hold-next-source-token'],
    'handoff decision generation held' => [static fn (): mixed => $generationHeld189()['handoff_plan_next189']['decision'], 'hold-next-source-reset-generation'],
    'handoff decision post reset held' => [static fn (): mixed => $postResetHeld189()['handoff_plan_next189']['decision'], 'hold-next-source-until-post-reset-current-rebind'],
    'handoff resume after ordinal' => [static fn (): mixed => $admitted189()['handoff_plan_next189']['resume_after_fresh_ordinal'], 1],
    'handoff next cursor' => [static fn (): mixed => $admitted189()['handoff_plan_next189']['next_cursor'], 'wp.returning.next.cursor.189'],
    'yield boundary admitted' => [static fn (): mixed => $admitted189()['yield_boundary_next189'], 'recursive-view-returning-next189-current-rebound-rows-acked-next-source-visible'],
    'yield boundary fenced' => [static fn (): mixed => $partial189()['yield_boundary_next189'], 'recursive-view-returning-next189-current-rebound-rows-fence-next-source'],
    'dependency closure marker' => [static fn (): mixed => $admitted189()['dependency_closure_next189'], 'no new support component needed; reuses next186 post-reset RETURNING rebinding and adds row-ack next-source admission fencing'],
    'dependency includes next189' => [static fn (): mixed => in_array('sqlite-trigger-recursive-view-returning-current-source-next189', $admitted189()['dependencies_next189'], true), true],
    'dependency includes row ack' => [static fn (): mixed => in_array('sqlite-returning-post-reset-row-ack-next-source-admission', $admitted189()['dependencies_next189'], true), true],
    'dependency includes next186' => [static fn (): mixed => in_array('sqlite-trigger-recursive-view-returning-current-source-next186', $admitted189()['dependencies_next189'], true), true],
    'non overlap mentions next186' => [static fn (): mixed => str_contains($admitted189()['non_overlap_next189'], 'next186 post-reset'), true],
    'bad next token rejected' => [static fn (): mixed => $plan189(['next_source_token' => 'bad token']), InvalidArgumentException::class],
    'bad expected next token rejected' => [static fn (): mixed => $plan189(['expected_next_source_token' => 'bad token']), InvalidArgumentException::class],
    'bad next cursor rejected' => [static fn (): mixed => $plan189(['next_cursor' => 'bad cursor']), InvalidArgumentException::class],
    'bad generation rejected' => [static fn (): mixed => $plan189(['expected_reset_generation' => 'bad token']), InvalidArgumentException::class],
    'bad acknowledged list rejected' => [static fn (): mixed => $plan189(['fresh_acknowledged_ordinals' => 'bad-list']), InvalidArgumentException::class],
    'bad acknowledged ordinal rejected' => [static fn (): mixed => $plan189(['fresh_acknowledged_ordinals' => [-1]]), InvalidArgumentException::class],
    'bad next view rejected' => [static fn (): mixed => SQLiteTriggerRecursiveViewReturningCurrentSourceNextPlan::executeNextSourceAdmissionAfterFreshAck($rows189, $currentInput189, $nextInput189, $currentView189, ['mapping' => ['bad' => '']], $returning189, ['post_reset_view' => $postResetView189, 'post_reset_input' => $postResetInput189, 'fresh_acknowledged_ordinals' => [0, 1]]), InvalidArgumentException::class],
];

$tests = [];
foreach ($cases189 as $name => [$callback, $expected]) {
    $tests['trigger recursive view returning current source next189 ' . $name] = static function (TestRunner $t) use ($callback, $expected): void {
        if (is_string($expected) && is_a($expected, Throwable::class, true)) {
            $t->throws($expected, $callback);
            return;
        }
        $t->same($expected, $callback());
    };
}

return $tests;
