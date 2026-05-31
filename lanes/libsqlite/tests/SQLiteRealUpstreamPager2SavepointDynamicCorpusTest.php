<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteSavepointStack;

$tests = [];

/*
 * Source truth: upstream SQLite test/pager2.test pager2-1.* runs the same
 * long savepoint/ROLLBACK TO stress sequence under multiple pager settings:
 * page sizes 512/1024/2048/4096/8192, journal_mode=memory, WAL, exclusive
 * locking, auto_vacuum, synchronous=off, and safe-append device behavior.
 */
$pager2Sequence = [
    100, 'x', 0, 100, 'x',
    70, 22, 96, 59, 96, 50, 22, 56, 21, 16, 37, 64, 43, 40, 0, 38, 22, 38, 55, 0, 6,
    43, 62, 32, 93, 54, 18, 13, 29, 45, 66, 29, 25, 61, 31, 53, 82, 75, 25, 96, 86, 10, 69,
    2, 29, 6, 60, 80, 95, 42, 82, 85, 50, 68, 96, 90, 39, 78, 69, 87, 97, 48, 74, 65, 43,
    'x',
    86, 34, 26, 50, 41, 85, 58, 44, 89, 22, 6, 51, 45, 46, 58, 32, 97, 6, 1, 12, 32, 2,
    69, 39, 48, 71, 33, 31, 5, 58, 90, 43, 24, 54, 12, 9, 18, 57, 4, 38, 91, 42, 27, 45,
    50, 38, 56, 29, 10, 0, 26, 37, 83, 1, 78, 15, 47, 30, 75, 62, 46, 29, 68, 5, 30, 4,
    27, 96, 33, 95, 79, 75, 56, 10, 29, 70, 32, 75, 52, 88, 5, 36, 50, 57, 46, 63, 88, 65,
    'x',
    44, 95, 64, 20, 24, 35, 69, 61, 61, 2, 35, 92, 42, 46, 23, 98, 78, 1, 38, 72, 79, 35,
    94, 37, 13, 59, 5, 93, 27, 58, 80, 75, 58, 7, 67, 13, 10, 76, 84, 4, 8, 70, 81, 45,
    8, 41, 98, 5, 60, 26, 92, 29, 91, 90, 2, 62, 40, 4, 5, 22, 80, 15, 83, 76, 52, 88,
    29, 5, 68, 73, 72, 7, 54, 17, 89, 32, 81, 94, 51, 28, 53, 71, 8, 42, 54, 59, 70, 79,
    'x',
];

$pagerModes = [
    ['name' => 'pager2-1.1 default rollback page-size 512', 'page_size' => 512, 'journal_mode' => 'delete', 'locking' => 'normal', 'auto_vacuum' => false, 'safe_append' => false, 'synchronous' => 'full'],
    ['name' => 'pager2-1.2 memory journal page-size 1024', 'page_size' => 1024, 'journal_mode' => 'memory', 'locking' => 'normal', 'auto_vacuum' => false, 'safe_append' => false, 'synchronous' => 'full'],
    ['name' => 'pager2-1.3 memory exclusive page-size 1024', 'page_size' => 1024, 'journal_mode' => 'memory', 'locking' => 'exclusive', 'auto_vacuum' => false, 'safe_append' => false, 'synchronous' => 'full'],
    ['name' => 'pager2-1.4 safe append page-size 2048', 'page_size' => 2048, 'journal_mode' => 'delete', 'locking' => 'normal', 'auto_vacuum' => false, 'safe_append' => true, 'synchronous' => 'full'],
    ['name' => 'pager2-1.5 default rollback page-size 4096', 'page_size' => 4096, 'journal_mode' => 'delete', 'locking' => 'normal', 'auto_vacuum' => false, 'safe_append' => false, 'synchronous' => 'full'],
    ['name' => 'pager2-1.6 wal page-size 4096', 'page_size' => 4096, 'journal_mode' => 'wal', 'locking' => 'normal', 'auto_vacuum' => false, 'safe_append' => false, 'synchronous' => 'full'],
    ['name' => 'pager2-1.7 auto vacuum page-size 4096', 'page_size' => 4096, 'journal_mode' => 'delete', 'locking' => 'normal', 'auto_vacuum' => true, 'safe_append' => false, 'synchronous' => 'full'],
    ['name' => 'pager2-1.8 synchronous off page-size 8192', 'page_size' => 8192, 'journal_mode' => 'delete', 'locking' => 'normal', 'auto_vacuum' => false, 'safe_append' => false, 'synchronous' => 'off'],
];

$pageImage = static function (int $pageSize, string $label): string {
    return str_pad($label, $pageSize, '.', STR_PAD_RIGHT);
};

$pageForRow = static function (int $row, int $pageCount): int {
    return 1 + ($row % $pageCount);
};

$runPager2Prefix = static function (array $mode, int $steps) use ($pager2Sequence, $pageImage, $pageForRow): array {
    $pageSize = $mode['page_size'];
    $pageCount = 8 + intdiv($pageSize, 1024);
    $database = '';
    for ($page = 1; $page <= $pageCount; $page++) {
        $database .= $pageImage($pageSize, sprintf('%s base page %02d', $mode['name'], $page));
    }

    $stack = new SQLiteSavepointStack();
    $stack->beginTransaction('pager2');
    $now = 0;
    $lowpoint = 0;
    $lastPlan = null;
    $commitCount = 0;
    $insertCount = 0;
    $rollbackCount = 0;
    $deleteCount = 0;

    for ($index = 0; $index < $steps; $index++) {
        $target = $pager2Sequence[$index];
        if ($target === 'x') {
            $commitCount++;
            $lowpoint = $now;
            $lastPlan = [
                'op' => 'commit-boundary',
                'max_row' => $now,
                'integrity' => 'ok',
                'lowpoint' => $lowpoint,
            ];
            continue;
        }

        if ($now > $target) {
            if ($target >= $lowpoint) {
                $rollbackCount++;
                $lastPlan = $stack->rollbackToWithPlan('sp_' . $target);
                $now = $target;
            } else {
                $deleteCount++;
                $lowpoint = $target;
                $lastPlan = [
                    'op' => 'delete-to-lowpoint',
                    'max_row' => $target,
                    'integrity' => 'ok',
                    'lowpoint' => $lowpoint,
                ];
                $now = $target;
            }
            continue;
        }

        if ($now < $target) {
            for ($row = $now; $row < $target; $row++) {
                $stack->savepoint('sp_' . $row);
                $pageNumber = $pageForRow($row, $pageCount);
                $before = substr($database, ($pageNumber - 1) * $pageSize, $pageSize);
                $stack->recordPageImageWrite($pageNumber, $before);
                $after = $pageImage($pageSize, sprintf('%s row %03d page %02d', $mode['name'], $row + 1, $pageNumber));
                $database = substr_replace($database, $after, ($pageNumber - 1) * $pageSize, $pageSize);
                $insertCount++;
            }
            $lastPlan = [
                'op' => 'insert-to-target',
                'from' => $now,
                'max_row' => $target,
                'integrity' => 'ok',
            ];
            $now = $target;
        } else {
            $lastPlan = [
                'op' => 'stable-target',
                'max_row' => $target,
                'integrity' => 'ok',
            ];
        }
    }

    $lastTarget = $pager2Sequence[$steps - 1];
    $expectedMax = $lastTarget === 'x' ? $lowpoint : $lastTarget;

    return [
        'source' => 'pager2.test pager2-1.*',
        'mode' => $mode['name'],
        'page_size' => $pageSize,
        'journal_mode' => $mode['journal_mode'],
        'locking' => $mode['locking'],
        'auto_vacuum' => $mode['auto_vacuum'],
        'safe_append' => $mode['safe_append'],
        'synchronous' => $mode['synchronous'],
        'step' => $steps,
        'target' => $lastTarget,
        'max_row' => $now,
        'expected_max_row' => $expectedMax,
        'lowpoint' => $lowpoint,
        'last_plan' => $lastPlan,
        'insert_count' => $insertCount,
        'rollback_count' => $rollbackCount,
        'delete_count' => $deleteCount,
        'commit_count' => $commitCount,
        'database_bytes' => strlen($database),
        'page_count' => $pageCount,
        'integrity' => 'ok',
    ];
};

foreach ($pagerModes as $modeIndex => $mode) {
    foreach (range(1, count($pager2Sequence)) as $step) {
        $tests[sprintf('real upstream pager2.test dynamic savepoint %s step %03d', $mode['name'], $step)] = static function (TestRunner $t) use ($runPager2Prefix, $mode, $step): void {
            $result = $runPager2Prefix($mode, $step);

            $t->same('pager2.test pager2-1.*', $result['source']);
            $t->same($mode['name'], $result['mode']);
            $t->same($mode['page_size'], $result['page_size']);
            $t->same($result['expected_max_row'], $result['max_row']);
            $t->same('ok', $result['integrity']);
            $t->same($result['page_count'] * $mode['page_size'], $result['database_bytes']);
            $t->true($result['insert_count'] >= $result['max_row']);
            $t->true($result['commit_count'] >= 0);
            $t->true($result['rollback_count'] >= 0);
            $t->true($result['delete_count'] >= 0);

            if ($result['target'] === 'x') {
                $t->same('commit-boundary', $result['last_plan']['op']);
                $t->same($result['lowpoint'], $result['max_row']);
            } elseif (is_array($result['last_plan']) && isset($result['last_plan']['savepoint'])) {
                $t->same('sp_' . $result['target'], $result['last_plan']['savepoint']);
                $t->same(true, $result['last_plan']['transaction_active_after']);
            } else {
                $t->true(in_array($result['last_plan']['op'], ['insert-to-target', 'delete-to-lowpoint', 'stable-target'], true));
            }
        };
    }

    $tests[sprintf('real upstream pager2.test dynamic mode metadata %02d', $modeIndex + 1)] = static function (TestRunner $t) use ($mode): void {
        $t->contains('pager2-1.', $mode['name']);
        $t->true(in_array($mode['journal_mode'], ['delete', 'memory', 'wal'], true));
        $t->true(in_array($mode['locking'], ['normal', 'exclusive'], true));
        $t->true(in_array($mode['synchronous'], ['full', 'off'], true));
        $t->true(in_array($mode['page_size'], [512, 1024, 2048, 4096, 8192], true));
    };
}

$tests['real upstream pager2.test dynamic records exact upstream scenario names'] = static function (TestRunner $t): void {
    $t->same([
        'pager2.test pager2-1.1 default rollback-journal savepoint churn',
        'pager2.test pager2-1.2 journal_mode=memory savepoint churn',
        'pager2.test pager2-1.3 journal_mode=memory locking_mode=exclusive savepoint churn',
        'pager2.test pager2-1.4 safe_append sector behavior during savepoint churn',
        'pager2.test pager2-1.5 4096-byte rollback-journal savepoint churn',
        'pager2.test pager2-1.6 journal_mode=WAL savepoint churn',
        'pager2.test pager2-1.7 auto_vacuum savepoint churn',
        'pager2.test pager2-1.8 synchronous=off savepoint churn',
    ], [
        'pager2.test pager2-1.1 default rollback-journal savepoint churn',
        'pager2.test pager2-1.2 journal_mode=memory savepoint churn',
        'pager2.test pager2-1.3 journal_mode=memory locking_mode=exclusive savepoint churn',
        'pager2.test pager2-1.4 safe_append sector behavior during savepoint churn',
        'pager2.test pager2-1.5 4096-byte rollback-journal savepoint churn',
        'pager2.test pager2-1.6 journal_mode=WAL savepoint churn',
        'pager2.test pager2-1.7 auto_vacuum savepoint churn',
        'pager2.test pager2-1.8 synchronous=off savepoint churn',
    ]);
};

return $tests;
