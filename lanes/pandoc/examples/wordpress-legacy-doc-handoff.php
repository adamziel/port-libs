<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\Pandoc\CompoundFileBinary;
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
$xstz = static function (string $text) use ($u16, $utf16le): string {
    $encoded = $utf16le($text);

    return $u16(intdiv(strlen($encoded), 2)) . $encoded . $u16(0);
};
$sttbUnicode = static function (array $strings) use ($u16, $utf16le): string {
    $bytes = $u16(0xffff) . $u16(count($strings)) . $u16(0);
    foreach ($strings as $string) {
        $encoded = $utf16le((string) $string);
        $bytes .= $u16(intdiv(strlen($encoded), 2)) . $encoded;
    }

    return $bytes;
};
$ffData = static function (array $options) use ($u16, $u32, $xstz, $sttbUnicode): string {
    $fieldType = (string) ($options['fieldType'] ?? 'text');
    $fieldTypeCode = match ($fieldType) {
        'text' => 0,
        'checkbox' => 1,
        'dropdown' => 2,
        default => throw new RuntimeException('Unsupported FFData fixture field type'),
    };

    $currentStateCode = (int) ($options['currentStateCode'] ?? 0);
    $bits = $fieldTypeCode
        | (($currentStateCode & 0x1f) << 2)
        | (!empty($options['hasOwnHelpText']) ? (1 << 7) : 0)
        | (!empty($options['hasOwnStatusText']) ? (1 << 8) : 0)
        | (!empty($options['protected']) ? (1 << 9) : 0)
        | (!empty($options['checkboxAutoSize']) ? (1 << 10) : 0)
        | (((int) ($options['textTypeCode'] ?? 0) & 0x07) << 11)
        | (!empty($options['recalculateOnExit']) ? (1 << 14) : 0)
        | ($fieldType === 'dropdown' ? (1 << 15) : 0);

    $bytes = $u32(0xffffffff)
        . $u16($bits)
        . $u16($fieldType === 'text' ? (int) ($options['maxLength'] ?? 0) : 0)
        . $u16($fieldType === 'checkbox' ? (int) ($options['checkboxSizeHalfPoints'] ?? 20) : 0)
        . $xstz((string) ($options['name'] ?? ''));

    if ($fieldType === 'text') {
        $bytes .= $xstz((string) ($options['defaultText'] ?? ''));
    } else {
        $bytes .= $u16((int) ($options['defaultStateCode'] ?? 0));
    }

    $bytes .= $xstz((string) ($options['textFormat'] ?? ''))
        . $xstz((string) ($options['helpText'] ?? ''))
        . $xstz((string) ($options['statusText'] ?? ''))
        . $xstz((string) ($options['entryMacro'] ?? ''))
        . $xstz((string) ($options['exitMacro'] ?? ''));

    if ($fieldType === 'dropdown') {
        $bytes .= $sttbUnicode($options['dropDownItems'] ?? []);
    }

    return $bytes;
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
$unallocatedDirectoryEntry = static function () use ($u32): string {
    return str_repeat("\0", 68)
        . $u32(0xffffffff)
        . $u32(0xffffffff)
        . $u32(0xffffffff)
        . str_repeat("\0", 48);
};
$padDirectoryEntries = static function (string $directory, int $sectorSize) use ($unallocatedDirectoryEntry): string {
    if ((strlen($directory) % 128) !== 0) {
        throw new RuntimeException('CFB fixture directory entries must be 128-byte aligned');
    }

    while ((strlen($directory) % $sectorSize) !== 0) {
        $directory .= $unallocatedDirectoryEntry();
    }

    return $directory;
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
$makeDirectoryEntry = $directoryEntry;

$typedLpstr = static function (string $value): string {
    $bytes = $value . "\0";
    $raw = pack('v', 0x001e) . "\0\0" . pack('V', strlen($bytes)) . $bytes;

    return str_pad($raw, (int) (ceil(strlen($raw) / 4) * 4), "\0");
};
$typedLpwstr = static function (string $value) use ($utf16le): string {
    $bytes = $utf16le($value . "\0");
    $raw = pack('v', 0x001f) . "\0\0" . pack('V', intdiv(strlen($bytes), 2)) . $bytes;

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
$typedBlob = static function (string $payload) use ($u32): string {
    $raw = pack('v', 0x0041) . "\0\0" . $u32(strlen($payload)) . $payload;

    return str_pad($raw, (int) (ceil(strlen($raw) / 4) * 4), "\0");
};
$typedUnicodeBlob = static function (string $value) use ($typedBlob, $utf16le): string {
    return $typedBlob($utf16le($value . "\0"));
};
$vtLpwstr = static function (string $value) use ($u32, $utf16le): string {
    $bytes = $utf16le($value . "\0");
    $raw = pack('v', 0x001f) . "\0\0" . $u32(intdiv(strlen($bytes), 2)) . $bytes;

    return str_pad($raw, (int) (ceil(strlen($raw) / 4) * 4), "\0");
};
$typedHyperlinks = static function (array $links) use ($typedBlob, $typedI4, $u32, $vtLpwstr): string {
    $payload = $u32(count($links) * 6);
    foreach ($links as $link) {
        $payload .= $typedI4((int) ($link['hash'] ?? 0))
            . $typedI4((int) ($link['app'] ?? 0))
            . $typedI4((int) ($link['shapeId'] ?? 0))
            . $typedI4((int) ($link['info'] ?? 0))
            . $vtLpwstr((string) ($link['target'] ?? ''))
            . $vtLpwstr((string) ($link['location'] ?? ''));
    }

    return $typedBlob($payload);
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
$typedUnicodeDictionary = static function (array $names) use ($utf16le): string {
    $raw = pack('V', count($names));
    foreach ($names as $propertyId => $name) {
        $bytes = $utf16le((string) $name . "\0");
        $raw .= pack('V', (int) $propertyId) . pack('V', intdiv(strlen($bytes), 2)) . $bytes;
        $raw = str_pad($raw, (int) (ceil(strlen($raw) / 4) * 4), "\0");
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
$propertySetValueOffset = static function (string $propertySet, int $propertyId): int {
    $sectionOffset = 48;
    $propertyCount = unpack('Vvalue', substr($propertySet, $sectionOffset + 4, 4))['value'];
    for ($index = 0; $index < $propertyCount; $index++) {
        $entryOffset = $sectionOffset + 8 + ($index * 8);
        $id = unpack('Vvalue', substr($propertySet, $entryOffset, 4))['value'];
        if ($id === $propertyId) {
            $relativeOffset = unpack('Vvalue', substr($propertySet, $entryOffset + 4, 4))['value'];

            return $sectionOffset + $relativeOffset;
        }
    }

    throw new RuntimeException('Legacy DOC handoff fixture property not found');
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
$stwUser = static function (array $variables) use ($u16, $u32, $utf16le): string {
    $bytes = $u16(0xffff) . $u16(count($variables)) . $u16(4);
    foreach ($variables as $variable) {
        $nameBytes = $utf16le((string) $variable['name']);
        $bytes .= $u16(intdiv(strlen($nameBytes), 2)) . $nameBytes . $u32((int) ($variable['extra'] ?? 0));
    }
    foreach ($variables as $variable) {
        $valueBytes = $utf16le((string) $variable['value']);
        $bytes .= $u16(intdiv(strlen($valueBytes), 2)) . $valueBytes;
    }

    return $bytes;
};
$sttbSavedBy = static function (array $pairs) use ($u16, $utf16le): string {
    $strings = [];
    foreach ($pairs as $pair) {
        $strings[] = (string) $pair['author'];
        $strings[] = (string) $pair['path'];
    }

    $bytes = $u16(0xffff) . $u16(count($strings)) . $u16(0);
    foreach ($strings as $string) {
        $encoded = $utf16le($string);
        $bytes .= $u16(intdiv(strlen($encoded), 2)) . $encoded;
    }

    return $bytes;
};
$sttbFnm = static function (array $references) use ($u16, $utf16le): string {
    $bytes = $u16(0xffff) . $u16(count($references)) . $u16(8);
    foreach ($references as $reference) {
        $path = (string) $reference['path'];
        $encoded = $utf16le($path);
        $referenceTypeCode = (int) ($reference['referenceTypeCode'] ?? 5);
        $documentIndex = (int) ($reference['documentIndex'] ?? 0);
        $fnpi = (($documentIndex & 0x0fff) << 4) | ($referenceTypeCode & 0x000f);
        $bytes .= $u16(intdiv(strlen($encoded), 2))
            . $encoded
            . $u16($fnpi)
            . chr(((int) ($reference['ichRelative'] ?? 0xff)) & 0xff)
            . chr(((int) ($reference['fnfb'] ?? 0)) & 0xff)
            . str_repeat("\0", 4);
    }

    return $bytes;
};
$pms = static function (array $options = []) use ($u16, $u32, $utf16le): string {
    $sourceRecord = static function (array $source) use ($u16): string {
        $sourceCode = (int) ($source['sourceCode'] ?? 0xff);
        $flags = (!empty($source['linkToFilename']) ? 0x01 : 0)
            | (!empty($source['linkToConnectionString']) ? 0x02 : 0)
            | (!empty($source['noPromptQuery']) ? 0x04 : 0)
            | (!empty($source['query']) ? 0x08 : 0);
        $documentIndex = (int) ($source['documentIndex'] ?? 0);
        $referenceTypeCode = (int) ($source['referenceTypeCode'] ?? ($sourceCode === 0xff ? 0 : 3));
        $fnpi = (($documentIndex & 0x0fff) << 4) | ($referenceTypeCode & 0x000f);

        return chr($sourceCode & 0xff)
            . chr($flags & 0xff)
            . $u16((int) ($source['fieldToken'] ?? 0))
            . $u16((int) ($source['recordToken'] ?? 0))
            . $u16($fnpi);
    };

    $wpms = (int) ($options['state'] ?? (
        0x0001
        | 0x0002
        | (0x01 << 3)
        | (1 << 10)
        | (1 << 11)
        | (0x02 << 13)
    ));
    $sources = $options['sources'] ?? [];
    $source0 = is_array($sources[0] ?? null) ? $sources[0] : ['sourceCode' => 0xff];
    $source1 = is_array($sources[1] ?? null) ? $sources[1] : ['sourceCode' => 0xff];
    $sqlQuery = (string) ($options['sqlQuery'] ?? '');
    $sqlBytes = $sqlQuery === '' ? '' : $utf16le($sqlQuery . "\0");
    $rfs = (int) ($options['recordFilter'] ?? (
        0x0001
        | (0x02 << 1)
        | (1 << 3)
        | (1 << 4)
        | (1 << 6)
        | (1 << 7)
    ));

    $bytes = $u16($wpms)
        . chr((int) ($options['headerFieldSourceIndex'] ?? 0))
        . chr((int) ($options['dataFetchSourceIndex'] ?? 0))
        . $u32((int) ($options['currentRecordIndex'] ?? 7))
        . $sourceRecord($source0)
        . $sourceRecord($source1)
        . $u32($rfs)
        . $u16(strlen($sqlBytes))
        . $sqlBytes;

    $recordFilterStrings = array_values(array_map(
        static fn (mixed $value): string => (string) $value,
        $options['recordFilterStrings'] ?? []
    ));
    if ($recordFilterStrings !== []) {
        $bytes = substr_replace($bytes, $u32($rfs | (1 << 16)), 24, 4);
        $bytes .= $u16(0xffff) . $u16(count($recordFilterStrings)) . $u16(0);
        foreach ($recordFilterStrings as $string) {
            $encoded = $utf16le($string);
            $bytes .= $u16(intdiv(strlen($encoded), 2)) . $encoded;
        }
    }

    return $bytes;
};
$sttbfCaption = static function (array $captions) use ($u16, $utf16le): string {
    $bytes = $u16(0xffff) . $u16(count($captions)) . $u16(6);
    foreach ($captions as $caption) {
        $label = (string) $caption['label'];
        $encoded = $utf16le($label);
        $flags = ((int) ($caption['insertLocationCode'] ?? 0) & 0x0003)
            | (!empty($caption['includeChapterNumber']) ? (1 << 2) : 0)
            | (((int) ($caption['headingLevel'] ?? 0) & 0x000f) << 3)
            | (!empty($caption['noLabel']) ? (1 << 15) : 0);
        $bytes .= $u16(intdiv(strlen($encoded), 2))
            . $encoded
            . $u16($flags)
            . $u16((int) ($caption['numberFormatCode'] ?? 0))
            . $u16((int) ($caption['chapterSeparatorCode'] ?? 0));
    }

    return $bytes;
};
$sttbfAutoCaption = static function (array $rules) use ($u16, $utf16le): string {
    $bytes = $u16(0xffff) . $u16(count($rules)) . $u16(2);
    foreach ($rules as $rule) {
        $progId = (string) $rule['progId'];
        $encoded = $utf16le($progId);
        $bytes .= $u16(intdiv(strlen($encoded), 2))
            . $encoded
            . $u16((int) $rule['captionIndex']);
    }

    return $bytes;
};
$routeSlip = static function (array $recipients, array $options = []) use ($u16): string {
    $ansi = static function (string $value) use ($u16): string {
        return $u16(strlen($value)) . $value;
    };
    $bytes = $u16(!empty($options['routed']) ? 1 : 0)
        . $u16(!empty($options['returnOriginal']) ? 1 : 0)
        . $u16(!empty($options['trackStatus']) ? 1 : 0)
        . $u16(!empty($options['dirty']) ? 1 : 0)
        . $u16((int) ($options['protect'] ?? 0))
        . $u16((int) ($options['stage'] ?? 0))
        . $u16((int) ($options['deliveryOption'] ?? 0))
        . $u16(count($recipients))
        . $ansi((string) ($options['subject'] ?? ''))
        . $ansi((string) ($options['message'] ?? ''))
        . $ansi((string) ($options['status'] ?? ''))
        . $ansi((string) ($options['title'] ?? ''));
    foreach ($recipients as $recipient) {
        $entryId = (string) ($recipient['entryId'] ?? '');
        $name = (string) ($recipient['name'] ?? '');
        $bytes .= $u16(strlen($entryId)) . $u16(strlen($name)) . $entryId . $name;
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
        $bytes .= chr((int) $record['character']) . chr((int) ($record['flags'] ?? $record['endFlags'] ?? $record['typeCode'] ?? 0));
    }

    return $bytes;
};
$dttm = static function (int $year, int $month, int $day, int $hour, int $minute, int $weekday = 0): int {
    return ($minute & 0x3f)
        | (($hour & 0x1f) << 6)
        | (($day & 0x1f) << 11)
        | (($month & 0x0f) << 16)
        | ((($year - 1900) & 0x01ff) << 20)
        | (($weekday & 0x07) << 29);
};
$dopBase = static function () use ($u16, $u32, $dttm): string {
    $flags1 = 0x00000001 | 0x00000004 | (2 << 5) | (1 << 16) | (5 << 18);
    $flags2 = (1 << 6) | (1 << 8) | (1 << 10) | (1 << 12) | (1 << 14)
        | (1 << 15) | (1 << 17) | (1 << 20) | (1 << 21) | (1 << 22)
        | (1 << 25) | (1 << 27) | (1 << 29) | (1 << 31);
    $flags3 = 2 | (7 << 2) | (3 << 16) | (1 << 26) | (1 << 27)
        | (1 << 28) | (1 << 29) | (1 << 31);
    $viewFlags = 5 | (125 << 3) | (2 << 12) | (1 << 15);

    return $u32($flags1)
        . $u32($flags2)
        . $u16(0x0003)
        . $u16(720)
        . $u16(65001)
        . $u16(360)
        . $u16(3)
        . $u16(0)
        . $u32($dttm(2024, 4, 6, 7, 8, 6))
        . $u32($dttm(2024, 4, 8, 9, 10, 1))
        . $u32($dttm(2024, 4, 9, 11, 12, 2))
        . $u16(4)
        . $u32(125)
        . $u32(2345)
        . $u32(12345)
        . $u16(12)
        . $u32(67)
        . $u32($flags3)
        . $u32(890)
        . $u32(2400)
        . $u32(14000)
        . $u16(13)
        . $u32(72)
        . $u32(901)
        . $u32(0x0a0b0c0d)
        . $u16($viewFlags);
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
$secondPieceText = "\rReview\v"
    . 'note ' . "\x02" . ' # '
    . 'comment ' . "\x05" . ' '
    . $fieldBegin . ' HYPERLINK "https://example.test/legacy-doc?source=42" \o "Source packet" '
    . $fieldSeparator . 'source dossier' . $fieldEnd
    . ' '
    . $fieldBegin . ' HYPERLINK \l "legacy_anchor" '
    . $fieldSeparator . 'opening bookmark' . $fieldEnd
    . ' '
    . $fieldBegin . ' REF "legacy_anchor" \h '
    . $fieldSeparator . 'Legacy DOC import' . $fieldEnd
    . ' '
    . $fieldBegin . ' PAGEREF legacy_anchor \p '
    . $fieldSeparator . '7' . $fieldEnd
    . ' '
    . $fieldBegin . ' PAGE \* Arabic ' . $fieldSeparator . '7' . $fieldEnd
    . ' '
    . $fieldBegin . ' ASK Owner "Owner?" \d "M" \o ' . $fieldSeparator . 'Mia' . $fieldEnd
    . ' '
    . $fieldBegin . ' FILLIN "Note?" \d "QA" ' . $fieldSeparator . 'Ready' . $fieldEnd
    . ' '
    . $fieldBegin . ' FORMTEXT \* MERGEFORMAT ' . $fieldSeparator . 'pending review' . $fieldEnd
    . ' '
    . $fieldBegin . ' MERGEFIELD Name ' . $fieldSeparator . 'Ada' . $fieldEnd
    . ' '
    . $fieldBegin . ' DATA "C:\Data\legacy-mailmerge.csv" "C:\Data\legacy-header.doc" \m \* MERGEFORMAT '
    . $fieldSeparator . 'merge data source' . $fieldEnd
    . ' '
    . $fieldBegin . ' DOCVARIABLE Batch ' . $fieldSeparator . '42' . $fieldEnd
    . ' '
    . $fieldBegin . ' SYMBOL 183 \f "Symbol" \s 12 \u ' . $fieldSeparator . '·' . $fieldEnd
    . ' '
    . $fieldBegin . ' INCLUDEPICTURE "chart.png" \d \* MERGEFORMAT '
    . $fieldSeparator . 'chart' . $fieldEnd
    . ' '
    . $fieldBegin . ' INCLUDETEXT "https://e.test/c.doc" \c "H1" \! '
    . $fieldSeparator . 'clause' . $fieldEnd
    . ' '
    . $fieldBegin . ' MACROBUTTON ApproveImport "Approve packet" '
    . $fieldSeparator . 'Approve packet' . $fieldEnd
    . ' '
    . $fieldBegin . ' GOTOBUTTON legacy_anchor "Jump to source" '
    . $fieldSeparator . 'Jump to source' . $fieldEnd
    . ' '
    . $fieldBegin . ' AUTONUMLGL ' . $fieldSeparator . '2.1' . $fieldEnd
    . ' '
    . $fieldBegin . ' HYPERLINK "https://example.test/audit#page" \o "Nested page link" '
    . $fieldSeparator . 'nested p. '
    . $fieldBegin . ' PAGE \* Arabic ' . $fieldSeparator . '9' . $fieldEnd
    . $fieldEnd
    . ' '
    . $fieldBegin . ' FILENAME \p \* MERGEFORMAT ' . $fieldSeparator . 'C:\Sites\wp\legacy packet.doc' . $fieldEnd
    . ' '
    . $fieldBegin . ' TEMPLATE ' . $fieldSeparator . 'Migration.dotm' . $fieldEnd
    . ' '
    . $fieldBegin . ' FILESIZE \# "#,##0 KB" ' . $fieldSeparator . '12 KB' . $fieldEnd
    . ' '
    . $fieldBegin . ' IMPORT "chart-alias.png" \d ' . $fieldSeparator . 'alias chart' . $fieldEnd
    . ' '
    . $fieldBegin . ' INCLUDE "https://e.test/alias.doc" \c "H2" \! ' . $fieldSeparator . 'alias clause' . $fieldEnd
    . ' '
    . $fieldBegin . ' QUOTE "Hidden instruction literal" \* Upper ' . $fieldSeparator . 'DISPLAYED LITERAL' . $fieldEnd
    . ' '
    . $fieldBegin . ' SHAPE "Hidden shape instruction" \* MERGEFORMAT ' . $fieldSeparator . 'shape placeholder' . $fieldEnd
    . "\x01"
    . ' pic ' . "\x01"
    . ".\r";
$firstPieceBytes = $utf16le($firstPieceText);
$secondPieceBytes = $utf16le($secondPieceText);
$subdocumentSeparatorBytes = $utf16le("\r");
$footnoteSubdocumentText = "Footnote body retained for metadata-only review.\r";
$headerSubdocumentText = 'H ' . $fieldBegin . ' DATE ' . $fieldSeparator . '2026-06-06' . $fieldEnd . "\r\r";
$commentSubdocumentText = "Comment body retained for annotation review.\r";
$endnoteSubdocumentText = 'Endnote ' . $fieldBegin . ' NOTEREF "_RefNote" \f ' . $fieldSeparator . '1' . $fieldEnd . "\r";
$textboxSubdocumentText = 'Textbox ' . $fieldBegin . ' PAGE \* Arabic ' . $fieldSeparator . '3' . $fieldEnd . " metadata\r";
$headerTextboxSubdocumentText = 'Header textbox ' . $fieldBegin . ' REF "legacy_anchor" \h ' . $fieldSeparator . 'Anchor' . $fieldEnd . "\r";
$footnoteSubdocumentBytes = $utf16le($footnoteSubdocumentText);
$headerSubdocumentBytes = $utf16le($headerSubdocumentText);
$commentSubdocumentBytes = $utf16le($commentSubdocumentText);
$endnoteSubdocumentBytes = $utf16le($endnoteSubdocumentText);
$textboxSubdocumentBytes = $utf16le($textboxSubdocumentText);
$headerTextboxSubdocumentBytes = $utf16le($headerTextboxSubdocumentText);
$firstPieceStart = 1024;
$secondPieceStart = $firstPieceStart + strlen($firstPieceBytes);
$mainTextByteEnd = $secondPieceStart + strlen($secondPieceBytes);
$subdocumentSeparatorStart = $mainTextByteEnd;
$footnoteSubdocumentStart = $subdocumentSeparatorStart + strlen($subdocumentSeparatorBytes);
$headerSubdocumentStart = $footnoteSubdocumentStart + strlen($footnoteSubdocumentBytes);
$commentSubdocumentStart = $headerSubdocumentStart + strlen($headerSubdocumentBytes);
$endnoteSubdocumentStart = $commentSubdocumentStart + strlen($commentSubdocumentBytes);
$textboxSubdocumentStart = $endnoteSubdocumentStart + strlen($endnoteSubdocumentBytes);
$headerTextboxSubdocumentStart = $textboxSubdocumentStart + strlen($textboxSubdocumentBytes);
$subdocumentByteEnd = $headerTextboxSubdocumentStart + strlen($headerTextboxSubdocumentBytes);

$wordDocument = str_repeat("\0", $firstPieceStart)
    . $firstPieceBytes
    . $secondPieceBytes
    . $subdocumentSeparatorBytes
    . $footnoteSubdocumentBytes
    . $headerSubdocumentBytes
    . $commentSubdocumentBytes
    . $endnoteSubdocumentBytes
    . $textboxSubdocumentBytes
    . $headerTextboxSubdocumentBytes;
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
$revisionMarkGrpprl = $u16(0x0801) . "\x01"
    . $u16(0x4804) . $u16(1)
    . $u16(0x6805) . $u32($dttm(2024, 4, 7, 8, 9, 0));
$paragraphRevisionGrpprl = $u16(0xc66f) . chr(7) . chr(1)
    . $u16(1)
    . $u32($dttm(2024, 4, 6, 7, 8, 0));
$paragraphPapxPayload = $u16(0) . $paragraphRevisionGrpprl;
$papxRevisionOffset = $papxFkpPage * 512;
$papxRevisionRecordOffset = 480;
$papxBxOffset = (2 + 1) * 4;
$wordDocument = substr_replace(
    $wordDocument,
    $u32($firstPieceStart)
        . $u32($mainTextByteEnd)
        . $u32($mainTextByteEnd + 2),
    $papxRevisionOffset,
    12
);
$wordDocument = substr_replace(
    $wordDocument,
    chr(intdiv($papxRevisionRecordOffset, 2)),
    $papxRevisionOffset + $papxBxOffset,
    1
);
$wordDocument = substr_replace(
    $wordDocument,
    "\0" . chr(intdiv(strlen($paragraphPapxPayload), 2)) . $paragraphPapxPayload,
    $papxRevisionOffset + $papxRevisionRecordOffset,
    2 + strlen($paragraphPapxPayload)
);
$chpxRevisionOffset = $chpxFkpPage * 512;
$chpxRevisionRecordOffset = 80;
$wordDocument = substr_replace(
    $wordDocument,
    $u32($firstPieceStart)
        . $u32($secondPieceStart)
        . $u32($mainTextByteEnd)
        . $u32($mainTextByteEnd + 2),
    $chpxRevisionOffset,
    16
);
$wordDocument = substr_replace(
    $wordDocument,
    chr(intdiv($chpxRevisionRecordOffset, 2)) . "\0\0",
    $chpxRevisionOffset + 16,
    3
);
$wordDocument = substr_replace(
    $wordDocument,
    chr(strlen($revisionMarkGrpprl)) . $revisionMarkGrpprl,
    $chpxRevisionOffset + $chpxRevisionRecordOffset,
    1 + strlen($revisionMarkGrpprl)
);
$wordDocument = substr_replace($wordDocument, $u16(0xa5ec), 0, 2);
$wordDocument = substr_replace($wordDocument, $u16(0x00c1), 2, 2);
$wordDocument = substr_replace($wordDocument, $u16(0x0409), 6, 2);
$wordDocument = substr_replace($wordDocument, $u16(0x3e3d), 10, 2);
$wordDocument = substr_replace($wordDocument, $u16(0x00bf), 12, 2);
$wordDocument = substr_replace($wordDocument, $u32(0), 24, 4);
$wordDocument = substr_replace($wordDocument, $u32($subdocumentByteEnd), 28, 4);

$firstPieceCharacters = intdiv(strlen($firstPieceBytes), 2);
$secondPieceCharacters = intdiv(strlen($secondPieceBytes), 2);
$footnoteSubdocumentCharacters = intdiv(strlen($footnoteSubdocumentBytes), 2);
$headerSubdocumentCharacters = intdiv(strlen($headerSubdocumentBytes), 2);
$commentSubdocumentCharacters = intdiv(strlen($commentSubdocumentBytes), 2);
$endnoteSubdocumentCharacters = intdiv(strlen($endnoteSubdocumentBytes), 2);
$textboxSubdocumentCharacters = intdiv(strlen($textboxSubdocumentBytes), 2);
$headerTextboxSubdocumentCharacters = intdiv(strlen($headerTextboxSubdocumentBytes), 2);
$totalPieceCharacters = $firstPieceCharacters + $secondPieceCharacters;
$footnoteSubdocumentCpStart = $totalPieceCharacters + 1;
$headerSubdocumentCpStart = $footnoteSubdocumentCpStart + $footnoteSubdocumentCharacters;
$commentSubdocumentCpStart = $headerSubdocumentCpStart + $headerSubdocumentCharacters;
$endnoteSubdocumentCpStart = $commentSubdocumentCpStart + $commentSubdocumentCharacters;
$textboxSubdocumentCpStart = $endnoteSubdocumentCpStart + $endnoteSubdocumentCharacters;
$headerTextboxSubdocumentCpStart = $textboxSubdocumentCpStart + $textboxSubdocumentCharacters;
$pieceTableLastCp = $headerTextboxSubdocumentCpStart + $headerTextboxSubdocumentCharacters;
$mainText = $firstPieceText . $secondPieceText;
$mainTextCharacters = preg_split('//u', $mainText, -1, PREG_SPLIT_NO_EMPTY);
if (!is_array($mainTextCharacters) || count($mainTextCharacters) !== $totalPieceCharacters) {
    throw new RuntimeException('Unable to split legacy DOC main text into CP characters');
}
$fieldTypeCodes = [
    'HYPERLINK' => 0x58,
    'NOTEREF' => 0x05,
    'REF' => 0x03,
    'PAGEREF' => 0x25,
    'PAGE' => 0x21,
    'DATE' => 0x1f,
    'FILENAME' => 0x1d,
    'TEMPLATE' => 0x1e,
    'FILESIZE' => 0x45,
    'ASK' => 0x26,
    'FILLIN' => 0x27,
    'FORMTEXT' => 0x46,
    'MERGEFIELD' => 0x3b,
    'DATA' => 0x28,
    'DOCVARIABLE' => 0x40,
    'SYMBOL' => 0x39,
    'IMPORT' => 0x37,
    'INCLUDE' => 0x24,
    'QUOTE' => 0x23,
    'SHAPE' => 0x5f,
    'INCLUDEPICTURE' => 0x43,
    'INCLUDETEXT' => 0x44,
    'GOTOBUTTON' => 0x32,
    'MACROBUTTON' => 0x33,
    'AUTONUMLGL' => 0x35,
];
$fieldRecordsForText = static function (string $text) use ($fieldBegin, $fieldSeparator, $fieldEnd, $fieldTypeCodes): array {
    $characters = preg_split('//u', $text, -1, PREG_SPLIT_NO_EMPTY);
    if (!is_array($characters)) {
        $characters = str_split($text);
    }

    $records = [];
    $openFields = [];
    for ($cp = 0, $count = count($characters); $cp < $count; $cp++) {
        $character = $characters[$cp];
        if ($character === $fieldBegin) {
            $instruction = '';
            for ($cursor = $cp + 1; $cursor < $count; $cursor++) {
                if ($characters[$cursor] === $fieldSeparator || $characters[$cursor] === $fieldEnd) {
                    break;
                }
                $instruction .= $characters[$cursor];
            }
            $fieldName = strtoupper((string) (preg_split('/\s+/', trim($instruction))[0] ?? ''));
            $records[] = [
                'cp' => $cp,
                'character' => 0x13,
                'typeCode' => $fieldTypeCodes[$fieldName] ?? 0x01,
            ];
            $openFields[] = [
                'fieldName' => $fieldName,
                'hasSeparator' => false,
            ];
            continue;
        }

        if ($character === $fieldSeparator) {
            $openIndex = count($openFields) - 1;
            if ($openIndex >= 0) {
                $openFields[$openIndex]['hasSeparator'] = true;
            }
            $records[] = [
                'cp' => $cp,
                'character' => 0x14,
            ];
            continue;
        }

        if ($character === $fieldEnd) {
            $openField = array_pop($openFields) ?? [
                'fieldName' => '',
                'hasSeparator' => false,
            ];
            $endFlags = (($openField['hasSeparator'] ?? false) === true ? 0x80 : 0);
            if (($openField['fieldName'] ?? '') === 'PAGE') {
                $endFlags |= 0x14;
            }
            if ($openFields !== []) {
                $endFlags |= 0x40;
            }
            $records[] = [
                'cp' => $cp,
                'character' => 0x15,
                'endFlags' => $endFlags,
            ];
        }
    }

    return $records;
};
$fieldRecords = $fieldRecordsForText($mainText);
$headerFieldRecords = $fieldRecordsForText($headerSubdocumentText);
$endnoteFieldRecords = $fieldRecordsForText($endnoteSubdocumentText);
$textboxFieldRecords = $fieldRecordsForText($textboxSubdocumentText);
$headerTextboxFieldRecords = $fieldRecordsForText($headerTextboxSubdocumentText);
$plcfldMom = $buildPlcfldMom($fieldRecords, $totalPieceCharacters);
$plcfldHdr = $buildPlcfldMom($headerFieldRecords, $headerSubdocumentCharacters);
$plcfldEdn = $buildPlcfldMom($endnoteFieldRecords, $endnoteSubdocumentCharacters);
$plcfldTxbx = $buildPlcfldMom($textboxFieldRecords, $textboxSubdocumentCharacters);
$plcfldHdrTxbx = $buildPlcfldMom($headerTextboxFieldRecords, $headerTextboxSubdocumentCharacters);
$plcfHdd = $u32(0)
    . $u32(0)
    . $u32(0)
    . $u32(0)
    . $u32(0)
    . $u32(0)
    . $u32(0)
    . $u32(0)
    . $u32($headerSubdocumentCharacters - 1)
    . $u32($headerSubdocumentCharacters - 1)
    . $u32($headerSubdocumentCharacters - 1)
    . $u32($headerSubdocumentCharacters - 1)
    . $u32($headerSubdocumentCharacters - 1)
    . $u32(0);
$dop = $dopBase();
$wordDocument = substr_replace($wordDocument, $u32(strlen($wordDocument)), 0x0040, 4);
$wordDocument = substr_replace($wordDocument, $u32($totalPieceCharacters), 0x004c, 4);
$wordDocument = substr_replace($wordDocument, $u32($footnoteSubdocumentCharacters), 0x0050, 4);
$wordDocument = substr_replace($wordDocument, $u32($headerSubdocumentCharacters), 0x0054, 4);
$wordDocument = substr_replace($wordDocument, $u32($commentSubdocumentCharacters), 0x005c, 4);
$wordDocument = substr_replace($wordDocument, $u32($endnoteSubdocumentCharacters), 0x0060, 4);
$wordDocument = substr_replace($wordDocument, $u32($textboxSubdocumentCharacters), 0x0064, 4);
$wordDocument = substr_replace($wordDocument, $u32($headerTextboxSubdocumentCharacters), 0x0068, 4);
$plcPcd = $u32(0)
    . $u32($firstPieceCharacters)
    . $u32($firstPieceCharacters + $secondPieceCharacters)
    . $u32($footnoteSubdocumentCpStart)
    . $u32($headerSubdocumentCpStart)
    . $u32($commentSubdocumentCpStart)
    . $u32($endnoteSubdocumentCpStart)
    . $u32($textboxSubdocumentCpStart)
    . $u32($headerTextboxSubdocumentCpStart)
    . $u32($pieceTableLastCp)
    . $u16(0x0001) . $u32($firstPieceStart) . "\0\0"
    . $u16(0) . $u32($secondPieceStart) . "\0\0"
    . $u16(0) . $u32($subdocumentSeparatorStart) . "\0\0"
    . $u16(0) . $u32($footnoteSubdocumentStart) . "\0\0"
    . $u16(0) . $u32($headerSubdocumentStart) . "\0\0"
    . $u16(0) . $u32($commentSubdocumentStart) . "\0\0"
    . $u16(0) . $u32($endnoteSubdocumentStart) . "\0\0"
    . $u16(0) . $u32($textboxSubdocumentStart) . "\0\0"
    . $u16(0) . $u32($headerTextboxSubdocumentStart) . "\0\0";
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
$firstInlinePictureOffset = strpos($secondPieceText, "\x01");
$secondInlinePictureOffset = is_int($firstInlinePictureOffset)
    ? strpos($secondPieceText, "\x01", $firstInlinePictureOffset + 1)
    : false;
if ($secondInlinePictureOffset === false) {
    throw new RuntimeException('Unable to locate legacy DOC inline picture fixture character');
}
$inlinePictureCp = $firstPieceCharacters + $characterLength(substr($secondPieceText, 0, $secondInlinePictureOffset));
$inlinePictureFc = $secondPieceStart + (($inlinePictureCp - $firstPieceCharacters) * 2);
$pictureDataStreamOffset = 8;
$pictureDataStream = 'padding!' . str_repeat('P', 12) . 'inline-picture-bytes-not-exposed';
$pictureGrpprl = $u16(0x0855) . "\x01"
    . $u16(0x6a03) . $u32($pictureDataStreamOffset)
    . $u16(0x0806) . "\x01";
$chpxPictureRecordOffset = 120;
$wordDocument = substr_replace(
    $wordDocument,
    $u32($firstPieceStart)
        . $u32($secondPieceStart)
        . $u32($inlinePictureFc)
        . $u32($inlinePictureFc + 2)
        . $u32($mainTextByteEnd),
    $chpxRevisionOffset,
    20
);
$wordDocument = substr_replace(
    $wordDocument,
    chr(intdiv($chpxRevisionRecordOffset, 2)) . "\0" . chr(intdiv($chpxPictureRecordOffset, 2)) . "\0",
    $chpxRevisionOffset + 20,
    4
);
$wordDocument = substr_replace(
    $wordDocument,
    chr(strlen($pictureGrpprl)) . $pictureGrpprl,
    $chpxRevisionOffset + $chpxPictureRecordOffset,
    1 + strlen($pictureGrpprl)
);
$wordDocument = substr_replace($wordDocument, chr(4), $chpxRevisionOffset + 511, 1);
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
$commentAuthorXst = static function (string $value) use ($u16, $utf16le): string {
    $bytes = $utf16le($value);

    return $u16(intdiv(strlen($bytes), 2)) . $bytes . $u16(0);
};
$commentAuthors = $commentAuthorXst('Migration Lead')
    . $commentAuthorXst('Review Editor')
    . $commentAuthorXst('Archive Owner')
    . $commentAuthorXst('Mira Reviewer');
$plcfSed = $u32(0)
    . $u32($totalPieceCharacters + 1)
    . $u16(0) . $u32($sepxFc) . $u16(0) . $u32(0);
$plcBtePapx = $u32($firstPieceStart)
    . $u32($mainTextByteEnd)
    . $u32($papxFkpPage);
$plcBteChpx = $u32($firstPieceStart)
    . $u32($secondPieceStart)
    . $u32($inlinePictureFc)
    . $u32($inlinePictureFc + 2)
    . $u32($mainTextByteEnd)
    . $u32($chpxFkpPage)
    . $u32($chpxFkpPage)
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
$listOrderedLevelPapx = $u16(0x2461) . "\x01";
$listOrderedLevelChpx = $u16(0x0835) . "\x01";
$listOrderedLevel = $listLevel(3, 0x00, "\0.", [1], 1, $listOrderedLevelPapx, $listOrderedLevelChpx);
$listBulletLevel = $listLevel(1, 0x17, "•");
$plfLst = $u16(2)
    . $lstf(1001, 2001, [0 => 15], 0x01)
    . $lstf(2002, 3002, [0 => 16], 0x01, 2);
$plfLfo = $u32(2)
    . $u32(1001) . $u32(0) . $u32(0) . chr(1) . chr(0xfc) . chr(0) . "\0"
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
$documentVariablesTable = $stwUser([
    ['name' => 'MigrationBatch', 'value' => 'legacy-doc-42'],
    ['name' => 'ReviewStatus', 'value' => 'needs editorial review'],
    ['name' => 'Sign', 'value' => 'opaque signature blob'],
]);
$saveHistoryTable = $sttbSavedBy([
    ['author' => 'Migration Desk', 'path' => 'C:\Legacy\packet-draft.doc'],
    ['author' => 'Review Lead', 'path' => 'D:\Archive\legacy-doc-42-final.doc'],
]);
$externalFileTable = $sttbFnm([
    [
        'path' => 'C:\Legacy\Subdocs\appendix-a.doc',
        'referenceTypeCode' => 5,
        'documentIndex' => 3,
        'ichRelative' => 10,
        'fnfb' => 0x08,
    ],
    [
        'path' => 'https://example.test/merge/source.csv',
        'referenceTypeCode' => 3,
        'documentIndex' => 4,
        'ichRelative' => 0xff,
        'fnfb' => 0x10,
    ],
    [
        'path' => 'https://e.test/c.doc',
        'referenceTypeCode' => 5,
        'documentIndex' => 5,
        'ichRelative' => 0xff,
        'fnfb' => 0x10,
    ],
]);
$mailMergeSettingsTable = $pms([
    'sources' => [[
        'sourceCode' => 0,
        'linkToFilename' => true,
        'linkToConnectionString' => true,
        'noPromptQuery' => true,
        'query' => true,
        'fieldToken' => 0x002c,
        'recordToken' => 0x000d,
        'documentIndex' => 4,
        'referenceTypeCode' => 3,
    ]],
    'sqlQuery' => 'SELECT * FROM LegacyContacts WHERE Segment = "review"',
    'recordFilterStrings' => ['Segment = review'],
]);
$routeSlipTable = $routeSlip([
    [
        'entryId' => "entry-id-001",
        'name' => 'Route Reviewer',
    ],
    [
        'entryId' => "entry-id-002",
        'name' => 'Route Archivist',
    ],
], [
    'routed' => true,
    'returnOriginal' => true,
    'trackStatus' => true,
    'protect' => 2,
    'stage' => 1,
    'deliveryOption' => 1,
    'subject' => 'Legacy DOC packet',
    'message' => 'Please review before import.',
    'status' => 'Awaiting legal signoff',
    'title' => 'Route packet 42',
]);
$revisionAuthorTable = $sttbUnicode(['Unknown', 'Migration Lead', 'Review Editor', 'Archive Owner']);
$captionDefinitionTable = $sttbfCaption([
    [
        'label' => 'Figure',
        'insertLocationCode' => 1,
        'includeChapterNumber' => true,
        'headingLevel' => 2,
        'numberFormatCode' => 1,
        'chapterSeparatorCode' => 0x002e,
    ],
    [
        'label' => 'Table',
        'insertLocationCode' => 0,
        'noLabel' => true,
        'numberFormatCode' => 0xff,
    ],
]);
$autoCaptionTable = $sttbfAutoCaption([
    [
        'progId' => 'Word.Picture.8',
        'captionIndex' => 0,
    ],
    [
        'progId' => 'Excel.Chart.8',
        'captionIndex' => 1,
    ],
]);
$fcDop = strlen($clx);
$fcPlcfFldMom = $fcDop + strlen($dop);
$fcPlcfFldHdr = $fcPlcfFldMom + strlen($plcfldMom);
$fcPlcfFldEdn = $fcPlcfFldHdr + strlen($plcfldHdr);
$fcPlcfFldTxbx = $fcPlcfFldEdn + strlen($plcfldEdn);
$fcPlcfFldHdrTxbx = $fcPlcfFldTxbx + strlen($plcfldTxbx);
$fcPlcfHdd = $fcPlcfFldHdrTxbx + strlen($plcfldHdrTxbx);
$fcSttbfAssoc = $fcPlcfHdd + strlen($plcfHdd);
$fcStwUser = $fcSttbfAssoc + strlen($associatedStringsTable);
$fcSttbSavedBy = $fcStwUser + strlen($documentVariablesTable);
$fcSttbFnm = $fcSttbSavedBy + strlen($saveHistoryTable);
$fcPms = $fcSttbFnm + strlen($externalFileTable);
$fcRouteSlip = $fcPms + strlen($mailMergeSettingsTable);
$fcSttbfRMark = $fcRouteSlip + strlen($routeSlipTable);
$fcSttbfCaption = $fcSttbfRMark + strlen($revisionAuthorTable);
$fcSttbfAutoCaption = $fcSttbfCaption + strlen($captionDefinitionTable);
$fcSttbfBkmk = $fcSttbfAutoCaption + strlen($autoCaptionTable);
$fcPlcfBkf = $fcSttbfBkmk + strlen($sttbfBkmk);
$fcPlcfBkl = $fcPlcfBkf + strlen($plcfBkf);
$fcPlcffndRef = $fcPlcfBkl + strlen($plcfBkl);
$fcPlcffndTxt = $fcPlcffndRef + strlen($plcffndRef);
$fcPlcfendRef = $fcPlcffndTxt + strlen($plcffndTxt);
$fcPlcfendTxt = $fcPlcfendRef + strlen($plcfendRef);
$fcPlcfandRef = $fcPlcfendTxt + strlen($plcfendTxt);
$fcPlcfandTxt = $fcPlcfandRef + strlen($plcfandRef);
$fcGrpXstAtnOwners = $fcPlcfandTxt + strlen($plcfandTxt);
$fcPlcfSed = $fcGrpXstAtnOwners + strlen($commentAuthors);
$fcPlcBtePapx = $fcPlcfSed + strlen($plcfSed);
$fcPlcBteChpx = $fcPlcBtePapx + strlen($plcBtePapx);
$fcStshf = $fcPlcBteChpx + strlen($plcBteChpx);
$fcPlfLst = $fcStshf + strlen($stsh);
$fcPlfLfo = $fcPlfLst + strlen($plfLst) + strlen($listOrderedLevel) + strlen($listBulletLevel);
$tableStream = $clx . $dop . $plcfldMom . $plcfldHdr . $plcfldEdn . $plcfldTxbx . $plcfldHdrTxbx . $plcfHdd . $associatedStringsTable . $documentVariablesTable . $saveHistoryTable . $externalFileTable . $mailMergeSettingsTable . $routeSlipTable . $revisionAuthorTable . $captionDefinitionTable . $autoCaptionTable . $sttbfBkmk . $plcfBkf . $plcfBkl . $plcffndRef . $plcffndTxt . $plcfendRef . $plcfendTxt . $plcfandRef . $plcfandTxt . $commentAuthors . $plcfSed . $plcBtePapx . $plcBteChpx . $stsh . $plfLst . $listOrderedLevel . $listBulletLevel . $plfLfo;
$wordDocument = substr_replace($wordDocument, $u32($fcStshf), 0x00a2, 4);
$wordDocument = substr_replace($wordDocument, $u32(strlen($stsh)), 0x00a6, 4);
$wordDocument = substr_replace($wordDocument, $u32($fcPlcfHdd), 0x00f2, 4);
$wordDocument = substr_replace($wordDocument, $u32(strlen($plcfHdd)), 0x00f6, 4);
$wordDocument = substr_replace($wordDocument, $u32($fcPlcBteChpx), 0x00fa, 4);
$wordDocument = substr_replace($wordDocument, $u32(strlen($plcBteChpx)), 0x00fe, 4);
$wordDocument = substr_replace($wordDocument, $u32($fcPlcBtePapx), 0x0102, 4);
$wordDocument = substr_replace($wordDocument, $u32(strlen($plcBtePapx)), 0x0106, 4);
$wordDocument = substr_replace($wordDocument, $u32($fcPlcfFldMom), 0x011a, 4);
$wordDocument = substr_replace($wordDocument, $u32(strlen($plcfldMom)), 0x011e, 4);
$wordDocument = substr_replace($wordDocument, $u32($fcPlcfFldHdr), 0x0122, 4);
$wordDocument = substr_replace($wordDocument, $u32(strlen($plcfldHdr)), 0x0126, 4);
$wordDocument = substr_replace($wordDocument, $u32($fcPlcfFldEdn), 0x021a, 4);
$wordDocument = substr_replace($wordDocument, $u32(strlen($plcfldEdn)), 0x021e, 4);
$wordDocument = substr_replace($wordDocument, $u32($fcPlcfFldTxbx), 0x0262, 4);
$wordDocument = substr_replace($wordDocument, $u32(strlen($plcfldTxbx)), 0x0266, 4);
$wordDocument = substr_replace($wordDocument, $u32($fcPlcfFldHdrTxbx), 0x0272, 4);
$wordDocument = substr_replace($wordDocument, $u32(strlen($plcfldHdrTxbx)), 0x0276, 4);
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
$wordDocument = substr_replace($wordDocument, $u32($fcStwUser), 0x027a, 4);
$wordDocument = substr_replace($wordDocument, $u32(strlen($documentVariablesTable)), 0x027e, 4);
$wordDocument = substr_replace($wordDocument, $u32($fcSttbSavedBy), 0x02d2, 4);
$wordDocument = substr_replace($wordDocument, $u32(strlen($saveHistoryTable)), 0x02d6, 4);
$wordDocument = substr_replace($wordDocument, $u32($fcSttbFnm), 0x02da, 4);
$wordDocument = substr_replace($wordDocument, $u32(strlen($externalFileTable)), 0x02de, 4);
$wordDocument = substr_replace($wordDocument, $u32($fcPms), 0x01fa, 4);
$wordDocument = substr_replace($wordDocument, $u32(strlen($mailMergeSettingsTable)), 0x01fe, 4);
$wordDocument = substr_replace($wordDocument, $u32($fcRouteSlip), 0x02ca, 4);
$wordDocument = substr_replace($wordDocument, $u32(strlen($routeSlipTable)), 0x02ce, 4);
$wordDocument = substr_replace($wordDocument, $u32($fcSttbfRMark), 0x0232, 4);
$wordDocument = substr_replace($wordDocument, $u32(strlen($revisionAuthorTable)), 0x0236, 4);
$wordDocument = substr_replace($wordDocument, $u32($fcSttbfCaption), 0x023a, 4);
$wordDocument = substr_replace($wordDocument, $u32(strlen($captionDefinitionTable)), 0x023e, 4);
$wordDocument = substr_replace($wordDocument, $u32($fcSttbfAutoCaption), 0x0242, 4);
$wordDocument = substr_replace($wordDocument, $u32(strlen($autoCaptionTable)), 0x0246, 4);
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
$wordDocument = substr_replace($wordDocument, $u32($fcDop), 0x0192, 4);
$wordDocument = substr_replace($wordDocument, $u32(strlen($dop)), 0x0196, 4);
$wordDocument = substr_replace($wordDocument, $u32($fcGrpXstAtnOwners), 0x01ba, 4);
$wordDocument = substr_replace($wordDocument, $u32(strlen($commentAuthors)), 0x01be, 4);
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
$unicodeReviewStreamPath = 'Résumé/Σύνοψη';
$unicodeReviewStreamBytes = 'unicode CFB review packet';

$streams = [
    'WordDocument' => $wordDocument,
    '1Table' => $tableStream,
    'Data' => $pictureDataStream,
    $unicodeReviewStreamPath => $unicodeReviewStreamBytes,
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
                0 => $typedUnicodeDictionary([
                    2 => 'MigrationBatch',
                    3 => 'Needs Review',
                    4 => 'Source Id',
                    5 => 'Archive Bytes',
                    6 => 'Source Guid',
                    7 => 'Review Weight',
                    8 => 'Confidence Score',
                    9 => 'Invoice Total',
                    10 => 'Review Date',
                    11 => '_PID_LINKBASE',
                    12 => '_PID_HLINKS',
                    13 => 'QA Ω',
                ]),
                1 => $typedI2(1200),
                2 => $typedLpwstr('legacy-doc-42'),
                3 => $typedBool(true),
                4 => $typedI4(4242),
                5 => $typedUi8Parts(1705032704, 1),
                6 => $typedClsid($sourceGuid),
                7 => $typedR4(1.25),
                8 => $typedR8(0.875),
                9 => $typedCurrency(12345678),
                10 => $typedOleDate(45309.5),
                11 => $typedUnicodeBlob('https://example.test/legacy/'),
                12 => $typedHyperlinks([
                    [
                        'hash' => 0x12345678,
                        'app' => 42,
                        'shapeId' => 1001,
                        'info' => 0x00000000,
                        'target' => 'appendix-a.html',
                        'location' => 'ReviewAnchor',
                    ],
                    [
                        'hash' => 0x01020304,
                        'app' => 77,
                        'shapeId' => 2002,
                        'info' => 0x00010000,
                        'target' => 'https://example.test/source.doc',
                        'location' => '',
                    ],
                ]),
                13 => $typedLpwstr('unicode-review-α'),
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
$regularStreams = [];
foreach ($streams as $name => $data) {
    if (strlen($data) >= 4096) {
        $regularStreams[(string) $name] = $data;
        continue;
    }

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
foreach ($regularStreams as $name => $data) {
    $startSector = count($sectors);
    $chunks = str_split($padTo($data, $sectorSize), $sectorSize);
    foreach ($chunks as $index => $chunk) {
        $sector = count($sectors);
        $sectors[] = $chunk;
        $fat[$sector] = $index === count($chunks) - 1 ? $end : $sector + 1;
    }
    $locations[$name] = [
        'startSector' => $startSector,
        'size' => strlen($data),
    ];
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
$directoryChunks = str_split($padDirectoryEntries($directory, $sectorSize), $sectorSize);
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
$miniFatEntriesPerSector = intdiv($sectorSize, 4);
$miniFatEntryCount = max($miniFatEntriesPerSector, intdiv(count($miniFat) + $miniFatEntriesPerSector - 1, $miniFatEntriesPerSector) * $miniFatEntriesPerSector);
for ($index = 0; $index < $miniFatEntryCount; $index++) {
    $miniFatBytes .= $u32($miniFat[$index] ?? $free);
}
$miniFatChunks = str_split($padTo($miniFatBytes, $sectorSize), $sectorSize);
$previousMiniFatSector = 2;
foreach ($miniFatChunks as $index => $chunk) {
    if ($index === 0) {
        $sectors[2] = $chunk;
        $fat[2] = count($miniFatChunks) === 1 ? $end : count($sectors);
        continue;
    }

    $sector = count($sectors);
    $sectors[] = $chunk;
    $fat[$previousMiniFatSector] = $sector;
    $fat[$sector] = $index === count($miniFatChunks) - 1 ? $end : $sector + 1;
    $previousMiniFatSector = $sector;
}

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
    . $u32(count($miniFatChunks))
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
$reader = new LegacyDocReader();
$result = $reader->readBytes($docBytes);
$blocks = (new WordPressBlockWriter())->write($result['document']);
$formFieldDataSamples = [
    'reviewerName' => $reader->decodeFormFieldData($ffData([
        'fieldType' => 'text',
        'name' => 'ReviewerName',
        'defaultText' => 'pending review',
        'textFormat' => 'Title Case',
        'helpText' => 'Enter the reviewer name before import.',
        'statusText' => 'Reviewer name stored in legacy form metadata.',
        'maxLength' => 40,
        'hasOwnHelpText' => true,
        'hasOwnStatusText' => true,
        'protected' => true,
    ])),
    'approval' => $reader->decodeFormFieldData($ffData([
        'fieldType' => 'checkbox',
        'name' => 'ApproveImport',
        'defaultStateCode' => 0,
        'currentStateCode' => 1,
        'checkboxSizeHalfPoints' => 24,
    ])),
    'publicationState' => $reader->decodeFormFieldData($ffData([
        'fieldType' => 'dropdown',
        'name' => 'PublicationState',
        'defaultStateCode' => 1,
        'currentStateCode' => 2,
        'dropDownItems' => ['Draft', 'Review', 'Publish'],
        'helpText' => 'Choose the publication state for the migrated post.',
    ])),
];

$summary = [
    'metadata' => $result['metadata'],
    'streams' => $result['streams'],
    'streamDirectory' => $result['streamDirectory'],
    'directoryEntries' => $result['directoryEntries'],
    'textSource' => $result['document']->attr('textSource'),
    'fib' => $result['fib'],
    'subdocuments' => $result['subdocuments'],
    'headerFooterStories' => $result['headerFooterStories'],
    'styles' => $result['styles'],
    'formattingRuns' => $result['formattingRuns'],
    'listFormats' => $result['listFormats'],
    'listOverrides' => $result['listOverrides'],
    'sections' => $result['sections'],
    'bookmarks' => $result['bookmarks'],
    'footnotes' => $result['footnotes'],
    'endnotes' => $result['endnotes'],
    'comments' => $result['comments'],
    'commentAuthors' => $result['commentAuthors'],
    'revisionAuthors' => $result['revisionAuthors'],
    'captionDefinitions' => $result['captionDefinitions'],
    'autoCaptionRules' => $result['autoCaptionRules'],
    'fieldCharacters' => $result['fieldCharacters'],
    'fields' => $result['fields'],
    'fieldStories' => $result['fieldStories'],
    'formFieldDataSamples' => $formFieldDataSamples,
    'embeddedObjects' => $result['embeddedObjects'],
    'embeddedObjectReferences' => $result['embeddedObjectReferences'],
    'pictureReferences' => $result['pictureReferences'],
    'macroProjects' => $result['macroProjects'],
    'associatedStrings' => $result['associatedStrings'],
    'documentProperties' => $result['documentProperties'],
    'documentVariables' => $result['documentVariables'],
    'saveHistory' => $result['saveHistory'],
    'externalFileReferences' => $result['externalFileReferences'],
    'subdocumentReferences' => $result['subdocumentReferences'],
    'mailMergeSettings' => $result['mailMergeSettings'],
    'routeSlip' => $result['routeSlip'],
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
    $summaryInfoLocation = $locations["\x05SummaryInformation"] ?? null;
    if (!is_array($summaryInfoLocation)) {
        throw new RuntimeException('Legacy DOC handoff self-test missing SummaryInformation stream location');
    }
    $summaryInfoOffset = 512 + ($rootMiniStart * $sectorSize) + ((int) $summaryInfoLocation['startSector'] * $miniSectorSize);
    $duplicatePropertyGuardDoc = substr_replace($docBytes, $u32(2), $summaryInfoOffset + 48 + 8 + (2 * 8), 4);
    try {
        (new LegacyDocReader())->readBytes($duplicatePropertyGuardDoc);

        throw new RuntimeException('Legacy DOC handoff self-test accepted duplicate OLE property-set identifiers');
    } catch (RuntimeException $exception) {
        if (!str_contains($exception->getMessage(), 'duplicate property identifiers')) {
            throw $exception;
        }
    }
    $dirtyTypedPaddingDoc = substr_replace(
        $docBytes,
        $u16(1),
        $summaryInfoOffset + $propertySetValueOffset($streams["\x05SummaryInformation"], 2) + 2,
        2
    );
    try {
        (new LegacyDocReader())->readBytes($dirtyTypedPaddingDoc);

        throw new RuntimeException('Legacy DOC handoff self-test accepted nonzero OLE typed-value padding');
    } catch (RuntimeException $exception) {
        if (!str_contains($exception->getMessage(), 'nonzero typed-value padding')) {
            throw $exception;
        }
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
    foreach ([
        'data-legacy-doc-mail-merge-field-type="data-source-redirect"',
        'data-legacy-doc-mail-merge-data-source-basename="legacy-mailmerge.csv"',
        'data-legacy-doc-mail-merge-header-document-basename="legacy-header.doc"',
        'data-legacy-doc-mail-merge-associated-data-source-index="8"',
        'data-legacy-doc-mail-merge-header-document-index="9"',
        'data-legacy-doc-mail-merge-switch-m="true"',
    ] as $expectedAttribute) {
        if (!str_contains($summary['wordpressBlocks'], $expectedAttribute)) {
            throw new RuntimeException('Legacy DOC handoff self-test missing DATA mail-merge attribute: ' . $expectedAttribute);
        }
    }
    $visibleBlocks = strip_tags($summary['wordpressBlocks']);
    if (!str_contains($visibleBlocks, 'merge data source') || str_contains($visibleBlocks, 'DATA') || str_contains($visibleBlocks, 'legacy-mailmerge.csv') || str_contains($visibleBlocks, 'legacy-header.doc')) {
        throw new RuntimeException('Legacy DOC handoff self-test exposed DATA mail-merge source text');
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
    if (($summary['metadata']['documentVariableCount'] ?? null) !== 3 || count($summary['documentVariables'] ?? []) !== 3) {
        throw new RuntimeException('Legacy DOC handoff self-test missing StwUser document-variable inventory');
    }
    if (($summary['metadata']['documentVariableValues'] ?? []) !== [
        'MigrationBatch' => 'legacy-doc-42',
        'ReviewStatus' => 'needs editorial review',
    ]) {
        throw new RuntimeException('Legacy DOC handoff self-test missing StwUser document-variable values');
    }
    if (($summary['metadata']['documentSignatureVariableCount'] ?? null) !== 1 || ($summary['metadata']['documentSignaturePolicy'] ?? '') !== 'signature-blob-metadata-only') {
        throw new RuntimeException('Legacy DOC handoff self-test missing StwUser signature-variable policy');
    }
    $signatureVariables = array_values(array_filter(
        $summary['documentVariables'],
        static fn (array $variable): bool => ($variable['signatureVariable'] ?? false) === true
    ));
    if (count($signatureVariables) !== 1 || ($signatureVariables[0]['name'] ?? '') !== 'Sign' || ($signatureVariables[0]['redacted'] ?? null) !== true || isset($signatureVariables[0]['value'])) {
        throw new RuntimeException('Legacy DOC handoff self-test exposed StwUser signature variable bytes');
    }
    if (
        str_contains($summary['wordpressBlocks'], 'legacy-doc-42')
        || str_contains($summary['wordpressBlocks'], 'unicode-review-α')
        || str_contains($summary['wordpressBlocks'], 'QA Ω')
        || str_contains($summary['wordpressBlocks'], 'needs editorial review')
        || str_contains($summary['wordpressBlocks'], 'opaque signature blob')
    ) {
        throw new RuntimeException('Legacy DOC handoff self-test rendered StwUser metadata into blocks');
    }
    if (($summary['metadata']['saveHistoryCount'] ?? null) !== 2 || count($summary['saveHistory'] ?? []) !== 2) {
        throw new RuntimeException('Legacy DOC handoff self-test missing SttbSavedBy save-history inventory');
    }
    if (($summary['metadata']['latestSavedBy'] ?? '') !== 'Review Lead' || ($summary['metadata']['latestSavedName'] ?? '') !== 'legacy-doc-42-final.doc') {
        throw new RuntimeException('Legacy DOC handoff self-test missing latest save-history metadata');
    }
    if (($summary['saveHistory'][0]['path'] ?? '') !== 'C:\Legacy\packet-draft.doc' || ($summary['saveHistory'][1]['basename'] ?? '') !== 'legacy-doc-42-final.doc') {
        throw new RuntimeException('Legacy DOC handoff self-test missing ordered save-history paths');
    }
    if (str_contains($summary['wordpressBlocks'], 'packet-draft.doc') || str_contains($summary['wordpressBlocks'], 'Review Lead')) {
        throw new RuntimeException('Legacy DOC handoff self-test rendered SttbSavedBy metadata into blocks');
    }
    if (($summary['metadata']['externalFileReferenceCount'] ?? null) !== 3 || count($summary['externalFileReferences'] ?? []) !== 3) {
        throw new RuntimeException('Legacy DOC handoff self-test missing SttbFnm external-file inventory');
    }
    if (($summary['metadata']['externalFileReferencePolicy'] ?? '') !== 'metadata-only-native-review') {
        throw new RuntimeException('Legacy DOC handoff self-test missing SttbFnm metadata-only policy');
    }
    if (($summary['externalFileReferences'][0]['path'] ?? '') !== 'C:\Legacy\Subdocs\appendix-a.doc' || ($summary['externalFileReferences'][0]['relativePath'] ?? '') !== 'Subdocs\appendix-a.doc') {
        throw new RuntimeException('Legacy DOC handoff self-test missing SttbFnm subdocument path metadata');
    }
    if (($summary['externalFileReferences'][0]['referenceType'] ?? '') !== 'subdocument' || ($summary['externalFileReferences'][0]['fileSystem'] ?? '') !== 'ntfs') {
        throw new RuntimeException('Legacy DOC handoff self-test missing SttbFnm subdocument FNIF metadata');
    }
    if (($summary['externalFileReferences'][1]['path'] ?? '') !== 'https://example.test/merge/source.csv' || ($summary['externalFileReferences'][1]['referenceType'] ?? '') !== 'mail-merge-data-source' || ($summary['externalFileReferences'][1]['fileSystem'] ?? '') !== 'non-file-system') {
        throw new RuntimeException('Legacy DOC handoff self-test missing SttbFnm external source metadata');
    }
    if (($summary['externalFileReferences'][2]['path'] ?? '') !== 'https://e.test/c.doc' || ($summary['externalFileReferences'][2]['referenceType'] ?? '') !== 'subdocument' || ($summary['externalFileReferences'][2]['fileSystem'] ?? '') !== 'non-file-system') {
        throw new RuntimeException('Legacy DOC handoff self-test missing SttbFnm include-field source metadata');
    }
    if (($summary['metadata']['subdocumentReferenceCount'] ?? null) !== 2 || count($summary['subdocumentReferences'] ?? []) !== 2) {
        throw new RuntimeException('Legacy DOC handoff self-test missing SttbFnm subdocument reference inventory');
    }
    if (($summary['metadata']['subdocumentReferencePolicy'] ?? '') !== 'metadata-only-native-review') {
        throw new RuntimeException('Legacy DOC handoff self-test missing SttbFnm subdocument reference metadata-only policy');
    }
    if (($summary['subdocumentReferences'][0]['path'] ?? '') !== 'C:\Legacy\Subdocs\appendix-a.doc' || ($summary['subdocumentReferences'][0]['relationshipRole'] ?? '') !== 'master-subdocument-link' || ($summary['subdocumentReferences'][0]['externalFileReferenceIndex'] ?? null) !== 0) {
        throw new RuntimeException('Legacy DOC handoff self-test missing master subdocument reference metadata');
    }
    if (($summary['subdocumentReferences'][1]['path'] ?? '') !== 'https://e.test/c.doc' || ($summary['subdocumentReferences'][1]['pathKind'] ?? '') !== 'external-url' || ($summary['subdocumentReferences'][1]['externalFileReferenceIndex'] ?? null) !== 2) {
        throw new RuntimeException('Legacy DOC handoff self-test missing URL subdocument reference metadata');
    }
    if (($summary['metadata']['mailMergeSettingsPolicy'] ?? '') !== 'metadata-only-native-review' || ($summary['metadata']['mailMergeSourceRecordCount'] ?? null) !== 1) {
        throw new RuntimeException('Legacy DOC handoff self-test missing Pms mail-merge settings metadata');
    }
    if (($summary['mailMergeSettings']['documentType'] ?? '') !== 'letters' || ($summary['mailMergeSettings']['destination'] ?? '') !== 'email') {
        throw new RuntimeException('Legacy DOC handoff self-test missing Pms mail-merge state metadata');
    }
    if (($summary['mailMergeSettings']['sourceRecords'][0]['externalFileReferenceIndex'] ?? null) !== 1 || ($summary['mailMergeSettings']['sourceRecords'][0]['path'] ?? '') !== 'https://example.test/merge/source.csv') {
        throw new RuntimeException('Legacy DOC handoff self-test missing Pms/SttbFnm source linkage');
    }
    if (($summary['mailMergeSettings']['sourceRecords'][0]['fieldSeparatorToken'] ?? '') !== 'comma' || ($summary['mailMergeSettings']['sourceRecords'][0]['recordSeparatorToken'] ?? '') !== 'carriage-return') {
        throw new RuntimeException('Legacy DOC handoff self-test missing Pms separator metadata');
    }
    if (($summary['mailMergeSettings']['sqlQuery'] ?? '') !== 'SELECT * FROM LegacyContacts WHERE Segment = "review"' || ($summary['mailMergeSettings']['recordFilterStrings'][0] ?? '') !== 'Segment = review') {
        throw new RuntimeException('Legacy DOC handoff self-test missing Pms SQL/filter metadata');
    }
    if (str_contains($visibleBlocks, 'LegacyContacts') || str_contains($visibleBlocks, 'Segment = review')) {
        throw new RuntimeException('Legacy DOC handoff self-test rendered Pms metadata into blocks');
    }
    foreach ([
        'data-legacy-doc-include-external-reference-index="2"',
        'data-legacy-doc-include-external-reference-match="path"',
        'data-legacy-doc-include-external-reference-type="subdocument"',
        'data-legacy-doc-include-external-reference-document-index="5"',
        'data-legacy-doc-include-external-reference-file-system="non-file-system"',
        'data-legacy-doc-include-external-reference-policy="metadata-only-native-review"',
        'data-legacy-doc-include-external-reference-can-expose-bytes="false"',
    ] as $expectedAttribute) {
        if (!str_contains($summary['wordpressBlocks'], $expectedAttribute)) {
            throw new RuntimeException('Legacy DOC handoff self-test missing include/SttbFnm relationship attribute: ' . $expectedAttribute);
        }
    }
    if (str_contains($summary['wordpressBlocks'], 'appendix-a.doc') || str_contains($summary['wordpressBlocks'], 'source.csv')) {
        throw new RuntimeException('Legacy DOC handoff self-test rendered SttbFnm metadata into blocks');
    }
    if (($summary['metadata']['routeSlipRecipientCount'] ?? null) !== 2 || count($summary['routeSlip']['recipients'] ?? []) !== 2) {
        throw new RuntimeException('Legacy DOC handoff self-test missing RouteSlip recipient inventory');
    }
    if (($summary['metadata']['routeSlipPolicy'] ?? '') !== 'metadata-only-native-review' || ($summary['routeSlip']['extractionPolicy'] ?? '') !== 'metadata-only-native-review') {
        throw new RuntimeException('Legacy DOC handoff self-test missing RouteSlip metadata-only policy');
    }
    if (($summary['routeSlip']['routed'] ?? null) !== true || ($summary['routeSlip']['returnOriginal'] ?? null) !== true || ($summary['routeSlip']['trackStatus'] ?? null) !== true) {
        throw new RuntimeException('Legacy DOC handoff self-test missing RouteSlip routing flags');
    }
    if (($summary['routeSlip']['protect'] ?? null) !== 2 || ($summary['routeSlip']['stage'] ?? null) !== 1 || ($summary['routeSlip']['deliveryOption'] ?? null) !== 1 || ($summary['routeSlip']['deliveryMode'] ?? '') !== 'parallel') {
        throw new RuntimeException('Legacy DOC handoff self-test missing RouteSlip option metadata');
    }
    if (($summary['routeSlip']['subject'] ?? '') !== 'Legacy DOC packet' || ($summary['routeSlip']['message'] ?? '') !== 'Please review before import.' || ($summary['routeSlip']['status'] ?? '') !== 'Awaiting legal signoff' || ($summary['routeSlip']['title'] ?? '') !== 'Route packet 42') {
        throw new RuntimeException('Legacy DOC handoff self-test missing RouteSlip route strings');
    }
    if (($summary['routeSlip']['recipients'][0]['name'] ?? '') !== 'Route Reviewer' || ($summary['routeSlip']['recipients'][0]['entryIdHex'] ?? '') !== '656e7472792d69642d303031') {
        throw new RuntimeException('Legacy DOC handoff self-test missing RouteSlip first recipient metadata');
    }
    if (($summary['routeSlip']['recipients'][1]['name'] ?? '') !== 'Route Archivist' || ($summary['routeSlip']['recipients'][1]['entryIdByteCount'] ?? null) !== 12) {
        throw new RuntimeException('Legacy DOC handoff self-test missing RouteSlip second recipient metadata');
    }
    if (str_contains($summary['wordpressBlocks'], 'Route Reviewer') || str_contains($summary['wordpressBlocks'], 'Please review before import.') || str_contains($summary['wordpressBlocks'], 'Route Archivist')) {
        throw new RuntimeException('Legacy DOC handoff self-test rendered RouteSlip metadata into blocks');
    }
    if (($summary['metadata']['revisionAuthorCount'] ?? null) !== 4 || count($summary['revisionAuthors'] ?? []) !== 4) {
        throw new RuntimeException('Legacy DOC handoff self-test missing revision author metadata');
    }
    if (($summary['metadata']['revisionAuthorPolicy'] ?? '') !== 'metadata-only-native-review') {
        throw new RuntimeException('Legacy DOC handoff self-test missing revision author policy');
    }
    if (($summary['revisionAuthors'][0]['name'] ?? '') !== 'Unknown' || ($summary['revisionAuthors'][0]['reservedUnknownAuthor'] ?? null) !== true) {
        throw new RuntimeException('Legacy DOC handoff self-test missing SttbfRMark Unknown sentinel');
    }
    if (($summary['revisionAuthors'][1]['name'] ?? '') !== 'Migration Lead' || ($summary['revisionAuthors'][2]['reviewerRole'] ?? '') !== 'revision-author') {
        throw new RuntimeException('Legacy DOC handoff self-test missing SttbfRMark reviewer records');
    }
    if (str_contains($summary['wordpressBlocks'], 'Archive Owner') || str_contains($summary['wordpressBlocks'], 'Review Editor')) {
        throw new RuntimeException('Legacy DOC handoff self-test rendered revision author metadata into blocks');
    }
    if (($summary['metadata']['captionDefinitionCount'] ?? null) !== 2 || count($summary['captionDefinitions'] ?? []) !== 2) {
        throw new RuntimeException('Legacy DOC handoff self-test missing SttbfCaption metadata');
    }
    if (($summary['metadata']['captionDefinitionPolicy'] ?? '') !== 'metadata-only-native-review') {
        throw new RuntimeException('Legacy DOC handoff self-test missing SttbfCaption metadata-only policy');
    }
    if (($summary['captionDefinitions'][0]['label'] ?? '') !== 'Figure'
        || ($summary['captionDefinitions'][0]['insertLocation'] ?? '') !== 'above-selected-item'
        || ($summary['captionDefinitions'][0]['headingLevel'] ?? null) !== 2
        || ($summary['captionDefinitions'][0]['numberFormat'] ?? '') !== 'upperRoman'
        || ($summary['captionDefinitions'][0]['chapterSeparator'] ?? '') !== 'period'
    ) {
        throw new RuntimeException('Legacy DOC handoff self-test missing SttbfCaption Figure CAPI metadata');
    }
    if (($summary['captionDefinitions'][1]['label'] ?? '') !== 'Table'
        || ($summary['captionDefinitions'][1]['includeLabel'] ?? null) !== false
        || ($summary['captionDefinitions'][1]['numberFormat'] ?? '') !== 'none'
    ) {
        throw new RuntimeException('Legacy DOC handoff self-test missing SttbfCaption no-label metadata');
    }
    if (($summary['metadata']['autoCaptionRuleCount'] ?? null) !== 2 || count($summary['autoCaptionRules'] ?? []) !== 2) {
        throw new RuntimeException('Legacy DOC handoff self-test missing SttbfAutoCaption metadata');
    }
    if (($summary['metadata']['autoCaptionPolicy'] ?? '') !== 'metadata-only-native-review') {
        throw new RuntimeException('Legacy DOC handoff self-test missing SttbfAutoCaption metadata-only policy');
    }
    if (($summary['autoCaptionRules'][0]['progId'] ?? '') !== 'Word.Picture.8'
        || ($summary['autoCaptionRules'][0]['captionLabel'] ?? '') !== 'Figure'
        || ($summary['autoCaptionRules'][0]['captionHeadingLevel'] ?? null) !== 2
        || ($summary['autoCaptionRules'][1]['progId'] ?? '') !== 'Excel.Chart.8'
        || ($summary['autoCaptionRules'][1]['captionLabel'] ?? '') !== 'Table'
    ) {
        throw new RuntimeException('Legacy DOC handoff self-test missing SttbfAutoCaption rule linkage');
    }
    if (str_contains($summary['wordpressBlocks'], 'Word.Picture.8') || str_contains($summary['wordpressBlocks'], 'Excel.Chart.8')) {
        throw new RuntimeException('Legacy DOC handoff self-test rendered AutoCaption ProgIDs into blocks');
    }
    $documentProperties = $summary['documentProperties'] ?? null;
    $documentPolicyFlags = is_array($documentProperties) && is_array($documentProperties['policyFlags'] ?? null) ? $documentProperties['policyFlags'] : [];
    if (!is_array($documentProperties) || ($summary['metadata']['documentPropertyByteCount'] ?? null) !== 84 || ($documentProperties['byteCount'] ?? null) !== 84) {
        throw new RuntimeException('Legacy DOC handoff self-test missing DOP document-property byte counts');
    }
    if (!in_array('auto-hyphenation', $documentPolicyFlags, true) || !in_array('include-subdocuments-in-statistics', $documentPolicyFlags, true) || !in_array('gutter-at-top', $documentPolicyFlags, true)) {
        throw new RuntimeException('Legacy DOC handoff self-test missing DOP policy flags');
    }
    $documentCompatibilityOptionFlags = is_array($documentProperties['compatibilityOptionFlags'] ?? null) ? $documentProperties['compatibilityOptionFlags'] : [];
    if (!in_array('no-tab-hanging-indent', $documentCompatibilityOptionFlags, true) || !in_array('no-space-raise-lower', $documentCompatibilityOptionFlags, true)) {
        throw new RuntimeException('Legacy DOC handoff self-test missing DOP compatibility option flags');
    }
    if (($summary['metadata']['documentCompatibilityOptionFlags'] ?? []) !== $documentCompatibilityOptionFlags) {
        throw new RuntimeException('Legacy DOC handoff self-test missing DOP compatibility option metadata copy');
    }
    if (($documentProperties['defaultTabStopTwips'] ?? null) !== 720 || ($documentProperties['htmlCodePage'] ?? null) !== 65001) {
        throw new RuntimeException('Legacy DOC handoff self-test missing DOP tab/codepage metadata');
    }
    if (($documentProperties['createdAt'] ?? '') !== '2024-04-06T07:08:00' || ($documentProperties['lastPrintedAt'] ?? '') !== '2024-04-09T11:12:00') {
        throw new RuntimeException('Legacy DOC handoff self-test missing DOP DTTM timestamps');
    }
    if (($documentProperties['statistics']['wordCount'] ?? null) !== 2345 || ($documentProperties['statistics']['wordCountWithSubdocuments'] ?? null) !== 2400) {
        throw new RuntimeException('Legacy DOC handoff self-test missing DOP document statistics');
    }
    if (($documentProperties['view']['kind'] ?? '') !== 'web' || ($documentProperties['view']['zoomPercent'] ?? null) !== 125 || ($documentProperties['view']['zoomKind'] ?? '') !== 'best-fit') {
        throw new RuntimeException('Legacy DOC handoff self-test missing DOP saved view metadata');
    }
    if (str_contains($summary['wordpressBlocks'], 'auto-hyphenation') || str_contains($summary['wordpressBlocks'], 'no-tab-hanging-indent') || str_contains($summary['wordpressBlocks'], '0a0b0c0d')) {
        throw new RuntimeException('Legacy DOC handoff self-test rendered DOP policy metadata into blocks');
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
        'template',
        'complex',
        'hasPictures',
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
        || ($fibRgLw97['ccpTxbx'] ?? null) !== $textboxSubdocumentCharacters
        || ($fibRgLw97['ccpHdrTxbx'] ?? null) !== $headerTextboxSubdocumentCharacters
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
            'endCp' => $textboxSubdocumentCpStart,
            'characterCount' => $endnoteSubdocumentCharacters,
        ],
        [
            'type' => 'textbox',
            'startCp' => $textboxSubdocumentCpStart,
            'endCp' => $headerTextboxSubdocumentCpStart,
            'characterCount' => $textboxSubdocumentCharacters,
        ],
        [
            'type' => 'header-textbox',
            'startCp' => $headerTextboxSubdocumentCpStart,
            'endCp' => $pieceTableLastCp,
            'characterCount' => $headerTextboxSubdocumentCharacters,
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
    if (($summary['metadata']['formattingRunCount'] ?? null) !== 5 || ($summary['metadata']['paragraphFormattingRunCount'] ?? null) !== 1) {
        throw new RuntimeException('Legacy DOC handoff self-test missing formatting table run counts');
    }
    if (($summary['metadata']['characterFormattingRunCount'] ?? null) !== 4 || ($summary['formattingRuns'][0]['table'] ?? '') !== 'PlcBtePapx') {
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
    if (($summary['formattingRuns'][2]['startFc'] ?? null) !== $secondPieceStart || ($summary['formattingRuns'][2]['endFc'] ?? null) !== $inlinePictureFc) {
        throw new RuntimeException('Legacy DOC handoff self-test missing character formatting second-piece range');
    }
    if (($summary['formattingRuns'][3]['startFc'] ?? null) !== $inlinePictureFc || ($summary['formattingRuns'][3]['endFc'] ?? null) !== $inlinePictureFc + 2 || ($summary['formattingRuns'][3]['fkpRunCount'] ?? null) !== 4) {
        throw new RuntimeException('Legacy DOC handoff self-test missing character formatting picture range');
    }
    if (($summary['formattingRuns'][0]['canApplyFormatting'] ?? null) !== false) {
        throw new RuntimeException('Legacy DOC handoff self-test should keep full SPRM formatting expansion disabled');
    }
    if (($summary['metadata']['revisionMarkedFormattingRunCount'] ?? null) !== 2 || ($summary['metadata']['formattingRevisionPolicy'] ?? '') !== 'metadata-only-native-review') {
        throw new RuntimeException('Legacy DOC handoff self-test missing revision-mark formatting metadata');
    }
    $paragraphRevisionMark = $summary['formattingRuns'][0]['revisionMarks'][0] ?? [];
    if (($paragraphRevisionMark['type'] ?? '') !== 'paragraph-property' || ($paragraphRevisionMark['source'] ?? '') !== 'PapxFkp') {
        throw new RuntimeException('Legacy DOC handoff self-test missing PAPX paragraph property revision metadata');
    }
    if (($paragraphRevisionMark['authorName'] ?? '') !== 'Migration Lead' || ($paragraphRevisionMark['authorSourceTable'] ?? '') !== 'SttbfRMark') {
        throw new RuntimeException('Legacy DOC handoff self-test missing SttbfRMark-linked PAPX revision author');
    }
    if (($paragraphRevisionMark['timestamp'] ?? '') !== '2024-04-06T07:08:00' || ($paragraphRevisionMark['canApplyRevision'] ?? null) !== false) {
        throw new RuntimeException('Legacy DOC handoff self-test missing metadata-only PAPX revision timestamp');
    }
    $formattingRevisionMark = $summary['formattingRuns'][1]['revisionMarks'][0] ?? [];
    if (($formattingRevisionMark['type'] ?? '') !== 'inserted' || ($formattingRevisionMark['authorName'] ?? '') !== 'Migration Lead') {
        throw new RuntimeException('Legacy DOC handoff self-test missing SttbfRMark-linked CHPX revision author');
    }
    if (($formattingRevisionMark['timestamp'] ?? '') !== '2024-04-07T08:09:00' || ($formattingRevisionMark['canApplyRevision'] ?? null) !== false) {
        throw new RuntimeException('Legacy DOC handoff self-test missing metadata-only CHPX revision timestamp');
    }
    if (str_contains($summary['wordpressBlocks'], '2024-04-07T08:09:00')) {
        throw new RuntimeException('Legacy DOC handoff self-test rendered CHPX revision metadata into blocks');
    }
    if (str_contains($summary['wordpressBlocks'], '2024-04-06T07:08:00')) {
        throw new RuntimeException('Legacy DOC handoff self-test rendered PAPX revision metadata into blocks');
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
    if (($summary['metadata']['listLevelParagraphPropertyCount'] ?? null) !== 1 || ($summary['metadata']['listLevelTextPropertyCount'] ?? null) !== 1) {
        throw new RuntimeException('Legacy DOC handoff self-test missing list-level formatting metadata counts');
    }
    if (($summary['listFormats'][0]['levels'][0]['paragraphProperties'][0]['sourceSprm'] ?? '') !== 'sprmPJc' || ($summary['listFormats'][0]['levels'][0]['paragraphProperties'][0]['value'] ?? '') !== 'center') {
        throw new RuntimeException('Legacy DOC handoff self-test missing list-level paragraph formatting metadata');
    }
    if (($summary['listFormats'][0]['levels'][0]['textProperties'][0]['sourceSprm'] ?? '') !== 'sprmCFBold' || ($summary['listFormats'][0]['levels'][0]['textProperties'][0]['enabled'] ?? null) !== true) {
        throw new RuntimeException('Legacy DOC handoff self-test missing list-level text formatting metadata');
    }
    foreach (['sprmPJc', 'sprmCFBold'] as $hiddenListMetadata) {
        if (str_contains($summary['wordpressBlocks'], $hiddenListMetadata)) {
            throw new RuntimeException('Legacy DOC handoff self-test rendered list-level formatting metadata into blocks');
        }
    }
    if (($summary['listFormats'][1]['levels'][0]['numberFormat'] ?? '') !== 'bullet' || ($summary['listFormats'][1]['levels'][0]['numberText'] ?? '') !== '•') {
        throw new RuntimeException('Legacy DOC handoff self-test missing bullet list level metadata');
    }
    if (($summary['listOverrides'][0]['autoNumberField'] ?? '') !== 'AUTONUMLGL' || ($summary['listOverrides'][0]['levels'][0]['startAt'] ?? null) !== 7) {
        throw new RuntimeException('Legacy DOC handoff self-test missing list override start-at metadata');
    }
    if (($summary['listOverrides'][1]['firstParagraphCp'] ?? null) !== $firstPieceCharacters || ($summary['listOverrides'][0]['levels'][0]['formattingOverride'] ?? null) !== false) {
        throw new RuntimeException('Legacy DOC handoff self-test missing list override paragraph/start metadata');
    }
    if (($summary['listFormats'][0]['levels'][0]['canApplyNumbering'] ?? null) !== false) {
        throw new RuntimeException('Legacy DOC handoff self-test should keep legacy numbering application disabled');
    }
    if (($summary['metadata']['cfbStreamCount'] ?? null) !== 16 || ($summary['metadata']['cfbTimestampedDirectoryEntryCount'] ?? null) !== 2) {
        throw new RuntimeException('Legacy DOC handoff self-test missing CFB directory counts');
    }
    $compoundFile = CompoundFileBinary::fromBytes($docBytes);
    if (!in_array($unicodeReviewStreamPath, $summary['streams'], true) || !$compoundFile->hasStream('résumé/σύνοψη') || $compoundFile->readStream('RÉSUMÉ/ΣΎΝΟΨΗ') !== $unicodeReviewStreamBytes) {
        throw new RuntimeException('Legacy DOC handoff self-test missing Unicode CFB stream lookup');
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
        'QA Ω' => 'unicode-review-α',
    ]) {
        throw new RuntimeException('Legacy DOC handoff self-test missing user-defined custom properties');
    }
    if (
        ($summary['metadata']['hyperlinkBase'] ?? '') !== 'https://example.test/legacy/'
        || ($summary['metadata']['hyperlinkBasePolicy'] ?? '') !== 'metadata-only-native-review'
        || ($summary['metadata']['hyperlinkCount'] ?? null) !== 2
        || ($summary['metadata']['hyperlinkPolicy'] ?? '') !== 'metadata-only-native-review'
    ) {
        throw new RuntimeException('Legacy DOC handoff self-test missing reserved hyperlink metadata');
    }
    if (
        ($summary['metadata']['hyperlinks'][0]['target'] ?? '') !== 'appendix-a.html'
        || ($summary['metadata']['hyperlinks'][0]['location'] ?? '') !== 'ReviewAnchor'
        || ($summary['metadata']['hyperlinks'][0]['targetKind'] ?? '') !== 'relative-or-file'
        || ($summary['metadata']['hyperlinks'][0]['fixupStatus'] ?? '') !== 'synchronized'
        || ($summary['metadata']['hyperlinks'][1]['target'] ?? '') !== 'https://example.test/source.doc'
        || ($summary['metadata']['hyperlinks'][1]['targetKind'] ?? '') !== 'external-url'
        || ($summary['metadata']['hyperlinks'][1]['fixupStatus'] ?? '') !== 'requires-fixup'
        || ($summary['metadata']['hyperlinks'][1]['canExposeBytes'] ?? null) !== false
    ) {
        throw new RuntimeException('Legacy DOC handoff self-test missing reserved hyperlink record details');
    }
    if (
        str_contains($blocks, 'https://example.test/legacy/')
        || str_contains($blocks, 'appendix-a.html')
        || str_contains($blocks, 'https://example.test/source.doc')
    ) {
        throw new RuntimeException('Legacy DOC handoff self-test rendered reserved hyperlink metadata into blocks');
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
    if (($summary['metadata']['commentAuthorCount'] ?? null) !== 4 || count($summary['commentAuthors'] ?? []) !== 4) {
        throw new RuntimeException('Legacy DOC handoff self-test missing comment author owner table');
    }
    if (($summary['commentAuthors'][0]['name'] ?? '') !== 'Migration Lead' || ($summary['commentAuthors'][3]['name'] ?? '') !== 'Mira Reviewer') {
        throw new RuntimeException('Legacy DOC handoff self-test missing comment author names');
    }
    if (($summary['comments'][0]['marker'] ?? '') !== 'MR' || ($summary['comments'][0]['authorInitials'] ?? '') !== 'MR') {
        throw new RuntimeException('Legacy DOC handoff self-test missing comment author initials');
    }
    if (($summary['comments'][0]['authorIndex'] ?? null) !== 3 || ($summary['comments'][0]['bookmarkTag'] ?? null) !== 0x2042) {
        throw new RuntimeException('Legacy DOC handoff self-test missing comment descriptor provenance');
    }
    if (($summary['comments'][0]['authorName'] ?? '') !== 'Mira Reviewer') {
        throw new RuntimeException('Legacy DOC handoff self-test missing resolved comment author name');
    }
    $subdocumentsByType = [];
    foreach (($summary['subdocuments'] ?? []) as $subdocument) {
        $subdocumentsByType[(string) ($subdocument['type'] ?? '')] = $subdocument;
    }
    if (($summary['metadata']['subdocumentCount'] ?? null) !== 6 || count($subdocumentsByType) !== 6) {
        throw new RuntimeException('Legacy DOC handoff self-test missing supplemental subdocument text records');
    }
    if (
        ($subdocumentsByType['footnote']['text'] ?? '') !== $footnoteSubdocumentText
        || ($subdocumentsByType['header']['text'] ?? '') !== $headerSubdocumentText
        || ($subdocumentsByType['comment']['text'] ?? '') !== $commentSubdocumentText
        || ($subdocumentsByType['endnote']['text'] ?? '') !== $endnoteSubdocumentText
        || ($subdocumentsByType['textbox']['text'] ?? '') !== $textboxSubdocumentText
        || ($subdocumentsByType['header-textbox']['text'] ?? '') !== $headerTextboxSubdocumentText
    ) {
        throw new RuntimeException('Legacy DOC handoff self-test missing supplemental subdocument body text');
    }
    $headerFooterStoryText = substr($headerSubdocumentText, 0, -1);
    if (
        ($summary['metadata']['headerFooterStoryCount'] ?? null) !== 1
        || ($summary['metadata']['headerFooterDeclaredStoryCount'] ?? null) !== 12
        || count($summary['headerFooterStories'] ?? []) !== 1
        || ($summary['metadata']['headerFooterStories'] ?? []) !== ($summary['headerFooterStories'] ?? [])
    ) {
        throw new RuntimeException('Legacy DOC handoff self-test missing PlcfHdd header/footer story inventory');
    }
    if (
        ($summary['headerFooterStories'][0]['sourceTable'] ?? '') !== 'PlcfHdd'
        || ($summary['headerFooterStories'][0]['storyNumber'] ?? null) !== 7
        || ($summary['headerFooterStories'][0]['role'] ?? '') !== 'odd-page-header'
        || ($summary['headerFooterStories'][0]['kind'] ?? '') !== 'header'
        || ($summary['headerFooterStories'][0]['sectionIndex'] ?? null) !== 1
        || ($summary['headerFooterStories'][0]['text'] ?? '') !== $headerFooterStoryText
    ) {
        throw new RuntimeException('Legacy DOC handoff self-test missing PlcfHdd odd-header story metadata');
    }
    if (
        ($summary['headerFooterStories'][0]['guardCp'] ?? null) !== $headerSubdocumentCharacters - 1
        || ($summary['headerFooterStories'][0]['hasGuardParagraph'] ?? null) !== true
    ) {
        throw new RuntimeException('Legacy DOC handoff self-test missing PlcfHdd guard-paragraph boundary');
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
    if (($summary['metadata']['pictureReferenceCount'] ?? null) !== 1 || count($summary['pictureReferences'] ?? []) !== 1) {
        throw new RuntimeException('Legacy DOC handoff self-test missing inline picture reference count');
    }
    if (
        ($summary['pictureReferences'][0]['type'] ?? '') !== 'inline-picture'
        || ($summary['pictureReferences'][0]['pictureIndex'] ?? null) !== 1
        || ($summary['pictureReferences'][0]['characterCode'] ?? null) !== 1
        || ($summary['pictureReferences'][0]['canExposeBytes'] ?? null) !== false
        || ($summary['pictureReferences'][0]['source'] ?? '') !== 'chpx-data-stream'
        || ($summary['pictureReferences'][0]['extractionPolicy'] ?? '') !== 'metadata-only-native-review'
        || ($summary['pictureReferences'][0]['dataStreamOffset'] ?? null) !== $pictureDataStreamOffset
        || ($summary['pictureReferences'][0]['availableDataBytes'] ?? null) !== strlen($pictureDataStream) - $pictureDataStreamOffset
        || ($summary['pictureReferences'][0]['sourceSprms'] ?? []) !== ['sprmCFSpec', 'sprmCPicLocation', 'sprmCFData']
        || ($summary['pictureReferences'][0]['dataStreamKind'] ?? '') !== 'binary-data'
    ) {
        throw new RuntimeException('Legacy DOC handoff self-test missing inline picture reference provenance');
    }
    if (($summary['metadata']['pictureDataFormattingRunCount'] ?? null) !== 1 || ($summary['formattingRuns'][3]['pictureData'][0]['dataStreamOffset'] ?? null) !== $pictureDataStreamOffset) {
        throw new RuntimeException('Legacy DOC handoff self-test missing CHPX picture Data-stream formatting metadata');
    }
    if (($summary['metadata']['pictureExtractionPolicy'] ?? '') !== 'metadata-only-native-review' || ($summary['metadata']['pictureReferences'] ?? []) !== ($summary['pictureReferences'] ?? [])) {
        throw new RuntimeException('Legacy DOC handoff self-test exposed an unsafe picture extraction policy');
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
    $totalFieldRecordCount = count($fieldRecords) + count($headerFieldRecords) + count($endnoteFieldRecords) + count($textboxFieldRecords) + count($headerTextboxFieldRecords);
    if (($summary['metadata']['fieldCharacterCount'] ?? null) !== $totalFieldRecordCount || count($summary['fieldCharacters'] ?? []) !== $totalFieldRecordCount) {
        throw new RuntimeException('Legacy DOC handoff self-test missing Plcfld field-character inventory');
    }
    if (($summary['metadata']['fieldCount'] ?? null) !== 30 || count($summary['fields'] ?? []) !== 30) {
        throw new RuntimeException('Legacy DOC handoff self-test missing Plcfld field range inventory');
    }
    if (($summary['metadata']['fields'] ?? []) !== ($summary['fields'] ?? [])) {
        throw new RuntimeException('Legacy DOC handoff self-test missing Plcfld fields in metadata');
    }
    if (($summary['metadata']['fieldStoryCount'] ?? null) !== 5 || ($summary['metadata']['fieldStories'] ?? []) !== ($summary['fieldStories'] ?? [])) {
        throw new RuntimeException('Legacy DOC handoff self-test missing supplemental Plcfld story inventory');
    }
    if (array_column($summary['fieldStories'] ?? [], 'story') !== ['main', 'header', 'endnote', 'textbox', 'header-textbox'] || array_column($summary['fieldStories'] ?? [], 'table') !== ['PlcfldMom', 'PlcfldHdr', 'PlcfldEdn', 'PlcfldTxbx', 'PlcfldHdrTxbx']) {
        throw new RuntimeException('Legacy DOC handoff self-test missing Plcfld story table mapping');
    }
    if (array_column($summary['fieldStories'] ?? [], 'fieldCount') !== [26, 1, 1, 1, 1] || array_column($summary['fieldStories'] ?? [], 'fieldCharacterCount') !== [count($fieldRecords), count($headerFieldRecords), count($endnoteFieldRecords), count($textboxFieldRecords), count($headerTextboxFieldRecords)]) {
        throw new RuntimeException('Legacy DOC handoff self-test missing Plcfld story field counts');
    }
    if (array_column($summary['fieldStories'] ?? [], 'characterCount') !== [$totalPieceCharacters, $headerSubdocumentCharacters, $endnoteSubdocumentCharacters, $textboxSubdocumentCharacters, $headerTextboxSubdocumentCharacters]) {
        throw new RuntimeException('Legacy DOC handoff self-test missing Plcfld story character counts');
    }
    if (array_column($summary['fields'] ?? [], 'type') !== [
        'hyperlink',
        'hyperlink',
        'ref',
        'pageref',
        'page',
        'ask',
        'fillin',
        'formtext',
        'mergefield',
        'data',
        'docvariable',
        'symbol',
        'includepicture',
        'includetext',
        'macrobutton',
        'gotobutton',
        'autonumlgl',
        'page',
        'hyperlink',
        'filename',
        'template',
        'filesize',
        'import',
        'include',
        'quote',
        'shape',
        'date',
        'noteref',
        'page',
        'ref',
    ]) {
        throw new RuntimeException('Legacy DOC handoff self-test missing Plcfld field type mapping');
    }
    if (($summary['fieldCharacters'][0]['kind'] ?? '') !== 'begin' || ($summary['fieldCharacters'][0]['type'] ?? '') !== 'hyperlink') {
        throw new RuntimeException('Legacy DOC handoff self-test missing first Plcfld begin record');
    }
    if (($summary['fields'][4]['endFlags'] ?? null) !== 0x94
        || ($summary['fields'][4]['resultDirty'] ?? null) !== true
        || ($summary['fields'][4]['locked'] ?? null) !== true
        || ($summary['fields'][4]['hasSeparatorFlag'] ?? null) !== true
        || ($summary['fields'][4]['separatorFlagMatchesRange'] ?? null) !== true
    ) {
        throw new RuntimeException('Legacy DOC handoff self-test missing PAGE Plcfld end flag metadata');
    }
    if (($summary['fields'][4]['endFlagNames'] ?? []) !== ['result-dirty', 'locked', 'has-separator']) {
        throw new RuntimeException('Legacy DOC handoff self-test missing PAGE Plcfld end flag names');
    }
    $pageEndCharacter = null;
    foreach ($summary['fieldCharacters'] ?? [] as $fieldCharacter) {
        if (($fieldCharacter['kind'] ?? '') === 'end'
            && ($fieldCharacter['story'] ?? '') === 'main'
            && ($fieldCharacter['cp'] ?? null) === ($summary['fields'][4]['endCp'] ?? null)
        ) {
            $pageEndCharacter = $fieldCharacter;
            break;
        }
    }
    if (($pageEndCharacter['endFlags'] ?? null) !== 0x94 || ($pageEndCharacter['locked'] ?? null) !== true) {
        throw new RuntimeException('Legacy DOC handoff self-test missing PAGE Plcfld end-character flags');
    }
    if (($summary['fields'][5]['typeCode'] ?? null) !== 0x26 || ($summary['fields'][5]['type'] ?? '') !== 'ask') {
        throw new RuntimeException('Legacy DOC handoff self-test missing ASK Plcfld metadata');
    }
    if (($summary['fields'][6]['typeCode'] ?? null) !== 0x27 || ($summary['fields'][6]['type'] ?? '') !== 'fillin') {
        throw new RuntimeException('Legacy DOC handoff self-test missing FILLIN Plcfld metadata');
    }
    if (($summary['fields'][7]['typeCode'] ?? null) !== 0x46 || ($summary['fields'][7]['hasResult'] ?? null) !== true) {
        throw new RuntimeException('Legacy DOC handoff self-test missing FORMTEXT Plcfld result range');
    }
    $formFieldDataSamples = $summary['formFieldDataSamples'] ?? [];
    if (
        ($formFieldDataSamples['reviewerName']['fieldType'] ?? '') !== 'text'
        || ($formFieldDataSamples['reviewerName']['name'] ?? '') !== 'ReviewerName'
        || ($formFieldDataSamples['reviewerName']['defaultText'] ?? '') !== 'pending review'
        || ($formFieldDataSamples['reviewerName']['textFormat'] ?? '') !== 'Title Case'
        || ($formFieldDataSamples['reviewerName']['maxLength'] ?? null) !== 40
        || ($formFieldDataSamples['reviewerName']['hasOwnHelpText'] ?? null) !== true
        || ($formFieldDataSamples['reviewerName']['protected'] ?? null) !== true
    ) {
        throw new RuntimeException('Legacy DOC handoff self-test missing decoded FFData textbox metadata');
    }
    if (
        ($formFieldDataSamples['approval']['fieldType'] ?? '') !== 'checkbox'
        || ($formFieldDataSamples['approval']['name'] ?? '') !== 'ApproveImport'
        || ($formFieldDataSamples['approval']['defaultChecked'] ?? null) !== false
        || ($formFieldDataSamples['approval']['checked'] ?? null) !== true
        || ($formFieldDataSamples['approval']['checkboxSizeHalfPoints'] ?? null) !== 24
    ) {
        throw new RuntimeException('Legacy DOC handoff self-test missing decoded FFData checkbox metadata');
    }
    if (
        ($formFieldDataSamples['publicationState']['fieldType'] ?? '') !== 'dropdown'
        || ($formFieldDataSamples['publicationState']['name'] ?? '') !== 'PublicationState'
        || ($formFieldDataSamples['publicationState']['defaultDropDownItem'] ?? '') !== 'Review'
        || ($formFieldDataSamples['publicationState']['selectedDropDownItem'] ?? '') !== 'Publish'
        || ($formFieldDataSamples['publicationState']['dropDownItems'] ?? []) !== ['Draft', 'Review', 'Publish']
    ) {
        throw new RuntimeException('Legacy DOC handoff self-test missing decoded FFData dropdown metadata');
    }
    foreach (['PublicationState', 'Choose the publication state for the migrated post.'] as $hiddenFormFieldMetadata) {
        if (str_contains($summary['wordpressBlocks'], $hiddenFormFieldMetadata)) {
            throw new RuntimeException('Legacy DOC handoff self-test rendered FFData metadata into blocks');
        }
    }
    if (($summary['fields'][8]['typeCode'] ?? null) !== 0x3b || ($summary['fields'][8]['type'] ?? '') !== 'mergefield') {
        throw new RuntimeException('Legacy DOC handoff self-test missing MERGEFIELD Plcfld metadata');
    }
    if (($summary['fields'][9]['typeCode'] ?? null) !== 0x28 || ($summary['fields'][9]['type'] ?? '') !== 'data') {
        throw new RuntimeException('Legacy DOC handoff self-test missing DATA Plcfld metadata');
    }
    if (($summary['fields'][10]['typeCode'] ?? null) !== 0x40 || ($summary['fields'][10]['type'] ?? '') !== 'docvariable') {
        throw new RuntimeException('Legacy DOC handoff self-test missing DOCVARIABLE Plcfld metadata');
    }
    if (($summary['fields'][12]['typeCode'] ?? null) !== 0x43 || ($summary['fields'][12]['type'] ?? '') !== 'includepicture') {
        throw new RuntimeException('Legacy DOC handoff self-test missing INCLUDEPICTURE Plcfld metadata');
    }
    if (($summary['fields'][13]['typeCode'] ?? null) !== 0x44 || ($summary['fields'][13]['type'] ?? '') !== 'includetext') {
        throw new RuntimeException('Legacy DOC handoff self-test missing INCLUDETEXT Plcfld metadata');
    }
    if (($summary['fields'][14]['typeCode'] ?? null) !== 0x33 || ($summary['fields'][14]['type'] ?? '') !== 'macrobutton') {
        throw new RuntimeException('Legacy DOC handoff self-test missing MACROBUTTON Plcfld metadata');
    }
    if (($summary['fields'][15]['typeCode'] ?? null) !== 0x32 || ($summary['fields'][15]['type'] ?? '') !== 'gotobutton') {
        throw new RuntimeException('Legacy DOC handoff self-test missing GOTOBUTTON Plcfld metadata');
    }
    if (($summary['fields'][16]['typeCode'] ?? null) !== 0x35 || ($summary['fields'][16]['type'] ?? '') !== 'autonumlgl') {
        throw new RuntimeException('Legacy DOC handoff self-test missing AUTONUMLGL Plcfld metadata');
    }
    if (($summary['fields'][17]['typeCode'] ?? null) !== 0x21
        || ($summary['fields'][17]['type'] ?? '') !== 'page'
        || ($summary['fields'][17]['nestingLevel'] ?? null) !== 1
        || ($summary['fields'][17]['endFlags'] ?? null) !== 0xd4
        || ($summary['fields'][17]['nested'] ?? null) !== true
    ) {
        throw new RuntimeException('Legacy DOC handoff self-test missing nested PAGE Plcfld metadata');
    }
    if (($summary['fields'][18]['typeCode'] ?? null) !== 0x58
        || ($summary['fields'][18]['type'] ?? '') !== 'hyperlink'
        || ($summary['fields'][18]['nestingLevel'] ?? null) !== 0
        || ($summary['fields'][18]['nested'] ?? null) !== false
    ) {
        throw new RuntimeException('Legacy DOC handoff self-test missing outer nested HYPERLINK Plcfld metadata');
    }
    if (($summary['fields'][19]['typeCode'] ?? null) !== 0x1d || ($summary['fields'][19]['type'] ?? '') !== 'filename') {
        throw new RuntimeException('Legacy DOC handoff self-test missing FILENAME Plcfld metadata');
    }
    if (($summary['fields'][20]['typeCode'] ?? null) !== 0x1e || ($summary['fields'][20]['type'] ?? '') !== 'template') {
        throw new RuntimeException('Legacy DOC handoff self-test missing TEMPLATE Plcfld metadata');
    }
    if (($summary['fields'][21]['typeCode'] ?? null) !== 0x45 || ($summary['fields'][21]['type'] ?? '') !== 'filesize') {
        throw new RuntimeException('Legacy DOC handoff self-test missing FILESIZE Plcfld metadata');
    }
    if (($summary['fields'][22]['typeCode'] ?? null) !== 0x37 || ($summary['fields'][22]['type'] ?? '') !== 'import') {
        throw new RuntimeException('Legacy DOC handoff self-test missing IMPORT Plcfld metadata');
    }
    if (($summary['fields'][23]['typeCode'] ?? null) !== 0x24 || ($summary['fields'][23]['type'] ?? '') !== 'include') {
        throw new RuntimeException('Legacy DOC handoff self-test missing INCLUDE Plcfld metadata');
    }
    if (($summary['fields'][24]['typeCode'] ?? null) !== 0x23 || ($summary['fields'][24]['type'] ?? '') !== 'quote') {
        throw new RuntimeException('Legacy DOC handoff self-test missing QUOTE Plcfld metadata');
    }
    if (($summary['fields'][25]['typeCode'] ?? null) !== 0x5f || ($summary['fields'][25]['type'] ?? '') !== 'shape') {
        throw new RuntimeException('Legacy DOC handoff self-test missing SHAPE Plcfld metadata');
    }
    if (($summary['fields'][26]['story'] ?? '') !== 'header' || ($summary['fields'][26]['typeCode'] ?? null) !== 0x1f || ($summary['fields'][26]['type'] ?? '') !== 'date') {
        throw new RuntimeException('Legacy DOC handoff self-test missing header Plcfld DATE metadata');
    }
    if (($summary['fields'][27]['story'] ?? '') !== 'endnote' || ($summary['fields'][27]['typeCode'] ?? null) !== 0x05 || ($summary['fields'][27]['type'] ?? '') !== 'noteref') {
        throw new RuntimeException('Legacy DOC handoff self-test missing endnote Plcfld NOTEREF metadata');
    }
    if (($summary['fields'][28]['story'] ?? '') !== 'textbox' || ($summary['fields'][28]['typeCode'] ?? null) !== 0x21 || ($summary['fields'][28]['type'] ?? '') !== 'page') {
        throw new RuntimeException('Legacy DOC handoff self-test missing textbox Plcfld PAGE metadata');
    }
    if (($summary['fields'][29]['story'] ?? '') !== 'header-textbox' || ($summary['fields'][29]['typeCode'] ?? null) !== 0x03 || ($summary['fields'][29]['type'] ?? '') !== 'ref') {
        throw new RuntimeException('Legacy DOC handoff self-test missing header textbox Plcfld REF metadata');
    }
    foreach ([
        '<p><span id="legacy_anchor" class="legacy-doc-bookmark" data-legacy-doc-bookmark="legacy_anchor" data-legacy-doc-bookmark-start-cp="0" data-legacy-doc-bookmark-end-cp="21">Legacy DOC import ΩЖ魚</span></p>',
        '<p>Review<br/>note ',
        '<span class="legacy-doc-note-ref legacy-doc-footnote-ref" data-legacy-doc-note-type="footnote" data-legacy-doc-note-index="1" data-legacy-doc-note-reference-cp="' . (string) ($summary['footnotes'][0]['referenceCp'] ?? '') . '" data-legacy-doc-note-text-start-cp="0" data-legacy-doc-note-text-end-cp="35" data-legacy-doc-note-auto-numbered="true" data-legacy-doc-note-has-body="true" data-legacy-doc-note-body-character-count="35"><sup>1</sup></span>',
        '<span class="legacy-doc-note-ref legacy-doc-endnote-ref" data-legacy-doc-note-type="endnote" data-legacy-doc-note-index="0" data-legacy-doc-note-reference-cp="' . (string) ($summary['endnotes'][0]['referenceCp'] ?? '') . '" data-legacy-doc-note-text-start-cp="0" data-legacy-doc-note-text-end-cp="29" data-legacy-doc-note-auto-numbered="false" data-legacy-doc-note-has-body="true" data-legacy-doc-note-body-character-count="29"><sup>#</sup></span>',
        '<span class="legacy-doc-comment-ref" data-legacy-doc-comment-index="1" data-legacy-doc-comment-reference-cp="' . (string) ($summary['comments'][0]['referenceCp'] ?? '') . '" data-legacy-doc-comment-text-start-cp="0" data-legacy-doc-comment-text-end-cp="24" data-legacy-doc-comment-author-index="3" data-legacy-doc-comment-author-initials="MR" data-legacy-doc-comment-author-name="Mira Reviewer" data-legacy-doc-comment-bookmark-tag="8258" data-legacy-doc-comment-has-body="true" data-legacy-doc-comment-body-character-count="24"><sup>MR</sup></span>',
        '<a href="https://example.test/legacy-doc?source=42" title="Source packet">source dossier</a>',
        '<a href="#legacy_anchor">opening bookmark</a>',
        '<span class="legacy-doc-field legacy-doc-cross-reference legacy-doc-field-ref" data-legacy-doc-field="ref" data-legacy-doc-field-instruction="REF &quot;legacy_anchor&quot; \h" data-legacy-doc-cross-reference-type="bookmark" data-legacy-doc-cross-reference-target="legacy_anchor" data-legacy-doc-cross-reference-switches="h" data-legacy-doc-cross-reference-hyperlink="true">Legacy DOC import</span>',
        '<span class="legacy-doc-field legacy-doc-cross-reference legacy-doc-field-pageref" data-legacy-doc-field="pageref" data-legacy-doc-field-instruction="PAGEREF legacy_anchor \p" data-legacy-doc-cross-reference-type="bookmark-page" data-legacy-doc-cross-reference-target="legacy_anchor" data-legacy-doc-cross-reference-switches="p" data-legacy-doc-cross-reference-relative="true">7</span>',
        '<span class="legacy-doc-field legacy-doc-field-page" data-legacy-doc-field="page" data-legacy-doc-field-instruction="PAGE \* Arabic" data-legacy-doc-field-format="Arabic">7</span>',
        '<span class="legacy-doc-field legacy-doc-prompt-field legacy-doc-field-ask" data-legacy-doc-field="ask" data-legacy-doc-field-instruction="ASK Owner &quot;Owner?&quot; \d &quot;M&quot; \o" data-legacy-doc-prompt-field-type="bookmark-prompt" data-legacy-doc-prompt-field-name="Owner" data-legacy-doc-prompt-text="Owner?" data-legacy-doc-prompt-default="M" data-legacy-doc-prompt-switches="d o">Mia</span>',
        '<span class="legacy-doc-field legacy-doc-prompt-field legacy-doc-field-fillin" data-legacy-doc-field="fillin" data-legacy-doc-field-instruction="FILLIN &quot;Note?&quot; \d &quot;QA&quot;" data-legacy-doc-prompt-field-type="prompt" data-legacy-doc-prompt-text="Note?" data-legacy-doc-prompt-default="QA" data-legacy-doc-prompt-switches="d">Ready</span>',
        '<span class="legacy-doc-field legacy-doc-form-field legacy-doc-field-formtext" data-legacy-doc-field="formtext" data-legacy-doc-field-instruction="FORMTEXT \* MERGEFORMAT" data-legacy-doc-form-field-type="text" data-legacy-doc-field-format="MERGEFORMAT">pending review</span>',
        '<span class="legacy-doc-field legacy-doc-data-field legacy-doc-field-mergefield" data-legacy-doc-field="mergefield" data-legacy-doc-field-instruction="MERGEFIELD Name" data-legacy-doc-data-field-type="mail-merge" data-legacy-doc-data-field-name="Name" data-legacy-doc-mail-merge-policy="metadata-only-native-review" data-legacy-doc-mail-merge-has-associated-data-source="true" data-legacy-doc-mail-merge-associated-data-source-table="SttbfAssoc" data-legacy-doc-mail-merge-associated-data-source-index="8" data-legacy-doc-mail-merge-has-header-document="true" data-legacy-doc-mail-merge-header-document-table="SttbfAssoc" data-legacy-doc-mail-merge-header-document-index="9" data-legacy-doc-mail-merge-external-reference-table="SttbFnm" data-legacy-doc-mail-merge-external-reference-index="1" data-legacy-doc-mail-merge-external-reference-type="mail-merge-data-source" data-legacy-doc-mail-merge-external-reference-document-index="4" data-legacy-doc-mail-merge-external-reference-file-system="non-file-system" data-legacy-doc-mail-merge-external-reference-can-expose-bytes="false">Ada</span>',
        '<span class="legacy-doc-field legacy-doc-data-field legacy-doc-field-docvariable" data-legacy-doc-field="docvariable" data-legacy-doc-field-instruction="DOCVARIABLE Batch" data-legacy-doc-data-field-type="document-variable" data-legacy-doc-data-field-name="Batch">42</span>',
        '<span class="legacy-doc-field legacy-doc-symbol-field legacy-doc-field-symbol" data-legacy-doc-field="symbol" data-legacy-doc-field-instruction="SYMBOL 183 \f &quot;Symbol&quot; \s 12 \u" data-legacy-doc-symbol-code="183" data-legacy-doc-symbol-font="Symbol" data-legacy-doc-symbol-size="12" data-legacy-doc-symbol-switches="u">·</span>',
        '<span class="legacy-doc-field legacy-doc-include-field legacy-doc-field-includepicture" data-legacy-doc-field="includepicture" data-legacy-doc-field-instruction="INCLUDEPICTURE &quot;chart.png&quot; \d \* MERGEFORMAT" data-legacy-doc-include-field-type="picture" data-legacy-doc-include-source="chart.png" data-legacy-doc-include-source-kind="file-path" data-legacy-doc-include-source-basename="chart.png" data-legacy-doc-field-format="MERGEFORMAT" data-legacy-doc-include-field-switches="d" data-legacy-doc-include-field-switch-d="true">chart</span>',
        '<span class="legacy-doc-field legacy-doc-include-field legacy-doc-field-includetext" data-legacy-doc-field="includetext" data-legacy-doc-field-instruction="INCLUDETEXT &quot;https://e.test/c.doc&quot; \c &quot;H1&quot; \!" data-legacy-doc-include-field-type="text" data-legacy-doc-include-source="https://e.test/c.doc" data-legacy-doc-include-source-kind="external-url" data-legacy-doc-include-source-basename="c.doc" data-legacy-doc-include-external-reference-index="2" data-legacy-doc-include-external-reference-match="path" data-legacy-doc-include-external-reference-type="subdocument" data-legacy-doc-include-external-reference-document-index="5" data-legacy-doc-include-external-reference-file-system="non-file-system" data-legacy-doc-include-external-reference-policy="metadata-only-native-review" data-legacy-doc-include-external-reference-can-expose-bytes="false" data-legacy-doc-include-field-switches="c !" data-legacy-doc-include-field-switch-c="H1" data-legacy-doc-include-field-lock-result="true">clause</span>',
        '<span class="legacy-doc-field legacy-doc-action-field legacy-doc-field-macrobutton" data-legacy-doc-field="macrobutton" data-legacy-doc-field-instruction="MACROBUTTON ApproveImport &quot;Approve packet&quot;" data-legacy-doc-action-field-type="macro" data-legacy-doc-action-field-command="ApproveImport" data-legacy-doc-action-field-command-kind="macro" data-legacy-doc-action-field-policy="metadata-only-native-review" data-legacy-doc-action-field-execution="disabled" data-legacy-doc-action-field-display-text="Approve packet">Approve packet</span>',
        '<span class="legacy-doc-field legacy-doc-action-field legacy-doc-field-gotobutton" data-legacy-doc-field="gotobutton" data-legacy-doc-field-instruction="GOTOBUTTON legacy_anchor &quot;Jump to source&quot;" data-legacy-doc-action-field-type="navigation" data-legacy-doc-action-field-destination="legacy_anchor" data-legacy-doc-action-field-destination-kind="bookmark-or-goto-target" data-legacy-doc-action-field-policy="metadata-only-native-review" data-legacy-doc-action-field-execution="disabled" data-legacy-doc-action-field-display-text="Jump to source">Jump to source</span>',
        '<span class="legacy-doc-field legacy-doc-numbering-field legacy-doc-field-autonumlgl" data-legacy-doc-field="autonumlgl" data-legacy-doc-field-instruction="AUTONUMLGL" data-legacy-doc-numbering-field-type="auto-number-legal" data-legacy-doc-numbering-field-list-policy="metadata-only-native-review" data-legacy-doc-numbering-field-list-match-count="1" data-legacy-doc-numbering-field-list-ilfo="1" data-legacy-doc-numbering-field-list-lsid="1001" data-legacy-doc-numbering-field-list-first-paragraph-cp="0" data-legacy-doc-numbering-field-list-index="1" data-legacy-doc-numbering-field-list-template-code="2001" data-legacy-doc-numbering-field-list-simple="true" data-legacy-doc-numbering-field-list-level="0" data-legacy-doc-numbering-field-list-start-at="3" data-legacy-doc-numbering-field-list-number-format="decimal" data-legacy-doc-numbering-field-list-text-template="%1." data-legacy-doc-numbering-field-list-follow="space" data-legacy-doc-numbering-field-list-override-level="0" data-legacy-doc-numbering-field-list-override-start-at="7">2.1</span>',
        '<a href="https://example.test/audit#page" title="Nested page link">nested p. <span class="legacy-doc-field legacy-doc-field-page" data-legacy-doc-field="page" data-legacy-doc-field-instruction="PAGE \* Arabic" data-legacy-doc-field-format="Arabic">9</span></a>',
        '<span class="legacy-doc-field legacy-doc-source-field legacy-doc-field-filename" data-legacy-doc-field="filename" data-legacy-doc-field-instruction="FILENAME \p \* MERGEFORMAT" data-legacy-doc-source-field-type="document-filename" data-legacy-doc-source-field-policy="metadata-only-native-review" data-legacy-doc-field-format="MERGEFORMAT" data-legacy-doc-source-field-switches="p" data-legacy-doc-source-field-switch-p="true" data-legacy-doc-source-field-result-kind="file-path" data-legacy-doc-source-field-basename="legacy packet.doc" data-legacy-doc-source-field-full-path="true">C:\Sites\wp\legacy packet.doc</span>',
        '<span class="legacy-doc-field legacy-doc-source-field legacy-doc-field-template" data-legacy-doc-field="template" data-legacy-doc-field-instruction="TEMPLATE" data-legacy-doc-source-field-type="template-filename" data-legacy-doc-source-field-policy="metadata-only-native-review" data-legacy-doc-source-field-result-kind="filename" data-legacy-doc-source-field-basename="Migration.dotm">Migration.dotm</span>',
        '<span class="legacy-doc-field legacy-doc-source-field legacy-doc-field-filesize" data-legacy-doc-field="filesize" data-legacy-doc-field-instruction="FILESIZE \# &quot;#,##0 KB&quot;" data-legacy-doc-source-field-type="file-size" data-legacy-doc-source-field-policy="metadata-only-native-review" data-legacy-doc-source-field-result-kind="byte-size">12 KB</span>',
        '<span class="legacy-doc-field legacy-doc-include-field legacy-doc-field-import" data-legacy-doc-field="import" data-legacy-doc-field-instruction="IMPORT &quot;chart-alias.png&quot; \d" data-legacy-doc-include-field-type="picture" data-legacy-doc-include-source="chart-alias.png" data-legacy-doc-include-source-kind="file-path" data-legacy-doc-include-source-basename="chart-alias.png" data-legacy-doc-include-field-switches="d" data-legacy-doc-include-field-switch-d="true">alias chart</span>',
        '<span class="legacy-doc-field legacy-doc-include-field legacy-doc-field-include" data-legacy-doc-field="include" data-legacy-doc-field-instruction="INCLUDE &quot;https://e.test/alias.doc&quot; \c &quot;H2&quot; \!" data-legacy-doc-include-field-type="text" data-legacy-doc-include-source="https://e.test/alias.doc" data-legacy-doc-include-source-kind="external-url" data-legacy-doc-include-source-basename="alias.doc" data-legacy-doc-include-field-switches="c !" data-legacy-doc-include-field-switch-c="H2" data-legacy-doc-include-field-lock-result="true">alias clause</span>',
        '<span class="legacy-doc-field legacy-doc-literal-field legacy-doc-field-quote" data-legacy-doc-field="quote" data-legacy-doc-field-instruction="QUOTE &quot;Hidden instruction literal&quot; \* Upper" data-legacy-doc-literal-field-type="literal-text" data-legacy-doc-literal-field-policy="metadata-only-native-review" data-legacy-doc-field-format="Upper" data-legacy-doc-literal-field-arguments="Hidden instruction literal" data-legacy-doc-literal-field-result-kind="displayed-result" data-legacy-doc-literal-field-result-character-count="17">DISPLAYED LITERAL</span>',
        '<span class="legacy-doc-field legacy-doc-literal-field legacy-doc-field-shape" data-legacy-doc-field="shape" data-legacy-doc-field-instruction="SHAPE &quot;Hidden shape instruction&quot; \* MERGEFORMAT" data-legacy-doc-literal-field-type="shape-quote-alias" data-legacy-doc-literal-field-policy="metadata-only-native-review" data-legacy-doc-literal-field-alias="quote" data-legacy-doc-field-format="MERGEFORMAT" data-legacy-doc-literal-field-arguments="Hidden shape instruction" data-legacy-doc-literal-field-result-kind="displayed-result" data-legacy-doc-literal-field-result-character-count="17">shape placeholder</span>',
        '<span class="legacy-doc-object-ref" data-legacy-doc-object-ref="1" data-legacy-doc-object-reference-cp="' . (string) ($summary['embeddedObjectReferences'][0]['referenceCp'] ?? '') . '" data-legacy-doc-object-character-code="1" data-legacy-doc-object-can-expose-bytes="false" data-legacy-doc-object-storage="ObjectPool/_42" data-legacy-doc-object-id="_42" data-legacy-doc-object-label="legacy-data.xlsx" data-legacy-doc-object-native-data-bytes="' . strlen($embeddedNativeData) . '" data-legacy-doc-object-transmission-format="unicode-text" data-legacy-doc-object-has-native-data="true" data-legacy-doc-object-has-presentation-data="true">embedded object: legacy-data.xlsx</span>',
        '<span class="legacy-doc-picture-ref" data-legacy-doc-picture-ref="1" data-legacy-doc-picture-reference-cp="' . (string) ($summary['pictureReferences'][0]['referenceCp'] ?? '') . '" data-legacy-doc-picture-character-code="1" data-legacy-doc-picture-can-expose-bytes="false" data-legacy-doc-picture-source="chpx-data-stream" data-legacy-doc-picture-policy="metadata-only-native-review" data-legacy-doc-picture-data-stream-offset="' . (string) $pictureDataStreamOffset . '" data-legacy-doc-picture-data-stream-available-bytes="' . (string) (strlen($pictureDataStream) - $pictureDataStreamOffset) . '" data-legacy-doc-picture-source-sprms="sprmCFSpec sprmCPicLocation sprmCFData" data-legacy-doc-picture-data-stream-kind="binary-data">inline picture</span>',
    ] as $needle) {
        if (!str_contains($blocks, $needle)) {
            throw new RuntimeException('Legacy DOC handoff self-test missing: ' . $needle);
        }
    }
    foreach (['HYPERLINK', 'REF', 'PAGEREF', 'ASK', 'FILLIN', 'FORMTEXT', 'MERGEFIELD', 'DOCVARIABLE', 'SYMBOL', 'INCLUDEPICTURE', 'INCLUDETEXT', 'FILENAME', 'TEMPLATE', 'FILESIZE', 'IMPORT', 'INCLUDE', 'QUOTE', 'SHAPE', 'Hidden instruction literal', 'Hidden shape instruction', 'MACROBUTTON', 'GOTOBUTTON', 'AUTONUMLGL', 'ApproveImport', 'legacy_anchor', 'DATE', 'NOTEREF'] as $instruction) {
        if (str_contains(strip_tags($blocks), $instruction)) {
            throw new RuntimeException('Legacy DOC handoff self-test rendered hidden field instruction: ' . $instruction);
        }
    }
    if (str_contains($blocks, '2026-06-06')) {
        throw new RuntimeException('Legacy DOC handoff self-test rendered supplemental header DATE result');
    }
    if (str_contains($blocks, 'HYPERLINK')) {
        throw new RuntimeException('Legacy DOC handoff self-test rendered hidden field instructions');
    }
    if (str_contains($blocks, "\x01")) {
        throw new RuntimeException('Legacy DOC handoff self-test rendered embedded object placeholder control character');
    }
    foreach ([$footnoteSubdocumentText, $headerSubdocumentText, $commentSubdocumentText, $endnoteSubdocumentText, $textboxSubdocumentText, $headerTextboxSubdocumentText] as $supplementalSubdocumentText) {
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
    if (str_contains($blocks, 'review-lock')) {
        throw new RuntimeException('Legacy DOC handoff self-test rendered associated metadata string: review-lock');
    }
    $visibleBlocks = strip_tags($blocks);
    foreach (['legacy-mailmerge.csv', 'legacy-header.doc'] as $hiddenAssociatedString) {
        if (str_contains($visibleBlocks, $hiddenAssociatedString)) {
            throw new RuntimeException('Legacy DOC handoff self-test rendered associated metadata string: ' . $hiddenAssociatedString);
        }
    }
    if (($summary['fib']['extendedCharacters'] ?? null) !== true || ($summary['fib']['encrypted'] ?? null) !== false) {
        throw new RuntimeException('Legacy DOC handoff self-test missing FIB preflight flags');
    }

    $buildVersionFourCfb = static function () use ($u16, $u32, $makeDirectoryEntry, $padDirectoryEntries, $free, $end, $fatSector): string {
        $v4SectorSize = 4096;
        $wordDocumentBytes = str_repeat('V', $v4SectorSize);
        $directory = $makeDirectoryEntry('Root Entry', 5, $end, 0, $free, $free, 1)
            . $makeDirectoryEntry('WordDocument', 2, 2, strlen($wordDocumentBytes), $free, $free, $free);
        $fatBytes = $u32($fatSector) . $u32($end) . $u32($end) . str_repeat($u32($free), 1021);
        $header = "\xd0\xcf\x11\xe0\xa1\xb1\x1a\xe1"
            . str_repeat("\0", 16)
            . $u16(0x003e)
            . $u16(4)
            . $u16(0xfffe)
            . $u16(12)
            . $u16(6)
            . str_repeat("\0", 6)
            . $u32(1)
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

        return str_pad($header, $v4SectorSize, "\0")
            . substr($fatBytes, 0, $v4SectorSize)
            . $padDirectoryEntries($directory, $v4SectorSize)
            . $wordDocumentBytes;
    };
    $versionFourDocBytes = $buildVersionFourCfb();
    if (CompoundFileBinary::fromBytes($versionFourDocBytes)->readStream('WordDocument') !== str_repeat('V', 4096)) {
        throw new RuntimeException('Legacy DOC handoff self-test missing readable version 4 CFB fixture');
    }
    $versionFourRejected = false;
    try {
        CompoundFileBinary::fromBytes(substr_replace($versionFourDocBytes, $u32(0), 40, 4));
    } catch (RuntimeException) {
        $versionFourRejected = true;
    }
    if (!$versionFourRejected) {
        throw new RuntimeException('Legacy DOC handoff self-test accepted version 4 CFB directory-sector count mismatch');
    }
    $versionFourRejected = false;
    try {
        CompoundFileBinary::fromBytes(substr_replace($versionFourDocBytes, "\x01", 512, 1));
    } catch (RuntimeException) {
        $versionFourRejected = true;
    }
    if (!$versionFourRejected) {
        throw new RuntimeException('Legacy DOC handoff self-test accepted dirty version 4 CFB header padding');
    }
    $versionFourRejected = false;
    try {
        CompoundFileBinary::fromBytes($versionFourDocBytes . "\0");
    } catch (RuntimeException) {
        $versionFourRejected = true;
    }
    if (!$versionFourRejected) {
        throw new RuntimeException('Legacy DOC handoff self-test accepted version 4 CFB trailing partial sector');
    }

    $directoryFieldOffset = static function (int $directoryId, int $fieldOffset) use ($fat, $sectorSize, $end): int {
        $entryOffset = ($directoryId * 128) + $fieldOffset;
        $directorySectorIndex = intdiv($entryOffset, $sectorSize);
        $offsetInSector = $entryOffset % $sectorSize;
        $sector = 1;
        for ($index = 0; $index < $directorySectorIndex; $index++) {
            $sector = (int) ($fat[$sector] ?? $end);
            if ($sector === $end) {
                throw new RuntimeException('Legacy DOC handoff fixture directory chain is shorter than expected');
            }
        }

        return 512 + ($sector * $sectorSize) + $offsetInSector;
    };
    $wordDocumentDirectoryId = (int) $nodeByPath['WordDocument'];
    $objectPoolDirectoryId = (int) $nodeByPath['ObjectPool'];
    $wordDocumentLocation = $locations['WordDocument'];
    $wordDocumentStreamOffset = 512 + ((int) $wordDocumentLocation['startSector'] * $sectorSize);
    if ((int) $wordDocumentLocation['size'] < 4096) {
        $wordDocumentStreamOffset = 512
            + ($rootMiniStart * $sectorSize)
            + ((int) $wordDocumentLocation['startSector'] * $miniSectorSize);
    }
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
    $highDwordDocBytes = substr_replace($docBytes, $u32(0x00000001), $directoryFieldOffset($wordDocumentDirectoryId, 124), 4);
    $highDwordSummary = (new LegacyDocReader())->readBytes($highDwordDocBytes);
    if (($highDwordSummary['metadata']['cfbIgnoredStreamSizeHighDwordEntryCount'] ?? null) !== 1) {
        throw new RuntimeException('Legacy DOC handoff self-test missing v3 high stream-size DWORD provenance');
    }
    $highDwordStreamDirectory = [];
    foreach ($highDwordSummary['streamDirectory'] as $stream) {
        $highDwordStreamDirectory[(string) $stream['path']] = $stream;
    }
    if (($highDwordStreamDirectory['WordDocument']['ignoredStreamSizeHighDword'] ?? null) !== 0x00000001) {
        throw new RuntimeException('Legacy DOC handoff self-test did not preserve ignored v3 high stream-size DWORD metadata');
    }
    $orphanedActiveDirectoryEntry = substr_replace($docBytes, $u32($wordDocumentDirectoryId), $directoryFieldOffset(0, 76), 4);
    $orphanedActiveDirectoryEntry = substr_replace($orphanedActiveDirectoryEntry, $u32($free), $directoryFieldOffset($wordDocumentDirectoryId, 68), 4);
    $orphanedActiveDirectoryEntry = substr_replace($orphanedActiveDirectoryEntry, $u32($free), $directoryFieldOffset($wordDocumentDirectoryId, 72), 4);
    $orphanedActiveDirectoryEntry = substr_replace($orphanedActiveDirectoryEntry, "\x01", $directoryFieldOffset($wordDocumentDirectoryId, 67), 1);
    $emptyActiveDirectoryName = substr_replace($docBytes, str_repeat("\0", 64), $directoryFieldOffset($wordDocumentDirectoryId, 0), 64);
    $emptyActiveDirectoryName = substr_replace($emptyActiveDirectoryName, $u16(2), $directoryFieldOffset($wordDocumentDirectoryId, 64), 2);
    $embeddedNullDirectoryName = $utf16le("Word\0Document");
    $embeddedNullActiveDirectoryName = substr_replace(
        $docBytes,
        str_pad($embeddedNullDirectoryName . "\0\0", 64, "\0"),
        $directoryFieldOffset($wordDocumentDirectoryId, 0),
        64
    );
    $embeddedNullActiveDirectoryName = substr_replace(
        $embeddedNullActiveDirectoryName,
        $u16(strlen($embeddedNullDirectoryName) + 2),
        $directoryFieldOffset($wordDocumentDirectoryId, 64),
        2
    );
    $malformedUtf16DirectoryName = $utf16le('Wo') . "\0\xd8" . $utf16le('rdDocument');
    $malformedUtf16ActiveDirectoryName = substr_replace(
        $docBytes,
        str_pad($malformedUtf16DirectoryName . "\0\0", 64, "\0"),
        $directoryFieldOffset($wordDocumentDirectoryId, 0),
        64
    );
    $malformedUtf16ActiveDirectoryName = substr_replace(
        $malformedUtf16ActiveDirectoryName,
        $u16(strlen($malformedUtf16DirectoryName) + 2),
        $directoryFieldOffset($wordDocumentDirectoryId, 64),
        2
    );
    $smallRegularWordDocument = str_repeat("\0", 512) . "Small regular stream must stay guarded\r";
    $smallRegularWordDocument = substr_replace($smallRegularWordDocument, $u16(0xa5ec), 0, 2);
    $smallRegularWordDocument = substr_replace($smallRegularWordDocument, $u16(0x00c1), 2, 2);
    $smallRegularWordDocument = substr_replace($smallRegularWordDocument, $u32(512), 24, 4);
    $smallRegularWordDocument = substr_replace($smallRegularWordDocument, $u32(strlen($smallRegularWordDocument)), 28, 4);
    $smallRegularDirectory = $makeDirectoryEntry('Root Entry', 5, $end, 0, $free, $free, 1)
        . $makeDirectoryEntry('WordDocument', 2, 2, strlen($smallRegularWordDocument), $free, $free, $free);
    $smallRegularFat = $u32($fatSector) . $u32($end) . $u32($end) . str_repeat($u32($free), 125);
    $smallRegularHeader = "\xd0\xcf\x11\xe0\xa1\xb1\x1a\xe1"
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
        . $u32(0)
        . str_repeat($u32($free), 108);
    $smallRegularStreamWithoutMiniFat = str_pad($smallRegularHeader, 512, "\0")
        . $smallRegularFat
        . $padDirectoryEntries($smallRegularDirectory, $sectorSize)
        . $padTo($smallRegularWordDocument, $sectorSize);
    $regularOnlyWordDocument = str_repeat('R', 4096);
    $regularOnlyDirectory = $makeDirectoryEntry('Root Entry', 5, $end, 0, $free, $free, 1)
        . $makeDirectoryEntry('WordDocument', 2, 2, strlen($regularOnlyWordDocument), $free, $free, $free);
    $regularOnlyFatEntries = [
        $fatSector,
        $end,
        3,
        4,
        5,
        6,
        7,
        8,
        9,
        $end,
    ];
    $regularOnlyFat = implode('', array_map($u32, $regularOnlyFatEntries))
        . str_repeat($u32($free), 128 - count($regularOnlyFatEntries));
    $regularOnlyHeader = "\xd0\xcf\x11\xe0\xa1\xb1\x1a\xe1"
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
    $regularOnlyCfb = str_pad($regularOnlyHeader, 512, "\0")
        . $regularOnlyFat
        . $padDirectoryEntries($regularOnlyDirectory, $sectorSize)
        . $regularOnlyWordDocument;
    if (CompoundFileBinary::fromBytes($regularOnlyCfb)->readStream('WordDocument') !== $regularOnlyWordDocument) {
        throw new RuntimeException('Legacy DOC handoff self-test missing regular-only CFB preflight fixture');
    }
    $overlongRegularStreamChain = $regularOnlyCfb . str_repeat('R', $sectorSize);
    $overlongRegularStreamChain = substr_replace($overlongRegularStreamChain, $u32(10), 512 + (9 * 4), 4);
    $overlongRegularStreamChain = substr_replace($overlongRegularStreamChain, $u32($end), 512 + (10 * 4), 4);
    foreach ([
        'absent MiniFAT FREESECT start sentinel' => substr_replace($regularOnlyCfb, $u32($free), 60, 4),
        'absent MiniFAT FATSECT start sentinel' => substr_replace($regularOnlyCfb, $u32($fatSector), 60, 4),
        'absent DIFAT FREESECT start sentinel' => substr_replace($regularOnlyCfb, $u32($free), 68, 4),
        'absent DIFAT DIFSECT start sentinel' => substr_replace($regularOnlyCfb, $u32(0xfffffffc), 68, 4),
    ] as $label => $corruptCfb) {
        try {
            CompoundFileBinary::fromBytes($corruptCfb);
        } catch (InvalidArgumentException | RuntimeException) {
            continue;
        }

        throw new RuntimeException('Legacy DOC handoff self-test accepted CFB absent-chain header start: ' . $label);
    }
    $regularWordDocumentForRootMiniStream = str_pad($smallRegularWordDocument, 4096, "\0");
    $rootMiniStreamWithoutMiniFatSector = 10;
    $rootMiniStreamWithoutMiniFatDirectory = $makeDirectoryEntry('Root Entry', 5, $rootMiniStreamWithoutMiniFatSector, 64, $free, $free, 1)
        . $makeDirectoryEntry('WordDocument', 2, 2, strlen($regularWordDocumentForRootMiniStream), $free, $free, $free);
    $rootMiniStreamWithoutMiniFatFatEntries = [
        $fatSector,
        $end,
        3,
        4,
        5,
        6,
        7,
        8,
        9,
        $end,
        $end,
    ];
    $rootMiniStreamWithoutMiniFatFat = implode('', array_map($u32, $rootMiniStreamWithoutMiniFatFatEntries))
        . str_repeat($u32($free), 128 - count($rootMiniStreamWithoutMiniFatFatEntries));
    $rootMiniStreamWithoutMiniFat = str_pad($smallRegularHeader, 512, "\0")
        . $rootMiniStreamWithoutMiniFatFat
        . $padDirectoryEntries($rootMiniStreamWithoutMiniFatDirectory, $sectorSize)
        . $regularWordDocumentForRootMiniStream
        . str_repeat('M', $sectorSize);
    $unusedPhysicalSectorId = intdiv(strlen($docBytes) - $sectorSize, $sectorSize);
    $docBytesWithUnusedPhysicalSector = $docBytes . str_repeat("\0", $sectorSize);
    $unownedFatMarkerEntryOffset = 512 + ($unusedPhysicalSectorId * 4);
    $rootMiniStreamSize = unpack('Vvalue', substr($docBytes, $directoryFieldOffset(0, 120), 4))['value'];
    $extraMiniSector = intdiv((int) $rootMiniStreamSize + $miniSectorSize - 1, $miniSectorSize);
    $wordMiniSectorCount = intdiv((int) $wordDocumentLocation['size'] + $miniSectorSize - 1, $miniSectorSize);
    $lastWordMiniSector = (int) $wordDocumentLocation['startSector'] + $wordMiniSectorCount - 1;
    $firstMiniFatSector = unpack('Vvalue', substr($docBytes, 60, 4))['value'];
    $overlongMiniStreamChain = substr_replace($docBytes, $u64((int) $rootMiniStreamSize + $miniSectorSize), $directoryFieldOffset(0, 120), 8);
    $overlongMiniStreamChain = substr_replace(
        $overlongMiniStreamChain,
        $u32($extraMiniSector),
        512 + ((int) $firstMiniFatSector * $sectorSize) + ($lastWordMiniSector * 4),
        4
    );
    $overlongMiniStreamChain = substr_replace(
        $overlongMiniStreamChain,
        $u32($end),
        512 + ((int) $firstMiniFatSector * $sectorSize) + ($extraMiniSector * 4),
        4
    );
    $unusedDirectoryEntryId = count($nodes);
    if (($unusedDirectoryEntryId * 128) >= (count($directoryChunks) * $sectorSize)) {
        throw new RuntimeException('Legacy DOC handoff fixture did not preserve an unused CFB directory entry');
    }
    $dirtyUnallocatedDirectoryEntry = substr_replace($docBytes, "X\0", $directoryFieldOffset($unusedDirectoryEntryId, 0), 2);
    $zeroLengthWordDocumentStartSentinel = static function (int $startSector) use ($docBytes, $u32, $u64, $directoryFieldOffset, $wordDocumentDirectoryId): string {
        $bytes = substr_replace($docBytes, $u64(0), $directoryFieldOffset($wordDocumentDirectoryId, 120), 8);

        return substr_replace($bytes, $u32($startSector), $directoryFieldOffset($wordDocumentDirectoryId, 116), 4);
    };
    foreach ([
        'unsupported CFB major version' => substr_replace($docBytes, $u16(5), 26, 2),
        'version 3 CFB directory-sector count' => substr_replace($docBytes, $u32(1), 40, 4),
        'non-null CFB header CLSID' => substr_replace($docBytes, "\x01", 8, 1),
        'nonzero CFB header reserved bytes' => substr_replace($docBytes, "\x01\0\0\0\0\0", 34, 6),
        'nonzero CFB transaction signature' => substr_replace($docBytes, $u32(0x12345678), 52, 4),
        'trailing CFB partial sector' => $docBytes . "\0",
        'invalid CFB mini stream cutoff' => substr_replace($docBytes, $u32(2048), 56, 4),
        'CFB MiniFAT start sector without MiniFAT count' => substr_replace($docBytes, $u32(0), 64, 4),
        'CFB MiniFAT count without valid start sector' => substr_replace($docBytes, $u32($end), 60, 4),
        'CFB DIFAT start sector without DIFAT count' => substr_replace($docBytes, $u32(0), 72, 4),
        'unterminated CFB DIFAT overflow chain' => substr_replace($docBytes, $u32(0), 512 + ($difatSector * $sectorSize) + ($sectorSize - 4), 4),
        'surplus CFB DIFAT FAT-sector listing' => substr_replace($docBytes, $u32(1), 512 + ($difatSector * $sectorSize) + 4, 4),
        'CFB FAT entry beyond physical file' => substr_replace($docBytes, $u32($end), 512 + (127 * 4), 4),
        'reserved CFB FAT marker on physical sector' => substr_replace($docBytesWithUnusedPhysicalSector, $u32(0xfffffffb), $unownedFatMarkerEntryOffset, 4),
        'out-of-range CFB FAT pointer on physical sector' => substr_replace($docBytesWithUnusedPhysicalSector, $u32($unusedPhysicalSectorId + 16), $unownedFatMarkerEntryOffset, 4),
        'unowned CFB FATSECT marker on physical sector' => substr_replace($docBytesWithUnusedPhysicalSector, $u32(0xfffffffd), $unownedFatMarkerEntryOffset, 4),
        'unowned CFB DIFSECT marker on physical sector' => substr_replace($docBytesWithUnusedPhysicalSector, $u32(0xfffffffc), $unownedFatMarkerEntryOffset, 4),
        'unreferenced CFB allocated sector' => substr_replace($docBytesWithUnusedPhysicalSector, $u32($end), $unownedFatMarkerEntryOffset, 4),
        'overlong CFB regular stream chain' => $overlongRegularStreamChain,
        'overlong CFB mini stream chain' => $overlongMiniStreamChain,
        'CFB root sibling directory reference' => substr_replace($docBytes, $u32($wordDocumentDirectoryId), $directoryFieldOffset(0, 68), 4),
        'CFB stream child directory reference' => substr_replace($docBytes, $u32($objectPoolDirectoryId), $directoryFieldOffset($wordDocumentDirectoryId, 76), 4),
        'CFB stream storage CLSID metadata' => substr_replace($docBytes, "\x01", $directoryFieldOffset($wordDocumentDirectoryId, 80), 1),
        'CFB stream storage state bits' => substr_replace($docBytes, $u32(0x00000010), $directoryFieldOffset($wordDocumentDirectoryId, 96), 4),
        'CFB stream storage creation time' => substr_replace($docBytes, $filetime('2024-01-01T00:00:00Z'), $directoryFieldOffset($wordDocumentDirectoryId, 100), 8),
        'CFB stream storage modification time' => substr_replace($docBytes, $filetime('2024-01-02T00:00:00Z'), $directoryFieldOffset($wordDocumentDirectoryId, 108), 8),
        'duplicate CFB root storage object' => substr_replace($docBytes, "\x05", $directoryFieldOffset($objectPoolDirectoryId, 66), 1),
        'CFB storage stream-data bytes' => substr_replace($docBytes, $u64(64), $directoryFieldOffset($objectPoolDirectoryId, 120), 8),
        'CFB storage start-sector reference' => substr_replace($docBytes, $u32($rootMiniStart), $directoryFieldOffset($objectPoolDirectoryId, 116), 4),
        'CFB storage FREESECT start sentinel' => substr_replace($docBytes, $u32($free), $directoryFieldOffset($objectPoolDirectoryId, 116), 4),
        'CFB storage FATSECT start sentinel' => substr_replace($docBytes, $u32($fatSector), $directoryFieldOffset($objectPoolDirectoryId, 116), 4),
        'CFB storage DIFSECT start sentinel' => substr_replace($docBytes, $u32(0xfffffffc), $directoryFieldOffset($objectPoolDirectoryId, 116), 4),
        'CFB zero-length stream start-sector reference' => substr_replace($docBytes, $u64(0), $directoryFieldOffset($wordDocumentDirectoryId, 120), 8),
        'CFB zero-length stream FREESECT start sentinel' => $zeroLengthWordDocumentStartSentinel($free),
        'CFB zero-length stream FATSECT start sentinel' => $zeroLengthWordDocumentStartSentinel($fatSector),
        'CFB zero-length stream DIFSECT start sentinel' => $zeroLengthWordDocumentStartSentinel(0xfffffffc),
        'CFB empty root FREESECT start sentinel' => substr_replace($regularOnlyCfb, $u32($free), $directoryFieldOffset(0, 116), 4),
        'CFB empty root FATSECT start sentinel' => substr_replace($regularOnlyCfb, $u32($fatSector), $directoryFieldOffset(0, 116), 4),
        'CFB empty root DIFSECT start sentinel' => substr_replace($regularOnlyCfb, $u32(0xfffffffc), $directoryFieldOffset(0, 116), 4),
        'red CFB root storage entry' => substr_replace($docBytes, "\0", $directoryFieldOffset(0, 67), 1),
        'red CFB sibling-tree root' => substr_replace($docBytes, "\0", $directoryFieldOffset((int) ($childIds[0] ?? $wordDocumentDirectoryId), 67), 1),
        'unequal CFB sibling-tree black height' => substr_replace($docBytes, "\x01", $directoryFieldOffset($redDirectoryId, 67), 1),
        'duplicate CFB FAT sector' => substr_replace(substr_replace($docBytes, $u32(2), 44, 4), $u32(0), 80, 4),
        'misclassified CFB FAT sector' => substr_replace($docBytes, $u32($end), 512, 4),
        'CFB root mini stream reuses directory sector' => substr_replace($docBytes, $u32(1), $directoryFieldOffset(0, 116), 4),
        'CFB orphaned active directory entry' => $orphanedActiveDirectoryEntry,
        'CFB active directory name missing UTF-16 terminator' => substr_replace($docBytes, "X\0", $directoryFieldOffset($wordDocumentDirectoryId, 24), 2),
        'CFB active directory name must not be empty' => $emptyActiveDirectoryName,
        'CFB active directory name contains embedded null' => $embeddedNullActiveDirectoryName,
        'CFB active directory name invalid UTF-16LE' => $malformedUtf16ActiveDirectoryName,
        'dirty CFB unallocated directory entry' => $dirtyUnallocatedDirectoryEntry,
        'CFB root mini stream without MiniFAT metadata' => $rootMiniStreamWithoutMiniFat,
        'small CFB stream without MiniFAT metadata' => $smallRegularStreamWithoutMiniFat,
        'invalid CFB root storage name' => substr_replace($docBytes, "X\0", $directoryFieldOffset(0, 0), 2),
        'complex DOC missing CLX piece table' => substr_replace($docBytes, $u32(0), $wordDocumentStreamOffset + 0x01a6, 4),
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
