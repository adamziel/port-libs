<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNextPlan;
use PortLibs\LibSqlite\SQLiteUpdateDeleteReturningSql;

$rows249 = [
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
$tables249 = ['wp_options' => $rows249];
$unique249 = [['blog_id', 'option_name']];

$yieldUpdate249 = "UPDATE wp_options SET (status, option_value, bytes) = ('yield249', option_value || ':yield249', bytes + 30) WHERE (blog_id, option_name) IN ((2, 'pending_theme'), (3, 'rewrite_rules')) RETURNING option_id, blog_id, option_name, status, bytes ORDER BY option_id";
$yieldDelete249 = "DELETE FROM wp_options WHERE (blog_id, option_name) IN ((1, '_transient_feed')) RETURNING option_id, blog_id, option_name, status, bytes ORDER BY option_id";
$attemptUpdate249 = "UPDATE wp_options SET (status, option_value, bytes) = ('attempt249', option_value || ':attempt249', bytes + 5) WHERE (blog_id, option_name) IN ((2, 'pending_theme'), (3, 'rewrite_rules')) RETURNING option_id, blog_id, option_name, status, bytes ORDER BY option_id";
$attemptDelete249 = "DELETE FROM wp_options WHERE (blog_id, option_name) IN ((3, 'orphaned_cache')) RETURNING option_id, blog_id, option_name, status, bytes ORDER BY option_id";
$retryUpdate249 = "UPDATE wp_options SET (status, option_value, bytes) = ('retry249', option_value || ':retry249', bytes + 20) WHERE (blog_id, option_name) IN ((2, 'pending_theme'), (3, 'rewrite_rules'), (4, 'plugin_batch')) RETURNING option_id, blog_id, option_name, status, bytes ORDER BY option_id";
$retryDelete249 = "DELETE FROM wp_options WHERE (blog_id, option_name) IN ((1, '_transient_timeout_feed'), (4, 'home')) RETURNING option_id, blog_id, option_name, status, bytes ORDER BY option_id";

$plan249 = static fn (?array $ack = null, int $chunkSize = 2): array => SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNextPlan::executeChunkedYieldResumeWindow(
    $tables249,
    [$yieldUpdate249, $yieldDelete249],
    [$attemptUpdate249, $attemptDelete249],
    [$retryUpdate249, $retryDelete249],
    $unique249,
    'app_settings_rowvalue_window_current_next249',
    'option_id',
    $ack,
    $chunkSize,
);
$required249 = static fn (): array => $plan249()['required_yield_tickets_next245'];
$missingAck249 = static fn (): array => array_slice($required249(), 0, 2);
$unexpectedAck249 = static fn (): array => [...$required249(), 'unexpected:ticket:next249'];
$chunkOneToken249 = static fn (): string => hash('sha256', implode("\n", array_slice($plan249()['window_yield_sequence_next249'], 0, 2)));

$cases249 = [
    'parser yield row-value predicate' => [static fn (): mixed => SQLiteUpdateDeleteReturningSql::parse($yieldUpdate249)['where'], "(blog_id, option_name) IN ((2, 'pending_theme'), (3, 'rewrite_rules'))"],
    'parser retry delete returning preserved' => [static fn (): mixed => SQLiteUpdateDeleteReturningSql::parse($retryDelete249)['returning'], 'option_id, blog_id, option_name, status, bytes'],
    'plan status' => [static fn (): mixed => $plan249()['status'], 'rowvalue-update-delete-returning-window-current-source-next249'],
    'inherits next245 gate status' => [static fn (): mixed => $plan249()['yield_current_source_gate_next245']['yield_boundary'], 'current-source-yield-before-next-source-next245'],
    'yield rows ids' => [static fn (): mixed => array_column($plan249()['yield_window_rows_next249'], 'option_id'), [7, 5, 3]],
    'yield rows status' => [static fn (): mixed => array_column($plan249()['yield_window_rows_next249'], 'status'), ['yield249', 'yield249', 'stale']],
    'yield rows running bytes' => [static fn (): mixed => array_column($plan249()['yield_window_rows_next249'], 'running_bytes'), [39, 76, 88]],
    'yield rows following bytes' => [static fn (): mixed => array_column($plan249()['yield_window_rows_next249'], 'following_bytes'), [49, 12, 0]],
    'yield rows cumulative running' => [static fn (): mixed => array_column($plan249()['yield_window_rows_next249'], 'cumulative_running_bytes'), [39, 115, 203]],
    'yield row lag tickets' => [static fn (): mixed => array_column($plan249()['yield_window_rows_next249'], 'lag_ticket'), [null, 'yield:1:7:rewrite_rules:rewrite_rules:39:39', 'yield:2:5:pending_theme:pending_theme:37:76']],
    'yield row lead tickets' => [static fn (): mixed => array_column($plan249()['yield_window_rows_next249'], 'lead_ticket'), ['yield:2:5:pending_theme:pending_theme:37:76', 'yield:3:3:_transient_feed:_transient_feed:12:88', null]],
    'yield sequence tokens' => [static fn (): mixed => $plan249()['window_yield_sequence_next249'], ['yield|1|7|rewrite_rules|rewrite_rules:39:39', 'yield|2|5|pending_theme|pending_theme:37:76', 'yield|3|3|_transient_feed|_transient_feed:12:88']],
    'retry rows ids' => [static fn (): mixed => array_column($plan249()['retry_window_rows_next249'], 'option_id'), [9, 10, 7, 5, 4]],
    'retry rows status' => [static fn (): mixed => array_column($plan249()['retry_window_rows_next249'], 'status'), ['retry249', 'live', 'retry249', 'retry249', 'stale']],
    'retry rows cumulative running' => [static fn (): mixed => array_column($plan249()['retry_window_rows_next249'], 'cumulative_running_bytes'), [31, 93, 184, 302, 433]],
    'retry sequence starts' => [static fn (): mixed => array_slice($plan249()['retry_window_sequence_next249'], 0, 2), ['retry-release|1|9|plugin_batch|plugin_batch:31:31', 'retry-release|2|10|home|home:31:62']],
    'default chunk count' => [static fn (): mixed => count($plan249()['yield_ack_chunks_next249']), 2],
    'chunk one rows' => [static fn (): mixed => $plan249()['yield_ack_chunks_next249'][0]['rowids'], [7, 5]],
    'chunk two rows' => [static fn (): mixed => $plan249()['yield_ack_chunks_next249'][1]['rowids'], [3]],
    'chunk one ordinals' => [static fn (): mixed => [$plan249()['yield_ack_chunks_next249'][0]['first_ordinal'], $plan249()['yield_ack_chunks_next249'][0]['last_ordinal']], [1, 2]],
    'chunk two ordinals' => [static fn (): mixed => [$plan249()['yield_ack_chunks_next249'][1]['first_ordinal'], $plan249()['yield_ack_chunks_next249'][1]['last_ordinal']], [3, 3]],
    'chunk one token deterministic' => [static fn (): mixed => $plan249()['yield_ack_chunks_next249'][0]['resume_token'], $chunkOneToken249()],
    'chunk token length' => [static fn (): mixed => strlen($plan249()['yield_ack_chunks_next249'][0]['resume_token']), 64],
    'chunk size one count' => [static fn (): mixed => count($plan249(null, 1)['yield_ack_chunks_next249']), 3],
    'chunk size three count' => [static fn (): mixed => count($plan249(null, 3)['yield_ack_chunks_next249']), 1],
    'resume gate chunk count' => [static fn (): mixed => $plan249()['yield_resume_gate_next249']['chunk_count'], 2],
    'resume gate acknowledged chunks' => [static fn (): mixed => $plan249()['yield_resume_gate_next249']['acknowledged_chunk_count'], 2],
    'resume gate held chunks' => [static fn (): mixed => $plan249()['yield_resume_gate_next249']['held_chunk_count'], 0],
    'resume gate complete' => [static fn (): mixed => $plan249()['current_source_yield_complete_next249'], true],
    'resume gate exposes retry' => [static fn (): mixed => $plan249()['retry_window_exposed_next249'], true],
    'resume gate retry ids' => [static fn (): mixed => $plan249()['yield_resume_gate_next249']['retry_rowids_if_exposed'], [9, 10, 7, 5, 4]],
    'resume token length' => [static fn (): mixed => strlen((string) $plan249()['next_source_resume_token_next249']), 64],
    'resume boundary exposed' => [static fn (): mixed => $plan249()['yield_resume_gate_next249']['resume_boundary'], 'next-source-retry-window-resumes-after-yield-chunks-next249'],
    'missing ack holds retry' => [static fn (): mixed => $plan249($missingAck249())['retry_window_exposed_next249'], false],
    'missing ack token null' => [static fn (): mixed => $plan249($missingAck249())['next_source_resume_token_next249'], null],
    'missing ack held chunk count' => [static fn (): mixed => $plan249($missingAck249())['yield_resume_gate_next249']['held_chunk_count'], 2],
    'missing ack acknowledged chunk count' => [static fn (): mixed => $plan249($missingAck249())['yield_resume_gate_next249']['acknowledged_chunk_count'], 0],
    'missing ack retry ids suppressed' => [static fn (): mixed => $plan249($missingAck249())['yield_resume_gate_next249']['retry_rowids_if_exposed'], []],
    'missing ack records ticket' => [static fn (): mixed => $plan249($missingAck249())['yield_resume_gate_next249']['missing_tickets'], ['yield:3:3:_transient_feed:_transient_feed:12:88']],
    'missing ack boundary held' => [static fn (): mixed => $plan249($missingAck249())['yield_resume_gate_next249']['resume_boundary'], 'next-source-retry-window-held-for-yield-chunks-next249'],
    'unexpected ack holds retry' => [static fn (): mixed => $plan249($unexpectedAck249())['retry_window_exposed_next249'], false],
    'unexpected ack records ticket' => [static fn (): mixed => $plan249($unexpectedAck249())['yield_resume_gate_next249']['unexpected_tickets'], ['unexpected:ticket:next249']],
    'unexpected ack token null' => [static fn (): mixed => $plan249($unexpectedAck249())['next_source_resume_token_next249'], null],
    'required tickets still next245 exact' => [static fn (): mixed => $required249(), ['yield:1:7:rewrite_rules:rewrite_rules:39:39', 'yield:2:5:pending_theme:pending_theme:37:76', 'yield:3:3:_transient_feed:_transient_feed:12:88']],
    'next245 exposed remains true' => [static fn (): mixed => $plan249()['next_source_exposed_next245'], true],
    'current source table row count' => [static fn (): mixed => count($plan249()['current_source_tables']['wp_options']), 8],
    'retry deletes timeout and home' => [static fn (): mixed => array_values(array_intersect([4, 10], array_column($plan249()['current_source_tables']['wp_options'], 'option_id'))), []],
    'retry preserves rolled back feed delete' => [static fn (): mixed => in_array(3, array_column($plan249()['current_source_tables']['wp_options'], 'option_id'), true), true],
    'retry preserves suppressed orphan delete' => [static fn (): mixed => in_array(8, array_column($plan249()['current_source_tables']['wp_options'], 'option_id'), true), true],
    'dependency marker' => [static fn (): mixed => in_array('sqlite-rowvalue-returning-window-chunked-yield-next249', $plan249()['dependencies_next249'], true), true],
    'application dependency marker' => [static fn (): mixed => in_array('application-rowvalue-returning-window-resume-next249', $plan249()['dependencies_next249'], true), true],
    'dependency closure' => [static fn (): mixed => str_contains($plan249()['dependency_closure_next249'], 'no new support component needed'), true],
    'non overlap mentions next245' => [static fn (): mixed => str_contains($plan249()['non_overlap_next249'], 'next245'), true],
    'non overlap mentions next236' => [static fn (): mixed => str_contains($plan249()['non_overlap_next249'], 'next236'), true],
    'non overlap mentions btree' => [static fn (): mixed => str_contains($plan249()['non_overlap_next249'], 'B-tree'), true],
    'invalid chunk rejected' => [static fn (): mixed => SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNextPlan::executeChunkedYieldResumeWindow($tables249, [$yieldUpdate249], [$attemptUpdate249], [$retryUpdate249], $unique249, 'app_settings_rowvalue_window_current_next249', 'option_id', null, 0), InvalidArgumentException::class],
    'malformed empty yield rejected' => [static fn (): mixed => SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNextPlan::executeChunkedYieldResumeWindow($tables249, [], [$attemptUpdate249], [$retryUpdate249], $unique249), InvalidArgumentException::class],
    'malformed empty attempt rejected' => [static fn (): mixed => SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNextPlan::executeChunkedYieldResumeWindow($tables249, [$yieldUpdate249], [], [$retryUpdate249], $unique249), InvalidArgumentException::class],
    'malformed empty retry rejected' => [static fn (): mixed => SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNextPlan::executeChunkedYieldResumeWindow($tables249, [$yieldUpdate249], [$attemptUpdate249], [], $unique249), InvalidArgumentException::class],
    'malformed savepoint rejected' => [static fn (): mixed => SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNextPlan::executeChunkedYieldResumeWindow($tables249, [$yieldUpdate249], [$attemptUpdate249], [$retryUpdate249], $unique249, 'bad-name'), InvalidArgumentException::class],
];

$tests = [];
foreach ($cases249 as $name => [$callback, $expected]) {
    $tests['rowvalue update delete returning window current source next249 ' . $name] = static function (TestRunner $t) use ($callback, $expected): void {
        if (is_string($expected) && is_a($expected, Throwable::class, true)) {
            $t->throws($expected, $callback);
            return;
        }
        $t->same($expected, $callback());
    };
}

return $tests;
