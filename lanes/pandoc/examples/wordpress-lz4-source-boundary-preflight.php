<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\Pandoc\ArchiveCompressionStream;
use PortLibs\Pandoc\Lz4Frame;
use PortLibs\Pandoc\TarArchive;

$primaryArchive = TarArchive::fromEntries([
    [
        'name' => 'packet/manifest.json',
        'data' => '{"source":"wordpress-lz4-source-boundary","target":"review"}',
    ],
    [
        'name' => 'packet/content.md',
        'data' => "# LZ4 source boundary\n\nPrimary packet for WordPress archive review.\n",
    ],
]);
$unexpectedArchive = TarArchive::fromEntries([
    [
        'name' => 'packet/unexpected-second.md',
        'data' => "Unexpected second package frame for reviewer triage.\n",
    ],
]);

$primaryBytes = $primaryArchive->bytes();
$unexpectedBytes = $unexpectedArchive->bytes();
$stream = Lz4Frame::skippableFrame('wordpress-lz4-boundary-review', 5)
    . Lz4Frame::build($primaryBytes, [
        'contentChecksum' => true,
        'contentSize' => true,
    ])
    . Lz4Frame::build($unexpectedBytes, [
        'contentChecksum' => true,
        'contentSize' => true,
    ]);

$inspection = ArchiveCompressionStream::inspectLz4FrameSourceBoundaryPolicy(
    $stream,
    ArchiveCompressionStream::FORMAT_LZ4_TAR,
    strlen($primaryBytes) + strlen($unexpectedBytes)
);

$strictPackageOpenBlocked = false;
try {
    ArchiveCompressionStream::openTar($stream, ArchiveCompressionStream::FORMAT_LZ4_TAR);
} catch (RuntimeException) {
    $strictPackageOpenBlocked = true;
}

if (in_array('--self-test', $argv, true)) {
    if ($inspection['policy'] !== 'review-before-conversion') {
        throw new RuntimeException('Expected concatenated LZ4 package frames to require review');
    }

    if (($inspection['standalonePackageFrameCount'] ?? null) !== 2) {
        throw new RuntimeException('Expected both LZ4 data frames to be classified as standalone packages');
    }

    if (($inspection['frames'][1]['entryNames'] ?? null) !== ['packet/manifest.json', 'packet/content.md']) {
        throw new RuntimeException('Expected primary LZ4 frame package entries to stay visible for review');
    }

    if (($inspection['frames'][2]['entryNames'] ?? null) !== ['packet/unexpected-second.md']) {
        throw new RuntimeException('Expected second LZ4 frame package entries to stay visible for review');
    }

    if (array_key_exists('data', $inspection['frames'][1]) || array_key_exists('data', $inspection['frames'][2])) {
        throw new RuntimeException('Expected LZ4 source-boundary policy to avoid exposing package payload bytes');
    }

    if (!$strictPackageOpenBlocked) {
        throw new RuntimeException('Expected strict TAR handoff to reject concatenated standalone package frames');
    }

    echo "lz4 source-boundary preflight self-test passed\n";
    exit(0);
}

echo "LZ4 source-boundary preflight for WordPress archive review:\n";
echo 'policy=' . $inspection['policy'] . "\n";
echo 'diagnostics=' . implode(',', $inspection['diagnostics']) . "\n";
echo 'standalonePackageFrames=' . $inspection['standalonePackageFrameCount'] . "\n";
echo 'combinedPackageStatus=' . $inspection['combinedPackageStatus'] . "\n";
echo 'strictPackageOpenBlocked=' . ($strictPackageOpenBlocked ? 'yes' : 'no') . "\n";
