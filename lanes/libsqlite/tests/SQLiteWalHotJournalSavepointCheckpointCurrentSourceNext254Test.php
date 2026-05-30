<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan;

require_once __DIR__ . '/../src/SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan.php';

$tests = [];

$hash = static fn (string $value): string => hash('sha256', $value);
$databaseDigest = $hash('next254 checkpoint database image');
$pageCacheDigest = $hash('next254 clean page cache image');
$cachePlan = [
    'status' => 'wal-hot-journal-savepoint-checkpoint-current-source-next250',
    'cache_invalidation_admitted' => true,
    'source_token' => 'wp-next254-current-source',
    'commit_generation' => 254,
    'schema_cookie' => 954,
    'database_digest' => $databaseDigest,
    'page_cache_digest' => $pageCacheDigest,
    'checkpoint_frame' => 41,
    'dirty_pages' => [1, 2, 5, 8],
    'commit_frames' => [38, 40, 41],
    'reader_names' => ['schema-reader', 'options-reader', 'autoload-reader'],
    'receipt_names' => ['invalidate-schema-cache', 'clear-options-readmark', 'refresh-schema-cookie', 'refresh-wal-index'],
    'operation_names' => ['admit_checkpoint_cache_invalidation_current_source_next250'],
    'dependencies' => ['sqlite-wal-hot-journal-savepoint-checkpoint-current-source-next250'],
];

$lease = static function (string $name, string $kind, array $pages, array $frames, array $readers, array $cacheReceipts, array $override = []) use ($cachePlan, $databaseDigest, $pageCacheDigest): array {
    return array_replace([
        'name' => $name,
        'kind' => $kind,
        'source_token' => $cachePlan['source_token'],
        'commit_generation' => $cachePlan['commit_generation'],
        'schema_cookie' => $cachePlan['schema_cookie'],
        'database_digest' => $databaseDigest,
        'page_cache_digest' => $pageCacheDigest,
        'checkpoint_frame' => $cachePlan['checkpoint_frame'],
        'page_numbers' => $pages,
        'commit_frames' => $frames,
        'reader_names' => $readers,
        'cache_receipt_names' => $cacheReceipts,
        'statement_reprepared' => true,
        'root_page_digest_matched' => true,
        'read_transaction_open' => true,
        'hot_journal_visible' => false,
        'savepoint_depth' => 0,
    ], $override);
};

$leases = [
    $lease('schema-statement-lease', 'schema-statement', [1], [38], ['schema-reader'], ['invalidate-schema-cache']),
    $lease('options-table-root-lease', 'table-root', [2, 5], [40], ['options-reader'], ['clear-options-readmark']),
    $lease('autoload-index-root-lease', 'index-root', [8], [41], ['autoload-reader'], ['refresh-schema-cookie']),
    $lease('read-transaction-lease', 'read-transaction', [1, 2, 5, 8], [38, 40, 41], ['schema-reader', 'options-reader', 'autoload-reader'], ['invalidate-schema-cache', 'clear-options-readmark', 'refresh-schema-cookie', 'refresh-wal-index']),
];

$plan = static fn (array $base = [], ?array $leaseRows = null): array => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next254AdmitCurrentSourceLeases(
    array_replace($cachePlan, $base),
    $leaseRows ?? $leases
);
$blocked = static fn (array $override): array => $plan([], [$leases[0], $lease('options-table-root-blocked', 'table-root', [2, 5], [40], ['options-reader'], ['clear-options-readmark'], $override), $leases[2], $leases[3]]);
$missingKind = static fn (): array => $plan([], [$leases[0], $leases[1], $leases[2]]);
$missingReader = static fn (): array => $plan([], [
    $lease('schema-statement-lease', 'schema-statement', [1], [38], ['schema-reader'], ['invalidate-schema-cache']),
    $lease('options-table-root-lease', 'table-root', [2, 5], [40], ['options-reader'], ['clear-options-readmark']),
    $lease('autoload-index-root-lease', 'index-root', [8], [41], ['options-reader'], ['refresh-schema-cookie']),
    $lease('read-transaction-lease', 'read-transaction', [1, 2, 5, 8], [38, 40, 41], ['schema-reader', 'options-reader'], ['invalidate-schema-cache', 'clear-options-readmark', 'refresh-schema-cookie', 'refresh-wal-index']),
]);
$missingPage = static fn (): array => $plan([], [
    $leases[0],
    $lease('options-table-root-lease', 'table-root', [2, 5], [40], ['options-reader'], ['clear-options-readmark']),
    $lease('autoload-index-root-lease', 'index-root', [5], [41], ['autoload-reader'], ['refresh-schema-cookie']),
    $lease('read-transaction-lease', 'read-transaction', [1, 2, 5], [38, 40, 41], ['schema-reader', 'options-reader', 'autoload-reader'], ['invalidate-schema-cache', 'clear-options-readmark', 'refresh-schema-cookie', 'refresh-wal-index']),
]);
$missingFrame = static fn (): array => $plan([], [
    $leases[0],
    $leases[1],
    $lease('autoload-index-root-lease', 'index-root', [8], [40], ['autoload-reader'], ['refresh-schema-cookie']),
    $lease('read-transaction-lease', 'read-transaction', [1, 2, 5, 8], [38, 40], ['schema-reader', 'options-reader', 'autoload-reader'], ['invalidate-schema-cache', 'clear-options-readmark', 'refresh-schema-cookie', 'refresh-wal-index']),
]);
$missingCacheReceipt = static fn (): array => $plan([], [
    $leases[0],
    $leases[1],
    $leases[2],
    $lease('read-transaction-lease', 'read-transaction', [1, 2, 5, 8], [38, 40, 41], ['schema-reader', 'options-reader', 'autoload-reader'], ['invalidate-schema-cache', 'clear-options-readmark', 'refresh-schema-cookie']),
]);
$duplicateLease = static fn (): array => $plan([], [$leases[0], $lease('schema-statement-lease', 'table-root', [2, 5], [40], ['options-reader'], ['clear-options-readmark']), $leases[2], $leases[3]]);

$cases = [
    'status' => [static fn (): mixed => $plan()['status'], 'wal-hot-journal-savepoint-checkpoint-current-source-next254'],
    'reason' => [static fn (): mixed => $plan()['reason'], 'checkpoint_current_source_leases_admitted'],
    'base status' => [static fn (): mixed => $plan()['base_status'], 'wal-hot-journal-savepoint-checkpoint-current-source-next250'],
    'source token' => [static fn (): mixed => $plan()['source_token'], 'wp-next254-current-source'],
    'commit generation' => [static fn (): mixed => $plan()['commit_generation'], 254],
    'schema cookie' => [static fn (): mixed => $plan()['schema_cookie'], 954],
    'database digest' => [static fn (): mixed => $plan()['database_digest'], $databaseDigest],
    'page cache digest' => [static fn (): mixed => $plan()['page_cache_digest'], $pageCacheDigest],
    'checkpoint frame' => [static fn (): mixed => $plan()['checkpoint_frame'], 41],
    'dirty pages' => [static fn (): mixed => $plan()['dirty_pages'], [1, 2, 5, 8]],
    'commit frames' => [static fn (): mixed => $plan()['commit_frames'], [38, 40, 41]],
    'reader names sorted' => [static fn (): mixed => $plan()['reader_names'], ['autoload-reader', 'options-reader', 'schema-reader']],
    'cache receipt names sorted' => [static fn (): mixed => $plan()['cache_receipt_names'], ['clear-options-readmark', 'invalidate-schema-cache', 'refresh-schema-cookie', 'refresh-wal-index']],
    'lease names' => [static fn (): mixed => $plan()['lease_names'], ['schema-statement-lease', 'options-table-root-lease', 'autoload-index-root-lease', 'read-transaction-lease']],
    'lease kinds' => [static fn (): mixed => $plan()['lease_kinds'], ['index-root', 'read-transaction', 'schema-statement', 'table-root']],
    'required lease kinds' => [static fn (): mixed => $plan()['required_lease_kinds'], ['schema-statement', 'table-root', 'index-root', 'read-transaction']],
    'missing lease kinds empty' => [static fn (): mixed => $plan()['missing_lease_kinds'], []],
    'duplicate names empty' => [static fn (): mixed => $plan()['duplicate_lease_names'], []],
    'accepted leases' => [static fn (): mixed => $plan()['accepted_lease_names'], ['schema-statement-lease', 'options-table-root-lease', 'autoload-index-root-lease', 'read-transaction-lease']],
    'blocked leases empty' => [static fn (): mixed => $plan()['blocked_lease_names'], []],
    'covered readers' => [static fn (): mixed => $plan()['covered_reader_names'], ['autoload-reader', 'options-reader', 'schema-reader']],
    'missing readers empty' => [static fn (): mixed => $plan()['missing_reader_names'], []],
    'covered pages' => [static fn (): mixed => $plan()['covered_dirty_pages'], [1, 2, 5, 8]],
    'missing pages empty' => [static fn (): mixed => $plan()['missing_dirty_pages'], []],
    'covered frames' => [static fn (): mixed => $plan()['covered_commit_frames'], [38, 40, 41]],
    'missing frames empty' => [static fn (): mixed => $plan()['missing_commit_frames'], []],
    'covered cache receipts' => [static fn (): mixed => $plan()['covered_cache_receipt_names'], ['clear-options-readmark', 'invalidate-schema-cache', 'refresh-schema-cookie', 'refresh-wal-index']],
    'missing cache receipts empty' => [static fn (): mixed => $plan()['missing_cache_receipt_names'], []],
    'blocked reasons empty' => [static fn (): mixed => $plan()['blocked_reasons'], []],
    'guard names' => [static fn (): mixed => $plan()['guard_names'], ['next250_cache_fence_admitted', 'lease_names_unique', 'required_lease_kinds_present', 'all_reopened_readers_have_current_source_leases', 'all_checkpoint_pages_bound_to_leases', 'all_commit_frames_bound_to_leases', 'all_cache_fence_receipts_consumed', 'all_lease_receipts_match_current_source']],
    'guard matches' => [static fn (): mixed => $plan()['guard_matches'], [true, true, true, true, true, true, true, true]],
    'blocked guards empty' => [static fn (): mixed => $plan()['blocked_guard_names'], []],
    'leases admitted' => [static fn (): mixed => $plan()['current_source_leases_admitted'], true],
    'statement action' => [static fn (): mixed => $plan()['statement_action'], 'reuse_statements_on_checkpoint_current_source'],
    'root page action' => [static fn (): mixed => $plan()['root_page_action'], 'serve_root_pages_from_checkpoint_database_digest'],
    'reader action' => [static fn (): mixed => $plan()['reader_action'], 'serve_read_transactions_from_generation_254'],
    'wal action' => [static fn (): mixed => $plan()['wal_action'], 'retain_wal_frames_for_leased_readers'],
    'lease digest length' => [static fn (): mixed => strlen($plan()['lease_digest']), 64],
    'operation inherited' => [static fn (): mixed => in_array('admit_checkpoint_cache_invalidation_current_source_next250', $plan()['operation_names'], true), true],
    'operation added' => [static fn (): mixed => in_array('admit_checkpoint_current_source_leases_next254', $plan()['operation_names'], true), true],
    'dependency inherited' => [static fn (): mixed => in_array('sqlite-wal-hot-journal-savepoint-checkpoint-current-source-next250', $plan()['dependencies'], true), true],
    'dependency next254' => [static fn (): mixed => in_array('sqlite-wal-hot-journal-savepoint-checkpoint-current-source-next254', $plan()['dependencies'], true), true],
    'dependency application' => [static fn (): mixed => in_array('application-import-current-source-statement-lease-fence', $plan()['dependencies'], true), true],
    'dependency closure' => [static fn (): mixed => str_contains($plan()['dependency_closure'], 'no new support component needed'), true],
    'non overlap' => [static fn (): mixed => str_contains($plan()['non_overlap'], 'does not repeat WAL byte truncation'), true],
    'row reason' => [static fn (): mixed => $plan()['lease_rows'][1]['lease_reason'], 'lease_matches_checkpoint_current_source'],
    'row pages' => [static fn (): mixed => $plan()['lease_rows'][1]['page_numbers'], [2, 5]],
    'row frames' => [static fn (): mixed => $plan()['lease_rows'][1]['commit_frames'], [40]],
    'row readers' => [static fn (): mixed => $plan()['lease_rows'][1]['reader_names'], ['options-reader']],
    'row cache receipts' => [static fn (): mixed => $plan()['lease_rows'][1]['cache_receipt_names'], ['clear-options-readmark']],
    'row flags' => [static fn (): mixed => [$plan()['lease_rows'][1]['statement_reprepared'], $plan()['lease_rows'][1]['root_page_digest_matched'], $plan()['lease_rows'][1]['read_transaction_open']], [true, true, true]],
    'stale token blocked' => [static fn (): mixed => $blocked(['source_token' => 'old-source'])['blocked_reasons'], ['lease_source_token_mismatch']],
    'stale generation blocked' => [static fn (): mixed => $blocked(['commit_generation' => 253])['blocked_reasons'], ['lease_commit_generation_mismatch']],
    'stale schema blocked' => [static fn (): mixed => $blocked(['schema_cookie' => 953])['blocked_reasons'], ['lease_schema_cookie_mismatch']],
    'stale database blocked' => [static fn (): mixed => $blocked(['database_digest' => $hash('stale database')])['blocked_reasons'], ['lease_database_digest_mismatch']],
    'stale page cache blocked' => [static fn (): mixed => $blocked(['page_cache_digest' => $hash('stale cache')])['blocked_reasons'], ['lease_page_cache_digest_mismatch']],
    'stale checkpoint blocked' => [static fn (): mixed => $blocked(['checkpoint_frame' => 40])['blocked_reasons'], ['lease_checkpoint_frame_mismatch']],
    'unknown page blocked' => [static fn (): mixed => $blocked(['page_numbers' => [2, 9]])['blocked_reasons'], ['lease_page_not_checkpointed']],
    'unknown frame blocked' => [static fn (): mixed => $blocked(['commit_frames' => [40, 99]])['blocked_reasons'], ['lease_frame_not_committed']],
    'unknown reader blocked' => [static fn (): mixed => $blocked(['reader_names' => ['options-reader', 'old-reader']])['blocked_reasons'], ['lease_reader_not_reopened']],
    'unknown cache receipt blocked' => [static fn (): mixed => $blocked(['cache_receipt_names' => ['clear-options-readmark', 'unknown-receipt']])['blocked_reasons'], ['lease_cache_receipt_unknown']],
    'statement not reprepare blocked' => [static fn (): mixed => $blocked(['statement_reprepared' => false])['blocked_reasons'], ['lease_statement_not_reprepared']],
    'root digest blocked' => [static fn (): mixed => $blocked(['root_page_digest_matched' => false])['blocked_reasons'], ['lease_root_page_digest_mismatch']],
    'read transaction blocked' => [static fn (): mixed => $blocked(['read_transaction_open' => false])['blocked_reasons'], ['lease_read_transaction_missing']],
    'hot journal visible blocked' => [static fn (): mixed => $blocked(['hot_journal_visible' => true])['blocked_reasons'], ['lease_hot_journal_visible']],
    'savepoint open blocked' => [static fn (): mixed => $blocked(['savepoint_depth' => 1])['blocked_reasons'], ['lease_savepoint_scope_open']],
    'blocked status' => [static fn (): mixed => $blocked(['source_token' => 'old-source'])['status'], 'wal-hot-journal-savepoint-checkpoint-current-source-blocked-next254'],
    'blocked reason' => [static fn (): mixed => $blocked(['source_token' => 'old-source'])['reason'], 'checkpoint_current_source_leases_held'],
    'blocked statement action' => [static fn (): mixed => $blocked(['source_token' => 'old-source'])['statement_action'], 'force_statement_reprepare_before_current_source_reuse'],
    'blocked root action' => [static fn (): mixed => $blocked(['source_token' => 'old-source'])['root_page_action'], 'hold_root_pages_until_lease_receipts_match'],
    'blocked reader action' => [static fn (): mixed => $blocked(['source_token' => 'old-source'])['reader_action'], 'hold_read_transactions_on_prior_generation'],
    'blocked wal action' => [static fn (): mixed => $blocked(['source_token' => 'old-source'])['wal_action'], 'preserve_wal_for_reopen_recheck'],
    'blocked guards' => [static fn (): mixed => $blocked(['source_token' => 'old-source'])['blocked_guard_names'], ['all_lease_receipts_match_current_source']],
    'missing kind list' => [static fn (): mixed => $missingKind()['missing_lease_kinds'], ['read-transaction']],
    'missing kind guard' => [static fn (): mixed => $missingKind()['blocked_guard_names'], ['required_lease_kinds_present', 'all_cache_fence_receipts_consumed']],
    'missing reader list' => [static fn (): mixed => $missingReader()['missing_reader_names'], ['autoload-reader']],
    'missing reader guard' => [static fn (): mixed => $missingReader()['blocked_guard_names'], ['all_reopened_readers_have_current_source_leases']],
    'missing page list' => [static fn (): mixed => $missingPage()['missing_dirty_pages'], [8]],
    'missing page guard' => [static fn (): mixed => $missingPage()['blocked_guard_names'], ['all_checkpoint_pages_bound_to_leases']],
    'missing frame list' => [static fn (): mixed => $missingFrame()['missing_commit_frames'], [41]],
    'missing frame guard' => [static fn (): mixed => $missingFrame()['blocked_guard_names'], ['all_commit_frames_bound_to_leases']],
    'missing cache receipt list' => [static fn (): mixed => $missingCacheReceipt()['missing_cache_receipt_names'], ['refresh-wal-index']],
    'missing cache receipt guard' => [static fn (): mixed => $missingCacheReceipt()['blocked_guard_names'], ['all_cache_fence_receipts_consumed']],
    'duplicate lease names' => [static fn (): mixed => $duplicateLease()['duplicate_lease_names'], ['schema-statement-lease']],
    'duplicate lease guard' => [static fn (): mixed => $duplicateLease()['blocked_guard_names'], ['lease_names_unique']],
    'combined blocked reasons' => [static fn (): mixed => $blocked(['source_token' => 'old-source', 'statement_reprepared' => false, 'hot_journal_visible' => true])['blocked_reasons'], ['lease_source_token_mismatch', 'lease_statement_not_reprepared', 'lease_hot_journal_visible']],
];

foreach ($cases as $name => [$callback, $expected]) {
    $tests['wal hot journal savepoint checkpoint current source next254 ' . $name] = static function (TestRunner $t) use ($callback, $expected): void {
        $t->same($expected, $callback());
    };
}

$throws = [
    'bad base rejected' => static fn () => $plan(['status' => 'bad']),
    'not admitted rejected' => static fn () => $plan(['cache_invalidation_admitted' => false]),
    'empty leases rejected' => static fn () => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next254AdmitCurrentSourceLeases($cachePlan, []),
    'bad source rejected' => static fn () => $plan(['source_token' => 'bad token']),
    'bad generation rejected' => static fn () => $plan(['commit_generation' => 0]),
    'bad schema rejected' => static fn () => $plan(['schema_cookie' => 0]),
    'bad database digest rejected' => static fn () => $plan(['database_digest' => 'short']),
    'bad cache digest rejected' => static fn () => $plan(['page_cache_digest' => 'short']),
    'bad checkpoint rejected' => static fn () => $plan(['checkpoint_frame' => -1]),
    'bad dirty pages rejected' => static fn () => $plan(['dirty_pages' => []]),
    'bad commit frames rejected' => static fn () => $plan(['commit_frames' => [0]]),
    'bad reader names rejected' => static fn () => $plan(['reader_names' => []]),
    'bad receipt names rejected' => static fn () => $plan(['receipt_names' => []]),
    'bad lease name rejected' => static fn () => $plan([], [array_replace($leases[0], ['name' => 'bad name'])]),
    'bad lease kind rejected' => static fn () => $plan([], [array_replace($leases[0], ['kind' => 'unknown'])]),
    'bad lease page rejected' => static fn () => $plan([], [array_replace($leases[0], ['page_numbers' => [0]])]),
    'bad lease frame rejected' => static fn () => $plan([], [array_replace($leases[0], ['commit_frames' => [0]])]),
    'bad lease reader rejected' => static fn () => $plan([], [array_replace($leases[0], ['reader_names' => ['bad reader']])]),
    'bad lease cache receipt rejected' => static fn () => $plan([], [array_replace($leases[0], ['cache_receipt_names' => ['bad receipt']])]),
];

foreach ($throws as $name => $callback) {
    $tests['wal hot journal savepoint checkpoint current source next254 ' . $name] = static function (TestRunner $t) use ($callback): void {
        $t->throws(Throwable::class, $callback);
    };
}

return $tests;
