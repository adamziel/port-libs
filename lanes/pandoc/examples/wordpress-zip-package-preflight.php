<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\Pandoc\GzipStream;
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
$compressedPackage = GzipStream::build($package->bytes(), [
    'modifiedAt' => $documentModifiedAt,
    'extraFieldData' => 'WP',
    'filename' => 'wordpress-import-package.zip',
    'comment' => 'Data Liberation package fixture',
    'headerCrc' => true,
]);
$compressedPackageMembers = GzipStream::members($compressedPackage);

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
echo 'gzip.filename=' . $compressedPackageMembers[0]['filename'] . "\n";
echo 'gzip.comment=' . $compressedPackageMembers[0]['comment'] . "\n";
echo 'gzip.compressedSize=' . $compressedPackageMembers[0]['compressedSize'] . "\n";
