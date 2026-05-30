<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan;

require_once __DIR__ . '/../src/SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan.php';

$tests = [];

$digest = static fn (string $value): string => hash('sha256', $value);
$token = ['id' => 'wp-hot-journal-current-source-next230', 'epoch' => 230];
$scopeDigests = static function (string $scope, array $pages) use ($digest): array {
    $rows = [];
    foreach ($pages as $page) {
        $rows[$page] = $digest($scope . ':checkpoint-page:' . $page);
    }

    return $rows;
};
$receiptRow = static function (string $scope, array $pages) use ($scopeDigests): array {
    return [
        'scope_name' => $scope,
        'publishable' => true,
        'page_digests' => $scopeDigests($scope, $pages),
    ];
};
$publish = [
    'status' => 'wal-hot-journal-savepoint-checkpoint-current-source-next227',
    'database_path' => '/srv/www/wp-content/database/wp-next230.sqlite',
    'wal_path' => '/srv/www/wp-content/database/wp-next230.sqlite-wal',
    'journal_path' => '/srv/www/wp-content/database/wp-next230.sqlite-journal',
    'current_source_token' => $token,
    'checkpoint_frame' => 52,
    'checkpoint_cookie' => 90230,
    'schema_cookie' => 1230,
    'next_source_epoch' => 231,
    'checkpoint_publish_allowed' => true,
    'receipt_rows' => [
        $receiptRow('wp-options-savepoint', [1, 2]),
        $receiptRow('wp-theme-savepoint', [3, 4]),
        $receiptRow('wp-cron-savepoint', [5]),
    ],
    'operation_names' => ['publish_checkpoint_next_source_receipt_next227'],
    'dependencies' => ['sqlite-wal-hot-journal-savepoint-checkpoint-current-source-next227'],
];
$ticket = static function (string $reader, string $scope, array $pages, array $overrides = []) use ($token, $scopeDigests): array {
    return array_merge([
        'reader_name' => $reader,
        'scope_name' => $scope,
        'source_token_id' => $token['id'],
        'source_epoch' => 231,
        'checkpoint_frame' => 52,
        'checkpoint_cookie' => 90230,
        'schema_cookie' => 1230,
        'visible_page_digests' => $scopeDigests($scope, $pages),
        'hot_journal_visible' => false,
        'wal_tail_visible' => false,
    ], $overrides);
};
$tickets = [
    $ticket('wp-options-reader', 'wp-options-savepoint', [1, 2]),
    $ticket('wp-theme-reader', 'wp-theme-savepoint', [3, 4]),
    $ticket('wp-cron-reader', 'wp-cron-savepoint', [5]),
];
$plan = static fn (?array $input = null, ?array $inputTickets = null): array =>
    SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next230Plan($input ?? $publish, $inputTickets ?? $tickets);

$blockedPublish = $publish;
$blockedPublish['status'] = 'wal-hot-journal-savepoint-checkpoint-current-source-blocked-next227';
$badPublishFlag = $publish;
$badPublishFlag['checkpoint_publish_allowed'] = false;
$badReceiptRows = $publish;
$badReceiptRows['receipt_rows'][0]['publishable'] = false;
$badTokenPublish = $publish;
$badTokenPublish['current_source_token']['id'] = '';
$badMissingKey = $publish;
unset($badMissingKey['checkpoint_frame']);

$badToken = $tickets;
$badToken[0]['source_token_id'] = 'stale-token';
$badEpoch = $tickets;
$badEpoch[0]['source_epoch'] = 230;
$badFrame = $tickets;
$badFrame[0]['checkpoint_frame'] = 51;
$badCookie = $tickets;
$badCookie[0]['checkpoint_cookie'] = 1;
$badSchema = $tickets;
$badSchema[0]['schema_cookie'] = 1;
$badHotJournal = $tickets;
$badHotJournal[0]['hot_journal_visible'] = true;
$badWalTail = $tickets;
$badWalTail[0]['wal_tail_visible'] = true;
$badScope = $tickets;
$badScope[0]['scope_name'] = 'unpublished-savepoint';
$badPageCount = $tickets;
$badPageCount[0]['visible_page_digests'] = [1 => $scopeDigests('wp-options-savepoint', [1])[1]];
$badPageNumber = $tickets;
$badPageNumber[0]['visible_page_digests'] = [1 => $scopeDigests('wp-options-savepoint', [1])[1], 9 => $digest('extra-page')];
$badPageDigest = $tickets;
$badPageDigest[0]['visible_page_digests'][2] = $digest('stale-page-two');
$duplicate = $tickets;
$duplicate[] = $ticket('wp-theme-reader', 'wp-theme-savepoint', [3, 4]);

$cases = [
    'status' => [static fn (): mixed => $plan()['status'], 'wal-hot-journal-savepoint-checkpoint-current-source-next230'],
    'reason' => [static fn (): mixed => $plan()['reason'], 'reopened_readers_observe_checkpoint_next_source_after_hot_journal_savepoint'],
    'database path' => [static fn (): mixed => $plan()['database_path'], $publish['database_path']],
    'wal path' => [static fn (): mixed => $plan()['wal_path'], $publish['wal_path']],
    'journal path' => [static fn (): mixed => $plan()['journal_path'], $publish['journal_path']],
    'reader epoch' => [static fn (): mixed => $plan()['reader_epoch'], 231],
    'checkpoint frame' => [static fn (): mixed => $plan()['checkpoint_frame'], 52],
    'checkpoint cookie' => [static fn (): mixed => $plan()['checkpoint_cookie'], 90230],
    'schema cookie' => [static fn (): mixed => $plan()['schema_cookie'], 1230],
    'ticket row count' => [static fn (): mixed => count($plan()['ticket_rows']), 3],
    'admitted readers' => [static fn (): mixed => $plan()['admitted_reader_names'], ['wp-options-reader', 'wp-theme-reader', 'wp-cron-reader']],
    'blocked readers empty' => [static fn (): mixed => $plan()['blocked_reader_names'], []],
    'duplicates empty' => [static fn (): mixed => $plan()['duplicate_reader_names'], []],
    'blocked reasons empty' => [static fn (): mixed => $plan()['blocked_reasons'], []],
    'guard names' => [static fn (): mixed => $plan()['guard_names'], ['next227_publish_receipts_admitted', 'all_reader_tickets_admitted', 'no_duplicate_reader_tickets', 'all_readers_use_next_source_epoch', 'all_readers_hide_hot_journal_and_wal_tail']],
    'guard matches' => [static fn (): mixed => $plan()['guard_matches'], [true, true, true, true, true]],
    'blocked guards empty' => [static fn (): mixed => $plan()['blocked_guard_names'], []],
    'can serve readers' => [static fn (): mixed => $plan()['can_serve_next_source_readers'], true],
    'ticket digest length' => [static fn (): mixed => strlen($plan()['reader_ticket_digest']), 64],
    'operation inherited' => [static fn (): mixed => $plan()['operation_names'][0], 'publish_checkpoint_next_source_receipt_next227'],
    'operation verify' => [static fn (): mixed => in_array('verify_reopened_reader_next_source_tickets_next230', $plan()['operation_names'], true), true],
    'operation fence' => [static fn (): mixed => in_array('fence_hot_journal_and_wal_tail_from_reopened_readers_next230', $plan()['operation_names'], true), true],
    'operation serve' => [static fn (): mixed => in_array('serve_checkpoint_next_source_readers_next230', $plan()['operation_names'], true), true],
    'dependency next230' => [static fn (): mixed => in_array('sqlite-wal-hot-journal-savepoint-checkpoint-current-source-next230', $plan()['dependencies'], true), true],
    'dependency application' => [static fn (): mixed => in_array('application-import-hot-journal-checkpoint-reader-reopen', $plan()['dependencies'], true), true],
    'dependency closure' => [static fn (): mixed => str_contains($plan()['dependency_closure'], 'no new support component needed'), true],
    'non overlap' => [static fn (): mixed => str_contains($plan()['non_overlap'], 'does not repeat next226'), true],
    'first reader admitted' => [static fn (): mixed => $plan()['ticket_rows'][0]['admitted'], true],
    'first reader reason' => [static fn (): mixed => $plan()['ticket_rows'][0]['ticket_reason'], 'reader_ticket_matches_published_checkpoint_source'],
    'first reader pages' => [static fn (): mixed => $plan()['ticket_rows'][0]['page_numbers'], [1, 2]],
    'first reader hot journal hidden' => [static fn (): mixed => $plan()['ticket_rows'][0]['hot_journal_visible'], false],
    'first reader wal tail hidden' => [static fn (): mixed => $plan()['ticket_rows'][0]['wal_tail_visible'], false],
    'first reader ticket digest length' => [static fn (): mixed => strlen($plan()['ticket_rows'][0]['ticket_digest']), 64],
    'bad publish status rejected' => [static fn (): mixed => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next230Plan($blockedPublish, $tickets), InvalidArgumentException::class],
    'bad publish flag rejected' => [static fn (): mixed => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next230Plan($badPublishFlag, $tickets), InvalidArgumentException::class],
    'bad receipt row rejected' => [static fn (): mixed => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next230Plan($badReceiptRows, $tickets), InvalidArgumentException::class],
    'bad publish token rejected' => [static fn (): mixed => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next230Plan($badTokenPublish, $tickets), InvalidArgumentException::class],
    'missing publish key rejected' => [static fn (): mixed => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next230Plan($badMissingKey, $tickets), InvalidArgumentException::class],
    'empty tickets rejected' => [static fn (): mixed => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next230Plan($publish, []), InvalidArgumentException::class],
    'bad token reason' => [static fn (): mixed => $plan(null, $badToken)['ticket_rows'][0]['blocked_reasons'], ['reader_source_token_mismatch']],
    'bad epoch reason' => [static fn (): mixed => $plan(null, $badEpoch)['ticket_rows'][0]['blocked_reasons'], ['reader_next_source_epoch_mismatch']],
    'bad epoch guard' => [static fn (): mixed => in_array('all_readers_use_next_source_epoch', $plan(null, $badEpoch)['blocked_guard_names'], true), true],
    'bad frame reason' => [static fn (): mixed => $plan(null, $badFrame)['ticket_rows'][0]['blocked_reasons'], ['reader_checkpoint_frame_mismatch']],
    'bad cookie reason' => [static fn (): mixed => $plan(null, $badCookie)['ticket_rows'][0]['blocked_reasons'], ['reader_checkpoint_cookie_mismatch']],
    'bad schema reason' => [static fn (): mixed => $plan(null, $badSchema)['ticket_rows'][0]['blocked_reasons'], ['reader_schema_cookie_mismatch']],
    'hot journal reason' => [static fn (): mixed => $plan(null, $badHotJournal)['ticket_rows'][0]['blocked_reasons'], ['reader_hot_journal_still_visible']],
    'hot journal guard' => [static fn (): mixed => in_array('all_readers_hide_hot_journal_and_wal_tail', $plan(null, $badHotJournal)['blocked_guard_names'], true), true],
    'wal tail reason' => [static fn (): mixed => $plan(null, $badWalTail)['ticket_rows'][0]['blocked_reasons'], ['reader_wal_tail_still_visible']],
    'bad scope reason' => [static fn (): mixed => $plan(null, $badScope)['ticket_rows'][0]['blocked_reasons'], ['reader_scope_not_published', 'reader_page_number_mismatch', 'reader_page_digest_mismatch']],
    'bad page count reason' => [static fn (): mixed => $plan(null, $badPageCount)['ticket_rows'][0]['blocked_reasons'], ['reader_page_number_mismatch', 'reader_page_digest_mismatch']],
    'bad page number reason' => [static fn (): mixed => $plan(null, $badPageNumber)['ticket_rows'][0]['blocked_reasons'], ['reader_page_number_mismatch', 'reader_page_digest_mismatch']],
    'bad page digest reason' => [static fn (): mixed => $plan(null, $badPageDigest)['ticket_rows'][0]['blocked_reasons'], ['reader_page_digest_mismatch']],
    'duplicate reader names' => [static fn (): mixed => $plan(null, $duplicate)['duplicate_reader_names'], ['wp-theme-reader']],
    'duplicate reader reason' => [static fn (): mixed => in_array('duplicate_reopened_reader_ticket', $plan(null, $duplicate)['blocked_reasons'], true), true],
    'blocked status' => [static fn (): mixed => $plan(null, $badToken)['status'], 'wal-hot-journal-savepoint-checkpoint-current-source-blocked-next230'],
    'blocked reader names' => [static fn (): mixed => $plan(null, $badToken)['blocked_reader_names'], ['wp-options-reader']],
    'blocked guard names' => [static fn (): mixed => $plan(null, $badToken)['blocked_guard_names'], ['all_reader_tickets_admitted']],
    'hold operation' => [static fn (): mixed => in_array('hold_reopened_reader_current_source_next230', $plan(null, $badToken)['operation_names'], true), true],
    'bad ticket reader rejected' => [static fn (): mixed => $plan(null, [$ticket('', 'wp-options-savepoint', [1])]), InvalidArgumentException::class],
    'bad ticket epoch rejected' => [static fn (): mixed => $plan(null, [$ticket('reader', 'wp-options-savepoint', [1], ['source_epoch' => 0])]), InvalidArgumentException::class],
    'bad ticket digest rejected' => [static fn (): mixed => $plan(null, [$ticket('reader', 'wp-options-savepoint', [1], ['visible_page_digests' => [1 => 'bad']])]), InvalidArgumentException::class],
];

foreach ($cases as $name => [$callback, $expected]) {
    $tests['wal hot journal savepoint checkpoint current source next230 ' . $name] = static function (TestRunner $t) use ($callback, $expected): void {
        if (is_string($expected) && class_exists($expected) && is_a($expected, Throwable::class, true)) {
            $t->throws($expected, $callback);
            return;
        }
        $t->same($expected, $callback());
    };
}

return $tests;
