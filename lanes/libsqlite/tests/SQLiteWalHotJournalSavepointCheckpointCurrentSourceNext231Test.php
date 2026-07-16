<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan;

require_once __DIR__ . '/../src/SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan.php';

$tests = [];

$digest = static fn (string $value): string => hash('sha256', $value);
$walDigest = $digest('next231 restarted wal header bytes plus no frames');
$token = ['id' => 'wp-hot-journal-current-source-next231', 'epoch' => 231];
$admission = [
    'status' => 'wal-hot-journal-savepoint-checkpoint-current-source-next211',
    'database_path' => '/srv/www/wp-content/database/wp-next231.sqlite',
    'wal_path' => '/srv/www/wp-content/database/wp-next231.sqlite-wal',
    'journal_path' => '/srv/www/wp-content/database/wp-next231.sqlite-journal',
    'current_source_token' => $token,
    'checkpoint_frame' => 88,
    'checkpoint_cookie' => 90231,
    'schema_cookie' => 1231,
    'next_source_epoch' => 232,
    'checkpoint_admitted' => true,
    'admitted_reader_names' => ['wp-options-import', 'wp-theme-import'],
    'reopen_reader_names' => ['old-plugin-reader'],
    'operation_names' => ['acknowledge_reader_page_digest_next211'],
    'dependencies' => ['sqlite-wal-hot-journal-savepoint-checkpoint-current-source-next211'],
];
$scope = static function (string $name, array $pages, array $readers) use ($digest): array {
    $pageDigests = [];
    foreach ($pages as $page) {
        $pageDigests[$page] = $digest($name . ':page:' . $page);
    }

    return [
        'name' => $name,
        'savepoint_depth' => 0,
        'released' => true,
        'rollback_generation' => 231,
        'checkpoint_frame' => 88,
        'checkpoint_cookie' => 90231,
        'schema_cookie' => 1231,
        'journal_delete_receipt' => true,
        'wal_reset_frame' => 88,
        'reader_names' => $readers,
        'page_digests' => $pageDigests,
    ];
};
$receipt = static function (string $scopeName, array $pages) use ($digest, $token): array {
    $pageDigests = [];
    foreach ($pages as $page) {
        $pageDigests[$page] = $digest($scopeName . ':page:' . $page);
    }

    return [
        'scope_name' => $scopeName,
        'source_token_id' => $token['id'],
        'source_epoch' => 231,
        'checkpoint_frame' => 88,
        'checkpoint_cookie' => 90231,
        'schema_cookie' => 1231,
        'journal_delete_receipt' => true,
        'page_digests' => $pageDigests,
        'next_source_epoch' => 232,
    ];
};
$finalized = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next219Plan($admission, [
    $scope('wp-options-savepoint', [1, 2], ['wp-options-import']),
    $scope('wp-theme-savepoint', [3], ['wp-theme-import']),
    $scope('wp-cron-savepoint', [4], []),
]);
$publish = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next227Plan($finalized, [
    $receipt('wp-options-savepoint', [1, 2]),
    $receipt('wp-theme-savepoint', [3]),
    $receipt('wp-cron-savepoint', [4]),
]);
$checksum = static fn (int $salt1, int $salt2, int $mx, int $backfill, array $readmarks, string $digest): string =>
    hash('sha256', json_encode([$salt1, $salt2, $mx, $backfill, $readmarks, $digest], JSON_THROW_ON_ERROR));
$walIndexReceipt = static function (array $overrides = []) use ($checksum, $walDigest, $token): array {
    $readmarks = ['wp-cron-reader' => 88, 'wp-options-reader' => 88];
    $salt1 = 23101;
    $salt2 = 23102;
    $mx = 88;
    $backfill = 88;

    return array_merge([
        'name' => 'wp-options-shm-reopen-next231',
        'scope_names' => ['wp-options-savepoint', 'wp-theme-savepoint'],
        'source_token_id' => $token['id'],
        'source_epoch' => 231,
        'next_source_epoch' => 232,
        'checkpoint_frame' => 88,
        'checkpoint_cookie' => 90231,
        'schema_cookie' => 1231,
        'wal_digest' => $walDigest,
        'salt_1' => $salt1,
        'salt_2' => $salt2,
        'checksum_digest' => $checksum($salt1, $salt2, $mx, $backfill, $readmarks, $walDigest),
        'mx_frame' => $mx,
        'backfill_frame' => $backfill,
        'readmark_frames' => $readmarks,
        'readers_reopened' => true,
        'shm_synced' => true,
    ], $overrides);
};
$cronReceipt = $walIndexReceipt([
    'name' => 'wp-cron-shm-reopen-next231',
    'scope_names' => ['wp-cron-savepoint'],
    'readmark_frames' => ['wp-cron-reader' => 88],
    'checksum_digest' => $checksum(23101, 23102, 88, 88, ['wp-cron-reader' => 88], $walDigest),
]);
$receipts = [$walIndexReceipt(), $cronReceipt];
$plan = static fn (?array $inputPublish = null, ?array $inputReceipts = null, ?string $digestValue = null): array =>
    SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next231VerifyWalIndexReopen(
        $inputPublish ?? $publish,
        $inputReceipts ?? $receipts,
        $digestValue ?? $walDigest
    );

$badPublishStatus = $publish;
$badPublishStatus['status'] = 'wal-hot-journal-savepoint-checkpoint-current-source-blocked-next227';
$badPublishFlag = $publish;
$badPublishFlag['checkpoint_publish_allowed'] = false;
$badMissingPublishKey = $publish;
unset($badMissingPublishKey['checkpoint_frame']);
$badTokenPublish = $publish;
$badTokenPublish['current_source_token']['id'] = '';
$badNextEpochPublish = $publish;
$badNextEpochPublish['next_source_epoch'] = 231;

$missingScopeReceipts = [$walIndexReceipt()];
$extraScopeReceipts = [$walIndexReceipt(['scope_names' => ['wp-options-savepoint', 'not-published']]), $cronReceipt];
$duplicateNameReceipts = [$walIndexReceipt(), $walIndexReceipt(['scope_names' => ['wp-cron-savepoint']])];
$badTokenReceipts = [$walIndexReceipt(['source_token_id' => 'stale-token']), $cronReceipt];
$badSourceEpochReceipts = [$walIndexReceipt(['source_epoch' => 230]), $cronReceipt];
$badNextEpochReceipts = [$walIndexReceipt(['next_source_epoch' => 233]), $cronReceipt];
$badFrameReceipts = [$walIndexReceipt(['checkpoint_frame' => 87]), $cronReceipt];
$badCookieReceipts = [$walIndexReceipt(['checkpoint_cookie' => 1]), $cronReceipt];
$badSchemaReceipts = [$walIndexReceipt(['schema_cookie' => 1]), $cronReceipt];
$badWalReceipts = [$walIndexReceipt(['wal_digest' => $digest('stale-wal')]), $cronReceipt];
$badSaltReceipts = [$walIndexReceipt(['salt_1' => 0]), $cronReceipt];
$badMxReceipts = [$walIndexReceipt(['mx_frame' => 87]), $cronReceipt];
$badBackfillReceipts = [$walIndexReceipt(['backfill_frame' => 87]), $cronReceipt];
$badReadersReceipts = [$walIndexReceipt(['readers_reopened' => false]), $cronReceipt];
$badSyncReceipts = [$walIndexReceipt(['shm_synced' => false]), $cronReceipt];
$badReadmarkReceipts = [$walIndexReceipt([
    'readmark_frames' => ['wp-options-reader' => 87],
    'checksum_digest' => $checksum(23101, 23102, 88, 88, ['wp-options-reader' => 87], $walDigest),
]), $cronReceipt];
$badChecksumReceipts = [$walIndexReceipt(['checksum_digest' => $digest('bad-checksum')]), $cronReceipt];

$cases = [
    'status' => [static fn (): mixed => $plan()['status'], 'wal-hot-journal-savepoint-checkpoint-current-source-next231'],
    'reason' => [static fn (): mixed => $plan()['reason'], 'wal_index_reopen_receipts_match_published_checkpoint_current_source'],
    'database path' => [static fn (): mixed => $plan()['database_path'], $admission['database_path']],
    'wal path' => [static fn (): mixed => $plan()['wal_path'], $admission['wal_path']],
    'journal path' => [static fn (): mixed => $plan()['journal_path'], $admission['journal_path']],
    'source epoch' => [static fn (): mixed => $plan()['source_epoch'], 231],
    'next source epoch' => [static fn (): mixed => $plan()['next_source_epoch'], 232],
    'checkpoint frame' => [static fn (): mixed => $plan()['checkpoint_frame'], 88],
    'checkpoint cookie' => [static fn (): mixed => $plan()['checkpoint_cookie'], 90231],
    'schema cookie' => [static fn (): mixed => $plan()['schema_cookie'], 1231],
    'expected wal digest' => [static fn (): mixed => $plan()['expected_wal_digest'], $walDigest],
    'publishable scopes' => [static fn (): mixed => $plan()['publishable_scope_names'], ['wp-options-savepoint', 'wp-theme-savepoint', 'wp-cron-savepoint']],
    'covered scopes' => [static fn (): mixed => $plan()['covered_scope_names'], ['wp-options-savepoint', 'wp-theme-savepoint', 'wp-cron-savepoint']],
    'missing scopes empty' => [static fn (): mixed => $plan()['missing_scope_names'], []],
    'extra scopes empty' => [static fn (): mixed => $plan()['extra_scope_names'], []],
    'duplicate names empty' => [static fn (): mixed => $plan()['duplicate_receipt_names'], []],
    'receipt names' => [static fn (): mixed => $plan()['receipt_names'], ['wp-options-shm-reopen-next231', 'wp-cron-shm-reopen-next231']],
    'reopenable receipt names' => [static fn (): mixed => $plan()['reopenable_receipt_names'], ['wp-options-shm-reopen-next231', 'wp-cron-shm-reopen-next231']],
    'blocked receipt names empty' => [static fn (): mixed => $plan()['blocked_receipt_names'], []],
    'blocked reasons empty' => [static fn (): mixed => $plan()['blocked_reasons'], []],
    'guard names' => [static fn (): mixed => $plan()['guard_names'], ['next227_publish_receipts_admitted', 'no_duplicate_wal_index_receipts', 'all_publishable_scopes_have_wal_index_reopen_receipts', 'no_unpublished_scope_wal_index_receipts', 'all_wal_index_receipts_reopenable', 'next_source_epoch_matches_publish_epoch', 'checkpoint_frame_matches_publish_frame']],
    'guard matches' => [static fn (): mixed => $plan()['guard_matches'], [true, true, true, true, true, true, true]],
    'blocked guards empty' => [static fn (): mixed => $plan()['blocked_guard_names'], []],
    'can reopen current source' => [static fn (): mixed => $plan()['can_reopen_current_source'], true],
    'reopen digest length' => [static fn (): mixed => strlen($plan()['reopen_digest']), 64],
    'operation verify' => [static fn (): mixed => in_array('verify_wal_index_reopen_receipts_current_source_next231', $plan()['operation_names'], true), true],
    'operation readmark' => [static fn (): mixed => in_array('validate_reader_readmarks_after_checkpoint_publish_next231', $plan()['operation_names'], true), true],
    'operation advance' => [static fn (): mixed => in_array('advance_reopened_current_source_after_wal_index_next231', $plan()['operation_names'], true), true],
    'dependency next231' => [static fn (): mixed => in_array('sqlite-wal-hot-journal-savepoint-checkpoint-current-source-next231', $plan()['dependencies'], true), true],
    'dependency readmark fence' => [static fn (): mixed => in_array('sqlite-wal-index-reopen-readmark-fence', $plan()['dependencies'], true), true],
    'dependency application' => [static fn (): mixed => in_array('application-import-wal-index-reopen-current-source', $plan()['dependencies'], true), true],
    'dependency closure' => [static fn (): mixed => str_contains($plan()['dependency_closure'], 'no new support component needed'), true],
    'non overlap' => [static fn (): mixed => str_contains($plan()['non_overlap'], 'standalone SHM read-mark diagnostics'), true],
    'first row reopenable' => [static fn (): mixed => $plan()['receipt_rows'][0]['reopenable'], true],
    'first row reason' => [static fn (): mixed => $plan()['receipt_rows'][0]['receipt_reason'], 'wal_index_reopen_receipt_matches_published_checkpoint'],
    'first row scopes' => [static fn (): mixed => $plan()['receipt_rows'][0]['scope_names'], ['wp-options-savepoint', 'wp-theme-savepoint']],
    'first row token' => [static fn (): mixed => $plan()['receipt_rows'][0]['source_token_id'], $token['id']],
    'first row source epoch' => [static fn (): mixed => $plan()['receipt_rows'][0]['source_epoch'], 231],
    'first row next epoch' => [static fn (): mixed => $plan()['receipt_rows'][0]['next_source_epoch'], 232],
    'first row frame' => [static fn (): mixed => $plan()['receipt_rows'][0]['checkpoint_frame'], 88],
    'first row mx frame' => [static fn (): mixed => $plan()['receipt_rows'][0]['mx_frame'], 88],
    'first row backfill' => [static fn (): mixed => $plan()['receipt_rows'][0]['backfill_frame'], 88],
    'first row readmarks' => [static fn (): mixed => $plan()['receipt_rows'][0]['readmark_frames'], ['wp-cron-reader' => 88, 'wp-options-reader' => 88]],
    'first row reader names' => [static fn (): mixed => $plan()['receipt_rows'][0]['readmark_reader_names'], ['wp-cron-reader', 'wp-options-reader']],
    'first row checksum matches' => [static fn (): mixed => $plan()['receipt_rows'][0]['checksum_digest'], $plan()['receipt_rows'][0]['expected_checksum_digest']],
    'first row receipt digest length' => [static fn (): mixed => strlen($plan()['receipt_rows'][0]['receipt_digest']), 64],
    'second row scopes' => [static fn (): mixed => $plan()['receipt_rows'][1]['scope_names'], ['wp-cron-savepoint']],
    'missing scope blocks' => [static fn (): mixed => $plan(null, $missingScopeReceipts)['status'], 'wal-hot-journal-savepoint-checkpoint-current-source-blocked-next231'],
    'missing scope reason' => [static fn (): mixed => $plan(null, $missingScopeReceipts)['blocked_reasons'], ['wal_index_scope_reopen_receipt_missing']],
    'extra scope reason' => [static fn (): mixed => in_array('wal_index_receipt_for_unpublished_scope', $plan(null, $extraScopeReceipts)['blocked_reasons'], true), true],
    'duplicate name reason' => [static fn (): mixed => in_array('duplicate_wal_index_reopen_receipt', $plan(null, $duplicateNameReceipts)['blocked_reasons'], true), true],
    'bad token reason' => [static fn (): mixed => $plan(null, $badTokenReceipts)['receipt_rows'][0]['blocked_reasons'], ['wal_index_source_token_mismatch']],
    'bad source epoch reason' => [static fn (): mixed => $plan(null, $badSourceEpochReceipts)['receipt_rows'][0]['blocked_reasons'], ['wal_index_source_epoch_mismatch']],
    'bad next epoch reason' => [static fn (): mixed => $plan(null, $badNextEpochReceipts)['receipt_rows'][0]['blocked_reasons'], ['wal_index_next_source_epoch_mismatch']],
    'bad next epoch guard' => [static fn (): mixed => in_array('next_source_epoch_matches_publish_epoch', $plan(null, $badNextEpochReceipts)['blocked_guard_names'], true), true],
    'bad frame reason' => [static fn (): mixed => $plan(null, $badFrameReceipts)['receipt_rows'][0]['blocked_reasons'], ['wal_index_checkpoint_frame_mismatch']],
    'bad cookie reason' => [static fn (): mixed => $plan(null, $badCookieReceipts)['receipt_rows'][0]['blocked_reasons'], ['wal_index_checkpoint_cookie_mismatch']],
    'bad schema reason' => [static fn (): mixed => $plan(null, $badSchemaReceipts)['receipt_rows'][0]['blocked_reasons'], ['wal_index_schema_cookie_mismatch']],
    'bad wal reason' => [static fn (): mixed => $plan(null, $badWalReceipts)['receipt_rows'][0]['blocked_reasons'], ['wal_index_wal_digest_mismatch']],
    'bad salt reason' => [static fn (): mixed => $plan(null, $badSaltReceipts)['receipt_rows'][0]['blocked_reasons'], ['wal_index_salt_missing', 'wal_index_checksum_digest_mismatch']],
    'bad mx reason' => [static fn (): mixed => $plan(null, $badMxReceipts)['receipt_rows'][0]['blocked_reasons'], ['wal_index_mx_frame_mismatch', 'wal_index_checksum_digest_mismatch']],
    'bad mx guard' => [static fn (): mixed => in_array('checkpoint_frame_matches_publish_frame', $plan(null, $badMxReceipts)['blocked_guard_names'], true), true],
    'bad backfill reason' => [static fn (): mixed => $plan(null, $badBackfillReceipts)['receipt_rows'][0]['blocked_reasons'], ['wal_index_backfill_before_checkpoint', 'wal_index_checksum_digest_mismatch']],
    'bad readers reason' => [static fn (): mixed => $plan(null, $badReadersReceipts)['receipt_rows'][0]['blocked_reasons'], ['wal_index_readers_reopened_receipt_missing']],
    'bad sync reason' => [static fn (): mixed => $plan(null, $badSyncReceipts)['receipt_rows'][0]['blocked_reasons'], ['wal_index_shm_sync_missing']],
    'bad readmark reason' => [static fn (): mixed => $plan(null, $badReadmarkReceipts)['receipt_rows'][0]['blocked_reasons'], ['wal_index_readmark_frame_mismatch']],
    'bad checksum reason' => [static fn (): mixed => $plan(null, $badChecksumReceipts)['receipt_rows'][0]['blocked_reasons'], ['wal_index_checksum_digest_mismatch']],
    'bad publish status rejected' => [static fn (): mixed => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next231VerifyWalIndexReopen($badPublishStatus, $receipts, $walDigest), InvalidArgumentException::class],
    'bad publish flag rejected' => [static fn (): mixed => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next231VerifyWalIndexReopen($badPublishFlag, $receipts, $walDigest), InvalidArgumentException::class],
    'missing publish key rejected' => [static fn (): mixed => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next231VerifyWalIndexReopen($badMissingPublishKey, $receipts, $walDigest), InvalidArgumentException::class],
    'bad publish token rejected' => [static fn (): mixed => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next231VerifyWalIndexReopen($badTokenPublish, $receipts, $walDigest), InvalidArgumentException::class],
    'bad publish next epoch rejected' => [static fn (): mixed => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next231VerifyWalIndexReopen($badNextEpochPublish, $receipts, $walDigest), InvalidArgumentException::class],
    'empty receipts rejected' => [static fn (): mixed => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next231VerifyWalIndexReopen($publish, [], $walDigest), InvalidArgumentException::class],
    'bad expected digest rejected' => [static fn (): mixed => $plan(null, null, 'bad'), InvalidArgumentException::class],
    'empty receipt name rejected' => [static fn (): mixed => $plan(null, [$walIndexReceipt(['name' => ''])]), InvalidArgumentException::class],
    'bad receipt digest rejected' => [static fn (): mixed => $plan(null, [$walIndexReceipt(['wal_digest' => 'bad'])]), InvalidArgumentException::class],
    'bad checksum digest rejected' => [static fn (): mixed => $plan(null, [$walIndexReceipt(['checksum_digest' => 'bad'])]), InvalidArgumentException::class],
    'bad scope list rejected' => [static fn (): mixed => $plan(null, [$walIndexReceipt(['scope_names' => []])]), InvalidArgumentException::class],
    'bad readmark list rejected' => [static fn (): mixed => $plan(null, [$walIndexReceipt(['readmark_frames' => []])]), InvalidArgumentException::class],
    'bad readmark reader rejected' => [static fn (): mixed => $plan(null, [$walIndexReceipt(['readmark_frames' => ['' => 88]])]), InvalidArgumentException::class],
];

foreach ($cases as $name => [$callback, $expected]) {
    $tests['wal hot journal savepoint checkpoint current source next231 ' . $name] = static function (TestRunner $t) use ($callback, $expected): void {
        if (is_string($expected) && class_exists($expected) && is_a($expected, Throwable::class, true)) {
            $t->throws($expected, $callback);
            return;
        }
        $t->same($expected, $callback());
    };
}

return $tests;
