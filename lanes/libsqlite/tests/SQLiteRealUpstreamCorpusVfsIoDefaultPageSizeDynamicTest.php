<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteVfsIoTrafficPlan;

$tests = [];

$flagSets = [
    'none' => [],
    'atomic' => ['atomic'],
    'atomic512' => ['atomic512'],
    'atomic2k' => ['atomic2K'],
    'atomic64k' => ['atomic64K'],
    'atomic-plus-atomic2k' => ['atomic2K', 'atomic'],
    'safe-append' => ['safe_append'],
    'sequential' => ['sequential'],
    'atomic-safe-append' => ['atomic', 'safe_append'],
    'atomic-sequential' => ['atomic', 'sequential'],
    'atomic2k-safe-append' => ['atomic2K', 'safe_append'],
    'atomic64k-sequential' => ['atomic64K', 'sequential'],
];

$requestedPageSizes = [512, 1024, 2048, 4096, 8192, 16384, 32768];
$sectorSizes = [512, 1024, 2048, 4096, 8192];
$syncModes = ['off', 'normal', 'full'];
$pageShapes = [
    'single-page' => [1, 0],
    'two-page' => [2, 0],
    'append-page' => [1, 1],
    'read-only' => [0, 0],
];

$expectedDefaultPageSize = static function (array $flags, int $requestedPageSize): int {
    $normalized = array_map(static fn (string $flag): string => strtolower($flag), $flags);
    if (in_array('atomic', $normalized, true)) {
        return max($requestedPageSize, 8192);
    }
    if (in_array('atomic64k', $normalized, true)) {
        return max($requestedPageSize, 1024);
    }
    if (in_array('atomic2k', $normalized, true)) {
        return max($requestedPageSize, 2048);
    }
    if (in_array('atomic512', $normalized, true)) {
        return max($requestedPageSize, 1024);
    }

    return $requestedPageSize;
};

$caseNumber = 0;
foreach ($flagSets as $flagLabel => $flags) {
    foreach ($requestedPageSizes as $requestedPageSize) {
        foreach ($sectorSizes as $sectorSize) {
            foreach ($syncModes as $syncMode) {
                foreach ($pageShapes as $shapeLabel => [$changedPages, $appendedPages]) {
                    ++$caseNumber;
                    $scenario = "io-5.dynamic.{$caseNumber}.{$flagLabel}.{$requestedPageSize}.{$sectorSize}.{$syncMode}.{$shapeLabel}";
                    $expectedDefault = $expectedDefaultPageSize($flags, $requestedPageSize);

                    $tests["real upstream corpus vfs io default page size dynamic {$scenario}"] = static function (TestRunner $t) use ($scenario, $flags, $requestedPageSize, $changedPages, $appendedPages, $sectorSize, $syncMode, $expectedDefault): void {
                        $plan = SQLiteVfsIoTrafficPlan::transaction(
                            $scenario,
                            $requestedPageSize,
                            $changedPages,
                            $appendedPages,
                            $flags,
                            $sectorSize,
                            $syncMode
                        );

                        $t->same($scenario, $plan['scenario']);
                        $t->same($requestedPageSize, $plan['page_size']);
                        $t->same($sectorSize, $plan['sector_size']);
                        $t->same($expectedDefault, $plan['default_page_size']);
                        $t->same($syncMode === 'off' ? 0 : count($plan['sync_targets']), $plan['syncs']);
                        $t->same($changedPages + $appendedPages + ($changedPages > 0 ? 1 : 0), $plan['database_writes']);
                        $t->same(true, in_array('sqlite-upstream-io-test', $plan['dependencies'], true));
                        $t->same(true, in_array('sqlite-vfs-device-characteristics', $plan['dependencies'], true));
                    };
                }
            }
        }
    }
}

$upstreamIo5Cases = [
    'io-5.1 no flags sector512 selects 1024' => [[], 512, 1024, 1024],
    'io-5.2 no flags sector1024 selects 1024' => [[], 1024, 1024, 1024],
    'io-5.3 no flags sector2048 selects 2048' => [[], 2048, 2048, 2048],
    'io-5.4 no flags sector8192 selects 8192' => [[], 8192, 8192, 8192],
    'io-5.5 no flags sector16384 caps at requested 8192' => [[], 16384, 8192, 8192],
    'io-5.6 atomic sector512 selects 8192' => [['atomic'], 512, 1024, 8192],
    'io-5.7 atomic512 sector512 selects 1024' => [['atomic512'], 512, 1024, 1024],
    'io-5.8 atomic2k sector512 selects 2048' => [['atomic2K'], 512, 1024, 2048],
    'io-5.9 atomic2k sector4096 selects requested 4096' => [['atomic2K'], 4096, 4096, 4096],
    'io-5.10 atomic2k plus atomic selects 8192' => [['atomic2K', 'atomic'], 512, 1024, 8192],
    'io-5.11 atomic64k sector512 selects 1024' => [['atomic64K'], 512, 1024, 1024],
];

foreach ($upstreamIo5Cases as $name => [$flags, $sectorSize, $requestedPageSize, $expectedDefault]) {
    $tests["real upstream corpus vfs io default page size canonical {$name}"] = static function (TestRunner $t) use ($name, $flags, $sectorSize, $requestedPageSize, $expectedDefault): void {
        $plan = SQLiteVfsIoTrafficPlan::transaction($name, $requestedPageSize, 1, 0, $flags, $sectorSize, 'full');

        $t->same($expectedDefault, $plan['default_page_size']);
        $t->same($requestedPageSize, $plan['page_size']);
        $t->same($sectorSize, $plan['sector_size']);
        $t->same('io-5', substr($name, 0, 4));
        $t->same(true, in_array('sqlite-upstream-io-test', $plan['dependencies'], true));
        $t->same(true, in_array('sqlite-pager-io-traffic', $plan['dependencies'], true));
    };
}

$tests['real upstream corpus vfs io default page size rejects non power of two page size'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn (): array => SQLiteVfsIoTrafficPlan::transaction('io-5.invalid-page-size', 1536, 1));
};

$tests['real upstream corpus vfs io default page size rejects non power of two sector size'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn (): array => SQLiteVfsIoTrafficPlan::transaction('io-5.invalid-sector-size', 1024, 1, 0, [], 1536));
};

return $tests;
