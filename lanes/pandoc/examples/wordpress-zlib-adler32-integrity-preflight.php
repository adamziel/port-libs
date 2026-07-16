<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\Pandoc\ArchiveCompressionStream;
use PortLibs\Pandoc\DeflateStream;
use PortLibs\Pandoc\TarArchive;

$archive = TarArchive::fromEntries([
    [
        'name' => 'packet/manifest.json',
        'data' => '{"source":"wordpress-zlib-adler32-integrity","target":"review"}',
    ],
    [
        'name' => 'packet/content.md',
        'data' => "# ZLIB Adler-32 integrity\n\nReady for WordPress archive review.\n",
    ],
]);

$tarBytes = $archive->bytes();
$stream = DeflateStream::build($tarBytes, [
    'format' => DeflateStream::FORMAT_ZLIB,
    'compressionLevel' => 9,
]);
$computedAdler32 = intval(hash('adler32', $tarBytes), 16);
$badAdler32 = $computedAdler32 === 0 ? 1 : $computedAdler32 - 1;
$corruptStream = substr_replace($stream, pack('N', $badAdler32), -4, 4);

$validInspection = ArchiveCompressionStream::inspectZlibAdler32IntegrityPolicy(
    $stream,
    ArchiveCompressionStream::FORMAT_ZLIB_TAR,
    strlen($tarBytes)
);
$corruptInspection = ArchiveCompressionStream::inspectZlibAdler32IntegrityPolicy(
    $corruptStream,
    ArchiveCompressionStream::FORMAT_ZLIB_TAR,
    strlen($tarBytes)
);

$strictPackageOpenBlocked = false;
try {
    ArchiveCompressionStream::openTar(
        $corruptStream,
        ArchiveCompressionStream::FORMAT_ZLIB_TAR,
        strlen($tarBytes)
    );
} catch (RuntimeException) {
    $strictPackageOpenBlocked = true;
}

if (in_array('--self-test', $argv, true)) {
    if (($validInspection['handoffPolicy'] ?? null) !== 'within-thresholds') {
        throw new RuntimeException('Expected valid zlib Adler-32 metadata to pass preflight.');
    }

    if (($corruptInspection['handoffPolicy'] ?? null) !== 'review-before-conversion') {
        throw new RuntimeException('Expected corrupt zlib Adler-32 metadata to require review.');
    }

    if (($corruptInspection['computedAdler32'] ?? null) !== $computedAdler32) {
        throw new RuntimeException('Expected computed Adler-32 to remain visible for review.');
    }

    if (($corruptInspection['storedAdler32'] ?? null) !== $badAdler32) {
        throw new RuntimeException('Expected stored corrupt Adler-32 to remain visible for review.');
    }

    if (($corruptInspection['diagnostics'] ?? []) !== ['zlib-adler32-mismatch']) {
        throw new RuntimeException('Expected zlib Adler-32 mismatch diagnostic.');
    }

    if (isset($corruptInspection['stream']['data']) || isset($corruptInspection['archive']) || isset($corruptInspection['tarBytes'])) {
        throw new RuntimeException('Expected zlib Adler-32 preflight to avoid exposing decoded package bytes.');
    }

    if (!$strictPackageOpenBlocked) {
        throw new RuntimeException('Expected strict TAR handoff to reject the corrupt zlib stream.');
    }

    echo "zlib adler32 integrity preflight self-test passed\n";
    exit(0);
}

echo "ZLIB Adler-32 integrity preflight for WordPress archive review:\n";
echo 'validPolicy=' . $validInspection['handoffPolicy'] . "\n";
echo 'corruptPolicy=' . $corruptInspection['handoffPolicy'] . "\n";
echo 'diagnostics=' . implode(',', $corruptInspection['diagnostics']) . "\n";
echo 'storedAdler32=' . $corruptInspection['storedAdler32Hex'] . "\n";
echo 'computedAdler32=' . $corruptInspection['computedAdler32Hex'] . "\n";
echo 'strictPackageOpenBlocked=' . ($strictPackageOpenBlocked ? 'yes' : 'no') . "\n";
