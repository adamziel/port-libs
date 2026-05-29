<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteVfsFileWriter;
use PortLibs\LibSqlite\SQLiteWal;
use PortLibs\LibSqlite\SQLiteWalHeader;
use PortLibs\LibSqlite\SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan;

$tests = [];

$pageSize = 512;
$databasePath = '/srv/www/wp-content/database/wp-next178.sqlite';
$journalPath = $databasePath . '-journal';
$walPath = $databasePath . '-wal';
$page = static fn (string $label): string => str_pad($label, $pageSize, '.', STR_PAD_RIGHT);
$databaseBytes = implode('', [
    $page('next178 dirty schema before reopen'),
    $page('next178 dirty options root before reopen'),
    $page('next178 dirty active plugins before checkpoint'),
    $page('next178 dirty autoload index before reopen'),
]);
$journalBytes = 'next178-hot-journal-bytes';
$hot = [
    2 => $page('next178 hot rollback options root'),
    4 => $page('next178 hot rollback autoload index'),
];
$savepointBefore = [
    3 => $page('next178 before active plugins retry'),
];

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
    [1, 0, 'next178 current wal schema draft'],
    [2, 4, 'next178 current wal options commit'],
    [4, 4, 'next178 current wal autoload commit'],
], 178, 0x17800101, 0x17800102);
$nextWalBytes = $makeWalBytes([
    [3, 4, 'next178 next wal active plugins retry'],
], 179, 0x17900101, 0x17900102);
$currentWal = SQLiteWal::parse($currentWalBytes, $pageSize, true);
$nextWal = SQLiteWal::parse($nextWalBytes, $pageSize, true);

$bootstrap = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next167Plan(
    $databasePath,
    $databaseBytes,
    $pageSize,
    'plugin-import-next178',
    $hot,
    $savepointBefore,
    $currentWal,
    $currentWalBytes,
    $nextWal,
    $nextWalBytes,
    [1 => ['image' => $page('next178 current wal schema draft'), 'source_id' => 'bootstrap', 'epoch' => 1]],
    [1, 2, 3, 4],
    [
        ['name' => 'bootstrap-current', 'source_id' => 'bootstrap', 'epoch' => 1],
        ['name' => 'bootstrap-stale', 'source_id' => 'old-bootstrap', 'epoch' => 1],
    ],
    null,
    null,
    null,
    'restart',
    3,
    178
);
$currentToken = $bootstrap['current_source_token'];
$nextToken = $bootstrap['next_source_token'];
$prepared = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next167Plan(
    $databasePath,
    $databaseBytes,
    $pageSize,
    'plugin-import-next178',
    $hot,
    $savepointBefore,
    $currentWal,
    $currentWalBytes,
    $nextWal,
    $nextWalBytes,
    [
        1 => ['image' => $page('next178 current wal schema draft'), 'source_id' => $currentToken['id'], 'epoch' => $currentToken['epoch']],
        2 => ['image' => $page('next178 current wal options commit'), 'source_id' => $currentToken['id'], 'epoch' => $currentToken['epoch']],
        3 => ['image' => $savepointBefore[3], 'source_id' => $currentToken['id'], 'epoch' => $currentToken['epoch']],
        4 => ['image' => $page('next178 current wal autoload commit'), 'source_id' => $currentToken['id'], 'epoch' => $currentToken['epoch']],
    ],
    [1, 2, 3, 4],
    [
        ['name' => 'wp-current-schema', 'source_id' => $currentToken['id'], 'epoch' => $currentToken['epoch']],
        ['name' => 'wp-next-reader', 'source_id' => $nextToken['id'], 'epoch' => $nextToken['epoch']],
    ],
    $currentToken,
    $nextToken,
    null,
    'restart',
    3,
    178
);

$makeRoot = static function () use ($databasePath, $databaseBytes, $journalBytes, $currentWalBytes): string {
    $root = sys_get_temp_dir() . '/port-libs-sqlite-next178-' . bin2hex(random_bytes(4));
    $databaseLocal = $root . '/' . ltrim($databasePath, '/');
    $dir = dirname($databaseLocal);
    if (!is_dir($dir) && !mkdir($dir, 0777, true) && !is_dir($dir)) {
        throw new RuntimeException('Unable to create next178 temp database directory');
    }
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
$run = static function () use ($makeRoot, $rmRoot, $prepared, $journalPath): array {
    $root = $makeRoot();
    try {
        $writer = new SQLiteVfsFileWriter($root);
        $applied = $writer->applyWalHotJournalSavepointCheckpointCurrentSourceNext175($prepared);
        $databaseLocal = $root . '/' . ltrim((string) $prepared['database_path'], '/');
        $journalLocal = $root . '/' . ltrim($journalPath, '/');
        $receipt = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next178Plan(
            $prepared,
            $applied,
            (string) file_get_contents($databaseLocal),
            is_file($journalLocal) ? (string) file_get_contents($journalLocal) : null,
            (string) file_get_contents($databaseLocal . '-wal')
        );

        return [$applied, $receipt];
    } finally {
        $rmRoot($root);
    }
};
$ok = static fn (): array => $run()[1];
$applied = static fn (): array => $run()[0];
$staleDatabase = static function () use ($prepared, $run): array {
    [, $receipt] = $run();
    return SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next178Plan($prepared, $receipt + ['status' => 'applied', 'publication' => ['can_publish' => true], 'operations' => [], 'durable_syncs' => 2, 'directory_syncs' => 1], 'bad', null, $prepared['base_plan']['next_durable']['wal_bytes']);
};
$staleJournal = static fn (): array => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next178Plan($prepared, $applied(), $prepared['base_plan']['current_durable']['database_bytes'], 'leftover-journal', $prepared['base_plan']['next_durable']['wal_bytes']);
$staleWal = static fn (): array => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next178Plan($prepared, $applied(), $prepared['base_plan']['current_durable']['database_bytes'], null, 'bad-wal');
$badOrder = static function () use ($prepared, $applied): array {
    $receipt = $applied();
    $receipt['operations'] = array_reverse($receipt['operations']);
    return SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next178Plan($prepared, $receipt, $prepared['base_plan']['current_durable']['database_bytes'], null, $prepared['base_plan']['next_durable']['wal_bytes']);
};
$blockedApply = static function () use ($prepared, $applied): array {
    $receipt = $applied();
    $receipt['status'] = 'blocked';
    return SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next178Plan($prepared, $receipt, $prepared['base_plan']['current_durable']['database_bytes'], null, $prepared['base_plan']['next_durable']['wal_bytes']);
};

$cases = [
    'status' => [static fn (): mixed => $ok()['status'], 'wal-hot-journal-savepoint-checkpoint-current-source-next178'],
    'reason' => [static fn (): mixed => $ok()['reason'], 'post_apply_files_match_guarded_checkpoint_receipt'],
    'can publish receipt' => [static fn (): mixed => $ok()['can_publish_receipt'], true],
    'database path' => [static fn (): mixed => $ok()['database_path'], $databasePath],
    'journal path' => [static fn (): mixed => $ok()['journal_path'], $journalPath],
    'wal path' => [static fn (): mixed => $ok()['wal_path'], $walPath],
    'matched source names' => [static fn (): mixed => $ok()['matched_source_names'], ['database', 'journal', 'wal']],
    'stale source names empty' => [static fn (): mixed => $ok()['stale_source_names'], []],
    'blocked reasons empty' => [static fn (): mixed => $ok()['blocked_reasons'], []],
    'source rows count' => [static fn (): mixed => count($ok()['source_rows']), 3],
    'database row name' => [static fn (): mixed => $ok()['source_rows'][0]['name'], 'database'],
    'database row path' => [static fn (): mixed => $ok()['source_rows'][0]['path'], $databasePath],
    'database row matches' => [static fn (): mixed => $ok()['source_rows'][0]['matches'], true],
    'database row expected length' => [static fn (): mixed => $ok()['source_rows'][0]['expected_length'], strlen($prepared['base_plan']['current_durable']['database_bytes'])],
    'database row actual length' => [static fn (): mixed => $ok()['source_rows'][0]['actual_length'], strlen($prepared['base_plan']['current_durable']['database_bytes'])],
    'journal row name' => [static fn (): mixed => $ok()['source_rows'][1]['name'], 'journal'],
    'journal row removed' => [static fn (): mixed => $ok()['source_rows'][1]['matches'], true],
    'journal row actual length null' => [static fn (): mixed => $ok()['source_rows'][1]['actual_length'], null],
    'wal row name' => [static fn (): mixed => $ok()['source_rows'][2]['name'], 'wal'],
    'wal row matches' => [static fn (): mixed => $ok()['source_rows'][2]['matches'], true],
    'wal row expected length' => [static fn (): mixed => $ok()['source_rows'][2]['expected_length'], strlen($prepared['base_plan']['next_durable']['wal_bytes'])],
    'operation names' => [static fn (): mixed => $ok()['operation_names'], ['write', 'truncate', 'sync', 'delete', 'write', 'truncate', 'sync', 'sync_directory']],
    'operation order matches' => [static fn (): mixed => $ok()['operation_order_matches'], true],
    'durable syncs' => [static fn (): mixed => $ok()['durable_syncs'], 2],
    'directory syncs' => [static fn (): mixed => $ok()['directory_syncs'], 1],
    'database sha length' => [static fn (): mixed => strlen($ok()['database_sha256']), 64],
    'wal sha length' => [static fn (): mixed => strlen($ok()['wal_sha256']), 64],
    'receipt digest length' => [static fn (): mixed => strlen($ok()['receipt_digest']), 64],
    'dependency next178' => [static fn (): mixed => in_array('sqlite-wal-hot-journal-savepoint-checkpoint-current-source-next178', $ok()['dependencies'], true), true],
    'dependency receipt' => [static fn (): mixed => in_array('sqlite-wal-hot-journal-post-apply-receipt', $ok()['dependencies'], true), true],
    'wordpress dependency' => [static fn (): mixed => in_array('wordpress-import-hot-journal-checkpoint-reopen-receipt', $ok()['dependencies'], true), true],
    'dependency closure' => [static fn (): mixed => str_contains($ok()['dependency_closure'], 'no new support component needed'), true],
    'non overlap' => [static fn (): mixed => str_contains($ok()['non_overlap'], 'does not repeat next173'), true],
    'applied status' => [static fn (): mixed => $applied()['status'], 'applied'],
    'applied delete count' => [static fn (): mixed => $applied()['files_deleted'], 1],
    'applied operation count' => [static fn (): mixed => count($applied()['operations']), 8],
    'applied publication status' => [static fn (): mixed => $applied()['publication']['status'], 'wal-hot-journal-savepoint-checkpoint-current-source-next173'],
    'stale database blocked' => [static fn (): mixed => $staleDatabase()['status'], 'wal-hot-journal-savepoint-checkpoint-current-source-blocked-next178'],
    'stale database names' => [static fn (): mixed => $staleDatabase()['stale_source_names'], ['database']],
    'stale database reason' => [static fn (): mixed => $staleDatabase()['blocked_reasons'], ['vfs_publication_not_applied', 'stale_database_after_apply', 'durable_operation_order_mismatch']],
    'stale journal blocked' => [static fn (): mixed => $staleJournal()['status'], 'wal-hot-journal-savepoint-checkpoint-current-source-blocked-next178'],
    'stale journal names' => [static fn (): mixed => $staleJournal()['stale_source_names'], ['journal']],
    'stale journal reason' => [static fn (): mixed => $staleJournal()['blocked_reasons'], ['stale_journal_after_apply']],
    'stale wal blocked' => [static fn (): mixed => $staleWal()['status'], 'wal-hot-journal-savepoint-checkpoint-current-source-blocked-next178'],
    'stale wal names' => [static fn (): mixed => $staleWal()['stale_source_names'], ['wal']],
    'bad order blocked' => [static fn (): mixed => $badOrder()['blocked_reasons'], ['durable_operation_order_mismatch']],
    'bad order names reversed first' => [static fn (): mixed => $badOrder()['operation_names'][0], 'sync_directory'],
    'blocked apply reason' => [static fn (): mixed => $blockedApply()['blocked_reasons'], ['vfs_publication_not_applied']],
    'missing prepared rejected' => [static function () use ($prepared, $applied): string {
        $bad = $prepared;
        unset($bad['base_plan']);
        try {
            SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next178Plan($bad, $applied(), 'db', null, 'wal');
        } catch (Throwable $throwable) {
            return $throwable->getMessage();
        }

        return 'no-error';
    }, 'SQLite WAL hot-journal savepoint checkpoint current-source next178 missing prepared base_plan'],
    'missing applied rejected' => [static function () use ($prepared): string {
        try {
            SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next178Plan($prepared, [], 'db', null, 'wal');
        } catch (Throwable $throwable) {
            return $throwable->getMessage();
        }

        return 'no-error';
    }, 'SQLite WAL hot-journal savepoint checkpoint current-source next178 missing applied status'],
];

foreach ($cases as $name => [$callback, $expected]) {
    $tests['wal hot journal savepoint checkpoint current source next178 ' . $name] = static function (TestRunner $t) use ($callback, $expected): void {
        $t->same($expected, $callback());
    };
}

return $tests;
