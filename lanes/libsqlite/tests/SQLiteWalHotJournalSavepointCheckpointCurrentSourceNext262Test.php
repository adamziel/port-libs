<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan;

require_once __DIR__ . '/../src/SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan.php';

$tests = [];

$digest = static fn (string $value): string => hash('sha256', $value);
$databaseDigest = $digest('next262 checkpoint database image');
$pageCacheDigest = $digest('next262 current page cache image');
$sourceToken = 'wp-next262-current-source';
$generation = 262;
$schemaCookie = 1262;
$checkpointFrame = 52;
$base = [
    'status' => 'wal-hot-journal-savepoint-checkpoint-current-source-next260',
    'current_source_admitted' => true,
    'database_path' => '/srv/www/wp-content/database/wp-next262.sqlite',
    'journal_path' => '/srv/www/wp-content/database/wp-next262.sqlite-journal',
    'wal_path' => '/srv/www/wp-content/database/wp-next262.sqlite-wal',
    'source_token' => $sourceToken,
    'database_digest' => $databaseDigest,
    'page_cache_digest' => $pageCacheDigest,
    'commit_generation' => $generation,
    'schema_cookie' => $schemaCookie,
    'checkpoint_frame' => $checkpointFrame,
    'dirty_pages' => [1, 2, 4, 7],
    'accepted_reader_names' => ['wp-options-reader', 'autoload-index-reader'],
    'operation_names' => ['admit_hot_journal_savepoint_checkpoint_current_source_next260'],
    'dependencies' => ['sqlite-wal-hot-journal-savepoint-checkpoint-current-source-next260'],
];
$common = [
    'source_token' => $sourceToken,
    'database_digest' => $databaseDigest,
    'page_cache_digest' => $pageCacheDigest,
    'commit_generation' => $generation,
    'schema_cookie' => $schemaCookie,
    'checkpoint_frame' => $checkpointFrame,
];
$cache = [
    [
        'name' => 'options-page-cache',
        'reader_name' => 'wp-options-reader',
        'page_number' => 1,
    ] + $common + [
        'hot_journal_generation_seen' => null,
        'stale_wal_frame_seen' => null,
        'evicted' => false,
    ],
    [
        'name' => 'autoload-page-cache',
        'reader_name' => 'autoload-index-reader',
        'page_number' => 4,
    ] + $common + [
        'hot_journal_generation_seen' => null,
        'stale_wal_frame_seen' => null,
        'evicted' => false,
    ],
    [
        'name' => 'stale-options-cache',
        'reader_name' => 'wp-options-reader',
        'page_number' => 2,
    ] + $common + [
        'commit_generation' => 261,
        'hot_journal_generation_seen' => 261,
        'stale_wal_frame_seen' => 48,
        'evicted' => true,
    ],
];
$retry = [
    [
        'name' => 'retry-options-page',
        'reader_name' => 'wp-options-reader',
        'page_number' => 1,
    ] + $common + [
        'snapshot_reopened' => true,
        'hot_journal_visible' => false,
        'stale_wal_visible' => false,
    ],
    [
        'name' => 'retry-autoload-page',
        'reader_name' => 'autoload-index-reader',
        'page_number' => 4,
    ] + $common + [
        'snapshot_reopened' => true,
        'hot_journal_visible' => false,
        'stale_wal_visible' => false,
    ],
];

$plan = static fn (): array => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next262FenceReaderCache($base, $cache, $retry);
$blockedCache = static fn (array $replace, int $index = 0): array => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next262FenceReaderCache($base, array_replace($cache, [$index => array_replace($cache[$index], $replace)]), $retry);
$blockedRetry = static fn (array $replace, int $index = 0): array => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next262FenceReaderCache($base, $cache, array_replace($retry, [$index => array_replace($retry[$index], $replace)]));

$cases = [
    'status' => [static fn (): mixed => $plan()['status'], 'wal-hot-journal-savepoint-checkpoint-current-source-next262'],
    'reason' => [static fn (): mixed => $plan()['reason'], 'reader_cache_retry_uses_checkpoint_current_source'],
    'base status' => [static fn (): mixed => $plan()['base_status'], 'wal-hot-journal-savepoint-checkpoint-current-source-next260'],
    'database path' => [static fn (): mixed => $plan()['database_path'], '/srv/www/wp-content/database/wp-next262.sqlite'],
    'journal path' => [static fn (): mixed => $plan()['journal_path'], '/srv/www/wp-content/database/wp-next262.sqlite-journal'],
    'wal path' => [static fn (): mixed => $plan()['wal_path'], '/srv/www/wp-content/database/wp-next262.sqlite-wal'],
    'source token' => [static fn (): mixed => $plan()['source_token'], $sourceToken],
    'generation' => [static fn (): mixed => $plan()['commit_generation'], $generation],
    'schema cookie' => [static fn (): mixed => $plan()['schema_cookie'], $schemaCookie],
    'checkpoint frame' => [static fn (): mixed => $plan()['checkpoint_frame'], $checkpointFrame],
    'database digest' => [static fn (): mixed => $plan()['database_digest'], $databaseDigest],
    'page cache digest' => [static fn (): mixed => $plan()['page_cache_digest'], $pageCacheDigest],
    'dirty pages' => [static fn (): mixed => $plan()['dirty_pages'], [1, 2, 4, 7]],
    'accepted readers' => [static fn (): mixed => $plan()['accepted_reader_names'], ['wp-options-reader', 'autoload-index-reader']],
    'usable pages' => [static fn (): mixed => $plan()['usable_cache_pages'], [1, 4]],
    'retry pages' => [static fn (): mixed => $plan()['retry_pages'], [1, 4]],
    'missing retry pages empty' => [static fn (): mixed => $plan()['missing_retry_pages'], []],
    'usable cache names' => [static fn (): mixed => $plan()['usable_cache_names'], ['options-page-cache', 'autoload-page-cache']],
    'evicted cache names' => [static fn (): mixed => $plan()['evicted_cache_names'], ['stale-options-cache']],
    'admitted retry names' => [static fn (): mixed => $plan()['admitted_retry_names'], ['retry-options-page', 'retry-autoload-page']],
    'blocked retry names empty' => [static fn (): mixed => $plan()['blocked_retry_names'], []],
    'blocked reasons empty' => [static fn (): mixed => $plan()['blocked_reasons'], []],
    'guard names' => [static fn (): mixed => $plan()['guard_names'], ['next260_current_source_admitted', 'cache_entries_match_current_source_epoch', 'retry_reads_match_current_source_epoch', 'retry_pages_have_current_cache_entries', 'stale_hot_journal_cache_entries_evicted', 'all_reader_cache_fences_match']],
    'guard matches' => [static fn (): mixed => $plan()['guard_matches'], [true, true, true, true, true, true]],
    'blocked guards empty' => [static fn (): mixed => $plan()['blocked_guard_names'], []],
    'reader cache admitted' => [static fn (): mixed => $plan()['reader_cache_admitted'], true],
    'cache action' => [static fn (): mixed => $plan()['cache_action'], 'reuse_current_source_page_cache_for_retry_readers'],
    'retry action' => [static fn (): mixed => $plan()['retry_action'], 'retry_application_import_readers_on_generation_262'],
    'journal action' => [static fn (): mixed => $plan()['journal_action'], 'hot_journal_remains_retired_for_retry_readers'],
    'cache row reader' => [static fn (): mixed => $plan()['cache_rows'][0]['reader_name'], 'wp-options-reader'],
    'cache row page' => [static fn (): mixed => $plan()['cache_rows'][0]['page_number'], 1],
    'cache row usable' => [static fn (): mixed => $plan()['cache_rows'][0]['usable'], true],
    'stale row evicted' => [static fn (): mixed => $plan()['cache_rows'][2]['evicted'], true],
    'stale row safe' => [static fn (): mixed => $plan()['cache_rows'][2]['safe'], true],
    'stale row reason' => [static fn (): mixed => $plan()['cache_rows'][2]['receipt_reason'], 'cache_still_references_hot_journal_generation|cache_still_references_stale_wal_frame'],
    'retry row reader' => [static fn (): mixed => $plan()['retry_rows'][1]['reader_name'], 'autoload-index-reader'],
    'retry row admitted' => [static fn (): mixed => $plan()['retry_rows'][1]['admitted'], true],
    'digest length' => [static fn (): mixed => strlen($plan()['fence_digest']), 64],
    'operation inherited' => [static fn (): mixed => in_array('admit_hot_journal_savepoint_checkpoint_current_source_next260', $plan()['operation_names'], true), true],
    'operation added' => [static fn (): mixed => in_array('fence_reader_cache_after_hot_journal_checkpoint_current_source_next262', $plan()['operation_names'], true), true],
    'dependency inherited' => [static fn (): mixed => in_array('sqlite-wal-hot-journal-savepoint-checkpoint-current-source-next260', $plan()['dependencies'], true), true],
    'dependency next262' => [static fn (): mixed => in_array('sqlite-wal-hot-journal-savepoint-checkpoint-current-source-next262', $plan()['dependencies'], true), true],
    'dependency application' => [static fn (): mixed => in_array('application-import-retry-reader-cache-current-source', $plan()['dependencies'], true), true],
    'dependency closure' => [static fn (): mixed => str_contains($plan()['dependency_closure'], 'no new support component needed'), true],
    'non overlap' => [static fn (): mixed => str_contains($plan()['non_overlap'], 'does not repeat next260 rollback-journal/savepoint/checkpoint admission'), true],
    'cache source token block' => [static fn (): mixed => $blockedCache(['source_token' => 'old-source'])['blocked_reasons'], ['source_token_mismatch', 'retry_page_missing_current_cache_entry_1']],
    'cache database digest block' => [static fn (): mixed => $blockedCache(['database_digest' => $digest('old database')])['blocked_reasons'], ['database_digest_mismatch', 'retry_page_missing_current_cache_entry_1']],
    'cache page cache digest block' => [static fn (): mixed => $blockedCache(['page_cache_digest' => $digest('old cache')])['blocked_reasons'], ['page_cache_digest_mismatch', 'retry_page_missing_current_cache_entry_1']],
    'cache generation block' => [static fn (): mixed => $blockedCache(['commit_generation' => 261])['blocked_reasons'], ['commit_generation_mismatch', 'retry_page_missing_current_cache_entry_1']],
    'cache schema block' => [static fn (): mixed => $blockedCache(['schema_cookie' => 1261])['blocked_reasons'], ['schema_cookie_mismatch', 'retry_page_missing_current_cache_entry_1']],
    'cache checkpoint block' => [static fn (): mixed => $blockedCache(['checkpoint_frame' => 51])['blocked_reasons'], ['checkpoint_frame_mismatch', 'retry_page_missing_current_cache_entry_1']],
    'cache page block' => [static fn (): mixed => $blockedCache(['page_number' => 9])['blocked_reasons'], ['cache_page_not_in_checkpoint_dirty_set', 'retry_page_missing_current_cache_entry_1']],
    'cache reader block' => [static fn (): mixed => $blockedCache(['reader_name' => 'old-reader'])['blocked_reasons'], ['cache_reader_not_admitted', 'retry_page_missing_current_cache_entry_1']],
    'cache hot journal block' => [static fn (): mixed => $blockedCache(['hot_journal_generation_seen' => 261])['blocked_reasons'], ['cache_still_references_hot_journal_generation', 'retry_page_missing_current_cache_entry_1']],
    'cache stale wal block' => [static fn (): mixed => $blockedCache(['stale_wal_frame_seen' => 48])['blocked_reasons'], ['cache_still_references_stale_wal_frame', 'retry_page_missing_current_cache_entry_1']],
    'cache current evicted block' => [static fn (): mixed => $blockedCache(['evicted' => true])['blocked_reasons'], ['cache_entry_evicted_despite_current_source_match', 'retry_page_missing_current_cache_entry_1']],
    'retry token block' => [static fn (): mixed => $blockedRetry(['source_token' => 'old-source'])['blocked_reasons'], ['source_token_mismatch']],
    'retry database digest block' => [static fn (): mixed => $blockedRetry(['database_digest' => $digest('old database')])['blocked_reasons'], ['database_digest_mismatch']],
    'retry cache digest block' => [static fn (): mixed => $blockedRetry(['page_cache_digest' => $digest('old cache')])['blocked_reasons'], ['page_cache_digest_mismatch']],
    'retry generation block' => [static fn (): mixed => $blockedRetry(['commit_generation' => 261])['blocked_reasons'], ['commit_generation_mismatch']],
    'retry schema block' => [static fn (): mixed => $blockedRetry(['schema_cookie' => 1261])['blocked_reasons'], ['schema_cookie_mismatch']],
    'retry checkpoint block' => [static fn (): mixed => $blockedRetry(['checkpoint_frame' => 51])['blocked_reasons'], ['checkpoint_frame_mismatch']],
    'retry page block' => [static fn (): mixed => $blockedRetry(['page_number' => 7])['missing_retry_pages'], [7]],
    'retry reader block' => [static fn (): mixed => $blockedRetry(['reader_name' => 'old-reader'])['blocked_reasons'], ['retry_reader_not_admitted']],
    'retry snapshot block' => [static fn (): mixed => $blockedRetry(['snapshot_reopened' => false])['blocked_reasons'], ['retry_snapshot_not_reopened']],
    'retry hot journal block' => [static fn (): mixed => $blockedRetry(['hot_journal_visible' => true])['blocked_reasons'], ['retry_hot_journal_visible']],
    'retry stale wal block' => [static fn (): mixed => $blockedRetry(['stale_wal_visible' => true])['blocked_reasons'], ['retry_stale_wal_visible']],
    'blocked status' => [static fn (): mixed => $blockedRetry(['snapshot_reopened' => false])['status'], 'wal-hot-journal-savepoint-checkpoint-current-source-blocked-next262'],
    'blocked reason' => [static fn (): mixed => $blockedRetry(['snapshot_reopened' => false])['reason'], 'reader_cache_retry_held_until_checkpoint_current_source_matches'],
    'blocked cache action' => [static fn (): mixed => $blockedRetry(['snapshot_reopened' => false])['cache_action'], 'evict_or_reload_reader_page_cache_before_retry'],
    'blocked retry action' => [static fn (): mixed => $blockedRetry(['snapshot_reopened' => false])['retry_action'], 'pin_retry_readers_to_reopen_current_source'],
    'blocked journal action' => [static fn (): mixed => $blockedRetry(['snapshot_reopened' => false])['journal_action'], 'retain_hot_journal_recovery_fence_for_retry_readers'],
];

foreach ($cases as $name => [$callback, $expected]) {
    $tests['wal hot journal savepoint checkpoint current source next262 ' . $name] = static function (TestRunner $t) use ($callback, $expected): void {
        $t->same($expected, $callback());
    };
}

$throws = [
    'bad base rejected' => static fn () => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next262FenceReaderCache(array_replace($base, ['status' => 'bad']), $cache, $retry),
    'not admitted rejected' => static fn () => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next262FenceReaderCache(array_replace($base, ['current_source_admitted' => false]), $cache, $retry),
    'empty cache rejected' => static fn () => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next262FenceReaderCache($base, [], $retry),
    'empty retry rejected' => static fn () => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next262FenceReaderCache($base, $cache, []),
    'bad source token rejected' => static fn () => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next262FenceReaderCache(array_replace($base, ['source_token' => 'bad token']), $cache, $retry),
    'bad database digest rejected' => static fn () => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next262FenceReaderCache(array_replace($base, ['database_digest' => 'short']), $cache, $retry),
    'bad page cache digest rejected' => static fn () => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next262FenceReaderCache(array_replace($base, ['page_cache_digest' => 'short']), $cache, $retry),
    'bad generation rejected' => static fn () => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next262FenceReaderCache(array_replace($base, ['commit_generation' => 0]), $cache, $retry),
    'bad schema rejected' => static fn () => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next262FenceReaderCache(array_replace($base, ['schema_cookie' => 0]), $cache, $retry),
    'bad checkpoint rejected' => static fn () => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next262FenceReaderCache(array_replace($base, ['checkpoint_frame' => 0]), $cache, $retry),
    'bad dirty pages rejected' => static fn () => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next262FenceReaderCache(array_replace($base, ['dirty_pages' => []]), $cache, $retry),
    'bad reader set rejected' => static fn () => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next262FenceReaderCache(array_replace($base, ['accepted_reader_names' => []]), $cache, $retry),
    'bad cache name rejected' => static fn () => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next262FenceReaderCache($base, [array_replace($cache[0], ['name' => 'bad name'])], $retry),
    'bad cache reader rejected' => static fn () => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next262FenceReaderCache($base, [array_replace($cache[0], ['reader_name' => 'bad reader'])], $retry),
    'bad cache page rejected' => static fn () => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next262FenceReaderCache($base, [array_replace($cache[0], ['page_number' => 0])], $retry),
    'bad retry name rejected' => static fn () => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next262FenceReaderCache($base, $cache, [array_replace($retry[0], ['name' => 'bad name'])]),
    'bad retry reader rejected' => static fn () => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next262FenceReaderCache($base, $cache, [array_replace($retry[0], ['reader_name' => 'bad reader'])]),
    'bad retry page rejected' => static fn () => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next262FenceReaderCache($base, $cache, [array_replace($retry[0], ['page_number' => 0])]),
];

foreach ($throws as $name => $callback) {
    $tests['wal hot journal savepoint checkpoint current source next262 ' . $name] = static function (TestRunner $t) use ($callback): void {
        $t->throws(Throwable::class, $callback);
    };
}

return $tests;
