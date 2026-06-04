<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\Pandoc\GzipStream;
use PortLibs\Pandoc\Lz4Frame;
use PortLibs\Pandoc\TarArchive;
use PortLibs\Pandoc\TarArchiveEntry;
use PortLibs\Pandoc\ZipPackage;

$crc32 = static fn (string $bytes): int => (int) sprintf('%u', crc32($bytes));
$documentModifiedAt = 1780479017;
$mediaModifiedAt = 1780479021;
$mediaAccessedAt = 1780479022;
$mediaCreatedAt = 1780479023;

$packNtfsFileTime = static function (int $timestamp): string {
    $filetime = ($timestamp + 11644473600) * 10000000;
    $low = $filetime & 0xffffffff;
    $high = intdiv($filetime, 0x100000000);

    return pack('VV', $low, $high);
};
$buildNtfsExtra = static function (int $modifiedAt, int $accessedAt, int $createdAt) use ($packNtfsFileTime): string {
    $payload = pack('Vvv', 0, 0x0001, 24)
        . $packNtfsFileTime($modifiedAt)
        . $packNtfsFileTime($accessedAt)
        . $packNtfsFileTime($createdAt);

    return pack('vv', 0x000a, strlen($payload)) . $payload;
};
$buildRawTarRecord = static function (string $name, string $typeFlag, string $data = '', int $modifiedAt = 0): string {
    $octal = static fn (int $value, int $length): string => str_pad(decoct($value), $length - 1, '0', STR_PAD_LEFT) . "\0";
    $field = static fn (string $value, int $length): string => str_pad($value, $length, "\0");

    $header = $field($name, 100)
        . $octal(0644, 8)
        . $octal(0, 8)
        . $octal(0, 8)
        . $octal(strlen($data), 12)
        . $octal($modifiedAt, 12)
        . str_repeat(' ', 8)
        . $typeFlag
        . $field('', 100)
        . "ustar\0"
        . '00'
        . $field('', 32)
        . $field('', 32)
        . $octal(0, 8)
        . $octal(0, 8)
        . $field('', 155)
        . str_repeat("\0", 12);

    $checksum = 0;
    for ($index = 0, $length = strlen($header); $index < $length; $index++) {
        $checksum += ord($header[$index]);
    }

    $padding = strlen($data) % 512 === 0 ? '' : str_repeat("\0", 512 - (strlen($data) % 512));

    return substr_replace($header, sprintf('%06o', $checksum) . "\0 ", 148, 8) . $data . $padding;
};

$buildDescriptorBackedPackage = static function () use ($crc32): string {
    $name = 'word/comments.xml';
    $data = '<w:comments><w:comment>Reviewer note from migration packet</w:comment></w:comments>';
    $compressed = gzdeflate($data);
    $crc = $crc32($data);
    $flags = 0x0808;

    $body = pack(
        'VvvvvvVVVvv',
        0x04034b50,
        20,
        $flags,
        8,
        0,
        0,
        0,
        0,
        0,
        strlen($name),
        0
    );
    $body .= $name . $compressed . "PK\x07\x08" . pack('VVV', $crc, strlen($compressed), strlen($data));

    $central = pack(
        'VvvvvvvVVVvvvvvVV',
        0x02014b50,
        0x0314,
        20,
        $flags,
        8,
        0,
        0,
        $crc,
        strlen($compressed),
        strlen($data),
        strlen($name),
        0,
        0,
        0,
        0,
        0,
        0
    );
    $central .= $name;

    return $body
        . $central
        . pack('VvvvvVVv', 0x06054b50, 0, 0, 1, 1, strlen($central), strlen($body), 0);
};

$buildNtfsBackedPackage = static function () use ($crc32, $buildNtfsExtra, $mediaModifiedAt, $mediaAccessedAt, $mediaCreatedAt): string {
    $name = 'word/media/review.png';
    $data = "PNG reviewer attachment placeholder\n";
    $extra = $buildNtfsExtra($mediaModifiedAt, $mediaAccessedAt, $mediaCreatedAt);
    $crc = $crc32($data);

    $body = pack(
        'VvvvvvVVVvv',
        0x04034b50,
        20,
        0x0800,
        0,
        0,
        0,
        $crc,
        strlen($data),
        strlen($data),
        strlen($name),
        strlen($extra)
    );
    $body .= $name . $extra . $data;

    $central = pack(
        'VvvvvvvVVVvvvvvVV',
        0x02014b50,
        0x0314,
        20,
        0x0800,
        0,
        0,
        0,
        $crc,
        strlen($data),
        strlen($data),
        strlen($name),
        strlen($extra),
        0,
        0,
        0,
        0x81a40000,
        0
    );
    $central .= $name . $extra;

    return $body
        . $central
        . pack('VvvvvVVv', 0x06054b50, 0, 0, 1, 1, strlen($central), strlen($body), 0);
};

$buildExtendedTimestampBackedPackage = static function () use ($crc32, $mediaModifiedAt, $mediaAccessedAt, $mediaCreatedAt): string {
    $name = 'word/media/reviewer-note.txt';
    $data = "Reviewer media provenance\n";
    $localExtra = pack('vvCVVV', 0x5455, 13, 0x07, $mediaModifiedAt, $mediaAccessedAt, $mediaCreatedAt);
    $centralExtra = pack('vvCV', 0x5455, 5, 0x01, $mediaModifiedAt);
    $crc = $crc32($data);

    $body = pack(
        'VvvvvvVVVvv',
        0x04034b50,
        20,
        0x0800,
        0,
        0,
        0,
        $crc,
        strlen($data),
        strlen($data),
        strlen($name),
        strlen($localExtra)
    );
    $body .= $name . $localExtra . $data;

    $central = pack(
        'VvvvvvvVVVvvvvvVV',
        0x02014b50,
        0x0314,
        20,
        0x0800,
        0,
        0,
        0,
        $crc,
        strlen($data),
        strlen($data),
        strlen($name),
        strlen($centralExtra),
        0,
        0,
        0,
        0x81a40000,
        0
    );
    $central .= $name . $centralExtra;

    return $body
        . $central
        . pack('VvvvvVVv', 0x06054b50, 0, 0, 1, 1, strlen($central), strlen($body), 0);
};

$package = ZipPackage::fromParts([
    [
        'name' => '[Content_Types].xml',
        'data' => '<Types><Default Extension="xml" ContentType="application/xml"/></Types>',
        'compressionMethod' => 0,
    ],
    [
        'name' => '_rels/.rels',
        'data' => '<Relationships><Relationship Target="word/document.xml"/></Relationships>',
    ],
    [
        'name' => 'word/document.xml',
        'data' => '<w:document><w:body><w:p>WordPress import source</w:p></w:body></w:document>',
        'comment' => 'generated document part',
        'modifiedAt' => $documentModifiedAt,
        'externalAttributes' => 0x81a40000,
    ],
], 'wordpress import package');
$descriptorPackage = ZipPackage::fromString($buildDescriptorBackedPackage());
$ntfsPackage = ZipPackage::fromString($buildNtfsBackedPackage());
$extendedTimestampPackage = ZipPackage::fromString($buildExtendedTimestampBackedPackage());
$compressedPackage = GzipStream::build($package->bytes(), [
    'modifiedAt' => $documentModifiedAt,
    'extraFieldData' => 'WP',
    'filename' => 'wordpress-import-package.zip',
    'comment' => 'Data Liberation package fixture',
    'headerCrc' => true,
]);
$compressedPackageMembers = GzipStream::members($compressedPackage);
$tarPacket = TarArchive::fromEntries([
    [
        'name' => 'packet/',
        'type' => TarArchiveEntry::TYPE_DIRECTORY,
        'modifiedAt' => $documentModifiedAt,
    ],
    [
        'name' => 'packet/manifest.json',
        'data' => '{"source":"wordpress-import","container":"tar"}',
        'modifiedAt' => $documentModifiedAt,
    ],
    [
        'name' => 'packet/word/document.xml',
        'data' => '<w:document><w:body><w:p>Tar packet WordPress source</w:p></w:body></w:document>',
        'modifiedAt' => $documentModifiedAt,
    ],
]);
$compressedTarPacket = GzipStream::build($tarPacket->bytes(), [
    'modifiedAt' => $documentModifiedAt,
    'filename' => 'wordpress-import-packet.tar',
    'comment' => 'gzip tar review packet',
    'headerCrc' => true,
]);
$tarPacketRoundTrip = TarArchive::fromString(GzipStream::decode($compressedTarPacket));
$gnuLongDocumentName = 'packet/' . str_repeat('migration-review-', 7) . 'word/document.xml';
$gnuLongNameTar = $buildRawTarRecord('././@LongLink', 'L', $gnuLongDocumentName . "\0", $documentModifiedAt)
    . $buildRawTarRecord(
        'placeholder-document.xml',
        '0',
        '<w:document><w:body><w:p>GNU long-name tar source</w:p></w:body></w:document>',
        $documentModifiedAt
    )
    . str_repeat("\0", 1024);
$gnuLongNamePacket = TarArchive::fromString($gnuLongNameTar);
$lz4ReviewPacket = Lz4Frame::skippableFrame('wordpress import archive metadata', 2)
    . Lz4Frame::build($tarPacket->bytes(), [
        'blockChecksum' => true,
        'contentChecksum' => true,
        'contentSize' => true,
    ]);
$lz4ReviewFrames = Lz4Frame::frames($lz4ReviewPacket);
$lz4TarPacketRoundTrip = TarArchive::fromString(Lz4Frame::decode($lz4ReviewPacket));
$symlinkRejected = false;
try {
    ZipPackage::fromParts([
        [
            'name' => 'word/media/review.png',
            'data' => '../embeddings/oleObject1.bin',
            'compressionMethod' => 0,
            'externalAttributes' => 0xa1ff0000,
        ],
    ]);
} catch (RuntimeException $exception) {
    $symlinkRejected = str_contains($exception->getMessage(), 'symlink');
}

if (in_array('--self-test', $argv, true)) {
    if (!$package->has('/word/document.xml')) {
        throw new RuntimeException('Expected word/document.xml to be discoverable as an OPC part');
    }

    if ($package->read('/word/document.xml') !== '<w:document><w:body><w:p>WordPress import source</w:p></w:body></w:document>') {
        throw new RuntimeException('Expected document part bytes to round-trip from the ZIP package');
    }

    if ($package->packageComment() !== 'wordpress import package') {
        throw new RuntimeException('Expected package comment metadata to round-trip from the generated ZIP package');
    }

    $documentEntry = $package->entry('/word/document.xml');
    if ($documentEntry->lastModifiedTimestamp() !== $documentModifiedAt) {
        throw new RuntimeException('Expected document part ZIP timestamp metadata to round-trip');
    }

    if ($documentEntry->externalFileAttributes !== 0x81a40000) {
        throw new RuntimeException('Expected document part ZIP external attributes to round-trip');
    }

    if ($documentEntry->extendedLastModifiedTimestamp() !== $documentModifiedAt) {
        throw new RuntimeException('Expected document part ZIP extended timestamp extra field to round-trip');
    }

    if ($package->localExtendedLastModifiedTimestamp('/word/document.xml') !== $documentModifiedAt) {
        throw new RuntimeException('Expected document part local ZIP extended timestamp extra field to round-trip');
    }

    if ($package->localExtraField('/word/document.xml', 0x5455) === null) {
        throw new RuntimeException('Expected document part local ZIP extra fields to be inspectable');
    }

    $extendedTimestamps = $extendedTimestampPackage->localExtendedTimestamps('/word/media/reviewer-note.txt');
    if (($extendedTimestamps['modifiedAt'] ?? null) !== $mediaModifiedAt) {
        throw new RuntimeException('Expected local extended modified timestamp metadata to round-trip');
    }

    if (($extendedTimestamps['accessedAt'] ?? null) !== $mediaAccessedAt) {
        throw new RuntimeException('Expected local extended access timestamp metadata to round-trip');
    }

    if (($extendedTimestamps['createdAt'] ?? null) !== $mediaCreatedAt) {
        throw new RuntimeException('Expected local extended creation timestamp metadata to round-trip');
    }

    if ($extendedTimestampPackage->entry('/word/media/reviewer-note.txt')->extendedAccessedTimestamp() !== null) {
        throw new RuntimeException('Expected central extended timestamp metadata to omit access time');
    }

    if ($extendedTimestampPackage->read('/word/media/reviewer-note.txt') !== "Reviewer media provenance\n") {
        throw new RuntimeException('Expected extended timestamp package media bytes to round-trip');
    }

    if ($descriptorPackage->read('/word/comments.xml') !== '<w:comments><w:comment>Reviewer note from migration packet</w:comment></w:comments>') {
        throw new RuntimeException('Expected descriptor-backed comments part bytes to round-trip from the ZIP package');
    }

    $mediaEntry = $ntfsPackage->entry('/word/media/review.png');
    if ($mediaEntry->ntfsLastModifiedTimestamp() !== $mediaModifiedAt) {
        throw new RuntimeException('Expected central NTFS modified timestamp metadata to round-trip');
    }

    if ($mediaEntry->lastModifiedTimestamp() !== $mediaModifiedAt) {
        throw new RuntimeException('Expected NTFS timestamp metadata to supply the import modified time');
    }

    if ($ntfsPackage->localNtfsLastModifiedTimestamp('/word/media/review.png') !== $mediaModifiedAt) {
        throw new RuntimeException('Expected local NTFS modified timestamp metadata to round-trip');
    }

    if (GzipStream::decode($compressedPackage) !== $package->bytes()) {
        throw new RuntimeException('Expected gzip-wrapped ZIP package bytes to round-trip');
    }

    if (($compressedPackageMembers[0]['filename'] ?? null) !== 'wordpress-import-package.zip') {
        throw new RuntimeException('Expected gzip original filename metadata to round-trip');
    }

    if (($compressedPackageMembers[0]['extraFieldData'] ?? '') !== 'WP') {
        throw new RuntimeException('Expected gzip extra field metadata to round-trip');
    }

    if (!$tarPacketRoundTrip->has('/packet/word/document.xml')) {
        throw new RuntimeException('Expected tar packet document part to be discoverable');
    }

    if ($tarPacketRoundTrip->read('/packet/manifest.json') !== '{"source":"wordpress-import","container":"tar"}') {
        throw new RuntimeException('Expected tar packet manifest bytes to round-trip');
    }

    if ($tarPacketRoundTrip->read('/packet/word/document.xml') !== '<w:document><w:body><w:p>Tar packet WordPress source</w:p></w:body></w:document>') {
        throw new RuntimeException('Expected gzip-wrapped tar document bytes to round-trip');
    }

    if ($gnuLongNamePacket->read('/' . $gnuLongDocumentName) !== '<w:document><w:body><w:p>GNU long-name tar source</w:p></w:body></w:document>') {
        throw new RuntimeException('Expected GNU long-name tar document bytes to be addressable by the metadata path');
    }

    if (!$tarPacketRoundTrip->entry('packet/')->isDirectory()) {
        throw new RuntimeException('Expected tar packet directory metadata to round-trip');
    }

    if (($lz4ReviewFrames[0]['type'] ?? null) !== 'skippable') {
        throw new RuntimeException('Expected LZ4 skippable metadata frame to be preserved');
    }

    if (($lz4ReviewFrames[1]['blockChecksum'] ?? false) !== true) {
        throw new RuntimeException('Expected LZ4 block checksums to be validated');
    }

    if ($lz4TarPacketRoundTrip->read('/packet/word/document.xml') !== '<w:document><w:body><w:p>Tar packet WordPress source</w:p></w:body></w:document>') {
        throw new RuntimeException('Expected LZ4-wrapped tar document bytes to round-trip');
    }

    if (!$symlinkRejected) {
        throw new RuntimeException('Expected ZIP symlink entries to be rejected before media import');
    }

    echo "zip package writer preflight self-test passed\n";
    exit(0);
}

echo "ZIP package parts for WordPress import preflight:\n";
echo 'packageComment=' . $package->packageComment() . "\n";
foreach ($package->entries() as $entry) {
    $modifiedAt = $entry->lastModifiedTimestamp();
    echo '- ' . $entry->name
        . ' method=' . $entry->compressionMethod
        . ' crc32=' . $entry->crc32Hex()
        . ' modifiedAt=' . ($modifiedAt === null ? 'none' : (string) $modifiedAt)
        . ' externalAttributes=' . sprintf('0x%08x', $entry->externalFileAttributes)
        . ' extraFields=' . count($entry->centralExtraFields())
        . ' localExtraFields=' . count($package->localExtraFields($entry->name))
        . "\n";
}
echo 'document.xml=' . $package->read('/word/document.xml') . "\n";
echo 'descriptor.comments.xml=' . $descriptorPackage->read('/word/comments.xml') . "\n";
$ntfsTimestamps = $ntfsPackage->entry('/word/media/review.png')->ntfsTimestamps();
echo 'ntfs.review.png.modifiedAt=' . ($ntfsTimestamps['modifiedAt'] ?? 'none') . "\n";
echo 'ntfs.review.png.localModifiedAt=' . ($ntfsPackage->localNtfsLastModifiedTimestamp('/word/media/review.png') ?? 'none') . "\n";
$extendedTimestamps = $extendedTimestampPackage->localExtendedTimestamps('/word/media/reviewer-note.txt') ?? [];
echo 'extended.reviewer-note.modifiedAt=' . ($extendedTimestamps['modifiedAt'] ?? 'none') . "\n";
echo 'extended.reviewer-note.accessedAt=' . ($extendedTimestamps['accessedAt'] ?? 'none') . "\n";
echo 'extended.reviewer-note.createdAt=' . ($extendedTimestamps['createdAt'] ?? 'none') . "\n";
echo 'symlinkPolicy=' . ($symlinkRejected ? 'rejected' : 'not-rejected') . "\n";
echo 'gzip.filename=' . $compressedPackageMembers[0]['filename'] . "\n";
echo 'gzip.comment=' . $compressedPackageMembers[0]['comment'] . "\n";
echo 'gzip.compressedSize=' . $compressedPackageMembers[0]['compressedSize'] . "\n";
echo 'tar.entries=' . implode(',', $tarPacketRoundTrip->names()) . "\n";
echo 'tar.document.xml=' . $tarPacketRoundTrip->read('/packet/word/document.xml') . "\n";
echo 'tar.gnuLongName=' . implode(',', $gnuLongNamePacket->names()) . "\n";
echo 'lz4.frames=' . count($lz4ReviewFrames) . "\n";
echo 'lz4.skippable=' . $lz4ReviewFrames[0]['data'] . "\n";
echo 'lz4.blockTypes=' . implode(',', $lz4ReviewFrames[1]['blockTypes']) . "\n";
echo 'lz4.document.xml=' . $lz4TarPacketRoundTrip->read('/packet/word/document.xml') . "\n";
