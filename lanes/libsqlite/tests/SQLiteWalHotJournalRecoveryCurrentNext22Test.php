<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteRollbackJournal;
use PortLibs\LibSqlite\SQLiteRollbackJournalHeader;
use PortLibs\LibSqlite\SQLiteVfsFileWriter;
use PortLibs\LibSqlite\SQLiteWal;
use PortLibs\LibSqlite\SQLiteWalHeader;

$tests = [];

$pageSize = 512;
$databasePath = '/srv/www/wp-content/database/.ht.sqlite';
$page = static fn (string $label): string => str_pad($label, $pageSize, '.', STR_PAD_RIGHT);
$baseDatabase = $page('db-header-current-schema') . $page('siteurl after interrupted txn') . $page('autoload index after interrupted txn') . $page('draft page beyond preimage');
$preHotPageOne = $page('db-header-before-hot-journal');
$preHotPageTwo = $page('siteurl before hot journal');
$preHotPageThree = $page('autoload index before hot journal');
$walPageTwo = $page('siteurl committed in wal after recovery');
$walPageThree = $page('autoload index committed in wal');
$walDraftPageFour = $page('plugin draft uncommitted wal tail');

$makeJournal = static function (array $pages, int $initialPages = 3) use ($pageSize): string {
    $nonce = 0x2468ace0;
    $sectorSize = 512;
    $bytes = SQLiteRollbackJournalHeader::MAGIC . pack('N*', count($pages), $nonce, $initialPages, $sectorSize, $pageSize);
    $bytes = str_pad($bytes, $sectorSize, "\0");
    foreach ($pages as $pageNumber => $image) {
        $bytes .= pack('N', $pageNumber) . $image . pack('N', SQLiteRollbackJournal::pageChecksum($image, $nonce));
    }

    return $bytes;
};

$makeWal = static function (array $frames, ?callable $mutate = null) use ($pageSize): string {
    $salt1 = 0x1234abcd;
    $salt2 = 0x5678dcba;
    $headerPrefix = pack('N*', SQLiteWalHeader::MAGIC_BIG_ENDIAN, 3007000, $pageSize, 44, $salt1, $salt2);
    $seed = SQLiteWal::checksumPair($headerPrefix, false);
    $bytes = $headerPrefix . pack('N*', $seed[0], $seed[1]);
    foreach ($frames as $frame) {
        [$pageNumber, $commitPageCount, $image] = $frame;
        $framePrefix = pack('N*', $pageNumber, $commitPageCount, $salt1, $salt2);
        $seed = SQLiteWal::checksumPair(substr($framePrefix, 0, 8) . $image, false, $seed[0], $seed[1]);
        $bytes .= $framePrefix . pack('N*', $seed[0], $seed[1]) . $image;
    }

    return $mutate === null ? $bytes : $mutate($bytes);
};

$journalBytes = $makeJournal([
    1 => $preHotPageOne,
    2 => $preHotPageTwo,
    3 => $preHotPageThree,
    5 => $page('ignored beyond initial db page count'),
]);
$walBytes = $makeWal([
    [2, 0, $page('siteurl stale superseded wal frame')],
    [2, 3, $walPageTwo],
    [3, 3, $walPageThree],
    [4, 0, $walDraftPageFour],
]);
$corruptWalBytes = $makeWal([
    [2, 3, $walPageTwo],
    [3, 3, $walPageThree],
], static fn (string $bytes): string => substr_replace($bytes, 'Z', 32 + 24 + 40, 1));
$noCommitWalBytes = $makeWal([
    [2, 0, $page('wal draft without commit')],
    [3, 0, $page('second wal draft without commit')],
]);

$apply = static function (string $journal, string $wal, string $database = null) use ($baseDatabase, $databasePath): array {
    $root = sys_get_temp_dir() . '/port-libsqlite-wal-hot-journal-' . bin2hex(random_bytes(4));
    $localDatabase = $root . $databasePath;
    if (!is_dir(dirname($localDatabase)) && !mkdir(dirname($localDatabase), 0777, true) && !is_dir(dirname($localDatabase))) {
        throw new RuntimeException('Unable to create hot journal recovery fixture directory');
    }
    file_put_contents($localDatabase, $database ?? $baseDatabase);
    file_put_contents($localDatabase . '-journal', $journal);
    file_put_contents($localDatabase . '-wal', $wal . 'stale-wal-tail');

    $applied = (new SQLiteVfsFileWriter($root))->applyHotJournalThenWalRecovery(
        $database ?? $baseDatabase,
        $journal,
        $wal,
        $databasePath,
        false,
        false,
        null,
        512
    );

    return [
        'root' => $root,
        'database' => (string) file_get_contents($localDatabase),
        'journal_exists' => is_file($localDatabase . '-journal'),
        'wal' => (string) file_get_contents($localDatabase . '-wal'),
        'applied' => $applied,
    ];
};

$result = static fn (): array => $apply($journalBytes, $walBytes);
$corruptResult = static fn (): array => $apply($journalBytes, $corruptWalBytes);
$noCommitResult = static fn (): array => $apply($journalBytes, $noCommitWalBytes);

$cases = [
    'combined recovery status is applied' => static fn (): mixed => $result()['applied']['status'],
    'combined recovery is atomic' => static fn (): mixed => $result()['applied']['atomic'],
    'combined recovery operation count' => static fn (): mixed => $result()['applied']['applied'],
    'combined recovery durable sync count' => static fn (): mixed => $result()['applied']['durable_syncs'],
    'combined recovery directory sync count' => static fn (): mixed => $result()['applied']['directory_syncs'],
    'combined recovery deletes hot journal' => static fn (): mixed => $result()['journal_exists'],
    'combined recovery restores hot page before wal' => static fn (): mixed => str_contains($result()['applied']['rollback_recovery']['database_bytes'], 'siteurl before hot journal'),
    'combined recovery trims database to hot journal initial pages' => static fn (): mixed => $result()['applied']['rollback_recovery']['final_database_bytes'],
    'combined recovery ignores beyond initial journal page' => static fn (): mixed => str_contains($result()['applied']['rollback_recovery']['database_bytes'], 'ignored beyond initial db page count'),
    'combined recovery checkpoints wal page two' => static fn (): mixed => str_contains($result()['database'], 'siteurl committed in wal after recovery'),
    'combined recovery checkpoints wal page three' => static fn (): mixed => str_contains($result()['database'], 'autoload index committed in wal'),
    'combined recovery excludes uncommitted wal page four from database' => static fn (): mixed => str_contains($result()['database'], 'plugin draft uncommitted wal tail'),
    'combined recovery final database page count is three' => static fn (): mixed => intdiv(strlen($result()['database']), 512),
    'combined recovery wal status uses committed prefix' => static fn (): mixed => $result()['applied']['wal_recovery']['status'],
    'combined recovery wal reason reports tail discard' => static fn (): mixed => $result()['applied']['wal_recovery']['reason'],
    'combined recovery wal valid frame count' => static fn (): mixed => $result()['applied']['wal_recovery']['valid_frame_count'],
    'combined recovery wal committed frame count' => static fn (): mixed => $result()['applied']['wal_recovery']['committed_frame_count'],
    'combined recovery wal discarded valid tail count' => static fn (): mixed => $result()['applied']['wal_recovery']['discarded_valid_tail_frame_count'],
    'combined recovery wal checkpoint page count' => static fn (): mixed => $result()['applied']['wal_recovery']['checkpoint_database_page_count'],
    'combined recovery wal bytes are committed prefix' => static fn (): mixed => strlen($result()['wal']),
    'combined recovery removes stale local wal tail' => static fn (): mixed => str_contains($result()['wal'], 'stale-wal-tail'),
    'combined recovery removes uncommitted wal tail' => static fn (): mixed => str_contains($result()['wal'], 'plugin draft uncommitted wal tail'),
    'combined recovery preserves committed wal page' => static fn (): mixed => str_contains($result()['wal'], 'autoload index committed in wal'),
    'combined recovery first operation restores hot journal' => static fn (): mixed => $result()['applied']['operations'][0]['reason'],
    'combined recovery syncs hot journal before wal checkpoint' => static fn (): mixed => $result()['applied']['operations'][2]['reason'],
    'combined recovery checkpoints after hot sync' => static fn (): mixed => $result()['applied']['operations'][3]['reason'],
    'combined recovery trims after wal checkpoint' => static fn (): mixed => $result()['applied']['operations'][4]['reason'],
    'combined recovery syncs after wal checkpoint' => static fn (): mixed => $result()['applied']['operations'][5]['reason'],
    'combined recovery restores committed wal prefix' => static fn (): mixed => $result()['applied']['operations'][6]['reason'],
    'combined recovery truncates wal tail' => static fn (): mixed => $result()['applied']['operations'][7]['reason'],
    'combined recovery syncs wal prefix' => static fn (): mixed => $result()['applied']['operations'][8]['reason'],
    'combined recovery deletes journal after wal recovery' => static fn (): mixed => $result()['applied']['operations'][9]['reason'],
    'combined recovery persists directory last' => static fn (): mixed => $result()['applied']['operations'][10]['reason'],
    'combined recovery has hot journal dependency' => static fn (): mixed => in_array('sqlite-hot-journal-before-wal-recovery', $result()['applied']['dependencies'], true),
    'combined recovery has wal transaction dependency' => static fn (): mixed => in_array('sqlite-wal-transaction-recovery-boundary', $result()['applied']['dependencies'], true),
    'combined recovery has atomic rollback dependency' => static fn (): mixed => in_array('vfs-atomic-rollback-on-write-failure', $result()['applied']['dependencies'], true),
    'corrupt wal status is recovered committed prefix' => static fn (): mixed => $corruptResult()['applied']['wal_recovery']['status'],
    'corrupt wal reason is checksum mismatch' => static fn (): mixed => $corruptResult()['applied']['wal_recovery']['reason'],
    'corrupt wal first invalid frame' => static fn (): mixed => $corruptResult()['applied']['wal_recovery']['first_invalid_frame'],
    'corrupt wal preserves hot journal page one in database' => static fn (): mixed => str_contains($corruptResult()['database'], 'db-header-before-hot-journal'),
    'corrupt wal omits corrupt checkpoint page two' => static fn (): mixed => str_contains($corruptResult()['database'], 'siteurl committed in wal after recovery'),
    'corrupt wal truncates to header only' => static fn (): mixed => strlen($corruptResult()['wal']),
    'no commit wal reason' => static fn (): mixed => $noCommitResult()['applied']['wal_recovery']['reason'],
    'no commit wal cannot checkpoint' => static fn (): mixed => $noCommitResult()['applied']['wal_recovery']['can_checkpoint'],
    'no commit leaves hot journal page two in database' => static fn (): mixed => str_contains($noCommitResult()['database'], 'siteurl before hot journal'),
    'no commit removes interrupted database page two' => static fn (): mixed => str_contains($noCommitResult()['database'], 'siteurl after interrupted txn'),
    'no commit truncates wal to header only' => static fn (): mixed => strlen($noCommitResult()['wal']),
    'reserved lock skips recovery' => static function () use ($baseDatabase, $journalBytes, $walBytes, $databasePath): mixed {
        $root = sys_get_temp_dir() . '/port-libsqlite-wal-hot-journal-skip-' . bin2hex(random_bytes(4));
        return (new SQLiteVfsFileWriter($root))->applyHotJournalThenWalRecovery($baseDatabase, $journalBytes, $walBytes, $databasePath, true, false, null, 512)['status'];
    },
    'missing super journal skips recovery' => static function () use ($baseDatabase, $journalBytes, $walBytes, $databasePath): mixed {
        $root = sys_get_temp_dir() . '/port-libsqlite-wal-hot-journal-super-' . bin2hex(random_bytes(4));
        return (new SQLiteVfsFileWriter($root))->applyHotJournalThenWalRecovery($baseDatabase, $journalBytes, $walBytes, $databasePath, false, true, false, 512)['rollback_recovery']['reason'];
    },
    'read only combined recovery rejected' => static function () use ($baseDatabase, $journalBytes, $walBytes, $databasePath): mixed {
        try {
            (new SQLiteVfsFileWriter(sys_get_temp_dir() . '/port-libsqlite-wal-hot-journal-ro-' . bin2hex(random_bytes(4)), true))
                ->applyHotJournalThenWalRecovery($baseDatabase, $journalBytes, $walBytes, $databasePath, false, false, null, 512);
        } catch (LogicException) {
            return 'rejected';
        }
        return 'accepted';
    },
    'empty database path rejected' => static function () use ($baseDatabase, $journalBytes, $walBytes): mixed {
        try {
            (new SQLiteVfsFileWriter(sys_get_temp_dir() . '/port-libsqlite-wal-hot-journal-path-' . bin2hex(random_bytes(4))))
                ->applyHotJournalThenWalRecovery($baseDatabase, $journalBytes, $walBytes, '', false, false, null, 512);
        } catch (InvalidArgumentException) {
            return 'rejected';
        }
        return 'accepted';
    },
];

$expected = [
    'combined recovery status is applied' => 'applied',
    'combined recovery is atomic' => true,
    'combined recovery operation count' => 11,
    'combined recovery durable sync count' => 3,
    'combined recovery directory sync count' => 1,
    'combined recovery deletes hot journal' => false,
    'combined recovery restores hot page before wal' => true,
    'combined recovery trims database to hot journal initial pages' => 1536,
    'combined recovery ignores beyond initial journal page' => false,
    'combined recovery checkpoints wal page two' => true,
    'combined recovery checkpoints wal page three' => true,
    'combined recovery excludes uncommitted wal page four from database' => false,
    'combined recovery final database page count is three' => 3,
    'combined recovery wal status uses committed prefix' => 'recovered_committed_prefix',
    'combined recovery wal reason reports tail discard' => 'uncommitted_valid_tail_after_last_commit',
    'combined recovery wal valid frame count' => 4,
    'combined recovery wal committed frame count' => 3,
    'combined recovery wal discarded valid tail count' => 1,
    'combined recovery wal checkpoint page count' => 3,
    'combined recovery wal bytes are committed prefix' => 1640,
    'combined recovery removes stale local wal tail' => false,
    'combined recovery removes uncommitted wal tail' => false,
    'combined recovery preserves committed wal page' => true,
    'combined recovery first operation restores hot journal' => 'restore_database_pages_from_hot_journal_before_wal',
    'combined recovery syncs hot journal before wal checkpoint' => 'sync_hot_journal_recovered_database_before_wal',
    'combined recovery checkpoints after hot sync' => 'checkpoint_committed_wal_after_hot_journal',
    'combined recovery trims after wal checkpoint' => 'trim_database_after_committed_wal_recovery',
    'combined recovery syncs after wal checkpoint' => 'sync_database_after_committed_wal_recovery',
    'combined recovery restores committed wal prefix' => 'restore_committed_wal_prefix_after_hot_journal',
    'combined recovery truncates wal tail' => 'discard_uncommitted_wal_tail_after_hot_journal',
    'combined recovery syncs wal prefix' => 'sync_committed_wal_prefix_after_hot_journal',
    'combined recovery deletes journal after wal recovery' => 'delete_hot_journal_after_ordered_wal_recovery',
    'combined recovery persists directory last' => 'persist_hot_journal_wal_recovery_sidecars',
    'combined recovery has hot journal dependency' => true,
    'combined recovery has wal transaction dependency' => true,
    'combined recovery has atomic rollback dependency' => true,
    'corrupt wal status is recovered committed prefix' => 'recovered_committed_prefix',
    'corrupt wal reason is checksum mismatch' => 'corrupt_tail_after_committed_prefix',
    'corrupt wal first invalid frame' => 1,
    'corrupt wal preserves hot journal page one in database' => true,
    'corrupt wal omits corrupt checkpoint page two' => false,
    'corrupt wal truncates to header only' => 32,
    'no commit wal reason' => 'no_committed_transaction_in_valid_prefix',
    'no commit wal cannot checkpoint' => false,
    'no commit leaves hot journal page two in database' => true,
    'no commit removes interrupted database page two' => false,
    'no commit truncates wal to header only' => 32,
    'reserved lock skips recovery' => 'skipped',
    'missing super journal skips recovery' => 'missing_super_journal',
    'read only combined recovery rejected' => 'rejected',
    'empty database path rejected' => 'rejected',
];

foreach ($cases as $name => $case) {
    $tests["wal hot-journal recovery current-next22: {$name}"] = static function (TestRunner $t) use ($case, $expected, $name): void {
        $t->same($expected[$name], $case());
    };
}

return $tests;
