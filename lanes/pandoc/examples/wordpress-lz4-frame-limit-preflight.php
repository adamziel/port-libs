<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\Pandoc\ArchiveCompressionStream;
use PortLibs\Pandoc\Lz4Frame;
use PortLibs\Pandoc\TarArchive;

$archive = TarArchive::fromEntries([
    [
        'name' => 'packet/manifest.json',
        'data' => '{"source":"wordpress-lz4-frame-limit","target":"review"}',
    ],
    [
        'name' => 'packet/content.md',
        'data' => "# LZ4 frame-limit preflight\n\nReady for WordPress archive review.\n",
    ],
]);
$tarBytes = $archive->bytes();
$firstLength = 512;
$secondLength = 1536;
$thirdOffset = $firstLength + $secondLength;

$stream = Lz4Frame::skippableFrame('wordpress-lz4-frame-limit', 8)
    . Lz4Frame::build(substr($tarBytes, 0, $firstLength), [
        'contentChecksum' => true,
        'contentSize' => true,
    ])
    . Lz4Frame::skippableFrame('oversized-decoded-frame-review', 9)
    . Lz4Frame::build(substr($tarBytes, $firstLength, $secondLength), [
        'blockChecksum' => true,
        'contentChecksum' => true,
        'contentSize' => true,
    ])
    . Lz4Frame::build(substr($tarBytes, $thirdOffset), [
        'contentChecksum' => true,
        'contentSize' => true,
    ]);

$inspection = ArchiveCompressionStream::inspectLz4DataFrameLimitPolicy(
    $stream,
    ArchiveCompressionStream::FORMAT_LZ4_TAR,
    2,
    1024,
    strlen($tarBytes)
);

if (in_array('--self-test', $argv, true)) {
    if ($inspection['handoffPolicy'] !== 'review-before-conversion') {
        throw new RuntimeException('Expected over-limit LZ4 data frames to require review');
    }

    if (($inspection['countOverLimitDataFrameCount'] ?? null) !== 1) {
        throw new RuntimeException('Expected one LZ4 data frame over the count threshold');
    }

    if (($inspection['byteOverLimitFrameCount'] ?? null) !== 1) {
        throw new RuntimeException('Expected one LZ4 data frame over the decoded-byte threshold');
    }

    if (($inspection['entryNames'] ?? null) !== ['packet/manifest.json', 'packet/content.md']) {
        throw new RuntimeException('Expected TAR package entries to remain visible for review');
    }

    foreach ($inspection['frames'] as $frame) {
        if (array_key_exists('data', $frame)) {
            throw new RuntimeException('Expected LZ4 frame-limit policy to avoid exposing package payload bytes');
        }
    }

    echo "lz4 frame-limit preflight self-test passed\n";
    exit(0);
}

echo "LZ4 frame-limit preflight for WordPress archive review:\n";
echo 'handoffPolicy=' . $inspection['handoffPolicy'] . "\n";
echo 'diagnostics=' . implode(',', $inspection['diagnostics']) . "\n";
echo 'dataFrames=' . $inspection['dataFrameCount'] . "\n";
echo 'countOverLimitFrames=' . $inspection['countOverLimitDataFrameCount'] . "\n";
echo 'byteOverLimitFrames=' . $inspection['byteOverLimitFrameCount'] . "\n";
echo 'largestFrameDecodedSize=' . $inspection['largestFrameDecodedSize'] . "\n";
