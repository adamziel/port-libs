<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteWalHotJournalSavepointCheckpointCurrentSourceNext250Plan;

require_once __DIR__ . '/../src/SQLiteWalHotJournalSavepointCheckpointCurrentSourceNext250Plan.php';

$tests = [];

$hash = static fn (string $value): string => hash('sha256', $value);
$databaseDigest = $hash('next250 checkpoint database image');
$pageCacheDigest = $hash('next250 clean page cache image');
$cleanupPlan = [
    'status' => 'wal-hot-journal-savepoint-checkpoint-current-source-next247',
    'cleanup_admitted' => true,
    'database_path' => '/srv/www/wp-content/database/wp-next250.sqlite',
    'journal_path' => '/srv/www/wp-content/database/wp-next250.sqlite-journal',
    'wal_path' => '/srv/www/wp-content/database/wp-next250.sqlite-wal',
    'source_token' => 'wp-next250-current-source',
    'commit_generation' => 250,
    'schema_cookie' => 950,
    'database_digest' => $databaseDigest,
    'page_cache_digest' => $pageCacheDigest,
    'wal_index_salt' => ['next250-salt-a', 'next250-salt-b'],
    'wal_index_mx_frame' => 31,
    'checkpoint_frame' => 29,
    'dirty_pages' => [1, 2, 4, 7],
    'commit_frames' => [27, 30, 31],
    'reader_names' => ['schema-reader', 'options-reader', 'autoload-reader'],
    'operation_names' => ['seal_post_checkpoint_cleanup_current_source_next247'],
    'dependencies' => ['sqlite-wal-hot-journal-savepoint-checkpoint-current-source-next247'],
];

$receipt = static function (string $name, string $kind, array $pages, array $frames, array $readers, array $override = []) use ($cleanupPlan, $databaseDigest, $pageCacheDigest): array {
    return array_replace([
        'name' => $name,
        'kind' => $kind,
        'database_path' => $cleanupPlan['database_path'],
        'journal_path' => $cleanupPlan['journal_path'],
        'wal_path' => $cleanupPlan['wal_path'],
        'source_token' => $cleanupPlan['source_token'],
        'commit_generation' => $cleanupPlan['commit_generation'],
        'schema_cookie' => $cleanupPlan['schema_cookie'],
        'database_digest' => $databaseDigest,
        'page_cache_digest' => $pageCacheDigest,
        'wal_index_salt' => $cleanupPlan['wal_index_salt'],
        'wal_index_mx_frame' => $cleanupPlan['wal_index_mx_frame'],
        'checkpoint_frame' => $cleanupPlan['checkpoint_frame'],
        'page_numbers' => $pages,
        'commit_frames' => $frames,
        'reader_names' => $readers,
        'page_cache_invalidated' => true,
        'readmark_cleared' => true,
        'schema_cookie_refreshed' => true,
        'wal_index_refreshed' => true,
        'reader_reopened' => true,
        'hot_journal_visible' => false,
        'stale_wal_visible' => false,
        'savepoint_depth' => 0,
        'shared_lock_held' => true,
    ], $override);
};

$receipts = [
    $receipt('invalidate-schema-cache', 'cache-invalidate', [1], [27], ['schema-reader']),
    $receipt('clear-options-readmark', 'readmark-clear', [2], [30], ['options-reader']),
    $receipt('refresh-schema-cookie', 'schema-cookie-refresh', [4], [31], ['autoload-reader']),
    $receipt('refresh-wal-index', 'wal-index-refresh', [1, 2, 4, 7], [27, 30, 31], ['schema-reader', 'options-reader', 'autoload-reader']),
];

$plan = static fn (): array => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNext250Plan::admitCacheInvalidation($cleanupPlan, $receipts);
$blocked = static fn (array $override): array => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNext250Plan::admitCacheInvalidation(
    $cleanupPlan,
    [
        $receipts[0],
        $receipt('clear-options-readmark-blocked', 'readmark-clear', [2], [30], ['options-reader'], $override),
        $receipts[2],
        $receipts[3],
    ]
);
$missingKind = static fn (): array => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNext250Plan::admitCacheInvalidation(
    $cleanupPlan,
    [$receipts[0], $receipts[1], $receipts[2]]
);
$missingReader = static fn (): array => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNext250Plan::admitCacheInvalidation(
    $cleanupPlan,
    [
        $receipt('invalidate-schema-cache', 'cache-invalidate', [1], [27], ['schema-reader']),
        $receipt('clear-options-readmark', 'readmark-clear', [2], [30], ['options-reader']),
        $receipt('refresh-schema-cookie', 'schema-cookie-refresh', [4], [31], ['options-reader']),
        $receipt('refresh-wal-index', 'wal-index-refresh', [1, 2, 4, 7], [27, 30, 31], ['schema-reader', 'options-reader']),
    ]
);
$missingPage = static fn (): array => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNext250Plan::admitCacheInvalidation(
    $cleanupPlan,
    [
        $receipt('invalidate-schema-cache', 'cache-invalidate', [1], [27], ['schema-reader']),
        $receipt('clear-options-readmark', 'readmark-clear', [2], [30], ['options-reader']),
        $receipt('refresh-schema-cookie', 'schema-cookie-refresh', [4], [31], ['autoload-reader']),
        $receipt('refresh-wal-index', 'wal-index-refresh', [1, 2, 4], [27, 30, 31], ['schema-reader', 'options-reader', 'autoload-reader']),
    ]
);
$missingFrame = static fn (): array => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNext250Plan::admitCacheInvalidation(
    $cleanupPlan,
    [
        $receipt('invalidate-schema-cache', 'cache-invalidate', [1], [27], ['schema-reader']),
        $receipt('clear-options-readmark', 'readmark-clear', [2], [30], ['options-reader']),
        $receipt('refresh-schema-cookie', 'schema-cookie-refresh', [4], [30], ['autoload-reader']),
        $receipt('refresh-wal-index', 'wal-index-refresh', [1, 2, 4, 7], [27, 30], ['schema-reader', 'options-reader', 'autoload-reader']),
    ]
);
$duplicateReceipt = static fn (): array => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNext250Plan::admitCacheInvalidation(
    $cleanupPlan,
    [$receipts[0], $receipt('invalidate-schema-cache', 'readmark-clear', [2], [30], ['options-reader']), $receipts[2], $receipts[3]]
);

$cases = [
    'status' => [static fn (): mixed => $plan()['status'], 'wal-hot-journal-savepoint-checkpoint-current-source-next250'],
    'reason' => [static fn (): mixed => $plan()['reason'], 'checkpoint_current_source_cache_invalidation_admitted'],
    'base status' => [static fn (): mixed => $plan()['base_status'], 'wal-hot-journal-savepoint-checkpoint-current-source-next247'],
    'database path' => [static fn (): mixed => $plan()['database_path'], '/srv/www/wp-content/database/wp-next250.sqlite'],
    'wal path' => [static fn (): mixed => $plan()['wal_path'], '/srv/www/wp-content/database/wp-next250.sqlite-wal'],
    'journal path' => [static fn (): mixed => $plan()['journal_path'], '/srv/www/wp-content/database/wp-next250.sqlite-journal'],
    'source token' => [static fn (): mixed => $plan()['source_token'], 'wp-next250-current-source'],
    'commit generation' => [static fn (): mixed => $plan()['commit_generation'], 250],
    'schema cookie' => [static fn (): mixed => $plan()['schema_cookie'], 950],
    'database digest' => [static fn (): mixed => $plan()['database_digest'], $databaseDigest],
    'page cache digest' => [static fn (): mixed => $plan()['page_cache_digest'], $pageCacheDigest],
    'wal salt' => [static fn (): mixed => $plan()['wal_index_salt'], ['next250-salt-a', 'next250-salt-b']],
    'wal mx frame' => [static fn (): mixed => $plan()['wal_index_mx_frame'], 31],
    'checkpoint frame' => [static fn (): mixed => $plan()['checkpoint_frame'], 29],
    'dirty pages' => [static fn (): mixed => $plan()['dirty_pages'], [1, 2, 4, 7]],
    'commit frames' => [static fn (): mixed => $plan()['commit_frames'], [27, 30, 31]],
    'reader names sorted' => [static fn (): mixed => $plan()['reader_names'], ['autoload-reader', 'options-reader', 'schema-reader']],
    'receipt names' => [static fn (): mixed => $plan()['receipt_names'], ['invalidate-schema-cache', 'clear-options-readmark', 'refresh-schema-cookie', 'refresh-wal-index']],
    'receipt kinds' => [static fn (): mixed => $plan()['receipt_kinds'], ['cache-invalidate', 'readmark-clear', 'schema-cookie-refresh', 'wal-index-refresh']],
    'required kinds' => [static fn (): mixed => $plan()['required_receipt_kinds'], ['cache-invalidate', 'readmark-clear', 'schema-cookie-refresh', 'wal-index-refresh']],
    'missing kinds empty' => [static fn (): mixed => $plan()['missing_receipt_kinds'], []],
    'duplicate names empty' => [static fn (): mixed => $plan()['duplicate_receipt_names'], []],
    'accepted receipts' => [static fn (): mixed => $plan()['accepted_receipt_names'], ['invalidate-schema-cache', 'clear-options-readmark', 'refresh-schema-cookie', 'refresh-wal-index']],
    'blocked receipts empty' => [static fn (): mixed => $plan()['blocked_receipt_names'], []],
    'covered readers' => [static fn (): mixed => $plan()['covered_reader_names'], ['autoload-reader', 'options-reader', 'schema-reader']],
    'missing readers empty' => [static fn (): mixed => $plan()['missing_reader_names'], []],
    'covered pages' => [static fn (): mixed => $plan()['covered_dirty_pages'], [1, 2, 4, 7]],
    'missing pages empty' => [static fn (): mixed => $plan()['missing_dirty_pages'], []],
    'covered frames' => [static fn (): mixed => $plan()['covered_commit_frames'], [27, 30, 31]],
    'missing frames empty' => [static fn (): mixed => $plan()['missing_commit_frames'], []],
    'blocked reasons empty' => [static fn (): mixed => $plan()['blocked_reasons'], []],
    'guard names' => [static fn (): mixed => $plan()['guard_names'], ['next247_cleanup_admitted', 'cache_invalidation_receipt_names_unique', 'required_cache_invalidation_kinds_present', 'all_readers_reopened_after_cache_invalidation', 'all_dirty_pages_removed_from_stale_cache', 'all_commit_frames_removed_from_stale_readmarks', 'all_cache_receipts_match_current_source']],
    'guard matches' => [static fn (): mixed => $plan()['guard_matches'], [true, true, true, true, true, true, true]],
    'blocked guards empty' => [static fn (): mixed => $plan()['blocked_guard_names'], []],
    'cache admitted' => [static fn (): mixed => $plan()['cache_invalidation_admitted'], true],
    'cache action' => [static fn (): mixed => $plan()['cache_action'], 'discard_stale_page_cache_before_current_source_read'],
    'wal index action' => [static fn (): mixed => $plan()['wal_index_action'], 'refresh_wal_index_header_for_checkpoint_current_source'],
    'reader action' => [static fn (): mixed => $plan()['reader_action'], 'serve_reopened_readers_from_checkpoint_database_source'],
    'hot journal action' => [static fn (): mixed => $plan()['hot_journal_action'], 'keep_hot_journal_deleted_after_cache_fence'],
    'cache digest length' => [static fn (): mixed => strlen($plan()['cache_digest']), 64],
    'operation inherited' => [static fn (): mixed => in_array('seal_post_checkpoint_cleanup_current_source_next247', $plan()['operation_names'], true), true],
    'operation added' => [static fn (): mixed => in_array('admit_checkpoint_cache_invalidation_current_source_next250', $plan()['operation_names'], true), true],
    'dependency inherited' => [static fn (): mixed => in_array('sqlite-wal-hot-journal-savepoint-checkpoint-current-source-next247', $plan()['dependencies'], true), true],
    'dependency next250' => [static fn (): mixed => in_array('sqlite-wal-hot-journal-savepoint-checkpoint-current-source-next250', $plan()['dependencies'], true), true],
    'dependency wordpress' => [static fn (): mixed => in_array('wordpress-import-checkpoint-current-source-cache-fence', $plan()['dependencies'], true), true],
    'dependency closure' => [static fn (): mixed => str_contains($plan()['dependency_closure'], 'no new support component needed'), true],
    'non overlap' => [static fn (): mixed => str_contains($plan()['non_overlap'], 'does not repeat checkpoint publication'), true],
    'row kind' => [static fn (): mixed => $plan()['receipt_rows'][1]['kind'], 'readmark-clear'],
    'row reason' => [static fn (): mixed => $plan()['receipt_rows'][1]['cache_reason'], 'cache_receipt_matches_checkpoint_current_source'],
    'row pages' => [static fn (): mixed => $plan()['receipt_rows'][1]['page_numbers'], [2]],
    'row frames' => [static fn (): mixed => $plan()['receipt_rows'][1]['commit_frames'], [30]],
    'row readers' => [static fn (): mixed => $plan()['receipt_rows'][1]['reader_names'], ['options-reader']],
    'row flags' => [static fn (): mixed => [$plan()['receipt_rows'][1]['page_cache_invalidated'], $plan()['receipt_rows'][1]['readmark_cleared'], $plan()['receipt_rows'][1]['schema_cookie_refreshed'], $plan()['receipt_rows'][1]['wal_index_refreshed'], $plan()['receipt_rows'][1]['reader_reopened']], [true, true, true, true, true]],
    'row stale flags' => [static fn (): mixed => [$plan()['receipt_rows'][1]['hot_journal_visible'], $plan()['receipt_rows'][1]['stale_wal_visible']], [false, false]],
    'row closed savepoint' => [static fn (): mixed => $plan()['receipt_rows'][1]['savepoint_depth'], 0],
    'stale token blocked' => [static fn (): mixed => $blocked(['source_token' => 'old-source'])['blocked_reasons'], ['cache_source_token_mismatch']],
    'stale generation blocked' => [static fn (): mixed => $blocked(['commit_generation' => 249])['blocked_reasons'], ['cache_commit_generation_mismatch']],
    'stale schema blocked' => [static fn (): mixed => $blocked(['schema_cookie' => 949])['blocked_reasons'], ['cache_schema_cookie_mismatch']],
    'stale database blocked' => [static fn (): mixed => $blocked(['database_digest' => $hash('stale database')])['blocked_reasons'], ['cache_database_digest_mismatch']],
    'stale page cache blocked' => [static fn (): mixed => $blocked(['page_cache_digest' => $hash('stale cache')])['blocked_reasons'], ['cache_page_cache_digest_mismatch']],
    'stale salt blocked' => [static fn (): mixed => $blocked(['wal_index_salt' => ['old-a', 'old-b']])['blocked_reasons'], ['cache_wal_index_salt_mismatch']],
    'stale mx frame blocked' => [static fn (): mixed => $blocked(['wal_index_mx_frame' => 30])['blocked_reasons'], ['cache_wal_index_mx_frame_mismatch']],
    'stale checkpoint blocked' => [static fn (): mixed => $blocked(['checkpoint_frame' => 28])['blocked_reasons'], ['cache_checkpoint_frame_mismatch']],
    'unknown page blocked' => [static fn (): mixed => $blocked(['page_numbers' => [2, 9]])['blocked_reasons'], ['cache_page_not_dirty']],
    'unknown frame blocked' => [static fn (): mixed => $blocked(['commit_frames' => [30, 99]])['blocked_reasons'], ['cache_frame_not_committed']],
    'unknown reader blocked' => [static fn (): mixed => $blocked(['reader_names' => ['options-reader', 'old-reader']])['blocked_reasons'], ['cache_reader_not_admitted']],
    'page cache not invalidated blocked' => [static fn (): mixed => $blocked(['page_cache_invalidated' => false])['blocked_reasons'], ['cache_page_cache_not_invalidated']],
    'readmark not cleared blocked' => [static fn (): mixed => $blocked(['readmark_cleared' => false])['blocked_reasons'], ['cache_readmark_not_cleared']],
    'schema not refreshed blocked' => [static fn (): mixed => $blocked(['schema_cookie_refreshed' => false])['blocked_reasons'], ['cache_schema_cookie_not_refreshed']],
    'wal index not refreshed blocked' => [static fn (): mixed => $blocked(['wal_index_refreshed' => false])['blocked_reasons'], ['cache_wal_index_not_refreshed']],
    'reader not reopened blocked' => [static fn (): mixed => $blocked(['reader_reopened' => false])['blocked_reasons'], ['cache_reader_not_reopened']],
    'hot journal visible blocked' => [static fn (): mixed => $blocked(['hot_journal_visible' => true])['blocked_reasons'], ['cache_hot_journal_still_visible']],
    'stale wal visible blocked' => [static fn (): mixed => $blocked(['stale_wal_visible' => true])['blocked_reasons'], ['cache_stale_wal_still_visible']],
    'savepoint open blocked' => [static fn (): mixed => $blocked(['savepoint_depth' => 1])['blocked_reasons'], ['cache_savepoint_scope_open']],
    'shared lock missing blocked' => [static fn (): mixed => $blocked(['shared_lock_held' => false])['blocked_reasons'], ['cache_shared_lock_missing']],
    'blocked status' => [static fn (): mixed => $blocked(['source_token' => 'old-source'])['status'], 'wal-hot-journal-savepoint-checkpoint-current-source-blocked-next250'],
    'blocked reason' => [static fn (): mixed => $blocked(['source_token' => 'old-source'])['reason'], 'checkpoint_current_source_cache_invalidation_held'],
    'blocked cache action' => [static fn (): mixed => $blocked(['source_token' => 'old-source'])['cache_action'], 'retain_prior_cache_until_invalidation_receipts_match'],
    'blocked wal action' => [static fn (): mixed => $blocked(['source_token' => 'old-source'])['wal_index_action'], 'hold_wal_index_refresh_for_recheck'],
    'blocked reader action' => [static fn (): mixed => $blocked(['source_token' => 'old-source'])['reader_action'], 'force_readers_to_reopen_after_cache_fence'],
    'blocked hot journal action' => [static fn (): mixed => $blocked(['source_token' => 'old-source'])['hot_journal_action'], 'preserve_hot_journal_recovery_until_cache_fence'],
    'blocked guards' => [static fn (): mixed => $blocked(['source_token' => 'old-source'])['blocked_guard_names'], ['all_cache_receipts_match_current_source']],
    'missing kind list' => [static fn (): mixed => $missingKind()['missing_receipt_kinds'], ['wal-index-refresh']],
    'missing kind guard' => [static fn (): mixed => $missingKind()['blocked_guard_names'], ['required_cache_invalidation_kinds_present', 'all_dirty_pages_removed_from_stale_cache']],
    'missing reader list' => [static fn (): mixed => $missingReader()['missing_reader_names'], ['autoload-reader']],
    'missing reader guard' => [static fn (): mixed => $missingReader()['blocked_guard_names'], ['all_readers_reopened_after_cache_invalidation']],
    'missing page list' => [static fn (): mixed => $missingPage()['missing_dirty_pages'], [7]],
    'missing page guard' => [static fn (): mixed => $missingPage()['blocked_guard_names'], ['all_dirty_pages_removed_from_stale_cache']],
    'missing frame list' => [static fn (): mixed => $missingFrame()['missing_commit_frames'], [31]],
    'missing frame guard' => [static fn (): mixed => $missingFrame()['blocked_guard_names'], ['all_commit_frames_removed_from_stale_readmarks']],
    'duplicate receipt names' => [static fn (): mixed => $duplicateReceipt()['duplicate_receipt_names'], ['invalidate-schema-cache']],
    'duplicate receipt guard' => [static fn (): mixed => $duplicateReceipt()['blocked_guard_names'], ['cache_invalidation_receipt_names_unique']],
    'combined blocked reasons' => [static fn (): mixed => $blocked(['source_token' => 'old-source', 'readmark_cleared' => false, 'stale_wal_visible' => true])['blocked_reasons'], ['cache_source_token_mismatch', 'cache_readmark_not_cleared', 'cache_stale_wal_still_visible']],
];

foreach ($cases as $name => [$callback, $expected]) {
    $tests['wal hot journal savepoint checkpoint current source next250 ' . $name] = static function (TestRunner $t) use ($callback, $expected): void {
        $t->same($expected, $callback());
    };
}

$throws = [
    'bad base status rejected' => static fn () => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNext250Plan::admitCacheInvalidation(array_replace($cleanupPlan, ['status' => 'bad']), $receipts),
    'not admitted base rejected' => static fn () => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNext250Plan::admitCacheInvalidation(array_replace($cleanupPlan, ['cleanup_admitted' => false]), $receipts),
    'empty receipts rejected' => static fn () => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNext250Plan::admitCacheInvalidation($cleanupPlan, []),
    'bad database path rejected' => static fn () => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNext250Plan::admitCacheInvalidation(array_replace($cleanupPlan, ['database_path' => '']), $receipts),
    'bad wal path rejected' => static fn () => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNext250Plan::admitCacheInvalidation(array_replace($cleanupPlan, ['wal_path' => '']), $receipts),
    'bad journal path rejected' => static fn () => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNext250Plan::admitCacheInvalidation(array_replace($cleanupPlan, ['journal_path' => '']), $receipts),
    'bad source token rejected' => static fn () => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNext250Plan::admitCacheInvalidation(array_replace($cleanupPlan, ['source_token' => 'bad token']), $receipts),
    'bad generation rejected' => static fn () => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNext250Plan::admitCacheInvalidation(array_replace($cleanupPlan, ['commit_generation' => 0]), $receipts),
    'bad schema cookie rejected' => static fn () => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNext250Plan::admitCacheInvalidation(array_replace($cleanupPlan, ['schema_cookie' => 0]), $receipts),
    'bad database digest rejected' => static fn () => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNext250Plan::admitCacheInvalidation(array_replace($cleanupPlan, ['database_digest' => 'short']), $receipts),
    'bad page cache digest rejected' => static fn () => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNext250Plan::admitCacheInvalidation(array_replace($cleanupPlan, ['page_cache_digest' => 'short']), $receipts),
    'bad wal salt rejected' => static fn () => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNext250Plan::admitCacheInvalidation(array_replace($cleanupPlan, ['wal_index_salt' => ['only-one']]), $receipts),
    'bad wal mx frame rejected' => static fn () => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNext250Plan::admitCacheInvalidation(array_replace($cleanupPlan, ['wal_index_mx_frame' => -1]), $receipts),
    'bad checkpoint frame rejected' => static fn () => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNext250Plan::admitCacheInvalidation(array_replace($cleanupPlan, ['checkpoint_frame' => -1]), $receipts),
    'bad dirty pages rejected' => static fn () => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNext250Plan::admitCacheInvalidation(array_replace($cleanupPlan, ['dirty_pages' => []]), $receipts),
    'bad commit frames rejected' => static fn () => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNext250Plan::admitCacheInvalidation(array_replace($cleanupPlan, ['commit_frames' => [0]]), $receipts),
    'bad reader names rejected' => static fn () => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNext250Plan::admitCacheInvalidation(array_replace($cleanupPlan, ['reader_names' => []]), $receipts),
    'bad receipt name rejected' => static fn () => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNext250Plan::admitCacheInvalidation($cleanupPlan, [array_replace($receipts[0], ['name' => 'bad name'])]),
    'bad receipt kind rejected' => static fn () => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNext250Plan::admitCacheInvalidation($cleanupPlan, [array_replace($receipts[0], ['kind' => 'unknown'])]),
    'bad receipt page rejected' => static fn () => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNext250Plan::admitCacheInvalidation($cleanupPlan, [array_replace($receipts[0], ['page_numbers' => [0]])]),
    'bad receipt frame rejected' => static fn () => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNext250Plan::admitCacheInvalidation($cleanupPlan, [array_replace($receipts[0], ['commit_frames' => ['bad']])]),
    'bad receipt reader rejected' => static fn () => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNext250Plan::admitCacheInvalidation($cleanupPlan, [array_replace($receipts[0], ['reader_names' => ['bad reader']])]),
];

foreach ($throws as $name => $callback) {
    $tests['wal hot journal savepoint checkpoint current source next250 ' . $name] = static function (TestRunner $t) use ($callback): void {
        $t->throws(Throwable::class, $callback);
    };
}

return $tests;
