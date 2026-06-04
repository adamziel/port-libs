<?php

declare(strict_types=1);

namespace PortLibs\Pandoc;

final class CompoundFileBinary
{
    private const SIGNATURE = "\xd0\xcf\x11\xe0\xa1\xb1\x1a\xe1";
    private const FREESECT = 0xffffffff;
    private const ENDOFCHAIN = 0xfffffffe;
    private const FATSECT = 0xfffffffd;
    private const DIFSECT = 0xfffffffc;
    private const MAXREGSECT = 0xfffffffa;

    /** @var list<int> */
    private array $fat;

    /** @var list<array{name:string,path:string,type:int,startSector:int,size:int,leftSiblingId:int,rightSiblingId:int,childId:int,directoryId:int}> */
    private array $entries;

    /** @var array<string, int> */
    private array $entriesByName;

    /** @var list<int>|null */
    private ?array $miniFat = null;

    private ?string $miniStream = null;

    /**
     * @param list<int> $fat
     * @param list<array{name:string,path:string,type:int,startSector:int,size:int,leftSiblingId:int,rightSiblingId:int,childId:int,directoryId:int}> $entries
     * @param array<string, int> $entriesByName
     */
    private function __construct(
        private readonly string $bytes,
        private readonly int $sectorSize,
        private readonly int $miniSectorSize,
        private readonly int $miniStreamCutoff,
        private readonly int $firstDirectorySector,
        private readonly int $firstMiniFatSector,
        private readonly int $miniFatSectorCount,
        array $fat,
        array $entries,
        array $entriesByName,
        private readonly int $maxStreamBytes
    ) {
        $this->fat = $fat;
        $this->entries = $entries;
        $this->entriesByName = $entriesByName;
    }

    public static function fromBytes(string $bytes, int $maxStreamBytes = 16777216): self
    {
        if (strlen($bytes) < 512 || substr($bytes, 0, 8) !== self::SIGNATURE) {
            throw new \InvalidArgumentException('CFB file is missing the compound-file signature');
        }

        $majorVersion = self::u16($bytes, 26);
        $byteOrder = self::u16($bytes, 28);
        $sectorShift = self::u16($bytes, 30);
        $miniSectorShift = self::u16($bytes, 32);
        if ($byteOrder !== 0xfffe) {
            throw new \InvalidArgumentException('CFB file must use little-endian byte order');
        }
        if (!in_array($sectorShift, [9, 12], true)) {
            throw new \InvalidArgumentException('CFB file uses an unsupported sector size');
        }
        if ($majorVersion === 3 && $sectorShift !== 9) {
            throw new \InvalidArgumentException('CFB version 3 files must use 512-byte sectors');
        }
        if ($majorVersion === 4 && $sectorShift !== 12) {
            throw new \InvalidArgumentException('CFB version 4 files must use 4096-byte sectors');
        }
        if ($miniSectorShift !== 6) {
            throw new \InvalidArgumentException('CFB file uses an unsupported mini-sector size');
        }

        $sectorSize = 1 << $sectorShift;
        $miniSectorSize = 1 << $miniSectorShift;
        $fatSectorCount = self::u32($bytes, 44);
        $firstDirectorySector = self::u32($bytes, 48);
        $miniStreamCutoff = self::u32($bytes, 56);
        $firstMiniFatSector = self::u32($bytes, 60);
        $miniFatSectorCount = self::u32($bytes, 64);
        $firstDifatSector = self::u32($bytes, 68);
        $difatSectorCount = self::u32($bytes, 72);

        $difat = [];
        for ($index = 0; $index < 109; $index++) {
            $sector = self::u32($bytes, 76 + ($index * 4));
            if (self::isRegularSector($sector)) {
                $difat[] = $sector;
            }
        }

        $sectorCount = self::sectorCount($bytes, $sectorSize);
        $difatSector = $firstDifatSector;
        $seenDifat = [];
        for ($index = 0; $index < $difatSectorCount; $index++) {
            if (!self::isRegularSector($difatSector) || $difatSector >= $sectorCount || isset($seenDifat[$difatSector])) {
                throw new \RuntimeException('CFB DIFAT chain is invalid');
            }
            $seenDifat[$difatSector] = true;
            $sectorBytes = self::sectorBytes($bytes, $sectorSize, $difatSector);
            $entryCount = intdiv($sectorSize, 4) - 1;
            for ($entry = 0; $entry < $entryCount; $entry++) {
                $fatSector = self::u32($sectorBytes, $entry * 4);
                if (self::isRegularSector($fatSector)) {
                    $difat[] = $fatSector;
                }
            }
            $difatSector = self::u32($sectorBytes, $sectorSize - 4);
        }

        $fatSectorIds = array_slice($difat, 0, $fatSectorCount);
        if (count($fatSectorIds) !== $fatSectorCount) {
            throw new \RuntimeException('CFB header does not provide all FAT sectors');
        }

        $fat = [];
        foreach ($fatSectorIds as $fatSectorId) {
            if (!self::isRegularSector($fatSectorId) || $fatSectorId >= $sectorCount) {
                throw new \RuntimeException('CFB FAT sector points outside the file');
            }
            $sectorBytes = self::sectorBytes($bytes, $sectorSize, $fatSectorId);
            for ($offset = 0; $offset < $sectorSize; $offset += 4) {
                $fat[] = self::u32($sectorBytes, $offset);
            }
        }

        $reader = new self(
            $bytes,
            $sectorSize,
            $miniSectorSize,
            $miniStreamCutoff,
            $firstDirectorySector,
            $firstMiniFatSector,
            $miniFatSectorCount,
            $fat,
            [],
            [],
            $maxStreamBytes
        );

        $directoryBytes = $reader->readRegularSectorChain($firstDirectorySector, null, 'directory');
        [$entries, $entriesByName] = self::parseDirectory($directoryBytes);

        return new self(
            $bytes,
            $sectorSize,
            $miniSectorSize,
            $miniStreamCutoff,
            $firstDirectorySector,
            $firstMiniFatSector,
            $miniFatSectorCount,
            $fat,
            $entries,
            $entriesByName,
            $maxStreamBytes
        );
    }

    /**
     * @return list<array{name:string,path:string,type:int,startSector:int,size:int,leftSiblingId:int,rightSiblingId:int,childId:int,directoryId:int}>
     */
    public function entries(): array
    {
        return $this->entries;
    }

    /**
     * @return list<string>
     */
    public function streamNames(): array
    {
        $names = [];
        foreach ($this->entries as $entry) {
            if ($entry['type'] === 2) {
                $names[] = $entry['path'];
            }
        }

        return $names;
    }

    public function hasStream(string $name): bool
    {
        $entry = $this->findEntry($name);

        return $entry !== null && $entry['type'] === 2;
    }

    public function streamSize(string $name): int
    {
        $entry = $this->requireStreamEntry($name);

        return $entry['size'];
    }

    public function readStream(string $name): string
    {
        $entry = $this->requireStreamEntry($name);
        if ($entry['size'] === 0) {
            return '';
        }
        if ($entry['size'] > $this->maxStreamBytes) {
            throw new \RuntimeException('CFB stream exceeds the configured size limit: ' . $entry['name']);
        }

        if (
            $entry['size'] < $this->miniStreamCutoff
            && self::isRegularSector($this->firstMiniFatSector)
            && $this->miniFatSectorCount > 0
        ) {
            return $this->readMiniSectorChain($entry['startSector'], $entry['size'], $entry['name']);
        }

        return $this->readRegularSectorChain($entry['startSector'], $entry['size'], $entry['name']);
    }

    /**
     * @return array{name:string,path:string,type:int,startSector:int,size:int,leftSiblingId:int,rightSiblingId:int,childId:int,directoryId:int}|null
     */
    private function findEntry(string $name): ?array
    {
        $normalized = self::normalizeName($name);
        $index = $this->entriesByName[$normalized] ?? null;

        return $index === null ? null : $this->entries[$index];
    }

    /**
     * @return array{name:string,path:string,type:int,startSector:int,size:int,leftSiblingId:int,rightSiblingId:int,childId:int,directoryId:int}
     */
    private function requireStreamEntry(string $name): array
    {
        $entry = $this->findEntry($name);
        if ($entry === null || $entry['type'] !== 2) {
            throw new \RuntimeException('CFB stream is missing: ' . $name);
        }

        return $entry;
    }

    private function readRegularSectorChain(int $startSector, ?int $expectedSize, string $label): string
    {
        if (!self::isRegularSector($startSector)) {
            if ($expectedSize === 0) {
                return '';
            }
            throw new \RuntimeException('CFB sector chain is missing for ' . $label);
        }

        $sectorCount = self::sectorCount($this->bytes, $this->sectorSize);
        $seen = [];
        $data = '';
        $sector = $startSector;
        while (self::isRegularSector($sector)) {
            if ($sector >= $sectorCount || isset($seen[$sector])) {
                throw new \RuntimeException('CFB sector chain is invalid for ' . $label);
            }
            $seen[$sector] = true;
            $data .= self::sectorBytes($this->bytes, $this->sectorSize, $sector);
            if (strlen($data) > $this->maxStreamBytes + $this->sectorSize) {
                throw new \RuntimeException('CFB sector chain exceeds the configured size limit for ' . $label);
            }
            $sector = $this->fat[$sector] ?? self::FREESECT;
        }

        if ($sector !== self::ENDOFCHAIN) {
            throw new \RuntimeException('CFB sector chain is not terminated for ' . $label);
        }

        if ($expectedSize !== null) {
            if (strlen($data) < $expectedSize) {
                throw new \RuntimeException('CFB sector chain is shorter than declared for ' . $label);
            }

            return substr($data, 0, $expectedSize);
        }

        return $data;
    }

    private function readMiniSectorChain(int $startMiniSector, int $expectedSize, string $label): string
    {
        if (!self::isRegularSector($startMiniSector)) {
            throw new \RuntimeException('CFB mini-sector chain is missing for ' . $label);
        }

        $miniFat = $this->loadMiniFat();
        $miniStream = $this->loadMiniStream();
        $seen = [];
        $data = '';
        $sector = $startMiniSector;
        while (self::isRegularSector($sector)) {
            $offset = $sector * $this->miniSectorSize;
            if ($offset + $this->miniSectorSize > strlen($miniStream) || isset($seen[$sector])) {
                throw new \RuntimeException('CFB mini-sector chain is invalid for ' . $label);
            }
            $seen[$sector] = true;
            $data .= substr($miniStream, $offset, $this->miniSectorSize);
            if (strlen($data) > $this->maxStreamBytes + $this->miniSectorSize) {
                throw new \RuntimeException('CFB mini-sector chain exceeds the configured size limit for ' . $label);
            }
            $sector = $miniFat[$sector] ?? self::FREESECT;
        }

        if ($sector !== self::ENDOFCHAIN) {
            throw new \RuntimeException('CFB mini-sector chain is not terminated for ' . $label);
        }
        if (strlen($data) < $expectedSize) {
            throw new \RuntimeException('CFB mini-sector chain is shorter than declared for ' . $label);
        }

        return substr($data, 0, $expectedSize);
    }

    /**
     * @return list<int>
     */
    private function loadMiniFat(): array
    {
        if ($this->miniFat !== null) {
            return $this->miniFat;
        }
        if (!self::isRegularSector($this->firstMiniFatSector) || $this->miniFatSectorCount <= 0) {
            throw new \RuntimeException('CFB file does not contain a MiniFAT');
        }

        $bytes = $this->readRegularSectorChain(
            $this->firstMiniFatSector,
            $this->miniFatSectorCount * $this->sectorSize,
            'MiniFAT'
        );
        $fat = [];
        for ($offset = 0, $length = strlen($bytes); $offset + 4 <= $length; $offset += 4) {
            $fat[] = self::u32($bytes, $offset);
        }

        $this->miniFat = $fat;

        return $fat;
    }

    private function loadMiniStream(): string
    {
        if ($this->miniStream !== null) {
            return $this->miniStream;
        }

        $root = null;
        foreach ($this->entries as $entry) {
            if ($entry['type'] === 5) {
                $root = $entry;
                break;
            }
        }
        if ($root === null || $root['size'] === 0) {
            throw new \RuntimeException('CFB file does not contain a root mini stream');
        }

        $this->miniStream = $this->readRegularSectorChain($root['startSector'], $root['size'], 'Root Entry mini stream');

        return $this->miniStream;
    }

    /**
     * @return array{0:list<array{name:string,path:string,type:int,startSector:int,size:int,leftSiblingId:int,rightSiblingId:int,childId:int,directoryId:int}>,1:array<string,int>}
     */
    private static function parseDirectory(string $directoryBytes): array
    {
        $rawEntries = [];
        $root = null;
        $byName = [];
        for ($offset = 0, $length = strlen($directoryBytes); $offset + 128 <= $length; $offset += 128) {
            $directoryId = intdiv($offset, 128);
            $entryBytes = substr($directoryBytes, $offset, 128);
            $type = ord($entryBytes[66]);
            if ($type === 0) {
                $rawEntries[$directoryId] = null;
                continue;
            }

            $nameLength = self::u16($entryBytes, 64);
            if ($nameLength < 2 || $nameLength > 64) {
                $rawEntries[$directoryId] = null;
                continue;
            }
            $nameBytes = substr($entryBytes, 0, $nameLength - 2);
            $name = self::decodeUtf16Le($nameBytes);
            $entry = [
                'name' => $name,
                'path' => $name,
                'type' => $type,
                'startSector' => self::u32($entryBytes, 116),
                'size' => self::u64($entryBytes, 120),
                'leftSiblingId' => self::u32($entryBytes, 68),
                'rightSiblingId' => self::u32($entryBytes, 72),
                'childId' => self::u32($entryBytes, 76),
                'directoryId' => $directoryId,
            ];
            $rawEntries[$directoryId] = $entry;
            if ($type === 5 && $root === null) {
                $root = $entry;
            }
        }

        if ($root === null) {
            throw new \RuntimeException('CFB directory is missing the Root Entry storage');
        }

        $root['path'] = '';
        $entries = [$root];
        $visited = [$root['directoryId'] => true];
        self::collectDirectoryTree($root['childId'], '', $rawEntries, $entries, $byName, $visited);

        return [$entries, $byName];
    }

    /**
     * @param array<int,array{name:string,path:string,type:int,startSector:int,size:int,leftSiblingId:int,rightSiblingId:int,childId:int,directoryId:int}|null> $rawEntries
     * @param list<array{name:string,path:string,type:int,startSector:int,size:int,leftSiblingId:int,rightSiblingId:int,childId:int,directoryId:int}> $entries
     * @param array<string,int> $byName
     * @param array<int,bool> $visited
     */
    private static function collectDirectoryTree(
        int $nodeId,
        string $parentPath,
        array $rawEntries,
        array &$entries,
        array &$byName,
        array &$visited
    ): void {
        if (!self::isRegularSector($nodeId)) {
            return;
        }
        if (!array_key_exists($nodeId, $rawEntries) || $rawEntries[$nodeId] === null) {
            throw new \RuntimeException('CFB directory tree points outside the directory');
        }
        if (isset($visited[$nodeId])) {
            throw new \RuntimeException('CFB directory tree contains a cycle');
        }

        $visited[$nodeId] = true;
        $entry = $rawEntries[$nodeId];
        self::collectDirectoryTree($entry['leftSiblingId'], $parentPath, $rawEntries, $entries, $byName, $visited);

        $entry['path'] = $parentPath === '' ? $entry['name'] : $parentPath . '/' . $entry['name'];
        $entryIndex = count($entries);
        $entries[] = $entry;
        if ($entry['type'] === 2) {
            $fullName = self::normalizeName($entry['path']);
            if (isset($byName[$fullName])) {
                throw new \RuntimeException('CFB directory contains a duplicate stream path: ' . $entry['path']);
            }
            $byName[$fullName] = $entryIndex;
            if ($parentPath === '') {
                $rootName = self::normalizeName($entry['name']);
                if ($rootName !== $fullName && isset($byName[$rootName])) {
                    throw new \RuntimeException('CFB directory contains a duplicate root stream name: ' . $entry['name']);
                }
                $byName[$rootName] = $entryIndex;
            }
        }
        if ($entry['type'] === 1) {
            self::collectDirectoryTree($entry['childId'], $entry['path'], $rawEntries, $entries, $byName, $visited);
        }

        self::collectDirectoryTree($entry['rightSiblingId'], $parentPath, $rawEntries, $entries, $byName, $visited);
    }

    private static function normalizeName(string $name): string
    {
        return strtolower(ltrim($name, '/'));
    }

    private static function isRegularSector(int $sector): bool
    {
        return $sector >= 0 && $sector <= self::MAXREGSECT;
    }

    private static function sectorCount(string $bytes, int $sectorSize): int
    {
        return intdiv(max(0, strlen($bytes) - 512), $sectorSize);
    }

    private static function sectorBytes(string $bytes, int $sectorSize, int $sector): string
    {
        $offset = 512 + ($sector * $sectorSize);
        if ($offset < 512 || $offset + $sectorSize > strlen($bytes)) {
            throw new \RuntimeException('CFB sector points outside the file');
        }

        return substr($bytes, $offset, $sectorSize);
    }

    private static function decodeUtf16Le(string $bytes): string
    {
        if ($bytes === '') {
            return '';
        }

        $decoded = iconv('UTF-16LE', 'UTF-8//IGNORE', $bytes);
        if (is_string($decoded)) {
            return $decoded;
        }

        return '';
    }

    private static function u16(string $bytes, int $offset): int
    {
        if ($offset < 0 || $offset + 2 > strlen($bytes)) {
            throw new \RuntimeException('Unexpected end of CFB data');
        }
        $values = unpack('vvalue', substr($bytes, $offset, 2));

        return (int) $values['value'];
    }

    private static function u32(string $bytes, int $offset): int
    {
        if ($offset < 0 || $offset + 4 > strlen($bytes)) {
            throw new \RuntimeException('Unexpected end of CFB data');
        }
        $values = unpack('Vvalue', substr($bytes, $offset, 4));

        return (int) $values['value'];
    }

    private static function u64(string $bytes, int $offset): int
    {
        $low = self::u32($bytes, $offset);
        $high = self::u32($bytes, $offset + 4);
        if ($high > intdiv(PHP_INT_MAX - $low, 4294967296)) {
            throw new \RuntimeException('CFB stream size exceeds PHP integer range');
        }

        return ($high * 4294967296) + $low;
    }
}
