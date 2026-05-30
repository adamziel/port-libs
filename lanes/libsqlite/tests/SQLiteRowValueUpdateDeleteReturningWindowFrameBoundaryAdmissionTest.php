<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNextPlan;

$rows260 = [
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
$tables260 = ['wp_options' => $rows260];
$unique260 = [['blog_id', 'option_name']];

$yieldUpdate260 = "UPDATE wp_options SET (status, option_value, bytes) = ('yield260', option_value || ':yield260', bytes + 30) WHERE (blog_id, option_name) IN ((2, 'pending_theme'), (3, 'rewrite_rules')) RETURNING option_id, blog_id, option_name, status, bytes ORDER BY option_id";
$yieldDelete260 = "DELETE FROM wp_options WHERE (blog_id, option_name) IN ((1, '_transient_feed')) RETURNING option_id, blog_id, option_name, status, bytes ORDER BY option_id";
$attemptUpdate260 = "UPDATE wp_options SET (status, option_value, bytes) = ('attempt260', option_value || ':attempt260', bytes + 5) WHERE (blog_id, option_name) IN ((2, 'pending_theme'), (3, 'rewrite_rules')) RETURNING option_id, blog_id, option_name, status, bytes ORDER BY option_id";
$attemptDelete260 = "DELETE FROM wp_options WHERE (blog_id, option_name) IN ((3, 'orphaned_cache')) RETURNING option_id, blog_id, option_name, status, bytes ORDER BY option_id";
$retryUpdate260 = "UPDATE wp_options SET (status, option_value, bytes) = ('retry260', option_value || ':retry260', bytes + 20) WHERE (blog_id, option_name) IN ((2, 'pending_theme'), (3, 'rewrite_rules'), (4, 'plugin_batch')) RETURNING option_id, blog_id, option_name, status, bytes ORDER BY option_id";
$retryDelete260 = "DELETE FROM wp_options WHERE (blog_id, option_name) IN ((1, '_transient_timeout_feed'), (4, 'home')) RETURNING option_id, blog_id, option_name, status, bytes ORDER BY option_id";

$plan260 = static fn (?array $ackYield = null, ?string $resume = null, ?array $ackNext = null, ?array $ackBoundary = null): array => SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNextPlan::executeFrameBoundaryAdmission(
    $tables260,
    [$yieldUpdate260, $yieldDelete260],
    [$attemptUpdate260, $attemptDelete260],
    [$retryUpdate260, $retryDelete260],
    $unique260,
    'app_settings_rowvalue_window_current_next260',
    'option_id',
    $ackYield,
    $resume,
    $ackNext,
    $ackBoundary,
);

$tickets260 = static fn (): array => $plan260()['source_handoff_tickets_next251'];
$mixedTickets260 = static fn (): array => $plan260()['boundary_mixed_tickets_next260'];
$ackBoundaryFirst260 = static fn (): array => [$mixedTickets260()[0]];
$ackBoundarySecond260 = static fn (): array => [$mixedTickets260()[1]];
$ackNextGap260 = static fn (): array => [$tickets260()[0], $tickets260()[2], $tickets260()[3], $tickets260()[4], $tickets260()[5], $tickets260()[6], $tickets260()[7]];

$cases260 = [
    'status' => [static fn (): mixed => $plan260()['status'], 'rowvalue-update-delete-returning-window-current-source-next260'],
    'inherits next255 admission' => [static fn (): mixed => $plan260()['next_row_admission_next255'], true],
    'boundary admission flag' => [static fn (): mixed => $plan260()['boundary_admission_next260'], true],
    'summary row count' => [static fn (): mixed => $plan260()['boundary_summary_next260']['row_count'], 8],
    'summary ready count' => [static fn (): mixed => $plan260()['boundary_summary_next260']['ready_count'], 8],
    'summary blocked count' => [static fn (): mixed => $plan260()['boundary_summary_next260']['blocked_count'], 0],
    'summary mixed count' => [static fn (): mixed => $plan260()['boundary_summary_next260']['mixed_boundary_count'], 2],
    'summary mixed ready count' => [static fn (): mixed => $plan260()['boundary_summary_next260']['mixed_ready_count'], 2],
    'summary mixed blocked count' => [static fn (): mixed => $plan260()['boundary_summary_next260']['mixed_blocked_count'], 0],
    'summary ready rowids' => [static fn (): mixed => $plan260()['boundary_summary_next260']['ready_rowids'], [7, 5, 3, 9, 10, 7, 5, 4]],
    'summary blocked rowids empty' => [static fn (): mixed => $plan260()['boundary_summary_next260']['blocked_rowids'], []],
    'summary mixed rowids' => [static fn (): mixed => $plan260()['boundary_summary_next260']['mixed_rowids'], [3, 9]],
    'summary blocked reasons empty' => [static fn (): mixed => $plan260()['boundary_summary_next260']['blocked_reasons'], []],
    'fence savepoint' => [static fn (): mixed => $plan260()['boundary_fence_next260']['savepoint'], 'app_settings_rowvalue_window_current_next260'],
    'fence source handoff state' => [static fn (): mixed => $plan260()['boundary_fence_next260']['source_handoff_state'], 'current-source-drained-next-source-digest-ready-next251'],
    'fence next row ready count' => [static fn (): mixed => $plan260()['boundary_fence_next260']['next_row_ready_count'], 8],
    'fence row count' => [static fn (): mixed => $plan260()['boundary_fence_next260']['row_count'], 8],
    'fence mixed count' => [static fn (): mixed => $plan260()['boundary_fence_next260']['mixed_boundary_count'], 2],
    'fence ready count' => [static fn (): mixed => $plan260()['boundary_fence_next260']['ready_count'], 8],
    'fence blocked count' => [static fn (): mixed => $plan260()['boundary_fence_next260']['blocked_count'], 0],
    'fence released' => [static fn (): mixed => $plan260()['boundary_fence_next260']['current_to_next_boundary_released'], true],
    'fence digest length' => [static fn (): mixed => strlen($plan260()['boundary_fence_next260']['boundary_digest']), 64],
    'fence mixed digest length' => [static fn (): mixed => strlen($plan260()['boundary_fence_next260']['mixed_boundary_digest']), 64],
    'ready tickets' => [static fn (): mixed => $plan260()['boundary_ready_tickets_next260'], $tickets260()],
    'blocked tickets empty' => [static fn (): mixed => $plan260()['boundary_blocked_tickets_next260'], []],
    'mixed tickets' => [static fn (): mixed => $plan260()['boundary_mixed_tickets_next260'], [$tickets260()[2], $tickets260()[3]]],
    'row ordinals' => [static fn (): mixed => array_column($plan260()['boundary_window_rows_next260'], 'boundary_ordinal_next260'), [1, 2, 3, 4, 5, 6, 7, 8]],
    'row ids' => [static fn (): mixed => array_column($plan260()['boundary_window_rows_next260'], 'boundary_rowid_next260'), [7, 5, 3, 9, 10, 7, 5, 4]],
    'row boundary flags' => [static fn (): mixed => array_column($plan260()['boundary_window_rows_next260'], 'boundary_crosses_source_next260'), [false, false, true, true, false, false, false, false]],
    'row ack flags' => [static fn (): mixed => array_unique(array_column($plan260()['boundary_window_rows_next260'], 'boundary_ticket_acknowledged_next260')), [true]],
    'row next ready flags' => [static fn (): mixed => array_unique(array_column($plan260()['boundary_window_rows_next260'], 'boundary_next_row_ready_next260')), [true]],
    'row ready flags' => [static fn (): mixed => array_unique(array_column($plan260()['boundary_window_rows_next260'], 'boundary_ready_next260')), [true]],
    'first frame tickets' => [static fn (): mixed => $plan260()['boundary_window_rows_next260'][0]['boundary_frame_tickets_next260'], [$tickets260()[0], $tickets260()[1]]],
    'middle current frame tickets' => [static fn (): mixed => $plan260()['boundary_window_rows_next260'][2]['boundary_frame_tickets_next260'], [$tickets260()[1], $tickets260()[2], $tickets260()[3]]],
    'first next frame tickets' => [static fn (): mixed => $plan260()['boundary_window_rows_next260'][3]['boundary_frame_tickets_next260'], [$tickets260()[2], $tickets260()[3], $tickets260()[4]]],
    'last frame tickets' => [static fn (): mixed => $plan260()['boundary_window_rows_next260'][7]['boundary_frame_tickets_next260'], [$tickets260()[6], $tickets260()[7]]],
    'current frame epochs' => [static fn (): mixed => $plan260()['boundary_window_rows_next260'][1]['boundary_frame_epochs_next260'], ['wp-current-source-251', 'wp-current-source-251', 'wp-current-source-251']],
    'mixed current frame epochs' => [static fn (): mixed => $plan260()['boundary_window_rows_next260'][2]['boundary_frame_epochs_next260'], ['wp-current-source-251', 'wp-current-source-251', 'wp-next-source-251']],
    'mixed next frame epochs' => [static fn (): mixed => $plan260()['boundary_window_rows_next260'][3]['boundary_frame_epochs_next260'], ['wp-current-source-251', 'wp-next-source-251', 'wp-next-source-251']],
    'next frame epochs' => [static fn (): mixed => $plan260()['boundary_window_rows_next260'][4]['boundary_frame_epochs_next260'], ['wp-next-source-251', 'wp-next-source-251', 'wp-next-source-251']],
    'receipt length row one' => [static fn (): mixed => strlen($plan260()['boundary_window_rows_next260'][0]['boundary_receipt_next260']), 64],
    'receipt length mixed row' => [static fn (): mixed => strlen($plan260()['boundary_window_rows_next260'][2]['boundary_receipt_next260']), 64],
    'resume from start count' => [static fn (): mixed => $plan260()['boundary_resume_next260']['remaining_count'], 8],
    'resume from first ticket count' => [static fn (): mixed => $plan260(null, $tickets260()[0])['boundary_resume_next260']['remaining_count'], 7],
    'resume from first ticket next id' => [static fn (): mixed => $plan260(null, $tickets260()[0])['boundary_resume_next260']['rows'][0]['boundary_rowid_next260'], 5],
    'resume from last ticket exhausted' => [static fn (): mixed => $plan260(null, $tickets260()[7])['boundary_resume_next260']['exhausted'], true],
    'ack first boundary ready count' => [static fn (): mixed => $plan260(null, null, null, $ackBoundaryFirst260())['boundary_summary_next260']['ready_count'], 7],
    'ack first boundary blocked rowids' => [static fn (): mixed => $plan260(null, null, null, $ackBoundaryFirst260())['boundary_summary_next260']['blocked_rowids'], [9]],
    'ack first boundary mixed blocked count' => [static fn (): mixed => $plan260(null, null, null, $ackBoundaryFirst260())['boundary_summary_next260']['mixed_blocked_count'], 1],
    'ack first boundary blocked reason' => [static fn (): mixed => $plan260(null, null, null, $ackBoundaryFirst260())['boundary_summary_next260']['blocked_reasons']['source-boundary-ticket-not-acknowledged-next260'], 1],
    'ack first boundary fence held' => [static fn (): mixed => $plan260(null, null, null, $ackBoundaryFirst260())['boundary_fence_next260']['current_to_next_boundary_released'], false],
    'ack second boundary ready count' => [static fn (): mixed => $plan260(null, null, null, $ackBoundarySecond260())['boundary_summary_next260']['ready_count'], 7],
    'ack second boundary blocked rowids' => [static fn (): mixed => $plan260(null, null, null, $ackBoundarySecond260())['boundary_summary_next260']['blocked_rowids'], [3]],
    'next row gap ready count' => [static fn (): mixed => $plan260(null, null, $ackNextGap260())['boundary_summary_next260']['ready_count'], 6],
    'next row gap blocked rowids' => [static fn (): mixed => $plan260(null, null, $ackNextGap260())['boundary_summary_next260']['blocked_rowids'], [5, 3]],
    'next row gap reason count' => [static fn (): mixed => $plan260(null, null, $ackNextGap260())['boundary_summary_next260']['blocked_reasons']['next-row-not-admitted-before-boundary-next260'], 2],
    'next row gap mixed blocked count' => [static fn (): mixed => $plan260(null, null, $ackNextGap260())['boundary_summary_next260']['mixed_blocked_count'], 1],
    'resume over boundary subset count' => [static fn (): mixed => $plan260(null, $tickets260()[0], null, $ackBoundaryFirst260())['boundary_resume_next260']['remaining_count'], 6],
    'dependencies include next260' => [static fn (): mixed => in_array('sqlite-rowvalue-returning-window-boundary-current-source-next260', $plan260()['dependencies_next260'], true), true],
    'dependencies include next255' => [static fn (): mixed => in_array('sqlite-rowvalue-returning-window-next-row-admission-next255', $plan260()['dependencies_next260'], true), true],
    'dependency closure no support' => [static fn (): mixed => str_contains($plan260()['dependency_closure_next260'], 'no new support component needed'), true],
    'non overlap mentions next255' => [static fn (): mixed => str_contains($plan260()['non_overlap_next260'], 'next255'), true],
    'non overlap mentions json avoided' => [static fn (): mixed => str_contains($plan260()['non_overlap_next260'], 'JSON table'), true],
    'bad resume ticket rejected' => [static fn (): mixed => $plan260(null, 'missing-ticket-next260'), InvalidArgumentException::class],
    'blocked resume ticket rejected' => [static fn (): mixed => $plan260(null, $tickets260()[3], null, $ackBoundaryFirst260()), InvalidArgumentException::class],
    'empty boundary ticket rejected' => [static fn (): mixed => $plan260(null, null, null, ['']), InvalidArgumentException::class],
    'bad savepoint rejected by base' => [static fn (): mixed => SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNextPlan::executeFrameBoundaryAdmission($tables260, [$yieldUpdate260], [$attemptUpdate260], [$retryUpdate260], $unique260, 'bad-name'), InvalidArgumentException::class],
];

$tests = [];
foreach ($cases260 as $name => [$callback, $expected]) {
    $tests['rowvalue update delete returning window current source next260 ' . $name] = static function (TestRunner $t) use ($callback, $expected): void {
        if (is_string($expected) && is_a($expected, Throwable::class, true)) {
            $t->throws($expected, $callback);
            return;
        }
        $t->same($expected, $callback());
    };
}

return $tests;
