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
    int $color = 1
) use ($u16, $u32, $u64, $utf16le): string {
    $nameBytes = $utf16le($name . "\0");

    return str_pad($nameBytes, 64, "\0")
        . $u16(strlen($nameBytes))
        . chr($type)
        . chr($color)
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

$buildParagraphFormattingDoc = static function () use ($u16, $u32, $padTo): array {
    $firstParagraphText = "Centered layout paragraph\r";
    $secondParagraphText = "Plain paragraph\r";
    $text = $firstParagraphText . $secondParagraphText;
    $textBytes = iconv('UTF-8', 'Windows-1252//TRANSLIT', $text);
    if (!is_string($textBytes)) {
        throw new RuntimeException('Unable to encode simple WordDocument fixture text');
    }

    $fibSize = 768;
    $textStartFc = $fibSize;
    $firstParagraphEndFc = $textStartFc + strlen($firstParagraphText);
    $textEndFc = $textStartFc + strlen($textBytes);
    $wordDocument = str_repeat("\0", $fibSize) . $textBytes;
    $wordDocument = substr_replace($wordDocument, $u16(0xa5ec), 0, 2);
    $wordDocument = substr_replace($wordDocument, $u16(0x00c1), 2, 2);
    $wordDocument = substr_replace($wordDocument, $u32($textStartFc), 24, 4);
    $wordDocument = substr_replace($wordDocument, $u32($textEndFc), 28, 4);

    $paragraphLayoutGrpprl = $u16(0x2461) . "\x03"
        . $u16(0x2405) . "\x01"
        . $u16(0x2406) . "\x01"
        . $u16(0x2407) . "\x01"
        . $u16(0x845e) . $u16(720)
        . $u16(0x8460) . pack('v', 0xfe98)
        . $u16(0x845d) . $u16(240)
        . $u16(0xa413) . $u16(120)
        . $u16(0xa414) . $u16(240)
        . $u16(0x6412) . $u16(480) . $u16(1);
    $firstPapxPayload = $u16(0) . $paragraphLayoutGrpprl;
    $plainPapxPayload = $u16(0);

    $papxFkpPage = intdiv(strlen($wordDocument) + 511, 512);
    $papxFkpOffset = $papxFkpPage * 512;
    $firstPapxOffset = 384;
    $plainPapxOffset = 456;
    $papxFkp = str_repeat("\0", 512);
    $papxFkp = substr_replace(
        $papxFkp,
        $u32($textStartFc) . $u32($firstParagraphEndFc) . $u32($textEndFc),
        0,
        12
    );
    $papxFkp = substr_replace(
        $papxFkp,
        chr(intdiv($firstPapxOffset, 2)) . str_repeat("\0", 12)
            . chr(intdiv($plainPapxOffset, 2)) . str_repeat("\0", 12),
        12,
        26
    );
    $papxFkp = substr_replace(
        $papxFkp,
        "\0" . chr(intdiv(strlen($firstPapxPayload), 2)) . $firstPapxPayload,
        $firstPapxOffset,
        2 + strlen($firstPapxPayload)
    );
    $papxFkp = substr_replace(
        $papxFkp,
        "\0" . chr(intdiv(strlen($plainPapxPayload), 2)) . $plainPapxPayload,
        $plainPapxOffset,
        2 + strlen($plainPapxPayload)
    );
    $papxFkp = substr_replace($papxFkp, chr(2), 511, 1);
    $wordDocument = str_pad($wordDocument, $papxFkpOffset, "\0") . $papxFkp;

    $plcBtePapx = $u32($textStartFc)
        . $u32($firstParagraphEndFc)
        . $u32($textEndFc)
        . $u32($papxFkpPage)
        . $u32($papxFkpPage);
    $wordDocument = substr_replace($wordDocument, $u32(0), 0x0102, 4);
    $wordDocument = substr_replace($wordDocument, $u32(strlen($plcBtePapx)), 0x0106, 4);

    return [
        'WordDocument' => $padTo($wordDocument, 4096),
        '0Table' => $padTo($plcBtePapx, 4096),
    ];
};

$buildCfb = static function (array $streams) use ($u16, $u32, $directoryEntry, $unallocatedDirectoryEntry, $padTo): string {
    $sectorSize = 512;
    $free = 0xffffffff;
    $end = 0xfffffffe;
    $fatSector = 0xfffffffd;
    $tableSectors = str_split((string) $streams['0Table'], $sectorSize);
    $wordSectors = str_split((string) $streams['WordDocument'], $sectorSize);
    $tableStartSector = 2;
    $wordStartSector = $tableStartSector + count($tableSectors);
    $fat = [
        0 => $fatSector,
        1 => $end,
    ];
    foreach ($tableSectors as $index => $_sectorBytes) {
        $sector = $tableStartSector + $index;
        $fat[$sector] = $index === count($tableSectors) - 1 ? $end : $sector + 1;
    }
    foreach ($wordSectors as $index => $_sectorBytes) {
        $sector = $wordStartSector + $index;
        $fat[$sector] = $index === count($wordSectors) - 1 ? $end : $sector + 1;
    }

    $fatBytes = '';
    for ($index = 0; $index < 128; $index++) {
        $fatBytes .= $u32($fat[$index] ?? $free);
    }

    $directory = $directoryEntry('Root Entry', 5, $end, 0, $free, $free, 1)
        . $directoryEntry('0Table', 2, $tableStartSector, strlen((string) $streams['0Table']), 2, 3, $free)
        . $directoryEntry('Data', 2, $end, 0, $free, $free, $free, 0)
        . $directoryEntry('WordDocument', 2, $wordStartSector, strlen((string) $streams['WordDocument']), $free, $free, $free, 0);
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
        . implode('', $tableSectors)
        . implode('', $wordSectors);
};

$result = (new LegacyDocReader())->readBytes($buildCfb($buildParagraphFormattingDoc()));
$blocks = (new WordPressBlockWriter())->write($result['document']);
$summary = [
    'metadata' => $result['metadata'],
    'blocks' => $blocks,
];

if (($argv[1] ?? '') === '--self-test') {
    if (($result['metadata']['paragraphPropertyFormattingRunCount'] ?? null) !== 1) {
        throw new RuntimeException('Legacy DOC paragraph-formatting smoke missing paragraph-property run count');
    }

    $properties = $result['formattingRuns'][0]['paragraphProperties'] ?? [];
    foreach (['sprmPJc', 'sprmPFKeep', 'sprmPFKeepFollow', 'sprmPFPageBreakBefore', 'sprmPDxaLeft', 'sprmPDxaLeft1', 'sprmPDxaRight', 'sprmPDyaBefore', 'sprmPDyaAfter', 'sprmPDyaLine'] as $sourceSprm) {
        if (!in_array($sourceSprm, array_column($properties, 'sourceSprm'), true)) {
            throw new RuntimeException('Legacy DOC paragraph-formatting smoke missing ' . $sourceSprm);
        }
    }

    $lineSpacing = array_values(array_filter(
        $properties,
        static fn (array $property): bool => ($property['sourceSprm'] ?? null) === 'sprmPDyaLine'
    ))[0] ?? null;
    if (!is_array($lineSpacing) || ($lineSpacing['mode'] ?? null) !== 'multiple' || (float) ($lineSpacing['multiple'] ?? 0.0) !== 2.0) {
        throw new RuntimeException('Legacy DOC paragraph-formatting smoke missing line-spacing metadata');
    }

    if (!str_contains($blocks, '<p>Centered layout paragraph</p>') || !str_contains($blocks, '<p>Plain paragraph</p>')) {
        throw new RuntimeException('Legacy DOC paragraph-formatting smoke missing imported WordPress text');
    }
    foreach (['sprmPJc', 'sprmPDxaLeft', 'sprmPDyaLine', 'metadata-only-native-review'] as $hidden) {
        if (str_contains($blocks, $hidden)) {
            throw new RuntimeException('Legacy DOC paragraph-formatting smoke rendered metadata into blocks: ' . $hidden);
        }
    }

    echo "legacy doc paragraph-formatting handoff self-test ok\n";
    return;
}

echo json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
