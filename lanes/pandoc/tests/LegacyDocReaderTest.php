<?php

declare(strict_types=1);

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\CompoundFileBinary;
use PortLibs\Pandoc\LegacyDocReader;
use PortLibs\Pandoc\MarkdownWriter;
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
        throw new RuntimeException('CFB test directory CLSID fixture is invalid');
    }

    $tail = hex2bin($matches[4] . $matches[5]);
    if (!is_string($tail) || strlen($tail) !== 8) {
        throw new RuntimeException('Unable to encode CFB test directory CLSID fixture');
    }

    return $u32((int) hexdec($matches[1]))
        . $u16((int) hexdec($matches[2]))
        . $u16((int) hexdec($matches[3]))
        . $tail;
};
$utf16le = static function (string $text): string {
    $encoded = iconv('UTF-8', 'UTF-16LE', $text);
    if (!is_string($encoded)) {
        throw new RuntimeException('Unable to encode UTF-16LE test fixture text');
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
    if (strlen($nameBytes) > 64) {
        throw new RuntimeException('CFB test directory name is too long');
    }

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

$buildCfb = static function (array $streams, bool $useMiniStreams = true, array $directoryMetadata = [], array $options = []) use ($u16, $u32, $directoryEntry, $padTo, $compareCfbDirectoryNames): string {
    $majorVersion = (int) ($options['majorVersion'] ?? 3);
    $sectorSize = $majorVersion === 4 ? 4096 : 512;
    $sectorShift = $majorVersion === 4 ? 12 : 9;
    $miniSectorSize = 64;
    $free = 0xffffffff;
    $end = 0xfffffffe;
    $fatSector = 0xfffffffd;
    $streamMetadata = [];
    $streamData = [];
    foreach ($streams as $name => $stream) {
        if (is_array($stream) && array_key_exists('data', $stream)) {
            $streamData[(string) $name] = (string) $stream['data'];
            $streamMetadata[(string) $name] = [
                'createdAt' => isset($stream['createdAt']) ? (string) $stream['createdAt'] : null,
                'modifiedAt' => isset($stream['modifiedAt']) ? (string) $stream['modifiedAt'] : null,
            ];
            continue;
        }

        $streamData[(string) $name] = (string) $stream;
        $streamMetadata[(string) $name] = [
            'createdAt' => null,
            'modifiedAt' => null,
        ];
    }
    foreach ($directoryMetadata as $path => $metadata) {
        $directoryMetadata[(string) $path] = [
            'createdAt' => isset($metadata['createdAt']) ? (string) $metadata['createdAt'] : null,
            'modifiedAt' => isset($metadata['modifiedAt']) ? (string) $metadata['modifiedAt'] : null,
            'clsid' => isset($metadata['clsid']) ? (string) $metadata['clsid'] : null,
            'stateBits' => isset($metadata['stateBits']) ? (int) $metadata['stateBits'] : 0,
        ];
    }

    $miniStreams = [];
    $regularStreams = [];
    foreach ($streamData as $name => $data) {
        if ($useMiniStreams && strlen($data) < 4096) {
            $miniStreams[$name] = $data;
        } else {
            $regularStreams[$name] = $data;
        }
    }

    $miniFat = [];
    $miniStreamBytes = '';
    $streamLocations = [];
    foreach ($miniStreams as $name => $data) {
        $firstMiniSector = intdiv(strlen($miniStreamBytes), $miniSectorSize);
        $sectorCount = max(1, intdiv(strlen($data) + $miniSectorSize - 1, $miniSectorSize));
        for ($index = 0; $index < $sectorCount; $index++) {
            $miniFat[$firstMiniSector + $index] = $index === $sectorCount - 1 ? $end : $firstMiniSector + $index + 1;
        }
        $miniStreamBytes .= $padTo($data, $miniSectorSize);
        $streamLocations[$name] = [
            'startSector' => $firstMiniSector,
            'size' => strlen($data),
        ];
    }
    $miniStreamSize = strlen($miniStreamBytes);
    $miniStreamBytes = $miniStreamSize === 0 ? '' : $padTo($miniStreamBytes, $sectorSize);

    $sectors = [];
    $fat = [];
    $allocateSector = static function (string $bytes) use (&$sectors, &$fat, $padTo, $sectorSize, $end): int {
        $sector = count($sectors);
        $sectors[] = $padTo($bytes, $sectorSize);
        $fat[$sector] = $end;

        return $sector;
    };

    $sectors[] = str_repeat("\0", $sectorSize);
    $fat[] = $fatSector;
    $directorySector = $allocateSector('');
    $miniFatSector = $miniFat === [] ? $end : $allocateSector('');
    $rootMiniStart = $miniStreamSize === 0 ? $end : count($sectors);
    if ($miniStreamSize > 0) {
        $chunks = str_split($miniStreamBytes, $sectorSize);
        foreach ($chunks as $index => $chunk) {
            $sector = $allocateSector($chunk);
            $fat[$sector] = $index === count($chunks) - 1 ? $end : $sector + 1;
        }
    }

    foreach ($regularStreams as $name => $data) {
        $startSector = count($sectors);
        $chunks = str_split($padTo($data, $sectorSize), $sectorSize);
        foreach ($chunks as $index => $chunk) {
            $sector = $allocateSector($chunk);
            $fat[$sector] = $index === count($chunks) - 1 ? $end : $sector + 1;
        }
        $streamLocations[$name] = [
            'startSector' => $startSector,
            'size' => strlen($data),
        ];
    }

    $nodes = [
        [
            'name' => 'Root Entry',
            'type' => 5,
            'startSector' => $rootMiniStart,
            'size' => $miniStreamSize,
            'children' => [],
        ],
    ];
    $nodeByPath = ['' => 0];
    foreach ($streamData as $name => $_data) {
        $path = trim(str_replace('\\', '/', (string) $name), '/');
        $segments = array_values(array_filter(explode('/', $path), static fn (string $segment): bool => $segment !== ''));
        if ($segments === []) {
            throw new RuntimeException('CFB test stream path is empty');
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
                    'startSector' => $end,
                    'size' => 0,
                    'children' => [],
                ];
                $nodes[$parentIndex]['children'][] = $nodeByPath[$storagePath];
            }
            $parentIndex = $nodeByPath[$storagePath];
            $parentPath = $storagePath;
        }

        $leafName = $segments[count($segments) - 1];
        $streamPath = $parentPath === '' ? $leafName : $parentPath . '/' . $leafName;
        $location = $streamLocations[(string) $name];
        $nodes[] = [
            'name' => $leafName,
            'type' => 2,
            'startSector' => $location['startSector'],
            'size' => $location['size'],
            'children' => [],
        ];
        $nodeByPath[$streamPath] = count($nodes) - 1;
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
        $children = $node['children'];
        if ($children !== []) {
            $childIds[$nodeIndex] = $buildSiblingTree($children);
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

    $directory = '';
    foreach ($nodes as $nodeIndex => $node) {
        $entryPath = array_search($nodeIndex, $nodeByPath, true);
        $entryPath = is_string($entryPath) ? $entryPath : '';
        $metadata = $directoryMetadata[$entryPath]
            ?? $streamMetadata[$entryPath]
            ?? ['createdAt' => null, 'modifiedAt' => null];
        $directory .= $directoryEntry(
            (string) $node['name'],
            (int) $node['type'],
            (int) $node['startSector'],
            (int) $node['size'],
            $leftSiblings[$nodeIndex] ?? $free,
            $rightSiblings[$nodeIndex] ?? $free,
            $childIds[$nodeIndex] ?? $free,
            $metadata['createdAt'],
            $metadata['modifiedAt'],
            $metadata['clsid'] ?? null,
            (int) ($metadata['stateBits'] ?? 0),
            $nodeColors[$nodeIndex] ?? 1
        );
    }
    $directoryChunks = str_split($padTo($directory, $sectorSize), $sectorSize);
    $previousDirectorySector = $directorySector;
    foreach ($directoryChunks as $index => $chunk) {
        if ($index === 0) {
            $sectors[$directorySector] = $chunk;
            continue;
        }

        $sector = $allocateSector($chunk);
        $fat[$previousDirectorySector] = $sector;
        $previousDirectorySector = $sector;
    }
    $fat[$previousDirectorySector] = $end;

    if ($miniFat !== []) {
        $miniFatBytes = '';
        for ($index = 0, $count = count($miniFat); $index < $count; $index++) {
            $miniFatBytes .= $u32($miniFat[$index] ?? $free);
        }
        $sectors[$miniFatSector] = $padTo($miniFatBytes, $sectorSize);
    }

    $fatBytes = '';
    $fatEntries = max(intdiv($sectorSize, 4), count($sectors));
    for ($index = 0; $index < $fatEntries; $index++) {
        $fatBytes .= $u32($fat[$index] ?? $free);
    }
    $sectors[0] = substr($fatBytes, 0, $sectorSize);
    $directorySectorCount = $majorVersion === 4 ? count($directoryChunks) : 0;

    $header = "\xd0\xcf\x11\xe0\xa1\xb1\x1a\xe1"
        . str_repeat("\0", 16)
        . $u16(0x003e)
        . $u16($majorVersion)
        . $u16(0xfffe)
        . $u16($sectorShift)
        . $u16(6)
        . str_repeat("\0", 6)
        . $u32($directorySectorCount)
        . $u32(1)
        . $u32($directorySector)
        . $u32(0)
        . $u32(4096)
        . $u32($miniFatSector)
        . $u32($miniFat === [] ? 0 : 1)
        . $u32($end)
        . $u32(0)
        . $u32(0)
        . str_repeat($u32($free), 108);

    return str_pad($header, $sectorSize, "\0") . implode('', $sectors);
};

$moveFatListingToDifatSector = static function (string $bytes) use ($u32): array {
    $sectorSize = 512;
    $free = 0xffffffff;
    $end = 0xfffffffe;
    $difatSector = intdiv(strlen($bytes) - 512, $sectorSize);
    if ($difatSector < 0 || $difatSector >= 128) {
        throw new RuntimeException('CFB DIFAT overflow test fixture requires the first FAT sector to cover the added DIFAT sector');
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

$typedLpstr = static function (string $value): string {
    $bytes = $value . "\0";
    $raw = pack('v', 0x001e) . "\0\0" . pack('V', strlen($bytes)) . $bytes;

    return str_pad($raw, (int) (ceil(strlen($raw) / 4) * 4), "\0");
};
$typedLpstrBytes = static function (string $bytes): string {
    $bytes .= "\0";
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
$typedUi2 = static fn (int $value): string => pack('v', 0x0012) . "\0\0" . pack('v', $value) . "\0\0";
$typedUi4 = static fn (int $value): string => pack('v', 0x0013) . "\0\0" . pack('V', $value);
$typedI8Parts = static fn (int $low, int $high): string => pack('v', 0x0014) . "\0\0" . pack('V2', $low, $high);
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
$objectInfo = static fn (int $clipboardFormat): string => "\0\0" . pack('v', $clipboardFormat);
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
    $ansiClipboard = static fn (string $value): string => $u32(strlen($value) + 1) . $value . "\0";
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
        . $ansiClipboard($ansiClipboardFormat)
        . $ansiString('')
        . $u32(0x71b239f4)
        . $unicodeString($unicodeUserType)
        . $unicodeClipboard($unicodeClipboardFormat)
        . $unicodeString('');
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
        $ichRelative = (int) ($reference['ichRelative'] ?? 0xff);
        $fnfb = (int) ($reference['fnfb'] ?? 0);
        $bytes .= $u16(intdiv(strlen($encoded), 2))
            . $encoded
            . $u16($fnpi)
            . chr($ichRelative & 0xff)
            . chr($fnfb & 0xff)
            . str_repeat("\0", 4);
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
$plcfldMom = static function (array $records, int $finalCp) use ($u32): string {
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

$buildSimpleWordDocument = static function (string $text, int $flags = 0, string $encoding = 'Windows-1252//TRANSLIT'): string {
    $textBytes = iconv('UTF-8', $encoding, $text);
    if (!is_string($textBytes)) {
        throw new RuntimeException('Unable to encode simple WordDocument test text');
    }

    $fib = str_repeat("\0", 512);
    $fib = substr_replace($fib, pack('v', 0xa5ec), 0, 2);
    $fib = substr_replace($fib, pack('v', 0x00c1), 2, 2);
    $fib = substr_replace($fib, pack('v', $flags), 10, 2);
    $fib = substr_replace($fib, pack('V', 512), 24, 4);
    $fib = substr_replace($fib, pack('V', 512 + strlen($textBytes)), 28, 4);

    return $fib . $textBytes;
};
$buildExtendedFibWordDocument = static function (string $text, int $flags = 0, string $encoding = 'Windows-1252//TRANSLIT') use ($buildSimpleWordDocument, $u32): string {
    $wordDocument = $buildSimpleWordDocument($text, $flags, $encoding);
    $textBytes = substr($wordDocument, 512);
    $fibSize = 768;
    $fib = str_pad(substr($wordDocument, 0, 512), $fibSize, "\0");
    $fib = substr_replace($fib, $u32($fibSize), 24, 4);
    $fib = substr_replace($fib, $u32($fibSize + strlen($textBytes)), 28, 4);

    return $fib . $textBytes;
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

$buildStyleSheetDocStreams = static function (array $styleRecords) use ($buildSimpleWordDocument, $styleSheet, $u32): array {
    $wordDocument = $buildSimpleWordDocument("Styled legacy packet\r");
    $stsh = $styleSheet($styleRecords);
    $wordDocument = substr_replace($wordDocument, $u32(0), 0x00a2, 4);
    $wordDocument = substr_replace($wordDocument, $u32(strlen($stsh)), 0x00a6, 4);

    return [
        'WordDocument' => $wordDocument,
        '0Table' => $stsh,
    ];
};

$buildPieceTableDocStreams = static function (
    int $firstPieceFlags = 0,
    int $secondPieceFlags = 0
) use ($utf16le, $u16, $u32): array {
    $compressedText = "Legacy \x93smart\x94 ";
    $unicodeText = "Unicode Ω import\r";
    $unicodeBytes = $utf16le($unicodeText);
    $compressedStart = 1024;
    $unicodeStart = $compressedStart + strlen($compressedText);

    $wordDocument = str_repeat("\0", $compressedStart)
        . $compressedText
        . $unicodeBytes;
    $wordDocument = substr_replace($wordDocument, pack('v', 0xa5ec), 0, 2);
    $wordDocument = substr_replace($wordDocument, pack('v', 0x00c1), 2, 2);
    $wordDocument = substr_replace($wordDocument, pack('v', 0x0204), 10, 2);
    $wordDocument = substr_replace($wordDocument, pack('V', 0), 24, 4);
    $wordDocument = substr_replace($wordDocument, pack('V', strlen($wordDocument)), 28, 4);

    $firstCharacters = strlen($compressedText);
    $secondCharacters = 17;
    $plc = $u32(0)
        . $u32($firstCharacters)
        . $u32($firstCharacters + $secondCharacters)
        . $u16($firstPieceFlags) . $u32(($compressedStart * 2) | 0x40000000) . "\0\0"
        . $u16($secondPieceFlags) . $u32($unicodeStart) . "\0\0";
    $clx = "\x02" . $u32(strlen($plc)) . $plc;
    $wordDocument = substr_replace($wordDocument, $u32(0), 0x01a2, 4);
    $wordDocument = substr_replace($wordDocument, $u32(strlen($clx)), 0x01a6, 4);

    return [
        'WordDocument' => $wordDocument,
        '1Table' => $clx,
    ];
};

$buildSubdocumentPieceTableDocStreams = static function () use ($u16, $u32): array {
    $mainText = 'Main review body';
    $separator = "\r";
    $footnoteText = 'Footnote body must stay metadata-only';
    $headerText = 'Header packet should not render';
    $commentText = 'Comment body stays annotation-only';

    $pieces = [
        $mainText,
        $separator,
        $footnoteText,
        $headerText,
        $commentText,
    ];
    $cpOffsets = [0];
    foreach ($pieces as $piece) {
        $cpOffsets[] = end($cpOffsets) + strlen($piece);
    }

    $pieceStart = 1024;
    $wordDocument = str_repeat("\0", $pieceStart);
    $pcds = '';
    foreach ($pieces as $piece) {
        $fc = strlen($wordDocument);
        $wordDocument .= $piece;
        $pcds .= $u16(0) . $u32(($fc * 2) | 0x40000000) . "\0\0";
    }

    $plc = '';
    foreach ($cpOffsets as $cp) {
        $plc .= $u32($cp);
    }
    $plc .= $pcds;
    $clx = "\x02" . $u32(strlen($plc)) . $plc;

    $wordDocument = substr_replace($wordDocument, $u16(0xa5ec), 0, 2);
    $wordDocument = substr_replace($wordDocument, $u16(0x00c1), 2, 2);
    $wordDocument = substr_replace($wordDocument, $u16(0x0204), 10, 2);
    $wordDocument = substr_replace($wordDocument, $u32(0), 24, 4);
    $wordDocument = substr_replace($wordDocument, $u32(strlen($wordDocument)), 28, 4);
    $wordDocument = substr_replace($wordDocument, $u32(strlen($wordDocument)), 0x0040, 4);
    $wordDocument = substr_replace($wordDocument, $u32(strlen($mainText)), 0x004c, 4);
    $wordDocument = substr_replace($wordDocument, $u32(strlen($footnoteText)), 0x0050, 4);
    $wordDocument = substr_replace($wordDocument, $u32(strlen($headerText)), 0x0054, 4);
    $wordDocument = substr_replace($wordDocument, $u32(strlen($commentText)), 0x005c, 4);
    $wordDocument = substr_replace($wordDocument, $u32(0), 0x01a2, 4);
    $wordDocument = substr_replace($wordDocument, $u32(strlen($clx)), 0x01a6, 4);

    return [
        'streams' => [
            'WordDocument' => $wordDocument,
            '1Table' => $clx,
        ],
        'mainText' => $mainText,
        'footnoteText' => $footnoteText,
        'headerText' => $headerText,
        'commentText' => $commentText,
        'expectedLastCp' => end($cpOffsets),
    ];
};

$buildSubdocumentReferenceBodyDocStreams = static function () use ($utf16le, $u16, $u32): array {
    $mainText = "Main \x02 footnote, # endnote, \x05 comment\r";
    $separator = "\r";
    $footnoteText = "Footnote body retained for reviewer metadata.\r";
    $headerText = "Header text stays metadata-only.\r";
    $commentText = "Comment body retained for reviewer metadata.\r";
    $endnoteText = "Endnote body retained for reviewer metadata.\r";

    $pieces = [
        $mainText,
        $separator,
        $footnoteText,
        $headerText,
        $commentText,
        $endnoteText,
    ];
    $cpOffsets = [0];
    foreach ($pieces as $piece) {
        $cpOffsets[] = end($cpOffsets) + strlen($piece);
    }

    $pieceStart = 1536;
    $wordDocument = str_repeat("\0", $pieceStart);
    $pcds = '';
    foreach ($pieces as $piece) {
        $pieceBytes = $utf16le($piece);
        $fc = strlen($wordDocument);
        $wordDocument .= $pieceBytes;
        $pcds .= $u16(0) . $u32($fc) . "\0\0";
    }

    $plc = '';
    foreach ($cpOffsets as $cp) {
        $plc .= $u32($cp);
    }
    $plc .= $pcds;
    $clx = "\x02" . $u32(strlen($plc)) . $plc;

    $footnoteReferenceCp = strpos($mainText, "\x02");
    $endnoteReferenceCp = strpos($mainText, '#');
    $commentReferenceCp = strpos($mainText, "\x05");
    if ($footnoteReferenceCp === false || $endnoteReferenceCp === false || $commentReferenceCp === false) {
        throw new RuntimeException('Unable to locate note/comment reference markers in legacy DOC fixture');
    }

    $mainTextEndCp = strlen($mainText);
    $plcffndRef = $u32($footnoteReferenceCp)
        . $u32($mainTextEndCp)
        . $u16(1);
    $plcffndTxt = $u32(0)
        . $u32(strlen($footnoteText))
        . $u32(strlen($footnoteText) + 1);
    $plcfendRef = $u32($endnoteReferenceCp)
        . $u32($mainTextEndCp)
        . $u16(0);
    $plcfendTxt = $u32(0)
        . $u32(strlen($endnoteText))
        . $u32(strlen($endnoteText) + 1);

    $commentInitialsBytes = $utf16le('CM');
    $commentDescriptor = $u16(2)
        . $commentInitialsBytes
        . str_repeat("\0", 18 - strlen($commentInitialsBytes))
        . $u16(4)
        . $u16(0)
        . $u16(0)
        . $u32(0x3344);
    $plcfandRef = $u32($commentReferenceCp)
        . $u32($mainTextEndCp)
        . $commentDescriptor;
    $plcfandTxt = $u32(0)
        . $u32(strlen($commentText))
        . $u32(strlen($commentText) + 1);

    $fcPlcffndRef = strlen($clx);
    $fcPlcffndTxt = $fcPlcffndRef + strlen($plcffndRef);
    $fcPlcfendRef = $fcPlcffndTxt + strlen($plcffndTxt);
    $fcPlcfendTxt = $fcPlcfendRef + strlen($plcfendRef);
    $fcPlcfandRef = $fcPlcfendTxt + strlen($plcfendTxt);
    $fcPlcfandTxt = $fcPlcfandRef + strlen($plcfandRef);
    $tableStream = $clx . $plcffndRef . $plcffndTxt . $plcfendRef . $plcfendTxt . $plcfandRef . $plcfandTxt;

    $wordDocument = substr_replace($wordDocument, $u16(0xa5ec), 0, 2);
    $wordDocument = substr_replace($wordDocument, $u16(0x00c1), 2, 2);
    $wordDocument = substr_replace($wordDocument, $u16(0x0204), 10, 2);
    $wordDocument = substr_replace($wordDocument, $u32(0), 24, 4);
    $wordDocument = substr_replace($wordDocument, $u32(strlen($wordDocument)), 28, 4);
    $wordDocument = substr_replace($wordDocument, $u32(strlen($wordDocument)), 0x0040, 4);
    $wordDocument = substr_replace($wordDocument, $u32(strlen($mainText)), 0x004c, 4);
    $wordDocument = substr_replace($wordDocument, $u32(strlen($footnoteText)), 0x0050, 4);
    $wordDocument = substr_replace($wordDocument, $u32(strlen($headerText)), 0x0054, 4);
    $wordDocument = substr_replace($wordDocument, $u32(strlen($commentText)), 0x005c, 4);
    $wordDocument = substr_replace($wordDocument, $u32(strlen($endnoteText)), 0x0060, 4);
    $wordDocument = substr_replace($wordDocument, $u32(0), 0x01a2, 4);
    $wordDocument = substr_replace($wordDocument, $u32(strlen($clx)), 0x01a6, 4);
    $wordDocument = substr_replace($wordDocument, $u32($fcPlcffndRef), 0x00aa, 4);
    $wordDocument = substr_replace($wordDocument, $u32(strlen($plcffndRef)), 0x00ae, 4);
    $wordDocument = substr_replace($wordDocument, $u32($fcPlcffndTxt), 0x00b2, 4);
    $wordDocument = substr_replace($wordDocument, $u32(strlen($plcffndTxt)), 0x00b6, 4);
    $wordDocument = substr_replace($wordDocument, $u32($fcPlcfendRef), 0x020a, 4);
    $wordDocument = substr_replace($wordDocument, $u32(strlen($plcfendRef)), 0x020e, 4);
    $wordDocument = substr_replace($wordDocument, $u32($fcPlcfendTxt), 0x0212, 4);
    $wordDocument = substr_replace($wordDocument, $u32(strlen($plcfendTxt)), 0x0216, 4);
    $wordDocument = substr_replace($wordDocument, $u32($fcPlcfandRef), 0x00ba, 4);
    $wordDocument = substr_replace($wordDocument, $u32(strlen($plcfandRef)), 0x00be, 4);
    $wordDocument = substr_replace($wordDocument, $u32($fcPlcfandTxt), 0x00c2, 4);
    $wordDocument = substr_replace($wordDocument, $u32(strlen($plcfandTxt)), 0x00c6, 4);

    return [
        'streams' => [
            'WordDocument' => $wordDocument,
            '1Table' => $tableStream,
        ],
        'mainText' => $mainText,
        'footnoteText' => $footnoteText,
        'headerText' => $headerText,
        'commentText' => $commentText,
        'endnoteText' => $endnoteText,
    ];
};

$buildSupplementalFieldTableDocStreams = static function () use ($utf16le, $u16, $u32, $plcfldMom): array {
    $fieldBegin = "\x13";
    $fieldSeparator = "\x14";
    $fieldEnd = "\x15";
    $fieldTypeCodes = [
        'DATE' => 0x1f,
        'NOTEREF' => 0x05,
        'PAGE' => 0x21,
        'REF' => 0x03,
    ];
    $fieldRecordsForText = static function (string $text) use ($fieldBegin, $fieldSeparator, $fieldEnd, $fieldTypeCodes): array {
        $characters = preg_split('//u', $text, -1, PREG_SPLIT_NO_EMPTY);
        if (!is_array($characters)) {
            $characters = str_split($text);
        }

        $records = [];
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
                continue;
            }

            if ($character === $fieldSeparator) {
                $records[] = [
                    'cp' => $cp,
                    'character' => 0x14,
                ];
                continue;
            }

            if ($character === $fieldEnd) {
                $records[] = [
                    'cp' => $cp,
                    'character' => 0x15,
                ];
            }
        }

        return $records;
    };

    $mainText = "Main body stays rendered\r";
    $separator = "\r";
    $footnoteText = 'Footnote ' . $fieldBegin . ' PAGE \* Arabic ' . $fieldSeparator . '2' . $fieldEnd . " metadata\r";
    $headerText = 'Header ' . $fieldBegin . ' DATE \@ "yyyy-MM-dd" ' . $fieldSeparator . '2026-06-06' . $fieldEnd . "\r";
    $commentText = 'Comment ' . $fieldBegin . ' REF "legacy_anchor" \h ' . $fieldSeparator . 'Legacy anchor' . $fieldEnd . "\r";
    $endnoteText = 'Endnote ' . $fieldBegin . ' NOTEREF "_RefNote" \f ' . $fieldSeparator . '1' . $fieldEnd . " metadata\r";
    $textboxText = 'Textbox ' . $fieldBegin . ' PAGE \* Arabic ' . $fieldSeparator . '3' . $fieldEnd . " metadata\r";
    $headerTextboxText = 'Header textbox ' . $fieldBegin . ' REF "legacy_anchor" \h ' . $fieldSeparator . 'Anchor' . $fieldEnd . "\r";
    $pieces = [
        $mainText,
        $separator,
        $footnoteText,
        $headerText,
        $commentText,
        $endnoteText,
        $textboxText,
        $headerTextboxText,
    ];
    $cpOffsets = [0];
    foreach ($pieces as $piece) {
        $cpOffsets[] = end($cpOffsets) + strlen($piece);
    }

    $pieceStart = 1536;
    $wordDocument = str_repeat("\0", $pieceStart);
    $pcds = '';
    foreach ($pieces as $piece) {
        $pieceBytes = $utf16le($piece);
        $fc = strlen($wordDocument);
        $wordDocument .= $pieceBytes;
        $pcds .= $u16(0) . $u32($fc) . "\0\0";
    }

    $plc = '';
    foreach ($cpOffsets as $cp) {
        $plc .= $u32($cp);
    }
    $plc .= $pcds;
    $clx = "\x02" . $u32(strlen($plc)) . $plc;

    $headerFieldRecords = $fieldRecordsForText($headerText);
    $footnoteFieldRecords = $fieldRecordsForText($footnoteText);
    $commentFieldRecords = $fieldRecordsForText($commentText);
    $endnoteFieldRecords = $fieldRecordsForText($endnoteText);
    $textboxFieldRecords = $fieldRecordsForText($textboxText);
    $headerTextboxFieldRecords = $fieldRecordsForText($headerTextboxText);
    $plcfldHdr = $plcfldMom($headerFieldRecords, strlen($headerText));
    $plcfldFtn = $plcfldMom($footnoteFieldRecords, strlen($footnoteText));
    $plcfldAtn = $plcfldMom($commentFieldRecords, strlen($commentText));
    $plcfldEdn = $plcfldMom($endnoteFieldRecords, strlen($endnoteText));
    $plcfldTxbx = $plcfldMom($textboxFieldRecords, strlen($textboxText));
    $plcfldHdrTxbx = $plcfldMom($headerTextboxFieldRecords, strlen($headerTextboxText));
    $fcPlcfFldHdr = strlen($clx);
    $fcPlcfFldFtn = $fcPlcfFldHdr + strlen($plcfldHdr);
    $fcPlcfFldAtn = $fcPlcfFldFtn + strlen($plcfldFtn);
    $fcPlcfFldEdn = $fcPlcfFldAtn + strlen($plcfldAtn);
    $fcPlcfFldTxbx = $fcPlcfFldEdn + strlen($plcfldEdn);
    $fcPlcfFldHdrTxbx = $fcPlcfFldTxbx + strlen($plcfldTxbx);
    $tableStream = $clx . $plcfldHdr . $plcfldFtn . $plcfldAtn . $plcfldEdn . $plcfldTxbx . $plcfldHdrTxbx;

    $wordDocument = substr_replace($wordDocument, $u16(0xa5ec), 0, 2);
    $wordDocument = substr_replace($wordDocument, $u16(0x00c1), 2, 2);
    $wordDocument = substr_replace($wordDocument, $u16(0x0204), 10, 2);
    $wordDocument = substr_replace($wordDocument, $u32(0), 24, 4);
    $wordDocument = substr_replace($wordDocument, $u32(strlen($wordDocument)), 28, 4);
    $wordDocument = substr_replace($wordDocument, $u32(strlen($wordDocument)), 0x0040, 4);
    $wordDocument = substr_replace($wordDocument, $u32(strlen($mainText)), 0x004c, 4);
    $wordDocument = substr_replace($wordDocument, $u32(strlen($footnoteText)), 0x0050, 4);
    $wordDocument = substr_replace($wordDocument, $u32(strlen($headerText)), 0x0054, 4);
    $wordDocument = substr_replace($wordDocument, $u32(strlen($commentText)), 0x005c, 4);
    $wordDocument = substr_replace($wordDocument, $u32(strlen($endnoteText)), 0x0060, 4);
    $wordDocument = substr_replace($wordDocument, $u32(strlen($textboxText)), 0x0064, 4);
    $wordDocument = substr_replace($wordDocument, $u32(strlen($headerTextboxText)), 0x0068, 4);
    $wordDocument = substr_replace($wordDocument, $u32(0), 0x01a2, 4);
    $wordDocument = substr_replace($wordDocument, $u32(strlen($clx)), 0x01a6, 4);
    $wordDocument = substr_replace($wordDocument, $u32($fcPlcfFldHdr), 0x0122, 4);
    $wordDocument = substr_replace($wordDocument, $u32(strlen($plcfldHdr)), 0x0126, 4);
    $wordDocument = substr_replace($wordDocument, $u32($fcPlcfFldFtn), 0x012a, 4);
    $wordDocument = substr_replace($wordDocument, $u32(strlen($plcfldFtn)), 0x012e, 4);
    $wordDocument = substr_replace($wordDocument, $u32($fcPlcfFldAtn), 0x0132, 4);
    $wordDocument = substr_replace($wordDocument, $u32(strlen($plcfldAtn)), 0x0136, 4);
    $wordDocument = substr_replace($wordDocument, $u32($fcPlcfFldEdn), 0x021a, 4);
    $wordDocument = substr_replace($wordDocument, $u32(strlen($plcfldEdn)), 0x021e, 4);
    $wordDocument = substr_replace($wordDocument, $u32($fcPlcfFldTxbx), 0x0262, 4);
    $wordDocument = substr_replace($wordDocument, $u32(strlen($plcfldTxbx)), 0x0266, 4);
    $wordDocument = substr_replace($wordDocument, $u32($fcPlcfFldHdrTxbx), 0x0272, 4);
    $wordDocument = substr_replace($wordDocument, $u32(strlen($plcfldHdrTxbx)), 0x0276, 4);

    return [
        'streams' => [
            'WordDocument' => $wordDocument,
            '1Table' => $tableStream,
        ],
        'mainText' => $mainText,
        'footnoteText' => $footnoteText,
        'headerText' => $headerText,
        'commentText' => $commentText,
        'endnoteText' => $endnoteText,
        'textboxText' => $textboxText,
        'headerTextboxText' => $headerTextboxText,
        'headerFieldRecords' => $headerFieldRecords,
        'footnoteFieldRecords' => $footnoteFieldRecords,
        'commentFieldRecords' => $commentFieldRecords,
        'endnoteFieldRecords' => $endnoteFieldRecords,
        'textboxFieldRecords' => $textboxFieldRecords,
        'headerTextboxFieldRecords' => $headerTextboxFieldRecords,
        'fieldTableOffsets' => [
            'header' => $fcPlcfFldHdr,
            'footnote' => $fcPlcfFldFtn,
            'comment' => $fcPlcfFldAtn,
            'endnote' => $fcPlcfFldEdn,
            'textbox' => $fcPlcfFldTxbx,
            'header-textbox' => $fcPlcfFldHdrTxbx,
        ],
    ];
};

$buildBookmarkTableDocStreams = static function () use ($buildSimpleWordDocument, $utf16le, $u16, $u32): array {
    $text = "Intro target text\rJump to anchor\r";
    $wordDocument = $buildSimpleWordDocument($text);

    $sttbf = $u16(0xffff) . $u16(2) . $u16(0);
    foreach (['_HiddenMark', 'legacy_anchor'] as $name) {
        $nameBytes = $utf16le($name);
        $sttbf .= $u16(intdiv(strlen($nameBytes), 2)) . $nameBytes;
    }

    $textEndCp = strlen($text);
    $plcfBkf = $u32(0)
        . $u32(6)
        . $u32($textEndCp + 1)
        . $u16(0) . $u16(0)
        . $u16(1) . $u16(0);
    $plcfBkl = $u32(5)
        . $u32(17)
        . $u32($textEndCp + 1);

    $fcSttbfBkmk = 0;
    $fcPlcfBkf = strlen($sttbf);
    $fcPlcfBkl = $fcPlcfBkf + strlen($plcfBkf);
    $tableStream = $sttbf . $plcfBkf . $plcfBkl;

    $wordDocument = substr_replace($wordDocument, $u32($fcSttbfBkmk), 0x0142, 4);
    $wordDocument = substr_replace($wordDocument, $u32(strlen($sttbf)), 0x0146, 4);
    $wordDocument = substr_replace($wordDocument, $u32($fcPlcfBkf), 0x014a, 4);
    $wordDocument = substr_replace($wordDocument, $u32(strlen($plcfBkf)), 0x014e, 4);
    $wordDocument = substr_replace($wordDocument, $u32($fcPlcfBkl), 0x0152, 4);
    $wordDocument = substr_replace($wordDocument, $u32(strlen($plcfBkl)), 0x0156, 4);

    return [
        'WordDocument' => $wordDocument,
        '0Table' => $tableStream,
    ];
};

$buildNoteTableDocStreams = static function () use ($u16, $u32): array {
    $text = "Alpha \x02 beta * end\r";
    $fibSize = 1024;
    $wordDocument = str_repeat("\0", $fibSize) . $text;
    $wordDocument = substr_replace($wordDocument, $u16(0xa5ec), 0, 2);
    $wordDocument = substr_replace($wordDocument, $u16(0x00c1), 2, 2);
    $wordDocument = substr_replace($wordDocument, $u32($fibSize), 24, 4);
    $wordDocument = substr_replace($wordDocument, $u32($fibSize + strlen($text)), 28, 4);

    $footnoteReferenceCp = 6;
    $endnoteReferenceCp = 13;
    $textEndCp = strlen($text);
    $plcffndRef = $u32($footnoteReferenceCp)
        . $u32($textEndCp)
        . $u16(1);
    $plcffndTxt = $u32(0)
        . $u32(17)
        . $u32(18);
    $plcfendRef = $u32($endnoteReferenceCp)
        . $u32($textEndCp)
        . $u16(0);
    $plcfendTxt = $u32(0)
        . $u32(11)
        . $u32(12);

    $fcPlcffndRef = 0;
    $fcPlcffndTxt = $fcPlcffndRef + strlen($plcffndRef);
    $fcPlcfendRef = $fcPlcffndTxt + strlen($plcffndTxt);
    $fcPlcfendTxt = $fcPlcfendRef + strlen($plcfendRef);
    $tableStream = $plcffndRef . $plcffndTxt . $plcfendRef . $plcfendTxt;

    $wordDocument = substr_replace($wordDocument, $u32($fcPlcffndRef), 0x00aa, 4);
    $wordDocument = substr_replace($wordDocument, $u32(strlen($plcffndRef)), 0x00ae, 4);
    $wordDocument = substr_replace($wordDocument, $u32($fcPlcffndTxt), 0x00b2, 4);
    $wordDocument = substr_replace($wordDocument, $u32(strlen($plcffndTxt)), 0x00b6, 4);
    $wordDocument = substr_replace($wordDocument, $u32($fcPlcfendRef), 0x020a, 4);
    $wordDocument = substr_replace($wordDocument, $u32(strlen($plcfendRef)), 0x020e, 4);
    $wordDocument = substr_replace($wordDocument, $u32($fcPlcfendTxt), 0x0212, 4);
    $wordDocument = substr_replace($wordDocument, $u32(strlen($plcfendTxt)), 0x0216, 4);

    return [
        'WordDocument' => $wordDocument,
        '0Table' => $tableStream,
    ];
};

$buildCommentTableDocStreams = static function () use ($utf16le, $u16, $u32): array {
    $text = "Alpha \x05 beta\r";
    $fibSize = 1024;
    $wordDocument = str_repeat("\0", $fibSize) . $text;
    $wordDocument = substr_replace($wordDocument, $u16(0xa5ec), 0, 2);
    $wordDocument = substr_replace($wordDocument, $u16(0x00c1), 2, 2);
    $wordDocument = substr_replace($wordDocument, $u32($fibSize), 24, 4);
    $wordDocument = substr_replace($wordDocument, $u32($fibSize + strlen($text)), 28, 4);

    $initialsBytes = $utf16le('JD');
    $atrd = $u16(2)
        . $initialsBytes
        . str_repeat("\0", 18 - strlen($initialsBytes))
        . $u16(2)
        . $u16(0)
        . $u16(0)
        . $u32(0x1234);

    $commentReferenceCp = 6;
    $textEndCp = strlen($text);
    $plcfandRef = $u32($commentReferenceCp)
        . $u32($textEndCp)
        . $atrd;
    $plcfandTxt = $u32(0)
        . $u32(31)
        . $u32(32);
    $xstz = static function (string $value) use ($utf16le, $u16): string {
        $bytes = $utf16le($value);

        return $u16(intdiv(strlen($bytes), 2)) . $bytes . $u16(0);
    };
    $commentAuthors = $xstz('Migration Lead')
        . $xstz('Review Editor')
        . $xstz('Janet Doe');

    $fcPlcfandRef = 0;
    $fcPlcfandTxt = strlen($plcfandRef);
    $fcGrpXstAtnOwners = $fcPlcfandTxt + strlen($plcfandTxt);
    $tableStream = $plcfandRef . $plcfandTxt . $commentAuthors;

    $wordDocument = substr_replace($wordDocument, $u32($fcPlcfandRef), 0x00ba, 4);
    $wordDocument = substr_replace($wordDocument, $u32(strlen($plcfandRef)), 0x00be, 4);
    $wordDocument = substr_replace($wordDocument, $u32($fcPlcfandTxt), 0x00c2, 4);
    $wordDocument = substr_replace($wordDocument, $u32(strlen($plcfandTxt)), 0x00c6, 4);
    $wordDocument = substr_replace($wordDocument, $u32($fcGrpXstAtnOwners), 0x01ba, 4);
    $wordDocument = substr_replace($wordDocument, $u32(strlen($commentAuthors)), 0x01be, 4);

    return [
        'WordDocument' => $wordDocument,
        '0Table' => $tableStream,
    ];
};

$buildSectionTableDocStreams = static function () use ($u16, $u32): array {
    $text = "Intro section\fSecond section\r";
    $fibSize = 1024;
    $sepxFc = 1536;
    $sepx = $u16(4) . "\x12\x34\x56\x78";
    $wordDocument = str_repeat("\0", $fibSize) . $text;
    $wordDocument = str_pad($wordDocument, $sepxFc, "\0") . $sepx;
    $wordDocument = substr_replace($wordDocument, $u16(0xa5ec), 0, 2);
    $wordDocument = substr_replace($wordDocument, $u16(0x00c1), 2, 2);
    $wordDocument = substr_replace($wordDocument, $u32($fibSize), 24, 4);
    $wordDocument = substr_replace($wordDocument, $u32($fibSize + strlen($text)), 28, 4);

    $plcfSed = $u32(0)
        . $u32(14)
        . $u32(strlen($text))
        . $u16(0) . $u32(0xffffffff) . $u16(0) . $u32(0)
        . $u16(0) . $u32($sepxFc) . $u16(0) . $u32(0);
    $wordDocument = substr_replace($wordDocument, $u32(0), 0x00ca, 4);
    $wordDocument = substr_replace($wordDocument, $u32(strlen($plcfSed)), 0x00ce, 4);

    return [
        'WordDocument' => $wordDocument,
        '0Table' => $plcfSed,
    ];
};

$buildFormattingTableDocStreams = static function () use ($buildSimpleWordDocument, $u32): array {
    $text = "Styled first\rPlain second\r";
    $wordDocument = $buildSimpleWordDocument($text);
    $textStartFc = 512;
    $textEndFc = $textStartFc + strlen($text);

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
    $papx = $u32($textStartFc)
        . $u32($textEndFc)
        . $u32($papxFkpPage);
    $chpx = $u32($textStartFc)
        . $u32($textStartFc + strlen('Styled'))
        . $u32($textEndFc)
        . $u32($chpxFkpPage)
        . $u32($chpxFkpPage);
    $tableStream = $papx . $chpx;

    $wordDocument = substr_replace($wordDocument, $u32(0), 0x0102, 4);
    $wordDocument = substr_replace($wordDocument, $u32(strlen($papx)), 0x0106, 4);
    $wordDocument = substr_replace($wordDocument, $u32(strlen($papx)), 0x00fa, 4);
    $wordDocument = substr_replace($wordDocument, $u32(strlen($chpx)), 0x00fe, 4);

    return [
        'WordDocument' => $wordDocument,
        '0Table' => $tableStream,
    ];
};

$listLevel = static function (
    int $level,
    int $startAt,
    int $numberFormat,
    string $numberText,
    array $placeholderOffsets = [],
    int $follow = 0,
    string $papx = '',
    string $chpx = '',
    int $flags = 0,
    int $restartLimit = 0
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
        . chr($flags)
        . $rgbxchNums
        . chr($follow)
        . $u32(0)
        . $u32(0)
        . chr(strlen($chpx))
        . chr(strlen($papx))
        . chr($restartLimit)
        . "\0"
        . $papx
        . $chpx
        . $u16(intdiv(strlen($numberTextBytes), 2))
        . $numberTextBytes;
};

$buildListTableDocStreams = static function () use ($u16, $u32, $listLevel): array {
    $text = "First numbered item\rSecond bullet item\r";
    $wordDocument = str_repeat("\0", 1024) . $text;
    $wordDocument = substr_replace($wordDocument, $u16(0xa5ec), 0, 2);
    $wordDocument = substr_replace($wordDocument, $u16(0x00c1), 2, 2);
    $wordDocument = substr_replace($wordDocument, $u32(1024), 24, 4);
    $wordDocument = substr_replace($wordDocument, $u32(1024 + strlen($text)), 28, 4);

    $lstf = static function (int $lsid, int $tplc, array $styles, int $flags, int $grfhic = 0) use ($u16, $u32): string {
        $styleBytes = '';
        for ($level = 0; $level < 9; $level++) {
            $styleBytes .= $u16($styles[$level] ?? 0x0fff);
        }

        return $u32($lsid) . $u32($tplc) . $styleBytes . chr($flags) . chr($grfhic);
    };

    $orderedLevel = $listLevel(0, 3, 0x00, "\0.", [1], 1, "\x11\x22\x33", "\x44\x55");
    $bulletLevel = $listLevel(0, 1, 0x17, "•");
    $plfLst = $u16(2)
        . $lstf(1001, 2001, [0 => 15], 0x01)
        . $lstf(2002, 3002, [], 0x01, 2);
    $lfo1 = $u32(1001) . $u32(0) . $u32(0) . chr(1) . chr(0xfe) . chr(0) . "\0";
    $lfo2 = $u32(2002) . $u32(0) . $u32(0) . chr(0) . chr(0) . chr(0) . "\0";
    $lfoData1 = $u32(0) . $u32(7) . $u32(0x10);
    $lfoData2 = $u32(strlen("First numbered item\r"));
    $plfLfo = $u32(2) . $lfo1 . $lfo2 . $lfoData1 . $lfoData2;
    $tableStream = $plfLst . $orderedLevel . $bulletLevel . $plfLfo;
    $fcPlfLfo = strlen($plfLst) + strlen($orderedLevel) + strlen($bulletLevel);

    $wordDocument = substr_replace($wordDocument, $u32(0), 0x02e2, 4);
    $wordDocument = substr_replace($wordDocument, $u32(strlen($plfLst)), 0x02e6, 4);
    $wordDocument = substr_replace($wordDocument, $u32($fcPlfLfo), 0x02ea, 4);
    $wordDocument = substr_replace($wordDocument, $u32(strlen($plfLfo)), 0x02ee, 4);

    return [
        'WordDocument' => $wordDocument,
        '0Table' => $tableStream,
    ];
};

return [
    'reads CFB directory streams including MiniFAT-backed legacy streams' => static function (TestRunner $t) use ($buildCfb): void {
        $bytes = $buildCfb([
            'WordDocument' => 'small stream bytes',
            "\x05SummaryInformation" => 'summary bytes',
            'LargePreview' => str_repeat('L', 5000),
        ]);
        $compoundFile = CompoundFileBinary::fromBytes($bytes);

        $t->same(['LargePreview', 'WordDocument', "\x05SummaryInformation"], $compoundFile->streamNames());
        $t->true($compoundFile->hasStream('worddocument'));
        $t->same(18, $compoundFile->streamSize('WordDocument'));
        $t->same('small stream bytes', $compoundFile->readStream('WordDocument'));
        $t->same('summary bytes', $compoundFile->readStream("\x05SummaryInformation"));
        $t->same(str_repeat('L', 5000), $compoundFile->readStream('LargePreview'));
    },
    'preserves CFB directory storage timestamps for legacy DOC review provenance' => static function (TestRunner $t) use ($buildCfb, $buildSimpleWordDocument): void {
        $docBytes = $buildCfb([
            'WordDocument' => $buildSimpleWordDocument("Timestamped review packet\r"),
            'ObjectPool/_42/' . "\x01" . 'Ole10Native' => 'timestamped native attachment bytes',
        ], true, [
            '' => [
                'modifiedAt' => '2024-04-06T07:08:09Z',
            ],
            'ObjectPool/_42' => [
                'createdAt' => '2024-04-07T08:09:10Z',
                'modifiedAt' => '2024-04-08T09:10:11Z',
            ],
        ]);

        $compoundFile = CompoundFileBinary::fromBytes($docBytes);
        $entriesByPath = [];
        foreach ($compoundFile->entries() as $entry) {
            $entriesByPath[(string) $entry['path']] = $entry;
        }

        $t->same(null, $entriesByPath['']['createdAt']);
        $t->same('2024-04-06T07:08:09Z', $entriesByPath['']['modifiedAt']);
        $t->same('2024-04-07T08:09:10Z', $entriesByPath['ObjectPool/_42']['createdAt']);
        $t->same('2024-04-08T09:10:11Z', $entriesByPath['ObjectPool/_42']['modifiedAt']);
        $t->same(null, $entriesByPath['WordDocument']['createdAt']);
        $t->same(null, $entriesByPath["ObjectPool/_42/\x01Ole10Native"]['modifiedAt']);

        $result = (new LegacyDocReader())->readBytes($docBytes);
        $streamDirectory = $result['streamDirectory'];
        $directoryEntries = $result['directoryEntries'];
        $directoryByPath = [];
        foreach ($directoryEntries as $entry) {
            $directoryByPath[(string) $entry['path']] = $entry;
        }

        $t->same($streamDirectory, $result['document']->attr('cfbStreamDirectory'));
        $t->same($directoryEntries, $result['document']->attr('cfbDirectoryEntries'));
        $t->same(2, $result['metadata']['cfbStreamCount']);
        $t->same(2, $result['metadata']['cfbTimestampedDirectoryEntryCount']);
        $t->same('root', $directoryByPath['']['type']);
        $t->same('2024-04-06T07:08:09Z', $directoryByPath['']['modifiedAt']);
        $t->same('storage', $directoryByPath['ObjectPool/_42']['type']);
        $t->same('2024-04-07T08:09:10Z', $directoryByPath['ObjectPool/_42']['createdAt']);
        $t->same('2024-04-08T09:10:11Z', $directoryByPath['ObjectPool/_42']['modifiedAt']);
        $t->same('WordDocument', $streamDirectory[1]['path']);
        $t->same('', $streamDirectory[1]['storagePath']);
        $t->true(!isset($streamDirectory[1]['createdAt']), 'CFB stream entries should not invent stream creation timestamps');
        $t->true(!isset($streamDirectory[1]['modifiedAt']), 'CFB stream entries should not invent stream modification timestamps');
        $t->same("ObjectPool/_42/\x01Ole10Native", $streamDirectory[0]['path']);
        $t->same('ObjectPool/_42', $streamDirectory[0]['storagePath']);
        $t->same(strlen('timestamped native attachment bytes'), $streamDirectory[0]['bytes']);
    },
    'preserves CFB directory CLSID and state-bit provenance for legacy DOC storage review' => static function (TestRunner $t) use ($buildCfb, $buildSimpleWordDocument): void {
        $rootClsid = '00112233-4455-6677-8899-aabbccddeeff';
        $objectPoolClsid = '00020906-0000-0000-c000-000000000046';
        $docBytes = $buildCfb([
            'WordDocument' => $buildSimpleWordDocument("Classed storage review packet\r"),
            'ObjectPool/_42/' . "\x01" . 'Ole10Native' => 'classed native attachment bytes',
        ], true, [
            '' => [
                'clsid' => $rootClsid,
                'stateBits' => 0x40000001,
            ],
            'ObjectPool/_42' => [
                'clsid' => $objectPoolClsid,
                'stateBits' => 0x00000010,
            ],
        ]);

        $compoundFile = CompoundFileBinary::fromBytes($docBytes);
        $compoundEntriesByPath = [];
        foreach ($compoundFile->entries() as $entry) {
            $compoundEntriesByPath[(string) $entry['path']] = $entry;
        }

        $t->same($rootClsid, $compoundEntriesByPath['']['clsid']);
        $t->same(0x40000001, $compoundEntriesByPath['']['stateBits']);
        $t->same($objectPoolClsid, $compoundEntriesByPath['ObjectPool/_42']['clsid']);
        $t->same(0x00000010, $compoundEntriesByPath['ObjectPool/_42']['stateBits']);
        $t->true(!isset($compoundEntriesByPath['WordDocument']['clsid']), 'Zero CFB stream CLSIDs should stay omitted');
        $t->true(!isset($compoundEntriesByPath['WordDocument']['stateBits']), 'Zero CFB stream state bits should stay omitted');

        $result = (new LegacyDocReader())->readBytes($docBytes);
        $directoryEntries = $result['directoryEntries'];
        $directoryByPath = [];
        foreach ($directoryEntries as $entry) {
            $directoryByPath[(string) $entry['path']] = $entry;
        }

        $t->same($directoryEntries, $result['document']->attr('cfbDirectoryEntries'));
        $t->same(2, $result['metadata']['cfbClassIdDirectoryEntryCount']);
        $t->same(2, $result['metadata']['cfbStateBitsDirectoryEntryCount']);
        $t->same($rootClsid, $directoryByPath['']['clsid']);
        $t->same(0x40000001, $directoryByPath['']['stateBits']);
        $t->same($objectPoolClsid, $directoryByPath['ObjectPool/_42']['clsid']);
        $t->same(0x00000010, $directoryByPath['ObjectPool/_42']['stateBits']);
        $t->true(!isset($directoryByPath['WordDocument']['clsid']), 'Legacy DOC directory report should not invent stream CLSIDs');
        $t->true(!isset($directoryByPath['WordDocument']['stateBits']), 'Legacy DOC directory report should not invent stream state bits');
    },
    'traverses CFB storage child trees for nested legacy streams' => static function (TestRunner $t) use ($buildCfb): void {
        $bytes = $buildCfb([
            'WordDocument' => 'root stream bytes',
            'Review/Notes' => 'nested reviewer notes',
        ]);
        $compoundFile = CompoundFileBinary::fromBytes($bytes);

        $t->same([
            'Review/Notes',
            'WordDocument',
        ], $compoundFile->streamNames());
        $t->true($compoundFile->hasStream('Review/Notes'));
        $t->same(false, $compoundFile->hasStream('Notes'));
        $t->same('nested reviewer notes', $compoundFile->readStream('review/notes'));
    },
    'looks up CFB Unicode stream paths case-insensitively for legacy DOC review' => static function (TestRunner $t) use ($buildCfb): void {
        $bytes = $buildCfb([
            'WordDocument' => 'root stream bytes',
            'Résumé/Σύνοψη' => 'unicode reviewer notes',
        ]);
        $compoundFile = CompoundFileBinary::fromBytes($bytes);

        $t->same([
            'Résumé/Σύνοψη',
            'WordDocument',
        ], $compoundFile->streamNames());
        $t->true($compoundFile->hasStream('résumé/σύνοψη'));
        $t->same(22, $compoundFile->streamSize('RÉSUMÉ/ΣΎΝΟΨΗ'));
        $t->same('unicode reviewer notes', $compoundFile->readStream('résumé/σύνοψη'));
    },
    'rejects orphaned active CFB directory entries before exposing streams' => static function (TestRunner $t) use ($buildCfb, $buildSimpleWordDocument, $u32): void {
        $bytes = $buildCfb([
            'WordDocument' => $buildSimpleWordDocument("Reachable legacy body\r"),
            'Review/Notes' => 'orphaned reviewer notes',
        ]);
        $directorySectorOffset = 512 + 512;
        $wordDocumentLeftSiblingOffset = $directorySectorOffset + 128 + 68;
        $orphanedReviewStorage = substr_replace($bytes, $u32(0xffffffff), $wordDocumentLeftSiblingOffset, 4);

        $t->throws(\RuntimeException::class, static fn (): CompoundFileBinary => CompoundFileBinary::fromBytes($orphanedReviewStorage));
    },
    'rejects cyclic CFB directory child trees before exposing streams' => static function (TestRunner $t) use ($buildCfb, $u32): void {
        $bytes = $buildCfb([
            'WordDocument' => 'root stream bytes',
        ]);
        $directorySectorOffset = 512 + 512;
        $firstStreamRightSiblingOffset = $directorySectorOffset + 128 + 72;
        $cyclic = substr_replace($bytes, $u32(1), $firstStreamRightSiblingOffset, 4);

        $t->throws(\RuntimeException::class, static fn (): CompoundFileBinary => CompoundFileBinary::fromBytes($cyclic));
    },
    'rejects CFB directory sibling stream IDs that use sector-chain sentinels' => static function (TestRunner $t) use ($buildCfb, $u32): void {
        $bytes = $buildCfb([
            'WordDocument' => 'root stream bytes',
        ]);
        $directorySectorOffset = 512 + 512;
        $firstStreamLeftSiblingOffset = $directorySectorOffset + 128 + 68;
        $firstStreamRightSiblingOffset = $directorySectorOffset + 128 + 72;

        foreach ([0xfffffffe, 0xfffffffd, 0xfffffffc] as $sentinel) {
            $corruptLeft = substr_replace($bytes, $u32($sentinel), $firstStreamLeftSiblingOffset, 4);
            $t->throws(\RuntimeException::class, static fn (): CompoundFileBinary => CompoundFileBinary::fromBytes($corruptLeft));

            $corruptRight = substr_replace($bytes, $u32($sentinel), $firstStreamRightSiblingOffset, 4);
            $t->throws(\RuntimeException::class, static fn (): CompoundFileBinary => CompoundFileBinary::fromBytes($corruptRight));
        }
    },
    'rejects unsorted CFB directory sibling trees before stream lookup' => static function (TestRunner $t) use ($buildCfb, $u32): void {
        $bytes = $buildCfb([
            'A' => 'a',
            'BB' => 'bb',
            'CCC' => 'ccc',
        ]);
        $directorySectorOffset = 512 + 512;
        $bbLeftSiblingOffset = $directorySectorOffset + (2 * 128) + 68;
        $bbRightSiblingOffset = $directorySectorOffset + (2 * 128) + 72;
        $unsorted = substr_replace($bytes, $u32(3), $bbLeftSiblingOffset, 4);
        $unsorted = substr_replace($unsorted, $u32(0xffffffff), $bbRightSiblingOffset, 4);

        $t->throws(\RuntimeException::class, static fn (): CompoundFileBinary => CompoundFileBinary::fromBytes($unsorted));
    },
    'rejects consecutive red CFB directory sibling-tree nodes' => static function (TestRunner $t) use ($buildCfb): void {
        $bytes = $buildCfb([
            'A' => 'a',
            'BB' => 'bb',
            'CCC' => 'ccc',
        ]);
        $directorySectorOffset = 512 + 512;
        $bbColorOffset = $directorySectorOffset + (2 * 128) + 67;
        $aColorOffset = $directorySectorOffset + (1 * 128) + 67;
        $redTree = substr_replace($bytes, "\0", $bbColorOffset, 1);
        $redTree = substr_replace($redTree, "\0", $aColorOffset, 1);

        $t->throws(\RuntimeException::class, static fn (): CompoundFileBinary => CompoundFileBinary::fromBytes($redTree));
    },
    'rejects illegal CFB directory names and invalid color flags' => static function (TestRunner $t) use ($buildCfb): void {
        $t->throws(\RuntimeException::class, static fn (): CompoundFileBinary => CompoundFileBinary::fromBytes($buildCfb([
            'Bad:Name' => 'bad stream',
        ])));

        $bytes = $buildCfb([
            'WordDocument' => 'root stream bytes',
        ]);
        $directorySectorOffset = 512 + 512;
        $streamColorOffset = $directorySectorOffset + 128 + 67;
        $invalidColor = substr_replace($bytes, "\x02", $streamColorOffset, 1);

        $t->throws(\RuntimeException::class, static fn (): CompoundFileBinary => CompoundFileBinary::fromBytes($invalidColor));
    },
    'rejects dirty CFB directory name padding before stream lookup' => static function (TestRunner $t) use ($buildCfb, $utf16le): void {
        $bytes = $buildCfb([
            'WordDocument' => 'root stream bytes',
        ]);
        $directorySectorOffset = 512 + 512;
        $rootNamePaddingOffset = $directorySectorOffset + strlen($utf16le("Root Entry\0"));
        $streamNamePaddingOffset = $directorySectorOffset + 128 + strlen($utf16le("WordDocument\0"));

        foreach ([
            'dirty root name padding' => substr_replace($bytes, "\x01", $rootNamePaddingOffset, 1),
            'dirty stream name padding' => substr_replace($bytes, "\x01", $streamNamePaddingOffset, 1),
        ] as $corruptDocBytes) {
            $t->throws(\RuntimeException::class, static fn (): CompoundFileBinary => CompoundFileBinary::fromBytes($corruptDocBytes));
        }
    },
    'rejects unsupported CFB minor versions before stream lookup' => static function (TestRunner $t) use ($buildCfb, $u16): void {
        $bytes = $buildCfb([
            'WordDocument' => 'root stream bytes',
        ]);

        $unsupportedMinor = substr_replace($bytes, $u16(0x003d), 24, 2);
        $t->throws(\InvalidArgumentException::class, static fn (): CompoundFileBinary => CompoundFileBinary::fromBytes($unsupportedMinor));
    },
    'rejects invalid CFB header versions and directory-sector counts before stream lookup' => static function (TestRunner $t) use ($buildCfb, $u16, $u32): void {
        $bytes = $buildCfb([
            'WordDocument' => 'root stream bytes',
        ]);

        $unsupportedMajor = substr_replace($bytes, $u16(5), 26, 2);
        $t->throws(\InvalidArgumentException::class, static fn (): CompoundFileBinary => CompoundFileBinary::fromBytes($unsupportedMajor));

        $versionThreeWithDirectoryCount = substr_replace($bytes, $u32(1), 40, 4);
        $t->throws(\RuntimeException::class, static fn (): CompoundFileBinary => CompoundFileBinary::fromBytes($versionThreeWithDirectoryCount));

        $versionFour = $buildCfb([
            'WordDocument' => 'root stream bytes',
        ], true, [], ['majorVersion' => 4]);
        $versionFourFile = CompoundFileBinary::fromBytes($versionFour);
        $t->same('root stream bytes', $versionFourFile->readStream('WordDocument'));

        $versionFourWithMissingDirectoryCount = substr_replace($versionFour, $u32(0), 40, 4);
        $t->throws(\RuntimeException::class, static fn (): CompoundFileBinary => CompoundFileBinary::fromBytes($versionFourWithMissingDirectoryCount));

        $versionFourWithDirtyHeaderPadding = substr_replace($versionFour, "\x01", 512, 1);
        $t->throws(\RuntimeException::class, static fn (): CompoundFileBinary => CompoundFileBinary::fromBytes($versionFourWithDirtyHeaderPadding));
    },
    'rejects CFB files with trailing partial sectors before stream lookup' => static function (TestRunner $t) use ($buildCfb): void {
        $versionThree = $buildCfb([
            'WordDocument' => 'root stream bytes',
        ]);
        $t->same('root stream bytes', CompoundFileBinary::fromBytes($versionThree)->readStream('WordDocument'));
        $t->throws(\RuntimeException::class, static fn (): CompoundFileBinary => CompoundFileBinary::fromBytes($versionThree . "\0"));

        $versionFour = $buildCfb([
            'WordDocument' => str_repeat('V', 4096),
        ], true, [], ['majorVersion' => 4]);
        $t->same(str_repeat('V', 4096), CompoundFileBinary::fromBytes($versionFour)->readStream('WordDocument'));
        $t->throws(\RuntimeException::class, static fn (): CompoundFileBinary => CompoundFileBinary::fromBytes($versionFour . "\0"));
    },
    'rejects reserved CFB header fields and invalid root storage identity before stream lookup' => static function (TestRunner $t) use ($buildCfb, $u32): void {
        $bytes = $buildCfb([
            'WordDocument' => 'root stream bytes',
        ]);
        $directorySectorOffset = 512 + 512;

        foreach ([
            'non-null header CLSID' => substr_replace($bytes, "\x01", 8, 1),
            'nonzero reserved header bytes' => substr_replace($bytes, "\x01\0\0\0\0\0", 34, 6),
            'invalid mini stream cutoff' => substr_replace($bytes, $u32(2048), 56, 4),
            'invalid root storage name' => substr_replace($bytes, "X\0", $directorySectorOffset, 2),
        ] as $corruptDocBytes) {
            $t->throws(\RuntimeException::class, static fn (): CompoundFileBinary => CompoundFileBinary::fromBytes($corruptDocBytes));
        }
    },
    'rejects CFB FAT entries beyond the physical file before stream lookup' => static function (TestRunner $t) use ($buildCfb, $buildSimpleWordDocument, $u32): void {
        $bytes = $buildCfb([
            'WordDocument' => $buildSimpleWordDocument("FAT EOF guard packet\r"),
        ]);
        $fatEntryPastEof = 127;
        $fatEntryOffset = 512 + ($fatEntryPastEof * 4);
        $corruptDocBytes = substr_replace($bytes, $u32(0xfffffffe), $fatEntryOffset, 4);

        $t->throws(\RuntimeException::class, static fn (): array => (new LegacyDocReader())->readBytes($corruptDocBytes));
    },
    'rejects unowned CFB FAT marker entries on physical sectors before stream lookup' => static function (TestRunner $t) use ($buildCfb, $buildSimpleWordDocument, $u32): void {
        $bytes = $buildCfb([
            'WordDocument' => $buildSimpleWordDocument("Reserved FAT marker packet\r"),
        ]);
        $sectorSize = 512;
        $unusedSectorId = intdiv(strlen($bytes) - $sectorSize, $sectorSize);
        $bytesWithUnusedSector = $bytes . str_repeat("\0", $sectorSize);
        $fatEntryOffset = $sectorSize + ($unusedSectorId * 4);

        foreach ([
            'reserved marker' => 0xfffffffb,
            'out-of-range regular sector pointer' => $unusedSectorId + 16,
            'unowned FATSECT marker' => 0xfffffffd,
            'unowned DIFSECT marker' => 0xfffffffc,
        ] as $_label => $fatEntryValue) {
            $corruptDocBytes = substr_replace($bytesWithUnusedSector, $u32($fatEntryValue), $fatEntryOffset, 4);
            $t->throws(\RuntimeException::class, static fn (): array => (new LegacyDocReader())->readBytes($corruptDocBytes));
        }
    },
    'rejects inconsistent CFB MiniFAT and DIFAT header chains before stream lookup' => static function (TestRunner $t) use ($buildCfb, $u32): void {
        $bytes = $buildCfb([
            'WordDocument' => 'root stream bytes',
            "\x05SummaryInformation" => 'summary bytes',
        ]);

        foreach ([
            'MiniFAT start sector without a MiniFAT count' => substr_replace($bytes, $u32(0), 64, 4),
            'MiniFAT count without a valid MiniFAT start sector' => substr_replace($bytes, $u32(0xfffffffe), 60, 4),
            'DIFAT start sector without a DIFAT count' => substr_replace($bytes, $u32(2), 68, 4),
        ] as $corruptDocBytes) {
            $t->throws(\RuntimeException::class, static fn (): CompoundFileBinary => CompoundFileBinary::fromBytes($corruptDocBytes));
        }
    },
    'rejects overlong CFB MiniFAT chains beyond the declared sector count' => static function (TestRunner $t) use ($buildCfb, $u32): void {
        $bytes = $buildCfb([
            'WordDocument' => 'root stream bytes',
            "\x05SummaryInformation" => 'summary bytes',
        ]);
        $sectorSize = 512;
        $endOfChain = 0xfffffffe;
        $miniFatStartSector = unpack('Vvalue', substr($bytes, 60, 4))['value'];
        $extraMiniFatSector = intdiv(strlen($bytes) - $sectorSize, $sectorSize);
        $overlongMiniFatChain = $bytes . str_repeat("\0", $sectorSize);
        $overlongMiniFatChain = substr_replace(
            $overlongMiniFatChain,
            $u32($extraMiniFatSector),
            $sectorSize + ((int) $miniFatStartSector * 4),
            4
        );
        $overlongMiniFatChain = substr_replace(
            $overlongMiniFatChain,
            $u32($endOfChain),
            $sectorSize + ($extraMiniFatSector * 4),
            4
        );

        $t->throws(\RuntimeException::class, static fn (): CompoundFileBinary => CompoundFileBinary::fromBytes($overlongMiniFatChain));
    },
    'rejects small CFB streams when MiniFAT metadata is absent before stream lookup' => static function (TestRunner $t) use ($buildCfb, $buildSimpleWordDocument): void {
        $docBytes = $buildCfb([
            'WordDocument' => $buildSimpleWordDocument("Small regular stream must stay guarded\r"),
        ], false);

        $t->throws(\RuntimeException::class, static fn (): CompoundFileBinary => CompoundFileBinary::fromBytes($docBytes));
    },
    'reads CFB packages whose FAT sector is listed from a DIFAT overflow sector' => static function (TestRunner $t) use ($buildCfb, $buildSimpleWordDocument, $moveFatListingToDifatSector): void {
        $wordDocument = $buildSimpleWordDocument("DIFAT overflow import packet\r");
        $fixture = $moveFatListingToDifatSector($buildCfb([
            'WordDocument' => $wordDocument,
            "\x05SummaryInformation" => 'summary bytes',
        ]));

        $t->true($fixture['difatSector'] > 0);
        $cfb = CompoundFileBinary::fromBytes($fixture['bytes']);
        $t->same(['WordDocument', "\x05SummaryInformation"], $cfb->streamNames());
        $t->true($cfb->hasStream('WordDocument'));
        $t->same($wordDocument, $cfb->readStream('WordDocument'));

        $result = (new LegacyDocReader())->readBytes($fixture['bytes']);
        $t->same('DIFAT overflow import packet', $result['document']->children[0]->children[0]->attr('text'));
        $t->same(2, $result['metadata']['cfbStreamCount']);
        $t->same(['WordDocument', "\x05SummaryInformation"], $result['document']->attr('cfbStreams'));
    },
    'rejects unterminated CFB DIFAT overflow chains before stream lookup' => static function (TestRunner $t) use ($buildCfb, $moveFatListingToDifatSector, $u32): void {
        $fixture = $moveFatListingToDifatSector($buildCfb([
            'WordDocument' => 'root stream bytes',
        ]));
        $unterminated = substr_replace($fixture['bytes'], $u32(0), 512 + ((int) $fixture['difatSector'] * 512) + 508, 4);

        $t->throws(\RuntimeException::class, static fn (): CompoundFileBinary => CompoundFileBinary::fromBytes($unterminated));
    },
    'rejects surplus CFB DIFAT FAT-sector listings beyond the declared count' => static function (TestRunner $t) use ($buildCfb, $moveFatListingToDifatSector, $u32): void {
        $bytes = $buildCfb([
            'WordDocument' => 'root stream bytes',
        ]);
        $surplusHeaderDifatEntry = substr_replace($bytes, $u32(2), 80, 4);

        $t->throws(\RuntimeException::class, static fn (): CompoundFileBinary => CompoundFileBinary::fromBytes($surplusHeaderDifatEntry));

        $fixture = $moveFatListingToDifatSector($bytes);
        $surplusOverflowDifatEntry = substr_replace($fixture['bytes'], $u32(1), 512 + ((int) $fixture['difatSector'] * 512) + 4, 4);

        $t->throws(\RuntimeException::class, static fn (): CompoundFileBinary => CompoundFileBinary::fromBytes($surplusOverflowDifatEntry));
    },
    'rejects invalid CFB directory object-type fields before stream lookup' => static function (TestRunner $t) use ($buildCfb, $u32, $u64): void {
        $bytes = $buildCfb([
            'WordDocument' => 'root stream bytes',
            'ObjectPool/_42/Native' => 'nested native bytes',
        ]);
        $directorySectorOffset = 512 + 512;

        $rootLeftSibling = substr_replace($bytes, $u32(1), $directorySectorOffset + 68, 4);
        $t->throws(\RuntimeException::class, static fn (): CompoundFileBinary => CompoundFileBinary::fromBytes($rootLeftSibling));

        $wordDocumentChild = substr_replace($bytes, $u32(2), $directorySectorOffset + 128 + 76, 4);
        $t->throws(\RuntimeException::class, static fn (): CompoundFileBinary => CompoundFileBinary::fromBytes($wordDocumentChild));

        $objectPoolSize = substr_replace($bytes, $u64(64), $directorySectorOffset + (2 * 128) + 120, 8);
        $t->throws(\RuntimeException::class, static fn (): CompoundFileBinary => CompoundFileBinary::fromBytes($objectPoolSize));
    },
    'rejects CFB stream directory entries carrying storage-only metadata before stream lookup' => static function (TestRunner $t) use ($buildCfb, $u32, $filetime): void {
        $bytes = $buildCfb([
            'WordDocument' => 'root stream bytes',
        ]);
        $directorySectorOffset = 512 + 512;
        $wordDocumentDirectoryOffset = $directorySectorOffset + 128;

        foreach ([
            'stream CLSID' => substr_replace($bytes, "\x01", $wordDocumentDirectoryOffset + 80, 1),
            'stream state bits' => substr_replace($bytes, $u32(0x00000010), $wordDocumentDirectoryOffset + 96, 4),
            'stream creation time' => substr_replace($bytes, $filetime('2024-01-01T00:00:00Z'), $wordDocumentDirectoryOffset + 100, 8),
            'stream modification time' => substr_replace($bytes, $filetime('2024-01-02T00:00:00Z'), $wordDocumentDirectoryOffset + 108, 8),
        ] as $_label => $corruptDocBytes) {
            $t->throws(\RuntimeException::class, static fn (): CompoundFileBinary => CompoundFileBinary::fromBytes($corruptDocBytes));
        }
    },
    'rejects CFB directory start-sector mismatches before stream lookup' => static function (TestRunner $t) use ($buildCfb, $u32, $u64): void {
        $bytes = $buildCfb([
            'WordDocument' => 'root stream bytes',
            'ObjectPool/_42/Native' => 'nested native bytes',
        ]);
        $directorySectorOffset = 512 + 512;

        $objectPoolStartSector = substr_replace($bytes, $u32(2), $directorySectorOffset + (2 * 128) + 116, 4);
        $t->throws(\RuntimeException::class, static fn (): CompoundFileBinary => CompoundFileBinary::fromBytes($objectPoolStartSector));

        $zeroLengthStreamWithStartSector = substr_replace($bytes, $u64(0), $directorySectorOffset + 128 + 120, 8);
        $t->throws(\RuntimeException::class, static fn (): CompoundFileBinary => CompoundFileBinary::fromBytes($zeroLengthStreamWithStartSector));

        $rootWithoutMiniStreamBytes = $buildCfb([
            'WordDocument' => str_repeat('W', 5000),
        ], false);
        $rootStartSector = substr_replace($rootWithoutMiniStreamBytes, $u32(2), $directorySectorOffset + 116, 4);
        $t->throws(\RuntimeException::class, static fn (): CompoundFileBinary => CompoundFileBinary::fromBytes($rootStartSector));
    },
    'rejects malformed active CFB directory entry names before stream lookup' => static function (TestRunner $t) use ($buildCfb, $u16, $u32): void {
        $bytes = $buildCfb([
            'WordDocument' => 'root stream bytes',
            'Review/Notes' => 'review stream bytes',
        ]);
        $directorySectorOffset = 512 + 512;

        $wordDocumentNameLength = 26;
        $missingTerminator = substr_replace($bytes, "X\0", $directorySectorOffset + 128 + $wordDocumentNameLength - 2, 2);
        $t->throws(\RuntimeException::class, static fn (): CompoundFileBinary => CompoundFileBinary::fromBytes($missingTerminator));

        $orphanedReviewStorage = substr_replace($bytes, $u32(0xffffffff), $directorySectorOffset + 128 + 68, 4);
        $invalidNameLength = substr_replace($orphanedReviewStorage, $u16(65), $directorySectorOffset + (2 * 128) + 64, 2);
        $t->throws(\RuntimeException::class, static fn (): CompoundFileBinary => CompoundFileBinary::fromBytes($invalidNameLength));
    },
    'rejects red CFB directory sibling-tree roots before stream lookup' => static function (TestRunner $t) use ($buildCfb): void {
        $bytes = $buildCfb([
            'A' => 'a',
            'BB' => 'bb',
            'CCC' => 'ccc',
        ]);
        $directorySectorOffset = 512 + 512;
        $bbColorOffset = $directorySectorOffset + (2 * 128) + 67;
        $redRoot = substr_replace($bytes, "\0", $bbColorOffset, 1);

        $t->throws(\RuntimeException::class, static fn (): CompoundFileBinary => CompoundFileBinary::fromBytes($redRoot));
    },
    'rejects unequal black-height CFB directory sibling trees before stream lookup' => static function (TestRunner $t) use ($buildCfb): void {
        $bytes = $buildCfb([
            'A' => 'a',
            'BB' => 'bb',
        ]);
        $directorySectorOffset = 512 + 512;
        $aColorOffset = $directorySectorOffset + 128 + 67;
        $imbalancedBlackHeight = substr_replace($bytes, "\x01", $aColorOffset, 1);

        $t->throws(\RuntimeException::class, static fn (): CompoundFileBinary => CompoundFileBinary::fromBytes($imbalancedBlackHeight));
    },
    'rejects duplicate and misclassified CFB FAT sectors before stream lookup' => static function (TestRunner $t) use ($buildCfb, $u32): void {
        $bytes = $buildCfb([
            'WordDocument' => 'root stream bytes',
        ]);

        $duplicateFatSector = substr_replace($bytes, $u32(2), 44, 4);
        $duplicateFatSector = substr_replace($duplicateFatSector, $u32(0), 80, 4);
        $t->throws(\RuntimeException::class, static fn (): CompoundFileBinary => CompoundFileBinary::fromBytes($duplicateFatSector));

        $fatSectorNotMarked = substr_replace($bytes, $u32(0xfffffffe), 512, 4);
        $t->throws(\RuntimeException::class, static fn (): CompoundFileBinary => CompoundFileBinary::fromBytes($fatSectorNotMarked));
    },
    'rejects CFB regular stream chains that reuse directory sectors before stream lookup' => static function (TestRunner $t) use ($buildCfb, $buildSimpleWordDocument, $u32): void {
        $bytes = $buildCfb([
            'WordDocument' => $buildSimpleWordDocument("Overlapping sector payload should stay opaque\r"),
            'Preview' => str_repeat('P', 600),
        ], false);
        $directorySectorOffset = 512 + 512;
        $wordDocumentStartSectorOffset = $directorySectorOffset + 128 + 116;
        $overlappingStream = substr_replace($bytes, $u32(1), $wordDocumentStartSectorOffset, 4);

        $t->throws(\RuntimeException::class, static fn (): CompoundFileBinary => CompoundFileBinary::fromBytes($overlappingStream));
    },
    'extracts non-complex legacy DOC text and OLE SummaryInformation metadata' => static function (TestRunner $t) use ($buildCfb, $buildSimpleWordDocument, $propertySet): void {
        $docBytes = $buildCfb([
            'WordDocument' => $buildSimpleWordDocument("Legacy import title\rReviewer notes keep hard\vbreaks.\r"),
            "\x05SummaryInformation" => $propertySet([
                2 => 'Legacy CFB Packet',
                4 => 'Migration Desk',
                6 => 'Source .doc review notes',
                8 => 'Reviewer',
            ]),
            "\x05DocumentSummaryInformation" => $propertySet([
                2 => 'Import queue',
                15 => 'Example Press',
            ]),
        ]);

        $result = (new LegacyDocReader())->readBytes($docBytes);
        $document = $result['document'];
        $blocks = (new WordPressBlockWriter())->write($document);
        $markdown = (new MarkdownWriter())->write($document);

        $t->same('doc', $document->attr('sourceFormat'));
        $t->same('fib-text-range', $document->attr('textSource'));
        $t->same(false, $result['fib']['encrypted']);
        $t->same(false, $result['fib']['extendedCharacters']);
        $t->same('Legacy CFB Packet', $result['metadata']['title']);
        $t->same('Migration Desk', $result['metadata']['creator']);
        $t->same('Source .doc review notes', $result['metadata']['description']);
        $t->same('Reviewer', $result['metadata']['lastModifiedBy']);
        $t->same('Import queue', $result['metadata']['category']);
        $t->same('Example Press', $result['metadata']['company']);
        $t->same(2, count($document->children));
        $t->same('Legacy import title', $document->children[0]->children[0]->attr('text'));
        $t->same('Reviewer notes keep hard', $document->children[1]->children[0]->attr('text'));
        $t->same('linebreak', $document->children[1]->children[1]->type);
        $t->same('breaks.', $document->children[1]->children[2]->attr('text'));
        $t->contains('Reviewer notes keep hard', $markdown);
        $t->contains("<p>Reviewer notes keep hard<br/>breaks.</p>", $blocks);
    },
    'extracts legacy DOC SummaryInformation dates counts and security metadata' => static function (TestRunner $t) use ($buildCfb, $buildSimpleWordDocument, $typedPropertySet, $typedLpwstr, $typedI4, $typedFiletime): void {
        $docBytes = $buildCfb([
            'WordDocument' => $buildSimpleWordDocument("Metadata review packet\r"),
            "\x05SummaryInformation" => $typedPropertySet([
                2 => $typedLpwstr('Unicode Legacy Packet Ω'),
                11 => $typedFiletime('2024-02-03T04:05:06Z'),
                12 => $typedFiletime('2024-01-02T03:04:05Z'),
                13 => $typedFiletime('2024-02-10T11:12:13Z'),
                14 => $typedI4(7),
                15 => $typedI4(321),
                16 => $typedI4(2048),
                18 => $typedLpwstr('Word 97 Review Importer'),
                19 => $typedI4(0x00000003),
            ]),
        ]);

        $result = (new LegacyDocReader())->readBytes($docBytes);
        $metadata = $result['metadata'];

        $t->same('Unicode Legacy Packet Ω', $metadata['title']);
        $t->same('2024-02-03T04:05:06Z', $metadata['lastPrinted']);
        $t->same('2024-01-02T03:04:05Z', $metadata['created']);
        $t->same('2024-02-10T11:12:13Z', $metadata['modified']);
        $t->same(7, $metadata['pageCount']);
        $t->same(321, $metadata['wordCount']);
        $t->same(2048, $metadata['characterCount']);
        $t->same('Word 97 Review Importer', $metadata['application']);
        $t->same(3, $metadata['documentSecurity']);
        $t->same(['passwordProtected', 'readOnlyRecommended'], $metadata['documentSecurityFlags']);
        $t->same('Unicode Legacy Packet Ω', $result['document']->attr('meta')['title']);
    },
    'surfaces legacy DOC FibBase language and document-state flags for review' => static function (TestRunner $t) use ($buildCfb, $buildSimpleWordDocument, $u16): void {
        $wordDocument = $buildSimpleWordDocument("FibBase review packet\r", 0x2c33);
        $wordDocument = substr_replace($wordDocument, $u16(0x0409), 6, 2);
        $wordDocument = substr_replace($wordDocument, $u16(0x00bf), 12, 2);
        $docBytes = $buildCfb([
            'WordDocument' => $wordDocument,
        ]);

        $result = (new LegacyDocReader())->readBytes($docBytes);
        $fib = $result['fib'];
        $fibBase = $result['metadata']['fibBase'];

        $t->same(0x0409, $fib['languageId']);
        $t->same('en-US', $fib['languageTag']);
        $t->same(0x00bf, $fib['nFibBack']);
        $t->same(0, $fib['lKey']);
        $t->same(3, $fib['quickSaveCount']);
        $t->same([
            'template',
            'glossary',
            'readOnlyRecommended',
            'writeReservation',
            'loadOverride',
        ], $fib['flagNames']);
        $t->same(true, $fib['template']);
        $t->same(true, $fib['glossary']);
        $t->same(true, $fib['readOnlyRecommended']);
        $t->same(true, $fib['writeReservation']);
        $t->same(true, $fib['loadOverride']);
        $t->same(false, $fib['encrypted']);
        $t->same($fibBase, $result['document']->attr('meta')['fibBase']);
        $t->same(0x0409, $fibBase['languageId']);
        $t->same('en-US', $fibBase['languageTag']);
        $t->same(0x00bf, $fibBase['nFibBack']);
        $t->same('0Table', $fibBase['tableStream']);
        $t->same(3, $fibBase['quickSaveCount']);
        $t->same([
            'template',
            'glossary',
            'readOnlyRecommended',
            'writeReservation',
            'loadOverride',
        ], $fibBase['flags']);
        $t->same(true, $fibBase['template']);
        $t->same(true, $fibBase['glossary']);
        $t->same(true, $fibBase['readOnlyRecommended']);
        $t->same(true, $fibBase['writeReservation']);
        $t->same(true, $fibBase['loadOverride']);
    },
    'extracts legacy DOC DOP document properties as metadata-only review policy' => static function (TestRunner $t) use ($buildCfb, $buildSimpleWordDocument, $dopBase, $u32): void {
        $dop = $dopBase();
        $wordDocument = $buildSimpleWordDocument("DOP metadata review packet\r");
        $wordDocument = substr_replace($wordDocument, $u32(0), 0x0192, 4);
        $wordDocument = substr_replace($wordDocument, $u32(strlen($dop)), 0x0196, 4);
        $docBytes = $buildCfb([
            'WordDocument' => $wordDocument,
            '0Table' => $dop,
        ]);

        $result = (new LegacyDocReader())->readBytes($docBytes);
        $documentProperties = $result['documentProperties'];
        $metadata = $result['metadata'];
        $blocks = (new WordPressBlockWriter())->write($result['document']);
        $expectedPolicyFlags = [
            'facing-pages',
            'mail-merge-main-document',
            'spelling-checked',
            'spelling-errors-hidden',
            'label-document',
            'auto-hyphenation',
            'link-styles',
            'track-revisions',
            'exact-word-counts',
            'comments-locked',
            'mirror-margins',
            'word97-compatibility',
            'forms-protection-enabled',
            'revision-markup-view',
            'vba-project-locked',
            'embed-fonts',
            'print-form-data-only',
            'save-form-data-only',
            'shade-form-fields',
            'shade-merge-fields',
            'include-subdocuments-in-statistics',
            'gutter-at-top',
        ];

        $t->same($documentProperties, $metadata['documentProperties']);
        $t->same($documentProperties, $result['document']->attr('documentProperties'));
        $t->same($documentProperties, $result['document']->attr('meta')['documentProperties']);
        $t->same(84, $metadata['documentPropertyByteCount']);
        $t->same(84, $documentProperties['byteCount']);
        $t->same(84, $documentProperties['baseByteCount']);
        $t->same($expectedPolicyFlags, $documentProperties['policyFlags']);
        $t->same($expectedPolicyFlags, $metadata['documentPolicyFlags']);
        $t->same('beneath-text', $documentProperties['footnotePlacement']);
        $t->same('section', $documentProperties['footnoteNumberingRestart']);
        $t->same(5, $documentProperties['footnoteStartingNumber']);
        $t->same(0x0003, $documentProperties['compatibilityOptions']);
        $t->same([
            'no-tab-hanging-indent',
            'no-space-raise-lower',
        ], $documentProperties['compatibilityOptionFlags']);
        $t->same($documentProperties['compatibilityOptionFlags'], $metadata['documentCompatibilityOptionFlags']);
        $t->same(720, $documentProperties['defaultTabStopTwips']);
        $t->same(65001, $documentProperties['htmlCodePage']);
        $t->same(360, $documentProperties['hyphenationZoneTwips']);
        $t->same(3, $documentProperties['consecutiveHyphenLimit']);
        $t->same('2024-04-06T07:08:00', $documentProperties['createdAt']);
        $t->same('2024-04-08T09:10:00', $documentProperties['revisedAt']);
        $t->same('2024-04-09T11:12:00', $documentProperties['lastPrintedAt']);
        $t->same(4, $documentProperties['revisionNumber']);
        $t->same(125, $documentProperties['editMinutes']);
        $t->same('page', $documentProperties['endnoteNumberingRestart']);
        $t->same(7, $documentProperties['endnoteStartingNumber']);
        $t->same('document-end', $documentProperties['endnotePlacement']);
        $t->same('0a0b0c0d', $documentProperties['protectionHash']);
        $t->same([
            'wordCount' => 2345,
            'characterCount' => 12345,
            'pageCount' => 12,
            'paragraphCount' => 67,
            'lineCount' => 890,
            'wordCountWithSubdocuments' => 2400,
            'characterCountWithSubdocuments' => 14000,
            'pageCountWithSubdocuments' => 13,
            'paragraphCountWithSubdocuments' => 72,
            'lineCountWithSubdocuments' => 901,
        ], $documentProperties['statistics']);
        $t->same([
            'kind' => 'web',
            'zoomPercent' => 125,
            'zoomKind' => 'best-fit',
            'gutterAtTop' => true,
        ], $documentProperties['view']);
        $t->contains('<p>DOP metadata review packet</p>', $blocks);
        $t->true(!str_contains($blocks, 'auto-hyphenation'));
        $t->true(!str_contains($blocks, 'no-tab-hanging-indent'));
        $t->true(!str_contains($blocks, '0a0b0c0d'));
    },
    'decodes legacy DOC DOP Copts60 compatibility options for review metadata' => static function (TestRunner $t) use ($buildCfb, $buildSimpleWordDocument, $dopBase, $u16, $u32): void {
        $dop = substr_replace($dopBase(), $u16(0xffff), 8, 2);
        $wordDocument = $buildSimpleWordDocument("DOP Copts60 review packet\r");
        $wordDocument = substr_replace($wordDocument, $u32(0), 0x0192, 4);
        $wordDocument = substr_replace($wordDocument, $u32(strlen($dop)), 0x0196, 4);

        $result = (new LegacyDocReader())->readBytes($buildCfb([
            'WordDocument' => $wordDocument,
            '0Table' => $dop,
        ]));
        $documentProperties = $result['documentProperties'];
        $metadata = $result['metadata'];
        $blocks = (new WordPressBlockWriter())->write($result['document']);

        $expectedCompatibilityOptions = [
            'no-tab-hanging-indent',
            'no-space-raise-lower',
            'suppress-space-before-after-page-break',
            'wrap-trailing-spaces',
            'print-color-as-black',
            'no-column-balance',
            'convert-mail-merge-escapes',
            'suppress-top-spacing',
            'single-border-for-contiguous-cells',
            'show-breaks-in-frames',
            'swap-borders-facing-pages',
            'leave-backslash-alone',
            'expand-shift-return',
            'underline-trailing-spaces',
            'balance-single-byte-double-byte-width',
        ];

        $t->same(0xffff, $documentProperties['compatibilityOptions']);
        $t->same($expectedCompatibilityOptions, $documentProperties['compatibilityOptionFlags']);
        $t->same($expectedCompatibilityOptions, $metadata['documentCompatibilityOptionFlags']);
        $t->same($documentProperties, $result['document']->attr('documentProperties'));
        $t->same($documentProperties, $result['document']->attr('meta')['documentProperties']);
        $t->contains('<p>DOP Copts60 review packet</p>', $blocks);
        $t->true(!str_contains($blocks, 'single-border-for-contiguous-cells'));
    },
    'rejects malformed legacy DOC DOP document properties before exposing metadata' => static function (TestRunner $t) use ($buildCfb, $buildSimpleWordDocument, $dopBase, $u16, $u32): void {
        $buildDocBytes = static function (string $dop) use ($buildCfb, $buildSimpleWordDocument, $u32): string {
            $wordDocument = $buildSimpleWordDocument("Malformed DOP metadata packet\r");
            $wordDocument = substr_replace($wordDocument, $u32(0), 0x0192, 4);
            $wordDocument = substr_replace($wordDocument, $u32(strlen($dop)), 0x0196, 4);

            return $buildCfb([
                'WordDocument' => $wordDocument,
                '0Table' => $dop,
            ]);
        };

        $valid = $dopBase();
        foreach ([
            'truncated base' => substr($valid, 0, 83),
            'nonzero wSpare2' => substr_replace($valid, $u16(1), 18, 2),
            'reserved form flag' => substr_replace($valid, $u32(1 << 30), 52, 4),
            'negative revision count' => substr_replace($valid, $u16(0xffff), 32, 2),
        ] as $dop) {
            $t->throws(\RuntimeException::class, static fn (): array => (new LegacyDocReader())->readBytes($buildDocBytes($dop)));
        }

        $missingTableWordDocument = $buildSimpleWordDocument("Missing DOP table stream packet\r");
        $missingTableWordDocument = substr_replace($missingTableWordDocument, $u32(0), 0x0192, 4);
        $missingTableWordDocument = substr_replace($missingTableWordDocument, $u32(strlen($valid)), 0x0196, 4);
        $t->throws(\RuntimeException::class, static fn (): array => (new LegacyDocReader())->readBytes($buildCfb([
            'WordDocument' => $missingTableWordDocument,
        ])));
    },
    'decodes legacy DOC LPSTR metadata using the property-set code page' => static function (TestRunner $t) use ($buildCfb, $buildSimpleWordDocument, $typedPropertySet, $typedLpstrBytes, $typedI2): void {
        $titleBytes = hex2bin('c8ecefeef0f220eef2e7fbe2eee2');
        $creatorBytes = hex2bin('d0e5e4e0eaf2eef0');
        if (!is_string($titleBytes) || !is_string($creatorBytes)) {
            throw new RuntimeException('Unable to build Windows-1251 metadata fixture');
        }

        $docBytes = $buildCfb([
            'WordDocument' => $buildSimpleWordDocument("Codepage metadata review packet\r"),
            "\x05SummaryInformation" => $typedPropertySet([
                1 => $typedI2(1251),
                2 => $typedLpstrBytes($titleBytes),
                4 => $typedLpstrBytes($creatorBytes),
            ]),
            "\x05DocumentSummaryInformation" => $typedPropertySet([
                1 => $typedI2(65001),
                2 => $typedLpstrBytes('Очередь импорта'),
            ]),
        ]);

        $result = (new LegacyDocReader())->readBytes($docBytes);
        $metadata = $result['metadata'];

        $t->same('Импорт отзывов', $metadata['title']);
        $t->same('Редактор', $metadata['creator']);
        $t->same('Очередь импорта', $metadata['category']);
        $t->same('Импорт отзывов', $result['document']->attr('meta')['title']);
    },
    'extracts legacy DOC SttbfAssoc associated strings as fallback review metadata' => static function (TestRunner $t) use ($buildCfb, $buildSimpleWordDocument, $sttbfAssoc, $propertySet, $u32): void {
        $associatedStringsTable = $sttbfAssoc([
            1 => 'C:\Templates\migration.dot',
            2 => 'Associated title should not override OLE',
            3 => 'Associated subject',
            4 => 'legacy,review,mailmerge',
            6 => 'Associated author should not override OLE',
            7 => 'Assoc Editor',
            8 => 'C:\Data\mailmerge.csv',
            9 => 'C:\Data\header.doc',
            17 => 'secret-pass',
        ]);
        $wordDocument = $buildSimpleWordDocument("Associated metadata packet\r");
        $wordDocument = substr_replace($wordDocument, $u32(0), 0x019a, 4);
        $wordDocument = substr_replace($wordDocument, $u32(strlen($associatedStringsTable)), 0x019e, 4);
        $docBytes = $buildCfb([
            'WordDocument' => $wordDocument,
            '0Table' => $associatedStringsTable,
            "\x05SummaryInformation" => $propertySet([
                2 => 'OLE title wins',
                4 => 'OLE Author wins',
            ]),
        ]);

        $result = (new LegacyDocReader())->readBytes($docBytes);
        $metadata = $result['metadata'];
        $associatedStrings = $result['associatedStrings'];
        $blocks = (new WordPressBlockWriter())->write($result['document']);

        $t->same(9, count($associatedStrings));
        $t->same(9, $metadata['associatedStringCount']);
        $t->same($associatedStrings, $metadata['associatedStrings']);
        $t->same($associatedStrings, $result['document']->attr('associatedStrings'));
        $t->same('OLE title wins', $metadata['title']);
        $t->same('OLE Author wins', $metadata['creator']);
        $t->same('Associated subject', $metadata['subject']);
        $t->same('legacy,review,mailmerge', $metadata['keywords']);
        $t->same('Assoc Editor', $metadata['lastModifiedBy']);
        $t->same('C:\Templates\migration.dot', $metadata['associatedTemplatePath']);
        $t->same('C:\Data\mailmerge.csv', $metadata['mailMergeDataSource']);
        $t->same('C:\Data\header.doc', $metadata['mailMergeHeaderDocument']);
        $t->same(true, $metadata['hasWriteReservationPassword']);
        $t->same(11, $metadata['writeReservationPasswordCharacterCount']);
        $t->same('associatedTemplatePath', $associatedStrings[0]['role']);
        $t->same('C:\Templates\migration.dot', $associatedStrings[0]['value']);
        $t->same('writeReservationPassword', $associatedStrings[8]['role']);
        $t->same(true, $associatedStrings[8]['redacted']);
        $t->same(11, $associatedStrings[8]['characterCount']);
        $t->true(!array_key_exists('value', $associatedStrings[8]));
        $t->contains('<p>Associated metadata packet</p>', $blocks);
        $t->true(!str_contains($blocks, 'secret-pass'));
        $t->true(!str_contains($blocks, 'mailmerge.csv'));
    },
    'rejects malformed legacy DOC SttbfAssoc tables before exposing associated metadata' => static function (TestRunner $t) use ($buildCfb, $buildSimpleWordDocument, $sttbfAssoc, $u16, $u32): void {
        $buildDocBytes = static function (string $associatedStringsTable) use ($buildCfb, $buildSimpleWordDocument, $u32): string {
            $wordDocument = $buildSimpleWordDocument("Malformed associated metadata packet\r");
            $wordDocument = substr_replace($wordDocument, $u32(0), 0x019a, 4);
            $wordDocument = substr_replace($wordDocument, $u32(strlen($associatedStringsTable)), 0x019e, 4);

            return $buildCfb([
                'WordDocument' => $wordDocument,
                '0Table' => $associatedStringsTable,
            ]);
        };

        $valid = $sttbfAssoc([
            2 => 'Associated title',
            17 => 'review-lock',
        ]);
        foreach ([
            'wrong count' => substr_replace($valid, $u16(17), 2, 2),
            'extra data' => substr_replace($valid, $u16(2), 4, 2),
            'long password' => $sttbfAssoc([17 => str_repeat('x', 16)]),
            'trailing bytes' => $valid . "\0\0",
        ] as $associatedStringsTable) {
            $t->throws(\RuntimeException::class, static fn (): array => (new LegacyDocReader())->readBytes($buildDocBytes($associatedStringsTable)));
        }
    },
    'extracts legacy DOC StwUser document variables as metadata-only review data' => static function (TestRunner $t) use ($buildCfb, $buildExtendedFibWordDocument, $stwUser, $u32): void {
        $documentVariablesTable = $stwUser([
            ['name' => 'MigrationBatch', 'value' => 'legacy-doc-2026'],
            ['name' => 'Review Status', 'value' => 'needs QA'],
            ['name' => 'Sign', 'value' => 'opaque signature bytes'],
        ]);
        $wordDocument = $buildExtendedFibWordDocument("Document variable review packet\r");
        $wordDocument = substr_replace($wordDocument, $u32(0), 0x027a, 4);
        $wordDocument = substr_replace($wordDocument, $u32(strlen($documentVariablesTable)), 0x027e, 4);
        $docBytes = $buildCfb([
            'WordDocument' => $wordDocument,
            '0Table' => $documentVariablesTable,
        ]);

        $result = (new LegacyDocReader())->readBytes($docBytes);
        $metadata = $result['metadata'];
        $documentVariables = $result['documentVariables'];
        $blocks = (new WordPressBlockWriter())->write($result['document']);
        $markdown = (new MarkdownWriter())->write($result['document']);

        $t->same(3, count($documentVariables));
        $t->same(3, $metadata['documentVariableCount']);
        $t->same($documentVariables, $metadata['documentVariables']);
        $t->same($documentVariables, $result['document']->attr('documentVariables'));
        $t->same($documentVariables, $result['document']->attr('meta')['documentVariables']);
        $t->same([
            'MigrationBatch' => 'legacy-doc-2026',
            'Review Status' => 'needs QA',
        ], $metadata['documentVariableValues']);
        $t->same(1, $metadata['documentSignatureVariableCount']);
        $t->same('signature-blob-metadata-only', $metadata['documentSignaturePolicy']);
        $t->same(0, $documentVariables[0]['index']);
        $t->same('MigrationBatch', $documentVariables[0]['name']);
        $t->same('legacy-doc-2026', $documentVariables[0]['value']);
        $t->same(15, $documentVariables[0]['valueCharacterCount']);
        $t->same(32, $documentVariables[0]['valueByteCount']);
        $t->same(1, $documentVariables[1]['index']);
        $t->same('Review Status', $documentVariables[1]['name']);
        $t->same('needs QA', $documentVariables[1]['value']);
        $t->same(8, $documentVariables[1]['valueCharacterCount']);
        $t->same(18, $documentVariables[1]['valueByteCount']);
        $t->same(2, $documentVariables[2]['index']);
        $t->same('Sign', $documentVariables[2]['name']);
        $t->same(true, $documentVariables[2]['signatureVariable']);
        $t->same(true, $documentVariables[2]['redacted']);
        $t->same(false, $documentVariables[2]['canExposeBytes']);
        $t->same(22, $documentVariables[2]['valueCharacterCount']);
        $t->same(46, $documentVariables[2]['valueByteCount']);
        $t->same('signature-blob-metadata-only', $documentVariables[2]['extractionPolicy']);
        $t->true(!array_key_exists('value', $documentVariables[2]));
        $t->contains('<p>Document variable review packet</p>', $blocks);
        $t->contains('Document variable review packet', $markdown);
        $t->true(!str_contains($blocks, 'legacy-doc-2026'));
        $t->true(!str_contains($blocks, 'opaque signature bytes'));
        $t->true(!str_contains($markdown, 'needs QA'));
        $t->true(!str_contains($markdown, 'opaque signature bytes'));
    },
    'extracts legacy DOC SttbSavedBy save history as metadata-only review data' => static function (TestRunner $t) use ($buildCfb, $buildExtendedFibWordDocument, $sttbSavedBy, $u16, $u32): void {
        $saveHistoryTable = $sttbSavedBy([
            ['author' => 'Migration Desk', 'path' => 'C:\Legacy\Drafts\packet-v1.doc'],
            ['author' => 'Review Lead', 'path' => 'D:\Archive\Final import.doc'],
        ]);
        $wordDocument = $buildExtendedFibWordDocument("Save history review packet\r");
        $wordDocument = substr_replace($wordDocument, $u32(0), 0x02d2, 4);
        $wordDocument = substr_replace($wordDocument, $u32(strlen($saveHistoryTable)), 0x02d6, 4);
        $docBytes = $buildCfb([
            'WordDocument' => $wordDocument,
            '0Table' => $saveHistoryTable,
        ]);

        $result = (new LegacyDocReader())->readBytes($docBytes);
        $metadata = $result['metadata'];
        $saveHistory = $result['saveHistory'];
        $blocks = (new WordPressBlockWriter())->write($result['document']);
        $markdown = (new MarkdownWriter())->write($result['document']);

        $t->same(2, count($saveHistory));
        $t->same(2, $metadata['saveHistoryCount']);
        $t->same($saveHistory, $metadata['saveHistory']);
        $t->same($saveHistory, $result['document']->attr('saveHistory'));
        $t->same($saveHistory, $result['document']->attr('meta')['saveHistory']);
        $t->same('Migration Desk', $saveHistory[0]['author']);
        $t->same('C:\Legacy\Drafts\packet-v1.doc', $saveHistory[0]['path']);
        $t->same('packet-v1.doc', $saveHistory[0]['basename']);
        $t->same('Review Lead', $saveHistory[1]['author']);
        $t->same('D:\Archive\Final import.doc', $saveHistory[1]['path']);
        $t->same('Final import.doc', $saveHistory[1]['basename']);
        $t->same('Review Lead', $metadata['latestSavedBy']);
        $t->same('D:\Archive\Final import.doc', $metadata['latestSavedPath']);
        $t->same('Final import.doc', $metadata['latestSavedName']);
        $t->same('SttbSavedBy', $saveHistory[0]['sourceTable']);
        $t->same('earliest-to-latest', $saveHistory[0]['order']);
        $t->contains('<p>Save history review packet</p>', $blocks);
        $t->contains('Save history review packet', $markdown);
        $t->true(!str_contains($blocks, 'packet-v1.doc'));
        $t->true(!str_contains($blocks, 'Review Lead'));
        $t->true(!str_contains($markdown, 'Final import.doc'));

        $buildDocBytes = static function (string $table) use ($buildCfb, $buildExtendedFibWordDocument, $u32): string {
            $wordDocument = $buildExtendedFibWordDocument("Malformed save history packet\r");
            $wordDocument = substr_replace($wordDocument, $u32(0), 0x02d2, 4);
            $wordDocument = substr_replace($wordDocument, $u32(strlen($table)), 0x02d6, 4);

            return $buildCfb([
                'WordDocument' => $wordDocument,
                '0Table' => $table,
            ]);
        };
        foreach ([
            'wrong extended marker' => substr_replace($saveHistoryTable, $u16(0), 0, 2),
            'odd string count' => substr_replace($saveHistoryTable, $u16(3), 2, 2),
            'too many strings' => substr_replace($saveHistoryTable, $u16(22), 2, 2),
            'extra data' => substr_replace($saveHistoryTable, $u16(2), 4, 2),
            'trailing bytes' => $saveHistoryTable . "\0",
        ] as $table) {
            $t->throws(\RuntimeException::class, static fn (): array => (new LegacyDocReader())->readBytes($buildDocBytes($table)));
        }

        $missingTableWordDocument = $buildExtendedFibWordDocument("Missing save history table stream packet\r");
        $missingTableWordDocument = substr_replace($missingTableWordDocument, $u32(0), 0x02d2, 4);
        $missingTableWordDocument = substr_replace($missingTableWordDocument, $u32(strlen($saveHistoryTable)), 0x02d6, 4);
        $t->throws(\RuntimeException::class, static fn (): array => (new LegacyDocReader())->readBytes($buildCfb([
            'WordDocument' => $missingTableWordDocument,
        ])));
    },
    'extracts legacy DOC SttbFnm external file references as metadata-only review data' => static function (TestRunner $t) use ($buildCfb, $buildExtendedFibWordDocument, $sttbFnm, $u16, $u32): void {
        $subdocumentPath = 'C:\Legacy\Subdocs\chapter1.doc';
        $mailMergeSource = 'https://example.test/mailmerge.csv';
        $externalFileTable = $sttbFnm([
            [
                'path' => $subdocumentPath,
                'referenceTypeCode' => 5,
                'documentIndex' => 2,
                'ichRelative' => 10,
                'fnfb' => 0x08,
            ],
            [
                'path' => $mailMergeSource,
                'referenceTypeCode' => 3,
                'documentIndex' => 7,
                'ichRelative' => 0xff,
                'fnfb' => 0x10,
            ],
        ]);
        $wordDocument = $buildExtendedFibWordDocument("External filename review packet\r");
        $wordDocument = substr_replace($wordDocument, $u32(0), 0x02da, 4);
        $wordDocument = substr_replace($wordDocument, $u32(strlen($externalFileTable)), 0x02de, 4);
        $docBytes = $buildCfb([
            'WordDocument' => $wordDocument,
            '0Table' => $externalFileTable,
        ]);

        $result = (new LegacyDocReader())->readBytes($docBytes);
        $metadata = $result['metadata'];
        $externalFileReferences = $result['externalFileReferences'];
        $blocks = (new WordPressBlockWriter())->write($result['document']);
        $markdown = (new MarkdownWriter())->write($result['document']);

        $t->same(2, count($externalFileReferences));
        $t->same(2, $metadata['externalFileReferenceCount']);
        $t->same('metadata-only-native-review', $metadata['externalFileReferencePolicy']);
        $t->same($externalFileReferences, $metadata['externalFileReferences']);
        $t->same($externalFileReferences, $result['document']->attr('externalFileReferences'));
        $t->same($externalFileReferences, $result['document']->attr('meta')['externalFileReferences']);
        $t->same(0, $externalFileReferences[0]['index']);
        $t->same('SttbFnm', $externalFileReferences[0]['sourceTable']);
        $t->same($subdocumentPath, $externalFileReferences[0]['path']);
        $t->same(strlen($subdocumentPath), $externalFileReferences[0]['pathCharacterCount']);
        $t->same('chapter1.doc', $externalFileReferences[0]['basename']);
        $t->same(0x0025, $externalFileReferences[0]['fnpi']);
        $t->same(5, $externalFileReferences[0]['referenceTypeCode']);
        $t->same('subdocument', $externalFileReferences[0]['referenceType']);
        $t->same(2, $externalFileReferences[0]['documentIndex']);
        $t->same(10, $externalFileReferences[0]['ichRelative']);
        $t->same('Subdocs\chapter1.doc', $externalFileReferences[0]['relativePath']);
        $t->same(0x08, $externalFileReferences[0]['fnfb']);
        $t->same(['ntfs'], $externalFileReferences[0]['fileSystemFlags']);
        $t->same('ntfs', $externalFileReferences[0]['fileSystem']);
        $t->same(false, $externalFileReferences[0]['canExposeBytes']);
        $t->same('metadata-only-native-review', $externalFileReferences[0]['extractionPolicy']);
        $t->same(1, $externalFileReferences[1]['index']);
        $t->same($mailMergeSource, $externalFileReferences[1]['path']);
        $t->same('mailmerge.csv', $externalFileReferences[1]['basename']);
        $t->same(0x0073, $externalFileReferences[1]['fnpi']);
        $t->same(3, $externalFileReferences[1]['referenceTypeCode']);
        $t->same('mail-merge-data-source', $externalFileReferences[1]['referenceType']);
        $t->same(7, $externalFileReferences[1]['documentIndex']);
        $t->same(0xff, $externalFileReferences[1]['ichRelative']);
        $t->true(!array_key_exists('relativePath', $externalFileReferences[1]));
        $t->same(0x10, $externalFileReferences[1]['fnfb']);
        $t->same(['non-file-system'], $externalFileReferences[1]['fileSystemFlags']);
        $t->same('non-file-system', $externalFileReferences[1]['fileSystem']);
        $t->contains('<p>External filename review packet</p>', $blocks);
        $t->contains('External filename review packet', $markdown);
        $t->true(!str_contains($blocks, 'chapter1.doc'));
        $t->true(!str_contains($blocks, 'mailmerge.csv'));
        $t->true(!str_contains($markdown, 'Subdocs'));

        $buildDocBytes = static function (string $table) use ($buildCfb, $buildExtendedFibWordDocument, $u32): string {
            $wordDocument = $buildExtendedFibWordDocument("Malformed external filename packet\r");
            $wordDocument = substr_replace($wordDocument, $u32(0), 0x02da, 4);
            $wordDocument = substr_replace($wordDocument, $u32(strlen($table)), 0x02de, 4);

            return $buildCfb([
                'WordDocument' => $wordDocument,
                '0Table' => $table,
            ]);
        };
        $emptyFilenameTable = $u16(0xffff) . $u16(1) . $u16(8) . $u16(0) . str_repeat("\0", 8);
        foreach ([
            'wrong extended marker' => substr_replace($externalFileTable, $u16(0), 0, 2),
            'wrong extra bytes' => substr_replace($externalFileTable, $u16(0), 4, 2),
            'empty filename' => $emptyFilenameTable,
            'relative path offset outside filename' => $sttbFnm([[
                'path' => 'short.doc',
                'referenceTypeCode' => 5,
                'documentIndex' => 1,
                'ichRelative' => 20,
                'fnfb' => 0x01,
            ]]),
            'invalid reference type' => $sttbFnm([[
                'path' => 'C:\Legacy\bad.doc',
                'referenceTypeCode' => 2,
                'documentIndex' => 1,
                'ichRelative' => 0xff,
                'fnfb' => 0x08,
            ]]),
            'invalid document identifier' => $sttbFnm([[
                'path' => 'C:\Legacy\bad.doc',
                'referenceTypeCode' => 5,
                'documentIndex' => 0x0fff,
                'ichRelative' => 0xff,
                'fnfb' => 0x08,
            ]]),
            'non-file-system combined with FAT' => $sttbFnm([[
                'path' => 'https://example.test/bad.doc',
                'referenceTypeCode' => 3,
                'documentIndex' => 1,
                'ichRelative' => 0xff,
                'fnfb' => 0x11,
            ]]),
            'truncated FNIF' => substr($externalFileTable, 0, -1),
            'trailing bytes' => $externalFileTable . "\0",
        ] as $table) {
            $t->throws(\RuntimeException::class, static fn (): array => (new LegacyDocReader())->readBytes($buildDocBytes($table)));
        }

        $missingTableWordDocument = $buildExtendedFibWordDocument("Missing external filename table stream packet\r");
        $missingTableWordDocument = substr_replace($missingTableWordDocument, $u32(0), 0x02da, 4);
        $missingTableWordDocument = substr_replace($missingTableWordDocument, $u32(strlen($externalFileTable)), 0x02de, 4);
        $t->throws(\RuntimeException::class, static fn (): array => (new LegacyDocReader())->readBytes($buildCfb([
            'WordDocument' => $missingTableWordDocument,
        ])));
    },
    'relates legacy DOC include fields to matching SttbFnm external filename records' => static function (TestRunner $t) use ($buildCfb, $buildExtendedFibWordDocument, $sttbFnm, $u32): void {
        $fieldBegin = "\x13";
        $fieldSeparator = "\x14";
        $fieldEnd = "\x15";
        $text = 'Linked '
            . $fieldBegin . ' INCLUDETEXT "Subdocs\chapter1.doc" \! '
            . $fieldSeparator . 'chapter text' . $fieldEnd
            . ' and '
            . $fieldBegin . ' INCLUDEPICTURE "C:/Legacy/Figures/chart.png" \d '
            . $fieldSeparator . 'chart' . $fieldEnd
            . ".\r";
        $externalFileTable = $sttbFnm([
            [
                'path' => 'C:\Legacy\Subdocs\chapter1.doc',
                'referenceTypeCode' => 5,
                'documentIndex' => 2,
                'ichRelative' => 10,
                'fnfb' => 0x08,
            ],
            [
                'path' => 'C:\Legacy\Figures\chart.png',
                'referenceTypeCode' => 5,
                'documentIndex' => 3,
                'ichRelative' => 10,
                'fnfb' => 0x09,
            ],
        ]);
        $wordDocument = $buildExtendedFibWordDocument($text);
        $wordDocument = substr_replace($wordDocument, $u32(0), 0x02da, 4);
        $wordDocument = substr_replace($wordDocument, $u32(strlen($externalFileTable)), 0x02de, 4);
        $result = (new LegacyDocReader())->readBytes($buildCfb([
            'WordDocument' => $wordDocument,
            '0Table' => $externalFileTable,
        ]));
        $paragraph = $result['document']->children[0];
        $includedText = $paragraph->children[1];
        $includedPicture = $paragraph->children[3];
        $textAttributes = $includedText->attr('attributes');
        $pictureAttributes = $includedPicture->attr('attributes');
        $blocks = (new WordPressBlockWriter())->write($result['document']);
        $markdown = (new MarkdownWriter())->write($result['document']);

        $t->same('includetext', $textAttributes['data-legacy-doc-field']);
        $t->same('Subdocs\chapter1.doc', $textAttributes['data-legacy-doc-include-source']);
        $t->same('0', $textAttributes['data-legacy-doc-include-external-reference-index']);
        $t->same('relative-path', $textAttributes['data-legacy-doc-include-external-reference-match']);
        $t->same('subdocument', $textAttributes['data-legacy-doc-include-external-reference-type']);
        $t->same('2', $textAttributes['data-legacy-doc-include-external-reference-document-index']);
        $t->same('ntfs', $textAttributes['data-legacy-doc-include-external-reference-file-system']);
        $t->same('metadata-only-native-review', $textAttributes['data-legacy-doc-include-external-reference-policy']);
        $t->same('false', $textAttributes['data-legacy-doc-include-external-reference-can-expose-bytes']);
        $t->same('includepicture', $pictureAttributes['data-legacy-doc-field']);
        $t->same('C:/Legacy/Figures/chart.png', $pictureAttributes['data-legacy-doc-include-source']);
        $t->same('1', $pictureAttributes['data-legacy-doc-include-external-reference-index']);
        $t->same('path', $pictureAttributes['data-legacy-doc-include-external-reference-match']);
        $t->same('subdocument', $pictureAttributes['data-legacy-doc-include-external-reference-type']);
        $t->same('3', $pictureAttributes['data-legacy-doc-include-external-reference-document-index']);
        $t->same('fat+ntfs', $pictureAttributes['data-legacy-doc-include-external-reference-file-system']);
        $t->same('metadata-only-native-review', $pictureAttributes['data-legacy-doc-include-external-reference-policy']);
        $t->same('false', $pictureAttributes['data-legacy-doc-include-external-reference-can-expose-bytes']);
        $t->same(2, $result['metadata']['externalFileReferenceCount']);
        $t->contains('data-legacy-doc-include-external-reference-index="0"', $blocks);
        $t->contains('data-legacy-doc-include-external-reference-match="relative-path"', $blocks);
        $t->contains('data-legacy-doc-include-external-reference-file-system="fat+ntfs"', $blocks);
        $t->contains('data-legacy-doc-include-external-reference-index="0"', $markdown);
        $t->true(!str_contains($blocks, 'C:\Legacy\Subdocs\chapter1.doc'));
    },
    'extracts legacy DOC RouteSlip routing metadata as metadata-only review data' => static function (TestRunner $t) use ($buildCfb, $buildExtendedFibWordDocument, $routeSlip, $u16, $u32): void {
        $routeSlipTable = $routeSlip([
            [
                'entryId' => "entry-id-001",
                'name' => 'Mira Reviewer',
            ],
            [
                'entryId' => "entry-id-002",
                'name' => 'Archive Owner',
            ],
        ], [
            'routed' => true,
            'returnOriginal' => true,
            'trackStatus' => true,
            'dirty' => false,
            'protect' => 2,
            'stage' => 1,
            'deliveryOption' => 1,
            'subject' => 'Legacy DOC packet',
            'message' => 'Please review before import.',
            'status' => 'Awaiting legal signoff',
            'title' => 'Route packet 42',
        ]);
        $wordDocument = $buildExtendedFibWordDocument("Route slip review packet\r");
        $wordDocument = substr_replace($wordDocument, $u32(0), 0x02ca, 4);
        $wordDocument = substr_replace($wordDocument, $u32(strlen($routeSlipTable)), 0x02ce, 4);
        $docBytes = $buildCfb([
            'WordDocument' => $wordDocument,
            '0Table' => $routeSlipTable,
        ]);

        $result = (new LegacyDocReader())->readBytes($docBytes);
        $metadata = $result['metadata'];
        $routeSlip = $result['routeSlip'];
        $blocks = (new WordPressBlockWriter())->write($result['document']);
        $markdown = (new MarkdownWriter())->write($result['document']);

        $t->same(2, $routeSlip['recipientCount']);
        $t->same(2, $metadata['routeSlipRecipientCount']);
        $t->same($routeSlip, $metadata['routeSlip']);
        $t->same($routeSlip, $result['document']->attr('routeSlip'));
        $t->same($routeSlip, $result['document']->attr('meta')['routeSlip']);
        $t->same('RouteSlip', $routeSlip['sourceTable']);
        $t->same('metadata-only-native-review', $routeSlip['extractionPolicy']);
        $t->same('Windows-1252', $routeSlip['sourceEncoding']);
        $t->same(true, $routeSlip['routed']);
        $t->same(true, $routeSlip['returnOriginal']);
        $t->same(true, $routeSlip['trackStatus']);
        $t->same(false, $routeSlip['dirty']);
        $t->same(2, $routeSlip['protect']);
        $t->same(1, $routeSlip['stage']);
        $t->same(1, $routeSlip['deliveryOption']);
        $t->same('parallel', $routeSlip['deliveryMode']);
        $t->same('Legacy DOC packet', $routeSlip['subject']);
        $t->same('Please review before import.', $routeSlip['message']);
        $t->same('Awaiting legal signoff', $routeSlip['status']);
        $t->same('Route packet 42', $routeSlip['title']);
        $t->same(2, count($routeSlip['recipients']));
        $t->same(0, $routeSlip['recipients'][0]['index']);
        $t->same('Mira Reviewer', $routeSlip['recipients'][0]['name']);
        $t->same(12, $routeSlip['recipients'][0]['entryIdByteCount']);
        $t->same('656e7472792d69642d303031', $routeSlip['recipients'][0]['entryIdHex']);
        $t->same('RouteSlipInfo', $routeSlip['recipients'][0]['sourceTable']);
        $t->same('Windows-1252', $routeSlip['recipients'][0]['sourceEncoding']);
        $t->same(1, $routeSlip['recipients'][1]['index']);
        $t->same('Archive Owner', $routeSlip['recipients'][1]['name']);
        $t->same(12, $routeSlip['recipients'][1]['entryIdByteCount']);
        $t->same('656e7472792d69642d303032', $routeSlip['recipients'][1]['entryIdHex']);
        $t->contains('<p>Route slip review packet</p>', $blocks);
        $t->contains('Route slip review packet', $markdown);
        $t->true(!str_contains($blocks, 'Mira Reviewer'));
        $t->true(!str_contains($blocks, 'Please review before import.'));
        $t->true(!str_contains($markdown, 'Archive Owner'));

        $buildDocBytes = static function (string $table) use ($buildCfb, $buildExtendedFibWordDocument, $u32): string {
            $wordDocument = $buildExtendedFibWordDocument("Malformed route slip packet\r");
            $wordDocument = substr_replace($wordDocument, $u32(0), 0x02ca, 4);
            $wordDocument = substr_replace($wordDocument, $u32(strlen($table)), 0x02ce, 4);

            return $buildCfb([
                'WordDocument' => $wordDocument,
                '0Table' => $table,
            ]);
        };
        foreach ([
            'too many recipients' => str_repeat("\0", 14) . $u16(1025) . str_repeat("\0", 8),
            'invalid delivery option' => substr_replace($routeSlipTable, $u16(2), 12, 2),
            'oversized subject' => str_repeat("\0", 16) . $u16(256) . str_repeat('A', 256) . str_repeat("\0", 6),
            'empty recipient name' => str_repeat("\0", 14) . $u16(1) . str_repeat("\0", 8) . $u16(0) . $u16(0),
            'truncated recipient entry id' => str_repeat("\0", 14) . $u16(1) . str_repeat("\0", 8) . $u16(4) . $u16(3) . 'xx',
            'trailing bytes' => $routeSlipTable . "\0",
        ] as $table) {
            $t->throws(\RuntimeException::class, static fn (): array => (new LegacyDocReader())->readBytes($buildDocBytes($table)));
        }

        $missingTableWordDocument = $buildExtendedFibWordDocument("Missing route slip table stream packet\r");
        $missingTableWordDocument = substr_replace($missingTableWordDocument, $u32(0), 0x02ca, 4);
        $missingTableWordDocument = substr_replace($missingTableWordDocument, $u32(strlen($routeSlipTable)), 0x02ce, 4);
        $t->throws(\RuntimeException::class, static fn (): array => (new LegacyDocReader())->readBytes($buildCfb([
            'WordDocument' => $missingTableWordDocument,
        ])));
    },
    'rejects malformed legacy DOC StwUser document variables before exposing metadata' => static function (TestRunner $t) use ($buildCfb, $buildExtendedFibWordDocument, $stwUser, $u16, $u32): void {
        $buildDocBytes = static function (string $documentVariablesTable) use ($buildCfb, $buildExtendedFibWordDocument, $u32): string {
            $wordDocument = $buildExtendedFibWordDocument("Malformed document variables packet\r");
            $wordDocument = substr_replace($wordDocument, $u32(0), 0x027a, 4);
            $wordDocument = substr_replace($wordDocument, $u32(strlen($documentVariablesTable)), 0x027e, 4);

            return $buildCfb([
                'WordDocument' => $wordDocument,
                '0Table' => $documentVariablesTable,
            ]);
        };

        $valid = $stwUser([
            ['name' => 'ReviewStatus', 'value' => 'ready'],
            ['name' => 'Owner', 'value' => 'migration desk'],
        ]);
        foreach ([
            'wrong extended marker' => substr_replace($valid, $u16(0), 0, 2),
            'wrong extra-data size' => substr_replace($valid, $u16(2), 4, 2),
            'duplicate variable names' => $stwUser([
                ['name' => 'ReviewStatus', 'value' => 'ready'],
                ['name' => 'reviewstatus', 'value' => 'duplicate'],
            ]),
            'truncated value' => substr($valid, 0, -1),
            'trailing bytes' => $valid . "\0",
        ] as $documentVariablesTable) {
            $t->throws(\RuntimeException::class, static fn (): array => (new LegacyDocReader())->readBytes($buildDocBytes($documentVariablesTable)));
        }

        $missingTableWordDocument = $buildExtendedFibWordDocument("Missing document variables table stream packet\r");
        $missingTableWordDocument = substr_replace($missingTableWordDocument, $u32(0), 0x027a, 4);
        $missingTableWordDocument = substr_replace($missingTableWordDocument, $u32(strlen($valid)), 0x027e, 4);
        $t->throws(\RuntimeException::class, static fn (): array => (new LegacyDocReader())->readBytes($buildCfb([
            'WordDocument' => $missingTableWordDocument,
        ])));
    },
    'extracts legacy DOC DocumentSummaryInformation counters and booleans' => static function (TestRunner $t) use ($buildCfb, $buildSimpleWordDocument, $typedPropertySet, $typedLpstr, $typedI4, $typedBool): void {
        $docBytes = $buildCfb([
            'WordDocument' => $buildSimpleWordDocument("Document summary review packet\r"),
            "\x05DocumentSummaryInformation" => $typedPropertySet([
                2 => $typedLpstr('Import queue'),
                3 => $typedLpstr('A4 Paper (210x297 mm)'),
                4 => $typedI4(8192),
                5 => $typedI4(44),
                6 => $typedI4(12),
                7 => $typedI4(0),
                8 => $typedI4(3),
                9 => $typedI4(1),
                10 => $typedI4(2),
                11 => $typedBool(false),
                14 => $typedLpstr('Review Manager'),
                15 => $typedLpstr('Example Press'),
                16 => $typedBool(true),
                17 => $typedI4(2400),
                19 => $typedBool(false),
                22 => $typedBool(true),
                23 => $typedI4(0x00100000),
                26 => $typedLpstr('migration-review'),
                27 => $typedLpstr('draft-import'),
                28 => $typedLpstr('en-US'),
                29 => $typedLpstr('16.0'),
            ]),
        ]);

        $metadata = (new LegacyDocReader())->readBytes($docBytes)['metadata'];

        $t->same('Import queue', $metadata['category']);
        $t->same('A4 Paper (210x297 mm)', $metadata['presentationFormat']);
        $t->same(8192, $metadata['byteCount']);
        $t->same(44, $metadata['lineCount']);
        $t->same(12, $metadata['paragraphCount']);
        $t->same(0, $metadata['slideCount']);
        $t->same(3, $metadata['noteCount']);
        $t->same(1, $metadata['hiddenSlideCount']);
        $t->same(2, $metadata['multimediaClipCount']);
        $t->same(false, $metadata['scale']);
        $t->same('Review Manager', $metadata['manager']);
        $t->same('Example Press', $metadata['company']);
        $t->same(true, $metadata['linksDirty']);
        $t->same(2400, $metadata['charactersWithSpaces']);
        $t->same(false, $metadata['sharedDocument']);
        $t->same(true, $metadata['hyperlinksChanged']);
        $t->same(0x00100000, $metadata['applicationVersion']);
        $t->same('migration-review', $metadata['contentType']);
        $t->same('draft-import', $metadata['contentStatus']);
        $t->same('en-US', $metadata['language']);
        $t->same('16.0', $metadata['documentVersion']);
    },
    'extracts legacy DOC DocumentSummaryInformation heading pairs and document parts' => static function (TestRunner $t) use ($buildCfb, $buildSimpleWordDocument, $typedPropertySet, $typedVectorVariant, $typedVariantLpstr, $typedVariantI4, $typedVectorLpstr): void {
        $docBytes = $buildCfb([
            'WordDocument' => $buildSimpleWordDocument("Document part inventory\r"),
            "\x05DocumentSummaryInformation" => $typedPropertySet([
                12 => $typedVectorVariant([
                    $typedVariantLpstr('Sections'),
                    $typedVariantI4(2),
                    $typedVariantLpstr('Appendices'),
                    $typedVariantI4(1),
                ]),
                13 => $typedVectorLpstr([
                    'Overview',
                    'Migration notes',
                    'Review appendix',
                ]),
            ]),
        ]);

        $result = (new LegacyDocReader())->readBytes($docBytes);
        $metadata = $result['metadata'];

        $t->same([
            'Overview',
            'Migration notes',
            'Review appendix',
        ], $metadata['documentParts']);
        $t->same([
            [
                'heading' => 'Sections',
                'count' => 2,
                'parts' => ['Overview', 'Migration notes'],
            ],
            [
                'heading' => 'Appendices',
                'count' => 1,
                'parts' => ['Review appendix'],
            ],
        ], $metadata['headingPairs']);
        $t->same($metadata['headingPairs'], $result['document']->attr('meta')['headingPairs']);
    },
    'extracts padded legacy DOC vector property-set values for document headings' => static function (TestRunner $t) use ($buildCfb, $buildSimpleWordDocument, $typedPropertySet, $typedVariantI4, $utf16le): void {
        $padDword = static fn (string $bytes): string => str_pad($bytes, (int) (ceil(strlen($bytes) / 4) * 4), "\0");
        $typedPaddedVariantLpstr = static function (string $value) use ($padDword): string {
            $bytes = $value . "\0";

            return $padDword(pack('v', 0x001e) . "\0\0" . pack('V', strlen($bytes)) . $bytes);
        };
        $typedPaddedVectorVariant = static function (array $variants) use ($padDword): string {
            return $padDword(pack('v', 0x100c) . "\0\0" . pack('V', count($variants)) . implode('', $variants));
        };
        $typedPaddedVectorLpwstr = static function (array $values) use ($padDword, $utf16le): string {
            $payload = pack('V', count($values));
            foreach ($values as $value) {
                $bytes = $utf16le((string) $value . "\0");
                $payload .= $padDword(pack('V', intdiv(strlen($bytes), 2)) . $bytes);
            }

            return $padDword(pack('v', 0x101f) . "\0\0" . $payload);
        };

        $docBytes = $buildCfb([
            'WordDocument' => $buildSimpleWordDocument("Padded document part inventory\r"),
            "\x05DocumentSummaryInformation" => $typedPropertySet([
                12 => $typedPaddedVectorVariant([
                    $typedPaddedVariantLpstr('Sections'),
                    $typedVariantI4(2),
                    $typedPaddedVariantLpstr('Appendix'),
                    $typedVariantI4(1),
                ]),
                13 => $typedPaddedVectorLpwstr([
                    'Intro',
                    'QA',
                    'Appendix',
                ]),
            ]),
        ]);

        $result = (new LegacyDocReader())->readBytes($docBytes);
        $metadata = $result['metadata'];

        $t->same(['Intro', 'QA', 'Appendix'], $metadata['documentParts']);
        $t->same([
            [
                'heading' => 'Sections',
                'count' => 2,
                'parts' => ['Intro', 'QA'],
            ],
            [
                'heading' => 'Appendix',
                'count' => 1,
                'parts' => ['Appendix'],
            ],
        ], $metadata['headingPairs']);
        $t->same($metadata['documentParts'], $result['document']->attr('meta')['documentParts']);
        $t->same($metadata['headingPairs'], $result['document']->attr('meta')['headingPairs']);
    },
    'extracts legacy DOC user-defined custom document properties from OLE dictionaries' => static function (TestRunner $t) use ($buildCfb, $buildSimpleWordDocument, $typedPropertySetStream, $typedDictionary, $typedLpstr, $typedI2, $typedI4, $typedBool, $typedFiletime): void {
        $docSummaryFmtid = hex2bin('02d5cdd59c2e1b10939708002b2cf9ae');
        $userDefinedFmtid = hex2bin('05d5cdd59c2e1b10939708002b2cf9ae');
        if (!is_string($docSummaryFmtid) || !is_string($userDefinedFmtid)) {
            throw new RuntimeException('Unable to build OLE property-set FMTID fixtures');
        }

        $docBytes = $buildCfb([
            'WordDocument' => $buildSimpleWordDocument("Custom metadata review packet\r"),
            "\x05DocumentSummaryInformation" => $typedPropertySetStream([
                [
                    'fmtid' => $docSummaryFmtid,
                    'properties' => [
                        2 => $typedLpstr('Import queue'),
                        15 => $typedLpstr('Example Press'),
                    ],
                ],
                [
                    'fmtid' => $userDefinedFmtid,
                    'properties' => [
                        0 => $typedDictionary([
                            2 => 'MigrationBatch',
                            3 => 'Needs Review',
                            4 => 'Source Id',
                            5 => 'Review Timestamp',
                        ]),
                        1 => $typedI2(1252),
                        2 => $typedLpstr('batch-42'),
                        3 => $typedBool(true),
                        4 => $typedI4(9876),
                        5 => $typedFiletime('2024-03-04T05:06:07Z'),
                    ],
                ],
            ]),
        ]);

        $result = (new LegacyDocReader())->readBytes($docBytes);
        $metadata = $result['metadata'];

        $t->same('Import queue', $metadata['category']);
        $t->same('Example Press', $metadata['company']);
        $t->same([
            'MigrationBatch' => 'batch-42',
            'Needs Review' => true,
            'Source Id' => 9876,
            'Review Timestamp' => '2024-03-04T05:06:07Z',
        ], $metadata['customProperties']);
        $t->same($metadata['customProperties'], $result['document']->attr('meta')['customProperties']);
    },
    'extracts legacy DOC unsigned integer 64-bit and CLSID OLE property scalars' => static function (TestRunner $t) use ($buildCfb, $buildSimpleWordDocument, $typedPropertySet, $typedPropertySetStream, $typedDictionary, $typedI2, $typedUi2, $typedUi4, $typedI8Parts, $typedUi8Parts, $typedClsid): void {
        $docSummaryFmtid = hex2bin('02d5cdd59c2e1b10939708002b2cf9ae');
        $userDefinedFmtid = hex2bin('05d5cdd59c2e1b10939708002b2cf9ae');
        if (!is_string($docSummaryFmtid) || !is_string($userDefinedFmtid)) {
            throw new RuntimeException('Unable to build OLE property-set FMTID fixtures');
        }

        $sourceGuid = 'f0e1d2c3-b4a5-9687-1020-304050607080';
        $archiveBytes = 6000000000;
        $docBytes = $buildCfb([
            'WordDocument' => $buildSimpleWordDocument("Scalar metadata review packet\r"),
            "\x05SummaryInformation" => $typedPropertySet([
                14 => $typedUi4(2147483650),
                19 => $typedUi4(0x0000000d),
            ]),
            "\x05DocumentSummaryInformation" => $typedPropertySetStream([
                [
                    'fmtid' => $docSummaryFmtid,
                    'properties' => [
                        4 => $typedUi4(4294967295),
                    ],
                ],
                [
                    'fmtid' => $userDefinedFmtid,
                    'properties' => [
                        0 => $typedDictionary([
                            2 => 'Reviewer Tier',
                            3 => 'Archive Revision',
                            4 => 'Signed Delta',
                            5 => 'Archive Bytes',
                            6 => 'Source Guid',
                            7 => 'Max Unsigned',
                        ]),
                        1 => $typedI2(1252),
                        2 => $typedUi2(65535),
                        3 => $typedUi4(4000000000),
                        4 => $typedI8Parts(0xffffffd6, 0xffffffff),
                        5 => $typedUi8Parts(1705032704, 1),
                        6 => $typedClsid($sourceGuid),
                        7 => $typedUi8Parts(0xffffffff, 0xffffffff),
                    ],
                ],
            ]),
        ]);

        $result = (new LegacyDocReader())->readBytes($docBytes);
        $metadata = $result['metadata'];

        $t->same(2147483650, $metadata['pageCount']);
        $t->same(0x0000000d, $metadata['documentSecurity']);
        $t->same(['passwordProtected', 'readOnlyEnforced', 'lockedForAnnotations'], $metadata['documentSecurityFlags']);
        $t->same(4294967295, $metadata['byteCount']);
        $t->same([
            'Reviewer Tier' => 65535,
            'Archive Revision' => 4000000000,
            'Signed Delta' => -42,
            'Archive Bytes' => $archiveBytes,
            'Source Guid' => $sourceGuid,
            'Max Unsigned' => '18446744073709551615',
        ], $metadata['customProperties']);
        $t->same($metadata['customProperties'], $result['document']->attr('meta')['customProperties']);
    },
    'extracts legacy DOC floating currency and automation-date OLE property scalars' => static function (TestRunner $t) use ($buildCfb, $buildSimpleWordDocument, $typedPropertySetStream, $typedDictionary, $typedI2, $typedR4, $typedR8, $typedCurrency, $typedOleDate): void {
        $userDefinedFmtid = hex2bin('05d5cdd59c2e1b10939708002b2cf9ae');
        if (!is_string($userDefinedFmtid)) {
            throw new RuntimeException('Unable to build OLE property-set FMTID fixtures');
        }

        $docBytes = $buildCfb([
            'WordDocument' => $buildSimpleWordDocument("Floating custom metadata review packet\r"),
            "\x05DocumentSummaryInformation" => $typedPropertySetStream([
                [
                    'fmtid' => $userDefinedFmtid,
                    'properties' => [
                        0 => $typedDictionary([
                            2 => 'Review Weight',
                            3 => 'Confidence Score',
                            4 => 'Invoice Total',
                            5 => 'Review Date',
                        ]),
                        1 => $typedI2(1252),
                        2 => $typedR4(1.25),
                        3 => $typedR8(0.875),
                        4 => $typedCurrency(12345678),
                        5 => $typedOleDate(45309.5),
                    ],
                ],
            ]),
        ]);

        $result = (new LegacyDocReader())->readBytes($docBytes);
        $metadata = $result['metadata'];

        $t->same([
            'Review Weight' => 1.25,
            'Confidence Score' => 0.875,
            'Invoice Total' => '1234.5678',
            'Review Date' => '2024-01-18T12:00:00Z',
        ], $metadata['customProperties'] ?? null);
        $t->same($metadata['customProperties'], $result['document']->attr('meta')['customProperties']);
    },
    'preserves legacy DOC inline picture placeholders as review spans without exposing bytes' => static function (TestRunner $t) use ($buildCfb, $buildSimpleWordDocument): void {
        $text = "Inline picture \x01 and \x01 stay review-only\r";
        $firstPictureCp = strpos($text, "\x01");
        $secondPictureCp = strpos($text, "\x01", (int) $firstPictureCp + 1);
        if (!is_int($firstPictureCp) || !is_int($secondPictureCp)) {
            throw new RuntimeException('Unable to locate legacy DOC picture placeholder fixture characters');
        }

        $docBytes = $buildCfb([
            'WordDocument' => $buildSimpleWordDocument($text, 0x0008),
        ]);

        $result = (new LegacyDocReader())->readBytes($docBytes);
        $document = $result['document'];
        $metadata = $result['metadata'];
        $pictureReferences = $result['pictureReferences'] ?? [];
        $markdown = (new MarkdownWriter())->write($document);
        $blocks = (new WordPressBlockWriter())->write($document);

        $t->same(true, $metadata['fibBase']['hasPictures'] ?? null);
        $t->same(2, count($pictureReferences));
        $t->same(2, $metadata['pictureReferenceCount'] ?? null);
        $t->same($pictureReferences, $metadata['pictureReferences'] ?? null);
        $t->same($pictureReferences, $document->attr('pictureReferences'));
        $t->same('inline-picture', $pictureReferences[0]['type']);
        $t->same($firstPictureCp, $pictureReferences[0]['referenceCp']);
        $t->same(0x01, $pictureReferences[0]['characterCode']);
        $t->same(1, $pictureReferences[0]['pictureIndex']);
        $t->same(false, $pictureReferences[0]['canExposeBytes']);
        $t->same('fib-has-pictures', $pictureReferences[0]['source']);
        $t->same('metadata-only-native-review', $pictureReferences[0]['extractionPolicy']);
        $t->same($secondPictureCp, $pictureReferences[1]['referenceCp']);
        $t->same(2, $pictureReferences[1]['pictureIndex']);

        $firstPicture = $document->children[0]->children[1];
        $secondPicture = $document->children[0]->children[3];
        $t->same('span', $firstPicture->type);
        $t->same(['legacy-doc-picture-ref'], $firstPicture->attr('classes'));
        $t->same('1', $firstPicture->attr('attributes')['data-legacy-doc-picture-ref']);
        $t->same((string) $firstPictureCp, $firstPicture->attr('attributes')['data-legacy-doc-picture-reference-cp']);
        $t->same('false', $firstPicture->attr('attributes')['data-legacy-doc-picture-can-expose-bytes']);
        $t->same('fib-has-pictures', $firstPicture->attr('attributes')['data-legacy-doc-picture-source']);
        $t->same('metadata-only-native-review', $firstPicture->attr('attributes')['data-legacy-doc-picture-policy']);
        $t->same('inline picture', $firstPicture->children[0]->attr('text'));
        $t->same('2', $secondPicture->attr('attributes')['data-legacy-doc-picture-ref']);
        $t->same('inline picture', $secondPicture->children[0]->attr('text'));

        $t->contains('[inline picture]{.legacy-doc-picture-ref data-legacy-doc-picture-ref="1"', $markdown);
        $t->contains('<span class="legacy-doc-picture-ref" data-legacy-doc-picture-ref="1" data-legacy-doc-picture-reference-cp="' . (string) $firstPictureCp . '"', $blocks);
        $t->contains('data-legacy-doc-picture-policy="metadata-only-native-review">inline picture</span>', $blocks);
        $t->true(!str_contains($blocks, "\x01"), 'Legacy DOC picture placeholder control characters should not render to WordPress blocks');
    },
    'reports legacy DOC ObjectPool embedded OLE object streams without exposing bytes' => static function (TestRunner $t) use ($buildCfb, $buildSimpleWordDocument, $objectInfo, $ole10NativeStream, $compObjStream): void {
        $nativeData = 'embedded spreadsheet bytes';
        $nativeStreamBytes = $ole10NativeStream(
            'legacy-sheet.xlsx',
            'C:\legacy\legacy-sheet.xlsx',
            'C:\Temp\legacy-sheet.tmp',
            $nativeData
        );
        $docBytes = $buildCfb([
            'WordDocument' => $buildSimpleWordDocument("Embedded object \x01 review packet\r"),
            'ObjectPool/_42/' . "\x03" . 'ObjInfo' => $objectInfo(0x0014),
            'ObjectPool/_42/' . "\x01" . 'CompObj' => $compObjStream(
                'Package',
                'Native',
                'Legacy Package Ω',
                'Excel.Sheet.12'
            ),
            'ObjectPool/_42/' . "\x01" . 'Ole10Native' => $nativeStreamBytes,
            'ObjectPool/_42/' . "\x02" . 'OlePres000' => "presentation preview bytes",
            'ObjectPool/_43/' . "\x03" . 'ObjInfo' => $objectInfo(0x000a),
            'ObjectPool/_43/Workbook' => "private workbook bytes",
        ]);

        $result = (new LegacyDocReader())->readBytes($docBytes);
        $objects = $result['embeddedObjects'];
        $references = $result['embeddedObjectReferences'];
        $document = $result['document'];
        $markdown = (new MarkdownWriter())->write($document);
        $blocks = (new WordPressBlockWriter())->write($document);

        $t->same(2, count($objects));
        $t->same(2, $result['metadata']['embeddedObjectCount']);
        $t->same($objects, $document->attr('embeddedObjects'));
        $t->same('_42', $objects[0]['objectId']);
        $t->same('ObjectPool/_42', $objects[0]['storagePath']);
        $t->same(4, $objects[0]['streamCount']);
        $t->same(true, $objects[0]['hasNativeData']);
        $t->same(true, $objects[0]['hasPresentationData']);
        $t->same(false, $objects[0]['canExposeBytes']);
        $t->same(['Legacy Package Ω'], $objects[0]['compoundObjectDisplayNames']);
        $t->same(['Excel.Sheet.12'], $objects[0]['compoundObjectClipboardFormats']);
        $t->same(strlen($nativeData), $objects[0]['nativeDataBytes']);
        $t->same(['legacy-sheet.xlsx'], $objects[0]['nativeLabels']);
        $t->same(['C:\legacy\legacy-sheet.xlsx'], $objects[0]['nativeSourcePaths']);
        $t->same(['code' => 0x0014, 'name' => 'unicode-text'], $objects[0]['transmissionFormat']);
        $t->same([
            'compound-object',
            'native-data',
            'presentation-data',
            'object-info',
        ], array_map(static fn (array $stream): string => $stream['role'], $objects[0]['streams']));
        $t->same(false, $objects[0]['streams'][0]['compoundObject']['canExposeBytes']);
        $t->same('Package', $objects[0]['streams'][0]['compoundObject']['ansiUserType']);
        $t->same(['kind' => 'registered', 'name' => 'Native'], $objects[0]['streams'][0]['compoundObject']['ansiClipboardFormat']);
        $t->same('Legacy Package Ω', $objects[0]['streams'][0]['compoundObject']['unicodeUserType']);
        $t->same(['kind' => 'registered', 'name' => 'Excel.Sheet.12'], $objects[0]['streams'][0]['compoundObject']['unicodeClipboardFormat']);
        $t->same('Legacy Package Ω', $objects[0]['streams'][0]['compoundObject']['displayName']);
        $t->same(['kind' => 'registered', 'name' => 'Excel.Sheet.12'], $objects[0]['streams'][0]['compoundObject']['clipboardFormat']);
        $t->same(false, $objects[0]['streams'][1]['canExposeBytes']);
        $t->same(strlen($nativeStreamBytes), $objects[0]['streams'][1]['bytes']);
        $t->same([
            'canExposeBytes' => false,
            'declaredPayloadBytes' => strlen($nativeStreamBytes) - 4,
            'flags' => 0x0002,
            'label' => 'legacy-sheet.xlsx',
            'sourcePath' => 'C:\legacy\legacy-sheet.xlsx',
            'temporaryPath' => 'C:\Temp\legacy-sheet.tmp',
            'nativeDataBytes' => strlen($nativeData),
        ], $objects[0]['streams'][1]['oleNative']);

        $t->same('_43', $objects[1]['objectId']);
        $t->same(['code' => 0x000a, 'name' => 'html'], $objects[1]['transmissionFormat']);
        $t->same(['object-info', 'private-data'], array_map(static fn (array $stream): string => $stream['role'], $objects[1]['streams']));
        $t->same(1, count($references));
        $t->same(1, $result['metadata']['embeddedObjectReferenceCount']);
        $t->same($references, $result['metadata']['embeddedObjectReferences']);
        $t->same($references, $document->attr('embeddedObjectReferences'));
        $t->same('embedded-object', $references[0]['type']);
        $t->same(16, $references[0]['referenceCp']);
        $t->same(0x01, $references[0]['characterCode']);
        $t->same(1, $references[0]['objectIndex']);
        $t->same('ObjectPool/_42', $references[0]['storagePath']);
        $t->same('_42', $references[0]['objectId']);
        $t->same('legacy-sheet.xlsx', $references[0]['label']);
        $t->same(false, $references[0]['canExposeBytes']);
        $t->same(true, $references[0]['hasNativeData']);
        $t->same(true, $references[0]['hasPresentationData']);
        $t->same(strlen($nativeData), $references[0]['nativeDataBytes']);
        $objectRef = $document->children[0]->children[1];
        $t->same('span', $objectRef->type);
        $t->same(['legacy-doc-object-ref'], $objectRef->attr('classes'));
        $t->same('1', $objectRef->attr('attributes')['data-legacy-doc-object-ref']);
        $t->same('16', $objectRef->attr('attributes')['data-legacy-doc-object-reference-cp']);
        $t->same('ObjectPool/_42', $objectRef->attr('attributes')['data-legacy-doc-object-storage']);
        $t->same('legacy-sheet.xlsx', $objectRef->attr('attributes')['data-legacy-doc-object-label']);
        $t->same('embedded object: legacy-sheet.xlsx', $objectRef->children[0]->attr('text'));
        $t->contains('[embedded object: legacy-sheet.xlsx]{.legacy-doc-object-ref data-legacy-doc-object-ref="1"', $markdown);
        $t->contains('<p>Embedded object <span class="legacy-doc-object-ref" data-legacy-doc-object-ref="1" data-legacy-doc-object-reference-cp="16"', $blocks);
        $t->contains('data-legacy-doc-object-label="legacy-sheet.xlsx" data-legacy-doc-object-native-data-bytes="26" data-legacy-doc-object-transmission-format="unicode-text" data-legacy-doc-object-has-native-data="true" data-legacy-doc-object-has-presentation-data="true">embedded object: legacy-sheet.xlsx</span> review packet</p>', $blocks);
        $t->true(!str_contains($blocks, "\x01"), 'Legacy DOC object placeholder control character should not render to WordPress blocks');
        $t->true(!str_contains($blocks, $nativeData), 'Embedded OLE native bytes should not render to WordPress blocks');
        $t->true(!str_contains($blocks, 'presentation preview bytes'), 'Embedded OLE presentation bytes should not render to WordPress blocks');
    },
    'maps legacy DOC embedded object placeholders to ordered ObjectPool review spans' => static function (TestRunner $t) use ($buildCfb, $buildSimpleWordDocument, $objectInfo, $ole10NativeStream): void {
        $firstNativeData = 'first opaque object bytes';
        $secondNativeData = 'second opaque object bytes';
        $docBytes = $buildCfb([
            'WordDocument' => $buildSimpleWordDocument("Objects \x01 and \x01 done\r"),
            'ObjectPool/_10/' . "\x03" . 'ObjInfo' => $objectInfo(0x0014),
            'ObjectPool/_10/' . "\x01" . 'Ole10Native' => $ole10NativeStream(
                'first-sheet.xlsx',
                'C:\legacy\first-sheet.xlsx',
                'C:\Temp\first-sheet.tmp',
                $firstNativeData
            ),
            'ObjectPool/_11/' . "\x03" . 'ObjInfo' => $objectInfo(0x0014),
            'ObjectPool/_11/' . "\x01" . 'Ole10Native' => $ole10NativeStream(
                'second-sheet.xlsx',
                'C:\legacy\second-sheet.xlsx',
                'C:\Temp\second-sheet.tmp',
                $secondNativeData
            ),
        ]);

        $result = (new LegacyDocReader())->readBytes($docBytes);
        $references = $result['embeddedObjectReferences'];
        $document = $result['document'];
        $blocks = (new WordPressBlockWriter())->write($document);

        $t->same(2, count($references));
        $t->same(2, $result['metadata']['embeddedObjectReferenceCount']);
        $t->same($references, $document->attr('embeddedObjectReferences'));
        $t->same([8, 14], array_map(static fn (array $reference): int => (int) $reference['referenceCp'], $references));
        $t->same([1, 2], array_map(static fn (array $reference): int => (int) $reference['objectIndex'], $references));
        $t->same(['ObjectPool/_10', 'ObjectPool/_11'], array_map(static fn (array $reference): string => (string) $reference['storagePath'], $references));
        $t->same(['first-sheet.xlsx', 'second-sheet.xlsx'], array_map(static fn (array $reference): string => (string) $reference['label'], $references));
        $t->same('embedded object: first-sheet.xlsx', $document->children[0]->children[1]->children[0]->attr('text'));
        $t->same('embedded object: second-sheet.xlsx', $document->children[0]->children[3]->children[0]->attr('text'));
        $t->contains('<p>Objects <span class="legacy-doc-object-ref" data-legacy-doc-object-ref="1" data-legacy-doc-object-reference-cp="8"', $blocks);
        $t->contains('data-legacy-doc-object-label="first-sheet.xlsx"', $blocks);
        $t->contains('data-legacy-doc-object-label="second-sheet.xlsx"', $blocks);
        $t->true(!str_contains($blocks, "\x01"), 'Legacy DOC object placeholders should not render to WordPress blocks');
        $t->true(!str_contains($blocks, $firstNativeData), 'First embedded OLE native bytes should not render to WordPress blocks');
        $t->true(!str_contains($blocks, $secondNativeData), 'Second embedded OLE native bytes should not render to WordPress blocks');
    },
    'reports malformed legacy DOC CompObj streams without exposing object bytes' => static function (TestRunner $t) use ($buildCfb, $buildSimpleWordDocument): void {
        $docBytes = $buildCfb([
            'WordDocument' => $buildSimpleWordDocument("Malformed compound object packet\r"),
            'ObjectPool/_77/' . "\x01" . 'CompObj' => str_repeat("\0", 20),
        ]);

        $result = (new LegacyDocReader())->readBytes($docBytes);
        $object = $result['embeddedObjects'][0];
        $compoundStream = $object['streams'][0];

        $t->same('ObjectPool/_77', $object['storagePath']);
        $t->same(false, $object['canExposeBytes']);
        $t->same('compound-object', $compoundStream['role']);
        $t->same(false, $compoundStream['compoundObject']['canExposeBytes']);
        $t->same('truncated-compobj-header', $compoundStream['compoundObject']['diagnostics'][0]['code']);
        $t->same('truncated-compobj-header', $object['diagnostics'][0]['code']);
    },
    'reports malformed legacy DOC Ole10Native stream sizes without exposing bytes' => static function (TestRunner $t) use ($buildCfb, $buildSimpleWordDocument, $u16, $u32): void {
        $payload = $u16(0x0002)
            . "broken.bin\0"
            . "C:\legacy\broken.bin\0"
            . $u16(0)
            . $u16(0)
            . "C:\Temp\broken.tmp\0"
            . $u32(128)
            . 'abc';
        $docBytes = $buildCfb([
            'WordDocument' => $buildSimpleWordDocument("Malformed embedded object packet\r"),
            'ObjectPool/_99/' . "\x01" . 'Ole10Native' => $u32(strlen($payload)) . $payload,
        ]);

        $result = (new LegacyDocReader())->readBytes($docBytes);
        $object = $result['embeddedObjects'][0];
        $nativeStream = $object['streams'][0];

        $t->same('ObjectPool/_99', $object['storagePath']);
        $t->same(false, $object['canExposeBytes']);
        $t->same('native-data', $nativeStream['role']);
        $t->same(false, $nativeStream['canExposeBytes']);
        $t->same('broken.bin', $nativeStream['oleNative']['label']);
        $t->same(128, $nativeStream['oleNative']['nativeDataBytes']);
        $t->same(3, $nativeStream['oleNative']['availableNativeDataBytes']);
        $t->same('ole-native-data-size-exceeds-stream', $nativeStream['oleNative']['diagnostics'][0]['code']);
        $t->same('ole-native-data-size-exceeds-stream', $object['diagnostics'][0]['code']);
    },
    'reports legacy DOC VBA macro project streams as disabled review metadata' => static function (TestRunner $t) use ($buildCfb, $buildSimpleWordDocument): void {
        $docBytes = $buildCfb([
            'WordDocument' => $buildSimpleWordDocument("Macro-enabled legacy packet\r"),
            'Macros/PROJECT' => "ID=\"LegacyMacros\"\r\nDocument=ThisDocument/&H00000000\r\nModule=MigrationTools\r\n",
            'Macros/PROJECTwm' => "LegacyMacros\0ThisDocument\0MigrationTools\0",
            'Macros/VBA/dir' => "compressed vba directory bytes",
            'Macros/VBA/_VBA_PROJECT' => "performance cache bytes",
            'Macros/VBA/ThisDocument' => "Attribute VB_Name = \"ThisDocument\"\r\nPrivate Sub Document_Open()\r\nEnd Sub\r\n",
            'Macros/VBA/MigrationTools' => "Attribute VB_Name = \"MigrationTools\"\r\nSub ImportPacket()\r\nEnd Sub\r\n",
        ]);

        $result = (new LegacyDocReader())->readBytes($docBytes);
        $document = $result['document'];
        $metadata = $result['metadata'];
        $projects = $result['macroProjects'];
        $blocks = (new WordPressBlockWriter())->write($document);

        $t->same(true, $metadata['containsMacros']);
        $t->same(1, $metadata['macroProjectCount']);
        $t->same('disabled-native-review', $metadata['macroPolicy']);
        $t->same($projects, $document->attr('macroProjects'));
        $t->same(1, count($projects));
        $t->same('Macros', $projects[0]['storagePath']);
        $t->same('macro-execution-disabled', $projects[0]['policy']);
        $t->same(false, $projects[0]['canExecute']);
        $t->same(false, $projects[0]['canExposeBytes']);
        $t->same(true, $projects[0]['hasVbaStorage']);
        $t->same(true, $projects[0]['hasDirStream']);
        $t->same(true, $projects[0]['hasProjectStream']);
        $t->same(true, $projects[0]['hasProjectWmStream']);
        $t->same(true, $projects[0]['hasPerformanceCache']);
        $t->same(['MigrationTools', 'ThisDocument'], $projects[0]['moduleStreams']);
        $t->same([
            'project-properties',
            'project-codepage',
            'module-stream',
            'module-stream',
            'vba-performance-cache',
            'vba-dir-compressed',
        ], array_map(static fn (array $stream): string => $stream['role'], $projects[0]['streams']));
        $t->same(false, $projects[0]['streams'][2]['canExposeBytes']);
        $t->same(strlen("Attribute VB_Name = \"MigrationTools\"\r\nSub ImportPacket()\r\nEnd Sub\r\n"), $projects[0]['streams'][2]['bytes']);
        $t->contains('<p>Macro-enabled legacy packet</p>', $blocks);
        $t->true(!str_contains($blocks, 'Document_Open'), 'Legacy DOC VBA module bytes should not render to WordPress blocks');
        $t->true(!str_contains($blocks, 'ImportPacket'), 'Legacy DOC VBA module bytes should not render to WordPress blocks');
    },
    'extracts complex legacy DOC piece-table text from the selected 1Table stream' => static function (TestRunner $t) use ($buildCfb, $buildPieceTableDocStreams): void {
        $streams = $buildPieceTableDocStreams();
        $docBytes = $buildCfb($streams);
        $result = (new LegacyDocReader())->readBytes($docBytes);
        $document = $result['document'];
        $blocks = (new WordPressBlockWriter())->write($document);

        $t->same('piece-table', $document->attr('textSource'));
        $t->same('1Table', $document->attr('tableStream'));
        $t->same(true, $result['fib']['complex']);
        $t->same('Legacy “smart” Unicode Ω import', $document->children[0]->children[0]->attr('text'));
        $t->contains('<p>Legacy “smart” Unicode Ω import</p>', $blocks);
    },
    'uses FibRgLw97 subdocument CP counts to keep supplemental piece-table text out of rendered main blocks' => static function (TestRunner $t) use ($buildCfb, $buildSubdocumentPieceTableDocStreams): void {
        $fixture = $buildSubdocumentPieceTableDocStreams();
        $result = (new LegacyDocReader())->readBytes($buildCfb($fixture['streams']));
        $document = $result['document'];
        $blocks = (new WordPressBlockWriter())->write($document);
        $fibRgLw97 = $result['fib']['fibRgLw97'];

        $mainCharacters = strlen($fixture['mainText']);
        $footnoteCharacters = strlen($fixture['footnoteText']);
        $headerCharacters = strlen($fixture['headerText']);
        $commentCharacters = strlen($fixture['commentText']);
        $footnoteStartCp = $mainCharacters + 1;
        $headerStartCp = $footnoteStartCp + $footnoteCharacters;
        $commentStartCp = $headerStartCp + $headerCharacters;

        $t->same('piece-table', $document->attr('textSource'));
        $t->same(1, count($document->children));
        $t->same('Main review body', $document->children[0]->children[0]->attr('text'));
        $t->contains('<p>Main review body</p>', $blocks);
        $t->true(!str_contains($blocks, 'Footnote body must stay metadata-only'));
        $t->true(!str_contains($blocks, 'Header packet should not render'));
        $t->true(!str_contains($blocks, 'Comment body stays annotation-only'));

        $t->same($fibRgLw97, $result['metadata']['fibRgLw97']);
        $t->same($fibRgLw97, $document->attr('meta')['fibRgLw97']);
        $t->same($mainCharacters, $fibRgLw97['ccpText']);
        $t->same($footnoteCharacters, $fibRgLw97['ccpFtn']);
        $t->same($headerCharacters, $fibRgLw97['ccpHdd']);
        $t->same($commentCharacters, $fibRgLw97['ccpAtn']);
        $t->same($fixture['expectedLastCp'], $fibRgLw97['pieceTableExpectedLastCp']);
        $t->same($fixture['expectedLastCp'] - $mainCharacters - 1, $fibRgLw97['supplementalSubdocumentCharacters']);
        $t->same(true, $fibRgLw97['hasSupplementalSubdocuments']);
        $t->same([
            [
                'type' => 'main',
                'startCp' => 0,
                'endCp' => $mainCharacters,
                'characterCount' => $mainCharacters,
            ],
            [
                'type' => 'footnote',
                'startCp' => $footnoteStartCp,
                'endCp' => $headerStartCp,
                'characterCount' => $footnoteCharacters,
            ],
            [
                'type' => 'header',
                'startCp' => $headerStartCp,
                'endCp' => $commentStartCp,
                'characterCount' => $headerCharacters,
            ],
            [
                'type' => 'comment',
                'startCp' => $commentStartCp,
                'endCp' => $fixture['expectedLastCp'],
                'characterCount' => $commentCharacters,
            ],
        ], $fibRgLw97['subdocuments']);
    },
    'extracts legacy DOC supplemental note comment and header subdocument text as metadata only' => static function (TestRunner $t) use ($buildCfb, $buildSubdocumentReferenceBodyDocStreams): void {
        $fixture = $buildSubdocumentReferenceBodyDocStreams();
        $result = (new LegacyDocReader())->readBytes($buildCfb($fixture['streams']));
        $document = $result['document'];
        $metadata = $result['metadata'];
        $subdocuments = $result['subdocuments'];
        $footnotes = $result['footnotes'];
        $endnotes = $result['endnotes'];
        $comments = $result['comments'];
        $blocks = (new WordPressBlockWriter())->write($document);

        $subdocumentsByType = [];
        foreach ($subdocuments as $subdocument) {
            $subdocumentsByType[(string) $subdocument['type']] = $subdocument;
        }

        $t->same(4, count($subdocuments));
        $t->same(4, $metadata['subdocumentCount']);
        $t->same($subdocuments, $metadata['subdocuments']);
        $t->same($subdocuments, $document->attr('subdocuments'));
        $t->same($fixture['footnoteText'], $subdocumentsByType['footnote']['text']);
        $t->same($fixture['headerText'], $subdocumentsByType['header']['text']);
        $t->same($fixture['commentText'], $subdocumentsByType['comment']['text']);
        $t->same($fixture['endnoteText'], $subdocumentsByType['endnote']['text']);

        $t->same($fixture['footnoteText'], $footnotes[0]['bodyText']);
        $t->same(strlen($fixture['footnoteText']), $footnotes[0]['bodyCharacterCount']);
        $t->same($fixture['endnoteText'], $endnotes[0]['bodyText']);
        $t->same(strlen($fixture['endnoteText']), $endnotes[0]['bodyCharacterCount']);
        $t->same($fixture['commentText'], $comments[0]['bodyText']);
        $t->same(strlen($fixture['commentText']), $comments[0]['bodyCharacterCount']);

        $paragraph = $document->children[0];
        $footnoteRef = $paragraph->children[1];
        $endnoteRef = $paragraph->children[3];
        $commentRef = $paragraph->children[5];
        $t->same('true', $footnoteRef->attr('attributes')['data-legacy-doc-note-has-body']);
        $t->same((string) strlen($fixture['footnoteText']), $footnoteRef->attr('attributes')['data-legacy-doc-note-body-character-count']);
        $t->same('true', $endnoteRef->attr('attributes')['data-legacy-doc-note-has-body']);
        $t->same((string) strlen($fixture['endnoteText']), $endnoteRef->attr('attributes')['data-legacy-doc-note-body-character-count']);
        $t->same('true', $commentRef->attr('attributes')['data-legacy-doc-comment-has-body']);
        $t->same((string) strlen($fixture['commentText']), $commentRef->attr('attributes')['data-legacy-doc-comment-body-character-count']);

        $t->contains('<p>Main <span class="legacy-doc-note-ref legacy-doc-footnote-ref"', $blocks);
        foreach (['footnoteText', 'headerText', 'commentText', 'endnoteText'] as $field) {
            $t->true(!str_contains($blocks, trim($fixture[$field])), 'Legacy DOC supplemental subdocument text should not render to WordPress blocks');
        }
    },
    'parses legacy DOC PlcfHdd header footer story ranges as metadata only' => static function (TestRunner $t) use ($buildCfb, $buildSubdocumentReferenceBodyDocStreams, $u32): void {
        $fixture = $buildSubdocumentReferenceBodyDocStreams();
        $headerCharacters = strlen($fixture['headerText']);
        $headerStoryText = substr($fixture['headerText'], 0, -1);
        $ignoredFinalCp = 0x44444444;
        $plcfhdd = $u32(0)
            . $u32(0)
            . $u32(0)
            . $u32(0)
            . $u32(0)
            . $u32(0)
            . $u32(0)
            . $u32(0)
            . $u32($headerCharacters - 1)
            . $u32($headerCharacters - 1)
            . $u32($headerCharacters - 1)
            . $u32($headerCharacters - 1)
            . $u32($headerCharacters - 1)
            . $u32($ignoredFinalCp);
        $fcPlcfHdd = strlen($fixture['streams']['1Table']);
        $fixture['streams']['1Table'] .= $plcfhdd;
        $fixture['streams']['WordDocument'] = substr_replace($fixture['streams']['WordDocument'], $u32($fcPlcfHdd), 0x00f2, 4);
        $fixture['streams']['WordDocument'] = substr_replace($fixture['streams']['WordDocument'], $u32(strlen($plcfhdd)), 0x00f6, 4);

        $result = (new LegacyDocReader())->readBytes($buildCfb($fixture['streams']));
        $document = $result['document'];
        $metadata = $result['metadata'];
        $stories = $result['headerFooterStories'];
        $blocks = (new WordPressBlockWriter())->write($document);

        $t->same(1, count($stories));
        $t->same(1, $metadata['headerFooterStoryCount']);
        $t->same(12, $metadata['headerFooterDeclaredStoryCount']);
        $t->same($ignoredFinalCp, $metadata['headerFooterIgnoredFinalCp']);
        $t->same($stories, $metadata['headerFooterStories']);
        $t->same($stories, $document->attr('headerFooterStories'));
        $t->same('PlcfHdd', $stories[0]['sourceTable']);
        $t->same(8, $stories[0]['index']);
        $t->same(7, $stories[0]['storyNumber']);
        $t->same('odd-page-header', $stories[0]['role']);
        $t->same('header', $stories[0]['kind']);
        $t->same(1, $stories[0]['sectionIndex']);
        $t->same(0, $stories[0]['startCp']);
        $t->same($headerCharacters - 1, $stories[0]['endCp']);
        $t->same($headerCharacters - 1, $stories[0]['characterCount']);
        $t->same($headerStoryText, $stories[0]['text']);
        $t->same($headerCharacters - 1, $stories[0]['guardCp']);
        $t->same(true, $stories[0]['hasGuardParagraph']);
        $t->contains('<p>Main <span class="legacy-doc-note-ref legacy-doc-footnote-ref"', $blocks);
        $t->true(!str_contains($blocks, $headerStoryText), 'Legacy DOC PlcfHdd header story text should not render to WordPress blocks');

        $badFixture = $fixture;
        $badFixture['streams']['1Table'] = substr_replace($badFixture['streams']['1Table'], $u32($headerCharacters), $fcPlcfHdd + 48, 4);
        $t->throws(\RuntimeException::class, static fn (): array => (new LegacyDocReader())->readBytes($buildCfb($badFixture['streams'])));
    },
    'extracts legacy DOC supplemental story Plcfld field tables as metadata only' => static function (TestRunner $t) use ($buildCfb, $buildSupplementalFieldTableDocStreams, $u32): void {
        $fixture = $buildSupplementalFieldTableDocStreams();
        $result = (new LegacyDocReader())->readBytes($buildCfb($fixture['streams']));
        $document = $result['document'];
        $metadata = $result['metadata'];
        $fields = $result['fields'];
        $fieldCharacters = $result['fieldCharacters'];
        $fieldStories = $result['fieldStories'];
        $blocks = (new WordPressBlockWriter())->write($document);

        $t->same(18, $metadata['fieldCharacterCount']);
        $t->same(6, $metadata['fieldCount']);
        $t->same(6, $metadata['fieldStoryCount']);
        $t->same($fields, $metadata['fields']);
        $t->same($fieldCharacters, $metadata['fieldCharacters']);
        $t->same($fieldStories, $metadata['fieldStories']);
        $t->same($fieldStories, $document->attr('fieldStories'));
        $t->same(['header', 'footnote', 'comment', 'endnote', 'textbox', 'header-textbox'], array_column($fieldStories, 'story'));
        $t->same(['PlcfldHdr', 'PlcfldFtn', 'PlcfldAtn', 'PlcfldEdn', 'PlcfldTxbx', 'PlcfldHdrTxbx'], array_column($fieldStories, 'table'));
        $t->same([
            strlen($fixture['headerText']),
            strlen($fixture['footnoteText']),
            strlen($fixture['commentText']),
            strlen($fixture['endnoteText']),
            strlen($fixture['textboxText']),
            strlen($fixture['headerTextboxText']),
        ], array_column($fieldStories, 'characterCount'));
        $t->same([3, 3, 3, 3, 3, 3], array_column($fieldStories, 'fieldCharacterCount'));
        $t->same([1, 1, 1, 1, 1, 1], array_column($fieldStories, 'fieldCount'));

        $t->same(['date', 'page', 'ref', 'noteref', 'page', 'ref'], array_column($fields, 'type'));
        $t->same(['header', 'footnote', 'comment', 'endnote', 'textbox', 'header-textbox'], array_column($fields, 'story'));
        $t->same([1, 1, 1, 1, 1, 1], array_column($fields, 'storyIndex'));
        $t->same(strpos($fixture['headerText'], "\x13"), $fields[0]['beginCp']);
        $t->same(strpos($fixture['headerText'], "\x14"), $fields[0]['separatorCp']);
        $t->same(strpos($fixture['headerText'], "\x15"), $fields[0]['endCp']);
        $t->same(strpos($fixture['footnoteText'], "\x13"), $fields[1]['beginCp']);
        $t->same(strpos($fixture['footnoteText'], "\x14"), $fields[1]['separatorCp']);
        $t->same(strpos($fixture['footnoteText'], "\x15"), $fields[1]['endCp']);
        $t->same(strpos($fixture['commentText'], "\x13"), $fields[2]['beginCp']);
        $t->same(strpos($fixture['commentText'], "\x14"), $fields[2]['separatorCp']);
        $t->same(strpos($fixture['commentText'], "\x15"), $fields[2]['endCp']);
        $t->same(strpos($fixture['endnoteText'], "\x13"), $fields[3]['beginCp']);
        $t->same(strpos($fixture['endnoteText'], "\x14"), $fields[3]['separatorCp']);
        $t->same(strpos($fixture['endnoteText'], "\x15"), $fields[3]['endCp']);
        $t->same(strpos($fixture['textboxText'], "\x13"), $fields[4]['beginCp']);
        $t->same(strpos($fixture['textboxText'], "\x14"), $fields[4]['separatorCp']);
        $t->same(strpos($fixture['textboxText'], "\x15"), $fields[4]['endCp']);
        $t->same(strpos($fixture['headerTextboxText'], "\x13"), $fields[5]['beginCp']);
        $t->same(strpos($fixture['headerTextboxText'], "\x14"), $fields[5]['separatorCp']);
        $t->same(strpos($fixture['headerTextboxText'], "\x15"), $fields[5]['endCp']);
        $t->same(array_merge(
            array_fill(0, 3, 'header'),
            array_fill(0, 3, 'footnote'),
            array_fill(0, 3, 'comment'),
            array_fill(0, 3, 'endnote'),
            array_fill(0, 3, 'textbox'),
            array_fill(0, 3, 'header-textbox')
        ), array_column($fieldCharacters, 'story'));
        $t->same([1, 2, 3, 1, 2, 3, 1, 2, 3, 1, 2, 3, 1, 2, 3, 1, 2, 3], array_column($fieldCharacters, 'storyIndex'));
        $t->same([
            'begin',
            'separator',
            'end',
            'begin',
            'separator',
            'end',
            'begin',
            'separator',
            'end',
            'begin',
            'separator',
            'end',
            'begin',
            'separator',
            'end',
            'begin',
            'separator',
            'end',
        ], array_column($fieldCharacters, 'kind'));

        $t->contains('<p>Main body stays rendered</p>', $blocks);
        foreach (['Header ', 'Footnote ', 'Comment ', 'Endnote ', 'Textbox ', 'Header textbox ', '2026-06-06', 'Legacy anchor', 'Anchor', 'PAGE', 'DATE', 'REF', 'NOTEREF'] as $hiddenText) {
            $t->true(!str_contains($blocks, $hiddenText), 'Legacy DOC supplemental field text should not render to WordPress blocks');
        }

        $badFixture = $fixture;
        $headerFinalCpOffset = $badFixture['fieldTableOffsets']['header'] + (count($badFixture['headerFieldRecords']) * 4);
        $badFixture['streams']['1Table'] = substr_replace(
            $badFixture['streams']['1Table'],
            $u32(strlen($badFixture['headerText']) + 1),
            $headerFinalCpOffset,
            4
        );
        $t->throws(\RuntimeException::class, static fn (): array => (new LegacyDocReader())->readBytes($buildCfb($badFixture['streams'])));

        $badEndnoteFixture = $fixture;
        $endnoteFinalCpOffset = $badEndnoteFixture['fieldTableOffsets']['endnote'] + (count($badEndnoteFixture['endnoteFieldRecords']) * 4);
        $badEndnoteFixture['streams']['1Table'] = substr_replace(
            $badEndnoteFixture['streams']['1Table'],
            $u32(strlen($badEndnoteFixture['endnoteText']) + 1),
            $endnoteFinalCpOffset,
            4
        );
        $t->throws(\RuntimeException::class, static fn (): array => (new LegacyDocReader())->readBytes($buildCfb($badEndnoteFixture['streams'])));

        $badTextboxFixture = $fixture;
        $textboxFinalCpOffset = $badTextboxFixture['fieldTableOffsets']['textbox'] + (count($badTextboxFixture['textboxFieldRecords']) * 4);
        $badTextboxFixture['streams']['1Table'] = substr_replace(
            $badTextboxFixture['streams']['1Table'],
            $u32(strlen($badTextboxFixture['textboxText']) + 1),
            $textboxFinalCpOffset,
            4
        );
        $t->throws(\RuntimeException::class, static fn (): array => (new LegacyDocReader())->readBytes($buildCfb($badTextboxFixture['streams'])));
    },
    'honors legacy DOC piece-table no-paragraph-last flags on non-paragraph pieces' => static function (TestRunner $t) use ($buildCfb, $buildPieceTableDocStreams): void {
        $streams = $buildPieceTableDocStreams(0x0001);
        $docBytes = $buildCfb($streams);
        $result = (new LegacyDocReader())->readBytes($docBytes);
        $document = $result['document'];

        $t->same('piece-table', $document->attr('textSource'));
        $t->same(true, $result['fib']['complex']);
        $t->same('Legacy “smart” Unicode Ω import', $document->children[0]->children[0]->attr('text'));
    },
    'rejects dirty legacy DOC piece-table descriptors before exposing text' => static function (TestRunner $t) use ($buildCfb, $buildPieceTableDocStreams): void {
        $reader = new LegacyDocReader();

        $t->throws(\RuntimeException::class, static fn (): array => $reader->readBytes($buildCfb(
            $buildPieceTableDocStreams(0x0004)
        )));
    },
    'rejects complex legacy DOC FIBs without CLX piece-table data before direct text fallback' => static function (TestRunner $t) use ($buildCfb, $buildSimpleWordDocument, $buildPieceTableDocStreams, $u32): void {
        $reader = new LegacyDocReader();

        $t->throws(\RuntimeException::class, static fn (): array => $reader->readBytes($buildCfb([
            'WordDocument' => $buildSimpleWordDocument("Complex direct range should stay opaque\r", 0x0004),
        ])));

        $missingClx = $buildPieceTableDocStreams();
        $missingClx['WordDocument'] = substr_replace($missingClx['WordDocument'], $u32(0), 0x01a6, 4);
        $t->throws(\RuntimeException::class, static fn (): array => $reader->readBytes($buildCfb($missingClx)));
    },
    'rejects no-paragraph-last legacy DOC pieces containing paragraph marks' => static function (TestRunner $t) use ($buildCfb, $buildPieceTableDocStreams): void {
        $reader = new LegacyDocReader();

        $t->throws(\RuntimeException::class, static fn (): array => $reader->readBytes($buildCfb(
            $buildPieceTableDocStreams(0, 0x0001)
        )));
    },
    'rejects malformed legacy DOC FibRgLw97 subdocument counts before exposing piece-table text' => static function (TestRunner $t) use ($buildCfb, $buildSubdocumentPieceTableDocStreams, $u32): void {
        $reader = new LegacyDocReader();

        $negativeFootnoteCount = $buildSubdocumentPieceTableDocStreams();
        $negativeFootnoteCount['streams']['WordDocument'] = substr_replace(
            $negativeFootnoteCount['streams']['WordDocument'],
            $u32(0xffffffff),
            0x0050,
            4
        );
        $t->throws(\RuntimeException::class, static fn (): array => $reader->readBytes($buildCfb($negativeFootnoteCount['streams'])));

        $nonzeroReserved3 = $buildSubdocumentPieceTableDocStreams();
        $nonzeroReserved3['streams']['WordDocument'] = substr_replace(
            $nonzeroReserved3['streams']['WordDocument'],
            $u32(1),
            0x0058,
            4
        );
        $t->throws(\RuntimeException::class, static fn (): array => $reader->readBytes($buildCfb($nonzeroReserved3['streams'])));

        $badPieceTableLimit = $buildSubdocumentPieceTableDocStreams();
        $badPieceTableLimit['streams']['1Table'] = substr_replace(
            $badPieceTableLimit['streams']['1Table'],
            $u32($badPieceTableLimit['expectedLastCp'] + 1),
            5 + (5 * 4),
            4
        );
        $t->throws(\RuntimeException::class, static fn (): array => $reader->readBytes($buildCfb($badPieceTableLimit['streams'])));

        $badCbMac = $buildSubdocumentPieceTableDocStreams();
        $badCbMac['streams']['WordDocument'] = substr_replace(
            $badCbMac['streams']['WordDocument'],
            $u32(strlen($badCbMac['streams']['WordDocument']) + 1),
            0x0040,
            4
        );
        $t->throws(\RuntimeException::class, static fn (): array => $reader->readBytes($buildCfb($badCbMac['streams'])));
    },
    'uses FIB extended-character flag for direct Unicode WordDocument text ranges' => static function (TestRunner $t) use ($buildCfb, $buildSimpleWordDocument): void {
        $docBytes = $buildCfb([
            'WordDocument' => $buildSimpleWordDocument("ΩЖ魚\rRésumé\r", 0x1000, 'UTF-16LE'),
        ]);

        $result = (new LegacyDocReader())->readBytes($docBytes);
        $document = $result['document'];
        $blocks = (new WordPressBlockWriter())->write($document);

        $t->same('fib-text-range', $document->attr('textSource'));
        $t->same(false, $result['fib']['encrypted']);
        $t->same(true, $result['fib']['extendedCharacters']);
        $t->same('ΩЖ魚', $document->children[0]->children[0]->attr('text'));
        $t->same('Résumé', $document->children[1]->children[0]->attr('text'));
        $t->contains('<p>ΩЖ魚</p>', $blocks);
    },
    'maps legacy DOC field-code hyperlinks to normal link AST nodes' => static function (TestRunner $t) use ($buildCfb, $buildSimpleWordDocument): void {
        $fieldBegin = "\x13";
        $fieldSeparator = "\x14";
        $fieldEnd = "\x15";
        $docBytes = $buildCfb([
            'WordDocument' => $buildSimpleWordDocument(
                'Field link to '
                . $fieldBegin . ' HYPERLINK "https://example.test/legacy?post=42&step=doc" \o "Source packet" '
                . $fieldSeparator . 'source dossier' . $fieldEnd
                . ' and '
                . $fieldBegin . ' HYPERLINK \l "legacy_anchor" '
                . $fieldSeparator . 'anchor jump' . $fieldEnd
                . ".\r"
            ),
        ]);

        $document = (new LegacyDocReader())->readBytes($docBytes)['document'];
        $markdown = (new MarkdownWriter())->write($document);
        $blocks = (new WordPressBlockWriter())->write($document);
        $paragraph = $document->children[0];

        $t->same('Field link to ', $paragraph->children[0]->attr('text'));
        $external = $paragraph->children[1];
        $t->same('link', $external->type);
        $t->same('https://example.test/legacy?post=42&step=doc', $external->attr('url'));
        $t->same('Source packet', $external->attr('title'));
        $t->same('source dossier', $external->children[0]->attr('text'));
        $t->same(' and ', $paragraph->children[2]->attr('text'));
        $internal = $paragraph->children[3];
        $t->same('link', $internal->type);
        $t->same('#legacy_anchor', $internal->attr('url'));
        $t->same('anchor jump', $internal->children[0]->attr('text'));

        $t->contains('Field link to [source dossier](https://example.test/legacy?post=42&step=doc "Source packet") and [anchor jump](#legacy_anchor).', $markdown);
        $t->true(!str_contains($markdown, 'HYPERLINK'), 'Legacy DOC field instructions should not render to Markdown');
        $t->contains('<a href="https://example.test/legacy?post=42&amp;step=doc" title="Source packet">source dossier</a>', $blocks);
        $t->contains('<a href="#legacy_anchor">anchor jump</a>', $blocks);
        $t->true(!str_contains($blocks, 'HYPERLINK'), 'Legacy DOC field instructions should not render to WordPress blocks');
    },
    'extracts standard legacy DOC bookmarks as review metadata and anchor spans' => static function (TestRunner $t) use ($buildCfb, $buildBookmarkTableDocStreams): void {
        $result = (new LegacyDocReader())->readBytes($buildCfb($buildBookmarkTableDocStreams()));
        $document = $result['document'];
        $bookmarks = $result['bookmarks'];
        $paragraph = $document->children[0];
        $markdown = (new MarkdownWriter())->write($document);
        $blocks = (new WordPressBlockWriter())->write($document);

        $t->same(2, count($bookmarks));
        $t->same($bookmarks, $document->attr('bookmarks'));
        $t->same(2, $result['metadata']['bookmarkCount']);
        $t->same($bookmarks, $result['metadata']['bookmarks']);
        $t->same('_HiddenMark', $bookmarks[0]['name']);
        $t->same(true, $bookmarks[0]['hidden']);
        $t->same(0, $bookmarks[0]['startCp']);
        $t->same(5, $bookmarks[0]['endCp']);
        $t->same('legacy_anchor', $bookmarks[1]['name']);
        $t->same(false, $bookmarks[1]['hidden']);
        $t->same(6, $bookmarks[1]['startCp']);
        $t->same(17, $bookmarks[1]['endCp']);
        $t->same(true, $bookmarks[1]['canAnchor']);

        $hidden = $paragraph->children[0];
        $t->same('span', $hidden->type);
        $t->same('_HiddenMark', $hidden->attr('id'));
        $t->same(['legacy-doc-bookmark', 'legacy-doc-bookmark-hidden'], $hidden->attr('classes'));
        $t->same('Intro', $hidden->children[0]->attr('text'));
        $t->same(' ', $paragraph->children[1]->attr('text'));

        $anchor = $paragraph->children[2];
        $t->same('span', $anchor->type);
        $t->same('legacy_anchor', $anchor->attr('id'));
        $t->same(['legacy-doc-bookmark'], $anchor->attr('classes'));
        $t->same('target text', $anchor->children[0]->attr('text'));
        $t->same('legacy_anchor', $anchor->attr('attributes')['data-legacy-doc-bookmark']);
        $t->same('6', $anchor->attr('attributes')['data-legacy-doc-bookmark-start-cp']);
        $t->same('17', $anchor->attr('attributes')['data-legacy-doc-bookmark-end-cp']);

        $t->contains('[target text]{#legacy_anchor .legacy-doc-bookmark data-legacy-doc-bookmark="legacy_anchor"', $markdown);
        $t->contains('<span id="legacy_anchor" class="legacy-doc-bookmark" data-legacy-doc-bookmark="legacy_anchor" data-legacy-doc-bookmark-start-cp="6" data-legacy-doc-bookmark-end-cp="17">target text</span>', $blocks);
    },
    'extracts legacy DOC footnote and endnote reference PLCs as review anchors' => static function (TestRunner $t) use ($buildCfb, $buildNoteTableDocStreams): void {
        $result = (new LegacyDocReader())->readBytes($buildCfb($buildNoteTableDocStreams()));
        $document = $result['document'];
        $footnotes = $result['footnotes'];
        $endnotes = $result['endnotes'];
        $paragraph = $document->children[0];
        $markdown = (new MarkdownWriter())->write($document);
        $blocks = (new WordPressBlockWriter())->write($document);

        $t->same(1, count($footnotes));
        $t->same(1, count($endnotes));
        $t->same($footnotes, $document->attr('footnotes'));
        $t->same($endnotes, $document->attr('endnotes'));
        $t->same(1, $result['metadata']['footnoteReferenceCount']);
        $t->same(1, $result['metadata']['endnoteReferenceCount']);
        $t->same('footnote', $footnotes[0]['type']);
        $t->same(6, $footnotes[0]['referenceCp']);
        $t->same(1, $footnotes[0]['referenceIndex']);
        $t->same(true, $footnotes[0]['autoNumbered']);
        $t->same('1', $footnotes[0]['marker']);
        $t->same(0, $footnotes[0]['textStartCp']);
        $t->same(17, $footnotes[0]['textEndCp']);
        $t->same('endnote', $endnotes[0]['type']);
        $t->same(13, $endnotes[0]['referenceCp']);
        $t->same(0, $endnotes[0]['referenceIndex']);
        $t->same(false, $endnotes[0]['autoNumbered']);
        $t->same('*', $endnotes[0]['marker']);
        $t->same(0, $endnotes[0]['textStartCp']);
        $t->same(11, $endnotes[0]['textEndCp']);

        $t->same('Alpha ', $paragraph->children[0]->attr('text'));
        $footnoteRef = $paragraph->children[1];
        $t->same('span', $footnoteRef->type);
        $t->same(['legacy-doc-note-ref', 'legacy-doc-footnote-ref'], $footnoteRef->attr('classes'));
        $t->same('footnote', $footnoteRef->attr('attributes')['data-legacy-doc-note-type']);
        $t->same('1', $footnoteRef->attr('attributes')['data-legacy-doc-note-index']);
        $t->same('6', $footnoteRef->attr('attributes')['data-legacy-doc-note-reference-cp']);
        $t->same('true', $footnoteRef->attr('attributes')['data-legacy-doc-note-auto-numbered']);
        $t->same('superscript', $footnoteRef->children[0]->type);
        $t->same('1', $footnoteRef->children[0]->children[0]->attr('text'));
        $t->same(' beta ', $paragraph->children[2]->attr('text'));
        $endnoteRef = $paragraph->children[3];
        $t->same('endnote', $endnoteRef->attr('attributes')['data-legacy-doc-note-type']);
        $t->same('0', $endnoteRef->attr('attributes')['data-legacy-doc-note-index']);
        $t->same('13', $endnoteRef->attr('attributes')['data-legacy-doc-note-reference-cp']);
        $t->same('false', $endnoteRef->attr('attributes')['data-legacy-doc-note-auto-numbered']);
        $t->same('*', $endnoteRef->children[0]->children[0]->attr('text'));
        $t->same(' end', $paragraph->children[4]->attr('text'));

        $t->contains('[^1^]{.legacy-doc-note-ref .legacy-doc-footnote-ref data-legacy-doc-note-type="footnote"', $markdown);
        $t->contains('[^\*^]{.legacy-doc-note-ref .legacy-doc-endnote-ref data-legacy-doc-note-type="endnote"', $markdown);
        $t->contains('<span class="legacy-doc-note-ref legacy-doc-footnote-ref" data-legacy-doc-note-type="footnote" data-legacy-doc-note-index="1" data-legacy-doc-note-reference-cp="6" data-legacy-doc-note-text-start-cp="0" data-legacy-doc-note-text-end-cp="17" data-legacy-doc-note-auto-numbered="true"><sup>1</sup></span>', $blocks);
        $t->contains('<span class="legacy-doc-note-ref legacy-doc-endnote-ref" data-legacy-doc-note-type="endnote" data-legacy-doc-note-index="0" data-legacy-doc-note-reference-cp="13" data-legacy-doc-note-text-start-cp="0" data-legacy-doc-note-text-end-cp="11" data-legacy-doc-note-auto-numbered="false"><sup>*</sup></span>', $blocks);
        $t->true(!str_contains($blocks, "\x02"), 'Legacy DOC special footnote reference character should not render directly');
    },
    'extracts legacy DOC comment reference PLCs as review anchors' => static function (TestRunner $t) use ($buildCfb, $buildCommentTableDocStreams): void {
        $result = (new LegacyDocReader())->readBytes($buildCfb($buildCommentTableDocStreams()));
        $document = $result['document'];
        $comments = $result['comments'];
        $commentAuthors = $result['commentAuthors'];
        $metadata = $result['metadata'];
        $paragraph = $document->children[0];
        $markdown = (new MarkdownWriter())->write($document);
        $blocks = (new WordPressBlockWriter())->write($document);

        $t->same(1, count($comments));
        $t->same($comments, $document->attr('comments'));
        $t->same($comments, $metadata['comments']);
        $t->same($commentAuthors, $document->attr('commentAuthors'));
        $t->same($commentAuthors, $metadata['commentAuthors']);
        $t->same(1, $metadata['commentReferenceCount']);
        $t->same(3, $metadata['commentAuthorCount']);
        $t->same('Migration Lead', $commentAuthors[0]['name']);
        $t->same(14, $commentAuthors[0]['characterCount']);
        $t->same('UTF-16LE-Xstz', $commentAuthors[0]['sourceEncoding']);
        $t->same(32, $commentAuthors[0]['recordBytes']);
        $t->same('Review Editor', $commentAuthors[1]['name']);
        $t->same('Janet Doe', $commentAuthors[2]['name']);
        $t->same('comment', $comments[0]['type']);
        $t->same(1, $comments[0]['index']);
        $t->same(6, $comments[0]['referenceCp']);
        $t->same('JD', $comments[0]['authorInitials']);
        $t->same(2, $comments[0]['authorIndex']);
        $t->same('Janet Doe', $comments[0]['authorName']);
        $t->same(0x1234, $comments[0]['bookmarkTag']);
        $t->same(false, $comments[0]['lengthZeroRange']);
        $t->same('JD', $comments[0]['marker']);
        $t->same(0, $comments[0]['textStartCp']);
        $t->same(31, $comments[0]['textEndCp']);
        $t->same(true, $comments[0]['canAnchor']);

        $t->same('Alpha ', $paragraph->children[0]->attr('text'));
        $commentRef = $paragraph->children[1];
        $t->same('span', $commentRef->type);
        $t->same(['legacy-doc-comment-ref'], $commentRef->attr('classes'));
        $t->same('1', $commentRef->attr('attributes')['data-legacy-doc-comment-index']);
        $t->same('6', $commentRef->attr('attributes')['data-legacy-doc-comment-reference-cp']);
        $t->same('0', $commentRef->attr('attributes')['data-legacy-doc-comment-text-start-cp']);
        $t->same('31', $commentRef->attr('attributes')['data-legacy-doc-comment-text-end-cp']);
        $t->same('2', $commentRef->attr('attributes')['data-legacy-doc-comment-author-index']);
        $t->same('JD', $commentRef->attr('attributes')['data-legacy-doc-comment-author-initials']);
        $t->same('Janet Doe', $commentRef->attr('attributes')['data-legacy-doc-comment-author-name']);
        $t->same((string) 0x1234, $commentRef->attr('attributes')['data-legacy-doc-comment-bookmark-tag']);
        $t->same('superscript', $commentRef->children[0]->type);
        $t->same('JD', $commentRef->children[0]->children[0]->attr('text'));
        $t->same(' beta', $paragraph->children[2]->attr('text'));

        $t->contains('[^JD^]{.legacy-doc-comment-ref data-legacy-doc-comment-index="1"', $markdown);
        $t->contains('<span class="legacy-doc-comment-ref" data-legacy-doc-comment-index="1" data-legacy-doc-comment-reference-cp="6" data-legacy-doc-comment-text-start-cp="0" data-legacy-doc-comment-text-end-cp="31" data-legacy-doc-comment-author-index="2" data-legacy-doc-comment-author-initials="JD" data-legacy-doc-comment-author-name="Janet Doe" data-legacy-doc-comment-bookmark-tag="4660"><sup>JD</sup></span>', $blocks);
        $t->true(!str_contains($blocks, "\x05"), 'Legacy DOC special comment reference character should not render directly');
    },
    'extracts legacy DOC section descriptor PLCs as bounded layout review metadata' => static function (TestRunner $t) use ($buildCfb, $buildSectionTableDocStreams): void {
        $result = (new LegacyDocReader())->readBytes($buildCfb($buildSectionTableDocStreams()));
        $document = $result['document'];
        $sections = $result['sections'];
        $metadata = $result['metadata'];
        $blocks = (new WordPressBlockWriter())->write($document);

        $t->same(2, count($sections));
        $t->same($sections, $document->attr('sections'));
        $t->same($sections, $metadata['sections']);
        $t->same(2, $metadata['sectionCount']);
        $t->same(1, $sections[0]['index']);
        $t->same(0, $sections[0]['startCp']);
        $t->same(14, $sections[0]['endCp']);
        $t->same(13, $sections[0]['contentEndCp']);
        $t->same(true, $sections[0]['hasSectionBreak']);
        $t->same(false, $sections[0]['hasSepx']);
        $t->true(!isset($sections[0]['sepxFc']), 'Default section descriptors should not invent SEPX offsets');

        $t->same(2, $sections[1]['index']);
        $t->same(14, $sections[1]['startCp']);
        $t->same(29, $sections[1]['endCp']);
        $t->same(29, $sections[1]['contentEndCp']);
        $t->same(false, $sections[1]['hasSectionBreak']);
        $t->same(true, $sections[1]['hasSepx']);
        $t->same(1536, $sections[1]['sepxFc']);
        $t->same(6, $sections[1]['sepxByteCount']);
        $t->same(4, $sections[1]['sprmByteCount']);
        $t->contains('<p>Intro section<br/>Second section</p>', $blocks);
    },
    'extracts legacy DOC stylesheet style names as bounded review metadata' => static function (TestRunner $t) use ($buildCfb, $buildStyleSheetDocStreams, $styleDefinition): void {
        $result = (new LegacyDocReader())->readBytes($buildCfb($buildStyleSheetDocStreams([
            15 => $styleDefinition('Review Heading,Import Title', 1, 0x0fff, 16, 2),
            16 => $styleDefinition('Reviewer Body', 1, 15, 16, 2),
            17 => $styleDefinition('Migration Emphasis', 2, 0x0fff, 16, 1),
        ])));
        $document = $result['document'];
        $styles = $result['styles'];
        $metadata = $result['metadata'];

        $t->same(3, count($styles));
        $t->same($styles, $document->attr('styles'));
        $t->same($styles, $metadata['styles']);
        $t->same(3, $metadata['styleCount']);
        $t->same(15, $styles[0]['istd']);
        $t->same('paragraph', $styles[0]['type']);
        $t->same('Review Heading', $styles[0]['name']);
        $t->same(['Import Title'], $styles[0]['aliases']);
        $t->same(0x0ffe, $styles[0]['sti']);
        $t->same(false, $styles[0]['builtIn']);
        $t->same(2, $styles[0]['cupx']);
        $t->same(16, $styles[0]['nextIstd']);
        $t->true(!isset($styles[0]['basedOnIstd']), 'Root styles should not invent based-on relationships');
        $t->same('Reviewer Body', $styles[1]['name']);
        $t->same(15, $styles[1]['basedOnIstd']);
        $t->same('character', $styles[2]['type']);
        $t->same('Migration Emphasis', $styles[2]['name']);
        $t->same(1, $styles[2]['cupx']);
        $t->same(10 + 2 + strlen("Migration Emphasis") * 2 + 2, $styles[2]['cbStd']);
        $t->same($styles[2]['cbStd'], $styles[2]['bchUpe']);
    },
    'reports legacy DOC paragraph and character formatting table FKP ranges for review' => static function (TestRunner $t) use ($buildCfb, $buildFormattingTableDocStreams): void {
        $result = (new LegacyDocReader())->readBytes($buildCfb($buildFormattingTableDocStreams()));
        $document = $result['document'];
        $runs = $result['formattingRuns'];
        $metadata = $result['metadata'];
        $blocks = (new WordPressBlockWriter())->write($document);

        $t->same($runs, $document->attr('formattingRuns'));
        $t->same($runs, $metadata['formattingRuns']);
        $t->same(3, $metadata['formattingRunCount']);
        $t->same(1, $metadata['paragraphFormattingRunCount']);
        $t->same(2, $metadata['characterFormattingRunCount']);

        $t->same('paragraph', $runs[0]['kind']);
        $t->same('PlcBtePapx', $runs[0]['table']);
        $t->same(512, $runs[0]['startFc']);
        $t->same(538, $runs[0]['endFc']);
        $t->same(26, $runs[0]['byteLength']);
        $t->same(2, $runs[0]['fkpPage']);
        $t->same(1024, $runs[0]['fkpByteOffset']);
        $t->same(512, $runs[0]['fkpByteCount']);
        $t->same(2, $runs[0]['fkpRunCount']);
        $t->same(false, $runs[0]['canApplyFormatting']);

        $t->same('character', $runs[1]['kind']);
        $t->same('PlcBteChpx', $runs[1]['table']);
        $t->same(512, $runs[1]['startFc']);
        $t->same(518, $runs[1]['endFc']);
        $t->same(6, $runs[1]['byteLength']);
        $t->same(3, $runs[1]['fkpPage']);
        $t->same(1536, $runs[1]['fkpByteOffset']);
        $t->same(3, $runs[1]['fkpRunCount']);

        $t->same('character', $runs[2]['kind']);
        $t->same(518, $runs[2]['startFc']);
        $t->same(538, $runs[2]['endFc']);
        $t->same(20, $runs[2]['byteLength']);
        $t->same(3, $runs[2]['fkpPage']);
        $t->true(!isset($runs[2]['unusedPnFkpBits']), 'Zero PnFkp unused bits should stay omitted');
        $t->contains('<p>Styled first</p>', $blocks);
        $t->contains('<p>Plain second</p>', $blocks);
    },
    'rejects malformed legacy DOC formatting table BTE ranges before exposing metadata' => static function (TestRunner $t) use ($buildCfb, $buildFormattingTableDocStreams, $u32): void {
        $reader = new LegacyDocReader();

        $badLength = $buildFormattingTableDocStreams();
        $badLength['WordDocument'] = substr_replace($badLength['WordDocument'], $u32(8), 0x0106, 4);
        $t->throws(\RuntimeException::class, static fn (): array => $reader->readBytes($buildCfb($badLength)));

        $unsortedFc = $buildFormattingTableDocStreams();
        $unsortedFc['0Table'] = substr_replace($unsortedFc['0Table'], $u32(512), 4, 4);
        $t->throws(\RuntimeException::class, static fn (): array => $reader->readBytes($buildCfb($unsortedFc)));

        $badFkpPage = $buildFormattingTableDocStreams();
        $badFkpPage['0Table'] = substr_replace($badFkpPage['0Table'], $u32(9999), 8, 4);
        $t->throws(\RuntimeException::class, static fn (): array => $reader->readBytes($buildCfb($badFkpPage)));
    },
    'extracts legacy DOC list table formats and overrides as numbering review metadata' => static function (TestRunner $t) use ($buildCfb, $buildListTableDocStreams): void {
        $result = (new LegacyDocReader())->readBytes($buildCfb($buildListTableDocStreams()));
        $document = $result['document'];
        $formats = $result['listFormats'];
        $overrides = $result['listOverrides'];
        $metadata = $result['metadata'];
        $blocks = (new WordPressBlockWriter())->write($document);

        $t->same($formats, $document->attr('listFormats'));
        $t->same($overrides, $document->attr('listOverrides'));
        $t->same($formats, $metadata['listFormats']);
        $t->same($overrides, $metadata['listOverrides']);
        $t->same(2, $metadata['listFormatCount']);
        $t->same(2, $metadata['listLevelCount']);
        $t->same(2, $metadata['listOverrideCount']);

        $t->same(1001, $formats[0]['lsid']);
        $t->same(2001, $formats[0]['templateCode']);
        $t->same(true, $formats[0]['simple']);
        $t->same(false, $formats[0]['autoNumber']);
        $t->same(false, $formats[0]['hybrid']);
        $t->same([['level' => 0, 'istd' => 15]], $formats[0]['linkedStyleIstds']);
        $orderedLevel = $formats[0]['levels'][0];
        $t->same(0, $orderedLevel['level']);
        $t->same(3, $orderedLevel['startAt']);
        $t->same('decimal', $orderedLevel['numberFormat']);
        $t->same('%1.', $orderedLevel['numberText']);
        $t->same([1], $orderedLevel['placeholderOffsets']);
        $t->same([0], $orderedLevel['placeholderLevels']);
        $t->same('space', $orderedLevel['follow']);
        $t->same(3, $orderedLevel['paragraphPropertyBytes']);
        $t->same(2, $orderedLevel['characterPropertyBytes']);
        $t->same(false, $orderedLevel['canApplyNumbering']);

        $t->same(2002, $formats[1]['lsid']);
        $bulletLevel = $formats[1]['levels'][0];
        $t->same('bullet', $bulletLevel['numberFormat']);
        $t->same('•', $bulletLevel['numberText']);
        $t->same('tab', $bulletLevel['follow']);
        $t->same([], $bulletLevel['placeholderOffsets']);
        $t->same(2, $formats[1]['htmlCompatibilityFlags']);

        $t->same(1, $overrides[0]['ilfo']);
        $t->same(1001, $overrides[0]['lsid']);
        $t->same('AUTONUM', $overrides[0]['autoNumberField']);
        $t->same(0, $overrides[0]['firstParagraphCp']);
        $t->same(1, $overrides[0]['overrideLevelCount']);
        $t->same(0, $overrides[0]['levels'][0]['level']);
        $t->same(true, $overrides[0]['levels'][0]['startAtOverride']);
        $t->same(false, $overrides[0]['levels'][0]['formattingOverride']);
        $t->same(7, $overrides[0]['levels'][0]['startAt']);
        $t->same(2, $overrides[1]['ilfo']);
        $t->same(2002, $overrides[1]['lsid']);
        $t->same(strlen("First numbered item\r"), $overrides[1]['firstParagraphCp']);
        $t->same([], $overrides[1]['levels']);
        $t->contains('<p>First numbered item</p>', $blocks);
        $t->contains('<p>Second bullet item</p>', $blocks);
    },
    'rejects malformed legacy DOC list tables before exposing numbering metadata' => static function (TestRunner $t) use ($buildCfb, $buildListTableDocStreams, $u32): void {
        $reader = new LegacyDocReader();

        $badLength = $buildListTableDocStreams();
        $badLength['WordDocument'] = substr_replace($badLength['WordDocument'], $u32(3), 0x02e6, 4);
        $t->throws(\RuntimeException::class, static fn (): array => $reader->readBytes($buildCfb($badLength)));

        $duplicateLsid = $buildListTableDocStreams();
        $duplicateLsid['0Table'] = substr_replace($duplicateLsid['0Table'], $u32(1001), 2 + 28, 4);
        $t->throws(\RuntimeException::class, static fn (): array => $reader->readBytes($buildCfb($duplicateLsid)));

        $unknownOverride = $buildListTableDocStreams();
        $fcPlfLfo = unpack('Vvalue', substr($unknownOverride['WordDocument'], 0x02ea, 4))['value'];
        $unknownOverride['0Table'] = substr_replace($unknownOverride['0Table'], $u32(9999), (int) $fcPlfLfo + 4, 4);
        $t->throws(\RuntimeException::class, static fn (): array => $reader->readBytes($buildCfb($unknownOverride)));

        $badPlaceholder = $buildListTableDocStreams();
        $levelOffset = 2 + (2 * 28);
        $badPlaceholder['0Table'] = substr_replace($badPlaceholder['0Table'], "\x02", $levelOffset + 6, 1);
        $t->throws(\RuntimeException::class, static fn (): array => $reader->readBytes($buildCfb($badPlaceholder)));
    },
    'rejects malformed legacy DOC stylesheet records before exposing style metadata' => static function (TestRunner $t) use ($buildCfb, $buildStyleSheetDocStreams, $styleDefinition, $u16, $u32): void {
        $reader = new LegacyDocReader();

        $duplicateNames = $buildStyleSheetDocStreams([
            15 => $styleDefinition('Duplicate Style', 1, 0x0fff, 15, 2),
            16 => $styleDefinition('duplicate style', 2, 0x0fff, 16, 1),
        ]);
        $t->throws(\RuntimeException::class, static fn (): array => $reader->readBytes($buildCfb($duplicateNames)));

        $badBase = $buildStyleSheetDocStreams([
            15 => $styleDefinition('Bad Base', 1, 14, 15, 2),
        ]);
        $t->throws(\RuntimeException::class, static fn (): array => $reader->readBytes($buildCfb($badBase)));

        $truncated = $buildStyleSheetDocStreams([
            15 => $styleDefinition('Truncated Style', 1, 0x0fff, 15, 2),
        ]);
        $truncated['WordDocument'] = substr_replace($truncated['WordDocument'], $u32(12), 0x00a6, 4);
        $t->throws(\RuntimeException::class, static fn (): array => $reader->readBytes($buildCfb($truncated)));

        $badFixedStyleCount = $buildStyleSheetDocStreams([
            15 => $styleDefinition('Bad Header', 1, 0x0fff, 15, 2),
        ]);
        $badFixedStyleCount['0Table'] = substr_replace($badFixedStyleCount['0Table'], $u16(0x000e), 10, 2);
        $t->throws(\RuntimeException::class, static fn (): array => $reader->readBytes($buildCfb($badFixedStyleCount)));
    },
    'rejects malformed legacy DOC section descriptor PLCs before rendering sections' => static function (TestRunner $t) use ($buildCfb, $buildSectionTableDocStreams, $u32): void {
        $reader = new LegacyDocReader();

        $duplicateCp = $buildSectionTableDocStreams();
        $duplicateCp['0Table'] = substr_replace($duplicateCp['0Table'], $u32(0), 4, 4);
        $t->throws(\RuntimeException::class, static fn (): array => $reader->readBytes($buildCfb($duplicateCp)));

        $missingSectionBreak = $buildSectionTableDocStreams();
        $missingSectionBreak['WordDocument'] = substr_replace($missingSectionBreak['WordDocument'], 'x', 1024 + 13, 1);
        $t->throws(\RuntimeException::class, static fn (): array => $reader->readBytes($buildCfb($missingSectionBreak)));

        $badSepxPointer = $buildSectionTableDocStreams();
        $badSepxPointer['0Table'] = substr_replace($badSepxPointer['0Table'], $u32(999999), 26, 4);
        $t->throws(\RuntimeException::class, static fn (): array => $reader->readBytes($buildCfb($badSepxPointer)));
    },
    'rejects malformed legacy DOC footnote endnote and comment PLCs before rendering references' => static function (TestRunner $t) use ($buildCfb, $buildNoteTableDocStreams, $buildCommentTableDocStreams, $u16, $u32): void {
        $reader = new LegacyDocReader();
        $missingFootnoteText = $buildNoteTableDocStreams();
        $missingFootnoteText['WordDocument'] = substr_replace($missingFootnoteText['WordDocument'], $u32(0), 0x00b6, 4);
        $t->throws(\RuntimeException::class, static fn (): array => $reader->readBytes($buildCfb($missingFootnoteText)));

        $badAutoReference = $buildNoteTableDocStreams();
        $badAutoReference['WordDocument'] = substr_replace($badAutoReference['WordDocument'], 'x', 1024 + 6, 1);
        $t->throws(\RuntimeException::class, static fn (): array => $reader->readBytes($buildCfb($badAutoReference)));

        $missingCommentText = $buildCommentTableDocStreams();
        $missingCommentText['WordDocument'] = substr_replace($missingCommentText['WordDocument'], $u32(0), 0x00c6, 4);
        $t->throws(\RuntimeException::class, static fn (): array => $reader->readBytes($buildCfb($missingCommentText)));

        $badCommentMarker = $buildCommentTableDocStreams();
        $badCommentMarker['WordDocument'] = substr_replace($badCommentMarker['WordDocument'], 'x', 1024 + 6, 1);
        $t->throws(\RuntimeException::class, static fn (): array => $reader->readBytes($buildCfb($badCommentMarker)));

        $badCommentReserved = $buildCommentTableDocStreams();
        $badCommentReserved['0Table'] = substr_replace($badCommentReserved['0Table'], $u16(1), 8 + 22, 2);
        $t->throws(\RuntimeException::class, static fn (): array => $reader->readBytes($buildCfb($badCommentReserved)));

        $badCommentAuthorTerminator = $buildCommentTableDocStreams();
        $commentAuthorsOffset = unpack('Vvalue', substr($badCommentAuthorTerminator['WordDocument'], 0x01ba, 4))['value'];
        $firstAuthorTerminatorOffset = (int) $commentAuthorsOffset + 2 + (strlen('Migration Lead') * 2);
        $badCommentAuthorTerminator['0Table'] = substr_replace($badCommentAuthorTerminator['0Table'], "X\0", $firstAuthorTerminatorOffset, 2);
        $t->throws(\RuntimeException::class, static fn (): array => $reader->readBytes($buildCfb($badCommentAuthorTerminator)));

        $commentAuthorIndexOutOfRange = $buildCommentTableDocStreams();
        $commentAuthorIndexOutOfRange['WordDocument'] = substr_replace($commentAuthorIndexOutOfRange['WordDocument'], $u32(30), 0x01be, 4);
        $t->throws(\RuntimeException::class, static fn (): array => $reader->readBytes($buildCfb($commentAuthorIndexOutOfRange)));
    },
    'preserves legacy DOC non-hyperlink field provenance around displayed results' => static function (TestRunner $t) use ($buildCfb, $buildSimpleWordDocument): void {
        $fieldBegin = "\x13";
        $fieldSeparator = "\x14";
        $fieldEnd = "\x15";
        $docBytes = $buildCfb([
            'WordDocument' => $buildSimpleWordDocument(
                'Page '
                . $fieldBegin . ' PAGE \* Arabic ' . $fieldSeparator . '7' . $fieldEnd
                . ' of '
                . $fieldBegin . ' NUMPAGES \* Arabic ' . $fieldSeparator . '12' . $fieldEnd
                . ' updated '
                . $fieldBegin . ' DATE \@ "MMMM d, yyyy" ' . $fieldSeparator . 'June 5, 2026' . $fieldEnd
                . ".\r"
            ),
        ]);

        $document = (new LegacyDocReader())->readBytes($docBytes)['document'];
        $markdown = (new MarkdownWriter())->write($document);
        $blocks = (new WordPressBlockWriter())->write($document);
        $paragraph = $document->children[0];

        $page = $paragraph->children[1];
        $t->same('span', $page->type);
        $t->same(['legacy-doc-field', 'legacy-doc-field-page'], $page->attr('classes'));
        $t->same('page', $page->attr('attributes')['data-legacy-doc-field']);
        $t->same('PAGE \* Arabic', $page->attr('attributes')['data-legacy-doc-field-instruction']);
        $t->same('Arabic', $page->attr('attributes')['data-legacy-doc-field-format']);
        $t->same('7', $page->children[0]->attr('text'));

        $pageCount = $paragraph->children[3];
        $t->same('numpages', $pageCount->attr('attributes')['data-legacy-doc-field']);
        $t->same('12', $pageCount->children[0]->attr('text'));

        $date = $paragraph->children[5];
        $t->same('date', $date->attr('attributes')['data-legacy-doc-field']);
        $t->same('DATE \@ "MMMM d, yyyy"', $date->attr('attributes')['data-legacy-doc-field-instruction']);
        $t->same('MMMM d, yyyy', $date->attr('attributes')['data-legacy-doc-field-format']);
        $t->same('June 5, 2026', $date->children[0]->attr('text'));

        $t->contains('Page [7]{.legacy-doc-field .legacy-doc-field-page data-legacy-doc-field="page" data-legacy-doc-field-instruction="PAGE \\\\* Arabic" data-legacy-doc-field-format="Arabic"} of', $markdown);
        $t->contains('updated [June 5, 2026]{.legacy-doc-field .legacy-doc-field-date data-legacy-doc-field="date" data-legacy-doc-field-instruction="DATE \\\\@ \\"MMMM d, yyyy\\"" data-legacy-doc-field-format="MMMM d, yyyy"}.', $markdown);
        $t->contains('<span class="legacy-doc-field legacy-doc-field-page" data-legacy-doc-field="page" data-legacy-doc-field-instruction="PAGE \* Arabic" data-legacy-doc-field-format="Arabic">7</span>', $blocks);
        $t->contains('<span class="legacy-doc-field legacy-doc-field-date" data-legacy-doc-field="date" data-legacy-doc-field-instruction="DATE \@ &quot;MMMM d, yyyy&quot;" data-legacy-doc-field-format="MMMM d, yyyy">June 5, 2026</span>', $blocks);
    },
    'preserves legacy DOC form-field provenance around displayed results' => static function (TestRunner $t) use ($buildCfb, $buildSimpleWordDocument): void {
        $fieldBegin = "\x13";
        $fieldSeparator = "\x14";
        $fieldEnd = "\x15";
        $docBytes = $buildCfb([
            'WordDocument' => $buildSimpleWordDocument(
                'Survey '
                . $fieldBegin . ' FORMTEXT \* MERGEFORMAT ' . $fieldSeparator . 'Alice Reviewer' . $fieldEnd
                . ', checkbox '
                . $fieldBegin . ' FORMCHECKBOX ' . $fieldSeparator . 'X' . $fieldEnd
                . ', choice '
                . $fieldBegin . ' FORMDROPDOWN ' . $fieldSeparator . 'Option B' . $fieldEnd
                . ".\r"
            ),
        ]);

        $document = (new LegacyDocReader())->readBytes($docBytes)['document'];
        $markdown = (new MarkdownWriter())->write($document);
        $blocks = (new WordPressBlockWriter())->write($document);
        $paragraph = $document->children[0];

        $textField = $paragraph->children[1];
        $t->same('span', $textField->type);
        $t->same(['legacy-doc-field', 'legacy-doc-form-field', 'legacy-doc-field-formtext'], $textField->attr('classes'));
        $t->same('formtext', $textField->attr('attributes')['data-legacy-doc-field']);
        $t->same('FORMTEXT \* MERGEFORMAT', $textField->attr('attributes')['data-legacy-doc-field-instruction']);
        $t->same('MERGEFORMAT', $textField->attr('attributes')['data-legacy-doc-field-format']);
        $t->same('text', $textField->attr('attributes')['data-legacy-doc-form-field-type']);
        $t->same('Alice Reviewer', $textField->children[0]->attr('text'));

        $checkbox = $paragraph->children[3];
        $t->same(['legacy-doc-field', 'legacy-doc-form-field', 'legacy-doc-field-formcheckbox'], $checkbox->attr('classes'));
        $t->same('formcheckbox', $checkbox->attr('attributes')['data-legacy-doc-field']);
        $t->same('checkbox', $checkbox->attr('attributes')['data-legacy-doc-form-field-type']);
        $t->same('true', $checkbox->attr('attributes')['data-legacy-doc-form-field-checked']);
        $t->same('X', $checkbox->children[0]->attr('text'));

        $dropdown = $paragraph->children[5];
        $t->same(['legacy-doc-field', 'legacy-doc-form-field', 'legacy-doc-field-formdropdown'], $dropdown->attr('classes'));
        $t->same('formdropdown', $dropdown->attr('attributes')['data-legacy-doc-field']);
        $t->same('dropdown', $dropdown->attr('attributes')['data-legacy-doc-form-field-type']);
        $t->same('Option B', $dropdown->children[0]->attr('text'));

        $t->contains('[Alice Reviewer]{.legacy-doc-field .legacy-doc-form-field .legacy-doc-field-formtext data-legacy-doc-field="formtext"', $markdown);
        $t->contains('[X]{.legacy-doc-field .legacy-doc-form-field .legacy-doc-field-formcheckbox data-legacy-doc-field="formcheckbox"', $markdown);
        $t->contains('[Option B]{.legacy-doc-field .legacy-doc-form-field .legacy-doc-field-formdropdown data-legacy-doc-field="formdropdown"', $markdown);
        $t->contains('<span class="legacy-doc-field legacy-doc-form-field legacy-doc-field-formtext" data-legacy-doc-field="formtext" data-legacy-doc-field-instruction="FORMTEXT \* MERGEFORMAT" data-legacy-doc-form-field-type="text" data-legacy-doc-field-format="MERGEFORMAT">Alice Reviewer</span>', $blocks);
        $t->contains('<span class="legacy-doc-field legacy-doc-form-field legacy-doc-field-formcheckbox" data-legacy-doc-field="formcheckbox" data-legacy-doc-field-instruction="FORMCHECKBOX" data-legacy-doc-form-field-type="checkbox" data-legacy-doc-form-field-checked="true">X</span>', $blocks);
        $t->contains('<span class="legacy-doc-field legacy-doc-form-field legacy-doc-field-formdropdown" data-legacy-doc-field="formdropdown" data-legacy-doc-field-instruction="FORMDROPDOWN" data-legacy-doc-form-field-type="dropdown">Option B</span>', $blocks);
        foreach (['FORMTEXT', 'FORMCHECKBOX', 'FORMDROPDOWN'] as $instruction) {
            $t->true(!str_contains(strip_tags($blocks), $instruction), 'Legacy DOC form field instructions should not render as visible text');
        }
    },
    'decodes legacy DOC FFData form-field options for review handoff metadata' => static function (TestRunner $t) use ($ffData): void {
        $reader = new LegacyDocReader();

        $textField = $reader->decodeFormFieldData($ffData([
            'fieldType' => 'text',
            'name' => 'ReviewerName',
            'defaultText' => 'Alice Reviewer',
            'textFormat' => 'Title Case',
            'helpText' => 'Enter reviewer display name.',
            'statusText' => 'Reviewer name for import audit.',
            'entryMacro' => 'AuditEnter',
            'exitMacro' => 'AuditExit',
            'maxLength' => 40,
            'textTypeCode' => 0,
            'hasOwnHelpText' => true,
            'hasOwnStatusText' => true,
            'protected' => true,
            'recalculateOnExit' => true,
        ]));
        $t->same('FFData', $textField['source']);
        $t->same('0xffffffff', $textField['versionHex']);
        $t->same('text', $textField['fieldType']);
        $t->same(0, $textField['fieldTypeCode']);
        $t->same(0, $textField['currentStateCode']);
        $t->same('ReviewerName', $textField['name']);
        $t->same('regular', $textField['textType']);
        $t->same(0, $textField['textTypeCode']);
        $t->same(40, $textField['maxLength']);
        $t->same('Alice Reviewer', $textField['defaultText']);
        $t->same('Title Case', $textField['textFormat']);
        $t->same('Enter reviewer display name.', $textField['helpText']);
        $t->same('Reviewer name for import audit.', $textField['statusText']);
        $t->same('AuditEnter', $textField['entryMacro']);
        $t->same('AuditExit', $textField['exitMacro']);
        $t->same(true, $textField['hasOwnHelpText']);
        $t->same(true, $textField['hasOwnStatusText']);
        $t->same(true, $textField['protected']);
        $t->same(true, $textField['recalculateOnExit']);
        $t->same(false, $textField['hasListBox']);
        $t->true($textField['byteCount'] > 0, 'FFData text field byte count should be retained');

        $checkbox = $reader->decodeFormFieldData($ffData([
            'fieldType' => 'checkbox',
            'name' => 'ApprovePacket',
            'defaultStateCode' => 1,
            'currentStateCode' => 25,
            'checkboxSizeHalfPoints' => 24,
            'checkboxAutoSize' => true,
            'protected' => true,
        ]));
        $t->same('checkbox', $checkbox['fieldType']);
        $t->same('ApprovePacket', $checkbox['name']);
        $t->same(1, $checkbox['defaultStateCode']);
        $t->same(25, $checkbox['currentStateCode']);
        $t->same(true, $checkbox['checkboxAutoSize']);
        $t->same(24, $checkbox['checkboxSizeHalfPoints']);
        $t->same(true, $checkbox['defaultChecked']);
        $t->same(false, $checkbox['checked']);
        $t->same('undefined', $checkbox['checkboxState']);
        $t->same(true, $checkbox['protected']);

        $dropdown = $reader->decodeFormFieldData($ffData([
            'fieldType' => 'dropdown',
            'name' => 'ImportState',
            'defaultStateCode' => 1,
            'currentStateCode' => 2,
            'dropDownItems' => ['Draft', 'Review', 'Publish'],
            'helpText' => 'Choose the publication state.',
        ]));
        $t->same('dropdown', $dropdown['fieldType']);
        $t->same('ImportState', $dropdown['name']);
        $t->same(1, $dropdown['defaultSelectedIndex']);
        $t->same(2, $dropdown['selectedIndex']);
        $t->same(false, $dropdown['selectionUndefined']);
        $t->same(['Draft', 'Review', 'Publish'], $dropdown['dropDownItems']);
        $t->same(3, $dropdown['dropDownItemCount']);
        $t->same('Review', $dropdown['defaultDropDownItem']);
        $t->same('Publish', $dropdown['selectedDropDownItem']);
        $t->same('Choose the publication state.', $dropdown['helpText']);
        $t->same(true, $dropdown['hasListBox']);

        $t->throws(\RuntimeException::class, static fn (): array => $reader->decodeFormFieldData(substr_replace($ffData([
            'fieldType' => 'text',
            'name' => 'BadVersion',
        ]), "\0\0\0\0", 0, 4)));
        $t->throws(\RuntimeException::class, static fn (): array => $reader->decodeFormFieldData($ffData([
            'fieldType' => 'dropdown',
            'name' => 'BadDefault',
            'defaultStateCode' => 4,
            'currentStateCode' => 0,
            'dropDownItems' => ['Only'],
        ])));
        $t->throws(\RuntimeException::class, static fn (): array => $reader->decodeFormFieldData($ffData([
            'fieldType' => 'checkbox',
            'name' => 'BadFormat',
            'defaultStateCode' => 1,
            'currentStateCode' => 1,
            'textFormat' => '0.00',
        ])));
        $truncated = substr($ffData([
            'fieldType' => 'text',
            'name' => 'Truncated',
            'defaultText' => 'value',
        ]), 0, -1);
        $t->throws(\RuntimeException::class, static fn (): array => $reader->decodeFormFieldData($truncated));
    },
    'extracts legacy DOC Plcfld field tables for form-field handoff metadata' => static function (TestRunner $t) use ($buildCfb, $buildSimpleWordDocument, $plcfldMom, $u32): void {
        $fieldBegin = "\x13";
        $fieldSeparator = "\x14";
        $fieldEnd = "\x15";
        $text = 'Survey '
            . $fieldBegin . ' FORMTEXT \* MERGEFORMAT ' . $fieldSeparator . 'Alice Reviewer' . $fieldEnd
            . ', checkbox '
            . $fieldBegin . ' FORMCHECKBOX ' . $fieldSeparator . 'X' . $fieldEnd
            . ', choice '
            . $fieldBegin . ' FORMDROPDOWN ' . $fieldSeparator . 'Option B' . $fieldEnd
            . ".\r";

        $firstBegin = strpos($text, $fieldBegin);
        $firstSeparator = strpos($text, $fieldSeparator, (int) $firstBegin);
        $firstEnd = strpos($text, $fieldEnd, (int) $firstSeparator);
        $secondBegin = strpos($text, $fieldBegin, (int) $firstEnd + 1);
        $secondSeparator = strpos($text, $fieldSeparator, (int) $secondBegin);
        $secondEnd = strpos($text, $fieldEnd, (int) $secondSeparator);
        $thirdBegin = strpos($text, $fieldBegin, (int) $secondEnd + 1);
        $thirdSeparator = strpos($text, $fieldSeparator, (int) $thirdBegin);
        $thirdEnd = strpos($text, $fieldEnd, (int) $thirdSeparator);
        foreach ([$firstBegin, $firstSeparator, $firstEnd, $secondBegin, $secondSeparator, $secondEnd, $thirdBegin, $thirdSeparator, $thirdEnd] as $cp) {
            if (!is_int($cp)) {
                throw new RuntimeException('Unable to locate legacy DOC field marker fixture');
            }
        }

        $fieldTable = $plcfldMom([
            ['cp' => $firstBegin, 'character' => 0x13, 'typeCode' => 0x46],
            ['cp' => $firstSeparator, 'character' => 0x14],
            ['cp' => $firstEnd, 'character' => 0x15],
            ['cp' => $secondBegin, 'character' => 0x13, 'typeCode' => 0x47],
            ['cp' => $secondSeparator, 'character' => 0x14],
            ['cp' => $secondEnd, 'character' => 0x15],
            ['cp' => $thirdBegin, 'character' => 0x13, 'typeCode' => 0x53],
            ['cp' => $thirdSeparator, 'character' => 0x14],
            ['cp' => $thirdEnd, 'character' => 0x15],
        ], strlen($text));
        $wordDocument = $buildSimpleWordDocument($text);
        $wordDocument = substr_replace($wordDocument, $u32(0), 0x011a, 4);
        $wordDocument = substr_replace($wordDocument, $u32(strlen($fieldTable)), 0x011e, 4);

        $result = (new LegacyDocReader())->readBytes($buildCfb([
            'WordDocument' => $wordDocument,
            '0Table' => $fieldTable,
        ]));
        $metadata = $result['metadata'];
        $document = $result['document'];
        $fields = $result['fields'];
        $fieldCharacters = $result['fieldCharacters'];
        $blocks = (new WordPressBlockWriter())->write($document);

        $t->same(9, $metadata['fieldCharacterCount']);
        $t->same(3, $metadata['fieldCount']);
        $t->same($fields, $metadata['fields']);
        $t->same($fields, $document->attr('fields'));
        $t->same($fieldCharacters, $document->attr('fieldCharacters'));
        $t->same('formtext', $fields[0]['type']);
        $t->same(0x46, $fields[0]['typeCode']);
        $t->same($firstBegin, $fields[0]['beginCp']);
        $t->same($firstSeparator, $fields[0]['separatorCp']);
        $t->same($firstEnd, $fields[0]['endCp']);
        $t->same($firstBegin + 1, $fields[0]['instructionStartCp']);
        $t->same($firstSeparator, $fields[0]['instructionEndCp']);
        $t->same($firstSeparator + 1, $fields[0]['resultStartCp']);
        $t->same($firstEnd, $fields[0]['resultEndCp']);
        $t->same(true, $fields[0]['hasResult']);
        $t->same('formcheckbox', $fields[1]['type']);
        $t->same(0x47, $fields[1]['typeCode']);
        $t->same('formdropdown', $fields[2]['type']);
        $t->same(0x53, $fields[2]['typeCode']);
        $t->same('begin', $fieldCharacters[0]['kind']);
        $t->same($firstBegin, $fieldCharacters[0]['cp']);
        $t->same(0x46, $fieldCharacters[0]['typeCode']);
        $t->same('formtext', $fieldCharacters[0]['type']);
        $t->same('separator', $fieldCharacters[1]['kind']);
        $t->same($firstSeparator, $fieldCharacters[1]['cp']);
        $t->same('end', $fieldCharacters[2]['kind']);
        $t->same($firstEnd, $fieldCharacters[2]['cp']);
        $t->contains('data-legacy-doc-form-field-type="text"', $blocks);
        $t->contains('data-legacy-doc-form-field-type="checkbox"', $blocks);
        $t->contains('data-legacy-doc-form-field-type="dropdown"', $blocks);
    },
    'preserves legacy DOC Plcfld end flags for field review metadata' => static function (TestRunner $t) use ($buildCfb, $buildSimpleWordDocument, $plcfldMom, $u32): void {
        $fieldBegin = "\x13";
        $fieldSeparator = "\x14";
        $fieldEnd = "\x15";
        $text = 'Flags '
            . $fieldBegin . ' PAGE \* Arabic ' . $fieldSeparator . '5' . $fieldEnd
            . ' hidden '
            . $fieldBegin . ' SET Sign "opaque signature bytes" ' . $fieldEnd
            . ".\r";

        $pageBegin = strpos($text, $fieldBegin);
        $pageSeparator = strpos($text, $fieldSeparator, (int) $pageBegin);
        $pageEnd = strpos($text, $fieldEnd, (int) $pageSeparator);
        $setBegin = strpos($text, $fieldBegin, (int) $pageEnd + 1);
        $setEnd = strpos($text, $fieldEnd, (int) $setBegin);
        foreach ([$pageBegin, $pageSeparator, $pageEnd, $setBegin, $setEnd] as $cp) {
            if (!is_int($cp)) {
                throw new RuntimeException('Unable to locate legacy DOC Plcfld end-flag fixture');
            }
        }

        $fieldTable = $plcfldMom([
            ['cp' => $pageBegin, 'character' => 0x13, 'typeCode' => 0x21],
            ['cp' => $pageSeparator, 'character' => 0x14],
            ['cp' => $pageEnd, 'character' => 0x15, 'endFlags' => 0x9c],
            ['cp' => $setBegin, 'character' => 0x13, 'typeCode' => 0x06],
            ['cp' => $setEnd, 'character' => 0x15, 'endFlags' => 0x20],
        ], strlen($text));
        $wordDocument = $buildSimpleWordDocument($text);
        $wordDocument = substr_replace($wordDocument, $u32(0), 0x011a, 4);
        $wordDocument = substr_replace($wordDocument, $u32(strlen($fieldTable)), 0x011e, 4);

        $result = (new LegacyDocReader())->readBytes($buildCfb([
            'WordDocument' => $wordDocument,
            '0Table' => $fieldTable,
        ]));
        $fields = $result['fields'];
        $fieldCharacters = $result['fieldCharacters'];
        $paragraph = $result['document']->children[0];
        $blocks = (new WordPressBlockWriter())->write($result['document']);

        $t->same(5, $result['metadata']['fieldCharacterCount']);
        $t->same(2, $result['metadata']['fieldCount']);
        $t->same('page', $fields[0]['type']);
        $t->same(0x9c, $fields[0]['endFlags']);
        $t->same(['result-dirty', 'result-edited', 'locked', 'has-separator'], $fields[0]['endFlagNames']);
        $t->same(true, $fields[0]['resultDirty']);
        $t->same(true, $fields[0]['resultEdited']);
        $t->same(true, $fields[0]['locked']);
        $t->same(false, $fields[0]['privateResult']);
        $t->same(true, $fields[0]['hasSeparatorFlag']);
        $t->same(true, $fields[0]['separatorFlagMatchesRange']);
        $t->same(0x9c, $fieldCharacters[2]['endFlags']);
        $t->same(true, $fieldCharacters[2]['locked']);
        $t->same(true, $fieldCharacters[2]['hasSeparatorFlag']);
        $t->same('set', $fields[1]['type']);
        $t->same(0x20, $fields[1]['endFlags']);
        $t->same(['private-result'], $fields[1]['endFlagNames']);
        $t->same(true, $fields[1]['privateResult']);
        $t->same(false, $fields[1]['hasResult']);
        $t->same(false, $fields[1]['hasSeparatorFlag']);
        $t->same(true, $fields[1]['separatorFlagMatchesRange']);
        $t->same(0x20, $fieldCharacters[4]['endFlags']);
        $t->same(true, $fieldCharacters[4]['privateResult']);

        $signatureSet = $paragraph->children[3];
        $t->same('SET Sign [redacted]', $signatureSet->attr('attributes')['data-legacy-doc-field-instruction']);
        $t->same('signature-blob-metadata-only', $signatureSet->attr('attributes')['data-legacy-doc-set-field-policy']);
        $t->true(!str_contains($blocks, 'opaque signature bytes'), 'Legacy DOC private-result SET values should stay redacted from WordPress blocks');
    },
    'rejects malformed legacy DOC Plcfld field tables before exposing metadata' => static function (TestRunner $t) use ($buildCfb, $buildSimpleWordDocument, $plcfldMom, $u32): void {
        $text = "Broken \x13 PAGE \x147\x15\r";
        $begin = strpos($text, "\x13");
        $separator = strpos($text, "\x14");
        $end = strpos($text, "\x15");
        if (!is_int($begin) || !is_int($separator) || !is_int($end)) {
            throw new RuntimeException('Unable to locate malformed field marker fixture');
        }

        $buildDocBytes = static function (string $fieldTable) use ($buildCfb, $buildSimpleWordDocument, $text, $u32): string {
            $wordDocument = $buildSimpleWordDocument($text);
            $wordDocument = substr_replace($wordDocument, $u32(0), 0x011a, 4);
            $wordDocument = substr_replace($wordDocument, $u32(strlen($fieldTable)), 0x011e, 4);

            return $buildCfb([
                'WordDocument' => $wordDocument,
                '0Table' => $fieldTable,
            ]);
        };

        $valid = $plcfldMom([
            ['cp' => $begin, 'character' => 0x13, 'typeCode' => 0x21],
            ['cp' => $separator, 'character' => 0x14],
            ['cp' => $end, 'character' => 0x15],
        ], strlen($text));
        $mismatchedCharacter = substr_replace($valid, "\x14", 16, 1);
        $unsortedCps = $plcfldMom([
            ['cp' => $separator, 'character' => 0x14],
            ['cp' => $begin, 'character' => 0x13, 'typeCode' => 0x21],
            ['cp' => $end, 'character' => 0x15],
        ], strlen($text));
        $separatorOutsideField = $plcfldMom([
            ['cp' => $separator, 'character' => 0x14],
            ['cp' => $end, 'character' => 0x15],
        ], strlen($text));

        $reader = new LegacyDocReader();
        $t->throws(\RuntimeException::class, static fn (): array => $reader->readBytes($buildDocBytes($mismatchedCharacter)));
        $t->throws(\RuntimeException::class, static fn (): array => $reader->readBytes($buildDocBytes($unsortedCps)));
        $t->throws(\RuntimeException::class, static fn (): array => $reader->readBytes($buildDocBytes($separatorOutsideField)));
    },
    'preserves legacy DOC cross-reference field provenance around displayed results' => static function (TestRunner $t) use ($buildCfb, $buildSimpleWordDocument): void {
        $fieldBegin = "\x13";
        $fieldSeparator = "\x14";
        $fieldEnd = "\x15";
        $docBytes = $buildCfb([
            'WordDocument' => $buildSimpleWordDocument(
                'See '
                . $fieldBegin . ' REF "legacy_anchor" \h ' . $fieldSeparator . 'Legacy DOC import' . $fieldEnd
                . ' on page '
                . $fieldBegin . ' PAGEREF legacy_anchor \p ' . $fieldSeparator . '7' . $fieldEnd
                . ' and note '
                . $fieldBegin . ' NOTEREF "_RefNote" \f \h ' . $fieldSeparator . '1' . $fieldEnd
                . ".\r"
            ),
        ]);

        $document = (new LegacyDocReader())->readBytes($docBytes)['document'];
        $markdown = (new MarkdownWriter())->write($document);
        $blocks = (new WordPressBlockWriter())->write($document);
        $paragraph = $document->children[0];

        $reference = $paragraph->children[1];
        $t->same('span', $reference->type);
        $t->same(['legacy-doc-field', 'legacy-doc-cross-reference', 'legacy-doc-field-ref'], $reference->attr('classes'));
        $t->same('ref', $reference->attr('attributes')['data-legacy-doc-field']);
        $t->same('REF "legacy_anchor" \h', $reference->attr('attributes')['data-legacy-doc-field-instruction']);
        $t->same('bookmark', $reference->attr('attributes')['data-legacy-doc-cross-reference-type']);
        $t->same('legacy_anchor', $reference->attr('attributes')['data-legacy-doc-cross-reference-target']);
        $t->same('h', $reference->attr('attributes')['data-legacy-doc-cross-reference-switches']);
        $t->same('true', $reference->attr('attributes')['data-legacy-doc-cross-reference-hyperlink']);
        $t->same('Legacy DOC import', $reference->children[0]->attr('text'));

        $pageReference = $paragraph->children[3];
        $t->same(['legacy-doc-field', 'legacy-doc-cross-reference', 'legacy-doc-field-pageref'], $pageReference->attr('classes'));
        $t->same('pageref', $pageReference->attr('attributes')['data-legacy-doc-field']);
        $t->same('bookmark-page', $pageReference->attr('attributes')['data-legacy-doc-cross-reference-type']);
        $t->same('legacy_anchor', $pageReference->attr('attributes')['data-legacy-doc-cross-reference-target']);
        $t->same('p', $pageReference->attr('attributes')['data-legacy-doc-cross-reference-switches']);
        $t->same('true', $pageReference->attr('attributes')['data-legacy-doc-cross-reference-relative']);
        $t->same('7', $pageReference->children[0]->attr('text'));

        $noteReference = $paragraph->children[5];
        $t->same(['legacy-doc-field', 'legacy-doc-cross-reference', 'legacy-doc-field-noteref'], $noteReference->attr('classes'));
        $t->same('noteref', $noteReference->attr('attributes')['data-legacy-doc-field']);
        $t->same('NOTEREF "_RefNote" \f \h', $noteReference->attr('attributes')['data-legacy-doc-field-instruction']);
        $t->same('note', $noteReference->attr('attributes')['data-legacy-doc-cross-reference-type']);
        $t->same('_RefNote', $noteReference->attr('attributes')['data-legacy-doc-cross-reference-target']);
        $t->same('f h', $noteReference->attr('attributes')['data-legacy-doc-cross-reference-switches']);
        $t->same('true', $noteReference->attr('attributes')['data-legacy-doc-cross-reference-hyperlink']);
        $t->same('1', $noteReference->children[0]->attr('text'));

        $t->contains('[Legacy DOC import]{.legacy-doc-field .legacy-doc-cross-reference .legacy-doc-field-ref data-legacy-doc-field="ref"', $markdown);
        $t->contains('[7]{.legacy-doc-field .legacy-doc-cross-reference .legacy-doc-field-pageref data-legacy-doc-field="pageref"', $markdown);
        $t->contains('[1]{.legacy-doc-field .legacy-doc-cross-reference .legacy-doc-field-noteref data-legacy-doc-field="noteref"', $markdown);
        $t->contains('<span class="legacy-doc-field legacy-doc-cross-reference legacy-doc-field-ref" data-legacy-doc-field="ref" data-legacy-doc-field-instruction="REF &quot;legacy_anchor&quot; \h" data-legacy-doc-cross-reference-type="bookmark" data-legacy-doc-cross-reference-target="legacy_anchor" data-legacy-doc-cross-reference-switches="h" data-legacy-doc-cross-reference-hyperlink="true">Legacy DOC import</span>', $blocks);
        $t->contains('<span class="legacy-doc-field legacy-doc-cross-reference legacy-doc-field-pageref" data-legacy-doc-field="pageref" data-legacy-doc-field-instruction="PAGEREF legacy_anchor \p" data-legacy-doc-cross-reference-type="bookmark-page" data-legacy-doc-cross-reference-target="legacy_anchor" data-legacy-doc-cross-reference-switches="p" data-legacy-doc-cross-reference-relative="true">7</span>', $blocks);
        $t->contains('<span class="legacy-doc-field legacy-doc-cross-reference legacy-doc-field-noteref" data-legacy-doc-field="noteref" data-legacy-doc-field-instruction="NOTEREF &quot;_RefNote&quot; \f \h" data-legacy-doc-cross-reference-type="note" data-legacy-doc-cross-reference-target="_RefNote" data-legacy-doc-cross-reference-switches="f h" data-legacy-doc-cross-reference-hyperlink="true">1</span>', $blocks);
        foreach (['REF', 'PAGEREF', 'NOTEREF'] as $instruction) {
            $t->true(!str_contains(strip_tags($blocks), $instruction), 'Legacy DOC cross-reference instructions should not render as visible text');
        }
    },
    'preserves legacy DOC merge and document-variable field provenance around displayed results' => static function (TestRunner $t) use ($buildCfb, $buildSimpleWordDocument): void {
        $fieldBegin = "\x13";
        $fieldSeparator = "\x14";
        $fieldEnd = "\x15";
        $docBytes = $buildCfb([
            'WordDocument' => $buildSimpleWordDocument(
                'Dear '
                . $fieldBegin . ' MERGEFIELD "Customer Name" \b "before " \f " after" \* MERGEFORMAT ' . $fieldSeparator . 'Ada Lovelace' . $fieldEnd
                . ', batch '
                . $fieldBegin . ' DOCVARIABLE MigrationBatch \* Upper ' . $fieldSeparator . 'LEGACY-DOC-42' . $fieldEnd
                . ".\r"
            ),
        ]);

        $document = (new LegacyDocReader())->readBytes($docBytes)['document'];
        $markdown = (new MarkdownWriter())->write($document);
        $blocks = (new WordPressBlockWriter())->write($document);
        $paragraph = $document->children[0];

        $mergeField = $paragraph->children[1];
        $t->same('span', $mergeField->type);
        $t->same(['legacy-doc-field', 'legacy-doc-data-field', 'legacy-doc-field-mergefield'], $mergeField->attr('classes'));
        $t->same('mergefield', $mergeField->attr('attributes')['data-legacy-doc-field']);
        $t->same('MERGEFIELD "Customer Name" \b "before " \f " after" \* MERGEFORMAT', $mergeField->attr('attributes')['data-legacy-doc-field-instruction']);
        $t->same('mail-merge', $mergeField->attr('attributes')['data-legacy-doc-data-field-type']);
        $t->same('Customer Name', $mergeField->attr('attributes')['data-legacy-doc-data-field-name']);
        $t->same('MERGEFORMAT', $mergeField->attr('attributes')['data-legacy-doc-field-format']);
        $t->same('before ', $mergeField->attr('attributes')['data-legacy-doc-data-field-prefix']);
        $t->same(' after', $mergeField->attr('attributes')['data-legacy-doc-data-field-suffix']);
        $t->same('b f', $mergeField->attr('attributes')['data-legacy-doc-data-field-switches']);
        $t->same('Ada Lovelace', $mergeField->children[0]->attr('text'));

        $docVariable = $paragraph->children[3];
        $t->same('span', $docVariable->type);
        $t->same(['legacy-doc-field', 'legacy-doc-data-field', 'legacy-doc-field-docvariable'], $docVariable->attr('classes'));
        $t->same('docvariable', $docVariable->attr('attributes')['data-legacy-doc-field']);
        $t->same('document-variable', $docVariable->attr('attributes')['data-legacy-doc-data-field-type']);
        $t->same('MigrationBatch', $docVariable->attr('attributes')['data-legacy-doc-data-field-name']);
        $t->same('Upper', $docVariable->attr('attributes')['data-legacy-doc-field-format']);
        $t->same('LEGACY-DOC-42', $docVariable->children[0]->attr('text'));

        $t->contains('[Ada Lovelace]{.legacy-doc-field .legacy-doc-data-field .legacy-doc-field-mergefield data-legacy-doc-field="mergefield"', $markdown);
        $t->contains('data-legacy-doc-data-field-name="Customer Name"', $markdown);
        $t->contains('[LEGACY-DOC-42]{.legacy-doc-field .legacy-doc-data-field .legacy-doc-field-docvariable data-legacy-doc-field="docvariable"', $markdown);
        $t->contains('<span class="legacy-doc-field legacy-doc-data-field legacy-doc-field-mergefield" data-legacy-doc-field="mergefield" data-legacy-doc-field-instruction="MERGEFIELD &quot;Customer Name&quot; \b &quot;before &quot; \f &quot; after&quot; \* MERGEFORMAT" data-legacy-doc-data-field-type="mail-merge" data-legacy-doc-data-field-name="Customer Name" data-legacy-doc-field-format="MERGEFORMAT" data-legacy-doc-data-field-prefix="before " data-legacy-doc-data-field-suffix=" after" data-legacy-doc-data-field-switches="b f">Ada Lovelace</span>', $blocks);
        $t->contains('<span class="legacy-doc-field legacy-doc-data-field legacy-doc-field-docvariable" data-legacy-doc-field="docvariable" data-legacy-doc-field-instruction="DOCVARIABLE MigrationBatch \* Upper" data-legacy-doc-data-field-type="document-variable" data-legacy-doc-data-field-name="MigrationBatch" data-legacy-doc-field-format="Upper">LEGACY-DOC-42</span>', $blocks);
        foreach (['MERGEFIELD', 'DOCVARIABLE'] as $instruction) {
            $t->true(!str_contains(strip_tags($blocks), $instruction), 'Legacy DOC data field instructions should not render as visible text');
        }
    },
    'preserves legacy DOC SET field assignments as hidden handoff metadata' => static function (TestRunner $t) use ($buildCfb, $buildSimpleWordDocument, $plcfldMom, $u32): void {
        $fieldBegin = "\x13";
        $fieldSeparator = "\x14";
        $fieldEnd = "\x15";
        $text = 'Variables '
            . $fieldBegin . ' SET "MigrationBatch" "legacy-doc-42" \* MERGEFORMAT ' . $fieldEnd
            . ' '
            . $fieldBegin . ' SET Sign "opaque signature bytes" ' . $fieldEnd
            . ' show '
            . $fieldBegin . ' DOCVARIABLE MigrationBatch ' . $fieldSeparator . 'legacy-doc-42' . $fieldEnd
            . ".\r";

        $firstBegin = strpos($text, $fieldBegin);
        $firstEnd = strpos($text, $fieldEnd, (int) $firstBegin);
        $secondBegin = strpos($text, $fieldBegin, (int) $firstEnd + 1);
        $secondEnd = strpos($text, $fieldEnd, (int) $secondBegin);
        $thirdBegin = strpos($text, $fieldBegin, (int) $secondEnd + 1);
        $thirdSeparator = strpos($text, $fieldSeparator, (int) $thirdBegin);
        $thirdEnd = strpos($text, $fieldEnd, (int) $thirdSeparator);
        foreach ([$firstBegin, $firstEnd, $secondBegin, $secondEnd, $thirdBegin, $thirdSeparator, $thirdEnd] as $cp) {
            if (!is_int($cp)) {
                throw new RuntimeException('Unable to locate legacy DOC SET field fixture');
            }
        }

        $fieldTable = $plcfldMom([
            ['cp' => $firstBegin, 'character' => 0x13, 'typeCode' => 0x06],
            ['cp' => $firstEnd, 'character' => 0x15],
            ['cp' => $secondBegin, 'character' => 0x13, 'typeCode' => 0x06],
            ['cp' => $secondEnd, 'character' => 0x15],
            ['cp' => $thirdBegin, 'character' => 0x13, 'typeCode' => 0x40],
            ['cp' => $thirdSeparator, 'character' => 0x14],
            ['cp' => $thirdEnd, 'character' => 0x15],
        ], strlen($text));
        $wordDocument = $buildSimpleWordDocument($text);
        $wordDocument = substr_replace($wordDocument, $u32(0), 0x011a, 4);
        $wordDocument = substr_replace($wordDocument, $u32(strlen($fieldTable)), 0x011e, 4);

        $result = (new LegacyDocReader())->readBytes($buildCfb([
            'WordDocument' => $wordDocument,
            '0Table' => $fieldTable,
        ]));
        $document = $result['document'];
        $paragraph = $document->children[0];
        $fields = $result['fields'];
        $fieldCharacters = $result['fieldCharacters'];
        $markdown = (new MarkdownWriter())->write($document);
        $blocks = (new WordPressBlockWriter())->write($document);

        $t->same(7, $result['metadata']['fieldCharacterCount']);
        $t->same(3, $result['metadata']['fieldCount']);
        $t->same($fields, $result['metadata']['fields']);
        $t->same($fields, $document->attr('fields'));
        $t->same('set', $fields[0]['type']);
        $t->same(0x06, $fields[0]['typeCode']);
        $t->same(false, $fields[0]['hasResult']);
        $t->same($firstBegin, $fields[0]['beginCp']);
        $t->same($firstEnd, $fields[0]['endCp']);
        $t->same($firstEnd, $fields[0]['instructionEndCp']);
        $t->same('set', $fields[1]['type']);
        $t->same(0x06, $fieldCharacters[0]['typeCode']);
        $t->same('set', $fieldCharacters[0]['type']);
        $t->same('docvariable', $fields[2]['type']);

        $set = $paragraph->children[1];
        $t->same('span', $set->type);
        $t->same([], $set->children);
        $t->same(['legacy-doc-field', 'legacy-doc-set-field', 'legacy-doc-field-set'], $set->attr('classes'));
        $t->same('set', $set->attr('attributes')['data-legacy-doc-field']);
        $t->same('SET "MigrationBatch" "legacy-doc-42" \* MERGEFORMAT', $set->attr('attributes')['data-legacy-doc-field-instruction']);
        $t->same('document-variable-assignment', $set->attr('attributes')['data-legacy-doc-set-field-type']);
        $t->same('MigrationBatch', $set->attr('attributes')['data-legacy-doc-set-field-name']);
        $t->same('MERGEFORMAT', $set->attr('attributes')['data-legacy-doc-field-format']);
        $t->same('13', $set->attr('attributes')['data-legacy-doc-set-field-value-character-count']);
        $t->same('legacy-doc-42', $set->attr('attributes')['data-legacy-doc-set-field-value']);

        $signatureSet = $paragraph->children[3];
        $t->same('span', $signatureSet->type);
        $t->same([], $signatureSet->children);
        $t->same('SET Sign [redacted]', $signatureSet->attr('attributes')['data-legacy-doc-field-instruction']);
        $t->same('Sign', $signatureSet->attr('attributes')['data-legacy-doc-set-field-name']);
        $t->same('true', $signatureSet->attr('attributes')['data-legacy-doc-field-instruction-redacted']);
        $t->same('true', $signatureSet->attr('attributes')['data-legacy-doc-set-field-redacted']);
        $t->same('signature-blob-metadata-only', $signatureSet->attr('attributes')['data-legacy-doc-set-field-policy']);
        $t->same('22', $signatureSet->attr('attributes')['data-legacy-doc-set-field-value-character-count']);
        $t->true(!isset($signatureSet->attr('attributes')['data-legacy-doc-set-field-value']), 'Signature SET field values should stay redacted');

        $docVariable = $paragraph->children[5];
        $t->same('span', $docVariable->type);
        $t->same('docvariable', $docVariable->attr('attributes')['data-legacy-doc-field']);
        $t->same('MigrationBatch', $docVariable->attr('attributes')['data-legacy-doc-data-field-name']);
        $t->same('legacy-doc-42', $docVariable->children[0]->attr('text'));

        $t->contains('[]{.legacy-doc-field .legacy-doc-set-field .legacy-doc-field-set data-legacy-doc-field="set"', $markdown);
        $t->contains('data-legacy-doc-set-field-name="MigrationBatch"', $markdown);
        $t->contains('data-legacy-doc-set-field-policy="signature-blob-metadata-only"', $markdown);
        $t->contains('<span class="legacy-doc-field legacy-doc-set-field legacy-doc-field-set" data-legacy-doc-field="set" data-legacy-doc-field-instruction="SET &quot;MigrationBatch&quot; &quot;legacy-doc-42&quot; \* MERGEFORMAT" data-legacy-doc-set-field-type="document-variable-assignment" data-legacy-doc-set-field-name="MigrationBatch" data-legacy-doc-field-format="MERGEFORMAT" data-legacy-doc-set-field-value-character-count="13" data-legacy-doc-set-field-value="legacy-doc-42"></span>', $blocks);
        $t->contains('<span class="legacy-doc-field legacy-doc-set-field legacy-doc-field-set" data-legacy-doc-field="set" data-legacy-doc-field-instruction="SET Sign [redacted]" data-legacy-doc-set-field-type="document-variable-assignment" data-legacy-doc-set-field-name="Sign" data-legacy-doc-field-instruction-redacted="true" data-legacy-doc-set-field-value-character-count="22" data-legacy-doc-set-field-redacted="true" data-legacy-doc-set-field-policy="signature-blob-metadata-only"></span>', $blocks);
        $t->true(!str_contains(strip_tags($blocks), 'SET'), 'Legacy DOC SET field instructions should not render as visible text');
        $t->true(!str_contains(strip_tags($blocks), 'MigrationBatch'), 'Legacy DOC SET field names should not render as visible text');
        $t->true(!str_contains($blocks, 'opaque signature bytes'), 'Legacy DOC signature SET values should not render in WordPress blocks');
        $t->true(!str_contains($markdown, 'opaque signature bytes'), 'Legacy DOC signature SET values should not render in Markdown');
    },
    'preserves legacy DOC prompt field provenance around displayed results' => static function (TestRunner $t) use ($buildCfb, $buildSimpleWordDocument): void {
        $fieldBegin = "\x13";
        $fieldSeparator = "\x14";
        $fieldEnd = "\x15";
        $docBytes = $buildCfb([
            'WordDocument' => $buildSimpleWordDocument(
                'Prompt '
                . $fieldBegin . ' ASK ReviewOwner "Who owns this packet?" \d "Mira" \o ' . $fieldSeparator . 'Mira Reviewer' . $fieldEnd
                . '; fill '
                . $fieldBegin . ' FILLIN "Migration note?" \d "Needs QA" ' . $fieldSeparator . 'Ready for WordPress' . $fieldEnd
                . ".\r"
            ),
        ]);

        $document = (new LegacyDocReader())->readBytes($docBytes)['document'];
        $markdown = (new MarkdownWriter())->write($document);
        $blocks = (new WordPressBlockWriter())->write($document);
        $paragraph = $document->children[0];

        $ask = $paragraph->children[1];
        $t->same('span', $ask->type);
        $t->same(['legacy-doc-field', 'legacy-doc-prompt-field', 'legacy-doc-field-ask'], $ask->attr('classes'));
        $t->same('ask', $ask->attr('attributes')['data-legacy-doc-field']);
        $t->same('ASK ReviewOwner "Who owns this packet?" \d "Mira" \o', $ask->attr('attributes')['data-legacy-doc-field-instruction']);
        $t->same('bookmark-prompt', $ask->attr('attributes')['data-legacy-doc-prompt-field-type']);
        $t->same('ReviewOwner', $ask->attr('attributes')['data-legacy-doc-prompt-field-name']);
        $t->same('Who owns this packet?', $ask->attr('attributes')['data-legacy-doc-prompt-text']);
        $t->same('Mira', $ask->attr('attributes')['data-legacy-doc-prompt-default']);
        $t->same('d o', $ask->attr('attributes')['data-legacy-doc-prompt-switches']);
        $t->same('Mira Reviewer', $ask->children[0]->attr('text'));

        $fillIn = $paragraph->children[3];
        $t->same('span', $fillIn->type);
        $t->same(['legacy-doc-field', 'legacy-doc-prompt-field', 'legacy-doc-field-fillin'], $fillIn->attr('classes'));
        $t->same('fillin', $fillIn->attr('attributes')['data-legacy-doc-field']);
        $t->same('prompt', $fillIn->attr('attributes')['data-legacy-doc-prompt-field-type']);
        $t->same('Migration note?', $fillIn->attr('attributes')['data-legacy-doc-prompt-text']);
        $t->same('Needs QA', $fillIn->attr('attributes')['data-legacy-doc-prompt-default']);
        $t->same('d', $fillIn->attr('attributes')['data-legacy-doc-prompt-switches']);
        $t->same('Ready for WordPress', $fillIn->children[0]->attr('text'));

        $t->contains('[Mira Reviewer]{.legacy-doc-field .legacy-doc-prompt-field .legacy-doc-field-ask data-legacy-doc-field="ask"', $markdown);
        $t->contains('data-legacy-doc-prompt-field-name="ReviewOwner"', $markdown);
        $t->contains('[Ready for WordPress]{.legacy-doc-field .legacy-doc-prompt-field .legacy-doc-field-fillin data-legacy-doc-field="fillin"', $markdown);
        $t->contains('<span class="legacy-doc-field legacy-doc-prompt-field legacy-doc-field-ask" data-legacy-doc-field="ask" data-legacy-doc-field-instruction="ASK ReviewOwner &quot;Who owns this packet?&quot; \d &quot;Mira&quot; \o" data-legacy-doc-prompt-field-type="bookmark-prompt" data-legacy-doc-prompt-field-name="ReviewOwner" data-legacy-doc-prompt-text="Who owns this packet?" data-legacy-doc-prompt-default="Mira" data-legacy-doc-prompt-switches="d o">Mira Reviewer</span>', $blocks);
        $t->contains('<span class="legacy-doc-field legacy-doc-prompt-field legacy-doc-field-fillin" data-legacy-doc-field="fillin" data-legacy-doc-field-instruction="FILLIN &quot;Migration note?&quot; \d &quot;Needs QA&quot;" data-legacy-doc-prompt-field-type="prompt" data-legacy-doc-prompt-text="Migration note?" data-legacy-doc-prompt-default="Needs QA" data-legacy-doc-prompt-switches="d">Ready for WordPress</span>', $blocks);
        foreach (['ASK', 'FILLIN'] as $instruction) {
            $t->true(!str_contains(strip_tags($blocks), $instruction), 'Legacy DOC prompt field instructions should not render as visible text');
        }
    },
    'preserves legacy DOC symbol field provenance around displayed glyphs' => static function (TestRunner $t) use ($buildCfb, $buildSimpleWordDocument): void {
        $fieldBegin = "\x13";
        $fieldSeparator = "\x14";
        $fieldEnd = "\x15";
        $docBytes = $buildCfb([
            'WordDocument' => $buildSimpleWordDocument(
                'Marker '
                . $fieldBegin . ' SYMBOL 183 \f "Symbol" \s 12 \u ' . $fieldSeparator . '·' . $fieldEnd
                . " bullet.\r"
            ),
        ]);

        $document = (new LegacyDocReader())->readBytes($docBytes)['document'];
        $markdown = (new MarkdownWriter())->write($document);
        $blocks = (new WordPressBlockWriter())->write($document);
        $paragraph = $document->children[0];
        $symbol = $paragraph->children[1];

        $t->same('span', $symbol->type);
        $t->same(['legacy-doc-field', 'legacy-doc-symbol-field', 'legacy-doc-field-symbol'], $symbol->attr('classes'));
        $t->same('symbol', $symbol->attr('attributes')['data-legacy-doc-field']);
        $t->same('SYMBOL 183 \f "Symbol" \s 12 \u', $symbol->attr('attributes')['data-legacy-doc-field-instruction']);
        $t->same('183', $symbol->attr('attributes')['data-legacy-doc-symbol-code']);
        $t->same('Symbol', $symbol->attr('attributes')['data-legacy-doc-symbol-font']);
        $t->same('12', $symbol->attr('attributes')['data-legacy-doc-symbol-size']);
        $t->same('u', $symbol->attr('attributes')['data-legacy-doc-symbol-switches']);
        $t->same('·', $symbol->children[0]->attr('text'));

        $t->contains('[·]{.legacy-doc-field .legacy-doc-symbol-field .legacy-doc-field-symbol data-legacy-doc-field="symbol"', $markdown);
        $t->contains('data-legacy-doc-symbol-font="Symbol"', $markdown);
        $t->contains('<span class="legacy-doc-field legacy-doc-symbol-field legacy-doc-field-symbol" data-legacy-doc-field="symbol" data-legacy-doc-field-instruction="SYMBOL 183 \f &quot;Symbol&quot; \s 12 \u" data-legacy-doc-symbol-code="183" data-legacy-doc-symbol-font="Symbol" data-legacy-doc-symbol-size="12" data-legacy-doc-symbol-switches="u">·</span>', $blocks);
        $t->true(!str_contains(strip_tags($blocks), 'SYMBOL'), 'Legacy DOC symbol field instructions should not render as visible text');
    },
    'preserves legacy DOC generated table and index field provenance around displayed results' => static function (TestRunner $t) use ($buildCfb, $buildSimpleWordDocument): void {
        $fieldBegin = "\x13";
        $fieldSeparator = "\x14";
        $fieldEnd = "\x15";
        $docBytes = $buildCfb([
            'WordDocument' => $buildSimpleWordDocument(
                'Generated '
                . $fieldBegin . ' TOC \o "1-3" \h \z \u ' . $fieldSeparator . 'Introduction	1' . $fieldEnd
                . ' and '
                . $fieldBegin . ' INDEX \c "2" \h "A" ' . $fieldSeparator . 'Legacy term, 4' . $fieldEnd
                . ' plus '
                . $fieldBegin . ' TOA \c "1" \p ' . $fieldSeparator . 'Case One 2' . $fieldEnd
                . ".\r"
            ),
        ]);

        $document = (new LegacyDocReader())->readBytes($docBytes)['document'];
        $markdown = (new MarkdownWriter())->write($document);
        $blocks = (new WordPressBlockWriter())->write($document);
        $paragraph = $document->children[0];

        $toc = $paragraph->children[1];
        $t->same('span', $toc->type);
        $t->same(['legacy-doc-field', 'legacy-doc-generated-field', 'legacy-doc-field-toc'], $toc->attr('classes'));
        $t->same('toc', $toc->attr('attributes')['data-legacy-doc-field']);
        $t->same('TOC \o "1-3" \h \z \u', $toc->attr('attributes')['data-legacy-doc-field-instruction']);
        $t->same('table-of-contents', $toc->attr('attributes')['data-legacy-doc-generated-field-type']);
        $t->same('o h z u', $toc->attr('attributes')['data-legacy-doc-generated-field-switches']);
        $t->same('1-3', $toc->attr('attributes')['data-legacy-doc-generated-field-switch-o']);
        $t->same('true', $toc->attr('attributes')['data-legacy-doc-generated-field-switch-h']);
        $t->same('true', $toc->attr('attributes')['data-legacy-doc-generated-field-switch-z']);
        $t->same('true', $toc->attr('attributes')['data-legacy-doc-generated-field-switch-u']);
        $t->same('Introduction	1', $toc->children[0]->attr('text'));

        $index = $paragraph->children[3];
        $t->same('span', $index->type);
        $t->same(['legacy-doc-field', 'legacy-doc-generated-field', 'legacy-doc-field-index'], $index->attr('classes'));
        $t->same('index', $index->attr('attributes')['data-legacy-doc-field']);
        $t->same('INDEX \c "2" \h "A"', $index->attr('attributes')['data-legacy-doc-field-instruction']);
        $t->same('index', $index->attr('attributes')['data-legacy-doc-generated-field-type']);
        $t->same('c h', $index->attr('attributes')['data-legacy-doc-generated-field-switches']);
        $t->same('2', $index->attr('attributes')['data-legacy-doc-generated-field-switch-c']);
        $t->same('A', $index->attr('attributes')['data-legacy-doc-generated-field-switch-h']);
        $t->same('Legacy term, 4', $index->children[0]->attr('text'));

        $toa = $paragraph->children[5];
        $t->same('span', $toa->type);
        $t->same(['legacy-doc-field', 'legacy-doc-generated-field', 'legacy-doc-field-toa'], $toa->attr('classes'));
        $t->same('toa', $toa->attr('attributes')['data-legacy-doc-field']);
        $t->same('TOA \c "1" \p', $toa->attr('attributes')['data-legacy-doc-field-instruction']);
        $t->same('table-of-authorities', $toa->attr('attributes')['data-legacy-doc-generated-field-type']);
        $t->same('c p', $toa->attr('attributes')['data-legacy-doc-generated-field-switches']);
        $t->same('1', $toa->attr('attributes')['data-legacy-doc-generated-field-switch-c']);
        $t->same('true', $toa->attr('attributes')['data-legacy-doc-generated-field-switch-p']);
        $t->same('Case One 2', $toa->children[0]->attr('text'));

        $t->contains('[Introduction	1]{.legacy-doc-field .legacy-doc-generated-field .legacy-doc-field-toc data-legacy-doc-field="toc"', $markdown);
        $t->contains('[Legacy term, 4]{.legacy-doc-field .legacy-doc-generated-field .legacy-doc-field-index data-legacy-doc-field="index"', $markdown);
        $t->contains('[Case One 2]{.legacy-doc-field .legacy-doc-generated-field .legacy-doc-field-toa data-legacy-doc-field="toa"', $markdown);
        $t->contains('<span class="legacy-doc-field legacy-doc-generated-field legacy-doc-field-toc" data-legacy-doc-field="toc" data-legacy-doc-field-instruction="TOC \o &quot;1-3&quot; \h \z \u" data-legacy-doc-generated-field-type="table-of-contents" data-legacy-doc-generated-field-switches="o h z u" data-legacy-doc-generated-field-switch-o="1-3" data-legacy-doc-generated-field-switch-h="true" data-legacy-doc-generated-field-switch-z="true" data-legacy-doc-generated-field-switch-u="true">Introduction	1</span>', $blocks);
        $t->contains('<span class="legacy-doc-field legacy-doc-generated-field legacy-doc-field-index" data-legacy-doc-field="index" data-legacy-doc-field-instruction="INDEX \c &quot;2&quot; \h &quot;A&quot;" data-legacy-doc-generated-field-type="index" data-legacy-doc-generated-field-switches="c h" data-legacy-doc-generated-field-switch-c="2" data-legacy-doc-generated-field-switch-h="A">Legacy term, 4</span>', $blocks);
        $t->contains('<span class="legacy-doc-field legacy-doc-generated-field legacy-doc-field-toa" data-legacy-doc-field="toa" data-legacy-doc-field-instruction="TOA \c &quot;1&quot; \p" data-legacy-doc-generated-field-type="table-of-authorities" data-legacy-doc-generated-field-switches="c p" data-legacy-doc-generated-field-switch-c="1" data-legacy-doc-generated-field-switch-p="true">Case One 2</span>', $blocks);
        foreach (['TOC', 'INDEX', 'TOA'] as $instruction) {
            $t->true(!str_contains(strip_tags($blocks), $instruction), 'Legacy DOC generated field instructions should not render as visible text');
        }
    },
    'preserves legacy DOC sequence and list-number field provenance around displayed results' => static function (TestRunner $t) use ($buildCfb, $buildSimpleWordDocument): void {
        $fieldBegin = "\x13";
        $fieldSeparator = "\x14";
        $fieldEnd = "\x15";
        $docBytes = $buildCfb([
            'WordDocument' => $buildSimpleWordDocument(
                'Numbered '
                . $fieldBegin . ' SEQ "Figure" \r 4 \* Arabic ' . $fieldSeparator . '4' . $fieldEnd
                . ' and '
                . $fieldBegin . ' LISTNUM "LegalDefault" \l 2 \s 3 ' . $fieldSeparator . '3.2' . $fieldEnd
                . ".\r"
            ),
        ]);

        $document = (new LegacyDocReader())->readBytes($docBytes)['document'];
        $markdown = (new MarkdownWriter())->write($document);
        $blocks = (new WordPressBlockWriter())->write($document);
        $paragraph = $document->children[0];

        $sequence = $paragraph->children[1];
        $t->same('span', $sequence->type);
        $t->same(['legacy-doc-field', 'legacy-doc-numbering-field', 'legacy-doc-field-seq'], $sequence->attr('classes'));
        $t->same('seq', $sequence->attr('attributes')['data-legacy-doc-field']);
        $t->same('SEQ "Figure" \r 4 \* Arabic', $sequence->attr('attributes')['data-legacy-doc-field-instruction']);
        $t->same('sequence', $sequence->attr('attributes')['data-legacy-doc-numbering-field-type']);
        $t->same('Figure', $sequence->attr('attributes')['data-legacy-doc-numbering-field-name']);
        $t->same('Figure', $sequence->attr('attributes')['data-legacy-doc-numbering-field-arguments']);
        $t->same('Arabic', $sequence->attr('attributes')['data-legacy-doc-field-format']);
        $t->same('r', $sequence->attr('attributes')['data-legacy-doc-numbering-field-switches']);
        $t->same('4', $sequence->attr('attributes')['data-legacy-doc-numbering-field-switch-r']);
        $t->same('4', $sequence->children[0]->attr('text'));

        $listNumber = $paragraph->children[3];
        $t->same('span', $listNumber->type);
        $t->same(['legacy-doc-field', 'legacy-doc-numbering-field', 'legacy-doc-field-listnum'], $listNumber->attr('classes'));
        $t->same('listnum', $listNumber->attr('attributes')['data-legacy-doc-field']);
        $t->same('LISTNUM "LegalDefault" \l 2 \s 3', $listNumber->attr('attributes')['data-legacy-doc-field-instruction']);
        $t->same('list-number', $listNumber->attr('attributes')['data-legacy-doc-numbering-field-type']);
        $t->same('LegalDefault', $listNumber->attr('attributes')['data-legacy-doc-numbering-field-name']);
        $t->same('LegalDefault', $listNumber->attr('attributes')['data-legacy-doc-numbering-field-arguments']);
        $t->same('l s', $listNumber->attr('attributes')['data-legacy-doc-numbering-field-switches']);
        $t->same('2', $listNumber->attr('attributes')['data-legacy-doc-numbering-field-switch-l']);
        $t->same('3', $listNumber->attr('attributes')['data-legacy-doc-numbering-field-switch-s']);
        $t->same('3.2', $listNumber->children[0]->attr('text'));

        $t->contains('[4]{.legacy-doc-field .legacy-doc-numbering-field .legacy-doc-field-seq data-legacy-doc-field="seq"', $markdown);
        $t->contains('data-legacy-doc-numbering-field-name="Figure"', $markdown);
        $t->contains('[3.2]{.legacy-doc-field .legacy-doc-numbering-field .legacy-doc-field-listnum data-legacy-doc-field="listnum"', $markdown);
        $t->contains('<span class="legacy-doc-field legacy-doc-numbering-field legacy-doc-field-seq" data-legacy-doc-field="seq" data-legacy-doc-field-instruction="SEQ &quot;Figure&quot; \r 4 \* Arabic" data-legacy-doc-numbering-field-type="sequence" data-legacy-doc-field-format="Arabic" data-legacy-doc-numbering-field-name="Figure" data-legacy-doc-numbering-field-arguments="Figure" data-legacy-doc-numbering-field-switches="r" data-legacy-doc-numbering-field-switch-r="4">4</span>', $blocks);
        $t->contains('<span class="legacy-doc-field legacy-doc-numbering-field legacy-doc-field-listnum" data-legacy-doc-field="listnum" data-legacy-doc-field-instruction="LISTNUM &quot;LegalDefault&quot; \l 2 \s 3" data-legacy-doc-numbering-field-type="list-number" data-legacy-doc-numbering-field-name="LegalDefault" data-legacy-doc-numbering-field-arguments="LegalDefault" data-legacy-doc-numbering-field-switches="l s" data-legacy-doc-numbering-field-switch-l="2" data-legacy-doc-numbering-field-switch-s="3">3.2</span>', $blocks);
        foreach (['SEQ', 'LISTNUM'] as $instruction) {
            $t->true(!str_contains(strip_tags($blocks), $instruction), 'Legacy DOC numbering field instructions should not render as visible text');
        }
    },
    'preserves legacy DOC automatic numbering field provenance around displayed results' => static function (TestRunner $t) use ($buildCfb, $buildSimpleWordDocument, $plcfldMom, $u32): void {
        $fieldBegin = "\x13";
        $fieldSeparator = "\x14";
        $fieldEnd = "\x15";
        $text = 'Auto numbers '
            . $fieldBegin . ' AUTONUM \* Arabic ' . $fieldSeparator . '1' . $fieldEnd
            . ', '
            . $fieldBegin . ' AUTONUMOUT \s 2 ' . $fieldSeparator . 'II.' . $fieldEnd
            . ', and '
            . $fieldBegin . ' AUTONUMLGL ' . $fieldSeparator . '2.1' . $fieldEnd
            . ".\r";

        $autoBegin = strpos($text, $fieldBegin);
        $autoSeparator = strpos($text, $fieldSeparator, (int) $autoBegin);
        $autoEnd = strpos($text, $fieldEnd, (int) $autoSeparator);
        $outlineBegin = strpos($text, $fieldBegin, (int) $autoEnd + 1);
        $outlineSeparator = strpos($text, $fieldSeparator, (int) $outlineBegin);
        $outlineEnd = strpos($text, $fieldEnd, (int) $outlineSeparator);
        $legalBegin = strpos($text, $fieldBegin, (int) $outlineEnd + 1);
        $legalSeparator = strpos($text, $fieldSeparator, (int) $legalBegin);
        $legalEnd = strpos($text, $fieldEnd, (int) $legalSeparator);
        foreach ([$autoBegin, $autoSeparator, $autoEnd, $outlineBegin, $outlineSeparator, $outlineEnd, $legalBegin, $legalSeparator, $legalEnd] as $cp) {
            if (!is_int($cp)) {
                throw new RuntimeException('Unable to locate legacy DOC automatic-numbering field fixture');
            }
        }

        $fieldTable = $plcfldMom([
            ['cp' => $autoBegin, 'character' => 0x13, 'typeCode' => 0x36],
            ['cp' => $autoSeparator, 'character' => 0x14],
            ['cp' => $autoEnd, 'character' => 0x15, 'endFlags' => 0x80],
            ['cp' => $outlineBegin, 'character' => 0x13, 'typeCode' => 0x34],
            ['cp' => $outlineSeparator, 'character' => 0x14],
            ['cp' => $outlineEnd, 'character' => 0x15, 'endFlags' => 0x80],
            ['cp' => $legalBegin, 'character' => 0x13, 'typeCode' => 0x35],
            ['cp' => $legalSeparator, 'character' => 0x14],
            ['cp' => $legalEnd, 'character' => 0x15, 'endFlags' => 0x80],
        ], strlen($text));
        $wordDocument = $buildSimpleWordDocument($text);
        $wordDocument = substr_replace($wordDocument, $u32(0), 0x011a, 4);
        $wordDocument = substr_replace($wordDocument, $u32(strlen($fieldTable)), 0x011e, 4);

        $result = (new LegacyDocReader())->readBytes($buildCfb([
            'WordDocument' => $wordDocument,
            '0Table' => $fieldTable,
        ]));
        $document = $result['document'];
        $fields = $result['fields'];
        $paragraph = $document->children[0];
        $markdown = (new MarkdownWriter())->write($document);
        $blocks = (new WordPressBlockWriter())->write($document);

        $t->same(9, $result['metadata']['fieldCharacterCount']);
        $t->same(3, $result['metadata']['fieldCount']);
        $t->same('autonum', $fields[0]['type']);
        $t->same(0x36, $fields[0]['typeCode']);
        $t->same('autonumout', $fields[1]['type']);
        $t->same(0x34, $fields[1]['typeCode']);
        $t->same('autonumlgl', $fields[2]['type']);
        $t->same(0x35, $fields[2]['typeCode']);

        $auto = $paragraph->children[1];
        $t->same('span', $auto->type);
        $t->same(['legacy-doc-field', 'legacy-doc-numbering-field', 'legacy-doc-field-autonum'], $auto->attr('classes'));
        $t->same('autonum', $auto->attr('attributes')['data-legacy-doc-field']);
        $t->same('AUTONUM \* Arabic', $auto->attr('attributes')['data-legacy-doc-field-instruction']);
        $t->same('auto-number', $auto->attr('attributes')['data-legacy-doc-numbering-field-type']);
        $t->same('Arabic', $auto->attr('attributes')['data-legacy-doc-field-format']);
        $t->same('1', $auto->children[0]->attr('text'));

        $outline = $paragraph->children[3];
        $t->same(['legacy-doc-field', 'legacy-doc-numbering-field', 'legacy-doc-field-autonumout'], $outline->attr('classes'));
        $t->same('autonumout', $outline->attr('attributes')['data-legacy-doc-field']);
        $t->same('AUTONUMOUT \s 2', $outline->attr('attributes')['data-legacy-doc-field-instruction']);
        $t->same('auto-number-outline', $outline->attr('attributes')['data-legacy-doc-numbering-field-type']);
        $t->same('s', $outline->attr('attributes')['data-legacy-doc-numbering-field-switches']);
        $t->same('2', $outline->attr('attributes')['data-legacy-doc-numbering-field-switch-s']);
        $t->same('II.', $outline->children[0]->attr('text'));

        $legal = $paragraph->children[5];
        $t->same(['legacy-doc-field', 'legacy-doc-numbering-field', 'legacy-doc-field-autonumlgl'], $legal->attr('classes'));
        $t->same('autonumlgl', $legal->attr('attributes')['data-legacy-doc-field']);
        $t->same('AUTONUMLGL', $legal->attr('attributes')['data-legacy-doc-field-instruction']);
        $t->same('auto-number-legal', $legal->attr('attributes')['data-legacy-doc-numbering-field-type']);
        $t->same('2.1', $legal->children[0]->attr('text'));

        $t->contains('[1]{.legacy-doc-field .legacy-doc-numbering-field .legacy-doc-field-autonum data-legacy-doc-field="autonum"', $markdown);
        $t->contains('[II.]{.legacy-doc-field .legacy-doc-numbering-field .legacy-doc-field-autonumout data-legacy-doc-field="autonumout"', $markdown);
        $t->contains('[2.1]{.legacy-doc-field .legacy-doc-numbering-field .legacy-doc-field-autonumlgl data-legacy-doc-field="autonumlgl"', $markdown);
        $t->contains('<span class="legacy-doc-field legacy-doc-numbering-field legacy-doc-field-autonum" data-legacy-doc-field="autonum" data-legacy-doc-field-instruction="AUTONUM \* Arabic" data-legacy-doc-numbering-field-type="auto-number" data-legacy-doc-field-format="Arabic">1</span>', $blocks);
        $t->contains('<span class="legacy-doc-field legacy-doc-numbering-field legacy-doc-field-autonumout" data-legacy-doc-field="autonumout" data-legacy-doc-field-instruction="AUTONUMOUT \s 2" data-legacy-doc-numbering-field-type="auto-number-outline" data-legacy-doc-numbering-field-switches="s" data-legacy-doc-numbering-field-switch-s="2">II.</span>', $blocks);
        $t->contains('<span class="legacy-doc-field legacy-doc-numbering-field legacy-doc-field-autonumlgl" data-legacy-doc-field="autonumlgl" data-legacy-doc-field-instruction="AUTONUMLGL" data-legacy-doc-numbering-field-type="auto-number-legal">2.1</span>', $blocks);
        foreach (['AUTONUM', 'AUTONUMOUT', 'AUTONUMLGL'] as $instruction) {
            $t->true(!str_contains(strip_tags($blocks), $instruction), 'Legacy DOC automatic numbering field instructions should not render as visible text');
        }
    },
    'preserves legacy DOC include field provenance around displayed results' => static function (TestRunner $t) use ($buildCfb, $buildSimpleWordDocument): void {
        $fieldBegin = "\x13";
        $fieldSeparator = "\x14";
        $fieldEnd = "\x15";
        $docBytes = $buildCfb([
            'WordDocument' => $buildSimpleWordDocument(
                'Includes '
                . $fieldBegin . ' INCLUDEPICTURE "C:\Legacy\Figures\chart.png" \d \* MERGEFORMAT '
                . $fieldSeparator . 'chart placeholder' . $fieldEnd
                . ' and '
                . $fieldBegin . ' INCLUDETEXT "https://example.test/legacy/clause.doc" \c "Heading 1" \! '
                . $fieldSeparator . 'Imported clause' . $fieldEnd
                . ".\r"
            ),
        ]);

        $document = (new LegacyDocReader())->readBytes($docBytes)['document'];
        $markdown = (new MarkdownWriter())->write($document);
        $blocks = (new WordPressBlockWriter())->write($document);
        $paragraph = $document->children[0];

        $picture = $paragraph->children[1];
        $t->same('span', $picture->type);
        $t->same(['legacy-doc-field', 'legacy-doc-include-field', 'legacy-doc-field-includepicture'], $picture->attr('classes'));
        $t->same('includepicture', $picture->attr('attributes')['data-legacy-doc-field']);
        $t->same('INCLUDEPICTURE "C:\Legacy\Figures\chart.png" \d \* MERGEFORMAT', $picture->attr('attributes')['data-legacy-doc-field-instruction']);
        $t->same('picture', $picture->attr('attributes')['data-legacy-doc-include-field-type']);
        $t->same('C:\Legacy\Figures\chart.png', $picture->attr('attributes')['data-legacy-doc-include-source']);
        $t->same('file-path', $picture->attr('attributes')['data-legacy-doc-include-source-kind']);
        $t->same('chart.png', $picture->attr('attributes')['data-legacy-doc-include-source-basename']);
        $t->same('MERGEFORMAT', $picture->attr('attributes')['data-legacy-doc-field-format']);
        $t->same('d', $picture->attr('attributes')['data-legacy-doc-include-field-switches']);
        $t->same('true', $picture->attr('attributes')['data-legacy-doc-include-field-switch-d']);
        $t->same('chart placeholder', $picture->children[0]->attr('text'));

        $includedText = $paragraph->children[3];
        $t->same(['legacy-doc-field', 'legacy-doc-include-field', 'legacy-doc-field-includetext'], $includedText->attr('classes'));
        $t->same('includetext', $includedText->attr('attributes')['data-legacy-doc-field']);
        $t->same('INCLUDETEXT "https://example.test/legacy/clause.doc" \c "Heading 1" \!', $includedText->attr('attributes')['data-legacy-doc-field-instruction']);
        $t->same('text', $includedText->attr('attributes')['data-legacy-doc-include-field-type']);
        $t->same('https://example.test/legacy/clause.doc', $includedText->attr('attributes')['data-legacy-doc-include-source']);
        $t->same('external-url', $includedText->attr('attributes')['data-legacy-doc-include-source-kind']);
        $t->same('clause.doc', $includedText->attr('attributes')['data-legacy-doc-include-source-basename']);
        $t->same('c !', $includedText->attr('attributes')['data-legacy-doc-include-field-switches']);
        $t->same('Heading 1', $includedText->attr('attributes')['data-legacy-doc-include-field-switch-c']);
        $t->same('true', $includedText->attr('attributes')['data-legacy-doc-include-field-lock-result']);
        $t->same('Imported clause', $includedText->children[0]->attr('text'));

        $t->contains('[chart placeholder]{.legacy-doc-field .legacy-doc-include-field .legacy-doc-field-includepicture data-legacy-doc-field="includepicture"', $markdown);
        $t->contains('[Imported clause]{.legacy-doc-field .legacy-doc-include-field .legacy-doc-field-includetext data-legacy-doc-field="includetext"', $markdown);
        $t->contains('<span class="legacy-doc-field legacy-doc-include-field legacy-doc-field-includepicture" data-legacy-doc-field="includepicture" data-legacy-doc-field-instruction="INCLUDEPICTURE &quot;C:\Legacy\Figures\chart.png&quot; \d \* MERGEFORMAT" data-legacy-doc-include-field-type="picture" data-legacy-doc-include-source="C:\Legacy\Figures\chart.png" data-legacy-doc-include-source-kind="file-path" data-legacy-doc-include-source-basename="chart.png" data-legacy-doc-field-format="MERGEFORMAT" data-legacy-doc-include-field-switches="d" data-legacy-doc-include-field-switch-d="true">chart placeholder</span>', $blocks);
        $t->contains('<span class="legacy-doc-field legacy-doc-include-field legacy-doc-field-includetext" data-legacy-doc-field="includetext" data-legacy-doc-field-instruction="INCLUDETEXT &quot;https://example.test/legacy/clause.doc&quot; \c &quot;Heading 1&quot; \!" data-legacy-doc-include-field-type="text" data-legacy-doc-include-source="https://example.test/legacy/clause.doc" data-legacy-doc-include-source-kind="external-url" data-legacy-doc-include-source-basename="clause.doc" data-legacy-doc-include-field-switches="c !" data-legacy-doc-include-field-switch-c="Heading 1" data-legacy-doc-include-field-lock-result="true">Imported clause</span>', $blocks);
        foreach (['INCLUDEPICTURE', 'INCLUDETEXT'] as $instruction) {
            $t->true(!str_contains(strip_tags($blocks), $instruction), 'Legacy DOC include field instructions should not render as visible text');
        }
    },
    'preserves legacy DOC action field provenance without executing actions' => static function (TestRunner $t) use ($buildCfb, $buildSimpleWordDocument, $plcfldMom, $u32): void {
        $fieldBegin = "\x13";
        $fieldSeparator = "\x14";
        $fieldEnd = "\x15";
        $text = 'Actions '
            . $fieldBegin . ' MACROBUTTON ApproveImport "Approve packet" ' . $fieldSeparator . 'Approve packet' . $fieldEnd
            . ' and '
            . $fieldBegin . ' GOTOBUTTON legacy_anchor "Jump to source" ' . $fieldSeparator . 'Jump to source' . $fieldEnd
            . ".\r";

        $macroBegin = strpos($text, $fieldBegin);
        $macroSeparator = strpos($text, $fieldSeparator, (int) $macroBegin);
        $macroEnd = strpos($text, $fieldEnd, (int) $macroSeparator);
        $goToBegin = strpos($text, $fieldBegin, (int) $macroEnd + 1);
        $goToSeparator = strpos($text, $fieldSeparator, (int) $goToBegin);
        $goToEnd = strpos($text, $fieldEnd, (int) $goToSeparator);
        foreach ([$macroBegin, $macroSeparator, $macroEnd, $goToBegin, $goToSeparator, $goToEnd] as $cp) {
            if (!is_int($cp)) {
                throw new RuntimeException('Unable to locate legacy DOC action-field fixture');
            }
        }

        $fieldTable = $plcfldMom([
            ['cp' => $macroBegin, 'character' => 0x13, 'typeCode' => 0x33],
            ['cp' => $macroSeparator, 'character' => 0x14],
            ['cp' => $macroEnd, 'character' => 0x15, 'endFlags' => 0x80],
            ['cp' => $goToBegin, 'character' => 0x13, 'typeCode' => 0x32],
            ['cp' => $goToSeparator, 'character' => 0x14],
            ['cp' => $goToEnd, 'character' => 0x15, 'endFlags' => 0x80],
        ], strlen($text));
        $wordDocument = $buildSimpleWordDocument($text);
        $wordDocument = substr_replace($wordDocument, $u32(0), 0x011a, 4);
        $wordDocument = substr_replace($wordDocument, $u32(strlen($fieldTable)), 0x011e, 4);

        $result = (new LegacyDocReader())->readBytes($buildCfb([
            'WordDocument' => $wordDocument,
            '0Table' => $fieldTable,
        ]));
        $document = $result['document'];
        $fields = $result['fields'];
        $paragraph = $document->children[0];
        $markdown = (new MarkdownWriter())->write($document);
        $blocks = (new WordPressBlockWriter())->write($document);

        $t->same(6, $result['metadata']['fieldCharacterCount']);
        $t->same(2, $result['metadata']['fieldCount']);
        $t->same('macrobutton', $fields[0]['type']);
        $t->same(0x33, $fields[0]['typeCode']);
        $t->same('gotobutton', $fields[1]['type']);
        $t->same(0x32, $fields[1]['typeCode']);

        $macroButton = $paragraph->children[1];
        $t->same('span', $macroButton->type);
        $t->same(['legacy-doc-field', 'legacy-doc-action-field', 'legacy-doc-field-macrobutton'], $macroButton->attr('classes'));
        $t->same('macrobutton', $macroButton->attr('attributes')['data-legacy-doc-field']);
        $t->same('MACROBUTTON ApproveImport "Approve packet"', $macroButton->attr('attributes')['data-legacy-doc-field-instruction']);
        $t->same('macro', $macroButton->attr('attributes')['data-legacy-doc-action-field-type']);
        $t->same('ApproveImport', $macroButton->attr('attributes')['data-legacy-doc-action-field-command']);
        $t->same('macro', $macroButton->attr('attributes')['data-legacy-doc-action-field-command-kind']);
        $t->same('metadata-only-native-review', $macroButton->attr('attributes')['data-legacy-doc-action-field-policy']);
        $t->same('disabled', $macroButton->attr('attributes')['data-legacy-doc-action-field-execution']);
        $t->same('Approve packet', $macroButton->attr('attributes')['data-legacy-doc-action-field-display-text']);
        $t->same('Approve packet', $macroButton->children[0]->attr('text'));

        $goToButton = $paragraph->children[3];
        $t->same(['legacy-doc-field', 'legacy-doc-action-field', 'legacy-doc-field-gotobutton'], $goToButton->attr('classes'));
        $t->same('gotobutton', $goToButton->attr('attributes')['data-legacy-doc-field']);
        $t->same('GOTOBUTTON legacy_anchor "Jump to source"', $goToButton->attr('attributes')['data-legacy-doc-field-instruction']);
        $t->same('navigation', $goToButton->attr('attributes')['data-legacy-doc-action-field-type']);
        $t->same('legacy_anchor', $goToButton->attr('attributes')['data-legacy-doc-action-field-destination']);
        $t->same('bookmark-or-goto-target', $goToButton->attr('attributes')['data-legacy-doc-action-field-destination-kind']);
        $t->same('metadata-only-native-review', $goToButton->attr('attributes')['data-legacy-doc-action-field-policy']);
        $t->same('disabled', $goToButton->attr('attributes')['data-legacy-doc-action-field-execution']);
        $t->same('Jump to source', $goToButton->attr('attributes')['data-legacy-doc-action-field-display-text']);
        $t->same('Jump to source', $goToButton->children[0]->attr('text'));

        $t->contains('[Approve packet]{.legacy-doc-field .legacy-doc-action-field .legacy-doc-field-macrobutton data-legacy-doc-field="macrobutton"', $markdown);
        $t->contains('[Jump to source]{.legacy-doc-field .legacy-doc-action-field .legacy-doc-field-gotobutton data-legacy-doc-field="gotobutton"', $markdown);
        $t->contains('<span class="legacy-doc-field legacy-doc-action-field legacy-doc-field-macrobutton" data-legacy-doc-field="macrobutton" data-legacy-doc-field-instruction="MACROBUTTON ApproveImport &quot;Approve packet&quot;" data-legacy-doc-action-field-type="macro" data-legacy-doc-action-field-command="ApproveImport" data-legacy-doc-action-field-command-kind="macro" data-legacy-doc-action-field-policy="metadata-only-native-review" data-legacy-doc-action-field-execution="disabled" data-legacy-doc-action-field-display-text="Approve packet">Approve packet</span>', $blocks);
        $t->contains('<span class="legacy-doc-field legacy-doc-action-field legacy-doc-field-gotobutton" data-legacy-doc-field="gotobutton" data-legacy-doc-field-instruction="GOTOBUTTON legacy_anchor &quot;Jump to source&quot;" data-legacy-doc-action-field-type="navigation" data-legacy-doc-action-field-destination="legacy_anchor" data-legacy-doc-action-field-destination-kind="bookmark-or-goto-target" data-legacy-doc-action-field-policy="metadata-only-native-review" data-legacy-doc-action-field-execution="disabled" data-legacy-doc-action-field-display-text="Jump to source">Jump to source</span>', $blocks);
        foreach (['MACROBUTTON', 'GOTOBUTTON', 'ApproveImport', 'legacy_anchor'] as $instruction) {
            $t->true(!str_contains(strip_tags($blocks), $instruction), 'Legacy DOC action field instructions should not render as visible text');
        }
    },
    'preserves legacy DOC nested field results inside displayed field output' => static function (TestRunner $t) use ($buildCfb, $buildSimpleWordDocument, $plcfldMom, $u32): void {
        $fieldBegin = "\x13";
        $fieldSeparator = "\x14";
        $fieldEnd = "\x15";
        $text = 'Nested '
            . $fieldBegin . ' HYPERLINK "https://example.test/review" \o "Review packet" '
            . $fieldSeparator . 'Source p. '
            . $fieldBegin . ' PAGE \* Arabic ' . $fieldSeparator . '12' . $fieldEnd
            . ' checked' . $fieldEnd
            . ".\r";

        $outerBegin = strpos($text, $fieldBegin);
        $outerSeparator = strpos($text, $fieldSeparator, (int) $outerBegin);
        $innerBegin = strpos($text, $fieldBegin, (int) $outerSeparator + 1);
        $innerSeparator = strpos($text, $fieldSeparator, (int) $innerBegin);
        $innerEnd = strpos($text, $fieldEnd, (int) $innerSeparator);
        $outerEnd = strpos($text, $fieldEnd, (int) $innerEnd + 1);
        foreach ([$outerBegin, $outerSeparator, $innerBegin, $innerSeparator, $innerEnd, $outerEnd] as $cp) {
            if (!is_int($cp)) {
                throw new RuntimeException('Unable to locate legacy DOC nested field fixture');
            }
        }

        $fieldTable = $plcfldMom([
            ['cp' => $outerBegin, 'character' => 0x13, 'typeCode' => 0x58],
            ['cp' => $outerSeparator, 'character' => 0x14],
            ['cp' => $innerBegin, 'character' => 0x13, 'typeCode' => 0x21],
            ['cp' => $innerSeparator, 'character' => 0x14],
            ['cp' => $innerEnd, 'character' => 0x15, 'endFlags' => 0xd4],
            ['cp' => $outerEnd, 'character' => 0x15, 'endFlags' => 0x80],
        ], strlen($text));
        $wordDocument = $buildSimpleWordDocument($text);
        $wordDocument = substr_replace($wordDocument, $u32(0), 0x011a, 4);
        $wordDocument = substr_replace($wordDocument, $u32(strlen($fieldTable)), 0x011e, 4);

        $result = (new LegacyDocReader())->readBytes($buildCfb([
            'WordDocument' => $wordDocument,
            '0Table' => $fieldTable,
        ]));
        $document = $result['document'];
        $fields = $result['fields'];
        $paragraph = $document->children[0];
        $link = $paragraph->children[1];
        $markdown = (new MarkdownWriter())->write($document);
        $blocks = (new WordPressBlockWriter())->write($document);

        $t->same(6, $result['metadata']['fieldCharacterCount']);
        $t->same(2, $result['metadata']['fieldCount']);
        $t->same('page', $fields[0]['type']);
        $t->same(1, $fields[0]['nestingLevel']);
        $t->same(0xd4, $fields[0]['endFlags']);
        $t->same(['result-dirty', 'locked', 'nested', 'has-separator'], $fields[0]['endFlagNames']);
        $t->same(true, $fields[0]['nested']);
        $t->same('hyperlink', $fields[1]['type']);
        $t->same(0, $fields[1]['nestingLevel']);
        $t->same(false, $fields[1]['nested']);

        $t->same('link', $link->type);
        $t->same('https://example.test/review', $link->attr('url'));
        $t->same('Review packet', $link->attr('title'));
        $t->same('Source p. ', $link->children[0]->attr('text'));
        $t->same('span', $link->children[1]->type);
        $t->same(['legacy-doc-field', 'legacy-doc-field-page'], $link->children[1]->attr('classes'));
        $t->same('page', $link->children[1]->attr('attributes')['data-legacy-doc-field']);
        $t->same('12', $link->children[1]->children[0]->attr('text'));
        $t->same(' checked', $link->children[2]->attr('text'));

        $t->contains('[12]{.legacy-doc-field .legacy-doc-field-page data-legacy-doc-field="page"', $markdown);
        $t->contains('<a href="https://example.test/review" title="Review packet">Source p. <span class="legacy-doc-field legacy-doc-field-page" data-legacy-doc-field="page" data-legacy-doc-field-instruction="PAGE \* Arabic" data-legacy-doc-field-format="Arabic">12</span> checked</a>', $blocks);
        foreach (['HYPERLINK', 'PAGE'] as $instruction) {
            $t->true(!str_contains(strip_tags($blocks), $instruction), 'Legacy DOC nested field instructions should not render as visible text');
        }
    },
    'rejects malformed legacy DOC field-code boundaries before exposing text' => static function (TestRunner $t) use ($buildCfb, $buildSimpleWordDocument): void {
        $reader = new LegacyDocReader();

        $t->throws(\RuntimeException::class, static fn (): array => $reader->readBytes($buildCfb([
            'WordDocument' => $buildSimpleWordDocument("Broken page \x13 PAGE \x147\r"),
        ])));
    },
    'rejects encrypted legacy DOC FIBs before exposing extracted text' => static function (TestRunner $t) use ($buildCfb, $buildSimpleWordDocument): void {
        $reader = new LegacyDocReader();

        $t->throws(\RuntimeException::class, static fn (): array => $reader->readBytes($buildCfb([
            'WordDocument' => $buildSimpleWordDocument("Encrypted payload should stay opaque\r", 0x0100),
        ])));
    },
    'rejects unencrypted legacy DOC FIBs with nonzero lKey before exposing text' => static function (TestRunner $t) use ($buildCfb, $buildSimpleWordDocument, $u32): void {
        $reader = new LegacyDocReader();
        $wordDocument = $buildSimpleWordDocument("Nonzero lKey payload should stay opaque\r");
        $wordDocument = substr_replace($wordDocument, $u32(0x12345678), 14, 4);

        $t->throws(\RuntimeException::class, static fn (): array => $reader->readBytes($buildCfb([
            'WordDocument' => $wordDocument,
        ])));
    },
    'rejects malformed legacy DOC containers without shelling out to Word' => static function (TestRunner $t) use ($buildCfb): void {
        $reader = new LegacyDocReader();

        $t->throws(\InvalidArgumentException::class, static fn (): array => $reader->readBytes('not a compound file'));
        $t->throws(\RuntimeException::class, static fn (): array => $reader->readBytes($buildCfb([
            '0Table' => '',
        ])));
        $t->throws(\InvalidArgumentException::class, static fn (): array => $reader->readBytes($buildCfb([
            'WordDocument' => str_repeat("\0", 64),
        ])));
    },
];
