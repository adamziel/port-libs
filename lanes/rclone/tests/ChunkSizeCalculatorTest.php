<?php

declare(strict_types=1);

use PortLibs\Rclone\ChunkSizeCalculator;

$cases = [
    [
        'name' => 'streaming file',
        'size' => -1,
        'maxParts' => 10_000,
        'defaultChunkSize' => ChunkSizeCalculator::mebi(10),
        'want' => ChunkSizeCalculator::mebi(10),
    ],
    [
        'name' => 'default size returned when file size is small enough',
        'size' => 1_000,
        'maxParts' => 10_000,
        'defaultChunkSize' => ChunkSizeCalculator::mebi(10),
        'want' => ChunkSizeCalculator::mebi(10),
    ],
    [
        'name' => 'default size returned when file size is just 1 byte small enough',
        'size' => ChunkSizeCalculator::mebi(100_000) - 1,
        'maxParts' => 10_000,
        'defaultChunkSize' => ChunkSizeCalculator::mebi(10),
        'want' => ChunkSizeCalculator::mebi(10),
    ],
    [
        'name' => 'no rounding up when everything divides evenly',
        'size' => ChunkSizeCalculator::mebi(1_000_000),
        'maxParts' => 10_000,
        'defaultChunkSize' => ChunkSizeCalculator::mebi(100),
        'want' => ChunkSizeCalculator::mebi(100),
    ],
    [
        'name' => 'rounding up to nearest MiB when not quite enough parts',
        'size' => ChunkSizeCalculator::mebi(1_000_000),
        'maxParts' => 9_999,
        'defaultChunkSize' => ChunkSizeCalculator::mebi(100),
        'want' => ChunkSizeCalculator::mebi(101),
    ],
    [
        'name' => 'rounding up to nearest MiB when one extra byte',
        'size' => ChunkSizeCalculator::mebi(1_000_000) + 1,
        'maxParts' => 10_000,
        'defaultChunkSize' => ChunkSizeCalculator::mebi(100),
        'want' => ChunkSizeCalculator::mebi(101),
    ],
    [
        'name' => 'expected MiB value when rounding sets to absolute minimum',
        'size' => ChunkSizeCalculator::mebi(1) - 1,
        'maxParts' => 1,
        'defaultChunkSize' => ChunkSizeCalculator::mebi(1),
        'want' => ChunkSizeCalculator::mebi(1),
    ],
    [
        'name' => 'expected MiB value when rounding to absolute min with extra',
        'size' => ChunkSizeCalculator::mebi(1) + 1,
        'maxParts' => 1,
        'defaultChunkSize' => ChunkSizeCalculator::mebi(1),
        'want' => ChunkSizeCalculator::mebi(2),
    ],
    [
        'name' => 'issue from forum 1',
        'size' => 120_864_818_840,
        'maxParts' => 10_000,
        'defaultChunkSize' => 5 * 1_024 * 1_024,
        'want' => ChunkSizeCalculator::mebi(12),
    ],
];

$tests = [];
foreach ($cases as $case) {
    $tests['chunk size calculator maps upstream ' . $case['name']] = static function (TestRunner $t) use ($case): void {
        $got = ChunkSizeCalculator::calculate(
            $case['size'],
            $case['maxParts'],
            $case['defaultChunkSize'],
        );

        $t->same($case['want'], $got);
        if ($case['size'] < 0) {
            $t->same(null, ChunkSizeCalculator::partsFor($case['size'], $got));

            return;
        }

        $parts = ChunkSizeCalculator::partsFor($case['size'], $got);
        $t->true($parts !== null && $parts <= $case['maxParts']);

        if ($got > $case['defaultChunkSize']) {
            $smallerParts = ChunkSizeCalculator::partsFor($case['size'], $got - ChunkSizeCalculator::MEBI);
            $t->true($smallerParts !== null && $smallerParts > $case['maxParts']);
        }
    };
}

$tests['chunk size calculator exposes fixed part ranges for wordpress archive uploads'] = static function (TestRunner $t): void {
    $example = require __DIR__ . '/../examples/wordpress-chunked-archive-upload.php';

    $t->same(ChunkSizeCalculator::mebi(12), $example['chunkSize']);
    $t->same(9_606, $example['partCount']);
    $t->same(['offset' => 0, 'length' => ChunkSizeCalculator::mebi(12)], $example['firstPart']);
    $t->same(['offset' => 120_858_869_760, 'length' => 5_949_080], $example['lastPart']);
    $t->same(true, $example['withinProviderLimit']);
    $t->same(null, $example['streamingPartCount']);
    $t->same(ChunkSizeCalculator::mebi(5), $example['streamingChunkSize']);
};

return $tests;
