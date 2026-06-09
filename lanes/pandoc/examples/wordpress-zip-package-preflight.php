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
$packZipVariableUnsignedInteger = static function (int $value): string {
    if ($value < 0) {
        throw new RuntimeException('ZIP owner fixture values must be non-negative');
    }

    $bytes = '';
    do {
        $bytes .= chr($value & 0xff);
        $value = intdiv($value, 256);
    } while ($value > 0);

    return $bytes;
};
$buildUnixOwnerExtra = static function (int $uid, int $gid) use ($packZipVariableUnsignedInteger): string {
    $uidBytes = $packZipVariableUnsignedInteger($uid);
    $gidBytes = $packZipVariableUnsignedInteger($gid);
    $payload = chr(1)
        . chr(strlen($uidBytes))
        . $uidBytes
        . chr(strlen($gidBytes))
        . $gidBytes;

    return pack('vv', 0x7875, strlen($payload)) . $payload;
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
$buildDescriptorSlackBackedPackage = static function () use ($crc32): string {
    $commentsName = 'word/comments.xml';
    $commentsData = '<w:comments><w:comment>Descriptor slack should stay review-only</w:comment></w:comments>';
    $commentsCompressed = gzdeflate($commentsData);
    $commentsCrc = $crc32($commentsData);
    $commentsFlags = 0x0808;
    $descriptorSlack = 'hidden-descriptor-tail';
    $documentName = 'word/document.xml';
    $documentData = '<w:document><w:p>Descriptor follower</w:p></w:document>';
    $documentCrc = $crc32($documentData);
    $commentsOffset = 0;

    $body = pack(
        'VvvvvvVVVvv',
        0x04034b50,
        20,
        $commentsFlags,
        8,
        0,
        0,
        0,
        0,
        0,
        strlen($commentsName),
        0
    );
    $body .= $commentsName
        . $commentsCompressed
        . "PK\x07\x08"
        . pack('VVV', $commentsCrc, strlen($commentsCompressed), strlen($commentsData))
        . $descriptorSlack;
    $documentOffset = strlen($body);
    $body .= pack(
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

    $central = pack(
        'VvvvvvvVVVvvvvvVV',
        0x02014b50,
        0x0314,
        20,
        $commentsFlags,
        8,
        0,
        0,
        $commentsCrc,
        strlen($commentsCompressed),
        strlen($commentsData),
        strlen($commentsName),
        0,
        0,
        0,
        0,
        0,
        $commentsOffset
    );
    $central .= $commentsName;
    $central .= pack(
        'VvvvvvvVVVvvvvvVV',
        0x02014b50,
        0x0314,
        20,
        0x0800,
        0,
        0,
        0,
        $documentCrc,
        strlen($documentData),
        strlen($documentData),
        strlen($documentName),
        0,
        0,
        0,
        0,
        0,
        $documentOffset
    );
    $central .= $documentName;

    return $body
        . $central
        . pack('VvvvvVVv', 0x06054b50, 0, 0, 2, 2, strlen($central), strlen($body), 0);
};
$buildStoredFirstDescriptorBackedPackage = static function (string $contents) use ($crc32): string {
    $name = 'mimetype';
    $crc = $crc32($contents);
    $flags = 0x0808;

    $body = pack(
        'VvvvvvVVVvv',
        0x04034b50,
        20,
        $flags,
        0,
        0,
        0,
        0,
        0,
        0,
        strlen($name),
        0
    );
    $body .= $name . $contents . "PK\x07\x08" . pack('VVV', $crc, strlen($contents), strlen($contents));

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
        strlen($contents),
        strlen($contents),
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
$buildDescriptorPlaceholderMismatchBackedPackage = static function () use ($crc32): string {
    $name = 'word/comments.xml';
    $data = '<w:comments><w:comment>Descriptor local header placeholders should stay blocked</w:comment></w:comments>';
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
        $crc,
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
$buildZip64DataDescriptorBackedPackage = static function () use ($crc32): string {
    $name = 'word/comments.xml';
    $data = '<w:comments><w:comment>ZIP64 descriptor metadata should stay blocked</w:comment></w:comments>';
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
    $body .= $name . $compressed . "PK\x07\x08" . pack('VVVVV', $crc, strlen($compressed), 0, strlen($data), 0);

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

$buildNtfsReservedBackedPackage = static function () use ($crc32): string {
    $name = 'word/media/nonzero-ntfs-reserved.bin';
    $data = "NTFS reserved bytes must stay zero before media import\n";
    $centralExtra = pack('vvV', 0x000a, 4, 1);
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

$buildInvalidDosTimestampBackedPackage = static function () use ($crc32): string {
    $name = 'word/media/bad-date.txt';
    $data = "invalid DOS timestamp metadata should be reviewed before media import\n";
    $crc = $crc32($data);
    $modifiedTime = 0;
    $modifiedDate = 0x0020;

    $body = pack(
        'VvvvvvVVVvv',
        0x04034b50,
        20,
        0x0800,
        0,
        $modifiedTime,
        $modifiedDate,
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
        $modifiedTime,
        $modifiedDate,
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
$buildCentralUnicodePathMissingLocalBackedPackage = static function () use (
    $crc32,
    $buildUnicodeExtra,
    $unicodePathName,
    $unicodePathRawName
): string {
    $data = "Central-only Unicode path metadata should stay blocked\n";
    $crc = $crc32($data);
    $unicodePathExtra = $buildUnicodeExtra(0x7075, $unicodePathRawName, $unicodePathName);

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
        0
    );
    $body .= $unicodePathRawName . $data;

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
        strlen($unicodePathExtra),
        0,
        0,
        0,
        0x81a40000,
        0
    );
    $central .= $unicodePathRawName . $unicodePathExtra;

    return $body
        . $central
        . pack('VvvvvVVv', 0x06054b50, 0, 0, 1, 1, strlen($central), strlen($body), 0);
};
$buildUtf8UnicodePathMismatchBackedPackage = static function () use ($crc32, $buildUnicodeExtra): string {
    $name = 'word/media/review.png';
    $unicodeName = "word/media/review-\u{2603}.png";
    $data = "Conflicting UTF-8 path metadata should stay blocked\n";
    $crc = $crc32($data);
    $unicodePathExtra = $buildUnicodeExtra(0x7075, $name, $unicodeName);

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
        strlen($unicodePathExtra),
        0,
        0,
        0,
        0x81a40000,
        0
    );
    $central .= $name . $unicodePathExtra;

    return $body
        . $central
        . pack('VvvvvVVv', 0x06054b50, 0, 0, 1, 1, strlen($central), strlen($body), 0);
};
$buildUtf8UnicodeCommentMismatchBackedPackage = static function () use ($crc32, $buildUnicodeExtra): string {
    $name = 'word/media/review-comment.png';
    $rawComment = 'review comment';
    $unicodeComment = "review \u{2603} comment";
    $data = "Conflicting UTF-8 comment metadata should stay blocked\n";
    $crc = $crc32($data);
    $unicodeCommentExtra = $buildUnicodeExtra(0x6375, $rawComment, $unicodeComment);

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
        strlen($unicodeCommentExtra),
        strlen($rawComment),
        0,
        0,
        0x81a40000,
        0
    );
    $central .= $name . $unicodeCommentExtra . $rawComment;

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
$buildZip64SizeUpgradeBackedPackage = static function () use ($crc32, $packUInt64): string {
    $name = 'word/media/oversized-review.bin';
    $data = "ZIP64 size upgrade metadata should stay blocked but explainable\n";
    $compressed = gzdeflate($data);
    $crc = $crc32($data);
    $zip64Values = $packUInt64(strlen($data))
        . $packUInt64(strlen($compressed))
        . $packUInt64(0)
        . pack('V', 0);
    $zip64Extra = pack('vv', 0x0001, strlen($zip64Values)) . $zip64Values;

    $body = pack(
        'VvvvvvVVVvv',
        0x04034b50,
        20,
        0x0800,
        8,
        0,
        0,
        $crc,
        strlen($compressed),
        strlen($data),
        strlen($name),
        0
    );
    $body .= $name . $compressed;

    $central = pack(
        'VvvvvvvVVVvvvvvVV',
        0x02014b50,
        0x0314,
        20,
        0x0800,
        8,
        0,
        0,
        $crc,
        0xffffffff,
        0xffffffff,
        strlen($name),
        strlen($zip64Extra),
        0,
        0xffff,
        0,
        0x81a40000,
        0xffffffff
    );
    $central .= $name . $zip64Extra;

    return $body
        . $central
        . pack('VvvvvVVv', 0x06054b50, 0, 0, 1, 1, strlen($central), strlen($body), 0);
};
$buildZip64LocalHeaderMismatchBackedPackage = static function () use ($crc32, $packUInt64): string {
    $centralName = 'word/document.xml';
    $localName = 'word/media/spoofed-local-header.bin';
    $data = "ZIP64 offset should not hide a spoofed local header name\n";
    $crc = $crc32($data);
    $zip64OffsetValue = $packUInt64(0);
    $zip64Extra = pack('vv', 0x0001, strlen($zip64OffsetValue)) . $zip64OffsetValue;

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
        strlen($zip64Extra),
        0,
        0,
        0,
        0x81a40000,
        0xffffffff
    );
    $central .= $centralName . $zip64Extra;

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
$buildControlNameBackedPackage = static function (string $rawName, ?string $unicodeName = null) use ($crc32, $buildUnicodeExtra): string {
    $data = "Control-byte media path should stay blocked\n";
    $crc = $crc32($data);
    $extra = $unicodeName === null ? '' : $buildUnicodeExtra(0x7075, $rawName, $unicodeName);
    $flags = $unicodeName === null ? 0x0800 : 0;

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
        strlen($rawName),
        strlen($extra)
    );
    $body .= $rawName . $extra . $data;

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
        strlen($rawName),
        strlen($extra),
        0,
        0,
        0,
        0x81a40000,
        0
    );
    $central .= $rawName . $extra;

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
$buildDirectoryCrcBackedPackage = static function (): string {
    $name = 'word/media/';
    $crc = 0x7b;

    $body = pack(
        'VvvvvvVVVvv',
        0x04034b50,
        20,
        0x0800,
        0,
        0,
        0,
        $crc,
        0,
        0,
        strlen($name),
        0
    );
    $body .= $name;

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
        0,
        0,
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
$buildDuplicateCentralDirectoryNameBackedPackage = static function () use ($crc32): string {
    $name = 'word/media/review.txt';
    $parts = [
        "first duplicate package part bytes\n",
        "second duplicate package part bytes\n",
    ];
    $body = '';
    $central = '';

    foreach ($parts as $data) {
        $crc = $crc32($data);
        $offset = strlen($body);
        $body .= pack(
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
            $offset
        );
        $central .= $name;
    }

    return $body
        . $central
        . pack('VvvvvVVv', 0x06054b50, 0, 0, 2, 2, strlen($central), strlen($body), 0);
};
$buildPrefixedZipBackedPackage = static function () use ($crc32): string {
    $prefix = "MZhidden-review-stub\n";
    $name = 'word/document.xml';
    $data = '<w:document><w:p>Prefixed package should stay blocked</w:p></w:document>';
    $crc = $crc32($data);
    $localHeaderOffset = strlen($prefix);

    $body = $prefix . pack(
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
        $localHeaderOffset
    );
    $central .= $name;

    return $body
        . $central
        . pack('VvvvvVVv', 0x06054b50, 0, 0, 1, 1, strlen($central), strlen($body), 0);
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
$buildTraditionalEncryptedBackedPackage = static function (
    string $encryptedHeader = 'PKWAREHEAD12',
    string $ciphertext = "encrypted media payload bytes\n"
) use ($crc32): string {
    $name = 'word/media/traditional-encrypted.bin';
    $plaintext = "WordPress import media before traditional ZIP encryption\n";
    $encryptedPayload = $encryptedHeader . $ciphertext;
    $crc = $crc32($plaintext);
    $flags = 0x0801;

    $body = pack(
        'VvvvvvVVVvv',
        0x04034b50,
        20,
        $flags,
        0,
        0,
        0,
        $crc,
        strlen($encryptedPayload),
        strlen($plaintext),
        strlen($name),
        0
    );
    $body .= $name . $encryptedPayload;

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
        strlen($encryptedPayload),
        strlen($plaintext),
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
$buildUnderstatedVersionNeededBackedPackage = static function () use ($crc32): string {
    $name = 'word/document.xml';
    $data = '<w:document><w:body><w:p>Deflated package part with understated version metadata</w:p></w:body></w:document>';
    $compressed = gzdeflate($data);
    $crc = $crc32($data);

    $body = pack(
        'VvvvvvVVVvv',
        0x04034b50,
        10,
        0x0800,
        8,
        0,
        0,
        $crc,
        strlen($compressed),
        strlen($data),
        strlen($name),
        0
    );
    $body .= $name . $compressed;

    $central = pack(
        'VvvvvvvVVVvvvvvVV',
        0x02014b50,
        0x0314,
        10,
        0x0800,
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
$buildUnknownCreatorHostBackedPackage = static function (int $flags = 0x0800) use ($crc32): string {
    $name = 'word/media/unknown-host-review.bin';
    $data = "Unknown ZIP creator host metadata should stay reviewable\n";
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
        0x3f14,
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
$buildStrictGeneralPurposeFlagReviewBackedPackage = static function () use ($crc32): string {
    $name = 'word/document.xml';
    $data = '<w:document><w:body><w:p>Flagged descriptor review</w:p></w:body></w:document>';
    $compressed = gzdeflate($data);
    $crc = $crc32($data);
    $flags = 0x080e;

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
    $body .= $name . $compressed . pack('VVVV', 0x08074b50, $crc, strlen($compressed), strlen($data));

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
        0x81a40000,
        0
    );
    $central .= $name;

    return $body
        . $central
        . pack('VvvvvVVv', 0x06054b50, 0, 0, 1, 1, strlen($central), strlen($body), 0);
};
$buildExtraFieldIdMismatchBackedPackage = static function () use ($crc32): string {
    $name = 'word/media/split-extra-review.bin';
    $data = "Central and local ZIP extra-field ids should stay reviewable\n";
    $crc = $crc32($data);
    $centralExtra = pack('vva*', 0xcafe, strlen('central-review'), 'central-review');
    $localExtra = pack('vva*', 0xbeef, strlen('local-review'), 'local-review');

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
$buildMalformedExtraFieldStructureBackedPackage = static function () use ($crc32): string {
    $entries = [
        [
            'name' => 'word/document.xml',
            'data' => '<w:document><w:body><w:p>Malformed central extra field</w:p></w:body></w:document>',
            'localExtra' => '',
            'centralExtra' => pack('vv', 0xcafe, 4) . 'A',
        ],
        [
            'name' => 'word/media/local-extra.bin',
            'data' => "Malformed local extra field bytes\n",
            'localExtra' => "\xbe\xef",
            'centralExtra' => '',
        ],
    ];
    $body = '';
    $centralRecords = [];

    foreach ($entries as $entry) {
        $name = $entry['name'];
        $data = $entry['data'];
        $localExtra = $entry['localExtra'];
        $centralExtra = $entry['centralExtra'];
        $crc = $crc32($data);
        $localHeaderOffset = strlen($body);

        $body .= pack(
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

        $centralRecord = pack(
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
            $localHeaderOffset
        );
        $centralRecords[] = $centralRecord . $name . $centralExtra;
    }

    $central = implode('', $centralRecords);

    return $body
        . $central
        . pack('VvvvvVVv', 0x06054b50, 0, 0, count($entries), count($entries), strlen($central), strlen($body), 0);
};
$buildDuplicateExtraFieldBackedPackage = static function () use ($crc32): string {
    $name = 'word/media/duplicate-extra-review.bin';
    $data = "Duplicate ZIP extra field metadata should stay blocked for strict media import\n";
    $crc = $crc32($data);
    $centralExtra = pack('vva*', 0xcafe, strlen('first-review'), 'first-review')
        . pack('vva*', 0xcafe, strlen('second-review'), 'second-review');
    $localExtra = $centralExtra;

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
$buildRawExtraFieldPolicyBackedPackage = static function () use ($crc32): string {
    $duplicateExtra = pack('vva*', 0xcafe, strlen('first-review'), 'first-review')
        . pack('vva*', 0xcafe, strlen('second-review'), 'second-review');
    $entries = [
        [
            'name' => 'word/media/raw-duplicate-extra-review.bin',
            'data' => "Raw duplicate ZIP extra field metadata should stay visible before instantiation\n",
            'centralExtra' => $duplicateExtra,
            'localExtra' => $duplicateExtra,
        ],
        [
            'name' => 'word/media/raw-split-extra-review.bin',
            'data' => "Raw central/local ZIP extra-field ids should stay visible before instantiation\n",
            'centralExtra' => pack('vva*', 0xbeef, strlen('central-only'), 'central-only'),
            'localExtra' => pack('vva*', 0xfeed, strlen('local-only'), 'local-only'),
        ],
        [
            'name' => 'word/media/raw-value-extra-review.bin',
            'data' => "Raw central/local ZIP extra-field values should stay visible before instantiation\n",
            'centralExtra' => pack('vva*', 0xf00d, strlen('central'), 'central'),
            'localExtra' => pack('vva*', 0xf00d, strlen('local'), 'local'),
        ],
    ];
    $body = '';
    $central = '';

    foreach ($entries as $entry) {
        $name = $entry['name'];
        $data = $entry['data'];
        $crc = $crc32($data);
        $localExtra = $entry['localExtra'];
        $centralExtra = $entry['centralExtra'];
        $localHeaderOffset = strlen($body);

        $body .= pack(
            'VvvvvvVVVvv',
            0x04034b50,
            20,
            0x0840,
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

        $central .= pack(
            'VvvvvvvVVVvvvvvVV',
            0x02014b50,
            0x0314,
            20,
            0x0840,
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
            $localHeaderOffset
        );
        $central .= $name . $centralExtra;
    }

    return $body
        . $central
        . pack('VvvvvVVv', 0x06054b50, 0, 0, count($entries), count($entries), strlen($central), strlen($body), 0);
};
$buildRawNameCollisionPolicyBackedPackage = static function () use ($crc32, $buildUnicodeExtra): string {
    $rawName = 'word/media/review-image.bin';
    $firstName = 'word/media/review-one.png';
    $secondName = 'word/media/review-two.png';
    $entries = [
        [
            'name' => 'word/document.xml',
            'data' => '<w:document><w:body><w:p>Raw central-directory collision review</w:p></w:body></w:document>',
            'flags' => 0x0801,
            'localExtra' => '',
            'centralExtra' => '',
        ],
        [
            'name' => 'word/media/Review.PNG',
            'data' => "case collision first media bytes\n",
            'flags' => 0x0801,
            'localExtra' => '',
            'centralExtra' => '',
        ],
        [
            'name' => 'word/media/review.png',
            'data' => "case collision second media bytes\n",
            'flags' => 0x0801,
            'localExtra' => '',
            'centralExtra' => '',
        ],
        [
            'name' => $rawName,
            'localName' => $rawName,
            'data' => "raw collision first media bytes\n",
            'flags' => 0x0001,
            'localExtra' => $buildUnicodeExtra(0x7075, $rawName, $firstName),
            'centralExtra' => $buildUnicodeExtra(0x7075, $rawName, $firstName),
        ],
        [
            'name' => $rawName,
            'localName' => $rawName,
            'data' => "raw collision second media bytes\n",
            'flags' => 0x0001,
            'localExtra' => $buildUnicodeExtra(0x7075, $rawName, $secondName),
            'centralExtra' => $buildUnicodeExtra(0x7075, $rawName, $secondName),
        ],
    ];
    $body = '';
    $central = '';

    foreach ($entries as $entry) {
        $name = $entry['name'];
        $localName = $entry['localName'] ?? $name;
        $data = $entry['data'];
        $flags = $entry['flags'];
        $localExtra = $entry['localExtra'];
        $centralExtra = $entry['centralExtra'];
        $crc = $crc32($data);
        $localHeaderOffset = strlen($body);

        $body .= pack(
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
            strlen($localName),
            strlen($localExtra)
        );
        $body .= $localName . $localExtra . $data;

        $central .= pack(
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
            strlen($centralExtra),
            0,
            0,
            0,
            0x81a40000,
            $localHeaderOffset
        );
        $central .= $name . $centralExtra;
    }

    return $body
        . $central
        . pack('VvvvvVVv', 0x06054b50, 0, 0, count($entries), count($entries), strlen($central), strlen($body), 0);
};
$buildUnixOwnerBackedPackage = static function () use ($crc32, $buildUnixOwnerExtra): string {
    $name = 'word/media/unix-owner-review.txt';
    $data = "Unix UID/GID owner metadata should stay reviewable\n";
    $crc = $crc32($data);
    $extra = $buildUnixOwnerExtra(1001, 1002);

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
$buildExtraFieldValueMismatchBackedPackage = static function () use ($crc32): string {
    $name = 'word/media/split-extra-value-review.bin';
    $data = "Central and local ZIP extra-field values should stay reviewable\n";
    $crc = $crc32($data);
    $centralExtra = pack('vva*', 0xcafe, strlen('central-review'), 'central-review');
    $localExtra = pack('vva*', 0xcafe, strlen('local-review'), 'local-review');

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
$buildUnixFileTypeNameMismatchBackedPackage = static function (string $name, int $externalAttributes) use ($crc32): string {
    $data = str_ends_with($name, '/') ? '' : "Unix file type metadata should match entry name shape\n";
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
        $externalAttributes,
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
$insertZipBeforeEndOfCentralDirectory = static function (string $zip, string $bytes): string {
    $eocdOffset = strrpos($zip, "PK\x05\x06");
    if ($eocdOffset === false) {
        throw new RuntimeException('ZIP end-of-central-directory fixture was not found');
    }

    return substr($zip, 0, $eocdOffset) . $bytes . substr($zip, $eocdOffset);
};
$rewriteFirstCentralLocalHeaderOffset = static function (string $zip, int $localHeaderOffset): string {
    $eocdOffset = strrpos($zip, "PK\x05\x06");
    if ($eocdOffset === false) {
        throw new RuntimeException('ZIP end-of-central-directory fixture was not found');
    }

    $centralDirectoryOffset = unpack('Vvalue', substr($zip, $eocdOffset + 16, 4))['value'];
    if (substr($zip, $centralDirectoryOffset, 4) !== "PK\x01\x02") {
        throw new RuntimeException('ZIP central directory fixture was not found');
    }

    return substr_replace($zip, pack('V', $localHeaderOffset), $centralDirectoryOffset + 42, 4);
};
$buildArchiveExtraDataRecordBackedPackage = static function (string $zip): string {
    $eocdOffset = strrpos($zip, "PK\x05\x06");
    if ($eocdOffset === false) {
        throw new RuntimeException('ZIP end-of-central-directory fixture was not found');
    }

    $archiveExtraData = 'wordpress-archive-extra-data';
    $archiveExtraRecord = "PK\x06\x08" . pack('V', strlen($archiveExtraData)) . $archiveExtraData;

    return substr($zip, 0, $eocdOffset) . $archiveExtraRecord . substr($zip, $eocdOffset);
};
$buildInterEntryArchiveExtraDataRecordBackedPackage = static function (string $zip) use ($rewriteZipEndOfCentralDirectory): string {
    $eocdOffset = strrpos($zip, "PK\x05\x06");
    if ($eocdOffset === false) {
        throw new RuntimeException('ZIP end-of-central-directory fixture was not found');
    }

    $centralDirectorySize = unpack('Vvalue', substr($zip, $eocdOffset + 12, 4))['value'];
    $centralDirectoryOffset = unpack('Vvalue', substr($zip, $eocdOffset + 16, 4))['value'];
    $firstNameLength = unpack('vvalue', substr($zip, $centralDirectoryOffset + 28, 2))['value'];
    $firstExtraLength = unpack('vvalue', substr($zip, $centralDirectoryOffset + 30, 2))['value'];
    $firstCommentLength = unpack('vvalue', substr($zip, $centralDirectoryOffset + 32, 2))['value'];
    $interEntryOffset = $centralDirectoryOffset + 46 + $firstNameLength + $firstExtraLength + $firstCommentLength;

    $archiveExtraData = 'wordpress-inter-entry-archive-extra-data';
    $archiveExtraRecord = "PK\x06\x08" . pack('V', strlen($archiveExtraData)) . $archiveExtraData;
    $zip = substr($zip, 0, $interEntryOffset) . $archiveExtraRecord . substr($zip, $interEntryOffset);

    return $rewriteZipEndOfCentralDirectory($zip, [
        'centralDirectorySize' => $centralDirectorySize + strlen($archiveExtraRecord),
    ]);
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
$rewriteZip64EndOfCentralDirectoryPayloadSize = static function (string $zip, int $payloadSize) use ($packUInt64): string {
    $summary = ZipPackage::endOfCentralDirectoryPreflight($zip);
    $recordOffset = $summary['zip64EndOfCentralDirectoryOffset'];
    if ($recordOffset === null) {
        throw new RuntimeException('ZIP64 EOCD fixture was not found');
    }

    return substr_replace($zip, $packUInt64($payloadSize), $recordOffset + 4, 8);
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
$buildUnsafeLocalHeaderNameBackedPackage = static function () use ($crc32): string {
    $centralName = 'word/media/review.png';
    $localName = 'word/../media/review.png';
    $data = "Unsafe local header media path should stay blocked\n";
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
$buildRawPlatformMetadataLocalHeaderMismatchBackedPackage = static function () use ($crc32): string {
    $entries = [
        [
            'centralName' => 'word/document.xml',
            'localName' => 'word/other.xml',
            'data' => '<w:document><w:body><w:p>Raw sidecar review</w:p></w:body></w:document>',
        ],
        [
            'centralName' => '__MACOSX/word/media/._review.png',
            'data' => "AppleDouble sidecar should not import as document media\n",
        ],
        [
            'centralName' => 'word/media/Thumbs.db',
            'data' => "Windows thumbnail cache should not import as document media\n",
        ],
        [
            'centralName' => 'word/media/review.png',
            'data' => "Visible media that would otherwise import normally\n",
        ],
    ];
    $body = '';
    $central = '';

    foreach ($entries as $entry) {
        $centralName = $entry['centralName'];
        $localName = $entry['localName'] ?? $centralName;
        $data = $entry['data'];
        $crc = $crc32($data);
        $localHeaderOffset = strlen($body);

        $body .= pack(
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
            strlen($centralName),
            0,
            0,
            0,
            0,
            0x81a40000,
            $localHeaderOffset
        );
        $central .= $centralName;
    }

    return $body
        . $central
        . pack('VvvvvVVv', 0x06054b50, 0, 0, count($entries), count($entries), strlen($central), strlen($body), 0);
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
$buildUnclaimedLocalHeaderBackedPackage = static function () use ($crc32): string {
    $documentName = 'word/document.xml';
    $documentData = '<w:document><w:body><w:p>Unclaimed local header bytes should stay blocked</w:p></w:body></w:document>';
    $documentCrc = $crc32($documentData);
    $orphanName = 'word/media/orphan.bin';
    $orphanData = "orphan local media bytes should stay blocked\n";
    $orphanCrc = $crc32($orphanData);

    $orphanLocal = pack(
        'VvvvvvVVVvv',
        0x04034b50,
        20,
        0x0800,
        0,
        0,
        0,
        $orphanCrc,
        strlen($orphanData),
        strlen($orphanData),
        strlen($orphanName),
        0
    );
    $orphanLocal .= $orphanName . $orphanData;

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
    $body .= $documentName . $documentData . $orphanLocal;

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
        strlen($documentData),
        strlen($documentData),
        strlen($documentName),
        0,
        0,
        0,
        0,
        0x81a40000,
        0
    );
    $central .= $documentName;

    return $body
        . $central
        . pack('VvvvvVVv', 0x06054b50, 0, 0, 1, 1, strlen($central), strlen($body), 0);
};
$corruptZipEntryPayload = static function (string $zip, string $entryName): string {
    $cursor = 0;
    $length = strlen($zip);

    while ($cursor + 30 <= $length && substr($zip, $cursor, 4) === "PK\x03\x04") {
        $compressedSize = unpack('Vvalue', substr($zip, $cursor + 18, 4))['value'];
        $nameLength = unpack('vvalue', substr($zip, $cursor + 26, 2))['value'];
        $extraLength = unpack('vvalue', substr($zip, $cursor + 28, 2))['value'];
        $nameStart = $cursor + 30;
        $dataStart = $nameStart + $nameLength + $extraLength;
        $name = substr($zip, $nameStart, $nameLength);

        if ($name === $entryName) {
            if ($compressedSize <= 0) {
                throw new RuntimeException('Cannot corrupt ZIP fixture payload for ' . $entryName);
            }

            $zip[$dataStart] = chr(ord($zip[$dataStart]) ^ 0xff);

            return $zip;
        }

        $cursor = $dataStart + $compressedSize;
    }

    throw new RuntimeException('ZIP fixture entry not found: ' . $entryName);
};
$buildTrailingDeflateBytesBackedPackage = static function () use ($crc32): string {
    $name = 'word/document.xml';
    $data = '<w:document><w:body><w:p>Trailing deflate bytes should stay blocked</w:p></w:body></w:document>';
    $compressed = gzdeflate($data) . 'hidden-deflate-tail';
    $crc = $crc32($data);

    $body = pack(
        'VvvvvvVVVvv',
        0x04034b50,
        20,
        0x0800,
        8,
        0,
        0,
        $crc,
        strlen($compressed),
        strlen($data),
        strlen($name),
        0
    );
    $body .= $name . $compressed;

    $central = pack(
        'VvvvvvvVVVvvvvvVV',
        0x02014b50,
        0x0314,
        20,
        0x0800,
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
        0x81a40000,
        0
    );
    $central .= $name;

    return $body
        . $central
        . pack('VvvvvVVv', 0x06054b50, 0, 0, 1, 1, strlen($central), strlen($body), 0);
};
$buildLocalHeaderOrderReviewBackedPackage = static function () use ($crc32): string {
    $body = '';
    $centralRecordsByName = [];
    $entries = [
        [
            'name' => 'mimetype',
            'data' => 'application/vnd.oasis.opendocument.text',
            'method' => 0,
        ],
        [
            'name' => 'content.xml',
            'data' => '<office:document-content><text:p>review packet</text:p></office:document-content>',
            'method' => 8,
        ],
        [
            'name' => 'styles.xml',
            'data' => '<office:document-styles><style:style/></office:document-styles>',
            'method' => 8,
        ],
    ];

    foreach ($entries as $entry) {
        $name = $entry['name'];
        $data = $entry['data'];
        $method = $entry['method'];
        $compressed = $method === 8 ? gzdeflate($data) : $data;
        $crc = $crc32($data);
        $localHeaderOffset = strlen($body);

        $body .= pack(
            'VvvvvvVVVvv',
            0x04034b50,
            20,
            0x0800,
            $method,
            0,
            0,
            $crc,
            strlen($compressed),
            strlen($data),
            strlen($name),
            0
        );
        $body .= $name . $compressed;

        $central = pack(
            'VvvvvvvVVVvvvvvVV',
            0x02014b50,
            0x0314,
            20,
            0x0800,
            $method,
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
            $localHeaderOffset
        );
        $centralRecordsByName[$name] = $central . $name;
    }

    $central = $centralRecordsByName['content.xml']
        . $centralRecordsByName['styles.xml']
        . $centralRecordsByName['mimetype'];

    return $body
        . $central
        . pack('VvvvvVVv', 0x06054b50, 0, 0, 3, 3, strlen($central), strlen($body), 0);
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
$packageDosAttributePreflight = $package->dosAttributePreflight();
$packageCreatorHostPreflight = $package->creatorHostSystemPreflight();
$generatedCreatorHostPackage = ZipPackage::fromParts([
    [
        'name' => 'word/document.xml',
        'data' => '<w:document><w:p>Generated Windows host package</w:p></w:document>',
        'creatorHostSystem' => 10,
        'externalAttributes' => 0x20,
    ],
    [
        'name' => 'word/media/review.txt',
        'data' => "Generated DOS host media review\n",
        'compressionMethod' => 0,
        'creatorHostSystem' => 0,
        'externalAttributes' => 0x20,
    ],
]);
$generatedCreatorHostPreflight = $generatedCreatorHostPackage->creatorHostSystemPreflight();
$generatedCreatorHostStrictPreflight = $generatedCreatorHostPackage->strictImportPreflight(4096, 100.0, 4096);
$generatedCreatorHostRawPolicy = ZipPackage::creatorHostSystemPolicyPreflight($generatedCreatorHostPackage->bytes());
$generatedUnknownCreatorHostRejected = false;
try {
    ZipPackage::fromParts([
        [
            'name' => 'word/media/generated-unknown-host.bin',
            'data' => 'Generated packages must not emit unknown creator host metadata',
            'creatorHostSystem' => 63,
        ],
    ]);
} catch (RuntimeException $exception) {
    $generatedUnknownCreatorHostRejected = str_contains($exception->getMessage(), 'creator host system 63');
}
$packageCommentPreflight = $package->commentPreflight();
$packageExtraFieldPreflight = $package->extraFieldPreflight();
$packageUnixOwnerPreflight = $package->unixOwnerPreflight();
$packagePathHierarchyPreflight = $package->pathHierarchyPreflight();
$packageCaseInsensitiveNamePreflight = $package->caseInsensitiveNamePreflight();
$packageLocalHeaderPreflight = $package->localHeaderPreflight();
$packageLocalHeaderOrderPreflight = $package->localHeaderOrderPreflight();
$packageReadIntegrityPreflight = $package->readIntegrityPreflight(4096);
$packageModificationTimePreflight = $package->modificationTimePreflight();
$packagePlatformMetadataPreflight = $package->platformMetadataPreflight();
$localHeaderOrderReviewPackage = ZipPackage::fromString($buildLocalHeaderOrderReviewBackedPackage());
$localHeaderOrderReviewPreflight = $localHeaderOrderReviewPackage->localHeaderOrderPreflight();
$localHeaderOrderReviewStrictPreflight = $localHeaderOrderReviewPackage->strictImportPreflight(4096, 100.0, 4096);
$strictImportDocumentXml = '<w:document><w:body><w:p>Strict import source</w:p></w:body></w:document>';
$strictImportPackage = ZipPackage::fromParts([
    [
        'name' => 'word/document.xml',
        'data' => $strictImportDocumentXml,
        'modifiedAt' => $documentModifiedAt,
        'externalAttributes' => 0x81a40000,
    ],
    [
        'name' => 'word/media/',
        'externalAttributes' => 0x41ed0000,
    ],
    [
        'name' => 'word/media/review.txt',
        'data' => "review media bytes\n",
        'compressionMethod' => 0,
        'externalAttributes' => 0x81a40000,
    ],
]);
$strictImportPreflight = $strictImportPackage->strictImportPreflight(4096, 100.0, 4096);
$strictImportEntryHandoffPreflight = $strictImportPackage->entryHandoffPreflight([
    ['name' => '/word/document.xml', 'required' => true, 'kind' => 'file', 'role' => 'main-document'],
    ['name' => 'word/media/review.txt', 'required' => false, 'kind' => 'file', 'role' => 'attachment', 'maxUncompressedBytes' => 64],
    ['name' => 'word/media/', 'required' => false, 'kind' => 'directory', 'role' => 'media-directory'],
    ['name' => 'word/comments.xml', 'required' => false, 'kind' => 'file', 'role' => 'optional-comments'],
], 4096);
$strictImportCentralDirectoryInventory = ZipPackage::centralDirectoryInventoryPreflight($strictImportPackage->bytes());
$centralDirectoryDeclaredLowInventory = ZipPackage::centralDirectoryInventoryPreflight($rewriteZipEndOfCentralDirectory(
    $strictImportPackage->bytes(),
    [
        'diskEntryCount' => 2,
        'totalEntryCount' => 2,
    ]
));
$centralDirectoryDeclaredHighInventory = ZipPackage::centralDirectoryInventoryPreflight($rewriteZipEndOfCentralDirectory(
    $strictImportPackage->bytes(),
    [
        'diskEntryCount' => 4,
        'totalEntryCount' => 4,
    ]
));
$centralDirectoryGapPayload = "hidden central directory gap\n";
$centralDirectoryGapRecord = "PK\x06\x08" . pack('V', strlen($centralDirectoryGapPayload)) . $centralDirectoryGapPayload;
$centralDirectoryGapBytes = $insertZipBeforeEndOfCentralDirectory($strictImportPackage->bytes(), $centralDirectoryGapRecord);
$centralDirectoryGapInventory = ZipPackage::centralDirectoryInventoryPreflight($centralDirectoryGapBytes);
$centralDirectoryGapRawStrictPreflight = ZipPackage::rawStrictImportPreflight(
    $centralDirectoryGapBytes,
    4096,
    100.0,
    4096
);
$centralDirectoryUnderstatedSize = $strictImportCentralDirectoryInventory['entries'][0]['recordEnd']
    - $strictImportCentralDirectoryInventory['centralDirectoryOffset'];
$centralDirectoryUnderstatedBytes = $rewriteZipEndOfCentralDirectory(
    $strictImportPackage->bytes(),
    [
        'centralDirectorySize' => $centralDirectoryUnderstatedSize,
    ]
);
$centralDirectoryUnderstatedInventory = ZipPackage::centralDirectoryInventoryPreflight($centralDirectoryUnderstatedBytes);
$centralDirectoryUnderstatedRepairPlan = ZipPackage::centralDirectoryRepairPlanPreflight($centralDirectoryUnderstatedBytes);
$centralDirectoryUnderstatedRawStrictPreflight = ZipPackage::rawStrictImportPreflight(
    $centralDirectoryUnderstatedBytes,
    4096,
    100.0,
    4096
);
$centralDirectoryTailPayload = "hidden central tail\n";
$centralDirectoryTailRecord = "PK\x06\x08" . pack('V', strlen($centralDirectoryTailPayload)) . $centralDirectoryTailPayload;
$centralDirectoryTailBytes = $insertZipBeforeEndOfCentralDirectory($strictImportPackage->bytes(), $centralDirectoryTailRecord);
$centralDirectoryTailBytes = $rewriteZipEndOfCentralDirectory(
    $centralDirectoryTailBytes,
    [
        'centralDirectorySize' => $strictImportCentralDirectoryInventory['centralDirectorySize'] + strlen($centralDirectoryTailRecord),
    ]
);
$centralDirectoryTailInventory = ZipPackage::centralDirectoryInventoryPreflight($centralDirectoryTailBytes);
$centralDirectoryTailRawStrictPreflight = ZipPackage::rawStrictImportPreflight(
    $centralDirectoryTailBytes,
    4096,
    100.0,
    4096
);
$centralDirectoryDuplicateOffsetBytes = $buildDuplicateLocalOffsetBackedPackage();
$centralDirectoryDuplicateOffsetInventory = ZipPackage::centralDirectoryInventoryPreflight($centralDirectoryDuplicateOffsetBytes);
$centralDirectoryDuplicateOffsetRawStrictPreflight = ZipPackage::rawStrictImportPreflight(
    $centralDirectoryDuplicateOffsetBytes,
    4096,
    100.0,
    4096
);
$centralDirectoryDuplicateOffsetRejected = false;
try {
    ZipPackage::fromString($centralDirectoryDuplicateOffsetBytes);
} catch (RuntimeException $exception) {
    $centralDirectoryDuplicateOffsetRejected = str_contains($exception->getMessage(), 'Duplicate ZIP local header offset');
}
$rawStrictImportPreflight = ZipPackage::rawStrictImportPreflight($strictImportPackage->bytes(), 4096, 100.0, 4096);
$rawStrictTrailingEocdBytes = $strictImportPackage->bytes() . "detached reviewer bytes\n";
$rawStrictTrailingEocdSummary = ZipPackage::endOfCentralDirectoryTrailingBytesPreflight($rawStrictTrailingEocdBytes);
$rawStrictTrailingEocdPreflight = ZipPackage::rawStrictImportPreflight($rawStrictTrailingEocdBytes, 4096, 100.0, 4096);
$prefixedZipBytes = $buildPrefixedZipBackedPackage();
$prefixedZipPrefixPreflight = ZipPackage::packagePrefixPreflight($prefixedZipBytes);
$rawStrictPrefixedZipPreflight = ZipPackage::rawStrictImportPreflight($prefixedZipBytes, 4096, 100.0, 4096);
$prefixedZipRejected = false;
try {
    ZipPackage::fromString($prefixedZipBytes);
} catch (RuntimeException $exception) {
    $prefixedZipRejected = str_contains($exception->getMessage(), 'unexpected bytes before the first local header');
}
$emptyStrictImportPackage = ZipPackage::fromParts([]);
$emptyStrictImportPreflight = $emptyStrictImportPackage->strictImportPreflight(4096, 100.0, 4096);
$emptyRawStrictImportPreflight = ZipPackage::rawStrictImportPreflight($emptyStrictImportPackage->bytes(), 4096, 100.0, 4096);
$emptyStrictImportRejected = false;
try {
    $emptyStrictImportPackage->assertStrictImportable(4096, 100.0, 4096);
} catch (RuntimeException $exception) {
    $emptyStrictImportRejected = str_contains($exception->getMessage(), 'empty-package');
}
$strictCommentImportPreflight = $package->strictImportPreflight(4096, 100.0, 4096);
$strictCommentImportRejected = false;
try {
    $package->assertStrictImportable(4096, 100.0, 4096);
} catch (RuntimeException $exception) {
    $strictCommentImportRejected = str_contains($exception->getMessage(), 'package-or-entry-comments');
}
$buildCommentControlBackedPackage = static function () use ($crc32): string {
    $entries = [
        [
            'name' => 'word/document.xml',
            'data' => '<w:document><w:body><w:p>Control comment review</w:p></w:body></w:document>',
            'method' => 8,
            'comment' => "entry\x7freview",
        ],
        [
            'name' => 'word/media/review.txt',
            'data' => "review media bytes with control comment metadata\n",
            'method' => 0,
            'comment' => '',
        ],
    ];
    $body = '';
    $central = '';
    foreach ($entries as $entry) {
        $name = $entry['name'];
        $data = $entry['data'];
        $method = $entry['method'];
        $comment = $entry['comment'];
        $compressed = $method === 8 ? gzdeflate($data) : $data;
        $offset = strlen($body);
        $crc = $crc32($data);
        $flags = 0x0800;

        $body .= pack(
            'VvvvvvVVVvv',
            0x04034b50,
            20,
            $flags,
            $method,
            0,
            0,
            $crc,
            strlen($compressed),
            strlen($data),
            strlen($name),
            0
        );
        $body .= $name . $compressed;

        $central .= pack(
            'VvvvvvvVVVvvvvvVV',
            0x02014b50,
            0x0314,
            20,
            $flags,
            $method,
            0,
            0,
            $crc,
            strlen($compressed),
            strlen($data),
            strlen($name),
            0,
            strlen($comment),
            0,
            0,
            0x81a40000,
            $offset
        );
        $central .= $name . $comment;
    }

    $packageComment = "package\0review";

    return $body
        . $central
        . pack('VvvvvVVv', 0x06054b50, 0, 0, count($entries), count($entries), strlen($central), strlen($body), strlen($packageComment))
        . $packageComment;
};
$strictCommentControlImportPackage = ZipPackage::fromString($buildCommentControlBackedPackage());
$strictCommentControlPreflight = $strictCommentControlImportPackage->commentPreflight();
$strictCommentControlImportPreflight = $strictCommentControlImportPackage->strictImportPreflight(4096, 100.0, 4096);
$strictCommentControlImportRejected = false;
try {
    $strictCommentControlImportPackage->assertStrictImportable(4096, 100.0, 4096);
} catch (RuntimeException $exception) {
    $strictCommentControlImportRejected = str_contains($exception->getMessage(), 'comment-control-bytes');
}
$strictCommentUnicodeControlImportPackage = ZipPackage::fromParts([
    [
        'name' => 'word/document.xml',
        'data' => '<w:document><w:body><w:p>Unicode comment review</w:p></w:body></w:document>',
        'comment' => "entry\u{200d}review",
    ],
    [
        'name' => 'word/media/review.txt',
        'data' => "review media bytes with Unicode comment metadata\n",
        'compressionMethod' => 0,
    ],
], "package\u{202e}review");
$strictCommentUnicodeControlPreflight = $strictCommentUnicodeControlImportPackage->commentPreflight();
$strictCommentUnicodeControlImportPreflight = $strictCommentUnicodeControlImportPackage->strictImportPreflight(4096, 100.0, 4096);
$strictCommentUnicodeControlImportRejected = false;
try {
    $strictCommentUnicodeControlImportPackage->assertStrictImportable(4096, 100.0, 4096);
} catch (RuntimeException $exception) {
    $strictCommentUnicodeControlImportRejected = str_contains($exception->getMessage(), 'comment-unicode-format-controls');
}
$generatedPackageCommentControlRejected = false;
try {
    ZipPackage::fromParts([
        [
            'name' => 'word/document.xml',
            'data' => '<w:document><w:body><w:p>Generated package comment guard</w:p></w:body></w:document>',
        ],
    ], "package\0review");
} catch (RuntimeException $exception) {
    $generatedPackageCommentControlRejected = str_contains($exception->getMessage(), 'control bytes');
}
$generatedEntryCommentControlRejected = false;
try {
    ZipPackage::fromParts([
        [
            'name' => 'word/document.xml',
            'data' => '<w:document><w:body><w:p>Generated entry comment guard</w:p></w:body></w:document>',
            'comment' => "entry\x7freview",
        ],
    ]);
} catch (RuntimeException $exception) {
    $generatedEntryCommentControlRejected = str_contains($exception->getMessage(), 'control bytes');
}
$generatedInvalidDosTimestampRejected = false;
try {
    ZipPackage::fromParts([
        [
            'name' => 'word/document.xml',
            'data' => '<w:document><w:body><w:p>Generated invalid DOS date guard</w:p></w:body></w:document>',
            'modifiedDosTime' => 0,
            'modifiedDosDate' => 1,
        ],
    ]);
} catch (RuntimeException $exception) {
    $generatedInvalidDosTimestampRejected = str_contains($exception->getMessage(), 'valid timestamp');
}
$odtMimetype = 'application/vnd.oasis.opendocument.text';
$odtMimetypePackage = ZipPackage::fromParts([
    [
        'name' => 'mimetype',
        'data' => $odtMimetype,
        'compressionMethod' => 0,
    ],
    [
        'name' => 'content.xml',
        'data' => '<office:document-content xmlns:office="urn:oasis:names:tc:opendocument:xmlns:office:1.0"/>',
    ],
]);
$odtMimetypePreflight = $odtMimetypePackage->storedFirstEntryPreflight('mimetype', $odtMimetype);
$odtMimetypeExtraFieldRejected = false;
try {
    ZipPackage::fromParts([
        [
            'name' => 'mimetype',
            'data' => $odtMimetype,
            'compressionMethod' => 0,
            'extraFieldData' => $documentReviewExtra,
        ],
        [
            'name' => 'content.xml',
            'data' => '<office:document-content xmlns:office="urn:oasis:names:tc:opendocument:xmlns:office:1.0"/>',
        ],
    ])->assertStoredFirstEntry('mimetype', $odtMimetype, 'ODT mimetype entry');
} catch (RuntimeException $exception) {
    $odtMimetypeExtraFieldRejected = str_contains($exception->getMessage(), 'must not carry ZIP extra fields');
}
$odtMimetypeDescriptorPackage = ZipPackage::fromString($buildStoredFirstDescriptorBackedPackage($odtMimetype));
$odtMimetypeDescriptorPreflight = $odtMimetypeDescriptorPackage->storedFirstEntryPreflight('mimetype', $odtMimetype);
$odtMimetypeDescriptorRejected = false;
try {
    $odtMimetypeDescriptorPackage->assertStoredFirstEntry('mimetype', $odtMimetype, 'ODT mimetype entry');
} catch (RuntimeException $exception) {
    $odtMimetypeDescriptorRejected = str_contains($exception->getMessage(), 'must not use a ZIP data descriptor');
}
$packageCommentPolicyRejected = false;
try {
    $package->assertNoPackageOrEntryComments();
} catch (RuntimeException $exception) {
    $packageCommentPolicyRejected = str_contains($exception->getMessage(), 'package or entry comments');
}
$corruptPayloadPackage = ZipPackage::fromString($corruptZipEntryPayload($package->bytes(), 'word/document.xml'));
$corruptPayloadPreflight = $corruptPayloadPackage->readIntegrityPreflight();
$corruptPayloadRejected = false;
try {
    $corruptPayloadPackage->assertReadableEntries();
} catch (RuntimeException $exception) {
    $corruptPayloadRejected = str_contains($exception->getMessage(), 'cannot be read by native pandoc package import');
}
$trailingDeflatePackage = ZipPackage::fromString($buildTrailingDeflateBytesBackedPackage());
$trailingDeflatePreflight = $trailingDeflatePackage->readIntegrityPreflight();
$trailingDeflateRejected = false;
try {
    $trailingDeflatePackage->assertReadableEntries();
} catch (RuntimeException $exception) {
    $trailingDeflateRejected = str_contains($exception->getMessage(), 'trailing bytes after the raw deflate stream');
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
$rawUnknownCreatorHostBytes = $buildUnknownCreatorHostBackedPackage(0x0840);
$unknownCreatorHostRawPolicy = ZipPackage::creatorHostSystemPolicyPreflight($rawUnknownCreatorHostBytes);
$unknownCreatorHostRawStrict = ZipPackage::rawStrictImportPreflight($rawUnknownCreatorHostBytes, 4096, 100.0, 4096);
$unknownCreatorHostRejected = false;
try {
    $unknownCreatorHostPackage->assertKnownCreatorHostSystems();
} catch (RuntimeException $exception) {
    $unknownCreatorHostRejected = str_contains($exception->getMessage(), 'unknown creator host-system entries');
}
$generatedDuplicateExtraFieldRejected = false;
try {
    ZipPackage::fromParts([
        [
            'name' => 'word/media/generated-duplicate-extra-review.bin',
            'data' => "Generated duplicate ZIP extra field metadata should fail before output\n",
            'extraFieldData' => pack('vva*', 0xcafe, strlen('first-review'), 'first-review')
                . pack('vva*', 0xcafe, strlen('second-review'), 'second-review'),
        ],
    ]);
} catch (RuntimeException $exception) {
    $generatedDuplicateExtraFieldRejected = str_contains($exception->getMessage(), 'duplicate extra field ids');
}
$unixOwnerExtra = $buildUnixOwnerExtra(1001, 1002);
$unixOwnerPackage = ZipPackage::fromString($buildUnixOwnerBackedPackage());
$unixOwnerPreflight = $unixOwnerPackage->unixOwnerPreflight();
$unixOwnerStrictPreflight = $unixOwnerPackage->strictImportPreflight(4096, 100.0, 4096);
$unixOwnerRejected = false;
try {
    $unixOwnerPackage->assertNoUnixOwnerMetadata();
} catch (RuntimeException $exception) {
    $unixOwnerRejected = str_contains($exception->getMessage(), 'Unix UID/GID owner extra fields');
}
$unixOwnerStrictRejected = false;
try {
    $unixOwnerPackage->assertStrictImportable(4096, 100.0, 4096);
} catch (RuntimeException $exception) {
    $unixOwnerStrictRejected = str_contains($exception->getMessage(), 'unix-owner-extra-fields');
}
$generatedUnixOwnerExtraFieldRejected = false;
try {
    ZipPackage::fromParts([
        [
            'name' => 'word/media/generated-unix-owner-review.txt',
            'data' => "Generated ZIP owner metadata should fail before output\n",
            'extraFieldData' => $unixOwnerExtra,
        ],
    ]);
} catch (RuntimeException $exception) {
    $generatedUnixOwnerExtraFieldRejected = str_contains($exception->getMessage(), 'Unix UID/GID owner metadata');
}
$duplicateExtraFieldPackage = ZipPackage::fromString($buildDuplicateExtraFieldBackedPackage());
$duplicateExtraFieldPreflight = $duplicateExtraFieldPackage->extraFieldPreflight();
$duplicateExtraFieldRejected = false;
try {
    $duplicateExtraFieldPackage->assertNoDuplicateExtraFieldIds();
} catch (RuntimeException $exception) {
    $duplicateExtraFieldRejected = str_contains($exception->getMessage(), 'duplicate extra field ids');
}
$rawExtraFieldPolicyBytes = $buildRawExtraFieldPolicyBackedPackage();
$rawExtraFieldPolicyPreflight = ZipPackage::extraFieldPolicyPreflight($rawExtraFieldPolicyBytes);
$rawExtraFieldPolicyStrictPreflight = ZipPackage::rawStrictImportPreflight($rawExtraFieldPolicyBytes, 4096, 100.0, 4096);
$extraFieldIdMismatchPackage = ZipPackage::fromString($buildExtraFieldIdMismatchBackedPackage());
$extraFieldIdMismatchPreflight = $extraFieldIdMismatchPackage->extraFieldPreflight();
$extraFieldIdMismatchRejected = false;
try {
    $extraFieldIdMismatchPackage->assertMatchingExtraFieldIds();
} catch (RuntimeException $exception) {
    $extraFieldIdMismatchRejected = str_contains($exception->getMessage(), 'central/local extra field id mismatches');
}
$extraFieldValueMismatchPackage = ZipPackage::fromString($buildExtraFieldValueMismatchBackedPackage());
$extraFieldValueMismatchPreflight = $extraFieldValueMismatchPackage->extraFieldPreflight();
$extraFieldValueMismatchRejected = false;
try {
    $extraFieldValueMismatchPackage->assertMatchingExtraFieldValues();
} catch (RuntimeException $exception) {
    $extraFieldValueMismatchRejected = str_contains($exception->getMessage(), 'central/local extra field value mismatches');
}
$malformedExtraFieldStructureBytes = $buildMalformedExtraFieldStructureBackedPackage();
$malformedExtraFieldStructurePreflight = ZipPackage::extraFieldStructurePolicyPreflight($malformedExtraFieldStructureBytes);
$malformedExtraFieldStructureRawStrictPreflight = ZipPackage::rawStrictImportPreflight(
    $malformedExtraFieldStructureBytes,
    4096,
    100.0,
    4096
);
$malformedExtraFieldStructureRejected = false;
try {
    ZipPackage::fromString($malformedExtraFieldStructureBytes);
} catch (RuntimeException $exception) {
    $malformedExtraFieldStructureRejected = str_contains($exception->getMessage(), 'extra field');
}
$pathHierarchyCollisionPackage = ZipPackage::fromParts([
    [
        'name' => 'word/document.xml',
        'data' => '<w:document><w:body><w:p>Path hierarchy collision review</w:p></w:body></w:document>',
    ],
    [
        'name' => 'word/media',
        'data' => "File entry shadowing a media directory path\n",
        'compressionMethod' => 0,
    ],
    [
        'name' => 'word/media/',
    ],
    [
        'name' => 'word/media/review.png',
        'data' => "PNG reviewer attachment placeholder\n",
        'compressionMethod' => 0,
    ],
]);
$pathHierarchyCollisionPreflight = $pathHierarchyCollisionPackage->pathHierarchyPreflight();
$pathHierarchyCollisionRejected = false;
try {
    $pathHierarchyCollisionPackage->assertNoPathHierarchyCollisions();
} catch (RuntimeException $exception) {
    $pathHierarchyCollisionRejected = str_contains($exception->getMessage(), 'file/directory path hierarchy collisions');
}
$nameHygieneReviewPackage = ZipPackage::fromParts([
    [
        'name' => 'word/document.xml',
        'data' => '<w:document><w:body><w:p>Name hygiene review</w:p></w:body></w:document>',
    ],
    [
        'name' => 'word/media/review image.png',
        'data' => "Safe internal-space media placeholder\n",
        'compressionMethod' => 0,
    ],
    [
        'name' => 'word/media/ leading.png',
        'data' => "Leading-space media placeholder\n",
        'compressionMethod' => 0,
    ],
    [
        'name' => 'word/media/review.png ',
        'data' => "Trailing-space media placeholder\n",
        'compressionMethod' => 0,
    ],
    [
        'name' => 'word/media/trailing./review.png',
        'data' => "Trailing-dot segment media placeholder\n",
        'compressionMethod' => 0,
    ],
    [
        'name' => 'word/media/CON',
        'data' => "Windows reserved media placeholder\n",
        'compressionMethod' => 0,
    ],
    [
        'name' => 'word/media/review.png:Zone.Identifier',
        'data' => "Windows alternate data stream media placeholder\n",
        'compressionMethod' => 0,
    ],
    [
        'name' => "word/media/review\u{202e}gnp.txt",
        'data' => "Bidirectional override media placeholder\n",
        'compressionMethod' => 0,
    ],
    [
        'name' => "word/media/vector\u{200d}icon.svg",
        'data' => "Zero-width joiner media placeholder\n",
        'compressionMethod' => 0,
    ],
]);
$nameHygienePreflight = $nameHygieneReviewPackage->nameHygienePreflight();
$nameHygieneStrictPreflight = $nameHygieneReviewPackage->strictImportPreflight(4096, 100.0, 4096);
$nameHygieneRejected = false;
try {
    $nameHygieneReviewPackage->assertNoNameHygieneReviewEntries();
} catch (RuntimeException $exception) {
    $nameHygieneRejected = str_contains($exception->getMessage(), 'entry name hygiene issues');
}
$nameHygieneStrictRejected = false;
try {
    $nameHygieneReviewPackage->assertStrictImportable(4096, 100.0, 4096);
} catch (RuntimeException $exception) {
    $nameHygieneStrictRejected = str_contains($exception->getMessage(), 'name-hygiene-review-entries');
}
$platformMetadataPackage = ZipPackage::fromParts([
    [
        'name' => 'word/document.xml',
        'data' => '<w:document><w:body><w:p>Platform metadata review</w:p></w:body></w:document>',
    ],
    [
        'name' => 'word/media/review.png',
        'data' => "Visible reviewer attachment placeholder\n",
        'compressionMethod' => 0,
    ],
    [
        'name' => '__MACOSX/',
    ],
    [
        'name' => '__MACOSX/word/media/._review.png',
        'data' => "AppleDouble resource fork placeholder\n",
        'compressionMethod' => 0,
    ],
    [
        'name' => 'word/media/._review.png',
        'data' => "Resource fork beside visible media\n",
        'compressionMethod' => 0,
    ],
    [
        'name' => 'word/media/.DS_Store',
        'data' => "Finder metadata should not import as document media\n",
        'compressionMethod' => 0,
    ],
]);
$platformMetadataPreflight = $platformMetadataPackage->platformMetadataPreflight();
$platformMetadataStrictPreflight = $platformMetadataPackage->strictImportPreflight(4096, 100.0, 4096);
$platformMetadataRejected = false;
try {
    $platformMetadataPackage->assertNoPlatformMetadataEntries();
} catch (RuntimeException $exception) {
    $platformMetadataRejected = str_contains($exception->getMessage(), 'platform metadata sidecar entries');
}
$platformMetadataStrictRejected = false;
try {
    $platformMetadataPackage->assertStrictImportable(4096, 100.0, 4096);
} catch (RuntimeException $exception) {
    $platformMetadataStrictRejected = str_contains($exception->getMessage(), 'platform-metadata-entries');
}
$windowsPlatformMetadataPackage = ZipPackage::fromParts([
    [
        'name' => 'word/document.xml',
        'data' => '<w:document><w:body><w:p>Windows platform metadata review</w:p></w:body></w:document>',
    ],
    [
        'name' => 'word/media/review.png',
        'data' => "Visible reviewer attachment placeholder\n",
        'compressionMethod' => 0,
    ],
    [
        'name' => 'word/media/Thumbs.db',
        'data' => "Windows thumbnail cache should not import as document media\n",
        'compressionMethod' => 0,
    ],
    [
        'name' => 'customXml/desktop.ini',
        'data' => "[.ShellClassInfo]\nLocalizedResourceName=Custom XML\n",
        'compressionMethod' => 0,
    ],
    [
        'name' => 'word/media/source-diagram.svg',
        'data' => "<svg />\n",
        'compressionMethod' => 0,
    ],
]);
$windowsPlatformMetadataPreflight = $windowsPlatformMetadataPackage->platformMetadataPreflight();
$windowsPlatformMetadataStrictPreflight = $windowsPlatformMetadataPackage->strictImportPreflight(4096, 100.0, 4096);
$windowsPlatformMetadataRejected = false;
try {
    $windowsPlatformMetadataPackage->assertNoPlatformMetadataEntries();
} catch (RuntimeException $exception) {
    $windowsPlatformMetadataRejected = str_contains($exception->getMessage(), 'Thumbs.db')
        && str_contains($exception->getMessage(), 'desktop.ini');
}
$windowsPlatformMetadataStrictRejected = false;
try {
    $windowsPlatformMetadataPackage->assertStrictImportable(4096, 100.0, 4096);
} catch (RuntimeException $exception) {
    $windowsPlatformMetadataStrictRejected = str_contains($exception->getMessage(), 'platform-metadata-entries');
}
$rawPlatformMetadataMismatchBytes = $buildRawPlatformMetadataLocalHeaderMismatchBackedPackage();
$rawPlatformMetadataPolicyPreflight = ZipPackage::platformMetadataPolicyPreflight($rawPlatformMetadataMismatchBytes);
$rawPlatformMetadataStrictPreflight = ZipPackage::rawStrictImportPreflight($rawPlatformMetadataMismatchBytes, 4096, 100.0, 4096);
$caseInsensitiveNameCollisionPackage = ZipPackage::fromParts([
    [
        'name' => 'word/document.xml',
        'data' => '<w:document><w:body><w:p>Case-insensitive media collision review</w:p></w:body></w:document>',
    ],
    [
        'name' => 'word/media/Review.PNG',
        'data' => "first reviewer attachment placeholder\n",
        'compressionMethod' => 0,
    ],
    [
        'name' => 'word/media/review.png',
        'data' => "second reviewer attachment placeholder\n",
        'compressionMethod' => 0,
    ],
]);
$caseInsensitiveNameCollisionPreflight = $caseInsensitiveNameCollisionPackage->caseInsensitiveNamePreflight();
$caseInsensitiveNameCollisionStrictPreflight = $caseInsensitiveNameCollisionPackage->strictImportPreflight(4096, 100.0, 4096);
$caseInsensitiveNameCollisionRejected = false;
try {
    $caseInsensitiveNameCollisionPackage->assertNoCaseInsensitiveNameCollisions();
} catch (RuntimeException $exception) {
    $caseInsensitiveNameCollisionRejected = str_contains($exception->getMessage(), 'case-insensitive entry name collisions');
}
$caseInsensitiveNameCollisionStrictRejected = false;
try {
    $caseInsensitiveNameCollisionPackage->assertStrictImportable(4096, 100.0, 4096);
} catch (RuntimeException $exception) {
    $caseInsensitiveNameCollisionStrictRejected = str_contains($exception->getMessage(), 'case-insensitive-name-collisions');
}
$unicodeNameCollisionPrecomposedName = "word/media/Caf\u{00e9}.PNG";
$unicodeNameCollisionDecomposedName = "word/media/cafe\u{0301}.png";
$unicodeNameCollisionPackage = ZipPackage::fromParts([
    [
        'name' => 'word/document.xml',
        'data' => '<w:document><w:body><w:p>Unicode-normalized media collision review</w:p></w:body></w:document>',
    ],
    [
        'name' => $unicodeNameCollisionPrecomposedName,
        'data' => "precomposed reviewer attachment placeholder\n",
        'compressionMethod' => 0,
    ],
    [
        'name' => $unicodeNameCollisionDecomposedName,
        'data' => "decomposed reviewer attachment placeholder\n",
        'compressionMethod' => 0,
    ],
]);
$unicodeNameCollisionPreflight = $unicodeNameCollisionPackage->caseInsensitiveNamePreflight();
$unicodeNameCollisionStrictPreflight = $unicodeNameCollisionPackage->strictImportPreflight(4096, 100.0, 4096);
$unicodeNameCollisionRejected = false;
try {
    $unicodeNameCollisionPackage->assertNoCaseInsensitiveNameCollisions();
} catch (RuntimeException $exception) {
    $unicodeNameCollisionRejected = str_contains($exception->getMessage(), 'case-insensitive entry name collisions');
}
$unicodeNameCollisionStrictRejected = false;
try {
    $unicodeNameCollisionPackage->assertStrictImportable(4096, 100.0, 4096);
} catch (RuntimeException $exception) {
    $unicodeNameCollisionStrictRejected = str_contains($exception->getMessage(), 'case-insensitive-name-collisions');
}
$rawNameCollisionPolicyBytes = $buildRawNameCollisionPolicyBackedPackage();
$rawNameCollisionPolicyPreflight = ZipPackage::centralDirectoryNameCollisionPreflight($rawNameCollisionPolicyBytes);
$rawNameCollisionStrictPreflight = ZipPackage::rawStrictImportPreflight($rawNameCollisionPolicyBytes, 4096, 100.0, 4096);
$deflateOptionFlagsRejected = false;
try {
    ZipPackage::fromString($buildStoredDeflateOptionFlagBackedPackage());
} catch (RuntimeException $exception) {
    $deflateOptionFlagsRejected = str_contains($exception->getMessage(), 'deflate compression option flag bits');
}
$strictGeneralPurposeFlagReviewPackage = ZipPackage::fromString($buildStrictGeneralPurposeFlagReviewBackedPackage());
$strictGeneralPurposeFlagPreflight = $strictGeneralPurposeFlagReviewPackage->generalPurposeFlagPreflight();
$strictGeneralPurposeFlagImportPreflight = $strictGeneralPurposeFlagReviewPackage->strictImportPreflight(4096, 100.0, 4096);
$strictGeneralPurposeFlagReviewRejected = false;
try {
    $strictGeneralPurposeFlagReviewPackage->assertNoStrictGeneralPurposeFlagReviewEntries();
} catch (RuntimeException $exception) {
    $strictGeneralPurposeFlagReviewRejected = str_contains(
        $exception->getMessage(),
        'general-purpose flag metadata'
    );
}
$strictGeneralPurposeFlagImportRejected = false;
try {
    $strictGeneralPurposeFlagReviewPackage->assertStrictImportable(4096, 100.0, 4096);
} catch (RuntimeException $exception) {
    $strictGeneralPurposeFlagImportRejected = str_contains($exception->getMessage(), 'data-descriptor-entries')
        && str_contains($exception->getMessage(), 'deflate-option-flag-entries');
}
$splitZipBytes = $rewriteZipEndOfCentralDirectory($package->bytes(), [
    'diskNumber' => 1,
    'centralDirectoryDisk' => 1,
    'diskEntryCount' => 2,
]);
$splitZipArchivePreflight = ZipPackage::endOfCentralDirectoryPreflight($splitZipBytes);
$splitZipDiskPreflight = ZipPackage::splitArchivePreflight($splitZipBytes);
$splitZipArchiveRejected = false;
try {
    ZipPackage::fromString($splitZipBytes);
} catch (RuntimeException $exception) {
    $splitZipArchiveRejected = str_contains($exception->getMessage(), 'Split ZIP packages');
}
$archiveExtraDataRecordBytes = $buildArchiveExtraDataRecordBackedPackage($package->bytes());
$archiveExtraDataRecordPreflight = ZipPackage::archiveExtraDataRecordPreflight($archiveExtraDataRecordBytes);
$archiveExtraDataRecordRejected = false;
try {
    ZipPackage::fromString($archiveExtraDataRecordBytes);
} catch (RuntimeException $exception) {
    $archiveExtraDataRecordRejected = str_contains($exception->getMessage(), 'archive extra data records');
}
$interEntryArchiveExtraDataRecordBytes = $buildInterEntryArchiveExtraDataRecordBackedPackage($package->bytes());
$interEntryArchiveExtraDataRecordPreflight = ZipPackage::archiveExtraDataRecordPreflight($interEntryArchiveExtraDataRecordBytes);
$interEntryArchiveExtraDataRecordRejected = false;
try {
    ZipPackage::fromString($interEntryArchiveExtraDataRecordBytes);
} catch (RuntimeException $exception) {
    $interEntryArchiveExtraDataRecordRejected = str_contains($exception->getMessage(), 'central directory header')
        || str_contains($exception->getMessage(), 'archive extra data records');
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
$zip64MalformedLocatorBytes = substr_replace(
    $zip64LocatorBytes,
    $packUInt64(0),
    ($zip64LocatorPreflight['zip64EndOfCentralDirectoryLocatorOffset'] ?? 0) + 8,
    8
);
$zip64MalformedLocatorPreflight = ZipPackage::endOfCentralDirectoryPreflight($zip64MalformedLocatorBytes);
$zip64MalformedLocatorAccounting = ZipPackage::zip64EndOfCentralDirectoryAccountingPreflight($zip64MalformedLocatorBytes);
$zip64MalformedLocatorRejected = false;
try {
    ZipPackage::fromString($zip64MalformedLocatorBytes);
} catch (RuntimeException $exception) {
    $zip64MalformedLocatorRejected = str_contains($exception->getMessage(), 'ZIP64 end-of-central-directory');
}
$zip64EocdMismatchBytes = $rewriteZipEndOfCentralDirectory($zip64LocatorBytes, [
    'diskEntryCount' => 2,
    'totalEntryCount' => 2,
]);
$zip64EocdMismatchAccounting = ZipPackage::zip64EndOfCentralDirectoryAccountingPreflight($zip64EocdMismatchBytes);
$zip64SmallRecordBytes = $rewriteZip64EndOfCentralDirectoryPayloadSize($zip64LocatorBytes, 40);
$zip64SmallRecordAccounting = ZipPackage::zip64EndOfCentralDirectoryAccountingPreflight($zip64SmallRecordBytes);
$rawStrictSplitZipPreflight = ZipPackage::rawStrictImportPreflight($splitZipBytes, 4096, 100.0, 4096);
$rawStrictArchiveExtraDataRecordPreflight = ZipPackage::rawStrictImportPreflight($archiveExtraDataRecordBytes, 4096, 100.0, 4096);
$rawStrictInterEntryArchiveExtraDataRecordPreflight = ZipPackage::rawStrictImportPreflight(
    $interEntryArchiveExtraDataRecordBytes,
    4096,
    100.0,
    4096
);
$rawStrictZip64EocdPreflight = ZipPackage::rawStrictImportPreflight($zip64EocdBytes, 4096, 100.0, 4096);
$rawStrictZip64LocatorPreflight = ZipPackage::rawStrictImportPreflight($zip64LocatorBytes, 4096, 100.0, 4096);
$rawStrictZip64MalformedLocatorPreflight = ZipPackage::rawStrictImportPreflight($zip64MalformedLocatorBytes, 4096, 100.0, 4096);
$rawStrictZip64EocdMismatchPreflight = ZipPackage::rawStrictImportPreflight($zip64EocdMismatchBytes, 4096, 100.0, 4096);
$rawStrictZip64SmallRecordPreflight = ZipPackage::rawStrictImportPreflight($zip64SmallRecordBytes, 4096, 100.0, 4096);
$rawStrictLocalHeaderNamePreflight = ZipPackage::rawStrictImportPreflight($buildLocalHeaderNameMismatchBackedPackage(), 4096, 100.0, 4096);
$rawStrictUnsafeLocalHeaderNamePreflight = ZipPackage::rawStrictImportPreflight($buildUnsafeLocalHeaderNameBackedPackage(), 4096, 100.0, 4096);
$rawStrictLocalHeaderSpanPreflight = ZipPackage::rawStrictImportPreflight($buildUnclaimedLocalHeaderBackedPackage(), 4096, 100.0, 4096);
$rawStrictLocalHeaderOffsetPreflight = ZipPackage::rawStrictImportPreflight(
    $rewriteFirstCentralLocalHeaderOffset($package->bytes(), $package->centralDirectoryOffset()),
    4096,
    100.0,
    4096
);
$eocdCentralDirectoryOffsetBytes = $rewriteZipEndOfCentralDirectory($package->bytes(), [
    'centralDirectoryOffset' => 0,
]);
$eocdCentralDirectoryOffsetPreflight = ZipPackage::endOfCentralDirectoryOffsetPreflight($eocdCentralDirectoryOffsetBytes);
$rawStrictEocdCentralDirectoryOffsetPreflight = ZipPackage::rawStrictImportPreflight(
    $eocdCentralDirectoryOffsetBytes,
    4096,
    100.0,
    4096
);
$gzipReviewExtra = pack('CCv', ord('W'), ord('P'), strlen('review:v1')) . 'review:v1';
$descriptorPackage = ZipPackage::fromString($buildDescriptorBackedPackage());
$descriptorDataDescriptorPreflight = $descriptorPackage->dataDescriptorPreflight();
$descriptorSlackBytes = $buildDescriptorSlackBackedPackage();
$descriptorSlackPreflight = ZipPackage::dataDescriptorIntegrityPreflight($descriptorSlackBytes);
$descriptorSlackRawStrictPreflight = ZipPackage::rawStrictImportPreflight($descriptorSlackBytes, 4096, 100.0, 4096);
$descriptorSlackRejected = false;
try {
    ZipPackage::fromString($descriptorSlackBytes);
} catch (RuntimeException $exception) {
    $descriptorSlackRejected = str_contains($exception->getMessage(), 'unexpected trailing bytes');
}
$descriptorPlaceholderRejected = false;
try {
    ZipPackage::fromString($buildDescriptorPlaceholderMismatchBackedPackage());
} catch (RuntimeException $exception) {
    $descriptorPlaceholderRejected = str_contains($exception->getMessage(), 'data descriptor placeholders');
}
$zip64DataDescriptorRejected = false;
try {
    ZipPackage::fromString($buildZip64DataDescriptorBackedPackage());
} catch (RuntimeException $exception) {
    $zip64DataDescriptorRejected = str_contains($exception->getMessage(), 'ZIP64-sized fields');
}
$ntfsPackage = ZipPackage::fromString($buildNtfsBackedPackage());
$ntfsReservedRejected = false;
try {
    ZipPackage::fromString($buildNtfsReservedBackedPackage());
} catch (RuntimeException $exception) {
    $ntfsReservedRejected = str_contains($exception->getMessage(), 'nonzero reserved bytes');
}
$extendedTimestampPackage = ZipPackage::fromString($buildExtendedTimestampBackedPackage());
$invalidDosTimestampPackage = ZipPackage::fromString($buildInvalidDosTimestampBackedPackage());
$invalidDosTimestampPreflight = $invalidDosTimestampPackage->modificationTimePreflight();
$invalidDosTimestampStrictPreflight = $invalidDosTimestampPackage->strictImportPreflight(4096, 100.0, 4096);
$invalidDosTimestampRejected = false;
try {
    $invalidDosTimestampPackage->assertValidModificationTimes();
} catch (RuntimeException $exception) {
    $invalidDosTimestampRejected = str_contains($exception->getMessage(), 'invalid DOS modification timestamps');
}
$invalidDosTimestampStrictRejected = false;
try {
    $invalidDosTimestampPackage->assertStrictImportable(4096, 100.0, 4096);
} catch (RuntimeException $exception) {
    $invalidDosTimestampStrictRejected = str_contains($exception->getMessage(), 'invalid-modification-times');
}
$unicodePathPackage = ZipPackage::fromString($buildUnicodePathBackedPackage());
$unicodePathRawNamePreflight = $unicodePathPackage->rawNamePreflight();
$unicodePathRawNameProvenanceRejected = false;
try {
    $unicodePathPackage->assertNoRawNameProvenanceReviewEntries();
} catch (RuntimeException $exception) {
    $unicodePathRawNameProvenanceRejected = str_contains($exception->getMessage(), 'raw entry-name provenance');
}
$centralUnicodePathMissingLocalBytes = $buildCentralUnicodePathMissingLocalBackedPackage();
$unicodeExtraFieldPolicyPreflight = ZipPackage::unicodeExtraFieldPolicyPreflight($centralUnicodePathMissingLocalBytes);
$unicodeExtraFieldRawStrictPreflight = ZipPackage::rawStrictImportPreflight($centralUnicodePathMissingLocalBytes, 4096, 100.0, 4096);
$centralUnicodePathMissingLocalRejected = false;
try {
    ZipPackage::fromString($centralUnicodePathMissingLocalBytes);
} catch (RuntimeException $exception) {
    $centralUnicodePathMissingLocalRejected = str_contains($exception->getMessage(), 'Unicode path metadata is missing');
}
$utf8UnicodePathMismatchRejected = false;
try {
    ZipPackage::fromString($buildUtf8UnicodePathMismatchBackedPackage());
} catch (RuntimeException $exception) {
    $utf8UnicodePathMismatchRejected = str_contains($exception->getMessage(), 'does not match UTF-8 header text');
}
$utf8UnicodeCommentMismatchRejected = false;
try {
    ZipPackage::fromString($buildUtf8UnicodeCommentMismatchBackedPackage());
} catch (RuntimeException $exception) {
    $utf8UnicodeCommentMismatchRejected = str_contains($exception->getMessage(), 'does not match UTF-8 header text');
}
$emptyUnicodeCommentRejected = false;
try {
    $rawComment = 'review comment must remain visible';
    ZipPackage::fromParts([
        [
            'name' => 'word/media/review-note.txt',
            'data' => "Empty Unicode comment metadata should stay blocked\n",
            'compressionMethod' => 0,
            'comment' => $rawComment,
            'extraFieldData' => $buildUnicodeExtra(0x6375, $rawComment, ''),
        ],
    ]);
} catch (RuntimeException $exception) {
    $emptyUnicodeCommentRejected = str_contains($exception->getMessage(), 'must not replace non-empty header text');
}
$duplicateUnicodeExtraRejected = false;
try {
    ZipPackage::fromParts([
        [
            'name' => 'word/media/review.png',
            'data' => "Duplicate Unicode path extras should stay blocked\n",
            'compressionMethod' => 0,
            'extraFieldData' => $buildUnicodeExtra(0x7075, 'word/media/review.png', 'word/media/review.png')
                . $buildUnicodeExtra(0x7075, 'word/media/review.png', 'word/media/review-alt.png'),
        ],
    ]);
} catch (RuntimeException $exception) {
    $duplicateUnicodeExtraRejected = str_contains($exception->getMessage(), 'duplicate extra field ids')
        || str_contains($exception->getMessage(), 'appears more than once');
}
$malformedExtendedTimestampRejected = false;
try {
    ZipPackage::fromParts([
        [
            'name' => 'word/media/review-timestamp.txt',
            'data' => "Malformed extended timestamp extras should stay blocked\n",
            'compressionMethod' => 0,
            'extraFieldData' => pack('vvCV', 0x5455, 5, 0x09, $documentModifiedAt),
        ],
    ]);
} catch (RuntimeException $exception) {
    $malformedExtendedTimestampRejected = str_contains($exception->getMessage(), 'unsupported flag bits');
}
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
$writablePermissionPackage = ZipPackage::fromParts([
    [
        'name' => 'word/document.xml',
        'data' => '<w:document><w:body><w:p>Writable permission review source</w:p></w:body></w:document>',
        'externalAttributes' => 0x81a40000,
    ],
    [
        'name' => 'word/media/group-writable.txt',
        'data' => "group writable media requires review\n",
        'compressionMethod' => 0,
        'externalAttributes' => 0x81b40000,
    ],
    [
        'name' => 'word/media/world-writable.txt',
        'data' => "world writable media requires review\n",
        'compressionMethod' => 0,
        'externalAttributes' => 0x81b60000,
    ],
    [
        'name' => 'word/media/open-directory/',
        'data' => '',
        'compressionMethod' => 0,
        'externalAttributes' => 0x41ff0000,
    ],
]);
$writablePermissionPreflight = $writablePermissionPackage->permissionPreflight();
$writablePermissionStrictPreflight = $writablePermissionPackage->strictImportPreflight(4096, 100.0, 4096);
$writablePermissionRejected = false;
try {
    $writablePermissionPackage->assertNoWritablePermissionEntries();
} catch (RuntimeException $exception) {
    $writablePermissionRejected = str_contains($exception->getMessage(), 'Unix group/world-writable permission entries');
}
$writablePermissionStrictRejected = false;
try {
    $writablePermissionPackage->assertStrictImportable(4096, 100.0, 4096);
} catch (RuntimeException $exception) {
    $writablePermissionStrictRejected = str_contains($exception->getMessage(), 'unix-writable-permission-entries');
}
$hiddenDosAttributePackage = ZipPackage::fromParts([
    [
        'name' => 'word/document.xml',
        'data' => '<w:document><w:body><w:p>DOS hidden media review source</w:p></w:body></w:document>',
        'externalAttributes' => 0x81a40020,
    ],
    [
        'name' => 'word/media/hidden-review.txt',
        'data' => "hidden media bytes require explicit import review\n",
        'compressionMethod' => 0,
        'externalAttributes' => 0x81a40022,
    ],
    [
        'name' => 'word/media/system-review.txt',
        'data' => "system media bytes require explicit import review\n",
        'compressionMethod' => 0,
        'externalAttributes' => 0x81a40024,
    ],
    [
        'name' => 'word/media/VOLUME',
        'data' => '',
        'compressionMethod' => 0,
        'externalAttributes' => 0x00000008,
    ],
]);
$hiddenDosAttributePreflight = $hiddenDosAttributePackage->dosAttributePreflight();
$hiddenDosAttributeStrictPreflight = $hiddenDosAttributePackage->strictImportPreflight(4096, 100.0, 4096);
$hiddenDosAttributeRejected = false;
try {
    $hiddenDosAttributePackage->assertNoHiddenSystemOrVolumeLabelEntries();
} catch (RuntimeException $exception) {
    $hiddenDosAttributeRejected = str_contains($exception->getMessage(), 'DOS hidden, system, or volume-label entries');
}
$hiddenDosAttributeStrictRejected = false;
try {
    $hiddenDosAttributePackage->assertStrictImportable(4096, 100.0, 4096);
} catch (RuntimeException $exception) {
    $hiddenDosAttributeStrictRejected = str_contains($exception->getMessage(), 'hidden-system-or-volume-label-entries');
}
$compressedPackage = GzipStream::build($package->bytes(), [
    'modifiedAt' => $documentModifiedAt,
    'extraFieldData' => $gzipReviewExtra,
    'filename' => 'wordpress-import-package.zip',
    'comment' => 'Data Liberation package fixture',
    'headerCrc' => true,
]);
$compressedPackageMembers = GzipStream::members($compressedPackage);
$compressedPackageInspection = ArchiveCompressionStream::inspectZipStream(
    $compressedPackage,
    ArchiveCompressionStream::FORMAT_GZIP_ZIP,
    strlen($package->bytes())
);
$compressedPackageDetectedFormat = ArchiveCompressionStream::detectZipFormat(
    $compressedPackage,
    strlen($package->bytes())
);
$compressedPackageDetectedKind = ArchiveCompressionStream::detectPackageKindAuto(
    $compressedPackage,
    strlen($package->bytes())
);
$compressedPackageGenericInspection = ArchiveCompressionStream::inspectPackageStreamAuto(
    $compressedPackage,
    strlen($package->bytes())
);
$compressedPackageRoundTrip = ArchiveCompressionStream::openZipAuto(
    $compressedPackage,
    strlen($package->bytes())
);
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
$paddedGzipTarPacket = $compressedTarPacket . str_repeat("\0", 12);
$paddedGzipTarInspection = ArchiveCompressionStream::inspectTarStream(
    $paddedGzipTarPacket,
    ArchiveCompressionStream::FORMAT_GZIP_TAR,
    strlen($tarPacketBytes),
    $tarPacketUnpackedBytes
);
$gzipNonZeroTrailerRejected = false;
try {
    ArchiveCompressionStream::inspectTarStream(
        $compressedTarPacket . "\0" . 'review-trailer',
        ArchiveCompressionStream::FORMAT_GZIP_TAR,
        strlen($tarPacketBytes),
        $tarPacketUnpackedBytes
    );
} catch (RuntimeException $exception) {
    $gzipNonZeroTrailerRejected = str_contains($exception->getMessage(), 'Invalid GZIP member header signature');
}
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
$textHintGzipTarPacket = GzipStream::build($tarPacketBytes, [
    'modifiedAt' => $documentModifiedAt,
    'filename' => 'wordpress-import-packet-text-flag.tar',
    'textHint' => true,
]);
$textHintGzipTarInspection = ArchiveCompressionStream::inspectTarStream(
    $textHintGzipTarPacket,
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
$streamDetectedTarKind = ArchiveCompressionStream::detectPackageKindAuto(
    $compressedTarPacket,
    strlen($tarPacketBytes),
    $tarPacketUnpackedBytes
);
$streamGenericTarInspection = ArchiveCompressionStream::inspectPackageStreamAuto(
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
    'atime' => (string) ($documentModifiedAt + 12) . '.50',
    'ctime' => (string) ($documentModifiedAt + 13) . '.75',
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
$unixSpecialFileRejected = false;
try {
    ZipPackage::fromParts([
        [
            'name' => 'word/media/reviewer-device.bin',
            'data' => 'character device metadata must not become media bytes',
            'compressionMethod' => 0,
            'externalAttributes' => 0x21b60000,
        ],
    ]);
} catch (RuntimeException $exception) {
    $unixSpecialFileRejected = str_contains($exception->getMessage(), 'Unix special file entries');
}
$zip64Rejected = false;
try {
    ZipPackage::fromString($buildZip64ExtraBackedPackage());
} catch (RuntimeException $exception) {
    $zip64Rejected = str_contains($exception->getMessage(), 'ZIP64 extra field');
}
$zip64ExtraPreflight = ZipPackage::zip64ExtraFieldPreflight($buildZip64ExtraBackedPackage());
$zip64SizeUpgradeBytes = $buildZip64SizeUpgradeBackedPackage();
$zip64SizeUpgradePreflight = ZipPackage::zip64ExtraFieldPreflight($zip64SizeUpgradeBytes);
$zip64SizeUpgradeRejected = false;
try {
    ZipPackage::fromString($zip64SizeUpgradeBytes);
} catch (RuntimeException $exception) {
    $zip64SizeUpgradeRejected = str_contains($exception->getMessage(), 'ZIP64')
        || str_contains($exception->getMessage(), 'Split ZIP entry data');
}
$zip64LocalHeaderMismatchBytes = $buildZip64LocalHeaderMismatchBackedPackage();
$zip64LocalHeaderMismatchPreflight = ZipPackage::zip64ExtraFieldPreflight($zip64LocalHeaderMismatchBytes);
$zip64LocalHeaderMismatchRawStrict = ZipPackage::rawStrictImportPreflight($zip64LocalHeaderMismatchBytes, 4096, 100.0, 4096);
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
$zipControlNameRawRejected = false;
try {
    ZipPackage::fromString($buildControlNameBackedPackage("word/media/review\nimage.png"));
} catch (RuntimeException $exception) {
    $zipControlNameRawRejected = str_contains($exception->getMessage(), 'control characters');
}
$zipControlNameGeneratedRejected = false;
try {
    ZipPackage::fromParts([
        [
            'name' => "word/media/generated\nreview.png",
            'data' => "generated control-byte ZIP media path\n",
            'compressionMethod' => 0,
        ],
    ]);
} catch (RuntimeException $exception) {
    $zipControlNameGeneratedRejected = str_contains($exception->getMessage(), 'control characters');
}
$zipControlNameUnicodeRejected = false;
try {
    $rawControlName = 'word/media/review-image.bin';
    ZipPackage::fromString($buildControlNameBackedPackage($rawControlName, "word/media/review\nimage.png"));
} catch (RuntimeException $exception) {
    $zipControlNameUnicodeRejected = str_contains($exception->getMessage(), 'control characters');
}
$zipControlNameRejected = $zipControlNameRawRejected
    && $zipControlNameGeneratedRejected
    && $zipControlNameUnicodeRejected;
$directoryPayloadRejected = false;
try {
    ZipPackage::fromString($buildDirectoryPayloadBackedPackage());
} catch (RuntimeException $exception) {
    $directoryPayloadRejected = str_contains($exception->getMessage(), 'directory entry');
}
$directoryCrcRejected = false;
try {
    ZipPackage::fromString($buildDirectoryCrcBackedPackage());
} catch (RuntimeException $exception) {
    $directoryCrcRejected = str_contains($exception->getMessage(), 'directory entry')
        && str_contains($exception->getMessage(), 'zero CRC32');
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
$unixDirectoryRegularTypeRejected = false;
try {
    ZipPackage::fromString($buildUnixFileTypeNameMismatchBackedPackage('word/media/', 0x81a40000));
} catch (RuntimeException $exception) {
    $unixDirectoryRegularTypeRejected = str_contains($exception->getMessage(), 'Unix regular-file external attributes');
}
$unixFileDirectoryTypeRejected = false;
try {
    ZipPackage::fromString($buildUnixFileTypeNameMismatchBackedPackage('word/media/reviewer-folder', 0x41ed0000));
} catch (RuntimeException $exception) {
    $unixFileDirectoryTypeRejected = str_contains($exception->getMessage(), 'Unix directory external attributes')
        && str_contains($exception->getMessage(), 'not named as a directory');
}
$generatedUnixTypeMismatchRejected = false;
try {
    ZipPackage::fromParts([
        [
            'name' => 'word/media/',
            'externalAttributes' => 0x81a40000,
        ],
    ]);
} catch (RuntimeException $exception) {
    $generatedUnixTypeMismatchRejected = str_contains($exception->getMessage(), 'Unix regular-file external attributes');
}
$unixFileTypeNameMismatchRejected = $unixDirectoryRegularTypeRejected
    && $unixFileDirectoryTypeRejected
    && $generatedUnixTypeMismatchRejected;
$rawExternalAttributePolicyZip = $buildUnixFileTypeNameMismatchBackedPackage('word/media/', 0x81a40000);
$rawExternalAttributePolicy = ZipPackage::externalAttributePolicyPreflight($rawExternalAttributePolicyZip);
$rawExternalAttributeStrictPreflight = ZipPackage::rawStrictImportPreflight(
    $rawExternalAttributePolicyZip,
    4096,
    100.0,
    4096
);
$duplicateLocalOffsetRejected = false;
try {
    ZipPackage::fromString($buildDuplicateLocalOffsetBackedPackage());
} catch (RuntimeException $exception) {
    $duplicateLocalOffsetRejected = str_contains($exception->getMessage(), 'Duplicate ZIP local header offset');
}
$duplicateCentralDirectoryNameBytes = $buildDuplicateCentralDirectoryNameBackedPackage();
$duplicateCentralDirectoryNameInventory = ZipPackage::centralDirectoryInventoryPreflight($duplicateCentralDirectoryNameBytes);
$duplicateCentralDirectoryNameRawStrictPreflight = ZipPackage::rawStrictImportPreflight(
    $duplicateCentralDirectoryNameBytes,
    4096,
    100.0,
    4096
);
$duplicateCentralDirectoryNameRejected = false;
try {
    ZipPackage::fromString($duplicateCentralDirectoryNameBytes);
} catch (RuntimeException $exception) {
    $duplicateCentralDirectoryNameRejected = str_contains($exception->getMessage(), 'Duplicate ZIP package entry');
}
$centralDirectorySignaturePackage = ZipPackage::fromString($buildCentralDirectorySignatureBackedPackage());
$centralDirectorySignaturePreflight = $centralDirectorySignaturePackage->centralDirectorySignaturePreflight();
$centralDirectorySignatureParsed = ($centralDirectorySignaturePreflight['signatureData'] ?? null) === 'central-signature'
    && ($centralDirectorySignaturePreflight['cryptographicVerification'] ?? null) === 'not-performed-native-bounded-reader'
    && $centralDirectorySignaturePackage->read('/word/document.xml') === '<w:document><w:body><w:p>Signed central directory metadata is inspectable</w:p></w:body></w:document>';
$centralDirectorySignatureStrictPreflight = $centralDirectorySignaturePackage->strictImportPreflight(4096, 100.0, 4096);
$centralDirectorySignatureStrictRejected = false;
try {
    $centralDirectorySignaturePackage->assertStrictImportable(4096, 100.0, 4096);
} catch (RuntimeException $exception) {
    $centralDirectorySignatureStrictRejected = str_contains($exception->getMessage(), 'central-directory-signature-unverified');
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
$traditionalEncryptionPreflight = ZipPackage::encryptionPolicyPreflight($buildTraditionalEncryptedBackedPackage());
$traditionalEncryptedEntry = $traditionalEncryptionPreflight['encryptedEntries'][0] ?? [];
$traditionalEncryptedRejected = false;
try {
    ZipPackage::fromString($buildTraditionalEncryptedBackedPackage());
} catch (RuntimeException $exception) {
    $traditionalEncryptedRejected = str_contains($exception->getMessage(), 'Encrypted ZIP entries');
}
$truncatedTraditionalEncryptionPreflight = ZipPackage::encryptionPolicyPreflight($buildTraditionalEncryptedBackedPackage('short', ''));
$truncatedTraditionalEncryptedEntry = $truncatedTraditionalEncryptionPreflight['encryptedEntries'][0] ?? [];
$compressedPatchedDataRejected = false;
try {
    ZipPackage::fromString($buildEncryptedMetadataBackedPackage(0x0820));
} catch (RuntimeException $exception) {
    $compressedPatchedDataRejected = str_contains($exception->getMessage(), 'Compressed-patched ZIP entries');
}
$aesExtraFieldRejected = false;
$aesExtraField = pack('vva*', 0x9901, strlen('AE' . "\x02\x00" . 'vendor' . "\x08\x00"), 'AE' . "\x02\x00" . 'vendor' . "\x08\x00");
try {
    ZipPackage::fromParts([
        [
            'name' => 'word/media/aes-review.bin',
            'data' => 'AES extra field metadata must not become import media bytes',
            'compressionMethod' => 0,
            'extraFieldData' => $aesExtraField,
        ],
    ]);
} catch (RuntimeException $exception) {
    $aesExtraFieldRejected = str_contains($exception->getMessage(), 'WinZip AES extra field');
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
$understatedVersionNeededPreflight = ZipPackage::compressionMethodPolicyPreflight($buildUnderstatedVersionNeededBackedPackage());
$understatedVersionNeededRejected = false;
try {
    ZipPackage::fromString($buildUnderstatedVersionNeededBackedPackage());
} catch (RuntimeException $exception) {
    $understatedVersionNeededRejected = str_contains($exception->getMessage(), 'requires at least 20');
}
$localHeaderNameMismatchRejected = false;
try {
    ZipPackage::fromString($buildLocalHeaderNameMismatchBackedPackage());
} catch (RuntimeException $exception) {
    $localHeaderNameMismatchRejected = str_contains($exception->getMessage(), 'local header name');
}
$unsafeLocalHeaderNameRejected = false;
try {
    ZipPackage::fromString($buildUnsafeLocalHeaderNameBackedPackage());
} catch (RuntimeException $exception) {
    $unsafeLocalHeaderNameRejected = str_contains($exception->getMessage(), 'local header name');
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
$tarDuplicatePaxKeywordRejected = false;
$tarDuplicatePaxPathRejected = false;
try {
    TarArchive::fromString(
        $buildRawTarRecord(
            'PaxHeaders/duplicate-path',
            'x',
            $buildPaxPayload(['path' => 'packet/first.xml'])
                . $buildPaxPayload(['path' => 'packet/second.xml']),
            $documentModifiedAt
        )
        . $buildRawTarRecord('placeholder.xml', '0', '<w:document/>', $documentModifiedAt)
        . str_repeat("\0", 1024)
    );
} catch (RuntimeException $exception) {
    $tarDuplicatePaxPathRejected = str_contains($exception->getMessage(), 'duplicate keyword path');
}
$tarDuplicatePaxSizeRejected = false;
try {
    TarArchive::fromString(
        $buildRawTarRecord(
            'PaxHeaders/duplicate-size',
            'x',
            $buildPaxPayload([
                'path' => 'packet/duplicate-size.xml',
                'size' => '13',
            ]) . $buildPaxPayload(['size' => '13']),
            $documentModifiedAt
        )
        . $buildRawTarRecord('placeholder-size.xml', '0', '<w:document/>', $documentModifiedAt)
        . str_repeat("\0", 1024)
    );
} catch (RuntimeException $exception) {
    $tarDuplicatePaxSizeRejected = str_contains($exception->getMessage(), 'duplicate keyword size');
}
$tarDuplicatePaxGlobalRejected = false;
try {
    TarArchive::fromString(
        $buildRawTarRecord(
            'GlobalHead/duplicate-comment',
            'g',
            $buildPaxPayload(['comment' => 'first review note'])
                . $buildPaxPayload(['comment' => 'second review note']),
            $documentModifiedAt
        )
        . $buildRawTarRecord('packet/document.xml', '0', '<w:document/>', $documentModifiedAt)
        . str_repeat("\0", 1024)
    );
} catch (RuntimeException $exception) {
    $tarDuplicatePaxGlobalRejected = str_contains($exception->getMessage(), 'duplicate keyword comment');
}
$tarDuplicatePaxKeywordRejected = $tarDuplicatePaxPathRejected
    && $tarDuplicatePaxSizeRejected
    && $tarDuplicatePaxGlobalRejected;
$tarPaxMtimeOverflowRejected = false;
$tarPaxMtimeLocalOverflowRejected = false;
$tarPaxMtimeOverflowValue = (string) PHP_INT_MAX . '0.25';
try {
    TarArchive::fromString(
        $buildRawTarRecord('PaxHeaders/overflow-mtime', 'x', $buildPaxPayload([
            'path' => 'packet/overflow-mtime.xml',
            'mtime' => $tarPaxMtimeOverflowValue,
        ]), $documentModifiedAt)
        . $buildRawTarRecord('placeholder.xml', '0', '<w:document/>', $documentModifiedAt)
        . str_repeat("\0", 1024)
    );
} catch (RuntimeException $exception) {
    $tarPaxMtimeLocalOverflowRejected = str_contains($exception->getMessage(), 'PAX mtime');
}
$tarPaxMtimeGlobalOverflowRejected = false;
try {
    TarArchive::fromString(
        $buildRawTarRecord('GlobalHead/overflow-mtime', 'g', $buildPaxPayload([
            'mtime' => $tarPaxMtimeOverflowValue,
        ]), $documentModifiedAt)
        . $buildRawTarRecord('packet/global-overflow-mtime.xml', '0', '<w:document/>', $documentModifiedAt)
        . str_repeat("\0", 1024)
    );
} catch (RuntimeException $exception) {
    $tarPaxMtimeGlobalOverflowRejected = str_contains($exception->getMessage(), 'PAX mtime');
}
$tarPaxMtimeOverflowRejected = $tarPaxMtimeLocalOverflowRejected && $tarPaxMtimeGlobalOverflowRejected;
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
$tarGnuLongNameTerminatorRejected = false;
try {
    $unterminatedGnuLongName = 'packet/' . str_repeat('unterminated-review-', 5) . 'word/document.xml';
    TarArchive::fromString(
        $buildRawTarRecord('././@LongLink', 'L', $unterminatedGnuLongName, $documentModifiedAt)
        . $buildRawTarRecord('placeholder.xml', '0', '<w:document/>', $documentModifiedAt)
        . str_repeat("\0", 1024)
    );
} catch (RuntimeException $exception) {
    $tarGnuLongNameTerminatorRejected = str_contains($exception->getMessage(), 'NUL terminator');
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

    if (
        ($packageReadIntegrityPreflight['entryCount'] ?? null) !== 3
        || ($packageReadIntegrityPreflight['readableEntryCount'] ?? null) !== 3
        || ($packageReadIntegrityPreflight['failedEntryCount'] ?? null) !== 0
    ) {
        throw new RuntimeException('Expected ZIP read-integrity preflight to accept generated package bytes');
    }

    if ($package->assertReadableEntries(4096) !== $packageReadIntegrityPreflight) {
        throw new RuntimeException('Expected ZIP read-integrity assertion to return the accepted summary');
    }

    if (
        ($strictImportPreflight['isValid'] ?? null) !== true
        || ($strictImportPreflight['diagnostics'] ?? null) !== []
        || ($strictImportPreflight['entryCount'] ?? null) !== 3
        || ($strictImportPreflight['centralDirectoryInventory'] ?? null) !== $strictImportCentralDirectoryInventory
        || ($strictImportPreflight['centralDirectoryInventory']['entryCount'] ?? null) !== 3
        || ($strictImportPreflight['centralDirectoryInventory']['isSupportedByBoundedReader'] ?? null) !== true
        || ($strictImportPreflight['contentPresence']['hasEntries'] ?? null) !== true
        || ($strictImportPreflight['contentPresence']['entryCount'] ?? null) !== 3
        || ($strictImportPreflight['compressionMethods']['supportedEntryCount'] ?? null) !== 3
        || ($strictImportPreflight['readIntegrity']['failedEntryCount'] ?? null) !== 0
        || ($strictImportPreflight['dosAttributes']['hiddenSystemOrVolumeLabelEntryCount'] ?? null) !== 0
        || ($strictImportPreflight['permissions']['writablePermissionEntryCount'] ?? null) !== 0
        || ($strictImportPreflight['modificationTimes']['invalidDosTimestampEntryCount'] ?? null) !== 0
        || ($strictImportPreflight['nameHygiene']['reviewEntryCount'] ?? null) !== 0
        || ($strictImportPreflight['platformMetadata']['platformMetadataEntryCount'] ?? null) !== 0
    ) {
        throw new RuntimeException('Expected strict ZIP import preflight to accept the clean WordPress package');
    }

    if ($strictImportPackage->assertStrictImportable(4096, 100.0, 4096) !== $strictImportPreflight) {
        throw new RuntimeException('Expected strict ZIP import assertion to return the accepted summary');
    }

    if ($strictImportPackage->read('/word/media/review.txt') !== "review media bytes\n") {
        throw new RuntimeException('Expected strict ZIP import package media bytes to remain readable after preflight');
    }

    if (
        ($strictImportEntryHandoffPreflight['isSupportedByBoundedReader'] ?? null) !== true
        || ($strictImportEntryHandoffPreflight['requestedEntryCount'] ?? null) !== 4
        || ($strictImportEntryHandoffPreflight['requiredEntryCount'] ?? null) !== 1
        || ($strictImportEntryHandoffPreflight['optionalEntryCount'] ?? null) !== 3
        || ($strictImportEntryHandoffPreflight['presentEntryCount'] ?? null) !== 3
        || ($strictImportEntryHandoffPreflight['missingRequiredEntryCount'] ?? null) !== 0
        || ($strictImportEntryHandoffPreflight['missingOptionalEntryCount'] ?? null) !== 1
        || ($strictImportEntryHandoffPreflight['handoffEntryCount'] ?? null) !== 3
        || ($strictImportEntryHandoffPreflight['failedEntryCount'] ?? null) !== 0
        || ($strictImportEntryHandoffPreflight['issues'] ?? null) !== []
        || ($strictImportEntryHandoffPreflight['handoffEntries'][0]['contentSha256'] ?? null) !== hash('sha256', $strictImportDocumentXml)
        || ($strictImportEntryHandoffPreflight['entries'][3]['status'] ?? null) !== 'missing-optional'
    ) {
        throw new RuntimeException('Expected selected ZIP entry handoff preflight to accept required document/media entries and preserve optional missing parts');
    }

    if (
        ($centralDirectoryDeclaredLowInventory['declaredEntryCount'] ?? null) !== 2
        || ($centralDirectoryDeclaredLowInventory['scannedEntryCount'] ?? null) !== 3
        || ($centralDirectoryDeclaredLowInventory['entryCountDelta'] ?? null) !== 1
        || ($centralDirectoryDeclaredLowInventory['extraScannedEntryCount'] ?? null) !== 1
        || ($centralDirectoryDeclaredLowInventory['missingDeclaredEntryCount'] ?? null) !== 0
        || ($centralDirectoryDeclaredLowInventory['entryCountMismatchKind'] ?? null) !== 'declared-too-low'
        || ($centralDirectoryDeclaredLowInventory['issues'] ?? null) !== ['central-directory-entry-count-mismatch']
    ) {
        throw new RuntimeException('Expected ZIP central-directory inventory to expose under-declared EOCD entry counts');
    }

    if (
        ($centralDirectoryDeclaredHighInventory['declaredEntryCount'] ?? null) !== 4
        || ($centralDirectoryDeclaredHighInventory['scannedEntryCount'] ?? null) !== 3
        || ($centralDirectoryDeclaredHighInventory['entryCountDelta'] ?? null) !== -1
        || ($centralDirectoryDeclaredHighInventory['extraScannedEntryCount'] ?? null) !== 0
        || ($centralDirectoryDeclaredHighInventory['missingDeclaredEntryCount'] ?? null) !== 1
        || ($centralDirectoryDeclaredHighInventory['entryCountMismatchKind'] ?? null) !== 'declared-too-high'
        || ($centralDirectoryDeclaredHighInventory['issues'] ?? null) !== ['central-directory-entry-count-mismatch']
    ) {
        throw new RuntimeException('Expected ZIP central-directory inventory to expose over-declared EOCD entry counts');
    }

    if (
        ($centralDirectoryGapInventory['scannedEntryCount'] ?? null) !== 3
        || ($centralDirectoryGapInventory['scanCompletedCentralDirectory'] ?? null) !== true
        || ($centralDirectoryGapInventory['hasUnexpectedCentralDirectoryTail'] ?? null) !== false
        || ($centralDirectoryGapInventory['hasCentralDirectoryEocdGap'] ?? null) !== true
        || ($centralDirectoryGapInventory['centralDirectoryEocdGapOffset'] ?? null) !== $strictImportCentralDirectoryInventory['centralDirectoryEnd']
        || ($centralDirectoryGapInventory['centralDirectoryEocdGapBytes'] ?? null) !== strlen($centralDirectoryGapRecord)
        || ($centralDirectoryGapInventory['issues'] ?? null) !== ['central-directory-eocd-gap']
        || ($centralDirectoryGapRawStrictPreflight['isValid'] ?? null) !== false
        || !in_array('central-directory-eocd-gap', $centralDirectoryGapRawStrictPreflight['diagnostics'] ?? [], true)
    ) {
        throw new RuntimeException('Expected ZIP central-directory gap recovery metadata before strict package import');
    }

    if (
        ($centralDirectoryUnderstatedInventory['declaredEntryCount'] ?? null) !== 3
        || ($centralDirectoryUnderstatedInventory['scannedEntryCount'] ?? null) !== 1
        || ($centralDirectoryUnderstatedInventory['entryCountMismatchKind'] ?? null) !== 'declared-too-high'
        || ($centralDirectoryUnderstatedInventory['hasCentralDirectoryEocdGap'] ?? null) !== true
        || ($centralDirectoryUnderstatedInventory['centralDirectoryEocdGapSignature'] ?? null) !== 'central-directory-header'
        || ($centralDirectoryUnderstatedInventory['hasRecoverableCentralDirectoryGapEntries'] ?? null) !== true
        || ($centralDirectoryUnderstatedInventory['recoverableGapEntryCount'] ?? null) !== 2
        || array_column($centralDirectoryUnderstatedInventory['recoverableGapEntries'] ?? [], 'name') !== ['word/media/', 'word/media/review.txt']
        || ($centralDirectoryUnderstatedRepairPlan['repairAvailable'] ?? null) !== true
        || ($centralDirectoryUnderstatedRepairPlan['policy'] ?? null) !== 'review-only-central-directory-size-repair'
        || ($centralDirectoryUnderstatedRepairPlan['plannedEntryCount'] ?? null) !== 3
        || ($centralDirectoryUnderstatedRepairPlan['correctedCentralDirectorySize'] ?? null) !== $strictImportCentralDirectoryInventory['centralDirectorySize']
        || ($centralDirectoryUnderstatedRepairPlan['unrecoveredGapBytes'] ?? null) !== 0
        || array_column($centralDirectoryUnderstatedRepairPlan['plannedEntries'] ?? [], 'name') !== ['word/document.xml', 'word/media/', 'word/media/review.txt']
        || ($centralDirectoryUnderstatedInventory['issues'] ?? null) !== [
            'central-directory-entry-count-mismatch',
            'central-directory-eocd-gap',
            'central-directory-eocd-gap-central-headers',
        ]
        || ($centralDirectoryUnderstatedRawStrictPreflight['centralDirectoryRepairPlan'] ?? null) !== $centralDirectoryUnderstatedRepairPlan
        || ($centralDirectoryUnderstatedRawStrictPreflight['isValid'] ?? null) !== false
        || !in_array('central-directory-eocd-gap-central-headers', $centralDirectoryUnderstatedRawStrictPreflight['diagnostics'] ?? [], true)
        || !in_array('central-directory-repair-plan-review', $centralDirectoryUnderstatedRawStrictPreflight['diagnostics'] ?? [], true)
        || !in_array('central-directory-size-understatement-repair-available', $centralDirectoryUnderstatedRawStrictPreflight['diagnostics'] ?? [], true)
    ) {
        throw new RuntimeException('Expected ZIP central-directory understated size to expose a review-only repair plan');
    }

    if (
        ($centralDirectoryTailInventory['scannedEntryCount'] ?? null) !== 3
        || ($centralDirectoryTailInventory['scanCompletedCentralDirectory'] ?? null) !== false
        || ($centralDirectoryTailInventory['hasUnexpectedCentralDirectoryTail'] ?? null) !== true
        || ($centralDirectoryTailInventory['centralDirectoryTailBytes'] ?? null) !== strlen($centralDirectoryTailRecord)
        || ($centralDirectoryTailInventory['unexpectedRecordOffset'] ?? null) !== $strictImportCentralDirectoryInventory['centralDirectoryEnd']
        || ($centralDirectoryTailInventory['unexpectedRecordSignatureHex'] ?? null) !== '504b0608'
        || ($centralDirectoryTailInventory['hasCentralDirectoryEocdGap'] ?? null) !== false
        || ($centralDirectoryTailInventory['issues'] ?? null) !== ['central-directory-unexpected-record', 'central-directory-unexpected-tail']
        || ($centralDirectoryTailRawStrictPreflight['isValid'] ?? null) !== false
        || !in_array('central-directory-unexpected-tail', $centralDirectoryTailRawStrictPreflight['diagnostics'] ?? [], true)
    ) {
        throw new RuntimeException('Expected ZIP central-directory unexpected-tail recovery metadata before strict package import');
    }

    if (
        !$centralDirectoryDuplicateOffsetRejected
        || ($centralDirectoryDuplicateOffsetInventory['hasDuplicateLocalHeaderOffsets'] ?? null) !== true
        || ($centralDirectoryDuplicateOffsetInventory['duplicateLocalHeaderOffsetGroupCount'] ?? null) !== 1
        || ($centralDirectoryDuplicateOffsetInventory['duplicateLocalHeaderOffsetEntryCount'] ?? null) !== 2
        || ($centralDirectoryDuplicateOffsetInventory['duplicateLocalHeaderOffsetGroups'][0]['localHeaderOffset'] ?? null) !== 0
        || ($centralDirectoryDuplicateOffsetInventory['duplicateLocalHeaderOffsetGroups'][0]['names'] ?? null) !== [
            'word/media/review-one.png',
            'word/media/review-two.png',
        ]
        || ($centralDirectoryDuplicateOffsetInventory['issues'] ?? null) !== [
            'central-directory-duplicate-local-header-offsets',
        ]
        || ($centralDirectoryDuplicateOffsetRawStrictPreflight['isValid'] ?? null) !== false
        || ($centralDirectoryDuplicateOffsetRawStrictPreflight['canInstantiate'] ?? null) !== false
        || ($centralDirectoryDuplicateOffsetRawStrictPreflight['centralDirectoryInventory'] ?? null) !== $centralDirectoryDuplicateOffsetInventory
        || !in_array('central-directory-duplicate-local-header-offsets', $centralDirectoryDuplicateOffsetRawStrictPreflight['diagnostics'] ?? [], true)
        || !in_array('zip-package-instantiation-failed', $centralDirectoryDuplicateOffsetRawStrictPreflight['diagnostics'] ?? [], true)
    ) {
        throw new RuntimeException('Expected ZIP central-directory inventory to expose duplicate local header offsets before import');
    }

    if (
        ($rawStrictImportPreflight['isValid'] ?? null) !== true
        || ($rawStrictImportPreflight['canInstantiate'] ?? null) !== true
        || ($rawStrictImportPreflight['instantiationError'] ?? null) !== null
        || ($rawStrictImportPreflight['diagnostics'] ?? null) !== []
        || ($rawStrictImportPreflight['preflightErrors'] ?? null) !== []
        || ($rawStrictImportPreflight['entryCount'] ?? null) !== 3
        || ($rawStrictImportPreflight['strictImport'] ?? null) !== $strictImportPreflight
        || ($rawStrictImportPreflight['centralDirectoryInventory']['entryCount'] ?? null) !== 3
        || ($rawStrictImportPreflight['localHeaderSpans']['issueEntryCount'] ?? null) !== 0
        || ($rawStrictImportPreflight['localHeaderSpans']['isSupportedByBoundedReader'] ?? null) !== true
        || ($rawStrictImportPreflight['compressionMethods']['supportedEntryCount'] ?? null) !== 3
        || ($rawStrictImportPreflight['encryption']['hasEncryptedEntries'] ?? null) !== false
    ) {
        throw new RuntimeException('Expected raw strict ZIP import preflight to accept clean package bytes');
    }

    if (
        ($rawStrictTrailingEocdPreflight['isValid'] ?? null) !== false
        || ($rawStrictTrailingEocdPreflight['canInstantiate'] ?? null) !== false
        || ($rawStrictTrailingEocdPreflight['entryCount'] ?? null) !== 3
        || ($rawStrictTrailingEocdPreflight['endOfCentralDirectoryTrailingBytes'] ?? null) !== $rawStrictTrailingEocdSummary
        || ($rawStrictTrailingEocdSummary['hasEndOfCentralDirectoryCandidate'] ?? null) !== true
        || ($rawStrictTrailingEocdSummary['hasTrailingBytes'] ?? null) !== true
        || ($rawStrictTrailingEocdSummary['trailingByteCount'] ?? null) !== strlen("detached reviewer bytes\n")
        || ($rawStrictTrailingEocdSummary['totalEntryCount'] ?? null) !== 3
        || !in_array('eocd-trailing-bytes', $rawStrictTrailingEocdPreflight['diagnostics'] ?? [], true)
        || !in_array('zip-package-instantiation-failed', $rawStrictTrailingEocdPreflight['diagnostics'] ?? [], true)
    ) {
        throw new RuntimeException('Expected raw strict ZIP import preflight to report bytes after EOCD');
    }

    if (
        !$prefixedZipRejected
        || ($prefixedZipPrefixPreflight['hasPackagePrefix'] ?? null) !== true
        || ($prefixedZipPrefixPreflight['prefixByteCount'] ?? null) !== strlen("MZhidden-review-stub\n")
        || ($prefixedZipPrefixPreflight['prefixSignature'] ?? null) !== 'mz-executable-stub'
        || ($prefixedZipPrefixPreflight['isPackageLayoutOtherwiseContiguous'] ?? null) !== true
        || ($prefixedZipPrefixPreflight['isSupportedByBoundedReader'] ?? null) !== false
        || ($rawStrictPrefixedZipPreflight['isValid'] ?? null) !== false
        || ($rawStrictPrefixedZipPreflight['canInstantiate'] ?? null) !== false
        || ($rawStrictPrefixedZipPreflight['packagePrefix'] ?? null) !== $prefixedZipPrefixPreflight
        || !in_array('package-prefix-bytes', $rawStrictPrefixedZipPreflight['diagnostics'] ?? [], true)
        || !in_array('package-prefix-mz-executable-stub', $rawStrictPrefixedZipPreflight['diagnostics'] ?? [], true)
    ) {
        throw new RuntimeException('Expected raw strict ZIP import preflight to report prefixed package bytes');
    }

    if (
        ($emptyStrictImportPreflight['isValid'] ?? null) !== false
        || ($emptyStrictImportPreflight['diagnostics'] ?? null) !== ['empty-package']
        || ($emptyStrictImportPreflight['contentPresence']['hasEntries'] ?? null) !== false
        || ($emptyStrictImportPreflight['contentPresence']['issues'] ?? null) !== ['empty-package']
        || !$emptyStrictImportRejected
    ) {
        throw new RuntimeException('Expected strict ZIP import preflight to reject empty package bytes');
    }

    if (
        ($emptyRawStrictImportPreflight['isValid'] ?? null) !== false
        || ($emptyRawStrictImportPreflight['canInstantiate'] ?? null) !== true
        || ($emptyRawStrictImportPreflight['instantiationError'] ?? null) !== null
        || ($emptyRawStrictImportPreflight['diagnostics'] ?? null) !== ['empty-package']
        || ($emptyRawStrictImportPreflight['strictImport'] ?? null) !== $emptyStrictImportPreflight
    ) {
        throw new RuntimeException('Expected raw strict ZIP import preflight to reject empty package bytes');
    }

    if (
        ($strictCommentImportPreflight['isValid'] ?? null) !== false
        || ($strictCommentImportPreflight['diagnostics'] ?? null) !== ['package-or-entry-comments']
        || !$strictCommentImportRejected
    ) {
        throw new RuntimeException('Expected strict ZIP import preflight to reject package and entry comments');
    }

    if (
        ($strictCommentControlPreflight['hasComments'] ?? null) !== true
        || ($strictCommentControlPreflight['hasCommentControlBytes'] ?? null) !== true
        || ($strictCommentControlPreflight['packageCommentHasControlBytes'] ?? null) !== true
        || ($strictCommentControlPreflight['packageCommentControlByteOffsets'] ?? null) !== [7]
        || ($strictCommentControlPreflight['packageCommentIssues'] ?? null) !== ['package-comment-control-bytes']
        || ($strictCommentControlPreflight['commentControlByteEntryCount'] ?? null) !== 1
        || ($strictCommentControlPreflight['commentControlByteEntries'][0]['name'] ?? null) !== 'word/document.xml'
        || ($strictCommentControlPreflight['commentControlByteEntries'][0]['commentControlByteOffsets'] ?? null) !== [5]
        || ($strictCommentControlPreflight['commentControlByteEntries'][0]['issues'] ?? null) !== ['entry-comment-control-bytes']
        || ($strictCommentControlImportPreflight['isValid'] ?? null) !== false
        || ($strictCommentControlImportPreflight['diagnostics'] ?? null) !== ['package-or-entry-comments', 'comment-control-bytes']
        || ($strictCommentControlImportPreflight['comments'] ?? null) !== $strictCommentControlPreflight
        || !$strictCommentControlImportRejected
    ) {
        throw new RuntimeException('Expected strict ZIP import preflight to reject raw comment control bytes');
    }

    if (
        ($strictCommentUnicodeControlPreflight['hasComments'] ?? null) !== true
        || ($strictCommentUnicodeControlPreflight['hasCommentControlBytes'] ?? null) !== false
        || ($strictCommentUnicodeControlPreflight['hasCommentUnicodeFormatControls'] ?? null) !== true
        || ($strictCommentUnicodeControlPreflight['hasCommentBidiControls'] ?? null) !== true
        || ($strictCommentUnicodeControlPreflight['packageCommentHasUnicodeFormatControls'] ?? null) !== true
        || ($strictCommentUnicodeControlPreflight['packageCommentHasBidiControls'] ?? null) !== true
        || ($strictCommentUnicodeControlPreflight['packageCommentUnicodeFormatControlNames'] ?? null) !== ['right-to-left-override']
        || ($strictCommentUnicodeControlPreflight['packageCommentBidiControlNames'] ?? null) !== ['right-to-left-override']
        || ($strictCommentUnicodeControlPreflight['commentUnicodeFormatControlEntryCount'] ?? null) !== 1
        || ($strictCommentUnicodeControlPreflight['commentBidiControlEntryCount'] ?? null) !== 0
        || ($strictCommentUnicodeControlPreflight['commentUnicodeFormatControlEntries'][0]['name'] ?? null) !== 'word/document.xml'
        || ($strictCommentUnicodeControlPreflight['commentUnicodeFormatControlEntries'][0]['unicodeFormatControlNames'] ?? null) !== ['zero-width-joiner']
        || ($strictCommentUnicodeControlImportPreflight['isValid'] ?? null) !== false
        || ($strictCommentUnicodeControlImportPreflight['diagnostics'] ?? null) !== [
            'package-or-entry-comments',
            'comment-unicode-format-controls',
            'comment-bidi-format-controls',
        ]
        || ($strictCommentUnicodeControlImportPreflight['comments'] ?? null) !== $strictCommentUnicodeControlPreflight
        || !$strictCommentUnicodeControlImportRejected
    ) {
        throw new RuntimeException('Expected strict ZIP import preflight to reject Unicode comment format controls');
    }

    if (!$generatedPackageCommentControlRejected || !$generatedEntryCommentControlRejected) {
        throw new RuntimeException('Expected generated ZIP comments with raw control bytes to be rejected before writing');
    }

    if (!$generatedInvalidDosTimestampRejected) {
        throw new RuntimeException('Expected generated ZIP DOS timestamp fields to be validated before writing');
    }

    if (
        ($odtMimetypePreflight['entryName'] ?? null) !== 'mimetype'
        || ($odtMimetypePreflight['firstLocalEntryName'] ?? null) !== 'mimetype'
        || ($odtMimetypePreflight['isStored'] ?? null) !== true
        || ($odtMimetypePreflight['hasCentralExtraFields'] ?? null) !== false
        || ($odtMimetypePreflight['contentsMatch'] ?? null) !== true
        || ($odtMimetypePreflight['isValid'] ?? null) !== true
    ) {
        throw new RuntimeException('Expected ODT mimetype ZIP preflight to accept a stored first entry');
    }

    if ($odtMimetypePackage->assertStoredFirstEntry('mimetype', $odtMimetype, 'ODT mimetype entry') !== $odtMimetypePreflight) {
        throw new RuntimeException('Expected ODT mimetype ZIP assertion to return the accepted summary');
    }

    if (!$odtMimetypeExtraFieldRejected) {
        throw new RuntimeException('Expected ODT mimetype ZIP extra fields to be rejected before package import');
    }

    if (
        ($odtMimetypeDescriptorPreflight['entryName'] ?? null) !== 'mimetype'
        || ($odtMimetypeDescriptorPreflight['isFirstLocalEntry'] ?? null) !== true
        || ($odtMimetypeDescriptorPreflight['isStored'] ?? null) !== true
        || ($odtMimetypeDescriptorPreflight['usesDataDescriptor'] ?? null) !== true
        || ($odtMimetypeDescriptorPreflight['contentsMatch'] ?? null) !== true
        || ($odtMimetypeDescriptorPreflight['isValid'] ?? null) !== false
        || !$odtMimetypeDescriptorRejected
    ) {
        throw new RuntimeException('Expected ODT mimetype ZIP data descriptors to be rejected before package import');
    }

    if (
        !$corruptPayloadRejected
        || ($corruptPayloadPreflight['failedEntryCount'] ?? null) !== 1
        || ($corruptPayloadPreflight['failedEntries'][0]['name'] ?? null) !== 'word/document.xml'
    ) {
        throw new RuntimeException('Expected corrupt ZIP payloads to be rejected before package media handoff');
    }

    if (
        !$trailingDeflateRejected
        || ($trailingDeflatePreflight['failedEntryCount'] ?? null) !== 1
        || ($trailingDeflatePreflight['failedEntries'][0]['name'] ?? null) !== 'word/document.xml'
        || !str_contains(($trailingDeflatePreflight['failedEntries'][0]['error'] ?? ''), 'trailing bytes after the raw deflate stream')
    ) {
        throw new RuntimeException('Expected trailing deflate ZIP payload bytes to be rejected before package media handoff');
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

    if (
        !$strictGeneralPurposeFlagReviewRejected
        || !$strictGeneralPurposeFlagImportRejected
        || ($strictGeneralPurposeFlagPreflight['strictReviewEntryCount'] ?? null) !== 1
        || ($strictGeneralPurposeFlagPreflight['dataDescriptorEntryCount'] ?? null) !== 1
        || ($strictGeneralPurposeFlagPreflight['deflateOptionEntryCount'] ?? null) !== 1
        || ($strictGeneralPurposeFlagPreflight['strictReviewEntries'][0]['deflateOptionName'] ?? null) !== 'deflate-super-fast'
        || ($strictGeneralPurposeFlagPreflight['strictReviewEntries'][0]['issues'] ?? null) !== [
            'data-descriptor-entry',
            'deflate-option-flags',
        ]
        || ($strictGeneralPurposeFlagImportPreflight['isValid'] ?? null) !== false
        || !in_array('data-descriptor-entries', $strictGeneralPurposeFlagImportPreflight['diagnostics'] ?? [], true)
        || !in_array('deflate-option-flag-entries', $strictGeneralPurposeFlagImportPreflight['diagnostics'] ?? [], true)
    ) {
        throw new RuntimeException('Expected ZIP general-purpose flag review preflight to reject strict import handoff');
    }

    if (!$storedSizeMismatchRejected) {
        throw new RuntimeException('Expected stored ZIP entry size mismatches to be rejected before media import');
    }

    if (
        ($packagePermissionPreflight['entryCount'] ?? null) !== 3
        || ($packagePermissionPreflight['executableFileCount'] ?? null) !== 0
        || ($packagePermissionPreflight['writablePermissionEntryCount'] ?? null) !== 0
    ) {
        throw new RuntimeException('Expected ZIP package permission preflight to accept generated non-executable import parts');
    }

    if ($package->assertNoExecutableFiles() !== $packagePermissionPreflight) {
        throw new RuntimeException('Expected ZIP package permission preflight to return the accepted summary');
    }

    if ($package->assertNoWritablePermissionEntries() !== $packagePermissionPreflight) {
        throw new RuntimeException('Expected ZIP package writable-permission preflight to return the accepted summary');
    }

    if (
        ($packageDosAttributePreflight['entryCount'] ?? null) !== 3
        || ($packageDosAttributePreflight['hiddenSystemOrVolumeLabelEntryCount'] ?? null) !== 0
    ) {
        throw new RuntimeException('Expected generated ZIP package DOS attributes to be safe for media import');
    }

    if ($package->assertNoHiddenSystemOrVolumeLabelEntries() !== $packageDosAttributePreflight) {
        throw new RuntimeException('Expected generated ZIP package DOS attribute preflight to return the accepted summary');
    }

    if (
        !$hiddenDosAttributeRejected
        || !$hiddenDosAttributeStrictRejected
        || ($hiddenDosAttributePreflight['hiddenSystemOrVolumeLabelEntryCount'] ?? null) !== 3
        || ($hiddenDosAttributePreflight['hiddenSystemOrVolumeLabelEntries'][0]['name'] ?? null) !== 'word/media/hidden-review.txt'
        || ($hiddenDosAttributeStrictPreflight['diagnostics'] ?? null) !== ['hidden-system-or-volume-label-entries']
    ) {
        throw new RuntimeException('Expected ZIP DOS hidden/system/volume-label attributes to be rejected before media import');
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

    if (
        ($generatedCreatorHostPreflight['entryCount'] ?? null) !== 2
        || ($generatedCreatorHostPreflight['knownHostSystemEntryCount'] ?? null) !== 2
        || ($generatedCreatorHostPreflight['unknownHostSystemEntryCount'] ?? null) !== 0
        || ($generatedCreatorHostPreflight['entries'][0]['madeByHostSystem'] ?? null) !== 10
        || ($generatedCreatorHostPreflight['entries'][0]['madeByHostSystemName'] ?? null) !== 'windows-ntfs'
        || ($generatedCreatorHostPreflight['entries'][1]['madeByHostSystem'] ?? null) !== 0
        || ($generatedCreatorHostPreflight['entries'][1]['madeByHostSystemName'] ?? null) !== 'ms-dos-fat'
        || ($generatedCreatorHostRawPolicy['unknownHostSystemEntryCount'] ?? null) !== 0
        || ($generatedCreatorHostRawPolicy['isSupportedByBoundedReader'] ?? null) !== true
        || ($generatedCreatorHostStrictPreflight['isValid'] ?? null) !== true
        || !$generatedUnknownCreatorHostRejected
    ) {
        throw new RuntimeException('Expected generated ZIP creator host systems to be explicit known metadata before package handoff');
    }

    if ($package->assertNoDuplicateExtraFieldIds() !== $packageExtraFieldPreflight) {
        throw new RuntimeException('Expected generated ZIP package extra fields to return the accepted summary');
    }

    if ($package->assertMatchingExtraFieldIds() !== $packageExtraFieldPreflight) {
        throw new RuntimeException('Expected generated ZIP package extra-field ids to match between central and local headers');
    }

    if ($package->assertMatchingExtraFieldValues() !== $packageExtraFieldPreflight) {
        throw new RuntimeException('Expected generated ZIP package extra-field values to match between central and local headers');
    }

    if (($packageExtraFieldPreflight['duplicateExtraFieldEntryCount'] ?? null) !== 0) {
        throw new RuntimeException('Expected generated ZIP package to avoid duplicate extra field ids');
    }

    if (!$generatedDuplicateExtraFieldRejected) {
        throw new RuntimeException('Expected generated ZIP writer to reject duplicate extra field ids before package output');
    }

    if ($package->assertNoUnixOwnerMetadata() !== $packageUnixOwnerPreflight) {
        throw new RuntimeException('Expected generated ZIP package Unix owner preflight to return the accepted summary');
    }

    if (($packageUnixOwnerPreflight['ownerMetadataEntryCount'] ?? null) !== 0) {
        throw new RuntimeException('Expected generated ZIP package to avoid Unix UID/GID owner metadata');
    }

    if (
        !$unixOwnerRejected
        || !$unixOwnerStrictRejected
        || ($unixOwnerPreflight['ownerMetadataEntryCount'] ?? null) !== 1
        || ($unixOwnerPreflight['ownerMetadataEntries'][0]['name'] ?? null) !== 'word/media/unix-owner-review.txt'
        || ($unixOwnerPreflight['ownerMetadataEntries'][0]['centralOwner']['uid'] ?? null) !== 1001
        || ($unixOwnerPreflight['ownerMetadataEntries'][0]['localOwner']['gid'] ?? null) !== 1002
        || ($unixOwnerStrictPreflight['diagnostics'][0] ?? null) !== 'unix-owner-extra-fields'
    ) {
        throw new RuntimeException('Expected ZIP Unix UID/GID owner extra fields to stay blocked before media import');
    }

    if (!$generatedUnixOwnerExtraFieldRejected) {
        throw new RuntimeException('Expected generated ZIP writer to reject Unix UID/GID owner metadata before package output');
    }

    if (($packageExtraFieldPreflight['mismatchedExtraFieldEntryCount'] ?? null) !== 0) {
        throw new RuntimeException('Expected generated ZIP package to avoid central/local extra field id mismatches');
    }

    if (($packageExtraFieldPreflight['mismatchedExtraFieldValueEntryCount'] ?? null) !== 0) {
        throw new RuntimeException('Expected generated ZIP package to avoid central/local extra field value mismatches');
    }

    if ($package->assertNoPathHierarchyCollisions() !== $packagePathHierarchyPreflight) {
        throw new RuntimeException('Expected generated ZIP package path hierarchy preflight to return the accepted summary');
    }

    if (($packagePathHierarchyPreflight['collisionEntryCount'] ?? null) !== 0) {
        throw new RuntimeException('Expected generated ZIP package to avoid file/directory path hierarchy collisions');
    }

    if ($package->assertNoCaseInsensitiveNameCollisions() !== $packageCaseInsensitiveNamePreflight) {
        throw new RuntimeException('Expected generated ZIP package case-insensitive name preflight to return the accepted summary');
    }

    if (($packageCaseInsensitiveNamePreflight['collisionEntryCount'] ?? null) !== 0) {
        throw new RuntimeException('Expected generated ZIP package to avoid case-insensitive entry name collisions');
    }

    if (
        !$unknownCreatorHostRejected
        || ($unknownCreatorHostPreflight['unknownHostSystemEntryCount'] ?? null) !== 1
        || ($unknownCreatorHostPreflight['unknownEntries'][0]['madeByHostSystem'] ?? null) !== 63
        || ($unknownCreatorHostPreflight['unknownEntries'][0]['name'] ?? null) !== 'word/media/unknown-host-review.bin'
        || ($unknownCreatorHostRawPolicy['unknownHostSystemEntryCount'] ?? null) !== 1
        || ($unknownCreatorHostRawPolicy['blockedEntryCount'] ?? null) !== 1
        || ($unknownCreatorHostRawPolicy['unknownEntries'][0]['policy'] ?? null) !== 'blocked'
        || ($unknownCreatorHostRawStrict['canInstantiate'] ?? null) !== false
        || ($unknownCreatorHostRawStrict['creatorHostSystems'] ?? null) !== $unknownCreatorHostRawPolicy
        || !in_array('unknown-creator-host-systems', $unknownCreatorHostRawStrict['diagnostics'] ?? [], true)
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

    if (
        ($rawExtraFieldPolicyPreflight['entryCount'] ?? null) !== 3
        || ($rawExtraFieldPolicyPreflight['duplicateExtraFieldEntryCount'] ?? null) !== 1
        || ($rawExtraFieldPolicyPreflight['duplicateEntries'][0]['duplicateCentralExtraFieldIds'][0] ?? null) !== 0xcafe
        || ($rawExtraFieldPolicyPreflight['duplicateEntries'][0]['duplicateLocalExtraFieldIds'][0] ?? null) !== 0xcafe
        || ($rawExtraFieldPolicyPreflight['mismatchedExtraFieldEntryCount'] ?? null) !== 1
        || ($rawExtraFieldPolicyPreflight['mismatchedEntries'][0]['centralOnlyExtraFieldIds'][0] ?? null) !== 0xbeef
        || ($rawExtraFieldPolicyPreflight['mismatchedEntries'][0]['localOnlyExtraFieldIds'][0] ?? null) !== 0xfeed
        || ($rawExtraFieldPolicyPreflight['mismatchedExtraFieldValueEntryCount'] ?? null) !== 1
        || ($rawExtraFieldPolicyPreflight['valueMismatchedEntries'][0]['mismatchedExtraFieldValueIds'][0] ?? null) !== 0xf00d
        || ($rawExtraFieldPolicyStrictPreflight['extraFields'] ?? null) !== $rawExtraFieldPolicyPreflight
        || !in_array('extra-field-policy-issues', $rawExtraFieldPolicyStrictPreflight['diagnostics'] ?? [], true)
        || !in_array('duplicate-extra-field-ids', $rawExtraFieldPolicyStrictPreflight['diagnostics'] ?? [], true)
        || !in_array('central-local-extra-field-id-mismatch', $rawExtraFieldPolicyStrictPreflight['diagnostics'] ?? [], true)
        || !in_array('central-local-extra-field-value-mismatch', $rawExtraFieldPolicyStrictPreflight['diagnostics'] ?? [], true)
    ) {
        throw new RuntimeException('Expected raw ZIP extra-field policy to be reported before media import instantiation');
    }

    if (
        !$extraFieldIdMismatchRejected
        || ($extraFieldIdMismatchPreflight['mismatchedExtraFieldEntryCount'] ?? null) !== 1
        || ($extraFieldIdMismatchPreflight['mismatchedEntries'][0]['name'] ?? null) !== 'word/media/split-extra-review.bin'
        || ($extraFieldIdMismatchPreflight['mismatchedEntries'][0]['centralOnlyExtraFieldIds'][0] ?? null) !== 0xcafe
        || ($extraFieldIdMismatchPreflight['mismatchedEntries'][0]['localOnlyExtraFieldIds'][0] ?? null) !== 0xbeef
    ) {
        throw new RuntimeException('Expected central/local ZIP extra field id mismatches to stay blocked for strict media import');
    }

    if (
        !$extraFieldValueMismatchRejected
        || ($extraFieldValueMismatchPreflight['mismatchedExtraFieldValueEntryCount'] ?? null) !== 1
        || ($extraFieldValueMismatchPreflight['valueMismatchedEntries'][0]['name'] ?? null) !== 'word/media/split-extra-value-review.bin'
        || ($extraFieldValueMismatchPreflight['valueMismatchedEntries'][0]['mismatchedExtraFieldValueIds'][0] ?? null) !== 0xcafe
    ) {
        throw new RuntimeException('Expected central/local ZIP extra field value mismatches to stay blocked for strict media import');
    }

    if (
        !$malformedExtraFieldStructureRejected
        || ($malformedExtraFieldStructurePreflight['entryCount'] ?? null) !== 2
        || ($malformedExtraFieldStructurePreflight['issueEntryCount'] ?? null) !== 2
        || ($malformedExtraFieldStructurePreflight['centralExtraFieldIssueEntryCount'] ?? null) !== 1
        || ($malformedExtraFieldStructurePreflight['localExtraFieldIssueEntryCount'] ?? null) !== 1
        || ($malformedExtraFieldStructurePreflight['issues'] ?? null) !== [
            'central-extra-field-truncated-payload',
            'local-extra-field-truncated-header',
        ]
        || ($malformedExtraFieldStructureRawStrictPreflight['extraFieldStructure'] ?? null) !== $malformedExtraFieldStructurePreflight
        || !in_array('extra-field-structure-issues', $malformedExtraFieldStructureRawStrictPreflight['diagnostics'] ?? [], true)
        || !in_array('central-extra-field-truncated-payload', $malformedExtraFieldStructureRawStrictPreflight['diagnostics'] ?? [], true)
        || !in_array('local-extra-field-truncated-header', $malformedExtraFieldStructureRawStrictPreflight['diagnostics'] ?? [], true)
    ) {
        throw new RuntimeException('Expected malformed ZIP extra-field structure to be reported before media import instantiation');
    }

    if (
        !$pathHierarchyCollisionRejected
        || ($pathHierarchyCollisionPreflight['collisionEntryCount'] ?? null) !== 3
        || ($pathHierarchyCollisionPreflight['collisionEntries'][0]['name'] ?? null) !== 'word/media'
        || ($pathHierarchyCollisionPreflight['collisionEntries'][0]['samePathDirectoryName'] ?? null) !== 'word/media/'
        || ($pathHierarchyCollisionPreflight['collisionEntries'][1]['name'] ?? null) !== 'word/media/'
        || ($pathHierarchyCollisionPreflight['collisionEntries'][2]['ancestorFileNames'][0] ?? null) !== 'word/media'
    ) {
        throw new RuntimeException('Expected ZIP file/directory path hierarchy collisions to stay blocked for strict media import');
    }

    if (
        !$nameHygieneRejected
        || !$nameHygieneStrictRejected
        || ($nameHygienePreflight['reviewEntryCount'] ?? null) !== 7
        || ($nameHygienePreflight['leadingOrTrailingWhitespaceEntryCount'] ?? null) !== 2
        || ($nameHygienePreflight['trailingDotSegmentEntryCount'] ?? null) !== 1
        || ($nameHygienePreflight['windowsReservedNameEntryCount'] ?? null) !== 1
        || ($nameHygienePreflight['windowsAlternateDataStreamEntryCount'] ?? null) !== 1
        || ($nameHygienePreflight['unicodeFormatControlEntryCount'] ?? null) !== 2
        || ($nameHygienePreflight['unicodeBidiControlEntryCount'] ?? null) !== 1
        || ($nameHygienePreflight['entries'][1]['hasNameHygieneIssue'] ?? null) !== false
        || ($nameHygienePreflight['reviewEntries'][0]['flaggedSegments'][0]['segment'] ?? null) !== ' leading.png'
        || ($nameHygienePreflight['reviewEntries'][3]['issues'] ?? null) !== ['segment-windows-reserved-name']
        || ($nameHygienePreflight['reviewEntries'][4]['issues'] ?? null) !== ['segment-windows-alternate-data-stream']
        || ($nameHygienePreflight['reviewEntries'][5]['issues'] ?? null) !== ['segment-unicode-format-control', 'segment-bidi-format-control']
        || ($nameHygienePreflight['reviewEntries'][6]['flaggedSegments'][0]['unicodeFormatControlNames'] ?? null) !== ['zero-width-joiner']
        || ($nameHygieneStrictPreflight['diagnostics'] ?? null) !== ['name-hygiene-review-entries']
    ) {
        throw new RuntimeException('Expected ZIP entry name hygiene issues to stay blocked for strict media import');
    }

    if (
        !$platformMetadataRejected
        || !$platformMetadataStrictRejected
        || ($platformMetadataPreflight['platformMetadataEntryCount'] ?? null) !== 4
        || ($platformMetadataPreflight['macosSidecarEntryCount'] ?? null) !== 2
        || ($platformMetadataPreflight['appleDoubleEntryCount'] ?? null) !== 2
        || ($platformMetadataPreflight['finderMetadataEntryCount'] ?? null) !== 1
        || ($platformMetadataPreflight['windowsSidecarEntryCount'] ?? null) !== 0
        || ($platformMetadataPreflight['entries'][1]['platform'] ?? null) !== null
        || ($platformMetadataPreflight['platformMetadataEntries'][0]['name'] ?? null) !== '__MACOSX/'
        || ($platformMetadataPreflight['platformMetadataEntries'][1]['issues'] ?? null) !== ['macos-sidecar-entry', 'appledouble-resource-entry']
        || ($platformMetadataStrictPreflight['diagnostics'] ?? null) !== ['platform-metadata-entries']
        || ($platformMetadataStrictPreflight['platformMetadata']['platformMetadataEntryCount'] ?? null) !== 4
        || $platformMetadataPackage->read('/word/media/review.png') !== "Visible reviewer attachment placeholder\n"
    ) {
        throw new RuntimeException('Expected macOS ZIP platform metadata sidecars to stay blocked for strict media import');
    }

    if (
        !$windowsPlatformMetadataRejected
        || !$windowsPlatformMetadataStrictRejected
        || ($windowsPlatformMetadataPreflight['platformMetadataEntryCount'] ?? null) !== 2
        || ($windowsPlatformMetadataPreflight['macosSidecarEntryCount'] ?? null) !== 0
        || ($windowsPlatformMetadataPreflight['windowsSidecarEntryCount'] ?? null) !== 2
        || ($windowsPlatformMetadataPreflight['windowsThumbnailCacheEntryCount'] ?? null) !== 1
        || ($windowsPlatformMetadataPreflight['windowsDesktopIniEntryCount'] ?? null) !== 1
        || ($windowsPlatformMetadataPreflight['platformMetadataEntries'][0]['name'] ?? null) !== 'word/media/Thumbs.db'
        || ($windowsPlatformMetadataPreflight['platformMetadataEntries'][0]['platform'] ?? null) !== 'windows'
        || ($windowsPlatformMetadataPreflight['platformMetadataEntries'][0]['issues'] ?? null) !== ['windows-thumbnail-cache-entry']
        || ($windowsPlatformMetadataPreflight['platformMetadataEntries'][1]['issues'] ?? null) !== ['windows-desktop-ini-entry']
        || ($windowsPlatformMetadataStrictPreflight['diagnostics'] ?? null) !== ['platform-metadata-entries']
        || ($windowsPlatformMetadataStrictPreflight['platformMetadata']['windowsSidecarEntryCount'] ?? null) !== 2
        || $windowsPlatformMetadataPackage->read('/word/media/Thumbs.db') !== "Windows thumbnail cache should not import as document media\n"
    ) {
        throw new RuntimeException('Expected Windows ZIP platform metadata sidecars to stay blocked for strict media import');
    }

    if (
        ($rawPlatformMetadataPolicyPreflight['platformMetadataEntryCount'] ?? null) !== 2
        || ($rawPlatformMetadataPolicyPreflight['macosSidecarEntryCount'] ?? null) !== 1
        || ($rawPlatformMetadataPolicyPreflight['appleDoubleEntryCount'] ?? null) !== 1
        || ($rawPlatformMetadataPolicyPreflight['windowsSidecarEntryCount'] ?? null) !== 1
        || ($rawPlatformMetadataPolicyPreflight['platformMetadataEntries'][0]['name'] ?? null) !== '__MACOSX/word/media/._review.png'
        || ($rawPlatformMetadataPolicyPreflight['platformMetadataEntries'][0]['diagnostics'] ?? null) !== ['zip-macos-sidecar-entry', 'zip-appledouble-resource-entry']
        || ($rawPlatformMetadataPolicyPreflight['platformMetadataEntries'][1]['name'] ?? null) !== 'word/media/Thumbs.db'
        || ($rawPlatformMetadataStrictPreflight['canInstantiate'] ?? null) !== false
        || ($rawPlatformMetadataStrictPreflight['platformMetadata'] ?? null) !== $rawPlatformMetadataPolicyPreflight
        || !in_array('platform-metadata-entries', $rawPlatformMetadataStrictPreflight['diagnostics'] ?? [], true)
        || !in_array('local-header-name-mismatch', $rawPlatformMetadataStrictPreflight['diagnostics'] ?? [], true)
        || !in_array('zip-package-instantiation-failed', $rawPlatformMetadataStrictPreflight['diagnostics'] ?? [], true)
    ) {
        throw new RuntimeException('Expected raw ZIP platform metadata policy to survive package instantiation failure');
    }

    if (
        !$caseInsensitiveNameCollisionRejected
        || !$caseInsensitiveNameCollisionStrictRejected
        || ($caseInsensitiveNameCollisionPreflight['collisionGroupCount'] ?? null) !== 1
        || ($caseInsensitiveNameCollisionPreflight['collisionEntryCount'] ?? null) !== 2
        || ($caseInsensitiveNameCollisionPreflight['collisionGroups'][0]['caseFoldKey'] ?? null) !== 'word/media/review.png'
        || ($caseInsensitiveNameCollisionPreflight['collisionEntries'][0]['name'] ?? null) !== 'word/media/Review.PNG'
        || ($caseInsensitiveNameCollisionPreflight['collisionEntries'][1]['name'] ?? null) !== 'word/media/review.png'
        || ($caseInsensitiveNameCollisionStrictPreflight['diagnostics'] ?? null) !== ['case-insensitive-name-collisions']
    ) {
        throw new RuntimeException('Expected ZIP case-insensitive entry name collisions to stay blocked for strict media import');
    }

    if ($caseInsensitiveNameCollisionPackage->read('/word/media/Review.PNG') !== "first reviewer attachment placeholder\n") {
        throw new RuntimeException('Expected exact-case ZIP media path to remain readable before case-insensitive handoff rejection');
    }

    if (
        !$unicodeNameCollisionRejected
        || !$unicodeNameCollisionStrictRejected
        || ($unicodeNameCollisionPreflight['collisionGroupCount'] ?? null) !== 1
        || ($unicodeNameCollisionPreflight['collisionEntryCount'] ?? null) !== 2
        || ($unicodeNameCollisionPreflight['collisionGroups'][0]['caseFoldKey'] ?? null) !== "word/media/caf\u{00e9}.png"
        || ($unicodeNameCollisionPreflight['collisionEntries'][0]['name'] ?? null) !== $unicodeNameCollisionPrecomposedName
        || ($unicodeNameCollisionPreflight['collisionEntries'][1]['name'] ?? null) !== $unicodeNameCollisionDecomposedName
        || ($unicodeNameCollisionStrictPreflight['diagnostics'] ?? null) !== ['case-insensitive-name-collisions']
    ) {
        throw new RuntimeException('Expected Unicode-normalized ZIP entry name collisions to stay blocked for strict media import');
    }

    if ($unicodeNameCollisionPackage->read('/' . $unicodeNameCollisionDecomposedName) !== "decomposed reviewer attachment placeholder\n") {
        throw new RuntimeException('Expected exact Unicode ZIP media path to remain readable before normalized handoff rejection');
    }

    if (
        ($rawNameCollisionPolicyPreflight['isSupportedByBoundedReader'] ?? null) !== false
        || ($rawNameCollisionPolicyPreflight['caseInsensitiveNameCollisionEntryCount'] ?? null) !== 2
        || ($rawNameCollisionPolicyPreflight['rawNameCollisionEntryCount'] ?? null) !== 2
        || ($rawNameCollisionPolicyPreflight['caseInsensitiveNameCollisionGroups'][0]['caseFoldKey'] ?? null) !== 'word/media/review.png'
        || ($rawNameCollisionPolicyPreflight['rawNameCollisionGroups'][0]['rawName'] ?? null) !== 'word/media/review-image.bin'
        || ($rawNameCollisionStrictPreflight['canInstantiate'] ?? null) !== false
        || ($rawNameCollisionStrictPreflight['centralDirectoryNameCollisions'] ?? null) !== $rawNameCollisionPolicyPreflight
        || !in_array('central-directory-name-collision-issues', $rawNameCollisionStrictPreflight['diagnostics'] ?? [], true)
        || !in_array('case-insensitive-name-collisions', $rawNameCollisionStrictPreflight['diagnostics'] ?? [], true)
        || !in_array('raw-name-collisions', $rawNameCollisionStrictPreflight['diagnostics'] ?? [], true)
        || !in_array('encrypted-zip-entries', $rawNameCollisionStrictPreflight['diagnostics'] ?? [], true)
        || !in_array('zip-package-instantiation-failed', $rawNameCollisionStrictPreflight['diagnostics'] ?? [], true)
    ) {
        throw new RuntimeException('Expected raw ZIP central-directory name collisions to survive package instantiation failure');
    }

    if (!$zipControlNameRejected) {
        throw new RuntimeException('Expected ZIP entry names with control bytes to be rejected before media import');
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
        || ($splitZipDiskPreflight['hasSplitArchiveMarkers'] ?? null) !== true
        || ($splitZipDiskPreflight['splitArchiveEntryCount'] ?? null) !== 0
        || ($splitZipDiskPreflight['isSupportedByBoundedReader'] ?? null) !== false
        || ($splitZipDiskPreflight['issues'] ?? null) !== ['split-archive-eocd']
        || !$splitZipArchiveRejected
    ) {
        throw new RuntimeException('Expected split ZIP EOCD metadata to be reported and rejected before import');
    }

    if (
        ($rawStrictSplitZipPreflight['isValid'] ?? null) !== false
        || ($rawStrictSplitZipPreflight['canInstantiate'] ?? null) !== false
        || ($rawStrictSplitZipPreflight['splitArchive']['issues'] ?? null) !== ['split-archive-eocd']
        || !in_array('unsupported-archive-layout', $rawStrictSplitZipPreflight['diagnostics'] ?? [], true)
        || !in_array('split-archive-eocd', $rawStrictSplitZipPreflight['diagnostics'] ?? [], true)
        || !in_array('zip-package-instantiation-failed', $rawStrictSplitZipPreflight['diagnostics'] ?? [], true)
    ) {
        throw new RuntimeException('Expected raw strict ZIP import preflight to reject split package bytes');
    }

    if (
        ($archiveExtraDataRecordPreflight['archiveExtraDataRecordCount'] ?? null) !== 1
        || ($archiveExtraDataRecordPreflight['hasArchiveExtraDataRecord'] ?? null) !== true
        || ($archiveExtraDataRecordPreflight['isSupportedByBoundedReader'] ?? null) !== false
        || ($archiveExtraDataRecordPreflight['archiveExtraDataRecords'][0]['location'] ?? null) !== 'between-central-directory-and-eocd'
        || ($archiveExtraDataRecordPreflight['archiveExtraDataRecords'][0]['issues'] ?? null) !== ['archive-extra-data-record']
        || !$archiveExtraDataRecordRejected
    ) {
        throw new RuntimeException('Expected ZIP archive extra data records to be reported and rejected before import');
    }

    if (
        ($rawStrictArchiveExtraDataRecordPreflight['isValid'] ?? null) !== false
        || ($rawStrictArchiveExtraDataRecordPreflight['canInstantiate'] ?? null) !== false
        || ($rawStrictArchiveExtraDataRecordPreflight['archiveExtraDataRecords']['archiveExtraDataRecordCount'] ?? null) !== 1
        || ($rawStrictArchiveExtraDataRecordPreflight['archiveExtraDataRecords']['archiveExtraDataRecords'][0]['location'] ?? null) !== 'between-central-directory-and-eocd'
        || !in_array('archive-extra-data-records', $rawStrictArchiveExtraDataRecordPreflight['diagnostics'] ?? [], true)
        || !in_array('zip-package-instantiation-failed', $rawStrictArchiveExtraDataRecordPreflight['diagnostics'] ?? [], true)
    ) {
        throw new RuntimeException('Expected raw strict ZIP import preflight to reject archive extra data records');
    }

    if (
        ($interEntryArchiveExtraDataRecordPreflight['entryCount'] ?? null) !== 3
        || ($interEntryArchiveExtraDataRecordPreflight['archiveExtraDataRecordCount'] ?? null) !== 1
        || ($interEntryArchiveExtraDataRecordPreflight['hasArchiveExtraDataRecord'] ?? null) !== true
        || ($interEntryArchiveExtraDataRecordPreflight['isSupportedByBoundedReader'] ?? null) !== false
        || ($interEntryArchiveExtraDataRecordPreflight['archiveExtraDataRecords'][0]['location'] ?? null) !== 'before-central-directory-entry'
        || ($interEntryArchiveExtraDataRecordPreflight['entries'][1]['name'] ?? null) !== '_rels/.rels'
        || !$interEntryArchiveExtraDataRecordRejected
    ) {
        throw new RuntimeException('Expected inter-entry ZIP archive extra data records to keep central-entry provenance before rejection');
    }

    if (
        ($rawStrictInterEntryArchiveExtraDataRecordPreflight['isValid'] ?? null) !== false
        || ($rawStrictInterEntryArchiveExtraDataRecordPreflight['canInstantiate'] ?? null) !== false
        || ($rawStrictInterEntryArchiveExtraDataRecordPreflight['archiveExtraDataRecords']['archiveExtraDataRecordCount'] ?? null) !== 1
        || ($rawStrictInterEntryArchiveExtraDataRecordPreflight['archiveExtraDataRecords']['archiveExtraDataRecords'][0]['location'] ?? null) !== 'before-central-directory-entry'
        || !in_array('archive-extra-data-records', $rawStrictInterEntryArchiveExtraDataRecordPreflight['diagnostics'] ?? [], true)
        || !in_array('zip-package-instantiation-failed', $rawStrictInterEntryArchiveExtraDataRecordPreflight['diagnostics'] ?? [], true)
    ) {
        throw new RuntimeException('Expected raw strict ZIP import preflight to reject inter-entry archive extra data records');
    }

    if (
        ($zip64EocdPreflight['requiresZip64'] ?? null) !== true
        || ($zip64EocdPreflight['isArchiveLayoutSupported'] ?? null) !== false
        || !$zip64EocdRejected
    ) {
        throw new RuntimeException('Expected ZIP64 EOCD markers to be reported and rejected before import');
    }

    if (
        ($rawStrictZip64EocdPreflight['isValid'] ?? null) !== false
        || ($rawStrictZip64EocdPreflight['canInstantiate'] ?? null) !== false
        || ($rawStrictZip64EocdPreflight['archive']['requiresZip64'] ?? null) !== true
        || ($rawStrictZip64EocdPreflight['centralDirectoryInventory'] ?? null) !== null
        || !in_array('unsupported-archive-layout', $rawStrictZip64EocdPreflight['diagnostics'] ?? [], true)
        || !str_contains(implode(',', $rawStrictZip64EocdPreflight['diagnostics'] ?? []), 'zip64-end-of-central-directory')
        || !in_array('zip-package-instantiation-failed', $rawStrictZip64EocdPreflight['diagnostics'] ?? [], true)
    ) {
        throw new RuntimeException('Expected raw strict ZIP import preflight to reject ZIP64 EOCD markers');
    }

    if (
        ($zip64LocatorPreflight['hasZip64EndOfCentralDirectoryLocator'] ?? null) !== true
        || ($zip64LocatorPreflight['hasZip64EndOfCentralDirectory'] ?? null) !== true
        || ($zip64LocatorPreflight['zip64EndOfCentralDirectorySize'] ?? null) !== 56
        || !$zip64LocatorRejected
    ) {
        throw new RuntimeException('Expected ZIP64 EOCD locator metadata to be reported and rejected before import');
    }

    if (
        ($rawStrictZip64LocatorPreflight['isValid'] ?? null) !== false
        || ($rawStrictZip64LocatorPreflight['canInstantiate'] ?? null) !== false
        || ($rawStrictZip64LocatorPreflight['zip64EndOfCentralDirectory']['hasZip64EndOfCentralDirectoryLocator'] ?? null) !== true
        || ($rawStrictZip64LocatorPreflight['centralDirectoryInventory'] ?? null) !== null
        || !str_contains(implode(',', $rawStrictZip64LocatorPreflight['diagnostics'] ?? []), 'zip64-end-of-central-directory')
        || !in_array('zip-package-instantiation-failed', $rawStrictZip64LocatorPreflight['diagnostics'] ?? [], true)
    ) {
        throw new RuntimeException('Expected raw strict ZIP import preflight to reject ZIP64 locator bytes');
    }

    if (
        ($zip64MalformedLocatorPreflight['hasZip64EndOfCentralDirectoryLocator'] ?? null) !== true
        || ($zip64MalformedLocatorPreflight['hasZip64EndOfCentralDirectory'] ?? null) !== false
        || !in_array('zip64-end-of-central-directory-record-missing', $zip64MalformedLocatorPreflight['zip64Issues'] ?? [], true)
        || ($zip64MalformedLocatorAccounting['recordSignature'] ?? null) !== 'local-file-header'
        || ($zip64MalformedLocatorAccounting['recordSignatureHex'] ?? null) !== '504b0304'
        || !in_array('zip64-end-of-central-directory-locator-target-not-record', $zip64MalformedLocatorAccounting['issues'] ?? [], true)
        || !$zip64MalformedLocatorRejected
    ) {
        throw new RuntimeException('Expected malformed ZIP64 locator metadata to be reported and rejected before import');
    }

    if (
        ($rawStrictZip64MalformedLocatorPreflight['isValid'] ?? null) !== false
        || ($rawStrictZip64MalformedLocatorPreflight['canInstantiate'] ?? null) !== false
        || ($rawStrictZip64MalformedLocatorPreflight['zip64EndOfCentralDirectory']['hasZip64EndOfCentralDirectoryLocator'] ?? null) !== true
        || ($rawStrictZip64MalformedLocatorPreflight['zip64EndOfCentralDirectory']['hasZip64EndOfCentralDirectory'] ?? null) !== false
        || ($rawStrictZip64MalformedLocatorPreflight['zip64EndOfCentralDirectory']['recordSignature'] ?? null) !== 'local-file-header'
        || ($rawStrictZip64MalformedLocatorPreflight['centralDirectoryInventory'] ?? null) !== null
        || !in_array('zip64-end-of-central-directory-record-missing', $rawStrictZip64MalformedLocatorPreflight['diagnostics'] ?? [], true)
        || !in_array('zip64-end-of-central-directory-locator-target-not-record', $rawStrictZip64MalformedLocatorPreflight['diagnostics'] ?? [], true)
        || !in_array('zip-package-instantiation-failed', $rawStrictZip64MalformedLocatorPreflight['diagnostics'] ?? [], true)
    ) {
        throw new RuntimeException('Expected raw strict ZIP import preflight to reject malformed ZIP64 locator bytes');
    }

    if (
        ($zip64EocdMismatchAccounting['eocdFieldsMatchZip64Record'] ?? null) !== false
        || ($zip64EocdMismatchAccounting['eocdZip64MismatchedFields'] ?? []) !== ['diskEntryCount', 'totalEntryCount']
        || !in_array('zip64-eocd-field-mismatch', $zip64EocdMismatchAccounting['issues'] ?? [], true)
        || !in_array('zip64-eocd-field-mismatch', $rawStrictZip64EocdMismatchPreflight['diagnostics'] ?? [], true)
        || ($rawStrictZip64EocdMismatchPreflight['zip64EndOfCentralDirectory']['eocdZip64MismatchedFields'] ?? []) !== ['diskEntryCount', 'totalEntryCount']
    ) {
        throw new RuntimeException('Expected raw strict ZIP import preflight to report ZIP64 EOCD field mismatches');
    }

    if (
        ($zip64SmallRecordAccounting['recordPayloadSize'] ?? null) !== 40
        || ($zip64SmallRecordAccounting['recordEndsAtLocator'] ?? null) !== false
        || !in_array('zip64-end-of-central-directory-record-too-small', $zip64SmallRecordAccounting['issues'] ?? [], true)
        || !in_array('zip64-end-of-central-directory-record-gap-before-locator', $zip64SmallRecordAccounting['issues'] ?? [], true)
        || !in_array('zip64-end-of-central-directory-record-too-small', $rawStrictZip64SmallRecordPreflight['diagnostics'] ?? [], true)
        || !in_array('zip64-end-of-central-directory-record-gap-before-locator', $rawStrictZip64SmallRecordPreflight['diagnostics'] ?? [], true)
    ) {
        throw new RuntimeException('Expected raw strict ZIP import preflight to report ZIP64 EOCD payload-size policy diagnostics');
    }

    if (($package->localNames()[0] ?? null) !== '[Content_Types].xml') {
        throw new RuntimeException('Expected local ZIP entry order to be inspectable for package preflight');
    }

    if (($package->localEntries()[0]->localHeaderOffset ?? -1) !== 0) {
        throw new RuntimeException('Expected first local ZIP entry to start at package offset zero');
    }

    if (
        ($packageLocalHeaderPreflight['entryCount'] ?? null) !== 3
        || ($packageLocalHeaderPreflight['firstLocalEntryName'] ?? null) !== '[Content_Types].xml'
        || ($packageLocalHeaderPreflight['entries'][0]['localHeaderOffset'] ?? null) !== 0
        || ($packageLocalHeaderPreflight['entries'][0]['isContiguousWithNext'] ?? null) !== true
        || ($packageLocalHeaderPreflight['entries'][2]['recordEnd'] ?? null) !== ($packageLocalHeaderPreflight['centralDirectoryOffset'] ?? null)
    ) {
        throw new RuntimeException('Expected ZIP local header spans to be inspectable for package preflight');
    }

    if (($strictImportPreflight['localHeaders']['entryCount'] ?? null) !== 3) {
        throw new RuntimeException('Expected strict ZIP import preflight to include local header spans');
    }

    if (
        ($packageLocalHeaderOrderPreflight['hasCentralDirectoryOrderMismatch'] ?? null) !== false
        || ($packageLocalHeaderOrderPreflight['mismatchedEntryCount'] ?? null) !== 0
        || ($packageLocalHeaderOrderPreflight['centralDirectoryOrderNames'] ?? []) !== $package->names()
        || ($packageLocalHeaderOrderPreflight['localHeaderOrderNames'] ?? []) !== $package->localNames()
        || ($strictCommentImportPreflight['localHeaderOrder']['entryCount'] ?? null) !== 3
    ) {
        throw new RuntimeException('Expected generated ZIP package central-directory order to match local header order');
    }

    if (
        ($localHeaderOrderReviewPreflight['hasCentralDirectoryOrderMismatch'] ?? null) !== true
        || ($localHeaderOrderReviewPreflight['mismatchedEntryCount'] ?? null) !== 3
        || ($localHeaderOrderReviewPreflight['centralDirectoryOrderNames'] ?? []) !== ['content.xml', 'styles.xml', 'mimetype']
        || ($localHeaderOrderReviewPreflight['localHeaderOrderNames'] ?? []) !== ['mimetype', 'content.xml', 'styles.xml']
        || ($localHeaderOrderReviewPreflight['entries'][0]['name'] ?? null) !== 'content.xml'
        || ($localHeaderOrderReviewPreflight['entries'][0]['localHeaderOrder'] ?? null) !== 1
        || ($localHeaderOrderReviewPreflight['entries'][0]['localHeaderNameAtCentralDirectoryIndex'] ?? null) !== 'mimetype'
        || ($localHeaderOrderReviewPreflight['entries'][0]['centralDirectoryNameAtLocalHeaderOrder'] ?? null) !== 'styles.xml'
        || ($localHeaderOrderReviewStrictPreflight['isValid'] ?? null) !== true
        || ($localHeaderOrderReviewStrictPreflight['localHeaderOrder'] ?? null) !== $localHeaderOrderReviewPreflight
    ) {
        throw new RuntimeException('Expected ZIP central-directory order mismatches to remain valid but visible for review');
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

    if (
        ($packageModificationTimePreflight['entryCount'] ?? null) !== 3
        || ($packageModificationTimePreflight['timestampEntryCount'] ?? null) !== 1
        || ($packageModificationTimePreflight['invalidDosTimestampEntryCount'] ?? null) !== 0
        || ($packageModificationTimePreflight['entries'][2]['timestampSource'] ?? null) !== 'extended-timestamp'
    ) {
        throw new RuntimeException('Expected ZIP modification-time preflight to accept generated package timestamps');
    }

    if ($package->assertValidModificationTimes() !== $packageModificationTimePreflight) {
        throw new RuntimeException('Expected ZIP modification-time assertion to return the accepted summary');
    }

    if (
        !$invalidDosTimestampRejected
        || !$invalidDosTimestampStrictRejected
        || ($invalidDosTimestampPreflight['invalidDosTimestampEntryCount'] ?? null) !== 1
        || ($invalidDosTimestampPreflight['invalidDosTimestampEntries'][0]['name'] ?? null) !== 'word/media/bad-date.txt'
        || ($invalidDosTimestampStrictPreflight['diagnostics'] ?? null) !== ['invalid-modification-times']
    ) {
        throw new RuntimeException('Expected invalid DOS ZIP modification timestamps to stay blocked before media import');
    }

    if ($invalidDosTimestampPackage->read('/word/media/bad-date.txt') !== "invalid DOS timestamp metadata should be reviewed before media import\n") {
        throw new RuntimeException('Expected invalid DOS timestamp media bytes to remain readable before strict handoff rejection');
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

    if (
        ($descriptorDataDescriptorPreflight['descriptorEntryCount'] ?? null) !== 1
        || ($descriptorDataDescriptorPreflight['signedDescriptorEntryCount'] ?? null) !== 1
        || ($descriptorDataDescriptorPreflight['descriptorEntries'][0]['name'] ?? null) !== 'word/comments.xml'
        || ($descriptorDataDescriptorPreflight['descriptorEntries'][0]['hasSignature'] ?? null) !== true
        || ($descriptorDataDescriptorPreflight['descriptorEntries'][0]['descriptorLength'] ?? null) !== 16
        || ($descriptorDataDescriptorPreflight['descriptorEntries'][0]['descriptorSpan'] ?? null) !== 16
        || ($descriptorDataDescriptorPreflight['descriptorEntries'][0]['surplusDescriptorBytes'] ?? null) !== 0
        || ($descriptorDataDescriptorPreflight['descriptorEntries'][0]['hasZeroLocalHeaderPlaceholders'] ?? null) !== true
    ) {
        throw new RuntimeException('Expected ZIP data descriptor metadata to be inspectable before comments import');
    }

    if (
        !$descriptorSlackRejected
        || ($descriptorSlackPreflight['descriptorEntryCount'] ?? null) !== 1
        || ($descriptorSlackPreflight['mismatchedDescriptorEntryCount'] ?? null) !== 1
        || ($descriptorSlackPreflight['descriptorEntries'][0]['issues'] ?? null) !== ['data-descriptor-length-mismatch']
        || ($descriptorSlackPreflight['descriptorEntries'][0]['surplusDescriptorBytes'] ?? null) !== strlen('hidden-descriptor-tail')
        || !in_array('data-descriptor-length-mismatch', $descriptorSlackRawStrictPreflight['diagnostics'] ?? [], true)
    ) {
        throw new RuntimeException('Expected ZIP descriptor boundary slack to stay blocked with byte-range preflight metadata');
    }

    if (!$descriptorPlaceholderRejected) {
        throw new RuntimeException('Expected nonzero ZIP data descriptor local header placeholders to stay blocked before package import');
    }

    if (!$zip64DataDescriptorRejected) {
        throw new RuntimeException('Expected ZIP64-sized data descriptors to stay blocked before package import');
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

    if (!$ntfsReservedRejected) {
        throw new RuntimeException('Expected nonzero ZIP NTFS reserved bytes to be rejected before media import');
    }

    if (GzipStream::decode($compressedPackage) !== $package->bytes()) {
        throw new RuntimeException('Expected gzip-wrapped ZIP package bytes to round-trip');
    }

    if ($compressedPackageDetectedFormat !== ArchiveCompressionStream::FORMAT_GZIP_ZIP) {
        throw new RuntimeException('Expected archive stream detection to classify the gzip-wrapped ZIP package');
    }

    if ($compressedPackageDetectedKind !== ArchiveCompressionStream::PACKAGE_KIND_ZIP) {
        throw new RuntimeException('Expected generic archive stream detection to classify the gzip-wrapped ZIP package kind');
    }

    if (($compressedPackageGenericInspection['kind'] ?? null) !== ArchiveCompressionStream::PACKAGE_KIND_ZIP) {
        throw new RuntimeException('Expected generic archive stream inspection to expose the ZIP package kind');
    }

    if (($compressedPackageGenericInspection['entryNames'] ?? []) !== $package->names()) {
        throw new RuntimeException('Expected generic archive stream inspection to preserve ZIP package entry names');
    }

    if (($compressedPackageInspection['entryNames'] ?? []) !== $package->names()) {
        throw new RuntimeException('Expected gzip-wrapped ZIP package inspection to preserve entry names');
    }

    if (($compressedPackageInspection['stream']['memberCount'] ?? 0) !== 1) {
        throw new RuntimeException('Expected gzip-wrapped ZIP package inspection to expose one gzip member');
    }

    if ($compressedPackageRoundTrip->read('/word/document.xml') !== $package->read('/word/document.xml')) {
        throw new RuntimeException('Expected gzip-wrapped ZIP package dispatch to expose document bytes');
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

    $textHintGzipMember = $textHintGzipTarInspection['stream']['members'][0] ?? [];
    if (($textHintGzipMember['textHint'] ?? null) !== true) {
        throw new RuntimeException('Expected gzip FTEXT review flag to be inspectable');
    }

    if ((($textHintGzipMember['flags'] ?? 0) & 0x01) !== 0x01) {
        throw new RuntimeException('Expected gzip raw header flags to preserve FTEXT provenance');
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

    if ($streamDetectedTarKind !== ArchiveCompressionStream::PACKAGE_KIND_TAR) {
        throw new RuntimeException('Expected generic archive stream detection to classify the gzip-wrapped tar packet kind');
    }

    if (($streamGenericTarInspection['kind'] ?? null) !== ArchiveCompressionStream::PACKAGE_KIND_TAR) {
        throw new RuntimeException('Expected generic archive stream inspection to expose the TAR packet kind');
    }

    if ($streamGenericTarInspection['archive']->read('/packet/manifest.json') !== '{"source":"wordpress-import","container":"tar"}') {
        throw new RuntimeException('Expected generic archive stream inspection to preserve TAR packet bytes');
    }

    if ($streamDecodedTarBytes !== $tarPacket->bytes()) {
        throw new RuntimeException('Expected archive stream auto-detection to return the tar packet bytes');
    }

    if (($paddedGzipTarInspection['stream']['trailingPaddingBytes'] ?? null) !== 12) {
        throw new RuntimeException('Expected gzip tar packet inspection to expose NUL trailer padding bytes');
    }

    if (($paddedGzipTarInspection['stream']['uncompressedSize'] ?? null) !== strlen($tarPacketBytes)) {
        throw new RuntimeException('Expected padded gzip tar packet inspection to preserve decoded tar size');
    }

    if ($paddedGzipTarInspection['archive']->read('/packet/manifest.json') !== '{"source":"wordpress-import","container":"tar"}') {
        throw new RuntimeException('Expected NUL-padded gzip tar packet manifest bytes to round-trip');
    }

    if (!$gzipNonZeroTrailerRejected) {
        throw new RuntimeException('Expected gzip tar packet with nonzero trailer bytes to be rejected before import');
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

    if ($paxEntry->accessedAt !== $documentModifiedAt + 12 || $paxEntry->changedAt !== $documentModifiedAt + 13) {
        throw new RuntimeException('Expected PAX access and change timestamps to be visible for review packets');
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

    if (
        ($zip64ExtraPreflight['zip64Entries'][0]['issues'] ?? []) !== [
            'zip64-extra-field',
            'zip64-extra-field-without-sentinel',
            'zip64-extra-field-trailing-bytes',
        ]
    ) {
        throw new RuntimeException('Expected unneeded ZIP64 extra-field metadata to remain explainable before rejection');
    }

    if (
        !$zip64SizeUpgradeRejected
        || ($zip64SizeUpgradePreflight['requiresZip64EntryCount'] ?? null) !== 1
        || ($zip64SizeUpgradePreflight['centralZip64ExtraFieldEntryCount'] ?? null) !== 1
        || ($zip64SizeUpgradePreflight['zip64Entries'][0]['centralZip64RequiredFields'] ?? []) !== [
            'uncompressedSize',
            'compressedSize',
            'localHeaderOffset',
            'diskStart',
        ]
        || ($zip64SizeUpgradePreflight['zip64Entries'][0]['centralZip64Values']['localHeaderOffset'] ?? null) !== 0
    ) {
        throw new RuntimeException('Expected ZIP64 size-upgrade extra fields to be planned and rejected before media import');
    }

    if (
        ($zip64LocalHeaderMismatchPreflight['mismatchedLocalHeaderEntryCount'] ?? null) !== 1
        || ($zip64LocalHeaderMismatchPreflight['zip64Entries'][0]['localHeaderOffsetSource'] ?? null) !== 'zip64-extra-field'
        || ($zip64LocalHeaderMismatchPreflight['zip64Entries'][0]['localName'] ?? null) !== 'word/media/spoofed-local-header.bin'
        || ($zip64LocalHeaderMismatchPreflight['zip64Entries'][0]['rawNameMatchesLocalHeader'] ?? null) !== false
        || ($zip64LocalHeaderMismatchPreflight['zip64Entries'][0]['decodedNameMatchesLocalHeader'] ?? null) !== false
        || !in_array('zip64-local-header-name-mismatch', $zip64LocalHeaderMismatchPreflight['issues'] ?? [], true)
        || !in_array('zip64-local-header-decoded-name-mismatch', $zip64LocalHeaderMismatchPreflight['issues'] ?? [], true)
        || !in_array('zip64-local-header-name-mismatch', $zip64LocalHeaderMismatchRawStrict['diagnostics'] ?? [], true)
    ) {
        throw new RuntimeException('Expected ZIP64 local-header offset spoofing to stay visible before package import');
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

    if (!$directoryCrcRejected) {
        throw new RuntimeException('Expected ZIP directory entries with nonzero CRC32 metadata to be rejected before media import');
    }

    if (!$dosDirectoryAttributeMismatchRejected) {
        throw new RuntimeException('Expected ZIP non-directory names with directory attributes to be rejected before media import');
    }

    if (!$unixFileTypeNameMismatchRejected) {
        throw new RuntimeException('Expected ZIP Unix file-type metadata to match entry name shape before media import');
    }

    if (
        ($rawExternalAttributePolicy['issueEntryCount'] ?? null) !== 1
        || ($rawExternalAttributePolicy['unixFileTypeMismatchEntryCount'] ?? null) !== 1
        || ($rawExternalAttributePolicy['issueEntries'][0]['name'] ?? null) !== 'word/media/'
        || ($rawExternalAttributePolicy['issueEntries'][0]['policy'] ?? null) !== 'blocked'
        || !in_array('unix-file-type-name-mismatch', $rawExternalAttributePolicy['issues'] ?? [], true)
        || ($rawExternalAttributeStrictPreflight['canInstantiate'] ?? true) !== false
        || !in_array('unix-file-type-name-mismatch', $rawExternalAttributeStrictPreflight['diagnostics'] ?? [], true)
    ) {
        throw new RuntimeException('Expected raw ZIP external-attribute policy diagnostics before package instantiation');
    }

    if (!$localEntryOverlapRejected) {
        throw new RuntimeException('Expected ZIP local entry overlap to be rejected before media import');
    }

    if (!$duplicateLocalOffsetRejected) {
        throw new RuntimeException('Expected duplicate ZIP local header offsets to be rejected before media import');
    }

    if (
        !$duplicateCentralDirectoryNameRejected
        || ($duplicateCentralDirectoryNameInventory['hasDuplicateEntryNames'] ?? null) !== true
        || ($duplicateCentralDirectoryNameInventory['duplicateEntryNameGroupCount'] ?? null) !== 1
        || ($duplicateCentralDirectoryNameInventory['duplicateEntryNameEntryCount'] ?? null) !== 2
        || ($duplicateCentralDirectoryNameInventory['duplicateEntryNameGroups'][0]['name'] ?? null) !== 'word/media/review.txt'
        || ($duplicateCentralDirectoryNameInventory['duplicateEntryNameGroups'][0]['centralDirectoryIndexes'] ?? null) !== [0, 1]
        || ($duplicateCentralDirectoryNameInventory['isSupportedByBoundedReader'] ?? null) !== false
        || !in_array('duplicate-central-directory-entry-names', $duplicateCentralDirectoryNameInventory['issues'] ?? [], true)
        || ($duplicateCentralDirectoryNameRawStrictPreflight['canInstantiate'] ?? true) !== false
        || !in_array('duplicate-central-directory-entry-names', $duplicateCentralDirectoryNameRawStrictPreflight['diagnostics'] ?? [], true)
    ) {
        throw new RuntimeException('Expected duplicate ZIP central-directory names to stay blocked before media import');
    }

    if (!$centralDirectorySignatureParsed) {
        throw new RuntimeException('Expected ZIP central-directory digital signature metadata to be inspectable before media import');
    }

    if (($centralDirectorySignaturePreflight['signatureLength'] ?? null) !== strlen('central-signature')) {
        throw new RuntimeException('Expected ZIP central-directory digital signature length to be preserved for review');
    }

    if (
        !$centralDirectorySignatureStrictRejected
        || ($centralDirectorySignatureStrictPreflight['diagnostics'] ?? null) !== ['central-directory-signature-unverified']
    ) {
        throw new RuntimeException('Expected unverified ZIP central-directory digital signatures to fail strict native import preflight');
    }

    if (!$strongEncryptionRejected) {
        throw new RuntimeException('Expected ZIP strong-encryption metadata to be rejected before media import');
    }

    if (!$centralDirectoryEncryptionRejected) {
        throw new RuntimeException('Expected ZIP central-directory encryption metadata to be rejected before media import');
    }

    if (
        !$traditionalEncryptedRejected
        || ($traditionalEncryptionPreflight['encryptedEntryCount'] ?? null) !== 1
        || ($traditionalEncryptionPreflight['traditionalEncryptionEntryCount'] ?? null) !== 1
        || ($traditionalEncryptedEntry['traditionalEncryptionHeaderLength'] ?? null) !== 12
        || ($traditionalEncryptedEntry['traditionalEncryptionHeaderAvailableBytes'] ?? null) !== 12
        || ($traditionalEncryptedEntry['traditionalEncryptionPayloadSize'] ?? null) <= 0
        || ($traditionalEncryptedEntry['compressedSizeIncludesTraditionalEncryptionHeader'] ?? null) !== true
    ) {
        throw new RuntimeException('Expected traditional ZIP encryption layout to be blocked and exposed for review');
    }

    if (
        ($truncatedTraditionalEncryptionPreflight['truncatedTraditionalEncryptionHeaderEntryCount'] ?? null) !== 1
        || ($truncatedTraditionalEncryptedEntry['hasTruncatedTraditionalEncryptionHeader'] ?? null) !== true
        || !array_key_exists('traditionalEncryptionPayloadOffset', $truncatedTraditionalEncryptedEntry)
        || $truncatedTraditionalEncryptedEntry['traditionalEncryptionPayloadOffset'] !== null
    ) {
        throw new RuntimeException('Expected truncated traditional ZIP encryption headers to remain blocked and visible in preflight');
    }

    if (!$compressedPatchedDataRejected) {
        throw new RuntimeException('Expected ZIP compressed-patched data metadata to be rejected before media import');
    }

    if (!$aesExtraFieldRejected) {
        throw new RuntimeException('Expected ZIP AES extra field metadata to be rejected before media import');
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

    if (!$understatedVersionNeededRejected) {
        throw new RuntimeException('Expected understated ZIP version-needed metadata to be rejected before media import');
    }

    if (($understatedVersionNeededPreflight['understatedVersionEntryCount'] ?? 0) !== 1) {
        throw new RuntimeException('Expected ZIP compression policy preflight to count understated version-needed entries');
    }

    if (($understatedVersionNeededPreflight['understatedVersionEntries'][0]['minimumVersionNeededToExtract'] ?? null) !== 20) {
        throw new RuntimeException('Expected ZIP compression policy preflight to expose the minimum extraction version');
    }

    if (!$localHeaderNameMismatchRejected) {
        throw new RuntimeException('Expected ZIP local header name mismatches to be rejected before media import');
    }

    if (!$unsafeLocalHeaderNameRejected) {
        throw new RuntimeException('Expected unsafe ZIP local header names to be rejected before media import');
    }

    if (
        ($rawStrictLocalHeaderNamePreflight['isValid'] ?? null) !== false
        || ($rawStrictLocalHeaderNamePreflight['canInstantiate'] ?? null) !== false
        || ($rawStrictLocalHeaderNamePreflight['localHeaderNames']['mismatchedEntryCount'] ?? null) !== 1
        || ($rawStrictLocalHeaderNamePreflight['localHeaderNames']['mismatchedEntries'][0]['centralName'] ?? null) !== 'word/document.xml'
        || ($rawStrictLocalHeaderNamePreflight['localHeaderNames']['mismatchedEntries'][0]['localName'] ?? null) !== 'word/other.xml'
        || !in_array('local-header-name-issues', $rawStrictLocalHeaderNamePreflight['diagnostics'] ?? [], true)
        || !in_array('zip-package-instantiation-failed', $rawStrictLocalHeaderNamePreflight['diagnostics'] ?? [], true)
    ) {
        throw new RuntimeException('Expected raw strict ZIP preflight to report local header name mismatches');
    }

    if (
        ($rawStrictUnsafeLocalHeaderNamePreflight['isValid'] ?? null) !== false
        || ($rawStrictUnsafeLocalHeaderNamePreflight['canInstantiate'] ?? null) !== false
        || ($rawStrictUnsafeLocalHeaderNamePreflight['localHeaderNames']['mismatchedEntryCount'] ?? null) !== 1
        || ($rawStrictUnsafeLocalHeaderNamePreflight['localHeaderNames']['mismatchedEntries'][0]['centralName'] ?? null) !== 'word/media/review.png'
        || ($rawStrictUnsafeLocalHeaderNamePreflight['localHeaderNames']['mismatchedEntries'][0]['localRawNameSafetyIssues'] ?? null) !== ['parent-directory-segment']
        || !in_array('local-header-unsafe-raw-name', $rawStrictUnsafeLocalHeaderNamePreflight['diagnostics'] ?? [], true)
        || !in_array('local-header-raw-name-parent-directory-segment', $rawStrictUnsafeLocalHeaderNamePreflight['diagnostics'] ?? [], true)
        || !in_array('zip-package-instantiation-failed', $rawStrictUnsafeLocalHeaderNamePreflight['diagnostics'] ?? [], true)
    ) {
        throw new RuntimeException('Expected raw strict ZIP preflight to report unsafe local header names');
    }

    if (
        ($rawStrictLocalHeaderSpanPreflight['isValid'] ?? null) !== false
        || ($rawStrictLocalHeaderSpanPreflight['canInstantiate'] ?? null) !== false
        || ($rawStrictLocalHeaderSpanPreflight['localHeaderSpans']['issueEntryCount'] ?? null) !== 1
        || ($rawStrictLocalHeaderSpanPreflight['localHeaderSpans']['issueEntries'][0]['unclaimedBytesStartWithLocalHeader'] ?? null) !== true
        || ($rawStrictLocalHeaderSpanPreflight['localHeaderSpans']['issueEntries'][0]['unclaimedBytesSignature'] ?? null) !== 'local-file-header'
        || ($rawStrictLocalHeaderSpanPreflight['localHeaderSpans']['issueEntries'][0]['unclaimedBytesPreviewByteCount'] ?? null) !== 16
        || !in_array('local-header-span-issues', $rawStrictLocalHeaderSpanPreflight['diagnostics'] ?? [], true)
        || !in_array('local-entry-unclaimed-bytes', $rawStrictLocalHeaderSpanPreflight['diagnostics'] ?? [], true)
        || !in_array('zip-package-instantiation-failed', $rawStrictLocalHeaderSpanPreflight['diagnostics'] ?? [], true)
    ) {
        throw new RuntimeException('Expected raw strict ZIP preflight to report unclaimed local header spans');
    }

    if (
        ($rawStrictLocalHeaderOffsetPreflight['isValid'] ?? null) !== false
        || ($rawStrictLocalHeaderOffsetPreflight['canInstantiate'] ?? null) !== false
        || ($rawStrictLocalHeaderOffsetPreflight['localHeaderSpans']['issueEntryCount'] ?? null) !== 1
        || ($rawStrictLocalHeaderOffsetPreflight['localHeaderSpans']['issueEntries'][0]['localHeaderOffsetLocation'] ?? null) !== 'inside-central-directory'
        || ($rawStrictLocalHeaderOffsetPreflight['localHeaderSpans']['issueEntries'][0]['localHeaderAvailable'] ?? null) !== false
        || !in_array('local-header-span-issues', $rawStrictLocalHeaderOffsetPreflight['diagnostics'] ?? [], true)
        || !in_array('local-header-offset-inside-central-directory', $rawStrictLocalHeaderOffsetPreflight['diagnostics'] ?? [], true)
        || !in_array('zip-package-instantiation-failed', $rawStrictLocalHeaderOffsetPreflight['diagnostics'] ?? [], true)
    ) {
        throw new RuntimeException('Expected raw strict ZIP preflight to report central-directory local header offsets');
    }

    if (
        ($eocdCentralDirectoryOffsetPreflight['centralDirectoryOffset'] ?? null) !== 0
        || ($eocdCentralDirectoryOffsetPreflight['centralDirectoryStartSignature'] ?? null) !== 'local-file-header'
        || ($eocdCentralDirectoryOffsetPreflight['centralDirectoryOffsetLocation'] ?? null) !== 'local-file-header'
        || ($eocdCentralDirectoryOffsetPreflight['centralDirectoryRangeStartsWithCentralHeader'] ?? null) !== false
        || ($eocdCentralDirectoryOffsetPreflight['centralDirectoryEndMatchesEocdOffset'] ?? null) !== false
        || ($eocdCentralDirectoryOffsetPreflight['issues'] ?? null) !== ['central-directory-offset-not-central-header']
        || ($rawStrictEocdCentralDirectoryOffsetPreflight['endOfCentralDirectoryOffset'] ?? null) !== $eocdCentralDirectoryOffsetPreflight
        || ($rawStrictEocdCentralDirectoryOffsetPreflight['canInstantiate'] ?? null) !== false
        || !in_array('central-directory-offset-not-central-header', $rawStrictEocdCentralDirectoryOffsetPreflight['diagnostics'] ?? [], true)
        || !in_array('zip-package-instantiation-failed', $rawStrictEocdCentralDirectoryOffsetPreflight['diagnostics'] ?? [], true)
    ) {
        throw new RuntimeException('Expected raw strict ZIP import preflight to report EOCD central-directory offsets before package instantiation');
    }

    if (!$localEntrySlackRejected) {
        throw new RuntimeException('Expected hidden ZIP local entry bytes to be rejected before media import');
    }

    if (!$executablePermissionRejected) {
        throw new RuntimeException('Expected Unix executable ZIP media entries to be rejected before media import');
    }

    if (
        !$writablePermissionRejected
        || !$writablePermissionStrictRejected
        || ($writablePermissionPreflight['groupWritableEntryCount'] ?? null) !== 3
        || ($writablePermissionPreflight['worldWritableEntryCount'] ?? null) !== 2
        || ($writablePermissionPreflight['writablePermissionEntryCount'] ?? null) !== 3
        || ($writablePermissionPreflight['writablePermissionEntries'][0]['name'] ?? null) !== 'word/media/group-writable.txt'
        || ($writablePermissionPreflight['writablePermissionEntries'][1]['issues'] ?? null) !== [
            'unix-group-writable-permission',
            'unix-world-writable-permission',
        ]
        || ($writablePermissionStrictPreflight['diagnostics'] ?? null) !== ['unix-writable-permission-entries']
    ) {
        throw new RuntimeException('Expected Unix group/world-writable ZIP permissions to be rejected before media import');
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

    if (!$tarDuplicatePaxKeywordRejected) {
        throw new RuntimeException('Expected TAR duplicate PAX keyword metadata to be rejected before import');
    }

    if (!$tarPaxMtimeOverflowRejected) {
        throw new RuntimeException('Expected TAR PAX mtime overflow metadata to be rejected before import');
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

    if (!$tarGnuLongNameTerminatorRejected) {
        throw new RuntimeException('Expected TAR GNU long names without NUL terminators to be rejected before import');
    }

    if (!$tarOwnerUtf8Rejected) {
        throw new RuntimeException('Expected TAR owner metadata with invalid UTF-8 to be rejected before import');
    }

    if (!$tarPaxReviewMetadataUtf8Rejected) {
        throw new RuntimeException('Expected TAR PAX review metadata with invalid UTF-8 to be rejected before import');
    }

    if (!$unixSpecialFileRejected) {
        throw new RuntimeException('Expected ZIP Unix special file entries to be rejected before media import');
    }

    $unicodePathEntry = $unicodePathPackage->entry('/' . $unicodePathName);
    if ($unicodePathEntry->rawName !== $unicodePathRawName) {
        throw new RuntimeException('Expected ZIP Unicode path extra field to preserve raw legacy path bytes');
    }

    if ($unicodePathEntry->nameEncoding !== 'info-zip-unicode-path') {
        throw new RuntimeException('Expected ZIP Unicode path extra field to provide decoded media path');
    }

    if (
        !$unicodePathRawNameProvenanceRejected
        || ($unicodePathRawNamePreflight['provenanceEntryCount'] ?? null) !== 1
        || ($unicodePathRawNamePreflight['unicodePathExtraEntryCount'] ?? null) !== 1
        || ($unicodePathRawNamePreflight['decodedNameDiffersFromRawNameEntryCount'] ?? null) !== 1
        || ($unicodePathRawNamePreflight['provenanceEntries'][0]['rawName'] ?? null) !== $unicodePathRawName
        || ($unicodePathRawNamePreflight['provenanceEntries'][0]['name'] ?? null) !== $unicodePathName
        || ($unicodePathRawNamePreflight['provenanceEntries'][0]['issues'] ?? null) !== [
            'raw-name-decoded-value-differs',
            'raw-name-info-zip-unicode-path',
        ]
    ) {
        throw new RuntimeException('Expected ZIP raw-name provenance preflight to expose Unicode path metadata before media import');
    }

    if ($unicodePathEntry->comment !== $unicodeComment || $unicodePathEntry->commentEncoding !== 'info-zip-unicode-comment') {
        throw new RuntimeException('Expected ZIP Unicode comment extra field to provide decoded media review comment');
    }

    if ($unicodePathPackage->read('/' . $unicodePathName) !== "Unicode media attachment placeholder\n") {
        throw new RuntimeException('Expected Unicode path media attachment bytes to round-trip');
    }

    if (!$centralUnicodePathMissingLocalRejected) {
        throw new RuntimeException('Expected central-only ZIP Unicode path metadata to be rejected before media import');
    }

    if (
        ($unicodeExtraFieldPolicyPreflight['isSupportedByBoundedReader'] ?? null) !== false
        || ($unicodeExtraFieldPolicyPreflight['entryCount'] ?? null) !== 1
        || ($unicodeExtraFieldPolicyPreflight['centralUnicodePathEntryCount'] ?? null) !== 1
        || ($unicodeExtraFieldPolicyPreflight['localUnicodePathEntryCount'] ?? null) !== 0
        || ($unicodeExtraFieldPolicyPreflight['issues'] ?? null) !== ['unicode-path-local-extra-field-missing']
        || ($unicodeExtraFieldRawStrictPreflight['unicodeExtraFields'] ?? null) !== $unicodeExtraFieldPolicyPreflight
        || !in_array('unicode-extra-field-issues', $unicodeExtraFieldRawStrictPreflight['diagnostics'] ?? [], true)
        || !in_array('unicode-path-local-extra-field-missing', $unicodeExtraFieldRawStrictPreflight['diagnostics'] ?? [], true)
        || !in_array('zip-package-instantiation-failed', $unicodeExtraFieldRawStrictPreflight['diagnostics'] ?? [], true)
    ) {
        throw new RuntimeException('Expected raw strict ZIP preflight to expose malformed Unicode extra-field policy before instantiation');
    }

    if (!$utf8UnicodePathMismatchRejected) {
        throw new RuntimeException('Expected contradictory UTF-8 ZIP path metadata to be rejected before media import');
    }

    if (!$utf8UnicodeCommentMismatchRejected) {
        throw new RuntimeException('Expected contradictory UTF-8 ZIP comment metadata to be rejected before media import');
    }

    if (!$emptyUnicodeCommentRejected) {
        throw new RuntimeException('Expected empty ZIP Unicode comment metadata to be rejected before hiding raw comments');
    }

    if (!$duplicateUnicodeExtraRejected) {
        throw new RuntimeException('Expected duplicate ZIP Unicode path metadata to be rejected before media import');
    }

    if (!$malformedExtendedTimestampRejected) {
        throw new RuntimeException('Expected malformed ZIP extended timestamp metadata to be rejected before media import');
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
echo 'odtMimetypeStoredFirst=' . ($odtMimetypePreflight['isValid'] ? 'yes' : 'no') . "\n";
echo 'odtMimetypeLocalFirst=' . ($odtMimetypePreflight['firstLocalEntryName'] ?? 'none') . "\n";
echo 'zipStoredFirstMimetypeExtraFieldPolicy=' . ($odtMimetypeExtraFieldRejected ? 'rejected' : 'not-rejected') . "\n";
echo 'zipStoredFirstMimetypeDescriptorPolicy=' . ($odtMimetypeDescriptorRejected ? 'rejected' : 'not-rejected') . "\n";
echo 'packageLocalHeaders.entryCount=' . $packageLocalHeaderPreflight['entryCount'] . "\n";
echo 'packageLocalHeaders.firstLocalEntry=' . ($packageLocalHeaderPreflight['firstLocalEntryName'] ?? 'none') . "\n";
echo 'packageLocalHeaders.lastRecordEnd=' . ($packageLocalHeaderPreflight['entries'][2]['recordEnd'] ?? 'none') . "\n";
echo 'packageLocalHeaderOrder.central=' . implode(',', $packageLocalHeaderOrderPreflight['centralDirectoryOrderNames']) . "\n";
echo 'packageLocalHeaderOrder.local=' . implode(',', $packageLocalHeaderOrderPreflight['localHeaderOrderNames']) . "\n";
echo 'packageLocalHeaderOrder.mismatchedEntryCount=' . $packageLocalHeaderOrderPreflight['mismatchedEntryCount'] . "\n";
echo 'zipLocalHeaderOrderReview.central=' . implode(',', $localHeaderOrderReviewPreflight['centralDirectoryOrderNames']) . "\n";
echo 'zipLocalHeaderOrderReview.local=' . implode(',', $localHeaderOrderReviewPreflight['localHeaderOrderNames']) . "\n";
echo 'zipLocalHeaderOrderReview.mismatchedEntryCount=' . $localHeaderOrderReviewPreflight['mismatchedEntryCount'] . "\n";
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
echo 'packageReadIntegrity.readableEntryCount=' . $packageReadIntegrityPreflight['readableEntryCount'] . "\n";
echo 'packageReadIntegrity.failedEntryCount=' . $packageReadIntegrityPreflight['failedEntryCount'] . "\n";
echo 'packageModificationTimes.timestampEntryCount=' . $packageModificationTimePreflight['timestampEntryCount'] . "\n";
echo 'packageModificationTimes.invalidDosTimestampEntryCount=' . $packageModificationTimePreflight['invalidDosTimestampEntryCount'] . "\n";
echo 'zipStrictImportPolicy=' . ($strictImportPreflight['isValid'] ? 'accepted' : 'rejected') . "\n";
echo 'zipStrictImportDiagnostics=' . implode(',', $strictImportPreflight['diagnostics']) . "\n";
echo 'zipStrictImportCentralDirectoryEntries=' . $strictImportPreflight['centralDirectoryInventory']['entryCount'] . "\n";
echo 'zipStrictImportCentralDirectorySupported=' . ($strictImportPreflight['centralDirectoryInventory']['isSupportedByBoundedReader'] ? 'true' : 'false') . "\n";
echo 'zipEntryHandoffPolicy=' . ($strictImportEntryHandoffPreflight['isSupportedByBoundedReader'] ? 'accepted' : 'rejected') . "\n";
echo 'zipEntryHandoffReadyEntries=' . $strictImportEntryHandoffPreflight['handoffEntryCount'] . "\n";
echo 'zipEntryHandoffMissingOptional=' . $strictImportEntryHandoffPreflight['missingOptionalEntryCount'] . "\n";
echo 'zipEntryHandoffDiagnostics=' . implode(',', $strictImportEntryHandoffPreflight['issues']) . "\n";
echo 'zipCentralDirectoryDeclaredLowKind=' . ($centralDirectoryDeclaredLowInventory['entryCountMismatchKind'] ?? 'none') . "\n";
echo 'zipCentralDirectoryDeclaredLowExtraScanned=' . $centralDirectoryDeclaredLowInventory['extraScannedEntryCount'] . "\n";
echo 'zipCentralDirectoryDeclaredHighKind=' . ($centralDirectoryDeclaredHighInventory['entryCountMismatchKind'] ?? 'none') . "\n";
echo 'zipCentralDirectoryDeclaredHighMissing=' . $centralDirectoryDeclaredHighInventory['missingDeclaredEntryCount'] . "\n";
echo 'zipCentralDirectoryGapBytes=' . $centralDirectoryGapInventory['centralDirectoryEocdGapBytes'] . "\n";
echo 'zipCentralDirectoryUnderstatedRecoverableEntries=' . $centralDirectoryUnderstatedInventory['recoverableGapEntryCount'] . "\n";
echo 'zipCentralDirectoryUnderstatedGapSignature=' . ($centralDirectoryUnderstatedInventory['centralDirectoryEocdGapSignature'] ?? 'none') . "\n";
echo 'zipCentralDirectoryUnderstatedRepairPolicy=' . $centralDirectoryUnderstatedRepairPlan['policy'] . "\n";
echo 'zipCentralDirectoryUnderstatedRepairPlannedEntries=' . $centralDirectoryUnderstatedRepairPlan['plannedEntryCount'] . "\n";
echo 'zipCentralDirectoryUnderstatedRepairRecoveredBytes=' . $centralDirectoryUnderstatedRepairPlan['recoveredGapBytes'] . "\n";
echo 'zipCentralDirectoryTailBytes=' . $centralDirectoryTailInventory['centralDirectoryTailBytes'] . "\n";
echo 'zipCentralDirectoryTailSignature=' . ($centralDirectoryTailInventory['unexpectedRecordSignatureHex'] ?? 'none') . "\n";
echo 'zipCentralDirectoryDuplicateOffsetGroups=' . $centralDirectoryDuplicateOffsetInventory['duplicateLocalHeaderOffsetGroupCount'] . "\n";
echo 'zipCentralDirectoryDuplicateOffsetEntries=' . $centralDirectoryDuplicateOffsetInventory['duplicateLocalHeaderOffsetEntryCount'] . "\n";
echo 'zipStrictImportNameHygieneReviewEntries=' . $strictImportPreflight['nameHygiene']['reviewEntryCount'] . "\n";
echo 'zipStrictImportPlatformMetadataEntries=' . $strictImportPreflight['platformMetadata']['platformMetadataEntryCount'] . "\n";
echo 'zipRawStrictImportPolicy=' . ($rawStrictImportPreflight['isValid'] ? 'accepted' : 'rejected') . "\n";
echo 'zipRawStrictImportCanInstantiate=' . ($rawStrictImportPreflight['canInstantiate'] ? 'true' : 'false') . "\n";
echo 'zipRawStrictImportDiagnostics=' . implode(',', $rawStrictImportPreflight['diagnostics']) . "\n";
echo 'zipRawStrictImportPreflightErrors=' . count($rawStrictImportPreflight['preflightErrors']) . "\n";
echo 'zipRawStrictDuplicateOffsetPolicy=' . ($centralDirectoryDuplicateOffsetRawStrictPreflight['isValid'] ? 'accepted' : 'rejected') . "\n";
echo 'zipRawStrictDuplicateOffsetDiagnostics=' . implode(',', $centralDirectoryDuplicateOffsetRawStrictPreflight['diagnostics']) . "\n";
echo 'zipRawStrictTrailingEocdPolicy=' . ($rawStrictTrailingEocdPreflight['isValid'] ? 'accepted' : 'rejected') . "\n";
echo 'zipRawStrictTrailingEocdBytes=' . $rawStrictTrailingEocdSummary['trailingByteCount'] . "\n";
echo 'zipRawStrictTrailingEocdDiagnostics=' . implode(',', $rawStrictTrailingEocdPreflight['diagnostics']) . "\n";
echo 'zipEmptyStrictImportPolicy=' . ($emptyStrictImportPreflight['isValid'] ? 'accepted' : 'rejected') . "\n";
echo 'zipEmptyStrictContentPresence=' . ($emptyStrictImportPreflight['contentPresence']['hasEntries'] ? 'has-entries' : 'empty') . "\n";
echo 'zipEmptyRawStrictImportPolicy=' . ($emptyRawStrictImportPreflight['isValid'] ? 'accepted' : 'rejected') . "\n";
echo 'zipEmptyRawStrictImportDiagnostics=' . implode(',', $emptyRawStrictImportPreflight['diagnostics']) . "\n";
echo 'zipRawStrictLocalHeaderNamePolicy=' . ($rawStrictLocalHeaderNamePreflight['isValid'] ? 'accepted' : 'rejected') . "\n";
echo 'zipRawStrictLocalHeaderNameIssues=' . implode(',', $rawStrictLocalHeaderNamePreflight['localHeaderNames']['issues'] ?? []) . "\n";
echo 'zipRawStrictLocalHeaderNameMismatchCount=' . ($rawStrictLocalHeaderNamePreflight['localHeaderNames']['mismatchedEntryCount'] ?? 0) . "\n";
echo 'zipRawStrictUnsafeLocalHeaderNamePolicy=' . ($rawStrictUnsafeLocalHeaderNamePreflight['isValid'] ? 'accepted' : 'rejected') . "\n";
echo 'zipRawStrictUnsafeLocalHeaderNameIssues=' . implode(',', $rawStrictUnsafeLocalHeaderNamePreflight['localHeaderNames']['issues'] ?? []) . "\n";
echo 'zipRawStrictLocalHeaderSpanPolicy=' . ($rawStrictLocalHeaderSpanPreflight['isValid'] ? 'accepted' : 'rejected') . "\n";
echo 'zipRawStrictLocalHeaderSpanIssues=' . implode(',', $rawStrictLocalHeaderSpanPreflight['localHeaderSpans']['issues'] ?? []) . "\n";
echo 'zipRawStrictLocalHeaderSpanUnclaimedBytes=' . ($rawStrictLocalHeaderSpanPreflight['localHeaderSpans']['issueEntries'][0]['unclaimedBytes'] ?? 0) . "\n";
echo 'zipRawStrictLocalHeaderSpanSignature=' . ($rawStrictLocalHeaderSpanPreflight['localHeaderSpans']['issueEntries'][0]['unclaimedBytesSignature'] ?? 'none') . "\n";
echo 'zipRawStrictLocalHeaderSpanPreviewHex=' . ($rawStrictLocalHeaderSpanPreflight['localHeaderSpans']['issueEntries'][0]['unclaimedBytesPreviewHex'] ?? '') . "\n";
echo 'zipRawStrictLocalHeaderOffsetPolicy=' . ($rawStrictLocalHeaderOffsetPreflight['isValid'] ? 'accepted' : 'rejected') . "\n";
echo 'zipRawStrictLocalHeaderOffsetIssues=' . implode(',', $rawStrictLocalHeaderOffsetPreflight['localHeaderSpans']['issues'] ?? []) . "\n";
echo 'zipRawStrictLocalHeaderOffsetLocation=' . ($rawStrictLocalHeaderOffsetPreflight['localHeaderSpans']['issueEntries'][0]['localHeaderOffsetLocation'] ?? 'none') . "\n";
echo 'zipEocdCentralDirectoryOffsetPolicy=' . ($eocdCentralDirectoryOffsetPreflight['isSupportedByBoundedReader'] ? 'accepted' : 'rejected') . "\n";
echo 'zipEocdCentralDirectoryOffsetSignature=' . ($eocdCentralDirectoryOffsetPreflight['centralDirectoryStartSignature'] ?? 'none') . "\n";
echo 'zipEocdCentralDirectoryOffsetIssues=' . implode(',', $eocdCentralDirectoryOffsetPreflight['issues']) . "\n";
echo 'zipRawStrictEocdCentralDirectoryOffsetDiagnostics=' . implode(',', $rawStrictEocdCentralDirectoryOffsetPreflight['diagnostics']) . "\n";
echo 'zipRawExternalAttributeIssueCount=' . $rawExternalAttributePolicy['issueEntryCount'] . "\n";
echo 'zipRawExternalAttributeDiagnostics=' . implode(',', $rawExternalAttributeStrictPreflight['diagnostics']) . "\n";
echo 'zipStrictImportCommentPolicy=' . ($strictCommentImportRejected ? 'rejected' : 'not-rejected') . "\n";
echo 'zipStrictImportCommentDiagnostics=' . implode(',', $strictCommentImportPreflight['diagnostics']) . "\n";
echo 'zipCommentControlBytePolicy=' . ($strictCommentControlImportRejected ? 'rejected' : 'not-rejected') . "\n";
echo 'zipCommentControlByteEntryCount=' . $strictCommentControlPreflight['commentControlByteEntryCount'] . "\n";
echo 'zipCommentControlByteDiagnostics=' . implode(',', $strictCommentControlImportPreflight['diagnostics']) . "\n";
echo 'zipCommentUnicodeControlPolicy=' . ($strictCommentUnicodeControlImportRejected ? 'rejected' : 'not-rejected') . "\n";
echo 'zipCommentUnicodeControlEntryCount=' . $strictCommentUnicodeControlPreflight['commentUnicodeFormatControlEntryCount'] . "\n";
echo 'zipCommentUnicodeControlDiagnostics=' . implode(',', $strictCommentUnicodeControlImportPreflight['diagnostics']) . "\n";
echo 'zipInvalidDosTimestampPolicy=' . ($invalidDosTimestampRejected ? 'rejected' : 'not-rejected') . "\n";
echo 'zipInvalidDosTimestampStrictPolicy=' . ($invalidDosTimestampStrictRejected ? 'rejected' : 'not-rejected') . "\n";
echo 'zipInvalidDosTimestampEntry=' . ($invalidDosTimestampPreflight['invalidDosTimestampEntries'][0]['name'] ?? 'none') . "\n";
echo 'zipTrailingDeflatePolicy=' . ($trailingDeflateRejected ? 'rejected' : 'not-rejected') . "\n";
echo 'zipTrailingDeflateError=' . ($trailingDeflatePreflight['failedEntries'][0]['error'] ?? 'none') . "\n";
echo 'packageExtraField.mismatchedEntryCount=' . $packageExtraFieldPreflight['mismatchedExtraFieldEntryCount'] . "\n";
echo 'packageExtraField.valueMismatchedEntryCount=' . $packageExtraFieldPreflight['mismatchedExtraFieldValueEntryCount'] . "\n";
echo 'packagePermissions.unixModeEntryCount=' . $packagePermissionPreflight['unixModeEntryCount'] . "\n";
echo 'packagePermissions.executableFileCount=' . $packagePermissionPreflight['executableFileCount'] . "\n";
echo 'packagePermissions.writablePermissionEntryCount=' . $packagePermissionPreflight['writablePermissionEntryCount'] . "\n";
echo 'packageDosAttributes.hiddenSystemOrVolumeLabelEntryCount=' . $packageDosAttributePreflight['hiddenSystemOrVolumeLabelEntryCount'] . "\n";
echo 'zipDosHiddenSystemVolumePolicy=' . ($hiddenDosAttributeRejected ? 'rejected' : 'not-rejected') . "\n";
echo 'zipDosHiddenSystemVolumeStrictPolicy=' . ($hiddenDosAttributeStrictRejected ? 'rejected' : 'not-rejected') . "\n";
echo 'zipDosHiddenSystemVolumeEntry=' . ($hiddenDosAttributePreflight['hiddenSystemOrVolumeLabelEntries'][0]['name'] ?? 'none') . "\n";
echo 'packageCreatorHosts=' . implode(',', array_map(static fn (array $host): string => $host['name'], $packageCreatorHostPreflight['hostSystems'])) . "\n";
echo 'packageCreatorUnknownEntries=' . $packageCreatorHostPreflight['unknownHostSystemEntryCount'] . "\n";
echo 'zipGeneratedCreatorHosts=' . implode(',', array_map(static fn (array $host): string => $host['name'], $generatedCreatorHostPreflight['hostSystems'])) . "\n";
echo 'zipGeneratedCreatorStrictPolicy=' . ($generatedCreatorHostStrictPreflight['isValid'] ? 'accepted' : 'rejected') . "\n";
echo 'zipGeneratedUnknownCreatorHostPolicy=' . ($generatedUnknownCreatorHostRejected ? 'rejected' : 'not-rejected') . "\n";
echo 'zipRawCreatorHostPolicy=' . ($unknownCreatorHostRawStrict['isValid'] ? 'accepted' : 'rejected') . "\n";
echo 'zipRawCreatorHostUnknownEntries=' . $unknownCreatorHostRawPolicy['unknownHostSystemEntryCount'] . "\n";
echo 'packageExtraFields.duplicateEntryCount=' . $packageExtraFieldPreflight['duplicateExtraFieldEntryCount'] . "\n";
echo 'packageUnixOwners.ownerMetadataEntryCount=' . $packageUnixOwnerPreflight['ownerMetadataEntryCount'] . "\n";
echo 'packagePathHierarchy.collisionEntryCount=' . $packagePathHierarchyPreflight['collisionEntryCount'] . "\n";
echo 'zipNameHygieneReviewPolicy=' . ($nameHygieneRejected && $nameHygieneStrictRejected ? 'rejected' : 'not-rejected') . "\n";
echo 'zipNameHygieneReviewEntries=' . $nameHygienePreflight['reviewEntryCount'] . "\n";
echo 'zipNameHygieneReviewIssues=' . implode(',', $nameHygienePreflight['reviewEntries'][0]['issues'] ?? []) . "\n";
echo 'zipNameHygieneWindowsReservedEntries=' . $nameHygienePreflight['windowsReservedNameEntryCount'] . "\n";
echo 'zipNameHygieneWindowsAdsEntries=' . $nameHygienePreflight['windowsAlternateDataStreamEntryCount'] . "\n";
echo 'zipNameHygieneUnicodeFormatEntries=' . $nameHygienePreflight['unicodeFormatControlEntryCount'] . "\n";
echo 'zipNameHygieneUnicodeBidiEntries=' . $nameHygienePreflight['unicodeBidiControlEntryCount'] . "\n";
echo 'packagePlatformMetadata.entryCount=' . $packagePlatformMetadataPreflight['platformMetadataEntryCount'] . "\n";
echo 'zipPlatformMetadataPolicy=' . ($platformMetadataRejected && $platformMetadataStrictRejected ? 'rejected' : 'not-rejected') . "\n";
echo 'zipPlatformMetadataEntries=' . $platformMetadataPreflight['platformMetadataEntryCount'] . "\n";
echo 'zipPlatformMetadataIssues=' . implode(',', $platformMetadataPreflight['platformMetadataEntries'][1]['issues'] ?? []) . "\n";
echo 'zipPlatformMetadataDiagnostics=' . implode(',', $platformMetadataStrictPreflight['diagnostics']) . "\n";
echo 'zipWindowsPlatformMetadataPolicy=' . ($windowsPlatformMetadataRejected && $windowsPlatformMetadataStrictRejected ? 'rejected' : 'not-rejected') . "\n";
echo 'zipWindowsPlatformMetadataEntries=' . $windowsPlatformMetadataPreflight['platformMetadataEntryCount'] . "\n";
echo 'zipWindowsPlatformMetadataSidecars=' . $windowsPlatformMetadataPreflight['windowsSidecarEntryCount'] . "\n";
echo 'zipWindowsPlatformMetadataIssues=' . implode(',', $windowsPlatformMetadataPreflight['platformMetadataEntries'][0]['issues'] ?? []) . "\n";
echo 'zipRawPlatformMetadataEntries=' . $rawPlatformMetadataPolicyPreflight['platformMetadataEntryCount'] . "\n";
echo 'zipRawPlatformMetadataStrictDiagnostics=' . implode(',', $rawPlatformMetadataStrictPreflight['diagnostics']) . "\n";
echo 'packageCaseInsensitiveNames.collisionEntryCount=' . $packageCaseInsensitiveNamePreflight['collisionEntryCount'] . "\n";
echo 'packageArchive.eocdOffset=' . $packageArchivePreflight['eocdOffset'] . "\n";
echo 'packageArchive.totalEntryCount=' . $packageArchivePreflight['totalEntryCount'] . "\n";
echo 'packageArchive.centralDirectorySize=' . $packageArchivePreflight['centralDirectorySize'] . "\n";
echo 'packageArchive.singleDisk=' . ($packageArchivePreflight['isSingleDisk'] ? 'true' : 'false') . "\n";
echo 'packageArchive.layoutSupported=' . ($packageArchivePreflight['isArchiveLayoutSupported'] ? 'true' : 'false') . "\n";
echo 'zipSplitArchivePolicy=' . ($splitZipArchiveRejected ? 'rejected' : 'not-rejected') . "\n";
echo 'zipSplitArchiveSingleDisk=' . ($splitZipArchivePreflight['isSingleDisk'] ? 'true' : 'false') . "\n";
echo 'zipSplitArchiveMarkerPolicy=' . ($splitZipDiskPreflight['hasSplitArchiveMarkers'] ? 'blocked' : 'supported') . "\n";
echo 'zipSplitArchiveEntryCount=' . $splitZipDiskPreflight['splitArchiveEntryCount'] . "\n";
echo 'zipSplitArchiveIssues=' . implode(',', $splitZipDiskPreflight['issues']) . "\n";
echo 'zipRawStrictSplitPolicy=' . ($rawStrictSplitZipPreflight['isValid'] ? 'accepted' : 'rejected') . "\n";
echo 'zipRawStrictSplitDiagnostics=' . implode(',', $rawStrictSplitZipPreflight['diagnostics']) . "\n";
echo 'zipPackagePrefixPolicy=' . ($prefixedZipRejected ? 'rejected' : 'not-rejected') . "\n";
echo 'zipPackagePrefixBytes=' . $prefixedZipPrefixPreflight['prefixByteCount'] . "\n";
echo 'zipPackagePrefixSignature=' . ($prefixedZipPrefixPreflight['prefixSignature'] ?? 'none') . "\n";
echo 'zipRawStrictPackagePrefixDiagnostics=' . implode(',', $rawStrictPrefixedZipPreflight['diagnostics']) . "\n";
echo 'zipArchiveExtraDataPolicy=' . ($archiveExtraDataRecordRejected ? 'rejected' : 'not-rejected') . "\n";
echo 'zipArchiveExtraDataRecordCount=' . $archiveExtraDataRecordPreflight['archiveExtraDataRecordCount'] . "\n";
echo 'zipArchiveExtraDataLocation=' . ($archiveExtraDataRecordPreflight['archiveExtraDataRecords'][0]['location'] ?? 'none') . "\n";
echo 'zipRawStrictArchiveExtraPolicy=' . ($rawStrictArchiveExtraDataRecordPreflight['isValid'] ? 'accepted' : 'rejected') . "\n";
echo 'zipRawStrictArchiveExtraDiagnostics=' . implode(',', $rawStrictArchiveExtraDataRecordPreflight['diagnostics']) . "\n";
echo 'zipInterEntryArchiveExtraDataPolicy=' . ($interEntryArchiveExtraDataRecordRejected ? 'rejected' : 'not-rejected') . "\n";
echo 'zipInterEntryArchiveExtraDataLocation=' . ($interEntryArchiveExtraDataRecordPreflight['archiveExtraDataRecords'][0]['location'] ?? 'none') . "\n";
echo 'zipRawStrictInterEntryArchiveExtraDiagnostics=' . implode(',', $rawStrictInterEntryArchiveExtraDataRecordPreflight['diagnostics']) . "\n";
echo 'zip64EocdPolicy=' . ($zip64EocdRejected ? 'rejected' : 'not-rejected') . "\n";
echo 'zip64EocdRequiresZip64=' . ($zip64EocdPreflight['requiresZip64'] ? 'true' : 'false') . "\n";
echo 'zipRawStrictZip64EocdPolicy=' . ($rawStrictZip64EocdPreflight['isValid'] ? 'accepted' : 'rejected') . "\n";
echo 'zipRawStrictZip64EocdDiagnostics=' . implode(',', $rawStrictZip64EocdPreflight['diagnostics']) . "\n";
echo 'zip64LocatorPolicy=' . ($zip64LocatorRejected ? 'rejected' : 'not-rejected') . "\n";
echo 'zip64LocatorDetected=' . ($zip64LocatorPreflight['hasZip64EndOfCentralDirectoryLocator'] ? 'true' : 'false') . "\n";
echo 'zip64LocatorRecordSize=' . ($zip64LocatorPreflight['zip64EndOfCentralDirectorySize'] ?? 'none') . "\n";
echo 'zipRawStrictZip64LocatorPolicy=' . ($rawStrictZip64LocatorPreflight['isValid'] ? 'accepted' : 'rejected') . "\n";
echo 'zipRawStrictZip64LocatorDiagnostics=' . implode(',', $rawStrictZip64LocatorPreflight['diagnostics']) . "\n";
echo 'zip64MalformedLocatorPolicy=' . ($zip64MalformedLocatorRejected ? 'rejected' : 'not-rejected') . "\n";
echo 'zip64MalformedLocatorTargetSignature=' . ($zip64MalformedLocatorAccounting['recordSignature'] ?? 'none') . "\n";
echo 'zip64MalformedLocatorTargetSignatureHex=' . ($zip64MalformedLocatorAccounting['recordSignatureHex'] ?? 'none') . "\n";
echo 'zipRawStrictZip64MalformedLocatorDiagnostics=' . implode(',', $rawStrictZip64MalformedLocatorPreflight['diagnostics']) . "\n";
echo 'zip64EocdMismatchFields=' . implode(',', $zip64EocdMismatchAccounting['eocdZip64MismatchedFields']) . "\n";
echo 'zipRawStrictZip64EocdMismatchDiagnostics=' . implode(',', $rawStrictZip64EocdMismatchPreflight['diagnostics']) . "\n";
echo 'zip64SmallRecordIssues=' . implode(',', $zip64SmallRecordAccounting['issues']) . "\n";
echo 'zipRawStrictZip64SmallRecordDiagnostics=' . implode(',', $rawStrictZip64SmallRecordPreflight['diagnostics']) . "\n";
echo 'descriptor.comments.xml=' . $descriptorPackage->read('/word/comments.xml') . "\n";
echo 'descriptor.entryCount=' . $descriptorDataDescriptorPreflight['descriptorEntryCount'] . "\n";
echo 'descriptor.signedEntryCount=' . $descriptorDataDescriptorPreflight['signedDescriptorEntryCount'] . "\n";
echo 'descriptor.comments.xml.length=' . ($descriptorDataDescriptorPreflight['descriptorEntries'][0]['descriptorLength'] ?? 'none') . "\n";
echo 'descriptor.comments.xml.span=' . ($descriptorDataDescriptorPreflight['descriptorEntries'][0]['descriptorSpan'] ?? 'none') . "\n";
echo 'descriptor.zeroLocalHeaderPlaceholders=' . (($descriptorDataDescriptorPreflight['descriptorEntries'][0]['hasZeroLocalHeaderPlaceholders'] ?? false) ? 'true' : 'false') . "\n";
echo 'descriptorSlackPolicy=' . ($descriptorSlackRejected ? 'rejected' : 'not-rejected') . "\n";
echo 'descriptorSlackIssues=' . implode(',', $descriptorSlackPreflight['issues']) . "\n";
echo 'descriptorSlackSurplusBytes=' . ($descriptorSlackPreflight['descriptorEntries'][0]['surplusDescriptorBytes'] ?? 'none') . "\n";
echo 'zipDescriptorPlaceholderPolicy=' . ($descriptorPlaceholderRejected ? 'rejected' : 'not-rejected') . "\n";
echo 'zip64DescriptorPolicy=' . ($zip64DataDescriptorRejected ? 'rejected' : 'not-rejected') . "\n";
$ntfsTimestamps = $ntfsPackage->entry('/word/media/review.png')->ntfsTimestamps();
echo 'ntfs.review.png.modifiedAt=' . ($ntfsTimestamps['modifiedAt'] ?? 'none') . "\n";
echo 'ntfs.review.png.localModifiedAt=' . ($ntfsPackage->localNtfsLastModifiedTimestamp('/word/media/review.png') ?? 'none') . "\n";
echo 'zipNtfsReservedPolicy=' . ($ntfsReservedRejected ? 'rejected' : 'not-rejected') . "\n";
$extendedTimestamps = $extendedTimestampPackage->localExtendedTimestamps('/word/media/reviewer-note.txt') ?? [];
echo 'extended.reviewer-note.modifiedAt=' . ($extendedTimestamps['modifiedAt'] ?? 'none') . "\n";
echo 'extended.reviewer-note.accessedAt=' . ($extendedTimestamps['accessedAt'] ?? 'none') . "\n";
echo 'extended.reviewer-note.createdAt=' . ($extendedTimestamps['createdAt'] ?? 'none') . "\n";
echo 'symlinkPolicy=' . ($symlinkRejected ? 'rejected' : 'not-rejected') . "\n";
echo 'zipUnixSpecialFilePolicy=' . ($unixSpecialFileRejected ? 'rejected' : 'not-rejected') . "\n";
echo 'zip64Policy=' . ($zip64Rejected ? 'rejected' : 'not-rejected') . "\n";
echo 'zip64ExtraFieldEntries=' . $zip64ExtraPreflight['zip64ExtraFieldEntryCount'] . "\n";
echo 'zip64ExtraFieldIssues=' . implode(',', $zip64ExtraPreflight['zip64Entries'][0]['issues'] ?? []) . "\n";
echo 'zip64SizeUpgradePolicy=' . ($zip64SizeUpgradeRejected ? 'rejected' : 'not-rejected') . "\n";
echo 'zip64SizeUpgradeFields=' . implode(',', $zip64SizeUpgradePreflight['zip64Entries'][0]['centralZip64RequiredFields'] ?? []) . "\n";
echo 'zip64SizeUpgradeLocalOffset=' . ($zip64SizeUpgradePreflight['zip64Entries'][0]['centralZip64Values']['localHeaderOffset'] ?? 'none') . "\n";
echo 'zip64LocalHeaderMismatchCount=' . $zip64LocalHeaderMismatchPreflight['mismatchedLocalHeaderEntryCount'] . "\n";
echo 'zip64LocalHeaderMismatchIssues=' . implode(',', $zip64LocalHeaderMismatchPreflight['issues'] ?? []) . "\n";
echo 'driveLetterPathPolicy=' . ($driveLetterRejected ? 'rejected' : 'not-rejected') . "\n";
echo 'rawUnicodePathPolicy=' . ($rawUnicodeTraversalRejected ? 'rejected' : 'not-rejected') . "\n";
echo 'zipControlNamePolicy=' . ($zipControlNameRejected ? 'rejected' : 'not-rejected') . "\n";
echo 'directoryPayloadPolicy=' . ($directoryPayloadRejected ? 'rejected' : 'not-rejected') . "\n";
echo 'zipDirectoryCrcPolicy=' . ($directoryCrcRejected ? 'rejected' : 'not-rejected') . "\n";
echo 'zipDosDirectoryAttributePolicy=' . ($dosDirectoryAttributeMismatchRejected ? 'rejected' : 'not-rejected') . "\n";
echo 'zipUnixFileTypeNamePolicy=' . ($unixFileTypeNameMismatchRejected ? 'rejected' : 'not-rejected') . "\n";
echo 'zipLocalEntryOverlapPolicy=' . ($localEntryOverlapRejected ? 'rejected' : 'not-rejected') . "\n";
echo 'zipDuplicateLocalOffsetPolicy=' . ($duplicateLocalOffsetRejected ? 'rejected' : 'not-rejected') . "\n";
echo 'zipDuplicateCentralDirectoryNamePolicy=' . ($duplicateCentralDirectoryNameRejected ? 'rejected' : 'not-rejected') . "\n";
echo 'zipDuplicateCentralDirectoryNameGroups=' . $duplicateCentralDirectoryNameInventory['duplicateEntryNameGroupCount'] . "\n";
echo 'zipDuplicateCentralDirectoryNameRawStrictDiagnostics=' . implode(',', $duplicateCentralDirectoryNameRawStrictPreflight['diagnostics']) . "\n";
echo 'zipCentralDirectorySignaturePolicy=' . ($centralDirectorySignatureParsed ? 'inspectable' : 'not-inspectable') . "\n";
echo 'zipCentralDirectorySignatureLength=' . $centralDirectorySignaturePreflight['signatureLength'] . "\n";
echo 'zipCentralDirectorySignatureVerification=' . $centralDirectorySignaturePreflight['cryptographicVerification'] . "\n";
echo 'zipCentralDirectorySignatureStrictPolicy=' . ($centralDirectorySignatureStrictRejected ? 'rejected' : 'not-rejected') . "\n";
echo 'zipCentralDirectorySignatureStrictDiagnostics=' . implode(',', $centralDirectorySignatureStrictPreflight['diagnostics']) . "\n";
echo 'strongEncryptionPolicy=' . ($strongEncryptionRejected ? 'rejected' : 'not-rejected') . "\n";
echo 'centralDirectoryEncryptionPolicy=' . ($centralDirectoryEncryptionRejected ? 'rejected' : 'not-rejected') . "\n";
echo 'traditionalEncryptionPolicy=' . ($traditionalEncryptedRejected ? $traditionalEncryptionPreflight['extractionPolicy'] : 'not-rejected') . "\n";
echo 'traditionalEncryptionHeaderBytes=' . ($traditionalEncryptedEntry['traditionalEncryptionHeaderAvailableBytes'] ?? 0) . "\n";
echo 'traditionalEncryptionPayloadBytes=' . ($traditionalEncryptedEntry['traditionalEncryptionPayloadSize'] ?? 0) . "\n";
echo 'traditionalEncryptionTruncatedHeaders=' . $truncatedTraditionalEncryptionPreflight['truncatedTraditionalEncryptionHeaderEntryCount'] . "\n";
echo 'compressedPatchedDataPolicy=' . ($compressedPatchedDataRejected ? 'rejected' : 'not-rejected') . "\n";
echo 'zipAesExtraFieldPolicy=' . ($aesExtraFieldRejected ? 'rejected' : 'not-rejected') . "\n";
echo 'zipUnsupportedCompressionMethodPolicy=' . ($unsupportedCompressionMethodRejected ? 'rejected' : 'not-rejected') . "\n";
echo 'zipUnsupportedCompressionMethodEntry=' . ($unsupportedCompressionMethodPreflight['unsupportedEntries'][0]['name'] ?? 'none') . "\n";
echo 'zipUnknownCreatorHostPolicy=' . ($unknownCreatorHostRejected ? 'rejected' : 'not-rejected') . "\n";
echo 'zipUnknownCreatorHostEntry=' . ($unknownCreatorHostPreflight['unknownEntries'][0]['name'] ?? 'none') . "\n";
echo 'zipGeneratedDuplicateExtraFieldPolicy=' . ($generatedDuplicateExtraFieldRejected ? 'rejected' : 'not-rejected') . "\n";
echo 'zipGeneratedUnixOwnerExtraFieldPolicy=' . ($generatedUnixOwnerExtraFieldRejected ? 'rejected' : 'not-rejected') . "\n";
echo 'zipUnixOwnerExtraFieldPolicy=' . ($unixOwnerRejected && $unixOwnerStrictRejected ? 'rejected' : 'not-rejected') . "\n";
echo 'zipUnixOwnerExtraFieldEntry=' . ($unixOwnerPreflight['ownerMetadataEntries'][0]['name'] ?? 'none') . "\n";
echo 'zipUnixOwnerExtraFieldUidGid='
    . ($unixOwnerPreflight['ownerMetadataEntries'][0]['centralOwner']['uid'] ?? 'none')
    . ':'
    . ($unixOwnerPreflight['ownerMetadataEntries'][0]['centralOwner']['gid'] ?? 'none')
    . "\n";
echo 'zipDuplicateExtraFieldPolicy=' . ($duplicateExtraFieldRejected ? 'rejected' : 'not-rejected') . "\n";
echo 'zipDuplicateExtraFieldEntry=' . ($duplicateExtraFieldPreflight['duplicateEntries'][0]['name'] ?? 'none') . "\n";
echo 'zipRawExtraFieldPolicyIssues=' . implode(',', $rawExtraFieldPolicyPreflight['issues']) . "\n";
echo 'zipRawExtraFieldStrictDiagnostics=' . implode(',', $rawExtraFieldPolicyStrictPreflight['diagnostics']) . "\n";
echo 'zipExtraFieldIdMismatchPolicy=' . ($extraFieldIdMismatchRejected ? 'rejected' : 'not-rejected') . "\n";
echo 'zipExtraFieldIdMismatchEntry=' . ($extraFieldIdMismatchPreflight['mismatchedEntries'][0]['name'] ?? 'none') . "\n";
echo 'zipExtraFieldValueMismatchPolicy=' . ($extraFieldValueMismatchRejected ? 'rejected' : 'not-rejected') . "\n";
echo 'zipExtraFieldValueMismatchEntry=' . ($extraFieldValueMismatchPreflight['valueMismatchedEntries'][0]['name'] ?? 'none') . "\n";
echo 'zipExtraFieldStructureIssueEntries=' . $malformedExtraFieldStructurePreflight['issueEntryCount'] . "\n";
echo 'zipRawExtraFieldStructureIssues=' . implode(',', $malformedExtraFieldStructureRawStrictPreflight['extraFieldStructure']['issues'] ?? []) . "\n";
echo 'zipPathHierarchyCollisionPolicy=' . ($pathHierarchyCollisionRejected ? 'rejected' : 'not-rejected') . "\n";
echo 'zipPathHierarchyCollisionEntry=' . ($pathHierarchyCollisionPreflight['collisionEntries'][0]['name'] ?? 'none') . "\n";
echo 'zipCaseInsensitiveNameCollisionPolicy=' . ($caseInsensitiveNameCollisionRejected ? 'rejected' : 'not-rejected') . "\n";
echo 'zipCaseInsensitiveNameStrictPolicy=' . ($caseInsensitiveNameCollisionStrictRejected ? 'rejected' : 'not-rejected') . "\n";
echo 'zipCaseInsensitiveNameCollisionEntry=' . ($caseInsensitiveNameCollisionPreflight['collisionEntries'][0]['name'] ?? 'none') . "\n";
echo 'zipCaseInsensitiveNameCollisionKey=' . ($caseInsensitiveNameCollisionPreflight['collisionGroups'][0]['caseFoldKey'] ?? 'none') . "\n";
echo 'zipUnicodeNameCollisionPolicy=' . ($unicodeNameCollisionRejected ? 'rejected' : 'not-rejected') . "\n";
echo 'zipUnicodeNameCollisionStrictPolicy=' . ($unicodeNameCollisionStrictRejected ? 'rejected' : 'not-rejected') . "\n";
echo 'zipUnicodeNameCollisionEntry=' . ($unicodeNameCollisionPreflight['collisionEntries'][0]['name'] ?? 'none') . "\n";
echo 'zipUnicodeNameCollisionKey=' . ($unicodeNameCollisionPreflight['collisionGroups'][0]['caseFoldKey'] ?? 'none') . "\n";
echo 'zipRawCentralNameCollisionPolicy=' . ($rawNameCollisionPolicyPreflight['isSupportedByBoundedReader'] ? 'accepted' : 'rejected') . "\n";
echo 'zipRawCentralNameCollisionIssues=' . implode(',', $rawNameCollisionPolicyPreflight['issues']) . "\n";
echo 'zipRawCentralNameCollisionCaseEntry=' . ($rawNameCollisionPolicyPreflight['caseInsensitiveNameCollisionEntries'][0]['name'] ?? 'none') . "\n";
echo 'zipRawCentralNameCollisionRawEntry=' . ($rawNameCollisionPolicyPreflight['rawNameCollisionEntries'][0]['name'] ?? 'none') . "\n";
echo 'zipDeflateOptionFlagPolicy=' . ($deflateOptionFlagsRejected ? 'rejected' : 'not-rejected') . "\n";
echo 'zipGeneralPurposeFlagReviewPolicy=' . ($strictGeneralPurposeFlagReviewRejected ? 'rejected' : 'not-rejected') . "\n";
echo 'zipGeneralPurposeFlagStrictPolicy=' . ($strictGeneralPurposeFlagImportRejected ? 'rejected' : 'not-rejected') . "\n";
echo 'zipGeneralPurposeFlagReviewEntry=' . ($strictGeneralPurposeFlagPreflight['strictReviewEntries'][0]['name'] ?? 'none') . "\n";
echo 'zipGeneralPurposeFlagReviewIssues=' . implode(',', $strictGeneralPurposeFlagPreflight['strictReviewEntries'][0]['issues'] ?? []) . "\n";
echo 'zipStoredSizeMismatchPolicy=' . ($storedSizeMismatchRejected ? 'rejected' : 'not-rejected') . "\n";
echo 'zipPayloadIntegrityPolicy=' . ($corruptPayloadRejected ? 'rejected' : 'not-rejected') . "\n";
echo 'zipPayloadIntegrityFailedEntry=' . ($corruptPayloadPreflight['failedEntries'][0]['name'] ?? 'none') . "\n";
echo 'zipVersionNeededMismatchPolicy=' . ($versionNeededMismatchRejected ? 'rejected' : 'not-rejected') . "\n";
echo 'zipUnsupportedVersionNeededPolicy=' . ($unsupportedVersionNeededRejected ? 'rejected' : 'not-rejected') . "\n";
echo 'zipUnderstatedVersionNeededPolicy=' . ($understatedVersionNeededRejected ? 'rejected' : 'not-rejected') . "\n";
echo 'zipUnderstatedVersionNeededEntries=' . $understatedVersionNeededPreflight['understatedVersionEntryCount'] . "\n";
echo 'zipUnderstatedVersionNeededMinimum=' . ($understatedVersionNeededPreflight['understatedVersionEntries'][0]['minimumVersionNeededToExtract'] ?? 'none') . "\n";
echo 'zipLocalHeaderNameMismatchPolicy=' . ($localHeaderNameMismatchRejected ? 'rejected' : 'not-rejected') . "\n";
echo 'zipUnsafeLocalHeaderNamePolicy=' . ($unsafeLocalHeaderNameRejected ? 'rejected' : 'not-rejected') . "\n";
echo 'zipLocalEntrySlackPolicy=' . ($localEntrySlackRejected ? 'rejected' : 'not-rejected') . "\n";
echo 'zipDuplicateUnicodeExtraPolicy=' . ($duplicateUnicodeExtraRejected ? 'rejected' : 'not-rejected') . "\n";
echo 'zipMalformedExtendedTimestampPolicy=' . ($malformedExtendedTimestampRejected ? 'rejected' : 'not-rejected') . "\n";
echo 'zipExecutablePermissionPolicy=' . ($executablePermissionRejected ? 'rejected' : 'not-rejected') . "\n";
echo 'zipWritablePermissionPolicy=' . ($writablePermissionRejected && $writablePermissionStrictRejected ? 'rejected' : 'not-rejected') . "\n";
echo 'zipWritablePermissionEntries=' . $writablePermissionPreflight['writablePermissionEntryCount'] . "\n";
echo 'zipWritablePermissionDiagnostics=' . implode(',', $writablePermissionStrictPreflight['diagnostics']) . "\n";
echo 'boundedReadPolicy=' . ($oversizedMediaRejected ? 'rejected' : 'not-rejected') . "\n";
echo 'tarEndMarkerPolicy=' . ($missingTarEndMarkerRejected ? 'rejected' : 'not-rejected') . "\n";
echo 'tarDanglingPaxPolicy=' . ($danglingPaxMetadataRejected ? 'rejected' : 'not-rejected') . "\n";
echo 'tarDriveLetterPolicy=' . ($tarDriveLetterRejected ? 'rejected' : 'not-rejected') . "\n";
echo 'tarSparsePolicy=' . ($tarSparseRejected ? 'rejected' : 'not-rejected') . "\n";
echo 'tarUstarVersionPolicy=' . ($tarUstarVersionRejected ? 'rejected' : 'not-rejected') . "\n";
echo 'tarGlobalPaxPerEntryPolicy=' . ($tarGlobalPaxPerEntryRejected ? 'rejected' : 'not-rejected') . "\n";
echo 'tarDuplicatePaxKeywordPolicy=' . ($tarDuplicatePaxKeywordRejected ? 'rejected' : 'not-rejected') . "\n";
echo 'tarPaxMtimeOverflowPolicy=' . ($tarPaxMtimeOverflowRejected ? 'rejected' : 'not-rejected') . "\n";
echo 'tarPaxLinkpathPolicy=' . ($tarPaxLinkpathRejected ? 'rejected' : 'not-rejected') . "\n";
echo 'tarGnuLongLinkPolicy=' . ($tarGnuLongLinkRejected ? 'rejected' : 'not-rejected') . "\n";
echo 'tarPaxUtf8PathPolicy=' . ($tarPaxUtf8PathRejected ? 'rejected' : 'not-rejected') . "\n";
echo 'tarUstarPathUtf8Policy=' . ($tarUstarPathUtf8Rejected ? 'rejected' : 'not-rejected') . "\n";
echo 'tarGnuLongNameUtf8Policy=' . ($tarGnuLongNameUtf8Rejected ? 'rejected' : 'not-rejected') . "\n";
echo 'tarGnuLongNameTerminatorPolicy=' . ($tarGnuLongNameTerminatorRejected ? 'rejected' : 'not-rejected') . "\n";
echo 'tarOwnerUtf8Policy=' . ($tarOwnerUtf8Rejected ? 'rejected' : 'not-rejected') . "\n";
echo 'tarPaxReviewMetadataUtf8Policy=' . ($tarPaxReviewMetadataUtf8Rejected ? 'rejected' : 'not-rejected') . "\n";
$unicodePathEntry = $unicodePathPackage->entry('/' . $unicodePathName);
echo 'unicodePath.name=' . $unicodePathEntry->name . "\n";
echo 'unicodePath.rawName=' . $unicodePathEntry->rawName . "\n";
echo 'unicodePath.encoding=' . $unicodePathEntry->nameEncoding . "\n";
echo 'unicodePath.rawNameProvenanceEntries=' . $unicodePathRawNamePreflight['provenanceEntryCount'] . "\n";
echo 'unicodePath.rawNameProvenanceIssues=' . implode(',', $unicodePathRawNamePreflight['provenanceEntries'][0]['issues'] ?? []) . "\n";
echo 'unicodePath.rawNameProvenancePolicy=' . ($unicodePathRawNameProvenanceRejected ? 'rejected' : 'not-rejected') . "\n";
echo 'unicodePath.comment=' . $unicodePathEntry->comment . "\n";
echo 'unicodeExtraFieldPolicy=' . ($unicodeExtraFieldPolicyPreflight['isSupportedByBoundedReader'] ? 'accepted' : 'rejected') . "\n";
echo 'unicodeExtraFieldIssues=' . implode(',', $unicodeExtraFieldPolicyPreflight['issues']) . "\n";
echo 'unicodeExtraFieldRawStrictDiagnostics=' . implode(',', $unicodeExtraFieldRawStrictPreflight['diagnostics']) . "\n";
echo 'zipCentralUnicodePathMissingLocalPolicy=' . ($centralUnicodePathMissingLocalRejected ? 'rejected' : 'not-rejected') . "\n";
echo 'zipUtf8UnicodePathMismatchPolicy=' . ($utf8UnicodePathMismatchRejected ? 'rejected' : 'not-rejected') . "\n";
echo 'zipUtf8UnicodeCommentMismatchPolicy=' . ($utf8UnicodeCommentMismatchRejected ? 'rejected' : 'not-rejected') . "\n";
echo 'zipEmptyUnicodeCommentPolicy=' . ($emptyUnicodeCommentRejected ? 'rejected' : 'not-rejected') . "\n";
echo 'gzip.filename=' . $compressedPackageMembers[0]['filename'] . "\n";
echo 'gzip.comment=' . $compressedPackageMembers[0]['comment'] . "\n";
echo 'gzip.zipDetectedFormat=' . $compressedPackageDetectedFormat . "\n";
echo 'gzip.zipDetectedKind=' . $compressedPackageDetectedKind . "\n";
echo 'gzip.zipGenericEntries=' . implode(',', $compressedPackageGenericInspection['entryNames']) . "\n";
echo 'gzip.zipEntries=' . implode(',', $compressedPackageInspection['entryNames']) . "\n";
echo 'gzip.zipDocument.xml=' . $compressedPackageRoundTrip->read('/word/document.xml') . "\n";
echo 'gzip.latin1FilenameText=' . $latin1GzipTarMembers[0]['filenameText'] . "\n";
echo 'gzip.latin1CommentText=' . $latin1GzipTarMembers[0]['commentText'] . "\n";
echo 'gzip.extraSubfields=' . implode(',', array_map(static fn (array $field): string => $field['identifier'], $compressedPackageMembers[0]['extraFields'])) . "\n";
echo 'gzip.compressedSize=' . $compressedPackageMembers[0]['compressedSize'] . "\n";
echo 'gzip.trailingPaddingBytes=' . $paddedGzipTarInspection['stream']['trailingPaddingBytes'] . "\n";
echo 'gzip.nonZeroTrailerPolicy=' . ($gzipNonZeroTrailerRejected ? 'rejected' : 'not-rejected') . "\n";
echo 'gzip.textHint=' . (($textHintGzipTarInspection['stream']['members'][0]['textHint'] ?? false) ? 'true' : 'false') . "\n";
echo 'tar.entries=' . implode(',', $tarPacketRoundTrip->names()) . "\n";
echo 'tar.document.xml=' . $tarPacketRoundTrip->read('/packet/word/document.xml') . "\n";
echo 'tar.globalPaxComment=' . ($tarPacketRoundTrip->globalPaxHeaders()['comment'] ?? 'none') . "\n";
echo 'tar.detectedFormat=' . $streamDetectedTarFormat . "\n";
echo 'tar.detectedKind=' . $streamDetectedTarKind . "\n";
echo 'tar.genericEntries=' . implode(',', $streamGenericTarInspection['entryNames']) . "\n";
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
echo 'tar.paxAccessedAt=' . ($paxMetadataPacket->entry('/' . $paxDocumentName)->accessedAt ?? 'none') . "\n";
echo 'tar.paxChangedAt=' . ($paxMetadataPacket->entry('/' . $paxDocumentName)->changedAt ?? 'none') . "\n";
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
