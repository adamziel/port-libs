<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNextPlan;
use PortLibs\LibSqlite\SQLiteUpdateDeleteReturningSql;

$rows257 = [
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
$tables257 = ['wp_options' => $rows257];
$unique257 = [['blog_id', 'option_name']];

$yieldUpdate257 = "UPDATE wp_options SET (status, option_value, bytes) = ('yield257', option_value || ':yield257', bytes + 30) WHERE (blog_id, option_name) IN ((2, 'pending_theme'), (3, 'rewrite_rules')) RETURNING option_id, blog_id, option_name, status, bytes ORDER BY option_id";
$yieldDelete257 = "DELETE FROM wp_options WHERE (blog_id, option_name) IN ((1, '_transient_feed')) RETURNING option_id, blog_id, option_name, status, bytes ORDER BY option_id";
$attemptUpdate257 = "UPDATE wp_options SET (status, option_value, bytes) = ('attempt257', option_value || ':attempt257', bytes + 5) WHERE (blog_id, option_name) IN ((2, 'pending_theme'), (3, 'rewrite_rules')) RETURNING option_id, blog_id, option_name, status, bytes ORDER BY option_id";
$attemptDelete257 = "DELETE FROM wp_options WHERE (blog_id, option_name) IN ((3, 'orphaned_cache')) RETURNING option_id, blog_id, option_name, status, bytes ORDER BY option_id";
$retryUpdate257 = "UPDATE wp_options SET (status, option_value, bytes) = ('retry257', option_value || ':retry257', bytes + 20) WHERE (blog_id, option_name) IN ((2, 'pending_theme'), (3, 'rewrite_rules'), (4, 'plugin_batch')) RETURNING option_id, blog_id, option_name, status, bytes ORDER BY option_id";
$retryDelete257 = "DELETE FROM wp_options WHERE (blog_id, option_name) IN ((1, '_transient_timeout_feed'), (4, 'home')) RETURNING option_id, blog_id, option_name, status, bytes ORDER BY option_id";

$plan257 = static fn (?array $yieldAck = null, ?array $chunkAck = null, int $chunkSize = 2): array => SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNextPlan::executeDeleteRetryPublication(
    $tables257,
    [$yieldUpdate257, $yieldDelete257],
    [$attemptUpdate257, $attemptDelete257],
    [$retryUpdate257, $retryDelete257],
    $unique257,
    'wp_options_rowvalue_window_current_next257',
    'option_id',
    $yieldAck,
    $chunkSize,
    $chunkAck,
);
$requiredYield257 = static fn (): array => $plan257()['required_yield_tickets_next245'];
$requiredChunks257 = static fn (): array => $plan257()['required_window_chunk_tokens_next253'];
$missingChunk257 = static fn (): array => array_slice($requiredChunks257(), 0, 1);
$missingYield257 = static fn (): array => array_slice($requiredYield257(), 0, 2);
$unexpectedChunk257 = static fn (): array => [...$requiredChunks257(), str_repeat('b', 64)];

$cases257 = [
    'parser delete action' => [static fn (): mixed => SQLiteUpdateDeleteReturningSql::parse($yieldDelete257)['action'], 'delete'],
    'parser retry delete where' => [static fn (): mixed => SQLiteUpdateDeleteReturningSql::parse($retryDelete257)['where'], "(blog_id, option_name) IN ((1, '_transient_timeout_feed'), (4, 'home'))"],
    'plan status' => [static fn (): mixed => $plan257()['status'], 'rowvalue-update-delete-returning-window-current-source-next257'],
    'inherits chunk gate complete' => [static fn (): mixed => $plan257()['window_current_source_chunk_gate_next253']['chunk_source_complete'], true],
    'inherits retry exposed' => [static fn (): mixed => $plan257()['window_current_source_retry_exposed_next253'], true],
    'gate current count' => [static fn (): mixed => $plan257()['delete_returning_tombstone_gate_next257']['current_source_delete_count'], 1],
    'gate retry count' => [static fn (): mixed => $plan257()['delete_returning_tombstone_gate_next257']['next_source_retry_delete_count'], 2],
    'gate current rowids' => [static fn (): mixed => $plan257()['delete_returning_tombstone_gate_next257']['current_source_delete_rowids'], [3]],
    'gate retry rowids' => [static fn (): mixed => $plan257()['delete_returning_tombstone_gate_next257']['next_source_retry_delete_rowids'], [4, 10]],
    'gate complete' => [static fn (): mixed => $plan257()['delete_returning_tombstone_gate_next257']['current_source_tombstones_complete'], true],
    'gate retry tombstones exposed' => [static fn (): mixed => $plan257()['delete_returning_tombstone_gate_next257']['next_source_retry_tombstones_exposed'], true],
    'gate boundary exposed' => [static fn (): mixed => $plan257()['delete_returning_tombstone_gate_next257']['source_boundary'], 'current-source-delete-tombstones-before-next-source-retry-next257'],
    'gate no blockers' => [static fn (): mixed => $plan257()['delete_returning_tombstone_gate_next257']['blocked_reasons'], []],
    'current tombstone rowids' => [static fn (): mixed => array_column($plan257()['current_source_delete_tombstones_next257'], 'option_id'), [3]],
    'current tombstone names' => [static fn (): mixed => array_column($plan257()['current_source_delete_tombstones_next257'], 'option_name'), ['_transient_feed']],
    'current tombstone source' => [static fn (): mixed => array_column($plan257()['current_source_delete_tombstones_next257'], 'source'), ['current-source-yield-next257']],
    'current tombstone statement ordinal' => [static fn (): mixed => array_column($plan257()['current_source_delete_tombstones_next257'], 'statement_ordinal_next257'), [2]],
    'current tombstone delete ordinal' => [static fn (): mixed => array_column($plan257()['current_source_delete_tombstones_next257'], 'delete_ordinal_next257'), [1]],
    'current tombstone token length' => [static fn (): mixed => strlen((string) $plan257()['current_source_delete_tombstones_next257'][0]['tombstone_token_next257']), 64],
    'suppressed tombstone rowids' => [static fn (): mixed => array_column($plan257()['suppressed_attempt_delete_tombstones_next257'], 'option_id'), [8]],
    'suppressed tombstone invisible source in stream' => [static fn (): mixed => $plan257()['delete_returning_publication_stream_next257'][1]['source'], 'suppressed-attempt-delete-returning-next257'],
    'suppressed tombstone stream invisible' => [static fn (): mixed => $plan257()['delete_returning_publication_stream_next257'][1]['visible'], false],
    'retry tombstone rowids' => [static fn (): mixed => array_column($plan257()['next_source_retry_delete_tombstones_next257'], 'option_id'), [4, 10]],
    'retry tombstone names' => [static fn (): mixed => array_column($plan257()['next_source_retry_delete_tombstones_next257'], 'option_name'), ['_transient_timeout_feed', 'home']],
    'held retry tombstones empty when exposed' => [static fn (): mixed => $plan257()['held_next_source_retry_delete_tombstones_next257'], []],
    'publication rowids prefix' => [static fn (): mixed => array_slice($plan257()['delete_returning_publication_rowids_next257'], 0, 4), [3, 8, 4, 10]],
    'publication rowids full' => [static fn (): mixed => $plan257()['delete_returning_publication_rowids_next257'], [3, 8, 4, 10, 9, 10, 7, 5, 4]],
    'publication source prefix' => [static fn (): mixed => array_slice($plan257()['delete_returning_publication_sources_next257'], 0, 4), ['current-delete-returning-next257', 'suppressed-attempt-delete-returning-next257', 'next-source-retry-delete-returning-next257', 'next-source-retry-delete-returning-next257']],
    'publication retry window suffix count' => [static fn (): mixed => count(array_filter($plan257()['delete_returning_publication_sources_next257'], static fn (string $source): bool => $source === 'next-source-retry-window-row-next257')), 5],
    'publication ordinals' => [static fn (): mixed => array_column($plan257()['delete_returning_publication_stream_next257'], 'publication_ordinal_next257'), [1, 2, 3, 4, 5, 6, 7, 8, 9]],
    'publication tokens count' => [static fn (): mixed => count($plan257()['delete_returning_publication_tokens_next257']), 9],
    'publication tokens unique' => [static fn (): mixed => count(array_unique($plan257()['delete_returning_publication_tokens_next257'])), 9],
    'publication token lengths' => [static fn (): mixed => array_unique(array_map('strlen', $plan257()['delete_returning_publication_tokens_next257'])), [64]],
    'release token length' => [static fn (): mixed => strlen((string) $plan257()['delete_returning_release_token_next257']), 64],
    'release token stable' => [static fn (): mixed => $plan257()['delete_returning_release_token_next257'] === $plan257()['delete_returning_release_token_next257'], true],
    'missing chunk gate holds retry' => [static fn (): mixed => $plan257(null, $missingChunk257())['delete_returning_tombstone_gate_next257']['next_source_retry_tombstones_exposed'], false],
    'missing chunk held rowids' => [static fn (): mixed => array_column($plan257(null, $missingChunk257())['held_next_source_retry_delete_tombstones_next257'], 'option_id'), [4, 10]],
    'missing chunk exposed retry empty' => [static fn (): mixed => $plan257(null, $missingChunk257())['next_source_retry_delete_tombstones_next257'], []],
    'missing chunk stream rowids current and suppressed only' => [static fn (): mixed => $plan257(null, $missingChunk257())['delete_returning_publication_rowids_next257'], [3, 8]],
    'missing chunk release null' => [static fn (): mixed => $plan257(null, $missingChunk257())['delete_returning_release_token_next257'], null],
    'missing chunk blocker' => [static fn (): mixed => $plan257(null, $missingChunk257())['delete_returning_tombstone_gate_next257']['blocked_reasons'], ['current-source-window-chunks-incomplete-next257']],
    'unexpected chunk gate holds retry' => [static fn (): mixed => $plan257(null, $unexpectedChunk257())['delete_returning_tombstone_gate_next257']['next_source_retry_tombstones_exposed'], false],
    'unexpected chunk blocker' => [static fn (): mixed => $plan257(null, $unexpectedChunk257())['delete_returning_tombstone_gate_next257']['blocked_reasons'], ['current-source-window-chunks-incomplete-next257']],
    'missing yield gate holds retry' => [static fn (): mixed => $plan257($missingYield257())['delete_returning_tombstone_gate_next257']['next_source_retry_tombstones_exposed'], false],
    'missing yield blockers' => [static fn (): mixed => $plan257($missingYield257())['delete_returning_tombstone_gate_next257']['blocked_reasons'], ['current-source-yield-tickets-incomplete-next257']],
    'missing yield stream rowids current and suppressed only' => [static fn (): mixed => $plan257($missingYield257())['delete_returning_publication_rowids_next257'], [3, 8]],
    'chunk size one still exposes retry' => [static fn (): mixed => $plan257(null, null, 1)['delete_returning_tombstone_gate_next257']['next_source_retry_tombstones_exposed'], true],
    'chunk size one required chunks' => [static fn (): mixed => count($plan257(null, null, 1)['required_window_chunk_tokens_next253']), 3],
    'chunk size three required chunks' => [static fn (): mixed => count($plan257(null, null, 3)['required_window_chunk_tokens_next253']), 1],
    'current source table keeps yielded delete after rollback semantics' => [static fn (): mixed => in_array(3, array_column($plan257()['current_source_tables']['wp_options'], 'option_id'), true), true],
    'current source table removes retry deletes' => [static fn (): mixed => array_values(array_intersect([4, 10], array_column($plan257()['current_source_tables']['wp_options'], 'option_id'))), []],
    'suppressed attempt delete remains in table' => [static fn (): mixed => in_array(8, array_column($plan257()['current_source_tables']['wp_options'], 'option_id'), true), true],
    'dependency marker' => [static fn (): mixed => in_array('sqlite-rowvalue-delete-returning-current-source-tombstone-gate-next257', $plan257()['dependencies_next257'], true), true],
    'wordpress marker' => [static fn (): mixed => in_array('wordpress-rowvalue-returning-window-delete-retry-publication', $plan257()['dependencies_next257'], true), true],
    'dependency closure' => [static fn (): mixed => str_contains($plan257()['dependency_closure_next257'], 'no new support component needed'), true],
    'non overlap next253' => [static fn (): mixed => str_contains($plan257()['non_overlap_next257'], 'next253'), true],
    'non overlap wal' => [static fn (): mixed => str_contains($plan257()['non_overlap_next257'], 'WAL/VFS'), true],
    'non overlap btree' => [static fn (): mixed => str_contains($plan257()['non_overlap_next257'], 'B-tree'), true],
    'bad chunk rejected by base' => [static fn (): mixed => SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNextPlan::executeDeleteRetryPublication($tables257, [$yieldUpdate257], [$attemptUpdate257], [$retryUpdate257], $unique257, 'wp_options_rowvalue_window_current_next257', 'option_id', null, 0), InvalidArgumentException::class],
    'malformed empty yield rejected' => [static fn (): mixed => SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNextPlan::executeDeleteRetryPublication($tables257, [], [$attemptUpdate257], [$retryUpdate257], $unique257), InvalidArgumentException::class],
    'malformed empty attempt rejected' => [static fn (): mixed => SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNextPlan::executeDeleteRetryPublication($tables257, [$yieldUpdate257], [], [$retryUpdate257], $unique257), InvalidArgumentException::class],
    'malformed empty retry rejected' => [static fn (): mixed => SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNextPlan::executeDeleteRetryPublication($tables257, [$yieldUpdate257], [$attemptUpdate257], [], $unique257), InvalidArgumentException::class],
    'malformed savepoint rejected' => [static fn (): mixed => SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNextPlan::executeDeleteRetryPublication($tables257, [$yieldUpdate257], [$attemptUpdate257], [$retryUpdate257], $unique257, 'bad-name'), InvalidArgumentException::class],
];

$tests = [];
foreach ($cases257 as $name => [$callback, $expected]) {
    $tests['rowvalue update delete returning window current source next257 ' . $name] = static function (TestRunner $t) use ($callback, $expected): void {
        if (is_string($expected) && is_a($expected, Throwable::class, true)) {
            $t->throws($expected, $callback);
            return;
        }
        $t->same($expected, $callback());
    };
}

return $tests;
