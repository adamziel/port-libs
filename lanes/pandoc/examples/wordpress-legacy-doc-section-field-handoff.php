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
    int $child
) use ($u16, $u32, $u64, $utf16le): string {
    $nameBytes = $utf16le($name . "\0");

    return str_pad($nameBytes, 64, "\0")
        . $u16(strlen($nameBytes))
        . chr($type)
        . "\x01"
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
$wordDocument = static function (string $text) use ($u16, $u32, $padTo): string {
    $textBytes = iconv('UTF-8', 'Windows-1252//TRANSLIT', $text);
    if (!is_string($textBytes)) {
        throw new RuntimeException('Unable to encode simple WordDocument fixture text');
    }

    $fib = str_repeat("\0", 512);
    $fib = substr_replace($fib, $u16(0xa5ec), 0, 2);
    $fib = substr_replace($fib, $u16(0x00c1), 2, 2);
    $fib = substr_replace($fib, $u32(512), 24, 4);
    $fib = substr_replace($fib, $u32(512 + strlen($textBytes)), 28, 4);

    return $padTo($fib . $textBytes, 4096);
};
$buildCfb = static function (string $wordDocument) use ($u16, $u32, $directoryEntry, $unallocatedDirectoryEntry, $padTo): string {
    $sectorSize = 512;
    $free = 0xffffffff;
    $end = 0xfffffffe;
    $fatSector = 0xfffffffd;
    $wordDocumentSectors = str_split($wordDocument, $sectorSize);
    $wordDocumentStartSector = 2;
    $fat = [
        0 => $fatSector,
        1 => $end,
    ];
    foreach ($wordDocumentSectors as $index => $_sectorBytes) {
        $sector = $wordDocumentStartSector + $index;
        $fat[$sector] = $index === count($wordDocumentSectors) - 1 ? $end : $sector + 1;
    }

    $fatBytes = '';
    for ($index = 0; $index < 128; $index++) {
        $fatBytes .= $u32($fat[$index] ?? $free);
    }

    $directory = $directoryEntry('Root Entry', 5, $end, 0, $free, $free, 1)
        . $directoryEntry('WordDocument', 2, $wordDocumentStartSector, strlen($wordDocument), $free, $free, $free);
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
        . $u32($end)
        . $u32(0)
        . $u32($end)
        . $u32(0)
        . $u32(0)
        . str_repeat($u32($free), 108);

    return str_pad($header, $sectorSize, "\0")
        . substr($fatBytes, 0, $sectorSize)
        . $padTo($directory, $sectorSize)
        . implode('', $wordDocumentSectors);
};

$fieldBegin = "\x13";
$fieldSeparator = "\x14";
$fieldEnd = "\x15";
$docBytes = $buildCfb($wordDocument(
    'Section '
    . $fieldBegin . ' SECTION \* Arabic ' . $fieldSeparator . '3' . $fieldEnd
    . ' of '
    . $fieldBegin . ' SECTIONPAGES \* Arabic ' . $fieldSeparator . '4' . $fieldEnd
    . " ready for WordPress review.\r"
));
$result = (new LegacyDocReader())->readBytes($docBytes);
$blocks = (new WordPressBlockWriter())->write($result['document']);
$summary = [
    'metadata' => $result['metadata'],
    'blocks' => $blocks,
];

if (($argv[1] ?? '') === '--self-test') {
    foreach ([
        'data-legacy-doc-field="section"',
        'data-legacy-doc-field-instruction="SECTION \* Arabic"',
        '>3</span>',
        'data-legacy-doc-field="sectionpages"',
        'data-legacy-doc-field-instruction="SECTIONPAGES \* Arabic"',
        '>4</span>',
    ] as $needle) {
        if (!str_contains($blocks, $needle)) {
            throw new RuntimeException('Legacy DOC section-field smoke missing expected WordPress handoff: ' . $needle);
        }
    }

    foreach (['SECTION', 'SECTIONPAGES'] as $hidden) {
        if (str_contains(strip_tags($blocks), $hidden)) {
            throw new RuntimeException('Legacy DOC section-field smoke rendered hidden instruction text: ' . $hidden);
        }
    }

    echo "legacy doc section-field handoff self-test ok\n";
    return;
}

echo json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
