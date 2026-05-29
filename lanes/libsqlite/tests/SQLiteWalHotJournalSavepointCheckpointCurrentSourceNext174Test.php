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
$databasePath = '/srv/www/wp-content/database/wp-next174.sqlite';
$journalPath = $databasePath . '-journal';
$walPath = $databasePath . '-wal';
$page = static fn (string $label): string => str_pad($label, $pageSize, '.', STR_PAD_RIGHT);
$cleanPages = [
    1 => $page('next174 clean schema before failed import'),
    2 => $page('next174 clean wp_options root before failed import'),
    3 => $page('next174 clean active_plugins before failed import'),
    4 => $page('next174 clean rewrite_rules before failed import'),
];
$dirtyDatabase = $page('next174 dirty schema from failed import')
    . $page('next174 dirty wp_options root from failed import')
    . $page('next174 dirty active_plugins from failed import')
    . $page('next174 dirty rewrite_rules from failed import');

$makeJournal = static function (array $pages, int $nonce = 0x17417401) use ($sectorSize, $pageSize): string {
    $header = SQLiteRollbackJournalHeader::MAGIC . pack('N*', count($pages), $nonce, count($pages), $sectorSize, $pageSize);
    $bytes = str_pad($header, $sectorSize, "\0");
    foreach ($pages as $pageNumber => $image) {
        $bytes .= pack('N', $pageNumber) . $image . pack('N', SQLiteRollbackJournal::pageChecksum($image, $nonce));
    }

    return $bytes;
};

$makeWal = static function (array $frames, int $checkpoint = 174) use ($pageSize, $page): string {
    $salt1 = 0x17417401;
    $salt2 = 0x17417402;
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
    [1, 0, 'next174 retained schema draft before publish'],
    [2, 4, 'next174 retained wp_options commit before publish'],
    [3, 0, 'next174 discarded active_plugins draft'],
    [4, 4, 'next174 discarded rewrite_rules commit'],
]);
$wal = SQLiteWal::parse($walBytes, $pageSize, true);

$makeStack = static function (): SQLiteSavepointStack {
    $stack = new SQLiteSavepointStack();
    $stack->beginTransaction('wp-import-next174');
    $stack->recordWalFrameWrite(1, 1);
    $stack->recordWalFrameWrite(2, 2, true);
    $stack->savepoint('plugin-batch-next174');
    $stack->recordWalFrameWrite(3, 3);
    $stack->recordWalFrameWrite(4, 4, true);

    return $stack;
};

$base169 = static fn (array $completed = [], string $mode = 'restart'): array => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next169Plan(
    $databasePath,
    $dirtyDatabase,
    $journalBytes,
    $makeStack(),
    'plugin-batch-next174',
    $wal,
    $walBytes,
    [1, 2, 3, 4],
    $completed,
    $mode
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

$filesFor = static function (array $completed, string $mode = 'restart') use ($base169, $databasePath, $journalPath, $walPath, $journalBytes): array {
    $base = $base169($completed, $mode);
    $payloads = $base['base_plan']['payloads'];
    $databaseKey = $base['released_database_synced']
        ? $databasePath . '#next165-released-checkpoint'
        : $databasePath . '#next165-current-checkpoint';
    $walKey = $base['released_database_synced']
        ? $walPath . '#next165-released-reader'
        : $walPath . '#next165-current-reader';

    return [
        $databasePath => (string) $payloads[$databaseKey],
        $journalPath => $base['journal_delete_completed'] ? null : $journalBytes,
        $walPath => (string) $payloads[$walKey],
    ];
};

$plan = static fn (array $completed = [], ?array $files = null, string $mode = 'restart', bool $reserved = false): array => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next174Plan(
    $databasePath,
    $dirtyDatabase,
    $journalBytes,
    $makeStack(),
    'plugin-batch-next174',
    $wal,
    $walBytes,
    [1, 2, 3, 4],
    $completed,
    $files ?? $filesFor($completed, $mode),
    $mode,
    null,
    $reserved
);

$cases = [
    'status' => [static fn (): mixed => $plan()['status'], 'wal-hot-journal-savepoint-checkpoint-current-source-next174'],
    'reason' => [static fn (): mixed => $plan()['reason'], 'verify_partial_hot_journal_savepoint_checkpoint_files_before_resume'],
    'database path' => [static fn (): mixed => $plan()['database_path'], $databasePath],
    'journal path' => [static fn (): mixed => $plan()['journal_path'], $journalPath],
    'wal path' => [static fn (): mixed => $plan()['wal_path'], $walPath],
    'savepoint' => [static fn (): mixed => $plan()['savepoint'], 'plugin-batch-next174'],
    'mode' => [static fn (): mixed => $plan()['mode'], 'restart'],
    'page size' => [static fn (): mixed => $plan()['page_size'], 512],
    'page numbers' => [static fn (): mixed => $plan()['page_numbers'], [1, 2, 3, 4]],
    'recovery admitted' => [static fn (): mixed => $plan()['recovery_admitted'], true],
    'initial needs no replay with matching files' => [static fn (): mixed => $plan()['needs_replay'], false],
    'initial missing paths empty' => [static fn (): mixed => $plan()['missing_required_paths'], []],
    'initial mismatched paths empty' => [static fn (): mixed => $plan()['mismatched_paths'], []],
    'initial replay actions empty' => [static fn (): mixed => $plan()['replay_actions'], []],
    'initial hot journal delete blocked until sync' => [static fn (): mixed => $plan()['hot_journal_delete_admitted'], false],
    'initial journal required' => [static fn (): mixed => $plan()['journal_required_for_recovery'], true],
    'initial reader release blocked' => [static fn (): mixed => $plan()['reader_release_admitted'], false],
    'initial wal reset blocked' => [static fn (): mixed => $plan()['wal_reset_admitted'], false],
    'file roles initial' => [static fn (): mixed => $plan()['file_roles'], ['current-checkpoint-database', 'hot-journal', 'current-reader-wal']],
    'file rows count' => [static fn (): mixed => count($plan()['file_rows']), 3],
    'database row matches' => [static fn (): mixed => $plan()['file_rows'][0]['matches'], true],
    'journal row required' => [static fn (): mixed => $plan()['file_rows'][1]['required'], true],
    'wal row required' => [static fn (): mixed => $plan()['file_rows'][2]['required'], true],
    'database row action verify' => [static fn (): mixed => $plan()['file_rows'][0]['replay_action'], 'verify_persisted'],
    'digest length' => [static fn (): mixed => strlen($plan()['file_digest']), 64],
    'base digest length' => [static fn (): mixed => strlen($plan()['base_phase_digest']), 64],
    'current complete delete admitted' => [static fn (): mixed => $plan($currentComplete)['hot_journal_delete_admitted'], true],
    'current complete reader release blocked while journal exists' => [static fn (): mixed => $plan($currentComplete)['reader_release_admitted'], false],
    'current complete wal reset blocked' => [static fn (): mixed => $plan($currentComplete)['wal_reset_admitted'], false],
    'journal deleted required false' => [static fn (): mixed => $plan($journalDeleted)['journal_required_for_recovery'], false],
    'journal deleted reader release admitted' => [static fn (): mixed => $plan($journalDeleted)['reader_release_admitted'], true],
    'journal deleted wal reset blocked before release db' => [static fn (): mixed => $plan($journalDeleted)['wal_reset_admitted'], false],
    'release started wal reset admitted' => [static fn (): mixed => $plan($releaseStarted)['wal_reset_admitted'], true],
    'release started roles still current' => [static fn (): mixed => $plan($releaseStarted)['file_roles'], ['current-checkpoint-database', 'hot-journal', 'current-reader-wal']],
    'all restart complete' => [static fn (): mixed => $plan($allRestart)['resume_complete'], true],
    'all restart roles released' => [static fn (): mixed => $plan($allRestart)['file_roles'], ['released-checkpoint-database', 'hot-journal', 'released-wal']],
    'all restart journal not required' => [static fn (): mixed => $plan($allRestart)['file_rows'][1]['required'], false],
    'all restart wal required' => [static fn (): mixed => $plan($allRestart)['file_rows'][2]['required'], true],
    'all restart no replay' => [static fn (): mixed => $plan($allRestart)['needs_replay'], false],
    'all truncate complete' => [static fn (): mixed => $plan($allTruncate, null, 'truncate')['resume_complete'], true],
    'all truncate wal role empty' => [static fn (): mixed => $plan($allTruncate, null, 'truncate')['file_rows'][2]['role'], 'released-empty-wal'],
    'all truncate wal not required' => [static fn (): mixed => $plan($allTruncate, null, 'truncate')['file_rows'][2]['required'], false],
    'missing database requires replay' => [static fn (): mixed => $plan($currentComplete, [$journalPath => $journalBytes, $walPath => $filesFor($currentComplete)[$walPath]])['mismatched_paths'], [$databasePath]],
    'missing database action' => [static fn (): mixed => $plan($currentComplete, [$journalPath => $journalBytes, $walPath => $filesFor($currentComplete)[$walPath]])['replay_actions'], ['rewrite_current_checkpoint_database_payload']],
    'missing wal action' => [static fn (): mixed => $plan($currentComplete, [$databasePath => $filesFor($currentComplete)[$databasePath], $journalPath => $journalBytes])['replay_actions'], ['rewrite_retained_wal_for_reader']],
    'bad database bytes mismatch' => [static fn (): mixed => $plan($currentComplete, array_replace($filesFor($currentComplete), [$databasePath => 'bad']))['mismatched_paths'], [$databasePath]],
    'bad journal bytes mismatch' => [static fn (): mixed => $plan($currentComplete, array_replace($filesFor($currentComplete), [$journalPath => 'bad']))['mismatched_paths'], [$journalPath]],
    'bad wal bytes mismatch' => [static fn (): mixed => $plan($currentComplete, array_replace($filesFor($currentComplete), [$walPath => 'bad']))['mismatched_paths'], [$walPath]],
    'blocked status bubbles' => [static fn (): mixed => $plan([], null, 'restart', true)['status'], 'wal-hot-journal-savepoint-checkpoint-current-source-blocked-next174'],
    'blocked recovery false' => [static fn (): mixed => $plan([], null, 'restart', true)['recovery_admitted'], false],
    'dependency marker' => [static fn (): mixed => in_array('sqlite-wal-hot-journal-savepoint-checkpoint-current-source-next174', $plan()['dependencies'], true), true],
    'dependency file resume' => [static fn (): mixed => in_array('sqlite-wal-hot-journal-checkpoint-file-resume', $plan()['dependencies'], true), true],
    'wordpress dependency' => [static fn (): mixed => in_array('wordpress-import-hot-journal-savepoint-file-replay', $plan()['dependencies'], true), true],
    'dependency closure text' => [static fn (): mixed => str_contains($plan()['dependency_closure'], 'no new support component needed'), true],
    'non overlap text' => [static fn (): mixed => str_contains($plan()['non_overlap'], 'extends next169'), true],
];

foreach ($cases as $name => [$callback, $expected]) {
    $tests['wal hot journal savepoint checkpoint current source next174 ' . $name] = static function (TestRunner $t) use ($callback, $expected): void {
        $t->same($expected, $callback());
    };
}

$throws = [
    'bad file path rejected' => static fn () => $plan($currentComplete, [0 => 'bytes']),
    'bad file bytes rejected' => static fn () => $plan($currentComplete, [$databasePath => ['bad']]),
    'bad mode rejected' => static fn () => $plan([], null, 'passive'),
    'unknown completed reason rejected' => static fn () => $plan(['missing-op']),
];

foreach ($throws as $name => $callback) {
    $tests['wal hot journal savepoint checkpoint current source next174 ' . $name] = static function (TestRunner $t) use ($callback): void {
        $t->throws(Throwable::class, $callback);
    };
}

return $tests;
