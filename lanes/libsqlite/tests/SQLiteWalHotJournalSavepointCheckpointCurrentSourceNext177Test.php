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
$databasePath = '/srv/www/wp-content/database/wp-next177.sqlite';
$journalPath = $databasePath . '-journal';
$walPath = $databasePath . '-wal';
$page = static fn (string $label): string => str_pad($label, $pageSize, '.', STR_PAD_RIGHT);
$dirtyDatabase = $page('next177 dirty schema after plugin import')
    . $page('next177 dirty wp_options root after plugin import')
    . $page('next177 dirty active_plugins after plugin import')
    . $page('next177 dirty rewrite_rules after plugin import');
$cleanPages = [
    1 => $page('next177 clean schema before plugin import'),
    2 => $page('next177 clean wp_options root before plugin import'),
    4 => $page('next177 clean rewrite_rules before plugin import'),
];

$makeJournal = static function (array $pages, int $nonce = 0x17717701) use ($sectorSize, $pageSize): string {
    $header = SQLiteRollbackJournalHeader::MAGIC . pack('N*', count($pages), $nonce, max(array_keys($pages)), $sectorSize, $pageSize);
    $bytes = str_pad($header, $sectorSize, "\0");
    foreach ($pages as $pageNumber => $image) {
        $bytes .= pack('N', $pageNumber) . $image . pack('N', SQLiteRollbackJournal::pageChecksum($image, $nonce));
    }

    return $bytes;
};

$makeWal = static function (array $frames, int $checkpoint = 177) use ($pageSize, $page): string {
    $salt1 = 0x17717701;
    $salt2 = 0x17717702;
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
    $stack->beginTransaction('wp-import-next177');
    $stack->recordWalFrameWrite(1, 1);
    $stack->recordWalFrameWrite(2, 2, true);
    $stack->savepoint('plugin-batch-next177');
    $stack->recordWalFrameWrite(3, 3);
    $stack->recordWalFrameWrite(4, 4, true);

    return $stack;
};

$journalBytes = $makeJournal($cleanPages);
$walBytes = $makeWal([
    [1, 0, 'next177 retained schema draft before publish'],
    [2, 4, 'next177 retained wp_options commit before publish'],
    [3, 0, 'next177 discarded active_plugins draft'],
    [4, 4, 'next177 discarded rewrite_rules commit'],
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
    'plugin-batch-next177',
    $wal,
    $walBytes,
    [1, 2, 3, 4],
    $completed,
    $files ?? [],
    $mode,
    null,
    $reserved
);

$filesFrom = static function (array $completed, string $mode = 'restart') use ($base, $databasePath, $journalPath, $walPath, $journalBytes): array {
    $probe = $base($completed, [], $mode);
    $journalRow = $probe['file_rows'][1];
    $payloads = $probe['base_plan']['base_plan']['payloads'];
    $databaseKey = $probe['base_plan']['released_database_synced']
        ? $databasePath . '#next165-released-checkpoint'
        : $databasePath . '#next165-current-checkpoint';
    $walKey = $probe['base_plan']['released_database_synced']
        ? $walPath . '#next165-released-reader'
        : $walPath . '#next165-current-reader';

    return [
        $databasePath => (string) $payloads[$databaseKey],
        $journalPath => $journalRow['required'] ? $journalBytes : null,
        $walPath => (string) $payloads[$walKey],
    ];
};

$matching = static fn (array $completed = [], string $mode = 'restart'): array => $base($completed, $filesFrom($completed, $mode), $mode);
$plan = static fn (array $resume, bool $lock = true, bool $dirSync = true): array => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::atomicResumeApplyPlan($resume, $lock, $dirSync);
$noReplay = static fn (): array => $plan($matching());
$missingDatabase = static fn (): array => $plan($base($currentComplete, [
    $journalPath => $journalBytes,
    $walPath => $filesFrom($currentComplete)[$walPath],
]));
$missingWal = static fn (): array => $plan($base($currentComplete, [
    $databasePath => $filesFrom($currentComplete)[$databasePath],
    $journalPath => $journalBytes,
]));
$missingJournal = static fn (): array => $plan($base([], [
    $databasePath => $filesFrom([])[$databasePath],
    $journalPath => 'stale-journal',
    $walPath => $filesFrom([])[$walPath],
]));
$deleteJournal = static fn (): array => $plan($matching($currentComplete));
$releaseDeletePending = static fn (): array => $plan($base($journalDeleted, array_replace($filesFrom($journalDeleted), [$journalPath => $journalBytes])));
$releaseReady = static fn (): array => $plan($matching($allRestart));
$truncateReady = static fn (): array => $plan($matching($allTruncate, 'truncate'));
$blockedBase = static fn (): array => $plan($base([], null, 'restart', true));

$cases = [
    'status' => [static fn (): mixed => $noReplay()['status'], 'wal-hot-journal-savepoint-checkpoint-current-source-next177'],
    'reason noop' => [static fn (): mixed => $noReplay()['reason'], 'verified_resume_files_need_no_vfs_apply'],
    'database path' => [static fn (): mixed => $noReplay()['database_path'], $databasePath],
    'journal path' => [static fn (): mixed => $noReplay()['journal_path'], $journalPath],
    'wal path' => [static fn (): mixed => $noReplay()['wal_path'], $walPath],
    'exclusive lock' => [static fn (): mixed => $noReplay()['exclusive_lock_held'], true],
    'directory sync' => [static fn (): mixed => $noReplay()['directory_sync_available'], true],
    'can apply' => [static fn (): mixed => $noReplay()['can_apply'], true],
    'noop true' => [static fn (): mixed => $noReplay()['noop'], true],
    'noop operations empty' => [static fn (): mixed => $noReplay()['operation_names'], []],
    'noop payloads empty' => [static fn (): mixed => $noReplay()['payload_paths'], []],
    'noop blocked empty' => [static fn (): mixed => $noReplay()['blocked_reasons'], []],
    'digest propagated' => [static fn (): mixed => strlen($noReplay()['resume_digest']), 64],
    'database replay status' => [static fn (): mixed => $missingDatabase()['status'], 'wal-hot-journal-savepoint-checkpoint-current-source-next177'],
    'database replay reason' => [static fn (): mixed => $missingDatabase()['reason'], 'ordered_atomic_resume_apply_for_verified_hot_journal_savepoint_checkpoint'],
    'database replay ops' => [static fn (): mixed => $missingDatabase()['operation_names'], ['write', 'truncate', 'sync', 'sync_directory']],
    'database replay first reason' => [static fn (): mixed => $missingDatabase()['operation_reasons'][0], 'rewrite_current_checkpoint_database_payload'],
    'database replay trim reason' => [static fn (): mixed => $missingDatabase()['operation_reasons'][1], 'trim_current-checkpoint-database_after_resume_next177'],
    'database replay payload path' => [static fn (): mixed => $missingDatabase()['payload_paths'], [$databasePath]],
    'database replay write bytes positive' => [static fn (): mixed => $missingDatabase()['write_bytes'] > 0, true],
    'database replay durable count' => [static fn (): mixed => $missingDatabase()['durable_operation_count'], 2],
    'wal replay ops' => [static fn (): mixed => $missingWal()['operation_names'], ['write', 'truncate', 'sync', 'sync_directory']],
    'wal replay first reason' => [static fn (): mixed => $missingWal()['operation_reasons'][0], 'rewrite_retained_wal_for_reader'],
    'wal replay payload path' => [static fn (): mixed => $missingWal()['payload_paths'], [$walPath]],
    'journal restore ops' => [static fn (): mixed => $missingJournal()['operation_names'], ['write', 'sync', 'sync_directory']],
    'journal restore reason' => [static fn (): mixed => $missingJournal()['operation_reasons'][0], 'restore_hot_journal_for_resume'],
    'journal restore payload' => [static fn (): mixed => $missingJournal()['payload_paths'], [$journalPath]],
    'journal delete ops' => [static fn (): mixed => $deleteJournal()['operation_names'], ['delete', 'sync_directory']],
    'journal delete reason' => [static fn (): mixed => $deleteJournal()['operation_reasons'][0], 'delete_hot_journal_after_verified_resume_next177'],
    'journal delete count' => [static fn (): mixed => $deleteJournal()['delete_count'], 1],
    'release delete pending status' => [static fn (): mixed => $releaseDeletePending()['status'], 'wal-hot-journal-savepoint-checkpoint-current-source-next177'],
    'release delete pending ops' => [static fn (): mixed => $releaseDeletePending()['operation_names'], ['delete', 'sync_directory']],
    'release ready noop' => [static fn (): mixed => $releaseReady()['noop'], true],
    'release ready can apply' => [static fn (): mixed => $releaseReady()['can_apply'], true],
    'truncate ready noop' => [static fn (): mixed => $truncateReady()['noop'], true],
    'truncate ready can apply' => [static fn (): mixed => $truncateReady()['can_apply'], true],
    'lock blocker status' => [static fn (): mixed => $plan($matching(), false)['status'], 'wal-hot-journal-savepoint-checkpoint-current-source-blocked-next177'],
    'lock blocker reason' => [static fn (): mixed => $plan($matching(), false)['blocked_reasons'], ['exclusive_lock_required_before_hot_journal_checkpoint_resume']],
    'dir sync blocker status' => [static fn (): mixed => $plan($base($currentComplete, [$journalPath => $journalBytes, $walPath => $filesFrom($currentComplete)[$walPath]]), true, false)['status'], 'wal-hot-journal-savepoint-checkpoint-current-source-blocked-next177'],
    'dir sync blocker reason' => [static fn (): mixed => $plan($base($currentComplete, [$journalPath => $journalBytes, $walPath => $filesFrom($currentComplete)[$walPath]]), true, false)['blocked_reasons'], ['directory_sync_required_for_atomic_resume_publication']],
    'blocked base status' => [static fn (): mixed => $blockedBase()['status'], 'wal-hot-journal-savepoint-checkpoint-current-source-blocked-next177'],
    'blocked base reason' => [static fn (): mixed => $blockedBase()['blocked_reasons'], ['resume_state_not_current_source_next174']],
    'dependency marker' => [static fn (): mixed => in_array('sqlite-wal-hot-journal-savepoint-checkpoint-current-source-next177', $missingDatabase()['dependencies'], true), true],
    'dependency apply order' => [static fn (): mixed => in_array('sqlite-vfs-atomic-resume-apply-order', $missingDatabase()['dependencies'], true), true],
    'dependency closure' => [static fn (): mixed => str_contains($missingDatabase()['dependency_closure'], 'no new support component needed'), true],
    'non overlap' => [static fn (): mixed => str_contains($missingDatabase()['non_overlap'], 'extends next174'), true],
];

foreach ($cases as $name => [$callback, $expected]) {
    $tests['wal hot journal savepoint checkpoint current source next177 ' . $name] = static function (TestRunner $t) use ($callback, $expected): void {
        $t->same($expected, $callback());
    };
}

$throws = [
    'missing status rejected' => static function () use ($matching): void {
        $resume = $matching();
        unset($resume['status']);
        SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::atomicResumeApplyPlan($resume);
    },
    'missing rows rejected' => static function () use ($matching): void {
        $resume = $matching();
        unset($resume['file_rows']);
        SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::atomicResumeApplyPlan($resume);
    },
    'bad rows rejected' => static function () use ($matching): void {
        $resume = $matching();
        $resume['file_rows'] = 'bad';
        SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::atomicResumeApplyPlan($resume);
    },
    'missing wal role rejected' => static function () use ($matching): void {
        $resume = $matching();
        $resume['file_rows'] = array_slice($resume['file_rows'], 0, 2);
        SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::atomicResumeApplyPlan($resume);
    },
];

foreach ($throws as $name => $callback) {
    $tests['wal hot journal savepoint checkpoint current source next177 ' . $name] = static function (TestRunner $t) use ($callback): void {
        $t->throws(Throwable::class, $callback);
    };
}

return $tests;
