<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\Pandoc\LegacyDocReader;
use PortLibs\Pandoc\WordPressBlockWriter;

$u16 = static fn (int $value): string => pack('v', $value);
$u32 = static fn (int $value): string => pack('V', $value);
$u64 = static fn (int $value): string => pack('V2', $value & 0xffffffff, intdiv($value, 4294967296));
$utf16le = static function (string $text): string {
    $encoded = iconv('UTF-8', 'UTF-16LE', $text);
    if (!is_string($encoded)) {
        throw new RuntimeException('Unable to encode UTF-16LE CFB fixture text');
    }

    return $encoded;
};
$padTo = static function (string $bytes, int $size): string {
    $remainder = strlen($bytes) % $size;

    return $remainder === 0 ? $bytes : $bytes . str_repeat("\0", $size - $remainder);
};
$directoryEntry = static function (
    string $name,
    int $type,
    int $startSector,
    int $size,
    int $leftSibling,
    int $rightSibling,
    int $child,
    int $colorFlag = 1
) use ($u16, $u32, $u64, $utf16le): string {
    $nameBytes = $utf16le($name . "\0");

    return str_pad($nameBytes, 64, "\0")
        . $u16(strlen($nameBytes))
        . chr($type)
        . chr($colorFlag)
        . $u32($leftSibling)
        . $u32($rightSibling)
        . $u32($child)
        . str_repeat("\0", 16)
        . $u32(0)
        . str_repeat("\0", 16)
        . $u32($startSector)
        . $u64($size);
};
$unallocatedDirectoryEntry = static function () use ($u32): string {
    return str_repeat("\0", 68)
        . $u32(0xffffffff)
        . $u32(0xffffffff)
        . $u32(0xffffffff)
        . str_repeat("\0", 48);
};
$buildWordDocument = static function (string $text): string {
    $textBytes = iconv('UTF-8', 'Windows-1252//TRANSLIT', $text);
    if (!is_string($textBytes)) {
        throw new RuntimeException('Unable to encode simple WordDocument fixture text');
    }

    $fib = str_repeat("\0", 512);
    $fib = substr_replace($fib, pack('v', 0xa5ec), 0, 2);
    $fib = substr_replace($fib, pack('v', 0x00c1), 2, 2);
    $fib = substr_replace($fib, pack('V', 512), 24, 4);
    $fib = substr_replace($fib, pack('V', 512 + strlen($textBytes)), 28, 4);

    return $fib . $textBytes;
};
$typedLpstr = static function (string $value): string {
    $bytes = $value . "\0";
    $raw = pack('v', 0x001e) . "\0\0" . pack('V', strlen($bytes)) . $bytes;

    return str_pad($raw, (int) (ceil(strlen($raw) / 4) * 4), "\0");
};
$typedI2 = static fn (int $value): string => pack('v', 0x0002) . "\0\0" . pack('v', $value) . "\0\0";
$typedThumbnail = static function (int $clipboardTag, ?int $formatId, string $data) use ($u32): string {
    $payload = $u32($clipboardTag);
    if ($clipboardTag !== 0) {
        $payload .= $u32($formatId ?? 0) . $data;
    }
    $raw = pack('v', 0x0047) . "\0\0" . $u32(strlen($payload)) . $payload;

    return str_pad($raw, (int) (ceil(strlen($raw) / 4) * 4), "\0");
};
$typedPropertySet = static function (array $properties) use ($u32, $typedI2): string {
    if (!array_key_exists(1, $properties)) {
        $properties = [1 => $typedI2(1252)] + $properties;
    }

    $count = count($properties);
    $valueOffset = 8 + ($count * 8);
    $directory = '';
    $payload = '';
    foreach ($properties as $id => $typedValue) {
        $directory .= $u32((int) $id) . $u32($valueOffset + strlen($payload));
        $payload .= $typedValue;
    }
    $set = $u32($valueOffset + strlen($payload)) . $u32($count) . $directory . $payload;

    return pack('v', 0xfffe)
        . pack('v', 0)
        . $u32(0)
        . str_repeat("\0", 16)
        . $u32(1)
        . str_repeat("\0", 16)
        . $u32(48)
        . $set;
};
$buildCfb = static function (string $wordDocument, string $summaryInformation) use ($u16, $u32, $directoryEntry, $unallocatedDirectoryEntry, $padTo): string {
    $sectorSize = 512;
    $miniSectorSize = 64;
    $free = 0xffffffff;
    $end = 0xfffffffe;
    $fatSector = 0xfffffffd;
    $streams = [
        'WordDocument' => $wordDocument,
        "\x05SummaryInformation" => $summaryInformation,
    ];

    $miniFat = [];
    $miniStream = '';
    $locations = [];
    foreach ($streams as $name => $data) {
        $firstMiniSector = intdiv(strlen($miniStream), $miniSectorSize);
        $sectorCount = max(1, intdiv(strlen($data) + $miniSectorSize - 1, $miniSectorSize));
        for ($index = 0; $index < $sectorCount; $index++) {
            $miniFat[$firstMiniSector + $index] = $index === $sectorCount - 1 ? $end : $firstMiniSector + $index + 1;
        }
        $miniStream .= $padTo($data, $miniSectorSize);
        $locations[$name] = [
            'startSector' => $firstMiniSector,
            'size' => strlen($data),
        ];
    }
    $miniStreamSize = strlen($miniStream);
    $miniStream = $padTo($miniStream, $sectorSize);

    $miniStreamChunks = str_split($miniStream, $sectorSize);
    $fat = [
        $fatSector,
        $end,
        $end,
    ];
    foreach ($miniStreamChunks as $index => $_chunk) {
        $sector = 3 + $index;
        $fat[$sector] = $index === count($miniStreamChunks) - 1 ? $end : $sector + 1;
    }
    $fatBytes = '';
    for ($index = 0; $index < 128; $index++) {
        $fatBytes .= $u32($fat[$index] ?? $free);
    }

    $miniFatBytes = '';
    for ($index = 0, $count = intdiv($sectorSize, 4); $index < $count; $index++) {
        $miniFatBytes .= $u32($miniFat[$index] ?? $free);
    }

    $directory = $directoryEntry('Root Entry', 5, 3, $miniStreamSize, $free, $free, 1)
        . $directoryEntry(
            'WordDocument',
            2,
            (int) $locations['WordDocument']['startSector'],
            (int) $locations['WordDocument']['size'],
            $free,
            2,
            $free
        )
        . $directoryEntry(
            "\x05SummaryInformation",
            2,
            (int) $locations["\x05SummaryInformation"]['startSector'],
            (int) $locations["\x05SummaryInformation"]['size'],
            $free,
            $free,
            $free,
            0
        );
    while (strlen($directory) < $sectorSize) {
        $directory .= $unallocatedDirectoryEntry();
    }

    $header = "\xd0\xcf\x11\xe0\xa1\xb1\x1a\xe1"
        . str_repeat("\0", 16)
        . $u16(0x003e)
        . $u16(3)
        . $u16(0xfffe)
        . $u16(9)
        . $u16(6)
        . str_repeat("\0", 6)
        . $u32(0)
        . $u32(1)
        . $u32(1)
        . $u32(0)
        . $u32(4096)
        . $u32(2)
        . $u32(1)
        . $u32($end)
        . $u32(0)
        . $u32(0)
        . str_repeat($u32($free), 108);

    return str_pad($header, $sectorSize, "\0")
        . substr($fatBytes, 0, $sectorSize)
        . $padTo($directory, $sectorSize)
        . substr($miniFatBytes, 0, $sectorSize)
        . implode('', $miniStreamChunks);
};

$thumbnailBytes = 'DIB legacy thumbnail review packet';
$summaryInformation = $typedPropertySet([
    2 => $typedLpstr('Legacy DOC thumbnail packet'),
    17 => $typedThumbnail(0xffffffff, 0x00000008, $thumbnailBytes),
]);
$docBytes = $buildCfb(
    $buildWordDocument("Thumbnail metadata review packet\r"),
    $summaryInformation
);
$result = (new LegacyDocReader())->readBytes($docBytes);
$blocks = (new WordPressBlockWriter())->write($result['document']);
$metadata = $result['metadata'];

if (($argv[1] ?? '') === '--self-test') {
    $thumbnail = $metadata['thumbnail'] ?? null;
    if (!is_array($thumbnail) || ($thumbnail['type'] ?? '') !== 'thumbnail' || ($thumbnail['source'] ?? '') !== 'SummaryInformation') {
        throw new RuntimeException('Legacy DOC thumbnail smoke missing SummaryInformation thumbnail metadata');
    }
    if (($thumbnail['clipboardTag'] ?? '') !== 'windows' || ($thumbnail['clipboardTagValue'] ?? null) !== 0xffffffff) {
        throw new RuntimeException('Legacy DOC thumbnail smoke missing clipboard tag metadata');
    }
    if (($thumbnail['formatId'] ?? null) !== 0x00000008 || ($thumbnail['format'] ?? '') !== 'dib') {
        throw new RuntimeException('Legacy DOC thumbnail smoke missing clipboard format metadata');
    }
    if (($thumbnail['byteCount'] ?? null) !== strlen($thumbnailBytes) || ($thumbnail['sha256'] ?? '') !== hash('sha256', $thumbnailBytes)) {
        throw new RuntimeException('Legacy DOC thumbnail smoke missing byte-count/hash metadata');
    }
    if (($thumbnail['canExposeBytes'] ?? null) !== false || ($thumbnail['extractionPolicy'] ?? '') !== 'metadata-only-native-review') {
        throw new RuntimeException('Legacy DOC thumbnail smoke missing metadata-only extraction policy');
    }
    if (
        ($metadata['thumbnailClipboardTag'] ?? '') !== 'windows'
        || ($metadata['thumbnailFormat'] ?? '') !== 'dib'
        || ($metadata['thumbnailByteCount'] ?? null) !== strlen($thumbnailBytes)
        || ($metadata['thumbnailPolicy'] ?? '') !== 'metadata-only-native-review'
        || ($metadata['thumbnailSha256'] ?? '') !== hash('sha256', $thumbnailBytes)
    ) {
        throw new RuntimeException('Legacy DOC thumbnail smoke missing flattened thumbnail metadata');
    }
    if (($result['document']->attr('meta')['thumbnail'] ?? null) !== $thumbnail) {
        throw new RuntimeException('Legacy DOC thumbnail smoke missing AST thumbnail metadata');
    }
    $metadataJson = json_encode($metadata, JSON_THROW_ON_ERROR);
    if (str_contains($metadataJson, 'DIB legacy thumbnail review packet') || str_contains($blocks, 'DIB legacy thumbnail review packet')) {
        throw new RuntimeException('Legacy DOC thumbnail smoke exposed raw thumbnail bytes');
    }

    $dirtyCodepagePadding = substr_replace($summaryInformation, $u16(0x0101), 48 + 8 + (3 * 8) + 6, 2);
    try {
        (new LegacyDocReader())->readBytes($buildCfb(
            $buildWordDocument("Malformed thumbnail metadata packet\r"),
            $dirtyCodepagePadding
        ));

        throw new RuntimeException('Legacy DOC thumbnail smoke accepted nonzero OLE codepage value padding');
    } catch (RuntimeException $exception) {
        if (!str_contains($exception->getMessage(), '16-bit value padding')) {
            throw $exception;
        }
    }

    echo "Legacy DOC thumbnail handoff self-test passed\n";
    exit(0);
}

echo $blocks;
echo "\n\n<!-- legacy-doc-thumbnail-metadata ";
echo json_encode([
    'title' => $metadata['title'] ?? null,
    'thumbnailClipboardTag' => $metadata['thumbnailClipboardTag'] ?? null,
    'thumbnailFormat' => $metadata['thumbnailFormat'] ?? null,
    'thumbnailByteCount' => $metadata['thumbnailByteCount'] ?? null,
    'thumbnailPolicy' => $metadata['thumbnailPolicy'] ?? null,
], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
echo " -->\n";
