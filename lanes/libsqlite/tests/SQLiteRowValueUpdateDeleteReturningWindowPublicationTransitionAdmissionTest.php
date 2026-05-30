<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNextPlan;

$rows258 = [
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
$tables258 = ['wp_options' => $rows258];
$unique258 = [['blog_id', 'option_name']];

$yieldUpdate258 = "UPDATE wp_options SET (status, option_value, bytes) = ('yield258', option_value || ':yield258', bytes + 30) WHERE (blog_id, option_name) IN ((2, 'pending_theme'), (3, 'rewrite_rules')) RETURNING option_id, blog_id, option_name, status, bytes ORDER BY option_id";
$yieldDelete258 = "DELETE FROM wp_options WHERE (blog_id, option_name) IN ((1, '_transient_feed')) RETURNING option_id, blog_id, option_name, status, bytes ORDER BY option_id";
$attemptUpdate258 = "UPDATE wp_options SET (status, option_value, bytes) = ('attempt258', option_value || ':attempt258', bytes + 5) WHERE (blog_id, option_name) IN ((2, 'pending_theme'), (3, 'rewrite_rules')) RETURNING option_id, blog_id, option_name, status, bytes ORDER BY option_id";
$attemptDelete258 = "DELETE FROM wp_options WHERE (blog_id, option_name) IN ((3, 'orphaned_cache')) RETURNING option_id, blog_id, option_name, status, bytes ORDER BY option_id";
$retryUpdate258 = "UPDATE wp_options SET (status, option_value, bytes) = ('retry258', option_value || ':retry258', bytes + 20) WHERE (blog_id, option_name) IN ((2, 'pending_theme'), (3, 'rewrite_rules'), (4, 'plugin_batch')) RETURNING option_id, blog_id, option_name, status, bytes ORDER BY option_id";
$retryDelete258 = "DELETE FROM wp_options WHERE (blog_id, option_name) IN ((1, '_transient_timeout_feed'), (4, 'home')) RETURNING option_id, blog_id, option_name, status, bytes ORDER BY option_id";

$plan258 = static fn (?array $ack = null, ?string $resume = null, ?string $transition = null): array => SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNextPlan::executePublicationTransitionAdmission(
    $tables258,
    [$yieldUpdate258, $yieldDelete258],
    [$attemptUpdate258, $attemptDelete258],
    [$retryUpdate258, $retryDelete258],
    $unique258,
    'app_settings_rowvalue_window_current_next258',
    'option_id',
    $ack,
    $resume,
    $transition,
);

$required258 = static fn (): array => $plan258()['required_yield_tickets_next245'];
$transition258 = static fn (): string => $plan258()['required_transition_token_next258'];
$admitted258 = static fn (): array => $plan258(null, null, $transition258());
$missingAck258 = static fn (): array => array_slice($required258(), 0, 2);
$badTransition258 = 'unexpected:transition:next258';
$firstYield258 = static fn (): string => $required258()[0];
$lastYield258 = static fn (): string => $required258()[2];
$firstRetry258 = static fn (): string => $plan258()['current_source_transition_next258']['first_retry_ticket_next258'];
$lastRetry258 = static fn (): string => $admitted258()['publication_rows_next258'][7]['ticket'];

$cases258 = [
    'status' => [static fn (): mixed => $plan258()['status'], 'rowvalue-update-delete-returning-window-current-source-next258'],
    'inherits next252 status' => [static fn (): mixed => $plan258()['publication_window_fence_next252']['retry_after_current_high_water'], true],
    'required transition token sha256' => [static fn (): mixed => strlen($transition258()), 64],
    'required token stable' => [static fn (): mixed => $transition258() === $plan258()['current_source_transition_next258']['required_transition_token_next258'], true],
    'transition complete before acknowledgement' => [static fn (): mixed => $plan258()['current_source_transition_next258']['transition_complete_next258'], true],
    'transition current complete' => [static fn (): mixed => $plan258()['current_source_transition_next258']['current_source_complete_next258'], true],
    'transition next available' => [static fn (): mixed => $plan258()['current_source_transition_next258']['next_source_available_next258'], true],
    'transition retry after high water' => [static fn (): mixed => $plan258()['current_source_transition_next258']['retry_after_current_high_water_next258'], true],
    'transition high water ticket' => [static fn (): mixed => $plan258()['current_source_transition_next258']['current_high_water_ticket_next258'], $lastYield258()],
    'transition first retry ticket' => [static fn (): mixed => $plan258()['current_source_transition_next258']['first_retry_ticket_next258'], $firstRetry258()],
    'transition high water ordinal' => [static fn (): mixed => $plan258()['current_source_transition_next258']['current_high_water_ordinal_next258'], 3],
    'transition first retry ordinal' => [static fn (): mixed => $plan258()['current_source_transition_next258']['first_retry_ordinal_next258'], 4],
    'transition window digest sha256' => [static fn (): mixed => strlen($plan258()['current_source_transition_next258']['window_digest_next258']), 64],
    'transition not acknowledged by default' => [static fn (): mixed => $plan258()['transition_acknowledged_next258'], false],
    'next not admitted by default' => [static fn (): mixed => $plan258()['next_source_admitted_next258'], false],
    'blocked reason missing token' => [static fn (): mixed => $plan258()['blocked_reasons_next258'], ['missing-current-source-transition-token-next258']],
    'publication rows default current only' => [static fn (): mixed => array_column($plan258()['publication_rows_next258'], 'option_id'), [7, 5, 3]],
    'publication count default current only' => [static fn (): mixed => $plan258()['publication_row_count_next258'], 3],
    'publication ordinals default' => [static fn (): mixed => array_column($plan258()['publication_rows_next258'], 'publication_ordinal_next258'), [1, 2, 3]],
    'publication phases current only' => [static fn (): mixed => array_column($plan258()['publication_rows_next258'], 'publication_phase_next258'), ['current-source-window', 'current-source-window', 'current-source-window']],
    'current rows admitted default' => [static fn (): mixed => array_column($plan258()['publication_rows_next258'], 'next_source_admitted_next258'), [true, true, true]],
    'acknowledged token accepted' => [static fn (): mixed => $admitted258()['transition_acknowledged_next258'], true],
    'next admitted with token' => [static fn (): mixed => $admitted258()['next_source_admitted_next258'], true],
    'blocked reasons clear with token' => [static fn (): mixed => $admitted258()['blocked_reasons_next258'], []],
    'publication rows admitted all ids' => [static fn (): mixed => array_column($admitted258()['publication_rows_next258'], 'option_id'), [7, 5, 3, 9, 10, 7, 5, 4]],
    'publication count admitted all rows' => [static fn (): mixed => $admitted258()['publication_row_count_next258'], 8],
    'publication ordinals admitted' => [static fn (): mixed => array_column($admitted258()['publication_rows_next258'], 'publication_ordinal_next258'), [1, 2, 3, 4, 5, 6, 7, 8]],
    'admitted next flags' => [static fn (): mixed => array_column($admitted258()['publication_rows_next258'], 'next_source_admitted_next258'), [true, true, true, true, true, true, true, true]],
    'admitted phase boundary' => [static fn (): mixed => array_column($admitted258()['publication_rows_next258'], 'publication_phase_next258'), ['current-source-window', 'current-source-window', 'current-source-window', 'next-source-transition', 'next-source-transition', 'next-source-transition', 'next-source-transition', 'next-source-transition']],
    'admitted row numbers preserved' => [static fn (): mixed => array_column($admitted258()['publication_rows_next258'], 'window_row_number_next252'), [1, 2, 3, 4, 5, 6, 7, 8]],
    'admitted partition numbers preserved' => [static fn (): mixed => array_column($admitted258()['publication_rows_next258'], 'window_partition_row_number_next252'), [1, 2, 3, 1, 2, 3, 4, 5]],
    'admitted high water on first retry' => [static fn (): mixed => $admitted258()['publication_rows_next258'][3]['window_current_source_high_water_ticket_next252'], $lastYield258()],
    'admitted first retry previous ticket' => [static fn (): mixed => $admitted258()['publication_rows_next258'][3]['window_previous_ticket_next252'], $lastYield258()],
    'admitted last current next ticket' => [static fn (): mixed => $admitted258()['publication_rows_next258'][2]['window_next_ticket_next252'], $firstRetry258()],
    'admitted first retry ordinal' => [static fn (): mixed => $admitted258()['publication_rows_next258'][3]['window_next_source_first_ordinal_next252'], 4],
    'bad token not acknowledged' => [static fn (): mixed => $plan258(null, null, $badTransition258)['transition_acknowledged_next258'], false],
    'bad token holds next rows' => [static fn (): mixed => array_column($plan258(null, null, $badTransition258)['publication_rows_next258'], 'option_id'), [7, 5, 3]],
    'bad token blocked reason' => [static fn (): mixed => $plan258(null, null, $badTransition258)['blocked_reasons_next258'], ['unexpected-current-source-transition-token-next258']],
    'missing yield ack still carries next248 reason' => [static fn (): mixed => $plan258($missingAck258())['blocked_reasons_next258'], ['missing-current-source-yield-ticket-next248']],
    'missing yield ack transition incomplete' => [static fn (): mixed => $plan258($missingAck258())['current_source_transition_next258']['transition_complete_next258'], false],
    'missing yield ack no next available' => [static fn (): mixed => $plan258($missingAck258())['current_source_transition_next258']['next_source_available_next258'], false],
    'missing yield ack publication current only' => [static fn (): mixed => array_column($plan258($missingAck258())['publication_rows_next258'], 'option_id'), [7, 5, 3]],
    'resume default tickets current only without transition' => [static fn (): mixed => $plan258()['resume_tickets_next258'], $plan258()['publication_sequence_tickets_next248']],
    'resume first yield without transition starts second row' => [static fn (): mixed => array_column($plan258(null, $firstYield258())['resume_rows_next258'], 'option_id'), [5, 3, 9, 10, 7, 5, 4]],
    'resume last yield without transition starts retry quarantine' => [static fn (): mixed => array_column($plan258(null, $lastYield258())['resume_rows_next258'], 'option_id'), [9, 10, 7, 5, 4]],
    'resume first retry with token starts second retry' => [static fn (): mixed => array_column($plan258(null, $firstRetry258(), $transition258())['resume_rows_next258'], 'option_id'), [10, 7, 5, 4]],
    'resume last retry with token exhausted' => [static fn (): mixed => $plan258(null, $lastRetry258(), $transition258())['resume_rows_next258'], []],
    'resume tickets with token all' => [static fn (): mixed => $admitted258()['resume_tickets_next258'], $admitted258()['publication_sequence_tickets_next248']],
    'dependency includes transition token' => [static fn (): mixed => in_array('sqlite-rowvalue-returning-current-source-transition-token-next258', $plan258()['dependencies_next258'], true), true],
    'dependency includes next admission' => [static fn (): mixed => in_array('sqlite-rowvalue-returning-next-source-admission-after-window-high-water-next258', $plan258()['dependencies_next258'], true), true],
    'dependency includes application' => [static fn (): mixed => in_array('application-rowvalue-returning-window-transition-current-source-next258', $plan258()['dependencies_next258'], true), true],
    'dependency closure no new support' => [static fn (): mixed => str_contains($plan258()['dependency_closure_next258'], 'no new support component needed'), true],
    'non overlap mentions next252' => [static fn (): mixed => str_contains($plan258()['non_overlap_next258'], 'next252'), true],
    'non overlap mentions next248' => [static fn (): mixed => str_contains($plan258()['non_overlap_next258'], 'next248'), true],
    'bad resume rejected by base' => [static fn (): mixed => $plan258(null, 'missing-ticket-next258'), InvalidArgumentException::class],
    'bad savepoint rejected by base' => [static fn (): mixed => SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNextPlan::executePublicationTransitionAdmission($tables258, [$yieldUpdate258], [$attemptUpdate258], [$retryUpdate258], $unique258, 'bad-name'), InvalidArgumentException::class],
    'bad rowid rejected by base' => [static fn (): mixed => SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNextPlan::executePublicationTransitionAdmission(['wp_options' => [['option_id' => ['bad'], 'blog_id' => 1, 'option_name' => 'home', 'autoload' => 'yes', 'status' => 'live', 'bytes' => 1, 'option_value' => 'x']]], ["UPDATE wp_options SET (status, option_value, bytes) = ('yield258', option_value, bytes) WHERE (blog_id, option_name) IN ((1, 'home')) RETURNING option_id, blog_id, option_name, status, bytes"], [$attemptUpdate258], [$retryUpdate258], $unique258), InvalidArgumentException::class],
];

$tests = [];
foreach ($cases258 as $name => [$callback, $expected]) {
    $tests['rowvalue update delete returning window current source next258 ' . $name] = static function (TestRunner $t) use ($callback, $expected): void {
        if (is_string($expected) && is_a($expected, Throwable::class, true)) {
            $t->throws($expected, $callback);
            return;
        }
        $t->same($expected, $callback());
    };
}

return $tests;
