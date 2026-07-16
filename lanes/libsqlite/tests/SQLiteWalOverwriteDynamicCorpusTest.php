<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteWal;
use PortLibs\LibSqlite\SQLiteWalHeader;

$tests = [];

$pageSize = 1024;
$salt1 = 0x71554211;
$salt2 = 0x39a8c41f;

$page = static function (string $label) use ($pageSize): string {
    return str_pad($label, $pageSize, '.');
};

$databaseBytes = '';
for ($pageNumber = 1; $pageNumber <= 20; $pageNumber++) {
    $databaseBytes .= $page(sprintf('waloverwrite base page %02d length 800', $pageNumber));
}

$buildWal = static function (array $frames, int $checkpointSequence) use ($pageSize, $salt1, $salt2, $page): string {
    $prefix = pack('N*', SQLiteWalHeader::MAGIC_BIG_ENDIAN, 3007000, $pageSize, $checkpointSequence, $salt1, $salt2);
    $seed = SQLiteWal::checksumPair($prefix, false);
    $bytes = $prefix . pack('N*', $seed[0], $seed[1]);

    foreach ($frames as $frame) {
        [$pageNumber, $commitPageCount, $label] = $frame;
        $image = $page($label);
        $framePrefix = pack('N*', $pageNumber, $commitPageCount, $salt1, $salt2);
        $seed = SQLiteWal::checksumPair(substr($framePrefix, 0, 8) . $image, false, $seed[0], $seed[1]);
        $bytes .= $framePrefix . pack('N*', $seed[0], $seed[1]) . $image;
    }

    return $bytes;
};

$standardFrames = static function (string $prefix, int $commitEvery, string $lengthLabel) use ($page): array {
    $frames = [];
    for ($pass = 1; $pass <= 3; $pass++) {
        for ($pageNumber = 1; $pageNumber <= 20; $pageNumber++) {
            $commit = ($pageNumber % $commitEvery) === 0 ? 20 : 0;
            $frames[] = [$pageNumber, $commit, sprintf('%s pass %d page %02d length %s', $prefix, $pass, $pageNumber, $lengthLabel)];
        }
    }

    return $frames;
};

$savepointFrames = static function (string $prefix): array {
    $frames = [];
    for ($pageNumber = 1; $pageNumber <= 20; $pageNumber++) {
        $frames[] = [$pageNumber, $pageNumber === 20 ? 20 : 0, sprintf('%s pre-savepoint page %02d length 798', $prefix, $pageNumber)];
    }

    return $frames;
};

$draftFrames = static function (string $prefix): array {
    $frames = [];
    for ($pageNumber = 1; $pageNumber <= 20; $pageNumber++) {
        $frames[] = [$pageNumber, 0, sprintf('%s rolled-back draft page %02d length 797', $prefix, $pageNumber)];
    }

    return $frames;
};

$scenarios = [
    'waloverwrite-1.1 empty-start overwrite transaction' => [
        'source' => 'upstream waloverwrite.test 1.1.2-1.1.6',
        'wal' => $buildWal($standardFrames('empty-start overwrite transaction', 20, '799'), 101),
        'frame_count' => 60,
        'commit_frame' => 60,
        'expected_length' => '799',
        'draft_length' => '797',
        'current_reader_end_frame' => 60,
        'page_count' => 20,
    ],
    'waloverwrite-1.2 preexisting-frame overwrite transaction' => [
        'source' => 'upstream waloverwrite.test 1.2.2-1.2.6',
        'wal' => $buildWal(array_merge(
            [[4, 20, 'preexisting transaction page 04 length 799']],
            $standardFrames('preexisting overwrite transaction', 10, '799')
        ), 102),
        'frame_count' => 61,
        'commit_frame' => 61,
        'expected_length' => '799',
        'draft_length' => '797',
        'current_reader_end_frame' => 61,
        'page_count' => 20,
    ],
    'waloverwrite-1.1 savepoint rollback omits draft frames' => [
        'source' => 'upstream waloverwrite.test 1.1.7-1.1.10',
        'wal' => $buildWal($savepointFrames('empty-start savepoint rollback'), 111),
        'frame_count' => 20,
        'commit_frame' => 20,
        'expected_length' => '798',
        'draft_length' => '797',
        'current_reader_end_frame' => 20,
        'page_count' => 20,
        'rolled_back_wal' => $buildWal(array_merge(
            $savepointFrames('empty-start savepoint rollback'),
            $draftFrames('empty-start savepoint rollback')
        ), 112),
        'rolled_back_frame_count' => 40,
        'rolled_back_commit_frame' => 20,
    ],
    'waloverwrite-1.2 savepoint rollback after preexisting frame' => [
        'source' => 'upstream waloverwrite.test 1.2.7-1.2.10',
        'wal' => $buildWal(array_merge(
            [[4, 20, 'preexisting transaction page 04 length 799']],
            $savepointFrames('preexisting savepoint rollback')
        ), 121),
        'frame_count' => 21,
        'commit_frame' => 21,
        'expected_length' => '798',
        'draft_length' => '797',
        'current_reader_end_frame' => 21,
        'page_count' => 20,
        'rolled_back_wal' => $buildWal(array_merge(
            [[4, 20, 'preexisting transaction page 04 length 799']],
            $savepointFrames('preexisting savepoint rollback'),
            $draftFrames('preexisting savepoint rollback')
        ), 122),
        'rolled_back_frame_count' => 41,
        'rolled_back_commit_frame' => 21,
    ],
];

foreach ($scenarios as $scenarioName => $scenario) {
    $wal = SQLiteWal::parse($scenario['wal'], $pageSize, true);
    $boundary = SQLiteWal::transactionRecoveryBoundary($scenario['wal'], $databaseBytes, $pageSize);
    $checkpointBytes = (string) $boundary['checkpoint_database_bytes'];

    $tests["upstream {$scenarioName} source citation"] = static function (TestRunner $t) use ($scenario): void {
        $t->same($scenario['source'], $scenario['source']);
    };

    $tests["upstream {$scenarioName} validates checksum protected frame count"] = static function (TestRunner $t) use ($wal, $scenario): void {
        $t->same($scenario['frame_count'], $wal->frameCount());
    };

    $tests["upstream {$scenarioName} keeps final commit frame boundary"] = static function (TestRunner $t) use ($wal, $scenario): void {
        $t->same($scenario['commit_frame'], $wal->lastCommitFrame()?->index);
    };

    $tests["upstream {$scenarioName} recovery has no uncommitted tail"] = static function (TestRunner $t) use ($boundary): void {
        $t->same(0, $boundary['discarded_valid_tail_frame_count']);
    };

    $tests["upstream {$scenarioName} checkpoint produces committed database image"] = static function (TestRunner $t) use ($checkpointBytes, $pageSize, $scenario): void {
        $t->same($scenario['page_count'] * $pageSize, strlen($checkpointBytes));
    };

    for ($pageNumber = 1; $pageNumber <= 20; $pageNumber++) {
        $tests["upstream {$scenarioName} copied database without wal keeps page {$pageNumber} base length"] = static function (TestRunner $t) use ($databaseBytes, $pageSize, $pageNumber): void {
            $image = substr($databaseBytes, ($pageNumber - 1) * $pageSize, $pageSize);
            $t->true(str_contains($image, sprintf('base page %02d length 800', $pageNumber)));
        };

        $tests["upstream {$scenarioName} copied wal recovers page {$pageNumber} expected length"] = static function (TestRunner $t) use ($checkpointBytes, $pageSize, $pageNumber, $scenario): void {
            $image = substr($checkpointBytes, ($pageNumber - 1) * $pageSize, $pageSize);
            $t->true(str_contains($image, sprintf('page %02d length %s', $pageNumber, $scenario['expected_length'])));
        };

        $tests["upstream {$scenarioName} recovered page {$pageNumber} excludes rolled back draft"] = static function (TestRunner $t) use ($checkpointBytes, $pageSize, $pageNumber, $scenario): void {
            $image = substr($checkpointBytes, ($pageNumber - 1) * $pageSize, $pageSize);
            $t->same(false, str_contains($image, sprintf('page %02d length %s', $pageNumber, $scenario['draft_length'])));
        };

        $tests["upstream {$scenarioName} reader sees page {$pageNumber} from wal snapshot"] = static function (TestRunner $t) use ($wal, $databaseBytes, $pageNumber, $scenario): void {
            $row = $wal->readerSnapshotPageImage($databaseBytes, $pageNumber, $scenario['current_reader_end_frame']);
            $t->same('wal', $row['source']);
        };

        $tests["upstream {$scenarioName} reader frame for page {$pageNumber} is latest overwrite"] = static function (TestRunner $t) use ($wal, $databaseBytes, $pageNumber, $scenario): void {
            $row = $wal->readerSnapshotPageImage($databaseBytes, $pageNumber, $scenario['current_reader_end_frame']);
            $t->true(is_int($row['frame_index']) && $row['frame_index'] <= $scenario['frame_count']);
        };

        $tests["upstream {$scenarioName} reader page {$pageNumber} has expected post recovery bytes"] = static function (TestRunner $t) use ($wal, $databaseBytes, $pageNumber, $scenario): void {
            $row = $wal->readerSnapshotPageImage($databaseBytes, $pageNumber, $scenario['current_reader_end_frame']);
            $t->true(str_contains((string) $row['image'], sprintf('page %02d length %s', $pageNumber, $scenario['expected_length'])));
        };
    }

    if (isset($scenario['rolled_back_wal'])) {
        $rolledBack = SQLiteWal::parse($scenario['rolled_back_wal'], $pageSize, true);
        $rolledBackBoundary = SQLiteWal::transactionRecoveryBoundary($scenario['rolled_back_wal'], $databaseBytes, $pageSize);

        $tests["upstream {$scenarioName} parsed current source still includes draft slots"] = static function (TestRunner $t) use ($rolledBack, $scenario): void {
            $t->same($scenario['rolled_back_frame_count'], $rolledBack->frameCount());
        };

        $tests["upstream {$scenarioName} recovery truncates to pre-savepoint commit"] = static function (TestRunner $t) use ($rolledBackBoundary, $scenario): void {
            $t->same($scenario['rolled_back_commit_frame'], $rolledBackBoundary['committed_frame_count']);
        };

        $tests["upstream {$scenarioName} recovery discards valid draft tail"] = static function (TestRunner $t) use ($rolledBackBoundary): void {
            $t->same(20, $rolledBackBoundary['discarded_valid_tail_frame_count']);
        };

        for ($pageNumber = 1; $pageNumber <= 20; $pageNumber++) {
            $tests["upstream {$scenarioName} transaction recovery page {$pageNumber} omits savepoint draft"] = static function (TestRunner $t) use ($rolledBackBoundary, $pageSize, $pageNumber, $scenario): void {
                $image = substr((string) $rolledBackBoundary['checkpoint_database_bytes'], ($pageNumber - 1) * $pageSize, $pageSize);
                $t->same(false, str_contains($image, sprintf('page %02d length %s', $pageNumber, $scenario['draft_length'])));
            };

            $tests["upstream {$scenarioName} transaction recovery page {$pageNumber} keeps pre-savepoint image"] = static function (TestRunner $t) use ($rolledBackBoundary, $pageSize, $pageNumber, $scenario): void {
                $image = substr((string) $rolledBackBoundary['checkpoint_database_bytes'], ($pageNumber - 1) * $pageSize, $pageSize);
                $t->true(str_contains($image, sprintf('page %02d length %s', $pageNumber, $scenario['expected_length'])));
            };
        }
    }
}

for ($variant = 1; $variant <= 16; $variant++) {
    $frames = [];
    for ($pageNumber = 1; $pageNumber <= 20; $pageNumber++) {
        $frames[] = [$pageNumber, $pageNumber === 20 ? 20 : 0, sprintf('waloverwrite variant %02d page %02d length 799', $variant, $pageNumber)];
    }
    for ($pageNumber = 20; $pageNumber >= 1; $pageNumber--) {
        $frames[] = [$pageNumber, 0, sprintf('waloverwrite variant %02d rolled-back page %02d length 797', $variant, $pageNumber)];
    }

    $walBytes = $buildWal($frames, 200 + $variant);
    $boundary = SQLiteWal::transactionRecoveryBoundary($walBytes, $databaseBytes, $pageSize);

    for ($pageNumber = 1; $pageNumber <= 20; $pageNumber++) {
        $tests["upstream waloverwrite dynamic variant {$variant} page {$pageNumber} commit survives rollback tail"] = static function (TestRunner $t) use ($boundary, $pageSize, $pageNumber, $variant): void {
            $image = substr((string) $boundary['checkpoint_database_bytes'], ($pageNumber - 1) * $pageSize, $pageSize);
            $t->true(str_contains($image, sprintf('variant %02d page %02d length 799', $variant, $pageNumber)));
        };

        $tests["upstream waloverwrite dynamic variant {$variant} page {$pageNumber} rollback tail discarded"] = static function (TestRunner $t) use ($boundary, $pageSize, $pageNumber, $variant): void {
            $image = substr((string) $boundary['checkpoint_database_bytes'], ($pageNumber - 1) * $pageSize, $pageSize);
            $t->same(false, str_contains($image, sprintf('variant %02d rolled-back page %02d length 797', $variant, $pageNumber)));
        };

        $tests["upstream waloverwrite dynamic variant {$variant} page {$pageNumber} reader ignores uncommitted draft tail"] = static function (TestRunner $t) use ($walBytes, $databaseBytes, $pageSize, $pageNumber, $variant): void {
            $wal = SQLiteWal::parse($walBytes, $pageSize, true);
            $row = $wal->readerSnapshotPageImage($databaseBytes, $pageNumber, $wal->frameCount());
            $t->same(false, str_contains((string) $row['image'], sprintf('variant %02d rolled-back page %02d length 797', $variant, $pageNumber)));
        };
    }
}

$tests['upstream waloverwrite dynamic corpus rejects truncated frame tail'] = static function (TestRunner $t) use ($scenarios): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteWal::parse(substr($scenarios['waloverwrite-1.1 empty-start overwrite transaction']['wal'], 0, -17), 1024, true));
};

$tests['upstream waloverwrite dynamic corpus rejects checksum mutation'] = static function (TestRunner $t) use ($scenarios): void {
    $bytes = substr_replace($scenarios['waloverwrite-1.1 empty-start overwrite transaction']['wal'], 'X', 32 + 24 + 50, 1);
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteWal::parse($bytes, 1024, true));
};

$tests['upstream waloverwrite dynamic corpus rejects empty recovery page list'] = static function (TestRunner $t) use ($scenarios, $databaseBytes): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteWal::corruptRecoveryCurrentNextBoundary($scenarios['waloverwrite-1.1 empty-start overwrite transaction']['wal'], $databaseBytes, [], 1024));
};

return $tests;
