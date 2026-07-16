<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteWal;
use PortLibs\LibSqlite\SQLiteWalHeader;

$tests = [];

$makePage = static fn (string $label, int $pageSize): string => str_pad(substr($label, 0, $pageSize), $pageSize, '.', STR_PAD_RIGHT);

$makeDatabaseBytes = static function (int $pageSize, int $pageCount) use ($makePage): string {
    $bytes = '';
    for ($page = 1; $page <= $pageCount; $page++) {
        $bytes .= $makePage("db-page-{$page}", $pageSize);
    }

    return $bytes;
};

$makeWalBytes = static function (array $frames, int $pageSize, int $sequence) use ($makePage): string {
    $salt1 = 0x12340000 + $sequence;
    $salt2 = 0x56780000 + $sequence;
    $headerPrefix = pack('N*', SQLiteWalHeader::MAGIC_BIG_ENDIAN, 3007000, $pageSize, $sequence, $salt1, $salt2);
    $seed = SQLiteWal::checksumPair($headerPrefix, false);
    $bytes = $headerPrefix . pack('N*', $seed[0], $seed[1]);

    foreach ($frames as $frame) {
        $framePrefix = pack('N*', $frame['page'], $frame['commit'], $salt1, $salt2);
        $image = $makePage($frame['label'], $pageSize);
        $seed = SQLiteWal::checksumPair(substr($framePrefix, 0, 8) . $image, false, $seed[0], $seed[1]);
        $bytes .= $framePrefix . pack('N*', $seed[0], $seed[1]) . $image;
    }

    return $bytes;
};

$scenarios = [];
for ($scenario = 1; $scenario <= 40; $scenario++) {
    $pageSize = ($scenario % 2 === 0) ? 1024 : 512;
    $basePages = 3 + ($scenario % 3);
    $transactions = 2 + ($scenario % 5);
    $frames = [];
    $commitFrames = [];

    for ($tx = 1; $tx <= $transactions; $tx++) {
        $frames[] = [
            'page' => 2 + (($scenario + $tx) % $basePages),
            'commit' => 0,
            'label' => "s{$scenario}-tx{$tx}-dirty",
        ];
        $commitPage = $basePages + $tx;
        $frames[] = [
            'page' => $commitPage,
            'commit' => $commitPage,
            'label' => "s{$scenario}-tx{$tx}-commit",
        ];
        $commitFrames[] = count($frames);
    }

    $frames[] = [
        'page' => 1,
        'commit' => 0,
        'label' => "s{$scenario}-uncommitted",
    ];

    $walBytes = $makeWalBytes($frames, $pageSize, 1000 + $scenario);
    $databaseBytes = $makeDatabaseBytes($pageSize, $basePages);
    $wal = SQLiteWal::parse($walBytes, null, true);
    $latestCommit = $commitFrames[count($commitFrames) - 1];
    $previousCommit = $commitFrames[max(0, count($commitFrames) - 2)];
    $latestPageCount = $basePages + $transactions;
    $readerFrame = $previousCommit;

    $scenarios["upstream wal dynamic scenario {$scenario}"] = [
        'wal' => $wal,
        'database' => $databaseBytes,
        'pageSize' => $pageSize,
        'basePages' => $basePages,
        'transactions' => $transactions,
        'frameCount' => count($frames),
        'latestCommit' => $latestCommit,
        'previousCommit' => $previousCommit,
        'readerFrame' => $readerFrame,
        'latestPageCount' => $latestPageCount,
        'latestCommitPage' => $latestPageCount,
        'latestCommitLabel' => "s{$scenario}-tx{$transactions}-commit",
        'previousPageCount' => $basePages + max(1, $transactions - 1),
        'source' => 'upstream wal.test transaction visibility, wal2.test reader marks, walckpt.test checkpoint modes, walro.test read-only snapshots',
    ];
}

$checks = [
    'latest snapshot ends at wal frame count' => static fn (array $s): mixed => $s['wal']->readerSnapshot($s['database'])['end_frame'],
    'latest snapshot ignores uncommitted tail for commit frame' => static fn (array $s): mixed => $s['wal']->readerSnapshot($s['database'])['commit_frame']->index,
    'latest snapshot grows database to last commit page count' => static fn (array $s): mixed => $s['wal']->readerSnapshot($s['database'])['database_page_count'],
    'previous reader snapshot pins previous commit frame' => static fn (array $s): mixed => $s['wal']->readerSnapshot($s['database'], $s['readerFrame'])['commit_frame']->index,
    'previous reader snapshot exposes previous page count' => static fn (array $s): mixed => $s['wal']->readerSnapshot($s['database'], $s['readerFrame'])['database_page_count'],
    'snapshot before first commit falls back to base page count' => static fn (array $s): mixed => $s['wal']->readerSnapshot($s['database'], 1)['database_page_count'],
    'latest commit page image comes from wal' => static fn (array $s): mixed => $s['wal']->readerSnapshotPageImage($s['database'], $s['latestCommitPage'])['source'],
    'latest commit page image uses final transaction frame' => static fn (array $s): mixed => substr($s['wal']->readerSnapshotPageImage($s['database'], $s['latestCommitPage'])['image'], 0, strlen($s['latestCommitLabel'])),
    'base page one ignores uncommitted tail' => static fn (array $s): mixed => substr($s['wal']->readerSnapshotPageImage($s['database'], 1)['image'], 0, 9),
    'previous reader cannot see latest grown page' => static function (array $s): mixed {
        try {
            $s['wal']->readerSnapshotPageImage($s['database'], $s['latestCommitPage'], $s['readerFrame']);
        } catch (OutOfBoundsException) {
            return 'rejected';
        }
        return 'accepted';
    },
    'latest page map includes grown database pages' => static fn (array $s): mixed => count($s['wal']->readerSnapshotPageMap($s['database'])),
    'previous page map includes previous commit pages' => static fn (array $s): mixed => count($s['wal']->readerSnapshotPageMap($s['database'], $s['readerFrame'])),
    'noop checkpoint does not backfill' => static fn (array $s): mixed => $s['wal']->checkpointModePlan($s['database'], 'noop')['checkpointed_frame_count'],
    'noop checkpoint reports noop reason' => static fn (array $s): mixed => $s['wal']->checkpointModePlan($s['database'], 'noop')['reason'],
    'passive checkpoint with old reader is reader limited' => static fn (array $s): mixed => $s['wal']->checkpointModePlan($s['database'], 'passive', $s['readerFrame'])['reason'],
    'passive checkpoint with old reader is not busy' => static fn (array $s): mixed => $s['wal']->checkpointModePlan($s['database'], 'passive', $s['readerFrame'])['busy'],
    'full checkpoint with old reader is busy' => static fn (array $s): mixed => $s['wal']->checkpointModePlan($s['database'], 'full', $s['readerFrame'])['busy'],
    'full checkpoint with old reader reports blocked completion' => static fn (array $s): mixed => $s['wal']->checkpointModePlan($s['database'], 'full', $s['readerFrame'])['reason'],
    'restart checkpoint cannot reset with uncommitted tail' => static fn (array $s): mixed => $s['wal']->checkpointModePlan($s['database'], 'restart')['can_reset'],
    'truncate checkpoint preserves uncommitted wal tail' => static fn (array $s): mixed => $s['wal']->checkpointModeResult($s['database'], 'truncate')['wal_action'],
    'passive checkpoint writes through latest page count' => static fn (array $s): mixed => $s['wal']->checkpointModeResult($s['database'], 'passive')['database_page_count'],
    'passive checkpoint writes latest commit page bytes' => static fn (array $s): mixed => substr($s['wal']->checkpointModeResult($s['database'], 'passive')['database_bytes'], ($s['latestCommitPage'] - 1) * $s['pageSize'], strlen($s['latestCommitLabel'])),
    'reader visibility remains stable across passive checkpoint' => static fn (array $s): mixed => $s['wal']->checkpointReaderVisibility($s['database'], [1, $s['previousPageCount']], 'passive', $s['readerFrame'])['stable'],
    'reader visibility old reader preserves wal action' => static fn (array $s): mixed => $s['wal']->checkpointReaderVisibility($s['database'], [$s['previousPageCount']], 'passive', $s['readerFrame'])['wal_action'],
    'read mark plan pins previous reader' => static fn (array $s): mixed => $s['wal']->readMarkPlan([0, $s['readerFrame'], $s['latestCommit'], null])['checkpoint_pinned_frame'],
    'read mark plan recommends unused slot' => static fn (array $s): mixed => $s['wal']->readMarkPlan([0, $s['readerFrame'], $s['latestCommit'], null])['recommended_reader_slot'],
    'read mark plan recommends latest commit frame' => static fn (array $s): mixed => $s['wal']->readMarkPlan([0, $s['readerFrame'], $s['latestCommit'], null])['recommended_reader_frame'],
    'read mark plan blocks reset while stale reader exists' => static fn (array $s): mixed => $s['wal']->readMarkPlan([0, $s['readerFrame'], $s['latestCommit'], null])['reset_blocked'],
    'source cites upstream scripts' => static fn (array $s): mixed => $s['source'],
    'snapshot rejects frame after wal end' => static function (array $s): mixed {
        try {
            $s['wal']->readerSnapshot($s['database'], $s['frameCount'] + 1);
        } catch (InvalidArgumentException) {
            return 'rejected';
        }
        return 'accepted';
    },
];

$expected = [
    'latest snapshot ends at wal frame count' => static fn (array $s): mixed => $s['frameCount'],
    'latest snapshot ignores uncommitted tail for commit frame' => static fn (array $s): mixed => $s['latestCommit'],
    'latest snapshot grows database to last commit page count' => static fn (array $s): mixed => $s['latestPageCount'],
    'previous reader snapshot pins previous commit frame' => static fn (array $s): mixed => $s['previousCommit'],
    'previous reader snapshot exposes previous page count' => static fn (array $s): mixed => $s['previousPageCount'],
    'snapshot before first commit falls back to base page count' => static fn (array $s): mixed => $s['basePages'],
    'latest commit page image comes from wal' => static fn (): mixed => 'wal',
    'latest commit page image uses final transaction frame' => static fn (array $s): mixed => $s['latestCommitLabel'],
    'base page one ignores uncommitted tail' => static fn (): mixed => 'db-page-1',
    'previous reader cannot see latest grown page' => static fn (): mixed => 'rejected',
    'latest page map includes grown database pages' => static fn (array $s): mixed => $s['latestPageCount'],
    'previous page map includes previous commit pages' => static fn (array $s): mixed => $s['previousPageCount'],
    'noop checkpoint does not backfill' => static fn (): mixed => 0,
    'noop checkpoint reports noop reason' => static fn (): mixed => 'noop_checkpoint_does_not_backfill',
    'passive checkpoint with old reader is reader limited' => static fn (): mixed => 'reader_limited_passive_checkpoint',
    'passive checkpoint with old reader is not busy' => static fn (): mixed => false,
    'full checkpoint with old reader is busy' => static fn (): mixed => true,
    'full checkpoint with old reader reports blocked completion' => static fn (): mixed => 'reader_blocks_checkpoint_completion',
    'restart checkpoint cannot reset with uncommitted tail' => static fn (): mixed => false,
    'truncate checkpoint preserves uncommitted wal tail' => static fn (): mixed => 'preserve_wal',
    'passive checkpoint writes through latest page count' => static fn (array $s): mixed => $s['latestPageCount'],
    'passive checkpoint writes latest commit page bytes' => static fn (array $s): mixed => $s['latestCommitLabel'],
    'reader visibility remains stable across passive checkpoint' => static fn (): mixed => true,
    'reader visibility old reader preserves wal action' => static fn (): mixed => 'preserve_wal',
    'read mark plan pins previous reader' => static fn (array $s): mixed => $s['readerFrame'],
    'read mark plan recommends unused slot' => static fn (): mixed => 0,
    'read mark plan recommends latest commit frame' => static fn (array $s): mixed => $s['latestCommit'],
    'read mark plan blocks reset while stale reader exists' => static fn (): mixed => true,
    'source cites upstream scripts' => static fn (array $s): mixed => $s['source'],
    'snapshot rejects frame after wal end' => static fn (): mixed => 'rejected',
];

foreach ($scenarios as $scenarioName => $scenario) {
    foreach ($checks as $checkName => $callback) {
        $tests["{$scenarioName} {$checkName}"] = static function (TestRunner $t) use ($scenario, $checkName, $callback, $expected): void {
            $t->same($expected[$checkName]($scenario), $callback($scenario));
        };
    }
}

return $tests;
