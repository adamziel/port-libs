<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteWal;
use PortLibs\LibSqlite\SQLiteWalHeader;

$tests = [];

$page = static fn (string $label, int $pageSize): string => str_pad(substr($label, 0, $pageSize), $pageSize, '.', STR_PAD_RIGHT);

$databaseBytes = static function (int $pageSize, int $pageCount) use ($page): string {
    $bytes = '';
    for ($pageNumber = 1; $pageNumber <= $pageCount; $pageNumber++) {
        $bytes .= $page("pager-wal-db-page-{$pageNumber}", $pageSize);
    }

    return $bytes;
};

$makeWalBytes = static function (array $frames, int $pageSize, int $sequence) use ($page): string {
    $salt1 = 0x51570000 + $sequence;
    $salt2 = 0x57510000 + $sequence;
    $headerPrefix = pack('N*', SQLiteWalHeader::MAGIC_BIG_ENDIAN, 3007000, $pageSize, $sequence, $salt1, $salt2);
    $seed = SQLiteWal::checksumPair($headerPrefix, false);
    $bytes = $headerPrefix . pack('N*', $seed[0], $seed[1]);

    foreach ($frames as $frame) {
        $image = $page($frame['label'], $pageSize);
        $framePrefix = pack('N*', $frame['page'], $frame['commit'], $salt1, $salt2);
        $seed = SQLiteWal::checksumPair(substr($framePrefix, 0, 8) . $image, false, $seed[0], $seed[1]);
        $bytes .= $framePrefix . pack('N*', $seed[0], $seed[1]) . $image;
    }

    return $bytes;
};

$corruptFrameChecksum2 = static function (string $walBytes, int $pageSize, int $frameIndex): string {
    $offset = 32 + (($frameIndex - 1) * (24 + $pageSize)) + 20;

    return substr_replace($walBytes, "\0\0\0\0", $offset, 4);
};

$latestCommitAtOrBefore = static function (array $frames, int $validPrefix): ?array {
    $latest = null;
    for ($index = 1; $index <= $validPrefix; $index++) {
        if ($frames[$index - 1]['commit'] > 0) {
            $latest = [$index, $frames[$index - 1]['commit']];
        }
    }

    return $latest;
};

$scenarios = [];
for ($case = 1; $case <= 125; $case++) {
    $pageSize = [512, 1024, 2048, 4096][$case % 4];
    $basePageCount = 3 + ($case % 5);
    $frameCount = 6 + ($case % 7);
    $validPrefix = ($case * 5) % ($frameCount + 1);
    $frames = [];
    for ($frame = 1; $frame <= $frameCount; $frame++) {
        $commit = $frame % 2 === 0 ? $basePageCount + intdiv($frame, 2) : 0;
        $frames[] = [
            'page' => 1 + (($case + ($frame * 3)) % max($basePageCount, $commit)),
            'commit' => $commit,
            'label' => "wal-18-case-{$case}-frame-{$frame}-prefix-{$validPrefix}",
        ];
    }

    $walBytes = $makeWalBytes($frames, $pageSize, 180000 + $case);
    $latestCommit = $latestCommitAtOrBefore($frames, $validPrefix);
    $committedFrame = $latestCommit[0] ?? 0;
    $committedPageCount = $latestCommit[1] ?? null;

    $scenarios["case {$case}"] = [
        'bytes' => $validPrefix < $frameCount ? $corruptFrameChecksum2($walBytes, $pageSize, $validPrefix + 1) : $walBytes,
        'database' => $databaseBytes($pageSize, $basePageCount),
        'page_size' => $pageSize,
        'frame_count' => $frameCount,
        'valid_prefix' => $validPrefix,
        'committed_frame' => $committedFrame,
        'committed_page_count' => $committedPageCount,
        'first_invalid' => $validPrefix < $frameCount ? $validPrefix + 1 : null,
    ];
}

$checks = [
    'valid frame prefix follows first bad checksum word' => [
        static fn (array $r): mixed => $r['valid_frame_count'],
        static fn (array $s): mixed => $s['valid_prefix'],
    ],
    'transaction recovery commits only complete prefix transactions' => [
        static fn (array $r): mixed => $r['committed_frame_count'],
        static fn (array $s): mixed => $s['committed_frame'],
    ],
    'first invalid frame points at corrupted frame checksum' => [
        static fn (array $r): mixed => $r['first_invalid_frame'],
        static fn (array $s): mixed => $s['first_invalid'],
    ],
    'committed byte boundary stops after last commit frame' => [
        static fn (array $r): mixed => $r['committed_end_offset'],
        static fn (array $s): mixed => 32 + ($s['committed_frame'] * (24 + $s['page_size'])),
    ],
    'recovery byte boundary stops after valid frame prefix' => [
        static fn (array $r): mixed => $r['recovery_end_offset'],
        static fn (array $s): mixed => 32 + ($s['valid_prefix'] * (24 + $s['page_size'])),
    ],
    'uncommitted valid tail is discarded before checkpoint' => [
        static fn (array $r): mixed => $r['discarded_valid_tail_frame_count'],
        static fn (array $s): mixed => $s['valid_prefix'] - $s['committed_frame'],
    ],
    'corrupt tail frame count reflects invalid suffix' => [
        static fn (array $r): mixed => $r['discarded_corrupt_tail_frame_count'],
        static fn (array $s): mixed => $s['frame_count'] - $s['valid_prefix'],
    ],
    'checkpoint page count follows last committed frame' => [
        static fn (array $r): mixed => $r['checkpoint_database_page_count'],
        static fn (array $s): mixed => $s['committed_page_count'],
    ],
];

foreach ($scenarios as $scenarioName => $scenario) {
    foreach ($checks as $checkName => [$actual, $expected]) {
        $tests["real upstream corpus pager wal checksum prefix {$scenarioName} {$checkName}"] = static function (TestRunner $t) use ($scenario, $actual, $expected): void {
            $result = SQLiteWal::transactionRecoveryBoundary($scenario['bytes'], $scenario['database'], $scenario['page_size']);

            $t->same($expected($scenario), $actual($result));
        };
    }
}

return $tests;
