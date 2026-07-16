<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan;

require_once __DIR__ . '/../src/SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan.php';

$tests = [];

$digest = static fn (string $value): string => hash('sha256', $value);
$token = ['id' => 'wp-hot-journal-current-source-next227', 'epoch' => 227];
$admission = [
    'status' => 'wal-hot-journal-savepoint-checkpoint-current-source-next211',
    'database_path' => '/srv/www/wp-content/database/wp-next227.sqlite',
    'wal_path' => '/srv/www/wp-content/database/wp-next227.sqlite-wal',
    'journal_path' => '/srv/www/wp-content/database/wp-next227.sqlite-journal',
    'current_source_token' => $token,
    'checkpoint_frame' => 44,
    'checkpoint_cookie' => 90227,
    'schema_cookie' => 1227,
    'next_source_epoch' => 228,
    'checkpoint_admitted' => true,
    'admitted_reader_names' => ['wp-options-import', 'wp-theme-import'],
    'reopen_reader_names' => ['old-plugin-reader'],
    'operation_names' => ['acknowledge_reader_page_digest_next211'],
    'dependencies' => ['sqlite-wal-hot-journal-savepoint-checkpoint-current-source-next211'],
];
$scope = static function (string $name, array $pages, array $overrides = []) use ($digest): array {
    $pageDigests = [];
    foreach ($pages as $page) {
        $pageDigests[$page] = $digest($name . ':page:' . $page);
    }

    return array_merge([
        'name' => $name,
        'savepoint_depth' => 0,
        'released' => true,
        'rollback_generation' => 227,
        'checkpoint_frame' => 44,
        'checkpoint_cookie' => 90227,
        'schema_cookie' => 1227,
        'journal_delete_receipt' => true,
        'wal_reset_frame' => 44,
        'reader_names' => ['wp-options-import'],
        'page_digests' => $pageDigests,
    ], $overrides);
};
$scopes = [
    $scope('wp-options-savepoint', [1, 2]),
    $scope('wp-theme-savepoint', [3], ['reader_names' => ['wp-theme-import']]),
    $scope('wp-cron-savepoint', [4, 5, 6], ['reader_names' => []]),
];
$finalization = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next219Plan($admission, $scopes);
$receipt = static function (string $scopeName, array $pages, array $overrides = []) use ($digest, $token): array {
    $pageDigests = [];
    foreach ($pages as $page) {
        $pageDigests[$page] = $digest($scopeName . ':page:' . $page);
    }

    return array_merge([
        'scope_name' => $scopeName,
        'source_token_id' => $token['id'],
        'source_epoch' => $token['epoch'],
        'checkpoint_frame' => 44,
        'checkpoint_cookie' => 90227,
        'schema_cookie' => 1227,
        'journal_delete_receipt' => true,
        'page_digests' => $pageDigests,
        'next_source_epoch' => 228,
    ], $overrides);
};
$receipts = [
    $receipt('wp-options-savepoint', [1, 2]),
    $receipt('wp-theme-savepoint', [3]),
    $receipt('wp-cron-savepoint', [4, 5, 6]),
];
$plan = static fn (?array $final = null, ?array $inputReceipts = null): array => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next227Plan($final ?? $finalization, $inputReceipts ?? $receipts);

$missing = [$receipts[0], $receipts[1]];
$extra = $receipts;
$extra[] = $receipt('unfinalized-scope', [7]);
$duplicate = $receipts;
$duplicate[] = $receipt('wp-theme-savepoint', [3]);
$badToken = $receipts;
$badToken[0]['source_token_id'] = 'stale-source';
$badEpoch = $receipts;
$badEpoch[0]['source_epoch'] = 226;
$badFrame = $receipts;
$badFrame[0]['checkpoint_frame'] = 43;
$badCookie = $receipts;
$badCookie[0]['checkpoint_cookie'] = 1;
$badSchema = $receipts;
$badSchema[0]['schema_cookie'] = 1;
$badJournal = $receipts;
$badJournal[0]['journal_delete_receipt'] = false;
$badNextEpoch = $receipts;
$badNextEpoch[0]['next_source_epoch'] = 229;
$badPageCount = $receipts;
$badPageCount[0]['page_digests'] = [1 => $digest('wp-options-savepoint:page:1')];
$badPageNumber = $receipts;
$badPageNumber[0]['page_digests'] = [1 => $digest('wp-options-savepoint:page:1'), 9 => $digest('wp-options-savepoint:page:9')];
$badPageDigest = $receipts;
$badPageDigest[0]['page_digests'][2] = $digest('stale-page-two');
$badBlockedFinal = $finalization;
$badBlockedFinal['status'] = 'wal-hot-journal-savepoint-checkpoint-current-source-blocked-next219';
$badPublishFlag = $finalization;
$badPublishFlag['checkpoint_next_source_published'] = false;
$badScopeFinalized = $finalization;
$badScopeFinalized['scope_rows'][0]['finalized'] = false;
$badMissingKey = $finalization;
unset($badMissingKey['checkpoint_frame']);
$badTokenFinal = $finalization;
$badTokenFinal['current_source_token']['id'] = '';

$cases = [
    'status' => [static fn (): mixed => $plan()['status'], 'wal-hot-journal-savepoint-checkpoint-current-source-next227'],
    'reason' => [static fn (): mixed => $plan()['reason'], 'publish_receipts_seal_hot_journal_savepoint_checkpoint_next_source'],
    'database path' => [static fn (): mixed => $plan()['database_path'], $admission['database_path']],
    'wal path' => [static fn (): mixed => $plan()['wal_path'], $admission['wal_path']],
    'journal path' => [static fn (): mixed => $plan()['journal_path'], $admission['journal_path']],
    'checkpoint frame' => [static fn (): mixed => $plan()['checkpoint_frame'], 44],
    'checkpoint cookie' => [static fn (): mixed => $plan()['checkpoint_cookie'], 90227],
    'schema cookie' => [static fn (): mixed => $plan()['schema_cookie'], 1227],
    'next source epoch' => [static fn (): mixed => $plan()['next_source_epoch'], 228],
    'receipt row count' => [static fn (): mixed => count($plan()['receipt_rows']), 3],
    'publishable scopes' => [static fn (): mixed => $plan()['publishable_scope_names'], ['wp-options-savepoint', 'wp-theme-savepoint', 'wp-cron-savepoint']],
    'blocked scopes empty' => [static fn (): mixed => $plan()['blocked_scope_names'], []],
    'missing scopes empty' => [static fn (): mixed => $plan()['missing_scope_names'], []],
    'extra scopes empty' => [static fn (): mixed => $plan()['extra_scope_names'], []],
    'duplicate scopes empty' => [static fn (): mixed => $plan()['duplicate_scope_names'], []],
    'blocked reasons empty' => [static fn (): mixed => $plan()['blocked_reasons'], []],
    'publish allowed' => [static fn (): mixed => $plan()['checkpoint_publish_allowed'], true],
    'publish digest length' => [static fn (): mixed => strlen($plan()['publish_digest']), 64],
    'first receipt publishable' => [static fn (): mixed => $plan()['receipt_rows'][0]['publishable'], true],
    'first receipt reason' => [static fn (): mixed => $plan()['receipt_rows'][0]['receipt_reason'], 'publish_receipt_matches_finalized_scope'],
    'first receipt pages' => [static fn (): mixed => $plan()['receipt_rows'][0]['page_numbers'], [1, 2]],
    'first receipt digest length' => [static fn (): mixed => strlen($plan()['receipt_rows'][0]['receipt_digest']), 64],
    'operation includes seal' => [static fn (): mixed => in_array('seal_hot_journal_delete_receipt_current_source_next227', $plan()['operation_names'], true), true],
    'operation includes publish' => [static fn (): mixed => in_array('publish_checkpoint_next_source_receipt_next227', $plan()['operation_names'], true), true],
    'dependency includes next227' => [static fn (): mixed => in_array('sqlite-wal-hot-journal-savepoint-checkpoint-current-source-next227', $plan()['dependencies'], true), true],
    'dependency includes application' => [static fn (): mixed => in_array('application-import-hot-journal-checkpoint-publish-receipts', $plan()['dependencies'], true), true],
    'dependency closure' => [static fn (): mixed => str_contains($plan()['dependency_closure'], 'no new support component needed'), true],
    'non overlap' => [static fn (): mixed => str_contains($plan()['non_overlap'], 'does not repeat next211'), true],
    'guard names count' => [static fn (): mixed => count($plan()['guard_names']), 6],
    'guard matches' => [static fn (): mixed => $plan()['guard_matches'], [true, true, true, true, true, true]],
    'blocked guards empty' => [static fn (): mixed => $plan()['blocked_guard_names'], []],
    'missing receipt blocks' => [static fn (): mixed => $plan(null, $missing)['status'], 'wal-hot-journal-savepoint-checkpoint-current-source-blocked-next227'],
    'missing receipt reason' => [static fn (): mixed => $plan(null, $missing)['blocked_reasons'], ['finalized_scope_publish_receipt_missing']],
    'missing receipt scope' => [static fn (): mixed => $plan(null, $missing)['missing_scope_names'], ['wp-cron-savepoint']],
    'extra receipt blocks' => [static fn (): mixed => $plan(null, $extra)['extra_scope_names'], ['unfinalized-scope']],
    'extra receipt reason' => [static fn (): mixed => in_array('publish_receipt_for_unfinalized_scope', $plan(null, $extra)['blocked_reasons'], true), true],
    'duplicate receipt blocks' => [static fn (): mixed => $plan(null, $duplicate)['duplicate_scope_names'], ['wp-theme-savepoint']],
    'duplicate receipt reason' => [static fn (): mixed => in_array('duplicate_scope_publish_receipt', $plan(null, $duplicate)['blocked_reasons'], true), true],
    'bad token reason' => [static fn (): mixed => $plan(null, $badToken)['receipt_rows'][0]['blocked_reasons'], ['publish_source_token_mismatch']],
    'bad epoch reason' => [static fn (): mixed => $plan(null, $badEpoch)['receipt_rows'][0]['blocked_reasons'], ['publish_source_epoch_mismatch']],
    'bad frame reason' => [static fn (): mixed => $plan(null, $badFrame)['receipt_rows'][0]['blocked_reasons'], ['publish_checkpoint_frame_mismatch']],
    'bad cookie reason' => [static fn (): mixed => $plan(null, $badCookie)['receipt_rows'][0]['blocked_reasons'], ['publish_checkpoint_cookie_mismatch']],
    'bad schema reason' => [static fn (): mixed => $plan(null, $badSchema)['receipt_rows'][0]['blocked_reasons'], ['publish_schema_cookie_mismatch']],
    'bad journal reason' => [static fn (): mixed => $plan(null, $badJournal)['receipt_rows'][0]['blocked_reasons'], ['publish_hot_journal_delete_receipt_missing']],
    'bad next epoch reason' => [static fn (): mixed => $plan(null, $badNextEpoch)['receipt_rows'][0]['blocked_reasons'], ['publish_next_source_epoch_mismatch']],
    'bad next epoch guard' => [static fn (): mixed => in_array('next_source_epoch_advances_once', $plan(null, $badNextEpoch)['blocked_guard_names'], true), true],
    'bad page count reason' => [static fn (): mixed => $plan(null, $badPageCount)['receipt_rows'][0]['blocked_reasons'], ['publish_page_digest_count_mismatch', 'publish_page_number_mismatch', 'publish_page_digest_mismatch']],
    'bad page number reason' => [static fn (): mixed => $plan(null, $badPageNumber)['receipt_rows'][0]['blocked_reasons'], ['publish_page_number_mismatch', 'publish_page_digest_mismatch']],
    'bad page digest reason' => [static fn (): mixed => $plan(null, $badPageDigest)['receipt_rows'][0]['blocked_reasons'], ['publish_page_digest_mismatch']],
    'blocked finalization guard' => [static fn (): mixed => $plan($badBlockedFinal)['blocked_guard_names'], ['next219_finalization_admitted']],
    'blocked publish flag guard' => [static fn (): mixed => $plan($badPublishFlag)['blocked_guard_names'], ['next219_finalization_admitted']],
    'unfinalized scope reason' => [static fn (): mixed => $plan($badScopeFinalized)['receipt_rows'][0]['blocked_reasons'], ['publish_scope_not_finalized']],
    'empty receipts rejected' => [static fn (): mixed => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next227Plan($finalization, []), InvalidArgumentException::class],
    'missing finalization key rejected' => [static fn (): mixed => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next227Plan($badMissingKey, $receipts), InvalidArgumentException::class],
    'bad token finalization rejected' => [static fn (): mixed => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next227Plan($badTokenFinal, $receipts), InvalidArgumentException::class],
    'bad receipt digest rejected' => [static fn (): mixed => $plan(null, [$receipt('wp-options-savepoint', [1, 2], ['page_digests' => [1 => 'bad']])]), InvalidArgumentException::class],
    'bad receipt scope rejected' => [static fn (): mixed => $plan(null, [$receipt('', [1])]), InvalidArgumentException::class],
];

foreach ($cases as $name => [$callback, $expected]) {
    $tests['wal hot journal savepoint checkpoint current source next227 ' . $name] = static function (TestRunner $t) use ($callback, $expected): void {
        if (is_string($expected) && class_exists($expected) && is_a($expected, Throwable::class, true)) {
            $t->throws($expected, $callback);
            return;
        }
        $t->same($expected, $callback());
    };
}

return $tests;
