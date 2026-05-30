<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan;

require_once __DIR__ . '/../src/SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan.php';

$tests = [];

$digest = static fn (string $value): string => hash('sha256', $value);
$token = ['id' => 'hot-journal-checkpoint-source:217', 'epoch' => 217];
$reader = static function (string $name, int $page, string $action, bool $admitted = true) use ($digest): array {
    return [
        'reader' => $name,
        'page' => $page,
        'expected_action' => $action,
        'acknowledged_image_sha256' => $digest($name . ':ack'),
        'expected_image_sha256' => $digest($name . ':expected'),
        'observed_image_sha256' => $digest($name . ':ack'),
        'checkpoint_admitted' => $admitted,
    ];
};
$receipt = static function (string $name, string $action, array $overrides = []) use ($digest, $token): array {
    return array_merge([
        'source_id' => $token['id'],
        'epoch' => $token['epoch'],
        'checkpoint_frame' => 31,
        'checkpoint_cookie' => 4127,
        'schema_cookie' => 902,
        'image_sha256' => $digest($name . ':ack'),
        'acknowledged' => $action === 'retain-reader-cache',
        'reopen_fenced' => $action === 'reopen-reader-cache',
        'reopen_fence_token' => 'reopen:' . $name . ':' . $token['id'] . ':31',
        'journal_deleted' => true,
        'wal_synced' => true,
        'directory_synced' => true,
    ], $overrides);
};

$admission = [
    'status' => 'wal-hot-journal-savepoint-checkpoint-current-source-next211',
    'database_path' => '/srv/www/wp-content/database/wp-next217.sqlite',
    'wal_path' => '/srv/www/wp-content/database/wp-next217.sqlite-wal',
    'journal_path' => '/srv/www/wp-content/database/wp-next217.sqlite-journal',
    'current_source_token' => $token,
    'checkpoint_frame' => 31,
    'checkpoint_cookie' => 4127,
    'schema_cookie' => 902,
    'checkpoint_admitted' => true,
    'reader_admission_rows' => [
        $reader('wp-options-current', 2, 'retain-reader-cache'),
        $reader('cron-current', 5, 'retain-reader-cache'),
        $reader('old-plugin-reader', 2, 'reopen-reader-cache', false),
        $reader('stale-theme-reader', 7, 'reopen-reader-cache', false),
    ],
    'operation_names' => ['acknowledge_reader_page_digest_next211'],
    'dependencies' => ['sqlite-wal-hot-journal-savepoint-checkpoint-current-source-next211'],
];
$receipts = [
    'wp-options-current' => $receipt('wp-options-current', 'retain-reader-cache'),
    'cron-current' => $receipt('cron-current', 'retain-reader-cache'),
    'old-plugin-reader' => $receipt('old-plugin-reader', 'reopen-reader-cache'),
    'stale-theme-reader' => $receipt('stale-theme-reader', 'reopen-reader-cache'),
];
$plan = static fn (?array $admissionInput = null, ?array $receiptInput = null): array => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next217Plan($admissionInput ?? $admission, $receiptInput ?? $receipts);

$missingAck = $receipts;
unset($missingAck['cron-current']);
$badDigest = $receipts;
$badDigest['wp-options-current']['image_sha256'] = $digest('old-page');
$badSource = $receipts;
$badSource['wp-options-current']['source_id'] = 'older-source';
$badEpoch = $receipts;
$badEpoch['wp-options-current']['epoch'] = 216;
$badFrame = $receipts;
$badFrame['wp-options-current']['checkpoint_frame'] = 30;
$badCookie = $receipts;
$badCookie['wp-options-current']['checkpoint_cookie'] = 1;
$badSchema = $receipts;
$badSchema['wp-options-current']['schema_cookie'] = 1;
$badFence = $receipts;
$badFence['old-plugin-reader']['reopen_fence_token'] = 'reopen:old-plugin-reader:older:31';
$missingFence = $receipts;
$missingFence['old-plugin-reader']['reopen_fenced'] = false;
$journalUndurable = $receipts;
$journalUndurable['cron-current']['journal_deleted'] = false;
$walUndurable = $receipts;
$walUndurable['cron-current']['wal_synced'] = false;
$directoryUndurable = $receipts;
$directoryUndurable['cron-current']['directory_synced'] = false;
$orphan = $receipts;
$orphan['orphan-reader'] = $receipt('orphan-reader', 'retain-reader-cache');
$blockedAdmission = $admission;
$blockedAdmission['status'] = 'wal-hot-journal-savepoint-checkpoint-current-source-blocked-next211';
$notAdmittedAdmission = $admission;
$notAdmittedAdmission['checkpoint_admitted'] = false;
$readerNotAdmitted = $admission;
$readerNotAdmitted['reader_admission_rows'][0]['checkpoint_admitted'] = false;

$cases = [
    'status' => [static fn (): mixed => $plan()['status'], 'wal-hot-journal-savepoint-checkpoint-current-source-next217'],
    'reason' => [static fn (): mixed => $plan()['reason'], 'durable_reader_receipts_admit_checkpoint_next_source'],
    'database path' => [static fn (): mixed => $plan()['database_path'], '/srv/www/wp-content/database/wp-next217.sqlite'],
    'wal path' => [static fn (): mixed => $plan()['wal_path'], '/srv/www/wp-content/database/wp-next217.sqlite-wal'],
    'journal path' => [static fn (): mixed => $plan()['journal_path'], '/srv/www/wp-content/database/wp-next217.sqlite-journal'],
    'token id' => [static fn (): mixed => $plan()['current_source_token']['id'], 'hot-journal-checkpoint-source:217'],
    'token epoch' => [static fn (): mixed => $plan()['current_source_token']['epoch'], 217],
    'checkpoint frame' => [static fn (): mixed => $plan()['checkpoint_frame'], 31],
    'checkpoint cookie' => [static fn (): mixed => $plan()['checkpoint_cookie'], 4127],
    'schema cookie' => [static fn (): mixed => $plan()['schema_cookie'], 902],
    'next source epoch' => [static fn (): mixed => $plan()['next_source_epoch'], 218],
    'retained readers' => [static fn (): mixed => $plan()['retained_reader_names'], ['wp-options-current', 'cron-current']],
    'reopened readers' => [static fn (): mixed => $plan()['reopened_reader_names'], ['old-plugin-reader', 'stale-theme-reader']],
    'admitted readers' => [static fn (): mixed => $plan()['admitted_reader_names'], ['wp-options-current', 'cron-current', 'old-plugin-reader', 'stale-theme-reader']],
    'blocked readers empty' => [static fn (): mixed => $plan()['blocked_reader_names'], []],
    'orphan receipts empty' => [static fn (): mixed => $plan()['orphan_receipts'], []],
    'guard count' => [static fn (): mixed => count($plan()['guard_names']), 8],
    'guard matches' => [static fn (): mixed => $plan()['guard_matches'], array_fill(0, 8, true)],
    'blocked guards empty' => [static fn (): mixed => $plan()['blocked_guard_names'], []],
    'checkpoint admitted' => [static fn (): mixed => $plan()['checkpoint_admitted'], true],
    'digest length' => [static fn (): mixed => strlen($plan()['receipt_digest']), 64],
    'first row reader' => [static fn (): mixed => $plan()['reader_receipt_rows'][0]['reader'], 'wp-options-current'],
    'first row page' => [static fn (): mixed => $plan()['reader_receipt_rows'][0]['page'], 2],
    'first row action' => [static fn (): mixed => $plan()['reader_receipt_rows'][0]['expected_action'], 'retain-reader-cache'],
    'first row ack matches' => [static fn (): mixed => $plan()['reader_receipt_rows'][0]['ack_receipt_matches'], true],
    'first row reopen false' => [static fn (): mixed => $plan()['reader_receipt_rows'][0]['reopen_receipt_matches'], false],
    'first row journal durable' => [static fn (): mixed => $plan()['reader_receipt_rows'][0]['journal_delete_durable'], true],
    'first row wal durable' => [static fn (): mixed => $plan()['reader_receipt_rows'][0]['wal_sync_durable'], true],
    'first row directory durable' => [static fn (): mixed => $plan()['reader_receipt_rows'][0]['directory_sync_durable'], true],
    'first row admitted' => [static fn (): mixed => $plan()['reader_receipt_rows'][0]['admitted'], true],
    'first row transition' => [static fn (): mixed => $plan()['reader_receipt_rows'][0]['transition'], 'wp-options-current>admit-next-source:next217'],
    'reopen row ack false' => [static fn (): mixed => $plan()['reader_receipt_rows'][2]['ack_receipt_matches'], false],
    'reopen row fence true' => [static fn (): mixed => $plan()['reader_receipt_rows'][2]['reopen_receipt_matches'], true],
    'reopen row token' => [static fn (): mixed => $plan()['reader_receipt_rows'][2]['reopen_fence_token'], 'reopen:old-plugin-reader:hot-journal-checkpoint-source:217:31'],
    'operation next211 carried' => [static fn (): mixed => in_array('acknowledge_reader_page_digest_next211', $plan()['operation_names'], true), true],
    'operation next217 ack' => [static fn (): mixed => in_array('verify_durable_reader_ack_receipts_next217', $plan()['operation_names'], true), true],
    'operation next217 fence' => [static fn (): mixed => in_array('verify_reopen_fence_receipts_next217', $plan()['operation_names'], true), true],
    'dependency next211 carried' => [static fn (): mixed => in_array('sqlite-wal-hot-journal-savepoint-checkpoint-current-source-next211', $plan()['dependencies'], true), true],
    'dependency next217' => [static fn (): mixed => in_array('sqlite-wal-hot-journal-savepoint-checkpoint-current-source-next217', $plan()['dependencies'], true), true],
    'dependency application' => [static fn (): mixed => in_array('application-import-hot-journal-checkpoint-reopen-receipts', $plan()['dependencies'], true), true],
    'dependency closure' => [static fn (): mixed => str_contains($plan()['dependency_closure'], 'no new support component needed'), true],
    'non overlap' => [static fn (): mixed => str_contains($plan()['non_overlap'], 'does not repeat next211'), true],
    'missing ack status' => [static fn (): mixed => $plan(null, $missingAck)['status'], 'wal-hot-journal-savepoint-checkpoint-current-source-blocked-next217'],
    'missing ack reader' => [static fn (): mixed => $plan(null, $missingAck)['blocked_reader_names'], ['cron-current']],
    'missing ack guard' => [static fn (): mixed => in_array('retained_readers_acknowledged', $plan(null, $missingAck)['blocked_guard_names'], true), true],
    'bad digest reader' => [static fn (): mixed => $plan(null, $badDigest)['blocked_reader_names'], ['wp-options-current']],
    'bad source reader' => [static fn (): mixed => $plan(null, $badSource)['reader_receipt_rows'][0]['source_id'], 'older-source'],
    'bad source blocked' => [static fn (): mixed => $plan(null, $badSource)['blocked_reader_names'], ['wp-options-current']],
    'bad epoch blocked' => [static fn (): mixed => $plan(null, $badEpoch)['blocked_reader_names'], ['wp-options-current']],
    'bad frame blocked' => [static fn (): mixed => $plan(null, $badFrame)['blocked_reader_names'], ['wp-options-current']],
    'bad cookie blocked' => [static fn (): mixed => $plan(null, $badCookie)['blocked_reader_names'], ['wp-options-current']],
    'bad schema blocked' => [static fn (): mixed => $plan(null, $badSchema)['blocked_reader_names'], ['wp-options-current']],
    'bad fence blocked' => [static fn (): mixed => $plan(null, $badFence)['blocked_reader_names'], ['old-plugin-reader']],
    'missing fence guard' => [static fn (): mixed => in_array('reopened_readers_fenced', $plan(null, $missingFence)['blocked_guard_names'], true), true],
    'journal durable guard' => [static fn (): mixed => in_array('hot_journal_delete_durable', $plan(null, $journalUndurable)['blocked_guard_names'], true), true],
    'wal durable guard' => [static fn (): mixed => in_array('wal_generation_synced', $plan(null, $walUndurable)['blocked_guard_names'], true), true],
    'directory durable guard' => [static fn (): mixed => in_array('directory_entry_synced', $plan(null, $directoryUndurable)['blocked_guard_names'], true), true],
    'orphan guard' => [static fn (): mixed => in_array('no_orphan_receipts', $plan(null, $orphan)['blocked_guard_names'], true), true],
    'orphan listed' => [static fn (): mixed => $plan(null, $orphan)['orphan_receipts'], ['orphan-reader']],
    'blocked admission guard' => [static fn (): mixed => in_array('next211_checkpoint_admitted', $plan($blockedAdmission)['blocked_guard_names'], true), true],
    'not admitted guard' => [static fn (): mixed => in_array('next211_checkpoint_admitted', $plan($notAdmittedAdmission)['blocked_guard_names'], true), true],
    'reader not admitted blocked' => [static fn (): mixed => $plan($readerNotAdmitted)['blocked_reader_names'], ['wp-options-current']],
];

foreach ($cases as $name => [$callback, $expected]) {
    $tests['wal hot journal savepoint checkpoint current source next217 ' . $name] = static function (TestRunner $t) use ($callback, $expected): void {
        $t->same($expected, $callback());
    };
}

$throws = [
    'missing admission key rejected' => static function () use ($plan, $admission): void {
        $bad = $admission;
        unset($bad['wal_path']);
        $plan($bad);
    },
    'empty receipts rejected' => static fn () => $plan(null, []),
    'empty token rejected' => static function () use ($plan, $admission): void {
        $bad = $admission;
        $bad['current_source_token']['id'] = '';
        $plan($bad);
    },
    'bad checkpoint frame rejected' => static function () use ($plan, $admission): void {
        $bad = $admission;
        $bad['checkpoint_frame'] = 0;
        $plan($bad);
    },
    'bad reader action rejected' => static function () use ($plan, $admission): void {
        $bad = $admission;
        $bad['reader_admission_rows'][0]['expected_action'] = 'reuse';
        $plan($bad);
    },
    'bad reader page rejected' => static function () use ($plan, $admission): void {
        $bad = $admission;
        $bad['reader_admission_rows'][0]['page'] = 0;
        $plan($bad);
    },
];

foreach ($throws as $name => $callback) {
    $tests['wal hot journal savepoint checkpoint current source next217 ' . $name] = static function (TestRunner $t) use ($callback): void {
        $t->throws(Throwable::class, $callback);
    };
}

return $tests;
