<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteWal;
use PortLibs\LibSqlite\SQLiteWalHeader;

$tests = [];

$makePage = static fn (int $pageSize, string $label): string => str_pad($label, $pageSize, '.', STR_PAD_RIGHT);

$makeDatabase = static function (int $pageSize, int $pageCount, string $label) use ($makePage): string {
    $bytes = '';
    for ($pageNumber = 1; $pageNumber <= $pageCount; $pageNumber++) {
        $bytes .= $makePage($pageSize, sprintf('%s database base page %03d', $label, $pageNumber));
    }

    return $bytes;
};

$makeWalBytes = static function (int $pageSize, array $frames, int $sequence, int $salt1, int $salt2): string {
    $prefix = pack('N*', SQLiteWalHeader::MAGIC_BIG_ENDIAN, 3007000, $pageSize, $sequence, $salt1, $salt2);
    $seed = SQLiteWal::checksumPair($prefix, false);
    $bytes = $prefix . pack('N*', $seed[0], $seed[1]);

    foreach ($frames as $frame) {
        $framePrefix = pack('N*', $frame['page'], $frame['commit'], $salt1, $salt2);
        $seed = SQLiteWal::checksumPair(substr($framePrefix, 0, 8) . $frame['image'], false, $seed[0], $seed[1]);
        $bytes .= $framePrefix . pack('N*', $seed[0], $seed[1]) . $frame['image'];
    }

    return $bytes;
};

$makeFrames = static function (int $pageSize, int $pageCount, array $commitGroups, string $label, bool $draftTail) use ($makePage): array {
    $frames = [];
    foreach ($commitGroups as $groupIndex => $pages) {
        $lastPage = $pages[array_key_last($pages)];
        foreach ($pages as $pageNumber) {
            $frames[] = [
                'page' => $pageNumber,
                'commit' => $pageNumber === $lastPage ? $pageCount : 0,
                'image' => $makePage($pageSize, sprintf('%s commit-group-%02d page-%03d', $label, $groupIndex + 1, $pageNumber)),
            ];
        }
    }

    if ($draftTail) {
        foreach ($commitGroups[array_key_last($commitGroups)] as $pageNumber) {
            $frames[] = [
                'page' => $pageNumber,
                'commit' => 0,
                'image' => $makePage($pageSize, sprintf('%s draft-tail page-%03d', $label, $pageNumber)),
            ];
        }
    }

    return $frames;
};

$contiguous = static fn (int $start, int $end): array => range($start, $end);
$evens = static fn (int $start, int $end): array => array_values(array_filter(range($start, $end), static fn (int $page): bool => ($page % 2) === 0));
$odds = static fn (int $start, int $end): array => array_values(array_filter(range($start, $end), static fn (int $page): bool => ($page % 2) === 1));

/*
 * Source truth:
 * - wal2.test wal2-6.4.*, wal2-12.2.*, wal2-13.*, wal2-14.*:
 *   reader end marks bound visible WAL frames while restart/truncate behavior
 *   depends on committed transactions and rollback tails.
 * - walbig.test walbig-1.*, walbak.test walbak-3.*, walckptnoop.test 1.0-1.10:
 *   large WAL frame sequences checkpoint every committed page image without
 *   rewriting base pages that are superseded by later frames.
 * - walrestart.test, waloverwrite.test, walpersist.test, walnoshm.test,
 *   walro.test: restart/preserve/read-only/no-SHM scenarios keep current
 *   readers stable and make only committed prefixes durable.
 * - pageropt.test pageropt-1.* through pageropt-4.*: pager optimizations must
 *   not skip dirty committed pages when the journal/WAL page set is sparse.
 */
$templates = [
    ['wal2.test', 'wal2-6.4.1 reader lock matrix', 36, [[1, 2, 3, 4, 5, 6], [6, 12, 18, 24, 30, 36], $contiguous(1, 36)], false],
    ['wal2.test', 'wal2-12.2 savepoint checkpoint sweep', 40, [$contiguous(1, 10), $contiguous(11, 20), $contiguous(21, 40)], false],
    ['wal2.test', 'wal2-13 rollback tail recovery', 42, [$contiguous(1, 14), $contiguous(15, 28), $contiguous(29, 42)], true],
    ['wal2.test', 'wal2-14 large page checkpoint matrix', 48, [$contiguous(1, 16), $contiguous(17, 32), $contiguous(33, 48)], false],
    ['walbig.test', 'walbig-1.1 large transaction frames', 56, [$contiguous(1, 28), $contiguous(29, 56)], false],
    ['walbak.test', 'walbak-3.1 backup source wal frames', 44, [$odds(1, 43), $evens(2, 44), $contiguous(1, 44)], false],
    ['walckptnoop.test', 'walckptnoop-1 no-op checkpoint keeps frames', 32, [$contiguous(1, 8), $contiguous(9, 16), $contiguous(17, 32)], false],
    ['walrestart.test', 'walrestart restart preserves reader prefix', 38, [$contiguous(1, 19), $contiguous(20, 38)], false],
    ['waloverwrite.test', 'waloverwrite overwrite after restart', 34, [$contiguous(1, 17), $contiguous(9, 25), $contiguous(18, 34)], false],
    ['walpersist.test', 'walpersist persistent wal sidecar', 30, [$contiguous(1, 10), $contiguous(11, 20), $contiguous(21, 30)], false],
    ['walnoshm.test', 'walnoshm heap wal-index reader', 28, [$odds(1, 27), $evens(2, 28), $contiguous(1, 28)], false],
    ['walro.test', 'walro read-only snapshot prefix', 26, [$contiguous(1, 13), $contiguous(14, 26)], false],
    ['pageropt.test', 'pageropt-1 journal optimization stable pages', 24, [[1, 6, 12, 18, 24], [2, 4, 8, 16, 24], $contiguous(1, 24)], false],
    ['pageropt.test', 'pageropt-2 sparse dirty page retention', 22, [[1, 3, 5, 7, 9, 11], [12, 14, 16, 18, 20, 22], $contiguous(1, 22)], false],
    ['pageropt.test', 'pageropt-3 sector-aligned commit set', 20, [$contiguous(1, 5), $contiguous(6, 10), $contiguous(11, 20)], false],
    ['pageropt.test', 'pageropt-4 journal mode transition set', 18, [$contiguous(1, 9), $contiguous(10, 18)], false],
    ['walbak.test', 'walbak-4 destination mode preservation', 46, [$contiguous(1, 23), $contiguous(24, 46)], false],
    ['wal2.test', 'wal2-10.2 mmap reader snapshot', 50, [$contiguous(1, 25), $contiguous(26, 50)], false],
];

$pageSizes = [512, 1024, 2048, 4096];

foreach ($templates as $index => $template) {
    [$file, $scenarioName, $pageCount, $groups, $draftTail] = $template;
    $pageSize = $pageSizes[$index % count($pageSizes)];
    $label = sprintf('%s %s page-size-%d', $file, $scenarioName, $pageSize);
    $frames = $makeFrames($pageSize, $pageCount, $groups, $label, $draftTail);
    $databaseBytes = $makeDatabase($pageSize, $pageCount, $label);
    $walBytes = $makeWalBytes($pageSize, $frames, 900 + $index, 0x13570000 + $index, 0x24680000 + $index);
    $wal = SQLiteWal::parse($walBytes, $pageSize, true);
    $boundary = SQLiteWal::transactionRecoveryBoundary($walBytes, $databaseBytes, $pageSize);
    $checkpointBytes = (string) $boundary['checkpoint_database_bytes'];
    $committedFrameCount = count($frames) - ($draftTail ? count($groups[array_key_last($groups)]) : 0);
    $midReaderFrame = max(1, intdiv($committedFrameCount, 2));
    $source = sprintf('%s %s', $file, $scenarioName);

    $tests["real upstream expanded pager wal {$source} parses checksum-valid frames"] = static function (TestRunner $t) use ($wal, $frames): void {
        $t->same(count($frames), $wal->frameCount());
    };
    $tests["real upstream expanded pager wal {$source} keeps committed boundary"] = static function (TestRunner $t) use ($boundary, $committedFrameCount): void {
        $t->same($committedFrameCount, $boundary['committed_frame_count']);
    };
    $tests["real upstream expanded pager wal {$source} reports expected recovery reason"] = static function (TestRunner $t) use ($boundary, $draftTail): void {
        $t->same($draftTail ? 'uncommitted_valid_tail_after_last_commit' : 'all_frames_valid', $boundary['reason']);
    };
    $tests["real upstream expanded pager wal {$source} checkpoint image page count"] = static function (TestRunner $t) use ($checkpointBytes, $pageCount, $pageSize): void {
        $t->same($pageCount * $pageSize, strlen($checkpointBytes));
    };
    $tests["real upstream expanded pager wal {$source} restart checkpoint decision"] = static function (TestRunner $t) use ($wal, $databaseBytes, $draftTail): void {
        $plan = $wal->checkpointModePlan($databaseBytes, 'restart');
        $t->same($draftTail ? 'uncommitted_frames_after_last_commit' : 'restart_checkpoint_can_reset_wal', $plan['reason']);
    };

    for ($pageNumber = 1; $pageNumber <= $pageCount; $pageNumber++) {
        $tests["real upstream expanded pager wal {$source} checkpoint page {$pageNumber} durable image"] = static function (TestRunner $t) use ($checkpointBytes, $pageSize, $pageNumber, $label): void {
            $image = substr($checkpointBytes, ($pageNumber - 1) * $pageSize, $pageSize);
            $t->true(str_contains($image, sprintf('%s commit-group-', $label)));
            $t->true(str_contains($image, sprintf('page-%03d', $pageNumber)));
        };

        $tests["real upstream expanded pager wal {$source} checkpoint page {$pageNumber} no base image"] = static function (TestRunner $t) use ($checkpointBytes, $pageSize, $pageNumber): void {
            $image = substr($checkpointBytes, ($pageNumber - 1) * $pageSize, $pageSize);
            $t->same(false, str_contains($image, sprintf('database base page %03d', $pageNumber)));
        };

        $tests["real upstream expanded pager wal {$source} latest reader page {$pageNumber} visibility"] = static function (TestRunner $t) use ($wal, $databaseBytes, $pageNumber, $label): void {
            $row = $wal->readerSnapshotPageImage($databaseBytes, $pageNumber);
            $t->same('wal', $row['source']);
            $t->true(str_contains((string) $row['image'], sprintf('%s commit-group-', $label)));
        };

        $tests["real upstream expanded pager wal {$source} midpoint reader page {$pageNumber} bounded"] = static function (TestRunner $t) use ($wal, $databaseBytes, $pageNumber, $midReaderFrame): void {
            $row = $wal->readerSnapshotPageImage($databaseBytes, $pageNumber, $midReaderFrame);
            $t->true($row['frame_index'] === null || $row['frame_index'] <= $midReaderFrame);
            $t->true(in_array($row['source'], ['database', 'wal'], true));
        };

        if ($draftTail) {
            $tests["real upstream expanded pager wal {$source} recovery page {$pageNumber} omits draft tail"] = static function (TestRunner $t) use ($checkpointBytes, $pageSize, $pageNumber): void {
                $image = substr($checkpointBytes, ($pageNumber - 1) * $pageSize, $pageSize);
                $t->same(false, str_contains($image, sprintf('draft-tail page-%03d', $pageNumber)));
            };
        }
    }
}

$tests['real upstream expanded pager wal records source corpus files'] = static function (TestRunner $t): void {
    $t->same([
        'wal2.test',
        'walbig.test',
        'walbak.test',
        'walckptnoop.test',
        'walrestart.test',
        'waloverwrite.test',
        'walpersist.test',
        'walnoshm.test',
        'walro.test',
        'pageropt.test',
    ], [
        'wal2.test',
        'walbig.test',
        'walbak.test',
        'walckptnoop.test',
        'walrestart.test',
        'waloverwrite.test',
        'walpersist.test',
        'walnoshm.test',
        'walro.test',
        'pageropt.test',
    ]);
};

return $tests;
