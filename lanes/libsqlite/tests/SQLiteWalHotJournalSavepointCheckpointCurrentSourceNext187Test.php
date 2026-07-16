<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan;

$tests = [];

$databasePath = '/srv/www/wp-content/database/wp-next187.sqlite';
$walPath = $databasePath . '-wal';
$postApply = [
    'status' => 'wal-hot-journal-savepoint-checkpoint-current-source-next183',
    'database_path' => $databasePath,
    'wal_path' => $walPath,
    'reader_source_token' => 'wal-hot-journal-savepoint-checkpoint-next183:current:postapply187',
    'file_digest' => hash('sha256', 'next187-post-apply-files'),
    'verified_all_match' => true,
    'directory_sync_verified' => true,
    'hot_journal_deleted' => true,
    'dependencies' => [
        'sqlite-wal-hot-journal-savepoint-checkpoint-current-source-next183',
        'sqlite-post-apply-current-source-reader-admission',
    ],
];
$reopen = [
    'status' => 'wal-hot-journal-savepoint-checkpoint-current-source-next184',
    'can_reuse_reader_marks' => true,
    'all_reader_pages_separated' => true,
    'salt_pair_rotated' => true,
    'checkpoint_sequence_advanced' => true,
    'source_transition_digest' => hash('sha256', 'next187-transition'),
    'next_wal_sha256' => hash('sha256', 'next187-retry-wal'),
    'reader_page_numbers' => [1, 2, 4, 8],
    'reader_next_sources' => ['checkpoint-database', 'next-wal', 'next-wal', 'checkpoint-database'],
    'dependencies' => [
        'sqlite-wal-hot-journal-savepoint-checkpoint-current-source-next184',
        'sqlite-wal-reader-mark-source-separation-after-reopen',
    ],
];
$expectedRetryToken = 'wal-hot-journal-savepoint-checkpoint-next187:retry:' . substr(hash('sha256', implode('|', [
    $postApply['reader_source_token'],
    $reopen['source_transition_digest'],
    $postApply['file_digest'],
    $reopen['next_wal_sha256'],
    implode(',', array_map('strval', $reopen['reader_page_numbers'])),
])), 0, 32);

$plan = static fn (?array $post = null, ?array $next = null, ?array $tokens = null, bool $hotJournalObserved = true): array =>
    SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next187Plan(
        $post ?? $postApply,
        $next ?? $reopen,
        $tokens ?? [$expectedRetryToken],
        $hotJournalObserved
    );

$withPostToken = [$postApply['reader_source_token']];
$withStaleAndRetry = ['stale-before-next187', $expectedRetryToken];
$noTokens = [];
$badPostStatus = $postApply;
$badPostStatus['status'] = 'wal-hot-journal-savepoint-checkpoint-current-source-blocked-next183';
$badPostDigest = $postApply;
$badPostDigest['verified_all_match'] = false;
$badPostSync = $postApply;
$badPostSync['directory_sync_verified'] = false;
$badPostJournal = $postApply;
$badPostJournal['hot_journal_deleted'] = false;
$badReopenStatus = $reopen;
$badReopenStatus['status'] = 'wal-hot-journal-savepoint-checkpoint-current-source-blocked-next184';
$badReopenMarks = $reopen;
$badReopenMarks['can_reuse_reader_marks'] = false;
$badReopenSeparated = $reopen;
$badReopenSeparated['all_reader_pages_separated'] = false;
$badReopenSalt = $reopen;
$badReopenSalt['salt_pair_rotated'] = false;
$badReopenCheckpoint = $reopen;
$badReopenCheckpoint['checkpoint_sequence_advanced'] = false;
$duplicateRetry = [$expectedRetryToken, $expectedRetryToken];

$cases = [
    'status' => [static fn (): mixed => $plan()['status'], 'wal-hot-journal-savepoint-checkpoint-current-source-next187'],
    'reason' => [static fn (): mixed => $plan()['reason'], 'hot_journal_post_apply_reader_token_retired_before_retry_wal_checkpoint_source'],
    'database path' => [static fn (): mixed => $plan()['database_path'], $databasePath],
    'wal path' => [static fn (): mixed => $plan()['wal_path'], $walPath],
    'post apply token' => [static fn (): mixed => $plan()['post_apply_reader_token'], $postApply['reader_source_token']],
    'retry token' => [static fn (): mixed => $plan()['retry_reader_token'], $expectedRetryToken],
    'reader token retained' => [static fn (): mixed => $plan()['retained_reader_tokens'], [$expectedRetryToken]],
    'stale reader tokens empty' => [static fn (): mixed => $plan()['stale_reader_tokens'], []],
    'post apply token retired' => [static fn (): mixed => $plan()['post_apply_token_retired'], true],
    'requires reader reopen false' => [static fn (): mixed => $plan()['requires_reader_reopen'], false],
    'hot journal observed' => [static fn (): mixed => $plan()['hot_journal_observed'], true],
    'post apply ready' => [static fn (): mixed => $plan()['post_apply_ready'], true],
    'reopen ready' => [static fn (): mixed => $plan()['reopen_ready'], true],
    'can admit retry' => [static fn (): mixed => $plan()['can_admit_retry_checkpoint_source'], true],
    'post digest' => [static fn (): mixed => $plan()['post_apply_file_digest'], $postApply['file_digest']],
    'transition digest' => [static fn (): mixed => $plan()['retry_transition_digest'], $reopen['source_transition_digest']],
    'next wal sha' => [static fn (): mixed => $plan()['next_wal_sha256'], $reopen['next_wal_sha256']],
    'reader pages' => [static fn (): mixed => $plan()['reader_page_numbers'], [1, 2, 4, 8]],
    'reader next sources' => [static fn (): mixed => $plan()['reader_next_sources'], ['checkpoint-database', 'next-wal', 'next-wal', 'checkpoint-database']],
    'blocked reasons empty' => [static fn (): mixed => $plan()['blocked_reasons'], []],
    'token row count' => [static fn (): mixed => count($plan()['token_rows']), 1],
    'token row classification' => [static fn (): mixed => $plan()['token_rows'][0]['classification'], 'retry-current'],
    'token row retained' => [static fn (): mixed => $plan()['token_rows'][0]['retained_for_retry'], true],
    'token row reopen false' => [static fn (): mixed => $plan()['token_rows'][0]['requires_reopen'], false],
    'dependency next183' => [static fn (): mixed => in_array('sqlite-wal-hot-journal-savepoint-checkpoint-current-source-next183', $plan()['dependencies'], true), true],
    'dependency next184' => [static fn (): mixed => in_array('sqlite-wal-hot-journal-savepoint-checkpoint-current-source-next184', $plan()['dependencies'], true), true],
    'dependency next187' => [static fn (): mixed => in_array('sqlite-wal-hot-journal-savepoint-checkpoint-current-source-next187', $plan()['dependencies'], true), true],
    'application dependency' => [static fn (): mixed => in_array('application-wal-import-retry-reader-token-fence', $plan()['dependencies'], true), true],
    'dependency closure' => [static fn (): mixed => str_contains($plan()['dependency_closure'], 'no new support component needed'), true],
    'non overlap' => [static fn (): mixed => str_contains($plan()['non_overlap'], 'does not repeat atomic file-map apply'), true],
    'post token blocks status' => [static fn (): mixed => $plan(null, null, $withPostToken)['status'], 'wal-hot-journal-savepoint-checkpoint-current-source-blocked-next187'],
    'post token blocks reason' => [static fn (): mixed => $plan(null, null, $withPostToken)['blocked_reasons'], [
        'post_apply_reader_token_must_be_retired_before_retry_wal_reuse',
        'stale_reader_tokens_require_reopen_before_retry_wal_reuse',
    ]],
    'post token stale list' => [static fn (): mixed => $plan(null, null, $withPostToken)['stale_reader_tokens'], [$postApply['reader_source_token']]],
    'post token classification' => [static fn (): mixed => $plan(null, null, $withPostToken)['token_rows'][0]['classification'], 'post-apply-current'],
    'stale plus retry status blocked' => [static fn (): mixed => $plan(null, null, $withStaleAndRetry)['status'], 'wal-hot-journal-savepoint-checkpoint-current-source-blocked-next187'],
    'stale plus retry requires reopen' => [static fn (): mixed => $plan(null, null, $withStaleAndRetry)['requires_reader_reopen'], true],
    'stale plus retry stale token' => [static fn (): mixed => $plan(null, null, $withStaleAndRetry)['stale_reader_tokens'], ['stale-before-next187']],
    'stale plus retry reason' => [static fn (): mixed => $plan(null, null, $withStaleAndRetry)['blocked_reasons'], ['stale_reader_tokens_require_reopen_before_retry_wal_reuse']],
    'no tokens still admits' => [static fn (): mixed => $plan(null, null, $noTokens)['can_admit_retry_checkpoint_source'], true],
    'no tokens retained empty' => [static fn (): mixed => $plan(null, null, $noTokens)['retained_reader_tokens'], []],
    'missing hot journal observation' => [static fn (): mixed => $plan(null, null, null, false)['blocked_reasons'], ['hot_journal_recovery_observation_required']],
    'bad post status reason' => [static fn (): mixed => $plan($badPostStatus)['blocked_reasons'], ['next183_post_apply_current_source_not_verified']],
    'bad post digest reason' => [static fn (): mixed => $plan($badPostDigest)['blocked_reasons'], ['next183_post_apply_current_source_not_verified']],
    'bad post sync reason' => [static fn (): mixed => $plan($badPostSync)['blocked_reasons'], ['next183_post_apply_current_source_not_verified']],
    'bad post journal reason' => [static fn (): mixed => $plan($badPostJournal)['blocked_reasons'], ['next183_post_apply_current_source_not_verified']],
    'bad reopen status reason' => [static fn (): mixed => $plan(null, $badReopenStatus)['blocked_reasons'], ['next184_reopened_wal_source_not_publishable']],
    'bad reopen marks reason' => [static fn (): mixed => $plan(null, $badReopenMarks)['blocked_reasons'], ['next184_reopened_wal_source_not_publishable']],
    'bad reopen separated reason' => [static fn (): mixed => $plan(null, $badReopenSeparated)['blocked_reasons'], ['next184_reopened_wal_source_not_publishable']],
    'bad reopen salt reason' => [static fn (): mixed => $plan(null, $badReopenSalt)['blocked_reasons'], ['next184_reopened_wal_source_not_publishable']],
    'bad reopen checkpoint reason' => [static fn (): mixed => $plan(null, $badReopenCheckpoint)['blocked_reasons'], ['next184_reopened_wal_source_not_publishable']],
    'duplicate retry status blocked' => [static fn (): mixed => $plan(null, null, $duplicateRetry)['status'], 'wal-hot-journal-savepoint-checkpoint-current-source-blocked-next187'],
    'duplicate retry reason' => [static fn (): mixed => $plan(null, null, $duplicateRetry)['blocked_reasons'], ['duplicate_retry_reader_tokens']],
    'duplicate retry retained count' => [static fn (): mixed => count($plan(null, null, $duplicateRetry)['retained_reader_tokens']), 2],
    'combined block reasons' => [static fn (): mixed => $plan($badPostStatus, $badReopenStatus, $withPostToken, false)['blocked_reasons'], [
        'hot_journal_recovery_observation_required',
        'next183_post_apply_current_source_not_verified',
        'next184_reopened_wal_source_not_publishable',
        'post_apply_reader_token_must_be_retired_before_retry_wal_reuse',
        'stale_reader_tokens_require_reopen_before_retry_wal_reuse',
    ]],
];

foreach ($cases as $name => [$callback, $expected]) {
    $tests['wal hot journal savepoint checkpoint current source next187 ' . $name] = static function (TestRunner $t) use ($callback, $expected): void {
        $t->same($expected, $callback());
    };
}

$throws = [
    'missing post key rejected' => static function () use ($plan, $postApply): void {
        $bad = $postApply;
        unset($bad['file_digest']);
        $plan($bad);
    },
    'missing reopen key rejected' => static function () use ($plan, $reopen): void {
        $bad = $reopen;
        unset($bad['next_wal_sha256']);
        $plan(null, $bad);
    },
    'empty token rejected' => static fn () => $plan(null, null, ['']),
    'non string token rejected' => static fn () => $plan(null, null, [123]),
    'bad reader pages rejected' => static function () use ($plan, $reopen): void {
        $bad = $reopen;
        $bad['reader_page_numbers'] = 'bad';
        $plan(null, $bad);
    },
    'bad reader sources rejected' => static function () use ($plan, $reopen): void {
        $bad = $reopen;
        $bad['reader_next_sources'] = 'bad';
        $plan(null, $bad);
    },
];

foreach ($throws as $name => $callback) {
    $tests['wal hot journal savepoint checkpoint current source next187 ' . $name] = static function (TestRunner $t) use ($callback): void {
        $t->throws(Throwable::class, $callback);
    };
}

return $tests;
