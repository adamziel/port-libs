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
$buildCharacterFormattingDoc = static function () use ($u16, $u32, $padTo): array {
    $formattedRunText = 'Formatted review';
    $hiddenRunText = ' hidden reviewer note';
    $plainRunText = " plain import\r";
    $text = $formattedRunText . $hiddenRunText . $plainRunText;
    $textBytes = iconv('UTF-8', 'Windows-1252//TRANSLIT', $text);
    if (!is_string($textBytes)) {
        throw new RuntimeException('Unable to encode simple WordDocument fixture text');
    }

    $fibSize = 768;
    $textStartFc = $fibSize;
    $formattedRunEndFc = $textStartFc + strlen($formattedRunText);
    $hiddenRunEndFc = $formattedRunEndFc + strlen($hiddenRunText);
    $textEndFc = $textStartFc + strlen($textBytes);
    $wordDocument = str_repeat("\0", $fibSize) . $textBytes;
    $wordDocument = substr_replace($wordDocument, $u16(0xa5ec), 0, 2);
    $wordDocument = substr_replace($wordDocument, $u16(0x00c1), 2, 2);
    $wordDocument = substr_replace($wordDocument, $u32($textStartFc), 24, 4);
    $wordDocument = substr_replace($wordDocument, $u32($textEndFc), 28, 4);

    $formattedGrpprl = $u16(0x0835) . "\x01"
        . $u16(0x0836) . "\x01"
        . $u16(0x2a3e) . "\x01";
    $hiddenGrpprl = $u16(0x083c) . "\x01";
    $plainGrpprl = $u16(0x083c) . "\x00";
    $chpxFkpPage = intdiv(strlen($wordDocument) + 511, 512);
    $chpxFkpOffset = $chpxFkpPage * 512;
    $formattedChpxOffset = 64;
    $plainChpxOffset = 96;
    $hiddenChpxOffset = 128;
    $chpxFkp = str_repeat("\0", 512);
    $chpxFkp = substr_replace(
        $chpxFkp,
        $u32($textStartFc) . $u32($formattedRunEndFc) . $u32($hiddenRunEndFc) . $u32($textEndFc),
        0,
        16
    );
    $chpxFkp = substr_replace(
        $chpxFkp,
        chr(intdiv($formattedChpxOffset, 2)) . chr(intdiv($hiddenChpxOffset, 2)) . chr(intdiv($plainChpxOffset, 2)),
        16,
        3
    );
    $chpxFkp = substr_replace($chpxFkp, chr(strlen($formattedGrpprl)) . $formattedGrpprl, $formattedChpxOffset, 1 + strlen($formattedGrpprl));
    $chpxFkp = substr_replace($chpxFkp, chr(strlen($hiddenGrpprl)) . $hiddenGrpprl, $hiddenChpxOffset, 1 + strlen($hiddenGrpprl));
    $chpxFkp = substr_replace($chpxFkp, chr(strlen($plainGrpprl)) . $plainGrpprl, $plainChpxOffset, 1 + strlen($plainGrpprl));
    $chpxFkp = substr_replace($chpxFkp, chr(3), 511, 1);
    $wordDocument = str_pad($wordDocument, $chpxFkpOffset, "\0") . $chpxFkp;

    $plcBteChpx = $u32($textStartFc)
        . $u32($formattedRunEndFc)
        . $u32($hiddenRunEndFc)
        . $u32($textEndFc)
        . $u32($chpxFkpPage)
        . $u32($chpxFkpPage)
        . $u32($chpxFkpPage);
    $wordDocument = substr_replace($wordDocument, $u32(0), 0x00fa, 4);
    $wordDocument = substr_replace($wordDocument, $u32(strlen($plcBteChpx)), 0x00fe, 4);

    return [
        'WordDocument' => $padTo($wordDocument, 4096),
        '0Table' => $padTo($plcBteChpx, 4096),
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

$result = (new LegacyDocReader())->readBytes($buildCfb($buildCharacterFormattingDoc()));
$blocks = (new WordPressBlockWriter())->write($result['document']);
$summary = [
    'metadata' => $result['metadata'],
    'blocks' => $blocks,
];

if (($argv[1] ?? '') === '--self-test') {
    if (($result['metadata']['textPropertyFormattingRunCount'] ?? null) !== 3) {
        throw new RuntimeException('Legacy DOC character-formatting smoke missing text-property run count');
    }
    $properties = $result['formattingRuns'][0]['textProperties'] ?? [];
    foreach (['sprmCFBold', 'sprmCFItalic', 'sprmCKul'] as $sourceSprm) {
        if (!in_array($sourceSprm, array_column($properties, 'sourceSprm'), true)) {
            throw new RuntimeException('Legacy DOC character-formatting smoke missing ' . $sourceSprm);
        }
    }
    if (($result['metadata']['inlineTextFormattingApplicationCount'] ?? null) !== 1) {
        throw new RuntimeException('Legacy DOC character-formatting smoke missing inline formatting application count');
    }
    if (($result['metadata']['hiddenTextSuppressionCount'] ?? null) !== 1 || ($result['metadata']['hiddenTextSuppressionPolicy'] ?? null) !== 'suppressed-hidden-text-native-review') {
        throw new RuntimeException('Legacy DOC character-formatting smoke missing hidden text suppression metadata');
    }
    if (($result['formattingRuns'][0]['inlineFormattingPolicy'] ?? null) !== 'semantic-inline-native-review') {
        throw new RuntimeException('Legacy DOC character-formatting smoke missing semantic inline policy');
    }
    if (!str_contains($blocks, '<p><strong><em><u>Formatted review</u></em></strong> plain import</p>')) {
        throw new RuntimeException('Legacy DOC character-formatting smoke missing semantic WordPress formatting');
    }
    foreach (['hidden reviewer note', 'sprmCFBold', 'sprmCFItalic', 'sprmCKul', 'sprmCFVanish', 'metadata-only-native-review', 'suppressed-hidden-text-native-review'] as $hidden) {
        if (str_contains($blocks, $hidden)) {
            throw new RuntimeException('Legacy DOC character-formatting smoke rendered metadata into blocks: ' . $hidden);
        }
    }

    echo "legacy doc character-formatting handoff self-test ok\n";
    return;
}

echo json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
