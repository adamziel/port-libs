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
$xstz = static function (string $text) use ($u16, $utf16le): string {
    $encoded = $utf16le($text);

    return $u16(intdiv(strlen($encoded), 2)) . $encoded . $u16(0);
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
$ffData = static function () use ($u16, $u32, $xstz): string {
    $bits = (1 << 7) | (1 << 8) | (1 << 9) | (1 << 14);

    return $u32(0xffffffff)
        . $u16($bits)
        . $u16(40)
        . $u16(0)
        . $xstz('PublicationState')
        . $xstz('Review')
        . $xstz('Title Case')
        . $xstz('Choose the WordPress publication state.')
        . $xstz('Legacy DOC form field metadata for import review.')
        . $xstz('DoNotRunEntry')
        . $xstz('DoNotRunExit');
};
$plcfldMom = static function (array $records, int $finalCp) use ($u32): string {
    $bytes = '';
    foreach ($records as $record) {
        $bytes .= $u32((int) $record['cp']);
    }
    $bytes .= $u32($finalCp);
    foreach ($records as $record) {
        $bytes .= chr((int) $record['character']) . chr((int) ($record['typeCode'] ?? 0));
    }

    return $bytes;
};
$buildFixtureStreams = static function () use ($u16, $u32, $padTo, $ffData, $plcfldMom): array {
    $fieldBegin = "\x13";
    $fieldSeparator = "\x14";
    $fieldEnd = "\x15";
    $text = 'Publish status '
        . $fieldBegin . ' FORMTEXT \* MERGEFORMAT ' . $fieldSeparator . 'pending review' . $fieldEnd
        . " from legacy DOC.\r";
    $textBytes = iconv('UTF-8', 'Windows-1252//TRANSLIT', $text);
    if (!is_string($textBytes)) {
        throw new RuntimeException('Unable to encode legacy DOC text fixture');
    }

    $beginCp = strpos($text, $fieldBegin);
    $separatorCp = strpos($text, $fieldSeparator);
    $endCp = strpos($text, $fieldEnd);
    if (!is_int($beginCp) || !is_int($separatorCp) || !is_int($endCp)) {
        throw new RuntimeException('Unable to locate legacy DOC form field fixture characters');
    }

    $fibSize = 512;
    $textStartFc = $fibSize;
    $beginStartFc = $textStartFc + $beginCp;
    $beginEndFc = $beginStartFc + 1;
    $textEndFc = $textStartFc + strlen($textBytes);
    $wordDocument = str_repeat("\0", $fibSize) . $textBytes;
    $wordDocument = substr_replace($wordDocument, $u16(0xa5ec), 0, 2);
    $wordDocument = substr_replace($wordDocument, $u16(0x00c1), 2, 2);
    $wordDocument = substr_replace($wordDocument, $u32($textStartFc), 24, 4);
    $wordDocument = substr_replace($wordDocument, $u32($textEndFc), 28, 4);

    $formFieldData = $ffData();
    $dataOffset = 13;
    $dataStream = str_repeat('D', $dataOffset)
        . $formFieldData
        . 'trailing-picture-or-ole-bytes-not-exposed';

    $chpxFkpPage = intdiv(strlen($wordDocument) + 511, 512);
    $chpxFkpOffset = $chpxFkpPage * 512;
    $formFieldGrpprl = $u16(0x0855) . "\x01"
        . $u16(0x6a03) . $u32($dataOffset)
        . $u16(0x0806) . "\x01";
    $chpxOffset = 64;
    $chpxFkp = str_repeat("\0", 512);
    $chpxFkp = substr_replace(
        $chpxFkp,
        $u32($textStartFc) . $u32($beginStartFc) . $u32($beginEndFc) . $u32($textEndFc),
        0,
        16
    );
    $chpxFkp = substr_replace($chpxFkp, "\0" . chr(intdiv($chpxOffset, 2)) . "\0", 16, 3);
    $chpxFkp = substr_replace($chpxFkp, chr(strlen($formFieldGrpprl)) . $formFieldGrpprl, $chpxOffset, 1 + strlen($formFieldGrpprl));
    $chpxFkp = substr_replace($chpxFkp, chr(3), 511, 1);
    $wordDocument = str_pad($wordDocument, $chpxFkpOffset, "\0") . $chpxFkp;

    $fieldTable = $plcfldMom([
        ['cp' => $beginCp, 'character' => 0x13, 'typeCode' => 0x46],
        ['cp' => $separatorCp, 'character' => 0x14],
        ['cp' => $endCp, 'character' => 0x15],
    ], strlen($text));
    $plcBteChpx = $u32($textStartFc)
        . $u32($beginStartFc)
        . $u32($beginEndFc)
        . $u32($textEndFc)
        . $u32($chpxFkpPage)
        . $u32($chpxFkpPage)
        . $u32($chpxFkpPage);
    $wordDocument = substr_replace($wordDocument, $u32(strlen($fieldTable)), 0x00fa, 4);
    $wordDocument = substr_replace($wordDocument, $u32(strlen($plcBteChpx)), 0x00fe, 4);
    $wordDocument = substr_replace($wordDocument, $u32(0), 0x011a, 4);
    $wordDocument = substr_replace($wordDocument, $u32(strlen($fieldTable)), 0x011e, 4);

    return [
        'WordDocument' => $padTo($wordDocument, 4096),
        '0Table' => $padTo($fieldTable . $plcBteChpx, 4096),
        'Data' => $padTo($dataStream, 4096),
    ];
};
$buildCfb = static function (array $streams) use ($u16, $u32, $directoryEntry, $unallocatedDirectoryEntry, $padTo): string {
    $sectorSize = 512;
    $free = 0xffffffff;
    $end = 0xfffffffe;
    $fatSector = 0xfffffffd;
    $tableSectors = str_split((string) $streams['0Table'], $sectorSize);
    $dataSectors = str_split((string) $streams['Data'], $sectorSize);
    $wordSectors = str_split((string) $streams['WordDocument'], $sectorSize);
    $tableStartSector = 2;
    $dataStartSector = $tableStartSector + count($tableSectors);
    $wordStartSector = $dataStartSector + count($dataSectors);
    $fat = [
        0 => $fatSector,
        1 => $end,
    ];
    foreach ($tableSectors as $index => $_sectorBytes) {
        $sector = $tableStartSector + $index;
        $fat[$sector] = $index === count($tableSectors) - 1 ? $end : $sector + 1;
    }
    foreach ($dataSectors as $index => $_sectorBytes) {
        $sector = $dataStartSector + $index;
        $fat[$sector] = $index === count($dataSectors) - 1 ? $end : $sector + 1;
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
        . $directoryEntry('Data', 2, $dataStartSector, strlen((string) $streams['Data']), $free, $free, $free, 0)
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
        . implode('', $dataSectors)
        . implode('', $wordSectors);
};

$result = (new LegacyDocReader())->readBytes($buildCfb($buildFixtureStreams()));
$blocks = (new WordPressBlockWriter())->write($result['document']);
$summary = [
    'metadata' => $result['metadata'],
    'formFieldDataReferences' => $result['formFieldDataReferences'],
    'blocks' => $blocks,
];

if (($argv[1] ?? '') === '--self-test') {
    if (($result['metadata']['formFieldDataReferenceCount'] ?? null) !== 1) {
        throw new RuntimeException('Legacy DOC FFData handoff smoke missing form-field data reference');
    }

    foreach ([
        'data-legacy-doc-form-field-data-source="chpx-data-stream"',
        'data-legacy-doc-form-field-name="PublicationState"',
        'data-legacy-doc-form-field-default-text="Review"',
        'data-legacy-doc-form-field-text-format="Title Case"',
        'data-legacy-doc-form-field-max-length="40"',
        'data-legacy-doc-form-field-macro-policy="disabled-native-review"',
        '>pending review</span>',
    ] as $needle) {
        if (!str_contains($blocks, $needle)) {
            throw new RuntimeException('Legacy DOC FFData handoff smoke missing expected WordPress block output: ' . $needle);
        }
    }

    foreach (['FORMTEXT', 'trailing-picture-or-ole-bytes-not-exposed', 'DoNotRunEntry()'] as $hidden) {
        if (str_contains(strip_tags($blocks), $hidden)) {
            throw new RuntimeException('Legacy DOC FFData handoff smoke rendered hidden data: ' . $hidden);
        }
    }

    echo "legacy doc form-field-data handoff self-test ok\n";
    return;
}

echo json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
