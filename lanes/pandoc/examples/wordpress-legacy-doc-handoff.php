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
        . $u64(0)
        . $u64(0)
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

$fieldBegin = "\x13";
$fieldSeparator = "\x14";
$fieldEnd = "\x15";
$firstPieceText = 'Legacy DOC import ΩЖ魚';
$secondPieceText = "\rReviewer notes keep hard\vbreaks for block review with "
    . 'note ' . "\x02" . ' and endnote # while checking '
    . $fieldBegin . ' HYPERLINK "https://example.test/legacy-doc?source=42" \o "Source packet" '
    . $fieldSeparator . 'source dossier' . $fieldEnd
    . ' and '
    . $fieldBegin . ' HYPERLINK \l "legacy_anchor" '
    . $fieldSeparator . 'opening bookmark' . $fieldEnd
    . ' on page '
    . $fieldBegin . ' PAGE \* Arabic ' . $fieldSeparator . '7' . $fieldEnd
    . ".\r";
$firstPieceBytes = $utf16le($firstPieceText);
$secondPieceBytes = $utf16le($secondPieceText);
$firstPieceStart = 1024;
$secondPieceStart = $firstPieceStart + strlen($firstPieceBytes);

$wordDocument = str_repeat("\0", $firstPieceStart) . $firstPieceBytes . $secondPieceBytes;
$wordDocument = substr_replace($wordDocument, $u16(0xa5ec), 0, 2);
$wordDocument = substr_replace($wordDocument, $u16(0x00c1), 2, 2);
$wordDocument = substr_replace($wordDocument, $u16(0x1204), 10, 2);
$wordDocument = substr_replace($wordDocument, $u32(0), 24, 4);
$wordDocument = substr_replace($wordDocument, $u32(strlen($wordDocument)), 28, 4);

$firstPieceCharacters = intdiv(strlen($firstPieceBytes), 2);
$secondPieceCharacters = intdiv(strlen($secondPieceBytes), 2);
$plcPcd = $u32(0)
    . $u32($firstPieceCharacters)
    . $u32($firstPieceCharacters + $secondPieceCharacters)
    . $u16(0x0001) . $u32($firstPieceStart) . "\0\0"
    . $u16(0) . $u32($secondPieceStart) . "\0\0";
$clx = "\x02" . $u32(strlen($plcPcd)) . $plcPcd;
$bookmarkName = 'legacy_anchor';
$bookmarkNameBytes = $utf16le($bookmarkName);
$sttbfBkmk = $u16(0xffff)
    . $u16(1)
    . $u16(0)
    . $u16(intdiv(strlen($bookmarkNameBytes), 2))
    . $bookmarkNameBytes;
$totalPieceCharacters = $firstPieceCharacters + $secondPieceCharacters;
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
$fcSttbfBkmk = strlen($clx);
$fcPlcfBkf = $fcSttbfBkmk + strlen($sttbfBkmk);
$fcPlcfBkl = $fcPlcfBkf + strlen($plcfBkf);
$fcPlcffndRef = $fcPlcfBkl + strlen($plcfBkl);
$fcPlcffndTxt = $fcPlcffndRef + strlen($plcffndRef);
$fcPlcfendRef = $fcPlcffndTxt + strlen($plcffndTxt);
$fcPlcfendTxt = $fcPlcfendRef + strlen($plcfendRef);
$tableStream = $clx . $sttbfBkmk . $plcfBkf . $plcfBkl . $plcffndRef . $plcffndTxt . $plcfendRef . $plcfendTxt;
$wordDocument = substr_replace($wordDocument, $u32($fcPlcffndRef), 0x00aa, 4);
$wordDocument = substr_replace($wordDocument, $u32(strlen($plcffndRef)), 0x00ae, 4);
$wordDocument = substr_replace($wordDocument, $u32($fcPlcffndTxt), 0x00b2, 4);
$wordDocument = substr_replace($wordDocument, $u32(strlen($plcffndTxt)), 0x00b6, 4);
$wordDocument = substr_replace($wordDocument, $u32(0), 0x01a2, 4);
$wordDocument = substr_replace($wordDocument, $u32(strlen($clx)), 0x01a6, 4);
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
$docSummaryFmtid = hex2bin('02d5cdd59c2e1b10939708002b2cf9ae');
$userDefinedFmtid = hex2bin('05d5cdd59c2e1b10939708002b2cf9ae');
if (!is_string($docSummaryFmtid) || !is_string($userDefinedFmtid)) {
    throw new RuntimeException('Unable to build OLE property-set FMTID fixtures');
}

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
                ]),
                1 => $typedI2(1252),
                2 => $typedLpstr('legacy-doc-42'),
                3 => $typedBool(true),
                4 => $typedI4(4242),
            ],
        ],
    ]),
    'ObjectPool/_42/' . "\x03" . 'ObjInfo' => "\0\0" . $u16(0x0014),
    'ObjectPool/_42/' . "\x01" . 'Ole10Native' => 'opaque legacy embedded spreadsheet bytes',
    'ObjectPool/_42/' . "\x02" . 'OlePres000' => 'opaque embedded object presentation preview',
    'Macros/PROJECT' => "ID=\"LegacyMacros\"\r\nDocument=ThisDocument/&H00000000\r\nModule=MigrationTools\r\n",
    'Macros/PROJECTwm' => "LegacyMacros\0ThisDocument\0MigrationTools\0",
    'Macros/VBA/dir' => 'compressed vba directory bytes',
    'Macros/VBA/_VBA_PROJECT' => 'performance cache bytes',
    'Macros/VBA/ThisDocument' => "Attribute VB_Name = \"ThisDocument\"\r\nPrivate Sub Document_Open()\r\nEnd Sub\r\n",
    'Macros/VBA/MigrationTools' => "Attribute VB_Name = \"MigrationTools\"\r\nSub ImportPacket()\r\nEnd Sub\r\n",
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
    $childIds[0] ?? $free
);
foreach ($nodes as $nodeIndex => $node) {
    if ($nodeIndex === 0) {
        continue;
    }

    $type = (int) $node['type'];
    $streamPath = (string) ($node['streamPath'] ?? '');
    $location = $type === 2 ? $locations[$streamPath] : ['startSector' => $end, 'size' => 0];
    $directory .= $directoryEntry(
        (string) $node['name'],
        $type,
        $location['startSector'],
        $location['size'],
        $leftSiblings[$nodeIndex] ?? $free,
        $rightSiblings[$nodeIndex] ?? $free,
        $childIds[$nodeIndex] ?? $free
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

$docBytes = str_pad($header, 512, "\0") . implode('', $sectors);
$result = (new LegacyDocReader())->readBytes($docBytes);
$blocks = (new WordPressBlockWriter())->write($result['document']);

$summary = [
    'metadata' => $result['metadata'],
    'streams' => $result['streams'],
    'textSource' => $result['document']->attr('textSource'),
    'fib' => $result['fib'],
    'bookmarks' => $result['bookmarks'],
    'footnotes' => $result['footnotes'],
    'endnotes' => $result['endnotes'],
    'embeddedObjects' => $result['embeddedObjects'],
    'macroProjects' => $result['macroProjects'],
    'blockCount' => count($result['document']->children),
    'wordpressBlocks' => $blocks,
];

if (($argv[1] ?? '') === '--self-test') {
    if (($summary['metadata']['title'] ?? '') !== 'Legacy DOC import packet') {
        throw new RuntimeException('Legacy DOC handoff self-test missing metadata title');
    }
    if (($summary['metadata']['creator'] ?? '') !== 'Migration Desk') {
        throw new RuntimeException('Legacy DOC handoff self-test missing metadata creator');
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
    if (($summary['metadata']['lineCount'] ?? null) !== 2 || ($summary['metadata']['linksDirty'] ?? null) !== true) {
        throw new RuntimeException('Legacy DOC handoff self-test missing DocumentSummaryInformation review metadata');
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
    ]) {
        throw new RuntimeException('Legacy DOC handoff self-test missing user-defined custom properties');
    }
    if (($summary['metadata']['bookmarkCount'] ?? null) !== 1) {
        throw new RuntimeException('Legacy DOC handoff self-test missing standard bookmark count');
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
    if (($summary['metadata']['embeddedObjectCount'] ?? null) !== 1) {
        throw new RuntimeException('Legacy DOC handoff self-test missing embedded object count');
    }
    if (($summary['embeddedObjects'][0]['storagePath'] ?? '') !== 'ObjectPool/_42') {
        throw new RuntimeException('Legacy DOC handoff self-test missing ObjectPool storage report');
    }
    if (($summary['embeddedObjects'][0]['transmissionFormat'] ?? []) !== ['code' => 0x0014, 'name' => 'unicode-text']) {
        throw new RuntimeException('Legacy DOC handoff self-test missing ObjInfo transmission format');
    }
    if (($summary['embeddedObjects'][0]['hasNativeData'] ?? null) !== true || ($summary['embeddedObjects'][0]['hasPresentationData'] ?? null) !== true) {
        throw new RuntimeException('Legacy DOC handoff self-test missing embedded object stream roles');
    }
    if (($summary['embeddedObjects'][0]['canExposeBytes'] ?? null) !== false) {
        throw new RuntimeException('Legacy DOC handoff self-test exposed embedded object bytes');
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
    foreach ([
        '<p><span id="legacy_anchor" class="legacy-doc-bookmark" data-legacy-doc-bookmark="legacy_anchor" data-legacy-doc-bookmark-start-cp="0" data-legacy-doc-bookmark-end-cp="21">Legacy DOC import ΩЖ魚</span></p>',
        '<p>Reviewer notes keep hard<br/>breaks for block review with note ',
        '<span class="legacy-doc-note-ref legacy-doc-footnote-ref" data-legacy-doc-note-type="footnote" data-legacy-doc-note-index="1" data-legacy-doc-note-reference-cp="' . (string) ($summary['footnotes'][0]['referenceCp'] ?? '') . '" data-legacy-doc-note-text-start-cp="0" data-legacy-doc-note-text-end-cp="35" data-legacy-doc-note-auto-numbered="true"><sup>1</sup></span>',
        '<span class="legacy-doc-note-ref legacy-doc-endnote-ref" data-legacy-doc-note-type="endnote" data-legacy-doc-note-index="0" data-legacy-doc-note-reference-cp="' . (string) ($summary['endnotes'][0]['referenceCp'] ?? '') . '" data-legacy-doc-note-text-start-cp="0" data-legacy-doc-note-text-end-cp="29" data-legacy-doc-note-auto-numbered="false"><sup>#</sup></span>',
        '<a href="https://example.test/legacy-doc?source=42" title="Source packet">source dossier</a>',
        '<a href="#legacy_anchor">opening bookmark</a>',
        '<span class="legacy-doc-field legacy-doc-field-page" data-legacy-doc-field="page" data-legacy-doc-field-instruction="PAGE \* Arabic" data-legacy-doc-field-format="Arabic">7</span>',
    ] as $needle) {
        if (!str_contains($blocks, $needle)) {
            throw new RuntimeException('Legacy DOC handoff self-test missing: ' . $needle);
        }
    }
    if (str_contains($blocks, 'HYPERLINK')) {
        throw new RuntimeException('Legacy DOC handoff self-test rendered hidden field instructions');
    }
    if (str_contains($blocks, 'opaque legacy embedded spreadsheet bytes') || str_contains($blocks, 'opaque embedded object presentation preview')) {
        throw new RuntimeException('Legacy DOC handoff self-test rendered embedded object payload bytes');
    }
    if (str_contains($blocks, 'Document_Open') || str_contains($blocks, 'ImportPacket')) {
        throw new RuntimeException('Legacy DOC handoff self-test rendered macro module payload bytes');
    }
    if (($summary['fib']['extendedCharacters'] ?? null) !== true || ($summary['fib']['encrypted'] ?? null) !== false) {
        throw new RuntimeException('Legacy DOC handoff self-test missing FIB preflight flags');
    }

    foreach ([
        'unsupported CFB major version' => substr_replace($docBytes, $u16(5), 26, 2),
        'version 3 CFB directory-sector count' => substr_replace($docBytes, $u32(1), 40, 4),
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
