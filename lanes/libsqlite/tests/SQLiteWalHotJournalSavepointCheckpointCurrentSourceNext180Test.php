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
$databasePath = '/srv/www/wp-content/database/wp-next180.sqlite';
$journalPath = $databasePath . '-journal';
$walPath = $databasePath . '-wal';
$page = static fn (string $label): string => str_pad($label, $pageSize, '.', STR_PAD_RIGHT);
$dirtyDatabase = $page('next180 dirty schema after plugin import')
    . $page('next180 dirty wp_options root after plugin import')
    . $page('next180 dirty active_plugins after plugin import')
    . $page('next180 dirty rewrite_rules after plugin import');
$cleanPages = [
    1 => $page('next180 clean schema before plugin import'),
    2 => $page('next180 clean wp_options root before plugin import'),
    4 => $page('next180 clean rewrite_rules before plugin import'),
];

$makeJournal = static function (array $pages, int $nonce = 0x18018001) use ($sectorSize, $pageSize): string {
    $header = SQLiteRollbackJournalHeader::MAGIC . pack('N*', count($pages), $nonce, max(array_keys($pages)), $sectorSize, $pageSize);
    $bytes = str_pad($header, $sectorSize, "\0");
    foreach ($pages as $pageNumber => $image) {
        $bytes .= pack('N', $pageNumber) . $image . pack('N', SQLiteRollbackJournal::pageChecksum($image, $nonce));
    }

    return $bytes;
};

$makeWal = static function (array $frames, int $checkpoint = 180) use ($pageSize, $page): string {
    $salt1 = 0x18018001;
    $salt2 = 0x18018002;
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
    $stack->beginTransaction('wp-import-next180');
    $stack->recordWalFrameWrite(1, 1);
    $stack->recordWalFrameWrite(2, 2, true);
    $stack->savepoint('plugin-batch-next180');
    $stack->recordWalFrameWrite(3, 3);
    $stack->recordWalFrameWrite(4, 4, true);

    return $stack;
};

$journalBytes = $makeJournal($cleanPages);
$walBytes = $makeWal([
    [1, 0, 'next180 retained schema draft before publish'],
    [2, 4, 'next180 retained wp_options commit before publish'],
    [3, 0, 'next180 discarded active_plugins draft'],
    [4, 4, 'next180 discarded rewrite_rules commit'],
]);
$wal = SQLiteWal::parse($walBytes, $pageSize, true);

$currentComplete = [
    'publish_hot_journal_savepoint_current_checkpoint_database_next165',
    'trim_database_after_current_checkpoint_publish_next165',
    'preserve_retained_wal_for_pinned_reader_next165',
    'sync_current_checkpoint_before_reader_release_next165',
];
$journalDeleted = array_merge($currentComplete, ['delete_hot_journal_after_current_source_checkpoint_next165']);
$releaseStarted = array_merge($journalDeleted, ['publish_released_savepoint_checkpoint_database_next165']);
$allRestart = array_merge($releaseStarted, [
    'restart_wal_after_savepoint_release_next165',
    'sync_released_checkpoint_after_savepoint_publish_next165',
]);
$allTruncate = array_merge($releaseStarted, [
    'truncate_wal_after_savepoint_release_next165',
    'sync_released_checkpoint_after_savepoint_publish_next165',
]);

$base = static fn (array $completed = [], ?array $files = null, string $mode = 'restart', bool $reserved = false): array => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next174Plan(
    $databasePath,
    $dirtyDatabase,
    $journalBytes,
    $makeStack(),
    'plugin-batch-next180',
    $wal,
    $walBytes,
    [1, 2, 3, 4],
    $completed,
    $files ?? [],
    $mode,
    null,
    $reserved
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

$filesFrom = static function (array $completed, string $mode = 'restart') use ($base, $databasePath, $journalPath, $walPath, $journalBytes, $payloadBytesFrom): array {
    $probe = $base($completed, [], $mode);
    $payloads = $payloadBytesFrom($probe);

    return [
        $databasePath => $payloads[$databasePath],
        $journalPath => $probe['file_rows'][1]['required'] ? $journalBytes : null,
        $walPath => $payloads[$walPath],
    ];
};

$matching = static fn (array $completed = [], string $mode = 'restart'): array => $base($completed, $filesFrom($completed, $mode), $mode);
$next177 = static fn (array $resume): array => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next177Plan($resume);
$apply = static fn (array $plan, array $files, array $payloads, ?int $fail = null): array => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next180Apply($plan, $files, $payloads, $fail);

$missingDatabaseResume = $base($currentComplete, [
    $journalPath => $journalBytes,
    $walPath => $filesFrom($currentComplete)[$walPath],
]);
$missingDatabasePlan = $next177($missingDatabaseResume);
$missingDatabasePayloads = $payloadBytesFrom($missingDatabaseResume);
$missingDatabaseFiles = [
    $journalPath => $journalBytes,
    $walPath => $filesFrom($currentComplete)[$walPath],
];
$missingDatabaseApply = static fn (): array => $apply($missingDatabasePlan, $missingDatabaseFiles, $missingDatabasePayloads);

$missingWalResume = $base($currentComplete, [
    $databasePath => $filesFrom($currentComplete)[$databasePath],
    $journalPath => $journalBytes,
]);
$missingWalPlan = $next177($missingWalResume);
$missingWalApply = static fn (): array => $apply($missingWalPlan, [
    $databasePath => $filesFrom($currentComplete)[$databasePath],
    $journalPath => $journalBytes,
], $payloadBytesFrom($missingWalResume));

$missingJournalResume = $base([], [
    $databasePath => $filesFrom([])[$databasePath],
    $journalPath => 'stale-journal',
    $walPath => $filesFrom([])[$walPath],
]);
$missingJournalPlan = $next177($missingJournalResume);
$missingJournalApply = static fn (): array => $apply($missingJournalPlan, [
    $databasePath => $filesFrom([])[$databasePath],
    $journalPath => 'stale-journal',
    $walPath => $filesFrom([])[$walPath],
], $payloadBytesFrom($missingJournalResume));

$deleteJournalResume = $matching($currentComplete);
$deleteJournalPlan = $next177($deleteJournalResume);
$deleteJournalApply = static fn (): array => $apply($deleteJournalPlan, $filesFrom($currentComplete), $payloadBytesFrom($deleteJournalResume));

$releaseReadyResume = $matching($allRestart);
$releaseReadyPlan = $next177($releaseReadyResume);
$releaseReadyApply = static fn (): array => $apply($releaseReadyPlan, $filesFrom($allRestart), $payloadBytesFrom($releaseReadyResume));

$truncateReadyResume = $matching($allTruncate, 'truncate');
$truncateReadyPlan = $next177($truncateReadyResume);
$truncateReadyApply = static fn (): array => $apply($truncateReadyPlan, $filesFrom($allTruncate, 'truncate'), $payloadBytesFrom($truncateReadyResume));

$failure = static fn (): array => $apply($missingDatabasePlan, $missingDatabaseFiles, $missingDatabasePayloads, 1);
$blockedNext177 = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next177Plan($matching(), false);

$cases = [
    'status' => [static fn (): mixed => $missingDatabaseApply()['status'], 'wal-hot-journal-savepoint-checkpoint-current-source-next180'],
    'reason' => [static fn (): mixed => $missingDatabaseApply()['reason'], 'atomic_vfs_apply_published_for_hot_journal_savepoint_checkpoint'],
    'operation names' => [static fn (): mixed => $missingDatabaseApply()['operation_names'], ['write', 'truncate', 'sync', 'sync_directory']],
    'applied operation count' => [static fn (): mixed => $missingDatabaseApply()['applied_operation_count'], 4],
    'staged database path' => [static fn (): mixed => $missingDatabaseApply()['staged_payload_paths'], [$databasePath]],
    'no deleted path on rewrite' => [static fn (): mixed => $missingDatabaseApply()['deleted_paths'], []],
    'sync paths include file and directory' => [static fn (): mixed => $missingDatabaseApply()['synced_paths'], [$databasePath, dirname($databasePath)]],
    'durable paths include file and directory' => [static fn (): mixed => $missingDatabaseApply()['durable_paths'], [$databasePath, dirname($databasePath)]],
    'write bytes match database' => [static fn (): mixed => $missingDatabaseApply()['written_bytes'], strlen($missingDatabasePayloads[$databasePath])],
    'truncate bytes match database' => [static fn (): mixed => $missingDatabaseApply()['truncated_bytes'], strlen($missingDatabasePayloads[$databasePath])],
    'digest changed after publish' => [static fn (): mixed => $missingDatabaseApply()['file_digest_before'] !== $missingDatabaseApply()['file_digest_after'], true],
    'published true' => [static fn (): mixed => $missingDatabaseApply()['published'], true],
    'rolled back false' => [static fn (): mixed => $missingDatabaseApply()['rolled_back'], false],
    'verified all match' => [static fn (): mixed => $missingDatabaseApply()['verified_all_match'], true],
    'verified role database' => [static fn (): mixed => $missingDatabaseApply()['verified_roles'], ['database']],
    'database bytes published' => [static fn (): mixed => $missingDatabaseApply()['files'][$databasePath], $missingDatabasePayloads[$databasePath]],
    'journal retained during database rewrite' => [static fn (): mixed => $missingDatabaseApply()['files'][$journalPath], $journalBytes],
    'wal retained during database rewrite' => [static fn (): mixed => $missingDatabaseApply()['files'][$walPath], $filesFrom($currentComplete)[$walPath]],
    'hot journal not deleted during database rewrite' => [static fn (): mixed => $missingDatabaseApply()['hot_journal_deleted'], false],
    'dependency marker' => [static fn (): mixed => in_array('sqlite-wal-hot-journal-savepoint-checkpoint-current-source-next180', $missingDatabaseApply()['dependencies'], true), true],
    'atomic file map marker' => [static fn (): mixed => in_array('sqlite-vfs-atomic-file-map-publication', $missingDatabaseApply()['dependencies'], true), true],
    'dependency closure' => [static fn (): mixed => str_contains($missingDatabaseApply()['dependency_closure'], 'no new support component needed'), true],
    'non overlap' => [static fn (): mixed => str_contains($missingDatabaseApply()['non_overlap'], 'materializes next177'), true],
    'wal status' => [static fn (): mixed => $missingWalApply()['status'], 'wal-hot-journal-savepoint-checkpoint-current-source-next180'],
    'wal staged path' => [static fn (): mixed => $missingWalApply()['staged_payload_paths'], [$walPath]],
    'wal bytes published' => [static fn (): mixed => $missingWalApply()['files'][$walPath], $payloadBytesFrom($missingWalResume)[$walPath]],
    'wal verified role' => [static fn (): mixed => $missingWalApply()['verified_roles'], ['wal']],
    'journal restore status' => [static fn (): mixed => $missingJournalApply()['status'], 'wal-hot-journal-savepoint-checkpoint-current-source-next180'],
    'journal restore staged path' => [static fn (): mixed => $missingJournalApply()['staged_payload_paths'], [$journalPath]],
    'journal restore bytes' => [static fn (): mixed => $missingJournalApply()['files'][$journalPath], $journalBytes],
    'journal restore verified role' => [static fn (): mixed => $missingJournalApply()['verified_roles'], ['hot-journal']],
    'delete journal status' => [static fn (): mixed => $deleteJournalApply()['status'], 'wal-hot-journal-savepoint-checkpoint-current-source-next180'],
    'delete journal path' => [static fn (): mixed => $deleteJournalApply()['deleted_paths'], [$journalPath]],
    'delete journal file null' => [static fn (): mixed => $deleteJournalApply()['files'][$journalPath], null],
    'delete journal hot flag' => [static fn (): mixed => $deleteJournalApply()['hot_journal_deleted'], true],
    'delete journal verified' => [static fn (): mixed => $deleteJournalApply()['verified_all_match'], true],
    'release ready noop status' => [static fn (): mixed => $releaseReadyApply()['status'], 'wal-hot-journal-savepoint-checkpoint-current-source-next180'],
    'release ready noop reason' => [static fn (): mixed => $releaseReadyApply()['reason'], 'no_vfs_changes_needed_after_next177_verification'],
    'release ready noop count' => [static fn (): mixed => $releaseReadyApply()['applied_operation_count'], 0],
    'release ready digest stable' => [static fn (): mixed => $releaseReadyApply()['file_digest_before'] === $releaseReadyApply()['file_digest_after'], true],
    'truncate ready noop' => [static fn (): mixed => $truncateReadyApply()['reason'], 'no_vfs_changes_needed_after_next177_verification'],
    'failure status' => [static fn (): mixed => $failure()['status'], 'wal-hot-journal-savepoint-checkpoint-current-source-failed-next180'],
    'failure applied one' => [static fn (): mixed => $failure()['applied_operation_count'], 1],
    'failure rolled back' => [static fn (): mixed => $failure()['rolled_back'], true],
    'failure unpublished' => [static fn (): mixed => $failure()['published'], false],
    'failure original files preserved' => [static fn (): mixed => $failure()['files'], $missingDatabaseFiles],
    'failure digest unchanged' => [static fn (): mixed => $failure()['file_digest_before'] === $failure()['file_digest_after'], true],
    'failure blocker' => [static fn (): mixed => $failure()['blocked_reasons'], ['simulated_failure_before_directory_sync']],
    'blocked status' => [static fn (): mixed => $apply($blockedNext177, [], [])['status'], 'wal-hot-journal-savepoint-checkpoint-current-source-blocked-next180'],
    'blocked reason' => [static fn (): mixed => $apply($blockedNext177, [], [])['blocked_reasons'], ['next177_apply_plan_required']],
];

foreach ($cases as $name => [$callback, $expected]) {
    $tests['wal hot journal savepoint checkpoint current source next180 ' . $name] = static function (TestRunner $t) use ($callback, $expected): void {
        $t->same($expected, $callback());
    };
}

$throws = [
    'missing status rejected' => static function () use ($missingDatabasePlan, $missingDatabaseFiles, $missingDatabasePayloads): void {
        $plan = $missingDatabasePlan;
        unset($plan['status']);
        SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next180Apply($plan, $missingDatabaseFiles, $missingDatabasePayloads);
    },
    'missing operations rejected' => static function () use ($missingDatabasePlan, $missingDatabaseFiles, $missingDatabasePayloads): void {
        $plan = $missingDatabasePlan;
        unset($plan['operations']);
        SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next180Apply($plan, $missingDatabaseFiles, $missingDatabasePayloads);
    },
    'bad payload digest rejected' => static function () use ($missingDatabasePlan, $missingDatabaseFiles, $missingDatabasePayloads, $databasePath): void {
        $payloads = $missingDatabasePayloads;
        $payloads[$databasePath] = 'bad';
        SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next180Apply($missingDatabasePlan, $missingDatabaseFiles, $payloads);
    },
    'missing payload rejected' => static function () use ($missingDatabasePlan, $missingDatabaseFiles): void {
        SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next180Apply($missingDatabasePlan, $missingDatabaseFiles, []);
    },
    'bad failure index rejected' => static function () use ($missingDatabasePlan, $missingDatabaseFiles, $missingDatabasePayloads): void {
        SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next180Apply($missingDatabasePlan, $missingDatabaseFiles, $missingDatabasePayloads, 99);
    },
];

foreach ($throws as $name => $callback) {
    $tests['wal hot journal savepoint checkpoint current source next180 ' . $name] = static function (TestRunner $t) use ($callback): void {
        $t->throws(Throwable::class, $callback);
    };
}

return $tests;
