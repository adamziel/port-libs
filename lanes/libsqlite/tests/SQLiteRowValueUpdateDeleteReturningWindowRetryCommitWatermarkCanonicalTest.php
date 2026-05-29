<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNextPlan;
use PortLibs\LibSqlite\SQLiteUpdateDeleteReturningSql;

$rowsWatermark = [
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
$tablesWatermark = ['wp_options' => $rowsWatermark];
$uniqueWatermark = [['blog_id', 'option_name']];

$yieldUpdateWatermark = "UPDATE wp_options SET (status, option_value, bytes) = ('yieldWatermark', option_value || ':yieldWatermark', bytes + 30) WHERE (blog_id, option_name) IN ((2, 'pending_theme'), (3, 'rewrite_rules')) RETURNING option_id, blog_id, option_name, status, bytes ORDER BY option_id";
$yieldDeleteWatermark = "DELETE FROM wp_options WHERE (blog_id, option_name) IN ((1, '_transient_feed')) RETURNING option_id, blog_id, option_name, status, bytes ORDER BY option_id";
$attemptUpdateWatermark = "UPDATE wp_options SET (status, option_value, bytes) = ('attemptWatermark', option_value || ':attemptWatermark', bytes + 5) WHERE (blog_id, option_name) IN ((2, 'pending_theme'), (3, 'rewrite_rules')) RETURNING option_id, blog_id, option_name, status, bytes ORDER BY option_id";
$attemptDeleteWatermark = "DELETE FROM wp_options WHERE (blog_id, option_name) IN ((3, 'orphaned_cache')) RETURNING option_id, blog_id, option_name, status, bytes ORDER BY option_id";
$retryUpdateWatermark = "UPDATE wp_options SET (status, option_value, bytes) = ('retryWatermark', option_value || ':retryWatermark', bytes + 20) WHERE (blog_id, option_name) IN ((2, 'pending_theme'), (3, 'rewrite_rules'), (4, 'plugin_batch')) RETURNING option_id, blog_id, option_name, status, bytes ORDER BY option_id";
$retryDeleteWatermark = "DELETE FROM wp_options WHERE (blog_id, option_name) IN ((1, '_transient_timeout_feed'), (4, 'home')) RETURNING option_id, blog_id, option_name, status, bytes ORDER BY option_id";

$planWatermark = static fn (?array $yieldAck = null, ?array $chunkAck = null, ?array $commitAck = null, int $chunkSize = 2): array => SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNextPlan::executeRetryCommitWatermark(
    $tablesWatermark,
    [$yieldUpdateWatermark, $yieldDeleteWatermark],
    [$attemptUpdateWatermark, $attemptDeleteWatermark],
    [$retryUpdateWatermark, $retryDeleteWatermark],
    $uniqueWatermark,
    'wp_options_rowvalue_window_retry_commit',
    'option_id',
    $yieldAck,
    $chunkSize,
    $chunkAck,
    $commitAck,
);
$requiredYieldWatermark = static fn (): array => $planWatermark()['required_yield_tickets_next245'];
$requiredChunksWatermark = static fn (): array => $planWatermark()['required_window_chunk_tokens_next253'];
$requiredCommitsWatermark = static fn (): array => $planWatermark()['required_retry_commit_tokens'];
$missingYieldWatermark = static fn (): array => array_slice($requiredYieldWatermark(), 0, 2);
$missingChunkWatermark = static fn (): array => array_slice($requiredChunksWatermark(), 0, 1);
$missingCommitWatermark = static fn (): array => array_slice($requiredCommitsWatermark(), 0, 4);
$unexpectedCommitWatermark = static fn (): array => [...$requiredCommitsWatermark(), str_repeat('b', 64)];

$casesWatermark = [
    'parser update row-value assignments' => [static fn (): mixed => array_keys(SQLiteUpdateDeleteReturningSql::parse($yieldUpdateWatermark)['assignments']), ['status', 'option_value', 'bytes']],
    'parser retry delete where' => [static fn (): mixed => SQLiteUpdateDeleteReturningSql::parse($retryDeleteWatermark)['where'], "(blog_id, option_name) IN ((1, '_transient_timeout_feed'), (4, 'home'))"],
    'plan status' => [static fn (): mixed => $planWatermark()['status'], 'rowvalue-update-delete-returning-window-retry-commit-watermark'],
    'inherits next253 retry exposed' => [static fn (): mixed => $planWatermark()['window_current_source_retry_exposed_next253'], true],
    'commit state durable' => [static fn (): mixed => $planWatermark()['retry_commit_state'], 'current-source-complete-next-source-retry-durable'],
    'watermark savepoint' => [static fn (): mixed => $planWatermark()['retry_commit_watermark']['savepoint'], 'wp_options_rowvalue_window_retry_commit'],
    'watermark source boundary' => [static fn (): mixed => $planWatermark()['retry_commit_watermark']['source_boundary'], 'current-source-complete-next-source-retry-durable'],
    'watermark chunk gate complete' => [static fn (): mixed => $planWatermark()['retry_commit_watermark']['current_chunk_gate_complete'], true],
    'watermark retry exposed' => [static fn (): mixed => $planWatermark()['retry_commit_watermark']['retry_exposed'], true],
    'required commit count' => [static fn (): mixed => $planWatermark()['retry_commit_watermark']['required_commit_count'], 5],
    'ack commit count' => [static fn (): mixed => $planWatermark()['retry_commit_watermark']['acknowledged_commit_count'], 5],
    'commit source complete' => [static fn (): mixed => $planWatermark()['retry_commit_watermark']['commit_source_complete'], true],
    'durable retry count' => [static fn (): mixed => $planWatermark()['retry_commit_watermark']['durable_retry_count'], 5],
    'watermark token length' => [static fn (): mixed => strlen($planWatermark()['retry_commit_watermark']['watermark_token']), 64],
    'required commit token lengths' => [static fn (): mixed => array_unique(array_map('strlen', $requiredCommitsWatermark())), [64]],
    'required commit tokens unique' => [static fn (): mixed => count(array_unique($requiredCommitsWatermark())), 5],
    'ack defaults to required' => [static fn (): mixed => $planWatermark()['acknowledged_retry_commit_tokens'], $requiredCommitsWatermark()],
    'retry commit rowids' => [static fn (): mixed => array_column($planWatermark()['retry_commit_rows'], 'option_id'), [9, 10, 7, 5, 4]],
    'retry commit sources' => [static fn (): mixed => array_unique(array_column($planWatermark()['retry_commit_rows'], 'source')), ['next-source-retry-window-next253']],
    'durable publication rowids' => [static fn (): mixed => $planWatermark()['durable_publication_rowids'], [7, 5, 3, 9, 10, 7, 5, 4]],
    'durable retry rowids' => [static fn (): mixed => $planWatermark()['durable_retry_rowids'], [9, 10, 7, 5, 4]],
    'durable ordinals' => [static fn (): mixed => array_column($planWatermark()['durable_publication_rows'], 'durable_ordinal'), [1, 2, 3, 4, 5, 6, 7, 8]],
    'current durable flags true' => [static fn (): mixed => array_slice(array_column($planWatermark()['durable_publication_rows'], 'durable'), 0, 3), [true, true, true]],
    'retry durable flags true' => [static fn (): mixed => array_slice(array_column($planWatermark()['durable_publication_rows'], 'durable'), 3), [true, true, true, true, true]],
    'current phases durable' => [static fn (): mixed => array_unique(array_slice(array_column($planWatermark()['durable_publication_rows'], 'commit_phase'), 0, 3)), ['current-source-window-durable']],
    'retry phases durable' => [static fn (): mixed => array_unique(array_slice(array_column($planWatermark()['durable_publication_rows'], 'commit_phase'), 3)), ['next-source-retry-durable']],
    'commit token count' => [static fn (): mixed => count(array_column($planWatermark()['durable_publication_rows'], 'commit_token')), 8],
    'commit token lengths' => [static fn (): mixed => array_unique(array_map('strlen', array_column($planWatermark()['durable_publication_rows'], 'commit_token'))), [64]],
    'commit tokens unique' => [static fn (): mixed => count(array_unique(array_column($planWatermark()['durable_publication_rows'], 'commit_token'))), 8],
    'chunk size one still durable retry count' => [static fn (): mixed => $planWatermark(null, null, null, 1)['retry_commit_watermark']['durable_retry_count'], 5],
    'chunk size one current rows first' => [static fn (): mixed => array_slice($planWatermark(null, null, null, 1)['durable_publication_rowids'], 0, 3), [7, 5, 3]],
    'chunk size three required chunks' => [static fn (): mixed => count($planWatermark(null, null, null, 3)['required_window_chunk_tokens_next253']), 1],
    'missing commit state held' => [static fn (): mixed => $planWatermark(null, null, $missingCommitWatermark())['retry_commit_state'], 'next-source-retry-held-for-commit-watermark'],
    'missing commit complete false' => [static fn (): mixed => $planWatermark(null, null, $missingCommitWatermark())['retry_commit_watermark']['commit_source_complete'], false],
    'missing commit token recorded' => [static fn (): mixed => $planWatermark(null, null, $missingCommitWatermark())['retry_commit_watermark']['missing_commit_tokens'], [$requiredCommitsWatermark()[4]]],
    'missing commit unexpected empty' => [static fn (): mixed => $planWatermark(null, null, $missingCommitWatermark())['retry_commit_watermark']['unexpected_commit_tokens'], []],
    'missing commit durable retry count zero' => [static fn (): mixed => $planWatermark(null, null, $missingCommitWatermark())['retry_commit_watermark']['durable_retry_count'], 0],
    'missing commit current rows still durable' => [static fn (): mixed => array_slice(array_column($planWatermark(null, null, $missingCommitWatermark())['durable_publication_rows'], 'durable'), 0, 3), [true, true, true]],
    'missing commit retry rows pending' => [static fn (): mixed => array_slice(array_column($planWatermark(null, null, $missingCommitWatermark())['durable_publication_rows'], 'durable'), 3), [false, false, false, false, false]],
    'missing commit retry phases pending' => [static fn (): mixed => array_unique(array_slice(array_column($planWatermark(null, null, $missingCommitWatermark())['durable_publication_rows'], 'commit_phase'), 3)), ['next-source-retry-pending']],
    'missing commit durable retry rowids empty' => [static fn (): mixed => $planWatermark(null, null, $missingCommitWatermark())['durable_retry_rowids'], []],
    'missing commit watermark token changes' => [static fn (): mixed => $planWatermark()['retry_commit_watermark']['watermark_token'] === $planWatermark(null, null, $missingCommitWatermark())['retry_commit_watermark']['watermark_token'], false],
    'unexpected commit state held' => [static fn (): mixed => $planWatermark(null, null, $unexpectedCommitWatermark())['retry_commit_state'], 'next-source-retry-held-for-commit-watermark'],
    'unexpected commit recorded' => [static fn (): mixed => $planWatermark(null, null, $unexpectedCommitWatermark())['retry_commit_watermark']['unexpected_commit_tokens'], [str_repeat('b', 64)]],
    'unexpected commit durable retry count zero' => [static fn (): mixed => $planWatermark(null, null, $unexpectedCommitWatermark())['retry_commit_watermark']['durable_retry_count'], 0],
    'missing chunk holds commit complete' => [static fn (): mixed => $planWatermark(null, $missingChunkWatermark())['retry_commit_watermark']['commit_source_complete'], false],
    'missing chunk retry not exposed' => [static fn (): mixed => $planWatermark(null, $missingChunkWatermark())['retry_commit_watermark']['retry_exposed'], false],
    'missing chunk required commits empty' => [static fn (): mixed => $planWatermark(null, $missingChunkWatermark())['required_retry_commit_tokens'], []],
    'missing chunk durable rowids current only' => [static fn (): mixed => $planWatermark(null, $missingChunkWatermark())['durable_publication_rowids'], [7, 5, 3]],
    'missing chunk durable retry rowids empty' => [static fn (): mixed => $planWatermark(null, $missingChunkWatermark())['durable_retry_rowids'], []],
    'missing yield holds commit complete' => [static fn (): mixed => $planWatermark($missingYieldWatermark())['retry_commit_watermark']['commit_source_complete'], false],
    'missing yield retry not exposed' => [static fn (): mixed => $planWatermark($missingYieldWatermark())['retry_commit_watermark']['retry_exposed'], false],
    'missing yield durable current only' => [static fn (): mixed => $planWatermark($missingYieldWatermark())['durable_publication_rowids'], [7, 5, 3]],
    'base next253 release present when complete' => [static fn (): mixed => strlen((string) $planWatermark()['window_current_source_release_token_next253']), 64],
    'base next253 release null when chunk missing' => [static fn (): mixed => $planWatermark(null, $missingChunkWatermark())['window_current_source_release_token_next253'], null],
    'current source table count' => [static fn (): mixed => count($planWatermark()['current_source_tables']['wp_options']), 8],
    'retry source table count' => [static fn (): mixed => count($planWatermark()['next_source_tables']['wp_options']), 8],
    'rolled back feed remains current' => [static fn (): mixed => in_array(3, array_column($planWatermark()['current_source_tables']['wp_options'], 'option_id'), true), true],
    'retry deletes timeout' => [static fn (): mixed => in_array(4, array_column($planWatermark()['next_source_tables']['wp_options'], 'option_id'), true), false],
    'retry updates plugin batch' => [static fn (): mixed => $planWatermark()['retry_commit_rows'][0]['option_id'], 9],
    'dependency marker' => [static fn (): mixed => in_array('sqlite-rowvalue-returning-window-retry-commit-watermark', $planWatermark()['dependencies'], true), true],
    'wordpress marker' => [static fn (): mixed => in_array('wordpress-rowvalue-returning-window-retry-commit-watermark', $planWatermark()['dependencies'], true), true],
    'dependency closure' => [static fn (): mixed => str_contains($planWatermark()['dependency_closure'], 'no new support component needed'), true],
    'non overlap next253' => [static fn (): mixed => str_contains($planWatermark()['non_overlap'], 'next253'), true],
    'non overlap next248' => [static fn (): mixed => str_contains($planWatermark()['non_overlap'], 'next248'), true],
    'non overlap btree' => [static fn (): mixed => str_contains($planWatermark()['non_overlap'], 'B-tree'), true],
    'invalid chunk rejected by base' => [static fn (): mixed => SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNextPlan::executeRetryCommitWatermark($tablesWatermark, [$yieldUpdateWatermark], [$attemptUpdateWatermark], [$retryUpdateWatermark], $uniqueWatermark, 'wp_options_rowvalue_window_retry_commit', 'option_id', null, 0), InvalidArgumentException::class],
    'malformed empty yield rejected' => [static fn (): mixed => SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNextPlan::executeRetryCommitWatermark($tablesWatermark, [], [$attemptUpdateWatermark], [$retryUpdateWatermark], $uniqueWatermark), InvalidArgumentException::class],
    'malformed empty attempt rejected' => [static fn (): mixed => SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNextPlan::executeRetryCommitWatermark($tablesWatermark, [$yieldUpdateWatermark], [], [$retryUpdateWatermark], $uniqueWatermark), InvalidArgumentException::class],
    'malformed empty retry rejected' => [static fn (): mixed => SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNextPlan::executeRetryCommitWatermark($tablesWatermark, [$yieldUpdateWatermark], [$attemptUpdateWatermark], [], $uniqueWatermark), InvalidArgumentException::class],
    'malformed savepoint rejected' => [static fn (): mixed => SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNextPlan::executeRetryCommitWatermark($tablesWatermark, [$yieldUpdateWatermark], [$attemptUpdateWatermark], [$retryUpdateWatermark], $uniqueWatermark, 'bad-name'), InvalidArgumentException::class],
];

$tests = [];
foreach ($casesWatermark as $name => [$callback, $expected]) {
    $tests['rowvalue update delete returning window retry commit watermark ' . $name] = static function (TestRunner $t) use ($callback, $expected): void {
        if (is_string($expected) && is_a($expected, Throwable::class, true)) {
            $t->throws($expected, $callback);
            return;
        }
        $t->same($expected, $callback());
    };
}

return $tests;
