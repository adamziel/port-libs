<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan;

require_once __DIR__ . '/../src/SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan.php';

$tests = [];

$digest = static fn (string $value): string => hash('sha256', $value);
$databaseDigest = $digest('next263 checkpoint database image');
$pageCacheDigest = $digest('next263 current page cache image');
$sourceToken = 'wp-next263-current-source';
$common = [
    'source_token' => $sourceToken,
    'database_digest' => $databaseDigest,
    'page_cache_digest' => $pageCacheDigest,
    'commit_generation' => 263,
    'schema_cookie' => 1263,
    'checkpoint_frame' => 53,
];
$base = [
    'status' => 'wal-hot-journal-savepoint-checkpoint-current-source-next262',
    'reader_cache_admitted' => true,
    'database_path' => '/srv/www/wp-content/database/wp-next263.sqlite',
    'journal_path' => '/srv/www/wp-content/database/wp-next263.sqlite-journal',
    'wal_path' => '/srv/www/wp-content/database/wp-next263.sqlite-wal',
    'admitted_retry_names' => ['retry-options-page', 'retry-autoload-page'],
    'retry_pages' => [1, 4],
    'accepted_reader_names' => ['wp-options-reader', 'autoload-index-reader'],
    'operation_names' => ['fence_reader_cache_after_hot_journal_checkpoint_current_source_next262'],
    'dependencies' => ['sqlite-wal-hot-journal-savepoint-checkpoint-current-source-next262'],
] + $common;
$receipt = static function (string $name, string $retryName, string $readerName, int $page, array $override = []) use ($common): array {
    return array_replace([
        'name' => $name,
        'retry_name' => $retryName,
        'reader_name' => $readerName,
        'page_number' => $page,
        'cursor_closed' => true,
        'snapshot_released' => true,
        'hot_journal_visible' => false,
        'stale_wal_visible' => false,
    ] + $common, $override);
};
$receipts = [
    $receipt('close-options-retry', 'retry-options-page', 'wp-options-reader', 1),
    $receipt('close-autoload-retry', 'retry-autoload-page', 'autoload-index-reader', 4),
];

$plan = static fn (?array $inputBase = null, ?array $inputReceipts = null): array =>
    SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next263SealRetryReadReceipts($inputBase ?? $base, $inputReceipts ?? $receipts);
$blocked = static fn (array $replace, int $index = 0): array => $plan(null, array_replace($receipts, [$index => array_replace($receipts[$index], $replace)]));

$cases = [
    'status' => [static fn (): mixed => $plan()['status'], 'wal-hot-journal-savepoint-checkpoint-current-source-next263'],
    'reason' => [static fn (): mixed => $plan()['reason'], 'retry_read_receipts_sealed_on_checkpoint_current_source'],
    'base status' => [static fn (): mixed => $plan()['base_status'], 'wal-hot-journal-savepoint-checkpoint-current-source-next262'],
    'database path' => [static fn (): mixed => $plan()['database_path'], '/srv/www/wp-content/database/wp-next263.sqlite'],
    'journal path' => [static fn (): mixed => $plan()['journal_path'], '/srv/www/wp-content/database/wp-next263.sqlite-journal'],
    'wal path' => [static fn (): mixed => $plan()['wal_path'], '/srv/www/wp-content/database/wp-next263.sqlite-wal'],
    'source token' => [static fn (): mixed => $plan()['source_token'], $sourceToken],
    'generation' => [static fn (): mixed => $plan()['commit_generation'], 263],
    'schema cookie' => [static fn (): mixed => $plan()['schema_cookie'], 1263],
    'checkpoint frame' => [static fn (): mixed => $plan()['checkpoint_frame'], 53],
    'database digest' => [static fn (): mixed => $plan()['database_digest'], $databaseDigest],
    'page cache digest' => [static fn (): mixed => $plan()['page_cache_digest'], $pageCacheDigest],
    'receipt names' => [static fn (): mixed => $plan()['retry_close_receipt_names'], ['close-options-retry', 'close-autoload-retry']],
    'accepted receipt names' => [static fn (): mixed => $plan()['accepted_retry_close_receipt_names'], ['close-options-retry', 'close-autoload-retry']],
    'blocked receipt names empty' => [static fn (): mixed => $plan()['blocked_retry_close_receipt_names'], []],
    'duplicates empty' => [static fn (): mixed => $plan()['duplicate_retry_close_receipt_names'], []],
    'covered retry names' => [static fn (): mixed => $plan()['covered_retry_names'], ['retry-options-page', 'retry-autoload-page']],
    'missing retry names empty' => [static fn (): mixed => $plan()['missing_retry_names'], []],
    'covered pages' => [static fn (): mixed => $plan()['covered_retry_pages'], [1, 4]],
    'missing pages empty' => [static fn (): mixed => $plan()['missing_retry_pages'], []],
    'blocked reasons empty' => [static fn (): mixed => $plan()['blocked_reasons'], []],
    'guard names' => [static fn (): mixed => $plan()['guard_names'], ['next262_reader_cache_admitted', 'retry_close_receipt_names_unique', 'all_retry_names_closed', 'all_retry_pages_closed', 'all_retry_close_receipts_current']],
    'guard matches' => [static fn (): mixed => $plan()['guard_matches'], [true, true, true, true, true]],
    'blocked guards empty' => [static fn (): mixed => $plan()['blocked_guard_names'], []],
    'sealed bool' => [static fn (): mixed => $plan()['retry_read_receipts_sealed'], true],
    'retry action' => [static fn (): mixed => $plan()['retry_action'], 'close_retry_read_receipts_on_generation_263'],
    'cache action' => [static fn (): mixed => $plan()['cache_action'], 'keep_next262_current_source_cache_reusable'],
    'journal action' => [static fn (): mixed => $plan()['journal_action'], 'hot_journal_retirement_confirmed_for_retry_receipts'],
    'row admitted' => [static fn (): mixed => $plan()['retry_receipt_rows'][0]['admitted'], true],
    'row reason' => [static fn (): mixed => $plan()['retry_receipt_rows'][0]['receipt_reason'], 'retry_close_receipt_matches_checkpoint_current_source'],
    'digest length' => [static fn (): mixed => strlen($plan()['seal_digest']), 64],
    'operation inherited' => [static fn (): mixed => in_array('fence_reader_cache_after_hot_journal_checkpoint_current_source_next262', $plan()['operation_names'], true), true],
    'operation added' => [static fn (): mixed => in_array('seal_retry_read_receipts_after_reader_cache_fence_next263', $plan()['operation_names'], true), true],
    'dependency inherited' => [static fn (): mixed => in_array('sqlite-wal-hot-journal-savepoint-checkpoint-current-source-next262', $plan()['dependencies'], true), true],
    'dependency next263' => [static fn (): mixed => in_array('sqlite-wal-hot-journal-savepoint-checkpoint-current-source-next263', $plan()['dependencies'], true), true],
    'dependency wordpress' => [static fn (): mixed => in_array('wordpress-import-retry-reader-receipts-current-source', $plan()['dependencies'], true), true],
    'dependency closure' => [static fn (): mixed => str_contains($plan()['dependency_closure'], 'no new support component needed'), true],
    'non overlap' => [static fn (): mixed => str_contains($plan()['non_overlap'], 'does not repeat next260 checkpoint admission'), true],
    'blocked status' => [static fn (): mixed => $blocked(['cursor_closed' => false])['status'], 'wal-hot-journal-savepoint-checkpoint-current-source-blocked-next263'],
    'blocked reason' => [static fn (): mixed => $blocked(['cursor_closed' => false])['reason'], 'retry_read_receipts_wait_for_checkpoint_current_source'],
    'blocked retry action' => [static fn (): mixed => $blocked(['cursor_closed' => false])['retry_action'], 'hold_retry_read_receipts_for_reopen'],
    'blocked cache action' => [static fn (): mixed => $blocked(['cursor_closed' => false])['cache_action'], 'preserve_next262_cache_fence_until_receipts_close'],
    'blocked journal action' => [static fn (): mixed => $blocked(['cursor_closed' => false])['journal_action'], 'retain_hot_journal_retirement_receipts'],
    'source token block' => [static fn (): mixed => $blocked(['source_token' => 'old-source'])['retry_receipt_rows'][0]['blocked_reasons'], ['retry_close_source_token_mismatch']],
    'database digest block' => [static fn (): mixed => $blocked(['database_digest' => $digest('old database')])['retry_receipt_rows'][0]['blocked_reasons'], ['retry_close_database_digest_mismatch']],
    'page cache digest block' => [static fn (): mixed => $blocked(['page_cache_digest' => $digest('old cache')])['retry_receipt_rows'][0]['blocked_reasons'], ['retry_close_page_cache_digest_mismatch']],
    'generation block' => [static fn (): mixed => $blocked(['commit_generation' => 262])['retry_receipt_rows'][0]['blocked_reasons'], ['retry_close_generation_mismatch']],
    'schema block' => [static fn (): mixed => $blocked(['schema_cookie' => 1262])['retry_receipt_rows'][0]['blocked_reasons'], ['retry_close_schema_cookie_mismatch']],
    'checkpoint block' => [static fn (): mixed => $blocked(['checkpoint_frame' => 52])['retry_receipt_rows'][0]['blocked_reasons'], ['retry_close_checkpoint_frame_mismatch']],
    'retry name block' => [static fn (): mixed => $blocked(['retry_name' => 'old-retry'])['retry_receipt_rows'][0]['blocked_reasons'], ['retry_close_unknown_retry_name']],
    'reader name block' => [static fn (): mixed => $blocked(['reader_name' => 'old-reader'])['retry_receipt_rows'][0]['blocked_reasons'], ['retry_close_unknown_reader_name']],
    'page block' => [static fn (): mixed => $blocked(['page_number' => 9])['retry_receipt_rows'][0]['blocked_reasons'], ['retry_close_unknown_page']],
    'cursor block' => [static fn (): mixed => $blocked(['cursor_closed' => false])['retry_receipt_rows'][0]['blocked_reasons'], ['retry_close_cursor_not_closed']],
    'snapshot block' => [static fn (): mixed => $blocked(['snapshot_released' => false])['retry_receipt_rows'][0]['blocked_reasons'], ['retry_close_snapshot_not_released']],
    'hot journal block' => [static fn (): mixed => $blocked(['hot_journal_visible' => true])['retry_receipt_rows'][0]['blocked_reasons'], ['retry_close_hot_journal_visible']],
    'stale wal block' => [static fn (): mixed => $blocked(['stale_wal_visible' => true])['retry_receipt_rows'][0]['blocked_reasons'], ['retry_close_stale_wal_visible']],
    'duplicate receipt' => [static fn (): mixed => $plan(null, array_replace($receipts, [1 => array_replace($receipts[1], ['name' => 'close-options-retry'])]))['duplicate_retry_close_receipt_names'], ['close-options-retry']],
    'duplicate guard' => [static fn (): mixed => $plan(null, array_replace($receipts, [1 => array_replace($receipts[1], ['name' => 'close-options-retry'])]))['blocked_guard_names'], ['retry_close_receipt_names_unique', 'all_retry_close_receipts_current']],
    'missing retry coverage' => [static fn (): mixed => $plan(null, [$receipts[0]])['missing_retry_names'], ['retry-autoload-page']],
    'missing page coverage' => [static fn (): mixed => $plan(null, [$receipts[0]])['missing_retry_pages'], [4]],
];

foreach ($cases as $name => [$callback, $expected]) {
    $tests['wal hot journal savepoint checkpoint current source next263 ' . $name] = static function (TestRunner $t) use ($callback, $expected): void {
        $t->same($expected, $callback());
    };
}

$throws = [
    'bad base rejected' => static fn () => $plan(array_replace($base, ['status' => 'bad'])),
    'not admitted rejected' => static fn () => $plan(array_replace($base, ['reader_cache_admitted' => false])),
    'empty receipts rejected' => static fn () => $plan(null, []),
    'bad source token rejected' => static fn () => $plan(array_replace($base, ['source_token' => 'bad token'])),
    'bad database digest rejected' => static fn () => $plan(array_replace($base, ['database_digest' => 'short'])),
    'bad page cache digest rejected' => static fn () => $plan(array_replace($base, ['page_cache_digest' => 'short'])),
    'bad generation rejected' => static fn () => $plan(array_replace($base, ['commit_generation' => 0])),
    'bad schema rejected' => static fn () => $plan(array_replace($base, ['schema_cookie' => 0])),
    'bad checkpoint rejected' => static fn () => $plan(array_replace($base, ['checkpoint_frame' => 0])),
    'bad retry names rejected' => static fn () => $plan(array_replace($base, ['admitted_retry_names' => []])),
    'bad retry pages rejected' => static fn () => $plan(array_replace($base, ['retry_pages' => [0]])),
    'bad reader names rejected' => static fn () => $plan(array_replace($base, ['accepted_reader_names' => []])),
    'bad receipt name rejected' => static fn () => $plan(null, [array_replace($receipts[0], ['name' => 'bad name'])]),
    'bad receipt retry rejected' => static fn () => $plan(null, [array_replace($receipts[0], ['retry_name' => 'bad retry'])]),
    'bad receipt reader rejected' => static fn () => $plan(null, [array_replace($receipts[0], ['reader_name' => 'bad reader'])]),
    'bad receipt page rejected' => static fn () => $plan(null, [array_replace($receipts[0], ['page_number' => 0])]),
    'bad receipt token rejected' => static fn () => $plan(null, [array_replace($receipts[0], ['source_token' => 'bad token'])]),
    'bad receipt digest rejected' => static fn () => $plan(null, [array_replace($receipts[0], ['database_digest' => 'short'])]),
];

foreach ($throws as $name => $callback) {
    $tests['wal hot journal savepoint checkpoint current source next263 ' . $name] = static function (TestRunner $t) use ($callback): void {
        $t->throws(Throwable::class, $callback);
    };
}

return $tests;
