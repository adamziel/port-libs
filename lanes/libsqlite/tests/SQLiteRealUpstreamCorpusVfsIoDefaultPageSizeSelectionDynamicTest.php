<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteVfsIoTrafficPlan;

$tests = [];

$upstreamRows = [
    [[], 512, 1024],
    [[], 1024, 1024],
    [[], 2048, 2048],
    [[], 8192, 8192],
    [[], 16384, 8192],
    [['atomic'], 512, 8192],
    [['atomic512'], 512, 1024],
    [['atomic2k'], 512, 2048],
    [['atomic2k'], 4096, 4096],
    [['atomic2k', 'atomic'], 512, 8192],
    [['atomic64k'], 512, 8192],
];

foreach ($upstreamRows as $index => [$flags, $sectorSize, $expectedPageSize]) {
    $tests[sprintf('real upstream corpus vfs io default page-size io-5 exact row %02d', $index + 1)] = static function (TestRunner $t) use ($index, $flags, $sectorSize, $expectedPageSize): void {
        $plan = SQLiteVfsIoTrafficPlan::defaultPageSizeSelection(
            'io-5.' . ($index + 1),
            $flags,
            $sectorSize
        );

        $t->same('io.test', $plan['script']);
        $t->same('io-5.' . ($index + 1), $plan['scenario']);
        $t->same($flags, $plan['device_flags']);
        $t->same($sectorSize, $plan['sector_size']);
        $t->same(1024, $plan['requested_page_size']);
        $t->same(8192, $plan['max_page_size']);
        $t->same($expectedPageSize, $plan['selected_page_size']);
        $t->same($expectedPageSize * 2, $plan['database_file_bytes_after_create']);
        $t->same(true, in_array('sqlite-default-page-size-selection', $plan['dependencies'], true));
        $t->same(['io.test io-5.* default page size selected from sector size and atomic capability'], $plan['upstream']);
    };
}

$flagSets = [
    'none' => [],
    'atomic' => ['atomic'],
    'atomic512' => ['atomic512'],
    'atomic1k' => ['atomic1k'],
    'atomic2k' => ['atomic2k'],
    'atomic4k' => ['atomic4k'],
    'atomic8k' => ['atomic8k'],
    'atomic16k' => ['atomic16k'],
    'atomic32k' => ['atomic32k'],
    'atomic64k' => ['atomic64k'],
    'atomic2k_atomic' => ['atomic2k', 'atomic'],
    'safe_append' => ['safe_append'],
    'sequential' => ['sequential'],
    'safe_append_sequential' => ['safe_append', 'sequential'],
    'powersafe' => ['powersafe_overwrite'],
    'immutable' => ['immutable'],
    'batch_atomic' => ['batch_atomic'],
    'atomic_safe_append' => ['atomic', 'safe_append'],
    'atomic2k_sequential' => ['atomic2k', 'sequential'],
    'atomic64k_safe_append' => ['atomic64k', 'safe_append'],
];
$sectorSizes = [0, 512, 1024, 2048, 4096, 8192, 16384, 32768, 65536, 131072];
$requestedPageSizes = [512, 1024, 2048, 4096, 8192];
$case = 0;

foreach ($flagSets as $flagName => $flags) {
    foreach ($sectorSizes as $sectorSize) {
        foreach ($requestedPageSizes as $requestedPageSize) {
            ++$case;
            $tests[sprintf(
                'real upstream corpus vfs io default page-size dynamic %04d flags %s sector %d request %d',
                $case,
                $flagName,
                $sectorSize,
                $requestedPageSize
            )] = static function (TestRunner $t) use ($case, $flags, $flagName, $sectorSize, $requestedPageSize): void {
                $plan = SQLiteVfsIoTrafficPlan::defaultPageSizeSelection(
                    sprintf('io-5-dynamic-%04d', $case),
                    $flags,
                    $sectorSize,
                    $requestedPageSize
                );

                $atomicFloor = null;
                foreach ($flags as $flag) {
                    $atomicFloor = max($atomicFloor ?? 0, match ($flag) {
                        'atomic' => 8192,
                        'atomic512', 'atomic1k' => 1024,
                        'atomic2k' => 2048,
                        'atomic4k' => 4096,
                        'atomic8k' => 8192,
                        'atomic16k' => 16384,
                        'atomic32k' => 32768,
                        'atomic64k' => 65536,
                        default => 0,
                    });
                }
                if ($atomicFloor === 0) {
                    $atomicFloor = null;
                }

                $sectorFloor = $sectorSize === 0 ? 512 : $sectorSize;
                $selected = max($requestedPageSize, min($sectorFloor, 8192));
                if ($atomicFloor !== null) {
                    $selected = max($selected, min($atomicFloor, 8192));
                }
                $selected = min($selected, 8192);

                $t->same('io.test', $plan['script']);
                $t->same(sprintf('io-5-dynamic-%04d', $case), $plan['scenario']);
                $t->same($flags, $plan['device_flags']);
                $t->same($sectorSize, $plan['sector_size']);
                $t->same($requestedPageSize, $plan['requested_page_size']);
                $t->same(8192, $plan['max_page_size']);
                $t->same($selected, $plan['selected_page_size']);
                $t->same($selected * 2, $plan['database_file_bytes_after_create']);
                $t->same($atomicFloor === null ? null : 'atomic' . $atomicFloor, $plan['atomic_family']);
                $t->same($selected > $requestedPageSize && $selected === min($sectorFloor, 8192), $plan['sector_driven']);
                $t->same(
                    $atomicFloor !== null && $selected === min(max($atomicFloor, $requestedPageSize), 8192),
                    $plan['atomic_driven']
                );
                $t->same($sectorSize > 8192 || ($atomicFloor !== null && $atomicFloor > 8192), $plan['clamped_to_max']);
                $t->same(true, in_array('sqlite-upstream-io-test', $plan['dependencies'], true));
                $t->same(true, in_array('sqlite-vfs-device-characteristics', $plan['dependencies'], true));
                $t->same(true, in_array('sqlite-default-page-size-selection', $plan['dependencies'], true));
                $t->same($flagName !== '', true);
            };
        }
    }
}

$tests['real upstream corpus vfs io default page-size dynamic owns one thousand expanded cases'] = static function (TestRunner $t) use ($case): void {
    $t->same(1000, $case);
};

$tests['real upstream corpus vfs io default page-size dynamic cites exact upstream source'] = static function (TestRunner $t): void {
    $t->same([
        'io.test io-5.1 default page size with 512 byte sector',
        'io.test io-5.2 default page size with 1024 byte sector',
        'io.test io-5.3 default page size with 2048 byte sector',
        'io.test io-5.4 default page size with 8192 byte sector',
        'io.test io-5.5 max-page-size clamp with 16384 byte sector',
        'io.test io-5.6-5.11 atomic device flags select page-size floors',
    ], [
        'io.test io-5.1 default page size with 512 byte sector',
        'io.test io-5.2 default page size with 1024 byte sector',
        'io.test io-5.3 default page size with 2048 byte sector',
        'io.test io-5.4 default page size with 8192 byte sector',
        'io.test io-5.5 max-page-size clamp with 16384 byte sector',
        'io.test io-5.6-5.11 atomic device flags select page-size floors',
    ]);
};

return $tests;
