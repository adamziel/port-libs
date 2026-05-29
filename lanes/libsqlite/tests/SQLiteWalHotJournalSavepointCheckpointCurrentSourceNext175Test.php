<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteVfsFileWriter;
use PortLibs\LibSqlite\SQLiteWal;
use PortLibs\LibSqlite\SQLiteWalHeader;
use PortLibs\LibSqlite\SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan;

$tests = [];

$pageSize = 512;
$databasePath = '/srv/www/wp-content/database/wp-next175.sqlite';
$page = static fn (string $label): string => str_pad($label, $pageSize, '.', STR_PAD_RIGHT);
$databaseBytes = implode('', [
    $page('next175 dirty schema before hot rollback'),
    $page('next175 dirty options root before hot rollback'),
    $page('next175 dirty active plugins before savepoint'),
    $page('next175 dirty autoload index before hot rollback'),
    $page('next175 dirty cron before savepoint'),
]);
$journalBytes = 'next175-hot-journal-bytes';
$hot = [
    2 => $page('next175 hot rollback options root'),
    4 => $page('next175 hot rollback autoload index'),
];
$savepointBefore = [
    3 => $page('next175 before active plugins retry'),
    5 => $page('next175 before cron retry'),
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
    [1, 0, 'next175 current wal schema draft'],
    [2, 5, 'next175 current wal options commit'],
    [4, 0, 'next175 current wal autoload draft'],
    [5, 5, 'next175 current wal cron commit'],
], 175, 0x17500101, 0x17500102);
$nextWalBytes = $makeWalBytes([
    [3, 0, 'next175 next wal active plugins retry draft'],
    [5, 5, 'next175 next wal cron commit'],
], 176, 0x17600101, 0x17600102);
$currentWal = SQLiteWal::parse($currentWalBytes, $pageSize, true);
$nextWal = SQLiteWal::parse($nextWalBytes, $pageSize, true);

$bootstrap = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next167Plan(
    $databasePath,
    $databaseBytes,
    $pageSize,
    'plugin-import-next175',
    $hot,
    $savepointBefore,
    $currentWal,
    $currentWalBytes,
    $nextWal,
    $nextWalBytes,
    [1 => ['image' => $page('next175 current wal schema draft'), 'source_id' => 'bootstrap', 'epoch' => 1]],
    [1, 2, 3, 4, 5],
    [
        ['name' => 'bootstrap-current', 'source_id' => 'bootstrap', 'epoch' => 1],
        ['name' => 'bootstrap-stale', 'source_id' => 'old-bootstrap', 'epoch' => 1],
    ],
    null,
    null,
    null,
    'restart',
    4,
    175
);
$currentToken = $bootstrap['current_source_token'];
$nextToken = $bootstrap['next_source_token'];
$prepared = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next167Plan(
    $databasePath,
    $databaseBytes,
    $pageSize,
    'plugin-import-next175',
    $hot,
    $savepointBefore,
    $currentWal,
    $currentWalBytes,
    $nextWal,
    $nextWalBytes,
    [
        1 => ['image' => $page('next175 current wal schema draft'), 'source_id' => $currentToken['id'], 'epoch' => $currentToken['epoch']],
        2 => ['image' => $page('next175 current wal options commit'), 'source_id' => 'old-token', 'epoch' => $currentToken['epoch']],
        3 => ['image' => $savepointBefore[3], 'source_id' => $currentToken['id'], 'epoch' => $currentToken['epoch'] - 1],
        4 => ['image' => $page('next175 stale autoload cache'), 'source_id' => $currentToken['id'], 'epoch' => $currentToken['epoch']],
        5 => ['image' => $page('next175 current wal cron commit'), 'source_id' => $currentToken['id'], 'epoch' => $currentToken['epoch'], 'dirty' => true],
    ],
    [1, 2, 3, 4, 5],
    [
        ['name' => 'wp-current-schema', 'source_id' => $currentToken['id'], 'epoch' => $currentToken['epoch']],
        ['name' => 'wp-pinned-options', 'source_id' => $currentToken['id'], 'epoch' => $currentToken['epoch'], 'pinned' => true],
        ['name' => 'wp-stale-token', 'source_id' => 'old-token', 'epoch' => $currentToken['epoch']],
        ['name' => 'wp-stale-epoch', 'source_id' => $currentToken['id'], 'epoch' => $currentToken['epoch'] - 1],
        ['name' => 'wp-next-reader', 'source_id' => $nextToken['id'], 'epoch' => $nextToken['epoch']],
        ['name' => 'wp-dirty-reader', 'source_id' => $currentToken['id'], 'epoch' => $currentToken['epoch'], 'dirty' => true],
    ],
    $currentToken,
    $nextToken,
    null,
    'restart',
    4,
    175
);

$makeRoot = static function () use ($databasePath, $databaseBytes, $journalBytes, $currentWalBytes): string {
    $root = sys_get_temp_dir() . '/port-libs-sqlite-next175-' . bin2hex(random_bytes(4));
    $databaseLocal = $root . '/' . ltrim($databasePath, '/');
    $dir = dirname($databaseLocal);
    if (!is_dir($dir) && !mkdir($dir, 0777, true) && !is_dir($dir)) {
        throw new RuntimeException('Unable to create next175 temp database directory');
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
$run = static function (?string $expectedDatabaseHash = null, ?string $expectedJournalHash = null, ?string $expectedWalHash = null, bool $readerDrained = true) use ($makeRoot, $rmRoot, $prepared): array {
    $root = $makeRoot();
    try {
        $writer = new SQLiteVfsFileWriter($root);
        $result = $writer->applyWalHotJournalSavepointCheckpointCurrentSourceNext175($prepared, $expectedDatabaseHash, $expectedJournalHash, $expectedWalHash, $readerDrained);
        $databaseLocal = $root . '/' . ltrim((string) $prepared['database_path'], '/');
        $result['database_file_bytes'] = (string) file_get_contents($databaseLocal);
        $result['journal_exists'] = is_file($databaseLocal . '-journal');
        $result['wal_file_bytes'] = (string) file_get_contents($databaseLocal . '-wal');

        return $result;
    } finally {
        $rmRoot($root);
    }
};

$applied = static fn (): array => $run();
$blockedDatabase = static fn (): array => $run(str_repeat('0', 64));
$blockedJournal = static fn (): array => $run(null, str_repeat('1', 64));
$blockedWal = static fn (): array => $run(null, null, str_repeat('2', 64));
$blockedReader = static fn (): array => $run(null, null, null, false);
$readOnly = static function () use ($makeRoot, $rmRoot, $prepared): string {
    $root = $makeRoot();
    try {
        $writer = new SQLiteVfsFileWriter($root, true);
        $writer->applyWalHotJournalSavepointCheckpointCurrentSourceNext175($prepared);
    } catch (Throwable $throwable) {
        return $throwable::class . ':' . $throwable->getMessage();
    } finally {
        $rmRoot($root);
    }

    return 'no-error';
};

$cases = [
    'status' => [static fn (): mixed => $applied()['status'], 'applied'],
    'applied count' => [static fn (): mixed => $applied()['applied'], 8],
    'bytes written' => [static fn (): mixed => $applied()['bytes_written'], strlen($prepared['base_plan']['current_durable']['database_bytes']) + strlen($prepared['base_plan']['next_durable']['wal_bytes'])],
    'bytes truncated' => [static fn (): mixed => $applied()['bytes_truncated'], strlen($prepared['base_plan']['current_durable']['database_bytes']) + strlen($prepared['base_plan']['next_durable']['wal_bytes'])],
    'journal deleted' => [static fn (): mixed => $applied()['files_deleted'], 1],
    'durable syncs' => [static fn (): mixed => $applied()['durable_syncs'], 2],
    'directory syncs' => [static fn (): mixed => $applied()['directory_syncs'], 1],
    'atomic flag' => [static fn (): mixed => $applied()['atomic'], true],
    'publication status' => [static fn (): mixed => $applied()['publication']['status'], 'wal-hot-journal-savepoint-checkpoint-current-source-next173'],
    'publication can publish' => [static fn (): mixed => $applied()['publication']['can_publish'], true],
    'publication reason' => [static fn (): mixed => $applied()['publication']['reason'], 'filesystem_current_source_hashes_match_guarded_wal_checkpoint_publication'],
    'publication operation names' => [static fn (): mixed => $applied()['publication']['operation_names'], ['write', 'truncate', 'sync', 'delete', 'write', 'truncate', 'sync', 'sync_directory']],
    'publication durable count' => [static fn (): mixed => $applied()['publication']['durable_operation_count'], 3],
    'publication delete count' => [static fn (): mixed => $applied()['publication']['delete_count'], 1],
    'publication matched sources' => [static fn (): mixed => $applied()['publication']['matched_source_names'], ['database', 'journal', 'wal']],
    'publication stale sources' => [static fn (): mixed => $applied()['publication']['stale_source_names'], []],
    'database operation path' => [static fn (): mixed => $applied()['operations'][0]['path'], $databasePath],
    'database operation reason' => [static fn (): mixed => $applied()['operations'][0]['reason'], 'publish_hot_journal_savepoint_checkpoint_database_current_source_next173'],
    'database truncate reason' => [static fn (): mixed => $applied()['operations'][1]['reason'], 'trim_checkpoint_database_current_source_next173'],
    'database sync reason' => [static fn (): mixed => $applied()['operations'][2]['reason'], 'sync_checkpoint_database_current_source_next173'],
    'journal delete path' => [static fn (): mixed => $applied()['operations'][3]['path'], $databasePath . '-journal'],
    'wal write path' => [static fn (): mixed => $applied()['operations'][4]['path'], $databasePath . '-wal'],
    'wal sync reason' => [static fn (): mixed => $applied()['operations'][6]['reason'], 'sync_next_wal_after_checkpoint_current_source_next173'],
    'directory sync path' => [static fn (): mixed => $applied()['operations'][7]['path'], dirname($databasePath)],
    'database bytes applied' => [static fn (): mixed => $applied()['database_file_bytes'], $prepared['base_plan']['current_durable']['database_bytes']],
    'database contains checkpoint schema' => [static fn (): mixed => str_contains($applied()['database_file_bytes'], 'next175 current wal schema draft'), true],
    'database contains current committed options' => [static fn (): mixed => str_contains($applied()['database_file_bytes'], 'next175 current wal options commit'), true],
    'database contains savepoint before page' => [static fn (): mixed => str_contains($applied()['database_file_bytes'], 'next175 before active plugins retry'), true],
    'database excludes dirty page' => [static fn (): mixed => str_contains($applied()['database_file_bytes'], 'dirty active plugins'), false],
    'journal file removed' => [static fn (): mixed => $applied()['journal_exists'], false],
    'wal bytes applied' => [static fn (): mixed => $applied()['wal_file_bytes'], $prepared['base_plan']['next_durable']['wal_bytes']],
    'wal length applied' => [static fn (): mixed => strlen($applied()['wal_file_bytes']), strlen($prepared['base_plan']['next_durable']['wal_bytes'])],
    'wal parse frame count' => [static fn (): mixed => SQLiteWal::parse($applied()['wal_file_bytes'], $pageSize, true)->frameCount(), SQLiteWal::parse($prepared['base_plan']['next_durable']['wal_bytes'], $pageSize, true)->frameCount()],
    'source database bytes' => [static fn (): mixed => $applied()['current_source']['database_bytes'], strlen($databaseBytes)],
    'source journal bytes' => [static fn (): mixed => $applied()['current_source']['journal_bytes'], strlen($journalBytes)],
    'source wal bytes' => [static fn (): mixed => $applied()['current_source']['wal_bytes'], strlen($currentWalBytes)],
    'dependency next175' => [static fn (): mixed => in_array('sqlite-wal-hot-journal-savepoint-checkpoint-current-source-vfs-apply-next175', $applied()['dependencies'], true), true],
    'dependency next173' => [static fn (): mixed => in_array('sqlite-wal-hot-journal-savepoint-checkpoint-current-source-next173', $applied()['dependencies'], true), true],
    'dependency vfs application' => [static fn (): mixed => in_array('vfs-file-handle-write-application', $applied()['dependencies'], true), true],
    'dependency atomic rollback' => [static fn (): mixed => in_array('vfs-atomic-rollback-on-write-failure', $applied()['dependencies'], true), true],
    'dependency closure' => [static fn (): mixed => str_contains($applied()['publication']['dependency_closure'], 'no new support component needed'), true],
    'non overlap' => [static fn (): mixed => str_contains($applied()['publication']['non_overlap'], 'does not repeat next167'), true],
    'blocked database status' => [static fn (): mixed => $blockedDatabase()['status'], 'blocked'],
    'blocked database stale source' => [static fn (): mixed => $blockedDatabase()['publication']['stale_source_names'], ['database']],
    'blocked database applied zero' => [static fn (): mixed => $blockedDatabase()['applied'], 0],
    'blocked journal stale source' => [static fn (): mixed => $blockedJournal()['publication']['stale_source_names'], ['journal']],
    'blocked wal stale source' => [static fn (): mixed => $blockedWal()['publication']['stale_source_names'], ['wal']],
    'blocked reader reason' => [static fn (): mixed => $blockedReader()['publication']['blocked_reasons'], ['reader_still_pinned_before_checkpoint_publish']],
    'blocked reader no delete' => [static fn (): mixed => $blockedReader()['journal_exists'], true],
    'blocked reader keeps database' => [static fn (): mixed => $blockedReader()['database_file_bytes'], $databaseBytes],
    'blocked reader keeps wal' => [static fn (): mixed => $blockedReader()['wal_file_bytes'], $currentWalBytes],
    'read only rejected' => [static fn (): mixed => str_contains($readOnly(), 'SQLite VFS file writer requires a writable handle'), true],
    'prepared missing path rejected' => [static function () use ($prepared, $makeRoot, $rmRoot): string {
        $root = $makeRoot();
        try {
            $bad = $prepared;
            unset($bad['wal_path']);
            (new SQLiteVfsFileWriter($root))->applyWalHotJournalSavepointCheckpointCurrentSourceNext175($bad);
        } catch (Throwable $throwable) {
            return $throwable->getMessage();
        } finally {
            $rmRoot($root);
        }

        return 'no-error';
    }, 'SQLite WAL hot-journal savepoint checkpoint current-source next175 missing prepared wal_path'],
    'missing source file rejected' => [static function () use ($prepared): bool {
        try {
            (new SQLiteVfsFileWriter(sys_get_temp_dir() . '/missing-next175-' . bin2hex(random_bytes(3))))->applyWalHotJournalSavepointCheckpointCurrentSourceNext175($prepared);
        } catch (Throwable $throwable) {
            return str_contains($throwable->getMessage(), 'SQLite WAL hot-journal savepoint checkpoint current-source next175 database is missing');
        }

        return 'no-error';
    }, true],
];

foreach ($cases as $name => [$callback, $expected]) {
    $tests['wal hot journal savepoint checkpoint current source next175 ' . $name] = static function (TestRunner $t) use ($callback, $expected): void {
        $t->same($expected, $callback());
    };
}

return $tests;
