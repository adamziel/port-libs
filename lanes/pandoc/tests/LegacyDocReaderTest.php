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
    int $child
) use ($u16, $u32, $u64, $utf16le): string {
    $nameBytes = $utf16le($name . "\0");
    if (strlen($nameBytes) > 64) {
        throw new RuntimeException('CFB test directory name is too long');
    }

    return str_pad($nameBytes, 64, "\0")
        . $u16(strlen($nameBytes))
        . chr($type)
        . "\x01"
        . $u32($leftSibling)
        . $u32($rightSibling)
        . $u32($child)
        . str_repeat("\0", 16)
        . $u32(0)
        . $u64(0)
        . $u64(0)
        . $u32($startSector)
        . $u64($size);
};

$buildCfb = static function (array $streams, bool $useMiniStreams = true) use ($u16, $u32, $directoryEntry, $padTo, $compareCfbDirectoryNames): string {
    $sectorSize = 512;
    $miniSectorSize = 64;
    $free = 0xffffffff;
    $end = 0xfffffffe;
    $fatSector = 0xfffffffd;

    $miniStreams = [];
    $regularStreams = [];
    foreach ($streams as $name => $data) {
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
    foreach ($streams as $name => $_data) {
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

    $directory = '';
    foreach ($nodes as $nodeIndex => $node) {
        $directory .= $directoryEntry(
            (string) $node['name'],
            (int) $node['type'],
            (int) $node['startSector'],
            (int) $node['size'],
            $leftSiblings[$nodeIndex] ?? $free,
            $rightSiblings[$nodeIndex] ?? $free,
            $childIds[$nodeIndex] ?? $free
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
$typedFiletime = static function (string $iso8601) use ($u64): string {
    $seconds = strtotime($iso8601);
    if ($seconds === false) {
        throw new RuntimeException('Unable to encode FILETIME fixture timestamp');
    }

    return pack('v', 0x0040) . "\0\0" . $u64(((int) $seconds + 11644473600) * 10000000);
};
$typedVectorLpstr = static function (array $values): string {
    $payload = pack('V', count($values));
    foreach ($values as $value) {
        $bytes = (string) $value . "\0";
        $payload .= pack('V', strlen($bytes)) . $bytes;
    }
    $raw = pack('v', 0x101e) . "\0\0" . $payload;

    return str_pad($raw, (int) (ceil(strlen($raw) / 4) * 4), "\0");
};
$typedVariantLpstr = static function (string $value): string {
    $bytes = $value . "\0";

    return pack('v', 0x001e) . "\0\0" . pack('V', strlen($bytes)) . $bytes;
};
$typedVariantI4 = static fn (int $value): string => pack('v', 0x0003) . "\0\0" . pack('V', $value);
$typedVectorVariant = static function (array $variants): string {
    $raw = pack('v', 0x100c) . "\0\0" . pack('V', count($variants)) . implode('', $variants);

    return str_pad($raw, (int) (ceil(strlen($raw) / 4) * 4), "\0");
};
$objectInfo = static fn (int $clipboardFormat): string => "\0\0" . pack('v', $clipboardFormat);
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
    'reports legacy DOC ObjectPool embedded OLE object streams without exposing bytes' => static function (TestRunner $t) use ($buildCfb, $buildSimpleWordDocument, $objectInfo): void {
        $docBytes = $buildCfb([
            'WordDocument' => $buildSimpleWordDocument("Embedded object review packet\r"),
            'ObjectPool/_42/' . "\x03" . 'ObjInfo' => $objectInfo(0x0014),
            'ObjectPool/_42/' . "\x01" . 'CompObj' => "compound display metadata",
            'ObjectPool/_42/' . "\x01" . 'Ole10Native' => "native spreadsheet bytes",
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
        $t->same(['code' => 0x0014, 'name' => 'unicode-text'], $objects[0]['transmissionFormat']);
        $t->same([
            'compound-object',
            'native-data',
            'presentation-data',
            'object-info',
        ], array_map(static fn (array $stream): string => $stream['role'], $objects[0]['streams']));
        $t->same(false, $objects[0]['streams'][1]['canExposeBytes']);
        $t->same(strlen('native spreadsheet bytes'), $objects[0]['streams'][1]['bytes']);

        $t->same('_43', $objects[1]['objectId']);
        $t->same(['code' => 0x000a, 'name' => 'html'], $objects[1]['transmissionFormat']);
        $t->same(['object-info', 'private-data'], array_map(static fn (array $stream): string => $stream['role'], $objects[1]['streams']));
        $t->contains('<p>Embedded object review packet</p>', $blocks);
        $t->true(!str_contains($blocks, 'native spreadsheet bytes'), 'Embedded OLE native bytes should not render to WordPress blocks');
        $t->true(!str_contains($blocks, 'presentation preview bytes'), 'Embedded OLE presentation bytes should not render to WordPress blocks');
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
    'rejects no-paragraph-last legacy DOC pieces containing paragraph marks' => static function (TestRunner $t) use ($buildCfb, $buildPieceTableDocStreams): void {
        $reader = new LegacyDocReader();

        $t->throws(\RuntimeException::class, static fn (): array => $reader->readBytes($buildCfb(
            $buildPieceTableDocStreams(0, 0x0001)
        )));
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
    'rejects malformed legacy DOC footnote and endnote PLCs before rendering references' => static function (TestRunner $t) use ($buildCfb, $buildNoteTableDocStreams, $u32): void {
        $reader = new LegacyDocReader();
        $missingFootnoteText = $buildNoteTableDocStreams();
        $missingFootnoteText['WordDocument'] = substr_replace($missingFootnoteText['WordDocument'], $u32(0), 0x00b6, 4);
        $t->throws(\RuntimeException::class, static fn (): array => $reader->readBytes($buildCfb($missingFootnoteText)));

        $badAutoReference = $buildNoteTableDocStreams();
        $badAutoReference['WordDocument'] = substr_replace($badAutoReference['WordDocument'], 'x', 1024 + 6, 1);
        $t->throws(\RuntimeException::class, static fn (): array => $reader->readBytes($buildCfb($badAutoReference)));
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
