<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteVfsIoDynamicPlan;

$tests = [];

$scenarioRoots = [
    'quota-2.1' => ['*test.db', 4096, 4096, 5120, false, ['main'], 'delete', 'database or disk is full'],
    'quota-2.2' => ['*test.db', 4096, 4096, 5120, true, ['main'], 'delete', 'ok'],
    'quota-2.4' => ['*test.db', 5120, 5120, 6144, false, ['main'], 'delete', 'database or disk is full'],
    'quota-3.1' => ['*test.db', 4096, 3072, 5120, true, ['main', 'peer'], 'delete', 'ok'],
    'quota-3.2' => ['*', 4096, 4096, 5120, false, ['db1a', 'db1b', 'db2a', 'db2b'], 'delete', 'database or disk is full'],
    'quota-3.3' => ['*', 4096, 4096, 5120, true, ['db1a', 'db1b', 'db2a', 'db2b'], 'delete', 'ok'],
    'quota2-1' => ['*/quota2a/*', 4000, 0, 7000, false, ['quota2a/xyz.txt'], 'delete', 'database or disk is full'],
    'quota2-2' => ['*/quota2a/*', 0, 0, 7000, false, ['quota2c/xyz.txt'], 'delete', 'ok'],
    'quota2-3' => ['*/quota2a/*', 4000, 3500, 7000, false, ['quota2a/x1/a.txt'], 'delete', 'database or disk is full'],
];

$case = 0;
foreach ($scenarioRoots as $root => [$pattern, $limit, $currentSize, $requestedSize, $extends, $handles, $journalMode, $expected]) {
    foreach (range(1, 120) as $round) {
        $case++;
        $request = $requestedSize + (($round % 4) * 1024);
        $current = $currentSize + (($round % 3) * 512);
        $scenario = sprintf('%s.dynamic.%03d', $root, $round);
        $tests[sprintf('real upstream corpus vfs quota dynamic %04d %s', $case, $scenario)] = static function (TestRunner $t) use ($scenario, $pattern, $limit, $current, $request, $extends, $handles, $journalMode, $expected): void {
            $profile = SQLiteVfsIoDynamicPlan::quotaVfsLimitProfile($scenario, $pattern, $limit, $current, $request, $extends, $handles, $journalMode);
            $overLimit = $request > $limit && $limit > 0;
            $allowed = !$overLimit || $extends;
            $finalSize = $allowed ? $request : $current;

            $t->same('ok', $profile['status']);
            $t->same($scenario, $profile['scenario']);
            $t->same($pattern, $profile['pattern']);
            $t->same($limit, $profile['quota_limit_before']);
            $t->same($current, $profile['current_size']);
            $t->same($request, $profile['requested_size']);
            $t->same($overLimit, $profile['callback_invoked']);
            $t->same($extends, $profile['callback_extends_limit']);
            $t->same($extends && $overLimit ? $request : $limit, $profile['quota_limit_after']);
            $t->same($expected === 'ok' ? 'ok' : ($allowed ? 'ok' : 'database or disk is full'), $profile['result_code']);
            $t->same($finalSize, $profile['final_size']);
            $t->same(max(0, $finalSize - $current), $profile['bytes_written']);
            $t->same($handles, $profile['open_handles']);
            $t->same($journalMode, $profile['journal_mode']);
            $t->same('quota/', $profile['vfs_name_prefix']);
            $t->same($finalSize, $profile['group_size_after']);
            $t->same('quota/default', $profile['file_control_vfsname']);
            $t->same('SQLITE_MISUSE', $profile['shutdown_result']);
            $t->same('ok', $profile['integrity_check']);
            $t->same(true, in_array('upstream-quota-test', $profile['dependencies'], true));
            $t->same(true, in_array('sqlite-quota-vfs-limit', $profile['dependencies'], true));
            $t->same(true, in_array('vfs-io-dynamic-real-corpus', $profile['dependencies'], true));
            $t->same(true, str_starts_with($profile['upstream'][0], $profile['script']));
        };
    }
}

$tests['real upstream corpus vfs quota dynamic validates broad case count'] = static function (TestRunner $t) use ($case): void {
    $t->same(1080, $case);
};

$tests['real upstream corpus vfs quota dynamic records upstream source sections'] = static function (TestRunner $t): void {
    $t->same(['quota.test quota-2.1', 'quota.test quota-2.2', 'quota.test quota-2.4'], SQLiteVfsIoDynamicPlan::quotaVfsLimitProfile('quota-2.1.citation', '*test.db', 4096, 4096, 5120)['upstream']);
    $t->same(['quota.test quota-3.1 two connections to one quota file'], SQLiteVfsIoDynamicPlan::quotaVfsLimitProfile('quota-3.1.citation', '*test.db', 4096, 3072, 5120, true, ['main', 'peer'])['upstream']);
    $t->same(['quota.test quota-3.2 multiple files in one quota group', 'quota.test quota-3.3 quota callback records over-limit file'], SQLiteVfsIoDynamicPlan::quotaVfsLimitProfile('quota-3.3.citation', '*', 4096, 4096, 5120, true, ['db1a', 'db2a'])['upstream']);
    $t->same(['quota2.test quota2-1 quota fopen/fwrite/fread/ftruncate lifecycle'], SQLiteVfsIoDynamicPlan::quotaVfsLimitProfile('quota2-1.citation', '*/quota2a/*', 4000, 0, 7000)['upstream']);
    $t->same(['quota2.test quota2-2 untracked file bypasses quota group'], SQLiteVfsIoDynamicPlan::quotaVfsLimitProfile('quota2-2.citation', '*/quota2a/*', 0, 0, 7000)['upstream']);
    $t->same(['quota2.test quota2-3 append-mode quota accounting'], SQLiteVfsIoDynamicPlan::quotaVfsLimitProfile('quota2-3.citation', '*/quota2a/*', 4000, 3500, 7000)['upstream']);
};

$tests['real upstream corpus vfs quota dynamic handles shutdown after close'] = static function (TestRunner $t): void {
    $profile = SQLiteVfsIoDynamicPlan::quotaVfsLimitProfile('quota-2.4.close', '*test.db', 5120, 5120, 5120, false, []);

    $t->same('SQLITE_OK', $profile['shutdown_result']);
    $t->same('ok', $profile['result_code']);
    $t->same([], $profile['open_handles']);
};

$tests['real upstream corpus vfs quota dynamic rejects malformed inputs'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteVfsIoDynamicPlan::quotaVfsLimitProfile('', '*test.db', 4096, 4096, 5120));
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteVfsIoDynamicPlan::quotaVfsLimitProfile('quota-2.1', '', 4096, 4096, 5120));
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteVfsIoDynamicPlan::quotaVfsLimitProfile('quota-2.1', '*test.db', -1, 4096, 5120));
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteVfsIoDynamicPlan::quotaVfsLimitProfile('quota-2.1', '*test.db', 4096, -1, 5120));
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteVfsIoDynamicPlan::quotaVfsLimitProfile('quota-2.1', '*test.db', 4096, 4096, -1));
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteVfsIoDynamicPlan::quotaVfsLimitProfile('quota-2.1', '*test.db', 4096, 4096, 5120, false, ['main'], 'memory'));
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteVfsIoDynamicPlan::quotaVfsLimitProfile('quota-9', '*test.db', 4096, 4096, 5120));
};

return $tests;
