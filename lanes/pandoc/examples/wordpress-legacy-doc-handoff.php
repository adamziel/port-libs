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
$padTo = static function (string $bytes, int $size): string {
    $remainder = strlen($bytes) % $size;

    return $remainder === 0 ? $bytes : $bytes . str_repeat("\0", $size - $remainder);
};
$directoryEntry = static function (string $name, int $type, int $startSector, int $size) use ($u16, $u32, $u64, $utf16le): string {
    $nameBytes = $utf16le($name . "\0");

    return str_pad($nameBytes, 64, "\0")
        . $u16(strlen($nameBytes))
        . chr($type)
        . "\0"
        . $u32(0xffffffff)
        . $u32(0xffffffff)
        . $u32(0xffffffff)
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
$propertySet = static function (array $values) use ($u32, $typedI2, $typedLpstr): string {
    $properties = [1 => $typedI2(1252)];
    foreach ($values as $id => $value) {
        $properties[(int) $id] = $typedLpstr((string) $value);
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

$wordText = "Legacy DOC import ΩЖ魚\rReviewer notes keep hard\vbreaks for block review.\r";
$wordTextBytes = iconv('UTF-8', 'UTF-16LE', $wordText);
if (!is_string($wordTextBytes)) {
    throw new RuntimeException('Unable to encode legacy DOC fixture text');
}

$wordDocument = str_repeat("\0", 512);
$wordDocument = substr_replace($wordDocument, $u16(0xa5ec), 0, 2);
$wordDocument = substr_replace($wordDocument, $u16(0x00c1), 2, 2);
$wordDocument = substr_replace($wordDocument, $u16(0x1000), 10, 2);
$wordDocument = substr_replace($wordDocument, $u32(512), 24, 4);
$wordDocument = substr_replace($wordDocument, $u32(512 + strlen($wordTextBytes)), 28, 4);
$wordDocument .= $wordTextBytes;

$streams = [
    'WordDocument' => $wordDocument,
    "\x05SummaryInformation" => $propertySet([
        2 => 'Legacy DOC import packet',
        4 => 'Migration Desk',
        6 => 'Source .doc review notes',
        8 => 'Reviewer',
    ]),
];

$miniSectorSize = 64;
$sectorSize = 512;
$free = 0xffffffff;
$end = 0xfffffffe;
$fatSector = 0xfffffffd;
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

$directory = $directoryEntry('Root Entry', 5, $rootMiniStart, $miniStreamSize);
foreach ($streams as $name => $data) {
    $location = $locations[$name];
    $directory .= $directoryEntry((string) $name, 2, $location['startSector'], $location['size']);
}
$sectors[1] = $padTo($directory, $sectorSize);

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
    foreach ([
        '<p>Legacy DOC import ΩЖ魚</p>',
        '<p>Reviewer notes keep hard<br/>breaks for block review.</p>',
    ] as $needle) {
        if (!str_contains($blocks, $needle)) {
            throw new RuntimeException('Legacy DOC handoff self-test missing: ' . $needle);
        }
    }
    if (($summary['fib']['extendedCharacters'] ?? null) !== true || ($summary['fib']['encrypted'] ?? null) !== false) {
        throw new RuntimeException('Legacy DOC handoff self-test missing FIB preflight flags');
    }

    echo "legacy doc handoff self-test ok\n";
    return;
}

echo json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
