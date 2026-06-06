<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\Pandoc\LegacyDocReader;
use PortLibs\Pandoc\WordPressBlockWriter;

$u16 = static fn (int $value): string => pack('v', $value);
$u32 = static fn (int $value): string => pack('V', $value);
$u64 = static fn (int $value): string => pack('V2', $value & 0xffffffff, intdiv($value, 4294967296));
$filetime = static function (?string $iso8601) use ($u64): string {
    if ($iso8601 === null || $iso8601 === '') {
        return str_repeat("\0", 8);
    }

    $seconds = strtotime($iso8601);
    if ($seconds === false) {
        throw new RuntimeException('Unable to encode CFB FILETIME fixture timestamp');
    }

    return $u64(((int) $seconds + 11644473600) * 10000000);
};
$clsidBytes = static function (?string $clsid) use ($u16, $u32): string {
    if ($clsid === null || $clsid === '') {
        return str_repeat("\0", 16);
    }

    if (!preg_match('/^([0-9a-f]{8})-([0-9a-f]{4})-([0-9a-f]{4})-([0-9a-f]{4})-([0-9a-f]{12})$/i', $clsid, $matches)) {
        throw new RuntimeException('CFB fixture directory CLSID is invalid');
    }

    $tail = hex2bin($matches[4] . $matches[5]);
    if (!is_string($tail) || strlen($tail) !== 8) {
        throw new RuntimeException('Unable to encode CFB fixture directory CLSID');
    }

    return $u32((int) hexdec($matches[1]))
        . $u16((int) hexdec($matches[2]))
        . $u16((int) hexdec($matches[3]))
        . $tail;
};
$utf16le = static function (string $text): string {
    $encoded = iconv('UTF-8', 'UTF-16LE', $text);
    if (!is_string($encoded)) {
        throw new RuntimeException('Unable to encode UTF-16LE fixture text');
    }

    return $encoded;
};
$characterLength = static function (string $text): int {
    if ($text === '') {
        return 0;
    }

    $characters = preg_split('//u', $text, -1, PREG_SPLIT_NO_EMPTY);

    return is_array($characters) ? count($characters) : strlen($text);
};
$padTo = static function (string $bytes, int $size): string {
    $remainder = strlen($bytes) % $size;

    return $remainder === 0 ? $bytes : $bytes . str_repeat("\0", $size - $remainder);
};
$directoryNameSortUnits = static function (string $name) use ($utf16le): array {
    $upper = function_exists('mb_strtoupper') ? mb_strtoupper($name, 'UTF-8') : strtoupper($name);
    $bytes = $utf16le($upper);
    $units = [];
    for ($offset = 0, $length = strlen($bytes); $offset + 2 <= $length; $offset += 2) {
        $units[] = unpack('vvalue', substr($bytes, $offset, 2))['value'];
    }

    return $units;
};
$compareCfbDirectoryNames = static function (string $left, string $right) use ($utf16le, $directoryNameSortUnits): int {
    $leftLength = strlen($utf16le($left . "\0"));
    $rightLength = strlen($utf16le($right . "\0"));
    if ($leftLength !== $rightLength) {
        return $leftLength <=> $rightLength;
    }

    $leftUnits = $directoryNameSortUnits($left);
    $rightUnits = $directoryNameSortUnits($right);
    $count = min(count($leftUnits), count($rightUnits));
    for ($index = 0; $index < $count; $index++) {
        if ($leftUnits[$index] !== $rightUnits[$index]) {
            return $leftUnits[$index] <=> $rightUnits[$index];
        }
    }

    return count($leftUnits) <=> count($rightUnits);
};
$directoryEntry = static function (
    string $name,
    int $type,
    int $startSector,
    int $size,
    int $leftSibling,
    int $rightSibling,
    int $child,
    ?string $createdAt = null,
    ?string $modifiedAt = null,
    ?string $clsid = null,
    int $stateBits = 0,
    int $colorFlag = 1
) use ($u16, $u32, $u64, $filetime, $clsidBytes, $utf16le): string {
    $nameBytes = $utf16le($name . "\0");

    return str_pad($nameBytes, 64, "\0")
        . $u16(strlen($nameBytes))
        . chr($type)
        . chr($colorFlag)
        . $u32($leftSibling)
        . $u32($rightSibling)
        . $u32($child)
        . $clsidBytes($clsid)
        . $u32($stateBits)
        . $filetime($createdAt)
        . $filetime($modifiedAt)
        . $u32($startSector)
        . $u64($size);
};

$typedLpstr = static function (string $value): string {
    $bytes = $value . "\0";
    $raw = pack('v', 0x001e) . "\0\0" . pack('V', strlen($bytes)) . $bytes;

    return str_pad($raw, (int) (ceil(strlen($raw) / 4) * 4), "\0");
};
$typedI2 = static fn (int $value): string => pack('v', 0x0002) . "\0\0" . pack('v', $value) . "\0\0";
$typedI4 = static fn (int $value): string => pack('v', 0x0003) . "\0\0" . pack('V', $value);
$typedBool = static fn (bool $value): string => pack('v', 0x000b) . "\0\0" . pack('v', $value ? 0xffff : 0) . "\0\0";
$typedUi4 = static fn (int $value): string => pack('v', 0x0013) . "\0\0" . pack('V', $value);
$typedUi8Parts = static fn (int $low, int $high): string => pack('v', 0x0015) . "\0\0" . pack('V2', $low, $high);
$typedR4 = static fn (float $value): string => pack('v', 0x0004) . "\0\0" . pack('g', $value);
$typedR8 = static fn (float $value): string => pack('v', 0x0005) . "\0\0" . pack('e', $value);
$typedCurrency = static fn (int $scaledValue): string => pack('v', 0x0006) . "\0\0" . pack('V2', $scaledValue & 0xffffffff, intdiv($scaledValue, 4294967296));
$typedOleDate = static fn (float $serialDate): string => pack('v', 0x0007) . "\0\0" . pack('e', $serialDate);
$typedFiletime = static function (string $iso8601) use ($u64): string {
    $seconds = strtotime($iso8601);
    if ($seconds === false) {
        throw new RuntimeException('Unable to encode FILETIME fixture timestamp');
    }

    return pack('v', 0x0040) . "\0\0" . $u64(((int) $seconds + 11644473600) * 10000000);
};
$typedClsid = static function (string $clsid) use ($clsidBytes): string {
    return pack('v', 0x0048) . "\0\0" . $clsidBytes($clsid);
};
$typedVectorLpstr = static function (array $values) use ($padTo): string {
    $payload = pack('V', count($values));
    foreach ($values as $value) {
        $bytes = (string) $value . "\0";
        $payload .= $padTo(pack('V', strlen($bytes)) . $bytes, 4);
    }
    $raw = pack('v', 0x101e) . "\0\0" . $payload;

    return str_pad($raw, (int) (ceil(strlen($raw) / 4) * 4), "\0");
};
$typedVariantLpstr = static function (string $value) use ($padTo): string {
    $bytes = $value . "\0";

    return $padTo(pack('v', 0x001e) . "\0\0" . pack('V', strlen($bytes)) . $bytes, 4);
};
$typedVariantI4 = static fn (int $value): string => pack('v', 0x0003) . "\0\0" . pack('V', $value);
$typedVectorVariant = static function (array $variants): string {
    $raw = pack('v', 0x100c) . "\0\0" . pack('V', count($variants)) . implode('', $variants);

    return str_pad($raw, (int) (ceil(strlen($raw) / 4) * 4), "\0");
};
$typedDictionary = static function (array $names): string {
    $raw = pack('V', count($names));
    foreach ($names as $propertyId => $name) {
        $bytes = (string) $name . "\0";
        $raw .= pack('V', (int) $propertyId) . pack('V', strlen($bytes)) . $bytes;
    }

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
$typedPropertySetStream = static function (array $sets) use ($typedPropertySet, $u32): string {
    $descriptorOffset = 28 + (count($sets) * 20);
    $descriptors = '';
    $payload = '';
    foreach ($sets as $set) {
        $fmtid = (string) $set['fmtid'];
        if (strlen($fmtid) !== 16) {
            throw new RuntimeException('Property set FMTID fixtures must be 16 bytes');
        }

        $propertySetBytes = substr($typedPropertySet($set['properties']), 48);
        $descriptors .= $fmtid . $u32($descriptorOffset + strlen($payload));
        $payload .= $propertySetBytes;
    }

    return pack('v', 0xfffe)
        . pack('v', 0)
        . $u32(0)
        . str_repeat("\0", 16)
        . $u32(count($sets))
        . $descriptors
        . $payload;
};
$propertySet = static function (array $values) use ($typedLpstr, $typedPropertySet): string {
    $properties = [];
    foreach ($values as $id => $value) {
        $properties[(int) $id] = $typedLpstr((string) $value);
    }

    return $typedPropertySet($properties);
};
$sttbfAssoc = static function (array $values) use ($u16, $utf16le): string {
    $bytes = $u16(0xffff) . $u16(18) . $u16(0);
    for ($index = 0; $index < 18; $index++) {
        $encoded = $utf16le((string) ($values[$index] ?? ''));
        $bytes .= $u16(intdiv(strlen($encoded), 2)) . $encoded;
    }

    return $bytes;
};
$buildPlcfldMom = static function (array $records, int $finalCp) use ($u32): string {
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
$ole10NativeStream = static function (
    string $label,
    string $sourcePath,
    string $temporaryPath,
    string $nativeData
) use ($u16, $u32): string {
    $ansi = static fn (string $value): string => $value . "\0";
    $payload = $u16(0x0002)
        . $ansi($label)
        . $ansi($sourcePath)
        . $u16(0)
        . $u16(0)
        . $ansi($temporaryPath)
        . $u32(strlen($nativeData))
        . $nativeData;

    return $u32(strlen($payload)) . $payload;
};
$compObjStream = static function (
    string $ansiUserType,
    string $ansiClipboardFormat,
    string $unicodeUserType,
    string $unicodeClipboardFormat
) use ($u32, $utf16le): string {
    $ansiString = static fn (string $value): string => $u32(strlen($value) + 1) . $value . "\0";
    $unicodeString = static function (string $value) use ($u32, $utf16le): string {
        $bytes = $utf16le($value . "\0");

        return $u32(strlen($bytes)) . $bytes;
    };
    $unicodeClipboard = static function (string $value) use ($u32, $utf16le): string {
        $bytes = $utf16le($value . "\0");

        return $u32(intdiv(strlen($bytes), 2)) . $bytes;
    };

    return str_repeat("\0", 28)
        . $ansiString($ansiUserType)
        . $ansiString($ansiClipboardFormat)
        . $ansiString('')
        . $u32(0x71b239f4)
        . $unicodeString($unicodeUserType)
        . $unicodeClipboard($unicodeClipboardFormat)
        . $unicodeString('');
};
$styleDefinition = static function (
    string $name,
    int $styleType,
    int $basedOnIstd,
    int $nextIstd,
    int $cupx,
    int $sti = 0x0ffe
) use ($u16, $utf16le): string {
    $nameBytes = $utf16le($name);
    $xstzName = $u16(intdiv(strlen($nameBytes), 2)) . $nameBytes . $u16(0);
    $cbStd = 10 + strlen($xstzName);
    $std = $u16($sti & 0x0fff)
        . $u16(($styleType & 0x000f) | (($basedOnIstd & 0x0fff) << 4))
        . $u16(($cupx & 0x000f) | (($nextIstd & 0x0fff) << 4))
        . $u16($cbStd)
        . $u16(0)
        . $xstzName;

    return $u16(strlen($std)) . $std . (strlen($std) % 2 === 0 ? '' : "\0");
};
$styleSheet = static function (array $styleRecords) use ($u16): string {
    $lastIstd = max(array_keys($styleRecords));
    $styleCount = max(15, (int) $lastIstd + 1);
    $stshif = $u16($styleCount)
        . $u16(0x000a)
        . $u16(0x0001)
        . $u16(0x0100)
        . $u16(0x000f)
        . $u16(0)
        . $u16(0)
        . $u16(0)
        . $u16(0);
    $styles = '';
    for ($istd = 0; $istd < $styleCount; $istd++) {
        $styles .= $styleRecords[$istd] ?? $u16(0);
    }

    return $u16(strlen($stshif)) . $stshif . $styles;
};
$listLevel = static function (
    int $startAt,
    int $numberFormat,
    string $numberText,
    array $placeholderOffsets = [],
    int $follow = 0,
    string $papx = '',
    string $chpx = ''
) use ($u16, $u32, $utf16le): string {
    $numberTextBytes = '';
    $characters = preg_split('//u', $numberText, -1, PREG_SPLIT_NO_EMPTY);
    if (!is_array($characters)) {
        $characters = str_split($numberText);
    }
    foreach ($characters as $character) {
        if (strlen($character) === 1 && ord($character) < 0x20) {
            $numberTextBytes .= $u16(ord($character));
            continue;
        }

        $encoded = $utf16le($character);
        if (strlen($encoded) !== 2) {
            throw new RuntimeException('Legacy DOC list-level fixture supports BMP characters only');
        }
        $numberTextBytes .= $encoded;
    }

    $rgbxchNums = '';
    for ($index = 0; $index < 9; $index++) {
        $rgbxchNums .= chr($placeholderOffsets[$index] ?? 0);
    }

    return $u32($startAt)
        . chr($numberFormat)
        . "\0"
        . $rgbxchNums
        . chr($follow)
        . $u32(0)
        . $u32(0)
        . chr(strlen($chpx))
        . chr(strlen($papx))
        . "\0\0"
        . $papx
        . $chpx
        . $u16(intdiv(strlen($numberTextBytes), 2))
        . $numberTextBytes;
};

$fieldBegin = "\x13";
$fieldSeparator = "\x14";
$fieldEnd = "\x15";
$embeddedNativeData = 'opaque legacy embedded spreadsheet bytes';
$firstPieceText = 'Legacy DOC import ΩЖ魚';
$secondPieceText = "\rReviewer notes keep hard\vbreaks for block review with "
    . 'note ' . "\x02" . ' and endnote # while checking '
    . 'comment ' . "\x05" . ' and '
    . $fieldBegin . ' HYPERLINK "https://example.test/legacy-doc?source=42" \o "Source packet" '
    . $fieldSeparator . 'source dossier' . $fieldEnd
    . ' and '
    . $fieldBegin . ' HYPERLINK \l "legacy_anchor" '
    . $fieldSeparator . 'opening bookmark' . $fieldEnd
    . ' with cross-reference '
    . $fieldBegin . ' REF "legacy_anchor" \h '
    . $fieldSeparator . 'Legacy DOC import' . $fieldEnd
    . ' on referenced page '
    . $fieldBegin . ' PAGEREF legacy_anchor \p '
    . $fieldSeparator . '7' . $fieldEnd
    . ' on page '
    . $fieldBegin . ' PAGE \* Arabic ' . $fieldSeparator . '7' . $fieldEnd
    . ' with form value '
    . $fieldBegin . ' FORMTEXT \* MERGEFORMAT ' . $fieldSeparator . 'pending review' . $fieldEnd
    . ' and source symbol '
    . $fieldBegin . ' SYMBOL 183 \f "Symbol" \s 12 \u ' . $fieldSeparator . '·' . $fieldEnd
    . "\x01"
    . ".\r";
$firstPieceBytes = $utf16le($firstPieceText);
$secondPieceBytes = $utf16le($secondPieceText);
$subdocumentSeparatorBytes = $utf16le("\r");
$footnoteSubdocumentText = "Footnote body retained for metadata-only review.\r";
$headerSubdocumentText = "Header text retained for layout review.\r";
$commentSubdocumentText = "Comment body retained for annotation review.\r";
$endnoteSubdocumentText = "Endnote body retained for metadata-only review.\r";
$footnoteSubdocumentBytes = $utf16le($footnoteSubdocumentText);
$headerSubdocumentBytes = $utf16le($headerSubdocumentText);
$commentSubdocumentBytes = $utf16le($commentSubdocumentText);
$endnoteSubdocumentBytes = $utf16le($endnoteSubdocumentText);
$firstPieceStart = 1024;
$secondPieceStart = $firstPieceStart + strlen($firstPieceBytes);
$mainTextByteEnd = $secondPieceStart + strlen($secondPieceBytes);
$subdocumentSeparatorStart = $mainTextByteEnd;
$footnoteSubdocumentStart = $subdocumentSeparatorStart + strlen($subdocumentSeparatorBytes);
$headerSubdocumentStart = $footnoteSubdocumentStart + strlen($footnoteSubdocumentBytes);
$commentSubdocumentStart = $headerSubdocumentStart + strlen($headerSubdocumentBytes);
$endnoteSubdocumentStart = $commentSubdocumentStart + strlen($commentSubdocumentBytes);
$subdocumentByteEnd = $endnoteSubdocumentStart + strlen($endnoteSubdocumentBytes);

$wordDocument = str_repeat("\0", $firstPieceStart)
    . $firstPieceBytes
    . $secondPieceBytes
    . $subdocumentSeparatorBytes
    . $footnoteSubdocumentBytes
    . $headerSubdocumentBytes
    . $commentSubdocumentBytes
    . $endnoteSubdocumentBytes;
$sepxFc = strlen($wordDocument) + 64;
$wordDocument = str_pad($wordDocument, $sepxFc, "\0") . $u16(4) . "\x34\x12\x00\x00";
$appendFkp = static function (string &$wordDocument, int $runCount): int {
    $page = intdiv(strlen($wordDocument) + 511, 512);
    $offset = $page * 512;
    $wordDocument = str_pad($wordDocument, $offset, "\0")
        . str_repeat("\0", 511)
        . chr($runCount);

    return $page;
};
$papxFkpPage = $appendFkp($wordDocument, 2);
$chpxFkpPage = $appendFkp($wordDocument, 3);
$wordDocument = substr_replace($wordDocument, $u16(0xa5ec), 0, 2);
$wordDocument = substr_replace($wordDocument, $u16(0x00c1), 2, 2);
$wordDocument = substr_replace($wordDocument, $u16(0x0409), 6, 2);
$wordDocument = substr_replace($wordDocument, $u16(0x3e34), 10, 2);
$wordDocument = substr_replace($wordDocument, $u16(0x00bf), 12, 2);
$wordDocument = substr_replace($wordDocument, $u32(0), 24, 4);
$wordDocument = substr_replace($wordDocument, $u32($subdocumentByteEnd), 28, 4);

$firstPieceCharacters = intdiv(strlen($firstPieceBytes), 2);
$secondPieceCharacters = intdiv(strlen($secondPieceBytes), 2);
$footnoteSubdocumentCharacters = intdiv(strlen($footnoteSubdocumentBytes), 2);
$headerSubdocumentCharacters = intdiv(strlen($headerSubdocumentBytes), 2);
$commentSubdocumentCharacters = intdiv(strlen($commentSubdocumentBytes), 2);
$endnoteSubdocumentCharacters = intdiv(strlen($endnoteSubdocumentBytes), 2);
$totalPieceCharacters = $firstPieceCharacters + $secondPieceCharacters;
$footnoteSubdocumentCpStart = $totalPieceCharacters + 1;
$headerSubdocumentCpStart = $footnoteSubdocumentCpStart + $footnoteSubdocumentCharacters;
$commentSubdocumentCpStart = $headerSubdocumentCpStart + $headerSubdocumentCharacters;
$endnoteSubdocumentCpStart = $commentSubdocumentCpStart + $commentSubdocumentCharacters;
$pieceTableLastCp = $endnoteSubdocumentCpStart + $endnoteSubdocumentCharacters;
$mainText = $firstPieceText . $secondPieceText;
$mainTextCharacters = preg_split('//u', $mainText, -1, PREG_SPLIT_NO_EMPTY);
if (!is_array($mainTextCharacters) || count($mainTextCharacters) !== $totalPieceCharacters) {
    throw new RuntimeException('Unable to split legacy DOC main text into CP characters');
}
$fieldTypeCodes = [
    'HYPERLINK' => 0x58,
    'REF' => 0x03,
    'PAGEREF' => 0x25,
    'PAGE' => 0x21,
    'FORMTEXT' => 0x46,
    'SYMBOL' => 0x39,
];
$fieldRecords = [];
for ($cp = 0, $count = count($mainTextCharacters); $cp < $count; $cp++) {
    $character = $mainTextCharacters[$cp];
    if ($character === $fieldBegin) {
        $instruction = '';
        for ($cursor = $cp + 1; $cursor < $count; $cursor++) {
            if ($mainTextCharacters[$cursor] === $fieldSeparator || $mainTextCharacters[$cursor] === $fieldEnd) {
                break;
            }
            $instruction .= $mainTextCharacters[$cursor];
        }
        $fieldName = strtoupper((string) (preg_split('/\s+/', trim($instruction))[0] ?? ''));
        $fieldRecords[] = [
            'cp' => $cp,
            'character' => 0x13,
            'typeCode' => $fieldTypeCodes[$fieldName] ?? 0x01,
        ];
        continue;
    }
    if ($character === $fieldSeparator) {
        $fieldRecords[] = [
            'cp' => $cp,
            'character' => 0x14,
        ];
        continue;
    }
    if ($character === $fieldEnd) {
        $fieldRecords[] = [
            'cp' => $cp,
            'character' => 0x15,
        ];
    }
}
$plcfldMom = $buildPlcfldMom($fieldRecords, $totalPieceCharacters);
$wordDocument = substr_replace($wordDocument, $u32(strlen($wordDocument)), 0x0040, 4);
$wordDocument = substr_replace($wordDocument, $u32($totalPieceCharacters), 0x004c, 4);
$wordDocument = substr_replace($wordDocument, $u32($footnoteSubdocumentCharacters), 0x0050, 4);
$wordDocument = substr_replace($wordDocument, $u32($headerSubdocumentCharacters), 0x0054, 4);
$wordDocument = substr_replace($wordDocument, $u32($commentSubdocumentCharacters), 0x005c, 4);
$wordDocument = substr_replace($wordDocument, $u32($endnoteSubdocumentCharacters), 0x0060, 4);
$plcPcd = $u32(0)
    . $u32($firstPieceCharacters)
    . $u32($firstPieceCharacters + $secondPieceCharacters)
    . $u32($footnoteSubdocumentCpStart)
    . $u32($headerSubdocumentCpStart)
    . $u32($commentSubdocumentCpStart)
    . $u32($endnoteSubdocumentCpStart)
    . $u32($pieceTableLastCp)
    . $u16(0x0001) . $u32($firstPieceStart) . "\0\0"
    . $u16(0) . $u32($secondPieceStart) . "\0\0"
    . $u16(0) . $u32($subdocumentSeparatorStart) . "\0\0"
    . $u16(0) . $u32($footnoteSubdocumentStart) . "\0\0"
    . $u16(0) . $u32($headerSubdocumentStart) . "\0\0"
    . $u16(0) . $u32($commentSubdocumentStart) . "\0\0"
    . $u16(0) . $u32($endnoteSubdocumentStart) . "\0\0";
$clx = "\x02" . $u32(strlen($plcPcd)) . $plcPcd;
$bookmarkName = 'legacy_anchor';
$bookmarkNameBytes = $utf16le($bookmarkName);
$sttbfBkmk = $u16(0xffff)
    . $u16(1)
    . $u16(0)
    . $u16(intdiv(strlen($bookmarkNameBytes), 2))
    . $bookmarkNameBytes;
$plcfBkf = $u32(0)
    . $u32($totalPieceCharacters + 1)
    . $u16(0) . $u16(0);
$plcfBkl = $u32($firstPieceCharacters)
    . $u32($totalPieceCharacters + 1);
$footnoteReferenceOffset = strpos($secondPieceText, "\x02");
$endnoteReferenceOffset = strpos($secondPieceText, '#');
if ($footnoteReferenceOffset === false || $endnoteReferenceOffset === false) {
    throw new RuntimeException('Unable to locate legacy DOC note reference fixture characters');
}
$footnoteReferenceCp = $firstPieceCharacters + $characterLength(substr($secondPieceText, 0, $footnoteReferenceOffset));
$endnoteReferenceCp = $firstPieceCharacters + $characterLength(substr($secondPieceText, 0, $endnoteReferenceOffset));
$plcffndRef = $u32($footnoteReferenceCp)
    . $u32($totalPieceCharacters + 1)
    . $u16(1);
$plcffndTxt = $u32(0)
    . $u32(35)
    . $u32(36);
$plcfendRef = $u32($endnoteReferenceCp)
    . $u32($totalPieceCharacters + 1)
    . $u16(0);
$plcfendTxt = $u32(0)
    . $u32(29)
    . $u32(30);
$commentReferenceOffset = strpos($secondPieceText, "\x05");
if ($commentReferenceOffset === false) {
    throw new RuntimeException('Unable to locate legacy DOC comment reference fixture character');
}
$commentReferenceCp = $firstPieceCharacters + $characterLength(substr($secondPieceText, 0, $commentReferenceOffset));
$commentInitialsBytes = $utf16le('MR');
$commentDescriptor = $u16(2)
    . $commentInitialsBytes
    . str_repeat("\0", 18 - strlen($commentInitialsBytes))
    . $u16(3)
    . $u16(0)
    . $u16(0)
    . $u32(0x2042);
$plcfandRef = $u32($commentReferenceCp)
    . $u32($totalPieceCharacters + 1)
    . $commentDescriptor;
$plcfandTxt = $u32(0)
    . $u32(24)
    . $u32(25);
$plcfSed = $u32(0)
    . $u32($totalPieceCharacters + 1)
    . $u16(0) . $u32($sepxFc) . $u16(0) . $u32(0);
$plcBtePapx = $u32($firstPieceStart)
    . $u32($mainTextByteEnd)
    . $u32($papxFkpPage);
$plcBteChpx = $u32($firstPieceStart)
    . $u32($secondPieceStart)
    . $u32($mainTextByteEnd)
    . $u32($chpxFkpPage)
    . $u32($chpxFkpPage);
$stsh = $styleSheet([
    15 => $styleDefinition('Review Heading,Import Title', 1, 0x0fff, 16, 2),
    16 => $styleDefinition('Reviewer Body', 1, 15, 16, 2),
    17 => $styleDefinition('Migration Emphasis', 2, 0x0fff, 16, 1),
]);
$lstf = static function (int $lsid, int $tplc, array $styles, int $flags, int $grfhic = 0) use ($u16, $u32): string {
    $styleBytes = '';
    for ($level = 0; $level < 9; $level++) {
        $styleBytes .= $u16($styles[$level] ?? 0x0fff);
    }

    return $u32($lsid) . $u32($tplc) . $styleBytes . chr($flags) . chr($grfhic);
};
$listOrderedLevel = $listLevel(3, 0x00, "\0.", [1], 1, "\x11\x22", "\x33");
$listBulletLevel = $listLevel(1, 0x17, "•");
$plfLst = $u16(2)
    . $lstf(1001, 2001, [0 => 15], 0x01)
    . $lstf(2002, 3002, [0 => 16], 0x01, 2);
$plfLfo = $u32(2)
    . $u32(1001) . $u32(0) . $u32(0) . chr(1) . chr(0xfe) . chr(0) . "\0"
    . $u32(2002) . $u32(0) . $u32(0) . chr(0) . chr(0) . chr(0) . "\0"
    . $u32(0) . $u32(7) . $u32(0x10)
    . $u32($firstPieceCharacters);
$associatedStringsTable = $sttbfAssoc([
    1 => 'C:\Templates\legacy-import.dot',
    2 => 'Associated title should not override OLE',
    3 => 'Legacy source packet',
    4 => 'legacy,word,review',
    6 => 'Associated author should not override OLE',
    7 => 'Review Desk',
    8 => 'C:\Data\legacy-mailmerge.csv',
    9 => 'C:\Data\legacy-header.doc',
    17 => 'review-lock',
]);
$fcPlcfFldMom = strlen($clx);
$fcSttbfAssoc = $fcPlcfFldMom + strlen($plcfldMom);
$fcSttbfBkmk = $fcSttbfAssoc + strlen($associatedStringsTable);
$fcPlcfBkf = $fcSttbfBkmk + strlen($sttbfBkmk);
$fcPlcfBkl = $fcPlcfBkf + strlen($plcfBkf);
$fcPlcffndRef = $fcPlcfBkl + strlen($plcfBkl);
$fcPlcffndTxt = $fcPlcffndRef + strlen($plcffndRef);
$fcPlcfendRef = $fcPlcffndTxt + strlen($plcffndTxt);
$fcPlcfendTxt = $fcPlcfendRef + strlen($plcfendRef);
$fcPlcfandRef = $fcPlcfendTxt + strlen($plcfendTxt);
$fcPlcfandTxt = $fcPlcfandRef + strlen($plcfandRef);
$fcPlcfSed = $fcPlcfandTxt + strlen($plcfandTxt);
$fcPlcBtePapx = $fcPlcfSed + strlen($plcfSed);
$fcPlcBteChpx = $fcPlcBtePapx + strlen($plcBtePapx);
$fcStshf = $fcPlcBteChpx + strlen($plcBteChpx);
$fcPlfLst = $fcStshf + strlen($stsh);
$fcPlfLfo = $fcPlfLst + strlen($plfLst) + strlen($listOrderedLevel) + strlen($listBulletLevel);
$tableStream = $clx . $plcfldMom . $associatedStringsTable . $sttbfBkmk . $plcfBkf . $plcfBkl . $plcffndRef . $plcffndTxt . $plcfendRef . $plcfendTxt . $plcfandRef . $plcfandTxt . $plcfSed . $plcBtePapx . $plcBteChpx . $stsh . $plfLst . $listOrderedLevel . $listBulletLevel . $plfLfo;
$wordDocument = substr_replace($wordDocument, $u32($fcStshf), 0x00a2, 4);
$wordDocument = substr_replace($wordDocument, $u32(strlen($stsh)), 0x00a6, 4);
$wordDocument = substr_replace($wordDocument, $u32($fcPlcBteChpx), 0x00fa, 4);
$wordDocument = substr_replace($wordDocument, $u32(strlen($plcBteChpx)), 0x00fe, 4);
$wordDocument = substr_replace($wordDocument, $u32($fcPlcBtePapx), 0x0102, 4);
$wordDocument = substr_replace($wordDocument, $u32(strlen($plcBtePapx)), 0x0106, 4);
$wordDocument = substr_replace($wordDocument, $u32($fcPlcfFldMom), 0x011a, 4);
$wordDocument = substr_replace($wordDocument, $u32(strlen($plcfldMom)), 0x011e, 4);
$wordDocument = substr_replace($wordDocument, $u32($fcPlcfSed), 0x00ca, 4);
$wordDocument = substr_replace($wordDocument, $u32(strlen($plcfSed)), 0x00ce, 4);
$wordDocument = substr_replace($wordDocument, $u32($fcPlcffndRef), 0x00aa, 4);
$wordDocument = substr_replace($wordDocument, $u32(strlen($plcffndRef)), 0x00ae, 4);
$wordDocument = substr_replace($wordDocument, $u32($fcPlcffndTxt), 0x00b2, 4);
$wordDocument = substr_replace($wordDocument, $u32(strlen($plcffndTxt)), 0x00b6, 4);
$wordDocument = substr_replace($wordDocument, $u32(0), 0x01a2, 4);
$wordDocument = substr_replace($wordDocument, $u32(strlen($clx)), 0x01a6, 4);
$wordDocument = substr_replace($wordDocument, $u32($fcSttbfAssoc), 0x019a, 4);
$wordDocument = substr_replace($wordDocument, $u32(strlen($associatedStringsTable)), 0x019e, 4);
$wordDocument = substr_replace($wordDocument, $u32($fcSttbfBkmk), 0x0142, 4);
$wordDocument = substr_replace($wordDocument, $u32(strlen($sttbfBkmk)), 0x0146, 4);
$wordDocument = substr_replace($wordDocument, $u32($fcPlcfBkf), 0x014a, 4);
$wordDocument = substr_replace($wordDocument, $u32(strlen($plcfBkf)), 0x014e, 4);
$wordDocument = substr_replace($wordDocument, $u32($fcPlcfBkl), 0x0152, 4);
$wordDocument = substr_replace($wordDocument, $u32(strlen($plcfBkl)), 0x0156, 4);
$wordDocument = substr_replace($wordDocument, $u32($fcPlcfendRef), 0x020a, 4);
$wordDocument = substr_replace($wordDocument, $u32(strlen($plcfendRef)), 0x020e, 4);
$wordDocument = substr_replace($wordDocument, $u32($fcPlcfendTxt), 0x0212, 4);
$wordDocument = substr_replace($wordDocument, $u32(strlen($plcfendTxt)), 0x0216, 4);
$wordDocument = substr_replace($wordDocument, $u32($fcPlcfandRef), 0x00ba, 4);
$wordDocument = substr_replace($wordDocument, $u32(strlen($plcfandRef)), 0x00be, 4);
$wordDocument = substr_replace($wordDocument, $u32($fcPlcfandTxt), 0x00c2, 4);
$wordDocument = substr_replace($wordDocument, $u32(strlen($plcfandTxt)), 0x00c6, 4);
$wordDocument = substr_replace($wordDocument, $u32($fcPlfLst), 0x02e2, 4);
$wordDocument = substr_replace($wordDocument, $u32(strlen($plfLst)), 0x02e6, 4);
$wordDocument = substr_replace($wordDocument, $u32($fcPlfLfo), 0x02ea, 4);
$wordDocument = substr_replace($wordDocument, $u32(strlen($plfLfo)), 0x02ee, 4);
$docSummaryFmtid = hex2bin('02d5cdd59c2e1b10939708002b2cf9ae');
$userDefinedFmtid = hex2bin('05d5cdd59c2e1b10939708002b2cf9ae');
if (!is_string($docSummaryFmtid) || !is_string($userDefinedFmtid)) {
    throw new RuntimeException('Unable to build OLE property-set FMTID fixtures');
}
$sourceGuid = 'f0e1d2c3-b4a5-9687-1020-304050607080';
$archiveBytes = 6000000000;

$streams = [
    'WordDocument' => $wordDocument,
    '1Table' => $tableStream,
    "\x05SummaryInformation" => $typedPropertySet([
        2 => $typedLpstr('Legacy DOC import packet'),
        4 => $typedLpstr('Migration Desk'),
        6 => $typedLpstr('Source .doc review notes'),
        8 => $typedLpstr('Reviewer'),
        12 => $typedFiletime('2024-01-02T03:04:05Z'),
        13 => $typedFiletime('2024-02-10T11:12:13Z'),
        14 => $typedI4(2),
        15 => $typedI4(12),
        16 => $typedI4(72),
        18 => $typedLpstr('Native PHP legacy DOC handoff'),
        19 => $typedI4(0x00000002),
    ]),
    "\x05DocumentSummaryInformation" => $typedPropertySetStream([
        [
            'fmtid' => $docSummaryFmtid,
            'properties' => [
                1 => $typedI2(65001),
                2 => $typedLpstr('Data Liberation import queue - legacy обзор'),
                4 => $typedUi4(4096),
                5 => $typedI4(2),
                6 => $typedI4(2),
                11 => $typedBool(false),
                12 => $typedVectorVariant([
                    $typedVariantLpstr('Sections'),
                    $typedVariantI4(2),
                    $typedVariantLpstr('Appendices'),
                    $typedVariantI4(1),
                ]),
                13 => $typedVectorLpstr([
                    'Overview',
                    'Reviewer notes',
                    'Source appendix',
                ]),
                15 => $typedLpstr('Example Press'),
                16 => $typedBool(true),
                17 => $typedI4(78),
            ],
        ],
        [
            'fmtid' => $userDefinedFmtid,
            'properties' => [
                0 => $typedDictionary([
                    2 => 'MigrationBatch',
                    3 => 'Needs Review',
                    4 => 'Source Id',
                    5 => 'Archive Bytes',
                    6 => 'Source Guid',
                    7 => 'Review Weight',
                    8 => 'Confidence Score',
                    9 => 'Invoice Total',
                    10 => 'Review Date',
                ]),
                1 => $typedI2(1252),
                2 => $typedLpstr('legacy-doc-42'),
                3 => $typedBool(true),
                4 => $typedI4(4242),
                5 => $typedUi8Parts(1705032704, 1),
                6 => $typedClsid($sourceGuid),
                7 => $typedR4(1.25),
                8 => $typedR8(0.875),
                9 => $typedCurrency(12345678),
                10 => $typedOleDate(45309.5),
            ],
        ],
    ]),
    'ObjectPool/_42/' . "\x03" . 'ObjInfo' => "\0\0" . $u16(0x0014),
    'ObjectPool/_42/' . "\x01" . 'CompObj' => $compObjStream(
        'Package',
        'Native',
        'Legacy Package Ω',
        'Excel.Sheet.12'
    ),
    'ObjectPool/_42/' . "\x01" . 'Ole10Native' => $ole10NativeStream(
        'legacy-data.xlsx',
        'C:\legacy\legacy-data.xlsx',
        'C:\Temp\legacy-data.tmp',
        $embeddedNativeData
    ),
    'ObjectPool/_42/' . "\x02" . 'OlePres000' => 'opaque embedded object presentation preview',
    'Macros/PROJECT' => "ID=\"LegacyMacros\"\r\nDocument=ThisDocument/&H00000000\r\nModule=MigrationTools\r\n",
    'Macros/PROJECTwm' => "LegacyMacros\0ThisDocument\0MigrationTools\0",
    'Macros/VBA/dir' => 'compressed vba directory bytes',
    'Macros/VBA/_VBA_PROJECT' => 'performance cache bytes',
    'Macros/VBA/ThisDocument' => "Attribute VB_Name = \"ThisDocument\"\r\nPrivate Sub Document_Open()\r\nEnd Sub\r\n",
    'Macros/VBA/MigrationTools' => "Attribute VB_Name = \"MigrationTools\"\r\nSub ImportPacket()\r\nEnd Sub\r\n",
];
$directoryTimestamps = [
    '' => [
        'modifiedAt' => '2024-04-06T07:08:09Z',
        'clsid' => '00112233-4455-6677-8899-aabbccddeeff',
        'stateBits' => 0x40000001,
    ],
    'ObjectPool/_42' => [
        'createdAt' => '2024-04-07T08:09:10Z',
        'modifiedAt' => '2024-04-08T09:10:11Z',
        'clsid' => '00020906-0000-0000-c000-000000000046',
        'stateBits' => 0x00000010,
    ],
];

$miniSectorSize = 64;
$sectorSize = 512;
$free = 0xffffffff;
$end = 0xfffffffe;
$fatSector = 0xfffffffd;

$nodes = [
    [
        'name' => 'Root Entry',
        'type' => 5,
        'children' => [],
    ],
];
$nodeByPath = ['' => 0];
foreach ($streams as $name => $_data) {
    $path = trim(str_replace('\\', '/', (string) $name), '/');
    $segments = array_values(array_filter(explode('/', $path), static fn (string $segment): bool => $segment !== ''));
    if ($segments === []) {
        throw new RuntimeException('CFB fixture stream path is empty');
    }

    $parentIndex = 0;
    $parentPath = '';
    for ($index = 0, $last = count($segments) - 1; $index < $last; $index++) {
        $storagePath = $parentPath === '' ? $segments[$index] : $parentPath . '/' . $segments[$index];
        if (!isset($nodeByPath[$storagePath])) {
            $nodeByPath[$storagePath] = count($nodes);
            $nodes[] = [
                'name' => $segments[$index],
                'type' => 1,
                'children' => [],
            ];
            $nodes[$parentIndex]['children'][] = $nodeByPath[$storagePath];
        }
        $parentIndex = $nodeByPath[$storagePath];
        $parentPath = $storagePath;
    }

    $leafName = $segments[count($segments) - 1];
    $streamPath = $parentPath === '' ? $leafName : $parentPath . '/' . $leafName;
    $nodeByPath[$streamPath] = count($nodes);
    $nodes[] = [
        'name' => $leafName,
        'type' => 2,
        'streamPath' => $streamPath,
        'children' => [],
    ];
    $nodes[$parentIndex]['children'][] = count($nodes) - 1;
}

$leftSiblings = [];
$rightSiblings = [];
$childIds = [];
$nodeColors = array_fill(0, count($nodes), 1);
$buildSiblingTree = static function (array $children) use (&$buildSiblingTree, &$nodes, &$leftSiblings, &$rightSiblings, $compareCfbDirectoryNames, $free): int {
    usort($children, static fn (int $left, int $right): int => $compareCfbDirectoryNames((string) $nodes[$left]['name'], (string) $nodes[$right]['name']));
    $middle = intdiv(count($children), 2);
    $nodeIndex = $children[$middle];
    $left = array_slice($children, 0, $middle);
    $right = array_slice($children, $middle + 1);
    $leftSiblings[$nodeIndex] = $left === [] ? $free : $buildSiblingTree($left);
    $rightSiblings[$nodeIndex] = $right === [] ? $free : $buildSiblingTree($right);

    return $nodeIndex;
};
foreach ($nodes as $nodeIndex => $node) {
    if ($node['children'] !== []) {
        $childIds[$nodeIndex] = $buildSiblingTree($node['children']);
    }
}
$colorSiblingTree = static function (int $nodeId, bool $forceBlack = false) use (&$colorSiblingTree, &$leftSiblings, &$rightSiblings, $free): array {
    if ($nodeId === $free) {
        return [[
            'blackHeight' => 1,
            'rootColor' => 1,
            'colors' => [],
        ]];
    }

    $leftOptions = $colorSiblingTree($leftSiblings[$nodeId] ?? $free);
    $rightOptions = $colorSiblingTree($rightSiblings[$nodeId] ?? $free);
    $options = [];
    foreach ([1, 0] as $colorFlag) {
        if ($forceBlack && $colorFlag !== 1) {
            continue;
        }
        foreach ($leftOptions as $leftOption) {
            foreach ($rightOptions as $rightOption) {
                if ($leftOption['blackHeight'] !== $rightOption['blackHeight']) {
                    continue;
                }
                if ($colorFlag === 0 && ($leftOption['rootColor'] === 0 || $rightOption['rootColor'] === 0)) {
                    continue;
                }

                $colors = $leftOption['colors'] + $rightOption['colors'];
                $colors[$nodeId] = $colorFlag;
                $options[] = [
                    'blackHeight' => $leftOption['blackHeight'] + ($colorFlag === 1 ? 1 : 0),
                    'rootColor' => $colorFlag,
                    'colors' => $colors,
                ];
            }
        }
    }

    return $options;
};
foreach ($childIds as $childId) {
    $options = $colorSiblingTree($childId, true);
    if ($options === []) {
        throw new RuntimeException('Unable to color CFB fixture directory tree');
    }
    usort($options, static fn (array $left, array $right): int => $left['blackHeight'] <=> $right['blackHeight']);
    foreach ($options[0]['colors'] as $nodeIndex => $colorFlag) {
        $nodeColors[(int) $nodeIndex] = (int) $colorFlag;
    }
}

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

$sectors = [
    str_repeat("\0", $sectorSize),
    '',
    '',
];
$fat = [
    $fatSector,
    $end,
    $end,
];
$rootMiniStart = count($sectors);
$miniStreamChunks = str_split($miniStream, $sectorSize);
foreach ($miniStreamChunks as $index => $chunk) {
    $sector = count($sectors);
    $sectors[] = $chunk;
    $fat[] = $index === count($miniStreamChunks) - 1 ? $end : $sector + 1;
}

$directory = $directoryEntry(
    'Root Entry',
    5,
    $rootMiniStart,
    $miniStreamSize,
    $free,
    $free,
    $childIds[0] ?? $free,
    $directoryTimestamps['']['createdAt'] ?? null,
    $directoryTimestamps['']['modifiedAt'] ?? null,
    $directoryTimestamps['']['clsid'] ?? null,
    (int) ($directoryTimestamps['']['stateBits'] ?? 0)
);
foreach ($nodes as $nodeIndex => $node) {
    if ($nodeIndex === 0) {
        continue;
    }

    $type = (int) $node['type'];
    $streamPath = (string) ($node['streamPath'] ?? '');
    $location = $type === 2 ? $locations[$streamPath] : ['startSector' => $end, 'size' => 0];
    $entryPath = $type === 2 ? $streamPath : array_search($nodeIndex, $nodeByPath, true);
    $entryPath = is_string($entryPath) ? $entryPath : '';
    $timestamps = $directoryTimestamps[$entryPath] ?? ['createdAt' => null, 'modifiedAt' => null];
    $directory .= $directoryEntry(
        (string) $node['name'],
        $type,
        $location['startSector'],
        $location['size'],
        $leftSiblings[$nodeIndex] ?? $free,
        $rightSiblings[$nodeIndex] ?? $free,
        $childIds[$nodeIndex] ?? $free,
        $timestamps['createdAt'],
        $timestamps['modifiedAt'],
        $timestamps['clsid'] ?? null,
        (int) ($timestamps['stateBits'] ?? 0),
        $nodeColors[$nodeIndex] ?? 1
    );
}
$directoryChunks = str_split($padTo($directory, $sectorSize), $sectorSize);
$previousDirectorySector = 1;
foreach ($directoryChunks as $index => $chunk) {
    if ($index === 0) {
        $sectors[1] = $chunk;
        continue;
    }

    $sector = count($sectors);
    $sectors[] = $chunk;
    $fat[$previousDirectorySector] = $sector;
    $fat[$sector] = $end;
    $previousDirectorySector = $sector;
}
$fat[$previousDirectorySector] = $end;

$miniFatBytes = '';
for ($index = 0, $count = count($miniFat); $index < $count; $index++) {
    $miniFatBytes .= $u32($miniFat[$index] ?? $free);
}
$sectors[2] = $padTo($miniFatBytes, $sectorSize);

$fatBytes = '';
for ($index = 0; $index < 128; $index++) {
    $fatBytes .= $u32($fat[$index] ?? $free);
}
$sectors[0] = substr($fatBytes, 0, $sectorSize);

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

$moveFatListingToDifatSector = static function (string $bytes) use ($u32, $sectorSize, $free, $end): array {
    $difatSector = intdiv(strlen($bytes) - 512, $sectorSize);
    if ($difatSector < 0 || $difatSector >= 128) {
        throw new RuntimeException('Legacy DOC handoff fixture requires the first FAT sector to cover the DIFAT overflow sector');
    }

    $difatBytes = $u32(0) . str_repeat($u32($free), 126) . $u32($end);
    $bytes = substr_replace($bytes, $u32(0xfffffffc), 512 + ($difatSector * 4), 4);
    $bytes = substr_replace($bytes, $u32($difatSector), 68, 4);
    $bytes = substr_replace($bytes, $u32(1), 72, 4);
    $bytes = substr_replace($bytes, str_repeat($u32($free), 109), 76, 109 * 4);
    $bytes .= $difatBytes;

    return [
        'bytes' => $bytes,
        'difatSector' => $difatSector,
    ];
};

$docBytes = str_pad($header, 512, "\0") . implode('', $sectors);
$difatFixture = $moveFatListingToDifatSector($docBytes);
$docBytes = $difatFixture['bytes'];
$difatSector = (int) $difatFixture['difatSector'];
$result = (new LegacyDocReader())->readBytes($docBytes);
$blocks = (new WordPressBlockWriter())->write($result['document']);

$summary = [
    'metadata' => $result['metadata'],
    'streams' => $result['streams'],
    'streamDirectory' => $result['streamDirectory'],
    'directoryEntries' => $result['directoryEntries'],
    'textSource' => $result['document']->attr('textSource'),
    'fib' => $result['fib'],
    'subdocuments' => $result['subdocuments'],
    'styles' => $result['styles'],
    'formattingRuns' => $result['formattingRuns'],
    'listFormats' => $result['listFormats'],
    'listOverrides' => $result['listOverrides'],
    'sections' => $result['sections'],
    'bookmarks' => $result['bookmarks'],
    'footnotes' => $result['footnotes'],
    'endnotes' => $result['endnotes'],
    'comments' => $result['comments'],
    'fieldCharacters' => $result['fieldCharacters'],
    'fields' => $result['fields'],
    'embeddedObjects' => $result['embeddedObjects'],
    'embeddedObjectReferences' => $result['embeddedObjectReferences'],
    'macroProjects' => $result['macroProjects'],
    'associatedStrings' => $result['associatedStrings'],
    'difatSector' => $difatSector,
    'blockCount' => count($result['document']->children),
    'wordpressBlocks' => $blocks,
];

if (($argv[1] ?? '') === '--self-test') {
    if ($difatSector <= 0) {
        throw new RuntimeException('Legacy DOC handoff self-test missing DIFAT overflow sector');
    }
    if (($summary['metadata']['title'] ?? '') !== 'Legacy DOC import packet') {
        throw new RuntimeException('Legacy DOC handoff self-test missing metadata title');
    }
    if (($summary['metadata']['creator'] ?? '') !== 'Migration Desk') {
        throw new RuntimeException('Legacy DOC handoff self-test missing metadata creator');
    }
    if (($summary['metadata']['subject'] ?? '') !== 'Legacy source packet') {
        throw new RuntimeException('Legacy DOC handoff self-test missing SttbfAssoc fallback subject');
    }
    if (($summary['metadata']['keywords'] ?? '') !== 'legacy,word,review') {
        throw new RuntimeException('Legacy DOC handoff self-test missing SttbfAssoc fallback keywords');
    }
    if (($summary['metadata']['associatedTemplatePath'] ?? '') !== 'C:\Templates\legacy-import.dot') {
        throw new RuntimeException('Legacy DOC handoff self-test missing associated template path');
    }
    if (($summary['metadata']['mailMergeDataSource'] ?? '') !== 'C:\Data\legacy-mailmerge.csv' || ($summary['metadata']['mailMergeHeaderDocument'] ?? '') !== 'C:\Data\legacy-header.doc') {
        throw new RuntimeException('Legacy DOC handoff self-test missing mail-merge associated paths');
    }
    if (($summary['metadata']['hasWriteReservationPassword'] ?? null) !== true || ($summary['metadata']['writeReservationPasswordCharacterCount'] ?? null) !== 11) {
        throw new RuntimeException('Legacy DOC handoff self-test missing redacted write-reservation password metadata');
    }
    if (($summary['metadata']['associatedStringCount'] ?? null) !== 9 || count($summary['associatedStrings'] ?? []) !== 9) {
        throw new RuntimeException('Legacy DOC handoff self-test missing associated string inventory');
    }
    if (($summary['associatedStrings'][8]['role'] ?? '') !== 'writeReservationPassword' || ($summary['associatedStrings'][8]['redacted'] ?? null) !== true || isset($summary['associatedStrings'][8]['value'])) {
        throw new RuntimeException('Legacy DOC handoff self-test exposed write-reservation password value');
    }
    if (($summary['metadata']['pageCount'] ?? null) !== 2 || ($summary['metadata']['wordCount'] ?? null) !== 12) {
        throw new RuntimeException('Legacy DOC handoff self-test missing SummaryInformation counts');
    }
    if (($summary['metadata']['category'] ?? '') !== 'Data Liberation import queue - legacy обзор') {
        throw new RuntimeException('Legacy DOC handoff self-test missing codepage-decoded review category');
    }
    if (($summary['metadata']['documentSecurityFlags'] ?? []) !== ['readOnlyRecommended']) {
        throw new RuntimeException('Legacy DOC handoff self-test missing document security flags');
    }
    if (($summary['metadata']['fibBase']['languageTag'] ?? '') !== 'en-US' || ($summary['metadata']['fibBase']['languageId'] ?? null) !== 0x0409) {
        throw new RuntimeException('Legacy DOC handoff self-test missing FIB language provenance');
    }
    if (($summary['metadata']['fibBase']['nFibBack'] ?? null) !== 0x00bf || ($summary['metadata']['fibBase']['quickSaveCount'] ?? null) !== 3) {
        throw new RuntimeException('Legacy DOC handoff self-test missing FIB version/quick-save provenance');
    }
    if (($summary['metadata']['fibBase']['flags'] ?? []) !== [
        'complex',
        'tableStream1',
        'readOnlyRecommended',
        'writeReservation',
        'extendedCharacters',
        'loadOverride',
    ]) {
        throw new RuntimeException('Legacy DOC handoff self-test missing FIB state flags');
    }
    $fibRgLw97 = $summary['metadata']['fibRgLw97'] ?? null;
    if (!is_array($fibRgLw97) || ($fibRgLw97['ccpText'] ?? null) !== $totalPieceCharacters) {
        throw new RuntimeException('Legacy DOC handoff self-test missing FibRgLw97 main-text count');
    }
    if (($fibRgLw97['cbMac'] ?? null) !== strlen($wordDocument)) {
        throw new RuntimeException('Legacy DOC handoff self-test missing FibRgLw97 cbMac boundary');
    }
    if (
        ($fibRgLw97['ccpFtn'] ?? null) !== $footnoteSubdocumentCharacters
        || ($fibRgLw97['ccpHdd'] ?? null) !== $headerSubdocumentCharacters
        || ($fibRgLw97['ccpAtn'] ?? null) !== $commentSubdocumentCharacters
        || ($fibRgLw97['ccpEdn'] ?? null) !== $endnoteSubdocumentCharacters
    ) {
        throw new RuntimeException('Legacy DOC handoff self-test missing FibRgLw97 supplemental subdocument counts');
    }
    if (($fibRgLw97['pieceTableExpectedLastCp'] ?? null) !== $pieceTableLastCp || ($fibRgLw97['hasSupplementalSubdocuments'] ?? null) !== true) {
        throw new RuntimeException('Legacy DOC handoff self-test missing FibRgLw97 piece-table boundary');
    }
    if (($fibRgLw97['subdocuments'] ?? []) !== [
        [
            'type' => 'main',
            'startCp' => 0,
            'endCp' => $totalPieceCharacters,
            'characterCount' => $totalPieceCharacters,
        ],
        [
            'type' => 'footnote',
            'startCp' => $footnoteSubdocumentCpStart,
            'endCp' => $headerSubdocumentCpStart,
            'characterCount' => $footnoteSubdocumentCharacters,
        ],
        [
            'type' => 'header',
            'startCp' => $headerSubdocumentCpStart,
            'endCp' => $commentSubdocumentCpStart,
            'characterCount' => $headerSubdocumentCharacters,
        ],
        [
            'type' => 'comment',
            'startCp' => $commentSubdocumentCpStart,
            'endCp' => $endnoteSubdocumentCpStart,
            'characterCount' => $commentSubdocumentCharacters,
        ],
        [
            'type' => 'endnote',
            'startCp' => $endnoteSubdocumentCpStart,
            'endCp' => $pieceTableLastCp,
            'characterCount' => $endnoteSubdocumentCharacters,
        ],
    ]) {
        throw new RuntimeException('Legacy DOC handoff self-test missing FibRgLw97 subdocument ranges');
    }
    if (($summary['metadata']['styleCount'] ?? null) !== 3 || ($summary['styles'][0]['name'] ?? '') !== 'Review Heading') {
        throw new RuntimeException('Legacy DOC handoff self-test missing stylesheet style inventory');
    }
    if (($summary['styles'][0]['aliases'] ?? []) !== ['Import Title'] || ($summary['styles'][0]['type'] ?? '') !== 'paragraph') {
        throw new RuntimeException('Legacy DOC handoff self-test missing stylesheet alias/type metadata');
    }
    if (($summary['styles'][1]['basedOnIstd'] ?? null) !== 15 || ($summary['styles'][2]['type'] ?? '') !== 'character') {
        throw new RuntimeException('Legacy DOC handoff self-test missing stylesheet relationship/type metadata');
    }
    if (($summary['metadata']['formattingRunCount'] ?? null) !== 3 || ($summary['metadata']['paragraphFormattingRunCount'] ?? null) !== 1) {
        throw new RuntimeException('Legacy DOC handoff self-test missing formatting table run counts');
    }
    if (($summary['metadata']['characterFormattingRunCount'] ?? null) !== 2 || ($summary['formattingRuns'][0]['table'] ?? '') !== 'PlcBtePapx') {
        throw new RuntimeException('Legacy DOC handoff self-test missing character/paragraph formatting table split');
    }
    if (($summary['formattingRuns'][0]['startFc'] ?? null) !== $firstPieceStart || ($summary['formattingRuns'][0]['endFc'] ?? null) !== $mainTextByteEnd) {
        throw new RuntimeException('Legacy DOC handoff self-test missing paragraph formatting FC range');
    }
    if (($summary['formattingRuns'][0]['fkpPage'] ?? null) !== $papxFkpPage || ($summary['formattingRuns'][0]['fkpRunCount'] ?? null) !== 2) {
        throw new RuntimeException('Legacy DOC handoff self-test missing paragraph FKP page provenance');
    }
    if (($summary['formattingRuns'][1]['table'] ?? '') !== 'PlcBteChpx' || ($summary['formattingRuns'][1]['endFc'] ?? null) !== $secondPieceStart) {
        throw new RuntimeException('Legacy DOC handoff self-test missing character formatting first-piece range');
    }
    if (($summary['formattingRuns'][2]['startFc'] ?? null) !== $secondPieceStart || ($summary['formattingRuns'][2]['fkpRunCount'] ?? null) !== 3) {
        throw new RuntimeException('Legacy DOC handoff self-test missing character formatting second-piece range');
    }
    if (($summary['formattingRuns'][0]['canApplyFormatting'] ?? null) !== false) {
        throw new RuntimeException('Legacy DOC handoff self-test should keep full SPRM formatting expansion disabled');
    }
    if (($summary['metadata']['listFormatCount'] ?? null) !== 2 || ($summary['metadata']['listLevelCount'] ?? null) !== 2) {
        throw new RuntimeException('Legacy DOC handoff self-test missing list format/level counts');
    }
    if (($summary['metadata']['listOverrideCount'] ?? null) !== 2 || count($summary['listOverrides'] ?? []) !== 2) {
        throw new RuntimeException('Legacy DOC handoff self-test missing list override counts');
    }
    if (($summary['listFormats'][0]['lsid'] ?? null) !== 1001 || ($summary['listFormats'][0]['linkedStyleIstds'][0]['istd'] ?? null) !== 15) {
        throw new RuntimeException('Legacy DOC handoff self-test missing ordered list LSTF metadata');
    }
    if (($summary['listFormats'][0]['levels'][0]['numberText'] ?? '') !== '%1.' || ($summary['listFormats'][0]['levels'][0]['follow'] ?? '') !== 'space') {
        throw new RuntimeException('Legacy DOC handoff self-test missing ordered list level metadata');
    }
    if (($summary['listFormats'][1]['levels'][0]['numberFormat'] ?? '') !== 'bullet' || ($summary['listFormats'][1]['levels'][0]['numberText'] ?? '') !== '•') {
        throw new RuntimeException('Legacy DOC handoff self-test missing bullet list level metadata');
    }
    if (($summary['listOverrides'][0]['autoNumberField'] ?? '') !== 'AUTONUM' || ($summary['listOverrides'][0]['levels'][0]['startAt'] ?? null) !== 7) {
        throw new RuntimeException('Legacy DOC handoff self-test missing list override start-at metadata');
    }
    if (($summary['listOverrides'][1]['firstParagraphCp'] ?? null) !== $firstPieceCharacters || ($summary['listOverrides'][0]['levels'][0]['formattingOverride'] ?? null) !== false) {
        throw new RuntimeException('Legacy DOC handoff self-test missing list override paragraph/start metadata');
    }
    if (($summary['listFormats'][0]['levels'][0]['canApplyNumbering'] ?? null) !== false) {
        throw new RuntimeException('Legacy DOC handoff self-test should keep legacy numbering application disabled');
    }
    if (($summary['metadata']['cfbStreamCount'] ?? null) !== 14 || ($summary['metadata']['cfbTimestampedDirectoryEntryCount'] ?? null) !== 2) {
        throw new RuntimeException('Legacy DOC handoff self-test missing CFB directory counts');
    }
    $directoryByPath = [];
    foreach ($summary['directoryEntries'] as $directoryEntry) {
        $directoryByPath[(string) ($directoryEntry['path'] ?? '')] = $directoryEntry;
    }
    if (($directoryByPath['']['type'] ?? '') !== 'root' || ($directoryByPath['']['modifiedAt'] ?? '') !== '2024-04-06T07:08:09Z') {
        throw new RuntimeException('Legacy DOC handoff self-test missing root storage modified timestamp');
    }
    if (($summary['metadata']['cfbClassIdDirectoryEntryCount'] ?? null) !== 2 || ($summary['metadata']['cfbStateBitsDirectoryEntryCount'] ?? null) !== 2) {
        throw new RuntimeException('Legacy DOC handoff self-test missing CFB CLSID/state-bit counts');
    }
    if (($directoryByPath['']['clsid'] ?? '') !== '00112233-4455-6677-8899-aabbccddeeff' || ($directoryByPath['']['stateBits'] ?? null) !== 0x40000001) {
        throw new RuntimeException('Legacy DOC handoff self-test missing root storage CLSID/state bits');
    }
    if (isset($directoryByPath['WordDocument']['createdAt']) || isset($directoryByPath['WordDocument']['modifiedAt'])) {
        throw new RuntimeException('Legacy DOC handoff self-test assigned timestamps to a stream entry');
    }
    if (isset($directoryByPath['WordDocument']['clsid']) || isset($directoryByPath['WordDocument']['stateBits'])) {
        throw new RuntimeException('Legacy DOC handoff self-test assigned CLSID/state bits to a stream entry');
    }
    if (($directoryByPath['ObjectPool/_42']['type'] ?? '') !== 'storage') {
        throw new RuntimeException('Legacy DOC handoff self-test missing ObjectPool storage directory entry');
    }
    if (($directoryByPath['ObjectPool/_42']['createdAt'] ?? '') !== '2024-04-07T08:09:10Z') {
        throw new RuntimeException('Legacy DOC handoff self-test missing ObjectPool storage creation timestamp');
    }
    if (($directoryByPath['ObjectPool/_42']['modifiedAt'] ?? '') !== '2024-04-08T09:10:11Z') {
        throw new RuntimeException('Legacy DOC handoff self-test missing ObjectPool storage modified timestamp');
    }
    if (($directoryByPath['ObjectPool/_42']['clsid'] ?? '') !== '00020906-0000-0000-c000-000000000046' || ($directoryByPath['ObjectPool/_42']['stateBits'] ?? null) !== 0x00000010) {
        throw new RuntimeException('Legacy DOC handoff self-test missing ObjectPool storage CLSID/state bits');
    }
    if (($summary['metadata']['lineCount'] ?? null) !== 2 || ($summary['metadata']['linksDirty'] ?? null) !== true) {
        throw new RuntimeException('Legacy DOC handoff self-test missing DocumentSummaryInformation review metadata');
    }
    if (($summary['metadata']['byteCount'] ?? null) !== 4096) {
        throw new RuntimeException('Legacy DOC handoff self-test missing unsigned byte-count metadata');
    }
    if (($summary['metadata']['documentParts'] ?? []) !== ['Overview', 'Reviewer notes', 'Source appendix']) {
        throw new RuntimeException('Legacy DOC handoff self-test missing document part titles');
    }
    if (($summary['metadata']['headingPairs'][0]['parts'] ?? []) !== ['Overview', 'Reviewer notes']) {
        throw new RuntimeException('Legacy DOC handoff self-test missing heading-pair section inventory');
    }
    if (($summary['metadata']['customProperties'] ?? []) !== [
        'MigrationBatch' => 'legacy-doc-42',
        'Needs Review' => true,
        'Source Id' => 4242,
        'Archive Bytes' => $archiveBytes,
        'Source Guid' => $sourceGuid,
        'Review Weight' => 1.25,
        'Confidence Score' => 0.875,
        'Invoice Total' => '1234.5678',
        'Review Date' => '2024-01-18T12:00:00Z',
    ]) {
        throw new RuntimeException('Legacy DOC handoff self-test missing user-defined custom properties');
    }
    if (($summary['metadata']['bookmarkCount'] ?? null) !== 1) {
        throw new RuntimeException('Legacy DOC handoff self-test missing standard bookmark count');
    }
    if (($summary['metadata']['sectionCount'] ?? null) !== 1) {
        throw new RuntimeException('Legacy DOC handoff self-test missing section descriptor count');
    }
    if (($summary['sections'][0]['hasSepx'] ?? null) !== true || ($summary['sections'][0]['sprmByteCount'] ?? null) !== 4) {
        throw new RuntimeException('Legacy DOC handoff self-test missing SEPX section provenance');
    }
    if (($summary['sections'][0]['startCp'] ?? null) !== 0 || ($summary['sections'][0]['endCp'] ?? null) !== $totalPieceCharacters) {
        throw new RuntimeException('Legacy DOC handoff self-test missing section CP range');
    }
    if (($summary['bookmarks'][0]['name'] ?? '') !== 'legacy_anchor' || ($summary['bookmarks'][0]['canAnchor'] ?? null) !== true) {
        throw new RuntimeException('Legacy DOC handoff self-test missing standard bookmark anchor metadata');
    }
    if (($summary['metadata']['footnoteReferenceCount'] ?? null) !== 1 || ($summary['metadata']['endnoteReferenceCount'] ?? null) !== 1) {
        throw new RuntimeException('Legacy DOC handoff self-test missing footnote/endnote reference counts');
    }
    if (($summary['footnotes'][0]['marker'] ?? '') !== '1' || ($summary['footnotes'][0]['autoNumbered'] ?? null) !== true) {
        throw new RuntimeException('Legacy DOC handoff self-test missing auto-numbered footnote metadata');
    }
    if (($summary['endnotes'][0]['marker'] ?? '') !== '#' || ($summary['endnotes'][0]['autoNumbered'] ?? null) !== false) {
        throw new RuntimeException('Legacy DOC handoff self-test missing custom endnote metadata');
    }
    if (($summary['metadata']['commentReferenceCount'] ?? null) !== 1) {
        throw new RuntimeException('Legacy DOC handoff self-test missing comment reference count');
    }
    if (($summary['comments'][0]['marker'] ?? '') !== 'MR' || ($summary['comments'][0]['authorInitials'] ?? '') !== 'MR') {
        throw new RuntimeException('Legacy DOC handoff self-test missing comment author initials');
    }
    if (($summary['comments'][0]['authorIndex'] ?? null) !== 3 || ($summary['comments'][0]['bookmarkTag'] ?? null) !== 0x2042) {
        throw new RuntimeException('Legacy DOC handoff self-test missing comment descriptor provenance');
    }
    $subdocumentsByType = [];
    foreach (($summary['subdocuments'] ?? []) as $subdocument) {
        $subdocumentsByType[(string) ($subdocument['type'] ?? '')] = $subdocument;
    }
    if (($summary['metadata']['subdocumentCount'] ?? null) !== 4 || count($subdocumentsByType) !== 4) {
        throw new RuntimeException('Legacy DOC handoff self-test missing supplemental subdocument text records');
    }
    if (
        ($subdocumentsByType['footnote']['text'] ?? '') !== $footnoteSubdocumentText
        || ($subdocumentsByType['header']['text'] ?? '') !== $headerSubdocumentText
        || ($subdocumentsByType['comment']['text'] ?? '') !== $commentSubdocumentText
        || ($subdocumentsByType['endnote']['text'] ?? '') !== $endnoteSubdocumentText
    ) {
        throw new RuntimeException('Legacy DOC handoff self-test missing supplemental subdocument body text');
    }
    if (($summary['footnotes'][0]['bodyText'] ?? '') !== substr($footnoteSubdocumentText, 0, 35)) {
        throw new RuntimeException('Legacy DOC handoff self-test missing bounded footnote body text');
    }
    if (($summary['endnotes'][0]['bodyText'] ?? '') !== substr($endnoteSubdocumentText, 0, 29)) {
        throw new RuntimeException('Legacy DOC handoff self-test missing bounded endnote body text');
    }
    if (($summary['comments'][0]['bodyText'] ?? '') !== substr($commentSubdocumentText, 0, 24)) {
        throw new RuntimeException('Legacy DOC handoff self-test missing bounded comment body text');
    }
    if (($summary['metadata']['embeddedObjectCount'] ?? null) !== 1) {
        throw new RuntimeException('Legacy DOC handoff self-test missing embedded object count');
    }
    if (($summary['embeddedObjects'][0]['storagePath'] ?? '') !== 'ObjectPool/_42') {
        throw new RuntimeException('Legacy DOC handoff self-test missing ObjectPool storage report');
    }
    if (($summary['embeddedObjects'][0]['transmissionFormat'] ?? []) !== ['code' => 0x0014, 'name' => 'unicode-text']) {
        throw new RuntimeException('Legacy DOC handoff self-test missing ObjInfo transmission format');
    }
    if (($summary['embeddedObjects'][0]['compoundObjectDisplayNames'] ?? []) !== ['Legacy Package Ω']) {
        throw new RuntimeException('Legacy DOC handoff self-test missing CompObj display-name metadata');
    }
    if (($summary['embeddedObjects'][0]['compoundObjectClipboardFormats'] ?? []) !== ['Excel.Sheet.12']) {
        throw new RuntimeException('Legacy DOC handoff self-test missing CompObj clipboard-format metadata');
    }
    if (($summary['embeddedObjects'][0]['hasNativeData'] ?? null) !== true || ($summary['embeddedObjects'][0]['hasPresentationData'] ?? null) !== true) {
        throw new RuntimeException('Legacy DOC handoff self-test missing embedded object stream roles');
    }
    if (($summary['embeddedObjects'][0]['nativeDataBytes'] ?? null) !== strlen($embeddedNativeData)) {
        throw new RuntimeException('Legacy DOC handoff self-test missing Ole10Native native-data byte count');
    }
    if (($summary['embeddedObjects'][0]['nativeLabels'] ?? []) !== ['legacy-data.xlsx']) {
        throw new RuntimeException('Legacy DOC handoff self-test missing Ole10Native display label');
    }
    if (($summary['embeddedObjects'][0]['nativeSourcePaths'] ?? []) !== ['C:\legacy\legacy-data.xlsx']) {
        throw new RuntimeException('Legacy DOC handoff self-test missing Ole10Native source path');
    }
    if (($summary['embeddedObjects'][0]['nativeTemporaryPaths'] ?? []) !== ['C:\Temp\legacy-data.tmp']) {
        throw new RuntimeException('Legacy DOC handoff self-test missing Ole10Native temporary path');
    }
    $compoundStream = null;
    $nativeStream = null;
    foreach (($summary['embeddedObjects'][0]['streams'] ?? []) as $stream) {
        if (($stream['role'] ?? '') === 'compound-object') {
            $compoundStream = $stream;
        }
        if (($stream['role'] ?? '') === 'native-data') {
            $nativeStream = $stream;
        }
    }
    if (($compoundStream['compoundObject']['displayName'] ?? '') !== 'Legacy Package Ω') {
        throw new RuntimeException('Legacy DOC handoff self-test missing CompObj stream display name');
    }
    if (($compoundStream['compoundObject']['clipboardFormat'] ?? []) !== ['kind' => 'registered', 'name' => 'Excel.Sheet.12']) {
        throw new RuntimeException('Legacy DOC handoff self-test missing CompObj stream clipboard format');
    }
    if (($nativeStream['oleNative']['nativeDataBytes'] ?? null) !== strlen($embeddedNativeData)) {
        throw new RuntimeException('Legacy DOC handoff self-test missing Ole10Native stream metadata');
    }
    if (($summary['embeddedObjects'][0]['canExposeBytes'] ?? null) !== false) {
        throw new RuntimeException('Legacy DOC handoff self-test exposed embedded object bytes');
    }
    if (($summary['metadata']['embeddedObjectReferenceCount'] ?? null) !== 1 || count($summary['embeddedObjectReferences'] ?? []) !== 1) {
        throw new RuntimeException('Legacy DOC handoff self-test missing embedded object reference count');
    }
    if (($summary['embeddedObjectReferences'][0]['storagePath'] ?? '') !== 'ObjectPool/_42' || ($summary['embeddedObjectReferences'][0]['label'] ?? '') !== 'legacy-data.xlsx') {
        throw new RuntimeException('Legacy DOC handoff self-test missing embedded object reference metadata');
    }
    if (($summary['embeddedObjectReferences'][0]['canExposeBytes'] ?? null) !== false || ($summary['embeddedObjectReferences'][0]['nativeDataBytes'] ?? null) !== strlen($embeddedNativeData)) {
        throw new RuntimeException('Legacy DOC handoff self-test exposed or lost embedded object reference byte policy');
    }
    if (($summary['metadata']['containsMacros'] ?? null) !== true || ($summary['metadata']['macroProjectCount'] ?? null) !== 1) {
        throw new RuntimeException('Legacy DOC handoff self-test missing macro project preflight metadata');
    }
    if (($summary['metadata']['macroPolicy'] ?? '') !== 'disabled-native-review') {
        throw new RuntimeException('Legacy DOC handoff self-test missing disabled macro policy');
    }
    if (($summary['macroProjects'][0]['storagePath'] ?? '') !== 'Macros') {
        throw new RuntimeException('Legacy DOC handoff self-test missing Macros storage report');
    }
    if (($summary['macroProjects'][0]['policy'] ?? '') !== 'macro-execution-disabled' || ($summary['macroProjects'][0]['canExecute'] ?? null) !== false) {
        throw new RuntimeException('Legacy DOC handoff self-test did not disable macro execution');
    }
    if (($summary['macroProjects'][0]['canExposeBytes'] ?? null) !== false) {
        throw new RuntimeException('Legacy DOC handoff self-test exposed macro project bytes');
    }
    if (($summary['macroProjects'][0]['moduleStreams'] ?? []) !== ['MigrationTools', 'ThisDocument']) {
        throw new RuntimeException('Legacy DOC handoff self-test missing macro module stream inventory');
    }
    if (($summary['macroProjects'][0]['hasDirStream'] ?? null) !== true || ($summary['macroProjects'][0]['hasPerformanceCache'] ?? null) !== true) {
        throw new RuntimeException('Legacy DOC handoff self-test missing VBA project stream roles');
    }
    if (($summary['textSource'] ?? '') !== 'piece-table' || ($summary['fib']['complex'] ?? null) !== true || ($summary['fib']['tableStream'] ?? '') !== '1Table') {
        throw new RuntimeException('Legacy DOC handoff self-test missing CLX piece-table preflight');
    }
    if (($summary['metadata']['fieldCharacterCount'] ?? null) !== count($fieldRecords) || count($summary['fieldCharacters'] ?? []) !== count($fieldRecords)) {
        throw new RuntimeException('Legacy DOC handoff self-test missing Plcfld field-character inventory');
    }
    if (($summary['metadata']['fieldCount'] ?? null) !== 7 || count($summary['fields'] ?? []) !== 7) {
        throw new RuntimeException('Legacy DOC handoff self-test missing Plcfld field range inventory');
    }
    if (($summary['metadata']['fields'] ?? []) !== ($summary['fields'] ?? [])) {
        throw new RuntimeException('Legacy DOC handoff self-test missing Plcfld fields in metadata');
    }
    if (array_column($summary['fields'] ?? [], 'type') !== [
        'hyperlink',
        'hyperlink',
        'ref',
        'pageref',
        'page',
        'formtext',
        'symbol',
    ]) {
        throw new RuntimeException('Legacy DOC handoff self-test missing Plcfld field type mapping');
    }
    if (($summary['fieldCharacters'][0]['kind'] ?? '') !== 'begin' || ($summary['fieldCharacters'][0]['type'] ?? '') !== 'hyperlink') {
        throw new RuntimeException('Legacy DOC handoff self-test missing first Plcfld begin record');
    }
    if (($summary['fields'][5]['typeCode'] ?? null) !== 0x46 || ($summary['fields'][5]['hasResult'] ?? null) !== true) {
        throw new RuntimeException('Legacy DOC handoff self-test missing FORMTEXT Plcfld result range');
    }
    foreach ([
        '<p><span id="legacy_anchor" class="legacy-doc-bookmark" data-legacy-doc-bookmark="legacy_anchor" data-legacy-doc-bookmark-start-cp="0" data-legacy-doc-bookmark-end-cp="21">Legacy DOC import ΩЖ魚</span></p>',
        '<p>Reviewer notes keep hard<br/>breaks for block review with note ',
        '<span class="legacy-doc-note-ref legacy-doc-footnote-ref" data-legacy-doc-note-type="footnote" data-legacy-doc-note-index="1" data-legacy-doc-note-reference-cp="' . (string) ($summary['footnotes'][0]['referenceCp'] ?? '') . '" data-legacy-doc-note-text-start-cp="0" data-legacy-doc-note-text-end-cp="35" data-legacy-doc-note-auto-numbered="true" data-legacy-doc-note-has-body="true" data-legacy-doc-note-body-character-count="35"><sup>1</sup></span>',
        '<span class="legacy-doc-note-ref legacy-doc-endnote-ref" data-legacy-doc-note-type="endnote" data-legacy-doc-note-index="0" data-legacy-doc-note-reference-cp="' . (string) ($summary['endnotes'][0]['referenceCp'] ?? '') . '" data-legacy-doc-note-text-start-cp="0" data-legacy-doc-note-text-end-cp="29" data-legacy-doc-note-auto-numbered="false" data-legacy-doc-note-has-body="true" data-legacy-doc-note-body-character-count="29"><sup>#</sup></span>',
        '<span class="legacy-doc-comment-ref" data-legacy-doc-comment-index="1" data-legacy-doc-comment-reference-cp="' . (string) ($summary['comments'][0]['referenceCp'] ?? '') . '" data-legacy-doc-comment-text-start-cp="0" data-legacy-doc-comment-text-end-cp="24" data-legacy-doc-comment-author-index="3" data-legacy-doc-comment-author-initials="MR" data-legacy-doc-comment-bookmark-tag="8258" data-legacy-doc-comment-has-body="true" data-legacy-doc-comment-body-character-count="24"><sup>MR</sup></span>',
        '<a href="https://example.test/legacy-doc?source=42" title="Source packet">source dossier</a>',
        '<a href="#legacy_anchor">opening bookmark</a>',
        '<span class="legacy-doc-field legacy-doc-cross-reference legacy-doc-field-ref" data-legacy-doc-field="ref" data-legacy-doc-field-instruction="REF &quot;legacy_anchor&quot; \h" data-legacy-doc-cross-reference-type="bookmark" data-legacy-doc-cross-reference-target="legacy_anchor" data-legacy-doc-cross-reference-switches="h" data-legacy-doc-cross-reference-hyperlink="true">Legacy DOC import</span>',
        '<span class="legacy-doc-field legacy-doc-cross-reference legacy-doc-field-pageref" data-legacy-doc-field="pageref" data-legacy-doc-field-instruction="PAGEREF legacy_anchor \p" data-legacy-doc-cross-reference-type="bookmark-page" data-legacy-doc-cross-reference-target="legacy_anchor" data-legacy-doc-cross-reference-switches="p" data-legacy-doc-cross-reference-relative="true">7</span>',
        '<span class="legacy-doc-field legacy-doc-field-page" data-legacy-doc-field="page" data-legacy-doc-field-instruction="PAGE \* Arabic" data-legacy-doc-field-format="Arabic">7</span>',
        '<span class="legacy-doc-field legacy-doc-form-field legacy-doc-field-formtext" data-legacy-doc-field="formtext" data-legacy-doc-field-instruction="FORMTEXT \* MERGEFORMAT" data-legacy-doc-form-field-type="text" data-legacy-doc-field-format="MERGEFORMAT">pending review</span>',
        '<span class="legacy-doc-field legacy-doc-symbol-field legacy-doc-field-symbol" data-legacy-doc-field="symbol" data-legacy-doc-field-instruction="SYMBOL 183 \f &quot;Symbol&quot; \s 12 \u" data-legacy-doc-symbol-code="183" data-legacy-doc-symbol-font="Symbol" data-legacy-doc-symbol-size="12" data-legacy-doc-symbol-switches="u">·</span>',
        '<span class="legacy-doc-object-ref" data-legacy-doc-object-ref="1" data-legacy-doc-object-reference-cp="' . (string) ($summary['embeddedObjectReferences'][0]['referenceCp'] ?? '') . '" data-legacy-doc-object-character-code="1" data-legacy-doc-object-can-expose-bytes="false" data-legacy-doc-object-storage="ObjectPool/_42" data-legacy-doc-object-id="_42" data-legacy-doc-object-label="legacy-data.xlsx" data-legacy-doc-object-native-data-bytes="' . strlen($embeddedNativeData) . '" data-legacy-doc-object-transmission-format="unicode-text" data-legacy-doc-object-has-native-data="true" data-legacy-doc-object-has-presentation-data="true">embedded object: legacy-data.xlsx</span>',
    ] as $needle) {
        if (!str_contains($blocks, $needle)) {
            throw new RuntimeException('Legacy DOC handoff self-test missing: ' . $needle);
        }
    }
    foreach (['HYPERLINK', 'REF', 'PAGEREF', 'FORMTEXT', 'SYMBOL'] as $instruction) {
        if (str_contains(strip_tags($blocks), $instruction)) {
            throw new RuntimeException('Legacy DOC handoff self-test rendered hidden field instruction: ' . $instruction);
        }
    }
    if (str_contains($blocks, 'HYPERLINK')) {
        throw new RuntimeException('Legacy DOC handoff self-test rendered hidden field instructions');
    }
    if (str_contains($blocks, "\x01")) {
        throw new RuntimeException('Legacy DOC handoff self-test rendered embedded object placeholder control character');
    }
    foreach ([$footnoteSubdocumentText, $headerSubdocumentText, $commentSubdocumentText, $endnoteSubdocumentText] as $supplementalSubdocumentText) {
        if (str_contains($blocks, trim($supplementalSubdocumentText))) {
            throw new RuntimeException('Legacy DOC handoff self-test rendered supplemental subdocument text');
        }
    }
    if (str_contains($blocks, $embeddedNativeData) || str_contains($blocks, 'opaque embedded object presentation preview')) {
        throw new RuntimeException('Legacy DOC handoff self-test rendered embedded object payload bytes');
    }
    if (str_contains($blocks, 'Document_Open') || str_contains($blocks, 'ImportPacket')) {
        throw new RuntimeException('Legacy DOC handoff self-test rendered macro module payload bytes');
    }
    foreach (['review-lock', 'legacy-mailmerge.csv', 'legacy-header.doc'] as $hiddenAssociatedString) {
        if (str_contains($blocks, $hiddenAssociatedString)) {
            throw new RuntimeException('Legacy DOC handoff self-test rendered associated metadata string: ' . $hiddenAssociatedString);
        }
    }
    if (($summary['fib']['extendedCharacters'] ?? null) !== true || ($summary['fib']['encrypted'] ?? null) !== false) {
        throw new RuntimeException('Legacy DOC handoff self-test missing FIB preflight flags');
    }

    $directorySectorOffset = 512 + 512;
    $directoryFieldOffset = static fn (int $directoryId, int $fieldOffset): int => $directorySectorOffset + ($directoryId * 128) + $fieldOffset;
    $wordDocumentDirectoryId = (int) $nodeByPath['WordDocument'];
    $objectPoolDirectoryId = (int) $nodeByPath['ObjectPool'];
    $wordDocumentMiniStreamOffset = 512
        + ($rootMiniStart * $sectorSize)
        + ((int) $locations['WordDocument']['startSector'] * $miniSectorSize);
    $redDirectoryId = null;
    foreach ($nodeColors as $directoryId => $colorFlag) {
        if ((int) $directoryId !== 0 && (int) $colorFlag === 0) {
            $redDirectoryId = (int) $directoryId;
            break;
        }
    }
    if ($redDirectoryId === null) {
        throw new RuntimeException('Legacy DOC handoff self-test fixture did not produce a red CFB directory node');
    }
    $orphanedActiveDirectoryEntry = substr_replace($docBytes, $u32($wordDocumentDirectoryId), $directoryFieldOffset(0, 76), 4);
    $orphanedActiveDirectoryEntry = substr_replace($orphanedActiveDirectoryEntry, $u32($free), $directoryFieldOffset($wordDocumentDirectoryId, 68), 4);
    $orphanedActiveDirectoryEntry = substr_replace($orphanedActiveDirectoryEntry, $u32($free), $directoryFieldOffset($wordDocumentDirectoryId, 72), 4);
    $orphanedActiveDirectoryEntry = substr_replace($orphanedActiveDirectoryEntry, "\x01", $directoryFieldOffset($wordDocumentDirectoryId, 67), 1);
    foreach ([
        'unsupported CFB major version' => substr_replace($docBytes, $u16(5), 26, 2),
        'version 3 CFB directory-sector count' => substr_replace($docBytes, $u32(1), 40, 4),
        'non-null CFB header CLSID' => substr_replace($docBytes, "\x01", 8, 1),
        'nonzero CFB header reserved bytes' => substr_replace($docBytes, "\x01\0\0\0\0\0", 34, 6),
        'invalid CFB mini stream cutoff' => substr_replace($docBytes, $u32(2048), 56, 4),
        'CFB MiniFAT start sector without MiniFAT count' => substr_replace($docBytes, $u32(0), 64, 4),
        'CFB MiniFAT count without valid start sector' => substr_replace($docBytes, $u32($end), 60, 4),
        'CFB DIFAT start sector without DIFAT count' => substr_replace($docBytes, $u32(0), 72, 4),
        'unterminated CFB DIFAT overflow chain' => substr_replace($docBytes, $u32(0), 512 + ($difatSector * $sectorSize) + ($sectorSize - 4), 4),
        'CFB root sibling directory reference' => substr_replace($docBytes, $u32($wordDocumentDirectoryId), $directoryFieldOffset(0, 68), 4),
        'CFB stream child directory reference' => substr_replace($docBytes, $u32($objectPoolDirectoryId), $directoryFieldOffset($wordDocumentDirectoryId, 76), 4),
        'CFB storage stream-data bytes' => substr_replace($docBytes, $u64(64), $directoryFieldOffset($objectPoolDirectoryId, 120), 8),
        'red CFB sibling-tree root' => substr_replace($docBytes, "\0", $directoryFieldOffset((int) ($childIds[0] ?? $wordDocumentDirectoryId), 67), 1),
        'unequal CFB sibling-tree black height' => substr_replace($docBytes, "\x01", $directoryFieldOffset($redDirectoryId, 67), 1),
        'duplicate CFB FAT sector' => substr_replace(substr_replace($docBytes, $u32(2), 44, 4), $u32(0), 80, 4),
        'misclassified CFB FAT sector' => substr_replace($docBytes, $u32($end), 512, 4),
        'CFB root mini stream reuses directory sector' => substr_replace($docBytes, $u32(1), $directoryFieldOffset(0, 116), 4),
        'CFB orphaned active directory entry' => $orphanedActiveDirectoryEntry,
        'invalid CFB root storage name' => substr_replace($docBytes, "X\0", 1024, 2),
        'complex DOC missing CLX piece table' => substr_replace($docBytes, $u32(0), $wordDocumentMiniStreamOffset + 0x01a6, 4),
    ] as $label => $corruptDocBytes) {
        try {
            (new LegacyDocReader())->readBytes($corruptDocBytes);
        } catch (InvalidArgumentException | RuntimeException) {
            continue;
        }

        throw new RuntimeException('Legacy DOC handoff self-test accepted corrupt header: ' . $label);
    }

    echo "legacy doc handoff self-test ok\n";
    return;
}

echo json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
