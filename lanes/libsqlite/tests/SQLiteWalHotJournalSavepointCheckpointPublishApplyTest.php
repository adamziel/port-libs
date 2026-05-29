<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteVfsFileWriter;
use PortLibs\LibSqlite\SQLiteWal;
use PortLibs\LibSqlite\SQLiteWalHeader;
use PortLibs\LibSqlite\SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan;

$tests = [];

$pageSize = 512;
$databasePath = '/srv/www/wp-content/database/wp-next181.sqlite';
$journalPath = $databasePath . '-journal';
$walPath = $databasePath . '-wal';
$page = static fn (string $label): string => str_pad($label, $pageSize, '.', STR_PAD_RIGHT);
$databaseBytes = implode('', [
    $page('next181 dirty schema before reopen'),
    $page('next181 dirty options before checkpoint'),
    $page('next181 dirty plugin retry before savepoint'),
]);
$journalBytes = 'next181-hot-journal';
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
    [1, 0, 'next181 current wal schema draft'],
    [2, 3, 'next181 current wal options commit'],
], 181, 0x18100101, 0x18100102);
$nextWalBytes = $makeWalBytes([
    [3, 3, 'next181 next wal plugin retry commit'],
], 182, 0x18200101, 0x18200102);
$currentWal = SQLiteWal::parse($currentWalBytes, $pageSize, true);
$nextWal = SQLiteWal::parse($nextWalBytes, $pageSize, true);
$bootstrap = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next167Plan(
    $databasePath,
    $databaseBytes,
    $pageSize,
    'plugin-import-next181',
    [2 => $page('next181 hot rollback options')],
    [3 => $page('next181 before plugin retry')],
    $currentWal,
    $currentWalBytes,
    $nextWal,
    $nextWalBytes,
    [1 => ['image' => $page('next181 current wal schema draft'), 'source_id' => 'bootstrap', 'epoch' => 1]],
    [1, 2, 3],
    [['name' => 'bootstrap-current', 'source_id' => 'bootstrap', 'epoch' => 1]],
    null,
    null,
    null,
    'restart',
    2,
    181
);
$currentToken = $bootstrap['current_source_token'];
$nextToken = $bootstrap['next_source_token'];
$prepared = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next167Plan(
    $databasePath,
    $databaseBytes,
    $pageSize,
    'plugin-import-next181',
    [2 => $page('next181 hot rollback options')],
    [3 => $page('next181 before plugin retry')],
    $currentWal,
    $currentWalBytes,
    $nextWal,
    $nextWalBytes,
    [
        1 => ['image' => $page('next181 current wal schema draft'), 'source_id' => $currentToken['id'], 'epoch' => $currentToken['epoch']],
        2 => ['image' => $page('next181 current wal options commit'), 'source_id' => $currentToken['id'], 'epoch' => $currentToken['epoch']],
        3 => ['image' => $page('next181 before plugin retry'), 'source_id' => $currentToken['id'], 'epoch' => $currentToken['epoch']],
    ],
    [1, 2, 3],
    [
        ['name' => 'wp-current-schema', 'source_id' => $currentToken['id'], 'epoch' => $currentToken['epoch']],
        ['name' => 'wp-next-reader', 'source_id' => $nextToken['id'], 'epoch' => $nextToken['epoch']],
    ],
    $currentToken,
    $nextToken,
    null,
    'restart',
    2,
    181
);

$makeRoot = static function () use ($databasePath, $databaseBytes, $journalBytes, $currentWalBytes): string {
    $root = sys_get_temp_dir() . '/port-libs-sqlite-next181-' . bin2hex(random_bytes(4));
    $databaseLocal = $root . '/' . ltrim($databasePath, '/');
    mkdir(dirname($databaseLocal), 0777, true);
    file_put_contents($databaseLocal, $databaseBytes);
    file_put_contents($databaseLocal . '-journal', $journalBytes);
    file_put_contents($databaseLocal . '-wal', $currentWalBytes);

    return $root;
};
$rmRoot = static function (string $root): void {
    if (!is_dir($root)) {
        return;
    }
    $items = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST
    );
    foreach ($items as $item) {
        $item->isDir() ? rmdir($item->getPathname()) : unlink($item->getPathname());
    }
    rmdir($root);
};
$run = static function () use ($makeRoot, $rmRoot, $prepared, $journalPath, $pageSize): array {
    $root = $makeRoot();
    try {
        $writer = new SQLiteVfsFileWriter($root);
        $applied = $writer->publishWalHotJournalSavepointCheckpoint($prepared);
        $databaseLocal = $root . '/' . ltrim((string) $prepared['database_path'], '/');
        $journalLocal = $root . '/' . ltrim($journalPath, '/');
        $databaseAfter = (string) file_get_contents($databaseLocal);
        $journalAfter = is_file($journalLocal) ? (string) file_get_contents($journalLocal) : null;
        $walAfter = (string) file_get_contents($databaseLocal . '-wal');
        $receipt = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next178Plan($prepared, $applied, $databaseAfter, $journalAfter, $walAfter);
        $reopen = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next181Plan(
            $prepared,
            $receipt,
            $databaseAfter,
            $journalAfter,
            $walAfter,
            SQLiteWal::parse($walAfter, $pageSize, true)
        );

        return [$applied, $receipt, $reopen, $databaseAfter, $journalAfter, $walAfter];
    } finally {
        $rmRoot($root);
    }
};
$ok = static fn (): array => $run()[2];
$receipt = static fn (): array => $run()[1];
$staleDatabase = static function () use ($prepared, $receipt, $nextWalBytes, $pageSize): array {
    return SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next181Plan($prepared, $receipt(), 'bad-db', null, $nextWalBytes, SQLiteWal::parse($nextWalBytes, $pageSize, true));
};
$staleJournal = static function () use ($prepared, $receipt, $nextWalBytes, $pageSize): array {
    return SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next181Plan($prepared, $receipt(), $prepared['base_plan']['current_durable']['database_bytes'], 'leftover', $nextWalBytes, SQLiteWal::parse($nextWalBytes, $pageSize, true));
};
$staleWal = static function () use ($prepared, $receipt, $pageSize): array {
    $badWal = $prepared['base_plan']['next_durable']['wal_bytes'] . 'tail';
    return SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next181Plan($prepared, $receipt(), $prepared['base_plan']['current_durable']['database_bytes'], null, $badWal, SQLiteWal::parse($prepared['base_plan']['next_durable']['wal_bytes'], $pageSize, true));
};
$blockedReceipt = static function () use ($prepared, $receipt, $nextWalBytes, $pageSize): array {
    $bad = $receipt();
    $bad['can_publish_receipt'] = false;
    return SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next181Plan($prepared, $bad, $prepared['base_plan']['current_durable']['database_bytes'], null, $nextWalBytes, SQLiteWal::parse($nextWalBytes, $pageSize, true));
};
$badWalReopen = static function () use ($prepared, $receipt, $nextWalBytes): array {
    return SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next181Plan($prepared, $receipt(), $prepared['base_plan']['current_durable']['database_bytes'], null, $nextWalBytes, SQLiteWal::parse($nextWalBytes, 512, false));
};

$cases = [
    'status' => [static fn (): mixed => $ok()['status'], 'wal-hot-journal-savepoint-checkpoint-current-source-next181'],
    'reason' => [static fn (): mixed => $ok()['reason'], 'reopened_files_and_wal_frames_confirm_post_apply_checkpoint_publication'],
    'receipt publishable' => [static fn (): mixed => $ok()['receipt_publishable'], true],
    'can reopen publish' => [static fn (): mixed => $ok()['can_reopen_publish'], true],
    'database path' => [static fn (): mixed => $ok()['database_path'], $databasePath],
    'journal path' => [static fn (): mixed => $ok()['journal_path'], $journalPath],
    'wal path' => [static fn (): mixed => $ok()['wal_path'], $walPath],
    'matched source names' => [static fn (): mixed => $ok()['matched_source_names'], ['database', 'journal', 'wal']],
    'stale source names empty' => [static fn (): mixed => $ok()['stale_source_names'], []],
    'blocked reasons empty' => [static fn (): mixed => $ok()['blocked_reasons'], []],
    'source row count' => [static fn (): mixed => count($ok()['source_rows']), 3],
    'database row length' => [static fn (): mixed => $ok()['source_rows'][0]['actual_length'], strlen($prepared['base_plan']['current_durable']['database_bytes'])],
    'journal row absent' => [static fn (): mixed => $ok()['source_rows'][1]['matches'], true],
    'journal row actual length' => [static fn (): mixed => $ok()['source_rows'][1]['actual_length'], null],
    'wal row length' => [static fn (): mixed => $ok()['source_rows'][2]['actual_length'], strlen($prepared['base_plan']['next_durable']['wal_bytes'])],
    'wal checkpoint sequence' => [static fn (): mixed => $ok()['wal_checkpoint_sequence'], 182],
    'wal frame count' => [static fn (): mixed => $ok()['wal_frame_count'], 1],
    'wal commit frame count' => [static fn (): mixed => $ok()['wal_commit_frame_count'], 1],
    'wal last commit frame' => [static fn (): mixed => $ok()['wal_last_commit_frame'], 1],
    'wal last commit page count' => [static fn (): mixed => $ok()['wal_last_commit_page_count'], 3],
    'wal checksums validated' => [static fn (): mixed => $ok()['wal_checksums_validated'], true],
    'receipt digest length' => [static fn (): mixed => strlen((string) $ok()['receipt_digest']), 64],
    'reopen digest length' => [static fn (): mixed => strlen($ok()['reopen_digest']), 64],
    'dependency next181' => [static fn (): mixed => in_array('sqlite-wal-hot-journal-savepoint-checkpoint-current-source-next181', $ok()['dependencies'], true), true],
    'dependency reopen' => [static fn (): mixed => in_array('sqlite-wal-post-apply-reopen-validated', $ok()['dependencies'], true), true],
    'wordpress dependency' => [static fn (): mixed => in_array('wordpress-import-hot-journal-checkpoint-reopen-wal-frames', $ok()['dependencies'], true), true],
    'dependency closure' => [static fn (): mixed => str_contains($ok()['dependency_closure'], 'no new support component needed'), true],
    'non overlap' => [static fn (): mixed => str_contains($ok()['non_overlap'], 'does not repeat publish-apply'), true],
    'receipt status' => [static fn (): mixed => $receipt()['status'], 'wal-hot-journal-savepoint-checkpoint-current-source-next178'],
    'stale database blocked' => [static fn (): mixed => $staleDatabase()['status'], 'wal-hot-journal-savepoint-checkpoint-current-source-blocked-next181'],
    'stale database names' => [static fn (): mixed => $staleDatabase()['stale_source_names'], ['database']],
    'stale database reasons' => [static fn (): mixed => $staleDatabase()['blocked_reasons'], ['reopened_database_does_not_match_durable_payload']],
    'stale journal blocked' => [static fn (): mixed => $staleJournal()['status'], 'wal-hot-journal-savepoint-checkpoint-current-source-blocked-next181'],
    'stale journal names' => [static fn (): mixed => $staleJournal()['stale_source_names'], ['journal']],
    'stale journal reasons' => [static fn (): mixed => $staleJournal()['blocked_reasons'], ['reopened_journal_does_not_match_durable_payload']],
    'stale wal blocked' => [static fn (): mixed => $staleWal()['status'], 'wal-hot-journal-savepoint-checkpoint-current-source-blocked-next181'],
    'stale wal names' => [static fn (): mixed => $staleWal()['stale_source_names'], ['wal']],
    'blocked receipt reason' => [static fn (): mixed => $blockedReceipt()['blocked_reasons'], ['post_apply_receipt_not_publishable']],
    'bad wal reopen reason' => [static fn (): mixed => $badWalReopen()['blocked_reasons'], ['reopened_wal_checksum_or_header_mismatch']],
    'missing prepared rejected' => [static function () use ($prepared, $receipt, $nextWalBytes, $pageSize): string {
        $bad = $prepared;
        unset($bad['base_plan']);
        try {
            SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next181Plan($bad, $receipt(), 'db', null, $nextWalBytes, SQLiteWal::parse($nextWalBytes, $pageSize, true));
        } catch (Throwable $throwable) {
            return $throwable->getMessage();
        }

        return 'no-error';
    }, 'SQLite WAL hot-journal savepoint checkpoint current-source next181 missing prepared base_plan'],
    'missing receipt rejected' => [static function () use ($prepared, $nextWalBytes, $pageSize): string {
        try {
            SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next181Plan($prepared, [], 'db', null, $nextWalBytes, SQLiteWal::parse($nextWalBytes, $pageSize, true));
        } catch (Throwable $throwable) {
            return $throwable->getMessage();
        }

        return 'no-error';
    }, 'SQLite WAL hot-journal savepoint checkpoint current-source next181 missing receipt status'],
];

foreach ($cases as $name => [$callback, $expected]) {
    $tests['wal hot journal savepoint checkpoint current source next181 ' . $name] = static function (TestRunner $t) use ($callback, $expected): void {
        $t->same($expected, $callback());
    };
}

return $tests;
