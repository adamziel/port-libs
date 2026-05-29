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
$databasePath = '/srv/www/wp-content/database/wp-next169.sqlite';
$page = static fn (string $label): string => str_pad($label, $pageSize, '.', STR_PAD_RIGHT);
$cleanPages = [
    1 => $page('next169 clean schema before failed import'),
    2 => $page('next169 clean wp_options root before failed import'),
    3 => $page('next169 clean active_plugins before failed import'),
    4 => $page('next169 clean rewrite_rules before failed import'),
];
$dirtyDatabase = $page('next169 dirty schema from failed import')
    . $page('next169 dirty wp_options root from failed import')
    . $page('next169 dirty active_plugins from failed import')
    . $page('next169 dirty rewrite_rules from failed import');

$makeJournal = static function (array $pages, int $nonce = 0x16916901) use ($sectorSize, $pageSize): string {
    $header = SQLiteRollbackJournalHeader::MAGIC . pack('N*', count($pages), $nonce, count($pages), $sectorSize, $pageSize);
    $bytes = str_pad($header, $sectorSize, "\0");
    foreach ($pages as $pageNumber => $image) {
        $bytes .= pack('N', $pageNumber) . $image . pack('N', SQLiteRollbackJournal::pageChecksum($image, $nonce));
    }

    return $bytes;
};

$makeWal = static function (array $frames, int $checkpoint = 169) use ($pageSize, $page): string {
    $salt1 = 0x16916901;
    $salt2 = 0x16916902;
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

$journalBytes = $makeJournal($cleanPages);
$walBytes = $makeWal([
    [1, 0, 'next169 retained schema draft before publish'],
    [2, 4, 'next169 retained wp_options commit before publish'],
    [3, 0, 'next169 discarded active_plugins draft'],
    [4, 4, 'next169 discarded rewrite_rules commit'],
]);
$wal = SQLiteWal::parse($walBytes, $pageSize, true);

$makeStack = static function (): SQLiteSavepointStack {
    $stack = new SQLiteSavepointStack();
    $stack->beginTransaction('wp-import-next169');
    $stack->recordWalFrameWrite(1, 1);
    $stack->recordWalFrameWrite(2, 2, true);
    $stack->savepoint('plugin-batch-next169');
    $stack->recordWalFrameWrite(3, 3);
    $stack->recordWalFrameWrite(4, 4, true);

    return $stack;
};

$plan = static fn (array $completed = [], string $mode = 'restart', ?int $reader = null, bool $reserved = false): array => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next169Plan(
    $databasePath,
    $dirtyDatabase,
    $journalBytes,
    $makeStack(),
    'plugin-batch-next169',
    $wal,
    $walBytes,
    [1, 2, 3, 4],
    $completed,
    $mode,
    $reader,
    $reserved
);

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

$cases = [
    'status' => [static fn (): mixed => $plan()['status'], 'wal-hot-journal-savepoint-checkpoint-current-source-next169'],
    'reason' => [static fn (): mixed => $plan()['reason'], 'resume_hot_journal_savepoint_checkpoint_publish_after_partial_vfs_apply'],
    'database path' => [static fn (): mixed => $plan()['database_path'], $databasePath],
    'journal path' => [static fn (): mixed => $plan()['journal_path'], $databasePath . '-journal'],
    'wal path' => [static fn (): mixed => $plan()['wal_path'], $databasePath . '-wal'],
    'savepoint' => [static fn (): mixed => $plan()['savepoint'], 'plugin-batch-next169'],
    'mode' => [static fn (): mixed => $plan()['mode'], 'restart'],
    'page size' => [static fn (): mixed => $plan()['page_size'], 512],
    'page numbers' => [static fn (): mixed => $plan()['page_numbers'], [1, 2, 3, 4]],
    'resume admitted' => [static fn (): mixed => $plan()['resume_admitted'], true],
    'not complete initially' => [static fn (): mixed => $plan()['resume_complete'], false],
    'first pending operation' => [static fn (): mixed => $plan()['next_operation_reason'], 'publish_hot_journal_savepoint_current_checkpoint_database_next165'],
    'initial completed count' => [static fn (): mixed => $plan()['completed_count'], 0],
    'initial pending count' => [static fn (): mixed => $plan()['pending_count'], 8],
    'initial journal required' => [static fn (): mixed => $plan()['journal_required_for_recovery'], true],
    'initial journal delete blocked' => [static fn (): mixed => $plan()['journal_delete_admitted'], false],
    'initial reader release blocked' => [static fn (): mixed => $plan()['reader_release_admitted'], false],
    'initial wal reset blocked' => [static fn (): mixed => $plan()['wal_reset_admitted'], false],
    'initial current db unsynced' => [static fn (): mixed => $plan()['current_database_synced'], false],
    'initial wal not preserved' => [static fn (): mixed => $plan()['current_wal_preserved'], false],
    'initial next action' => [static fn (): mixed => $plan()['phase_rows'][0]['resume_action'], 'rewrite_current_checkpoint_database_payload'],
    'initial journal exists first row' => [static fn (): mixed => $plan()['phase_rows'][0]['journal_must_exist'], true],
    'initial phase digest length' => [static fn (): mixed => strlen($plan()['phase_digest']), 64],
    'initial base digest length' => [static fn (): mixed => strlen($plan()['base_publish_digest']), 64],
    'crash windows' => [static fn (): mixed => $plan()['crash_windows'], ['current_source_checkpoint_publish', 'hot_journal_retirement', 'released_savepoint_checkpoint_publish']],
    'resume actions include delete fence' => [static fn (): mixed => in_array('delete_hot_journal_after_current_payloads_durable', $plan()['resume_actions'], true), true],
    'current complete count' => [static fn (): mixed => $plan($currentComplete)['completed_count'], 4],
    'current complete pending count' => [static fn (): mixed => $plan($currentComplete)['pending_count'], 4],
    'current complete next op delete journal' => [static fn (): mixed => $plan($currentComplete)['next_operation_reason'], 'delete_hot_journal_after_current_source_checkpoint_next165'],
    'current complete db synced' => [static fn (): mixed => $plan($currentComplete)['current_database_synced'], true],
    'current complete wal preserved' => [static fn (): mixed => $plan($currentComplete)['current_wal_preserved'], true],
    'current complete journal delete admitted' => [static fn (): mixed => $plan($currentComplete)['journal_delete_admitted'], true],
    'current complete reader release blocked until delete' => [static fn (): mixed => $plan($currentComplete)['reader_release_admitted'], false],
    'current complete wal reset blocked' => [static fn (): mixed => $plan($currentComplete)['wal_reset_admitted'], false],
    'journal deleted required false' => [static fn (): mixed => $plan($journalDeleted)['journal_required_for_recovery'], false],
    'journal deleted completed true' => [static fn (): mixed => $plan($journalDeleted)['journal_delete_completed'], true],
    'journal deleted reader release admitted' => [static fn (): mixed => $plan($journalDeleted)['reader_release_admitted'], true],
    'journal deleted wal reset blocked' => [static fn (): mixed => $plan($journalDeleted)['wal_reset_admitted'], false],
    'release started wal reset admitted' => [static fn (): mixed => $plan($releaseStarted)['wal_reset_admitted'], true],
    'release started next op restart wal' => [static fn (): mixed => $plan($releaseStarted)['next_operation_reason'], 'restart_wal_after_savepoint_release_next165'],
    'release started pending count' => [static fn (): mixed => $plan($releaseStarted)['pending_count'], 2],
    'all restart complete' => [static fn (): mixed => $plan($allRestart)['resume_complete'], true],
    'all restart pending empty' => [static fn (): mixed => $plan($allRestart)['pending_operation_reasons'], []],
    'all restart next null' => [static fn (): mixed => $plan($allRestart)['next_operation_reason'], null],
    'all restart released synced' => [static fn (): mixed => $plan($allRestart)['released_database_synced'], true],
    'all restart completed count' => [static fn (): mixed => $plan($allRestart)['completed_count'], 8],
    'all restart pending count' => [static fn (): mixed => $plan($allRestart)['pending_count'], 0],
    'all restart phase verify' => [static fn (): mixed => $plan($allRestart)['phase_rows'][6]['resume_action'], 'verify_persisted'],
    'truncate complete' => [static fn (): mixed => $plan($allTruncate, 'truncate')['resume_complete'], true],
    'truncate wal operation reason accepted' => [static fn (): mixed => $plan($allTruncate, 'truncate')['phase_rows'][6]['reason'], 'truncate_wal_after_savepoint_release_next165'],
    'truncate phase action verified' => [static fn (): mixed => $plan($allTruncate, 'truncate')['phase_rows'][6]['resume_action'], 'verify_persisted'],
    'base status' => [static fn (): mixed => $plan()['base_plan']['status'], 'wal-hot-journal-savepoint-checkpoint-current-source-next165'],
    'base pinned pages' => [static fn (): mixed => $plan()['base_plan']['pinned_reader_page_numbers'], [1, 2]],
    'base stale pages' => [static fn (): mixed => $plan()['base_plan']['stale_publish_blocked_page_numbers'], [3, 4]],
    'dependency marker' => [static fn (): mixed => in_array('sqlite-wal-hot-journal-savepoint-checkpoint-current-source-next169', $plan()['dependencies'], true), true],
    'dependency resume' => [static fn (): mixed => in_array('sqlite-wal-checkpoint-partial-publish-resume', $plan()['dependencies'], true), true],
    'dependency wordpress' => [static fn (): mixed => in_array('wordpress-import-hot-journal-savepoint-crash-resume', $plan()['dependencies'], true), true],
    'dependency closure text' => [static fn (): mixed => str_contains($plan()['dependency_closure'], 'no new support component needed'), true],
    'non overlap text' => [static fn (): mixed => str_contains($plan()['non_overlap'], 'extends next165'), true],
    'blocked status' => [static fn (): mixed => $plan([], 'restart', null, true)['status'], 'wal-hot-journal-savepoint-checkpoint-current-source-blocked-next169'],
    'blocked admitted false' => [static fn (): mixed => $plan([], 'restart', null, true)['resume_admitted'], false],
    'blocked dependency marker' => [static fn (): mixed => in_array('sqlite-wal-hot-journal-savepoint-checkpoint-current-source-next169', $plan([], 'restart', null, true)['dependencies'], true), true],
];

foreach ($cases as $name => [$callback, $expected]) {
    $tests['wal hot journal savepoint checkpoint current source next169 ' . $name] = static function (TestRunner $t) use ($callback, $expected): void {
        $t->same($expected, $callback());
    };
}

$throws = [
    'unknown completed reason rejected' => static fn () => $plan(['not-a-publish-operation']),
    'empty completed reason rejected' => static fn () => $plan(['']),
    'non-string completed reason rejected' => static fn () => $plan([123]),
    'bad mode rejected' => static fn () => $plan([], 'passive'),
    'reader past retained rejected' => static fn () => $plan([], 'restart', 3),
];

foreach ($throws as $name => $callback) {
    $tests['wal hot journal savepoint checkpoint current source next169 ' . $name] = static function (TestRunner $t) use ($callback): void {
        $t->throws(Throwable::class, $callback);
    };
}

return $tests;
