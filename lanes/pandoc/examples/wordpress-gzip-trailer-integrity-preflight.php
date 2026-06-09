<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\Pandoc\ArchiveCompressionStream;
use PortLibs\Pandoc\GzipStream;
use PortLibs\Pandoc\TarArchive;

$archive = TarArchive::fromEntries([
    [
        'name' => 'packet/manifest.json',
        'data' => '{"source":"wordpress-gzip-trailer-integrity","target":"review"}',
    ],
    [
        'name' => 'packet/content.md',
        'data' => "# GZIP trailer integrity\n\nReady for WordPress archive review.\n",
    ],
]);

$tarBytes = $archive->bytes();
$splitOffset = 512;
$firstPayload = substr($tarBytes, 0, $splitOffset);
$secondPayload = substr($tarBytes, $splitOffset);
$firstMember = GzipStream::build($firstPayload, [
    'filename' => 'wordpress-gzip-trailer-part-1.tar',
    'comment' => 'valid first decoded package segment',
]);
$secondMember = GzipStream::build($secondPayload, [
    'filename' => 'wordpress-gzip-trailer-part-2.tar',
    'comment' => 'corrupt trailer decoded package segment',
]);

$secondCrc32 = (int) sprintf('%u', crc32($secondPayload));
$badSecondCrc32 = $secondCrc32 === 0 ? 1 : $secondCrc32 - 1;
$corruptSecondMember = substr_replace($secondMember, pack('V', $badSecondCrc32), -8, 4);
$stream = $firstMember . $corruptSecondMember;

$inspection = ArchiveCompressionStream::inspectGzipTrailerIntegrityPolicy(
    $stream,
    ArchiveCompressionStream::FORMAT_GZIP_TAR,
    strlen($tarBytes)
);

$strictPackageOpenBlocked = false;
try {
    ArchiveCompressionStream::openTar($stream, ArchiveCompressionStream::FORMAT_GZIP_TAR, strlen($tarBytes));
} catch (RuntimeException) {
    $strictPackageOpenBlocked = true;
}

if (in_array('--self-test', $argv, true)) {
    if ($inspection['handoffPolicy'] !== 'review-before-conversion') {
        throw new RuntimeException('Expected corrupt gzip trailer metadata to require review.');
    }

    if (($inspection['failedMemberCount'] ?? null) !== 1) {
        throw new RuntimeException('Expected one gzip member trailer integrity failure.');
    }

    if (($inspection['crcMismatchMemberCount'] ?? null) !== 1) {
        throw new RuntimeException('Expected one gzip member CRC32 mismatch.');
    }

    if (($inspection['members'][1]['computedCrc32'] ?? null) !== $secondCrc32) {
        throw new RuntimeException('Expected computed gzip member CRC32 to remain visible.');
    }

    if (isset($inspection['members'][1]['data']) || isset($inspection['archive']) || isset($inspection['tarBytes'])) {
        throw new RuntimeException('Expected gzip trailer preflight to avoid exposing decoded package bytes.');
    }

    if (!$strictPackageOpenBlocked) {
        throw new RuntimeException('Expected strict TAR handoff to reject the corrupt gzip stream.');
    }

    echo "gzip trailer integrity preflight self-test passed\n";
    exit(0);
}

echo "GZIP trailer integrity preflight for WordPress archive review:\n";
echo 'handoffPolicy=' . $inspection['handoffPolicy'] . "\n";
echo 'diagnostics=' . implode(',', $inspection['diagnostics']) . "\n";
echo 'failedMembers=' . $inspection['failedMemberCount'] . "\n";
echo 'crcMismatchMembers=' . $inspection['crcMismatchMemberCount'] . "\n";
echo 'isizeMismatchMembers=' . $inspection['isizeMismatchMemberCount'] . "\n";
echo 'strictPackageOpenBlocked=' . ($strictPackageOpenBlocked ? 'yes' : 'no') . "\n";
