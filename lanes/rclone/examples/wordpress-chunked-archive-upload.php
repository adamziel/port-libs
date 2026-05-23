<?php

declare(strict_types=1);

require __DIR__ . '/../../../tools/bootstrap.php';

use PortLibs\Rclone\ChunkSizeCalculator;

$maxProviderParts = 10_000;
$defaultChunkSize = ChunkSizeCalculator::mebi(5);

// A large consolidated migration archive: WXR, SQL, and uploads tar stream.
$archiveSize = 120_864_818_840;
$chunkSize = ChunkSizeCalculator::calculate($archiveSize, $maxProviderParts, $defaultChunkSize);
$ranges = ChunkSizeCalculator::partRanges($archiveSize, $chunkSize);

return [
    'archivePath' => 'exports/site-full-migration.tar',
    'archiveSize' => $archiveSize,
    'maxProviderParts' => $maxProviderParts,
    'defaultChunkSize' => $defaultChunkSize,
    'chunkSize' => $chunkSize,
    'partCount' => ChunkSizeCalculator::partsFor($archiveSize, $chunkSize),
    'firstPart' => $ranges[0],
    'lastPart' => $ranges[array_key_last($ranges)],
    'withinProviderLimit' => ChunkSizeCalculator::partsFor($archiveSize, $chunkSize) <= $maxProviderParts,
    'streamingChunkSize' => ChunkSizeCalculator::calculate(-1, $maxProviderParts, $defaultChunkSize),
    'streamingPartCount' => ChunkSizeCalculator::partsFor(-1, $defaultChunkSize),
];
