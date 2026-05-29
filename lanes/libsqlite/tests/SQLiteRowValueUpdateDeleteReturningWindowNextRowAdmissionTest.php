<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNextPlan;

$rows255 = [
    ['option_id' => 1, 'blog_id' => 1, 'option_name' => 'siteurl', 'autoload' => 'yes', 'status' => 'live', 'bytes' => 20, 'option_value' => 'https://one.test'],
    ['option_id' => 2, 'blog_id' => 1, 'option_name' => 'home', 'autoload' => 'yes', 'status' => 'live', 'bytes' => 19, 'option_value' => 'https://home.test'],
    ['option_id' => 3, 'blog_id' => 1, 'option_name' => '_transient_feed', 'autoload' => 'no', 'status' => 'stale', 'bytes' => 12, 'option_value' => 'feed'],
    ['option_id' => 4, 'blog_id' => 1, 'option_name' => '_transient_timeout_feed', 'autoload' => 'no', 'status' => 'stale', 'bytes' => 13, 'option_value' => 'timeout'],
    ['option_id' => 5, 'blog_id' => 2, 'option_name' => 'pending_theme', 'autoload' => 'no', 'status' => 'queued', 'bytes' => 7, 'option_value' => 'theme'],
    ['option_id' => 6, 'blog_id' => 2, 'option_name' => 'siteurl', 'autoload' => 'yes', 'status' => 'live', 'bytes' => 25, 'option_value' => 'https://two.test'],
    ['option_id' => 7, 'blog_id' => 3, 'option_name' => 'rewrite_rules', 'autoload' => 'rewrite', 'status' => 'queued', 'bytes' => 9, 'option_value' => 'rules'],
    ['option_id' => 8, 'blog_id' => 3, 'option_name' => 'orphaned_cache', 'autoload' => 'yes', 'status' => 'orphaned', 'bytes' => 5, 'option_value' => 'cache'],
    ['option_id' => 9, 'blog_id' => 4, 'option_name' => 'plugin_batch', 'autoload' => 'no', 'status' => 'queued', 'bytes' => 11, 'option_value' => 'plugin'],
    ['option_id' => 10, 'blog_id' => 4, 'option_name' => 'home', 'autoload' => 'yes', 'status' => 'live', 'bytes' => 31, 'option_value' => 'https://four-home.test'],
];
$tables255 = ['wp_options' => $rows255];
$unique255 = [['blog_id', 'option_name']];

$yieldUpdate255 = "UPDATE wp_options SET (status, option_value, bytes) = ('yield255', option_value || ':yield255', bytes + 30) WHERE (blog_id, option_name) IN ((2, 'pending_theme'), (3, 'rewrite_rules')) RETURNING option_id, blog_id, option_name, status, bytes ORDER BY option_id";
$yieldDelete255 = "DELETE FROM wp_options WHERE (blog_id, option_name) IN ((1, '_transient_feed')) RETURNING option_id, blog_id, option_name, status, bytes ORDER BY option_id";
$attemptUpdate255 = "UPDATE wp_options SET (status, option_value, bytes) = ('attempt255', option_value || ':attempt255', bytes + 5) WHERE (blog_id, option_name) IN ((2, 'pending_theme'), (3, 'rewrite_rules')) RETURNING option_id, blog_id, option_name, status, bytes ORDER BY option_id";
$attemptDelete255 = "DELETE FROM wp_options WHERE (blog_id, option_name) IN ((3, 'orphaned_cache')) RETURNING option_id, blog_id, option_name, status, bytes ORDER BY option_id";
$retryUpdate255 = "UPDATE wp_options SET (status, option_value, bytes) = ('retry255', option_value || ':retry255', bytes + 20) WHERE (blog_id, option_name) IN ((2, 'pending_theme'), (3, 'rewrite_rules'), (4, 'plugin_batch')) RETURNING option_id, blog_id, option_name, status, bytes ORDER BY option_id";
$retryDelete255 = "DELETE FROM wp_options WHERE (blog_id, option_name) IN ((1, '_transient_timeout_feed'), (4, 'home')) RETURNING option_id, blog_id, option_name, status, bytes ORDER BY option_id";

$plan255 = static fn (?array $ackYield = null, ?string $resume = null, ?array $ackNext = null): array => SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNextPlan::executeWindowRowAdmission(
    $tables255,
    [$yieldUpdate255, $yieldDelete255],
    [$attemptUpdate255, $attemptDelete255],
    [$retryUpdate255, $retryDelete255],
    $unique255,
    'wp_options_rowvalue_window_current_next255',
    'option_id',
    $ackYield,
    $resume,
    $ackNext,
);

$tickets255 = static fn (): array => $plan255()['source_handoff_tickets_next251'];
$required255 = static fn (): array => $plan255()['required_yield_tickets_next245'];
$ackGap255 = static fn (): array => [$tickets255()[0], $tickets255()[2], $tickets255()[3], $tickets255()[4], $tickets255()[5], $tickets255()[6], $tickets255()[7]];
$ackFirstOnly255 = static fn (): array => [$tickets255()[0]];
$lastReadyWithGap255 = static fn (): string => $plan255(null, null, $ackGap255())['next_row_ready_tickets_next255'][4];

$cases255 = [
    'status' => [static fn (): mixed => $plan255()['status'], 'rowvalue-update-delete-returning-window-current-source-next255'],
    'inherits next251 state' => [static fn (): mixed => $plan255()['source_handoff_state_next251'], 'current-source-drained-next-source-digest-ready-next251'],
    'next row admission flag' => [static fn (): mixed => $plan255()['next_row_admission_next255'], true],
    'row count' => [static fn (): mixed => $plan255()['next_row_admission_summary_next255']['row_count'], 8],
    'ready count' => [static fn (): mixed => $plan255()['next_row_admission_summary_next255']['ready_count'], 8],
    'blocked count' => [static fn (): mixed => $plan255()['next_row_admission_summary_next255']['blocked_count'], 0],
    'current source ready count' => [static fn (): mixed => $plan255()['next_row_admission_summary_next255']['current_source_ready_count'], 3],
    'next source ready count' => [static fn (): mixed => $plan255()['next_row_admission_summary_next255']['next_source_ready_count'], 5],
    'ready rowids' => [static fn (): mixed => $plan255()['next_row_admission_summary_next255']['ready_rowids'], [7, 5, 3, 9, 10, 7, 5, 4]],
    'blocked rowids empty' => [static fn (): mixed => $plan255()['next_row_admission_summary_next255']['blocked_rowids'], []],
    'blocked reasons empty' => [static fn (): mixed => $plan255()['next_row_admission_summary_next255']['blocked_reasons'], []],
    'fence savepoint' => [static fn (): mixed => $plan255()['next_row_admission_fence_next255']['savepoint'], 'wp_options_rowvalue_window_current_next255'],
    'fence source state' => [static fn (): mixed => $plan255()['next_row_admission_fence_next255']['source_handoff_state'], 'current-source-drained-next-source-digest-ready-next251'],
    'fence mode' => [static fn (): mixed => $plan255()['next_row_admission_fence_next255']['window_mode'], 'RETURNING rows next-row admission after current source handoff'],
    'fence row count' => [static fn (): mixed => $plan255()['next_row_admission_fence_next255']['row_count'], 8],
    'fence ready count' => [static fn (): mixed => $plan255()['next_row_admission_fence_next255']['ready_count'], 8],
    'fence blocked count' => [static fn (): mixed => $plan255()['next_row_admission_fence_next255']['blocked_count'], 0],
    'fence all retry acknowledged' => [static fn (): mixed => $plan255()['next_row_admission_fence_next255']['all_retry_rows_acknowledged'], true],
    'fence all current acknowledged' => [static fn (): mixed => $plan255()['next_row_admission_fence_next255']['all_current_rows_acknowledged'], true],
    'fence handoff token sha256' => [static fn (): mixed => strlen($plan255()['next_row_admission_fence_next255']['source_handoff_token']), 64],
    'fence ready digest sha256' => [static fn (): mixed => strlen($plan255()['next_row_admission_fence_next255']['ready_digest']), 64],
    'fence blocked digest sha256' => [static fn (): mixed => strlen($plan255()['next_row_admission_fence_next255']['blocked_digest']), 64],
    'rows tickets preserved' => [static fn (): mixed => array_column($plan255()['next_row_window_rows_next255'], 'ticket'), $tickets255()],
    'rows ordinals' => [static fn (): mixed => array_column($plan255()['next_row_window_rows_next255'], 'next_row_ordinal_next255'), [1, 2, 3, 4, 5, 6, 7, 8]],
    'rows ids' => [static fn (): mixed => array_column($plan255()['next_row_window_rows_next255'], 'next_row_rowid_next255'), [7, 5, 3, 9, 10, 7, 5, 4]],
    'rows source epochs' => [static fn (): mixed => array_column($plan255()['next_row_window_rows_next255'], 'next_row_source_epoch_next255'), ['wp-current-source-251', 'wp-current-source-251', 'wp-current-source-251', 'wp-next-source-251', 'wp-next-source-251', 'wp-next-source-251', 'wp-next-source-251', 'wp-next-source-251']],
    'previous tickets' => [static fn (): mixed => array_column($plan255()['next_row_window_rows_next255'], 'next_row_previous_ticket_next255'), [null, $tickets255()[0], $tickets255()[1], $tickets255()[2], $tickets255()[3], $tickets255()[4], $tickets255()[5], $tickets255()[6]]],
    'next tickets' => [static fn (): mixed => array_column($plan255()['next_row_window_rows_next255'], 'next_row_next_ticket_next255'), [$tickets255()[1], $tickets255()[2], $tickets255()[3], $tickets255()[4], $tickets255()[5], $tickets255()[6], $tickets255()[7], null]],
    'current acknowledged flags' => [static fn (): mixed => array_unique(array_column($plan255()['next_row_window_rows_next255'], 'next_row_current_acknowledged_next255')), [true]],
    'previous acknowledged flags' => [static fn (): mixed => array_unique(array_column($plan255()['next_row_window_rows_next255'], 'next_row_previous_acknowledged_next255')), [true]],
    'ready flags' => [static fn (): mixed => array_unique(array_column($plan255()['next_row_window_rows_next255'], 'next_row_ready_next255')), [true]],
    'receipt length first row' => [static fn (): mixed => strlen($plan255()['next_row_window_rows_next255'][0]['next_row_admission_receipt_next255']), 64],
    'ready tickets' => [static fn (): mixed => $plan255()['next_row_ready_tickets_next255'], $tickets255()],
    'blocked tickets empty' => [static fn (): mixed => $plan255()['next_row_blocked_tickets_next255'], []],
    'resume from start count' => [static fn (): mixed => $plan255()['next_row_resume_next255']['remaining_count'], 8],
    'resume from first ticket count' => [static fn (): mixed => $plan255(null, $tickets255()[0])['next_row_resume_next255']['remaining_count'], 7],
    'resume from first ticket next id' => [static fn (): mixed => $plan255(null, $tickets255()[0])['next_row_resume_next255']['rows'][0]['next_row_rowid_next255'], 5],
    'resume from last ticket exhausted' => [static fn (): mixed => $plan255(null, $tickets255()[7])['next_row_resume_next255']['exhausted'], true],
    'ack gap ready count' => [static fn (): mixed => $plan255(null, null, $ackGap255())['next_row_admission_summary_next255']['ready_count'], 6],
    'ack gap blocked count' => [static fn (): mixed => $plan255(null, null, $ackGap255())['next_row_admission_summary_next255']['blocked_count'], 2],
    'ack gap ready rowids' => [static fn (): mixed => $plan255(null, null, $ackGap255())['next_row_admission_summary_next255']['ready_rowids'], [7, 9, 10, 7, 5, 4]],
    'ack gap blocked rowids' => [static fn (): mixed => $plan255(null, null, $ackGap255())['next_row_admission_summary_next255']['blocked_rowids'], [5, 3]],
    'ack gap current blocked count' => [static fn (): mixed => $plan255(null, null, $ackGap255())['next_row_admission_summary_next255']['current_source_blocked_count'], 2],
    'ack gap next blocked count' => [static fn (): mixed => $plan255(null, null, $ackGap255())['next_row_admission_summary_next255']['next_source_blocked_count'], 0],
    'ack gap current ticket reason count' => [static fn (): mixed => $plan255(null, null, $ackGap255())['next_row_admission_summary_next255']['blocked_reasons']['current-returning-ticket-not-acknowledged-next255'], 1],
    'ack gap previous ticket reason count' => [static fn (): mixed => $plan255(null, null, $ackGap255())['next_row_admission_summary_next255']['blocked_reasons']['previous-returning-ticket-not-acknowledged-next255'], 1],
    'ack gap retry acknowledged true' => [static fn (): mixed => $plan255(null, null, $ackGap255())['next_row_admission_fence_next255']['all_retry_rows_acknowledged'], true],
    'ack gap current acknowledged false' => [static fn (): mixed => $plan255(null, null, $ackGap255())['next_row_admission_fence_next255']['all_current_rows_acknowledged'], false],
    'ack first only ready rowids' => [static fn (): mixed => $plan255(null, null, $ackFirstOnly255())['next_row_admission_summary_next255']['ready_rowids'], [7]],
    'ack first only blocked count' => [static fn (): mixed => $plan255(null, null, $ackFirstOnly255())['next_row_admission_summary_next255']['blocked_count'], 7],
    'ack first only next blocked count' => [static fn (): mixed => $plan255(null, null, $ackFirstOnly255())['next_row_admission_summary_next255']['next_source_blocked_count'], 5],
    'resume over ready subset count' => [static fn (): mixed => $plan255(null, $lastReadyWithGap255(), $ackGap255())['next_row_resume_next255']['remaining_count'], 1],
    'yield ack barrier inherited blocks next source rows' => [static fn (): mixed => $plan255(array_slice($required255(), 0, 2))['next_row_admission_summary_next255']['row_count'], 3],
    'yield ack barrier inherited ready count' => [static fn (): mixed => $plan255(array_slice($required255(), 0, 2))['next_row_admission_summary_next255']['ready_count'], 3],
    'yield ack barrier inherited all retry acknowledged true because absent' => [static fn (): mixed => $plan255(array_slice($required255(), 0, 2))['next_row_admission_fence_next255']['all_retry_rows_acknowledged'], true],
    'dependencies include next255' => [static fn (): mixed => in_array('sqlite-rowvalue-returning-window-next-row-admission-next255', $plan255()['dependencies_next255'], true), true],
    'dependencies include next251' => [static fn (): mixed => in_array('sqlite-rowvalue-returning-current-source-handoff-next251', $plan255()['dependencies_next255'], true), true],
    'dependency closure no new support' => [static fn (): mixed => str_contains($plan255()['dependency_closure_next255'], 'no new support component needed'), true],
    'non overlap mentions next251' => [static fn (): mixed => str_contains($plan255()['non_overlap_next255'], 'next251'), true],
    'non overlap mentions next250' => [static fn (): mixed => str_contains($plan255()['non_overlap_next255'], 'next250'), true],
    'bad resume ticket rejected' => [static fn (): mixed => $plan255(null, 'missing-ticket-next255'), InvalidArgumentException::class],
    'bad ready resume ticket rejected' => [static fn (): mixed => $plan255(null, $tickets255()[1], $ackGap255()), InvalidArgumentException::class],
    'empty acknowledged ticket rejected' => [static fn (): mixed => $plan255(null, null, ['']), InvalidArgumentException::class],
    'bad savepoint rejected by base' => [static fn (): mixed => SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNextPlan::executeWindowRowAdmission($tables255, [$yieldUpdate255], [$attemptUpdate255], [$retryUpdate255], $unique255, 'bad-name'), InvalidArgumentException::class],
];

$tests = [];
foreach ($cases255 as $name => [$callback, $expected]) {
    $tests['rowvalue update delete returning window current source next255 ' . $name] = static function (TestRunner $t) use ($callback, $expected): void {
        if (is_string($expected) && is_a($expected, Throwable::class, true)) {
            $t->throws($expected, $callback);
            return;
        }
        $t->same($expected, $callback());
    };
}

return $tests;
