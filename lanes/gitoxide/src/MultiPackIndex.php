<?php

declare(strict_types=1);

namespace PortLibs\Gitoxide;

final class MultiPackIndex
{
    private const SIGNATURE = 'MIDX';
    private const VERSION = 1;
    private const HEADER_BYTES = 12;
    private const TOC_ENTRY_BYTES = 12;
    private const FANOUT_ENTRIES = 256;
    private const UINT32_BYTES = 4;
    private const UINT64_BYTES = 8;
    private const LARGE_OFFSET_FLAG = 0x80000000;

    private const CHUNK_PACK_NAMES = 'PNAM';
    private const CHUNK_FANOUT = 'OIDF';
    private const CHUNK_LOOKUP = 'OIDL';
    private const CHUNK_OFFSETS = 'OOFF';
    private const CHUNK_LARGE_OFFSETS = 'LOFF';
    private const CHUNK_SENTINEL = "\0\0\0\0";

    private const HASHES = [
        1 => ['name' => 'sha1', 'bytes' => 20],
        2 => ['name' => 'sha256', 'bytes' => 32],
    ];

    /**
     * @param list<int> $fanout
     * @param list<string> $oids
     * @param list<array{packIndex:int,packOffset:int}> $offsets
     * @param list<string> $indexNames
     */
    private function __construct(
        private readonly string $bytes,
        private readonly int $version,
        private readonly int $hashKind,
        private readonly string $hashName,
        private readonly int $hashBytes,
        private readonly int $baseMultiPackIndexCount,
        private readonly int $packCount,
        private readonly int $objectCount,
        private readonly array $fanout,
        private readonly array $oids,
        private readonly array $offsets,
        private readonly array $indexNames,
        private readonly string $checksum,
    ) {
    }

    public static function fromBytes(string $bytes, ?int $allocationLimitBytes = null): self
    {
        if ($allocationLimitBytes !== null && $allocationLimitBytes < 0) {
            throw new \InvalidArgumentException('Multi-pack-index allocation limit cannot be negative');
        }

        $length = strlen($bytes);
        if ($length < self::HEADER_BYTES + self::TOC_ENTRY_BYTES) {
            throw new \InvalidArgumentException("Multi-pack-index of size {$length} is too small");
        }
        if (substr($bytes, 0, 4) !== self::SIGNATURE) {
            throw new \InvalidArgumentException('Invalid multi-pack-index signature');
        }

        $version = ord($bytes[4]);
        if ($version !== self::VERSION) {
            throw new \InvalidArgumentException("Unsupported multi-pack-index version: {$version}");
        }

        $hashKind = ord($bytes[5]);
        if (!isset(self::HASHES[$hashKind])) {
            throw new \InvalidArgumentException("Unsupported multi-pack-index object hash kind: {$hashKind}");
        }
        $hashName = self::HASHES[$hashKind]['name'];
        $hashBytes = self::HASHES[$hashKind]['bytes'];
        $chunkCount = ord($bytes[6]);
        if ($chunkCount === 0) {
            throw new \InvalidArgumentException('Multi-pack-index must contain at least one chunk');
        }
        $baseMultiPackIndexCount = ord($bytes[7]);
        $packCount = self::readUInt32At($bytes, 8);

        $chunks = self::readChunkTable($bytes, $chunkCount);
        $indexNames = self::readIndexNames(
            self::requiredChunk($bytes, $chunks, self::CHUNK_PACK_NAMES),
            $packCount,
            $allocationLimitBytes
        );
        $fanout = self::readFanout(self::requiredChunk($bytes, $chunks, self::CHUNK_FANOUT));
        $objectCount = $fanout[255];
        $oids = self::readOids(self::requiredChunk($bytes, $chunks, self::CHUNK_LOOKUP), $objectCount, $hashBytes);
        $largeOffsets = isset($chunks[self::CHUNK_LARGE_OFFSETS])
            ? self::readLargeOffsets(self::slice($bytes, $chunks[self::CHUNK_LARGE_OFFSETS]))
            : null;
        $offsets = self::readOffsets(
            self::requiredChunk($bytes, $chunks, self::CHUNK_OFFSETS),
            $objectCount,
            $largeOffsets
        );

        $checksumOffset = max(array_map(static fn (array $range): int => $range['end'], $chunks));
        $trailer = substr($bytes, $checksumOffset);
        if (strlen($trailer) !== $hashBytes) {
            throw new \InvalidArgumentException('Multi-pack-index trailer checksum does not have the expected size');
        }

        return new self(
            $bytes,
            $version,
            $hashKind,
            $hashName,
            $hashBytes,
            $baseMultiPackIndexCount,
            $packCount,
            $objectCount,
            $fanout,
            $oids,
            $offsets,
            $indexNames,
            bin2hex($trailer),
        );
    }

    public static function open(string $path, ?int $allocationLimitBytes = null): self
    {
        if (!is_file($path)) {
            throw new \RuntimeException("Multi-pack-index file not found: {$path}");
        }

        return self::fromBytes((string) file_get_contents($path), $allocationLimitBytes);
    }

    public function version(): int
    {
        return $this->version;
    }

    public function objectHash(): string
    {
        return $this->hashName;
    }

    public function objectHashKind(): int
    {
        return $this->hashKind;
    }

    public function hashBytes(): int
    {
        return $this->hashBytes;
    }

    public function baseMultiPackIndexCount(): int
    {
        return $this->baseMultiPackIndexCount;
    }

    public function packCount(): int
    {
        return $this->packCount;
    }

    public function count(): int
    {
        return $this->objectCount;
    }

    public function checksum(): string
    {
        return $this->checksum;
    }

    public function verifyChecksum(): string
    {
        $actual = hash($this->hashName, substr($this->bytes, 0, -$this->hashBytes));
        if ($actual !== $this->checksum) {
            throw new \RuntimeException('Multi-pack-index checksum mismatch');
        }

        return $actual;
    }

    public function verifyIntegrityFast(): string
    {
        $checksum = $this->verifyChecksum();
        if ($this->objectCount === 0) {
            throw new \RuntimeException('Multi-pack-index claims to have no objects');
        }

        for ($i = 0; $i < $this->objectCount - 1; $i++) {
            if (strcmp($this->oids[$i], $this->oids[$i + 1]) >= 0) {
                throw new \RuntimeException("Multi-pack-index object ids are out of order at entry {$i}");
            }
        }

        foreach ($this->offsets as $index => $offset) {
            if ($offset['packIndex'] >= $this->packCount) {
                throw new \RuntimeException("Multi-pack-index entry {$index} references missing pack index {$offset['packIndex']}");
            }
        }

        return $checksum;
    }

    /**
     * @return list<string>
     */
    public function indexNames(): array
    {
        return $this->indexNames;
    }

    /**
     * @return list<string>
     */
    public function packNames(): array
    {
        return $this->indexNames;
    }

    public function oidAtIndex(int $index): string
    {
        if ($index < 0 || $index >= $this->objectCount) {
            throw new \OutOfBoundsException("Multi-pack-index entry {$index} is out of bounds");
        }

        return $this->oids[$index];
    }

    /**
     * @return array{packIndex:int,packOffset:int}
     */
    public function packIdAndPackOffsetAtIndex(int $index): array
    {
        if ($index < 0 || $index >= $this->objectCount) {
            throw new \OutOfBoundsException("Multi-pack-index entry {$index} is out of bounds");
        }

        return $this->offsets[$index];
    }

    public function entryAt(int $index): MultiPackIndexEntry
    {
        $offset = $this->packIdAndPackOffsetAtIndex($index);

        return new MultiPackIndexEntry($this->oids[$index], $offset['packIndex'], $offset['packOffset'], $index);
    }

    public function lookup(string $oid): ?MultiPackIndexEntry
    {
        $oid = strtolower($oid);
        $hexLength = $this->hashBytes * 2;
        if (preg_match('/^[0-9a-f]{' . $hexLength . '}$/', $oid) !== 1) {
            throw new \InvalidArgumentException("Lookup object id must be a {$hexLength}-character {$this->hashName} hex string");
        }

        $firstByte = hexdec(substr($oid, 0, 2));
        $lower = $firstByte === 0 ? 0 : $this->fanout[$firstByte - 1];
        $upper = $this->fanout[$firstByte];

        while ($lower < $upper) {
            $middle = intdiv($lower + $upper, 2);
            $comparison = strcmp($oid, $this->oids[$middle]);
            if ($comparison === 0) {
                return $this->entryAt($middle);
            }
            if ($comparison < 0) {
                $upper = $middle;
            } else {
                $lower = $middle + 1;
            }
        }

        return null;
    }

    /**
     * @return array{status:'missing',candidateRange:array{start:int,end:int}}|array{status:'ambiguous',matches:list<int>,candidateRange:array{start:int,end:int}}|array{status:'found',entry:MultiPackIndexEntry,candidateRange:array{start:int,end:int}}
     */
    public function lookupPrefix(string $prefix): array
    {
        $prefix = $this->normalizePrefix($prefix);
        ['matches' => $matches, 'candidateRange' => $candidateRange] = $this->matchingPrefixIndexes($prefix);

        if ($matches === []) {
            return ['status' => 'missing', 'candidateRange' => $candidateRange];
        }
        if (count($matches) > 1) {
            return ['status' => 'ambiguous', 'matches' => $matches, 'candidateRange' => $candidateRange];
        }

        return ['status' => 'found', 'entry' => $this->entryAt($matches[0]), 'candidateRange' => $candidateRange];
    }

    public function disambiguatePrefix(string $oid, int $minimumHexLength): ?string
    {
        $hexLength = $this->hashBytes * 2;
        if (preg_match('/^[0-9a-fA-F]{' . $hexLength . '}$/', $oid) !== 1) {
            throw new \InvalidArgumentException("Disambiguation object id must be a {$hexLength}-character {$this->hashName} hex string");
        }
        if ($minimumHexLength < 4 || $minimumHexLength > $hexLength) {
            throw new \InvalidArgumentException("Disambiguation prefix length must be between 4 and {$hexLength} hexadecimal characters");
        }

        $oid = strtolower($oid);
        if ($minimumHexLength === $hexLength) {
            return $this->lookup($oid) === null ? null : $oid;
        }

        for ($length = $minimumHexLength; $length < $hexLength; $length++) {
            $prefix = substr($oid, 0, $length);
            $result = $this->lookupPrefix($prefix);
            if ($result['status'] === 'missing') {
                return null;
            }
            if ($result['status'] === 'found') {
                return $prefix;
            }
        }

        return $oid;
    }

    /**
     * @return list<MultiPackIndexEntry>
     */
    public function entries(): array
    {
        $entries = [];
        for ($i = 0; $i < $this->objectCount; $i++) {
            $entries[] = $this->entryAt($i);
        }

        return $entries;
    }

    /**
     * @return array<string,array{start:int,end:int}>
     */
    private static function readChunkTable(string $bytes, int $chunkCount): array
    {
        $length = strlen($bytes);
        $tableBytes = ($chunkCount + 1) * self::TOC_ENTRY_BYTES;
        if ($length < self::HEADER_BYTES + $tableBytes) {
            throw new \InvalidArgumentException("Multi-pack-index table of contents is truncated for {$chunkCount} chunks");
        }

        $entries = [];
        $offset = self::HEADER_BYTES;
        for ($i = 0; $i < $chunkCount + 1; $i++) {
            $id = substr($bytes, $offset, 4);
            $offset += 4;
            $chunkOffset = self::readUInt64($bytes, $offset);
            $entries[] = ['id' => $id, 'offset' => $chunkOffset];
        }

        if ($entries[$chunkCount]['id'] !== self::CHUNK_SENTINEL) {
            throw new \InvalidArgumentException('Multi-pack-index chunk table is missing the sentinel entry');
        }

        $chunks = [];
        for ($i = 0; $i < $chunkCount; $i++) {
            $id = $entries[$i]['id'];
            if ($id === self::CHUNK_SENTINEL) {
                throw new \InvalidArgumentException("Multi-pack-index sentinel appeared before chunk {$i}");
            }
            if (isset($chunks[$id])) {
                throw new \InvalidArgumentException("Multi-pack-index chunk {$id} was encountered more than once");
            }

            $start = $entries[$i]['offset'];
            $end = $entries[$i + 1]['offset'];
            if ($start > $length || $end > $length) {
                throw new \InvalidArgumentException('Multi-pack-index chunk offset points past the file');
            }
            if ($end < $start) {
                throw new \InvalidArgumentException('Multi-pack-index chunk offsets must be ordered');
            }
            $chunks[$id] = ['start' => $start, 'end' => $end];
        }

        return $chunks;
    }

    /**
     * @param array<string,array{start:int,end:int}> $chunks
     */
    private static function requiredChunk(string $bytes, array $chunks, string $id): string
    {
        if (!isset($chunks[$id])) {
            throw new \InvalidArgumentException("Required multi-pack-index chunk {$id} was not found");
        }

        return self::slice($bytes, $chunks[$id]);
    }

    /**
     * @param array{start:int,end:int} $range
     */
    private static function slice(string $bytes, array $range): string
    {
        return substr($bytes, $range['start'], $range['end'] - $range['start']);
    }

    /**
     * @return list<string>
     */
    private static function readIndexNames(string $chunk, int $packCount, ?int $allocationLimitBytes): array
    {
        if ($allocationLimitBytes !== null && $packCount > intdiv($allocationLimitBytes, 8)) {
            throw new \InvalidArgumentException('Multi-pack-index pack name table exceeds the configured allocation limit');
        }

        $names = [];
        $cursor = 0;
        for ($i = 0; $i < $packCount; $i++) {
            $nul = strpos($chunk, "\0", $cursor);
            if ($nul === false) {
                throw new \InvalidArgumentException('Each multi-pack-index pack path name must be terminated with a null byte');
            }
            $name = substr($chunk, $cursor, $nul - $cursor);
            if ($allocationLimitBytes !== null && strlen($name) > $allocationLimitBytes) {
                throw new \InvalidArgumentException('Multi-pack-index pack name exceeds the configured allocation limit');
            }
            if ($names !== [] && strcmp($names[count($names) - 1], $name) >= 0) {
                throw new \InvalidArgumentException('Multi-pack-index pack names must be ordered alphabetically');
            }
            $names[] = $name;
            $cursor = $nul + 1;
        }

        $trailer = substr($chunk, $cursor);
        if ($trailer !== '' && trim($trailer, "\0") !== '') {
            throw new \InvalidArgumentException('Multi-pack-index pack names chunk has non-padding bytes after all paths');
        }

        return $names;
    }

    /**
     * @return list<int>
     */
    private static function readFanout(string $chunk): array
    {
        if (strlen($chunk) !== self::FANOUT_ENTRIES * self::UINT32_BYTES) {
            throw new \InvalidArgumentException('Multi-pack-index fan-out chunk does not have the expected size');
        }

        $fanout = [];
        $offset = 0;
        for ($i = 0; $i < self::FANOUT_ENTRIES; $i++) {
            $fanout[] = self::readUInt32($chunk, $offset);
        }
        self::assertMonotonicFanout($fanout);

        return $fanout;
    }

    private function normalizePrefix(string $prefix): string
    {
        $prefix = strtolower($prefix);
        $hexLength = $this->hashBytes * 2;
        if (preg_match('/^[0-9a-f]{4,' . $hexLength . '}$/', $prefix) !== 1) {
            throw new \InvalidArgumentException("Lookup prefix must be 4 to {$hexLength} hexadecimal characters");
        }

        return $prefix;
    }

    /**
     * @return array{matches:list<int>,candidateRange:array{start:int,end:int}}
     */
    private function matchingPrefixIndexes(string $prefix): array
    {
        $firstByte = hexdec(substr($prefix, 0, 2));
        $lower = $firstByte === 0 ? 0 : $this->fanout[$firstByte - 1];
        $upper = $this->fanout[$firstByte];

        $found = null;
        while ($lower < $upper) {
            $middle = intdiv($lower + $upper, 2);
            $comparison = self::comparePrefix($prefix, $this->oids[$middle]);
            if ($comparison === 0) {
                $found = $middle;
                break;
            }
            if ($comparison < 0) {
                $upper = $middle;
            } else {
                $lower = $middle + 1;
            }
        }

        if ($found === null) {
            return ['matches' => [], 'candidateRange' => ['start' => 0, 'end' => 0]];
        }

        $start = $found;
        while ($start > 0 && self::comparePrefix($prefix, $this->oids[$start - 1]) === 0) {
            $start--;
        }
        $end = $found + 1;
        while ($end < $this->objectCount && self::comparePrefix($prefix, $this->oids[$end]) === 0) {
            $end++;
        }

        return [
            'matches' => range($start, $end - 1),
            'candidateRange' => [
                'start' => $start,
                'end' => $end,
            ],
        ];
    }

    private static function comparePrefix(string $prefix, string $oid): int
    {
        $comparison = strncmp($prefix, $oid, strlen($prefix));
        if ($comparison < 0) {
            return -1;
        }
        if ($comparison > 0) {
            return 1;
        }

        return 0;
    }

    /**
     * @return list<string>
     */
    private static function readOids(string $chunk, int $objectCount, int $hashBytes): array
    {
        $expected = $objectCount * $hashBytes;
        if (strlen($chunk) !== $expected) {
            throw new \InvalidArgumentException('Multi-pack-index object-id chunk does not have the expected size');
        }

        $oids = [];
        for ($i = 0; $i < $objectCount; $i++) {
            $oids[] = bin2hex(substr($chunk, $i * $hashBytes, $hashBytes));
        }

        return $oids;
    }

    /**
     * @param null|list<int> $largeOffsets
     * @return list<array{packIndex:int,packOffset:int}>
     */
    private static function readOffsets(string $chunk, int $objectCount, ?array $largeOffsets): array
    {
        $expected = $objectCount * (self::UINT32_BYTES + self::UINT32_BYTES);
        if (strlen($chunk) !== $expected) {
            throw new \InvalidArgumentException('Multi-pack-index object-offset chunk does not have the expected size');
        }

        $entries = [];
        $cursor = 0;
        for ($i = 0; $i < $objectCount; $i++) {
            $packIndex = self::readUInt32($chunk, $cursor);
            $offset32 = self::readUInt32($chunk, $cursor);
            $packOffset = $offset32;
            if (($offset32 & self::LARGE_OFFSET_FLAG) !== 0 && $largeOffsets !== null) {
                $largeOffsetIndex = $offset32 ^ self::LARGE_OFFSET_FLAG;
                if (!array_key_exists($largeOffsetIndex, $largeOffsets)) {
                    throw new \InvalidArgumentException("Multi-pack-index references large offset {$largeOffsetIndex}, but it is missing");
                }
                $packOffset = $largeOffsets[$largeOffsetIndex];
            }
            $entries[] = ['packIndex' => $packIndex, 'packOffset' => $packOffset];
        }

        return $entries;
    }

    /**
     * @return list<int>
     */
    private static function readLargeOffsets(string $chunk): array
    {
        if (strlen($chunk) % self::UINT64_BYTES !== 0) {
            throw new \InvalidArgumentException('Multi-pack-index large-offset chunk is not 64-bit aligned');
        }

        $offsets = [];
        for ($cursor = 0; $cursor < strlen($chunk);) {
            $offsets[] = self::readUInt64($chunk, $cursor);
        }

        return $offsets;
    }

    /**
     * @param list<int> $fanout
     */
    private static function assertMonotonicFanout(array $fanout): void
    {
        $previous = 0;
        foreach ($fanout as $index => $value) {
            if ($value < $previous) {
                throw new \InvalidArgumentException("Multi-pack-index fan-out table must be monotonically increasing at index {$index}");
            }
            $previous = $value;
        }
    }

    private static function readUInt32(string $bytes, int &$offset): int
    {
        $chunk = substr($bytes, $offset, self::UINT32_BYTES);
        if (strlen($chunk) !== self::UINT32_BYTES) {
            throw new \InvalidArgumentException('Multi-pack-index ended while reading a 32-bit value');
        }
        $offset += self::UINT32_BYTES;

        return (int) unpack('N', $chunk)[1];
    }

    private static function readUInt32At(string $bytes, int $offset): int
    {
        return self::readUInt32($bytes, $offset);
    }

    private static function readUInt64(string $bytes, int &$offset): int
    {
        $chunk = substr($bytes, $offset, self::UINT64_BYTES);
        if (strlen($chunk) !== self::UINT64_BYTES) {
            throw new \InvalidArgumentException('Multi-pack-index ended while reading a 64-bit value');
        }
        $offset += self::UINT64_BYTES;
        $parts = unpack('Nhigh/Nlow', $chunk);
        $high = (int) $parts['high'];
        $low = (int) $parts['low'];
        if ($high > 0x7fffffff) {
            throw new \InvalidArgumentException('Multi-pack-index offset exceeds this PHP integer platform');
        }

        return $high * 4294967296 + $low;
    }
}
