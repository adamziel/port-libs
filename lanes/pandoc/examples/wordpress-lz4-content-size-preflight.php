<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\Pandoc\ArchiveCompressionStream;
use PortLibs\Pandoc\Lz4Frame;
use PortLibs\Pandoc\TarArchive;

$lz4HeaderChecksum = static fn (string $descriptor): string => chr((intval(hash('xxh32', $descriptor), 16) >> 8) & 0xff);

$archive = TarArchive::fromEntries([
    [
        'name' => 'packet/manifest.json',
        'data' => '{"source":"wordpress-lz4-content-size","target":"review"}',
    ],
    [
        'name' => 'packet/content.md',
        'data' => "# LZ4 content-size preflight\n\nReady for WordPress archive review.\n",
    ],
]);
$tarBytes = $archive->bytes();
$valid = Lz4Frame::build($tarBytes, [
    'blockChecksum' => true,
    'contentChecksum' => true,
    'contentSize' => true,
]);
$declaredSize = strlen($tarBytes) + 5;
$mismatched = substr_replace($valid, pack('V2', $declaredSize, 0), 6, 8);
$mismatched = substr_replace($mismatched, $lz4HeaderChecksum(substr($mismatched, 4, 10)), 14, 1);
$inspection = ArchiveCompressionStream::inspectLz4ContentSizePolicy(
    Lz4Frame::skippableFrame('wordpress-import-review', 4) . $mismatched
);
$strictDecodeBlocked = false;
try {
    Lz4Frame::decode($mismatched);
} catch (RuntimeException) {
    $strictDecodeBlocked = true;
}

if (in_array('--self-test', $argv, true)) {
    if ($inspection['handoffPolicy'] !== 'review-before-conversion') {
        throw new RuntimeException('Expected mismatched LZ4 content size to require review');
    }

    if (($inspection['stream']['frames'][1]['contentSizeDelta'] ?? null) !== 5) {
        throw new RuntimeException('Expected LZ4 content-size delta to be preserved for review');
    }

    if (array_key_exists('data', $inspection['stream']['frames'][1])) {
        throw new RuntimeException('Expected LZ4 content-size policy to avoid exposing package payload bytes');
    }

    if (!$strictDecodeBlocked) {
        throw new RuntimeException('Expected strict LZ4 decode to reject the mismatched content-size frame');
    }

    echo "lz4 content-size preflight self-test passed\n";
    exit(0);
}

echo "LZ4 content-size preflight for WordPress archive review:\n";
echo 'handoffPolicy=' . $inspection['handoffPolicy'] . "\n";
echo 'mismatchedFrames=' . $inspection['mismatchedContentSizeFrameCount'] . "\n";
echo 'declaredSize=' . $inspection['stream']['frames'][1]['contentSize'] . "\n";
echo 'decodedSize=' . $inspection['stream']['frames'][1]['decodedDataSize'] . "\n";
echo 'strictDecodeBlocked=' . ($strictDecodeBlocked ? 'yes' : 'no') . "\n";
