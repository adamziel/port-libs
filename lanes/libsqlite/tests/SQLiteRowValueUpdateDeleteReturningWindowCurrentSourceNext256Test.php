<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNextPlan;
use PortLibs\LibSqlite\SQLiteUpdateDeleteReturningSql;

$rows256 = [
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
$tables256 = ['wp_options' => $rows256];
$unique256 = [['blog_id', 'option_name']];

$yieldUpdate256 = "UPDATE wp_options SET (status, option_value, bytes) = ('yield256', option_value || ':yield256', bytes + 30) WHERE (blog_id, option_name) IN ((2, 'pending_theme'), (3, 'rewrite_rules')) RETURNING option_id, blog_id, option_name, status, bytes ORDER BY option_id";
$yieldDelete256 = "DELETE FROM wp_options WHERE (blog_id, option_name) IN ((1, '_transient_feed')) RETURNING option_id, blog_id, option_name, status, bytes ORDER BY option_id";
$attemptUpdate256 = "UPDATE wp_options SET (status, option_value, bytes) = ('attempt256', option_value || ':attempt256', bytes + 5) WHERE (blog_id, option_name) IN ((2, 'pending_theme'), (3, 'rewrite_rules')) RETURNING option_id, blog_id, option_name, status, bytes ORDER BY option_id";
$attemptDelete256 = "DELETE FROM wp_options WHERE (blog_id, option_name) IN ((3, 'orphaned_cache')) RETURNING option_id, blog_id, option_name, status, bytes ORDER BY option_id";
$retryUpdate256 = "UPDATE wp_options SET (status, option_value, bytes) = ('retry256', option_value || ':retry256', bytes + 20) WHERE (blog_id, option_name) IN ((2, 'pending_theme'), (3, 'rewrite_rules'), (4, 'plugin_batch')) RETURNING option_id, blog_id, option_name, status, bytes ORDER BY option_id";
$retryDelete256 = "DELETE FROM wp_options WHERE (blog_id, option_name) IN ((1, '_transient_timeout_feed'), (4, 'home')) RETURNING option_id, blog_id, option_name, status, bytes ORDER BY option_id";

$plan256 = static fn (?array $yieldAck = null, ?array $chunkAck = null, ?array $commitAck = null, int $chunkSize = 2): array => SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNextPlan::executeNext256(
    $tables256,
    [$yieldUpdate256, $yieldDelete256],
    [$attemptUpdate256, $attemptDelete256],
    [$retryUpdate256, $retryDelete256],
    $unique256,
    'wp_options_rowvalue_window_current_next256',
    'option_id',
    $yieldAck,
    $chunkSize,
    $chunkAck,
    $commitAck,
);
$requiredYield256 = static fn (): array => $plan256()['required_yield_tickets_next245'];
$requiredChunks256 = static fn (): array => $plan256()['required_window_chunk_tokens_next253'];
$requiredCommits256 = static fn (): array => $plan256()['required_retry_commit_tokens_next256'];
$missingYield256 = static fn (): array => array_slice($requiredYield256(), 0, 2);
$missingChunk256 = static fn (): array => array_slice($requiredChunks256(), 0, 1);
$missingCommit256 = static fn (): array => array_slice($requiredCommits256(), 0, 4);
$unexpectedCommit256 = static fn (): array => [...$requiredCommits256(), str_repeat('b', 64)];

$cases256 = [
    'parser update row-value assignments' => [static fn (): mixed => array_keys(SQLiteUpdateDeleteReturningSql::parse($yieldUpdate256)['assignments']), ['status', 'option_value', 'bytes']],
    'parser retry delete where' => [static fn (): mixed => SQLiteUpdateDeleteReturningSql::parse($retryDelete256)['where'], "(blog_id, option_name) IN ((1, '_transient_timeout_feed'), (4, 'home'))"],
    'plan status' => [static fn (): mixed => $plan256()['status'], 'rowvalue-update-delete-returning-window-current-source-next256'],
    'inherits next253 retry exposed' => [static fn (): mixed => $plan256()['window_current_source_retry_exposed_next253'], true],
    'commit state durable' => [static fn (): mixed => $plan256()['retry_commit_state_next256'], 'current-source-complete-next-source-retry-durable-next256'],
    'watermark savepoint' => [static fn (): mixed => $plan256()['retry_commit_watermark_next256']['savepoint'], 'wp_options_rowvalue_window_current_next256'],
    'watermark source boundary' => [static fn (): mixed => $plan256()['retry_commit_watermark_next256']['source_boundary'], 'current-source-complete-next-source-retry-durable-next256'],
    'watermark chunk gate complete' => [static fn (): mixed => $plan256()['retry_commit_watermark_next256']['current_chunk_gate_complete'], true],
    'watermark retry exposed' => [static fn (): mixed => $plan256()['retry_commit_watermark_next256']['retry_exposed'], true],
    'required commit count' => [static fn (): mixed => $plan256()['retry_commit_watermark_next256']['required_commit_count'], 5],
    'ack commit count' => [static fn (): mixed => $plan256()['retry_commit_watermark_next256']['acknowledged_commit_count'], 5],
    'commit source complete' => [static fn (): mixed => $plan256()['retry_commit_watermark_next256']['commit_source_complete'], true],
    'durable retry count' => [static fn (): mixed => $plan256()['retry_commit_watermark_next256']['durable_retry_count'], 5],
    'watermark token length' => [static fn (): mixed => strlen($plan256()['retry_commit_watermark_next256']['watermark_token']), 64],
    'required commit token lengths' => [static fn (): mixed => array_unique(array_map('strlen', $requiredCommits256())), [64]],
    'required commit tokens unique' => [static fn (): mixed => count(array_unique($requiredCommits256())), 5],
    'ack defaults to required' => [static fn (): mixed => $plan256()['acknowledged_retry_commit_tokens_next256'], $requiredCommits256()],
    'retry commit rowids' => [static fn (): mixed => array_column($plan256()['retry_commit_rows_next256'], 'option_id'), [9, 10, 7, 5, 4]],
    'retry commit sources' => [static fn (): mixed => array_unique(array_column($plan256()['retry_commit_rows_next256'], 'source')), ['next-source-retry-window-next253']],
    'durable publication rowids' => [static fn (): mixed => $plan256()['durable_publication_rowids_next256'], [7, 5, 3, 9, 10, 7, 5, 4]],
    'durable retry rowids' => [static fn (): mixed => $plan256()['durable_retry_rowids_next256'], [9, 10, 7, 5, 4]],
    'durable ordinals' => [static fn (): mixed => array_column($plan256()['durable_publication_rows_next256'], 'durable_ordinal_next256'), [1, 2, 3, 4, 5, 6, 7, 8]],
    'current durable flags true' => [static fn (): mixed => array_slice(array_column($plan256()['durable_publication_rows_next256'], 'durable_next256'), 0, 3), [true, true, true]],
    'retry durable flags true' => [static fn (): mixed => array_slice(array_column($plan256()['durable_publication_rows_next256'], 'durable_next256'), 3), [true, true, true, true, true]],
    'current phases durable' => [static fn (): mixed => array_unique(array_slice(array_column($plan256()['durable_publication_rows_next256'], 'commit_phase_next256'), 0, 3)), ['current-source-window-durable']],
    'retry phases durable' => [static fn (): mixed => array_unique(array_slice(array_column($plan256()['durable_publication_rows_next256'], 'commit_phase_next256'), 3)), ['next-source-retry-durable']],
    'commit token count' => [static fn (): mixed => count(array_column($plan256()['durable_publication_rows_next256'], 'commit_token_next256')), 8],
    'commit token lengths' => [static fn (): mixed => array_unique(array_map('strlen', array_column($plan256()['durable_publication_rows_next256'], 'commit_token_next256'))), [64]],
    'commit tokens unique' => [static fn (): mixed => count(array_unique(array_column($plan256()['durable_publication_rows_next256'], 'commit_token_next256'))), 8],
    'chunk size one still durable retry count' => [static fn (): mixed => $plan256(null, null, null, 1)['retry_commit_watermark_next256']['durable_retry_count'], 5],
    'chunk size one current rows first' => [static fn (): mixed => array_slice($plan256(null, null, null, 1)['durable_publication_rowids_next256'], 0, 3), [7, 5, 3]],
    'chunk size three required chunks' => [static fn (): mixed => count($plan256(null, null, null, 3)['required_window_chunk_tokens_next253']), 1],
    'missing commit state held' => [static fn (): mixed => $plan256(null, null, $missingCommit256())['retry_commit_state_next256'], 'next-source-retry-held-for-commit-watermark-next256'],
    'missing commit complete false' => [static fn (): mixed => $plan256(null, null, $missingCommit256())['retry_commit_watermark_next256']['commit_source_complete'], false],
    'missing commit token recorded' => [static fn (): mixed => $plan256(null, null, $missingCommit256())['retry_commit_watermark_next256']['missing_commit_tokens'], [$requiredCommits256()[4]]],
    'missing commit unexpected empty' => [static fn (): mixed => $plan256(null, null, $missingCommit256())['retry_commit_watermark_next256']['unexpected_commit_tokens'], []],
    'missing commit durable retry count zero' => [static fn (): mixed => $plan256(null, null, $missingCommit256())['retry_commit_watermark_next256']['durable_retry_count'], 0],
    'missing commit current rows still durable' => [static fn (): mixed => array_slice(array_column($plan256(null, null, $missingCommit256())['durable_publication_rows_next256'], 'durable_next256'), 0, 3), [true, true, true]],
    'missing commit retry rows pending' => [static fn (): mixed => array_slice(array_column($plan256(null, null, $missingCommit256())['durable_publication_rows_next256'], 'durable_next256'), 3), [false, false, false, false, false]],
    'missing commit retry phases pending' => [static fn (): mixed => array_unique(array_slice(array_column($plan256(null, null, $missingCommit256())['durable_publication_rows_next256'], 'commit_phase_next256'), 3)), ['next-source-retry-pending']],
    'missing commit durable retry rowids empty' => [static fn (): mixed => $plan256(null, null, $missingCommit256())['durable_retry_rowids_next256'], []],
    'missing commit watermark token changes' => [static fn (): mixed => $plan256()['retry_commit_watermark_next256']['watermark_token'] === $plan256(null, null, $missingCommit256())['retry_commit_watermark_next256']['watermark_token'], false],
    'unexpected commit state held' => [static fn (): mixed => $plan256(null, null, $unexpectedCommit256())['retry_commit_state_next256'], 'next-source-retry-held-for-commit-watermark-next256'],
    'unexpected commit recorded' => [static fn (): mixed => $plan256(null, null, $unexpectedCommit256())['retry_commit_watermark_next256']['unexpected_commit_tokens'], [str_repeat('b', 64)]],
    'unexpected commit durable retry count zero' => [static fn (): mixed => $plan256(null, null, $unexpectedCommit256())['retry_commit_watermark_next256']['durable_retry_count'], 0],
    'missing chunk holds commit complete' => [static fn (): mixed => $plan256(null, $missingChunk256())['retry_commit_watermark_next256']['commit_source_complete'], false],
    'missing chunk retry not exposed' => [static fn (): mixed => $plan256(null, $missingChunk256())['retry_commit_watermark_next256']['retry_exposed'], false],
    'missing chunk required commits empty' => [static fn (): mixed => $plan256(null, $missingChunk256())['required_retry_commit_tokens_next256'], []],
    'missing chunk durable rowids current only' => [static fn (): mixed => $plan256(null, $missingChunk256())['durable_publication_rowids_next256'], [7, 5, 3]],
    'missing chunk durable retry rowids empty' => [static fn (): mixed => $plan256(null, $missingChunk256())['durable_retry_rowids_next256'], []],
    'missing yield holds commit complete' => [static fn (): mixed => $plan256($missingYield256())['retry_commit_watermark_next256']['commit_source_complete'], false],
    'missing yield retry not exposed' => [static fn (): mixed => $plan256($missingYield256())['retry_commit_watermark_next256']['retry_exposed'], false],
    'missing yield durable current only' => [static fn (): mixed => $plan256($missingYield256())['durable_publication_rowids_next256'], [7, 5, 3]],
    'base next253 release present when complete' => [static fn (): mixed => strlen((string) $plan256()['window_current_source_release_token_next253']), 64],
    'base next253 release null when chunk missing' => [static fn (): mixed => $plan256(null, $missingChunk256())['window_current_source_release_token_next253'], null],
    'current source table count' => [static fn (): mixed => count($plan256()['current_source_tables']['wp_options']), 8],
    'retry source table count' => [static fn (): mixed => count($plan256()['next_source_tables']['wp_options']), 8],
    'rolled back feed remains current' => [static fn (): mixed => in_array(3, array_column($plan256()['current_source_tables']['wp_options'], 'option_id'), true), true],
    'retry deletes timeout' => [static fn (): mixed => in_array(4, array_column($plan256()['next_source_tables']['wp_options'], 'option_id'), true), false],
    'retry updates plugin batch' => [static fn (): mixed => $plan256()['retry_commit_rows_next256'][0]['option_id'], 9],
    'dependency marker' => [static fn (): mixed => in_array('sqlite-rowvalue-returning-window-retry-commit-watermark-next256', $plan256()['dependencies_next256'], true), true],
    'wordpress marker' => [static fn (): mixed => in_array('wordpress-rowvalue-returning-window-current-source-next256', $plan256()['dependencies_next256'], true), true],
    'dependency closure' => [static fn (): mixed => str_contains($plan256()['dependency_closure_next256'], 'no new support component needed'), true],
    'non overlap next253' => [static fn (): mixed => str_contains($plan256()['non_overlap_next256'], 'next253'), true],
    'non overlap next248' => [static fn (): mixed => str_contains($plan256()['non_overlap_next256'], 'next248'), true],
    'non overlap btree' => [static fn (): mixed => str_contains($plan256()['non_overlap_next256'], 'B-tree'), true],
    'invalid chunk rejected by base' => [static fn (): mixed => SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNextPlan::executeNext256($tables256, [$yieldUpdate256], [$attemptUpdate256], [$retryUpdate256], $unique256, 'wp_options_rowvalue_window_current_next256', 'option_id', null, 0), InvalidArgumentException::class],
    'malformed empty yield rejected' => [static fn (): mixed => SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNextPlan::executeNext256($tables256, [], [$attemptUpdate256], [$retryUpdate256], $unique256), InvalidArgumentException::class],
    'malformed empty attempt rejected' => [static fn (): mixed => SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNextPlan::executeNext256($tables256, [$yieldUpdate256], [], [$retryUpdate256], $unique256), InvalidArgumentException::class],
    'malformed empty retry rejected' => [static fn (): mixed => SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNextPlan::executeNext256($tables256, [$yieldUpdate256], [$attemptUpdate256], [], $unique256), InvalidArgumentException::class],
    'malformed savepoint rejected' => [static fn (): mixed => SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNextPlan::executeNext256($tables256, [$yieldUpdate256], [$attemptUpdate256], [$retryUpdate256], $unique256, 'bad-name'), InvalidArgumentException::class],
];

$tests = [];
foreach ($cases256 as $name => [$callback, $expected]) {
    $tests['rowvalue update delete returning window current source next256 ' . $name] = static function (TestRunner $t) use ($callback, $expected): void {
        if (is_string($expected) && is_a($expected, Throwable::class, true)) {
            $t->throws($expected, $callback);
            return;
        }
        $t->same($expected, $callback());
    };
}

return $tests;
