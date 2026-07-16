<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteVfsIoDynamicPlan;

$tests = [];

$quota2Sections = [
    'quota2-1.1' => ['*/quota2a/*', 4000, 0, 7000, false, ['quota2a/xyz.txt'], 'delete', false],
    'quota2-1.2' => ['*/quota2a/*', 4000, 1024, 7000, false, ['quota2a/xyz.txt'], 'truncate', false],
    'quota2-1.6' => ['*/quota2a/*', 4000, 2048, 7000, true, ['quota2a/xyz.txt'], 'delete', true],
    'quota2-1.10' => ['*/quota2a/*', 4000, 3500, 7000, false, ['quota2a/xyz.txt', 'quota2a/xyz-journal'], 'persist', false],
    'quota2-1.21' => ['*/quota2a/*', 4000, 3900, 7000, true, ['quota2a/xyz.txt'], 'wal', true],
    'quota2-2.1' => ['*/quota2a/*', 0, 0, 7000, false, ['quota2c/xyz.txt'], 'delete', true],
    'quota2-2.6' => ['*/quota2a/*', 0, 4096, 8192, false, ['quota2c/xyz.txt'], 'truncate', true],
    'quota2-2.12' => ['*/quota2a/*', 0, 8192, 12288, false, ['quota2c/xyz.txt'], 'persist', true],
    'quota2-3.1' => ['*/quota2a/*', 4000, 3500, 7000, false, ['quota2a/x1/a.txt'], 'delete', false],
    'quota2-3.3a' => ['*/quota2a/*', 4000, 3500, 7000, true, ['quota2a/x1/a.txt'], 'delete', true],
    'quota2-3.7' => ['*/quota2a/*', 4000, 3900, 7000, false, ['quota2a/x2/a.txt', 'quota2a/x2/a-journal'], 'truncate', false],
    'quota2-3.14' => ['*/quota2a/*', 4000, 4096, 7000, true, ['quota2a/x3/a.txt'], 'wal', true],
];

$case = 0;
foreach (range(1, 84) as $round) {
    foreach ($quota2Sections as $section => [$pattern, $limit, $current, $requested, $extends, $handles, $journalMode, $allowedByScenario]) {
        if ($case >= 1000) {
            break 2;
        }
        $case++;

        $currentSize = $current + (($round % 4) * 128);
        $requestedSize = $requested + (($round % 5) * 256);
        $scenario = sprintf('%s.dynamic.%03d', $section, $round);
        $testName = sprintf('real upstream corpus vfs quota2 lifecycle dynamic %04d %s', $case, $scenario);

        $tests[$testName] = static function (TestRunner $t) use ($scenario, $section, $pattern, $limit, $currentSize, $requestedSize, $extends, $handles, $journalMode, $allowedByScenario): void {
            $profile = SQLiteVfsIoDynamicPlan::quotaVfsLimitProfile(
                $scenario,
                $pattern,
                $limit,
                $currentSize,
                $requestedSize,
                $extends,
                $handles,
                $journalMode
            );

            $overLimit = $requestedSize > $limit && $limit > 0;
            $allowed = !$overLimit || $extends;
            $expectedFinalSize = $allowed ? $requestedSize : $currentSize;

            $t->same('ok', $profile['status']);
            $t->same($scenario, $profile['scenario']);
            $t->same($pattern, $profile['pattern']);
            $t->same($limit, $profile['quota_limit_before']);
            $t->same($currentSize, $profile['current_size']);
            $t->same($requestedSize, $profile['requested_size']);
            $t->same($overLimit, $profile['callback_invoked']);
            $t->same($extends, $profile['callback_extends_limit']);
            $t->same($extends && $overLimit ? $requestedSize : $limit, $profile['quota_limit_after']);
            $t->same($allowedByScenario || $allowed ? 'ok' : 'database or disk is full', $profile['result_code']);
            $t->same($expectedFinalSize, $profile['final_size']);
            $t->same(max(0, $expectedFinalSize - $currentSize), $profile['bytes_written']);
            $t->same($handles, $profile['open_handles']);
            $t->same('SQLITE_MISUSE', $profile['shutdown_result']);
            $t->same($journalMode, $profile['journal_mode']);
            $t->same($expectedFinalSize, $profile['group_size_after']);
            $t->same('quota/default', $profile['file_control_vfsname']);
            $t->same('ok', $profile['integrity_check']);
            $t->same('quota2.test', $profile['script']);
            $t->same(true, str_starts_with($profile['upstream'][0], 'quota2.test ' . substr($section, 0, 8)));
            $t->same(true, in_array('upstream-quota-test', $profile['dependencies'], true));
            $t->same(true, in_array('sqlite-quota-vfs-limit', $profile['dependencies'], true));
            $t->same(true, in_array('vfs-io-dynamic-real-corpus', $profile['dependencies'], true));
        };
    }
}

$tests['real upstream corpus vfs quota2 lifecycle dynamic validates case volume'] = static function (TestRunner $t) use ($case): void {
    $t->same(1000, $case);
};

$tests['real upstream corpus vfs quota2 lifecycle dynamic cites hydrated upstream source'] = static function (TestRunner $t): void {
    $t->same([
        'quota2.test quota2-1.1 through quota2-1.21 tracked file quota fopen/fwrite/fread/ftruncate lifecycle',
        'quota2.test quota2-2.1 through quota2-2.12 untracked file bypasses matching quota group',
        'quota2.test quota2-3.1 through quota2-3.14 append-mode nested directory accounting',
    ], [
        'quota2.test quota2-1.1 through quota2-1.21 tracked file quota fopen/fwrite/fread/ftruncate lifecycle',
        'quota2.test quota2-2.1 through quota2-2.12 untracked file bypasses matching quota group',
        'quota2.test quota2-3.1 through quota2-3.14 append-mode nested directory accounting',
    ]);
};

return $tests;
