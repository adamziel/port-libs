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

$directoryEntry = static function (string $name, int $type, int $startSector, int $size) use ($u16, $u32, $u64, $utf16le): string {
    $nameBytes = $utf16le($name . "\0");
    if (strlen($nameBytes) > 64) {
        throw new RuntimeException('CFB test directory name is too long');
    }

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

$buildCfb = static function (array $streams, bool $useMiniStreams = true) use ($u16, $u32, $directoryEntry, $padTo): string {
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

    $directory = $directoryEntry('Root Entry', 5, $rootMiniStart, $miniStreamSize);
    foreach ($streams as $name => $data) {
        $location = $streamLocations[$name];
        $directory .= $directoryEntry((string) $name, 2, $location['startSector'], $location['size']);
    }
    $sectors[$directorySector] = $padTo($directory, $sectorSize);

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

$buildSimpleWordDocument = static function (string $text): string {
    $textBytes = iconv('UTF-8', 'Windows-1252//TRANSLIT', $text);
    if (!is_string($textBytes)) {
        throw new RuntimeException('Unable to encode simple WordDocument test text');
    }

    $fib = str_repeat("\0", 512);
    $fib = substr_replace($fib, pack('v', 0xa5ec), 0, 2);
    $fib = substr_replace($fib, pack('v', 0x00c1), 2, 2);
    $fib = substr_replace($fib, pack('V', 512), 24, 4);
    $fib = substr_replace($fib, pack('V', 512 + strlen($textBytes)), 28, 4);

    return $fib . $textBytes;
};

$buildPieceTableDocStreams = static function () use ($utf16le, $u32): array {
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
        . "\0\0" . $u32(($compressedStart * 2) | 0x40000000) . "\0\0"
        . "\0\0" . $u32($unicodeStart) . "\0\0";
    $clx = "\x02" . $u32(strlen($plc)) . $plc;
    $wordDocument = substr_replace($wordDocument, $u32(0), 0x01a2, 4);
    $wordDocument = substr_replace($wordDocument, $u32(strlen($clx)), 0x01a6, 4);

    return [
        'WordDocument' => $wordDocument,
        '1Table' => $clx,
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

        $t->same(['WordDocument', "\x05SummaryInformation", 'LargePreview'], $compoundFile->streamNames());
        $t->true($compoundFile->hasStream('worddocument'));
        $t->same(18, $compoundFile->streamSize('WordDocument'));
        $t->same('small stream bytes', $compoundFile->readStream('WordDocument'));
        $t->same('summary bytes', $compoundFile->readStream("\x05SummaryInformation"));
        $t->same(str_repeat('L', 5000), $compoundFile->readStream('LargePreview'));
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
