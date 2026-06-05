<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\Pandoc\ArchiveCompressionStream;
use PortLibs\Pandoc\DeflateStream;
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
$documentReviewExtra = pack('vva*', 0xcafe, strlen('wp-review:v1'), 'wp-review:v1');

$packNtfsFileTime = static function (int $timestamp): string {
    $filetime = ($timestamp + 11644473600) * 10000000;
    $low = $filetime & 0xffffffff;
    $high = intdiv($filetime, 0x100000000);

    return pack('VV', $low, $high);
};
$buildUnicodeExtra = static function (int $id, string $rawBytes, string $utf8Text) use ($crc32): string {
    $payload = pack('CV', 1, $crc32($rawBytes)) . $utf8Text;

    return pack('vv', $id, strlen($payload)) . $payload;
};
$buildNtfsExtra = static function (int $modifiedAt, int $accessedAt, int $createdAt) use ($packNtfsFileTime): string {
    $payload = pack('Vvv', 0, 0x0001, 24)
        . $packNtfsFileTime($modifiedAt)
        . $packNtfsFileTime($accessedAt)
        . $packNtfsFileTime($createdAt);

    return pack('vv', 0x000a, strlen($payload)) . $payload;
};
$buildRawTarRecord = static function (string $name, string $typeFlag, string $data = '', int $modifiedAt = 0, ?int $headerSize = null): string {
    $octal = static fn (int $value, int $length): string => str_pad(decoct($value), $length - 1, '0', STR_PAD_LEFT) . "\0";
    $field = static fn (string $value, int $length): string => str_pad($value, $length, "\0");
    $headerSize ??= strlen($data);

    $header = $field($name, 100)
        . $octal(0644, 8)
        . $octal(0, 8)
        . $octal(0, 8)
        . $octal($headerSize, 12)
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
$buildBase256TarField = static function (int $value, int $length): string {
    if ($value < 0) {
        throw new RuntimeException('base-256 TAR fixture values must be non-negative');
    }

    $field = str_repeat("\0", $length);
    for ($index = $length - 1; $index >= 0 && $value > 0; $index--) {
        $field[$index] = chr($value & 0xff);
        $value = intdiv($value, 256);
    }

    if ($value !== 0) {
        throw new RuntimeException('base-256 TAR fixture value is too large');
    }

    $field[0] = chr(ord($field[0]) | 0x80);

    return $field;
};
$rewriteTarHeaderFields = static function (string $archive, array $fields): string {
    $header = substr($archive, 0, 512);
    foreach ($fields as $offset => $field) {
        $header = substr_replace($header, $field, (int) $offset, strlen($field));
    }

    $header = substr_replace($header, str_repeat(' ', 8), 148, 8);
    $checksum = 0;
    for ($index = 0, $length = strlen($header); $index < $length; $index++) {
        $checksum += ord($header[$index]);
    }

    $header = substr_replace($header, sprintf('%06o', $checksum) . "\0 ", 148, 8);

    return $header . substr($archive, 512);
};
$buildPaxPayload = static function (array $headers): string {
    $payload = '';
    foreach ($headers as $key => $value) {
        $body = " {$key}={$value}\n";
        $recordLength = strlen($body) + 1;
        do {
            $nextLength = strlen((string) $recordLength) + strlen($body);
            if ($nextLength === $recordLength) {
                $payload .= $recordLength . $body;
                break;
            }
            $recordLength = $nextLength;
        } while (true);
    }

    return $payload;
};
$lz4HeaderChecksum = static fn (string $descriptor): string => chr((intval(hash('xxh32', $descriptor), 16) >> 8) & 0xff);
$buildDependentLz4Frame = static function (string $dictionaryBlock) use ($lz4HeaderChecksum): string {
    $matchLength = strlen($dictionaryBlock);
    if ($matchLength < 20 || $matchLength > 0xffff) {
        throw new RuntimeException('Dependent LZ4 fixture block must be between 20 and 65535 bytes');
    }

    $extensionLength = $matchLength - 19;
    $matchLengthExtension = '';
    while ($extensionLength >= 255) {
        $matchLengthExtension .= "\xff";
        $extensionLength -= 255;
    }
    $matchLengthExtension .= chr($extensionLength);
    $matchPayload = chr(0x0f)
        . pack('v', $matchLength)
        . $matchLengthExtension;
    $descriptor = chr(0x40) . chr(0x40);

    return pack('V', 0x184d2204)
        . $descriptor
        . $lz4HeaderChecksum($descriptor)
        . pack('V', 0x80000000 | $matchLength)
        . $dictionaryBlock
        . pack('V', strlen($matchPayload))
        . $matchPayload
        . pack('V', 0);
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

$unicodePathName = "word/media/review-\u{2603}.png";
$unicodePathRawName = 'word/media/review-image.bin';
$unicodeRawComment = 'legacy reviewer comment';
$unicodeComment = "Unicode reviewer \u{2603} comment";
$buildUnicodePathBackedPackage = static function () use (
    $crc32,
    $buildUnicodeExtra,
    $unicodePathName,
    $unicodePathRawName,
    $unicodeRawComment,
    $unicodeComment
): string {
    $data = "Unicode media attachment placeholder\n";
    $crc = $crc32($data);
    $unicodePathExtra = $buildUnicodeExtra(0x7075, $unicodePathRawName, $unicodePathName);
    $unicodeCommentExtra = $buildUnicodeExtra(0x6375, $unicodeRawComment, $unicodeComment);

    $body = pack(
        'VvvvvvVVVvv',
        0x04034b50,
        20,
        0,
        0,
        0,
        0,
        $crc,
        strlen($data),
        strlen($data),
        strlen($unicodePathRawName),
        strlen($unicodePathExtra)
    );
    $body .= $unicodePathRawName . $unicodePathExtra . $data;

    $centralExtra = $unicodePathExtra . $unicodeCommentExtra;
    $central = pack(
        'VvvvvvvVVVvvvvvVV',
        0x02014b50,
        0x0314,
        20,
        0,
        0,
        0,
        0,
        $crc,
        strlen($data),
        strlen($data),
        strlen($unicodePathRawName),
        strlen($centralExtra),
        strlen($unicodeRawComment),
        0,
        0,
        0x81a40000,
        0
    );
    $central .= $unicodePathRawName . $centralExtra . $unicodeRawComment;

    return $body
        . $central
        . pack('VvvvvVVv', 0x06054b50, 0, 0, 1, 1, strlen($central), strlen($body), 0);
};
$buildZip64ExtraBackedPackage = static function () use ($crc32): string {
    $name = 'word/media/oversized-review.bin';
    $data = "ZIP64 media placeholder should stay blocked\n";
    $crc = $crc32($data);
    $zip64Extra = pack('vv', 0x0001, 8) . str_repeat("\0", 8);

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
        0
    );
    $body .= $name . $data;

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
        strlen($zip64Extra),
        0,
        0,
        0,
        0x81a40000,
        0
    );
    $central .= $name . $zip64Extra;

    return $body
        . $central
        . pack('VvvvvVVv', 0x06054b50, 0, 0, 1, 1, strlen($central), strlen($body), 0);
};
$buildDriveLetterBackedPackage = static function () use ($crc32): string {
    $name = 'C:word/media/review.png';
    $data = "Drive-letter media path should stay blocked\n";
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
        0
    );
    $body .= $name . $data;

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
        0,
        0,
        0,
        0,
        0x81a40000,
        0
    );
    $central .= $name;

    return $body
        . $central
        . pack('VvvvvVVv', 0x06054b50, 0, 0, 1, 1, strlen($central), strlen($body), 0);
};
$buildDirectoryPayloadBackedPackage = static function () use ($crc32): string {
    $name = 'word/media/';
    $data = "Directory payload should stay blocked\n";
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
        0
    );
    $body .= $name . $data;

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
        0,
        0,
        0,
        0,
        0x10,
        0
    );
    $central .= $name;

    return $body
        . $central
        . pack('VvvvvVVv', 0x06054b50, 0, 0, 1, 1, strlen($central), strlen($body), 0);
};
$buildEncryptedMetadataBackedPackage = static function (int $flags) use ($crc32): string {
    $name = 'word/media/encrypted-review.xml';
    $data = "Encrypted metadata should stay blocked\n";
    $crc = $crc32($data);

    $body = pack(
        'VvvvvvVVVvv',
        0x04034b50,
        20,
        $flags,
        0,
        0,
        0,
        $crc,
        strlen($data),
        strlen($data),
        strlen($name),
        0
    );
    $body .= $name . $data;

    $central = pack(
        'VvvvvvvVVVvvvvvVV',
        0x02014b50,
        0x0314,
        20,
        $flags,
        0,
        0,
        0,
        $crc,
        strlen($data),
        strlen($data),
        strlen($name),
        0,
        0,
        0,
        0,
        0x81a40000,
        0
    );
    $central .= $name;

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
        'extraFieldData' => $documentReviewExtra,
    ],
], 'wordpress import package');
$gzipReviewExtra = pack('CCv', ord('W'), ord('P'), strlen('review:v1')) . 'review:v1';
$descriptorPackage = ZipPackage::fromString($buildDescriptorBackedPackage());
$ntfsPackage = ZipPackage::fromString($buildNtfsBackedPackage());
$extendedTimestampPackage = ZipPackage::fromString($buildExtendedTimestampBackedPackage());
$unicodePathPackage = ZipPackage::fromString($buildUnicodePathBackedPackage());
$compressedPackage = GzipStream::build($package->bytes(), [
    'modifiedAt' => $documentModifiedAt,
    'extraFieldData' => $gzipReviewExtra,
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
$streamDetectedTarFormat = ArchiveCompressionStream::detectTarFormat(
    $compressedTarPacket,
    strlen($tarPacket->bytes()),
    strlen($tarPacket->read('/packet/manifest.json')) + strlen($tarPacket->read('/packet/word/document.xml'))
);
$streamDecodedTarBytes = ArchiveCompressionStream::decodeTarBytesAuto(
    $compressedTarPacket,
    strlen($tarPacket->bytes()),
    strlen($tarPacket->read('/packet/manifest.json')) + strlen($tarPacket->read('/packet/word/document.xml'))
);
$streamDispatchedTarPacket = ArchiveCompressionStream::openTarAuto(
    $compressedTarPacket,
    strlen($tarPacket->bytes()),
    strlen($tarPacket->read('/packet/manifest.json')) + strlen($tarPacket->read('/packet/word/document.xml'))
);
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
$paxDocumentName = 'packet/pax/document.xml';
$paxDocumentBytes = '<w:document><w:body><w:p>PAX metadata tar source</w:p></w:body></w:document>';
$paxMetadataTar = $buildRawTarRecord('PaxHeaders/pax-document', 'x', $buildPaxPayload([
    'path' => $paxDocumentName,
    'size' => (string) strlen($paxDocumentBytes),
    'mtime' => (string) ($documentModifiedAt + 11) . '.25',
    'uid' => '1001',
    'gid' => '1002',
    'uname' => 'wp-reviewer',
    'gname' => 'import-team',
]), $documentModifiedAt)
    . $buildRawTarRecord('placeholder-document.xml', '0', $paxDocumentBytes, 0, 0)
    . str_repeat("\0", 1024);
$paxMetadataPacket = TarArchive::fromString($paxMetadataTar);
$base256DocumentBytes = '<w:document><w:body><w:p>Base-256 tar metadata source</w:p></w:body></w:document>';
$base256NumericTar = $rewriteTarHeaderFields(
    $buildRawTarRecord('packet/base256/document.xml', '0', $base256DocumentBytes),
    [
        100 => $buildBase256TarField(0640, 8),
        108 => $buildBase256TarField(100001, 8),
        116 => $buildBase256TarField(100002, 8),
        124 => $buildBase256TarField(strlen($base256DocumentBytes), 12),
        136 => $buildBase256TarField($documentModifiedAt + 18, 12),
    ]
) . str_repeat("\0", 1024);
$base256NumericPacket = TarArchive::fromString($base256NumericTar);
$deflateReviewPacket = DeflateStream::build($tarPacket->bytes(), [
    'format' => DeflateStream::FORMAT_ZLIB,
    'compressionLevel' => 9,
]);
$deflateReviewMetadata = DeflateStream::inspectZlib($deflateReviewPacket);
$deflateTarPacketRoundTrip = TarArchive::fromString(DeflateStream::decode($deflateReviewPacket));
$rawDeflateReviewPacket = DeflateStream::build($tarPacket->bytes(), [
    'format' => DeflateStream::FORMAT_RAW,
    'compressionLevel' => 9,
]);
$rawDeflateTarPacketRoundTrip = TarArchive::fromString(DeflateStream::decode(
    $rawDeflateReviewPacket,
    DeflateStream::FORMAT_RAW
));
$lz4ReviewPacket = Lz4Frame::skippableFrame('wordpress import archive metadata', 2)
    . Lz4Frame::build($tarPacket->bytes(), [
        'blockChecksum' => true,
        'contentChecksum' => true,
        'contentSize' => true,
    ]);
$lz4ReviewFrames = Lz4Frame::frames($lz4ReviewPacket);
$lz4TarPacketRoundTrip = TarArchive::fromString(Lz4Frame::decode($lz4ReviewPacket));
$dependentLz4ReviewIndex = $buildDependentLz4Frame('packet/word/document.xml:');
$dependentLz4ReviewFrames = Lz4Frame::frames($dependentLz4ReviewIndex);
$dependentLz4ReviewIndexText = Lz4Frame::decode($dependentLz4ReviewIndex);
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
$zip64Rejected = false;
try {
    ZipPackage::fromString($buildZip64ExtraBackedPackage());
} catch (RuntimeException $exception) {
    $zip64Rejected = str_contains($exception->getMessage(), 'ZIP64 extra field');
}
$driveLetterRejected = false;
try {
    ZipPackage::fromString($buildDriveLetterBackedPackage());
} catch (RuntimeException $exception) {
    $driveLetterRejected = str_contains($exception->getMessage(), 'Unsafe ZIP package entry name');
}
$directoryPayloadRejected = false;
try {
    ZipPackage::fromString($buildDirectoryPayloadBackedPackage());
} catch (RuntimeException $exception) {
    $directoryPayloadRejected = str_contains($exception->getMessage(), 'directory entry');
}
$strongEncryptionRejected = false;
try {
    ZipPackage::fromString($buildEncryptedMetadataBackedPackage(0x0840));
} catch (RuntimeException $exception) {
    $strongEncryptionRejected = str_contains($exception->getMessage(), 'Strong-encrypted ZIP entries');
}
$centralDirectoryEncryptionRejected = false;
try {
    ZipPackage::fromString($buildEncryptedMetadataBackedPackage(0x2800));
} catch (RuntimeException $exception) {
    $centralDirectoryEncryptionRejected = str_contains($exception->getMessage(), 'central-directory encryption metadata');
}
$missingTarEndMarkerRejected = false;
try {
    TarArchive::fromString($buildRawTarRecord('packet/missing-end-marker.xml', '0', '<w:document/>', $documentModifiedAt));
} catch (RuntimeException $exception) {
    $missingTarEndMarkerRejected = str_contains($exception->getMessage(), 'end marker');
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

    if ($documentEntry->centralExtraField(0xcafe) !== 'wp-review:v1') {
        throw new RuntimeException('Expected document part central ZIP review extra field to round-trip');
    }

    if ($package->localExtraField('/word/document.xml', 0xcafe) !== 'wp-review:v1') {
        throw new RuntimeException('Expected document part local ZIP review extra field to round-trip');
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

    if (($compressedPackageMembers[0]['extraFieldData'] ?? '') !== $gzipReviewExtra) {
        throw new RuntimeException('Expected gzip extra field metadata to round-trip');
    }

    if (($compressedPackageMembers[0]['extraFields'][0]['identifier'] ?? null) !== 'WP') {
        throw new RuntimeException('Expected gzip extra field subfield identifier to be inspectable');
    }

    if (($compressedPackageMembers[0]['extraFields'][0]['data'] ?? null) !== 'review:v1') {
        throw new RuntimeException('Expected gzip extra field subfield payload to be inspectable');
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

    if ($streamDispatchedTarPacket->read('/packet/word/document.xml') !== '<w:document><w:body><w:p>Tar packet WordPress source</w:p></w:body></w:document>') {
        throw new RuntimeException('Expected archive stream auto-detection to open gzip-wrapped tar packets');
    }

    if ($streamDetectedTarFormat !== ArchiveCompressionStream::FORMAT_GZIP_TAR) {
        throw new RuntimeException('Expected archive stream auto-detection to classify the gzip-wrapped tar packet');
    }

    if ($streamDecodedTarBytes !== $tarPacket->bytes()) {
        throw new RuntimeException('Expected archive stream auto-detection to return the tar packet bytes');
    }

    if ($gnuLongNamePacket->read('/' . $gnuLongDocumentName) !== '<w:document><w:body><w:p>GNU long-name tar source</w:p></w:body></w:document>') {
        throw new RuntimeException('Expected GNU long-name tar document bytes to be addressable by the metadata path');
    }

    $paxEntry = $paxMetadataPacket->entry('/' . $paxDocumentName);
    if ($paxEntry->size !== strlen($paxDocumentBytes)) {
        throw new RuntimeException('Expected PAX size metadata to define the tar document payload length');
    }

    if ($paxEntry->modifiedAt !== $documentModifiedAt + 11) {
        throw new RuntimeException('Expected PAX mtime metadata to define the tar document modified time');
    }

    if ($paxEntry->uid !== 1001 || $paxEntry->gid !== 1002 || $paxEntry->userName !== 'wp-reviewer' || $paxEntry->groupName !== 'import-team') {
        throw new RuntimeException('Expected PAX owner metadata to round-trip for review packets');
    }

    if ($paxMetadataPacket->read('/' . $paxDocumentName) !== $paxDocumentBytes) {
        throw new RuntimeException('Expected PAX metadata tar document bytes to round-trip');
    }

    $base256Entry = $base256NumericPacket->entry('/packet/base256/document.xml');
    if ($base256Entry->size !== strlen($base256DocumentBytes)) {
        throw new RuntimeException('Expected base-256 TAR size metadata to define the document payload length');
    }

    if ($base256Entry->modifiedAt !== $documentModifiedAt + 18 || $base256Entry->mode !== 0640) {
        throw new RuntimeException('Expected base-256 TAR timestamp and mode metadata to round-trip');
    }

    if ($base256Entry->uid !== 100001 || $base256Entry->gid !== 100002) {
        throw new RuntimeException('Expected base-256 TAR owner ids to round-trip');
    }

    if ($base256NumericPacket->read('/packet/base256/document.xml') !== $base256DocumentBytes) {
        throw new RuntimeException('Expected base-256 TAR document bytes to round-trip');
    }

    if (($deflateReviewMetadata['compressionMethod'] ?? null) !== 8 || ($deflateReviewMetadata['compressionLevelHint'] ?? null) !== 'maximum') {
        throw new RuntimeException('Expected zlib-wrapped deflate review packet metadata to be inspectable');
    }

    if ($deflateTarPacketRoundTrip->read('/packet/word/document.xml') !== '<w:document><w:body><w:p>Tar packet WordPress source</w:p></w:body></w:document>') {
        throw new RuntimeException('Expected zlib-wrapped deflate tar document bytes to round-trip');
    }

    if ($rawDeflateTarPacketRoundTrip->read('/packet/manifest.json') !== '{"source":"wordpress-import","container":"tar"}') {
        throw new RuntimeException('Expected raw deflate tar manifest bytes to round-trip');
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

    if (($dependentLz4ReviewFrames[0]['blockIndependent'] ?? true) !== false) {
        throw new RuntimeException('Expected dependent LZ4 frame metadata to preserve block-dependency mode');
    }

    if (($dependentLz4ReviewFrames[0]['blockTypes'] ?? []) !== ['uncompressed', 'compressed']) {
        throw new RuntimeException('Expected dependent LZ4 review index to combine uncompressed and compressed blocks');
    }

    if ($dependentLz4ReviewIndexText !== 'packet/word/document.xml:packet/word/document.xml:') {
        throw new RuntimeException('Expected dependent LZ4 review index to decode across block history');
    }

    if (!$symlinkRejected) {
        throw new RuntimeException('Expected ZIP symlink entries to be rejected before media import');
    }

    if (!$zip64Rejected) {
        throw new RuntimeException('Expected ZIP64 extra-field entries to be rejected before media import');
    }

    if (!$driveLetterRejected) {
        throw new RuntimeException('Expected drive-letter ZIP paths to be rejected before media import');
    }

    if (!$directoryPayloadRejected) {
        throw new RuntimeException('Expected ZIP directory entries with payload bytes to be rejected before media import');
    }

    if (!$strongEncryptionRejected) {
        throw new RuntimeException('Expected ZIP strong-encryption metadata to be rejected before media import');
    }

    if (!$centralDirectoryEncryptionRejected) {
        throw new RuntimeException('Expected ZIP central-directory encryption metadata to be rejected before media import');
    }

    if (!$missingTarEndMarkerRejected) {
        throw new RuntimeException('Expected TAR packets without two zero end blocks to be rejected before import');
    }

    $unicodePathEntry = $unicodePathPackage->entry('/' . $unicodePathName);
    if ($unicodePathEntry->rawName !== $unicodePathRawName) {
        throw new RuntimeException('Expected ZIP Unicode path extra field to preserve raw legacy path bytes');
    }

    if ($unicodePathEntry->nameEncoding !== 'info-zip-unicode-path') {
        throw new RuntimeException('Expected ZIP Unicode path extra field to provide decoded media path');
    }

    if ($unicodePathEntry->comment !== $unicodeComment || $unicodePathEntry->commentEncoding !== 'info-zip-unicode-comment') {
        throw new RuntimeException('Expected ZIP Unicode comment extra field to provide decoded media review comment');
    }

    if ($unicodePathPackage->read('/' . $unicodePathName) !== "Unicode media attachment placeholder\n") {
        throw new RuntimeException('Expected Unicode path media attachment bytes to round-trip');
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
echo 'document.xml.reviewExtra=' . ($package->entry('/word/document.xml')->centralExtraField(0xcafe) ?? 'none') . "\n";
echo 'document.xml.localReviewExtra=' . ($package->localExtraField('/word/document.xml', 0xcafe) ?? 'none') . "\n";
echo 'descriptor.comments.xml=' . $descriptorPackage->read('/word/comments.xml') . "\n";
$ntfsTimestamps = $ntfsPackage->entry('/word/media/review.png')->ntfsTimestamps();
echo 'ntfs.review.png.modifiedAt=' . ($ntfsTimestamps['modifiedAt'] ?? 'none') . "\n";
echo 'ntfs.review.png.localModifiedAt=' . ($ntfsPackage->localNtfsLastModifiedTimestamp('/word/media/review.png') ?? 'none') . "\n";
$extendedTimestamps = $extendedTimestampPackage->localExtendedTimestamps('/word/media/reviewer-note.txt') ?? [];
echo 'extended.reviewer-note.modifiedAt=' . ($extendedTimestamps['modifiedAt'] ?? 'none') . "\n";
echo 'extended.reviewer-note.accessedAt=' . ($extendedTimestamps['accessedAt'] ?? 'none') . "\n";
echo 'extended.reviewer-note.createdAt=' . ($extendedTimestamps['createdAt'] ?? 'none') . "\n";
echo 'symlinkPolicy=' . ($symlinkRejected ? 'rejected' : 'not-rejected') . "\n";
echo 'zip64Policy=' . ($zip64Rejected ? 'rejected' : 'not-rejected') . "\n";
echo 'driveLetterPathPolicy=' . ($driveLetterRejected ? 'rejected' : 'not-rejected') . "\n";
echo 'directoryPayloadPolicy=' . ($directoryPayloadRejected ? 'rejected' : 'not-rejected') . "\n";
echo 'strongEncryptionPolicy=' . ($strongEncryptionRejected ? 'rejected' : 'not-rejected') . "\n";
echo 'centralDirectoryEncryptionPolicy=' . ($centralDirectoryEncryptionRejected ? 'rejected' : 'not-rejected') . "\n";
echo 'tarEndMarkerPolicy=' . ($missingTarEndMarkerRejected ? 'rejected' : 'not-rejected') . "\n";
$unicodePathEntry = $unicodePathPackage->entry('/' . $unicodePathName);
echo 'unicodePath.name=' . $unicodePathEntry->name . "\n";
echo 'unicodePath.rawName=' . $unicodePathEntry->rawName . "\n";
echo 'unicodePath.encoding=' . $unicodePathEntry->nameEncoding . "\n";
echo 'unicodePath.comment=' . $unicodePathEntry->comment . "\n";
echo 'gzip.filename=' . $compressedPackageMembers[0]['filename'] . "\n";
echo 'gzip.comment=' . $compressedPackageMembers[0]['comment'] . "\n";
echo 'gzip.extraSubfields=' . implode(',', array_map(static fn (array $field): string => $field['identifier'], $compressedPackageMembers[0]['extraFields'])) . "\n";
echo 'gzip.compressedSize=' . $compressedPackageMembers[0]['compressedSize'] . "\n";
echo 'tar.entries=' . implode(',', $tarPacketRoundTrip->names()) . "\n";
echo 'tar.document.xml=' . $tarPacketRoundTrip->read('/packet/word/document.xml') . "\n";
echo 'tar.detectedFormat=' . $streamDetectedTarFormat . "\n";
echo 'tar.gnuLongName=' . implode(',', $gnuLongNamePacket->names()) . "\n";
echo 'tar.paxDocument=' . $paxMetadataPacket->read('/' . $paxDocumentName) . "\n";
echo 'tar.paxOwner=' . $paxMetadataPacket->entry('/' . $paxDocumentName)->userName . ':' . $paxMetadataPacket->entry('/' . $paxDocumentName)->groupName . "\n";
echo 'tar.base256Owner=' . $base256NumericPacket->entry('/packet/base256/document.xml')->uid . ':' . $base256NumericPacket->entry('/packet/base256/document.xml')->gid . "\n";
echo 'tar.base256ModifiedAt=' . $base256NumericPacket->entry('/packet/base256/document.xml')->modifiedAt . "\n";
echo 'deflate.format=' . $deflateReviewMetadata['format'] . "\n";
echo 'deflate.windowSize=' . $deflateReviewMetadata['windowSize'] . "\n";
echo 'deflate.levelHint=' . $deflateReviewMetadata['compressionLevelHint'] . "\n";
echo 'deflate.document.xml=' . $deflateTarPacketRoundTrip->read('/packet/word/document.xml') . "\n";
echo 'deflate.rawManifest=' . $rawDeflateTarPacketRoundTrip->read('/packet/manifest.json') . "\n";
echo 'lz4.frames=' . count($lz4ReviewFrames) . "\n";
echo 'lz4.skippable=' . $lz4ReviewFrames[0]['data'] . "\n";
echo 'lz4.blockTypes=' . implode(',', $lz4ReviewFrames[1]['blockTypes']) . "\n";
echo 'lz4.document.xml=' . $lz4TarPacketRoundTrip->read('/packet/word/document.xml') . "\n";
echo 'lz4.dependentBlockIndependent=' . (($dependentLz4ReviewFrames[0]['blockIndependent'] ?? true) ? 'true' : 'false') . "\n";
echo 'lz4.dependentIndex=' . $dependentLz4ReviewIndexText . "\n";
