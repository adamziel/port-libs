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

$buildCfb = static function (array $streams, bool $useMiniStreams = true, array $directoryMetadata = []) use ($u16, $u32, $directoryEntry, $padTo, $compareCfbDirectoryNames): string {
    $sectorSize = 512;
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
    $fatEntries = max(128, count($sectors));
    for ($index = 0; $index < $fatEntries; $index++) {
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
        . $u32($directorySector)
        . $u32(0)
        . $u32(4096)
        . $u32($miniFatSector)
        . $u32($miniFat === [] ? 0 : 1)
        . $u32($end)
        . $u32(0)
        . $u32(0)
        . str_repeat($u32($free), 108);

    return str_pad($header, 512, "\0") . implode('', $sectors);
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

    $fcPlcfandRef = 0;
    $fcPlcfandTxt = strlen($plcfandRef);
    $tableStream = $plcfandRef . $plcfandTxt;

    $wordDocument = substr_replace($wordDocument, $u32($fcPlcfandRef), 0x00ba, 4);
    $wordDocument = substr_replace($wordDocument, $u32(strlen($plcfandRef)), 0x00be, 4);
    $wordDocument = substr_replace($wordDocument, $u32($fcPlcfandTxt), 0x00c2, 4);
    $wordDocument = substr_replace($wordDocument, $u32(strlen($plcfandTxt)), 0x00c6, 4);

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
    'rejects cyclic CFB directory child trees before exposing streams' => static function (TestRunner $t) use ($buildCfb, $u32): void {
        $bytes = $buildCfb([
            'WordDocument' => 'root stream bytes',
        ]);
        $directorySectorOffset = 512 + 512;
        $firstStreamRightSiblingOffset = $directorySectorOffset + 128 + 72;
        $cyclic = substr_replace($bytes, $u32(1), $firstStreamRightSiblingOffset, 4);

        $t->throws(\RuntimeException::class, static fn (): CompoundFileBinary => CompoundFileBinary::fromBytes($cyclic));
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
    'rejects invalid CFB header versions and directory-sector counts before stream lookup' => static function (TestRunner $t) use ($buildCfb, $u16, $u32): void {
        $bytes = $buildCfb([
            'WordDocument' => 'root stream bytes',
        ]);

        $unsupportedMajor = substr_replace($bytes, $u16(5), 26, 2);
        $t->throws(\InvalidArgumentException::class, static fn (): CompoundFileBinary => CompoundFileBinary::fromBytes($unsupportedMajor));

        $versionThreeWithDirectoryCount = substr_replace($bytes, $u32(1), 40, 4);
        $t->throws(\RuntimeException::class, static fn (): CompoundFileBinary => CompoundFileBinary::fromBytes($versionThreeWithDirectoryCount));
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
    'reports legacy DOC ObjectPool embedded OLE object streams without exposing bytes' => static function (TestRunner $t) use ($buildCfb, $buildSimpleWordDocument, $objectInfo, $ole10NativeStream, $compObjStream): void {
        $nativeData = 'embedded spreadsheet bytes';
        $nativeStreamBytes = $ole10NativeStream(
            'legacy-sheet.xlsx',
            'C:\legacy\legacy-sheet.xlsx',
            'C:\Temp\legacy-sheet.tmp',
            $nativeData
        );
        $docBytes = $buildCfb([
            'WordDocument' => $buildSimpleWordDocument("Embedded object review packet\r"),
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
        $document = $result['document'];
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
        $t->contains('<p>Embedded object review packet</p>', $blocks);
        $t->true(!str_contains($blocks, $nativeData), 'Embedded OLE native bytes should not render to WordPress blocks');
        $t->true(!str_contains($blocks, 'presentation preview bytes'), 'Embedded OLE presentation bytes should not render to WordPress blocks');
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
        $metadata = $result['metadata'];
        $paragraph = $document->children[0];
        $markdown = (new MarkdownWriter())->write($document);
        $blocks = (new WordPressBlockWriter())->write($document);

        $t->same(1, count($comments));
        $t->same($comments, $document->attr('comments'));
        $t->same($comments, $metadata['comments']);
        $t->same(1, $metadata['commentReferenceCount']);
        $t->same('comment', $comments[0]['type']);
        $t->same(1, $comments[0]['index']);
        $t->same(6, $comments[0]['referenceCp']);
        $t->same('JD', $comments[0]['authorInitials']);
        $t->same(2, $comments[0]['authorIndex']);
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
        $t->same((string) 0x1234, $commentRef->attr('attributes')['data-legacy-doc-comment-bookmark-tag']);
        $t->same('superscript', $commentRef->children[0]->type);
        $t->same('JD', $commentRef->children[0]->children[0]->attr('text'));
        $t->same(' beta', $paragraph->children[2]->attr('text'));

        $t->contains('[^JD^]{.legacy-doc-comment-ref data-legacy-doc-comment-index="1"', $markdown);
        $t->contains('<span class="legacy-doc-comment-ref" data-legacy-doc-comment-index="1" data-legacy-doc-comment-reference-cp="6" data-legacy-doc-comment-text-start-cp="0" data-legacy-doc-comment-text-end-cp="31" data-legacy-doc-comment-author-index="2" data-legacy-doc-comment-author-initials="JD" data-legacy-doc-comment-bookmark-tag="4660"><sup>JD</sup></span>', $blocks);
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
        $result = (new LegacyDocReader())->readBytes($buildCfb($buildFormattingTableDocStreams(), false));
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
        $t->throws(\RuntimeException::class, static fn (): array => $reader->readBytes($buildCfb($badLength, false)));

        $unsortedFc = $buildFormattingTableDocStreams();
        $unsortedFc['0Table'] = substr_replace($unsortedFc['0Table'], $u32(512), 4, 4);
        $t->throws(\RuntimeException::class, static fn (): array => $reader->readBytes($buildCfb($unsortedFc, false)));

        $badFkpPage = $buildFormattingTableDocStreams();
        $badFkpPage['0Table'] = substr_replace($badFkpPage['0Table'], $u32(9999), 8, 4);
        $t->throws(\RuntimeException::class, static fn (): array => $reader->readBytes($buildCfb($badFkpPage, false)));
    },
    'extracts legacy DOC list table formats and overrides as numbering review metadata' => static function (TestRunner $t) use ($buildCfb, $buildListTableDocStreams): void {
        $result = (new LegacyDocReader())->readBytes($buildCfb($buildListTableDocStreams(), false));
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
        $t->throws(\RuntimeException::class, static fn (): array => $reader->readBytes($buildCfb($badLength, false)));

        $duplicateLsid = $buildListTableDocStreams();
        $duplicateLsid['0Table'] = substr_replace($duplicateLsid['0Table'], $u32(1001), 2 + 28, 4);
        $t->throws(\RuntimeException::class, static fn (): array => $reader->readBytes($buildCfb($duplicateLsid, false)));

        $unknownOverride = $buildListTableDocStreams();
        $fcPlfLfo = unpack('Vvalue', substr($unknownOverride['WordDocument'], 0x02ea, 4))['value'];
        $unknownOverride['0Table'] = substr_replace($unknownOverride['0Table'], $u32(9999), (int) $fcPlfLfo + 4, 4);
        $t->throws(\RuntimeException::class, static fn (): array => $reader->readBytes($buildCfb($unknownOverride, false)));

        $badPlaceholder = $buildListTableDocStreams();
        $levelOffset = 2 + (2 * 28);
        $badPlaceholder['0Table'] = substr_replace($badPlaceholder['0Table'], "\x02", $levelOffset + 6, 1);
        $t->throws(\RuntimeException::class, static fn (): array => $reader->readBytes($buildCfb($badPlaceholder, false)));
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
