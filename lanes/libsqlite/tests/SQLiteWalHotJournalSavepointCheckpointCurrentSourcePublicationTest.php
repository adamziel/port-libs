<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan;

$tests = [];

$admission = [
    'status' => 'wal-hot-journal-savepoint-checkpoint-current-source-next200',
    'reason' => 'sealed_reader_generation_has_hot_journal_savepoint_checkpoint_durability_receipts',
    'database_path' => '/srv/www/wp-content/database/wp-next201.sqlite',
    'wal_path' => '/srv/www/wp-content/database/wp-next201.sqlite-wal',
    'journal_path' => '/srv/www/wp-content/database/wp-next201.sqlite-journal',
    'publication_token' => 'wal-hot-journal-savepoint-checkpoint-next200:publish:next201',
    'previous_reader_epoch' => 200,
    'sealed_reader_epoch' => 204,
    'receipt_count' => 4,
    'receipt_ticket_ids' => ['wp-reader-next201-a', 'wp-reader-next201-b', 'wp-reader-next201-c', 'wp-reader-next201-d'],
    'receipt_pages' => [1, 2, 3, 7],
    'expected_savepoint_generation' => 77,
    'can_admit_durable_readers' => true,
    'blocked_reasons' => [],
    'dependencies' => [
        'sqlite-wal-hot-journal-savepoint-checkpoint-current-source-next200',
        'sqlite-hot-journal-savepoint-checkpoint-durable-reader-admission',
        'application-import-retry-checkpoint-durable-reader-admission',
    ],
];
$checkpointDigest = hash('sha256', 'next201 checkpoint database bytes after hot journal recovery');
$walDigest = hash('sha256', 'next201 wal retry bytes after savepoint release');
$sourceRow = static fn (array $overrides = []): array => array_merge([
    'ticket_id' => 'wp-reader-next201-a',
    'page_number' => 1,
    'reader_epoch' => 201,
    'publication_token' => $admission['publication_token'],
    'source' => 'checkpoint-database',
    'source_digest' => $checkpointDigest,
    'cache_epoch' => 201,
    'savepoint_generation' => 77,
    'checkpoint_visible' => true,
    'reader_cache_rebased' => true,
], $overrides);
$rows = [
    $sourceRow(),
    $sourceRow([
        'ticket_id' => 'wp-reader-next201-b',
        'page_number' => 2,
        'reader_epoch' => 202,
        'source' => 'next-wal',
        'source_digest' => $walDigest,
        'cache_epoch' => 202,
    ]),
    $sourceRow([
        'ticket_id' => 'wp-reader-next201-c',
        'page_number' => 3,
        'reader_epoch' => 203,
        'source' => 'next-wal',
        'source_digest' => $walDigest,
        'cache_epoch' => 204,
    ]),
    $sourceRow([
        'ticket_id' => 'wp-reader-next201-d',
        'page_number' => 7,
        'reader_epoch' => 204,
        'source' => 'checkpoint-database',
        'source_digest' => $checkpointDigest,
        'cache_epoch' => 204,
    ]),
];
$plan = static fn (
    ?array $input = null,
    ?array $sourceRows = null,
    ?string $expectedCheckpoint = null,
    ?string $expectedWal = null,
    ?string $hotJournal = null,
): array => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::publishDurableReaderCurrentSources(
    $input ?? $admission,
    $sourceRows ?? $rows,
    $expectedCheckpoint ?? $checkpointDigest,
    $expectedWal ?? $walDigest,
    $hotJournal
);

$blockedAdmission = $admission;
$blockedAdmission['status'] = 'wal-hot-journal-savepoint-checkpoint-current-source-blocked-next200';
$notAdmitted = $admission;
$notAdmitted['can_admit_durable_readers'] = false;
$duplicateRows = [$sourceRow(), $sourceRow(['page_number' => 2])];
$missingRows = array_slice($rows, 0, 3);
$unknownTicket = [$sourceRow(['ticket_id' => 'wp-reader-next201-x'])];
$unknownPage = [$sourceRow(['page_number' => 99])];
$lowEpoch = [$sourceRow(['reader_epoch' => 200])];
$highEpoch = [$sourceRow(['reader_epoch' => 205])];
$badPublication = [$sourceRow(['publication_token' => 'wrong-publication'])];
$badSource = [$sourceRow(['source' => 'rollback-journal'])];
$badCheckpointDigest = [$sourceRow(['source_digest' => hash('sha256', 'stale checkpoint')])];
$badWalDigest = [$sourceRow(['source' => 'next-wal', 'source_digest' => hash('sha256', 'stale wal')])];
$staleCache = [$sourceRow(['reader_epoch' => 202, 'cache_epoch' => 201])];
$badSavepoint = [$sourceRow(['savepoint_generation' => 76])];
$notVisible = [$sourceRow(['checkpoint_visible' => false])];
$notRebased = [$sourceRow(['reader_cache_rebased' => false])];
$combinedBad = [$sourceRow([
    'ticket_id' => 'wp-reader-next201-x',
    'page_number' => 99,
    'reader_epoch' => 200,
    'publication_token' => 'wrong-publication',
    'source' => 'rollback-journal',
    'source_digest' => hash('sha256', 'bad source'),
    'cache_epoch' => 199,
    'savepoint_generation' => 76,
    'checkpoint_visible' => false,
    'reader_cache_rebased' => false,
])];

$cases = [
    'status' => [static fn (): mixed => $plan()['status'], 'wal-hot-journal-savepoint-checkpoint-current-source-next201'],
    'reason' => [static fn (): mixed => $plan()['reason'], 'durable_reader_current_sources_rebased_after_hot_journal_savepoint_checkpoint'],
    'database path' => [static fn (): mixed => $plan()['database_path'], $admission['database_path']],
    'wal path' => [static fn (): mixed => $plan()['wal_path'], $admission['wal_path']],
    'journal path' => [static fn (): mixed => $plan()['journal_path'], $admission['journal_path']],
    'publication token' => [static fn (): mixed => $plan()['publication_token'], $admission['publication_token']],
    'previous epoch' => [static fn (): mixed => $plan()['previous_reader_epoch'], 200],
    'sealed epoch' => [static fn (): mixed => $plan()['sealed_reader_epoch'], 204],
    'source count' => [static fn (): mixed => $plan()['source_count'], 4],
    'source tickets' => [static fn (): mixed => $plan()['source_ticket_ids'], ['wp-reader-next201-a', 'wp-reader-next201-b', 'wp-reader-next201-c', 'wp-reader-next201-d']],
    'source pages' => [static fn (): mixed => $plan()['source_pages'], [1, 2, 3, 7]],
    'source kinds' => [static fn (): mixed => $plan()['source_kinds'], ['checkpoint-database', 'next-wal']],
    'checkpoint source count' => [static fn (): mixed => $plan()['checkpoint_source_count'], 2],
    'wal source count' => [static fn (): mixed => $plan()['wal_source_count'], 2],
    'expected checkpoint digest' => [static fn (): mixed => $plan()['expected_checkpoint_database_digest'], $checkpointDigest],
    'expected wal digest' => [static fn (): mixed => $plan()['expected_wal_digest'], $walDigest],
    'hot journal absent' => [static fn (): mixed => $plan()['hot_journal_absent'], true],
    'can publish' => [static fn (): mixed => $plan()['can_publish_current_sources'], true],
    'blocked empty' => [static fn (): mixed => $plan()['blocked_reasons'], []],
    'publication digest length' => [static fn (): mixed => strlen($plan()['publication_digest']), 64],
    'first row checkpoint digest matches' => [static fn (): mixed => $plan()['published_rows'][0]['source_digest_matches'], true],
    'second row wal digest matches' => [static fn (): mixed => $plan()['published_rows'][1]['source_digest_matches'], true],
    'third row cache epoch' => [static fn (): mixed => $plan()['published_rows'][2]['cache_epoch'], 204],
    'fourth row source' => [static fn (): mixed => $plan()['published_rows'][3]['source'], 'checkpoint-database'],
    'row blocked empty' => [static fn (): mixed => $plan()['published_rows'][0]['blocked_reasons'], []],
    'dependency next200' => [static fn (): mixed => in_array('sqlite-wal-hot-journal-savepoint-checkpoint-current-source-next200', $plan()['dependencies'], true), true],
    'dependency next201' => [static fn (): mixed => in_array('sqlite-wal-hot-journal-savepoint-checkpoint-current-source-next201', $plan()['dependencies'], true), true],
    'application dependency' => [static fn (): mixed => in_array('application-import-retry-current-source-reader-cache-rebase', $plan()['dependencies'], true), true],
    'dependency closure' => [static fn (): mixed => str_contains($plan()['dependency_closure'], 'no new support component needed'), true],
    'non overlap' => [static fn (): mixed => str_contains($plan()['non_overlap'], 'does not repeat WAL byte truncation'), true],
    'blocked admission status' => [static fn (): mixed => $plan($blockedAdmission)['blocked_reasons'], ['next200_durable_reader_admission_required']],
    'blocked admission can admit' => [static fn (): mixed => $plan($notAdmitted)['blocked_reasons'], ['next200_durable_reader_admission_not_publishable']],
    'hot journal present blocks' => [static fn (): mixed => $plan(null, null, null, null, 'leftover-hot-journal')['blocked_reasons'], ['hot_journal_still_present_after_current_source_publication']],
    'duplicate blocks' => [static fn (): mixed => $plan(null, $duplicateRows)['blocked_reasons'], ['duplicate_current_source_ticket', 'missing_current_source_for_durable_reader_ticket']],
    'missing ticket blocks' => [static fn (): mixed => $plan(null, $missingRows)['blocked_reasons'], ['missing_current_source_for_durable_reader_ticket']],
    'unknown ticket blocks' => [static fn (): mixed => $plan(null, $unknownTicket)['blocked_reasons'], ['current_source_ticket_not_durably_admitted', 'missing_current_source_for_durable_reader_ticket']],
    'unknown page blocks' => [static fn (): mixed => $plan(null, $unknownPage)['blocked_reasons'], ['current_source_page_not_durably_admitted', 'missing_current_source_for_durable_reader_ticket']],
    'low epoch blocks' => [static fn (): mixed => $plan(null, $lowEpoch)['blocked_reasons'], ['current_source_epoch_outside_sealed_generation', 'missing_current_source_for_durable_reader_ticket']],
    'high epoch blocks' => [static fn (): mixed => $plan(null, $highEpoch)['blocked_reasons'], ['current_source_epoch_outside_sealed_generation', 'current_source_cache_epoch_stale', 'missing_current_source_for_durable_reader_ticket']],
    'bad publication blocks' => [static fn (): mixed => $plan(null, $badPublication)['blocked_reasons'], ['current_source_publication_token_mismatch', 'missing_current_source_for_durable_reader_ticket']],
    'bad source blocks' => [static fn (): mixed => $plan(null, $badSource)['blocked_reasons'], ['current_source_kind_unknown', 'current_source_digest_mismatch', 'missing_current_source_for_durable_reader_ticket']],
    'bad checkpoint digest blocks' => [static fn (): mixed => $plan(null, $badCheckpointDigest)['blocked_reasons'], ['current_source_digest_mismatch', 'missing_current_source_for_durable_reader_ticket']],
    'bad wal digest blocks' => [static fn (): mixed => $plan(null, $badWalDigest)['blocked_reasons'], ['current_source_digest_mismatch', 'missing_current_source_for_durable_reader_ticket']],
    'stale cache blocks' => [static fn (): mixed => $plan(null, $staleCache)['blocked_reasons'], ['current_source_cache_epoch_stale', 'missing_current_source_for_durable_reader_ticket']],
    'bad savepoint blocks' => [static fn (): mixed => $plan(null, $badSavepoint)['blocked_reasons'], ['current_source_savepoint_generation_mismatch', 'missing_current_source_for_durable_reader_ticket']],
    'not visible blocks' => [static fn (): mixed => $plan(null, $notVisible)['blocked_reasons'], ['current_source_checkpoint_not_visible', 'missing_current_source_for_durable_reader_ticket']],
    'not rebased blocks' => [static fn (): mixed => $plan(null, $notRebased)['blocked_reasons'], ['current_source_reader_cache_not_rebased', 'missing_current_source_for_durable_reader_ticket']],
    'combined blocks' => [static fn (): mixed => $plan(null, $combinedBad)['blocked_reasons'], [
        'current_source_ticket_not_durably_admitted',
        'current_source_page_not_durably_admitted',
        'current_source_epoch_outside_sealed_generation',
        'current_source_publication_token_mismatch',
        'current_source_kind_unknown',
        'current_source_digest_mismatch',
        'current_source_cache_epoch_stale',
        'current_source_savepoint_generation_mismatch',
        'current_source_checkpoint_not_visible',
        'current_source_reader_cache_not_rebased',
        'missing_current_source_for_durable_reader_ticket',
    ]],
    'blocked status' => [static fn (): mixed => $plan(null, $badWalDigest)['status'], 'wal-hot-journal-savepoint-checkpoint-current-source-blocked-next201'],
    'blocked reason' => [static fn (): mixed => $plan(null, $badWalDigest)['reason'], 'durable_reader_current_sources_wait_for_rebased_checkpoint_visibility'],
    'blocked cannot publish' => [static fn (): mixed => $plan(null, $badWalDigest)['can_publish_current_sources'], false],
    'blocked row reason' => [static fn (): mixed => $plan(null, $badWalDigest)['published_rows'][0]['blocked_reasons'], ['current_source_digest_mismatch']],
];

foreach ($cases as $name => [$callback, $expected]) {
    $tests['wal hot journal savepoint checkpoint current source next201 ' . $name] = static function (TestRunner $t) use ($callback, $expected): void {
        $t->same($expected, $callback());
    };
}

$throws = [
    'missing admission key rejected' => static function () use ($plan, $admission): void {
        $bad = $admission;
        unset($bad['publication_token']);
        $plan($bad);
    },
    'empty admission path rejected' => static function () use ($plan, $admission): void {
        $bad = $admission;
        $bad['database_path'] = '';
        $plan($bad);
    },
    'bad admission epoch rejected' => static function () use ($plan, $admission): void {
        $bad = $admission;
        $bad['sealed_reader_epoch'] = '204';
        $plan($bad);
    },
    'bad admission ticket rejected' => static function () use ($plan, $admission): void {
        $bad = $admission;
        $bad['receipt_ticket_ids'] = [''];
        $plan($bad);
    },
    'bad admission page rejected' => static function () use ($plan, $admission): void {
        $bad = $admission;
        $bad['receipt_pages'] = [0];
        $plan($bad);
    },
    'empty source rows rejected' => static fn () => $plan(null, []),
    'empty checkpoint digest rejected' => static fn () => $plan(null, null, ''),
    'empty wal digest rejected' => static fn () => $plan(null, null, null, ''),
    'non array row rejected' => static fn () => $plan(null, ['bad']),
    'empty ticket rejected' => static fn () => $plan(null, [$sourceRow(['ticket_id' => ''])]),
    'bad page rejected' => static fn () => $plan(null, [$sourceRow(['page_number' => '1'])]),
    'empty source rejected' => static fn () => $plan(null, [$sourceRow(['source' => ''])]),
    'bad digest rejected' => static fn () => $plan(null, [$sourceRow(['source_digest' => []])]),
];

foreach ($throws as $name => $callback) {
    $tests['wal hot journal savepoint checkpoint current source next201 ' . $name] = static function (TestRunner $t) use ($callback): void {
        $t->throws(Throwable::class, $callback);
    };
}

return $tests;
