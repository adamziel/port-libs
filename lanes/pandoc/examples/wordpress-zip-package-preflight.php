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
$packUInt64 = static function (int $value): string {
    if ($value < 0) {
        throw new RuntimeException('ZIP64 fixture values must be non-negative');
    }

    return pack('VV', $value & 0xffffffff, intdiv($value, 0x100000000));
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
$buildDependentLz4Payload = static function (): string {
    $historyBlock = '';
    for ($index = 0; strlen($historyBlock) < 65536; $index++) {
        $historyBlock .= hash('sha256', 'wordpress-dependent-lz4-review-' . $index, true);
    }
    $historyBlock = substr($historyBlock, 0, 65536);

    return $historyBlock . substr($historyBlock, 1) . $historyBlock[0];
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
$buildRawUnicodeTraversalBackedPackage = static function () use ($crc32, $buildUnicodeExtra): string {
    $rawName = 'word/../media/review.png';
    $safeUnicodeName = 'word/media/review.png';
    $data = "Raw traversal path should stay blocked\n";
    $crc = $crc32($data);
    $unicodePathExtra = $buildUnicodeExtra(0x7075, $rawName, $safeUnicodeName);

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
        strlen($rawName),
        strlen($unicodePathExtra)
    );
    $body .= $rawName . $unicodePathExtra . $data;

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
        strlen($rawName),
        strlen($unicodePathExtra),
        0,
        0,
        0,
        0x81a40000,
        0
    );
    $central .= $rawName . $unicodePathExtra;

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
$buildOverlappingLocalEntryBackedPackage = static function () use ($crc32): string {
    $documentName = 'word/document.xml';
    $documentData = '<w:document><w:body><w:p>Overlapping layout should stay blocked</w:p></w:body></w:document>';
    $documentCrc = $crc32($documentData);
    $stylesName = 'word/styles.xml';
    $stylesData = '<w:styles/>';
    $stylesCrc = $crc32($stylesData);

    $body = pack(
        'VvvvvvVVVvv',
        0x04034b50,
        20,
        0x0800,
        0,
        0,
        0,
        $documentCrc,
        strlen($documentData),
        strlen($documentData),
        strlen($documentName),
        0
    );
    $body .= $documentName . $documentData;
    $stylesOffset = strlen($body);
    $body .= pack(
        'VvvvvvVVVvv',
        0x04034b50,
        20,
        0x0800,
        0,
        0,
        0,
        $stylesCrc,
        strlen($stylesData),
        strlen($stylesData),
        strlen($stylesName),
        0
    );
    $body .= $stylesName . $stylesData;

    $central = pack(
        'VvvvvvvVVVvvvvvVV',
        0x02014b50,
        0x0314,
        20,
        0x0800,
        0,
        0,
        0,
        $documentCrc,
        strlen($documentData) + 12,
        strlen($documentData) + 12,
        strlen($documentName),
        0,
        0,
        0,
        0,
        0x81a40000,
        0
    );
    $central .= $documentName;
    $central .= pack(
        'VvvvvvvVVVvvvvvVV',
        0x02014b50,
        0x0314,
        20,
        0x0800,
        0,
        0,
        0,
        $stylesCrc,
        strlen($stylesData),
        strlen($stylesData),
        strlen($stylesName),
        0,
        0,
        0,
        0,
        0x81a40000,
        $stylesOffset
    );
    $central .= $stylesName;

    return $body
        . $central
        . pack('VvvvvVVv', 0x06054b50, 0, 0, 2, 2, strlen($central), strlen($body), 0);
};
$buildDuplicateLocalOffsetBackedPackage = static function () use ($crc32): string {
    $firstName = 'word/media/review-one.png';
    $secondName = 'word/media/review-two.png';
    $data = "Duplicate local header offsets should stay blocked\n";
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
        strlen($firstName),
        0
    );
    $body .= $firstName . $data;

    $central = '';
    foreach ([$firstName, $secondName] as $name) {
        $central .= pack(
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
    }

    return $body
        . $central
        . pack('VvvvvVVv', 0x06054b50, 0, 0, 2, 2, strlen($central), strlen($body), 0);
};
$buildCentralDirectorySignatureBackedPackage = static function () use ($crc32): string {
    $name = 'word/document.xml';
    $data = '<w:document><w:body><w:p>Signed central directory metadata is inspectable</w:p></w:body></w:document>';
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
    $centralDirectorySignature = pack('Vv', 0x05054b50, strlen('central-signature')) . 'central-signature';

    return $body
        . $central
        . $centralDirectorySignature
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
$buildVersionNeededMismatchBackedPackage = static function () use ($crc32): string {
    $name = 'word/settings.xml';
    $data = '<w:settings><w:updateFields w:val="true"/></w:settings>';
    $crc = $crc32($data);

    $body = pack(
        'VvvvvvVVVvv',
        0x04034b50,
        10,
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
$buildUnsupportedVersionNeededBackedPackage = static function () use ($crc32): string {
    $name = 'word/media/bzip2-review.bin';
    $data = "Unsupported extraction version should stay blocked\n";
    $crc = $crc32($data);

    $body = pack(
        'VvvvvvVVVvv',
        0x04034b50,
        46,
        0x0800,
        12,
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
        0x031e,
        46,
        0x0800,
        12,
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
$buildUnsupportedCompressionMethodBackedPackage = static function () use ($crc32): string {
    $name = 'word/media/bzip2-review.bin';
    $data = "Unsupported compression method should stay blocked before media bytes\n";
    $crc = $crc32($data);

    $body = pack(
        'VvvvvvVVVvv',
        0x04034b50,
        20,
        0x0800,
        12,
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
        12,
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
$buildUnknownCreatorHostBackedPackage = static function () use ($crc32): string {
    $name = 'word/media/unknown-host-review.bin';
    $data = "Unknown ZIP creator host metadata should stay reviewable\n";
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
        0x3f14,
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
        0,
        0
    );
    $central .= $name;

    return $body
        . $central
        . pack('VvvvvVVv', 0x06054b50, 0, 0, 1, 1, strlen($central), strlen($body), 0);
};
$buildStoredDeflateOptionFlagBackedPackage = static function () use ($crc32): string {
    $name = 'word/media/stored-fast-flags.bin';
    $data = "Stored media bytes must not carry deflate option flags\n";
    $crc = $crc32($data);
    $flags = 0x0806;

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
$buildStoredSizeMismatchBackedPackage = static function () use ($crc32): string {
    $name = 'word/media/stored-review.txt';
    $data = "Stored media bytes must have matching size metadata\n";
    $crc = $crc32($data);
    $declaredUncompressedSize = strlen($data) + 1;

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
        $declaredUncompressedSize,
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
        $declaredUncompressedSize,
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
$buildDosDirectoryAttributeMismatchBackedPackage = static function () use ($crc32): string {
    $name = 'word/media/reviewer-folder';
    $data = '';
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
$rewriteZipEndOfCentralDirectory = static function (string $zip, array $fields): string {
    $eocdOffset = strrpos($zip, "PK\x05\x06");
    if ($eocdOffset === false) {
        throw new RuntimeException('ZIP end-of-central-directory fixture was not found');
    }

    $offsets = [
        'diskNumber' => 4,
        'centralDirectoryDisk' => 6,
        'diskEntryCount' => 8,
        'totalEntryCount' => 10,
        'centralDirectorySize' => 12,
        'centralDirectoryOffset' => 16,
    ];
    foreach ($fields as $field => $value) {
        if (!isset($offsets[$field]) || !is_int($value)) {
            throw new RuntimeException('Unsupported ZIP end-of-central-directory fixture field: ' . (string) $field);
        }

        $format = $field === 'centralDirectorySize' || $field === 'centralDirectoryOffset' ? 'V' : 'v';
        $zip = substr_replace(
            $zip,
            pack($format, $value),
            $eocdOffset + $offsets[$field],
            $format === 'V' ? 4 : 2
        );
    }

    return $zip;
};
$buildZip64EndOfCentralDirectoryBackedPackage = static function (string $zip) use ($packUInt64): string {
    $eocdOffset = strrpos($zip, "PK\x05\x06");
    if ($eocdOffset === false) {
        throw new RuntimeException('ZIP end-of-central-directory fixture was not found');
    }

    $centralDirectorySize = unpack('Vvalue', substr($zip, $eocdOffset + 12, 4))['value'];
    $centralDirectoryOffset = unpack('Vvalue', substr($zip, $eocdOffset + 16, 4))['value'];
    $zip64EocdOffset = $eocdOffset;
    $zip64Eocd = "PK\x06\x06"
        . $packUInt64(44)
        . pack('vvVV', 45, 45, 0, 0)
        . $packUInt64(3)
        . $packUInt64(3)
        . $packUInt64((int) $centralDirectorySize)
        . $packUInt64((int) $centralDirectoryOffset);
    $zip64Locator = "PK\x06\x07"
        . pack('V', 0)
        . $packUInt64($zip64EocdOffset)
        . pack('V', 1);
    $eocd = substr($zip, $eocdOffset);
    $eocd = substr_replace($eocd, pack('v', 0xffff), 8, 2);
    $eocd = substr_replace($eocd, pack('v', 0xffff), 10, 2);
    $eocd = substr_replace($eocd, pack('V', 0xffffffff), 12, 4);
    $eocd = substr_replace($eocd, pack('V', 0xffffffff), 16, 4);

    return substr($zip, 0, $eocdOffset) . $zip64Eocd . $zip64Locator . $eocd;
};
$buildLocalHeaderNameMismatchBackedPackage = static function () use ($crc32): string {
    $centralName = 'word/document.xml';
    $localName = 'word/other.xml';
    $data = '<w:document><w:body><w:p>Local header mismatch should stay blocked</w:p></w:body></w:document>';
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
        strlen($localName),
        0
    );
    $body .= $localName . $data;

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
        strlen($centralName),
        0,
        0,
        0,
        0,
        0x81a40000,
        0
    );
    $central .= $centralName;

    return $body
        . $central
        . pack('VvvvvVVv', 0x06054b50, 0, 0, 1, 1, strlen($central), strlen($body), 0);
};
$buildLocalEntrySlackBackedPackage = static function () use ($crc32): string {
    $name = 'word/document.xml';
    $data = '<w:document><w:body><w:p>Hidden local bytes should stay blocked</w:p></w:body></w:document>';
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
    $body .= $name . $data . 'hidden-review-slack-before-central-directory';

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
$packageArchivePreflight = $package->archivePreflight();
$packageSizePreflight = $package->sizePreflight();
$packageCompressionPreflight = $package->compressionMethodPreflight();
$packagePermissionPreflight = $package->permissionPreflight();
$packageCreatorHostPreflight = $package->creatorHostSystemPreflight();
$packageCommentPreflight = $package->commentPreflight();
$packageExtraFieldPreflight = $package->extraFieldPreflight();
$packageCommentPolicyRejected = false;
try {
    $package->assertNoPackageOrEntryComments();
} catch (RuntimeException $exception) {
    $packageCommentPolicyRejected = str_contains($exception->getMessage(), 'package or entry comments');
}
$packageSizeRejected = false;
try {
    $package->assertSizePreflight($packageSizePreflight['uncompressedBytes'] - 1, null);
} catch (RuntimeException $exception) {
    $packageSizeRejected = str_contains($exception->getMessage(), 'maximum total uncompressed size');
}
$packageExpansionRejected = false;
try {
    $package->assertSizePreflight(null, max(0.0, ($packageSizePreflight['expansionRatio'] ?? 0.0) - 0.01));
} catch (RuntimeException $exception) {
    $packageExpansionRejected = str_contains($exception->getMessage(), 'expansion ratio');
}
$unsupportedCompressionMethodPackage = ZipPackage::fromString($buildUnsupportedCompressionMethodBackedPackage());
$unsupportedCompressionMethodPreflight = $unsupportedCompressionMethodPackage->compressionMethodPreflight();
$unsupportedCompressionMethodRejected = false;
try {
    $unsupportedCompressionMethodPackage->assertSupportedCompressionMethods();
} catch (RuntimeException $exception) {
    $unsupportedCompressionMethodRejected = str_contains($exception->getMessage(), 'unsupported compression methods');
}
$unknownCreatorHostPackage = ZipPackage::fromString($buildUnknownCreatorHostBackedPackage());
$unknownCreatorHostPreflight = $unknownCreatorHostPackage->creatorHostSystemPreflight();
$unknownCreatorHostRejected = false;
try {
    $unknownCreatorHostPackage->assertKnownCreatorHostSystems();
} catch (RuntimeException $exception) {
    $unknownCreatorHostRejected = str_contains($exception->getMessage(), 'unknown creator host-system entries');
}
$duplicateExtraFieldPackage = ZipPackage::fromParts([
    [
        'name' => 'word/media/duplicate-extra-review.bin',
        'data' => "Duplicate ZIP extra field metadata should stay blocked for strict media import\n",
        'compressionMethod' => 0,
        'extraFieldData' => pack('vva*', 0xcafe, strlen('first-review'), 'first-review')
            . pack('vva*', 0xcafe, strlen('second-review'), 'second-review'),
    ],
]);
$duplicateExtraFieldPreflight = $duplicateExtraFieldPackage->extraFieldPreflight();
$duplicateExtraFieldRejected = false;
try {
    $duplicateExtraFieldPackage->assertNoDuplicateExtraFieldIds();
} catch (RuntimeException $exception) {
    $duplicateExtraFieldRejected = str_contains($exception->getMessage(), 'duplicate extra field ids');
}
$deflateOptionFlagsRejected = false;
try {
    ZipPackage::fromString($buildStoredDeflateOptionFlagBackedPackage());
} catch (RuntimeException $exception) {
    $deflateOptionFlagsRejected = str_contains($exception->getMessage(), 'deflate compression option flag bits');
}
$splitZipBytes = $rewriteZipEndOfCentralDirectory($package->bytes(), [
    'diskNumber' => 1,
    'centralDirectoryDisk' => 1,
    'diskEntryCount' => 2,
]);
$splitZipArchivePreflight = ZipPackage::endOfCentralDirectoryPreflight($splitZipBytes);
$splitZipArchiveRejected = false;
try {
    ZipPackage::fromString($splitZipBytes);
} catch (RuntimeException $exception) {
    $splitZipArchiveRejected = str_contains($exception->getMessage(), 'Split ZIP packages');
}
$zip64EocdBytes = $rewriteZipEndOfCentralDirectory($package->bytes(), [
    'diskEntryCount' => 0xffff,
    'totalEntryCount' => 0xffff,
    'centralDirectorySize' => 0xffffffff,
]);
$zip64EocdPreflight = ZipPackage::endOfCentralDirectoryPreflight($zip64EocdBytes);
$zip64EocdRejected = false;
try {
    ZipPackage::fromString($zip64EocdBytes);
} catch (RuntimeException $exception) {
    $zip64EocdRejected = str_contains($exception->getMessage(), 'ZIP64 packages');
}
$zip64LocatorBytes = $buildZip64EndOfCentralDirectoryBackedPackage($package->bytes());
$zip64LocatorPreflight = ZipPackage::endOfCentralDirectoryPreflight($zip64LocatorBytes);
$zip64LocatorRejected = false;
try {
    ZipPackage::fromString($zip64LocatorBytes);
} catch (RuntimeException $exception) {
    $zip64LocatorRejected = str_contains($exception->getMessage(), 'ZIP64 end-of-central-directory');
}
$gzipReviewExtra = pack('CCv', ord('W'), ord('P'), strlen('review:v1')) . 'review:v1';
$descriptorPackage = ZipPackage::fromString($buildDescriptorBackedPackage());
$ntfsPackage = ZipPackage::fromString($buildNtfsBackedPackage());
$extendedTimestampPackage = ZipPackage::fromString($buildExtendedTimestampBackedPackage());
$unicodePathPackage = ZipPackage::fromString($buildUnicodePathBackedPackage());
$oversizedMediaPackage = ZipPackage::fromParts([
    [
        'name' => 'word/document.xml',
        'data' => '<w:document><w:body><w:p>Bounded import source</w:p></w:body></w:document>',
    ],
    [
        'name' => 'word/media/oversized.bin',
        'data' => str_repeat("Oversized reviewer media bytes\n", 12),
    ],
]);
$oversizedMediaRejected = false;
try {
    $oversizedMediaPackage->readBounded('/word/media/oversized.bin', 64);
} catch (RuntimeException $exception) {
    $oversizedMediaRejected = str_contains($exception->getMessage(), 'exceeds maximum uncompressed read size');
}
$executableMediaPackage = ZipPackage::fromParts([
    [
        'name' => 'word/document.xml',
        'data' => '<w:document><w:body><w:p>Executable media review source</w:p></w:body></w:document>',
        'externalAttributes' => 0x81a40000,
    ],
    [
        'name' => 'word/media/reviewer-script.bin',
        'data' => "#!/bin/sh\necho reviewer-script\n",
        'compressionMethod' => 0,
        'externalAttributes' => 0x81ed0000,
    ],
    [
        'name' => 'word/media/',
        'externalAttributes' => 0x41ed0000,
    ],
]);
$executablePermissionRejected = false;
try {
    $executableMediaPackage->assertNoExecutableFiles();
} catch (RuntimeException $exception) {
    $executablePermissionRejected = str_contains($exception->getMessage(), 'Unix executable file entries');
}
$compressedPackage = GzipStream::build($package->bytes(), [
    'modifiedAt' => $documentModifiedAt,
    'extraFieldData' => $gzipReviewExtra,
    'filename' => 'wordpress-import-package.zip',
    'comment' => 'Data Liberation package fixture',
    'headerCrc' => true,
]);
$compressedPackageMembers = GzipStream::members($compressedPackage);
$tarPacketGlobalPaxHeaders = [
    'comment' => 'wordpress import review packet',
    'hdrcharset' => 'BINARY',
];
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
], [
    'globalPaxHeaders' => $tarPacketGlobalPaxHeaders,
]);
$tarPacketBytes = $tarPacket->bytes();
$tarPacketUnpackedBytes = strlen($tarPacket->read('/packet/manifest.json'))
    + strlen($tarPacket->read('/packet/word/document.xml'));
$compressedTarPacket = GzipStream::build($tarPacketBytes, [
    'modifiedAt' => $documentModifiedAt,
    'filename' => 'wordpress-import-packet.tar',
    'comment' => 'gzip tar review packet',
    'headerCrc' => true,
]);
$latin1GzipTarPacket = GzipStream::build($tarPacketBytes, [
    'modifiedAt' => $documentModifiedAt,
    'filename' => "wordpress-r\xE9sum\xE9-packet.tar",
    'comment' => "caf\xE9 gzip tar review packet",
]);
$latin1GzipTarMembers = GzipStream::members($latin1GzipTarPacket);
$latin1GzipTarInspection = ArchiveCompressionStream::inspectTarStream(
    $latin1GzipTarPacket,
    ArchiveCompressionStream::FORMAT_GZIP_TAR,
    strlen($tarPacketBytes),
    $tarPacketUnpackedBytes
);
$reproducibleGzipTarPacket = GzipStream::build($tarPacketBytes, [
    'modifiedAt' => 0,
    'extraFlags' => 4,
    'operatingSystem' => 3,
    'filename' => 'wordpress-reproducible-import-packet.tar',
]);
$reproducibleGzipTarInspection = ArchiveCompressionStream::inspectTarStream(
    $reproducibleGzipTarPacket,
    ArchiveCompressionStream::FORMAT_GZIP_TAR,
    strlen($tarPacketBytes),
    $tarPacketUnpackedBytes
);
$tarPacketRoundTrip = TarArchive::fromString(GzipStream::decode($compressedTarPacket));
$streamDetectedTarFormat = ArchiveCompressionStream::detectTarFormat(
    $compressedTarPacket,
    strlen($tarPacketBytes),
    $tarPacketUnpackedBytes
);
$streamDecodedTarBytes = ArchiveCompressionStream::decodeTarBytesAuto(
    $compressedTarPacket,
    strlen($tarPacketBytes),
    $tarPacketUnpackedBytes
);
$streamDispatchedTarPacket = ArchiveCompressionStream::openTarAuto(
    $compressedTarPacket,
    strlen($tarPacketBytes),
    $tarPacketUnpackedBytes
);
$tarPacketSplitOffset = 700;
$splitGzipTarPacket = GzipStream::build(substr($tarPacketBytes, 0, $tarPacketSplitOffset), [
    'modifiedAt' => $documentModifiedAt,
    'filename' => 'wordpress-import-packet.part-1.tar',
    'comment' => 'split gzip tar member one',
    'extraFlags' => 4,
    'operatingSystem' => 3,
    'extraFieldData' => pack('CCv', ord('W'), ord('P'), strlen('split:1')) . 'split:1',
    'headerCrc' => true,
]) . GzipStream::build(substr($tarPacketBytes, $tarPacketSplitOffset), [
    'modifiedAt' => $documentModifiedAt,
    'filename' => 'wordpress-import-packet.part-2.tar',
    'comment' => 'split gzip tar member two',
    'extraFlags' => 2,
    'operatingSystem' => 255,
    'extraFieldData' => pack('CCv', ord('P'), ord('D'), strlen('split:2')) . 'split:2',
    'headerCrc' => true,
]);
$splitGzipTarInspection = ArchiveCompressionStream::inspectTarStreamAuto(
    $splitGzipTarPacket,
    strlen($tarPacketBytes),
    $tarPacketUnpackedBytes
);
$splitLz4TarPacket = Lz4Frame::skippableFrame('split wordpress archive metadata', 3)
    . Lz4Frame::build(substr($tarPacketBytes, 0, $tarPacketSplitOffset), [
        'contentSize' => true,
        'contentChecksum' => true,
    ])
    . Lz4Frame::build(substr($tarPacketBytes, $tarPacketSplitOffset), [
        'contentSize' => true,
        'contentChecksum' => true,
    ]);
$splitLz4TarInspection = ArchiveCompressionStream::inspectTarStream(
    $splitLz4TarPacket,
    ArchiveCompressionStream::FORMAT_LZ4_TAR,
    strlen($tarPacketBytes),
    $tarPacketUnpackedBytes
);
$separateCompleteTarPacket = TarArchive::fromEntries([
    [
        'name' => 'packet/separate-manifest.json',
        'data' => '{"source":"separate-complete-tar"}',
    ],
]);
$separateCompleteGzipTarRejected = false;
try {
    ArchiveCompressionStream::inspectTarStreamAuto($compressedTarPacket . GzipStream::build($separateCompleteTarPacket->bytes(), [
        'filename' => 'second-complete.tar',
    ]));
} catch (RuntimeException $exception) {
    $separateCompleteGzipTarRejected = str_contains($exception->getMessage(), 'non-zero bytes after the end marker');
}
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
$deflateTarInspection = ArchiveCompressionStream::inspectTarStream(
    $deflateReviewPacket,
    ArchiveCompressionStream::FORMAT_ZLIB_TAR,
    strlen($tarPacketBytes),
    $tarPacketUnpackedBytes
);
$deflateTarPacketRoundTrip = TarArchive::fromString(DeflateStream::decode($deflateReviewPacket));
$rawDeflateReviewPacket = DeflateStream::build($tarPacket->bytes(), [
    'format' => DeflateStream::FORMAT_RAW,
    'compressionLevel' => 9,
]);
$rawDeflateTarInspection = ArchiveCompressionStream::inspectTarStream(
    $rawDeflateReviewPacket,
    ArchiveCompressionStream::FORMAT_RAW_DEFLATE_TAR,
    strlen($tarPacketBytes),
    $tarPacketUnpackedBytes
);
$rawDeflateTarPacketRoundTrip = TarArchive::fromString(DeflateStream::decode(
    $rawDeflateReviewPacket,
    DeflateStream::FORMAT_RAW
));
$deflateTrailingBytesRejected = false;
try {
    DeflateStream::inspectZlib($deflateReviewPacket . 'review-garbage' . substr($deflateReviewPacket, -4));
} catch (RuntimeException $exception) {
    $deflateTrailingBytesRejected = str_contains($exception->getMessage(), 'trailing bytes');
}
$rawDeflateTrailingBytesRejected = false;
try {
    DeflateStream::decode($rawDeflateReviewPacket . 'review-garbage', DeflateStream::FORMAT_RAW);
} catch (RuntimeException $exception) {
    $rawDeflateTrailingBytesRejected = str_contains($exception->getMessage(), 'trailing bytes');
}
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
$dependentLz4BuiltPayload = $buildDependentLz4Payload();
$dependentLz4BuiltPacket = Lz4Frame::build($dependentLz4BuiltPayload, [
    'blockIndependent' => false,
    'blockChecksum' => true,
    'contentChecksum' => true,
    'contentSize' => true,
]);
$dependentLz4BuiltFrames = Lz4Frame::frames($dependentLz4BuiltPacket);
$dependentLz4BuiltPayloadRoundTrip = Lz4Frame::decode($dependentLz4BuiltPacket);
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
$rawUnicodeTraversalRejected = false;
try {
    ZipPackage::fromString($buildRawUnicodeTraversalBackedPackage());
} catch (RuntimeException $exception) {
    $rawUnicodeTraversalRejected = str_contains($exception->getMessage(), 'Unsafe ZIP package entry name');
}
$directoryPayloadRejected = false;
try {
    ZipPackage::fromString($buildDirectoryPayloadBackedPackage());
} catch (RuntimeException $exception) {
    $directoryPayloadRejected = str_contains($exception->getMessage(), 'directory entry');
}
$localEntryOverlapRejected = false;
try {
    ZipPackage::fromString($buildOverlappingLocalEntryBackedPackage());
} catch (RuntimeException $exception) {
    $localEntryOverlapRejected = str_contains($exception->getMessage(), 'overlaps the next local header')
        || str_contains($exception->getMessage(), 'local header sizes');
}
$storedSizeMismatchRejected = false;
try {
    ZipPackage::fromString($buildStoredSizeMismatchBackedPackage());
} catch (RuntimeException $exception) {
    $storedSizeMismatchRejected = str_contains($exception->getMessage(), 'Stored ZIP entry')
        && str_contains($exception->getMessage(), 'mismatched compressed and uncompressed sizes');
}
$dosDirectoryAttributeMismatchRejected = false;
try {
    ZipPackage::fromString($buildDosDirectoryAttributeMismatchBackedPackage());
} catch (RuntimeException $exception) {
    $dosDirectoryAttributeMismatchRejected = str_contains($exception->getMessage(), 'directory external attributes')
        && str_contains($exception->getMessage(), 'not named as a directory');
}
$duplicateLocalOffsetRejected = false;
try {
    ZipPackage::fromString($buildDuplicateLocalOffsetBackedPackage());
} catch (RuntimeException $exception) {
    $duplicateLocalOffsetRejected = str_contains($exception->getMessage(), 'Duplicate ZIP local header offset');
}
$centralDirectorySignaturePackage = ZipPackage::fromString($buildCentralDirectorySignatureBackedPackage());
$centralDirectorySignaturePreflight = $centralDirectorySignaturePackage->centralDirectorySignaturePreflight();
$centralDirectorySignatureParsed = ($centralDirectorySignaturePreflight['signatureData'] ?? null) === 'central-signature'
    && ($centralDirectorySignaturePreflight['cryptographicVerification'] ?? null) === 'not-performed-native-bounded-reader'
    && $centralDirectorySignaturePackage->read('/word/document.xml') === '<w:document><w:body><w:p>Signed central directory metadata is inspectable</w:p></w:body></w:document>';
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
$compressedPatchedDataRejected = false;
try {
    ZipPackage::fromString($buildEncryptedMetadataBackedPackage(0x0820));
} catch (RuntimeException $exception) {
    $compressedPatchedDataRejected = str_contains($exception->getMessage(), 'Compressed-patched ZIP entries');
}
$versionNeededMismatchRejected = false;
try {
    ZipPackage::fromString($buildVersionNeededMismatchBackedPackage());
} catch (RuntimeException $exception) {
    $versionNeededMismatchRejected = str_contains($exception->getMessage(), 'version needed to extract');
}
$unsupportedVersionNeededRejected = false;
try {
    ZipPackage::fromString($buildUnsupportedVersionNeededBackedPackage());
} catch (RuntimeException $exception) {
    $unsupportedVersionNeededRejected = str_contains($exception->getMessage(), 'version needed to extract');
}
$localHeaderNameMismatchRejected = false;
try {
    ZipPackage::fromString($buildLocalHeaderNameMismatchBackedPackage());
} catch (RuntimeException $exception) {
    $localHeaderNameMismatchRejected = str_contains($exception->getMessage(), 'local header name');
}
$localEntrySlackRejected = false;
try {
    ZipPackage::fromString($buildLocalEntrySlackBackedPackage());
} catch (RuntimeException $exception) {
    $localEntrySlackRejected = str_contains($exception->getMessage(), 'unexpected trailing bytes');
}
$missingTarEndMarkerRejected = false;
try {
    TarArchive::fromString($buildRawTarRecord('packet/missing-end-marker.xml', '0', '<w:document/>', $documentModifiedAt));
} catch (RuntimeException $exception) {
    $missingTarEndMarkerRejected = str_contains($exception->getMessage(), 'end marker');
}
$danglingPaxMetadataRejected = false;
try {
    TarArchive::fromString(
        $buildRawTarRecord('PaxHeaders/dangling', 'x', $buildPaxPayload([
            'path' => 'packet/dangling/document.xml',
        ]), $documentModifiedAt)
        . str_repeat("\0", 1024)
    );
} catch (RuntimeException $exception) {
    $danglingPaxMetadataRejected = str_contains($exception->getMessage(), 'PAX extended metadata');
}
$tarDriveLetterRejected = false;
try {
    TarArchive::fromString($buildRawTarRecord('C:packet/document.xml', '0', '<w:document/>', $documentModifiedAt) . str_repeat("\0", 1024));
} catch (RuntimeException $exception) {
    $tarDriveLetterRejected = str_contains($exception->getMessage(), 'Unsafe TAR entry name');
}
$tarSparseRejected = false;
try {
    TarArchive::fromString(
        $buildRawTarRecord('PaxHeaders/sparse', 'x', $buildPaxPayload([
            'path' => 'packet/sparse-review.bin',
            'GNU.sparse.major' => '1',
            'GNU.sparse.minor' => '0',
            'GNU.sparse.realsize' => '4096',
            'GNU.sparse.map' => '0,16,4080,16',
        ]), $documentModifiedAt)
        . $buildRawTarRecord('placeholder-sparse.bin', '0', 'sparse payload fragment', $documentModifiedAt)
        . str_repeat("\0", 1024)
    );
} catch (RuntimeException $exception) {
    $tarSparseRejected = str_contains($exception->getMessage(), 'sparse file entries');
}
$tarUstarVersionRejected = false;
try {
    TarArchive::fromString(
        $rewriteTarHeaderFields(
            $buildRawTarRecord('packet/bad-ustar-version.xml', '0', '<w:document/>', $documentModifiedAt),
            [
                263 => '99',
            ]
        ) . str_repeat("\0", 1024)
    );
} catch (RuntimeException $exception) {
    $tarUstarVersionRejected = str_contains($exception->getMessage(), 'ustar version');
}
$tarGlobalPaxPerEntryRejected = false;
try {
    TarArchive::fromString(
        $buildRawTarRecord('GlobalHead/path', 'g', $buildPaxPayload([
            'path' => 'packet/global-pax-document.xml',
        ]), $documentModifiedAt)
        . $buildRawTarRecord('packet/original-document.xml', '0', '<w:document/>', $documentModifiedAt)
        . str_repeat("\0", 1024)
    );
} catch (RuntimeException $exception) {
    $tarGlobalPaxPerEntryRejected = str_contains($exception->getMessage(), 'global PAX header path');
}
$tarPaxLinkpathRejected = false;
try {
    TarArchive::fromString(
        $buildRawTarRecord('PaxHeaders/linkpath', 'x', $buildPaxPayload([
            'path' => 'packet/linkpath-regular.xml',
            'linkpath' => 'packet/target.xml',
        ]), $documentModifiedAt)
        . $buildRawTarRecord('placeholder.xml', '0', '<w:document/>', $documentModifiedAt)
        . str_repeat("\0", 1024)
    );
} catch (RuntimeException $exception) {
    $tarPaxLinkpathRejected = str_contains($exception->getMessage(), 'PAX linkpath');
}
$tarGnuLongLinkRejected = false;
try {
    TarArchive::fromString(
        $buildRawTarRecord('././@LongLink', 'K', 'packet/target.xml' . "\0", $documentModifiedAt)
        . $buildRawTarRecord('placeholder.xml', '0', '<w:document/>', $documentModifiedAt)
        . str_repeat("\0", 1024)
    );
} catch (RuntimeException $exception) {
    $tarGnuLongLinkRejected = str_contains($exception->getMessage(), 'GNU long-link metadata');
}
$tarPaxUtf8PathRejected = false;
try {
    TarArchive::fromString(
        $buildRawTarRecord('PaxHeaders/invalid-utf8-path', 'x', $buildPaxPayload([
            'path' => "packet/invalid-\xC3\x28.xml",
        ]), $documentModifiedAt)
        . $buildRawTarRecord('placeholder.xml', '0', '<w:document/>', $documentModifiedAt)
        . str_repeat("\0", 1024)
    );
} catch (RuntimeException $exception) {
    $tarPaxUtf8PathRejected = str_contains($exception->getMessage(), 'PAX path metadata');
}
$tarUstarPathUtf8Rejected = false;
try {
    TarArchive::fromString(
        $buildRawTarRecord("packet/invalid-\xC3\x28.xml", '0', '<w:document/>', $documentModifiedAt)
        . str_repeat("\0", 1024)
    );
} catch (RuntimeException $exception) {
    $tarUstarPathUtf8Rejected = str_contains($exception->getMessage(), 'TAR entry name');
}
$tarGnuLongNameUtf8Rejected = false;
try {
    TarArchive::fromString(
        $buildRawTarRecord('././@LongLink', 'L', "packet/invalid-\xC3\x28.xml\0", $documentModifiedAt)
        . $buildRawTarRecord('placeholder.xml', '0', '<w:document/>', $documentModifiedAt)
        . str_repeat("\0", 1024)
    );
} catch (RuntimeException $exception) {
    $tarGnuLongNameUtf8Rejected = str_contains($exception->getMessage(), 'GNU long name metadata');
}
$tarOwnerUtf8Rejected = false;
$tarUstarOwnerUtf8Rejected = false;
try {
    TarArchive::fromString(
        $rewriteTarHeaderFields(
            $buildRawTarRecord('packet/invalid-owner.xml', '0', '<w:document/>', $documentModifiedAt),
            [
                265 => str_pad("reviewer-\xC3\x28", 32, "\0"),
            ]
        ) . str_repeat("\0", 1024)
    );
} catch (RuntimeException $exception) {
    $tarUstarOwnerUtf8Rejected = str_contains($exception->getMessage(), 'ustar user name metadata');
}
$tarPaxOwnerUtf8Rejected = false;
try {
    TarArchive::fromString(
        $buildRawTarRecord('PaxHeaders/invalid-owner', 'x', $buildPaxPayload([
            'path' => 'packet/invalid-pax-owner.xml',
            'gname' => "import-\xC3\x28",
        ]), $documentModifiedAt)
        . $buildRawTarRecord('placeholder.xml', '0', '<w:document/>', $documentModifiedAt)
        . str_repeat("\0", 1024)
    );
} catch (RuntimeException $exception) {
    $tarPaxOwnerUtf8Rejected = str_contains($exception->getMessage(), 'PAX gname metadata');
}
$tarOwnerUtf8Rejected = $tarUstarOwnerUtf8Rejected && $tarPaxOwnerUtf8Rejected;
$tarPaxReviewMetadataUtf8Rejected = false;
$tarPaxReviewMetadataGlobalUtf8Rejected = false;
try {
    TarArchive::fromString(
        $buildRawTarRecord('GlobalHead/invalid-review-comment', 'g', $buildPaxPayload([
            'comment' => "bad-\xC3\x28",
        ]), $documentModifiedAt)
        . $buildRawTarRecord('packet/invalid-review-comment.xml', '0', '<w:document/>', $documentModifiedAt)
        . str_repeat("\0", 1024)
    );
} catch (RuntimeException $exception) {
    $tarPaxReviewMetadataGlobalUtf8Rejected = str_contains($exception->getMessage(), 'PAX comment metadata');
}
$tarPaxReviewMetadataLocalUtf8Rejected = false;
try {
    TarArchive::fromString(
        $buildRawTarRecord('PaxHeaders/invalid-review-key', 'x', $buildPaxPayload([
            'path' => 'packet/invalid-review-key.xml',
            "review-\xC3\x28" => 'bad-key',
        ]), $documentModifiedAt)
        . $buildRawTarRecord('placeholder.xml', '0', '<w:document/>', $documentModifiedAt)
        . str_repeat("\0", 1024)
    );
} catch (RuntimeException $exception) {
    $tarPaxReviewMetadataLocalUtf8Rejected = str_contains($exception->getMessage(), 'PAX header key metadata');
}
$tarPaxReviewMetadataGeneratedUtf8Rejected = false;
try {
    TarArchive::fromEntries([
        ['name' => 'packet/generated-invalid-review.xml', 'data' => '<w:document/>'],
    ], [
        'globalPaxHeaders' => [
            'comment' => "bad-\xC3\x28",
        ],
    ]);
} catch (RuntimeException $exception) {
    $tarPaxReviewMetadataGeneratedUtf8Rejected = str_contains($exception->getMessage(), 'PAX headers comment metadata');
}
$tarPaxReviewMetadataUtf8Rejected = $tarPaxReviewMetadataGlobalUtf8Rejected
    && $tarPaxReviewMetadataLocalUtf8Rejected
    && $tarPaxReviewMetadataGeneratedUtf8Rejected;

if (in_array('--self-test', $argv, true)) {
    if (!$package->has('/word/document.xml')) {
        throw new RuntimeException('Expected word/document.xml to be discoverable as an OPC part');
    }

    if ($package->read('/word/document.xml') !== '<w:document><w:body><w:p>WordPress import source</w:p></w:body></w:document>') {
        throw new RuntimeException('Expected document part bytes to round-trip from the ZIP package');
    }

    if ($package->readBounded('/word/document.xml', 2048) !== '<w:document><w:body><w:p>WordPress import source</w:p></w:body></w:document>') {
        throw new RuntimeException('Expected bounded document part bytes to round-trip from the ZIP package');
    }

    if (!$oversizedMediaRejected) {
        throw new RuntimeException('Expected oversized ZIP media reads to be rejected before import');
    }

    if (($packageSizePreflight['entryCount'] ?? null) !== 3 || ($packageSizePreflight['fileCount'] ?? null) !== 3) {
        throw new RuntimeException('Expected ZIP package size preflight to count importable package files');
    }

    if (($packageSizePreflight['uncompressedBytes'] ?? 0) <= ($packageSizePreflight['compressedBytes'] ?? 0)) {
        throw new RuntimeException('Expected ZIP package size preflight to report aggregate expansion');
    }

    if (($packageSizePreflight['largestEntry']['name'] ?? null) !== 'word/document.xml') {
        throw new RuntimeException('Expected ZIP package size preflight to identify the largest import part');
    }

    if ($package->assertSizePreflight($packageSizePreflight['uncompressedBytes'], $packageSizePreflight['expansionRatio']) !== $packageSizePreflight) {
        throw new RuntimeException('Expected ZIP package size preflight limits to return the accepted summary');
    }

    if (
        ($packageCompressionPreflight['entryCount'] ?? null) !== 3
        || ($packageCompressionPreflight['supportedEntryCount'] ?? null) !== 3
        || ($packageCompressionPreflight['unsupportedCompressionMethodCount'] ?? null) !== 0
        || ($packageCompressionPreflight['deflatedEntryCount'] ?? null) !== 2
    ) {
        throw new RuntimeException('Expected ZIP compression method preflight to accept generated stored/deflated import parts');
    }

    if ($package->assertSupportedCompressionMethods() !== $packageCompressionPreflight) {
        throw new RuntimeException('Expected ZIP compression method preflight to return the accepted summary');
    }

    if (($unsupportedCompressionMethodPreflight['unsupportedEntries'][0]['compressionMethod'] ?? null) !== 12) {
        throw new RuntimeException('Expected ZIP compression method preflight to expose unsupported method 12');
    }

    if (!$unsupportedCompressionMethodRejected) {
        throw new RuntimeException('Expected unsupported ZIP compression methods to be rejected before media import');
    }

    if (!$deflateOptionFlagsRejected) {
        throw new RuntimeException('Expected deflate option flags on stored ZIP entries to be rejected before media import');
    }

    if (!$storedSizeMismatchRejected) {
        throw new RuntimeException('Expected stored ZIP entry size mismatches to be rejected before media import');
    }

    if (($packagePermissionPreflight['entryCount'] ?? null) !== 3 || ($packagePermissionPreflight['executableFileCount'] ?? null) !== 0) {
        throw new RuntimeException('Expected ZIP package permission preflight to accept generated non-executable import parts');
    }

    if ($package->assertNoExecutableFiles() !== $packagePermissionPreflight) {
        throw new RuntimeException('Expected ZIP package permission preflight to return the accepted summary');
    }

    if (
        ($packageCreatorHostPreflight['entryCount'] ?? null) !== 3
        || ($packageCreatorHostPreflight['knownHostSystemEntryCount'] ?? null) !== 3
        || ($packageCreatorHostPreflight['unknownHostSystemEntryCount'] ?? null) !== 0
        || ($packageCreatorHostPreflight['hostSystems'][0]['name'] ?? null) !== 'unix'
    ) {
        throw new RuntimeException('Expected ZIP creator host-system preflight to identify generated Unix package parts');
    }

    if ($package->assertKnownCreatorHostSystems() !== $packageCreatorHostPreflight) {
        throw new RuntimeException('Expected known ZIP creator host systems to return the accepted summary');
    }

    if ($package->assertNoDuplicateExtraFieldIds() !== $packageExtraFieldPreflight) {
        throw new RuntimeException('Expected generated ZIP package extra fields to return the accepted summary');
    }

    if (($packageExtraFieldPreflight['duplicateExtraFieldEntryCount'] ?? null) !== 0) {
        throw new RuntimeException('Expected generated ZIP package to avoid duplicate extra field ids');
    }

    if (
        !$unknownCreatorHostRejected
        || ($unknownCreatorHostPreflight['unknownHostSystemEntryCount'] ?? null) !== 1
        || ($unknownCreatorHostPreflight['unknownEntries'][0]['madeByHostSystem'] ?? null) !== 63
        || ($unknownCreatorHostPreflight['unknownEntries'][0]['name'] ?? null) !== 'word/media/unknown-host-review.bin'
    ) {
        throw new RuntimeException('Expected unknown ZIP creator host systems to stay blocked for reviewer import');
    }

    if (($duplicateExtraFieldPreflight['duplicateEntries'][0]['name'] ?? null) !== 'word/media/duplicate-extra-review.bin') {
        throw new RuntimeException('Expected ZIP duplicate extra-field preflight to expose the conflicting media entry');
    }

    if (($duplicateExtraFieldPreflight['duplicateEntries'][0]['duplicateCentralExtraFieldIds'][0] ?? null) !== 0xcafe) {
        throw new RuntimeException('Expected ZIP duplicate extra-field preflight to expose duplicate central field id 0xcafe');
    }

    if (($duplicateExtraFieldPreflight['duplicateEntries'][0]['duplicateLocalExtraFieldIds'][0] ?? null) !== 0xcafe) {
        throw new RuntimeException('Expected ZIP duplicate extra-field preflight to expose duplicate local field id 0xcafe');
    }

    if (!$duplicateExtraFieldRejected) {
        throw new RuntimeException('Expected duplicate ZIP extra field ids to stay blocked for strict media import');
    }

    if (!$packageSizeRejected) {
        throw new RuntimeException('Expected aggregate ZIP package size limits to reject oversized packages before import');
    }

    if (!$packageExpansionRejected) {
        throw new RuntimeException('Expected aggregate ZIP package expansion ratio limits to reject compressed packages before import');
    }

    if ($package->packageComment() !== 'wordpress import package') {
        throw new RuntimeException('Expected package comment metadata to round-trip from the generated ZIP package');
    }

    if (($packageCommentPreflight['packageComment'] ?? null) !== 'wordpress import package') {
        throw new RuntimeException('Expected ZIP package comment preflight to expose the decoded package comment');
    }

    if (($packageCommentPreflight['packageCommentEncoding'] ?? null) !== 'utf-8') {
        throw new RuntimeException('Expected ZIP package comment preflight to expose comment encoding');
    }

    if (($packageCommentPreflight['entryCommentCount'] ?? null) !== 1) {
        throw new RuntimeException('Expected ZIP package comment preflight to count reviewer entry comments');
    }

    if (($packageCommentPreflight['hasComments'] ?? null) !== true) {
        throw new RuntimeException('Expected ZIP package comment preflight to mark package or entry comments as present');
    }

    if (($packageCommentPreflight['commentedEntryNames'] ?? null) !== ['word/document.xml']) {
        throw new RuntimeException('Expected ZIP package comment preflight to expose commented entry names');
    }

    if (!$packageCommentPolicyRejected) {
        throw new RuntimeException('Expected strict ZIP package comment policy to reject package or entry comments');
    }

    if (($packageCommentPreflight['commentedEntries'][0]['name'] ?? null) !== 'word/document.xml') {
        throw new RuntimeException('Expected ZIP package comment preflight to identify the commented document part');
    }

    if (($packageCommentPreflight['commentedEntries'][0]['comment'] ?? null) !== 'generated document part') {
        throw new RuntimeException('Expected ZIP package comment preflight to expose document part comment metadata');
    }

    if (
        ($packageArchivePreflight['isSingleDisk'] ?? null) !== true
        || ($packageArchivePreflight['isArchiveLayoutSupported'] ?? null) !== true
        || ($packageArchivePreflight['totalEntryCount'] ?? null) !== 3
        || ($packageArchivePreflight['packageComment'] ?? null) !== 'wordpress import package'
    ) {
        throw new RuntimeException('Expected ZIP archive EOCD preflight to accept the generated single-disk package');
    }

    if (
        ($splitZipArchivePreflight['isSingleDisk'] ?? null) !== false
        || ($splitZipArchivePreflight['isArchiveLayoutSupported'] ?? null) !== false
        || !$splitZipArchiveRejected
    ) {
        throw new RuntimeException('Expected split ZIP EOCD metadata to be reported and rejected before import');
    }

    if (
        ($zip64EocdPreflight['requiresZip64'] ?? null) !== true
        || ($zip64EocdPreflight['isArchiveLayoutSupported'] ?? null) !== false
        || !$zip64EocdRejected
    ) {
        throw new RuntimeException('Expected ZIP64 EOCD markers to be reported and rejected before import');
    }

    if (
        ($zip64LocatorPreflight['hasZip64EndOfCentralDirectoryLocator'] ?? null) !== true
        || ($zip64LocatorPreflight['hasZip64EndOfCentralDirectory'] ?? null) !== true
        || ($zip64LocatorPreflight['zip64EndOfCentralDirectorySize'] ?? null) !== 56
        || !$zip64LocatorRejected
    ) {
        throw new RuntimeException('Expected ZIP64 EOCD locator metadata to be reported and rejected before import');
    }

    if (($package->localNames()[0] ?? null) !== '[Content_Types].xml') {
        throw new RuntimeException('Expected local ZIP entry order to be inspectable for package preflight');
    }

    if (($package->localEntries()[0]->localHeaderOffset ?? -1) !== 0) {
        throw new RuntimeException('Expected first local ZIP entry to start at package offset zero');
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

    if (($latin1GzipTarMembers[0]['filename'] ?? null) !== "wordpress-r\xE9sum\xE9-packet.tar") {
        throw new RuntimeException('Expected gzip Latin-1 filename raw bytes to stay available');
    }

    if (($latin1GzipTarMembers[0]['filenameText'] ?? null) !== "wordpress-r\u{00E9}sum\u{00E9}-packet.tar") {
        throw new RuntimeException('Expected gzip Latin-1 filename to decode to UTF-8 review text');
    }

    if (($latin1GzipTarMembers[0]['commentText'] ?? null) !== "caf\u{00E9} gzip tar review packet") {
        throw new RuntimeException('Expected gzip Latin-1 comment to decode to UTF-8 review text');
    }

    if (($latin1GzipTarInspection['stream']['members'][0]['filenameText'] ?? null) !== "wordpress-r\u{00E9}sum\u{00E9}-packet.tar") {
        throw new RuntimeException('Expected archive inspection to expose decoded gzip filename text');
    }

    if ($latin1GzipTarInspection['archive']->read('/packet/manifest.json') !== '{"source":"wordpress-import","container":"tar"}') {
        throw new RuntimeException('Expected Latin-1 gzip tar packet manifest bytes to round-trip');
    }

    $reproducibleGzipMember = $reproducibleGzipTarInspection['stream']['members'][0] ?? [];
    if (($reproducibleGzipMember['modifiedAtKnown'] ?? null) !== false) {
        throw new RuntimeException('Expected reproducible gzip tar packet to mark zero MTIME as absent');
    }

    if (!array_key_exists('modifiedAtText', $reproducibleGzipMember) || $reproducibleGzipMember['modifiedAtText'] !== null) {
        throw new RuntimeException('Expected reproducible gzip tar packet to omit timestamp review text');
    }

    if (($reproducibleGzipMember['extraFlagsMeaning'] ?? null) !== 'fastest-compression') {
        throw new RuntimeException('Expected gzip XFL review label to identify fastest compression');
    }

    if (($reproducibleGzipMember['operatingSystemName'] ?? null) !== 'unix') {
        throw new RuntimeException('Expected gzip OS review label to identify Unix provenance');
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

    if ($tarPacketRoundTrip->globalPaxHeaders() !== $tarPacketGlobalPaxHeaders) {
        throw new RuntimeException('Expected TAR global PAX review metadata to round-trip');
    }

    if (($tarPacketRoundTrip->entry('/packet/manifest.json')->paxHeaders['comment'] ?? null) !== 'wordpress import review packet') {
        throw new RuntimeException('Expected TAR global PAX review comment to be visible on entries');
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

    if (($splitGzipTarInspection['stream']['memberCount'] ?? 0) !== 2) {
        throw new RuntimeException('Expected split gzip tar packet to expose both gzip members');
    }

    if (($splitGzipTarInspection['entryNames'] ?? []) !== ['packet/', 'packet/manifest.json', 'packet/word/document.xml']) {
        throw new RuntimeException('Expected split gzip tar inspection to preserve tar entry order');
    }

    $splitGzipMemberFilenames = array_map(
        static fn (array $member): ?string => $member['filename'],
        $splitGzipTarInspection['stream']['members'] ?? []
    );
    if ($splitGzipMemberFilenames !== ['wordpress-import-packet.part-1.tar', 'wordpress-import-packet.part-2.tar']) {
        throw new RuntimeException('Expected split gzip tar packet member filenames to be inspectable');
    }

    $splitGzipMemberExtraFlags = array_map(
        static fn (array $member): int => $member['extraFlags'],
        $splitGzipTarInspection['stream']['members'] ?? []
    );
    if ($splitGzipMemberExtraFlags !== [4, 2]) {
        throw new RuntimeException('Expected split gzip tar packet member XFL values to be inspectable');
    }

    $splitGzipMemberExtraFlagMeanings = array_map(
        static fn (array $member): string => $member['extraFlagsMeaning'],
        $splitGzipTarInspection['stream']['members'] ?? []
    );
    if ($splitGzipMemberExtraFlagMeanings !== ['fastest-compression', 'maximum-compression']) {
        throw new RuntimeException('Expected split gzip tar packet member XFL labels to be inspectable');
    }

    $splitGzipMemberOperatingSystems = array_map(
        static fn (array $member): int => $member['operatingSystem'],
        $splitGzipTarInspection['stream']['members'] ?? []
    );
    if ($splitGzipMemberOperatingSystems !== [3, 255]) {
        throw new RuntimeException('Expected split gzip tar packet member OS values to be inspectable');
    }

    $splitGzipMemberOperatingSystemNames = array_map(
        static fn (array $member): string => $member['operatingSystemName'],
        $splitGzipTarInspection['stream']['members'] ?? []
    );
    if ($splitGzipMemberOperatingSystemNames !== ['unix', 'unknown']) {
        throw new RuntimeException('Expected split gzip tar packet member OS labels to be inspectable');
    }

    $splitGzipMemberExtraFields = array_map(
        static fn (array $member): ?string => $member['extraFields'][0]['identifier'] ?? null,
        $splitGzipTarInspection['stream']['members'] ?? []
    );
    if ($splitGzipMemberExtraFields !== ['WP', 'PD']) {
        throw new RuntimeException('Expected split gzip tar packet extra subfields to be inspectable');
    }

    $splitGzipMemberCrc32 = array_map(
        static fn (array $member): int => $member['crc32'],
        $splitGzipTarInspection['stream']['members'] ?? []
    );
    if ($splitGzipMemberCrc32 !== [
        (int) sprintf('%u', crc32(substr($tarPacketBytes, 0, $tarPacketSplitOffset))),
        (int) sprintf('%u', crc32(substr($tarPacketBytes, $tarPacketSplitOffset))),
    ]) {
        throw new RuntimeException('Expected split gzip tar packet member CRC32 values to be inspectable');
    }

    if ($splitGzipTarInspection['archive']->read('/packet/word/document.xml') !== '<w:document><w:body><w:p>Tar packet WordPress source</w:p></w:body></w:document>') {
        throw new RuntimeException('Expected split gzip tar packet document bytes to round-trip');
    }

    if (($splitLz4TarInspection['stream']['frameCount'] ?? 0) !== 3 || ($splitLz4TarInspection['stream']['dataFrameCount'] ?? 0) !== 2) {
        throw new RuntimeException('Expected split LZ4 tar packet to expose skippable metadata plus two data frames');
    }

    if (($splitLz4TarInspection['stream']['skippableFrameCount'] ?? 0) !== 1) {
        throw new RuntimeException('Expected split LZ4 tar packet to preserve one skippable metadata frame');
    }

    if (($splitLz4TarInspection['stream']['frames'][0]['data'] ?? '') !== 'split wordpress archive metadata') {
        throw new RuntimeException('Expected split LZ4 skippable metadata to be inspectable');
    }

    if ($splitLz4TarInspection['archive']->read('/packet/manifest.json') !== '{"source":"wordpress-import","container":"tar"}') {
        throw new RuntimeException('Expected split LZ4 tar packet manifest bytes to round-trip');
    }

    if (!$separateCompleteGzipTarRejected) {
        throw new RuntimeException('Expected concatenated complete gzip tar archives to be rejected before import');
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

    if (($deflateTarInspection['stream']['compressedPayloadSize'] ?? null) !== strlen($deflateReviewPacket) - 6) {
        throw new RuntimeException('Expected zlib-wrapped deflate compressed payload size to be inspectable');
    }

    if (($deflateTarInspection['stream']['uncompressedSize'] ?? null) !== strlen($tarPacketBytes)) {
        throw new RuntimeException('Expected zlib-wrapped deflate uncompressed tar size to be inspectable');
    }

    if ($deflateTarPacketRoundTrip->read('/packet/word/document.xml') !== '<w:document><w:body><w:p>Tar packet WordPress source</w:p></w:body></w:document>') {
        throw new RuntimeException('Expected zlib-wrapped deflate tar document bytes to round-trip');
    }

    if (($rawDeflateTarInspection['stream']['compressedPayloadSize'] ?? null) !== strlen($rawDeflateReviewPacket)) {
        throw new RuntimeException('Expected raw deflate compressed payload size to be inspectable');
    }

    if (($rawDeflateTarInspection['stream']['uncompressedSize'] ?? null) !== strlen($tarPacketBytes)) {
        throw new RuntimeException('Expected raw deflate uncompressed tar size to be inspectable');
    }

    if ($rawDeflateTarPacketRoundTrip->read('/packet/manifest.json') !== '{"source":"wordpress-import","container":"tar"}') {
        throw new RuntimeException('Expected raw deflate tar manifest bytes to round-trip');
    }

    if (!$deflateTrailingBytesRejected || !$rawDeflateTrailingBytesRejected) {
        throw new RuntimeException('Expected deflate review packets with trailing bytes to be rejected before package handoff');
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

    if (($dependentLz4BuiltFrames[0]['blockIndependent'] ?? true) !== false) {
        throw new RuntimeException('Expected built dependent LZ4 packet to clear the block-independence flag');
    }

    if (($dependentLz4BuiltFrames[0]['blockTypes'] ?? []) !== ['uncompressed', 'compressed']) {
        throw new RuntimeException('Expected built dependent LZ4 packet to compress the second block from prior history');
    }

    if ($dependentLz4BuiltPayloadRoundTrip !== $dependentLz4BuiltPayload) {
        throw new RuntimeException('Expected built dependent LZ4 packet bytes to decode across block history');
    }

    if (strlen($dependentLz4BuiltPacket) >= strlen($dependentLz4BuiltPayload)) {
        throw new RuntimeException('Expected built dependent LZ4 packet to be smaller than the review payload');
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

    if (!$rawUnicodeTraversalRejected) {
        throw new RuntimeException('Expected unsafe raw ZIP names with safe Unicode metadata to be rejected before media import');
    }

    if (!$directoryPayloadRejected) {
        throw new RuntimeException('Expected ZIP directory entries with payload bytes to be rejected before media import');
    }

    if (!$dosDirectoryAttributeMismatchRejected) {
        throw new RuntimeException('Expected ZIP non-directory names with directory attributes to be rejected before media import');
    }

    if (!$localEntryOverlapRejected) {
        throw new RuntimeException('Expected ZIP local entry overlap to be rejected before media import');
    }

    if (!$duplicateLocalOffsetRejected) {
        throw new RuntimeException('Expected duplicate ZIP local header offsets to be rejected before media import');
    }

    if (!$centralDirectorySignatureParsed) {
        throw new RuntimeException('Expected ZIP central-directory digital signature metadata to be inspectable before media import');
    }

    if (($centralDirectorySignaturePreflight['signatureLength'] ?? null) !== strlen('central-signature')) {
        throw new RuntimeException('Expected ZIP central-directory digital signature length to be preserved for review');
    }

    if (!$strongEncryptionRejected) {
        throw new RuntimeException('Expected ZIP strong-encryption metadata to be rejected before media import');
    }

    if (!$centralDirectoryEncryptionRejected) {
        throw new RuntimeException('Expected ZIP central-directory encryption metadata to be rejected before media import');
    }

    if (!$compressedPatchedDataRejected) {
        throw new RuntimeException('Expected ZIP compressed-patched data metadata to be rejected before media import');
    }

    if ($package->entry('/word/document.xml')->neededToExtractVersion() !== 20) {
        throw new RuntimeException('Expected ZIP version-needed metadata to be exposed for package preflight');
    }

    if (!$versionNeededMismatchRejected) {
        throw new RuntimeException('Expected ZIP local version-needed mismatches to be rejected before media import');
    }

    if (!$unsupportedVersionNeededRejected) {
        throw new RuntimeException('Expected unsupported ZIP version-needed metadata to be rejected before media import');
    }

    if (!$localHeaderNameMismatchRejected) {
        throw new RuntimeException('Expected ZIP local header name mismatches to be rejected before media import');
    }

    if (!$localEntrySlackRejected) {
        throw new RuntimeException('Expected hidden ZIP local entry bytes to be rejected before media import');
    }

    if (!$executablePermissionRejected) {
        throw new RuntimeException('Expected Unix executable ZIP media entries to be rejected before media import');
    }

    if (!$missingTarEndMarkerRejected) {
        throw new RuntimeException('Expected TAR packets without two zero end blocks to be rejected before import');
    }

    if (!$danglingPaxMetadataRejected) {
        throw new RuntimeException('Expected dangling TAR PAX metadata review packets to be rejected before import');
    }

    if (!$tarDriveLetterRejected) {
        throw new RuntimeException('Expected TAR drive-letter review packet paths to be rejected before import');
    }

    if (!$tarSparseRejected) {
        throw new RuntimeException('Expected TAR sparse review packets to be rejected before import');
    }

    if (!$tarUstarVersionRejected) {
        throw new RuntimeException('Expected TAR packets with unsupported ustar version bytes to be rejected before import');
    }

    if (!$tarGlobalPaxPerEntryRejected) {
        throw new RuntimeException('Expected TAR global PAX per-entry metadata to be rejected before import');
    }

    if (!$tarPaxLinkpathRejected) {
        throw new RuntimeException('Expected TAR PAX linkpath metadata to be rejected before import');
    }

    if (!$tarGnuLongLinkRejected) {
        throw new RuntimeException('Expected TAR GNU long-link metadata to be rejected before import');
    }

    if (!$tarPaxUtf8PathRejected) {
        throw new RuntimeException('Expected TAR PAX paths with invalid UTF-8 to be rejected before import');
    }

    if (!$tarUstarPathUtf8Rejected) {
        throw new RuntimeException('Expected TAR ustar paths with invalid UTF-8 to be rejected before import');
    }

    if (!$tarGnuLongNameUtf8Rejected) {
        throw new RuntimeException('Expected TAR GNU long names with invalid UTF-8 to be rejected before import');
    }

    if (!$tarOwnerUtf8Rejected) {
        throw new RuntimeException('Expected TAR owner metadata with invalid UTF-8 to be rejected before import');
    }

    if (!$tarPaxReviewMetadataUtf8Rejected) {
        throw new RuntimeException('Expected TAR PAX review metadata with invalid UTF-8 to be rejected before import');
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
echo 'packageCommentEncoding=' . $packageCommentPreflight['packageCommentEncoding'] . "\n";
echo 'packageCommentedEntries=' . implode(',', array_map(static fn (array $entry): string => $entry['name'], $packageCommentPreflight['commentedEntries'])) . "\n";
echo 'zipCommentPolicy=' . ($packageCommentPolicyRejected ? 'rejected' : 'not-rejected') . "\n";
echo 'localOrder=' . implode(',', $package->localNames()) . "\n";
foreach ($package->entries() as $entry) {
    $modifiedAt = $entry->lastModifiedTimestamp();
    echo '- ' . $entry->name
        . ' method=' . $entry->compressionMethod
        . ' crc32=' . $entry->crc32Hex()
        . ' versionNeeded=' . $entry->neededToExtractVersion()
        . ' modifiedAt=' . ($modifiedAt === null ? 'none' : (string) $modifiedAt)
        . ' externalAttributes=' . sprintf('0x%08x', $entry->externalFileAttributes)
        . ' extraFields=' . count($entry->centralExtraFields())
        . ' localExtraFields=' . count($package->localExtraFields($entry->name))
        . "\n";
}
echo 'document.xml=' . $package->read('/word/document.xml') . "\n";
echo 'document.xml.reviewExtra=' . ($package->entry('/word/document.xml')->centralExtraField(0xcafe) ?? 'none') . "\n";
echo 'document.xml.localReviewExtra=' . ($package->localExtraField('/word/document.xml', 0xcafe) ?? 'none') . "\n";
echo 'packageSize.uncompressedBytes=' . $packageSizePreflight['uncompressedBytes'] . "\n";
echo 'packageSize.compressedBytes=' . $packageSizePreflight['compressedBytes'] . "\n";
echo 'packageSize.expansionRatio=' . ($packageSizePreflight['expansionRatio'] === null ? 'unknown' : (string) $packageSizePreflight['expansionRatio']) . "\n";
echo 'packageSize.largestEntry=' . ($packageSizePreflight['largestEntry']['name'] ?? 'none') . "\n";
echo 'packageCompression.supportedEntryCount=' . $packageCompressionPreflight['supportedEntryCount'] . "\n";
echo 'packageCompression.unsupportedMethodCount=' . $packageCompressionPreflight['unsupportedCompressionMethodCount'] . "\n";
echo 'packagePermissions.unixModeEntryCount=' . $packagePermissionPreflight['unixModeEntryCount'] . "\n";
echo 'packagePermissions.executableFileCount=' . $packagePermissionPreflight['executableFileCount'] . "\n";
echo 'packageCreatorHosts=' . implode(',', array_map(static fn (array $host): string => $host['name'], $packageCreatorHostPreflight['hostSystems'])) . "\n";
echo 'packageCreatorUnknownEntries=' . $packageCreatorHostPreflight['unknownHostSystemEntryCount'] . "\n";
echo 'packageExtraFields.duplicateEntryCount=' . $packageExtraFieldPreflight['duplicateExtraFieldEntryCount'] . "\n";
echo 'packageArchive.eocdOffset=' . $packageArchivePreflight['eocdOffset'] . "\n";
echo 'packageArchive.totalEntryCount=' . $packageArchivePreflight['totalEntryCount'] . "\n";
echo 'packageArchive.centralDirectorySize=' . $packageArchivePreflight['centralDirectorySize'] . "\n";
echo 'packageArchive.singleDisk=' . ($packageArchivePreflight['isSingleDisk'] ? 'true' : 'false') . "\n";
echo 'packageArchive.layoutSupported=' . ($packageArchivePreflight['isArchiveLayoutSupported'] ? 'true' : 'false') . "\n";
echo 'zipSplitArchivePolicy=' . ($splitZipArchiveRejected ? 'rejected' : 'not-rejected') . "\n";
echo 'zipSplitArchiveSingleDisk=' . ($splitZipArchivePreflight['isSingleDisk'] ? 'true' : 'false') . "\n";
echo 'zip64EocdPolicy=' . ($zip64EocdRejected ? 'rejected' : 'not-rejected') . "\n";
echo 'zip64EocdRequiresZip64=' . ($zip64EocdPreflight['requiresZip64'] ? 'true' : 'false') . "\n";
echo 'zip64LocatorPolicy=' . ($zip64LocatorRejected ? 'rejected' : 'not-rejected') . "\n";
echo 'zip64LocatorDetected=' . ($zip64LocatorPreflight['hasZip64EndOfCentralDirectoryLocator'] ? 'true' : 'false') . "\n";
echo 'zip64LocatorRecordSize=' . ($zip64LocatorPreflight['zip64EndOfCentralDirectorySize'] ?? 'none') . "\n";
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
echo 'rawUnicodePathPolicy=' . ($rawUnicodeTraversalRejected ? 'rejected' : 'not-rejected') . "\n";
echo 'directoryPayloadPolicy=' . ($directoryPayloadRejected ? 'rejected' : 'not-rejected') . "\n";
echo 'zipDosDirectoryAttributePolicy=' . ($dosDirectoryAttributeMismatchRejected ? 'rejected' : 'not-rejected') . "\n";
echo 'zipLocalEntryOverlapPolicy=' . ($localEntryOverlapRejected ? 'rejected' : 'not-rejected') . "\n";
echo 'zipDuplicateLocalOffsetPolicy=' . ($duplicateLocalOffsetRejected ? 'rejected' : 'not-rejected') . "\n";
echo 'zipCentralDirectorySignaturePolicy=' . ($centralDirectorySignatureParsed ? 'inspectable' : 'not-inspectable') . "\n";
echo 'zipCentralDirectorySignatureLength=' . $centralDirectorySignaturePreflight['signatureLength'] . "\n";
echo 'zipCentralDirectorySignatureVerification=' . $centralDirectorySignaturePreflight['cryptographicVerification'] . "\n";
echo 'strongEncryptionPolicy=' . ($strongEncryptionRejected ? 'rejected' : 'not-rejected') . "\n";
echo 'centralDirectoryEncryptionPolicy=' . ($centralDirectoryEncryptionRejected ? 'rejected' : 'not-rejected') . "\n";
echo 'compressedPatchedDataPolicy=' . ($compressedPatchedDataRejected ? 'rejected' : 'not-rejected') . "\n";
echo 'zipUnsupportedCompressionMethodPolicy=' . ($unsupportedCompressionMethodRejected ? 'rejected' : 'not-rejected') . "\n";
echo 'zipUnsupportedCompressionMethodEntry=' . ($unsupportedCompressionMethodPreflight['unsupportedEntries'][0]['name'] ?? 'none') . "\n";
echo 'zipUnknownCreatorHostPolicy=' . ($unknownCreatorHostRejected ? 'rejected' : 'not-rejected') . "\n";
echo 'zipUnknownCreatorHostEntry=' . ($unknownCreatorHostPreflight['unknownEntries'][0]['name'] ?? 'none') . "\n";
echo 'zipDuplicateExtraFieldPolicy=' . ($duplicateExtraFieldRejected ? 'rejected' : 'not-rejected') . "\n";
echo 'zipDuplicateExtraFieldEntry=' . ($duplicateExtraFieldPreflight['duplicateEntries'][0]['name'] ?? 'none') . "\n";
echo 'zipDeflateOptionFlagPolicy=' . ($deflateOptionFlagsRejected ? 'rejected' : 'not-rejected') . "\n";
echo 'zipStoredSizeMismatchPolicy=' . ($storedSizeMismatchRejected ? 'rejected' : 'not-rejected') . "\n";
echo 'zipVersionNeededMismatchPolicy=' . ($versionNeededMismatchRejected ? 'rejected' : 'not-rejected') . "\n";
echo 'zipUnsupportedVersionNeededPolicy=' . ($unsupportedVersionNeededRejected ? 'rejected' : 'not-rejected') . "\n";
echo 'zipLocalHeaderNameMismatchPolicy=' . ($localHeaderNameMismatchRejected ? 'rejected' : 'not-rejected') . "\n";
echo 'zipLocalEntrySlackPolicy=' . ($localEntrySlackRejected ? 'rejected' : 'not-rejected') . "\n";
echo 'zipExecutablePermissionPolicy=' . ($executablePermissionRejected ? 'rejected' : 'not-rejected') . "\n";
echo 'boundedReadPolicy=' . ($oversizedMediaRejected ? 'rejected' : 'not-rejected') . "\n";
echo 'tarEndMarkerPolicy=' . ($missingTarEndMarkerRejected ? 'rejected' : 'not-rejected') . "\n";
echo 'tarDanglingPaxPolicy=' . ($danglingPaxMetadataRejected ? 'rejected' : 'not-rejected') . "\n";
echo 'tarDriveLetterPolicy=' . ($tarDriveLetterRejected ? 'rejected' : 'not-rejected') . "\n";
echo 'tarSparsePolicy=' . ($tarSparseRejected ? 'rejected' : 'not-rejected') . "\n";
echo 'tarUstarVersionPolicy=' . ($tarUstarVersionRejected ? 'rejected' : 'not-rejected') . "\n";
echo 'tarGlobalPaxPerEntryPolicy=' . ($tarGlobalPaxPerEntryRejected ? 'rejected' : 'not-rejected') . "\n";
echo 'tarPaxLinkpathPolicy=' . ($tarPaxLinkpathRejected ? 'rejected' : 'not-rejected') . "\n";
echo 'tarGnuLongLinkPolicy=' . ($tarGnuLongLinkRejected ? 'rejected' : 'not-rejected') . "\n";
echo 'tarPaxUtf8PathPolicy=' . ($tarPaxUtf8PathRejected ? 'rejected' : 'not-rejected') . "\n";
echo 'tarUstarPathUtf8Policy=' . ($tarUstarPathUtf8Rejected ? 'rejected' : 'not-rejected') . "\n";
echo 'tarGnuLongNameUtf8Policy=' . ($tarGnuLongNameUtf8Rejected ? 'rejected' : 'not-rejected') . "\n";
echo 'tarOwnerUtf8Policy=' . ($tarOwnerUtf8Rejected ? 'rejected' : 'not-rejected') . "\n";
echo 'tarPaxReviewMetadataUtf8Policy=' . ($tarPaxReviewMetadataUtf8Rejected ? 'rejected' : 'not-rejected') . "\n";
$unicodePathEntry = $unicodePathPackage->entry('/' . $unicodePathName);
echo 'unicodePath.name=' . $unicodePathEntry->name . "\n";
echo 'unicodePath.rawName=' . $unicodePathEntry->rawName . "\n";
echo 'unicodePath.encoding=' . $unicodePathEntry->nameEncoding . "\n";
echo 'unicodePath.comment=' . $unicodePathEntry->comment . "\n";
echo 'gzip.filename=' . $compressedPackageMembers[0]['filename'] . "\n";
echo 'gzip.comment=' . $compressedPackageMembers[0]['comment'] . "\n";
echo 'gzip.latin1FilenameText=' . $latin1GzipTarMembers[0]['filenameText'] . "\n";
echo 'gzip.latin1CommentText=' . $latin1GzipTarMembers[0]['commentText'] . "\n";
echo 'gzip.extraSubfields=' . implode(',', array_map(static fn (array $field): string => $field['identifier'], $compressedPackageMembers[0]['extraFields'])) . "\n";
echo 'gzip.compressedSize=' . $compressedPackageMembers[0]['compressedSize'] . "\n";
echo 'tar.entries=' . implode(',', $tarPacketRoundTrip->names()) . "\n";
echo 'tar.document.xml=' . $tarPacketRoundTrip->read('/packet/word/document.xml') . "\n";
echo 'tar.globalPaxComment=' . ($tarPacketRoundTrip->globalPaxHeaders()['comment'] ?? 'none') . "\n";
echo 'tar.detectedFormat=' . $streamDetectedTarFormat . "\n";
echo 'tar.splitGzipMembers=' . $splitGzipTarInspection['stream']['memberCount'] . "\n";
echo 'tar.splitGzipMemberFiles=' . implode(',', array_map(static fn (array $member): ?string => $member['filename'], $splitGzipTarInspection['stream']['members'])) . "\n";
echo 'tar.splitGzipMemberXfl=' . implode(',', array_map(static fn (array $member): int => $member['extraFlags'], $splitGzipTarInspection['stream']['members'])) . "\n";
echo 'tar.splitGzipMemberOs=' . implode(',', array_map(static fn (array $member): int => $member['operatingSystem'], $splitGzipTarInspection['stream']['members'])) . "\n";
echo 'tar.splitGzipMemberExtraFields=' . implode(',', array_map(static fn (array $member): ?string => $member['extraFields'][0]['identifier'] ?? null, $splitGzipTarInspection['stream']['members'])) . "\n";
echo 'tar.splitLz4Frames=' . $splitLz4TarInspection['stream']['frameCount'] . "\n";
echo 'tar.splitLz4DataFrames=' . $splitLz4TarInspection['stream']['dataFrameCount'] . "\n";
echo 'tar.completeConcatenationPolicy=' . ($separateCompleteGzipTarRejected ? 'rejected' : 'not-rejected') . "\n";
echo 'tar.gnuLongName=' . implode(',', $gnuLongNamePacket->names()) . "\n";
echo 'tar.paxDocument=' . $paxMetadataPacket->read('/' . $paxDocumentName) . "\n";
echo 'tar.paxOwner=' . $paxMetadataPacket->entry('/' . $paxDocumentName)->userName . ':' . $paxMetadataPacket->entry('/' . $paxDocumentName)->groupName . "\n";
echo 'tar.base256Owner=' . $base256NumericPacket->entry('/packet/base256/document.xml')->uid . ':' . $base256NumericPacket->entry('/packet/base256/document.xml')->gid . "\n";
echo 'tar.base256ModifiedAt=' . $base256NumericPacket->entry('/packet/base256/document.xml')->modifiedAt . "\n";
echo 'deflate.format=' . $deflateReviewMetadata['format'] . "\n";
echo 'deflate.windowSize=' . $deflateReviewMetadata['windowSize'] . "\n";
echo 'deflate.levelHint=' . $deflateReviewMetadata['compressionLevelHint'] . "\n";
echo 'deflate.compressedPayloadSize=' . $deflateTarInspection['stream']['compressedPayloadSize'] . "\n";
echo 'deflate.uncompressedSize=' . $deflateTarInspection['stream']['uncompressedSize'] . "\n";
echo 'deflate.document.xml=' . $deflateTarPacketRoundTrip->read('/packet/word/document.xml') . "\n";
echo 'deflate.rawCompressedPayloadSize=' . $rawDeflateTarInspection['stream']['compressedPayloadSize'] . "\n";
echo 'deflate.rawUncompressedSize=' . $rawDeflateTarInspection['stream']['uncompressedSize'] . "\n";
echo 'deflate.rawManifest=' . $rawDeflateTarPacketRoundTrip->read('/packet/manifest.json') . "\n";
echo 'deflate.trailingBytesPolicy=' . ($deflateTrailingBytesRejected && $rawDeflateTrailingBytesRejected ? 'rejected' : 'not-rejected') . "\n";
echo 'lz4.frames=' . count($lz4ReviewFrames) . "\n";
echo 'lz4.skippable=' . $lz4ReviewFrames[0]['data'] . "\n";
echo 'lz4.blockTypes=' . implode(',', $lz4ReviewFrames[1]['blockTypes']) . "\n";
echo 'lz4.document.xml=' . $lz4TarPacketRoundTrip->read('/packet/word/document.xml') . "\n";
echo 'lz4.dependentBlockIndependent=' . (($dependentLz4ReviewFrames[0]['blockIndependent'] ?? true) ? 'true' : 'false') . "\n";
echo 'lz4.dependentIndex=' . $dependentLz4ReviewIndexText . "\n";
echo 'lz4.dependentBuildBlockTypes=' . implode(',', $dependentLz4BuiltFrames[0]['blockTypes']) . "\n";
echo 'lz4.dependentBuildDecodedBytes=' . strlen($dependentLz4BuiltPayloadRoundTrip) . "\n";
