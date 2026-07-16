<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNextPlan;

$rows261 = [
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
$tables261 = ['wp_options' => $rows261];
$unique261 = [['blog_id', 'option_name']];

$yieldUpdate261 = "UPDATE wp_options SET (status, option_value, bytes) = ('yield261', option_value || ':yield261', bytes + 30) WHERE (blog_id, option_name) IN ((2, 'pending_theme'), (3, 'rewrite_rules')) RETURNING option_id, blog_id, option_name, status, bytes ORDER BY option_id";
$yieldDelete261 = "DELETE FROM wp_options WHERE (blog_id, option_name) IN ((1, '_transient_feed')) RETURNING option_id, blog_id, option_name, status, bytes ORDER BY option_id";
$attemptUpdate261 = "UPDATE wp_options SET (status, option_value, bytes) = ('attempt261', option_value || ':attempt261', bytes + 5) WHERE (blog_id, option_name) IN ((2, 'pending_theme'), (3, 'rewrite_rules')) RETURNING option_id, blog_id, option_name, status, bytes ORDER BY option_id";
$attemptDelete261 = "DELETE FROM wp_options WHERE (blog_id, option_name) IN ((3, 'orphaned_cache')) RETURNING option_id, blog_id, option_name, status, bytes ORDER BY option_id";
$retryUpdate261 = "UPDATE wp_options SET (status, option_value, bytes) = ('retry261', option_value || ':retry261', bytes + 20) WHERE (blog_id, option_name) IN ((2, 'pending_theme'), (3, 'rewrite_rules'), (4, 'plugin_batch')) RETURNING option_id, blog_id, option_name, status, bytes ORDER BY option_id";
$retryDelete261 = "DELETE FROM wp_options WHERE (blog_id, option_name) IN ((1, '_transient_timeout_feed'), (4, 'home')) RETURNING option_id, blog_id, option_name, status, bytes ORDER BY option_id";

$plan261 = static fn (
    ?array $watermarks = null,
    ?string $resume = null,
    ?array $rowReceipts = null,
    ?array $ack = null,
    bool $requireNextReceipts = true,
    bool $requireNextWatermark = true,
): array => SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNextPlan::executeSourceSegmentWatermark(
    $tables261,
    [$yieldUpdate261, $yieldDelete261],
    [$attemptUpdate261, $attemptDelete261],
    [$retryUpdate261, $retryDelete261],
    $unique261,
    'app_settings_rowvalue_window_current_next261',
    'option_id',
    $ack,
    $resume,
    'wp-current-source-261',
    'wp-next-source-261',
    null,
    null,
    $rowReceipts,
    $requireNextReceipts,
    $watermarks,
    $requireNextWatermark,
);

$watermarks261 = static fn (): array => $plan261()['expected_source_window_watermarks_next261'];
$provided261 = static fn (): array => [
    'current' => $watermarks261()['current']['watermark_token'],
    'next' => $watermarks261()['next']['watermark_token'],
];
$badCurrentWatermark261 = static fn (): array => ['current' => 'bad-current-next261', 'next' => $provided261()['next']];
$badNextWatermark261 = static fn (): array => ['current' => $provided261()['current'], 'next' => 'bad-next-next261'];
$missingNextWatermark261 = static fn (): array => ['current' => $provided261()['current']];
$rowReceipts261 = static fn (): array => $plan261()['expected_row_receipts_next254'];
$badReceipt261 = static function () use ($rowReceipts261): array {
    $receipts = $rowReceipts261();
    $receipts[3]['receipt_token'] = 'bad-row-receipt-next261';
    return $receipts;
};
$required261 = static fn (): array => $plan261()['required_yield_tickets_next245'];
$missingAck261 = static fn (): array => array_slice($required261(), 0, 1);
$tickets261 = static fn (): array => $plan261()['published_tickets_next261'];
$firstTicket261 = static fn (): string => $tickets261()[0];
$lastCurrentTicket261 = static fn (): string => $tickets261()[2];
$firstNextTicket261 = static fn (): string => $plan261()['published_next_tickets_next261'][0];
$lastTicket261 = static fn (): string => $tickets261()[7];

$cases261 = [
    'status' => [static fn (): mixed => $plan261()['status'], 'rowvalue-update-delete-returning-window-current-source-next261'],
    'inherits next254 admitted state' => [static fn (): mixed => $plan261()['admission_state_next254'], 'current-source-next254-window-receipts-admitted'],
    'source window state ready' => [static fn (): mixed => $plan261()['source_window_state_next261'], 'current-source-window-watermarks-admit-next-source-next261'],
    'barrier savepoint' => [static fn (): mixed => $plan261()['source_window_barrier_next261']['savepoint'], 'app_settings_rowvalue_window_current_next261'],
    'barrier rowid column' => [static fn (): mixed => $plan261()['source_window_barrier_next261']['rowid_column'], 'option_id'],
    'barrier current epoch' => [static fn (): mixed => $plan261()['source_window_barrier_next261']['current_source_epoch'], 'wp-current-source-261'],
    'barrier next epoch' => [static fn (): mixed => $plan261()['source_window_barrier_next261']['next_source_epoch'], 'wp-next-source-261'],
    'admission ready true' => [static fn (): mixed => $plan261()['source_window_barrier_next261']['admission_ready'], true],
    'requires next watermark' => [static fn (): mixed => $plan261()['source_window_barrier_next261']['require_next_segment_watermark'], true],
    'current segment row count' => [static fn (): mixed => $plan261()['source_window_barrier_next261']['current_segment_row_count'], 3],
    'next segment row count' => [static fn (): mixed => $plan261()['source_window_barrier_next261']['next_segment_row_count'], 5],
    'published row count' => [static fn (): mixed => $plan261()['source_window_barrier_next261']['published_row_count'], 8],
    'published next row count' => [static fn (): mixed => $plan261()['source_window_barrier_next261']['published_next_row_count'], 5],
    'no blocked reasons' => [static fn (): mixed => $plan261()['source_window_barrier_next261']['blocked_reasons'], []],
    'barrier token sha256' => [static fn (): mixed => strlen($plan261()['source_window_barrier_next261']['barrier_token']), 64],
    'current watermark sha256' => [static fn (): mixed => strlen($watermarks261()['current']['watermark_token']), 64],
    'next watermark sha256' => [static fn (): mixed => strlen($watermarks261()['next']['watermark_token']), 64],
    'provided watermarks default expected' => [static fn (): mixed => $plan261()['provided_source_window_watermarks_next261'], $provided261()],
    'current watermark ids' => [static fn (): mixed => $watermarks261()['current']['row_ids'], [7, 5, 3]],
    'next watermark ids' => [static fn (): mixed => $watermarks261()['next']['row_ids'], [9, 10, 7, 5, 4]],
    'current watermark tickets' => [static fn (): mixed => $watermarks261()['current']['tickets'], array_slice($tickets261(), 0, 3)],
    'next watermark tickets' => [static fn (): mixed => $watermarks261()['next']['tickets'], array_slice($tickets261(), 3)],
    'current running final' => [static fn (): mixed => $watermarks261()['current']['running_bytes_final'], 88],
    'next running final' => [static fn (): mixed => $watermarks261()['next']['running_bytes_final'], 131],
    'current following total' => [static fn (): mixed => $watermarks261()['current']['following_bytes_total'], 61],
    'next following total' => [static fn (): mixed => $watermarks261()['next']['following_bytes_total'], 222],
    'published ids current then next' => [static fn (): mixed => array_column($plan261()['published_rows_next261'], 'option_id'), [7, 5, 3, 9, 10, 7, 5, 4]],
    'published tickets stable' => [static fn (): mixed => $plan261()['published_tickets_next261'], $plan261()['admitted_tickets_next254']],
    'published next ids' => [static fn (): mixed => array_column($plan261()['published_next_rows_next261'], 'option_id'), [9, 10, 7, 5, 4]],
    'published next tickets' => [static fn (): mixed => $plan261()['published_next_tickets_next261'], array_slice($tickets261(), 3)],
    'publication ordinals' => [static fn (): mixed => array_column($plan261()['published_rows_next261'], 'source_window_ordinal_next261'), [1, 2, 3, 4, 5, 6, 7, 8]],
    'publication segments' => [static fn (): mixed => array_column($plan261()['published_rows_next261'], 'source_window_segment_next261'), ['current', 'current', 'current', 'next', 'next', 'next', 'next', 'next']],
    'row token sha256' => [static fn (): mixed => strlen($plan261()['published_rows_next261'][0]['source_window_row_token_next261']), 64],
    'resume start count' => [static fn (): mixed => $plan261()['source_window_resume_next261']['remaining_count'], 8],
    'resume first count' => [static fn (): mixed => $plan261(null, $firstTicket261())['source_window_resume_next261']['remaining_count'], 7],
    'resume last current starts next' => [static fn (): mixed => $plan261(null, $lastCurrentTicket261())['source_window_resume_tickets_next261'][0], $firstNextTicket261()],
    'resume first next count' => [static fn (): mixed => $plan261(null, $firstNextTicket261())['source_window_resume_next261']['remaining_count'], 4],
    'resume last exhausted' => [static fn (): mixed => $plan261(null, $lastTicket261())['source_window_resume_next261']['exhausted'], true],
    'bad current watermark state held' => [static fn (): mixed => $plan261($badCurrentWatermark261())['source_window_state_next261'], 'current-source-window-watermarks-hold-next-source-next261'],
    'bad current watermark reason' => [static fn (): mixed => $plan261($badCurrentWatermark261())['source_window_barrier_next261']['blocked_reasons'], ['current-source-window-watermark-mismatch-next261']],
    'bad current watermark publishes current only' => [static fn (): mixed => array_column($plan261($badCurrentWatermark261())['published_rows_next261'], 'option_id'), [7, 5, 3]],
    'bad next watermark reason' => [static fn (): mixed => $plan261($badNextWatermark261())['source_window_barrier_next261']['blocked_reasons'], ['next-source-window-watermark-mismatch-next261']],
    'bad next watermark next count zero' => [static fn (): mixed => $plan261($badNextWatermark261())['source_window_barrier_next261']['published_next_row_count'], 0],
    'missing next watermark reason' => [static fn (): mixed => $plan261($missingNextWatermark261())['source_window_barrier_next261']['blocked_reasons'], ['next-source-window-watermark-mismatch-next261']],
    'optional next watermark reason' => [static fn (): mixed => $plan261($missingNextWatermark261(), null, null, null, true, false)['source_window_barrier_next261']['blocked_reasons'], ['next-source-window-watermark-not-required-next261']],
    'optional next watermark still holds next' => [static fn (): mixed => $plan261($missingNextWatermark261(), null, null, null, true, false)['source_window_barrier_next261']['published_next_row_count'], 0],
    'bad row receipt blocks admission' => [static fn (): mixed => $plan261(null, null, $badReceipt261())['source_window_barrier_next261']['blocked_reasons'], ['row-receipt-admission-not-ready-next261']],
    'bad row receipt publishes current only' => [static fn (): mixed => array_column($plan261(null, null, $badReceipt261())['published_rows_next261'], 'option_id'), [7, 5, 3]],
    'missing yield ack blocks admission' => [static fn (): mixed => $plan261(null, null, null, $missingAck261())['source_window_barrier_next261']['blocked_reasons'], ['row-receipt-admission-not-ready-next261']],
    'missing yield ack state held' => [static fn (): mixed => $plan261(null, null, null, $missingAck261())['source_window_state_next261'], 'current-source-window-watermarks-hold-next-source-next261'],
    'dependency includes watermark' => [static fn (): mixed => in_array('sqlite-returning-window-segment-watermark-next261', $plan261()['dependencies_next261'], true), true],
    'dependency includes application' => [static fn (): mixed => in_array('application-rowvalue-returning-window-source-watermark-next261', $plan261()['dependencies_next261'], true), true],
    'dependency closure no new support' => [static fn (): mixed => str_contains($plan261()['dependency_closure_next261'], 'no new support component needed'), true],
    'non overlap mentions next254' => [static fn (): mixed => str_contains($plan261()['non_overlap_next261'], 'next254'), true],
    'non overlap mentions next251' => [static fn (): mixed => str_contains($plan261()['non_overlap_next261'], 'next251'), true],
    'bad resume rejected' => [static fn (): mixed => $plan261(null, 'missing-ticket-next261'), InvalidArgumentException::class],
    'bad rowid column rejected' => [static fn (): mixed => SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNextPlan::executeSourceSegmentWatermark($tables261, [$yieldUpdate261], [$attemptUpdate261], [$retryUpdate261], $unique261, 'app_settings_rowvalue_window_current_next261', 'missing_id'), InvalidArgumentException::class],
];

$tests = [];
foreach ($cases261 as $name => [$callback, $expected]) {
    $tests['rowvalue update delete returning window current source next261 ' . $name] = static function (TestRunner $t) use ($callback, $expected): void {
        if (is_string($expected) && is_a($expected, Throwable::class, true)) {
            $t->throws($expected, $callback);
            return;
        }
        $t->same($expected, $callback());
    };
}

return $tests;
