<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNextPlan;

$rows259 = [
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
$tables259 = ['wp_options' => $rows259];
$unique259 = [['blog_id', 'option_name']];

$yieldUpdate259 = "UPDATE wp_options SET (status, option_value, bytes) = ('yield259', option_value || ':yield259', bytes + 30) WHERE (blog_id, option_name) IN ((2, 'pending_theme'), (3, 'rewrite_rules')) RETURNING option_id, blog_id, option_name, status, bytes ORDER BY option_id";
$yieldDelete259 = "DELETE FROM wp_options WHERE (blog_id, option_name) IN ((1, '_transient_feed')) RETURNING option_id, blog_id, option_name, status, bytes ORDER BY option_id";
$attemptUpdate259 = "UPDATE wp_options SET (status, option_value, bytes) = ('attempt259', option_value || ':attempt259', bytes + 5) WHERE (blog_id, option_name) IN ((2, 'pending_theme'), (3, 'rewrite_rules')) RETURNING option_id, blog_id, option_name, status, bytes ORDER BY option_id";
$attemptDelete259 = "DELETE FROM wp_options WHERE (blog_id, option_name) IN ((3, 'orphaned_cache')) RETURNING option_id, blog_id, option_name, status, bytes ORDER BY option_id";
$retryUpdate259 = "UPDATE wp_options SET (status, option_value, bytes) = ('retry259', option_value || ':retry259', bytes + 20) WHERE (blog_id, option_name) IN ((2, 'pending_theme'), (3, 'rewrite_rules'), (4, 'plugin_batch')) RETURNING option_id, blog_id, option_name, status, bytes ORDER BY option_id";
$retryDelete259 = "DELETE FROM wp_options WHERE (blog_id, option_name) IN ((1, '_transient_timeout_feed'), (4, 'home')) RETURNING option_id, blog_id, option_name, status, bytes ORDER BY option_id";

$plan259 = static fn (?array $ackYield = null, ?string $resume = null, ?array $ackNext = null, ?array $ackFrame = null, bool $requirePrevious = true): array => SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNextPlan::executeNext259(
    $tables259,
    [$yieldUpdate259, $yieldDelete259],
    [$attemptUpdate259, $attemptDelete259],
    [$retryUpdate259, $retryDelete259],
    $unique259,
    'wp_options_rowvalue_window_current_next259',
    'option_id',
    $ackYield,
    $resume,
    $ackNext,
    $ackFrame,
    $requirePrevious,
);

$tickets259 = static fn (): array => $plan259()['next_row_ready_tickets_next255'];
$requiredYield259 = static fn (): array => $plan259()['required_yield_tickets_next245'];
$ackFrameGap259 = static fn (): array => [$tickets259()[0], $tickets259()[2], $tickets259()[3], $tickets259()[4], $tickets259()[5], $tickets259()[6], $tickets259()[7]];
$ackFrameFirstOnly259 = static fn (): array => [$tickets259()[0]];
$ackNextGap259 = static fn (): array => [$tickets259()[0], $tickets259()[2], $tickets259()[3], $tickets259()[4], $tickets259()[5], $tickets259()[6], $tickets259()[7]];
$lastReadyWithGap259 = static fn (): string => $plan259(null, null, null, $ackFrameGap259())['current_row_frame_ready_tickets_next259'][4];

$cases259 = [
    'status' => [static fn (): mixed => $plan259()['status'], 'rowvalue-update-delete-returning-window-current-source-next259'],
    'inherits next255 admission' => [static fn (): mixed => $plan259()['next_row_admission_next255'], true],
    'current row frame admission flag' => [static fn (): mixed => $plan259()['current_row_frame_admission_next259'], true],
    'summary row count' => [static fn (): mixed => $plan259()['current_row_frame_summary_next259']['row_count'], 8],
    'summary ready count' => [static fn (): mixed => $plan259()['current_row_frame_summary_next259']['ready_count'], 8],
    'summary blocked count' => [static fn (): mixed => $plan259()['current_row_frame_summary_next259']['blocked_count'], 0],
    'summary current ready count' => [static fn (): mixed => $plan259()['current_row_frame_summary_next259']['current_source_ready_count'], 3],
    'summary next ready count' => [static fn (): mixed => $plan259()['current_row_frame_summary_next259']['next_source_ready_count'], 5],
    'summary transition count' => [static fn (): mixed => $plan259()['current_row_frame_summary_next259']['transition_count'], 1],
    'summary ready rowids' => [static fn (): mixed => $plan259()['current_row_frame_summary_next259']['ready_rowids'], [7, 5, 3, 9, 10, 7, 5, 4]],
    'summary blocked rowids empty' => [static fn (): mixed => $plan259()['current_row_frame_summary_next259']['blocked_rowids'], []],
    'summary blocked reasons empty' => [static fn (): mixed => $plan259()['current_row_frame_summary_next259']['blocked_reasons'], []],
    'fence savepoint' => [static fn (): mixed => $plan259()['current_row_frame_fence_next259']['savepoint'], 'wp_options_rowvalue_window_current_next259'],
    'fence source state' => [static fn (): mixed => $plan259()['current_row_frame_fence_next259']['source_handoff_state'], 'current-source-drained-next-source-digest-ready-next251'],
    'fence next row ready count' => [static fn (): mixed => $plan259()['current_row_frame_fence_next259']['next_row_ready_count'], 8],
    'fence next row blocked count' => [static fn (): mixed => $plan259()['current_row_frame_fence_next259']['next_row_blocked_count'], 0],
    'fence mode' => [static fn (): mixed => $plan259()['current_row_frame_fence_next259']['frame_mode'], 'RETURNING CURRENT ROW frame closes before following row is visible'],
    'fence previous required' => [static fn (): mixed => $plan259()['current_row_frame_fence_next259']['require_previous_frame_close'], true],
    'fence row count' => [static fn (): mixed => $plan259()['current_row_frame_fence_next259']['row_count'], 8],
    'fence ready count' => [static fn (): mixed => $plan259()['current_row_frame_fence_next259']['ready_count'], 8],
    'fence blocked count' => [static fn (): mixed => $plan259()['current_row_frame_fence_next259']['blocked_count'], 0],
    'fence transition count' => [static fn (): mixed => $plan259()['current_row_frame_fence_next259']['transition_count'], 1],
    'fence all current acknowledged' => [static fn (): mixed => $plan259()['current_row_frame_fence_next259']['all_current_frames_acknowledged'], true],
    'fence all next acknowledged' => [static fn (): mixed => $plan259()['current_row_frame_fence_next259']['all_next_frames_acknowledged'], true],
    'fence ready digest sha256' => [static fn (): mixed => strlen($plan259()['current_row_frame_fence_next259']['ready_digest']), 64],
    'fence blocked digest sha256' => [static fn (): mixed => strlen($plan259()['current_row_frame_fence_next259']['blocked_digest']), 64],
    'rows tickets preserved' => [static fn (): mixed => array_column($plan259()['current_row_frame_rows_next259'], 'ticket'), $tickets259()],
    'rows ordinals' => [static fn (): mixed => array_column($plan259()['current_row_frame_rows_next259'], 'current_frame_ordinal_next259'), [1, 2, 3, 4, 5, 6, 7, 8]],
    'rows ids' => [static fn (): mixed => array_column($plan259()['current_row_frame_rows_next259'], 'current_frame_rowid_next259'), [7, 5, 3, 9, 10, 7, 5, 4]],
    'rows epochs' => [static fn (): mixed => array_column($plan259()['current_row_frame_rows_next259'], 'current_frame_source_epoch_next259'), ['wp-current-source-251', 'wp-current-source-251', 'wp-current-source-251', 'wp-next-source-251', 'wp-next-source-251', 'wp-next-source-251', 'wp-next-source-251', 'wp-next-source-251']],
    'previous tickets' => [static fn (): mixed => array_column($plan259()['current_row_frame_rows_next259'], 'current_frame_previous_ticket_next259'), [null, $tickets259()[0], $tickets259()[1], $tickets259()[2], $tickets259()[3], $tickets259()[4], $tickets259()[5], $tickets259()[6]]],
    'next tickets' => [static fn (): mixed => array_column($plan259()['current_row_frame_rows_next259'], 'current_frame_next_ticket_next259'), [$tickets259()[1], $tickets259()[2], $tickets259()[3], $tickets259()[4], $tickets259()[5], $tickets259()[6], $tickets259()[7], null]],
    'current acknowledged flags' => [static fn (): mixed => array_unique(array_column($plan259()['current_row_frame_rows_next259'], 'current_frame_current_acknowledged_next259')), [true]],
    'previous closed flags' => [static fn (): mixed => array_unique(array_column($plan259()['current_row_frame_rows_next259'], 'current_frame_previous_closed_next259')), [true]],
    'next row ready flags' => [static fn (): mixed => array_unique(array_column($plan259()['current_row_frame_rows_next259'], 'current_frame_next_row_ready_next259')), [true]],
    'ready flags' => [static fn (): mixed => array_unique(array_column($plan259()['current_row_frame_rows_next259'], 'current_frame_ready_next259')), [true]],
    'transition flags' => [static fn (): mixed => array_column($plan259()['current_row_frame_rows_next259'], 'current_frame_crosses_source_epoch_next259'), [false, false, true, false, false, false, false, false]],
    'receipt length first row' => [static fn (): mixed => strlen($plan259()['current_row_frame_rows_next259'][0]['current_frame_receipt_next259']), 64],
    'ready tickets' => [static fn (): mixed => $plan259()['current_row_frame_ready_tickets_next259'], $tickets259()],
    'blocked tickets empty' => [static fn (): mixed => $plan259()['current_row_frame_blocked_tickets_next259'], []],
    'resume from start count' => [static fn (): mixed => $plan259()['current_row_frame_resume_next259']['remaining_count'], 8],
    'resume from first ticket count' => [static fn (): mixed => $plan259(null, $tickets259()[0])['current_row_frame_resume_next259']['remaining_count'], 7],
    'resume from first ticket next id' => [static fn (): mixed => $plan259(null, $tickets259()[0])['current_row_frame_resume_next259']['rows'][0]['current_frame_rowid_next259'], 5],
    'resume from last ticket exhausted' => [static fn (): mixed => $plan259(null, $tickets259()[7])['current_row_frame_resume_next259']['exhausted'], true],
    'frame ack gap ready count' => [static fn (): mixed => $plan259(null, null, null, $ackFrameGap259())['current_row_frame_summary_next259']['ready_count'], 6],
    'frame ack gap blocked count' => [static fn (): mixed => $plan259(null, null, null, $ackFrameGap259())['current_row_frame_summary_next259']['blocked_count'], 2],
    'frame ack gap ready rowids' => [static fn (): mixed => $plan259(null, null, null, $ackFrameGap259())['current_row_frame_summary_next259']['ready_rowids'], [7, 9, 10, 7, 5, 4]],
    'frame ack gap blocked rowids' => [static fn (): mixed => $plan259(null, null, null, $ackFrameGap259())['current_row_frame_summary_next259']['blocked_rowids'], [5, 3]],
    'frame ack gap current blocked count' => [static fn (): mixed => $plan259(null, null, null, $ackFrameGap259())['current_row_frame_summary_next259']['current_source_blocked_count'], 2],
    'frame ack gap next blocked count' => [static fn (): mixed => $plan259(null, null, null, $ackFrameGap259())['current_row_frame_summary_next259']['next_source_blocked_count'], 0],
    'frame ack gap current reason count' => [static fn (): mixed => $plan259(null, null, null, $ackFrameGap259())['current_row_frame_summary_next259']['blocked_reasons']['current-row-frame-not-acknowledged-next259'], 1],
    'frame ack gap previous reason count' => [static fn (): mixed => $plan259(null, null, null, $ackFrameGap259())['current_row_frame_summary_next259']['blocked_reasons']['previous-row-frame-not-closed-next259'], 1],
    'frame ack gap all current false' => [static fn (): mixed => $plan259(null, null, null, $ackFrameGap259())['current_row_frame_fence_next259']['all_current_frames_acknowledged'], false],
    'frame ack gap all next true' => [static fn (): mixed => $plan259(null, null, null, $ackFrameGap259())['current_row_frame_fence_next259']['all_next_frames_acknowledged'], true],
    'frame first only ready rowids' => [static fn (): mixed => $plan259(null, null, null, $ackFrameFirstOnly259())['current_row_frame_summary_next259']['ready_rowids'], [7]],
    'frame first only blocked count' => [static fn (): mixed => $plan259(null, null, null, $ackFrameFirstOnly259())['current_row_frame_summary_next259']['blocked_count'], 7],
    'frame first only next blocked count' => [static fn (): mixed => $plan259(null, null, null, $ackFrameFirstOnly259())['current_row_frame_summary_next259']['next_source_blocked_count'], 5],
    'previous close optional ready count' => [static fn (): mixed => $plan259(null, null, null, $ackFrameGap259(), false)['current_row_frame_summary_next259']['ready_count'], 7],
    'previous close optional reason count' => [static fn (): mixed => $plan259(null, null, null, $ackFrameGap259(), false)['current_row_frame_summary_next259']['blocked_reasons'], ['current-row-frame-not-acknowledged-next259' => 1]],
    'next row gap blocks same two rows' => [static fn (): mixed => $plan259(null, null, $ackNextGap259())['current_row_frame_summary_next259']['blocked_rowids'], [5, 3]],
    'next row gap reason propagated' => [static fn (): mixed => $plan259(null, null, $ackNextGap259())['current_row_frame_summary_next259']['blocked_reasons']['next-row-not-ready-next259'], 2],
    'resume over ready subset count' => [static fn (): mixed => $plan259(null, $lastReadyWithGap259(), null, $ackFrameGap259())['current_row_frame_resume_next259']['remaining_count'], 1],
    'yield ack barrier inherited row count' => [static fn (): mixed => $plan259(array_slice($requiredYield259(), 0, 2))['current_row_frame_summary_next259']['row_count'], 3],
    'yield ack barrier inherited ready count' => [static fn (): mixed => $plan259(array_slice($requiredYield259(), 0, 2))['current_row_frame_summary_next259']['ready_count'], 3],
    'yield ack barrier inherited transition count' => [static fn (): mixed => $plan259(array_slice($requiredYield259(), 0, 2))['current_row_frame_summary_next259']['transition_count'], 0],
    'dependencies include next259' => [static fn (): mixed => in_array('sqlite-rowvalue-returning-window-current-row-frame-next259', $plan259()['dependencies_next259'], true), true],
    'dependencies include next255' => [static fn (): mixed => in_array('sqlite-rowvalue-returning-window-next-row-admission-next255', $plan259()['dependencies_next259'], true), true],
    'dependency closure no new support' => [static fn (): mixed => str_contains($plan259()['dependency_closure_next259'], 'no new support component needed'), true],
    'non overlap mentions next255' => [static fn (): mixed => str_contains($plan259()['non_overlap_next259'], 'next255'), true],
    'non overlap mentions next251' => [static fn (): mixed => str_contains($plan259()['non_overlap_next259'], 'next251'), true],
    'bad resume ticket rejected' => [static fn (): mixed => $plan259(null, 'missing-ticket-next259'), InvalidArgumentException::class],
    'bad ready resume ticket rejected' => [static fn (): mixed => $plan259(null, $tickets259()[1], null, $ackFrameGap259()), InvalidArgumentException::class],
    'empty frame ticket rejected' => [static fn (): mixed => $plan259(null, null, null, ['']), InvalidArgumentException::class],
    'bad savepoint rejected by base' => [static fn (): mixed => SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNextPlan::executeNext259($tables259, [$yieldUpdate259], [$attemptUpdate259], [$retryUpdate259], $unique259, 'bad-name'), InvalidArgumentException::class],
];

$tests = [];
foreach ($cases259 as $name => [$callback, $expected]) {
    $tests['rowvalue update delete returning window current source next259 ' . $name] = static function (TestRunner $t) use ($callback, $expected): void {
        if (is_string($expected) && is_a($expected, Throwable::class, true)) {
            $t->throws($expected, $callback);
            return;
        }
        $t->same($expected, $callback());
    };
}

return $tests;
