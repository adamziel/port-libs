<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNextPlan;

$rows248 = [
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
$tables248 = ['wp_options' => $rows248];
$unique248 = [['blog_id', 'option_name']];

$yieldUpdate248 = "UPDATE wp_options SET (status, option_value, bytes) = ('yield248', option_value || ':yield248', bytes + 30) WHERE (blog_id, option_name) IN ((2, 'pending_theme'), (3, 'rewrite_rules')) RETURNING option_id, blog_id, option_name, status, bytes ORDER BY option_id";
$yieldDelete248 = "DELETE FROM wp_options WHERE (blog_id, option_name) IN ((1, '_transient_feed')) RETURNING option_id, blog_id, option_name, status, bytes ORDER BY option_id";
$attemptUpdate248 = "UPDATE wp_options SET (status, option_value, bytes) = ('attempt248', option_value || ':attempt248', bytes + 5) WHERE (blog_id, option_name) IN ((2, 'pending_theme'), (3, 'rewrite_rules')) RETURNING option_id, blog_id, option_name, status, bytes ORDER BY option_id";
$attemptDelete248 = "DELETE FROM wp_options WHERE (blog_id, option_name) IN ((3, 'orphaned_cache')) RETURNING option_id, blog_id, option_name, status, bytes ORDER BY option_id";
$retryUpdate248 = "UPDATE wp_options SET (status, option_value, bytes) = ('retry248', option_value || ':retry248', bytes + 20) WHERE (blog_id, option_name) IN ((2, 'pending_theme'), (3, 'rewrite_rules'), (4, 'plugin_batch')) RETURNING option_id, blog_id, option_name, status, bytes ORDER BY option_id";
$retryDelete248 = "DELETE FROM wp_options WHERE (blog_id, option_name) IN ((1, '_transient_timeout_feed'), (4, 'home')) RETURNING option_id, blog_id, option_name, status, bytes ORDER BY option_id";

$plan248 = static fn (?array $ack = null, ?string $resume = null): array => SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNextPlan::executeNext248(
    $tables248,
    [$yieldUpdate248, $yieldDelete248],
    [$attemptUpdate248, $attemptDelete248],
    [$retryUpdate248, $retryDelete248],
    $unique248,
    'wp_options_rowvalue_window_current_next248',
    'option_id',
    $ack,
    $resume,
);

$required248 = static fn (): array => $plan248()['required_yield_tickets_next245'];
$firstYield248 = static fn (): string => $required248()[0];
$lastYield248 = static fn (): string => $required248()[2];
$firstRetry248 = static fn (): string => $plan248()['retry_publication_rows_next248'][0]['ticket'];
$lastRetry248 = static fn (): string => $plan248()['retry_publication_rows_next248'][4]['ticket'];
$missingAck248 = static fn (): array => array_slice($required248(), 0, 2);
$unexpectedAck248 = static fn (): array => [...$required248(), 'unexpected:publication:next248'];

$cases248 = [
    'status' => [static fn (): mixed => $plan248()['status'], 'rowvalue-update-delete-returning-window-current-source-next248'],
    'inherits next245 exposed gate' => [static fn (): mixed => $plan248()['next_source_exposed_next245'], true],
    'publication state exposed' => [static fn (): mixed => $plan248()['publication_state_next248'], 'current-source-yield-complete-next-source-resumable-next248'],
    'barrier savepoint' => [static fn (): mixed => $plan248()['publication_barrier_next248']['savepoint'], 'wp_options_rowvalue_window_current_next248'],
    'barrier current complete' => [static fn (): mixed => $plan248()['publication_barrier_next248']['current_source_complete'], true],
    'barrier next exposed' => [static fn (): mixed => $plan248()['publication_barrier_next248']['next_source_exposed'], true],
    'barrier required count' => [static fn (): mixed => $plan248()['publication_barrier_next248']['required_yield_count'], 3],
    'barrier acknowledged count' => [static fn (): mixed => $plan248()['publication_barrier_next248']['acknowledged_yield_count'], 3],
    'barrier retry count' => [static fn (): mixed => $plan248()['publication_barrier_next248']['retry_row_count'], 5],
    'barrier published count' => [static fn (): mixed => $plan248()['publication_barrier_next248']['published_row_count'], 8],
    'barrier blocked reasons empty' => [static fn (): mixed => $plan248()['publication_barrier_next248']['blocked_reasons'], []],
    'barrier current digest is sha256' => [static fn (): mixed => strlen($plan248()['publication_barrier_next248']['current_source_digest']), 64],
    'barrier next digest is sha256' => [static fn (): mixed => strlen($plan248()['publication_barrier_next248']['next_source_digest']), 64],
    'barrier token is sha256' => [static fn (): mixed => strlen($plan248()['publication_barrier_next248']['barrier_token']), 64],
    'barrier token changes when held' => [static fn (): mixed => $plan248()['publication_barrier_next248']['barrier_token'] === $plan248($missingAck248())['publication_barrier_next248']['barrier_token'], false],
    'current publication ids' => [static fn (): mixed => array_column($plan248()['current_publication_rows_next248'], 'option_id'), [7, 5, 3]],
    'current publication source labels' => [static fn (): mixed => array_unique(array_column($plan248()['current_publication_rows_next248'], 'source')), ['current-yield']],
    'current publication ordinals' => [static fn (): mixed => array_column($plan248()['current_publication_rows_next248'], 'publication_ordinal_next248'), [1, 2, 3]],
    'current publication statuses' => [static fn (): mixed => array_column($plan248()['current_publication_rows_next248'], 'status'), ['yield248', 'yield248', 'stale']],
    'current publication running bytes' => [static fn (): mixed => array_column($plan248()['current_publication_rows_next248'], 'running_bytes'), [39, 76, 88]],
    'current publication following bytes' => [static fn (): mixed => array_column($plan248()['current_publication_rows_next248'], 'following_bytes'), [49, 12, 0]],
    'current publication cursor length' => [static fn (): mixed => strlen($plan248()['current_publication_rows_next248'][0]['cursor']), 64],
    'retry publication ids' => [static fn (): mixed => array_column($plan248()['retry_publication_rows_next248'], 'option_id'), [9, 10, 7, 5, 4]],
    'retry publication source labels' => [static fn (): mixed => array_unique(array_column($plan248()['retry_publication_rows_next248'], 'source')), ['next-retry']],
    'retry publication ordinals' => [static fn (): mixed => array_column($plan248()['retry_publication_rows_next248'], 'publication_ordinal_next248'), [1, 2, 3, 4, 5]],
    'retry publication statuses' => [static fn (): mixed => array_column($plan248()['retry_publication_rows_next248'], 'status'), ['retry248', 'live', 'retry248', 'retry248', 'stale']],
    'retry publication running bytes' => [static fn (): mixed => array_column($plan248()['retry_publication_rows_next248'], 'running_bytes'), [31, 62, 91, 118, 131]],
    'retry publication following bytes' => [static fn (): mixed => array_column($plan248()['retry_publication_rows_next248'], 'following_bytes'), [100, 69, 40, 13, 0]],
    'sequence ids include yield then retry' => [static fn (): mixed => array_column($plan248()['publication_sequence_next248'], 'option_id'), [7, 5, 3, 9, 10, 7, 5, 4]],
    'sequence tickets include yield then retry' => [static fn (): mixed => $plan248()['publication_sequence_tickets_next248'], [...$required248(), ...array_column($plan248()['retry_publication_rows_next248'], 'ticket')]],
    'sequence ordinals monotonic' => [static fn (): mixed => array_column($plan248()['publication_sequence_next248'], 'sequence_ordinal_next248'), [1, 2, 3, 4, 5, 6, 7, 8]],
    'sequence phases yield first' => [static fn (): mixed => array_column(array_slice($plan248()['publication_sequence_next248'], 0, 3), 'publication_phase_next248'), ['current-source-yield', 'current-source-yield', 'current-source-yield']],
    'sequence phases retry last' => [static fn (): mixed => array_column(array_slice($plan248()['publication_sequence_next248'], 3), 'publication_phase_next248'), ['next-source-retry', 'next-source-retry', 'next-source-retry', 'next-source-retry', 'next-source-retry']],
    'yield rows do not expose next source' => [static fn (): mixed => array_column(array_slice($plan248()['publication_sequence_next248'], 0, 3), 'next_source_visible_next248'), [false, false, false]],
    'retry rows expose next source' => [static fn (): mixed => array_column(array_slice($plan248()['publication_sequence_next248'], 3), 'next_source_visible_next248'), [true, true, true, true, true]],
    'resume from start count' => [static fn (): mixed => $plan248()['publication_resume_next248']['remaining_count'], 8],
    'resume from start offset' => [static fn (): mixed => $plan248()['publication_resume_next248']['resume_offset'], 0],
    'resume from first yield count' => [static fn (): mixed => $plan248(null, $firstYield248())['publication_resume_next248']['remaining_count'], 7],
    'resume from first yield starts on second yield' => [static fn (): mixed => $plan248(null, $firstYield248())['publication_resume_tickets_next248'][0], $required248()[1]],
    'resume from last yield starts on first retry' => [static fn (): mixed => $plan248(null, $lastYield248())['publication_resume_tickets_next248'][0], $firstRetry248()],
    'resume from first retry starts on second retry' => [static fn (): mixed => $plan248(null, $firstRetry248())['publication_resume_next248']['rows'][0]['option_id'], 10],
    'resume from last retry is exhausted' => [static fn (): mixed => $plan248(null, $lastRetry248())['publication_resume_next248']['exhausted'], true],
    'resume from last retry count zero' => [static fn (): mixed => $plan248(null, $lastRetry248())['publication_resume_next248']['remaining_count'], 0],
    'held state blocks retry publication' => [static fn (): mixed => $plan248($missingAck248())['publication_state_next248'], 'current-source-yield-pending-next-source-held-next248'],
    'held barrier next exposed false' => [static fn (): mixed => $plan248($missingAck248())['publication_barrier_next248']['next_source_exposed'], false],
    'held published count only yield' => [static fn (): mixed => $plan248($missingAck248())['publication_barrier_next248']['published_row_count'], 3],
    'held sequence omits retry ids' => [static fn (): mixed => array_column($plan248($missingAck248())['publication_sequence_next248'], 'option_id'), [7, 5, 3]],
    'held blocked reason missing' => [static fn (): mixed => $plan248($missingAck248())['publication_barrier_next248']['blocked_reasons'], ['missing-current-source-yield-ticket-next248']],
    'held missing ticket preserved from next245' => [static fn (): mixed => $plan248($missingAck248())['yield_current_source_gate_next245']['missing_tickets'], [$lastYield248()]],
    'unexpected state blocks retry publication' => [static fn (): mixed => $plan248($unexpectedAck248())['publication_barrier_next248']['next_source_exposed'], false],
    'unexpected blocked reason' => [static fn (): mixed => $plan248($unexpectedAck248())['publication_barrier_next248']['blocked_reasons'], ['unexpected-current-source-yield-ticket-next248']],
    'unexpected ticket preserved from next245' => [static fn (): mixed => $plan248($unexpectedAck248())['yield_current_source_gate_next245']['unexpected_tickets'], ['unexpected:publication:next248']],
    'dependencies include cursor' => [static fn (): mixed => in_array('sqlite-returning-current-source-publication-cursor-next248', $plan248()['dependencies_next248'], true), true],
    'dependencies include wordpress' => [static fn (): mixed => in_array('wordpress-rowvalue-returning-window-resume-barrier-next248', $plan248()['dependencies_next248'], true), true],
    'dependency closure no new support' => [static fn (): mixed => str_contains($plan248()['dependency_closure_next248'], 'no new support component needed'), true],
    'non overlap mentions next245' => [static fn (): mixed => str_contains($plan248()['non_overlap_next248'], 'next245'), true],
    'non overlap mentions next236' => [static fn (): mixed => str_contains($plan248()['non_overlap_next248'], 'next236'), true],
    'bad resume ticket rejected' => [static fn (): mixed => $plan248(null, 'missing-ticket-next248'), InvalidArgumentException::class],
    'bad savepoint rejected by base' => [static fn (): mixed => SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNextPlan::executeNext248($tables248, [$yieldUpdate248], [$attemptUpdate248], [$retryUpdate248], $unique248, 'bad-name'), InvalidArgumentException::class],
];

$tests = [];
foreach ($cases248 as $name => [$callback, $expected]) {
    $tests['rowvalue update delete returning window current source next248 ' . $name] = static function (TestRunner $t) use ($callback, $expected): void {
        if (is_string($expected) && is_a($expected, Throwable::class, true)) {
            $t->throws($expected, $callback);
            return;
        }
        $t->same($expected, $callback());
    };
}

return $tests;
