<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNext253Plan;
use PortLibs\LibSqlite\SQLiteUpdateDeleteReturningSql;

$rows253 = [
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
$tables253 = ['wp_options' => $rows253];
$unique253 = [['blog_id', 'option_name']];

$yieldUpdate253 = "UPDATE wp_options SET (status, option_value, bytes) = ('yield253', option_value || ':yield253', bytes + 30) WHERE (blog_id, option_name) IN ((2, 'pending_theme'), (3, 'rewrite_rules')) RETURNING option_id, blog_id, option_name, status, bytes ORDER BY option_id";
$yieldDelete253 = "DELETE FROM wp_options WHERE (blog_id, option_name) IN ((1, '_transient_feed')) RETURNING option_id, blog_id, option_name, status, bytes ORDER BY option_id";
$attemptUpdate253 = "UPDATE wp_options SET (status, option_value, bytes) = ('attempt253', option_value || ':attempt253', bytes + 5) WHERE (blog_id, option_name) IN ((2, 'pending_theme'), (3, 'rewrite_rules')) RETURNING option_id, blog_id, option_name, status, bytes ORDER BY option_id";
$attemptDelete253 = "DELETE FROM wp_options WHERE (blog_id, option_name) IN ((3, 'orphaned_cache')) RETURNING option_id, blog_id, option_name, status, bytes ORDER BY option_id";
$retryUpdate253 = "UPDATE wp_options SET (status, option_value, bytes) = ('retry253', option_value || ':retry253', bytes + 20) WHERE (blog_id, option_name) IN ((2, 'pending_theme'), (3, 'rewrite_rules'), (4, 'plugin_batch')) RETURNING option_id, blog_id, option_name, status, bytes ORDER BY option_id";
$retryDelete253 = "DELETE FROM wp_options WHERE (blog_id, option_name) IN ((1, '_transient_timeout_feed'), (4, 'home')) RETURNING option_id, blog_id, option_name, status, bytes ORDER BY option_id";

$plan253 = static fn (?array $yieldAck = null, ?array $chunkAck = null, int $chunkSize = 2): array => SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNext253Plan::execute(
    $tables253,
    [$yieldUpdate253, $yieldDelete253],
    [$attemptUpdate253, $attemptDelete253],
    [$retryUpdate253, $retryDelete253],
    $unique253,
    'wp_options_rowvalue_window_current_next253',
    'option_id',
    $yieldAck,
    $chunkSize,
    $chunkAck,
);
$requiredYield253 = static fn (): array => $plan253()['required_yield_tickets_next245'];
$requiredChunks253 = static fn (): array => $plan253()['required_window_chunk_tokens_next253'];
$missingChunk253 = static fn (): array => array_slice($requiredChunks253(), 0, 1);
$unexpectedChunk253 = static fn (): array => [...$requiredChunks253(), str_repeat('a', 64)];
$missingYield253 = static fn (): array => array_slice($requiredYield253(), 0, 2);

$cases253 = [
    'parser update row-value where' => [static fn (): mixed => SQLiteUpdateDeleteReturningSql::parse($yieldUpdate253)['where'], "(blog_id, option_name) IN ((2, 'pending_theme'), (3, 'rewrite_rules'))"],
    'parser delete returning' => [static fn (): mixed => SQLiteUpdateDeleteReturningSql::parse($retryDelete253)['returning'], 'option_id, blog_id, option_name, status, bytes'],
    'plan status' => [static fn (): mixed => $plan253()['status'], 'rowvalue-update-delete-returning-window-current-source-next253'],
    'inherits next249 status overwritten' => [static fn (): mixed => $plan253()['current_source_yield_complete_next249'], true],
    'required chunk count' => [static fn (): mixed => count($plan253()['required_window_chunk_tokens_next253']), 2],
    'ack chunk count' => [static fn (): mixed => count($plan253()['acknowledged_window_chunk_tokens_next253']), 2],
    'chunk gate required count' => [static fn (): mixed => $plan253()['window_current_source_chunk_gate_next253']['required_chunk_count'], 2],
    'chunk gate acknowledged count' => [static fn (): mixed => $plan253()['window_current_source_chunk_gate_next253']['acknowledged_chunk_count'], 2],
    'chunk gate complete' => [static fn (): mixed => $plan253()['window_current_source_chunk_gate_next253']['chunk_source_complete'], true],
    'chunk gate retry exposed' => [static fn (): mixed => $plan253()['window_current_source_retry_exposed_next253'], true],
    'chunk gate boundary exposed' => [static fn (): mixed => $plan253()['window_current_source_chunk_gate_next253']['source_boundary'], 'current-source-window-chunks-complete-next253'],
    'current chunks rowids' => [static fn (): mixed => array_column($plan253()['current_source_window_chunks_next253'], 'rowids'), [[7, 5], [3]]],
    'current chunks first ordinals' => [static fn (): mixed => array_column($plan253()['current_source_window_chunks_next253'], 'first_ordinal'), [1, 3]],
    'current chunks last ordinals' => [static fn (): mixed => array_column($plan253()['current_source_window_chunks_next253'], 'last_ordinal'), [2, 3]],
    'cursor rowids exposed' => [static fn (): mixed => $plan253()['window_current_source_cursor_rowids_next253'], [7, 5, 3, 9, 10, 7, 5, 4]],
    'cursor sources exposed' => [static fn (): mixed => array_count_values(array_column($plan253()['window_current_source_cursor_next253'], 'source')), ['current-window-chunk-next253' => 3, 'next-source-retry-window-next253' => 5]],
    'current cursor chunks' => [static fn (): mixed => array_slice(array_column($plan253()['window_current_source_cursor_next253'], 'chunk'), 0, 3), [1, 1, 2]],
    'current cursor ordinals' => [static fn (): mixed => array_slice(array_column($plan253()['window_current_source_cursor_next253'], 'ordinal_in_chunk'), 0, 3), [1, 2, 1]],
    'retry cursor ordinals' => [static fn (): mixed => array_slice(array_column($plan253()['window_current_source_cursor_next253'], 'ordinal_in_chunk'), 3), [1, 2, 3, 4, 5]],
    'current cursor complete flags' => [static fn (): mixed => array_slice(array_column($plan253()['window_current_source_cursor_next253'], 'chunk_complete'), 0, 3), [true, true, true]],
    'retry cursor complete flags' => [static fn (): mixed => array_slice(array_column($plan253()['window_current_source_cursor_next253'], 'chunk_complete'), 3), [true, true, true, true, true]],
    'cursor token count' => [static fn (): mixed => count($plan253()['window_current_source_cursor_tokens_next253']), 8],
    'cursor token lengths' => [static fn (): mixed => array_unique(array_map('strlen', $plan253()['window_current_source_cursor_tokens_next253'])), [64]],
    'cursor tokens unique' => [static fn (): mixed => count(array_unique($plan253()['window_current_source_cursor_tokens_next253'])), 8],
    'retry rowids exposed' => [static fn (): mixed => $plan253()['window_current_source_retry_rowids_next253'], [9, 10, 7, 5, 4]],
    'release token length' => [static fn (): mixed => strlen((string) $plan253()['window_current_source_release_token_next253']), 64],
    'release token stable' => [static fn (): mixed => $plan253()['window_current_source_release_token_next253'] === $plan253()['window_current_source_release_token_next253'], true],
    'chunk size one required count' => [static fn (): mixed => count($plan253(null, null, 1)['required_window_chunk_tokens_next253']), 3],
    'chunk size three required count' => [static fn (): mixed => count($plan253(null, null, 3)['required_window_chunk_tokens_next253']), 1],
    'chunk size one cursor current rows' => [static fn (): mixed => array_slice($plan253(null, null, 1)['window_current_source_cursor_rowids_next253'], 0, 3), [7, 5, 3]],
    'missing chunk holds retry' => [static fn (): mixed => $plan253(null, $missingChunk253())['window_current_source_retry_exposed_next253'], false],
    'missing chunk release token null' => [static fn (): mixed => $plan253(null, $missingChunk253())['window_current_source_release_token_next253'], null],
    'missing chunk cursor rowids current only' => [static fn (): mixed => $plan253(null, $missingChunk253())['window_current_source_cursor_rowids_next253'], [7, 5, 3]],
    'missing chunk records token' => [static fn (): mixed => $plan253(null, $missingChunk253())['window_current_source_chunk_gate_next253']['missing_chunk_tokens'], [$requiredChunks253()[1]]],
    'missing chunk unexpected empty' => [static fn (): mixed => $plan253(null, $missingChunk253())['window_current_source_chunk_gate_next253']['unexpected_chunk_tokens'], []],
    'missing chunk boundary held' => [static fn (): mixed => $plan253(null, $missingChunk253())['window_current_source_chunk_gate_next253']['source_boundary'], 'next-source-retry-held-for-current-window-chunks-next253'],
    'missing chunk current flags false' => [static fn (): mixed => array_column($plan253(null, $missingChunk253())['window_current_source_cursor_next253'], 'chunk_complete'), [false, false, false]],
    'unexpected chunk holds retry' => [static fn (): mixed => $plan253(null, $unexpectedChunk253())['window_current_source_retry_exposed_next253'], false],
    'unexpected chunk records token' => [static fn (): mixed => $plan253(null, $unexpectedChunk253())['window_current_source_chunk_gate_next253']['unexpected_chunk_tokens'], [str_repeat('a', 64)]],
    'unexpected chunk release token null' => [static fn (): mixed => $plan253(null, $unexpectedChunk253())['window_current_source_release_token_next253'], null],
    'missing yield still holds at next249' => [static fn (): mixed => $plan253($missingYield253())['current_source_yield_complete_next249'], false],
    'missing yield cursor current only' => [static fn (): mixed => $plan253($missingYield253())['window_current_source_cursor_rowids_next253'], [7, 5, 3]],
    'missing yield flags false' => [static fn (): mixed => array_column($plan253($missingYield253())['window_current_source_cursor_next253'], 'chunk_complete'), [false, false, false]],
    'missing yield retry rowids held' => [static fn (): mixed => $plan253($missingYield253())['window_current_source_retry_rowids_next253'], []],
    'missing yield release token null' => [static fn (): mixed => $plan253($missingYield253())['window_current_source_release_token_next253'], null],
    'missing yield boundary held' => [static fn (): mixed => $plan253($missingYield253())['window_current_source_chunk_gate_next253']['source_boundary'], 'next-source-retry-held-for-current-window-chunks-next253'],
    'missing yield ticket gate recorded' => [static fn (): mixed => $plan253($missingYield253())['window_current_source_chunk_gate_next253']['yield_tickets_complete'], false],
    'next249 retry exposed false on missing yield' => [static fn (): mixed => $plan253($missingYield253())['retry_window_exposed_next249'], false],
    'yield sequence inherited' => [static fn (): mixed => $plan253()['window_yield_sequence_next249'], ['yield|1|7|rewrite_rules|rewrite_rules:39:39', 'yield|2|5|pending_theme|pending_theme:37:76', 'yield|3|3|_transient_feed|_transient_feed:12:88']],
    'retry sequence inherited first two' => [static fn (): mixed => array_slice($plan253()['retry_window_sequence_next249'], 0, 2), ['retry-release|1|9|plugin_batch|plugin_batch:31:31', 'retry-release|2|10|home|home:31:62']],
    'current source table count' => [static fn (): mixed => count($plan253()['current_source_tables']['wp_options']), 8],
    'current source deletes retry rows' => [static fn (): mixed => array_values(array_intersect([4, 10], array_column($plan253()['current_source_tables']['wp_options'], 'option_id'))), []],
    'rolled back feed remains' => [static fn (): mixed => in_array(3, array_column($plan253()['current_source_tables']['wp_options'], 'option_id'), true), true],
    'suppressed orphan remains' => [static fn (): mixed => in_array(8, array_column($plan253()['current_source_tables']['wp_options'], 'option_id'), true), true],
    'dependency marker' => [static fn (): mixed => in_array('sqlite-rowvalue-returning-window-current-source-chunk-gate-next253', $plan253()['dependencies_next253'], true), true],
    'wordpress marker' => [static fn (): mixed => in_array('wordpress-rowvalue-returning-window-current-source-next253', $plan253()['dependencies_next253'], true), true],
    'dependency closure' => [static fn (): mixed => str_contains($plan253()['dependency_closure_next253'], 'no new support component needed'), true],
    'non overlap next249' => [static fn (): mixed => str_contains($plan253()['non_overlap_next253'], 'next249'), true],
    'non overlap next248' => [static fn (): mixed => str_contains($plan253()['non_overlap_next253'], 'next248'), true],
    'non overlap btree' => [static fn (): mixed => str_contains($plan253()['non_overlap_next253'], 'B-tree'), true],
    'invalid chunk rejected by base' => [static fn (): mixed => SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNext253Plan::execute($tables253, [$yieldUpdate253], [$attemptUpdate253], [$retryUpdate253], $unique253, 'wp_options_rowvalue_window_current_next253', 'option_id', null, 0), InvalidArgumentException::class],
    'malformed empty yield rejected' => [static fn (): mixed => SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNext253Plan::execute($tables253, [], [$attemptUpdate253], [$retryUpdate253], $unique253), InvalidArgumentException::class],
    'malformed empty attempt rejected' => [static fn (): mixed => SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNext253Plan::execute($tables253, [$yieldUpdate253], [], [$retryUpdate253], $unique253), InvalidArgumentException::class],
    'malformed empty retry rejected' => [static fn (): mixed => SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNext253Plan::execute($tables253, [$yieldUpdate253], [$attemptUpdate253], [], $unique253), InvalidArgumentException::class],
    'malformed savepoint rejected' => [static fn (): mixed => SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNext253Plan::execute($tables253, [$yieldUpdate253], [$attemptUpdate253], [$retryUpdate253], $unique253, 'bad-name'), InvalidArgumentException::class],
];

$tests = [];
foreach ($cases253 as $name => [$callback, $expected]) {
    $tests['rowvalue update delete returning window current source next253 ' . $name] = static function (TestRunner $t) use ($callback, $expected): void {
        if (is_string($expected) && is_a($expected, Throwable::class, true)) {
            $t->throws($expected, $callback);
            return;
        }
        $t->same($expected, $callback());
    };
}

return $tests;
