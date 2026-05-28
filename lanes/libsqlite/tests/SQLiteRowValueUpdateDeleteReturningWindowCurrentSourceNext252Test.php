<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNext252Plan;

$rows252 = [
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
$tables252 = ['wp_options' => $rows252];
$unique252 = [['blog_id', 'option_name']];

$yieldUpdate252 = "UPDATE wp_options SET (status, option_value, bytes) = ('yield252', option_value || ':yield252', bytes + 30) WHERE (blog_id, option_name) IN ((2, 'pending_theme'), (3, 'rewrite_rules')) RETURNING option_id, blog_id, option_name, status, bytes ORDER BY option_id";
$yieldDelete252 = "DELETE FROM wp_options WHERE (blog_id, option_name) IN ((1, '_transient_feed')) RETURNING option_id, blog_id, option_name, status, bytes ORDER BY option_id";
$attemptUpdate252 = "UPDATE wp_options SET (status, option_value, bytes) = ('attempt252', option_value || ':attempt252', bytes + 5) WHERE (blog_id, option_name) IN ((2, 'pending_theme'), (3, 'rewrite_rules')) RETURNING option_id, blog_id, option_name, status, bytes ORDER BY option_id";
$attemptDelete252 = "DELETE FROM wp_options WHERE (blog_id, option_name) IN ((3, 'orphaned_cache')) RETURNING option_id, blog_id, option_name, status, bytes ORDER BY option_id";
$retryUpdate252 = "UPDATE wp_options SET (status, option_value, bytes) = ('retry252', option_value || ':retry252', bytes + 20) WHERE (blog_id, option_name) IN ((2, 'pending_theme'), (3, 'rewrite_rules'), (4, 'plugin_batch')) RETURNING option_id, blog_id, option_name, status, bytes ORDER BY option_id";
$retryDelete252 = "DELETE FROM wp_options WHERE (blog_id, option_name) IN ((1, '_transient_timeout_feed'), (4, 'home')) RETURNING option_id, blog_id, option_name, status, bytes ORDER BY option_id";

$plan252 = static fn (?array $ack = null, ?string $resume = null): array => SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNext252Plan::execute(
    $tables252,
    [$yieldUpdate252, $yieldDelete252],
    [$attemptUpdate252, $attemptDelete252],
    [$retryUpdate252, $retryDelete252],
    $unique252,
    'wp_options_rowvalue_window_current_next252',
    'option_id',
    $ack,
    $resume,
);

$required252 = static fn (): array => $plan252()['required_yield_tickets_next245'];
$firstYield252 = static fn (): string => $required252()[0];
$lastYield252 = static fn (): string => $required252()[2];
$firstRetry252 = static fn (): string => $plan252()['next_source_first_ticket_next252'];
$lastRetry252 = static fn (): string => $plan252()['retry_publication_rows_next248'][4]['ticket'];
$missingAck252 = static fn (): array => array_slice($required252(), 0, 2);
$unexpectedAck252 = static fn (): array => [...$required252(), 'unexpected:window:next252'];

$cases252 = [
    'status' => [static fn (): mixed => $plan252()['status'], 'rowvalue-update-delete-returning-window-current-source-next252'],
    'inherits next248 status before merge' => [static fn (): mixed => $plan252()['publication_state_next248'], 'current-source-yield-complete-next-source-resumable-next248'],
    'window count all rows' => [static fn (): mixed => count($plan252()['current_source_publication_windows_next252']), 8],
    'current count' => [static fn (): mixed => $plan252()['current_source_window_count_next252'], 3],
    'retry count' => [static fn (): mixed => $plan252()['next_source_window_count_next252'], 5],
    'high water ticket is last yield' => [static fn (): mixed => $plan252()['current_source_high_water_ticket_next252'], $lastYield252()],
    'first retry ticket' => [static fn (): mixed => $plan252()['next_source_first_ticket_next252'], $firstRetry252()],
    'first retry ordinal' => [static fn (): mixed => $plan252()['next_source_first_ordinal_next252'], 4],
    'row numbers monotonic' => [static fn (): mixed => array_column($plan252()['current_source_publication_windows_next252'], 'window_row_number_next252'), [1, 2, 3, 4, 5, 6, 7, 8]],
    'partition labels' => [static fn (): mixed => array_column($plan252()['current_source_publication_windows_next252'], 'window_partition_next252'), ['current-yield', 'current-yield', 'current-yield', 'next-retry', 'next-retry', 'next-retry', 'next-retry', 'next-retry']],
    'partition row numbers' => [static fn (): mixed => array_column($plan252()['current_source_publication_windows_next252'], 'window_partition_row_number_next252'), [1, 2, 3, 1, 2, 3, 4, 5]],
    'total rows repeated' => [static fn (): mixed => array_unique(array_column($plan252()['current_source_publication_windows_next252'], 'window_total_rows_next252')), [8]],
    'boundaries' => [static fn (): mixed => array_column($plan252()['current_source_publication_windows_next252'], 'window_boundary_next252'), ['first-row', 'middle-row', 'middle-row', 'middle-row', 'middle-row', 'middle-row', 'middle-row', 'last-row']],
    'current booleans' => [static fn (): mixed => array_column($plan252()['current_source_publication_windows_next252'], 'window_is_current_source_next252'), [true, true, true, false, false, false, false, false]],
    'retry booleans' => [static fn (): mixed => array_column($plan252()['current_source_publication_windows_next252'], 'window_is_next_source_next252'), [false, false, false, true, true, true, true, true]],
    'next source visible mirrors sequence' => [static fn (): mixed => array_column($plan252()['current_source_publication_windows_next252'], 'next_source_visible_next248'), [false, false, false, true, true, true, true, true]],
    'current complete before retry rows' => [static fn (): mixed => array_column($plan252()['current_source_publication_windows_next252'], 'window_current_complete_before_row_next252'), [false, false, false, true, true, true, true, true]],
    'high water progression' => [static fn (): mixed => array_column($plan252()['current_source_publication_windows_next252'], 'window_current_source_high_water_ticket_next252'), [$required252()[0], $required252()[1], $required252()[2], $required252()[2], $required252()[2], $required252()[2], $required252()[2], $required252()[2]]],
    'first retry ordinal progression' => [static fn (): mixed => array_column($plan252()['current_source_publication_windows_next252'], 'window_next_source_first_ordinal_next252'), [null, null, null, 4, 4, 4, 4, 4]],
    'previous ticket first null' => [static fn (): mixed => $plan252()['current_source_publication_windows_next252'][0]['window_previous_ticket_next252'], null],
    'previous ticket fourth is high water' => [static fn (): mixed => $plan252()['current_source_publication_windows_next252'][3]['window_previous_ticket_next252'], $lastYield252()],
    'next ticket third is first retry' => [static fn (): mixed => $plan252()['current_source_publication_windows_next252'][2]['window_next_ticket_next252'], $firstRetry252()],
    'next ticket last null' => [static fn (): mixed => $plan252()['current_source_publication_windows_next252'][7]['window_next_ticket_next252'], null],
    'cursor digest length' => [static fn (): mixed => strlen($plan252()['current_source_publication_windows_next252'][0]['window_cursor_digest_next252']), 64],
    'window digest length' => [static fn (): mixed => strlen($plan252()['publication_window_fence_next252']['window_digest']), 64],
    'fence current complete' => [static fn (): mixed => $plan252()['publication_window_fence_next252']['current_source_complete'], true],
    'fence next exposed' => [static fn (): mixed => $plan252()['publication_window_fence_next252']['next_source_exposed'], true],
    'fence high water ordinal' => [static fn (): mixed => $plan252()['publication_window_fence_next252']['current_high_water_ordinal'], 3],
    'fence first retry ordinal' => [static fn (): mixed => $plan252()['publication_window_fence_next252']['first_retry_ordinal'], 4],
    'fence retry after high water' => [static fn (): mixed => $plan252()['publication_window_fence_next252']['retry_after_current_high_water'], true],
    'fence row counts' => [static fn (): mixed => [$plan252()['publication_window_fence_next252']['current_window_row_count'], $plan252()['publication_window_fence_next252']['retry_window_row_count'], $plan252()['publication_window_fence_next252']['window_row_count']], [3, 5, 8]],
    'fence no blocked reasons' => [static fn (): mixed => $plan252()['publication_window_fence_next252']['blocked_reasons'], []],
    'resume from start row numbers' => [static fn (): mixed => array_column($plan252()['resume_window_rows_next252'], 'window_row_number_next252'), [1, 2, 3, 4, 5, 6, 7, 8]],
    'resume from first yield starts second row' => [static fn (): mixed => $plan252(null, $firstYield252())['resume_window_rows_next252'][0]['window_row_number_next252'], 2],
    'resume from first yield starts second ticket' => [static fn (): mixed => $plan252(null, $firstYield252())['resume_window_tickets_next252'][0], $required252()[1]],
    'resume from last yield starts first retry' => [static fn (): mixed => $plan252(null, $lastYield252())['resume_window_rows_next252'][0]['ticket'], $firstRetry252()],
    'resume from last yield keeps first retry ordinal' => [static fn (): mixed => $plan252(null, $lastYield252())['resume_window_rows_next252'][0]['window_row_number_next252'], 4],
    'resume from first retry starts second retry' => [static fn (): mixed => $plan252(null, $firstRetry252())['resume_window_rows_next252'][0]['option_id'], 10],
    'resume from last retry exhausted' => [static fn (): mixed => $plan252(null, $lastRetry252())['resume_window_rows_next252'], []],
    'held status' => [static fn (): mixed => $plan252($missingAck252())['publication_state_next248'], 'current-source-yield-pending-next-source-held-next248'],
    'held window count only current' => [static fn (): mixed => count($plan252($missingAck252())['current_source_publication_windows_next252']), 3],
    'held current count' => [static fn (): mixed => $plan252($missingAck252())['current_source_window_count_next252'], 3],
    'held retry count' => [static fn (): mixed => $plan252($missingAck252())['next_source_window_count_next252'], 0],
    'held first retry null' => [static fn (): mixed => $plan252($missingAck252())['next_source_first_ticket_next252'], null],
    'held first retry ordinal null' => [static fn (): mixed => $plan252($missingAck252())['next_source_first_ordinal_next252'], null],
    'held fence high water ordinal' => [static fn (): mixed => $plan252($missingAck252())['publication_window_fence_next252']['current_high_water_ordinal'], 3],
    'held fence retry ordinal null' => [static fn (): mixed => $plan252($missingAck252())['publication_window_fence_next252']['first_retry_ordinal'], null],
    'held fence retry after high water vacuous' => [static fn (): mixed => $plan252($missingAck252())['publication_window_fence_next252']['retry_after_current_high_water'], true],
    'held blocked reason' => [static fn (): mixed => $plan252($missingAck252())['publication_window_fence_next252']['blocked_reasons'], ['missing-current-source-yield-ticket-next248']],
    'unexpected retry held' => [static fn (): mixed => $plan252($unexpectedAck252())['next_source_window_count_next252'], 0],
    'unexpected blocked reason' => [static fn (): mixed => $plan252($unexpectedAck252())['publication_window_fence_next252']['blocked_reasons'], ['unexpected-current-source-yield-ticket-next248']],
    'dependencies include fence' => [static fn (): mixed => in_array('sqlite-rowvalue-returning-current-source-window-fence-next252', $plan252()['dependencies_next252'], true), true],
    'dependencies include next source ordinal' => [static fn (): mixed => in_array('sqlite-rowvalue-returning-next-source-row-number-after-current-next252', $plan252()['dependencies_next252'], true), true],
    'dependencies include wordpress' => [static fn (): mixed => in_array('wordpress-rowvalue-returning-window-current-source-next252', $plan252()['dependencies_next252'], true), true],
    'dependency closure no new support' => [static fn (): mixed => str_contains($plan252()['dependency_closure_next252'], 'no new support component needed'), true],
    'non overlap mentions next248' => [static fn (): mixed => str_contains($plan252()['non_overlap_next252'], 'next248'), true],
    'non overlap mentions next245' => [static fn (): mixed => str_contains($plan252()['non_overlap_next252'], 'next245'), true],
    'bad resume rejected by base' => [static fn (): mixed => $plan252(null, 'missing-ticket-next252'), InvalidArgumentException::class],
    'bad savepoint rejected by base' => [static fn (): mixed => SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNext252Plan::execute($tables252, [$yieldUpdate252], [$attemptUpdate252], [$retryUpdate252], $unique252, 'bad-name'), InvalidArgumentException::class],
    'bad rowid rejected' => [static fn (): mixed => SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNext252Plan::execute(['wp_options' => [['option_id' => ['bad'], 'blog_id' => 1, 'option_name' => 'home', 'autoload' => 'yes', 'status' => 'live', 'bytes' => 1, 'option_value' => 'x']]], ["UPDATE wp_options SET (status, option_value, bytes) = ('yield252', option_value, bytes) WHERE (blog_id, option_name) IN ((1, 'home')) RETURNING option_id, blog_id, option_name, status, bytes"], [$attemptUpdate252], [$retryUpdate252], $unique252), InvalidArgumentException::class],
];

$tests = [];
foreach ($cases252 as $name => [$callback, $expected]) {
    $tests['rowvalue update delete returning window current source next252 ' . $name] = static function (TestRunner $t) use ($callback, $expected): void {
        if (is_string($expected) && is_a($expected, Throwable::class, true)) {
            $t->throws($expected, $callback);
            return;
        }
        $t->same($expected, $callback());
    };
}

return $tests;
