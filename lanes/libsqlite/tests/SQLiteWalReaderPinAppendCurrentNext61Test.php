<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteWal;
use PortLibs\LibSqlite\SQLiteWalAppendPlan;
use PortLibs\LibSqlite\SQLiteWalHeader;

$tests = [];

$pageSize = 512;
$page = static fn (string $label): string => str_pad($label, $pageSize, '.', STR_PAD_RIGHT);
$databaseBytes = $page('db page 1 wp_options schema base')
    . $page('db page 2 siteurl base')
    . $page('db page 3 autoload index base')
    . $page('db page 4 theme mods base');
$databasePath = '/tmp/wp-content/database/.ht.sqlite';
$salt1 = 0x61616161;
$salt2 = 0x62626262;

$makeWal = static function (array $frames, int $checkpointSequence = 61) use ($pageSize, $salt1, $salt2): string {
    $headerPrefix = pack('N*', SQLiteWalHeader::MAGIC_BIG_ENDIAN, 3007000, $pageSize, $checkpointSequence, $salt1, $salt2);
    $seed = SQLiteWal::checksumPair($headerPrefix, false);
    $bytes = $headerPrefix . pack('N*', $seed[0], $seed[1]);

    foreach ($frames as [$pageNumber, $commitPageCount, $image]) {
        $framePrefix = pack('N*', $pageNumber, $commitPageCount, $salt1, $salt2);
        $seed = SQLiteWal::checksumPair(substr($framePrefix, 0, 8) . $image, false, $seed[0], $seed[1]);
        $bytes .= $framePrefix . pack('N*', $seed[0], $seed[1]) . $image;
    }

    return $bytes;
};

$wal = SQLiteWal::parse($makeWal([
    [2, 0, $page('frame 1 siteurl before pinned reader')],
    [3, 4, $page('frame 2 autoload index first commit')],
    [2, 0, $page('frame 3 siteurl draft after pin')],
    [4, 4, $page('frame 4 theme mods committed before append')],
]), $pageSize, true);

$transactions = [
    [
        'pages' => [
            2 => $page('frame 5 siteurl committed writer append'),
            3 => $page('frame 6 autoload index committed writer append'),
        ],
        'database_page_count' => 4,
        'commit' => true,
    ],
    [
        'pages' => [
            4 => $page('frame 7 uncommitted theme mods writer tail'),
        ],
        'commit' => false,
    ],
];

$pinnedRestart = static fn (): array => SQLiteWalAppendPlan::pinnedCheckpointAppendCurrentNext(
    $wal,
    $databaseBytes,
    $databasePath,
    $transactions,
    [1, 2, 3, 4],
    'restart',
    2
);
$pinnedFull = static fn (): array => SQLiteWalAppendPlan::pinnedCheckpointAppendCurrentNext(
    $wal,
    $databaseBytes,
    $databasePath,
    $transactions,
    [2, 3, 4],
    'full',
    2,
    false,
    false
);
$unpinnedRestart = static fn (): array => SQLiteWalAppendPlan::pinnedCheckpointAppendCurrentNext(
    $wal,
    $databaseBytes,
    $databasePath,
    $transactions,
    [2, 3, 4],
    'restart',
    4
);
$unpinnedTruncate = static fn (): array => SQLiteWalAppendPlan::pinnedCheckpointAppendCurrentNext(
    $wal,
    $databaseBytes,
    $databasePath,
    $transactions,
    [2, 3, 4],
    'truncate',
    4
);

$cases = [
    'pinned restart status' => [static fn (): mixed => $pinnedRestart()['status'], 'pinned-append-planned'],
    'pinned restart reason' => [static fn (): mixed => $pinnedRestart()['reason'], 'reader_pin_preserves_wal_then_writer_appends'],
    'pinned restart mode' => [static fn (): mixed => $pinnedRestart()['mode'], 'restart'],
    'pinned restart checkpoint busy' => [static fn (): mixed => $pinnedRestart()['checkpoint']['busy'], true],
    'pinned restart checkpoint reason' => [static fn (): mixed => $pinnedRestart()['checkpoint']['reason'], 'reader_blocks_checkpoint_completion'],
    'pinned restart checkpoint action preserves wal' => [static fn (): mixed => $pinnedRestart()['checkpoint']['wal_action'], 'preserve_wal'],
    'pinned restart partial backfill' => [static fn (): mixed => $pinnedRestart()['checkpoint_backfilled_partial'], true],
    'pinned restart preserved wal flag' => [static fn (): mixed => $pinnedRestart()['pin_preserved_wal'], true],
    'pinned restart append starts after preserved wal' => [static fn (): mixed => $pinnedRestart()['appended_after_preserved_wal'], true],
    'pinned restart current end frame' => [static fn (): mixed => $pinnedRestart()['current_reader_end_frame'], 2],
    'pinned restart next end frame' => [static fn (): mixed => $pinnedRestart()['next_reader_end_frame'], 6],
    'pinned restart append start frame' => [static fn (): mixed => $pinnedRestart()['append']['start_frame'], 5],
    'pinned restart append end frame' => [static fn (): mixed => $pinnedRestart()['append']['end_frame'], 7],
    'pinned restart appended frame count' => [static fn (): mixed => $pinnedRestart()['append']['appended_frame_count'], 3],
    'pinned restart committed transaction count' => [static fn (): mixed => $pinnedRestart()['append']['committed_transaction_count'], 1],
    'pinned restart uncommitted transaction count' => [static fn (): mixed => $pinnedRestart()['append']['uncommitted_transaction_count'], 1],
    'pinned restart last commit frame' => [static fn (): mixed => $pinnedRestart()['append']['last_commit_frame'], 6],
    'pinned restart current sources' => [static fn (): mixed => $pinnedRestart()['current_reader_sources'], ['database', 'wal', 'wal', 'database']],
    'pinned restart next sources' => [static fn (): mixed => $pinnedRestart()['next_reader_sources'], ['database', 'wal', 'wal', 'wal']],
    'pinned restart current frames' => [static fn (): mixed => $pinnedRestart()['current_reader_frame_indexes'], [null, 1, 2, null]],
    'pinned restart next frames' => [static fn (): mixed => $pinnedRestart()['next_reader_frame_indexes'], [null, 5, 6, 4]],
    'pinned restart current stable' => [static fn (): mixed => $pinnedRestart()['current_reader_stable'], true],
    'pinned restart next sees committed append' => [static fn (): mixed => $pinnedRestart()['next_reader_sees_committed_append'], true],
    'pinned restart hides uncommitted tail' => [static fn (): mixed => $pinnedRestart()['next_reader_hides_uncommitted_tail'], true],
    'pinned restart current page two before pin' => [static fn (): mixed => str_contains($pinnedRestart()['current_reader'][1]['image'], 'before pinned reader'), true],
    'pinned restart next page two appended' => [static fn (): mixed => str_contains($pinnedRestart()['next_reader'][1]['image'], 'committed writer append'), true],
    'pinned restart next page three appended' => [static fn (): mixed => str_contains($pinnedRestart()['next_reader'][2]['image'], 'autoload index committed writer'), true],
    'pinned restart next page four not uncommitted tail' => [static fn (): mixed => str_contains($pinnedRestart()['next_reader'][3]['image'], 'theme mods committed before append'), true],
    'pinned restart operation write offset is original wal length' => [static fn (): mixed => $pinnedRestart()['operations'][0]['offset'], 32 + (4 * (24 + 512))],
    'pinned restart operations include wal sync' => [static fn (): mixed => $pinnedRestart()['operations'][1]['op'], 'sync'],
    'pinned restart operations include directory sync' => [static fn (): mixed => $pinnedRestart()['operations'][2]['op'], 'sync_directory'],
    'pinned restart dependency marker' => [static fn (): mixed => in_array('sqlite-wal-reader-pin-append-current-next61', $pinnedRestart()['dependencies'], true), true],
    'pinned restart dependency keeps append transaction' => [static fn (): mixed => in_array('sqlite-wal-append-transaction', $pinnedRestart()['dependencies'], true), true],

    'pinned full status' => [static fn (): mixed => $pinnedFull()['status'], 'pinned-append-planned'],
    'pinned full checkpoint reason' => [static fn (): mixed => $pinnedFull()['checkpoint']['reason'], 'reader_blocks_checkpoint_completion'],
    'pinned full checkpoint busy' => [static fn (): mixed => $pinnedFull()['checkpoint']['busy'], true],
    'pinned full action preserves wal' => [static fn (): mixed => $pinnedFull()['checkpoint']['wal_action'], 'preserve_wal'],
    'pinned full current sources' => [static fn (): mixed => $pinnedFull()['current_reader_sources'], ['wal', 'wal', 'database']],
    'pinned full next sources' => [static fn (): mixed => $pinnedFull()['next_reader_sources'], ['wal', 'wal', 'wal']],
    'pinned full no sync ops when disabled' => [static fn (): mixed => count($pinnedFull()['operations']), 1],
    'pinned full write op only' => [static fn (): mixed => $pinnedFull()['operations'][0]['op'], 'write'],
    'pinned full next frame page two' => [static fn (): mixed => $pinnedFull()['next_reader_frame_indexes'][0], 5],
    'pinned full next frame page three' => [static fn (): mixed => $pinnedFull()['next_reader_frame_indexes'][1], 6],
    'pinned full next frame page four' => [static fn (): mixed => $pinnedFull()['next_reader_frame_indexes'][2], 4],

    'unpinned restart status' => [static fn (): mixed => $unpinnedRestart()['status'], 'pinned-append-planned'],
    'unpinned restart checkpoint reason' => [static fn (): mixed => $unpinnedRestart()['checkpoint']['reason'], 'reader_blocks_wal_reset'],
    'unpinned restart checkpoint action' => [static fn (): mixed => $unpinnedRestart()['checkpoint']['wal_action'], 'preserve_wal'],
    'latest restart start frame after preserved latest reader' => [static fn (): mixed => $unpinnedRestart()['append']['start_frame'], 5],
    'latest restart end frame' => [static fn (): mixed => $unpinnedRestart()['append']['end_frame'], 7],
    'latest restart next end frame' => [static fn (): mixed => $unpinnedRestart()['next_reader_end_frame'], 6],
    'latest restart appended after preserved wal' => [static fn (): mixed => $unpinnedRestart()['appended_after_preserved_wal'], true],
    'latest restart pin preserved wal' => [static fn (): mixed => $unpinnedRestart()['pin_preserved_wal'], true],
    'unpinned restart current stable' => [static fn (): mixed => $unpinnedRestart()['current_reader_stable'], true],
    'latest restart next sources' => [static fn (): mixed => $unpinnedRestart()['next_reader_sources'], ['wal', 'wal', 'wal']],
    'latest restart next frames' => [static fn (): mixed => $unpinnedRestart()['next_reader_frame_indexes'], [5, 6, 4]],
    'unpinned restart next hides uncommitted tail' => [static fn (): mixed => $unpinnedRestart()['next_reader_hides_uncommitted_tail'], true],
    'unpinned restart next sees committed append' => [static fn (): mixed => $unpinnedRestart()['next_reader_sees_committed_append'], true],

    'unpinned truncate status' => [static fn (): mixed => $unpinnedTruncate()['status'], 'pinned-append-planned'],
    'unpinned truncate checkpoint reason' => [static fn (): mixed => $unpinnedTruncate()['checkpoint']['reason'], 'reader_blocks_wal_reset'],
    'unpinned truncate checkpoint action' => [static fn (): mixed => $unpinnedTruncate()['checkpoint']['wal_action'], 'preserve_wal'],
    'unpinned truncate append start frame' => [static fn (): mixed => $unpinnedTruncate()['append']['start_frame'], 5],
    'unpinned truncate next end frame' => [static fn (): mixed => $unpinnedTruncate()['next_reader_end_frame'], 6],
    'unpinned truncate next page two appended' => [static fn (): mixed => str_contains($unpinnedTruncate()['next_reader'][0]['image'], 'committed writer append'), true],
    'unpinned truncate next page four checkpoint database' => [static fn (): mixed => str_contains($unpinnedTruncate()['next_reader'][2]['image'], 'theme mods committed before append'), true],
];

foreach ($cases as $name => [$callback, $expected]) {
    $tests['wal reader pin append current next61 ' . $name] = static function (TestRunner $t) use ($callback, $expected): void {
        $t->same($expected, $callback());
    };
}

$tests['wal reader pin append current next61 rejects empty pages'] = static function (TestRunner $t) use ($wal, $databaseBytes, $databasePath, $transactions): void {
    $t->throws(InvalidArgumentException::class, static fn (): mixed => SQLiteWalAppendPlan::pinnedCheckpointAppendCurrentNext($wal, $databaseBytes, $databasePath, $transactions, [], 'restart', 2));
};

$tests['wal reader pin append current next61 rejects non integer page'] = static function (TestRunner $t) use ($wal, $databaseBytes, $databasePath, $transactions): void {
    $t->throws(InvalidArgumentException::class, static fn (): mixed => SQLiteWalAppendPlan::pinnedCheckpointAppendCurrentNext($wal, $databaseBytes, $databasePath, $transactions, ['2'], 'restart', 2));
};

$tests['wal reader pin append current next61 rejects zero reader frame'] = static function (TestRunner $t) use ($wal, $databaseBytes, $databasePath, $transactions): void {
    $t->throws(InvalidArgumentException::class, static fn (): mixed => SQLiteWalAppendPlan::pinnedCheckpointAppendCurrentNext($wal, $databaseBytes, $databasePath, $transactions, [2], 'restart', 0));
};

$tests['wal reader pin append current next61 rejects invalid mode'] = static function (TestRunner $t) use ($wal, $databaseBytes, $databasePath, $transactions): void {
    $t->throws(InvalidArgumentException::class, static fn (): mixed => SQLiteWalAppendPlan::pinnedCheckpointAppendCurrentNext($wal, $databaseBytes, $databasePath, $transactions, [2], 'invalid', 2));
};

return $tests;
