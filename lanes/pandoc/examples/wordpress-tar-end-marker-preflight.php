<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\Pandoc\ArchiveCompressionStream;
use PortLibs\Pandoc\GzipStream;
use PortLibs\Pandoc\TarArchive;

$archive = TarArchive::fromEntries([
    [
        'name' => 'packet/manifest.json',
        'data' => '{"source":"wordpress-tar-end-marker","target":"review"}',
    ],
    [
        'name' => 'packet/content.md',
        'data' => "# TAR end-marker preflight\n\nReady for WordPress archive review.\n",
    ],
]);

$trailingPayload = "detached reviewer bytes after tar end marker\n";
$tailedTar = $archive->bytes() . str_pad($trailingPayload, 512, "\0");
$packet = GzipStream::build($tailedTar, [
    'filename' => 'wordpress-end-marker-review.tar',
    'comment' => 'non-zero tar tail stays review-only',
]);

$inspection = ArchiveCompressionStream::inspectTarEndMarkerPolicy(
    $packet,
    ArchiveCompressionStream::FORMAT_GZIP_TAR,
    strlen($tailedTar)
);

$strictDecodeBlocked = false;
try {
    ArchiveCompressionStream::openTar(
        $packet,
        ArchiveCompressionStream::FORMAT_GZIP_TAR,
        strlen($tailedTar)
    );
} catch (RuntimeException) {
    $strictDecodeBlocked = true;
}

if (in_array('--self-test', $argv, true)) {
    if ($inspection['handoffPolicy'] !== 'review-before-conversion') {
        throw new RuntimeException('Expected non-zero TAR trailing bytes to require review');
    }

    if ($inspection['diagnostics'] !== ['tar-end-marker-trailing-non-zero-bytes']) {
        throw new RuntimeException('Expected TAR trailing-byte diagnostic to be preserved');
    }

    if (($inspection['stream']['members'][0]['filename'] ?? null) !== 'wordpress-end-marker-review.tar') {
        throw new RuntimeException('Expected gzip member filename provenance to be preserved');
    }

    if (array_key_exists('tarBytes', $inspection) || array_key_exists('archive', $inspection)) {
        throw new RuntimeException('Expected TAR end-marker policy to avoid exposing package bytes');
    }

    if (!$strictDecodeBlocked) {
        throw new RuntimeException('Expected strict TAR decode to reject the tailed archive');
    }

    echo "tar end-marker preflight self-test passed\n";
    exit(0);
}

echo "TAR end-marker preflight for WordPress archive review:\n";
echo 'handoffPolicy=' . $inspection['handoffPolicy'] . "\n";
echo 'diagnostics=' . implode(',', $inspection['diagnostics']) . "\n";
echo 'endMarkerOffset=' . $inspection['endMarkerOffset'] . "\n";
echo 'trailingByteCount=' . $inspection['trailingByteCount'] . "\n";
echo 'trailingNonZeroByteCount=' . $inspection['trailingNonZeroByteCount'] . "\n";
echo 'strictDecodeBlocked=' . ($strictDecodeBlocked ? 'yes' : 'no') . "\n";
