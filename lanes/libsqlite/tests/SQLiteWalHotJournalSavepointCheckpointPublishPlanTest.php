<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteWal;
use PortLibs\LibSqlite\SQLiteWalHeader;
use PortLibs\LibSqlite\SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan;

$tests = [];

$pageSize = 512;
$databasePath = '/srv/www/wp-content/database/wp-publish.sqlite';
$page = static fn (string $label): string => str_pad($label, $pageSize, '.', STR_PAD_RIGHT);
$databaseBytes = $page('publish dirty schema after interrupted import')
    . $page('publish dirty wp_options root after interrupted import')
    . $page('publish dirty active_plugins after interrupted import');
$journalBytes = 'hot-journal-publish:' . $page('publish clean wp_options before failed import');

$makeWalBytes = static function (array $frames, int $checkpoint, int $salt1, int $salt2) use ($pageSize, $page): string {
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

$currentWalBytes = $makeWalBytes([
    [1, 0, 'publish current schema draft'],
    [2, 3, 'publish current wp_options commit'],
    [3, 3, 'publish current active_plugins commit'],
], 173, 0x17300101, 0x17300102);
$nextWalBytes = $makeWalBytes([
    [2, 0, 'publish next wp_options draft'],
    [3, 3, 'publish next active_plugins commit'],
], 174, 0x17400101, 0x17400102);
$currentWal = SQLiteWal::parse($currentWalBytes, $pageSize, true);
$nextWal = SQLiteWal::parse($nextWalBytes, $pageSize, true);

$bootstrap = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::planCheckpointSourceTransition(
    $databasePath,
    $databaseBytes,
    $pageSize,
    'wp-batch-publish',
    [2 => $page('publish hot journal clean wp_options root')],
    [3 => $page('publish savepoint before active_plugins')],
    $currentWal,
    $currentWalBytes,
    $nextWal,
    $nextWalBytes,
    [1 => ['image' => $page('publish current schema draft'), 'source_id' => 'bootstrap', 'epoch' => 1]],
    [1, 2, 3],
    [
        ['name' => 'bootstrap-current', 'source_id' => 'bootstrap', 'epoch' => 1],
        ['name' => 'bootstrap-stale', 'source_id' => 'old-bootstrap', 'epoch' => 1],
    ],
    null,
    null,
    null,
    'restart',
    3,
    173
);
$currentToken = $bootstrap['current_source_token'];
$nextToken = $bootstrap['next_source_token'];

$prepared = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::planCheckpointSourceTransition(
    $databasePath,
    $databaseBytes,
    $pageSize,
    'wp-batch-publish',
    [2 => $page('publish hot journal clean wp_options root')],
    [3 => $page('publish savepoint before active_plugins')],
    $currentWal,
    $currentWalBytes,
    $nextWal,
    $nextWalBytes,
    [
        1 => ['image' => $page('publish current schema draft'), 'source_id' => $currentToken['id'], 'epoch' => $currentToken['epoch']],
        2 => ['image' => $page('publish stale root cache'), 'source_id' => 'old', 'epoch' => 1],
    ],
    [1, 2, 3],
    [
        ['name' => 'wp-reader-current', 'source_id' => $currentToken['id'], 'epoch' => $currentToken['epoch']],
        ['name' => 'wp-reader-stale', 'source_id' => 'old', 'epoch' => 1],
    ],
    $currentToken,
    $nextToken,
    null,
    'restart',
    3,
    173
);

$ok = static fn (): array => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::publishDurableHotJournalSavepointCheckpointPlan(
    $prepared,
    $databaseBytes,
    $journalBytes,
    $currentWalBytes,
    hash('sha256', $databaseBytes),
    hash('sha256', $journalBytes),
    hash('sha256', $currentWalBytes),
    true
);
$staleDatabase = static fn (): array => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::publishDurableHotJournalSavepointCheckpointPlan($prepared, $databaseBytes . 'x', $journalBytes, $currentWalBytes, hash('sha256', $databaseBytes), hash('sha256', $journalBytes), hash('sha256', $currentWalBytes));
$staleJournal = static fn (): array => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::publishDurableHotJournalSavepointCheckpointPlan($prepared, $databaseBytes, $journalBytes . 'x', $currentWalBytes, hash('sha256', $databaseBytes), hash('sha256', $journalBytes), hash('sha256', $currentWalBytes));
$staleWal = static fn (): array => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::publishDurableHotJournalSavepointCheckpointPlan($prepared, $databaseBytes, $journalBytes, $currentWalBytes . 'x', hash('sha256', $databaseBytes), hash('sha256', $journalBytes), hash('sha256', $currentWalBytes));
$pinned = static fn (): array => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::publishDurableHotJournalSavepointCheckpointPlan($prepared, $databaseBytes, $journalBytes, $currentWalBytes, null, null, null, false);
$blockedPrepared = $prepared;
$blockedPrepared['status'] = 'wal-hot-journal-savepoint-checkpoint-current-source-blocked-next167';
$blockedBase = static fn (): array => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::publishDurableHotJournalSavepointCheckpointPlan($blockedPrepared, $databaseBytes, $journalBytes, $currentWalBytes);

$cases = [
    'status' => [static fn (): mixed => $ok()['status'], 'wal-hot-journal-savepoint-checkpoint-publish-current-source'],
    'reason' => [static fn (): mixed => $ok()['reason'], 'filesystem_current_source_hashes_match_guarded_wal_checkpoint_publication'],
    'database path' => [static fn (): mixed => $ok()['database_path'], $databasePath],
    'journal path' => [static fn (): mixed => $ok()['journal_path'], $databasePath . '-journal'],
    'wal path' => [static fn (): mixed => $ok()['wal_path'], $databasePath . '-wal'],
    'reader drained' => [static fn (): mixed => $ok()['reader_drained'], true],
    'prepared status' => [static fn (): mixed => $ok()['prepared_status'], 'wal-hot-journal-savepoint-checkpoint-current-source-next167'],
    'fingerprint propagated' => [static fn (): mixed => $ok()['publication_fingerprint'], $prepared['publication_fingerprint']],
    'current token propagated' => [static fn (): mixed => $ok()['current_source_token'], $currentToken],
    'next token propagated' => [static fn (): mixed => $ok()['next_source_token'], $nextToken],
    'source names all match' => [static fn (): mixed => $ok()['matched_source_names'], ['database', 'journal', 'wal']],
    'no stale names' => [static fn (): mixed => $ok()['stale_source_names'], []],
    'no blocked reasons' => [static fn (): mixed => $ok()['blocked_reasons'], []],
    'can publish' => [static fn (): mixed => $ok()['can_publish'], true],
    'source row count' => [static fn (): mixed => count($ok()['source_rows']), 3],
    'database row bytes' => [static fn (): mixed => $ok()['source_rows'][0]['bytes'], strlen($databaseBytes)],
    'journal row bytes' => [static fn (): mixed => $ok()['source_rows'][1]['bytes'], strlen($journalBytes)],
    'wal row bytes' => [static fn (): mixed => $ok()['source_rows'][2]['bytes'], strlen($currentWalBytes)],
    'database row hash' => [static fn (): mixed => $ok()['source_rows'][0]['actual_hash'], hash('sha256', $databaseBytes)],
    'journal row hash' => [static fn (): mixed => $ok()['source_rows'][1]['actual_hash'], hash('sha256', $journalBytes)],
    'wal row hash' => [static fn (): mixed => $ok()['source_rows'][2]['actual_hash'], hash('sha256', $currentWalBytes)],
    'operation names' => [static fn (): mixed => $ok()['operation_names'], ['write', 'truncate', 'sync', 'delete', 'write', 'truncate', 'sync', 'sync_directory']],
    'operation reason first' => [static fn (): mixed => $ok()['operation_reasons'][0], 'publish_hot_journal_savepoint_checkpoint_database_current_source_publish'],
    'operation reason journal' => [static fn (): mixed => $ok()['operation_reasons'][3], 'delete_hot_journal_after_current_source_match_publish'],
    'operation reason directory' => [static fn (): mixed => $ok()['operation_reasons'][7], 'persist_hot_journal_savepoint_checkpoint_current_source_publish'],
    'durable count' => [static fn (): mixed => $ok()['durable_operation_count'], 3],
    'delete count' => [static fn (): mixed => $ok()['delete_count'], 1],
    'write bytes positive' => [static fn (): mixed => $ok()['write_bytes'] > 0, true],
    'truncate bytes positive' => [static fn (): mixed => $ok()['truncate_bytes'] > 0, true],
    'dependency next167 retained' => [static fn (): mixed => in_array('sqlite-wal-hot-journal-savepoint-checkpoint-current-source-next167', $ok()['dependencies'], true), true],
    'dependency publish present' => [static fn (): mixed => in_array('sqlite-wal-hot-journal-savepoint-checkpoint-publish-current-source', $ok()['dependencies'], true), true],
    'dependency vfs admission present' => [static fn (): mixed => in_array('sqlite-vfs-current-source-hash-admission', $ok()['dependencies'], true), true],
    'dependency closure' => [static fn (): mixed => str_contains($ok()['dependency_closure'], 'no new support component needed'), true],
    'non overlap' => [static fn (): mixed => str_contains($ok()['non_overlap'], 'does not repeat next167'), true],
    'stale database status' => [static fn (): mixed => $staleDatabase()['status'], 'wal-hot-journal-savepoint-checkpoint-publish-current-source-blocked'],
    'stale database names' => [static fn (): mixed => $staleDatabase()['stale_source_names'], ['database']],
    'stale database reason' => [static fn (): mixed => $staleDatabase()['blocked_reasons'], ['stale_database_source_hash']],
    'stale database operations empty' => [static fn (): mixed => $staleDatabase()['operations'], []],
    'stale journal names' => [static fn (): mixed => $staleJournal()['stale_source_names'], ['journal']],
    'stale journal reason' => [static fn (): mixed => $staleJournal()['blocked_reasons'], ['stale_journal_source_hash']],
    'stale wal names' => [static fn (): mixed => $staleWal()['stale_source_names'], ['wal']],
    'stale wal reason' => [static fn (): mixed => $staleWal()['blocked_reasons'], ['stale_wal_source_hash']],
    'pinned status' => [static fn (): mixed => $pinned()['status'], 'wal-hot-journal-savepoint-checkpoint-publish-current-source-blocked'],
    'pinned reason' => [static fn (): mixed => $pinned()['blocked_reasons'], ['reader_still_pinned_before_checkpoint_publish']],
    'pinned operations empty' => [static fn (): mixed => $pinned()['operation_names'], []],
    'blocked prepared status' => [static fn (): mixed => $blockedBase()['status'], 'wal-hot-journal-savepoint-checkpoint-publish-current-source-blocked'],
    'blocked prepared reason' => [static fn (): mixed => $blockedBase()['blocked_reasons'], ['prepared_publication_guard_not_ready']],
    'blocked prepared can publish false' => [static fn (): mixed => $blockedBase()['can_publish'], false],
    'implicit expected hashes publish' => [static fn (): mixed => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::publishDurableHotJournalSavepointCheckpointPlan($prepared, $databaseBytes, $journalBytes, $currentWalBytes)['can_publish'], true],
    'implicit expected stale empty' => [static fn (): mixed => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::publishDurableHotJournalSavepointCheckpointPlan($prepared, $databaseBytes, $journalBytes, $currentWalBytes)['stale_source_names'], []],
    'source row order' => [static fn (): mixed => array_column($ok()['source_rows'], 'name'), ['database', 'journal', 'wal']],
    'source row paths' => [static fn (): mixed => array_column($ok()['source_rows'], 'path'), [$databasePath, $databasePath . '-journal', $databasePath . '-wal']],
    'source row matches' => [static fn (): mixed => array_column($ok()['source_rows'], 'matched'), [true, true, true]],
    'operation durable flags' => [static fn (): mixed => array_column($ok()['operations'], 'durable'), [false, false, true, false, false, false, true, true]],
    'operation paths' => [static fn (): mixed => array_column($ok()['operations'], 'path'), [$databasePath, $databasePath, $databasePath, $databasePath . '-journal', $databasePath . '-wal', $databasePath . '-wal', $databasePath . '-wal', dirname($databasePath)]],
];

foreach ($cases as $name => [$callback, $expected]) {
    $tests['wal hot journal savepoint checkpoint current source publish ' . $name] = static function (TestRunner $t) use ($callback, $expected): void {
        $t->same($expected, $callback());
    };
}

$throws = [
    'missing prepared status rejected' => static function () use ($prepared, $databaseBytes, $journalBytes, $currentWalBytes): void {
        $bad = $prepared;
        unset($bad['status']);
        SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::publishDurableHotJournalSavepointCheckpointPlan($bad, $databaseBytes, $journalBytes, $currentWalBytes);
    },
    'missing prepared dependencies rejected' => static function () use ($prepared, $databaseBytes, $journalBytes, $currentWalBytes): void {
        $bad = $prepared;
        unset($bad['dependencies']);
        SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::publishDurableHotJournalSavepointCheckpointPlan($bad, $databaseBytes, $journalBytes, $currentWalBytes);
    },
    'bad dependencies shape rejected' => static function () use ($prepared, $databaseBytes, $journalBytes, $currentWalBytes): void {
        $bad = $prepared;
        $bad['dependencies'] = 'bad';
        SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::publishDurableHotJournalSavepointCheckpointPlan($bad, $databaseBytes, $journalBytes, $currentWalBytes);
    },
    'empty journal rejected' => static fn () => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::publishDurableHotJournalSavepointCheckpointPlan($prepared, $databaseBytes, '', $currentWalBytes),
    'empty wal rejected' => static fn () => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::publishDurableHotJournalSavepointCheckpointPlan($prepared, $databaseBytes, $journalBytes, ''),
];

foreach ($throws as $name => $callback) {
    $tests['wal hot journal savepoint checkpoint current source publish ' . $name] = static function (TestRunner $t) use ($callback): void {
        $t->throws(Throwable::class, $callback);
    };
}

return $tests;
