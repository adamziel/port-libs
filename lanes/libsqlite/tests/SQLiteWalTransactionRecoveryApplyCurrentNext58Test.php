<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteVfsFileWriter;
use PortLibs\LibSqlite\SQLiteWal;
use PortLibs\LibSqlite\SQLiteWalHeader;

$tests = [];

$pageSize = 512;
$page = static fn (string $label): string => str_pad($label, $pageSize, '.', STR_PAD_RIGHT);
$databasePath = '/wp-content/database/.ht.sqlite';

$makeWal = static function (array $frames) use ($pageSize): string {
    $salt1 = 0x5868abcd;
    $salt2 = 0x78563412;
    $headerPrefix = pack('N*', SQLiteWalHeader::MAGIC_BIG_ENDIAN, 3007000, $pageSize, 58, $salt1, $salt2);
    $seed = SQLiteWal::checksumPair($headerPrefix, false);
    $bytes = $headerPrefix . pack('N*', $seed[0], $seed[1]);

    foreach ($frames as [$pageNumber, $commitPageCount, $image]) {
        $framePrefix = pack('N*', $pageNumber, $commitPageCount, $salt1, $salt2);
        $seed = SQLiteWal::checksumPair(substr($framePrefix, 0, 8) . $image, false, $seed[0], $seed[1]);
        $bytes .= $framePrefix . pack('N*', $seed[0], $seed[1]) . $image;
    }

    return $bytes;
};

$baseDatabase = $page('wp db header before wal tx recovery')
    . $page('base siteurl option before wal')
    . $page('base active_plugins option before wal')
    . $page('base autoload index before wal');
$committedThenDraftWal = $makeWal([
    [2, 0, $page('draft siteurl first frame')],
    [2, 4, $page('committed siteurl option frame')],
    [3, 0, $page('draft active_plugins after commit')],
    [4, 0, $page('draft autoload index after commit')],
]);
$twoCommitThenDraftWal = $makeWal([
    [2, 0, $page('first transaction siteurl draft')],
    [2, 4, $page('first transaction siteurl commit')],
    [3, 0, $page('second transaction plugin draft')],
    [3, 4, $page('second transaction plugin commit')],
    [4, 0, $page('third transaction uncommitted index')],
]);
$uncommittedOnlyWal = $makeWal([
    [2, 0, $page('only uncommitted siteurl draft')],
    [3, 0, $page('only uncommitted plugin draft')],
]);
$truncatedAfterCommitWal = substr($committedThenDraftWal, 0, 32 + (2 * (24 + $pageSize)) + 77);
$corruptAfterCommitWal = substr($committedThenDraftWal, 0, -4) . 'xxxx';
$badHeaderWal = substr_replace($committedThenDraftWal, "\xff", 31, 1);

$apply = static function (string $walBytes, string $databaseBytes = null, string $path = null, bool $readOnly = false) use ($baseDatabase, $databasePath): array {
    $root = sys_get_temp_dir() . '/port-libsqlite-wal-tx-boundary-' . bin2hex(random_bytes(4));
    $targetPath = $path ?? $databasePath;
    $localDatabase = $root . $targetPath;
    $localWal = $localDatabase . '-wal';
    if (!is_dir(dirname($localDatabase)) && !mkdir(dirname($localDatabase), 0777, true) && !is_dir(dirname($localDatabase))) {
        throw new RuntimeException('Unable to create temporary SQLite WAL transaction fixture');
    }
    file_put_contents($localDatabase, $databaseBytes ?? $baseDatabase);
    file_put_contents($localWal, $walBytes . 'stale-sidecar-tail');

    $applied = (new SQLiteVfsFileWriter($root, $readOnly))->applyWalTransactionRecoveryBoundary(
        $walBytes,
        $databaseBytes ?? $baseDatabase,
        $targetPath,
        512
    );

    return [
        'root' => $root,
        'database' => (string) file_get_contents($localDatabase),
        'wal' => (string) file_get_contents($localWal),
        'applied' => $applied,
    ];
};

$boundary = static fn (string $walBytes): array => SQLiteWal::transactionRecoveryBoundary($walBytes, $baseDatabase, 512);
$checksumBoundary = static fn (string $walBytes): array => SQLiteWal::checksumRecoveryBoundary($walBytes, $baseDatabase, 512);
$singleCommit = static fn (): array => $apply($committedThenDraftWal);
$twoCommit = static fn (): array => $apply($twoCommitThenDraftWal);
$noCommit = static fn (): array => $apply($uncommittedOnlyWal);
$truncated = static fn (): array => $apply($truncatedAfterCommitWal);
$corrupt = static fn (): array => $apply($corruptAfterCommitWal);
$badHeader = static fn (): array => $apply($badHeaderWal);

$cases = [
    'single commit boundary status recovers committed prefix' => static fn (): mixed => $boundary($committedThenDraftWal)['status'],
    'single commit boundary reason discards valid tail' => static fn (): mixed => $boundary($committedThenDraftWal)['reason'],
    'single commit valid frame count includes drafts' => static fn (): mixed => $boundary($committedThenDraftWal)['valid_frame_count'],
    'single commit committed frame count' => static fn (): mixed => $boundary($committedThenDraftWal)['committed_frame_count'],
    'single commit discarded valid tail count' => static fn (): mixed => $boundary($committedThenDraftWal)['discarded_valid_tail_frame_count'],
    'single commit discarded corrupt tail count' => static fn (): mixed => $boundary($committedThenDraftWal)['discarded_corrupt_tail_frame_count'],
    'single commit committed end offset' => static fn (): mixed => $boundary($committedThenDraftWal)['committed_end_offset'],
    'single commit recovery end offset keeps all valid frames' => static fn (): mixed => $boundary($committedThenDraftWal)['recovery_end_offset'],
    'single commit checkpoint database page count' => static fn (): mixed => $boundary($committedThenDraftWal)['checkpoint_database_page_count'],
    'single commit checkpoint contains committed siteurl' => static fn (): mixed => str_contains((string) $boundary($committedThenDraftWal)['checkpoint_database_bytes'], 'committed siteurl option frame'),
    'single commit checkpoint excludes draft plugin' => static fn (): mixed => str_contains((string) $boundary($committedThenDraftWal)['checkpoint_database_bytes'], 'draft active_plugins after commit'),
    'single commit checksum boundary would preserve valid draft tail' => static fn (): mixed => $checksumBoundary($committedThenDraftWal)['uncommitted_frame_count'],
    'single commit transaction wal omits valid draft tail' => static fn (): mixed => str_contains($singleCommit()['wal'], 'draft active_plugins after commit'),
    'single commit transaction wal omits second valid draft tail' => static fn (): mixed => str_contains($singleCommit()['wal'], 'draft autoload index after commit'),
    'single commit transaction wal length' => static fn (): mixed => strlen($singleCommit()['wal']),
    'single commit database contains committed siteurl' => static fn (): mixed => str_contains($singleCommit()['database'], 'committed siteurl option frame'),
    'single commit database keeps base plugin page' => static fn (): mixed => str_contains($singleCommit()['database'], 'base active_plugins option before wal'),
    'single commit database omits draft plugin page' => static fn (): mixed => str_contains($singleCommit()['database'], 'draft active_plugins after commit'),
    'single commit apply status' => static fn (): mixed => $singleCommit()['applied']['status'],
    'single commit apply atomic' => static fn (): mixed => $singleCommit()['applied']['atomic'],
    'single commit operation count' => static fn (): mixed => $singleCommit()['applied']['applied'],
    'single commit bytes written' => static fn (): mixed => $singleCommit()['applied']['bytes_written'],
    'single commit durable syncs' => static fn (): mixed => $singleCommit()['applied']['durable_syncs'],
    'single commit directory syncs' => static fn (): mixed => $singleCommit()['applied']['directory_syncs'],
    'single commit first operation reason' => static fn (): mixed => $singleCommit()['applied']['operations'][0]['reason'],
    'single commit wal restore reason' => static fn (): mixed => $singleCommit()['applied']['operations'][3]['reason'],
    'single commit final operation reason' => static fn (): mixed => $singleCommit()['applied']['operations'][6]['reason'],
    'single commit dependency has transaction boundary' => static fn (): mixed => in_array('sqlite-wal-transaction-recovery-boundary', $singleCommit()['applied']['dependencies'], true),
    'single commit dependency has vfs apply marker' => static fn (): mixed => in_array('sqlite-wal-transaction-boundary-vfs-apply', $singleCommit()['applied']['dependencies'], true),
    'single commit recovery exposes reason' => static fn (): mixed => $singleCommit()['applied']['recovery']['reason'],

    'two commit boundary committed frame count' => static fn (): mixed => $boundary($twoCommitThenDraftWal)['committed_frame_count'],
    'two commit boundary last page count' => static fn (): mixed => $boundary($twoCommitThenDraftWal)['last_commit_page_count'],
    'two commit discarded valid tail count' => static fn (): mixed => $boundary($twoCommitThenDraftWal)['discarded_valid_tail_frame_count'],
    'two commit database contains first commit' => static fn (): mixed => str_contains($twoCommit()['database'], 'first transaction siteurl commit'),
    'two commit database contains second commit' => static fn (): mixed => str_contains($twoCommit()['database'], 'second transaction plugin commit'),
    'two commit database excludes third draft' => static fn (): mixed => str_contains($twoCommit()['database'], 'third transaction uncommitted index'),
    'two commit wal excludes third draft' => static fn (): mixed => str_contains($twoCommit()['wal'], 'third transaction uncommitted index'),
    'two commit wal length' => static fn (): mixed => strlen($twoCommit()['wal']),
    'two commit operation count' => static fn (): mixed => $twoCommit()['applied']['applied'],
    'two commit bytes written' => static fn (): mixed => $twoCommit()['applied']['bytes_written'],

    'no commit boundary status recovers committed prefix' => static fn (): mixed => $boundary($uncommittedOnlyWal)['status'],
    'no commit boundary reason' => static fn (): mixed => $boundary($uncommittedOnlyWal)['reason'],
    'no commit committed frame count' => static fn (): mixed => $boundary($uncommittedOnlyWal)['committed_frame_count'],
    'no commit checkpoint unavailable' => static fn (): mixed => $boundary($uncommittedOnlyWal)['can_checkpoint'],
    'no commit database remains unchanged' => static fn (): mixed => $noCommit()['database'] === $baseDatabase,
    'no commit wal truncates to header only' => static fn (): mixed => strlen($noCommit()['wal']),
    'no commit drops uncommitted siteurl' => static fn (): mixed => str_contains($noCommit()['wal'], 'only uncommitted siteurl draft'),
    'no commit operation count' => static fn (): mixed => $noCommit()['applied']['applied'],
    'no commit durable syncs only wal' => static fn (): mixed => $noCommit()['applied']['durable_syncs'],
    'no commit starts with wal restore' => static fn (): mixed => $noCommit()['applied']['operations'][0]['reason'],
    'no commit final directory sync' => static fn (): mixed => $noCommit()['applied']['operations'][3]['reason'],

    'truncated tail reason' => static fn (): mixed => $boundary($truncatedAfterCommitWal)['reason'],
    'truncated tail committed frame count' => static fn (): mixed => $boundary($truncatedAfterCommitWal)['committed_frame_count'],
    'truncated tail corrupt count' => static fn (): mixed => $boundary($truncatedAfterCommitWal)['discarded_corrupt_tail_frame_count'],
    'truncated tail wal length' => static fn (): mixed => strlen($truncated()['wal']),
    'truncated tail database keeps committed siteurl' => static fn (): mixed => str_contains($truncated()['database'], 'committed siteurl option frame'),

    'corrupt tail reason combines valid and corrupt tails' => static fn (): mixed => $boundary($corruptAfterCommitWal)['reason'],
    'corrupt tail committed frame count' => static fn (): mixed => $boundary($corruptAfterCommitWal)['committed_frame_count'],
    'corrupt tail valid tail count' => static fn (): mixed => $boundary($corruptAfterCommitWal)['discarded_valid_tail_frame_count'],
    'corrupt tail corrupt tail count' => static fn (): mixed => $boundary($corruptAfterCommitWal)['discarded_corrupt_tail_frame_count'],
    'corrupt tail wal length' => static fn (): mixed => strlen($corrupt()['wal']),
    'corrupt tail removes stale sidecar bytes' => static fn (): mixed => str_contains($corrupt()['wal'], 'stale-sidecar-tail'),

    'bad header status is corrupt' => static fn (): mixed => $boundary($badHeaderWal)['status'],
    'bad header reason' => static fn (): mixed => $boundary($badHeaderWal)['reason'],
    'bad header committed frame count' => static fn (): mixed => $boundary($badHeaderWal)['committed_frame_count'],
    'bad header database remains unchanged' => static fn (): mixed => $badHeader()['database'] === $baseDatabase,
    'bad header wal truncates to header only' => static fn (): mixed => strlen($badHeader()['wal']),

    'empty database path is rejected' => static function () use ($committedThenDraftWal, $baseDatabase): mixed {
        try {
            (new SQLiteVfsFileWriter(sys_get_temp_dir() . '/port-libsqlite-wal-tx-reject-' . bin2hex(random_bytes(4))))
                ->applyWalTransactionRecoveryBoundary($committedThenDraftWal, $baseDatabase, '', 512);
        } catch (InvalidArgumentException) {
            return 'rejected';
        }
        return 'accepted';
    },
    'read only writer is rejected' => static function () use ($committedThenDraftWal, $baseDatabase, $databasePath): mixed {
        try {
            (new SQLiteVfsFileWriter(sys_get_temp_dir() . '/port-libsqlite-wal-tx-ro-' . bin2hex(random_bytes(4)), true))
                ->applyWalTransactionRecoveryBoundary($committedThenDraftWal, $baseDatabase, $databasePath, 512);
        } catch (LogicException) {
            return 'rejected';
        }
        return 'accepted';
    },
    'unaligned database image is rejected' => static function () use ($committedThenDraftWal, $baseDatabase, $databasePath): mixed {
        try {
            (new SQLiteVfsFileWriter(sys_get_temp_dir() . '/port-libsqlite-wal-tx-align-' . bin2hex(random_bytes(4))))
                ->applyWalTransactionRecoveryBoundary($committedThenDraftWal, substr($baseDatabase, 1), $databasePath, 512);
        } catch (InvalidArgumentException) {
            return 'rejected';
        }
        return 'accepted';
    },
];

$expected = [
    'single commit boundary status recovers committed prefix' => 'recovered_committed_prefix',
    'single commit boundary reason discards valid tail' => 'uncommitted_valid_tail_after_last_commit',
    'single commit valid frame count includes drafts' => 4,
    'single commit committed frame count' => 2,
    'single commit discarded valid tail count' => 2,
    'single commit discarded corrupt tail count' => 0,
    'single commit committed end offset' => 1104,
    'single commit recovery end offset keeps all valid frames' => 2176,
    'single commit checkpoint database page count' => 4,
    'single commit checkpoint contains committed siteurl' => true,
    'single commit checkpoint excludes draft plugin' => false,
    'single commit checksum boundary would preserve valid draft tail' => 2,
    'single commit transaction wal omits valid draft tail' => false,
    'single commit transaction wal omits second valid draft tail' => false,
    'single commit transaction wal length' => 1104,
    'single commit database contains committed siteurl' => true,
    'single commit database keeps base plugin page' => true,
    'single commit database omits draft plugin page' => false,
    'single commit apply status' => 'applied',
    'single commit apply atomic' => true,
    'single commit operation count' => 7,
    'single commit bytes written' => 3152,
    'single commit durable syncs' => 2,
    'single commit directory syncs' => 1,
    'single commit first operation reason' => 'checkpoint_committed_wal_transaction_prefix',
    'single commit wal restore reason' => 'restore_committed_wal_transaction_prefix',
    'single commit final operation reason' => 'persist_wal_transaction_recovery_sidecars',
    'single commit dependency has transaction boundary' => true,
    'single commit dependency has vfs apply marker' => true,
    'single commit recovery exposes reason' => 'uncommitted_valid_tail_after_last_commit',

    'two commit boundary committed frame count' => 4,
    'two commit boundary last page count' => 4,
    'two commit discarded valid tail count' => 1,
    'two commit database contains first commit' => true,
    'two commit database contains second commit' => true,
    'two commit database excludes third draft' => false,
    'two commit wal excludes third draft' => false,
    'two commit wal length' => 2176,
    'two commit operation count' => 7,
    'two commit bytes written' => 4224,

    'no commit boundary status recovers committed prefix' => 'recovered_committed_prefix',
    'no commit boundary reason' => 'no_committed_transaction_in_valid_prefix',
    'no commit committed frame count' => 0,
    'no commit checkpoint unavailable' => false,
    'no commit database remains unchanged' => true,
    'no commit wal truncates to header only' => 32,
    'no commit drops uncommitted siteurl' => false,
    'no commit operation count' => 4,
    'no commit durable syncs only wal' => 1,
    'no commit starts with wal restore' => 'restore_committed_wal_transaction_prefix',
    'no commit final directory sync' => 'persist_wal_transaction_recovery_sidecars',

    'truncated tail reason' => 'corrupt_tail_after_committed_prefix',
    'truncated tail committed frame count' => 2,
    'truncated tail corrupt count' => 1,
    'truncated tail wal length' => 1104,
    'truncated tail database keeps committed siteurl' => true,

    'corrupt tail reason combines valid and corrupt tails' => 'uncommitted_valid_tail_before_corrupt_frame',
    'corrupt tail committed frame count' => 2,
    'corrupt tail valid tail count' => 1,
    'corrupt tail corrupt tail count' => 1,
    'corrupt tail wal length' => 1104,
    'corrupt tail removes stale sidecar bytes' => false,

    'bad header status is corrupt' => 'corrupt',
    'bad header reason' => 'header_checksum_mismatch',
    'bad header committed frame count' => 0,
    'bad header database remains unchanged' => true,
    'bad header wal truncates to header only' => 32,

    'empty database path is rejected' => 'rejected',
    'read only writer is rejected' => 'rejected',
    'unaligned database image is rejected' => 'rejected',
];

foreach ($cases as $name => $callback) {
    $tests['wal transaction recovery vfs apply current next58 ' . $name] = static function (TestRunner $t) use ($callback, $expected, $name): void {
        $t->same($expected[$name], $callback());
    };
}

return $tests;
