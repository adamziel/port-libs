<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteRollbackJournal;
use PortLibs\LibSqlite\SQLiteRollbackJournalHeader;
use PortLibs\LibSqlite\SQLiteSavepointStack;
use PortLibs\LibSqlite\SQLiteWal;
use PortLibs\LibSqlite\SQLiteWalHeader;
use PortLibs\LibSqlite\SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan;

$tests = [];

$pageSize = 512;
$sectorSize = 512;
$databasePath = '/srv/www/wp-content/database/wp-next183.sqlite';
$journalPath = $databasePath . '-journal';
$walPath = $databasePath . '-wal';
$page = static fn (string $label): string => str_pad($label, $pageSize, '.', STR_PAD_RIGHT);
$dirtyDatabase = $page('next183 dirty schema after plugin import')
    . $page('next183 dirty wp_options root after plugin import')
    . $page('next183 dirty active_plugins after plugin import')
    . $page('next183 dirty rewrite_rules after plugin import');
$cleanPages = [
    1 => $page('next183 clean schema before plugin import'),
    2 => $page('next183 clean wp_options root before plugin import'),
    4 => $page('next183 clean rewrite_rules before plugin import'),
];

$makeJournal = static function (array $pages, int $nonce = 0x18318301) use ($sectorSize, $pageSize): string {
    $header = SQLiteRollbackJournalHeader::MAGIC . pack('N*', count($pages), $nonce, max(array_keys($pages)), $sectorSize, $pageSize);
    $bytes = str_pad($header, $sectorSize, "\0");
    foreach ($pages as $pageNumber => $image) {
        $bytes .= pack('N', $pageNumber) . $image . pack('N', SQLiteRollbackJournal::pageChecksum($image, $nonce));
    }

    return $bytes;
};

$makeWal = static function (array $frames, int $checkpoint = 183) use ($pageSize, $page): string {
    $salt1 = 0x18318301;
    $salt2 = 0x18318302;
    $prefix = pack('N*', SQLiteWalHeader::MAGIC_BIG_ENDIAN, 3007000, $pageSize, $checkpoint, $salt1, $salt2);
    $seed = SQLiteWal::checksumPair($prefix, false);
    $bytes = $prefix . pack('N*', $seed[0], $seed[1]);
    foreach ($frames as [$pageNumber, $commitPageCount, $label]) {
        $image = $page($label);
        $framePrefix = pack('N*', $pageNumber, $commitPageCount, $salt1, $salt2);
        $seed = SQLiteWal::checksumPair(substr($framePrefix, 0, 8) . $image, false, $seed[0], $seed[1]);
        $bytes .= $framePrefix . pack('N*', $seed[0], $seed[1]) . $image;
    }

    return $bytes;
};

$makeStack = static function (): SQLiteSavepointStack {
    $stack = new SQLiteSavepointStack();
    $stack->beginTransaction('wp-import-next183');
    $stack->recordWalFrameWrite(1, 1);
    $stack->recordWalFrameWrite(2, 2, true);
    $stack->savepoint('plugin-batch-next183');
    $stack->recordWalFrameWrite(3, 3);
    $stack->recordWalFrameWrite(4, 4, true);

    return $stack;
};

$journalBytes = $makeJournal($cleanPages);
$walBytes = $makeWal([
    [1, 0, 'next183 retained schema draft before publish'],
    [2, 4, 'next183 retained wp_options commit before publish'],
    [3, 0, 'next183 discarded active_plugins draft'],
    [4, 4, 'next183 discarded rewrite_rules commit'],
]);
$wal = SQLiteWal::parse($walBytes, $pageSize, true);
$currentComplete = [
    'publish_hot_journal_savepoint_current_checkpoint_database_next165',
    'trim_database_after_current_checkpoint_publish_next165',
    'preserve_retained_wal_for_pinned_reader_next165',
    'sync_current_checkpoint_before_reader_release_next165',
];

$base = static fn (array $completed = [], ?array $files = null): array => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::planAtomicPublishApply(
    $databasePath,
    $dirtyDatabase,
    $journalBytes,
    $makeStack(),
    'plugin-batch-next183',
    $wal,
    $walBytes,
    [1, 2, 3, 4],
    $completed,
    $files ?? []
);

$payloadBytesFrom = static function (array $resume) use ($databasePath, $journalPath, $walPath, $journalBytes): array {
    $payloads = $resume['base_plan']['base_plan']['payloads'];
    $databaseKey = $resume['base_plan']['released_database_synced']
        ? $databasePath . '#next165-released-checkpoint'
        : $databasePath . '#next165-current-checkpoint';
    $walKey = $resume['base_plan']['released_database_synced']
        ? $walPath . '#next165-released-reader'
        : $walPath . '#next165-current-reader';

    return [
        $databasePath => (string) $payloads[$databaseKey],
        $journalPath => $journalBytes,
        $walPath => (string) $payloads[$walKey],
    ];
};

$filesFrom = static function (array $completed) use ($base, $databasePath, $journalPath, $walPath, $journalBytes, $payloadBytesFrom): array {
    $probe = $base($completed, []);
    $payloads = $payloadBytesFrom($probe);

    return [
        $databasePath => $payloads[$databasePath],
        $journalPath => $probe['file_rows'][1]['required'] ? $journalBytes : null,
        $walPath => $payloads[$walPath],
    ];
};

$matching = static fn (array $completed = []): array => $base($completed, $filesFrom($completed));
$apply = static function (array $resume, array $files) use ($payloadBytesFrom): array {
    return SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::applyAtomicPublishOperations(
        SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::atomicResumeApplyPlan($resume),
        $files,
        $payloadBytesFrom($resume)
    );
};

$deleteJournalResume = $matching($currentComplete);
$deleteJournalApply = $apply($deleteJournalResume, $filesFrom($currentComplete));
$deleteJournalVerify = static fn (array $tokens = [], int $epoch = 183): array => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next183Verify(
    $deleteJournalApply,
    $deleteJournalApply['files'],
    $tokens,
    $epoch
);
$freshToken = $deleteJournalVerify()['reader_source_token'];
$freshVerify = static fn (): array => $deleteJournalVerify([$freshToken]);
$staleVerify = static fn (): array => $deleteJournalVerify(['wal-hot-journal-savepoint-checkpoint-next183:current:stale']);

$dirtyFiles = $deleteJournalApply['files'];
$dirtyFiles[$databasePath] = substr((string) $dirtyFiles[$databasePath], 0, -1) . '!';
$dirtyVerify = static fn (): array => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next183Verify($deleteJournalApply, $dirtyFiles);

$resurrectedJournalFiles = $deleteJournalApply['files'];
$resurrectedJournalFiles[$journalPath] = $journalBytes;
$resurrectedJournalVerify = static fn (): array => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next183Verify($deleteJournalApply, $resurrectedJournalFiles);

$missingSyncApply = $deleteJournalApply;
$missingSyncApply['durable_paths'] = array_values(array_filter(
    $missingSyncApply['durable_paths'],
    static fn (string $path): bool => $path !== dirname($databasePath)
));
$missingSyncVerify = static fn (): array => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next183Verify($missingSyncApply, $missingSyncApply['files']);

$failedApply = $deleteJournalApply;
$failedApply['status'] = 'wal-hot-journal-savepoint-checkpoint-current-source-failed-next180';
$failedApply['published'] = false;
$failedApply['rolled_back'] = true;
$failedVerify = static fn (): array => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next183Verify($failedApply, $failedApply['files']);

$cases = [
    'status' => [static fn (): mixed => $deleteJournalVerify()['status'], 'wal-hot-journal-savepoint-checkpoint-current-source-next183'],
    'reason' => [static fn (): mixed => $deleteJournalVerify()['reason'], 'post_apply_current_source_verified_for_checkpoint_reader'],
    'database path' => [static fn (): mixed => $deleteJournalVerify()['database_path'], $databasePath],
    'journal path' => [static fn (): mixed => $deleteJournalVerify()['journal_path'], $journalPath],
    'wal path' => [static fn (): mixed => $deleteJournalVerify()['wal_path'], $walPath],
    'epoch' => [static fn (): mixed => $deleteJournalVerify()['reader_epoch'], 183],
    'token prefix' => [static fn (): mixed => str_starts_with($deleteJournalVerify()['reader_source_token'], 'wal-hot-journal-savepoint-checkpoint-next183:current:'), true],
    'token length' => [static fn (): mixed => strlen($deleteJournalVerify()['reader_source_token']), 85],
    'token stable' => [static fn (): mixed => $deleteJournalVerify()['reader_source_token'], $deleteJournalVerify()['reader_source_token']],
    'token changes with epoch' => [static fn (): mixed => $deleteJournalVerify([], 184)['reader_source_token'] !== $deleteJournalVerify([], 183)['reader_source_token'], true],
    'cache token empty' => [static fn (): mixed => $deleteJournalVerify()['reader_cache_tokens'], []],
    'retained token accepted' => [static fn (): mixed => $freshVerify()['retained_reader_cache_tokens'], [$freshToken]],
    'fresh token no reopen' => [static fn (): mixed => $freshVerify()['requires_reader_reopen'], false],
    'stale token blocked' => [static fn (): mixed => $staleVerify()['status'], 'wal-hot-journal-savepoint-checkpoint-current-source-blocked-next183'],
    'stale token reopen' => [static fn (): mixed => $staleVerify()['requires_reader_reopen'], true],
    'stale token reason' => [static fn (): mixed => $staleVerify()['blocked_reasons'], ['reader_cache_token_predates_post_apply_current_source']],
    'stale token retained empty' => [static fn (): mixed => $staleVerify()['retained_reader_cache_tokens'], []],
    'stale token listed' => [static fn (): mixed => $staleVerify()['stale_reader_cache_tokens'], ['wal-hot-journal-savepoint-checkpoint-next183:current:stale']],
    'digest matches apply' => [static fn (): mixed => $deleteJournalVerify()['digest_matches_apply_result'], true],
    'file digest' => [static fn (): mixed => $deleteJournalVerify()['file_digest'], $deleteJournalApply['file_digest_after']],
    'expected digest' => [static fn (): mixed => $deleteJournalVerify()['expected_file_digest'], $deleteJournalApply['file_digest_after']],
    'verified all match' => [static fn (): mixed => $deleteJournalVerify()['verified_all_match'], true],
    'verified roles' => [static fn (): mixed => $deleteJournalVerify()['verified_roles'], ['hot-journal']],
    'verified paths' => [static fn (): mixed => $deleteJournalVerify()['verified_paths'], [$journalPath]],
    'row path' => [static fn (): mixed => $deleteJournalVerify()['verified_rows'][0]['path'], $journalPath],
    'row role' => [static fn (): mixed => $deleteJournalVerify()['verified_rows'][0]['role'], 'hot-journal'],
    'row present false' => [static fn (): mixed => $deleteJournalVerify()['verified_rows'][0]['present'], false],
    'row actual sha null' => [static fn (): mixed => $deleteJournalVerify()['verified_rows'][0]['actual_sha256'], null],
    'row expected sha null' => [static fn (): mixed => $deleteJournalVerify()['verified_rows'][0]['expected_sha256'], null],
    'row actual length null' => [static fn (): mixed => $deleteJournalVerify()['verified_rows'][0]['actual_length'], null],
    'row expected length null' => [static fn (): mixed => $deleteJournalVerify()['verified_rows'][0]['expected_length'], null],
    'row matches' => [static fn (): mixed => $deleteJournalVerify()['verified_rows'][0]['matches'], true],
    'hot journal deleted' => [static fn (): mixed => $deleteJournalVerify()['hot_journal_deleted'], true],
    'directory sync verified' => [static fn (): mixed => $deleteJournalVerify()['directory_sync_verified'], true],
    'durable paths' => [static fn (): mixed => $deleteJournalVerify()['durable_paths'], [dirname($databasePath)]],
    'synced paths' => [static fn (): mixed => $deleteJournalVerify()['synced_paths'], [dirname($databasePath)]],
    'blocked reasons empty' => [static fn (): mixed => $deleteJournalVerify()['blocked_reasons'], []],
    'dependency marker' => [static fn (): mixed => in_array('sqlite-wal-hot-journal-savepoint-checkpoint-current-source-next183', $deleteJournalVerify()['dependencies'], true), true],
    'reader admission marker' => [static fn (): mixed => in_array('sqlite-post-apply-current-source-reader-admission', $deleteJournalVerify()['dependencies'], true), true],
    'inherits next180 marker' => [static fn (): mixed => in_array('sqlite-wal-hot-journal-savepoint-checkpoint-current-source-next180', $deleteJournalVerify()['dependencies'], true), true],
    'dependency closure' => [static fn (): mixed => str_contains($deleteJournalVerify()['dependency_closure'], 'no new support component needed'), true],
    'non overlap' => [static fn (): mixed => str_contains($deleteJournalVerify()['non_overlap'], 'does not repeat next180'), true],
    'dirty files blocked' => [static fn (): mixed => $dirtyVerify()['status'], 'wal-hot-journal-savepoint-checkpoint-current-source-blocked-next183'],
    'dirty file mismatch reason' => [static fn (): mixed => $dirtyVerify()['blocked_reasons'], ['published_file_digest_mismatch_after_restart']],
    'dirty file row match still scoped' => [static fn (): mixed => $dirtyVerify()['verified_all_match'], true],
    'dirty file digest differs' => [static fn (): mixed => $dirtyVerify()['digest_matches_apply_result'], false],
    'resurrected journal blocked' => [static fn (): mixed => $resurrectedJournalVerify()['status'], 'wal-hot-journal-savepoint-checkpoint-current-source-blocked-next183'],
    'resurrected journal reasons' => [static fn (): mixed => $resurrectedJournalVerify()['blocked_reasons'], ['published_file_payload_mismatch_after_restart', 'published_file_digest_mismatch_after_restart', 'hot_journal_reappeared_after_verified_delete']],
    'resurrected journal present' => [static fn (): mixed => $resurrectedJournalVerify()['verified_rows'][0]['present'], true],
    'missing sync blocked' => [static fn (): mixed => $missingSyncVerify()['status'], 'wal-hot-journal-savepoint-checkpoint-current-source-blocked-next183'],
    'missing sync reason' => [static fn (): mixed => $missingSyncVerify()['blocked_reasons'], ['directory_sync_missing_for_post_apply_current_source']],
    'missing sync flag' => [static fn (): mixed => $missingSyncVerify()['directory_sync_verified'], false],
    'failed apply blocked' => [static fn (): mixed => $failedVerify()['status'], 'wal-hot-journal-savepoint-checkpoint-current-source-blocked-next183'],
    'failed apply reason' => [static fn (): mixed => $failedVerify()['blocked_reasons'], ['next180_apply_result_required']],
];

foreach ($cases as $name => [$callback, $expected]) {
    $tests['wal hot journal savepoint checkpoint current source next183 ' . $name] = static function (TestRunner $t) use ($callback, $expected): void {
        $t->same($expected, $callback());
    };
}

$throws = [
    'missing status rejected' => static function () use ($deleteJournalApply): void {
        $apply = $deleteJournalApply;
        unset($apply['status']);
        SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next183Verify($apply, []);
    },
    'missing verified rows rejected' => static function () use ($deleteJournalApply): void {
        $apply = $deleteJournalApply;
        unset($apply['verified_rows']);
        SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next183Verify($apply, $apply['files']);
    },
    'bad file path rejected' => static function () use ($deleteJournalApply): void {
        SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next183Verify($deleteJournalApply, ['' => 'bad']);
    },
    'bad file bytes rejected' => static function () use ($deleteJournalApply, $databasePath): void {
        SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next183Verify($deleteJournalApply, [$databasePath => 42]);
    },
    'zero epoch rejected' => static function () use ($deleteJournalApply): void {
        SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next183Verify($deleteJournalApply, $deleteJournalApply['files'], [], 0);
    },
];

foreach ($throws as $name => $callback) {
    $tests['wal hot journal savepoint checkpoint current source next183 ' . $name] = static function (TestRunner $t) use ($callback): void {
        $t->throws(Throwable::class, $callback);
    };
}

return $tests;
