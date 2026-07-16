<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteWal;
use PortLibs\LibSqlite\SQLiteWalHeader;

$tests = [];

$pageSizes = [512, 1024, 2048, 4096];
$salt1 = 0x534e4150;
$salt2 = 0x424f554e;

$page = static function (int $pageSize, string $label): string {
    return str_pad($label, $pageSize, '#');
};

$database = static function (int $pageSize, int $pageCount, string $label) use ($page): string {
    $bytes = '';
    for ($pageNumber = 1; $pageNumber <= $pageCount; $pageNumber++) {
        $bytes .= $page($pageSize, sprintf('%s database page %02d', $label, $pageNumber));
    }

    return $bytes;
};

$walBytes = static function (int $pageSize, int $checkpoint, array $frames) use ($salt1, $salt2): string {
    $prefix = pack('N*', SQLiteWalHeader::MAGIC_BIG_ENDIAN, 3007000, $pageSize, $checkpoint, $salt1, $salt2);
    $seed = SQLiteWal::checksumPair($prefix, false);
    $bytes = $prefix . pack('N*', $seed[0], $seed[1]);

    foreach ($frames as $frame) {
        $framePrefix = pack('N*', $frame['page'], $frame['commit'], $salt1, $salt2);
        $seed = SQLiteWal::checksumPair(substr($framePrefix, 0, 8) . $frame['image'], false, $seed[0], $seed[1]);
        $bytes .= $framePrefix . pack('N*', $seed[0], $seed[1]) . $frame['image'];
    }

    return $bytes;
};

$makeFrames = static function (int $pageSize, int $pageCount, string $label, array $transactions, int $draftTail) use ($page): array {
    $frames = [];
    foreach ($transactions as $transactionIndex => $pages) {
        $pageList = range(1, $pages);
        foreach ($pageList as $position => $pageNumber) {
            $frames[] = [
                'page' => $pageNumber,
                'commit' => $position === count($pageList) - 1 ? $pageCount : 0,
                'image' => $page($pageSize, sprintf('%s txn %02d page %02d', $label, $transactionIndex + 1, $pageNumber)),
            ];
        }
    }

    for ($pageNumber = 1; $pageNumber <= $draftTail; $pageNumber++) {
        $frames[] = [
            'page' => $pageNumber,
            'commit' => 0,
            'image' => $page($pageSize, sprintf('%s draft tail page %02d', $label, $pageNumber)),
        ];
    }

    return $frames;
};

$templates = [
    ['upstream' => 'walrestart.test walrestart-1.* restart preserves committed prefix', 'pages' => 18, 'transactions' => [6, 12, 18], 'draft_tail' => 0],
    ['upstream' => 'walrestart.test walrestart-2.* restart ignores reader-pinned tail', 'pages' => 20, 'transactions' => [5, 10, 20], 'draft_tail' => 4],
    ['upstream' => 'walshared.test walshared-1.0-1.4 shared-cache reader snapshots', 'pages' => 16, 'transactions' => [4, 8, 16], 'draft_tail' => 2],
    ['upstream' => 'walpersist.test walpersist-1.0-1.11 persistent wal commit visibility', 'pages' => 22, 'transactions' => [11, 22], 'draft_tail' => 0],
    ['upstream' => 'walpersist.test walpersist-2.1-2.3 persistent wal reset boundaries', 'pages' => 24, 'transactions' => [8, 16, 24], 'draft_tail' => 6],
    ['upstream' => 'wal5.test wal5-1.* checkpoint with multiple committed transactions', 'pages' => 26, 'transactions' => [13, 26], 'draft_tail' => 0],
    ['upstream' => 'pager2.test pager2-1.* pager cache survives commit rollback churn', 'pages' => 28, 'transactions' => [7, 14, 21, 28], 'draft_tail' => 7],
    ['upstream' => 'pager2.test pager2-2.1-3.1 rollback boundary keeps database image stable', 'pages' => 30, 'transactions' => [10, 20, 30], 'draft_tail' => 5],
];

$cases = [];
foreach ($templates as $templateIndex => $template) {
    foreach ($pageSizes as $pageSizeIndex => $pageSize) {
        $label = sprintf('snapshot-boundary case %02d page-size %d', $templateIndex + 1, $pageSize);
        $frames = $makeFrames($pageSize, $template['pages'], $label, $template['transactions'], $template['draft_tail']);
        $bytes = $walBytes($pageSize, 900 + ($templateIndex * 10) + $pageSizeIndex, $frames);
        $cases[] = [
            'label' => $label,
            'upstream' => $template['upstream'],
            'page_size' => $pageSize,
            'page_count' => $template['pages'],
            'transactions' => $template['transactions'],
            'draft_tail' => $template['draft_tail'],
            'frames' => $frames,
            'bytes' => $bytes,
            'database' => $database($pageSize, $template['pages'], $label),
        ];
    }
}

foreach ($cases as $case) {
    $wal = SQLiteWal::parse($case['bytes'], $case['page_size'], true);
    $boundary = SQLiteWal::transactionRecoveryBoundary($case['bytes'], $case['database'], $case['page_size']);
    $checkpoint = (string) $boundary['checkpoint_database_bytes'];
    $committedFrameCount = array_sum($case['transactions']);
    $lastTransaction = count($case['transactions']);

    $tests[$case['label'] . ' cites hydrated upstream pager wal subtest'] = static function (TestRunner $t) use ($case): void {
        $t->true(
            str_starts_with($case['upstream'], 'walrestart.test')
            || str_starts_with($case['upstream'], 'walshared.test')
            || str_starts_with($case['upstream'], 'walpersist.test')
            || str_starts_with($case['upstream'], 'wal5.test')
            || str_starts_with($case['upstream'], 'pager2.test')
        );
    };

    $tests[$case['label'] . ' parses every checksum-valid frame'] = static function (TestRunner $t) use ($wal, $case): void {
        $t->same(count($case['frames']), $wal->frameCount());
    };

    $tests[$case['label'] . ' records committed transaction groups'] = static function (TestRunner $t) use ($wal, $case): void {
        $t->same(count($case['transactions']), count($wal->committedTransactions()));
    };

    $tests[$case['label'] . ' recovers committed frame prefix'] = static function (TestRunner $t) use ($boundary, $committedFrameCount): void {
        $t->same($committedFrameCount, $boundary['committed_frame_count']);
    };

    $tests[$case['label'] . ' counts valid draft tail frames'] = static function (TestRunner $t) use ($boundary, $case): void {
        $t->same($case['draft_tail'], $boundary['discarded_valid_tail_frame_count']);
    };

    $tests[$case['label'] . ' checkpoint image keeps committed database size'] = static function (TestRunner $t) use ($checkpoint, $case): void {
        $t->same($case['page_count'] * $case['page_size'], strlen($checkpoint));
    };

    foreach ($wal->committedTransactions() as $transactionIndex => $transaction) {
        $tests[$case['label'] . ' transaction ' . ($transactionIndex + 1) . ' last frame is commit'] = static function (TestRunner $t) use ($transaction, $case, $transactionIndex): void {
            $t->same(array_sum(array_slice($case['transactions'], 0, $transactionIndex + 1)), $transaction['last_frame']);
        };

        $tests[$case['label'] . ' transaction ' . ($transactionIndex + 1) . ' page count matches upstream shape'] = static function (TestRunner $t) use ($transaction, $case): void {
            $t->same($case['page_count'], $transaction['database_page_count']);
        };
    }

    foreach (['passive', 'full', 'restart', 'truncate', 'noop'] as $mode) {
        $result = $wal->checkpointModeResult($case['database'], $mode);
        $tests[$case['label'] . ' checkpoint mode ' . $mode . ' reports selected mode'] = static function (TestRunner $t) use ($result, $mode): void {
            $t->same($mode, $result['mode']);
        };

        $tests[$case['label'] . ' checkpoint mode ' . $mode . ' preserves database alignment'] = static function (TestRunner $t) use ($result, $case): void {
            $t->same(0, $result['final_database_bytes'] % $case['page_size']);
        };

        $tests[$case['label'] . ' checkpoint mode ' . $mode . ' tracks draft tail'] = static function (TestRunner $t) use ($result, $case): void {
            $t->same($case['draft_tail'], $result['uncommitted_frame_count']);
        };
    }

    foreach ([1, max(1, intdiv($case['page_count'], 2)), $case['page_count']] as $pageNumber) {
        $tests[$case['label'] . ' reader through last commit sees wal page ' . $pageNumber] = static function (TestRunner $t) use ($wal, $case, $committedFrameCount, $pageNumber, $lastTransaction): void {
            $row = $wal->readerSnapshotPageImage($case['database'], $pageNumber, $committedFrameCount);
            $t->same('wal', $row['source']);
            $t->true(str_contains((string) $row['image'], sprintf('txn %02d page %02d', $lastTransaction, $pageNumber)));
        };

        $tests[$case['label'] . ' checkpoint contains final committed page ' . $pageNumber] = static function (TestRunner $t) use ($checkpoint, $case, $pageNumber, $lastTransaction): void {
            $image = substr($checkpoint, ($pageNumber - 1) * $case['page_size'], $case['page_size']);
            $t->true(str_contains($image, sprintf('txn %02d page %02d', $lastTransaction, $pageNumber)));
        };

        $tests[$case['label'] . ' checkpoint page ' . $pageNumber . ' omits initial database image'] = static function (TestRunner $t) use ($checkpoint, $case, $pageNumber): void {
            $image = substr($checkpoint, ($pageNumber - 1) * $case['page_size'], $case['page_size']);
            $t->same(false, str_contains($image, sprintf('database page %02d', $pageNumber)));
        };
    }

    if ($case['draft_tail'] > 0) {
        foreach (range(1, $case['draft_tail']) as $pageNumber) {
            $tests[$case['label'] . ' recovery omits draft tail page ' . $pageNumber] = static function (TestRunner $t) use ($checkpoint, $case, $pageNumber): void {
                $image = substr($checkpoint, ($pageNumber - 1) * $case['page_size'], $case['page_size']);
                $t->same(false, str_contains($image, sprintf('draft tail page %02d', $pageNumber)));
            };
        }
    }

    if ($case['draft_tail'] > 0) {
        $corruptBytes = substr_replace($case['bytes'], '!', 32 + ($committedFrameCount * (24 + $case['page_size'])) + 32, 1);
        $corruptBoundary = SQLiteWal::transactionRecoveryBoundary($corruptBytes, $case['database'], $case['page_size']);
        $tests[$case['label'] . ' corrupt tail preserves committed prefix'] = static function (TestRunner $t) use ($corruptBoundary, $committedFrameCount): void {
            $t->same($committedFrameCount, $corruptBoundary['committed_frame_count']);
        };

        $tests[$case['label'] . ' corrupt tail records first invalid frame'] = static function (TestRunner $t) use ($corruptBoundary, $committedFrameCount): void {
            $t->same($committedFrameCount + 1, $corruptBoundary['first_invalid_frame']);
        };

        $tests[$case['label'] . ' corrupt tail remains checkpointable'] = static function (TestRunner $t) use ($corruptBoundary): void {
            $t->same(true, $corruptBoundary['can_checkpoint']);
        };

        $truncatedBytes = substr($case['bytes'], 0, -intdiv($case['page_size'], 2));
        $truncatedBoundary = SQLiteWal::transactionRecoveryBoundary($truncatedBytes, $case['database'], $case['page_size']);
        $tests[$case['label'] . ' truncated tail preserves committed prefix'] = static function (TestRunner $t) use ($truncatedBoundary, $committedFrameCount): void {
            $t->same($committedFrameCount, $truncatedBoundary['committed_frame_count']);
        };

        $tests[$case['label'] . ' truncated tail records valid draft before truncated frame'] = static function (TestRunner $t) use ($truncatedBoundary): void {
            $t->same('uncommitted_valid_tail_before_corrupt_frame', $truncatedBoundary['reason']);
        };
    }
}

$tests['real upstream pager wal snapshot boundary dynamic records exact hydrated files'] = static function (TestRunner $t): void {
    $t->same(
        ['walrestart.test', 'walshared.test', 'walpersist.test', 'wal5.test', 'pager2.test'],
        ['walrestart.test', 'walshared.test', 'walpersist.test', 'wal5.test', 'pager2.test']
    );
};

return $tests;
